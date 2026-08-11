<?php
/**
 * Admin-area header/sidebar layout. Expects init.php + auth.php to already
 * be loaded and require_admin() to have already been called by the page.
 */

$pageTitle = $pageTitle ?? 'Admin';
$adminUser = current_user();
$currentScript = basename($_SERVER['SCRIPT_NAME']);

$navItems = [
    ['label' => 'Dashboard',      'href' => 'admin/index.php',       'icon' => 'fa-gauge',        'match' => ['index.php']],
    ['label' => 'Users',          'href' => 'admin/users.php',       'icon' => 'fa-users',        'match' => ['users.php', 'user.php']],
    ['label' => 'Resources',      'href' => 'admin/resources.php',   'icon' => 'fa-book',         'match' => ['resources.php', 'resource-add.php', 'resource-edit.php']],
    ['label' => 'Categories',     'href' => 'admin/categories.php',  'icon' => 'fa-tags',         'match' => ['categories.php']],
    ['label' => 'Payments',       'href' => 'admin/payments.php',    'icon' => 'fa-money-bill',   'match' => ['payments.php']],
    ['label' => 'Subscriptions',  'href' => 'admin/subscriptions.php', 'icon' => 'fa-id-card',    'match' => ['subscriptions.php']],
    ['label' => 'Settings',       'href' => 'admin/settings.php',    'icon' => 'fa-gear',         'match' => ['settings.php']],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | <?= e(SITE_NAME) ?> Admin</title>
    <meta name="robots" content="noindex, nofollow">

    <link rel="icon" type="image/svg+xml" href="<?= e(asset_url('images/logo.svg')) ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= e(asset_url('images/favicon-32x32.png')) ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= e(asset_url('images/favicon-16x16.png')) ?>">
    <link rel="icon" href="<?= e(asset_url('images/favicon.ico')) ?>">
    <link rel="apple-touch-icon" href="<?= e(asset_url('images/apple-touch-icon.png')) ?>">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="<?= e(asset_url('css/style.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('css/admin.css')) ?>">
</head>
<body>

<nav class="navbar navbar-dark bg-dark d-lg-none">
    <div class="container-fluid">
        <button class="btn btn-outline-light btn-sm" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminSidebar">
            <i class="fa-solid fa-bars"></i>
        </button>
        <span class="navbar-brand mb-0 h6 text-white"><?= e(SITE_NAME) ?> Admin</span>
    </div>
</nav>

<div class="d-flex admin-shell">
    <div class="offcanvas-lg offcanvas-start bg-dark text-white admin-sidebar" tabindex="-1" id="adminSidebar">
        <div class="offcanvas-header d-lg-none">
            <span class="fw-bold">Menu</span>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#adminSidebar"></button>
        </div>
        <div class="offcanvas-body d-flex flex-column p-0">
            <div class="p-3 d-none d-lg-block">
                <a href="<?= e(base_url('admin/index.php')) ?>" class="text-white text-decoration-none fw-bold d-flex align-items-center">
                    <img src="<?= e(asset_url('images/logo.svg')) ?>" alt="<?= e(SITE_NAME) ?> logo" width="24" height="24" class="me-2">
                    <?= e(SITE_NAME) ?> Admin
                </a>
            </div>
            <ul class="nav nav-pills flex-column mb-auto px-2">
                <?php foreach ($navItems as $item): ?>
                    <li class="nav-item">
                        <a href="<?= e(base_url($item['href'])) ?>" class="nav-link text-white <?= in_array($currentScript, $item['match'], true) ? 'active' : '' ?>">
                            <i class="fa-solid <?= e($item['icon']) ?> me-2"></i><?= e($item['label']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="p-2 border-top border-secondary mt-3">
                <a href="<?= e(base_url('admin/profile.php')) ?>" class="nav-link text-white <?= $currentScript === 'profile.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-user me-2"></i>Profile
                </a>
                <a href="<?= e(base_url()) ?>" class="nav-link text-white" target="_blank">
                    <i class="fa-solid fa-arrow-up-right-from-square me-2"></i>View Site
                </a>
                <a href="<?= e(base_url('admin/logout.php')) ?>" class="nav-link text-white">
                    <i class="fa-solid fa-right-from-bracket me-2"></i>Logout
                </a>
            </div>
        </div>
    </div>

    <main class="flex-grow-1 admin-content">
        <div class="d-none d-lg-flex justify-content-end align-items-center border-bottom bg-white px-4 py-2">
            <span class="small text-secondary">Signed in as <strong><?= e($adminUser['first_name'] ?? '') ?></strong></span>
        </div>

        <div class="p-4">
            <?php $flashMessages = flash_get(); ?>
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

            <h1 class="h4 fw-bold mb-4"><?= e($pageTitle) ?></h1>
