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

// can_download_resource() now returns true unconditionally for free
// resources (see includes/download-functions.php), so this block is only
// ever reachable for a members-only resource that the visitor isn't
// entitled to — the login redirect below preserves the existing Pro-only
// protection exactly as it was, it just no longer runs for free resources.
if (!can_download_resource($resource)) {
    if (!is_logged_in()) {
        redirect('login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    }

    flash_set('warning', 'This resource is available to Teacher Pro members. Upgrade to download it.');
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

// Free resources are unlimited now — no quota to consume. The visitor
// may or may not be logged in; record_download() already accepts a null
// user_id for an anonymous downloader (downloads.user_id is nullable).
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
