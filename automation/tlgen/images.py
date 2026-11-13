"""Thumbnail (1200x900, 4:3 like the site's card frame) and preview (1200x900) JPEGs."""
import subprocess
import tempfile
from pathlib import Path

from PIL import Image, ImageDraw, ImageFilter, ImageFont

from . import config, render_html, render_pdf, spec as S

THUMB = (1200, 900)     # 4:3, the shape of the resource-card frame on the site
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


def thumbnail_fits(spec, work: Path) -> bool:
    """True when the thumbnail title fits its safe area (read back from Chromium)."""
    dom = render_pdf.dump_dom(render_html.thumb_card(spec, *THUMB), Path(work))
    return 'data-fit="ok"' in dom


def first_student_page(pdf: Path, page_count: int) -> int:
    """1-based number of the first student page (worksheet/quiz/homework all
    start with a "Name:" line); page 3 if it can't be found."""
    try:
        from pypdf import PdfReader
        reader = PdfReader(str(pdf))
        for i in range(1, min(page_count, len(reader.pages))):
            if "Name:" in (reader.pages[i].extract_text() or ""):
                return i + 1
    except Exception:
        pass
    return min(3, page_count)


def _mock_pages(main_pdf, page_count):
    """Two real pages for the thumbnail mockup: the first student page in
    front, the lesson plan behind (falls back to whatever exists)."""
    picks = [p for p in (first_student_page(main_pdf, page_count), 2) if p <= page_count] or [1]
    out = []
    for p, im in zip(picks, page_images(main_pdf, picks, dpi=110)):
        f = Path(main_pdf).with_name(f".thumb-page-{p}.png")
        im.save(f)
        out.append(f)
    return out


def thumbnail(spec, out: Path, main_pdf: Path = None, page_count: int = 0) -> Path:
    """The catalog card. With the main PDF, the art area shows a mockup of
    real pages from the pack; without it, the cover emoji (as before)."""
    png = Path(out).with_suffix(".png")
    pages = _mock_pages(main_pdf, page_count) if main_pdf and page_count else None
    try:
        render_pdf.screenshot(render_html.thumb_card(spec, *THUMB, pages=pages), png, *THUMB)
    finally:
        for f in pages or []:
            Path(f).unlink(missing_ok=True)
    with Image.open(png) as im:
        im.convert("RGB").save(out, "JPEG", quality=88, optimize=True)
    png.unlink()
    return Path(out)


def _rounded(im, radius, border=0, border_color=(227, 224, 218)):
    """A page image with rounded corners and a thin border, as RGBA."""
    w, h = im.size
    card = Image.new("RGBA", (w + 2 * border, h + 2 * border), border_color + (255,))
    card.paste(im.convert("RGBA"), (border, border))
    mask = Image.new("L", card.size, 0)
    ImageDraw.Draw(mask).rounded_rectangle([0, 0, card.width - 1, card.height - 1], radius=radius, fill=255)
    card.putalpha(mask)
    return card


def _drop(bg, card, x, y, layers=((3, 2, 46), (14, 12, 60), (34, 26, 40))):
    """Paste card with a soft, layered drop shadow (offset, blur, opacity)."""
    for off, blur, alpha in layers:
        sh = Image.new("RGBA", card.size, (0, 0, 0, 0))
        sh.paste((15, 23, 52, alpha), mask=card.split()[3])
        pad = blur * 3
        big = Image.new("RGBA", (card.width + 2 * pad, card.height + 2 * pad), (0, 0, 0, 0))
        big.paste(sh, (pad, pad))
        big = big.filter(ImageFilter.GaussianBlur(blur))
        bg.alpha_composite(big, (x - pad, y - pad + off))
    bg.alpha_composite(card, (x, y))


def _pill(d, x, y, text, font, fill, color):
    w = int(d.textlength(text, font=font)) + 36
    d.rounded_rectangle([x, y, x + w, y + 44], radius=22, fill=fill)
    d.text((x + 18, y + 22), text, font=font, fill=color, anchor="lm")
    return x + w + 12


def preview(spec, main_pdf: Path, page_count: int, out: Path) -> Path:
    """Three real pages from the pack as a presentation mockup: the first
    student page in front on a small stack of sheets, the cover and lesson
    plan fanned behind it, each with rounded corners, a hairline border and
    a soft layered shadow."""
    m = spec["meta"]
    sc = config.SUBJECT_COLORS[m["subject"]]
    # Cover, lesson plan, first student page — what a teacher wants to see.
    picks = [p for p in (1, 2, first_student_page(main_pdf, page_count)) if p <= page_count]
    pages = page_images(main_pdf, picks, dpi=96)
    bg = Image.new("RGBA", PREVIEW, config.BRAND["cream"])
    d = ImageDraw.Draw(bg)
    d.ellipse([-260, 520, 420, 1200], fill=sc["soft"])
    d.ellipse([880, -220, 1440, 300], fill=sc["soft"])
    for gx in range(30, PREVIEW[0], 34):              # faint dot grid, like a desk mat
        for gy in range(160, PREVIEW[1], 34):
            d.ellipse([gx - 1.4, gy - 1.4, gx + 1.4, gy + 1.4], fill=(23, 42, 110, 18))
    font = ImageFont.truetype(config.ttf("fredoka-latin-700-normal"), 40)
    small = ImageFont.truetype(config.ttf("nunito-latin-800-normal"), 22)
    d.text((60, 40), m["title"][:58] + ("…" if len(m["title"]) > 58 else ""), font=font, fill=config.BRAND["navy"])
    x = 60
    for text, fill, color in ((sc["name"], sc["ink"], "#FFFFFF"), (S.grade_display(m["grade"]), "#FFFFFF", sc["ink"]),
                              ("PRO" if m["tier"] == "pro" else "FREE", config.BRAND["gold"] if m["tier"] == "pro" else "#1FB5A8",
                               config.BRAND["navy"] if m["tier"] == "pro" else "#FFFFFF"),
                              (f"{page_count} pages + editable files", "#FFFFFF", config.PALETTE["text"])):
        x = _pill(d, x, 98, text, small, fill, color)
    h = 610
    cards = [_rounded(im.resize((int(im.width * h / im.height), h), Image.LANCZOS), 12, 3) for im in pages]
    if len(cards) == 3:
        back = [(cards[0], 70, 205, -6), (cards[1], 690, 205, 6)]
        front = (cards[2], 365, 172, 0)
    else:
        back = []
        front = None
        for i, cd in enumerate(cards):
            _drop(bg, cd, 230 + 380 * i, 180)
    for cd, cx, cy, ang in back:
        rot = cd.rotate(ang, expand=True, resample=Image.BICUBIC)
        _drop(bg, rot, cx, cy)
    if front:
        cd, cx, cy, _ = front
        blank = Image.new("RGB", (cd.width - 6, cd.height - 6), "white")
        for k, (dx, dy, ang) in enumerate(((16, 14, 2.2), (8, 7, -1.4))):   # a small stack of sheets
            sheet = _rounded(blank, 12, 3).rotate(ang, expand=True, resample=Image.BICUBIC)
            _drop(bg, sheet, cx + dx - 6, cy + dy - 6, layers=((2, 3, 30), (10, 10, 30)))
        _drop(bg, cd, cx, cy)
    bg.convert("RGB").save(out, "JPEG", quality=88, optimize=True)
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
