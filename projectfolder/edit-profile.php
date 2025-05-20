<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$page_title = "Edit Profile";
require_once 'includes/header.php';

// Fetch current user data
$stmt = prepareStatement("SELECT * FROM user WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate email uniqueness
    $stmt = prepareStatement("SELECT user_id FROM user WHERE email = ? AND user_id != ?");
    $stmt->bind_param("si", $email, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows > 0) {
        $error = "Email already exists!";
    } else {
        $conn = getDbConnection();
        $conn->begin_transaction();

        try {
            // Update basic info
            $stmt = prepareStatement("
                UPDATE user 
                SET name = ?, email = ?, phone = ?
                WHERE user_id = ?
            ");
            $stmt->bind_param("sssi", $name, $email, $phone, $user_id);
            $stmt->execute();
            $stmt->close();

            // Update password if provided
            if (!empty($current_password) && !empty($new_password)) {
                if ($new_password !== $confirm_password) {
                    throw new Exception("New passwords do not match!");
                }

                // Verify current password
                if (!password_verify($current_password, $user['pass_hash'])) {
                    throw new Exception("Current password is incorrect!");
                }

                // Update password
                $new_pass_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = prepareStatement("UPDATE user SET pass_hash = ? WHERE user_id = ?");
                $stmt->bind_param("si", $new_pass_hash, $user_id);
                $stmt->execute();
                $stmt->close();
            }

            $conn->commit();

            // Fetch updated user data
            $stmt = prepareStatement("SELECT * FROM user WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $success = "Profile updated successfully!";
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Edit Profile</h4>
                </div>
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <form method="post" action="">
                        <div class="form-group mb-3">
                            <label for="name">Name</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($user['name']); ?>" required>
                        </div>

                        <div class="form-group mb-3">
                            <label for="email">Email</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>

                        <div class="form-group mb-3">
                            <label for="phone">Phone</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($user['phone']); ?>">
                        </div>

                        <hr>
                        <h5>Change Password</h5>
                        <p class="text-muted">Leave blank to keep current password</p>

                        <div class="form-group mb-3">
                            <label for="current_password">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password">
                        </div>

                        <div class="form-group mb-3">
                            <label for="new_password">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password">
                        </div>

                        <div class="form-group mb-3">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Update Profile</button>
                            <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 