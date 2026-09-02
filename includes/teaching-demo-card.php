<?php
/**
 * Renders one Teaching Demo entry (see includes/teaching-demos.php).
 * Expects $demo (array, one entry from get_teaching_demos()) in scope.
 * Reusable so a future homepage refresh — or a "browse all demos" page —
 * can render additional demos with this same partial.
 */
$hasVideo = !empty($demo['video_type']) && ($demo['video_id'] || $demo['video_url']);
?>
<div class="card shadow-sm border-0 overflow-hidden">
    <div class="row g-0">
        <div class="col-lg-7">
            <?php if ($hasVideo): ?>
                <div class="teaching-demo-player position-relative bg-dark"
                     style="aspect-ratio:16/9;"
                     data-video-type="<?= e($demo['video_type']) ?>"
                     data-video-id="<?= e($demo['video_id'] ?? '') ?>"
                     data-video-url="<?= e($demo['video_url'] ?? '') ?>"
                     data-video-title="<?= e($demo['title']) ?>">
                    <?php if (!empty($demo['poster_image'])): ?>
                        <img src="<?= e($demo['poster_image']) ?>" alt="" class="w-100 h-100" style="object-fit:cover;" loading="lazy">
                    <?php endif; ?>
                    <button type="button" class="teaching-demo-play-btn btn position-absolute top-50 start-50 translate-middle rounded-circle d-flex align-items-center justify-content-center"
                            aria-label="Watch teaching demo: <?= e($demo['title']) ?>">
                        <i class="fa-solid fa-play"></i>
                    </button>
                </div>
            <?php else: ?>
                <div class="d-flex flex-column align-items-center justify-content-center text-center bg-light text-primary p-4" style="aspect-ratio:16/9;">
                    <i class="fa-solid fa-clapperboard fa-3x mb-3"></i>
                    <p class="fw-bold mb-1">Teaching Demo Coming Soon</p>
                    <p class="small text-secondary mb-0">We're preparing a real classroom-style walkthrough for this lesson.</p>
                </div>
            <?php endif; ?>
        </div>
        <div class="col-lg-5">
            <div class="card-body h-100 d-flex flex-column p-4 p-lg-5">
                <div class="mb-2">
                    <span class="badge bg-light text-dark border"><?= e($demo['grade']) ?></span>
                    <span class="badge bg-light text-dark border"><?= e($demo['subject']) ?></span>
                    <?php if ($hasVideo && !empty($demo['duration'])): ?>
                        <span class="badge bg-light text-dark border"><i class="fa-regular fa-clock me-1"></i><?= e($demo['duration']) ?></span>
                    <?php endif; ?>
                </div>
                <h3 class="h5 fw-bold mb-2"><?= e($demo['topic']) ?></h3>
                <p class="text-secondary small mb-3"><?= e($demo['description']) ?></p>

                <?php if (!empty($demo['whats_included'])): ?>
                    <p class="fw-bold small text-uppercase text-secondary mb-2">What You'll See</p>
                    <ul class="list-unstyled small mb-4">
                        <?php foreach ($demo['whats_included'] as $item): ?>
                            <li class="mb-1"><i class="fa-solid fa-check text-success me-2"></i><?= e($item) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <div class="mt-auto">
                    <a href="<?= e($demo['resource_link']) ?>" class="btn btn-primary">Explore Teaching Resources</a>
                </div>
            </div>
        </div>
    </div>
</div>
