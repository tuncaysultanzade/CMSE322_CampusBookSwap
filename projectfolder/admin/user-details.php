<?php
require_once '../config.php';
require_once '../includes/auth.php';
require_once '../includes/db.php';

// Ensure user is logged in and is an admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: /login.php');
    exit();
}

$user_id = $_GET['id'] ?? 0;
if (!$user_id) {
    header('Location: users.php');
    exit();
}

$conn = getDbConnection();

// Get user details
$stmt = prepareStatement("
    SELECT 
        u.*,
        COUNT(DISTINCT l.listing_id) as total_listings,
        COUNT(DISTINCT t_buyer.transaction_id) as total_purchases,
        COUNT(DISTINCT t_seller.transaction_id) as total_sales,
        AVG(r.rating) as avg_rating,
        COUNT(DISTINCT r.rating_id) as total_ratings
    FROM user u
    LEFT JOIN listing l ON u.user_id = l.user_id
    LEFT JOIN transaction t_buyer ON u.user_id = t_buyer.buyer_id
    LEFT JOIN listing l_seller ON u.user_id = l_seller.user_id
    LEFT JOIN transaction t_seller ON l_seller.listing_id = t_seller.listing_id
    LEFT JOIN rating r ON u.user_id = r.reviewed_user_id AND r.admin_approved = 1
    WHERE u.user_id = ?
    GROUP BY u.user_id
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    header('Location: users.php');
    exit();
}

