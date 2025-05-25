<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/header.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/pages/dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    $auth = new Auth();
    if ($auth->login($email, $password)) {
        // Redirect to intended page or dashboard
        $redirect = $_SESSION['redirect_url'] ?? BASE_URL . '/pages/dashboard.php';
        unset($_SESSION['redirect_url']);
        header('Location: ' . $redirect);
        exit;
    } else {
        $error = 'Invalid email or password';
    }
}
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-sm mt-5">
                <div class="card-body">
                    <h3 class="card-title text-center">Login</h3>
                    <?php if (isset($_SESSION['error_message'])): ?>
                        <div class="alert alert-danger"><?= e($_SESSION['error_message']) ?></div>
                        <?php unset($_SESSION['error_message']); ?>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= e($error) ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember" name="remember">
                            <label class="form-check-label" for="remember">Remember me</label>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Login</button>

                        <!-- Add to login form with google Sign In-->
                        <div class="text-center mt-4">
                            <p>Or sign in with:</p>
                            <a href="<?= BASE_URL ?>/auth/google" class="btn btn-outline-danger">
                                <i class="fab fa-google"></i> Google
                            </a>
                        </div>
                    </form>
                    <div class="mt-3 text-center">
                        <p>Don't have an account? <a href="<?= BASE_URL ?>/pages/register.php">Register here</a></p>

                    <div class="mt-3 text-center">
                        <a href="<?= BASE_URL ?>/pages/forgot-password.php">Forgot password?</a>
                    </div>

                    <?php if (file_exists(__DIR__ . '/../config/oauth-config.php')): ?>
                        <div class="mt-3 text-center">
                            <p class="mb-2">Or login with:</p>
                            <a href="<?= BASE_URL ?>/process/google-login.php" class="btn btn-outline-danger">
                                <i class="bi bi-google"></i> Google
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>