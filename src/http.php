<?php
/**
 * Helper comuni per gli endpoint JSON.
 */

declare(strict_types=1);

/*
 * Robustezza JSON: un warning/notice PHP non deve MAI finire stampato dentro la risposta
 * (corromperebbe il JSON e il client crasherebbe su r.json()). Quindi:
 *  - i warning vengono "ingoiati" e raccolti in $GLOBALS['__json_warns'];
 *  - l'output viene bufferizzato e scartato prima di emettere il JSON;
 *  - un eventuale errore fatale viene comunque restituito come JSON.
 * Con DEBUG i warning raccolti vengono inclusi nella risposta sotto la chiave "_warn".
 */
$GLOBALS['__json_warns'] = [];
ini_set('display_errors', '0');
ini_set('html_errors', '0');
set_error_handler(function (int $no, string $str, string $file = '', int $line = 0): bool {
    $GLOBALS['__json_warns'][] = $str . ' @ ' . basename($file) . ':' . $line;
    return true; // ingoia: niente output, l'esecuzione prosegue
});
register_shutdown_function(function (): void {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['ok' => false,
            'error' => 'PHP fatal: ' . $e['message'] . ' @ ' . basename($e['file']) . ':' . $e['line']]);
    }
});
ob_start();

function json_out($data, int $status = 200): never
{
    while (ob_get_level() > 0) { ob_end_clean(); } // scarta qualsiasi output spurio (warning/HTML)
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    if (!empty($GLOBALS['__json_warns']) && is_array($data)) {
        $data['_warn'] = $GLOBALS['__json_warns']; // diagnostica (DEBUG)
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_err(string $message, int $status = 400): never
{
    json_out(['ok' => false, 'error' => $message], $status);
}

/** Legge il body JSON (o i campi POST) della richiesta. @return array<string,mixed> */
function read_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw !== false && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }
    return $_POST;
}
