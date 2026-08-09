<?php
/**
 * Manual payment submission (teacher-facing). Admin approve/reject
 * workflow is added alongside /admin/payments.php in a later stage —
 * that code will operate on the same `payments` row shape, so swapping
 * in an automatic payment gateway later only means writing a new
 * "create an approved payment" path, not touching membership logic.
 */

const PAYMENT_METHODS = [
    'bank_transfer' => 'Bank Transfer',
    'promptpay'     => 'PromptPay',
    'manual_other'  => 'Other',
];

/**
 * Validates and stores a manual payment submission, and marks the
 * membership 'pending' (unless it's already active — an early renewal
 * payment must not interrupt current access while it awaits approval).
 * Returns ['success' => bool, 'errors' => array<string,string>]
 */
function submit_payment(int $userId, array $input, array $file): array
{
    $errors = [];

    $amount = (float)($input['amount'] ?? 0);
    $method = (string)($input['method'] ?? 'bank_transfer');
    $paymentDate = trim((string)($input['payment_date'] ?? ''));
    $reference = clean_input($input['reference_number'] ?? '');

    if ($amount <= 0 || $amount > 999999) {
        $errors['amount'] = 'Please enter a valid amount.';
    }

    if (!array_key_exists($method, PAYMENT_METHODS)) {
        $method = 'bank_transfer';
    }

    $paymentDateObj = DateTime::createFromFormat('Y-m-d', $paymentDate);
    if (!$paymentDateObj || $paymentDateObj->format('Y-m-d') !== $paymentDate) {
        $errors['payment_date'] = 'Please enter a valid date.';
    } elseif ($paymentDate > date('Y-m-d')) {
        // Plain ISO-format string comparison — createFromFormat('Y-m-d', ...)
        // fills the missing time-of-day from the current time rather than
        // midnight, so comparing DateTime objects here would flag today's
        // date as "in the future" for anyone submitting after 00:00.
        $errors['payment_date'] = 'Payment date cannot be in the future.';
    }

    if ($reference === '' || mb_strlen($reference) > 150) {
        $errors['reference_number'] = 'Please enter the transaction/reference number.';
    }

    $screenshotFilename = null;
    if (!empty($file['name'])) {
        $upload = handle_upload($file, UPLOAD_BASE_PATH . '/payment-proofs', ALLOWED_IMAGE_MIME_TYPES, MAX_IMAGE_SIZE_BYTES);

        if (!$upload['success']) {
            $errors['screenshot'] = $upload['error'];
        } else {
            $screenshotFilename = $upload['filename'];
        }
    }

    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }

    $db = getDB();
    $db->prepare(
        'INSERT INTO payments (user_id, amount, currency, method, reference_number, payment_date, screenshot_path, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([$userId, $amount, CURRENCY, $method, $reference, $paymentDate, $screenshotFilename, 'pending']);

    // Don't downgrade a currently-active member who is paying ahead of expiry.
    $db->prepare("UPDATE memberships SET status = 'pending' WHERE user_id = ? AND status != 'active'")
        ->execute([$userId]);

    return ['success' => true, 'errors' => []];
}

function get_user_payments(int $userId): array
{
    $stmt = getDB()->prepare('SELECT * FROM payments WHERE user_id = ? ORDER BY created_at DESC');
    $stmt->execute([$userId]);

    return $stmt->fetchAll();
}

function payment_status_badge_class(string $status): string
{
    return match ($status) {
        'approved' => 'bg-success',
        'rejected' => 'bg-danger',
        default    => 'bg-warning text-dark',
    };
}
