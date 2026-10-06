<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-functions.php';
require_once __DIR__ . '/../includes/review-functions.php';
require_once __DIR__ . '/../includes/subject-functions.php';
require_once __DIR__ . '/../includes/games-functions.php';

require_admin();
$stats = get_dashboard_stats();
$mostDownloaded = get_most_downloaded_resources(5);
$mostActiveUsers = get_most_active_users(5);
$reviewStats = get_review_stats();
$topRatedResources = get_top_rated_resources(5);
$mostReviewedResources = get_most_reviewed_resources(5);
$downloadsBySubjectGrade = get_downloads_by_subject_grade();
$topTopics = get_top_topics_by_downloads(10);
$totalGamePlays = get_total_game_plays();
$gamePlaysThisMonth = get_game_plays_this_month();
$gamePlayStats = get_game_play_stats();

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

<h2 class="h6 fw-bold text-secondary text-uppercase mb-3">Downloads by Subject &amp; Grade</h2>
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <p class="small text-secondary mb-3">Every grade each subject covers, with real published-resource and download counts — a highlighted 0 means that grade has no published content yet, a different problem from a grade that has content but few downloads.</p>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Grade</th>
                        <th class="text-end">Resources</th>
                        <th class="text-end">Total Downloads</th>
                        <th class="text-end">Avg / Resource</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($downloadsBySubjectGrade as $row): ?>
                        <?php $avgDownloads = $row['resource_count'] > 0 ? $row['total_downloads'] / $row['resource_count'] : 0; ?>
                        <tr<?= $row['resource_count'] === 0 ? ' class="table-warning"' : '' ?>>
                            <td><?= e($row['subject_name']) ?></td>
                            <td><?= e($row['grade_level']) ?></td>
                            <td class="text-end"><?= (int)$row['resource_count'] ?></td>
                            <td class="text-end"><?= (int)$row['total_downloads'] ?></td>
                            <td class="text-end"><?= $row['resource_count'] > 0 ? number_format($avgDownloads, 1) : '&mdash;' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<h2 class="h6 fw-bold text-secondary text-uppercase mb-3">Top Topics by Downloads</h2>
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <?php if (empty($topTopics)): ?>
            <p class="text-secondary small mb-0">No downloads recorded yet.</p>
        <?php else: ?>
            <ol class="mb-0 ps-3">
                <?php foreach ($topTopics as $topic): ?>
                    <li class="small mb-1">
                        <span class="fw-bold"><?= e($topic['topic']) ?></span>
                        <span class="text-secondary">(<?= e($topic['subject_name']) ?>)</span>
                        &mdash; <?= (int)$topic['total_downloads'] ?> download<?= (int)$topic['total_downloads'] === 1 ? '' : 's' ?>
                        across <?= (int)$topic['resource_count'] ?> resource<?= (int)$topic['resource_count'] === 1 ? '' : 's' ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </div>
</div>

<h2 class="h6 fw-bold text-secondary text-uppercase mb-3">Games</h2>
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="card shadow-sm border-0 stat-card h-100"><div class="card-body">
            <p class="text-secondary small text-uppercase mb-1">Total Plays</p>
            <p class="stat-value mb-0"><?= (int)$totalGamePlays ?></p>
        </div></div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card shadow-sm border-0 stat-card h-100"><div class="card-body">
            <p class="text-secondary small text-uppercase mb-1">Plays This Month</p>
            <p class="stat-value mb-0"><?= (int)$gamePlaysThisMonth ?></p>
        </div></div>
    </div>
</div>
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <p class="small text-secondary mb-3">"Played" counts a real Start Game click, not just a page view of the game's landing page. "Completed" is how many of those plays were finished rather than abandoned partway through.</p>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Game</th>
                        <th>Subject</th>
                        <th class="text-end">Played</th>
                        <th class="text-end">Completed</th>
                        <th class="text-end">Completion Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($gamePlayStats as $row): ?>
                        <?php $completionRate = $row['started'] > 0 ? ($row['completed'] / $row['started']) * 100 : 0; ?>
                        <tr<?= $row['started'] === 0 ? ' class="table-warning"' : '' ?>>
                            <td><a href="<?= e(base_url('game.php?slug=' . urlencode($row['slug']))) ?>" target="_blank"><?= e($row['title']) ?></a></td>
                            <td><?= e($row['subject']) ?></td>
                            <td class="text-end"><?= (int)$row['started'] ?></td>
                            <td class="text-end"><?= (int)$row['completed'] ?></td>
                            <td class="text-end"><?= $row['started'] > 0 ? number_format($completionRate, 0) . '%' : '&mdash;' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
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
