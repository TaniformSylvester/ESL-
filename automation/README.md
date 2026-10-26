# TeachLuma resource generator

Creates complete, print-ready teaching packs and uploads them to
teachluma.com as **unpublished drafts** for an admin to review. Nothing goes
live until someone approves it in **Admin → Resource Automation**.

```
Claude Code session (cloud)                          teachluma.com (FastComet)
┌──────────────────────────────┐   HTTPS + token    ┌──────────────────────────────┐
│ plan → write spec → build    │ ─────────────────▶ │ api/automation.php           │
│ (PDF, DOCX, PPTX, images)    │  drafts only       │  validate → create_resource()│
│ QC + up to 3 corrections     │                    │  is_published = 0, always    │
└──────────────────────────────┘                    │ Admin review → Approve       │
                                                    └──────────────────────────────┘
```

All the heavy work (writing, rendering, checking) happens in the Claude Code
session. The website only receives finished files, so FastComet needs no
cron jobs, Python or extra software.

## What a pack contains

| | Free | Pro |
|---|---|---|
| Printable PDF: cover, lesson plan, worksheet, answer key | ✓ | ✓ |
| Editable Word worksheet | ✓ | full pack |
| Separate answer key PDF | ✓ | ✓ |
| Quiz, homework, rubric, picture flashcards | | ✓ |
| Editable PowerPoint lesson slides | | ✓ |

The thumbnail (1200×675) and preview (1200×900) images are generated from
the real pages.

## One-time setup

1. **Database:** run `automation_migration.sql` in phpMyAdmin. It only adds
   new tables; nothing existing is changed.
2. **Upload the PHP files** from the deployment zip.
3. **Create an API token:** Admin → Resource Automation → API access →
   Create token. It is shown once. The site stores only a hash of it.
4. **Cloud environment for Claude Code:** open the environment menu in the
   session title bar, then Edit.
   - **Network access:** add `teachluma.com` under *Allowed domains*, and
     keep *Allow package managers* ticked.
   - **Secret:** under *Network secrets*, add the token:
     - Allowed websites: `teachluma.com`;
     - Path prefix: `/api/automation.php`;
     - header `Authorization` with prefix `Bearer`;
     - a second header `X-TeachLuma-Token` with no prefix and the same token
       (some hosts strip `Authorization`).

     The token then never exists inside the session. Alternatively, set an
     environment variable `TEACHLUMA_API_TOKEN`.
   - Never paste the token into a chat, a file or GitHub.
5. In a new session: `bash automation/bootstrap.sh`. It installs the tools
   and checks everything.

## Running it

- **On demand:** ask Claude Code: *"Run the TeachLuma generator for 2
  resources following automation/AGENT_GUIDE.md."*
- **On a schedule:** create a Claude Code scheduled task (routine) that
  starts a fresh session in this environment with that prompt. The
  dashboard's *Frequency* and *Run hour* control whether a scheduled run
  actually generates anything. If it isn't due, the run stops right away.

Commands (from `automation/`, with `.venv/bin/python -m tlgen`): `check`,
`status`, `plan`, `run-start`, `new-spec`, `build`, `upload`,
`report-failed`, `heartbeat` and `run-finish`. See `tlgen/cli.py` or
`AGENT_GUIDE.md`.

## Safety model

- The API has no publish, edit or delete action. Every upload goes through
  the site's normal `create_resource()` with `is_published = 0`.
- **Approve** is the only way to publish. It requires the same QC checklist
  confirmation as the normal resource editor.
- Token scopes are `catalog:read` and `drafts:write`. The token cannot touch
  users, memberships, subscriptions, payments or existing resources.
- **Limits:**
  - 60 requests a minute;
  - 30 uploads an hour;
  - a daily upload cap you set in the dashboard (default 10);
  - 80 MB per request (the site's normal 25 MB file and 3 MB image limits
    still apply).
- The server re-validates everything:
  - taxonomy and metadata;
  - file type and structure (a renamed PHP file is rejected);
  - image sizes;
  - every mandatory QC check marked passed;
  - the score matching its breakdown;
  - duplicates (same file, same title, or a ≥60% similar topic at the same
    grade).
- **Idempotency keys:** a retried upload never creates a second draft.
- **Audit log:** every API call and admin action is recorded (dashboard →
  Audit log).
- **Emergency stop:** one button. The API then refuses everything except
  `status`, and any running run is marked stopped.
- **Automatic publishing:** stays off and cannot be switched on yet. The
  score is shown as *automated indicators*, not verified accuracy.
- Social media copy is stored for you only. Nothing is posted anywhere.

## Quality control

Each build runs 17 mandatory checks:
- spec structure, taxonomy and metadata;
- answer-key completeness;
- every math answer re-solved from its parameters;
- lesson timing;
- every file opened (including by LibreOffice);
- blank-page detection and page budget;
- images and file sizes;
- spelling;
- tier components;
- a live duplicate check;
- wording safety and reading level;
- social copy.

Failures get up to 3 correction attempts, automatic or by editing the spec.
After that the item is reported as failed, with its error report, and nothing
is uploaded. You can request regeneration from the dashboard.

## Costs and limitations

- **Claude usage:** generation uses your Claude plan's Claude Code usage. A
  resource takes roughly 5–15 minutes of agent time. Heavy schedules use more
  of your plan's limits, and runs can be cut short when limits are reached.
  A run interrupted this way shows as *interrupted* and can be retried. There
  is no separate API bill unless you choose to run it with an API key.
- **Hosting:** each pack adds about 0.5–1.5 MB to `uploads/`. FastComet does
  no generation work.
- **Scheduling:** scheduled tasks run in the cloud. The dashboard schedule
  decides whether a run generates anything. If a run is missed (for example,
  usage limits), the next one catches up.
- **Accuracy:** automated checks catch structural, arithmetic, layout and
  reading-level problems. They cannot fully verify facts or teaching quality,
  which is why every resource needs your approval.
- **Pictures:** only emoji (Noto Color Emoji, Apache 2.0/OFL) and diagrams
  drawn by the generator, so there are no licensing worries but the visual
  style is limited.

## Files

- `tlgen/`: the generator (planner, renderers, QC, CLI).
- `tlgen/data/topics.json`: curriculum topic bank (ESL K–10, Math 1–6,
  Science 1–6).
- `specs/examples/`: the three reviewed acceptance-test specs, as a quality
  reference.
- `specs/work/`, `out/`, `.venv/`, `.cache/`: local working files (gitignored).
