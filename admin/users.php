<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-functions.php';
require_once __DIR__ . '/../includes/membership.php';

require_admin();

$filters = [
    'search'             => trim((string)($_GET['search'] ?? '')),
    'role'               => trim((string)($_GET['role'] ?? '')),
    'membership_status'  => trim((string)($_GET['membership_status'] ?? '')),
];
$page = max(1, (int)($_GET['page'] ?? 1));
$result = get_users_paginated($filters, $page, ADMIN_ROWS_PER_PAGE);

$pageTitle = 'Users';
require_once __DIR__ . '/../includes/admin-header.php';
?>
<form method="get" action="<?= e(base_url('admin/users.php')) ?>" class="row g-2 mb-4">
    <div class="col-md-4">
        <input type="text" name="search" class="form-control" placeholder="Search name, email, school&hellip;" value="<?= e($filters['search']) ?>">
    </div>
    <div class="col-md-3">
        <select name="role" class="form-select">
            <option value="">All Roles</option>
            <option value="teacher" <?= $filters['role'] === 'teacher' ? 'selected' : '' ?>>Teacher</option>
            <option value="admin" <?= $filters['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
        </select>
    </div>
    <div class="col-md-3">
        <select name="membership_status" class="form-select">
            <option value="">All Membership Statuses</option>
            <option value="active" <?= $filters['membership_status'] === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="pending" <?= $filters['membership_status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
            <option value="expired" <?= $filters['membership_status'] === 'expired' ? 'selected' : '' ?>>Expired</option>
            <option value="cancelled" <?= $filters['membership_status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            <option value="inactive" <?= $filters['membership_status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
    </div>
    <div class="col-md-2 d-grid">
        <button type="submit" class="btn btn-primary">Filter</button>
    </div>
</form>

<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>School</th>
                    <th>Role</th>
                    <th>Membership</th>
                    <th>Joined</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($result['items'])): ?>
                    <tr><td colspan="7" class="text-center text-secondary py-4">No users found.</td></tr>
                <?php endif; ?>
                <?php foreach ($result['items'] as $u): ?>
                    <tr>
                        <td><?= e($u['first_name'] . ' ' . $u['last_name']) ?><?= !$u['is_active'] ? ' <span class="badge bg-secondary">Deactivated</span>' : '' ?></td>
                        <td><?= e($u['email']) ?></td>
                        <td><?= e($u['school_name'] ?? '') ?></td>
                        <td><span class="badge <?= $u['role'] === 'admin' ? 'bg-dark' : 'bg-light text-dark border' ?>"><?= e(ucfirst($u['role'])) ?></span></td>
                        <td>
                            <?php if ($u['membership_status']): ?>
                                <span class="badge <?= e(membership_status_badge_class(['status' => $u['membership_status'], 'expiry_date' => $u['expiry_date']])) ?>">
                                    <?= e(membership_status_label(['status' => $u['membership_status'], 'expiry_date' => $u['expiry_date']])) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-secondary"><?= e(format_date($u['created_at'])) ?></td>
                        <td><a href="<?= e(base_url('admin/user.php?id=' . $u['id'])) ?>" class="btn btn-sm btn-outline-primary">View</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4"><?= render_pagination($result['page'], $result['total_pages']) ?></div>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
