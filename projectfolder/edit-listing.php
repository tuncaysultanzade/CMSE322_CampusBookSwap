<?php
require_once 'config.php';
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/image_helper.php';

// Ensure user is logged in
requireLogin();

$listing_id = intval($_GET['id'] ?? 0);
$error = '';
$success = false;

if (!$listing_id) {
    header('Location: dashboard.php');
    exit();
}

// Get listing details
$stmt = prepareStatement("
    SELECT l.*, b.title as book_title, b.author, b.publisher, b.publisher_year
    FROM listing l
    JOIN book b ON l.book_id = b.book_id
    WHERE l.listing_id = ? AND l.user_id = ?
");
$stmt->bind_param("ii", $listing_id, getCurrentUserId());
$stmt->execute();
$result = $stmt->get_result();
$listing = $result->fetch_assoc();

if (!$listing) {
    header('Location: dashboard.php');
    exit();
}

// Get listing images
$stmt = prepareStatement("SELECT * FROM listing_image WHERE listing_id = ?");
$stmt->bind_param("i", $listing_id);
$stmt->execute();
$images = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Start transaction
        $conn = getDbConnection();
        $conn->begin_transaction();

        // Update listing information
        $stmt = prepareStatement("
            UPDATE listing 
            SET price = ?, listing_type = ?, description = ?
            WHERE listing_id = ? AND user_id = ?
        ");
        $price = $_POST['listing_type'] === 'sale' ? $_POST['price'] : 0;
        $stmt->bind_param("dssis",
            $price,
            $_POST['listing_type'],
            $_POST['description'],
            $listing_id,
            getCurrentUserId()
        );
        $stmt->execute();

        // Handle image uploads if any
        if (!empty($_FILES['images']['name'][0])) {
            $upload_dir = UPLOAD_DIR . 'listings/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                try {
                    // Validate image
                    validateImage([
                        'tmp_name' => $tmp_name,
                        'size' => $_FILES['images']['size'][$key],
                        'error' => $_FILES['images']['error'][$key],
                        'name' => $_FILES['images']['name'][$key]
                    ]);

                    // Generate unique filename
                    $ext = pathinfo($_FILES['images']['name'][$key], PATHINFO_EXTENSION);
                    $filename = uniqid('book_') . '.' . $ext;
                    $filepath = $upload_dir . $filename;
                    
                    // Compress and save image
                    if (compressAndSaveImage($tmp_name, $filepath)) {
                        $stmt = prepareStatement("INSERT INTO listing_image (listing_id, image_url) VALUES (?, ?)");
                        $image_url = 'uploads/listings/' . $filename;
                        $stmt->bind_param("is", $listing_id, $image_url);
                        $stmt->execute();
                    }
                } catch (Exception $e) {
                    throw new Exception('Error processing image ' . ($_FILES['images']['name'][$key]) . ': ' . $e->getMessage());
                }
            }
        }

        $conn->commit();
        $success = true;
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Listing updated successfully.'];
        header('Location: listing.php?id=' . $listing_id);
        exit();

    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollback();
        }
        $error = $e->getMessage();
    }
}

include 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Edit Listing</h4>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data">
                        <h5 class="mb-3">Book Information</h5>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Title</label>
                                <input type="text" class="form-control" 
                                       value="<?php echo htmlspecialchars($listing['book_title'] ?? ''); ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Author</label>
                                <input type="text" class="form-control" 
                                       value="<?php echo htmlspecialchars($listing['author'] ?? ''); ?>" readonly>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Publisher</label>
                                <input type="text" class="form-control" 
                                       value="<?php echo htmlspecialchars($listing['publisher'] ?? ''); ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Publisher Year</label>
                                <input type="text" class="form-control" 
                                       value="<?php echo htmlspecialchars($listing['publisher_year'] ?? ''); ?>" readonly>
                            </div>
                        </div>

                        <h5 class="mb-3 mt-4">Listing Details</h5>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Listing Type</label>
                                <select class="form-select" name="listing_type" id="listingType" required>
                                    <option value="sale" <?php echo ($listing['listing_type'] ?? '') === 'sale' ? 'selected' : ''; ?>>
                                        For Sale
                                    </option>
                                    <option value="exchange" <?php echo ($listing['listing_type'] ?? '') === 'exchange' ? 'selected' : ''; ?>>
                                        For Exchange
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-6" id="priceField">
                                <label class="form-label">Price (₺)</label>
                                <input type="number" class="form-control" name="price" step="0.01" min="0"
                                       value="<?php echo htmlspecialchars($listing['price'] ?? '0.00'); ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Condition</label>
                            <select class="form-select" name="condition" required>
                                <?php
                                $conditions = ['new', 'like new', 'very good', 'good', 'fair', 'poor'];
                                foreach ($conditions as $condition):
                                    $selected = ($condition === ($listing['condition'] ?? '')) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo $condition; ?>" <?php echo $selected; ?>>
                                        <?php echo ucwords($condition); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="4" required><?php 
                                echo htmlspecialchars($listing['description'] ?? ''); 
                            ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Add More Images</label>
                            <input type="file" class="form-control" name="images[]" 
                                   accept="image/jpeg,image/png" multiple
                                   onchange="validateFileSize(this)">
                            <small class="text-muted">
                                Allowed types: <?php echo implode(', ', ALLOWED_EXTENSIONS); ?>. 
                                Maximum size: 5MB per image
                            </small>
                        </div>

                        <?php if ($images): ?>
                            <div class="mb-3">
                                <label class="form-label">Current Images</label>
                                <div class="row" id="currentImages">
                                    <?php foreach ($images as $image): ?>
                                        <div class="col-md-3 mb-2 image-container" id="image-<?php echo $image['image_id']; ?>">
                                            <div class="position-relative">
                                                <img src="<?php echo htmlspecialchars($image['image_url']); ?>" 
                                                     class="img-thumbnail" alt="Listing image">
                                                <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0 m-1 delete-image"
                                                        data-image-id="<?php echo $image['image_id']; ?>">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="text-end">
                            <a href="listing.php?id=<?php echo $listing_id; ?>" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Update Listing</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Toggle price field based on listing type
    $('#listingType').change(function() {
        if ($(this).val() === 'exchange') {
            $('#priceField').hide();
            $('[name="price"]').val(0);
        } else {
            $('#priceField').show();
        }
    }).trigger('change');

    // Handle image deletion
    $('.delete-image').click(function(e) {
        e.preventDefault();
        const button = $(this);
        const imageId = button.data('image-id');
        const container = button.closest('.image-container');

        if (confirm('Are you sure you want to delete this image?')) {
            $.ajax({
                url: '/ajax/delete_image.php',
                method: 'POST',
                data: {
                    image_id: imageId,
                    listing_id: <?php echo $listing_id; ?>
                },
                success: function(response) {
                    if (response.success) {
                        container.fadeOut(300, function() {
                            $(this).remove();
                            if ($('#currentImages .image-container').length === 0) {
                                $('#currentImages').closest('.mb-3').remove();
                            }
                        });
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('Error deleting image. Please try again.');
                }
            });
        }
    });
});

function validateFileSize(input) {
    const maxSize = 5 * 1024 * 1024; // 5MB in bytes
    const files = input.files;
    
    for (let i = 0; i < files.length; i++) {
        if (files[i].size > maxSize) {
            alert('Error: Image file size must be less than 5MB. Please resize your image or choose a smaller one.');
            input.value = ''; // Clear the input
            return;
        }
    }
}
</script>

<?php include 'includes/footer.php'; ?> 