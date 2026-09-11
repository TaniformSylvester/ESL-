<?php
/**
 * Educational videos: admin CRUD, public listing/detail data, and the
 * video<->resource / video<->video relationship queries. Only the YouTube
 * video ID is ever stored — the embed markup lives entirely in
 * includes/video-card.php / video.php / resource.php, never in the database.
 */

function video_slug_exists(string $slug, ?int $excludeId = null): bool
{
    if ($excludeId !== null) {
        $stmt = getDB()->prepare('SELECT id FROM videos WHERE slug = ? AND id != ? LIMIT 1');
        $stmt->execute([$slug, $excludeId]);
    } else {
        $stmt = getDB()->prepare('SELECT id FROM videos WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
    }

    return (bool)$stmt->fetch();
}

function generate_unique_video_slug(string $title, ?int $excludeId = null): string
{
    $base = slugify($title);
    $base = $base !== '' ? $base : 'video';
    $slug = $base;
    $i = 2;

    while (video_slug_exists($slug, $excludeId)) {
        $slug = $base . '-' . $i;
        $i++;
    }

    return $slug;
}

/** The YouTube thumbnail URL to use when no custom thumbnail has been uploaded. */
function video_youtube_thumbnail_url(string $youtubeVideoId): string
{
    return 'https://i.ytimg.com/vi/' . rawurlencode($youtubeVideoId) . '/hqdefault.jpg';
}

/** The thumbnail URL to actually display for a video row — custom upload first, YouTube's own image as the fallback. */
function video_display_thumbnail_url(array $video): string
{
    if (!empty($video['thumbnail'])) {
        return UPLOAD_THUMBNAIL_URL . '/' . rawurlencode($video['thumbnail']);
    }

    return video_youtube_thumbnail_url($video['youtube_video_id']);
}

/** Every video, newest first — for the admin list, published or not. */
function get_all_videos_for_admin(): array
{
    return getDB()->query(
        "SELECT v.*, s.name AS subject_name
         FROM videos v
         INNER JOIN subjects s ON s.id = v.subject_id
         ORDER BY v.created_at DESC"
    )->fetchAll();
}

/**
 * Fetches a page of published videos matching the given filters, for the
 * public video library. $filters may contain: search (title/topic),
 * subject_id, grade. Returns ['items' => array, 'total' => int, 'total_pages' => int, 'page' => int].
 */
function get_published_videos(array $filters, int $page, int $perPage): array
{
    $where = ['v.is_published = 1'];
    $params = [];

    $search = trim((string)($filters['search'] ?? ''));
    if ($search !== '') {
        $where[] = '(v.title LIKE ? OR v.topic LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like);
    }

    $subjectId = (int)($filters['subject_id'] ?? 0);
    if ($subjectId > 0) {
        $where[] = 'v.subject_id = ?';
        $params[] = $subjectId;
    }

    $grade = trim((string)($filters['grade'] ?? ''));
    if ($grade !== '' && in_array($grade, GRADE_LEVELS, true)) {
        $where[] = 'v.grade_level = ?';
        $params[] = $grade;
    }

    $whereSql = implode(' AND ', $where);
    $db = getDB();

    $countStmt = $db->prepare("SELECT COUNT(*) FROM videos v WHERE {$whereSql}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $totalPages = $perPage > 0 ? max(1, (int)ceil($total / $perPage)) : 1;
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;

    $sql = "SELECT v.*, s.name AS subject_name, s.slug AS subject_slug
            FROM videos v
            INNER JOIN subjects s ON s.id = v.subject_id
            WHERE {$whereSql}
            ORDER BY v.created_at DESC
            LIMIT {$perPage} OFFSET {$offset}";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return ['items' => $stmt->fetchAll(), 'total' => $total, 'total_pages' => $totalPages, 'page' => $page];
}

/** Every published video, unpaginated — used by sitemap.php. */
function get_all_published_videos(): array
{
    $stmt = getDB()->query('SELECT slug, updated_at FROM videos WHERE is_published = 1 ORDER BY updated_at DESC');

    return $stmt->fetchAll();
}

/** The $limit most recent published videos — for the homepage "Learn with TeachLuma" section. */
function get_featured_videos(int $limit = 3): array
{
    $stmt = getDB()->prepare(
        "SELECT v.*, s.name AS subject_name, s.slug AS subject_slug
         FROM videos v
         INNER JOIN subjects s ON s.id = v.subject_id
         WHERE v.is_published = 1
         ORDER BY v.created_at DESC
         LIMIT " . max(1, $limit)
    );
    $stmt->execute();

    return $stmt->fetchAll();
}

function get_video_by_id(int $id): ?array
{
    $stmt = getDB()->prepare('SELECT * FROM videos WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);

    return $stmt->fetch() ?: null;
}

/** Only ever used for the public video page, so only a published video is ever returned. */
function get_published_video_by_slug(string $slug): ?array
{
    $stmt = getDB()->prepare(
        "SELECT v.*, s.name AS subject_name, s.slug AS subject_slug
         FROM videos v
         INNER JOIN subjects s ON s.id = v.subject_id
         WHERE v.slug = ? AND v.is_published = 1
         LIMIT 1"
    );
    $stmt->execute([$slug]);

    return $stmt->fetch() ?: null;
}

/** The resources a video is associated with, in sort order — video.php's "Practice This Skill". */
function get_video_resources(int $videoId): array
{
    $stmt = getDB()->prepare(
        "SELECT r.*, c.name AS category_name, s.name AS subject_name, s.slug AS subject_slug
         FROM video_resources vr
         INNER JOIN resources r ON r.id = vr.resource_id
         LEFT JOIN categories c ON c.id = r.category_id
         INNER JOIN subjects s ON s.id = r.subject_id
         WHERE vr.video_id = ? AND r.is_published = 1 AND r.status = 'active'
         ORDER BY vr.sort_order, r.title"
    );
    $stmt->execute([$videoId]);

    return $stmt->fetchAll();
}

/** Resource IDs only, for repopulating the admin form's multi-select on edit. */
function get_video_resource_ids(int $videoId): array
{
    $stmt = getDB()->prepare('SELECT resource_id FROM video_resources WHERE video_id = ?');
    $stmt->execute([$videoId]);

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/** Published videos associated with a resource — resource.php's "Watch the Lesson" section reads the first of these. */
function get_published_videos_for_resource(int $resourceId): array
{
    $stmt = getDB()->prepare(
        "SELECT v.*, s.name AS subject_name
         FROM video_resources vr
         INNER JOIN videos v ON v.id = vr.video_id
         INNER JOIN subjects s ON s.id = v.subject_id
         WHERE vr.resource_id = ? AND v.is_published = 1
         ORDER BY vr.sort_order, v.created_at"
    );
    $stmt->execute([$resourceId]);

    return $stmt->fetchAll();
}

/** Manually admin-picked related videos, published only, in sort_order. Empty when none picked — video.php falls back to the automatic relevance query in that case. */
function get_manual_related_videos(int $videoId): array
{
    $stmt = getDB()->prepare(
        "SELECT v.*, s.name AS subject_name, s.slug AS subject_slug
         FROM video_related_videos vv
         INNER JOIN videos v ON v.id = vv.related_video_id
         INNER JOIN subjects s ON s.id = v.subject_id
         WHERE vv.video_id = ? AND v.is_published = 1
         ORDER BY vv.sort_order, v.title"
    );
    $stmt->execute([$videoId]);

    return $stmt->fetchAll();
}

/**
 * Automatic "More Videos" fallback, ranked by relevance: same subject (+2),
 * same grade (+1) — mirrors get_related_resources()'s relevance scoring.
 */
function get_related_videos(array $video, int $limit = 3): array
{
    $stmt = getDB()->prepare(
        "SELECT v.*, s.name AS subject_name, s.slug AS subject_slug,
                (
                    (CASE WHEN v.subject_id = ? THEN 2 ELSE 0 END) +
                    (CASE WHEN v.grade_level IS NOT NULL AND v.grade_level = ? THEN 1 ELSE 0 END)
                ) AS relevance
         FROM videos v
         INNER JOIN subjects s ON s.id = v.subject_id
         WHERE v.is_published = 1 AND v.id != ?
         ORDER BY relevance DESC, v.created_at DESC
         LIMIT " . max(1, $limit)
    );
    $stmt->execute([
        $video['subject_id'],
        $video['grade_level'] ?? '',
        $video['id'],
    ]);

    return $stmt->fetchAll();
}

/** Every related_video_id currently picked for this video, regardless of the target's published state — pre-checks the admin form's picker. */
function get_related_video_ids(int $videoId): array
{
    $stmt = getDB()->prepare('SELECT related_video_id FROM video_related_videos WHERE video_id = ? ORDER BY sort_order');
    $stmt->execute([$videoId]);

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function validate_video_input(array $input): array
{
    $errors = [];

    $title = clean_input($input['title'] ?? '');
    if ($title === '' || mb_strlen($title) > 200) {
        $errors['title'] = 'Please enter a title.';
    }

    $youtubeId = trim((string)($input['youtube_video_id'] ?? ''));
    if ($youtubeId === '' || !preg_match('/^[A-Za-z0-9_-]{6,20}$/', $youtubeId)) {
        $errors['youtube_video_id'] = 'Please enter a valid YouTube video ID (the part after "v=" in the video URL).';
    }

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

    if (!empty($input['topic']) && mb_strlen($input['topic']) > 150) {
        $errors['topic'] = 'Topic is too long.';
    }

    if (!empty($input['duration']) && mb_strlen($input['duration']) > 20) {
        $errors['duration'] = 'Duration is too long — use a short format like "4:32".';
    }

    if (!empty($input['seo_title']) && mb_strlen($input['seo_title']) > 255) {
        $errors['seo_title'] = 'SEO title is too long (255 characters max).';
    }

    if (!empty($input['meta_description']) && mb_strlen($input['meta_description']) > 300) {
        $errors['meta_description'] = 'Meta description is too long (300 characters max).';
    }

    return $errors;
}

/** The optional long-text fields shared by create_video() and update_video(), normalized to null-when-empty. */
function extract_video_text_fields(array $input): array
{
    $fields = [];
    foreach (['learning_objectives', 'key_concepts', 'transcript'] as $key) {
        $value = clean_input($input[$key] ?? '');
        $fields[$key] = $value !== '' ? $value : null;
    }

    return $fields;
}

/** Returns ['success' => bool, 'errors' => array<string,string>, 'id' => ?int] */
function create_video(array $input, array $files = []): array
{
    $errors = validate_video_input($input);

    $uploadedThumbnail = null;
    if (empty($errors) && !empty($files['thumbnail']['name'])) {
        $upload = handle_upload($files['thumbnail'], UPLOAD_THUMBNAIL_PATH, ALLOWED_IMAGE_MIME_TYPES, MAX_IMAGE_SIZE_BYTES);

        if (!$upload['success']) {
            $errors['thumbnail'] = $upload['error'];
        } else {
            $uploadedThumbnail = $upload;
        }
    }

    if (!empty($errors)) {
        if ($uploadedThumbnail) {
            @unlink(UPLOAD_THUMBNAIL_PATH . '/' . $uploadedThumbnail['filename']);
        }

        return ['success' => false, 'errors' => $errors, 'id' => null];
    }

    $title = clean_input($input['title']);
    $description = clean_input($input['description'] ?? '');
    $topic = clean_input($input['topic'] ?? '');
    $duration = clean_input($input['duration'] ?? '');
    $seoTitle = clean_input($input['seo_title'] ?? '');
    $metaDescription = clean_input($input['meta_description'] ?? '');
    $textFields = extract_video_text_fields($input);

    $stmt = getDB()->prepare(
        'INSERT INTO videos (title, slug, description, youtube_video_id, subject_id, grade_level, topic,
                              duration, thumbnail, learning_objectives, key_concepts, transcript,
                              seo_title, meta_description, is_published)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $title,
        generate_unique_video_slug($title),
        $description !== '' ? $description : null,
        trim((string)$input['youtube_video_id']),
        (int)$input['subject_id'],
        $input['grade_level'] !== '' ? $input['grade_level'] : null,
        $topic !== '' ? $topic : null,
        $duration !== '' ? $duration : null,
        $uploadedThumbnail['filename'] ?? null,
        $textFields['learning_objectives'],
        $textFields['key_concepts'],
        $textFields['transcript'],
        $seoTitle !== '' ? $seoTitle : null,
        $metaDescription !== '' ? $metaDescription : null,
        !empty($input['is_published']) ? 1 : 0,
    ]);

    $newId = (int)getDB()->lastInsertId();

    set_video_resources($newId, $input['resource_ids'] ?? []);
    set_related_videos($newId, $input['related_video_ids'] ?? []);

    return ['success' => true, 'errors' => [], 'id' => $newId];
}

/** Returns ['success' => bool, 'errors' => array<string,string>] */
function update_video(int $id, array $input, array $files = []): array
{
    $existing = get_video_by_id($id);
    if (!$existing) {
        return ['success' => false, 'errors' => ['general' => 'Video not found.']];
    }

    $errors = validate_video_input($input);

    $newThumbnail = null;
    if (empty($errors) && !empty($files['thumbnail']['name'])) {
        $upload = handle_upload($files['thumbnail'], UPLOAD_THUMBNAIL_PATH, ALLOWED_IMAGE_MIME_TYPES, MAX_IMAGE_SIZE_BYTES);

        if (!$upload['success']) {
            $errors['thumbnail'] = $upload['error'];
        } else {
            $newThumbnail = $upload;
        }
    }

    if (!empty($errors)) {
        if ($newThumbnail) {
            @unlink(UPLOAD_THUMBNAIL_PATH . '/' . $newThumbnail['filename']);
        }

        return ['success' => false, 'errors' => $errors];
    }

    $thumbnail = $existing['thumbnail'];
    if ($newThumbnail) {
        if (!empty($existing['thumbnail'])) {
            @unlink(UPLOAD_THUMBNAIL_PATH . '/' . $existing['thumbnail']);
        }
        $thumbnail = $newThumbnail['filename'];
    }

    $title = clean_input($input['title']);
    $description = clean_input($input['description'] ?? '');
    $topic = clean_input($input['topic'] ?? '');
    $duration = clean_input($input['duration'] ?? '');
    $seoTitle = clean_input($input['seo_title'] ?? '');
    $metaDescription = clean_input($input['meta_description'] ?? '');
    $textFields = extract_video_text_fields($input);

    getDB()->prepare(
        // slug is deliberately not regenerated on edit — same reasoning as
        // resources/bundles: a published video's URL may already be shared/indexed.
        'UPDATE videos SET title = ?, description = ?, youtube_video_id = ?, subject_id = ?, grade_level = ?,
                            topic = ?, duration = ?, thumbnail = ?, learning_objectives = ?, key_concepts = ?,
                            transcript = ?, seo_title = ?, meta_description = ?, is_published = ? WHERE id = ?'
    )->execute([
        $title,
        $description !== '' ? $description : null,
        trim((string)$input['youtube_video_id']),
        (int)$input['subject_id'],
        $input['grade_level'] !== '' ? $input['grade_level'] : null,
        $topic !== '' ? $topic : null,
        $duration !== '' ? $duration : null,
        $thumbnail,
        $textFields['learning_objectives'],
        $textFields['key_concepts'],
        $textFields['transcript'],
        $seoTitle !== '' ? $seoTitle : null,
        $metaDescription !== '' ? $metaDescription : null,
        !empty($input['is_published']) ? 1 : 0,
        $id,
    ]);

    set_video_resources($id, $input['resource_ids'] ?? []);
    set_related_videos($id, $input['related_video_ids'] ?? []);

    return ['success' => true, 'errors' => []];
}

/** Replaces a video's associated-resources list wholesale. */
function set_video_resources(int $videoId, array $resourceIds): void
{
    $db = getDB();
    $db->prepare('DELETE FROM video_resources WHERE video_id = ?')->execute([$videoId]);

    $stmt = $db->prepare('INSERT INTO video_resources (video_id, resource_id, sort_order) VALUES (?, ?, ?)');
    $order = 0;
    foreach ($resourceIds as $resourceId) {
        $resourceId = (int)$resourceId;
        if ($resourceId > 0) {
            $stmt->execute([$videoId, $resourceId, $order]);
            $order++;
        }
    }
}

/** Replaces a video's manual related-videos list wholesale. Self-references are silently dropped. */
function set_related_videos(int $videoId, array $relatedIds): void
{
    $db = getDB();
    $db->prepare('DELETE FROM video_related_videos WHERE video_id = ?')->execute([$videoId]);

    $stmt = $db->prepare('INSERT INTO video_related_videos (video_id, related_video_id, sort_order) VALUES (?, ?, ?)');
    $order = 0;
    foreach ($relatedIds as $relatedId) {
        $relatedId = (int)$relatedId;
        if ($relatedId > 0 && $relatedId !== $videoId) {
            $stmt->execute([$videoId, $relatedId, $order]);
            $order++;
        }
    }
}
