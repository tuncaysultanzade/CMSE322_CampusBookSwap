<?php
require_once 'config.php';
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Ensure user is logged in
if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: login.php');
    exit();
}

$listing_id = $_GET['id'] ?? null;
$error = '';
$success = '';

if (!$listing_id) {
    header('Location: index.php');
    exit();
}

// Get listing details
$stmt = prepareStatement("
    SELECT 
        l.*,
        b.title as book_title,
        b.author,
        b.publisher,
        b.publisher_year,
        u.name as seller_name,
        u.email as seller_email,
        (SELECT image_url FROM listing_image WHERE listing_id = l.listing_id LIMIT 1) as cover_image
    FROM listing l
    JOIN book b ON l.book_id = b.book_id
    JOIN user u ON l.user_id = u.user_id
    WHERE l.listing_id = ? 
    AND l.listing_status = 'active'
    AND l.listing_type = 'sale'
    AND l.admin_approved = 1
");
$stmt->bind_param("i", $listing_id);
$stmt->execute();
$listing = $stmt->get_result()->fetch_assoc();

if (!$listing) {
    header('Location: index.php');
    exit();
}

// Prevent buying own listing
if ($listing['user_id'] === getCurrentUserId()) {
    header('Location: listing.php?id=' . $listing_id);
    exit();
}

// Handle checkout submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn = getDbConnection();
        $conn->begin_transaction();

        // Create transaction record
        $stmt = prepareStatement("
            INSERT INTO transaction (
                listing_id, buyer_id,
                amount, transaction_type, transaction_status, transaction_date,
                delivery_address, tracking_code, courier_name
            ) VALUES (?, ?, ?, 'purchase', 'paid', NOW(), ?, NULL, NULL)
        ");
        
        $buyer_id = getCurrentUserId();
        $delivery_address = $_POST['delivery_address'];

        $stmt->bind_param("iids",
            $listing_id,
            $buyer_id,
            $listing['price'],
            $delivery_address
        );
        $stmt->execute();
        $transaction_id = $conn->insert_id;

        // Update listing status
        $stmt = prepareStatement("
            UPDATE listing 
            SET listing_status = 'sold'
            WHERE listing_id = ?
        ");
        $stmt->bind_param("i", $listing_id);
        $stmt->execute();

        $conn->commit();
        $success = 'Order placed successfully! You will be notified when the seller confirms the order.';

    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollback();
        }
        $error = 'Error processing your order. Please try again.';
    }
}

include 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <?php if ($success): ?>
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="fas fa-check-circle text-success fa-4x mb-3"></i>
                        <h3>Thank You!</h3>
                        <p class="lead"><?php echo $success; ?></p>
                        <div class="mt-4">
                            <a href="transactions.php" class="btn btn-primary">View Your Orders</a>
                            <a href="index.php" class="btn btn-secondary">Continue Shopping</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Order Summary -->
            <div class="col-md-4 order-md-2 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">Order Summary</h4>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <?php if ($listing['cover_image']): ?>
                                <img src="<?php echo htmlspecialchars($listing['cover_image']); ?>" 
                                     class="img-thumbnail me-3" style="width: 80px;" alt="Book cover">
                            <?php endif; ?>
                            <div>
                                <h5 class="mb-1"><?php echo htmlspecialchars($listing['book_title']); ?></h5>
                                <p class="text-muted mb-0"><?php echo htmlspecialchars($listing['author']); ?></p>
                            </div>
                        </div>

                        <ul class="list-group list-group-flush mb-3">
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Price</span>
                                <strong><?php echo $listing['price'] ? number_format($listing['price'], 2).' ₺' : '0.00 ₺'; ?></strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Seller</span>
                                <span class="text-muted"><?php echo htmlspecialchars($listing['seller_name']); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Publisher</span>
                                <span class="text-muted"><?php echo htmlspecialchars($listing['publisher']); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Year</span>
                                <span class="text-muted"><?php echo htmlspecialchars($listing['publisher_year']); ?></span>
                            </li>
                        </ul>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            Payment will be handled securely when the seller confirms the order.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Checkout Form -->
            <div class="col-md-8 order-md-1">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">Checkout Information</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <form method="POST" id="checkoutForm">
                            <h5 class="mb-3">Payment Information</h5>
                            <div class="row g-3 mb-4">
                                <div class="col-12">
                                    <label class="form-label">Card Number</label>
                                    <input type="text" class="form-control" name="card_number" 
                                           placeholder="1234 5678 9012 3456" required
                                           pattern="[0-9\s]{13,19}" maxlength="19">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Expiration Date</label>
                                    <input type="text" class="form-control" name="card_expiry" 
                                           placeholder="MM/YY" required
                                           pattern="(0[1-9]|1[0-2])\/([0-9]{2})" maxlength="5">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">CVV</label>
                                    <input type="text" class="form-control" name="card_cvv" 
                                           placeholder="123" required
                                           pattern="[0-9]{3,4}" maxlength="4">
                                </div>
                            </div>

                            <h5 class="mb-3">Delivery Information</h5>
                            <div class="mb-4">
                                <label class="form-label">Delivery Address</label>
                                <textarea class="form-control" name="delivery_address" 
                                          rows="3" required
                                          placeholder="Enter your complete delivery address"></textarea>
                            </div>

                            <hr class="my-4">

                            <div class="form-check mb-4">
                                <input type="checkbox" class="form-check-input" id="terms" required>
                                <label class="form-check-label" for="terms">
                                    I agree to the terms and conditions
                                </label>
                            </div>

                            <div class="d-grid gap-2">
                                <button class="btn btn-primary btn-lg" type="submit">
                                    Place Order
                                </button>
                                <a href="listing.php?id=<?php echo $listing_id; ?>" 
                                   class="btn btn-outline-secondary">
                                    Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
$(document).ready(function() {
    // Format card number with spaces
    $('input[name="card_number"]').on('input', function() {
        $(this).val($(this).val().replace(/[^\d]/g, '').replace(/(.{4})/g, '$1 ').trim());
    });

    // Format expiry date
    $('input[name="card_expiry"]').on('input', function() {
        var v = $(this).val().replace(/\D/g, '');
        if (v.length >= 2) {
            $(this).val(v.substring(0,2) + '/' + v.substring(2));
        }
    });

    // Validate form
    $('#checkoutForm').submit(function(e) {
        if (!$('#terms').is(':checked')) {
            e.preventDefault();
            alert('Please agree to the terms and conditions.');
            return;
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?> 