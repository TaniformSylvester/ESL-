<?php
/**
 * Short upgrade nudge for Free-plan accounts, rendered hidden on
 * resource.php and revealed by assets/js/downloads.js the moment a
 * download link is clicked (the file itself downloads normally). Never
 * rendered for active Pro members or admins.
 *
 * Expects in scope: $resource.
 */
?>
<div class="alert alert-info alert-dismissible mt-3 mb-0 js-post-download-upsell" role="status" hidden>
    <p class="fw-bold mb-1"><i class="fa-solid fa-circle-check text-success me-1"></i>Your download is starting.</p>
    <p class="small mb-2">
        Want the full lesson next time? Teacher Pro packs add editable slides, flashcards, a quiz, homework and a rubric &mdash;
        <strong><?= plan_price_tag('monthly') ?>/month</strong> or <strong><?= plan_price_tag('annual') ?>/year</strong> (best value).
    </p>
    <a href="<?= e(base_url('pricing.php')) ?>" class="btn btn-sm btn-primary"
       data-ga-event="upgrade_click"
       data-ga-params="<?= e(json_encode(['location' => 'post_download', 'resource_id' => (int)$resource['id']])) ?>">See Pro plans</a>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
