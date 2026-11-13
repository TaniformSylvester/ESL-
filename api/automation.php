<?php
/**
 * Resource Automation API — used only by the TeachLuma resource generator
 * (automation/ in the repo, run from Claude Code sessions). See
 * includes/automation-functions.php for the safety model.
 *
 *   GET  ?action=status            settings, schedule, tier balance
 *   GET  ?action=taxonomy          subjects, grades, categories, types
 *   GET  ?action=catalog           every resource's metadata (no user data)
 *   GET  ?action=work_queue        regeneration requests + runs to retry
 *   POST ?action=duplicate_check   {subject, grade, title, topic, file_hash?}
 *   POST ?action=run_start         {trigger, plan[], retry_of?}
 *   POST ?action=run_heartbeat     {run_id, message?}
 *   POST ?action=run_finish        {run_id, status, message}
 *   POST ?action=upload            multipart: run_id, idempotency_key, meta, qc_report, social, spec + files
 *   POST ?action=report_failed     {run_id, idempotency_key, meta, qc_report, error_report, spec, correction_attempts}
 *
 * There is deliberately no publish, edit or delete action.
 *
 * Auth: "Authorization: Bearer <token>" or "X-TeachLuma-Token: <token>"
 * (some hosts strip the Authorization header before PHP sees it).
 */
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/automation-functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

function api_out(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function api_json_body(): array
{
    $data = json_decode((string)file_get_contents('php://input'), true);

    return is_array($data) ? $data : [];
}

$action = (string)($_GET['action'] ?? '');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (!automation_available()) {
    api_out(503, ['ok' => false, 'error' => 'Automation is not installed on this site yet (database migration not run).']);
}

// HTTPS only, except for local development.
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
if (!$isHttps && !preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/', $host)) {
    api_out(400, ['ok' => false, 'error' => 'HTTPS is required.']);
}

if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > AUTOMATION_MAX_REQUEST_BYTES) {
    api_out(413, ['ok' => false, 'error' => 'Request too large.']);
}

// --- Authentication ---------------------------------------------------------
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
$plain = preg_match('/^Bearer\s+(\S+)$/i', $auth, $m) ? $m[1] : trim((string)($_SERVER['HTTP_X_TEACHLUMA_TOKEN'] ?? ''));
$token = $plain !== '' ? automation_find_token($plain) : null;
if (!$token) {
    automation_audit('auth_failed', 401, 'action=' . mb_substr($action, 0, 30));
    api_out(401, ['ok' => false, 'error' => 'Invalid or revoked token.']);
}
$tokenId = (int)$token['id'];
getDB()->prepare('UPDATE api_tokens SET last_used_at = NOW(), last_used_ip = ? WHERE id = ?')->execute([automation_client_ip(), $tokenId]);

// --- Rate limits ------------------------------------------------------------
if (automation_rate_limited($tokenId, null, AUTOMATION_RATE_PER_MINUTE, 60)) {
    automation_audit('rate_limited', 429, "action={$action}", $tokenId);
    api_out(429, ['ok' => false, 'error' => 'Too many requests. Slow down.']);
}

// --- Action table: method + scope -------------------------------------------
$actions = [
    'status'          => ['GET', 'catalog:read'],
    'taxonomy'        => ['GET', 'catalog:read'],
    'catalog'         => ['GET', 'catalog:read'],
    'work_queue'      => ['GET', 'drafts:write'],
    'item_spec'       => ['GET', 'drafts:write'],
    'duplicate_check' => ['POST', 'catalog:read'],
    'run_start'       => ['POST', 'drafts:write'],
    'run_heartbeat'   => ['POST', 'drafts:write'],
    'run_finish'      => ['POST', 'drafts:write'],
    'upload'          => ['POST', 'drafts:write'],
    'report_failed'   => ['POST', 'drafts:write'],
];
if (!isset($actions[$action])) {
    automation_audit('unknown_action', 404, mb_substr($action, 0, 50), $tokenId);
    api_out(404, ['ok' => false, 'error' => 'Unknown action.']);
}
[$needMethod, $needScope] = $actions[$action];
if ($method !== $needMethod) {
    api_out(405, ['ok' => false, 'error' => "Use {$needMethod}."]);
}
if (!in_array($needScope, explode(',', (string)$token['scopes']), true)) {
    automation_audit($action, 403, 'missing scope ' . $needScope, $tokenId);
    api_out(403, ['ok' => false, 'error' => 'This token is not allowed to do that.']);
}

