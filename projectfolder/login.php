<?php
require_once 'includes/header.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (!$email || !$password) {
        $error = 'Please fill in all fields.';
    } else {
        $result = login($email, $password);
        
        if ($result['success']) {
            // Set welcome message
            $_SESSION['flash'] = [
                'type' => 'success',
                'message' => 'Welcome back!'
            ];
            
            // Redirect to home page
            header('Location: index.php');
            exit();
        } else {
            $error = $result['message'];
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h3 class="card-title text-center mb-4">Login</h3>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" data-validate>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email address</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                    </div>
                    
                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">
                        Login
                    </button>
                </form>
                
                <hr class="my-4">
                
                <p class="text-center mb-0">
                    Don't have an account? 
                    <a href="register.php" class="text-decoration-none">Register here</a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 