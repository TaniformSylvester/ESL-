"""Thumbnail (1200x675) and preview (1200x900) JPEGs for the resource page."""
import subprocess
import tempfile
from pathlib import Path

from PIL import Image, ImageDraw, ImageFilter, ImageFont

from . import config, render_html, render_pdf

THUMB = (1200, 675)
PREVIEW = (1200, 900)


def page_images(pdf: Path, pages, dpi=70):
    """Render selected 1-based pages of a PDF to PIL images."""
    out = []
    with tempfile.TemporaryDirectory() as tmp:
        for p in pages:
            stem = Path(tmp) / f"p{p}"
            subprocess.run(["pdftoppm", "-r", str(dpi), "-png", "-f", str(p), "-l", str(p), "-singlefile", str(pdf), str(stem)],
                           check=True, capture_output=True, timeout=120)
            with Image.open(f"{stem}.png") as im:
                out.append(im.convert("RGB"))
    return out


def thumbnail(spec, out: Path) -> Path:
    png = Path(out).with_suffix(".png")
    render_pdf.screenshot(render_html.thumb_card(spec, *THUMB), png, *THUMB)
    with Image.open(png) as im:
        im.convert("RGB").save(out, "JPEG", quality=88, optimize=True)
    png.unlink()
    return Path(out)


def preview(spec, main_pdf: Path, page_count: int, out: Path) -> Path:
    """Three real pages from the pack, fanned on a brand background."""
    m = spec["meta"]
    sc = config.SUBJECT_COLORS[m["subject"]]
    # Cover, lesson plan, first student page (page 3) — what a teacher wants to see.
    picks = [p for p in (1, 2, 3) if p <= page_count]
    pages = page_images(main_pdf, picks)
    bg = Image.new("RGB", PREVIEW, config.BRAND["cream"])
    d = ImageDraw.Draw(bg)
    d.ellipse([-260, 520, 420, 1200], fill=sc["soft"])
    d.ellipse([880, -220, 1440, 300], fill=sc["soft"])
    font = ImageFont.truetype(config.ttf("fredoka-latin-700-normal"), 40)
    small = ImageFont.truetype(config.ttf("nunito-latin-800-normal"), 24)
    d.text((60, 44), m["title"][:58] + ("…" if len(m["title"]) > 58 else ""), font=font, fill=config.BRAND["navy"])
    d.text((60, 98), f"{m['grade']} · {sc['name']} · {'PRO' if m['tier'] == 'pro' else 'FREE'} · {page_count} pages + editable files",
           font=small, fill=sc["ink"])
    h = 640
    slots = [(40, 190, -5), (365, 175, 0), (675, 190, 5)] if len(pages) == 3 else [(250 + 360 * i, 175, 0) for i in range(len(pages))]
    for im, (x, y, angle) in zip(pages, slots):
        im = im.resize((int(im.width * h / im.height), h), Image.LANCZOS)
        framed = Image.new("RGBA", (im.width + 8, im.height + 8), (255, 255, 255, 255))
        framed.paste(im, (4, 4))
        rot = framed.rotate(angle, expand=True, resample=Image.BICUBIC)
        shadow = Image.new("RGBA", rot.size, (0, 0, 0, 0))
        shadow.paste((23, 42, 110, 70), mask=rot.split()[3])
        shadow = shadow.filter(ImageFilter.GaussianBlur(10))
        bg.paste(shadow, (x + 10, y + 14), shadow)
        bg.paste(rot, (x, y), rot)
    bg.save(out, "JPEG", quality=86, optimize=True)
    return Path(out)


def blank_pages(pdf: Path, page_count: int, threshold=0.995):
    """1-based numbers of pages that are (almost) entirely white."""
    blanks = []
    with tempfile.TemporaryDirectory() as tmp:
        subprocess.run(["pdftoppm", "-r", "20", "-gray", "-png", str(pdf), f"{tmp}/p"], check=True, capture_output=True, timeout=180)
        files = sorted(Path(tmp).glob("p*.png"), key=lambda f: int(f.stem.split("-")[-1]))
        for i, f in enumerate(files[:page_count]):
            with Image.open(f) as im:
                hist = im.convert("L").histogram()
            white = sum(hist[245:]) / max(1, sum(hist))
            if white >= threshold:
                blanks.append(i + 1)
    return blanks


def visual_stats(path: Path):
    """(width, height, fraction of near-white pixels) for an image."""
    with Image.open(path) as im:
        w, h = im.size
        hist = im.convert("L").histogram()
    return w, h, sum(hist[245:]) / max(1, sum(hist))
