<?php
/**
 * Resource Automation — server side of the generation + publishing pipeline.
 *
 * The resource generator runs OUTSIDE this server (in Claude Code sessions,
 * see automation/README.md). It talks to /api/automation.php with a
 * least-privilege token. Everything here is designed so that automation:
 *
 *   - can only ADD new resources, always as unpublished drafts, through the
 *     existing create_resource() (same validation, same upload checks);
 *   - never updates or deletes an existing resource, and never touches
 *     users, memberships, subscriptions, payments or settings;
 *   - can be stopped instantly (emergency stop is checked on every call);
 *   - leaves an audit trail for every call.
 *
 * Publishing stays a human decision: an admin approves a draft in
 * Admin > Resource Automation, which is the same QC confirmation the
 * normal resource form requires. Quality scores are automated indicators
 * only — never a verified judgement of educational accuracy.
 */

require_once __DIR__ . '/upload-functions.php';
require_once __DIR__ . '/resource-functions.php';
require_once __DIR__ . '/subject-functions.php';
require_once __DIR__ . '/guide-functions.php';

const AUTOMATION_SCOPES = ['catalog:read', 'drafts:write'];
const AUTOMATION_RATE_PER_MINUTE = 60;          // any API call, per token
const AUTOMATION_UPLOADS_PER_HOUR = 30;         // draft uploads, per token
const AUTOMATION_MAX_REQUEST_BYTES = 80 * 1024 * 1024;
const AUTOMATION_STALE_RUN_MINUTES = 120;       // no heartbeat for this long = interrupted
const AUTOMATION_MAX_CORRECTIONS = 3;
const AUTOMATION_SCORE_MAX = [
    'educational_accuracy' => 25,
    'completeness'         => 20,
    'formatting'           => 15,
    'age_appropriateness'  => 15,
    'visual_quality'       => 10,
    'metadata'             => 15,
];
const AUTOMATION_ADDITIONAL_EXTENSIONS = ['pdf', 'docx', 'pptx'];
const AUTOMATION_ITEM_STATUSES = [
    'pending_review'       => 'Awaiting review',
    'approved'             => 'Approved & published',
    'rejected'             => 'Rejected',
    'failed'               => 'Failed QC',
    'regenerate_requested' => 'Regeneration requested',
    'regenerated'          => 'Replaced by a regenerated version',
];

// -----------------------------------------------------------------------
// SETTINGS
// -----------------------------------------------------------------------

/** Whether the automation tables exist (the migration is run by hand). */
function automation_available(): bool
{
    static $ok = null;
    if ($ok === null) {
        try {
            getDB()->query('SELECT 1 FROM automation_settings LIMIT 1');
            $ok = true;
        } catch (PDOException $e) {
            $ok = false;
        }
    }

    return $ok;
}

/** Subject slug => list of valid grade labels, from the subjects table. */
function automation_subject_grades(): array
{
    $out = [];
    foreach (get_all_subjects() as $subject) {
        $out[$subject['slug']] = get_subject_grade_levels($subject);
    }

    return $out;
}

/** The settings row, with subjects/grades decoded and defaulted. */
function automation_settings(): array
{
    $row = getDB()->query('SELECT * FROM automation_settings WHERE id = 1')->fetch() ?: [];
    $valid = automation_subject_grades();

    $subjects = array_values(array_intersect(array_filter(explode(',', (string)($row['subjects'] ?? ''))), array_keys($valid)));
    $grades = json_decode((string)($row['grades'] ?? ''), true);
    if (!is_array($grades)) {
        $grades = $valid; // default: every grade the subject offers
    }
    foreach ($valid as $slug => $allowed) {
        $grades[$slug] = array_values(array_intersect($grades[$slug] ?? $allowed, $allowed));
    }

    return [
        'enabled'                => (bool)($row['enabled'] ?? false),
        'emergency_stop'         => (bool)($row['emergency_stop'] ?? false),
        'frequency'              => $row['frequency'] ?? 'manual',
        'run_hour'               => (int)($row['run_hour'] ?? 2),
        'subjects'               => $subjects,
        'grades'                 => $grades,
        'per_run'                => (int)($row['per_run'] ?? 2),
        'free_percent'           => (int)($row['free_percent'] ?? 33),
        'max_uploads_per_day'    => (int)($row['max_uploads_per_day'] ?? 10),
        // Automatic public publishing is not available yet; the column exists
        // for later, and is forced off here regardless of the stored value.
        'auto_publish'           => false,
        'auto_publish_min_score' => (int)($row['auto_publish_min_score'] ?? 90),
        'updated_at'             => $row['updated_at'] ?? null,
    ];
}

