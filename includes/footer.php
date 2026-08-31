    </main>

<footer class="bg-dark text-light pt-5 pb-4 mt-5">
    <div class="container">
        <div class="row gy-4">
            <div class="col-md-4">
                <h5 class="fw-bold"><i class="fa-solid fa-graduation-cap me-1"></i> <?= e(SITE_NAME) ?></h5>
                <p class="text-secondary small"><?= e(SITE_DESCRIPTION) ?></p>
            </div>
            <div class="col-md-4">
                <h6 class="fw-bold">Quick Links</h6>
                <ul class="list-unstyled small">
                    <li><a class="text-secondary text-decoration-none" href="<?= e(base_url('resources.php')) ?>">Resources</a></li>
                    <li><a class="text-secondary text-decoration-none" href="<?= e(base_url('teacher-hub.php')) ?>">Teacher Hub</a></li>
                    <li><a class="text-secondary text-decoration-none" href="<?= e(base_url('pricing.php')) ?>">Pricing</a></li>
                    <li><a class="text-secondary text-decoration-none" href="<?= e(base_url('about.php')) ?>">About</a></li>
                    <li><a class="text-secondary text-decoration-none" href="<?= e(base_url('contact.php')) ?>">Contact</a></li>
                    <li><a class="text-secondary text-decoration-none" href="<?= e(base_url('terms.php')) ?>">Terms &amp; Conditions</a></li>
                    <li><a class="text-secondary text-decoration-none" href="<?= e(base_url('privacy.php')) ?>">Privacy Policy</a></li>
                </ul>
            </div>
            <div class="col-md-4">
                <h6 class="fw-bold">Contact</h6>
                <p class="text-secondary small mb-1">
                    <i class="fa-solid fa-envelope me-1"></i>
                    <a class="text-secondary text-decoration-none" href="mailto:<?= e(CONTACT_EMAIL) ?>"><?= e(CONTACT_EMAIL) ?></a>
                </p>
            </div>
        </div>
        <hr class="border-secondary">
        <p class="text-secondary small text-center mb-0">&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?>. All rights reserved.</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>window.APP_BASE_URL = <?= json_encode(rtrim(SITE_URL, '/')) ?>;</script>
<script src="<?= e(asset_url('js/main.js')) ?>"></script>
<script src="<?= e(asset_url('js/favorites.js')) ?>"></script>
<script src="<?= e(asset_url('js/reviews.js')) ?>"></script>
<script src="<?= e(asset_url('js/downloads.js')) ?>"></script>
<script src="<?= e(asset_url('js/teaching-demo.js')) ?>"></script>
<?php if (GA_ENABLED && GA_MEASUREMENT_ID !== ''): ?>
<script src="<?= e(asset_url('js/analytics.js')) ?>"></script>
<?php endif; ?>
</body>
</html>
