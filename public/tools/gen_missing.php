<?php
/**
 * TEST TEMPORANEO — genera le carte mancanti: Angeli, Templari, Zombi, Giganti, Tritoni (da cancellare).
 *
 *   GET /tools/gen_missing.php?secret=XXX                → DRY RUN (piano)
 *   GET /tools/gen_missing.php?secret=XXX&mode=apply     → inserisce le carte + genera fino a 5 immagini
 *                                                          (richiamare finché remaining_images > 0)
 */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../../imagen/lib/imagen.php';
imagen_boot();

$env = __DIR__ . '/../../../.env';
$SECRET = '';
foreach (file($env, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), 'IMAGE_WORKER_SECRET=')) {
        $SECRET = trim(substr(trim($line), strlen('IMAGE_WORKER_SECRET=')));
        break;
    }
}
if ($SECRET === '' || !hash_equals($SECRET, (string) ($_GET['secret'] ?? ''))) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}
$apply = (($_GET['mode'] ?? '') === 'apply');
$batch = max(1, min(6, (int) ($_GET['batch'] ?? 5)));
$pdo = db();

// ── Palette per colore (stesse del card editor) ───────────────────────────────
$PAL = [
    'W' => 'radiant ivory and antique gold palette, holy sunlight rays, alabaster marble and white feathers',
    'U' => 'deep sapphire blue and cyan palette, glowing arcane glyphs, sea mist and cold starlight',
    'B' => 'obsidian black and necrotic violet palette, eerie green ghost-light, bone and creeping shadow',
    'R' => 'crimson red and ember orange palette, flying sparks, volcanic smoke and molten light',
];
$style = fn(string $col) => '. Dark fantasy trading card game illustration, painterly style, ' . $PAL[$col]
    . ', dramatic cinematic lighting, highly detailed, dark vignette edges, '
    . 'no text, no watermark, no logo, no card frame, portrait composition';

