<?php
/**
 * Download/gestione del log diagnostico delle battaglie (magic/logs/battle.jsonl).
 *
 *   GET /api/logs.php?key=INSTALL_KEY            -> scarica l'intero log (JSONL)
 *   GET /api/logs.php?key=INSTALL_KEY&tail=200   -> ultime N righe come testo (comodo da incollare)
 *   GET /api/logs.php?key=INSTALL_KEY&clear=1     -> azzera il log
 *
 * Gated da INSTALL_KEY (config). Il file vive SOPRA la docroot: è raggiungibile solo da qui.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config.php';

if ((string) ($_GET['key'] ?? '') !== INSTALL_KEY) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'forbidden';
    exit;
}

$file = __DIR__ . '/../../logs/battle.jsonl';

if (isset($_GET['clear'])) {
    @file_put_contents($file, '');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'log azzerato';
    exit;
}

if (!is_file($file)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'nessun log ancora (gioca una battaglia con LOG_BATTLE=true)';
    exit;
}

$tail = (int) ($_GET['tail'] ?? 0);
if ($tail > 0) {
    $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    header('Content-Type: text/plain; charset=utf-8');
    echo implode("\n", array_slice($lines, -$tail));
    exit;
}

header('Content-Type: application/x-ndjson; charset=utf-8');
header('Content-Disposition: attachment; filename="varco-battle.jsonl"');
readfile($file);
