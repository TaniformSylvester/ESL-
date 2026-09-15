<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-functions.php';

require_admin();
$user = current_user();

$profileErrors = [];
$passwordErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'profile') {
    require_csrf();

    $result = update_user_profile($user['id'], $_POST);

    if ($result['success']) {
        log_admin_action($user['id'], 'update_own_profile', 'Admin updated their own profile');
        flash_set('success', 'Your profile has been updated.');
        redirect('admin/profile.php');
    }

    $profileErrors = $result['errors'];
    $user = array_merge($user, [
        'first_name'  => clean_input($_POST['first_name'] ?? ''),
        'last_name'   => clean_input($_POST['last_name'] ?? ''),
        'school_name' => clean_input($_POST['school_name'] ?? ''),
        'country'     => clean_input($_POST['country'] ?? ''),
        'phone'       => clean_input($_POST['phone'] ?? ''),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'password') {
    require_csrf();

    $result = change_user_password(
        $user['id'],
        (string)($_POST['current_password'] ?? ''),
        (string)($_POST['new_password'] ?? ''),
        (string)($_POST['confirm_password'] ?? '')
    );

    if ($result['success']) {
        log_admin_action($user['id'], 'change_own_password', 'Admin changed their own password');
        flash_set('success', 'Your password has been changed.');
        redirect('admin/profile.php');
    }

    $passwordErrors = $result['errors'];
}

$pageTitle = 'My Profile';
require_once __DIR__ . '/../includes/admin-header.php';
?>
<div class="row g-4">
    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h2 class="h5 fw-bold mb-3">Account Details</h2>

                <form method="post" action="<?= e(base_url('admin/profile.php')) ?>" novalidate>
                    <?php csrf_field(); ?>
                    <input type="hidden" name="form" value="profile">

                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" value="<?= e($user['email']) ?>" disabled>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="first_name">First Name</label>
                            <input type="text" class="form-control <?= isset($profileErrors['first_name']) ? 'is-invalid' : '' ?>"
                                   id="first_name" name="first_name" value="<?= e($user['first_name']) ?>" required maxlength="100">
                            <?php if (isset($profileErrors['first_name'])): ?><div class="invalid-feedback"><?= e($profileErrors['first_name']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="last_name">Last Name</label>
                            <input type="text" class="form-control <?= isset($profileErrors['last_name']) ? 'is-invalid' : '' ?>"
                                   id="last_name" name="last_name" value="<?= e($user['last_name']) ?>" required maxlength="100">
                            <?php if (isset($profileErrors['last_name'])): ?><div class="invalid-feedback"><?= e($profileErrors['last_name']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="school_name">School Name</label>
                            <input type="text" class="form-control <?= isset($profileErrors['school_name']) ? 'is-invalid' : '' ?>"
                                   id="school_name" name="school_name" value="<?= e($user['school_name'] ?? '') ?>" required maxlength="150">
                            <?php if (isset($profileErrors['school_name'])): ?><div class="invalid-feedback"><?= e($profileErrors['school_name']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="country">Country</label>
                            <input type="text" class="form-control <?= isset($profileErrors['country']) ? 'is-invalid' : '' ?>"
                                   id="country" name="country" value="<?= e($user['country'] ?? '') ?>" required maxlength="100">
                            <?php if (isset($profileErrors['country'])): ?><div class="invalid-feedback"><?= e($profileErrors['country']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="phone">Phone Number <span class="text-secondary">(optional)</span></label>
                            <input type="text" class="form-control <?= isset($profileErrors['phone']) ? 'is-invalid' : '' ?>"
                                   id="phone" name="phone" value="<?= e($user['phone'] ?? '') ?>" maxlength="30">
                            <?php if (isset($profileErrors['phone'])): ?><div class="invalid-feedback"><?= e($profileErrors['phone']) ?></div><?php endif; ?>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary mt-4">Save Changes</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h2 class="h5 fw-bold mb-3">Change Password</h2>

                <form method="post" action="<?= e(base_url('admin/profile.php')) ?>" novalidate>
                    <?php csrf_field(); ?>
                    <input type="hidden" name="form" value="password">

                    <div class="mb-3">
                        <label class="form-label" for="current_password">Current Password</label>
                        <input type="password" class="form-control <?= isset($passwordErrors['current_password']) ? 'is-invalid' : '' ?>"
                               id="current_password" name="current_password" required>
                        <?php if (isset($passwordErrors['current_password'])): ?><div class="invalid-feedback"><?= e($passwordErrors['current_password']) ?></div><?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="new_password">New Password</label>
                        <input type="password" class="form-control <?= isset($passwordErrors['new_password']) ? 'is-invalid' : '' ?>"
                               id="new_password" name="new_password" required minlength="<?= (int)PASSWORD_MIN_LENGTH ?>">
                        <div class="form-text">At least <?= (int)PASSWORD_MIN_LENGTH ?> characters, with a letter and a number.</div>
                        <?php if (isset($passwordErrors['new_password'])): ?><div class="invalid-feedback d-block"><?= e($passwordErrors['new_password']) ?></div><?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="confirm_password">Confirm New Password</label>
                        <input type="password" class="form-control <?= isset($passwordErrors['confirm_password']) ? 'is-invalid' : '' ?>"
                               id="confirm_password" name="confirm_password" required>
                        <?php if (isset($passwordErrors['confirm_password'])): ?><div class="invalid-feedback"><?= e($passwordErrors['confirm_password']) ?></div><?php endif; ?>
                    </div>

                    <button type="submit" class="btn btn-primary">Change Password</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
