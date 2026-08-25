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
    $where = ['r.is_published = 1'];
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
        'SELECT r.*, c.name AS category_name, c.slug AS category_slug, s.name AS subject_name, s.slug AS subject_slug
         FROM resources r
         LEFT JOIN categories c ON c.id = r.category_id
         INNER JOIN subjects s ON s.id = r.subject_id
         WHERE r.slug = ? AND r.is_published = 1
         LIMIT 1'
    );
    $stmt->execute([$slug]);

    return $stmt->fetch() ?: null;
}

function get_published_resource_count(): int
{
    return (int)getDB()->query('SELECT COUNT(*) FROM resources WHERE is_published = 1')->fetchColumn();
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
        'SELECT resource_type, COUNT(*) AS total FROM resources WHERE is_published = 1 GROUP BY resource_type'
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
        'SELECT s.name, COUNT(*) AS total
         FROM resources r
         INNER JOIN subjects s ON s.id = r.subject_id
         WHERE r.is_published = 1
         GROUP BY s.name'
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
        'SELECT r.*, c.name AS category_name, c.slug AS category_slug, s.name AS subject_name, s.slug AS subject_slug,
                (
                    (CASE WHEN r.category_id IS NOT NULL AND r.category_id = ? THEN 3 ELSE 0 END) +
                    (CASE WHEN r.grade_level IS NOT NULL AND r.grade_level = ? THEN 2 ELSE 0 END) +
                    (CASE WHEN r.resource_type = ? THEN 1 ELSE 0 END)
                ) AS relevance
         FROM resources r
         LEFT JOIN categories c ON c.id = r.category_id
         INNER JOIN subjects s ON s.id = r.subject_id
         WHERE r.is_published = 1 AND r.id != ? AND r.subject_id = ?
         ORDER BY relevance DESC, r.created_at DESC
         LIMIT ' . max(1, $limit)
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
        'SELECT r.*, c.name AS category_name, s.name AS subject_name, s.slug AS subject_slug
         FROM resources r
         LEFT JOIN categories c ON c.id = r.category_id
         INNER JOIN subjects s ON s.id = r.subject_id
         WHERE r.is_published = 1
         ORDER BY r.created_at DESC
         LIMIT ' . max(1, $limit)
    );
    $stmt->execute();

    return $stmt->fetchAll();
}

/** Published resources ordered by download_count — used for the 404 page's "Popular Resources" recovery links. */
function get_popular_resources(int $limit = 6): array
{
    $stmt = getDB()->prepare(
        'SELECT r.*, s.name AS subject_name
         FROM resources r
         INNER JOIN subjects s ON s.id = r.subject_id
         WHERE r.is_published = 1
         ORDER BY r.download_count DESC, r.created_at DESC
         LIMIT ' . max(1, $limit)
    );
    $stmt->execute();

    return $stmt->fetchAll();
}

function get_free_resources(int $limit = 6): array
{
    $stmt = getDB()->prepare(
        'SELECT r.*, c.name AS category_name, s.name AS subject_name, s.slug AS subject_slug
         FROM resources r
         LEFT JOIN categories c ON c.id = r.category_id
         INNER JOIN subjects s ON s.id = r.subject_id
         WHERE r.is_published = 1 AND r.is_free = 1
         ORDER BY r.created_at DESC
         LIMIT ' . max(1, $limit)
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

    return $errors;
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

    $stmt = getDB()->prepare(
        'INSERT INTO resources (title, slug, description, seo_title, meta_description, resource_type, subject_id, category_id, grade_level, topic,
                                 thumbnail, preview_image, file_path, file_name, file_size, file_type, is_free, is_published)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $title,
        generate_unique_resource_slug($title),
        $description !== '' ? $description : null,
        $seoTitle !== '' ? $seoTitle : null,
        $metaDescription !== '' ? $metaDescription : null,
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
        !empty($input['is_published']) ? 1 : 0,
    ]);

    return ['success' => true, 'errors' => [], 'id' => (int)getDB()->lastInsertId()];
}

/** Returns ['success' => bool, 'errors' => array<string,string>] */
function update_resource(int $id, array $input, array $files): array
{
    $existing = get_resource_by_id($id);

    if (!$existing) {
        return ['success' => false, 'errors' => ['general' => 'Resource not found.']];
    }

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

    getDB()->prepare(
        'UPDATE resources SET title = ?, description = ?, seo_title = ?, meta_description = ?, resource_type = ?, subject_id = ?, category_id = ?, grade_level = ?,
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
        !empty($input['is_published']) ? 1 : 0,
        $id,
    ]);

    return ['success' => true, 'errors' => []];
}

/** Deletes the resource's uploaded files from disk, then the DB row. Downloads/favorites rows cascade via FK. */
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

    getDB()->prepare('DELETE FROM resources WHERE id = ?')->execute([$id]);
}
