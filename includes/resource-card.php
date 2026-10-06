<?php
/**
 * Renders one resource card. Expects $resource (assoc array from the
 * resources table, optionally joined with category_name/subject_name) to be
 * set before this file is included. Requires auth.php and
 * favorites-functions.php to already be loaded by the including page.
 * The .resource-card data-* attributes feed assets/js/analytics.js.
 */
$cardIcon = resource_type_icon($resource['resource_type']);
$cardThumbUrl = !empty($resource['thumbnail']) ? UPLOAD_THUMBNAIL_URL . '/' . rawurlencode($resource['thumbnail']) : null;
$cardIsLoggedIn = is_logged_in();
$cardIsFavorited = $cardIsLoggedIn && is_favorited((int)$_SESSION['user_id'], (int)$resource['id']);
$cardUrl = base_url('resource.php?slug=' . urlencode($resource['slug']));
$cardSubject = $resource['subject_name'] ?? '';
$cardTone = $cardSubject !== '' ? subject_key($cardSubject) : 'esl';
?>
<div class="col">
    <article class="card resource-card tone-<?= e($cardTone) ?>"
         data-resource-id="<?= (int)$resource['id'] ?>"
         data-resource-title="<?= e($resource['title']) ?>"
         data-subject="<?= e($cardSubject) ?>"
         data-grade="<?= e($resource['grade_level'] ?? '') ?>">
        <div class="position-relative">
            <a href="<?= e($cardUrl) ?>" class="card-media" tabindex="-1" aria-hidden="true">
                <?php if ($cardThumbUrl): ?>
                    <img src="<?= e($cardThumbUrl) ?>" class="card-img-top" alt="" loading="lazy" width="400" height="300">
                <?php else: ?>
                    <span class="card-media-placeholder"><i class="fa-solid <?= e($cardIcon) ?>"></i></span>
                <?php endif; ?>
            </a>
            <div class="card-media-badges">
                <?php if ($cardSubject !== ''): ?>
                    <span class="badge badge-subject-<?= e($cardTone) ?>"><?= e($cardSubject) ?></span>
                <?php endif; ?>
                <span class="badge <?= $resource['is_free'] ? 'badge-free' : 'badge-members' ?>">
                    <?= $resource['is_free'] ? 'Free' : '<i class="fa-solid fa-lock me-1" aria-hidden="true"></i>Members' ?>
                </span>
            </div>
            <?php if ($cardIsLoggedIn): ?>
                <button type="button" class="btn btn-sm favorite-btn position-absolute top-0 end-0 m-2 <?= $cardIsFavorited ? 'active' : '' ?>"
                        data-resource-id="<?= (int)$resource['id'] ?>" data-csrf="<?= e(generate_csrf_token()) ?>"
                        aria-pressed="<?= $cardIsFavorited ? 'true' : 'false' ?>"
                        aria-label="<?= $cardIsFavorited ? 'Remove from favorites' : 'Add to favorites' ?>"
                        title="<?= $cardIsFavorited ? 'Remove from favorites' : 'Add to favorites' ?>">
                    <i class="fa-<?= $cardIsFavorited ? 'solid' : 'regular' ?> fa-heart <?= $cardIsFavorited ? 'text-danger' : '' ?>"></i>
                </button>
            <?php endif; ?>
        </div>
        <div class="card-body d-flex flex-column">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                <?php if (!empty($resource['grade_level'])): ?>
                    <span class="badge badge-grade"><?= e($resource['grade_level']) ?></span>
                <?php endif; ?>
                <span class="card-meta"><i class="fa-solid <?= e($cardIcon) ?> me-1" aria-hidden="true"></i><?= e($resource['resource_type']) ?></span>
            </div>
            <h3 class="h6 mb-1">
                <a class="card-title-link" href="<?= e($cardUrl) ?>"><?= e($resource['title']) ?></a>
            </h3>
            <?php if (!empty($resource['topic'])): ?>
                <p class="card-meta mb-2"><?= e($resource['topic']) ?></p>
            <?php endif; ?>
            <?php if (!empty($resource['avg_rating'])): ?>
                <p class="small mb-2">
                    <i class="fa-solid fa-star review-stars" aria-hidden="true"></i> <span class="fw-bold"><?= e(number_format((float)$resource['avg_rating'], 1)) ?></span>
                    <span class="text-secondary">(<?= (int)$resource['review_count'] ?> review<?= (int)$resource['review_count'] === 1 ? '' : 's' ?>)</span>
                </p>
            <?php endif; ?>
            <p class="small text-secondary flex-grow-1 mb-3"><?= e(truncate_text($resource['description'] ?? '', 90)) ?></p>
            <a href="<?= e($cardUrl) ?>" class="link-arrow">
                View Resource <i class="fa-solid fa-arrow-right" aria-hidden="true"></i><span class="visually-hidden">: <?= e($resource['title']) ?></span>
            </a>
        </div>
    </article>
</div>
