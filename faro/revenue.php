<?php
/**
 * Faro — entrypoint del CALLBACK REVENUE server-to-server (canale 's2s').
 *
 * È QUI che entrano i soldi: i network (H5 Games Ads, AppLixir, ...) chiamano
 * questo URL con l'importo reale. Per questo è l'unico canale autorizzato a
 * scrivere revenue_micros, ed è protetto da firma HMAC-SHA256 sul corpo grezzo.
 *
 *   POST /revenue.php
 *   Header: X-Faro-Signature: <hex hmac_sha256(body, FARO_S2S_SECRET)>
 *   Body JSON: { "app":"varco", "cid":"<uuid>", "sid":"<uuid>",
 *                "uid":"<uuid impression>", "src":"reddit",
 *                "revenue_micros":4200, "props":{"network":"applixir","format":"rewarded"} }
 *
 * Ogni adattatore di network (mappare i loro campi nel formato qui sopra) arriva
 * in una fase successiva: questo endpoint definisce il contratto interno.
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/collector.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method not allowed']);
    exit;
}

$body = file_get_contents('php://input') ?: '';

// --- Verifica firma HMAC (confronto a tempo costante) -------------------------
$sig = $_SERVER['HTTP_X_FARO_SIGNATURE'] ?? '';
$expected = hash_hmac('sha256', $body, FARO_S2S_SECRET);
if ($sig === '' || !hash_equals($expected, $sig)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'firma non valida']);
    exit;
}

$payload = json_decode($body, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'json non valido']);
    exit;
}

// Un evento revenue ha sempre un proprio uid (l'id impression del network):
// rende il callback idempotente se il network ritenta.
$payload['e'] = $payload['e'] ?? $payload['event'] ?? 'ad_revenue';

$pdo = faro_db();
try {
    $norm = faro_normalize_event($payload, 's2s', ['ip_hash' => null, 'ua_hash' => null]);
    $res = faro_write_event($pdo, $norm);
    echo json_encode(['ok' => true, 'result' => $res]);
} catch (FaroInvalidEvent $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
