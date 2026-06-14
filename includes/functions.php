<?php
require_once 'db_connection.php';

// ============================================
// LOGGING FUNCTIONS - Fixed for all user types
// ============================================

/**
 * Log system activity for all user types
 * 
 * @param int $user_id User ID (can be 0 for system actions)
 * @param string $action Action performed
 * @param string|null $details Additional details
 * @param string $user_type Type of user ('staff', 'supplier', 'department', 'system')
 * @return bool Success status
 */
function logActivity($user_id, $action, $details = null, $user_type = 'staff') {
    global $db;
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    // For staff users (from users table) - insert into system_logs
    if ($user_type == 'staff' && $user_id > 0) {
        // Check if user exists in users table
        $check_sql = "SELECT id FROM users WHERE id = ?";
        $check_stmt = $db->prepare($check_sql);
        $check_stmt->bind_param("i", $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $sql = "INSERT INTO system_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)";
            $stmt = $db->prepare($sql);
            $stmt->bind_param("isss", $user_id, $action, $details, $ip);
            return $stmt->execute();
        } else {
            // User doesn't exist, log to file instead
            return logToFile($action, $details, $ip, $user_type);
        }
    }
    
    // For suppliers, department users, or system actions - log to file
    return logToFile($action, $details, $ip, $user_type);
}

/**
 * Log to file for non-staff users or when database insert fails
 */
function logToFile($action, $details, $ip, $user_type = 'system') {
    // Create logs directory if not exists
    $log_dir = dirname(__DIR__) . '/logs';
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0777, true);
    }
    
    $log_file = $log_dir . '/activity.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [$user_type] [$action] " . ($details ?? '') . " - IP: $ip\n";
    
    return error_log($log_entry, 3, $log_file);
}

// ============================================
// Alternative: Modify database to accept NULL user_id
// Run these SQL queries to fix permanently:
// ============================================
// ALTER TABLE system_logs MODIFY COLUMN user_id INT NULL;
// ALTER TABLE system_logs DROP FOREIGN KEY system_logs_ibfk_1;
// ALTER TABLE system_logs ADD CONSTRAINT system_logs_ibfk_1 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;
// ============================================

// ============================================
// USER ROLE FUNCTIONS
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
// DASHBOARD STATS
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
        
        // Total staff users
        $result = $db->query("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
        $stats['total_staff'] = $result->fetch_assoc()['count'];
        
        // Total department users
        $result = $db->query("SELECT COUNT(*) as count FROM department_users WHERE status = 'active'");
        $stats['total_department_users'] = $result->fetch_assoc()['count'];
    }
    
    return $stats;
}

// ============================================
// PASSWORD RESET FUNCTIONS (For Staff & Suppliers)
// ============================================

// Generate password reset token
function generateResetToken($user_id, $user_type = 'staff') {
    global $db;
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    if ($user_type == 'staff') {
        $sql = "UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?";
    } elseif ($user_type == 'supplier') {
        $sql = "UPDATE suppliers SET reset_token = ?, reset_expires = ? WHERE id = ?";
    } else {
        $sql = "UPDATE department_users SET reset_token = ?, reset_expires = ? WHERE id = ?";
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
    } elseif ($user_type == 'supplier') {
        $sql = "SELECT id FROM suppliers WHERE reset_token = ? AND reset_expires > NOW() AND status = 'active'";
    } else {
        $sql = "SELECT id FROM department_users WHERE reset_token = ? AND reset_expires > NOW() AND status = 'active'";
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
    } elseif ($user_type == 'supplier') {
        $sql = "UPDATE suppliers SET reset_token = NULL, reset_expires = NULL WHERE id = ?";
    } else {
        $sql = "UPDATE department_users SET reset_token = NULL, reset_expires = NULL WHERE id = ?";
    }
    
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $user_id);
    return $stmt->execute();
}

// ============================================
// DEPARTMENT USER FUNCTIONS
// ============================================

// Get department user by ID
function getDepartmentUser($user_id) {
    global $db;
    $sql = "SELECT du.*, d.department_name 
            FROM department_users du
            JOIN departments d ON du.department_id = d.id
            WHERE du.id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Verify department user credentials
function verifyDepartmentUser($email, $password) {
    global $db;
    $sql = "SELECT du.*, d.department_name, d.department_code 
            FROM department_users du
            JOIN departments d ON du.department_id = d.id
            WHERE du.email = ? AND du.status = 'active'";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if ($user && password_verify($password, $user['password'])) {
        return $user;
    }
    return false;
}

// ============================================
// SUPPLIER FUNCTIONS
// ============================================

// Verify supplier credentials
function verifySupplier($email, $password) {
    global $db;
    $sql = "SELECT * FROM suppliers WHERE email = ? AND status = 'active'";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $supplier = $result->fetch_assoc();
    
    if ($supplier && password_verify($password, $supplier['password'])) {
        return $supplier;
    }
    return false;
}

// ============================================
// HELPER FUNCTIONS
// ============================================

// Sanitize input
function sanitize($input) {
    global $db;
    return htmlspecialchars(strip_tags(trim($input)));
}

// Redirect with message
function redirectWithMessage($url, $message, $type = 'success') {
    $_SESSION['toast_message'] = $message;
    $_SESSION['toast_type'] = $type;
    header("Location: " . $url);
    exit();
}

// Format currency
function formatCurrency($amount) {
    return 'TZS ' . number_format($amount, 2);
}

// Format date
function formatDate($date, $format = 'd M Y') {
    if (empty($date)) return '-';
    return date($format, strtotime($date));
}

// Get stock status class
function getStockStatusClass($current, $min, $max) {
    if ($current <= $min) return 'danger';
    if ($current >= $max) return 'warning';
    return 'success';
}

// Get stock status text
function getStockStatusText($current, $min, $max) {
    if ($current <= $min) return 'Critical - Low Stock';
    if ($current >= $max) return 'Over Stocked';
    return 'Normal';
}
?>