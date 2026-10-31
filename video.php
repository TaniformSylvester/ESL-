<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/video-functions.php';
require_once __DIR__ . '/includes/resource-functions.php';
require_once __DIR__ . '/includes/favorites-functions.php';
require_once __DIR__ . '/includes/seo-functions.php';

$slug = trim((string)($_GET['slug'] ?? ''));
$video = $slug !== '' ? get_published_video_by_slug($slug) : null;

if (!$video) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$videoResources = get_video_resources((int)$video['id']);

// Manual admin picks take priority; fall back to the automatic
// subject/grade relevance query only when nothing has been manually chosen
// — same pattern as resource.php's Related Resources section.
$relatedVideos = get_manual_related_videos((int)$video['id']);
if (empty($relatedVideos)) {
    $relatedVideos = get_related_videos($video, 3);
}

$objectivesList = !empty($video['learning_objectives']) ? array_filter(array_map('trim', explode("\n", $video['learning_objectives']))) : [];
$keyConceptsList = !empty($video['key_concepts']) ? array_filter(array_map('trim', explode("\n", $video['key_concepts']))) : [];

$pageTitle = generate_video_seo_title($video);
$pageDescription = generate_video_seo_description($video);
$pageImage = video_display_thumbnail_url($video);

$breadcrumbItems = [
    ['name' => 'Videos', 'url' => base_url('videos.php')],
    ['name' => $video['title'], 'url' => base_url('video.php?slug=' . rawurlencode($video['slug']))],
];
$breadcrumbSchema = [
    '@context'        => 'https://schema.org',
    '@type'           => 'BreadcrumbList',
    'itemListElement' => array_map(static function (array $item, int $index): array {
        return ['@type' => 'ListItem', 'position' => $index + 1, 'name' => $item['name'], 'item' => $item['url']];
    }, $breadcrumbItems, array_keys($breadcrumbItems)),
];

// Only fields we actually have real values for — never a fabricated
// duration/upload date, matching the site's never-fabricate SEO rule.
$videoSchema = [
    '@context'    => 'https://schema.org',
    '@type'       => 'VideoObject',
    'name'        => $video['title'],
    'description' => $video['description'] ?? $pageDescription,
    'thumbnailUrl' => video_display_thumbnail_url($video),
    'uploadDate'  => date('c', strtotime($video['created_at'])),
    'embedUrl'    => 'https://www.youtube-nocookie.com/embed/' . rawurlencode($video['youtube_video_id']),
];

require_once __DIR__ . '/includes/header.php';
?>
<script type="application/ld+json"><?= json_encode($videoSchema, JSON_UNESCAPED_SLASHES) ?></script>
<script type="application/ld+json"><?= json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES) ?></script>
<div class="container py-5">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="<?= e(base_url('videos.php')) ?>">Videos</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= e($video['title']) ?></li>
        </ol>
    </nav>

    <div class="row g-4 g-lg-5">
        <div class="col-lg-8 mx-auto">
            <div class="mb-2">
                <?php if (!empty($video['grade_level'])): ?>
                    <span class="badge bg-light text-dark border"><?= e($video['grade_level']) ?></span>
                <?php endif; ?>
                <span class="badge bg-light text-dark border"><?= e($video['subject_name']) ?></span>
                <?php if (!empty($video['topic'])): ?>
                    <span class="badge bg-light text-dark border"><?= e($video['topic']) ?></span>
                <?php endif; ?>
                <?php if (!empty($video['duration'])): ?>
                    <span class="badge bg-light text-dark border"><i class="fa-regular fa-clock me-1"></i><?= e($video['duration']) ?></span>
                <?php endif; ?>
            </div>

            <h1 class="fw-bold mb-3"><?= e($video['title']) ?></h1>

            <?php require __DIR__ . '/includes/video-embed-player.php'; ?>

            <?php if (!empty($video['description'])): ?>
                <p class="text-secondary mt-4"><?= nl2br(e($video['description'])) ?></p>
            <?php endif; ?>

            <?php if (!empty($objectivesList)): ?>
                <h2 class="h5 fw-bold mt-4 mb-2">What You'll Learn</h2>
                <ul>
                    <?php foreach ($objectivesList as $objective): ?>
                        <li><?= e($objective) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if (!empty($keyConceptsList)): ?>
                <h2 class="h5 fw-bold mt-4 mb-2">Key Concepts</h2>
                <ul>
                    <?php foreach ($keyConceptsList as $concept): ?>
                        <li><?= e($concept) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if (!empty($video['transcript'])): ?>
                <details class="mt-4">
                    <summary class="fw-bold" style="cursor:pointer;">Video Transcript</summary>
                    <p class="text-secondary mt-2"><?= nl2br(e($video['transcript'])) ?></p>
                </details>
            <?php endif; ?>

            <?php if (!empty($videoResources)): ?>
                <hr class="my-5">
                <h2 class="h4 fw-bold mb-4">Practice This Skill</h2>
                <div class="row row-cols-1 row-cols-sm-2 g-4">
                    <?php foreach ($videoResources as $resource): ?>
                        <?php include __DIR__ . '/includes/resource-card.php'; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($relatedVideos)): ?>
                <hr class="my-5">
                <h2 class="h4 fw-bold mb-4">More <?= e($video['subject_name']) ?> Videos</h2>
                <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4">
                    <?php foreach ($relatedVideos as $relatedVideo): ?>
                        <?php $video2 = $video; $video = $relatedVideo; // video-card.php expects $video in scope ?>
                        <?php include __DIR__ . '/includes/video-card.php'; ?>
                        <?php $video = $video2; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
