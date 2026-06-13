<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

checkAuth(['Procurement Officer', 'Hotel Manager', 'Storekeeper']);

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'No ID provided']);
    exit();
}

$po_id = intval($_GET['id']);

// Get PO details with rejection_reason
$sql = "SELECT po.*, s.company_name as supplier_name, 
        s.contact_person, s.phone as supplier_phone,
        u.fullname as created_by_name,
        a.fullname as approved_by_name
        FROM purchase_orders po
        JOIN suppliers s ON po.supplier_id = s.id
        JOIN users u ON po.created_by = u.id
        LEFT JOIN users a ON po.approved_by = a.id
        WHERE po.id = ?";

$stmt = $db->prepare($sql);
$stmt->bind_param("i", $po_id);
$stmt->execute();
$result = $stmt->get_result();
$po = $result->fetch_assoc();

if (!$po) {
    echo json_encode(['error' => 'Purchase order not found']);
    exit();
}

// Get PO items
$items_sql = "SELECT pi.*, i.item_name, i.unit 
              FROM po_items pi
              JOIN inventory_items i ON pi.item_id = i.id
              WHERE pi.po_id = ?";

$items_stmt = $db->prepare($items_sql);
$items_stmt->bind_param("i", $po_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();
$items = $items_result->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    'po' => $po,
    'items' => $items
]);
?>