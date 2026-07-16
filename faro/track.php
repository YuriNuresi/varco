<?php
/**
 * Faro — helper SERVER-SIDE (canale 'server').
 *
 * Da includere in cima a ogni pagina di gioco:
 *
 *     <?php require __DIR__ . '/path/to/faro/track.php'; faro_track('varco'); ?>
 *
 * Registra il pageview e la sorgente (src) lato server, a render-time: è immune
 * agli adblock e costituisce la "verità di base" sul traffico. L'engagement
 * dettagliato (click, rewarded) lo aggiunge invece sdk.js lato client.
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/collector.php';

/** UUID v4. */
function faro_uuid(): string
{
    $d = random_bytes(16);
    $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
    $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
}

/** Cookie helper coerente (host-only; per cross-subdomain impostare il domain). */
function faro_set_cookie(string $name, string $value, int $maxAge): void
{
    if (headers_sent()) {
        return;
    }
    setcookie($name, $value, [
        'expires'  => $maxAge > 0 ? time() + $maxAge : 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => false, // sdk.js deve poter leggere cid/sid per coerenza
        'samesite' => 'Lax',
    ]);
}

/** client_id persistente (1 anno): l'identità anonima per dominio. */
function faro_client_id(): string
{
    $cid = $_COOKIE['faro_cid'] ?? '';
    if (!faro_valid_id($cid)) {
        $cid = faro_uuid();
        faro_set_cookie('faro_cid', $cid, 31536000);
        $_COOKIE['faro_cid'] = $cid;
    }
    return $cid;
}

/** session_id (30 min scorrevoli). */
function faro_session_id(): string
{
    $sid = $_COOKIE['faro_sid'] ?? '';
    if (!faro_valid_id($sid)) {
        $sid = faro_uuid();
    }
    faro_set_cookie('faro_sid', $sid, 1800); // rinnova la finestra a ogni pageview
    $_COOKIE['faro_sid'] = $sid;
    return $sid;
}

/**
 * Sorgente di acquisizione, first-touch: ?src=... vince e viene memorizzato per
 * 30 giorni; in mancanza si eredita il cookie; in ultima istanza dal referrer.
 */
function faro_src(): string
{
    $src = faro_sanitize_src((string) ($_GET['src'] ?? ''));
    if ($src !== '') {
        faro_set_cookie('faro_src', $src, 2592000);
        return $src;
    }
    $cookieSrc = faro_sanitize_src((string) ($_COOKIE['faro_src'] ?? ''));
    if ($cookieSrc !== '') {
        return $cookieSrc;
    }
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    if ($ref !== '') {
        $host = parse_url($ref, PHP_URL_HOST) ?: '';
        $host = preg_replace('/^www\./', '', strtolower($host));
        if ($host !== '' && stripos($host, ($_SERVER['HTTP_HOST'] ?? '')) === false) {
            $src = faro_sanitize_src(explode('.', $host)[0]);
            if ($src !== '') {
                faro_set_cookie('faro_src', $src, 2592000);
                return $src;
            }
        }
    }
    return '';
}

/**
 * Registra un evento lato server. Non lancia mai: il tracking non deve MAI
 * rompere la pagina di gioco (fallisce in silenzio, eventualmente loggando).
 */
function faro_track(string $app, string $event = 'pageview', array $props = []): void
{
    try {
        $ev = faro_normalize_event([
            'app'   => $app,
            'cid'   => faro_client_id(),
            'sid'   => faro_session_id(),
            'e'     => $event,
            'src'   => faro_src(),
            'props' => $props ?: null,
            // uid deterministico per pageview: 1 pageview per sessione+evento+ora.
            'uid'   => substr(hash('sha256', $app . faro_session_id() . $event . gmdate('YmdH')), 0, 32),
        ], 'server', [
            'ip_hash' => faro_hash(faro_client_ip()),
            'ua_hash' => faro_hash($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'ua'      => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ]);
        faro_write_event(faro_db(), $ev);
    } catch (Throwable $e) {
        if (FARO_DEBUG) {
            error_log('[faro_track] ' . $e->getMessage());
        }
    }
}
