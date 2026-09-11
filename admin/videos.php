<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-functions.php';
require_once __DIR__ . '/../includes/video-functions.php';
require_once __DIR__ . '/../includes/resource-functions.php';
require_once __DIR__ . '/../includes/subject-functions.php';

require_admin();
$admin = current_user();

$errors = [];
$editing = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'create') {
        $result = create_video($_POST, $_FILES);
        if ($result['success']) {
            log_admin_action($admin['id'], 'create_video', "Created video #{$result['id']}: " . clean_input($_POST['title'] ?? ''));
            flash_set('success', 'Video created.');
            redirect('admin/videos.php');
        }
        $errors = $result['errors'];
        $editing = ['id' => null] + $_POST;
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $result = update_video($id, $_POST, $_FILES);
        if ($result['success']) {
            log_admin_action($admin['id'], 'update_video', "Video #{$id}");
            flash_set('success', 'Video updated.');
            redirect('admin/videos.php');
        }
        $errors = $result['errors'];
        $editing = ['id' => $id] + $_POST;
    }
}

$videos = get_all_videos_for_admin();
$subjects = get_all_subjects();
$allResourcesForPicker = get_all_resources_paginated(['status' => 'published', 'archive_status' => 'active'], 1, 1000)['items'];

$editId = (int)($_GET['edit'] ?? 0);
if (!$editing && $editId > 0) {
    $editing = get_video_by_id($editId);
}
$isEditing = !empty($editing['id']);

$selectedResourceIds = $isEditing
    ? (isset($_POST['resource_ids']) ? array_map('intval', $_POST['resource_ids']) : get_video_resource_ids((int)$editing['id']))
    : array_map('intval', $_POST['resource_ids'] ?? []);

$selectedRelatedVideoIds = $isEditing
    ? (isset($_POST['related_video_ids']) ? array_map('intval', $_POST['related_video_ids']) : get_related_video_ids((int)$editing['id']))
    : array_map('intval', $_POST['related_video_ids'] ?? []);

// Every other video, for the "Related Videos" picker — excludes the video
// being edited so it can never relate to itself.
$allVideosForPicker = array_values(array_filter($videos, static function (array $v) use ($editing): bool {
    return !$editing || (int)$v['id'] !== (int)($editing['id'] ?? 0);
}));

$subjectGradeMap = [];
foreach ($subjects as $subjectRow) {
    $subjectGradeMap[(int)$subjectRow['id']] = get_subject_grade_levels($subjectRow);
}

$field = static function (string $key) use ($editing) {
    return $editing[$key] ?? '';
};

