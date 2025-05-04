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
    try {
        if (empty($_POST['first_name']) || empty($_POST['last_name']) || empty($_POST['email']) || empty($_POST['password'])) {
            throw new Exception("All fields are required");
        }
        
        if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format");
        }
        
        if (strlen($_POST['password']) < 8) {
            throw new Exception("Password must be at least 8 characters");
        }
        
        if ($_POST['password'] !== $_POST['password_confirm']) {
            throw new Exception("Passwords do not match");
        }
        
        $auth = new Auth();
        $userId = $auth->register([
            'first_name' => trim($_POST['first_name']),
            'last_name' => trim($_POST['last_name']),
            'email' => trim($_POST['email']),
            'password' => $_POST['password'],
            'role' => 'employee', // Default role
            'is_active' => 0 // Needs admin approval
        ]);
        
        $_SESSION['success_message'] = 'Registration successful! Please wait for admin approval.';
        header('Location: ' . BASE_URL . '/pages/login.php');
        exit;
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
                    <h3 class="card-title text-center">Register</h3>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= e($error) ?></div>
                    <?php endif; ?>
                    
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
                        
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" name="password" required minlength="8">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" name="password_confirm" required minlength="8">
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">Register</button>
                    </form>
                    
                    <div class="mt-3 text-center">
                        Already have an account? <a href="<?= BASE_URL ?>/pages/login.php">Login here</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>