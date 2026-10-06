<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/bundle-functions.php';
require_once __DIR__ . '/../includes/stripe-functions.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !STRIPE_ENABLED) {
    redirect('bundles.php');
}

require_csrf();

$user = current_user();
$bundleId = (int)($_POST['bundle_id'] ?? 0);
$bundle = $bundleId > 0 ? get_bundle_by_id($bundleId) : null;

if (!$bundle || !$bundle['is_published']) {
    flash_set('error', 'That bundle is not available.');
    redirect('bundles.php');
}

if (has_purchased_bundle((int)$user['id'], $bundleId)) {
    flash_set('info', "You already own this bundle.");
    redirect('bundle.php?slug=' . urlencode($bundle['slug']));
}

if (too_many_attempts('bundle_checkout:' . $user['id'], 5, 600)) {
    flash_set('error', 'Too many attempts. Please wait a few minutes and try again.');
    redirect('bundle.php?slug=' . urlencode($bundle['slug']));
}
record_attempt('bundle_checkout:' . $user['id']);

$result = create_bundle_checkout_session($user, $bundle);

if (!$result['success'] || !$result['url']) {
    flash_set('error', $result['error'] ?? 'Could not start the Stripe checkout. Please try again.');
    redirect('bundle.php?slug=' . urlencode($bundle['slug']));
}

header('Location: ' . $result['url']);
exit;
