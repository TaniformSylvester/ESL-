<?php
/**
 * Download eligibility, free-plan quota, logging, and history. Requires
 * auth.php and membership.php to already be loaded (uses is_admin() /
 * isMemberActive()).
 */

/**
 * The single source of truth for "can this resource be downloaded right
 * now" — used by both resource.php and member/download.php so they never
 * drift apart. Members-only resources (is_free = 0) always require an
 * active Pro membership, with no exception for remaining free-plan quota.
 * Free resources require a logged-in account and remaining monthly quota
 * unless the account is Pro or admin (both unlimited).
 */
function can_download_resource(array $resource): bool
{
    if (is_admin() || isMemberActive()) {
        return true;
    }

    if (!$resource['is_free']) {
        return false;
    }

    return is_logged_in() && get_free_download_usage((int)$_SESSION['user_id'])['remaining'] > 0;
}

/**
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

function record_download(?int $userId, int $resourceId): void
{
    $db = getDB();

    $db->prepare('INSERT INTO downloads (user_id, resource_id, ip_address) VALUES (?, ?, ?)')
        ->execute([$userId, $resourceId, $_SERVER['REMOTE_ADDR'] ?? null]);

    $db->prepare('UPDATE resources SET download_count = download_count + 1 WHERE id = ?')
        ->execute([$resourceId]);
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
        "SELECT d.downloaded_at, r.id AS resource_id, r.title, r.slug, r.resource_type, r.is_published, r.status
         FROM downloads d
         INNER JOIN resources r ON r.id = d.resource_id
         WHERE d.user_id = ?
         ORDER BY d.downloaded_at DESC
         LIMIT {$perPage} OFFSET {$offset}"
    );
    $stmt->execute([$userId]);

    return ['items' => $stmt->fetchAll(), 'total' => $total, 'total_pages' => $totalPages, 'page' => $page];
}
