<?php
/**
 * Faro — pagina di stato (health check).
 *
 * Evita il 403 "nudo" su /faro e conferma a colpo d'occhio che il servizio è su
 * e che il DB risponde. Non espone configurazione né dati.
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/db.php';

header('Content-Type: application/json; charset=utf-8');

$db = 'down';
try {
    faro_db()->query('SELECT 1');
    $db = 'up';
} catch (Throwable $e) {
    http_response_code(503);
}

echo json_encode([
    'service' => 'faro',
    'status'  => 'ok',
    'db'      => $db,
    'time'    => gmdate('c'),
], JSON_PRETTY_PRINT);
