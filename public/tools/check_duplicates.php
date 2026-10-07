<?php
header('Content-Type: application/json; charset=utf-8');

// Carica credenziali dal .env
$envFile = __DIR__ . '/../../.env';
$env = [];
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
        $env[trim($k)] = trim($v, " \t\"'");
    }
}

$key = $_GET['key'] ?? '';
if ($key !== ($env['INSTALL_KEY'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Chiave non valida']);
    exit;
}

try {
    $pdo = new PDO(
        'mysql:host=' . ($env['DB_HOST'] ?? 'localhost') . ';dbname=' . ($env['DB_NAME'] ?? 'sirialibjo533'),
        $env['DB_USER'] ?? '',
        $env['DB_PASS'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::MYSQL_ATTR_CHARSET => 'utf8mb4']
    );
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB: ' . $e->getMessage()]);
    exit;
}

// Trova duplicati
$sql = "SELECT name, colors, COUNT(*) as cnt, GROUP_CONCAT(id) as ids 
        FROM magic_cards 
        WHERE source = 'custom' 
        GROUP BY name, colors 
        HAVING cnt > 1 
        ORDER BY name";

try {
    $stmt = $pdo->query($sql);
    $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($duplicates)) {
        echo json_encode(['ok' => true, 'message' => 'Nessun duplicato trovato', 'duplicates_count' => 0]);
        exit;
    }
    
    // Per ogni duplicato, mostra quali versioni hanno immagine e quali no
    $details = [];
    foreach ($duplicates as $dup) {
        $ids = explode(',', $dup['ids']);
        $check_sql = "SELECT id, image_url FROM magic_cards WHERE id IN (" . implode(',', array_map('strval', $ids)) . ")";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute();
        $versions = $check_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $details[] = [
            'name' => $dup['name'],
            'colors' => $dup['colors'],
            'total' => $dup['cnt'],
            'versions' => $versions
        ];
    }
    
    echo json_encode(['ok' => true, 'duplicates_count' => count($duplicates), 'details' => $details], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
