<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-functions.php';
require_once __DIR__ . '/../includes/guide-functions.php';

require_admin();
$admin = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/guides.php');
}

require_csrf();

$id = (int)($_POST['id'] ?? 0);
$guide = $id > 0 ? get_guide_by_id($id) : null;

if ($guide) {
    delete_guide($id);
    log_admin_action($admin['id'], 'delete_guide', "Deleted guide #{$id}: {$guide['title']}");
    flash_set('success', 'Guide deleted.');
} else {
    flash_set('error', 'Guide not found.');
}

redirect('admin/guides.php');
