# Secure Container Images – DevSecOps Pipeline

[![Security Dashboard](https://img.shields.io/badge/Security-Dashboard-blue?style=flat&logo=github)](https://konrading.github.io/sem5-pw-cis/)
[![SBOM Available](https://img.shields.io/badge/SBOM-Available-green?style=flat&logo=github)](https://github.com/KonradinG/sem5-pw-cis/network/dependencies)
[![Trivy Scanning](https://img.shields.io/badge/Trivy-Weekly%20Scan-orange?style=flat&logo=aqua)](https://github.com/KonradinG/sem5-pw-cis/actions/workflows/security_summary.yml)
[![Documentation](https://img.shields.io/badge/Documentation-Available-brightgreen?style=flat&logo=readthedocs)](DOCUMENTATION.md)

A fully automated DevSecOps pipeline for hardened container images with a strong focus on vulnerability management, automated dependency updates, and transparent security reporting. The goal is a reproducible, auditable, and customer‑ready example of modern container security practices.

## Table of Contents

- [Overview](#overview)
- [Included Images](#included-images)
- [Pipeline](#pipeline)
- [Implemented Controls](#implemented-controls)
- [Security Reporting](#security-reporting)
- [Repository Structure](#repository-structure)
- [Planned Enhancements](#planned-enhancements)
- [Target Audience](#target-audience)

## Overview

All images are built, scanned, and monitored automatically for known vulnerabilities. Dependency updates are proposed via Renovate, compared against the current CVE baseline, and merged only when security improves.

## Included Images

| Image                      | Description                                                                                                                         |
| -------------------------- | ----------------------------------------------------------------------------------------------------------------------------------- |
| `postgres-18`              | Hardened PostgreSQL container image                                                                                                 |
| `php-mysql` / `8.1-apache` | PHP and MySQL container image with security hardening                                                                               |
| `python-3.14`              | Hardened Python 3.14 container image                                                                                                |
| `vote`, `result`, `worker` | **Test images** from example-voting-app (suggested by Prof. Nestler, [source](https://github.com/dockersamples/example-voting-app)) |

**Note on Test Images:** The Example Voting App images (`vote`, `result`, `worker`) serve exclusively for demonstration purposes and intentionally contain known vulnerabilities. The CVE-Gate does not block these images to demonstrate the full pipeline functionality (scanning, issue tracking, reporting) even with critical vulnerabilities. All vulnerabilities are nevertheless fully captured, tracked in audit logs, and displayed on the security dashboard. Only the hard-blocking mechanism is exempted for these test images.

## Pipeline

The security pipeline runs on push and on schedule. It scans locally, builds and scans images, opens/updates security issues, and auto‑merges safe dependency updates.

For detailed pipeline architecture and technical implementation, see [SECURITY_PIPELINE.md](SECURITY_PIPELINE.md).

## Implemented Controls

### Container Vulnerability Scanning

- Scanner: Trivy
- Triggers: on every push to `main` and on scheduled runs
- Severities evaluated: MEDIUM, HIGH, CRITICAL

### Automated Security Issues

- Creates/updates a GitHub Issue automatically when MEDIUM+ vulnerabilities are detected
- Appends dated scan blocks to the existing issue
- Auto‑closes the issue when no MEDIUM+ vulnerabilities remain

### Automated Dependency Updates (Renovate)

- Manages Docker base images, digests, and patch versions
- Major version updates disabled; security‑relevant updates prioritized
- Auto‑merge only when all security checks pass

### Hard Blocking Rules

| Condition                       | Behaviour                    |
| ------------------------------- | ---------------------------- |
| CRITICAL vulnerability present  | Auto‑merge is blocked        |
| Vulnerability count increases   | Auto‑merge is blocked        |
| Vulnerability count decreases   | Auto‑merge enabled (GraphQL) |
| Production images with CRITICAL | Deployment blocked           |
| Test images with CRITICAL       | Skipped (demo only)          |

## Security Reporting

### Weekly Security Summary & Dashboard

The live security dashboard is available at: **[Security Dashboard](https://konrading.github.io/sem5-pw-cis/)**

The file `docs/data/security-summary.json` is generated weekly (and can be run on-demand) and powers the web dashboard in `docs/`.

**Dashboard Features:**

- Risk Index visualization with trend chart
- Real-time vulnerability overview (CRITICAL, HIGH, MEDIUM, LOW)
- Per-image severity breakdown
- Direct links to detailed CVE analysis
- Integration with GitHub Issues for security tracking

**Important Note on Dashboard:** The dashboard displays all 6 images including the Example Voting App test images (`vote`, `result`, `worker`). These test images intentionally contain known vulnerabilities to demonstrate the full pipeline capability. See the information box at the top of the dashboard for details on CVE-Gate exemptions.

Current JSON structure (version 3):

```
{
	"generatedAt": "UTC timestamp",
	"period": {"start": "YYYY-MM-DD", "end": "YYYY-MM-DD"},
	"images": [
		{"name": "image-name", "critical": 0, "high": 1, "medium": 2, "low": 5, "unknown": 0}
	],
	"totals": {"critical": 0, "high": 1, "medium": 2, "low": 5, "unknown": 0},
	"delta": {"critical": 0, "high": -1, "medium": 0, "low": 2, "unknown": 0},
	"direction": "better|worse|stable",
	"trend": [
		{"date": "2025-11-20", "critical": 0, "high": 1, "medium": 2},
		{"date": "2025-11-27", "critical": 0, "high": 0, "medium": 2}
	],
	"riskIndex": 7,
	"riskIndexMethod": "(critical*10 + high*5 + medium*2) / 300 * 100; capped at 100",
	"version": 3,
	"sources": ["reports/trivy/php-mysql.json", "reports/trivy/postgres.json"]
}
```

The dashboard expects flattened image severity fields and a `trend` array for Chart.js.

### Generation Script

`scripts/generate_security_summary.py` can be invoked manually:

```bash
python scripts/generate_security_summary.py \
	--trivy reports/trivy/php-mysql.json reports/trivy/postgres.json \
	--previous docs/data/security-summary.json \
	--output docs/data/security-summary.json
```

If `--trivy` paths are omitted it auto-discovers JSON reports in `--trivy-dir` (default `reports/trivy`). Missing or invalid reports produce a placeholder entry.

### Automated Workflow

The GitHub Actions workflow `.github/workflows/security_summary.yml` runs weekly (Monday 03:00 UTC) and on manual dispatch:

1. Builds the container images.
2. Scans them with Trivy (JSON output stored under `reports/trivy/`).
3. Generates/updates `security-summary.json` with historical trend retention (last 26 entries by default).
4. Commits and pushes changes if there are modifications.

Adjust the cron schedule or history retention via workflow or script parameters as needed.

### Audit Logs

- Individual, chronological logs per container image
- Suitable for audits, reviews, and customer reporting
- Directory: `audit-logs/`

## DevSecOps Principles

- Shift‑left security: detect vulnerabilities early in the pipeline
- Full automation: minimal manual intervention
- Transparency: all security decisions visible in‑repo
- Reproducibility: every scan and decision is traceable

## Repository Structure

| Path                  | Purpose                           |
| --------------------- | --------------------------------- |
| `.github/workflows/`  | CI/CD and security pipelines      |
| `images/`             | Dockerfiles for container images  |
| `audit-logs/`         | Long‑term security logs           |
| `cve-baseline/`       | Reference vulnerability baselines |
| `SECURITY_SUMMARY.md` | Security documentation            |

## Implemented Features

- ✅ Web‑based security dashboard (GitHub Pages)
- ✅ Graphical vulnerability trend visualization (Chart.js)
- ✅ Weekly automated security summary reports
- ✅ SBOM generation (CycloneDX format, monthly)
- ✅ GitHub Security tab integration (SARIF upload)

### SBOM generation

The SBOM is generated in CI using Anchore's `sbom-action` and written to `sbom/sbom-cyclonedx.json`. The folder `sbom/` is tracked in the repo so the action has a valid destination path.

## Planned Enhancements

- Static Application Security Testing (SAST)
- Config and infrastructure misconfiguration scanning
- SBOM diff comparison (before/after dependency updates)
- Exportable customer‑ready PDF security reports

## Target Audience

- Software developers
- DevSecOps and security engineers
- Auditors and customers
- Education and demonstrations

---

This repository demonstrates how to implement container security in a fully automated, auditable, and customer‑friendly way using modern DevSecOps practices. It serves as both a reference implementation and an educational example for secure container pipelines.
