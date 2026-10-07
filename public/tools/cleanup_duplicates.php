<?php
/**
 * Script di pulizia duplicati
 * Trova carte con nome identico (stesso colore) e cancella quella senza immagine
 * 
 * GET /tools/cleanup_duplicates.php?key=INSTALL_KEY
 * GET /tools/cleanup_duplicates.php?key=INSTALL_KEY&action=delete
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
    $action = (string) ($_GET['action'] ?? '');

    // Trova duplicati: stessa carta (name + colors) con più ID
    $sql = "SELECT name, colors, COUNT(*) as cnt, GROUP_CONCAT(id) as ids 
            FROM " . TBL_CARDS . " 
            WHERE source = 'custom' 
            GROUP BY name, colors 
            HAVING cnt > 1 
            ORDER BY name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($duplicates)) {
        json_out(['ok' => true, 'message' => 'Nessun duplicato trovato', 'count' => 0]);
        exit;
    }

    // Analizza i duplicati
    $to_delete = [];
    $summary = [];

    foreach ($duplicates as $dup) {
        $ids = explode(',', $dup['ids']);
        $name = $dup['name'];
        $colors = $dup['colors'];
        
        // Carica tutte le versioni
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $check_sql = "SELECT id, image_url FROM " . TBL_CARDS . " WHERE id IN ($placeholders) ORDER BY image_url DESC";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute($ids);
        $versions = $check_stmt->fetchAll(PDO::FETCH_ASSOC);

        // La prima ha immagine (ORDER BY image_url DESC), le altre no
        $with_img = $versions[0] ?? null;
        $without_img = array_slice($versions, 1);

        foreach ($without_img as $v) {
            $to_delete[] = $v['id'];
            $summary[] = [
                'card' => "$name ($colors)",
                'keep_id' => $with_img['id'],
                'delete_id' => $v['id'],
                'status' => 'pronto per eliminare'
            ];
        }
    }

    // Se action=delete, elimina i duplicati
    if ($action === 'delete' && !empty($to_delete)) {
        $placeholders = implode(',', array_fill(0, count($to_delete), '?'));
        $del_sql = "DELETE FROM " . TBL_CARDS . " WHERE id IN ($placeholders)";
        $del_stmt = $pdo->prepare($del_sql);
        $del_stmt->execute($to_delete);
        $deleted_count = $del_stmt->rowCount();

        json_out([
            'ok' => true,
            'message' => "Eliminati $deleted_count duplicati senza immagine",
            'deleted_count' => $deleted_count,
            'deleted_summary' => $summary
        ]);
    } else {
        json_out([
            'ok' => true,
            'message' => 'Duplicati trovati (PRE-DELETE view)',
            'duplicates_found' => count($to_delete),
            'to_delete' => $summary,
            'next_step' => 'Aggiungi &action=delete per eliminare i duplicati'
        ]);
    }

} catch (Throwable $e) {
    json_err('Errore: ' . $e->getMessage());
}
