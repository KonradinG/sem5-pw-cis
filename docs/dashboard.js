// Weekly Security Dashboard Logic
// Loads security-summary.json and renders:
//  - Current risk index + direction
//  - Severity totals with delta vs last week
//  - Per-image severity breakdown
//  - Risk index trend over up to last 4 weeks (recomputed from critical/high/medium)

(function init() {
  const SUMMARY_PATH = "data/security-summary.json";
  const GH_OWNER = "KonradinG";
  const GH_REPO = "sem5-pw-cis";
  let __pagesPrevStatus = 'unknown';
  let __pagesPollDisabled = false; // stop polling on API 403s to avoid console noise

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
  const GH_CACHE_PATH = "data/gh-cache.json";

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

  // Load open security issues
  loadOpenIssues();

  // Initial check and periodic refresh for GitHub Pages status
  checkPagesStatus();
  setInterval(checkPagesStatus, 60000);

  async function fetchGhCache() {
    try {
      const res = await fetch(GH_CACHE_PATH, { cache: 'no-store' });
      if (!res.ok) return null;
      return await res.json();
    } catch { return null; }
  }

  // Check GitHub Pages build/deploy status and show a banner if updating
  async function checkPagesStatus() {
    if (__pagesPollDisabled) return;
    const banner = document.getElementById("updateBanner");
    const textEl = document.getElementById("updateBannerText");
    if (!banner || !textEl) return;

    try {
      // Prefer cached status written by workflow
      const cache = await fetchGhCache();
      if (cache && cache.pages && cache.pages.status) {
        const status = String(cache.pages.status).toLowerCase();
        if (status === 'queued' || status === 'building') {
          banner.hidden = false;
          banner.classList.remove('update-idle','update-error');
          banner.classList.add('update-running');
          const started = cache.pages.created_at ? new Date(cache.pages.created_at) : null;
          const ago = started ? ` – gestartet vor ${timeAgo(started)}` : '';
          textEl.textContent = `🔄 Seiten-Update läuft (${status})${ago}`;
          __pagesPrevStatus = 'running';
          return;
        }
        if (status === 'built') {
          banner.hidden = false;
          banner.classList.remove('update-running','update-error');
          banner.classList.add('update-idle');
          const finished = cache.pages.updated_at ? new Date(cache.pages.updated_at) : null;
          const ago = finished ? ` – aktualisiert vor ${timeAgo(finished)}` : '';
          textEl.textContent = `✅ Seiten-Status: aktuell${ago}`;
          if (__pagesPrevStatus === 'running' && /github\.io$/.test(location.hostname)) {
            setTimeout(() => location.reload(), 10000);
          }
          __pagesPrevStatus = 'built';
          return;
        }
        if (status === 'errored') {
          banner.hidden = false;
          banner.classList.remove('update-running','update-idle');
          banner.classList.add('update-error');
          textEl.textContent = `⚠️ Seiten-Update fehlgeschlagen`;
          __pagesPrevStatus = 'error';
          return;
        }
        // unknown → hide
        banner.hidden = true;
        return;
      }

      const res = await fetch(`https://api.github.com/repos/${GH_OWNER}/${GH_REPO}/pages/builds/latest`, {
        headers: { 'Accept': 'application/vnd.github+json' }, cache: 'no-store'
      });

      if (res.ok) {
        const data = await res.json();
        const status = String(data.status || '').toLowerCase();
        if (status === 'queued' || status === 'building') {
          banner.hidden = false;
          banner.classList.remove('update-idle','update-error');
          banner.classList.add('update-running');
          const started = data.created_at ? new Date(data.created_at) : null;
          const ago = started ? ` – gestartet vor ${timeAgo(started)}` : '';
          textEl.textContent = `🔄 Seiten-Update läuft (${status})${ago}`;
          __pagesPrevStatus = 'running';
          return;
        }
        if (status === 'built') {
          banner.hidden = false;
          banner.classList.remove('update-running','update-error');
          banner.classList.add('update-idle');
          const finished = data.updated_at ? new Date(data.updated_at) : null;
          const ago = finished ? ` – aktualisiert vor ${timeAgo(finished)}` : '';
          textEl.textContent = `✅ Seiten-Status: aktuell${ago}`;
          if (__pagesPrevStatus === 'running' && /github\.io$/.test(location.hostname)) {
            // Give the CDN a few seconds to propagate, then reload to pick up the new build
            setTimeout(() => location.reload(), 10000);
          }
          __pagesPrevStatus = 'built';
          return;
        }
        if (status === 'errored') {
          banner.hidden = false;
          banner.classList.remove('update-running','update-idle');
          banner.classList.add('update-error');
          textEl.textContent = `⚠️ Seiten-Update fehlgeschlagen`;
          __pagesPrevStatus = 'error';
          return;
        }
        // Unknown → hide
        banner.hidden = true;
        return;
      }

      // If forbidden (403), likely rate-limited or requires auth → stop polling gracefully
      if (res.status === 403) {
        banner.hidden = true;
        __pagesPollDisabled = true;
        console.warn('GitHub API 403 for Pages status; disabling further polling.');
        return;
      }

      // Fallback: if Pages API not available, try to infer from Actions runs
      const runsRes = await fetch(`https://api.github.com/repos/${GH_OWNER}/${GH_REPO}/actions/workflows`, { cache: 'no-store' });
      if (runsRes.ok) {
        const wf = await runsRes.json();
        // Try to find Pages workflow by name
        const pagesWf = (wf.workflows || []).find(w => /pages build and deployment/i.test(w.name || ''));
        if (pagesWf) {
          const r = await fetch(`https://api.github.com/repos/${GH_OWNER}/${GH_REPO}/actions/workflows/${pagesWf.id}/runs?per_page=1`, { cache: 'no-store' });
          if (r.ok) {
            const j = await r.json();
            const run = j.workflow_runs?.[0];
            if (run && (run.status === 'in_progress' || run.status === 'queued')) {
              banner.hidden = false;
              banner.classList.remove('update-idle','update-error');
              banner.classList.add('update-running');
              const started = run.created_at ? new Date(run.created_at) : null;
              const ago = started ? ` – gestartet vor ${timeAgo(started)}` : '';
              textEl.textContent = `🔄 Seiten-Update läuft${ago}`;
              __pagesPrevStatus = 'running';
              return;
            }
          }
        }
      }
      banner.hidden = true;
    } catch (e) {
      const banner = document.getElementById('updateBanner');
      if (banner) {
        banner.hidden = false;
        banner.classList.remove('update-idle','update-running');
        banner.classList.add('update-error');
        const textEl = document.getElementById('updateBannerText');
        if (textEl) textEl.textContent = '⚠️ Status konnte nicht geladen werden';
      }
    }
  }

  /**
   * Load and display open GitHub issues with security label
   */
  async function loadOpenIssues() {
    const container = document.getElementById("issues-container");
    try {
      // Try cached issues first (written by workflow)
      let issues = null;
      const cache = await fetchGhCache();
      if (cache && Array.isArray(cache.issues)) {
        issues = cache.issues;
      } else {
        const response = await fetch('https://api.github.com/repos/KonradinG/sem5-pw-cis/issues?labels=security&state=open', { cache: 'no-store' });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        issues = await response.json();
      }

      if (!Array.isArray(issues) || issues.length === 0) {
        container.innerHTML = '<p class="no-issues">✅ Keine offenen Security Issues</p>';
        return;
      }

      let html = '<div class="issues-list">';
      issues.forEach(issue => {
        const createdDate = new Date(issue.created_at);
        const age = timeAgo(createdDate);
        const labels = issue.labels.map(l => {
          const textColor = getContrastColor(l.color || 'cccccc');
          return `<span class="label" style="background-color: #${l.color}; color: ${textColor}">${l.name}</span>`;
        }).join('');

        html += `
          <div class="issue-item">
            <div class="issue-header">
              <a href="${issue.html_url}" target="_blank" class="issue-title">
                #${issue.number}: ${issue.title}
              </a>
              <span class="age">${age}</span>
            </div>
            <div class="issue-labels">${labels}</div>
          </div>
        `;
      });
      html += '</div>';
      container.innerHTML = html;
    } catch (error) {
      console.error('Error loading issues:', error);
      container.innerHTML = '<p class="error">⚠️ Fehler beim Laden der Issues</p>';
    }
  }

  /**
   * Convert date to human-readable relative time
   * @param {Date} date - The date to convert
   * @returns {string} Human-readable time ago string
   */
  function timeAgo(date) {
    const seconds = Math.floor((new Date() - date) / 1000);
    const intervals = [
      { label: 'Jahr', seconds: 31536000 },
      { label: 'Monat', seconds: 2592000 },
      { label: 'Woche', seconds: 604800 },
      { label: 'Tag', seconds: 86400 },
      { label: 'Stunde', seconds: 3600 },
      { label: 'Minute', seconds: 60 }
    ];

    for (const interval of intervals) {
      const count = Math.floor(seconds / interval.seconds);
      if (count >= 1) {
        return `vor ${count} ${interval.label}${count !== 1 ? 'en' : ''}`;
      }
    }
    return 'gerade eben';
  }

  // Choose readable text color (#000 or #fff) for a given hex background
  function getContrastColor(hex) {
    try {
      const h = String(hex || '').replace('#', '').padEnd(6, '0');
      const r = parseInt(h.substring(0, 2), 16) || 0;
      const g = parseInt(h.substring(2, 4), 16) || 0;
      const b = parseInt(h.substring(4, 6), 16) || 0;
      const yiq = (r * 299 + g * 587 + b * 114) / 1000; // luminance heuristic
      return yiq >= 140 ? '#000' : '#fff';
    } catch (e) {
      return '#000';
    }
  }
})();
