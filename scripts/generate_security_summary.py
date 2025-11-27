import json
import os
import argparse
from datetime import datetime, timedelta
from typing import Dict, Any, List

OUTPUT_PATH = os.path.join("docs", "data", "security-summary.json")
DEFAULT_TRIVY_DIR = os.environ.get("TRIVY_REPORT_DIR", os.path.join("reports", "trivy"))

# Adjustable weights for future tuning
WEIGHTS = {"critical": 10, "high": 5, "medium": 2}
MAX_SCORE = 300  # Normalization baseline for riskIndex formula


def safe_load_json(path: str) -> Any:
    try:
        with open(path, "r", encoding="utf-8") as f:
            return json.load(f)
    except FileNotFoundError:
        return None
    except json.JSONDecodeError:
        print(f"Warnung: Ungültiges JSON übersprungen: {path}")
        return None


def find_trivy_reports(directory: str) -> List[str]:
    if not os.path.isdir(directory):
        return []
    files = []
    for entry in os.listdir(directory):
        if entry.lower().endswith(".json"):
            files.append(os.path.join(directory, entry))
    return sorted(files)


def parse_trivy_report(path: str) -> Dict[str, Any]:
    data = safe_load_json(path)
    if not data:
        return {}
    artifact = data.get("ArtifactName") or data.get("Target") or os.path.splitext(os.path.basename(path))[0]
    counts = {"critical": 0, "high": 0, "medium": 0, "low": 0, "unknown": 0}
    results = data.get("Results", [])
    for res in results:
        for v in res.get("Vulnerabilities", []) or []:
            sev = (v.get("Severity") or "UNKNOWN").lower()
            if sev in counts:
                counts[sev] += 1
            else:
                counts["unknown"] += 1
    # Flattened structure for dashboard convenience
    return {"name": artifact, **counts}


def collect_image_vulnerabilities(report_paths: List[str]) -> List[Dict[str, Any]]:
    images: Dict[str, Dict[str, int]] = {}
    for rpt in report_paths:
        parsed = parse_trivy_report(rpt)
        if not parsed:
            continue
        name = parsed["name"]
        if name not in images:
            images[name] = {"critical": 0, "high": 0, "medium": 0, "low": 0, "unknown": 0}
        for sev in ["critical", "high", "medium", "low", "unknown"]:
            images[name][sev] += parsed.get(sev, 0)
    return [{"name": name, **vulns} for name, vulns in sorted(images.items())]


def load_previous_summary(path: str) -> Dict[str, Any]:
    data = safe_load_json(path) or {}
    # Migration: if images have nested structure
    migrated_images = []
    for img in data.get("images", []):
        if "vulnerabilities" in img:
            migrated_images.append({"name": img.get("name", "unknown"), **img["vulnerabilities"]})
        else:
            migrated_images.append(img)
    data["images"] = migrated_images
    return data


def compute_totals(images: List[Dict[str, Any]]) -> Dict[str, int]:
    totals = {"critical": 0, "high": 0, "medium": 0, "low": 0, "unknown": 0}
    for img in images:
        for sev in totals.keys():
            totals[sev] += int(img.get(sev, 0))
    return totals


def compute_risk_index(totals: Dict[str, int]) -> int:
    score = (
        totals.get("critical", 0) * WEIGHTS["critical"]
        + totals.get("high", 0) * WEIGHTS["high"]
        + totals.get("medium", 0) * WEIGHTS["medium"]
    )
    # Normalize and cap
    normalized = 0 if score <= 0 else min(100, round((score / MAX_SCORE) * 100))
    return normalized


def determine_direction(current_totals: Dict[str, int], previous_totals: Dict[str, int]) -> str:
    current_sum = current_totals["critical"] + current_totals["high"] + current_totals["medium"]
    previous_sum = previous_totals.get("critical", 0) + previous_totals.get("high", 0) + previous_totals.get("medium", 0)
    if current_sum > previous_sum:
        return "worse"
    if current_sum < previous_sum:
        return "better"
    return "stable"


