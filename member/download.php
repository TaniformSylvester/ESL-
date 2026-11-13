<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/membership.php';
require_once __DIR__ . '/../includes/resource-functions.php';
require_once __DIR__ . '/../includes/download-functions.php';
require_once __DIR__ . '/../includes/bundle-functions.php';

$id = (int)($_GET['id'] ?? 0);
$resource = $id > 0 ? get_resource_by_id($id) : null;

if (!$resource || !$resource['is_published'] || $resource['status'] !== 'active') {
    http_response_code(404);
    require __DIR__ . '/../404.php';
    exit;
}

// Every download needs an account (lead capture). A logged-out visitor is
// sent to registration, which returns them to the resource page afterwards.
if (!is_logged_in()) {
    flash_set('info', 'Create a free account (or log in) to download this resource.');
    redirect(download_signup_url($resource));
}

// A members-only resource the user isn't entitled to can still be
// downloaded by spending a Free Pro Preview credit. The credit is only
// spent further down, once the file is confirmed to exist, so a missing
// file never costs one.
$useProTrial = false;
if (!can_download_resource($resource)) {
    if (!can_use_pro_trial($resource)) {
        flash_set('warning', 'This resource is available to Teacher Pro members. Upgrade to download it.');
        redirect('resource.php?slug=' . urlencode($resource['slug']));
    }
    $useProTrial = true;
}

if (empty($resource['file_path'])) {
    error_log("Download error: resource #{$id} has no file_path set");
    flash_set('error', 'This resource is not currently available for download.');
    redirect('resource.php?slug=' . urlencode($resource['slug']));
}

$filePath = UPLOAD_PROTECTED_PATH . '/' . $resource['file_path'];

if (!is_file($filePath)) {
    error_log("Download error: file missing on disk for resource #{$id}: {$filePath}");
    flash_set('error', 'This resource is not currently available for download. Please contact support.');
    redirect('resource.php?slug=' . urlencode($resource['slug']));
}

$userId = (int)$_SESSION['user_id'];
$spentCredit = false;

if ($useProTrial && !try_unlock_with_pro_trial($userId, $id, $spentCredit)) {
    flash_set('warning', "You've used all your Free Pro Preview credits. Upgrade to Teacher Pro to download this resource.");
    redirect('resource.php?slug=' . urlencode($resource['slug']));
}

record_download($userId, $id, $spentCredit);

$downloadName = $resource['file_name'] ?: ($resource['slug'] . '.' . $resource['file_type']);
$downloadName = str_replace(['"', "\r", "\n"], '', $downloadName);

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Transfer-Encoding: binary');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

readfile($filePath);
exit;
