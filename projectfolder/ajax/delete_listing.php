<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

// Set the content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Not authenticated'
    ]);
    exit();
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
$user_id = $_SESSION['user_id'];

$conn = getDbConnection();
$conn->begin_transaction();

try {
    // Check if the listing belongs to the user
    $stmt = prepareStatement("SELECT user_id FROM listing WHERE listing_id = ?");
    $stmt->bind_param("i", $listing_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $listing = $result->fetch_assoc();
    $stmt->close();

    if (!$listing || $listing['user_id'] != $user_id) {
        throw new Exception('Unauthorized access');
    }

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
    $stmt = prepareStatement("DELETE FROM listing WHERE listing_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $listing_id, $user_id);
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