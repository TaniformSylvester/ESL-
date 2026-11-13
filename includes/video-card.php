<?php
/**
 * Renders one video card. Expects $video (assoc array from the videos
 * table, joined with subject_name) to be set before this file is included.
 * Reused by index.php's "Learn with TeachLuma" section and videos.php's
 * library grid — thumbnail-only, no YouTube iframe is ever loaded here
 * (see assets/js/video-embed.js for the click-to-play embed, which only
 * runs on video.php/resource.php).
 */
$videoUrl = base_url('video.php?slug=' . urlencode($video['slug']));
$videoTone = !empty($video['subject_name']) ? subject_key($video['subject_name']) : 'esl';
?>
<div class="col">
    <article class="card video-card h-100">
        <a href="<?= e($videoUrl) ?>" class="card-media" tabindex="-1" aria-hidden="true">
            <img src="<?= e(video_display_thumbnail_url($video)) ?>" class="card-img-top" alt="" loading="lazy" width="480" height="270">
            <span class="video-play-chip"><i class="fa-solid fa-play"></i></span>
            <?php if (!empty($video['duration'])): ?>
                <span class="badge bg-dark position-absolute bottom-0 end-0 m-2"><?= e($video['duration']) ?></span>
            <?php endif; ?>
        </a>
        <div class="card-body d-flex flex-column">
            <div class="d-flex flex-wrap gap-1 mb-2">
                <span class="badge badge-subject-videos"><i class="fa-solid fa-circle-play me-1" aria-hidden="true"></i>Video</span>
                <?php if (!empty($video['subject_name'])): ?>
                    <span class="badge badge-subject-<?= e($videoTone) ?>"><?= e($video['subject_name']) ?></span>
                <?php endif; ?>
                <?php if (!empty($video['grade_level'])): ?>
                    <span class="badge badge-grade"><?= e($video['grade_level']) ?></span>
                <?php endif; ?>
            </div>
            <h3 class="h6 mb-1">
                <a class="card-title-link" href="<?= e($videoUrl) ?>"><?= e($video['title']) ?></a>
            </h3>
            <?php if (!empty($video['description'])): ?>
                <p class="small text-secondary flex-grow-1 mb-3"><?= e(truncate_text($video['description'], 90)) ?></p>
            <?php else: ?>
                <div class="flex-grow-1"></div>
            <?php endif; ?>
            <a href="<?= e($videoUrl) ?>" class="link-arrow">
                Watch Lesson <i class="fa-solid fa-arrow-right" aria-hidden="true"></i><span class="visually-hidden">: <?= e($video['title']) ?></span>
            </a>
        </div>
    </article>
</div>
