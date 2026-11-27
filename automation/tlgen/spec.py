"""The resource spec: the single JSON file the agent writes for each resource.

Everything else (PDF, DOCX, PPTX, images, answer keys, What's Included,
QC) is derived from it, so one spec = one reproducible package. See
AGENT_GUIDE.md for the authoring rules and specs/examples/ for samples.
"""
import json
import random
import re
from pathlib import Path

from . import mathgen

SECTION_TYPES = {"match", "fill_blank", "multiple_choice", "true_false", "short_answer", "label", "sequence",
                 "sort", "draw_write", "math"}
PRO_PARTS = ("quiz", "homework", "rubric")
LETTERS = "abcdefghijklmnopqrstuvwxyz"


def uk_year(grade):
    """British-curriculum year for a US-style grade: Kindergarten = Year 1,
    Grade 1 = Year 2 ... Grade 10 = Year 11 (same mapping as the website)."""
    grade = (grade or "").strip()
    if grade == "Kindergarten":
        return "Year 1"
    m = re.fullmatch(r"Grade (\d{1,2})", grade)
    return f"Year {int(m.group(1)) + 1}" if m else None


def grade_display(grade):
    """"Grade 3 / Year 4" for printed files, so they suit US-style and
    British/international-school classes alike."""
    year = uk_year(grade)
    return f"{grade} / {year}" if year else (grade or "")


def level_display(meta):
    """recommended_level with the UK year added after the grade:
    "Grade 3 (ages 8–9)" -> "Grade 3 / Year 4 (ages 8–9)"."""
    grade = meta.get("grade") or ""
    level = meta.get("recommended_level") or grade
    if grade and uk_year(grade) and "Year " not in level and level.startswith(grade):
        return grade_display(grade) + level[len(grade):]
    return level


def load(path):
    with open(path, encoding="utf-8") as fh:
        spec = json.load(fh)
    spec.setdefault("_path", str(Path(path).resolve()))
    return spec


