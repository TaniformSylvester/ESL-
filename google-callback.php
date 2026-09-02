<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/google-functions.php';
require_once __DIR__ . '/includes/email.php';

require_guest();

if (!GOOGLE_ENABLED) {
    redirect('login.php');
}

$redirectPath = $_SESSION['google_oauth_redirect'] ?? 'dashboard.php';
unset($_SESSION['google_oauth_redirect']);

$expectedState = $_SESSION['google_oauth_state'] ?? null;
unset($_SESSION['google_oauth_state']);

if (isset($_GET['error'])) {
    // The user cancelled at Google's consent screen — not an error worth logging.
    redirect('login.php');
}

$state = (string)($_GET['state'] ?? '');
$code = (string)($_GET['code'] ?? '');

if ($state === '' || $expectedState === null || !hash_equals($expectedState, $state) || $code === '') {
    flash_set('error', 'Google sign-in could not be verified. Please try again.');
    redirect('login.php');
}

$tokens = google_exchange_code($code);
if (!$tokens || empty($tokens['access_token'])) {
    flash_set('error', 'Google sign-in failed. Please try again or use your email and password.');
    redirect('login.php');
}

$profile = google_fetch_userinfo($tokens['access_token']);
if (!$profile) {
    flash_set('error', 'Google sign-in failed. Please try again or use your email and password.');
    redirect('login.php');
}

$result = find_or_create_google_user($profile);

if (isset($result['error'])) {
    flash_set('error', $result['error']);
    redirect('login.php');
}

$user = $result['user'];

if (!$user['is_active']) {
    flash_set('error', 'This account has been deactivated. Please contact support.');
    redirect('login.php');
}

login_user_session($user);

if ($result['is_new']) {
    send_welcome_email($user);
    send_admin_new_registration_email($user);
}

redirect($redirectPath);
