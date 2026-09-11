<?php
/**
 * Renders one game card. Expects $game (one entry from get_all_games()) in
 * scope. Reused by the homepage Games section and games.php's hub grid.
 */
$difficultyBadgeClass = match ($game['difficulty'] ?? '') {
    'Easy'     => 'badge-free',
    'Advanced' => 'badge-members',
    default    => 'bg-light text-dark border',
};
?>
<div class="col">
    <div class="card game-card shadow-sm h-100">
        <a href="<?= e(base_url('game.php?slug=' . urlencode($game['slug']))) ?>" class="text-decoration-none text-reset">
            <div class="game-card-thumb d-flex align-items-center justify-content-center">
                <?php if (!empty($game['thumbnail'])): ?>
                    <img src="<?= e($game['thumbnail']) ?>" class="card-img-top" alt="<?= e($game['title']) ?>" loading="lazy">
                <?php else: ?>
                    <i class="fa-solid fa-gamepad fa-3x text-white"></i>
                <?php endif; ?>
            </div>
        </a>
        <div class="card-body d-flex flex-column">
            <div class="d-flex flex-wrap gap-1 mb-2">
                <span class="badge bg-light text-dark border"><?= e($game['subject']) ?></span>
                <span class="badge bg-light text-dark border"><?= e($game['grade']) ?></span>
                <span class="badge <?= e($difficultyBadgeClass) ?>"><?= e($game['difficulty']) ?></span>
            </div>
            <h3 class="h6 fw-bold mb-1">
                <a class="text-decoration-none text-reset" href="<?= e(base_url('game.php?slug=' . urlencode($game['slug']))) ?>">
                    <?= e($game['title']) ?>
                </a>
            </h3>
            <p class="small text-secondary flex-grow-1"><?= e($game['short_description']) ?></p>
            <a href="<?= e(base_url('game.php?slug=' . urlencode($game['slug']))) ?>" class="btn btn-primary btn-sm mt-2">
                <i class="fa-solid fa-play me-1"></i>Play Game
            </a>
        </div>
    </div>
</div>
