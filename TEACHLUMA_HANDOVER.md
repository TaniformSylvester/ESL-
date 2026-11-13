# TeachLuma — Project Handover Document

This document is for a new Claude instance picking up development of TeachLuma. Read this fully before making any changes. It reflects the actual state of the codebase as of the end of **TeachLuma 2.0 Phase 3**.

---

## 1. Project Overview

TeachLuma (formerly "ESL Teacher Hub") is a PHP/MySQL teacher-resource subscription platform, live at **teachluma.com**, hosted on FastComet shared hosting.

**What it is:** a general teaching-resource platform — not a Thai curriculum site (that framing was explicitly corrected earlier in the project). The audience is teachers broadly: ESL/EFL teachers, primary teachers, international-school teachers, tutors, homeschool educators, in Thailand and internationally.

**What it offers:**
- Downloadable teaching resources (worksheets, lesson plans, PowerPoints, flashcards, etc.) across English/ESL, Mathematics, and Science.
- A free tier (limited monthly downloads) and a paid "Teacher Pro" membership (monthly/annual) for unlimited downloads.
- **Teacher Hub** — a small library of practical, hand-written "how to teach X" guides, distinct from the downloadable resources.
- A teacher review/rating system on resources.

**Current state of the resource library: intentionally empty.** All resources that existed before this rebuild have been archived (not deleted) as part of a deliberate decision to rebuild the library with higher-quality content. See Section 3 (Phase History) and Section 11 (Next Planned Work).

---

## 2. Current Architecture

**Technology/framework:** Plain procedural PHP 8+ (tested against PHP 8.4), no framework. PDO for all database access, prepared statements throughout. Bootstrap 5 (via CDN) for styling, no JS framework — vanilla JS only where needed (favorites, reviews, admin form filtering).

