<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/membership.php';
require_once __DIR__ . '/../includes/settings-functions.php';
require_once __DIR__ . '/../includes/upload-functions.php';
require_once __DIR__ . '/../includes/payment-functions.php';
require_once __DIR__ . '/../includes/email.php';

require_login();
$user = current_user();
$errors = [];
$old = ['amount' => (string)SUBSCRIPTION_PRICE, 'method' => 'bank_transfer', 'payment_date' => date('Y-m-d'), 'reference_number' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    if (too_many_attempts('payment_submit:' . $user['id'], 5, 600)) {
        $errors['general'] = 'You\'ve submitted several payments recently. Please wait a few minutes and try again, or contact us if you need help.';
    } else {
        record_attempt('payment_submit:' . $user['id']);
        $result = submit_payment($user['id'], $_POST, $_FILES['screenshot'] ?? []);

        if ($result['success']) {
            $submittedPayment = ['amount' => (float)($_POST['amount'] ?? 0)];
            send_payment_submitted_email($user, $submittedPayment);
            send_admin_new_payment_email($user, $submittedPayment);
            flash_set('success', 'Thanks! Your payment has been submitted and is awaiting approval.');
            redirect('member/subscription.php');
        }

        $errors = $result['errors'];
    }
    $old = [
        'amount'           => clean_input($_POST['amount'] ?? $old['amount']),
        'method'           => clean_input($_POST['method'] ?? $old['method']),
        'payment_date'     => clean_input($_POST['payment_date'] ?? $old['payment_date']),
        'reference_number' => clean_input($_POST['reference_number'] ?? ''),
    ];
}

$membership = get_membership($user['id']) ?? ['status' => 'inactive', 'expiry_date' => null];
$isActive = isMemberActive($user['id']);
$payments = get_user_payments($user['id']);

$bankName = get_setting('bank_name');
$bankAccountName = get_setting('bank_account_name');
$bankAccountNumber = get_setting('bank_account_number');
$promptPayNumber = get_setting('promptpay_number');
$qrCodeImage = get_setting('qr_code_image');
$paymentInstructions = get_setting('payment_instructions');
$hasPaymentDetails = $bankName || $bankAccountName || $bankAccountNumber || $promptPayNumber;

