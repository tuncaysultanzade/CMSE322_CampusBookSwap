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
    $where_clauses[] = "(l.title LIKE ? OR b.title LIKE ? OR buyer.name LIKE ? OR seller.name LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if ($status !== 'all') {
    $where_clauses[] = "t.transaction_status = ?";
    $params[] = $status;
}

if ($type !== 'all') {
    $where_clauses[] = "t.transaction_type = ?";
    $params[] = $type;
}

$where_sql = $where_clauses ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Get total count for pagination
$count_sql = "
    SELECT COUNT(*) as total 
    FROM transaction t
    LEFT JOIN listing l ON t.listing_id = l.listing_id
    LEFT JOIN book b ON l.book_id = b.book_id
    LEFT JOIN user buyer ON t.buyer_id = buyer.user_id
    LEFT JOIN user seller ON l.user_id = seller.user_id
    $where_sql";
$stmt = $conn->prepare($count_sql);
if ($params) {
    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
}
$stmt->execute();
$total_transactions = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_transactions / $per_page);

// Get transactions for current page
$offset = ($page - 1) * $per_page;
$sql = "
    SELECT 
        t.*,
        l.title as listing_title,
        COALESCE(b.title, l.title) as book_title,
        buyer.name as buyer_name,
        seller.name as seller_name,
        (SELECT image_url FROM listing_image WHERE listing_id = l.listing_id LIMIT 1) as cover_image,
        t.delivery_address
    FROM transaction t
    LEFT JOIN listing l ON t.listing_id = l.listing_id
    LEFT JOIN book b ON l.book_id = b.book_id
    LEFT JOIN user buyer ON t.buyer_id = buyer.user_id
    LEFT JOIN user seller ON l.user_id = seller.user_id
    $where_sql
    ORDER BY t.transaction_date DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);
