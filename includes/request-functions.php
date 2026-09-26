<?php
/**
 * "Request a Resource" — lets a teacher tell us what resource they need,
 * lets other teachers add their voice to an existing request instead of
 * creating a duplicate, and gives admins a demand-driven view of what to
 * produce next. Mirrors the review system's shape: one base record plus
 * a per-supporter table, so "N teachers asked for this" is always a real
 * COUNT(*) — never a maintained counter that could drift.
 */

/** Validates raw request-form input. Returns a field => message error map; empty means valid. */
function validate_request_input(array $input): array
{
    $errors = [];

    $subjectId = (int)($input['subject_id'] ?? 0);
    if ($subjectId <= 0) {
        $errors['subject_id'] = 'Please select a subject.';
    }

    $grade = trim((string)($input['grade_level'] ?? ''));
    if ($grade === '' || !in_array($grade, GRADE_LEVELS, true)) {
        $errors['grade_level'] = 'Please select a grade.';
    }

    $topic = trim((string)($input['topic'] ?? ''));
    if ($topic === '' || mb_strlen($topic) > 200) {
        $errors['topic'] = 'Please enter the resource topic.';
    }

    $resourceType = trim((string)($input['resource_type'] ?? ''));
    $resourceTypeOther = trim((string)($input['resource_type_other'] ?? ''));
    if ($resourceType === '') {
        $errors['resource_type'] = 'Please select a resource type.';
    } elseif ($resourceType === 'Other' && $resourceTypeOther === '') {
        $errors['resource_type_other'] = 'Please describe the resource type you need.';
    } elseif ($resourceType !== 'Other' && !in_array($resourceType, RESOURCE_TYPES, true)) {
        $errors['resource_type'] = 'Please select a valid resource type.';
    }

    $description = trim((string)($input['description'] ?? ''));
    if ($description === '') {
        $errors['description'] = 'Please describe what you need.';
    } elseif (mb_strlen($description) > 2000) {
        $errors['description'] = 'Please keep the description under 2000 characters.';
    }

    $email = trim((string)($input['email'] ?? ''));
    if ($email !== '' && !validate_email_format($email)) {
        $errors['email'] = 'Please enter a valid email address, or leave it blank.';
    }

    $difficulty = trim((string)($input['difficulty'] ?? ''));
    if ($difficulty !== '' && !array_key_exists($difficulty, REQUEST_DIFFICULTIES)) {
        $errors['difficulty'] = 'Please choose a valid difficulty.';
    }

    $formats = array_filter((array)($input['preferred_formats'] ?? []));
    foreach ($formats as $format) {
        if (!array_key_exists($format, REQUEST_FORMATS)) {
            $errors['preferred_formats'] = 'Please choose valid preferred formats.';
            break;
        }
    }

    return $errors;
}

/**
 * Lightweight duplicate detection: an existing, still-open request for the
 * same subject/grade/resource type with the same topic (case-insensitive,
 * trimmed). Deliberately simple — a much fuzzier match risks incorrectly
 * merging genuinely different requests, which the brief explicitly warns
 * against; an exact topic match on the same subject/grade/type is a safe,
 * high-confidence signal without needing keyword-similarity scoring.
 */
function find_similar_request(array $input): ?array
{
    $subjectId = (int)($input['subject_id'] ?? 0);
    $grade = trim((string)($input['grade_level'] ?? ''));
    $topic = trim((string)($input['topic'] ?? ''));
    $resourceType = trim((string)($input['resource_type'] ?? ''));

    if ($subjectId <= 0 || $grade === '' || $topic === '') {
        return null;
    }

    $stmt = getDB()->prepare(
        "SELECT * FROM resource_requests
         WHERE subject_id = ? AND grade_level = ? AND resource_type = ?
           AND LOWER(TRIM(topic)) = LOWER(?)
           AND status NOT IN ('declined', 'duplicate')
         ORDER BY created_at DESC LIMIT 1"
    );
    $stmt->execute([$subjectId, $grade, $resourceType, $topic]);

    return $stmt->fetch() ?: null;
}

/**
 * Creates a new request record plus a supporter row for the submitter
 * themselves (so they're counted in that request's own demand total).
 * Returns ['success' => bool, 'errors' => array, 'id' => ?int].
 */
