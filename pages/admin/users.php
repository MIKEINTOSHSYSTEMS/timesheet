<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/header.php';

// Only admin can access this page
if (!hasRole('admin')) {
    header('Location: ' . BASE_URL . '/pages/dashboard.php');
    exit;
}

$user = new User();
$auth = new Auth();
$error = '';
$success = '';

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['create_user'])) {
            // Validate input
            if (empty($_POST['first_name']) || empty($_POST['last_name']) || empty($_POST['email'])) {
                throw new Exception("All fields are required");
            }
            
            if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid email format");
            }
            
            if (strlen($_POST['password']) < 8) {
                throw new Exception("Password must be at least 8 characters");
            }
            
            // Create new user
            $userId = $auth->register([
                'first_name' => trim($_POST['first_name']),
                'last_name' => trim($_POST['last_name']),
                'email' => trim($_POST['email']),
                'password' => $_POST['password'],
                'role' => $_POST['role'],
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            ]);
            
            $success = 'User created successfully';
        } elseif (isset($_POST['update_user'])) {
            // Validate input
            if (empty($_POST['first_name']) || empty($_POST['last_name']) || empty($_POST['email'])) {
                throw new Exception("All fields are required");
            }
            
            if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid email format");
            }
            
            if (!empty($_POST['password']) && strlen($_POST['password']) < 8) {
                throw new Exception("Password must be at least 8 characters");
            }
            
            // Update existing user
            $auth->updateUser($_POST['user_id'], [
                'first_name' => trim($_POST['first_name']),
                'last_name' => trim($_POST['last_name']),
                'email' => trim($_POST['email']),
                'role' => $_POST['role'],
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                'password' => !empty($_POST['password']) ? $_POST['password'] : null
            ]);
            
            $success = 'User updated successfully';
        } elseif (isset($_POST['delete_user'])) {
            // Delete user (soft delete)
            $pdo = (new Database())->getConnection();
            $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE user_id = ?");
            $currentStatus = $user->getUserById($_POST['user_id'])['is_active'];
            $newStatus = $currentStatus ? 0 : 1;
            $stmt->execute([$newStatus, $_POST['user_id']]);
            $success = $newStatus ? 'User activated successfully' : 'User deactivated successfully';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get all users with their roles
//$users = $user->getAllUsers();

// In the users.php, modify the getAllUsers() call:
try {
    $users = $user->getAllUsers();
} catch (Exception $e) {
    error_log("User management error: " . $e->getMessage());
    $error = "Failed to load users. Please try again.";
    $users = [];
}
?>

<!-- Rest of the HTML remains the same, but add role-based filtering if needed -->

<div class="container-fluid">
    <h1 class="mb-4">User Management</h1>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Create New User</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">First Name</label>
                        <input type="text" class="form-control" name="first_name" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Last Name</label>
                        <input type="text" class="form-control" name="last_name" required>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control" name="password" required>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Role</label>
                        <select class="form-select" name="role" required>
                            <option value="employee">Employee</option>
                            <option value="consultant">Consultant</option>
                            <option value="manager">Manager</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check mt-4 pt-2">
                            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                    </div>
                </div>
                
                <button type="submit" name="create_user" class="btn btn-primary">Create User</button>
            </form>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">User List</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                               
                               <td><?= e($u['first_name']. ' ' . $u['last_name']) ?></td> 
                               <td><?= e($u['email']) ?></td>
                                <td><?= ucfirst(e($u['role'])) ?></td>
                                <td>
                                    <span class="badge bg-<?= $u['is_active'] ? 'success' : 'danger' ?>">
                                        <?= $u['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary edit-user" 
                                            data-user-id="<?= $u['user_id'] ?>"
                                            data-first-name="<?= e($u['first_name']) ?>"
                                            data-last-name="<?= e($u['last_name']) ?>"
                                            data-email="<?= e($u['email']) ?>"
                                            data-role="<?= e($u['role']) ?>"
                                            data-is-active="<?= $u['is_active'] ?>">
                                        Edit
                                    </button>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                        <button type="submit" name="delete_user" class="btn btn-sm btn-outline-danger">
                                            <?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-control" name="first_name" id="edit_first_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-control" name="last_name" id="edit_last_name" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" id="edit_email" required>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Role</label>
                            <select class="form-select" name="role" id="edit_role" required>
                                <option value="employee">Employee</option>
                                <option value="consultant">Consultant</option>
                                <option value="manager">Manager</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check mt-4 pt-2">
                                <input class="form-check-input" type="checkbox" name="is_active" id="edit_is_active">
                                <label class="form-check-label" for="edit_is_active">Active</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">New Password (leave blank to keep current)</label>
                        <input type="password" class="form-control" name="password">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="update_user" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle edit user button clicks
    document.querySelectorAll('.edit-user').forEach(button => {
        button.addEventListener('click', function() {
            const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
            document.getElementById('edit_user_id').value = this.dataset.userId;
            document.getElementById('edit_first_name').value = this.dataset.firstName;
            document.getElementById('edit_last_name').value = this.dataset.lastName;
            document.getElementById('edit_email').value = this.dataset.email;
            document.getElementById('edit_role').value = this.dataset.role;
            document.getElementById('edit_is_active').checked = this.dataset.isActive === '1';
            modal.show();
        });
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>