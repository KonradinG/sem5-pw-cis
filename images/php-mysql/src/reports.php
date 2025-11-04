<?php
// reports.php — Automatische Ansicht & Upload für JSON-Reports (Trivy / SBOM)

declare(strict_types=1);

// ---------- Einstellungen ----------
$UPLOAD_DIR = __DIR__ . '/reports/uploads';
$MAX_BYTES  = 10 * 1024 * 1024; // 10 MB max
$ALLOWED_EXT = ['json'];

@mkdir($UPLOAD_DIR, 0775, true);

// ---------- Hilfsfunktionen ----------
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function list_json_files(string $dir): array {
    if (!is_dir($dir)) return [];
    $files = array_filter(scandir($dir) ?: [], fn($f)=>is_file("$dir/$f") && str_ends_with(strtolower($f), '.json'));
    usort($files, fn($a,$b)=>filemtime("$dir/$b") <=> filemtime("$dir/$a"));
    return array_values($files);
}

function validate_upload(array $file, int $max, array $exts): ?string {
    if ($file['error'] !== UPLOAD_ERR_OK) return 'Fehler beim Upload.';
    if ($file['size'] > $max) return 'Datei zu groß (max 10 MB).';
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $exts)) return 'Nur JSON-Dateien erlaubt.';
    $raw = file_get_contents($file['tmp_name']);
    json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) return 'Ungültige JSON-Datei.';
    return null;
}

function store_file(array $file, string $dir): string {
    $base = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($file['name']));
    $ts = date('Ymd_His');
    $rand = bin2hex(random_bytes(3));
    $target = "$dir/{$ts}_{$rand}_$base";
    move_uploaded_file($file['tmp_name'], $target);
    @chmod($target, 0664);
    return basename($target);
}

function load_json(string $path): ?array {
    if (!is_readable($path)) return null;
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : null;
}

function extract_vulns(?array $data): array {
    $out = [];
    if (!$data || empty($data['Results'])) return $out;
    foreach ($data['Results'] as $res) {
        $target = $res['Target'] ?? 'unknown';
        foreach ($res['Vulnerabilities'] ?? [] as $v) {
            $out[] = [
                'Target' => $target,
                'PkgName' => $v['PkgName'] ?? '',
                'Installed' => $v['InstalledVersion'] ?? '',
                'VulnID' => $v['VulnerabilityID'] ?? '',
                'Severity' => strtoupper($v['Severity'] ?? 'UNKNOWN'),
                'Title' => $v['Title'] ?? '',
                'Fixed' => $v['FixedVersion'] ?? '',
                'URL' => $v['PrimaryURL'] ?? '',
                'Source' => $v['DataSource']['Name'] ?? '',
            ];
        }
    }
    return $out;
}

function count_by_sev(array $rows): array {
    $sev = ['CRITICAL'=>0,'HIGH'=>0,'MEDIUM'=>0,'LOW'=>0,'UNKNOWN'=>0];
    foreach ($rows as $r) {
        $s = strtoupper($r['Severity'] ?? 'UNKNOWN');
        $sev[$s] = ($sev[$s] ?? 0) + 1;
    }
    return $sev;
}

// ---------- Upload handling ----------
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['report'])) {
    $err = validate_upload($_FILES['report'], $MAX_BYTES, $ALLOWED_EXT);
    if ($err) {
        $msg = "❌ $err";
    } else {
        $saved = store_file($_FILES['report'], $UPLOAD_DIR);
        $msg = "✅ Upload erfolgreich: " . h($saved);
    }
}

