<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/resource-functions.php';
require_once __DIR__ . '/includes/favorites-functions.php';
require_once __DIR__ . '/includes/subject-functions.php';
require_once __DIR__ . '/includes/guide-functions.php';
require_once __DIR__ . '/includes/review-functions.php';
require_once __DIR__ . '/includes/teaching-demos.php';

$featuredResources = get_featured_resources(6);
$freeResources = get_free_resources(6);
$categoriesGrouped = get_categories_grouped();
$subjects = get_all_subjects();
$recentGuides = get_recent_guides(3);
$featuredReviews = get_featured_site_reviews(3);
$featuredDemo = get_featured_teaching_demo();

$subjectIcons = [
    'esl'     => 'fa-comments',
    'math'    => 'fa-calculator',
    'science' => 'fa-flask',
];

$pageTitle = 'Teaching Resources & Classroom Guidance for Teachers';
$pageDescription = SITE_DESCRIPTION;
require_once __DIR__ . '/includes/header.php';
?>

<section class="hero py-5">
    <div class="container py-5">
        <div class="row align-items-center">
            <div class="col-lg-8 mx-auto text-center">
                <h1 class="display-5 fw-bold mb-3">Ready-to-Teach Resources and Real Classroom Guidance</h1>
                <p class="lead mb-4"><?= e(SITE_NAME) ?> is a resource platform for teachers &mdash; ESL/EFL teachers, primary teachers, international-school teachers, tutors, and homeschool educators anywhere in the world. Download English/ESL resources from Kindergarten to Grade 10, plus Math and Science for Grades 1&ndash;6, and get practical how-to-teach guidance in the Teacher Hub.</p>
                <div class="d-flex flex-column flex-sm-row justify-content-center gap-3">
                    <a href="<?= e(base_url('resources.php')) ?>" class="btn btn-light btn-lg px-4 fw-semibold">Explore Resources</a>
                    <a href="<?= e(base_url('teacher-hub.php')) ?>" class="btn btn-outline-light btn-lg px-4">Visit the Teacher Hub</a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if (!empty($subjects)): ?>
<section class="py-5 section-soft">
    <div class="container">
        <h2 class="h3 fw-bold text-center mb-5">What's Here: Browse by Subject</h2>
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

<?php if (!empty($recentGuides)): ?>
<section class="py-5 section-soft">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-5">
            <h2 class="h3 fw-bold mb-0">From the Teacher Hub</h2>
            <a href="<?= e(base_url('teacher-hub.php')) ?>" class="small">View All Guides &rarr;</a>
        </div>
        <p class="text-secondary mb-4" style="max-width:640px;">Beyond downloads, the Teacher Hub has practical how-to-teach guides &mdash; classroom activities, common student difficulties, and differentiation ideas.</p>
        <div class="row row-cols-1 row-cols-sm-3 g-4 mb-4">
            <?php foreach ($recentGuides as $guide): ?>
                <div class="col">
                    <a href="<?= e(base_url('teacher-hub-guide.php?slug=' . urlencode($guide['slug']))) ?>" class="card shadow-sm border-0 h-100 text-decoration-none text-reset">
                        <div class="card-body">
                            <span class="badge bg-light text-dark border mb-2"><?= e(GUIDE_CATEGORIES[$guide['category']]) ?></span>
                            <h3 class="h6 fw-bold"><?= e($guide['title']) ?></h3>
                            <?php if (!empty($guide['summary'])): ?>
                                <p class="small text-secondary mb-0"><?= e($guide['summary']) ?></p>
                            <?php endif; ?>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <?php foreach (GUIDE_CATEGORIES as $categorySlug => $categoryLabel): ?>
                <a href="<?= e(base_url('teacher-hub.php#' . $categorySlug)) ?>" class="btn btn-sm btn-outline-secondary"><?= e($categoryLabel) ?></a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($featuredDemo): ?>
<section class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="h3 fw-bold mb-2">See TeachLuma in Action</h2>
            <p class="text-secondary mx-auto" style="max-width:640px;">Watch short teaching demos and see how TeachLuma resources can be used in a real classroom. Don't just download a resource &mdash; see how it can be taught, across ESL, Mathematics, and Science.</p>
        </div>
        <?php $demo = $featuredDemo; // teaching-demo-card.php expects $demo in scope ?>
        <?php require __DIR__ . '/includes/teaching-demo-card.php'; ?>
    </div>
</section>
<?php endif; ?>

