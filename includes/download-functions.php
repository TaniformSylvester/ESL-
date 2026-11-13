<?php
/**
 * Download eligibility, free-plan quota, logging, and history. Requires
 * auth.php and membership.php to already be loaded (uses is_admin() /
 * isMemberActive()).
 */

/** Free Pro Preview credits a new account starts with (the column default in migrations/add_pro_trial_credits.sql). */
if (!defined('PRO_TRIAL_CREDITS_TOTAL')) {
    define('PRO_TRIAL_CREDITS_TOTAL', 3);
}

/**
 * The single source of truth for "can this resource be downloaded right
 * now, without spending anything" — used by resource.php and both
 * download endpoints so they never drift apart.
 *
 * Every download needs an account (lead capture). Free resources are then
 * unlimited. A members-only resource (is_free = 0) needs an active Pro
 * membership, a one-time bundle purchase that includes it (see
 * includes/bundle-functions.php; bundle access never expires), or an
 * earlier Free Pro Preview unlock of that same resource. A user who could
 * still unlock it with a credit gets false here; see can_use_pro_trial().
 */
function can_download_resource(array $resource): bool
{
    if (!is_logged_in()) {
        return false;
    }

    if (is_admin() || isMemberActive()) {
        return true;
    }

    if (!empty($resource['is_free'])) {
        return true;
    }

    $userId = (int)$_SESSION['user_id'];
    $resourceId = (int)$resource['id'];

    return has_bundle_access($userId, $resourceId) || has_pro_trial_unlock($userId, $resourceId);
}

// -----------------------------------------------------------------------
// FREE PRO PREVIEW CREDITS
// -----------------------------------------------------------------------

/** Remaining Free Pro Preview credits for this account (never negative). */
function get_pro_trial_credits(int $userId): int
{
    $stmt = getDB()->prepare('SELECT pro_trial_credits FROM users WHERE id = ?');
    $stmt->execute([$userId]);

    return max(0, (int)$stmt->fetchColumn());
}

/** Whether this user already unlocked this members-only resource with a credit. */
function has_pro_trial_unlock(int $userId, int $resourceId): bool
{
    $stmt = getDB()->prepare('SELECT 1 FROM pro_trial_unlocks WHERE user_id = ? AND resource_id = ? LIMIT 1');
    $stmt->execute([$userId, $resourceId]);

    return (bool)$stmt->fetchColumn();
}

/**
 * Whether the logged-in user could download this resource by spending a
 * Free Pro Preview credit: a members-only resource they aren't otherwise
 * entitled to, a non-Pro account, and at least one credit left.
 */
function can_use_pro_trial(array $resource): bool
{
    if (!is_logged_in() || is_admin() || isMemberActive() || !empty($resource['is_free'])) {
        return false;
    }

    if (can_download_resource($resource)) {
        return false; // already entitled: nothing to spend
    }

    return get_pro_trial_credits((int)$_SESSION['user_id']) > 0;
}

/**
 * Spends one credit to unlock a members-only resource for this user, and
 * returns whether the user now has it unlocked. Server-side only (the
 * download endpoints); never trust a client-supplied count.
 *
 * One transaction: the unlock row is inserted first, and its unique key
 * means a resource is only ever charged once, even for two simultaneous
 * requests. The decrement re-checks the balance in its own WHERE clause,
 * so credits can never go below zero. If no credit is left, the unlock row
 * is rolled back. An already-unlocked resource returns true without
 * charging.
 *
 * $charged is set to true only when this call actually spent a credit.
 */
function try_unlock_with_pro_trial(int $userId, int $resourceId, ?bool &$charged = null): bool
{
    $charged = false;
    $db = getDB();
    $db->beginTransaction();

    try {
        $insert = $db->prepare('INSERT IGNORE INTO pro_trial_unlocks (user_id, resource_id) VALUES (?, ?)');
        $insert->execute([$userId, $resourceId]);

        if ($insert->rowCount() === 0) {
            $db->commit();
            return true; // unlocked before: no second charge
        }

        $spend = $db->prepare('UPDATE users SET pro_trial_credits = pro_trial_credits - 1 WHERE id = ? AND pro_trial_credits > 0');
        $spend->execute([$userId]);

        if ($spend->rowCount() === 0) {
            $db->rollBack();
            return false; // no credits left
        }

        $db->commit();
        $charged = true;
        return true;
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('Pro trial unlock failed for user #' . $userId . ', resource #' . $resourceId . ': ' . $e->getMessage());
        return false;
    }
}

/** Badge text, e.g. "2/3 Free Pro Preview Credits Remaining" (shared by resource.php, dashboard.php and the status API). */
function pro_trial_credits_label(int $remaining): string
{
    return $remaining . '/' . PRO_TRIAL_CREDITS_TOTAL . ' Free Pro Preview Credit' . (PRO_TRIAL_CREDITS_TOTAL === 1 ? '' : 's') . ' Remaining';
}

/**
 * Where a logged-out visitor goes to download: registration, returning to
 * the resource page afterwards (a redirect straight to the file would leave
 * the browser on the registration page).
 */
function download_signup_url(array $resource): string
{
    return 'register.php?redirect=' . urlencode('/resource.php?slug=' . $resource['slug']);
}

