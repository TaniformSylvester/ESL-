<?php
/**
 * Teacher Hub: guide CRUD (admin/guides.php), public read queries
 * (teacher-hub.php, teacher-hub-guide.php), and the guide<->resource
 * cross-linking used by both resource.php ("From the Teacher Hub") and
 * guide pages ("Recommended Resources"). Links are always hand-picked
 * real resource IDs stored in guide_related_resources — never an
 * automatic keyword match, so a guide never recommends something
 * irrelevant.
 */

const GUIDE_CATEGORIES = [
    'esl'       => 'ESL & English Teaching',
    'math'      => 'Mathematics Teaching',
    'science'   => 'Science Teaching',
    'classroom' => 'Classroom Practice',
];

/** The structured content sections a guide may have, in display order, with their public heading. */
const GUIDE_SECTIONS = [
    'intro'               => null, // shown directly under the title, no heading of its own
    'practical_advice'    => 'Practical Advice',
    'classroom_examples'  => 'Classroom Examples',
    'activities'          => 'Activities',
    'common_difficulties' => 'Common Difficulties',
    'differentiation'     => 'Differentiation',
    'assessment'          => 'Assessment',
];

function guide_slug_exists(string $slug, ?int $excludeId = null): bool
{
    if ($excludeId !== null) {
        $stmt = getDB()->prepare('SELECT id FROM guides WHERE slug = ? AND id != ? LIMIT 1');
        $stmt->execute([$slug, $excludeId]);
    } else {
        $stmt = getDB()->prepare('SELECT id FROM guides WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
    }

    return (bool)$stmt->fetch();
}

function generate_unique_guide_slug(string $title, ?int $excludeId = null): string
{
    $base = slugify($title);
    $base = $base !== '' ? $base : 'guide';
    $slug = $base;
    $i = 2;

    while (guide_slug_exists($slug, $excludeId)) {
        $slug = $base . '-' . $i;
        $i++;
    }

    return $slug;
}

function get_guide_by_slug(string $slug): ?array
{
    $stmt = getDB()->prepare(
        'SELECT g.*, s.name AS subject_name, s.slug AS subject_slug
         FROM guides g
         LEFT JOIN subjects s ON s.id = g.subject_id
         WHERE g.slug = ? AND g.is_published = 1
         LIMIT 1'
    );
    $stmt->execute([$slug]);

    return $stmt->fetch() ?: null;
}

function get_guide_by_id(int $id): ?array
{
    $stmt = getDB()->prepare('SELECT * FROM guides WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);

    return $stmt->fetch() ?: null;
}

/** Published guides in one category, for a Teacher Hub category listing. */
function get_guides_by_category(string $category, int $limit = 0): array
{
    $sql = 'SELECT * FROM guides WHERE category = ? AND is_published = 1 ORDER BY title';
    if ($limit > 0) {
        $sql .= ' LIMIT ' . $limit;
    }
    $stmt = getDB()->prepare($sql);
    $stmt->execute([$category]);

    return $stmt->fetchAll();
}

/** All published guides grouped by category, in GUIDE_CATEGORIES order, for the Teacher Hub index. */
function get_all_guides_grouped(): array
{
    $grouped = [];
    foreach (array_keys(GUIDE_CATEGORIES) as $category) {
        $guides = get_guides_by_category($category);
        if (!empty($guides)) {
            $grouped[$category] = $guides;
        }
    }

    return $grouped;
}

/** A handful of published guides for the homepage teaser — most recent first. */
function get_recent_guides(int $limit = 3): array
{
    $stmt = getDB()->prepare(
        'SELECT * FROM guides WHERE is_published = 1 ORDER BY created_at DESC LIMIT ' . max(1, $limit)
    );
    $stmt->execute();

    return $stmt->fetchAll();
}

function get_published_guide_count(): int
{
    return (int)getDB()->query('SELECT COUNT(*) FROM guides WHERE is_published = 1')->fetchColumn();
}

/** Admin listing — includes drafts. $filters may contain: search, category, status ('published'|'draft'|''). */
function get_all_guides_paginated(array $filters, int $page, int $perPage): array
{
    $where = [];
    $params = [];

    $search = trim((string)($filters['search'] ?? ''));
    if ($search !== '') {
        $where[] = 'title LIKE ?';
        $params[] = '%' . $search . '%';
    }

    $category = trim((string)($filters['category'] ?? ''));
    if (array_key_exists($category, GUIDE_CATEGORIES)) {
        $where[] = 'category = ?';
        $params[] = $category;
    }

    $status = trim((string)($filters['status'] ?? ''));
    if ($status === 'published') {
        $where[] = 'is_published = 1';
    } elseif ($status === 'draft') {
        $where[] = 'is_published = 0';
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    $db = getDB();

    $countStmt = $db->prepare("SELECT COUNT(*) FROM guides {$whereSql}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $totalPages = $perPage > 0 ? max(1, (int)ceil($total / $perPage)) : 1;
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;

    $stmt = $db->prepare("SELECT * FROM guides {$whereSql} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}");
    $stmt->execute($params);

    return ['items' => $stmt->fetchAll(), 'total' => $total, 'total_pages' => $totalPages, 'page' => $page];
}

function validate_guide_input(array $input, ?int $excludeId = null): array
{
    $errors = [];

    $title = clean_input($input['title'] ?? '');
    if ($title === '' || mb_strlen($title) > 200) {
        $errors['title'] = 'Please enter a title.';
    }

    if (!array_key_exists($input['category'] ?? '', GUIDE_CATEGORIES)) {
        $errors['category'] = 'Please choose a category.';
    }

    if (!empty($input['subject_id']) && !get_subject_by_id((int)$input['subject_id'])) {
        $errors['subject_id'] = 'Please choose a valid subject.';
    }

    if (!empty($input['summary']) && mb_strlen($input['summary']) > 300) {
        $errors['summary'] = 'Summary is too long (300 characters max).';
    }

    return $errors;
}

/** Returns ['success' => bool, 'errors' => array<string,string>, 'id' => ?int] */
function create_guide(array $input): array
{
    $errors = validate_guide_input($input);
    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors, 'id' => null];
    }

    $title = clean_input($input['title']);
    $fields = extract_guide_content_fields($input);

    $stmt = getDB()->prepare(
        'INSERT INTO guides (title, slug, category, subject_id, summary, intro, practical_advice, classroom_examples,
                              activities, common_difficulties, differentiation, assessment, seo_title, meta_description, is_published)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $title,
        generate_unique_guide_slug($title),
        $input['category'],
        !empty($input['subject_id']) ? (int)$input['subject_id'] : null,
        $fields['summary'],
        $fields['intro'],
        $fields['practical_advice'],
        $fields['classroom_examples'],
        $fields['activities'],
        $fields['common_difficulties'],
        $fields['differentiation'],
        $fields['assessment'],
        $fields['seo_title'],
        $fields['meta_description'],
        !empty($input['is_published']) ? 1 : 0,
    ]);

    return ['success' => true, 'errors' => [], 'id' => (int)getDB()->lastInsertId()];
}

