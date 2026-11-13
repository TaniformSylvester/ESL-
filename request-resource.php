<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/subject-functions.php';
require_once __DIR__ . '/includes/request-functions.php';
require_once __DIR__ . '/includes/email.php';

$isLoggedIn = is_logged_in();
$user = $isLoggedIn ? current_user() : null;

$errors = [];
$old = [];
$view = 'form';
$similarRequest = null;
$supportedTopic = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string)($_POST['action'] ?? 'submit');

    if ($action === 'support_open') {
        // "I Need This Too" on an already-open request shown in the "Teachers are asking for" list.
        $requestId = (int)($_POST['request_id'] ?? 0);
        $alreadySupported = isset($_SESSION['supported_requests']) && in_array($requestId, $_SESSION['supported_requests'], true);
        if ($requestId > 0 && !$alreadySupported) {
            $result = support_existing_request($requestId, $isLoggedIn ? (int)$_SESSION['user_id'] : null, $isLoggedIn ? ($user['email'] ?? null) : null);
            if ($result['success']) {
                $_SESSION['supported_requests'] = array_merge($_SESSION['supported_requests'] ?? [], [$requestId]);
            }
        }
        $target = get_request_by_id($requestId);
        $view = 'supported';
        $supportedTopic = $target['topic'] ?? null;
    } elseif ($action === 'support_similar') {
        $similarId = (int)($_POST['similar_id'] ?? 0);
        $email = trim((string)($_POST['email'] ?? ''));
        $result = support_existing_request($similarId, $isLoggedIn ? (int)$_SESSION['user_id'] : null, $isLoggedIn ? ($user['email'] ?? null) : ($email !== '' ? $email : null));
        $target = get_request_by_id($similarId);
        $view = 'supported';
        $supportedTopic = $target['topic'] ?? null;
    } else {
        // Main form submission.
        $honeypot = trim((string)($_POST['website'] ?? ''));
        $old = $_POST;

        if ($honeypot !== '') {
            // Looked like a bot: pretend it worked, but don't store or send anything.
            $view = 'success';
        } else {
            $rateKey = 'resource_request:' . ($_SERVER['REMOTE_ADDR'] ?? '');
            if (too_many_attempts($rateKey, 5, 600)) {
                $errors['general'] = 'You\'ve submitted several requests recently. Please wait a few minutes and try again.';
            } else {
                $errors = validate_request_input($_POST);

                if (empty($errors)) {
                    $forceSubmit = !empty($_POST['submit_anyway']);
                    $similar = $forceSubmit ? null : find_similar_request($_POST);

                    if ($similar) {
                        $similarRequest = $similar;
                        $similarRequest['demand'] = get_request_demand((int)$similar['id']);
                        $view = 'similar_found';
                    } else {
                        record_attempt($rateKey);
                        $input = $_POST;
                        if ($isLoggedIn) {
                            $input['name'] = $input['name'] ?: trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
                            $input['email'] = $input['email'] ?: ($user['email'] ?? '');
                        }
                        $result = create_resource_request($input, $isLoggedIn ? (int)$_SESSION['user_id'] : null);

                        if ($result['success']) {
                            $newRequest = get_request_by_id($result['id']);
                            $submittedEmail = trim((string)($input['email'] ?? ''));
                            if ($submittedEmail !== '') {
                                send_resource_request_confirmation_email($submittedEmail, $newRequest);
                            }
                            $gaCustomEvents = [[
                                'name'   => 'resource_request_submitted',
                                'params' => [
                                    'subject' => $newRequest['subject_name'] ?? null,
                                    'grade'   => $newRequest['grade_level'] ?? null,
                                    'type'    => $newRequest['resource_type'] ?? null,
                                ],
                            ]];
                            $view = 'success';
                            $old = [];
                        } else {
                            $errors = $result['errors'];
                        }
                    }
                }
            }
        }
    }
}

$subjects = get_all_subjects();
$subjectGradeMap = [];
foreach ($subjects as $subjectRow) {
    $subjectGradeMap[(int)$subjectRow['id']] = get_subject_grade_levels($subjectRow);
}
$popularRequests = get_most_requested_open(5);

