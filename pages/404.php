<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8 text-center">
            <h1 class="display-1 text-danger">404</h1>
            <h2>Page Not Found</h2>
            <p class="lead">The page you are looking for doesn't exist or has been moved.</p>
            <a href="<?= BASE_URL ?>/pages/dashboard.php" class="btn btn-primary">Go to Dashboard</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>