<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-functions.php';
require_once __DIR__ . '/../includes/settings-functions.php';
require_once __DIR__ . '/../includes/upload-functions.php';

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

    if (!empty($_FILES['qr_code_image']['name'])) {
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
$promptPayNumber = get_setting('promptpay_number');
$qrCodeImage = get_setting('qr_code_image');
$paymentInstructions = get_setting('payment_instructions');

$pageTitle = 'Settings';
require_once __DIR__ . '/../includes/admin-header.php';
?>
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h2 class="h5 fw-bold mb-1">Payment Settings</h2>
                <p class="text-secondary small mb-4">
                    Shown to teachers on the Subscription page as instructions for paying their
                    Teacher Pro membership (<?= format_currency(PRICE_MONTHLY) ?>/month or <?= format_currency(PRICE_ANNUAL) ?>/year).
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
                            <input type="text" class="form-control" id="promptpay_number" name="promptpay_number" value="<?= e($promptPayNumber) ?>" maxlength="50">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="qr_code_image">PromptPay QR Code Image <span class="text-secondary">(optional)</span></label>
                            <input type="file" class="form-control <?= isset($errors['qr_code_image']) ? 'is-invalid' : '' ?>"
                                   id="qr_code_image" name="qr_code_image" accept=".jpg,.jpeg,.png,.webp">
                            <?php if ($qrCodeImage): ?>
                                <img src="<?= e(UPLOAD_BASE_URL . '/' . rawurlencode($qrCodeImage)) ?>" class="img-thumbnail mt-2" style="max-width:150px;" alt="Current QR code">
                            <?php endif; ?>
                            <?php if (isset($errors['qr_code_image'])): ?><div class="invalid-feedback d-block"><?= e($errors['qr_code_image']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="payment_instructions">Payment Instructions</label>
                            <textarea class="form-control" id="payment_instructions" name="payment_instructions" rows="4"><?= e($paymentInstructions) ?></textarea>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary mt-4">Save Settings</button>
                </form>
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
