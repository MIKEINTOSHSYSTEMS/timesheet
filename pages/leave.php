<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../classes/LeaveManager.php';

$leaveManager = new LeaveManager();
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['request_leave'])) {
            $leaveManager->requestLeave($user_id, [
                'leave_type_id' => $_POST['leave_type_id'],
                'start_date' => $_POST['start_date'],
                'end_date' => $_POST['end_date'],
                'reason' => $_POST['reason']
            ]);
            $success = 'Leave request submitted successfully';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$leave_types = $leaveManager->getLeaveTypes();
$balance = $leaveManager->getLeaveBalance($user_id);
$requests = $leaveManager->getUserLeaveRequests($user_id);
?>

<div class="container mt-4">
    <h1 class="mb-4">Leave Management</h1>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Request Leave</h5>
        </div>
        <div class="card-body">
            <form method="post">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Leave Type</label>
                        <select class="form-select" name="leave_type_id" required>
                            <?php foreach ($leave_types as $type): ?>
                                <option value="<?= $type['leave_type_id'] ?>">
                                    <?= htmlspecialchars($type['type_name']) ?>
                                    <?= $type['is_paid'] ? '(Paid)' : '(Unpaid)' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Start Date</label>
                        <input type="date" class="form-control" name="start_date" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">End Date</label>
                        <input type="date" class="form-control" name="end_date" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Reason</label>
                        <textarea class="form-control" name="reason" rows="3" required></textarea>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" name="request_leave" class="btn btn-primary">
                        Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">My Leave Requests</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Days</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $request): ?>
                            <tr>
                                <td><?= htmlspecialchars($request['type_name']) ?></td>
                                <td><?= date('M d, Y', strtotime($request['start_date'])) ?></td>
                                <td><?= date('M d, Y', strtotime($request['end_date'])) ?></td>
                                <td><?= $request['days_requested'] ?></td>
                                <td>
                                    <span class="badge bg-<?=
                                                            $request['status'] === 'approved' ? 'success' : ($request['status'] === 'rejected' ? 'danger' : 'warning') ?>">
                                        <?= ucfirst($request['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($request['status'] === 'pending'): ?>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="request_id"
                                                value="<?= $request['leave_request_id'] ?>">
                                            <button type="submit" name="cancel_request"
                                                class="btn btn-sm btn-warning">
                                                Cancel
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<br>
<p>
    <a href="<?= BASE_URL ?>/pages/dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
</p>
</br>

<?php require_once __DIR__ . '../../includes/footer.php'; ?>