<?php
/**
 * Admin: gestione screenshot pending/approved.
 * Accesso gated da INSTALL_KEY (come dbx.php).
 * Azioni: approve (sposta in approved/), delete (elimina).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config.php';

$key = $_GET['key'] ?? $_POST['key'] ?? '';
if ($key !== env('INSTALL_KEY', 'varco-setup-2026')) {
    http_response_code(403);
    echo 'Forbidden'; exit;
}

$pendingDir  = __DIR__ . '/../../screenshots/pending';
$approvedDir = __DIR__ . '/../../screenshots/approved';
@mkdir($pendingDir,  0755, true);
@mkdir($approvedDir, 0755, true);

// Azioni POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $file   = basename($_POST['file'] ?? '');
    if (!$file || !preg_match('/^[\w\-]+\.(jpg|jpeg|png)$/i', $file)) {
        echo json_encode(['ok' => false, 'error' => 'invalid file']); exit;
    }
    if ($action === 'approve') {
        $src = $pendingDir  . '/' . $file;
        $dst = $approvedDir . '/' . $file;
        if (!file_exists($src)) { echo json_encode(['ok' => false, 'error' => 'not found']); exit; }
        rename($src, $dst);
        echo json_encode(['ok' => true]);
    } elseif ($action === 'delete') {
        $src = $pendingDir . '/' . $file;
        if (file_exists($src)) unlink($src);
        $src2 = $approvedDir . '/' . $file;
        if (file_exists($src2)) unlink($src2);
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'unknown action']);
    }
    exit;
}

// Lista file
function listShots(string $dir): array {
    $files = glob($dir . '/*.{jpg,jpeg,png}', GLOB_BRACE) ?: [];
    usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
    return $files;
}

$pending  = listShots($pendingDir);
$approved = listShots($approvedDir);

// URL pubblica per le preview (via endpoint di servizio)
function previewUrl(string $path, string $type, string $key): string {
    return '/tools/screenshots.php?key=' . urlencode($key) . '&img=' . urlencode($type . '/' . basename($path));
}

// Serve singola immagine
if (isset($_GET['img'])) {
    [$type, $file] = explode('/', $_GET['img'], 2) + ['', ''];
    $file = basename($file);
    if (!preg_match('/^[\w\-]+\.(jpg|jpeg|png)$/i', $file)) { http_response_code(400); exit; }
    $dir  = $type === 'approved' ? $approvedDir : $pendingDir;
    $path = $dir . '/' . $file;
    if (!file_exists($path)) { http_response_code(404); exit; }
    $mime = str_ends_with($file, '.png') ? 'image/png' : 'image/jpeg';
    header('Content-Type: ' . $mime);
    header('Cache-Control: private, max-age=60');
    readfile($path);
    exit;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Varco — Screenshot Admin</title>
<style>
body{font-family:sans-serif;background:#0e0a18;color:#ece3cf;margin:0;padding:1.5rem}
h1{color:#ecd28d;margin-bottom:.3rem}
h2{color:#9b8fb4;font-size:1rem;text-transform:uppercase;letter-spacing:.1em;margin:1.5rem 0 .7rem}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:1rem}
.card{background:#1a1230;border:1px solid #3a2860;border-radius:8px;overflow:hidden;position:relative}
.card img{width:100%;aspect-ratio:9/16;object-fit:cover;display:block}
.card .meta{font-size:.75rem;color:#9b8fb4;padding:.4rem .6rem}
.card .actions{display:flex;gap:.4rem;padding:.4rem .6rem .7rem}
button{cursor:pointer;border:none;border-radius:5px;padding:.35rem .7rem;font-size:.8rem;font-weight:600}
.btn-approve{background:#3fa55a;color:#fff}
.btn-delete{background:#b8392c;color:#fff}
.badge{position:absolute;top:.4rem;right:.4rem;background:#ecd28d;color:#0e0a18;
  font-size:.65rem;font-weight:700;border-radius:3px;padding:.1rem .4rem;letter-spacing:.08em}
.empty{color:#5a4e7a;font-style:italic}
</style>
</head>
<body>
<h1>📷 Screenshot Admin</h1>
<p style="color:#5a4e7a;font-size:.85rem">Pending: <?= count($pending) ?> · Approved: <?= count($approved) ?></p>

<h2>⏳ Pending (<?= count($pending) ?>)</h2>
<?php if (!$pending): ?>
  <p class="empty">Nessuno screenshot in attesa.</p>
<?php else: ?>
<div class="grid">
<?php foreach ($pending as $f): $name = basename($f); ?>
  <div class="card" id="card-pending-<?= htmlspecialchars($name) ?>">
    <img src="<?= htmlspecialchars(previewUrl($f, 'pending', $key)) ?>" loading="lazy">
    <div class="meta"><?= htmlspecialchars($name) ?></div>
    <div class="actions">
      <button class="btn-approve" onclick="act('approve','<?= htmlspecialchars($name) ?>','pending')">✅ Approva</button>
      <button class="btn-delete"  onclick="act('delete','<?= htmlspecialchars($name) ?>','pending')">🗑 Elimina</button>
    </div>
  </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<h2>✅ Approved (<?= count($approved) ?>)</h2>
<?php if (!$approved): ?>
  <p class="empty">Nessuno screenshot approvato ancora.</p>
<?php else: ?>
<div class="grid">
<?php foreach ($approved as $f): $name = basename($f); ?>
  <div class="card" id="card-approved-<?= htmlspecialchars($name) ?>">
    <span class="badge">OK</span>
    <img src="<?= htmlspecialchars(previewUrl($f, 'approved', $key)) ?>" loading="lazy">
    <div class="meta"><?= htmlspecialchars($name) ?></div>
    <div class="actions">
      <button class="btn-delete" onclick="act('delete','<?= htmlspecialchars($name) ?>','approved')">🗑 Elimina</button>
    </div>
  </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<script>
const KEY = <?= json_encode($key) ?>;
async function act(action, file, section) {
  const fd = new FormData();
  fd.append('action', action); fd.append('file', file); fd.append('key', KEY);
  const r = await fetch(location.pathname + '?key=' + encodeURIComponent(KEY), { method: 'POST', body: fd });
  const j = await r.json();
  if (j.ok) {
    document.getElementById('card-' + section + '-' + file)?.remove();
  } else {
    alert('Errore: ' + j.error);
  }
}
</script>
</body>
</html>