function create_resource_request(array $input, ?int $userId): array
{
    $errors = validate_request_input($input);
    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors, 'id' => null];
    }

    $resourceType = trim((string)$input['resource_type']);
    $formats = array_filter((array)($input['preferred_formats'] ?? []), static fn($f) => array_key_exists($f, REQUEST_FORMATS));

    $db = getDB();
    $stmt = $db->prepare(
        'INSERT INTO resource_requests
            (user_id, name, email, subject_id, grade_level, topic, resource_type, resource_type_other,
             description, difficulty, preferred_formats, additional_notes)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $email = trim((string)($input['email'] ?? ''));
    $stmt->execute([
        $userId,
        clean_input($input['name'] ?? '') ?: null,
        $email !== '' ? $email : null,
        (int)$input['subject_id'],
        trim((string)$input['grade_level']),
        clean_input($input['topic']),
        $resourceType,
        $resourceType === 'Other' ? clean_input($input['resource_type_other'] ?? '') : null,
        clean_input($input['description']),
        trim((string)($input['difficulty'] ?? '')) ?: null,
        !empty($formats) ? implode(',', $formats) : null,
        clean_input($input['additional_notes'] ?? '') ?: null,
    ]);

    $requestId = (int)$db->lastInsertId();
    add_request_supporter($requestId, $userId, $email !== '' ? $email : null);

    return ['success' => true, 'errors' => [], 'id' => $requestId];
}

/**
 * Records one teacher's support for a request — the submitter's own
 * support (from create_resource_request()) and every later "I Need This
 * Too" click share this same path. Silently no-ops on an obvious repeat
 * (same logged-in user, or same email) rather than erroring, since
 * clicking twice isn't something a teacher should see rejected.
 */
function add_request_supporter(int $requestId, ?int $userId, ?string $email): void
{
    $db = getDB();

    if ($userId !== null) {
        $existing = $db->prepare('SELECT id FROM resource_request_supporters WHERE request_id = ? AND user_id = ?');
        $existing->execute([$requestId, $userId]);
        if ($existing->fetch()) {
            return;
        }
    } elseif ($email !== null) {
        $existing = $db->prepare('SELECT id FROM resource_request_supporters WHERE request_id = ? AND user_id IS NULL AND email = ?');
        $existing->execute([$requestId, $email]);
        if ($existing->fetch()) {
            return;
        }
    }

    $stmt = $db->prepare('INSERT INTO resource_request_supporters (request_id, user_id, email) VALUES (?, ?, ?)');
    $stmt->execute([$requestId, $userId, $email]);
}

/**
 * "I Need This Too" on an existing request. Returns
 * ['success' => bool, 'error' => ?string] — fails only if the request
 * doesn't exist or is no longer open (published/declined/duplicate),
 * since supporting a finished request doesn't mean anything.
 */
function support_existing_request(int $requestId, ?int $userId, ?string $email): array
{
    $request = get_request_by_id($requestId);
    if (!$request) {
        return ['success' => false, 'error' => 'That request could not be found.'];
    }
    if (in_array($request['status'], ['published', 'declined', 'duplicate'], true)) {
        return ['success' => false, 'error' => 'That request is no longer open for support.'];
    }

    add_request_supporter($requestId, $userId, $email);

    return ['success' => true, 'error' => null];
}

function get_request_by_id(int $id): ?array
{
    $stmt = getDB()->prepare(
        "SELECT r.*, s.name AS subject_name, res.title AS linked_resource_title, res.slug AS linked_resource_slug,
                dup.id AS duplicate_of_display_id
         FROM resource_requests r
         LEFT JOIN subjects s ON s.id = r.subject_id
         LEFT JOIN resources res ON res.id = r.linked_resource_id
         LEFT JOIN resource_requests dup ON dup.id = r.duplicate_of_id
         WHERE r.id = ? LIMIT 1"
    );
    $stmt->execute([$id]);

    return $stmt->fetch() ?: null;
}

/** ['count' => int, 'first_at' => ?string, 'last_at' => ?string] — never exposes who supported it. */
function get_request_demand(int $requestId): array
{
    $stmt = getDB()->prepare(
        'SELECT COUNT(*) AS total, MIN(created_at) AS first_at, MAX(created_at) AS last_at
         FROM resource_request_supporters WHERE request_id = ?'
    );
    $stmt->execute([$requestId]);
    $row = $stmt->fetch();

    return [
        'count'    => (int)($row['total'] ?? 0),
        'first_at' => $row['first_at'] ?? null,
        'last_at'  => $row['last_at'] ?? null,
    ];
}

/**
 * Admin listing with filters + search + pagination, each row carrying its
 * live demand count. $filters may contain: status, subject_id, grade,
 * resource_type, difficulty, search, date_from, date_to.
 */
