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
$type = $_GET['type'] ?? 'all';
$page = max(1, $_GET['page'] ?? 1);
$per_page = 20;

// Build query
$where_clauses = [];
$params = [];

if ($search) {
    $where_clauses[] = "(l.title LIKE ? OR b.title LIKE ? OR b.author LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if ($status !== 'all') {
    $where_clauses[] = "l.listing_status = ?";
    $params[] = $status;
}

if ($type !== 'all') {
    $where_clauses[] = "l.listing_type = ?";
    $params[] = $type;
}

$where_sql = $where_clauses ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Get total count for pagination
$count_sql = "
    SELECT COUNT(*) as total 
    FROM listing l
    LEFT JOIN book b ON l.book_id = b.book_id
    $where_sql";
$stmt = $conn->prepare($count_sql);
if ($params) {
    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
}
$stmt->execute();
$total_listings = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_listings / $per_page);

// Get listings for current page
$offset = ($page - 1) * $per_page;
$sql = "
    SELECT 
        l.*,
        b.title as book_title,
        b.author,
        u.name as seller_name,
        u.email as seller_email,
        (SELECT image_url FROM listing_image WHERE listing_id = l.listing_id LIMIT 1) as cover_image
    FROM listing l
    LEFT JOIN book b ON l.book_id = b.book_id
    LEFT JOIN user u ON l.user_id = u.user_id
    $where_sql
    ORDER BY l.list_date DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);
$params[] = $per_page;
$params[] = $offset;
$param_types = str_repeat('s', count($params) - 2) . 'ii';
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$listings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

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
                    <a href="/admin/listings.php" class="list-group-item list-group-item-action active">
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
                    <h5 class="float-start mb-0">Listing Management</h5>
                </div>
                <div class="card-body">
                    <!-- Search and Filters -->
                    <form class="row g-3 mb-4">
                        <div class="col-md-4">
                            <div class="input-group">
                                <input type="text" class="form-control" name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Search listings...">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" name="status" onchange="this.form.submit()">
                                <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="sold" <?php echo $status === 'sold' ? 'selected' : ''; ?>>Sold</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" name="type" onchange="this.form.submit()">
                                <option value="all" <?php echo $type === 'all' ? 'selected' : ''; ?>>All Types</option>
                                <option value="sale" <?php echo $type === 'sale' ? 'selected' : ''; ?>>For Sale</option>
                                <option value="exchange" <?php echo $type === 'exchange' ? 'selected' : ''; ?>>For Exchange</option>
                            </select>
                        </div>
                    </form>

                    <!-- Listings Table -->
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Book</th>
                                    <th>Seller</th>
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
                                                     alt="Book cover" class="img-thumbnail me-2" style="width: 50px;">
                                            <?php endif; ?>
                                            <div>
                                                <h6 class="mb-0"><?php echo htmlspecialchars($listing['book_title']); ?></h6>
                                                <small class="text-muted"><?php echo htmlspecialchars($listing['author']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars($listing['seller_name']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($listing['seller_email']); ?></small>
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
                                        <div class="mt-2">
                                            <select class="form-select form-select-sm update-status" 
                                                    data-listing-id="<?php echo $listing['listing_id']; ?>">
                                                <option value="">Change Status...</option>
                                                <option value="active" <?php echo $listing['listing_status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                                <option value="inactive" <?php echo $listing['listing_status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                <option value="sold" <?php echo $listing['listing_status'] === 'sold' ? 'selected' : ''; ?>>Sold</option>
                                            </select>
                                        </div>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($listing['list_date'])); ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="/listing.php?id=<?php echo $listing['listing_id']; ?>" 
                                               class="btn btn-outline-primary" target="_blank">
                                                View
                                            </a>
                                            <?php if (!$listing['admin_approved']): ?>
                                                <button class="btn btn-outline-success approve-listing"
                                                        data-listing-id="<?php echo $listing['listing_id']; ?>">
                                                    Approve
                                                </button>
                                                <button class="btn btn-outline-danger reject-listing"
                                                        data-listing-id="<?php echo $listing['listing_id']; ?>">
                                                    Reject
                                                </button>
                                            <?php endif; ?>
                                            <button class="btn btn-outline-danger delete-listing"
                                                    data-listing-id="<?php echo $listing['listing_id']; ?>">
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
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>&type=<?php echo $type; ?>">
                                    Previous
                                </a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>&type=<?php echo $type; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>&type=<?php echo $type; ?>">
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
    // Handle listing approval/rejection
    $('.approve-listing, .reject-listing').click(function(e) {
        e.preventDefault();
        const button = $(this);
        const listingId = button.data('listing-id');
        const action = button.hasClass('approve-listing') ? 'approve' : 'reject';

        if (confirm(`Are you sure you want to ${action} this listing?`)) {
            $.ajax({
                url: '/admin/ajax/moderate.php',
                method: 'POST',
                data: { 
                    type: 'listing',
                    id: listingId,
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

    // Handle listing deletion
    $('.delete-listing').click(function(e) {
        e.preventDefault();
        const listingId = $(this).data('listing-id');

        if (confirm('Are you sure you want to delete this listing? This action cannot be undone.')) {
            $.ajax({
                url: '/admin/ajax/delete_listing.php',
                method: 'POST',
                data: { listing_id: listingId },
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

    // Handle listing status updates
    $('.update-status').change(function() {
        const select = $(this);
        const listingId = select.data('listing-id');
        const newStatus = select.val();

        if (!newStatus) return; // Skip if "Change Status..." is selected

        if (confirm(`Are you sure you want to change the status to ${newStatus}?`)) {
            $.ajax({
                url: 'ajax/update_listing_status.php',
                method: 'POST',
                data: {
                    listing_id: listingId,
                    status: newStatus
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + (response.message || 'Failed to update status'));
                        select.val(select.find('option:selected').val()); // Reset to previous value
                    }
                },
                error: function() {
                    alert('Error: Failed to communicate with the server');
                    select.val(select.find('option:selected').val()); // Reset to previous value
                }
            });
        } else {
            select.val(select.find('option:selected').val()); // Reset if cancelled
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?> 