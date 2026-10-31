<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-functions.php';
require_once __DIR__ . '/../includes/automation-functions.php';

require_admin();
$admin = current_user();
$adminId = (int)$admin['id'];
$ready = automation_available();
$tabs = ['overview' => 'Overview', 'review' => 'Review queue', 'failed' => 'Failed', 'runs' => 'Runs', 'settings' => 'Settings', 'access' => 'API access', 'audit' => 'Audit log'];
$tab = array_key_exists($_GET['tab'] ?? '', $tabs) ? $_GET['tab'] : 'overview';
$newToken = null;
$settingsErrors = [];

if ($ready && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string)($_POST['action'] ?? '');
    $back = 'admin/automation.php?tab=' . urlencode($_POST['tab'] ?? 'overview');

    switch ($action) {
        case 'emergency_on':
            automation_set_emergency_stop(true, $adminId);
            log_admin_action($adminId, 'automation_emergency_stop', 'Emergency stop ON');
            flash_set('warning', 'Emergency stop is ON. The generator is refused on its very next request.');
            redirect($back);
        case 'emergency_off':
            automation_set_emergency_stop(false, $adminId);
            log_admin_action($adminId, 'automation_emergency_stop', 'Emergency stop OFF');
            flash_set('success', 'Emergency stop released.');
            redirect($back);
        case 'save_settings':
            $settingsErrors = automation_save_settings($_POST, $adminId);
            if (!$settingsErrors) {
                log_admin_action($adminId, 'automation_settings', 'Updated automation settings');
                flash_set('success', 'Automation settings saved.');
                redirect('admin/automation.php?tab=settings');
            }
            $tab = 'settings';
            break;
        case 'create_token':
            $newToken = automation_create_token(trim((string)($_POST['token_name'] ?? '')) ?: 'Resource generator', $adminId);
            log_admin_action($adminId, 'automation_token', 'Created an automation API token');
            $tab = 'access';
            break;
        case 'revoke_token':
            automation_revoke_token((int)($_POST['id'] ?? 0), $adminId);
            log_admin_action($adminId, 'automation_token', 'Revoked automation API token #' . (int)($_POST['id'] ?? 0));
            flash_set('success', 'Token revoked. Anything using it is refused immediately.');
            redirect('admin/automation.php?tab=access');
        case 'retry_run':
            $err = automation_request_retry((int)($_POST['id'] ?? 0), $adminId);
            flash_set($err ? 'error' : 'success', $err ?: 'Marked for retry. The next generator run picks it up first.');
            redirect('admin/automation.php?tab=runs');
        case 'regenerate':
            $err = automation_reject_item((int)($_POST['id'] ?? 0), $adminId, trim((string)($_POST['note'] ?? '')), true);
            flash_set($err ? 'error' : 'success', $err ?: 'Queued for regeneration on the next run.');
            redirect($back);
    }
}

if (!$ready) {
    $pageTitle = 'Resource Automation';
    require_once __DIR__ . '/../includes/admin-header.php';
    echo '<div class="alert alert-warning"><h2 class="h6 fw-bold">One-time setup needed</h2><p class="mb-0">Run <code>automation_migration.sql</code> in phpMyAdmin (cPanel &rarr; phpMyAdmin &rarr; your TeachLuma database &rarr; SQL tab &rarr; paste &rarr; Go), then reload this page.</p></div>';
    require_once __DIR__ . '/../includes/admin-footer.php';
    exit;
}

$settings = automation_settings();
$stats = automation_stats();
[$nextRun, $nextReason] = automation_next_run($settings);
$subjectGrades = automation_subject_grades();
$subjectNames = ['esl' => 'ESL', 'math' => 'Math', 'science' => 'Science'];

$statusBadge = static fn(string $s): string => match ($s) {
    'pending_review' => 'bg-warning text-dark',
    'approved', 'completed' => 'bg-success',
    'failed', 'interrupted' => 'bg-danger',
    'stopped', 'rejected' => 'bg-secondary',
    'partial', 'regenerate_requested' => 'bg-info text-dark',
    'running' => 'bg-primary',
    default => 'bg-light text-dark',
};
$fmt = static fn(?string $dt): string => $dt ? format_date($dt, 'j M Y, H:i') : '—';

