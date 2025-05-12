<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../classes/EmailService.php';

if (!hasRole('admin')) {
    header('Location: ' . BASE_URL . '/pages/dashboard.php');
    exit;
}

$db = new Database();
$pdo = $db->getConnection();
$translation = new Translation();
$emailService = new EmailService();

$success = '';
$error = '';
$current_template = null;

// Handle all form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        if (isset($_POST['update_settings'])) {
            // Handle system settings update
            foreach ($_POST['settings'] as $key => $value) {
                $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
                $stmt->execute([$value, $key]);
            }
            $success = 'System settings updated successfully';
        } elseif (isset($_POST['update_smtp'])) {
            // Handle SMTP settings
            $smtpFields = [
                'SMTP_HOST',
                'SMTP_PORT',
                'SMTP_USERNAME',
                'SMTP_PASSWORD',
                'SMTP_SECURITY',
                'SMTP_FROM_EMAIL',
                'SMTP_FROM_NAME'
            ];

            foreach ($smtpFields as $field) {
                $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
                $stmt->execute([$_POST[$field], $field]);
            }
            $success = 'SMTP settings updated successfully';
        } elseif (isset($_POST['update_oauth'])) {
            // Handle OAuth settings
            $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'GOOGLE_OAUTH_CLIENT_ID'");
            $stmt->execute([$_POST['google_client_id']]);

            $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'GOOGLE_OAUTH_CLIENT_SECRET'");
            $stmt->execute([$_POST['google_client_secret']]);
            $success = 'OAuth settings updated successfully';
        } elseif (isset($_POST['create_template'])) {
            // Handle new template creation
            $stmt = $pdo->prepare("
                INSERT INTO email_templates 
                (template_name, template_subject, template_body, is_active)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $_POST['new_template_name'],
                $_POST['new_template_subject'],
                $_POST['new_template_body'],
                isset($_POST['new_template_active']) ? 1 : 0
            ]);
            $success = 'Email template created successfully';
        } elseif (isset($_POST['update_template'])) {
            // Handle template update
            $stmt = $pdo->prepare("
                UPDATE email_templates 
                SET template_name = ?, template_subject = ?, template_body = ?, is_active = ?
                WHERE template_id = ?
            ");
            $stmt->execute([
                $_POST['template_name'],
                $_POST['template_subject'],
                $_POST['template_body'],
                isset($_POST['is_active']) ? 1 : 0,
                $_POST['template_id']
            ]);
            $success = 'Email template updated successfully';
        } elseif (isset($_POST['update_translation'])) {
            // Handle translation update
            $stmt = $pdo->prepare("
                UPDATE month_translations
                SET month_name = ?, short_month_name = ?
                WHERE translation_id = ?
            ");
            $stmt->execute([
                $_POST['month_name'],
                $_POST['short_month_name'],
                $_POST['translation_id']
            ]);
            $success = 'Translation updated successfully';
        }

        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = 'Operation failed: ' . $e->getMessage();
    }
}

// Get all system settings
$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Get email templates
$templates = $pdo->query("SELECT * FROM email_templates ORDER BY template_name")->fetchAll();

// Get all languages
$languages = $pdo->query("SELECT * FROM languages WHERE is_active = 1")->fetchAll();

