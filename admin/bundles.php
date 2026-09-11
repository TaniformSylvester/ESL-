<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-functions.php';
require_once __DIR__ . '/../includes/bundle-functions.php';
require_once __DIR__ . '/../includes/resource-functions.php';

require_admin();
$admin = current_user();

$errors = [];
$editing = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'create') {
        $result = create_bundle($_POST);
        if ($result['success']) {
            log_admin_action($admin['id'], 'create_bundle', "Created bundle #{$result['id']}: " . clean_input($_POST['title'] ?? ''));
            flash_set('success', 'Bundle created.');
            redirect('admin/bundles.php');
        }
        $errors = $result['errors'];
        $editing = ['id' => null] + $_POST;
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $result = update_bundle($id, $_POST);
        if ($result['success']) {
            log_admin_action($admin['id'], 'update_bundle', "Bundle #{$id}");
            flash_set('success', 'Bundle updated.');
            redirect('admin/bundles.php');
        }
        $errors = $result['errors'];
        $editing = ['id' => $id] + $_POST;
    }
}

$bundles = get_all_bundles_for_admin();
$allResourcesForPicker = get_all_resources_paginated(['status' => 'published', 'archive_status' => 'active'], 1, 1000)['items'];

$editId = (int)($_GET['edit'] ?? 0);
if (!$editing && $editId > 0) {
    $editing = get_bundle_by_id($editId);
}
$isEditing = !empty($editing['id']);
$selectedResourceIds = $isEditing
    ? (isset($_POST['resource_ids']) ? array_map('intval', $_POST['resource_ids']) : get_bundle_resource_ids((int)$editing['id']))
    : array_map('intval', $_POST['resource_ids'] ?? []);

$pageTitle = 'Bundles';
require_once __DIR__ . '/../includes/admin-header.php';
?>
<div class="row g-4">
    <div class="col-lg-7">
        <?php if (empty($bundles)): ?>
            <p class="text-secondary">No bundles yet. Add the first one using the form.</p>
        <?php else: ?>
            <div class="card shadow-sm border-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr><th>Title</th><th>Price</th><th>Status</th><th></th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bundles as $bundleRow): ?>
                                <tr>
                                    <td><?= e($bundleRow['title']) ?></td>
                                    <td><?= e(format_currency($bundleRow['price'])) ?></td>
                                    <td>
                                        <?php if ($bundleRow['is_published']): ?>
                                            <span class="badge bg-success">Published</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Draft</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?= e(base_url('bundle.php?slug=' . urlencode($bundleRow['slug']))) ?>" class="btn btn-sm btn-outline-secondary" target="_blank">View</a>
                                        <a href="<?= e(base_url('admin/bundles.php?edit=' . (int)$bundleRow['id'])) ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-5">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h2 class="h5 fw-bold mb-3"><?= $isEditing ? 'Edit Bundle' : 'Add Bundle' ?></h2>

                <form method="post" action="<?= e(base_url('admin/bundles.php')) ?>" novalidate>
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="<?= $isEditing ? 'update' : 'create' ?>">
                    <?php if ($isEditing): ?>
                        <input type="hidden" name="id" value="<?= (int)$editing['id'] ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label" for="title">Title</label>
                        <input type="text" class="form-control <?= isset($errors['title']) ? 'is-invalid' : '' ?>"
                               id="title" name="title" value="<?= e($editing['title'] ?? '') ?>" required maxlength="200">
                        <?php if (isset($errors['title'])): ?><div class="invalid-feedback"><?= e($errors['title']) ?></div><?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="description">Description <span class="text-secondary fw-normal">(optional)</span></label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?= e($editing['description'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="price">Price (<?= e(CURRENCY) ?>)</label>
                        <input type="number" step="0.01" min="0.01" class="form-control <?= isset($errors['price']) ? 'is-invalid' : '' ?>"
                               id="price" name="price" value="<?= e($editing['price'] ?? '') ?>" required>
                        <?php if (isset($errors['price'])): ?><div class="invalid-feedback"><?= e($errors['price']) ?></div><?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="resource_ids">Included Resources</label>
                        <select class="form-select <?= isset($errors['resource_ids']) ? 'is-invalid' : '' ?>" id="resource_ids" name="resource_ids[]" multiple size="10" required>
                            <?php foreach ($allResourcesForPicker as $resourceOption): ?>
                                <option value="<?= (int)$resourceOption['id'] ?>" <?= in_array((int)$resourceOption['id'], $selectedResourceIds, true) ? 'selected' : '' ?>>
                                    <?= e($resourceOption['title']) ?> <?= $resourceOption['is_free'] ? '(Free)' : '(Members Only)' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="form-text">Ctrl/Cmd-click to select multiple. A bundle's real value is bypassing the Members Only gate, so it usually only makes sense with Pro resources — but any mix is allowed.</p>
                        <?php if (isset($errors['resource_ids'])): ?><div class="invalid-feedback"><?= e($errors['resource_ids']) ?></div><?php endif; ?>
                    </div>

                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" id="is_published" name="is_published" value="1" <?= !empty($editing['is_published']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_published">Published (visible on the public Bundles page)</label>
                    </div>

                    <button type="submit" class="btn btn-primary"><?= $isEditing ? 'Save Changes' : 'Add Bundle' ?></button>
                    <?php if ($isEditing): ?>
                        <a href="<?= e(base_url('admin/bundles.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        <?php if ($isEditing): ?>
            <p class="text-secondary small mt-3">There's no delete action for bundles — only publish/unpublish — since deleting one a teacher already paid for would break their access. Untick "Published" to hide a bundle from new buyers.</p>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
