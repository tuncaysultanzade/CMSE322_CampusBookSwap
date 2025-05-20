<?php
require_once '../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please login first.']);
    exit();
}

$listing_id = $_POST['listing_id'] ?? null;

if (!$listing_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Listing ID is required.']);
    exit();
}

$user_id = getCurrentUserId();
$conn = getDbConnection();

// Check if already favorited
$stmt = prepareStatement("SELECT 1 FROM favorite WHERE user_id = ? AND listing_id = ?");
$stmt->bind_param("ii", $user_id, $listing_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Remove favorite
    $stmt = prepareStatement("DELETE FROM favorite WHERE user_id = ? AND listing_id = ?");
    $stmt->bind_param("ii", $user_id, $listing_id);
    $success = $stmt->execute();
} else {
    // Add favorite
    $stmt = prepareStatement("INSERT INTO favorite (user_id, listing_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $user_id, $listing_id);
    $success = $stmt->execute();
}

echo json_encode(['success' => $success]); 