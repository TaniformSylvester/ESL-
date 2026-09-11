<?php
/**
 * Renders one video card. Expects $video (assoc array from the videos
 * table, joined with subject_name) to be set before this file is included.
 * Reused by index.php's "Learn with TeachLuma" section and videos.php's
 * library grid — thumbnail-only, no YouTube iframe is ever loaded here
 * (see assets/js/video-embed.js for the click-to-play embed, which only
 * runs on video.php/resource.php).
 */
?>
<div class="col">
    <div class="card video-card shadow-sm h-100">
        <a href="<?= e(base_url('video.php?slug=' . urlencode($video['slug']))) ?>" class="text-decoration-none text-reset">
            <div class="position-relative">
                <img src="<?= e(video_display_thumbnail_url($video)) ?>" class="card-img-top" alt="<?= e($video['title']) ?>" loading="lazy">
                <span class="position-absolute top-50 start-50 translate-middle rounded-circle bg-dark bg-opacity-75 d-flex align-items-center justify-content-center text-white" style="width:48px;height:48px;">
                    <i class="fa-solid fa-play"></i>
                </span>
                <?php if (!empty($video['duration'])): ?>
                    <span class="badge bg-dark position-absolute bottom-0 end-0 m-2"><?= e($video['duration']) ?></span>
                <?php endif; ?>
            </div>
        </a>
        <div class="card-body d-flex flex-column">
            <div class="d-flex flex-wrap gap-1 mb-2">
                <?php if (!empty($video['grade_level'])): ?>
                    <span class="badge bg-light text-dark border"><?= e($video['grade_level']) ?></span>
                <?php endif; ?>
                <?php if (!empty($video['subject_name'])): ?>
                    <span class="badge bg-light text-dark border"><?= e($video['subject_name']) ?></span>
                <?php endif; ?>
            </div>
            <h3 class="h6 fw-bold mb-1">
                <a class="text-decoration-none text-reset" href="<?= e(base_url('video.php?slug=' . urlencode($video['slug']))) ?>">
                    <?= e($video['title']) ?>
                </a>
            </h3>
            <?php if (!empty($video['description'])): ?>
                <p class="small text-secondary flex-grow-1"><?= e(truncate_text($video['description'], 90)) ?></p>
            <?php endif; ?>
            <a href="<?= e(base_url('video.php?slug=' . urlencode($video['slug']))) ?>" class="btn btn-outline-primary btn-sm mt-2">
                Watch Lesson <i class="fa-solid fa-arrow-right ms-1"></i>
            </a>
        </div>
    </div>
</div>