// Get month translations
$monthTranslations = [];
if (isset($_GET['language_id'])) {
    $stmt = $pdo->prepare("
        SELECT mt.*, l.language_name 
        FROM month_translations mt
        JOIN languages l ON mt.language_id = l.language_id
        WHERE mt.language_id = ?
        ORDER BY mt.month_number
    ");
    $stmt->execute([$_GET['language_id']]);
    $monthTranslations = $stmt->fetchAll();
}
?>

<div class="container-fluid">
    <h1 class="mb-4">System Settings</h1>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Application Settings -->
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-gear me-2"></i>Application Settings</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Application Name</label>
                            <input type="text" class="form-control" name="settings[APP_NAME]"
                                value="<?= htmlspecialchars($settings['APP_NAME'] ?? '') ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Default Language</label>
                            <select class="form-select" name="settings[DEFAULT_LANGUAGE]">
                                <?php foreach ($languages as $lang): ?>
                                    <option value="<?= htmlspecialchars($lang['language_code']) ?>"
                                        <?= ($settings['DEFAULT_LANGUAGE'] ?? 'en') === $lang['language_code'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($lang['language_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" name="settings[ETHIOPIAN_CALENDAR_DEFAULT]"
                                id="ethiopian_calendar" value="1"
                                <?= ($settings['ETHIOPIAN_CALENDAR_DEFAULT'] ?? '0') === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="ethiopian_calendar">Default to Ethiopian Calendar</label>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Timesheet Submission Deadline (days)</label>
                            <input type="number" class="form-control"
                                name="settings[TIMESHEET_SUBMISSION_DEADLINE]"
                                value="<?= htmlspecialchars($settings['TIMESHEET_SUBMISSION_DEADLINE'] ?? '5') ?>">
                        </div>

                        <button type="submit" name="update_settings" class="btn btn-primary w-100">
                            <i class="bi bi-save me-2"></i>Save Settings
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Email & Auth Settings -->
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-envelope me-2"></i>Email & Auth Settings</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <h6 class="mb-3">SMTP Settings</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-12">
                                <input type="text" class="form-control" name="SMTP_HOST"
                                    placeholder="SMTP Host" value="<?= htmlspecialchars($settings['SMTP_HOST'] ?? '') ?>">
                            </div>
                            <div class="col-6">
                                <input type="number" class="form-control" name="SMTP_PORT"
                                    placeholder="Port" value="<?= htmlspecialchars($settings['SMTP_PORT'] ?? '587') ?>">
                            </div>
                            <div class="col-6">
                                <select class="form-select" name="SMTP_SECURITY">
                                    <option value="tls" <?= ($settings['SMTP_SECURITY'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS</option>
                                    <option value="ssl" <?= ($settings['SMTP_SECURITY'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <input type="text" class="form-control" name="SMTP_USERNAME"
                                    placeholder="Username" value="<?= htmlspecialchars($settings['SMTP_USERNAME'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <input type="password" class="form-control" name="SMTP_PASSWORD"
                                    placeholder="Password" value="<?= htmlspecialchars($settings['SMTP_PASSWORD'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <input type="email" class="form-control" name="SMTP_FROM_EMAIL"
                                    placeholder="From Email" value="<?= htmlspecialchars($settings['SMTP_FROM_EMAIL'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <input type="text" class="form-control" name="SMTP_FROM_NAME"
                                    placeholder="From Name" value="<?= htmlspecialchars($settings['SMTP_FROM_NAME'] ?? '') ?>">
                            </div>
                        </div>

                        <h6 class="mb-3">Google OAuth</h6>
                        <div class="row g-3">
                            <div class="col-12">
                                <input type="text" class="form-control" name="google_client_id"
                                    placeholder="Client ID" value="<?= htmlspecialchars($settings['GOOGLE_OAUTH_CLIENT_ID'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <input type="password" class="form-control" name="google_client_secret"
                                    placeholder="Client Secret" value="<?= htmlspecialchars($settings['GOOGLE_OAUTH_CLIENT_SECRET'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" name="update_smtp" class="btn btn-primary me-2">
                                <i class="bi bi-save me-2"></i>Save Email
                            </button>
                            <button type="submit" name="update_oauth" class="btn btn-primary">
                                <i class="bi bi-google me-2"></i>Save OAuth
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Email Templates -->
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>Email Templates</h5>
                </div>
                <div class="card-body">
                    <!-- Create New Template Form -->
                    <div class="mb-4">
                        <h6>Create New Template</h6>
                        <form method="POST">
                            <div class="mb-3">
                                <input type="text" class="form-control" name="new_template_name"
                                    placeholder="Template Name" required>
                            </div>
                            <div class="mb-3">
                                <input type="text" class="form-control" name="new_template_subject"
                                    placeholder="Email Subject" required>
                            </div>
                            <div class="mb-3">
                                <textarea class="form-control" name="new_template_body"
                                    rows="4" placeholder="Template Body" required></textarea>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox"
                                    name="new_template_active" id="new_template_active" checked>
                                <label class="form-check-label" for="new_template_active">
                                    Active
                                </label>
                            </div>
                            <button type="submit" name="create_template" class="btn btn-success w-100">
                                <i class="bi bi-plus-circle me-2"></i>Create Template
                            </button>
                        </form>
                    </div>

                    <!-- Existing Templates -->
                    <h6 class="border-top pt-3">Manage Templates</h6>
                    <div class="accordion" id="templatesAccordion">
                        <?php foreach ($templates as $template): ?>
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button" type="button"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#template<?= $template['template_id'] ?>">
                                        <?= htmlspecialchars($template['template_name']) ?>
                                        <?php if ($template['is_active']): ?>
                                            <span class="badge bg-success ms-2">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary ms-2">Inactive</span>
                                        <?php endif; ?>
                                    </button>
                                </h2>
                                <div id="template<?= $template['template_id'] ?>"
                                    class="accordion-collapse collapse">
                                    <div class="accordion-body">
                                        <form method="POST">
                                            <input type="hidden" name="template_id"
                                                value="<?= $template['template_id'] ?>">
                                            <div class="mb-3">
                                                <label class="form-label">Template Name</label>
                                                <input type="text" class="form-control"
                                                    name="template_name"
                                                    value="<?= htmlspecialchars($template['template_name']) ?>">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Subject</label>
                                                <input type="text" class="form-control"
                                                    name="template_subject"
                                                    value="<?= htmlspecialchars($template['template_subject']) ?>">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Body</label>
                                                <textarea class="form-control" name="template_body"
                                                    rows="6"><?= htmlspecialchars($template['template_body']) ?></textarea>
                                            </div>
                                            <div class="form-check mb-3">
                                                <input class="form-check-input" type="checkbox"
                                                    name="is_active"
                                                    id="active<?= $template['template_id'] ?>"
                                                    <?= $template['is_active'] ? 'checked' : '' ?>>
                                                <label class="form-check-label"
                                                    for="active<?= $template['template_id'] ?>">
                                                    Active
                                                </label>
                                            </div>
                                            <div class="d-grid gap-2">
                                                <button type="submit" name="update_template"
                                                    class="btn btn-primary">
                                                    <i class="bi bi-save me-2"></i>Update Template
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Translation Management -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-translate me-2"></i>Translation Management</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <form method="GET">
                                <div class="mb-3">
                                    <label class="form-label">Select Language</label>
                                    <select class="form-select" name="language_id" onchange="this.form.submit()">
                                        <option value="">-- Select Language --</option>
                                        <?php foreach ($languages as $lang): ?>
                                            <option value="<?= htmlspecialchars($lang['language_id']) ?>"
                                                <?= isset($_GET['language_id']) && $_GET['language_id'] == $lang['language_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($lang['language_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </form>
                        </div>

                        <?php if (!empty($monthTranslations)): ?>
                            <div class="col-md-8">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Month Number</th>
                                                <th>Month Name</th>
                                                <th>Short Name</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($monthTranslations as $trans): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($trans['month_number']) ?></td>
                                                    <td><?= htmlspecialchars($trans['month_name']) ?></td>
                                                    <td><?= htmlspecialchars($trans['short_month_name']) ?></td>
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-outline-primary"
                                                            data-bs-toggle="modal" data-bs-target="#editTranslationModal"
                                                            data-id="<?= $trans['translation_id'] ?>"
                                                            data-name="<?= htmlspecialchars($trans['month_name']) ?>"
                                                            data-short="<?= htmlspecialchars($trans['short_month_name']) ?>">
                                                            Edit
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Translation Modal -->
<div class="modal fade" id="editTranslationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Month Translation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="translation_id" id="translationId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Month Name</label>
                        <input type="text" class="form-control" name="month_name" id="monthName" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Short Name</label>
                        <input type="text" class="form-control" name="short_month_name" id="shortMonthName" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_translation" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Initialize translation modal
    document.getElementById('editTranslationModal').addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        document.getElementById('translationId').value = button.getAttribute('data-id');
        document.getElementById('monthName').value = button.getAttribute('data-name');
        document.getElementById('shortMonthName').value = button.getAttribute('data-short');
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>