$pageTitle = 'Resource Automation';
require_once __DIR__ . '/../includes/admin-header.php';
?>
<?php if ($settings['emergency_stop']): ?>
    <div class="alert alert-danger d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span><i class="fa-solid fa-circle-stop me-2" aria-hidden="true"></i><strong>Emergency stop is ON.</strong> The generator is refused on every request until you release it.</span>
        <form method="post" class="m-0"><?php csrf_field(); ?><input type="hidden" name="action" value="emergency_off"><input type="hidden" name="tab" value="<?= e($tab) ?>">
            <button class="btn btn-sm btn-light">Release emergency stop</button></form>
    </div>
<?php endif; ?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <p class="text-secondary mb-0">
        Generation: <strong><?= $settings['enabled'] ? 'Enabled' : 'Disabled' ?></strong> &middot;
        Schedule: <strong><?= e(ucfirst($settings['frequency'])) ?></strong> &middot;
        Automatic publishing: <strong>Off</strong> <span class="small">(every resource needs your approval)</span>
    </p>
    <?php if (!$settings['emergency_stop']): ?>
        <form method="post" class="m-0" onsubmit="return confirm('Stop all automation immediately?');"><?php csrf_field(); ?>
            <input type="hidden" name="action" value="emergency_on"><input type="hidden" name="tab" value="<?= e($tab) ?>">
            <button class="btn btn-danger"><i class="fa-solid fa-circle-stop me-1" aria-hidden="true"></i>Emergency stop</button>
        </form>
    <?php endif; ?>
</div>

<ul class="nav nav-tabs mb-4">
    <?php foreach ($tabs as $key => $label): ?>
        <li class="nav-item"><a class="nav-link <?= $tab === $key ? 'active' : '' ?>" href="<?= e(base_url('admin/automation.php?tab=' . $key)) ?>"><?= e($label) ?>
            <?php if ($key === 'review' && $stats['pending']): ?><span class="badge bg-warning text-dark ms-1"><?= (int)$stats['pending'] ?></span><?php endif; ?>
            <?php if ($key === 'failed' && $stats['failed']): ?><span class="badge bg-danger ms-1"><?= (int)$stats['failed'] ?></span><?php endif; ?>
        </a></li>
    <?php endforeach; ?>
</ul>

<?php if ($tab === 'overview'): ?>
    <div class="row g-3 mb-4">
        <?php foreach ([
            ['Total generated', $stats['total'], ''],
            ['Published', $stats['published'], 'text-success'],
            ['Awaiting review', $stats['pending'], 'text-warning'],
            ['Failed', $stats['failed'], 'text-danger'],
            ['Free', $stats['free'], ''],
            ['Pro', $stats['pro'], ''],
        ] as [$label, $value, $cls]): ?>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="card shadow-sm border-0 stat-card h-100"><div class="card-body">
                    <p class="text-secondary small text-uppercase mb-1"><?= e($label) ?></p>
                    <p class="stat-value mb-0 <?= e($cls) ?>"><?= (int)$value ?></p>
                </div></div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-md-6"><div class="card shadow-sm border-0 h-100"><div class="card-body">
            <p class="text-secondary small text-uppercase mb-1">Last successful generation</p>
            <p class="h5 mb-0"><?= e($fmt($stats['last_success'])) ?></p>
        </div></div></div>
        <div class="col-md-6"><div class="card shadow-sm border-0 h-100"><div class="card-body">
            <p class="text-secondary small text-uppercase mb-1">Next scheduled generation</p>
            <p class="h5 mb-0"><?= $nextRun ? e(format_date($nextRun, 'j M Y, H:i')) : '—' ?></p>
            <?php if (!$nextRun): ?><p class="small text-secondary mb-0"><?= e($nextReason) ?></p><?php endif; ?>
        </div></div></div>
    </div>
    <?php $pending = automation_list_items(['pending_review'], 5); ?>
    <div class="card shadow-sm border-0"><div class="card-body">
        <h2 class="h6 fw-bold">Waiting for your review</h2>
        <?php if (!$pending): ?><p class="text-secondary small mb-0">Nothing waiting. New drafts appear here after each run.</p><?php endif; ?>
        <?php foreach ($pending as $it): ?>
            <div class="d-flex align-items-center gap-3 py-2 border-top">
                <?php if ($it['thumbnail']): ?><img src="<?= e(UPLOAD_THUMBNAIL_URL . '/' . rawurlencode($it['thumbnail'])) ?>" alt="" width="72" height="54" class="rounded" style="object-fit:cover"><?php endif; ?>
                <div class="flex-grow-1"><div class="fw-semibold"><?= e($it['title']) ?></div>
                    <div class="small text-secondary"><?= e(($subjectNames[$it['subject_slug']] ?? $it['subject_slug']) . ' · ' . $it['grade_level'] . ' · ' . ucfirst($it['tier'])) ?> · score <?= (int)$it['quality_score'] ?>/100</div></div>
                <a class="btn btn-sm btn-primary" href="<?= e(base_url('admin/automation-item.php?id=' . $it['id'])) ?>">Review</a>
            </div>
        <?php endforeach; ?>
    </div></div>

