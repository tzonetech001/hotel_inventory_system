<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';

header('Content-Type: application/json');

$username = $_GET['username'] ?? '';
$user_id = intval($_GET['user_id'] ?? 0);

if (empty($username)) {
    echo json_encode(['available' => false]);
    exit();
}

$sql = "SELECT id FROM users WHERE username = ? AND id != ?";
$stmt = $db->prepare($sql);
$stmt->bind_param("si", $username, $user_id);
$stmt->execute();
$result = $stmt->get_result();

echo json_encode(['available' => $result->num_rows === 0]);
?>