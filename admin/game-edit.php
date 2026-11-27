<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-functions.php';
require_once __DIR__ . '/../includes/upload-functions.php';
require_once __DIR__ . '/../includes/games-functions.php';

require_admin();
$admin = current_user();

$row = get_uploaded_game_row((int)($_GET['id'] ?? $_POST['id'] ?? 0));
if (!$row) {
    flash_set('error', 'That game was not found.');
    redirect('admin/games.php');
}

$errors = [];
$old = $row;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    flash_set('error', 'That file was too large to upload (limit ' . format_file_size(uploaded_game_size_limit()) . ').');
    redirect('admin/game-edit.php?id=' . (int)$row['id']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $result = update_uploaded_game($row, $_POST, $_FILES['game_file'] ?? [], $_FILES['thumbnail'] ?? []);

    if ($result['success']) {
        log_admin_action($admin['id'], 'edit_game', 'Edited game "' . clean_input($_POST['title'] ?? '') . '"');
        flash_set('success', 'Game saved.');
        redirect('admin/games.php');
    }

    $errors = $result['errors'];
    $old = array_merge($row, array_intersect_key($_POST, array_flip([
        'title', 'slug', 'subject', 'grade', 'topic', 'difficulty', 'short_description', 'what_students_practice', 'how_to_use',
    ])));
    $old['is_published'] = !empty($_POST['is_published']);
    $old['is_featured'] = !empty($_POST['is_featured']);
}

$invalid = static fn(string $field): string => isset($errors[$field]) ? 'is-invalid' : '';
$feedback = static function (string $field) use ($errors): void {
    if (isset($errors[$field])) {
        echo '<div class="invalid-feedback d-block">' . e($errors[$field]) . '</div>';
    }
};

$pageTitle = 'Edit Game';
require_once __DIR__ . '/../includes/admin-header.php';
?>
<p class="mb-3"><a href="<?= e(base_url('admin/games.php')) ?>">&larr; All games</a></p>

<?php if ($errors): ?>
    <div class="alert alert-danger">Please fix the highlighted fields.</div>
<?php endif; ?>

<form method="post" action="<?= e(base_url('admin/game-edit.php?id=' . (int)$row['id'])) ?>" enctype="multipart/form-data" novalidate>
    <?php csrf_field(); ?>
    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold" for="title">Title</label>
                            <input type="text" class="form-control <?= $invalid('title') ?>" id="title" name="title" value="<?= e($old['title']) ?>" maxlength="150" required>
                            <?php $feedback('title'); ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="slug">URL slug</label>
                            <input type="text" class="form-control" id="slug" name="slug" value="<?= e($old['slug']) ?>" maxlength="100" aria-describedby="slug-help">
                            <div class="form-text" id="slug-help">game.php?slug=<strong><?= e($row['slug']) ?></strong></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="subject">Subject</label>
                            <select class="form-select" id="subject" name="subject">
                                <?php foreach (GAME_SUBJECTS as $s): ?>
                                    <option <?= $old['subject'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="grade">Grade</label>
                            <input type="text" class="form-control" id="grade" name="grade" value="<?= e($old['grade']) ?>" maxlength="50" placeholder="e.g. Grade 1–3">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="difficulty">Difficulty</label>
                            <select class="form-select" id="difficulty" name="difficulty">
                                <?php foreach (GAME_DIFFICULTIES as $d): ?>
                                    <option <?= $old['difficulty'] === $d ? 'selected' : '' ?>><?= e($d) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold" for="topic">Topic</label>
                            <input type="text" class="form-control" id="topic" name="topic" value="<?= e($old['topic']) ?>" maxlength="150" placeholder="e.g. Animals vocabulary">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold" for="short_description">Short description</label>
                            <input type="text" class="form-control" id="short_description" name="short_description" value="<?= e($old['short_description']) ?>" maxlength="255" placeholder="One sentence shown on the game card">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="what_students_practice">What students practice <span class="text-secondary fw-normal">(one per line)</span></label>
                            <textarea class="form-control" id="what_students_practice" name="what_students_practice" rows="5"><?= e((string)$old['what_students_practice']) ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="how_to_use">How to use in class <span class="text-secondary fw-normal">(one step per line)</span></label>
                            <textarea class="form-control" id="how_to_use" name="how_to_use" rows="5" placeholder="Leave blank for the standard classroom steps"><?= e((string)$old['how_to_use']) ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_published" name="is_published" value="1" <?= $old['is_published'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_published">Published on the Games page</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_featured" name="is_featured" value="1" <?= $old['is_featured'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_featured">Featured</label>
                    </div>
                    <hr>
                    <a href="<?= e(base_url('play-game.php?slug=' . urlencode($row['slug']))) ?>" target="_blank" rel="noopener" class="btn btn-outline-secondary w-100">
                        <i class="fa-solid fa-play me-1" aria-hidden="true"></i>Preview game
                    </a>
                </div>
            </div>

            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">
                    <label class="form-label fw-semibold" for="thumbnail">Thumbnail <span class="text-secondary fw-normal">(16:9 works best)</span></label>
                    <?php if (!empty($row['thumbnail'])): ?>
                        <img src="<?= e(UPLOAD_THUMBNAIL_URL . '/' . rawurlencode($row['thumbnail'])) ?>" alt="Current thumbnail" class="img-fluid rounded mb-2">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="remove_thumbnail" name="remove_thumbnail" value="1">
                            <label class="form-check-label small" for="remove_thumbnail">Remove thumbnail</label>
                        </div>
                    <?php endif; ?>
                    <input type="file" class="form-control <?= $invalid('thumbnail') ?>" id="thumbnail" name="thumbnail" accept=".jpg,.jpeg,.png,.webp">
                    <?php $feedback('thumbnail'); ?>
                    <div class="form-text">Without one, the card shows a game-controller tile in the subject's color.</div>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <label class="form-label fw-semibold" for="game_file">Replace game file</label>
                    <p class="small text-secondary mb-2">Current: <?= e($row['original_filename']) ?> (<?= e(format_file_size((int)$row['file_size'])) ?>)</p>
                    <input type="file" class="form-control <?= $invalid('game_file') ?>" id="game_file" name="game_file" accept=".html,.htm,text/html">
                    <?php $feedback('game_file'); ?>
                    <div class="form-text">Upload a fixed or updated version. The game keeps its page, link and play count.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary">Save Game</button>
        <a href="<?= e(base_url('admin/games.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