def build_summary(images: List[Dict[str, Any]], previous: Dict[str, Any], history_limit: int, min_history: int, backfill_mode: str, sources: List[str]) -> Dict[str, Any]:
    today = datetime.utcnow().date()
    period_end = today
    period_start = today - timedelta(days=7)

    totals = compute_totals(images)
    previous_totals = previous.get("totals", {"critical": 0, "high": 0, "medium": 0, "low": 0, "unknown": 0})
    risk_index = compute_risk_index(totals)
    direction = determine_direction(totals, previous_totals)

    # Trend history (array of weekly snapshots)
    prev_trend = previous.get("trend", [])
    if isinstance(prev_trend, dict):  # migrate old object-style trend
        prev_trend = []
    new_entry = {
        "date": str(period_end),
        "critical": totals["critical"],
        "high": totals["high"],
        "medium": totals["medium"],
    }
    trend_history = prev_trend + [new_entry]

    # Synthetic backfill to guarantee minimum history length
    if min_history and len(trend_history) < min_history:
        missing = min_history - len(trend_history)
        # earliest existing entry after adding new_entry is index 0
        earliest = trend_history[0]
        base_values = earliest if backfill_mode == "repeat" else {"critical": 0, "high": 0, "medium": 0}
        earliest_date = datetime.strptime(earliest["date"], "%Y-%m-%d").date()
        synthetic_entries: List[Dict[str, Any]] = []
        for i in range(missing, 0, -1):
            d = earliest_date - timedelta(days=7 * i)
            synthetic_entries.append({
                "date": str(d),
                "critical": base_values.get("critical", 0),
                "high": base_values.get("high", 0),
                "medium": base_values.get("medium", 0),
            })
        trend_history = synthetic_entries + trend_history

    if history_limit and len(trend_history) > history_limit:
        trend_history = trend_history[-history_limit:]

    # Deduplicate by date (keep latest occurrence for each date)
    dedup: Dict[str, Dict[str, Any]] = {}
    for entry in trend_history:
        dedup[entry["date"]] = entry  # later entries overwrite earlier ones
    # Sort ascending by date string
    trend_history = [dedup[d] for d in sorted(dedup.keys())]

    # Re-apply history limit after dedup (in case removal changed ordering/length)
    if history_limit and len(trend_history) > history_limit:
        trend_history = trend_history[-history_limit:]

    delta = {
        "critical": totals["critical"] - previous_totals.get("critical", 0),
        "high": totals["high"] - previous_totals.get("high", 0),
        "medium": totals["medium"] - previous_totals.get("medium", 0),
        "low": totals["low"] - previous_totals.get("low", 0),
        "unknown": totals["unknown"] - previous_totals.get("unknown", 0),
    }

    if totals["critical"] + totals["high"] + totals["medium"] == 0:
        summary_txt = "Keine sicherheitsrelevanten Schwachstellen (MEDIUM+) in dieser Periode erkannt."
        findings = "Aktuell keine offenen Findings mit Merge-Relevanz."
        recommendations = "Weiter wöchentliche Scans und Renovate-PRs überwachen; keine Maßnahmen nötig."
    else:
        summary_txt = "Sicherheitsrelevante Schwachstellen erkannt – siehe Detailzahlen."
        findings = "Analyse der MEDIUM/HIGH/CRITICAL Findings erforderlich." if totals["high"] + totals["critical"] > 0 else "Nur MEDIUM-Findings vorhanden."
        recommendations = "Priorisiere Behebung von HIGH/CRITICAL; Re-Scan nach Updates durchführen." if totals["high"] + totals["critical"] > 0 else "Patch-Updates für MEDIUM-Findings zeitnah einplanen."

    data = {
        "generatedAt": datetime.utcnow().isoformat(timespec="seconds") + "Z",
        "period": {"start": str(period_start), "end": str(period_end)},
        "images": images if images else [{"name": "(no-trivy-reports)", "critical": 0, "high": 0, "medium": 0, "low": 0, "unknown": 0}],
        "totals": totals,
        "delta": delta,
        "direction": direction,
        "trend": trend_history,
        "summary": summary_txt,
        "findings": findings,
        "recommendations": recommendations,
        "riskIndex": risk_index,
        "riskIndexMethod": "(critical*10 + high*5 + medium*2) / 300 * 100; capped at 100",
        "version": 3,
        "sources": sources,
    }
    return data


def write_json(path: str, data: Dict[str, Any]) -> None:
    os.makedirs(os.path.dirname(path), exist_ok=True)
    with open(path, "w", encoding="utf-8") as f:
        json.dump(data, f, ensure_ascii=False, indent=2)


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Generate aggregated security summary from Trivy reports.")
    parser.add_argument("--trivy", nargs="*", help="Explicit Trivy JSON report files (optional).")
    parser.add_argument("--trivy-dir", default=DEFAULT_TRIVY_DIR, help="Directory to auto-discover Trivy JSON reports.")
    parser.add_argument("--previous", default=OUTPUT_PATH, help="Path to previous summary JSON for trend continuation.")
    parser.add_argument("--output", default=OUTPUT_PATH, help="Output path for new summary JSON.")
    parser.add_argument("--history-limit", type=int, default=26, help="Max number of historical entries (trend) to retain.")
    parser.add_argument("--min-history", type=int, default=4, help="Ensure at least this many trend points (synthetic backfill if needed).")
    parser.add_argument("--backfill-mode", choices=["repeat", "zeros"], default="repeat", help="Backfill strategy for synthetic history: repeat earliest values or use zeros.")
    return parser.parse_args()


def main():
    args = parse_args()
    previous = load_previous_summary(args.previous)
    report_paths = args.trivy if args.trivy else find_trivy_reports(args.trivy_dir)
    images = collect_image_vulnerabilities(report_paths)
    summary = build_summary(
        images,
        previous,
        args.history_limit,
        args.min_history,
        args.backfill_mode,
        report_paths or [args.trivy_dir],
    )
    write_json(args.output, summary)
    print(
        f"Security summary written to {args.output} (riskIndex={summary['riskIndex']}, images={len(summary['images'])}, trendPoints={len(summary['trend'])})"
    )


if __name__ == "__main__":
    main()
