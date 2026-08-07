<?php
/**
 * Faro — configurazione globale.
 *
 * Riusa lo STESSO MySQL condiviso degli altri giochi su OVH: legge le credenziali
 * dal .env di root (../.env, condiviso) e da un .env locale opzionale (precedenza).
 * Le tabelle Faro hanno prefisso faro_ per convivere con magic_* e h7_*.
 */

declare(strict_types=1);

// --- Caricamento .env (root condiviso, poi locale che ha la precedenza) -------
(function (): void {
    // Ordine: .env condiviso nella home OVH (due livelli sopra 3d/faro/), poi
    // eventuali .env locali. La prima occorrenza di ogni chiave vince.
    $envFiles = [__DIR__ . '/../../.env', __DIR__ . '/../.env', __DIR__ . '/.env'];
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
    }
})();

function faro_env(string $key, ?string $default = null): ?string
{
    $v = getenv($key);
    return $v === false ? $default : $v;
}

// --- Credenziali DB (le stesse di varco/helios: DB condiviso) -----------------
define('FARO_DB_HOST', faro_env('DB_HOST', 'localhost'));
define('FARO_DB_NAME', faro_env('DB_NAME', 'magic_game'));
define('FARO_DB_USER', faro_env('DB_USER', 'root'));
define('FARO_DB_PASS', faro_env('DB_PASS', ''));
define('FARO_DB_PORT', (int) faro_env('DB_PORT', '3306'));
define('FARO_DB_CHARSET', 'utf8mb4');

const FARO_TBL_EVENTS = 'faro_events';

// --- App ammesse --------------------------------------------------------------
// Se FARO_APPS (csv nel .env) è valorizzato fa da whitelist rigida; altrimenti si
// accetta qualunque id ben formato (^[a-z0-9_-]{2,32}$). Comodo col portale e i
// suoi sotto-progetti, che crescono nel tempo senza toccare la config.
define('FARO_APPS', array_values(array_filter(array_map('trim', explode(',', (string) faro_env('FARO_APPS', ''))))));

// --- Sicurezza ----------------------------------------------------------------
// Salt per gli hash di IP/UA (anti-spam senza memorizzare dati personali).
// METTERE un valore casuale lungo nel .env di root: FARO_IP_SALT=...
define('FARO_IP_SALT', faro_env('FARO_IP_SALT', 'cambia-questo-salt'));

// Segreto per verificare i callback revenue server-to-server (HMAC-SHA256).
// METTERE nel .env: FARO_S2S_SECRET=...
define('FARO_S2S_SECRET', faro_env('FARO_S2S_SECRET', 'cambia-questo-segreto-s2s'));

// Origini ammesse per il beacon JS (CORS). I tuoi domini di gioco.
// Esempio .env: FARO_ALLOWED_ORIGINS=https://varco.tuodominio.it,https://helios.tuodominio.it
define('FARO_ALLOWED_ORIGINS', faro_env('FARO_ALLOWED_ORIGINS', '*'));

// Chiave per l'installer one-shot (install.php). Azzerala/cambiala dopo l'uso.
define('FARO_INSTALL_KEY', faro_env('FARO_INSTALL_KEY', 'faro-setup-2026'));

// Password per la dashboard e l'API metriche.
define('FARO_ADMIN_PASSWORD', faro_env('FARO_ADMIN_PASSWORD', 'faro-admin-2026'));

// --- Mail / briefing mattutino (digest.php) -----------------------------------
// Destinatario e mittente del briefing. Ricadono sui valori condivisi se presenti.
define('FARO_MAIL_TO',   faro_env('FARO_MAIL_TO',   faro_env('ADMIN_EMAILS', 'yurrena@gmail.com')));
define('FARO_MAIL_FROM', faro_env('FARO_MAIL_FROM', faro_env('SMTP_FROM', 'noreply@portale3d.it')));
// SMTP PREDISPOSTO: se FARO_SMTP_HOST (o SMTP_HOST condiviso) è valorizzato il mailer
// usa SMTP; altrimenti ricade su mail() di PHP. Riusa le chiavi SMTP_* condivise.
define('FARO_SMTP_HOST',   faro_env('FARO_SMTP_HOST',   faro_env('SMTP_HOST', '')));
define('FARO_SMTP_PORT',   (int) faro_env('FARO_SMTP_PORT', faro_env('SMTP_PORT', '587')));
define('FARO_SMTP_USER',   faro_env('FARO_SMTP_USER',   faro_env('SMTP_USER', '')));
define('FARO_SMTP_PASS',   faro_env('FARO_SMTP_PASS',   faro_env('SMTP_PASS', '')));
define('FARO_SMTP_SECURE', faro_env('FARO_SMTP_SECURE', 'tls')); // 'tls' | 'ssl' | ''

// Chiave per il cron del digest (riusa CRON_KEY condivisa se presente).
define('FARO_CRON_KEY', faro_env('FARO_CRON_KEY', faro_env('CRON_KEY', 'faro-cron-2026')));

// --- Limiti anti-abuso --------------------------------------------------------
const FARO_RATE_WINDOW_SEC = 10;   // finestra di rate-limit
const FARO_RATE_MAX        = 60;   // eventi max per IP nella finestra
const FARO_BATCH_MAX       = 50;   // eventi max per singola richiesta beacon
const FARO_PROPS_MAX_BYTES = 4096; // tetto dimensione props (JSON)

// --- Debug: spento di default; in locale metti FARO_DEBUG=1 nel .env ----------
define('FARO_DEBUG', faro_env('FARO_DEBUG', '0') === '1');
if (FARO_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}
