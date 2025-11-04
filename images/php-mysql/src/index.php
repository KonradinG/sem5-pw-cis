<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/create_csrf.php';

function get_all_employees(PDO $pdo): array {
    $stmt = $pdo->query("SELECT * FROM employees ORDER BY created_at DESC, id DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$employees = get_all_employees($pdo);

$HR_FIELDS = [
  'dienstvertrag_unterschrieben' => 'Dienstvertrag unterschrieben',
  'checkliste_erstellen'         => 'Checkliste erstellt',
  'zugangschip_ausgabe'          => 'Zugangschip ausgegeben',
];
$IT_FIELDS = [
  'benutzer_anlegen'          => 'Benutzer anlegen',
  'arbeitsplatz_vorbereiten'  => 'Arbeitsplatz vorbereiten',
  'chipkarten_programmierung' => 'Chipkarten Programmierung',
  'berechtigung_verteilen'    => 'Berechtigung verteilen',
  'it_einschulung'            => 'IT Einschulung',
];

function pill(bool $v){
  $cls = $v ? 'ok' : 'off';
  $txt = $v ? 'erledigt' : 'nicht erledigt';
  return "<span class=\"pill pill-$cls\">$txt</span>";
}
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Übersicht · Alle Mitarbeitenden</title>
<style>
:root{--bg:#0b1020;--card:#121a33;--text:#e2e8f0;--muted:#94a3b8;--line:#26324d;--ok:#16a34a;--off:#ef4444}
*{box-sizing:border-box} body{margin:0;font-family:system-ui,Segoe UI,Roboto,Arial;background:var(--bg);color:var(--text)}
.wrap{max-width:1200px;margin:32px auto;padding:0 20px}
h1{margin:0 0 12px}.links a{color:#c7d2fe;text-decoration:none;margin-right:12px}
.card{background:linear-gradient(180deg,rgba(255,255,255,.02),rgba(255,255,255,0));border:1px solid var(--line);border-radius:16px;padding:18px;margin-bottom:16px}
.muted{color:var(--muted)}
.sections{display:grid;gap:12px}@media(min-width:900px){.sections{grid-template-columns:1fr 1fr}}
.kacheln{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:10px}
.kachel{display:flex;align-items:center;justify-content:space-between;gap:10px;border:1px solid var(--line);border-radius:12px;padding:10px 12px;background:rgba(255,255,255,.02)}
.pill{padding:4px 10px;border-radius:999px;font-weight:700;font-size:12px;border:1px solid rgba(255,255,255,.12)}
.pill-ok{background:rgba(22,163,74,.10);color:#bbf7d0;border-color:rgba(22,163,74,.35)}
.pill-off{background:rgba(239,68,68,.10);color:#fecaca;border-color:rgba(239,68,68,.35)}
</style>
</head>
<body>
<div class="wrap">
  <h1>Alle Mitarbeitenden · Übersicht</h1>
  <div class="links">
    <a href="/hr.php">HR</a>
    <a href="/it.php">IT</a>
  </div>

  <?php if(!$employees): ?>
    <div class="card">Noch keine Mitarbeitenden angelegt.</div>
  <?php else: foreach($employees as $e): ?>
    <div class="card">
      <h2 style="margin:0 0 6px"><?=htmlspecialchars($e['name'])?> <span class="muted">· Eintritt: <?=htmlspecialchars($e['entry_date'])?></span></h2>
      <p class="muted" style="margin:0 0 12px"><?=nl2br(htmlspecialchars($e['message']))?></p>

      <div class="sections">
        <section>
          <h3>HR</h3>
          <div class="kacheln">
            <?php foreach($HR_FIELDS as $k=>$label): ?>
              <div class="kachel"><div><?=htmlspecialchars($label)?></div><?=pill((bool)$e[$k])?></div>
            <?php endforeach; ?>
          </div>
        </section>
        <section>
          <h3>IT</h3>
          <div class="kacheln">
            <?php foreach($IT_FIELDS as $k=>$label): ?>
              <div class="kachel"><div><?=htmlspecialchars($label)?></div><?=pill((bool)$e[$k])?></div>
            <?php endforeach; ?>
          </div>
        </section>
      </div>
    </div>
  <?php endforeach; endif; ?>
</div>
</body></html>
