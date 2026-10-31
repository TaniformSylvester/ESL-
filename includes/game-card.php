<?php
/**
 * Renders one game card. Expects $game (one entry from get_all_games()) in
 * scope. Reused by the homepage Games section and games.php's hub grid
 * (whose subject filter reads the column's data-subject).
 */
$difficultyBadgeClass = match ($game['difficulty'] ?? '') {
    'Easy'     => 'badge-free',
    'Advanced' => 'badge-members',
    default    => 'badge-neutral',
};
$subjectKey = game_subject_key($game['subject'] ?? '');
$gamePlayUrl = base_url('game.php?slug=' . urlencode($game['slug'])) . '#play';
?>
<div class="col" data-subject="<?= e($subjectKey) ?>">
    <article class="card game-card h-100">
        <a href="<?= e($gamePlayUrl) ?>" class="card-media game-card-thumb game-card-thumb-<?= e($subjectKey) ?> d-flex align-items-center justify-content-center" tabindex="-1" aria-hidden="true">
            <?php if (!empty($game['thumbnail'])): ?>
                <img src="<?= e($game['thumbnail']) ?>" alt="" loading="lazy" width="400" height="225">
            <?php else: ?>
                <i class="fa-solid fa-gamepad fa-3x text-white"></i>
            <?php endif; ?>
        </a>
        <div class="card-body d-flex flex-column">
            <div class="d-flex flex-wrap gap-1 mb-2">
                <span class="badge badge-subject-<?= e($subjectKey) ?>"><?= e($game['subject']) ?></span>
                <span class="badge badge-grade"><?= e($game['grade']) ?></span>
                <span class="badge <?= e($difficultyBadgeClass) ?>"><?= e($game['difficulty']) ?></span>
            </div>
            <h3 class="h6 mb-1">
                <a class="card-title-link" href="<?= e($gamePlayUrl) ?>"><?= e($game['title']) ?></a>
            </h3>
            <p class="small text-secondary flex-grow-1"><?= e($game['short_description']) ?></p>
            <a href="<?= e($gamePlayUrl) ?>" class="btn btn-accent btn-sm mt-2 game-play-btn">
                <i class="fa-solid fa-play me-1" aria-hidden="true"></i>Play Game<span class="visually-hidden">: <?= e($game['title']) ?></span>
            </a>
        </div>
    </article>
</div>
