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
if (!isset($_POST['listing_id']) || !is_numeric($_POST['listing_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid listing ID'
    ]);
    exit();
}

$listing_id = (int)$_POST['listing_id'];
$conn = getDbConnection();
$conn->begin_transaction();

try {
    // Delete associated images
    $stmt = prepareStatement("DELETE FROM listing_image WHERE listing_id = ?");
    $stmt->bind_param("i", $listing_id);
    $stmt->execute();
    $stmt->close();

    // Delete favorites
    $stmt = prepareStatement("DELETE FROM favorite WHERE listing_id = ?");
    $stmt->bind_param("i", $listing_id);
    $stmt->execute();
    $stmt->close();

    // Delete the listing
    $stmt = prepareStatement("DELETE FROM listing WHERE listing_id = ?");
    $stmt->bind_param("i", $listing_id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Listing deleted successfully'
    ]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 