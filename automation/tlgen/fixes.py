"""Automatic corrections for QC failures that have a safe mechanical fix.

Content problems (wrong facts, weak activities, spelling) are NOT patched
here; they need the spec to be edited, which build.py also counts as a
correction attempt.
"""
import re

from . import mathgen, spec as S


def _trim(text, limit):
    text = text.strip()
    if len(text) <= limit:
        return text
    cut = text[:limit]
    end = max(cut.rfind(". "), cut.rfind("! "), cut.rfind("? "))
    return (cut[:end + 1] if end > limit * 0.5 else cut[:cut.rfind(" ")].rstrip(",;:") + "…").strip()


def apply(spec, report, state):
    """Mutate spec/state to address failed checks. Returns a list of fix descriptions."""
    failed = {c["id"]: c for c in report["mandatory"] if not c["passed"]}
    done = []
    m = spec["meta"]

    if ("page_budget" in failed and _over_budget(failed["page_budget"]["detail"])) or "no_blank_pages" in failed:
        if state.get("compact", 0) < 2:
            state["compact"] = state.get("compact", 0) + 1
            state["compact_fix"] = state["compact"]
            done.append(f"tightened layout to compact level {state['compact']} (page budget / blank page)")

    if "math_verified" in failed:
        n = 0
        for part in S.parts(spec):
            for sec in spec[part]["sections"]:
                for it in sec.get("items", []) if sec["type"] == "math" else []:
                    try:
                        right = mathgen.solve(it["gen"], it["params"])
                    except Exception:
                        continue
                    if it["answer"] != right:
                        it["answer"] = right
                        n += 1
        if n:
            done.append(f"recomputed {n} math answer(s) from their parameters")

    if "lesson_timing" in failed:
        total = sum(st["minutes"] for st in spec["lesson_plan"]["stages"])
        if 15 <= total <= 120:
            m["suggested_duration"] = f"{total} minutes"
            done.append(f"set suggested duration to the lesson's stage total ({total} minutes)")

    if "metadata" in failed:
        if len(m["description"]) > 500:
            m["description"] = _trim(m["description"], 495)
            done.append("shortened description to 500 characters")

    if "social_copy" in failed:
        so = spec.setdefault("social", {})
        tags = []
        for t in so.get("hashtags", []):
            t = "#" + re.sub(r"[^\w]", "", str(t).lstrip("#"))
            if 3 <= len(t) <= 41 and t.lower() not in [x.lower() for x in tags]:
                tags.append(t)
        if tags != so.get("hashtags"):
            so["hashtags"] = tags[:12]
            done.append("normalised hashtags")
        for k, lim in (("facebook_post", 2000), ("pinterest_title", 100), ("pinterest_description", 500)):
            if len(so.get(k, "")) > lim:
                so[k] = _trim(so[k], lim - 3)
                done.append(f"shortened {k}")

    if "images" in failed or "files_open" in failed:
        # Rendering hiccups (fonts not loaded, converter timeout) clear on a re-render.
        done.append("re-rendering files")
    return done


def _over_budget(detail):
    m = re.search(r"has (\d+) pages \(allowed (\d+)–(\d+)\)", detail)
    return bool(m) and int(m.group(1)) > int(m.group(3))
