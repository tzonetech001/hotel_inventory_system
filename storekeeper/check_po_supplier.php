<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';

header('Content-Type: application/json');

$po_number = $_GET['po'] ?? '';
$item_id = intval($_GET['item_id'] ?? 0);

// If no PO number provided, return valid (since PO is optional)
if (empty($po_number)) {
    echo json_encode(['valid' => true, 'message' => 'No PO number provided']);
    exit();
}

if ($item_id <= 0) {
    echo json_encode(['valid' => false, 'message' => 'Please select an item first']);
    exit();
}

// Get item's supplier
$item_sql = "SELECT supplier_id, item_name FROM inventory_items WHERE id = ?";
$item_stmt = $db->prepare($item_sql);
$item_stmt->bind_param("i", $item_id);
$item_stmt->execute();
$item_result = $item_stmt->get_result();
$item_data = $item_result->fetch_assoc();

if (!$item_data) {
    echo json_encode(['valid' => false, 'message' => 'Item not found!']);
    exit();
}

if (!$item_data['supplier_id']) {
    echo json_encode(['valid' => false, 'message' => 'This item has no supplier assigned!']);
    exit();
}

$supplier_id = $item_data['supplier_id'];

// Check if PO exists and is approved for this supplier
$po_sql = "SELECT po.id, po.status, s.company_name 
           FROM purchase_orders po
           JOIN suppliers s ON po.supplier_id = s.id
           WHERE po.po_number = ? AND po.supplier_id = ? 
           AND po.status IN ('approved', 'delivered', 'confirmed')";
$po_stmt = $db->prepare($po_sql);
$po_stmt->bind_param("si", $po_number, $supplier_id);
$po_stmt->execute();
$po_result = $po_stmt->get_result();

if ($po_result->num_rows > 0) {
    $po_data = $po_result->fetch_assoc();
    echo json_encode([
        'valid' => true, 
        'message' => "✓ Valid PO: $po_number for " . htmlspecialchars($po_data['company_name'])
    ]);
} else {
    // Check if PO exists but not approved
    $check_sql = "SELECT status, s.company_name 
                  FROM purchase_orders po
                  JOIN suppliers s ON po.supplier_id = s.id
                  WHERE po.po_number = ? AND po.supplier_id = ?";
    $check_stmt = $db->prepare($check_sql);
    $check_stmt->bind_param("si", $po_number, $supplier_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $check_data = $check_result->fetch_assoc();
        $status = $check_data['status'];
        $status_text = $status == 'pending' ? 'Pending Approval' : ucfirst($status);
        echo json_encode([
            'valid' => false, 
            'message' => "PO $po_number exists but status is '$status_text'. Only approved POs can be used!"
        ]);
    } else {
        echo json_encode([
            'valid' => false, 
            'message' => "PO $po_number not found or does not belong to this item's supplier!"
        ]);
    }
}
?>