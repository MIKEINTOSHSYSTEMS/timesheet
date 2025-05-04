<?php
// calendar-switcher.php
$ethiopianCalendar = $_SESSION['ethiopian_calendar'] ?? false;
$isFooter = strpos($_SERVER['REQUEST_URI'], 'footer') !== false;
?>
<div class="calendar-switcher <?= $isFooter ? 'mb-3' : '' ?>">
    <form method="post" action="<?= BASE_URL ?>/process/calendar-process.php" class="<?= $isFooter ? '' : 'd-flex align-items-center' ?>">
        <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" id="calendarSwitch"
                name="ethiopian_calendar" value="1"
                <?= $ethiopianCalendar ? 'checked' : '' ?> onchange="this.form.submit()">

            <label class="form-check-label <?= $isFooter ? 'text-white-50' : 'text-white' ?>" for="calendarSwitch">
                <?= $isFooter
                    ? 'Ethiopian Calendar'
                    : '<img src="' . BASE_URL . '/assets/images/ethcal.png" alt="Ethiopian Calendar" style="height: 24px;">' ?>
            </label>

        </div>
        <input type="hidden" name="redirect" value="<?= $_SERVER['REQUEST_URI'] ?>">
    </form>
</div>