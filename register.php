<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth.php';

require_guest();

$errors = [];
$old = [
    'first_name' => '', 'last_name' => '', 'email' => '',
    'school_name' => '', 'country' => 'Thailand', 'phone' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $result = register_teacher($_POST);

    if ($result['success']) {
        flash_set('success', 'Your account has been created. Please log in to continue.');
        redirect('login.php');
    }

    $errors = $result['errors'];
    foreach ($old as $key => $value) {
        $old[$key] = clean_input($_POST[$key] ?? $value);
    }
}

$pageTitle = 'Register';
$pageDescription = 'Create your free ' . SITE_NAME . ' account.';
require_once __DIR__ . '/includes/header.php';
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4 p-md-5">
                    <h1 class="h3 fw-bold mb-1">Create Your Account</h1>
                    <p class="text-secondary mb-4">Join <?= e(SITE_NAME) ?> to browse and download teaching resources.</p>

                    <?php if (!empty($errors['general'])): ?>
                        <div class="alert alert-danger"><?= e($errors['general']) ?></div>
                    <?php endif; ?>

                    <form method="post" action="<?= e(base_url('register.php')) ?>" novalidate>
                        <?php csrf_field(); ?>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="first_name">First Name</label>
                                <input type="text" class="form-control <?= isset($errors['first_name']) ? 'is-invalid' : '' ?>"
                                       id="first_name" name="first_name" value="<?= e($old['first_name']) ?>" required maxlength="100">
                                <?php if (isset($errors['first_name'])): ?><div class="invalid-feedback"><?= e($errors['first_name']) ?></div><?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="last_name">Last Name</label>
                                <input type="text" class="form-control <?= isset($errors['last_name']) ? 'is-invalid' : '' ?>"
                                       id="last_name" name="last_name" value="<?= e($old['last_name']) ?>" required maxlength="100">
                                <?php if (isset($errors['last_name'])): ?><div class="invalid-feedback"><?= e($errors['last_name']) ?></div><?php endif; ?>
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="email">Email</label>
                                <input type="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                                       id="email" name="email" value="<?= e($old['email']) ?>" required maxlength="190">
                                <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?= e($errors['email']) ?></div><?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="password">Password</label>
                                <input type="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                                       id="password" name="password" required minlength="<?= (int)PASSWORD_MIN_LENGTH ?>">
                                <div class="form-text">At least <?= (int)PASSWORD_MIN_LENGTH ?> characters, with a letter and a number.</div>
                                <?php if (isset($errors['password'])): ?><div class="invalid-feedback d-block"><?= e($errors['password']) ?></div><?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="confirm_password">Confirm Password</label>
                                <input type="password" class="form-control <?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>"
                                       id="confirm_password" name="confirm_password" required>
                                <?php if (isset($errors['confirm_password'])): ?><div class="invalid-feedback"><?= e($errors['confirm_password']) ?></div><?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="school_name">School Name</label>
                                <input type="text" class="form-control <?= isset($errors['school_name']) ? 'is-invalid' : '' ?>"
                                       id="school_name" name="school_name" value="<?= e($old['school_name']) ?>" required maxlength="150">
                                <?php if (isset($errors['school_name'])): ?><div class="invalid-feedback"><?= e($errors['school_name']) ?></div><?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="country">Country</label>
                                <input type="text" class="form-control <?= isset($errors['country']) ? 'is-invalid' : '' ?>"
                                       id="country" name="country" value="<?= e($old['country']) ?>" required maxlength="100">
                                <?php if (isset($errors['country'])): ?><div class="invalid-feedback"><?= e($errors['country']) ?></div><?php endif; ?>
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="phone">Phone Number <span class="text-secondary">(optional)</span></label>
                                <input type="text" class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>"
                                       id="phone" name="phone" value="<?= e($old['phone']) ?>" maxlength="30">
                                <?php if (isset($errors['phone'])): ?><div class="invalid-feedback"><?= e($errors['phone']) ?></div><?php endif; ?>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 mt-4 py-2">Create Account</button>
                    </form>

                    <p class="text-center text-secondary mt-4 mb-0">
                        Already have an account? <a href="<?= e(base_url('login.php')) ?>">Log in</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
