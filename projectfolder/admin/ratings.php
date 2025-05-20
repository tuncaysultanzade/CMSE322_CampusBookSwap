<?php
require_once '../config.php';
require_once '../includes/auth.php';
require_once '../includes/db.php';

// Ensure user is logged in and is an admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: /login.php');
    exit();
}

$conn = getDbConnection();

$page = max(1, $_GET['page'] ?? 1);
$per_page = 20;

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM rating";
$total_ratings = $conn->query($count_sql)->fetch_assoc()['total'];
$total_pages = ceil($total_ratings / $per_page);

// Get ratings for current page
$offset = ($page - 1) * $per_page;
$sql = "
    SELECT 
        r.*,
        reviewer.name as reviewer_name,
        reviewed.name as reviewed_name
    FROM rating r
    JOIN user reviewer ON r.reviewer_id = reviewer.user_id
    JOIN user reviewed ON r.reviewed_user_id = reviewed.user_id
    ORDER BY r.rating_date DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $per_page, $offset);
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
                    <a href="/admin/users.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-users"></i> User Management
                    </a>
                    <a href="/admin/listings.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-list"></i> Listing Management
                    </a>
                    <a href="/admin/ratings.php" class="list-group-item list-group-item-action active">
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
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Rating Management</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($ratings)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-star fa-3x text-muted mb-3"></i>
                            <p class="lead">No ratings found.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Book</th>
                                        <th>From</th>
                                        <th>To</th>
                                        <th>Rating</th>
                                        <th>Comment</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ratings as $rating): ?>
                                        <tr>
                                            <td><?php echo date('M j, Y', strtotime($rating['rating_date'])); ?></td>
                                            <td>N/A</td>
                                            <td><?php echo htmlspecialchars($rating['reviewer_name']); ?></td>
                                            <td><?php echo htmlspecialchars($rating['reviewed_name']); ?></td>
                                            <td>
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star <?php echo $i <= $rating['rating'] ? 'text-warning' : 'text-muted'; ?>"></i>
                                                <?php endfor; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($rating['comment']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $rating['admin_approved'] ? 'success' : 'warning'; ?>">
                                                    <?php echo $rating['admin_approved'] ? 'Approved' : 'Pending'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <?php if (!$rating['admin_approved']): ?>
                                                        <button class="btn btn-outline-success approve-rating"
                                                                data-id="<?php echo $rating['rating_id']; ?>">
                                                            Approve
                                                        </button>
                                                        <button class="btn btn-outline-danger reject-rating"
                                                                data-id="<?php echo $rating['rating_id']; ?>">
                                                            Reject
                                                        </button>
                                                    <?php endif; ?>
                                                    <button class="btn btn-outline-danger delete-rating"
                                                            data-id="<?php echo $rating['rating_id']; ?>">
                                                        Delete
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>">
                                            Previous
                                        </a>
                                    </li>
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>">
                                            Next
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Handle rating approval/rejection
    $('.approve-rating, .reject-rating').click(function(e) {
        e.preventDefault();
        const button = $(this);
        const action = button.hasClass('approve-rating') ? 'approve' : 'reject';
        const id = button.data('id');

        if (confirm(`Are you sure you want to ${action} this rating?`)) {
            $.ajax({
                url: '/admin/ajax/moderate.php',
                method: 'POST',
                data: { 
                    type: 'rating',
                    id: id,
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

    // Handle rating deletion
    $('.delete-rating').click(function(e) {
        e.preventDefault();
        const id = $(this).data('id');

        if (confirm('Are you sure you want to delete this rating? This action cannot be undone.')) {
            $.ajax({
                url: '/admin/ajax/moderate.php',
                method: 'POST',
                data: { 
                    type: 'rating',
                    id: id,
                    action: 'reject'
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