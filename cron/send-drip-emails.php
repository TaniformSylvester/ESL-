<?php
/**
 * Sends the welcome drip emails (Day 1, Day 3, Day 7 after sign-up) to
 * Free-plan teachers. See includes/drip-functions.php for who is skipped
 * and config/config.php (DRIP_*) for the switches and limits.
 *
 * cPanel setup (Cron Jobs), run once an hour:
 *   0 * * * * /usr/local/bin/php /home/USERNAME/public_html/cron/send-drip-emails.php >/dev/null 2>&1
 * (adjust the path if the site lives in an addon domain's document root;
 * cPanel's Cron Jobs page shows your home directory. If /usr/local/bin/php
 * isn't right on your host, use the PHP path cPanel suggests, e.g.
 * /usr/local/bin/ea-php83.)
 *
 * If your host only supports URL-based cron jobs, set CRON_SECRET in
 * config/config.php and call:
 *   https://yourdomain.com/cron/send-drip-emails.php?token=YOUR_SECRET
 *
 * Safe to run as often as you like:
 *   * a non-blocking file lock (logs/send-drip-emails.lock) makes an
 *     overlapping run exit straight away;
 *   * each step is claimed in the database before it is sent, so even two
 *     runs that somehow got past the lock can't send the same step twice;
 *   * at most DRIP_BATCH_SIZE emails per run, with DRIP_SEND_PAUSE_MS
 *     between them, to stay well inside shared-hosting mail limits.
 * Nothing here touches memberships, payments or transactional email.
 */
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/drip-functions.php';

$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    if (CRON_SECRET === '' || !hash_equals(CRON_SECRET, (string)($_GET['token'] ?? ''))) {
        http_response_code(403);
        exit('Forbidden.');
    }
    header('Content-Type: text/plain; charset=UTF-8');
}

$finish = static function (string $message): void {
    $line = date('Y-m-d H:i:s') . ' - Drip emails: ' . $message;
    error_log($line);
    echo $line . PHP_EOL;
};

if (!DRIP_EMAILS_ENABLED) {
    $finish('switched off (DRIP_EMAILS_ENABLED is false). Nothing sent.');
    exit;
}

// One run at a time. The lock is released automatically when the script
// ends, even if it crashes, so a stale lock file never blocks future runs.
$lockFile = ROOT_PATH . '/logs/send-drip-emails.lock';
$lock = @fopen($lockFile, 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    $finish('another run is still in progress. Nothing sent.');
    exit;
}

@set_time_limit(max(60, (int)ceil(DRIP_BATCH_SIZE * (DRIP_SEND_PAUSE_MS / 1000 + 3))));

$budget = max(0, (int)DRIP_BATCH_SIZE);
$counts = ['sent' => 0, 'skipped' => 0, 'failed' => 0];
$perStep = [1 => 0, 2 => 0, 3 => 0];

try {
    // Later steps first: a teacher already in the sequence finishes it
    // before the batch is spent on new sign-ups.
    foreach ([3, 2, 1] as $step) {
        if ($budget <= 0) {
            break;
        }

        foreach (get_drip_due_users($step, $budget) as $user) {
            if ($budget <= 0) {
                break;
            }

            $result = send_drip_step($user, $step);
            $counts[$result]++;

            if ($result !== 'skipped') {
                $budget--; // failed sends still used the mail server
                if ($result === 'sent') {
                    $perStep[$step]++;
                }
                usleep(max(0, (int)DRIP_SEND_PAUSE_MS) * 1000);
            }
        }
    }
} catch (Throwable $e) {
    email_log_error('Drip cron stopped early: ' . $e->getMessage());
    $finish('stopped early after an error (see logs/php-error.log). Sent ' . $counts['sent'] . '.');
    flock($lock, LOCK_UN);
    fclose($lock);
    exit(1);
}

flock($lock, LOCK_UN);
fclose($lock);

$finish(sprintf(
    'sent %d (Day 1: %d, Day 3: %d, Day 7: %d), skipped %d, failed %d (will retry next run).',
    $counts['sent'], $perStep[1], $perStep[2], $perStep[3], $counts['skipped'], $counts['failed']
));
