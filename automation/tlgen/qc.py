"""Quality control: mandatory checks + a 100-point automated score.

Mandatory checks are pass/fail gates: a package with any failure is never
uploaded (the server re-checks this too). The score is a set of automated
indicators in six categories. It is NOT an independent verification of
educational accuracy — that is the admin's job when approving a draft.
"""
import json
import re
import subprocess
import tempfile
import zipfile
from pathlib import Path

from . import config, images, mathgen, planner, spec as S

SCORE_MAX = {"educational_accuracy": 25, "completeness": 20, "formatting": 15, "age_appropriateness": 15,
             "visual_quality": 10, "metadata": 15}
SCORE_NOTE = ("Automated indicators only (structure, computed answers, readability, formatting, metadata). "
              "Not an independent verification of educational accuracy — a teacher must review before publishing.")

PAGE_BUDGET = {"free": (4, 14), "pro": (8, 32)}
MAX_FILE = 25 * 1024 * 1024
MAX_IMAGE = 3 * 1024 * 1024
UNSAFE = {"kill", "killed", "gun", "guns", "bloody", "drunk", "beer", "wine", "cigarette", "smoke", "sexy", "stupid",
          "idiot", "dumb", "hate", "die", "dead", "weapon", "bomb", "drugs", "casino", "gamble"}
ALWAYS_OK = {"teachluma", "pdf", "docx", "pptx", "powerpoint", "esl", "ok", "worksheet", "worksheets", "flashcard", "flashcards",
             "pre", "re", "e.g", "i.e", "vs", "etc", "min", "mins", "am", "pm", "pinterest", "facebook", "instagram", "tiktok",
             "multi", "non", "co", "sub", "semi",            # hyphen prefixes (multi-step, non-fiction)
             "monday", "tuesday", "wednesday", "thursday", "friday", "saturday", "sunday",
             "mondays", "tuesdays", "wednesdays", "thursdays", "fridays", "saturdays", "sundays",
             "aren", "isn", "wasn", "weren", "doesn", "didn", "don", "hasn", "haven", "hadn", "couldn", "wouldn",
             "shouldn", "mustn", "needn", "ll", "ve", "re", "handwashing", "homework", "classmate", "classmates", "website", "websites", "smartphone", "emoji",
             "january", "february", "march", "april", "june", "july", "august", "september", "october", "november",
             "december", "mr", "mrs", "ms", "dr", "celsius", "fahrenheit", "tv", "app", "apps", "online", "email"}
# First names commonly used in classroom examples (proper nouns the dictionary lacks).
COMMON_NAMES = {"sam", "mia", "leo", "max", "nina", "ali", "tom", "anna", "lily", "jack", "emma", "noah", "mai", "kai", "zoe",
                "lucy", "maria", "luis", "sara", "amir", "yuki", "wei", "kim", "raj", "tim", "ella", "ravi", "aisha", "juan",
                "sofia", "ahmed", "lena", "ming", "tara", "nadia", "pablo", "rosa", "ken", "amy", "liam", "olivia", "ethan",
                "simon", "macdonald", "ben", "dan", "jake", "kate", "lisa", "mark", "paul", "peter", "sue", "tony", "omar",
                "hassan", "fatima", "carlos", "diego", "elena", "hiro", "jin", "mei", "minh", "nam", "an", "linh", "chloe",
                "grace", "harry", "jamie", "josh", "maya", "nora", "ryan", "sophie", "zara", "ivy", "finn", "ruby", "oscar"}


def grade_number(grade):
    if grade.lower().startswith("kinder"):
        return 0
    m = re.search(r"\d+", grade)
    return int(m.group()) if m else 3


def _syllables(word):
    w = word.lower()
    groups = re.findall(r"[aeiouy]+", w)
    n = len(groups)
    if w.endswith("e") and not w.endswith(("le", "ee")) and n > 1:
        n -= 1
    return max(1, n)


