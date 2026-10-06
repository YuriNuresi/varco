<?php
/**
 * Configurazione globale: credenziali DB + costanti di gioco.
 *
 * In locale puoi sovrascrivere le credenziali con un file .env (vedi .env.example)
 * oppure modificare direttamente i valori placeholder qui sotto prima del deploy su OVH.
 */

declare(strict_types=1);

// Nasconde la versione PHP negli header HTTP (in coppia con l'unset in public/.htaccess)
if (!headers_sent()) {
    header_remove('X-Powered-By');
}

// --- Caricamento .env opzionale (solo per comodità in locale) -----------------
(function (): void {
    // Carica prima il .env di root (condiviso con gli altri giochi su OVH), poi quello locale.
    // Le chiavi del .env locale hanno la precedenza.
    $envFiles = [__DIR__ . '/../.env', __DIR__ . '/.env'];
    foreach ($envFiles as $envFile) {
    if (!is_file($envFile)) {
        continue;
    }
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
        $k = trim($k);
        $v = trim($v, " \t\"'");
        if ($k !== '' && getenv($k) === false) {
            putenv("$k=$v");
            $_ENV[$k] = $v;
        }
    }
    } // end foreach $envFiles
})();

function env(string $key, ?string $default = null): ?string
{
    $v = getenv($key);
    return $v === false ? $default : $v;
}

// --- Credenziali DB (PLACEHOLDER: compilare prima del deploy su OVH) ----------
define('CFG_DB_HOST', env('DB_HOST', 'localhost'));
define('CFG_DB_NAME', env('DB_NAME', 'magic_game'));
define('CFG_DB_USER', env('DB_USER', 'root'));
define('CFG_DB_PASS', env('DB_PASS', ''));
define('CFG_DB_PORT', (int) env('DB_PORT', '3306'));
define('CFG_DB_CHARSET', 'utf8mb4');

// Nomi tabella (prefisso per convivere in un DB condiviso, es. OVH con Helios h7_).
const TBL_CARDS = 'magic_cards';
const TBL_DECKS = 'magic_decks';
const TBL_SCENARIOS = 'magic_scenarios'; // villaggi della campagna 2.0 (vedi src/Scenarios.php)

// Chiave per l'installer one-shot (install.php). Cambiala/azzerala dopo l'uso.
define('INSTALL_KEY', env('INSTALL_KEY', 'varco-setup-2026'));

// --- Costanti di gioco (da tarare in playtest) -------------------------------
const HAND_SIZE      = 6;   // carte pescate per giocatore a inizio match
const MANA_CAP       = 10;  // tetto di mana per round (sulle 3 corsie)
const LANES          = 3;   // numero corsie
const MAGE_LIFE      = 10;  // vita iniziale di ogni mago (multi-round: vince chi azzera l'avversario)
const COSTO_MINIMO   = 1;   // costo minimo assunto per corsia (per la regola "stretta" opzionale)
const FATIGUE_FROM   = 6;   // dal round N in poi entrambi i maghi subiscono danno crescente (anti-stallo garantito)

// Regole costruzione mazzo: mini mono-colore.
const DECK_MIN_SIZE  = 15;  // dimensione minima mazzo
const DECK_MAX_SIZE  = 20;  // dimensione massima mazzo
const MAX_COPIES     = 2;   // copie massime della stessa carta

// Draft "apri buste": pesi di rarità (stile bustina MTG) e dimensioni.
const PACK_SIZE      = 15;  // carte per busta
const PACKS_PER_DRAFT = 2;  // buste aperte per draft (=> pool di 30 carte mono-colore)
const RARITY_WEIGHTS = ['common' => 70, 'uncommon' => 22, 'rare' => 6, 'mythic' => 2];

// --- Campagna "Varco" --------------------------------------------------------
// Draft pre-valle: per ogni round mostri CAMPAIGN_DRAFT_OPTIONS carte (mono-colore, pesate per
// rarità) ancorate a un costo di mana crescente; ne scegli CAMPAIGN_DRAFT_PICK. Gli "ancoraggi"
// definiscono la CURVA del mazzo iniziale: tante economiche, qualche carta cara (dove vivono le rare).
// Risultato: count(CAMPAIGN_DRAFT_ANCHORS) × CAMPAIGN_DRAFT_PICK carte (qui 6×2 = 12).
const CAMPAIGN_DRAFT_ANCHORS  = [1, 2, 2, 3, 4, 5]; // costo di mana "ancora" per ogni round di draft
const CAMPAIGN_DRAFT_OPTIONS  = 6;  // carte mostrate per round
const CAMPAIGN_DRAFT_PICK     = 2;  // carte scelte per round
const CAMPAIGN_REWARD_OPTIONS = 6;  // carte mostrate come bottino dopo ogni battaglia vinta
const CAMPAIGN_REWARD_PICK    = 1;  // carte scelte come bottino

