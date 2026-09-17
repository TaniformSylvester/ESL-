<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/resource-functions.php';
require_once __DIR__ . '/includes/favorites-functions.php';
require_once __DIR__ . '/includes/subject-functions.php';
require_once __DIR__ . '/includes/review-functions.php';
require_once __DIR__ . '/includes/video-functions.php';
require_once __DIR__ . '/includes/games-functions.php';
require_once __DIR__ . '/includes/bundle-functions.php';
require_once __DIR__ . '/includes/guide-functions.php';

$featuredVideos = get_featured_videos(3);
$featuredGames = get_featured_games(4);
$featuredBundles = get_featured_bundles(6);
$subjects = get_all_subjects();
$subjectCounts = get_resource_counts_by_subject();
$featuredReviews = get_featured_site_reviews(3);
$recentGuides = get_recent_guides(3);

// Real, live counts for the homepage trust strip — nothing here is ever
// hardcoded or estimated, so the numbers stay accurate as the site grows.
$statResourceCount = get_published_resource_count();
$statSubjectCount = count($subjects);
$statGameCount = count(get_all_games());
$statGuideCount = get_published_guide_count();

// The homepage's one resource-discovery grid: the most recently
// published resources — free-resource access is still front and center
// via the hero's CTA and every card's Free/Members badge, without a
// second overlapping section repeating the same resources.
$secondaryResourcesTitle = 'Featured Resources';
$secondaryResources = attach_rating_summaries(get_featured_resources(6));

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

$pageTitle = 'Ready-to-Use Teaching Resources for Schools Across Southeast Asia';
$pageDescription = 'Find practical ESL, Mathematics and Science resources, classroom activities and interactive learning games designed for classrooms across Southeast Asia.';

// Single source of truth for the FAQ accordion below — also drives the
// FAQPage schema, so the structured data can never drift from what a
// visitor actually sees on the page.
$faqItems = [
    [
        'question' => 'How many resources can I download for free?',
        'answer'   => 'Free resources are unlimited for everyone — no account required. Only members-only resources require a Teacher Pro membership.',
    ],
    [
        'question' => 'What do I get with TeachLuma Pro?',
        'answer'   => 'Pro members get unlimited downloads of members-only resources while their membership is active.',
    ],
    [
        'question' => 'What subjects does TeachLuma cover?',
        'answer'   => 'TeachLuma provides English and ESL resources from Kindergarten to Grade 10, plus Mathematics and Science resources for Grades 1–6.',
    ],
    [
        'question' => 'How do I pay?',
        'answer'   => 'Pay securely by card or scan to pay with PromptPay — handled by Stripe. Your Teacher Pro membership activates automatically the moment payment is confirmed, no waiting for approval.',
    ],
    [
        'question' => 'Can I cancel anytime?',
        'answer'   => "Yes. Membership renews manually, not automatically — if you don't submit another payment before your expiry date, your account simply reverts to the Free plan (still unlimited downloads of free resources) rather than being charged or locked out.",
    ],
];
$faqSchema = [
    '@context'   => 'https://schema.org',
    '@type'      => 'FAQPage',
    'mainEntity' => array_map(static function (array $item): array {
        return [
            '@type'          => 'Question',
            'name'           => $item['question'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['answer']],
        ];
    }, $faqItems),
];

require_once __DIR__ . '/includes/header.php';
?>
<script type="application/ld+json"><?= json_encode($faqSchema, JSON_UNESCAPED_SLASHES) ?></script>

<section class="hero py-5">
    <div class="container py-4">
        <div class="row align-items-center">
            <div class="col-lg-8 mx-auto text-center">
                <h1 class="display-5 fw-bold mb-3">Ready-to-Use Teaching Resources for Schools Across Southeast Asia</h1>
                <p class="lead mb-3">Find practical ESL, Mathematics and Science resources, classroom activities and interactive learning games designed for classrooms across Southeast Asia.</p>
                <div class="d-flex flex-column flex-sm-row justify-content-center gap-3 mb-3">
                    <a href="<?= e(base_url('resources.php?access=free')) ?>" class="btn btn-light btn-lg px-4 fw-semibold">Browse Free Resources</a>
                    <a href="<?= e(base_url('games.php')) ?>" class="btn btn-outline-light btn-lg px-4">Explore Games</a>
                </div>
                <p class="small mb-0" style="opacity:0.85;">Free resources &bull; No login required &bull; Classroom-ready materials</p>
            </div>
        </div>
    </div>
</section>

<section class="py-4 border-bottom">
    <div class="container">
        <div class="row g-3 text-center">
            <div class="col-6 col-md-3">
                <div class="fw-bold h4 mb-0 text-primary"><?= (int)$statResourceCount ?>+</div>
                <p class="small text-secondary mb-0">Teaching Resources</p>
            </div>
            <div class="col-6 col-md-3">
                <div class="fw-bold h4 mb-0 text-primary"><?= (int)$statSubjectCount ?></div>
                <p class="small text-secondary mb-0">Subjects Covered</p>
            </div>
            <div class="col-6 col-md-3">
                <div class="fw-bold h4 mb-0 text-primary"><?= (int)$statGameCount ?></div>
                <p class="small text-secondary mb-0">Learning Games</p>
            </div>
            <div class="col-6 col-md-3">
                <div class="fw-bold h4 mb-0 text-primary"><?= (int)$statGuideCount ?></div>
                <p class="small text-secondary mb-0">Teacher Hub Guides</p>
            </div>
        </div>
    </div>
</section>

