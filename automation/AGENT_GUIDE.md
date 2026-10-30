# TeachLuma generator: per-run guide for the agent

You are generating classroom resources for TeachLuma (teachluma.com). Teachers
will print these and use them with real children. Accuracy and age
appropriateness matter more than speed or quantity. Follow every step below
in order.

## Hard rules

- **Never publish anything.** The API can only create *unpublished drafts*.
  A human approves every resource in the dashboard. Do not look for a way
  around this.
- **Never upload a package that failed QC.** Report it with `report-failed`.
- **Stop immediately** if any command reports *Emergency stop*. Run nothing
  else that run.
- **Never print, log, commit or paste `TEACHLUMA_API_TOKEN`.**
- **Do not edit anything outside `automation/specs/work/` and `automation/out/`**
  during a run. That includes site PHP, the database and the generator code.
  If the generator has a bug, finish the run with `run-finish --status partial`
  and describe the bug in the message.
- Only use pictures the generator provides: emoji (Noto Color Emoji) and the
  built-in diagrams. Never download images from the web.

## Each run

Run all commands from `automation/` with `.venv/bin/python -m tlgen …`
(`bash bootstrap.sh` first in a fresh session).

1. `check`, then `status`: check the tools and the site. If emergency stop is on, stop. For a scheduled
   run, continue only if `due` is true.
2. `plan`: picks topics from curriculum gaps, avoids duplicates and assigns
   Free/Pro to match the dashboard setting. Admin regeneration requests come
   first. Read each one's `review_note` and fix exactly what it says.
3. `run-start --trigger scheduled|manual` (for a run the admin marked for
   retry: `run-start --trigger retry --retry-of <id>`).
4. For each planned key:
   1. `new-spec <key>` creates `specs/work/<key>.json`. For an admin
      regeneration request, it starts from the rejected version and adds the
      admin's note as `_admin_feedback`: change what the note asks for. To get
      new math questions, delete that section's `items` (keep its `generator`)
      and change its `seed` or ranges. If the note is about the generator
      itself (layout, thumbnails), say so in the `run-finish` message.
   2. Fill every `TODO` (see *Writing the spec* below). Use the examples in
      `specs/examples/` as the quality bar.
   3. `build specs/work/<key>.json` renders, runs QC and applies automatic
      corrections.
   4. If it ends with `qc_failed`, read the failed checks, fix the spec, and
      build again. Each changed-spec rebuild counts as one correction attempt
      (3 at most).
   5. Look at the rendered pages (`out/<key>/pkg/`): open the main PDF pages
      as images (`pdftoppm -r 50 -png`). Check that nothing is cut off,
      overlapping or confusing. Fix the spec if needed.
   6. `upload <key>`, or `report-failed <key>` if it could not pass.
   7. `heartbeat "<what you are doing>"` between resources (runs with no
      heartbeat for 2 hours are marked interrupted).
5. `run-finish` (it works out completed/partial/failed from the results).

## Writing the spec

Fill the spec yourself. Never leave filler text.

**Accuracy**
- Every fact must be correct for the grade level. When a simplification is
  needed for young children, it must still be true. For example, say "Most
  plants grow from seeds", not "All plants grow from seeds".
- Math: use `"type": "math"` sections with a `generator` (types are listed in
  `tlgen/mathgen.py`). Answers are computed and re-checked; never type math
  answers by hand. Hand-written questions that involve numbers (in
  `multiple_choice` or `short_answer`) must be checked twice by you.
- Multiple-choice distractors must be clearly wrong, but not silly. There
  must be exactly one correct answer.
- Pictures (emoji) must clearly show the word. If no emoji clearly fits, use
  a built-in drawing (`"pic:frogspawn"`, `"pic:tadpole"`, `"pic:tadpole_legs"`,
  `"pic:froglet"`, `"pic:frog"`; see `PICTURES` in `tlgen/illustrations.py`)
  or choose another word. Never use a picture that could teach something
  wrong (for example, a fish emoji for "tadpole").

**Language and level**
- Student instructions: one short sentence, simple words.
- Sentence length: about 10 words for Kindergarten to Grade 2, 15 for Grades
  3–5. QC checks reading level and the longest sentence.
- ESL: use the target language repeatedly, and give picture support for
  beginners.
- Nothing scary, violent, unhealthy, brand-related or culturally insensitive.
  Use names from many cultures.

