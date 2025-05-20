<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Not authenticated'
    ]);
    exit();
}

try {
    $user_id = $_SESSION['user_id'];

    // Get total unread messages count
    $stmt = prepareStatement("
        SELECT COUNT(*) as unread_count
        FROM message 
        WHERE receiver_id = ? AND is_read = 0
    ");
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    echo json_encode([
        'success' => true,
        'unreadCount' => (int)$result['unread_count']
    ]);
} catch (Exception $e) {
    error_log("Error in get_unread_count.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching unread count'
    ]);
}
?> 