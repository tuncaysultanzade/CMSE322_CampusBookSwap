<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$page_title = "Dashboard";
require_once 'includes/header.php';

// Fetch user information
$stmt = prepareStatement("SELECT * FROM user WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch user's listings (all)
$stmt = prepareStatement("
    SELECT l.*, b.title as book_title, b.author, 
           (SELECT image_url FROM listing_image WHERE listing_id = l.listing_id LIMIT 1) as cover_image
    FROM listing l
    LEFT JOIN book b ON l.book_id = b.book_id
    WHERE l.user_id = ? 
    ORDER BY l.list_date DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$listings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch recent transactions (both sales and purchases)
$stmt = prepareStatement("
    SELECT t.*, 
           b.title as listing_title,
           CASE 
               WHEN t.buyer_id = ? THEN seller.name
               ELSE buyer.name
           END as other_party_name,
           CASE 
               WHEN t.buyer_id = ? THEN 'purchase'
               ELSE 'sale'
           END as transaction_type
    FROM transaction t
    JOIN listing l ON t.listing_id = l.listing_id
    JOIN book b ON l.book_id = b.book_id
    JOIN user seller ON l.user_id = seller.user_id
    JOIN user buyer ON t.buyer_id = buyer.user_id
    WHERE t.buyer_id = ? OR l.user_id = ?
    ORDER BY t.transaction_date DESC
    LIMIT 5
");
$stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
$stmt->execute();
$transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch unread messages count
$stmt = prepareStatement("
    SELECT COUNT(*) as unread_count
    FROM message
    WHERE receiver_id = ? AND is_read = 0
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$unread_messages = $result['unread_count'];
$stmt->close();

// Fetch recent ratings
$stmt = prepareStatement("
    SELECT r.*, u.name as reviewer_name
    FROM rating r
    JOIN user u ON r.reviewer_id = u.user_id
    WHERE r.reviewed_user_id = ?
    ORDER BY r.rating_date DESC
    LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$ratings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<div class="container mt-4">
    <div class="row">
        <!-- User Profile Card -->
        <div class="col-md-4 mb-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Profile</h5>
                    <p class="card-text">
                        <strong>Name:</strong> <?php echo htmlspecialchars($user['name']); ?><br>
                        <strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?><br>
                        <strong>Phone:</strong> <?php echo htmlspecialchars($user['phone']); ?><br>
                        <strong>Member since:</strong> <?php echo date('M Y', strtotime($user['reg_date'])); ?>
                    </p>
                    <a href="#" class="btn btn-primary btn-sm edit-profile-btn">Edit Profile</a>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="col-md-8 mb-4">
            <div class="row">
                <div class="col-sm-4">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h6 class="card-title">Active Listings</h6>
                            <h2 class="card-text"><?php echo count($listings); ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h6 class="card-title">Unread Messages</h6>
                            <h2 class="card-text"><?php echo $unread_messages; ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h6 class="card-title">Total Ratings</h6>
                            <h2 class="card-text"><?php echo count($ratings); ?></h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Listings -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Your Listings</h5>
                    <a href="add-listing.php" class="btn btn-primary btn-sm">Add New Listing</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Book</th>
                                    <th>Price</th>
                                    <th>Type</th>
                                    <th>Status</th>
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
                                                     alt="Book cover" class="img-thumbnail mr-2" style="width: 50px;">
                                            <?php endif; ?>
                                            <div>
                                                <?php echo htmlspecialchars($listing['book_title']); ?><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($listing['author']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo $listing['price'] ? number_format($listing['price'], 2).' ₺' : 'Exchange'; ?></td>
                                    <td><?php echo ucfirst($listing['listing_type']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo match($listing['listing_status']) {
                                                'active' => 'success',
                                                'pending' => 'warning',
                                                'sold' => 'info',
                                                'inactive' => 'secondary',
                                                default => 'secondary'
                                            };
                                        ?>">
                                            <?php echo ucfirst($listing['listing_status']); ?>
                                        </span>
                                        <?php if (!$listing['admin_approved']): ?>
                                            <span class="badge bg-warning">Pending Approval</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="listing.php?id=<?php echo $listing['listing_id']; ?>" 
                                               class="btn btn-sm btn-outline-primary">View</a>
                                            <?php if ($listing['listing_status'] !== 'sold'): ?>
                                                <a href="#" class="btn btn-sm btn-outline-secondary edit-listing" 
                                                   data-listing-id="<?php echo $listing['listing_id']; ?>">Edit</a>
                                                <button class="btn btn-sm btn-outline-info mark-as-sold" 
                                                        data-listing-id="<?php echo $listing['listing_id']; ?>">
                                                    Mark as Sold
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger delete-listing" 
                                                        data-listing-id="<?php echo $listing['listing_id']; ?>">Delete</button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Recent Transactions -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Transactions</h5>
                    <a href="transactions.php" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <?php if (empty($transactions)): ?>
                            <div class="text-center py-3">
                                <p class="text-muted mb-0">No transactions yet</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($transactions as $transaction): ?>
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($transaction['listing_title']); ?></h6>
                                    <small class="text-muted">
                                        <?php echo date('M d, Y', strtotime($transaction['transaction_date'])); ?>
                                    </small>
                                </div>
                                <p class="mb-1">
                                    <?php echo $transaction['transaction_type'] === 'purchase' ? 'Bought from' : 'Sold to'; ?>: 
                                    <?php echo htmlspecialchars($transaction['other_party_name']); ?>
                                </p>
                                <small class="text-<?php echo getStatusColor($transaction['transaction_status']); ?>">
                                    <?php echo ucfirst($transaction['transaction_status']); ?>
                                </small>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Ratings -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Recent Ratings</h5>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <?php foreach ($ratings as $rating): ?>
                        <div class="list-group-item">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1">
                                    <?php for($i = 0; $i < $rating['rating']; $i++): ?>
                                        <i class="fas fa-star text-warning"></i>
                                    <?php endfor; ?>
                                </h6>
                                <small class="text-muted">
                                    <?php echo date('M d, Y', strtotime($rating['rating_date'])); ?>
                                </small>
                            </div>
                            <p class="mb-1"><?php echo htmlspecialchars($rating['comment']); ?></p>
                            <small>By: <?php echo htmlspecialchars($rating['reviewer_name']); ?></small>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Listing Modal -->
<div class="modal fade" id="deleteListingModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Listing</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this listing? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Handle delete listing
    let listingToDelete = null;
    
    $('.delete-listing').click(function(e) {
        e.preventDefault();
        listingToDelete = $(this).data('listing-id');
        $('#deleteListingModal').modal('show');
    });

    $('#confirmDelete').click(function() {
        if (listingToDelete) {
            $.ajax({
                url: 'ajax/delete_listing.php',
                method: 'POST',
                data: { listing_id: listingToDelete },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error deleting listing: ' + response.message);
                    }
                },
                error: function() {
                    alert('Error communicating with server');
                }
            });
        }
        $('#deleteListingModal').modal('hide');
    });

    // Handle edit listing
    $('.edit-listing').click(function(e) {
        e.preventDefault();
        const listingId = $(this).data('listing-id');
        window.location.href = 'edit-listing.php?id=' + listingId;
    });

    // Handle edit profile
    $('.edit-profile-btn').click(function(e) {
        e.preventDefault();
        window.location.href = 'edit-profile.php';
    });

    // Handle mark as sold
    $('.mark-as-sold').click(function(e) {
        e.preventDefault();
        const button = $(this);
        const listingId = button.data('listing-id');

        if (confirm('Are you sure you want to mark this listing as sold?')) {
            $.ajax({
                url: 'ajax/update_listing_status.php',
                method: 'POST',
                data: {
                    listing_id: listingId,
                    status: 'sold'
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + (response.message || 'Failed to update status'));
                    }
                },
                error: function() {
                    alert('Error: Failed to communicate with the server');
                }
            });
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>

<?php
function getStatusColor($status) {
    return match($status) {
        'paid' => 'warning',
        'shipped' => 'info',
        'delivered' => 'primary',
        'completed' => 'success',
        'cancelled' => 'danger',
        default => 'secondary'
    };
}
?> 