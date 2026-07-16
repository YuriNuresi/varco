<?php
/**
 * Import Scryfall -> seed.sql  (ESEGUIRE IN LOCALE, non su OVH)
 *
 *   php scripts/import_scryfall.php
 *
 * IMPORT MASSIVO (scelta 2026-06-24): includiamo TUTTE le creature cartacee con P/T intero,
 * senza filtrare per testo o keyword. Il motore usa solo P/T + le 4 keyword che sa applicare
 * (Flying/First strike/Deathtouch/Trample), parse-ate dall'array `keywords`; le altre abilità
 * stampate vengono IGNORATE dal gioco e in UI il testo della carta è coperto dalle nostre icone.
 * Escludiamo: layout strani (DFC/split/…), funny set, e carte solo-digitali (Arena/alchemy) per
 * evitare i doppioni "A-…". Risultato atteso: ~17.5k creature.
 *
 * Educato coi rate limit: un solo download del bulk.
 */

declare(strict_types=1);

// Il bulk è ~170MB; il decode in array PHP richiede parecchia memoria. Script locale: alziamo.
ini_set('memory_limit', '3072M');

const OUT_FILE         = __DIR__ . '/../seed.sql';
const CACHE_FILE       = __DIR__ . '/oracle_cards.json'; // cache locale per non riscaricare

$USER_AGENT = 'MagicFanMVP/1.0 (local import; non-commercial)';

function http_get(string $url, string $userAgent): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => $userAgent,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        CURLOPT_TIMEOUT        => 600,
        // Script LOCALE: il PHP di Windows spesso non ha un CA bundle configurato.
        // Disabilitiamo la verifica SSL SOLO qui (download da API pubblica Scryfall).
        // NON replicare questo comportamento nel codice server.
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);
    $body = curl_exec($ch);
    if ($body === false) {
        fwrite(STDERR, 'Errore curl: ' . curl_error($ch) . "\n");
        exit(1);
    }
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($code >= 400) {
        fwrite(STDERR, "HTTP $code su $url\n");
        exit(1);
    }
    return (string) $body;
}

/** Scarica un URL grande direttamente su file (streaming), senza tenerlo in RAM. */
function http_download(string $url, string $dest, string $userAgent): void
{
    $fp = fopen($dest, 'wb');
    if ($fp === false) {
        fwrite(STDERR, "Impossibile aprire $dest in scrittura\n");
        exit(1);
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FILE           => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => $userAgent,
        CURLOPT_TIMEOUT        => 1200,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);
    $ok   = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    fclose($fp);
    if ($ok === false || $code >= 400) {
        fwrite(STDERR, "Download fallito (HTTP $code) $err\n");
        exit(1);
    }
}

// --- 1. Trova l'URL del bulk "oracle_cards" ----------------------------------
echo "[1/4] Recupero metadati bulk-data...\n";
$bulkMeta = json_decode(http_get('https://api.scryfall.com/bulk-data', $USER_AGENT), true);
$downloadUri = null;
foreach ($bulkMeta['data'] ?? [] as $entry) {
    if (($entry['type'] ?? '') === 'oracle_cards') {
        $downloadUri = $entry['download_uri'];
        break;
    }
}
if ($downloadUri === null) {
    fwrite(STDERR, "Impossibile trovare il bulk 'oracle_cards'.\n");
    exit(1);
}

// --- 2. Scarica (o usa cache) ------------------------------------------------
if (is_file(CACHE_FILE) && filesize(CACHE_FILE) > 1_000_000) {
    echo "[2/4] Uso cache locale: " . CACHE_FILE . "\n";
} else {
    echo "[2/4] Scarico il bulk (~150MB) da:\n      $downloadUri\n      (un download solo, abbi pazienza)...\n";
    http_download($downloadUri, CACHE_FILE, $USER_AGENT);
    echo "      Salvato in cache: " . CACHE_FILE . "\n";
}

