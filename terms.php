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

            <h2 class="h5 fw-bold mt-4">1. Membership</h2>
            <p>Membership grants access to download resources marked "Members Only" for as long as your membership remains active. Membership is currently activated manually after payment is confirmed and lasts one month from the date of approval.</p>

            <h2 class="h5 fw-bold mt-4">2. Use of Resources</h2>
            <p>Resources downloaded from <?= e(SITE_NAME) ?> are licensed for use by the downloading teacher in their own classroom(s). Redistributing, reselling, or publicly re-hosting downloaded files is not permitted.</p>

            <h2 class="h5 fw-bold mt-4">3. Payments</h2>
            <p>Membership payments are currently processed manually. Submitting a payment does not guarantee approval; our team reviews each submission before activating membership.</p>

            <h2 class="h5 fw-bold mt-4">4. Account Responsibility</h2>
            <p>You are responsible for keeping your account credentials confidential and for all activity under your account.</p>

            <h2 class="h5 fw-bold mt-4">5. Changes</h2>
            <p>We may update these terms, pricing, or the resource library from time to time. Continued use of <?= e(SITE_NAME) ?> after changes means you accept the updated terms.</p>

            <h2 class="h5 fw-bold mt-4">6. Contact</h2>
            <p>Questions about these terms can be sent to <a href="mailto:<?= e(CONTACT_EMAIL) ?>"><?= e(CONTACT_EMAIL) ?></a>.</p>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
