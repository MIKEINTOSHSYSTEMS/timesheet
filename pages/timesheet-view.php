<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../includes/header.php';

$timesheet_id = isset($_GET['timesheet_id']) ? (int)$_GET['timesheet_id'] : 0;

$db = new Database();
$pdo = $db->getConnection();
$timesheet = new Timesheet();

// Get timesheet details
$stmt = $pdo->prepare("
    SELECT ts.*, u.first_name, u.last_name, u.role 
    FROM timesheets ts
    JOIN users u ON ts.user_id = u.user_id
    WHERE ts.timesheet_id = ?
");
$stmt->execute([$timesheet_id]);
$timesheetData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$timesheetData) {
    $_SESSION['error_message'] = 'Timesheet not found';
    header('Location: ' . BASE_URL . '/pages/dashboard.php');
    exit;
}

// Check if current user has permission to view
//$canView = ($_SESSION['user_id'] == $timesheetData['user_id']) || hasRole(['admin', 'manager']);
$canView = ($_SESSION['user_id'] == $timesheetData['user_id']) || hasRole('admin');

if (!$canView) {
    $_SESSION['error_message'] = 'You do not have permission to view this timesheet';
    header('Location: ' . BASE_URL . '/pages/dashboard.php');
    exit;
}

// Get entries
$stmt = $pdo->prepare("
    SELECT te.*, p.project_name, pa.hours_allocated
    FROM timesheet_entries te
    JOIN projects p ON te.project_id = p.project_id
    LEFT JOIN project_allocations pa ON pa.project_id = p.project_id 
        AND pa.user_id = ?
        AND (te.timesheet_id = ?)
    WHERE te.timesheet_id = ?
");
$stmt->execute([$timesheetData['user_id'], $timesheet_id, $timesheet_id]);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $timesheetData['month'], $timesheetData['year']);
?>

<div class="container-fluid">
    <h2 class="my-4">Timesheet Details</h2>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                Timesheet for <?= e($timesheetData['first_name'] . ' ' . $timesheetData['last_name']) ?>
                (<?= getMonthName($timesheetData['month']) ?> <?= $timesheetData['year'] ?>)
            </h5>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-4">
                    <strong>Status:</strong>
                    <span class="badge bg-<?=
                                            $timesheetData['status'] === 'approved' ? 'success' : ($timesheetData['status'] === 'submitted' ? 'info' : 'warning')
                                            ?>">
                        <?= ucfirst($timesheetData['status']) ?>
                    </span>
                </div>
                <div class="col-md-4">
                    <strong>Submitted:</strong>
                    <?= $timesheetData['submitted_at'] ? date('M j, Y H:i', strtotime($timesheetData['submitted_at'])) : 'Not submitted' ?>
                </div>
                <div class="col-md-4">
                    <strong>Approved:</strong>
                    <?= $timesheetData['approved_at'] ? date('M j, Y H:i', strtotime($timesheetData['approved_at'])) : 'Not approved' ?>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Project</th>
                            <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                                <th><?= $day ?></th>
                            <?php endfor; ?>
                            <th>Total</th>
                            <th>Allocated</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entries as $entry): ?>
                            <tr>
                                <td><?= e($entry['project_name']) ?></td>
                                <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                                    <td><?= $entry["day_$day"] ?? 0 ?></td>
                                <?php endfor; ?>
                                <td><?= $entry['total_hours'] ?></td>
                                <td><?= $entry['hours_allocated'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            
            <?php if (hasRole('admin', 'manager') && $timesheetData['status'] === 'submitted'): ?>
                <div class="mt-3 text-end">
                    <form method="POST" action="./admin/approve-timesheet.php" class="d-inline">
                        <input type="hidden" name="timesheet_id" value="<?= $timesheet_id ?>">
                        <input type="hidden" name="action" value="approve">
                        <button type="submit" class="btn btn-success">Approve</button>
                    </form>
                    <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">Reject</button>
                </div>

                <!-- Reject Modal -->
                <div class="modal fade" id="rejectModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Reject Timesheet</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST" action="./admin/approve-timesheet.php">
                                <div class="modal-body">
                                    <input type="hidden" name="timesheet_id" value="<?= $timesheet_id ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <div class="mb-3">
                                        <label class="form-label">Reason for rejection</label>
                                        <textarea class="form-control" name="rejection_reason" required></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-danger">Submit Rejection</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>