<?php require_once __DIR__ . '/auth.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
        <div class="container">
            <a class="navbar-brand" href="/index.php"><?= SITE_NAME ?></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/index.php"><i class="fas fa-home"></i> Home</a>
                    </li>
                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/add-listing.php"><i class="fas fa-plus"></i> Add Listing</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/favorites.php"><i class="fas fa-heart"></i> Favorites</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/messages.php">
                                <i class="fas fa-envelope"></i> Messages
                                <span id="unreadMessageCount" class="badge bg-danger rounded-pill" style="display: none;"></span>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <?php if (isLoggedIn()): ?>
                        <?php if (isAdmin()): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="/admin/"><i class="fas fa-cog"></i> Admin</a>
                            </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/dashboard.php"><i class="fas fa-user"></i> Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/register.php"><i class="fas fa-user-plus"></i> Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    
    <div class="container mb-4">
        <?php if (isset($_SESSION['flash'])): ?>
            <div class="alert alert-<?= $_SESSION['flash']['type'] ?> alert-dismissible fade show">
                <?= $_SESSION['flash']['message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['flash']); ?>
        <?php endif; ?> 
    </div>
    
    <?php if (isLoggedIn()): ?>
    <script>
    function updateUnreadMessageCount() {
        $.ajax({
            url: 'ajax/get_unread_count.php',
            method: 'GET',
            dataType: 'json',
            success: function(data) {
                const badge = $('#unreadMessageCount');
                if (data.success && data.unreadCount > 0) {
                    badge.text(data.unreadCount).show();
                } else {
                    badge.hide();
                }
            },
            error: function() {
                console.log('Error fetching unread message count');
            }
        });
    }

    // Update unread count every 30 seconds
    $(document).ready(function() {
        updateUnreadMessageCount();
        setInterval(updateUnreadMessageCount, 30000);
    });
    </script>
    <?php endif; ?>

    <div class="container mb-4"> 