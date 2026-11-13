<?php
/**
 * Email abstraction. Everything else in the app should call send_email()
 * rather than mail() directly, so swapping in SMTP later (see
 * config.php SMTP_* settings) only requires changing this one file.
 */

/**
 * Sends one email with PHP's mail(). Never throws: every failure (invalid
 * address, mail() returning false, a warning or exception from the mail
 * transport) is caught and written to logs/php-error.log, and the caller
 * gets false.
 *
 * $bodyText is optional: when given, the message is sent as
 * multipart/alternative (HTML plus plain text), which reads better in
 * text-only clients and helps deliverability.
 */
function send_email(string $to, string $subject, string $bodyHtml, ?string $bodyText = null): bool
{
    // Extension point: when SMTP_ENABLED is turned on in config.php, wire a
    // real SMTP client here (e.g. PHPMailer). For now, and for most cPanel
    // shared-hosting setups, PHP's built-in mail() is sufficient.

    $to = trim($to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        email_log_error('Not sent, invalid recipient address ' . email_mask_address($to) . ': ' . $subject);
        return false;
    }

    // Header injection guard: a subject can never carry a line break.
    $subject = trim(str_replace(["\r", "\n"], ' ', $subject));
    $encodedSubject = function_exists('mb_encode_mimeheader')
        ? mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n")
        : $subject;

    $headers = [
        'MIME-Version: 1.0',
        'From: ' . SMTP_FROM_NAME . ' <' . SMTP_FROM_EMAIL . '>',
    ];

    if ($bodyText !== null) {
        $boundary = 'tl_' . bin2hex(random_bytes(12));
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
        $message = "--{$boundary}\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n"
            . $bodyText . "\r\n\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n"
            . $bodyHtml . "\r\n\r\n"
            . "--{$boundary}--\r\n";
    } else {
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $message = $bodyHtml;
    }

    // mail() reports transport problems as PHP warnings; capture the text
    // so the log says why, instead of hiding it with @.
    $warning = null;
    set_error_handler(static function (int $errno, string $errstr) use (&$warning): bool {
        $warning = $errstr;
        return true;
    });

    try {
        // Aligns the envelope sender with the From header — without this, mail()
        // lets the server pick its own envelope sender, which can silently
        // mismatch SMTP_FROM_EMAIL and undermine SPF/DKIM alignment checks even
        // when both are otherwise configured correctly.
        $sent = mail($to, $encodedSubject, $message, implode("\r\n", $headers), '-f' . SMTP_FROM_EMAIL);
    } catch (Throwable $e) {
        $sent = false;
        $warning = $e->getMessage();
    } finally {
        restore_error_handler();
    }

    if (!$sent) {
        email_log_error('mail() failed for ' . email_mask_address($to) . ': ' . $subject . ($warning ? ' (' . $warning . ')' : ''));
    }

    return (bool)$sent;
}

/**
 * Writes an email problem to logs/php-error.log, in the same file and
 * timestamp style PHP uses in production, whatever the ENVIRONMENT setting.
 * Falls back to PHP's default error log if that file can't be written.
 */
function email_log_error(string $message): void
{
    $line = '[' . gmdate('d-M-Y H:i:s') . ' UTC] TeachLuma email: ' . $message . PHP_EOL;
    $logFile = ROOT_PATH . '/logs/php-error.log';

    if (!@error_log($line, 3, $logFile)) {
        error_log('TeachLuma email: ' . $message);
    }
}

/** "teacher@gmail.com" -> "t*****@gmail.com", so logs identify a mailbox without storing full addresses. */
function email_mask_address(string $address): string
{
    $at = strrpos($address, '@');
    if ($at === false || $at === 0) {
        return '(invalid address)';
    }

    return substr($address, 0, 1) . str_repeat('*', max(1, $at - 1)) . substr($address, $at);
}

/**
 * Queues an email to be sent after the response has gone to the browser,
 * so a slow mail server never delays the page (registration and Google
 * sign-in redirect straight away).
 *
 * At shutdown, the session is saved and released first (otherwise the
 * page the visitor is redirected to would wait for the session lock), the
 * response is finished with fastcgi_finish_request() (PHP-FPM) or
 * litespeed_finish_request() (LiteSpeed, as on FastComet), and only then
 * are the queued emails sent. Where neither function exists, the emails
 * still go out at shutdown; the page just waits for them, as before.
 * Failures are logged by send_email() and never reach the visitor.
 */
