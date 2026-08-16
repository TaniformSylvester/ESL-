<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/resource-functions.php';
require_once __DIR__ . '/includes/favorites-functions.php';
require_once __DIR__ . '/includes/subject-functions.php';

$featuredResources = get_featured_resources(6);
$freeResources = get_free_resources(6);
$categoriesGrouped = get_categories_grouped();
$subjects = get_all_subjects();

$subjectIcons = [
    'esl'     => 'fa-comments',
    'math'    => 'fa-calculator',
    'science' => 'fa-flask',
];

$pageTitle = 'Home';
$pageDescription = SITE_DESCRIPTION;
require_once __DIR__ . '/includes/header.php';
?>

<section class="hero py-5">
    <div class="container py-5">
        <div class="row align-items-center">
            <div class="col-lg-8 mx-auto text-center">
                <h1 class="display-5 fw-bold mb-3">Ready-to-Teach ESL, Math &amp; Science Resources for Teachers</h1>
                <p class="lead mb-4">Lesson plans, worksheets, PowerPoints, games and classroom resources for Kindergarten through Grade 9 — all in one place.</p>
                <div class="d-flex flex-column flex-sm-row justify-content-center gap-3">
                    <a href="<?= e(base_url('resources.php')) ?>" class="btn btn-light btn-lg px-4 fw-semibold">Explore Resources</a>
                    <a href="<?= e(base_url('pricing.php')) ?>" class="btn btn-outline-light btn-lg px-4">Join for <?= format_currency(SUBSCRIPTION_PRICE) ?>/month</a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if (!empty($subjects)): ?>
<section class="py-5 section-soft">
    <div class="container">
        <h2 class="h3 fw-bold text-center mb-5">Browse by Subject</h2>
        <div class="row g-4 justify-content-center">
            <?php foreach ($subjects as $subject): ?>
                <div class="col-md-4">
                    <a href="<?= e(base_url('resources.php?subject_id=' . (int)$subject['id'])) ?>" class="card shadow-sm border-0 text-center text-decoration-none h-100">
                        <div class="card-body p-4">
                            <i class="fa-solid <?= e($subjectIcons[$subject['slug']] ?? 'fa-book') ?> fa-2x text-primary mb-3"></i>
                            <h3 class="h5 fw-bold text-dark mb-1"><?= e($subject['name']) ?></h3>
                            <p class="small text-secondary mb-0"><?= e($subject['min_grade']) ?> &ndash; <?= e($subject['max_grade']) ?></p>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="py-5">
    <div class="container">
        <h2 class="h3 fw-bold text-center mb-5">Why Teachers Choose <?= e(SITE_NAME) ?></h2>
        <div class="row g-4 text-center">
            <div class="col-md-3">
                <i class="fa-solid fa-clock fa-2x text-primary mb-3"></i>
                <h3 class="h6 fw-bold">Save Preparation Time</h3>
                <p class="small text-secondary">Ready-to-use materials so you can spend less time planning and more time teaching.</p>
            </div>
            <div class="col-md-3">
                <i class="fa-solid fa-child-reaching fa-2x text-primary mb-3"></i>
                <h3 class="h6 fw-bold">Built for Real Classrooms</h3>
                <p class="small text-secondary">Practical resources designed for real ESL, Math and Science classes, not generic content.</p>
            </div>
            <div class="col-md-3">
                <i class="fa-solid fa-layer-group fa-2x text-primary mb-3"></i>
                <h3 class="h6 fw-bold">Every Format You Need</h3>
                <p class="small text-secondary">Lesson plans, worksheets, PowerPoints, flashcards, games and assessments.</p>
            </div>
            <div class="col-md-3">
                <i class="fa-solid fa-arrows-rotate fa-2x text-primary mb-3"></i>
                <h3 class="h6 fw-bold">New Resources Regularly</h3>
                <p class="small text-secondary">The resource library keeps growing, so there's always something fresh to use.</p>
            </div>
        </div>
    </div>
</section>

