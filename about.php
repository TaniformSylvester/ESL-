<?php
require_once __DIR__ . '/includes/init.php';

$pageTitle = 'About Us';
$pageDescription = 'Learn about ' . SITE_NAME . '\'s mission to help ESL teachers save preparation time.';
require_once __DIR__ . '/includes/header.php';
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <h1 class="fw-bold mb-4">About <?= e(SITE_NAME) ?></h1>

            <p class="lead">Our mission is simple: help teachers save preparation time by providing ready-to-use ESL teaching materials designed for real classrooms.</p>

            <p>Every worksheet, lesson plan, and activity on <?= e(SITE_NAME) ?> is built with one goal in mind &mdash; being genuinely useful to a teacher standing in front of a class of primary school students. We focus on practical, classroom-tested resources rather than generic, mass-produced materials.</p>

            <h2 class="h4 fw-bold mt-5 mb-3">Who We're For</h2>
            <p><?= e(SITE_NAME) ?> was built primarily for English teachers in Thai primary schools, but any primary or elementary ESL teacher, anywhere in the world, is welcome to use our resources.</p>

            <h2 class="h4 fw-bold mt-5 mb-3">What Makes Us Different</h2>
            <ul>
                <li>Resources organized specifically for primary grade levels and ESL skill areas.</li>
                <li>A mix of lesson plans, worksheets, PowerPoints, flashcards, games and assessments &mdash; everything you need for a lesson in one place.</li>
                <li>New materials added regularly.</li>
                <li>A simple, affordable membership at <?= format_currency(SUBSCRIPTION_PRICE) ?>/month &mdash; no long-term contracts.</li>
            </ul>

            <h2 class="h4 fw-bold mt-5 mb-3">Save Time. Teach Better.</h2>
            <p>That's our promise: less time searching for or creating materials from scratch, more time actually teaching &mdash; and enjoying it.</p>

            <div class="text-center mt-5">
                <a href="<?= e(base_url('resources.php')) ?>" class="btn btn-primary px-4">Explore Resources</a>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
