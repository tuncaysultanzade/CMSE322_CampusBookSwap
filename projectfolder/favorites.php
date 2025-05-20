<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$page_title = "My Favorites";
require_once 'includes/header.php';

// Get user's favorite listings with book and seller information
$sql = "SELECT l.*, b.title as book_title, b.author, u.name as seller_name, 
               DATE_FORMAT(l.list_date, '%M %d, %Y') as formatted_date,
               (SELECT image_url FROM listing_image WHERE listing_id = l.listing_id LIMIT 1) as image_url
        FROM favorite f
        JOIN listing l ON f.listing_id = l.listing_id
        JOIN book b ON l.book_id = b.book_id
        JOIN user u ON l.user_id = u.user_id
        WHERE f.user_id = ? AND l.listing_status != 'inactive' AND l.listing_status != 'sold'
        ORDER BY l.list_date DESC";

try {
    $stmt = prepareStatement($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
} catch (Exception $e) {
    error_log("Error in favorites.php: " . $e->getMessage());
    echo '<div class="container mt-4"><div class="alert alert-danger">An error occurred while fetching your favorites. Please try again later.</div></div>';
    require_once 'includes/footer.php';
    exit();
}
?>

<div class="container mt-4">
    <h1 class="mb-4">My Favorites</h1>
    
    <?php if ($result->num_rows === 0): ?>
    <div class="alert alert-info">
        <p>You haven't added any listings to your favorites yet.</p>
        <a href="index.php" class="btn btn-primary">Browse Listings</a>
    </div>
    <?php else: ?>
    <div class="row">
        <?php while ($listing = $result->fetch_assoc()): ?>
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <a href="listing.php?id=<?php echo $listing['listing_id']; ?>" class="text-decoration-none text-dark">
                    <?php if ($listing['image_url']): ?>
                        <img src="<?php echo htmlspecialchars($listing['image_url']); ?>" 
                             class="card-img-top" alt="<?php echo htmlspecialchars($listing['book_title']); ?>"
                             style="height: 200px; object-fit: cover;">
                    <?php else: ?>
                        <div class="card-img-top bg-secondary text-white d-flex align-items-center justify-content-center" 
                             style="height: 200px;">
                            <i class="fas fa-book fa-3x"></i>
                        </div>
                    <?php endif; ?>
                    
                    <div class="card-body">
                        <h5 class="card-title">
                            <?php echo htmlspecialchars($listing['book_title']); ?>
                            <?php if ($listing['listing_status'] === 'active' && $listing['admin_approved']): ?>
                                <span class="badge bg-success">Active</span>
                            <?php elseif ($listing['listing_status'] === 'active' && !$listing['admin_approved']): ?>
                                <span class="badge bg-warning">Pending Approval</span>
                            <?php endif; ?>
                        </h5>
                        <p class="card-text">
                            <small class="text-muted">By <?php echo htmlspecialchars($listing['author']); ?></small><br>
                            <small class="text-muted">Listed on: <?php echo htmlspecialchars($listing['formatted_date']); ?></small>
                        </p>
                        
                        <?php if ($listing['listing_type'] === 'sale'): ?>
                        <p class="card-text">
                            <strong>Price: <?php echo number_format($listing['price'], 2); ?> ₺</strong>
                        </p>
                        <?php else: ?>
                        <p class="card-text">
                            <strong>For Exchange</strong>
                            <?php if ($listing['description']): ?>
                                <br><small class="text-muted">Exchange for: <?php echo htmlspecialchars($listing['description']); ?></small>
                            <?php endif; ?>
                        </p>
                        <?php endif; ?>
                        
                        <p class="card-text">
                            <small class="text-muted">Condition: <?php echo ucfirst(htmlspecialchars($listing['condition'])); ?></small>
                        </p>
                    </div>
                </a>
                
                <div class="card-footer bg-white border-top-0">
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">
                            Seller: <?php echo htmlspecialchars($listing['seller_name']); ?>
                        </small>
                        <button class="btn btn-danger btn-sm remove-favorite" 
                                data-listing-id="<?php echo $listing['listing_id']; ?>">
                            <i class="fas fa-heart-broken"></i> Remove
                            <span class="spinner-border spinner-border-sm d-none" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
    <?php endif; ?>
</div>

<script>
$(document).ready(function() {
    // Handle removing favorites
    $('.remove-favorite').click(function() {
        const button = $(this);
        const listingId = button.data('listing-id');
        const card = button.closest('.col-md-4');
        
        // Disable button and show loading spinner
        button.prop('disabled', true);
        button.find('.fa-heart-broken').addClass('d-none');
        button.find('.spinner-border').removeClass('d-none');
        
        $.ajax({
            url: 'ajax/toggle_favorite.php',
            type: 'POST',
            data: {
                listing_id: listingId
            },
            dataType: 'json',
            success: function(data) {
                if (data.success) {
                    // Animate and remove the card
                    card.fadeOut(400, function() {
                        $(this).remove();
                        // If no more favorites, reload the page to show the empty state
                        if ($('.col-md-4').length === 0) {
                            location.reload();
                        }
                    });
                } else {
                    // Reset button state
                    button.prop('disabled', false);
                    button.find('.fa-heart-broken').removeClass('d-none');
                    button.find('.spinner-border').addClass('d-none');
                    // Show error
                    alert(data.message || 'Error removing from favorites. Please try again.');
                }
            },
            error: function(xhr, status, error) {
                // Reset button state
                button.prop('disabled', false);
                button.find('.fa-heart-broken').removeClass('d-none');
                button.find('.spinner-border').addClass('d-none');
                // Show error
                alert('Network error. Please check your connection and try again.');
            }
        });
    });
});
</script>

<?php
$stmt->close();
require_once 'includes/footer.php';
?> 