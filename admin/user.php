<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-functions.php';
require_once __DIR__ . '/../includes/membership.php';
require_once __DIR__ . '/../includes/download-functions.php';

require_admin();
$admin = current_user();

$userId = (int)($_GET['id'] ?? 0);
$user = $userId > 0 ? get_user_by_id($userId) : null;

if (!$user) {
    http_response_code(404);
    require __DIR__ . '/../404.php';
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'update_profile') {
        $result = update_user_profile($userId, $_POST);
        if ($result['success']) {
            log_admin_action($admin['id'], 'update_user_profile', "Updated profile for user #{$userId}");
            flash_set('success', 'User profile updated.');
            redirect('admin/user.php?id=' . $userId);
        }
        $errors = $result['errors'];
    } elseif ($action === 'set_role') {
        $newRole = (string)($_POST['role'] ?? '');
        if ($userId === $admin['id'] && $newRole !== 'admin') {
            flash_set('error', "You can't remove your own admin role.");
        } elseif (update_user_role($userId, $newRole)) {
            log_admin_action($admin['id'], 'set_user_role', "Set user #{$userId} role to {$newRole}");
            flash_set('success', 'User role updated.');
        }
        redirect('admin/user.php?id=' . $userId);
    } elseif ($action === 'toggle_active') {
        if ($userId === $admin['id']) {
            flash_set('error', "You can't deactivate your own account.");
        } else {
            $newActive = !$user['is_active'];
            set_user_active($userId, $newActive);
            log_admin_action($admin['id'], $newActive ? 'activate_user' : 'deactivate_user', "User #{$userId}");
            flash_set('success', $newActive ? 'User activated.' : 'User deactivated.');
        }
        redirect('admin/user.php?id=' . $userId);
    } elseif ($action === 'extend_membership') {
        $months = max(1, min(12, (int)($_POST['months'] ?? 1)));
        extend_membership($userId, $months);
        log_admin_action($admin['id'], 'extend_membership', "Extended user #{$userId} membership by {$months} month(s)");
        flash_set('success', 'Membership extended.');
        redirect('admin/user.php?id=' . $userId);
    } elseif ($action === 'reset_membership') {
        reset_membership($userId);
        log_admin_action($admin['id'], 'reset_membership', "Reset user #{$userId} membership");
        flash_set('success', 'Membership reset to inactive.');
        redirect('admin/user.php?id=' . $userId);
    } elseif ($action === 'cancel_membership') {
        cancel_membership($userId);
        log_admin_action($admin['id'], 'cancel_membership', "Cancelled user #{$userId} membership");
        flash_set('success', 'Membership cancelled.');
        redirect('admin/user.php?id=' . $userId);
    }

    $user = get_user_by_id($userId);
}

$membership = ['status' => $user['membership_status'] ?? 'inactive', 'expiry_date' => $user['membership_expiry'] ?? null];
$downloadHistory = get_user_downloads($userId, 1, 10);

