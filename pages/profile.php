<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../includes/header.php';

$user = (new User())->getUserById($_SESSION['user_id']);
$auth = new Auth();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = [
            'first_name' => trim($_POST['first_name']),
            'last_name' => trim($_POST['last_name']),
            'email' => trim($_POST['email']),
            'phone_number' => trim($_POST['phone_number'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'preferred_language' => $_POST['preferred_language'] ?? 'en',
            'ethiopian_calendar_preference' => isset($_POST['ethiopian_calendar_preference']) ? 1 : 0
        ];

        if (!empty($_POST['password'])) {
            if ($_POST['password'] !== $_POST['password_confirm']) {
                throw new Exception("Passwords do not match");
            }
            $data['password'] = $_POST['password'];
        }

        $auth->updateUser($_SESSION['user_id'], $data);
        $_SESSION['success_message'] = 'Profile updated successfully';
        header('Location: ' . BASE_URL . '/pages/profile.php');
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<div class="container">
    <h1 class="mb-4">My Profile</h1>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">First Name</label>
                                <input type="text" class="form-control" name="first_name" 
                                       value="<?= e($user['first_name']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Last Name</label>
                                <input type="text" class="form-control" name="last_name" 
                                       value="<?= e($user['last_name']) ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" 
                                   value="<?= e($user['email']) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" name="phone_number" 
                                   value="<?= e($user['phone_number'] ?? '') ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address"><?= e($user['address'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Preferred Language</label>
                            <select class="form-select" name="preferred_language">
                                <option value="en" <?= ($user['preferred_language'] ?? 'en') === 'en' ? 'selected' : '' ?>>English</option>
                                <option value="am" <?= ($user['preferred_language'] ?? 'en') === 'am' ? 'selected' : '' ?>>አማርኛ</option>
                            </select>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" name="ethiopian_calendar_preference" 
                                   id="ethiopian_calendar" <?= ($user['ethiopian_calendar_preference'] ?? 0) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="ethiopian_calendar">Use Ethiopian Calendar</label>
                        </div>
                        
                        <hr>
                        
                        <h5>Change Password</h5>
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" class="form-control" name="password">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" name="password_confirm">
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Update Profile</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>