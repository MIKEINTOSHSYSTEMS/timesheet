<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../includes/header.php';

displayFlashMessages();

$user_id = $_SESSION['user_id'];
$timesheet = new Timesheet();
$translation = new Translation();

// Get current month/year or from request
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Get existing timesheet data FIRST
$currentTimesheet = $timesheet->getTimesheet($user_id, $month, $year);
$currentEntries = [];
$canSubmit = true; // Flag to control submission

if ($currentTimesheet) {
    // Create a more accessible structure for entries
    foreach ($currentTimesheet['entries'] ?? [] as $entry) {
        $currentEntries[$entry['project_id']] = $entry;
    }

    // Check if already submitted
    if ($currentTimesheet['status'] === 'submitted' || $currentTimesheet['status'] === 'approved') {
        $canSubmit = false;
        $_SESSION['info_message'] = 'You have already submitted this timesheet';
    }
}

// Check if this is a future timesheet
if ((new Timesheet())->isFutureTimesheet($month, $year) && $_SESSION['user_role'] !== 'admin') {
    $_SESSION['error_message'] = 'You cannot create or edit timesheets for future months';
    header("Location: " . BASE_URL . "/pages/dashboard.php");
    exit;
}

// Check if editing is allowed
$canEdit = $timesheet->canEditTimesheet(
    $currentTimesheet['timesheet_id'] ?? null, // Now this variable exists
    $user_id,
    $_SESSION['user_role'] === 'admin'
);

if (!$canEdit && $currentTimesheet && $currentTimesheet['status'] !== 'draft') {
    $_SESSION['info_message'] = 'This timesheet can no longer be edited';
    header("Location: " . BASE_URL . "/pages/dashboard.php");
    exit;
}

// Get user's projects
$projects = $timesheet->getUserProjects($user_id);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $timesheet->saveTimesheet($_POST, $user_id, $month, $year);
    if ($result) {
        $_SESSION['success_message'] = 'Timesheet saved successfully';
        if (!headers_sent()) {
            header("Location: " . BASE_URL . "/pages/timesheet.php?month=$month&year=$year");
            exit;
        } else {
            echo '<script>window.location.href="' . BASE_URL . '/pages/timesheet.php?month=' . $month . '&year=' . $year . '";</script>';
            exit;
        }
    }
}



// Get user's projects
//$projects = $timesheet->getUserProjects($user_id);

// Handle form submission
/*
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $timesheet->saveTimesheet($_POST, $user_id, $month, $year);
    if ($result) {
        $_SESSION['success_message'] = 'Timesheet saved successfully';
        //header("Location: timesheet.php?month=$month&year=$year");
        // To this (using double quotes for variable interpolation):
        header("Location: " . BASE_URL . "/pages/timesheet.php?month=$month&year=$year");
        exit;
    }
}
*/

/*
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $timesheet->saveTimesheet($_POST, $user_id, $month, $year);
    if ($result) {
        $_SESSION['success_message'] = 'Timesheet saved successfully';
        // Make sure no output has been sent before this
        if (!headers_sent()) {
            header("Location: " . BASE_URL . "/pages/timesheet.php?month=$month&year=$year");
            exit;
        } else {
            echo '<script>window.location.href="' . BASE_URL . '/pages/timesheet.php?month=' . $month . '&year=' . $year . '";</script>';
            exit;
        }
    }
}

*/

// Get days in month
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

// Get existing entries if they exist
/*
$currentTimesheet = $timesheet->getTimesheet($user_id, $month, $year);
$currentEntries = [];

if ($currentTimesheet) {
    // Create a more accessible structure for entries
    foreach ($currentTimesheet['entries'] ?? [] as $entry) {
        $currentEntries[$entry['project_id']] = $entry;
    }
}
*/

// After getting current timesheet data
$currentTimesheet = $timesheet->getTimesheet($user_id, $month, $year);
$currentEntries = [];
$canSubmit = true; // Flag to control submission

