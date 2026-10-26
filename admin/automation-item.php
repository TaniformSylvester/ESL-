<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-functions.php';
require_once __DIR__ . '/../includes/automation-functions.php';

require_admin();
$admin = current_user();
$adminId = (int)$admin['id'];

$genItem = automation_available() ? automation_get_item((int)($_GET['id'] ?? $_POST['id'] ?? 0)) : null;
if (!$genItem) {
    flash_set('error', 'That generated resource was not found.');
    redirect('admin/automation.php?tab=review');
}
$id = (int)$genItem['id'];
$qcError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'approve') {
        if (empty($_POST['qc_confirmed'])) {
            $qcError = 'Please confirm the quality checklist before publishing.';
        } else {
            $err = automation_approve_item($id, $adminId);
            if (!$err) {
                log_admin_action($adminId, 'automation_approve', "Approved and published generated resource #{$genItem['resource_id']}: {$genItem['title']}");
                flash_set('success', 'Approved and published.');
                redirect('admin/automation.php?tab=review');
            }
            flash_set('error', $err);
            redirect('admin/automation-item.php?id=' . $id);
        }
    } elseif ($action === 'reject' || $action === 'regenerate') {
        $err = automation_reject_item($id, $adminId, trim((string)($_POST['note'] ?? '')), $action === 'regenerate');
        if (!$err) {
            log_admin_action($adminId, 'automation_' . $action, "Generated resource item #{$id}: {$genItem['title']}");
        }
        flash_set($err ? 'error' : 'success', $err ?: ($action === 'regenerate' ? 'Rejected and queued for regeneration.' : 'Rejected. The draft stays unpublished.'));
        redirect('admin/automation.php?tab=review');
    }
}

$qc = $genItem['qc_report'] ? json_decode($genItem['qc_report'], true) : null;
$breakdown = $genItem['score_breakdown'] ? json_decode($genItem['score_breakdown'], true) : [];
$files = $genItem['resource_id'] ? get_resource_files((int)$genItem['resource_id']) : [];
$social = $genItem['resource_id'] ? automation_social_for((int)$genItem['resource_id']) : null;
$resource = $genItem['resource_id'] ? get_resource_by_id((int)$genItem['resource_id']) : null;
$catLabels = ['educational_accuracy' => 'Educational accuracy (automated indicators)', 'completeness' => 'Completeness', 'formatting' => 'Formatting',
    'age_appropriateness' => 'Age appropriateness', 'visual_quality' => 'Visual quality', 'metadata' => 'Metadata'];

