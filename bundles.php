<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/bundle-functions.php';

$bundles = get_published_bundles();

$pageTitle = 'Resource Bundles';
$pageDescription = 'One-time resource bundles for teachers — pay once, keep permanent access to a curated set of ' . SITE_NAME . ' resources, no subscription required.';
require_once __DIR__ . '/includes/header.php';
?>
<div class="container py-5">
    <div class="text-center mb-5">
        <h1 class="fw-bold mb-2">Resource Bundles</h1>
        <p class="text-secondary mx-auto" style="max-width:600px;">Only need a specific set of resources, not an ongoing subscription? Buy a bundle once and keep it &mdash; no renewal, no expiry.</p>
    </div>

    <?php if (empty($bundles)): ?>
        <p class="text-secondary text-center">No bundles are available yet &mdash; check back soon.</p>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?php foreach ($bundles as $bundle): ?>
                <?php include __DIR__ . '/includes/bundle-card.php'; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
