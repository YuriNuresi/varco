<?php
/**
 * Faro — API metriche (JSON).
 *
 *   GET /faro/metrics.php?days=7&key=FARO_ADMIN_PASSWORD
 *   GET /faro/metrics.php?days=7            (se loggato nella dashboard)
 *
 * Restituisce l'intero report (overview, per-app, acquisizione, mediation,
 * retention, serie giornaliera). Usata dalla dashboard, dall'Analyst e dalla mail.
 */

declare(strict_types=1);

session_start();
require_once __DIR__ . '/lib/metrics.php';

header('Content-Type: application/json; charset=utf-8');

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
    echo json_encode(['ok' => true] + faro_report(faro_db(), $days, $demo), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
