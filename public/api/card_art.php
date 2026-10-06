<?php
/**
 * Genera l'illustrazione AI per una carta CUSTOM (mai per una carta scryfall:
 * quelle hanno già image_url puntato a Scryfall e non vanno toccate).
 *
 *   POST { id: "custom_xxxx" }  -> genera e salva l'arte, aggiorna image_url
 *
 * Riusa lo stesso motore/stile di tools/gen_bg.php (Cloudflare Workers AI ->
 * FLUX-1 schnell, fallback HuggingFace, fallback Pollinations — quest'ultimo
 * senza chiave API e senza limiti di quota, utile quando gli altri due sono
 * esauriti). Il prompt NON include mai il nome della carta (i modelli tendono
 * a "scriverlo" come testo storpiato nell'immagine): usa invece creatura/
 * colori/rarità + l'eventuale nota d'arte libera scritta dall'utente, messa
 * SUBITO in apertura del prompt (i modelli danno più peso a ciò che leggono
 * per primo). Le rare/mitiche ricevono un tocco di luce dorata per
 * distinguersi visivamente, mai testo.
 *
 * La nota d'arte (scritta in italiano dall'utente) viene tradotta in inglese
 * con Groq prima di entrare nel prompt: FLUX è addestrato quasi solo su
 * didascalie inglesi e segue le istruzioni composte molto meglio in quella
 * lingua (verificato: "cavaliere a piedi + tempio sullo sfondo" in italiano
 * perdeva il tempio, la stessa frase in inglese lo rendeva correttamente).
 */
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/http.php';
require_once __DIR__ . '/../../src/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_err('Solo POST', 405);
}

$in = read_input();
$id = trim((string) ($in['id'] ?? ''));
if ($id === '') {
    json_err('id mancante');
}

$stmt = db()->prepare('SELECT * FROM ' . TBL_CARDS . " WHERE id = ? AND source = 'custom'");
$stmt->execute([$id]);
$card = $stmt->fetch();
if (!$card) {
    json_err('Carta custom non trovata', 404);
}

