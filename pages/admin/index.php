<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/header.php';

// Only admin can access this page
if (!hasRole('admin')) {
    header('Location: ' . BASE_URL . '/pages/dashboard.php');
    exit;
}

$db = new Database();
$pdo = $db->getConnection();
$user = new User();
$timesheet = new Timesheet();

// Get stats for dashboard
$stats = [
    'total_users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'active_users' => $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn(),
    'total_projects' => $pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn(),
    'active_projects' => $pdo->query("SELECT COUNT(*) FROM projects WHERE is_active = 1")->fetchColumn(),
    'pending_timesheets' => $pdo->query("SELECT COUNT(*) FROM timesheets WHERE status = 'submitted'")->fetchColumn()
];

// Get recent activities
$activities = $pdo->query("
    SELECT * FROM audit_log 
    ORDER BY created_at DESC 
    LIMIT 5
")->fetchAll();
?>

<div class="container-fluid">
    <h1 class="mb-4">Admin Dashboard</h1>
    
    <div class="row">
        <!-- Stats Cards -->
        <div class="col-md-3 mb-4">
            <div class="card text-white bg-primary">
                <div class="card-body">
                    <h5 class="card-title">Total Users</h5>
                    <h2 class="card-text"><?= $stats['total_users'] ?></h2>
                    <a href="<?= BASE_URL ?>/pages/admin/users.php" class="text-white">View Users</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-4">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <h5 class="card-title">Active Users</h5>
                    <h2 class="card-text"><?= $stats['active_users'] ?></h2>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-4">
            <div class="card text-white bg-info">
                <div class="card-body">
                    <h5 class="card-title">Total Projects</h5>
                    <h2 class="card-text"><?= $stats['total_projects'] ?></h2>
                    <a href="<?= BASE_URL ?>/pages/admin/projects.php" class="text-white">View Projects</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-4">
            <div class="card text-white bg-warning">
                <div class="card-body">
                    <h5 class="card-title">Pending Timesheets</h5>
                    <h2 class="card-text"><?= $stats['pending_timesheets'] ?></h2>
                    <a href="<?= BASE_URL ?>/pages/admin/reports.php?status=submitted" class="text-white">Review</a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Recent Activities</h5>
                </div>
                <div class="card-body">
                    <?php if ($activities): ?>
                        <div class="list-group">
                            <?php foreach ($activities as $activity): ?>
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?= e($activity['action']) ?></h6>
                                        <small><?= date('M j, H:i', strtotime($activity['created_at'])) ?></small>
                                    </div>
                                    <p class="mb-1"><?= e($activity['table_name']) ?> #<?= $activity['record_id'] ?></p>
                                    <small>IP: <?= e($activity['ip_address']) ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p>No recent activities found</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <a href="<?= BASE_URL ?>/pages/admin/users.php?action=create" class="btn btn-primary w-100">
                                <i class="bi bi-person-plus"></i> Add User
                            </a>
                        </div>
                        <div class="col-md-6 mb-3">
                            <a href="<?= BASE_URL ?>/pages/admin/projects.php?action=create" class="btn btn-primary w-100">
                                <i class="bi bi-folder-plus"></i> Add Project
                            </a>
                        </div>
                        <div class="col-md-6 mb-3">
                            <a href="<?= BASE_URL ?>/pages/admin/settings.php" class="btn btn-secondary w-100">
                                <i class="bi bi-gear"></i> System Settings
                            </a>
                        </div>
                        <div class="col-md-6 mb-3">
                            <a href="<?= BASE_URL ?>/pages/admin/reports.php" class="btn btn-info w-100">
                                <i class="bi bi-graph-up"></i> View Reports
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>