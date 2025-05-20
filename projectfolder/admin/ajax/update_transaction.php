<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/db.php';

// Ensure user is admin
if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get and validate input
$transaction_id = $_POST['transaction_id'] ?? null;
$status = $_POST['status'] ?? null;
$courier_name = $_POST['courier_name'] ?? null;
$tracking_code = $_POST['tracking_code'] ?? null;

// Validate required fields
if (!$transaction_id || !$status) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

// Validate status
$valid_statuses = ['paid', 'shipped', 'delivered', 'cancelled', 'completed'];
if (!in_array($status, $valid_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit();
}

// If status is shipped, require shipping information
if ($status === 'shipped' && (!$courier_name || !$tracking_code)) {
    echo json_encode(['success' => false, 'message' => 'Shipping information required']);
    exit();
}

try {
    // Start transaction
    $conn = getDbConnection();
    $conn->begin_transaction();

    // Update transaction
    $sql = "UPDATE transaction SET transaction_status = ?";
    $params = [$status];
    $types = 's';

    // Add shipping information if provided
    if ($status === 'shipped') {
        $sql .= ", courier_name = ?, tracking_code = ?";
        $params[] = $courier_name;
        $params[] = $tracking_code;
        $types .= 'ss';
    }

    $sql .= " WHERE transaction_id = ?";
    $params[] = $transaction_id;
    $types .= 'i';

    $stmt = prepareStatement($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $conn->commit();
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('No transaction found or no changes made');
    }
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 