<?php
/**
 * Resource/category queries: public read-side queries, category CRUD
 * (used by /admin/categories.php), and resource CRUD (used by
 * /admin/resources.php, resource-add.php, resource-edit.php).
 */

/** $subjectId filters to one subject's categories; omit/0 for all subjects (e.g. the admin category list). */
function get_categories(int $subjectId = 0): array
{
    static $all = null;

    if ($all === null) {
        $stmt = getDB()->query(
            'SELECT c.id, c.name, c.slug, c.group_name, c.subject_id, c.sort_order, s.name AS subject_name, s.slug AS subject_slug
             FROM categories c
             INNER JOIN subjects s ON s.id = c.subject_id
             ORDER BY s.sort_order, c.sort_order, c.name'
        );
        $all = $stmt->fetchAll();
    }

    if ($subjectId <= 0) {
        return $all;
    }

    return array_values(array_filter($all, static fn($c) => (int)$c['subject_id'] === $subjectId));
}

/** Categories grouped by subject name, e.g. ['ESL' => [...], 'Math' => [...], 'Science' => [...]]. */
function get_categories_grouped(int $subjectId = 0): array
{
    $grouped = [];

    foreach (get_categories($subjectId) as $category) {
        $grouped[$category['subject_name']][] = $category;
    }

    return $grouped;
}

function get_category_by_id(int $id): ?array
{
    $stmt = getDB()->prepare('SELECT * FROM categories WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);

    return $stmt->fetch() ?: null;
}

function category_slug_exists(string $slug, ?int $excludeId = null): bool
{
    if ($excludeId !== null) {
        $stmt = getDB()->prepare('SELECT id FROM categories WHERE slug = ? AND id != ? LIMIT 1');
        $stmt->execute([$slug, $excludeId]);
    } else {
        $stmt = getDB()->prepare('SELECT id FROM categories WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
    }

    return (bool)$stmt->fetch();
}

/** Returns ['success' => bool, 'errors' => array<string,string>] */
function create_category(array $input): array
{
    $errors = validate_category_input($input);

    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }

    $name = clean_input($input['name']);
    $slug = slugify($input['name']);
    $subjectId = (int)$input['subject_id'];
    $sortOrder = (int)($input['sort_order'] ?? 0);

    getDB()->prepare('INSERT INTO categories (subject_id, name, slug, sort_order) VALUES (?, ?, ?, ?)')
        ->execute([$subjectId, $name, $slug, $sortOrder]);

    return ['success' => true, 'errors' => []];
}

/** Returns ['success' => bool, 'errors' => array<string,string>] */
function update_category(int $id, array $input): array
{
    $errors = validate_category_input($input, $id);

    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }

    $name = clean_input($input['name']);
    $slug = slugify($input['name']);
    $subjectId = (int)$input['subject_id'];
    $sortOrder = (int)($input['sort_order'] ?? 0);

    getDB()->prepare('UPDATE categories SET subject_id = ?, name = ?, slug = ?, sort_order = ? WHERE id = ?')
        ->execute([$subjectId, $name, $slug, $sortOrder, $id]);

    return ['success' => true, 'errors' => []];
}

function validate_category_input(array $input, ?int $excludeId = null): array
{
    $errors = [];

    $name = clean_input($input['name'] ?? '');

    if ($name === '' || mb_strlen($name) > 100) {
        $errors['name'] = 'Please enter a category name.';
    } elseif (category_slug_exists(slugify($name), $excludeId)) {
        $errors['name'] = 'A category with this name already exists.';
    }

    if (!get_subject_by_id((int)($input['subject_id'] ?? 0))) {
        $errors['subject_id'] = 'Please choose a valid subject.';
    }

    return $errors;
}

/** Resources referencing this category simply lose their category (ON DELETE SET NULL) — they are not deleted. */
function delete_category(int $id): void
{
    getDB()->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
}

/**
 * Which teaching-detail fields to show, in what order, and under what
 * heading — varies by resource type so a Lesson Plan and a Flashcards
 * set don't read identically (a Lesson Plan leads with objectives and
 * procedure; Flashcards leads with how to present them and games).
 * Sections with no content are simply skipped by resource.php — this
 * only controls order/labeling, never invents content.
 */
