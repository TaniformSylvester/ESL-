<?php
/**
 * Minimal Google OAuth 2.0 client — no SDK/Composer dependency, consistent
 * with the rest of this codebase (see includes/stripe-functions.php for
 * the same pattern). Implements only the "Sign in with Google" flow:
 * building the consent-screen URL, exchanging the returned code for an
 * access token, and fetching the signed-in user's profile.
 */

function google_auth_url(string $state): string
{
    $params = [
        'client_id'     => GOOGLE_CLIENT_ID,
        'redirect_uri'  => base_url('google-callback.php'),
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'state'         => $state,
        'prompt'        => 'select_account',
    ];

    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
}

/** Exchanges an authorization code for tokens. Returns the decoded response, or null on failure. */
function google_exchange_code(string $code): ?array
{
    $params = [
        'code'          => $code,
        'client_id'     => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri'  => base_url('google-callback.php'),
        'grant_type'    => 'authorization_code',
    ];

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($params),
        CURLOPT_TIMEOUT        => 15,
    ]);
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        error_log('Google token exchange failed: ' . $curlError);
        return null;
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded) || isset($decoded['error'])) {
        error_log('Google token exchange error: ' . ($decoded['error_description'] ?? $decoded['error'] ?? 'unknown'));
        return null;
    }

    return $decoded;
}

/** Fetches the signed-in user's profile using their access token. Returns null on failure. */
function google_fetch_userinfo(string $accessToken): ?array
{
    $ch = curl_init('https://www.googleapis.com/oauth2/v3/userinfo');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $accessToken],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        error_log('Google userinfo fetch failed: ' . $curlError);
        return null;
    }

    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : null;
}

/**
 * Finds the user matching this Google profile, linking or creating an
 * account as needed. Email is only trusted when Google reports it verified
 * — an unverified email could belong to someone else. Matching by
 * google_id first (not just email) means a later email change on the
 * Google side can't silently hijack a different local account.
 *
 * Returns ['user' => array, 'is_new' => bool] or ['error' => string].
 */
function find_or_create_google_user(array $profile): array
{
    if (empty($profile['sub']) || empty($profile['email'])) {
        return ['error' => 'Google did not return the expected profile information.'];
    }
    if (empty($profile['email_verified'])) {
        return ['error' => 'Your Google email is not verified. Please verify it with Google and try again.'];
    }

    $googleId = (string)$profile['sub'];
    $email = strtolower(trim((string)$profile['email']));
    $db = getDB();

    $stmt = $db->prepare('SELECT id, first_name, last_name, email, role, school_name, country, phone, is_active, created_at FROM users WHERE google_id = ? LIMIT 1');
    $stmt->execute([$googleId]);
    $user = $stmt->fetch();
    if ($user) {
        return ['user' => $user, 'is_new' => false];
    }

    // No account linked to this Google ID yet — if an account with this
    // (Google-verified) email already exists, link it rather than creating
    // a duplicate; otherwise this is a brand-new signup.
    $stmt = $db->prepare('SELECT id, first_name, last_name, email, role, school_name, country, phone, is_active, created_at FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $existing = $stmt->fetch();

    if ($existing) {
        $db->prepare('UPDATE users SET google_id = ? WHERE id = ?')->execute([$googleId, $existing['id']]);
        return ['user' => $existing, 'is_new' => false];
    }

    $firstName = clean_input((string)($profile['given_name'] ?? 'Teacher'));
    $lastName = clean_input((string)($profile['family_name'] ?? ''));
    // Google accounts never need a usable password — a random hash means
    // the password-login form simply can never match it.
    $randomPasswordHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);

    $db->beginTransaction();
    try {
        $db->prepare(
            'INSERT INTO users (first_name, last_name, email, password_hash, google_id, role) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$firstName, $lastName ?: 'Teacher', $email, $randomPasswordHash, $googleId, 'teacher']);

        $userId = (int)$db->lastInsertId();

        $db->prepare("INSERT INTO memberships (user_id, status) VALUES (?, 'inactive')")->execute([$userId]);

        $db->commit();
    } catch (PDOException $e) {
        $db->rollBack();
        if ((int)$e->getCode() === 23000) {
            return ['error' => 'An account with this email already exists.'];
        }
        error_log('Google signup failed: ' . $e->getMessage());
        return ['error' => 'Something went wrong creating your account. Please try again.'];
    }

    $stmt = $db->prepare('SELECT id, first_name, last_name, email, role, school_name, country, phone, is_active, created_at FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);

    return ['user' => $stmt->fetch(), 'is_new' => true];
}
