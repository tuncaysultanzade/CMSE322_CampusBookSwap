<?php
http_response_code(500);
require_once 'includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center my-5">
        <div class="col-md-6 text-center">
            <h1 class="display-1 text-danger mb-4">500</h1>
            <h2 class="mb-4">Server Error</h2>
            <p class="lead mb-4">Something went wrong on our end. We're working to fix it.</p>
           
            <div>
                <a href="/" class="btn btn-primary me-2">
                    <i class="fas fa-home"></i> Go Home
                </a>
                <a href="javascript:location.reload()" class="btn btn-outline-secondary">
                    <i class="fas fa-redo"></i> Try Again
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 