function get_all_requests_for_admin(array $filters, int $page, int $perPage): array
{
    $where = [];
    $params = [];

    $status = trim((string)($filters['status'] ?? ''));
    if ($status !== '') {
        $where[] = 'r.status = ?';
        $params[] = $status;
    }
    $subjectId = (int)($filters['subject_id'] ?? 0);
    if ($subjectId > 0) {
        $where[] = 'r.subject_id = ?';
        $params[] = $subjectId;
    }
    $grade = trim((string)($filters['grade'] ?? ''));
    if ($grade !== '') {
        $where[] = 'r.grade_level = ?';
        $params[] = $grade;
    }
    $resourceType = trim((string)($filters['resource_type'] ?? ''));
    if ($resourceType !== '') {
        $where[] = 'r.resource_type = ?';
        $params[] = $resourceType;
    }
    $difficulty = trim((string)($filters['difficulty'] ?? ''));
    if ($difficulty !== '') {
        $where[] = 'r.difficulty = ?';
        $params[] = $difficulty;
    }
    $dateFrom = trim((string)($filters['date_from'] ?? ''));
    if ($dateFrom !== '') {
        $where[] = 'r.created_at >= ?';
        $params[] = $dateFrom . ' 00:00:00';
    }
    $dateTo = trim((string)($filters['date_to'] ?? ''));
    if ($dateTo !== '') {
        $where[] = 'r.created_at <= ?';
        $params[] = $dateTo . ' 23:59:59';
    }
    $search = trim((string)($filters['search'] ?? ''));
    if ($search !== '') {
        if (ctype_digit($search)) {
            $where[] = '(r.id = ? OR r.topic LIKE ? OR r.description LIKE ? OR r.email LIKE ?)';
            $params[] = (int)$search;
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        } else {
            $where[] = '(r.topic LIKE ? OR r.description LIKE ? OR r.email LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }
    }

    $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    $db = getDB();

    $countStmt = $db->prepare("SELECT COUNT(*) FROM resource_requests r {$whereSql}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($total / $perPage));
    $page = min(max(1, $page), $totalPages);
    $offset = ($page - 1) * $perPage;

    $stmt = $db->prepare(
        "SELECT r.*, s.name AS subject_name,
                (SELECT COUNT(*) FROM resource_request_supporters WHERE request_id = r.id) AS demand_count
         FROM resource_requests r
         LEFT JOIN subjects s ON s.id = r.subject_id
         {$whereSql}
         ORDER BY demand_count DESC, r.created_at DESC
         LIMIT {$perPage} OFFSET {$offset}"
    );
    $stmt->execute($params);

    return ['items' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'total_pages' => $totalPages];
}

/** Dashboard summary counts. */
function get_request_stats(): array
{
    $stmt = getDB()->query("SELECT status, COUNT(*) AS total FROM resource_requests GROUP BY status");
    $byStatus = array_fill_keys(array_keys(REQUEST_STATUSES), 0);
    foreach ($stmt->fetchAll() as $row) {
        $byStatus[$row['status']] = (int)$row['total'];
    }

    return [
        'total'       => array_sum($byStatus),
        'by_status'   => $byStatus,
    ];
}

/**
 * Demand insights for the admin dashboard — every figure here is a real
 * aggregate query; when there isn't enough data yet the caller gets null
 * back and shows "Not enough data yet" rather than a fabricated figure.
 */
function get_request_insights(): array
{
    $db = getDB();

    $thisWeek = (int)$db->query("SELECT COUNT(*) FROM resource_requests WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
    $thisMonth = (int)$db->query("SELECT COUNT(*) FROM resource_requests WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();

    $topSubject = $db->query(
        "SELECT s.name, COUNT(*) AS total FROM resource_requests r
         INNER JOIN subjects s ON s.id = r.subject_id
         GROUP BY s.id, s.name ORDER BY total DESC LIMIT 1"
    )->fetch();

    $topGrade = $db->query(
        "SELECT grade_level, COUNT(*) AS total FROM resource_requests
         WHERE grade_level IS NOT NULL GROUP BY grade_level ORDER BY total DESC LIMIT 1"
    )->fetch();

    $topType = $db->query(
        "SELECT resource_type, COUNT(*) AS total FROM resource_requests
         WHERE resource_type IS NOT NULL AND resource_type != '' GROUP BY resource_type ORDER BY total DESC LIMIT 1"
    )->fetch();

    return [
        'this_week'   => $thisWeek,
        'this_month'  => $thisMonth,
        'top_subject' => $topSubject['name'] ?? null,
        'top_grade'   => $topGrade['grade_level'] ?? null,
        'top_type'    => $topType['resource_type'] ?? null,
    ];
}

/** Open (not published/declined/duplicate) requests ranked by real demand — for the admin "Most Requested" view and the public teaser. */
function get_most_requested_open(int $limit = 10): array
{
    $stmt = getDB()->prepare(
        "SELECT r.id, r.topic, r.grade_level, r.resource_type, s.name AS subject_name,
                (SELECT COUNT(*) FROM resource_request_supporters WHERE request_id = r.id) AS demand_count
         FROM resource_requests r
         LEFT JOIN subjects s ON s.id = r.subject_id
         WHERE r.status NOT IN ('published', 'declined', 'duplicate')
         GROUP BY r.id
         HAVING demand_count > 0
         ORDER BY demand_count DESC, r.created_at ASC
         LIMIT " . max(1, $limit)
    );
    $stmt->execute();

    return $stmt->fetchAll();
}

function update_request_status(int $id, string $status): bool
{
    if (!array_key_exists($status, REQUEST_STATUSES)) {
        return false;
    }
    $stmt = getDB()->prepare('UPDATE resource_requests SET status = ? WHERE id = ?');
    $stmt->execute([$status, $id]);

    return true;
}

function set_request_admin_notes(int $id, string $notes): void
{
    $stmt = getDB()->prepare('UPDATE resource_requests SET admin_notes = ? WHERE id = ?');
    $stmt->execute([$notes !== '' ? $notes : null, $id]);
}

function assign_request_admin(int $id, ?int $adminId): void
{
    $stmt = getDB()->prepare('UPDATE resource_requests SET assigned_admin_id = ? WHERE id = ?');
    $stmt->execute([$adminId, $id]);
}

/**
 * Marks $id as a duplicate of $duplicateOfId and folds its supporters into
 * the target so demand consolidates onto one request rather than being
 * split across two records for the same actual need. Never deletes the
 * original request — it stays in the table with status='duplicate' for
 * historical record, exactly as required.
 */
function mark_request_duplicate(int $id, int $duplicateOfId): array
{
    if ($id === $duplicateOfId) {
        return ['success' => false, 'error' => 'A request cannot be a duplicate of itself.'];
    }
    $target = get_request_by_id($duplicateOfId);
    if (!$target) {
        return ['success' => false, 'error' => 'The target request could not be found.'];
    }

    $db = getDB();
    $supporters = $db->prepare('SELECT user_id, email FROM resource_request_supporters WHERE request_id = ?');
    $supporters->execute([$id]);
    foreach ($supporters->fetchAll() as $supporter) {
        add_request_supporter($duplicateOfId, $supporter['user_id'] !== null ? (int)$supporter['user_id'] : null, $supporter['email']);
    }

    $stmt = $db->prepare('UPDATE resource_requests SET status = ?, duplicate_of_id = ? WHERE id = ?');
    $stmt->execute(['duplicate', $duplicateOfId, $id]);

    return ['success' => true, 'error' => null];
}

/**
 * Links a request to a real, existing resource (never creates one) and
 * marks it published. Notifies every supporter who left an email — not
 * just the original submitter — since every one of them asked for this.
 */
function link_request_to_resource(int $requestId, int $resourceId): array
{
    $resource = get_resource_by_id($resourceId);
    if (!$resource) {
        return ['success' => false, 'error' => 'That resource could not be found.'];
    }

    $db = getDB();
    $stmt = $db->prepare("UPDATE resource_requests SET linked_resource_id = ?, status = 'published', completed_at = NOW() WHERE id = ?");
    $stmt->execute([$resourceId, $requestId]);

    notify_request_supporters_published($requestId, $resource);

    return ['success' => true, 'error' => null];
}

/** Emails every supporter with an email on file, once each, skipping anyone already notified for this request. */
function notify_request_supporters_published(int $requestId, array $resource): void
{
    $db = getDB();
    $stmt = $db->prepare(
        'SELECT id, email FROM resource_request_supporters WHERE request_id = ? AND email IS NOT NULL AND notified_at IS NULL'
    );
    $stmt->execute([$requestId]);
    $supporters = $stmt->fetchAll();

    if (empty($supporters)) {
        return;
    }

    $markNotified = $db->prepare('UPDATE resource_request_supporters SET notified_at = NOW() WHERE id = ?');
    foreach ($supporters as $supporter) {
        send_resource_request_published_email($supporter['email'], $resource);
        $markNotified->execute([$supporter['id']]);
    }
}

/** Streams a CSV of requests matching $filters directly to the browser (same filter shape as get_all_requests_for_admin()). Ends the request. */
function stream_requests_csv(array $filters): void
{
    $filters = $filters + ['status' => '', 'subject_id' => 0];
    $rows = get_all_requests_for_admin($filters, 1, 100000)['items'];

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="resource-requests-' . date('Y-m-d') . '.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Date', 'Subject', 'Grade', 'Topic', 'Resource Type', 'Requests', 'Status', 'Email', 'Linked Resource ID'], ',', '"', '\\');
    foreach ($rows as $row) {
        fputcsv($out, [
            $row['id'],
            $row['created_at'],
            $row['subject_name'] ?? '',
            $row['grade_level'] ?? '',
            $row['topic'],
            $row['resource_type'] === 'Other' ? ($row['resource_type_other'] ?? 'Other') : $row['resource_type'],
            $row['demand_count'],
            REQUEST_STATUSES[$row['status']] ?? $row['status'],
            $row['email'] ?? '',
            $row['linked_resource_id'] ?? '',
        ], ',', '"', '\\');
    }
    fclose($out);
    exit;
}
