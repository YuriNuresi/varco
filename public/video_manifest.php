<?php
/**
 * Varco — endpoint manifesto per game-video-studio.
 *
 * Genera uno storyboard no-spoiler per uno short verticale 9:16.
 * Il renderer (render_video.py) chiama questo endpoint e usa il JSON
 * per montare il video con Ken Burns + TTS + karaoke + musica.
 *
 * Query string:
 *   secret=XXX       — obbligatorio (MANIFEST_SECRET nei secret GitHub)
 *   lang=it|en       — opzionale, default 'it'
 *   valley=W|U|B|R|G — opzionale: forza una valle; ometti per rotazione settimanale
 *   latest=1         — alias per "usa la valle della settimana" (compatibilità workflow)
 *
 * Immagini: usa i background già generati da gen_bg.php (assets/bg/).
 * Se non ce ne sono abbastanza il manifesto ripiega sulle immagini base del gioco.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/db.php';

// ── Auth ─────────────────────────────────────────────────────────────────────
$secret = env('MANIFEST_SECRET', '');
if ($secret === '' || ($_GET['secret'] ?? '') !== $secret) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$lang = in_array($_GET['lang'] ?? '', ['it', 'en'], true) ? $_GET['lang'] : 'it';

// ── Valle della settimana (rotazione Mon-Sun ciclica su 5 valli) ──────────────
$VALLEYS = ['W', 'U', 'B', 'R', 'G'];
$VALLEY_META = [
    'W' => ['tribe' => 'Soldier', 'name_it' => 'Avamposto dei Soldati', 'name_en' => 'Soldiers\' Outpost',
            'color' => 'bianco', 'color_en' => 'white'],
    'U' => ['tribe' => 'Wizard',  'name_it' => 'Torre dei Maghi',       'name_en' => 'Tower of Wizards',
            'color' => 'blu',    'color_en' => 'blue'],
    'B' => ['tribe' => 'Zombie',  'name_it' => 'Cripta degli Zombi',    'name_en' => 'Crypt of Zombies',
            'color' => 'nero',   'color_en' => 'black'],
    'R' => ['tribe' => 'Goblin',  'name_it' => 'Accampamento Goblin',   'name_en' => 'Goblin Camp',
            'color' => 'rosso',  'color_en' => 'red'],
    'G' => ['tribe' => 'Elf',     'name_it' => 'Boschetto degli Elfi',  'name_en' => 'Elven Grove',
            'color' => 'verde',  'color_en' => 'green'],
];

$forceValley = strtoupper(trim($_GET['valley'] ?? ''));
if (isset($VALLEY_META[$forceValley])) {
    $valley = $forceValley;
} else {
    // Rotazione settimanale: settimana ISO % 5
    $week = (int) date('W');
    $valley = $VALLEYS[$week % 5];
}

$meta  = $VALLEY_META[$valley];
$tribe = $meta['tribe'];

// ── Carte rappresentative della tribù (fino a 4, con image_url) ──────────────
$db = db();
$stmt = $db->prepare(
    "SELECT id, name, mana_value, colors, power, toughness, rarity, image_url,
            flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender
     FROM " . TBL_CARDS . "
     WHERE enabled = 1 AND image_url IS NOT NULL AND image_url != ''
       AND colors LIKE :col
     ORDER BY RAND()
     LIMIT 6"
);
// Mappa colore → lettera WUBRG
$colorMap = ['W' => 'W', 'U' => 'U', 'B' => 'B', 'R' => 'R', 'G' => 'G'];
$stmt->execute([':col' => '%' . $colorMap[$valley] . '%']);
$cards = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fallback: qualsiasi carta con immagine se la tribù non ne ha abbastanza
if (count($cards) < 2) {
    $stmt2 = $db->prepare(
        "SELECT id, name, mana_value, colors, power, toughness, rarity, image_url,
                flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender
         FROM " . TBL_CARDS . "
         WHERE enabled = 1 AND image_url IS NOT NULL AND image_url != ''
         ORDER BY RAND() LIMIT 6"
    );
    $stmt2->execute();
    $cards = $stmt2->fetchAll(PDO::FETCH_ASSOC);
}

// ── Background: screenshot approvati dagli utenti → bg generati → fallback ───
$baseUrl     = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']
            . rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$approvedDir = __DIR__ . '/../screenshots/approved';
$shotFiles   = is_dir($approvedDir) ? glob($approvedDir . '/*.{jpg,jpeg,png}', GLOB_BRACE) : [];
shuffle($shotFiles); // screenshot utenti (hanno priorità come shot)

$bgDir   = __DIR__ . '/assets/bg';
$bgFiles = is_dir($bgDir) ? glob($bgDir . '/*.{jpg,jpeg,webp,png}', GLOB_BRACE) : [];
shuffle($bgFiles);

function approved_url(array &$shotFiles, string $baseUrl, string $key): ?string {
    $f = array_shift($shotFiles);
    if (!$f) return null;
    // Serviti via tools/screenshots.php (stesso gate dell'admin)
    return $baseUrl . '/tools/screenshots.php?key=' . urlencode($key) . '&img=approved/' . basename($f);
}

function bg_url(array &$bgFiles, string $baseUrl, string $fallback): string {
    $f = array_shift($bgFiles);
    if ($f) {
        $rel = '/assets/bg/' . basename($f);
        return $baseUrl . $rel;
    }
    return $baseUrl . '/assets/bg/' . $fallback;
}

// ── Testi dello storyboard (no-spoiler: evoca, non racconta) ─────────────────
function abilities_it(array $card): string {
    $ab = [];
    if ($card['flying'])        $ab[] = 'Volo';
    if ($card['first_strike'])  $ab[] = 'Attacco rapido';
    if ($card['deathtouch'])    $ab[] = 'Contatto letale';
    if ($card['trample'])       $ab[] = 'Travolgere';
    if ($card['double_strike']) $ab[] = 'Doppio attacco';
    if ($card['lifelink'])      $ab[] = 'Legame vitale';
    if ($card['reach'])         $ab[] = 'Portata';
    if ($card['defender'])      $ab[] = 'Difensore';
    return $ab ? implode(', ', $ab) : '';
}

function abilities_en(array $card): string {
    $ab = [];
    if ($card['flying'])        $ab[] = 'Flying';
    if ($card['first_strike'])  $ab[] = 'First Strike';
    if ($card['deathtouch'])    $ab[] = 'Deathtouch';
    if ($card['trample'])       $ab[] = 'Trample';
    if ($card['double_strike']) $ab[] = 'Double Strike';
    if ($card['lifelink'])      $ab[] = 'Lifelink';
    if ($card['reach'])         $ab[] = 'Reach';
    if ($card['defender'])      $ab[] = 'Defender';
    return $ab ? implode(', ', $ab) : '';
}

function rarity_it(string $r): string {
    return ['common'=>'comune','uncommon'=>'non comune','rare'=>'rara','mythic'=>'mitica'][$r] ?? $r;
}

// ── Costruisci gli shot ───────────────────────────────────────────────────────
$manifestSecret = env('MANIFEST_SECRET', '');
$shots    = [];
$imgPool  = array_column($cards, 'image_url'); // immagini dalle carte stesse

// Shot 0: apertura sulla valle — usa screenshot utente se disponibile
$bgShot0 = approved_url($shotFiles, $baseUrl, $manifestSecret) ?? bg_url($bgFiles, $baseUrl, 'arena.png');
if ($lang === 'en') {
    $valleyNameEn = $meta['name_en'];
    $shots[] = [
        'image' => $bgShot0,
        'vo'    => "The {$valleyNameEn}. A place where every card decides fate.",
    ];
} else {
    $valleyNameIt = $meta['name_it'];
    $shots[] = [
        'image' => $bgShot0,
        'vo'    => "{$valleyNameIt}. Un luogo dove ogni carta decide il destino.",
    ];
}

// ── Voce fuori campo: Groq genera frasi cinematiche sulla valle ───────────────
function groq_vo(string $valleyName, string $tribe, string $lang, array $abilities): array {
    $apiKey = env('GROQ_API_KEY', '');
    if ($apiKey === '') return [];

    $abilList = $abilities ? implode(', ', array_unique($abilities)) : '';
    $abilitiesHint = $abilList ? "Le creature hanno abilità come: {$abilList}." : '';

    if ($lang === 'en') {
        $prompt = "Write exactly 3 short voiceover lines for a 9:16 game trailer about '{$valleyName}' ({$tribe} creatures). {$abilitiesHint} Rules: no card names, no spoilers, cinematic and evocative, max 12 words each, present tense. Return only the 3 lines separated by newlines, nothing else.";
    } else {
        $prompt = "Scrivi esattamente 3 brevi frasi di voce fuori campo per un trailer 9:16 del luogo '{$valleyName}' (creature di tipo {$tribe}). {$abilitiesHint} Regole: niente nomi di carte, niente spoiler, stile cinematico ed evocativo, massimo 12 parole per frase, tempo presente. Rispondi solo con le 3 frasi separate da a capo, nient'altro.";
    }

    $payload = json_encode([
        'model'    => 'meta-llama/llama-4-scout-17b-16e-instruct',
        'messages' => [['role' => 'user', 'content' => $prompt]],
        'max_tokens' => 120,
        'temperature' => 0.8,
    ]);

    $ctx  = stream_context_create(['http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/json\r\nAuthorization: Bearer {$apiKey}\r\n",
        'content' => $payload,
        'timeout' => 20,
    ]]);
    $resp = @file_get_contents('https://api.groq.com/openai/v1/chat/completions', false, $ctx);
    if (!$resp) return [];

    $data = json_decode($resp, true);
    $text = trim($data['choices'][0]['message']['content'] ?? '');
    if (!$text) return [];

    $lines = array_values(array_filter(array_map('trim', explode("\n", $text))));
    return array_slice($lines, 0, 3);
}

// Raccogli abilità uniche dalle carte per dare contesto a Groq
$allAbilities = [];
foreach ($cards as $c) {
    if ($lang === 'en') { $ab = abilities_en($c); } else { $ab = abilities_it($c); }
    if ($ab) foreach (explode(', ', $ab) as $a) $allAbilities[] = trim($a);
}

$groqLines = groq_vo($meta['name_it'], $tribe, $lang, $allAbilities);

// Fallback se Groq non risponde
$fallbackLines = $lang === 'en'
    ? ["Every creature has its role. Every move, its price.", "Power or speed? Here, you choose.", "The arena awaits. Will you rise?"]
    : ["Ogni creatura ha il suo ruolo. Ogni mossa, il suo prezzo.", "Forza o velocità? Qui decidi tu.", "L'arena ti aspetta. Sarai all'altezza?"];
$voLines = count($groqLines) >= 3 ? $groqLines : $fallbackLines;

// Shot 1-3: immagine carta + voce cinematica (senza nominare la carta)
$showcards = array_slice($cards, 0, 3);
foreach ($showcards as $i => $card) {
    $img = $card['image_url'] ?: ($imgPool[$i] ?? bg_url($bgFiles, $baseUrl, 'arena.png'));
    $vo  = $voLines[$i] ?? $fallbackLines[$i];
    $shots[] = ['image' => $img, 'vo' => $vo];
}


// Shot 4: chiusura — altro screenshot utente o background
$bgFinal = approved_url($shotFiles, $baseUrl, $manifestSecret) ?? bg_url($bgFiles, $baseUrl, 'campaign.jpg');
if ($lang === 'en') {
    $shots[] = [
        'image' => $bgFinal,
        'vo'    => "Three lanes. Ten life points. Your choices write the story.",
    ];
} else {
    $shots[] = [
        'image' => $bgFinal,
        'vo'    => "Tre corsie. Dieci punti vita. Le tue scelte scrivono la storia.",
    ];
}

// ── Musica: Jamendo API (gratuita, CC) → fallback mp3 locale ─────────────────
// Jamendo ha 600k brani CC searchabili per tag. Chiave gratuita su developer.jamendo.com.
$JAMENDO_TAGS = [
    'W' => 'epic+orchestral+heroic',
    'U' => 'ambient+mystical+fantasy',
    'B' => 'dark+horror+gothic',
    'R' => 'aggressive+tribal+epic',
    'G' => 'nature+adventure+forest',
];

function jamendo_track(string $valley, array $tagMap): ?string {
    $clientId = env('JAMENDO_CLIENT_ID', '');
    if ($clientId === '') return null;

    $tags = $tagMap[$valley] ?? 'epic';
    // Prova prima con tag specifici, poi con tag generico se vuoto
    foreach ([$tags, 'epic', 'fantasy', 'ambient'] as $t) {
        $url  = "https://api.jamendo.com/v3.0/tracks/?client_id={$clientId}"
              . "&format=json&limit=20&tags={$t}&audioformat=mp31"
              . "&boost=popularity_week&order=popularity_week";
        $ctx  = stream_context_create(['http' => ['timeout' => 20]]);
        $resp = @file_get_contents($url, false, $ctx);
        if (!$resp) continue;
        $data   = json_decode($resp, true);
        $tracks = array_filter($data['results'] ?? [], fn($r) => !empty($r['audio']));
        if ($tracks) {
            $track = $tracks[array_rand($tracks)];
            return $track['audio'];
        }
    }
    return null;
}

$musicUrl = jamendo_track($valley, $JAMENDO_TAGS);

// Fallback: mp3 locali in magic/assets/music/
if (!$musicUrl) {
    $musicDir   = __DIR__ . '/../assets/music';
    $musicFiles = is_dir($musicDir) ? glob($musicDir . '/*.mp3') : [];
    if ($musicFiles) {
        shuffle($musicFiles);
        $musicUrl = $baseUrl . '/magic/' . ltrim(str_replace(__DIR__ . '/..', '', $musicFiles[0]), '/\\');
    }
}

// ── Output manifesto ─────────────────────────────────────────────────────────
$valleyName = $lang === 'en' ? $meta['name_en'] : $meta['name_it'];
$title      = strtoupper($valleyName);

$cta = $lang === 'en'
    ? ['head' => 'INDIE CARD GAME', 'desc' => 'Build a deck. Cross the Varco.', 'line1' => 'PLAY FREE', 'line2' => 'varco.portale3d.it']
    : ['head' => 'GIOCO DI CARTE INDIE', 'desc' => 'Costruisci un mazzo. Attraversa il Varco.', 'line1' => 'GIOCA GRATIS', 'line2' => 'varco.portale3d.it'];

$manifest = [
    'title'       => $title,
    'game_name'   => 'Varco',
    'output'      => 'varco_' . strtolower($valley) . '_' . date('Ymd') . '.mp4',
    'lang'        => $lang,
    'logo'        => $baseUrl . '/assets/bg/gate.png',
    'music'       => $musicUrl,
    'cta'         => $cta,
    'outro_image' => $baseUrl . '/assets/bg/campaign.jpg',
    'shots'       => $shots,
];

echo json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
