<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/resource-functions.php';
require_once __DIR__ . '/includes/favorites-functions.php';
require_once __DIR__ . '/includes/subject-functions.php';
require_once __DIR__ . '/includes/seo-functions.php';

$filters = [
    'search'        => trim((string)($_GET['search'] ?? '')),
    'subject_id'    => (int)($_GET['subject_id'] ?? 0),
    'grade'         => trim((string)($_GET['grade'] ?? '')),
    'resource_type' => trim((string)($_GET['resource_type'] ?? '')),
    'category_id'   => (int)($_GET['category_id'] ?? 0),
    'access'        => trim((string)($_GET['access'] ?? '')),
];

$page = max(1, (int)($_GET['page'] ?? 1));
$result = get_published_resources($filters, $page, RESOURCES_PER_PAGE);
$categoriesGrouped = get_categories_grouped($filters['subject_id']);
$subjects = get_all_subjects();

$activeSubject = $filters['subject_id'] > 0 ? get_subject_by_id($filters['subject_id']) : null;
$activeCategory = $filters['category_id'] > 0 ? get_category_by_id($filters['category_id']) : null;
$listingSeo = generate_resources_listing_seo($filters, $activeSubject, $activeCategory, (int)$result['total']);

$pageTitle = $listingSeo['title'];
$pageDescription = $listingSeo['description'];
$pageRobots = $listingSeo['noindex'] ? 'noindex, follow' : 'index, follow';

$breadcrumbSchema = null;
if ($activeSubject || $activeCategory) {
    $crumbs = [['name' => 'Resources', 'url' => base_url('resources.php')]];
    if ($activeSubject) {
        $crumbs[] = ['name' => $activeSubject['name'], 'url' => base_url('resources.php?subject_id=' . (int)$activeSubject['id'])];
    }
    if ($activeCategory) {
        $crumbs[] = ['name' => $activeCategory['name'], 'url' => base_url('resources.php?category_id=' . (int)$activeCategory['id'])];
    }
    $breadcrumbSchema = [
        '@context'        => 'https://schema.org',
        '@type'           => 'BreadcrumbList',
        'itemListElement' => array_map(static function (array $item, int $index): array {
            return ['@type' => 'ListItem', 'position' => $index + 1, 'name' => $item['name'], 'item' => $item['url']];
        }, $crumbs, array_keys($crumbs)),
    ];
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="container py-5">
    <?php if ($breadcrumbSchema): ?>
        <script type="application/ld+json"><?= json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES) ?></script>
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb small">
                <li class="breadcrumb-item"><a href="<?= e(base_url('resources.php')) ?>">Resources</a></li>
                <?php if ($activeSubject): ?>
                    <li class="breadcrumb-item"><a href="<?= e(base_url('resources.php?subject_id=' . (int)$activeSubject['id'])) ?>"><?= e($activeSubject['name']) ?></a></li>
                <?php endif; ?>
                <?php if ($activeCategory): ?>
                    <li class="breadcrumb-item active" aria-current="page"><?= e($activeCategory['name']) ?></li>
                <?php endif; ?>
            </ol>
        </nav>
    <?php endif; ?>
    <h1 class="fw-bold mb-1"><?= e($listingSeo['h1']) ?></h1>
    <?php if (!empty($listingSeo['intro'])): ?>
        <p class="text-secondary mb-2"><?= e($listingSeo['intro']) ?></p>
    <?php endif; ?>
    <p class="text-secondary mb-4">
        <?= (int)$result['total'] ?> resource<?= $result['total'] === 1 ? '' : 's' ?> found
    </p>

    <form method="get" action="<?= e(base_url('resources.php')) ?>" class="row g-2 mb-4">
        <div class="col-lg-2 col-md-6">
            <input type="text" name="search" class="form-control" placeholder="Search title, topic&hellip;" value="<?= e($filters['search']) ?>">
        </div>
        <div class="col-lg-2 col-md-6">
            <select name="subject_id" class="form-select" onchange="this.form.submit()">
                <option value="">All Subjects</option>
                <?php foreach ($subjects as $subjectOption): ?>
                    <option value="<?= (int)$subjectOption['id'] ?>" <?= $filters['subject_id'] === (int)$subjectOption['id'] ? 'selected' : '' ?>><?= e($subjectOption['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-2 col-md-6">
            <select name="grade" class="form-select">
                <option value="">All Grades</option>
                <?php foreach (GRADE_LEVELS as $grade): ?>
                    <option value="<?= e($grade) ?>" <?= $filters['grade'] === $grade ? 'selected' : '' ?>><?= e($grade) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-2 col-md-6">
            <select name="resource_type" class="form-select">
                <option value="">All Types</option>
                <?php foreach (RESOURCE_TYPES as $type): ?>
                    <option value="<?= e($type) ?>" <?= $filters['resource_type'] === $type ? 'selected' : '' ?>><?= e($type) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-2 col-md-6">
            <select name="category_id" class="form-select">
                <option value="">All Categories</option>
                <?php foreach ($categoriesGrouped as $groupName => $categories): ?>
                    <optgroup label="<?= e($groupName) ?>">
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int)$category['id'] ?>" <?= $filters['category_id'] === (int)$category['id'] ? 'selected' : '' ?>>
                                <?= e($category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-1 col-md-6">
            <select name="access" class="form-select">
                <option value="">All Access</option>
                <option value="free" <?= $filters['access'] === 'free' ? 'selected' : '' ?>>Free</option>
                <option value="members" <?= $filters['access'] === 'members' ? 'selected' : '' ?>>Members Only</option>
            </select>
        </div>
        <div class="col-lg-1 col-md-6 d-grid">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i></button>
        </div>
    </form>

    <?php if (empty($result['items'])): ?>
        <div class="alert alert-info">No resources match your search yet. Try different filters, or check back soon &mdash; new resources are added regularly.</div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4 mb-4">
            <?php foreach ($result['items'] as $resource): ?>
                <?php include __DIR__ . '/includes/resource-card.php'; ?>
            <?php endforeach; ?>
        </div>

        <?= render_pagination($result['page'], $result['total_pages']) ?>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
