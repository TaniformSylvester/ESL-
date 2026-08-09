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

function render_pagination(int $currentPage, int $totalPages): string
{
    if ($totalPages <= 1) {
        return '';
    }

    $html = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center">';

    $prevDisabled = $currentPage <= 1 ? ' disabled' : '';
    $html .= '<li class="page-item' . $prevDisabled . '">'
        . '<a class="page-link" href="' . e(current_url_with_params(['page' => max(1, $currentPage - 1)])) . '">Previous</a></li>';

    for ($i = 1; $i <= $totalPages; $i++) {
        $active = $i === $currentPage ? ' active' : '';
        $html .= '<li class="page-item' . $active . '">'
            . '<a class="page-link" href="' . e(current_url_with_params(['page' => $i])) . '">' . $i . '</a></li>';
    }

    $nextDisabled = $currentPage >= $totalPages ? ' disabled' : '';
    $html .= '<li class="page-item' . $nextDisabled . '">'
        . '<a class="page-link" href="' . e(current_url_with_params(['page' => min($totalPages, $currentPage + 1)])) . '">Next</a></li>';

    $html .= '</ul></nav>';

    return $html;
}
