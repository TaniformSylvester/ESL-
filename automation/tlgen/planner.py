"""Gap analysis: choose what to generate next.

Inputs: the live catalog (existing resources + earlier automation items), the
curriculum topic bank (data/topics.json) and the dashboard settings. Rules:

1. Admin regeneration requests come first.
2. Never pick a topic the server would block as a duplicate (mirrors
   automation_find_duplicates() in includes/automation-functions.php).
3. Prefer subject/grade/category cells with the fewest resources, so
   curriculum coverage grows evenly; rotate across subjects.
4. Assign Free/Pro so the running total moves toward the configured
   free percentage.
"""
import hashlib
import json
import re
from collections import Counter
from datetime import date

from . import config

STOP = {"a", "an", "the", "and", "or", "of", "to", "in", "on", "for", "with", "my", "our", "your", "pack", "lesson",
        "worksheet", "worksheets", "resource", "resources", "practice", "activity", "activities", "grade", "complete", "plan",
        "kids", "esl"}

# Resource type used for generated packs, per subject (all are in RESOURCE_TYPES).
DEFAULT_TYPE = {"esl": "Lesson Plan", "math": "Worksheet", "science": "Lesson Plan"}


def tokens(text):
    out = []
    for w in re.split(r"[^a-z0-9]+", (text or "").lower()):
        if not w or w in STOP or re.fullmatch(r"\d", w):
            continue
        if len(w) > 3 and w.endswith("s") and not w.endswith("ss"):
            w = w[:-1]
        if w not in out:
            out.append(w)
    return out


def similarity(a, b):
    x, y = set(tokens(a)), set(tokens(b))
    if not x or not y:
        return 0.0
    return len(x & y) / len(x | y)


def coverage(topic, text):
    """Share of the topic's key words found in `text` (1.0 = fully contained)."""
    x, y = set(tokens(topic)), set(tokens(text))
    return len(x & y) / len(x) if x else 0.0


# The planner skips a topic when an existing resource at the same grade covers
# at least this share of its key words. Uploads are blocked (server and QC)
# only at full coverage, so near-misses such as "Life Cycle of a Frog" next to
# a broad plants unit that mentions "life cycle" are still allowed.
PLAN_COVERAGE = 0.75


def covered_by(catalog, subject, grade, topic, threshold):
    """Existing resources at this subject+grade that already cover `topic`."""
    out = []
    for r in catalog.get("resources", []):
        if not r.get("is_published") and r.get("automation_status") in ("rejected", "regenerate_requested", "regenerated"):
            continue
        if r["subject"] == subject and r.get("grade") == grade and r.get("status") == "active" \
                and coverage(topic, f"{r['title']} {r.get('topic') or ''}") >= threshold:
            out.append(r["title"])
    return out


def local_duplicates(catalog, subject, grade, title, topic):
    """Same rule as the server; used for planning and offline QC."""
    hits = []
    rejected = {"rejected", "regenerate_requested", "regenerated"}
    for r in catalog.get("resources", []):
        if r["subject"] != subject or r.get("status") != "active":
            continue
        if not r.get("is_published") and r.get("automation_status") in rejected:
            continue
        same_title = tokens(r["title"]) and sorted(tokens(r["title"])) == sorted(tokens(title))
        sim = max(similarity(topic, r.get("topic") or ""), similarity(topic, r["title"]), similarity(title, r["title"]))
        if same_title:
            hits.append({"reason": "same title in this subject", "title": r["title"], "score": 1.0, "blocking": True})
        elif r.get("grade") == grade and sim >= 0.6:
            hits.append({"reason": "substantially similar topic at the same grade", "title": r["title"], "score": round(sim, 2), "blocking": True})
        elif r.get("grade") == grade and len(tokens(topic)) >= 2 and coverage(topic, f"{r['title']} {r.get('topic') or ''}") >= 1.0:
            hits.append({"reason": "topic already covered at the same grade", "title": r["title"], "score": 1.0, "blocking": True})
    for it in catalog.get("automation_items", []):
        # Topics already generated (or attempted) at this grade are skipped too,
        # unless an admin asked for them to be regenerated.
        if it["subject"] == subject and it["grade"] == grade and it.get("status") not in ("regenerate_requested",) \
                and similarity(topic, it.get("topic") or "") >= 0.6:
            hits.append({"reason": f"already generated ({it.get('status')})", "title": it.get("title"), "score": 1.0, "blocking": True})
    return hits


