<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bundle-functions.php';
require_once __DIR__ . '/includes/resource-functions.php';
require_once __DIR__ . '/includes/seo-functions.php';

$slug = trim((string)($_GET['slug'] ?? ''));
$bundle = $slug !== '' ? get_published_bundle_by_slug($slug) : null;

if (!$bundle) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$isLoggedIn = is_logged_in();
$alreadyOwned = $isLoggedIn && has_purchased_bundle((int)$_SESSION['user_id'], (int)$bundle['id']);
$bundleResources = get_bundle_resources((int)$bundle['id']);

$pageTitle = $bundle['title'];
$pageDescription = !empty($bundle['description'])
    ? seo_truncate_at_word($bundle['description'], 160)
    : ($bundle['title'] . ' — a one-time resource bundle from ' . SITE_NAME . '. Pay once, keep permanent access.');
require_once __DIR__ . '/includes/header.php';
?>
<div class="container py-5">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="<?= e(base_url('bundles.php')) ?>">Bundles</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= e($bundle['title']) ?></li>
        </ol>
    </nav>

    <?php if (($_GET['stripe'] ?? '') === 'success'): ?>
        <div class="alert alert-success alert-dismissible fade show">
            Payment received! We're confirming it with Stripe now — your access will show as active within a few seconds. Refresh this page if it hasn't updated yet.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif (($_GET['stripe'] ?? '') === 'cancelled'): ?>
        <div class="alert alert-warning alert-dismissible fade show">
            Checkout was cancelled — no payment was made. Feel free to try again below.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4 g-lg-5">
        <div class="col-lg-7">
            <h1 class="fw-bold mb-3"><?= e($bundle['title']) ?></h1>
            <?php if (!empty($bundle['description'])): ?>
                <p class="text-secondary"><?= nl2br(e($bundle['description'])) ?></p>
            <?php endif; ?>

            <h2 class="h5 fw-bold mt-4 mb-3">What's Included (<?= count($bundleResources) ?>)</h2>
            <?php if (empty($bundleResources)): ?>
                <p class="text-secondary">Resources are being finalized for this bundle &mdash; check back soon.</p>
            <?php else: ?>
                <ul class="list-group">
                    <?php foreach ($bundleResources as $bundleResource): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <a href="<?= e(base_url('resource.php?slug=' . urlencode($bundleResource['slug']))) ?>" class="text-decoration-none">
                                <?= e($bundleResource['title']) ?>
                            </a>
                            <span class="badge bg-light text-dark border"><?= e($bundleResource['resource_type']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4 text-center">
                    <p class="h3 fw-bold mb-1"><?= e(format_currency($bundle['price'])) ?></p>
                    <p class="text-secondary small mb-4">One-time payment &mdash; no subscription, no expiry.</p>

                    <?php if ($alreadyOwned): ?>
                        <p class="alert alert-success mb-0"><i class="fa-solid fa-circle-check me-1"></i>You own this bundle. Every resource above is unlocked for you.</p>
                    <?php elseif (!STRIPE_ENABLED): ?>
                        <p class="alert alert-warning mb-0">Purchases are temporarily unavailable. Please contact <a href="mailto:<?= e(CONTACT_EMAIL) ?>"><?= e(CONTACT_EMAIL) ?></a>.</p>
                    <?php elseif ($isLoggedIn): ?>
                        <form method="post" action="<?= e(base_url('member/bundle-checkout.php')) ?>">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="bundle_id" value="<?= (int)$bundle['id'] ?>">
                            <button type="submit" class="btn btn-primary btn-lg w-100">Buy with Stripe</button>
                        </form>
                        <p class="text-secondary small mt-2 mb-0">Pay by card or scan to pay with PromptPay.</p>
                    <?php else: ?>
                        <a href="<?= e(base_url('login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']))) ?>" class="btn btn-primary btn-lg w-100">Login to Buy</a>
                        <p class="text-secondary small mt-2 mb-0">Don't have an account? <a href="<?= e(base_url('register.php')) ?>">Register free</a>.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
