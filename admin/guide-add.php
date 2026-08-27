<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-functions.php';
require_once __DIR__ . '/../includes/guide-functions.php';
require_once __DIR__ . '/../includes/subject-functions.php';
require_once __DIR__ . '/../includes/resource-functions.php';

require_admin();
$admin = current_user();

$errors = [];
$old = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $result = create_guide($_POST);

    if ($result['success']) {
        set_guide_related_resources($result['id'], $_POST['resource_ids'] ?? []);
        log_admin_action($admin['id'], 'create_guide', "Created guide #{$result['id']}: " . clean_input($_POST['title'] ?? ''));
        flash_set('success', 'Guide created.');
        redirect('admin/guides.php');
    }

    $errors = $result['errors'];
    $old = $_POST;
}

$guide = null;
$isEdit = false;
$actionUrl = base_url('admin/guide-add.php');
$subjects = get_all_subjects();
$allResources = get_all_resources_paginated(['status' => 'published'], 1, 1000)['items'];
$selectedResourceIds = array_map('intval', $_POST['resource_ids'] ?? []);

$pageTitle = 'Add Guide';
require_once __DIR__ . '/../includes/admin-header.php';
require __DIR__ . '/../includes/admin-guide-form.php';
require_once __DIR__ . '/../includes/admin-footer.php';
