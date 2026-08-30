<?php
/**
 * Minimal Stripe REST API client — no SDK/Composer dependency, consistent
 * with the rest of this codebase. Only implements what this app actually
 * uses: creating a one-time Checkout Session and verifying webhook
 * signatures. See config/stripe.php for where the keys live.
 */

/**
 * POSTs form-encoded params to the Stripe API with the secret key as the
 * HTTP Basic Auth username (Stripe's documented auth scheme — no password).
 * Returns the decoded JSON body on any response (2xx or error), or null if
 * the request itself couldn't be made (network failure, etc).
 */
function stripe_api_request(string $method, string $endpoint, array $params = []): ?array
{
    $ch = curl_init('https://api.stripe.com/v1/' . ltrim($endpoint, '/'));

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD        => STRIPE_SECRET_KEY . ':',
        CURLOPT_TIMEOUT        => 15,
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    }

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        error_log('Stripe API request failed: ' . $curlError);
        return null;
    }

    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : null;
}

/**
 * Creates a one-time (mode=payment) Checkout Session for one month of
 * membership, in either USD (STRIPE_PRICE_USD, the default — card payments
 * from anywhere) or THB (PRICE_MONTHLY, config.php — added so Thai-issued
 * cards that reject foreign-currency charges have a card option too;
 * bank transfer/PromptPay remain available as the non-card THB option
 * regardless). $currency is 'usd' or 'thb'; anything else falls back to
 * 'usd'. Returns ['success' => bool, 'url' => ?string, 'error' => ?string].
 * The caller redirects the browser to the returned url — Stripe hosts the
 * actual payment page, so card details never touch this server.
 */
function create_stripe_checkout_session(array $user, string $currency = 'usd'): array
{
    $currency = $currency === 'thb' ? 'thb' : 'usd';
    $unitAmount = $currency === 'thb'
        ? (int)round(PRICE_MONTHLY * 100)
        : (int)round(STRIPE_PRICE_USD * 100);

    $params = [
        'mode'                        => 'payment',
        'customer_email'              => $user['email'],
        'success_url'                 => base_url('member/subscription.php?stripe=success&session_id={CHECKOUT_SESSION_ID}'),
        'cancel_url'                  => base_url('member/subscription.php?stripe=cancelled'),
        'line_items' => [
            [
                'quantity'   => 1,
                'price_data' => [
                    'currency'     => $currency,
                    'unit_amount'  => $unitAmount,
                    'product_data' => [
                        'name' => SITE_NAME . ' Membership — 1 Month',
                    ],
                ],
            ],
        ],
        'metadata' => [
            'user_id' => (string)$user['id'],
        ],
    ];

    $result = stripe_api_request('POST', 'checkout/sessions', $params);

    if (!$result || isset($result['error'])) {
        error_log('Stripe checkout session creation failed: ' . ($result['error']['message'] ?? 'unknown error'));
        return ['success' => false, 'url' => null, 'error' => 'Could not start the Stripe checkout. Please try again or use another payment method.'];
    }

    return ['success' => true, 'url' => $result['url'] ?? null, 'error' => null];
}

/**
 * Verifies a Stripe webhook request came from Stripe, per Stripe's
 * documented signature scheme: the Stripe-Signature header carries a
 * timestamp and one or more v1 signatures, each an HMAC-SHA256 of
 * "{timestamp}.{raw request body}" keyed with the webhook signing secret.
 * A tolerance window guards against replayed old requests.
 */
function verify_stripe_webhook_signature(string $payload, string $sigHeader, string $secret, int $toleranceSeconds = 300): bool
{
    $parts = [];
    foreach (explode(',', $sigHeader) as $pair) {
        $pair = explode('=', $pair, 2);
        if (count($pair) === 2) {
            $parts[trim($pair[0])][] = trim($pair[1]);
        }
    }

    $timestamp = isset($parts['t'][0]) ? (int)$parts['t'][0] : 0;
    $signatures = $parts['v1'] ?? [];

    if ($timestamp === 0 || empty($signatures)) {
        return false;
    }

    if (abs(time() - $timestamp) > $toleranceSeconds) {
        return false;
    }

    $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);

    foreach ($signatures as $signature) {
        if (hash_equals($expected, $signature)) {
            return true;
        }
    }

    return false;
}
