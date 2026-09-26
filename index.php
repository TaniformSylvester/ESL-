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

// "K–Grade 10" / "Grades 1–6" style labels from each subject's real range.
$gradeRangeLabel = static function (array $subject): string {
    $min = (string)($subject['min_grade'] ?? '');
    $max = (string)($subject['max_grade'] ?? '');
    if ($min === '' || $max === '') {
        return '';
    }
    if ($min === 'Kindergarten') {
        return 'K–' . $max;
    }
    if (str_starts_with($min, 'Grade ') && str_starts_with($max, 'Grade ')) {
        return 'Grades ' . substr($min, 6) . '–' . substr($max, 6);
    }

    return $min . '–' . $max;
};

// Resource types that actually have published resources (live counts).
$typeCounts = array_slice(get_resource_counts_by_type(), 0, 8, true);
$typeTones = ['gold', 'teal', 'esl', 'math', 'science', 'games', 'videos', 'gold'];

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

<section class="home-hero" aria-labelledby="hero-title">
    <div class="container">
        <div class="row align-items-center g-4 g-lg-5">
            <div class="col-lg-6 hero-copy">
                <p class="hero-eyebrow"><span>Teach better</span><span>Save time</span><span>Engage learners</span></p>
                <h1 id="hero-title">Quality Teaching Resources for <span class="text-highlight text-highlight-swoosh">Every Classroom</span></h1>
                <p class="hero-lead">Ready-to-use lesson plans, worksheets, games and videos for ESL, Math and Science &mdash; made for classrooms across Southeast Asia.</p>
                <div class="hero-ctas">
                    <a href="<?= e(base_url('resources.php')) ?>" class="btn btn-accent btn-lg">
                        <i class="fa-solid fa-magnifying-glass me-2" aria-hidden="true"></i>Browse Resources
                    </a>
                    <a href="<?= e(base_url('games.php')) ?>" class="btn btn-outline-primary btn-lg">
                        <i class="fa-solid fa-gamepad me-2" aria-hidden="true"></i>Explore Games
                    </a>
                </div>
                <?php if (!empty($subjects)): ?>
                    <ul class="hero-subjects" aria-label="Subjects">
                        <?php foreach ($subjects as $subject): ?>
                            <?php $range = $gradeRangeLabel($subject); ?>
                            <li>
                                <a class="subject-pill is-<?= e(subject_key($subject['name'])) ?>" href="<?= e(base_url('resources.php?subject_id=' . (int)$subject['id'])) ?>">
                                    <i class="fa-solid <?= e($subjectIcons[$subject['slug']] ?? 'fa-book') ?>" aria-hidden="true"></i>
                                    <?= e($subject['name']) ?><?php if ($range !== ''): ?> <small><?= e($range) ?></small><?php endif; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <div class="col-lg-6">
                <div class="hero-art">
                    <img src="<?= e(versioned_asset_url('images/teachluma-hero-illustration.svg')) ?>" width="640" height="500"
                         alt="Illustration of a TeachLuma teacher helping three smiling students with a worksheet at a classroom table"
                         fetchpriority="high" decoding="async">
                    <div class="hero-art-badge badge-a" aria-hidden="true">
                        <span class="icon-bubble tone-science"><i class="fa-solid fa-circle-check"></i></span>
                        <span>Free to download<small>No login needed</small></span>
                    </div>
                    <div class="hero-art-badge badge-b" aria-hidden="true">
                        <span class="icon-bubble tone-games"><i class="fa-solid fa-display"></i></span>
                        <span>Projector-ready<small>Games &amp; slides</small></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
    // Only real, non-zero numbers are shown — a "0" stat reads as broken.
    $heroStats = array_filter([
        ['value' => $statResourceCount, 'suffix' => '+', 'label' => 'Teaching Resources', 'icon' => 'fa-book-open', 'tone' => 'esl'],
        ['value' => $statSubjectCount, 'suffix' => '', 'label' => 'Subjects Covered', 'icon' => 'fa-shapes', 'tone' => 'math'],
        ['value' => $statGameCount, 'suffix' => '', 'label' => 'Learning Games', 'icon' => 'fa-gamepad', 'tone' => 'games'],
        ['value' => $statGuideCount, 'suffix' => '', 'label' => 'Teacher Hub Guides', 'icon' => 'fa-lightbulb', 'tone' => 'teal'],
    ], static fn(array $stat): bool => (int)$stat['value'] > 0);
