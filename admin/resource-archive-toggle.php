<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-functions.php';
require_once __DIR__ . '/../includes/resource-functions.php';

require_admin();
$admin = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/resources.php');
}

require_csrf();

$id = (int)($_POST['id'] ?? 0);
$action = (string)($_POST['action'] ?? '');
$resource = $id > 0 ? get_resource_by_id($id) : null;

if (!$resource) {
    flash_set('error', 'Resource not found.');
    redirect('admin/resources.php');
}

if ($action === 'archive') {
    $redirectResourceId = (int)($_POST['redirect_resource_id'] ?? 0);
    archive_resource($id, $redirectResourceId > 0 ? $redirectResourceId : null);
    log_admin_action($admin['id'], 'archive_resource', "Archived resource #{$id}: {$resource['title']}");
    flash_set('success', 'Resource archived. It is hidden from the public site but its record, reviews, and download history are preserved.');
} elseif ($action === 'unarchive') {
    unarchive_resource($id);
    log_admin_action($admin['id'], 'unarchive_resource', "Restored resource #{$id}: {$resource['title']}");
    flash_set('success', 'Resource restored to active.');
} else {
    flash_set('error', 'Unknown action.');
}

redirect('admin/resources.php');
