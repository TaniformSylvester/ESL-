<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/resource-functions.php';
require_once __DIR__ . '/../includes/favorites-functions.php';

require_login();
$user = current_user();

$page = max(1, (int)($_GET['page'] ?? 1));
$result = get_user_favorites($user['id'], $page, RESOURCES_PER_PAGE);

$pageTitle = 'My Favorites';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="container py-5">
    <h1 class="fw-bold mb-4">My Favorites</h1>

    <?php if (empty($result['items'])): ?>
        <div class="alert alert-info">
            You haven't favorited any resources yet. <a href="<?= e(base_url('resources.php')) ?>">Browse resources</a> and click the heart icon to save some here.
        </div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4 mb-4">
            <?php foreach ($result['items'] as $resource): ?>
                <?php include __DIR__ . '/../includes/resource-card.php'; ?>
            <?php endforeach; ?>
        </div>

        <?= render_pagination($result['page'], $result['total_pages']) ?>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
