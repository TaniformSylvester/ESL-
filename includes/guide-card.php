<?php
/**
 * Renders one Teacher Hub guide card. Expects $guide (assoc array from
 * get_recent_guides()/get_guides_by_category(), which includes category,
 * title, slug, and optionally summary) to be set before this file is included.
 */
?>
<div class="col">
    <a href="<?= e(base_url('teacher-hub-guide.php?slug=' . urlencode($guide['slug']))) ?>" class="card guide-card shadow-sm border-0 h-100 text-decoration-none text-reset">
        <div class="card-body d-flex flex-column">
            <?php if (!empty($guide['category']) && isset(GUIDE_CATEGORIES[$guide['category']])): ?>
                <span class="badge bg-light text-dark border align-self-start mb-2"><?= e(GUIDE_CATEGORIES[$guide['category']]) ?></span>
            <?php endif; ?>
            <h3 class="h6 fw-bold mb-1"><?= e($guide['title']) ?></h3>
            <?php if (!empty($guide['summary'])): ?>
                <p class="small text-secondary mb-0 flex-grow-1"><?= e($guide['summary']) ?></p>
            <?php endif; ?>
        </div>
    </a>
</div>