?>
<?php if (!empty($heroStats)): ?>
<div class="container">
    <div class="stat-strip" data-reveal>
        <div class="row row-cols-2 row-cols-lg-<?= count($heroStats) ?> g-2 g-md-3">
            <?php foreach ($heroStats as $stat): ?>
                <div class="col">
                    <div class="stat-item">
                        <span class="icon-bubble tone-<?= e($stat['tone']) ?>" aria-hidden="true"><i class="fa-solid <?= e($stat['icon']) ?>"></i></span>
                        <div><div class="stat-value"><?= (int)$stat['value'] ?><?= e($stat['suffix']) ?></div><p class="stat-label"><?= e($stat['label']) ?></p></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<section class="section" aria-labelledby="eco-title">
    <div class="container">
        <div class="section-head text-center" data-reveal>
            <p class="section-eyebrow">What is TeachLuma?</p>
            <h2 class="section-title" id="eco-title">Everything you need to <span class="text-highlight">teach</span>, in one place</h2>
            <p class="section-lead">TeachLuma is a teaching companion for ESL, primary and bilingual school teachers &mdash; download it, play it, watch it, or learn how to teach it.</p>
        </div>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-4">
            <div class="col" data-reveal>
                <a class="tl-card eco-card tone-esl" href="<?= e(base_url('resources.php')) ?>">
                    <span class="icon-bubble" aria-hidden="true"><i class="fa-solid fa-file-arrow-down"></i></span>
                    <h3>Resources</h3>
                    <p>Worksheets, lesson plans, PowerPoints and flashcards, organized by subject and grade.</p>
                    <span class="link-arrow">Browse resources <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
                </a>
            </div>
            <div class="col" data-reveal>
                <a class="tl-card eco-card tone-games" href="<?= e(base_url('games.php')) ?>">
                    <span class="icon-bubble" aria-hidden="true"><i class="fa-solid fa-gamepad"></i></span>
                    <h3>Games</h3>
                    <p>Interactive classroom games for the projector, whiteboard, tablet or phone &mdash; solo or team play.</p>
                    <span class="link-arrow">Play a game <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
                </a>
            </div>
            <div class="col" data-reveal>
                <a class="tl-card eco-card tone-videos" href="<?= e(base_url('videos.php')) ?>">
                    <span class="icon-bubble" aria-hidden="true"><i class="fa-solid fa-circle-play"></i></span>
                    <h3>Videos</h3>
                    <p>Short lesson videos to introduce a topic before using the matching resources.</p>
                    <span class="link-arrow">Watch videos <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
                </a>
            </div>
            <div class="col" data-reveal>
                <a class="tl-card eco-card tone-teal" href="<?= e(base_url('teacher-hub.php')) ?>">
                    <span class="icon-bubble" aria-hidden="true"><i class="fa-solid fa-chalkboard-user"></i></span>
                    <h3>Teacher Hub</h3>
                    <p>Practical how-to-teach guides, classroom activities, tools and differentiation ideas.</p>
                    <span class="link-arrow">Get teaching ideas <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
                </a>
            </div>
        </div>
    </div>
</section>

