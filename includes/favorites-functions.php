<?php
/**
 * Favorites: add/remove/list. Requires auth.php for session access.
 */

function is_favorited(int $userId, int $resourceId): bool
{
    $stmt = getDB()->prepare('SELECT id FROM favorites WHERE user_id = ? AND resource_id = ? LIMIT 1');
    $stmt->execute([$userId, $resourceId]);

    return (bool)$stmt->fetch();
}

/** Adds or removes the favorite and returns the new state (true = now favorited). */
function toggle_favorite(int $userId, int $resourceId): bool
{
    if (is_favorited($userId, $resourceId)) {
        getDB()->prepare('DELETE FROM favorites WHERE user_id = ? AND resource_id = ?')
            ->execute([$userId, $resourceId]);

        return false;
    }

    getDB()->prepare('INSERT INTO favorites (user_id, resource_id) VALUES (?, ?)')
        ->execute([$userId, $resourceId]);

    return true;
}

function get_user_favorites(int $userId, int $page, int $perPage): array
{
    $db = getDB();

    $countStmt = $db->prepare('SELECT COUNT(*) FROM favorites f INNER JOIN resources r ON r.id = f.resource_id WHERE f.user_id = ? AND r.is_published = 1');
    $countStmt->execute([$userId]);
    $total = (int)$countStmt->fetchColumn();

    $totalPages = $perPage > 0 ? max(1, (int)ceil($total / $perPage)) : 1;
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;

    $stmt = $db->prepare(
        "SELECT r.*, c.name AS category_name
         FROM favorites f
         INNER JOIN resources r ON r.id = f.resource_id
         LEFT JOIN categories c ON c.id = r.category_id
         WHERE f.user_id = ? AND r.is_published = 1
         ORDER BY f.created_at DESC
         LIMIT {$perPage} OFFSET {$offset}"
    );
    $stmt->execute([$userId]);

    return ['items' => $stmt->fetchAll(), 'total' => $total, 'total_pages' => $totalPages, 'page' => $page];
}