$pageTitle = 'Subscription';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="container py-5">
    <h1 class="fw-bold mb-1"><?= e(SITE_NAME) ?> Membership</h1>
    <p class="text-secondary mb-4"><?= format_currency(SUBSCRIPTION_PRICE) ?>/<?= e(SUBSCRIPTION_PERIOD_LABEL) ?></p>

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
                <span class="badge <?= e(membership_status_badge_class($membership)) ?> mb-2"><?= e(membership_status_label($membership)) ?></span>
                <?php if ($isActive): ?>
                    <p class="mb-0">Active until <strong><?= e(format_date($membership['expiry_date'])) ?></strong>
                        (<?= (int)membership_days_remaining($membership) ?> days remaining)</p>
                <?php elseif ($membership['status'] === 'pending'): ?>
                    <p class="mb-0">Your payment is awaiting approval. This usually takes less than a day.</p>
                <?php else: ?>
                    <p class="mb-0">You don't have an active membership yet. Submit a payment below to get started.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (STRIPE_ENABLED): ?>
        <div class="card shadow-sm border-0 mb-4 border-primary-subtle">
            <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <h2 class="h5 fw-bold mb-1"><i class="fa-solid fa-credit-card text-primary me-1"></i> Pay Instantly by Card</h2>
                    <p class="text-secondary mb-0">Activates your membership automatically — no waiting for approval.</p>
                </div>
                <form method="post" action="<?= e(base_url('member/stripe-checkout.php')) ?>">
                    <?php csrf_field(); ?>
                    <button type="submit" class="btn btn-primary px-4"><?= format_currency(SUBSCRIPTION_PRICE) ?> — Pay with Card</button>
                </form>
            </div>
        </div>
        <p class="text-secondary text-center small mb-4">— or pay by bank transfer / PromptPay below —</p>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h2 class="h5 fw-bold mb-3">Payment Instructions</h2>

                    <?php if (!$hasPaymentDetails): ?>
                        <p class="text-secondary">Payment details haven't been configured yet. Please contact <a href="mailto:<?= e(CONTACT_EMAIL) ?>"><?= e(CONTACT_EMAIL) ?></a> for instructions.</p>
                    <?php else: ?>
                        <ul class="list-unstyled small mb-3">
                            <?php if ($bankName): ?><li class="mb-1"><strong>Bank:</strong> <?= e($bankName) ?></li><?php endif; ?>
                            <?php if ($bankAccountName): ?><li class="mb-1"><strong>Account Name:</strong> <?= e($bankAccountName) ?></li><?php endif; ?>
                            <?php if ($bankAccountNumber): ?><li class="mb-1"><strong>Account Number:</strong> <?= e($bankAccountNumber) ?></li><?php endif; ?>
                            <?php if ($promptPayNumber): ?><li class="mb-1"><strong>PromptPay:</strong> <?= e($promptPayNumber) ?></li><?php endif; ?>
                        </ul>
                        <?php if ($qrCodeImage): ?>
                            <img src="<?= e(UPLOAD_BASE_URL . '/' . rawurlencode($qrCodeImage)) ?>" alt="PromptPay QR Code" class="img-fluid mb-3" style="max-width:200px;">
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($paymentInstructions): ?>
                        <p class="small text-secondary"><?= nl2br(e($paymentInstructions)) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h2 class="h5 fw-bold mb-3">Submit Your Payment</h2>

                    <?php if (!empty($errors['general'])): ?>
                        <div class="alert alert-danger"><?= e($errors['general']) ?></div>
                    <?php endif; ?>

                    <form method="post" action="<?= e(base_url('member/subscription.php')) ?>" enctype="multipart/form-data" novalidate>
                        <?php csrf_field(); ?>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="amount">Amount (<?= e(CURRENCY) ?>)</label>
                                <input type="number" step="0.01" min="0" class="form-control <?= isset($errors['amount']) ? 'is-invalid' : '' ?>"
                                       id="amount" name="amount" value="<?= e($old['amount']) ?>" required>
                                <?php if (isset($errors['amount'])): ?><div class="invalid-feedback"><?= e($errors['amount']) ?></div><?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="method">Payment Method</label>
                                <select class="form-select" id="method" name="method">
                                    <?php foreach (PAYMENT_METHODS as $value => $label): ?>
                                        <option value="<?= e($value) ?>" <?= $old['method'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="payment_date">Payment Date</label>
                                <input type="date" class="form-control <?= isset($errors['payment_date']) ? 'is-invalid' : '' ?>"
                                       id="payment_date" name="payment_date" value="<?= e($old['payment_date']) ?>" max="<?= e(date('Y-m-d')) ?>" required>
                                <?php if (isset($errors['payment_date'])): ?><div class="invalid-feedback"><?= e($errors['payment_date']) ?></div><?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="reference_number">Transaction / Reference No.</label>
                                <input type="text" class="form-control <?= isset($errors['reference_number']) ? 'is-invalid' : '' ?>"
                                       id="reference_number" name="reference_number" value="<?= e($old['reference_number']) ?>" required maxlength="150">
                                <?php if (isset($errors['reference_number'])): ?><div class="invalid-feedback"><?= e($errors['reference_number']) ?></div><?php endif; ?>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="screenshot">Payment Screenshot <span class="text-secondary">(optional)</span></label>
                                <input type="file" class="form-control <?= isset($errors['screenshot']) ? 'is-invalid' : '' ?>"
                                       id="screenshot" name="screenshot" accept=".jpg,.jpeg,.png,.webp">
                                <?php if (isset($errors['screenshot'])): ?><div class="invalid-feedback"><?= e($errors['screenshot']) ?></div><?php endif; ?>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 mt-4 py-2">Submit Payment</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($payments)): ?>
        <h2 class="h5 fw-bold mt-5 mb-3">Payment History</h2>
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        <th>Date</th>
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
                            <td><?= format_currency($payment['amount']) ?></td>
                            <td><?= e(PAYMENT_METHODS[$payment['method']] ?? $payment['method']) ?></td>
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
