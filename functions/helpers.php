<?php

/**
 * Common helper functions for the MERQ Timesheet application
 */

/**
 * Redirect to a different page
 */
function redirect($url)
{
    header("Location: $url");
    exit();
}

/**
 * Escape HTML output to prevent XSS attacks
 */
function e($string)
{
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Format date based on user's calendar preference
 */
function formatDate($date, $ethiopian = false)
{
    if ($ethiopian) {
        // TODO: Implement Ethiopian date formatting
        return date('d M Y', strtotime($date));
    }
    return date('d M Y', strtotime($date));
}

/**
 * Get translated month name
 */
function getMonthName($monthNumber, $language = 'en')
{
    global $translation;
    return $translation->getMonthName($monthNumber);
}

/**
 * Display flash messages
 */
function displayFlashMessages()
{
    if (isset($_SESSION['success_message'])) {
        echo '<div class="alert alert-success">' . e($_SESSION['success_message']) . '</div>';
        unset($_SESSION['success_message']);
    }
    if (isset($_SESSION['error_message'])) {
        echo '<div class="alert alert-danger">' . e($_SESSION['error_message']) . '</div>';
        unset($_SESSION['error_message']);
    }
}

/**
 * Check if user has specific role
 */
function hasRole($role)
{
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

/**
 * Get current URL with query parameters
 */
function currentUrl()
{
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $uri = $_SERVER['REQUEST_URI'];
    return "$protocol://$host$uri";
}

/**
 * Generate CSRF token
 */
function generateCsrfToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCsrfToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get days in month
 */
function getDaysInMonth($month, $year)
{
    return cal_days_in_month(CAL_GREGORIAN, $month, $year);
}

/**
 * Calculate working days in month
 */
function getWorkingDays($month, $year)
{
    $days = getDaysInMonth($month, $year);
    $workingDays = 0;

    for ($day = 1; $day <= $days; $day++) {
        $time = mktime(0, 0, 0, $month, $day, $year);
        if (date('N', $time) < 6) { // 6 and 7 are weekend days
            $workingDays++;
        }
    }

    return $workingDays;
}
    