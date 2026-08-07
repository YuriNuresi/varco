<?php
/**
 * Faro — dashboard (console). Login a password, render server-side dai dati veri.
 *   /faro/dashboard.php?days=7
 */

declare(strict_types=1);

session_start();
require_once __DIR__ . '/lib/metrics.php';

if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: dashboard.php');
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if ((string) ($_POST['password'] ?? '') === FARO_ADMIN_PASSWORD) {
        $_SESSION['faro_admin'] = true;
    } else {
        $loginError = 'Password errata.';
    }
}

$authed = !empty($_SESSION['faro_admin']);
$days = max(1, min(365, (int) ($_GET['days'] ?? 7)));
$demo = !empty($_GET['demo']);
$demoQs = $demo ? '&demo=1' : '';

function h($s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function eur_fmt(float $v): string { return '€' . number_format($v, 2, ',', '.'); }
function dur_fmt(int $s): string { if ($s < 60) return $s . 's'; $m = intdiv($s, 60); $r = $s % 60; return $r ? "{$m}m {$r}s" : "{$m}m"; }
?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Faro — console</title>
<style>
  :root{--bg:#0e1014;--card:#171a21;--card2:#1d212b;--line:#2a2f3a;--tx:#e7e9ee;--mut:#9aa3b2;--acc:#5dcaa5;--warn:#efb33a;--dang:#e85c5c}
  *{box-sizing:border-box}
  body{margin:0;background:#0e1014;color:var(--tx);font:15px/1.5 system-ui,-apple-system,Segoe UI,Roboto,sans-serif}
  body::before{content:"";position:fixed;inset:0;z-index:-1;background:linear-gradient(rgba(14,16,20,.45),rgba(14,16,20,.7)),url('assets/faro-bg.jpg') center center/cover no-repeat}
  .wrap{max-width:1080px;margin:0 auto;padding:24px}
  a{color:var(--acc);text-decoration:none}
  .top{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px}
  .brand{display:flex;align-items:center;gap:10px}
  .logo{width:34px;height:34px;border-radius:9px;background:#13261f;color:var(--acc);display:flex;align-items:center;justify-content:center;font-size:18px}
  .brand b{font-size:18px;font-weight:600}.brand small{color:var(--mut);display:block;font-size:12px}
  .controls{display:flex;gap:6px;align-items:center}
  .pill{font-size:13px;padding:6px 11px;border-radius:8px;background:var(--card);color:var(--mut);border:1px solid var(--line)}
  .pill.on{color:var(--tx);border-color:var(--acc)}
  .pill.toggle.on{color:#06231a;background:var(--acc);border-color:var(--acc)}
  .badge-demo{font-size:11px;font-weight:500;background:var(--warn);color:#2a1d00;padding:2px 8px;border-radius:6px;margin-left:8px;vertical-align:middle}
  .grid{display:grid;gap:12px}
  .kpis{grid-template-columns:repeat(auto-fit,minmax(150px,1fr));margin-bottom:18px}
  .kpi{background:var(--card);border-radius:11px;padding:14px 16px}
  .kpi .l{font-size:13px;color:var(--mut)}.kpi .v{font-size:25px;font-weight:600;margin-top:2px}
  .kpi .s{font-size:12px;color:var(--mut);margin-top:2px}
  .row{grid-template-columns:repeat(auto-fit,minmax(200px,1fr));margin-bottom:18px}
  .card{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:16px}
  .card h2{margin:0 0 14px;font-size:14px;font-weight:600;color:var(--tx);display:flex;gap:8px;align-items:center}
  .card h2 .hint{margin-left:auto;font-size:11px;color:var(--mut);font-weight:400}
  .app{display:flex;align-items:center;gap:8px;margin-bottom:10px}
  .dot{width:9px;height:9px;border-radius:50%}
  .kv{display:flex;justify-content:space-between;font-size:13px;padding:3px 0;color:var(--mut)}
  .kv b{color:var(--tx);font-weight:500}
  .bar{display:flex;align-items:center;gap:10px;margin-bottom:9px}
  .bar .nm{width:110px;font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .bar .track{flex:1;background:var(--card2);border-radius:6px;height:18px;overflow:hidden}
  .bar .fill{height:100%;background:var(--acc)}
  .bar .val{width:150px;text-align:right;font-size:12px;color:var(--mut)}
  table{width:100%;border-collapse:collapse;font-size:13px}
  th,td{text-align:left;padding:7px 0;border-bottom:1px solid var(--line)}
  th{color:var(--mut);font-weight:500}td:not(:first-child),th:not(:first-child){text-align:right}
  .empty{color:var(--mut);font-size:13px;padding:8px 0}
  /* tabella ultime visite */
  .visits-wrap{overflow-x:auto}
  table.visits{min-width:720px}
  table.visits th,table.visits td{text-align:left;white-space:nowrap;padding:7px 10px 7px 0}
  table.visits td.ua{max-width:260px;overflow:hidden;text-overflow:ellipsis;color:var(--mut);font-size:12px}
  table.visits td.num{text-align:right;font-variant-numeric:tabular-nums}
  table.visits td.mono,table.visits th.mono{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12px;color:var(--mut)}
  .tag{font-size:11px;padding:2px 8px;border-radius:6px;font-weight:600;display:inline-block;text-transform:capitalize}
  .tag.bot{background:rgba(232,92,92,.16);color:#f0a3a3}
  .tag.ai{background:rgba(127,119,221,.20);color:#bfb9f2}
  .tag.scanner{background:rgba(232,92,92,.30);color:#ffb3b3;box-shadow:inset 0 0 0 1px rgba(232,92,92,.55)}
  .tag.utente{background:rgba(93,202,165,.16);color:var(--acc)}
  .tag.sospetto{background:rgba(239,179,58,.16);color:var(--warn)}
  .tag.ignoto{background:var(--card2);color:var(--mut)}
  .vbar{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:-2px 0 12px}
  .mini{width:auto;margin:0;padding:5px 11px;font-size:12px;font-weight:500;background:var(--card2);color:var(--tx);border:1px solid var(--line);border-radius:8px;cursor:pointer}
  .mini.on{background:var(--acc);color:#06231a;border-color:var(--acc)}
  .vsum{display:flex;gap:14px;flex-wrap:wrap;font-size:12px;color:var(--mut);margin:0 0 12px;padding:6px 8px}
  .vsum b{color:var(--tx)}
  .pager{display:flex;align-items:center;gap:10px;justify-content:flex-end;margin-top:12px;font-size:13px;color:var(--mut)}
  .pager button{width:auto;margin:0;padding:6px 12px;background:var(--card2);color:var(--tx);border:1px solid var(--line);font-weight:500;font-size:13px}
  .pager button:disabled{opacity:.4;cursor:default}
  /* accordion (sezioni collassabili) */
  details.acc{margin-bottom:18px}
  details.acc>summary{cursor:pointer;list-style:none;background:var(--card);border:1px solid var(--line);border-radius:12px;padding:14px 16px;font-size:14px;font-weight:600;color:var(--tx);display:flex;align-items:center;gap:8px}
  details.acc>summary::-webkit-details-marker{display:none}
  details.acc>summary::after{content:"▸";margin-left:auto;color:var(--mut);font-size:13px;transition:transform .15s}
  details.acc[open]>summary::after{transform:rotate(90deg)}
  details.acc[open]>summary{border-radius:12px 12px 0 0}
  details.acc>summary:hover{border-color:#3a4150}
  details.acc .accsub{font-weight:400;font-size:11px;color:var(--mut)}
  .accbody{padding-top:12px}
  .login{max-width:340px;margin:14vh auto;background:rgba(23,26,33,.80);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);border:1px solid var(--line);border-radius:14px;padding:26px;box-shadow:0 12px 48px rgba(0,0,0,.5)}
  .login h1{font-size:20px;margin:0 0 4px}.login p{color:var(--mut);font-size:13px;margin:0 0 18px}
  input[type=password]{width:100%;padding:10px 12px;border-radius:9px;border:1px solid var(--line);background:var(--bg);color:var(--tx);font-size:15px}
  button{width:100%;margin-top:12px;padding:10px;border-radius:9px;border:0;background:var(--acc);color:#06231a;font-weight:600;font-size:15px;cursor:pointer}
  .err{color:var(--dang);font-size:13px;margin-top:10px}
  .muted{color:var(--mut)}
</style>
</head>
<body>
<?php if (!$authed): ?>
  <form class="login" method="post">
    <h1>Faro</h1>
    <p>Console traffico e monetizzazione.</p>
    <input type="password" name="password" placeholder="Password" autofocus>
    <button type="submit">Entra</button>
    <?php if (!empty($loginError)): ?><div class="err"><?= h($loginError) ?></div><?php endif; ?>
  </form>
</body></html>
<?php exit; endif; ?>

<?php
$err = null;
try {
    $pdo = faro_db();
    $hasDemo = faro_has_demo($pdo);
    $ov  = faro_overview($pdo, $days, $demo);
    $apps = faro_per_app($pdo, $days, $demo);
    $acq = faro_acquisition($pdo, $days, null, $demo);
    $med = faro_mediation($pdo, $days, $demo);
    $ret = faro_retention($pdo, $days, $demo);
    $visits = faro_recent_visits($pdo, $days, $demo, 100);
} catch (Throwable $e) {
    $err = $e->getMessage();
}
$colors = ['varco' => '#7F77DD', 'intercity' => '#5dcaa5', 'helios' => '#D85A30', 'portale3d' => '#efb33a'];
$maxRpv = 0.0;
foreach ($acq as $a) { $maxRpv = max($maxRpv, (float) $a['rev_per_visitor']); }
?>
<div class="wrap">
  <div class="top">
    <div class="brand">
      <div class="logo">◎</div>
      <div><b>Faro <?php if ($demo): ?><span class="badge-demo">dati demo</span><?php endif; ?></b><small>traffico e monetizzazione · 3 giochi</small></div>
    </div>
    <div class="controls">
      <?php foreach ([1 => '24h', 7 => '7g', 30 => '30g', 90 => '90g'] as $dd => $lbl): ?>
        <a class="pill <?= $days === $dd ? 'on' : '' ?>" href="?days=<?= $dd ?><?= $demoQs ?>"><?= $lbl ?></a>
      <?php endforeach; ?>
      <?php if (!empty($hasDemo) || $demo): ?>
        <a class="pill toggle <?= $demo ? 'on' : '' ?>" href="?days=<?= $days ?><?= $demo ? '' : '&demo=1' ?>"><?= $demo ? '☑' : '☐' ?> dati demo</a>
      <?php endif; ?>
      <a class="pill" href="?logout=1">esci</a>
    </div>
  </div>

  <?php if ($err): ?>
    <div class="card"><div class="err">Errore: <?= h($err) ?></div></div>
  <?php else: ?>

  <div class="grid kpis">
    <div class="kpi"><div class="l">visitatori</div><div class="v" id="kVisitors"><?= number_format($ov['visitors'], 0, ',', '.') ?></div><div class="s"><span id="kSessions"><?= number_format($ov['sessions'], 0, ',', '.') ?></span> sessioni</div></div>
    <div class="kpi"><div class="l">revenue</div><div class="v" id="kRevenue"><?= eur_fmt($ov['revenue_eur']) ?></div><div class="s"><span id="kPageviews"><?= number_format($ov['pageviews'], 0, ',', '.') ?></span> pageview</div></div>
    <div class="kpi"><div class="l">revenue / visitatore</div><div class="v" id="kRpv"><?= eur_fmt($ov['rev_per_visitor']) ?></div><div class="s">media pesata</div></div>
    <div class="kpi"><div class="l">rewarded completion</div><div class="v" id="kRewarded"><?= $ov['rewarded_completion'] ?>%</div><div class="s">retention D1 <?= $ret['d1'] ?>% · D7 <?= $ret['d7'] ?>%</div></div>
    <div class="kpi"><div class="l">pagine / sessione</div><div class="v" id="kPps"><?= number_format($ov['pages_per_session'], 1, ',', '.') ?></div><div class="s"><span id="kPageviews2"><?= number_format($ov['pageviews'], 0, ',', '.') ?></span> pagine viste</div></div>
    <div class="kpi"><div class="l">tempo medio sessione</div><div class="v" id="kDur"><?= dur_fmt($ov['avg_session_sec']) ?></div><div class="s">durata visita</div></div>
  </div>

  <div class="grid row" id="appsGrid">
    <?php if (!$apps): ?><div class="card"><div class="empty">Nessun dato ancora. Apri i giochi nel browser per popolarlo.</div></div><?php endif; ?>
    <?php foreach ($apps as $a): ?>
      <div class="card">
        <div class="app"><span class="dot" style="background:<?= $colors[$a['app']] ?? '#888' ?>"></span><b><?= h(ucfirst($a['app'])) ?></b></div>
        <div class="kv"><span>visitatori</span><b><?= number_format($a['visitors'], 0, ',', '.') ?></b></div>
        <div class="kv"><span>pageview</span><b><?= number_format($a['pageviews'], 0, ',', '.') ?></b></div>
        <div class="kv"><span>rev/visit.</span><b><?= eur_fmt($a['rev_per_visitor']) ?></b></div>
      </div>
    <?php endforeach; ?>
  </div>

  <details class="acc">
    <summary>approfondimenti <span class="accsub">acquisizione · mediation · retention</span></summary>
    <div class="accbody">

      <div class="grid" style="grid-template-columns:1fr;margin-bottom:18px">
        <div class="card">
          <h2>acquisizione — sorgenti per revenue/visitatore <span class="hint">dove vale la pena ri-postare</span></h2>
          <?php if (!$acq): ?><div class="empty">Nessuna sorgente ancora.</div><?php endif; ?>
          <?php foreach ($acq as $a):
            $w = $maxRpv > 0 ? max(4, round((float) $a['rev_per_visitor'] / $maxRpv * 100)) : 4; ?>
            <div class="bar">
              <span class="nm"><?= h($a['source']) ?></span>
              <span class="track"><span class="fill" style="width:<?= $w ?>%"></span></span>
              <span class="val"><?= eur_fmt((float) $a['rev_per_visitor']) ?> · <?= number_format($a['visitors'], 0, ',', '.') ?> vis</span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="grid row" style="margin-bottom:0">
        <div class="card">
          <h2>mediation — eCPM per network</h2>
          <?php if (!$med): ?><div class="empty">Nessun ricavo ancora (arriva dai callback S2S).</div>
          <?php else: ?>
          <table>
            <tr><th>network</th><th>impr.</th><th>eCPM</th><th>revenue</th></tr>
            <?php foreach ($med as $m): ?>
              <tr><td><?= h($m['network']) ?> <span class="muted"><?= h($m['format']) ?></span></td>
                  <td><?= number_format($m['impressions'], 0, ',', '.') ?></td>
                  <td><?= eur_fmt((float) $m['ecpm']) ?></td>
                  <td><?= eur_fmt((float) $m['revenue_eur']) ?></td></tr>
            <?php endforeach; ?>
          </table>
          <?php endif; ?>
        </div>
        <div class="card">
          <h2>retention</h2>
          <div class="kv"><span>coorte (nuovi)</span><b><?= number_format($ret['cohort'], 0, ',', '.') ?></b></div>
          <div class="kv"><span>D1 — tornano il giorno dopo</span><b><?= $ret['d1'] ?>%</b></div>
          <div class="kv"><span>D7 — entro 7 giorni</span><b><?= $ret['d7'] ?>%</b></div>
          <p class="muted" style="font-size:12px;margin:12px 0 0">D1 = tornano il giorno dopo · D7 = tornano almeno una volta entro 7 giorni (rolling).</p>
        </div>
      </div>

    </div>
  </details>

  <details class="acc">
    <summary><span style="color:var(--acc)">✦</span> Analyst <span class="accsub">domanda in italiano · interroga i dati veri</span></summary>
    <div class="accbody">
      <div class="card" style="border-color:#2f5f50">
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <input id="q" type="text" placeholder="Es. quale sorgente conviene spingere questa settimana?" style="flex:1;min-width:240px;padding:10px 12px;border-radius:9px;border:1px solid var(--line);background:var(--bg);color:var(--tx);font-size:14px">
          <button id="askBtn" style="width:auto;margin:0;padding:10px 18px">Chiedi</button>
        </div>
        <div id="suggest" style="display:flex;gap:6px;flex-wrap:wrap;margin-top:10px">
          <?php foreach (['Quale sorgente rende di più per visitatore?','Quale network di ads ha l\'eCPM migliore?','Quale gioco ha più visitatori?','Quanti rewarded completati negli ultimi 7 giorni?'] as $sug): ?>
            <span class="pill sug" style="cursor:pointer"><?= h($sug) ?></span>
          <?php endforeach; ?>
        </div>
        <div id="ans" style="display:none;margin-top:14px;border-top:1px solid var(--line);padding-top:14px"></div>
      </div>
    </div>
  </details>

  <div class="grid" style="grid-template-columns:1fr;margin-top:18px">
    <div class="card">
      <h2>ultime visite <span class="hint">una riga per sessione · bot o utenti?</span></h2>
      <div class="vbar">
        <button type="button" id="vAlerts" class="mini">🔔 attiva avvisi</button>
        <span id="vStatus" class="muted" style="font-size:12px"></span>
      </div>
      <div class="vsum" id="vSum"></div>
      <div class="empty" id="vEmpty" style="display:none">Nessuna visita nella finestra selezionata.</div>
      <div class="visits-wrap">
        <table class="visits" id="visitsTable">
          <thead>
            <tr>
              <th>quando</th><th>gioco</th><th>sorgente</th><th>tipo</th>
              <th class="num">pagine</th><th class="num">durata</th>
              <th class="mono">ip</th><th>user-agent</th>
            </tr>
          </thead>
          <tbody><!-- popolata lato JS (render + refresh ogni 60s) --></tbody>
        </table>
      </div>
      <div class="pager" id="visitsPager" style="display:none">
        <button id="vPrev" type="button">‹ prec</button>
        <span id="vInfo"></span>
        <button id="vNext" type="button">succ ›</button>
      </div>
    </div>
  </div>

  <?php endif; ?>
</div>

<?php
// Seed iniziale per la tabella visite (la stessa forma che restituisce visits.php).
$visitsSeed = [];
if (!empty($visits)) {
    foreach ($visits as $r) {
        $visitsSeed[] = [
            'first_ts'  => $r['first_ts'],
            'app'       => $r['app'],
            'src'       => $r['src'],
            'pageviews' => (int) $r['pageviews'],
            'dur'       => (int) $r['dur'],
            'ip_hash'   => $r['ip_hash'],
            'ua'        => $r['ua'],
            'kind'      => $r['kind'],
        ];
    }
}
?>
<script>
// Tabella "ultime visite": render + paginazione (10/pag) + refresh ogni 60s +
// avviso (suono + notifica di sistema) quando compaiono nuove sessioni.
(function(){
  var table = document.getElementById('visitsTable');
  if (!table) return;
  var tbody   = table.querySelector('tbody');
  var pager   = document.getElementById('visitsPager');
  var info    = document.getElementById('vInfo');
  var prevBtn = document.getElementById('vPrev'), nextBtn = document.getElementById('vNext');
  var sumEl   = document.getElementById('vSum');
  var emptyEl = document.getElementById('vEmpty');
  var statusEl= document.getElementById('vStatus');
  var alertsBtn = document.getElementById('vAlerts');

  var DAYS = <?= (int) $days ?>, DEMO = <?= $demo ? 'true' : 'false' ?>;
  var PER = 10, page = 0;
  var rows = <?= json_encode($visitsSeed, JSON_UNESCAPED_UNICODE) ?>;
  var lastCount = <?= isset($ov['sessions']) ? (int) $ov['sessions'] : 0 ?>;
  var alertsOn = false, audioCtx = null;
  var baseTitle = document.title;

  function esc(s){ return String(s==null?'':s).replace(/[&<>"]/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; }); }
  function ucf(s){ s=String(s||''); return s.charAt(0).toUpperCase()+s.slice(1); }
  function fmtWhen(ts){ var m=String(ts).match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/); return m ? (m[3]+'/'+m[2]+' '+m[4]+':'+m[5]) : esc(ts); }
  function fmtDur(s){ s=s|0; if(s<60) return s+'s'; var m=Math.floor(s/60), r=s%60; return r ? (m+'m '+r+'s') : (m+'m'); }
  function fmtNum(n){ return Number(n||0).toLocaleString('it-IT'); }
  function fmtNum1(n){ return Number(n||0).toLocaleString('it-IT',{minimumFractionDigits:1,maximumFractionDigits:1}); }
  function fmtEur(v){ return '€'+Number(v||0).toLocaleString('it-IT',{minimumFractionDigits:2,maximumFractionDigits:2}); }
  var APP_COLORS = <?= json_encode($colors, JSON_UNESCAPED_SLASHES) ?>;

  function setTxt(id, v){ var el = document.getElementById(id); if (el) el.textContent = v; }
  function updateKpis(ov){
    if (!ov) return;
    setTxt('kVisitors',  fmtNum(ov.visitors));
    setTxt('kSessions',  fmtNum(ov.sessions));
    setTxt('kRevenue',   fmtEur(ov.revenue_eur));
    setTxt('kPageviews', fmtNum(ov.pageviews));
    setTxt('kPageviews2',fmtNum(ov.pageviews));
    setTxt('kRpv',       fmtEur(ov.rev_per_visitor));
    setTxt('kRewarded',  (ov.rewarded_completion)+'%');
    setTxt('kPps',       fmtNum1(ov.pages_per_session));
    setTxt('kDur',       fmtDur(ov.avg_session_sec));
  }
  function appCardHtml(a){
    var c = APP_COLORS[a.app] || '#888';
    return '<div class="card">'
      + '<div class="app"><span class="dot" style="background:'+c+'"></span><b>'+esc(ucf(a.app))+'</b></div>'
      + '<div class="kv"><span>visitatori</span><b>'+fmtNum(a.visitors)+'</b></div>'
      + '<div class="kv"><span>pageview</span><b>'+fmtNum(a.pageviews)+'</b></div>'
      + '<div class="kv"><span>rev/visit.</span><b>'+fmtEur(a.rev_per_visitor)+'</b></div>'
      + '</div>';
  }
  function renderApps(apps){
    var grid = document.getElementById('appsGrid');
    if (!grid) return;
    grid.innerHTML = (apps && apps.length)
      ? apps.map(appCardHtml).join('')
      : '<div class="card"><div class="empty">Nessun dato ancora. Apri i giochi nel browser per popolarlo.</div></div>';
  }

  function rowHtml(v){
    var ua  = v.ua ? esc(v.ua) : '<span class="muted">—</span>';
    var iph = v.ip_hash ? esc(String(v.ip_hash).slice(0,8)) : '—';
    var kind = esc(v.kind || 'ignoto');
    return '<tr class="visit-row">'
      + '<td>'+fmtWhen(v.first_ts)+'</td>'
      + '<td>'+esc(ucf(v.app))+'</td>'
      + '<td>'+(v.src ? esc(v.src) : '(diretto)')+'</td>'
      + '<td><span class="tag '+kind+'">'+kind+'</span></td>'
      + '<td class="num">'+fmtNum(v.pageviews)+'</td>'
      + '<td class="num">'+fmtDur(v.dur)+'</td>'
      + '<td class="mono" title="'+esc(v.ip_hash||'')+'">'+iph+'</td>'
      + '<td class="ua" title="'+esc(v.ua||'')+'">'+ua+'</td>'
      + '</tr>';
  }

  function applyPager(){
    var all = tbody.querySelectorAll('.visit-row');
    var pages = Math.max(1, Math.ceil(all.length/PER));
    if (page > pages-1) page = pages-1;
    if (page < 0) page = 0;
    for (var i=0;i<all.length;i++){ all[i].style.display = (i>=page*PER && i<(page+1)*PER) ? '' : 'none'; }
    if (all.length > PER){
      pager.style.display='flex';
      info.textContent = (page+1)+' / '+pages+' · '+all.length+' visite';
      prevBtn.disabled = page===0; nextBtn.disabled = page===pages-1;
    } else { pager.style.display='none'; }
  }
  function renderTable(){
    var empty = !rows.length;
    emptyEl.style.display = empty ? '' : 'none';
    table.style.display   = empty ? 'none' : '';
    tbody.innerHTML = empty ? '' : rows.map(rowHtml).join('');
    applyPager();
  }
  function renderSummary(count){
    var k = {utente:0, ai:0, bot:0, scanner:0, sospetto:0, ignoto:0};
    rows.forEach(function(v){ k[v.kind] = (k[v.kind]||0)+1; });
    sumEl.innerHTML = '<span><b>'+fmtNum(count)+'</b> sessioni · mostrate <b>'+rows.length+'</b>:</span>'
      + ' <span><span class="tag utente">utente</span> <b>'+k.utente+'</b></span>'
      + ' <span><span class="tag ai">ai</span> <b>'+k.ai+'</b></span>'
      + ' <span><span class="tag bot">bot</span> <b>'+k.bot+'</b></span>'
      + ' <span><span class="tag scanner">scanner</span> <b>'+k.scanner+'</b></span>'
      + ' <span><span class="tag sospetto">sospetto</span> <b>'+k.sospetto+'</b></span>'
      + ' <span><span class="tag ignoto">ignoto</span> <b>'+k.ignoto+'</b></span>';
  }

  prevBtn.addEventListener('click', function(){ if(page>0){ page--; applyPager(); } });
  nextBtn.addEventListener('click', function(){ page++; applyPager(); });

  // ---- avvisi: suono (WebAudio) + notifica di sistema ----
  function ding(){
    try{
      if(!audioCtx) return;
      var t = audioCtx.currentTime;
      var o = audioCtx.createOscillator(), g = audioCtx.createGain();
      o.type='sine'; o.frequency.setValueAtTime(880, t); o.frequency.setValueAtTime(1175, t+0.12);
      g.gain.setValueAtTime(0.0001, t);
      g.gain.exponentialRampToValueAtTime(0.22, t+0.01);
      g.gain.exponentialRampToValueAtTime(0.0001, t+0.4);
      o.connect(g); g.connect(audioCtx.destination);
      o.start(t); o.stop(t+0.42);
    }catch(e){}
  }
  function flash(){
    sumEl.style.transition='background .25s'; sumEl.style.background='rgba(93,202,165,.20)'; sumEl.style.borderRadius='8px';
    setTimeout(function(){ sumEl.style.background=''; }, 1400);
  }
  function notify(delta, count){
    var msg = '+'+delta+' '+(delta===1 ? 'sessione' : 'sessioni')+' nell\'ultimo minuto';
    try{
      if (window.Notification && Notification.permission==='granted'){
        var n = new Notification('Faro — '+fmtNum(count)+' sessioni', { body: msg, tag:'faro-visits', renotify:true });
        setTimeout(function(){ try{ n.close(); }catch(e){} }, 8000);
      }
    }catch(e){}
    document.title = '🔔 '+msg+' — Faro';
    flash();
  }
  window.addEventListener('focus', function(){ document.title = baseTitle; });

  function enableAlerts(){
    try{
      audioCtx = audioCtx || new (window.AudioContext||window.webkitAudioContext)();
      if (audioCtx.state==='suspended') audioCtx.resume();
    }catch(e){}
    if (window.Notification && Notification.permission==='default'){ try{ Notification.requestPermission(); }catch(e){} }
    alertsOn = true; alertsBtn.classList.add('on'); alertsBtn.textContent='🔔 avvisi attivi';
    ding();
  }
  alertsBtn.addEventListener('click', enableAlerts);

  // ---- polling 60s ----
  function pad(n){ return (n<10?'0':'')+n; }
  function hms(){ var d=new Date(); return pad(d.getHours())+':'+pad(d.getMinutes())+':'+pad(d.getSeconds()); }
  function setStatus(t){ statusEl.textContent = t; }

  function poll(){
    fetch('visits.php?days='+DAYS+'&demo='+(DEMO?1:0), {credentials:'same-origin', cache:'no-store'})
      .then(function(r){ return r.json(); })
      .then(function(d){
        if(!d || !d.ok){ setStatus('errore aggiornamento'); return; }
        rows = d.visits || [];
        renderTable();
        renderSummary(d.count);
        updateKpis(d.overview);
        renderApps(d.per_app);
        setStatus('aggiornato alle '+hms()+' · auto ogni 60s');
        if (d.count > lastCount){
          var delta = d.count - lastCount;
          if (alertsOn) ding();
          notify(delta, d.count);
        }
        lastCount = d.count;
      })
      .catch(function(){ setStatus('offline — riprovo al prossimo giro'); });
  }

  // primo render dal seed, poi avvio del polling
  renderTable();
  renderSummary(lastCount);
  setStatus('auto-refresh ogni 60s · attiva gli avvisi per suono + notifica');
  setInterval(poll, 60000);
})();
</script>

<script>
(function(){
  var DEMO = <?= $demo ? 'true' : 'false' ?>, DAYS = <?= (int) $days ?>;
  var q = document.getElementById('q'), btn = document.getElementById('askBtn'), ans = document.getElementById('ans');
  if (!q) return;
  function esc(s){ return String(s).replace(/[&<>]/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;'}[c]; }); }
  function ask(){
    var question = q.value.trim(); if(!question) return;
    ans.style.display='block';
    ans.innerHTML='<span class="muted">L\'analyst sta pensando…</span>';
    fetch('ask.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({question:question, days:DAYS, demo:DEMO})})
      .then(function(r){return r.json();})
      .then(function(d){
        if(!d.ok){ ans.innerHTML='<span style="color:var(--dang)">Errore: '+esc(d.error||'')+'</span>'; return; }
        var html='<div style="font-size:15px;line-height:1.6">'+esc(d.answer)+'</div>';
        html+='<details style="margin-top:10px"><summary style="cursor:pointer;color:var(--mut);font-size:12px">SQL generata · '+d.row_count+' righe</summary>'
            +'<pre style="white-space:pre-wrap;background:var(--bg);border:1px solid var(--line);border-radius:8px;padding:10px;font-size:12px;color:#9fe1cb;overflow:auto">'+esc(d.sql)+'</pre></details>';
        ans.innerHTML=html;
      })
      .catch(function(e){ ans.innerHTML='<span style="color:var(--dang)">Errore di rete</span>'; });
  }
  btn.addEventListener('click', ask);
  q.addEventListener('keydown', function(e){ if(e.key==='Enter') ask(); });
  Array.prototype.forEach.call(document.querySelectorAll('.sug'), function(s){
    s.addEventListener('click', function(){ q.value=s.textContent; ask(); });
  });
})();
</script>
</body></html>
