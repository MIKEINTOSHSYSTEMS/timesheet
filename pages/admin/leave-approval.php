<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/LeaveManager.php';

// Update the role check at the top of leave-approval.php
if (!hasRole('admin', 'manager')) {
    error_log("Access denied - user doesn't have required role");
    $_SESSION['error_message'] = 'You do not have permission to access this page';
    header('Location: ' . BASE_URL . '/pages/dashboard.php');
    ob_end_flush();
    exit;
}

// Initialize database connection and LeaveManager
try {
    $db = new Database();
    $pdo = $db->getConnection();
    $leaveManager = new LeaveManager($pdo);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("System error. Please try again later.");
}

$error = '';
$success = '';

// Get leave request details
$leave_request = [];
if (isset($_GET['id'])) {
    $leave_request = $leaveManager->getLeaveRequestDetails($_GET['id']);
}

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['approve'])) {
            $leaveManager->approveLeave($_POST['leave_id'], $_SESSION['user_id']);
            $success = 'Leave request approved successfully';
        } elseif (isset($_POST['reject'])) {
            $leaveManager->rejectLeave($_POST['leave_id'], $_SESSION['user_id'], $_POST['rejection_reason']);
            $success = 'Leave request rejected successfully';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get pending leaves for the table
$pending_leaves = $leaveManager->getPendingLeaveRequests();
?>

<div class="container-fluid">
    <h1 class="mb-4">Leave Approval</h1>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-6">
            <?php if (!empty($leave_request)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Leave Request Details</h5>
                    </div>
                    <div class="card-body">
                        <dl class="row">
                            <dt class="col-sm-4">Employee:</dt>
                            <dd class="col-sm-8"><?= e($leave_request['first_name'] . ' ' . $leave_request['last_name']) ?></dd>

                            <dt class="col-sm-4">Leave Type:</dt>
                            <dd class="col-sm-8"><?= e($leave_request['type_name']) ?></dd>

                            <dt class="col-sm-4">Dates:</dt>
                            <dd class="col-sm-8">
                                <?= date('M d, Y', strtotime($leave_request['start_date'])) ?> -
                                <?= date('M d, Y', strtotime($leave_request['end_date'])) ?>
                            </dd>

                            <dt class="col-sm-4">Days Requested:</dt>
                            <dd class="col-sm-8"><?= $leave_request['days_requested'] ?></dd>

                            <dt class="col-sm-4">Reason:</dt>
                            <dd class="col-sm-8"><?= nl2br(e($leave_request['reason'])) ?></dd>
                        </dl>

                        <form method="POST">
                            <input type="hidden" name="leave_id" value="<?= $leave_request['leave_request_id'] ?>">

                            <div class="mb-3">
                                <label class="form-label">Rejection Reason (if rejecting)</label>
                                <textarea class="form-control" name="rejection_reason" rows="3"></textarea>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" name="approve" class="btn btn-success">
                                    Approve Leave
                                </button>
                                <button type="submit" name="reject" class="btn btn-danger">
                                    Reject Leave
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Pending Leave Requests</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($pending_leaves)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Type</th>
                                        <th>Dates</th>
                                        <th>Days</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
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
                                                <a href="?id=<?= $leave['leave_request_id'] ?>"
                                                    class="btn btn-sm btn-primary">
                                                    Review
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p>No pending leave requests</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>