if ($currentTimesheet) {
    // Create a more accessible structure for entries
    foreach ($currentTimesheet['entries'] ?? [] as $entry) {
        $currentEntries[$entry['project_id']] = $entry;
    }

    // Check if already submitted
    if ($currentTimesheet['status'] === 'submitted' || $currentTimesheet['status'] === 'approved') {
        $canSubmit = false;
        $_SESSION['info_message'] = 'You have already submitted this timesheet';
    }
}
?>

<div class="container-fluid">
    <h2 class="my-4">Timesheet</h2>

    <?php include __DIR__ . '/../includes/language-switcher.php'; ?>
    <?php include __DIR__ . '/../includes/calendar-switcher.php'; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <h5 class="mb-0">Employee/Consultant Work Record (Hours per day)</h5>
        </div>
        <div class="card-body">
            <?php if (isset($currentTimesheet['status'])): ?>
                <div class="alert alert-<?=
                                        $currentTimesheet['status'] === 'approved' ? 'success' : ($currentTimesheet['status'] === 'submitted' ? 'info' : 'warning')
                                        ?> mb-4">
                    <strong>Status:</strong> <?= ucfirst($currentTimesheet['status']) ?>
                    <?php if ($currentTimesheet['submitted_at']): ?>
                        <br><small>Submitted on: <?= date('M j, Y H:i', strtotime($currentTimesheet['submitted_at'])) ?></small>
                    <?php endif; ?>
                    <?php if ($currentTimesheet['approved_at']): ?>
                        <br><small>Approved on: <?= date('M j, Y H:i', strtotime($currentTimesheet['approved_at'])) ?></small>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (empty($projects)): ?>

                <div class="alert alert-warning">
                    No projects assigned to you. Please contact your administrator.
                </div>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="month" value="<?= $month ?>">
                    <input type="hidden" name="year" value="<?= $year ?>">

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Month:</label>
                            <select class="form-select" id="month-selector">
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?= $m ?>" <?= $m == $month ? 'selected' : '' ?>>
                                        <?= $translation->getMonthName($m) ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Year:</label>
                            <select class="form-select" id="year-selector">
                                <?php for ($y = $year - 5; $y <= $year + 5; $y++): ?>
                                    <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead class="table-dark">
                                <tr>
                                    <th>Project Name/Account</th>
                                    <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                                        <th class="text-center">
                                            <?= $day ?><br>
                                            <small><?= date('D', strtotime("$year-$month-$day")) ?></small>
                                        </th>
                                    <?php endfor; ?>
                                    <th>Total Hours</th>
                                    <th>Hours Allocated</th>
                                    <th>% of Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($projects as $project): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($project['project_name']) ?></td>
                                        <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                                            <td>
                                                <input type="number" class="form-control hours-input"
                                                    name="project[<?= $project['project_id'] ?>][day_<?= $day ?>]"
                                                    value="<?= $currentEntries[$project['project_id']]["day_$day"] ?? 0 ?>"
                                                    min="0" max="24" step="0.5"
                                                    <?= !$canEdit ? 'readonly' : '' ?>>
                                            </td>
                                        <?php endfor; ?>
                                        <td class="total-hours"><?= $currentEntries[$project['project_id']]['total_hours'] ?? 0 ?></td>
                                        <td><?= $project['hours_allocated'] ?></td>
                                        <td class="percentage">
                                            <?php
                                            $allocated = $project['hours_allocated'];
                                            $total = $currentEntries[$project['project_id']]['total_hours'] ?? 0;
                                            $percentage = $allocated > 0 ? round(($total / $allocated) * 100, 1) : 0;
                                            echo $percentage . '%';
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="text-end mt-3">
                        <button type="submit" name="action" value="save" class="btn btn-primary">Save Draft</button>
                        <?php if ($canSubmit): ?>
                            <button type="submit" name="action" value="submit" class="btn btn-success">Submit</button>
                        <?php else: ?>
                            <button type="button" class="btn btn-secondary" disabled>Already Submitted</button>
                        <?php endif; ?>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/timesheet.js"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>