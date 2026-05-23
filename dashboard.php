<?php
require_once 'includes/config.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
require_once 'includes/auth_check.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Redirect based on role to their specific dashboard
switch($role) {
    case 'Admin':
        header("Location: admin/dashboard.php");
        break;
    case 'Hotel Manager':
        header("Location: manager/dashboard.php");
        break;
    case 'Storekeeper':
        header("Location: storekeeper/dashboard.php");
        break;
    case 'Procurement Officer':
        header("Location: procurement/dashboard.php");
        break;
    case 'Supplier':
        header("Location: supplier/dashboard.php");
        break;
    default:
        header("Location: auth/logout.php");
}
exit();
?>