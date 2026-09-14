<?php
/**
 * Renders one bundle card. Expects $bundle (assoc array from
 * get_published_bundles()/get_featured_bundle(), which both include a live
 * resource_count) to be set before this file is included.
 */
$cardBundleCoverUrl = bundle_cover_image_url($bundle);
$cardBundleHasSavings = !empty($bundle['original_price']) && (float)$bundle['original_price'] > (float)$bundle['price'];
$cardBundleSavingsPct = $cardBundleHasSavings
    ? (int)round((((float)$bundle['original_price'] - (float)$bundle['price']) / (float)$bundle['original_price']) * 100)
    : 0;
?>
<div class="col">
    <div class="card bundle-card shadow-sm h-100">
        <a href="<?= e(base_url('bundle.php?slug=' . urlencode($bundle['slug']))) ?>" class="text-decoration-none text-reset">
            <div class="bundle-card-thumb position-relative d-flex align-items-center justify-content-center">
                <?php if ($cardBundleCoverUrl): ?>
                    <img src="<?= e($cardBundleCoverUrl) ?>" class="card-img-top" alt="<?= e($bundle['title']) ?>" loading="lazy">
                <?php else: ?>
                    <i class="fa-solid fa-box-open fa-3x text-white"></i>
                <?php endif; ?>
                <?php if ($cardBundleHasSavings): ?>
                    <span class="badge bg-danger position-absolute top-0 end-0 m-2">Save <?= $cardBundleSavingsPct ?>%</span>
                <?php endif; ?>
            </div>
        </a>
        <div class="card-body d-flex flex-column">
            <p class="small text-secondary mb-2">
                <i class="fa-solid fa-layer-group me-1"></i><?= (int)$bundle['resource_count'] ?> resource<?= (int)$bundle['resource_count'] === 1 ? '' : 's' ?> included
            </p>
            <h3 class="h6 fw-bold mb-1">
                <a class="text-decoration-none text-reset" href="<?= e(base_url('bundle.php?slug=' . urlencode($bundle['slug']))) ?>">
                    <?= e($bundle['title']) ?>
                </a>
            </h3>
            <?php if (!empty($bundle['description'])): ?>
                <p class="small text-secondary flex-grow-1"><?= e(truncate_text($bundle['description'], 100)) ?></p>
            <?php else: ?>
                <div class="flex-grow-1"></div>
            <?php endif; ?>
            <p class="mb-2">
                <span class="h5 fw-bold mb-0"><?= e(format_currency($bundle['price'])) ?></span>
                <span class="text-secondary small">one-time</span>
                <?php if ($cardBundleHasSavings): ?>
                    <span class="text-secondary small text-decoration-line-through ms-1"><?= e(format_currency($bundle['original_price'])) ?></span>
                <?php endif; ?>
            </p>
            <a href="<?= e(base_url('bundle.php?slug=' . urlencode($bundle['slug']))) ?>" class="btn btn-primary btn-sm mt-auto">
                View Bundle
            </a>
        </div>
    </div>
</div>
