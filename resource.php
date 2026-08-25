<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/membership.php';
require_once __DIR__ . '/includes/resource-functions.php';
require_once __DIR__ . '/includes/download-functions.php';
require_once __DIR__ . '/includes/favorites-functions.php';
require_once __DIR__ . '/includes/review-functions.php';
require_once __DIR__ . '/includes/seo-functions.php';

$slug = trim((string)($_GET['slug'] ?? ''));
$resource = $slug !== '' ? get_resource_by_slug($slug) : null;

if (!$resource) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$reviewErrors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_review') {
    require_csrf();

    if (!is_logged_in()) {
        redirect('login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    }

    if (!can_review_resource((int)$_SESSION['user_id'], (int)$resource['id'])) {
        flash_set('error', 'You can review a resource after you\'ve downloaded it.');
        redirect('resource.php?slug=' . urlencode($resource['slug']) . '#reviews');
    }

    $result = submit_review((int)$_SESSION['user_id'], (int)$resource['id'], $_POST);

    if ($result['success']) {
        flash_set('success', 'Thanks! Your review has been submitted and will appear once approved.');
        redirect('resource.php?slug=' . urlencode($resource['slug']) . '#reviews');
    }

    $reviewErrors = $result['errors'];
}

$isLoggedIn = is_logged_in();
$canDownload = can_download_resource($resource);
$isPro = $isLoggedIn && isMemberActive();
$freeUsage = ($isLoggedIn && !$isPro && !is_admin()) ? get_free_download_usage((int)$_SESSION['user_id']) : null;
$isFavorited = $isLoggedIn && is_favorited((int)$_SESSION['user_id'], (int)$resource['id']);
$previewUrl = !empty($resource['preview_image']) ? UPLOAD_PREVIEW_URL . '/' . rawurlencode($resource['preview_image']) : null;
$thumbUrl = !empty($resource['thumbnail']) ? UPLOAD_THUMBNAIL_URL . '/' . rawurlencode($resource['thumbnail']) : null;
$displayImage = $previewUrl ?? $thumbUrl;

$ratingSummary = get_resource_rating_summary((int)$resource['id']);
$resourceReviews = get_resource_reviews((int)$resource['id']);
$myReview = $isLoggedIn ? get_user_review((int)$_SESSION['user_id'], (int)$resource['id']) : null;
$canWriteReview = $isLoggedIn && can_review_resource((int)$_SESSION['user_id'], (int)$resource['id']);
$myHelpfulVotes = [];
if ($isLoggedIn) {
    foreach ($resourceReviews as $r) {
        if (has_marked_helpful((int)$_SESSION['user_id'], (int)$r['id'])) {
            $myHelpfulVotes[(int)$r['id']] = true;
        }
    }
}

$relatedResources = get_related_resources($resource, 6);

$pageTitle = generate_resource_seo_title($resource);
$pageDescription = generate_resource_seo_description($resource);
$pageImage = $displayImage ?? asset_url('images/og-image-icon.png');

$breadcrumbItems = [['name' => 'Resources', 'url' => base_url('resources.php')]];
if (!empty($resource['subject_name'])) {
    $breadcrumbItems[] = ['name' => $resource['subject_name'], 'url' => base_url('resources.php?subject_id=' . (int)$resource['subject_id'])];
}
if (!empty($resource['category_name'])) {
    $breadcrumbItems[] = ['name' => $resource['category_name'], 'url' => base_url('resources.php?category_id=' . (int)$resource['category_id'])];
}
$breadcrumbItems[] = ['name' => $resource['title'], 'url' => base_url('resource.php?slug=' . rawurlencode($resource['slug']))];

$breadcrumbSchema = [
    '@context'        => 'https://schema.org',
    '@type'           => 'BreadcrumbList',
    'itemListElement' => array_map(static function (array $item, int $index): array {
        return ['@type' => 'ListItem', 'position' => $index + 1, 'name' => $item['name'], 'item' => $item['url']];
    }, $breadcrumbItems, array_keys($breadcrumbItems)),
];

$resourceSchema = [
    '@context'              => 'https://schema.org',
    '@type'                 => 'LearningResource',
    'name'                  => $resource['title'],
    'description'           => $resource['description'] ?? $pageDescription,
    'learningResourceType'  => $resource['resource_type'],
    'isAccessibleForFree'   => (bool)$resource['is_free'],
    'url'                   => base_url('resource.php?slug=' . rawurlencode($resource['slug'])),
    'datePublished'         => date('c', strtotime($resource['created_at'])),
    'dateModified'          => date('c', strtotime($resource['updated_at'])),
    'publisher'             => ['@type' => 'Organization', 'name' => SITE_NAME, 'url' => base_url()],
];
if (!empty($resource['grade_level'])) {
    $resourceSchema['educationalLevel'] = $resource['grade_level'];
}
if (!empty($resource['subject_name'])) {
    $resourceSchema['about'] = $resource['subject_name'];
}
if (!empty($displayImage)) {
    $resourceSchema['image'] = $displayImage;
}
if ($ratingSummary['total'] > 0) {
    $resourceSchema['aggregateRating'] = [
        '@type'       => 'AggregateRating',
        'ratingValue' => $ratingSummary['average'],
        'reviewCount' => $ratingSummary['total'],
        'bestRating'  => 5,
        'worstRating' => 1,
    ];
    $resourceSchema['review'] = array_map(static function (array $review): array {
        return [
            '@type'        => 'Review',
            'reviewRating' => ['@type' => 'Rating', 'ratingValue' => (int)$review['rating'], 'bestRating' => 5, 'worstRating' => 1],
            'author'       => ['@type' => 'Person', 'name' => $review['first_name']],
            'datePublished' => date('c', strtotime($review['created_at'])),
            'reviewBody'   => $review['review_text'] ?? '',
        ];
    }, $resourceReviews);
}

require_once __DIR__ . '/includes/header.php';
?>
<script type="application/ld+json"><?= json_encode($resourceSchema, JSON_UNESCAPED_SLASHES) ?></script>
<script type="application/ld+json"><?= json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES) ?></script>
<div class="container py-5">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="<?= e(base_url('resources.php')) ?>">Resources</a></li>
            <?php if (!empty($resource['subject_name'])): ?>
                <li class="breadcrumb-item"><a href="<?= e(base_url('resources.php?subject_id=' . (int)$resource['subject_id'])) ?>"><?= e($resource['subject_name']) ?></a></li>
            <?php endif; ?>
            <?php if (!empty($resource['category_name'])): ?>
                <li class="breadcrumb-item"><?= e($resource['category_name']) ?></li>
            <?php endif; ?>
            <li class="breadcrumb-item active" aria-current="page"><?= e($resource['title']) ?></li>
        </ol>
    </nav>

    <div class="row g-4 g-lg-5">
        <div class="col-lg-6">
            <?php if ($displayImage): ?>
                <img src="<?= e($displayImage) ?>" alt="<?= e($resource['title']) ?>" class="img-fluid rounded shadow-sm">
            <?php else: ?>
                <div class="bg-light rounded d-flex align-items-center justify-content-center text-primary" style="aspect-ratio: 4/3;">
                    <i class="fa-solid <?= e(resource_type_icon($resource['resource_type'])) ?> fa-5x"></i>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-6">
            <div class="mb-2">
                <span class="badge <?= $resource['is_free'] ? 'badge-free' : 'badge-members' ?>">
                    <?= $resource['is_free'] ? 'Free' : '<i class="fa-solid fa-lock me-1"></i>Members Only' ?>
                </span>
                <?php if (!empty($resource['subject_name'])): ?>
                    <span class="badge bg-light text-dark border"><?= e($resource['subject_name']) ?></span>
                <?php endif; ?>
                <span class="badge bg-light text-dark border"><?= e($resource['resource_type']) ?></span>
                <?php if (!empty($resource['grade_level'])): ?>
                    <span class="badge bg-light text-dark border"><?= e($resource['grade_level']) ?></span>
                <?php endif; ?>
            </div>

            <?php if ($ratingSummary['total'] > 0): ?>
                <a href="#reviews" class="text-decoration-none text-reset d-inline-flex align-items-center gap-1 mb-2 small">
                    <span class="text-warning"><i class="fa-solid fa-star"></i></span>
                    <strong><?= e(number_format($ratingSummary['average'], 1)) ?></strong>
                    <span class="text-secondary">(<?= (int)$ratingSummary['total'] ?> teacher review<?= $ratingSummary['total'] === 1 ? '' : 's' ?>)</span>
                </a>
            <?php else: ?>
                <a href="#reviews" class="text-decoration-none text-secondary d-inline-flex align-items-center gap-1 mb-2 small">
                    <i class="fa-regular fa-star"></i> No ratings yet
                </a>
            <?php endif; ?>

            <div class="d-flex align-items-start justify-content-between gap-2">
                <h1 class="fw-bold mb-3"><?= e($resource['title']) ?></h1>
                <?php if ($isLoggedIn): ?>
                    <button type="button" class="btn btn-outline-secondary favorite-btn flex-shrink-0 <?= $isFavorited ? 'active' : '' ?>"
                            data-resource-id="<?= (int)$resource['id'] ?>" data-csrf="<?= e(generate_csrf_token()) ?>"
                            aria-pressed="<?= $isFavorited ? 'true' : 'false' ?>"
                            title="<?= $isFavorited ? 'Remove from favorites' : 'Add to favorites' ?>">
                        <i class="fa-<?= $isFavorited ? 'solid' : 'regular' ?> fa-heart <?= $isFavorited ? 'text-danger' : '' ?>"></i>
                    </button>
                <?php endif; ?>
            </div>

            <?php if (!empty($resource['description'])): ?>
                <h2 class="h6 fw-bold text-secondary text-uppercase mt-4 mb-2">About This Resource</h2>
                <p class="text-secondary"><?= nl2br(e($resource['description'])) ?></p>
            <?php endif; ?>

            <h2 class="h6 fw-bold text-secondary text-uppercase mt-4 mb-2">What's Included</h2>
            <dl class="row small mt-4">
                <?php if (!empty($resource['subject_name'])): ?>
                    <dt class="col-4">Subject</dt><dd class="col-8"><?= e($resource['subject_name']) ?></dd>
                <?php endif; ?>
                <?php if (!empty($resource['topic'])): ?>
                    <dt class="col-4">Topic</dt><dd class="col-8"><?= e($resource['topic']) ?></dd>
                <?php endif; ?>
                <?php if (!empty($resource['category_name'])): ?>
                    <dt class="col-4">Category</dt><dd class="col-8"><?= e($resource['category_name']) ?></dd>
                <?php endif; ?>
                <?php if (!empty($resource['file_type'])): ?>
                    <dt class="col-4">File Type</dt><dd class="col-8"><?= e(strtoupper($resource['file_type'])) ?></dd>
                <?php endif; ?>
                <?php if (!empty($resource['file_size'])): ?>
                    <dt class="col-4">File Size</dt><dd class="col-8"><?= e(format_file_size((int)$resource['file_size'])) ?></dd>
                <?php endif; ?>
                <dt class="col-4">Published</dt><dd class="col-8"><?= e(format_date($resource['created_at'])) ?></dd>
                <dt class="col-4">Downloads</dt><dd class="col-8"><?= (int)$resource['download_count'] ?></dd>
            </dl>

            <div class="mt-4">
                <?php if ($canDownload): ?>
                    <a href="<?= e(base_url('member/download.php?id=' . (int)$resource['id'])) ?>" class="btn btn-primary btn-lg px-4">
                        <i class="fa-solid fa-download me-2"></i>Download
                    </a>
                    <?php if ($freeUsage !== null && $resource['is_free']): ?>
                        <p class="small text-secondary mt-2 mb-0">
                            <?= e(free_download_usage_message($freeUsage)) ?>
                            <a href="<?= e(base_url('pricing.php')) ?>">Upgrade to Pro for unlimited downloads.</a>
                        </p>
                    <?php endif; ?>
                <?php elseif (!$resource['is_free']): ?>
                    <div class="alert alert-warning">
                        <p class="fw-bold mb-1"><i class="fa-solid fa-lock me-1"></i> Members Only</p>
                        <p class="mb-3">Upgrade to Teacher Pro (from <?= format_currency(PRICE_MONTHLY) ?>/month) to download this resource.</p>
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="<?= e(base_url('pricing.php')) ?>" class="btn btn-primary">Upgrade to Pro</a>
                            <?php if (!$isLoggedIn): ?>
                                <a href="<?= e(base_url('login.php')) ?>" class="btn btn-outline-secondary">Login</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php elseif (!$isLoggedIn): ?>
                    <div class="alert alert-info">
                        <p class="fw-bold mb-1"><i class="fa-solid fa-circle-info me-1"></i> Create a free account to download</p>
                        <p class="mb-3">Free accounts get up to <?= (int)FREE_DOWNLOAD_MONTHLY_LIMIT ?> downloads every month, no payment required.</p>
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="<?= e(base_url('register.php')) ?>" class="btn btn-primary">Create Free Account</a>
                            <a href="<?= e(base_url('login.php')) ?>" class="btn btn-outline-secondary">Login</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <p class="fw-bold mb-1"><i class="fa-solid fa-lock me-1"></i> <?= e(free_download_usage_message($freeUsage)) ?></p>
                        <p class="mb-3">Upgrade to Teacher Pro for unlimited downloads, or wait until next month for your free downloads to reset.</p>
                        <a href="<?= e(base_url('pricing.php')) ?>" class="btn btn-primary">Upgrade to Pro</a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="mt-3 d-flex align-items-center gap-2 small text-secondary">
                <span>Share:</span>
                <a href="https://www.facebook.com/sharer/sharer.php?u=<?= rawurlencode(base_url('resource.php?slug=' . $resource['slug'])) ?>"
                   target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary" title="Share on Facebook"><i class="fa-brands fa-facebook-f"></i></a>
                <a href="https://social-plugins.line.me/lineit/share?url=<?= rawurlencode(base_url('resource.php?slug=' . $resource['slug'])) ?>"
                   target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary" title="Share on LINE"><i class="fa-brands fa-line"></i></a>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="copyResourceLink" title="Copy Link" data-url="<?= e(base_url('resource.php?slug=' . rawurlencode($resource['slug']))) ?>">
                    <i class="fa-solid fa-link"></i>
                </button>
            </div>
        </div>
    </div>

    <hr class="my-5">

    <div id="reviews" class="row">
        <div class="col-lg-8 mx-auto">
            <h2 class="h4 fw-bold mb-3">Teacher Reviews</h2>

            <?php if ($ratingSummary['total'] > 0): ?>
                <div class="d-flex flex-wrap align-items-center gap-4 mb-4">
                    <div class="text-center">
                        <div class="display-5 fw-bold text-primary mb-0"><?= e(number_format($ratingSummary['average'], 1)) ?></div>
                        <div class="text-warning">
                            <?php $roundedAvg = (int)round($ratingSummary['average']); ?>
                            <?php for ($s = 1; $s <= 5; $s++): ?><i class="fa-<?= $s <= $roundedAvg ? 'solid' : 'regular' ?> fa-star"></i><?php endfor; ?>
                        </div>
                        <div class="small text-secondary text-nowrap"><?= (int)$ratingSummary['total'] ?> teacher review<?= $ratingSummary['total'] === 1 ? '' : 's' ?></div>
                    </div>
                    <div class="flex-grow-1" style="min-width:220px; max-width:340px;">
                        <?php for ($stars = 5; $stars >= 1; $stars--): $count = $ratingSummary['breakdown'][$stars]; $pct = $ratingSummary['total'] > 0 ? round($count / $ratingSummary['total'] * 100) : 0; ?>
                            <div class="d-flex align-items-center gap-2 small mb-1">
                                <span class="text-nowrap" style="width:42px;"><?= $stars ?> <i class="fa-solid fa-star text-warning"></i></span>
                                <div class="progress flex-grow-1" style="height:8px;">
                                    <div class="progress-bar bg-warning" role="progressbar" style="width: <?= (int)$pct ?>%" aria-valuenow="<?= (int)$pct ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <span class="text-secondary text-end" style="width:24px;"><?= (int)$count ?></span>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-light border text-center py-4 mb-4">
                    <div class="text-secondary mb-2"><i class="fa-regular fa-star fa-2x"></i></div>
                    <p class="fw-bold mb-1">No ratings yet</p>
                    <p class="text-secondary small mb-0">Be the first teacher to review this resource.</p>
                </div>
            <?php endif; ?>

            <?php if ($isLoggedIn && $canWriteReview): ?>
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-body">
                        <h3 class="h6 fw-bold mb-3"><?= $myReview ? 'Update Your Review' : 'How would you rate this resource?' ?></h3>
                        <?php if (!empty($reviewErrors['general'])): ?><div class="alert alert-danger"><?= e($reviewErrors['general']) ?></div><?php endif; ?>
                        <form method="post" action="<?= e(base_url('resource.php?slug=' . urlencode($resource['slug']))) ?>#reviews" novalidate>
                            <?php csrf_field(); ?>
                            <input type="hidden" name="action" value="submit_review">
                            <div class="star-rating-input mb-2">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <input type="radio" name="rating" id="star<?= $i ?>" value="<?= $i ?>" <?= (int)($myReview['rating'] ?? 0) === $i ? 'checked' : '' ?> required>
                                    <label for="star<?= $i ?>" title="<?= $i ?> star<?= $i === 1 ? '' : 's' ?>"><i class="fa-solid fa-star"></i></label>
                                <?php endfor; ?>
                            </div>
                            <?php if (isset($reviewErrors['rating'])): ?><div class="text-danger small mb-2"><?= e($reviewErrors['rating']) ?></div><?php endif; ?>
                            <label class="form-label" for="review_text">Write a review <span class="text-secondary">(optional)</span></label>
                            <textarea class="form-control <?= isset($reviewErrors['review_text']) ? 'is-invalid' : '' ?>" id="review_text" name="review_text" rows="3" maxlength="2000"><?= e($myReview['review_text'] ?? '') ?></textarea>
                            <?php if (isset($reviewErrors['review_text'])): ?><div class="invalid-feedback"><?= e($reviewErrors['review_text']) ?></div><?php endif; ?>
                            <button type="submit" class="btn btn-primary mt-3"><?= $myReview ? 'Update Review' : 'Submit Review' ?></button>
                        </form>
                        <?php if ($myReview): ?>
                            <p class="small text-secondary mt-2 mb-0">
                                Your review status:
                                <span class="badge <?= $myReview['status'] === 'approved' ? 'bg-success' : ($myReview['status'] === 'rejected' ? 'bg-danger' : 'bg-warning text-dark') ?>"><?= e(ucfirst($myReview['status'])) ?></span>
                                <?php if ($myReview['status'] === 'approved'): ?> &mdash; editing it will send it back for re-approval.<?php endif; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php elseif (!$isLoggedIn): ?>
                <div class="alert alert-light border small mb-4">
                    <a href="<?= e(base_url('login.php')) ?>">Log in</a> and download this resource to write a review.
                </div>
            <?php else: ?>
                <div class="alert alert-light border small mb-4">Download this resource to write a review.</div>
            <?php endif; ?>

            <?php if (!empty($resourceReviews)): ?>
                <?php foreach ($resourceReviews as $review): ?>
                    <div class="review-item border-bottom pb-3 mb-3">
                        <div class="text-warning mb-1">
                            <?php for ($s = 1; $s <= 5; $s++): ?><i class="fa-<?= $s <= (int)$review['rating'] ? 'solid' : 'regular' ?> fa-star"></i><?php endfor; ?>
                        </div>
                        <?php if (!empty($review['review_text'])): ?>
                            <p class="mb-2 review-text"><?= nl2br(e($review['review_text'])) ?></p>
                        <?php endif; ?>
                        <div class="d-flex flex-wrap align-items-center gap-2 small text-secondary">
                            <span class="fw-bold text-dark"><?= e($review['first_name']) ?></span>
                            <?php if ($review['is_verified']): ?>
                                <span class="text-success"><i class="fa-solid fa-circle-check"></i> Verified Teacher</span>
                            <?php endif; ?>
                            <span>&middot;</span>
                            <span><?= e(format_date($review['created_at'])) ?></span>
                        </div>
                        <div class="mt-2">
                            <?php if ($isLoggedIn): ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary review-helpful-btn <?= isset($myHelpfulVotes[(int)$review['id']]) ? 'active' : '' ?>"
                                        data-review-id="<?= (int)$review['id'] ?>" data-csrf="<?= e(generate_csrf_token()) ?>">
                                    <i class="fa-solid fa-thumbs-up me-1"></i>Helpful <span class="helpful-count"><?= (int)$review['helpful_count'] ?></span>
                                </button>
                            <?php else: ?>
                                <span class="small text-secondary"><i class="fa-solid fa-thumbs-up me-1"></i>Helpful <?= (int)$review['helpful_count'] ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($relatedResources)): ?>
        <hr class="my-5">
        <h2 class="h4 fw-bold mb-4">Related Resources</h2>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4">
            <?php foreach ($relatedResources as $relatedResource): ?>
                <?php $resource = $relatedResource; // resource-card.php expects $resource in scope ?>
                <?php include __DIR__ . '/includes/resource-card.php'; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<script>
(function () {
    var copyBtn = document.getElementById('copyResourceLink');
    if (!copyBtn) { return; }
    copyBtn.addEventListener('click', function () {
        navigator.clipboard.writeText(copyBtn.dataset.url).then(function () {
            var icon = copyBtn.querySelector('i');
            icon.classList.remove('fa-link');
            icon.classList.add('fa-check');
            setTimeout(function () {
                icon.classList.remove('fa-check');
                icon.classList.add('fa-link');
            }, 1500);
        });
    });
})();
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
