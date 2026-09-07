<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/bundle-functions.php';

header('Content-Type: application/xml; charset=UTF-8');

$staticPages = [
    ['loc' => base_url(), 'priority' => '1.0'],
    ['loc' => base_url('resources.php'), 'priority' => '0.9'],
    ['loc' => base_url('teacher-hub.php'), 'priority' => '0.8'],
    ['loc' => base_url('teacher-tools.php'), 'priority' => '0.6'],
    ['loc' => base_url('bundles.php'), 'priority' => '0.6'],
    ['loc' => base_url('pricing.php'), 'priority' => '0.8'],
    ['loc' => base_url('about.php'), 'priority' => '0.5'],
    ['loc' => base_url('contact.php'), 'priority' => '0.5'],
    ['loc' => base_url('terms.php'), 'priority' => '0.3'],
    ['loc' => base_url('privacy.php'), 'priority' => '0.3'],
];

// Note: getDB() itself halts the request with a friendly error if the
// database is unreachable (see includes/db.php), the same as every
// other page, so no extra error handling is needed here.
$stmt = getDB()->query("SELECT slug, updated_at FROM resources WHERE is_published = 1 AND status = 'active' ORDER BY updated_at DESC");
$resources = $stmt->fetchAll();

$stmt = getDB()->query('SELECT slug, updated_at FROM guides WHERE is_published = 1 ORDER BY updated_at DESC');
$guides = $stmt->fetchAll();

$bundlePages = get_published_bundles();

// Subject and category listing pages (resources.php?subject_id=/?category_id=)
// are real, uniquely-titled landing pages (see generate_resources_listing_seo())
// — but only worth indexing when they actually have published resources behind
// them, matching that same function's own noindex-when-empty rule so the
// sitemap never points Google at a page it would tell it not to index.
$stmt = getDB()->query(
    "SELECT s.id, MAX(r.updated_at) AS last_updated
     FROM subjects s
     INNER JOIN resources r ON r.subject_id = s.id AND r.is_published = 1 AND r.status = 'active'
     GROUP BY s.id"
);
$subjectPages = $stmt->fetchAll();

$stmt = getDB()->query(
    "SELECT c.id, MAX(r.updated_at) AS last_updated
     FROM categories c
     INNER JOIN resources r ON r.category_id = c.id AND r.is_published = 1 AND r.status = 'active'
     GROUP BY c.id"
);
$categoryPages = $stmt->fetchAll();

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($staticPages as $page): ?>
    <url>
        <loc><?= e($page['loc']) ?></loc>
        <priority><?= e($page['priority']) ?></priority>
    </url>
<?php endforeach; ?>
<?php foreach ($subjectPages as $subjectPage): ?>
    <url>
        <loc><?= e(base_url('resources.php?subject_id=' . (int)$subjectPage['id'])) ?></loc>
        <lastmod><?= e(date('Y-m-d', strtotime($subjectPage['last_updated']))) ?></lastmod>
        <priority>0.7</priority>
    </url>
<?php endforeach; ?>
<?php foreach ($categoryPages as $categoryPage): ?>
    <url>
        <loc><?= e(base_url('resources.php?category_id=' . (int)$categoryPage['id'])) ?></loc>
        <lastmod><?= e(date('Y-m-d', strtotime($categoryPage['last_updated']))) ?></lastmod>
        <priority>0.6</priority>
    </url>
<?php endforeach; ?>
<?php foreach ($resources as $resource): ?>
    <url>
        <loc><?= e(base_url('resource.php?slug=' . urlencode($resource['slug']))) ?></loc>
        <lastmod><?= e(date('Y-m-d', strtotime($resource['updated_at']))) ?></lastmod>
        <priority>0.6</priority>
    </url>
<?php endforeach; ?>
<?php foreach ($guides as $guide): ?>
    <url>
        <loc><?= e(base_url('teacher-hub-guide.php?slug=' . urlencode($guide['slug']))) ?></loc>
        <lastmod><?= e(date('Y-m-d', strtotime($guide['updated_at']))) ?></lastmod>
        <priority>0.6</priority>
    </url>
<?php endforeach; ?>
<?php foreach ($bundlePages as $bundlePage): ?>
    <url>
        <loc><?= e(base_url('bundle.php?slug=' . urlencode($bundlePage['slug']))) ?></loc>
        <lastmod><?= e(date('Y-m-d', strtotime($bundlePage['updated_at']))) ?></lastmod>
        <priority>0.5</priority>
    </url>
<?php endforeach; ?>
</urlset>