def reading_stats(sentences):
    words = [w for s in sentences for w in re.findall(r"[A-Za-z']+", s)]
    if not words:
        return 0.0, 0
    # Each prompt/instruction counts as at least one sentence.
    n_sent = sum(max(1, len(re.findall(r"[.!?]+(\s|$)", s))) for s in sentences)
    syl = sum(_syllables(w) for w in words)
    fk = 0.39 * (len(words) / n_sent) + 11.8 * (syl / len(words)) - 15.59
    longest = max(len(re.findall(r"[A-Za-z']+", part)) for s in sentences for part in re.split(r"[.!?]", s))
    return round(fk, 1), longest


def _spell_unknown(spec):
    from spellchecker import SpellChecker
    sp = SpellChecker()
    allow = {w.lower() for w in S.as_lines(spec.get("spelling_allow"))} | ALWAYS_OK
    allow |= {n.lower() for n in mathgen.NAMES} | COMMON_NAMES
    for v in spec.get("vocabulary") or []:
        allow |= {w.lower() for w in re.findall(r"[A-Za-z']+", v["word"])}
    words = set()
    for text in S.all_text(spec):
        text = re.sub(r"https?://\S+|#\w+|\b[\w-]+(\.[\w-]+)*\.(com|org|net)\b|\b\d+(st|nd|rd|th)\b", " ", text)
        for w in re.findall(r"[A-Za-z][A-Za-z'’-]*[A-Za-z]|[A-Za-z]", text):
            w = w.replace("’", "'")
            # A word written in syllables (ba-NA-na, com-PU-ter) is checked as the joined word.
            if "-" in w and w.replace("-", "").isalpha() and sp.known([w.replace("-", "").lower()]):
                continue
            for part in w.split("-"):
                p = part.lower()
                p = re.sub(r"'(s|re|ve|ll|d|m|t)$", "", p) if p not in ("can't", "won't", "don't") else p
                if len(p) > 1 and p not in allow:
                    words.add(p)
    return sorted(sp.unknown(words))


def _office_converts(path: Path):
    """LibreOffice must be able to open the file (and turn it into a PDF)."""
    with tempfile.TemporaryDirectory() as tmp:
        proc = subprocess.run([config.soffice(), "--headless", f"-env:UserInstallation=file://{tmp}/profile", "--convert-to", "pdf",
                               "--outdir", tmp, str(path)], capture_output=True, text=True, timeout=240)
        pdf = Path(tmp) / (path.stem + ".pdf")
        if not pdf.exists():
            return None, proc.stderr[-300:] or "conversion failed"
        from pypdf import PdfReader
        return len(PdfReader(str(pdf)).pages), None


def _duration_minutes(text):
    m = re.search(r"(\d+)\s*(?:-|–|to)?\s*(\d+)?\s*min", text or "")
    if not m:
        h = re.search(r"(\d+)\s*hour", text or "")
        return int(h.group(1)) * 60 if h else None
    return int(m.group(2) or m.group(1))


