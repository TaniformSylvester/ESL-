<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-functions.php';
require_once __DIR__ . '/../includes/guide-functions.php';
require_once __DIR__ . '/../includes/subject-functions.php';
require_once __DIR__ . '/../includes/resource-functions.php';

require_admin();
$admin = current_user();

$guideId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$guide = $guideId > 0 ? get_guide_by_id($guideId) : null;

if (!$guide) {
    http_response_code(404);
    require __DIR__ . '/../404.php';
    exit;
}

$errors = [];
$old = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $result = update_guide($guideId, $_POST);

    if ($result['success']) {
        set_guide_related_resources($guideId, $_POST['resource_ids'] ?? []);
        log_admin_action($admin['id'], 'update_guide', "Updated guide #{$guideId}");
        flash_set('success', 'Guide updated.');
        redirect('admin/guides.php');
    }

    $errors = $result['errors'];
    $old = $_POST;
    $guide = array_merge($guide, $_POST);
}

$isEdit = true;
$actionUrl = base_url('admin/guide-edit.php?id=' . $guideId);
$subjects = get_all_subjects();
$allResources = get_all_resources_paginated(['status' => 'published'], 1, 1000)['items'];
$selectedResourceIds = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? array_map('intval', $_POST['resource_ids'] ?? [])
    : array_map(static fn(array $r): int => (int)$r['id'], get_guide_related_resources($guideId));

$pageTitle = 'Edit Guide';
require_once __DIR__ . '/../includes/admin-header.php';
require __DIR__ . '/../includes/admin-guide-form.php';
require_once __DIR__ . '/../includes/admin-footer.php';
