<?php
require_once __DIR__ . '/config/config.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    echo "Database connection successful!<br>";
    
    // Test users table
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    echo "Users count: " . $stmt->fetchColumn() . "<br>";
    
    // Test getting a user
    $user = (new User())->getUserById(1);
    echo "First user email: " . ($user['email'] ?? 'Not found') . "<br>";
    
    // Test timesheet functions
    $timesheet = new Timesheet();
    $current = $timesheet->getTimesheet(1, date('n'), date('Y'));
    echo "Timesheet test: " . ($current ? "Success" : "No data") . "<br>";
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}