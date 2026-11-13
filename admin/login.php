<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';

if (is_logged_in()) {
    $user = current_user();
    redirect($user && $user['role'] === 'admin' ? 'admin/index.php' : 'dashboard.php');
}

$error = null;
$emailOld = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $email = (string)($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $emailOld = clean_input($email);

    if (too_many_attempts('admin_login_form:' . ($_SERVER['REMOTE_ADDR'] ?? ''), 10, 300)) {
        $error = 'Too many login attempts from this connection. Please wait a few minutes and try again.';
    } else {
        record_attempt('admin_login_form:' . ($_SERVER['REMOTE_ADDR'] ?? ''));
        $result = attempt_login($email, $password);

        // Deliberately generic: a teacher account entering correct
        // credentials here must see the same error as a wrong password,
        // so this form can't be used to fingerprint which accounts are admins.
        if ($result['success'] && $result['user']['role'] === 'admin') {
            login_user_session($result['user']);
            redirect('admin/index.php');
        }

        $error = 'Incorrect email or password.';
    }
}

$pageTitle = 'Admin Login';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-5">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4 p-md-5">
                    <h1 class="h3 fw-bold mb-1"><i class="fa-solid fa-lock me-1"></i> Admin Login</h1>
                    <p class="text-secondary mb-4">Restricted to <?= e(SITE_NAME) ?> administrators.</p>

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= e($error) ?></div>
                    <?php endif; ?>

                    <form method="post" action="<?= e(base_url('admin/login.php')) ?>" novalidate>
                        <?php csrf_field(); ?>

                        <div class="mb-3">
                            <label class="form-label" for="email">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= e($emailOld) ?>" required autofocus>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="password">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>

                        <button type="submit" class="btn btn-dark w-100 py-2">Log In</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
