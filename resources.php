<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/resource-functions.php';

$filters = [
    'search'        => trim((string)($_GET['search'] ?? '')),
    'grade'         => trim((string)($_GET['grade'] ?? '')),
    'resource_type' => trim((string)($_GET['resource_type'] ?? '')),
    'category_id'   => (int)($_GET['category_id'] ?? 0),
    'access'        => trim((string)($_GET['access'] ?? '')),
];

$page = max(1, (int)($_GET['page'] ?? 1));
$result = get_published_resources($filters, $page, RESOURCES_PER_PAGE);
$categoriesGrouped = get_categories_grouped();

$pageTitle = 'Resources';
$pageDescription = 'Browse ESL lesson plans, worksheets, PowerPoints, games and more for primary school teachers.';
require_once __DIR__ . '/includes/header.php';
?>
<div class="container py-5">
    <h1 class="fw-bold mb-1">Resources</h1>
    <p class="text-secondary mb-4">
        <?= (int)$result['total'] ?> resource<?= $result['total'] === 1 ? '' : 's' ?> found
    </p>

    <form method="get" action="<?= e(base_url('resources.php')) ?>" class="row g-2 mb-4">
        <div class="col-lg-3 col-md-6">
            <input type="text" name="search" class="form-control" placeholder="Search title, topic, subject&hellip;" value="<?= e($filters['search']) ?>">
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
        <div class="col-lg-2 col-md-6">
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
