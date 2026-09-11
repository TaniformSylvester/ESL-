<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/email.php';

require_guest();

$submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $email = clean_input($_POST['email'] ?? '');
    $rateKey = 'forgot_password:' . ($_SERVER['REMOTE_ADDR'] ?? '');

    if ($email !== '' && validate_email_format($email) && !too_many_attempts($rateKey, 5, 900)) {
        record_attempt($rateKey);

        $rawToken = create_password_reset_token($email);

        if ($rawToken !== null) {
            $stmt = getDB()->prepare('SELECT id, first_name, email FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([strtolower($email)]);
            $user = $stmt->fetch();

            if ($user) {
                $resetLink = base_url('reset-password.php?token=' . $rawToken);
                send_password_reset_email($user, $resetLink);
            }
        }
    }

    // Always show the same message, whether or not the email exists,
    // so this form can't be used to discover registered accounts.
    $submitted = true;
}

$pageTitle = 'Forgot Password';
require_once __DIR__ . '/includes/header.php';
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-5">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4 p-md-5">
                    <h1 class="h3 fw-bold mb-1">Reset Your Password</h1>

                    <?php if ($submitted): ?>
                        <p class="text-secondary mb-0">If an account exists for that email address, we've sent a link to reset your password. Please check your inbox.</p>
                        <p class="mt-4 mb-0"><a href="<?= e(base_url('login.php')) ?>">Back to Login</a></p>
                    <?php else: ?>
                        <p class="text-secondary mb-4">Enter your account email and we'll send you a link to reset your password.</p>

                        <form method="post" action="<?= e(base_url('forgot-password.php')) ?>" novalidate>
                            <?php csrf_field(); ?>
                            <div class="mb-3">
                                <label class="form-label" for="email">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required autofocus>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 py-2">Send Reset Link</button>
                        </form>

                        <p class="text-center text-secondary mt-4 mb-0">
                            <a href="<?= e(base_url('login.php')) ?>">Back to Login</a>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
