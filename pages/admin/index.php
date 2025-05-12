<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../classes/LeaveManager.php';

// Only admin can access this page
if (!hasRole('admin')) {
    header('Location: ' . BASE_URL . '/pages/dashboard.php');
    exit;
}

$db = new Database();
$pdo = $db->getConnection();
$user = new User();
$timesheet = new Timesheet();
$leaveManager = new LeaveManager();

// Get stats for dashboard
$stats = [
    'total_users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'active_users' => $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn(),
    'total_projects' => $pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn(),
    'active_projects' => $pdo->query("SELECT COUNT(*) FROM projects WHERE is_active = 1")->fetchColumn(),
    'pending_timesheets' => $pdo->query("SELECT COUNT(*) FROM timesheets WHERE status = 'submitted'")->fetchColumn(),
    'pending_leaves' => $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'pending'")->fetchColumn()
];

// Get pending leaves for the table
$pending_leaves = $leaveManager->getPendingLeaveRequests();

// Get pending timesheets for the table
$pending_timesheets = $pdo->query("
    SELECT 
        ts.timesheet_id,
        u.user_id, 
        CONCAT(u.first_name, ' ', u.last_name) as user_name,
        p.project_id,
        p.project_name,
        SUM(te.total_hours) as total_hours,
        ts.status,
        ts.submitted_at
    FROM timesheets ts
    JOIN timesheet_entries te ON ts.timesheet_id = te.timesheet_id
    JOIN users u ON ts.user_id = u.user_id
    LEFT JOIN projects p ON te.project_id = p.project_id
    WHERE ts.status = 'submitted'
    GROUP BY ts.timesheet_id, u.user_id, p.project_id, ts.status, ts.submitted_at
    ORDER BY ts.submitted_at DESC
    LIMIT 5
")->fetchAll();

// Get recent activities
$activities = $pdo->query("
    SELECT * FROM audit_log 
    ORDER BY created_at DESC 
    LIMIT 5
")->fetchAll();
?>

<div class="container-fluid">
    <h1 class="mb-4">Admin Dashboard</h1>

    <!-- Stats Cards -->
    <div class="row row-cols-1 row-cols-md-3 row-cols-xl-6 g-4 mb-4">
        <div class="col">
            <div class="card h-100 border-primary shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title text-muted mb-2">Total Users</h5>
                            <h2 class="mb-0"><?= $stats['total_users'] ?></h2>
                        </div>
                        <div class="icon-shape bg-primary text-white rounded-circle">
                            <i class="bi bi-people fs-4"></i>
                        </div>
                    </div>
                    <a href="<?= BASE_URL ?>/pages/admin/users.php" class="small text-primary text-decoration-none mt-2 d-block">
                        View Users <i class="bi bi-arrow-right-short"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card h-100 border-success shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title text-muted mb-2">Active Users</h5>
                            <h2 class="mb-0"><?= $stats['active_users'] ?></h2>
                        </div>
                        <div class="icon-shape bg-success text-white rounded-circle">
                            <i class="bi bi-person-check fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card h-100 border-info shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title text-muted mb-2">Total Projects</h5>
                            <h2 class="mb-0"><?= $stats['total_projects'] ?></h2>
                        </div>
                        <div class="icon-shape bg-info text-white rounded-circle">
                            <i class="bi bi-folder fs-4"></i>
                        </div>
                    </div>
                    <a href="<?= BASE_URL ?>/pages/admin/projects.php" class="small text-info text-decoration-none mt-2 d-block">
                        View Projects <i class="bi bi-arrow-right-short"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card h-100 border-warning shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title text-muted mb-2">Active Projects</h5>
                            <h2 class="mb-0"><?= $stats['active_projects'] ?></h2>
                        </div>
                        <div class="icon-shape bg-warning text-white rounded-circle">
                            <i class="bi bi-folder2-open fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card h-100 border-danger shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title text-muted mb-2">Pending Leaves</h5>
                            <h2 class="mb-0"><?= $stats['pending_leaves'] ?></h2>
                        </div>
                        <div class="icon-shape bg-danger text-white rounded-circle">
                            <i class="bi bi-calendar-x fs-4"></i>
                        </div>
                    </div>
                    <a href="<?= BASE_URL ?>/pages/admin/leave-approval.php" class="small text-danger text-decoration-none mt-2 d-block">
                        Review Leaves <i class="bi bi-arrow-right-short"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card h-100 border-secondary shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title text-muted mb-2">Pending Timesheets</h5>
                            <h2 class="mb-0"><?= $stats['pending_timesheets'] ?></h2>
                        </div>
                        <div class="icon-shape bg-secondary text-white rounded-circle">
                            <i class="bi bi-clock-history fs-4"></i>
                        </div>
                    </div>
                    <a href="<?= BASE_URL ?>/pages/admin/reports.php?status=submitted" class="small text-secondary text-decoration-none mt-2 d-block">
                        Review Timesheets <i class="bi bi-arrow-right-short"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="row g-4">
        <div class="col-xl-8">
            <!-- Pending Timesheets Table -->
            <div class="card shadow-sm mb-4">
                <div class="card-header d-flex justify-content-between align-items-center bg-secondary text-white">
                    <h5 class="mb-0">Pending Timesheet Approvals</h5>
                    <a href="<?= BASE_URL ?>/pages/admin/reports.php?status=submitted" class="btn btn-sm btn-light">
                        View All <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>User</th>
                                    <th>Project</th>
                                    <th>Hours</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($pending_timesheets)): ?>
                                    <?php foreach ($pending_timesheets as $timesheet): ?>
                                        <tr>
                                            <td><?= e($timesheet['user_name']) ?></td>
                                            <td><?= e($timesheet['project_name'] ?? 'N/A') ?></td>
                                            <td><?= number_format($timesheet['total_hours'], 2) ?></td>
                                            <td>
                                                <span class="badge bg-<?=
                                                                        $timesheet['status'] === 'approved' ? 'success' : ($timesheet['status'] === 'submitted' ? 'info' : 'warning')
                                                                        ?>">
                                                    <?= ucfirst($timesheet['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="<?= BASE_URL ?>/pages/timesheet-view.php?timesheet_id=<?= $timesheet['timesheet_id'] ?>"
                                                    class="btn btn-sm btn-outline-secondary">
                                                    Review
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4">No pending timesheet approvals</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Pending Leaves Table -->
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center bg-primary text-white">
                    <h5 class="mb-0">Pending Leave Requests</h5>
                    <a href="<?= BASE_URL ?>/pages/admin/leave-approval.php" class="btn btn-sm btn-light">
                        View All <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Employee</th>
                                    <th>Type</th>
                                    <th>Dates</th>
                                    <th>Days</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($pending_leaves)): ?>
                                    <?php foreach ($pending_leaves as $leave): ?>
                                        <tr>
                                            <td><?= e($leave['first_name'] . ' ' . $leave['last_name']) ?></td>
                                            <td><?= e($leave['type_name']) ?></td>
                                            <td>
                                                <?= date('M d', strtotime($leave['start_date'])) ?> -
                                                <?= date('M d', strtotime($leave['end_date'])) ?>
                                            </td>
                                            <td><?= $leave['days_requested'] ?></td>
                                            <td>
                                                <a href="<?= BASE_URL ?>/pages/admin/leave-approval.php?id=<?= $leave['leave_request_id'] ?>"
                                                    class="btn btn-sm btn-outline-primary">
                                                    Review
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4">No pending leave requests</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity & Quick Actions -->
        <div class="col-xl-4">
            <div class="row g-4">
                <!-- Recent Activity -->
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Recent Activities</h5>
                        </div>
                        <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                            <?php if ($activities): ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($activities as $activity): ?>
                                        <div class="list-group-item border-0 px-0 py-2">
                                            <div class="d-flex w-100 justify-content-between">
                                                <small class="text-muted"><?= date('M j, H:i', strtotime($activity['created_at'])) ?></small>
                                            </div>
                                            <div class="d-flex align-items-start">
                                                <div class="me-2">
                                                    <i class="bi bi-activity text-primary"></i>
                                                </div>
                                                <div>
                                                    <h6 class="mb-1"><?= e($activity['action']) ?></h6>
                                                    <small class="text-muted"><?= e($activity['table_name']) ?> #<?= $activity['record_id'] ?></small>
                                                    <div class="text-muted small mt-1">
                                                        IP: <?= e($activity['ip_address']) ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-3 text-muted">
                                    No recent activities found
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="<?= BASE_URL ?>/pages/admin/users.php?action=create"
                                    class="btn btn-outline-primary text-start">
                                    <i class="bi bi-person-plus me-2"></i> Add New User
                                </a>
                                <a href="<?= BASE_URL ?>/pages/admin/projects.php?action=create"
                                    class="btn btn-outline-primary text-start">
                                    <i class="bi bi-folder-plus me-2"></i> Create Project
                                </a>
                                <a href="<?= BASE_URL ?>/pages/admin/mass-email.php"
                                    class="btn btn-outline-primary text-start">
                                    <i class="bi bi-envelope-plus me-2"></i> Send Mass Email
                                </a>
                                <a href="<?= BASE_URL ?>/pages/admin/reports.php"
                                    class="btn btn-outline-primary text-start">
                                    <i class="bi bi-graph-up me-2"></i> View Reports
                                </a>
                                <a href="<?= BASE_URL ?>/pages/admin/settings.php"
                                    class="btn btn-outline-primary text-start">
                                    <i class="bi bi-gear me-2"></i> System Settings
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .icon-shape {
        width: 45px;
        height: 45px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .card-hover {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .card-hover:hover {
        transform: translateY(-3px);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    }

    .table-hover tbody tr {
        transition: background-color 0.2s ease;
    }

    .table-striped tbody tr:nth-of-type(odd) {
        background-color: rgba(0, 0, 0, 0.02);
    }
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>