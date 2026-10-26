"""Chromium (headless) helpers: HTML → PDF and HTML → PNG screenshot."""
import subprocess
import tempfile
from pathlib import Path

from . import config

FLAGS = ["--headless", "--no-sandbox", "--disable-gpu", "--hide-scrollbars", "--no-first-run",
         "--disable-extensions", "--allow-file-access-from-files", "--virtual-time-budget=8000",
         # Offline rendering: no update checks, sync or telemetry.
         "--disable-background-networking", "--disable-component-update", "--disable-sync", "--disable-domain-reliability",
         "--no-default-browser-check", "--metrics-recording-only", "--disable-breakpad"]


def _run(args, out: Path):
    out.unlink(missing_ok=True)
    with tempfile.TemporaryDirectory() as profile:
        proc = subprocess.run([config.chromium(), *FLAGS, f"--user-data-dir={profile}", *args],
                              capture_output=True, text=True, timeout=180)
    if not out.exists() or out.stat().st_size == 0:
        raise RuntimeError(f"Chromium did not produce {out.name}: {proc.stderr[-800:]}")


def html_to_pdf(html: str, out: Path) -> Path:
    out = Path(out)
    src = out.with_suffix(".src.html")
    src.write_text(html, encoding="utf-8")
    _run(["--no-pdf-header-footer", f"--print-to-pdf={out}", src.as_uri()], out)
    src.unlink()
    return out


def screenshot(html: str, out: Path, width: int, height: int) -> Path:
    out = Path(out)
    src = out.with_suffix(".src.html")
    src.write_text(html, encoding="utf-8")
    # Headless Chromium's viewport is shorter than --window-size, so render
    # taller and crop to the exact size.
    _run([f"--window-size={width},{height + 200}", "--force-device-scale-factor=1", f"--screenshot={out}", src.as_uri()], out)
    src.unlink()
    from PIL import Image
    with Image.open(out) as im:
        im.convert("RGB").crop((0, 0, width, height)).save(out)
    return out


def dump_dom(html: str, work: Path) -> str:
    """The page's DOM after scripts ran (used to read layout checks back)."""
    work = Path(work)
    src = work.with_suffix(".src.html")
    src.write_text(html, encoding="utf-8")
    with tempfile.TemporaryDirectory() as profile:
        proc = subprocess.run([config.chromium(), *FLAGS, f"--user-data-dir={profile}", "--window-size=1200,1100",
                               "--dump-dom", src.as_uri()], capture_output=True, text=True, timeout=180)
    src.unlink()
    return proc.stdout
