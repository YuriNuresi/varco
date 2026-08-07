<?php
/**
 * Faro — il COLLECTOR: l'unico punto che scrive in faro_events.
 *
 * Sia il beacon JS (public/collect.php) sia il callback revenue (public/revenue.php)
 * sia l'helper server-side (track.php) passano da qui. Tutta la validazione,
 * l'anti-spam e l'idempotenza vivono in un solo posto.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

/** Eccezione per input non valido => 400 lato HTTP. */
class FaroInvalidEvent extends RuntimeException {}

/** IP del client, gestendo eventuali proxy/CDN davanti (es. Cloudflare). */
function faro_client_ip(): string
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $k) {
        $v = $_SERVER[$k] ?? '';
        if ($v !== '') {
            // X-Forwarded-For può essere una lista: prendi il primo.
            return trim(explode(',', $v)[0]);
        }
    }
    return '';
}

/** Hash a 16 char con salt: anti-spam/dedup senza memorizzare dati personali. */
function faro_hash(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    return substr(hash_hmac('sha256', $value, FARO_IP_SALT), 0, 16);
}

/** Identificatori (client_id/session_id/event_uid): uuid-ish, limitati. */
function faro_valid_id(string $id): bool
{
    return (bool) preg_match('/^[A-Za-z0-9_-]{8,36}$/', $id);
}

/** Nome evento: minuscole, snake_case, max 48. */
function faro_valid_event_name(string $e): bool
{
    return (bool) preg_match('/^[a-z0-9_]{1,48}$/', $e);
}

/** src acquisizione: minuscole, alfanumerico + -_ , max 64; vuoto ammesso. */
function faro_sanitize_src(string $src): string
{
    $src = strtolower(trim($src));
    $src = preg_replace('/[^a-z0-9_\-]/', '', $src) ?? '';
    return substr($src, 0, 64);
}

/** Rate-limit grezzo per IP (anti-flood). True = troppi eventi, rifiuta. */
function faro_rate_limited(PDO $pdo, ?string $ipHash): bool
{
    if ($ipHash === null) {
        return false;
    }
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM ' . FARO_TBL_EVENTS .
        ' WHERE ip_hash = ? AND ts >= (NOW(3) - INTERVAL ? SECOND)'
    );
    $st->execute([$ipHash, FARO_RATE_WINDOW_SEC]);
    return ((int) $st->fetchColumn()) >= FARO_RATE_MAX;
}

/**
 * Normalizza + valida un evento grezzo. Ritorna l'array pronto per l'insert.
 * Lancia FaroInvalidEvent se qualcosa non va.
 *
 * $ctx = ['ip_hash'=>..., 'ua_hash'=>...] iniettato dall'entrypoint.
 */
function faro_normalize_event(array $raw, string $channel, array $ctx): array
{
    $app = (string) ($raw['app'] ?? '');
    if (!preg_match('/^[a-z0-9_-]{2,32}$/', $app)
        || (FARO_APPS !== [] && !in_array($app, FARO_APPS, true))) {
        throw new FaroInvalidEvent("app non valida");
    }

    $cid = (string) ($raw['cid'] ?? $raw['client_id'] ?? '');
    $sid = (string) ($raw['sid'] ?? $raw['session_id'] ?? '');
    if (!faro_valid_id($cid) || !faro_valid_id($sid)) {
        throw new FaroInvalidEvent("client_id/session_id mancante o malformato");
    }

    $event = (string) ($raw['e'] ?? $raw['event'] ?? '');
    if (!faro_valid_event_name($event)) {
        throw new FaroInvalidEvent("nome evento non valido");
    }

    $props = $raw['props'] ?? null;
    if ($props !== null && !is_array($props)) {
        throw new FaroInvalidEvent("props deve essere un oggetto");
    }
    $propsJson = $props === null ? null : json_encode($props, JSON_UNESCAPED_UNICODE);
    if ($propsJson !== null && strlen($propsJson) > FARO_PROPS_MAX_BYTES) {
        throw new FaroInvalidEvent("props troppo grande");
    }

    $uid = (string) ($raw['uid'] ?? $raw['event_uid'] ?? '');
    $eventUid = ($uid !== '' && faro_valid_id($uid)) ? $uid : null;

    // Il revenue è ammesso SOLO dal canale s2s (callback firmato). Mai dal client.
    $revenue = 0;
    if ($channel === 's2s') {
        $revenue = (int) ($raw['revenue_micros'] ?? 0);
        if ($revenue < 0 || $revenue > 1_000_000_000) { // sanity: max 1.000 € per evento
            throw new FaroInvalidEvent("revenue_micros fuori range");
        }
    }

    return [
        'app'            => $app,
        'client_id'      => $cid,
        'session_id'     => $sid,
        'event'          => $event,
        'src'            => faro_sanitize_src((string) ($raw['src'] ?? '')),
        'props'          => $propsJson,
        'revenue_micros' => $revenue,
        'channel'        => $channel,
        'ip_hash'        => $ctx['ip_hash'] ?? null,
        'ua_hash'        => $ctx['ua_hash'] ?? null,
        'ua'             => (isset($ctx['ua']) && trim((string) $ctx['ua']) !== '')
                             ? mb_substr((string) $ctx['ua'], 0, 255) : null,
        'event_uid'      => $eventUid,
    ];
}

/**
 * Scrive un evento normalizzato. INSERT IGNORE rende l'operazione idempotente
 * sull'event_uid (i retry del beacon non creano duplicati).
 * Ritorna 'ok' se inserito, 'dup' se già presente.
 */
function faro_write_event(PDO $pdo, array $ev): string
{
    // La colonna `ua` può non esistere ancora su DB non migrati: includila solo
    // se presente, così il collector non si rompe prima di lanciare install.php.
    $hasUa = faro_has_column($pdo, FARO_TBL_EVENTS, 'ua');

    $cols = ['app', 'client_id', 'session_id', 'event', 'src', 'props',
             'revenue_micros', 'channel', 'ip_hash', 'ua_hash', 'event_uid'];
    $params = [
        ':app'            => $ev['app'],
        ':client_id'      => $ev['client_id'],
        ':session_id'     => $ev['session_id'],
        ':event'          => $ev['event'],
        ':src'            => $ev['src'],
        ':props'          => $ev['props'],
        ':revenue_micros' => $ev['revenue_micros'],
        ':channel'        => $ev['channel'],
        ':ip_hash'        => $ev['ip_hash'],
        ':ua_hash'        => $ev['ua_hash'],
        ':event_uid'      => $ev['event_uid'],
    ];
    if ($hasUa) {
        $cols[] = 'ua';
        $params[':ua'] = $ev['ua'] ?? null;
    }

    $placeholders = array_map(fn($c) => ':' . $c, $cols);
    $sql = 'INSERT IGNORE INTO ' . FARO_TBL_EVENTS
        . ' (' . implode(', ', $cols) . ')'
        . ' VALUES (' . implode(', ', $placeholders) . ')';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->rowCount() > 0 ? 'ok' : 'dup';
}
