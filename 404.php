<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/favorites-functions.php';
require_once __DIR__ . '/includes/resource-functions.php';
require_once __DIR__ . '/includes/subject-functions.php';

$subjects = get_all_subjects();
$popularResources = get_popular_resources(4);

$pageTitle = 'Page Not Found';
$pageRobots = 'noindex, follow';
require_once __DIR__ . '/includes/header.php';
?>
<div class="container text-center py-5 my-5">
    <h1 class="display-3 fw-bold text-primary">404</h1>
    <p class="lead">Page Not Found</p>
    <p class="text-secondary">Sorry, the page you're looking for could not be found. Here are some places to pick up from:</p>

    <div class="d-flex flex-wrap justify-content-center gap-2 my-4">
        <a href="<?= e(base_url()) ?>" class="btn btn-primary">Homepage</a>
        <a href="<?= e(base_url('resources.php')) ?>" class="btn btn-outline-secondary">All Resources</a>
        <?php foreach ($subjects as $subject): ?>
            <a href="<?= e(base_url('resources.php?subject_id=' . (int)$subject['id'])) ?>" class="btn btn-outline-secondary"><?= e($subject['name']) ?></a>
        <?php endforeach; ?>
    </div>

    <?php if (!empty($popularResources)): ?>
        <h2 class="h5 fw-bold mt-5 mb-3">Popular Resources</h2>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-4 text-start">
            <?php foreach ($popularResources as $resource): ?>
                <?php include __DIR__ . '/includes/resource-card.php'; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
