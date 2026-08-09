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
