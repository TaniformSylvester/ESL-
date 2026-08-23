<?php
/**
 * Payment submission (teacher-facing) and the admin approve/reject
 * workflow. Swapping in an automatic payment gateway later only means
 * writing a new "create an approved payment" path elsewhere — the
 * approve/reject functions here don't care how a payment was created.
 */

// The manual-submission form's selectable options — deliberately excludes
// 'stripe', which is only ever set by the webhook, never chosen by a user.
const PAYMENT_METHODS = [
    'bank_transfer' => 'Bank Transfer',
    'promptpay'     => 'PromptPay',
    'manual_other'  => 'Other',
];

/** For displaying any payment row's method, including the gateway-only 'stripe' value PAYMENT_METHODS excludes. */
function payment_method_label(string $method): string
{
    return $method === 'stripe' ? 'Stripe (Card)' : (PAYMENT_METHODS[$method] ?? $method);
}

/**
 * Validates and stores a manual payment submission, and marks the
 * membership 'pending' (unless it's already active — an early renewal
 * payment must not interrupt current access while it awaits approval).
 * Returns ['success' => bool, 'errors' => array<string,string>]
 */
function submit_payment(int $userId, array $input, array $file): array
{
    $errors = [];

    $plan = (string)($input['plan'] ?? 'monthly');
    if (!array_key_exists($plan, PLAN_DAYS)) {
        $plan = 'monthly';
    }

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
        'INSERT INTO payments (user_id, amount, currency, method, plan, reference_number, payment_date, screenshot_path, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([$userId, $amount, CURRENCY, $method, $plan, $reference, $paymentDate, $screenshotFilename, 'pending']);

    // Don't downgrade a currently-active member who is paying ahead of expiry.
    $db->prepare("UPDATE memberships SET status = 'pending' WHERE user_id = ? AND status != 'active'")
        ->execute([$userId]);

    return ['success' => true, 'errors' => []];
}

/**
 * Records a Stripe-confirmed payment and immediately extends membership —
 * the automatic counterpart to approve_payment(), called from the webhook
 * once Stripe confirms the charge actually succeeded. Idempotent against
 * Stripe's at-least-once webhook delivery: a session already recorded
 * (checked via gateway_reference) is a no-op, not a duplicate extension.
 */
function record_stripe_payment(int $userId, float $amount, string $sessionId): void
{
    $db = getDB();

    $existing = $db->prepare('SELECT id FROM payments WHERE gateway_reference = ? LIMIT 1');
    $existing->execute([$sessionId]);
    if ($existing->fetchColumn()) {
        return;
    }

    // Stripe charges are always USD (STRIPE_CURRENCY) regardless of the
    // site's default THB currency used for bank transfer/PromptPay, and
    // only ever offers the monthly plan (see config/stripe.php).
    $db->prepare(
        "INSERT INTO payments (user_id, amount, currency, method, plan, reference_number, payment_date, status, gateway_reference, reviewed_at)
         VALUES (?, ?, ?, 'stripe', 'monthly', ?, CURDATE(), 'approved', ?, NOW())"
    )->execute([$userId, $amount, strtoupper(STRIPE_CURRENCY), $sessionId, $sessionId]);

    $paymentId = (int)$db->lastInsertId();

    extend_membership_for_plan($userId, 'monthly');

    $db->prepare('UPDATE memberships SET last_payment_id = ? WHERE user_id = ?')
        ->execute([$paymentId, $userId]);
}

/**
 * Payment rows can be in THB (bank transfer/PromptPay) or USD (Stripe) —
 * format_currency() alone always assumes THB, so payment-history tables
 * need this instead to display each row in its own actual currency.
 */
function format_payment_amount(array $payment): string
{
    if (($payment['currency'] ?? '') === 'USD') {
        return '$' . number_format((float)$payment['amount'], 2);
    }

    return format_currency($payment['amount']);
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

// -----------------------------------------------------------------------
// ADMIN: APPROVE / REJECT
// -----------------------------------------------------------------------

function get_payment_by_id(int $id): ?array
{
    $stmt = getDB()->prepare(
        'SELECT p.*, u.first_name, u.last_name, u.email
         FROM payments p
         INNER JOIN users u ON u.id = p.user_id
         WHERE p.id = ? LIMIT 1'
    );
    $stmt->execute([$id]);

    return $stmt->fetch() ?: null;
}

/** $filters may contain: status ('pending'|'approved'|'rejected'|''), search. */
function get_all_payments_paginated(array $filters, int $page, int $perPage): array
{
    $where = [];
    $params = [];

    $status = trim((string)($filters['status'] ?? ''));
    if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
        $where[] = 'p.status = ?';
        $params[] = $status;
    }

    $search = trim((string)($filters['search'] ?? ''));
    if ($search !== '') {
        $where[] = '(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR p.reference_number LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like, $like);
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    $db = getDB();

    $countStmt = $db->prepare("SELECT COUNT(*) FROM payments p INNER JOIN users u ON u.id = p.user_id {$whereSql}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $totalPages = $perPage > 0 ? max(1, (int)ceil($total / $perPage)) : 1;
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;

    $sql = "SELECT p.*, u.first_name, u.last_name, u.email
            FROM payments p
            INNER JOIN users u ON u.id = p.user_id
            {$whereSql}
            ORDER BY p.created_at DESC
            LIMIT {$perPage} OFFSET {$offset}";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return ['items' => $stmt->fetchAll(), 'total' => $total, 'total_pages' => $totalPages, 'page' => $page];
}

/**
 * Approves a pending payment and extends the member's subscription by the
 * payment's plan (30 days for monthly, 365 for annual) via
 * extend_membership_for_plan() (includes/admin-functions.php, must already
 * be loaded by the caller) — the same date math used for the Stripe path,
 * so membership dates are only ever computed in one place.
 * Returns the payment row (with user info) on success, or null if the
 * payment doesn't exist or was already processed.
 */
function approve_payment(int $paymentId, int $adminId): ?array
{
    $payment = get_payment_by_id($paymentId);

    if (!$payment || $payment['status'] !== 'pending') {
        return null;
    }

    $db = getDB();
    $db->prepare("UPDATE payments SET status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?")
        ->execute([$adminId, $paymentId]);

    extend_membership_for_plan((int)$payment['user_id'], $payment['plan'] ?? 'monthly');

    $db->prepare('UPDATE memberships SET last_payment_id = ? WHERE user_id = ?')
        ->execute([$paymentId, $payment['user_id']]);

    return $payment;
}

/**
 * Rejects a pending payment. Only downgrades the membership to 'inactive'
 * if it's still 'pending' — a renewal payment rejected while the member is
 * still currently active must not interrupt their existing access.
 * Returns the payment row (with user info) on success, or null if the
 * payment doesn't exist or was already processed.
 */
function reject_payment(int $paymentId, int $adminId, string $note): ?array
{
    $payment = get_payment_by_id($paymentId);

    if (!$payment || $payment['status'] !== 'pending') {
        return null;
    }

    $db = getDB();
    $db->prepare("UPDATE payments SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW(), admin_note = ? WHERE id = ?")
        ->execute([$adminId, $note !== '' ? $note : null, $paymentId]);

    $db->prepare("UPDATE memberships SET status = 'inactive' WHERE user_id = ? AND status = 'pending'")
        ->execute([$payment['user_id']]);

    return $payment;
}
