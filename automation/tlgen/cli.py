"""Command line: python -m tlgen <command>  (run from the automation/ folder).

  check                      verify local tools (Chromium, LibreOffice, fonts)
  status                     site automation status + work queue
  plan [--count N] [--only "esl:Grade 1"]...   choose topics → out/plan.json
  run-start [--trigger manual|scheduled|retry] [--retry-of ID]
  new-spec KEY               skeleton spec for a plan entry → specs/work/KEY.json
  build SPEC                 render + QC + automatic corrections (max 3)
  upload KEY                 upload a passed package as an UNPUBLISHED draft
  report-failed KEY          record a resource that could not pass QC
  heartbeat [MESSAGE]        keep the open run alive
  run-finish [--status completed|partial|failed] [--message TEXT]
"""
import argparse
import json
import shutil
import sys

from . import api, build as B, config, planner, spec as S

RUN_FILE = config.OUT / "run.json"
PLAN_FILE = config.OUT / "plan.json"
CACHE_DIR = config.OUT / "_site"
SPEC_DIR = config.ROOT / "specs" / "work"


def _write(path, data):
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(data, indent=2, ensure_ascii=False))


def _read(path, default=None):
    return json.loads(path.read_text()) if path.exists() else default


def _client():
    return api.Client()


def _run():
    run = _read(RUN_FILE)
    if not run:
        raise SystemExit("No open run. Start one with: run-start")
    return run


def qc_context(online=True):
    """Live taxonomy/settings/duplicate check from the site; falls back to the cache from `plan`."""
    if online:
        try:
            c = _client()
            st, tax, cat = c.status(), c.taxonomy(), c.catalog()
            for name, data in (("taxonomy", tax), ("status", st), ("catalog", cat)):
                _write(CACHE_DIR / f"{name}.json", data)
            return {"taxonomy": tax, "settings": st["settings"], "client": c, "catalog": cat}
        except Exception as exc:
            print(f"! site not reachable ({exc}); using cached catalog from the last `plan`.")
    return {"taxonomy": _read(CACHE_DIR / "taxonomy.json"), "settings": (_read(CACHE_DIR / "status.json") or {}).get("settings"),
            "client": None, "catalog": _read(CACHE_DIR / "catalog.json")}


# ---------------------------------------------------------------- commands
def cmd_check(a):
    ok = True
    for name, fn in (("Chromium", config.chromium), ("LibreOffice", config.soffice)):
        try:
            print(f"✓ {name}: {fn()}")
        except Exception as exc:
            ok = False
            print(f"✗ {name}: {exc}")
    for tool in ("pdftoppm",):
        print(("✓ " if shutil.which(tool) else "✗ ") + tool)
        ok &= bool(shutil.which(tool))
    print(("✓ " if config.EMOJI_FONT else "✗ ") + f"Noto Color Emoji: {config.EMOJI_FONT or 'missing (apt-get install fonts-noto-color-emoji)'}")
    ok &= bool(config.EMOJI_FONT)
    print(f"• token: {'TEACHLUMA_API_TOKEN environment variable' if config.API_TOKEN else 'network secret (added by the proxy, not visible here)'}")
    try:
        st = _client().status()
        print(f"✓ API {config.API_BASE}: connected · emergency_stop={st['settings']['emergency_stop']}")
    except api.ApiError as exc:
        ok = False
        hint = {0: "site not reachable: allow the domain under Network access",
                401: "token missing or wrong: check the secret's headers and allowed website",
                503: "migration not run on the site yet"}.get(exc.status, "")
        print(f"✗ API {config.API_BASE}: {exc}" + (f" ({hint})" if hint else ""))
    sys.exit(0 if ok else 1)


