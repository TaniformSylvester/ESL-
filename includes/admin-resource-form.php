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
<?php if ($isEdit && !empty($existingFiles)): ?>
<!-- Kept outside the main edit form below: an HTML <form> cannot be
     nested inside another <form>, and each "Remove" action here needs its
     own independent submit target. This one shared, hidden form is reused
     for whichever file's Remove button was clicked (see the script at the
     bottom of this file), rather than one nested form per file. -->
<form method="post" action="<?= e(base_url('admin/resource-file-delete.php')) ?>" id="removeFileForm" class="d-none">
    <?php csrf_field(); ?>
    <input type="hidden" name="id" id="removeFileId">
    <input type="hidden" name="resource_id" value="<?= (int)$resource['id'] ?>">
</form>
<?php endif; ?>
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

            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">
                    <h2 class="h6 fw-bold mb-1">SEO <span class="text-secondary fw-normal">(optional)</span></h2>
                    <p class="text-secondary small mb-3">Leave blank to auto-generate from the title, subject, and grade — e.g. "[Title] | Grade X Subject Resource". Only fill these in if you need to override that.</p>

                    <div class="mb-3">
                        <label class="form-label" for="seo_title">SEO Title</label>
                        <input type="text" class="form-control <?= isset($errors['seo_title']) ? 'is-invalid' : '' ?>"
                               id="seo_title" name="seo_title" value="<?= e($field('seo_title')) ?>" maxlength="255">
                        <?php if (isset($errors['seo_title'])): ?><div class="invalid-feedback"><?= e($errors['seo_title']) ?></div><?php endif; ?>
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="meta_description">Meta Description</label>
                        <textarea class="form-control <?= isset($errors['meta_description']) ? 'is-invalid' : '' ?>"
                                  id="meta_description" name="meta_description" rows="2" maxlength="300"><?= e($field('meta_description')) ?></textarea>
                        <?php if (isset($errors['meta_description'])): ?><div class="invalid-feedback"><?= e($errors['meta_description']) ?></div><?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">
                    <h2 class="h6 fw-bold mb-1">Teaching Details <span class="text-secondary fw-normal">(optional)</span></h2>
                    <p class="text-secondary small mb-3">Only fill these in with what's genuinely true of this specific file — they're shown on the resource page exactly as written, and left off entirely when blank. Leave anything blank you're not sure about.</p>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label" for="recommended_level">Recommended Level</label>
                            <input type="text" class="form-control <?= isset($errors['recommended_level']) ? 'is-invalid' : '' ?>"
                                   id="recommended_level" name="recommended_level" value="<?= e($field('recommended_level')) ?>" maxlength="100" placeholder="e.g. Beginner ESL">
                            <?php if (isset($errors['recommended_level'])): ?><div class="invalid-feedback"><?= e($errors['recommended_level']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="suggested_duration">Suggested Duration</label>
                            <input type="text" class="form-control <?= isset($errors['suggested_duration']) ? 'is-invalid' : '' ?>"
                                   id="suggested_duration" name="suggested_duration" value="<?= e($field('suggested_duration')) ?>" maxlength="50" placeholder="e.g. 30 minutes">
                            <?php if (isset($errors['suggested_duration'])): ?><div class="invalid-feedback"><?= e($errors['suggested_duration']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="skills_practiced">Skills Practiced</label>
                            <input type="text" class="form-control <?= isset($errors['skills_practiced']) ? 'is-invalid' : '' ?>"
                                   id="skills_practiced" name="skills_practiced" value="<?= e($field('skills_practiced')) ?>" maxlength="255" placeholder="Comma-separated, e.g. Vocabulary, Speaking">
                            <?php if (isset($errors['skills_practiced'])): ?><div class="invalid-feedback"><?= e($errors['skills_practiced']) ?></div><?php endif; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="overview">Overview <span class="text-secondary">(a fuller explanation than the short description above)</span></label>
                        <textarea class="form-control" id="overview" name="overview" rows="3"><?= e($field('overview')) ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="learning_objectives">Learning Objectives <span class="text-secondary">(one per line)</span></label>
                        <textarea class="form-control" id="learning_objectives" name="learning_objectives" rows="3" placeholder="Identify common animal vocabulary.&#10;Match spoken words with pictures."><?= e($field('learning_objectives')) ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="how_to_use">How to Use This Resource <span class="text-secondary">(one step per line)</span></label>
                        <textarea class="form-control" id="how_to_use" name="how_to_use" rows="3"><?= e($field('how_to_use')) ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="activity_ideas">Classroom Activity Ideas <span class="text-secondary">(one per line)</span></label>
                        <textarea class="form-control" id="activity_ideas" name="activity_ideas" rows="3"><?= e($field('activity_ideas')) ?></textarea>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="teacher_tips">Teacher Tips</label>
                            <textarea class="form-control" id="teacher_tips" name="teacher_tips" rows="2"><?= e($field('teacher_tips')) ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="differentiation_notes">Differentiation</label>
                            <textarea class="form-control" id="differentiation_notes" name="differentiation_notes" rows="2"><?= e($field('differentiation_notes')) ?></textarea>
                        </div>
                    </div>
                    <div class="row g-3 mt-0">
                        <div class="col-md-6">
                            <label class="form-label" for="assessment_notes">Assessment</label>
                            <textarea class="form-control" id="assessment_notes" name="assessment_notes" rows="2"><?= e($field('assessment_notes')) ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="whats_included">What's Included <span class="text-secondary">(one item per line — only list what's genuinely in the download)</span></label>
                            <textarea class="form-control" id="whats_included" name="whats_included" rows="2" placeholder="Lesson plan (PDF)&#10;12-slide PowerPoint&#10;Worksheet with answer key"><?= e($field('whats_included')) ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">
                    <h2 class="h6 fw-bold mb-1">Related Resources <span class="text-secondary fw-normal">(optional)</span></h2>
                    <p class="text-secondary small mb-3">Manually pick genuinely relevant resources. Leave empty to fall back to automatically-matched resources (same subject/category/grade).</p>
                    <select class="form-select" name="related_resource_ids[]" multiple size="8">
                        <?php foreach ($allResourcesForPicker as $relatedOption): ?>
                            <option value="<?= (int)$relatedOption['id'] ?>" <?= in_array((int)$relatedOption['id'], $selectedRelatedIds, true) ? 'selected' : '' ?>>
                                <?= e($relatedOption['title']) ?> <span>&mdash; <?= e($relatedOption['resource_type']) ?></span>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">
                    <h2 class="h6 fw-bold mb-1">Related Teacher Hub Guides <span class="text-secondary fw-normal">(optional)</span></h2>
                    <p class="text-secondary small mb-3">Only link guides that genuinely relate to this resource.</p>
                    <select class="form-select" name="related_guide_ids[]" multiple size="6">
                        <?php foreach ($allGuidesForPicker as $guideOption): ?>
                            <option value="<?= (int)$guideOption['id'] ?>" <?= in_array((int)$guideOption['id'], $selectedGuideIds, true) ? 'selected' : '' ?>>
                                <?= e($guideOption['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="card shadow-sm border-0 mb-4">
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

                    <hr class="my-3">
                    <h3 class="h6 fw-bold mb-1">Additional Files <span class="text-secondary fw-normal">(optional)</span></h3>
                    <p class="text-secondary small mb-3">For a separate file that isn't part of the main download above — e.g. a standalone answer key.</p>

                    <?php if ($isEdit && !empty($existingFiles)): ?>
                        <ul class="list-group mb-3">
                            <?php foreach ($existingFiles as $extraFile): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><?= e($extraFile['label'] ?: $extraFile['file_name']) ?> <span class="text-secondary small">(<?= e(format_file_size((int)$extraFile['file_size'])) ?>)</span></span>
                                    <button type="button" class="btn btn-sm btn-outline-danger js-remove-additional-file" data-file-id="<?= (int)$extraFile['id'] ?>">Remove</button>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php for ($slot = 1; $slot <= RESOURCE_ADDITIONAL_FILE_SLOTS; $slot++): ?>
                        <div class="row g-2 mb-2">
                            <div class="col-md-7">
                                <input type="file" class="form-control form-control-sm" name="additional_file_<?= $slot ?>">
                            </div>
                            <div class="col-md-5">
                                <input type="text" class="form-control form-control-sm" name="additional_file_label_<?= $slot ?>" placeholder="Label, e.g. Answer Key">
                            </div>
                        </div>
                    <?php endfor; ?>
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

                    <div class="mb-3 p-2 border rounded <?= isset($errors['qc_confirmed']) ? 'border-danger' : '' ?>" id="qc_checklist_box">
                        <p class="small fw-bold mb-1">Before publishing, confirm:</p>
                        <ul class="small text-secondary mb-2 ps-3">
                            <li>Title, subject, grade, topic and type are correct</li>
                            <li>The download file exists and opens correctly</li>
                            <li>Description and teaching information are accurate</li>
                            <li>"What's Included" matches the actual file(s)</li>
                            <li>No spelling/grammar errors; related resources and guide links are genuinely relevant</li>
                        </ul>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="qc_confirmed" id="qc_confirmed" value="1"
                                   <?= !empty($resource['qc_confirmed_at']) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="qc_confirmed">I've checked this against the list above</label>
                        </div>
                        <?php if (isset($errors['qc_confirmed'])): ?><div class="text-danger small mt-1"><?= e($errors['qc_confirmed']) ?></div><?php endif; ?>
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

    var qcBox = document.getElementById('qc_checklist_box');
    var publishedRadio = document.getElementById('status_published');
    var draftRadio = document.getElementById('status_draft');

    function toggleQcBox() {
        qcBox.style.display = publishedRadio.checked ? '' : 'none';
    }

    publishedRadio.addEventListener('change', toggleQcBox);
    draftRadio.addEventListener('change', toggleQcBox);
    toggleQcBox();

    // "Remove" on an existing additional file: fills in and submits the
    // shared #removeFileForm declared above the main form (can't be a
    // form nested inside this page's main form — see that form's comment).
    var removeFileForm = document.getElementById('removeFileForm');
    if (removeFileForm) {
        document.querySelectorAll('.js-remove-additional-file').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!confirm('Remove this file?')) {
                    return;
                }
                document.getElementById('removeFileId').value = btn.dataset.fileId;
                removeFileForm.submit();
            });
        });
    }
})();
</script>
