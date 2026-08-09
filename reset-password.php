<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth.php';

require_guest();

$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$tokenUser = validate_password_reset_token($token);
$errors = [];

if ($tokenUser && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors['password'] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
    } elseif (!preg_match('/[a-zA-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors['password'] = 'Password must contain at least one letter and one number.';
    }
    if ($password !== $confirm) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        if (consume_password_reset_token($token, $password)) {
            flash_set('success', 'Your password has been reset. Please log in with your new password.');
            redirect('login.php');
        }

        $errors['general'] = 'This reset link is no longer valid. Please request a new one.';
    }
}

$pageTitle = 'Reset Password';
require_once __DIR__ . '/includes/header.php';
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-5">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4 p-md-5">
                    <h1 class="h3 fw-bold mb-1">Choose a New Password</h1>

                    <?php if (!$tokenUser): ?>
                        <p class="text-secondary">This password reset link is invalid or has expired.</p>
                        <p class="mt-4 mb-0"><a href="<?= e(base_url('forgot-password.php')) ?>">Request a new reset link</a></p>
                    <?php else: ?>
                        <p class="text-secondary mb-4">Hi <?= e($tokenUser['first_name']) ?>, enter a new password below.</p>

                        <?php if (!empty($errors['general'])): ?>
                            <div class="alert alert-danger"><?= e($errors['general']) ?></div>
                        <?php endif; ?>

                        <form method="post" action="<?= e(base_url('reset-password.php')) ?>" novalidate>
                            <?php csrf_field(); ?>
                            <input type="hidden" name="token" value="<?= e($token) ?>">

                            <div class="mb-3">
                                <label class="form-label" for="password">New Password</label>
                                <input type="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                                       id="password" name="password" required minlength="<?= (int)PASSWORD_MIN_LENGTH ?>" autofocus>
                                <div class="form-text">At least <?= (int)PASSWORD_MIN_LENGTH ?> characters, with a letter and a number.</div>
                                <?php if (isset($errors['password'])): ?><div class="invalid-feedback d-block"><?= e($errors['password']) ?></div><?php endif; ?>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="confirm_password">Confirm New Password</label>
                                <input type="password" class="form-control <?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>"
                                       id="confirm_password" name="confirm_password" required>
                                <?php if (isset($errors['confirm_password'])): ?><div class="invalid-feedback"><?= e($errors['confirm_password']) ?></div><?php endif; ?>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 py-2">Reset Password</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