<?php if (!empty($subjects)): ?>
<section class="section section-cream" aria-labelledby="subjects-title">
    <div class="container">
        <div class="section-head text-center" data-reveal>
            <p class="section-eyebrow" style="--eyebrow-color:var(--tl-teal-ink);--eyebrow-bar:var(--tl-teal)">Browse by subject</p>
            <h2 class="section-title" id="subjects-title">Find resources for your subject</h2>
        </div>
        <div class="row g-4 justify-content-center">
            <?php foreach ($subjects as $subject): ?>
                <?php $tone = subject_key($subject['name']); $range = $gradeRangeLabel($subject); ?>
                <div class="col-md-6 col-lg-4" data-reveal>
                    <a href="<?= e(base_url('resources.php?subject_id=' . (int)$subject['id'])) ?>" class="subject-card tone-<?= e($tone) ?> subject-<?= e($subject['slug']) ?>">
                        <div class="subject-card-top d-flex align-items-center justify-content-between">
                            <span class="icon-bubble" aria-hidden="true"><i class="fa-solid <?= e($subjectIcons[$subject['slug']] ?? 'fa-book') ?>"></i></span>
                            <?php if ($range !== ''): ?>
                                <span class="badge badge-subject-<?= e($tone) ?> bg-white"><?= e($range) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="subject-card-body">
                            <h3><?= e($subject['name']) ?></h3>
                            <p class="small"><?= e($subjectDescriptions[$subject['slug']] ?? '') ?></p>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="link-arrow">Browse <?= e($subject['name']) ?> <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
                                <?php if (!empty($subjectCounts[$subject['name']])): ?>
                                    <span class="card-meta fw-bold"><?= (int)$subjectCounts[$subject['name']] ?> resource<?= (int)$subjectCounts[$subject['name']] === 1 ? '' : 's' ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($secondaryResources)): ?>
<section class="section" aria-labelledby="featured-title">
    <div class="container">
        <div class="section-head d-flex flex-wrap justify-content-between align-items-end gap-3" data-reveal>
            <div>
                <p class="section-eyebrow">Just added</p>
                <h2 class="section-title mb-0" id="featured-title"><?= e($secondaryResourcesTitle) ?></h2>
            </div>
            <a href="<?= e(base_url('resources.php')) ?>" class="link-arrow">View all resources <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
        </div>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4">
            <?php foreach ($secondaryResources as $resource): ?>
                <?php include __DIR__ . '/includes/resource-card.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (count($typeCounts) >= 2): ?>
<section class="section-sm pt-0" aria-labelledby="types-title">
    <div class="container">
        <div class="section-head mb-4" data-reveal>
            <p class="section-eyebrow" style="--eyebrow-color:var(--tl-teal-ink);--eyebrow-bar:var(--tl-teal)">Browse by type</p>
            <h2 class="h4 mb-0" id="types-title">What would you like to download?</h2>
        </div>
        <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-3" data-reveal>
            <?php $typeIndex = 0; foreach ($typeCounts as $type => $count): ?>
                <div class="col">
                    <a class="type-chip" href="<?= e(base_url('resources.php?resource_type=' . rawurlencode($type))) ?>">
                        <span class="icon-bubble tone-<?= e($typeTones[$typeIndex % count($typeTones)]) ?>" aria-hidden="true"><i class="fa-solid <?= e(resource_type_icon($type)) ?>"></i></span>
                        <span><?= e($type) ?><small><?= (int)$count ?> resource<?= $count === 1 ? '' : 's' ?></small></span>
                    </a>
                </div>
            <?php $typeIndex++; endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section section-cream" aria-labelledby="how-title">
    <div class="container">
        <div class="section-head text-center" data-reveal>
            <p class="section-eyebrow">How it works</p>
            <h2 class="section-title" id="how-title">From search to classroom in <span class="text-highlight">three steps</span></h2>
        </div>
        <div class="row g-4 align-items-stretch">
            <div class="col-md-4" data-reveal>
                <div class="step-card tone-gold">
                    <span class="step-number">1</span>
                    <h3>Find</h3>
                    <p>Browse by subject, grade or resource type &mdash; or search for the topic you're teaching this week.</p>
                </div>
            </div>
            <div class="col-md-4" data-reveal>
                <div class="step-card" style="--tone:var(--tl-teal-ink)">
                    <span class="step-number">2</span>
                    <h3>Download or play</h3>
                    <p>Free resources download instantly with no account. Games open straight in the browser.</p>
                </div>
            </div>
            <div class="col-md-4" data-reveal>
                <div class="step-card" style="--tone:var(--tl-navy)">
                    <span class="step-number">3</span>
                    <h3>Teach</h3>
                    <p>Print it, project it, or share it with students &mdash; ready for your next lesson.</p>
                </div>
            </div>
        </div>
        <div class="row justify-content-center mt-4">
            <div class="col-lg-9" data-reveal>
                <div class="creator-card">
                    <img src="<?= e(versioned_asset_url('brand/teachluma-teacher-avatar.svg')) ?>" alt="" width="96" height="96" loading="lazy">
                    <div>
                        <h3>Made by a classroom teacher</h3>
                        <p>TeachLuma is built for real classrooms in Thailand and across Southeast Asia &mdash; practical materials with the context you need to know whether a resource fits your lesson before you download it.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if (!empty($featuredGames)): ?>
