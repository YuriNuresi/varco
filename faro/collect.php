<?php
/**
 * Faro — entrypoint del BEACON lato client (canale 'client').
 *
 * Riceve un batch di eventi di engagement inviati da sdk.js via
 * navigator.sendBeacon(). NON accetta revenue (quello arriva solo via S2S firmato).
 *
 *   POST /collect.php
 *   Content-Type: application/json
 *   { "app":"varco", "cid":"<uuid>", "sid":"<uuid>",
 *     "events":[ {"e":"game_start","uid":"<uuid>","src":"reddit","props":{...}}, ... ] }
 *
 * Risposta: { "ok":true, "written":N, "dup":M } oppure { "ok":false, "error":"..." }
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/collector.php';

// --- CORS: i giochi stanno su domini diversi dal sottodominio di Faro ---------
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = array_map('trim', explode(',', FARO_ALLOWED_ORIGINS));
if (FARO_ALLOWED_ORIGINS === '*') {
    header('Access-Control-Allow-Origin: *');
} elseif ($origin !== '' && in_array($origin, $allowed, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204); // preflight
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method not allowed']);
    exit;
}

$body = file_get_contents('php://input') ?: '';
$payload = json_decode($body, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'json non valido']);
    exit;
}

$events = $payload['events'] ?? null;
if (!is_array($events) || $events === []) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'nessun evento']);
    exit;
}
if (count($events) > FARO_BATCH_MAX) {
    http_response_code(413);
    echo json_encode(['ok' => false, 'error' => 'batch troppo grande']);
    exit;
}

$pdo = faro_db();

$ctx = [
    'ip_hash' => faro_hash(faro_client_ip()),
    'ua_hash' => faro_hash($_SERVER['HTTP_USER_AGENT'] ?? ''),
    'ua'      => $_SERVER['HTTP_USER_AGENT'] ?? '',
];

if (faro_rate_limited($pdo, $ctx['ip_hash'])) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'troppe richieste']);
    exit;
}

// Campi a livello di batch (app/cid/sid) ereditati dai singoli eventi.
$base = [
    'app' => $payload['app'] ?? '',
    'cid' => $payload['cid'] ?? '',
    'sid' => $payload['sid'] ?? '',
];

$written = 0;
$dup = 0;
$errors = [];
foreach ($events as $i => $ev) {
    if (!is_array($ev)) {
        $errors[] = "evento #$i non è un oggetto";
        continue;
    }
    try {
        $norm = faro_normalize_event($ev + $base, 'client', $ctx);
        $res = faro_write_event($pdo, $norm);
        $res === 'ok' ? $written++ : $dup++;
    } catch (FaroInvalidEvent $e) {
        $errors[] = "evento #$i: " . $e->getMessage();
    }
}

echo json_encode([
    'ok'      => true,
    'written' => $written,
    'dup'     => $dup,
    'errors'  => $errors,
]);
