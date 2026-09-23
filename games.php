<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/games-functions.php';

$games = get_all_games();

$pageTitle = 'Interactive Classroom Math Games for Teachers';
$pageDescription = 'Free interactive classroom games for teachers — play on a projector, interactive whiteboard, tablet or phone. No login, no setup, just open and play.';

$breadcrumbSchema = [
    '@context'        => 'https://schema.org',
    '@type'           => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Games', 'item' => base_url('games.php')],
    ],
];

require_once __DIR__ . '/includes/header.php';
?>
<script type="application/ld+json"><?= json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES) ?></script>

<section class="hero py-5">
    <div class="container py-4 text-center">
        <h1 class="display-6 fw-bold mb-3">Interactive Classroom Games</h1>
        <p class="lead mx-auto mb-0" style="max-width:640px;">Fun, simple games you can play with your whole class on a projector or interactive board — or hand straight to students on a tablet or phone. No login, no setup.</p>
    </div>
</section>

<div class="container py-5">
    <?php if (empty($games)): ?>
        <div class="alert alert-info">New games are on the way &mdash; check back soon.</div>
    <?php else: ?>
        <?php
            $subjectCounts = [];
            foreach ($games as $g) {
                $subjectCounts[$g['subject']] = ($subjectCounts[$g['subject']] ?? 0) + 1;
            }
        ?>
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <h2 class="h4 fw-bold mb-0">All Games</h2>
            <div class="game-filter d-flex flex-wrap gap-2" role="group" aria-label="Filter games by subject">
                <button type="button" class="btn btn-sm btn-primary" data-filter="all" aria-pressed="true">All (<?= count($games) ?>)</button>
                <?php foreach ($subjectCounts as $subject => $count): ?>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-filter="<?= e(game_subject_key($subject)) ?>" aria-pressed="false"><?= e($subject) ?> (<?= (int)$count ?>)</button>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4 mb-5" id="gamesGrid">
            <?php foreach ($games as $game): ?>
                <?php include __DIR__ . '/includes/game-card.php'; ?>
            <?php endforeach; ?>
        </div>
        <script>
            (function () {
                var buttons = document.querySelectorAll('.game-filter [data-filter]');
                var cards = document.querySelectorAll('#gamesGrid > [data-subject]');
                buttons.forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var filter = btn.getAttribute('data-filter');
                        buttons.forEach(function (b) {
                            var on = b === btn;
                            b.setAttribute('aria-pressed', on ? 'true' : 'false');
                            b.classList.toggle('btn-primary', on);
                            b.classList.toggle('btn-outline-primary', !on);
                        });
                        cards.forEach(function (card) {
                            card.classList.toggle('d-none', filter !== 'all' && card.getAttribute('data-subject') !== filter);
                        });
                    });
                });
            })();
        </script>
    <?php endif; ?>

    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 p-md-5">
                    <h2 class="h5 fw-bold mb-3">Built for Real Classrooms</h2>
                    <div class="row g-4">
                        <div class="col-md-4">
                            <i class="fa-solid fa-display fa-2x text-primary mb-2"></i>
                            <p class="fw-bold mb-1 small text-uppercase text-secondary">Projector-Ready</p>
                            <p class="small text-secondary mb-0">Large text and big buttons, readable from the back of the room.</p>
                        </div>
                        <div class="col-md-4">
                            <i class="fa-solid fa-mobile-screen fa-2x text-primary mb-2"></i>
                            <p class="fw-bold mb-1 small text-uppercase text-secondary">Works Everywhere</p>
                            <p class="small text-secondary mb-0">Phones, tablets, laptops and interactive whiteboards — no app to install.</p>
                        </div>
                        <div class="col-md-4">
                            <i class="fa-solid fa-bolt fa-2x text-primary mb-2"></i>
                            <p class="fw-bold mb-1 small text-uppercase text-secondary">No Setup</p>
                            <p class="small text-secondary mb-0">No login or student accounts &mdash; open a game and start playing immediately.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
