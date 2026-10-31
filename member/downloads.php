<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/download-functions.php';

require_login();
$user = current_user();

$page = max(1, (int)($_GET['page'] ?? 1));
$result = get_user_downloads($user['id'], $page, RESOURCES_PER_PAGE);

$pageTitle = 'My Downloads';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="container py-5">
    <h1 class="fw-bold mb-4">My Downloads</h1>

    <?php if (empty($result['items'])): ?>
        <div class="alert alert-info">
            You haven't downloaded anything yet. <a href="<?= e(base_url('resources.php')) ?>">Browse resources</a> to get started.
        </div>
    <?php else: ?>
        <div class="card shadow-sm border-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Resource</th>
                            <th>Type</th>
                            <th>Downloaded</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($result['items'] as $row): ?>
                            <tr>
                                <td><?= e($row['title']) ?></td>
                                <td class="small text-secondary"><?= e($row['resource_type']) ?></td>
                                <td class="small text-secondary"><?= e(format_date($row['downloaded_at'], 'd M Y, g:i a')) ?></td>
                                <td class="text-end">
                                    <?php if ($row['is_published'] && $row['status'] === 'active'): ?>
                                        <a href="<?= e(base_url('member/download.php?id=' . $row['resource_id'])) ?>" class="btn btn-sm btn-outline-primary">Download Again</a>
                                    <?php else: ?>
                                        <span class="text-secondary small">No longer available</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4"><?= render_pagination($result['page'], $result['total_pages']) ?></div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
