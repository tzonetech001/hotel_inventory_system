<?php
/**
 * Hotel Inventory System - Main Dashboard Redirect
 * 
 * This file acts as the main entry point after login.
 * It redirects users to their respective dashboards based on their role.
 * 
 * User Types:
 * - Staff Users: Admin, Hotel Manager, Storekeeper, Procurement Officer
 * - Supplier Users: Suppliers (from suppliers table)
 */

// Load required configuration files
require_once 'includes/config.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
require_once 'includes/auth_check.php';

// ============================================
// STEP 1: CHECK IF USER IS LOGGED IN
// ============================================
if (!isset($_SESSION['user_id'])) {
    // User is not logged in - redirect to login page
    header("Location: auth/login.php");
    exit();
}

// ============================================
// STEP 2: GET USER INFORMATION
// ============================================
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';
$user_type = $_SESSION['user_type'] ?? 'staff';

// ============================================
// STEP 3: REDIRECT BASED ON USER TYPE AND ROLE
// ============================================

// For staff users (from users table)
if ($user_type == 'staff') {
    switch($role) {
        case 'Admin':
            // Admin has full system access
            header("Location: admin/dashboard.php");
            break;
            
        case 'Hotel Manager':
            // Manager can view reports, approve POs, manage suppliers
            header("Location: manager/dashboard.php");
            break;
            
        case 'Storekeeper':
            // Storekeeper manages inventory (stock in/out, add items)
            header("Location: storekeeper/dashboard.php");
            break;
            
        case 'Procurement Officer':
            // Procurement Officer creates and manages purchase orders
            header("Location: procurement/dashboard.php");
            break;
            
        default:
            // Unknown role - logout for security
            header("Location: auth/logout.php");
            break;
    }
} 
// For supplier users (from suppliers table)
elseif ($user_type == 'supplier') {
    // Supplier can view their orders
    header("Location: supplier/dashboard.php");
} 
else {
    // Unknown user type - logout for security
    header("Location: auth/logout.php");
}

// Exit to ensure no further code execution
exit();
?>