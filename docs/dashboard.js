// Weekly Security Dashboard Logic
// Loads security-summary.json and renders:
//  - Current risk index + direction
//  - Severity totals with delta vs last week
//  - Per-image severity breakdown
//  - Risk index trend over up to last 4 weeks (recomputed from critical/high/medium)

(function init() {
  const SUMMARY_PATH = "data/security-summary.json";

  // DOM references
  const riskIndexEl = document.getElementById("riskIndex");
  const riskDirectionEl = document.getElementById("riskDirection");
  const periodEl = document.getElementById("period");
  const lastUpdatedEl = document.getElementById("lastUpdated");
  const totalsBody = document.getElementById("totalsBody");
  const imagesTableBody = document.querySelector("#images-table tbody");
  const trendCanvas = document.getElementById("riskTrendChart");
  const summaryText = document.getElementById("summaryText");
  const recommendationsText = document.getElementById("recommendationsText");

  // Constants for risk index calculation (mirrors server-side script)
  const WEIGHTS = { critical: 10, high: 5, medium: 2 }; // low & unknown ignored for index
  const MAX_SCORE = 300; // normalization baseline

  // Helper: compute risk index from counts
  function computeRiskIndex(c, h, m) {
    const raw = c * WEIGHTS.critical + h * WEIGHTS.high + m * WEIGHTS.medium;
    return raw <= 0 ? 0 : Math.min(100, Math.round((raw / MAX_SCORE) * 100));
  }

  // Helper: decide color class based on risk value
  function riskClass(val) {
    if (val >= 50) return "risk-high";
    if (val >= 20) return "risk-medium";
    return "risk-low";
  }

  function renderTotals(totals, delta) {
    const severities = ["critical", "high", "medium", "low", "unknown"];
    severities.forEach(sev => {
      const tr = document.createElement("tr");
      const d = delta && typeof delta[sev] === "number" ? delta[sev] : 0;
      const sign = d > 0 ? "+" : "";
      tr.innerHTML = `
        <td class="sev sev-${sev}">${sev.toUpperCase()}</td>
        <td>${totals[sev] ?? 0}</td>
        <td class="delta ${d>0? 'worse': d<0? 'better':'stable'}">${sign}${d}</td>
      `;
      totalsBody.appendChild(tr);
    });
  }

  function renderImages(images) {
    images.forEach(img => {
      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td>${img.name}</td>
        <td class="sev sev-critical">${img.critical ?? 0}</td>
        <td class="sev sev-high">${img.high ?? 0}</td>
        <td class="sev sev-medium">${img.medium ?? 0}</td>
        <td class="sev sev-low">${img.low ?? 0}</td>
        <td class="sev sev-unknown">${img.unknown ?? 0}</td>
      `;
      imagesTableBody.appendChild(tr);
    });
  }

  function renderTrend(trendArr) {
    if (!Array.isArray(trendArr)) return;
    // Last up to 4 entries (already chronological)
    const slice = trendArr.slice(-4);
    const labels = slice.map(e => e.date);
    const riskValues = slice.map(e => computeRiskIndex(e.critical, e.high, e.medium));

    new Chart(trendCanvas, {
      type: "line",
      data: {
        labels,
        datasets: [
          {
            label: "Risk Index",
            data: riskValues,
            borderColor: "#c62828",
            backgroundColor: "rgba(198,40,40,0.15)",
            tension: 0.25,
            fill: true,
          },
        ],
      },
      options: {
        responsive: true,
        scales: {
          y: {
            beginAtZero: true,
            suggestedMax: 100,
            title: { display: true, text: "Risk Index" },
            grid: { color: "#eee" },
          },
          x: { grid: { display: false } },
        },
        plugins: {
          legend: { display: true },
          tooltip: { intersect: false, mode: "index" },
        },
      },
    });
  }

  async function load() {
    try {
      const res = await fetch(SUMMARY_PATH, { cache: "no-store" });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();

      // Render period
      const start = data.period?.start ?? "?";
      const end = data.period?.end ?? "?";
      periodEl.textContent = `Woche: ${start} bis ${end}`;

      // Last updated timestamp
      if (data.generatedAt && lastUpdatedEl) {
        const date = new Date(data.generatedAt);
        const formatted = date.toLocaleString('de-DE', { 
          year: 'numeric', month: '2-digit', day: '2-digit',
          hour: '2-digit', minute: '2-digit', timeZoneName: 'short'
        });
        lastUpdatedEl.querySelector('span').textContent = formatted;
      }

      // Risk index
      const riskIndex = typeof data.riskIndex === "number" ? data.riskIndex : 0;
      riskIndexEl.textContent = riskIndex;
      riskIndexEl.classList.add(riskClass(riskIndex));
      riskDirectionEl.textContent = `Trend: ${data.direction ?? '–'}`;
      riskDirectionEl.classList.add(`dir-${data.direction}`);

      // Totals & delta
      renderTotals(data.totals || {}, data.delta || {});

      // Images
      renderImages(Array.isArray(data.images) ? data.images : []);

      // Trend (historical risk index from severity snapshot)
      renderTrend(Array.isArray(data.trend) ? data.trend : []);

      // Summary & recommendations
      summaryText.textContent = data.summary || 'Keine Zusammenfassung verfügbar.';
      recommendationsText.textContent = data.recommendations || 'Keine Empfehlungen verfügbar.';
    } catch (err) {
      console.error("Fehler beim Laden der Security Summary:", err);
      const container = document.querySelector("main") || document.body;
      const div = document.createElement("div");
      div.className = "error-box";
      div.textContent = `Security Summary konnte nicht geladen werden (${err.message}).`;
      container.prepend(div);
    }
  }

  load();
})();