function send_email_deferred(string $to, string $subject, string $bodyHtml, ?string $bodyText = null): void
{
    static $queue = null;

    if ($queue === null) {
        $queue = new ArrayObject();
        register_shutdown_function(static function () use ($queue): void {
            if (count($queue) === 0) {
                return;
            }

            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            ignore_user_abort(true);

            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            } elseif (function_exists('litespeed_finish_request')) {
                litespeed_finish_request();
            }

            foreach ($queue as $email) {
                try {
                    send_email(...$email);
                } catch (Throwable $e) {
                    email_log_error('Deferred send failed: ' . $e->getMessage());
                }
            }
        });
    }

    $queue[] = [$to, $subject, $bodyHtml, $bodyText];
}

function send_password_reset_email(array $user, string $resetLink): bool
{
    $subject = 'Reset your ' . SITE_NAME . ' password';

    $body = '<p>Hi ' . e($user['first_name']) . ',</p>'
        . '<p>We received a request to reset your ' . e(SITE_NAME) . ' password. Click the link below to choose a new one:</p>'
        . '<p><a href="' . e($resetLink) . '">' . e($resetLink) . '</a></p>'
        . '<p>This link will expire in ' . PASSWORD_RESET_TTL_MINUTES . ' minutes. If you didn\'t request this, you can safely ignore this email.</p>';

    return send_email($user['email'], $subject, $body);
}

/**
 * The branded welcome email, sent once when a teacher signs up (email
 * registration or a new Google sign-in). It is the first step of the
 * welcome series: it welcomes the teacher, links straight to the most
 * downloaded resources, explains the Free Pro Preview credits and
 * highlights the free Teacher Tools.
 *
 * The message is built now, while the request has its database
 * connection, and sent after the response (send_email_deferred()), so
 * signing up never waits on the mail server. Returns false only when the
 * email can't be built or queued (logged); delivery failures are logged
 * at send time.
 */
function send_welcome_drip_email(int $user_id, string $email, string $name): bool
{
    try {
        $content = build_welcome_drip_email($user_id, $name);
        send_email_deferred($email, $content['subject'], $content['html'], $content['text']);
        return true;
    } catch (Throwable $e) {
        email_log_error('Welcome email for user #' . $user_id . ' could not be queued: ' . $e->getMessage());
        return false;
    }
}

/**
 * Builds the welcome email's subject, HTML and plain-text parts. Kept apart
 * from sending so it can be previewed or tested without sending anything.
 *
 * HTML follows email-client rules: table layout, inline styles, 600px max
 * width, a media query that stacks the two tool cards on phones, and web
 * fonts (Fredoka headings, Nunito text) where the client supports them,
 * falling back to Arial-family fonts in Gmail and Outlook.
 */