def cmd_status(a):
    c = _client()
    st = c.status()
    s = st["settings"]
    print(json.dumps({k: st[k] for k in ("due", "due_reason", "last_success", "tier_counts", "uploads_today", "server_time")}, indent=2))
    print(f"enabled={s['enabled']} emergency_stop={s['emergency_stop']} frequency={s['frequency']} per_run={s['per_run']} "
          f"free%={s['free_percent']} daily_cap={s['max_uploads_per_day']} auto_publish={s['auto_publish']}")
    if not s["emergency_stop"]:
        q = c.work_queue()
        print(f"work queue: {len(q['regenerate'])} regeneration request(s), {len(q['retry_runs'])} run(s) to retry")
        for r in q["regenerate"]:
            print(f"  regenerate item #{r['id']}: {r['subject']} {r['grade']} — {r['topic']} ({r['tier']}) note: {r.get('review_note') or '-'}")
        for r in q["retry_runs"]:
            print(f"  retry run #{r['id']}: {len(r['plan'])} planned")


def cmd_plan(a):
    c = _client()
    st, tax, cat = c.status(), c.taxonomy(), c.catalog()
    if st["settings"]["emergency_stop"]:
        raise SystemExit("Emergency stop is on — nothing to plan.")
    queue = c.work_queue()
    for name, data in (("status", st), ("taxonomy", tax), ("catalog", cat)):
        _write(CACHE_DIR / f"{name}.json", data)
    only = [tuple(x.split(":", 1)) for x in a.only] if a.only else None
    entries = planner.plan(st, cat, tax, a.count, queue, only)
    _write(PLAN_FILE, entries)
    print(f"Planned {len(entries)} resource(s) → {PLAN_FILE}")
    for e in entries:
        print(f"  [{e['tier']:4}] {e['subject']:7} {e['grade']:12} {e['category']:24} {e['topic']}  ({e['reason']})  key={e['key']}")


def cmd_run_start(a):
    c = _client()
    if a.trigger == "retry":
        retry = next((r for r in c.work_queue()["retry_runs"] if r["id"] == a.retry_of), None)
        if not retry:
            raise SystemExit(f"Run #{a.retry_of} is not marked for retry.")
        plan = retry["plan"]
        _write(PLAN_FILE, plan)
    else:
        plan = _read(PLAN_FILE) or []
    if not plan:
        raise SystemExit("Plan is empty; run `plan` first.")
    res = c.run_start(a.trigger, plan, a.retry_of)
    _write(RUN_FILE, {"run_id": res["run_id"], "trigger": a.trigger, "api_base": c.base, "keys": [e["key"] for e in plan]})
    print(f"Run #{res['run_id']} started ({a.trigger}, {len(plan)} planned).")