<?php if (!empty($secondaryResources)): ?>
<section class="py-5">
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

<?php if (!empty($subjects)): ?>
<section class="py-5 section-soft">
    <div class="container">
        <h2 class="h3 fw-bold text-center mb-5">Browse by Subject</h2>
        <div class="row g-4 justify-content-center">
            <?php foreach ($subjects as $subject): ?>
                <div class="col-md-4">
                    <a href="<?= e(base_url('resources.php?subject_id=' . (int)$subject['id'])) ?>" class="card subject-card shadow-sm border-0 text-center text-decoration-none h-100 subject-<?= e($subject['slug']) ?>">
                        <div class="card-body p-4">
                            <i class="fa-solid <?= e($subjectIcons[$subject['slug']] ?? 'fa-book') ?> fa-2x mb-3 subject-icon"></i>
                            <h3 class="h5 fw-bold text-dark mb-1"><?= e($subject['name']) ?></h3>
                            <p class="small text-secondary mb-1"><?= e($subjectDescriptions[$subject['slug']] ?? ($subject['min_grade'] . ' - ' . $subject['max_grade'])) ?></p>
                            <?php if (!empty($subjectCounts[$subject['name']])): ?>
                                <p class="small fw-semibold mb-0 subject-icon"><?= (int)$subjectCounts[$subject['name']] ?> resources</p>
                            <?php endif; ?>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($featuredGames)): ?>
<section class="py-5" id="games">
    <div class="container">
        <div class="text-center mb-4">
            <h2 class="h3 fw-bold mb-2">Make Learning More Interactive</h2>
            <p class="text-secondary mx-auto" style="max-width:640px;">Classroom-friendly learning games &mdash; projector-ready, and playable on computers, tablets and phones. No login required.</p>
        </div>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4 justify-content-center mb-4">
            <?php foreach ($featuredGames as $game): ?>
                <?php include __DIR__ . '/includes/game-card.php'; ?>
            <?php endforeach; ?>
        </div>
        <div class="text-center">
            <a href="<?= e(base_url('games.php')) ?>" class="btn btn-outline-primary">View All Games &rarr;</a>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($recentGuides)): ?>
<section class="py-5 section-soft">
    <div class="container">
        <div class="text-center mb-4">
            <h2 class="h3 fw-bold mb-2">Practical Teaching Ideas &amp; Guides</h2>
            <p class="text-secondary mx-auto" style="max-width:640px;">The Teacher Hub: how-to-teach guidance, classroom activities and differentiation ideas alongside our downloadable resources.</p>
        </div>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4 mb-4">
            <?php foreach ($recentGuides as $guide): ?>
                <?php include __DIR__ . '/includes/guide-card.php'; ?>
            <?php endforeach; ?>
        </div>
        <div class="text-center">
            <a href="<?= e(base_url('teacher-hub.php')) ?>" class="btn btn-outline-primary">Visit the Teacher Hub &rarr;</a>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($featuredVideos)): ?>
<section class="py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="h3 fw-bold mb-2">Learn with TeachLuma</h2>
                <p class="text-secondary mb-0" style="max-width:600px;">Short educational videos to introduce a lesson before using the matching resources.</p>
            </div>
            <a href="<?= e(base_url('videos.php')) ?>" class="small text-nowrap">View All Videos &rarr;</a>
        </div>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4">
            <?php foreach ($featuredVideos as $video): ?>
                <?php include __DIR__ . '/includes/video-card.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($featuredBundles)): ?>
<section class="py-5 section-soft">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-5">
            <h2 class="h3 fw-bold mb-0">Featured Bundles</h2>
            <a href="<?= e(base_url('bundles.php')) ?>" class="small">View All Bundles &rarr;</a>
        </div>
        <p class="text-secondary mb-4" style="max-width:640px;">Save money bundling related resources together in one purchase.</p>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4">
            <?php foreach ($featuredBundles as $bundle): ?>
                <?php include __DIR__ . '/includes/bundle-card.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

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
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm text-center">
                    <div class="card-body p-4 p-md-5">
                        <h2 class="h4 fw-bold mb-2">Free to Start. Affordable to Grow.</h2>
                        <p class="text-secondary mb-4">Every free resource is unlimited, no account required. When you're ready for the full members-only library, Teacher Pro is <?= e(format_currency(PRICE_MONTHLY)) ?>/month or <?= e(format_currency(PRICE_ANNUAL)) ?>/year.</p>
                        <a href="<?= e(base_url('pricing.php')) ?>" class="btn btn-primary px-4">See Pricing &amp; Plans</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-5 section-soft">
    <div class="container">
        <h2 class="h3 fw-bold text-center mb-5">Frequently Asked Questions</h2>
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="accordion" id="faqAccordion">
                    <?php foreach ($faqItems as $faqIndex => $faqItem): ?>
                        <div class="accordion-item">
                            <h3 class="accordion-header">
                                <button class="accordion-button<?= $faqIndex === 0 ? '' : ' collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#faq<?= (int)$faqIndex + 1 ?>">
                                    <?= e($faqItem['question']) ?>
                                </button>
                            </h3>
                            <div id="faq<?= (int)$faqIndex + 1 ?>" class="accordion-collapse collapse<?= $faqIndex === 0 ? ' show' : '' ?>" data-bs-parent="#faqAccordion">
                                <div class="accordion-body"><?= e($faqItem['answer']) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
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
            <a href="<?= e(base_url('games.php')) ?>" class="btn btn-outline-light btn-lg px-4">Explore Games</a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
