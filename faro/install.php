<?php
/**
 * Faro — installer one-shot della tabella faro_events.
 *
 *   GET /faro/install.php?key=FARO_INSTALL_KEY
 *
 * Esegue sql/schema.sql sul DB condiviso. Idempotente (CREATE TABLE IF NOT EXISTS).
 * Dopo l'uso: azzera FARO_INSTALL_KEY nel .env o cancella questo file.
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/db.php';

header('Content-Type: text/plain; charset=utf-8');

if ((string) ($_GET['key'] ?? '') !== FARO_INSTALL_KEY) {
    http_response_code(403);
    echo "forbidden";
    exit;
}

$sql = @file_get_contents(__DIR__ . '/sql/schema.sql');
if ($sql === false) {
    http_response_code(500);
    echo "schema.sql non trovato";
    exit;
}

try {
    // Eseguiamo l'intero file in un colpo: il parser di MySQL gestisce
    // correttamente i commenti "-- ..." e gli statement multipli separati da ';'
    // (split manuale evitato: i commenti possono contenere punti e virgola).
    $pdo = faro_db();
    $pdo->exec($sql);
    echo "ok: schema eseguito. Tabella faro_events pronta.\n";

    // --- Migrazioni idempotenti su tabelle già esistenti -----------------------
    // CREATE TABLE IF NOT EXISTS non aggiunge colonne a una tabella già creata:
    // le nuove colonne vanno applicate a parte, controllando prima che manchino
    // (ADD COLUMN IF NOT EXISTS non è portabile tra MySQL e MariaDB).
    if (!faro_has_column($pdo, FARO_TBL_EVENTS, 'ua')) {
        $pdo->exec('ALTER TABLE ' . FARO_TBL_EVENTS . ' ADD COLUMN ua VARCHAR(255) NULL AFTER ua_hash');
        echo "\nmigrazione: colonna `ua` aggiunta.";
    } else {
        echo "\nmigrazione: colonna `ua` già presente.";
    }

    echo "\nOra azzera FARO_INSTALL_KEY nel .env (o cancella install.php).";
} catch (Throwable $e) {
    http_response_code(500);
    echo "errore: " . $e->getMessage();
}
