<?php
/**
 * Public-site header. Expects init.php to already be loaded.
 * Pages may set $pageTitle and $pageDescription before including this file.
 */
require_once __DIR__ . '/ads-functions.php';

$pageTitle = $pageTitle ?? SITE_NAME;
$pageDescription = $pageDescription ?? SITE_DESCRIPTION;
$pageImage = $pageImage ?? asset_url('images/og-image.jpg');
$pageRobots = $pageRobots ?? 'index, follow';
$gaCustomEvents = $gaCustomEvents ?? [];
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

// Which top-level nav item is "current" — drives the active underline and aria-current.
$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
$navSection = match (true) {
    $currentScript === 'index.php'                                        => 'home',
    in_array($currentScript, ['resources.php', 'resource.php'], true)     => 'resources',
    in_array($currentScript, ['games.php', 'game.php'], true)             => 'games',
    in_array($currentScript, ['videos.php', 'video.php'], true)           => 'videos',
    in_array($currentScript, ['teacher-hub.php', 'teacher-hub-guide.php', 'teacher-tools.php', 'bundles.php', 'bundle.php', 'request-resource.php'], true) => 'hub',
    $currentScript === 'pricing.php'                                      => 'pricing',
    default                                                               => '',
};
$navItems = [
    'home'      => ['Home', base_url(), 'fa-house'],
    'resources' => ['Resources', base_url('resources.php'), 'fa-book-open'],
    'games'     => ['Games', base_url('games.php'), 'fa-gamepad'],
    'videos'    => ['Videos', base_url('videos.php'), 'fa-circle-play'],
    'hub'       => null, // dropdown, rendered separately
    'pricing'   => ['Pricing', base_url('pricing.php'), 'fa-tag'],
];
$hubLinks = [
    ['Teacher Hub', base_url('teacher-hub.php'), 'fa-lightbulb'],
    ['Teacher Tools', base_url('teacher-tools.php'), 'fa-stopwatch'],
    ['Bundles', base_url('bundles.php'), 'fa-layer-group'],
    ['Request a Resource', base_url('request-resource.php'), 'fa-hand-sparkles'],
];
$currentSearch = $currentScript === 'resources.php' ? trim((string)($_GET['search'] ?? '')) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if (GA_ENABLED && GA_MEASUREMENT_ID !== ''): ?>
    <!-- Google Analytics 4 — minimal funnel-diagnosis tracking (config/analytics.php) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?= e(GA_MEASUREMENT_ID) ?>"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '<?= e(GA_MEASUREMENT_ID) ?>');
    </script>
    <?php endif; ?>
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

    <meta name="theme-color" content="#FFFFFF">
    <script>document.documentElement.classList.add('js');</script>

    <link rel="icon" type="image/svg+xml" href="<?= e(versioned_asset_url('brand/teachluma-favicon.svg')) ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= e(versioned_asset_url('images/favicon-32x32.png')) ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= e(versioned_asset_url('images/favicon-16x16.png')) ?>">
    <link rel="icon" href="<?= e(versioned_asset_url('images/favicon.ico')) ?>" sizes="any">
    <link rel="apple-touch-icon" href="<?= e(versioned_asset_url('images/apple-touch-icon.png')) ?>">

    <link rel="preload" href="<?= e(asset_url('fonts/fredoka-latin-700-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?= e(asset_url('fonts/nunito-latin-400-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="<?= e(versioned_asset_url('css/style.css')) ?>">

    <?php if ($showAds): ?>
        <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=<?= e(ADSENSE_PUBLISHER_ID) ?>" crossorigin="anonymous"></script>
    <?php endif; ?>

    <?php if (GA_ENABLED && GA_MEASUREMENT_ID !== '' && !empty($gaCustomEvents)): ?>
    <script>
        <?php foreach ($gaCustomEvents as $gaEvent): ?>
        gtag('event', <?= json_encode($gaEvent['name']) ?>, <?= json_encode($gaEvent['params'] ?? [], JSON_UNESCAPED_SLASHES) ?>);
        <?php endforeach; ?>
    </script>
    <?php endif; ?>
</head>
<body>

<a class="visually-hidden-focusable position-absolute top-0 start-0 m-2 btn btn-accent" style="z-index:1100" href="#main-content">Skip to content</a>

