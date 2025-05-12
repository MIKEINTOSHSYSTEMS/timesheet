<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../classes/Role.php';
require_once __DIR__ . '/../../classes/Permission.php';

// Only admin can access this page
if (!hasRole('admin')) {
    header('Location: ' . BASE_URL . '/pages/dashboard.php');
    exit;
}

$role = new Role();
$permission = new Permission();
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['create_role'])) {
            if (empty($_POST['role_name'])) {
                throw new Exception("Role name is required");
            }

            $role->createRole([
                'role_name' => trim($_POST['role_name']),
                'role_description' => trim($_POST['role_description'] ?? ''),
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            ]);

            $success = 'Role created successfully';
        } elseif (isset($_POST['update_role'])) {
            if (empty($_POST['role_name']) || empty($_POST['role_id'])) {
                throw new Exception("Role name and ID are required");
            }

            $role->updateRole($_POST['role_id'], [
                'role_name' => trim($_POST['role_name']),
                'role_description' => trim($_POST['role_description'] ?? ''),
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            ]);

            $success = 'Role updated successfully';
        } elseif (isset($_POST['delete_role'])) {
            if (empty($_POST['role_id'])) {
                throw new Exception("Role ID is required");
            }

            $role->deleteRole($_POST['role_id']);
            $success = 'Role deleted successfully';
        } elseif (isset($_POST['update_permissions'])) {
            $role_id = $_POST['role_id'];
            $permissions = $_POST['permissions'] ?? [];

            $role->updateRolePermissions($role_id, $permissions);
            $success = 'Permissions updated successfully';
        } elseif (isset($_POST['update_menu_permissions'])) {
            $role_id = $_POST['role_id'];
            $menu_permissions = $_POST['menu_items'] ?? [];

            $role->updateMenuPermissions($role_id, $menu_permissions);
            $success = 'Menu permissions updated successfully';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get current roles and permissions
$roles = $role->getAllRoles();
$all_permissions = $permission->getAllPermissions();
$menu_items = $permission->getDefaultMenuItems();

// Get selected role data if requested
$selected_role = null;
$role_permissions = [];
$role_menu_permissions = [];

if (isset($_GET['role_id'])) {
    $selected_role = $role->getRole($_GET['role_id']);
    $role_permissions = $role->getRolePermissions($_GET['role_id']);
    $role_menu_permissions = $role->getMenuPermissions($_GET['role_id']);
}
?>

<div class="container mt-4">
    <h1 class="mb-4">Role Management</h1>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="row">
        <!-- Role List -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Roles List</h5>
                </div>
                <div class="card-body">
                    <form method="post">
                        <div class="input-group mb-3">
                            <input type="text" class="form-control" name="role_name"
                                placeholder="New role name" required>
                            <button type="submit" name="create_role"
                                class="btn btn-primary">Create</button>
                        </div>
                    </form>

                    <div class="list-group">
                        <?php foreach ($roles as $r): ?>
                            <a href="?role_id=<?= $r['role_id'] ?>"
                                class="list-group-item list-group-item-action <?= ($selected_role && $selected_role['role_id'] == $r['role_id']) ? 'active' : '' ?>">
                                <?= htmlspecialchars($r['role_name']) ?>
                                <?= !$r['is_active'] ? ' (Inactive)' : '' ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Role Details -->
        <div class="col-md-8">
            <?php if ($selected_role): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Edit Role: <?= htmlspecialchars($selected_role['role_name']) ?></h5>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="role_id" value="<?= $selected_role['role_id'] ?>">

                            <div class="mb-3">
                                <label class="form-label">Role Name</label>
                                <input type="text" class="form-control" name="role_name"
                                    value="<?= htmlspecialchars($selected_role['role_name']) ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="role_description"
                                    rows="3"><?= htmlspecialchars($selected_role['role_description']) ?></textarea>
                            </div>

                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" name="is_active"
                                    id="is_active" <?= $selected_role['is_active'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" name="update_role" class="btn btn-primary">Update</button>
                                <button type="submit" name="delete_role" class="btn btn-danger"
                                    onclick="return confirm('Are you sure?')">Delete</button>
                            </div>
                        </form>

                        <hr>

                        <!-- Permissions Management -->
                        <h5 class="mt-4">System Permissions</h5>
                        <form method="post">
                            <input type="hidden" name="role_id" value="<?= $selected_role['role_id'] ?>">

                            <div class="row">
                                <?php foreach ($all_permissions as $perm): ?>
                                    <div class="col-md-6 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox"
                                                name="permissions[]" value="<?= $perm['permission_id'] ?>"
                                                id="perm_<?= $perm['permission_id'] ?>"
                                                <?= in_array($perm['permission_id'], array_column($role_permissions, 'permission_id')) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="perm_<?= $perm['permission_id'] ?>">
                                                <?= htmlspecialchars($perm['permission_name']) ?>
                                                <small class="text-muted">(<?= $perm['permission_key'] ?>)</small>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <button type="submit" name="update_permissions" class="btn btn-primary mt-3">
                                Update Permissions
                            </button>
                        </form>

                        <hr>

                        <!-- Menu Access Control -->
                        <h5 class="mt-4">Menu Access</h5>
                        <form method="post">
                            <input type="hidden" name="role_id" value="<?= $selected_role['role_id'] ?>">

                            <div class="row">
                                <?php foreach ($menu_items as $key => $name):
                                    $has_access = false;
                                    foreach ($role_menu_permissions as $mp) {
                                        if ($mp['menu_item'] === $key && $mp['can_access']) {
                                            $has_access = true;
                                            break;
                                        }
                                    }
                                ?>
                                    <div class="col-md-4 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox"
                                                name="menu_items[<?= $key ?>]" value="1"
                                                id="menu_<?= $key ?>" <?= $has_access ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="menu_<?= $key ?>">
                                                <?= htmlspecialchars($name) ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <button type="submit" name="update_menu_permissions" class="btn btn-primary mt-3">
                                Update Menu Access
                            </button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info">Select a role from the list to edit permissions</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>