// ── Le 49 carte mancanti ──────────────────────────────────────────────────────
// [nome, colors, subtype, mv, pow, tou, rarity, keywords[], descrizione immagine]
$CARDS = [
    // ANGELI (W) — +10 → 12  [c4 u4 r3 m1]
    ['Cherubino del Varco',      'W', 'Angel', 1, 1, 1, 'common',   ['flying'], 'a small cherub angel with golden wings emerging from a dimensional rift'],
    ['Angelo Novizio',           'W', 'Angel', 2, 2, 1, 'common',   ['flying'], 'a young novice angel with white wings and a simple radiant halo'],
    ['Angelo Pellegrino',        'W', 'Angel', 2, 1, 3, 'common',   ['flying'], 'a pilgrim angel in flowing robes walking a path of light'],
    ['Angelo Custode',           'W', 'Angel', 3, 2, 3, 'common',   ['flying', 'lifelink'], 'a guardian angel shielding a beam of holy light with outstretched wings'],
    ['Angelo Messaggero',        'W', 'Angel', 3, 3, 2, 'uncommon', ['flying'], 'a swift messenger angel diving through clouds carrying a glowing scroll'],
    ['Angelo della Battaglia',   'W', 'Angel', 4, 3, 3, 'uncommon', ['flying', 'first_strike'], 'a battle angel in gleaming armor wielding a flaming sword'],
    ['Angelo Radioso',           'W', 'Angel', 4, 4, 3, 'uncommon', ['flying', 'lifelink'], 'a radiant angel glowing with healing golden light, arms open'],
    ['Angelo del Crepuscolo',    'W', 'Angel', 5, 4, 4, 'uncommon', ['flying'], 'a twilight angel with wings of fading sunset light, serene and powerful'],
    ['Angelo Vendicatore',       'W', 'Angel', 5, 5, 4, 'rare',     ['flying', 'first_strike'], 'an avenging angel descending with twin blazing swords, wrathful and majestic'],
    ['Arcangelo del Varco',      'W', 'Angel', 6, 6, 6, 'mythic',   ['flying', 'lifelink'], 'a colossal archangel with six burning wings towering over a dimensional gate'],

    // TEMPLARI (W) — +11 → 12  [c4 u4 r3 m1]
    ['Scudiero del Varco',       'W', 'Knight', 1, 1, 2, 'common',   [], 'a young squire polishing a shield engraved with a glowing portal sigil'],
    ['Templare Novizio',         'W', 'Knight', 2, 2, 2, 'common',   ['first_strike'], 'a novice templar knight in white tabard with a simple sword'],
    ['Templare della Guardia',   'W', 'Knight', 3, 2, 4, 'common',   ['defender'], 'a templar guard holding a tower shield at a marble gate'],
    ['Cavaliere Errante',        'W', 'Knight', 3, 3, 2, 'common',   [], 'a wandering knight on foot with a weathered cloak and shining blade'],
    ['Templare del Giuramento',  'W', 'Knight', 3, 3, 3, 'uncommon', ['first_strike'], 'a templar swearing an oath, sword raised to a beam of holy light'],
    ['Cavaliere del Sole',       'W', 'Knight', 4, 3, 4, 'uncommon', ['lifelink'], 'a knight in golden sun-emblazoned armor radiating warm light'],
    ['Templare Crociato',        'W', 'Knight', 4, 4, 3, 'uncommon', ['first_strike'], 'a crusader templar charging with lance and billowing white banner'],
    ['Cavaliere della Lancia',   'W', 'Knight', 5, 4, 4, 'uncommon', ['first_strike'], 'a mounted knight with a gleaming lance in mid-charge'],
    ['Comandante Templare',      'W', 'Knight', 5, 5, 4, 'rare',     ['first_strike'], 'a templar commander directing troops, ornate armor and commanding presence'],
    ['Gran Templare del Varco',  'W', 'Knight', 5, 4, 5, 'rare',     ['double_strike'], 'a grand templar wreathed in portal light wielding two blessed swords'],
    ['Gran Maestro dell\'Ordine','W', 'Knight', 6, 6, 5, 'mythic',   ['double_strike', 'lifelink'], 'the grandmaster of the templar order enthroned in radiant armor with ceremonial greatsword'],

    // ZOMBI (B) — +9 → 12  [c4 u4 r3 m1]
    ['Zombi Putrescente',        'B', 'Zombie', 2, 2, 1, 'common',   [], 'a rotting zombie shambling forward with torn burial clothes'],
    ['Zombi Errante',            'B', 'Zombie', 2, 1, 3, 'common',   [], 'a wandering zombie dragging chains through a foggy graveyard'],
    ['Massa di Zombi',           'B', 'Zombie', 3, 3, 2, 'uncommon', [], 'a writhing mass of zombie bodies clawing forward as one horror'],
    ['Zombi Divoratore',         'B', 'Zombie', 3, 2, 3, 'uncommon', ['deathtouch'], 'a hungry zombie with dripping venomous jaws and hollow eyes'],
    ['Becchino Zombi',           'B', 'Zombie', 4, 3, 3, 'uncommon', [], 'an undead gravedigger zombie with a rusted shovel raising corpses'],
    ['Orda Putrida',             'B', 'Zombie', 4, 4, 3, 'rare',     [], 'a putrid horde of zombies swarming over a ruined battlefield'],
    ['Zombi Colossale',          'B', 'Zombie', 5, 5, 4, 'rare',     ['trample'], 'a colossal stitched zombie giant towering over tombstones'],
    ['Signore degli Zombi',      'B', 'Zombie', 5, 4, 4, 'rare',     ['deathtouch'], 'a zombie lord with a bone crown commanding the undead with necrotic magic'],
    ['Re Lich del Varco',        'B', 'Zombie', 6, 6, 5, 'mythic',   ['deathtouch', 'lifelink'], 'a lich king on a throne of bones, crowned with ghostly green fire, dimensional rift behind'],

    // GIGANTI (R) — +7 → 12  [c4 u4 r3 m1]
    ['Gigante Giovane',          'R', 'Giant', 3, 3, 2, 'common',   [], 'a young fiery giant stomping through a mountain pass'],
    ['Gigante Lanciamassi',      'R', 'Giant', 4, 4, 3, 'uncommon', ['reach'], 'a giant hurling a massive burning boulder from a cliff'],
    ['Gigante della Fornace',    'R', 'Giant', 4, 3, 4, 'uncommon', [], 'a furnace giant with glowing cracks of magma across its stone skin'],
    ['Gigante Furioso',          'R', 'Giant', 5, 5, 3, 'uncommon', ['trample'], 'a furious giant swinging a burning tree trunk, roaring'],
    ['Gigante del Terremoto',    'R', 'Giant', 5, 5, 4, 'rare',     ['trample'], 'an earthquake giant splitting the ground with a colossal hammer blow'],
    ['Gigante Vulcanico',        'R', 'Giant', 6, 6, 5, 'rare',     ['trample'], 'a volcanic giant erupting with lava veins, ash clouds swirling'],
    ['Re dei Giganti del Varco', 'R', 'Giant', 6, 7, 6, 'mythic',   ['trample'], 'the king of giants crowned in molten gold striding through a dimensional rift'],

    // TRITONI (U) — +12 → 12  [c4 u4 r3 m1]
    ['Tritone Esploratore',      'U', 'Triton', 1, 1, 1, 'common',   [], 'a merfolk triton scout gliding through coral reefs with a small trident'],
    ['Tritone del Varco',        'U', 'Triton', 2, 2, 1, 'common',   [], 'a triton warrior emerging from a glowing dimensional rift underwater'],
    ['Guardia della Marea',      'U', 'Triton', 2, 1, 3, 'common',   ['defender'], 'a tide guard triton holding a shell shield against crashing waves'],
    ['Tritone Pescatore',        'U', 'Triton', 3, 2, 3, 'common',   [], 'a triton fisher casting a net of glowing water threads'],
    ['Incantatore delle Onde',   'U', 'Triton', 3, 3, 2, 'uncommon', [], 'a triton wave enchanter weaving spirals of luminous water magic'],
    ['Tritone Cavalcaonde',      'U', 'Triton', 4, 3, 3, 'uncommon', [], 'a triton riding a towering wave with a coral spear raised'],
    ['Lanciere della Barriera',  'U', 'Triton', 4, 2, 5, 'uncommon', ['reach'], 'a barrier lancer triton with a long coral pike guarding a glowing reef wall'],
    ['Sacerdotessa degli Abissi','U', 'Triton', 5, 4, 3, 'uncommon', ['lifelink'], 'a triton priestess of the depths channeling bioluminescent healing light'],
    ['Tritone Maremoto',         'U', 'Triton', 5, 4, 4, 'rare',     ['trample'], 'a tidal wave triton unleashing a devastating wall of water'],
    ['Custode della Fossa Abissale', 'U', 'Triton', 5, 3, 6, 'rare', ['defender'], 'an abyssal trench guardian triton in dark waters lit by anglerfish light'],
    ['Araldo delle Profondità',  'U', 'Triton', 6, 5, 5, 'rare',     [], 'a herald of the depths blowing a great conch shell summoning the sea'],
    ['Re Tritone del Varco',     'U', 'Triton', 6, 6, 5, 'mythic',   [], 'the triton king on a throne of coral and pearls, trident crackling with storm light, rift glowing behind'],
];

