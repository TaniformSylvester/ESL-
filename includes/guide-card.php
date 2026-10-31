<?php
/**
 * Renders one Teacher Hub guide card. Expects $guide (assoc array from
 * get_recent_guides()/get_guides_by_category(), which includes category,
 * title, slug, and optionally summary) to be set before this file is included.
 */
?>
<div class="col">
    <a href="<?= e(base_url('teacher-hub-guide.php?slug=' . urlencode($guide['slug']))) ?>" class="card guide-card h-100 text-decoration-none text-reset">
        <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="icon-bubble tone-teal" style="width:2.4rem;height:2.4rem;font-size:1rem" aria-hidden="true"><i class="fa-solid fa-lightbulb"></i></span>
                <?php if (!empty($guide['category']) && isset(GUIDE_CATEGORIES[$guide['category']])): ?>
                    <span class="badge badge-neutral"><?= e(GUIDE_CATEGORIES[$guide['category']]) ?></span>
                <?php endif; ?>
            </div>
            <h3 class="h6 mb-2"><?= e($guide['title']) ?></h3>
            <?php if (!empty($guide['summary'])): ?>
                <p class="small text-secondary mb-3 flex-grow-1"><?= e($guide['summary']) ?></p>
            <?php else: ?>
                <div class="flex-grow-1"></div>
            <?php endif; ?>
            <span class="link-arrow" style="color:var(--tl-teal-ink)">Read Guide <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
        </div>
    </a>
</div>
