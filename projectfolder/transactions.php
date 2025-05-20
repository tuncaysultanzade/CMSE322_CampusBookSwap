<?php
require_once 'config.php';
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Ensure user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$user_id = getCurrentUserId();
$type = $_GET['type'] ?? 'purchases';
$page = max(1, $_GET['page'] ?? 1);
$per_page = 10;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $transaction_id = $_POST['transaction_id'] ?? null;
    $action = $_POST['action'] ?? '';
    
    if ($transaction_id) {
        // Get transaction details
        $stmt = prepareStatement("
            SELECT t.*, l.user_id as seller_id
            FROM transaction t
            JOIN listing l ON t.listing_id = l.listing_id
            WHERE t.transaction_id = ?
        ");
        $stmt->bind_param("i", $transaction_id);
        $stmt->execute();
        $transaction = $stmt->get_result()->fetch_assoc();
        
        if ($transaction) {
            switch ($action) {
                case 'update_shipping':
                    if ($transaction['seller_id'] === $user_id && $transaction['transaction_status'] === 'paid') {
                        $tracking = $_POST['tracking_code'] ?? '';
                        $courier = $_POST['courier_name'] ?? '';
                        if ($tracking && $courier) {
                            $stmt = prepareStatement("
                                UPDATE transaction 
                                SET transaction_status = 'shipped',
                                    tracking_code = ?,
                                    courier_name = ?
                                WHERE transaction_id = ?
                            ");
                            $stmt->bind_param("ssi", $tracking, $courier, $transaction_id);
                            $stmt->execute();
                            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Shipping information updated successfully.'];
                        }
                    }
                    break;
                    
                case 'mark_delivered':
                    if ($transaction['buyer_id'] === $user_id && $transaction['transaction_status'] === 'shipped') {
                        $stmt = prepareStatement("
                            UPDATE transaction 
                            SET transaction_status = 'delivered'
                            WHERE transaction_id = ?
                        ");
                        $stmt->bind_param("i", $transaction_id);
                        $stmt->execute();
                        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Order marked as delivered.'];
                    }
                    break;
                    
                case 'complete_transaction':
                    if ($transaction['buyer_id'] === $user_id && $transaction['transaction_status'] === 'delivered') {
                        // Start transaction
                        $conn = getDbConnection();
                        $conn->begin_transaction();
                        
                        try {
                            // Add rating
                            $rating = intval($_POST['rating'] ?? 0);
                            $comment = $_POST['comment'] ?? '';
                            if ($rating >= 1 && $rating <= 5 && $comment) {
                                // Insert rating
                                $stmt = prepareStatement("
                                    INSERT INTO rating (
                                        reviewer_id, reviewed_user_id,
                                        rating, comment, rating_date, admin_approved
                                    ) VALUES (?, ?, ?, ?, CURDATE(), 1)
                                ");
                                $stmt->bind_param("iiis", 
                                    $user_id,
                                    $transaction['seller_id'],
                                    $rating,
                                    $comment
                                );
                                $stmt->execute();
                                
                                // Update transaction status
                                $stmt = prepareStatement("
                                    UPDATE transaction 
                                    SET transaction_status = 'completed'
                                    WHERE transaction_id = ?
                                ");
                                $stmt->bind_param("i", $transaction_id);
                                $stmt->execute();
                                
                                $conn->commit();
                                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Transaction completed and review submitted successfully.'];
                            } else {
                                throw new Exception('Invalid rating or comment.');
                            }
                        } catch (Exception $e) {
                            $conn->rollback();
                            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Error: ' . $e->getMessage()];
                        }
                    }
                    break;
            }
        }
    }
    
    // Redirect to remove POST data
    header('Location: ' . $_SERVER['PHP_SELF'] . '?type=' . $type . '&page=' . $page);
    exit();
}

// Build query
$where_clauses = [];
$params = [];
$param_types = '';

// Filter by type (purchases/sales)
if ($type === 'purchases') {
    $where_clauses[] = "t.buyer_id = ?";
} else {
    $where_clauses[] = "l.user_id = ?";
}
$params[] = $user_id;
$param_types .= 'i';

$where_sql = '';
if (!empty($where_clauses)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
}

// Get total count for pagination
$count_sql = "
    SELECT COUNT(*) as total 
    FROM transaction t 
    JOIN listing l ON t.listing_id = l.listing_id 
    $where_sql
";
$stmt = prepareStatement($count_sql);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$total_transactions = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_transactions / $per_page);

// Get transactions for current page
$offset = ($page - 1) * $per_page;
$sql = "
    SELECT 
        t.*,
        COALESCE(b.title, l.title) as book_title,
        b.author,
        li.image_url as cover_image,
        seller.name as seller_name,
        buyer.name as buyer_name,
        t.delivery_address,
        CASE 
            WHEN ? = t.buyer_id THEN seller.name
            ELSE buyer.name
        END as other_party_name,
        CASE 
            WHEN ? = t.buyer_id THEN seller.user_id
            ELSE buyer.user_id
        END as other_party_id
    FROM transaction t
    JOIN listing l ON t.listing_id = l.listing_id
    LEFT JOIN book b ON l.book_id = b.book_id
    LEFT JOIN listing_image li ON l.listing_id = li.listing_id
    JOIN user seller ON l.user_id = seller.user_id
    JOIN user buyer ON t.buyer_id = buyer.user_id
    $where_sql
    GROUP BY t.transaction_id
    ORDER BY t.transaction_date DESC
    LIMIT ? OFFSET ?
";

// Create a new array for the main query parameters
$main_params = [$user_id, $user_id]; // For the CASE statements
$main_param_types = 'ii';

// Add the WHERE clause parameter if it exists
if (!empty($params)) {
    $main_params[] = $user_id;
    $main_param_types .= 'i';
}

// Add parameters for LIMIT and OFFSET
$main_params[] = $per_page;
$main_params[] = $offset;
$main_param_types .= 'ii';

$stmt = prepareStatement($sql);
$stmt->bind_param($main_param_types, ...$main_params);
$stmt->execute();
$transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

include 'includes/header.php';
?>

<div class="container mt-4">
    <?php if (isset($_SESSION['flash'])): ?>
        <div class="alert alert-<?php echo $_SESSION['flash']['type']; ?> alert-dismissible fade show">
            <?php echo $_SESSION['flash']['message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-3">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Transaction History</h5>
                </div>
                <div class="list-group list-group-flush">
                    <a href="?type=purchases" 
                       class="list-group-item list-group-item-action <?php echo $type === 'purchases' ? 'active' : ''; ?>">
                        <i class="fas fa-shopping-cart"></i> My Purchases
                    </a>
                    <a href="?type=sales" 
                       class="list-group-item list-group-item-action <?php echo $type === 'sales' ? 'active' : ''; ?>">
                        <i class="fas fa-store"></i> My Sales
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-9">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">
                        <?php echo $type === 'purchases' ? 'My Purchases' : 'My Sales'; ?>
                    </h4>
                </div>
                <div class="card-body">
                    <?php if (empty($transactions)): ?>
                        <div class="text-center py-5">
                            <i class="fas <?php echo $type === 'purchases' ? 'fa-shopping-cart' : 'fa-store'; ?> fa-3x text-muted mb-3"></i>
                            <p class="lead">No transactions found.</p>
                            <?php if ($type === 'purchases'): ?>
                                <a href="index.php" class="btn btn-primary">Browse Books</a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($transactions as $transaction): ?>
                                <div class="list-group-item">
                                    <div class="row align-items-center">
                                        <div class="col-md-2">
                                            <?php if (!empty($transaction['cover_image'])): ?>
                                                <img src="<?php echo htmlspecialchars($transaction['cover_image']); ?>" 
                                                     class="img-thumbnail" alt="Book cover">
                                            <?php else: ?>
                                                <div class="bg-light p-3 text-center">
                                                    <i class="fas fa-book fa-2x text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-4">
                                            <h5 class="mb-1">
                                                <a href="listing.php?id=<?php echo $transaction['listing_id']; ?>" 
                                                   class="text-decoration-none">
                                                    <?php echo htmlspecialchars($transaction['book_title']); ?>
                                                </a>
                                            </h5>
                                            <p class="text-muted mb-1">
                                                <?php if (!empty($transaction['author'])): ?>
                                                    by <?php echo htmlspecialchars($transaction['author']); ?>
                                                <?php endif; ?>
                                            </p>
                                            <p class="mb-0">
                                                <small class="text-muted">
                                                    <?php echo $type === 'purchases' ? 'Seller' : 'Buyer'; ?>: 
                                                    <?php if (!empty($transaction['other_party_id']) && !empty($transaction['other_party_name'])): ?>
                                                        <a href="profile.php?id=<?php echo htmlspecialchars($transaction['other_party_id']); ?>" 
                                                           class="text-decoration-none">
                                                            <?php echo htmlspecialchars($transaction['other_party_name']); ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">Unknown User</span>
                                                    <?php endif; ?>
                                                </small>
                                            </p>
                                        </div>
                                        <div class="col-md-2 text-center">
                                            <div class="mb-2">
                                                <span class="badge bg-<?php 
                                                    echo match($transaction['transaction_status']) {
                                                        'paid' => 'warning',
                                                        'shipped' => 'info',
                                                        'delivered' => 'primary',
                                                        'completed' => 'success',
                                                        'cancelled' => 'danger',
                                                        default => 'secondary'
                                                    };
                                                ?>">
                                                    <?php echo ucfirst($transaction['transaction_status']); ?>
                                                </span>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo date('M j, Y', strtotime($transaction['transaction_date'])); ?>
                                            </small>
                                        </div>
                                        <div class="col-md-4">
                                            <?php if ($type === 'sales'): ?>
                                                <?php if ($transaction['transaction_status'] === 'paid'): ?>
                                                    <form method="POST" class="mb-2">
                                                        <input type="hidden" name="transaction_id" value="<?php echo $transaction['transaction_id']; ?>">
                                                        <input type="hidden" name="action" value="update_shipping">
                                                        <div class="input-group input-group-sm mb-2">
                                                            <input type="text" class="form-control" name="tracking_code" placeholder="Tracking Number" required>
                                                        </div>
                                                        <div class="input-group input-group-sm mb-2">
                                                            <input type="text" class="form-control" name="courier_name" placeholder="Courier Name" required>
                                                        </div>
                                                        <button type="submit" class="btn btn-primary btn-sm">
                                                            <i class="fas fa-shipping-fast"></i> Update Shipping
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            <?php else: ?> <!-- Purchases -->
                                                <?php if ($transaction['transaction_status'] === 'shipped'): ?>
                                                    <form method="POST">
                                                        <input type="hidden" name="transaction_id" value="<?php echo $transaction['transaction_id']; ?>">
                                                        <input type="hidden" name="action" value="mark_delivered">
                                                        <button type="submit" class="btn btn-success btn-sm mb-2" 
                                                                onclick="return confirm('Are you sure you want to mark this as delivered?')">
                                                            <i class="fas fa-check"></i> Mark as Delivered
                                                        </button>
                                                    </form>
                                                <?php elseif ($transaction['transaction_status'] === 'delivered' && !$transaction['has_rated']): ?>
                                                    <form method="POST">
                                                        <input type="hidden" name="transaction_id" value="<?php echo $transaction['transaction_id']; ?>">
                                                        <input type="hidden" name="action" value="complete_transaction">
                                                        <div class="rating mb-2">
                                                            <?php for($i = 5; $i >= 1; $i--): ?>
                                                                <input type="radio" name="rating" value="<?php echo $i; ?>" id="star<?php echo $transaction['transaction_id']; ?>_<?php echo $i; ?>" required>
                                                                <label for="star<?php echo $transaction['transaction_id']; ?>_<?php echo $i; ?>"><i class="fas fa-star"></i></label>
                                                            <?php endfor; ?>
                                                        </div>
                                                        <div class="input-group input-group-sm mb-2">
                                                            <textarea class="form-control" name="comment" placeholder="Write your review..." required></textarea>
                                                        </div>
                                                        <button type="submit" class="btn btn-primary btn-sm">
                                                            <i class="fas fa-check"></i> Complete Transaction
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <nav class="mt-4">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?type=<?php echo $type; ?>&page=<?php echo $page - 1; ?>">
                                        Previous
                                    </a>
                                </li>
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?type=<?php echo $type; ?>&page=<?php echo $i; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?type=<?php echo $type; ?>&page=<?php echo $page + 1; ?>">
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

<style>
.rating {
    display: flex;
    flex-direction: row-reverse;
    justify-content: flex-end;
}
.rating input {
    display: none;
}
.rating label {
    cursor: pointer;
    padding: 0 0.1em;
    font-size: 1.5em;
    color: #ddd;
}
.rating input:checked ~ label {
    color: #ffd700;
}
.rating label:hover,
.rating label:hover ~ label {
    color: #ffd700;
}
</style>

<?php include 'includes/footer.php'; ?> 