/** Validates and saves the dashboard settings form. Returns errors (empty on success). */
function automation_save_settings(array $input, int $adminId): array
{
    $valid = automation_subject_grades();
    $errors = [];

    $frequency = in_array($input['frequency'] ?? '', ['manual', 'daily', 'weekly'], true) ? $input['frequency'] : 'manual';
    $runHour = max(0, min(23, (int)($input['run_hour'] ?? 2)));
    $subjects = array_values(array_intersect((array)($input['subjects'] ?? []), array_keys($valid)));
    $grades = [];
    foreach ($valid as $slug => $allowed) {
        $grades[$slug] = array_values(array_intersect((array)($input['grades'][$slug] ?? []), $allowed));
        if (in_array($slug, $subjects, true) && !$grades[$slug]) {
            $errors['grades'] = 'Choose at least one grade for every selected subject.';
        }
    }
    if (!$subjects) {
        $errors['subjects'] = 'Choose at least one subject.';
    }
    $perRun = (int)($input['per_run'] ?? 2);
    if ($perRun < 1 || $perRun > 10) {
        $errors['per_run'] = 'Resources per run must be between 1 and 10.';
    }
    $freePercent = (int)($input['free_percent'] ?? 33);
    if ($freePercent < 0 || $freePercent > 100) {
        $errors['free_percent'] = 'Free share must be between 0 and 100%.';
    }
    $maxDay = (int)($input['max_uploads_per_day'] ?? 10);
    if ($maxDay < 1 || $maxDay > 50) {
        $errors['max_uploads_per_day'] = 'Daily upload limit must be between 1 and 50.';
    }

    if ($errors) {
        return $errors;
    }

    getDB()->prepare(
        'UPDATE automation_settings SET enabled = ?, frequency = ?, run_hour = ?, subjects = ?, grades = ?,
                per_run = ?, free_percent = ?, max_uploads_per_day = ?, auto_publish = 0, updated_by = ? WHERE id = 1'
    )->execute([
        !empty($input['enabled']) ? 1 : 0, $frequency, $runHour, implode(',', $subjects),
        json_encode($grades, JSON_UNESCAPED_UNICODE), $perRun, $freePercent, $maxDay, $adminId,
    ]);
    automation_audit('settings_saved', 200, "enabled=" . (!empty($input['enabled']) ? 1 : 0) . " freq={$frequency} per_run={$perRun} free={$freePercent}%", null, $adminId);

    return [];
}

function automation_set_emergency_stop(bool $on, int $adminId): void
{
    getDB()->prepare('UPDATE automation_settings SET emergency_stop = ?, updated_by = ? WHERE id = 1')->execute([$on ? 1 : 0, $adminId]);
    if ($on) {
        // Any run still marked running is stopped; the generator sees 423 on its next call.
        getDB()->exec("UPDATE automation_runs SET status = 'stopped', finished_at = NOW(), message = CONCAT(COALESCE(message, ''), ' [stopped by emergency stop]') WHERE status = 'running'");
    }
    automation_audit($on ? 'emergency_stop_on' : 'emergency_stop_off', 200, null, null, $adminId);
}

// -----------------------------------------------------------------------
// TOKENS, AUDIT, RATE LIMITS
// -----------------------------------------------------------------------

/** Creates a token and returns the plaintext ONCE. Only its hash is stored. */
function automation_create_token(string $name, int $adminId): string
{
    $plain = 'tl_' . bin2hex(random_bytes(24));
    getDB()->prepare('INSERT INTO api_tokens (name, token_hash, token_prefix, scopes, created_by) VALUES (?, ?, ?, ?, ?)')
        ->execute([mb_substr($name, 0, 100), hash('sha256', $plain), substr($plain, 0, 10), implode(',', AUTOMATION_SCOPES), $adminId]);
    automation_audit('token_created', 200, 'name=' . mb_substr($name, 0, 100), null, $adminId);

    return $plain;
}

function automation_revoke_token(int $id, int $adminId): void
{
    getDB()->prepare('UPDATE api_tokens SET revoked_at = NOW() WHERE id = ? AND revoked_at IS NULL')->execute([$id]);
    automation_audit('token_revoked', 200, "token #{$id}", null, $adminId);
}

/** Active token row for a plaintext token, or null. Constant-time lookup by hash. */
function automation_find_token(string $plain): ?array
{
    if (!preg_match('/^tl_[0-9a-f]{48}$/', $plain)) {
        return null;
    }
    $stmt = getDB()->prepare('SELECT * FROM api_tokens WHERE token_hash = ? AND revoked_at IS NULL LIMIT 1');
    $stmt->execute([hash('sha256', $plain)]);

    return $stmt->fetch() ?: null;
}

function automation_list_tokens(): array
{
    return getDB()->query('SELECT id, name, token_prefix, scopes, created_at, last_used_at, last_used_ip, revoked_at FROM api_tokens ORDER BY id DESC')->fetchAll();
}

function automation_client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function automation_audit(string $action, int $status, ?string $detail = null, ?int $tokenId = null, ?int $adminId = null): void
{
    try {
        getDB()->prepare('INSERT INTO automation_audit (token_id, admin_id, action, http_status, ip, detail) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$tokenId, $adminId, mb_substr($action, 0, 50), $status, automation_client_ip(), $detail !== null ? mb_substr($detail, 0, 500) : null]);
    } catch (PDOException $e) {
        error_log('automation_audit failed: ' . $e->getMessage());
    }
}

/** True when this token has already made $max calls of $action (or any, if null) within $seconds. */
function automation_rate_limited(int $tokenId, ?string $action, int $max, int $seconds): bool
{
    $sql = 'SELECT COUNT(*) FROM automation_audit WHERE token_id = ? AND created_at > (NOW() - INTERVAL ? SECOND)';
    $params = [$tokenId, $seconds];
    if ($action !== null) {
        $sql .= ' AND action = ? AND http_status < 400';
        $params[] = $action;
    }
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);

    return (int)$stmt->fetchColumn() >= $max;
}

// -----------------------------------------------------------------------
// RUNS
// -----------------------------------------------------------------------

/** Marks runs with no heartbeat for AUTOMATION_STALE_RUN_MINUTES as interrupted. */
function automation_mark_stale_runs(): void
{
    getDB()->prepare(
        "UPDATE automation_runs SET status = 'interrupted', finished_at = NOW(),
                message = CONCAT(COALESCE(CONCAT(message, ' '), ''), '[no heartbeat for ', ?, ' minutes - session ended or hit a usage limit]')
         WHERE status = 'running' AND COALESCE(heartbeat_at, started_at) < (NOW() - INTERVAL ? MINUTE)"
    )->execute([AUTOMATION_STALE_RUN_MINUTES, AUTOMATION_STALE_RUN_MINUTES]);
}

