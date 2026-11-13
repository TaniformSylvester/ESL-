<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-functions.php';
require_once __DIR__ . '/../includes/resource-functions.php';
require_once __DIR__ . '/../includes/request-functions.php';
require_once __DIR__ . '/../includes/email.php';

require_admin();
$admin = current_user();

$id = (int)($_GET['id'] ?? 0);
$request = $id > 0 ? get_request_by_id($id) : null;

if (!$request) {
    flash_set('error', 'That request could not be found.');
    redirect('admin/requests.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'update_status') {
        $status = (string)($_POST['status'] ?? '');
        if (update_request_status($id, $status)) {
            log_admin_action($admin['id'], 'update_request_status', "Request #{$id} -> {$status}");
            flash_set('success', 'Status updated.');
        } else {
            flash_set('error', 'Invalid status.');
        }
    } elseif ($action === 'save_notes') {
        set_request_admin_notes($id, clean_input($_POST['admin_notes'] ?? ''));
        log_admin_action($admin['id'], 'update_request_notes', "Request #{$id}");
        flash_set('success', 'Notes saved.');
    } elseif ($action === 'link_resource') {
        $resourceId = (int)($_POST['resource_id'] ?? 0);
        $result = $resourceId > 0 ? link_request_to_resource($id, $resourceId) : ['success' => false, 'error' => 'Please choose a resource.'];
        if ($result['success']) {
            log_admin_action($admin['id'], 'link_request_resource', "Request #{$id} -> Resource #{$resourceId}");
            flash_set('success', 'Request linked to resource and marked published. Supporters with an email have been notified.');
        } else {
            flash_set('error', $result['error']);
        }
    } elseif ($action === 'mark_duplicate') {
        $duplicateOfId = (int)($_POST['duplicate_of_id'] ?? 0);
        $result = $duplicateOfId > 0 ? mark_request_duplicate($id, $duplicateOfId) : ['success' => false, 'error' => 'Please choose the request this duplicates.'];
        if ($result['success']) {
            log_admin_action($admin['id'], 'mark_request_duplicate', "Request #{$id} -> duplicate of #{$duplicateOfId}");
            flash_set('success', 'Marked as a duplicate — supporters were merged into the original request.');
            redirect('admin/request-detail.php?id=' . $duplicateOfId);
        } else {
            flash_set('error', $result['error']);
        }
    }

    redirect('admin/request-detail.php?id=' . $id);
}

$demand = get_request_demand($id);
$formats = !empty($request['preferred_formats']) ? explode(',', $request['preferred_formats']) : [];
$allResourcesForPicker = get_all_resources_paginated(['status' => 'published', 'archive_status' => 'active'], 1, 1000)['items'];
$otherRequestsForDuplicate = getDB()->prepare('SELECT id, topic FROM resource_requests WHERE id != ? ORDER BY created_at DESC LIMIT 200');
$otherRequestsForDuplicate->execute([$id]);
$otherRequestsForDuplicate = $otherRequestsForDuplicate->fetchAll();

