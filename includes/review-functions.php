<?php
/**
 * Teacher review & rating system: eligibility, CRUD, rating summaries,
 * helpful votes, and the admin moderation/stats queries. Requires auth.php,
 * download-functions.php (has_downloaded_resource()) to already be loaded.
 *
 * Only approved reviews are ever shown publicly or counted in rating
 * summaries — pending/rejected reviews are visible only to their author
 * (My Reviews) and to admins (moderation queue).
 */

/**
 * A teacher may review a resource only once they've actually downloaded
 * it — reuses the existing downloads audit trail rather than inventing a
 * separate access-tracking mechanism. This is also the single source of
 * truth for the "Verified Teacher" badge (see get_resource_reviews()).
 */
function can_review_resource(int $userId, int $resourceId): bool
{
    return has_downloaded_resource($userId, $resourceId);
}

function get_user_review(int $userId, int $resourceId): ?array
{
    $stmt = getDB()->prepare('SELECT * FROM reviews WHERE user_id = ? AND resource_id = ? LIMIT 1');
    $stmt->execute([$userId, $resourceId]);

    return $stmt->fetch() ?: null;
}

/**
 * Creates or updates the user's single review for this resource (the
 * UNIQUE(resource_id, user_id) constraint is the real enforcement; this
 * just decides insert vs. update). Editing an existing review resets it
 * to 'pending' so the new content goes back through moderation. Returns
 * ['success' => bool, 'errors' => array<string,string>].
 */
function submit_review(int $userId, int $resourceId, array $input): array
{
    $errors = [];

    $rating = filter_var($input['rating'] ?? null, FILTER_VALIDATE_INT);
    if ($rating === false || $rating < 1 || $rating > 5) {
        $errors['rating'] = 'Please choose a star rating from 1 to 5.';
    }

    $reviewText = clean_input($input['review_text'] ?? '');
    if (mb_strlen($reviewText) > 2000) {
        $errors['review_text'] = 'Reviews are limited to 2000 characters.';
    }

    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }

    $existing = get_user_review($userId, $resourceId);
    $db = getDB();

    if ($existing) {
        $db->prepare(
            "UPDATE reviews SET rating = ?, review_text = ?, status = 'pending' WHERE id = ?"
        )->execute([$rating, $reviewText !== '' ? $reviewText : null, $existing['id']]);
    } else {
        $db->prepare(
            'INSERT INTO reviews (resource_id, user_id, rating, review_text) VALUES (?, ?, ?, ?)'
        )->execute([$resourceId, $userId, $rating, $reviewText !== '' ? $reviewText : null]);
    }

    return ['success' => true, 'errors' => []];
}

/**
 * Average rating (rounded to 1 decimal), total count, and a 5→1 star
 * breakdown — computed live from approved reviews only. Returns
 * average = null when there are no approved reviews yet (never fabricate
 * a rating for an unreviewed resource).
 */
function get_resource_rating_summary(int $resourceId): array
{
    $stmt = getDB()->prepare(
        'SELECT rating, COUNT(*) AS total FROM reviews WHERE resource_id = ? AND status = ? GROUP BY rating'
    );
    $stmt->execute([$resourceId, 'approved']);

    $breakdown = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
    $totalCount = 0;
    $ratingSum = 0;

    foreach ($stmt->fetchAll() as $row) {
        $stars = (int)$row['rating'];
        $count = (int)$row['total'];
        if (isset($breakdown[$stars])) {
            $breakdown[$stars] = $count;
        }
        $totalCount += $count;
        $ratingSum += $stars * $count;
    }

    return [
        'average'   => $totalCount > 0 ? round($ratingSum / $totalCount, 1) : null,
        'total'     => $totalCount,
        'breakdown' => $breakdown,
    ];
}

/** Approved reviews for a resource, newest-helpful first, with the author's display name and a freshly-computed Verified Teacher flag. */
function get_resource_reviews(int $resourceId, int $limit = 50): array
{
    $stmt = getDB()->prepare(
        "SELECT r.*, u.first_name, u.id AS reviewer_id
         FROM reviews r
         INNER JOIN users u ON u.id = r.user_id
         WHERE r.resource_id = ? AND r.status = 'approved'
         ORDER BY r.helpful_count DESC, r.created_at DESC
         LIMIT " . max(1, $limit)
    );
    $stmt->execute([$resourceId]);
    $reviews = $stmt->fetchAll();

    foreach ($reviews as &$review) {
        $review['is_verified'] = has_downloaded_resource((int)$review['user_id'], $resourceId);
    }

    return $reviews;
}