function automation_get_run(int $id): ?array
{
    $stmt = getDB()->prepare('SELECT * FROM automation_runs WHERE id = ?');
    $stmt->execute([$id]);

    return $stmt->fetch() ?: null;
}

function automation_recent_runs(int $limit = 20): array
{
    automation_mark_stale_runs();

    return getDB()->query('SELECT * FROM automation_runs ORDER BY id DESC LIMIT ' . max(1, $limit))->fetchAll();
}

function automation_last_success(): ?string
{
    $v = getDB()->query("SELECT MAX(finished_at) FROM automation_runs WHERE status IN ('completed', 'partial') AND generated > 0")->fetchColumn();

    return $v ?: null;
}

/** Whether a scheduled run is due now, with a human-readable reason. */
function automation_schedule_due(array $settings): array
{
    if ($settings['emergency_stop']) {
        return [false, 'Emergency stop is on.'];
    }
    if (!$settings['enabled']) {
        return [false, 'Automatic generation is disabled.'];
    }
    if ($settings['frequency'] === 'manual') {
        return [false, 'Manual mode: runs only when started by hand.'];
    }
    $last = getDB()->query("SELECT MAX(started_at) FROM automation_runs WHERE trigger_type = 'scheduled' AND status IN ('completed', 'partial', 'running')")->fetchColumn();
    if ($last) {
        $minGap = $settings['frequency'] === 'weekly' ? 6.5 * 86400 : 20 * 3600;
        if (time() - strtotime($last) < $minGap) {
            return [false, 'Already ran this ' . ($settings['frequency'] === 'weekly' ? 'week' : 'day') . '.'];
        }
    }

    return [true, 'Due.'];
}

/** When the next scheduled run is expected (display only), or null with a reason. */
function automation_next_run(array $settings): array
{
    [, $reason] = automation_schedule_due($settings);
    if ($settings['emergency_stop'] || !$settings['enabled'] || $settings['frequency'] === 'manual') {
        return [null, $reason];
    }
    $last = getDB()->query("SELECT MAX(started_at) FROM automation_runs WHERE trigger_type = 'scheduled' AND status IN ('completed', 'partial', 'running')")->fetchColumn();
    $base = new DateTime('today');
    $base->setTime($settings['run_hour'], 0);
    if ($base->getTimestamp() <= time()) {
        $base->modify('+1 day');
    }
    if ($settings['frequency'] === 'weekly' && $last) {
        $earliest = (new DateTime($last))->modify('+7 days');
        $earliest->setTime($settings['run_hour'], 0);
        if ($earliest > $base) {
            $base = $earliest;
        }
    }

    return [$base->format('Y-m-d H:i'), null];
}

/** Starts a run. Returns [runId|null, httpStatus, error]. */
function automation_start_run(array $data, int $tokenId): array
{
    $settings = automation_settings();
    $trigger = in_array($data['trigger'] ?? '', ['scheduled', 'manual', 'retry'], true) ? $data['trigger'] : 'manual';

    if ($trigger === 'scheduled') {
        [$due, $reason] = automation_schedule_due($settings);
        if (!$due) {
            return [null, 409, 'Not due: ' . $reason];
        }
    }

    automation_mark_stale_runs();
    if ((int)getDB()->query("SELECT COUNT(*) FROM automation_runs WHERE status = 'running'")->fetchColumn() > 0) {
        return [null, 409, 'Another run is still in progress.'];
    }

    $retryOf = null;
    if ($trigger === 'retry') {
        $retryOf = (int)($data['retry_of'] ?? 0);
        $orig = $retryOf ? automation_get_run($retryOf) : null;
        if (!$orig || !$orig['retry_requested']) {
            return [null, 422, 'That run has not been marked for retry.'];
        }
        getDB()->prepare('UPDATE automation_runs SET retry_requested = 0 WHERE id = ?')->execute([$retryOf]);
    }

    $plan = isset($data['plan']) && is_array($data['plan']) ? array_slice($data['plan'], 0, 20) : [];
    getDB()->prepare('INSERT INTO automation_runs (trigger_type, planned, plan, retry_of, token_id, heartbeat_at) VALUES (?, ?, ?, ?, ?, NOW())')
        ->execute([$trigger, count($plan), json_encode($plan, JSON_UNESCAPED_UNICODE), $retryOf, $tokenId]);

    return [(int)getDB()->lastInsertId(), 200, null];
}

function automation_open_run(int $runId): ?array
{
    $run = automation_get_run($runId);

    return ($run && $run['status'] === 'running') ? $run : null;
}

function automation_finish_run(int $runId, string $status, string $message): void
{
    $status = in_array($status, ['completed', 'partial', 'failed'], true) ? $status : 'completed';
    $counts = getDB()->prepare(
        "SELECT SUM(resource_id IS NOT NULL AND status <> 'failed') AS gen, SUM(status = 'failed') AS fail FROM automation_items WHERE run_id = ?"
    );
    $counts->execute([$runId]);
    $c = $counts->fetch();
    getDB()->prepare("UPDATE automation_runs SET status = ?, message = ?, generated = ?, failed = ?, finished_at = NOW() WHERE id = ? AND status = 'running'")
        ->execute([$status, mb_substr($message, 0, 5000), (int)$c['gen'], (int)$c['fail'], $runId]);
}

// -----------------------------------------------------------------------
// TAXONOMY, CATALOG, DUPLICATES
// -----------------------------------------------------------------------