**Structure**
- `lesson_plan.stages`: 4–6 stages. The minutes must add up to
  `suggested_duration`. Each stage needs concrete teacher actions and the
  questions to ask.
- Learning objectives must be practised by the activities, because QC checks
  the overlap.
- Free: lesson plan + worksheet with 5–6 varied activities (12+ items).
- Pro: Free plus quiz, homework, rubric (4 criteria × 4 levels) and 6–10
  vocabulary entries with emoji (used for the separate A5 flashcard file and the slides). Optional
  extra `slides`. Pro should be more comprehensive, not just longer.
- Section types: `match`, `fill_blank` (needs `____`, optional
  `"word_bank": true`), `multiple_choice`, `true_false`, `short_answer`,
  `label` (emoji + word), `draw_write` (`"box": false` and `"lines": 10` gives a
  lined writing space instead of a drawing box), `sequence` (steps in the correct order; they are
  shuffled when printed), `sort` (groups of words), `draw_write`, `math`.
- Pro teaching slides (`slides`, 4–6 required, each with `notes` of 40+
  characters saying what to say and ask). Teach the core idea; don't repeat
  the worksheet. Layouts (`type`):
  - `bullets` (default): `title`, `bullets`, optional `emoji`.
  - `steps`: `title`, `steps` (a process; 2–4 steps show as a flow).
  - `compare`: `title`, `left` and `right`, each with `title`, `bullets` and
    an optional `emoji`.
  - `example`: `title`, `problem`, `steps`, `answer`. The problem shows
    first; the solution is on the next slide.
  - `question`: `question`, optional `answer` and `emoji` (think, pair,
    share).
  - `diagram`: `title`, `emoji` (picture or `pic:`), `labels`.

  The deck adds the rest automatically: agenda, key-word slides, one slide
  per worksheet activity with a worked first item, quick checks, summary
  and exit ticket, all with speaker notes.
- Listening activities: add `"teacher_script": ["Number 1. Stand up, then
  clap your hands.", …]` to the section. The script prints in the answer key
  for the teacher to read aloud. Two-step picture options use an arrow:
  `"🧍➡️👏"`.
- Math generators include `add`, `sub`, `mult`, `div`, `compare`,
  `expanded_form`, `digit_value`, `from_place_values`, `base_ten_read`,
  `skip_count`, `odd_even`, `odd_even_objects`, `doubles`, `round`, clocks,
  fractions, perimeter/area and word problems (`tlgen/mathgen.py`). Also
  `pictograph` and `lineplot` (pass a `theme`; one graph per section),
  `order_ops`, `div_remainder`, `thermometer_read`, `frac_div`, and integers:
  `abs_value`, `opposite`, `order_ints`, `temp_change` (use negative `lo`).
- Use US spelling (meters, organize, color): the spell checker is American.
- Reading texts: add `"passage": ["paragraph 1", "paragraph 2"]` (and an optional
  `"passage_title"`) to any section. It prints in a box above the questions, in
  the Word file, and on its own slide before the activity.
- Never print misspelled words as distractors (boxs, goed): spelling QC fails
  them and `spelling_allow` is only for real words. Use real-word errors instead
  ("There are three box.", "She cans swim.").
- `spelling_allow` is only for real words the spell checker doesn't know,
  such as "CVC". It shows in the QC report.

**Metadata and social copy**
- Title 8–150 characters, specific (topic + grade-appropriate hook). It must
  not repeat an existing title.
- Description 40–500 characters; overview 80+ characters.
- Social copy is stored for the admin and never posted. Facebook post 40–2000
  characters, Pinterest title 10–100, Pinterest description 40–500, 3–12
  hashtags (`#Word` form), at least one visual suggestion.

## What QC checks

Mandatory (all must pass): `spec_valid`, `taxonomy`, `metadata`,
`answers_complete`, `math_verified`, `lesson_timing`, `files_open` (also
opened by LibreOffice), `no_blank_pages`, `page_budget` (Free 4–14, Pro 8–32
pages), `images`, `file_sizes`, `spelling`, `tier_components`,
`duplicate_check` (live site), `content_safety`, `reading_level`,
`social_copy`.

The 100-point score (accuracy 25, completeness 20, formatting 15, age
appropriateness 15, visual quality 10, metadata 15) is a set of automated
indicators. It is **not** proof of educational accuracy, so never describe it
that way.
