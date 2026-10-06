<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-functions.php';
require_once __DIR__ . '/../includes/membership.php';

require_admin();

$filters = [
    'membership_status' => trim((string)($_GET['status'] ?? '')),
    'search'             => trim((string)($_GET['search'] ?? '')),
];
$page = max(1, (int)($_GET['page'] ?? 1));
$result = get_users_paginated($filters, $page, ADMIN_ROWS_PER_PAGE);

$pageTitle = 'Subscriptions';
require_once __DIR__ . '/../includes/admin-header.php';
?>
<ul class="nav nav-pills mb-4">
    <?php foreach (['' => 'All', 'active' => 'Active', 'pending' => 'Pending', 'expired' => 'Expired', 'cancelled' => 'Cancelled', 'inactive' => 'Inactive'] as $value => $label): ?>
        <li class="nav-item">
            <a class="nav-link <?= $filters['membership_status'] === $value ? 'active' : '' ?>"
               href="<?= e(base_url('admin/subscriptions.php?status=' . $value)) ?>"><?= e($label) ?></a>
        </li>
    <?php endforeach; ?>
</ul>

<form method="get" action="<?= e(base_url('admin/subscriptions.php')) ?>" class="row g-2 mb-4">
    <input type="hidden" name="status" value="<?= e($filters['membership_status']) ?>">
    <div class="col-md-4">
        <input type="text" name="search" class="form-control" placeholder="Search name, email, school&hellip;" value="<?= e($filters['search']) ?>">
    </div>
    <div class="col-md-2">
        <button type="submit" class="btn btn-outline-secondary">Search</button>
    </div>
</form>

<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Teacher</th>
                    <th>Status</th>
                    <th>Plan</th>
                    <th>Started</th>
                    <th>Expires</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($result['items'])): ?>
                    <tr><td colspan="6" class="text-center text-secondary py-4">No members found.</td></tr>
                <?php endif; ?>
                <?php foreach ($result['items'] as $u): ?>
                    <?php $membership = ['status' => $u['membership_status'] ?? 'inactive', 'expiry_date' => $u['expiry_date'] ?? null]; ?>
                    <tr>
                        <td>
                            <?= e($u['first_name'] . ' ' . $u['last_name']) ?>
                            <div class="small text-secondary"><?= e($u['email']) ?></div>
                        </td>
                        <td><span class="badge <?= e(membership_status_badge_class($membership)) ?>"><?= e(membership_status_label($membership)) ?></span></td>
                        <td class="small text-secondary"><?= !empty($u['plan']) ? e(ucfirst($u['plan'])) : '&mdash;' ?></td>
                        <td class="small text-secondary"><?= !empty($u['start_date']) ? e(format_date($u['start_date'])) : '&mdash;' ?></td>
                        <td class="small text-secondary"><?= !empty($u['expiry_date']) ? e(format_date($u['expiry_date'])) : '&mdash;' ?></td>
                        <td><a href="<?= e(base_url('admin/user.php?id=' . $u['id'])) ?>" class="btn btn-sm btn-outline-primary">Manage</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4"><?= render_pagination($result['page'], $result['total_pages']) ?></div>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
