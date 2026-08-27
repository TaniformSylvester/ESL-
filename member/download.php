<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/membership.php';
require_once __DIR__ . '/../includes/resource-functions.php';
require_once __DIR__ . '/../includes/download-functions.php';

$id = (int)($_GET['id'] ?? 0);
$resource = $id > 0 ? get_resource_by_id($id) : null;

if (!$resource || !$resource['is_published'] || $resource['status'] !== 'active') {
    http_response_code(404);
    require __DIR__ . '/../404.php';
    exit;
}

if (!is_logged_in()) {
    redirect('login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
}

if (!can_download_resource($resource)) {
    if (!$resource['is_free']) {
        flash_set('warning', 'This resource is available to Teacher Pro members. Upgrade to download it.');
    } else {
        flash_set('warning', "You've reached your 5 free downloads for this month. Upgrade to Teacher Pro for unlimited downloads.");
    }
    redirect('resource.php?slug=' . urlencode($resource['slug']));
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

// can_download_resource() already confirmed eligibility, but the actual
// quota decrement for a free-plan user must happen here, atomically, and
// only once we know the file genuinely exists — a missing file on the
// server shouldn't cost the user one of their 5 free downloads.
if ($resource['is_free'] && !isMemberActive() && !is_admin()) {
    if (!try_consume_free_download((int)$_SESSION['user_id'])) {
        flash_set('warning', "You've reached your 5 free downloads for this month. Upgrade to Teacher Pro for unlimited downloads.");
        redirect('resource.php?slug=' . urlencode($resource['slug']));
    }
}

record_download(is_logged_in() ? (int)$_SESSION['user_id'] : null, $id);

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
