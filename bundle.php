<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bundle-functions.php';
require_once __DIR__ . '/includes/resource-functions.php';
require_once __DIR__ . '/includes/favorites-functions.php';
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
$bundleResources = attach_rating_summaries(get_bundle_resources((int)$bundle['id']));

// The preview gallery: the cover image (if any) always comes first — it's
// the fallback "at least something to show" image — followed by the
// dedicated gallery images an admin uploaded for browsing before purchase.
$galleryImages = [];
$bundleCoverUrl = bundle_cover_image_url($bundle);
if ($bundleCoverUrl) {
    $galleryImages[] = $bundleCoverUrl;
}
foreach (get_bundle_gallery_images((int)$bundle['id']) as $galleryImage) {
    $galleryImages[] = UPLOAD_BUNDLE_URL . '/' . rawurlencode($galleryImage['image']);
}

$bundleHasSavings = !empty($bundle['original_price']) && (float)$bundle['original_price'] > (float)$bundle['price'];
$bundleSavingsPct = $bundleHasSavings
    ? (int)round((((float)$bundle['original_price'] - (float)$bundle['price']) / (float)$bundle['original_price']) * 100)
    : 0;

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

    <div class="bundle-gallery" id="bundleGallery">
        <?php if (count($galleryImages) > 1): ?>
            <div class="bundle-gallery-thumbs" role="listbox" aria-label="<?= e($bundle['title']) ?> preview images">
                <?php foreach ($galleryImages as $imgIndex => $imgUrl): ?>
                    <button type="button" class="bundle-gallery-thumb<?= $imgIndex === 0 ? ' active' : '' ?>"
                            data-full="<?= e($imgUrl) ?>" role="option" aria-selected="<?= $imgIndex === 0 ? 'true' : 'false' ?>"
                            aria-label="View preview <?= $imgIndex + 1 ?> of <?= count($galleryImages) ?>">
                        <img src="<?= e($imgUrl) ?>" alt="Preview <?= $imgIndex + 1 ?> of <?= e($bundle['title']) ?>" loading="lazy">
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="bundle-gallery-main">
            <div class="bundle-gallery-main-frame">
                <?php if (!empty($galleryImages)): ?>
                    <img src="<?= e($galleryImages[0]) ?>" alt="<?= e($bundle['title']) ?> preview" id="bundleGalleryMainImage">
                <?php else: ?>
                    <i class="fa-solid fa-box-open fa-4x text-secondary"></i>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mt-3 mb-5">
        <?php if (!empty($galleryImages)): ?>
            <button type="button" class="btn btn-outline-primary" id="bundleViewPreviewBtn">
                <i class="fa-regular fa-eye me-1"></i>View Preview
            </button>
        <?php else: ?>
            <span></span>
        <?php endif; ?>
        <div class="d-flex align-items-center gap-2 small text-secondary">
            <span>Share:</span>
            <a href="https://www.facebook.com/sharer/sharer.php?u=<?= rawurlencode(base_url('bundle.php?slug=' . $bundle['slug'])) ?>"
               target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary" title="Share on Facebook"><i class="fa-brands fa-facebook-f"></i></a>
            <a href="https://social-plugins.line.me/lineit/share?url=<?= rawurlencode(base_url('bundle.php?slug=' . $bundle['slug'])) ?>"
               target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary" title="Share on LINE"><i class="fa-brands fa-line"></i></a>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="copyBundleLink" title="Copy Link" data-url="<?= e(base_url('bundle.php?slug=' . rawurlencode($bundle['slug']))) ?>">
                <i class="fa-solid fa-link"></i>
            </button>
        </div>
    </div>

    <?php if (count($galleryImages) > 0): ?>
    <div class="bundle-lightbox" id="bundleLightbox" hidden role="dialog" aria-modal="true" aria-label="<?= e($bundle['title']) ?> preview">
        <div class="bundle-lightbox-figure">
            <img src="" alt="<?= e($bundle['title']) ?> preview, large view" class="bundle-lightbox-image" id="bundleLightboxImage">
            <?php if (count($galleryImages) > 1): ?>
                <button type="button" class="bundle-lightbox-nav bundle-lightbox-nav-prev" id="bundleLightboxPrev" aria-label="Previous image"><i class="fa-solid fa-chevron-left"></i></button>
                <button type="button" class="bundle-lightbox-nav bundle-lightbox-nav-next" id="bundleLightboxNext" aria-label="Next image"><i class="fa-solid fa-chevron-right"></i></button>
            <?php endif; ?>
            <button type="button" class="bundle-lightbox-close" id="bundleLightboxClose" aria-label="Close preview"><i class="fa-solid fa-xmark"></i></button>
        </div>
    </div>
    <?php endif; ?>

    <div class="row g-4 g-lg-5">
        <div class="col-lg-7">
            <h1 class="fw-bold mb-3"><?= e($bundle['title']) ?></h1>
            <?php if (!empty($bundle['description'])): ?>
                <p class="text-secondary"><?= nl2br(e($bundle['description'])) ?></p>
            <?php endif; ?>
            <p class="text-secondary mb-0">
                <i class="fa-solid fa-layer-group me-1"></i>
                <?= count($bundleResources) ?> resource<?= count($bundleResources) === 1 ? '' : 's' ?> included
            </p>
        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4 text-center">
                    <p class="h3 fw-bold mb-1">
                        <?= e(format_currency($bundle['price'])) ?>
                        <?php if ($bundleHasSavings): ?>
                            <span class="fs-6 fw-normal text-secondary text-decoration-line-through"><?= e(format_currency($bundle['original_price'])) ?></span>
                        <?php endif; ?>
                    </p>
                    <?php if ($bundleHasSavings): ?>
                        <p class="mb-2"><span class="badge bg-danger">Save <?= $bundleSavingsPct ?>%</span></p>
                    <?php endif; ?>
                    <p class="text-secondary small mb-4">One-time payment &mdash; no subscription, no expiry.</p>

                    <?php if ($alreadyOwned): ?>
                        <p class="alert alert-success mb-0"><i class="fa-solid fa-circle-check me-1"></i>You own this bundle. Every resource below is unlocked for you.</p>
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

    <div class="mt-5">
        <h2 class="h5 fw-bold mb-3">What's Included (<?= count($bundleResources) ?>)</h2>
        <?php if (empty($bundleResources)): ?>
            <p class="text-secondary">Resources are being finalized for this bundle &mdash; check back soon.</p>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-4 g-4">
                <?php foreach ($bundleResources as $resource): ?>
                    <?php include __DIR__ . '/includes/resource-card.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
