<?php
/**
 * Security helpers: sessions, CSRF, output escaping, input cleaning,
 * and a lightweight rate limiter for public-facing forms.
 */

function secure_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_name(defined('SESSION_NAME') ? SESSION_NAME : 'app_session');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

/** Escape a value for safe HTML output. */
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/** Trim and strip tags from raw user input. */
function clean_input(?string $value): string
{
    return trim(strip_tags((string)$value));
}

// -----------------------------------------------------------------------
// CSRF PROTECTION
// -----------------------------------------------------------------------

function generate_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf_token(?string $token): bool
{
    return isset($_SESSION['csrf_token'])
        && is_string($token)
        && $token !== ''
        && hash_equals($_SESSION['csrf_token'], $token);
}

/** Echo a hidden CSRF input field inside a <form>. */
function csrf_field(): void
{
    echo '<input type="hidden" name="csrf_token" value="' . e(generate_csrf_token()) . '">';
}

/** Call at the top of every POST handler. Halts the request if the token is missing/invalid. */
function require_csrf(): void
{
    $token = $_POST['csrf_token'] ?? null;

    if (!verify_csrf_token($token)) {
        http_response_code(403);
        die('Your session expired or the form was submitted incorrectly. Please go back and try again.');
    }
}

// -----------------------------------------------------------------------
// LIGHTWEIGHT RATE LIMITING (session-based)
// Used for things like the contact form and password-reset requests.
// Login attempt lockout uses persistent DB columns instead (see auth.php).
// -----------------------------------------------------------------------

function too_many_attempts(string $key, int $maxAttempts, int $windowSeconds): bool
{
    $now = time();

    if (!isset($_SESSION['rate_limit'][$key]) || !is_array($_SESSION['rate_limit'][$key])) {
        $_SESSION['rate_limit'][$key] = [];
    }

    $_SESSION['rate_limit'][$key] = array_values(array_filter(
        $_SESSION['rate_limit'][$key],
        fn($timestamp) => $timestamp > $now - $windowSeconds
    ));

    return count($_SESSION['rate_limit'][$key]) >= $maxAttempts;
}

function record_attempt(string $key): void
{
    $_SESSION['rate_limit'][$key][] = time();
}
