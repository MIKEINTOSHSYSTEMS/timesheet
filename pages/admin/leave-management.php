<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../classes/LeaveManager.php';

if (!hasRole('admin', 'manager')) {
//if (!hasAnyRole(['admin', 'manager'])) {
    error_log("Access denied - user doesn't have required role");
    $_SESSION['error_message'] = 'You do not have permission to access this page';
    header('Location: ' . BASE_URL . '/pages/dashboard.php');
    exit;
}

$db = new Database();
$pdo = $db->getConnection();
$user = new User();
$leaveManager = new LeaveManager();

// Handle form submissions
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        if (isset($_POST['update_balance'])) {
            foreach ($_POST['balances'] as $user_id => $balance) {
                $stmt = $pdo->prepare("UPDATE users SET leave_balance = ? WHERE user_id = ?");
                $stmt->execute([$balance, $user_id]);
            }
            $success = 'Leave balances updated successfully';
        } elseif (isset($_POST['recalculate_all'])) {
            $users = $user->getAllUsers();
            foreach ($users as $u) {
                $leaveManager->calculateAnnualLeave($u['user_id']);
            }
            $success = 'All leave balances recalculated successfully';
        } elseif (isset($_POST['update_join_date'])) {
            $user_id = $_POST['update_join_date'];
            $new_join_date = $_POST['join_dates'][$user_id] ?? '';

            if (empty($new_join_date) || !strtotime($new_join_date)) {
                throw new Exception("Invalid date format for user $user_id");
            }

            $stmt = $pdo->prepare("UPDATE users SET join_date = ? WHERE user_id = ?");
            $stmt->execute([$new_join_date, $user_id]);
            $leaveManager->calculateAnnualLeave($user_id);
            $success = 'Join date updated successfully';
        } elseif (isset($_POST['update_all_join_dates'])) {
            foreach ($_POST['join_dates'] as $user_id => $new_join_date) {
                if (empty($new_join_date) || !strtotime($new_join_date)) {
                    throw new Exception("Invalid date format for user $user_id");
                }

                $stmt = $pdo->prepare("UPDATE users SET join_date = ? WHERE user_id = ?");
                $stmt->execute([$new_join_date, $user_id]);
                $leaveManager->calculateAnnualLeave($user_id);
            }
            $success = 'All join dates updated successfully';
        }

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}

// Get all users with leave information
$users = $pdo->query("
    SELECT u.user_id, u.first_name, u.last_name, 
           COALESCE(u.join_date, '') AS join_date,
           u.leave_balance,
           (SELECT SUM(days_requested) FROM leave_requests 
            WHERE user_id = u.user_id AND status = 'approved' 
            AND YEAR(start_date) = YEAR(CURDATE())) AS used_leave
    FROM users u
    ORDER BY u.join_date DESC
")->fetchAll();
?>

<div class="container-fluid">
    <h1 class="mb-4">Leave Management</h1>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Leave Balances</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Join Date</th>
                                <th>Annual Entitlement</th>
                                <th>Used Leave</th>
                                <th>Remaining Balance</th>
                                <th>Adjust Balance</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u):
                                $joinDate = $u['join_date'] ?? '';
                                $isInvalidDate = empty($joinDate) || !strtotime($joinDate);
                                $annualEntitlement = $leaveManager->calculateAnnualEntitlement($joinDate);
                                $remaining = $u['leave_balance'] - ($u['used_leave'] ?? 0);
                            ?>
                                <tr>
                                    <td><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></td>
                                    <td>
                                        <input type="date"
                                            class="form-control <?= $isInvalidDate ? 'is-invalid' : '' ?>"
                                            name="join_dates[<?= $u['user_id'] ?>]"
                                            value="<?= htmlspecialchars($joinDate) ?>"
                                            max="<?= date('Y-m-d') ?>">
                                    </td>
                                    <td><?= $annualEntitlement ?></td>
                                    <td><?= $u['used_leave'] ?? 0 ?></td>
                                    <td class="<?= $remaining < 0 ? 'text-danger' : '' ?>">
                                        <?= number_format($remaining, 2) ?>
                                    </td>
                                    <td>
                                        <input type="number"
                                            class="form-control"
                                            name="balances[<?= $u['user_id'] ?>]"
                                            value="<?= number_format($u['leave_balance'], 2) ?>"
                                            step="0.5"
                                            min="0">
                                    </td>
                                    <td>
                                        <button type="submit"
                                            name="update_join_date"
                                            value="<?= $u['user_id'] ?>"
                                            class="btn btn-sm btn-warning">
                                            <i class="bi bi-pencil"></i> Update
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    <button type="submit" name="update_balance" class="btn btn-primary">
                        <i class="bi bi-save me-2"></i>Save All Balances
                    </button>
                    <button type="submit" name="recalculate_all" class="btn btn-secondary">
                        <i class="bi bi-calculator me-2"></i>Recalculate All
                    </button>
                    <button type="submit" name="update_all_join_dates" class="btn btn-info">
                        <i class="bi bi-calendar-check me-2"></i>Update All Join Dates
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Leave Calculation Rules</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6>Accrual Rules</h6>
                    <ul>
                        <li>Base annual leave: 20 days</li>
                        <li>+2 days per year of service (max 30 days)</li>
                        <li>Leaves reset on employment anniversary</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h6>Carryover Rules</h6>
                    <ul>
                        <li>Max 5 days can be carried over</li>
                        <li>Carried days expire after 3 months</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.querySelector('form').addEventListener('submit', function(e) {
        const dateInputs = document.querySelectorAll('input[type="date"]');
        let valid = true;

        dateInputs.forEach(input => {
            if (!input.value || !Date.parse(input.value)) {
                input.classList.add('is-invalid');
                valid = false;
            }
        });

        if (!valid) {
            e.preventDefault();
            alert('Please fix invalid join dates before submitting');
        }
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>