function automation_taxonomy(): array
{
    $subjects = [];
    foreach (get_all_subjects() as $s) {
        $subjects[] = [
            'slug'       => $s['slug'],
            'name'       => $s['name'],
            'grades'     => get_subject_grade_levels($s),
            'categories' => array_map(static fn($c) => ['slug' => $c['slug'], 'name' => $c['name'], 'group' => $c['group_name'] ?? null], get_categories((int)$s['id'])),
        ];
    }

    return ['subjects' => $subjects, 'resource_types' => RESOURCE_TYPES];
}

/** Lightweight catalog of every resource (no user data), for gap analysis and duplicate checks. */
function automation_catalog(): array
{
    $rows = getDB()->query(
        "SELECT r.id, r.title, r.slug, s.slug AS subject, r.grade_level AS grade, c.slug AS category, r.topic,
                r.resource_type, r.is_free, r.is_published, r.status, DATE(r.created_at) AS created,
                (SELECT ai.status FROM automation_items ai WHERE ai.resource_id = r.id LIMIT 1) AS automation_status
         FROM resources r
         INNER JOIN subjects s ON s.id = r.subject_id
         LEFT JOIN categories c ON c.id = r.category_id
         ORDER BY r.id"
    )->fetchAll();
    foreach ($rows as &$r) {
        $r['id'] = (int)$r['id'];
        $r['is_free'] = (bool)$r['is_free'];
        $r['is_published'] = (bool)$r['is_published'];
    }
    unset($r);

    // Generated topics that never became a resource (failed) still count as
    // "tried", so the planner doesn't keep picking the same failing topic.
    $attempts = getDB()->query(
        "SELECT subject_slug AS subject, grade_level AS grade, category_slug AS category, topic, title, tier, status FROM automation_items"
    )->fetchAll();

    return ['resources' => $rows, 'automation_items' => $attempts];
}

/** Lowercased word set for similarity checks (punctuation, stopwords and plural -s removed). */
function automation_tokens(string $text): array
{
    static $stop = ['a', 'an', 'the', 'and', 'or', 'of', 'to', 'in', 'on', 'for', 'with', 'my', 'our', 'your', 'pack', 'lesson',
        'worksheet', 'worksheets', 'resource', 'resources', 'practice', 'activity', 'activities', 'grade', 'complete', 'plan', 'kids', 'esl'];
    $words = preg_split('/[^a-z0-9]+/', strtolower($text)) ?: [];
    $out = [];
    foreach ($words as $w) {
        if ($w === '' || in_array($w, $stop, true) || preg_match('/^\d$/', $w)) {
            continue;
        }
        if (strlen($w) > 3 && substr($w, -1) === 's' && substr($w, -2) !== 'ss') {
            $w = substr($w, 0, -1);
        }
        $out[$w] = true;
    }

    return array_keys($out);
}

function automation_similarity(string $a, string $b): float
{
    $x = automation_tokens($a);
    $y = automation_tokens($b);
    if (!$x || !$y) {
        return 0.0;
    }
    $inter = count(array_intersect($x, $y));

    return $inter / count(array_unique(array_merge($x, $y)));
}

/**
 * Existing resources that duplicate or closely resemble a proposed one.
 * Blocking matches: same file, same title in the same subject, a topic at
 * least 60% similar in the same subject and grade, or a topic whose key words
 * all appear in an existing resource at the same grade.
 */
function automation_find_duplicates(string $subjectSlug, string $grade, string $title, string $topic, ?string $fileHash = null): array
{
    $matches = [];

    if ($fileHash) {
        $stmt = getDB()->prepare('SELECT id, title, resource_id FROM automation_items WHERE main_file_hash = ? LIMIT 1');
        $stmt->execute([$fileHash]);
        if ($row = $stmt->fetch()) {
            $matches[] = ['reason' => 'identical file already uploaded', 'title' => $row['title'], 'resource_id' => $row['resource_id'], 'score' => 1.0, 'blocking' => true];
        }
    }

    // Drafts an admin rejected stay in the resources table (automation never
    // deletes anything), but they must not block their own regenerated version.
    $stmt = getDB()->prepare(
        "SELECT r.id, r.title, r.topic, r.grade_level FROM resources r INNER JOIN subjects s ON s.id = r.subject_id
         WHERE s.slug = ? AND r.status = 'active'
           AND NOT (r.is_published = 0 AND EXISTS (SELECT 1 FROM automation_items ai WHERE ai.resource_id = r.id
                                                    AND ai.status IN ('rejected', 'regenerate_requested', 'regenerated')))"
    );
    $stmt->execute([$subjectSlug]);
    $topicWords = automation_tokens($topic);
    foreach ($stmt->fetchAll() as $r) {
        $sameTitle = automation_tokens($r['title']) === automation_tokens($title) && automation_tokens($title);
        $topicSim = max(automation_similarity($topic, (string)$r['topic']), automation_similarity($topic, $r['title']), automation_similarity($title, $r['title']));
        $sameGrade = (string)$r['grade_level'] === $grade;
        if ($sameTitle) {
            $matches[] = ['reason' => 'same title in this subject', 'title' => $r['title'], 'resource_id' => (int)$r['id'], 'score' => 1.0, 'blocking' => true];
        } elseif ($sameGrade && $topicSim >= 0.6) {
            $matches[] = ['reason' => 'substantially similar topic at the same grade', 'title' => $r['title'], 'resource_id' => (int)$r['id'], 'score' => round($topicSim, 2), 'blocking' => true];
        } elseif ($sameGrade && count($topicWords) >= 2
                  && !array_diff($topicWords, automation_tokens($r['title'] . ' ' . (string)$r['topic']))) {
            // Every key word of the topic already appears in an existing
            // resource at this grade (long titles dilute the similarity score).
            $matches[] = ['reason' => 'topic already covered at the same grade', 'title' => $r['title'], 'resource_id' => (int)$r['id'], 'score' => 1.0, 'blocking' => true];
        } elseif ($topicSim >= 0.6) {
            $matches[] = ['reason' => 'similar topic at another grade (allowed)', 'title' => $r['title'], 'resource_id' => (int)$r['id'], 'score' => round($topicSim, 2), 'blocking' => false];
        }
    }

    return $matches;
}

