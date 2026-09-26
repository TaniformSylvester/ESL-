<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/review-functions.php';

require_login();
$user = current_user();

$page = max(1, (int)($_GET['page'] ?? 1));
$result = get_user_reviews_paginated($user['id'], $page, RESOURCES_PER_PAGE);

$pageTitle = 'My Reviews';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="container py-5">
    <h1 class="fw-bold mb-4">My Reviews</h1>

    <?php if (empty($result['items'])): ?>
        <div class="alert alert-info">
            You haven't reviewed any resources yet. Download a resource and come back here to share what you thought of it.
        </div>
    <?php else: ?>
        <?php foreach ($result['items'] as $review): ?>
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                        <div>
                            <a href="<?= e(base_url('resource.php?slug=' . urlencode($review['resource_slug']))) ?>" class="fw-bold text-decoration-none"><?= e($review['resource_title']) ?></a>
                            <div class="text-warning">
                                <?php for ($s = 1; $s <= 5; $s++): ?><i class="fa-<?= $s <= (int)$review['rating'] ? 'solid' : 'regular' ?> fa-star"></i><?php endfor; ?>
                            </div>
                        </div>
                        <span class="badge <?= $review['status'] === 'approved' ? 'bg-success' : ($review['status'] === 'rejected' ? 'bg-danger' : 'bg-warning text-dark') ?>"><?= e(ucfirst($review['status'])) ?></span>
                    </div>
                    <?php if (!empty($review['review_text'])): ?>
                        <p class="mt-2 mb-2"><?= nl2br(e($review['review_text'])) ?></p>
                    <?php endif; ?>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small text-secondary"><?= e(format_date($review['created_at'])) ?></span>
                        <a href="<?= e(base_url('resource.php?slug=' . urlencode($review['resource_slug']) . '#reviews')) ?>" class="btn btn-sm btn-outline-primary">Edit Review</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <?= render_pagination($result['page'], $result['total_pages']) ?>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