function has_marked_helpful(int $userId, int $reviewId): bool
{
    $stmt = getDB()->prepare('SELECT id FROM review_helpful WHERE user_id = ? AND review_id = ? LIMIT 1');
    $stmt->execute([$userId, $reviewId]);

    return (bool)$stmt->fetch();
}

/**
 * Toggles the current user's helpful vote on a review (mirrors
 * toggle_favorite()'s pattern). A user can't mark their own review
 * helpful. Returns ['helpful' => bool, 'count' => int] or null if the
 * review doesn't exist / isn't approved / belongs to the voter.
 */
function toggle_review_helpful(int $userId, int $reviewId): ?array
{
    $db = getDB();

    $stmt = $db->prepare("SELECT user_id, helpful_count FROM reviews WHERE id = ? AND status = 'approved' LIMIT 1");
    $stmt->execute([$reviewId]);
    $review = $stmt->fetch();

    if (!$review || (int)$review['user_id'] === $userId) {
        return null;
    }

    if (has_marked_helpful($userId, $reviewId)) {
        $db->prepare('DELETE FROM review_helpful WHERE user_id = ? AND review_id = ?')->execute([$userId, $reviewId]);
        $db->prepare('UPDATE reviews SET helpful_count = GREATEST(0, helpful_count - 1) WHERE id = ?')->execute([$reviewId]);
        $helpful = false;
    } else {
        $db->prepare('INSERT INTO review_helpful (review_id, user_id) VALUES (?, ?)')->execute([$reviewId, $userId]);
        $db->prepare('UPDATE reviews SET helpful_count = helpful_count + 1 WHERE id = ?')->execute([$reviewId]);
        $helpful = true;
    }

    $countStmt = $db->prepare('SELECT helpful_count FROM reviews WHERE id = ?');
    $countStmt->execute([$reviewId]);

    return ['helpful' => $helpful, 'count' => (int)$countStmt->fetchColumn()];
}

/** All of a user's reviews (any status), for the "My Reviews" account page. */
function get_user_reviews_paginated(int $userId, int $page, int $perPage): array
{
    $db = getDB();

    $countStmt = $db->prepare('SELECT COUNT(*) FROM reviews WHERE user_id = ?');
    $countStmt->execute([$userId]);
    $total = (int)$countStmt->fetchColumn();

    $totalPages = $perPage > 0 ? max(1, (int)ceil($total / $perPage)) : 1;
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;

    $stmt = $db->prepare(
        "SELECT rv.*, r.title AS resource_title, r.slug AS resource_slug
         FROM reviews rv
         INNER JOIN resources r ON r.id = rv.resource_id
         WHERE rv.user_id = ?
         ORDER BY rv.created_at DESC
         LIMIT {$perPage} OFFSET {$offset}"
    );
    $stmt->execute([$userId]);

    return ['items' => $stmt->fetchAll(), 'total' => $total, 'total_pages' => $totalPages, 'page' => $page];
}

// -----------------------------------------------------------------------
// ADMIN: MODERATION & STATS
// -----------------------------------------------------------------------

function get_review_by_id(int $id): ?array
{
    $stmt = getDB()->prepare(
        'SELECT rv.*, r.title AS resource_title, r.slug AS resource_slug, u.first_name, u.last_name, u.email
         FROM reviews rv
         INNER JOIN resources r ON r.id = rv.resource_id
         INNER JOIN users u ON u.id = rv.user_id
         WHERE rv.id = ? LIMIT 1'
    );
    $stmt->execute([$id]);

    return $stmt->fetch() ?: null;
}

