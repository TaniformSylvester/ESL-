<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/guide-functions.php';

$guidesGrouped = get_all_guides_grouped();

$pageTitle = 'Teacher Hub';
$pageDescription = 'Practical teaching guidance for ESL, Math, Science and classroom practice — how to teach specific topics, classroom activities, and tips from ' . SITE_NAME . '.';
require_once __DIR__ . '/includes/header.php';
?>
<div class="container py-5">
    <div class="text-center mb-5">
        <h1 class="fw-bold mb-2">Teacher Hub</h1>
        <p class="text-secondary mx-auto" style="max-width:640px;">
            Practical guidance for the classroom — how to teach specific topics, activity ideas, and tips to go along with
            <?= e(SITE_NAME) ?>'s downloadable resources.
        </p>
    </div>

    <?php if (empty($guidesGrouped)): ?>
        <div class="alert alert-info">Guides are being added — check back soon.</div>
    <?php endif; ?>

    <?php foreach ($guidesGrouped as $category => $guides): ?>
        <section class="mb-5" id="<?= e($category) ?>">
            <h2 class="h4 fw-bold mb-4"><?= e(GUIDE_CATEGORIES[$category]) ?></h2>
            <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4">
                <?php foreach ($guides as $guide): ?>
                    <?php include __DIR__ . '/includes/guide-card.php'; ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