/**
 * Dormant since the free-access model change: no longer called from
 * can_download_resource() or the download flow, since free resources are
 * now unlimited for everyone. Kept (with its backing users.free_downloads_*
 * columns and FREE_DOWNLOAD_MONTHLY_LIMIT constant) rather than deleted,
 * in case a future rate-limit or quota feature is wanted again — nothing
 * currently calls it.
 *
 * Read-only view of a free-plan user's monthly download usage, applying
 * the lazy calendar-month reset described in config.php's
 * FREE_DOWNLOAD_MONTHLY_LIMIT comment. Safe to call repeatedly (e.g. on
 * every page render) — the reset UPDATE is a no-op once the month has
 * already been rolled over for this user.
 */
function get_free_download_usage(int $userId): array
{
    $db = getDB();
    $month = date('Y-m');

    $db->prepare(
        'UPDATE users SET free_downloads_used = 0, free_downloads_month = ?
         WHERE id = ? AND (free_downloads_month IS NULL OR free_downloads_month != ?)'
    )->execute([$month, $userId, $month]);

    $stmt = $db->prepare('SELECT free_downloads_used FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $used = (int)$stmt->fetchColumn();

    return [
        'used'      => $used,
        'limit'     => FREE_DOWNLOAD_MONTHLY_LIMIT,
        'remaining' => max(0, FREE_DOWNLOAD_MONTHLY_LIMIT - $used),
    ];
}

/**
 * Tasteful, consistent copy for a Free-plan user's remaining quota —
 * shared by dashboard.php, resource.php, and member/subscription.php so
 * the wording never drifts between pages. $usage is get_free_download_usage()'s return value.
 */
function free_download_usage_message(array $usage): string
{
    if ($usage['remaining'] <= 0) {
        return "You've reached your monthly download limit. Upgrade to Pro for unlimited downloads.";
    }

    if ($usage['remaining'] === 1) {
        return 'Only 1 free download remaining this month.';
    }

    return "You've used {$usage['used']} of {$usage['limit']} free downloads this month.";
}

/**
 * Atomically consumes one of the user's monthly free downloads and
 * returns whether it succeeded. The UPDATE's WHERE clause re-checks the
 * limit in the same statement as the increment (rather than a separate
 * read-then-write), so two simultaneous requests can't both slip through
 * on a stale read — the second one's UPDATE simply matches zero rows.
 * Must be called from server-side code only (member/download.php); never
 * trust a client-supplied count.
 */
function try_consume_free_download(int $userId): bool
{
    get_free_download_usage($userId); // ensures the lazy month-reset has already run

    $stmt = getDB()->prepare(
        'UPDATE users SET free_downloads_used = free_downloads_used + 1
         WHERE id = ? AND free_downloads_month = ? AND free_downloads_used < ?'
    );
    $stmt->execute([$userId, date('Y-m'), FREE_DOWNLOAD_MONTHLY_LIMIT]);

    return $stmt->rowCount() > 0;
}

/** Whether this user has ever downloaded this resource — the basis for review eligibility and the "Verified Teacher" badge (see includes/review-functions.php). */
function has_downloaded_resource(int $userId, int $resourceId): bool
{
    $stmt = getDB()->prepare('SELECT id FROM downloads WHERE user_id = ? AND resource_id = ? LIMIT 1');
    $stmt->execute([$userId, $resourceId]);

    return (bool)$stmt->fetch();
}

function record_download(?int $userId, int $resourceId, bool $viaProTrial = false): void
{
    $db = getDB();

    $db->prepare('INSERT INTO downloads (user_id, resource_id, ip_address, via_pro_trial) VALUES (?, ?, ?, ?)')
        ->execute([$userId, $resourceId, $_SERVER['REMOTE_ADDR'] ?? null, $viaProTrial ? 1 : 0]);

    $db->prepare('UPDATE resources SET download_count = download_count + 1 WHERE id = ?')
        ->execute([$resourceId]);
}

/**
 * A short, dashboard-friendly list of resources this user has actually
 * downloaded — one row per resource (deduped, most recent download wins),
 * and only resources still published and active, so "Continue Where You
 * Left Off" never links to something retired. Distinct from
 * get_user_downloads(), which intentionally returns the full unfiltered
 * history (including archived resources) for the My Downloads page.
 */
function get_recent_active_downloads(int $userId, int $limit): array
{
    $limit = max(0, $limit);

    $stmt = getDB()->prepare(
        "SELECT r.id, r.title, r.slug, r.resource_type, r.grade_level, MAX(d.downloaded_at) AS downloaded_at
         FROM downloads d
         INNER JOIN resources r ON r.id = d.resource_id
         WHERE d.user_id = ? AND r.is_published = 1 AND r.status = 'active'
         GROUP BY r.id
         ORDER BY downloaded_at DESC
         LIMIT {$limit}"
    );
    $stmt->execute([$userId]);

    return $stmt->fetchAll();
}

function get_user_downloads(int $userId, int $page, int $perPage): array
{
    $db = getDB();

    $countStmt = $db->prepare('SELECT COUNT(*) FROM downloads WHERE user_id = ?');
    $countStmt->execute([$userId]);
    $total = (int)$countStmt->fetchColumn();

    $totalPages = $perPage > 0 ? max(1, (int)ceil($total / $perPage)) : 1;
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;

    $stmt = $db->prepare(
        "SELECT d.downloaded_at, d.via_pro_trial, r.id AS resource_id, r.title, r.slug, r.resource_type, r.is_published, r.status
         FROM downloads d
         INNER JOIN resources r ON r.id = d.resource_id
         WHERE d.user_id = ?
         ORDER BY d.downloaded_at DESC
         LIMIT {$perPage} OFFSET {$offset}"
    );
    $stmt->execute([$userId]);

    return ['items' => $stmt->fetchAll(), 'total' => $total, 'total_pages' => $totalPages, 'page' => $page];
}
