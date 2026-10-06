<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/video-functions.php';
require_once __DIR__ . '/includes/subject-functions.php';
require_once __DIR__ . '/includes/seo-functions.php';

$filters = [
    'search'     => trim((string)($_GET['search'] ?? '')),
    'subject_id' => (int)($_GET['subject_id'] ?? 0),
    'grade'      => trim((string)($_GET['grade'] ?? '')),
];

$page = max(1, (int)($_GET['page'] ?? 1));
$result = get_published_videos($filters, $page, VIDEOS_PER_PAGE);
$subjects = get_all_subjects();

$activeSubject = $filters['subject_id'] > 0 ? get_subject_by_id($filters['subject_id']) : null;
$listingSeo = generate_videos_listing_seo($filters, $activeSubject, (int)$result['total']);

$pageTitle = $listingSeo['title'];
$pageDescription = $listingSeo['description'];
$pageRobots = $listingSeo['noindex'] ? 'noindex, follow' : 'index, follow';

require_once __DIR__ . '/includes/header.php';
?>
<div class="container py-5">
    <h1 class="fw-bold mb-1"><?= e($listingSeo['h1']) ?></h1>
    <p class="text-secondary mb-4">
        <?= (int)$result['total'] ?> video<?= $result['total'] === 1 ? '' : 's' ?> found
    </p>

    <form method="get" action="<?= e(base_url('videos.php')) ?>" class="row g-2 mb-4">
        <div class="col-lg-5 col-md-6">
            <input type="text" name="search" class="form-control" placeholder="Search title, topic&hellip;" value="<?= e($filters['search']) ?>">
        </div>
        <div class="col-lg-3 col-md-6">
            <select name="subject_id" class="form-select" onchange="this.form.submit()">
                <option value="">All Subjects</option>
                <?php foreach ($subjects as $subjectOption): ?>
                    <option value="<?= (int)$subjectOption['id'] ?>" <?= $filters['subject_id'] === (int)$subjectOption['id'] ? 'selected' : '' ?>><?= e($subjectOption['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-3 col-md-6">
            <select name="grade" class="form-select">
                <option value="">All Grades</option>
                <?php foreach (GRADE_LEVELS as $grade): ?>
                    <option value="<?= e($grade) ?>" <?= $filters['grade'] === $grade ? 'selected' : '' ?>><?= e($grade) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-1 col-md-6 d-grid">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i></button>
        </div>
    </form>

    <?php if (empty($result['items'])): ?>
        <div class="alert alert-info">No videos match your search yet. Try different filters, or check back soon &mdash; new videos are added regularly.</div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4 mb-4">
            <?php foreach ($result['items'] as $video): ?>
                <?php include __DIR__ . '/includes/video-card.php'; ?>
            <?php endforeach; ?>
        </div>

        <?= render_pagination($result['page'], $result['total_pages']) ?>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