def run(spec, pkg, *, taxonomy=None, settings=None, client=None, catalog=None):
    """Return the QC report dict for a rendered package (see package.render)."""
    m = spec["meta"]
    checks, warnings = [], []

    def check(cid, passed, detail=""):
        checks.append({"id": cid, "passed": bool(passed), "detail": detail})
        return bool(passed)

    # 1. Spec structure
    errs = S.validate(spec)
    check("spec_valid", not errs, "; ".join(errs[:8]))

    # 2. Taxonomy (live, from the site)
    if taxonomy:
        subj = next((s for s in taxonomy["subjects"] if s["slug"] == m["subject"]), None)
        ok = bool(subj) and m["grade"] in subj["grades"] and m["category"] in [c["slug"] for c in subj["categories"]] \
            and m["resource_type"] in taxonomy["resource_types"]
        if settings:
            ok = ok and m["subject"] in settings["subjects"] and m["grade"] in settings["grades"].get(m["subject"], [])
        check("taxonomy", ok, "" if ok else "subject/grade/category/type not valid or not enabled in the dashboard")
    else:
        check("taxonomy", False, "taxonomy not loaded (run `plan` first or connect to the API)")

    # 3. Metadata
    meta_errs = []
    if not 8 <= len(m["title"].strip()) <= 150:
        meta_errs.append("title must be 8–150 chars")
    if not 40 <= len(m["description"].strip()) <= 500:
        meta_errs.append(f"description is {len(m['description'].strip())} chars (40–500)")
    if len(m["overview"].strip()) < 80:
        meta_errs.append("overview under 80 chars")
    if len(S.as_lines(m.get("learning_objectives"))) < 2 or len(S.as_lines(m.get("how_to_use"))) < 3:
        meta_errs.append("objectives (2+) / how-to-use (3+) too short")
    if len(pkg["whats_included"]) < 2:
        meta_errs.append("what's included too short")
    meta_ok = check("metadata", not meta_errs, "; ".join(meta_errs))

    # 4. Answers complete and consistent with the printed pages
    ans_errs = []
    for part in S.parts(spec):
        for i, sec in enumerate(spec[part]["sections"]):
            n_items = len(sec.get("items") or []) or len(sec.get("groups") or {})
            got = S.section_answers(sec, S.seed_for(spec, part, i))
            if len(got) != n_items or any(not str(b).strip() for _, b in got):
                ans_errs.append(f"{part} section {i + 1}")
            bank = S.word_bank(sec, S.seed_for(spec, part, i))
            if sec["type"] == "fill_blank" and sec.get("word_bank") and not all(str(it["answer"]) in bank for it in sec["items"]):
                ans_errs.append(f"{part} section {i + 1}: word bank lacks an answer")
    answers_ok = check("answers_complete", not ans_errs and not errs, "; ".join(ans_errs))

    # 5. Math answers re-solved independently
    math_items, bad = 0, []
    for part in S.parts(spec):
        for sec in spec[part]["sections"]:
            if sec["type"] == "math":
                for it in sec["items"]:
                    math_items += 1
                    try:
                        if mathgen.solve(it["gen"], it["params"]) != it["answer"]:
                            bad.append(it["prompt"])
                    except Exception as exc:     # unknown generator / bad params
                        bad.append(f"{it.get('prompt')} ({exc})")
    if m["subject"] == "math" and math_items < 10:
        bad.append(f"only {math_items} computed math items (need 10+)")
    math_ok = check("math_verified", not bad, "; ".join(bad[:5]) or (f"{math_items} items re-solved" if math_items else "no math items"))

    # 6. Lesson timing adds up
    total = sum(st.get("minutes", 0) for st in spec["lesson_plan"]["stages"])
    dur = _duration_minutes(m["suggested_duration"])
    timing_ok = check("lesson_timing", dur is not None and abs(total - dur) <= 5,
                      f"stages total {total} min; suggested duration {m['suggested_duration']}")

    # 7. Every file opens (pypdf / python-docx / python-pptx + LibreOffice)
    open_errs, page_counts = [], {}
    from pypdf import PdfReader
    for label, path in [("main", pkg["main"])] + pkg["extras"]:
        path = Path(path)
        try:
            if path.suffix == ".pdf":
                r = PdfReader(str(path))
                page_counts[path.name] = len(r.pages)
                if not "".join((r.pages[i].extract_text() or "") for i in range(min(2, len(r.pages)))).strip():
                    open_errs.append(f"{path.name}: no extractable text")
            else:
                with zipfile.ZipFile(path) as z:
                    z.testzip()
                if path.suffix == ".docx":
                    import docx
                    docx.Document(str(path))
                else:
                    import pptx
                    pptx.Presentation(str(path))
                pages, err = _office_converts(path)
                if err:
                    open_errs.append(f"{path.name}: LibreOffice could not open it ({err})")
                else:
                    page_counts[path.name] = pages
        except Exception as exc:
            open_errs.append(f"{path.name}: {exc}")
    files_ok = check("files_open", not open_errs, "; ".join(open_errs) or json.dumps(page_counts))

    # 8. No blank pages (an overflowing layout leaves near-empty pages)
    blanks = {}
    for path in [pkg["main"]] + [p for _, p in pkg["extras"] if str(p).endswith(".pdf")]:
        n = page_counts.get(Path(path).name, 0)
        b = images.blank_pages(Path(path), n) if n else []
        if b:
            blanks[Path(path).name] = b
    blank_ok = check("no_blank_pages", not blanks, json.dumps(blanks) if blanks else "")
    n_main = page_counts.get(Path(pkg["main"]).name, 0)
    sparse = [p for p in images.blank_pages(Path(pkg["main"]), n_main, 0.965) if p < n_main and p not in blanks.get(Path(pkg["main"]).name, [])] if n_main else []
    if sparse:
        warnings.append(f"nearly empty page(s) in the main PDF: {sparse} — check the layout (fine for drawing pages)")

    # 9. Page budget
    lo, hi = PAGE_BUDGET[m["tier"]]
    main_pages = page_counts.get(Path(pkg["main"]).name, 0)
    budget_ok = check("page_budget", lo <= main_pages <= hi, f"main PDF has {main_pages} pages (allowed {lo}–{hi})")

    # 10. Images
    img_errs = []
    if not pkg.get("thumbnail_fit"):
        img_errs.append("thumbnail title does not fit its frame (shorten the title)")
    for key, (mw, mh) in (("thumbnail", (1200, 900)), ("preview", (800, 600))):
        p = Path(pkg[key])
        w, h, white = images.visual_stats(p)
        if w < mw or h < mh:
            img_errs.append(f"{key} {w}x{h} below {mw}x{mh}")
        if white > 0.92:
            img_errs.append(f"{key} is mostly blank")
        if p.stat().st_size > MAX_IMAGE:
            img_errs.append(f"{key} over 3 MB")
    img_ok = check("images", not img_errs, "; ".join(img_errs))

    # 11. File sizes (site limit 25 MB per file)
    big = [Path(p).name for p in [pkg["main"]] + [x for _, x in pkg["extras"]] if Path(p).stat().st_size > MAX_FILE]
    size_ok = check("file_sizes", not big, ", ".join(big))

    # 12. Spelling
    unknown = _spell_unknown(spec)
    spell_ok = check("spelling", not unknown, ("unknown words: " + ", ".join(unknown[:20])) if unknown else
                     (f"allowed by spec: {', '.join(S.as_lines(spec.get('spelling_allow')))}" if spec.get("spelling_allow") else ""))

    # 13. Components required for the tier
    need = ["docx", "answer_key"] + (["pptx", "flashcards", "quiz", "homework", "rubric"] if m["tier"] == "pro" else [])
    have = set(pkg["components"])
    missing = [x for x in need if x not in have]
    tier_ok = check("tier_components", not missing, ("missing: " + ", ".join(missing)) if missing else ", ".join(sorted(have)))

    # 14. Duplicate / substantially similar resources
    dup_detail, dup_ok = "", False
    try:
        if client:
            res = client.duplicate_check(m["subject"], m["grade"], m["title"], m["topic"])
            hits = [x for x in res["matches"] if x["blocking"]]
            source = "live catalog"
            if catalog is not None and len(planner.tokens(m["topic"])) >= 2:
                # Same "topic already covered" rule as the server, also applied
                # here so it holds even where the site runs an older version.
                hits += [{"reason": "topic already covered at the same grade", "title": t}
                         for t in planner.covered_by(catalog, m["subject"], m["grade"], m["topic"], 1.0)
                         if t not in [h["title"] for h in hits]]
        elif catalog is not None:
            hits = planner.local_duplicates(catalog, m["subject"], m["grade"], m["title"], m["topic"])
            # The planner also skips topics generated before; a resource built
            # from the plan itself (or a regeneration) is that very attempt.
            hits = [h for h in hits if not h["reason"].startswith("already generated")]
            source = "cached catalog"
        else:
            raise RuntimeError("no catalog available")
        dup_ok = not hits
        dup_detail = (f"{source}: " + "; ".join(f"{h['reason']}: {h['title']}" for h in hits[:3])) if hits else f"{source}: no blocking matches"
    except Exception as exc:
        dup_detail = f"could not check: {exc}"
    check("duplicate_check", dup_ok, dup_detail)

    # 15. Content safety (age-appropriate wording)
    found = sorted({w for t in S.all_text(spec) for w in re.findall(r"[a-z]+", t.lower()) if w in UNSAFE})
    safe_ok = check("content_safety", not found, ", ".join(found))

    # 16. Reading level of student-facing text
    g = grade_number(m["grade"])
    fk, longest = reading_stats(S.student_text(spec))
    max_len = 14 if g <= 2 else (20 if g <= 5 else 28)
    limit = g + 3 + (1 if m["subject"] == "science" else 0)
    read_ok = check("reading_level", fk <= max(limit, 2.5) and longest <= max_len,
                    f"Flesch-Kincaid grade {fk} (limit {max(limit, 2.5)}); longest sentence {longest} words (limit {max_len})")

    # 17. Social copy (stored only, never posted automatically)
    so = spec.get("social") or {}
    tags = [t for t in so.get("hashtags", []) if re.fullmatch(r"#[\w]{2,40}", str(t))]
    so_errs = []
    if not 40 <= len(so.get("facebook_post", "").strip()) <= 2000:
        so_errs.append("facebook_post 40–2000")
    if not 10 <= len(so.get("pinterest_title", "").strip()) <= 100:
        so_errs.append("pinterest_title 10–100")
    if not 40 <= len(so.get("pinterest_description", "").strip()) <= 500:
        so_errs.append("pinterest_description 40–500")
    if not 3 <= len(tags) <= 12 or len(tags) != len(so.get("hashtags", [])):
        so_errs.append("3–12 valid hashtags")
    if not S.as_lines(so.get("visual_suggestions")):
        so_errs.append("visual_suggestions")
    social_ok = check("social_copy", not so_errs, "; ".join(so_errs))

    # ------------------------------------------------------------ score
    student = " ".join(S.student_text(spec)).lower()
    teach = student + " " + " ".join(st for s in spec["lesson_plan"]["stages"] for st in S.as_lines(s["steps"])).lower()
    objs = S.as_lines(m["learning_objectives"])
    aligned = sum(1 for o in objs if len(set(planner.tokens(o)) & set(planner.tokens(teach))) >= 2)
    vocab = [v["word"].lower() for v in spec.get("vocabulary") or []]
    vocab_used = sum(1 for w in vocab if w.split()[0] in student) / len(vocab) if vocab else 1.0
    n_items = sum(len(sec.get("items") or []) or sum(len(v) for v in sec.get("groups", {}).values())
                  for p in S.parts(spec) for sec in spec[p]["sections"])
    want_items = 30 if m["tier"] == "pro" else 12
    stages = spec["lesson_plan"]["stages"]
    visuals = json.dumps(spec, ensure_ascii=False).count('"emoji"') + sum(
        1 for p in S.parts(spec) for sec in spec[p]["sections"] for it in sec.get("items", []) if isinstance(it, dict) and it.get("render"))
    if visuals < 3:
        warnings.append("few pictures/diagrams in the student pages")
    if aligned < len(objs):
        warnings.append(f"{len(objs) - aligned} learning objective(s) not clearly practised in the activities")
    if vocab and vocab_used < 0.7:
        warnings.append(f"only {vocab_used:.0%} of vocabulary words appear in student pages")

    bd = {
        "educational_accuracy": (8 if math_ok else 0) + (8 if answers_ok else 0) + round(6 * aligned / max(1, len(objs))) + round(3 * vocab_used),
        "completeness": (8 if tier_ok else 0) + (4 if timing_ok else 0) + (4 if len(stages) >= 4 and all(len(S.as_lines(s["steps"])) >= 2 for s in stages) else 2)
                        + min(4, round(4 * n_items / want_items)),
        "formatting": (5 if files_ok else 0) + (4 if blank_ok else 0) + (3 if budget_ok else 0) + (3 if size_ok else 0),
        "age_appropriateness": (7 if read_ok else 2) + (4 if longest <= max_len else 1) + (4 if safe_ok else 0),
        "visual_quality": (4 if img_ok else 0) + (2 if img_ok else 0) + min(4, visuals),
        "metadata": (6 if meta_ok else 0) + (5 if social_ok else 0) + (4 if spell_ok else 0),
    }
    bd = {k: max(0, min(SCORE_MAX[k], v)) for k, v in bd.items()}
    score = sum(bd.values())
    passed = all(c["passed"] for c in checks)
    return {
        "passed": passed, "score": score, "breakdown": bd, "score_max": SCORE_MAX, "score_note": SCORE_NOTE,
        "mandatory": checks, "warnings": warnings, "page_counts": page_counts,
        "auto_publish_eligible": False,   # manual approval is required for every resource
    }
