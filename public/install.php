<?php
/**
 * Installer ONE-SHOT. Crea le tabelle e carica seed.sql nel MySQL.
 *
 *   https://varco.portale3d.it/install.php?key=...
 *
 * SICUREZZA: protetto da INSTALL_KEY. CANCELLA questo file dopo l'uso.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/db.php';

header('Content-Type: text/plain; charset=utf-8');

if (($_GET['key'] ?? '') !== INSTALL_KEY) {
    http_response_code(403);
    exit("403 — chiave mancante o errata.\n");
}

$seedPath = __DIR__ . '/../seed.sql';
if (!is_file($seedPath)) {
    http_response_code(500);
    exit("seed.sql non trovato in " . $seedPath . "\n");
}

$sql = file_get_contents($seedPath);

// Divide in statement sui ';' a fine riga (il formato del seed lo garantisce).
$statements = preg_split('/;\s*\n/', $sql);

try {
    $pdo = db();
} catch (Throwable $e) {
    http_response_code(500);
    exit("Connessione DB fallita: " . $e->getMessage() . "\n");
}

$run = 0;
$inserted = 0;
foreach ($statements as $stmt) {
    $stmt = trim($stmt);
    if ($stmt === '' || str_starts_with($stmt, '--')) {
        continue;
    }
    try {
        $affected = $pdo->exec($stmt);
        $run++;
        if (stripos($stmt, 'INSERT INTO') === 0) {
            $inserted += (int) $affected;
        }
    } catch (Throwable $e) {
        echo "ERRORE su statement #$run:\n" . substr($stmt, 0, 200) . "...\n";
        echo $e->getMessage() . "\n";
        http_response_code(500);
        exit;
    }
}

// Campagna 2.0: crea la tabella villaggi + i 5 villaggi iniziali (idempotente, vedi scripts/scenarios.sql).
$scenPath = __DIR__ . '/../scripts/scenarios.sql';
if (is_file($scenPath)) {
    foreach (preg_split('/;\s*\n/', (string) file_get_contents($scenPath)) as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '' || str_starts_with($stmt, '--')) { continue; }
        try { $pdo->exec($stmt); $run++; }
        catch (Throwable $e) { echo "AVVISO scenari: " . $e->getMessage() . "\n"; }
    }
}

$nCards = (int) $pdo->query('SELECT COUNT(*) FROM ' . TBL_CARDS)->fetchColumn();

echo "OK — installazione completata.\n";
echo "Statement eseguiti: $run\n";
echo "Carte inserite: $inserted (totale in tabella: $nCards)\n\n";
echo ">>> ORA CANCELLA install.php dal server. <<<\n";