$params[] = $per_page;
$params[] = $offset;
$param_types = str_repeat('s', count($params) - 2) . 'ii';
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

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
                    <a href="/admin/ratings.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-star"></i> Rating Management
                    </a>
                    <a href="/admin/transactions.php" class="list-group-item list-group-item-action active">
                        <i class="fas fa-exchange-alt"></i> Transactions
                    </a>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-9">
            <div class="card">
                <div class="card-header">
                    <h5 class="float-start mb-0">Transaction Management</h5>
                </div>
                <div class="card-body">
                    <!-- Search and Filters -->
                    <form class="row g-3 mb-4">
                        <div class="col-md-4">
                            <div class="input-group">
                                <input type="text" class="form-control" name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Search transactions...">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" name="status" onchange="this.form.submit()">
                                <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="paid" <?php echo $status === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                <option value="shipped" <?php echo $status === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                <option value="delivered" <?php echo $status === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" name="type" onchange="this.form.submit()">
                                <option value="all" <?php echo $type === 'all' ? 'selected' : ''; ?>>All Types</option>
                                <option value="purchase" <?php echo $type === 'purchase' ? 'selected' : ''; ?>>Purchase</option>
                                <option value="exchange" <?php echo $type === 'exchange' ? 'selected' : ''; ?>>Exchange</option>
                            </select>
                        </div>
                    </form>

                    <!-- Transactions Table -->
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Book</th>
                                    <th>Buyer</th>
                                    <th>Seller</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Type</th>
                                    <th>Delivery Address</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $transaction): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if ($transaction['cover_image']): ?>
                                                <img src="<?php echo htmlspecialchars($transaction['cover_image']); ?>" 
                                                     alt="Book cover" class="img-thumbnail me-2" style="width: 50px;">
                                            <?php endif; ?>
                                            <div>
                                                <h6 class="mb-0"><?php echo htmlspecialchars($transaction['book_title'] ?? 'Unknown Book'); ?></h6>
                                                <small class="text-muted"><?php echo htmlspecialchars($transaction['listing_title'] ?? ''); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($transaction['buyer_name'] ?? 'Unknown Buyer'); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['seller_name'] ?? 'Unknown Seller'); ?></td>
                                    <td>
                                        <?php if ($transaction['transaction_type'] === 'purchase'): ?>
                                            <?php echo number_format($transaction['amount'], 2); ?> ₺
                                        <?php else: ?>
                                            Exchange
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo match($transaction['transaction_status']) {
                                                'paid' => 'info',
                                                'shipped' => 'primary',
                                                'delivered' => 'success',
                                                'completed' => 'success',
                                                'cancelled' => 'danger',
                                                default => 'secondary'
                                            };
                                        ?>">
                                            <?php echo ucfirst($transaction['transaction_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $transaction['transaction_type'] === 'purchase' ? 'primary' : 'info'; ?>">
                                            <?php echo ucfirst($transaction['transaction_type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($transaction['delivery_address'] ?? 'N/A'); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($transaction['transaction_date'])); ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="/listing.php?id=<?php echo $transaction['listing_id']; ?>" 
                                               class="btn btn-outline-primary" target="_blank">
                                                View Listing
                                            </a>
                                        </div>
                                        <form class="mt-2 update-transaction-form">
                                            <input type="hidden" name="transaction_id" value="<?php echo $transaction['transaction_id']; ?>">
                                            <div class="input-group input-group-sm">
                                                <select name="status" class="form-select form-select-sm" required>
                                                    <option value="">Select Status</option>
                                                    <option value="paid" <?php echo $transaction['transaction_status'] === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                                    <option value="shipped" <?php echo $transaction['transaction_status'] === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                                    <option value="delivered" <?php echo $transaction['transaction_status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                                    <option value="completed" <?php echo $transaction['transaction_status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                    <option value="cancelled" <?php echo $transaction['transaction_status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                </select>
                                            </div>
                                            <div class="shipping-fields mt-2" style="display: <?php echo $transaction['transaction_status'] === 'shipped' ? 'block' : 'none'; ?>;">
                                                <div class="input-group input-group-sm mb-1">
                                                    <input type="text" name="courier_name" class="form-control form-control-sm" 
                                                           placeholder="Shipping Carrier" 
                                                           value="<?php echo htmlspecialchars($transaction['courier_name'] ?? ''); ?>">
                                                </div>
                                                <div class="input-group input-group-sm">
                                                    <input type="text" name="tracking_code" class="form-control form-control-sm" 
                                                           placeholder="Tracking Code"
                                                           value="<?php echo htmlspecialchars($transaction['tracking_code'] ?? ''); ?>">
                                                </div>
                                            </div>
                                            <button type="submit" class="btn btn-primary btn-sm mt-1">Update</button>
                                        </form>
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
    // Show/hide shipping fields based on status
    $('select[name="status"]').change(function() {
        const shippingFields = $(this).closest('form').find('.shipping-fields');
        if ($(this).val() === 'shipped') {
            shippingFields.show();
        } else {
            shippingFields.hide();
        }
    });

    // Handle transaction status updates
    $('.update-transaction-form').submit(function(e) {
        e.preventDefault();
        const form = $(this);
        const data = {
            transaction_id: form.find('input[name="transaction_id"]').val(),
            status: form.find('select[name="status"]').val(),
            courier_name: form.find('input[name="courier_name"]').val(),
            tracking_code: form.find('input[name="tracking_code"]').val()
        };

        // Validate shipping information
        if (data.status === 'shipped' && (!data.courier_name || !data.tracking_code)) {
            alert('Please enter both shipping carrier and tracking code.');
            return;
        }

        if (confirm(`Are you sure you want to update this transaction's status to ${data.status}?`)) {
            $.ajax({
                url: 'ajax/update_transaction.php',
                method: 'POST',
                data: data,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + (response.message || 'Unknown error occurred'));
                    }
                },
                error: function() {
                    alert('Error: Failed to update transaction status');
                }
            });
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?> 