// Get user's listings
$stmt = prepareStatement("
    SELECT 
        l.*,
        COALESCE(b.title, l.title) as book_title,
        b.author,
        (SELECT image_url FROM listing_image WHERE listing_id = l.listing_id LIMIT 1) as cover_image
    FROM listing l
    LEFT JOIN book b ON l.book_id = b.book_id
    WHERE l.user_id = ?
    ORDER BY l.list_date DESC
    LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$listings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get user's transactions
$stmt = prepareStatement("
    SELECT 
        t.*,
        COALESCE(b.title, l.title) as book_title,
        seller.name as seller_name,
        buyer.name as buyer_name,
        t.delivery_address
    FROM transaction t
    JOIN listing l ON t.listing_id = l.listing_id
    LEFT JOIN book b ON l.book_id = b.book_id
    JOIN user seller ON l.user_id = seller.user_id
    JOIN user buyer ON t.buyer_id = buyer.user_id
    WHERE t.buyer_id = ? OR l.user_id = ?
    ORDER BY t.transaction_date DESC
    LIMIT 5
");
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get user's ratings
$stmt = prepareStatement("
    SELECT 
        r.*,
        u.name as reviewer_name
    FROM rating r
    JOIN user u ON r.reviewer_id = u.user_id
    WHERE r.reviewed_user_id = ? AND r.admin_approved = 1
    ORDER BY r.rating_date DESC
    LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$ratings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Admin Panel</h5>
                </div>
                <div class="list-group list-group-flush">
                    <a href="/admin/" class="list-group-item list-group-item-action">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a href="/admin/users.php" class="list-group-item list-group-item-action active">
                        <i class="fas fa-users"></i> User Management
                    </a>
                    <a href="/admin/listings.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-list"></i> Listing Management
                    </a>
                    <a href="/admin/ratings.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-star"></i> Rating Management
                    </a>
                    <a href="/admin/transactions.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-exchange-alt"></i> Transactions
                    </a>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-9">
            <!-- User Profile -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">User Profile</h5>
                    <div>
                        <a href="users.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-arrow-left"></i> Back to Users
                        </a>
                        <?php if ($user['is_blocked']): ?>
                            <button class="btn btn-success btn-sm unblock-user" data-user-id="<?php echo $user['user_id']; ?>">
                                <i class="fas fa-unlock"></i> Unblock User
                            </button>
                        <?php else: ?>
                            <button class="btn btn-danger btn-sm block-user" data-user-id="<?php echo $user['user_id']; ?>">
                                <i class="fas fa-ban"></i> Block User
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Basic Information</h6>
                            <table class="table table-sm">
                                <tr>
                                    <th>Name:</th>
                                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                                </tr>
                                <tr>
                                    <th>Email:</th>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                </tr>
                                <tr>
                                    <th>Phone:</th>
                                    <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                </tr>
                                <tr>
                                    <th>Member Since:</th>
                                    <td><?php echo date('M j, Y', strtotime($user['reg_date'])); ?></td>
                                </tr>
                                <tr>
                                    <th>Status:</th>
                                    <td>
                                        <span class="badge bg-<?php echo $user['is_blocked'] ? 'danger' : 'success'; ?>">
                                            <?php echo $user['is_blocked'] ? 'Blocked' : 'Active'; ?>
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6>Activity Summary</h6>
                            <table class="table table-sm">
                                <tr>
                                    <th>Total Listings:</th>
                                    <td><?php echo $user['total_listings']; ?></td>
                                </tr>
                                <tr>
                                    <th>Total Purchases:</th>
                                    <td><?php echo $user['total_purchases']; ?></td>
                                </tr>
                                <tr>
                                    <th>Total Sales:</th>
                                    <td><?php echo $user['total_sales']; ?></td>
                                </tr>
                                <tr>
                                    <th>Rating:</th>
                                    <td>
                                        <?php if ($user['avg_rating']): ?>
                                            <?php echo number_format($user['avg_rating'], 1); ?> / 5.0
                                            (<?php echo $user['total_ratings']; ?> ratings)
                                        <?php else: ?>
                                            No ratings yet
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Listings -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Recent Listings</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($listings)): ?>
                        <p class="text-muted text-center mb-0">No listings found</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Book</th>
                                        <th>Price/Type</th>
                                        <th>Status</th>
                                        <th>Listed</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($listings as $listing): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php if ($listing['cover_image']): ?>
                                                        <img src="<?php echo htmlspecialchars($listing['cover_image']); ?>" 
                                                             class="img-thumbnail me-2" style="width: 50px; height: 50px; object-fit: cover;"
                                                             alt="Book cover">
                                                    <?php endif; ?>
                                                    <div>
                                                        <?php echo htmlspecialchars($listing['book_title']); ?><br>
                                                        <small class="text-muted">
                                                            <?php echo htmlspecialchars($listing['author']); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($listing['listing_type'] === 'sale'): ?>
                                                    <?php echo number_format($listing['price'], 2); ?> ₺
                                                <?php else: ?>
                                                    For Exchange
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo match($listing['listing_status']) {
                                                        'active' => 'success',
                                                        'inactive' => 'secondary',
                                                        'sold' => 'info',
                                                        default => 'secondary'
                                                    };
                                                ?>">
                                                    <?php echo ucfirst($listing['listing_status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo date('M j, Y', strtotime($listing['list_date'])); ?>
                                            </td>
                                            <td>
                                                <a href="/listing.php?id=<?php echo $listing['listing_id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Recent Transactions</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($transactions)): ?>
                        <p class="text-muted text-center mb-0">No transactions found</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Book</th>
                                        <th>Type</th>
                                        <th>Other Party</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions as $transaction): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($transaction['book_title']); ?></td>
                                            <td>
                                                <?php 
                                                    echo $transaction['buyer_id'] == $user_id ? 'Purchase' : 'Sale';
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                    echo htmlspecialchars($transaction['buyer_id'] == $user_id 
                                                        ? $transaction['seller_name'] 
                                                        : $transaction['buyer_name']); 
                                                ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo match($transaction['transaction_status']) {
                                                        'completed' => 'success',
                                                        'cancelled' => 'danger',
                                                        'pending' => 'warning',
                                                        'paid' => 'info',
                                                        'shipped' => 'primary',
                                                        default => 'secondary'
                                                    };
                                                ?>">
                                                    <?php echo ucfirst($transaction['transaction_status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo date('M j, Y', strtotime($transaction['transaction_date'])); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Ratings -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Recent Ratings</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($ratings)): ?>
                        <p class="text-muted text-center mb-0">No ratings found</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($ratings as $rating): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <span class="fw-bold"><?php echo htmlspecialchars($rating['reviewer_name']); ?></span>
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
                                    <p class="mb-0"><?php echo htmlspecialchars($rating['comment']); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Handle block/unblock user
    $('.block-user, .unblock-user').click(function(e) {
        e.preventDefault();
        const button = $(this);
        const userId = button.data('user-id');
        const action = button.hasClass('block-user') ? 'block' : 'unblock';

        if (confirm(`Are you sure you want to ${action} this user?`)) {
            $.ajax({
                url: '/admin/ajax/moderate.php',
                method: 'POST',
                data: { 
                    type: 'user',
                    id: userId,
                    action: action
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                }
            });
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?> 