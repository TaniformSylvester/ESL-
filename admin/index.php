<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-functions.php';
require_once __DIR__ . '/../includes/review-functions.php';

require_admin();
$stats = get_dashboard_stats();
$mostDownloaded = get_most_downloaded_resources(5);
$mostActiveUsers = get_most_active_users(5);
$reviewStats = get_review_stats();
$topRatedResources = get_top_rated_resources(5);
$mostReviewedResources = get_most_reviewed_resources(5);

$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/admin-header.php';
?>
<h2 class="h6 fw-bold text-secondary text-uppercase mb-3">Membership Overview</h2>
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-2">
        <div class="card shadow-sm border-0 stat-card h-100"><div class="card-body">
            <p class="text-secondary small text-uppercase mb-1">Total Users</p>
            <p class="stat-value mb-0"><?= (int)$stats['total_users'] ?></p>
        </div></div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="card shadow-sm border-0 stat-card h-100"><div class="card-body">
            <p class="text-secondary small text-uppercase mb-1">Free Users</p>
            <p class="stat-value mb-0"><?= (int)$stats['free_users'] ?></p>
        </div></div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="card shadow-sm border-0 stat-card h-100"><div class="card-body">
            <p class="text-secondary small text-uppercase mb-1">Pro Users</p>
            <p class="stat-value mb-0 text-success"><?= (int)$stats['pro_users'] ?></p>
        </div></div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="card shadow-sm border-0 stat-card h-100"><div class="card-body">
            <p class="text-secondary small text-uppercase mb-1">Monthly Pro</p>
            <p class="stat-value mb-0"><?= (int)$stats['pro_monthly'] ?></p>
        </div></div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="card shadow-sm border-0 stat-card h-100"><div class="card-body">
            <p class="text-secondary small text-uppercase mb-1">Annual Pro</p>
            <p class="stat-value mb-0"><?= (int)$stats['pro_annual'] ?></p>
        </div></div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="card shadow-sm border-0 stat-card h-100"><div class="card-body">
            <p class="text-secondary small text-uppercase mb-1">Expired</p>
            <p class="stat-value mb-0 text-danger"><?= (int)$stats['expired_members'] ?></p>
        </div></div>
    </div>
</div>

<h2 class="h6 fw-bold text-secondary text-uppercase mb-3">Payments</h2>
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="card shadow-sm border-0 stat-card h-100"><div class="card-body">
            <p class="text-secondary small text-uppercase mb-1">Pending</p>
            <p class="stat-value mb-0 text-warning"><?= (int)$stats['payments_pending'] ?></p>
            <?php if ($stats['payments_pending'] > 0): ?>
                <a href="<?= e(base_url('admin/payments.php')) ?>" class="small">Review &rarr;</a>
            <?php endif; ?>
        </div></div>
    </div>
    <div class="col-sm-4">
        <div class="card shadow-sm border-0 stat-card h-100"><div class="card-body">
            <p class="text-secondary small text-uppercase mb-1">Approved</p>
            <p class="stat-value mb-0 text-success"><?= (int)$stats['payments_approved'] ?></p>
        </div></div>
    </div>
    <div class="col-sm-4">
        <div class="card shadow-sm border-0 stat-card h-100"><div class="card-body">
            <p class="text-secondary small text-uppercase mb-1">Rejected</p>
            <p class="stat-value mb-0 text-danger"><?= (int)$stats['payments_rejected'] ?></p>
        </div></div>
    </div>
</div>

<h2 class="h6 fw-bold text-secondary text-uppercase mb-3">Revenue <span class="text-secondary fw-normal text-lowercase">(THB, approved payments only)</span></h2>
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="card shadow-sm border-0 stat-card h-100"><div class="card-body">
            <p class="text-secondary small text-uppercase mb-1">Total Approved Revenue</p>
            <p class="stat-value mb-0 text-primary"><?= format_currency($stats['revenue_total']) ?></p>
        </div></div>
    </div>
    <div class="col-sm-4">
        <div class="card shadow-sm border-0 stat-card h-100"><div class="card-body">
            <p class="text-secondary small text-uppercase mb-1">From Monthly Plan</p>
            <p class="stat-value mb-0"><?= format_currency($stats['revenue_monthly_plan']) ?></p>
        </div></div>
    </div>
    <div class="col-sm-4">
        <div class="card shadow-sm border-0 stat-card h-100"><div class="card-body">
            <p class="text-secondary small text-uppercase mb-1">From Annual Plan</p>
            <p class="stat-value mb-0"><?= format_currency($stats['revenue_annual_plan']) ?></p>
        </div></div>
    </div>
</div>

<h2 class="h6 fw-bold text-secondary text-uppercase mb-3">Resources &amp; Downloads</h2>
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="card shadow-sm border-0 stat-card h-100"><div class="card-body">
            <p class="text-secondary small text-uppercase mb-1">Total Resources</p>
            <p class="stat-value mb-0"><?= (int)$stats['total_resources'] ?></p>
        </div></div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card shadow-sm border-0 stat-card h-100"><div class="card-body">
            <p class="text-secondary small text-uppercase mb-1">Added This Month</p>
            <p class="stat-value mb-0"><?= (int)$stats['resources_this_month'] ?></p>
        </div></div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card shadow-sm border-0 stat-card h-100"><div class="card-body">
            <p class="text-secondary small text-uppercase mb-1">Total Downloads</p>
            <p class="stat-value mb-0"><?= (int)$stats['total_downloads'] ?></p>
        </div></div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card shadow-sm border-0 stat-card h-100"><div class="card-body">
            <p class="text-secondary small text-uppercase mb-1">Downloads This Month</p>
            <p class="stat-value mb-0"><?= (int)$stats['downloads_this_month'] ?></p>
        </div></div>
    </div>