$pageTitle = 'Videos';
require_once __DIR__ . '/../includes/admin-header.php';
?>
<div class="row g-4">
    <div class="col-lg-6">
        <?php if (empty($videos)): ?>
            <p class="text-secondary">No videos yet. Add the first one using the form.</p>
        <?php else: ?>
            <div class="card shadow-sm border-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr><th>Title</th><th>Subject / Grade</th><th>Status</th><th></th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($videos as $videoRow): ?>
                                <tr>
                                    <td><?= e($videoRow['title']) ?></td>
                                    <td class="small text-secondary"><?= e($videoRow['subject_name']) ?><?= !empty($videoRow['grade_level']) ? ' &middot; ' . e($videoRow['grade_level']) : '' ?></td>
                                    <td>
                                        <?php if ($videoRow['is_published']): ?>
                                            <span class="badge bg-success">Published</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Draft</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?= e(base_url('video.php?slug=' . urlencode($videoRow['slug']))) ?>" class="btn btn-sm btn-outline-secondary" target="_blank">View</a>
                                        <a href="<?= e(base_url('admin/videos.php?edit=' . (int)$videoRow['id'])) ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h2 class="h5 fw-bold mb-3"><?= $isEditing ? 'Edit Video' : 'Add Video' ?></h2>

                <?php if (!empty($errors['general'])): ?>
                    <div class="alert alert-danger"><?= e($errors['general']) ?></div>
                <?php endif; ?>

                <form method="post" action="<?= e(base_url('admin/videos.php')) ?>" enctype="multipart/form-data" novalidate>
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="<?= $isEditing ? 'update' : 'create' ?>">
                    <?php if ($isEditing): ?>
                        <input type="hidden" name="id" value="<?= (int)$editing['id'] ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label" for="title">Title</label>
                        <input type="text" class="form-control <?= isset($errors['title']) ? 'is-invalid' : '' ?>"
                               id="title" name="title" value="<?= e($field('title')) ?>" required maxlength="200">
                        <?php if (isset($errors['title'])): ?><div class="invalid-feedback"><?= e($errors['title']) ?></div><?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="youtube_video_id">YouTube Video ID</label>
                        <input type="text" class="form-control <?= isset($errors['youtube_video_id']) ? 'is-invalid' : '' ?>"
                               id="youtube_video_id" name="youtube_video_id" value="<?= e($field('youtube_video_id')) ?>" required maxlength="20" placeholder="e.g. dQw4w9WgXcQ">
                        <p class="form-text mb-0">The part after "v=" in the YouTube URL — not the full link.</p>
                        <?php if (isset($errors['youtube_video_id'])): ?><div class="invalid-feedback"><?= e($errors['youtube_video_id']) ?></div><?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="description">Description <span class="text-secondary fw-normal">(optional)</span></label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?= e($field('description')) ?></textarea>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="subject_id">Subject</label>
                            <select class="form-select <?= isset($errors['subject_id']) ? 'is-invalid' : '' ?>" id="subject_id" name="subject_id" required>
                                <option value="">Choose&hellip;</option>
                                <?php foreach ($subjects as $subjectOption): ?>
                                    <option value="<?= (int)$subjectOption['id'] ?>" <?= (int)$field('subject_id') === (int)$subjectOption['id'] ? 'selected' : '' ?>><?= e($subjectOption['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['subject_id'])): ?><div class="invalid-feedback"><?= e($errors['subject_id']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="grade_level">Grade Level</label>
                            <select class="form-select <?= isset($errors['grade_level']) ? 'is-invalid' : '' ?>" id="grade_level" name="grade_level">
                                <option value="">Not grade-specific</option>
                                <?php foreach (GRADE_LEVELS as $grade): ?>
                                    <option value="<?= e($grade) ?>" <?= $field('grade_level') === $grade ? 'selected' : '' ?>><?= e($grade) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['grade_level'])): ?><div class="invalid-feedback"><?= e($errors['grade_level']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="topic">Topic</label>
                            <input type="text" class="form-control <?= isset($errors['topic']) ? 'is-invalid' : '' ?>"
                                   id="topic" name="topic" value="<?= e($field('topic')) ?>" maxlength="150">
                            <?php if (isset($errors['topic'])): ?><div class="invalid-feedback"><?= e($errors['topic']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="duration">Duration <span class="text-secondary fw-normal">(optional)</span></label>
                            <input type="text" class="form-control <?= isset($errors['duration']) ? 'is-invalid' : '' ?>"
                                   id="duration" name="duration" value="<?= e($field('duration')) ?>" maxlength="20" placeholder="e.g. 4:32">
                            <?php if (isset($errors['duration'])): ?><div class="invalid-feedback"><?= e($errors['duration']) ?></div><?php endif; ?>
                        </div>
                    </div>

                    <div class="mb-3 mt-3">
                        <label class="form-label" for="thumbnail">Custom Thumbnail <span class="text-secondary fw-normal">(optional — defaults to YouTube's thumbnail)</span></label>
                        <input type="file" class="form-control <?= isset($errors['thumbnail']) ? 'is-invalid' : '' ?>"
                               id="thumbnail" name="thumbnail" accept=".jpg,.jpeg,.png,.webp">
                        <?php if ($isEditing && !empty($editing['thumbnail'])): ?>
                            <img src="<?= e(UPLOAD_THUMBNAIL_URL . '/' . rawurlencode($editing['thumbnail'])) ?>" class="img-thumbnail mt-2" style="max-width:120px;" alt="Current thumbnail">
                        <?php endif; ?>
                        <?php if (isset($errors['thumbnail'])): ?><div class="invalid-feedback d-block"><?= e($errors['thumbnail']) ?></div><?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="learning_objectives">Learning Objectives <span class="text-secondary">(one per line)</span></label>
                        <textarea class="form-control" id="learning_objectives" name="learning_objectives" rows="3" placeholder="Understand addition.&#10;Combine groups of objects."><?= e($field('learning_objectives')) ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="key_concepts">Key Concepts <span class="text-secondary">(one per line)</span></label>
                        <textarea class="form-control" id="key_concepts" name="key_concepts" rows="3"><?= e($field('key_concepts')) ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="transcript">Transcript / Voice-Over Script <span class="text-secondary fw-normal">(optional)</span></label>
                        <textarea class="form-control" id="transcript" name="transcript" rows="4"><?= e($field('transcript')) ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label mb-1">SEO <span class="text-secondary fw-normal">(optional — leave blank to auto-generate)</span></label>
                        <input type="text" class="form-control mb-2 <?= isset($errors['seo_title']) ? 'is-invalid' : '' ?>"
                               name="seo_title" value="<?= e($field('seo_title')) ?>" maxlength="255" placeholder="SEO Title">
                        <textarea class="form-control <?= isset($errors['meta_description']) ? 'is-invalid' : '' ?>"
                                  name="meta_description" rows="2" maxlength="300" placeholder="Meta Description"><?= e($field('meta_description')) ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="resource_ids">Associated Resources <span class="text-secondary fw-normal">(optional)</span></label>
                        <select class="form-select" id="resource_ids" name="resource_ids[]" multiple size="6">
                            <?php foreach ($allResourcesForPicker as $resourceOption): ?>
                                <option value="<?= (int)$resourceOption['id'] ?>" <?= in_array((int)$resourceOption['id'], $selectedResourceIds, true) ? 'selected' : '' ?>>
                                    <?= e($resourceOption['title']) ?> <span>&mdash; <?= e($resourceOption['resource_type']) ?></span>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="form-text mb-0">Ctrl/Cmd-click to select multiple. Shown as "Practice This Skill" on the video page, and this video appears as "Watch the Lesson" on each linked resource's page.</p>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="related_video_ids">Related Videos <span class="text-secondary fw-normal">(optional)</span></label>
                        <select class="form-select" id="related_video_ids" name="related_video_ids[]" multiple size="5">
                            <?php foreach ($allVideosForPicker as $videoOption): ?>
                                <option value="<?= (int)$videoOption['id'] ?>" <?= in_array((int)$videoOption['id'], $selectedRelatedVideoIds, true) ? 'selected' : '' ?>>
                                    <?= e($videoOption['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="form-text mb-0">Leave empty to fall back to automatically-matched videos (same subject/grade).</p>
                    </div>

                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" id="is_published" name="is_published" value="1" <?= !empty($editing['is_published']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_published">Published (visible on the public Videos page)</label>
                    </div>

                    <button type="submit" class="btn btn-primary"><?= $isEditing ? 'Save Changes' : 'Add Video' ?></button>
                    <?php if ($isEditing): ?>
                        <a href="<?= e(base_url('admin/videos.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        <?php if ($isEditing): ?>
            <p class="text-secondary small mt-3">There's no delete action for videos — only publish/unpublish — since a video may already be embedded on a resource page or indexed by Google. Untick "Published" to hide it.</p>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    var subjectGrades = <?= json_encode($subjectGradeMap, JSON_UNESCAPED_SLASHES) ?>;
    var subjectSelect = document.getElementById('subject_id');
    var gradeSelect = document.getElementById('grade_level');

    function applySubjectFilter() {
        var subjectId = subjectSelect.value;
        var allowedGrades = subjectId && subjectGrades[subjectId] ? subjectGrades[subjectId] : null;

        Array.prototype.forEach.call(gradeSelect.options, function (opt) {
            if (opt.value === '') {
                return;
            }
            var allowed = !allowedGrades || allowedGrades.indexOf(opt.value) !== -1;
            opt.hidden = !allowed;
            if (!allowed && opt.selected) {
                gradeSelect.value = '';
            }
        });
    }

    subjectSelect.addEventListener('change', applySubjectFilter);
    applySubjectFilter();
})();
</script>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
