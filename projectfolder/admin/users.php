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

// Handle search and filters
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? 'all';
$page = max(1, $_GET['page'] ?? 1);
$per_page = 20;

// Build query
$where_clauses = [];
$params = [];

if ($search) {
    $where_clauses[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if ($status !== 'all') {
    $where_clauses[] = "is_blocked = ?";
    $params[] = ($status === 'blocked' ? 1 : 0);
}

$where_sql = $where_clauses ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM user $where_sql";
$stmt = $conn->prepare($count_sql);
if ($params) {
    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
}
$stmt->execute();
$total_users = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_users / $per_page);

// Get users for current page
$offset = ($page - 1) * $per_page;
$sql = "
    SELECT 
        u.*,
        COUNT(DISTINCT l.listing_id) as total_listings,
        COUNT(DISTINCT t.transaction_id) as total_transactions,
        AVG(r.rating) as avg_rating
    FROM user u
    LEFT JOIN listing l ON u.user_id = l.user_id
    LEFT JOIN transaction t ON u.user_id = t.buyer_id
    LEFT JOIN rating r ON u.user_id = r.reviewed_user_id
    $where_sql
    GROUP BY u.user_id
    ORDER BY u.reg_date DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);
$params[] = $per_page;
$params[] = $offset;
$param_types = str_repeat('s', count($params) - 2) . 'ii';
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

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
            <div class="card">
                <div class="card-header">
                    <h5 class="float-start mb-0">User Management</h5>
                </div>
                <div class="card-body">
                    <!-- Search and Filter -->
                    <form class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="input-group">
                                <input type="text" class="form-control" name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Search users...">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <select class="form-select" name="status" onchange="this.form.submit()">
                                <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Users</option>
                                <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="blocked" <?php echo $status === 'blocked' ? 'selected' : ''; ?>>Blocked</option>
                            </select>
                        </div>
                    </form>

                    <!-- Users Table -->
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Contact</th>
                                    <th>Status</th>
                                    <th>Statistics</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div>
                                                <h6 class="mb-0"><?php echo htmlspecialchars($user['name']); ?></h6>
                                                <small class="text-muted">
                                                    Member since <?php echo date('M Y', strtotime($user['reg_date'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars($user['email']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($user['phone']); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $user['is_blocked'] ? 'danger' : 'success'; ?>">
                                            <?php echo $user['is_blocked'] ? 'Blocked' : 'Active'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div>Listings: <?php echo $user['total_listings']; ?></div>
                                        <div>Transactions: <?php echo $user['total_transactions']; ?></div>
                                        <div>Rating: 
                                            <?php if ($user['avg_rating']): ?>
                                                <?php echo number_format($user['avg_rating'], 1); ?> / 5.0
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="#" class="btn btn-outline-primary view-user" 
                                               data-user-id="<?php echo $user['user_id']; ?>">
                                                View
                                            </a>
                                            <?php if ($user['is_blocked']): ?>
                                                <button class="btn btn-outline-success unblock-user"
                                                        data-user-id="<?php echo $user['user_id']; ?>">
                                                    Unblock
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-outline-danger block-user"
                                                        data-user-id="<?php echo $user['user_id']; ?>">
                                                    Block
                                                </button>
                                            <?php endif; ?>
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
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>">
                                    Previous
                                </a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>">
                                    Next
                                </a>
                            </li>
                        </ul>
                    </nav>
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

    // Handle view user
    $('.view-user').click(function(e) {
        e.preventDefault();
        const userId = $(this).data('user-id');
        window.location.href = `/admin/user-details.php?id=${userId}`;
    });
});
</script>

<?php include '../includes/footer.php'; ?> 