<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/db.php';

header('Content-Type: application/json');

// Ensure user is logged in and is an admin
if (!isLoggedIn() || !isAdmin()) {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$conn = getDbConnection();

// Validate input
if (!isset($_POST['type']) || !isset($_POST['id']) || !isset($_POST['action']) || 
    !is_numeric($_POST['id']) || 
    !in_array($_POST['type'], ['listing', 'user', 'rating']) || 
    !in_array($_POST['action'], ['approve', 'reject', 'block', 'unblock'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid parameters'
    ]);
    exit();
}

$type = $_POST['type'];
$id = (int)$_POST['id'];
$action = $_POST['action'];

try {
    $stmt = null;
    
    switch ($type) {
        case 'listing':
            if ($action === 'approve') {
                $stmt = prepareStatement("UPDATE listing SET admin_approved = 1, listing_status = 'active' WHERE listing_id = ?");
            } else {
                $stmt = prepareStatement("UPDATE listing SET admin_approved = 0, listing_status = 'inactive' WHERE listing_id = ?");
            }
            $stmt->bind_param("i", $id);
            break;

        case 'user':
            if ($action === 'unblock') {
                $stmt = prepareStatement("UPDATE user SET is_blocked = 0 WHERE user_id = ?");
            } else {
                $stmt = prepareStatement("UPDATE user SET is_blocked = 1 WHERE user_id = ?");
            }
            $stmt->bind_param("i", $id);
            break;

        case 'rating':
            if ($action === 'approve') {
                $stmt = prepareStatement("UPDATE rating SET admin_approved = 1 WHERE rating_id = ?");
            } else {
                // Check if rating exists before deleting
                $check_stmt = prepareStatement("SELECT rating_id FROM rating WHERE rating_id = ?");
                $check_stmt->bind_param("i", $id);
                $check_stmt->execute();
                $result = $check_stmt->get_result();
                $check_stmt->close();

                if ($result->num_rows === 0) {
                    throw new Exception('Rating not found');
                }

                $stmt = prepareStatement("DELETE FROM rating WHERE rating_id = ?");
            }
            $stmt->bind_param("i", $id);
            break;
    }

    if ($stmt) {
        $stmt->execute();
        $affected_rows = $stmt->affected_rows;
        $stmt->close();

        if ($affected_rows === 0) {
            throw new Exception('No records were updated');
        }

        echo json_encode([
            'success' => true,
            'message' => ucfirst($type) . ' ' . $action . 'ed successfully'
        ]);
    } else {
        throw new Exception('Invalid operation');
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 