<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/membership.php';
require_once __DIR__ . '/../includes/resource-functions.php';
require_once __DIR__ . '/../includes/download-functions.php';
require_once __DIR__ . '/../includes/bundle-functions.php';

$id = (int)($_GET['id'] ?? 0);
$file = $id > 0 ? get_resource_file_by_id($id) : null;
$resource = $file ? get_resource_by_id((int)$file['resource_id']) : null;

if (!$file || !$resource || !$resource['is_published'] || $resource['status'] !== 'active') {
    http_response_code(404);
    require __DIR__ . '/../404.php';
    exit;
}

// Additional files share the main file's access rule (see
// member/download.php): an account is always required, and a Free Pro
// Preview credit unlocks the whole resource, so a credit spent here also
// covers the main file and every other additional file of this resource.
if (!is_logged_in()) {
    flash_set('info', 'Create a free account (or log in) to download this resource.');
    redirect(download_signup_url($resource));
}

$useProTrial = false;
if (!can_download_resource($resource)) {
    if (!can_use_pro_trial($resource)) {
        flash_set('warning', 'This resource is available to Teacher Pro members. Upgrade to download it.');
        redirect('resource.php?slug=' . urlencode($resource['slug']));
    }
    $useProTrial = true;
}

// Free-plan soft monthly limit: an additional file belongs to its resource,
// so it is covered by the same monthly download (see member/download.php).
$freeQuota = free_download_quota_state($resource);
if ($freeQuota !== null && !$freeQuota['allowed']) {
    flash_set('warning', $freeQuota['message']);
    redirect('resource.php?slug=' . urlencode($resource['slug']) . '&limit=1');
}

$filePath = UPLOAD_PROTECTED_PATH . '/' . $file['file_path'];

if (!is_file($filePath)) {
    error_log("Download error: additional file missing on disk for resource #{$resource['id']}, file #{$id}: {$filePath}");
    flash_set('error', 'This file is not currently available for download. Please contact support.');
    redirect('resource.php?slug=' . urlencode($resource['slug']));
}

if ($useProTrial) {
    $spentCredit = false;
    if (!try_unlock_with_pro_trial((int)$_SESSION['user_id'], (int)$resource['id'], $spentCredit)) {
        flash_set('warning', "You've used all your Free Pro Preview credits. Upgrade to Teacher Pro to download this resource.");
        redirect('resource.php?slug=' . urlencode($resource['slug']));
    }
    // Additional files aren't otherwise logged, but the download that
    // spends a credit is, so the credit always shows in download history.
    if ($spentCredit) {
        record_download((int)$_SESSION['user_id'], (int)$resource['id'], true);
    }
}

if (!$useProTrial) {
    $countedFree = false;
    if (!try_claim_free_download($resource, $countedFree)) {
        flash_set('warning', free_download_usage_message(get_free_download_usage((int)$_SESSION['user_id'])));
        redirect('resource.php?slug=' . urlencode($resource['slug']) . '&limit=1');
    }
    // As with a Free Pro Preview credit, the download that uses up one of
    // the month's free downloads is logged, so the resource then counts as
    // downloaded this month (re-downloads and the main file stay free) and
    // shows in download history.
    if ($countedFree) {
        record_download((int)$_SESSION['user_id'], (int)$resource['id']);
    }
}

$downloadName = $file['file_name'] ?: ('file-' . $id . '.' . $file['file_type']);
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