// -----------------------------------------------------------------------
// PACKAGE VALIDATION + DRAFT CREATION
// -----------------------------------------------------------------------

/** Structural check that a file is what its extension says (server side, independent of the generator). */
function automation_file_integrity(string $path, string $ext): ?string
{
    if (!is_file($path) || filesize($path) === 0) {
        return 'is empty';
    }
    if ($ext === 'pdf') {
        $fh = fopen($path, 'rb');
        $head = fread($fh, 5);
        fseek($fh, max(0, filesize($path) - 2048));
        $tail = fread($fh, 2048);
        fclose($fh);
        if ($head !== '%PDF-' || strpos($tail, '%%EOF') === false) {
            return 'is not a complete PDF';
        }
        return null;
    }
    if (in_array($ext, ['docx', 'pptx'], true)) {
        if (!class_exists('ZipArchive')) {
            return null; // can't inspect on this server; finfo check in handle_upload still applies
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return 'is not a valid Office file';
        }
        $need = $ext === 'docx' ? 'word/document.xml' : 'ppt/presentation.xml';
        $ok = $zip->locateName('[Content_Types].xml') !== false && $zip->locateName($need) !== false;
        $zip->close();
        return $ok ? null : 'is missing required Office parts';
    }
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        return @getimagesize($path) ? null : 'is not a readable image';
    }

    return 'has an unsupported type';
}

/** Lines of a multi-line field, trimmed, empty lines removed. */
function automation_lines($value): array
{
    if (is_array($value)) {
        $value = implode("\n", $value);
    }

    return array_values(array_filter(array_map('trim', preg_split('/\R/', (string)$value) ?: [])));
}

/**
 * Validates an uploaded package entirely on the server: metadata, taxonomy,
 * files, QC report and social copy. Returns [errors, normalized].
 */
