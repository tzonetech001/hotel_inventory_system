<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];

$sql = "SELECT fullname, username, profile_picture, role_id FROM users WHERE id = ?";
$stmt = $db->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit();
}

// Get role name
$role_sql = "SELECT role_name FROM roles WHERE id = ?";
$role_stmt = $db->prepare($role_sql);
$role_stmt->bind_param("i", $user['role_id']);
$role_stmt->execute();
$role_result = $role_stmt->get_result();
$role = $role_result->fetch_assoc();

// Build profile picture URL
$profile_pic_url = '';
$initials = '';

// Generate initials
$name_parts = explode(' ', $user['fullname']);
$initials = strtoupper(substr($name_parts[0], 0, 1));
if (isset($name_parts[1])) {
    $initials .= strtoupper(substr($name_parts[1], 0, 1));
} else if (strlen($name_parts[0]) > 1) {
    $initials .= strtoupper(substr($name_parts[0], 1, 1));
} else {
    $initials .= strtoupper(substr($user['fullname'], 0, 1));
}

if (!empty($user['profile_picture'])) {
    // Check multiple possible paths
    $doc_root = $_SERVER['DOCUMENT_ROOT'];
    $possible_paths = [
        $doc_root . '/hotel_inventory/uploads/profile_pictures/' . $user['profile_picture'],
        $doc_root . '../uploads/profile_pictures/' . $user['profile_picture']
    ];
    
    foreach ($possible_paths as $path) {
        if (file_exists($path)) {
            $profile_pic_url = APP_URL . '../uploads/profile_pictures/' . $user['profile_picture'];
            break;
        }
    }
}

echo json_encode([
    'success' => true,
    'fullname' => $user['fullname'],
    'username' => $user['username'],
    'role' => $role['role_name'] ?? 'User',
    'profile_picture_url' => $profile_pic_url,
    'initials' => $initials
]);
?>