$pageTitle = 'User: ' . $user['first_name'] . ' ' . $user['last_name'];
require_once __DIR__ . '/../includes/admin-header.php';
?>
<p><a href="<?= e(base_url('admin/users.php')) ?>" class="small"><i class="fa-solid fa-arrow-left me-1"></i>Back to Users</a></p>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h2 class="h5 fw-bold mb-3">Profile</h2>

                <form method="post" action="<?= e(base_url('admin/user.php?id=' . $userId)) ?>" novalidate>
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="update_profile">

                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" value="<?= e($user['email']) ?>" disabled>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="first_name">First Name</label>
                            <input type="text" class="form-control <?= isset($errors['first_name']) ? 'is-invalid' : '' ?>"
                                   id="first_name" name="first_name" value="<?= e($user['first_name']) ?>" required maxlength="100">
                            <?php if (isset($errors['first_name'])): ?><div class="invalid-feedback"><?= e($errors['first_name']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="last_name">Last Name</label>
                            <input type="text" class="form-control <?= isset($errors['last_name']) ? 'is-invalid' : '' ?>"
                                   id="last_name" name="last_name" value="<?= e($user['last_name']) ?>" required maxlength="100">
                            <?php if (isset($errors['last_name'])): ?><div class="invalid-feedback"><?= e($errors['last_name']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="school_name">School Name</label>
                            <input type="text" class="form-control <?= isset($errors['school_name']) ? 'is-invalid' : '' ?>"
                                   id="school_name" name="school_name" value="<?= e($user['school_name'] ?? '') ?>" required maxlength="150">
                            <?php if (isset($errors['school_name'])): ?><div class="invalid-feedback"><?= e($errors['school_name']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="country">Country</label>
                            <input type="text" class="form-control <?= isset($errors['country']) ? 'is-invalid' : '' ?>"
                                   id="country" name="country" value="<?= e($user['country'] ?? '') ?>" required maxlength="100">
                            <?php if (isset($errors['country'])): ?><div class="invalid-feedback"><?= e($errors['country']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="phone">Phone</label>
                            <input type="text" class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>"
                                   id="phone" name="phone" value="<?= e($user['phone'] ?? '') ?>" maxlength="30">
                            <?php if (isset($errors['phone'])): ?><div class="invalid-feedback"><?= e($errors['phone']) ?></div><?php endif; ?>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary mt-3">Save Changes</button>
                </form>
            </div>
        </div>

        <div class="card shadow-sm border-0 mt-4">
            <div class="card-body">
                <h2 class="h5 fw-bold mb-3">Account</h2>

                <p class="mb-2">Role:
                    <span class="badge <?= $user['role'] === 'admin' ? 'bg-dark' : 'bg-light text-dark border' ?>"><?= e(ucfirst($user['role'])) ?></span>
                </p>
                <form method="post" action="<?= e(base_url('admin/user.php?id=' . $userId)) ?>" class="d-flex gap-2 mb-3">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="set_role">
                    <select name="role" class="form-select form-select-sm w-auto">
                        <option value="teacher" <?= $user['role'] === 'teacher' ? 'selected' : '' ?>>Teacher</option>
                        <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                    </select>
                    <button type="submit" class="btn btn-sm btn-outline-secondary">Update Role</button>
                </form>

                <p class="mb-2">Status:
                    <span class="badge <?= $user['is_active'] ? 'bg-success' : 'bg-secondary' ?>"><?= $user['is_active'] ? 'Active' : 'Deactivated' ?></span>
                </p>
                <form method="post" action="<?= e(base_url('admin/user.php?id=' . $userId)) ?>"
                      onsubmit="return confirm('<?= $user['is_active'] ? 'Deactivate' : 'Activate' ?> this account?');">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="toggle_active">
                    <button type="submit" class="btn btn-sm btn-outline-<?= $user['is_active'] ? 'danger' : 'success' ?>">
                        <?= $user['is_active'] ? 'Deactivate Account' : 'Activate Account' ?>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h2 class="h5 fw-bold mb-3">Membership</h2>

                <p class="mb-3">
                    <span class="badge <?= e(membership_status_badge_class($membership)) ?>"><?= e(membership_status_label($membership)) ?></span>
                    <?php if (!empty($membership['expiry_date'])): ?>
                        <span class="small text-secondary ms-2">Expires <?= e(format_date($membership['expiry_date'])) ?></span>
                    <?php endif; ?>
                </p>

                <form method="post" action="<?= e(base_url('admin/user.php?id=' . $userId)) ?>" class="d-flex gap-2 mb-2">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="extend_membership">
                    <select name="months" class="form-select form-select-sm w-auto">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>"><?= $m ?> month<?= $m > 1 ? 's' : '' ?></option>
                        <?php endfor; ?>
                    </select>
                    <button type="submit" class="btn btn-sm btn-success">Extend Membership</button>
                </form>

                <form method="post" action="<?= e(base_url('admin/user.php?id=' . $userId)) ?>" class="d-inline"
                      onsubmit="return confirm('Cancel this membership?');">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="cancel_membership">
                    <button type="submit" class="btn btn-sm btn-outline-secondary">Cancel Membership</button>
                </form>
                <form method="post" action="<?= e(base_url('admin/user.php?id=' . $userId)) ?>" class="d-inline"
                      onsubmit="return confirm('Reset this membership to inactive? This clears their dates.');">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="reset_membership">
                    <button type="submit" class="btn btn-sm btn-outline-danger">Reset Membership</button>
                </form>
            </div>
        </div>

        <div class="card shadow-sm border-0 mt-4">
            <div class="card-body">
                <h2 class="h5 fw-bold mb-3">Download History</h2>
                <?php if (empty($downloadHistory['items'])): ?>
                    <p class="text-secondary small mb-0">No downloads yet.</p>
                <?php else: ?>
                    <ul class="list-unstyled small mb-0">
                        <?php foreach ($downloadHistory['items'] as $download): ?>
                            <li class="mb-1 d-flex justify-content-between border-bottom pb-1">
                                <span><?= e($download['title']) ?></span>
                                <span class="text-secondary"><?= e(format_date($download['downloaded_at'], 'd M Y, g:i a')) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if ($downloadHistory['total'] > 10): ?>
                        <p class="text-secondary small mt-2 mb-0">Showing the 10 most recent of <?= (int)$downloadHistory['total'] ?> downloads.</p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
