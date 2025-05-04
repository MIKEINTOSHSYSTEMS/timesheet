<?php
require_once __DIR__ . '/config/config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/pages/dashboard.php');
    exit;
} else {
    header('Location: ' . BASE_URL . '/pages/login.php');
    exit;
}