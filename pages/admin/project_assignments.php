<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/header.php';

// Only admin can access this page
if (!hasRole('admin')) {
    header('Location: ' . BASE_URL . '/pages/dashboard.php');
    exit;
}

$project_id = $_GET['project_id'] ?? null;
if (!$project_id || !is_numeric($project_id)) {
    header('Location: ' . BASE_URL . '/pages/admin/projects.php');
    exit;
}

$db = new Database();
$pdo = $db->getConnection();
$error = '';
$success = '';

// Get project details
$stmt = $pdo->prepare("SELECT * FROM projects WHERE project_id = ?");
$stmt->execute([$project_id]);
$project = $stmt->fetch();

if (!$project) {
    header('Location: ' . BASE_URL . '/pages/admin/projects.php');
    exit;
}

// Handle assignment actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        if (isset($_POST['bulk_assign_project'])) {
            // Validate input
            if (empty($_POST['user_ids']) || empty($_POST['hours_allocated']) || 
                empty($_POST['start_date']) || empty($_POST['end_date'])) {
                throw new Exception("All fields are required");
            }
            
            // Assign project to multiple users
            foreach ($_POST['user_ids'] as $user_id) {
                $stmt = $pdo->prepare("
                    INSERT INTO project_allocations 
                    (user_id, project_id, hours_allocated, start_date, end_date, created_by)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $user_id,
                    $project_id,
                    $_POST['hours_allocated'],
                    $_POST['start_date'],
                    $_POST['end_date'],
                    $_SESSION['user_id']
                ]);
            }
            $success = 'Project assigned to selected users successfully';
        } elseif (isset($_POST['assign_project'])) {
            // Validate input
            if (
                empty($_POST['user_id']) || empty($_POST['hours_allocated']) ||
                empty($_POST['start_date']) || empty($_POST['end_date'])
            ) {
                throw new Exception("All fields are required");
            }

            // Assign project to user
            $stmt = $pdo->prepare("
                INSERT INTO project_allocations 
                (user_id, project_id, hours_allocated, start_date, end_date, created_by)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_POST['user_id'],
                $project_id,
                $_POST['hours_allocated'],
                $_POST['start_date'],
                $_POST['end_date'],
                $_SESSION['user_id']
            ]);
            $success = 'Project assigned successfully';
        } elseif (isset($_POST['update_assignment'])) {
            // Update assignment
            $stmt = $pdo->prepare("
                UPDATE project_allocations 
                SET hours_allocated = ?, start_date = ?, end_date = ?, is_active = ?
                WHERE allocation_id = ?
            ");
            $stmt->execute([
                $_POST['hours_allocated'],
                $_POST['start_date'],
                $_POST['end_date'],
                isset($_POST['is_active']) ? 1 : 0,
                $_POST['allocation_id']
            ]);
            $success = 'Assignment updated successfully';
        } elseif (isset($_POST['delete_assignment'])) {
            // Toggle active status
            $stmt = $pdo->prepare("
                UPDATE project_allocations 
                SET is_active = NOT is_active 
                WHERE allocation_id = ?
            ");
            $stmt->execute([$_POST['allocation_id']]);
            $success = 'Assignment status updated successfully';
        } elseif (isset($_POST['bulk_delete_assignments'])) {
            // Bulk delete assignments
            if (empty($_POST['selected_assignments'])) {
                throw new Exception("No assignments selected for deletion");
            }

            $selected_assignments = $_POST['selected_assignments'];
            $placeholders = implode(',', array_fill(0, count($selected_assignments), '?'));
            $stmt = $pdo->prepare("
                DELETE FROM project_allocations 
                WHERE allocation_id IN ($placeholders)
            ");
            $stmt->execute($selected_assignments);
            $success = 'Selected assignments deleted successfully';
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// Get all active users
$users = $pdo->query("
    SELECT user_id, first_name, last_name 
    FROM users 
    WHERE is_active = 1
    ORDER BY last_name, first_name
")->fetchAll();

// Get current assignments with user names
$assignments = $pdo->prepare("
    SELECT pa.*, u.first_name, u.last_name 
    FROM project_allocations pa
    JOIN users u ON pa.user_id = u.user_id
    WHERE pa.project_id = ?
    ORDER BY u.last_name, u.first_name
");
$assignments->execute([$project_id]);
$assignments = $assignments->fetchAll();
?>

<!-- Rest of your HTML remains the same -->

<div class="container-fluid">
    <h1 class="mb-4">Project Assignments: <?= e($project['project_name']) ?></h1>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Assign Project to User</h5>
        </div>
        <!-- filepath: d:\Installed_Apps\wamp64\www\merqconsultancy\timesheet\pages\admin\project_assignments.php -->
        <div class="card-body">
            <form method="POST">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Users</label>
                        <select class="form-select" name="user_ids[]" multiple required>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user['user_id'] ?>">
                                    <?= e($user['first_name'] . ' ' . $user['last_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Hold Ctrl (Windows) or Command (Mac) to select multiple users.</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Hours Allocated</label>
                        <input type="number" class="form-control" name="hours_allocated" step="0.5" min="0" required>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Start Date</label>
                        <input type="date" class="form-control" name="start_date" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">End Date</label>
                        <input type="date" class="form-control" name="end_date" required>
                    </div>
                </div>

                <button type="submit" name="bulk_assign_project" class="btn btn-primary">Assign Project</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Current Assignments</h5>
        </div>
        <div class="card-body">
            <form method="POST" id="bulk-delete-form">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="select-all-assignments"></th>
                                <th>User</th>
                                <th>Hours Allocated</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assignments as $assignment): ?>
                                <tr>
                                    <td><input type="checkbox" name="selected_assignments[]" value="<?= $assignment['allocation_id'] ?>"></td>
                                    <td><?= e($assignment['first_name'] . ' ' . $assignment['last_name']) ?></td>
                                    <td><?= e($assignment['hours_allocated']) ?></td>
                                    <td><?= date('M j, Y', strtotime($assignment['start_date'])) ?></td>
                                    <td><?= date('M j, Y', strtotime($assignment['end_date'])) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $assignment['is_active'] ? 'success' : 'danger' ?>">
                                            <?= $assignment['is_active'] ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary edit-assignment"
                                            data-allocation-id="<?= $assignment['allocation_id'] ?>"
                                            data-user-id="<?= $assignment['user_id'] ?>"
                                            data-hours-allocated="<?= $assignment['hours_allocated'] ?>"
                                            data-start-date="<?= $assignment['start_date'] ?>"
                                            data-end-date="<?= $assignment['end_date'] ?>"
                                            data-is-active="<?= $assignment['is_active'] ?>">
                                            Edit
                                        </button>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="allocation_id" value="<?= $assignment['allocation_id'] ?>">
                                            <button type="submit" name="delete_assignment" class="btn btn-sm btn-outline-danger">
                                                Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <button type="submit" name="bulk_delete_assignments" class="btn btn-danger">Delete Selected</button>
            </form>
        </div>
    </div>
</div>

<!-- Edit Assignment Modal -->
<div class="modal fade" id="editAssignmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Assignment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="allocation_id" id="edit_allocation_id">

                    <div class="mb-3">
                        <label class="form-label">User</label>
                        <select class="form-select" name="user_id" id="edit_user_id" required>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user['user_id'] ?>">
                                    <?= e($user['first_name'] . ' ' . $user['last_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Hours Allocated</label>
                        <input type="number" class="form-control" name="hours_allocated" id="edit_hours_allocated" step="0.5" min="0" required>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" name="start_date" id="edit_start_date" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" name="end_date" id="edit_end_date" required>
                        </div>
                    </div>

                    <div class="mb-3 form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="edit_is_active">
                        <label class="form-check-label" for="edit_is_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="update_assignment" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle edit assignment button clicks
        document.querySelectorAll('.edit-assignment').forEach(button => {
            button.addEventListener('click', function() {
                const modal = new bootstrap.Modal(document.getElementById('editAssignmentModal'));
                document.getElementById('edit_allocation_id').value = this.dataset.allocationId;
                document.getElementById('edit_user_id').value = this.dataset.userId;
                document.getElementById('edit_hours_allocated').value = this.dataset.hoursAllocated;
                document.getElementById('edit_start_date').value = this.dataset.startDate;
                document.getElementById('edit_end_date').value = this.dataset.endDate;
                document.getElementById('edit_is_active').checked = this.dataset.isActive === '1';
                modal.show();
            });
        });

        // Handle select all checkbox
        const selectAllCheckbox = document.getElementById('select-all-assignments');
        const checkboxes = document.querySelectorAll('input[name="selected_assignments[]"]');
        selectAllCheckbox.addEventListener('change', function() {
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>