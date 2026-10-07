<?php
/**
 * API admin per carte custom (protetta da INSTALL_KEY).
 *
 * GET              — lista carte custom
 * POST             — crea carta custom
 * POST &action=generate_image — genera illustrazione via motore centralizzato ~/imagen
 * PUT              — aggiorna carta custom (richiede id nel body)
 * DELETE           — elimina carta custom (richiede id nel body)
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/http.php';
require_once __DIR__ . '/../../src/db.php';

require_once __DIR__ . '/../../src/auth.php';

$key = $_GET['key'] ?? $_SERVER['HTTP_X_ADMIN_KEY'] ?? '';
if ($key !== INSTALL_KEY && !is_admin()) {
    json_err('Accesso riservato agli admin', 403);
}

$method = $_SERVER['REQUEST_METHOD'];

// --- GET: lista carte custom, o stato dei provider immagine -----------------
if ($method === 'GET') {
    if (($_GET['action'] ?? '') === 'providers') {
        $IMAGEN = __DIR__ . '/../../../imagen/lib/imagen.php';
        if (!is_file($IMAGEN)) json_err('Motore imagen non trovato');
        require_once $IMAGEN;
        imagen_boot();
        json_out(['ok' => true, 'providers' => imagen_available_providers()]);
    }

    $where  = ["source = 'custom'"];
    $params = [];

    if (isset($_GET['q']) && trim((string) $_GET['q']) !== '') {
        $where[]  = 'name LIKE ?';
        $params[] = '%' . trim((string) $_GET['q']) . '%';
    }

    $sql = 'SELECT * FROM ' . TBL_CARDS . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY name LIMIT 500';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    json_out(['ok' => true, 'cards' => $stmt->fetchAll()]);
}

// --- POST -------------------------------------------------------------------
if ($method === 'POST') {
    $in = read_input();
    $action = (string) ($_GET['action'] ?? '');

    // --- Helpers: carica il motore immagini centralizzato (~/imagen) ---
    $__loadHelios = function () {
        static $loaded = false;
        if ($loaded) return;
        $loaded = true;

        $IMAGEN = __DIR__ . '/../../../imagen/lib/imagen.php';
        if (!is_file($IMAGEN)) json_err('Motore imagen non trovato');
        require_once $IMAGEN;
        imagen_boot();   // carica ~/.env da solo
    };

    // --- Generazione immagine AI (singolo provider, legacy) ---
    if ($action === 'generate_image') {
        $prompt = trim((string) ($in['prompt'] ?? ''));
        $cardName = trim((string) ($in['card_name'] ?? 'card'));
        if ($prompt === '') json_err('Prompt mancante');

        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($cardName));
        $slug = trim($slug, '-') ?: 'card';
        $filename = 'card_' . $slug . '_' . bin2hex(random_bytes(4)) . '.png';
        $relPath  = 'assets/cards/' . $filename;
        $absPath  = __DIR__ . '/../' . $relPath;
        $absDir   = dirname($absPath);
        if (!is_dir($absDir)) @mkdir($absDir, 0775, true);

        $__loadHelios();
        set_time_limit(120);

        $job = [
            'prompt'      => $prompt,
            'kind'        => 'card_art',
            'target_path' => $relPath,
            'ref_id'      => $slug,
            'game_id'     => 'varco',
            'scenario_id' => 'card-editor',
            'width'       => 512,
            'height'      => 768,
            'seed'        => random_int(0, 999999),   // varia a ogni click
        ];

        try {
            image_gen_dispatch($job, $absPath);
            $webUrl = $relPath;
            $provider = defined('IMAGE_PROVIDER') ? IMAGE_PROVIDER : '?';
            json_out(['ok' => true, 'url' => $webUrl, 'provider' => $provider]);
        } catch (Throwable $e) {
            json_err('Generazione fallita: ' . $e->getMessage());
        }
    }

    // --- Generazione con UN SOLO provider scelto (1 bottone = 1 chiamata free-tier) ---
    if ($action === 'generate_image_one') {
        $prompt = trim((string) ($in['prompt'] ?? ''));
        $cardName = trim((string) ($in['card_name'] ?? 'card'));
        $provider = trim((string) ($in['provider'] ?? ''));
        if ($prompt === '') json_err('Prompt mancante');
        if ($provider === '') json_err('Provider mancante');

        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($cardName));
        $slug = trim($slug, '-') ?: 'card';

        $__loadHelios();
        set_time_limit(90);

        $providers = imagen_available_providers();
        if (!isset($providers[$provider])) json_err('Provider sconosciuto: ' . $provider);
        if (!$providers[$provider]['ready']) {
            json_out(['ok' => false, 'provider' => $provider,
                'error' => 'Quota esaurita — riprovo dopo le ' . gmdate('H:i', $providers[$provider]['until']) . ' UTC']);
        }

        $filename = 'card_' . $slug . '_' . $provider . '_' . bin2hex(random_bytes(3)) . '.png';
        $relPath  = 'assets/cards/' . $filename;
        $absPath  = __DIR__ . '/../' . $relPath;
        $absDir   = dirname($absPath);
        if (!is_dir($absDir)) @mkdir($absDir, 0775, true);

        $job = [
            'prompt'      => $prompt,
            'kind'        => 'card_art',
            'target_path' => $relPath,
            'ref_id'      => $slug,
            'game_id'     => 'varco',
            'scenario_id' => 'card-editor',
            'width'       => 512,
            'height'      => 768,
            'seed'        => random_int(0, 999999),   // varia a ogni click
        ];

        try {
            imagen_generate_one($provider, $job, $absPath);
            json_out(['ok' => true, 'provider' => $provider, 'url' => $relPath]);
        } catch (Throwable $e) {
            imagen_handle_quota_error($provider, $e->getMessage());   // aggiorna il ledger
            json_out(['ok' => false, 'provider' => $provider, 'error' => $e->getMessage()]);
        }
    }

    // --- Crea carta custom ---------------------------------------------------
    $card = validate_card($in);
    $id = $card['id'] ?: bin2hex(random_bytes(16));

    $stmt = db()->prepare('INSERT INTO ' . TBL_CARDS .
        ' (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample,
           double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, \'custom\')');
    $stmt->execute([
        $id, $card['name'], $card['mana_value'], $card['colors'],
        $card['power'], $card['toughness'],
        $card['flying'], $card['first_strike'], $card['deathtouch'], $card['trample'],
        $card['double_strike'], $card['lifelink'], $card['reach'], $card['defender'],
        $card['rarity'], $card['subtypes'], $card['image_url'],
    ]);

    json_out(['ok' => true, 'id' => $id], 201);
}

// --- PUT: aggiorna carta custom ---------------------------------------------
if ($method === 'PUT') {
    $in = read_input();
    $id = trim((string) ($in['id'] ?? ''));
    if ($id === '') json_err('id mancante');

    $existing = db()->prepare('SELECT source FROM ' . TBL_CARDS . ' WHERE id = ?');
    $existing->execute([$id]);
    $row = $existing->fetch();
    if (!$row) json_err('Carta non trovata', 404);
    if ($row['source'] !== 'custom') json_err('Non puoi modificare carte Scryfall');

    $card = validate_card($in);

    $stmt = db()->prepare('UPDATE ' . TBL_CARDS . ' SET
        name=?, mana_value=?, colors=?, power=?, toughness=?,
        flying=?, first_strike=?, deathtouch=?, trample=?,
        double_strike=?, lifelink=?, reach=?, defender=?,
        rarity=?, subtypes=?, image_url=?, enabled=?
        WHERE id=? AND source=\'custom\'');
    $stmt->execute([
        $card['name'], $card['mana_value'], $card['colors'],
        $card['power'], $card['toughness'],
        $card['flying'], $card['first_strike'], $card['deathtouch'], $card['trample'],
        $card['double_strike'], $card['lifelink'], $card['reach'], $card['defender'],
        $card['rarity'], $card['subtypes'], $card['image_url'], $card['enabled'] ? 1 : 0,
        $id,
    ]);

    json_out(['ok' => true]);
}

// --- DELETE: elimina carta custom -------------------------------------------
if ($method === 'DELETE') {
    $in = read_input();
    $id = trim((string) ($in['id'] ?? ''));
    if ($id === '') json_err('id mancante');

    $stmt = db()->prepare('DELETE FROM ' . TBL_CARDS . ' WHERE id = ? AND source = \'custom\'');
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) json_err('Carta non trovata o non custom', 404);
    json_out(['ok' => true]);
}

json_err('Metodo non supportato', 405);

// --- Validazione campi carta ------------------------------------------------
function validate_card(array $in): array
{
    $name = trim((string) ($in['name'] ?? ''));
    if ($name === '') json_err('Nome carta mancante');

    $colors = strtoupper(trim((string) ($in['colors'] ?? '')));
    if ($colors !== '' && !preg_match('/^[WUBRG]+$/', $colors)) {
        json_err('Colori non validi (combinazione di W/U/B/R/G, vuoto per incolore)');
    }

    $rarity = strtolower(trim((string) ($in['rarity'] ?? 'common')));
    if (!in_array($rarity, ['common', 'uncommon', 'rare', 'mythic'], true)) {
        json_err('Rarità non valida');
    }

    $subtypes = trim((string) ($in['subtypes'] ?? ''));

    return [
        'id'            => trim((string) ($in['id'] ?? '')),
        'name'          => $name,
        'mana_value'    => max(0, (int) ($in['mana_value'] ?? 0)),
        'colors'        => $colors,
        'power'         => max(0, (int) ($in['power'] ?? 0)),
        'toughness'     => max(0, (int) ($in['toughness'] ?? 0)),
        'flying'        => !empty($in['flying']) ? 1 : 0,
        'first_strike'  => !empty($in['first_strike']) ? 1 : 0,
        'deathtouch'    => !empty($in['deathtouch']) ? 1 : 0,
        'trample'       => !empty($in['trample']) ? 1 : 0,
        'double_strike' => !empty($in['double_strike']) ? 1 : 0,
        'lifelink'      => !empty($in['lifelink']) ? 1 : 0,
        'reach'         => !empty($in['reach']) ? 1 : 0,
        'defender'      => !empty($in['defender']) ? 1 : 0,
        'rarity'        => $rarity,
        'subtypes'      => $subtypes,
        'image_url'     => trim((string) ($in['image_url'] ?? '')),
        'enabled'       => ($in['enabled'] ?? true),
    ];
}
