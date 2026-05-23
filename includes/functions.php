<?php
require_once 'db_connection.php';

// ============================================
// LOGGING FUNCTIONS (UPDATED - Handles both users and suppliers)
// ============================================

/**
 * Log system activity
 * 
 * @param int $user_id User ID (can be 0 for suppliers or system actions)
 * @param string $action Action performed
 * @param string|null $details Additional details
 * @param string $user_type Type of user ('staff' or 'supplier')
 * @return bool Success status
 */
function logActivity($user_id, $action, $details = null, $user_type = 'staff') {
    global $db;
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    // If user_id is 0 or we're logging for supplier, use a special handling
    if ($user_id == 0 || $user_type == 'supplier') {
        // For suppliers or system actions, we can either:
        // Option 1: Insert with user_id = NULL (requires altering table)
        // Option 2: Skip logging for suppliers
        // Option 3: Create a separate log table for suppliers
        
        // For now, we'll skip logging for suppliers to avoid foreign key errors
        // But we can still log to a file or create a separate table
        $log_file = dirname(__DIR__) . '/logs/activity.log';
        $log_entry = date('Y-m-d H:i:s') . " - [$action] " . ($details ?? '') . " - IP: $ip\n";
        error_log($log_entry, 3, $log_file);
        return true;
    }
    
    // For staff users, insert into system_logs table
    $sql = "INSERT INTO system_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("isss", $user_id, $action, $details, $ip);
    return $stmt->execute();
}

// Alternative: If you want to allow NULL user_id in system_logs, run this SQL:
// ALTER TABLE system_logs MODIFY COLUMN user_id INT NULL;
// ALTER TABLE system_logs DROP FOREIGN KEY system_logs_ibfk_1;
// ALTER TABLE system_logs ADD CONSTRAINT system_logs_ibfk_1 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;

// ============================================
// REST OF YOUR FUNCTIONS...
// ============================================

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

// Generate password reset token
function generateResetToken($user_id, $user_type = 'staff') {
    global $db;
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    if ($user_type == 'staff') {
        $sql = "UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?";
    } else {
        $sql = "UPDATE suppliers SET reset_token = ?, reset_expires = ? WHERE id = ?";
    }
    
    $stmt = $db->prepare($sql);
    $stmt->bind_param("ssi", $token, $expires, $user_id);
    $stmt->execute();
    
    return $token;
}

// Verify reset token
function verifyResetToken($token, $user_type = 'staff') {
    global $db;
    
    if ($user_type == 'staff') {
        $sql = "SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW() AND status = 'active'";
    } else {
        $sql = "SELECT id FROM suppliers WHERE reset_token = ? AND reset_expires > NOW() AND status = 'active'";
    }
    
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
function clearResetToken($user_id, $user_type = 'staff') {
    global $db;
    
    if ($user_type == 'staff') {
        $sql = "UPDATE users SET reset_token = NULL, reset_expires = NULL WHERE id = ?";
    } else {
        $sql = "UPDATE suppliers SET reset_token = NULL, reset_expires = NULL WHERE id = ?";
    }
    
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $user_id);
    return $stmt->execute();
}
?>