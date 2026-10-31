<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-functions.php';
require_once __DIR__ . '/../includes/upload-functions.php';
require_once __DIR__ . '/../includes/games-functions.php';

require_admin();
$admin = current_user();
$tableReady = uploaded_games_available();

$isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';

// Bigger than post_max_size: PHP drops the whole body, CSRF token included.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $message = 'is larger than the server allows (' . format_file_size(uploaded_game_size_limit()) . ').';
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['created' => [], 'errors' => [$message], 'too_large' => true]);
        exit;
    }
    flash_set('error', 'Those files were too large to upload in one go. Try fewer files at a time.');
    redirect('admin/games.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tableReady) {
    require_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'upload') {
        $result = create_uploaded_games($_FILES['game_files'] ?? [], [
            'subject'    => $_POST['subject'] ?? 'ESL',
            'grade'      => $_POST['grade'] ?? '',
            'difficulty' => $_POST['difficulty'] ?? 'Easy',
            'publish'    => !empty($_POST['publish']),
        ]);

        if ($result['created']) {
            $count = count($result['created']);
            log_admin_action($admin['id'], 'upload_games', 'Uploaded ' . $count . ' game(s): ' . mb_substr(implode(', ', $result['created']), 0, 400));
        }

        // The page's script uploads files one per request (so PHP's per-request
        // file-count and size limits never bite) and reports progress itself.
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode($result);
            exit;
        }

        if ($result['created']) {
            flash_set('success', $count . ' game' . ($count === 1 ? '' : 's') . ' uploaded' . (!empty($_POST['publish']) ? ' and published' : ' as drafts') . '. Open one to add a description, topic or thumbnail.');
        }
        foreach ($result['errors'] as $error) {
            flash_set('error', $error);
        }
        redirect('admin/games.php');
    }

    if ($action === 'bulk' && !empty($_POST['ids']) && in_array($_POST['bulk'] ?? '', ['publish', 'unpublish'], true)) {
        $changed = set_uploaded_games_published((array)$_POST['ids'], $_POST['bulk'] === 'publish');
        log_admin_action($admin['id'], 'bulk_games', ucfirst($_POST['bulk']) . "ed {$changed} game(s)");
        flash_set('success', $changed . ' game' . ($changed === 1 ? '' : 's') . ' ' . ($_POST['bulk'] === 'publish' ? 'published' : 'moved to drafts') . '.');
        redirect('admin/games.php');
    }

    if ($action === 'delete') {
        $row = get_uploaded_game_row((int)($_POST['id'] ?? 0));
        if ($row) {
            delete_uploaded_game($row);
            log_admin_action($admin['id'], 'delete_game', 'Deleted game "' . $row['title'] . '"');
            flash_set('success', 'Game deleted.');
        }
        redirect('admin/games.php');
    }

    redirect('admin/games.php');
}

$filters = [
    'search' => trim((string)($_GET['search'] ?? '')),
    'status' => trim((string)($_GET['status'] ?? '')),
];
$page = max(1, (int)($_GET['page'] ?? 1));
$result = $tableReady ? get_uploaded_games_paginated($filters, $page, ADMIN_ROWS_PER_PAGE) : ['items' => [], 'total' => 0, 'total_pages' => 1, 'page' => 1];
$builtInCount = count(get_builtin_games());
$sizeLimit = uploaded_game_size_limit();

$pageTitle = 'Games';
require_once __DIR__ . '/../includes/admin-header.php';
?>
<?php if (!$tableReady): ?>
    <div class="alert alert-warning">
        <h2 class="h6 fw-bold">One-time setup needed</h2>
        <p class="mb-0">Run <code>uploaded_games_migration.sql</code> in phpMyAdmin (cPanel &rarr; phpMyAdmin &rarr; your TeachLuma database &rarr; SQL tab &rarr; paste &rarr; Go), then reload this page to start uploading games.</p>
    </div>
