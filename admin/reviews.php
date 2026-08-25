<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-functions.php';
require_once __DIR__ . '/../includes/review-functions.php';

require_admin();
$admin = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $action = (string)($_POST['action'] ?? '');
    $reviewId = (int)($_POST['id'] ?? 0);

    if ($action === 'approve') {
        approve_review($reviewId);
        log_admin_action($admin['id'], 'approve_review', "Approved review #{$reviewId}");
        flash_set('success', 'Review approved.');
    } elseif ($action === 'reject') {
        reject_review($reviewId);
        log_admin_action($admin['id'], 'reject_review', "Rejected/hid review #{$reviewId}");
        flash_set('success', 'Review rejected.');
    } elseif ($action === 'delete') {
        delete_review($reviewId);
        log_admin_action($admin['id'], 'delete_review', "Deleted review #{$reviewId}");
        flash_set('success', 'Review deleted.');
    }

    redirect('admin/reviews.php?' . http_build_query(['status' => $_GET['status'] ?? '', 'rating' => $_GET['rating'] ?? '', 'search' => $_GET['search'] ?? '']));
}

$filters = [
    'status'      => trim((string)($_GET['status'] ?? '')),
    'rating'      => (int)($_GET['rating'] ?? 0),
    'search'      => trim((string)($_GET['search'] ?? '')),
];
$page = max(1, (int)($_GET['page'] ?? 1));
$result = get_all_reviews_paginated($filters, $page, ADMIN_ROWS_PER_PAGE);
$stats = get_review_stats();

$pageTitle = 'Reviews';
require_once __DIR__ . '/../includes/admin-header.php';
?>
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="card shadow-sm border-0 stat-card h-100"><div class="card-body">
            <p class="text-secondary small text-uppercase mb-1">Total Reviews</p>
            <p class="stat-value mb-0"><?= (int)$stats['total'] ?></p>
        </div></div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card shadow-sm border-0 stat-card h-100"><div class="card-body">
            <p class="text-secondary small text-uppercase mb-1">Pending</p>
            <p class="stat-value mb-0 text-warning"><?= (int)$stats['pending'] ?></p>
        </div></div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card shadow-sm border-0 stat-card h-100"><div class="card-body">
            <p class="text-secondary small text-uppercase mb-1">Approved</p>
            <p class="stat-value mb-0 text-success"><?= (int)$stats['approved'] ?></p>
        </div></div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card shadow-sm border-0 stat-card h-100"><div class="card-body">
            <p class="text-secondary small text-uppercase mb-1">Avg. Site Rating</p>
            <p class="stat-value mb-0 text-primary"><?= $stats['approved'] > 0 ? number_format($stats['avg_rating'], 1) : '&mdash;' ?></p>
        </div></div>
    </div>
</div>

<ul class="nav nav-pills mb-3">
    <?php foreach (['' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $value => $label): ?>
        <li class="nav-item">
            <a class="nav-link <?= $filters['status'] === $value ? 'active' : '' ?>"
               href="<?= e(base_url('admin/reviews.php?status=' . $value)) ?>"><?= e($label) ?></a>
        </li>
    <?php endforeach; ?>
</ul>

<form method="get" action="<?= e(base_url('admin/reviews.php')) ?>" class="row g-2 mb-4">
    <input type="hidden" name="status" value="<?= e($filters['status']) ?>">
    <div class="col-md-4">
        <input type="text" name="search" class="form-control" placeholder="Search review text, teacher, resource&hellip;" value="<?= e($filters['search']) ?>">
    </div>
    <div class="col-md-2">
        <select name="rating" class="form-select">
            <option value="">All Ratings</option>
            <?php for ($r = 5; $r >= 1; $r--): ?>
                <option value="<?= $r ?>" <?= $filters['rating'] === $r ? 'selected' : '' ?>><?= $r ?> star<?= $r === 1 ? '' : 's' ?></option>
            <?php endfor; ?>
        </select>
    </div>
    <div class="col-md-2">
        <button type="submit" class="btn btn-outline-secondary">Filter</button>
    </div>
</form>

<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Resource</th>
                    <th>Teacher</th>
                    <th>Rating</th>
                    <th>Review</th>
                    <th>Helpful</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($result['items'])): ?>
                    <tr><td colspan="8" class="text-center text-secondary py-4">No reviews found.</td></tr>
                <?php endif; ?>
                <?php foreach ($result['items'] as $review): ?>
                    <tr>
                        <td class="small">
                            <a href="<?= e(base_url('resource.php?slug=' . urlencode($review['resource_slug']) . '#reviews')) ?>" target="_blank"><?= e($review['resource_title']) ?></a>
                        </td>
                        <td class="small">
                            <?= e($review['first_name'] . ' ' . $review['last_name']) ?>
                            <div class="text-secondary"><?= e($review['email']) ?></div>
                        </td>
                        <td class="text-warning text-nowrap">
                            <?php for ($s = 1; $s <= 5; $s++): ?><i class="fa-<?= $s <= (int)$review['rating'] ? 'solid' : 'regular' ?> fa-star"></i><?php endfor; ?>
                        </td>
                        <td class="small" style="max-width:280px;"><?= e(truncate_text($review['review_text'] ?? '', 140)) ?></td>
                        <td class="small text-center"><?= (int)$review['helpful_count'] ?></td>
                        <td class="small text-secondary"><?= e(format_date($review['created_at'])) ?></td>
                        <td>
                            <span class="badge <?= $review['status'] === 'approved' ? 'bg-success' : ($review['status'] === 'rejected' ? 'bg-danger' : 'bg-warning text-dark') ?>"><?= e(ucfirst($review['status'])) ?></span>
                        </td>
                        <td class="text-nowrap">
                            <?php if ($review['status'] !== 'approved'): ?>
                                <form method="post" action="<?= e(base_url('admin/reviews.php?' . http_build_query($_GET))) ?>" class="d-inline">
                                    <?php csrf_field(); ?>
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="id" value="<?= (int)$review['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-success">Approve</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($review['status'] !== 'rejected'): ?>
                                <form method="post" action="<?= e(base_url('admin/reviews.php?' . http_build_query($_GET))) ?>" class="d-inline">
                                    <?php csrf_field(); ?>
                                    <input type="hidden" name="action" value="reject">
                                    <input type="hidden" name="id" value="<?= (int)$review['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><?= $review['status'] === 'approved' ? 'Hide' : 'Reject' ?></button>
                                </form>
                            <?php endif; ?>
                            <form method="post" action="<?= e(base_url('admin/reviews.php?' . http_build_query($_GET))) ?>" class="d-inline"
                                  onsubmit="return confirm('Permanently delete this review? This cannot be undone.');">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$review['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-secondary">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4"><?= render_pagination($result['page'], $result['total_pages']) ?></div>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
