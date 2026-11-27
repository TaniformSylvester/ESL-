<?php
/**
 * Shared add/edit Teacher Hub guide form. Expects: $guide (array|null),
 * $errors (array), $subjects (array), $allResources (array, published
 * only), $selectedResourceIds (array<int>), $actionUrl (string),
 * $isEdit (bool), $old (array).
 */
$field = static function (string $key) use ($guide, $old, $isEdit) {
    if (!$isEdit && isset($old[$key])) {
        return $old[$key];
    }
    return $guide[$key] ?? '';
};
?>
<form method="post" action="<?= e($actionUrl) ?>" novalidate>
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

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" for="category">Category</label>
                            <select class="form-select <?= isset($errors['category']) ? 'is-invalid' : '' ?>" id="category" name="category" required>
                                <option value="">Choose&hellip;</option>
                                <?php foreach (GUIDE_CATEGORIES as $value => $label): ?>
                                    <option value="<?= e($value) ?>" <?= $field('category') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['category'])): ?><div class="invalid-feedback"><?= e($errors['category']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="subject_id">Subject <span class="text-secondary">(optional)</span></label>
                            <select class="form-select <?= isset($errors['subject_id']) ? 'is-invalid' : '' ?>" id="subject_id" name="subject_id">
                                <option value="">None</option>
                                <?php foreach ($subjects as $subjectOption): ?>
                                    <option value="<?= (int)$subjectOption['id'] ?>" <?= (int)$field('subject_id') === (int)$subjectOption['id'] ? 'selected' : '' ?>><?= e($subjectOption['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label" for="summary">Summary <span class="text-secondary">(shown on the Teacher Hub index, 1-2 sentences)</span></label>
                        <textarea class="form-control <?= isset($errors['summary']) ? 'is-invalid' : '' ?>" id="summary" name="summary" rows="2" maxlength="300"><?= e($field('summary')) ?></textarea>
                        <?php if (isset($errors['summary'])): ?><div class="invalid-feedback"><?= e($errors['summary']) ?></div><?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">
                    <h2 class="h6 fw-bold mb-3">Content</h2>
                    <p class="text-secondary small">Leave any section blank to leave it off the published page.</p>

                    <div class="mb-3">
                        <label class="form-label" for="intro">Introduction</label>
                        <textarea class="form-control" id="intro" name="intro" rows="4"><?= e($field('intro')) ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="practical_advice">Practical Advice</label>
                        <textarea class="form-control" id="practical_advice" name="practical_advice" rows="4"><?= e($field('practical_advice')) ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="classroom_examples">Classroom Examples</label>
                        <textarea class="form-control" id="classroom_examples" name="classroom_examples" rows="4"><?= e($field('classroom_examples')) ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="activities">Activities <span class="text-secondary">(one per line)</span></label>
                        <textarea class="form-control" id="activities" name="activities" rows="4"><?= e($field('activities')) ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="common_difficulties">Common Difficulties</label>
                        <textarea class="form-control" id="common_difficulties" name="common_difficulties" rows="3"><?= e($field('common_difficulties')) ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="differentiation">Differentiation</label>
                        <textarea class="form-control" id="differentiation" name="differentiation" rows="3"><?= e($field('differentiation')) ?></textarea>
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="assessment">Assessment</label>
                        <textarea class="form-control" id="assessment" name="assessment" rows="3"><?= e($field('assessment')) ?></textarea>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h2 class="h6 fw-bold mb-1">SEO <span class="text-secondary fw-normal">(optional)</span></h2>
                    <p class="text-secondary small mb-3">Leave blank to auto-generate from the title.</p>
                    <div class="mb-3">
                        <label class="form-label" for="seo_title">SEO Title</label>
                        <input type="text" class="form-control" id="seo_title" name="seo_title" value="<?= e($field('seo_title')) ?>" maxlength="255">
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="meta_description">Meta Description</label>
                        <textarea class="form-control" id="meta_description" name="meta_description" rows="2" maxlength="300"><?= e($field('meta_description')) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">
                    <h2 class="h6 fw-bold mb-3">Status</h2>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="is_published" id="status_draft" value="0" <?= !$field('is_published') ? 'checked' : '' ?>>
                        <label class="form-check-label" for="status_draft">Draft</label>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="radio" name="is_published" id="status_published" value="1" <?= $field('is_published') ? 'checked' : '' ?>>
                        <label class="form-check-label" for="status_published">Published</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><?= $isEdit ? 'Save Changes' : 'Add Guide' ?></button>
                    <a href="<?= e(base_url('admin/guides.php')) ?>" class="btn btn-outline-secondary w-100 mt-2">Cancel</a>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h2 class="h6 fw-bold mb-1">Recommended Resources</h2>
                    <p class="text-secondary small mb-3">Only pick resources that genuinely fit this guide — never auto-matched.</p>
                    <select class="form-select" name="resource_ids[]" multiple size="12">
                        <?php foreach ($allResources as $resourceOption): ?>
                            <option value="<?= (int)$resourceOption['id'] ?>" <?= in_array((int)$resourceOption['id'], $selectedResourceIds, true) ? 'selected' : '' ?>>
                                <?= e($resourceOption['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Ctrl/Cmd-click to select multiple.</div>
                </div>
            </div>
        </div>
    </div>
</form>
