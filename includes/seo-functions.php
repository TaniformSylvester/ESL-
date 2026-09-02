<?php
/**
 * Automatic SEO metadata generation — the scalable engine behind resource
 * and listing-page titles/descriptions, so a newly added resource gets a
 * search-friendly page with zero manual SEO work. An admin-entered
 * seo_title/meta_description on a resource always wins over the
 * generated version; auto-generation is only ever the fallback.
 *
 * Nothing here fabricates information — every generated string is built
 * strictly from real columns already on the resource/filter (title,
 * grade_level, subject_name, category_name, resource_type, topic,
 * description). If a field is empty, it's simply omitted from the
 * generated string rather than replaced with a placeholder.
 */

/** "[Title] | Grade [X] [Subject] Resource" — SITE_NAME is appended separately by includes/header.php's <title> tag, matching every other page. */
function generate_resource_seo_title(array $resource): string
{
    if (!empty($resource['seo_title'])) {
        return $resource['seo_title'];
    }

    $context = [];
    if (!empty($resource['grade_level'])) {
        $context[] = $resource['grade_level'];
    }
    if (!empty($resource['subject_name'])) {
        $context[] = $resource['subject_name'];
    }

    if (empty($context)) {
        return $resource['title'];
    }

    return $resource['title'] . ' | ' . implode(' ', $context) . ' Resource';
}

/** Unique, ~150-160 character meta description built from real resource fields — never the same generic sentence for every resource. */
function generate_resource_seo_description(array $resource): string
{
    if (!empty($resource['meta_description'])) {
        return $resource['meta_description'];
    }

    $type = !empty($resource['resource_type']) ? strtolower($resource['resource_type']) : 'resource';
    $subjectGrade = trim(($resource['grade_level'] ?? '') . ' ' . ($resource['subject_name'] ?? ''));
    $focus = $resource['topic'] ?? ($resource['category_name'] ?? '');

    $lead = 'Download this ' . $type;
    if ($focus !== '') {
        $lead .= ' on ' . $focus;
    }
    if ($subjectGrade !== '') {
        $lead .= ' for ' . $subjectGrade;
    }
    $lead .= ' — ready to use in your classroom.';

    $body = trim((string)($resource['description'] ?? ''));
    if ($body === '') {
        return seo_truncate_at_word($lead, 160);
    }

    return seo_truncate_at_word($lead . ' ' . $body, 160);
}

/** Truncates at the last whole word within $maxLength, appending an ellipsis only when actually truncated. */
function seo_truncate_at_word(string $text, int $maxLength): string
{
    $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text);

    if (mb_strlen($text) <= $maxLength) {
        return $text;
    }

    $truncated = mb_substr($text, 0, $maxLength);
    $lastSpace = mb_strrpos($truncated, ' ');

    if ($lastSpace !== false) {
        $truncated = mb_substr($truncated, 0, $lastSpace);
    }

    return rtrim($truncated, " .,;:-") . '...';
}

/** "[Title] | Teacher Hub" — mirrors generate_resource_seo_title()'s override-first pattern. */
function generate_guide_seo_title(array $guide): string
{
    if (!empty($guide['seo_title'])) {
        return $guide['seo_title'];
    }

    return $guide['title'] . ' | Teacher Hub';
}

function generate_guide_seo_description(array $guide): string
{
    if (!empty($guide['meta_description'])) {
        return $guide['meta_description'];
    }

    if (!empty($guide['summary'])) {
        return seo_truncate_at_word($guide['summary'], 160);
    }

    if (!empty($guide['intro'])) {
        return seo_truncate_at_word($guide['intro'], 160);
    }

    return seo_truncate_at_word($guide['title'] . ' — practical teaching guidance from ' . SITE_NAME . '.', 160);
}

/**
 * Dynamic title/description/H1/intro for resources.php's filtered views —
 * these filters (subject, grade, resource type, category) are TeachLuma's
 * de-facto category/landing pages, so each meaningful combination gets
 * its own unique metadata instead of one generic description site-wide.
 * $noindex is true for free-text search results and empty-result pages
 * (thin/duplicate content Google shouldn't index), false otherwise.
 */
function generate_resources_listing_seo(array $filters, ?array $subject, ?array $category, int $resultCount): array
{
    $labelParts = [];

    if (!empty($filters['grade'])) {
        $labelParts[] = $filters['grade'];
    }
    if ($subject) {
        $labelParts[] = $subject['name'];
    }
    if ($category) {
        $labelParts[] = $category['name'];
    }
    if (!empty($filters['resource_type'])) {
        $labelParts[] = $filters['resource_type'] . 's';
    }

    $isSearch = trim((string)($filters['search'] ?? '')) !== '';
    $label = implode(' ', $labelParts);

    if ($isSearch) {
        $query = trim((string)$filters['search']);
        return [
            'title'       => 'Search results for "' . $query . '"',
            'description' => 'Resources matching "' . $query . '" on ' . SITE_NAME . '.',
            'h1'          => 'Search Results for "' . $query . '"',
            'intro'       => null,
            'noindex'     => true,
        ];
    }

    if ($label === '') {
        return [
            'title'       => 'Resources',
            'description' => 'Browse the complete ' . SITE_NAME . ' resource library — English/ESL, Math and Science lesson plans, worksheets, PowerPoints, games and more.',
            'h1'          => 'Resources',
            'intro'       => null,
            'noindex'     => $resultCount === 0,
        ];
    }

    $title = $label . ' Resources';
    $intro = 'Browse ready-to-use ' . $label . ' resources for your classroom, including worksheets, lesson plans, PowerPoints and more.';
    $description = seo_truncate_at_word(
        'Download ' . $label . ' resources on ' . SITE_NAME . ' — ready-to-use lesson plans, worksheets, PowerPoints and classroom activities.',
        160
    );

    return [
        'title'       => $title,
        'description' => $description,
        'h1'          => $title,
        'intro'       => $intro,
        // A filter combination with zero current results isn't a useful
        // landing page yet — keep it working for visitors, just don't
        // send Google to an empty page (Step 12: only index pages with
        // meaningful resources).
        'noindex'     => $resultCount === 0,
    ];
}
