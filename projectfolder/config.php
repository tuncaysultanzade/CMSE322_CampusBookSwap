<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'campusbookswap');
define('DB_PASS', '134679');
define('DB_NAME', 'campusbookswap');

// Site configuration
define('SITE_NAME', 'Campus BookSwap');
define('SITE_URL', 'http://localhost/campusbookswap');
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png']);

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
session_start();

// Error reporting (disable in production)
//error_reporting(E_ALL);
ini_set('display_errors', 0);

// Time zone
date_default_timezone_set('Europe/Istanbul'); 