<?php
/**
 * Authentication: registration, login, logout, session/role checks,
 * and password-reset token handling. Every access check elsewhere in
 * the app should go through the functions here rather than reading
 * $_SESSION directly.
 */

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

function is_admin(): bool
{
    return is_logged_in() && ($_SESSION['user_role'] ?? null) === 'admin';
}

/** Returns the fresh logged-in user row (without password_hash), or null. */
function current_user(): ?array
{
    static $user = null;
    static $loaded = false;

    if ($loaded) {
        return $user;
    }
    $loaded = true;

    if (!is_logged_in()) {
        return null;
    }

    $stmt = getDB()->prepare(
        'SELECT id, first_name, last_name, email, role, school_name, country, phone, is_active, created_at
         FROM users WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch();

    $user = $row ?: null;

    return $user;
}

/** Redirects guests to the login page, preserving where they were headed. */
function require_login(): void
{
    if (!is_logged_in()) {
        $current = $_SERVER['REQUEST_URI'] ?? '';
        redirect('login.php' . ($current !== '' ? '?redirect=' . urlencode($current) : ''));
    }
    enforce_single_session();
}

/** Redirects already-logged-in users away from guest-only pages (login, register). */
function require_guest(): void
{
    if (is_logged_in()) {
        redirect('dashboard.php');
    }
}

/**
 * Requires an active admin session. Unlike require_login(), this sends
 * guests to the dedicated admin login page rather than the member one —
 * the admin area has its own entry point even though it shares the same
 * users table and session mechanism.
 */
function require_admin(): void
{
    if (!is_logged_in()) {
        redirect('admin/login.php');
    }

    $user = current_user();

    if (!$user || $user['role'] !== 'admin') {
        redirect('403.php');
    }
}

// -----------------------------------------------------------------------
// REGISTRATION
// -----------------------------------------------------------------------

function validate_email_format(string $email): bool
{
    return strlen($email) <= 190 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function email_exists(string $email): bool
{
    $stmt = getDB()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    return (bool)$stmt->fetch();
}

/**
 * Validates and creates a new teacher account plus its (inactive) membership row.
 * Returns ['success' => bool, 'errors' => array<string,string>, 'user_id' => ?int]
 */
function register_teacher(array $input): array
{
    $errors = [];

    $firstName = clean_input($input['first_name'] ?? '');
    $lastName  = clean_input($input['last_name'] ?? '');
    $email     = strtolower(clean_input($input['email'] ?? ''));
    $password  = (string)($input['password'] ?? '');
    $confirm   = (string)($input['confirm_password'] ?? '');
    $school    = clean_input($input['school_name'] ?? '');
    $country   = clean_input($input['country'] ?? '');
    $phone     = clean_input($input['phone'] ?? '');

    if ($firstName === '' || mb_strlen($firstName) > 100) {
        $errors['first_name'] = 'Please enter your first name.';
    }
    if ($lastName === '' || mb_strlen($lastName) > 100) {
        $errors['last_name'] = 'Please enter your last name.';
    }
    if ($email === '' || !validate_email_format($email)) {
        $errors['email'] = 'Please enter a valid email address.';
    } elseif (email_exists($email)) {
        $errors['email'] = 'An account with this email already exists.';
    }
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors['password'] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
    } elseif (!preg_match('/[a-zA-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors['password'] = 'Password must contain at least one letter and one number.';
    }
    if ($password !== $confirm) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }
    if ($school === '' || mb_strlen($school) > 150) {
        $errors['school_name'] = 'Please enter your school name.';
    }
    if ($country === '' || mb_strlen($country) > 100) {
        $errors['country'] = 'Please enter your country.';
    }
    if ($phone !== '' && mb_strlen($phone) > 30) {
        $errors['phone'] = 'Phone number is too long.';
    }

    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors, 'user_id' => null];
    }

    $db = getDB();

    try {
        $db->beginTransaction();

        $stmt = $db->prepare(
            'INSERT INTO users (first_name, last_name, email, password_hash, role, school_name, country, phone)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $firstName,
            $lastName,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            'teacher',
            $school,
            $country,
            $phone !== '' ? $phone : null,
        ]);

        $userId = (int)$db->lastInsertId();

        $db->prepare('INSERT INTO memberships (user_id, status) VALUES (?, ?)')
            ->execute([$userId, 'inactive']);

        $db->commit();

        return ['success' => true, 'errors' => [], 'user_id' => $userId];
    } catch (PDOException $e) {
        $db->rollBack();

        if ((int)$e->getCode() === 23000) {
            return ['success' => false, 'errors' => ['email' => 'An account with this email already exists.'], 'user_id' => null];
        }

        error_log('Registration failed: ' . $e->getMessage());
        return ['success' => false, 'errors' => ['general' => 'Something went wrong. Please try again.'], 'user_id' => null];
    }
}

