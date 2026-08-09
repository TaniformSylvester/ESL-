<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth.php';

$isLoggedIn = is_logged_in();

$pageTitle = 'Pricing';
$pageDescription = 'Simple, affordable ' . SITE_NAME . ' membership pricing: ' . format_currency(SUBSCRIPTION_PRICE) . '/month.';
require_once __DIR__ . '/includes/header.php';
?>
<div class="container py-5">
    <div class="text-center mb-5">
        <h1 class="fw-bold">Simple, Affordable Pricing</h1>
        <p class="lead text-secondary">One plan. Full access. No hidden fees.</p>
    </div>

    <div class="row justify-content-center mb-5">
        <div class="col-lg-5">
            <div class="card border-primary shadow-sm">
                <div class="card-body p-4 p-md-5 text-center">
                    <h2 class="h5 fw-bold text-uppercase text-secondary">Teacher Membership</h2>
                    <p class="display-4 fw-bold text-primary mb-0"><?= format_currency(SUBSCRIPTION_PRICE) ?></p>
                    <p class="text-secondary mb-4">per <?= e(SUBSCRIPTION_PERIOD_LABEL) ?></p>

                    <ul class="list-unstyled text-start mb-4">
                        <li class="mb-2"><i class="fa-solid fa-check text-success me-2"></i>Unlimited access to member resources</li>
                        <li class="mb-2"><i class="fa-solid fa-check text-success me-2"></i>Download lesson plans</li>
                        <li class="mb-2"><i class="fa-solid fa-check text-success me-2"></i>Download worksheets</li>
                        <li class="mb-2"><i class="fa-solid fa-check text-success me-2"></i>Download PowerPoints</li>
                        <li class="mb-2"><i class="fa-solid fa-check text-success me-2"></i>Download classroom activities</li>
                        <li class="mb-2"><i class="fa-solid fa-check text-success me-2"></i>Download games</li>
                        <li class="mb-2"><i class="fa-solid fa-check text-success me-2"></i>Download assessments</li>
                        <li class="mb-2"><i class="fa-solid fa-check text-success me-2"></i>New resources added regularly</li>
                    </ul>

                    <?php if ($isLoggedIn): ?>
                        <a href="<?= e(base_url('dashboard.php')) ?>" class="btn btn-primary w-100 py-2">Go to Dashboard</a>
                    <?php else: ?>
                        <a href="<?= e(base_url('register.php')) ?>" class="btn btn-primary w-100 py-2">Join Now</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <h2 class="h4 fw-bold mb-3">How Membership Works Right Now</h2>
            <p>Membership currently renews on a manual monthly basis. Here's the process:</p>
            <ol>
                <li class="mb-2">Register for a free account and log in.</li>
                <li class="mb-2">Go to your <strong>Subscription</strong> page and review the payment instructions (bank transfer or PromptPay).</li>
                <li class="mb-2">Send your payment, then submit the amount, date, and reference number.</li>
                <li class="mb-2">Our team reviews and approves your payment &mdash; usually within a day &mdash; and your membership becomes active for one month from approval.</li>
                <li class="mb-2">Repeat each month to keep your access active.</li>
            </ol>
            <p class="text-secondary small">We're working on automatic monthly billing so this process will eventually be instant &mdash; for now, manual approval keeps things simple and keeps costs (and your price) low.</p>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
