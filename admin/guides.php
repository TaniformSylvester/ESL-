<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-functions.php';
require_once __DIR__ . '/../includes/guide-functions.php';

require_admin();

$filters = [
    'search'   => trim((string)($_GET['search'] ?? '')),
    'category' => trim((string)($_GET['category'] ?? '')),
    'status'   => trim((string)($_GET['status'] ?? '')),
];
$page = max(1, (int)($_GET['page'] ?? 1));
$result = get_all_guides_paginated($filters, $page, ADMIN_ROWS_PER_PAGE);

$pageTitle = 'Teacher Hub Guides';
require_once __DIR__ . '/../includes/admin-header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-secondary mb-0"><?= (int)$result['total'] ?> guide<?= $result['total'] === 1 ? '' : 's' ?></p>
    <a href="<?= e(base_url('admin/guide-add.php')) ?>" class="btn btn-primary"><i class="fa-solid fa-plus me-1"></i>Add Guide</a>
</div>

<form method="get" action="<?= e(base_url('admin/guides.php')) ?>" class="row g-2 mb-4">
    <div class="col-md-4">
        <input type="text" name="search" class="form-control" placeholder="Search title&hellip;" value="<?= e($filters['search']) ?>">
    </div>
    <div class="col-md-3">
        <select name="category" class="form-select">
            <option value="">All Categories</option>
            <?php foreach (GUIDE_CATEGORIES as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= $filters['category'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <select name="status" class="form-select">
            <option value="">All Statuses</option>
            <option value="published" <?= $filters['status'] === 'published' ? 'selected' : '' ?>>Published</option>
            <option value="draft" <?= $filters['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
        </select>
    </div>
    <div class="col-md-2">
        <button type="submit" class="btn btn-outline-secondary w-100">Filter</button>
    </div>
</form>

<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($result['items'])): ?>
                    <tr><td colspan="4" class="text-center text-secondary py-4">No guides found.</td></tr>
                <?php endif; ?>
                <?php foreach ($result['items'] as $guide): ?>
                    <tr>
                        <td><?= e($guide['title']) ?></td>
                        <td class="small"><?= e(GUIDE_CATEGORIES[$guide['category']] ?? $guide['category']) ?></td>
                        <td><span class="badge <?= $guide['is_published'] ? 'bg-success' : 'bg-secondary' ?>"><?= $guide['is_published'] ? 'Published' : 'Draft' ?></span></td>
                        <td class="text-end text-nowrap">
                            <a href="<?= e(base_url('teacher-hub-guide.php?slug=' . urlencode($guide['slug']))) ?>" class="btn btn-sm btn-outline-secondary" target="_blank">View</a>
                            <a href="<?= e(base_url('admin/guide-edit.php?id=' . $guide['id'])) ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                            <form method="post" action="<?= e(base_url('admin/guide-delete.php')) ?>" class="d-inline"
                                  onsubmit="return confirm('Delete this guide? This cannot be undone.');">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="id" value="<?= (int)$guide['id'] ?>">
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