def topic_bank():
    with open(config.DATA / "topics.json", encoding="utf-8") as fh:
        bank = json.load(fh)
    bank.pop("_about", None)
    return bank


def idempotency_key(subject, grade, topic, tier, salt=""):
    raw = f"{subject}|{grade}|{topic}|{tier}|{salt or date.today().isoformat()}"
    slug = re.sub(r"[^a-z0-9]+", "-", f"{subject}-{grade}-{topic}".lower()).strip("-")[:60]
    return f"tl-{slug}-{hashlib.sha256(raw.encode()).hexdigest()[:10]}"


def plan(status, catalog, taxonomy, count=None, queue=None, only=None):
    """Return a list of plan entries (dicts) for this run.

    only: optional list of (subject, grade) pairs to restrict choices (used for
    the three acceptance-test resources).
    """
    settings = status["settings"]
    count = count or settings["per_run"]
    bank = topic_bank()
    valid_cats = {s["slug"]: {c["slug"] for c in s["categories"]} for s in taxonomy["subjects"]}
    entries = []

    # 1. Regeneration requests from the dashboard.
    for r in (queue or {}).get("regenerate", [])[:count]:
        entries.append({
            "subject": r["subject"], "grade": r["grade"], "category": r["category"], "topic": r["topic"],
            "resource_type": r["resource_type"], "tier": r["tier"], "regenerates_item_id": r["id"],
            "review_note": r.get("review_note"), "reason": "admin requested regeneration",
            "key": idempotency_key(r["subject"], r["grade"], r["topic"], r["tier"], salt=f"regen-{r['id']}"),
        })

    # 2. Coverage counts per (subject, grade, category) from the live catalog.
    cell = Counter()
    for r in catalog.get("resources", []):
        if r.get("status") == "active":
            cell[(r["subject"], r.get("grade"), r.get("category"))] += 1
    for it in catalog.get("automation_items", []):
        if it.get("status") in ("pending_review", "approved"):
            cell[(it["subject"], it["grade"], it.get("category"))] += 1

    candidates = []
    for subject in settings["subjects"]:
        for grade in settings["grades"].get(subject, []):
            if only and (subject, grade) not in only:
                continue
            grade_total = sum(v for (s, g, _), v in cell.items() if s == subject and g == grade)
            for order, line in enumerate(bank.get(subject, {}).get(grade, [])):
                category, topic = line.split("|", 1)
                if category not in valid_cats.get(subject, set()):
                    continue
                if local_duplicates(catalog, subject, grade, topic, topic) or covered_by(catalog, subject, grade, topic, PLAN_COVERAGE):
                    continue
                # Lower is better: empty categories first, then thin grades, then bank order.
                candidates.append(((cell[(subject, grade, category)], grade_total, order), subject, grade, category, topic))
    candidates.sort(key=lambda c: c[0])

    # 3. Round-robin across subjects so one subject doesn't take every slot.
    by_subject = {}
    for c in candidates:
        by_subject.setdefault(c[1], []).append(c)
    used_cells = set()
    while len(entries) < count and any(by_subject.values()):
        for subject in list(by_subject):
            if len(entries) >= count:
                break
            queue_s = by_subject[subject]
            while queue_s:
                _, s, g, cat, topic = queue_s.pop(0)
                if (s, g, cat) in used_cells and any(q[3] != cat for q in queue_s):
                    continue        # spread within a run: one topic per category where possible
                used_cells.add((s, g, cat))
                entries.append({"subject": s, "grade": g, "category": cat, "topic": topic,
                                "resource_type": DEFAULT_TYPE.get(s, "Lesson Plan"), "reason": "curriculum gap"})
                break
            if not queue_s:
                by_subject.pop(subject)

    # 4. Tiers: move the overall balance toward free_percent.
    free_n, pro_n = status["tier_counts"]["free"], status["tier_counts"]["pro"]
    target = settings["free_percent"] / 100
    for e in entries:
        if "tier" not in e:
            total = free_n + pro_n + 1
            # Whichever choice leaves the free share closest to the target (ties go to Pro).
            e["tier"] = "free" if abs((free_n + 1) / total - target) < abs(free_n / total - target) else "pro"
            free_n += e["tier"] == "free"
            pro_n += e["tier"] == "pro"
        e.setdefault("key", idempotency_key(e["subject"], e["grade"], e["topic"], e["tier"]))
    return entries