<?php else: ?>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <h2 class="h5 fw-bold mb-1">Upload HTML games</h2>
        <p class="text-secondary small mb-3">
            Choose one or many <code>.html</code> game files (up to <?= (int)UPLOADED_GAME_MAX_FILES ?> at a time, <?= e(format_file_size($sizeLimit)) ?> each).
            Each file becomes a game on the <a href="<?= e(base_url('games.php')) ?>" target="_blank">Games page</a>, titled from the file's own
            <code>&lt;title&gt;</code>. Games must be single self-contained files (images and sounds inside the file, or loaded from full <code>https://</code> links).
            They run in a sealed-off sandbox, so a game's code can never reach your site, your visitors' accounts or this admin area.
        </p>
        <form method="post" action="<?= e(base_url('admin/games.php')) ?>" enctype="multipart/form-data" class="row g-3 align-items-end"
              id="game-upload-form" data-max-bytes="<?= (int)$sizeLimit ?>" data-max-files="<?= (int)UPLOADED_GAME_MAX_FILES ?>">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="upload">
            <div class="col-12">
                <label class="form-label fw-semibold" for="game_files">Game files</label>
                <input type="file" class="form-control" id="game_files" name="game_files[]" accept=".html,.htm,text/html" multiple required>
            </div>
            <div class="col-sm-4 col-lg-3">
                <label class="form-label" for="subject">Subject</label>
                <select class="form-select" id="subject" name="subject">
                    <?php foreach (GAME_SUBJECTS as $s): ?><option><?= e($s) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-4 col-lg-3">
                <label class="form-label" for="grade">Grade <span class="text-secondary">(optional)</span></label>
                <input type="text" class="form-control" id="grade" name="grade" maxlength="50" placeholder="e.g. Grade 1–3">
            </div>
            <div class="col-sm-4 col-lg-2">
                <label class="form-label" for="difficulty">Difficulty</label>
                <select class="form-select" id="difficulty" name="difficulty">
                    <?php foreach (GAME_DIFFICULTIES as $d): ?><option><?= e($d) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-6 col-lg-2">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="publish" name="publish" value="1" checked>
                    <label class="form-check-label" for="publish">Publish right away</label>
                </div>
            </div>
            <div class="col-sm-6 col-lg-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-upload me-1" aria-hidden="true"></i>Upload</button>
            </div>
            <p class="small text-secondary mb-0">Subject, grade and difficulty apply to every file in this upload &mdash; you can change any game afterwards.</p>
        </form>
        <div id="upload-progress" class="mt-3" hidden>
            <div class="progress mb-2" role="progressbar" aria-label="Upload progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                <div class="progress-bar" style="width:0%"></div>
            </div>
            <ul class="list-unstyled small mb-0" aria-live="polite"></ul>
        </div>
    </div>
</div>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <p class="text-secondary mb-0"><?= (int)$result['total'] ?> uploaded game<?= $result['total'] === 1 ? '' : 's' ?> &middot; plus <?= (int)$builtInCount ?> built-in games</p>
    <form method="get" action="<?= e(base_url('admin/games.php')) ?>" class="d-flex gap-2">
        <input type="text" name="search" class="form-control form-control-sm" placeholder="Search title or file&hellip;" value="<?= e($filters['search']) ?>" aria-label="Search games">
        <select name="status" class="form-select form-select-sm" aria-label="Status">
            <option value="">All</option>
            <option value="published" <?= $filters['status'] === 'published' ? 'selected' : '' ?>>Published</option>
            <option value="draft" <?= $filters['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
        </select>
        <button type="submit" class="btn btn-sm btn-outline-primary">Filter</button>
    </form>
</div>

<form method="post" action="<?= e(base_url('admin/games.php')) ?>" id="bulk-form">
    <?php csrf_field(); ?>
    <input type="hidden" name="action" value="bulk">
</form>

<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th style="width:2rem;"><input type="checkbox" class="form-check-input" id="select-all" aria-label="Select all games on this page"></th>
                    <th>Game</th>
                    <th>Subject</th>
                    <th>Grade</th>
                    <th>Status</th>
                    <th>Plays</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($result['items'])): ?>
                    <tr><td colspan="7" class="text-center text-secondary py-4">No uploaded games yet &mdash; upload your first ones above.</td></tr>
                <?php endif; ?>
                <?php
                $playStmt = getDB()->prepare("SELECT COUNT(*) FROM game_plays WHERE game_slug = ? AND event_type = 'started'");
                foreach ($result['items'] as $row):
                    $playStmt->execute([$row['slug']]);
                ?>
                    <tr>
                        <td><input type="checkbox" class="form-check-input game-select" name="ids[]" value="<?= (int)$row['id'] ?>" form="bulk-form" aria-label="Select <?= e($row['title']) ?>"></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <?php if (!empty($row['thumbnail'])): ?>
                                    <img src="<?= e(UPLOAD_THUMBNAIL_URL . '/' . rawurlencode($row['thumbnail'])) ?>" alt="" width="56" height="32" class="rounded" style="object-fit:cover;">
                                <?php else: ?>
                                    <span class="d-inline-flex align-items-center justify-content-center rounded bg-light text-secondary" style="width:56px;height:32px;"><i class="fa-solid fa-gamepad" aria-hidden="true"></i></span>
                                <?php endif; ?>
                                <div>
                                    <div class="fw-semibold"><?= e($row['title']) ?></div>
                                    <div class="small text-secondary"><?= e($row['original_filename']) ?> &middot; <?= e(format_file_size((int)$row['file_size'])) ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="small"><?= e($row['subject']) ?></td>
                        <td class="small"><?= e($row['grade'] !== '' ? $row['grade'] : '—') ?></td>
                        <td>
                            <span class="badge <?= $row['is_published'] ? 'bg-success' : 'bg-secondary' ?>"><?= $row['is_published'] ? 'Published' : 'Draft' ?></span>
                            <?php if ($row['is_featured']): ?><span class="badge bg-warning text-dark">Featured</span><?php endif; ?>
                        </td>
                        <td class="small"><?= (int)$playStmt->fetchColumn() ?></td>
                        <td class="text-end text-nowrap">
                            <a href="<?= e(base_url('play-game.php?slug=' . urlencode($row['slug']))) ?>" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">Preview</a>
                            <?php if ($row['is_published']): ?>
                                <a href="<?= e(base_url('game.php?slug=' . urlencode($row['slug']))) ?>" class="btn btn-sm btn-outline-secondary" target="_blank">View</a>
                            <?php endif; ?>
                            <a href="<?= e(base_url('admin/game-edit.php?id=' . $row['id'])) ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                            <form method="post" action="<?= e(base_url('admin/games.php')) ?>" class="d-inline"
                                  onsubmit="return confirm('Delete this game? Its file is removed and this cannot be undone.');">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (!empty($result['items'])): ?>
        <div class="card-footer bg-white d-flex flex-wrap align-items-center gap-2">
            <span class="small text-secondary">With selected:</span>
            <button type="submit" form="bulk-form" name="bulk" value="publish" class="btn btn-sm btn-outline-success">Publish</button>
            <button type="submit" form="bulk-form" name="bulk" value="unpublish" class="btn btn-sm btn-outline-secondary">Move to drafts</button>
        </div>
    <?php endif; ?>
</div>

<div class="mt-4"><?= render_pagination($result['page'], $result['total_pages']) ?></div>

<script>
    // Upload the chosen files one per request, with a live progress list.
    (function () {
        var form = document.getElementById('game-upload-form');
        if (!form || !window.fetch || !window.FormData) { return; }
        var maxBytes = parseInt(form.dataset.maxBytes, 10);
        var maxFiles = parseInt(form.dataset.maxFiles, 10);
        var box = document.getElementById('upload-progress');
        var bar = box.querySelector('.progress-bar');
        var list = box.querySelector('ul');

        function line(icon, cls, text) {
            var li = document.createElement('li');
            li.className = cls;
            li.textContent = icon + ' ' + text;
            list.appendChild(li);
        }

        form.addEventListener('submit', async function (event) {
            var files = Array.prototype.slice.call(form.querySelector('#game_files').files);
            if (!files.length) { return; }
            event.preventDefault();
            if (files.length > maxFiles) {
                alert('Please choose at most ' + maxFiles + ' files at a time.');
                return;
            }
            var button = form.querySelector('button[type=submit]');
            button.disabled = true;
            box.hidden = false;
            list.innerHTML = '';
            var ok = 0;

            for (var i = 0; i < files.length; i++) {
                var file = files[i];
                if (file.size > maxBytes) {
                    line('✗', 'text-danger', file.name + ' is larger than the server allows.');
                } else {
                    var data = new FormData(form);
                    data.delete('game_files[]');
                    data.append('game_files[]', file, file.name);
                    try {
                        var res = await fetch(form.getAttribute('action'), { method: 'POST', body: data, headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' });
                        var json = await res.json();
                        (json.created || []).forEach(function (title) { ok++; line('✓', 'text-success', title); });
                        (json.errors || []).forEach(function (msg) { line('✗', 'text-danger', json.too_large ? file.name + ' ' + msg : msg); });
                    } catch (e) {
                        line('✗', 'text-danger', file.name + ' failed to upload. Please try again.');
                    }
                }
                var pct = Math.round((i + 1) / files.length * 100);
                bar.style.width = pct + '%';
                box.querySelector('.progress').setAttribute('aria-valuenow', pct);
            }

            if (ok === files.length) {
                line('', 'fw-semibold mt-2', 'All ' + ok + ' uploaded. Refreshing the list…');
                setTimeout(function () { window.location.reload(); }, 1200);
            } else {
                line('', 'fw-semibold mt-2', ok + ' of ' + files.length + ' uploaded. Fix the files marked ✗ and upload them again.');
                var again = document.createElement('button');
                again.type = 'button';
                again.className = 'btn btn-sm btn-outline-primary mt-2';
                again.textContent = 'Refresh list';
                again.addEventListener('click', function () { window.location.reload(); });
                box.appendChild(again);
                button.disabled = false;
            }
        });
    })();

    (function () {
        var all = document.getElementById('select-all');
        if (!all) { return; }
        all.addEventListener('change', function () {
            document.querySelectorAll('.game-select').forEach(function (box) { box.checked = all.checked; });
        });
    })();
</script>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