def cmd_new_spec(a):
    plan = _read(PLAN_FILE) or []
    e = next((x for x in plan if x["key"] == a.key), None)
    if not e:
        raise SystemExit(f"{a.key} is not in {PLAN_FILE}")
    out = SPEC_DIR / f"{a.key}.json"
    if out.exists() and not a.force:
        raise SystemExit(f"{out} exists (use --force to overwrite).")
    if e.get("regenerates_item_id"):
        # Start a regeneration from the rejected version, with the admin's note
        # attached, so only what the note asks for needs to change.
        prev = next((r for r in _client().work_queue()["regenerate"] if r["id"] == e["regenerates_item_id"]), None)
        if prev and prev.get("spec"):
            spec = {k: v for k, v in prev["spec"].items() if not k.startswith("_")}
            spec["key"] = e["key"]
            spec["meta"]["regenerates_item_id"] = e["regenerates_item_id"]
            spec["_admin_feedback"] = e.get("review_note") or ""
            _write(out, spec)
            print(f"Wrote {out} from the rejected version.\nAdmin feedback: {spec['_admin_feedback'] or '(none)'}\n"
                  f"Fix what the feedback asks for (it may concern the generator itself), then: python -m tlgen build {out}")
            return
    pro = e["tier"] == "pro"
    todo = "TODO"
    skeleton = {
        "version": 1, "key": e["key"],
        "meta": {"subject": e["subject"], "grade": e["grade"], "category": e["category"], "topic": e["topic"], "tier": e["tier"],
                 "resource_type": e["resource_type"], "title": todo, "description": todo, "overview": todo,
                 "learning_objectives": [todo], "how_to_use": [todo], "suggested_duration": "45 minutes",
                 "recommended_level": e["grade"], "skills_practiced": todo, "activity_ideas": [todo], "teacher_tips": todo,
                 "differentiation_notes": todo, "assessment_notes": todo,
                 **({"regenerates_item_id": e["regenerates_item_id"]} if e.get("regenerates_item_id") else {})},
        "cover_emoji": [todo],
        "vocabulary": [{"word": todo, "emoji": todo, "definition": todo, "example": todo}],
        "lesson_plan": {"materials": [todo], "stages": [{"name": n, "minutes": 0, "steps": [todo]} for n in
                                                        ("Warm-up", "Presentation", "Guided practice", "Independent practice", "Wrap-up")],
                        "differentiation": {"support": [todo], "challenge": [todo]}, "assessment": todo},
        "worksheet": {"title": todo, "sections": [{"type": todo, "instructions": todo, "items": []}]},
        "social": {"facebook_post": todo, "pinterest_title": todo, "pinterest_description": todo, "hashtags": [todo], "visual_suggestions": [todo]},
        "spelling_allow": [],
    }
    if pro:
        skeleton.update({"quiz": {"title": todo, "sections": []}, "homework": {"title": todo, "sections": []},
                         "rubric": {"criteria": [{"name": todo, "levels": [todo] * 4}]}, "slides": []})
    if e.get("review_note"):
        skeleton["_admin_feedback"] = e["review_note"]
    _write(out, skeleton)
    print(f"Wrote {out}\nFill every TODO following AGENT_GUIDE.md, then: python -m tlgen build {out}")


def cmd_build(a):
    state, report, pkg = B.build(a.spec, qc_context(not a.offline))
    print(json.dumps({"status": state["status"], "score": report["score"], "breakdown": report["breakdown"],
                      "correction_attempts": state["correction_attempts"], "warnings": report["warnings"],
                      "files": [pkg["main"]] + [p for _, p in pkg["extras"]]}, indent=2, ensure_ascii=False))
    sys.exit(0 if state["status"] == "passed" else 2)


def _final(key):
    d = B.work_dir(key)
    state = B.load_state(key)
    spec_path = SPEC_DIR / f"{key}.json"
    if not spec_path.exists():
        spec_path = next(iter(config.ROOT.glob(f"specs/**/{key}.json")), spec_path)
    return state, S.load(spec_path), _read(d / "qc.json"), _read(d / "package.json")


def cmd_upload(a):
    run = _run()
    state, spec, report, pkg = _final(a.key)
    if state["status"] == "uploaded":
        print(f"Already uploaded (item #{state.get('item_id')}).")
        return
    if state["status"] != "passed" or not report or not report["passed"]:
        raise SystemExit("Only packages that passed QC can be uploaded. Failed ones: report-failed KEY")
    c = _client()
    c.heartbeat(run["run_id"], f"uploading {spec['meta']['title']}")
    meta = {k: v for k, v in spec["meta"].items()}
    meta["whats_included"] = pkg["whats_included"]
    files = {"resource_file": pkg["main"], "thumbnail": pkg["thumbnail"], "preview_image": pkg["preview"]}
    for i, (label, path) in enumerate(pkg["extras"], 1):
        files[f"additional_file_{i}"] = path
        meta[f"additional_file_label_{i}"] = label
    spec_out = {k: v for k, v in spec.items() if not k.startswith("_")}
    try:
        res = c.upload(run["run_id"], a.key, meta, report, spec["social"], spec_out, files)
    except api.ApiError as exc:
        print(f"Upload refused: {exc}")
        if exc.status == 409 and isinstance(exc.body, dict) and exc.body.get("duplicates"):
            print(json.dumps(exc.body["duplicates"], indent=2))
        sys.exit(2)
    state.update({"status": "uploaded", "item_id": res["item_id"], "resource_id": res.get("resource_id"), "run_id": run["run_id"]})
    B.save_state(state)
    print(f"Uploaded as UNPUBLISHED draft: item #{res['item_id']}, resource #{res.get('resource_id')}"
          f"{' (replay)' if res.get('replay') else ''}. It now waits for admin review.")


