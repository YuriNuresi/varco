<?php
/**
 * Migrazione one-shot: aggiunge la colonna `subtypes` a magic_cards e la riempie (backfill)
 * SOLO per le carte già presenti nel seed.sql attuale — così non serve reimportare i 177 MB di
 * oracle_cards.json su OVH: si lancia il .sql prodotto in phpMyAdmin.
 *
 *   php scripts/migrate_subtypes.php
 *   -> scrive scripts/migrate_subtypes.sql (ALTER + UPDATE raggruppati per tribù)
 *
 * Idempotente lato deploy: se la colonna esiste già, l'ALTER dà "Duplicate column" — ignorabile.
 */

declare(strict_types=1);
ini_set('memory_limit', '3072M');

const SEED_FILE   = __DIR__ . '/../seed.sql';
const ORACLE_FILE = __DIR__ . '/oracle_cards.json';
const OUT_FILE    = __DIR__ . '/migrate_subtypes.sql';
const BATCH       = 400; // id per statement UPDATE

if (!is_file(SEED_FILE))   { fwrite(STDERR, "seed.sql mancante\n"); exit(1); }
if (!is_file(ORACLE_FILE)) { fwrite(STDERR, "oracle_cards.json mancante (lancia prima import_scryfall.php)\n"); exit(1); }

// --- 1. id presenti nel seed (= carte che il gioco usa davvero) ---------------
echo "[1/3] Leggo gli id dal seed...\n";
$ids = [];
$fh  = fopen(SEED_FILE, 'r');
while (($line = fgets($fh)) !== false) {
    if ($line === '' || $line[0] !== '(') { continue; }
    if (preg_match("/^\('([^']+)'/", $line, $m)) { $ids[$m[1]] = true; }
}
fclose($fh);
echo "      " . count($ids) . " carte nel seed.\n";

// --- 2. oracle_id -> subtypes (stessa logica di import_scryfall.php) ----------
echo "[2/3] Estraggo i sottotipi da oracle_cards.json...\n";
$cards = json_decode((string) file_get_contents(ORACLE_FILE), true);
if (!is_array($cards)) { fwrite(STDERR, "JSON non valido\n"); exit(1); }

$byTribe = []; // "Goblin,Warrior" => [id, id, ...]
$hit = 0;
foreach ($cards as $card) {
    $oracleId = $card['oracle_id'] ?? null;
    if ($oracleId === null || !isset($ids[$oracleId])) { continue; }

    $typeLine = (string) ($card['type_line'] ?? '');
    if (($dash = strpos($typeLine, '—')) === false) { continue; }
    $tail = substr($typeLine, $dash + strlen('—'));
    if (($slash = strpos($tail, '//')) !== false) { $tail = substr($tail, 0, $slash); }
    $parts = preg_split('/\s+/', trim($tail), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if (!$parts) { continue; }

    $byTribe[implode(',', $parts)][] = $oracleId;
    $hit++;
}
echo "      $hit carte con sottotipi, " . count($byTribe) . " combinazioni distinte.\n";

// --- 3. Scrivo l'SQL ----------------------------------------------------------
echo "[3/3] Scrivo " . OUT_FILE . "...\n";
$sqlStr = static fn(string $s): string => "'" . str_replace(["\\", "'"], ["\\\\", "''"], $s) . "'";

$out = fopen(OUT_FILE, 'w');
fwrite($out, "-- Generato da scripts/migrate_subtypes.php\n");
fwrite($out, "-- Lanciare in phpMyAdmin su OVH. Se la colonna esiste già, ignora l'errore 'Duplicate column'.\n");
fwrite($out, "SET NAMES utf8mb4;\n\n");
fwrite($out, "ALTER TABLE magic_cards ADD COLUMN subtypes VARCHAR(255) NOT NULL DEFAULT '' AFTER rarity;\n");
fwrite($out, "ALTER TABLE magic_cards ADD INDEX (subtypes);\n\n");

foreach ($byTribe as $tribe => $list) {
    foreach (array_chunk($list, BATCH) as $chunk) {
        $inList = implode(',', array_map($sqlStr, $chunk));
        fwrite($out, 'UPDATE magic_cards SET subtypes=' . $sqlStr($tribe) . ' WHERE id IN (' . $inList . ");\n");
    }
}
fclose($out);
echo "Fatto.\n";