function automation_validate_package(array $meta, array $files, $qc, $social): array
{
    $errors = [];
    $n = [];

    $taxonomy = automation_taxonomy();
    $subject = null;
    foreach ($taxonomy['subjects'] as $s) {
        if ($s['slug'] === ($meta['subject'] ?? '')) {
            $subject = $s;
        }
    }
    if (!$subject) {
        $errors[] = 'Unknown subject.';
    } else {
        if (!in_array($meta['grade'] ?? '', $subject['grades'], true)) {
            $errors[] = 'Grade is not valid for this subject.';
        }
        if (!in_array($meta['category'] ?? '', array_column($subject['categories'], 'slug'), true)) {
            $errors[] = 'Category does not belong to this subject.';
        }
        $settings = automation_settings();
        if (!in_array($subject['slug'], $settings['subjects'], true) || !in_array($meta['grade'] ?? '', $settings['grades'][$subject['slug']] ?? [], true)) {
            $errors[] = 'This subject/grade is not enabled in the automation settings.';
        }
    }
    if (!in_array($meta['resource_type'] ?? '', RESOURCE_TYPES, true)) {
        $errors[] = 'Unknown resource type.';
    }
    if (!in_array($meta['tier'] ?? '', ['free', 'pro'], true)) {
        $errors[] = 'Tier must be "free" or "pro".';
    }

    $len = static fn($v) => mb_strlen(trim((string)$v));
    if ($len($meta['title'] ?? '') < 8 || $len($meta['title'] ?? '') > 150) {
        $errors[] = 'Title must be 8–150 characters.';
    }
    if ($len($meta['topic'] ?? '') < 2 || $len($meta['topic'] ?? '') > 150) {
        $errors[] = 'Topic must be 2–150 characters.';
    }
    if ($len($meta['description'] ?? '') < 40 || $len($meta['description'] ?? '') > 500) {
        $errors[] = 'Description must be 40–500 characters.';
    }
    if ($len($meta['overview'] ?? '') < 80) {
        $errors[] = 'Overview is too short (80+ characters).';
    }
    if (count(automation_lines($meta['learning_objectives'] ?? '')) < 2) {
        $errors[] = 'At least two learning objectives are required.';
    }
    if (count(automation_lines($meta['how_to_use'] ?? '')) < 3) {
        $errors[] = 'How to Use needs at least three steps.';
    }
    if (count(automation_lines($meta['whats_included'] ?? '')) < 2) {
        $errors[] = "What's Included needs at least two lines.";
    }
    if ($len($meta['suggested_duration'] ?? '') < 3) {
        $errors[] = 'Suggested duration is required.';
    }

    // Files: the main download must be a PDF; extras PDF/DOCX/PPTX; thumbnail and preview required.
    $main = $files['resource_file'] ?? null;
    if (empty($main['name'])) {
        $errors[] = 'The main PDF is missing.';
    } elseif (strtolower(pathinfo($main['name'], PATHINFO_EXTENSION)) !== 'pdf') {
        $errors[] = 'The main file must be a PDF.';
    } elseif ($why = automation_file_integrity($main['tmp_name'], 'pdf')) {
        $errors[] = "The main PDF {$why}.";
    }
    foreach (['thumbnail' => [600, 400], 'preview_image' => [800, 600]] as $key => [$minW, $minH]) {
        $f = $files[$key] ?? null;
        if (empty($f['name'])) {
            $errors[] = ucfirst(str_replace('_', ' ', $key)) . ' is required.';
            continue;
        }
        $dim = @getimagesize($f['tmp_name']);
        if (!$dim) {
            $errors[] = ucfirst(str_replace('_', ' ', $key)) . ' is not a readable image.';
        } elseif ($dim[0] < $minW || $dim[1] < $minH) {
            $errors[] = ucfirst(str_replace('_', ' ', $key)) . " must be at least {$minW}x{$minH}px.";
        }
    }
    for ($i = 1; $i <= RESOURCE_ADDITIONAL_FILE_SLOTS; $i++) {
        $f = $files["additional_file_{$i}"] ?? null;
        if (empty($f['name'])) {
            continue;
        }
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, AUTOMATION_ADDITIONAL_EXTENSIONS, true)) {
            $errors[] = "Additional file {$i} must be PDF, DOCX or PPTX.";
        } elseif ($why = automation_file_integrity($f['tmp_name'], $ext)) {
            $errors[] = "Additional file {$i} {$why}.";
        }
        if (trim((string)($meta["additional_file_label_{$i}"] ?? '')) === '') {
            $errors[] = "Additional file {$i} needs a label.";
        }
    }

    // QC report: every mandatory check must have passed; the score must add up.
    if (!is_array($qc)) {
        $errors[] = 'QC report is missing or not valid JSON.';
    } else {
        $mandatory = $qc['mandatory'] ?? [];
        if (!is_array($mandatory) || !$mandatory) {
            $errors[] = 'QC report has no mandatory checks.';
        } else {
            foreach ($mandatory as $check) {
                if (empty($check['passed'])) {
                    $errors[] = 'Mandatory QC check failed: ' . ($check['id'] ?? '?') . '. Failed resources must be reported, not uploaded.';
                }
            }
        }
        $total = 0;
        foreach (AUTOMATION_SCORE_MAX as $cat => $max) {
            $v = $qc['breakdown'][$cat] ?? null;
            if (!is_numeric($v) || $v < 0 || $v > $max) {
                $errors[] = "QC score for {$cat} is missing or out of range.";
                continue;
            }
            $total += $v;
        }
        if (!isset($qc['score']) || abs((float)$qc['score'] - $total) > 1) {
            $errors[] = 'QC total score does not match its breakdown.';
        }
        $attempts = (int)($qc['correction_attempts'] ?? 0);
        if ($attempts < 0 || $attempts > AUTOMATION_MAX_CORRECTIONS) {
            $errors[] = 'Correction attempts out of range.';
        }
    }

    // Social copy (stored only; never posted automatically).
    if (!is_array($social)) {
        $errors[] = 'Social media copy is missing.';
    } else {
        if ($len($social['facebook_post'] ?? '') < 40 || $len($social['facebook_post'] ?? '') > 2000) {
            $errors[] = 'Facebook post must be 40–2000 characters.';
        }
        if ($len($social['pinterest_title'] ?? '') < 10 || $len($social['pinterest_title'] ?? '') > 100) {
            $errors[] = 'Pinterest title must be 10–100 characters.';
        }
        if ($len($social['pinterest_description'] ?? '') < 40 || $len($social['pinterest_description'] ?? '') > 500) {
            $errors[] = 'Pinterest description must be 40–500 characters.';
        }
        $tags = array_values(array_filter((array)($social['hashtags'] ?? []), static fn($t) => preg_match('/^#[\p{L}\p{N}_]{2,40}$/u', (string)$t)));
        if (count($tags) < 3 || count($tags) > 12) {
            $errors[] = 'Provide 3–12 valid hashtags.';
        }
        if (count(automation_lines($social['visual_suggestions'] ?? [])) < 1) {
            $errors[] = 'Provide at least one promotional visual suggestion.';
        }
        $n['hashtags'] = $tags;
    }

    if ($subject) {
        $n['subject_id'] = 0;
        foreach (get_all_subjects() as $s) {
            if ($s['slug'] === $subject['slug']) {
                $n['subject_id'] = (int)$s['id'];
            }
        }
        $stmt = getDB()->prepare('SELECT id FROM categories WHERE slug = ? AND subject_id = ? LIMIT 1');
        $stmt->execute([$meta['category'] ?? '', $n['subject_id']]);
        $n['category_id'] = (int)$stmt->fetchColumn();
    }

    return [$errors, $n];
}

/** Uploads created today (draft packages), for the daily cap. */
function automation_uploads_today(): int
{
    return (int)getDB()->query('SELECT COUNT(*) FROM automation_items WHERE resource_id IS NOT NULL AND DATE(created_at) = CURDATE()')->fetchColumn();
}

/**
 * Creates the draft through the normal create_resource() path and records
 * the automation item + social copy. Returns [httpStatus, body].
 */
