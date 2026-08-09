<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-functions.php';
require_once __DIR__ . '/../includes/upload-functions.php';
require_once __DIR__ . '/../includes/resource-functions.php';

require_admin();
$admin = current_user();

$resourceId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$resource = $resourceId > 0 ? get_resource_by_id($resourceId) : null;

if (!$resource) {
    http_response_code(404);
    require __DIR__ . '/../404.php';
    exit;
}

$errors = [];
$old = []; // unused in edit mode (the form always reads from $resource), kept only so the shared partial's closure has a defined variable

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $result = update_resource($resourceId, $_POST, $_FILES);

    if ($result['success']) {
        log_admin_action($admin['id'], 'update_resource', "Updated resource #{$resourceId}");
        flash_set('success', 'Resource updated.');
        redirect('admin/resources.php');
    }

    $errors = $result['errors'];
    $old = $_POST;
    $resource = array_merge($resource, $_POST); // repopulate text fields; file fields still read from $resource below
}

$isEdit = true;
$actionUrl = base_url('admin/resource-edit.php?id=' . $resourceId);
$categoriesGrouped = get_categories_grouped();

$pageTitle = 'Edit Resource';
require_once __DIR__ . '/../includes/admin-header.php';
require __DIR__ . '/../includes/admin-resource-form.php';
require_once __DIR__ . '/../includes/admin-footer.php';
