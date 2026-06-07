<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
session_start();

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? 0;

if ($user_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Get user data from database
$sql = "SELECT fullname, username, profile_picture FROM users WHERE id = ?";
$stmt = $db->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit();
}

$fullname = $user['fullname'];
$profile_pic = $user['profile_picture'];
$profile_pic_url = '';

// Check for profile picture
if (!empty($profile_pic)) {
    $doc_root = $_SERVER['DOCUMENT_ROOT'];
    $possible_paths = [
        $doc_root . '/hotel_inventory/uploads/profile_pictures/' . $profile_pic,
        $doc_root . '/uploads/profile_pictures/' . $profile_pic,
        dirname(__DIR__) . '/uploads/profile_pictures/' . $profile_pic,
        __DIR__ . '/../uploads/profile_pictures/' . $profile_pic
    ];
    
    foreach ($possible_paths as $path) {
        if (file_exists($path)) {
            $profile_pic_url = APP_URL . '/uploads/profile_pictures/' . $profile_pic;
            break;
        }
    }
}

// Get initials
$name_parts = explode(' ', $fullname);
$initials = strtoupper(substr($name_parts[0], 0, 1));
if (isset($name_parts[1])) {
    $initials .= strtoupper(substr($name_parts[1], 0, 1));
} else if (strlen($name_parts[0]) > 1) {
    $initials .= strtoupper(substr($name_parts[0], 1, 1));
} else {
    $initials .= strtoupper(substr($fullname, 0, 1));
}

echo json_encode([
    'success' => true,
    'fullname' => $fullname,
    'username' => $user['username'],
    'profile_picture_url' => $profile_pic_url,
    'initials' => $initials
]);
?>