function build_welcome_drip_email(int $user_id, string $name): array
{
    $name = trim($name) !== '' ? trim($name) : 'there';
    $campaign = 'utm_source=email&utm_medium=email&utm_campaign=welcome';
    $link = static fn(string $path): string => base_url($path . (str_contains($path, '?') ? '&' : '?') . $campaign);

    // Real data only: the three most-downloaded published resources, and
    // this account's actual Free Pro Preview balance. Either section is
    // simply left out if it can't be read.
    $popular = [];
    try {
        if (!function_exists('get_popular_resources')) {
            require_once __DIR__ . '/resource-functions.php';
        }
        $popular = get_popular_resources(3);
    } catch (Throwable $e) {
        email_log_error('Welcome email: popular resources unavailable: ' . $e->getMessage());
    }

    $credits = null;
    try {
        $stmt = getDB()->prepare('SELECT pro_trial_credits FROM users WHERE id = ?');
        $stmt->execute([$user_id]);
        $value = $stmt->fetchColumn();
        $credits = $value === false ? null : max(0, (int)$value);
    } catch (Throwable $e) {
        $credits = null; // column missing (migration not run): skip the section
    }

    $site = e(SITE_NAME);
    $navy = '#172A6E';
    $yellow = '#FBB829';
    $ink = '#1F2433';
    $muted = '#5B6275';
    $heading = "'Fredoka','Arial Rounded MT Bold','Trebuchet MS',Arial,sans-serif";
    $text = "'Nunito','Segoe UI',Arial,Helvetica,sans-serif";

    $subject = 'Welcome to ' . SITE_NAME . ', ' . $name . '!';
    $preheader = 'Your account is ready: popular resources, free classroom tools and Free Pro Previews inside.';

    // Popular resources rows.
    $popularHtml = '';
    $popularText = '';
    foreach ($popular as $resource) {
        $url = $link('resource.php?slug=' . rawurlencode($resource['slug']));
        $meta = trim(($resource['subject_name'] ?? '') . (!empty($resource['grade_level']) ? ' · ' . $resource['grade_level'] : '') . (!empty($resource['resource_type']) ? ' · ' . $resource['resource_type'] : ''), ' ·');
        $badge = !empty($resource['is_free'])
            ? '<span style="font-family:' . $text . ';background:#DDF5F2;color:#0E7C73;border-radius:10px;padding:2px 8px;font-size:11px;font-weight:800;white-space:nowrap;">FREE</span>'
            : '<span style="font-family:' . $text . ';background:#FFF1CC;color:#7A5200;border-radius:10px;padding:2px 8px;font-size:11px;font-weight:800;white-space:nowrap;">PRO</span>';
        $popularHtml .= '<tr><td style="padding:12px 0;border-bottom:1px solid #E8ECF8;">'
            . '<a href="' . e($url) . '" style="font-family:' . $text . ';font-size:16px;font-weight:700;color:' . $navy . ';text-decoration:none;">' . e($resource['title']) . '</a> ' . $badge
            . ($meta !== '' ? '<div style="font-family:' . $text . ';font-size:13px;color:' . $muted . ';padding-top:3px;">' . e($meta) . '</div>' : '')
            . '</td></tr>';
        $popularText .= '- ' . $resource['title'] . ($meta !== '' ? ' (' . $meta . ')' : '') . "\n  " . $url . "\n";
    }

    $button = static fn(string $url, string $label, string $bg, string $fg): string =>
        '<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>'
        . '<td style="border-radius:10px;background:' . $bg . ';">'
        . '<a href="' . e($url) . '" style="display:inline-block;padding:13px 26px;font-family:' . $text . ';font-size:16px;font-weight:800;color:' . $fg . ';text-decoration:none;border-radius:10px;">' . e($label) . '</a>'
        . '</td></tr></table>';

    $toolCard = static fn(string $emoji, string $title, string $body, string $url): string =>
        '<td class="tl-stack" width="50%" valign="top" style="padding:6px;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#F4F6FB;border-radius:12px;"><tr><td style="padding:18px;">'
        . '<div style="font-size:28px;line-height:1;">' . $emoji . '</div>'
        . '<div style="font-family:' . $heading . ';font-size:18px;font-weight:700;color:' . $navy . ';padding:10px 0 6px;">' . e($title) . '</div>'
        . '<div style="font-family:' . $text . ';font-size:14px;line-height:1.5;color:' . $ink . ';padding-bottom:10px;">' . e($body) . '</div>'
        . '<a href="' . e($url) . '" style="font-family:' . $text . ';font-size:14px;font-weight:800;color:' . $navy . ';">Open it &rarr;</a>'
        . '</td></tr></table></td>';

    $pickerUrl = $link('teacher-tools.php') . '#name-picker';
    $timerUrl = $link('teacher-tools.php') . '#classroom-timer';
    $resourcesUrl = $link('resources.php');
    $proUrl = $link('resources.php?access=members');
    $dashboardUrl = $link('dashboard.php');

    $html = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light">'
        . '<title>' . e($subject) . '</title>'
        . '<link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@600;700&family=Nunito:wght@400;700;800&display=swap" rel="stylesheet">'
        . '<style>@media only screen and (max-width:520px){.tl-stack{display:block!important;width:100%!important;}.tl-pad{padding-left:20px!important;padding-right:20px!important;}.tl-h1{font-size:26px!important;}}</style>'
        . '</head><body style="margin:0;padding:0;background:#EEF1F8;">'
        . '<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:#EEF1F8;">' . e($preheader) . '</div>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#EEF1F8;"><tr><td align="center" style="padding:24px 12px;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;background:#FFFFFF;border-radius:16px;overflow:hidden;">'

        // Header
        . '<tr><td class="tl-pad" style="background:' . $navy . ';padding:28px 36px;">'
        . '<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>'
        . '<td valign="middle" style="padding-right:12px;"><img src="' . e(base_url('assets/images/apple-touch-icon.png')) . '" width="44" height="44" alt="" style="display:block;border-radius:10px;"></td>'
        . '<td valign="middle" style="font-family:' . $heading . ';font-size:26px;font-weight:700;color:#FFFFFF;">Teach<span style="color:' . $yellow . ';">Luma</span></td>'
        . '</tr></table>'
        . '<div style="font-family:' . $text . ';font-size:14px;font-weight:700;color:' . $yellow . ';padding-top:8px;">' . e(SITE_TAGLINE) . '</div>'
        . '</td></tr>'

        // Welcome
        . '<tr><td class="tl-pad" style="padding:32px 36px 8px;">'
        . '<h1 class="tl-h1" style="margin:0 0 12px;font-family:' . $heading . ';font-size:30px;line-height:1.2;font-weight:700;color:' . $navy . ';">Welcome, ' . e($name) . '! 👋</h1>'
        . '<p style="margin:0 0 14px;font-family:' . $text . ';font-size:16px;line-height:1.6;color:' . $ink . ';">Your ' . $site . ' account is ready. You can now download every free resource, as often as you like: lesson packs, worksheets, PowerPoints, flashcards and quizzes for ESL, Math and Science.</p>'
        . '<p style="margin:0 0 22px;font-family:' . $text . ';font-size:16px;line-height:1.6;color:' . $ink . ';">Not sure where to start? Here is what other teachers download most.</p>'
        . '</td></tr>'

        // Popular resources
        . ($popularHtml !== ''
            ? '<tr><td class="tl-pad" style="padding:0 36px;">'
              . '<div style="font-family:' . $text . ';font-size:12px;font-weight:800;letter-spacing:1px;color:' . $muted . ';text-transform:uppercase;padding-bottom:4px;">Popular right now</div>'
              . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">' . $popularHtml . '</table>'
              . '</td></tr>'
            : '')
        . '<tr><td class="tl-pad" style="padding:22px 36px 30px;">' . $button($resourcesUrl, 'Browse All Resources', $yellow, $navy) . '</td></tr>'

        // Free Pro Previews
        . ($credits !== null && $credits > 0
            ? '<tr><td class="tl-pad" style="padding:0 36px 30px;">'
              . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#FFF1CC;border-radius:12px;"><tr><td style="padding:20px 22px;">'
              . '<div style="font-family:' . $heading . ';font-size:19px;font-weight:700;color:' . $navy . ';padding-bottom:6px;">🎁 You have ' . $credits . ' Free Pro Preview' . ($credits === 1 ? '' : 's') . '</div>'
              . '<div style="font-family:' . $text . ';font-size:15px;line-height:1.55;color:' . $ink . ';padding-bottom:10px;">Try members-only resources before you decide on Teacher Pro. Each credit unlocks one Pro resource, including its answer keys and extra files.</div>'
              . '<a href="' . e($proUrl) . '" style="font-family:' . $text . ';font-size:15px;font-weight:800;color:' . $navy . ';">Explore Pro resources &rarr;</a>'
              . '</td></tr></table></td></tr>'
            : '')

        // Teacher Tools
        . '<tr><td class="tl-pad" style="padding:0 30px 8px;">'
        . '<div style="font-family:' . $heading . ';font-size:21px;font-weight:700;color:' . $navy . ';padding:0 6px 4px;">Free classroom tools</div>'
        . '<div style="font-family:' . $text . ';font-size:15px;line-height:1.55;color:' . $ink . ';padding:0 6px 10px;">Ready for your class screen, no setup needed.</div>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>'
        . $toolCard('🎯', 'Random Name Picker', 'Paste your class list and pick students fairly, with no repeats until everyone has had a turn.', $pickerUrl)
        . $toolCard('⏱️', 'Classroom Timer', 'A big, clear countdown for activities, tests and tidy-up time.', $timerUrl)
        . '</tr></table></td></tr>'

        // Sign-off
        . '<tr><td class="tl-pad" style="padding:24px 36px 32px;">'
        . '<p style="margin:0 0 6px;font-family:' . $text . ';font-size:16px;line-height:1.6;color:' . $ink . ';">Happy teaching,</p>'
        . '<p style="margin:0;font-family:' . $heading . ';font-size:17px;font-weight:700;color:' . $navy . ';">The ' . $site . ' team</p>'
        . '<p style="margin:16px 0 0;font-family:' . $text . ';font-size:14px;line-height:1.6;color:' . $muted . ';">Your dashboard is always one click away: <a href="' . e($dashboardUrl) . '" style="color:' . $navy . ';font-weight:700;">go to my dashboard</a>.</p>'
        . '</td></tr>'

        // Footer
        . '<tr><td class="tl-pad" style="background:#F4F6FB;padding:20px 36px;font-family:' . $text . ';font-size:12px;line-height:1.6;color:' . $muted . ';">'
        . 'You are receiving this email because you created an account at <a href="' . e(base_url('')) . '" style="color:' . $muted . ';">' . e(preg_replace('#^https?://#', '', SITE_URL)) . '</a>. '
        . 'Questions? Just reply, or write to <a href="mailto:' . e(CONTACT_EMAIL) . '" style="color:' . $muted . ';">' . e(CONTACT_EMAIL) . '</a>.'
        . '</td></tr>'

        . '</table></td></tr></table></body></html>';

    $plain = "Welcome, {$name}!\n\n"
        . "Your " . SITE_NAME . " account is ready. You can now download every free resource, as often as you like: lesson packs, worksheets, PowerPoints, flashcards and quizzes for ESL, Math and Science.\n\n"
        . ($popularText !== '' ? "POPULAR RIGHT NOW\n" . $popularText . "\n" : '')
        . "Browse all resources: {$resourcesUrl}\n\n"
        . ($credits !== null && $credits > 0
            ? "YOU HAVE {$credits} FREE PRO PREVIEW" . ($credits === 1 ? '' : 'S') . "\nEach credit unlocks one members-only resource, including its answer keys and extra files.\nExplore Pro resources: {$proUrl}\n\n"
            : '')
        . "FREE CLASSROOM TOOLS\n"
        . "- Random Name Picker: pick students fairly, with no repeats until everyone has had a turn.\n  {$pickerUrl}\n"
        . "- Classroom Timer: a big, clear countdown for activities, tests and tidy-up time.\n  {$timerUrl}\n\n"
        . "Happy teaching,\nThe " . SITE_NAME . " team\n\n"
        . "You are receiving this email because you created an account at " . preg_replace('#^https?://#', '', SITE_URL) . ". Questions? Reply, or write to " . CONTACT_EMAIL . ".\n";

    return ['subject' => $subject, 'html' => $html, 'text' => $plain];
}

