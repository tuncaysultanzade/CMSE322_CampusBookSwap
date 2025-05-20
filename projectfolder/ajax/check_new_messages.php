<?php
require_once '../config.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please login first.']);
    exit();
}

$other_user_id = $_GET['other_user_id'] ?? null;
$last_timestamp = $_GET['last_timestamp'] ?? null;

if (!$other_user_id || !$last_timestamp) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Other user ID and last timestamp are required.']);
    exit();
}

$current_user_id = $_SESSION['user_id'];

// Check for new messages
$stmt = $conn->prepare("
    SELECT COUNT(*) as new_count
    FROM message 
    WHERE ((sender_id = ? AND receiver_id = ?) 
           OR (sender_id = ? AND receiver_id = ?))
    AND timestamp > ?
");

$stmt->bind_param("iiiss", 
    $current_user_id, $other_user_id,
    $other_user_id, $current_user_id,
    $last_timestamp
);

$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

echo json_encode([
    'success' => true,
    'hasNewMessages' => $result['new_count'] > 0,
    'newCount' => (int)$result['new_count']
]); 