// -----------------------------------------------------------------------
// LOGIN / LOGOUT
// -----------------------------------------------------------------------

/**
 * Starts an authenticated session for the given user row. Also rotates
 * this account's session token, both in the session and in the users
 * table — since a fresh login always overwrites the stored token, any
 * other browser still holding the previous token stops matching, which
 * is what enforce_single_session() uses to sign that other session out.
 */
function login_user_session(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_name'] = $user['first_name'];

    $token = bin2hex(random_bytes(32));
    $_SESSION['session_token'] = $token;
    getDB()->prepare('UPDATE users SET current_session_token = ? WHERE id = ?')->execute([$token, $user['id']]);
}

/**
 * Enforces single-session-per-account for the member-facing login flow:
 * logging in elsewhere invalidates this session. Not applied to the admin
 * panel (require_admin() doesn't call this) — there's no incentive to
 * share admin credentials, so it would only add friction to the site
 * owner's own workflow across devices.
 */
function enforce_single_session(): void
{
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_token'])) {
        return;
    }

    $stmt = getDB()->prepare('SELECT current_session_token FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$_SESSION['user_id']]);
    $dbToken = $stmt->fetchColumn();

    if ($dbToken === false || !hash_equals((string)$dbToken, $_SESSION['session_token'])) {
        logout_user();
        flash_set('error', 'You were signed out because this account was signed in from another device.');
        redirect('login.php');
    }
}

function logout_user(): void
{
    unset($_SESSION['user_id'], $_SESSION['user_role'], $_SESSION['user_name']);
    session_regenerate_id(true);
}

/**
 * Attempts to authenticate an email/password pair, enforcing a persistent
 * lockout after repeated failures. Returns:
 *   ['success' => bool, 'error' => ?string, 'user' => ?array]
 */
function attempt_login(string $email, string $password): array
{
    $email = strtolower(clean_input($email));
    $genericError = 'Incorrect email or password.';

    if ($email === '' || $password === '') {
        return ['success' => false, 'error' => $genericError, 'user' => null];
    }

    $db = getDB();
    $stmt = $db->prepare(
        'SELECT id, first_name, last_name, email, password_hash, role, is_active, failed_login_attempts, locked_until
         FROM users WHERE email = ? LIMIT 1'
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        error_log("Login failure: unknown email '{$email}' from " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        return ['success' => false, 'error' => $genericError, 'user' => null];
    }

    if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
        return ['success' => false, 'error' => 'Too many failed attempts. Please try again in a few minutes.', 'user' => null];
    }

    if (!$user['is_active']) {
        return ['success' => false, 'error' => 'This account has been deactivated. Please contact support.', 'user' => null];
    }

    if (!password_verify($password, $user['password_hash'])) {
        $attempts = (int)$user['failed_login_attempts'] + 1;
        $lockedUntil = null;

        if ($attempts >= LOGIN_MAX_ATTEMPTS) {
            $lockedUntil = (new DateTime())->modify('+' . LOGIN_LOCKOUT_MINUTES . ' minutes')->format('Y-m-d H:i:s');
        }

        $db->prepare('UPDATE users SET failed_login_attempts = ?, locked_until = ? WHERE id = ?')
            ->execute([$attempts, $lockedUntil, $user['id']]);

        // Never log the password itself — only that an attempt failed, for whom, and from where.
        error_log("Login failure: wrong password for user #{$user['id']} ({$email}) from " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ($lockedUntil ? ' — account now locked' : ''));

        return ['success' => false, 'error' => $genericError, 'user' => null];
    }

    $db->prepare('UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE id = ?')
        ->execute([$user['id']]);

    unset($user['password_hash']);

    return ['success' => true, 'error' => null, 'user' => $user];
}

