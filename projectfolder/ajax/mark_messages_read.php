<?php
require_once '../config.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please login first.']);
    exit();
}

$sender_id = $_POST['sender_id'] ?? null;

if (!$sender_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Sender ID is required.']);
    exit();
}

$receiver_id = $_SESSION['user_id'];

// Mark messages as read
$stmt = $conn->prepare("
    UPDATE message 
    SET is_read = 1 
    WHERE receiver_id = ? AND sender_id = ? AND is_read = 0
");

$stmt->bind_param("ii", $receiver_id, $sender_id);
$success = $stmt->execute();

echo json_encode([
    'success' => $success,
    'affected_rows' => $stmt->affected_rows
]); 