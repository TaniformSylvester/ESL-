<?php
/**
 * "Free vs. Pro Pack" comparison card for resource.php. Shown only to
 * guests and Free-plan accounts (never to active Pro members or admins).
 *
 * Expects in scope: $resource, $isLoggedIn, $proPackSuggestion (?array from
 * get_pro_pack_suggestion()). Prices use price_tag(), so they follow the
 * visitor's THB/USD choice (assets/js/currency.js keeps them in step).
 */
$annualSavingsTag = price_tag(
    plan_price_in('monthly', 'THB') * 12 - plan_price_in('annual', 'THB'),
    round(plan_price_in('monthly', 'USD') * 12 - plan_price_in('annual', 'USD'), 2)
);
$comparisonRows = [
    ['Printable worksheet with answer key', true],
    ['Editable PowerPoint slides with speaker notes', false],
    ['Printable picture flashcards', false],
    ['Quiz / assessment', false],
    ['Homework practice page', false],
    ['Assessment rubric', false],
    ['Every members-only resource in the library', false],
];
$freeColumnTitle = $resource['is_free'] ? 'This free resource' : 'Free account';
$upgradeUrl = $isLoggedIn ? base_url('member/subscription.php?plan=annual') : base_url('pricing.php');
$gaParams = static fn(string $location): string => e(json_encode(['location' => $location, 'resource_id' => (int)$resource['id']]));
?>
<section class="card border-0 shadow-sm mt-5" aria-labelledby="proComparisonTitle">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap align-items-baseline justify-content-between gap-2 mb-3">
            <h2 class="h5 fw-bold mb-0" id="proComparisonTitle"><i class="fa-solid fa-layer-group text-primary me-2"></i>Free vs. Pro Complete Lesson Pack</h2>
            <span class="small text-secondary">Teacher Pro from <?= plan_price_tag('monthly') ?>/month</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-3">
                <thead>
                    <tr>
                        <th scope="col" class="fw-semibold">What you get</th>
                        <th scope="col" class="text-center fw-semibold"><?= e($freeColumnTitle) ?></th>
                        <th scope="col" class="text-center fw-semibold"><span class="badge badge-members">Pro Lesson Pack</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($comparisonRows as [$feature, $inFree]): ?>
                        <tr>
                            <td><?= e($feature) ?></td>
                            <td class="text-center">
                                <?php if ($inFree): ?>
                                    <i class="fa-solid fa-check text-success" aria-hidden="true"></i><span class="visually-hidden">Included</span>
                                <?php else: ?>
                                    <i class="fa-solid fa-minus text-secondary" aria-hidden="true"></i><span class="visually-hidden">Not included</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><i class="fa-solid fa-check text-success" aria-hidden="true"></i><span class="visually-hidden">Included</span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="small text-secondary mb-3">Contents vary by resource &mdash; each pack lists its files under &ldquo;What&rsquo;s Included&rdquo;.</p>

        <?php if (!empty($proPackSuggestion)): ?>
            <p class="mb-3">
                <i class="fa-solid fa-star text-warning me-1"></i>Fuller Pro pack for this grade:
                <a href="<?= e(base_url('resource.php?slug=' . rawurlencode($proPackSuggestion['slug']))) ?>"
                   data-ga-event="pro_pack_suggestion_click" data-ga-params="<?= $gaParams('resource_comparison') ?>"><?= e($proPackSuggestion['title']) ?></a>
            </p>
        <?php endif; ?>

        <div class="d-flex flex-wrap align-items-center gap-2">
            <a href="<?= e($upgradeUrl) ?>" class="btn btn-primary"
               data-ga-event="upgrade_click" data-ga-params="<?= $gaParams('resource_comparison') ?>">
                <?= $isLoggedIn ? 'Get Annual Pro &mdash; ' . plan_price_tag('annual') . '/year' : 'See Teacher Pro plans' ?>
            </a>
            <a href="<?= e(base_url('pricing.php')) ?>" class="btn btn-outline-secondary"
               data-ga-event="upgrade_click" data-ga-params="<?= $gaParams('resource_comparison_pricing') ?>">Compare plans</a>
            <span class="small text-secondary">Monthly <?= plan_price_tag('monthly') ?> or Annual <?= plan_price_tag('annual') ?> (save <?= $annualSavingsTag ?>/year)</span>
        </div>
    </div>
</section>
