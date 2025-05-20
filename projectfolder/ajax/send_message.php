<?php
require_once '../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please login first.']);
    exit();
}

$receiver_id = $_POST['receiver_id'] ?? null;
$text = trim($_POST['text'] ?? '');

if (!$receiver_id || !$text) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Receiver ID and message text are required.']);
    exit();
}

$sender_id = getCurrentUserId();
$conn = getDbConnection();

// Insert message
$stmt = prepareStatement("
    INSERT INTO message (sender_id, receiver_id, text, timestamp) 
    VALUES (?, ?, ?, NOW())
");

$stmt->bind_param("iis", $sender_id, $receiver_id, $text);
$success = $stmt->execute();
$message_id = $success ? $stmt->insert_id : null;

echo json_encode([
    'success' => $success,
    'message_id' => $message_id
]); 