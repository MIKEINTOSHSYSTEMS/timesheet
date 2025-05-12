<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../classes/Role.php';
require_once __DIR__ . '/../../classes/Permission.php';

if (!hasRole('admin')) {
    header('Location: ' . BASE_URL . '/pages/dashboard.php');
    exit;
}

$permission = new Permission();
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['create_permission'])) {
            $permission->createPermission([
                'permission_name' => $_POST['permission_name'],
                'permission_key' => $_POST['permission_key'],
                'description' => $_POST['description'] ?? null
            ]);
            $success = 'Permission created successfully';
        } elseif (isset($_POST['update_permission'])) {
            $permission->updatePermission($_POST['permission_id'], [
                'permission_name' => $_POST['permission_name'],
                'permission_key' => $_POST['permission_key'],
                'description' => $_POST['description'] ?? null
            ]);
            $success = 'Permission updated successfully';
        } elseif (isset($_POST['delete_permission'])) {
            $permission->deletePermission($_POST['permission_id']);
            $success = 'Permission deleted successfully';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$permissions = $permission->getAllPermissions();
?>

<div class="container mt-4">
    <h1 class="mb-4">Permissions Management</h1>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Create New Permission</h5>
        </div>
        <div class="card-body">
            <form method="post">
                <div class="row g-3">
                    <div class="col-md-4">
                        <input type="text" class="form-control" name="permission_name"
                            placeholder="Permission Name" required>
                    </div>
                    <div class="col-md-4">
                        <input type="text" class="form-control" name="permission_key"
                            placeholder="Permission Key (e.g., edit_users)" required>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" name="create_permission" class="btn btn-primary">
                            Create Permission
                        </button>
                    </div>
                </div>
                <div class="mt-2">
                    <textarea class="form-control" name="description"
                        placeholder="Description (optional)" rows="2"></textarea>
                </div>
            </form>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0">Existing Permissions</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Key</th>
                            <th>Description</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($permissions as $p): ?>
                            <tr>
                                <td><?= htmlspecialchars($p['permission_name']) ?></td>
                                <td><code><?= htmlspecialchars($p['permission_key']) ?></code></td>
                                <td><?= htmlspecialchars($p['description']) ?></td>
                                <td>
                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal"
                                        data-bs-target="#editModal<?= $p['permission_id'] ?>">
                                        Edit
                                    </button>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="permission_id"
                                            value="<?= $p['permission_id'] ?>">
                                        <button type="submit" name="delete_permission"
                                            class="btn btn-sm btn-danger"
                                            onclick="return confirm('Delete this permission?')">
                                            Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>

                            <!-- Edit Modal -->
                            <div class="modal fade" id="editModal<?= $p['permission_id'] ?>">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="post">
                                            <input type="hidden" name="permission_id"
                                                value="<?= $p['permission_id'] ?>">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit Permission</h5>
                                                <button type="button" class="btn-close"
                                                    data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label>Permission Name</label>
                                                    <input type="text" class="form-control"
                                                        name="permission_name"
                                                        value="<?= htmlspecialchars($p['permission_name']) ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label>Permission Key</label>
                                                    <input type="text" class="form-control"
                                                        name="permission_key"
                                                        value="<?= htmlspecialchars($p['permission_key']) ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label>Description</label>
                                                    <textarea class="form-control" name="description"
                                                        rows="3"><?= htmlspecialchars($p['description']) ?></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary"
                                                    data-bs-dismiss="modal">Close</button>
                                                <button type="submit" name="update_permission"
                                                    class="btn btn-primary">Save Changes</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>