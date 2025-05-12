<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/header.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/pages/dashboard.php');
    exit;
}

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (empty($_POST['email'])) {
            throw new Exception("Email is required");
        }

        $user = new User();
        $emailService = new EmailService();
        $user_data = $user->getUserByEmail($_POST['email']);

        if ($user_data) {
            $token = $user->createPasswordResetToken($user_data['user_id']);
            if ($token) {
                $emailService->sendPasswordResetEmail($user_data['email'], $token);
            }
            $success = "If the email exists in our system, you'll receive a password reset link.";
        } else {
            $success = "If the email exists in our system, you'll receive a password reset link.";
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-sm mt-5">
                <div class="card-body">
                    <h3 class="card-title text-center">Forgot Password</h3>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= e($error) ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= $success ?></div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">Reset Password</button>
                    </form>
                    
                    <div class="mt-3 text-center">
                        Remember your password? <a href="<?= BASE_URL ?>/pages/login.php">Login here</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>