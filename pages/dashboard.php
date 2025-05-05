<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../includes/header.php';

$user = (new User())->getUserById($_SESSION['user_id']);
$timesheet = new Timesheet();
$currentMonth = DateConverter::getCurrentMonth();
$currentYear = DateConverter::getCurrentYear();

// Handle filter parameters
$filterMonth = isset($_GET['month']) ? (int)$_GET['month'] : null;
$filterYear = isset($_GET['year']) ? (int)$_GET['year'] : null;

// Get all timesheets with optional filters
$allTimesheets = $timesheet->getAllUserTimesheets($_SESSION['user_id'], $filterMonth, $filterYear);

// Get current timesheet status
$currentTimesheet = $timesheet->getTimesheet($_SESSION['user_id'], $currentMonth, $currentYear);
?>

<div class="container">
    <h1 class="mb-4">Dashboard</h1>

    <div class="row">
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">User Profile</h5>
                </div>
                <div class="card-body">
                    <p><strong>Name:</strong> <?= e($user['first_name'] . ' ' . $user['last_name']) ?></p>
                    <p><strong>Email:</strong> <?= e($user['email']) ?></p>
                    <p><strong>Role:</strong> <?= ucfirst(e($user['role'])) ?></p>
                    <p><strong>Current Date:</strong> <?= DateConverter::formatDate(date('Y-m-d')) ?></p>
                    <a href="<?= BASE_URL ?>/pages/profile.php" class="btn btn-sm btn-outline-primary">Edit Profile</a>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Timesheet Filters</h5>
                </div>
                <div class="card-body">
                    <form method="get" action="">
                        <div class="mb-3">
                            <label for="month" class="form-label">Month</label>
                            <select class="form-select" id="month" name="month">
                                <option value="">All Months</option>
                                <?php for ($m = 1; $m <= (CalendarHelper::isEthiopian() ? 13 : 12); $m++): ?>
                                    <option value="<?= $m ?>" <?= $filterMonth === $m ? 'selected' : '' ?>>
                                        <?= CalendarHelper::getMonthName($m) ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="year" class="form-label">Year</label>
                            <select class="form-select" id="year" name="year">
                                <option value="">All Years</option>
                                <?php
                                $currentYear = CalendarHelper::isEthiopian() ? DateConverter::getCurrentYear() : date('Y');
                                for ($y = $currentYear - 5; $y <= $currentYear + 1; $y++): ?>
                                    <option value="<?= $y ?>" <?= $filterYear === $y ? 'selected' : '' ?>><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="<?= BASE_URL ?>/pages/dashboard.php" class="btn btn-secondary">Reset</a>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Current Month Timesheet</h5>
                </div>
                <div class="card-body">
                    <?php if ($currentTimesheet): ?>
                        <div class="alert alert-<?=
                                                $currentTimesheet['status'] === 'approved' ? 'success' : ($currentTimesheet['status'] === 'submitted' ? 'info' : 'warning')
                                                ?>">
                            <strong>Status:</strong> <?= ucfirst($currentTimesheet['status']) ?>
                            <?php if ($currentTimesheet['status'] === 'approved' && $currentTimesheet['approved_at']): ?>
                                <br><small>Approved on: <?= DateConverter::formatDate($currentTimesheet['approved_at'], 'M j, Y') ?></small>
                            <?php endif; ?>
                        </div>

                        <div class="progress mb-3">
                            <div class="progress-bar" role="progressbar"
                                style="width: <?= $currentTimesheet['completion_percentage'] ?? 0 ?>%"
                                aria-valuenow="<?= $currentTimesheet['completion_percentage'] ?? 0 ?>"
                                aria-valuemin="0" aria-valuemax="100">
                                <?= $currentTimesheet['completion_percentage'] ?? 0 ?>%
                            </div>
                        </div>

                        <p>Total hours logged: <?= $currentTimesheet['total_hours'] ?? 0 ?></p>
                        <p>Allocated hours: <?= $currentTimesheet['allocated_hours'] ?? 'N/A' ?></p>

                        <a href="<?= BASE_URL ?>/pages/timesheet.php" class="btn btn-primary">
                            <?= $currentTimesheet['status'] === 'draft' ? 'Continue Editing' : 'View Timesheet' ?>
                        </a>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <p>You haven't started your timesheet for this month yet.</p>
                            <a href="<?= BASE_URL ?>/pages/timesheet.php" class="btn btn-primary">Create Timesheet</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">All Timesheets</h5>
                </div>
                <div class="card-body">
                    <?php if ($allTimesheets): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Month</th>
                                        <th>Year</th>
                                        <th>Status</th>
                                        <th>Total Hours</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allTimesheets as $ts):
                                        $canEdit = $timesheet->canEditTimesheet($ts['timesheet_id'], $_SESSION['user_id'], $user['role'] === 'admin');
                                    ?>
                                        <tr>
                                            <td><?= CalendarHelper::getMonthName($ts['month']) ?></td>
                                            <td><?= $ts['year'] ?></td>
                                            <td>
                                                <span class="badge bg-<?=
                                                                        $ts['status'] === 'approved' ? 'success' : ($ts['status'] === 'submitted' ? 'info' : ($ts['status'] === 'rejected' ? 'danger' : 'warning'))
                                                                        ?>">
                                                    <?= ucfirst($ts['status']) ?>
                                                </span>
                                                <?php if ($ts['status'] === 'rejected' && !empty($ts['rejection_reason'])): ?>
                                                    <br><small>Reason: <?= e($ts['rejection_reason']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= $ts['total_hours'] ?? 0 ?></td>
                                            <td>
                                                <a href="<?= BASE_URL ?>/pages/timesheet.php?month=<?= $ts['month'] ?>&year=<?= $ts['year'] ?>"
                                                    class="btn btn-sm btn-outline-primary">View</a>
                                                <?php if ($canEdit): ?>
                                                    <a href="<?= BASE_URL ?>/pages/timesheet.php?month=<?= $ts['month'] ?>&year=<?= $ts['year'] ?>"
                                                        class="btn btn-sm btn-outline-secondary">Edit</a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p>No timesheets found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>