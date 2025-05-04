<?php
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    $_SESSION['error_message'] = 'Please login to access this page';
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

// Check if user is active
$user = (new User())->getUserById($_SESSION['user_id']);
if (!$user || !$user['is_active']) {
    session_destroy();
    $_SESSION['error_message'] = 'Your account is not active. Please contact administrator.';
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}
/*
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header('Location: ' . BASE_URL . '/pages/login.php');
    exit;
}

// Function to check user roles
function hasRole($roles) {
    if (!isset($_SESSION['role'])) {
        return false;
    }
    
    if (is_array($roles)) {
        return in_array($_SESSION['role'], $roles);
    }
    
    return $_SESSION['role'] === $roles;
}

// Function to check if user is active
function isActive() {
    return isset($_SESSION['is_active']) && $_SESSION['is_active'];
}


*/