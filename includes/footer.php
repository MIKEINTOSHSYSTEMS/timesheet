</div> <!-- Close container from header -->

<br>
<p>

</p>
</br>

<footer class="footer mt-auto py-4 bg-dark text-white">
    <div class="container">
        <div class="row">
            <!-- Company Info -->
            <div class="col-lg-4 mb-4 mb-lg-0">
                <div class="d-flex align-items-center mb-3">
                    <i class="bi bi-calendar-check fs-3 me-2"></i>
                    <h5 class="mb-0"><?= APP_NAME ?></h5>
                </div>
                <p class="small">Version <?= APP_VERSION ?></p>
                <p class="small">© <?= date('Y') ?> <a href="https://merqconsultancy.org">MERQ Consultancy</a>. All rights reserved.</p>
            </div>

            <!-- Quick Links -->
            <div class="col-lg-4 mb-4 mb-lg-0">
                <h5 class="mb-3">Quick Links</h5>
                <ul class="list-unstyled">
                    <li class="mb-2"><a href="<?= BASE_URL ?>/pages/dashboard.php" class="text-white-50">Dashboard</a></li>
                    <li class="mb-2"><a href="<?= BASE_URL ?>/pages/timesheet.php" class="text-white-50">Timesheet</a></li>
                    <li class="mb-2"><a href="<?= BASE_URL ?>/pages/profile.php" class="text-white-50">Profile</a></li>
                </ul>
            </div>

            <!-- Settings & Language -->
            <div class="col-lg-4">
                <h5 class="mb-3">Settings</h5>

                <!-- Language Selector -->
                <div class="mb-3">
                    <?php include __DIR__ . '/language-switcher.php'; ?>
                </div>

                <!-- Calendar Switcher -->
                <div class="mb-3">
                    <?php include __DIR__ . '/calendar-switcher.php'; ?>
                </div>

                <!-- Contact Info -->
                <div class="mt-4">
                    <h5 class="mb-3">Help & Support</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="bi bi-envelope me-2"></i> <a href="mailto:hr@merqconsultancy.org" class="text-white-50">hr@merqconsultancy.org</a></li>
                        <li class="mb-2"><i class="bi bi-telephone me-2"></i> <a href="tel:+251910810382" class="text-white-50">+251 910 810 382</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/script.js"></script>
</body>

</html>
<?php
if (ob_get_level() > 0) {
    ob_end_flush(); // Send the buffered output
}
?>