<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';

header('Content-Type: application/json');

$po_id = intval($_GET['id'] ?? 0);

if ($po_id > 0) {
    // Get PO details
    $sql = "SELECT po.*, s.company_name as supplier_name, s.contact_person, s.email as supplier_email, s.phone as supplier_phone,
            u.fullname as created_by_name, a.fullname as approved_by_name
            FROM purchase_orders po
            JOIN suppliers s ON po.supplier_id = s.id
            JOIN users u ON po.created_by = u.id
            LEFT JOIN users a ON po.approved_by = a.id
            WHERE po.id = $po_id";
    
    $result = $db->query($sql);
    $po = $result->fetch_assoc();
    
    if ($po) {
        // Get PO items
        $items_sql = "SELECT pi.*, i.item_name, i.unit 
                      FROM po_items pi
                      JOIN inventory_items i ON pi.item_id = i.id
                      WHERE pi.po_id = $po_id";
        $items_result = $db->query($items_sql);
        $items = $items_result->fetch_all(MYSQLI_ASSOC);
        
        echo json_encode([
            'po' => $po,
            'items' => $items
        ]);
    } else {
        echo json_encode(['error' => 'Purchase order not found']);
    }
} else {
    echo json_encode(['error' => 'Invalid PO ID']);
}
?>