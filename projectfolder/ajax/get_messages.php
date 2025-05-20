<?php
require_once '../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please login first.']);
    exit();
}

$thread_id = $_GET['thread_id'] ?? null;
$last_id = $_GET['last_id'] ?? 0;

if (!$thread_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Thread ID is required.']);
    exit();
}

$user_id = getCurrentUserId();
$conn = getDbConnection();

// Get new messages
$stmt = prepareStatement("
    SELECT 
        m.message_id,
        m.text,
        m.timestamp,
        m.sender_id = ? as is_mine
    FROM message m
    WHERE (m.sender_id = ? AND m.receiver_id = ?)
       OR (m.sender_id = ? AND m.receiver_id = ?)
    AND m.message_id > ?
    ORDER BY m.timestamp ASC
");

$stmt->bind_param("iiiiii", 
    $user_id,
    $user_id, $thread_id,
    $thread_id, $user_id,
    $last_id
);

$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = [
        'id' => $row['message_id'],
        'text' => htmlspecialchars($row['text']),
        'timestamp' => date('M j, Y g:i A', strtotime($row['timestamp'])),
        'is_mine' => (bool)$row['is_mine']
    ];
}

// Mark messages as read
if (!empty($messages)) {
    $stmt = prepareStatement("
        UPDATE message 
        SET is_read = 1 
        WHERE receiver_id = ? 
        AND sender_id = ?
    ");
    $stmt->bind_param("ii", $user_id, $thread_id);
    $stmt->execute();
}

echo json_encode([
    'success' => true,
    'messages' => $messages
]); 