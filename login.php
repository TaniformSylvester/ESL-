<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth.php';

require_guest();

$error = null;
$emailOld = '';
$redirectPath = safe_internal_path($_GET['redirect'] ?? null, 'dashboard.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $email = (string)($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $emailOld = clean_input($email);
    $redirectPath = safe_internal_path($_POST['redirect'] ?? null, 'dashboard.php');

    if (too_many_attempts('login_form:' . ($_SERVER['REMOTE_ADDR'] ?? ''), 10, 300)) {
        $error = 'Too many login attempts from this connection. Please wait a few minutes and try again.';
    } else {
        record_attempt('login_form:' . ($_SERVER['REMOTE_ADDR'] ?? ''));
        $result = attempt_login($email, $password);

        if ($result['success']) {
            login_user_session($result['user']);
            flash_set('success', 'Welcome back, ' . $result['user']['first_name'] . '!');
            redirect($redirectPath);
        }

        $error = $result['error'];
    }
}

$pageTitle = 'Login';
$pageDescription = 'Log in to your ' . SITE_NAME . ' account.';
require_once __DIR__ . '/includes/header.php';
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-5">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4 p-md-5">
                    <h1 class="h3 fw-bold mb-1">Welcome Back</h1>
                    <p class="text-secondary mb-4">Log in to access your teaching resources.</p>

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= e($error) ?></div>
                    <?php endif; ?>

                    <?php if (GOOGLE_ENABLED): ?>
                        <a href="<?= e(base_url('google-login.php?redirect=' . urlencode($redirectPath))) ?>" class="btn btn-outline-secondary w-100 py-2 mb-3 d-flex align-items-center justify-content-center gap-2">
                            <i class="fa-brands fa-google"></i> Continue with Google
                        </a>
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <hr class="flex-grow-1"><span class="text-secondary small">or</span><hr class="flex-grow-1">
                        </div>
                    <?php endif; ?>

                    <form method="post" action="<?= e(base_url('login.php')) ?>" novalidate>
                        <?php csrf_field(); ?>
                        <input type="hidden" name="redirect" value="<?= e($redirectPath) ?>">

                        <div class="mb-3">
                            <label class="form-label" for="email">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= e($emailOld) ?>" required autofocus>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="password">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>

                        <div class="d-flex justify-content-end mb-3">
                            <a class="small" href="<?= e(base_url('forgot-password.php')) ?>">Forgot password?</a>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2">Log In</button>
                    </form>

                    <p class="text-center text-secondary mt-4 mb-0">
                        Don't have an account? <a href="<?= e(base_url('register.php')) ?>">Join Now</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
