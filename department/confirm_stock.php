<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';


header('Content-Type: application/json');

// Check if department user is logged in
if (!isset($_SESSION['department_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$department_user_id = $_SESSION['department_user_id'];
$department_id = $_SESSION['department_id'];

$request_code = $input['request_code'] ?? '';
$request_id = $input['request_id'] ?? 0;

$db->begin_transaction();

try {
    // Get request details
    if ($request_code) {
        $sql = "SELECT sr.*, i.item_name, i.unit, i.current_stock 
                FROM stock_requests sr
                JOIN inventory_items i ON sr.item_id = i.id
                WHERE sr.request_code = ? AND sr.department_id = ? AND sr.status = 'pending'";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("si", $request_code, $department_id);
    } else {
        $sql = "SELECT sr.*, i.item_name, i.unit, i.current_stock 
                FROM stock_requests sr
                JOIN inventory_items i ON sr.item_id = i.id
                WHERE sr.id = ? AND sr.department_id = ? AND sr.status = 'pending'";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("ii", $request_id, $department_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $request = $result->fetch_assoc();
    
    if (!$request) {
        throw new Exception("Request not found or already processed");
    }
    
    // Check if enough stock is still available
    $current_stock = getCurrentStock($request['item_id']);
    if ($request['quantity'] > $current_stock) {
        throw new Exception("Insufficient stock! Only $current_stock available");
    }
    
    // Update stock (deduct)
    $storekeeper_id = $request['requested_by'];
    $reference = "QR Confirmed - " . $request['request_code'];
    
    if (updateStock($request['item_id'], $request['quantity'], 'OUT', $storekeeper_id, $reference)) {
        // Update request status
        $update_sql = "UPDATE stock_requests SET status = 'confirmed', confirmed_at = NOW(), department_user_id = ? WHERE id = ?";
        $update_stmt = $db->prepare($update_sql);
        $update_stmt->bind_param("ii", $department_user_id, $request['id']);
        $update_stmt->execute();
        
        // Record confirmation
        $confirm_sql = "INSERT INTO stock_out_confirmations (request_id, confirmed_by, confirmation_method, confirmed_at, ip_address) 
                        VALUES (?, ?, 'qr_scan', NOW(), ?)";
        $confirm_stmt = $db->prepare($confirm_sql);
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $confirm_stmt->bind_param("iis", $request['id'], $department_user_id, $ip_address);
        $confirm_stmt->execute();
        
        // Log activity
        logActivity($storekeeper_id, 'Stock OUT Confirmed', 
                   "Request {$request['request_code']} confirmed by department user ID: $department_user_id");
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Stock confirmed successfully',
            'item_name' => $request['item_name'],
            'quantity' => $request['quantity']
        ]);
    } else {
        throw new Exception("Error updating stock");
    }
    
} catch (Exception $e) {
    $db->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>