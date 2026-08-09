<?php
/**
 * Resource/category queries: public read-side queries, category CRUD
 * (used by /admin/categories.php), and resource CRUD (used by
 * /admin/resources.php, resource-add.php, resource-edit.php).
 */

function get_categories(): array
{
    static $categories = null;

    if ($categories === null) {
        $stmt = getDB()->query('SELECT id, name, slug, group_name FROM categories ORDER BY group_name, sort_order, name');
        $categories = $stmt->fetchAll();
    }

    return $categories;
}

/** Categories grouped by their group_name, e.g. ['English Skills' => [...], 'Teaching Resources' => [...]]. */
function get_categories_grouped(): array
{
    $grouped = [];

    foreach (get_categories() as $category) {
        $grouped[$category['group_name']][] = $category;
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
    $groupName = clean_input($input['group_name']);
    $sortOrder = (int)($input['sort_order'] ?? 0);

    getDB()->prepare('INSERT INTO categories (name, slug, group_name, sort_order) VALUES (?, ?, ?, ?)')
        ->execute([$name, $slug, $groupName, $sortOrder]);

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
    $groupName = clean_input($input['group_name']);
    $sortOrder = (int)($input['sort_order'] ?? 0);

    getDB()->prepare('UPDATE categories SET name = ?, slug = ?, group_name = ?, sort_order = ? WHERE id = ?')
        ->execute([$name, $slug, $groupName, $sortOrder, $id]);

    return ['success' => true, 'errors' => []];
}

function validate_category_input(array $input, ?int $excludeId = null): array
{
    $errors = [];

    $name = clean_input($input['name'] ?? '');
    $groupName = clean_input($input['group_name'] ?? '');

    if ($name === '' || mb_strlen($name) > 100) {
        $errors['name'] = 'Please enter a category name.';
    } elseif (category_slug_exists(slugify($name), $excludeId)) {
        $errors['name'] = 'A category with this name already exists.';
    }

    if ($groupName === '' || mb_strlen($groupName) > 50) {
        $errors['group_name'] = 'Please enter a group name.';
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
 * $filters may contain: search, grade, resource_type, category_id, access ('free'|'members'|'all')
 * Returns ['items' => array, 'total' => int, 'total_pages' => int]
 */
function get_published_resources(array $filters, int $page, int $perPage): array
{
    $where = ['r.is_published = 1'];
    $params = [];

    $search = trim((string)($filters['search'] ?? ''));
    if ($search !== '') {
        $where[] = '(r.title LIKE ? OR r.topic LIKE ? OR r.subject LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like);
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

    $sql = "SELECT r.*, c.name AS category_name, c.slug AS category_slug
            FROM resources r
            LEFT JOIN categories c ON c.id = r.category_id
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
        'SELECT r.*, c.name AS category_name, c.slug AS category_slug
         FROM resources r
         LEFT JOIN categories c ON c.id = r.category_id
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

function get_featured_resources(int $limit = 6): array
{
    $stmt = getDB()->prepare(
        'SELECT r.*, c.name AS category_name
         FROM resources r
         LEFT JOIN categories c ON c.id = r.category_id
         WHERE r.is_published = 1
         ORDER BY r.created_at DESC
         LIMIT ' . max(1, $limit)
    );
    $stmt->execute();

    return $stmt->fetchAll();
}

function get_free_resources(int $limit = 6): array
{
    $stmt = getDB()->prepare(
        'SELECT r.*, c.name AS category_name
         FROM resources r
         LEFT JOIN categories c ON c.id = r.category_id
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
 * $filters may contain: search, resource_type, category_id, status ('published'|'draft'|'').
 */
function get_all_resources_paginated(array $filters, int $page, int $perPage): array
{
    $where = [];
    $params = [];

    $search = trim((string)($filters['search'] ?? ''));
    if ($search !== '') {
        $where[] = '(r.title LIKE ? OR r.topic LIKE ? OR r.subject LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like);
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

    $sql = "SELECT r.*, c.name AS category_name
            FROM resources r
            LEFT JOIN categories c ON c.id = r.category_id
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

    $grade = $input['grade_level'] ?? '';
    if ($grade !== '' && !in_array($grade, GRADE_LEVELS, true)) {
        $errors['grade_level'] = 'Please choose a valid grade level.';
    }

    $categoryId = trim((string)($input['category_id'] ?? ''));
    if ($categoryId !== '' && !get_category_by_id((int)$categoryId)) {
        $errors['category_id'] = 'Please choose a valid category.';
    }

    if (!empty($input['subject']) && mb_strlen($input['subject']) > 100) {
        $errors['subject'] = 'Subject is too long.';
    }

    if (!empty($input['topic']) && mb_strlen($input['topic']) > 150) {
        $errors['topic'] = 'Topic is too long.';
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
    $subject = clean_input($input['subject'] ?? '');
    $topic = clean_input($input['topic'] ?? '');

    $stmt = getDB()->prepare(
        'INSERT INTO resources (title, slug, description, resource_type, category_id, grade_level, subject, topic,
                                 thumbnail, preview_image, file_path, file_name, file_size, file_type, is_free, is_published)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $title,
        generate_unique_resource_slug($title),
        $description !== '' ? $description : null,
        $input['resource_type'],
        !empty($input['category_id']) ? (int)$input['category_id'] : null,
        $input['grade_level'] !== '' ? $input['grade_level'] : null,
        $subject !== '' ? $subject : null,
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
    $subject = clean_input($input['subject'] ?? '');
    $topic = clean_input($input['topic'] ?? '');

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
        'UPDATE resources SET title = ?, slug = ?, description = ?, resource_type = ?, category_id = ?, grade_level = ?,
                               subject = ?, topic = ?, thumbnail = ?, preview_image = ?, file_path = ?, file_name = ?,
                               file_size = ?, file_type = ?, is_free = ?, is_published = ? WHERE id = ?'
    )->execute([
        $title,
        generate_unique_resource_slug($title, $id),
        $description !== '' ? $description : null,
        $input['resource_type'],
        !empty($input['category_id']) ? (int)$input['category_id'] : null,
        $input['grade_level'] !== '' ? $input['grade_level'] : null,
        $subject !== '' ? $subject : null,
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