$pageTitle = 'Review generated resource';
require_once __DIR__ . '/../includes/admin-header.php';
?>
<p class="mb-3"><a href="<?= e(base_url('admin/automation.php?tab=' . ($genItem['status'] === 'failed' ? 'failed' : 'review'))) ?>">&larr; Back to Resource Automation</a></p>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card shadow-sm border-0 mb-4"><div class="card-body">
            <div class="d-flex flex-wrap gap-2 mb-2">
                <span class="badge bg-light text-dark border"><?= e(strtoupper($genItem['subject_slug'])) ?> · <?= e($genItem['grade_level']) ?></span>
                <span class="badge <?= $genItem['tier'] === 'free' ? 'badge-free' : 'badge-members' ?>"><?= e(ucfirst($genItem['tier'])) ?></span>
                <span class="badge bg-light text-dark border"><?= e($genItem['resource_type']) ?></span>
                <span class="badge bg-secondary"><?= e(AUTOMATION_ITEM_STATUSES[$genItem['status']] ?? $genItem['status']) ?></span>
            </div>
            <h1 class="h4 fw-bold"><?= e($genItem['title']) ?></h1>
            <p class="text-secondary small mb-3">Topic: <?= e($genItem['topic']) ?> · Category: <?= e((string)$genItem['category_slug']) ?> · Generated <?= e(format_date($genItem['created_at'], 'j M Y, H:i')) ?> · Correction attempts: <?= (int)$genItem['correction_attempts'] ?>/<?= AUTOMATION_MAX_CORRECTIONS ?></p>
            <?php if ($genItem['preview_image']): ?>
                <img src="<?= e(UPLOAD_PREVIEW_URL . '/' . rawurlencode($genItem['preview_image'])) ?>" alt="Preview of <?= e($genItem['title']) ?>" class="img-fluid rounded border mb-3">
            <?php endif; ?>
            <?php if ($resource): ?>
                <p class="mb-1"><?= e((string)$resource['description']) ?></p>
            <?php endif; ?>
        </div></div>

        <?php if ($resource): ?>
        <div class="card shadow-sm border-0 mb-4"><div class="card-body">
            <h2 class="h6 fw-bold">Files</h2>
            <ul class="list-unstyled mb-0">
                <li class="py-1"><i class="fa-solid fa-file-pdf text-danger me-2" aria-hidden="true"></i><a href="<?= e(base_url('admin/automation-file.php?item=' . $id . '&file=main')) ?>"><?= e($resource['file_name']) ?></a> <span class="small text-secondary">(main download, <?= e(format_file_size((int)$resource['file_size'])) ?>)</span></li>
                <?php foreach ($files as $f): ?>
                    <li class="py-1"><i class="fa-solid fa-file me-2 text-secondary" aria-hidden="true"></i><a href="<?= e(base_url('admin/automation-file.php?item=' . $id . '&file=' . (int)$f['id'])) ?>"><?= e($f['file_name']) ?></a> <span class="small text-secondary">(<?= e((string)$f['label']) ?>, <?= e(format_file_size((int)$f['file_size'])) ?>)</span></li>
                <?php endforeach; ?>
            </ul>
            <p class="small text-secondary mt-2 mb-0">Download and open every file before approving. <a href="<?= e(base_url('admin/resource-edit.php?id=' . (int)$genItem['resource_id'])) ?>">Edit this draft</a> in the normal resource editor if anything needs fixing.</p>
        </div></div>
        <?php endif; ?>

        <?php if ($social): ?>
        <div class="card shadow-sm border-0 mb-4"><div class="card-body">
            <h2 class="h6 fw-bold">Prepared social media copy <span class="fw-normal small text-secondary">(never posted automatically)</span></h2>
            <p class="small fw-semibold mb-1">Facebook post</p><pre class="small bg-light p-2 rounded" style="white-space:pre-wrap"><?= e($social['facebook_post']) ?></pre>
            <p class="small fw-semibold mb-1">Pinterest</p><p class="small mb-1"><strong><?= e($social['pinterest_title']) ?></strong></p><p class="small"><?= e($social['pinterest_description']) ?></p>
            <p class="small fw-semibold mb-1">Hashtags</p><p class="small"><?= e($social['hashtags']) ?></p>
            <p class="small fw-semibold mb-1">Suggested promotional visuals</p><ul class="small mb-0"><?php foreach (automation_lines($social['visual_suggestions']) as $v): ?><li><?= e($v) ?></li><?php endforeach; ?></ul>
        </div></div>
        <?php endif; ?>
    </div>

    <div class="col-lg-5">
        <div class="card shadow-sm border-0 mb-4"><div class="card-body">
            <h2 class="h6 fw-bold mb-1">Automated quality score</h2>
            <p class="display-6 fw-bold mb-1"><?= $genItem['quality_score'] !== null ? (int)$genItem['quality_score'] : '—' ?><span class="fs-6 text-secondary">/100</span></p>
            <p class="small text-secondary">Automated indicators only. This is <strong>not</strong> an independent verification of educational accuracy — your review is.</p>
            <?php foreach (AUTOMATION_SCORE_MAX as $cat => $max): $v = (float)($breakdown[$cat] ?? 0); ?>
                <div class="small d-flex justify-content-between"><span><?= e($catLabels[$cat]) ?></span><span><?= rtrim(rtrim(number_format($v, 1), '0'), '.') ?>/<?= $max ?></span></div>
                <div class="progress mb-2" style="height:6px" role="progressbar" aria-label="<?= e($catLabels[$cat]) ?>" aria-valuenow="<?= (int)$v ?>" aria-valuemin="0" aria-valuemax="<?= $max ?>"><div class="progress-bar" style="width:<?= $max ? round($v / $max * 100) : 0 ?>%"></div></div>
            <?php endforeach; ?>
        </div></div>

        <?php if ($qc && !empty($qc['mandatory'])): ?>
        <div class="card shadow-sm border-0 mb-4"><div class="card-body">
            <h2 class="h6 fw-bold">Mandatory checks</h2>
            <ul class="list-unstyled small mb-0">
                <?php foreach ($qc['mandatory'] as $c): ?>
                    <li class="py-1 border-bottom"><?= !empty($c['passed']) ? '<span class="text-success">✓</span>' : '<span class="text-danger">✗</span>' ?> <strong><?= e((string)($c['label'] ?? $c['id'])) ?></strong>
                        <?php if (!empty($c['detail'])): ?><div class="text-secondary"><?= e(is_string($c['detail']) ? $c['detail'] : json_encode($c['detail'])) ?></div><?php endif; ?></li>
                <?php endforeach; ?>
            </ul>
            <?php if (!empty($qc['warnings'])): ?><p class="small fw-semibold mt-3 mb-1">Warnings</p><ul class="small text-secondary mb-0"><?php foreach ((array)$qc['warnings'] as $w): ?><li><?= e(is_string($w) ? $w : json_encode($w)) ?></li><?php endforeach; ?></ul><?php endif; ?>
            <?php if (!empty($qc['corrections'])): ?><p class="small fw-semibold mt-3 mb-1">Automatic corrections applied</p><ul class="small text-secondary mb-0"><?php foreach ((array)$qc['corrections'] as $w): ?><li><?= e(is_string($w) ? $w : json_encode($w)) ?></li><?php endforeach; ?></ul><?php endif; ?>
        </div></div>
        <?php endif; ?>

        <?php if ($genItem['error_report']): ?>
        <div class="card shadow-sm border-0 mb-4 border-danger"><div class="card-body">
            <h2 class="h6 fw-bold text-danger">Error report</h2>
            <pre class="small bg-light p-2 rounded mb-0" style="white-space:pre-wrap;max-height:420px;overflow:auto"><?= e($genItem['error_report']) ?></pre>
        </div></div>
        <?php endif; ?>

        <?php if ($genItem['review_note']): ?><div class="alert alert-secondary small">Review note: <?= e($genItem['review_note']) ?></div><?php endif; ?>

        <?php if ($genItem['status'] === 'pending_review'): ?>
        <div class="card shadow-sm border-0 mb-4"><div class="card-body">
            <h2 class="h6 fw-bold">Approve and publish</h2>
            <form method="post"><?php csrf_field(); ?><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?= $id ?>">
                <div class="mb-3 p-2 border rounded <?= $qcError ? 'border-danger' : '' ?>">
                    <p class="small fw-bold mb-1">Before publishing, confirm:</p>
                    <ul class="small text-secondary mb-2 ps-3">
                        <li>Title, subject, grade, topic and type are correct</li>
                        <li>The download file exists and opens correctly</li>
                        <li>Description and teaching information are accurate</li>
                        <li>"What's Included" matches the actual file(s)</li>
                        <li>No spelling/grammar errors; the content is educationally accurate</li>
                    </ul>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="qc_confirmed" id="qc_confirmed" value="1">
                        <label class="form-check-label small" for="qc_confirmed">I've checked this against the list above</label></div>
                    <?php if ($qcError): ?><div class="text-danger small mt-1"><?= e($qcError) ?></div><?php endif; ?>
                </div>
                <button class="btn btn-success w-100">Approve &amp; publish</button>
            </form>
        </div></div>
        <?php endif; ?>

        <?php if (in_array($genItem['status'], ['pending_review', 'failed'], true)): ?>
        <div class="card shadow-sm border-0"><div class="card-body">
            <h2 class="h6 fw-bold"><?= $genItem['status'] === 'failed' ? 'Regenerate' : 'Reject' ?></h2>
            <form method="post"><?php csrf_field(); ?><input type="hidden" name="id" value="<?= $id ?>">
                <label class="form-label small" for="note">Note for the generator (what to fix)</label>
                <textarea class="form-control mb-2" id="note" name="note" rows="3" maxlength="500"></textarea>
                <div class="d-flex gap-2">
                    <?php if ($genItem['status'] === 'pending_review'): ?><button class="btn btn-outline-danger flex-fill" name="action" value="reject">Reject</button><?php endif; ?>
                    <button class="btn btn-outline-primary flex-fill" name="action" value="regenerate"><?= $genItem['status'] === 'failed' ? 'Regenerate' : 'Reject &amp; regenerate' ?></button>
                </div>
            </form>
        </div></div>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
