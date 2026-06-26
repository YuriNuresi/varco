<?php
/**
 * Connessione PDO singleton.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        CFG_DB_HOST,
        CFG_DB_PORT,
        CFG_DB_NAME,
        CFG_DB_CHARSET
    );

    $pdo = new PDO($dsn, CFG_DB_USER, CFG_DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}
