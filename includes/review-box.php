<?php
/**
 * The "write a review" box on a resource page — extracted out of
 * resource.php so the exact same markup (including a fresh CSRF token)
 * can be rendered both on a normal page load and by
 * api/resource-download-status.php after a successful download, without
 * duplicating the review-eligibility logic or the form HTML in two places.
 *
 * Expects in scope: $resource (array), $isLoggedIn (bool),
 * $canWriteReview (bool), $myReview (?array), $reviewErrors (array).
 */
?>
<?php if ($isLoggedIn && $canWriteReview): ?>
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <h3 class="h6 fw-bold mb-3"><?= $myReview ? 'Update Your Review' : 'How would you rate this resource?' ?></h3>
            <?php if (!empty($reviewErrors['general'])): ?><div class="alert alert-danger"><?= e($reviewErrors['general']) ?></div><?php endif; ?>
            <form method="post" action="<?= e(base_url('resource.php?slug=' . urlencode($resource['slug']))) ?>#reviews" novalidate>
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="submit_review">
                <div class="star-rating-input mb-2">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <input type="radio" name="rating" id="star<?= $i ?>" value="<?= $i ?>" <?= (int)($myReview['rating'] ?? 0) === $i ? 'checked' : '' ?> required>
                        <label for="star<?= $i ?>" title="<?= $i ?> star<?= $i === 1 ? '' : 's' ?>"><i class="fa-solid fa-star"></i></label>
                    <?php endfor; ?>
                </div>
                <?php if (isset($reviewErrors['rating'])): ?><div class="text-danger small mb-2"><?= e($reviewErrors['rating']) ?></div><?php endif; ?>
                <label class="form-label" for="review_text">Write a review <span class="text-secondary">(optional)</span></label>
                <textarea class="form-control <?= isset($reviewErrors['review_text']) ? 'is-invalid' : '' ?>" id="review_text" name="review_text" rows="3" maxlength="2000"><?= e($myReview['review_text'] ?? '') ?></textarea>
                <?php if (isset($reviewErrors['review_text'])): ?><div class="invalid-feedback"><?= e($reviewErrors['review_text']) ?></div><?php endif; ?>
                <button type="submit" class="btn btn-primary mt-3"><?= $myReview ? 'Update Review' : 'Submit Review' ?></button>
            </form>
            <?php if ($myReview): ?>
                <p class="small text-secondary mt-2 mb-0">
                    Your review status:
                    <span class="badge <?= $myReview['status'] === 'approved' ? 'bg-success' : ($myReview['status'] === 'rejected' ? 'bg-danger' : 'bg-warning text-dark') ?>"><?= e(ucfirst($myReview['status'])) ?></span>
                    <?php if ($myReview['status'] === 'approved'): ?> &mdash; editing it will send it back for re-approval.<?php endif; ?>
                </p>
            <?php endif; ?>
        </div>
    </div>
<?php elseif (!$isLoggedIn): ?>
    <div class="alert alert-light border small mb-4">
        <a href="<?= e(base_url('login.php')) ?>">Sign in</a> to review this resource.
    </div>
<?php else: ?>
    <div class="alert alert-light border small mb-4">Download this resource to write a review.</div>
<?php endif; ?>
