<?php
/**
 * Membership status logic. isMemberActive() is the single source of
 * truth for paid-content access — every other file must call it rather
 * than re-deriving membership state from raw DB columns.
 */

// isMemberActive() calls is_logged_in(); pages that never load auth.php
// themselves (about.php, terms.php, ...) reach this via the header's ad
// check, which fatally errored for signed-in visitors without it.
require_once __DIR__ . '/auth.php';

function get_membership(int $userId): ?array
{
    $stmt = getDB()->prepare('SELECT * FROM memberships WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);

    return $stmt->fetch() ?: null;
}

/**
 * Returns true only if: the target user is logged in (when $userId is
 * omitted, the current session user is used), their membership status
 * is 'active', AND their expiry date has not passed. A stale 'active'
 * row past its expiry is lazily flipped to 'expired' here, so correctness
 * never depends on a cron job having run.
 */
function isMemberActive(?int $userId = null): bool
{
    if ($userId === null) {
        if (!is_logged_in()) {
            return false;
        }
        $userId = (int)$_SESSION['user_id'];
    }

    $membership = get_membership($userId);

    if (!$membership || $membership['status'] !== 'active' || empty($membership['expiry_date'])) {
        return false;
    }

    if (strtotime($membership['expiry_date']) < strtotime('today')) {
        getDB()->prepare("UPDATE memberships SET status = 'expired' WHERE user_id = ? AND status = 'active'")
            ->execute([$userId]);

        return false;
    }

    return true;
}

function membership_status_label(array $membership): string
{
    if (!empty($membership['expiry_date']) && $membership['status'] === 'active' && strtotime($membership['expiry_date']) < strtotime('today')) {
        return 'Expired';
    }

    return match ($membership['status']) {
        'active'    => 'Active',
        'pending'   => 'Pending Approval',
        'expired'   => 'Expired',
        'cancelled' => 'Cancelled',
        default     => 'Inactive',
    };
}

function membership_status_badge_class(array $membership): string
{
    return match (membership_status_label($membership)) {
        'Active'            => 'bg-success',
        'Pending Approval'  => 'bg-warning text-dark',
        'Expired'           => 'bg-danger',
        'Cancelled'         => 'bg-secondary',
        default             => 'bg-secondary',
    };
}

/** Days remaining until expiry, or null if there's no active expiry date. */
function membership_days_remaining(array $membership): ?int
{
    if (empty($membership['expiry_date'])) {
        return null;
    }

    $diff = (strtotime($membership['expiry_date']) - strtotime('today')) / 86400;

    return (int)round($diff);
}
