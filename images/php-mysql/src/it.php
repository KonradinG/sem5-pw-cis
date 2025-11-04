<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/create_csrf.php';

$IT_FIELDS = [
  'benutzer_anlegen'          => 'Benutzer anlegen',
  'arbeitsplatz_vorbereiten'  => 'Arbeitsplatz vorbereiten',
  'chipkarten_programmierung' => 'Chipkarten Programmierung',
  'berechtigung_verteilen'    => 'Berechtigung verteilen',
  'it_einschulung'            => 'IT Einschulung',
];
$HR_FIELDS = [
  'dienstvertrag_unterschrieben' => 'Dienstvertrag unterschrieben',
  'checkliste_erstellen'         => 'Checkliste erstellt',
  'zugangschip_ausgabe'          => 'Zugangschip ausgegeben',
];
function get_all_employees(PDO $pdo): array {
  $s=$pdo->query("SELECT * FROM employees ORDER BY created_at DESC, id DESC");
  return $s->fetchAll(PDO::FETCH_ASSOC);
}
function pill(bool $v){
  $cls=$v?'ok':'off'; $txt=$v?'erledigt':'nicht erledigt';
  return "<span class=\"pill pill-$cls\">$txt</span>";
}
function btn_text(bool $v){ return $v ? 'auf nicht erledigt' : 'auf erledigt'; }

if($_SERVER['REQUEST_METHOD']==='POST'){
  $token=$_POST['csrf_token']??''; if(!verify_csrf($token)){http_response_code(400);die('CSRF Fehler');}
  $action=$_POST['action']??'';
  if($action==='toggle'){
    $field=$_POST['field']??''; $id=(int)($_POST['id']??0); $cur=(int)($_POST['current']??0);
    if($id>0 && array_key_exists($field,$IT_FIELDS)){
      $new=$cur?0:1;
      $st=$pdo->prepare("UPDATE employees SET {$field}=:v WHERE id=:id");
      $st->execute([':v'=>$new,':id'=>$id]);
    }
    header("Location: /it.php"); exit;
  }
}
$employees=get_all_employees($pdo);
?>
<!doctype html>
<html lang="de"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>IT · Onboarding</title>
<style>
:root{--bg:#0b1020;--card:#121a33;--text:#e2e8f0;--muted:#94a3b8;--line:#26324d}
*{box-sizing:border-box}body{margin:0;font-family:system-ui,Segoe UI,Roboto,Arial;background:var(--bg);color:var(--text)}
.wrap{max-width:1200px;margin:32px auto;padding:0 20px}
.links a{color:#c7d2fe;text-decoration:none;margin-right:12px}
.card{background:linear-gradient(180deg,rgba(255,255,255,.02),rgba(255,255,255,0));border:1px solid var(--line);border-radius:16px;padding:18px;margin-bottom:16px}
h1{margin:0 0 12px}.muted{color:#94a3b8}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.kacheln{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:10px}
.kachel{display:flex;align-items:center;justify-content:space-between;gap:10px;border:1px solid var(--line);border-radius:12px;padding:10px 12px;background:rgba(255,255,255,.02)}
.pill{padding:4px 10px;border-radius:999px;font-weight:700;font-size:12px;border:1px solid rgba(255,255,255,.12)}
.pill-ok{background:rgba(22,163,74,.10);color:#bbf7d0;border-color:rgba(22,163,74,.35)}
.pill-off{background:rgba(239,68,68,.10);color:#fecaca;border-color:rgba(239,68,68,.35)}
.btn{appearance:none;border:none;border-radius:10px;padding:8px 12px;font-weight:700;cursor:pointer;background:#bbf7d0;color:#0b1020}
</style>
</head>
<body>
<div class="wrap">
  <h1>IT · Onboarding</h1>
  <div class="links"><a href="/index.php">Übersicht</a><a href="/hr.php">HR</a></div>

  <?php if(!$employees): ?>
    <div class="card">Noch keine Mitarbeitenden vorhanden.</div>
  <?php else: foreach($employees as $e): ?>
    <div class="card">
      <h3 style="margin:0 0 6px"><?=htmlspecialchars($e['name'])?> <span class="muted">· Eintritt: <?=htmlspecialchars($e['entry_date'])?></span></h3>
      <p class="muted" style="margin:0 0 12px"><?=nl2br(htmlspecialchars($e['message']))?></p>

      <div class="grid2">
        <!-- HR (nur Ansicht) -->
        <section>
          <h4>HR (nur Ansicht)</h4>
          <div class="kacheln">
            <?php foreach($HR_FIELDS as $k=>$label): ?>
              <div class="kachel"><div><?=htmlspecialchars($label)?></div><?=pill((bool)$e[$k])?></div>
            <?php endforeach; ?>
          </div>
        </section>

        <!-- IT (bearbeitbar) -->
        <section>
          <h4>IT (bearbeitbar)</h4>
          <div class="kacheln">
            <?php foreach($IT_FIELDS as $k=>$label): ?>
              <div class="kachel">
                <div><?=htmlspecialchars($label)?></div>
                <div style="display:flex;align-items:center;gap:10px">
                  <?=pill((bool)$e[$k])?>
                  <form method="post" action="/it.php" style="margin:0">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="csrf_token" value="<?=htmlspecialchars(csrf_token())?>">
                    <input type="hidden" name="field" value="<?=htmlspecialchars($k)?>">
                    <input type="hidden" name="current" value="<?= (int)$e[$k] ?>">
                    <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
                    <button class="btn" type="submit"><?=htmlspecialchars(btn_text((bool)$e[$k]))?></button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </section>
      </div>
    </div>
  <?php endforeach; endif; ?>
</div>
</body>
</html>