// --- Emergency stop: only "status" still answers ------------------------------
$settings = automation_settings();
if ($settings['emergency_stop'] && $action !== 'status') {
    automation_audit($action, 423, 'emergency stop', $tokenId);
    api_out(423, ['ok' => false, 'error' => 'Emergency stop is on. Automation is paused.']);
}

// --- Actions ----------------------------------------------------------------
switch ($action) {
    case 'status':
        automation_mark_stale_runs();
        [$due, $reason] = automation_schedule_due($settings);
        $tiers = getDB()->query("SELECT SUM(tier = 'free') AS free, SUM(tier = 'pro') AS pro FROM automation_items WHERE status <> 'failed'")->fetch();
        automation_audit('status', 200, null, $tokenId);
        api_out(200, ['ok' => true, 'settings' => $settings, 'due' => $due, 'due_reason' => $reason,
            'last_success' => automation_last_success(), 'tier_counts' => ['free' => (int)$tiers['free'], 'pro' => (int)$tiers['pro']],
            'uploads_today' => automation_uploads_today(), 'server_time' => date('c')]);

    case 'taxonomy':
        automation_audit('taxonomy', 200, null, $tokenId);
        api_out(200, ['ok' => true] + automation_taxonomy());

    case 'catalog':
        $cat = automation_catalog();
        automation_audit('catalog', 200, count($cat['resources']) . ' resources', $tokenId);
        api_out(200, ['ok' => true] + $cat);

    case 'work_queue':
        automation_audit('work_queue', 200, null, $tokenId);
        api_out(200, ['ok' => true] + automation_work_queue());

    case 'item_spec':
        // Read-only: the spec stored when a generated resource was uploaded, so clean
        // replacement files can be rebuilt. Changes nothing on the site.
        $rid = (int)($_GET['resource_id'] ?? 0);
        $st = getDB()->prepare('SELECT id, resource_id, idempotency_key, spec FROM automation_items
                                WHERE resource_id = ? AND spec IS NOT NULL ORDER BY id DESC LIMIT 1');
        $st->execute([$rid]);
        $row = $st->fetch();
        if (!$row) {
            automation_audit('item_spec', 404, "resource #{$rid}", $tokenId);
            api_out(404, ['ok' => false, 'error' => 'No stored spec for that resource.']);
        }
        automation_audit('item_spec', 200, "resource #{$rid}", $tokenId);
        api_out(200, ['ok' => true, 'item_id' => (int)$row['id'], 'resource_id' => (int)$row['resource_id'],
            'key' => $row['idempotency_key'], 'spec' => json_decode((string)$row['spec'], true)]);

    case 'duplicate_check':
        $d = api_json_body();
        $matches = automation_find_duplicates((string)($d['subject'] ?? ''), (string)($d['grade'] ?? ''), (string)($d['title'] ?? ''), (string)($d['topic'] ?? ''), $d['file_hash'] ?? null);
        $blocking = array_values(array_filter($matches, static fn($x) => $x['blocking']));
        automation_audit('duplicate_check', 200, ($blocking ? 'DUPLICATE ' : 'ok ') . mb_substr((string)($d['topic'] ?? ''), 0, 80), $tokenId);
        api_out(200, ['ok' => true, 'duplicate' => (bool)$blocking, 'matches' => $matches]);

    case 'run_start':
        [$runId, $status, $err] = automation_start_run(api_json_body(), $tokenId);
        automation_audit('run_start', $status, $err ?? "run #{$runId}", $tokenId);
        $status === 200 ? api_out(200, ['ok' => true, 'run_id' => $runId]) : api_out($status, ['ok' => false, 'error' => $err]);

    case 'run_heartbeat':
        $d = api_json_body();
        $run = automation_open_run((int)($d['run_id'] ?? 0));
        if (!$run) {
            api_out(409, ['ok' => false, 'error' => 'Run is not open (finished, stopped or interrupted).']);
        }
        getDB()->prepare('UPDATE automation_runs SET heartbeat_at = NOW(), message = ? WHERE id = ?')
            ->execute([mb_substr((string)($d['message'] ?? ''), 0, 1000) ?: $run['message'], $run['id']]);
        api_out(200, ['ok' => true]);

    case 'run_finish':
        $d = api_json_body();
        $run = automation_open_run((int)($d['run_id'] ?? 0));
        if (!$run) {
            api_out(409, ['ok' => false, 'error' => 'Run is not open.']);
        }
        automation_finish_run((int)$run['id'], (string)($d['status'] ?? 'completed'), (string)($d['message'] ?? ''));
        automation_audit('run_finish', 200, "run #{$run['id']} " . mb_substr((string)($d['status'] ?? ''), 0, 20), $tokenId);
        api_out(200, ['ok' => true]);

    case 'upload':
    case 'report_failed':
        $isUpload = $action === 'upload';
        $d = $isUpload ? $_POST : api_json_body();
        if ($isUpload && empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            api_out(413, ['ok' => false, 'error' => 'Upload exceeds the server limit (post_max_size).']);
        }
        $run = automation_open_run((int)($d['run_id'] ?? 0));
        if (!$run) {
            automation_audit($action, 409, 'run not open', $tokenId);
            api_out(409, ['ok' => false, 'error' => 'Run is not open (finished, stopped or interrupted).']);
        }
        $key = (string)($d['idempotency_key'] ?? '');
        if (!preg_match('/^[A-Za-z0-9_.:-]{8,100}$/', $key)) {
            api_out(422, ['ok' => false, 'error' => 'A valid idempotency_key (8–100 safe characters) is required.']);
        }
        // Idempotency: the same key never creates a second item — retries are safe.
        $stmt = getDB()->prepare('SELECT id, resource_id, status FROM automation_items WHERE idempotency_key = ?');
        $stmt->execute([$key]);
        if ($existing = $stmt->fetch()) {
            automation_audit($action, 200, "idempotent replay item #{$existing['id']}", $tokenId);
            api_out(200, ['ok' => true, 'replay' => true, 'item_id' => (int)$existing['id'], 'resource_id' => $existing['resource_id'] ? (int)$existing['resource_id'] : null, 'status' => $existing['status']]);
        }

        if (!$isUpload) {
            $out = automation_record_failure($run, $key, $d);
            automation_audit('report_failed', 201, "item #{$out['item_id']} " . mb_substr((string)($d['meta']['topic'] ?? ''), 0, 80), $tokenId);
            api_out(201, $out);
        }

        if (automation_rate_limited($tokenId, 'upload', AUTOMATION_UPLOADS_PER_HOUR, 3600)) {
            automation_audit('upload', 429, 'hourly upload limit', $tokenId);
            api_out(429, ['ok' => false, 'error' => 'Hourly upload limit reached.']);
        }
        if (automation_uploads_today() >= $settings['max_uploads_per_day']) {
            automation_audit('upload', 429, 'daily upload limit', $tokenId);
            api_out(429, ['ok' => false, 'error' => 'Daily upload limit reached (set in the dashboard).']);
        }
        $meta = json_decode((string)($d['meta'] ?? ''), true);
        $qc = json_decode((string)($d['qc_report'] ?? ''), true);
        $social = json_decode((string)($d['social'] ?? ''), true);
        $spec = json_decode((string)($d['spec'] ?? ''), true);
        if (!is_array($meta)) {
            api_out(422, ['ok' => false, 'error' => 'meta must be JSON.']);
        }
        [$status, $body] = automation_create_draft($run, $key, $meta, $_FILES, is_array($qc) ? $qc : [], is_array($social) ? $social : [], is_array($spec) ? $spec : null, $tokenId);
        $detail = $status === 201 ? "item #{$body['item_id']} resource #{$body['resource_id']} " . mb_substr((string)$meta['title'], 0, 80)
                                  : implode(' | ', array_slice($body['errors'] ?? [], 0, 3));
        automation_audit('upload', $status, $detail, $tokenId);
        api_out($status, $body);
}
