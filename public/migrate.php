<?php
/**
 * Migrazione one-shot: aggiunge la colonna `source` a magic_cards.
 *
 *   https://varco.portale3d.it/migrate.php?key=...
 *
 * Idempotente: se la colonna esiste già, non fa nulla.
 * CANCELLA questo file dopo l'uso.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/db.php';

header('Content-Type: text/plain; charset=utf-8');

if (($_GET['key'] ?? '') !== INSTALL_KEY) {
    http_response_code(403);
    exit("403 — chiave mancante o errata.\n");
}

$pdo = db();
$out = [];

// 1. Controlla se la colonna esiste già.
$cols = $pdo->query('SHOW COLUMNS FROM ' . TBL_CARDS . " LIKE 'source'")->fetchAll();

if ($cols) {
    $out[] = "Colonna `source` già presente, skip ALTER.";
} else {
    $pdo->exec('ALTER TABLE ' . TBL_CARDS . " ADD COLUMN source VARCHAR(10) NOT NULL DEFAULT 'scryfall' AFTER enabled");
    $out[] = "Colonna `source` aggiunta.";
}

// 2. Indice (idempotente: ignora se esiste).
try {
    $pdo->exec('CREATE INDEX idx_source ON ' . TBL_CARDS . ' (source)');
    $out[] = "Indice idx_source creato.";
} catch (Throwable $e) {
    $out[] = "Indice idx_source già presente, skip.";
}

// 3. Sicurezza: marca tutte le righe esistenti come scryfall.
$n = $pdo->exec("UPDATE " . TBL_CARDS . " SET source = 'scryfall' WHERE source = '' OR source IS NULL");
$out[] = "Righe marcate scryfall: " . ($n ?: 0);

echo implode("\n", $out) . "\n\nFatto! Cancella questo file.\n";
