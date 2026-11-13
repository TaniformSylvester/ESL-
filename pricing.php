<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/resource-functions.php';
require_once __DIR__ . '/includes/promptpay-functions.php';
require_once __DIR__ . '/includes/seo-functions.php';

$isLoggedIn = is_logged_in();
$totalResources = get_published_resource_count();
$typeCounts = get_resource_type_counts();
$subjectCounts = get_resource_counts_by_subject();

$scanToPayOn = scan_to_pay_config() !== null;
// Each figure in both currencies; price_tag() shows the visitor's one.
$annualMonthlyEquivalent = ['THB' => PRICE_ANNUAL / 12, 'USD' => PRICE_ANNUAL_USD / 12];
$annualSavings = ['THB' => (PRICE_MONTHLY * 12) - PRICE_ANNUAL, 'USD' => round((PRICE_MONTHLY_USD * 12) - PRICE_ANNUAL_USD, 2)];
$yearOfMonthly = ['THB' => PRICE_MONTHLY * 12, 'USD' => round(PRICE_MONTHLY_USD * 12, 2)];
$upgradeParams = fn (string $where, string $plan) => e(json_encode(['location' => $where, 'plan' => $plan]));

$pageTitle = 'Pricing — Free, Teacher Pro & School Plans';
$pageDescription = 'Free resources are unlimited with a free account, plus 3 Free Pro Previews. Teacher Pro unlocks members-only resources for ' . plan_price_text_both('monthly') . ' a month or ' . plan_price_text_both('annual') . ' a year. School and Department Passes cover whole teams.';
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

    <ul class="nav nav-pills justify-content-center gap-2 mb-4" id="planTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active px-4" id="tab-teachers" data-bs-toggle="pill" data-bs-target="#plans-teachers" type="button" role="tab" aria-controls="plans-teachers" aria-selected="true">
                <i class="fa-solid fa-user me-2" aria-hidden="true"></i>Individual Teachers
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link px-4" id="tab-schools" data-bs-toggle="pill" data-bs-target="#plans-schools" type="button" role="tab" aria-controls="plans-schools" aria-selected="false"
                    data-ga-event="pricing_tab" data-ga-params="<?= e(json_encode(['tab' => 'schools'])) ?>">
                <i class="fa-solid fa-school me-2" aria-hidden="true"></i>School &amp; Department Plans
            </button>
        </li>
    </ul>

    <div class="tab-content mb-5">
    <div class="tab-pane fade show active" id="plans-teachers" role="tabpanel" aria-labelledby="tab-teachers" tabindex="0">
    <?php if (STRIPE_ENABLED): ?><div class="text-center mb-4"><?= currency_switch_html() ?></div><?php endif; ?>
    <div class="row justify-content-center g-4">
        <!-- FREE -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body p-4 d-flex flex-column">
                    <h2 class="h5 fw-bold text-uppercase text-secondary">Free</h2>
                    <p class="display-5 fw-bold mb-0"><?= price_tag(0, 0) ?></p>
                    <p class="text-secondary mb-4">forever</p>
                    <ul class="list-unstyled small mb-4 flex-grow-1">
                        <li class="mb-2"><i class="fa-solid fa-check text-success me-2"></i>Browse the complete resource library</li>
                        <li class="mb-2"><i class="fa-solid fa-check text-success me-2"></i>Search and filter by subject &amp; grade</li>
                        <li class="mb-2"><i class="fa-solid fa-check text-success me-2"></i><strong>Unlimited</strong> downloads of every free resource</li>
                        <li class="mb-2"><i class="fa-solid fa-check text-success me-2"></i><strong>3 Free Pro Previews</strong> of members-only resources</li>
                        <li class="mb-2 text-secondary"><i class="fa-solid fa-xmark me-2"></i>Full members-only library</li>
                    </ul>
                    <?php if ($isLoggedIn): ?>
                        <a href="<?= e(base_url('dashboard.php')) ?>" class="btn btn-outline-secondary w-100">Go to Dashboard</a>
                    <?php else: ?>
                        <a href="<?= e(base_url('resources.php')) ?>" class="btn btn-outline-secondary w-100">Browse Free Resources</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- MONTHLY -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body p-4 d-flex flex-column">
                    <h2 class="h5 fw-bold text-uppercase text-secondary">Teacher Pro</h2>
                    <p class="display-5 fw-bold text-primary mb-0"><?= plan_price_tag('monthly') ?></p>
                    <p class="text-secondary mb-4">per month</p>
                    <ul class="list-unstyled small mb-4 flex-grow-1">
                        <li class="mb-2"><i class="fa-solid fa-check text-success me-2"></i><strong>Unlimited</strong> downloads</li>
                        <li class="mb-2"><i class="fa-solid fa-check text-success me-2"></i>Complete access to members-only resources</li>
                        <li class="mb-2"><i class="fa-solid fa-check text-success me-2"></i>English/ESL Kindergarten&ndash;Grade 10 (UK Years 1&ndash;11)</li>
                        <li class="mb-2"><i class="fa-solid fa-check text-success me-2"></i>Maths &amp; Science Grade 1&ndash;6 (UK Years 2&ndash;7)</li>
                        <li class="mb-2"><i class="fa-solid fa-check text-success me-2"></i>New resources added regularly</li>
                    </ul>
                    <a href="<?= e(base_url($isLoggedIn ? 'member/subscription.php?plan=monthly' : 'register.php')) ?>" class="btn btn-primary w-100"
                       data-ga-event="upgrade_click" data-ga-params="<?= $upgradeParams('pricing_page', 'monthly') ?>">Go Pro &mdash; <?= plan_price_tag('monthly') ?>/month</a>
                </div>
            </div>
        </div>

        <!-- ANNUAL (hero) -->
        <div class="col-lg-4">
            <div class="card shadow border-primary h-100 position-relative" style="border-width:2px;">
                <span class="badge bg-warning text-dark position-absolute top-0 start-50 translate-middle px-3 py-2">🏆 BEST VALUE</span>
                <div class="card-body p-4 d-flex flex-column">
                    <h2 class="h5 fw-bold text-uppercase text-primary mt-2">Teacher Pro Annual</h2>
                    <p class="display-5 fw-bold text-primary mb-0"><?= plan_price_tag('annual') ?></p>
                    <p class="text-secondary mb-1">per year &mdash; only about <?= price_tag(round($annualMonthlyEquivalent['THB']), round($annualMonthlyEquivalent['USD'], 2)) ?>/month</p>
                    <p class="fw-bold text-success mb-4">SAVE <?= price_tag($annualSavings['THB'], $annualSavings['USD']) ?>/YEAR</p>
                    <ul class="list-unstyled small mb-4 flex-grow-1">
                        <li class="mb-2"><i class="fa-solid fa-check text-success me-2"></i>Everything in Teacher Pro Monthly</li>
                        <li class="mb-2"><i class="fa-solid fa-check text-success me-2"></i><strong>Unlimited</strong> downloads, all year</li>
                        <li class="mb-2"><i class="fa-solid fa-check text-success me-2"></i>Pay once, forget about it for 12 months</li>
                    </ul>
                    <a href="<?= e(base_url($isLoggedIn ? 'member/subscription.php?plan=annual' : 'register.php')) ?>" class="btn btn-primary w-100"
                       data-ga-event="upgrade_click" data-ga-params="<?= $upgradeParams('pricing_page', 'annual') ?>">Get Annual Pro &mdash; <?= plan_price_tag('annual') ?></a>
                    <p class="text-secondary small text-center mt-2 mb-0"><?= plan_price_tag('monthly') ?> &times; 12 = <?= price_tag($yearOfMonthly['THB'], $yearOfMonthly['USD']) ?>. Annual = <?= plan_price_tag('annual') ?>. Savings = <?= price_tag($annualSavings['THB'], $annualSavings['USD']) ?>.</p>
                </div>
            </div>
        </div>
    </div>
    <p class="text-center text-secondary small mt-4 mb-0">
        <i class="fa-solid fa-school me-1" aria-hidden="true"></i>Buying for several teachers?
        <a href="#plans-schools" class="js-show-schools">See School &amp; Department Plans</a>.
    </p>
    </div>

    <!-- SCHOOL & DEPARTMENT PLANS -->
    <div class="tab-pane fade" id="plans-schools" role="tabpanel" aria-labelledby="tab-schools" tabindex="0">
        <p class="text-center text-secondary mx-auto mb-4" style="max-width:640px;">
            One payment covers your whole team. Every teacher gets their own Teacher Pro login, and your school gets one invoice and receipt.
        </p>
        <div class="row justify-content-center g-4">
            <!-- DEPARTMENT -->
            <div class="col-lg-5">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body p-4 d-flex flex-column">
                        <h2 class="h5 fw-bold text-uppercase text-secondary">Department Pass</h2>
                        <p class="display-5 fw-bold text-primary mb-0"><?= e(format_money(PRICE_DEPARTMENT_USD, 'USD')) ?></p>
                        <p class="text-secondary mb-1">per year &mdash; up to <?= (int)DEPARTMENT_PASS_SEATS ?> teachers</p>
                        <p class="fw-bold text-success mb-4">About <?= e(format_money(round(PRICE_DEPARTMENT_USD / DEPARTMENT_PASS_SEATS, 2), 'USD')) ?> per teacher per year (vs <?= e(format_money(PRICE_ANNUAL_USD, 'USD')) ?> each)</p>
                        <ul class="list-unstyled small mb-4 flex-grow-1">
                            <li class="mb-2"><i class="fa-solid fa-check text-success me-2"></i>Teacher Pro for up to <strong><?= (int)DEPARTMENT_PASS_SEATS ?> teachers</strong>, each with their own login</li>
                            <li class="mb-2"><i class="fa-solid fa-check text-success me-2"></i><strong>Unlimited</strong> downloads of every members-only resource</li>
                            <li class="mb-2"><i class="fa-solid fa-check text-success me-2"></i>ESL, Maths &amp; Science in one plan</li>
                            <li class="mb-2"><i class="fa-solid fa-check text-success me-2"></i>One invoice and receipt for your school</li>
                        </ul>
                        <a href="<?= e(base_url('contact.php?plan=department')) ?>" class="btn btn-primary w-100"
                           data-ga-event="team_plan_click" data-ga-params="<?= e(json_encode(['plan' => 'department'])) ?>">Request a Department Pass</a>
                    </div>
                </div>
            </div>

            <!-- SCHOOL -->
            <div class="col-lg-5">
                <div class="card shadow border-primary h-100 position-relative" style="border-width:2px;">
                    <span class="badge bg-warning text-dark position-absolute top-0 start-50 translate-middle px-3 py-2">🏫 WHOLE SCHOOL</span>
                    <div class="card-body p-4 d-flex flex-column">
                        <h2 class="h5 fw-bold text-uppercase text-primary mt-2">School Pass</h2>
                        <p class="display-5 fw-bold text-primary mb-0"><?= e(format_money(PRICE_SCHOOL_USD, 'USD')) ?></p>
                        <p class="text-secondary mb-1">per year &mdash; unlimited teachers</p>
                        <p class="fw-bold text-success mb-4">Every teacher at one school</p>
                        <ul class="list-unstyled small mb-4 flex-grow-1">
                            <li class="mb-2"><i class="fa-solid fa-check text-success me-2"></i>Teacher Pro for <strong>every teacher</strong> at your school</li>
                            <li class="mb-2"><i class="fa-solid fa-check text-success me-2"></i>Add new staff at any time during the year</li>
                            <li class="mb-2"><i class="fa-solid fa-check text-success me-2"></i><strong>Unlimited</strong> downloads of every members-only resource</li>
                            <li class="mb-2"><i class="fa-solid fa-check text-success me-2"></i>One invoice and receipt for your school</li>
                        </ul>
                        <a href="<?= e(base_url('contact.php?plan=school')) ?>" class="btn btn-primary w-100"
                           data-ga-event="team_plan_click" data-ga-params="<?= e(json_encode(['plan' => 'school'])) ?>">Request a School Pass</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row justify-content-center mt-5">
            <div class="col-lg-8">
                <h2 class="h5 fw-bold mb-3">How School &amp; Department Plans work</h2>
                <ol class="mb-3">
                    <li class="mb-2">Send us a request with your school's name and how many teachers need access.</li>
                    <li class="mb-2">We reply with an invoice and payment details.</li>
                    <li class="mb-2">Once it's paid, we activate Teacher Pro for 12 months on each teacher's account. Teachers just create a free account with their school email.</li>
                </ol>
                <p class="small text-secondary mb-0">Prices are in US dollars. Need a quote in another currency, or a plan for more than one school? <a href="<?= e(base_url('contact.php?plan=school')) ?>">Just ask</a>.</p>
            </div>
        </div>
    </div>
    </div>
    <script>
        // Open the School & Department tab from a #schools / #plans-schools link.
        document.addEventListener('DOMContentLoaded', function () {
            var tab = document.getElementById('tab-schools');
            if (!tab || typeof bootstrap === 'undefined') { return; }
            var show = function () { bootstrap.Tab.getOrCreateInstance(tab).show(); };
            if (location.hash === '#schools' || location.hash === '#plans-schools') { show(); }
            document.querySelectorAll('.js-show-schools').forEach(function (link) {
                link.addEventListener('click', function (event) { event.preventDefault(); show(); tab.scrollIntoView({ behavior: 'smooth', block: 'center' }); });
            });
        });
    </script>

    <!-- SUBJECT VALUE -->
    <div class="row justify-content-center mb-5">
        <div class="col-lg-9 text-center">
            <h2 class="h4 fw-bold text-uppercase mb-4">One Membership. Multiple Subjects.</h2>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100"><div class="card-body">
                        <h3 class="h6 fw-bold mb-1">English / ESL</h3>
                        <p class="text-secondary small mb-0">Kindergarten &rarr; Grade 10 <span class="d-block">(UK Years 1&ndash;11)</span></p>
                        <?php if (!empty($subjectCounts['ESL'])): ?><p class="small fw-bold text-primary mb-0"><?= (int)$subjectCounts['ESL'] ?>+ resources</p><?php endif; ?>
                    </div></div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100"><div class="card-body">
                        <h3 class="h6 fw-bold mb-1">Mathematics (Maths)</h3>
                        <p class="text-secondary small mb-0">Grade 1 &rarr; Grade 6 <span class="d-block">(UK Years 2&ndash;7)</span></p>
                        <?php if (!empty($subjectCounts['Math'])): ?><p class="small fw-bold text-primary mb-0"><?= (int)$subjectCounts['Math'] ?>+ resources</p><?php endif; ?>
                    </div></div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100"><div class="card-body">
                        <h3 class="h6 fw-bold mb-1">Science</h3>
                        <p class="text-secondary small mb-0">Grade 1 &rarr; Grade 6 <span class="d-block">(UK Years 2&ndash;7)</span></p>
                        <?php if (!empty($subjectCounts['Science'])): ?><p class="small fw-bold text-primary mb-0"><?= (int)$subjectCounts['Science'] ?>+ resources</p><?php endif; ?>
                    </div></div>
                </div>
            </div>
            <p class="text-secondary">Whether you teach Kindergarten English, Grade 5 (Year 6) Maths, Grade 6 Science or Grade 10 ESL, TeachLuma gives you ready-to-use classroom materials in one place.</p>
        </div>
    </div>

    <?php if (!empty($typeCounts)): ?>
    <!-- RESOURCE VALUE -->
    <div class="row justify-content-center mb-5">
        <div class="col-lg-9 text-center">
            <h2 class="h4 fw-bold mb-4"><?= (int)$totalResources ?>+ Ready-to-Use Resources</h2>
            <div class="d-flex flex-wrap justify-content-center gap-2">
                <?php foreach ($typeCounts as $type => $count): ?>
                    <span class="badge bg-light text-dark border px-3 py-2"><?= (int)$count ?>+ <?= e((int)$count === 1 ? $type : pluralize_resource_type($type)) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <h2 class="h4 fw-bold mb-3">How Membership Works</h2>
            <p>Here's how it works:</p>
            <ol>
                <li class="mb-2">Create a free account and download every free resource, unlimited. Your account also includes 3 Free Pro Previews of members-only resources.</li>
                <li class="mb-2">When you're ready to unlock members-only resources, go to your <strong>Subscription</strong> page and choose Monthly or Annual.</li>
                <?php if (STRIPE_ENABLED): ?>
                    <li class="mb-2">Pay securely by card &mdash; handled by Stripe. Your membership activates immediately once payment is confirmed, no waiting.</li>
                <?php endif; ?>
                <?php if ($scanToPayOn): ?>
                    <li class="mb-2" <?= STRIPE_ENABLED ? currency_only_attrs('THB') : '' ?>><?= STRIPE_ENABLED ? 'Or scan to pay' : 'Scan to pay' ?> with PromptPay from any Thai banking app, then upload your slip. We'll check it and activate your membership, usually within a few hours.</li>
                <?php endif; ?>
                <li class="mb-2">Renew before your membership expires to keep access to members-only resources &mdash; if it lapses, your account simply reverts to the Free plan (still unlimited free downloads) rather than being locked out.</li>
            </ol>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
