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
$error = '';
$success = '';

// Handle project actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (empty($_POST['project_code']) || empty($_POST['project_name'])) {
            throw new Exception("Project code and name are required");
        }
        
        if (isset($_POST['create_project'])) {
            // Check if project code exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE project_code = ?");
            $stmt->execute([$_POST['project_code']]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Project code already exists");
            }
            
            // Create new project
            $stmt = $pdo->prepare("
                INSERT INTO projects (project_code, project_name, description, is_active)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                trim($_POST['project_code']),
                trim($_POST['project_name']),
                trim($_POST['description'] ?? ''),
                isset($_POST['is_active']) ? 1 : 0
            ]);
            $success = 'Project created successfully';
        } elseif (isset($_POST['update_project'])) {
            // Check if project code exists (excluding current project)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE project_code = ? AND project_id != ?");
            $stmt->execute([$_POST['project_code'], $_POST['project_id']]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Project code already exists");
            }
            
            // Update existing project
            $stmt = $pdo->prepare("
                UPDATE projects 
                SET project_code = ?, project_name = ?, description = ?, is_active = ?
                WHERE project_id = ?
            ");
            $stmt->execute([
                trim($_POST['project_code']),
                trim($_POST['project_name']),
                trim($_POST['description'] ?? ''),
                isset($_POST['is_active']) ? 1 : 0,
                $_POST['project_id']
            ]);
            $success = 'Project updated successfully';
        } elseif (isset($_POST['delete_project'])) {
            // Delete project (soft delete)
            $currentStatus = $pdo->query("SELECT is_active FROM projects WHERE project_id = " . (int)$_POST['project_id'])->fetchColumn();
            $newStatus = $currentStatus ? 0 : 1;
            $stmt = $pdo->prepare("UPDATE projects SET is_active = ? WHERE project_id = ?");
            $stmt->execute([$newStatus, $_POST['project_id']]);
            $success = $newStatus ? 'Project activated successfully' : 'Project deactivated successfully';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get all projects
$projects = $pdo->query("SELECT * FROM projects ORDER BY project_name")->fetchAll();
?>

<!-- Rest of the HTML remains the same -->

<div class="container-fluid">
    <h1 class="mb-4">Project Management</h1>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Create New Project</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Project Code</label>
                        <input type="text" class="form-control" name="project_code" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Project Name</label>
                        <input type="text" class="form-control" name="project_name" required>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" name="description" rows="3"></textarea>
                </div>
                
                <div class="mb-3 form-check">
                    <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
                    <label class="form-check-label" for="is_active">Active</label>
                </div>
                
                <button type="submit" name="create_project" class="btn btn-primary">Create Project</button>
            </form>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Project List</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($projects as $project): ?>
                            <tr>
                                <td><?= e($project['project_code']) ?></td>
                                <td><?= e($project['project_name']) ?></td>
                                <td><?= e($project['description']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $project['is_active'] ? 'success' : 'danger' ?>">
                                        <?= $project['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary edit-project" 
                                            data-project-id="<?= $project['project_id'] ?>"
                                            data-project-code="<?= e($project['project_code']) ?>"
                                            data-project-name="<?= e($project['project_name']) ?>"
                                            data-description="<?= e($project['description']) ?>"
                                            data-is-active="<?= $project['is_active'] ?>">
                                        Edit
                                    </button>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="project_id" value="<?= $project['project_id'] ?>">
                                        <button type="submit" name="delete_project" class="btn btn-sm btn-outline-danger">
                                            <?= $project['is_active'] ? 'Deactivate' : 'Activate' ?>
                                        </button>
                                    </form>
                                    <a href="<?= BASE_URL ?>/pages/admin/project_assignments.php?project_id=<?= $project['project_id'] ?>" 
                                       class="btn btn-sm btn-outline-info">
                                        Assign
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit Project Modal -->
<div class="modal fade" id="editProjectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Project</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="project_id" id="edit_project_id">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Project Code</label>
                            <input type="text" class="form-control" name="project_code" id="edit_project_code" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Project Name</label>
                            <input type="text" class="form-control" name="project_name" id="edit_project_name" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="edit_description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="edit_is_active">
                        <label class="form-check-label" for="edit_is_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="update_project" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle edit project button clicks
    document.querySelectorAll('.edit-project').forEach(button => {
        button.addEventListener('click', function() {
            const modal = new bootstrap.Modal(document.getElementById('editProjectModal'));
            document.getElementById('edit_project_id').value = this.dataset.projectId;
            document.getElementById('edit_project_code').value = this.dataset.projectCode;
            document.getElementById('edit_project_name').value = this.dataset.projectName;
            document.getElementById('edit_description').value = this.dataset.description;
            document.getElementById('edit_is_active').checked = this.dataset.isActive === '1';
            modal.show();
        });
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>