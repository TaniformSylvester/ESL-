<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/stripe-functions.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !STRIPE_ENABLED) {
    redirect('member/subscription.php');
}

require_csrf();

$user = current_user();

if (too_many_attempts('stripe_checkout:' . $user['id'], 5, 600)) {
    flash_set('error', 'Too many attempts. Please wait a few minutes and try again.');
    redirect('member/subscription.php');
}
record_attempt('stripe_checkout:' . $user['id']);

$plan = in_array($_POST['plan'] ?? '', ['monthly', 'annual'], true) ? $_POST['plan'] : 'monthly';
// The currency the teacher saw the price in on the subscription page.
$currency = normalize_currency($_POST['currency'] ?? '') ?? visitor_currency();
$result = create_stripe_checkout_session($user, $plan, $currency);

if (!$result['success'] || !$result['url']) {
    flash_set('error', $result['error'] ?? 'Could not start the Stripe checkout. Please try again.');
    redirect('member/subscription.php');
}

header('Location: ' . $result['url']);
exit;
