<?php
require_once __DIR__ . '/../../config/config.php';
header('Content-Type: application/json');

$emailService = new EmailService();

if (isset($_GET['id'])) {
    $template = $emailService->getEmailTemplateById($_GET['id']);
    echo json_encode($template);
    exit;
}

echo json_encode([]);
