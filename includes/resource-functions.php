<?php
/**
 * Resource/category queries. Public read-side queries plus category CRUD
 * (used by /admin/categories.php). Resource CRUD is added in a later stage.
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
