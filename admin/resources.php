<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-functions.php';
require_once __DIR__ . '/../includes/resource-functions.php';
require_once __DIR__ . '/../includes/subject-functions.php';

require_admin();

$filters = [
    'search'         => trim((string)($_GET['search'] ?? '')),
    'subject_id'     => (int)($_GET['subject_id'] ?? 0),
    'resource_type'  => trim((string)($_GET['resource_type'] ?? '')),
    'category_id'    => (int)($_GET['category_id'] ?? 0),
    'status'         => trim((string)($_GET['status'] ?? '')),
    'archive_status' => trim((string)($_GET['archive_status'] ?? '')),
];
$page = max(1, (int)($_GET['page'] ?? 1));
$result = get_all_resources_paginated($filters, $page, ADMIN_ROWS_PER_PAGE);
$categoriesGrouped = get_categories_grouped();
$subjects = get_all_subjects();

$pageTitle = 'Resources';
require_once __DIR__ . '/../includes/admin-header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-secondary mb-0"><?= (int)$result['total'] ?> resource<?= $result['total'] === 1 ? '' : 's' ?></p>
    <a href="<?= e(base_url('admin/resource-add.php')) ?>" class="btn btn-primary"><i class="fa-solid fa-plus me-1"></i>Add Resource</a>
</div>

<form method="get" action="<?= e(base_url('admin/resources.php')) ?>" class="row g-2 mb-4">
    <div class="col-md-3">
        <input type="text" name="search" class="form-control" placeholder="Search title, topic&hellip;" value="<?= e($filters['search']) ?>">
    </div>
    <div class="col-md-2">
        <select name="subject_id" class="form-select">
            <option value="">All Subjects</option>
            <?php foreach ($subjects as $subjectOption): ?>
                <option value="<?= (int)$subjectOption['id'] ?>" <?= $filters['subject_id'] === (int)$subjectOption['id'] ? 'selected' : '' ?>><?= e($subjectOption['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <select name="resource_type" class="form-select">
            <option value="">All Types</option>
            <?php foreach (RESOURCE_TYPES as $type): ?>
                <option value="<?= e($type) ?>" <?= $filters['resource_type'] === $type ? 'selected' : '' ?>><?= e($type) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <select name="category_id" class="form-select">
            <option value="">All Categories</option>
            <?php foreach ($categoriesGrouped as $groupName => $categories): ?>
                <optgroup label="<?= e($groupName) ?>">
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int)$category['id'] ?>" <?= $filters['category_id'] === (int)$category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <select name="status" class="form-select">
            <option value="">All Statuses</option>
            <option value="published" <?= $filters['status'] === 'published' ? 'selected' : '' ?>>Published</option>
            <option value="draft" <?= $filters['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
        </select>
    </div>
    <div class="col-md-2">
        <select name="archive_status" class="form-select">
            <option value="">Active + Archived</option>
            <option value="active" <?= $filters['archive_status'] === 'active' ? 'selected' : '' ?>>Active Only</option>
            <option value="archived" <?= $filters['archive_status'] === 'archived' ? 'selected' : '' ?>>Archived Only</option>
        </select>
    </div>
    <div class="col-md-1 d-grid">
        <button type="submit" class="btn btn-outline-secondary"><i class="fa-solid fa-magnifying-glass"></i></button>
    </div>
</form>

<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Subject</th>
                    <th>Type</th>
                    <th>Category</th>
                    <th>Access</th>
                    <th>Status</th>
                    <th>Archive</th>
                    <th>Downloads</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($result['items'])): ?>
                    <tr><td colspan="9" class="text-center text-secondary py-4">No resources found.</td></tr>
                <?php endif; ?>
                <?php foreach ($result['items'] as $resource): ?>
                    <tr>
                        <td><?= e($resource['title']) ?></td>
                        <td class="small text-secondary"><?= e($resource['subject_name'] ?? '&mdash;') ?></td>
                        <td class="small"><?= e($resource['resource_type']) ?></td>
                        <td class="small text-secondary"><?= e($resource['category_name'] ?? '&mdash;') ?></td>
                        <td><span class="badge <?= $resource['is_free'] ? 'badge-free' : 'badge-members' ?>"><?= $resource['is_free'] ? 'Free' : 'Members' ?></span></td>
                        <td><span class="badge <?= $resource['is_published'] ? 'bg-success' : 'bg-secondary' ?>"><?= $resource['is_published'] ? 'Published' : 'Draft' ?></span></td>
                        <td><span class="badge <?= $resource['status'] === 'archived' ? 'bg-warning text-dark' : 'bg-light text-dark border' ?>"><?= $resource['status'] === 'archived' ? 'Archived' : 'Active' ?></span></td>
                        <td><?= (int)$resource['download_count'] ?></td>
                        <td class="text-end text-nowrap">
                            <a href="<?= e(base_url('resource.php?slug=' . urlencode($resource['slug']))) ?>" class="btn btn-sm btn-outline-secondary" target="_blank">View</a>
                            <a href="<?= e(base_url('admin/resource-edit.php?id=' . $resource['id'])) ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                            <?php if ($resource['status'] === 'archived'): ?>
                                <form method="post" action="<?= e(base_url('admin/resource-archive-toggle.php')) ?>" class="d-inline">
                                    <?php csrf_field(); ?>
                                    <input type="hidden" name="id" value="<?= (int)$resource['id'] ?>">
                                    <input type="hidden" name="action" value="unarchive">
                                    <button type="submit" class="btn btn-sm btn-outline-success">Restore</button>
                                </form>
                            <?php else: ?>
                                <form method="post" action="<?= e(base_url('admin/resource-archive-toggle.php')) ?>" class="d-inline"
                                      onsubmit="return confirm('Archive this resource? It will be hidden from the public site, but its record, reviews, and download history are kept.');">
                                    <?php csrf_field(); ?>
                                    <input type="hidden" name="id" value="<?= (int)$resource['id'] ?>">
                                    <input type="hidden" name="action" value="archive">
                                    <button type="submit" class="btn btn-sm btn-outline-warning">Archive</button>
                                </form>
                            <?php endif; ?>
                            <form method="post" action="<?= e(base_url('admin/resource-delete.php')) ?>" class="d-inline"
                                  onsubmit="return confirm('Delete this resource? This also removes its uploaded files. This cannot be undone.');">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="id" value="<?= (int)$resource['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4"><?= render_pagination($result['page'], $result['total_pages']) ?></div>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
