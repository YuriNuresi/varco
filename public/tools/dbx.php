<?php
/**
 * Console DB riutilizzabile per Varco — protetta da INSTALL_KEY.
 * Il MySQL OVH non è raggiungibile da fuori: questo endpoint gira SUL server e lavora sul DB condiviso.
 *
 * Usi:
 *   ?key=...&file=NAME.sql           esegue gli statement del file magic/sql/NAME.sql (sopra la docroot)
 *   ?key=...&q=SELECT ...            esegue una query (SELECT/SHOW -> righe; altro -> righe modificate)
 *   POST key, q                      idem (per query lunghe)
 *   &fmt=text                        output testuale corto (comodo da leggere via fetch); default json
 *
 * SICUREZZA: esegue SQL arbitrario se hai la chiave. Tienila segreta; a regime cancella questo file
 * (o cambia INSTALL_KEY). Non è un buco maggiore di install.php, ma è permanente: usalo con criterio.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/db.php';

$key = (string) ($_GET['key'] ?? $_POST['key'] ?? '');
if (!hash_equals(INSTALL_KEY, $key)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit("403 — chiave mancante o errata.\n");
}

$fmt = (string) ($_GET['fmt'] ?? 'json');

function respond(array $data, string $fmt): void
{
    if ($fmt === 'text') {
        header('Content-Type: text/plain; charset=utf-8');
        $lines = [];
        foreach ($data as $k => $v) {
            $lines[] = $k . ': ' . (is_scalar($v) || $v === null ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE));
        }
        echo implode("\n", $lines) . "\n";
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    exit;
}

try {
    $pdo = db();
} catch (Throwable $e) {
    http_response_code(500);
    respond(['ok' => false, 'error' => 'Connessione DB fallita: ' . $e->getMessage()], $fmt);
}

$file = (string) ($_GET['file'] ?? '');
$q    = (string) ($_POST['q'] ?? $_GET['q'] ?? '');

// --- Esecuzione di un file .sql (magic/sql/NAME.sql) --------------------------
if ($file !== '') {
    $path = __DIR__ . '/../../sql/' . basename($file);
    if (!is_file($path)) {
        http_response_code(404);
        respond(['ok' => false, 'error' => 'File non trovato: sql/' . basename($file)], $fmt);
    }

    $sql   = (string) file_get_contents($path);
    $stmts = preg_split('/;\s*\n/', $sql) ?: [];
    $run = 0; $affected = 0; $errors = [];
    foreach ($stmts as $s) {
        // Togli le righe di solo commento DENTRO lo statement (un commento prima di un INSERT non deve
        // far saltare l'intero statement). NB: assume nessun '--' dentro stringhe (vero per i nostri file).
        $s = trim((string) preg_replace('/^\s*--.*$/m', '', $s));
        if ($s === '') { continue; }
        try {
            $a = $pdo->exec($s);
            $run++;
            if (is_int($a)) { $affected += $a; }
        } catch (Throwable $e) {
            // Tolleriamo (e segnaliamo) gli errori idempotenti tipici: colonna/indice già esistenti.
            $errors[] = ['stmt' => substr(preg_replace('/\s+/', ' ', $s), 0, 140), 'err' => $e->getMessage()];
        }
    }
    respond([
        'ok'             => count($errors) === 0,
        'file'           => basename($file),
        'statements_run' => $run,
        'rows_affected'  => $affected,
        'error_count'    => count($errors),
        'errors'         => array_slice($errors, 0, 20),
    ], $fmt);
}

// --- Esecuzione di una singola query -----------------------------------------
if ($q !== '') {
    $isRead = (bool) preg_match('/^\s*(SELECT|SHOW|DESCRIBE|DESC|EXPLAIN|PRAGMA)\b/i', $q);
    try {
        if ($isRead) {
            $st = $pdo->query($q);
            $rows = $st->fetchAll();
            respond([
                'ok'        => true,
                'mode'      => 'read',
                'row_count' => count($rows),
                'rows'      => array_slice($rows, 0, 200),
                'truncated' => count($rows) > 200,
            ], $fmt);
        } else {
            $affected = $pdo->exec($q);
            respond(['ok' => true, 'mode' => 'write', 'rows_affected' => (int) $affected], $fmt);
        }
    } catch (Throwable $e) {
        http_response_code(400);
        respond(['ok' => false, 'error' => $e->getMessage()], $fmt);
    }
}

respond([
    'ok'    => true,
    'usage' => 'Passa ?file=NAME.sql per eseguire magic/sql/NAME.sql, oppure ?q=SQL per una query. &fmt=text per output corto.',
    'tables'=> array_map(static fn($r) => array_values($r)[0], $pdo->query('SHOW TABLES')->fetchAll()),
], $fmt);
