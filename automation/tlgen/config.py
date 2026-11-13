"""Paths, environment and brand constants for the generator."""
import glob
import os
import shutil
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent          # automation/
REPO = ROOT.parent                                     # site checkout
DATA = ROOT / "tlgen" / "data"
OUT = Path(os.environ.get("TLGEN_OUT", ROOT / "out"))
CACHE = ROOT / ".cache"

API_BASE = os.environ.get("TEACHLUMA_API_BASE", "https://teachluma.com").rstrip("/")
API_TOKEN = os.environ.get("TEACHLUMA_API_TOKEN", "")

MAX_CORRECTIONS = 3

# Brand colours (logo, cover band, thumbnails) stay TeachLuma navy + gold.
BRAND = {
    "navy": "#172A6E", "navy_dark": "#0F1D52", "gold": "#FBB829", "teal": "#1FB5A8",
    "cream": "#FFF8EC", "ink": "#2B2F36", "muted": "#5F6673",
}
# Printed-page palette: warm and calm rather than primary colours.
#   main  - borders, rules, accents and diagram strokes (3:1+ on white)
#   ink   - text and filled badges with white text (4.8:1+, WCAG AA)
#   soft  - light tints for boxes and table stripes
# Subject hues keep their identity (ESL blue, Math orange, Science green)
# as a warm slate blue, a muted coral and a soft sage.
SUBJECT_COLORS = {
    "esl": {"main": "#4A6FA5", "ink": "#355C8F", "soft": "#E9EFF7", "name": "ESL"},
    "math": {"main": "#D9775C", "ink": "#A4502F", "soft": "#FBECE6", "name": "Math"},
    "science": {"main": "#5E9C76", "ink": "#3D7453", "soft": "#E7F2EB", "name": "Science"},
}
PALETTE = {
    "heading": "#2E3D55",            # deep warm slate: page headings, table headers
    "text": "#2B2F36",               # charcoal body text
    "muted": "#5F6673",
    "line": "#E3E0DA",               # warm grey rules
    "stripe": "#FAF8F5",             # warm off-white table stripe
    "tip": "#E2A93B", "tip_soft": "#FDF4E1", "tip_ink": "#8A5A00",          # teacher tips (amber)
    "note": "#C9605B", "note_soft": "#FBEDEC", "note_ink": "#A8433F",       # important notes (rose)
}

FONT_DIR = REPO / "assets" / "fonts"
LOGO_SVG = REPO / "assets" / "brand" / "teachluma-logo.svg"
LOGO_WHITE_SVG = REPO / "assets" / "brand" / "teachluma-logo-white.svg"
EMOJI_FONT = next(iter(glob.glob("/usr/share/fonts/**/NotoColorEmoji.ttf", recursive=True)), "")


def chromium() -> str:
    """Path to a headless-capable Chromium/Chrome binary."""
    env = os.environ.get("TLGEN_CHROMIUM")
    if env:
        return env
    # The headless shell prints PDFs reliably; the full browser can hang in some containers.
    for pattern in ("/opt/pw-browsers/chromium_headless_shell-*/chrome-headless-shell-linux*/chrome-headless-shell",
                    "/opt/pw-browsers/chromium-*/chrome-linux*/chrome",
                    "/root/.cache/ms-playwright/chromium_headless_shell-*/chrome-headless-shell-linux*/chrome-headless-shell",
                    "/root/.cache/ms-playwright/chromium-*/chrome-linux*/chrome"):
        hits = sorted(glob.glob(pattern))
        if hits:
            return hits[-1]
    for name in ("chromium", "chromium-browser", "google-chrome"):
        if shutil.which(name):
            return shutil.which(name)
    raise RuntimeError("No Chromium found; set TLGEN_CHROMIUM.")


def soffice() -> str:
    path = shutil.which("soffice") or shutil.which("libreoffice")
    if not path:
        raise RuntimeError("LibreOffice (soffice) is required.")
    return path


def ttf(name: str) -> str:
    """A TTF copy of a site woff2 font (Pillow can't read woff2), cached."""
    CACHE.mkdir(parents=True, exist_ok=True)
    out = CACHE / f"{name}.ttf"
    if not out.exists():
        from fontTools.ttLib import TTFont
        font = TTFont(str(FONT_DIR / f"{name}.woff2"))
        font.flavor = None
        font.save(str(out))
    return str(out)