/** Lets the site owner know a new teacher signed up, since nothing else surfaces this in real time. */
function send_admin_new_registration_email(array $user): bool
{
    $subject = 'New teacher registered — ' . SITE_NAME;

    $body = '<p>A new teacher just registered:</p>'
        . '<p><strong>' . e($user['first_name'] . ' ' . $user['last_name']) . '</strong><br>' . e($user['email']) . '</p>';

    // Sent after the response, like the welcome email, so sign-up never waits on it.
    send_email_deferred(ADMIN_EMAIL, $subject, $body);
    return true;
}

/** Lets the site owner know a payment is waiting for review, since manual approval depends on someone noticing it. */
function send_admin_new_payment_email(array $user, array $payment): bool
{
    $subject = 'New payment submitted — ' . SITE_NAME;

    $body = '<p>' . e($user['first_name'] . ' ' . $user['last_name']) . ' (' . e($user['email']) . ') submitted a payment of '
        . e(format_currency($payment['amount'])) . ', awaiting your review.</p>'
        . '<p><a href="' . e(base_url('admin/payments.php')) . '">Review pending payments</a></p>';

    return send_email(ADMIN_EMAIL, $subject, $body);
}

function send_payment_submitted_email(array $user, array $payment): bool
{
    $subject = 'We received your payment — ' . SITE_NAME;

    $body = '<p>Hi ' . e($user['first_name']) . ',</p>'
        . '<p>Thanks! We received your payment submission of ' . e(format_currency($payment['amount'])) . ' and it\'s now awaiting review. '
        . 'We\'ll let you know as soon as it\'s approved — usually within a day.</p>';

    return send_email($user['email'], $subject, $body);
}

