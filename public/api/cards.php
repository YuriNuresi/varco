<?php
/**
 * GET carte enabled, con filtri opzionali:
 *   ?color=W        (un colore singolo; "C" = incolore)
 *   ?mana_value=3   (cmc esatto)
 *   ?q=drago        (ricerca per nome, LIKE)
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/http.php';
require_once __DIR__ . '/../../src/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_err('Solo GET', 405);
}

$where  = ['enabled = 1'];
$params = [];

$color = isset($_GET['color']) ? strtoupper(trim((string) $_GET['color'])) : '';
if ($color !== '') {
    if ($color === 'C') {
        $where[] = "colors = ''"; // incolore
    } elseif (preg_match('/^[WUBRG]$/', $color)) {
        // mazzo monocolore: la carta deve contenere quel colore
        $where[]  = 'colors LIKE ?';
        $params[] = '%' . $color . '%';
    } else {
        json_err('Colore non valido');
    }
}

if (isset($_GET['mana_value']) && $_GET['mana_value'] !== '') {
    $where[]  = 'mana_value = ?';
    $params[] = (int) $_GET['mana_value'];
}

if (isset($_GET['q']) && trim((string) $_GET['q']) !== '') {
    $where[]  = 'name LIKE ?';
    $params[] = '%' . trim((string) $_GET['q']) . '%';
}

$sql = 'SELECT id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample,
               double_strike, lifelink, reach, defender, rarity, image_url
        FROM ' . TBL_CARDS . ' WHERE ' . implode(' AND ', $where) . '
        ORDER BY mana_value, name LIMIT 500';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$cards = $stmt->fetchAll();

// Normalizza i flag a int per il JSON.
foreach ($cards as &$c) {
    $c['mana_value']   = (int) $c['mana_value'];
    $c['power']        = (int) $c['power'];
    $c['toughness']    = (int) $c['toughness'];
    $c['flying']        = (int) $c['flying'];
    $c['first_strike']  = (int) $c['first_strike'];
    $c['deathtouch']    = (int) $c['deathtouch'];
    $c['trample']       = (int) $c['trample'];
    $c['double_strike'] = (int) $c['double_strike'];
    $c['lifelink']      = (int) $c['lifelink'];
    $c['reach']         = (int) $c['reach'];
    $c['defender']      = (int) $c['defender'];
}
unset($c);

json_out(['ok' => true, 'cards' => $cards, 'count' => count($cards)]);