function get_teaching_detail_layout(string $resourceType): array
{
    return match ($resourceType) {
        'Lesson Plan' => [
            'learning_objectives'   => 'Learning Objectives',
            'how_to_use'            => 'Lesson Procedure',
            'assessment_notes'      => 'Assessment',
            'differentiation_notes' => 'Differentiation',
            'activity_ideas'        => 'Classroom Activity Ideas',
            'teacher_tips'          => 'Teacher Tips',
        ],
        'Worksheet' => [
            'how_to_use'            => 'Student Task',
            'activity_ideas'        => 'Classroom Use',
            'differentiation_notes' => 'Differentiation',
            'assessment_notes'      => 'Answer Key & Assessment',
            'learning_objectives'   => 'Learning Objectives',
            'teacher_tips'          => 'Teacher Tips',
        ],
        'PowerPoint' => [
            'how_to_use'            => 'Lesson Sequence',
            'activity_ideas'        => 'Classroom Activities',
            'teacher_tips'          => 'Teacher Tips',
            'learning_objectives'   => 'Learning Objectives',
            'differentiation_notes' => 'Differentiation',
            'assessment_notes'      => 'Assessment',
        ],
        'Flashcards' => [
            'how_to_use'            => 'How to Present',
            'activity_ideas'        => 'Games & Speaking Activities',
            'teacher_tips'          => 'Teacher Tips',
            'learning_objectives'   => 'Learning Objectives',
            'differentiation_notes' => 'Differentiation',
            'assessment_notes'      => 'Assessment',
        ],
        'Test', 'Assessment' => [
            'assessment_notes'      => 'Question Types & Suggested Use',
            'learning_objectives'   => 'Skills Assessed',
            'how_to_use'            => 'How to Administer',
            'differentiation_notes' => 'Differentiation',
            'activity_ideas'        => 'Follow-Up Activities',
            'teacher_tips'          => 'Teacher Tips',
        ],
        'Game', 'Classroom Activity' => [
            'how_to_use'            => 'How to Play',
            'activity_ideas'        => 'Variations',
            'teacher_tips'          => 'Teacher Tips',
            'learning_objectives'   => 'Learning Objectives',
            'differentiation_notes' => 'Differentiation',
            'assessment_notes'      => 'Assessment',
        ],
        default => [
            'learning_objectives'   => 'Learning Objectives',
            'how_to_use'            => 'How to Use This Resource',
            'activity_ideas'        => 'Classroom Activity Ideas',
            'differentiation_notes' => 'Differentiation',
            'assessment_notes'      => 'Assessment',
            'teacher_tips'          => 'Teacher Tips',
        ],
    };
}

function resource_type_icon(string $resourceType): string
{
    return match ($resourceType) {
        'Lesson Plan'         => 'fa-chalkboard-teacher',
        'Worksheet'           => 'fa-file-lines',
        'PowerPoint'          => 'fa-display',
        'Flashcards'          => 'fa-layer-group',
        'Classroom Activity'  => 'fa-people-group',
        'Game'                => 'fa-gamepad',
        'Test'                => 'fa-pen-to-square',
        'Assessment'          => 'fa-clipboard-check',
        'Poster'              => 'fa-image',
        'Teacher Resource'    => 'fa-book',
        default               => 'fa-file',
    };
}

/**
 * Fetches a page of published resources matching the given filters.
 * $filters may contain: search, subject_id, grade, resource_type, category_id, access ('free'|'members'|'all')
 * Returns ['items' => array, 'total' => int, 'total_pages' => int]
 */
