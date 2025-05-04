<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../includes/header.php';

$auth = new Auth();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (empty($_POST['current_password']) || empty($_POST['new_password']) || empty($_POST['confirm_password'])) {
            throw new Exception("All fields are required");
        }
        
        if ($_POST['new_password'] !== $_POST['confirm_password']) {
            throw new Exception("New passwords do not match");
        }
        
        if (strlen($_POST['new_password']) < 8) {
            throw new Exception("Password must be at least 8 characters");
        }
        
        $result = $auth->changePassword(
            $_SESSION['user_id'],
            $_POST['current_password'],
            $_POST['new_password']
        );
        
        if ($result) {
            $success = 'Password changed successfully';
            // Send email notification
            $user = (new User())->getUserById($_SESSION['user_id']);
            $subject = "Password Changed - " . APP_NAME;
            $message = "Hello " . e($user['first_name']) . ",\n\n";
            $message .= "Your password was recently changed. If you didn't make this change, please contact support immediately.\n\n";
            $message .= "Thank you,\n" . APP_NAME . " Team";
            mail($user['email'], $subject, $message);
        } else {
            throw new Exception("Failed to change password. Current password may be incorrect.");
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card mt-4">
                <div class="card-header">
                    <h4>Change Password</h4>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= e($error) ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= e($success) ?></div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8">
                            <div class="form-text">Minimum 8 characters</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8">
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Change Password</button>
                            <a href="<?= BASE_URL ?>/pages/profile.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>