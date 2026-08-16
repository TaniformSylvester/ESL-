<?php
/**
 * Shared add/edit resource form. Expects these variables set by the caller:
 * $resource (array|null), $errors (array), $categoriesGrouped (array),
 * $subjects (array), $actionUrl (string), $isEdit (bool), $old (array —
 * used to repopulate text fields after a validation error on create).
 */
$field = static function (string $key) use ($resource, $old, $isEdit) {
    if (!$isEdit && isset($old[$key])) {
        return $old[$key];
    }
    return $resource[$key] ?? '';
};

// Powers the Subject dropdown's client-side filtering of Grade Level and
// Category options — kept in sync with validate_resource_input()'s
// server-side check of the same rule, so JS convenience never becomes the
// only thing enforcing it.
$subjectGradeMap = [];
foreach ($subjects as $subjectRow) {
    $subjectGradeMap[(int)$subjectRow['id']] = get_subject_grade_levels($subjectRow);
}
?>
<form method="post" action="<?= e($actionUrl) ?>" enctype="multipart/form-data" novalidate>
    <?php csrf_field(); ?>

    <?php if (!empty($errors['general'])): ?>
        <div class="alert alert-danger"><?= e($errors['general']) ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label" for="title">Title</label>
                        <input type="text" class="form-control <?= isset($errors['title']) ? 'is-invalid' : '' ?>"
                               id="title" name="title" value="<?= e($field('title')) ?>" required maxlength="200">
                        <?php if (isset($errors['title'])): ?><div class="invalid-feedback"><?= e($errors['title']) ?></div><?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="4"><?= e($field('description')) ?></textarea>
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
                            <label class="form-label" for="resource_type">Resource Type</label>
                            <select class="form-select <?= isset($errors['resource_type']) ? 'is-invalid' : '' ?>" id="resource_type" name="resource_type" required>
                                <option value="">Choose&hellip;</option>
                                <?php foreach (RESOURCE_TYPES as $type): ?>
                                    <option value="<?= e($type) ?>" <?= $field('resource_type') === $type ? 'selected' : '' ?>><?= e($type) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['resource_type'])): ?><div class="invalid-feedback"><?= e($errors['resource_type']) ?></div><?php endif; ?>
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
                            <label class="form-label" for="category_id">Category</label>
                            <select class="form-select <?= isset($errors['category_id']) ? 'is-invalid' : '' ?>" id="category_id" name="category_id">
                                <option value="">Uncategorized</option>
                                <?php foreach ($categoriesGrouped as $groupName => $categories): ?>
                                    <optgroup label="<?= e($groupName) ?>">
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?= (int)$category['id'] ?>" data-subject-id="<?= (int)$category['subject_id'] ?>" <?= (int)$field('category_id') === (int)$category['id'] ? 'selected' : '' ?>>
                                                <?= e($category['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['category_id'])): ?><div class="invalid-feedback"><?= e($errors['category_id']) ?></div><?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h2 class="h6 fw-bold mb-3">Files</h2>

                    <div class="mb-3">
                        <label class="form-label" for="resource_file">Resource File <?= $isEdit ? '<span class="text-secondary">(leave blank to keep the current file)</span>' : '' ?></label>
                        <input type="file" class="form-control <?= isset($errors['resource_file']) ? 'is-invalid' : '' ?>"
                               id="resource_file" name="resource_file" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.zip" <?= $isEdit ? '' : 'required' ?>>
                        <?php if ($isEdit && !empty($resource['file_name'])): ?>
                            <div class="form-text">Current file: <?= e($resource['file_name']) ?> (<?= e(format_file_size((int)$resource['file_size'])) ?>)</div>
                        <?php endif; ?>
                        <?php if (isset($errors['resource_file'])): ?><div class="invalid-feedback d-block"><?= e($errors['resource_file']) ?></div><?php endif; ?>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="thumbnail">Thumbnail Image <?= $isEdit ? '<span class="text-secondary">(optional replace)</span>' : '<span class="text-secondary">(optional)</span>' ?></label>
                            <input type="file" class="form-control <?= isset($errors['thumbnail']) ? 'is-invalid' : '' ?>"
                                   id="thumbnail" name="thumbnail" accept=".jpg,.jpeg,.png,.webp">
                            <?php if ($isEdit && !empty($resource['thumbnail'])): ?>
                                <img src="<?= e(UPLOAD_THUMBNAIL_URL . '/' . rawurlencode($resource['thumbnail'])) ?>" class="img-thumbnail mt-2" style="max-width:120px;" alt="Current thumbnail">
                            <?php endif; ?>
                            <?php if (isset($errors['thumbnail'])): ?><div class="invalid-feedback d-block"><?= e($errors['thumbnail']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="preview_image">Preview Image <span class="text-secondary">(optional)</span></label>
                            <input type="file" class="form-control <?= isset($errors['preview_image']) ? 'is-invalid' : '' ?>"
                                   id="preview_image" name="preview_image" accept=".jpg,.jpeg,.png,.webp">
                            <?php if ($isEdit && !empty($resource['preview_image'])): ?>
                                <img src="<?= e(UPLOAD_PREVIEW_URL . '/' . rawurlencode($resource['preview_image'])) ?>" class="img-thumbnail mt-2" style="max-width:120px;" alt="Current preview">
                            <?php endif; ?>
                            <?php if (isset($errors['preview_image'])): ?><div class="invalid-feedback d-block"><?= e($errors['preview_image']) ?></div><?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h2 class="h6 fw-bold mb-3">Access &amp; Visibility</h2>

                    <div class="mb-3">
                        <label class="form-label d-block">Access</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="is_free" id="access_members" value="0" <?= !$field('is_free') ? 'checked' : '' ?>>
                            <label class="form-check-label" for="access_members">Members Only</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="is_free" id="access_free" value="1" <?= $field('is_free') ? 'checked' : '' ?>>
                            <label class="form-check-label" for="access_free">Free</label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label d-block">Status</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="is_published" id="status_draft" value="0" <?= !$field('is_published') ? 'checked' : '' ?>>
                            <label class="form-check-label" for="status_draft">Draft</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="is_published" id="status_published" value="1" <?= $field('is_published') ? 'checked' : '' ?>>
                            <label class="form-check-label" for="status_published">Published</label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100"><?= $isEdit ? 'Save Changes' : 'Add Resource' ?></button>
                    <a href="<?= e(base_url('admin/resources.php')) ?>" class="btn btn-outline-secondary w-100 mt-2">Cancel</a>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
(function () {
    var subjectGrades = <?= json_encode($subjectGradeMap, JSON_UNESCAPED_SLASHES) ?>;
    var subjectSelect = document.getElementById('subject_id');
    var gradeSelect = document.getElementById('grade_level');
    var categorySelect = document.getElementById('category_id');

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

        Array.prototype.forEach.call(categorySelect.options, function (opt) {
            if (opt.value === '') {
                return;
            }
            var allowed = !subjectId || opt.getAttribute('data-subject-id') === subjectId;
            opt.hidden = !allowed;
            if (!allowed && opt.selected) {
                categorySelect.value = '';
            }
        });

        Array.prototype.forEach.call(categorySelect.querySelectorAll('optgroup'), function (group) {
            var hasVisible = Array.prototype.some.call(group.querySelectorAll('option'), function (opt) {
                return !opt.hidden;
            });
            group.hidden = !hasVisible;
        });
    }

    subjectSelect.addEventListener('change', applySubjectFilter);
    applySubjectFilter();
})();
</script>
