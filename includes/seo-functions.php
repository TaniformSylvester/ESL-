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

/**
 * Pluralizes a RESOURCE_TYPES label for listing-page SEO copy and headings
 * (e.g. "Quiz" -> "Quizzes", "Classroom Activity" -> "Classroom Activities").
 * A plain "+ s" concatenation used to run here, producing "Flashcardss",
 * "Quizs" and "Classroom Activitys" wherever a type filter was applied.
 */
function pluralize_resource_type(string $type): string
{
    static $irregular = [
        'Quiz'     => 'Quizzes',
        'Homework' => 'Homework',
    ];
    if (isset($irregular[$type])) {
        return $irregular[$type];
    }
    if (substr($type, -1) === 's') {
        // Already plural/uncountable as a type name (e.g. "Flashcards").
        return $type;
    }
    if (preg_match('/[^aeiou]y$/i', $type)) {
        // Consonant + "y" -> "ies", covers every "<X> Activity" type.
        return substr($type, 0, -1) . 'ies';
    }

    return $type . 's';
}

/**
 * Builds "[Title] – Grade [X] [Subject] [Type]" for search results, kept
 * short enough that Google shows it whole (~60 characters before
 * includes/header.php appends " | SITE_NAME"). The context is added only
 * when it isn't already in the title ("Grade 7 ESL Reading Activity: ..."
 * stays as it is) and is dropped piece by piece when the title is long.
 */
function build_seo_title(string $title, ?string $grade, ?string $subject, ?string $type): string
{
    $title = trim($title);
    $grade = trim((string)$grade);
    $subject = trim((string)$subject);
    $type = trim((string)$type);
    $subjectWord = strcasecmp($subject, 'ESL') === 0 ? 'English' : $subject;

    if ($grade === '' || stripos($title, $grade) !== false) {
        return $title;
    }

    // [suffix, longest title it may produce]: the resource type ("Worksheet")
    // only while the whole title still fits; grade and subject a little longer,
    // since Google may cut the " | SITE_NAME" ending but keeps the keywords.
    $candidates = [];
    if ($subjectWord !== '' && $type !== '' && stripos($title, $type) === false) {
        $candidates[] = [$grade . ' ' . $subjectWord . ' ' . $type, 60];
    }
    if ($subjectWord !== '') {
        $candidates[] = [$grade . ' ' . $subjectWord, 66];
    }

    foreach ($candidates as [$suffix, $maxLength]) {
        if (mb_strlen($title . ' – ' . $suffix) <= $maxLength) {
            return $title . ' – ' . $suffix;
        }
    }

    // A long title still says which grade it is for; that's what teachers search.
    return $title . ' – ' . $grade;
}

/** Search-result description lead: "Grade 3 (ages 8–9) English (ESL/EFL) worksheet". */
function seo_context_phrase(?string $grade, ?string $subject, ?string $type): string
{
    $parts = array_filter([
        grade_label($grade),
        subject_label_with_uk(subject_search_label($subject)),
        $type !== null && $type !== '' ? strtolower($type) : '',
    ], static fn ($part) => $part !== '');

    return implode(' ', $parts);
}

/**
 * The resource's own description, which is unique to it, comes first so
 * it isn't cut off. A short "Grade 3 (ages 8–9) English (ESL/EFL)
 * worksheet:" lead is added only when the description doesn't already
 * name the grade, and "Free" only when it doesn't already say so.
 */
