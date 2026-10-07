<?php
/**
 * Helper per importare e generare carte rosse su OVH.
 * 
 * Accesso:
 *   GET  /?key=INSTALL_KEY          → dashboard con istruzioni
 *   POST /?action=import             → importa SQL nel database
 *   POST /?action=generate_images    → genera immagini per tutte le carte rosse
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/http.php';

// --- Auth ----
$key = $_GET['key'] ?? $_SERVER['HTTP_X_ADMIN_KEY'] ?? '';
if ($key !== INSTALL_KEY && !is_admin()) {
    json_err('Accesso riservato (key= o header X-Admin-Key)', 403);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = (string) ($_GET['action'] ?? $_POST['action'] ?? '');

// --- Helper: carica .env e motore Helios ---
$__loadHelios = function () {
    static $loaded = false;
    if ($loaded) return;
    $loaded = true;

    $ROOT_ENV      = __DIR__ . '/../../../.env';
    $HELIOS_ENGINE = __DIR__ . '/../../../helios/lib/image_gen.php';
    if (!is_file($ROOT_ENV)) json_err('.env di root non trovato');
    if (!is_file($HELIOS_ENGINE)) json_err('Motore Helios non trovato');

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

    foreach (['IMAGE_PROVIDER','IMAGE_FALLBACKS','CF_ACCOUNT_ID','CF_API_TOKEN',
              'CF_IMAGE_MODEL','HF_API_TOKEN','HF_IMAGE_MODEL','HF_API_URL'] as $c) {
        $val = getenv($c);
        if ($val !== false && $val !== '' && !defined($c)) define($c, $val);
    }

    require_once $HELIOS_ENGINE;
};

// --- Prompts per ogni carta ----
$PROMPTS = [
    'Diavoletto Fiammeggiante'      => 'A small scarlet imp wreathed in flames, mischievous and menacing, demonic wings spread, sulfurous glow, dark fantasy occult concept art',
    'Demone di Fuoco'               => 'A sinister fire demon with burning skin and blazing eyes, surrounded by black flames and infernal aura, dark fantasy occult concept art',
    'Imp Rosso del Varco'           => 'A crimson winged imp materializing through a rift, eyes glowing with hellfire, twisted grin, dark fantasy occult concept art',
    'Esecutore del Fuoco'           => 'A flame-wreathed demon executioner wielding a burning scythe, eyes burning like furnaces, dark fantasy occult concept art',
    'Tormentatore Infernale'        => 'A bulky infernal demon with charred skin, wreathed in black smoke and embers, cruel visage, dark fantasy occult concept art',
    'Diavolo Alato'                 => 'A winged devil silhouetted against hellfire, with massive charred wings and burning eyes, dark fantasy occult concept art',
    'Arcidiavolo della Lava'        => 'A greater demon emerging from molten lava, skin cracking with internal heat, surrounded by magma flows, dark fantasy occult concept art',
    'Diavolo delle Fiamme Nere'     => 'A mighty demon wreathed in black flames, obsidian horns and infernal chains, dark fantasy occult concept art',
    'Signore dei Tormenti'          => 'A terrible demon lord with wings spread wide, surrounded by swirling infernal energy and despair, dark fantasy occult concept art',
    'Devastatore Infernale'         => 'An enormous destruction demon with molten cracks in its body, trailing lava and destruction, dark fantasy occult concept art',
    'Arcidemone del Fuoco'          => 'An archdevil wreathed in cosmic hellfire, with intricate demonic architecture and infernal power, dark fantasy occult concept art',
    "Principe dell'Abisso di Fuoco"=> 'A demonic prince materializing from dimensional rift, commanding flames and shadow, dark fantasy occult concept art',
    'Drago Minore Rosso'            => 'A small red dragon with iridescent scales, breathing embers and smoke, curious and dangerous, dark fantasy creature concept art',
    'Drago del Varco'               => 'A crimson dragon emerging from a dimensional tear, scales shimmering with magical energy, dark fantasy creature concept art',
    'Piccolo Wyrm Fiammante'        => 'A young red wyvern with flame-touched wings, hovering above scorched earth, dark fantasy creature concept art',
    'Drago del Fuoco Selvaggio'     => 'A wild fire dragon mid-flight, breathing flames across a burning landscape, dark fantasy creature concept art',
    'Drago Accidia'                 => 'A massive serpentine dragon with molten ridges, coiled and watching with ancient intelligence, dark fantasy creature concept art',
    'Wyrm Tempesta Infuocata'       => 'A storm dragon wreathed in electrical fire, wings spread against raging skies, dark fantasy creature concept art',
    'Drago Reale della Lava'        => 'A noble dragon perched on a volcanic mountain, scales glowing with lava flows, dark fantasy creature concept art',
    'Drago Divoratore di Fuoco'     => 'A fearsome dragon mid-breath, unleashing an inferno from its maw, dark fantasy creature concept art',
    'Fenice Draconiana'             => 'A phoenix-like dragon with flaming wings, surrounded by embers and rebirth energy, dark fantasy creature concept art',
    'Drago Primordiale della Lava'  => 'An ancient primordial dragon, vast and terrible, surrounded by volcanic devastation, dark fantasy creature concept art',
    'Antico Drago Rosso'            => 'A massive ancient red dragon, wise and terrible, resting upon hoards of gold, dark fantasy creature concept art',
    'Signore dei Draghi Infuocati'  => 'A legendary dragon lord with ethereal flame crown, commanding the skies with absolute power, dark fantasy creature concept art',
    'Guerriero Rosso Ardente'       => 'A fierce warrior wrapped in flames, sword blazing, eyes burning with determination, dark fantasy warrior concept art',
    'Soldato del Varco'             => 'A stalwart soldier standing at a dimensional rift, armor scarred from battle, dark fantasy warrior concept art',
    'Arciere del Fuoco'             => 'An archer with flaming arrows, drawing bow with supernatural accuracy and grace, dark fantasy warrior concept art',
    'Battitore di Asce'             => 'A powerful axe-wielder mid-swing, muscles tensed with brutal force and determination, dark fantasy warrior concept art',
    'Mastino della Montagna di Fuoco'=> 'A massive warrior with scorched armor, standing defiant on burning mountain peaks, dark fantasy warrior concept art',
    'Picchiere Ribastone'           => 'A spear-wielding soldier in defensive stance, bristling with martial prowess, dark fantasy warrior concept art',
    'Signore di Guerra della Montagna'=> 'A legendary warlord with fiery aura, commanding the battlefield with presence, dark fantasy warrior concept art',
    'Campione del Fuoco'            => 'A champion warrior surrounded by magical flames, radiant with power and victory, dark fantasy warrior concept art',
    'Gigante della Roccia Incandescente'=> 'A towering giant warrior, stone-like skin glowing with internal heat, dark fantasy warrior concept art',
    'Generale Vittorioso'           => 'A victorious general with regal bearing, surrounded by the aura of conquest, dark fantasy warrior concept art',
    'Gran Maestro di Guerra'        => 'A grand master warrior transcendent with magical power, wielding ancient weapons, dark fantasy warrior concept art',
    'Signore dei Guerrieri Infuocati'=> 'A legendary warrior lord with celestial flames, commanding armies of heroes, dark fantasy warrior concept art',
];

// --- GET: mostra interfaccia ---
if ($method === 'GET' && $action === '') {
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Setup Carte Rosse</title>
        <style>
            body { font-family: sans-serif; max-width: 900px; margin: 40px auto; padding: 20px; background: #1a1a1a; color: #eee; }
            h1 { color: #ff6b6b; }
            .card { background: #222; border: 1px solid #444; padding: 20px; margin: 20px 0; border-radius: 8px; }
            .btn { display: inline-block; padding: 12px 24px; margin: 10px 10px 10px 0; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
            .btn-import { background: #ff6b6b; color: white; }
            .btn-generate { background: #4ecdc4; color: white; }
            .btn:hover { opacity: 0.9; }
            .status { margin-top: 20px; padding: 15px; border-radius: 4px; }
            .status.ok { background: #2d5016; border-left: 4px solid #4caf50; }
            .status.error { background: #5a1a1a; border-left: 4px solid #ff6b6b; }
            .status.info { background: #1a3a5a; border-left: 4px solid #4ecdc4; }
            code { background: #333; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
            .stat { margin: 10px 0; }
            .stat strong { color: #4ecdc4; }
        </style>
    </head>
    <body>
        <h1>🔴 Setup Carte Rosse (Diavoli, Draghi, Guerrieri)</h1>

        <div class="card">
            <h2>Step 1: Importa le carte nel database</h2>
            <p>36 carte rosse (12 per tipo) con schema identico alle carte nere.</p>
            <form method="POST" style="margin-top: 20px;">
                <input type="hidden" name="action" value="import">
                <button type="submit" class="btn btn-import">Importa SQL</button>
            </form>
            <p style="font-size: 12px; color: #999; margin-top: 10px;">File: <code>sql/red_cards.sql</code></p>
        </div>

        <div class="card">
            <h2>Step 2: Genera le immagini</h2>
            <p>Genera le immagini per tutte le 36 carte usando il motore Helios.</p>
            <p style="color: #ff9800; font-size: 14px;">⚠️ Questo potrebbe richiedere 5-10 minuti. Mantieni la pagina aperta.</p>
            <form method="POST" style="margin-top: 20px;">
                <input type="hidden" name="action" value="generate_images">
                <button type="submit" class="btn btn-generate">Genera Immagini</button>
            </form>
        </div>

        <div class="card">
            <h2>Statistiche attuali</h2>
            <?php
            try {
                $stmt = db()->prepare('SELECT COUNT(*) as total FROM ' . TBL_CARDS . ' WHERE source = ? AND colors = ?');
                $stmt->execute(['custom', 'R']);
                $row = $stmt->fetch();
                $redCards = $row['total'] ?? 0;

                $stmt = db()->prepare('SELECT COUNT(*) as total FROM ' . TBL_CARDS . ' WHERE source = ? AND colors LIKE ?');
                $stmt->execute(['custom', 'B%']);
                $row = $stmt->fetch();
                $blackCards = $row['total'] ?? 0;

                echo "<div class='stat'><strong>Carte rosse custom:</strong> $redCards / 36</div>";
                echo "<div class='stat'><strong>Carte nere custom:</strong> $blackCards</div>";
            } catch (Exception $e) {
                echo "<div class='status error'>Errore nel conteggio: " . $e->getMessage() . "</div>";
            }
            ?>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// --- POST: import SQL ---
if ($method === 'POST' && $action === 'import') {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $sqlFile = __DIR__ . '/../../sql/red_cards.sql';
        if (!is_file($sqlFile)) {
            json_err('File SQL non trovato: ' . $sqlFile);
        }

        $sql = file_get_contents($sqlFile);
        $pdo = db();
        
        // Esegui ogni statement separatamente
        $count = 0;
        foreach (explode(';', $sql) as $statement) {
            $statement = trim($statement);
            if ($statement === '') continue;
            $pdo->exec($statement . ';');
            $count++;
        }

        json_out(['ok' => true, 'message' => "✓ Importati $count statements dal SQL", 'statements' => $count], 200);
    } catch (PDOException $e) {
        json_err('Errore SQL: ' . $e->getMessage());
    } catch (Throwable $e) {
        json_err('Errore: ' . $e->getMessage());
    }
}

// --- POST: generate images ---
if ($method === 'POST' && $action === 'generate_images') {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $__loadHelios();

        // Carica le carte rosse dal database
        $stmt = db()->prepare('SELECT id, name FROM ' . TBL_CARDS . ' WHERE source = ? AND colors = ? ORDER BY name');
        $stmt->execute(['custom', 'R']);
        $redCards = $stmt->fetchAll();

        if (empty($redCards)) {
            json_err('Nessuna carta rossa trovata. Importa prima il SQL.');
        }

        $OUT_REL = 'assets/cards';
        $OUT_ABS = __DIR__ . '/../' . $OUT_REL;
        if (!is_dir($OUT_ABS)) @mkdir($OUT_ABS, 0775, true);

        set_time_limit(600);

        $results = [];
        $generated = 0;
        $failed = 0;

        foreach ($redCards as $card) {
            $cardName = $card['name'];
            $cardId = $card['id'];
            $prompt = $PROMPTS[$cardName] ?? null;

            if (!$prompt) {
                $results[] = ['card' => $cardName, 'status' => 'skip', 'reason' => 'Nessun prompt'];
                continue;
            }

            $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($cardName));
            $slug = trim($slug, '-') ?: 'card';
            $filename = 'card_' . $slug . '_' . bin2hex(random_bytes(4)) . '.png';
            $relPath = $OUT_REL . '/' . $filename;
            $absPath = __DIR__ . '/../' . $relPath;

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
                    $webUrl = $relPath;
                    
                    // Aggiorna database con image_url
                    $updateStmt = db()->prepare('UPDATE ' . TBL_CARDS . ' SET image_url = ? WHERE id = ?');
                    $updateStmt->execute([$webUrl, $cardId]);

                    $results[] = ['card' => $cardName, 'status' => 'ok', 'url' => $webUrl, 'size' => round(filesize($absPath) / 1024, 1) . 'KB'];
                    $generated++;
                } else {
                    $results[] = ['card' => $cardName, 'status' => 'fail', 'reason' => 'Immagine vuota'];
                    $failed++;
                }
            } catch (Throwable $e) {
                $results[] = ['card' => $cardName, 'status' => 'fail', 'reason' => $e->getMessage()];
                $failed++;
            }
        }

        json_out(['ok' => true, 'generated' => $generated, 'failed' => $failed, 'results' => $results], 200);
    } catch (Throwable $e) {
        json_err('Errore: ' . $e->getMessage());
    }
}

json_err('Azione non supportata', 405);
