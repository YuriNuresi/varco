<?php
/**
 * Quick stats: conta carte per colore nel database
 * 
 * GET /tools/card_stats.php?key=INSTALL_KEY
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/http.php';

// --- Auth ----
$key = $_GET['key'] ?? $_SERVER['HTTP_X_ADMIN_KEY'] ?? '';
if ($key !== INSTALL_KEY && !is_admin()) {
    json_err('Accesso riservato', 403);
}

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = db();
    
    // Count per colore
    $colors = ['B' => 'Nere', 'R' => 'Rosse', 'G' => 'Verdi', 'W' => 'Bianche', 'U' => 'Blu'];
    $stats = [];
    $total = 0;
    
    foreach ($colors as $code => $name) {
        $stmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM ' . TBL_CARDS . ' WHERE colors = ?');
        $stmt->execute([$code]);
        $row = $stmt->fetch();
        $count = $row['cnt'] ?? 0;
        
        $stats[$code] = [
            'name' => $name,
            'count' => $count,
        ];
        $total += $count;
    }
    
    // Count custom per tipo
    $stmt = $pdo->prepare('SELECT colors, subtypes, COUNT(*) as cnt FROM ' . TBL_CARDS . ' WHERE source = \'custom\' GROUP BY colors, subtypes ORDER BY colors, subtypes');
    $stmt->execute();
    $custom = [];
    foreach ($stmt->fetchAll() as $row) {
        $custom[] = [
            'color' => $row['colors'],
            'type' => $row['subtypes'],
            'count' => $row['cnt'],
        ];
    }
    
    json_out([
        'ok' => true,
        'totals' => $stats,
        'grand_total' => $total,
        'custom_breakdown' => $custom,
    ]);
} catch (Throwable $e) {
    json_err('Errore: ' . $e->getMessage());
}
