<?php
/**
 * Renders the click-to-play video embed. Expects $video (assoc array with
 * at least youtube_video_id and title) in scope. Reused by video.php's main
 * embed and resource.php's "Watch the Lesson" section. See
 * assets/js/video-embed.js for the click behavior — nothing loads until
 * the visitor clicks the play button.
 */
?>
<div class="video-embed-player position-relative bg-dark rounded overflow-hidden"
     style="aspect-ratio:16/9;"
     data-video-id="<?= e($video['youtube_video_id']) ?>"
     data-video-title="<?= e($video['title']) ?>">
    <img src="<?= e(video_display_thumbnail_url($video)) ?>" alt="" class="w-100 h-100" style="object-fit:cover;" loading="lazy">
    <button type="button" class="video-embed-play-btn btn position-absolute top-50 start-50 translate-middle rounded-circle d-flex align-items-center justify-content-center"
            aria-label="Play video: <?= e($video['title']) ?>">
        <i class="fa-solid fa-play"></i>
    </button>
</div>
