<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/resource-functions.php';
require_once __DIR__ . '/includes/favorites-functions.php';
require_once __DIR__ . '/includes/subject-functions.php';
require_once __DIR__ . '/includes/review-functions.php';
require_once __DIR__ . '/includes/teaching-demos.php';

$freeResources = attach_rating_summaries(get_free_resources(6));
$subjects = get_all_subjects();
$mathSubject = get_subject_by_slug('math');
$featuredReviews = get_featured_site_reviews(3);
$featuredDemo = get_featured_teaching_demo();

// Real download data decides which section we show — never fabricated.
// A small minimum threshold avoids calling a couple of stray downloads
// "Popular"; below that, real recency ("Latest Resources") is the
// honest thing to show instead.
$popularCandidates = get_popular_resources(6);
$showPopular = !empty($popularCandidates) && (int)$popularCandidates[0]['download_count'] >= 5;
$secondaryResourcesTitle = $showPopular ? 'Popular Resources' : 'Latest Resources';
$secondaryResources = attach_rating_summaries($showPopular ? $popularCandidates : get_featured_resources(6));

$subjectIcons = [
    'esl'     => 'fa-comments',
    'math'    => 'fa-calculator',
    'science' => 'fa-flask',
];
$subjectDescriptions = [
    'esl'     => 'Vocabulary, speaking, phonics and classroom activities.',
    'math'    => 'Printable and classroom-ready math activities.',
    'science' => 'Simple science resources and activities for young learners.',
];

$pageTitle = 'Ready-to-Use Teaching Resources for Busy Teachers';
$pageDescription = 'Find practical ESL, Mathematics and Science resources, classroom activities and interactive learning games for busy teachers.';
require_once __DIR__ . '/includes/header.php';
?>

<section class="hero py-5">
    <div class="container py-4">
        <div class="row align-items-center">
            <div class="col-lg-8 mx-auto text-center">
                <h1 class="display-5 fw-bold mb-3">Ready-to-Use Teaching Resources for Busy Teachers</h1>
                <p class="lead mb-3">Find practical ESL, Mathematics and Science resources, classroom activities and interactive learning games.</p>
                <div class="d-flex flex-column flex-sm-row justify-content-center gap-3 mb-3">
                    <a href="<?= e(base_url('resources.php?access=free')) ?>" class="btn btn-light btn-lg px-4 fw-semibold">Browse Free Resources</a>
                    <a href="<?= e(base_url('') . '#play-and-learn') ?>" class="btn btn-outline-light btn-lg px-4">Play &amp; Learn</a>
                </div>
                <p class="small mb-0" style="opacity:0.85;">Free resources &bull; Easy downloads &bull; Classroom-ready materials</p>
            </div>
        </div>
    </div>
</section>

<section class="py-5" id="play-and-learn">
    <div class="container">
        <div class="text-center mb-4">
            <h2 class="h3 fw-bold mb-2">🎮 Play &amp; Learn</h2>
            <p class="text-secondary mx-auto" style="max-width:640px;">Quick educational games students can play while practicing essential skills.</p>
        </div>
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="card shadow-sm border-0 number-challenge" id="numberChallenge">
                    <div class="card-body p-4 p-md-5">
                        <div class="d-flex justify-content-between align-items-center mb-4 small text-secondary fw-bold text-uppercase">
                            <span>Number Challenge</span>
                            <span aria-live="polite"><span id="ncQuestionCounter">Question 1/10</span> &middot; Score: <span id="ncScore">0</span></span>
                        </div>

                        <div id="ncGameArea">
                            <p class="text-center display-6 fw-bold mb-4" id="ncQuestion">&nbsp;</p>
                            <div class="row row-cols-1 row-cols-sm-3 g-2 justify-content-center">
                                <div class="col"><button type="button" class="btn btn-outline-primary w-100 nc-answer-btn"></button></div>
                                <div class="col"><button type="button" class="btn btn-outline-primary w-100 nc-answer-btn"></button></div>
                                <div class="col"><button type="button" class="btn btn-outline-primary w-100 nc-answer-btn"></button></div>
                            </div>
                            <p class="text-center fw-bold mt-3 mb-0" id="ncFeedback" aria-live="polite">&nbsp;</p>
                        </div>

                        <div id="ncResult" class="text-center d-none">
                            <p class="display-6 mb-2">🎉</p>
                            <h3 class="h5 fw-bold mb-3" id="ncResultText">&nbsp;</h3>
                            <div class="d-flex flex-column flex-sm-row justify-content-center gap-2">
                                <button type="button" class="btn btn-outline-primary" id="ncPlayAgain">Play Again</button>
                                <?php if ($mathSubject): ?>
                                    <a href="<?= e(base_url('resources.php?subject_id=' . (int)$mathSubject['id'])) ?>" class="btn btn-primary">Explore Math Resources</a>
                                <?php else: ?>
                                    <a href="<?= e(base_url('resources.php')) ?>" class="btn btn-primary">Explore Math Resources</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-5 section-soft">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-5">
            <h2 class="h3 fw-bold mb-0">Free Teaching Resources</h2>
            <a href="<?= e(base_url('resources.php?access=free')) ?>" class="small">View All Free Resources &rarr;</a>
        </div>
        <p class="text-secondary mb-4" style="max-width:640px;">Ready-to-use classroom materials you can download and use today.</p>
        <?php if (empty($freeResources)): ?>
            <p class="text-secondary">New free resources are being added — check back soon!</p>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4">
                <?php foreach ($freeResources as $resource): ?>
                    <?php include __DIR__ . '/includes/resource-card.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if (!empty($subjects)): ?>
