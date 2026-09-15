<?php
/**
 * General-purpose helpers used across the site (URLs, formatting,
 * flash messages, pagination). Nothing here talks to the database.
 */

function base_url(string $path = ''): string
{
    return rtrim(SITE_URL, '/') . '/' . ltrim($path, '/');
}

function asset_url(string $path): string
{
    return rtrim(SITE_URL, '/') . '/assets/' . ltrim($path, '/');
}

/**
 * Strips known tracking/marketing query parameters from a path+query
 * string before it's used to build a canonical URL — a link like
 * resource.php?slug=x&utm_source=facebook must still canonicalize to
 * the clean resource.php?slug=x, not to itself with the tracking noise
 * intact (which would create a duplicate indexable URL per visit source).
 * Leaves every other parameter (slug, subject_id, grade, page, search,
 * etc.) untouched since those define genuinely different page content.
 */
function strip_tracking_params(string $requestUri): string
{
    $trackingKeys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'fbclid', 'gclid', 'msclkid', 'ref', 'blocked', 'registered'];

    $parts = parse_url($requestUri);
    $path = $parts['path'] ?? '/';

    if (empty($parts['query'])) {
        return $path;
    }

    parse_str($parts['query'], $queryParams);

    foreach ($trackingKeys as $key) {
        unset($queryParams[$key]);
    }

    if (empty($queryParams)) {
        return $path;
    }

    return $path . '?' . http_build_query($queryParams);
}

function redirect(string $path): void
{
    $url = (str_starts_with($path, 'http://') || str_starts_with($path, 'https://'))
        ? $path
        : base_url($path);

    header('Location: ' . $url);
    exit;
}

/**
 * Validates a user-supplied "redirect back to" path, rejecting anything
 * that isn't a safe same-site relative path (prevents open-redirect attacks).
 */
function safe_internal_path(?string $path, string $default = ''): string
{
    if (empty($path)) {
        return $default;
    }

    if (str_starts_with($path, '/') && !str_starts_with($path, '//') && !preg_match('#^https?://#i', $path)) {
        return ltrim($path, '/');
    }

    return $default;
}

// -----------------------------------------------------------------------
// FLASH MESSAGES
// -----------------------------------------------------------------------

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** Returns and clears any queued flash messages. */
function flash_get(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

// -----------------------------------------------------------------------
// FORMATTING
// -----------------------------------------------------------------------

function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    return trim($text, '-');
}

function format_currency($amount): string
{
    return SITE_CURRENCY_SYMBOL . number_format((float)$amount, 0);
}

function format_date($date, string $format = 'd M Y'): string
{
    if (empty($date)) {
        return '';
    }

    $timestamp = $date instanceof DateTimeInterface ? $date->getTimestamp() : strtotime((string)$date);

    return $timestamp ? date($format, $timestamp) : '';
}

function format_file_size(int $bytes): string
{
    if ($bytes <= 0) {
        return '0 B';
    }

    $units = ['B', 'KB', 'MB', 'GB'];
    $power = (int)floor(log($bytes, 1024));
    $power = min($power, count($units) - 1);

    return round($bytes / (1024 ** $power), 1) . ' ' . $units[$power];
}

function truncate_text(string $text, int $length = 150): string
{
    $text = trim($text);

    if (mb_strlen($text) <= $length) {
        return $text;
    }

    return mb_substr($text, 0, $length) . '...';
}

// -----------------------------------------------------------------------
// PAGINATION
// -----------------------------------------------------------------------

function current_url_with_params(array $params): string
{
    $query = array_merge($_GET, $params);
    $path = strtok($_SERVER['REQUEST_URI'] ?? '', '?');

    return $path . '?' . http_build_query($query);
}

