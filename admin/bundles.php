<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-functions.php';
require_once __DIR__ . '/../includes/upload-functions.php';
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
        $result = create_bundle($_POST, $_FILES);
        if ($result['success']) {
            log_admin_action($admin['id'], 'create_bundle', "Created bundle #{$result['id']}: " . clean_input($_POST['title'] ?? ''));
            flash_set('success', 'Bundle created.' . ($result['warning'] ? ' ' . $result['warning'] : ''));
            redirect('admin/bundles.php?edit=' . $result['id']);
        }
        $errors = $result['errors'];
        $editing = ['id' => null] + $_POST;
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $result = update_bundle($id, $_POST, $_FILES);
        if ($result['success']) {
            log_admin_action($admin['id'], 'update_bundle', "Bundle #{$id}");
            flash_set('success', 'Bundle updated.' . ($result['warning'] ? ' ' . $result['warning'] : ''));
            redirect('admin/bundles.php?edit=' . $id);
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
$existingGalleryImages = $isEditing ? get_bundle_gallery_images((int)$editing['id']) : [];

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
                            <tr><th></th><th>Title</th><th>Price</th><th>Status</th><th></th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bundles as $bundleRow): ?>
                                <?php $rowCoverUrl = bundle_cover_image_url($bundleRow); ?>
                                <tr>
                                    <td style="width:56px;">
                                        <?php if ($rowCoverUrl): ?>
                                            <img src="<?= e($rowCoverUrl) ?>" alt="" style="width:48px;height:36px;object-fit:cover;border-radius:0.375rem;">
                                        <?php else: ?>
                                            <div class="d-flex align-items-center justify-content-center bg-light text-secondary" style="width:48px;height:36px;border-radius:0.375rem;">
                                                <i class="fa-solid fa-box-open"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e($bundleRow['title']) ?><?php if (!empty($bundleRow['is_featured'])): ?> <span class="badge bg-warning text-dark"><i class="fa-solid fa-star"></i> Featured</span><?php endif; ?></td>
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

                <?php if ($isEditing && !empty($existingGalleryImages)): ?>
                <!-- Kept outside the main edit form below: an HTML <form> cannot be
                     nested inside another <form> (browsers silently close the outer
                     form early when they hit a nested one, which corrupted the real
                     "Save Changes" submission's own hidden "id" field). This one
                     shared, hidden form is reused for whichever image's Remove
                     button was clicked (see the script at the bottom of this file),
                     rather than one nested form per image. -->
                <form method="post" action="<?= e(base_url('admin/bundle-gallery-image-delete.php')) ?>" id="removeGalleryImageForm" class="d-none">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="id" id="removeGalleryImageId">
                    <input type="hidden" name="bundle_id" value="<?= (int)$editing['id'] ?>">
                </form>
                <?php endif; ?>
                <form method="post" action="<?= e(base_url('admin/bundles.php')) ?>" enctype="multipart/form-data" novalidate>
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

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label" for="price">Price (<?= e(CURRENCY) ?>)</label>
                            <input type="number" step="0.01" min="0.01" class="form-control <?= isset($errors['price']) ? 'is-invalid' : '' ?>"
                                   id="price" name="price" value="<?= e($editing['price'] ?? '') ?>" required>
                            <?php if (isset($errors['price'])): ?><div class="invalid-feedback"><?= e($errors['price']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="original_price">Original Price <span class="text-secondary fw-normal">(optional)</span></label>
                            <input type="number" step="0.01" min="0.01" class="form-control <?= isset($errors['original_price']) ? 'is-invalid' : '' ?>"
                                   id="original_price" name="original_price" value="<?= e($editing['original_price'] ?? '') ?>">
                            <p class="form-text">Shown crossed out with a "Save X%" badge. Leave blank to hide.</p>
                            <?php if (isset($errors['original_price'])): ?><div class="invalid-feedback"><?= e($errors['original_price']) ?></div><?php endif; ?>
                        </div>
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

                    <div class="mb-3">
                        <label class="form-label" for="cover_image">Cover Image <span class="text-secondary fw-normal">(optional)</span></label>
                        <?php $coverUrl = $isEditing ? bundle_cover_image_url($editing) : null; ?>
                        <?php if ($coverUrl): ?>
                            <div class="mb-2">
                                <img src="<?= e($coverUrl) ?>" alt="" style="width:120px;height:90px;object-fit:cover;border-radius:0.5rem;border:1px solid #e5e7eb;">
                            </div>
                        <?php endif; ?>
                        <input type="file" class="form-control <?= isset($errors['cover_image']) ? 'is-invalid' : '' ?>" id="cover_image" name="cover_image" accept="image/png,image/jpeg,image/webp">
                        <p class="form-text">Used on the homepage feature, the bundle listing, and as the fallback gallery image. <?= $coverUrl ? 'Choose a new file to replace the current image.' : '' ?></p>
                        <?php if (isset($errors['cover_image'])): ?><div class="invalid-feedback"><?= e($errors['cover_image']) ?></div><?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="gallery_images">Preview Gallery Images <span class="text-secondary fw-normal">(optional)</span></label>
                        <?php if (!empty($existingGalleryImages)): ?>
                            <div class="d-flex flex-wrap gap-2 mb-2">
                                <?php foreach ($existingGalleryImages as $galleryImage): ?>
                                    <div class="position-relative">
                                        <img src="<?= e(UPLOAD_BUNDLE_URL . '/' . rawurlencode($galleryImage['image'])) ?>" alt=""
                                             style="width:80px;height:80px;object-fit:cover;border-radius:0.5rem;border:1px solid #e5e7eb;">
                                        <button type="button" class="btn btn-sm btn-danger py-0 px-1 position-absolute top-0 end-0 m-1 js-remove-gallery-image"
                                                data-image-id="<?= (int)$galleryImage['id'] ?>" title="Remove" aria-label="Remove this preview image">
                                            <i class="fa-solid fa-xmark"></i>
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <input type="file" class="form-control" id="gallery_images" name="gallery_images[]" accept="image/png,image/jpeg,image/webp" multiple>
                        <p class="form-text">Shown as a browsable thumbnail + preview gallery on the bundle page (up to <?= (int)BUNDLE_GALLERY_MAX_IMAGES ?> recommended). New files are added to the gallery, not replaced.</p>
                    </div>

                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" id="is_published" name="is_published" value="1" <?= !empty($editing['is_published']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_published">Published (visible on the public Bundles page)</label>
                    </div>

                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" id="is_featured" name="is_featured" value="1" <?= !empty($editing['is_featured']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_featured">Featured on homepage <span class="text-secondary fw-normal">(only one shows at a time — the most recently updated featured bundle wins)</span></label>
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
<script>
(function () {
    // "Remove" on an existing gallery image: fills in and submits the shared
    // #removeGalleryImageForm declared above the main form (can't be a form
    // nested inside this page's main form — see that form's comment).
    var removeGalleryImageForm = document.getElementById('removeGalleryImageForm');
    if (removeGalleryImageForm) {
        document.querySelectorAll('.js-remove-gallery-image').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!confirm('Remove this preview image?')) {
                    return;
                }
                document.getElementById('removeGalleryImageId').value = btn.dataset.imageId;
                removeGalleryImageForm.submit();
            });
        });
    }
})();
</script>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
