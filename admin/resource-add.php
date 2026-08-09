<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-functions.php';
require_once __DIR__ . '/../includes/upload-functions.php';
require_once __DIR__ . '/../includes/resource-functions.php';

require_admin();
$admin = current_user();

$errors = [];
$old = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $result = create_resource($_POST, $_FILES);

    if ($result['success']) {
        log_admin_action($admin['id'], 'create_resource', "Created resource #{$result['id']}: " . clean_input($_POST['title'] ?? ''));
        flash_set('success', 'Resource created.');
        redirect('admin/resources.php');
    }

    $errors = $result['errors'];
    $old = $_POST;
}

$resource = null;
$isEdit = false;
$actionUrl = base_url('admin/resource-add.php');
$categoriesGrouped = get_categories_grouped();

$pageTitle = 'Add Resource';
require_once __DIR__ . '/../includes/admin-header.php';
require __DIR__ . '/../includes/admin-resource-form.php';
require_once __DIR__ . '/../includes/admin-footer.php';
