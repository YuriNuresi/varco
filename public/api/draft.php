<?php
/**
 * Draft "apri buste": genera un pool mono-colore pesato per rarità.
 *   GET ?color=W[&packs=2]
 *
 * Pesi in RARITY_WEIGHTS (config). Se un secchiello rarità è vuoto, ripiega al livello sotto.
 * Il pool può contenere ripetizioni (come aprire bustine).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/http.php';
require_once __DIR__ . '/../../src/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_err('Solo GET', 405);
}

$color = strtoupper(trim((string) ($_GET['color'] ?? '')));
if (!preg_match('/^[WUBRG]$/', $color)) {
    json_err('Colore non valido (W/U/B/R/G)');
}
$packs = (int) ($_GET['packs'] ?? PACKS_PER_DRAFT);
$packs = max(1, min(4, $packs));
$total = $packs * PACK_SIZE;

// Carica le carte del colore raggruppate per rarità.
$stmt = db()->prepare('SELECT id,name,mana_value,colors,power,toughness,flying,first_strike,deathtouch,trample,
                              double_strike,lifelink,reach,defender,rarity,image_url
                       FROM ' . TBL_CARDS . ' WHERE enabled = 1 AND colors LIKE ?');
$stmt->execute(['%' . $color . '%']);
$byRarity = ['common' => [], 'uncommon' => [], 'rare' => [], 'mythic' => []];
foreach ($stmt->fetchAll() as $row) {
    foreach (['flying','first_strike','deathtouch','trample','double_strike','lifelink','reach','defender','mana_value','power','toughness'] as $k) {
        $row[$k] = (int) $row[$k];
    }
    $r = $row['rarity'];
    if (!isset($byRarity[$r])) { $r = 'common'; }
    $byRarity[$r][] = $row;
}

$totalAvailable = array_sum(array_map('count', $byRarity));
if ($totalAvailable === 0) {
    json_err('Nessuna carta per il colore ' . $color);
}

// Estrazione pesata di una rarità.
$weights = RARITY_WEIGHTS; // ['common'=>..,'uncommon'=>..,'rare'=>..,'mythic'=>..]
$sumW = array_sum($weights);
$fallback = ['mythic' => 'rare', 'rare' => 'uncommon', 'uncommon' => 'common', 'common' => 'common'];

$pickRarity = static function () use ($weights, $sumW): string {
    $r = mt_rand(1, $sumW);
    foreach ($weights as $rar => $w) {
        $r -= $w;
        if ($r <= 0) { return $rar; }
    }
    return 'common';
};

$pickCard = static function (string $rarity) use ($byRarity, $fallback): ?array {
    $r = $rarity;
    for ($i = 0; $i < 4; $i++) {
        if (!empty($byRarity[$r])) {
            return $byRarity[$r][array_rand($byRarity[$r])];
        }
        $next = $fallback[$r];
        if ($next === $r) { break; }
        $r = $next;
    }
    // ultima spiaggia: una qualsiasi
    foreach ($byRarity as $bucket) {
        if (!empty($bucket)) { return $bucket[array_rand($bucket)]; }
    }
    return null;
};

$pool = [];
for ($i = 0; $i < $total; $i++) {
    $card = $pickCard($pickRarity());
    if ($card) { $pool[] = $card; }
}

json_out([
    'ok'     => true,
    'color'  => $color,
    'packs'  => $packs,
    'pool'   => $pool,
    'rules'  => ['min' => DECK_MIN_SIZE, 'max' => DECK_MAX_SIZE, 'copies' => MAX_COPIES],
]);
