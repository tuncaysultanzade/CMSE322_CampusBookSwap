<?php
require_once __DIR__ . '/db.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: index.php');
        exit();
    }
}

function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

function login($email, $password) {
    $conn = getDbConnection();
    $stmt = prepareStatement("SELECT user_id, pass_hash, is_admin, is_blocked FROM user WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        if ($row['is_blocked']) {
            return ['success' => false, 'message' => 'Your account has been blocked. Please contact support.'];
        }
        
        if (password_verify($password, $row['pass_hash'])) {
            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['is_admin'] = (bool)$row['is_admin'];
            return ['success' => true];
        }
    }
    
    return ['success' => false, 'message' => 'Invalid email or password.'];
}

function register($name, $email, $password, $phone) {
    $conn = getDbConnection();
    
    // Check if email already exists
    $stmt = prepareStatement("SELECT user_id FROM user WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        return ['success' => false, 'message' => 'Email already registered.'];
    }
    
    // Hash password and insert new user
    $pass_hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = prepareStatement("INSERT INTO user (name, email, phone, pass_hash, reg_date) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssss", $name, $email, $phone, $pass_hash);
    
    if ($stmt->execute()) {
        return ['success' => true];
    }
    
    return ['success' => false, 'message' => 'Registration failed. Please try again.'];
}

function logout() {
    session_destroy();
    header('Location: login.php');
    exit();
} 