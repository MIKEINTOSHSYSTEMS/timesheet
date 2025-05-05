<?php
require_once __DIR__ . '/../config/config.php';

// Initialize calendar preference
$ethiopianCalendar = $_SESSION['ethiopian_calendar'] ?? false;

// Initialize language switcher variables
$translation = new Translation();
$languages = [
    'en' => 'English',
    'am' => 'አማርኛ'
];
$currentLanguage = $_SESSION['language'] ?? 'en';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - <?= $pageTitle ?? 'Timesheet' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Ethiopic&display=swap" rel="stylesheet">
    <script src="<?= BASE_URL ?>/assets/js/script.js"></script>
    <style>
        .ethiopian-text {
            font-family: 'Noto Sans Ethiopic', sans-serif;
        }
    </style>
    <?php if ($ethiopianCalendar): ?>
        <link rel="stylesheet" href="<?= BASE_URL ?>/modules/calendar/calendar.css">
        <script src="<?= BASE_URL ?>/modules/calendar/calendar.js"></script>
        <script>
            // Complete conversion functions as shown above
            function convertToEthiopian(dateStr) {
                const date = new Date(dateStr);
                const ec = new EthiopianCalendar(date);
                return {
                    date: ec.GetECDate('Y-m-d'),
                    year: ec.EC_year,
                    month: ec.EC_month,
                    day: ec.EC_day
                };
            }

            function convertToGregorian(ethDateStr) {
                const parts = ethDateStr.split('-');
                const ecYear = parseInt(parts[0]);
                const ecMonth = parseInt(parts[1]);
                const ecDay = parseInt(parts[2]);

                const ec = new EthiopianCalendar(new Date());
                const gcDate = ec.ethiopianToGregorian(ecYear, ecMonth, ecDay);

                // Format as YYYY-MM-DD
                const gcDateStr = `${gcDate.year}-${gcDate.month.toString().padStart(2, '0')}-${gcDate.day.toString().padStart(2, '0')}`;
                return {
                    date: gcDateStr,
                    year: gcDate.year,
                    month: gcDate.month,
                    day: gcDate.day
                };
            }

            // Helper function to update all date displays on the page
            function updateDateDisplays() {
                document.querySelectorAll('[data-date]').forEach(element => {
                    const dateStr = element.getAttribute('data-date');
                    if (document.body.classList.contains('ethiopian-calendar')) {
                        const ethDate = convertToEthiopian(dateStr);
                        element.textContent = ethDate.date;
                        element.classList.add('ethiopian-text');
                    } else {
                        // For Gregorian, just display as-is
                        element.textContent = dateStr;
                        element.classList.remove('ethiopian-text');
                    }
                });
            }

            // Initialize on page load
            document.addEventListener('DOMContentLoaded', function() {
                updateDateDisplays();

                // Update when calendar switch is toggled
                const calendarSwitch = document.getElementById('calendarSwitch');
                if (calendarSwitch) {
                    calendarSwitch.addEventListener('change', function() {
                        updateDateDisplays();
                    });
                }
            });

            // Initialize on page load
            document.addEventListener('DOMContentLoaded', function() {
                updateDateDisplays();

                // Update when calendar switch is toggled
                const calendarSwitch = document.getElementById('calendarSwitch');
                if (calendarSwitch) {
                    calendarSwitch.addEventListener('change', function() {
                        if (this.checked) {
                            document.body.classList.add('ethiopian-calendar');
                        } else {
                            document.body.classList.remove('ethiopian-calendar');
                        }
                        updateDateDisplays();
                    });
                }
            });
        </script>
    <?php endif; ?>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
        <div class="container-fluid px-3 px-lg-4">
            <a class="navbar-brand d-flex align-items-center" href="<?= BASE_URL ?>">
                <i class="bi bi-calendar-check"></i>
                <span class="d-none d-sm-inline"><?= APP_NAME ?></span>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent" aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>/pages/dashboard.php">
                                <i class="bi bi-speedometer2"></i> <span class="d-none d-md-inline">Dashboard</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>/pages/timesheet.php">
                                <i class="bi bi-journal-text"></i> <span class="d-none d-md-inline">Timesheet</span>
                            </a>
                        </li>

                        <?php if (hasRole('admin', 'manager')): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bi bi-gear"></i> <span class="d-none d-md-inline">Admin</span>
                                </a>
                                <ul class="dropdown-menu" aria-labelledby="adminDropdown">
                                    <?php if (hasRole('admin')): ?>
                                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/pages/admin/users.php"><i class="bi bi-people"></i> User Management</a></li>
                                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/pages/admin/projects.php"><i class="bi bi-folder"></i> Project Management</a></li>
                                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/pages/admin/settings.php"><i class="bi bi-sliders"></i> System Settings</a></li>
                                        <li>
                                            <hr class="dropdown-divider">
                                        </li>
                                    <?php endif; ?>
                                    <li><a class="dropdown-item" href="<?= BASE_URL ?>/pages/admin/reports.php"><i class="bi bi-graph-up"></i> Reports</a></li>
                                    <li><a class="dropdown-item" href="<?= BASE_URL ?>/pages/admin/"><i class="bi bi-speedometer2"></i> Admin Dashboard</a></li>
                                </ul>
                            </li>
                        <?php endif; ?>
                    <?php endif; ?>
                </ul>

                <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-lg-center">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <!-- Language Switcher - made more compact -->
                        <!-- Remove this entire language switcher section from header.php -->
                        <!-- <li class="nav-item me-2">
    <div class="language-switcher">
        <form method="post" action="<?= BASE_URL ?>/process/language-process.php" class="d-flex">
            <select name="language" class="form-select form-select-sm" onchange="this.form.submit()">
                <?php foreach ($languages as $code => $name): ?>
                    <option value="<?= $code ?>" <?= $currentLanguage === $code ? 'selected' : '' ?>>
                        <?= strtoupper($code) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" name="redirect" value="<?= $_SERVER['REQUEST_URI'] ?>">
        </form>
    </div>
</li> -->

                        <!-- Calendar Switcher - made more compact -->
                        <li class="nav-item me-2">
                            <div class="calendar-switcher">
                                <form method="post" action="<?= BASE_URL ?>/process/calendar-process.php" class="d-flex align-items-center">
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" id="calendarSwitch"
                                            name="ethiopian_calendar" value="1"
                                            <?= $ethiopianCalendar ? 'checked' : '' ?> onchange="this.form.submit()">
                                        <label class="form-check-label text-white" for="calendarSwitch">
                                            <img src="<?= BASE_URL ?>/assets/images/ethcal.png" alt="Ethiopian Calendar" style="height: 20px;">
                                        </label>

                                    </div>
                                    <input type="hidden" name="redirect" value="<?= $_SERVER['REQUEST_URI'] ?>">
                                </form>
                            </div>
                        </li>

                        <!-- User Profile Dropdown -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person-circle me-1"></i>

                                <span class="d-none d-lg-inline"><?= e($user['first_name'] . ' ' . $user['last_name']) ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>/pages/profile.php"><i class="bi bi-person"></i> Profile</a></li>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>/pages/change-password.php"><i class="bi bi-key"></i> Change Password</a></li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>/pages/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>/pages/login.php">
                                <i class="bi bi-box-arrow-in-right"></i> <span class="d-none d-md-inline">Login</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>/pages/register.php">
                                <i class="bi bi-person-plus"></i> <span class="d-none d-md-inline">Register</span>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid px-3 px-lg-4 py-3">
        <?php displayFlashMessages();

        ?>
    </div>
    <!--
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>

    </script>

                    -->