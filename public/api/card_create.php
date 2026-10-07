<?php
/**
 * Gestione carte CUSTOM (Fucina delle Carte).
 *
 *   GET    ?source=custom          -> lista delle carte custom create finora
 *   POST   { name, colors, mana_value, power, toughness, flying, first_strike,
 *            deathtouch, trample, double_strike, lifelink, reach, defender,
 *            rarity, subtypes, art_note }  -> crea una nuova carta custom
 *   PUT    { id, ...stessi campi } -> aggiorna una carta custom esistente (mai una scryfall)
 *   DELETE ?id=custom_xxxx         -> elimina una carta custom (mai una scryfall)
 *
 * Le carte create qui hanno source='custom' e sono usabili ovunque nel gioco
 * (draft, deckbuilder, campagna) esattamente come le altre: /api/cards.php le
 * restituisce già, perché filtra solo su enabled=1.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/http.php';
require_once __DIR__ . '/../../src/db.php';

const RARITIES = ['common', 'uncommon', 'rare', 'mythic'];
const KW_FIELDS = ['flying', 'first_strike', 'deathtouch', 'trample', 'double_strike', 'lifelink', 'reach', 'defender'];

/** Valida i campi comuni di POST/PUT. Chiama json_err (never) se qualcosa non va. */
function validate_card_fields(array $in): array
{
    $name = trim((string) ($in['name'] ?? ''));
    if ($name === '' || mb_strlen($name) > 255) {
        json_err('Nome mancante o troppo lungo (max 255 caratteri)');
    }

    $colorsIn = $in['colors'] ?? '';
    $colorsArr = is_array($colorsIn) ? $colorsIn : str_split((string) $colorsIn);
    $colors = '';
    foreach (['W', 'U', 'B', 'R', 'G'] as $c) {
        if (in_array($c, array_map('strtoupper', $colorsArr), true)) {
            $colors .= $c;
        }
    }

    $manaValue = (int) ($in['mana_value'] ?? 0);
    if ($manaValue < 0 || $manaValue > 10) {
        json_err('Costo di mana non valido (0-10)');
    }
    $power = (int) ($in['power'] ?? 0);
    $toughness = (int) ($in['toughness'] ?? 0);
    if ($power < 0 || $power > 99 || $toughness < 0 || $toughness > 99) {
        json_err('Forza/Costituzione non valide (0-99)');
    }

    $rarity = strtolower(trim((string) ($in['rarity'] ?? 'common')));
    if (!in_array($rarity, RARITIES, true)) {
        json_err('Rarità non valida (common/uncommon/rare/mythic)');
    }

    $subtypes = trim((string) ($in['subtypes'] ?? ''));
    $subtypes = preg_replace('/[^A-Za-zÀ-ÿ ,\'-]/u', '', $subtypes) ?? '';
    $subtypes = mb_substr($subtypes, 0, 255);

    // Nota per l'artista AI: testo libero (idee, ambientazione, posa...), niente HTML/controlli.
    $artNote = trim((string) ($in['art_note'] ?? ''));
    $artNote = preg_replace('/[\x00-\x1F\x7F<>]/u', '', $artNote) ?? '';
    $artNote = mb_substr($artNote, 0, 500);

    $kw = [];
    foreach (KW_FIELDS as $f) {
        $kw[$f] = !empty($in[$f]) ? 1 : 0;
    }

    return compact('name', 'colors', 'manaValue', 'power', 'toughness', 'rarity', 'subtypes', 'artNote', 'kw');
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $rows = db()->query(
        "SELECT id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch,
                trample, double_strike, lifelink, reach, defender, rarity, subtypes, art_note, image_url, source
         FROM " . TBL_CARDS . " WHERE source = 'custom' ORDER BY name"
    )->fetchAll();
    foreach ($rows as &$r) {
        foreach (['mana_value', 'power', 'toughness', ...KW_FIELDS] as $f) {
            $r[$f] = (int) $r[$f];
        }
    }
    unset($r);
    json_out(['ok' => true, 'cards' => $rows]);
}

if ($method === 'DELETE') {
    parse_str(file_get_contents('php://input') ?: ($_SERVER['QUERY_STRING'] ?? ''), $q);
    $id = trim((string) ($_GET['id'] ?? $q['id'] ?? ''));
    if ($id === '') {
        json_err('id mancante');
    }
    $stmt = db()->prepare('DELETE FROM ' . TBL_CARDS . " WHERE id = ? AND source = 'custom'");
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) {
        json_err('Carta non trovata (o non è una carta custom, non eliminabile)', 404);
    }
    json_out(['ok' => true]);
}

if ($method === 'POST') {
    $in = read_input();
    $f  = validate_card_fields($in);
    $id = 'custom_' . bin2hex(random_bytes(8));

    $stmt = db()->prepare(
        'INSERT INTO ' . TBL_CARDS . ' (id, name, mana_value, colors, power, toughness,
            flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender,
            rarity, subtypes, art_note, image_url, enabled, source)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, 1, \'custom\')'
    );
    $stmt->execute([
        $id, $f['name'], $f['manaValue'], $f['colors'], $f['power'], $f['toughness'],
        $f['kw']['flying'], $f['kw']['first_strike'], $f['kw']['deathtouch'], $f['kw']['trample'],
        $f['kw']['double_strike'], $f['kw']['lifelink'], $f['kw']['reach'], $f['kw']['defender'],
        $f['rarity'], $f['subtypes'], $f['artNote'],
    ]);

    json_out(['ok' => true, 'id' => $id], 201);
}

if ($method === 'PUT') {
    $in = read_input();
    $id = trim((string) ($in['id'] ?? ''));
    if ($id === '') {
        json_err('id mancante');
    }
    $f = validate_card_fields($in);

    $stmt = db()->prepare(
        'UPDATE ' . TBL_CARDS . ' SET name = ?, mana_value = ?, colors = ?, power = ?, toughness = ?,
            flying = ?, first_strike = ?, deathtouch = ?, trample = ?, double_strike = ?,
            lifelink = ?, reach = ?, defender = ?, rarity = ?, subtypes = ?, art_note = ?
         WHERE id = ? AND source = \'custom\''
    );
    $stmt->execute([
        $f['name'], $f['manaValue'], $f['colors'], $f['power'], $f['toughness'],
        $f['kw']['flying'], $f['kw']['first_strike'], $f['kw']['deathtouch'], $f['kw']['trample'],
        $f['kw']['double_strike'], $f['kw']['lifelink'], $f['kw']['reach'], $f['kw']['defender'],
        $f['rarity'], $f['subtypes'], $f['artNote'], $id,
    ]);

    $row = db()->prepare('SELECT id FROM ' . TBL_CARDS . " WHERE id = ? AND source = 'custom'");
    $row->execute([$id]);
    if (!$row->fetch()) {
        json_err('Carta non trovata (o non è una carta custom)', 404);
    }

    json_out(['ok' => true, 'id' => $id]);
}

json_err('Metodo non supportato', 405);
