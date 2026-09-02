<?php
/**
 * Subjects (ESL / Math / Science) are a small, fixed taxonomy seeded once
 * in database.sql — there's deliberately no admin CRUD for them, the same
 * way RESOURCE_TYPES is a fixed list rather than an editable table.
 */

function get_all_subjects(): array
{
    static $subjects = null;

    if ($subjects === null) {
        $subjects = getDB()->query('SELECT * FROM subjects ORDER BY sort_order, name')->fetchAll();
    }

    return $subjects;
}

function get_subject_by_id(int $id): ?array
{
    foreach (get_all_subjects() as $subject) {
        if ((int)$subject['id'] === $id) {
            return $subject;
        }
    }

    return null;
}

function get_subject_by_slug(string $slug): ?array
{
    foreach (get_all_subjects() as $subject) {
        if ($subject['slug'] === $slug) {
            return $subject;
        }
    }

    return null;
}

/**
 * Slices the site's full GRADE_LEVELS list down to the range a subject
 * actually applies to (e.g. Math is Grade 1-6, not Kindergarten or Grade
 * 7-9), so grade dropdowns only ever offer valid combinations.
 */
function get_subject_grade_levels(array $subject): array
{
    $min = array_search($subject['min_grade'], GRADE_LEVELS, true);
    $max = array_search($subject['max_grade'], GRADE_LEVELS, true);

    if ($min === false || $max === false) {
        return GRADE_LEVELS;
    }

    return array_slice(GRADE_LEVELS, $min, $max - $min + 1);
}
