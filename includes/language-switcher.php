<?php
$translation = new Translation();
$languages = [
    'en' => 'English',
    'am' => 'አማርኛ'
];
$currentLanguage = $_SESSION['language'] ?? 'en';
?>
<div class="language-switcher">
    <form method="post" action="<?= BASE_URL ?>/process/language-process.php">
        <div class="input-group">
            <label for="language-select" class="form-label text-white-50 mb-2">Select Language:</label>
            <label for="language-select" class="form-label text-white-50 mb-2">
                <i class="bi bi-translate me-2"></i>Select Language:
            </label>
            <select name="language" id="language-select" class="form-select" onchange="this.form.submit()">
                <?php foreach ($languages as $code => $name): ?>
                    <option value="<?= $code ?>" <?= $currentLanguage === $code ? 'selected' : '' ?>>
                        <?= $name ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" name="redirect" value="<?= $_SERVER['REQUEST_URI'] ?>">
        </div>
    </form>
</div>