function build_seo_description(string $body, ?string $grade, ?string $subject, ?string $type, bool $isFree, string $fallback): string
{
    $body = trim(preg_replace('/\s+/', ' ', $body) ?? $body);
    $grade = trim((string)$grade);
    $context = seo_context_phrase($grade, $subject, $type);

    if ($body === '') {
        $body = $fallback;
    }

    $lead = '';
    if ($grade !== '' && stripos($body, $grade) === false && $context !== '') {
        $lead = $context . ': ';
    }
    if ($isFree && stripos($body, 'free') === false) {
        $lead = 'Free ' . ($lead !== '' ? $lead : '');
    }
    if ($lead !== '') {
        $lead = mb_strtoupper(mb_substr($lead, 0, 1)) . mb_substr($lead, 1);
    }
    $text = $lead . $body;

    // When the description named the grade itself, the ages (and, for
    // English, "ESL/EFL") weren't added in the lead; add them at the end
    // when there's room, since teachers abroad search by age and "EFL".
    $ages = ages_for_grade($grade);
    if ($lead === '' || stripos($lead, 'ages') === false) {
        $isEnglish = strcasecmp(trim((string)$subject), 'ESL') === 0 && stripos($text, 'EFL') === false;
        $tail = $ages
            ? ($isEnglish ? 'For ESL/EFL classes, ages ' : 'Ages ') . $ages[0] . '–' . $ages[1] . '.'
            : ($isEnglish ? 'For ESL/EFL classes.' : '');
        if ($tail !== '' && mb_strlen($text . ' ' . $tail) <= 160) {
            $text .= (preg_match('/[.!?]$/', $text) ? ' ' : '. ') . $tail;
        }
    }

    return seo_truncate_at_word($text, 160);
}

/** Short, keyword-led title — SITE_NAME is appended separately by includes/header.php's <title> tag, matching every other page. */
function generate_resource_seo_title(array $resource): string
{
    if (!empty($resource['seo_title'])) {
        return $resource['seo_title'];
    }

    return build_seo_title(
        (string)$resource['title'],
        $resource['grade_level'] ?? null,
        $resource['subject_name'] ?? null,
        $resource['resource_type'] ?? null
    );
}

