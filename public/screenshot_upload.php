<?php
/**
 * Riceve gli screenshot 9:16 inviati dal bottone in-game.
 * Salva in magic/screenshots/pending/ (sopra la docroot, non accessibile dal web).
 * L'admin approva da /tools/screenshots.php → spostati in approved/.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$file = $_FILES['shot'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No file']);
    exit;
}

// Valida tipo MIME (deve essere jpeg/png dall'html2canvas)
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($file['tmp_name']);
if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid file type']);
    exit;
}

// Max 3 MB
if ($file['size'] > 3 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'File too large']);
    exit;
}

// Semplice rate-limit: max 5 upload per IP nell'ultima ora (contatore su file)
$ip      = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rlDir   = __DIR__ . '/../screenshots/.ratelimit';
@mkdir($rlDir, 0755, true);
$rlFile  = $rlDir . '/' . md5($ip) . '.json';
$rl      = file_exists($rlFile) ? json_decode(file_get_contents($rlFile), true) : ['t' => 0, 'n' => 0];
if (time() - $rl['t'] > 3600) { $rl = ['t' => time(), 'n' => 0]; }
if ($rl['n'] >= 5) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'Troppi screenshot — riprova tra un\'ora']);
    exit;
}
$rl['n']++;
file_put_contents($rlFile, json_encode($rl));

// Salva in pending/
$pendingDir = __DIR__ . '/../screenshots/pending';
@mkdir($pendingDir, 0755, true);

$round  = (int) ($_POST['round'] ?? 0);
$deckId = (int) ($_POST['deck_id'] ?? 0);
$ext    = $mime === 'image/png' ? 'png' : 'jpg';
$name   = date('Ymd_His') . '_r' . $round . '_d' . $deckId . '_' . substr(md5($ip), 0, 6) . '.' . $ext;
$dest   = $pendingDir . '/' . $name;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Save failed']);
    exit;
}

echo json_encode(['ok' => true, 'name' => $name]);
