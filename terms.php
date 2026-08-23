<?php
require_once __DIR__ . '/includes/init.php';

$pageTitle = 'Terms & Conditions';
require_once __DIR__ . '/includes/header.php';
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <h1 class="fw-bold mb-4">Terms &amp; Conditions</h1>
            <p class="text-secondary small">Last updated: <?= date('d M Y') ?></p>

            <p>By creating an account or using <?= e(SITE_NAME) ?>, you agree to the following terms.</p>

            <h2 class="h5 fw-bold mt-4">1. Plans</h2>
            <p><?= e(SITE_NAME) ?> offers a Free plan and a paid Teacher Pro plan (Monthly or Annual):</p>
            <ul>
                <li><strong>Free:</strong> browse the complete resource library and download up to 5 resources per calendar month. This allowance resets automatically at the start of each calendar month; it does not carry over.</li>
                <li><strong>Teacher Pro Monthly / Annual:</strong> unlimited downloads of every "Members Only" resource for as long as the membership remains active.</li>
            </ul>

            <h2 class="h5 fw-bold mt-4">2. Membership Activation &amp; Expiry</h2>
            <p>Membership is currently activated manually after payment is confirmed: a Monthly plan runs for 30 days and an Annual plan for 365 days from the date of approval. If a membership is not renewed before it expires, the account automatically reverts to the Free plan (5 downloads/month) rather than being suspended.</p>

            <h2 class="h5 fw-bold mt-4">3. Use of Resources (Licensing)</h2>
            <p>Resources downloaded from <?= e(SITE_NAME) ?>, whether under the Free or Pro plan, are licensed for use by the downloading teacher in their own classroom(s) only. Redistributing, reselling, sharing account access, or publicly re-hosting downloaded files is not permitted.</p>

            <h2 class="h5 fw-bold mt-4">4. Payments &amp; Approval</h2>
            <p>Membership payments are currently processed manually via bank transfer or PromptPay. Submitting a payment does not guarantee approval; our team reviews each submission (including the selected plan, amount, and reference number) before activating or extending membership. A rejected or still-pending payment does not grant Pro access.</p>

            <h2 class="h5 fw-bold mt-4">5. Account Responsibility</h2>
            <p>You are responsible for keeping your account credentials confidential and for all activity under your account, including download activity counted against your plan's limits.</p>

            <h2 class="h5 fw-bold mt-4">6. Changes</h2>
            <p>We may update these terms, pricing, plans, or the resource library from time to time. Continued use of <?= e(SITE_NAME) ?> after changes means you accept the updated terms.</p>

            <h2 class="h5 fw-bold mt-4">7. Contact</h2>
            <p>Questions about these terms can be sent to <a href="mailto:<?= e(CONTACT_EMAIL) ?>"><?= e(CONTACT_EMAIL) ?></a>.</p>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
