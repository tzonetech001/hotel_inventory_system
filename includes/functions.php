<?php
require_once 'db_connection.php';

// ============================================
// LOGGING FUNCTIONS
// ============================================

// Log system activity
function logActivity($user_id, $action, $details = null) {
    global $db;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $sql = "INSERT INTO system_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("isss", $user_id, $action, $details, $ip);
    return $stmt->execute();
}

// Get user role name
function getUserRole($user_id) {
    global $db;
    $sql = "SELECT r.role_name FROM users u 
            JOIN roles r ON u.role_id = r.id 
            WHERE u.id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['role_name'];
    }
    return null;
}

// ============================================
// STOCK MANAGEMENT FUNCTIONS
// ============================================

// Get current stock of an item
function getCurrentStock($item_id) {
    global $db;
    $sql = "SELECT current_stock FROM inventory_items WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['current_stock'];
    }
    return 0;
}

// Update stock movement
function updateStock($item_id, $quantity, $type, $user_id, $reference = null) {
    global $db;
    
    $db->begin_transaction();
    
    try {
        // Get current stock
        $current = getCurrentStock($item_id);
        $new_stock = ($type == 'IN') ? $current + $quantity : $current - $quantity;
        
        if ($type == 'OUT' && $new_stock < 0) {
            throw new Exception("Insufficient stock!");
        }
        
        // Update inventory
        $sql = "UPDATE inventory_items SET current_stock = ? WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("ii", $new_stock, $item_id);
        $stmt->execute();
        
        // Record movement
        $sql = "INSERT INTO stock_movements (item_id, movement_type, quantity, reference_no, performed_by) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("isisi", $item_id, $type, $quantity, $reference, $user_id);
        $stmt->execute();
        
        // Check for low stock alert
        checkLowStockAlert($item_id);
        
        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollback();
        return false;
    }
}

// Check and create low stock alerts
function checkLowStockAlert($item_id) {
    global $db;
    
    $sql = "SELECT item_name, current_stock, minimum_stock FROM inventory_items WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $item = $result->fetch_assoc();
    
    if ($item && $item['current_stock'] <= $item['minimum_stock']) {
        // Check if alert already exists
        $sql = "SELECT id FROM alerts WHERE item_id = ? AND is_read = 0";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            $message = "⚠️ " . $item['item_name'] . " ina stock " . $item['current_stock'] . 
                       " (minimum: " . $item['minimum_stock'] . "). Tafadhali reorder!";
            $sql = "INSERT INTO alerts (item_id, message) VALUES (?, ?)";
            $stmt = $db->prepare($sql);
            $stmt->bind_param("is", $item_id, $message);
            $stmt->execute();
        }
    }
}

// Get unread alerts count
function getUnreadAlertsCount() {
    global $db;
    $sql = "SELECT COUNT(*) as count FROM alerts WHERE is_read = 0";
    $result = $db->query($sql);
    $row = $result->fetch_assoc();
    return $row['count'];
}

// Get all active low stock items
function getLowStockItems() {
    global $db;
    $sql = "SELECT * FROM inventory_items WHERE current_stock <= minimum_stock AND status = 'active'";
    $result = $db->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}

// ============================================
// DASHBOARD STATS FUNCTIONS
// ============================================

// Get dashboard stats based on role
function getDashboardStats($role, $user_id = null) {
    global $db;
    $stats = [];
    
    if ($role == 'Admin' || $role == 'Hotel Manager') {
        // Total items
        $result = $db->query("SELECT COUNT(*) as count FROM inventory_items WHERE status = 'active'");
        $stats['total_items'] = $result->fetch_assoc()['count'];
        
        // Low stock items
        $result = $db->query("SELECT COUNT(*) as count FROM inventory_items WHERE current_stock <= minimum_stock");
        $stats['low_stock'] = $result->fetch_assoc()['count'];
        
        // Pending POs
        $result = $db->query("SELECT COUNT(*) as count FROM purchase_orders WHERE status = 'pending'");
        $stats['pending_po'] = $result->fetch_assoc()['count'];
        
        // Total suppliers
        $result = $db->query("SELECT COUNT(*) as count FROM suppliers WHERE status = 'active'");
        $stats['total_suppliers'] = $result->fetch_assoc()['count'];
    }
    
    return $stats;
}

// ============================================
// PASSWORD RESET FUNCTIONS
// ============================================

// Generate password reset token
function generateResetToken($user_id) {
    global $db;
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    $sql = "UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("ssi", $token, $expires, $user_id);
    $stmt->execute();
    
    return $token;
}

// Verify reset token
function verifyResetToken($token) {
    global $db;
    $sql = "SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW() AND status = 'active'";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($user = $result->fetch_assoc()) {
        return $user['id'];
    }
    return false;
}

// Clear reset token after password change
function clearResetToken($user_id) {
    global $db;
    $sql = "UPDATE users SET reset_token = NULL, reset_expires = NULL WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $user_id);
    return $stmt->execute();
}
?>