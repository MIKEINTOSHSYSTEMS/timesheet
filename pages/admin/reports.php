<?php
// reports.php
ob_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/header.php';

// Debugging
error_log("Accessing reports.php for user ID: " . ($_SESSION['user_id'] ?? 'none'));

//if (!hasRole(['admin', 'manager'])) {
//if (!hasRole('admin', 'manager')) {

//if (!hasRole(['admin', 'manager'])) {
if (!hasRole('admin', 'manager')) {
    error_log("Access denied - user doesn't have required role");
    $_SESSION['error_message'] = 'You do not have permission to access this page';
    header('Location: ' . BASE_URL . '/pages/dashboard.php');
    ob_end_flush();
    exit;
}

require_once __DIR__ . '/../../includes/header.php';

$db = new Database();
$pdo = $db->getConnection();
$timesheet = new Timesheet();

// Get current month/year if not specified
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : null;
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$status = isset($_GET['status']) ? $_GET['status'] : null;

// Build base query
/*
$query = "
    SELECT 
        ts.timesheet_id,
        u.user_id, 
        CONCAT(u.first_name, ' ', u.last_name) as user_name,
        p.project_id,
        p.project_name,
        SUM(te.total_hours) as total_hours,
        ts.status,
        ts.month,
        ts.year,
        pa.hours_allocated as allocated_hours
    FROM timesheets ts
    JOIN timesheet_entries te ON ts.timesheet_id = te.timesheet_id
    JOIN users u ON ts.user_id = u.user_id
    JOIN projects p ON te.project_id = p.project_id
    LEFT JOIN project_allocations pa ON pa.user_id = ts.user_id 
        AND pa.project_id = p.project_id
        AND (ts.month BETWEEN MONTH(pa.start_date) AND MONTH(pa.end_date))
        AND (ts.year BETWEEN YEAR(pa.start_date) AND YEAR(pa.end_date))
    WHERE ts.month = :month AND ts.year = :year
";
*/

$query = "
    SELECT 
        ts.timesheet_id,
        u.user_id, 
        CONCAT(u.first_name, ' ', u.last_name) as user_name,
        p.project_id,
        p.project_name,
        SUM(te.total_hours) as total_hours,
        ts.status,
        ts.month,
        ts.year,
        ts.calendar_type,
        pa.hours_allocated as allocated_hours
    FROM timesheets ts
    JOIN timesheet_entries te ON ts.timesheet_id = te.timesheet_id
    JOIN users u ON ts.user_id = u.user_id
    JOIN projects p ON te.project_id = p.project_id
    LEFT JOIN project_allocations pa ON pa.user_id = ts.user_id 
        AND pa.project_id = p.project_id
    WHERE 1=1
";

$params = [
    ':month' => $month,
    ':year' => $year
];

// Add filters based on current calendar view
if (CalendarHelper::isEthiopian()) {
    $query .= " AND ts.calendar_type = 'ethiopian'";
} else {
    $query .= " AND ts.calendar_type = 'gregorian'";
}

// Add month/year filters
if ($month) {
    $query .= " AND ts.month = :month";
    $params[':month'] = $month;
}
if ($year) {
    $query .= " AND ts.year = :year";
    $params[':year'] = $year;
}

// Add role to filters
$role = isset($_GET['role']) ? $_GET['role'] : null;

// Add to query building
if ($role) {
    $query .= " AND u.role = :role";
    $params[':role'] = $role;
}

// Add optional filters
if ($project_id) {
    $query .= " AND p.project_id = :project_id";
    $params[':project_id'] = $project_id;
}

if ($user_id) {
    $query .= " AND u.user_id = :user_id";
    $params[':user_id'] = $user_id;
}

if ($status) {
    $query .= " AND ts.status = :status";
    $params[':status'] = $status;
}

$query .= " GROUP BY ts.timesheet_id, u.user_id, p.project_id, ts.status, ts.month, ts.year, pa.hours_allocated";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Debugging
    error_log("Report query executed successfully. Found " . count($reportData) . " records.");
} catch (PDOException $e) {
    error_log("Report query error: " . $e->getMessage());
    $_SESSION['error_message'] = 'Failed to generate report. Please try again.';
    header('Location: ' . BASE_URL . '/pages/dashboard.php');
    ob_end_flush();
    exit;
}

// Get filter options
$projects = $pdo->query("SELECT project_id, project_name FROM projects WHERE is_active = 1 ORDER BY project_name")->fetchAll();
$users = $pdo->query("SELECT user_id, CONCAT(first_name, ' ', last_name) as name FROM users WHERE is_active = 1 ORDER BY last_name, first_name")->fetchAll();

ob_end_flush();
?>

<div class="container-fluid">
    <h1 class="mb-4">Timesheet Reports</h1>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Filters</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">Month</label>
                    <select class="form-select" name="month">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= $m == $month ? 'selected' : '' ?>>
                                <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Year</label>
                    <select class="form-select" name="year">
                        <?php for ($y = date('Y') - 5; $y <= date('Y') + 1; $y++): ?>
                            <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Project</label>
                    <select class="form-select" name="project_id">
                        <option value="">All Projects</option>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?= $project['project_id'] ?>" <?= $project_id == $project['project_id'] ? 'selected' : '' ?>>
                                <?= e($project['project_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">User</label>
                    <select class="form-select" name="user_id">
                        <option value="">All Users</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= $user['user_id'] ?>" <?= $user_id == $user['user_id'] ? 'selected' : '' ?>>
                                <?= e($user['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="">All Statuses</option>
                        <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="submitted" <?= $status === 'submitted' ? 'selected' : '' ?>>Submitted</option>
                        <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Role</label>
                    <select class="form-select" name="role">
                        <option value="">All Roles</option>
                        <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="manager" <?= $role === 'manager' ? 'selected' : '' ?>>Manager</option>
                        <option value="employee" <?= $role === 'employee' ? 'selected' : '' ?>>Employee</option>
                        <option value="consultant" <?= $role === 'consultant' ? 'selected' : '' ?>>Consultant</option>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Report Data</h5>
        </div>
        <div class="card-body">
            <?php if ($reportData): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Project</th>
                                <th>Hours</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportData as $row): ?>
                                <tr>
                                    <td><?= e($row['user_name']) ?></td>
                                    <td><?= e($row['project_name']) ?></td>
                                    <td><?= e($row['total_hours']) ?></td>
                                    <td>
                                        <span class="badge bg-<?=
                                                                $row['status'] === 'approved' ? 'success' : ($row['status'] === 'submitted' ? 'info' : 'warning')
                                                                ?>">
                                            <?= ucfirst($row['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="<?= BASE_URL ?>/pages/timesheet-view.php?timesheet_id=<?= $row['timesheet_id'] ?>"
                                            class="btn btn-sm btn-outline-primary">View Details</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    <button class="btn btn-success" onclick="window.print()">Print Report</button>
                    <a href="export-report.php?<?= http_build_query($_GET) ?>" class="btn btn-primary">Export to Excel</a>
                </div>
            <?php else: ?>
                <div class="alert alert-info">No data found for the selected filters</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>