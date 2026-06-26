<?php
/**
 * varco — generatore di SFONDI che RIUSA il motore immagini di Helios.
 *
 *   GET /tools/gen_bg.php?secret=XXX            → genera tutti gli sfondi mancanti
 *   GET /tools/gen_bg.php?secret=XXX&force=1    → rigenera anche quelli esistenti
 *   GET /tools/gen_bg.php?secret=XXX&only=arena → genera solo uno slug
 *
 * Auth: ?secret= confrontato con IMAGE_WORKER_SECRET del .env di root (~/.env),
 * lo stesso usato dal worker di Helios.
 *
 * Motore: helios/lib/image_gen.php (Cloudflare Workers AI → FLUX-1 schnell,
 * fallback HuggingFace). Nessuna dipendenza dal DB: chiamiamo image_gen_dispatch().
 *
 * Output: magic/public/assets/bg/<slug>.png  (web: /assets/bg/<slug>.png)
 *
 * Layout cartelle su OVH (siblings nella root SSH):
 *   ~/.env                      ~/helios/lib/image_gen.php
 *   ~/magic/public/tools/gen_bg.php (questo file)
 */
declare(strict_types=1);
ignore_user_abort(true);
header('Content-Type: application/json; charset=utf-8');

$ROOT_ENV     = __DIR__ . '/../../../.env';                       // ~/.env
$HELIOS_ENGINE= __DIR__ . '/../../../helios/lib/image_gen.php';   // ~/helios/lib/image_gen.php
$OUT_REL      = 'assets/bg';                                      // relativo a public/
$OUT_ABS      = __DIR__ . '/../' . $OUT_REL;                      // ~/magic/public/assets/bg

/* ---- carica .env di root (loader minimale, come lib/env.php di Helios) ---- */
if (!is_file($ROOT_ENV)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => ".env di root non trovato: $ROOT_ENV"]);
    exit;
}
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

/* ---- auth ---- */
$SECRET = (string) getenv('IMAGE_WORKER_SECRET');
$given  = (string) ($_GET['secret'] ?? $_SERVER['HTTP_X_WORKER_SECRET'] ?? '');
if ($SECRET === '' || !hash_equals($SECRET, $given)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}

/* ---- esporta le costanti che il motore si aspetta come define() ---- */
foreach (['IMAGE_PROVIDER','IMAGE_FALLBACKS','CF_ACCOUNT_ID','CF_API_TOKEN',
          'CF_IMAGE_MODEL','HF_API_TOKEN','HF_IMAGE_MODEL','HF_API_URL'] as $c) {
    $val = getenv($c);
    if ($val !== false && $val !== '' && !defined($c)) define($c, $val);
}
if (!is_file($HELIOS_ENGINE)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => "motore Helios non trovato: $HELIOS_ENGINE"]);
    exit;
}
require_once $HELIOS_ENGINE;

/* ---- art direction condivisa ---- */
$STYLE = ', dark fantasy occult concept art, painterly, deep indigo violet and antique gold palette, '
       . 'cyan and ember magical light accents, faint astrolabe and constellation geometry, '
       . 'cinematic atmospheric volumetric lighting, highly detailed, ultra dark vignette edges, '
       . 'no text, no watermark, no logo, no people, no characters, no creatures';

/* ---- sfondi da generare (slug => prompt). Aggiungere qui per la campagna. ---- */
$SCENES = [
    'void'  => 'A vast empty arcane night sky, deep indigo and violet cosmic nebula with golden '
             . 'star constellations and a faint glowing astrolabe ring, subtle stardust, abstract, '
             . 'dark and quiet, designed as a seamless website background',
    'arena' => 'An epic dark fantasy duelling ground split in two by a glowing dimensional rift '
             . '(a "varco") of cyan and violet light tearing across an ancient stone floor inlaid '
             . 'with gold filigree, drifting embers and arcane mist, seen in dramatic wide cinematic view',
    'gate'  => 'A colossal ancient arcane gateway standing in a starry void, an enormous stone arch '
             . 'carved with glowing golden runes and a swirling portal of cyan-violet energy at its '
             . 'center, mist at the base, epic dark fantasy splash art, cinematic key art',
];

$force = !empty($_GET['force']);
$only  = isset($_GET['only']) ? preg_replace('/[^a-z0-9_-]/', '', (string) $_GET['only']) : '';

if (!is_dir($OUT_ABS)) @mkdir($OUT_ABS, 0775, true);
set_time_limit(180);

$results = [];
foreach ($SCENES as $slug => $prompt) {
    if ($only !== '' && $slug !== $only) continue;

    $abs = $OUT_ABS . "/$slug.png";
    $web = "/$OUT_REL/$slug.png";
    if (!$force && is_file($abs) && filesize($abs) > 1024) {
        $results[] = ['slug' => $slug, 'status' => 'skip (esiste)', 'url' => $web];
        continue;
    }

    $job = [
        'prompt'      => $prompt . $STYLE,
        'kind'        => 'scenario_cover',
        'target_path' => "$OUT_REL/$slug.png",
        'ref_id'      => $slug,
        'game_id'     => 'varco',
        'scenario_id' => 'bg',
    ];

    $t0 = microtime(true);
    try {
        image_gen_dispatch($job, $abs);
        $results[] = [
            'slug'   => $slug,
            'status' => 'ok',
            'url'    => $web,
            'bytes'  => is_file($abs) ? filesize($abs) : 0,
            'sec'    => round(microtime(true) - $t0, 1),
        ];
    } catch (Throwable $e) {
        $results[] = ['slug' => $slug, 'status' => 'FAIL', 'error' => $e->getMessage()];
    }
}

echo json_encode([
    'ok'       => true,
    'provider' => defined('IMAGE_PROVIDER') ? IMAGE_PROVIDER : '?',
    'out'      => $OUT_REL,
    'results'  => $results,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
