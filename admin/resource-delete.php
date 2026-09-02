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
$resource = $id > 0 ? get_resource_by_id($id) : null;

if ($resource) {
    if (delete_resource($id)) {
        log_admin_action($admin['id'], 'delete_resource', "Deleted resource #{$id}: {$resource['title']}");
        flash_set('success', 'Resource deleted.');
    } else {
        error_log("admin/resource-delete.php: delete_resource(#{$id}) reported failure for \"{$resource['title']}\"");
        flash_set('error', 'Resource could not be deleted. Please try again or check the error log.');
    }
} else {
    flash_set('error', 'Resource not found.');
}

redirect('admin/resources.php');
