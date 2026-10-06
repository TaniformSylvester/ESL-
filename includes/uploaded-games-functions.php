<?php
/**
 * HTML games uploaded from Admin > Games.
 *
 * The hand-built games in includes/games-functions.php are files in the
 * codebase; these are single-file HTML games the site owner uploads
 * through the admin. get_all_games() merges the published ones in, so they
 * appear on the Games hub, get a game.php landing page, the sitemap and
 * play tracking, exactly like the built-in games.
 *
 * Security: an uploaded HTML file is code. It is stored in uploads/games/
 * (direct web access denied by .htaccess) and only ever served by
 * play-game.php with a Content-Security-Policy "sandbox" header, plus a
 * sandboxed <iframe> on game.php. Sandboxed without allow-same-origin, the
 * game runs as an anonymous "null" origin: it can't read the visitor's
 * TeachLuma login cookie, call the site's logged-in pages as them, touch
 * the parent page, or navigate the top window. Games that save scores in
 * localStorage get an in-memory stand-in (see play-game.php) instead of
 * crashing, since sandboxed pages have no real storage.
 *
 * Games are single self-contained .html files: anything they load must be
 * inline or from a full https:// URL (e.g. a CDN). A game that expects
 * separate image/sound files beside it won't find them.
 */

const UPLOADED_GAMES_DIR = UPLOAD_BASE_PATH . '/games';
const UPLOADED_GAME_MAX_BYTES = 15 * 1024 * 1024;
const UPLOADED_GAME_MAX_FILES = 50;
const GAME_SUBJECTS = ['ESL', 'Math', 'Science'];
const GAME_DIFFICULTIES = ['Easy', 'Medium', 'Advanced'];

/** Shown on game.php when a game has no "how to use" steps of its own. */
const UPLOADED_GAME_DEFAULT_STEPS = [
    'Open the game on your laptop or tablet.',
    'Connect to a projector or classroom display, if you have one.',
    'Press Fullscreen so the whole class can see.',
    'Play together, letting students take turns or discuss each answer.',
];

/**
 * Whether the uploaded_games table exists yet (the migration is run by hand
 * in phpMyAdmin). Until it does, everything here quietly returns nothing,
 * so deploying the files before running the SQL can't break the site.
 */
function uploaded_games_available(): bool
{
    static $available = null;

    if ($available === null) {
        try {
            getDB()->query('SELECT 1 FROM uploaded_games LIMIT 1');
            $available = true;
        } catch (PDOException $e) {
            $available = false;
        }
    }

    return $available;
}

/** Parses a php.ini size like "8M" or "1G" into bytes. */
function ini_size_bytes(string $value): int
{
    $value = trim($value);
    $number = (int)$value;

    return match (strtoupper(substr($value, -1))) {
        'G' => $number * 1073741824,
        'M' => $number * 1048576,
        'K' => $number * 1024,
        default => $number,
    };
}

/** The largest game file this server will actually accept (our cap, or PHP's limits if lower). */
function uploaded_game_size_limit(): int
{
    $limits = [UPLOADED_GAME_MAX_BYTES];
    foreach (['upload_max_filesize', 'post_max_size'] as $key) {
        $bytes = ini_size_bytes((string)ini_get($key));
        if ($bytes > 0) {
            $limits[] = $bytes;
        }
    }

    return min($limits);
}

/** Splits a one-item-per-line text field into a clean list. */
function uploaded_game_lines(?string $text): array
{
    $lines = preg_split('/\R/', (string)$text) ?: [];

    return array_values(array_filter(array_map(static fn($l) => trim((string)$l, " \t-•*"), $lines), static fn($l) => $l !== ''));
}

/** Maps an uploaded_games row to the same shape as a get_all_games() entry. */
function uploaded_game_to_game(array $row): array
{
    $steps = uploaded_game_lines($row['how_to_use'] ?? '');

    return [
        'slug'                   => $row['slug'],
        'title'                  => $row['title'],
        'subject'                => $row['subject'],
        'grade'                  => $row['grade'] !== '' ? $row['grade'] : 'All Grades',
        'topic'                  => $row['topic'] !== '' ? $row['topic'] : $row['subject'],
        'difficulty'             => $row['difficulty'],
        'short_description'      => $row['short_description'] !== ''
            ? $row['short_description']
            : 'An interactive ' . $row['subject'] . ' game to play with your class.',
        'what_students_practice' => uploaded_game_lines($row['what_students_practice'] ?? ''),
        'how_to_use'             => $steps ?: UPLOADED_GAME_DEFAULT_STEPS,
        'thumbnail'              => !empty($row['thumbnail']) ? UPLOAD_THUMBNAIL_URL . '/' . rawurlencode($row['thumbnail']) : null,
        'featured'               => (bool)$row['is_featured'],
        'team_play'              => false,
        'source'                 => 'upload',
        'id'                     => (int)$row['id'],
        'file_name'              => $row['file_name'],
        'is_published'           => (bool)$row['is_published'],
    ];
}