// ---------- Dateien einlesen ----------
$files = list_json_files($UPLOAD_DIR);
$reports = [];
foreach ($files as $f) {
    $data = load_json("$UPLOAD_DIR/$f");
    if ($data) {
        $rows = extract_vulns($data);
        $sev = count_by_sev($rows);
        $reports[] = ['name'=>$f,'rows'=>$rows,'sev'=>$sev];
    }
}
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Security Reports · Upload + Auto</title>
<style>
:root{
  --bg:#0b1020;--card:#121a33;--text:#e2e8f0;--muted:#94a3b8;--line:#26324d;
  --crit:#dc2626;--high:#ef4444;--med:#f59e0b;--low:#16a34a;--unk:#64748b;
}
*{box-sizing:border-box}body{margin:0;font-family:system-ui,Segoe UI,Roboto,Arial;background:var(--bg);color:var(--text)}
.wrap{max-width:1200px;margin:32px auto;padding:0 20px}
h1{margin:0 0 12px}.links a{color:#c7d2fe;text-decoration:none;margin-right:12px}
.card{background:linear-gradient(180deg,rgba(255,255,255,.02),transparent);border:1px solid var(--line);border-radius:16px;padding:18px;margin-bottom:16px}
.kpis{display:flex;gap:8px;flex-wrap:wrap;margin:8px 0}
.kpi{padding:8px 12px;border:1px solid var(--line);border-radius:10px;background:rgba(255,255,255,.02);font-weight:700}
.badge{padding:2px 8px;border-radius:999px;border:1px solid rgba(255,255,255,.12);font-size:12px;font-weight:800}
.b-crit{background:rgba(220,38,38,.15);color:#fecaca}
.b-high{background:rgba(239,68,68,.15);color:#ffe4e6}
.b-med{background:rgba(245,158,11,.15);color:#fde68a}
.b-low{background:rgba(22,163,74,.15);color:#bbf7d0}
.b-unk{background:rgba(100,116,139,.15);color:#e2e8f0}
.tbl{width:100%;border-collapse:collapse}
.tbl th,.tbl td{padding:8px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top}
.tbl th{font-size:13px;color:var(--muted)}
.input,.file{padding:10px 12px;border-radius:10px;border:1px solid var(--line);background:#0f1730;color:#e2e8f0}
.btn{appearance:none;border:none;border-radius:10px;padding:10px 14px;font-weight:700;cursor:pointer;background:#c7d2fe;color:#0b1020}
.small{color:#94a3b8;font-size:12px}
.sev{font-weight:800}
.sev.CRITICAL{color:#fecaca;background:rgba(220,38,38,.15);padding:2px 8px;border-radius:999px}
.sev.HIGH{color:#ffe4e6;background:rgba(239,68,68,.15);padding:2px 8px;border-radius:999px}
.sev.MEDIUM{color:#fde68a;background:rgba(245,158,11,.15);padding:2px 8px;border-radius:999px}
.sev.LOW{color:#bbf7d0;background:rgba(22,163,74,.15);padding:2px 8px;border-radius:999px}
.sev.UNKNOWN{color:#e2e8f0;background:rgba(100,116,139,.15);padding:2px 8px;border-radius:999px}
.msg{margin:8px 0;padding:8px 10px;border:1px solid var(--line);border-radius:10px;background:rgba(255,255,255,.04)}
</style>
</head>
<body>
<div class="wrap">
  <h1>Security Reports · Upload + Auto</h1>
  <div class="links">
    <a href="/index.php">Übersicht</a>
    <a href="/hr.php">HR</a>
    <a href="/it.php">IT</a>
  </div>

  <div class="card">
    <h2>JSON-Report hochladen</h2>
    <?php if($msg): ?><div class="msg"><?=h($msg)?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data">
      <input class="file" type="file" name="report" accept=".json" required>
      <button class="btn" type="submit">Hochladen</button>
      <div class="small">Erwarte Trivy- oder SBOM-JSON-Dateien (max. 10 MB).</div>
    </form>
  </div>

  <div class="card">
    <h2>Gefundene JSON-Dateien (im Ordner)</h2>
    <?php if(!$files): ?>
      <div class="small">Keine JSON-Dateien in <code>/reports/uploads/</code> gefunden.</div>
    <?php else: ?>
      <ul>
        <?php foreach($files as $f): ?><li><?=h($f)?></li><?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

  <?php foreach($reports as $r): ?>
    <div class="card">
      <h2><?=h($r['name'])?></h2>
      <?php $sev=$r['sev']; $rows=$r['rows']; ?>
      <div class="kpis">
        <div class="kpi"><span class="badge b-crit">CRITICAL</span> <?=$sev['CRITICAL']?></div>
        <div class="kpi"><span class="badge b-high">HIGH</span> <?=$sev['HIGH']?></div>
        <div class="kpi"><span class="badge b-med">MEDIUM</span> <?=$sev['MEDIUM']?></div>
        <div class="kpi"><span class="badge b-low">LOW</span> <?=$sev['LOW']?></div>
        <div class="kpi"><span class="badge b-unk">UNKNOWN</span> <?=$sev['UNKNOWN']?></div>
      </div>

      <?=render_table($rows, 'tbl_'.substr(md5($r['name']),0,8));?>
    </div>
  <?php endforeach; ?>
</div>

<?php
function render_table(array $rows, string $id): string {
  $count = count($rows);
  ob_start(); ?>
  <div style="margin:8px 0">
    <input class="input" placeholder="Suche (CVE, Paket, Target, Titel …)" oninput="filterTable('<?=$id?>', this.value)">
  </div>
  <div class="small"><?=$count?> Einträge</div>
  <table class="tbl" id="<?=$id?>">
    <thead><tr>
      <th>Severity</th><th>Vulnerability</th><th>Paket / Version</th>
      <th>Target</th><th>Titel</th><th>Fix</th><th>Quelle</th>
    </tr></thead>
    <tbody>
      <?php foreach($rows as $r): ?>
        <tr>
          <td><span class="sev <?=h($r['Severity'])?>"><?=h($r['Severity'])?></span></td>
          <td><?php if($r['URL']): ?><a href="<?=h($r['URL'])?>" target="_blank"><?=h($r['VulnID'])?></a><?php else: ?><?=h($r['VulnID'])?><?php endif; ?></td>
          <td><?=h($r['PkgName'].' @ '.$r['Installed'])?></td>
          <td><?=h($r['Target'])?></td>
          <td><?=h($r['Title'])?></td>
          <td><?=h($r['Fixed'] ?: '-')?></td>
          <td><?=h($r['Source'] ?: '-')?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <script>
    function filterTable(id, q){
      q=(q||'').toLowerCase();
      document.querySelectorAll('#'+id+' tbody tr').forEach(tr=>{
        tr.style.display = tr.innerText.toLowerCase().includes(q) ? '' : 'none';
      });
    }
  </script>
  <?php return ob_get_clean();
}
?>
</body></html>