<?php elseif ($tab === 'review' || $tab === 'failed'):
    $list = $tab === 'review' ? automation_list_items(['pending_review', 'regenerate_requested', 'approved', 'rejected', 'regenerated'], 100) : automation_list_items(['failed'], 100); ?>
    <?php if ($tab === 'failed'): ?>
        <p class="text-secondary small">These failed automated QC after <?= AUTOMATION_MAX_CORRECTIONS ?> correction attempts and were <strong>not uploaded</strong>. Inspect the error report, then queue a regeneration if the topic is still wanted.</p>
    <?php endif; ?>
    <div class="card shadow-sm border-0"><div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Resource</th><th>Subject / grade</th><th>Tier</th><th>Score</th><th>Status</th><th>Created</th><th></th></tr></thead>
            <tbody>
            <?php if (!$list): ?><tr><td colspan="7" class="text-center text-secondary py-4">Nothing here yet.</td></tr><?php endif; ?>
            <?php foreach ($list as $it): ?>
                <tr>
                    <td><div class="fw-semibold"><?= e($it['title']) ?></div><div class="small text-secondary"><?= e($it['resource_type'] . ' · ' . $it['topic']) ?></div></td>
                    <td class="small"><?= e(($subjectNames[$it['subject_slug']] ?? $it['subject_slug']) . ' · ' . $it['grade_level']) ?></td>
                    <td><span class="badge <?= $it['tier'] === 'free' ? 'badge-free' : 'badge-members' ?>"><?= e(ucfirst($it['tier'])) ?></span></td>
                    <td class="small"><?= $it['quality_score'] !== null ? (int)$it['quality_score'] . '/100' : '—' ?></td>
                    <td><span class="badge <?= e($statusBadge($it['status'])) ?>"><?= e(AUTOMATION_ITEM_STATUSES[$it['status']] ?? $it['status']) ?></span></td>
                    <td class="small text-secondary"><?= e($fmt($it['created_at'])) ?></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('admin/automation-item.php?id=' . $it['id'])) ?>"><?= $it['status'] === 'pending_review' ? 'Review' : 'Inspect' ?></a>
                        <?php if ($it['status'] === 'failed'): ?>
                            <form method="post" class="d-inline"><?php csrf_field(); ?><input type="hidden" name="action" value="regenerate"><input type="hidden" name="tab" value="failed"><input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                                <button class="btn btn-sm btn-outline-secondary">Regenerate</button></form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div>

<?php elseif ($tab === 'runs'): $runs = automation_recent_runs(30); ?>
    <p class="text-secondary small">A run that stops sending heartbeats for <?= AUTOMATION_STALE_RUN_MINUTES ?> minutes is marked <em>interrupted</em>. Retrying it re-processes only the planned resources that were not finished — uploads are idempotent, so nothing is duplicated.</p>
    <div class="card shadow-sm border-0"><div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>#</th><th>Trigger</th><th>Status</th><th>Planned</th><th>Uploaded</th><th>Failed</th><th>Started</th><th>Finished</th><th>Message</th><th></th></tr></thead>
            <tbody>
            <?php if (!$runs): ?><tr><td colspan="10" class="text-center text-secondary py-4">No runs yet.</td></tr><?php endif; ?>
            <?php foreach ($runs as $r): ?>
                <tr>
                    <td class="small"><?= (int)$r['id'] ?><?= $r['retry_of'] ? '<div class="text-secondary">retry of #' . (int)$r['retry_of'] . '</div>' : '' ?></td>
                    <td class="small"><?= e(ucfirst($r['trigger_type'])) ?></td>
                    <td><span class="badge <?= e($statusBadge($r['status'])) ?>"><?= e(ucfirst($r['status'])) ?></span><?= $r['retry_requested'] ? ' <span class="badge bg-info text-dark">retry queued</span>' : '' ?></td>
                    <td><?= (int)$r['planned'] ?></td><td><?= (int)$r['generated'] ?></td><td><?= (int)$r['failed'] ?></td>
                    <td class="small text-secondary"><?= e($fmt($r['started_at'])) ?></td>
                    <td class="small text-secondary"><?= e($fmt($r['finished_at'])) ?></td>
                    <td class="small" style="max-width:280px"><?= e(mb_strimwidth((string)$r['message'], 0, 200, '…')) ?></td>
                    <td><?php if (in_array($r['status'], ['interrupted', 'failed', 'partial', 'stopped'], true) && !$r['retry_requested']): ?>
                        <form method="post" class="m-0"><?php csrf_field(); ?><input type="hidden" name="action" value="retry_run"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-primary">Retry</button></form>
                    <?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div>