// -----------------------------------------------------------------------
// PROFILE / ACCOUNT MANAGEMENT
// -----------------------------------------------------------------------

/** Returns ['success' => bool, 'errors' => array<string,string>] */
function update_user_profile(int $userId, array $input): array
{
    $errors = [];

    $firstName = clean_input($input['first_name'] ?? '');
    $lastName  = clean_input($input['last_name'] ?? '');
    $school    = clean_input($input['school_name'] ?? '');
    $country   = clean_input($input['country'] ?? '');
    $phone     = clean_input($input['phone'] ?? '');

    if ($firstName === '' || mb_strlen($firstName) > 100) {
        $errors['first_name'] = 'Please enter your first name.';
    }
    if ($lastName === '' || mb_strlen($lastName) > 100) {
        $errors['last_name'] = 'Please enter your last name.';
    }
    if ($school === '' || mb_strlen($school) > 150) {
        $errors['school_name'] = 'Please enter your school name.';
    }
    if ($country === '' || mb_strlen($country) > 100) {
        $errors['country'] = 'Please enter your country.';
    }
    if ($phone !== '' && mb_strlen($phone) > 30) {
        $errors['phone'] = 'Phone number is too long.';
    }

    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }

    getDB()->prepare('UPDATE users SET first_name = ?, last_name = ?, school_name = ?, country = ?, phone = ? WHERE id = ?')
        ->execute([$firstName, $lastName, $school, $country, $phone !== '' ? $phone : null, $userId]);

    return ['success' => true, 'errors' => []];
}

/** Returns ['success' => bool, 'errors' => array<string,string>] */
function change_user_password(int $userId, string $currentPassword, string $newPassword, string $confirmPassword): array
{
    $errors = [];

    $stmt = getDB()->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($currentPassword, $row['password_hash'])) {
        $errors['current_password'] = 'Current password is incorrect.';
    }
    if (strlen($newPassword) < PASSWORD_MIN_LENGTH || !preg_match('/[a-zA-Z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
        $errors['new_password'] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters, with a letter and a number.';
    }
    if ($newPassword !== $confirmPassword) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }

    getDB()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
        ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);

    return ['success' => true, 'errors' => []];
}

// -----------------------------------------------------------------------
// PASSWORD RESET
// -----------------------------------------------------------------------

/** Creates a reset token for the given email and returns the RAW token, or null if no such active account exists. */
function create_password_reset_token(string $email): ?string
{
    $email = strtolower(clean_input($email));

    $stmt = getDB()->prepare('SELECT id FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        return null;
    }

    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);
    $expiresAt = (new DateTime())->modify('+' . PASSWORD_RESET_TTL_MINUTES . ' minutes')->format('Y-m-d H:i:s');

    getDB()->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)')
        ->execute([$user['id'], $tokenHash, $expiresAt]);

    return $rawToken;
}

/** Returns the associated user row if the raw token is valid, unused, and unexpired. */
function validate_password_reset_token(string $rawToken): ?array
{
    if ($rawToken === '') {
        return null;
    }

    $tokenHash = hash('sha256', $rawToken);

    $stmt = getDB()->prepare(
        'SELECT u.id, u.first_name, u.email
         FROM password_resets pr
         INNER JOIN users u ON u.id = pr.user_id
         WHERE pr.token_hash = ? AND pr.used = 0 AND pr.expires_at >= NOW()
         ORDER BY pr.id DESC LIMIT 1'
    );
    $stmt->execute([$tokenHash]);

    return $stmt->fetch() ?: null;
}

/** Sets a new password for the token's owner and invalidates all of that user's reset tokens. */
function consume_password_reset_token(string $rawToken, string $newPassword): bool
{
    $user = validate_password_reset_token($rawToken);

    if (!$user) {
        return false;
    }

    $db = getDB();
    $db->beginTransaction();

    try {
        $db->prepare('UPDATE users SET password_hash = ?, failed_login_attempts = 0, locked_until = NULL WHERE id = ?')
            ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $user['id']]);

        $db->prepare('UPDATE password_resets SET used = 1 WHERE user_id = ?')
            ->execute([$user['id']]);

        $db->commit();
        return true;
    } catch (PDOException $e) {
        $db->rollBack();
        error_log('Password reset failed: ' . $e->getMessage());
        return false;
    }
}
