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
    $userId = (int)($session['metadata']['user_id'] ?? 0);
    $sessionId = (string)($session['id'] ?? '');
    $paymentStatus = (string)($session['payment_status'] ?? '');
    $amountTotal = (int)($session['amount_total'] ?? 0);
    $currency = (string)($session['currency'] ?? 'usd');

    // payment_status may be "unpaid" for delayed payment methods even once
    // this event fires — only credit membership once Stripe confirms paid.
    if ($userId > 0 && $sessionId !== '' && $paymentStatus === 'paid') {
        $user = get_user_by_id($userId);

        if ($user) {
            record_stripe_payment($userId, $amountTotal / 100, $sessionId, $currency);

            $membership = get_membership($userId);
            send_payment_approved_email($user, $membership['expiry_date'] ?? '');
        }
    }
}

http_response_code(200);
echo 'ok';