function get_published_resources(array $filters, int $page, int $perPage): array
{
    $where = ["r.is_published = 1", "r.status = 'active'"];
    $params = [];

    $search = trim((string)($filters['search'] ?? ''));
    if ($search !== '') {
        $where[] = '(r.title LIKE ? OR r.topic LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like);
    }

    $subjectId = (int)($filters['subject_id'] ?? 0);
    if ($subjectId > 0) {
        $where[] = 'r.subject_id = ?';
        $params[] = $subjectId;
    }

    $grade = trim((string)($filters['grade'] ?? ''));
    if ($grade !== '' && in_array($grade, GRADE_LEVELS, true)) {
        $where[] = 'r.grade_level = ?';
        $params[] = $grade;
    }

    $type = trim((string)($filters['resource_type'] ?? ''));
    if ($type !== '' && in_array($type, RESOURCE_TYPES, true)) {
        $where[] = 'r.resource_type = ?';
        $params[] = $type;
    }

    $categoryId = (int)($filters['category_id'] ?? 0);
    if ($categoryId > 0) {
        $where[] = 'r.category_id = ?';
        $params[] = $categoryId;
    }

    $access = trim((string)($filters['access'] ?? ''));
    if ($access === 'free') {
        $where[] = 'r.is_free = 1';
    } elseif ($access === 'members') {
        $where[] = 'r.is_free = 0';
    }

    $whereSql = implode(' AND ', $where);
    $db = getDB();

    $countStmt = $db->prepare("SELECT COUNT(*) FROM resources r WHERE {$whereSql}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $totalPages = $perPage > 0 ? (int)ceil($total / $perPage) : 1;
    $page = max(1, min($page, max(1, $totalPages)));
    $offset = ($page - 1) * $perPage;

    $sql = "SELECT r.*, c.name AS category_name, c.slug AS category_slug, s.name AS subject_name, s.slug AS subject_slug
            FROM resources r
            LEFT JOIN categories c ON c.id = r.category_id
            INNER JOIN subjects s ON s.id = r.subject_id
            WHERE {$whereSql}
            ORDER BY r.created_at DESC
            LIMIT {$perPage} OFFSET {$offset}";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll();

    return ['items' => $items, 'total' => $total, 'total_pages' => max(1, $totalPages), 'page' => $page];
}

function get_resource_by_slug(string $slug): ?array
{
    $stmt = getDB()->prepare(
        "SELECT r.*, c.name AS category_name, c.slug AS category_slug, s.name AS subject_name, s.slug AS subject_slug
         FROM resources r
         LEFT JOIN categories c ON c.id = r.category_id
         INNER JOIN subjects s ON s.id = r.subject_id
         WHERE r.slug = ? AND r.is_published = 1 AND r.status = 'active'
         LIMIT 1"
    );
    $stmt->execute([$slug]);

    return $stmt->fetch() ?: null;
}

/**
 * Looks up an archived resource by slug regardless of is_published, so
 * resource.php can tell "this URL used to be a real resource, now
 * archived" apart from "this URL never existed" — used to redirect old
 * URLs somewhere useful instead of a blind 404 (TeachLuma 2.0 Phase 1).
 */
function get_archived_resource_by_slug(string $slug): ?array
{
    $stmt = getDB()->prepare(
        "SELECT r.*, c.slug AS category_slug, s.slug AS subject_slug
         FROM resources r
         LEFT JOIN categories c ON c.id = r.category_id
         LEFT JOIN subjects s ON s.id = r.subject_id
         WHERE r.slug = ? AND r.status = 'archived'
         LIMIT 1"
    );
    $stmt->execute([$slug]);

    return $stmt->fetch() ?: null;
}

function get_published_resource_count(): int
{
    return (int)getDB()->query("SELECT COUNT(*) FROM resources WHERE is_published = 1 AND status = 'active'")->fetchColumn();
}

/**
 * Published resource counts by resource_type, e.g. ['Worksheet' => 42,
 * 'PowerPoint' => 18, ...] — types with zero published resources are
 * omitted. Used for marketing copy (pricing.php, index.php) so counts are
 * always read from real data rather than hardcoded.
 */
function get_resource_type_counts(): array
{
    $stmt = getDB()->query(
        "SELECT resource_type, COUNT(*) AS total FROM resources WHERE is_published = 1 AND status = 'active' GROUP BY resource_type"
    );

    $counts = [];
    foreach ($stmt->fetchAll() as $row) {
        $counts[$row['resource_type']] = (int)$row['total'];
    }

    return $counts;
}

/** Published resource counts by subject name, e.g. ['ESL' => 30, 'Math' => 10, 'Science' => 10]. */
function get_resource_counts_by_subject(): array
{
    $stmt = getDB()->query(
        "SELECT s.name, COUNT(*) AS total
         FROM resources r
         INNER JOIN subjects s ON s.id = r.subject_id
         WHERE r.is_published = 1 AND r.status = 'active'
         GROUP BY s.name"
    );

    $counts = [];
    foreach ($stmt->fetchAll() as $row) {
        $counts[$row['name']] = (int)$row['total'];
    }

    return $counts;
}

/**
 * Related resources for the "Related Resources" section on a resource
 * page — always real, published resources from the database, never
 * invented. Ranked by relevance: same category (+3), same grade (+2),
 * same resource type (+1), all constrained to the same subject as a
 * baseline (a Science worksheet should never surface as "related" to an
 * ESL lesson plan just because both happen to be Grade 3).
 */
function get_related_resources(array $resource, int $limit = 6): array
{
    $stmt = getDB()->prepare(
        "SELECT r.*, c.name AS category_name, c.slug AS category_slug, s.name AS subject_name, s.slug AS subject_slug,
                (
                    (CASE WHEN r.category_id IS NOT NULL AND r.category_id = ? THEN 3 ELSE 0 END) +
                    (CASE WHEN r.grade_level IS NOT NULL AND r.grade_level = ? THEN 2 ELSE 0 END) +
                    (CASE WHEN r.resource_type = ? THEN 1 ELSE 0 END)
                ) AS relevance
         FROM resources r
         LEFT JOIN categories c ON c.id = r.category_id
         INNER JOIN subjects s ON s.id = r.subject_id
         WHERE r.is_published = 1 AND r.status = 'active' AND r.id != ? AND r.subject_id = ?
         ORDER BY relevance DESC, r.created_at DESC
         LIMIT " . max(1, $limit)
    );
    $stmt->execute([
        $resource['category_id'] ?? 0,
        $resource['grade_level'] ?? '',
        $resource['resource_type'],
        $resource['id'],
        $resource['subject_id'],
    ]);

    return $stmt->fetchAll();
}

function get_featured_resources(int $limit = 6): array
{
    $stmt = getDB()->prepare(
        "SELECT r.*, c.name AS category_name, s.name AS subject_name, s.slug AS subject_slug
         FROM resources r
         LEFT JOIN categories c ON c.id = r.category_id
         INNER JOIN subjects s ON s.id = r.subject_id
         WHERE r.is_published = 1 AND r.status = 'active'
         ORDER BY r.created_at DESC
         LIMIT " . max(1, $limit)
    );
    $stmt->execute();

    return $stmt->fetchAll();
}

/** Published resources ordered by download_count — used for the 404 page's "Popular Resources" recovery links. */
function get_popular_resources(int $limit = 6): array
{
    $stmt = getDB()->prepare(
        "SELECT r.*, s.name AS subject_name
         FROM resources r
         INNER JOIN subjects s ON s.id = r.subject_id
         WHERE r.is_published = 1 AND r.status = 'active'
         ORDER BY r.download_count DESC, r.created_at DESC
         LIMIT " . max(1, $limit)
    );
    $stmt->execute();

    return $stmt->fetchAll();
}

function get_free_resources(int $limit = 6): array
{
    $stmt = getDB()->prepare(
        "SELECT r.*, c.name AS category_name, s.name AS subject_name, s.slug AS subject_slug
         FROM resources r
         LEFT JOIN categories c ON c.id = r.category_id
         INNER JOIN subjects s ON s.id = r.subject_id
         WHERE r.is_published = 1 AND r.status = 'active' AND r.is_free = 1
         ORDER BY r.created_at DESC
         LIMIT " . max(1, $limit)
    );
    $stmt->execute();

    return $stmt->fetchAll();
}

// -----------------------------------------------------------------------
// ADMIN RESOURCE CRUD
// -----------------------------------------------------------------------

function get_resource_by_id(int $id): ?array
{
    $stmt = getDB()->prepare('SELECT * FROM resources WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);

    return $stmt->fetch() ?: null;
}

/**
 * Admin listing — unlike get_published_resources(), this includes drafts.
 * $filters may contain: search, subject_id, resource_type, category_id, status ('published'|'draft'|'').
 */
function get_all_resources_paginated(array $filters, int $page, int $perPage): array
{
    $where = [];
    $params = [];

    $search = trim((string)($filters['search'] ?? ''));
    if ($search !== '') {
        $where[] = '(r.title LIKE ? OR r.topic LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like);
    }

    $subjectId = (int)($filters['subject_id'] ?? 0);
    if ($subjectId > 0) {
        $where[] = 'r.subject_id = ?';
        $params[] = $subjectId;
    }

    $type = trim((string)($filters['resource_type'] ?? ''));
    if ($type !== '' && in_array($type, RESOURCE_TYPES, true)) {
        $where[] = 'r.resource_type = ?';
        $params[] = $type;
    }

    $categoryId = (int)($filters['category_id'] ?? 0);
    if ($categoryId > 0) {
        $where[] = 'r.category_id = ?';
        $params[] = $categoryId;
    }

    $status = trim((string)($filters['status'] ?? ''));
    if ($status === 'published') {
        $where[] = 'r.is_published = 1';
    } elseif ($status === 'draft') {
        $where[] = 'r.is_published = 0';
    }

    // Distinct from the is_published draft/published toggle above: archived
    // resources are kept out of the admin list by default only when the
    // caller explicitly asks (e.g. the guide-editor's resource picker
    // shouldn't offer archived resources); the main admin resources list
    // passes no archive_status so admins can still see/manage everything.
    $archiveStatus = trim((string)($filters['archive_status'] ?? ''));
    if ($archiveStatus === 'active') {
        $where[] = "r.status = 'active'";
    } elseif ($archiveStatus === 'archived') {
        $where[] = "r.status = 'archived'";
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    $db = getDB();

    $countStmt = $db->prepare("SELECT COUNT(*) FROM resources r {$whereSql}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $totalPages = $perPage > 0 ? max(1, (int)ceil($total / $perPage)) : 1;
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;

    $sql = "SELECT r.*, c.name AS category_name, s.name AS subject_name
            FROM resources r
            LEFT JOIN categories c ON c.id = r.category_id
            INNER JOIN subjects s ON s.id = r.subject_id
            {$whereSql}
            ORDER BY r.created_at DESC
            LIMIT {$perPage} OFFSET {$offset}";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return ['items' => $stmt->fetchAll(), 'total' => $total, 'total_pages' => $totalPages, 'page' => $page];
}

function resource_slug_exists(string $slug, ?int $excludeId = null): bool
{
    if ($excludeId !== null) {
        $stmt = getDB()->prepare('SELECT id FROM resources WHERE slug = ? AND id != ? LIMIT 1');
        $stmt->execute([$slug, $excludeId]);
    } else {
        $stmt = getDB()->prepare('SELECT id FROM resources WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
    }

    return (bool)$stmt->fetch();
}

function generate_unique_resource_slug(string $title, ?int $excludeId = null): string
{
    $base = slugify($title);
    $base = $base !== '' ? $base : 'resource';
    $slug = $base;
    $i = 2;

    while (resource_slug_exists($slug, $excludeId)) {
        $slug = $base . '-' . $i;
        $i++;
    }

    return $slug;
}

/** Strips path info and unsafe characters from an uploaded file's original name — display only, never used to build a filesystem path. */
function safe_display_filename(string $originalName): string
{
    $name = basename($originalName);
    $name = preg_replace('/[^\w\-. ]/', '', $name) ?? $name;

    return mb_substr($name, 0, 255);
}

function validate_resource_input(array $input): array
{
    $errors = [];

    $title = clean_input($input['title'] ?? '');
    if ($title === '' || mb_strlen($title) > 200) {
        $errors['title'] = 'Please enter a title.';
    }

    if (!in_array($input['resource_type'] ?? '', RESOURCE_TYPES, true)) {
        $errors['resource_type'] = 'Please choose a resource type.';
    }

    // Grade and category are validated against the chosen subject (not just
    // "any valid value site-wide") so a Math resource can't end up tagged
    // with an ESL-only category or a grade outside that subject's range —
    // checked here server-side, not just filtered client-side in the form.
    $subject = get_subject_by_id((int)($input['subject_id'] ?? 0));
    if (!$subject) {
        $errors['subject_id'] = 'Please choose a subject.';
    }

    $grade = $input['grade_level'] ?? '';
    if ($grade !== '') {
        $allowedGrades = $subject ? get_subject_grade_levels($subject) : GRADE_LEVELS;
        if (!in_array($grade, $allowedGrades, true)) {
            $errors['grade_level'] = 'Please choose a grade level valid for this subject.';
        }
    }

    $categoryId = trim((string)($input['category_id'] ?? ''));
    if ($categoryId !== '') {
        $category = get_category_by_id((int)$categoryId);
        if (!$category) {
            $errors['category_id'] = 'Please choose a valid category.';
        } elseif ($subject && (int)$category['subject_id'] !== (int)$subject['id']) {
            $errors['category_id'] = 'That category does not belong to the selected subject.';
        }
    }

    if (!empty($input['topic']) && mb_strlen($input['topic']) > 150) {
        $errors['topic'] = 'Topic is too long.';
    }

    if (!empty($input['seo_title']) && mb_strlen($input['seo_title']) > 255) {
        $errors['seo_title'] = 'SEO title is too long (255 characters max).';
    }

    if (!empty($input['meta_description']) && mb_strlen($input['meta_description']) > 300) {
        $errors['meta_description'] = 'Meta description is too long (300 characters max).';
    }

    if (!empty($input['recommended_level']) && mb_strlen($input['recommended_level']) > 100) {
        $errors['recommended_level'] = 'Recommended level is too long (100 characters max).';
    }

    if (!empty($input['suggested_duration']) && mb_strlen($input['suggested_duration']) > 50) {
        $errors['suggested_duration'] = 'Suggested duration is too long (50 characters max).';
    }

    if (!empty($input['skills_practiced']) && mb_strlen($input['skills_practiced']) > 255) {
        $errors['skills_practiced'] = 'Skills practiced is too long (255 characters max).';
    }

    // Quality-checklist gate (Phase 2 Step 15/16): a resource can only be
    // published once an admin has explicitly ticked the confirmation —
    // never automatically. $alreadyConfirmed lets update_resource() skip
    // re-asking when the resource is already published and staying that way.
    $alreadyConfirmed = !empty($input['_qc_already_confirmed']);
    if (!empty($input['is_published']) && !$alreadyConfirmed && empty($input['qc_confirmed'])) {
        $errors['qc_confirmed'] = 'Please confirm the quality checklist before publishing.';
    }

    return $errors;
}

/**
 * The eleven optional teaching-detail fields shared by create_resource()
 * and update_resource() — cleaned and normalized to null-when-empty so
 * resource.php can render each section only when it has real content.
 */
function extract_teaching_detail_fields(array $input): array
{
    $fields = [];
    foreach (['learning_objectives', 'recommended_level', 'suggested_duration', 'skills_practiced',
              'how_to_use', 'activity_ideas', 'teacher_tips', 'differentiation_notes', 'assessment_notes',
              'overview', 'whats_included'] as $key) {
        $value = clean_input($input[$key] ?? '');
        $fields[$key] = $value !== '' ? $value : null;
    }

    return $fields;
}

/** Returns ['success' => bool, 'errors' => array<string,string>, 'id' => ?int] */
function create_resource(array $input, array $files): array
{
    $errors = validate_resource_input($input);

    $uploadedResourceFile = null;
    $uploadedThumbnail = null;
    $uploadedPreview = null;

    if (empty($errors)) {
        $upload = handle_upload($files['resource_file'] ?? [], UPLOAD_PROTECTED_PATH, ALLOWED_RESOURCE_MIME_TYPES, MAX_UPLOAD_SIZE_BYTES);

        if (!$upload['success']) {
            $errors['resource_file'] = $upload['error'];
        } elseif ($upload['filename'] === null) {
            $errors['resource_file'] = 'Please choose a file to upload.';
        } else {
            $uploadedResourceFile = $upload;
        }
    }

    if (empty($errors) && !empty($files['thumbnail']['name'])) {
        $upload = handle_upload($files['thumbnail'], UPLOAD_THUMBNAIL_PATH, ALLOWED_IMAGE_MIME_TYPES, MAX_IMAGE_SIZE_BYTES);

        if (!$upload['success']) {
            $errors['thumbnail'] = $upload['error'];
        } else {
            $uploadedThumbnail = $upload;
        }
    }

    if (empty($errors) && !empty($files['preview_image']['name'])) {
        $upload = handle_upload($files['preview_image'], UPLOAD_PREVIEW_PATH, ALLOWED_IMAGE_MIME_TYPES, MAX_IMAGE_SIZE_BYTES);

        if (!$upload['success']) {
            $errors['preview_image'] = $upload['error'];
        } else {
            $uploadedPreview = $upload;
        }
    }

    if (!empty($errors)) {
        // Clean up anything that uploaded successfully before a later step failed.
        if ($uploadedResourceFile) {
            @unlink(UPLOAD_PROTECTED_PATH . '/' . $uploadedResourceFile['filename']);
        }
        if ($uploadedThumbnail) {
            @unlink(UPLOAD_THUMBNAIL_PATH . '/' . $uploadedThumbnail['filename']);
        }
        if ($uploadedPreview) {
            @unlink(UPLOAD_PREVIEW_PATH . '/' . $uploadedPreview['filename']);
        }

        return ['success' => false, 'errors' => $errors, 'id' => null];
    }

    $title = clean_input($input['title']);
    $description = clean_input($input['description'] ?? '');
    $topic = clean_input($input['topic'] ?? '');
    $seoTitle = clean_input($input['seo_title'] ?? '');
    $metaDescription = clean_input($input['meta_description'] ?? '');
    $teaching = extract_teaching_detail_fields($input);

    $isPublished = !empty($input['is_published']) ? 1 : 0;

    $stmt = getDB()->prepare(
        'INSERT INTO resources (title, slug, description, seo_title, meta_description,
                                 learning_objectives, recommended_level, suggested_duration, skills_practiced,
                                 how_to_use, activity_ideas, teacher_tips, differentiation_notes, assessment_notes,
                                 overview, whats_included, qc_confirmed_at,
                                 resource_type, subject_id, category_id, grade_level, topic,
                                 thumbnail, preview_image, file_path, file_name, file_size, file_type, is_free, is_published)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $title,
        generate_unique_resource_slug($title),
        $description !== '' ? $description : null,
        $seoTitle !== '' ? $seoTitle : null,
        $metaDescription !== '' ? $metaDescription : null,
        $teaching['learning_objectives'],
        $teaching['recommended_level'],
        $teaching['suggested_duration'],
        $teaching['skills_practiced'],
        $teaching['how_to_use'],
        $teaching['activity_ideas'],
        $teaching['teacher_tips'],
        $teaching['differentiation_notes'],
        $teaching['assessment_notes'],
        $teaching['overview'],
        $teaching['whats_included'],
        $isPublished ? date('Y-m-d H:i:s') : null,
        $input['resource_type'],
        (int)$input['subject_id'],
        !empty($input['category_id']) ? (int)$input['category_id'] : null,
        $input['grade_level'] !== '' ? $input['grade_level'] : null,
        $topic !== '' ? $topic : null,
        $uploadedThumbnail['filename'] ?? null,
        $uploadedPreview['filename'] ?? null,
        $uploadedResourceFile['filename'],
        safe_display_filename($files['resource_file']['name']),
        (int)$files['resource_file']['size'],
        strtolower(pathinfo($files['resource_file']['name'], PATHINFO_EXTENSION)),
        !empty($input['is_free']) ? 1 : 0,
        $isPublished,
    ]);

    $newId = (int)getDB()->lastInsertId();

    add_uploaded_additional_files($newId, $input, $files);
    set_resource_related_resources($newId, $input['related_resource_ids'] ?? []);
    set_resource_related_guides($newId, $input['related_guide_ids'] ?? []);

    return ['success' => true, 'errors' => [], 'id' => $newId];
}

/** Returns ['success' => bool, 'errors' => array<string,string>] */
function update_resource(int $id, array $input, array $files): array
{
    $existing = get_resource_by_id($id);

    if (!$existing) {
        return ['success' => false, 'errors' => ['general' => 'Resource not found.']];
    }

    // Staying published with a checklist already confirmed doesn't need the
    // checkbox re-ticked on every minor edit; going from draft to published
    // (or re-publishing after a previous unpublish, which clears the
    // confirmation below) always does.
    $publishStateUnchanged = (int)$existing['is_published'] === (!empty($input['is_published']) ? 1 : 0);
    $input['_qc_already_confirmed'] = $publishStateUnchanged && !empty($existing['qc_confirmed_at']);

    $errors = validate_resource_input($input);

    $newResourceFile = null;
    $newThumbnail = null;
    $newPreview = null;

    if (empty($errors) && !empty($files['resource_file']['name'])) {
        $upload = handle_upload($files['resource_file'], UPLOAD_PROTECTED_PATH, ALLOWED_RESOURCE_MIME_TYPES, MAX_UPLOAD_SIZE_BYTES);

        if (!$upload['success']) {
            $errors['resource_file'] = $upload['error'];
        } else {
            $newResourceFile = $upload;
        }
    }

    if (empty($errors) && !empty($files['thumbnail']['name'])) {
        $upload = handle_upload($files['thumbnail'], UPLOAD_THUMBNAIL_PATH, ALLOWED_IMAGE_MIME_TYPES, MAX_IMAGE_SIZE_BYTES);

        if (!$upload['success']) {
            $errors['thumbnail'] = $upload['error'];
        } else {
            $newThumbnail = $upload;
        }
    }

    if (empty($errors) && !empty($files['preview_image']['name'])) {
        $upload = handle_upload($files['preview_image'], UPLOAD_PREVIEW_PATH, ALLOWED_IMAGE_MIME_TYPES, MAX_IMAGE_SIZE_BYTES);

        if (!$upload['success']) {
            $errors['preview_image'] = $upload['error'];
        } else {
            $newPreview = $upload;
        }
    }

    if (!empty($errors)) {
        if ($newResourceFile) {
            @unlink(UPLOAD_PROTECTED_PATH . '/' . $newResourceFile['filename']);
        }
        if ($newThumbnail) {
            @unlink(UPLOAD_THUMBNAIL_PATH . '/' . $newThumbnail['filename']);
        }
        if ($newPreview) {
            @unlink(UPLOAD_PREVIEW_PATH . '/' . $newPreview['filename']);
        }

        return ['success' => false, 'errors' => $errors];
    }

    $title = clean_input($input['title']);
    $description = clean_input($input['description'] ?? '');
    $topic = clean_input($input['topic'] ?? '');
    $seoTitle = clean_input($input['seo_title'] ?? '');
    $metaDescription = clean_input($input['meta_description'] ?? '');
    $teaching = extract_teaching_detail_fields($input);

    $filePath = $existing['file_path'];
    $fileName = $existing['file_name'];
    $fileSize = $existing['file_size'];
    $fileType = $existing['file_type'];

    if ($newResourceFile) {
        if (!empty($existing['file_path'])) {
            @unlink(UPLOAD_PROTECTED_PATH . '/' . $existing['file_path']);
        }
        $filePath = $newResourceFile['filename'];
        $fileName = safe_display_filename($files['resource_file']['name']);
        $fileSize = (int)$files['resource_file']['size'];
        $fileType = strtolower(pathinfo($files['resource_file']['name'], PATHINFO_EXTENSION));
    }

    $thumbnail = $existing['thumbnail'];
    if ($newThumbnail) {
        if (!empty($existing['thumbnail'])) {
            @unlink(UPLOAD_THUMBNAIL_PATH . '/' . $existing['thumbnail']);
        }
        $thumbnail = $newThumbnail['filename'];
    }

    $preview = $existing['preview_image'];
    if ($newPreview) {
        if (!empty($existing['preview_image'])) {
            @unlink(UPLOAD_PREVIEW_PATH . '/' . $existing['preview_image']);
        }
        $preview = $newPreview['filename'];
    }

    $newIsPublished = !empty($input['is_published']) ? 1 : 0;
    if (!$publishStateUnchanged) {
        $qcConfirmedAt = $newIsPublished ? date('Y-m-d H:i:s') : null;
    } else {
        $qcConfirmedAt = $existing['qc_confirmed_at'];
    }

    getDB()->prepare(
        'UPDATE resources SET title = ?, description = ?, seo_title = ?, meta_description = ?,
                               learning_objectives = ?, recommended_level = ?, suggested_duration = ?, skills_practiced = ?,
                               how_to_use = ?, activity_ideas = ?, teacher_tips = ?, differentiation_notes = ?, assessment_notes = ?,
                               overview = ?, whats_included = ?, qc_confirmed_at = ?,
                               resource_type = ?, subject_id = ?, category_id = ?, grade_level = ?,
                               topic = ?, thumbnail = ?, preview_image = ?, file_path = ?, file_name = ?,
                               file_size = ?, file_type = ?, is_free = ?, is_published = ? WHERE id = ?'
    )->execute([
        $title,
        // slug is deliberately NOT regenerated here — once a resource is
        // published its URL may already be indexed by search engines and
        // shared/bookmarked by teachers, so editing the title must never
        // silently change the URL. The slug only exists to be set once,
        // on creation (see create_resource()).
        $description !== '' ? $description : null,
        $seoTitle !== '' ? $seoTitle : null,
        $metaDescription !== '' ? $metaDescription : null,
        $teaching['learning_objectives'],
        $teaching['recommended_level'],
        $teaching['suggested_duration'],
        $teaching['skills_practiced'],
        $teaching['how_to_use'],
        $teaching['activity_ideas'],
        $teaching['teacher_tips'],
        $teaching['differentiation_notes'],
        $teaching['assessment_notes'],
        $teaching['overview'],
        $teaching['whats_included'],
        $qcConfirmedAt,
        $input['resource_type'],
        (int)$input['subject_id'],
        !empty($input['category_id']) ? (int)$input['category_id'] : null,
        $input['grade_level'] !== '' ? $input['grade_level'] : null,
        $topic !== '' ? $topic : null,
        $thumbnail,
        $preview,
        $filePath,
        $fileName,
        $fileSize,
        $fileType,
        !empty($input['is_free']) ? 1 : 0,
        $newIsPublished,
        $id,
    ]);

    add_uploaded_additional_files($id, $input, $files);
    set_resource_related_resources($id, $input['related_resource_ids'] ?? []);
    set_resource_related_guides($id, $input['related_guide_ids'] ?? []);

    return ['success' => true, 'errors' => []];
}

/** Optional additional files on a resource (e.g. a standalone answer key), in sort_order. */
function get_resource_files(int $resourceId): array
{
    $stmt = getDB()->prepare('SELECT * FROM resource_files WHERE resource_id = ? ORDER BY sort_order, id');
    $stmt->execute([$resourceId]);

    return $stmt->fetchAll();
}

function get_resource_file_by_id(int $id): ?array
{
    $stmt = getDB()->prepare('SELECT * FROM resource_files WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);

    return $stmt->fetch() ?: null;
}

/** Removes one additional file's disk file and DB row. */
function delete_resource_file(int $id): void
{
    $file = get_resource_file_by_id($id);

    if (!$file) {
        return;
    }

    @unlink(UPLOAD_PROTECTED_PATH . '/' . $file['file_path']);
    getDB()->prepare('DELETE FROM resource_files WHERE id = ?')->execute([$id]);
}

/**
 * Handles up to RESOURCE_ADDITIONAL_FILE_SLOTS optional "additional file"
 * upload slots from the admin resource form (additional_file_1..N, each
 * with an optional additional_file_label_N) — a fixed number of slots
 * (config.php) rather than an open-ended dynamic multi-upload widget, so a
 * single save can only ever create a bounded number of new files. Silently
 * skips empty slots; upload errors on an optional slot are logged, not
 * fatal to the save. $input is the label text fields (from $_POST); $files
 * is the upload data (from $_FILES).
 */
function add_uploaded_additional_files(int $resourceId, array $input, array $files): void
{
    $db = getDB();
    $orderStmt = $db->prepare('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM resource_files WHERE resource_id = ?');
    $orderStmt->execute([$resourceId]);
    $nextOrder = (int)$orderStmt->fetchColumn();

    for ($slot = 1; $slot <= RESOURCE_ADDITIONAL_FILE_SLOTS; $slot++) {
        $fileKey = "additional_file_{$slot}";
        if (empty($files[$fileKey]['name'])) {
            continue;
        }

        $upload = handle_upload($files[$fileKey], UPLOAD_PROTECTED_PATH, ALLOWED_RESOURCE_MIME_TYPES, MAX_UPLOAD_SIZE_BYTES);

        if (!$upload['success'] || $upload['filename'] === null) {
            error_log("Additional file upload failed for resource #{$resourceId}, slot {$slot}: " . ($upload['error'] ?? 'unknown error'));
            continue;
        }

        $label = clean_input($input["additional_file_label_{$slot}"] ?? '');

        $db->prepare(
            'INSERT INTO resource_files (resource_id, file_path, file_name, file_size, file_type, label, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $resourceId,
            $upload['filename'],
            safe_display_filename($files[$fileKey]['name']),
            (int)$files[$fileKey]['size'],
            strtolower(pathinfo($files[$fileKey]['name'], PATHINFO_EXTENSION)),
            $label !== '' ? $label : null,
            $nextOrder++,
        ]);
    }
}

/** Manually admin-picked related resources, published+active only, in sort_order. Empty when none picked — resource.php falls back to the automatic relevance query in that case. */
function get_manual_related_resources(int $resourceId): array
{
    $stmt = getDB()->prepare(
        "SELECT r.*, c.name AS category_name, s.name AS subject_name, s.slug AS subject_slug
         FROM resource_related_resources rr
         INNER JOIN resources r ON r.id = rr.related_resource_id
         LEFT JOIN categories c ON c.id = r.category_id
         INNER JOIN subjects s ON s.id = r.subject_id
         WHERE rr.resource_id = ? AND r.is_published = 1 AND r.status = 'active'
         ORDER BY rr.sort_order, r.title"
    );
    $stmt->execute([$resourceId]);

    return $stmt->fetchAll();
}

/** Every related_resource_id currently picked for this resource, regardless of the target's published state — used to pre-check the admin resource form's related-resources picker. */
function get_related_resource_ids(int $resourceId): array
{
    $stmt = getDB()->prepare('SELECT related_resource_id FROM resource_related_resources WHERE resource_id = ? ORDER BY sort_order');
    $stmt->execute([$resourceId]);

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/** Replaces a resource's manual related-resources list wholesale — called from the admin resource editor with the full new set each save. Self-references are silently dropped. */
function set_resource_related_resources(int $resourceId, array $relatedIds): void
{
    $db = getDB();
    $db->prepare('DELETE FROM resource_related_resources WHERE resource_id = ?')->execute([$resourceId]);

    $stmt = $db->prepare('INSERT INTO resource_related_resources (resource_id, related_resource_id, sort_order) VALUES (?, ?, ?)');
    $order = 0;
    foreach ($relatedIds as $relatedId) {
        $relatedId = (int)$relatedId;
        if ($relatedId > 0 && $relatedId !== $resourceId) {
            $stmt->execute([$resourceId, $relatedId, $order]);
            $order++;
        }
    }
}

/** Deletes the resource's uploaded files from disk, then the DB row. Downloads/favorites/resource_files rows cascade via FK. */
function delete_resource(int $id): void
{
    $resource = get_resource_by_id($id);

    if (!$resource) {
        return;
    }

    if (!empty($resource['file_path'])) {
        @unlink(UPLOAD_PROTECTED_PATH . '/' . $resource['file_path']);
    }
    if (!empty($resource['thumbnail'])) {
        @unlink(UPLOAD_THUMBNAIL_PATH . '/' . $resource['thumbnail']);
    }
    if (!empty($resource['preview_image'])) {
        @unlink(UPLOAD_PREVIEW_PATH . '/' . $resource['preview_image']);
    }
    foreach (get_resource_files($id) as $extraFile) {
        @unlink(UPLOAD_PROTECTED_PATH . '/' . $extraFile['file_path']);
    }

    getDB()->prepare('DELETE FROM resources WHERE id = ?')->execute([$id]);
}

/**
 * Archives a resource: hides it from every public listing/search/featured
 * section while keeping its row, reviews, and download history intact —
 * the safe alternative to delete_resource() used by the TeachLuma 2.0
 * library rebuild. Optionally points old visitors at a specific
 * replacement resource; with no target, resource.php falls back to the
 * resource's subject/category listing page instead of a blind 404.
 */
function archive_resource(int $id, ?int $redirectResourceId = null): void
{
    getDB()->prepare(
        "UPDATE resources SET status = 'archived', redirect_resource_id = ?, archived_at = NOW() WHERE id = ?"
    )->execute([$redirectResourceId ?: null, $id]);
}

/** Restores a previously archived resource back to normal public visibility. */
function unarchive_resource(int $id): void
{
    getDB()->prepare(
        "UPDATE resources SET status = 'active', redirect_resource_id = NULL, archived_at = NULL WHERE id = ?"
    )->execute([$id]);
}