$cards = json_decode((string) file_get_contents(CACHE_FILE), true);
if (!is_array($cards)) {
    fwrite(STDERR, "JSON bulk non valido.\n");
    exit(1);
}
echo "      Carte totali nel bulk: " . count($cards) . "\n";

// --- 3. Filtro creature (massivo) --------------------------------------------
echo "[3/4] Seleziono TUTTE le creature cartacee con P/T intero...\n";

$BANNED_LAYOUTS = ['token', 'double_faced_token', 'emblem', 'art_series', 'scheme', 'planar', 'vanguard', 'meld', 'modal_dfc', 'transform', 'flip', 'split', 'adventure', 'class', 'saga', 'leveler'];

$valid   = [];
$seen    = [];
$skipped = 0;

foreach ($cards as $card) {
    $typeLine = $card['type_line'] ?? '';
    $layout   = $card['layout'] ?? 'normal';

    if (strpos($typeLine, 'Creature') === false) { $skipped++; continue; }
    if (in_array($layout, $BANNED_LAYOUTS, true)) { $skipped++; continue; }
    if (($card['set_type'] ?? '') === 'funny' || !empty($card['funny'])) { $skipped++; continue; }
    if (!empty($card['digital'])) { $skipped++; continue; }                 // niente Arena/alchemy digitali ("A-…")
    if (!in_array('paper', $card['games'] ?? [], true)) { $skipped++; continue; } // solo carte cartacee reali

    // P/T interi (scarta */* e simili)
    $power     = $card['power'] ?? null;
    $toughness = $card['toughness'] ?? null;
    if (!is_string($power) || !preg_match('/^\d+$/', $power)) { $skipped++; continue; }
    if (!is_string($toughness) || !preg_match('/^\d+$/', $toughness)) { $skipped++; continue; }

    $oracleId = $card['oracle_id'] ?? null;
    if ($oracleId === null || isset($seen[$oracleId])) { $skipped++; continue; }
    $seen[$oracleId] = true;

    // Sottotipi di creatura: la parte dopo "—" in type_line (es. "Creature — Goblin Warrior" -> "Goblin,Warrior").
    // Serve ai villaggi tematici della campagna (mazzi nemici di una sola tribù). Memorizzati comma-separated
    // per FIND_IN_SET. Per le carte split prendiamo solo il primo lato (il motore tratta la carta come unica).
    $subtypes = '';
    if (($dash = strpos($typeLine, '—')) !== false) {
        $tail = substr($typeLine, $dash + strlen('—'));
        if (($slash = strpos($tail, '//')) !== false) { $tail = substr($tail, 0, $slash); }
        $parts = preg_split('/\s+/', trim($tail), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $subtypes = implode(',', $parts);
    }

    // Parse-iamo SOLO le 4 keyword che il motore sa applicare; il resto del testo è ignorato (e coperto in UI).
    $kw = $card['keywords'] ?? [];
    $hasKw = static function (string $needle) use ($kw): int {
        foreach ($kw as $k) {
            if (strcasecmp($k, $needle) === 0) { return 1; }
        }
        return 0;
    };

    $valid[] = [
        'id'           => $oracleId,
        'name'         => (string) ($card['name'] ?? ''),
        'mana_value'   => (int) round((float) ($card['cmc'] ?? 0)),
        'colors'       => implode('', $card['colors'] ?? []),
        'power'        => (int) $power,
        'toughness'    => (int) $toughness,
        'flying'        => $hasKw('Flying'),
        'first_strike'  => $hasKw('First strike'),
        'deathtouch'    => $hasKw('Deathtouch'),
        'trample'       => $hasKw('Trample'),
        'double_strike' => $hasKw('Double strike'),
        'lifelink'      => $hasKw('Lifelink'),
        'reach'         => $hasKw('Reach'),
        'defender'      => $hasKw('Defender'),
        'rarity'       => in_array(($card['rarity'] ?? 'common'), ['common','uncommon','rare','mythic'], true)
                            ? $card['rarity'] : 'common',
        'image_url'    => $card['image_uris']['normal'] ?? null,
        'subtypes'     => $subtypes,
    ];
}

echo "      Creature valide: " . count($valid) . " (scartate: $skipped)\n";

if (count($valid) === 0) {
    fwrite(STDERR, "Nessuna carta valida: controlla il filtro.\n");
    exit(1);
}

// --- 4. Genera seed.sql ------------------------------------------------------
echo "[4/4] Scrivo seed.sql...\n";

function sqlStr(?string $s): string
{
    if ($s === null) {
        return 'NULL';
    }
    return "'" . str_replace(["\\", "'"], ["\\\\", "''"], $s) . "'";
}

$schema = <<<SQL
-- Generato da scripts/import_scryfall.php
-- Importare in MySQL (es. phpMyAdmin su OVH).
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS magic_cards (
  id           VARCHAR(40)  PRIMARY KEY,
  name         VARCHAR(255) NOT NULL,
  mana_value   TINYINT UNSIGNED NOT NULL,
  colors       VARCHAR(10)  NOT NULL DEFAULT '',
  power        TINYINT UNSIGNED NOT NULL,
  toughness    TINYINT UNSIGNED NOT NULL,
  flying        TINYINT(1)  NOT NULL DEFAULT 0,
  first_strike  TINYINT(1)  NOT NULL DEFAULT 0,
  deathtouch    TINYINT(1)  NOT NULL DEFAULT 0,
  trample       TINYINT(1)  NOT NULL DEFAULT 0,
  double_strike TINYINT(1)  NOT NULL DEFAULT 0,
  lifelink      TINYINT(1)  NOT NULL DEFAULT 0,
  reach         TINYINT(1)  NOT NULL DEFAULT 0,
  defender      TINYINT(1)  NOT NULL DEFAULT 0,
  rarity       VARCHAR(12)  NOT NULL DEFAULT 'common',
  subtypes     VARCHAR(255) NOT NULL DEFAULT '',
  image_url    VARCHAR(255) DEFAULT NULL,
  enabled      TINYINT(1)   NOT NULL DEFAULT 1,
  INDEX (enabled), INDEX (mana_value), INDEX (colors), INDEX (rarity)
);

CREATE TABLE IF NOT EXISTS magic_decks (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(100) NOT NULL,
  color      CHAR(1)      NOT NULL,
  card_ids   JSON         NOT NULL,
  created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

TRUNCATE TABLE magic_cards;


SQL;

$fh = fopen(OUT_FILE, 'w');
fwrite($fh, $schema);

$cols  = '(id,name,mana_value,colors,power,toughness,flying,first_strike,deathtouch,trample,double_strike,lifelink,reach,defender,rarity,subtypes,image_url,enabled)';
$batch = [];
$flushBatch = static function () use (&$batch, $fh, $cols): void {
    if (!$batch) {
        return;
    }
    fwrite($fh, "INSERT INTO magic_cards $cols VALUES\n" . implode(",\n", $batch) . ";\n");
    $batch = [];
};

foreach ($valid as $i => $v) {
    // TINYINT UNSIGNED max 255: tronca P/T mostruosi per non rompere l'insert.
    $power     = min(255, max(0, $v['power']));
    $toughness = min(255, max(0, $v['toughness']));
    $mana      = min(255, max(0, $v['mana_value']));
    $batch[] = sprintf(
        '(%s,%s,%d,%s,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%s,%s,%s,1)',
        sqlStr($v['id']),
        sqlStr($v['name']),
        $mana,
        sqlStr($v['colors']),
        $power,
        $toughness,
        $v['flying'],
        $v['first_strike'],
        $v['deathtouch'],
        $v['trample'],
        $v['double_strike'],
        $v['lifelink'],
        $v['reach'],
        $v['defender'],
        sqlStr($v['rarity']),
        sqlStr($v['subtypes']),
        sqlStr($v['image_url'])
    );
    if (count($batch) >= 200) {
        $flushBatch();
    }
}
$flushBatch();
fclose($fh);

echo "Fatto: " . OUT_FILE . " (" . count($valid) . " carte)\n";
