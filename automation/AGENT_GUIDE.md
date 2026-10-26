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

1. `status`: check the site. If emergency stop is on, stop. For a scheduled
   run, continue only if `due` is true.
2. `plan`: picks topics from curriculum gaps, avoids duplicates and assigns
   Free/Pro to match the dashboard setting. Admin regeneration requests come
   first. Read each one's `review_note` and fix exactly what it says.
3. `run-start --trigger scheduled|manual` (for a run the admin marked for
   retry: `run-start --trigger retry --retry-of <id>`).
4. For each planned key:
   1. `new-spec <key>` creates `specs/work/<key>.json`.
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
- Pictures (emoji) must clearly show the word. If no emoji clearly fits a
  word, choose another word.

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
  vocabulary entries with emoji (used for flashcards and slides). Optional
  extra `slides`. Pro should be more comprehensive, not just longer.
- Section types: `match`, `fill_blank` (needs `____`, optional
  `"word_bank": true`), `multiple_choice`, `true_false`, `short_answer`,
  `label` (emoji + word), `sequence` (steps in the correct order; they are
  shuffled when printed), `sort` (groups of words), `draw_write`, `math`.
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