<header class="site-header">
<nav class="navbar navbar-expand-lg" aria-label="Main navigation">
    <div class="container">
        <a class="navbar-brand" href="<?= e(base_url()) ?>">
            <img class="brand-logo" src="<?= e(versioned_asset_url('brand/teachluma-logo.svg')) ?>" alt="<?= e(SITE_NAME) ?> home" width="207" height="40">
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#mainNav" aria-controls="mainNav" aria-label="Open menu">
            <i class="fa-solid fa-bars" aria-hidden="true"></i>
        </button>

        <div class="offcanvas offcanvas-end" tabindex="-1" id="mainNav" aria-labelledby="mainNavLabel">
            <div class="offcanvas-header">
                <img class="brand-logo" src="<?= e(versioned_asset_url('brand/teachluma-logo.svg')) ?>" alt="" width="176" height="34" style="height:34px">
                <h2 class="visually-hidden" id="mainNavLabel">Menu</h2>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close menu"></button>
            </div>
            <div class="offcanvas-body align-items-lg-center flex-grow-1">
                <form class="nav-search d-lg-none mb-3" role="search" action="<?= e(base_url('resources.php')) ?>" method="get">
                    <label class="visually-hidden" for="navSearchMobile">Search resources</label>
                    <i class="fa-solid fa-magnifying-glass nav-search-icon" aria-hidden="true"></i>
                    <input class="form-control" type="search" id="navSearchMobile" name="search" placeholder="Search resources&hellip;" value="<?= e($currentSearch) ?>">
                </form>

                <ul class="navbar-nav site-nav mx-lg-auto">
                    <?php foreach ($navItems as $key => $item): ?>
                        <?php if ($key === 'hub'): ?>
                            <li class="nav-item dropdown navbar-hover-dropdown">
                                <a class="nav-link dropdown-toggle<?= $navSection === 'hub' ? ' active' : '' ?>" href="<?= e(base_url('teacher-hub.php')) ?>" role="button" data-bs-toggle="dropdown" aria-expanded="false"<?= $navSection === 'hub' ? ' aria-current="page"' : '' ?>>
                                    <i class="fa-solid fa-chalkboard-user nav-icon" aria-hidden="true"></i>Teacher Hub <i class="fa-solid fa-chevron-down small ms-1 d-none d-lg-inline" aria-hidden="true" style="font-size:.7em"></i>
                                </a>
                                <ul class="dropdown-menu">
                                    <?php foreach ($hubLinks as [$label, $url, $icon]): ?>
                                        <li><a class="dropdown-item" href="<?= e($url) ?>"><i class="fa-solid <?= e($icon) ?>" aria-hidden="true"></i><?= e($label) ?></a></li>
                                    <?php endforeach; ?>
                                </ul>
                            </li>
                        <?php else: [$label, $url, $icon] = $item; ?>
                            <li class="nav-item">
                                <a class="nav-link<?= $navSection === $key ? ' active' : '' ?>" href="<?= e($url) ?>"<?= $navSection === $key ? ' aria-current="page"' : '' ?>>
                                    <i class="fa-solid <?= e($icon) ?> nav-icon" aria-hidden="true"></i><?= e($label) ?>
                                </a>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>

                <div class="header-actions d-flex align-items-center gap-2">
                    <form class="nav-search nav-search-inline d-none d-xl-block" role="search" action="<?= e(base_url('resources.php')) ?>" method="get">
                        <label class="visually-hidden" for="navSearch">Search resources</label>
                        <i class="fa-solid fa-magnifying-glass nav-search-icon" aria-hidden="true"></i>
                        <input class="form-control" type="search" id="navSearch" name="search" placeholder="Search resources&hellip;" value="<?= e($currentSearch) ?>">
                    </form>
                    <div class="dropdown nav-search-toggle d-none d-lg-block">
                        <button type="button" class="btn btn-soft rounded-circle p-0 d-inline-flex align-items-center justify-content-center" style="width:42px;height:42px" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-label="Search resources">
                            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end p-2" style="width:300px">
                            <form class="nav-search" role="search" action="<?= e(base_url('resources.php')) ?>" method="get">
                                <label class="visually-hidden" for="navSearchCompact">Search resources</label>
                                <i class="fa-solid fa-magnifying-glass nav-search-icon" aria-hidden="true"></i>
                                <input class="form-control" type="search" id="navSearchCompact" name="search" placeholder="Search resources&hellip;" value="<?= e($currentSearch) ?>">
                            </form>
                        </div>
                    </div>

                    <?php if ($isLoggedIn): ?>
                        <div class="dropdown navbar-hover-dropdown">
                            <a class="btn btn-outline-primary dropdown-toggle" href="<?= e(base_url('dashboard.php')) ?>" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fa-solid fa-circle-user me-1" aria-hidden="true"></i>My Account
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="<?= e(base_url('dashboard.php')) ?>"><i class="fa-solid fa-gauge" aria-hidden="true"></i>Dashboard</a></li>
                                <li><a class="dropdown-item" href="<?= e(base_url('member/favorites.php')) ?>"><i class="fa-solid fa-heart" aria-hidden="true"></i>Favorites</a></li>
                                <li><a class="dropdown-item" href="<?= e(base_url('member/downloads.php')) ?>"><i class="fa-solid fa-download" aria-hidden="true"></i>Downloads</a></li>
                                <li><a class="dropdown-item" href="<?= e(base_url('member/reviews.php')) ?>"><i class="fa-solid fa-star" aria-hidden="true"></i>My Reviews</a></li>
                                <li><a class="dropdown-item" href="<?= e(base_url('member/subscription.php')) ?>"><i class="fa-solid fa-crown" aria-hidden="true"></i>Subscription</a></li>
                                <li><a class="dropdown-item" href="<?= e(base_url('member/profile.php')) ?>"><i class="fa-solid fa-user" aria-hidden="true"></i>Profile</a></li>
                                <?php if ($userRole === 'admin'): ?>
                                    <li><a class="dropdown-item" href="<?= e(base_url('admin/index.php')) ?>"><i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i>Admin Panel</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?= e(base_url('member/logout.php')) ?>"><i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>Logout</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a class="btn btn-outline-primary" href="<?= e(base_url('login.php')) ?>">Sign In</a>
                        <a class="btn btn-accent" href="<?= e(base_url('register.php')) ?>">Join Free</a>
                    <?php endif; ?>
                </div>

                <div class="offcanvas-extras d-flex gap-3 justify-content-center small mt-4">
                    <a href="<?= e(base_url('about.php')) ?>">About</a>
                    <a href="<?= e(base_url('contact.php')) ?>">Contact</a>
                    <a href="<?= e(base_url('request-resource.php')) ?>">Request a Resource</a>
                </div>
            </div>
        </div>
    </div>
</nav>
</header>

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

<main id="main-content">
