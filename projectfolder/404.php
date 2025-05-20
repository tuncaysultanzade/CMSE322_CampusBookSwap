<?php
require_once 'includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center my-5">
        <div class="col-md-6 text-center">
            <h1 class="display-1 text-primary mb-4">404</h1>
            <h2 class="mb-4">Page Not Found</h2>
            <p class="lead mb-4">The page you're looking for doesn't exist or has been moved.</p>
            
            <div>
                <a href="/" class="btn btn-primary me-2">
                    <i class="fas fa-home"></i> Go Home
                </a>
                <a href="javascript:history.back()" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Go Back
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 