/** $filters may contain: status, rating (1-5), resource_id, search (matches review text, teacher name/email, or resource title). */
function get_all_reviews_paginated(array $filters, int $page, int $perPage): array
{
    $where = [];
    $params = [];

    $status = trim((string)($filters['status'] ?? ''));
    if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
        $where[] = 'rv.status = ?';
        $params[] = $status;
    }

    $rating = (int)($filters['rating'] ?? 0);
    if ($rating >= 1 && $rating <= 5) {
        $where[] = 'rv.rating = ?';
        $params[] = $rating;
    }

    $resourceId = (int)($filters['resource_id'] ?? 0);
    if ($resourceId > 0) {
        $where[] = 'rv.resource_id = ?';
        $params[] = $resourceId;
    }

    $search = trim((string)($filters['search'] ?? ''));
    if ($search !== '') {
        $where[] = '(rv.review_text LIKE ? OR u.first_name LIKE ? OR u.email LIKE ? OR r.title LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like, $like);
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    $db = getDB();

    $countStmt = $db->prepare(
        "SELECT COUNT(*) FROM reviews rv
         INNER JOIN resources r ON r.id = rv.resource_id
         INNER JOIN users u ON u.id = rv.user_id
         {$whereSql}"
    );
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $totalPages = $perPage > 0 ? max(1, (int)ceil($total / $perPage)) : 1;
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;

    $sql = "SELECT rv.*, r.title AS resource_title, r.slug AS resource_slug, u.first_name, u.last_name, u.email
            FROM reviews rv
            INNER JOIN resources r ON r.id = rv.resource_id
            INNER JOIN users u ON u.id = rv.user_id
            {$whereSql}
            ORDER BY rv.created_at DESC
            LIMIT {$perPage} OFFSET {$offset}";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return ['items' => $stmt->fetchAll(), 'total' => $total, 'total_pages' => $totalPages, 'page' => $page];
}

function approve_review(int $id): bool
{
    return getDB()->prepare("UPDATE reviews SET status = 'approved' WHERE id = ?")->execute([$id]);
}

/** Also used to "hide" a currently-approved review — same underlying action, just triggered from a different starting status. */
function reject_review(int $id): bool
{
    return getDB()->prepare("UPDATE reviews SET status = 'rejected' WHERE id = ?")->execute([$id]);
}

function delete_review(int $id): bool
{
    return getDB()->prepare('DELETE FROM reviews WHERE id = ?')->execute([$id]);
}

/** Site-wide review counts and average rating (approved only), for the admin dashboard. */
function get_review_stats(): array
{
    $db = getDB();

    return [
        'total'       => (int)$db->query('SELECT COUNT(*) FROM reviews')->fetchColumn(),
        'pending'     => (int)$db->query("SELECT COUNT(*) FROM reviews WHERE status = 'pending'")->fetchColumn(),
        'approved'    => (int)$db->query("SELECT COUNT(*) FROM reviews WHERE status = 'approved'")->fetchColumn(),
        'rejected'    => (int)$db->query("SELECT COUNT(*) FROM reviews WHERE status = 'rejected'")->fetchColumn(),
        'avg_rating'  => (float)$db->query("SELECT COALESCE(AVG(rating), 0) FROM reviews WHERE status = 'approved'")->fetchColumn(),
    ];
}

/** Top $limit resources by average approved rating (at least 1 approved review). */
function get_top_rated_resources(int $limit = 5): array
{
    $stmt = getDB()->prepare(
        "SELECT r.title, r.slug, AVG(rv.rating) AS avg_rating, COUNT(*) AS review_count
         FROM reviews rv
         INNER JOIN resources r ON r.id = rv.resource_id
         WHERE rv.status = 'approved'
         GROUP BY r.id, r.title, r.slug
         ORDER BY avg_rating DESC, review_count DESC
         LIMIT " . max(1, $limit)
    );
    $stmt->execute();

    return $stmt->fetchAll();
}

/** Top $limit resources by number of approved reviews. */
function get_most_reviewed_resources(int $limit = 5): array
{
    $stmt = getDB()->prepare(
        "SELECT r.title, r.slug, AVG(rv.rating) AS avg_rating, COUNT(*) AS review_count
         FROM reviews rv
         INNER JOIN resources r ON r.id = rv.resource_id
         WHERE rv.status = 'approved'
         GROUP BY r.id, r.title, r.slug
         ORDER BY review_count DESC
         LIMIT " . max(1, $limit)
    );
    $stmt->execute();

    return $stmt->fetchAll();
}
