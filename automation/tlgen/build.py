"""Render → QC → automatic correction loop (at most 3 correction attempts).

State lives in out/<key>/state.json so attempts survive across commands and
sessions:
  new        → never built
  passed     → every mandatory check passed; ready to upload as a draft
  qc_failed  → failed, no mechanical fix left; edit the spec and build again
               (each changed-spec rebuild counts as a correction attempt)
  failed     → still failing after 3 corrections; report it (report-failed)
  uploaded / reported → done
"""
import hashlib
import shutil
import json
from datetime import datetime, timezone
from pathlib import Path

from . import config, fixes, package, qc, spec as S


def work_dir(key):
    return config.OUT / key


def load_state(key):
    p = work_dir(key) / "state.json"
    if p.exists():
        return json.loads(p.read_text())
    return {"key": key, "status": "new", "correction_attempts": 0, "compact": 0, "attempts": []}


def save_state(state):
    d = work_dir(state["key"])
    d.mkdir(parents=True, exist_ok=True)
    (d / "state.json").write_text(json.dumps(state, indent=2, ensure_ascii=False))


def _hash(path):
    return hashlib.sha256(Path(path).read_bytes()).hexdigest()


def error_report(state, report):
    failed = [c for c in report["mandatory"] if not c["passed"]]
    return {
        "summary": f"Failed {len(failed)} mandatory check(s) after {state['correction_attempts']} correction attempt(s).",
        "failed_checks": failed, "warnings": report.get("warnings", []),
        "attempts": state["attempts"], "generated_at": datetime.now(timezone.utc).isoformat(timespec="seconds"),
    }


def build(spec_path, ctx, log=print):
    spec = S.load(spec_path)
    key = spec.get("key") or ""
    if len(key) < 8:
        raise SystemExit("Spec has no valid 'key' (use new-spec to create it from the plan).")
    state = load_state(key)
    if state["status"] in ("uploaded", "reported"):
        raise SystemExit(f"{key} is already {state['status']}; a new version needs a new key.")
    if state["status"] == "failed":
        raise SystemExit(f"{key} failed after {config.MAX_CORRECTIONS} corrections. Run: report-failed {key}")

    h = _hash(spec_path)
    if state["status"] == "qc_failed" and h != state.get("spec_hash"):
        state["correction_attempts"] += 1
        state["attempts"].append({"at": _now(), "correction": "spec edited after QC failure"})
        log(f"Spec changed after a QC failure → correction attempt {state['correction_attempts']}/{config.MAX_CORRECTIONS}")

    # Layout tuning is redone on every build; only compaction applied as a
    # counted correction carries over.
    state["compact"] = state.get("compact_fix", 0)
    while True:
        S.prepare(spec)
        log(f"Rendering {key} (compact level {state['compact']})…")
        pkg = package.render(spec, work_dir(key) / "pkg", state["compact"])
        report = qc.run(spec, pkg, **ctx)
        report["correction_attempts"] = state["correction_attempts"]
        failed = [c["id"] for c in report["mandatory"] if not c["passed"]]
        state["attempts"].append({"at": _now(), "score": report["score"], "failed": failed, "pages": pkg["pages"]})
        S.save(spec, spec_path)                 # freezes generated math items + any fixes
        state["spec_hash"] = _hash(spec_path)
        (work_dir(key) / "qc.json").write_text(json.dumps(report, indent=2, ensure_ascii=False))
        (work_dir(key) / "package.json").write_text(json.dumps(pkg, indent=2, ensure_ascii=False))

        while report["passed"] and state["compact"] < 2 and any(w.startswith("nearly empty") for w in report["warnings"]):
            # Layout tuning (not a correction): tighten the layout a step at a time,
            # keeping each step only if it saves pages and still passes every check.
            log("Nearly empty page found; trying a tighter layout…")
            trial = package.render(spec, work_dir(key) / "pkg-trial", state["compact"] + 1)
            trial_report = qc.run(spec, trial, **ctx)
            shutil.rmtree(work_dir(key) / "pkg-trial")
            if trial_report["passed"] and trial["pages"] < pkg["pages"]:
                state["compact"] += 1
                pkg = package.render(spec, work_dir(key) / "pkg", state["compact"])
                report = qc.run(spec, pkg, **ctx)
                report["correction_attempts"] = state["correction_attempts"]
                state["attempts"][-1].update({"layout_tuned": state["compact"], "pages": pkg["pages"], "score": report["score"]})
                (work_dir(key) / "qc.json").write_text(json.dumps(report, indent=2, ensure_ascii=False))
                (work_dir(key) / "package.json").write_text(json.dumps(pkg, indent=2, ensure_ascii=False))
                log(f"Kept the tighter layout ({pkg['pages']} pages).")
            else:
                break

        if report["passed"]:
            state["status"] = "passed"
            save_state(state)
            log(f"QC passed · automated score {report['score']}/100 · {pkg['pages']} pages")
            return state, report, pkg

        log("QC failed: " + "; ".join(f"{c['id']} ({c['detail']})" for c in report["mandatory"] if not c["passed"]))
        if state["correction_attempts"] >= config.MAX_CORRECTIONS:
            state["status"] = "failed"
            state["error_report"] = error_report(state, report)
            save_state(state)
            log(f"Giving up after {config.MAX_CORRECTIONS} correction attempts. Run: report-failed {key}")
            return state, report, pkg

        done = fixes.apply(spec, report, state)
        if not done:
            state["status"] = "qc_failed"
            save_state(state)
            log("No automatic fix applies. Edit the spec to address the failures, then build again "
                f"({config.MAX_CORRECTIONS - state['correction_attempts']} correction attempt(s) left).")
            return state, report, pkg
        state["correction_attempts"] += 1
        state["attempts"][-1]["fixes"] = done
        log(f"Automatic correction {state['correction_attempts']}/{config.MAX_CORRECTIONS}: " + "; ".join(done))


def _now():
    return datetime.now(timezone.utc).isoformat(timespec="seconds")