</div>

<h2 class="h6 fw-bold text-secondary text-uppercase mb-3">Reviews</h2>
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="card shadow-sm border-0 stat-card h-100"><div class="card-body">
            <p class="text-secondary small text-uppercase mb-1">Total Reviews</p>
            <p class="stat-value mb-0"><?= (int)$reviewStats['total'] ?></p>
        </div></div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card shadow-sm border-0 stat-card h-100"><div class="card-body">
            <p class="text-secondary small text-uppercase mb-1">Pending</p>
            <p class="stat-value mb-0 text-warning"><?= (int)$reviewStats['pending'] ?></p>
            <?php if ($reviewStats['pending'] > 0): ?>
                <a href="<?= e(base_url('admin/reviews.php?status=pending')) ?>" class="small">Review &rarr;</a>
            <?php endif; ?>
        </div></div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card shadow-sm border-0 stat-card h-100"><div class="card-body">
            <p class="text-secondary small text-uppercase mb-1">Approved</p>
            <p class="stat-value mb-0 text-success"><?= (int)$reviewStats['approved'] ?></p>
        </div></div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card shadow-sm border-0 stat-card h-100"><div class="card-body">
            <p class="text-secondary small text-uppercase mb-1">Average Site Rating</p>
            <p class="stat-value mb-0 text-primary"><?= $reviewStats['approved'] > 0 ? number_format($reviewStats['avg_rating'], 1) : '&mdash;' ?></p>
        </div></div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <h2 class="h6 fw-bold mb-3">Top Rated Resources</h2>
                <?php if (empty($topRatedResources)): ?>
                    <p class="text-secondary small mb-0">No approved reviews yet.</p>
                <?php else: ?>
                    <ol class="mb-0 ps-3">
                        <?php foreach ($topRatedResources as $r): ?>
                            <li class="small mb-1">
                                <a href="<?= e(base_url('resource.php?slug=' . urlencode($r['slug']) . '#reviews')) ?>" target="_blank"><?= e($r['title']) ?></a>
                                &mdash; <i class="fa-solid fa-star text-warning"></i> <?= number_format((float)$r['avg_rating'], 1) ?> (<?= (int)$r['review_count'] ?>)
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <h2 class="h6 fw-bold mb-3">Most Reviewed Resources</h2>
                <?php if (empty($mostReviewedResources)): ?>
                    <p class="text-secondary small mb-0">No approved reviews yet.</p>
                <?php else: ?>
                    <ol class="mb-0 ps-3">
                        <?php foreach ($mostReviewedResources as $r): ?>
                            <li class="small mb-1">
                                <a href="<?= e(base_url('resource.php?slug=' . urlencode($r['slug']) . '#reviews')) ?>" target="_blank"><?= e($r['title']) ?></a>
                                &mdash; <?= (int)$r['review_count'] ?> review<?= (int)$r['review_count'] === 1 ? '' : 's' ?> (<i class="fa-solid fa-star text-warning"></i> <?= number_format((float)$r['avg_rating'], 1) ?>)
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <h2 class="h6 fw-bold mb-3">Most Downloaded Resources</h2>
                <?php if (empty($mostDownloaded)): ?>
                    <p class="text-secondary small mb-0">No downloads recorded yet.</p>
                <?php else: ?>
                    <ol class="mb-0 ps-3">
                        <?php foreach ($mostDownloaded as $r): ?>
                            <li class="small mb-1">
                                <a href="<?= e(base_url('resource.php?slug=' . urlencode($r['slug']))) ?>" target="_blank"><?= e($r['title']) ?></a>
                                &mdash; <?= (int)$r['download_count'] ?> downloads
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <h2 class="h6 fw-bold mb-3">Most Active Users</h2>
                <?php if (empty($mostActiveUsers)): ?>
                    <p class="text-secondary small mb-0">No downloads recorded yet.</p>
                <?php else: ?>
                    <ol class="mb-0 ps-3">
                        <?php foreach ($mostActiveUsers as $u): ?>
                            <li class="small mb-1">
                                <a href="<?= e(base_url('admin/user.php?id=' . (int)$u['id'])) ?>"><?= e($u['first_name'] . ' ' . $u['last_name']) ?></a>
                                &mdash; <?= (int)$u['download_total'] ?> downloads
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-3">
        <a href="<?= e(base_url('admin/users.php')) ?>" class="btn btn-outline-secondary w-100 py-3"><i class="fa-solid fa-users me-2"></i>Manage Users</a>
    </div>
    <div class="col-md-3">
        <a href="<?= e(base_url('admin/categories.php')) ?>" class="btn btn-outline-secondary w-100 py-3"><i class="fa-solid fa-tags me-2"></i>Manage Categories</a>
    </div>
    <div class="col-md-3">
        <a href="<?= e(base_url('admin/payments.php')) ?>" class="btn btn-outline-secondary w-100 py-3"><i class="fa-solid fa-money-bill me-2"></i>Review Payments</a>
    </div>
    <div class="col-md-3">
        <a href="<?= e(base_url('admin/reviews.php')) ?>" class="btn btn-outline-secondary w-100 py-3"><i class="fa-solid fa-star me-2"></i>Moderate Reviews</a>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