// Difficoltà crescente della valle (livello = vittorie + 1, max CAMPAIGN_LEVELS). La CPU al
// livello 1 ha solo COMUNI deboli e poche carte; salendo aumentano forza, rarità e dimensione mazzo.
const CAMPAIGN_LEVELS = 5;
const CAMPAIGN_DIFFICULTY = [
    1 => ['rarities' => ['common'],                                  'max_power' => 2, 'max_mv' => 3, 'size' => 10],
    2 => ['rarities' => ['common'],                                  'max_power' => 3, 'max_mv' => 4, 'size' => 12],
    3 => ['rarities' => ['common', 'uncommon'],                      'max_power' => 4, 'max_mv' => 5, 'size' => 14],
    4 => ['rarities' => ['common', 'uncommon', 'rare'],              'max_power' => 5, 'max_mv' => 6, 'size' => 16],
    5 => ['rarities' => ['common', 'uncommon', 'rare', 'mythic'],    'max_power' => 99, 'max_mv' => 9, 'size' => 18],
];

// --- Campagna 2.0: hub delle 5 VALLI + VILLAGGI tematici (per tribù) ----------
// Una VALLE = un colore, contiene una sequenza ordinata di VILLAGGI. Un VILLAGGIO = uno scenario a
// tribù UNICA (es. tutto Goblin) con N livelli crescenti, l'ultimo è il BOSS. Fra un villaggio e il
// successivo c'è una LOCANDA (cura ferite, servizi). Si gioca con un MAZZO UNICO condiviso (draft +
// bottini) e si roama libero: ogni livello SBLOCCATO in qualsiasi valle/ordine, rigiocando i vinti.
//
// Gli scenari "veri" vivono in tabella DB `magic_scenarios` (editabili, schedulabili). Se la tabella
// manca o una valle non ha villaggi, si ripiega su questi DEFAULT (un villaggio tematico per colore).
const CAMPAIGN_VALLEY_TRIBES = [
    'W' => ['tribe' => 'Soldier', 'name' => 'Avamposto dei Soldati'],
    'U' => ['tribe' => 'Wizard',  'name' => 'Torre dei Maghi'],
    'B' => ['tribe' => 'Zombie',  'name' => 'Cripta degli Zombi'],
    'R' => ['tribe' => 'Goblin',  'name' => 'Accampamento Goblin'],
    'G' => ['tribe' => 'Elf',     'name' => 'Boschetto degli Elfi'],
];

// Curva di difficoltà DENTRO un villaggio: un def per livello (l'ultimo con 'boss'=>true). Il filtro
// dei nemici è SEMPRE ristretto alla tribù del villaggio; questi cap scalano forza/rarità/dimensione.
const CAMPAIGN_VILLAGE_LEVELS = [
    ['name' => 'Reclute',   'rarities' => ['common'],                               'max_power' => 1, 'max_mv' => 2, 'size' => 10],
    ['name' => 'Guerrieri', 'rarities' => ['common'],                               'max_power' => 2, 'max_mv' => 3, 'size' => 11],
    ['name' => 'Veterani',  'rarities' => ['common', 'uncommon'],                   'max_power' => 3, 'max_mv' => 4, 'size' => 12],
    ['name' => 'Campioni',  'rarities' => ['common', 'uncommon'],                   'max_power' => 4, 'max_mv' => 5, 'size' => 13],
    ['name' => 'Élite',     'rarities' => ['common', 'uncommon', 'rare'],           'max_power' => 6, 'max_mv' => 6, 'size' => 14],
    ['name' => 'Capo',      'rarities' => ['common', 'uncommon', 'rare', 'mythic'], 'max_power' => 99, 'max_mv' => 9, 'size' => 16, 'boss' => true],
];

// Locanda fra un villaggio e il successivo: servizi base (Fase 1). L'editor (Fase 2) li renderà ricchi.
const CAMPAIGN_LOCANDA = ['heal' => true, 'swap' => 1]; // cura le ferite (già azzerate fra battaglie) + 1 scambio carta

// Log diagnostico delle battaglie (JSONL in magic/logs/battle.jsonl, sopra la docroot).
// Serve a debuggare i combattimenti che "non tornano": ogni corsia risolta scrive una riga.
// Scaricabile via /api/logs.php?key=INSTALL_KEY  (anche ?tail=N e ?clear=1).
const LOG_BATTLE = true;

// Mostra errori in locale; su OVH conviene disattivarli (impostare a false in produzione).
define('DEBUG', (bool) env('DEBUG', 'false'));
if (DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}