/**
 * Renders pagination — the original full page-number list unchanged on
 * tablet/desktop (>= md, 768px), and a windowed, position-stable version
 * on mobile only (< md).
 *
 * A resource/user/review list with many pages used to render EVERY page
 * number in one unwrapped row (Bootstrap's .pagination is display:flex
 * with no wrap) everywhere, including mobile — fine on desktop where
 * there's room, but on narrow viewports with enough pages it overflowed
 * the screen horizontally, and being centered, the page even loaded
 * scrolled into the middle of the list rather than showing page 1. There
 * was no reason to change the desktop/tablet experience to fix a
 * mobile-only problem, so it's kept exactly as it was and only the
 * mobile markup differs.
 *
 * The mobile version windows the numbers (first, last, and a small range
 * around the current page, with an ellipsis for any gap) so it can never
 * overflow regardless of how many total pages exist. Previous/Next are
 * their own separate <ul class="pagination"> groups (not part of the
 * same flex line as the numbers), pinned to the far left/right of the
 * outer flex row via justify-content-between — the page-number window's
 * width varies by page (e.g. "1 2 … 9" near the start vs "1 … 4 5 6 … 9"
 * in the middle vs "1 … 8 9" near the end), and if Previous/Next shared
 * one centered row with the numbers, that changing width shifted the
 * whole centered block sideways on every click, visibly relocating Next
 * (and Previous) each time. Pinning them to fixed edges keeps both
 * stationary regardless of how many numbers are shown.
 */
function render_pagination(int $currentPage, int $totalPages): string
{
    if ($totalPages <= 1) {
        return '';
    }

    $prevDisabled = $currentPage <= 1 ? ' disabled' : '';
    $nextDisabled = $currentPage >= $totalPages ? ' disabled' : '';
    $prevUrl = e(current_url_with_params(['page' => max(1, $currentPage - 1)]));
    $nextUrl = e(current_url_with_params(['page' => min($totalPages, $currentPage + 1)]));

    // --- Desktop/tablet (>= md): the original, unwindowed full list ---
    $desktopHtml = '<ul class="pagination justify-content-center d-none d-md-flex">'
        . '<li class="page-item' . $prevDisabled . '"><a class="page-link" href="' . $prevUrl . '">Previous</a></li>';
    for ($i = 1; $i <= $totalPages; $i++) {
        $active = $i === $currentPage ? ' active' : '';
        $desktopHtml .= '<li class="page-item' . $active . '">'
            . '<a class="page-link" href="' . e(current_url_with_params(['page' => $i])) . '">' . $i . '</a></li>';
    }
    $desktopHtml .= '<li class="page-item' . $nextDisabled . '"><a class="page-link" href="' . $nextUrl . '">Next</a></li></ul>';

    // --- Mobile (< md): windowed, with Previous/Next pinned to fixed edges ---
    $prevHtml = '<ul class="pagination mb-0"><li class="page-item' . $prevDisabled . '">'
        . '<a class="page-link" href="' . $prevUrl . '">Previous</a></li></ul>';

    $pagesToShow = array_unique(array_filter(
        [1, $currentPage - 1, $currentPage, $currentPage + 1, $totalPages],
        static fn(int $p): bool => $p >= 1 && $p <= $totalPages
    ));
    sort($pagesToShow);

    $numbersHtml = '<ul class="pagination pagination-numbers mb-0 flex-wrap justify-content-center">';
    $previousShown = 0;
    foreach ($pagesToShow as $i) {
        if ($i - $previousShown > 1) {
            $numbersHtml .= '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
        }
        $active = $i === $currentPage ? ' active' : '';
        $numbersHtml .= '<li class="page-item' . $active . '">'
            . '<a class="page-link" href="' . e(current_url_with_params(['page' => $i])) . '">' . $i . '</a></li>';
        $previousShown = $i;
    }
    $numbersHtml .= '</ul>';

    $nextHtml = '<ul class="pagination mb-0"><li class="page-item' . $nextDisabled . '">'
        . '<a class="page-link" href="' . $nextUrl . '">Next</a></li></ul>';

    $mobileHtml = '<div class="d-flex d-md-none justify-content-between align-items-start flex-nowrap gap-2">'
        . $prevHtml . $numbersHtml . $nextHtml . '</div>';

    return '<nav aria-label="Page navigation">' . $desktopHtml . $mobileHtml . '</nav>';
}
