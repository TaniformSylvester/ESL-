<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/membership.php';
require_once __DIR__ . '/../includes/payment-functions.php';
require_once __DIR__ . '/../includes/download-functions.php';

require_login();
$user = current_user();
$selectedPlan = in_array($_GET['plan'] ?? '', ['monthly', 'annual'], true) ? $_GET['plan'] : 'monthly';

$membership = get_membership($user['id']) ?? ['status' => 'inactive', 'expiry_date' => null];
$isActive = isMemberActive($user['id']);
$payments = get_user_payments($user['id']);

$pageTitle = 'Subscription';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="container py-5">
    <h1 class="fw-bold mb-1"><?= e(SITE_NAME) ?> Membership</h1>
    <p class="text-secondary mb-4">Teacher Pro: <?= format_currency(PRICE_MONTHLY) ?>/month or <?= format_currency(PRICE_ANNUAL) ?>/year</p>

    <?php if (($_GET['stripe'] ?? '') === 'success'): ?>
        <div class="alert alert-success alert-dismissible fade show">
            Payment received! We're confirming it with Stripe now — your membership will show as active within a few seconds. Refresh this page if it hasn't updated yet.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif (($_GET['stripe'] ?? '') === 'cancelled'): ?>
        <div class="alert alert-warning alert-dismissible fade show">
            Checkout was cancelled — no payment was made. Feel free to try again below.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <?php if ($isActive): ?>
                    <span class="badge bg-success mb-2">⭐ Teacher Pro<?= !empty($membership['plan']) ? ' (' . e(ucfirst($membership['plan'])) . ')' : '' ?></span>
                    <p class="mb-0">Unlimited downloads. Active until <strong><?= e(format_date($membership['expiry_date'])) ?></strong>
                        (<?= (int)membership_days_remaining($membership) ?> days remaining)</p>
                <?php else: ?>
                    <span class="badge <?= e(membership_status_badge_class($membership)) ?> mb-2">Free Plan<?= $membership['status'] === 'pending' ? ' &mdash; Payment Pending' : '' ?></span>
                    <?php if ($membership['status'] === 'pending'): ?>
                        <p class="mb-0">Your payment is awaiting approval. This usually takes less than a day.</p>
                    <?php else: ?>
                        <p class="mb-1">You're on the Free plan. You can download every free resource, unlimited, no restrictions.</p>
                        <p class="mb-0 text-secondary small">Upgrade to Teacher Pro below to also unlock members-only resources.</p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (STRIPE_ENABLED): ?>
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <h2 class="h5 fw-bold mb-1">Upgrade to Teacher Pro</h2>
                <p class="text-secondary mb-4">Pay securely by card or scan to pay with PromptPay — handled entirely by Stripe. Your membership activates automatically the moment payment is confirmed, no waiting for approval.</p>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100 d-flex flex-column <?= $selectedPlan !== 'annual' ? 'border-primary' : '' ?>">
                            <p class="fw-bold mb-1">Monthly</p>
                            <p class="h4 fw-bold mb-3"><?= format_currency(PRICE_MONTHLY) ?><span class="fs-6 fw-normal text-secondary">/month</span></p>
                            <form method="post" action="<?= e(base_url('member/stripe-checkout.php')) ?>" class="mt-auto">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="plan" value="monthly">
                                <button type="submit" class="btn btn-primary w-100">Pay with Stripe</button>
                            </form>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100 d-flex flex-column <?= $selectedPlan === 'annual' ? 'border-primary' : '' ?>">
                            <p class="fw-bold mb-1">Annual <span class="badge bg-warning text-dark">Best Value</span></p>
                            <p class="h4 fw-bold mb-3"><?= format_currency(PRICE_ANNUAL) ?><span class="fs-6 fw-normal text-secondary">/year</span></p>
                            <form method="post" action="<?= e(base_url('member/stripe-checkout.php')) ?>" class="mt-auto">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="plan" value="annual">
                                <button type="submit" class="btn btn-primary w-100">Pay with Stripe</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-warning">
            Card and PromptPay payment is temporarily unavailable. Please contact <a href="mailto:<?= e(CONTACT_EMAIL) ?>"><?= e(CONTACT_EMAIL) ?></a> to upgrade your membership.
        </div>
    <?php endif; ?>

    <?php if (!empty($payments)): ?>
        <h2 class="h5 fw-bold mt-5 mb-3">Payment History</h2>
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Plan</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Reference</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><?= e(format_date($payment['payment_date'])) ?></td>
                            <td class="small"><?= e(ucfirst($payment['plan'] ?? 'monthly')) ?></td>
                            <td><?= format_payment_amount($payment) ?></td>
                            <td><?= e(payment_method_label($payment['method'])) ?></td>
                            <td><?= e($payment['reference_number']) ?></td>
                            <td>
                                <span class="badge <?= e(payment_status_badge_class($payment['status'])) ?>"><?= e(ucfirst($payment['status'])) ?></span>
                                <?php if ($payment['status'] === 'rejected' && !empty($payment['admin_note'])): ?>
                                    <div class="small text-secondary mt-1"><?= e($payment['admin_note']) ?></div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
