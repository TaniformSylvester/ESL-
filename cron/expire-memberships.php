<?php
/**
 * Optional scheduled cleanup. isMemberActive() already self-heals a stale
 * 'active' row past its expiry the moment anyone checks it, so correctness
 * never depends on this script running — it exists to (a) keep admin
 * reports/stats accurate even for members nobody has "looked at" recently,
 * (b) send the "membership expired" email at the moment of transition, and
 * (c) send a renewal reminder a few days before expiry.
 *
 * cPanel setup (Cron Jobs), run once daily:
 *   php /home/USERNAME/public_html/cron/expire-memberships.php
 * (adjust the path if the site lives in an addon domain's document root
 * instead of public_html directly — cPanel's Cron Jobs page shows your
 * home directory path at the top of the form. If a bare "php" doesn't
 * work, your host may require a version-specific binary instead.)
 *
 * If your host only supports URL-based cron jobs instead of running a
 * PHP file directly, set CRON_SECRET in config/config.php and call:
 *   https://yourdomain.com/cron/expire-memberships.php?token=YOUR_SECRET
 */
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/email.php';

$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    if (CRON_SECRET === '' || ($_GET['token'] ?? '') !== CRON_SECRET) {
        http_response_code(403);
        exit('Forbidden.');
    }
}

$db = getDB();

// Renewal reminders: active memberships expiring within the reminder
// window that haven't been reminded yet. expiry_reminder_sent_at (reset
// to NULL on every renewal by extend_membership()) makes this safe to
// run daily without repeating the email, while still catching up if a
// cron run gets missed on the exact day the window opens.
$stmt = $db->prepare(
    "SELECT u.id, u.first_name, u.email, m.expiry_date
     FROM memberships m
     INNER JOIN users u ON u.id = m.user_id
     WHERE m.status = 'active'
       AND m.expiry_reminder_sent_at IS NULL
       AND m.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)"
);
$stmt->execute([MEMBERSHIP_EXPIRY_REMINDER_DAYS]);
$expiringSoon = $stmt->fetchAll();

foreach ($expiringSoon as $user) {
    send_membership_expiring_soon_email($user, $user['expiry_date']);
    $db->prepare('UPDATE memberships SET expiry_reminder_sent_at = NOW() WHERE user_id = ?')->execute([$user['id']]);
}

// Capture who is about to expire before flipping their status, so we can
// email them exactly once at the moment of transition.
$stmt = $db->query(
    "SELECT u.id, u.first_name, u.email
     FROM memberships m
     INNER JOIN users u ON u.id = m.user_id
     WHERE m.status = 'active' AND m.expiry_date < CURDATE()"
);
$expiring = $stmt->fetchAll();

$db->prepare("UPDATE memberships SET status = 'expired' WHERE status = 'active' AND expiry_date < CURDATE()")
    ->execute();

foreach ($expiring as $user) {
    send_membership_expired_email($user);
}

$message = date('Y-m-d H:i:s') . ' - Sent ' . count($expiringSoon) . ' renewal reminder(s), expired ' . count($expiring) . ' membership(s).';
error_log($message);
echo $message . PHP_EOL;
