<?php
/**
 * Lets an admin download a generated draft's files for review. The public
 * download endpoints (member/download.php, download-extra.php) only serve
 * published resources, and this does not change that: it is admin-only and
 * limited to resources created by the automation pipeline.
 */
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/automation-functions.php';

require_admin();

$item = automation_available() ? automation_get_item((int)($_GET['item'] ?? 0)) : null;
$resource = ($item && $item['resource_id']) ? get_resource_by_id((int)$item['resource_id']) : null;
if (!$resource) {
    http_response_code(404);
    exit('Not found.');
}

$which = (string)($_GET['file'] ?? 'main');
if ($which === 'main') {
    $path = $resource['file_path'];
    $name = $resource['file_name'];
} else {
    $file = get_resource_file_by_id((int)$which);
    if (!$file || (int)$file['resource_id'] !== (int)$resource['id']) {
        http_response_code(404);
        exit('Not found.');
    }
    $path = $file['file_path'];
    $name = $file['file_name'];
}

$full = UPLOAD_PROTECTED_PATH . '/' . basename((string)$path);
if (!is_file($full)) {
    http_response_code(404);
    exit('File missing.');
}

$mime = match (strtolower(pathinfo($name, PATHINFO_EXTENSION))) {
    'pdf'  => 'application/pdf',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    default => 'application/octet-stream',
};
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $name) . '"');
header('Content-Length: ' . filesize($full));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($full);
