<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/guide-functions.php';
require_once __DIR__ . '/includes/seo-functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/favorites-functions.php';
require_once __DIR__ . '/includes/resource-functions.php';

$slug = trim((string)($_GET['slug'] ?? ''));
$guide = $slug !== '' ? get_guide_by_slug($slug) : null;

if (!$guide) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$relatedResources = get_guide_related_resources((int)$guide['id']);

$pageTitle = generate_guide_seo_title($guide);
$pageDescription = generate_guide_seo_description($guide);

$breadcrumbItems = [
    ['name' => 'Teacher Hub', 'url' => base_url('teacher-hub.php')],
    ['name' => GUIDE_CATEGORIES[$guide['category']] ?? $guide['category'], 'url' => base_url('teacher-hub.php') . '#' . $guide['category']],
    ['name' => $guide['title'], 'url' => base_url('teacher-hub-guide.php?slug=' . rawurlencode($guide['slug']))],
];
$breadcrumbSchema = [
    '@context'        => 'https://schema.org',
    '@type'           => 'BreadcrumbList',
    'itemListElement' => array_map(static function (array $item, int $index): array {
        return ['@type' => 'ListItem', 'position' => $index + 1, 'name' => $item['name'], 'item' => $item['url']];
    }, $breadcrumbItems, array_keys($breadcrumbItems)),
];

$articleSchema = [
    '@context'      => 'https://schema.org',
    '@type'         => 'Article',
    'headline'      => $guide['title'],
    'description'   => $pageDescription,
    'url'           => base_url('teacher-hub-guide.php?slug=' . rawurlencode($guide['slug'])),
    'datePublished' => date('c', strtotime($guide['created_at'])),
    'dateModified'  => date('c', strtotime($guide['updated_at'])),
    'publisher'     => ['@type' => 'Organization', 'name' => SITE_NAME, 'url' => base_url()],
];

require_once __DIR__ . '/includes/header.php';
?>
<script type="application/ld+json"><?= json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES) ?></script>
<script type="application/ld+json"><?= json_encode($articleSchema, JSON_UNESCAPED_SLASHES) ?></script>
<div class="container py-5">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="<?= e(base_url('teacher-hub.php')) ?>">Teacher Hub</a></li>
            <li class="breadcrumb-item"><a href="<?= e(base_url('teacher-hub.php') . '#' . $guide['category']) ?>"><?= e(GUIDE_CATEGORIES[$guide['category']] ?? $guide['category']) ?></a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= e($guide['title']) ?></li>
        </ol>
    </nav>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <span class="badge bg-light text-dark border mb-2"><?= e(GUIDE_CATEGORIES[$guide['category']] ?? $guide['category']) ?></span>
            <h1 class="fw-bold mb-3"><?= e($guide['title']) ?></h1>

            <?php if (!empty($guide['intro'])): ?>
                <p class="lead"><?= nl2br(e($guide['intro'])) ?></p>
            <?php endif; ?>

            <?php foreach (GUIDE_SECTIONS as $field => $label): ?>
                <?php if ($field === 'intro' || empty($guide[$field])): ?>
                    <?php continue; ?>
                <?php endif; ?>
                <h2 class="h5 fw-bold mt-4 mb-2"><?= e($label) ?></h2>
                <?php if ($field === 'activities'): ?>
                    <ul>
                        <?php foreach (array_filter(array_map('trim', explode("\n", $guide[$field]))) as $line): ?>
                            <li><?= e($line) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p><?= nl2br(e($guide[$field])) ?></p>
                <?php endif; ?>
            <?php endforeach; ?>

            <?php if (!empty($relatedResources)): ?>
                <hr class="my-5">
                <h2 class="h5 fw-bold mb-4">Recommended TeachLuma Resources</h2>
                <div class="row row-cols-1 row-cols-sm-2 g-4">
                    <?php foreach ($relatedResources as $relatedResource): ?>
                        <?php $resource = $relatedResource; // resource-card.php expects $resource in scope ?>
                        <?php include __DIR__ . '/includes/resource-card.php'; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="text-center mt-5">
                <a href="<?= e(base_url('teacher-hub.php')) ?>" class="btn btn-outline-secondary">&larr; Back to Teacher Hub</a>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
