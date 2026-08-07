<?php
/**
 * Faro — API JSON delle ultime visite (per il refresh live della dashboard).
 *
 *   GET /faro/visits.php?days=7            (se loggato nella dashboard)
 *   GET /faro/visits.php?days=7&key=FARO_ADMIN_PASSWORD
 *
 * Ritorna { ok, count, visits[] } dove `count` = sessioni distinte nella
 * finestra (per rilevare nuove visite) e `visits` = ultime 100 (1 per sessione).
 */

declare(strict_types=1);

session_start();
require_once __DIR__ . '/lib/metrics.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$authed = !empty($_SESSION['faro_admin'])
    || (string) ($_GET['key'] ?? '') === FARO_ADMIN_PASSWORD;
if (!$authed) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    exit;
}

$days = (int) ($_GET['days'] ?? 7);
$demo = !empty($_GET['demo']);

try {
    $pdo = faro_db();

    // KPI di testata + schede per-gioco (per aggiornarli live come la tabella).
    $overview = faro_overview($pdo, $days, $demo);
    $perApp   = faro_per_app($pdo, $days, $demo);
    $count    = (int) $overview['sessions']; // = COUNT(DISTINCT session_id) nella finestra

    $visits = array_map(static function (array $r): array {
        return [
            'first_ts'  => $r['first_ts'],
            'app'       => $r['app'],
            'src'       => $r['src'],
            'pageviews' => (int) $r['pageviews'],
            'dur'       => (int) $r['dur'],
            'ip_hash'   => $r['ip_hash'],
            'ua'        => $r['ua'],
            'kind'      => $r['kind'],
        ];
    }, faro_recent_visits($pdo, $days, $demo, 100));

    echo json_encode([
        'ok'       => true,
        'count'    => $count,
        'overview' => $overview,
        'per_app'  => $perApp,
        'visits'   => $visits,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
