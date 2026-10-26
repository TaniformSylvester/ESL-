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

BRAND = {
    "navy": "#172A6E", "navy_dark": "#0F1D52", "gold": "#FBB829", "teal": "#1FB5A8",
    "cream": "#FFF8EC", "ink": "#1C2541", "muted": "#5B6478",
}
SUBJECT_COLORS = {
    "esl": {"main": "#2F6FE4", "ink": "#1D4FB8", "soft": "#E6EEFF", "name": "ESL"},
    "math": {"main": "#F57C2B", "ink": "#AE4E0B", "soft": "#FFEBDA", "name": "Math"},
    "science": {"main": "#2EA45B", "ink": "#1B7A40", "soft": "#E1F5E8", "name": "Science"},
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
    for pattern in ("/opt/pw-browsers/chromium-*/chrome-linux/chrome", "/root/.cache/ms-playwright/chromium-*/chrome-linux/chrome"):
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
