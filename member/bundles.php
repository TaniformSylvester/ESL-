<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/bundle-functions.php';

require_login();
$user = current_user();

$purchases = get_user_bundle_purchases($user['id']);

$pageTitle = 'My Bundles';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="container py-5">
    <h1 class="fw-bold mb-4">My Bundles</h1>

    <?php if (empty($purchases)): ?>
        <div class="alert alert-info">
            You haven't purchased any bundles yet. <a href="<?= e(base_url('bundles.php')) ?>">Browse bundles</a> for one-time access to a curated set of resources.
        </div>
    <?php else: ?>
        <div class="list-group">
            <?php foreach ($purchases as $purchase): ?>
                <a href="<?= e(base_url('bundle.php?slug=' . urlencode($purchase['slug']))) ?>" class="list-group-item list-group-item-action d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span><?= e($purchase['title']) ?></span>
                    <span class="text-secondary small text-nowrap">
                        <?= e(format_currency($purchase['amount'])) ?> &middot; Purchased <?= e(format_date($purchase['purchased_at'])) ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
