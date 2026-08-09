<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-functions.php';

require_admin();
$stats = get_dashboard_stats();

$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/admin-header.php';
?>
<div class="row g-3">
    <div class="col-sm-6 col-lg-3">
        <div class="card shadow-sm border-0 stat-card h-100">
            <div class="card-body">
                <p class="text-secondary small text-uppercase mb-1">Total Users</p>
                <p class="stat-value mb-0"><?= (int)$stats['total_users'] ?></p>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card shadow-sm border-0 stat-card h-100">
            <div class="card-body">
                <p class="text-secondary small text-uppercase mb-1">Active Members</p>
                <p class="stat-value mb-0 text-success"><?= (int)$stats['active_members'] ?></p>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card shadow-sm border-0 stat-card h-100">
            <div class="card-body">
                <p class="text-secondary small text-uppercase mb-1">Expired Members</p>
                <p class="stat-value mb-0 text-danger"><?= (int)$stats['expired_members'] ?></p>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card shadow-sm border-0 stat-card h-100">
            <div class="card-body">
                <p class="text-secondary small text-uppercase mb-1">Pending Payments</p>
                <p class="stat-value mb-0 text-warning"><?= (int)$stats['pending_payments'] ?></p>
                <?php if ($stats['pending_payments'] > 0): ?>
                    <a href="<?= e(base_url('admin/payments.php')) ?>" class="small">Review &rarr;</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card shadow-sm border-0 stat-card h-100">
            <div class="card-body">
                <p class="text-secondary small text-uppercase mb-1">Total Resources</p>
                <p class="stat-value mb-0"><?= (int)$stats['total_resources'] ?></p>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card shadow-sm border-0 stat-card h-100">
            <div class="card-body">
                <p class="text-secondary small text-uppercase mb-1">Total Downloads</p>
                <p class="stat-value mb-0"><?= (int)$stats['total_downloads'] ?></p>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card shadow-sm border-0 stat-card h-100">
            <div class="card-body">
                <p class="text-secondary small text-uppercase mb-1">Added This Month</p>
                <p class="stat-value mb-0"><?= (int)$stats['resources_this_month'] ?></p>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card shadow-sm border-0 stat-card h-100">
            <div class="card-body">
                <p class="text-secondary small text-uppercase mb-1">Est. Monthly Revenue</p>
                <p class="stat-value mb-0 text-primary"><?= format_currency($stats['monthly_revenue']) ?></p>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4 g-3">
    <div class="col-md-4">
        <a href="<?= e(base_url('admin/users.php')) ?>" class="btn btn-outline-secondary w-100 py-3"><i class="fa-solid fa-users me-2"></i>Manage Users</a>
    </div>
    <div class="col-md-4">
        <a href="<?= e(base_url('admin/categories.php')) ?>" class="btn btn-outline-secondary w-100 py-3"><i class="fa-solid fa-tags me-2"></i>Manage Categories</a>
    </div>
    <div class="col-md-4">
        <a href="<?= e(base_url('admin/payments.php')) ?>" class="btn btn-outline-secondary w-100 py-3"><i class="fa-solid fa-money-bill me-2"></i>Review Payments</a>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
