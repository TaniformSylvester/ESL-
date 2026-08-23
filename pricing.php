<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/resource-functions.php';

$isLoggedIn = is_logged_in();
$totalResources = get_published_resource_count();
$typeCounts = get_resource_type_counts();
$subjectCounts = get_resource_counts_by_subject();

$annualMonthlyEquivalent = PRICE_ANNUAL / 12;
$annualSavings = (PRICE_MONTHLY * 12) - PRICE_ANNUAL;

$pageTitle = 'Pricing';
$pageDescription = 'TeachLuma pricing: a free plan with 5 downloads a month, or Teacher Pro for unlimited downloads at ' . format_currency(PRICE_MONTHLY) . '/month or ' . format_currency(PRICE_ANNUAL) . '/year.';
require_once __DIR__ . '/includes/header.php';
?>
<div class="container py-5">
    <div class="text-center mb-5">
        <h1 class="fw-bold">Stop spending your evenings preparing lessons.</h1>
        <p class="lead text-secondary mx-auto" style="max-width:640px;">
            TeachLuma gives teachers ready-to-use teaching resources so you can spend less time preparing and more time teaching.
        </p>
        <p class="fw-bold text-primary">Find it. Download it. Teach it.</p>
    </div>

    <div class="row justify-content-center g-4 mb-5">
        <!-- FREE -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body p-4 d-flex flex-column">
                    <h2 class="h5 fw-bold text-uppercase text-secondary">Free</h2>
                    <p class="display-5 fw-bold mb-0">฿0</p>
                    <p class="text-secondary mb-4">forever</p>
                    <ul class="list-unstyled small mb-4 flex-grow-1">
                        <li class="mb-2"><i class="fa-solid fa-check text-success me-2"></i>Browse the complete resource library</li>
                        <li class="mb-2"><i class="fa-solid fa-check text-success me-2"></i>Search and filter by subject &amp; grade</li>
                        <li class="mb-2"><i class="fa-solid fa-check text-success me-2"></i>5 downloads every month</li>
                        <li class="mb-2 text-secondary"><i class="fa-solid fa-xmark me-2"></i>Members-only resources</li>
                    </ul>
                    <?php if ($isLoggedIn): ?>
                        <a href="<?= e(base_url('dashboard.php')) ?>" class="btn btn-outline-secondary w-100">Go to Dashboard</a>
                    <?php else: ?>
                        <a href="<?= e(base_url('register.php')) ?>" class="btn btn-outline-secondary w-100">Start Free</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- MONTHLY -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body p-4 d-flex flex-column">
                    <h2 class="h5 fw-bold text-uppercase text-secondary">Teacher Pro</h2>
                    <p class="display-5 fw-bold text-primary mb-0"><?= format_currency(PRICE_MONTHLY) ?></p>
                    <p class="text-secondary mb-4">per month</p>
                    <ul class="list-unstyled small mb-4 flex-grow-1">
                        <li class="mb-2"><i class="fa-solid fa-check text-success me-2"></i><strong>Unlimited</strong> downloads</li>
                        <li class="mb-2"><i class="fa-solid fa-check text-success me-2"></i>Complete access to members-only resources</li>
                        <li class="mb-2"><i class="fa-solid fa-check text-success me-2"></i>English/ESL Kindergarten&ndash;Grade 10</li>
                        <li class="mb-2"><i class="fa-solid fa-check text-success me-2"></i>Mathematics &amp; Science Grade 1&ndash;6</li>
                        <li class="mb-2"><i class="fa-solid fa-check text-success me-2"></i>New resources added regularly</li>
                    </ul>
                    <a href="<?= e(base_url($isLoggedIn ? 'member/subscription.php?plan=monthly' : 'register.php')) ?>" class="btn btn-primary w-100">Go Pro &mdash; <?= format_currency(PRICE_MONTHLY) ?>/month</a>
                </div>
            </div>
        </div>

        <!-- ANNUAL (hero) -->
        <div class="col-lg-4">
            <div class="card shadow border-primary h-100 position-relative" style="border-width:2px;">
                <span class="badge bg-warning text-dark position-absolute top-0 start-50 translate-middle px-3 py-2">🏆 BEST VALUE</span>
                <div class="card-body p-4 d-flex flex-column">
                    <h2 class="h5 fw-bold text-uppercase text-primary mt-2">Teacher Pro Annual</h2>
                    <p class="display-5 fw-bold text-primary mb-0"><?= format_currency(PRICE_ANNUAL) ?></p>
                    <p class="text-secondary mb-1">per year &mdash; only about ฿<?= number_format($annualMonthlyEquivalent, 0) ?>/month</p>
                    <p class="fw-bold text-success mb-4">SAVE <?= format_currency($annualSavings) ?>/YEAR</p>
                    <ul class="list-unstyled small mb-4 flex-grow-1">
                        <li class="mb-2"><i class="fa-solid fa-check text-success me-2"></i>Everything in Teacher Pro Monthly</li>
                        <li class="mb-2"><i class="fa-solid fa-check text-success me-2"></i><strong>Unlimited</strong> downloads, all year</li>
                        <li class="mb-2"><i class="fa-solid fa-check text-success me-2"></i>Pay once, forget about it for 12 months</li>
                    </ul>
                    <a href="<?= e(base_url($isLoggedIn ? 'member/subscription.php?plan=annual' : 'register.php')) ?>" class="btn btn-primary w-100">Get Annual Pro &mdash; <?= format_currency(PRICE_ANNUAL) ?></a>
                    <p class="text-secondary small text-center mt-2 mb-0"><?= format_currency(PRICE_MONTHLY) ?> &times; 12 = <?= format_currency(PRICE_MONTHLY * 12) ?>. Annual = <?= format_currency(PRICE_ANNUAL) ?>. Savings = <?= format_currency($annualSavings) ?>.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- SUBJECT VALUE -->
    <div class="row justify-content-center mb-5">
        <div class="col-lg-9 text-center">
            <h2 class="h4 fw-bold text-uppercase mb-4">One Membership. Multiple Subjects.</h2>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100"><div class="card-body">
                        <h3 class="h6 fw-bold mb-1">English / ESL</h3>
                        <p class="text-secondary small mb-0">Kindergarten &rarr; Grade 10</p>
                        <?php if (!empty($subjectCounts['ESL'])): ?><p class="small fw-bold text-primary mb-0"><?= (int)$subjectCounts['ESL'] ?>+ resources</p><?php endif; ?>
                    </div></div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100"><div class="card-body">
                        <h3 class="h6 fw-bold mb-1">Mathematics</h3>
                        <p class="text-secondary small mb-0">Grade 1 &rarr; Grade 6</p>
                        <?php if (!empty($subjectCounts['Math'])): ?><p class="small fw-bold text-primary mb-0"><?= (int)$subjectCounts['Math'] ?>+ resources</p><?php endif; ?>
                    </div></div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100"><div class="card-body">
                        <h3 class="h6 fw-bold mb-1">Science</h3>
                        <p class="text-secondary small mb-0">Grade 1 &rarr; Grade 6</p>
                        <?php if (!empty($subjectCounts['Science'])): ?><p class="small fw-bold text-primary mb-0"><?= (int)$subjectCounts['Science'] ?>+ resources</p><?php endif; ?>
                    </div></div>
                </div>
            </div>
            <p class="text-secondary">Whether you teach Kindergarten English, Grade 5 Mathematics, Grade 6 Science or Grade 10 ESL, TeachLuma gives you ready-to-use classroom materials in one place.</p>
        </div>
    </div>

    <?php if (!empty($typeCounts)): ?>
    <!-- RESOURCE VALUE -->
    <div class="row justify-content-center mb-5">
        <div class="col-lg-9 text-center">
            <h2 class="h4 fw-bold mb-4"><?= (int)$totalResources ?>+ Ready-to-Use Resources</h2>
            <div class="d-flex flex-wrap justify-content-center gap-2">
                <?php foreach ($typeCounts as $type => $count): ?>
                    <span class="badge bg-light text-dark border px-3 py-2"><?= (int)$count ?>+ <?= e($type) ?><?= $count === 1 ? '' : 's' ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <h2 class="h4 fw-bold mb-3">How Membership Works Right Now</h2>
            <p>Membership is currently activated manually after payment is confirmed. Here's the process:</p>
            <ol>
                <li class="mb-2">Register for a free account and log in &mdash; you'll get 5 free downloads a month right away.</li>
                <li class="mb-2">When you're ready for unlimited downloads, go to your <strong>Subscription</strong> page, choose Monthly or Annual, and review the payment instructions (bank transfer or PromptPay).</li>
                <li class="mb-2">Send your payment, then submit the amount, date, and reference number.</li>
                <li class="mb-2">Our team reviews and approves your payment &mdash; usually within a day &mdash; and your membership becomes active for 30 days (Monthly) or 365 days (Annual) from approval.</li>
                <li class="mb-2">Renew before your membership expires to keep unlimited access &mdash; if it lapses, your account simply reverts to the Free plan (5 downloads/month) rather than being locked out.</li>
            </ol>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