/** Published uploaded games, newest first, as get_all_games() entries. Cached per request. */
function get_published_uploaded_games(): array
{
    static $games = null;

    if ($games === null) {
        $games = [];
        if (uploaded_games_available()) {
            $rows = getDB()->query('SELECT * FROM uploaded_games WHERE is_published = 1 ORDER BY created_at DESC, id DESC')->fetchAll();
            $games = array_map('uploaded_game_to_game', $rows);
        }
    }

    return $games;
}

function get_uploaded_game_row(int $id): ?array
{
    if (!uploaded_games_available()) {
        return null;
    }
    $stmt = getDB()->prepare('SELECT * FROM uploaded_games WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);

    return $stmt->fetch() ?: null;
}

/** Any uploaded game by slug, published or not (for play-game.php's admin preview). */
function get_uploaded_game_row_by_slug(string $slug): ?array
{
    if (!uploaded_games_available()) {
        return null;
    }
    $stmt = getDB()->prepare('SELECT * FROM uploaded_games WHERE slug = ? LIMIT 1');
    $stmt->execute([$slug]);

    return $stmt->fetch() ?: null;
}

/** Admin list: every uploaded game, filterable by search text and status. */
function get_uploaded_games_paginated(array $filters, int $page, int $perPage): array
{
    $where = [];
    $params = [];

    $search = trim((string)($filters['search'] ?? ''));
    if ($search !== '') {
        $where[] = '(title LIKE ? OR original_filename LIKE ? OR topic LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like);
    }

    $status = (string)($filters['status'] ?? '');
    if ($status === 'published' || $status === 'draft') {
        $where[] = 'is_published = ?';
        $params[] = $status === 'published' ? 1 : 0;
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $db = getDB();

    $count = $db->prepare("SELECT COUNT(*) FROM uploaded_games {$whereSql}");
    $count->execute($params);
    $total = (int)$count->fetchColumn();

    $totalPages = max(1, (int)ceil($total / max(1, $perPage)));
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;

    $stmt = $db->prepare("SELECT * FROM uploaded_games {$whereSql} ORDER BY created_at DESC, id DESC LIMIT {$perPage} OFFSET {$offset}");
    $stmt->execute($params);

    return ['items' => $stmt->fetchAll(), 'total' => $total, 'total_pages' => $totalPages, 'page' => $page];
}

/** A slug that's free among both the built-in games and other uploads. */
function unique_game_slug(string $base, ?int $exceptId = null): string
{
    $base = substr(slugify($base), 0, 90) ?: 'game';
    $builtIn = array_column(get_builtin_games(), 'slug');
    $stmt = getDB()->prepare('SELECT id FROM uploaded_games WHERE slug = ? AND id != ? LIMIT 1');

    $slug = $base;
    for ($n = 2; ; $n++) {
        $stmt->execute([$slug, $exceptId ?? 0]);
        if (!in_array($slug, $builtIn, true) && !$stmt->fetchColumn()) {
            return $slug;
        }
        $slug = $base . '-' . $n;
    }
}

/** Reads a sensible title and description out of the game's own HTML. */
function extract_game_html_meta(string $html, string $originalName): array
{
    $title = '';
    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
        $title = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }
    if ($title === '') {
        $title = ucwords(trim(preg_replace('/[-_\s]+/', ' ', pathinfo($originalName, PATHINFO_FILENAME)) ?? ''));
    }

    $description = '';
    if (preg_match('/<meta\s[^>]*name=["\']description["\'][^>]*>/i', $html, $m)
        && preg_match('/content=["\']([^"\']*)["\']/i', $m[0], $c)) {
        $description = trim(html_entity_decode($c[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    return [
        'title'       => mb_substr($title !== '' ? $title : 'Untitled Game', 0, 150),
        'description' => mb_substr($description, 0, 255),
    ];
}

/**
 * Checks one uploaded file really is an HTML game and moves it into
 * uploads/games/. Returns ['success', 'error', 'file_name', 'html', 'size'].
 */
function store_game_html_upload(array $file): array
{
    $fail = static fn(string $msg): array => ['success' => false, 'error' => $msg, 'file_name' => null, 'html' => '', 'size' => 0];

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return $fail(match ($file['error'] ?? null) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'is larger than the server allows.',
            UPLOAD_ERR_NO_FILE => 'no file was chosen.',
            default => 'failed to upload. Please try again.',
        });
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return $fail('failed to upload. Please try again.');
    }

    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['html', 'htm'], true)) {
        return $fail('is not an .html file.');
    }
    if ((int)$file['size'] > UPLOADED_GAME_MAX_BYTES) {
        return $fail('is larger than ' . (UPLOADED_GAME_MAX_BYTES / 1048576) . ' MB.');
    }

    $html = (string)file_get_contents($file['tmp_name']);
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
    if ($finfo) {
        finfo_close($finfo);
    }
    if (!in_array($mime, ['text/html', 'text/plain', 'text/xml', 'application/xhtml+xml'], true)
        || !preg_match('/<(html|body|script|canvas|div|!doctype)\b/i', $html)) {
        return $fail("doesn't look like an HTML page.");
    }

    if (!is_dir(UPLOADED_GAMES_DIR) && !mkdir(UPLOADED_GAMES_DIR, 0755, true) && !is_dir(UPLOADED_GAMES_DIR)) {
        return $fail('could not be saved (server storage error).');
    }

    $fileName = bin2hex(random_bytes(16)) . '.html';
    if (!move_uploaded_file($file['tmp_name'], UPLOADED_GAMES_DIR . '/' . $fileName)) {
        return $fail('could not be saved.');
    }
    chmod(UPLOADED_GAMES_DIR . '/' . $fileName, 0644);

    return ['success' => true, 'error' => null, 'file_name' => $fileName, 'html' => $html, 'size' => strlen($html)];
}

/**
 * Bulk upload: one new game per file. $defaults holds subject, grade,
 * difficulty and publish, applied to every file in the batch; each title
 * comes from the file's own <title>. Returns ['created' => [titles],
 * 'errors' => ["file.html is not an .html file.", ...]].
 */
function create_uploaded_games(array $files, array $defaults): array
{
    $created = [];
    $errors = [];

    // Normalise PHP's files[] layout into one array per file.
    $list = [];
    foreach ((array)($files['name'] ?? []) as $i => $name) {
        $list[] = [
            'name'     => $name,
            'type'     => $files['type'][$i] ?? '',
            'tmp_name' => $files['tmp_name'][$i] ?? '',
            'error'    => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'size'     => $files['size'][$i] ?? 0,
        ];
    }
    $list = array_values(array_filter($list, static fn($f) => $f['error'] !== UPLOAD_ERR_NO_FILE));

    if (!$list) {
        return ['created' => [], 'errors' => ['Choose at least one .html game file.']];
    }
    if (count($list) > UPLOADED_GAME_MAX_FILES) {
        return ['created' => [], 'errors' => ['Please upload at most ' . UPLOADED_GAME_MAX_FILES . ' files at a time.']];
    }

    $subject = in_array($defaults['subject'] ?? '', GAME_SUBJECTS, true) ? $defaults['subject'] : 'ESL';
    $difficulty = in_array($defaults['difficulty'] ?? '', GAME_DIFFICULTIES, true) ? $defaults['difficulty'] : 'Easy';
    $grade = mb_substr(clean_input($defaults['grade'] ?? ''), 0, 50);
    $publish = !empty($defaults['publish']) ? 1 : 0;

    $insert = getDB()->prepare(
        'INSERT INTO uploaded_games (slug, title, subject, grade, difficulty, short_description, file_name, original_filename, file_size, is_published)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    foreach ($list as $file) {
        $displayName = basename((string)$file['name']);
        $stored = store_game_html_upload($file);
        if (!$stored['success']) {
            $errors[] = $displayName . ' ' . $stored['error'];
            continue;
        }

        $meta = extract_game_html_meta($stored['html'], $displayName);
        // Titles in Thai (or any non-Latin script) slugify to almost nothing; use the file name then.
        $slugSource = strlen(slugify($meta['title'])) >= 3 ? $meta['title'] : pathinfo($displayName, PATHINFO_FILENAME);
        $insert->execute([
            unique_game_slug($slugSource),
            $meta['title'],
            $subject,
            $grade,
            $difficulty,
            $meta['description'],
            $stored['file_name'],
            mb_substr($displayName, 0, 255),
            $stored['size'],
            $publish,
        ]);
        $created[] = $meta['title'];
    }

    return ['created' => $created, 'errors' => $errors];
}

/**
 * Saves the edit form. Optional $htmlFile replaces the game file and
 * $thumbFile the thumbnail. Returns ['success' => bool, 'errors' => [field => msg]].
 */
function update_uploaded_game(array $row, array $input, array $htmlFile, array $thumbFile): array
{
    $errors = [];

    $title = mb_substr(clean_input($input['title'] ?? ''), 0, 150);
    if ($title === '') {
        $errors['title'] = 'Please enter a title.';
    }

    $slugInput = trim((string)($input['slug'] ?? ''));
    $slug = unique_game_slug($slugInput !== '' ? $slugInput : $title, (int)$row['id']);

    $data = [
        'title'                  => $title,
        'slug'                   => $slug,
        'subject'                => in_array($input['subject'] ?? '', GAME_SUBJECTS, true) ? $input['subject'] : 'ESL',
        'grade'                  => mb_substr(clean_input($input['grade'] ?? ''), 0, 50),
        'topic'                  => mb_substr(clean_input($input['topic'] ?? ''), 0, 150),
        'difficulty'             => in_array($input['difficulty'] ?? '', GAME_DIFFICULTIES, true) ? $input['difficulty'] : 'Easy',
        'short_description'      => mb_substr(clean_input($input['short_description'] ?? ''), 0, 255),
        'what_students_practice' => mb_substr(trim((string)($input['what_students_practice'] ?? '')), 0, 5000),
        'how_to_use'             => mb_substr(trim((string)($input['how_to_use'] ?? '')), 0, 5000),
        'is_published'           => !empty($input['is_published']) ? 1 : 0,
        'is_featured'            => !empty($input['is_featured']) ? 1 : 0,
    ];

    $newHtml = null;
    if (($htmlFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $newHtml = store_game_html_upload($htmlFile);
        if (!$newHtml['success']) {
            $errors['game_file'] = 'That file ' . $newHtml['error'];
            $newHtml = null;
        }
    }

    $newThumb = null;
    if (empty($errors) && ($thumbFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $upload = handle_upload($thumbFile, UPLOAD_THUMBNAIL_PATH, ALLOWED_IMAGE_MIME_TYPES, MAX_IMAGE_SIZE_BYTES);
        if (!$upload['success']) {
            $errors['thumbnail'] = $upload['error'];
        } else {
            $newThumb = $upload['filename'];
        }
    }

    if (!empty($errors)) {
        if ($newHtml) {
            @unlink(UPLOADED_GAMES_DIR . '/' . $newHtml['file_name']);
        }
        if ($newThumb) {
            @unlink(UPLOAD_THUMBNAIL_PATH . '/' . $newThumb);
        }
        return ['success' => false, 'errors' => $errors];
    }

    if ($newHtml) {
        $data['file_name'] = $newHtml['file_name'];
        $data['original_filename'] = mb_substr(basename((string)$htmlFile['name']), 0, 255);
        $data['file_size'] = $newHtml['size'];
    }
    if ($newThumb) {
        $data['thumbnail'] = $newThumb;
    } elseif (!empty($input['remove_thumbnail'])) {
        $data['thumbnail'] = null;
    }

    $sets = implode(', ', array_map(static fn($k) => "{$k} = ?", array_keys($data)));
    getDB()->prepare("UPDATE uploaded_games SET {$sets} WHERE id = ?")
        ->execute([...array_values($data), (int)$row['id']]);

    // Play counts are stored by slug; carry them over to a renamed game.
    if ($slug !== $row['slug']) {
        getDB()->prepare('UPDATE game_plays SET game_slug = ? WHERE game_slug = ?')->execute([$slug, $row['slug']]);
    }

    // Old files are removed only once the new ones are safely recorded.
    if ($newHtml) {
        @unlink(UPLOADED_GAMES_DIR . '/' . $row['file_name']);
    }
    if (array_key_exists('thumbnail', $data) && !empty($row['thumbnail'])) {
        @unlink(UPLOAD_THUMBNAIL_PATH . '/' . $row['thumbnail']);
    }

    return ['success' => true, 'errors' => []];
}

function delete_uploaded_game(array $row): void
{
    getDB()->prepare('DELETE FROM uploaded_games WHERE id = ?')->execute([(int)$row['id']]);
    @unlink(UPLOADED_GAMES_DIR . '/' . $row['file_name']);
    if (!empty($row['thumbnail'])) {
        @unlink(UPLOAD_THUMBNAIL_PATH . '/' . $row['thumbnail']);
    }
}

/** Publish/unpublish several games at once from the admin list. */
function set_uploaded_games_published(array $ids, bool $published): int
{
    $ids = array_values(array_filter(array_map('intval', $ids)));
    if (!$ids) {
        return 0;
    }
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $stmt = getDB()->prepare("UPDATE uploaded_games SET is_published = ? WHERE id IN ({$marks})");
    $stmt->execute([$published ? 1 : 0, ...$ids]);

    return $stmt->rowCount();
}