function send_payment_approved_email(array $user, string $expiryDate): bool
{
    $subject = 'Your ' . SITE_NAME . ' membership is active!';

    $body = '<p>Hi ' . e($user['first_name']) . ',</p>'
        . '<p>Your payment has been approved and your membership is now active until <strong>' . e(format_date($expiryDate)) . '</strong>.</p>'
        . '<p><a href="' . e(base_url('resources.php')) . '">Start browsing resources</a></p>';

    return send_email($user['email'], $subject, $body);
}

function send_bundle_purchase_email(array $user, array $bundle): bool
{
    $subject = 'Your ' . SITE_NAME . ' bundle purchase — ' . $bundle['title'];

    $body = '<p>Hi ' . e($user['first_name']) . ',</p>'
        . '<p>Thanks for your purchase! You now have permanent access to every resource in <strong>' . e($bundle['title']) . '</strong>.</p>'
        . '<p><a href="' . e(base_url('bundle.php?slug=' . rawurlencode($bundle['slug']))) . '">View your bundle</a></p>';

    return send_email($user['email'], $subject, $body);
}

function send_payment_rejected_email(array $user, string $note): bool
{
    $subject = 'About your recent ' . SITE_NAME . ' payment';

    $body = '<p>Hi ' . e($user['first_name']) . ',</p>'
        . '<p>We were unable to approve your recent payment submission.</p>'
        . ($note !== '' ? '<p><strong>Reason:</strong> ' . nl2br(e($note)) . '</p>' : '')
        . '<p>Please double-check your payment details and submit again, or contact us at '
        . '<a href="mailto:' . e(CONTACT_EMAIL) . '">' . e(CONTACT_EMAIL) . '</a> if you have questions.</p>';

    return send_email($user['email'], $subject, $body);
}

