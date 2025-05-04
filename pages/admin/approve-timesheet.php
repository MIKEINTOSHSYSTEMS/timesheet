<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth-check.php';

//if (!hasRole(['admin', 'manager'])) {
if (!hasRole('admin', 'manager')) {
    $_SESSION['error_message'] = 'You do not have permission to perform this action';
    header('Location: ' . BASE_URL . '/pages/dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/pages/dashboard.php');
    exit;
}

$timesheet_id = isset($_POST['timesheet_id']) ? (int)$_POST['timesheet_id'] : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';

$db = new Database();
$pdo = $db->getConnection();

if ($action === 'approve') {
    $stmt = $pdo->prepare("
        UPDATE timesheets 
        SET status = 'approved', 
            approved_at = NOW(), 
            approved_by = ?,
            rejection_reason = NULL
        WHERE timesheet_id = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $timesheet_id]);
    $_SESSION['success_message'] = 'Timesheet approved successfully';
} elseif ($action === 'reject') {
    $rejection_reason = isset($_POST['rejection_reason']) ? $_POST['rejection_reason'] : '';
    $stmt = $pdo->prepare("
        UPDATE timesheets 
        SET status = 'rejected', 
            approved_at = NULL,
            approved_by = NULL,
            rejection_reason = ?
        WHERE timesheet_id = ?
    ");
    $stmt->execute([$rejection_reason, $timesheet_id]);
    $_SESSION['success_message'] = 'Timesheet rejected successfully';
}

header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? BASE_URL . '/pages/dashboard.php'));
exit;