<section class="section section-navy" id="games" aria-labelledby="games-title">
    <div class="container">
        <div class="section-head d-flex flex-wrap justify-content-between align-items-end gap-3" data-reveal>
            <div>
                <p class="section-eyebrow">Interactive games</p>
                <h2 class="section-title mb-2" id="games-title">Make learning <span class="text-highlight">more fun</span></h2>
                <p class="section-lead">Projector-ready classroom games for computers, tablets and phones. No login, no setup &mdash; play solo or in two teams.</p>
            </div>
            <a href="<?= e(base_url('games.php')) ?>" class="btn btn-accent">View All Games <i class="fa-solid fa-arrow-right ms-1" aria-hidden="true"></i></a>
        </div>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-4">
            <?php foreach ($featuredGames as $game): ?>
                <?php include __DIR__ . '/includes/game-card.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($featuredVideos)): ?>
<section class="section" aria-labelledby="videos-title">
    <div class="container">
        <div class="section-head d-flex flex-wrap justify-content-between align-items-end gap-3" data-reveal>
            <div>
                <p class="section-eyebrow" style="--eyebrow-color:var(--subject-videos-ink);--eyebrow-bar:var(--tl-pink)">Learn with TeachLuma</p>
                <h2 class="section-title mb-2" id="videos-title">Short lesson videos</h2>
                <p class="section-lead">Introduce a lesson with a short video, then follow up with the matching resources.</p>
            </div>
            <a href="<?= e(base_url('videos.php')) ?>" class="link-arrow">View all videos <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
        </div>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4">
            <?php foreach ($featuredVideos as $video): ?>
                <?php include __DIR__ . '/includes/video-card.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($recentGuides)): ?>
<section class="section section-cream" aria-labelledby="guides-title">
    <div class="container">
        <div class="section-head d-flex flex-wrap justify-content-between align-items-end gap-3" data-reveal>
            <div>
                <p class="section-eyebrow" style="--eyebrow-color:var(--tl-teal-ink);--eyebrow-bar:var(--tl-teal)">Teacher Hub</p>
                <h2 class="section-title mb-2" id="guides-title">Practical teaching ideas &amp; guides</h2>
                <p class="section-lead">How-to-teach guidance, classroom activities and differentiation ideas alongside our downloadable resources.</p>
            </div>
            <a href="<?= e(base_url('teacher-hub.php')) ?>" class="link-arrow">Visit the Teacher Hub <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
        </div>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4">
            <?php foreach ($recentGuides as $guide): ?>
                <?php include __DIR__ . '/includes/guide-card.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($featuredBundles)): ?>
<section class="section" aria-labelledby="bundles-title">
    <div class="container">
        <div class="section-head d-flex flex-wrap justify-content-between align-items-end gap-3" data-reveal>
            <div>
                <p class="section-eyebrow">Bundles</p>
                <h2 class="section-title mb-2" id="bundles-title">Featured bundles</h2>
                <p class="section-lead">Save money by getting related resources together in one purchase.</p>
            </div>
            <a href="<?= e(base_url('bundles.php')) ?>" class="link-arrow">View all bundles <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
        </div>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4">
            <?php foreach ($featuredBundles as $bundle): ?>
                <?php include __DIR__ . '/includes/bundle-card.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($featuredReviews)): ?>
