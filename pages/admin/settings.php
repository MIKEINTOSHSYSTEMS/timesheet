<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/header.php';

// Only allow admin access
if (!hasRole('admin')) {
    header('Location: ' . BASE_URL . '/pages/dashboard.php');
    exit;
}

$db = new Database();
$pdo = $db->getConnection();
$translation = new Translation();

// Handle form submissions
$success = '';
$error = '';

// Handle system settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    try {
        $pdo->beginTransaction();
        
        foreach ($_POST['settings'] as $key => $value) {
            $stmt = $pdo->prepare("
                UPDATE system_settings 
                SET setting_value = ?, updated_at = NOW()
                WHERE setting_key = ?
            ");
            $stmt->execute([$value, $key]);
        }
        
        $pdo->commit();
        $success = 'System settings updated successfully';
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = 'Failed to update settings: ' . $e->getMessage();
    }
}

// Handle translation update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_translation'])) {
    try {
        $pdo->beginTransaction();
        
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
        
        $pdo->commit();
        $success = 'Translation updated successfully';
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = 'Failed to update translation: ' . $e->getMessage();
    }
}

// Get all system settings - FIXED THIS PART
$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Get all languages
$stmt = $pdo->query("SELECT * FROM languages WHERE is_active = 1");
$languages = $stmt->fetchAll();

// Get month translations for editing
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

<!-- REST OF THE HTML REMAINS THE SAME AS BEFORE -->

<div class="container-fluid">
    <h1 class="mb-4">System Settings</h1>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Application Settings</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Application Name</label>
                            <input type="text" class="form-control" name="settings[APP_NAME]" 
                                   value="<?= e($settings['APP_NAME'] ?? '') ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Default Language</label>
                            <select class="form-select" name="settings[DEFAULT_LANGUAGE]">
                                <?php foreach ($languages as $lang): ?>
                                    <option value="<?= e($lang['language_code']) ?>" 
                                        <?= ($settings['DEFAULT_LANGUAGE'] ?? 'en') === $lang['language_code'] ? 'selected' : '' ?>>
                                        <?= e($lang['language_name']) ?>
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
                            <label class="form-label">Timesheet Submission Deadline (days after month end)</label>
                            <input type="number" class="form-control" name="settings[TIMESHEET_SUBMISSION_DEADLINE]" 
                                   value="<?= e($settings['TIMESHEET_SUBMISSION_DEADLINE'] ?? '5') ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Google OAuth Client ID</label>
                            <input type="text" class="form-control" name="settings[GOOGLE_OAUTH_CLIENT_ID]" 
                                   value="<?= e($settings['GOOGLE_OAUTH_CLIENT_ID'] ?? '') ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Google OAuth Client Secret</label>
                            <input type="password" class="form-control" name="settings[GOOGLE_OAUTH_CLIENT_SECRET]" 
                                   value="<?= e($settings['GOOGLE_OAUTH_CLIENT_SECRET'] ?? '') ?>">
                        </div>
                        
                        <button type="submit" name="update_settings" class="btn btn-primary">Save Settings</button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Translation Management</h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="mb-4">
                        <div class="mb-3">
                            <label class="form-label">Select Language to Edit</label>
                            <select class="form-select" name="language_id" onchange="this.form.submit()">
                                <option value="">-- Select Language --</option>
                                <?php foreach ($languages as $lang): ?>
                                    <option value="<?= e($lang['language_id']) ?>" 
                                        <?= isset($_GET['language_id']) && $_GET['language_id'] == $lang['language_id'] ? 'selected' : '' ?>>
                                        <?= e($lang['language_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                    
                    <?php if (!empty($monthTranslations)): ?>
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
                                            <td><?= e($trans['month_number']) ?></td>
                                            <td><?= e($trans['month_name']) ?></td>
                                            <td><?= e($trans['short_month_name']) ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                                        data-bs-toggle="modal" data-bs-target="#editTranslationModal"
                                                        data-id="<?= e($trans['translation_id']) ?>"
                                                        data-name="<?= e($trans['month_name']) ?>"
                                                        data-short="<?= e($trans['short_month_name']) ?>">
                                                    Edit
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
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
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="translation_id" id="translationId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Month Name</label>
                        <input type="text" class="form-control" name="month_name" id="monthName">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Short Name</label>
                        <input type="text" class="form-control" name="short_month_name" id="shortMonthName">
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
// Handle modal data
document.getElementById('editTranslationModal').addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    document.getElementById('translationId').value = button.getAttribute('data-id');
    document.getElementById('monthName').value = button.getAttribute('data-name');
    document.getElementById('shortMonthName').value = button.getAttribute('data-short');
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>