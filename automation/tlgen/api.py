"""Client for /api/automation.php (token auth, retries, clear errors)."""
import json
import time
from pathlib import Path

import requests

from . import config


class ApiError(RuntimeError):
    def __init__(self, status, body):
        self.status = status
        self.body = body
        msg = body.get("error") if isinstance(body, dict) else str(body)
        if isinstance(body, dict) and body.get("errors"):
            msg = "; ".join(map(str, body["errors"]))
        super().__init__(f"HTTP {status}: {msg}")


class Client:
    def __init__(self, base=None, token=None):
        self.base = (base or config.API_BASE).rstrip("/")
        self.token = token or config.API_TOKEN
        self.session = requests.Session()
        self.session.headers["User-Agent"] = "TeachLuma-Generator/1.0"
        if self.token:
            # Both headers: some hosts strip Authorization before PHP sees it.
            self.session.headers.update({"Authorization": f"Bearer {self.token}", "X-TeachLuma-Token": self.token})
        # Without TEACHLUMA_API_TOKEN, the token is expected to be a network
        # secret: the session's egress proxy adds the headers, so the token
        # never exists inside the session. The site answers 401 if it doesn't.

    def _call(self, method, action, *, json_body=None, data=None, files=None, retries=3):
        url = f"{self.base}/api/automation.php?action={action}"
        for attempt in range(retries + 1):
            try:
                r = self.session.request(method, url, json=json_body, data=data, files=files, timeout=180)
            except requests.RequestException as exc:
                if attempt == retries:
                    raise ApiError(0, {"error": f"network error: {exc}"})
                time.sleep(2 ** (attempt + 1))
                continue
            try:
                body = r.json()
            except ValueError:
                body = {"error": r.text[:300]}
            # Retry only transient failures; 4xx answers are final.
            if r.status_code in (502, 503, 504) and attempt < retries:
                time.sleep(2 ** (attempt + 1))
                continue
            if r.status_code == 429 and attempt < retries:
                time.sleep(30)
                continue
            if r.status_code >= 400:
                raise ApiError(r.status_code, body)
            return body
        raise ApiError(0, {"error": "unreachable"})

    def status(self):
        return self._call("GET", "status")

    def taxonomy(self):
        return self._call("GET", "taxonomy")

    def catalog(self):
        return self._call("GET", "catalog")

    def work_queue(self):
        return self._call("GET", "work_queue")

    def duplicate_check(self, subject, grade, title, topic, file_hash=None):
        return self._call("POST", "duplicate_check", json_body={"subject": subject, "grade": grade, "title": title, "topic": topic, "file_hash": file_hash})

    def run_start(self, trigger, plan, retry_of=None):
        return self._call("POST", "run_start", json_body={"trigger": trigger, "plan": plan, "retry_of": retry_of})

    def heartbeat(self, run_id, message=""):
        return self._call("POST", "run_heartbeat", json_body={"run_id": run_id, "message": message}, retries=1)

    def run_finish(self, run_id, status, message):
        return self._call("POST", "run_finish", json_body={"run_id": run_id, "status": status, "message": message})

    def report_failed(self, payload):
        return self._call("POST", "report_failed", json_body=payload)

    def upload(self, run_id, key, meta, qc, social, spec, files: dict):
        data = {"run_id": str(run_id), "idempotency_key": key, "meta": json.dumps(meta), "qc_report": json.dumps(qc),
                "social": json.dumps(social), "spec": json.dumps(spec)}
        # File contents are read into memory (each file is at most a few MB),
        # so a retried request re-sends complete files rather than spent handles.
        payload = {field: (Path(path).name, Path(path).read_bytes()) for field, path in files.items()}
        return self._call("POST", "upload", data=data, files=payload, retries=2)
