<?php
/**
 * TeachLuma Games — metadata for the interactive HTML5 classroom games
 * library. A plain config array, not a database table: each game is a
 * genuinely bespoke, hand-built HTML5 bundle (assets/games/<slug>/index.html
 * — the same convention already used by config.php's PRACTICE_ACTIVITIES),
 * not admin-generated content, so there is nothing here an admin form would
 * meaningfully CRUD. Adding a new game means adding one entry below plus
 * its own self-contained bundle — nothing else needs to change.
 *
 * Fields per game:
 *   slug              string   URL slug, also the assets/games/<slug>/ folder name
 *   title             string
 *   subject           string   display label, e.g. "Math"
 *   grade             string   display label, e.g. "Grade 1–2"
 *   topic             string
 *   difficulty        string   "Easy" | "Medium" | "Advanced"
 *   short_description string   one-sentence summary for cards
 *   what_students_practice array<string>  bullet list for the game landing page
 *   how_to_use        array<string>  short numbered classroom-use steps
 *   thumbnail         ?string  URL to a thumbnail image, or null to use the default icon tile
 *   featured          bool     whether this game is eligible for homepage/hub "Featured" placement
 */

/** Every configured game, in display order. */
function get_all_games(): array
{
    return [
        [
            'slug'                   => 'number-challenge',
            'title'                  => 'Number Challenge',
            'subject'                => 'Math',
            'grade'                  => 'Grade 1–2',
            'topic'                  => 'Addition & Subtraction',
            'difficulty'             => 'Easy',
            'short_description'      => 'Practice addition, subtraction and mental math in a fast-paced whole-class game.',
            'what_students_practice' => [
                'Addition within 20',
                'Subtraction within 20',
                'Quick mental math recall',
            ],
            'how_to_use' => [
                'Open the game on your laptop or tablet.',
                'Connect to a projector or classroom display, if you have one.',
                'Choose a mode, question count and timer on the start screen.',
                'Show each question and let students discuss the answer.',
                'Select the answer together, then move to the next question.',
            ],
            'thumbnail' => null,
            'featured'  => true,
        ],
        [
            'slug'                   => 'vocabulary-match',
            'title'                  => 'Vocabulary Match',
            'subject'                => 'ESL',
            'grade'                  => 'Kindergarten–Grade 2',
            'topic'                  => 'Vocabulary',
            'difficulty'             => 'Easy',
            'short_description'      => 'A picture-and-word memory matching game covering animals, food and colors vocabulary.',
            'what_students_practice' => [
                'Matching pictures to their English word',
                'Core vocabulary: animals, food and colors',
                'Visual memory and turn-taking',
            ],
            'how_to_use' => [
                'Open the game on your laptop or tablet.',
                'Connect to a projector or classroom display, if you have one.',
                'Choose a category and number of pairs on the start screen.',
                'Call on students to pick two cards and say the word aloud.',
                'Keep going until every picture is matched with its word.',
            ],
            'thumbnail' => null,
            'featured'  => true,
        ],
        // Future games (see get_related_games() and the Number Challenge
        // architecture for how a new mode/game slots in without rebuilding
        // the hub): Number Recognition, Missing Number, Number Bonds, etc.
    ];
}

/** Up to $limit featured games, for the homepage and hub "Featured" rail. */
function get_featured_games(int $limit = 3): array
{
    $featured = array_values(array_filter(get_all_games(), static fn(array $g): bool => !empty($g['featured'])));

    return array_slice($featured, 0, max(1, $limit));
}

function get_game_by_slug(string $slug): ?array
{
    foreach (get_all_games() as $game) {
        if ($game['slug'] === $slug) {
            return $game;
        }
    }

    return null;
}

/** Other games for a "More Games" section — same subject first, then anything else, excluding the current game. */
function get_related_games(array $game, int $limit = 3): array
{
    $others = array_values(array_filter(get_all_games(), static fn(array $g): bool => $g['slug'] !== $game['slug']));

    usort($others, static function (array $a, array $b) use ($game): int {
        $aMatch = $a['subject'] === $game['subject'] ? 0 : 1;
        $bMatch = $b['subject'] === $game['subject'] ? 0 : 1;

        return $aMatch <=> $bMatch;
    });

    return array_slice($others, 0, max(1, $limit));
}

/** The embeddable game bundle's URL — every game lives at assets/games/<slug>/index.html. */
function game_embed_url(array $game): string
{
    return asset_url('games/' . rawurlencode($game['slug']) . '/index.html');
}