<?php if (!empty($categoriesGrouped)): ?>
<section class="py-5 section-soft">
    <div class="container">
        <h2 class="h3 fw-bold text-center mb-5">Browse by Category</h2>
        <div class="row g-4">
            <?php foreach ($categoriesGrouped as $groupName => $categories): ?>
                <div class="col-md-6">
                    <h3 class="h6 fw-bold text-secondary text-uppercase small mb-3"><?= e($groupName) ?></h3>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($categories as $category): ?>
                            <a href="<?= e(base_url('resources.php?category_id=' . $category['id'])) ?>" class="btn btn-sm btn-outline-secondary">
                                <?= e($category['name']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-5">
            <h2 class="h3 fw-bold mb-0">Featured Resources</h2>
            <a href="<?= e(base_url('resources.php')) ?>" class="small">View All &rarr;</a>
        </div>
        <?php if (empty($featuredResources)): ?>
            <p class="text-secondary">New resources are being added — check back soon!</p>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4">
                <?php foreach ($featuredResources as $resource): ?>
                    <?php include __DIR__ . '/includes/resource-card.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="py-5 section-soft">
    <div class="container">
        <h2 class="h3 fw-bold text-center mb-5">How It Works</h2>
        <div class="row g-4 text-center">
            <div class="col-md-4">
                <div class="fw-bold fs-3 text-primary mb-2">1</div>
                <h3 class="h6 fw-bold">Create a Free Account</h3>
                <p class="small text-secondary">Register in a minute and browse the full resource library.</p>
            </div>
            <div class="col-md-4">
                <div class="fw-bold fs-3 text-primary mb-2">2</div>
                <h3 class="h6 fw-bold">Subscribe for <?= format_currency(SUBSCRIPTION_PRICE) ?>/month</h3>
                <p class="small text-secondary">Send your payment and get approved &mdash; usually within a day.</p>
            </div>
            <div class="col-md-4">
                <div class="fw-bold fs-3 text-primary mb-2">3</div>
                <h3 class="h6 fw-bold">Download &amp; Teach</h3>
                <p class="small text-secondary">Unlimited access to every members-only resource, all month long.</p>
            </div>
        </div>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="card border-primary shadow-sm text-center">
                    <div class="card-body p-4">
                        <h2 class="h5 fw-bold mb-1">Teacher Membership</h2>
                        <p class="display-6 fw-bold text-primary mb-0"><?= format_currency(SUBSCRIPTION_PRICE) ?><span class="fs-6 text-secondary">/month</span></p>
                        <p class="text-secondary small mt-2 mb-3">Unlimited access to all members-only resources.</p>
                        <a href="<?= e(base_url('pricing.php')) ?>" class="btn btn-primary px-4">See Full Pricing Details</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if (!empty($freeResources)): ?>
<section class="py-5 section-soft">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-5">
            <h2 class="h3 fw-bold mb-0">Free Resources</h2>
            <a href="<?= e(base_url('resources.php?access=free')) ?>" class="small">View All &rarr;</a>
        </div>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4">
            <?php foreach ($freeResources as $resource): ?>
                <?php include __DIR__ . '/includes/resource-card.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="py-5">
    <div class="container">
        <h2 class="h3 fw-bold text-center mb-5">Frequently Asked Questions</h2>
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="accordion" id="faqAccordion">
                    <div class="accordion-item">
                        <h3 class="accordion-header">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                What do I get with a membership?
                            </button>
                        </h3>
                        <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">Unlimited downloads of every members-only lesson plan, worksheet, PowerPoint, flashcard set, game and assessment in the library, for as long as your membership is active.</div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h3 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                How do I pay?
                            </button>
                        </h3>
                        <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">Currently membership is activated through a simple bank transfer or PromptPay payment that our team confirms manually, usually within a day. Details are shown once you register.</div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h3 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                Can I cancel anytime?
                            </button>
                        </h3>
                        <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">Yes. Your membership simply isn't renewed for the following month &mdash; there's no lock-in contract.</div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h3 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                Are any resources free?
                            </button>
                        </h3>
                        <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">Yes, a selection of resources is always free to download, no account required for browsing.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="hero py-5">
    <div class="container py-4 text-center">
        <h2 class="h3 fw-bold mb-3">Ready to spend less time preparing and more time teaching?</h2>
        <a href="<?= e(base_url('register.php')) ?>" class="btn btn-light btn-lg px-4 fw-semibold">Join for <?= format_currency(SUBSCRIPTION_PRICE) ?>/month</a>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
