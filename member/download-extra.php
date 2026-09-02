<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/membership.php';
require_once __DIR__ . '/../includes/resource-functions.php';
require_once __DIR__ . '/../includes/download-functions.php';

$id = (int)($_GET['id'] ?? 0);
$file = $id > 0 ? get_resource_file_by_id($id) : null;
$resource = $file ? get_resource_by_id((int)$file['resource_id']) : null;

if (!$file || !$resource || !$resource['is_published'] || $resource['status'] !== 'active') {
    http_response_code(404);
    require __DIR__ . '/../404.php';
    exit;
}

// Additional files share the same access rule as the resource's main
// file: free resources are unlimited and require no login, so this block
// is only reachable now for a members-only resource the visitor isn't
// entitled to (same Pro-only gate as member/download.php).
if (!can_download_resource($resource)) {
    if (!is_logged_in()) {
        redirect('login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    }

    flash_set('warning', 'This resource is available to Teacher Pro members. Upgrade to download it.');
    redirect('resource.php?slug=' . urlencode($resource['slug']));
}

$filePath = UPLOAD_PROTECTED_PATH . '/' . $file['file_path'];

if (!is_file($filePath)) {
    error_log("Download error: additional file missing on disk for resource #{$resource['id']}, file #{$id}: {$filePath}");
    flash_set('error', 'This file is not currently available for download. Please contact support.');
    redirect('resource.php?slug=' . urlencode($resource['slug']));
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
