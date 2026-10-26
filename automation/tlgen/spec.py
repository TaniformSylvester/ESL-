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
        return [(str(i + 1), f"{LETTERS[it['options'].index(it['answer'])]}) {it['answer']}") for i, it in enumerate(items)]
    if t == "true_false":
        return [(str(i + 1), "True" if it["answer"] else "False") for i, it in enumerate(items)]
    if t == "short_answer":
        return [(str(i + 1), str(it["answer"])) for i, it in enumerate(items)]
    if t == "sequence":
        order = shuffled(items, seed)
        return [(f"{LETTERS[order.index(step)]}", f"{i + 1} — {step}") for i, step in enumerate(items)]
    if t == "sort":
        return [(g, ", ".join(words)) for g, words in sec["groups"].items()]
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


def prepare(spec):
    """Expand math generators and fill derived defaults (in place)."""
    mathgen.expand_sections(spec)
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
        if key in ("gen", "params", "render", "emoji", "cover_emoji", "spelling_allow", "type", "generator", "key", "hashtags",
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