<?php elseif ($tab === 'settings'): ?>
    <form method="post" class="card shadow-sm border-0" novalidate><div class="card-body">
        <?php csrf_field(); ?><input type="hidden" name="action" value="save_settings">
        <?php foreach ($settingsErrors as $err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endforeach; ?>
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" role="switch" id="enabled" name="enabled" value="1" <?= $settings['enabled'] ? 'checked' : '' ?>>
            <label class="form-check-label fw-semibold" for="enabled">Enable automatic generation</label>
            <div class="form-text">When off, scheduled runs are skipped. You can still start a run by hand.</div>
        </div>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold" for="frequency">Schedule</label>
                <select class="form-select" id="frequency" name="frequency">
                    <?php foreach (['manual' => 'Manual only', 'daily' => 'Daily', 'weekly' => 'Weekly'] as $v => $l): ?>
                        <option value="<?= $v ?>" <?= $settings['frequency'] === $v ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold" for="run_hour">Expected run time (<?= e(TIMEZONE) ?>)</label>
                <select class="form-select" id="run_hour" name="run_hour">
                    <?php for ($h = 0; $h < 24; $h++): ?><option value="<?= $h ?>" <?= $settings['run_hour'] === $h ? 'selected' : '' ?>><?= sprintf('%02d:00', $h) ?></option><?php endfor; ?>
                </select>
                <div class="form-text">Match this to the time of the Claude Code routine.</div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold" for="per_run">Resources per run</label>
                <input type="number" class="form-control" id="per_run" name="per_run" min="1" max="10" value="<?= (int)$settings['per_run'] ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold" for="free_percent">Free share</label>
                <div class="input-group"><input type="number" class="form-control" id="free_percent" name="free_percent" min="0" max="100" value="<?= (int)$settings['free_percent'] ?>"><span class="input-group-text">% Free</span></div>
                <div class="form-text">The rest are Pro. Default 33% Free / 67% Pro. Pro packs include more components.</div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold" for="max_uploads_per_day">Daily upload limit</label>
                <input type="number" class="form-control" id="max_uploads_per_day" name="max_uploads_per_day" min="1" max="50" value="<?= (int)$settings['max_uploads_per_day'] ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Automatic publishing</label>
                <div class="form-control bg-light text-secondary">Off — not available yet</div>
                <div class="form-text">Later, resources scoring <?= (int)$settings['auto_publish_min_score'] ?>+ with every mandatory check passed may become eligible, only if you approve turning it on.</div>
            </div>
        </div>
        <hr>
        <p class="fw-semibold mb-2">Subjects and grades</p>
        <div class="row g-3">
            <?php foreach ($subjectGrades as $slug => $grades): ?>
                <div class="col-lg-4"><div class="border rounded p-3 h-100">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="subj-<?= e($slug) ?>" name="subjects[]" value="<?= e($slug) ?>" <?= in_array($slug, $settings['subjects'], true) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="subj-<?= e($slug) ?>"><?= e($subjectNames[$slug] ?? $slug) ?></label>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($grades as $g): ?>
                            <label class="small border rounded px-2 py-1"><input type="checkbox" class="form-check-input me-1" name="grades[<?= e($slug) ?>][]" value="<?= e($g) ?>" <?= in_array($g, $settings['grades'][$slug] ?? [], true) ? 'checked' : '' ?>><?= e(str_replace('Grade ', 'G', $g)) ?></label>
                        <?php endforeach; ?>
                    </div>
                </div></div>
            <?php endforeach; ?>
        </div>
        <button class="btn btn-primary mt-4">Save settings</button>
    </div></form>

<?php elseif ($tab === 'access'): ?>
    <?php if ($newToken): ?>
        <div class="alert alert-success">
            <p class="fw-semibold mb-1">New token — copy it now. It will not be shown again.</p>
            <code class="d-block p-2 bg-white border rounded user-select-all" style="word-break:break-all"><?= e($newToken) ?></code>
            <p class="small mb-0 mt-2">Add it to the Claude Code environment as the secret <code>TEACHLUMA_API_TOKEN</code>. Never put it in GitHub, chat or email.</p>
        </div>
    <?php endif; ?>
    <div class="card shadow-sm border-0 mb-4"><div class="card-body">
        <h2 class="h6 fw-bold">API tokens</h2>
        <p class="small text-secondary">A token lets the generator read the catalog and upload <strong>unpublished drafts</strong>. It cannot publish, edit or delete resources, or reach users, memberships, payments or settings. Limits: <?= AUTOMATION_RATE_PER_MINUTE ?> requests/minute, <?= AUTOMATION_UPLOADS_PER_HOUR ?> uploads/hour, <?= (int)$settings['max_uploads_per_day'] ?> uploads/day.</p>
        <form method="post" class="row g-2 mb-3"><?php csrf_field(); ?><input type="hidden" name="action" value="create_token">
            <div class="col-md-5"><input type="text" class="form-control" name="token_name" maxlength="100" placeholder="Token name, e.g. Claude Code routine" aria-label="Token name"></div>
            <div class="col-md-3"><button class="btn btn-primary w-100">Create token</button></div>
        </form>
        <div class="table-responsive"><table class="table table-sm align-middle mb-0">
            <thead><tr><th>Name</th><th>Prefix</th><th>Created</th><th>Last used</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach (automation_list_tokens() as $t): ?>
                <tr>
                    <td><?= e($t['name']) ?></td><td><code><?= e($t['token_prefix']) ?>…</code></td>
                    <td class="small"><?= e($fmt($t['created_at'])) ?></td>
                    <td class="small"><?= e($fmt($t['last_used_at'])) ?><?= $t['last_used_ip'] ? '<div class="text-secondary">' . e($t['last_used_ip']) . '</div>' : '' ?></td>
                    <td><?= $t['revoked_at'] ? '<span class="badge bg-secondary">Revoked</span>' : '<span class="badge bg-success">Active</span>' ?></td>
                    <td><?php if (!$t['revoked_at']): ?><form method="post" class="m-0" onsubmit="return confirm('Revoke this token? The generator stops working until it gets a new one.');"><?php csrf_field(); ?><input type="hidden" name="action" value="revoke_token"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><button class="btn btn-sm btn-outline-danger">Revoke</button></form><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div></div>

<?php elseif ($tab === 'audit'): $rows = getDB()->query('SELECT a.*, t.name AS token_name FROM automation_audit a LEFT JOIN api_tokens t ON t.id = a.token_id ORDER BY a.id DESC LIMIT 150')->fetchAll(); ?>
    <div class="card shadow-sm border-0"><div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Time</th><th>Who</th><th>Action</th><th>Result</th><th>IP</th><th>Detail</th></tr></thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-secondary py-4">No activity yet.</td></tr><?php endif; ?>
            <?php foreach ($rows as $a): ?>
                <tr>
                    <td class="small text-nowrap"><?= e($fmt($a['created_at'])) ?></td>
                    <td class="small"><?= $a['admin_id'] ? 'Admin #' . (int)$a['admin_id'] : ($a['token_name'] ? e($a['token_name']) : 'unknown') ?></td>
                    <td class="small"><code><?= e($a['action']) ?></code></td>
                    <td><span class="badge <?= (int)$a['http_status'] < 400 ? 'bg-success' : 'bg-danger' ?>"><?= (int)$a['http_status'] ?></span></td>
                    <td class="small text-secondary"><?= e((string)$a['ip']) ?></td>
                    <td class="small" style="max-width:380px"><?= e((string)$a['detail']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
