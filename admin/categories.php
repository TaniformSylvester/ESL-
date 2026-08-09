<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-functions.php';
require_once __DIR__ . '/../includes/resource-functions.php';

require_admin();
$admin = current_user();

$errors = [];
$editing = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'create') {
        $result = create_category($_POST);
        if ($result['success']) {
            log_admin_action($admin['id'], 'create_category', clean_input($_POST['name'] ?? ''));
            flash_set('success', 'Category created.');
            redirect('admin/categories.php');
        }
        $errors = $result['errors'];
        $editing = ['id' => null] + $_POST;
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $result = update_category($id, $_POST);
        if ($result['success']) {
            log_admin_action($admin['id'], 'update_category', "Category #{$id}");
            flash_set('success', 'Category updated.');
            redirect('admin/categories.php');
        }
        $errors = $result['errors'];
        $editing = ['id' => $id] + $_POST;
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        delete_category($id);
        log_admin_action($admin['id'], 'delete_category', "Category #{$id}");
        flash_set('success', 'Category deleted. Any resources in it are now uncategorized.');
        redirect('admin/categories.php');
    }
}

$categoriesGrouped = get_categories_grouped();

$pageTitle = 'Categories';
require_once __DIR__ . '/../includes/admin-header.php';
?>
<div class="row g-4">
    <div class="col-lg-7">
        <?php if (empty($categoriesGrouped)): ?>
            <p class="text-secondary">No categories yet. Add the first one using the form.</p>
        <?php endif; ?>

        <?php foreach ($categoriesGrouped as $groupName => $categories): ?>
            <h2 class="h6 fw-bold text-secondary text-uppercase mt-3"><?= e($groupName) ?></h2>
            <div class="card shadow-sm border-0 mb-3">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr><th>Name</th><th>Slug</th><th>Order</th><th></th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td><?= e($category['name']) ?></td>
                                    <td class="text-secondary small"><?= e($category['slug']) ?></td>
                                    <td><?= (int)$category['sort_order'] ?></td>
                                    <td class="text-end">
                                        <a href="<?= e(base_url('admin/categories.php?edit=' . $category['id'])) ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                                        <form method="post" action="<?= e(base_url('admin/categories.php')) ?>" class="d-inline"
                                              onsubmit="return confirm('Delete this category? Resources in it will become uncategorized, not deleted.');">
                                            <?php csrf_field(); ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int)$category['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="col-lg-5">
        <?php
            $editId = (int)($_GET['edit'] ?? 0);
            if (!$editing && $editId > 0) {
                $editing = get_category_by_id($editId);
            }
            $isEditing = !empty($editing['id']);
        ?>
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h2 class="h5 fw-bold mb-3"><?= $isEditing ? 'Edit Category' : 'Add Category' ?></h2>

                <form method="post" action="<?= e(base_url('admin/categories.php')) ?>" novalidate>
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="<?= $isEditing ? 'update' : 'create' ?>">
                    <?php if ($isEditing): ?>
                        <input type="hidden" name="id" value="<?= (int)$editing['id'] ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label" for="name">Name</label>
                        <input type="text" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                               id="name" name="name" value="<?= e($editing['name'] ?? '') ?>" required maxlength="100">
                        <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= e($errors['name']) ?></div><?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="group_name">Group</label>
                        <input type="text" class="form-control <?= isset($errors['group_name']) ? 'is-invalid' : '' ?>"
                               id="group_name" name="group_name" value="<?= e($editing['group_name'] ?? 'Teaching Resources') ?>" required maxlength="50" list="group-suggestions">
                        <datalist id="group-suggestions">
                            <option value="English Skills">
                            <option value="Teaching Resources">
                        </datalist>
                        <?php if (isset($errors['group_name'])): ?><div class="invalid-feedback"><?= e($errors['group_name']) ?></div><?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="sort_order">Sort Order</label>
                        <input type="number" class="form-control" id="sort_order" name="sort_order" value="<?= e($editing['sort_order'] ?? '0') ?>">
                    </div>

                    <button type="submit" class="btn btn-primary"><?= $isEditing ? 'Save Changes' : 'Add Category' ?></button>
                    <?php if ($isEditing): ?>
                        <a href="<?= e(base_url('admin/categories.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
