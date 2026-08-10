<?php
/**
 * Email abstraction. Everything else in the app should call send_email()
 * rather than mail() directly, so swapping in SMTP later (see
 * config.php SMTP_* settings) only requires changing this one file.
 */

function send_email(string $to, string $subject, string $bodyHtml): bool
{
    // Extension point: when SMTP_ENABLED is turned on in config.php, wire a
    // real SMTP client here (e.g. PHPMailer). For now, and for most
    // Hostinger shared-hosting setups, PHP's built-in mail() is sufficient.

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . SMTP_FROM_NAME . ' <' . SMTP_FROM_EMAIL . '>',
    ];

    $sent = @mail($to, $subject, $bodyHtml, implode("\r\n", $headers));

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
