<?php
/**
 * Public-site header. Expects init.php to already be loaded.
 * Pages may set $pageTitle and $pageDescription before including this file.
 */
require_once __DIR__ . '/ads-functions.php';

$pageTitle = $pageTitle ?? SITE_NAME;
$pageDescription = $pageDescription ?? SITE_DESCRIPTION;
$pageImage = $pageImage ?? asset_url('images/og-image-icon.png');
$pageRobots = $pageRobots ?? 'index, follow';
$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $_SESSION['user_role'] ?? null;
$showAds = should_show_ads();
$canonicalUrl = rtrim(SITE_URL, '/') . strip_tracking_params($_SERVER['REQUEST_URI'] ?? '/');
$organizationSchema = [
    '@context' => 'https://schema.org',
    '@type'    => 'Organization',
    'name'     => SITE_NAME,
    'url'      => base_url(),
    'logo'     => asset_url('images/og-image-icon.png'),
];
$websiteSchema = [
    '@context'        => 'https://schema.org',
    '@type'           => 'WebSite',
    'name'            => SITE_NAME,
    'url'             => base_url(),
    'potentialAction' => [
        '@type'       => 'SearchAction',
        'target'      => ['@type' => 'EntryPoint', 'urlTemplate' => base_url('resources.php') . '?search={search_term_string}'],
        'query-input' => 'required name=search_term_string',
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | <?= e(SITE_NAME) ?></title>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <meta name="robots" content="<?= e($pageRobots) ?>">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($pageDescription) ?>">
    <meta property="og:site_name" content="<?= e(SITE_NAME) ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= e($canonicalUrl) ?>">
    <meta property="og:image" content="<?= e($pageImage) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($pageTitle) ?>">
    <meta name="twitter:description" content="<?= e($pageDescription) ?>">
    <meta name="twitter:image" content="<?= e($pageImage) ?>">
    <link rel="canonical" href="<?= e($canonicalUrl) ?>">
    <script type="application/ld+json"><?= json_encode($organizationSchema, JSON_UNESCAPED_SLASHES) ?></script>
    <script type="application/ld+json"><?= json_encode($websiteSchema, JSON_UNESCAPED_SLASHES) ?></script>

    <link rel="icon" type="image/svg+xml" href="<?= e(asset_url('images/logo.svg')) ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= e(asset_url('images/favicon-32x32.png')) ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= e(asset_url('images/favicon-16x16.png')) ?>">
    <link rel="icon" href="<?= e(asset_url('images/favicon.ico')) ?>">
    <link rel="apple-touch-icon" href="<?= e(asset_url('images/apple-touch-icon.png')) ?>">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="<?= e(asset_url('css/style.css')) ?>">

    <?php if ($showAds): ?>
        <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=<?= e(ADSENSE_PUBLISHER_ID) ?>" crossorigin="anonymous"></script>
    <?php endif; ?>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold d-flex align-items-center" href="<?= e(base_url()) ?>">
            <img src="<?= e(asset_url('images/logo.svg')) ?>" alt="<?= e(SITE_NAME) ?> logo" width="28" height="28" class="me-2">
            <?= e(SITE_NAME) ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="<?= e(base_url()) ?>">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= e(base_url('resources.php')) ?>">Resources</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= e(base_url('teacher-hub.php')) ?>">Teacher Hub</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= e(base_url('pricing.php')) ?>">Pricing</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= e(base_url('about.php')) ?>">About</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= e(base_url('contact.php')) ?>">Contact</a></li>
            </ul>

            <ul class="navbar-nav align-items-lg-center gap-lg-2">
                <?php if ($isLoggedIn): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= e(base_url('dashboard.php')) ?>">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e(base_url('member/favorites.php')) ?>">Favorites</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e(base_url('member/downloads.php')) ?>">Downloads</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e(base_url('member/reviews.php')) ?>">My Reviews</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e(base_url('member/subscription.php')) ?>">Subscription</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e(base_url('member/profile.php')) ?>">Profile</a></li>
                    <?php if ($userRole === 'admin'): ?>
                        <li class="nav-item"><a class="nav-link" href="<?= e(base_url('admin/index.php')) ?>">Admin Panel</a></li>
                    <?php endif; ?>
                    <li class="nav-item"><a class="nav-link" href="<?= e(base_url('member/logout.php')) ?>">Logout</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="<?= e(base_url('login.php')) ?>">Login</a></li>
                    <li class="nav-item">
                        <a class="btn btn-primary btn-sm px-3" href="<?= e(base_url('register.php')) ?>">Join Now</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<?php $flashMessages = flash_get(); ?>
<?php if (!empty($flashMessages)): ?>
    <div class="container mt-3">
        <?php foreach ($flashMessages as $flash): ?>
            <?php
                $alertClass = match ($flash['type']) {
                    'success' => 'alert-success',
                    'error'   => 'alert-danger',
                    'warning' => 'alert-warning',
                    default   => 'alert-info',
                };
            ?>
            <div class="alert <?= e($alertClass) ?> alert-dismissible fade show" role="alert">
                <?= e($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<main>
