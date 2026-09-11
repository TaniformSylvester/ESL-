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
            'thumbnail' => asset_url('images/games/number-challenge-thumb.svg'),
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
            'thumbnail' => asset_url('images/games/vocabulary-match-thumb.svg'),
            'featured'  => true,
        ],
        [
            'slug'                   => 'phonics-sound-match',
            'title'                  => 'Phonics Sound Match',
            'subject'                => 'ESL',
            'grade'                  => 'Kindergarten–Grade 1',
            'topic'                  => 'Phonics',
            'difficulty'             => 'Easy',
            'short_description'      => 'Practice beginning, ending and middle sounds by matching a sound to a picture and word.',
            'what_students_practice' => [
                'Identifying beginning, middle and ending sounds',
                'Matching a sound to a picture and its word',
                'Phonemic awareness for early reading',
            ],
            'how_to_use' => [
                'Open the game on your laptop or tablet.',
                'Connect to a projector or classroom display, if you have one.',
                'Choose a level: Easy, Medium or Hard.',
                'Say the target sound aloud and let students think of the answer.',
                'Click the picture-word that matches, then move to the next round.',
            ],
            'thumbnail' => asset_url('images/games/phonics-sound-match-thumb.svg'),
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

// -----------------------------------------------------------------------
// PLAY TRACKING — real usage counts for the admin dashboard, recorded by
// api/game-track.php whenever a game's own JS fires a "started"/"completed"
// event. game_slug is validated against get_all_games() before anything is
// written, so a bad or made-up slug can never pollute the table.
// -----------------------------------------------------------------------

/** Records one real play event. Silently a no-op for an unknown slug/event — the caller (api/game-track.php) already validates, this is just a second line of defense. */
function record_game_play(string $slug, string $eventType): void
{
    if (!in_array($eventType, ['started', 'completed'], true) || !get_game_by_slug($slug)) {
        return;
    }

    $userId = is_logged_in() ? (int)$_SESSION['user_id'] : null;

    getDB()->prepare('INSERT INTO game_plays (game_slug, event_type, user_id) VALUES (?, ?, ?)')
        ->execute([$slug, $eventType, $userId]);
}

/** Total "started" plays across every game, all time — the admin dashboard's headline number. */
function get_total_game_plays(): int
{
    return (int)getDB()->query("SELECT COUNT(*) FROM game_plays WHERE event_type = 'started'")->fetchColumn();
}

/** "started" plays so far this calendar month. */
function get_game_plays_this_month(): int
{
    $stmt = getDB()->prepare(
        "SELECT COUNT(*) FROM game_plays WHERE event_type = 'started' AND DATE_FORMAT(played_at, '%Y-%m') = ?"
    );
    $stmt->execute([date('Y-m')]);

    return (int)$stmt->fetchColumn();
}

/**
 * Per-game play stats for the admin dashboard breakdown table — every
 * configured game is listed even with zero real plays (never omitted,
 * never fabricated), ordered by most-played first.
 */
function get_game_play_stats(): array
{
    $stmt = getDB()->prepare(
        "SELECT
            SUM(CASE WHEN event_type = 'started' THEN 1 ELSE 0 END) AS started,
            SUM(CASE WHEN event_type = 'completed' THEN 1 ELSE 0 END) AS completed
         FROM game_plays
         WHERE game_slug = ?"
    );

    $rows = [];
    foreach (get_all_games() as $game) {
        $stmt->execute([$game['slug']]);
        $counts = $stmt->fetch();

        $rows[] = [
            'slug'      => $game['slug'],
            'title'     => $game['title'],
            'subject'   => $game['subject'],
            'started'   => (int)($counts['started'] ?? 0),
            'completed' => (int)($counts['completed'] ?? 0),
        ];
    }

    usort($rows, static fn(array $a, array $b): int => $b['started'] <=> $a['started']);

    return $rows;
}
