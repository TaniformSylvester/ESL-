<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/games-functions.php';

$slug = trim((string)($_GET['slug'] ?? ''));
$game = $slug !== '' ? get_game_by_slug($slug) : null;

if (!$game) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$relatedGames = get_related_games($game, 3);

$pageTitle = $game['title'] . ' | ' . $game['grade'] . ' ' . $game['subject'] . ' Game';
$pageDescription = $game['title'] . ' — ' . $game['short_description'] . ' Free to play on any device, no login required.';

$breadcrumbSchema = [
    '@context'        => 'https://schema.org',
    '@type'           => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Games', 'item' => base_url('games.php')],
        ['@type' => 'ListItem', 'position' => 2, 'name' => $game['title'], 'item' => base_url('game.php?slug=' . rawurlencode($game['slug']))],
    ],
];

require_once __DIR__ . '/includes/header.php';
?>
<script type="application/ld+json"><?= json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES) ?></script>

<div class="container py-5">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="<?= e(base_url('games.php')) ?>">Games</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= e($game['title']) ?></li>
        </ol>
    </nav>

    <?php $subjectKey = game_subject_key($game['subject'] ?? ''); ?>
    <div class="row g-3 align-items-end mb-3">
        <div class="col-lg-8">
            <div class="d-flex flex-wrap gap-2 mb-2">
                <span class="badge badge-subject-<?= e($subjectKey) ?>"><?= e($game['subject']) ?></span>
                <span class="badge badge-grade"><?= e($game['grade']) ?></span>
                <span class="badge bg-light text-dark border"><?= e($game['topic']) ?></span>
                <span class="badge badge-free"><?= e($game['difficulty']) ?></span>
            </div>
            <h1 class="fw-bold mb-2"><?= e($game['title']) ?></h1>
            <p class="text-secondary mb-0"><?= e($game['short_description']) ?></p>
        </div>
        <div class="col-lg-4 d-flex gap-2">
            <a href="#play" class="btn btn-primary btn-lg flex-fill">
                <i class="fa-solid fa-play me-2"></i>Play
            </a>
            <a href="<?= e(game_embed_url($game)) ?>" target="_blank" rel="noopener" class="btn btn-outline-primary btn-lg flex-fill">
                <i class="fa-solid fa-up-right-from-square me-2"></i>Full Page
            </a>
        </div>
    </div>

    <div id="play" class="game-play-section mb-5">
        <h2 class="visually-hidden">Play <?= e($game['title']) ?></h2>
        <iframe src="<?= e(game_embed_url($game)) ?>" title="<?= e($game['title']) ?>" class="game-embed-frame" allow="fullscreen; autoplay" allowfullscreen></iframe>
        <p class="small text-secondary mt-2 mb-0">
            Tip: tap <strong>Full Page</strong> to open the game on its own, or use the <i class="fa-solid fa-expand"></i> button inside the game for classroom fullscreen.
            <?php if ($game['team_play'] ?? true): ?>
                Choose <strong>👥 2 Teams</strong> on the start screen to play team vs. team.
            <?php endif; ?>
        </p>
    </div>

    <div class="row g-4 mb-5">
        <?php if (!empty($game['what_students_practice'])): ?>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6 fw-bold text-uppercase text-secondary mb-2">What Students Practice</h2>
                    <ul class="mb-0">
                        <?php foreach ($game['what_students_practice'] as $item): ?>
                            <li><?= e($item) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php if (!empty($game['how_to_use'])): ?>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6 fw-bold text-uppercase text-secondary mb-2">How to Use in Class</h2>
                    <ol class="mb-0 ps-3">
                        <?php foreach ($game['how_to_use'] as $step): ?>
                            <li><?= e($step) ?></li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($relatedGames)): ?>
        <hr class="my-5">
        <h2 class="h4 fw-bold mb-4">More Games</h2>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4">
            <?php foreach ($relatedGames as $relatedGame): ?>
                <?php $game2 = $game; $game = $relatedGame; // game-card.php expects $game in scope ?>
                <?php include __DIR__ . '/includes/game-card.php'; ?>
                <?php $game = $game2; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