<section class="py-5">
    <div class="container">
        <h2 class="h3 fw-bold text-center mb-5">How It Works</h2>
        <div class="row g-4 text-center">
            <div class="col-md-4">
                <div class="fw-bold fs-3 text-primary mb-2">1</div>
                <h3 class="h6 fw-bold">Find It. Download It.</h3>
                <p class="small text-secondary">Browse the free resource library and download instantly &mdash; no account required.</p>
            </div>
            <div class="col-md-4">
                <div class="fw-bold fs-3 text-primary mb-2">2</div>
                <h3 class="h6 fw-bold">Create a Free Account (Optional)</h3>
                <p class="small text-secondary">Register anytime to save favorites, write reviews, and track your download history.</p>
            </div>
            <div class="col-md-4">
                <div class="fw-bold fs-3 text-primary mb-2">3</div>
                <h3 class="h6 fw-bold">Upgrade to Pro (Optional)</h3>
                <p class="small text-secondary">Unlock members-only resources &mdash; send your payment and get approved, usually within a day.</p>
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
        <div class="row justify-content-center g-4">
            <div class="col-md-5">
                <div class="card border-0 shadow-sm text-center h-100">
                    <div class="card-body p-4">
                        <h2 class="h5 fw-bold mb-1">TeachLuma Pro</h2>
                        <p class="display-6 fw-bold text-primary mb-0"><?= format_currency(PRICE_MONTHLY) ?><span class="fs-6 text-secondary">/month</span></p>
                        <p class="text-secondary small mt-2 mb-3">Unlimited downloads of every members-only resource.</p>
                        <a href="<?= e(base_url('pricing.php')) ?>" class="btn btn-outline-primary px-4">See Full Pricing</a>
                    </div>
                </div>
            </div>
            <div class="col-md-5">
                <div class="card border-primary shadow text-center h-100" style="border-width:2px;">
                    <div class="card-body p-4">
                        <span class="badge bg-warning text-dark mb-2">🏆 BEST VALUE</span>
                        <h2 class="h5 fw-bold mb-1">Or Save with Annual</h2>
                        <p class="display-6 fw-bold text-primary mb-0"><?= format_currency(PRICE_ANNUAL) ?><span class="fs-6 text-secondary">/year</span></p>
                        <p class="text-secondary small mt-2 mb-3">Save <?= format_currency((PRICE_MONTHLY * 12) - PRICE_ANNUAL) ?>/year vs. paying monthly.</p>
                        <a href="<?= e(base_url('pricing.php')) ?>" class="btn btn-primary px-4">Become a Pro Teacher</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if (!empty($featuredReviews)): ?>
<section class="py-5 section-soft">
    <div class="container">
        <h2 class="h3 fw-bold text-center mb-5">What Teachers Are Saying</h2>
        <div class="row row-cols-1 row-cols-md-3 g-4">
            <?php foreach ($featuredReviews as $review): ?>
                <div class="col">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-warning mb-2">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fa-solid fa-star<?= $i > (int)$review['rating'] ? ' text-secondary opacity-25' : '' ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <p class="small mb-3">&ldquo;<?= e($review['review_text']) ?>&rdquo;</p>
                            <p class="small text-secondary mb-0">
                                &mdash; <?= e($review['first_name']) ?>, on
                                <a href="<?= e(base_url('resource.php?slug=' . urlencode($review['resource_slug']))) ?>"><?= e($review['resource_title']) ?></a>
                            </p>
                        </div>
                    </div>
                </div>
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
                                How many resources can I download for free?
                            </button>
                        </h3>
                        <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">Free resources are unlimited for everyone &mdash; no account required. Only members-only resources require a Teacher Pro membership.</div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h3 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                What do I get with TeachLuma Pro?
                            </button>
                        </h3>
                        <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">Pro members get unlimited downloads of members-only resources while their membership is active.</div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h3 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                What subjects does TeachLuma cover?
                            </button>
                        </h3>
                        <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">TeachLuma provides English and ESL resources from Kindergarten to Grade 10, plus Mathematics and Science resources for Grades 1&ndash;6.</div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h3 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                How do I pay?
                            </button>
                        </h3>
                        <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">Currently, TeachLuma uses manual PromptPay or bank-transfer payment. After submitting your payment details, our team reviews and approves your membership.</div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h3 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq5">
                                How long does approval take?
                            </button>
                        </h3>
                        <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">Usually within a day.</div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h3 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq6">
                                Can I cancel anytime?
                            </button>
                        </h3>
                        <div id="faq6" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">Yes. Membership renews manually, not automatically &mdash; if you don't submit another payment before your expiry date, your account simply reverts to the Free plan (still unlimited downloads of free resources) rather than being charged or locked out.</div>
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
        <a href="<?= e(base_url('register.php')) ?>" class="btn btn-light btn-lg px-4 fw-semibold">Become a Pro Teacher</a>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
