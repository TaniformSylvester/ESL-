    </main>

<footer class="site-footer">
    <div class="container">
        <div class="row gy-4">
            <div class="col-lg-4">
                <a href="<?= e(base_url()) ?>" aria-label="<?= e(SITE_NAME) ?> home">
                    <img class="footer-logo" src="<?= e(versioned_asset_url('brand/teachluma-logo-white.svg')) ?>" alt="<?= e(SITE_NAME) ?>" width="228" height="44" loading="lazy">
                </a>
                <p class="footer-tagline mb-2">Teach better. Save time. Make learning engaging.</p>
                <p class="small mb-3"><?= e(SITE_DESCRIPTION) ?></p>
                <p class="small mb-0">
                    <i class="fa-solid fa-envelope me-1" aria-hidden="true"></i>
                    <a href="mailto:<?= e(CONTACT_EMAIL) ?>"><?= e(CONTACT_EMAIL) ?></a>
                </p>
            </div>
            <div class="col-6 col-md-4 col-lg-2 offset-lg-1">
                <h2>Explore</h2>
                <ul class="list-unstyled footer-links small mb-0">
                    <li><a href="<?= e(base_url('resources.php')) ?>">Resources</a></li>
                    <li><a href="<?= e(base_url('games.php')) ?>">Games</a></li>
                    <li><a href="<?= e(base_url('videos.php')) ?>">Videos</a></li>
                    <li><a href="<?= e(base_url('bundles.php')) ?>">Bundles</a></li>
                    <li><a href="<?= e(base_url('resources.php?access=free')) ?>">Free Resources</a></li>
                </ul>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <h2>For Teachers</h2>
                <ul class="list-unstyled footer-links small mb-0">
                    <li><a href="<?= e(base_url('teacher-hub.php')) ?>">Teacher Hub</a></li>
                    <li><a href="<?= e(base_url('teacher-tools.php')) ?>">Teacher Tools</a></li>
                    <li><a href="<?= e(base_url('request-resource.php')) ?>">Request a Resource</a></li>
                    <li><a href="<?= e(base_url('pricing.php')) ?>">Pricing</a></li>
                </ul>
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                <h2>TeachLuma</h2>
                <ul class="list-unstyled footer-links small mb-0">
                    <li><a href="<?= e(base_url('about.php')) ?>">About</a></li>
                    <li><a href="<?= e(base_url('contact.php')) ?>">Contact</a></li>
                    <li><a href="<?= e(base_url('terms.php')) ?>">Terms &amp; Conditions</a></li>
                    <li><a href="<?= e(base_url('privacy.php')) ?>">Privacy Policy</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom d-flex flex-column flex-md-row justify-content-between gap-2">
            <p class="mb-0">&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?>. All rights reserved.</p>
            <p class="mb-0">ESL &middot; Math &middot; Science resources for classrooms across Southeast Asia</p>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>window.APP_BASE_URL = <?= json_encode(rtrim(SITE_URL, '/')) ?>;</script>
<script src="<?= e(versioned_asset_url('js/main.js')) ?>"></script>
<script src="<?= e(versioned_asset_url('js/favorites.js')) ?>"></script>
<script src="<?= e(versioned_asset_url('js/reviews.js')) ?>"></script>
<script src="<?= e(versioned_asset_url('js/downloads.js')) ?>"></script>
<script src="<?= e(versioned_asset_url('js/video-embed.js')) ?>"></script>
<script src="<?= e(versioned_asset_url('js/teacher-tools.js')) ?>"></script>
<script src="<?= e(versioned_asset_url('js/bundle-gallery.js')) ?>"></script>
<?php if (GA_ENABLED && GA_MEASUREMENT_ID !== ''): ?>
<script src="<?= e(versioned_asset_url('js/analytics.js')) ?>"></script>
<?php endif; ?>
</body>
</html>
