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
$resourceId = (int)($_POST['resource_id'] ?? 0);
$file = $id > 0 ? get_resource_file_by_id($id) : null;

if ($file && (int)$file['resource_id'] === $resourceId) {
    delete_resource_file($id);
    log_admin_action($admin['id'], 'delete_resource_file', "Deleted additional file #{$id} from resource #{$resourceId}");
    flash_set('success', 'File removed.');
} else {
    flash_set('error', 'File not found.');
}

redirect('admin/resource-edit.php?id=' . $resourceId);
