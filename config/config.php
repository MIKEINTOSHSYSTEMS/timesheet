<?php

// Error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


require_once __DIR__ . '/database.php';

// Session configuration
session_start();

// Application settings
define('APP_NAME', 'MERQ Timesheet');
define('APP_VERSION', '1.0.0');
//define('BASE_URL', 'https://timesheet.merqconsultancy.org/timesheet');
define('BASE_URL', 'http://merqconsultancy/timesheet');

//$_SESSION['ethiopian_calendar'] = $_SESSION['ethiopian_calendar'] ?? false;
$_SESSION['ethiopian_calendar'] = $_SESSION['ethiopian_calendar'] ?? true;

//$_SESSION['success_message'] = 'Calendar preference updated successfully';

// Include necessary files
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Timesheet.php';
require_once __DIR__ . '/../classes/Translation.php';
require_once __DIR__ . '/../functions/helpers.php';