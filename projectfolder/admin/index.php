<?php
require_once '../config.php';
require_once '../includes/auth.php';
require_once '../includes/db.php';

// Ensure user is logged in and is an admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: /login.php');
    exit();
}

// Get system statistics
$stats = [];
$conn = getDbConnection();

// Total users
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM user WHERE is_blocked = 0");
$stmt->execute();
$stats['active_users'] = $stmt->get_result()->fetch_assoc()['count'];

// Total listings
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM listing WHERE listing_status = 'active' AND admin_approved = 1");
$stmt->execute();
$stats['active_listings'] = $stmt->get_result()->fetch_assoc()['count'];

// Pending approvals
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM listing WHERE admin_approved = 0 AND listing_status != 'sold'");
$stmt->execute();
$stats['pending_approvals'] = $stmt->get_result()->fetch_assoc()['count'];

// Recent transactions
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM transaction WHERE transaction_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$stmt->execute();
$stats['recent_transactions'] = $stmt->get_result()->fetch_assoc()['count'];

// Get recent activities
$stmt = $conn->prepare("
    SELECT 
        'listing' as type,
        l.listing_id as id,
        COALESCE(b.title, l.title, 'Untitled Listing') as title,
        COALESCE(u.name, 'Unknown User') as user_name,
        l.list_date as date,
        l.listing_status
    FROM listing l
    LEFT JOIN user u ON l.user_id = u.user_id
    LEFT JOIN book b ON l.book_id = b.book_id
    WHERE l.admin_approved = 0 AND l.listing_status != 'sold'
    UNION ALL
    SELECT 
        'rating' as type,
        r.rating_id as id,
        CONCAT('Rating by ', COALESCE(u1.name, 'Unknown User'), ' for ', COALESCE(u2.name, 'Unknown User')) as title,
        COALESCE(u1.name, 'Unknown User') as user_name,
        r.rating_date as date,
        NULL as listing_status
    FROM rating r
    LEFT JOIN user u1 ON r.reviewer_id = u1.user_id
    LEFT JOIN user u2 ON r.reviewed_user_id = u2.user_id
    WHERE r.admin_approved = 0
    ORDER BY date DESC
    LIMIT 10
");
$stmt->execute();
$recent_activities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

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
                    <a href="/admin/" class="list-group-item list-group-item-action active">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a href="/admin/users.php" class="list-group-item list-group-item-action">
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
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-sm-6 col-xl-3 mb-4">
                    <div class="card bg-primary text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="me-3">
                                    <div class="text-white-75">Active Users</div>
                                    <div class="display-4 fw-bold"><?php echo $stats['active_users']; ?></div>
                                </div>
                                <i class="fas fa-users fa-2x text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3 mb-4">
                    <div class="card bg-warning text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="me-3">
                                    <div class="text-white-75">Active Listings</div>
                                    <div class="display-4 fw-bold"><?php echo $stats['active_listings']; ?></div>
                                </div>
                                <i class="fas fa-list fa-2x text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3 mb-4">
                    <div class="card bg-success text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="me-3">
                                    <div class="text-white-75">Recent Transactions</div>
                                    <div class="display-4 fw-bold"><?php echo $stats['recent_transactions']; ?></div>
                                </div>
                                <i class="fas fa-exchange-alt fa-2x text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3 mb-4">
                    <div class="card bg-danger text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="me-3">
                                    <div class="text-white-75">Pending Approvals</div>
                                    <div class="display-4 fw-bold"><?php echo $stats['pending_approvals']; ?></div>
                                </div>
                                <i class="fas fa-clock fa-2x text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Recent Activities</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Title</th>
                                    <th>User</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_activities as $activity): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-<?php echo $activity['type'] === 'listing' ? 'primary' : 'info'; ?>">
                                            <?php echo ucfirst($activity['type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($activity['title'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($activity['user_name'] ?? ''); ?></td>
                                    <td><?php echo $activity['date'] ? date('M j, Y g:i A', strtotime($activity['date'])) : ''; ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="#" class="btn btn-outline-primary view-item" 
                                               data-type="<?php echo htmlspecialchars($activity['type'] ?? ''); ?>" 
                                               data-id="<?php echo htmlspecialchars($activity['id'] ?? ''); ?>">
                                                View
                                            </a>
                                            <button class="btn btn-outline-success approve-item"
                                                    data-type="<?php echo htmlspecialchars($activity['type'] ?? ''); ?>" 
                                                    data-id="<?php echo htmlspecialchars($activity['id'] ?? ''); ?>">
                                                Approve
                                            </button>
                                            <button class="btn btn-outline-danger reject-item"
                                                    data-type="<?php echo htmlspecialchars($activity['type'] ?? ''); ?>" 
                                                    data-id="<?php echo htmlspecialchars($activity['id'] ?? ''); ?>">
                                                Reject
                                            </button>
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
</div>

<script>
$(document).ready(function() {
    // Handle approval/rejection
    $('.approve-item, .reject-item').click(function(e) {
        e.preventDefault();
        const button = $(this);
        const action = button.hasClass('approve-item') ? 'approve' : 'reject';
        const type = button.data('type');
        const id = button.data('id');

        if (confirm(`Are you sure you want to ${action} this ${type}?`)) {
            $.ajax({
                url: `/admin/ajax/moderate.php`,
                method: 'POST',
                data: { type, id, action },
                success: function(response) {
                    if (response.success) {
                        button.closest('tr').fadeOut();
                    } else {
                        alert('Error: ' + response.message);
                    }
                }
            });
        }
    });

    // Handle view item
    $('.view-item').click(function(e) {
        e.preventDefault();
        const type = $(this).data('type');
        const id = $(this).data('id');
        
        if (type === 'listing') {
            window.location.href = `/listing.php?id=${id}`;
        } else if (type === 'rating') {
            // For ratings, we'll redirect to the user's profile who received the rating
            window.location.href = `/admin/ratings.php?id=${id}`;
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?> 