<?php
/**
 * Email abstraction. Everything else in the app should call send_email()
 * rather than mail() directly, so swapping in SMTP later (see
 * config.php SMTP_* settings) only requires changing this one file.
 */

function send_email(string $to, string $subject, string $bodyHtml): bool
{
    // Extension point: when SMTP_ENABLED is turned on in config.php, wire a
    // real SMTP client here (e.g. PHPMailer). For now, and for most cPanel
    // shared-hosting setups, PHP's built-in mail() is sufficient.

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . SMTP_FROM_NAME . ' <' . SMTP_FROM_EMAIL . '>',
    ];

    // Aligns the envelope sender with the From header — without this, mail()
    // lets the server pick its own envelope sender, which can silently
    // mismatch SMTP_FROM_EMAIL and undermine SPF/DKIM alignment checks even
    // when both are otherwise configured correctly.
    $sent = @mail($to, $subject, $bodyHtml, implode("\r\n", $headers), '-f' . SMTP_FROM_EMAIL);

    if (!$sent) {
        error_log("Failed to send email to {$to}: {$subject}");
    }

    return $sent;
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

function send_welcome_email(array $user): bool
{
    $subject = 'Welcome to ' . SITE_NAME . '!';

    $body = '<p>Hi ' . e($user['first_name']) . ',</p>'
        . '<p>Thanks for creating your ' . e(SITE_NAME) . ' account! You can browse free resources right away.</p>'
        . '<p><a href="' . e(base_url('member/subscription.php')) . '">Subscribe for ' . e(format_currency(SUBSCRIPTION_PRICE)) . '/month</a> '
        . 'any time to unlock the full resource library.</p>';

    return send_email($user['email'], $subject, $body);
}

/** Lets the site owner know a new teacher signed up, since nothing else surfaces this in real time. */
function send_admin_new_registration_email(array $user): bool
{
    $subject = 'New teacher registered — ' . SITE_NAME;

    $body = '<p>A new teacher just registered:</p>'
        . '<p><strong>' . e($user['first_name'] . ' ' . $user['last_name']) . '</strong><br>' . e($user['email']) . '</p>';

    return send_email(ADMIN_EMAIL, $subject, $body);
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

/** Sent by cron/expire-memberships.php when a membership transitions from active to expired. */
function send_membership_expired_email(array $user): bool
{
    $subject = 'Your ' . SITE_NAME . ' membership has expired';

    $body = '<p>Hi ' . e($user['first_name']) . ',</p>'
        . '<p>Your membership has expired, so members-only resources are no longer available for download.</p>'
        . '<p><a href="' . e(base_url('member/subscription.php')) . '">Renew your membership</a> to keep your access going.</p>';

    return send_email($user['email'], $subject, $body);
}
