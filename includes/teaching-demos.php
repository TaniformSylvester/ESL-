<?php
/**
 * Homepage "Teaching Demo" section data — a plain configuration array
 * rather than a database table, since a handful of curated demo videos
 * doesn't warrant new schema (see TEACHLUMA notes on this section). Each
 * entry is added by hand as a real demo becomes available; nothing here
 * is auto-generated.
 *
 * Fields per demo:
 *   title        string   e.g. "Grade 1 ESL — Animals"
 *   subject      string   display label, e.g. "English / ESL"
 *   grade        string   display label, e.g. "Grade 1"
 *   topic        string   e.g. "Animals"
 *   description  string   teacher-facing summary of what the demo shows
 *   whats_included array<string>  short "what you'll see" bullet list
 *   video_type   ?string  'youtube' | 'local' | null (null = no video yet)
 *   video_id     ?string  YouTube video ID when video_type is 'youtube'
 *   video_url    ?string  path/URL to the file when video_type is 'local'
 *   poster_image ?string  URL to a poster/thumbnail image, or null to use
 *                         the default icon placeholder
 *   duration     ?string  e.g. "4 min" — only ever shown when a real video exists
 *   resource_link string  where "Explore Teaching Resources" points
 *
 * No entry here claims a video exists unless video_type is actually set.
 */

/**
 * All configured teaching demos, in display order. Only the first is
 * shown on the homepage today; the structure supports more being added
 * later without any homepage/template changes.
 */
function get_teaching_demos(): array
{
    return [
        [
            'title'          => 'Grade 1 ESL — Animals',
            'subject'        => 'English / ESL',
            'grade'          => 'Grade 1',
            'topic'          => 'Animals',
            'description'    => "See how a simple TeachLuma ESL lesson introduces animal vocabulary through pictures, repetition, speaking practice, and classroom activities.",
            'whats_included' => [
                'Simple English instruction',
                'Visual vocabulary presentation',
                'Teacher-led speaking practice',
                'Student participation',
                'Classroom-ready activities',
            ],
            'video_type'     => null,
            'video_id'       => null,
            'video_url'      => null,
            'poster_image'   => null,
            'duration'       => null,
            'resource_link'  => base_url('resources.php'),
        ],
        // Future demos (not yet recorded — add here once a real video exists):
        //   Grade 1 Mathematics — Addition
        //   Grade 1 Science — Parts of a Plant
        //   Grade 2 ESL — My Family
    ];
}

/** The single demo currently featured on the homepage, or null if none are configured. */
function get_featured_teaching_demo(): ?array
{
    $demos = get_teaching_demos();

    return $demos[0] ?? null;
}
