<?php
/**
 * Migrazione ONE-SHOT: ricrea magic_cards con le nuove colonne e ricarica seed.sql.
 * Conserva magic_decks (id carta stabili).
 *   https://varco.portale3d.it/reinstall.php?key=...
 * CANCELLARE dopo l'uso.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/db.php';

header('Content-Type: text/plain; charset=utf-8');

if (($_GET['key'] ?? '') !== INSTALL_KEY) {
    http_response_code(403);
    exit("403 — chiave errata.\n");
}

$seedPath = __DIR__ . '/../seed.sql';
if (!is_file($seedPath)) { http_response_code(500); exit("seed.sql non trovato.\n"); }

try {
    $pdo = db();
    $pdo->exec('DROP TABLE IF EXISTS ' . TBL_CARDS);
} catch (Throwable $e) { http_response_code(500); exit('DB: ' . $e->getMessage() . "\n"); }

$statements = preg_split('/;\s*\n/', (string) file_get_contents($seedPath));
$run = 0; $inserted = 0;
foreach ($statements as $stmt) {
    $stmt = trim($stmt);
    if ($stmt === '' || str_starts_with($stmt, '--')) { continue; }
    try {
        $aff = $pdo->exec($stmt);
        $run++;
        if (stripos($stmt, 'INSERT INTO') === 0) { $inserted += (int) $aff; }
    } catch (Throwable $e) {
        echo "ERRORE #$run: " . substr($stmt, 0, 160) . "\n" . $e->getMessage() . "\n";
        http_response_code(500); exit;
    }
}

$n = (int) $pdo->query('SELECT COUNT(*) FROM ' . TBL_CARDS)->fetchColumn();
$cnt = static fn(string $col): int => (int) $pdo->query('SELECT COUNT(*) FROM ' . TBL_CARDS . " WHERE $col = 1")->fetchColumn();
echo "OK — migrazione completata.\nStatement: $run | carte: $n\n";
echo sprintf("keyword: volare %d | first %d | letale %d | travolgere %d | doppio %d | legame %d | reach %d | difensore %d\n",
    $cnt('flying'), $cnt('first_strike'), $cnt('deathtouch'), $cnt('trample'),
    $cnt('double_strike'), $cnt('lifelink'), $cnt('reach'), $cnt('defender'));
echo "\n>>> CANCELLA reinstall.php <<<\n";
