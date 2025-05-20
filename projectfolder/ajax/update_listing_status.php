<?php
require_once '../config.php';
require_once '../includes/auth.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

// Ensure user is logged in
if (!isLoggedIn()) {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

// Validate input
if (!isset($_POST['listing_id']) || !isset($_POST['status'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$listing_id = (int)$_POST['listing_id'];
$status = $_POST['status'];
$user_id = getCurrentUserId();

// Validate status
$valid_statuses = ['active', 'inactive', 'sold'];
if (!in_array($status, $valid_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit();
}

try {
    $conn = getDbConnection();
    $conn->begin_transaction();

    // Verify ownership
    $stmt = prepareStatement("SELECT user_id FROM listing WHERE listing_id = ?");
    $stmt->bind_param("i", $listing_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if (!$result || $result['user_id'] !== $user_id) {
        throw new Exception('Unauthorized: You can only update your own listings');
    }

    // Update listing status
    $stmt = prepareStatement("UPDATE listing SET listing_status = ? WHERE listing_id = ? AND user_id = ?");
    $stmt->bind_param("sii", $status, $listing_id, $user_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $conn->commit();
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('No listing found or no changes made');
    }
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 