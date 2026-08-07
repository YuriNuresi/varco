<?php
/**
 * GET  -> lista mazzi salvati.
 * POST -> crea un mazzo: { name, color, card_ids: [...] }.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/http.php';
require_once __DIR__ . '/../../src/db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $rows = db()->query('SELECT id, name, color, card_ids, created_at FROM ' . TBL_DECKS . ' ORDER BY created_at DESC')->fetchAll();
    foreach ($rows as &$r) {
        $r['card_ids'] = json_decode($r['card_ids'], true) ?: [];
        $r['size']     = count($r['card_ids']);
    }
    unset($r);
    json_out(['ok' => true, 'decks' => $rows]);
}

if ($method === 'POST') {
    $in    = read_input();
    $name  = trim((string) ($in['name'] ?? ''));
    $color = strtoupper(trim((string) ($in['color'] ?? '')));
    $ids   = $in['card_ids'] ?? [];

    if ($name === '') {
        json_err('Nome mazzo mancante');
    }
    if (!preg_match('/^[WUBRGC]$/', $color)) {
        json_err('Colore mazzo non valido (W/U/B/R/G/C)');
    }
    if (!is_array($ids)) {
        json_err('card_ids non valido');
    }
    if (count($ids) < DECK_MIN_SIZE || count($ids) > DECK_MAX_SIZE) {
        json_err('Il mazzo deve avere tra ' . DECK_MIN_SIZE . ' e ' . DECK_MAX_SIZE . ' carte (ne hai ' . count($ids) . ')');
    }
    // Max copie per carta.
    $counts = array_count_values(array_map('strval', $ids));
    foreach ($counts as $cid => $n) {
        if ($n > MAX_COPIES) {
            json_err('Massimo ' . MAX_COPIES . ' copie per carta (carta ' . $cid . ' ha ' . $n . ' copie)');
        }
    }

    // Verifica che le carte esistano, siano enabled e del colore giusto.
    $ids   = array_values(array_map('strval', $ids));
    $place = implode(',', array_fill(0, count($ids), '?'));
    $stmt  = db()->prepare('SELECT id, colors FROM ' . TBL_CARDS . " WHERE id IN ($place) AND enabled = 1 AND source = 'custom'");
    $stmt->execute($ids);
    $found = [];
    foreach ($stmt->fetchAll() as $row) {
        $found[$row['id']] = $row['colors'];
    }

    foreach ($ids as $id) {
        if (!isset($found[$id])) {
            json_err("Carta non valida o disabilitata: $id");
        }
        $cardColors = $found[$id];
        $isColorless = $cardColors === '';
        if ($color === 'C') {
            if (!$isColorless) {
                json_err("Carta non incolore nel mazzo incolore: $id");
            }
        } elseif (strpos($cardColors, $color) === false) {
            json_err("Carta non del colore $color: $id");
        }
    }

    $stmt = db()->prepare('INSERT INTO ' . TBL_DECKS . ' (name, color, card_ids) VALUES (?, ?, ?)');
    $stmt->execute([$name, $color, json_encode($ids)]);

    json_out(['ok' => true, 'id' => (int) db()->lastInsertId()], 201);
}

json_err('Metodo non supportato', 405);
