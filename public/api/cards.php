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

// Lookup per ID specifici (usato dall'editor campagna).
if (isset($_GET['ids']) && trim((string) $_GET['ids']) !== '') {
    $ids = array_filter(array_map('trim', explode(',', (string) $_GET['ids'])));
    if ($ids) {
        $place = implode(',', array_fill(0, count($ids), '?'));
        $stmt = db()->prepare('SELECT id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample,
                   double_strike, lifelink, reach, defender, rarity, image_url, source
            FROM ' . TBL_CARDS . " WHERE id IN ($place)");
        $stmt->execute(array_values($ids));
        $cards = $stmt->fetchAll();
        foreach ($cards as &$c) {
            $c['mana_value'] = (int) $c['mana_value'];
            $c['power'] = (int) $c['power'];
            $c['toughness'] = (int) $c['toughness'];
        }
        unset($c);
        json_out(['ok' => true, 'cards' => $cards, 'count' => count($cards)]);
    }
}

$where  = ['enabled = 1'];
$params = [];

$source = isset($_GET['source']) ? strtolower(trim((string) $_GET['source'])) : '';
if ($source !== '') {
    if (!in_array($source, ['scryfall', 'custom'], true)) {
        json_err('Source non valido (scryfall|custom)');
    }
    $where[]  = 'source = ?';
    $params[] = $source;
}

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
               double_strike, lifelink, reach, defender, rarity, image_url, source
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