def save(spec, path):
    data = {k: v for k, v in spec.items() if not k.startswith("_")}
    Path(path).write_text(json.dumps(data, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")


def as_lines(v):
    if isinstance(v, str):
        v = v.splitlines()
    return [str(x).strip() for x in (v or []) if str(x).strip()]


def shuffled(seq, seed):
    """Deterministic shuffle that never leaves a list of 3+ in its original order."""
    out = list(seq)
    rng = random.Random(seed)
    for _ in range(20):
        rng.shuffle(out)
        if len(out) < 3 or out != list(seq):
            break
    return out


def parts(spec):
    """Student-facing parts present in this spec, in print order."""
    names = ["worksheet"]
    if spec["meta"]["tier"] == "pro":
        names += [p for p in ("quiz", "homework") if spec.get(p)]
    return names


PART_TITLES = {"worksheet": "Worksheet", "quiz": "Quiz", "homework": "Homework"}


# ---------------------------------------------------------------- answer keys
def _shown(option):
    """How an option reads in an answer key: a "pic:" drawing is named, not coded."""
    o = str(option)
    return f"the {o[4:].replace('_', ' ')} picture" if o.startswith("pic:") else o


def section_answers(sec, seed):
    """[(label, answer_text)] for one section, in the order items are printed."""
    t = sec["type"]
    items = sec.get("items", [])
    if t == "match":
        rights = shuffled([it["right"] for it in items], seed)
        return [(str(i + 1), f"{LETTERS[rights.index(it['right'])]} — {it['right']}") for i, it in enumerate(items)]
    if t in ("fill_blank", "label"):
        return [(str(i + 1), str(it["answer"])) for i, it in enumerate(items)]
    if t == "multiple_choice":
        return [(str(i + 1), f"{LETTERS[it['options'].index(it['answer'])]}) {_shown(it['answer'])}") for i, it in enumerate(items)]
    if t == "true_false":
        return [(str(i + 1), "True" if it["answer"] else "False") for i, it in enumerate(items)]
    if t == "short_answer":
        return [(str(i + 1), str(it["answer"])) for i, it in enumerate(items)]
    if t == "sequence":
        order = shuffled(items, seed)
        return [(f"{LETTERS[order.index(step)]}", f"{i + 1} — {step}") for i, step in enumerate(items)]
    if t == "sort":
        # whole sentences already end in punctuation, so join them with a space
        return [(g, (" " if any(str(w).rstrip().endswith((".", "!", "?")) for w in words) else ", ").join(words))
                for g, words in sec["groups"].items()]
    if t == "draw_write":
        return [(str(i + 1), it.get("guidance") or "Answers will vary.") for i, it in enumerate(items)]
    if t == "math":
        return [(str(i + 1), str(it["answer"])) for i, it in enumerate(items)]
    return []


def word_bank(sec, seed):
    if sec["type"] in ("fill_blank", "label") and sec.get("word_bank", sec["type"] == "label"):
        words = [str(it["answer"]) for it in sec["items"]] + as_lines(sec.get("extra_words"))
        return shuffled(list(dict.fromkeys(words)), seed)
    if sec["type"] == "sort":
        words = [w for ws in sec["groups"].values() for w in ws]
        return shuffled(words, seed)
    return []


def seed_for(spec, part, idx):
    return f"{spec.get('key', '')}:{part}:{idx}"


# ---------------------------------------------------------------- validation
def validate(spec):
    """Structural errors in a spec (empty list = valid)."""
    errs = []
    m = spec.get("meta") or {}
    for f in ("subject", "grade", "category", "topic", "tier", "resource_type", "title", "description", "overview",
              "suggested_duration"):
        if not str(m.get(f, "")).strip():
            errs.append(f"meta.{f} is missing")
    if m.get("tier") not in ("free", "pro"):
        errs.append("meta.tier must be free or pro")
    if len(as_lines(m.get("learning_objectives"))) < 2:
        errs.append("meta.learning_objectives needs 2+ lines")
    if len(as_lines(m.get("how_to_use"))) < 3:
        errs.append("meta.how_to_use needs 3+ steps")
    if "TODO" in json.dumps(spec):
        errs.append("spec still contains TODO placeholders")

    lp = spec.get("lesson_plan") or {}
    stages = lp.get("stages") or []
    if len(stages) < 4:
        errs.append("lesson_plan.stages needs 4+ stages (warm-up, presentation, practice, wrap-up)")
    for i, st in enumerate(stages):
        if not st.get("name") or not isinstance(st.get("minutes"), int) or st["minutes"] <= 0 or len(as_lines(st.get("steps"))) < 1:
            errs.append(f"lesson_plan.stages[{i}] needs name, minutes (int) and steps")
    if len(as_lines(lp.get("materials"))) < 1:
        errs.append("lesson_plan.materials is empty")

    for v in spec.get("vocabulary") or []:
        if not v.get("word") or not v.get("definition"):
            errs.append(f"vocabulary entry {v!r} needs word and definition")

    tier = m.get("tier")
    if tier == "pro":
        for p in PRO_PARTS:
            if not spec.get(p):
                errs.append(f"Pro resources need a {p}")
        if not spec.get("vocabulary") or len(spec["vocabulary"]) < 6:
            errs.append("Pro resources need 6+ vocabulary entries (flashcards and slides use them)")
        slides = spec.get("slides") or []
        if len(slides) < 4:
            errs.append("Pro resources need 4+ teaching slides in spec.slides (see AGENT_GUIDE.md)")
        need = {"bullets": ("title", "bullets"), "steps": ("title", "steps"), "compare": ("title", "left", "right"),
                "example": ("title", "problem", "steps", "answer"), "question": ("question",), "diagram": ("title", "emoji", "labels")}
        for i, sl in enumerate(slides):
            kind = sl.get("type", "bullets")
            if kind not in need:
                errs.append(f"slides[{i}]: unknown type {kind!r}")
                continue
            missing = [f for f in need[kind] if not sl.get(f)]
            if missing:
                errs.append(f"slides[{i}] ({kind}) is missing {', '.join(missing)}")
            if len(str(sl.get("notes", "")).strip()) < 40:
                errs.append(f"slides[{i}] needs speaker notes (40+ characters: what to say and ask)")

    for part in ("worksheet", "quiz", "homework"):
        doc = spec.get(part)
        if not doc:
            if part == "worksheet":
                errs.append("worksheet is missing")
            continue
        secs = doc.get("sections") or []
        if not secs:
            errs.append(f"{part} has no sections")
        for i, sec in enumerate(secs):
            errs += [f"{part}.sections[{i}]: {e}" for e in _section_errors(sec)]
    rub = spec.get("rubric")
    if rub:
        for c in rub.get("criteria", []):
            if not c.get("name") or len(c.get("levels", [])) != 4:
                errs.append("rubric criteria need a name and exactly 4 levels (4 = best)")
        if len(rub.get("criteria", [])) < 3:
            errs.append("rubric needs 3+ criteria")
    return errs


def _section_errors(sec):
    t = sec.get("type")
    if t not in SECTION_TYPES:
        return [f"unknown type {t!r}"]
    errs = []
    if not str(sec.get("instructions", "")).strip():
        errs.append("instructions are missing")
    items = sec.get("items") or []
    if t == "sort":
        groups = sec.get("groups") or {}
        if len(groups) < 2 or any(len(v) < 2 for v in groups.values()):
            errs.append("sort needs 2+ groups with 2+ words each")
        words = [w.lower() for ws in groups.values() for w in ws]
        if len(words) != len(set(words)):
            errs.append("sort words must be unique")
        return errs
    least = {"draw_write": 1, "short_answer": 2}.get(t, 3)
    if len(items) < least:
        errs.append(f"needs at least {least} item(s)")
    for j, it in enumerate(items):
        need = {"match": ("left", "right"), "fill_blank": ("text", "answer"), "multiple_choice": ("q", "options", "answer"),
                "true_false": ("statement",), "short_answer": ("q", "answer"), "label": ("emoji", "answer"),
                "draw_write": ("prompt",), "math": ("gen", "params", "prompt", "answer")}.get(t, ())
        if t == "sequence":
            if not isinstance(it, str) or not it.strip():
                errs.append(f"item {j + 1} must be a non-empty step")
            continue
        for f in need:
            if f not in it or it[f] in ("", None, []):
                errs.append(f"item {j + 1} is missing {f}")
        if t == "true_false" and not isinstance(it.get("answer"), bool):
            errs.append(f"item {j + 1}: answer must be true or false")
        if t == "multiple_choice" and it.get("options"):
            if it.get("answer") not in it["options"]:
                errs.append(f"item {j + 1}: answer is not one of the options")
            if len(it["options"]) != len(set(map(str, it["options"]))) or not 2 <= len(it["options"]) <= 4:
                errs.append(f"item {j + 1}: needs 2–4 distinct options")
        if t == "fill_blank" and "___" not in str(it.get("text", "")):
            errs.append(f"item {j + 1}: text needs a ____ blank")
    if t == "match":
        rights = [str(it.get("right")).lower() for it in items]
        if len(rights) != len(set(rights)):
            errs.append("match answers must be unique")
    if t in ("fill_blank", "label") and sec.get("word_bank", t == "label"):
        answers = [str(it.get("answer")).lower() for it in items]
        if t == "label" and len(answers) != len(set(answers)):
            errs.append("label answers must be unique")
    keys = [json.dumps(it, sort_keys=True) for it in items]
    if len(keys) != len(set(keys)):
        errs.append("duplicate items")
    return errs


# Options that read naturally only at the end of a list stay there.
_LAST_OPTION = re.compile(r"^(all of|none of|both|neither|they are (equal|the same)|not enough)", re.I)


def balance_options(spec):
    """Spread correct answers evenly across option positions (in place).

    Writers tend to list the right answer first. Each multiple-choice section
    draws answer positions from a shuffled bag, so every letter is used about
    equally. The result depends only on the key, section and option set, so
    rebuilding a spec never moves an answer again. Set "keep_order": true on a
    section or item to keep the options exactly as written.
    """
    for part in ("worksheet", "quiz", "homework"):
        for si, sec in enumerate((spec.get(part) or {}).get("sections", [])):
            if sec.get("type") != "multiple_choice" or sec.get("keep_order"):
                continue
            rng = random.Random(f"{spec.get('key', '')}:{part}:{si}:options")
            bags = {}
            for it in sec.get("items", []):
                opts, ans = list(it.get("options") or []), it.get("answer")
                if it.get("keep_order") or ans not in opts:
                    continue
                last = [o for o in opts if _LAST_OPTION.match(str(o))]
                movable = [o for o in opts if o not in last]
                if ans not in movable or len(movable) < 2:
                    continue
                others = [o for o in movable if o != ans]
                bag = bags.setdefault(len(movable), [])
                if not bag:
                    bag.extend(rng.sample(range(len(movable)), len(movable)))
                others.insert(bag.pop(), ans)
                it["options"] = others + last
    return spec


def prepare(spec):
    """Expand math generators and fill derived defaults (in place)."""
    mathgen.expand_sections(spec)
    balance_options(spec)
    return spec


# ---------------------------------------------------------------- text extraction (QC)
def student_text(spec):
    """Sentences a student reads (instructions + items), for reading-level checks."""
    out = []
    for part in parts(spec):
        for sec in spec[part]["sections"]:
            out.append(sec.get("instructions", ""))
            for it in sec.get("items", []):
                if isinstance(it, str):
                    out.append(it)
                elif sec["type"] != "math":
                    out += [str(it.get(k, "")) for k in ("text", "q", "statement", "prompt", "left", "right") if it.get(k)]
    return [re.sub(r"_{2,}", "", s).strip() for s in out if s and s.strip()]


def all_text(spec):
    """Every human-written string in the spec (for spelling and safety checks)."""
    out = []

    def walk(v, key=""):
        if key in ("gen", "params", "render", "emoji", "cover_emoji", "spelling_allow", "international_allow", "type", "generator", "key", "hashtags",
                   "subject", "category", "tier", "resource_type", "_path"):
            return
        if isinstance(v, str):
            out.append(v)
        elif isinstance(v, dict):
            for k, x in v.items():
                walk(x, k)
        elif isinstance(v, list):
            for x in v:
                walk(x, key)

    walk({k: v for k, v in spec.items() if not k.startswith("_")})   # skip _path, _admin_feedback
    return out
