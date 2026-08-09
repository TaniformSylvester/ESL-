<?php
/**
 * Renders one resource card. Expects $resource (assoc array from the
 * resources table, optionally joined with category_name) to be set
 * before this file is included.
 */
$cardIcon = resource_type_icon($resource['resource_type']);
$cardThumbUrl = !empty($resource['thumbnail']) ? UPLOAD_THUMBNAIL_URL . '/' . rawurlencode($resource['thumbnail']) : null;
?>
<div class="col">
    <div class="card resource-card shadow-sm">
        <a href="<?= e(base_url('resource.php?slug=' . urlencode($resource['slug']))) ?>" class="text-decoration-none text-reset">
            <?php if ($cardThumbUrl): ?>
                <img src="<?= e($cardThumbUrl) ?>" class="card-img-top" alt="<?= e($resource['title']) ?>" loading="lazy">
            <?php else: ?>
                <div class="card-img-top d-flex align-items-center justify-content-center bg-light text-primary">
                    <i class="fa-solid <?= e($cardIcon) ?> fa-3x"></i>
                </div>
            <?php endif; ?>
        </a>
        <div class="card-body d-flex flex-column">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="badge <?= $resource['is_free'] ? 'badge-free' : 'badge-members' ?>">
                    <?= $resource['is_free'] ? 'Free' : '<i class="fa-solid fa-lock me-1"></i>Members' ?>
                </span>
                <span class="badge bg-light text-dark border"><?= e($resource['grade_level'] ?? '') ?></span>
            </div>
            <h3 class="h6 fw-bold mb-1">
                <a class="text-decoration-none text-reset" href="<?= e(base_url('resource.php?slug=' . urlencode($resource['slug']))) ?>">
                    <?= e($resource['title']) ?>
                </a>
            </h3>
            <p class="small text-secondary mb-2"><?= e($resource['resource_type']) ?><?= !empty($resource['topic']) ? ' &middot; ' . e($resource['topic']) : '' ?></p>
            <p class="small text-secondary flex-grow-1"><?= e(truncate_text($resource['description'] ?? '', 90)) ?></p>
            <a href="<?= e(base_url('resource.php?slug=' . urlencode($resource['slug']))) ?>" class="btn btn-outline-primary btn-sm mt-2">
                View Resource
            </a>
        </div>
    </div>
</div>
