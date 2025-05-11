<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth-check.php';

// Handle all redirects before including header.php
$redirect = null;

$user_id = $_SESSION['user_id'];
$is_admin = $_SESSION['user_role'] === 'admin';
$timesheet = new Timesheet();
$translation = new Translation();

// Get current month/year or from request
$month = isset($_GET['month']) ? (int)$_GET['month'] : DateConverter::getCurrentMonth();
$year = isset($_GET['year']) ? (int)$_GET['year'] : DateConverter::getCurrentYear();

// Get existing timesheet data
$currentTimesheet = $timesheet->getTimesheet($user_id, $month, $year);

// After getting currentTimesheet
$currentEntries = [];
$canSubmit = true;

if ($currentTimesheet) {
    foreach ($currentTimesheet['entries'] ?? [] as $entry) {
        $currentEntries[$entry['project_id']] = $entry;
    }

    if ($currentTimesheet['status'] === 'submitted' || $currentTimesheet['status'] === 'approved') {
        $canSubmit = false;
        $_SESSION['info_message'] = 'You have already submitted this timesheet';
    }
}

// Check if this is a future timesheet (for non-admins)
if (!$is_admin && $timesheet->isFutureTimesheet($month, $year, CalendarHelper::isEthiopian() ? 'ethiopian' : 'gregorian')) {
$_SESSION['error_message'] = 'You cannot create or edit timesheets for future months and years.';
    $redirect = BASE_URL . "/pages/dashboard.php";
}

// Check if editing is allowed
if (!$redirect) {
    $canEdit = $timesheet->canEditTimesheet(
        $currentTimesheet['timesheet_id'] ?? null,
        $user_id,
        $is_admin
    );

    if (!$canEdit && $currentTimesheet) {
        if ($currentTimesheet['status'] !== 'draft') {
            $_SESSION['info_message'] = 'Submitted timesheets cannot be edited';
        } elseif ($timesheet->isFutureTimesheet($month, $year, $currentTimesheet['calendar_type'] ?? 'gregorian')) {
            $_SESSION['error_message'] = 'Future timesheets cannot be edited';
        }
        $redirect = BASE_URL . "/pages/dashboard.php";
    }
}

// Handle form submission before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $timesheet->saveTimesheet($_POST, $user_id, $month, $year, $is_admin);
    if ($result) {
        $_SESSION['success_message'] = 'Timesheet saved successfully';
        $redirect = BASE_URL . "/pages/timesheet.php?month=$month&year=$year";
    } else {
        $redirect = BASE_URL . "/pages/timesheet.php?month=$month&year=$year";
    }
}

// Perform any redirects before output starts
if ($redirect) {
    header("Location: $redirect");
    exit;
}

// Now we can safely include the header and output content
require_once __DIR__ . '/../includes/header.php';
displayFlashMessages();

// Get user's projects (only if we're not redirecting)
$projects = $timesheet->getUserProjects($user_id);

// Get days in month - using CalendarHelper for Ethiopian support
$daysInMonth = CalendarHelper::getDaysInMonth($month, $year);
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
                        <br><small>Submitted on: <?= DateConverter::formatDate($currentTimesheet['submitted_at'], 'M j, Y H:i') ?></small>
                    <?php endif; ?>
                    <?php if ($currentTimesheet['approved_at']): ?>
                        <br><small>Approved on: <?= DateConverter::formatDate($currentTimesheet['approved_at'], 'M j, Y H:i') ?></small>
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
                                <?php
                                $currentCalendar = CalendarHelper::isEthiopian() ? 'ethiopian' : 'gregorian';
                                $monthCount = $currentCalendar === 'ethiopian' ? 13 : 12;

                                for ($m = 1; $m <= $monthCount; $m++):
                                    $selected = ($currentTimesheet && $currentTimesheet['calendar_type'] === $currentCalendar)
                                        ? ($m == $currentTimesheet['month'] ? 'selected' : '')
                                        : ($m == $month ? 'selected' : '');
                                ?>
                                    <option value="<?= $m ?>" <?= $selected ?>>
                                        <?= CalendarHelper::getMonthName($m) ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Year:</label>
                            <select class="form-select" id="year-selector">
                                <?php
                                $currentYear = DateConverter::getCurrentYear();
                                $startYear = $currentYear - 5;
                                $endYear = $currentYear + 5;

                                for ($y = $startYear; $y <= $endYear; $y++):
                                    $selected = ($currentTimesheet && $currentTimesheet['calendar_type'] === $currentCalendar)
                                        ? ($y == $currentTimesheet['year'] ? 'selected' : '')
                                        : ($y == $year ? 'selected' : '');
                                ?>
                                    <option value="<?= $y ?>" <?= $selected ?>>
                                        <?= $y ?>
                                        <?= $currentCalendar === 'ethiopian' ? ' (E.C.)' : '' ?>
                                    </option>
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
                                            <small><?= CalendarHelper::getDayName($day, $month, $year) ?></small>
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
                        <?php if ($currentTimesheet && $currentTimesheet['status'] === 'submitted'): ?>
                            <button type="button" class="btn btn-secondary" disabled>Already Submitted</button>
                        <?php elseif ($currentTimesheet && $currentTimesheet['status'] === 'approved'): ?>
                            <button type="button" class="btn btn-success" disabled>Approved</button>
                        <?php elseif (!$canEdit): ?>
                            <?php if ($timesheet->isFutureTimesheet($month, $year, $currentTimesheet['calendar_type'] ?? 'gregorian')): ?>
                                <button type="button" class="btn btn-warning" disabled>Future Timesheet</button>
                            <?php else: ?>
                                <button type="button" class="btn btn-warning" disabled>Read Only</button>
                            <?php endif; ?>
                        <?php else: ?>
                            <button type="submit" name="action" value="save" class="btn btn-primary">
                                <?= $currentTimesheet ? 'Update Draft' : 'Create Draft' ?>
                            </button>
                            <button type="submit" name="action" value="submit" class="btn btn-success">Submit</button>
                        <?php endif; ?>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/timesheet.js"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>