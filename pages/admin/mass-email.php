<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../classes/EmailService.php';

if (!hasRole('admin')) {
    header('Location: ' . BASE_URL . '/pages/dashboard.php');
    exit;
}

$user = new User();
$emailService = new EmailService();
$error = '';
$success = '';

$users = $user->getAllUsers();
$templates = $emailService->getAllEmailTemplates();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $selected_users = $_POST['users'] ?? [];
        $template_id = $_POST['template_id'];
        $custom_subject = $_POST['subject'];
        $custom_body = $_POST['body'];

        if (empty($selected_users)) {
            throw new Exception("Please select at least one recipient");
        }

        // Get template details if using template
        $template = [];
        if (!empty($template_id)) {
            $template = $emailService->getEmailTemplateById($template_id);
        }

        foreach ($selected_users as $user_id) {
            $user_data = $user->getUserById($user_id);

            $subject = $template['template_subject'] ?? $custom_subject;
            $body = $template['template_body'] ?? $custom_body;

            // Replace placeholders
            $body = str_replace(
                ['{first_name}', '{last_name}', '{email}'],
                [$user_data['first_name'], $user_data['last_name'], $user_data['email']],
                $body
            );

            $emailService->sendEmail(
                $user_data['email'],
                $subject,
                $body
            );
        }

        $success = "Emails sent successfully to " . count($selected_users) . " users";
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<div class="container-fluid">
    <h1 class="mb-4">Mass Email System</h1>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Select Recipients</label>
                            <select class="form-select" multiple size="10" name="users[]">
                                <?php foreach ($users as $u): ?>
                                    <option value="<?= e($u['user_id']) ?>">
                                        <?= e($u['first_name'] . ' ' . $u['last_name'] . ' <' . $u['email'] . '>') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Email Template</label>
                            <select class="form-select" name="template_id" id="templateSelect">
                                <option value="">-- Custom Email --</option>
                                <?php foreach ($templates as $t): ?>
                                    <option value="<?= e($t['template_id']) ?>">
                                        <?= e($t['template_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Subject</label>
                            <input type="text" class="form-control" name="subject" id="subjectInput">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Body</label>
                            <textarea class="form-control" name="body" id="bodyInput" rows="10"></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">Send Emails</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.getElementById('templateSelect').addEventListener('change', function() {
        if (this.value) {
            fetch(`<?= BASE_URL ?>/api/get-template.php?id=${this.value}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('subjectInput').value = data.template_subject;
                    document.getElementById('bodyInput').value = data.template_body;
                });
        }
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>