def cmd_report_failed(a):
    run = _run()
    state, spec, report, _ = _final(a.key)
    if state["status"] not in ("failed", "qc_failed"):
        raise SystemExit(f"{a.key} is '{state['status']}', not failed.")
    err = state.get("error_report") or B.error_report(state, report)
    res = _client().report_failed({"run_id": run["run_id"], "idempotency_key": a.key, "meta": spec["meta"], "qc_report": report,
                                   "error_report": err, "spec": {k: v for k, v in spec.items() if not k.startswith("_")},
                                   "correction_attempts": state["correction_attempts"]})
    state.update({"status": "reported", "item_id": res["item_id"]})
    B.save_state(state)
    print(f"Recorded as failed (item #{res['item_id']}); it stays unpublished and appears under Failed in the dashboard.")


def cmd_heartbeat(a):
    _client().heartbeat(_run()["run_id"], " ".join(a.message))
    print("ok")


def cmd_run_finish(a):
    run = _run()
    states = [B.load_state(k)["status"] for k in run.get("keys", [])]
    status = a.status or ("completed" if states and all(s == "uploaded" for s in states)
                          else "failed" if not any(s == "uploaded" for s in states) else "partial")
    msg = a.message or f"{states.count('uploaded')} uploaded, {states.count('reported')} failed, " \
                       f"{len([s for s in states if s not in ('uploaded', 'reported')])} not finished"
    _client().run_finish(run["run_id"], status, msg)
    RUN_FILE.rename(config.OUT / f"run-{run['run_id']}.json")
    print(f"Run #{run['run_id']} finished: {status} — {msg}")


def main(argv=None):
    p = argparse.ArgumentParser(prog="tlgen", description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    sub = p.add_subparsers(dest="cmd", required=True)
    sub.add_parser("check").set_defaults(fn=cmd_check)
    sub.add_parser("status").set_defaults(fn=cmd_status)
    sp = sub.add_parser("plan")
    sp.add_argument("--count", type=int)
    sp.add_argument("--only", action="append", help='restrict to "subject:Grade", repeatable')
    sp.set_defaults(fn=cmd_plan)
    sp = sub.add_parser("run-start")
    sp.add_argument("--trigger", choices=["manual", "scheduled", "retry"], default="manual")
    sp.add_argument("--retry-of", type=int)
    sp.set_defaults(fn=cmd_run_start)
    sp = sub.add_parser("new-spec")
    sp.add_argument("key")
    sp.add_argument("--force", action="store_true")
    sp.set_defaults(fn=cmd_new_spec)
    sp = sub.add_parser("build")
    sp.add_argument("spec")
    sp.add_argument("--offline", action="store_true", help="use the cached catalog instead of the live site")
    sp.set_defaults(fn=cmd_build)
    for name, fn in (("upload", cmd_upload), ("report-failed", cmd_report_failed)):
        sp = sub.add_parser(name)
        sp.add_argument("key")
        sp.set_defaults(fn=fn)
    sp = sub.add_parser("heartbeat")
    sp.add_argument("message", nargs="*")
    sp.set_defaults(fn=cmd_heartbeat)
    sp = sub.add_parser("run-finish")
    sp.add_argument("--status", choices=["completed", "partial", "failed"])
    sp.add_argument("--message")
    sp.set_defaults(fn=cmd_run_finish)
    a = p.parse_args(argv)
    try:
        a.fn(a)
    except api.ApiError as exc:
        if exc.status == 423:
            raise SystemExit("Emergency stop is on in the dashboard. Stopping.")
        raise SystemExit(str(exc))


if __name__ == "__main__":
    main()
