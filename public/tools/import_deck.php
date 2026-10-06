<?php
/**
 * Import Deck universal — importa e genera qualsiasi mazzo.
 * 
 * Accesso:
 *   GET  /?key=INSTALL_KEY&deck=red_cards          → dashboard per il mazzo
 *   POST /?key=INSTALL_KEY&deck=red_cards&action=import     → importa SQL
 *   POST /?key=INSTALL_KEY&deck=red_cards&action=generate   → genera immagini
 *   GET  /?key=INSTALL_KEY                         → lista tutti i mazzi disponibili
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

$method = $_SERVER['REQUEST_METHOD'];
$action = (string) ($_GET['action'] ?? $_POST['action'] ?? '');
$deckName = (string) ($_GET['deck'] ?? '');

// --- Helper: carica configurazione mazzo ---
function load_deck_config($name) {
    $file = __DIR__ . '/../../sql/' . preg_replace('/[^a-z0-9_-]/', '', $name) . '.json';
    if (!is_file($file)) {
        json_err("Mazzo non trovato: $name");
    }
    $config = json_decode(file_get_contents($file), true);
    if (!is_array($config)) {
        json_err("Config non valida: $name");
    }
    return $config;
}

// --- Helper: lista i mazzi disponibili ---
function list_available_decks() {
    $decks = [];
    $dir = __DIR__ . '/../../sql';
    foreach (glob("$dir/*.json") as $file) {
        $name = basename($file, '.json');
        if ($config = @json_decode(file_get_contents($file), true)) {
            $decks[] = [
                'id' => $name,
                'name' => $config['name'] ?? $name,
                'description' => $config['description'] ?? '',
                'color' => $config['color'] ?? '?',
            ];
        }
    }
    return $decks;
}

// --- GET: mostra dashboard ---
if ($method === 'GET' && $action === '') {
    // Se non c'è un deck specifico, mostra lista
    if ($deckName === '') {
        header('Content-Type: text/html; charset=utf-8');
        $decks = list_available_decks();
        ?>
        <!DOCTYPE html>
        <html lang="it">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Gestione Mazzi</title>
            <style>
                body { font-family: sans-serif; max-width: 1000px; margin: 40px auto; padding: 20px; background: #1a1a1a; color: #eee; }
                h1 { color: #4ecdc4; }
                .deck-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-top: 30px; }
                .deck-card { background: #222; border: 1px solid #444; padding: 20px; border-radius: 8px; cursor: pointer; transition: all 0.3s; }
                .deck-card:hover { background: #2a2a2a; border-color: #4ecdc4; }
                .deck-title { font-size: 18px; font-weight: bold; margin: 10px 0; color: #4ecdc4; }
                .deck-desc { font-size: 12px; color: #999; margin: 10px 0; }
                .deck-color { display: inline-block; width: 24px; height: 24px; border-radius: 50%; margin-right: 10px; vertical-align: middle; }
                .color-R { background: #ff6b6b; }
                .color-B { background: #333; border: 1px solid #666; }
                .color-G { background: #51cf66; }
                .color-U { background: #4ecdc4; }
                .color-W { background: #ffd93d; }
                a { color: #4ecdc4; text-decoration: none; }
                a:hover { text-decoration: underline; }
            </style>
        </head>
        <body>
            <h1>🎴 Gestione Mazzi Varco</h1>
            <p>Seleziona un mazzo per importare carte e generare immagini.</p>

            <div class="deck-grid">
                <?php foreach ($decks as $deck): ?>
                    <a href="?key=<?= urlencode($_GET['key'] ?? '') ?>&deck=<?= urlencode($deck['id']) ?>" style="text-decoration: none;">
                        <div class="deck-card">
                            <div>
                                <span class="deck-color color-<?= htmlspecialchars($deck['color']) ?>"></span>
                                <span style="font-weight: bold;"><?= htmlspecialchars($deck['color']) ?></span>
                            </div>
                            <div class="deck-title"><?= htmlspecialchars($deck['name']) ?></div>
                            <div class="deck-desc"><?= htmlspecialchars($deck['description']) ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if (empty($decks)): ?>
                <p style="color: #ff9800; margin-top: 40px;">Nessun mazzo disponibile. Crea un file .json in <code>sql/</code> per aggiungerne uno.</p>
            <?php endif; ?>
        </body>
        </html>
        <?php
        exit;
    }

    // Mostra dashboard per il mazzo specifico
    header('Content-Type: text/html; charset=utf-8');
    $config = load_deck_config($deckName);
    ?>
    <!DOCTYPE html>
    <html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($config['name']) ?></title>
        <style>
            body { font-family: sans-serif; max-width: 900px; margin: 40px auto; padding: 20px; background: #1a1a1a; color: #eee; }
            h1 { color: #4ecdc4; }
            .card { background: #222; border: 1px solid #444; padding: 20px; margin: 20px 0; border-radius: 8px; }
            .btn { display: inline-block; padding: 12px 24px; margin: 10px 10px 10px 0; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
            .btn-import { background: #ff6b6b; color: white; }
            .btn-generate { background: #4ecdc4; color: white; }
            .btn-back { background: #666; color: white; }
            .btn:hover { opacity: 0.9; }
            .status { margin-top: 20px; padding: 15px; border-radius: 4px; }
            .status.ok { background: #2d5016; border-left: 4px solid #4caf50; }
            .status.error { background: #5a1a1a; border-left: 4px solid #ff6b6b; }
            .stat { margin: 10px 0; }
            .stat strong { color: #4ecdc4; }
            code { background: #333; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
        </style>
    </head>
    <body>
        <a href="?key=<?= urlencode($_GET['key'] ?? '') ?>" class="btn btn-back">← Torna alla lista</a>

        <h1><?= htmlspecialchars($config['name']) ?></h1>
        <p><?= htmlspecialchars($config['description'] ?? '') ?></p>

        <div class="card">
            <h2>Step 1: Importa le carte nel database</h2>
            <p>File: <code><?= htmlspecialchars($config['sql_file']) ?></code></p>
            <form method="POST" style="margin-top: 20px;">
                <input type="hidden" name="action" value="import">
                <button type="submit" class="btn btn-import">Importa SQL</button>
            </form>
        </div>

        <div class="card">
            <h2>Step 2: Genera le immagini</h2>
            <p><?= count($config['prompts'] ?? []) ?> carte × 1 immagine ciascuna</p>
            <p style="color: #ff9800; font-size: 14px;">⚠️ Potrebbe richiedere 5-10 minuti. Mantieni la pagina aperta.</p>
            <form method="POST" style="margin-top: 20px;">
                <input type="hidden" name="action" value="generate">
                <button type="submit" class="btn btn-generate">Genera Immagini</button>
            </form>
        </div>

        <div class="card">
            <h2>Statistiche</h2>
            <?php
            try {
                $color = $config['color'] ?? '';
                if ($color) {
                    $stmt = db()->prepare('SELECT COUNT(*) as total FROM ' . TBL_CARDS . ' WHERE source = ? AND colors = ?');
                    $stmt->execute(['custom', $color]);
                    $row = $stmt->fetch();
                    $cardCount = $row['total'] ?? 0;
                    echo "<div class='stat'><strong>Carte importate:</strong> $cardCount</div>";
                }
            } catch (Exception $e) {
                echo "<div class='status error'>Errore: " . htmlspecialchars($e->getMessage()) . "</div>";
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

    if ($deckName === '') {
        json_err('Mazzo non specificato');
    }

    try {
        $config = load_deck_config($deckName);
        $sqlFile = __DIR__ . '/../../sql/' . basename($config['sql_file']);
        
        if (!is_file($sqlFile)) {
            json_err('File SQL non trovato: ' . $config['sql_file']);
        }

        $sql = file_get_contents($sqlFile);
        $pdo = db();
        
        $count = 0;
        foreach (explode(';', $sql) as $statement) {
            $statement = trim($statement);
            if ($statement === '') continue;
            $pdo->exec($statement . ';');
            $count++;
        }

        json_out(['ok' => true, 'message' => "✓ Importati $count statements", 'deck' => $deckName], 200);
    } catch (PDOException $e) {
        json_err('Errore SQL: ' . $e->getMessage());
    } catch (Throwable $e) {
        json_err('Errore: ' . $e->getMessage());
    }
}

// --- POST: generate images ---
if ($method === 'POST' && $action === 'generate') {
    header('Content-Type: application/json; charset=utf-8');

    if ($deckName === '') {
        json_err('Mazzo non specificato');
    }

    try {
        $config = load_deck_config($deckName);
        $prompts = $config['prompts'] ?? [];
        
        if (empty($prompts)) {
            json_err('Nessun prompt trovato nel mazzo');
        }

        $__loadHelios();

        // Carica le carte del mazzo dal database
        $color = $config['color'] ?? '';
        if (!$color) {
            json_err('Colore non definito nel mazzo');
        }

        $stmt = db()->prepare('SELECT id, name FROM ' . TBL_CARDS . ' WHERE source = ? AND colors = ? ORDER BY name');
        $stmt->execute(['custom', $color]);
        $cards = $stmt->fetchAll();

        if (empty($cards)) {
            json_err('Nessuna carta trovata. Importa prima il SQL.');
        }

        $OUT_REL = 'assets/cards';
        $OUT_ABS = __DIR__ . '/../' . $OUT_REL;
        if (!is_dir($OUT_ABS)) @mkdir($OUT_ABS, 0775, true);

        set_time_limit(600);

        $results = [];
        $generated = 0;
        $failed = 0;

        foreach ($cards as $card) {
            $cardName = $card['name'];
            $cardId = $card['id'];
            $prompt = $prompts[$cardName] ?? null;

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
                    $updateStmt = db()->prepare('UPDATE ' . TBL_CARDS . ' SET image_url = ? WHERE id = ?');
                    $updateStmt->execute([$webUrl, $cardId]);

                    $results[] = ['card' => $cardName, 'status' => 'ok', 'url' => $webUrl];
                    $generated++;
                } else {
                    $results[] = ['card' => $cardName, 'status' => 'fail', 'reason' => 'Immagine vuota'];
                    $failed++;
                }
            } catch (Throwable $e) {
                $results[] = ['card' => $cardName, 'status' => 'fail', 'reason' => substr($e->getMessage(), 0, 100)];
                $failed++;
            }
        }

        json_out(['ok' => true, 'deck' => $deckName, 'generated' => $generated, 'failed' => $failed, 'total' => count($results)], 200);
    } catch (Throwable $e) {
        json_err('Errore: ' . $e->getMessage());
    }
}

json_err('Azione non supportata', 405);
