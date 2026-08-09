<?php
/**
 * Download eligibility, logging, and history. Requires auth.php and
 * membership.php to already be loaded (uses is_admin() / isMemberActive()).
 */

/** The single source of truth for "can this resource be downloaded right now" — used by both resource.php and member/download.php so they never drift apart. */
function can_download_resource(array $resource): bool
{
    return (bool)$resource['is_free'] || isMemberActive() || is_admin();
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
        "SELECT d.downloaded_at, r.id AS resource_id, r.title, r.slug, r.resource_type, r.is_published
         FROM downloads d
         INNER JOIN resources r ON r.id = d.resource_id
         WHERE d.user_id = ?
         ORDER BY d.downloaded_at DESC
         LIMIT {$perPage} OFFSET {$offset}"
    );
    $stmt->execute([$userId]);

    return ['items' => $stmt->fetchAll(), 'total' => $total, 'total_pages' => $totalPages, 'page' => $page];
}
