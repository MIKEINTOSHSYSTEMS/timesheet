<?php
require_once __DIR__ . '/../config/config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION['ethiopian_calendar'] = isset($_POST['ethiopian_calendar']) && $_POST['ethiopian_calendar'] == '1';

// Initialize redirect URL safely
$redirect = BASE_URL . '/pages/dashboard.php';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Check if the checkbox was checked (value=1) or not (not submitted at all)
        $ethiopianCalendar = isset($_POST['ethiopian_calendar']) && $_POST['ethiopian_calendar'] == '1' ? 1 : 0;
        $_SESSION['ethiopian_calendar'] = $ethiopianCalendar;

        // Update user preference in database if logged in
        if (isset($_SESSION['user_id'])) {
            $db = new Database();
            $pdo = $db->getConnection();
            $stmt = $pdo->prepare("
                UPDATE user_profiles 
                SET ethiopian_calendar_preference = ?
                WHERE user_id = ?
            ");
            $stmt->execute([$ethiopianCalendar, $_SESSION['user_id']]);
        }

        // Set current date in the selected calendar
        require_once __DIR__ . '/../classes/DateConverter.php';
        require_once __DIR__ . '/../classes/CalendarHelper.php';
        CalendarHelper::init();
        $currentDate = CalendarHelper::getCurrentDate();
    }

    // Safely get redirect URL
    if (isset($_POST['redirect'])) {
        $redirect = filter_var($_POST['redirect'], FILTER_SANITIZE_URL);
        // Ensure the redirect stays within our domain
        if (strpos($redirect, BASE_URL) !== 0) {
            $redirect = BASE_URL . '/pages/dashboard.php';
        }
    }
} catch (Exception $e) {
    error_log("Calendar switch error: " . $e->getMessage());
    $_SESSION['error_message'] = 'Failed to update calendar preference';
}

header('Location: ' . $redirect);
exit;
?>

header('Location: ' . $redirect);
exit;
