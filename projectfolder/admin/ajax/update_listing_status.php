<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/db.php';

header('Content-Type: application/json');

// Ensure user is logged in and is an admin
if (!isLoggedIn() || !isAdmin()) {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

// Validate input
if (!isset($_POST['listing_id']) || !isset($_POST['status'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$listing_id = (int)$_POST['listing_id'];
$status = $_POST['status'];

// Validate status
$valid_statuses = ['active', 'inactive', 'sold'];
if (!in_array($status, $valid_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit();
}

try {
    $conn = getDbConnection();
    $conn->begin_transaction();

    // Update listing status
    $stmt = prepareStatement("UPDATE listing SET listing_status = ? WHERE listing_id = ?");
    $stmt->bind_param("si", $status, $listing_id);
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