/** Sent by cron/expire-memberships.php a few days before an active membership's expiry_date. */
function send_membership_expiring_soon_email(array $user, string $expiryDate): bool
{
    $subject = 'Your ' . SITE_NAME . ' membership expires soon';

    $body = '<p>Hi ' . e($user['first_name']) . ',</p>'
        . '<p>Your membership expires on <strong>' . e(format_date($expiryDate)) . '</strong>. '
        . 'Renew before then to keep uninterrupted access to member resources.</p>'
        . '<p><a href="' . e(base_url('member/subscription.php')) . '">Renew your membership</a></p>';

    return send_email($user['email'], $subject, $body);
}

/** Sent by cron/expire-memberships.php when a membership transitions from active to expired. */
function send_membership_expired_email(array $user): bool
{
    $subject = 'Your ' . SITE_NAME . ' membership has expired';

    $body = '<p>Hi ' . e($user['first_name']) . ',</p>'
        . '<p>Your membership has expired, so members-only resources are no longer available for download.</p>'
        . '<p><a href="' . e(base_url('member/subscription.php')) . '">Renew your membership</a> to keep your access going.</p>';

    return send_email($user['email'], $subject, $body);
}

/** Confirms a "Request a Resource" submission — never promises the resource will actually be produced. */
function send_resource_request_confirmation_email(string $email, array $request): bool
{
    $subject = SITE_NAME . ' — Resource Request Received';

    $typeLabel = $request['resource_type'] === 'Other'
        ? ($request['resource_type_other'] ?: 'Other')
        : $request['resource_type'];

    $body = '<p>Thanks for helping us improve ' . e(SITE_NAME) . '!</p>'
        . '<p>We\'ve received your resource request:</p>'
        . '<p><strong>' . e($request['topic']) . '</strong><br>'
        . e($request['subject_name'] ?? '') . ' &middot; ' . e($request['grade_level'] ?? '') . ' &middot; ' . e($typeLabel) . '</p>'
        . '<p>We\'ll consider it when planning future resources. If it becomes a ' . e(SITE_NAME) . ' resource, we\'ll let you know at this email address — but we can\'t promise every request will be produced.</p>'
        . '<p><a href="' . e(base_url('resources.php')) . '">Browse ' . e(SITE_NAME) . ' resources</a></p>';

    return send_email($email, $subject, $body);
}

/** Sent to every supporter of a request once it's linked to a published resource. */
function send_resource_request_published_email(string $email, array $resource): bool
{
    $subject = 'Your requested ' . SITE_NAME . ' resource is now available';

    $body = '<p>Good news — a resource you requested is now available on ' . e(SITE_NAME) . ':</p>'
        . '<p><strong>' . e($resource['title']) . '</strong></p>'
        . '<p><a href="' . e(base_url('resource.php?slug=' . rawurlencode($resource['slug']))) . '">View the resource</a></p>'
        . '<p>Thanks for telling us what you needed!</p>';

    return send_email($email, $subject, $body);
}