/** Unique, ~150-160 character meta description built from real resource fields — never the same generic sentence for every resource. */
function generate_resource_seo_description(array $resource): string
{
    if (!empty($resource['meta_description'])) {
        return $resource['meta_description'];
    }

    $focus = $resource['topic'] ?? ($resource['category_name'] ?? '');
    $fallback = ($focus !== '' ? 'Topic: ' . $focus . '. ' : '') . 'Ready to print and teach.';

    return build_seo_description(
        (string)($resource['description'] ?? ''),
        $resource['grade_level'] ?? null,
        $resource['subject_name'] ?? null,
        $resource['resource_type'] ?? null,
        !empty($resource['is_free']),
        $fallback
    );
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

/** "[Title] | Grade [X] [Subject] Video" — mirrors generate_resource_seo_title()'s override-first pattern. */
function generate_video_seo_title(array $video): string
{
    if (!empty($video['seo_title'])) {
        return $video['seo_title'];
    }

    return build_seo_title((string)$video['title'], $video['grade_level'] ?? null, $video['subject_name'] ?? null, 'Video');
}

/** Unique, ~150-160 character meta description built from real video fields — mirrors generate_resource_seo_description(). */
function generate_video_seo_description(array $video): string
{
    if (!empty($video['meta_description'])) {
        return $video['meta_description'];
    }

    $focus = $video['topic'] ?? '';
    $fallback = 'A short classroom video' . ($focus !== '' ? ' on ' . $focus : '') . ' from ' . SITE_NAME . '.';

    return build_seo_description(
        (string)($video['description'] ?? ''),
        $video['grade_level'] ?? null,
        $video['subject_name'] ?? null,
        'video',
        false,
        $fallback
    );
}

/**
 * Dynamic title/description/H1 for videos.php's filtered views — mirrors
 * generate_resources_listing_seo(). $noindex is true for free-text search
 * results and empty-result pages, matching the same reasoning.
 */
function generate_videos_listing_seo(array $filters, ?array $subject, int $resultCount): array
{
    $labelParts = [];

    if (!empty($filters['grade'])) {
        $labelParts[] = $filters['grade'];
    }
    if ($subject) {
        $labelParts[] = subject_search_label($subject['name']);
    }

    $isSearch = trim((string)($filters['search'] ?? '')) !== '';
    $label = implode(' ', $labelParts);

    if ($isSearch) {
        $query = trim((string)$filters['search']);
        return [
            'title'       => 'Search results for "' . $query . '"',
            'description' => 'Videos matching "' . $query . '" on ' . SITE_NAME . '.',
            'h1'          => 'Search Results for "' . $query . '"',
            'noindex'     => true,
        ];
    }

    if ($label === '') {
        return [
            'title'       => 'Educational Videos',
            'description' => 'Browse ' . SITE_NAME . '\'s library of educational videos — English (ESL/EFL), Math and Science lessons for classrooms around the world.',
            'h1'          => 'Educational Videos',
            'noindex'     => $resultCount === 0,
        ];
    }

    $title = $label . ' Videos';

    return [
        'title'       => $title,
        'description' => seo_truncate_at_word('Watch ' . $label . ' educational videos on ' . SITE_NAME . '.', 160),
        'h1'          => $title,
        'noindex'     => $resultCount === 0,
    ];
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

    // Titles stay short ("Grade 3 English (ESL/EFL) Worksheets"); the meta
    // description adds the ages and British spelling ("Grade 3 (ages 8–9)
    // Math (Maths)"), which teachers outside the US also search with.
    $descriptionLabelParts = [];
    if (!empty($filters['grade'])) {
        $labelParts[] = $filters['grade'];
        $descriptionLabelParts[] = grade_label($filters['grade']);
    }
    if ($subject) {
        $labelParts[] = subject_search_label($subject['name']);
        $descriptionLabelParts[] = subject_label_with_uk(subject_search_label($subject['name']));
    }
    if ($category) {
        $labelParts[] = $category['name'];
        $descriptionLabelParts[] = $category['name'];
    }
    $typeLabel = !empty($filters['resource_type']) ? pluralize_resource_type($filters['resource_type']) : '';
    if ($typeLabel !== '') {
        $labelParts[] = $typeLabel;
        $descriptionLabelParts[] = $typeLabel;
    }

    $isSearch = trim((string)($filters['search'] ?? '')) !== '';
    $isFree = ($filters['access'] ?? '') === 'free';
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

    if ($label === '' && $isFree) {
        return [
            'title'       => 'Free Teaching Resources — Worksheets & Lesson Plans',
            'description' => 'Free printable English (ESL/EFL), Math and Science worksheets, lesson plans, PowerPoints and classroom activities for ages 5–16. Unlimited downloads with a free teacher account.',
            'h1'          => 'Free Teaching Resources',
            'intro'       => 'Every resource on this page is free to download with a free account, as many times as you like.',
            'noindex'     => $resultCount === 0,
        ];
    }

    if ($label === '') {
        return [
            'title'       => 'Teaching Resources: Worksheets & Lesson Plans',
            'description' => 'Browse the complete ' . SITE_NAME . ' resource library — English (ESL/EFL), Math and Science lesson plans, worksheets, PowerPoints, games and more for ages 5–16.',
            'h1'          => 'Teaching Resources',
            'intro'       => null,
            'noindex'     => $resultCount === 0,
        ];
    }

    // "Grade 2 ESL Worksheets" reads naturally; only add "Resources" when
    // no resource type is chosen ("Grade 2 ESL Resources"), never
    // "Worksheets Resources".
    $title = ($isFree ? 'Free ' : '') . ($typeLabel !== '' ? $label : $label . ' Resources');
    if ($label === $typeLabel) {
        // Type on its own ("Worksheets") is too vague to rank; say what it covers.
        $title .= ' for English, Math & Science';
    }
    $intro = 'Browse ready-to-use ' . ($isFree ? 'free ' : '') . $label . ($typeLabel !== '' ? '' : ' resources') . ' for your classroom.';
    $description = seo_truncate_at_word(
        'Download ' . ($isFree ? 'free ' : '') . implode(' ', $descriptionLabelParts) . ($typeLabel !== '' ? '' : ' resources') . ' on ' . SITE_NAME . ' — ready-to-use, printable and made for real classrooms.',
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