$KEYS = ['flying', 'first_strike', 'deathtouch', 'trample', 'double_strike', 'lifelink', 'reach', 'defender'];

function card_slug(string $name): string
{
    return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-') ?: 'card';
}

// ── STEP 1: inserimento carte mancanti (idempotente per nome+subtype) ─────────
$inserted = [];
$existing = 0;
$ins = $pdo->prepare('INSERT INTO ' . TBL_CARDS . '
    (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample,
     double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,\'custom\')');
$chk = $pdo->prepare('SELECT COUNT(*) FROM ' . TBL_CARDS . ' WHERE name = ? AND subtypes = ? AND source = \'custom\'');

foreach ($CARDS as $c) {
    [$name, $col, $sub, $mv, $pow, $tou, $rar, $kw] = $c;
    $chk->execute([$name, $sub]);
    if ((int) $chk->fetchColumn() > 0) { $existing++; continue; }
    if ($apply) {
        $flags = array_map(fn($k) => in_array($k, $kw, true) ? 1 : 0, $KEYS);
        $ins->execute(array_merge(
            [strtoupper(bin2hex(random_bytes(16))), $name, $mv, $col, $pow, $tou],
            $flags,
            [$rar, $sub, '']
        ));
    }
    $inserted[] = $name;
}

// ── STEP 2: immagini per le carte di questo set che ne sono prive ─────────────
$names = array_map(fn($c) => $c[0], $CARDS);
$place = implode(',', array_fill(0, count($names), '?'));
$noImg = $pdo->prepare('SELECT id, name, colors, subtypes FROM ' . TBL_CARDS .
    " WHERE source = 'custom' AND (image_url IS NULL OR image_url = '') AND name IN ($place)");
$noImg->execute($names);
$pending = $noImg->fetchAll(PDO::FETCH_ASSOC);

$byName = [];
foreach ($CARDS as $c) { $byName[$c[0]] = $c; }

$generated = [];
$failed = [];
if ($apply) {
    ignore_user_abort(true);
    set_time_limit(150);
    $upd = $pdo->prepare('UPDATE ' . TBL_CARDS . ' SET image_url = ? WHERE id = ?');
    foreach (array_slice($pending, 0, $batch) as $row) {
        $def = $byName[$row['name']] ?? null;
        if (!$def) { continue; }
        [$name, $col, $sub, $mv, $pow, $tou, $rar, $kw, $desc] = $def;

        $rarityWord = $rar === 'mythic' ? 'legendary epic ' : ($rar === 'rare' ? 'powerful majestic ' : '');
        $prompt = $rarityWord . $desc . $style($col);

        $slug = card_slug($name);
        $rel  = 'assets/cards/card_' . $slug . '_' . bin2hex(random_bytes(3)) . '.png';
        $abs  = __DIR__ . '/../' . $rel;
        $dir  = dirname($abs);
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }

        try {
            imagen_generate([
                'prompt'      => $prompt,
                'kind'        => 'card_art',
                'target_path' => $rel,
                'ref_id'      => $slug,
                'game_id'     => 'varco',
                'scenario_id' => 'gen-missing',
                'width'       => 512,
                'height'      => 768,
                'seed'        => random_int(0, 999999),
            ], $abs);
            $upd->execute([$rel, $row['id']]);
            $generated[] = $name;
        } catch (Throwable $e) {
            $failed[] = $name . ': ' . mb_substr($e->getMessage(), 0, 400);
        }
    }
}

echo json_encode([
    'mode'             => $apply ? 'APPLY' : 'DRY RUN',
    'defined'          => count($CARDS),
    'already_in_db'    => $existing,
    'inserted_now'     => count($inserted),
    'images_generated' => $generated,
    'images_failed'    => $failed,
    'remaining_images' => max(0, count($pending) - ($apply ? count($generated) : 0)),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