/** Returns ['success' => bool, 'errors' => array<string,string>] */
function update_guide(int $id, array $input): array
{
    $errors = validate_guide_input($input, $id);
    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }

    $title = clean_input($input['title']);
    $fields = extract_guide_content_fields($input);

    getDB()->prepare(
        'UPDATE guides SET title = ?, category = ?, subject_id = ?, summary = ?, intro = ?, practical_advice = ?,
                            classroom_examples = ?, activities = ?, common_difficulties = ?, differentiation = ?,
                            assessment = ?, seo_title = ?, meta_description = ?, is_published = ? WHERE id = ?'
        // slug is never changed after creation, same rule as resources.
    )->execute([
        $title,
        $input['category'],
        !empty($input['subject_id']) ? (int)$input['subject_id'] : null,
        $fields['summary'],
        $fields['intro'],
        $fields['practical_advice'],
        $fields['classroom_examples'],
        $fields['activities'],
        $fields['common_difficulties'],
        $fields['differentiation'],
        $fields['assessment'],
        $fields['seo_title'],
        $fields['meta_description'],
        !empty($input['is_published']) ? 1 : 0,
        $id,
    ]);

    return ['success' => true, 'errors' => []];
}

function extract_guide_content_fields(array $input): array
{
    $fields = [];
    foreach (['summary', 'intro', 'practical_advice', 'classroom_examples', 'activities',
              'common_difficulties', 'differentiation', 'assessment', 'seo_title', 'meta_description'] as $key) {
        $value = clean_input($input[$key] ?? '');
        $fields[$key] = $value !== '' ? $value : null;
    }

    return $fields;
}

function delete_guide(int $id): void
{
    getDB()->prepare('DELETE FROM guides WHERE id = ?')->execute([$id]);
}

/** A guide's hand-picked related resources, in sort_order, joined with real resource data. */
function get_guide_related_resources(int $guideId): array
{
    $stmt = getDB()->prepare(
        'SELECT r.*, gr.sort_order
         FROM guide_related_resources gr
         INNER JOIN resources r ON r.id = gr.resource_id
         WHERE gr.guide_id = ? AND r.is_published = 1
         ORDER BY gr.sort_order, r.title'
    );
    $stmt->execute([$guideId]);

    return $stmt->fetchAll();
}

/** Replaces a guide's related-resources list wholesale — called from the admin guide editor with the full new set each save. */
function set_guide_related_resources(int $guideId, array $resourceIds): void
{
    $db = getDB();
    $db->prepare('DELETE FROM guide_related_resources WHERE guide_id = ?')->execute([$guideId]);

    $stmt = $db->prepare('INSERT INTO guide_related_resources (guide_id, resource_id, sort_order) VALUES (?, ?, ?)');
    $order = 0;
    foreach ($resourceIds as $resourceId) {
        $resourceId = (int)$resourceId;
        if ($resourceId > 0) {
            $stmt->execute([$guideId, $resourceId, $order]);
            $order++;
        }
    }
}

/** Published guides that recommend this resource — shown on resource.php as "From the Teacher Hub". */
function get_resource_related_guides(int $resourceId, int $limit = 3): array
{
    $stmt = getDB()->prepare(
        'SELECT g.*
         FROM guide_related_resources gr
         INNER JOIN guides g ON g.id = gr.guide_id
         WHERE gr.resource_id = ? AND g.is_published = 1
         ORDER BY g.title
         LIMIT ' . max(1, $limit)
    );
    $stmt->execute([$resourceId]);

    return $stmt->fetchAll();
}
