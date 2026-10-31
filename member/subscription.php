<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/membership.php';
require_once __DIR__ . '/../includes/payment-functions.php';
require_once __DIR__ . '/../includes/download-functions.php';
require_once __DIR__ . '/../includes/upload-functions.php';
require_once __DIR__ . '/../includes/promptpay-functions.php';
require_once __DIR__ . '/../includes/email.php';

require_login();
$user = current_user();
$selectedPlan = in_array($_GET['plan'] ?? '', ['monthly', 'annual'], true) ? $_GET['plan'] : 'monthly';
$scanToPay = scan_to_pay_config();
$scanErrors = [];
$scanOld = [];

// "Scan to pay" slip submission. A file bigger than PHP's post_max_size
// arrives with $_POST empty (no CSRF token either), so catch that first
// and explain it, rather than failing with the generic expired-form message.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    flash_set('error', 'That file was too large to upload. Please choose a smaller photo or a screenshot of your slip.');
    redirect('member/subscription.php?pay=scan#scan-to-pay');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'scan_payment' && $scanToPay) {
    require_csrf();

    $rateKey = 'scan_payment:' . $user['id'];
    if (too_many_attempts($rateKey, 5, 3600)) {
        flash_set('error', 'Too many submissions. Please wait a while, or contact us if you need help.');
        redirect('member/subscription.php');
    }
    record_attempt($rateKey);

    $result = submit_payment((int)$user['id'], [
        'plan'             => $_POST['plan'] ?? 'monthly',
        'method'           => 'promptpay',
        'payment_date'     => $_POST['payment_date'] ?? '',
        'reference_number' => $_POST['reference_number'] ?? '',
    ], $_FILES['screenshot'] ?? []);

    if ($result['success']) {
        send_admin_new_payment_email($user, $result['payment']);
        send_payment_submitted_email($user, $result['payment']);
        flash_set('success', "Thanks! We've received your payment slip. We'll check it and activate Teacher Pro, usually within a few hours, and email you when it's done.");
        redirect('member/subscription.php');
    }

    $scanErrors = $result['errors'];
    $scanOld = [
        'plan'             => ($_POST['plan'] ?? '') === 'annual' ? 'annual' : 'monthly',
        'payment_date'     => (string)($_POST['payment_date'] ?? ''),
        'reference_number' => (string)($_POST['reference_number'] ?? ''),
    ];
    $selectedPlan = $scanOld['plan'];
}

