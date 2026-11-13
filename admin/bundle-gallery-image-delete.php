<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-functions.php';
require_once __DIR__ . '/../includes/bundle-functions.php';

require_admin();
$admin = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/bundles.php');
}

require_csrf();

$id = (int)($_POST['id'] ?? 0);
$bundleId = (int)($_POST['bundle_id'] ?? 0);
$image = $id > 0 ? get_bundle_gallery_image_by_id($id) : null;

if ($image && (int)$image['bundle_id'] === $bundleId) {
    delete_bundle_gallery_image($id);
    log_admin_action($admin['id'], 'delete_bundle_gallery_image', "Deleted gallery image #{$id} from bundle #{$bundleId}");
    flash_set('success', 'Preview image removed.');
} else {
    flash_set('error', 'Image not found.');
}

redirect('admin/bundles.php?edit=' . $bundleId);