<section class="section section-cream" aria-labelledby="reviews-title">
    <div class="container">
        <div class="section-head text-center" data-reveal>
            <p class="section-eyebrow">Reviews</p>
            <h2 class="section-title" id="reviews-title">What teachers are saying</h2>
        </div>
        <div class="row row-cols-1 row-cols-md-3 g-4">
            <?php foreach ($featuredReviews as $review): ?>
                <div class="col" data-reveal>
                    <figure class="review-card mb-0">
                        <div class="review-stars mb-2" aria-label="<?= (int)$review['rating'] ?> out of 5 stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fa-solid fa-star<?= $i > (int)$review['rating'] ? ' opacity-25' : '' ?>" aria-hidden="true"></i>
                            <?php endfor; ?>
                        </div>
                        <blockquote class="mb-3"><p class="mb-0">&ldquo;<?= e($review['review_text']) ?>&rdquo;</p></blockquote>
                        <figcaption class="small text-secondary">
                            &mdash; <strong class="text-body"><?= e($review['first_name']) ?></strong>, on
                            <a href="<?= e(base_url('resource.php?slug=' . urlencode($review['resource_slug']))) ?>"><?= e($review['resource_title']) ?></a>
                        </figcaption>
                    </figure>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section" aria-label="Pricing and resource requests">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-6" data-reveal>
                <div class="cta-card tone-gold">
                    <span class="icon-bubble mb-3" aria-hidden="true"><i class="fa-solid fa-crown"></i></span>
                    <h2>Free to start. Affordable to grow.</h2>
                    <p class="mb-4">Every free resource is unlimited, no account required. When you're ready for the full members-only library, Teacher Pro is <?= e(format_currency(PRICE_MONTHLY)) ?>/month or <?= e(format_currency(PRICE_ANNUAL)) ?>/year.</p>
                    <a href="<?= e(base_url('pricing.php')) ?>" class="btn btn-primary">See Pricing &amp; Plans</a>
                </div>
            </div>
            <div class="col-lg-6" data-reveal>
                <div class="cta-card tone-teal">
                    <span class="icon-bubble mb-3" aria-hidden="true"><i class="fa-solid fa-hand-sparkles"></i></span>
                    <h2>Tell us what you need</h2>
                    <p class="mb-4">Can't find the resource you're looking for? Request a worksheet, lesson plan, PowerPoint, quiz, game, or other teaching resource, and help us decide what to create next.</p>
                    <a href="<?= e(base_url('request-resource.php')) ?>" class="btn btn-outline-primary">Request a Resource</a>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section section-cream" aria-labelledby="faq-title">
    <div class="container">
        <div class="section-head text-center" data-reveal>
            <p class="section-eyebrow">FAQ</p>
            <h2 class="section-title" id="faq-title">Frequently asked questions</h2>
        </div>
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="accordion accordion-tl" id="faqAccordion">
                    <?php foreach ($faqItems as $faqIndex => $faqItem): ?>
                        <div class="accordion-item">
                            <h3 class="accordion-header">
                                <button class="accordion-button<?= $faqIndex === 0 ? '' : ' collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#faq<?= (int)$faqIndex + 1 ?>" aria-expanded="<?= $faqIndex === 0 ? 'true' : 'false' ?>" aria-controls="faq<?= (int)$faqIndex + 1 ?>">
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

<section class="section" aria-labelledby="final-cta-title">
    <div class="container">
        <div class="final-cta" data-reveal>
            <div class="row align-items-center g-4">
                <div class="col-md-auto text-center">
                    <img src="<?= e(versioned_asset_url('brand/teachluma-teacher-avatar.svg')) ?>" alt="" width="150" height="150" loading="lazy">
                </div>
                <div class="col-md text-center text-md-start">
                    <h2 id="final-cta-title">Ready to <span class="text-highlight">teach</span>?</h2>
                    <p class="mb-0">Explore free resources, try a learning game, and find materials for your next lesson.</p>
                </div>
                <div class="col-lg-auto d-flex flex-column flex-sm-row gap-2 justify-content-center">
                    <a href="<?= e(base_url('resources.php?access=free')) ?>" class="btn btn-accent btn-lg">Browse Free Resources</a>
                    <a href="<?= e(base_url('games.php')) ?>" class="btn btn-outline-light btn-lg">Explore Games</a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
