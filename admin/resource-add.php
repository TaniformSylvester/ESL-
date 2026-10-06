<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-functions.php';
require_once __DIR__ . '/../includes/upload-functions.php';
require_once __DIR__ . '/../includes/resource-functions.php';
require_once __DIR__ . '/../includes/subject-functions.php';
require_once __DIR__ . '/../includes/guide-functions.php';
require_once __DIR__ . '/../includes/request-functions.php';

require_admin();
$admin = current_user();

$errors = [];
$old = [];
$fromRequest = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $result = create_resource($_POST, $_FILES);

    if ($result['success']) {
        log_admin_action($admin['id'], 'create_resource', "Created resource #{$result['id']}: " . clean_input($_POST['title'] ?? ''));
        flash_set('success', 'Resource created.');
        redirect('admin/resources.php');
    }

    $errors = $result['errors'];
    $old = $_POST;
} elseif (!empty($_GET['from_request'])) {
    // "Create Resource From Request" — pre-fills the existing add-resource
    // form from a teacher's request rather than building a second, separate
    // upload path. Saves the admin re-typing what the teacher already told
    // us; the actual file upload/publishing is entirely the normal flow.
    $fromRequest = get_request_by_id((int)$_GET['from_request']);
    if ($fromRequest) {
        $requestedType = $fromRequest['resource_type'] === 'Other' ? '' : $fromRequest['resource_type'];
        $notesPrefix = $fromRequest['resource_type'] === 'Other' && !empty($fromRequest['resource_type_other'])
            ? 'Requested format: ' . $fromRequest['resource_type_other'] . "\n\n" : '';
        $old = [
            'title'         => $fromRequest['topic'],
            'subject_id'    => $fromRequest['subject_id'],
            'grade_level'   => $fromRequest['grade_level'],
            'topic'         => $fromRequest['topic'],
            'resource_type' => $requestedType,
            'description'   => $notesPrefix . $fromRequest['description'] . (!empty($fromRequest['additional_notes']) ? "\n\nAdditional notes: " . $fromRequest['additional_notes'] : ''),
        ];
    }
}

$resource = null;
$isEdit = false;
$actionUrl = base_url('admin/resource-add.php');
$categoriesGrouped = get_categories_grouped();
$subjects = get_all_subjects();
$allResourcesForPicker = get_all_resources_paginated(['status' => 'published', 'archive_status' => 'active'], 1, 1000)['items'];
$allGuidesForPicker = get_all_guides_paginated(['status' => 'published'], 1, 1000)['items'];
$selectedRelatedIds = array_map('intval', $old['related_resource_ids'] ?? []);
$selectedGuideIds = array_map('intval', $old['related_guide_ids'] ?? []);
$existingFiles = [];

$pageTitle = 'Add Resource';
require_once __DIR__ . '/../includes/admin-header.php';
if ($fromRequest): ?>
    <div class="alert alert-info">
        Pre-filled from <a href="<?= e(base_url('admin/request-detail.php?id=' . (int)$fromRequest['id'])) ?>">Request #<?= (int)$fromRequest['id'] ?></a>.
        Once this resource is published, go back to that request and use <strong>Link to Resource</strong> to mark it published and notify the teachers who asked for it.
    </div>
<?php endif;
require __DIR__ . '/../includes/admin-resource-form.php';
require_once __DIR__ . '/../includes/admin-footer.php';