$membership = get_membership($user['id']) ?? ['status' => 'inactive', 'expiry_date' => null];
$isActive = isMemberActive($user['id']);
$payments = get_user_payments($user['id']);
$pendingPayment = get_pending_payment((int)$user['id']);
$scanOpen = $scanToPay && (($_GET['pay'] ?? '') === 'scan' || !empty($scanErrors) || !STRIPE_ENABLED);

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

    <?php if (STRIPE_ENABLED || $scanToPay): ?>
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <h2 class="h5 fw-bold mb-1">Upgrade to Teacher Pro</h2>
                <p class="text-secondary mb-4">
                    <?php if (STRIPE_ENABLED && $scanToPay): ?>
                        Pay by card and your membership activates instantly, or scan to pay with PromptPay from any Thai banking app and upload your slip.
                    <?php elseif (STRIPE_ENABLED): ?>
                        Pay securely by card. Your membership activates automatically the moment payment is confirmed.
                    <?php else: ?>
                        Scan to pay with PromptPay from any Thai banking app, then upload your slip. We'll activate your membership as soon as we've checked it.
                    <?php endif; ?>
                </p>
                <div class="row g-3">
                    <?php foreach (['monthly' => ['Monthly', PRICE_MONTHLY, '/month'], 'annual' => ['Annual', PRICE_ANNUAL, '/year']] as $plan => [$planLabel, $planPrice, $planPer]): ?>
                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100 d-flex flex-column <?= $selectedPlan === $plan ? 'border-primary' : '' ?>">
                                <p class="fw-bold mb-1"><?= e($planLabel) ?><?php if ($plan === 'annual'): ?> <span class="badge bg-warning text-dark">Best Value</span><?php endif; ?></p>
                                <p class="h4 fw-bold mb-3"><?= format_currency($planPrice) ?><span class="fs-6 fw-normal text-secondary"><?= e($planPer) ?></span></p>
                                <div class="mt-auto d-grid gap-2">
                                    <?php if (STRIPE_ENABLED): ?>
                                        <form method="post" action="<?= e(base_url('member/stripe-checkout.php')) ?>" class="d-grid">
                                            <?php csrf_field(); ?>
                                            <input type="hidden" name="plan" value="<?= e($plan) ?>">
                                            <button type="submit" class="btn btn-primary"><i class="fa-regular fa-credit-card me-2" aria-hidden="true"></i>Pay by card</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($scanToPay): ?>
                                        <a class="btn <?= STRIPE_ENABLED ? 'btn-outline-primary' : 'btn-primary' ?>"
                                           href="<?= e(base_url('member/subscription.php?plan=' . $plan . '&pay=scan#scan-to-pay')) ?>"
                                           data-open-scan="<?= e($plan) ?>"><i class="fa-solid fa-qrcode me-2" aria-hidden="true"></i>Scan to pay (PromptPay)</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <?php if ($scanToPay):
            $scanPlan = $scanOld['plan'] ?? $selectedPlan;
            $amountMonthly = SITE_CURRENCY_SYMBOL . number_format(PRICE_MONTHLY, 2);
            $amountAnnual = SITE_CURRENCY_SYMBOL . number_format(PRICE_ANNUAL, 2);
        ?>
            <section class="card shadow-sm border-0 mb-4 scan-to-pay" id="scan-to-pay" <?= $scanOpen ? '' : 'hidden' ?>
                     data-amount-monthly="<?= e($amountMonthly) ?>" data-amount-annual="<?= e($amountAnnual) ?>"
                     data-file-prefix="<?= e(strtolower(SITE_NAME)) ?>-promptpay" aria-labelledby="scan-to-pay-title">
                <div class="card-body p-4">
                    <h2 class="h5 fw-bold mb-1" id="scan-to-pay-title"><i class="fa-solid fa-qrcode me-2 text-primary" aria-hidden="true"></i>Scan to pay with PromptPay</h2>
                    <p class="text-secondary mb-4">Pay straight from your banking app. No card needed.</p>

                    <?php if ($pendingPayment): ?>
                        <div class="alert alert-info">
                            <strong>We've got your slip.</strong>
                            Your <?= e(format_payment_amount($pendingPayment)) ?> payment from <?= e(format_date($pendingPayment['payment_date'])) ?> is waiting for approval &mdash; we'll email you as soon as Teacher Pro is active.
                            Sent the wrong slip? Email <a href="mailto:<?= e(CONTACT_EMAIL) ?>"><?= e(CONTACT_EMAIL) ?></a>.
                        </div>
                    <?php endif; ?>

                    <div class="scan-plan-toggle btn-group mb-4" role="group" aria-label="Choose a plan">
                        <button type="button" class="btn btn-outline-primary" data-scan-plan="monthly" aria-pressed="<?= $scanPlan !== 'annual' ? 'true' : 'false' ?>">Monthly &middot; <?= format_currency(PRICE_MONTHLY) ?></button>
                        <button type="button" class="btn btn-outline-primary" data-scan-plan="annual" aria-pressed="<?= $scanPlan === 'annual' ? 'true' : 'false' ?>">Annual &middot; <?= format_currency(PRICE_ANNUAL) ?></button>
                    </div>

                    <div class="row g-4 align-items-start">
                        <div class="col-md-5">
                            <div class="scan-qr-card text-center">
                                <div class="scan-qr-brand">PromptPay</div>
                                <?php if ($scanToPay['mode'] === 'generated'): ?>
                                    <div class="scan-qr" data-qr
                                         data-payload-monthly="<?= e(promptpay_payload($scanToPay['promptpay_id'], plan_price('monthly'))) ?>"
                                         data-payload-annual="<?= e(promptpay_payload($scanToPay['promptpay_id'], plan_price('annual'))) ?>">
                                        <noscript><p class="small text-secondary p-3 mb-0">Turn on JavaScript to see the QR code, or pay the PromptPay number below.</p></noscript>
                                    </div>
                                <?php else: ?>
                                    <img class="scan-qr scan-qr-image" src="<?= e($scanToPay['qr_image_url']) ?>" alt="PromptPay QR code" width="240" height="240">
                                <?php endif; ?>
                                <p class="scan-qr-amount mb-0"><span data-scan-amount><?= e($scanPlan === 'annual' ? $amountAnnual : $amountMonthly) ?></span></p>
                                <?php if ($scanToPay['mode'] === 'image'): ?>
                                    <p class="small text-secondary mb-0">Type this amount when you pay.</p>
                                <?php endif; ?>
                                <?php if ($scanToPay['account_name'] !== ''): ?>
                                    <p class="small mb-0 mt-2">To <strong><?= e($scanToPay['account_name']) ?></strong></p>
                                <?php endif; ?>
                                <?php if ($scanToPay['mode'] === 'generated'): ?>
                                    <p class="small text-secondary mb-0">PromptPay <?= e(format_promptpay_id($scanToPay['promptpay_id'])) ?></p>
                                <?php endif; ?>
                            </div>
                            <?php if ($scanToPay['mode'] === 'generated'): ?>
                                <a class="btn btn-soft w-100 mt-3" href="#" data-qr-save hidden><i class="fa-solid fa-download me-2" aria-hidden="true"></i>Save QR image</a>
                            <?php else: ?>
                                <a class="btn btn-soft w-100 mt-3" href="<?= e($scanToPay['qr_image_url']) ?>" download><i class="fa-solid fa-download me-2" aria-hidden="true"></i>Save QR image</a>
                            <?php endif; ?>
                            <p class="small text-secondary mt-2 mb-0">Paying on this phone? Save the QR, then choose <em>Scan from photo</em> in your banking app.</p>
                        </div>

                        <div class="col-md-7">
                            <ol class="scan-steps">
                                <li><strong>Scan the QR</strong> with any Thai banking app (K PLUS, SCB EASY, Krungthai NEXT, Bangkok Bank&hellip;).</li>
                                <li><strong>Check the amount<?= $scanToPay['account_name'] !== '' ? ' and name' : '' ?></strong>, then confirm the payment.</li>
                                <li><strong>Upload your slip</strong> below. We'll check it and activate Teacher Pro, usually within a few hours.</li>
                            </ol>
                            <?php if ($scanToPay['instructions'] !== ''): ?>
                                <p class="small text-secondary"><?= nl2br(e($scanToPay['instructions'])) ?></p>
                            <?php endif; ?>

                            <?php if (!$pendingPayment || !empty($scanErrors)): ?>
                            <form method="post" action="<?= e(base_url('member/subscription.php')) ?>#scan-to-pay" enctype="multipart/form-data" class="scan-form" novalidate>
                                <?php csrf_field(); ?>
                                <input type="hidden" name="action" value="scan_payment">
                                <input type="hidden" name="plan" value="<?= e($scanPlan) ?>">

                                <?php if (!empty($scanErrors)): ?>
                                    <div class="alert alert-danger" role="alert">Please fix the highlighted fields and try again.</div>
                                <?php endif; ?>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold" for="screenshot">Payment slip <span class="text-danger" aria-hidden="true">*</span></label>
                                    <input type="file" class="form-control <?= isset($scanErrors['screenshot']) ? 'is-invalid' : '' ?>" id="screenshot" name="screenshot"
                                           accept="image/jpeg,image/png,image/webp" required aria-describedby="screenshot-help<?= isset($scanErrors['screenshot']) ? ' screenshot-error' : '' ?>">
                                    <div class="form-text" id="screenshot-help">A screenshot or photo of the slip your banking app shows (JPG, PNG or WebP).</div>
                                    <?php if (isset($scanErrors['screenshot'])): ?><div class="invalid-feedback d-block" id="screenshot-error"><?= e($scanErrors['screenshot']) ?></div><?php endif; ?>
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-sm-6">
                                        <label class="form-label fw-semibold" for="payment_date">Date paid</label>
                                        <input type="date" class="form-control <?= isset($scanErrors['payment_date']) ? 'is-invalid' : '' ?>" id="payment_date" name="payment_date"
                                               value="<?= e($scanOld['payment_date'] ?? date('Y-m-d')) ?>" max="<?= e(date('Y-m-d')) ?>" required>
                                        <?php if (isset($scanErrors['payment_date'])): ?><div class="invalid-feedback d-block"><?= e($scanErrors['payment_date']) ?></div><?php endif; ?>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label fw-semibold" for="reference_number">Transaction reference <span class="text-secondary fw-normal">(optional)</span></label>
                                        <input type="text" class="form-control <?= isset($scanErrors['reference_number']) ? 'is-invalid' : '' ?>" id="reference_number" name="reference_number"
                                               value="<?= e($scanOld['reference_number'] ?? '') ?>" maxlength="150" autocomplete="off">
                                        <?php if (isset($scanErrors['reference_number'])): ?><div class="invalid-feedback d-block"><?= e($scanErrors['reference_number']) ?></div><?php endif; ?>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-accent btn-lg w-100"><i class="fa-solid fa-paper-plane me-2" aria-hidden="true"></i>Submit payment slip &middot; <span data-scan-amount><?= e($scanPlan === 'annual' ? $amountAnnual : $amountMonthly) ?></span></button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>
            <?php if ($scanToPay['mode'] === 'generated'): ?>
                <script src="<?= e(versioned_asset_url('js/vendor/qrcode-generator.js')) ?>" defer></script>
            <?php endif; ?>
            <script src="<?= e(versioned_asset_url('js/scan-to-pay.js')) ?>" defer></script>
        <?php endif; ?>
    <?php else: ?>
        <div class="alert alert-warning">
            Online payment is temporarily unavailable. Please contact <a href="mailto:<?= e(CONTACT_EMAIL) ?>"><?= e(CONTACT_EMAIL) ?></a> to upgrade your membership.
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
