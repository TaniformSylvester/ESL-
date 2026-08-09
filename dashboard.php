<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/membership.php';
require_once __DIR__ . '/includes/resource-functions.php';

require_login();
$user = current_user();
$membership = get_membership($user['id']) ?? ['status' => 'inactive', 'expiry_date' => null];
$isActive = isMemberActive($user['id']);

$totalResources = get_published_resource_count();
$recentResources = get_featured_resources(4);
$categoriesGrouped = get_categories_grouped();

$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
?>
<div class="container py-5">
    <h1 class="h3 fw-bold mb-1">Welcome, <?= e($user['first_name']) ?>!</h1>
    <p class="text-secondary mb-4">Here's what's happening with your <?= e(SITE_NAME) ?> account.</p>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <p class="text-secondary small mb-1 text-uppercase">Membership</p>
                    <span class="badge <?= e(membership_status_badge_class($membership)) ?> mb-2"><?= e(membership_status_label($membership)) ?></span>
                    <?php if ($isActive): ?>
                        <p class="small mb-0">Renews/expires <?= e(format_date($membership['expiry_date'])) ?></p>
                    <?php else: ?>
                        <p class="small mb-0"><a href="<?= e(base_url('member/subscription.php')) ?>">Subscribe now &rarr;</a></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <p class="text-secondary small mb-1 text-uppercase">Resources Available</p>
                    <p class="h3 fw-bold mb-0"><?= (int)$totalResources ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <p class="text-secondary small mb-1 text-uppercase">Quick Links</p>
                    <div class="d-flex flex-column small">
                        <a href="<?= e(base_url('resources.php')) ?>">Browse Resources</a>
                        <a href="<?= e(base_url('member/favorites.php')) ?>">My Favorites</a>
                        <a href="<?= e(base_url('member/downloads.php')) ?>">My Downloads</a>
                        <a href="<?= e(base_url('member/profile.php')) ?>">Edit Profile</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="get" action="<?= e(base_url('resources.php')) ?>" class="d-flex gap-2">
                <input type="text" name="search" class="form-control" placeholder="Search resources by title, topic, subject&hellip;">
                <button type="submit" class="btn btn-primary px-4"><i class="fa-solid fa-magnifying-glass me-1"></i> Search</button>
            </form>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 fw-bold mb-0">Recently Added Resources</h2>
                <a href="<?= e(base_url('resources.php')) ?>" class="small">View All &rarr;</a>
            </div>
            <?php if (empty($recentResources)): ?>
                <p class="text-secondary">No resources published yet &mdash; check back soon.</p>
            <?php else: ?>
                <div class="row row-cols-1 row-cols-sm-2 g-4">
                    <?php foreach ($recentResources as $resource): ?>
                        <?php include __DIR__ . '/includes/resource-card.php'; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <h2 class="h5 fw-bold mb-3">Categories</h2>
            <?php foreach ($categoriesGrouped as $groupName => $categories): ?>
                <p class="small fw-bold text-secondary text-uppercase mb-2"><?= e($groupName) ?></p>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <?php foreach ($categories as $category): ?>
                        <a href="<?= e(base_url('resources.php?category_id=' . $category['id'])) ?>" class="btn btn-sm btn-outline-secondary">
                            <?= e($category['name']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
