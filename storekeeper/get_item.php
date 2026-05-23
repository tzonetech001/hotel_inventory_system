<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';

header('Content-Type: application/json');

$item_id = intval($_GET['id'] ?? 0);

if ($item_id > 0) {
    $sql = "SELECT i.*, s.company_name as supplier_name 
            FROM inventory_items i
            LEFT JOIN suppliers s ON i.supplier_id = s.id
            WHERE i.id = $item_id";
    $result = $db->query($sql);
    
    if ($item = $result->fetch_assoc()) {
        echo json_encode($item);
    } else {
        echo json_encode(['error' => 'Item not found']);
    }
} else {
    echo json_encode(['error' => 'Invalid item ID']);
}
?>