<?php
/**
 * Renders one bundle card. Expects $bundle (assoc array from
 * get_published_bundles()/get_featured_bundles(), which both include a live
 * resource_count) to be set before this file is included.
 */
$cardBundleCoverUrl = bundle_cover_image_url($bundle);
$cardBundleHasSavings = !empty($bundle['original_price']) && (float)$bundle['original_price'] > (float)$bundle['price'];
$cardBundleSavingsPct = $cardBundleHasSavings
    ? (int)round((((float)$bundle['original_price'] - (float)$bundle['price']) / (float)$bundle['original_price']) * 100)
    : 0;
$bundleUrl = base_url('bundle.php?slug=' . urlencode($bundle['slug']));
?>
<div class="col">
    <article class="card bundle-card h-100">
        <a href="<?= e($bundleUrl) ?>" class="card-media bundle-card-thumb d-flex align-items-center justify-content-center" tabindex="-1" aria-hidden="true">
            <?php if ($cardBundleCoverUrl): ?>
                <img src="<?= e($cardBundleCoverUrl) ?>" class="card-img-top" alt="" loading="lazy" width="400" height="300">
            <?php else: ?>
                <i class="fa-solid fa-box-open fa-3x text-white"></i>
            <?php endif; ?>
            <?php if ($cardBundleHasSavings): ?>
                <span class="badge badge-members position-absolute top-0 end-0 m-2">Save <?= $cardBundleSavingsPct ?>%</span>
            <?php endif; ?>
        </a>
        <div class="card-body d-flex flex-column">
            <p class="card-meta mb-2">
                <i class="fa-solid fa-layer-group me-1" aria-hidden="true"></i><?= (int)$bundle['resource_count'] ?> resource<?= (int)$bundle['resource_count'] === 1 ? '' : 's' ?> included
            </p>
            <h3 class="h6 mb-1">
                <a class="card-title-link" href="<?= e($bundleUrl) ?>"><?= e($bundle['title']) ?></a>
            </h3>
            <?php if (!empty($bundle['description'])): ?>
                <p class="small text-secondary flex-grow-1"><?= e(truncate_text($bundle['description'], 100)) ?></p>
            <?php else: ?>
                <div class="flex-grow-1"></div>
            <?php endif; ?>
            <div class="d-flex align-items-end justify-content-between gap-2 mt-2">
                <p class="mb-0">
                    <span class="bundle-price"><?= e(format_currency($bundle['price'])) ?></span>
                    <span class="text-secondary small">one-time</span>
                    <?php if ($cardBundleHasSavings): ?>
                        <span class="text-secondary small text-decoration-line-through ms-1"><?= e(format_currency($bundle['original_price'])) ?></span>
                    <?php endif; ?>
                </p>
                <a href="<?= e($bundleUrl) ?>" class="btn btn-primary btn-sm">
                    View<span class="visually-hidden"> <?= e($bundle['title']) ?></span>
                </a>
            </div>
        </div>
    </article>
</div>
