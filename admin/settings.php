<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-functions.php';
require_once __DIR__ . '/../includes/settings-functions.php';
require_once __DIR__ . '/../includes/upload-functions.php';
require_once __DIR__ . '/../includes/promptpay-functions.php';
require_once __DIR__ . '/../includes/payment-functions.php';

require_admin();
$admin = current_user();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $values = [
        'bank_name'            => clean_input($_POST['bank_name'] ?? ''),
        'bank_account_name'    => clean_input($_POST['bank_account_name'] ?? ''),
        'bank_account_number'  => clean_input($_POST['bank_account_number'] ?? ''),
        'promptpay_number'     => clean_input($_POST['promptpay_number'] ?? ''),
        'payment_instructions' => clean_input($_POST['payment_instructions'] ?? ''),
    ];

    if ($values['promptpay_number'] !== '' && promptpay_target($values['promptpay_number']) === null) {
        $errors['promptpay_number'] = 'Enter a 10-digit mobile number (e.g. 081 234 5678) or a 13-digit ID card / tax ID number.';
    }

    if (empty($errors) && !empty($_FILES['qr_code_image']['name'])) {
        $upload = handle_upload($_FILES['qr_code_image'], UPLOAD_BASE_PATH, ALLOWED_IMAGE_MIME_TYPES, MAX_IMAGE_SIZE_BYTES);

        if (!$upload['success']) {
            $errors['qr_code_image'] = $upload['error'];
        } else {
            $oldQr = get_setting('qr_code_image');
            if ($oldQr !== '') {
                @unlink(UPLOAD_BASE_PATH . '/' . $oldQr);
            }
            $values['qr_code_image'] = $upload['filename'];
        }
    }

    if (empty($errors)) {
        update_settings($values);
        log_admin_action($admin['id'], 'update_settings', 'Updated payment settings');
        flash_set('success', 'Settings updated.');
        redirect('admin/settings.php');
    }
}

$bankName = get_setting('bank_name');
$bankAccountName = get_setting('bank_account_name');
$bankAccountNumber = get_setting('bank_account_number');
$promptPayNumber = $errors ? ($values['promptpay_number'] ?? '') : get_setting('promptpay_number');
$qrCodeImage = get_setting('qr_code_image');
$paymentInstructions = get_setting('payment_instructions');
$scanToPay = $errors ? null : scan_to_pay_config();

