<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth.php';

require_login();
$user = current_user();

$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
?>
<div class="container py-5">
    <h1 class="h3 fw-bold mb-1">Welcome, <?= e($user['first_name']) ?>!</h1>
    <p class="text-secondary mb-4">This is your <?= e(SITE_NAME) ?> dashboard.</p>

    <div class="alert alert-info">
        Membership status, resource browsing, favorites, and downloads are being built in upcoming
        stages. For now, your account is set up and you're securely logged in.
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <h2 class="h6 fw-bold">Account</h2>
            <dl class="row mb-0 small">
                <dt class="col-sm-3">Name</dt>
                <dd class="col-sm-9"><?= e($user['first_name'] . ' ' . $user['last_name']) ?></dd>
                <dt class="col-sm-3">Email</dt>
                <dd class="col-sm-9"><?= e($user['email']) ?></dd>
                <dt class="col-sm-3">School</dt>
                <dd class="col-sm-9"><?= e($user['school_name'] ?? '') ?></dd>
                <dt class="col-sm-3">Country</dt>
                <dd class="col-sm-9"><?= e($user['country'] ?? '') ?></dd>
            </dl>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
