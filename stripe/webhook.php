<?php
/**
 * Public endpoint Stripe's servers call directly — no login, no CSRF token
 * (there's no user session involved at all). Trust is established entirely
 * through the signature check below, which is why that check happens
 * before anything else touches the request.
 */
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/admin-functions.php';
require_once __DIR__ . '/../includes/membership.php';
require_once __DIR__ . '/../includes/payment-functions.php';
require_once __DIR__ . '/../includes/bundle-functions.php';
require_once __DIR__ . '/../includes/stripe-functions.php';
require_once __DIR__ . '/../includes/email.php';

if (!STRIPE_ENABLED) {
    http_response_code(404);
    exit;
}

$payload = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if ($payload === '' || $sigHeader === '' || !verify_stripe_webhook_signature($payload, $sigHeader, STRIPE_WEBHOOK_SECRET)) {
    error_log('Stripe webhook: signature verification failed');
    http_response_code(400);
    exit('Invalid signature.');
}

$event = json_decode($payload, true);

if (!is_array($event) || !isset($event['type'])) {
    http_response_code(400);
    exit('Malformed payload.');
}

if ($event['type'] === 'checkout.session.completed') {
    $session = $event['data']['object'] ?? [];
    $type = (string)($session['metadata']['type'] ?? 'membership');
    $userId = (int)($session['metadata']['user_id'] ?? 0);
    $sessionId = (string)($session['id'] ?? '');
    $paymentStatus = (string)($session['payment_status'] ?? '');
    $amountTotal = (int)($session['amount_total'] ?? 0);

    // payment_status may be "unpaid" for delayed payment methods (PromptPay
    // included) even once this event first fires — only credit access
    // once Stripe confirms the payment actually cleared.
    if ($userId > 0 && $sessionId !== '' && $paymentStatus === 'paid') {
        $user = get_user_by_id($userId);

        if ($user && $type === 'bundle') {
            $bundleId = (int)($session['metadata']['bundle_id'] ?? 0);
            $bundle = $bundleId > 0 ? get_bundle_by_id($bundleId) : null;

            if ($bundle) {
                record_bundle_purchase($userId, $bundleId, $amountTotal / 100, $sessionId);
                send_bundle_purchase_email($user, $bundle);
            }
        } elseif ($user) {
            $plan = (string)($session['metadata']['plan'] ?? 'monthly');
            record_stripe_payment($userId, $amountTotal / 100, $sessionId, $plan);

            $membership = get_membership($userId);
            send_payment_approved_email($user, $membership['expiry_date'] ?? '');
        }
    }
}

http_response_code(200);
echo 'ok';
