<?php
require_once __DIR__ . '/includes/init.php';

$pageTitle = 'Privacy Policy';
require_once __DIR__ . '/includes/header.php';
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <h1 class="fw-bold mb-4">Privacy Policy</h1>
            <p class="text-secondary small">Last updated: <?= date('d M Y') ?></p>

            <p>This policy explains what information <?= e(SITE_NAME) ?> collects and how it's used.</p>

            <h2 class="h5 fw-bold mt-4">Information We Collect</h2>
            <ul>
                <li>Account details you provide: name, email, school, country, and optional phone number.</li>
                <li>Payment submission details you provide for manual membership approval (amount, date, reference number, optional screenshot). We do not process or store card details.</li>
                <li>Basic usage information such as which resources you download, used to maintain your download history and improve the resource library.</li>
                <li>Messages you send us through the contact form.</li>
            </ul>

            <h2 class="h5 fw-bold mt-4">How We Use Your Information</h2>
            <p>We use your information to operate your account, manage your membership, respond to your messages, and improve the site. We do not sell your personal information to third parties.</p>

            <h2 class="h5 fw-bold mt-4">Data Security</h2>
            <p>Passwords are stored using industry-standard hashing and are never stored or visible in plain text, including to our own team.</p>

            <h2 class="h5 fw-bold mt-4">Advertising &amp; Cookies</h2>
            <p><?= e(SITE_NAME) ?> shows ads served by Google AdSense to visitors and free-tier members (never to paying members with an active subscription). Google and its partners may use cookies or similar technologies to serve ads based on your prior visits to this or other websites. You can opt out of personalized advertising by visiting <a href="https://adssettings.google.com" target="_blank" rel="noopener">Google Ads Settings</a>, and you can learn more about how Google uses data at <a href="https://policies.google.com/technologies/partner-sites" target="_blank" rel="noopener">policies.google.com/technologies/partner-sites</a>. Where required, a consent banner lets you choose whether to allow personalized advertising.</p>

            <h2 class="h5 fw-bold mt-4">Your Choices</h2>
            <p>You can update your account details at any time from your profile page, or contact us to request that your account be deleted.</p>

            <h2 class="h5 fw-bold mt-4">Contact</h2>
            <p>Questions about this policy can be sent to <a href="mailto:<?= e(CONTACT_EMAIL) ?>"><?= e(CONTACT_EMAIL) ?></a>.</p>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
