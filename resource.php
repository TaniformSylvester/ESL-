<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/membership.php';
require_once __DIR__ . '/includes/resource-functions.php';
require_once __DIR__ . '/includes/download-functions.php';
require_once __DIR__ . '/includes/favorites-functions.php';
require_once __DIR__ . '/includes/review-functions.php';
require_once __DIR__ . '/includes/seo-functions.php';
require_once __DIR__ . '/includes/guide-functions.php';

$slug = trim((string)($_GET['slug'] ?? ''));
$resource = $slug !== '' ? get_resource_by_slug($slug) : null;

if (!$resource) {
    // This slug might belong to an archived resource rather than one that
    // never existed — don't blindly 404 an old URL when we can send the
    // visitor somewhere useful instead (TeachLuma 2.0 Phase 1).
    $archived = $slug !== '' ? get_archived_resource_by_slug($slug) : null;

    if ($archived) {
        $redirectTarget = !empty($archived['redirect_resource_id'])
            ? get_resource_by_id((int)$archived['redirect_resource_id'])
            : null;

        if ($redirectTarget && $redirectTarget['is_published'] && $redirectTarget['status'] === 'active') {
            header('Location: ' . base_url('resource.php?slug=' . rawurlencode($redirectTarget['slug'])), true, 301);
            exit;
        }

        // No specific replacement set — fall back to the archived
        // resource's own category/subject listing rather than the
        // homepage, so the visitor lands somewhere relevant.
        $fallbackParams = [];
        if (!empty($archived['category_id'])) {
            $fallbackParams['category_id'] = (int)$archived['category_id'];
        } elseif (!empty($archived['subject_id'])) {
            $fallbackParams['subject_id'] = (int)$archived['subject_id'];
        }
        $fallbackUrl = base_url('resources.php') . (!empty($fallbackParams) ? '?' . http_build_query($fallbackParams) : '');
        flash_set('info', 'That resource has been retired. Here are similar resources instead.');
        header('Location: ' . $fallbackUrl, true, 302);
        exit;
    }

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
$gaAccountState = !$isLoggedIn ? 'guest' : (is_admin() ? 'admin' : ($isPro ? 'pro' : 'free'));
$isFavorited = $isLoggedIn && is_favorited((int)$_SESSION['user_id'], (int)$resource['id']);
$previewUrl = !empty($resource['preview_image']) ? UPLOAD_PREVIEW_URL . '/' . rawurlencode($resource['preview_image']) : null;
$thumbUrl = !empty($resource['thumbnail']) ? UPLOAD_THUMBNAIL_URL . '/' . rawurlencode($resource['thumbnail']) : null;
$displayImage = $previewUrl ?? $thumbUrl;

$teachingLayout = get_teaching_detail_layout($resource['resource_type']);
$teachingSections = [];
foreach ($teachingLayout as $field => $label) {
    if (!empty($resource[$field])) {
        $teachingSections[$field] = $label;
    }
}
$listFields = ['learning_objectives', 'how_to_use', 'activity_ideas'];
$skillsList = !empty($resource['skills_practiced']) ? array_filter(array_map('trim', explode(',', $resource['skills_practiced']))) : [];

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

// Manual admin picks take priority (Phase 2 Step 12); fall back to the
// automatic relevance query only when nothing has been manually chosen.
$relatedResources = get_manual_related_resources((int)$resource['id']);
if (empty($relatedResources)) {
    $relatedResources = get_related_resources($resource, 6);
}
$relatedGuides = get_resource_related_guides((int)$resource['id'], 3);
$additionalFiles = get_resource_files((int)$resource['id']);
$whatsIncludedList = !empty($resource['whats_included']) ? array_filter(array_map('trim', explode("\n", $resource['whats_included']))) : [];

$pageTitle = generate_resource_seo_title($resource);
$pageDescription = generate_resource_seo_description($resource);
$pageImage = $displayImage ?? asset_url('images/og-image-icon.png');

// quota_blocked no longer applies (free downloads are unlimited) and is
// intentionally not emitted here anymore — see includes/download-functions.php.
$gaCustomEvents = [[
    'name'   => 'resource_view',
    'params' => [
        'resource_id'    => (int)$resource['id'],
        'resource_title' => $resource['title'],
        'subject'        => $resource['subject_name'] ?? null,
        'grade'          => $resource['grade_level'] ?? null,
        'is_free'        => (bool)$resource['is_free'],
    ],
]];

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
if (!empty($resource['learning_objectives'])) {
    $resourceSchema['teaches'] = array_filter(array_map('trim', explode("\n", $resource['learning_objectives'])));
}
if (!empty($resource['suggested_duration'])) {
    $resourceSchema['timeRequired'] = $resource['suggested_duration'];
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

            <?php if (!empty($resource['overview'])): ?>
                <h2 class="h6 fw-bold text-secondary text-uppercase mt-4 mb-2">Overview</h2>
                <p class="text-secondary"><?= nl2br(e($resource['overview'])) ?></p>
            <?php endif; ?>

            <h2 class="h6 fw-bold text-secondary text-uppercase mt-4 mb-2">Resource Details</h2>
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
                <dt class="col-4">Downloads</dt><dd class="col-8" id="resource-download-count"><?= (int)$resource['download_count'] ?></dd>
            </dl>

            <div class="mt-4">
                <?php if ($canDownload): ?>
                    <a href="<?= e(base_url('member/download.php?id=' . (int)$resource['id'])) ?>" class="btn btn-primary btn-lg px-4 js-download-link"
                       data-resource-id="<?= (int)$resource['id'] ?>"
                       data-resource-title="<?= e($resource['title']) ?>"
                       data-is-free="<?= $resource['is_free'] ? '1' : '0' ?>"
                       data-account-state="<?= e($gaAccountState) ?>"
                       data-initial-count="<?= (int)$resource['download_count'] ?>"
                       data-csrf="<?= e(generate_csrf_token()) ?>">
                        <i class="fa-solid fa-download me-2"></i>Download
                    </a>
                    <?php if ($resource['is_free']): ?>
                        <p class="small text-secondary mt-2 mb-0">
                            <i class="fa-solid fa-circle-check text-success me-1"></i>Free download &mdash; no account required.
                        </p>
                    <?php endif; ?>
                <?php else: ?>
                    <!-- Only reachable for a members-only resource now: free
                         resources are always downloadable, so this branch is
                         the Pro-only gate, unchanged from before. -->
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

    <?php if (!empty($teachingSections) || !empty($skillsList) || !empty($resource['recommended_level']) || !empty($resource['suggested_duration']) || !empty($whatsIncludedList) || !empty($additionalFiles)): ?>
    <hr class="my-5">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <?php if (!empty($skillsList) || !empty($resource['recommended_level']) || !empty($resource['suggested_duration'])): ?>
                <div class="d-flex flex-wrap gap-2 mb-4">
                    <?php if (!empty($resource['recommended_level'])): ?>
                        <span class="badge bg-light text-dark border"><i class="fa-solid fa-graduation-cap me-1"></i><?= e($resource['recommended_level']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($resource['suggested_duration'])): ?>
                        <span class="badge bg-light text-dark border"><i class="fa-regular fa-clock me-1"></i><?= e($resource['suggested_duration']) ?></span>
                    <?php endif; ?>
                    <?php foreach ($skillsList as $skill): ?>
                        <span class="badge bg-light text-dark border"><?= e($skill) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php foreach ($teachingSections as $field => $label): ?>
                <h2 class="h6 fw-bold text-secondary text-uppercase mb-2 mt-4"><?= e($label) ?></h2>
                <?php if (in_array($field, $listFields, true)): ?>
                    <ul class="mb-0">
                        <?php foreach (array_filter(array_map('trim', explode("\n", $resource[$field]))) as $line): ?>
                            <li><?= e($line) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-secondary mb-0"><?= nl2br(e($resource[$field])) ?></p>
                <?php endif; ?>
            <?php endforeach; ?>

            <?php if (!empty($whatsIncludedList)): ?>
                <h2 class="h6 fw-bold text-secondary text-uppercase mb-2 mt-4">What's Included</h2>
                <ul class="mb-0">
                    <?php foreach ($whatsIncludedList as $item): ?>
                        <li><?= e($item) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if (!empty($additionalFiles)): ?>
                <h2 class="h6 fw-bold text-secondary text-uppercase mb-2 mt-4">Additional Files</h2>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($additionalFiles as $extraFile): ?>
                        <li class="mb-2">
                            <?php if ($canDownload): ?>
                                <a href="<?= e(base_url('member/download-extra.php?id=' . (int)$extraFile['id'])) ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fa-solid fa-download me-1"></i><?= e($extraFile['label'] ?: $extraFile['file_name']) ?>
                                </a>
                            <?php else: ?>
                                <span class="text-secondary"><i class="fa-solid fa-lock me-1"></i><?= e($extraFile['label'] ?: $extraFile['file_name']) ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

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

            <div id="review-box-container">
                <?php require __DIR__ . '/includes/review-box.php'; ?>
            </div>

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

    <?php if (!empty($relatedGuides)): ?>
        <hr class="my-5">
        <h2 class="h4 fw-bold mb-4">From the Teacher Hub</h2>
        <div class="row row-cols-1 row-cols-sm-3 g-4">
            <?php foreach ($relatedGuides as $guide): ?>
                <div class="col">
                    <a href="<?= e(base_url('teacher-hub-guide.php?slug=' . urlencode($guide['slug']))) ?>" class="card shadow-sm border-0 h-100 text-decoration-none text-reset">
                        <div class="card-body">
                            <h3 class="h6 fw-bold"><?= e($guide['title']) ?></h3>
                            <?php if (!empty($guide['summary'])): ?>
                                <p class="small text-secondary mb-0"><?= e($guide['summary']) ?></p>
                            <?php endif; ?>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

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