$pageTitle = 'Request: ' . $request['topic'];
require_once __DIR__ . '/../includes/admin-header.php';
?>
<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <a href="<?= e(base_url('admin/requests.php')) ?>" class="small text-decoration-none">&larr; All Requests</a>
        <h1 class="h4 fw-bold mt-1 mb-0">Request #<?= (int)$request['id'] ?>: <?= e($request['topic']) ?></h1>
    </div>
    <?php
        $statusBadge = match ($request['status']) {
            'published' => 'bg-success', 'declined' => 'bg-danger', 'in_progress' => 'bg-warning text-dark',
            'planned' => 'bg-primary', 'reviewing' => 'bg-info text-dark', 'duplicate' => 'bg-light text-dark border',
            default => 'bg-secondary',
        };
    ?>
    <span class="badge <?= $statusBadge ?> fs-6"><?= e(REQUEST_STATUSES[$request['status']] ?? $request['status']) ?></span>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h2 class="h6 fw-bold text-secondary text-uppercase mb-3">Request Details</h2>
                <dl class="row small mb-0">
                    <dt class="col-4">Subject</dt><dd class="col-8"><?= e($request['subject_name'] ?? '&mdash;') ?></dd>
                    <dt class="col-4">Grade</dt><dd class="col-8"><?= e($request['grade_level'] ?? '&mdash;') ?></dd>
                    <dt class="col-4">Topic</dt><dd class="col-8"><?= e($request['topic']) ?></dd>
                    <dt class="col-4">Resource Type</dt><dd class="col-8"><?= e($request['resource_type'] === 'Other' ? ($request['resource_type_other'] ?: 'Other') : $request['resource_type']) ?></dd>
                    <dt class="col-4">Difficulty</dt><dd class="col-8"><?= e($request['difficulty'] ? (REQUEST_DIFFICULTIES[$request['difficulty']] ?? $request['difficulty']) : '&mdash;') ?></dd>
                    <dt class="col-4">Preferred Format</dt><dd class="col-8"><?= !empty($formats) ? e(implode(', ', array_map(static fn($f) => REQUEST_FORMATS[$f] ?? $f, $formats))) : '&mdash;' ?></dd>
                    <dt class="col-4">Submitted By</dt><dd class="col-8"><?= e($request['name'] ?: 'Anonymous') ?><?= $request['email'] ? ' &middot; ' . e($request['email']) : '' ?></dd>
                    <dt class="col-4">Submitted</dt><dd class="col-8"><?= e(format_date($request['created_at'])) ?></dd>
                </dl>
                <hr>
                <h3 class="h6 fw-bold text-secondary text-uppercase mb-2">Description</h3>
                <p class="mb-3"><?= nl2br(e($request['description'])) ?></p>
                <?php if (!empty($request['additional_notes'])): ?>
                    <h3 class="h6 fw-bold text-secondary text-uppercase mb-2">Additional Notes</h3>
                    <p class="mb-0"><?= nl2br(e($request['additional_notes'])) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h2 class="h6 fw-bold text-secondary text-uppercase mb-3">Admin Notes <span class="text-secondary fw-normal text-lowercase">(private — never shown to teachers)</span></h2>
                <form method="post" action="<?= e(base_url('admin/request-detail.php?id=' . $id)) ?>">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="save_notes">
                    <textarea class="form-control mb-2" name="admin_notes" rows="4"><?= e($request['admin_notes'] ?? '') ?></textarea>
                    <button type="submit" class="btn btn-outline-secondary btn-sm">Save Notes</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h2 class="h6 fw-bold text-secondary text-uppercase mb-3">Demand</h2>
                <p class="h4 fw-bold mb-1"><?= (int)$demand['count'] ?> teacher<?= (int)$demand['count'] === 1 ? '' : 's' ?> requested this</p>
                <?php if ($demand['first_at']): ?>
                    <p class="small text-secondary mb-0">First request: <?= e(format_date($demand['first_at'])) ?></p>
                    <p class="small text-secondary mb-0">Latest request: <?= e(format_date($demand['last_at'])) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h2 class="h6 fw-bold text-secondary text-uppercase mb-3">Change Status</h2>
                <form method="post" action="<?= e(base_url('admin/request-detail.php?id=' . $id)) ?>" class="d-flex gap-2">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="update_status">
                    <select name="status" class="form-select">
                        <?php foreach (REQUEST_STATUSES as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= $request['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary text-nowrap">Update</button>
                </form>
            </div>
        </div>

        <?php if (!empty($request['linked_resource_id'])): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h2 class="h6 fw-bold text-secondary text-uppercase mb-2">Linked Resource</h2>
                    <a href="<?= e(base_url('resource.php?slug=' . urlencode($request['linked_resource_slug']))) ?>" target="_blank"><?= e($request['linked_resource_title']) ?></a>
                    <?php if ($request['completed_at']): ?><p class="small text-secondary mb-0 mt-1">Completed <?= e(format_date($request['completed_at'])) ?></p><?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h2 class="h6 fw-bold text-secondary text-uppercase mb-3">Link to Resource</h2>
                    <form method="post" action="<?= e(base_url('admin/request-detail.php?id=' . $id)) ?>" class="mb-3">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="link_resource">
                        <select name="resource_id" class="form-select mb-2">
                            <option value="">Choose an existing resource&hellip;</option>
                            <?php foreach ($allResourcesForPicker as $resourceOption): ?>
                                <option value="<?= (int)$resourceOption['id'] ?>"><?= e($resourceOption['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-success btn-sm">Link &amp; Mark Published</button>
                    </form>
                    <a href="<?= e(base_url('admin/resource-add.php?from_request=' . $id)) ?>" class="btn btn-outline-primary btn-sm">
                        <i class="fa-solid fa-plus me-1"></i>Create Resource From This Request
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($request['status'] !== 'duplicate'): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h2 class="h6 fw-bold text-secondary text-uppercase mb-3">Mark as Duplicate</h2>
                    <form method="post" action="<?= e(base_url('admin/request-detail.php?id=' . $id)) ?>">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="mark_duplicate">
                        <select name="duplicate_of_id" class="form-select mb-2">
                            <option value="">This is a duplicate of&hellip;</option>
                            <?php foreach ($otherRequestsForDuplicate as $other): ?>
                                <option value="<?= (int)$other['id'] ?>">#<?= (int)$other['id'] ?> &mdash; <?= e($other['topic']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-outline-secondary btn-sm">Mark Duplicate &amp; Merge Supporters</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