$pageTitle = 'Settings';
require_once __DIR__ . '/../includes/admin-header.php';
?>
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h2 class="h5 fw-bold mb-1">Payment Settings</h2>
                <p class="text-secondary small mb-4">
                    Powers <strong>Scan to pay</strong> on the teacher's Subscription page: teachers pay
                    Teacher Pro (<?= format_currency(PRICE_MONTHLY) ?>/month or <?= format_currency(PRICE_ANNUAL) ?>/year) straight
                    into your PromptPay account, upload their slip, and you approve it under
                    <a href="<?= e(base_url('admin/payments.php')) ?>">Payments</a>. Card payments through Stripe are unaffected.
                    Leave a field blank to hide it.
                </p>

                <?php if (!empty($errors['general'])): ?>
                    <div class="alert alert-danger"><?= e($errors['general']) ?></div>
                <?php endif; ?>

                <form method="post" action="<?= e(base_url('admin/settings.php')) ?>" enctype="multipart/form-data" novalidate>
                    <?php csrf_field(); ?>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="bank_name">Bank Name</label>
                            <input type="text" class="form-control" id="bank_name" name="bank_name" value="<?= e($bankName) ?>" maxlength="150">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="bank_account_name">Account Name</label>
                            <input type="text" class="form-control" id="bank_account_name" name="bank_account_name" value="<?= e($bankAccountName) ?>" maxlength="150">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="bank_account_number">Account Number</label>
                            <input type="text" class="form-control" id="bank_account_number" name="bank_account_number" value="<?= e($bankAccountNumber) ?>" maxlength="50">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="promptpay_number">PromptPay Number</label>
                            <input type="text" class="form-control <?= isset($errors['promptpay_number']) ? 'is-invalid' : '' ?>" id="promptpay_number" name="promptpay_number"
                                   value="<?= e($promptPayNumber) ?>" maxlength="50" inputmode="numeric" aria-describedby="promptpay_help">
                            <div class="form-text" id="promptpay_help">The mobile number or ID card number your PromptPay is registered to. Teachers get a QR with the exact price filled in.</div>
                            <?php if (isset($errors['promptpay_number'])): ?><div class="invalid-feedback d-block"><?= e($errors['promptpay_number']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="qr_code_image">PromptPay QR Code Image <span class="text-secondary">(optional)</span></label>
                            <div class="form-text mt-0 mb-2">Only used if the PromptPay Number above is blank &mdash; e.g. a QR saved from your banking app. Teachers then type the amount themselves.</div>
                            <input type="file" class="form-control <?= isset($errors['qr_code_image']) ? 'is-invalid' : '' ?>"
                                   id="qr_code_image" name="qr_code_image" accept=".jpg,.jpeg,.png,.webp">
                            <?php if ($qrCodeImage): ?>
                                <img src="<?= e(UPLOAD_BASE_URL . '/' . rawurlencode($qrCodeImage)) ?>" class="img-thumbnail mt-2" style="max-width:150px;" alt="Current QR code">
                            <?php endif; ?>
                            <?php if (isset($errors['qr_code_image'])): ?><div class="invalid-feedback d-block"><?= e($errors['qr_code_image']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="payment_instructions">Extra note for teachers <span class="text-secondary">(optional)</span></label>
                            <div class="form-text mt-0 mb-2">Shown under the Scan to pay steps, e.g. "Slips are checked 8am&ndash;8pm Bangkok time."</div>
                            <textarea class="form-control" id="payment_instructions" name="payment_instructions" rows="4"><?= e($paymentInstructions) ?></textarea>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary mt-4">Save Settings</button>
                </form>
            </div>
        </div>

        <div class="card shadow-sm border-0 mt-4">
            <div class="card-body">
                <h2 class="h5 fw-bold mb-1">Scan to Pay Preview</h2>
                <?php if (!$scanToPay): ?>
                    <p class="text-secondary small mb-0">Scan to pay is <strong>off</strong>. Add your PromptPay Number (or upload a QR image) above to turn it on.</p>
                <?php elseif ($scanToPay['mode'] === 'generated'): ?>
                    <p class="text-secondary small mb-3">
                        This is the Monthly QR teachers see (<?= format_currency(PRICE_MONTHLY) ?>). Scan it with your own banking app to check it shows
                        your name and the right amount &mdash; then just cancel instead of paying.
                    </p>
                    <div class="d-flex flex-wrap align-items-center gap-4">
                        <div class="border rounded p-2 bg-white" style="width:200px;height:200px;" id="promptpay-preview"
                             data-payload="<?= e(promptpay_payload($scanToPay['promptpay_id'], plan_price('monthly'))) ?>"></div>
                        <div class="small">
                            <div><strong>PromptPay:</strong> <?= e(format_promptpay_id($scanToPay['promptpay_id'])) ?></div>
                            <div><strong>Amount:</strong> <?= format_currency(PRICE_MONTHLY) ?> (Annual: <?= format_currency(PRICE_ANNUAL) ?>)</div>
                            <?php if ($scanToPay['account_name'] !== ''): ?><div><strong>Shown as paying to:</strong> <?= e($scanToPay['account_name']) ?></div><?php endif; ?>
                        </div>
                    </div>
                    <script src="<?= e(versioned_asset_url('js/vendor/qrcode-generator.js')) ?>"></script>
                    <script>
                        (function () {
                            var box = document.getElementById('promptpay-preview');
                            if (!box || typeof qrcode !== 'function') { return; }
                            var qr = qrcode(0, 'M');
                            qr.addData(box.getAttribute('data-payload'));
                            qr.make();
                            box.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 2, scalable: true, alt: 'PromptPay QR preview' });
                            var svg = box.querySelector('svg');
                            if (svg) { svg.style.width = '100%'; svg.style.height = '100%'; }
                        })();
                    </script>
                <?php else: ?>
                    <p class="text-secondary small mb-3">Teachers see your uploaded QR image and are told to enter the amount themselves.</p>
                    <img src="<?= e($scanToPay['qr_image_url']) ?>" class="img-thumbnail" style="max-width:200px;" alt="Uploaded PromptPay QR code">
                <?php endif; ?>
            </div>
        </div>

        <div class="card shadow-sm border-0 mt-4">
            <div class="card-body">
                <h2 class="h5 fw-bold mb-1">Branding &amp; Other Settings</h2>
                <p class="text-secondary small mb-0">
                    Site name, subscription price, contact email, timezone, and other branding values
                    live in <code>config/config.php</code> so they only need to be set in one place —
                    edit that file directly to change them.
                </p>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
