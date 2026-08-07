<?php
/**
 * Faro — connessione PDO singleton (stessa convenzione di varco src/db.php).
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

function faro_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        FARO_DB_HOST,
        FARO_DB_PORT,
        FARO_DB_NAME,
        FARO_DB_CHARSET
    );

    $pdo = new PDO($dsn, FARO_DB_USER, FARO_DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}

/**
 * True se $table ha la colonna $col. Memoizzato per-processo: una sola
 * query a information_schema. Serve a far convivere il codice con DB non
 * ancora migrati (es. la colonna `ua` aggiunta dopo il primo deploy): chi
 * scrive/legge include la colonna solo se esiste davvero.
 */
function faro_has_column(PDO $pdo, string $table, string $col): bool
{
    static $cache = [];
    $key = $table . '.' . $col;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    try {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
             LIMIT 1'
        );
        $st->execute([$table, $col]);
        return $cache[$key] = (bool) $st->fetchColumn();
    } catch (Throwable $e) {
        return $cache[$key] = false;
    }
}
