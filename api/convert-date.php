<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (!isset($_GET['date'])) {
    echo json_encode(['success' => false, 'error' => 'Date parameter missing']);
    exit;
}

try {
    require_once __DIR__ . '/../modules/calendar/EthiopianCalendar.php';
    $ec = new EthiopianCalendar(strtotime($_GET['date']));

    echo json_encode([
        'success' => true,
        'ethiopianDate' => $ec->format('d M Y'), // Assuming 'format' is the correct method
        'gregorianDate' => date('d M Y', strtotime($_GET['date']))
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