function automation_create_draft(array $run, string $key, array $meta, array $files, array $qc, array $social, ?array $spec, int $tokenId): array
{
    [$errors, $n] = automation_validate_package($meta, $files, $qc, $social);
    if ($errors) {
        return [422, ['ok' => false, 'errors' => $errors]];
    }

    $hash = hash_file('sha256', $files['resource_file']['tmp_name']);
    $dupes = array_values(array_filter(
        automation_find_duplicates($meta['subject'], $meta['grade'], (string)$meta['title'], (string)$meta['topic'], $hash),
        static fn($m) => $m['blocking']
    ));
    if ($dupes) {
        return [409, ['ok' => false, 'errors' => ['Duplicate or substantially similar resource exists.'], 'duplicates' => $dupes]];
    }

    $input = [
        'title'                => $meta['title'],
        'description'          => $meta['description'],
        'resource_type'        => $meta['resource_type'],
        'subject_id'           => $n['subject_id'],
        'category_id'          => $n['category_id'] ?: '',
        'grade_level'          => $meta['grade'],
        'topic'                => $meta['topic'],
        'is_free'              => $meta['tier'] === 'free' ? 1 : 0,
        'is_published'         => 0,   // ALWAYS a draft — publishing is an admin decision
        'learning_objectives'  => implode("\n", automation_lines($meta['learning_objectives'])),
        'recommended_level'    => $meta['recommended_level'] ?? $meta['grade'],
        'suggested_duration'   => $meta['suggested_duration'],
        'skills_practiced'     => $meta['skills_practiced'] ?? '',
        'how_to_use'           => implode("\n", automation_lines($meta['how_to_use'])),
        'activity_ideas'       => implode("\n", automation_lines($meta['activity_ideas'] ?? '')),
        'teacher_tips'         => $meta['teacher_tips'] ?? '',
        'differentiation_notes' => $meta['differentiation_notes'] ?? '',
        'assessment_notes'     => $meta['assessment_notes'] ?? '',
        'overview'             => $meta['overview'],
        'whats_included'       => implode("\n", automation_lines($meta['whats_included'])),
    ];
    $createFiles = [
        'resource_file' => $files['resource_file'],
        'thumbnail'     => $files['thumbnail'],
        'preview_image' => $files['preview_image'],
    ];
    for ($i = 1; $i <= RESOURCE_ADDITIONAL_FILE_SLOTS; $i++) {
        if (!empty($files["additional_file_{$i}"]['name'])) {
            $createFiles["additional_file_{$i}"] = $files["additional_file_{$i}"];
            $input["additional_file_label_{$i}"] = $meta["additional_file_label_{$i}"];
        }
    }

    $result = create_resource($input, $createFiles);
    if (!$result['success']) {
        return [422, ['ok' => false, 'errors' => array_values($result['errors'])]];
    }
    $resourceId = (int)$result['id'];

    $db = getDB();
    $db->prepare(
        'INSERT INTO automation_items (run_id, resource_id, idempotency_key, title, subject_slug, grade_level, category_slug, topic, resource_type, tier,
                                       status, quality_score, score_breakdown, mandatory_passed, qc_report, correction_attempts, main_file_hash, spec)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?)'
    )->execute([
        (int)$run['id'], $resourceId, $key, mb_substr((string)$meta['title'], 0, 200), $meta['subject'], $meta['grade'], $meta['category'],
        mb_substr((string)$meta['topic'], 0, 150), $meta['resource_type'], $meta['tier'], 'pending_review', (int)round($qc['score']),
        json_encode($qc['breakdown']), json_encode($qc, JSON_UNESCAPED_UNICODE), (int)($qc['correction_attempts'] ?? 0), $hash,
        $spec ? json_encode($spec, JSON_UNESCAPED_UNICODE) : null,
    ]);
    $itemId = (int)$db->lastInsertId();

    $db->prepare('INSERT INTO resource_social (resource_id, facebook_post, pinterest_title, pinterest_description, hashtags, visual_suggestions) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([
            $resourceId, trim((string)$social['facebook_post']), trim((string)$social['pinterest_title']), trim((string)$social['pinterest_description']),
            implode(' ', $n['hashtags']), implode("\n", automation_lines($social['visual_suggestions'])),
        ]);

    automation_close_regenerated($meta);
    $db->prepare('UPDATE automation_runs SET heartbeat_at = NOW() WHERE id = ?')->execute([(int)$run['id']]);

    return [201, ['ok' => true, 'item_id' => $itemId, 'resource_id' => $resourceId, 'status' => 'pending_review', 'published' => false]];
}

/** Records a resource that failed QC after all correction attempts (nothing is uploaded). */
function automation_record_failure(array $run, string $key, array $data): array
{
    $meta = (array)($data['meta'] ?? []);
    $qc = $data['qc_report'] ?? null;
    getDB()->prepare(
        'INSERT INTO automation_items (run_id, resource_id, idempotency_key, title, subject_slug, grade_level, category_slug, topic, resource_type, tier,
                                       status, quality_score, score_breakdown, mandatory_passed, qc_report, correction_attempts, error_report, spec)
         VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, \'failed\', ?, ?, 0, ?, ?, ?, ?)'
    )->execute([
        (int)$run['id'], $key, mb_substr((string)($meta['title'] ?? 'Untitled'), 0, 200), (string)($meta['subject'] ?? ''), (string)($meta['grade'] ?? ''),
        $meta['category'] ?? null, mb_substr((string)($meta['topic'] ?? ''), 0, 150), (string)($meta['resource_type'] ?? ''),
        ($meta['tier'] ?? '') === 'free' ? 'free' : 'pro',
        is_array($qc) && isset($qc['score']) ? (int)round($qc['score']) : null,
        is_array($qc) && isset($qc['breakdown']) ? json_encode($qc['breakdown']) : null,
        is_array($qc) ? json_encode($qc, JSON_UNESCAPED_UNICODE) : null,
        max(0, min(AUTOMATION_MAX_CORRECTIONS, (int)($data['correction_attempts'] ?? 0))),
        mb_substr(is_string($data['error_report'] ?? null) ? $data['error_report'] : json_encode($data['error_report'] ?? null, JSON_UNESCAPED_UNICODE), 0, 60000),
        isset($data['spec']) ? json_encode($data['spec'], JSON_UNESCAPED_UNICODE) : null,
    ]);
    automation_close_regenerated($meta);

    return ['ok' => true, 'item_id' => (int)getDB()->lastInsertId(), 'status' => 'failed'];
}

