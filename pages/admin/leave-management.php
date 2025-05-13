<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/LeaveManager.php';

// Authorization check - only admin/manager can access
if (!hasRole('admin', 'manager')) {
    $_SESSION['error_message'] = 'You do not have permission to access this page';
    header('Location: ' . BASE_URL . '/pages/dashboard.php');
    exit;
}

// Initialize database and classes
try {
    $db = new Database();
    $pdo = $db->getConnection();
    $user = new User($pdo);
    $leaveManager = new LeaveManager($pdo);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("System error. Please try again later.");
}

$error = '';
$success = '';

// Get additional data for forms
$users_list = [];
$leave_types = [];
try {
    $users_list = $user->getAllUsers();
    $leave_types = $leaveManager->getLeaveTypes();
} catch (PDOException $e) {
    $error = "Failed to load form data: " . $e->getMessage();
    error_log($error);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        if (isset($_POST['update_balance'])) {
            // Handle balance updates
            foreach ($_POST['balances'] as $user_id => $balance) {
                $balance = (float) $balance;
                if ($balance < 0) {
                    throw new Exception("Leave balance cannot be negative");
                }
                $stmt = $pdo->prepare("UPDATE users SET leave_balance = ? WHERE user_id = ?");
                $stmt->execute([$balance, $user_id]);
            }
            $success = 'Leave balances updated successfully';
        } elseif (isset($_POST['update_used_leave'])) {
            // Handle used leave updates
            $user_id = (int) $_POST['update_used_leave'];
            $used_leave = (float) ($_POST['used_leaves'][$user_id] ?? 0);

            if ($used_leave < 0) {
                throw new Exception("Used leave cannot be negative");
            }

            // Update the leave_requests table for approved leaves in current year
            $current_year = date('Y');
            $stmt = $pdo->prepare("
                UPDATE leave_requests 
                SET days_requested = ?
                WHERE user_id = ? 
                AND status = 'approved' 
                AND YEAR(start_date) = ?
            ");
            $stmt->execute([$used_leave, $user_id, $current_year]);

            $success = 'Used leave days updated successfully';
        } elseif (isset($_POST['update_all_used_leaves'])) {
            // Handle bulk update of used leaves
            foreach ($_POST['used_leaves'] as $user_id => $used_leave) {
                $used_leave = (float) $used_leave;
                if ($used_leave < 0) {
                    throw new Exception("Used leave cannot be negative for user $user_id");
                }

                $current_year = date('Y');
                $stmt = $pdo->prepare("
                    UPDATE leave_requests 
                    SET days_requested = ?
                    WHERE user_id = ? 
                    AND status = 'approved' 
                    AND YEAR(start_date) = ?
                ");
                $stmt->execute([$used_leave, $user_id, $current_year]);
            }
            $success = 'All used leave days updated successfully';
        } elseif (isset($_POST['recalculate_all'])) {
            // Recalculate all leave balances
            $users = $user->getAllUsers();
            foreach ($users as $u) {
                $leaveManager->calculateAnnualLeaveWithoutTransaction($u['user_id']);
            }
            $success = 'All leave balances recalculated successfully';
        } elseif (isset($_POST['update_join_date'])) {
            // Handle join date updates
            $user_id = (int) $_POST['update_join_date'];
            $new_join_date = $_POST['join_dates'][$user_id] ?? '';

            if (empty($new_join_date)) {
                throw new Exception("Join date cannot be empty");
            }

            try {
                $date = new DateTime($new_join_date);
                $formatted_date = $date->format('Y-m-d');

                $today = new DateTime();
                if ($date > $today) {
                    throw new Exception("Join date cannot be in the future");
                }
            } catch (Exception $e) {
                throw new Exception("Invalid date format: " . $e->getMessage());
            }

            // Update join date
            $stmt = $pdo->prepare("UPDATE users SET join_date = ? WHERE user_id = ?");
            if (!$stmt->execute([$formatted_date, $user_id])) {
                throw new Exception("Failed to update join date");
            }

            // Recalculate leave balance
            $leaveManager->calculateAnnualLeaveWithoutTransaction($user_id);

            $success = 'Join date updated and leave balance recalculated';
        } elseif (isset($_POST['admin_request_leave'])) {
                // Handle admin creating leave request for user
                $selected_user_id = (int) $_POST['user_id'];
                $leave_type_id = (int) $_POST['leave_type_id'];
                $start_date = $_POST['start_date'];
                $end_date = $_POST['end_date'];
                $reason = $_POST['reason'] ?? '';

                // Validate required fields
                if (empty($selected_user_id) || empty($leave_type_id)) {
                    throw new Exception("Please select both employee and leave type");
                }

                // Validate dates
                if (empty($start_date) || empty($end_date)) {
                    throw new Exception("Start and end dates are required");
                }

                $start = new DateTime($start_date);
                $end = new DateTime($end_date);

                if ($start > $end) {
                    throw new Exception("Start date cannot be after end date");
                }

                // Calculate working days
                $interval = $start->diff($end);
                $days_requested = $interval->days + 1; // Include both dates
                $weekendDays = 0;

                for ($i = 0; $i <= $interval->days; $i++) {
                    $current = (clone $start)->add(new DateInterval("P{$i}D"));
                    if ($current->format('N') >= 6) {
                        $weekendDays++;
                    }
                }

                $days_requested -= $weekendDays;

                if ($days_requested <= 0) {
                    throw new Exception("Selected dates contain no working days");
                }

                // Verify leave type exists
                $stmt = $pdo->prepare("SELECT is_paid FROM leave_types WHERE leave_type_id = ?");
                $stmt->execute([$leave_type_id]);
                $leave_type = $stmt->fetch();

                if (!$leave_type) {
                    throw new Exception("Invalid leave type selected");
                }

                // Create approved leave request
                $stmt = $pdo->prepare("
                INSERT INTO leave_requests 
                (user_id, leave_type_id, start_date, end_date, days_requested, 
                 reason, status, approved_by, approved_at)
                VALUES (?, ?, ?, ?, ?, ?, 'approved', ?, NOW())
            ");

                $success = $stmt->execute([
                    $selected_user_id,
                    $leave_type_id,
                    $start->format('Y-m-d'),
                    $end->format('Y-m-d'),
                    $days_requested,
                    $reason,
                    $_SESSION['user_id']
                ]);

                if (!$success) {
                    throw new Exception("Failed to create leave request");
                }

                // Update balance for paid leaves
                if ($leave_type['is_paid']) {
                    $stmt = $pdo->prepare("
                    UPDATE users 
                    SET leave_balance = GREATEST(leave_balance - ?, 0)
                    WHERE user_id = ?
                ");
                    $stmt->execute([$days_requested, $selected_user_id]);
                }

                $success = 'Historical leave added successfully!';
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
            error_log("Leave Management Error: " . $e->getMessage());
        }
    }


    // Get users data with leave information
    try {
    $users = $pdo->query("
        SELECT u.user_id, u.first_name, u.last_name, 
               COALESCE(u.join_date, '') AS join_date,
               u.leave_balance,
               (SELECT COALESCE(SUM(days_requested), 0) FROM leave_requests 
                WHERE user_id = u.user_id AND status = 'approved' 
                AND YEAR(start_date) = YEAR(CURDATE())) AS used_leave
        FROM users u
        ORDER BY u.join_date DESC
    ")->fetchAll();
} catch (PDOException $e) {
    $error = "Failed to fetch users: " . $e->getMessage();
    error_log($error);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Management</title>
    <link href="<?= BASE_URL ?>/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/fontawesome.min.css" rel="stylesheet">
    <style>
        .is-invalid {
            border-color: #dc3545 !important;
        }

        .loading-spinner {
            display: none;
        }

        .btn-loading .loading-spinner {
            display: inline-block;
        }

        .btn-loading .btn-text {
            display: none;
        }

        .used-leave-input {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
        }

        .historical-form .form-control {
            max-width: 300px;
        }
    </style>
</head>

<body>
    <div class="container-fluid mt-4">
        <h1 class="mb-4">Leave Management</h1>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Leave Balances</h5>
            </div>
            <div class="card-body">
                <form method="POST" id="leaveForm">
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
                                    <th>Adjust Used Leave</th>
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
                                                max="<?= date('Y-m-d') ?>"
                                                required>
                                        </td>
                                        <td><?= $annualEntitlement ?></td>
                                        <td>
                                            <input type="number"
                                                class="form-control used-leave-input"
                                                name="used_leaves[<?= $u['user_id'] ?>]"
                                                value="<?= number_format($u['used_leave'], 2) ?>"
                                                step="0.5"
                                                min="0">
                                        </td>
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
                                                name="update_used_leave"
                                                value="<?= $u['user_id'] ?>"
                                                class="btn btn-sm btn-info update-used-btn">
                                                <span class="loading-spinner spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                                <span class="btn-text"><i class="fas fa-sync-alt"></i> Update Used</span>
                                            </button>
                                        </td>
                                        <td>
                                            <button type="submit"
                                                name="update_join_date"
                                                value="<?= $u['user_id'] ?>"
                                                class="btn btn-sm btn-warning update-btn">
                                                <span class="loading-spinner spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                                <span class="btn-text"><i class="fas fa-pencil-alt"></i> Update</span>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">
                        <button type="submit" name="update_balance" class="btn btn-primary">
                            <span class="loading-spinner spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                            <span class="btn-text"><i class="fas fa-save"></i> Save All Balances</span>
                        </button>
                        <button type="submit" name="update_all_used_leaves" class="btn btn-info ms-2">
                            <span class="loading-spinner spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                            <span class="btn-text"><i class="fas fa-save"></i> Save All Used Leaves</span>
                        </button>
                        <button type="submit" name="recalculate_all" class="btn btn-secondary ms-2">
                            <span class="loading-spinner spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                            <span class="btn-text"><i class="fas fa-calculator"></i> Recalculate All</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Add Historical Leave</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="historical-form">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Employee</label>
                            <select class="form-select" name="user_id" required>
                                <option value="">Select Employee</option>
                                <?php foreach ($users_list as $u): ?>
                                    <option value="<?= $u['user_id'] ?>">
                                        <?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Leave Type</label>
                            <select class="form-select" name="leave_type_id" required>
                                <?php foreach ($leave_types as $type): ?>
                                    <option value="<?= $type['leave_type_id'] ?>">
                                        <?= htmlspecialchars($type['type_name']) ?>
                                        (<?= $type['is_paid'] ? 'Paid' : 'Unpaid' ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" name="start_date" required max="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" name="end_date" required max="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Reason (Optional)</label>
                            <textarea class="form-control" name="reason" rows="2"></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" name="admin_request_leave" class="btn btn-success">
                                <i class="fas fa-history"></i> Add Historical Leave
                            </button>
                        </div>
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
                            <li>+2 days per year of service (max 30 days total)</li>
                            <li>Leaves reset on employment anniversary</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>Carryover Rules</h6>
                        <ul>
                            <li>Max 5 days can be carried over</li>
                            <li>Carried days expire after 3 months</li>
                            <li>Unused leave above 5 days is forfeited</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="<?= BASE_URL ?>/assets/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('leaveForm');

            // Form submission handling
            form.addEventListener('submit', function(e) {
                const submitBtn = e.submitter;

                // Show loading state
                if (submitBtn) {
                    submitBtn.classList.add('btn-loading');
                }

                // Validate join dates when updating
                if (submitBtn && submitBtn.name === 'update_join_date') {
                    const userId = submitBtn.value;
                    const dateInput = form.querySelector(`input[name="join_dates[${userId}]"]`);

                    if (!dateInput.value) {
                        e.preventDefault();
                        dateInput.classList.add('is-invalid');
                        alert('Please enter a valid join date');
                        submitBtn.classList.remove('btn-loading');
                        return;
                    }

                    try {
                        const date = new Date(dateInput.value);
                        if (isNaN(date.getTime())) {
                            throw new Error("Invalid date");
                        }

                        // Check if date is in future
                        const today = new Date();
                        today.setHours(0, 0, 0, 0);
                        if (date > today) {
                            e.preventDefault();
                            dateInput.classList.add('is-invalid');
                            alert('Join date cannot be in the future');
                            submitBtn.classList.remove('btn-loading');
                            return;
                        }
                    } catch (err) {
                        e.preventDefault();
                        dateInput.classList.add('is-invalid');
                        alert('Invalid date format');
                        submitBtn.classList.remove('btn-loading');
                        return;
                    }
                }

                // Validate balances when saving all
                if (submitBtn && (submitBtn.name === 'update_balance' || submitBtn.name === 'update_all_used_leaves' || submitBtn.name === 'update_used_leave')) {
                    const balanceInputs = form.querySelectorAll('input[name^="balances["], input[name^="used_leaves["]');
                    let valid = true;

                    balanceInputs.forEach(input => {
                        const value = parseFloat(input.value);
                        if (isNaN(value) || value < 0) {
                            input.classList.add('is-invalid');
                            valid = false;
                        }
                    });

                    if (!valid) {
                        e.preventDefault();
                        alert('Please fix invalid leave values before saving');
                        submitBtn.classList.remove('btn-loading');
                        return;
                    }
                }
            });

            // Clear validation errors when editing
            form.querySelectorAll('input').forEach(input => {
                input.addEventListener('input', function() {
                    this.classList.remove('is-invalid');
                });
            });
        });
    </script>

    <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</body>

</html>