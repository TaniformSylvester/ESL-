<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/membership.php';
require_once __DIR__ . '/includes/resource-functions.php';
require_once __DIR__ . '/includes/download-functions.php';
require_once __DIR__ . '/includes/favorites-functions.php';

$slug = trim((string)($_GET['slug'] ?? ''));
$resource = $slug !== '' ? get_resource_by_slug($slug) : null;

if (!$resource) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$isLoggedIn = is_logged_in();
$canDownload = can_download_resource($resource);
$isFavorited = $isLoggedIn && is_favorited((int)$_SESSION['user_id'], (int)$resource['id']);
$previewUrl = !empty($resource['preview_image']) ? UPLOAD_PREVIEW_URL . '/' . rawurlencode($resource['preview_image']) : null;
$thumbUrl = !empty($resource['thumbnail']) ? UPLOAD_THUMBNAIL_URL . '/' . rawurlencode($resource['thumbnail']) : null;
$displayImage = $previewUrl ?? $thumbUrl;

$pageTitle = $resource['title'];
$pageDescription = truncate_text($resource['description'] ?? '', 160);

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
if (!empty($resource['category_name'])) {
    $resourceSchema['about'] = $resource['category_name'];
}
if (!empty($displayImage)) {
    $resourceSchema['image'] = $displayImage;
}

require_once __DIR__ . '/includes/header.php';
?>
<script type="application/ld+json"><?= json_encode($resourceSchema, JSON_UNESCAPED_SLASHES) ?></script>
<div class="container py-5">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="<?= e(base_url('resources.php')) ?>">Resources</a></li>
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
                <span class="badge bg-light text-dark border"><?= e($resource['resource_type']) ?></span>
                <?php if (!empty($resource['grade_level'])): ?>
                    <span class="badge bg-light text-dark border"><?= e($resource['grade_level']) ?></span>
                <?php endif; ?>
            </div>

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
                <p class="text-secondary"><?= nl2br(e($resource['description'])) ?></p>
            <?php endif; ?>

            <dl class="row small mt-4">
                <?php if (!empty($resource['subject'])): ?>
                    <dt class="col-4">Subject</dt><dd class="col-8"><?= e($resource['subject']) ?></dd>
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
                <?php else: ?>
                    <div class="alert alert-warning">
                        <p class="fw-bold mb-1"><i class="fa-solid fa-lock me-1"></i> Members Only</p>
                        <p class="mb-3">Subscribe for <?= format_currency(SUBSCRIPTION_PRICE) ?>/month to download this resource.</p>
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="<?= e(base_url('pricing.php')) ?>" class="btn btn-primary">Subscribe for <?= format_currency(SUBSCRIPTION_PRICE) ?>/month</a>
                            <?php if (!$isLoggedIn): ?>
                                <a href="<?= e(base_url('login.php')) ?>" class="btn btn-outline-secondary">Login</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
