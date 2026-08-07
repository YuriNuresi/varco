<?php
/**
 * Script per generare le carte rosse (Diavoli, Draghi, Guerrieri):
 * 1. Inserisce le carte rosse nel database (da sql/red_cards.sql)
 * 2. Genera le immagini per ogni carta
 * 3. Aggiorna il database con gli URL delle immagini
 *
 * Uso:
 *   php scripts/generate_red_cards.php [--force] [--dry-run]
 *
 * --force:   Rigenera anche le carte che esistono già
 * --dry-run: Mostra le operazioni senza eseguirle
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/http.php';

// --- Parsing argomenti CLI ------
$force   = in_array('--force', $argv, true);
$dryRun  = in_array('--dry-run', $argv, true);

// --- Carica il motore Helios ----
$ROOT_ENV = __DIR__ . '/../../.env';
$HELIOS_ENGINE = __DIR__ . '/../../helios/lib/image_gen.php';

if (!is_file($ROOT_ENV)) {
    echo "ERRORE: .env di root non trovato: $ROOT_ENV\n";
    exit(1);
}
if (!is_file($HELIOS_ENGINE)) {
    echo "ERRORE: motore Helios non trovato: $HELIOS_ENGINE\n";
    exit(1);
}

// Carica .env
foreach (file($ROOT_ENV, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') continue;
    $eq = strpos($line, '='); if ($eq === false) continue;
    $k = trim(substr($line, 0, $eq));
    $v = trim(substr($line, $eq + 1));
    if (strlen($v) >= 2) {
        $f = $v[0]; $l = $v[strlen($v) - 1];
        if (($f === '"' && $l === '"') || ($f === "'" && $l === "'")) $v = substr($v, 1, -1);
    }
    if (getenv($k) === false) { putenv("$k=$v"); $_ENV[$k] = $v; }
}

// Carica le costanti che il motore si aspetta
foreach (['IMAGE_PROVIDER','IMAGE_FALLBACKS','CF_ACCOUNT_ID','CF_API_TOKEN',
          'CF_IMAGE_MODEL','HF_API_TOKEN','HF_IMAGE_MODEL','HF_API_URL'] as $c) {
    $val = getenv($c);
    if ($val !== false && $val !== '' && !defined($c)) define($c, $val);
}

require_once $HELIOS_ENGINE;

// --- Configurazione generazione immagini ---
$OUT_REL = 'assets/cards';
$OUT_ABS = __DIR__ . '/../public/' . $OUT_REL;

if (!is_dir($OUT_ABS)) {
    if (!$dryRun) @mkdir($OUT_ABS, 0775, true);
}

// --- Art direction per le carte (simile alle carte nere ma con accenti rossi) ---
$STYLE_DEVILS = ', dark fantasy occult concept art, painterly, deep red crimson and obsidian palette, '
              . 'amber and flame magical light accents, demonic sigils and hellfire geometry, '
              . 'cinematic atmospheric volumetric lighting, highly detailed, ultra dark vignette edges, '
              . 'no text, no watermark, no logo, no people, no characters';

$STYLE_DRAGONS = ', dark fantasy creature concept art, painterly, deep red and gold metallic palette, '
               . 'amber and ember magical light accents, draconic scales and celestial geometry, '
               . 'cinematic atmospheric volumetric lighting, highly detailed, ultra dark vignette edges, '
               . 'no text, no watermark, no logo, no people, no characters';

$STYLE_WARRIORS = ', dark fantasy warrior concept art, painterly, deep red and steel gray palette, '
                . 'golden and ember magical light accents, runic and heraldic geometry, '
                . 'cinematic atmospheric volumetric lighting, highly detailed, ultra dark vignette edges, '
                . 'no text, no watermark, no logo, no people, no characters';

// --- Prompts per ogni carta ----
$PROMPTS = [
    // DIAVOLI
    'Diavoletto Fiammeggiante'      => 'A small scarlet imp wreathed in flames, mischievous and menacing, demonic wings spread, sulfurous glow' . $STYLE_DEVILS,
    'Demone di Fuoco'               => 'A sinister fire demon with burning skin and blazing eyes, surrounded by black flames and infernal aura' . $STYLE_DEVILS,
    'Imp Rosso del Varco'           => 'A crimson winged imp materializing through a rift, eyes glowing with hellfire, twisted grin' . $STYLE_DEVILS,
    'Esecutore del Fuoco'           => 'A flame-wreathed demon executioner wielding a burning scythe, eyes burning like furnaces' . $STYLE_DEVILS,
    'Tormentatore Infernale'        => 'A bulky infernal demon with charred skin, wreathed in black smoke and embers, cruel visage' . $STYLE_DEVILS,
    'Diavolo Alato'                 => 'A winged devil silhouetted against hellfire, with massive charred wings and burning eyes' . $STYLE_DEVILS,
    'Arcidiavolo della Lava'        => 'A greater demon emerging from molten lava, skin cracking with internal heat, surrounded by magma flows' . $STYLE_DEVILS,
    'Diavolo delle Fiamme Nere'     => 'A mighty demon wreathed in black flames, obsidian horns and infernal chains' . $STYLE_DEVILS,
    'Signore dei Tormenti'          => 'A terrible demon lord with wings spread wide, surrounded by swirling infernal energy and despair' . $STYLE_DEVILS,
    'Devastatore Infernale'         => 'An enormous destruction demon with molten cracks in its body, trailing lava and destruction' . $STYLE_DEVILS,
    'Arcidemone del Fuoco'          => 'An archdevil wreathed in cosmic hellfire, with intricate demonic architecture and infernal power' . $STYLE_DEVILS,
    'Principe dell''Abisso di Fuoco'=> 'A demonic prince materializing from dimensional rift, commanding flames and shadow' . $STYLE_DEVILS,

    // DRAGHI
    'Drago Minore Rosso'            => 'A small red dragon with iridescent scales, breathing embers and smoke, curious and dangerous' . $STYLE_DRAGONS,
    'Drago del Varco'               => 'A crimson dragon emerging from a dimensional tear, scales shimmering with magical energy' . $STYLE_DRAGONS,
    'Piccolo Wyrm Fiammante'        => 'A young red wyvern with flame-touched wings, hovering above scorched earth' . $STYLE_DRAGONS,
    'Drago del Fuoco Selvaggio'     => 'A wild fire dragon mid-flight, breathing flames across a burning landscape' . $STYLE_DRAGONS,
    'Drago Accidia'                 => 'A massive serpentine dragon with molten ridges, coiled and watching with ancient intelligence' . $STYLE_DRAGONS,
    'Wyrm Tempesta Infuocata'       => 'A storm dragon wreathed in electrical fire, wings spread against raging skies' . $STYLE_DRAGONS,
    'Drago Reale della Lava'        => 'A noble dragon perched on a volcanic mountain, scales glowing with lava flows' . $STYLE_DRAGONS,
    'Drago Divoratore di Fuoco'     => 'A fearsome dragon mid-breath, unleashing an inferno from its maw' . $STYLE_DRAGONS,
    'Fenice Draconiana'             => 'A phoenix-like dragon with flaming wings, surrounded by embers and rebirth energy' . $STYLE_DRAGONS,
    'Drago Primordiale della Lava'  => 'An ancient primordial dragon, vast and terrible, surrounded by volcanic devastation' . $STYLE_DRAGONS,
    'Antico Drago Rosso'            => 'A massive ancient red dragon, wise and terrible, resting upon hoards of gold' . $STYLE_DRAGONS,
    'Signore dei Draghi Infuocati'  => 'A legendary dragon lord with ethereal flame crown, commanding the skies with absolute power' . $STYLE_DRAGONS,

    // GUERRIERI
    'Guerriero Rosso Ardente'       => 'A fierce warrior wrapped in flames, sword blazing, eyes burning with determination' . $STYLE_WARRIORS,
    'Soldato del Varco'             => 'A stalwart soldier standing at a dimensional rift, armor scarred from battle' . $STYLE_WARRIORS,
    'Arciere del Fuoco'             => 'An archer with flaming arrows, drawing bow with supernatural accuracy and grace' . $STYLE_WARRIORS,
    'Battitore di Asce'             => 'A powerful axe-wielder mid-swing, muscles tensed with brutal force and determination' . $STYLE_WARRIORS,
    'Mastino della Montagna di Fuoco'=> 'A massive warrior with scorched armor, standing defiant on burning mountain peaks' . $STYLE_WARRIORS,
    'Picchiere Ribastone'           => 'A spear-wielding soldier in defensive stance, bristling with martial prowess' . $STYLE_WARRIORS,
    'Signore di Guerra della Montagna'=> 'A legendary warlord with fiery aura, commanding the battlefield with presence' . $STYLE_WARRIORS,
    'Campione del Fuoco'            => 'A champion warrior surrounded by magical flames, radiant with power and victory' . $STYLE_WARRIORS,
    'Gigante della Roccia Incandescente'=> 'A towering giant warrior, stone-like skin glowing with internal heat' . $STYLE_WARRIORS,
    'Generale Vittorioso'           => 'A victorious general with regal bearing, surrounded by the aura of conquest' . $STYLE_WARRIORS,
    'Gran Maestro di Guerra'        => 'A grand master warrior transcendent with magical power, wielding ancient weapons' . $STYLE_WARRIORS,
    'Signore dei Guerrieri Infuocati'=> 'A legendary warrior lord with celestial flames, commanding armies of heroes' . $STYLE_WARRIORS,
];

// --- Funzione ausiliaria: genera slug da nome carta ---
function card_slug(string $name): string {
    return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-') ?: 'card';
}

// --- STEP 1: Carica il SQL e ottieni le carte ---
echo "=== Caricamento carte rosse ===\n";

$sqlFile = __DIR__ . '/../sql/red_cards.sql';
if (!is_file($sqlFile)) {
    echo "ERRORE: File SQL non trovato: $sqlFile\n";
    exit(1);
}

// Leggi il file SQL e estrai i nomi delle carte
$sqlContent = file_get_contents($sqlFile);
preg_match_all("/VALUES\s*\(\s*HEX\(RANDOM_BYTES\(16\)\),\s*'([^']+)',/", $sqlContent, $matches);
$cardNames = $matches[1] ?? [];

echo "Trovate " . count($cardNames) . " carte nel SQL\n";

// --- STEP 2: Inserisci le carte nel database (se non esistono) ---
echo "\n=== Inserimento carte nel database ===\n";

if (!$dryRun) {
    try {
        // Esegui il file SQL
        $sql = $sqlContent;
        $pdo = db();
        $pdo->exec($sql);
        echo "✓ Carte inserite nel database\n";
    } catch (PDOException $e) {
        echo "Errore nell'inserimento: " . $e->getMessage() . "\n";
        // Continua comunque, potrebbe essere un duplicato
    }
}

// --- STEP 3: Genera immagini per ogni carta ---
echo "\n=== Generazione immagini ===\n";
set_time_limit(600); // 10 minuti per generare tutte le immagini

$results = [];
$generated = 0;
$skipped = 0;
$failed = 0;

foreach ($cardNames as $cardName) {
    $prompt = $PROMPTS[$cardName] ?? null;
    if (!$prompt) {
        echo "⚠ Nessun prompt per: $cardName\n";
        $skipped++;
        continue;
    }

    $slug = card_slug($cardName);
    $filename = 'card_' . $slug . '_' . bin2hex(random_bytes(4)) . '.png';
    $relPath = $OUT_REL . '/' . $filename;
    $absPath = __DIR__ . '/../public/' . $relPath;

    // Salta se esiste già
    if (!$force && is_file($absPath) && filesize($absPath) > 1024) {
        echo "⊘ $cardName (esiste già)\n";
        $skipped++;
        continue;
    }

    if ($dryRun) {
        echo "→ $cardName (generarebbe: /$relPath)\n";
        $generated++;
        continue;
    }

    echo "◌ $cardName ... ";
    $t0 = microtime(true);

    try {
        $job = [
            'prompt'      => $prompt,
            'kind'        => 'card_art',
            'target_path' => $relPath,
            'ref_id'      => $slug,
            'game_id'     => 'varco',
            'scenario_id' => 'card-editor',
            'width'       => 512,
            'height'      => 768,
        ];

        image_gen_dispatch($job, $absPath);

        if (is_file($absPath) && filesize($absPath) > 1024) {
            $sec = round(microtime(true) - $t0, 1);
            $webUrl = '/' . $relPath;
            echo "✓ OK ({$sec}s, " . round(filesize($absPath) / 1024, 1) . "KB)\n";
            $results[$cardName] = $webUrl;
            $generated++;
        } else {
            echo "✗ FAIL (immagine vuota)\n";
            $failed++;
        }
    } catch (Throwable $e) {
        echo "✗ FAIL (" . substr($e->getMessage(), 0, 60) . ")\n";
        $failed++;
    }
}

// --- STEP 4: Aggiorna i database con gli URL delle immagini ---
if (!$dryRun && !empty($results)) {
    echo "\n=== Aggiornamento database con URL immagini ===\n";

    try {
        $pdo = db();
        $stmt = $pdo->prepare('UPDATE ' . TBL_CARDS . ' SET image_url = ? WHERE name = ? AND source = \'custom\'');

        foreach ($results as $cardName => $imageUrl) {
            $stmt->execute([$imageUrl, $cardName]);
            if ($stmt->rowCount() > 0) {
                echo "✓ $cardName → $imageUrl\n";
            } else {
                echo "⚠ $cardName non trovata nel DB\n";
            }
        }
    } catch (PDOException $e) {
        echo "Errore nell'aggiornamento: " . $e->getMessage() . "\n";
    }
}

// --- SUMMARY ---
echo "\n=== Riepilogo ===\n";
echo "Generate: $generated\n";
echo "Saltate:  $skipped\n";
echo "Errori:   $failed\n";
if ($dryRun) echo "MODALITÀ DRY-RUN: nessun cambiamento reale\n";

echo "\nCompleto!\n";
