<?php
require_once '../config.php';
require_once '../includes/auth.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

// Ensure user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$image_id = $_POST['image_id'] ?? null;
$listing_id = $_POST['listing_id'] ?? null;

if (!$image_id || !$listing_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Image ID and Listing ID are required']);
    exit();
}

try {
    $conn = getDbConnection();
    
    // Verify user owns the listing or is admin
    $stmt = $conn->prepare("
        SELECT user_id 
        FROM listing 
        WHERE listing_id = ?
    ");
    $stmt->bind_param("i", $listing_id);
    $stmt->execute();
    $listing = $stmt->get_result()->fetch_assoc();

    if (!$listing || ($listing['user_id'] !== $_SESSION['user_id'] && !isAdmin())) {
        throw new Exception('You do not have permission to delete this image');
    }

    // Get image path before deletion
    $stmt = $conn->prepare("SELECT image_url FROM listing_image WHERE image_id = ? AND listing_id = ?");
    $stmt->bind_param("ii", $image_id, $listing_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if (!$result) {
        throw new Exception('Image not found');
    }

    // Start transaction
    $conn->begin_transaction();

    // Delete image record
    $stmt = $conn->prepare("DELETE FROM listing_image WHERE image_id = ? AND listing_id = ?");
    $stmt->bind_param("ii", $image_id, $listing_id);
    $stmt->execute();

    // Delete physical file
    $file_path = __DIR__ . '/../' . $result['image_url'];
    if (file_exists($file_path)) {
        unlink($file_path);
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Image deleted successfully'
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollback();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
} 