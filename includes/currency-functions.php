<?php
/**
 * Two display currencies: Thai baht for teachers in Thailand, US dollars
 * for everyone else. Baht prices stay the source of truth (config.php's
 * PRICE_MONTHLY / PRICE_ANNUAL and each bundle's price); the dollar prices
 * below are set separately so they can be round, familiar amounts.
 *
 * Which currency a visitor sees:
 *   1. their own choice (the "Prices in" switch), kept in a cookie;
 *   2. otherwise, on their first page view, assets/js/currency.js guesses
 *      from the browser's time zone (Asia/Bangkok = baht, anything else =
 *      dollars), saves that guess in the same cookie and switches every
 *      price on the page to match;
 *   3. a logged-in teacher with no cookie gets the currency of the
 *      country on their profile;
 *   4. failing all that, baht (the site's original currency).
 *
 * PromptPay only works in baht, so PromptPay options are only shown with
 * baht prices; dollar checkout is card-only through Stripe.
 *
 * The dollar prices can be overridden in config/config.php by defining the
 * same constants there (this file only defines them if they are missing).
 */

if (!defined('PRICE_MONTHLY_USD')) {
    define('PRICE_MONTHLY_USD', 5.99);
}
if (!defined('PRICE_ANNUAL_USD')) {
    define('PRICE_ANNUAL_USD', 29.00);
}
// Used only to derive bundle prices in dollars from their baht price.
if (!defined('THB_PER_USD')) {
    define('THB_PER_USD', 33);
}

const DISPLAY_CURRENCIES = ['THB', 'USD'];
const CURRENCY_COOKIE = 'tl_currency';

/** 'THB' or 'USD' for an untrusted value, or null if it is neither. */
function normalize_currency($value): ?string
{
    $value = strtoupper(trim((string)$value));
    return in_array($value, DISPLAY_CURRENCIES, true) ? $value : null;
}

/** The currency a teacher's profile country suggests, or null if unknown. */
function currency_for_country(?string $country): ?string
{
    $country = mb_strtolower(trim((string)$country));
    if ($country === '') {
        return null;
    }

    return in_array($country, ['thailand', 'th', 'thai', 'ไทย', 'ประเทศไทย'], true) ? 'THB' : 'USD';
}

/** The currency to use for a given user record (emails, etc.). */
function currency_for_user(array $user): string
{
    return currency_for_country($user['country'] ?? null) ?? 'THB';
}

/** The currency this visitor sees prices in (see the file comment for the order). */
function visitor_currency(): string
{
    static $currency = null;
    if ($currency !== null) {
        return $currency;
    }

    $fromCookie = normalize_currency($_COOKIE[CURRENCY_COOKIE] ?? '');
    if ($fromCookie !== null) {
        return $currency = $fromCookie;
    }

    if (!empty($_SESSION['user_id']) && function_exists('current_user')) {
        $user = current_user();
        $fromProfile = $user ? currency_for_country($user['country'] ?? null) : null;
        if ($fromProfile !== null) {
            return $currency = $fromProfile;
        }
    }

    return $currency = 'THB';
}

/**
 * Handles the "Prices in" switch: ?currency=usd|thb on any page saves the
 * choice in a cookie and redirects to the same page without the parameter,
 * so the parameter never ends up in shared or indexed URLs.
 */
function handle_currency_switch(): void
{
    if (!isset($_GET['currency']) || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        return;
    }

    $chosen = normalize_currency($_GET['currency']);
    if ($chosen !== null && !headers_sent()) {
        setcookie(CURRENCY_COOKIE, $chosen, [
            'expires'  => time() + 365 * 24 * 3600,
            'path'     => '/',
            'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => false, // assets/js/currency.js reads it
            'samesite' => 'Lax',
        ]);
    }

    $parts = parse_url($_SERVER['REQUEST_URI'] ?? '/');
    parse_str($parts['query'] ?? '', $query);
    unset($query['currency']);
    $target = ($parts['path'] ?? '/') . ($query ? '?' . http_build_query($query) : '');

    header('Location: ' . $target, true, 302);
    exit;
}