/** When a package regenerates an earlier item, close the old item so the queue doesn't repeat it. */
function automation_close_regenerated(array $meta): void
{
    $old = (int)($meta['regenerates_item_id'] ?? 0);
    if ($old > 0) {
        getDB()->prepare("UPDATE automation_items SET status = 'regenerated' WHERE id = ? AND status = 'regenerate_requested'")->execute([$old]);
    }
}

/** Items marked for regeneration and runs marked for retry — the generator's to-do list. */
function automation_work_queue(): array
{
    $regen = getDB()->query(
        "SELECT id, title, subject_slug AS subject, grade_level AS grade, category_slug AS category, topic, resource_type, tier, review_note, error_report, spec
         FROM automation_items WHERE status = 'regenerate_requested' ORDER BY updated_at LIMIT 10"
    )->fetchAll();
    foreach ($regen as &$r) {
        $r['spec'] = $r['spec'] ? json_decode($r['spec'], true) : null;
    }
    unset($r);
    $retry = getDB()->query("SELECT id, plan, message FROM automation_runs WHERE retry_requested = 1 ORDER BY id LIMIT 5")->fetchAll();
    foreach ($retry as &$r) {
        $r['plan'] = $r['plan'] ? json_decode($r['plan'], true) : [];
    }
    unset($r);

    return ['regenerate' => $regen, 'retry_runs' => $retry];
}

// -----------------------------------------------------------------------
// ADMIN REVIEW
// -----------------------------------------------------------------------

function automation_get_item(int $id): ?array
{
    $stmt = getDB()->prepare(
        'SELECT i.*, r.is_published, r.slug AS resource_slug, r.thumbnail, r.preview_image, r.file_name, r.file_size, r.is_free, r.status AS resource_status
         FROM automation_items i LEFT JOIN resources r ON r.id = i.resource_id WHERE i.id = ?'
    );
    $stmt->execute([$id]);

    return $stmt->fetch() ?: null;
}

function automation_list_items(array $statuses, int $limit = 50): array
{
    $marks = implode(',', array_fill(0, count($statuses), '?'));
    $stmt = getDB()->prepare(
        "SELECT i.*, r.thumbnail, r.is_published FROM automation_items i LEFT JOIN resources r ON r.id = i.resource_id
         WHERE i.status IN ({$marks}) ORDER BY i.id DESC LIMIT " . max(1, $limit)
    );
    $stmt->execute($statuses);

    return $stmt->fetchAll();
}

/**
 * Approve = the admin's QC confirmation. Publishes ONLY the draft this item
 * created, only while it is still an unpublished, active draft.
 */
function automation_approve_item(int $itemId, int $adminId): ?string
{
    $item = automation_get_item($itemId);
    if (!$item || $item['status'] !== 'pending_review' || !$item['resource_id']) {
        return 'This item is not awaiting review.';
    }
    if ((int)$item['is_published'] === 1 || $item['resource_status'] !== 'active') {
        return 'The resource is no longer an unpublished draft.';
    }
    $db = getDB();
    $db->prepare('UPDATE resources SET is_published = 1, qc_confirmed_at = NOW() WHERE id = ? AND is_published = 0')->execute([(int)$item['resource_id']]);
    $db->prepare("UPDATE automation_items SET status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?")->execute([$adminId, $itemId]);
    automation_audit('item_approved', 200, "item #{$itemId} resource #{$item['resource_id']}", null, $adminId);

    return null;
}

function automation_reject_item(int $itemId, int $adminId, string $note, bool $regenerate): ?string
{
    $item = automation_get_item($itemId);
    if (!$item || !in_array($item['status'], ['pending_review', 'failed'], true)) {
        return 'This item cannot be rejected now.';
    }
    $status = $regenerate ? 'regenerate_requested' : ($item['status'] === 'failed' ? 'failed' : 'rejected');
    getDB()->prepare('UPDATE automation_items SET status = ?, review_note = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?')
        ->execute([$status, mb_substr($note, 0, 500) ?: null, $adminId, $itemId]);
    automation_audit($regenerate ? 'item_regenerate' : 'item_rejected', 200, "item #{$itemId}", null, $adminId);

    return null;
}

function automation_request_retry(int $runId, int $adminId): ?string
{
    $run = automation_get_run($runId);
    if (!$run || !in_array($run['status'], ['interrupted', 'failed', 'partial', 'stopped'], true)) {
        return 'Only interrupted, stopped, failed or partial runs can be retried.';
    }
    getDB()->prepare('UPDATE automation_runs SET retry_requested = 1 WHERE id = ?')->execute([$runId]);
    automation_audit('run_retry_requested', 200, "run #{$runId}", null, $adminId);

    return null;
}

function automation_stats(): array
{
    $db = getDB();
    $row = $db->query(
        "SELECT COUNT(*) AS total,
                SUM(i.status = 'pending_review') AS pending,
                SUM(i.status = 'failed') AS failed,
                SUM(i.status IN ('rejected', 'regenerate_requested', 'regenerated')) AS rejected,
                SUM(i.resource_id IS NOT NULL AND r.is_published = 1) AS published,
                SUM(i.resource_id IS NOT NULL AND i.tier = 'free') AS free,
                SUM(i.resource_id IS NOT NULL AND i.tier = 'pro') AS pro
         FROM automation_items i LEFT JOIN resources r ON r.id = i.resource_id"
    )->fetch();

    return array_map('intval', $row) + ['last_success' => automation_last_success()];
}

function automation_social_for(int $resourceId): ?array
{
    $stmt = getDB()->prepare('SELECT * FROM resource_social WHERE resource_id = ?');
    $stmt->execute([$resourceId]);

    return $stmt->fetch() ?: null;
}
