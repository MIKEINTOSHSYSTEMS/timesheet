<?php
require_once __DIR__ . '/../config/config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize redirect URL safely
$redirect = BASE_URL . '/pages/dashboard.php';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['language'])) {
        $language = filter_var($_POST['language'], FILTER_SANITIZE_STRING);
        $_SESSION['language'] = $language;
        
        // Update user preference in database if logged in
        if (isset($_SESSION['user_id'])) {
            $db = new Database();
            $pdo = $db->getConnection();
            $stmt = $pdo->prepare("
                UPDATE user_profiles 
                SET preferred_language = ?
                WHERE user_id = ?
            ");
            $stmt->execute([$language, $_SESSION['user_id']]);
        }
    }
    
    // Safely get redirect URL
    if (isset($_POST['redirect'])) {
        $redirect = filter_var($_POST['redirect'], FILTER_SANITIZE_URL);
        // Ensure the redirect stays within our domain
        if (!str_starts_with($redirect, BASE_URL)) {
            $redirect = BASE_URL . '/pages/dashboard.php';
        }
    }
} catch (Exception $e) {
    error_log("Language switch error: " . $e->getMessage());
    $_SESSION['error_message'] = 'Failed to update language preference';
}

header('Location: ' . $redirect);
exit;