$pageTitle = 'Request a Teaching Resource';
$pageDescription = "Can't find the teaching resource you need? Request worksheets, lesson plans, PowerPoints, quizzes, games and more from " . SITE_NAME . '.';
require_once __DIR__ . '/includes/header.php';
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <?php if ($view === 'success'): ?>
                <div class="text-center py-4">
                    <div class="text-success mb-3"><i class="fa-solid fa-circle-check fa-3x"></i></div>
                    <h1 class="fw-bold mb-2">Request Received!</h1>
                    <p class="text-secondary mb-1">Thanks for helping us improve <?= e(SITE_NAME) ?>.</p>
                    <p class="text-secondary mb-4">We've received your resource request and will review it when planning future resources. If your request becomes a <?= e(SITE_NAME) ?> resource, we'll let you know if you provided an email address.</p>
                    <div class="d-flex flex-column flex-sm-row justify-content-center gap-3">
                        <a href="<?= e(base_url('resources.php')) ?>" class="btn btn-primary px-4">Browse <?= e(SITE_NAME) ?> Resources</a>
                        <a href="<?= e(base_url('request-resource.php')) ?>" class="btn btn-outline-primary px-4">Request Another Resource</a>
                    </div>
                </div>

            <?php elseif ($view === 'supported'): ?>
                <div class="text-center py-4">
                    <div class="text-success mb-3"><i class="fa-solid fa-circle-check fa-3x"></i></div>
                    <h1 class="fw-bold mb-2">Thanks — We've Added Your Vote!</h1>
                    <?php if ($supportedTopic): ?>
                        <p class="text-secondary mb-4">We've noted that you need <strong><?= e($supportedTopic) ?></strong> too. The more teachers who ask for something, the more likely we are to make it.</p>
                    <?php else: ?>
                        <p class="text-secondary mb-4">The more teachers who ask for something, the more likely we are to make it.</p>
                    <?php endif; ?>
                    <div class="d-flex flex-column flex-sm-row justify-content-center gap-3">
                        <a href="<?= e(base_url('resources.php')) ?>" class="btn btn-primary px-4">Browse <?= e(SITE_NAME) ?> Resources</a>
                        <a href="<?= e(base_url('request-resource.php')) ?>" class="btn btn-outline-primary px-4">Request Another Resource</a>
                    </div>
                </div>

            <?php elseif ($view === 'similar_found'): ?>
                <h1 class="fw-bold mb-2">We Found a Similar Request</h1>
                <p class="text-secondary mb-4">Another teacher already asked for something very similar. Supporting it instead helps us see real demand — but if yours is genuinely different, you can still submit it separately.</p>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <h2 class="h5 fw-bold mb-1"><?= e($similarRequest['topic']) ?></h2>
                        <p class="text-secondary small mb-2">
                            <?= e($similarRequest['subject_name'] ?? '') ?> &middot; <?= e($similarRequest['grade_level'] ?? '') ?> &middot; <?= e($similarRequest['resource_type'] ?? '') ?>
                        </p>
                        <p class="mb-0">
                            <i class="fa-solid fa-people-group text-primary me-1"></i>
                            <strong><?= (int)$similarRequest['demand']['count'] ?></strong> teacher<?= (int)$similarRequest['demand']['count'] === 1 ? '' : 's' ?> requested this
                        </p>
                    </div>
                </div>
                <div class="d-flex flex-column flex-sm-row gap-3">
                    <form method="post" action="<?= e(base_url('request-resource.php')) ?>">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="support_similar">
                        <input type="hidden" name="similar_id" value="<?= (int)$similarRequest['id'] ?>">
                        <?php if (!$isLoggedIn): ?>
                            <input type="hidden" name="email" value="<?= e($old['email'] ?? '') ?>">
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary px-4">I Need This Too</button>
                    </form>
                    <form method="post" action="<?= e(base_url('request-resource.php')) ?>">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="submit">
                        <input type="hidden" name="submit_anyway" value="1">
                        <?php foreach (['name', 'email', 'subject_id', 'grade_level', 'topic', 'resource_type', 'resource_type_other', 'description', 'difficulty', 'additional_notes'] as $field): ?>
                            <input type="hidden" name="<?= e($field) ?>" value="<?= e($old[$field] ?? '') ?>">
                        <?php endforeach; ?>
                        <?php foreach ((array)($old['preferred_formats'] ?? []) as $format): ?>
                            <input type="hidden" name="preferred_formats[]" value="<?= e($format) ?>">
                        <?php endforeach; ?>
                        <button type="submit" class="btn btn-outline-secondary px-4">Submit a Different Request</button>
                    </form>
                </div>

            <?php else: ?>
                <h1 class="fw-bold mb-1">Request a Resource</h1>
                <p class="text-secondary mb-4">Can't find what you need? Tell us what resource you need and help us create resources that teachers actually use. Takes about a minute.</p>

                <?php if (!empty($errors['general'])): ?>
                    <div class="alert alert-danger"><?= e($errors['general']) ?></div>
                <?php endif; ?>

                <form method="post" action="<?= e(base_url('request-resource.php')) ?>" novalidate>
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="submit">

                    <div class="d-none" aria-hidden="true">
                        <label for="website">Leave this field blank</label>
                        <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" for="subject_id">Subject</label>
                            <select class="form-select <?= isset($errors['subject_id']) ? 'is-invalid' : '' ?>" id="subject_id" name="subject_id" required>
                                <option value="">Choose a subject&hellip;</option>
                                <?php foreach ($subjects as $subjectOption): ?>
                                    <option value="<?= (int)$subjectOption['id'] ?>" <?= (int)($old['subject_id'] ?? 0) === (int)$subjectOption['id'] ? 'selected' : '' ?>><?= e($subjectOption['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['subject_id'])): ?><div class="invalid-feedback"><?= e($errors['subject_id']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="grade_level">Grade</label>
                            <select class="form-select <?= isset($errors['grade_level']) ? 'is-invalid' : '' ?>" id="grade_level" name="grade_level" required>
                                <option value="">Choose a grade&hellip;</option>
                                <?php foreach (GRADE_LEVELS as $grade): ?>
                                    <option value="<?= e($grade) ?>" <?= ($old['grade_level'] ?? '') === $grade ? 'selected' : '' ?>><?= e($grade) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['grade_level'])): ?><div class="invalid-feedback"><?= e($errors['grade_level']) ?></div><?php endif; ?>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" for="topic">Topic</label>
                            <input type="text" class="form-control <?= isset($errors['topic']) ? 'is-invalid' : '' ?>" id="topic" name="topic"
                                   placeholder="e.g. Long Division" value="<?= e($old['topic'] ?? '') ?>" maxlength="200" required>
                            <?php if (isset($errors['topic'])): ?><div class="invalid-feedback"><?= e($errors['topic']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="resource_type">Resource Type</label>
                            <select class="form-select <?= isset($errors['resource_type']) ? 'is-invalid' : '' ?>" id="resource_type" name="resource_type" required>
                                <option value="">Choose a type&hellip;</option>
                                <?php foreach (RESOURCE_TYPES as $type): ?>
                                    <option value="<?= e($type) ?>" <?= ($old['resource_type'] ?? '') === $type ? 'selected' : '' ?>><?= e($type) ?></option>
                                <?php endforeach; ?>
                                <option value="Other" <?= ($old['resource_type'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                            </select>
                            <?php if (isset($errors['resource_type'])): ?><div class="invalid-feedback"><?= e($errors['resource_type']) ?></div><?php endif; ?>
                            <input type="text" class="form-control mt-2 <?= isset($errors['resource_type_other']) ? 'is-invalid' : '' ?>" id="resource_type_other" name="resource_type_other"
                                   placeholder="Describe the resource type" value="<?= e($old['resource_type_other'] ?? '') ?>" maxlength="150"
                                   style="<?= ($old['resource_type'] ?? '') === 'Other' ? '' : 'display:none;' ?>">
                            <?php if (isset($errors['resource_type_other'])): ?><div class="invalid-feedback d-block"><?= e($errors['resource_type_other']) ?></div><?php endif; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="description">What do you need?</label>
                        <textarea class="form-control <?= isset($errors['description']) ? 'is-invalid' : '' ?>" id="description" name="description" rows="4"
                                  placeholder="Tell us what you would like the resource to include. For example: &quot;I need challenging Grade 2 long division problems with quotients, remainders, and an answer key.&quot;"
                                  maxlength="2000" required><?= e($old['description'] ?? '') ?></textarea>
                        <?php if (isset($errors['description'])): ?><div class="invalid-feedback"><?= e($errors['description']) ?></div><?php endif; ?>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" for="difficulty">Difficulty <span class="text-secondary fw-normal">(optional)</span></label>
                            <select class="form-select" id="difficulty" name="difficulty">
                                <option value="">Not specified</option>
                                <?php foreach (REQUEST_DIFFICULTIES as $value => $label): ?>
                                    <option value="<?= e($value) ?>" <?= ($old['difficulty'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Preferred Format <span class="text-secondary fw-normal">(optional)</span></label>
                            <?php $oldFormats = (array)($old['preferred_formats'] ?? []); ?>
                            <?php foreach (REQUEST_FORMATS as $value => $label): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="preferred_formats[]" value="<?= e($value) ?>" id="format_<?= e($value) ?>" <?= in_array($value, $oldFormats, true) ? 'checked' : '' ?>>
                                    <label class="form-check-label small" for="format_<?= e($value) ?>"><?= e($label) ?></label>
                                </div>
                            <?php endforeach; ?>
                            <p class="form-text mb-0">We can't promise every requested format will be produced.</p>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="additional_notes">Additional Notes <span class="text-secondary fw-normal">(optional)</span></label>
                        <textarea class="form-control" id="additional_notes" name="additional_notes" rows="2"><?= e($old['additional_notes'] ?? '') ?></textarea>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" for="name">Your Name <span class="text-secondary fw-normal">(optional)</span></label>
                            <input type="text" class="form-control" id="name" name="name" maxlength="150"
                                   value="<?= e($old['name'] ?? ($isLoggedIn ? trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) : '')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="email">Email <span class="text-secondary fw-normal">(optional — to notify you)</span></label>
                            <input type="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" id="email" name="email" maxlength="190"
                                   value="<?= e($old['email'] ?? ($isLoggedIn ? ($user['email'] ?? '') : '')) ?>">
                            <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?= e($errors['email']) ?></div><?php endif; ?>
                        </div>
                    </div>

                    <p class="small text-secondary mb-3">By submitting this request, you agree that <?= e(SITE_NAME) ?> may use the information provided to review your resource request and, if you provide an email address, contact you about the request.</p>

                    <button type="submit" class="btn btn-primary px-4">Submit Request</button>
                </form>

                <?php if (!empty($popularRequests)): ?>
                    <hr class="my-5">
                    <h2 class="h5 fw-bold mb-3">Teachers Are Asking For</h2>
                    <div class="row row-cols-1 row-cols-sm-2 g-3">
                        <?php foreach ($popularRequests as $popular): ?>
                            <div class="col">
                                <div class="card border-0 shadow-sm h-100">
                                    <div class="card-body">
                                        <h3 class="h6 fw-bold mb-1"><?= e($popular['topic']) ?></h3>
                                        <p class="small text-secondary mb-2">
                                            <?= e($popular['subject_name'] ?? '') ?> &middot; <?= e($popular['grade_level'] ?? '') ?> &middot; <?= e($popular['resource_type'] ?? '') ?>
                                        </p>
                                        <p class="small mb-2"><?= (int)$popular['demand_count'] ?> teacher<?= (int)$popular['demand_count'] === 1 ? '' : 's' ?> requested this</p>
                                        <?php $alreadySupported = isset($_SESSION['supported_requests']) && in_array((int)$popular['id'], $_SESSION['supported_requests'], true); ?>
                                        <?php if ($alreadySupported): ?>
                                            <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>You need this too</span>
                                        <?php else: ?>
                                            <form method="post" action="<?= e(base_url('request-resource.php')) ?>">
                                                <?php csrf_field(); ?>
                                                <input type="hidden" name="action" value="support_open">
                                                <input type="hidden" name="request_id" value="<?= (int)$popular['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-primary">I Need This Too</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
(function () {
    var subjectGrades = <?= json_encode($subjectGradeMap, JSON_UNESCAPED_SLASHES) ?>;
    var subjectSelect = document.getElementById('subject_id');
    var gradeSelect = document.getElementById('grade_level');
    var typeSelect = document.getElementById('resource_type');
    var typeOtherInput = document.getElementById('resource_type_other');

    function applySubjectFilter() {
        if (!subjectSelect || !gradeSelect) { return; }
        var subjectId = subjectSelect.value;
        var allowedGrades = subjectId && subjectGrades[subjectId] ? subjectGrades[subjectId] : null;

        Array.prototype.forEach.call(gradeSelect.options, function (opt) {
            if (opt.value === '') { return; }
            var allowed = !allowedGrades || allowedGrades.indexOf(opt.value) !== -1;
            opt.hidden = !allowed;
            if (!allowed && opt.selected) { gradeSelect.value = ''; }
        });
    }

    function toggleTypeOther() {
        if (!typeSelect || !typeOtherInput) { return; }
        typeOtherInput.style.display = typeSelect.value === 'Other' ? '' : 'none';
    }

    if (subjectSelect) {
        subjectSelect.addEventListener('change', applySubjectFilter);
        applySubjectFilter();
    }
    if (typeSelect) {
        typeSelect.addEventListener('change', toggleTypeOther);
    }
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
