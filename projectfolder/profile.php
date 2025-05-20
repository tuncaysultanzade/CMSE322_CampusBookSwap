<?php
require_once 'config.php';
require_once 'includes/auth.php';
require_once 'includes/db.php';

$user_id = $_GET['id'] ?? getCurrentUserId();
$error = '';

// Get user details
$stmt = prepareStatement("
    SELECT u.*,
           COUNT(DISTINCT l.listing_id) as total_listings,
           COUNT(DISTINCT t_sales.transaction_id) as total_sales,
           COUNT(DISTINCT t_purchases.transaction_id) as total_purchases
    FROM user u
    LEFT JOIN listing l ON u.user_id = l.user_id
    LEFT JOIN listing l_sales ON u.user_id = l_sales.user_id
    LEFT JOIN transaction t_sales ON l_sales.listing_id = t_sales.listing_id AND t_sales.transaction_status = 'completed'
    LEFT JOIN transaction t_purchases ON u.user_id = t_purchases.buyer_id AND t_purchases.transaction_status = 'completed'
    WHERE u.user_id = ?
    GROUP BY u.user_id
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    header('Location: index.php');
    exit();
}

// Get rating statistics
$stmt = prepareStatement("
    SELECT 
        COUNT(*) as total_ratings,
        AVG(rating) as average_rating,
        COUNT(CASE WHEN rating = 5 THEN 1 END) as five_star,
        COUNT(CASE WHEN rating = 4 THEN 1 END) as four_star,
        COUNT(CASE WHEN rating = 3 THEN 1 END) as three_star,
        COUNT(CASE WHEN rating = 2 THEN 1 END) as two_star,
        COUNT(CASE WHEN rating = 1 THEN 1 END) as one_star
    FROM rating
    WHERE reviewed_user_id = ? AND admin_approved = 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$rating_stats = $stmt->get_result()->fetch_assoc();

// Get recent ratings
$stmt = prepareStatement("
    SELECT r.*,
           u.name as reviewer_name
    FROM rating r
    JOIN user u ON r.reviewer_id = u.user_id
    WHERE r.reviewed_user_id = ? AND r.admin_approved = 1
    ORDER BY r.rating_date DESC
    LIMIT 10
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_ratings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get active listings
$stmt = prepareStatement("
    SELECT l.*, 
           b.title as book_title,
           b.author,
           (SELECT image_url FROM listing_image WHERE listing_id = l.listing_id LIMIT 1) as cover_image
    FROM listing l
    JOIN book b ON l.book_id = b.book_id
    WHERE l.user_id = ?
    AND l.listing_status = 'active'
    AND l.admin_approved = 1
    ORDER BY l.list_date DESC
    LIMIT 4
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$active_listings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

include 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <!-- User Info -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="fas fa-user-circle fa-5x text-primary"></i>
                    </div>
                    <h4><?php echo htmlspecialchars($user['name']); ?></h4>
                    <p class="text-muted">
                        Member since <?php echo $user['reg_date'] ? date('F Y', strtotime($user['reg_date'])) : 'N/A'; ?>
                    </p>
                    <hr>
                    <div class="row text-center">
                        <div class="col">
                            <h5><?php echo $user['total_listings']; ?></h5>
                            <small class="text-muted">Listings</small>
                        </div>
                        <div class="col">
                            <h5><?php echo $user['total_sales']; ?></h5>
                            <small class="text-muted">Sales</small>
                        </div>
                        <div class="col">
                            <h5><?php echo $user['total_purchases']; ?></h5>
                            <small class="text-muted">Purchases</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rating Summary -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Rating Summary</h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <h2 class="mb-0">
                            <?php echo $rating_stats['average_rating'] ? number_format($rating_stats['average_rating'], 1) : '0.0'; ?>
                            <small class="text-muted">/ 5</small>
                        </h2>
                        <div class="text-warning mb-2">
                            <?php
                            $avg_rating = $rating_stats['average_rating'] ? round($rating_stats['average_rating']) : 0;
                            for ($i = 1; $i <= 5; $i++):
                            ?>
                                <i class="fas fa-star <?php echo $i <= $avg_rating ? 'text-warning' : 'text-muted'; ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <p class="text-muted">
                            <?php echo $rating_stats['total_ratings'] ?? 0; ?> ratings
                        </p>
                    </div>

                    <!-- Rating Distribution -->
                    <?php
                    $ratings = [
                        5 => $rating_stats['five_star'] ?? 0,
                        4 => $rating_stats['four_star'] ?? 0,
                        3 => $rating_stats['three_star'] ?? 0,
                        2 => $rating_stats['two_star'] ?? 0,
                        1 => $rating_stats['one_star'] ?? 0
                    ];
                    foreach ($ratings as $stars => $count):
                        $percentage = $rating_stats['total_ratings'] > 0 
                            ? ($count / $rating_stats['total_ratings']) * 100 
                            : 0;
                    ?>
                        <div class="d-flex align-items-center mb-2">
                            <div class="text-muted" style="width: 60px;">
                                <?php echo $stars; ?> <i class="fas fa-star text-warning"></i>
                            </div>
                            <div class="flex-grow-1 mx-2">
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-warning" 
                                         style="width: <?php echo $percentage; ?>%"></div>
                                </div>
                            </div>
                            <div class="text-muted" style="width: 40px;">
                                <?php echo $count; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <!-- Active Listings -->
            <?php if (!empty($active_listings)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Active Listings</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($active_listings as $listing): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card h-100">
                                        <div class="row g-0">
                                            <div class="col-4">
                                                <?php if ($listing['cover_image']): ?>
                                                    <img src="<?php echo htmlspecialchars($listing['cover_image']); ?>" 
                                                         class="img-fluid rounded-start" alt="Book cover">
                                                <?php else: ?>
                                                    <div class="bg-light h-100 d-flex align-items-center justify-content-center">
                                                        <i class="fas fa-book fa-2x text-muted"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-8">
                                                <div class="card-body">
                                                    <h6 class="card-title mb-1">
                                                        <a href="listing.php?id=<?php echo $listing['listing_id']; ?>" 
                                                           class="text-decoration-none">
                                                            <?php echo htmlspecialchars($listing['book_title']); ?>
                                                        </a>
                                                    </h6>
                                                    <p class="card-text small text-muted mb-2">
                                                        <?php echo htmlspecialchars($listing['author']); ?>
                                                    </p>
                                                    <p class="card-text">
                                                        <?php if ($listing['listing_type'] === 'exchange'): ?>
                                                            <strong>For Exchange</strong>
                                                        <?php else: ?>
                                                            <strong><?php echo $listing['price'] ? number_format($listing['price'], 2).' ₺' : '0.00 ₺'; ?></strong>
                                                        <?php endif; ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Recent Ratings -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Recent Ratings</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_ratings)): ?>
                        <p class="text-center text-muted my-4">No ratings yet</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recent_ratings as $rating): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <a href="profile.php?id=<?php echo $rating['reviewer_id']; ?>" 
                                               class="text-decoration-none">
                                                <?php echo htmlspecialchars($rating['reviewer_name']); ?>
                                            </a>
                                            <span class="text-muted mx-2">&bull;</span>
                                            <small class="text-muted">
                                                <?php echo date('M j, Y', strtotime($rating['rating_date'])); ?>
                                            </small>
                                        </div>
                                        <div class="text-warning">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star <?php echo $i <= $rating['rating'] ? 'text-warning' : 'text-muted'; ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <p class="mb-1"><?php echo htmlspecialchars($rating['comment']); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?> 