/** Teacher Pro price for a plan in the given currency. */
function plan_price_in(string $plan, string $currency): float
{
    if ($currency === 'USD') {
        return (float)($plan === 'annual' ? PRICE_ANNUAL_USD : PRICE_MONTHLY_USD);
    }

    return (float)($plan === 'annual' ? PRICE_ANNUAL : PRICE_MONTHLY);
}

/** A bundle's dollar price, derived from its baht price and rounded up to a .99 ending. */
function usd_price_for_thb(float $thb): float
{
    if ($thb <= 0) {
        return 0.0;
    }

    return max(0.99, ceil($thb / THB_PER_USD) - 0.01);
}

/** A bundle's price in the given currency. */
function bundle_price_in(float $thbPrice, string $currency): float
{
    return $currency === 'USD' ? usd_price_for_thb($thbPrice) : $thbPrice;
}

/** ฿199, $5.99, $29 — whole dollar amounts drop the cents. */
function format_money($amount, string $currency): string
{
    $amount = (float)$amount;

    if (normalize_currency($currency) === 'USD') {
        $decimals = abs($amount - round($amount)) < 0.005 ? 0 : 2;
        return '$' . number_format($amount, $decimals);
    }

    return format_currency($amount);
}

/**
 * A price that follows the visitor's currency: rendered in the server's
 * best guess, with both versions in data attributes so
 * assets/js/currency.js can switch it without a reload.
 */
function price_tag(float $thb, float $usd): string
{
    $thbText = format_money($thb, 'THB');
    $usdText = format_money($usd, 'USD');
    $current = visitor_currency() === 'USD' ? $usdText : $thbText;

    return '<span class="js-price" data-thb="' . e($thbText) . '" data-usd="' . e($usdText) . '">' . e($current) . '</span>';
}

/** price_tag() for a Teacher Pro plan. */
function plan_price_tag(string $plan): string
{
    return price_tag(plan_price_in($plan, 'THB'), plan_price_in($plan, 'USD'));
}

/** price_tag() for a bundle's baht price. */
function bundle_price_tag(float $thbPrice): string
{
    return price_tag($thbPrice, usd_price_for_thb($thbPrice));
}

/** Plain-text plan prices in both currencies, for meta descriptions. */
function plan_price_text_both(string $plan): string
{
    return format_money(plan_price_in($plan, 'THB'), 'THB') . ' (US' . format_money(plan_price_in($plan, 'USD'), 'USD') . ')';
}

/**
 * Attributes for an element that should only show in one currency, e.g.
 * PromptPay options (baht only). Hidden on the server's first guess when it
 * doesn't match; assets/js/currency.js keeps it in step after that.
 */
function currency_only_attrs(string $currency): string
{
    return 'data-currency-only="' . e($currency) . '"' . (visitor_currency() === $currency ? '' : ' hidden');
}

/** The "Prices in: ฿ THB | $ USD" switch. */
function currency_switch_html(): string
{
    $current = visitor_currency();
    $out = '<div class="currency-switch small text-secondary" data-currency-switch>Prices in: ';

    // Keep the page's own query (plan=annual, slug=...) for visitors without JavaScript.
    parse_str((string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY), $query);

    foreach (['THB' => '฿ THB', 'USD' => '$ USD'] as $code => $label) {
        $active = $current === $code;
        $href = '?' . http_build_query(array_merge($query, ['currency' => strtolower($code)]));
        $out .= '<a href="' . e($href) . '" class="currency-switch-option' . ($active ? ' active fw-bold' : '') . '"'
            . ' data-currency-choice="' . $code . '"' . ($active ? ' aria-current="true"' : '')
            . ' data-ga-event="select_currency" data-ga-params="' . e(json_encode(['currency' => $code])) . '">' . e($label) . '</a>';
        if ($code === 'THB') {
            $out .= ' <span aria-hidden="true">|</span> ';
        }
    }

    return $out . '</div>';
}