**Database:** MySQL/MariaDB, InnoDB engine, `utf8mb4` charset throughout. Local dev connects to a database named `esl_teacher_hub` via `config/database.php` (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`). The live site's database name/credentials are separately configured on the production `config/database.php` (not this repo's version — that file holds real secrets and must never be blindly overwritten by a deployment).

**Important directories:**
- `/admin/` — the entire admin panel (resources, guides, categories, subjects, reviews, users, payments, downloads, dashboard).
- `/member/` — logged-in member area (dashboard is `dashboard.php` at root; downloads, favorites, reviews, subscription, profile, and the actual download-serving endpoints live here).
- `/includes/` — all shared PHP logic: `*-functions.php` files (one per domain: `resource-functions.php`, `guide-functions.php`, `review-functions.php`, `download-functions.php`, `seo-functions.php`, `subject-functions.php`, `admin-functions.php`, `membership.php`, `auth.php`, `upload-functions.php`, `functions.php`, `db.php`, `security.php`), plus shared HTML partials (`header.php`, `footer.php`, `admin-header.php`, `admin-footer.php`, `resource-card.php`, `admin-resource-form.php`, `admin-guide-form.php`).
- `/uploads/` — **not tracked in git** (see `.gitignore`); this is where uploaded files actually live at runtime:
  - `uploads/protected/` — the actual resource files. Blocked from all direct web access via `.htaccess` (`Require all denied`) — the only way to reach a file is through `member/download.php` or `member/download-extra.php`, which check access rights first, then stream the file with `readfile()`.
  - `uploads/thumbnails/`, `uploads/previews/` — publicly servable images.
  - `uploads/payment-proofs/` — payment screenshot uploads (unrelated to resources).
  - Files are stored under **random internal names** (`bin2hex(random_bytes(16)) . '.' . $ext`) regardless of the original filename — this is deliberate (see Section 8) and should not be changed.
- `/config/` — `config.php` (site name/branding, constants, feature flags), `database.php` (DB credentials), `stripe.php`, `google.php`, `adsense.php`. **These hold live secrets and are never wholesale-overwritten by a deployment** — only specific documented lines are hand-edited on the live server.
- `/install/` — first-run setup scripts (not relevant to ongoing development).
- `database.sql` — the full schema reference (used for fresh installs), kept in sync with every migration but **not itself run against the live site** — migrations are separate, small, additive `.sql` files applied by hand via phpMyAdmin.

**Important files:**
- `resource.php` — the public resource detail page (the most complex page in the app).
- `resources.php` — the public filterable resource listing.
- `index.php` — homepage.
- `teacher-hub.php` / `teacher-hub-guide.php` — public Teacher Hub pages.
- `about.php`, `pricing.php`, `sitemap.php`, `robots.txt`.
- `includes/resource-functions.php` — the largest and most important file; all resource CRUD, queries, and the archive mechanism live here.
- `includes/guide-functions.php` — all Teacher Hub CRUD and guide↔resource linking.

**Authentication:** session-based, `includes/auth.php`. Single active session per account is enforced (a login invalidates any other active session for that user). Standard login/register/password-reset flow. **Not touched during Phases 1–3** — still fully functional, do not modify unnecessarily.

**Membership system:** `includes/membership.php`. Three tiers: Free, Teacher Pro Monthly (฿199), Teacher Pro Annual (฿999). `isMemberActive()` checks active-membership status. Free tier gets `FREE_DOWNLOAD_MONTHLY_LIMIT` (currently 5) downloads/month, tracked via `get_free_download_usage()` / `try_consume_free_download()`. **Not touched during Phases 1–3.**

**Payment system:** manual payment-proof upload + admin approval flow (bank transfer/PromptPay style, not automated card processing) plus Stripe config present (`config/stripe.php`, `/stripe/` directory) for card payments. Admin approves payments via `admin/payments.php`, which activates/extends the membership. **Not touched during Phases 1–3.**

**Resource system:** see Section 5 in full — this is what Phases 1–3 rebuilt/extended.

**Teacher Hub:** a small CMS for hand-written teaching guides, built in an earlier phase (before the "TeachLuma 2.0" rebuild began). See Section 6.

**Review system:** star ratings (1–5) + written text, download-verified eligibility (a user can only review a resource they've actually downloaded), admin moderation queue (`pending`/`approved`/`rejected`), helpful-vote counter. `includes/review-functions.php`. **Not touched during Phases 1–3** except one new read-only function (`get_featured_site_reviews`) added in an earlier phase for the homepage, and one `status = 'active'` join-condition added in Phase 1 so a homepage review never links to an archived resource.

**Download system:** `includes/download-functions.php` + `member/download.php` (main file) + `member/download-extra.php` (new in Phase 2, for optional additional files). Every download is recorded in the `downloads` table (user_id nullable, resource_id, timestamp) — this history is permanent and is never deleted when a resource is archived.

---

## 3. Phase History

**Context:** before "TeachLuma 2.0," an earlier initiative (not phase-numbered the same way) built the Teacher Hub from scratch, corrected the site's positioning away from being Thailand-first, rewrote the homepage/About page, and did a comprehensive SEO pass. That work is **foundational** — it is not part of Phases 1–3 below, but Phases 1–3 build on top of it and must not undo it.

### Phase 1 — Safely Archive the Existing Resource Library
Commit `e005b5d`. Goal: prepare for a full library rebuild without losing anything.
- Took a full `mysqldump` backup of the local dev database and verified it (restored into an isolated temp DB, compared row counts across all tables).
- Exported a full CSV inventory of every existing resource (id, title, slug, subject, grade, topic, type, access, description, file info, download/review counts, dates, URL).
- Documented file storage locations, naming convention, and confirmed there is no external file storage anywhere in the codebase.
- Added an **archive mechanism** to the `resources` table (see Section 4) instead of deleting anything.
- Updated every public-facing query (listings, search, featured, free, popular, related, subject/type counts, sitemap, Teacher Hub cross-links, homepage review showcase) to exclude archived resources, while admin queries still see everything by default.
- Built archived-URL handling in `resource.php`: an archived resource's old URL 301-redirects to an admin-set replacement if one exists, otherwise 302-redirects to that resource's subject/category listing page — never a blind 404, never a blind homepage redirect.
- Added an admin Archive/Restore UI (`admin/resource-archive-toggle.php` + buttons/filter in `admin/resources.php`).
- **Archived all 17 resources that existed at the time** (mostly placeholder/test data — several literally titled "Sample Resource N").
- Full regression pass confirmed auth, registration, memberships, payments, reviews, downloads-history, Teacher Hub, and all admin pages still work correctly with the library empty.

### Phase 2 — Build the New Resource Workflow
Commit `6845cb9`. Goal: build (not populate) the complete admin workflow and resource-page infrastructure for a new, higher-quality library.
- Added `overview` and `whats_included` fields, plus a `qc_confirmed_at` quality-checklist gate (see Section 4).
- Extended the `resource_type` ENUM with new types: Complete Lesson Pack, Speaking Activity, Reading Activity, Writing Activity, Quiz, Homework, Classroom Poster, Activity Pack (old types kept for backward compatibility with archived data).
- New `resource_files` table: optional additional files on a resource (e.g. a standalone answer key), separate from the single main download, with its own access-gated download endpoint (`member/download-extra.php`) that does not double-charge a free user's monthly quota.
- New `resource_related_resources` table: admins can manually pick related resources; `resource.php` prefers the manual list and only falls back to the pre-existing automatic relevance query when nothing has been manually picked.
- Resources can now link to Teacher Hub guides **from the resource's own admin page** (`set_resource_related_guides()`), not just from the guide's admin page as before — both write to the same `guide_related_resources` table.
- Reconciled 6 new topic categories against the target taxonomy (Classroom English, Number Sense, Life Science, Physical Science, and separate Math/Science "Assessments" categories) — everything else in the target taxonomy already had a matching existing category and was left alone.
- Rebuilt the admin resource form with all of the above, plus an Additional Files uploader (3 fixed slots) and the QC-confirmation checkbox.
- Updated `resource.php` with Overview, What's Included, and Additional Files sections. Relabeled the pre-existing metadata block (subject/topic/category/file type/size/downloads) from "What's Included" to **"Resource Details"** to avoid colliding with the new genuine What's Included content field.
- **Verified with 3 real test resources** pushed through the entire workflow end-to-end (create → upload main file + preview + additional file → teaching info → guide + related-resource linking → publish with QC gate → public view → download main file → download additional file → submit review → admin edit → archive → restore), then **fully deleted** afterward, including their files, relationship rows, test review, and test member account. One real bug was found and fixed in the process (a missing `require` in `download-extra.php`).

### Phase 3 — Verify Readiness for Production Library
No commit — **read-only verification, zero code or database changes**. Confirmed via fresh checks (not just re-stating Phase 1/2 claims):
- Schema fully intact: all Phase 1 + Phase 2 columns/tables present, all 17 resources still archived (not restored, not deleted), all 10 Teacher Hub guides untouched.
- Admin workflow pages (`resource-add.php`, `resource-edit.php`, and all other admin pages) load with zero errors, including with an empty library (empty Related Resources picker renders correctly with no error).
- File storage protection (`.htaccess` deny-all on `uploads/protected/`) confirmed still in place.
- SEO infrastructure (title/description generators, canonical URL logic, sitemap query, schema functions) confirmed present and unmodified.
- Concluded that reorganizing file storage into human-readable `English/Mathematics/Science/` subfolders (as one draft plan suggested) is **not recommended** and was not done — see Section 8 for why.
- Flagged an open item: **it has not been confirmed that the Phase 1 and Phase 2 migrations have actually been run against the live teachluma.com database.** This must be confirmed before any production resource work begins.

---

## 4. Database Changes

**Migrations created** (both delivered to the user as standalone `.sql` files, applied to local dev and verified; live-site application status unconfirmed — see Section 12):
1. `teachluma_2_phase1_migration.sql` — Phase 1's archive mechanism.
2. `phase2_migration_final.sql` — Phase 2's new-resource-workflow schema.

**Tables changed:**
- `resources` — see field list below.
- `categories` — 6 new rows inserted (not a schema change): `Classroom English` (ESL), `Number Sense` and `Assessments` (Math, slug `math-assessments`), `Life Science`, `Physical Science`, and `Assessments` (Science, slug `science-assessments`).

**Tables added:**
- `resource_files` (id, resource_id FK CASCADE → resources, file_path, file_name, file_size, file_type, label nullable, sort_order, created_at).
- `resource_related_resources` (id, resource_id FK CASCADE, related_resource_id FK CASCADE, sort_order, UNIQUE(resource_id, related_resource_id)).

**Important new fields on `resources`** (all additive, nothing existing altered/dropped):
| Field | Type | Purpose |
|---|---|---|
| `status` | ENUM('active','archived') DEFAULT 'active' | The archive mechanism (Phase 1). |
| `redirect_resource_id` | INT UNSIGNED NULL, self-referencing FK ON DELETE SET NULL | Optional admin-set replacement target for an archived resource's old URL. |
| `archived_at` | DATETIME NULL | When a resource was archived. |
| `overview` | TEXT NULL | Fuller explanation than `description`'s short blurb (Phase 2). |
| `whats_included` | TEXT NULL | Accurate list of what the download actually contains — never claim contents that aren't real. |
| `qc_confirmed_at` | DATETIME NULL | The QC mechanism (Phase 2) — see below. |

Plus the 9 teaching-detail fields added in an earlier (pre-"TeachLuma 2.0") phase, still in active use: `learning_objectives`, `recommended_level`, `suggested_duration`, `skills_practiced`, `how_to_use`, `activity_ideas`, `teacher_tips`, `differentiation_notes`, `assessment_notes` — all optional, all rendered only when non-empty, all admin-fillable via `includes/admin-resource-form.php`.

**Current resource status/archive mechanism:** a resource has two independent status concepts:
1. `is_published` (0/1) — Draft vs. Published. Pre-existing, unchanged in meaning.
2. `status` ('active'/'archived') — whether the resource is retired from public view. New in Phase 1. Archiving/restoring preserves the row, its reviews, and its download history untouched; only `status`/`archived_at`/`redirect_resource_id` change.

A resource is publicly visible only when **both** `is_published = 1` AND `status = 'active'`.

**QC mechanism:** `qc_confirmed_at` is set only when an admin explicitly ticks a confirmation checkbox on the resource form while setting the resource to Published (`validate_resource_input()` in `includes/resource-functions.php` rejects `is_published = 1` server-side without it). It is cleared to `NULL` again whenever the publish state changes in either direction, so re-publishing after an unpublish always asks again. This was chosen over a full Draft/Review/Published/Archived state machine deliberately — the brief that requested it explicitly allowed "the simplest reliable implementation" if a full 4-state machine would unnecessarily complicate the many existing places that check `is_published = 1`.

---

## 5. Resource System — Complete Current Workflow

**Create → Upload → Teaching information → Preview → Related resources → Teacher Hub links → SEO → QC → Publish → Download**

1. **Create**: admin fills in Title, Subject, Grade/Level, Topic, Resource Type (18 types available), short Description, Free/Premium (`is_free`) at `admin/resource-add.php`.
2. **Upload**: one required main file (`resource_file`, validated by extension **and** actual content-sniffed MIME type via `finfo`, stored under a random filename in `uploads/protected/`), one optional thumbnail, one optional preview image, up to 3 optional "Additional Files" each with an optional label (e.g. "Answer Key").
3. **Teaching information**: Overview, Learning Objectives (one per line), Skills Practiced (comma list), Recommended Level, Suggested Duration, How to Use, Classroom Activity Ideas (one per line), Teacher Tips, Differentiation, Assessment, What's Included (one per line) — all optional, all rendered on the public page only when filled in, laid out in a different order/with different labels **per resource type** via `get_teaching_detail_layout()` (e.g. a Worksheet leads with "Student Task"; a Lesson Plan leads with "Lesson Procedure").
4. **Preview**: the preview image (if uploaded) displays large at the top of the resource page; falls back to a thumbnail, then to a generic type icon.
5. **Related resources**: admin manually multi-selects from all other published+active resources. If none are picked, `resource.php` automatically falls back to a relevance-scored query (same subject, +weight for same category/grade/type).
6. **Teacher Hub links**: admin multi-selects from all published guides, from either the resource's own edit page or the guide's edit page — both write to the same `guide_related_resources` join table.
7. **SEO**: optional SEO Title / Meta Description override fields; if left blank, `generate_resource_seo_title()`/`generate_resource_seo_description()` auto-generate from title/subject/grade. Canonical URL is automatic (not admin-settable).
8. **QC**: before Published can be selected and saved, admin must tick the on-page quality-checklist confirmation checkbox (title/subject/grade/type correct, file opens, description/teaching info accurate, What's Included matches reality, no spelling errors, related resources and guide links genuinely relevant).
9. **Publish**: sets `is_published = 1`; the resource becomes visible on the public listing, search, featured/free sections, sitemap, and its own URL.
10. **Download**: main file via `member/download.php?id=`, additional files via `member/download-extra.php?id=` (file's own id, not the resource id) — both require login, both check `can_download_resource()` (free-tier quota or active membership), only the main file consumes a free-tier download credit.

Editing an existing resource **never regenerates its slug** — the slug is set once at creation and is permanent, specifically so a published/indexed/bookmarked URL is never silently broken by a later title edit.

---

## 6. Teacher Hub

**Current guides:** exactly 10, all published, unchanged since before "TeachLuma 2.0" began:
1. How to Teach Vocabulary to Young ESL Learners (esl)
2. How to Teach Phonics to Young Learners (esl)
3. 10 Easy ESL Classroom Games That Need No Prep (esl)
4. How to Teach Addition to Young Learners (math)
5. How to Teach Place Value (math)
6. How to Teach Basic Fractions (math)
7. How to Teach Animals to Young Learners (science)
8. Simple Science Experiments for Primary Students (science)
9. Classroom Management Tips for New Teachers (classroom)
10. Warm-Up Activities to Start Any Lesson (classroom)

Each has genuine, hand-written content (not AI-templated filler): summary, intro, practical advice, classroom examples, activities, common difficulties, differentiation, assessment. Categories are `esl`/`math`/`science`/`classroom` (the `GUIDE_CATEGORIES` constant in `includes/guide-functions.php`).

**Guide/resource relationship:** many-to-many via `guide_related_resources` (guide_id, resource_id, sort_order). Currently **empty** (0 rows) — no guide is linked to any resource right now, because the resources that existed when the guides were written are now archived, and no new resources exist yet to link. This is expected, not a bug — links must only be created where a genuine connection exists (per explicit "never invent connections" instructions carried through every phase of this project).

**Admin functionality:** full CRUD at `admin/guides.php` / `admin/guide-add.php` / `admin/guide-edit.php` / `admin/guide-delete.php`, using `includes/admin-guide-form.php`. Guide content fields map to `GUIDE_SECTIONS`. The guide editor's resource-picker only offers published+active resources.

---

## 7. SEO

- **Title generation:** `generate_resource_seo_title()` / `generate_guide_seo_title()` in `includes/seo-functions.php` — override-first (uses the admin's `seo_title` field if set), otherwise auto-generated from title + subject + grade.
- **Descriptions:** `generate_resource_seo_description()` / `generate_guide_seo_description()` — same override-first pattern, truncated to a safe length via `seo_truncate_at_word()`.
- **Canonical URLs:** generated automatically in `includes/header.php` from `SITE_URL` + the current request path, with tracking parameters stripped (`strip_tracking_params()`) so `?utm_source=...` etc. never creates a duplicate canonical.
- **Sitemap:** `sitemap.php` includes only `is_published = 1 AND status = 'active'` resources and `is_published = 1` guides, plus static pages. Currently emits 0 resource URLs (empty library) and the Teacher Hub index + 10 guide URLs.
- **Schema.org structured data:** `LearningResource` schema on `resource.php` (includes `teaches` from learning objectives and `timeRequired` from suggested duration when present, `aggregateRating`/`review` when reviews exist), `Article` schema on guide pages, `Organization`/`WebSite` schema site-wide, `BreadcrumbList` on both resource and guide pages.
- **Thin-content protection:** `resources.php`'s filtered listing views (`generate_resources_listing_seo()`) are noindexed automatically for free-text search results and for any filter combination that currently returns zero results — this was built in an earlier phase and is unmodified.

---

## 8. Access Control

- **Free resources:** `is_free = 1`. Downloadable by any logged-in user, subject to the monthly free-download quota.
- **Premium resources:** `is_free = 0`. Downloadable only by an active Teacher Pro member (or an admin). Everyone else sees an upgrade prompt.
- **Free download limits:** `FREE_DOWNLOAD_MONTHLY_LIMIT` (currently 5/month), tracked per-user, enforced server-side in `member/download.php` via `try_consume_free_download()` at the moment of an actual successful download (a missing/broken file never costs a quota credit).
- **Additional file access:** governed by the exact same `can_download_resource()` check as the parent resource's main file — a premium resource's additional files are premium too. Downloading an additional file does **not** consume a second free-download credit; the quota is charged once, at the resource level, via the main file.
- **Protected storage:** `uploads/protected/` has a `.htaccess` denying all direct web access (`Require all denied`) — this is the actual security boundary, not the random filename. The random filename (`bin2hex(random_bytes(16))`) exists only to avoid filename collisions and to avoid ever trusting a user-supplied filename for a filesystem path; it is not itself a security control and should not be treated as one. **Do not reorganize this into human-readable subfolders (e.g. `English/Grade-1/...`)** — it provides no benefit (nothing ever browses the raw directory) and would require touching tested upload/download code for no functional gain. The human-readable name a teacher sees when downloading comes from the separate `file_name` column, which already preserves whatever filename was originally uploaded — so following a clean naming convention when *preparing* source files before upload achieves the naming goal without any code change.

---

## 9. Important Safety Rules

These systems must **not** be broken or unnecessarily modified — they were explicitly out of scope for Phases 1–3 and were verified untouched at the end of each phase:
- Authentication / login / registration / session handling (`includes/auth.php`).
- Membership tiers and activation logic (`includes/membership.php`).
- Payment processing and admin payment approval (`admin/payments.php`, `config/stripe.php`, `/stripe/`).
- Free-download quota logic (`includes/download-functions.php`'s quota functions — the download *path* was extended in Phase 2 for additional files, but the quota-consumption logic itself was not changed).
- The review system's core logic and moderation workflow (`includes/review-functions.php`) — only one new read-function and one join-condition were added across all of Phases 1–3.
- The 10 existing Teacher Hub guides' content.
- Site branding, navigation structure, Privacy Policy, Terms, Contact page.

---

## 10. Current Status

**PHASE 3 COMPLETE.**

The system (schema, admin workflow, public resource-page template, access control, SEO, Teacher Hub linking) is built and verified ready to receive a new resource library. **The library itself is currently empty** — all 17 pre-rebuild resources are archived (recoverable, not deleted), and zero new resources have been created.

**Phase 4 is the production resource-library creation phase** — designing and actually creating the real, high-quality resources that will populate the site. That work has **not started**.

---

## 11. Next Planned Work

Phase 4 will design and create an initial production library of approximately:
- **30 English/ESL resources**
- **15 Mathematics resources**
- **15 Science resources**
- **Total: ~60 resources**

The preferred format is **Complete Teaching Resource Packs** (lesson plan + PowerPoint + worksheet + activity + assessment + homework + answer key, as appropriate — never force every component into every pack; a phonics lesson, a math lesson, and a science lesson each need a different mix). Do **not** create the resource list yet, and do **not** create any resources yet — the exact resource list will be provided separately before this work begins.

---

## 12. Known Limitations

- **The actual production resource files do not exist yet.** No lesson plans, worksheets, PowerPoints, flashcards, or any other real teaching content has been created. Everything built in Phases 1–3 is infrastructure/workflow, tested only with throwaway placeholder test data that was fully deleted afterward.
- **It has not been confirmed that the Phase 1 and Phase 2 database migrations have been successfully run against the live teachluma.com database.** All verification in Phases 1–3 was performed against a local development database. Confirm both migrations are live before starting Phase 4 or any other work that assumes the new schema exists in production.
- This development session has never had direct access to the live teachluma.com server or its production database — all work happens in a git checkout plus a local dev database, and is delivered to the user as migration `.sql` files and deployment `.zip` files for them to apply themselves.

---

## 13. Development Rules

- **Do not rebuild the site.** Extend the existing plain-PHP/PDO architecture; do not introduce a framework or rearchitect working systems.
- **Do not restore the archived resources** without being explicitly asked to.
- **Do not permanently delete the archived resources.** They exist so the old library can be recovered if ever needed.
- **Do not modify authentication unnecessarily.**
- **Do not modify payments unnecessarily.**
- **Do not modify memberships unnecessarily.**
- **Do not fabricate resource content.** Every teaching-detail field (objectives, how-to-use, activities, What's Included, etc.) must describe something genuinely true of the actual file. If the actual content isn't known, leave the field blank — never invent it.
- **Do not create fake reviews.** The review system only ever shows real, user-submitted, admin-approved reviews.
- **Do not create fake educational information** — no invented curriculum alignment, no invented statistics, no invented qualifications or credentials.
- **Do not mass-generate thin SEO content.** No hundreds of near-duplicate pages, no keyword-stuffed filler, no pages that exist only to increase indexed-URL count.
- **Use the existing architecture.** Reuse existing fields, tables, and functions wherever they already cover the need (e.g. the teaching-detail fields, the archive mechanism, the QC gate) instead of inventing parallel systems.
- **Test before making large changes** — this project's established pattern is: implement → apply migration to a local dev DB → run a real local PHP server → exercise the actual HTTP endpoints (registration, login, the admin workflow, downloads, etc.) with real requests → verify with fresh queries, not assumptions → only then commit/push/package for deployment.

---

## 14. Git Status

- **Branch:** `claude/esl-teacher-hub-platform-112zs4`
- **Remote:** `https://github.com/TaniformSylvester/ESL-`
- **Recent commits** (newest first):
  - `6845cb9` — Build new-resource-workflow foundation for TeachLuma 2.0 (Phase 2)
  - `e005b5d` — Add resource archive mechanism for TeachLuma 2.0 library rebuild (Phase 1)
  - `68f0f86` — Add Teacher Hub content platform and fix general-teaching-platform positioning
  - `19b1877` — Comprehensive SEO upgrade for scalable resource/category indexing
  - `ce28ccf` — Add teacher review & rating system to resource pages
  - `d89f107` — Add Free/Pro Monthly/Pro Annual plans with server-side download quota
  - `95b72c1` — Add Google AdSense integration, hidden from active paying members
  - `7a4bab4` — Expand to multi-subject platform and rebrand to TeachLuma
- Working tree is clean as of this handover — no uncommitted changes.

---

## 15. Files Changed in Phases 1–3

**Phase 1** (`e005b5d`):
`admin/guide-add.php`, `admin/guide-edit.php`, `admin/resource-archive-toggle.php` (new), `admin/resources.php`, `database.sql`, `includes/download-functions.php`, `includes/guide-functions.php`, `includes/resource-functions.php`, `includes/review-functions.php`, `member/download.php`, `member/downloads.php`, `resource.php`, `sitemap.php`.

**Phase 2** (`6845cb9`):
`admin/resource-add.php`, `admin/resource-edit.php`, `admin/resource-file-delete.php` (new), `config/config.php`, `database.sql`, `includes/admin-resource-form.php`, `includes/guide-functions.php`, `includes/resource-functions.php`, `member/download-extra.php` (new), `resource.php`.

**Phase 3:** no files changed (verification only).

**For reference, the earlier Teacher Hub / SEO foundation** (`68f0f86`, predates "TeachLuma 2.0" but is load-bearing for Phases 1–3): `about.php`, `admin/guide-add.php`, `admin/guide-delete.php`, `admin/guide-edit.php`, `admin/guides.php`, `config/config.php`, `database.sql`, `includes/admin-guide-form.php`, `includes/admin-header.php`, `includes/admin-resource-form.php`, `includes/footer.php`, `includes/guide-functions.php`, `includes/header.php`, `includes/resource-functions.php`, `includes/review-functions.php`, `includes/seo-functions.php`, `index.php`, `resource.php`, `sitemap.php`, `teacher-hub-guide.php` (new), `teacher-hub.php` (new).

Two migration `.sql` files were produced and delivered to the user (not committed to git, per the project's established pattern of keeping live-DB migrations separate from the schema reference file): `teachluma_2_phase1_migration.sql` and `phase2_migration_final.sql`. Both are additive-only and documented with rollback notes in their own headers.
