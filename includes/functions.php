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
 * asset_url() plus a ?v=<file mtime> cache-buster. .htaccess tells browsers
 * to cache CSS/JS/images for a month, so anything that changes between
 * deploys must use this or returning visitors keep the stale copy.
 */
function versioned_asset_url(string $path): string
{
    $file = ROOT_PATH . '/assets/' . ltrim($path, '/');

    return asset_url($path) . (is_file($file) ? '?v=' . filemtime($file) : '');
}

/** Maps a subject name to the CSS accent key behind the site-wide --subject-* tokens (esl | math | science). */
function subject_key(string $subject): string
{
    return match (true) {
        stripos($subject, 'math') !== false    => 'math',
        stripos($subject, 'science') !== false => 'science',
        default                                => 'esl',
    };
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
 * The page numbers to show for a windowed pagination bar: always the first
 * and last page, plus $radius pages either side of the current one, with
 * null marking a gap (rendered as an ellipsis). A gap of exactly one page
 * shows that page instead ("1 2 3", never "1 … 3"), so the bar never hides
 * a single number behind an ellipsis that's just as wide.
 *
 * @return array<int|null>
 */
function pagination_window(int $currentPage, int $totalPages, int $radius): array
{
    $pages = [];
    for ($i = 1; $i <= $totalPages; $i++) {
        if ($i === 1 || $i === $totalPages || abs($i - $currentPage) <= $radius) {
            $pages[] = $i;
        }
    }

    $window = [];
    $previous = 0;
    foreach ($pages as $i) {
        if ($i - $previous === 2) {
            $window[] = $i - 1;
        } elseif ($i - $previous > 2) {
            $window[] = null;
        }
        $window[] = $i;
        $previous = $i;
    }

    return $window;
}

/**
 * Renders pagination as a windowed bar (first, last, the pages around the
 * current one, ellipses for the gaps), so it stays at most eleven buttons
 * wide whether a list has 3 pages or 300.
 *
 * Desktop/tablet (>= md) shows two pages either side of the current one
 * in one centered row. It used to print every page number in a single
 * unwrapped row; once the resource library passed ~30 pages that row ran
 * off both edges of the screen, hiding Previous, page 1 and the current
 * page. Mobile (< md) shows one page either side, with Previous/Next
 * pinned to the far left/right (justify-content-between) so they don't
 * shift sideways as the number group changes width from page to page.
 * A "Page X of Y" line under the bar keeps position clear either way.
 */
function render_pagination(int $currentPage, int $totalPages): string
{
    if ($totalPages <= 1) {
        return '';
    }

    $currentPage = max(1, min($currentPage, $totalPages));
    $isFirst = $currentPage <= 1;
    $isLast = $currentPage >= $totalPages;

    // $compact (mobile) shows just the arrow; the full word stays as the
    // accessible name either way.
    $edgeItem = static function (string $rel, bool $compact) use ($currentPage, $isFirst, $isLast): string {
        $isPrev = $rel === 'prev';
        $disabled = $isPrev ? $isFirst : $isLast;
        $word = $isPrev ? 'Previous' : 'Next';
        $label = $compact
            ? '<span aria-hidden="true">' . ($isPrev ? '&lsaquo;' : '&rsaquo;') . '</span><span class="visually-hidden">' . $word . ' page</span>'
            : ($isPrev ? '&lsaquo; Previous' : 'Next &rsaquo;');
        $class = 'page-link' . ($compact ? ' page-link-arrow' : '');
        if ($disabled) {
            return '<li class="page-item disabled"><span class="' . $class . '" aria-disabled="true">' . $label . '</span></li>';
        }
        $target = $isPrev ? $currentPage - 1 : $currentPage + 1;
        return '<li class="page-item"><a class="' . $class . '" href="' . e(current_url_with_params(['page' => $target])) . '" rel="' . $rel . '">' . $label . '</a></li>';
    };

    $numberItems = static function (int $radius) use ($currentPage, $totalPages): string {
        $html = '';
        foreach (pagination_window($currentPage, $totalPages, $radius) as $i) {
            if ($i === null) {
                $html .= '<li class="page-item disabled pagination-gap" aria-hidden="true"><span class="page-link">&hellip;</span></li>';
            } elseif ($i === $currentPage) {
                $html .= '<li class="page-item active"><span class="page-link" aria-current="page">' . $i . '</span></li>';
            } else {
                $html .= '<li class="page-item"><a class="page-link" href="' . e(current_url_with_params(['page' => $i])) . '" aria-label="Page ' . $i . '">' . $i . '</a></li>';
            }
        }
        return $html;
    };

    // --- Desktop/tablet (>= md): one centered row ---
    $desktopHtml = '<ul class="pagination justify-content-center flex-wrap d-none d-md-flex mb-0">'
        . $edgeItem('prev', false) . $numberItems(2) . $edgeItem('next', false) . '</ul>';

    // --- Mobile (< md): Previous/Next pinned to fixed edges ---
    $mobileHtml = '<div class="pagination-mobile d-flex d-md-none justify-content-between align-items-start flex-nowrap">'
        . '<ul class="pagination mb-0">' . $edgeItem('prev', true) . '</ul>'
        . '<ul class="pagination pagination-numbers mb-0 flex-wrap justify-content-center">' . $numberItems(1) . '</ul>'
        . '<ul class="pagination mb-0">' . $edgeItem('next', true) . '</ul>'
        . '</div>';

    $summary = '<p class="pagination-summary">Page ' . $currentPage . ' of ' . $totalPages . '</p>';

    return '<nav class="pagination-nav" aria-label="Page navigation">' . $desktopHtml . $mobileHtml . $summary . '</nav>';
}
