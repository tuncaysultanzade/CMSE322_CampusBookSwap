<?php
require_once 'includes/auth.php';

// Set goodbye message
$_SESSION['flash'] = [
    'type' => 'success',
    'message' => 'You have been logged out successfully.'
];

// Destroy session and redirect
logout(); 