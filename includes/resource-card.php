<?php
/**
 * Renders one resource card. Expects $resource (assoc array from the
 * resources table, optionally joined with category_name) to be set
 * before this file is included. Requires auth.php and favorites-functions.php
 * to already be loaded by the including page.
 */
$cardIcon = resource_type_icon($resource['resource_type']);
$cardThumbUrl = !empty($resource['thumbnail']) ? UPLOAD_THUMBNAIL_URL . '/' . rawurlencode($resource['thumbnail']) : null;
$cardIsLoggedIn = is_logged_in();
$cardIsFavorited = $cardIsLoggedIn && is_favorited((int)$_SESSION['user_id'], (int)$resource['id']);
?>
<div class="col">
    <div class="card resource-card shadow-sm"
         data-resource-id="<?= (int)$resource['id'] ?>"
         data-resource-title="<?= e($resource['title']) ?>"
         data-subject="<?= e($resource['subject_name'] ?? '') ?>"
         data-grade="<?= e($resource['grade_level'] ?? '') ?>">
        <div class="position-relative">
            <a href="<?= e(base_url('resource.php?slug=' . urlencode($resource['slug']))) ?>" class="text-decoration-none text-reset">
                <?php if ($cardThumbUrl): ?>
                    <img src="<?= e($cardThumbUrl) ?>" class="card-img-top" alt="<?= e($resource['title']) ?>" loading="lazy">
                <?php else: ?>
                    <div class="card-img-top d-flex align-items-center justify-content-center bg-light text-primary">
                        <i class="fa-solid <?= e($cardIcon) ?> fa-3x"></i>
                    </div>
                <?php endif; ?>
            </a>
            <?php if ($cardIsLoggedIn): ?>
                <button type="button" class="btn btn-sm btn-light favorite-btn position-absolute top-0 end-0 m-2 <?= $cardIsFavorited ? 'active' : '' ?>"
                        data-resource-id="<?= (int)$resource['id'] ?>" data-csrf="<?= e(generate_csrf_token()) ?>"
                        aria-pressed="<?= $cardIsFavorited ? 'true' : 'false' ?>"
                        title="<?= $cardIsFavorited ? 'Remove from favorites' : 'Add to favorites' ?>">
                    <i class="fa-<?= $cardIsFavorited ? 'solid' : 'regular' ?> fa-heart <?= $cardIsFavorited ? 'text-danger' : '' ?>"></i>
                </button>
            <?php endif; ?>
        </div>
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
            <?php if (!empty($resource['avg_rating'])): ?>
                <p class="small text-warning mb-2">
                    <i class="fa-solid fa-star"></i> <span class="text-dark fw-bold"><?= e(number_format((float)$resource['avg_rating'], 1)) ?></span>
                    <span class="text-secondary">(<?= (int)$resource['review_count'] ?> review<?= (int)$resource['review_count'] === 1 ? '' : 's' ?>)</span>
                </p>
            <?php endif; ?>
            <p class="small text-secondary flex-grow-1"><?= e(truncate_text($resource['description'] ?? '', 90)) ?></p>
            <a href="<?= e(base_url('resource.php?slug=' . urlencode($resource['slug']))) ?>" class="btn btn-outline-primary btn-sm mt-2">
                View Resource
            </a>
        </div>
    </div>
</div>
