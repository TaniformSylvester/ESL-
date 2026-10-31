<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/google-functions.php';

require_guest();

if (!GOOGLE_ENABLED) {
    redirect('login.php');
}

$state = bin2hex(random_bytes(24));
$_SESSION['google_oauth_state'] = $state;
$_SESSION['google_oauth_redirect'] = safe_internal_path($_GET['redirect'] ?? null, 'dashboard.php');

header('Location: ' . google_auth_url($state));
exit;
