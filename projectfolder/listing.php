<?php
require_once 'includes/header.php';

$listing_id = intval($_GET['id'] ?? 0);

if (!$listing_id) {
    header('Location: index.php');
    exit();
}

// Get listing details with book info and seller details
$stmt = prepareStatement("
    SELECT 
        l.*,
        b.title as book_title,
        b.author,
        b.publisher,
        b.publisher_year,
        u.name as seller_name,
        u.email as seller_email,
        u.phone as seller_phone,
        COUNT(DISTINCT f.user_id) as favorite_count,
        EXISTS(
            SELECT 1 FROM favorite 
            WHERE user_id = ? AND listing_id = l.listing_id
        ) as is_favorited,
        EXISTS(
            SELECT 1 FROM transaction t 
            WHERE t.listing_id = l.listing_id 
            AND t.transaction_status IN ('paid', 'shipped', 'delivered', 'completed')
        ) as is_sold
    FROM listing l
    LEFT JOIN book b ON l.book_id = b.book_id
    LEFT JOIN user u ON l.user_id = u.user_id
    LEFT JOIN favorite f ON l.listing_id = f.listing_id
    WHERE l.listing_id = ? 
    AND (l.admin_approved = 1 OR l.user_id = ?)
    GROUP BY l.listing_id
");

$user_id = getCurrentUserId() ?? 0;
$stmt->bind_param("iii", $user_id, $listing_id, $user_id);
$stmt->execute();
$listing = $stmt->get_result()->fetch_assoc();

if (!$listing) {
    $_SESSION['flash'] = [
        'type' => 'danger',
        'message' => 'Listing not found or not approved yet.'
    ];
    header('Location: index.php');
    exit();
}

// Get listing images
$stmt = prepareStatement("SELECT * FROM listing_image WHERE listing_id = ?");
$stmt->bind_param("i", $listing_id);
$stmt->execute();
$images = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get book categories
$stmt = prepareStatement("
    SELECT c.name 
    FROM category c
    JOIN book_category bc ON c.cat_id = bc.cat_id
    WHERE bc.book_id = ?
");
$stmt->bind_param("i", $listing['book_id']);
$stmt->execute();
$categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get seller rating
$stmt = prepareStatement("
    SELECT AVG(rating) as avg_rating, COUNT(*) as total_ratings
    FROM rating
    WHERE reviewed_user_id = ? AND admin_approved = 1
");
$stmt->bind_param("i", $listing['user_id']);
$stmt->execute();
$rating = $stmt->get_result()->fetch_assoc();

// Get similar listings
$stmt = prepareStatement("
    SELECT l.*, b.title as book_title, MIN(li.image_url) as thumbnail,
           u.name as seller_name, u.user_id as seller_id
    FROM listing l
    JOIN book b ON l.book_id = b.book_id
    JOIN user u ON l.user_id = u.user_id
    LEFT JOIN listing_image li ON l.listing_id = li.listing_id
    WHERE l.listing_id != ?
    AND l.admin_approved = 1
    AND l.listing_status = 'active'
    AND EXISTS (
        SELECT 1 FROM book_category bc1
        WHERE bc1.book_id = l.book_id
        AND bc1.cat_id IN (
            SELECT bc2.cat_id
            FROM book_category bc2
            WHERE bc2.book_id = ?
        )
    )
    GROUP BY l.listing_id
    LIMIT 4
");
$stmt->bind_param("ii", $listing_id, $listing['book_id']);
$stmt->execute();
$similar_listings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="row">
    <!-- Left Column: Images -->
    <div class="col-md-6 mb-4">
        <?php if ($images): ?>
            <div class="position-relative">
                <img src="<?= htmlspecialchars($images[0]['image_url']) ?>" 
                     class="img-fluid rounded cursor-pointer" 
                     id="main-image" 
                     alt="<?= htmlspecialchars($listing['book_title']) ?>"
                     onclick="openImageModal(0)">
                
                <?php if ($listing['listing_type'] === 'exchange'): ?>
                    <span class="position-absolute top-0 end-0 badge bg-info m-2">
                        <i class="fas fa-exchange-alt"></i> Exchange
                    </span>
                <?php endif; ?>
            </div>
            
            <?php if (count($images) > 1): ?>
                <div class="row g-2 mt-2">
                    <?php foreach ($images as $index => $image): ?>
                        <div class="col-3">
                            <img src="<?= htmlspecialchars($image['image_url']) ?>" 
                                 class="img-fluid rounded gallery-thumbnail cursor-pointer" 
                                 onclick="changeMainImage(<?= $index ?>)"
                                 alt="Additional view">
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="rounded bg-light d-flex align-items-center justify-content-center" style="height: 400px;">
                <i class="fas fa-book fa-4x text-muted"></i>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Right Column: Details -->
    <div class="col-md-6">
        <h1 class="h2 mb-2"><?= htmlspecialchars($listing['book_title']) ?></h1>
        <p class="text-muted mb-4">by <?= htmlspecialchars($listing['author']) ?></p>
        
        <?php if ($listing['listing_type'] === 'sale'): ?>
            <h3 class="text-primary mb-4"><?= number_format($listing['price'], 2) ?> ₺</h3>
        <?php endif; ?>
        
        <div class="mb-4">
            <h5>Book Details</h5>
            <ul class="list-unstyled">
                <li><strong>Condition:</strong> <?= ucfirst($listing['condition']) ?></li>
                <li><strong>Publisher:</strong> <?= htmlspecialchars($listing['publisher']) ?></li>
                <li><strong>Year:</strong> <?= $listing['publisher_year'] ?></li>
                <li>
                    <strong>Categories:</strong>
                    <?php foreach ($categories as $category): ?>
                        <span class="badge bg-secondary"><?= htmlspecialchars($category['name']) ?></span>
                    <?php endforeach; ?>
                </li>
            </ul>
        </div>
        
        <?php if ($listing['description']): ?>
            <div class="mb-4">
                <h5>Description</h5>
                <p><?= nl2br(htmlspecialchars($listing['description'])) ?></p>
            </div>
        <?php endif; ?>
        
        <div class="mb-4">
            <h5>Seller Information</h5>
            <div class="d-flex align-items-center mb-2">
                <i class="fas fa-user-circle fa-2x text-muted me-2"></i>
                <div>
                    <div>
                        <a href="profile.php?id=<?= $listing['user_id'] ?>" class="text-decoration-none">
                            <?= htmlspecialchars($listing['seller_name']) ?>
                        </a>
                    </div>
                    <div class="text-muted small">
                        <?php if ($rating['total_ratings'] > 0): ?>
                            <i class="fas fa-star text-warning"></i>
                            <?= number_format($rating['avg_rating'], 1) ?>
                            (<?= $rating['total_ratings'] ?> ratings)
                        <?php else: ?>
                            No ratings yet
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="d-grid gap-2">
            <?php if ($listing['listing_type'] === 'sale' && 
                      $listing['listing_status'] === 'active' && 
                      !$listing['is_sold'] &&
                      $listing['admin_approved'] === 1 && 
                      $listing['user_id'] !== getCurrentUserId()): ?>
                <a href="/checkout.php?id=<?= $listing_id ?>" class="btn btn-primary btn-lg">
                    <i class="fas fa-shopping-cart"></i> Buy Now
                </a>
            <?php endif; ?>
            
            <?php if ($listing['user_id'] !== getCurrentUserId()): ?>
                <a href="/messages.php?user=<?= $listing['user_id'] ?>" class="btn btn-info btn-lg">
                    <i class="fas fa-comments"></i> Contact Seller
                </a>
            <?php endif; ?>
            
            <?php if (isLoggedIn()): ?>
                <?php if ($listing['user_id'] !== getCurrentUserId()): ?>
                    <button class="btn btn-outline-danger btn-lg btn-favorite <?= $listing['is_favorited'] ? 'active' : '' ?>"
                            data-listing-id="<?= $listing['listing_id'] ?>">
                        <i class="<?= $listing['is_favorited'] ? 'fas' : 'far' ?> fa-heart"></i>
                        <span class="favorite-text"><?= $listing['is_favorited'] ? 'Remove from favorites' : 'Add to favorites' ?></span>
                        <span class="favorite-count">(<?= $listing['favorite_count'] ?? 0 ?>)</span>
                    </button>
                <?php endif; ?>

                <?php if ($listing['user_id'] === getCurrentUserId()): ?>
                    <a href="edit-listing.php?id=<?= $listing_id ?>" class="btn btn-outline-primary btn-lg">
                        <i class="fas fa-edit"></i> Edit Listing
                    </a>
                    <?php if (!$listing['admin_approved']): ?>
                        <div class="alert alert-warning mt-3">
                            <i class="fas fa-clock"></i> This listing is pending admin approval.
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($similar_listings): ?>
    <hr class="my-5">
    
    <h3 class="mb-4">Similar Listings</h3>
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4">
        <?php foreach ($similar_listings as $similar): ?>
            <div class="col">
                <div class="card h-100">
                    <a href="/listing.php?id=<?= $similar['listing_id'] ?>" class="text-decoration-none text-dark">
                        <div class="position-relative">
                            <?php if ($similar['thumbnail']): ?>
                                <img src="<?= htmlspecialchars($similar['thumbnail']) ?>" 
                                     class="card-img-top" 
                                     alt="Book cover"
                                     style="height: 300px; object-fit: contain;">
                            <?php else: ?>
                                <div class="card-img-top bg-light d-flex align-items-center justify-content-center" 
                                     style="height: 300px;">
                                    <i class="fas fa-book fa-3x text-muted"></i>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($similar['listing_type'] === 'exchange'): ?>
                                <span class="position-absolute top-0 end-0 badge bg-info m-2">
                                    <i class="fas fa-exchange-alt"></i> Exchange
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="card-body">
                            <h5 class="card-title text-truncate">
                                <?= htmlspecialchars($similar['book_title']) ?>
                            </h5>
                            
                            <?php if ($similar['listing_type'] === 'sale'): ?>
                                <p class="card-text text-primary fw-bold">
                                    <?= number_format($similar['price'], 2) ?> ₺
                                </p>
                            <?php endif; ?>
                            
                            <p class="card-text">
                                <small class="text-muted">
                                    Condition: <?= ucfirst($similar['condition']) ?>
                                </small>
                            </p>
                        </div>
                    </a>
                    <div class="card-footer bg-white">
                        <small class="text-muted">
                            Seller: <?= htmlspecialchars($similar['seller_name']) ?>
                        </small>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Image Modal -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body p-0 position-relative">
                <button type="button" class="btn-close position-absolute top-0 end-0 m-2" 
                        data-bs-dismiss="modal" aria-label="Close"></button>
                <img id="modal-image" class="img-fluid w-100" alt="Large view">
                
                <?php if (count($images) > 1): ?>
                    <button class="btn btn-dark position-absolute top-50 start-0 translate-middle-y ms-2" 
                            onclick="prevImage()">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button class="btn btn-dark position-absolute top-50 end-0 translate-middle-y me-2" 
                            onclick="nextImage()">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
const images = <?= json_encode(array_column($images, 'image_url')) ?>;
let currentImageIndex = 0;
const imageModal = new bootstrap.Modal(document.getElementById('imageModal'));

function changeMainImage(index) {
    currentImageIndex = index;
    document.getElementById('main-image').src = images[index];
}

function openImageModal(index) {
    currentImageIndex = index;
    document.getElementById('modal-image').src = images[index];
    imageModal.show();
}

function prevImage() {
    currentImageIndex = (currentImageIndex - 1 + images.length) % images.length;
    document.getElementById('modal-image').src = images[currentImageIndex];
}

function nextImage() {
    currentImageIndex = (currentImageIndex + 1) % images.length;
    document.getElementById('modal-image').src = images[currentImageIndex];
}

// Keyboard navigation for modal
document.addEventListener('keydown', function(e) {
    if (!imageModal._isShown) return;
    
    if (e.key === 'ArrowLeft') {
        prevImage();
    } else if (e.key === 'ArrowRight') {
        nextImage();
    } else if (e.key === 'Escape') {
        imageModal.hide();
    }
});
</script>

<style>
.cursor-pointer {
    cursor: pointer;
}

.gallery-thumbnail {
    transition: opacity 0.2s;
}

.gallery-thumbnail:hover {
    opacity: 0.8;
}

#modal-image {
    max-height: 80vh;
    object-fit: contain;
}

.modal-body .btn-dark {
    opacity: 0.7;
}

.modal-body .btn-dark:hover {
    opacity: 1;
}
</style>

<?php require_once 'includes/footer.php'; ?> 