/* ---- carica .env di root (stesso loader minimale di gen_bg.php) ---- */
$ROOT_ENV = __DIR__ . '/../../../.env';
if (!is_file($ROOT_ENV)) {
    json_err('.env di root non trovato', 500);
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
foreach (['IMAGE_PROVIDER', 'IMAGE_FALLBACKS', 'CF_ACCOUNT_ID', 'CF_API_TOKEN',
          'CF_IMAGE_MODEL', 'HF_API_TOKEN', 'HF_IMAGE_MODEL', 'HF_API_URL',
          'GROQ_API_URL', 'GROQ_API_KEY', 'GROQ_MODEL'] as $c) {
    $val = getenv($c);
    if ($val !== false && $val !== '' && !defined($c)) define($c, $val);
}
$HELIOS_ENGINE = __DIR__ . '/../../../helios/lib/image_gen.php';
if (!is_file($HELIOS_ENGINE)) {
    json_err('Motore immagini non trovato', 500);
}
require_once $HELIOS_ENGINE;

/**
 * Traduce una frase libera (qualunque lingua) in un breve prompt inglese per la
 * generazione immagini. Best-effort: se Groq non è configurato o fallisce, ritorna
 * il testo originale invariato (non blocca mai la generazione dell'arte).
 */
function translate_art_note(string $text): string
{
    if ($text === '') return $text;
    $groqFile = __DIR__ . '/../../../helios/lib/groq_v3.php';
    if (!defined('GROQ_API_KEY') || GROQ_API_KEY === '' || !is_file($groqFile)) {
        return $text;
    }
    try {
        require_once $groqFile;
        $client = new GroqToolClient(GROQ_API_KEY, defined('GROQ_MODEL') ? GROQ_MODEL : 'llama-3.1-8b-instant',
            defined('GROQ_API_URL') && GROQ_API_URL !== '' ? GROQ_API_URL : 'https://api.groq.com/openai/v1/chat/completions');
        $out = $client->complete(
            'You translate short art-direction phrases (any language) into concise, natural English, '
            . 'suitable as part of an AI image generation prompt. Output ONLY the English translation, '
            . 'no quotes, no preamble, no explanation. If already in English, output it lightly cleaned up.',
            $text,
            80
        );
        $out = trim($out, " \t\n\r\0\x0B\"'");
        return $out !== '' ? $out : $text;
    } catch (Throwable $e) {
        return $text; // traduzione best-effort: mai bloccare la generazione per questo
    }
}

/* ---- prompt costruito dagli attributi della carta (MAI dal nome) ---- */
const COLOR_MOOD = [
    'W' => 'ivory and gold tones',
    'U' => 'deep arcane blue tones',
    'B' => 'bone and shadow tones',
    'R' => 'ember red and iron tones',
    'G' => 'deep green and bark tones',
];
$colorsArr = str_split((string) $card['colors']);
$moods = array_map(fn($c) => COLOR_MOOD[$c] ?? '', $colorsArr);
$moodTxt = implode(', ', array_filter($moods)) ?: 'grey stone and silver tones';

$subtypes = trim((string) $card['subtypes']);
$creatureTxt = $subtypes !== '' ? $subtypes : 'fantasy creature';

/* Rarità: le rare/mitiche ricevono un tocco di luce dorata per distinguersi (mai testo). */
$rarityFlourish = match ($card['rarity']) {
    'mythic' => ', surrounded by intense radiant golden light rays and a glowing golden aura, epic legendary atmosphere',
    'rare'   => ', touched by soft radiant golden light rays and a subtle golden aura',
    default  => '',
};

/* Nota d'arte libera dell'utente: è il soggetto PRINCIPALE del prompt, va in apertura
   (i modelli seguono con più fedeltà ciò che leggono per primo) e viene tradotta in
   inglese (vedi commento in cima al file). Il tipo di creatura resta come contesto,
   il colore/rarità restano un tocco atmosferico secondario. */
$artNote = translate_art_note(trim((string) ($card['art_note'] ?? '')));
// niente ":" fra tipo e descrizione: il modello lo legge come un'etichetta da scrivere
// nell'immagine (visto succedere con "Soldier: ...") — una frase con virgola no.
$subjectTxt = $artNote !== '' ? "a {$creatureTxt}, {$artNote}" : "a single {$creatureTxt}";

$prompt = "{$subjectTxt}, {$moodTxt}{$rarityFlourish}, dark fantasy painterly illustration, "
    . "single centered subject, no text, no letters, no words, no writing, no watermark, "
    . "no logo, no signature, no title, no caption, no border, no frame";

$targetRel = 'assets/cards/' . $id . '.jpg';
$targetAbs = __DIR__ . '/../' . $targetRel;
$dir = dirname($targetAbs);
if (!is_dir($dir)) { mkdir($dir, 0775, true); }

$job = [
    'prompt'      => $prompt,
    'kind'        => 'custom_card',
    'target_path' => $targetRel,
    'ref_id'      => $id,
    'game_id'     => 'varco',
    'scenario_id' => 'card',
    'steps'       => 4,   // default ufficiale cloudflare per flux-1-schnell: ogni step costa 9,6 neuroni,
                          // quindi steps=8 (usato prima) costava 76,8 neuroni/immagine invece di 38,4 —
                          // quasi il doppio, senza un vantaggio di qualità verificato (schnell è distillato
                          // proprio per girare bene a poche step)
    'width'       => 768, // usati solo dal fallback pollinations (cloudflare/huggingface ritornano già 1024x1024 di loro);
    'height'      => 1024,// verticale come le mini-card (aspect-ratio 488/680), altrimenti l'immagine arriva
                          // orizzontale e viene tagliata parecchio da object-fit:cover.
];

try {
    image_gen_dispatch($job, $targetAbs);
} catch (Throwable $e) {
    json_err('Generazione immagine fallita: ' . $e->getMessage(), 502);
}

// Query string di versione: senza, l'URL resterebbe identico a ogni rigenerazione e il
// browser (o qualunque cache intermedia) continuerebbe a servire l'immagine vecchia anche
// dopo aver salvato il nuovo file con lo stesso nome — è esattamente il bug segnalato.
// Millisecondi (non time(), che ha risoluzione di 1s) per restare univoco anche fra due
// rigenerazioni ravvicinate della stessa carta.
$webUrl = $targetRel . '?v=' . round(microtime(true) * 1000);
$upd = db()->prepare('UPDATE ' . TBL_CARDS . ' SET image_url = ? WHERE id = ?');
$upd->execute([$webUrl, $id]);

json_out(['ok' => true, 'image_url' => $webUrl]);