<section class="py-5">
    <div class="container">
        <h2 class="h3 fw-bold text-center mb-5">Browse by Subject</h2>
        <div class="row g-4 justify-content-center">
            <?php foreach ($subjects as $subject): ?>
                <div class="col-md-4">
                    <a href="<?= e(base_url('resources.php?subject_id=' . (int)$subject['id'])) ?>" class="card shadow-sm border-0 text-center text-decoration-none h-100">
                        <div class="card-body p-4">
                            <i class="fa-solid <?= e($subjectIcons[$subject['slug']] ?? 'fa-book') ?> fa-2x text-primary mb-3"></i>
                            <h3 class="h5 fw-bold text-dark mb-1"><?= e($subject['name']) ?></h3>
                            <p class="small text-secondary mb-0"><?= e($subjectDescriptions[$subject['slug']] ?? ($subject['min_grade'] . ' - ' . $subject['max_grade'])) ?></p>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($featuredDemo): ?>
<section class="py-5 section-soft">
    <div class="container">
        <div class="text-center mb-4">
            <h2 class="h3 fw-bold mb-2">See It in Action</h2>
            <p class="text-secondary mx-auto" style="max-width:600px;">Watch short teaching demonstrations and see how TeachLuma resources can be used in class.</p>
        </div>
        <?php $demo = $featuredDemo; // teaching-demo-card.php expects $demo in scope ?>
        <?php require __DIR__ . '/includes/teaching-demo-card.php'; ?>
    </div>
</section>
<?php endif; ?>

<section class="py-5">
    <div class="container">
        <h2 class="h3 fw-bold text-center mb-5">Why Teachers Use <?= e(SITE_NAME) ?></h2>
        <div class="row g-4 text-center">
            <div class="col-md-3">
                <i class="fa-solid fa-clock fa-2x text-primary mb-3"></i>
                <h3 class="h6 fw-bold">Ready to Use</h3>
                <p class="small text-secondary">Download classroom-ready materials without spending hours creating everything from scratch.</p>
            </div>
            <div class="col-md-3">
                <i class="fa-solid fa-chalkboard-user fa-2x text-primary mb-3"></i>
                <h3 class="h6 fw-bold">Teacher-Friendly</h3>
                <p class="small text-secondary">Resources are designed to be practical, clear and easy to use.</p>
            </div>
            <div class="col-md-3">
                <i class="fa-solid fa-gamepad fa-2x text-primary mb-3"></i>
                <h3 class="h6 fw-bold">Learn Through Play</h3>
                <p class="small text-secondary">Interactive activities and educational games help make learning more engaging.</p>
            </div>
            <div class="col-md-3">
                <i class="fa-solid fa-arrows-rotate fa-2x text-primary mb-3"></i>
                <h3 class="h6 fw-bold">Growing Library</h3>
                <p class="small text-secondary">New ESL, Mathematics and Science resources are continually being added.</p>
            </div>
        </div>
    </div>
</section>

<?php if (!empty($secondaryResources)): ?>
<section class="py-5 section-soft">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-5">
            <h2 class="h3 fw-bold mb-0"><?= e($secondaryResourcesTitle) ?></h2>
            <a href="<?= e(base_url('resources.php')) ?>" class="small">View All Resources &rarr;</a>
        </div>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4">
            <?php foreach ($secondaryResources as $resource): ?>
                <?php include __DIR__ . '/includes/resource-card.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm text-center">
                    <div class="card-body p-4 p-md-5">
                        <h2 class="h4 fw-bold mb-2">More Resources for Teachers</h2>
                        <p class="text-secondary mb-4">Need more classroom-ready materials? Explore TeachLuma Pro resources for even more teaching options.</p>
                        <a href="<?= e(base_url('pricing.php')) ?>" class="btn btn-primary px-4">Explore Pro Resources</a>
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
        <h2 class="h3 fw-bold mb-2">Ready to Teach?</h2>
        <p class="mb-4" style="opacity:0.9;">Explore free resources, try a learning game, and find materials for your next lesson.</p>
        <div class="d-flex flex-column flex-sm-row justify-content-center gap-3">
            <a href="<?= e(base_url('resources.php?access=free')) ?>" class="btn btn-light btn-lg px-4 fw-semibold">Browse Free Resources</a>
            <a href="<?= e(base_url('') . '#play-and-learn') ?>" class="btn btn-outline-light btn-lg px-4">Play &amp; Learn</a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
