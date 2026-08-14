<?php
/**
 * Admin-only queries and actions: dashboard stats, user management,
 * and membership adjustments made directly by an administrator.
 * Resource/category CRUD lives in resource-functions.php; payment
 * approve/reject lives alongside admin/payments.php in a later stage.
 */

function log_admin_action(int $adminId, string $action, string $details = ''): void
{
    getDB()->prepare('INSERT INTO admin_logs (admin_id, action, details) VALUES (?, ?, ?)')
        ->execute([$adminId, $action, $details]);
}

function get_dashboard_stats(): array
{
    $db = getDB();

    return [
        'total_users'         => (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn(),
        'active_members'      => (int)$db->query("SELECT COUNT(*) FROM memberships WHERE status = 'active' AND expiry_date >= CURDATE()")->fetchColumn(),
        'expired_members'     => (int)$db->query("SELECT COUNT(*) FROM memberships WHERE status = 'expired' OR (status = 'active' AND expiry_date < CURDATE())")->fetchColumn(),
        'pending_payments'    => (int)$db->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn(),
        'total_resources'     => (int)$db->query('SELECT COUNT(*) FROM resources')->fetchColumn(),
        'total_downloads'     => (int)$db->query('SELECT COUNT(*) FROM downloads')->fetchColumn(),
        'resources_this_month' => (int)$db->query("SELECT COUNT(*) FROM resources WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())")->fetchColumn(),
        'monthly_revenue'     => (int)$db->query("SELECT COUNT(*) FROM memberships WHERE status = 'active' AND expiry_date >= CURDATE()")->fetchColumn() * SUBSCRIPTION_PRICE,
    ];
}

/**
 * Paginated user search/listing. $filters may contain: search, role, membership_status.
 */
function get_users_paginated(array $filters, int $page, int $perPage): array
{
    $where = [];
    $params = [];

    $search = trim((string)($filters['search'] ?? ''));
    if ($search !== '') {
        $where[] = '(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.school_name LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like, $like);
    }

    $role = trim((string)($filters['role'] ?? ''));
    if (in_array($role, ['admin', 'teacher'], true)) {
        $where[] = 'u.role = ?';
        $params[] = $role;
    }

    $membershipStatus = trim((string)($filters['membership_status'] ?? ''));
    if ($membershipStatus !== '') {
        if ($membershipStatus === 'active') {
            $where[] = "m.status = 'active' AND m.expiry_date >= CURDATE()";
        } elseif ($membershipStatus === 'expired') {
            $where[] = "(m.status = 'expired' OR (m.status = 'active' AND m.expiry_date < CURDATE()))";
        } else {
            $where[] = 'm.status = ?';
            $params[] = $membershipStatus;
        }
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    $db = getDB();

    $countStmt = $db->prepare("SELECT COUNT(*) FROM users u LEFT JOIN memberships m ON m.user_id = u.id {$whereSql}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $totalPages = $perPage > 0 ? max(1, (int)ceil($total / $perPage)) : 1;
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;

    $sql = "SELECT u.id, u.first_name, u.last_name, u.email, u.role, u.school_name, u.country, u.is_active, u.created_at,
                   m.status AS membership_status, m.start_date, m.expiry_date
            FROM users u
            LEFT JOIN memberships m ON m.user_id = u.id
            {$whereSql}
            ORDER BY u.created_at DESC
            LIMIT {$perPage} OFFSET {$offset}";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return ['items' => $stmt->fetchAll(), 'total' => $total, 'total_pages' => $totalPages, 'page' => $page];
}

function get_user_by_id(int $id): ?array
{
    $stmt = getDB()->prepare(
        'SELECT u.*, m.status AS membership_status, m.start_date AS membership_start, m.expiry_date AS membership_expiry
         FROM users u
         LEFT JOIN memberships m ON m.user_id = u.id
         WHERE u.id = ? LIMIT 1'
    );
    $stmt->execute([$id]);

    $user = $stmt->fetch();
    if ($user) {
        unset($user['password_hash']);
    }

    return $user ?: null;
}

function update_user_role(int $userId, string $role): bool
{
    if (!in_array($role, ['admin', 'teacher'], true)) {
        return false;
    }

    getDB()->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$role, $userId]);

    return true;
}

function set_user_active(int $userId, bool $active): void
{
    getDB()->prepare('UPDATE users SET is_active = ? WHERE id = ?')->execute([$active ? 1 : 0, $userId]);
}

/** Extends (or starts) a user's membership by the given number of months from today or their current expiry, whichever is later. */
function extend_membership(int $userId, int $months = 1): void
{
    $membership = get_membership($userId);
    $today = new DateTime('today');

    $base = $today;
    if ($membership && !empty($membership['expiry_date'])) {
        $currentExpiry = new DateTime($membership['expiry_date']);
        if ($currentExpiry > $today) {
            $base = $currentExpiry;
        }
    }

    $newExpiry = (clone $base)->modify("+{$months} month")->format('Y-m-d');
    $db = getDB();

    if ($membership) {
        $startDate = $membership['start_date'] ?? $today->format('Y-m-d');
        // Reset the reminder flag so the next expiry cycle can send a fresh one.
        $db->prepare("UPDATE memberships SET status = 'active', start_date = COALESCE(start_date, ?), expiry_date = ?, expiry_reminder_sent_at = NULL WHERE user_id = ?")
            ->execute([$startDate, $newExpiry, $userId]);
    } else {
        $db->prepare("INSERT INTO memberships (user_id, status, start_date, expiry_date) VALUES (?, 'active', ?, ?)")
            ->execute([$userId, $today->format('Y-m-d'), $newExpiry]);
    }
}

function reset_membership(int $userId): void
{
    getDB()->prepare("UPDATE memberships SET status = 'inactive', start_date = NULL, expiry_date = NULL WHERE user_id = ?")
        ->execute([$userId]);
}

function cancel_membership(int $userId): void
{
    getDB()->prepare("UPDATE memberships SET status = 'cancelled' WHERE user_id = ?")
        ->execute([$userId]);
}
