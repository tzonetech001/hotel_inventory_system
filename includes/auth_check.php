<?php
require_once 'session.php';
require_once 'functions.php';

function checkAuth($allowed_roles = []) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: " . APP_URL . "/auth/login.php");
        exit();
    }
    
    $user_role = $_SESSION['role'];
    
    if (!empty($allowed_roles) && !in_array($user_role, $allowed_roles)) {
        header("Location: " . APP_URL . "/dashboard.php");
        exit();
    }
    
    return true;
}

function hasPermission($permission) {
    // Simple permission check based on role
    $role_permissions = [
        'Admin' => ['all'],
        'Hotel Manager' => ['view_reports', 'approve_po', 'view_suppliers'],
        'Storekeeper' => ['manage_inventory', 'stock_in', 'stock_out'],
        'Procurement Officer' => ['create_po', 'view_po', 'track_delivery'],
        'Supplier' => ['view_orders', 'update_delivery']
    ];
    
    $user_role = $_SESSION['role'];
    
    if ($permission == 'all' && $user_role == 'Admin') return true;
    if (in_array($permission, $role_permissions[$user_role])) return true;
    
    return false;
}
?>