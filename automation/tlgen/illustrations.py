"""Original or openly licensed visuals only.

- Emoji pictures come from Noto Color Emoji (Google; images Apache 2.0, font
  SIL OFL) — free to use commercially.
- Clocks, fraction shapes and rectangles are drawn by this code (original).
Every resource records which sources it used in its licence manifest.
"""
import hashlib
import math

from PIL import Image, ImageDraw, ImageFont

from . import config

LICENSES = {
    "emoji": "Noto Color Emoji by Google — images Apache License 2.0, font SIL Open Font License 1.1",
    "diagram": "Clock/fraction/shape diagrams drawn by the TeachLuma generator (original work)",
    "fonts": "Fredoka and Nunito — SIL Open Font License 1.1",
}

NAVY = (23, 42, 110)
GOLD = (251, 184, 41)


def _cache(name):
    d = config.CACHE / "img"
    d.mkdir(parents=True, exist_ok=True)
    return d / name


def emoji_png(char: str, size: int = 160) -> str:
    """Render one emoji to a transparent PNG (cached)."""
    key = hashlib.md5(f"{char}:{size}".encode()).hexdigest()[:12]
    out = _cache(f"emoji-{key}.png")
    if out.exists():
        return str(out)
    font = ImageFont.truetype(config.EMOJI_FONT, 109)   # Noto Color Emoji is a fixed 109px bitmap font
    img = Image.new("RGBA", (136, 128), (0, 0, 0, 0))
    ImageDraw.Draw(img).text((0, 0), char, font=font, embedded_color=True)
    bbox = img.getbbox() or (0, 0, 136, 128)
    img = img.crop(bbox)
    img.thumbnail((size, size), Image.LANCZOS)
    canvas = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    canvas.paste(img, ((size - img.width) // 2, (size - img.height) // 2), img)
    canvas.save(out)
    return str(out)


def is_emoji(s: str) -> bool:
    return bool(s) and any(ord(c) > 0x2100 for c in s) and len(s) <= 8


# ---------------------------------------------------------------- clocks
def _hand_angles(h, m):
    minute = m * 6
    hour = (h % 12) * 30 + m * 0.5
    return hour, minute


def clock_svg(h, m, size=150, hands=True):
    c, r = size / 2, size / 2 - 6
    parts = [f'<svg xmlns="http://www.w3.org/2000/svg" width="{size}" height="{size}" viewBox="0 0 {size} {size}">',
             f'<circle cx="{c}" cy="{c}" r="{r}" fill="#fff" stroke="#172A6E" stroke-width="4"/>']
    for i in range(60):
        a = math.radians(i * 6)
        r1 = r - (10 if i % 5 == 0 else 5)
        parts.append(f'<line x1="{c + r1 * math.sin(a):.1f}" y1="{c - r1 * math.cos(a):.1f}" x2="{c + (r - 2) * math.sin(a):.1f}" y2="{c - (r - 2) * math.cos(a):.1f}" stroke="#172A6E" stroke-width="{2 if i % 5 == 0 else 1}"/>')
    for n in range(1, 13):
        a = math.radians(n * 30)
        rr = r - size * 0.15
        parts.append(f'<text x="{c + rr * math.sin(a):.1f}" y="{c - rr * math.cos(a) + size * 0.038:.1f}" font-size="{size * 0.105:.0f}" font-family="Nunito, Arial" font-weight="700" text-anchor="middle" fill="#1C2541">{n}</text>')
    if hands:
        ha, ma = _hand_angles(h, m)
        for ang, ln, w, col in ((ha, r * 0.5, 6, "#172A6E"), (ma, r * 0.78, 4, "#F57C2B")):
            a = math.radians(ang)
            parts.append(f'<line x1="{c}" y1="{c}" x2="{c + ln * math.sin(a):.1f}" y2="{c - ln * math.cos(a):.1f}" stroke="{col}" stroke-width="{w}" stroke-linecap="round"/>')
    parts.append(f'<circle cx="{c}" cy="{c}" r="5" fill="#172A6E"/></svg>')
    return "".join(parts)


def clock_png(h, m, size=300, hands=True):
    out = _cache(f"clock-{h}-{m}-{size}-{int(hands)}.png")
    if out.exists():
        return str(out)
    img = Image.new("RGBA", (size, size), (255, 255, 255, 0))
    d = ImageDraw.Draw(img)
    c, r = size / 2, size / 2 - 8
    d.ellipse([c - r, c - r, c + r, c + r], fill="white", outline=NAVY, width=max(3, size // 60))
    font = ImageFont.truetype(config.ttf("nunito-latin-700-normal"), int(size * 0.11))
    for i in range(60):
        a = math.radians(i * 6)
        r1 = r - (size * 0.06 if i % 5 == 0 else size * 0.03)
        d.line([(c + r1 * math.sin(a), c - r1 * math.cos(a)), (c + (r - 3) * math.sin(a), c - (r - 3) * math.cos(a))], fill=NAVY, width=3 if i % 5 == 0 else 1)
    for n in range(1, 13):
        a = math.radians(n * 30)
        rr = r - size * 0.15
        d.text((c + rr * math.sin(a), c - rr * math.cos(a)), str(n), font=font, fill=(28, 37, 65), anchor="mm")
    if hands:
        ha, ma = _hand_angles(h, m)
        for ang, ln, w, col in ((ha, r * 0.5, max(5, size // 40), NAVY), (ma, r * 0.78, max(3, size // 60), (245, 124, 43))):
            a = math.radians(ang)
            d.line([(c, c), (c + ln * math.sin(a), c - ln * math.cos(a))], fill=col, width=w)
    d.ellipse([c - 6, c - 6, c + 6, c + 6], fill=NAVY)
    img.save(out)
    return str(out)


# ---------------------------------------------------------------- fractions / shapes
def fraction_svg(parts, shaded, shape="rect", size=150):
    if shape == "circle":
        c, r = size / 2, size / 2 - 4
        out = [f'<svg xmlns="http://www.w3.org/2000/svg" width="{size}" height="{size}" viewBox="0 0 {size} {size}">']
        for i in range(parts):
            a0, a1 = 2 * math.pi * i / parts - math.pi / 2, 2 * math.pi * (i + 1) / parts - math.pi / 2
            large = 1 if (a1 - a0) > math.pi else 0
            x0, y0, x1, y1 = c + r * math.cos(a0), c + r * math.sin(a0), c + r * math.cos(a1), c + r * math.sin(a1)
            fill = "#FBB829" if i < shaded else "#fff"
            out.append(f'<path d="M{c},{c} L{x0:.1f},{y0:.1f} A{r},{r} 0 {large} 1 {x1:.1f},{y1:.1f} Z" fill="{fill}" stroke="#172A6E" stroke-width="3"/>')
        return "".join(out) + "</svg>"
    w, h = size * 1.4, size * 0.6
    cell = w / parts
    out = [f'<svg xmlns="http://www.w3.org/2000/svg" width="{w:.0f}" height="{h:.0f}" viewBox="0 0 {w:.0f} {h:.0f}">']
    for i in range(parts):
        fill = "#FBB829" if i < shaded else "#fff"
        out.append(f'<rect x="{i * cell + 2:.1f}" y="2" width="{cell - 2:.1f}" height="{h - 4:.1f}" fill="{fill}" stroke="#172A6E" stroke-width="3"/>')
    return "".join(out) + "</svg>"


def fraction_png(parts, shaded, shape="rect", size=300):
    out = _cache(f"frac-{parts}-{shaded}-{shape}-{size}.png")
    if out.exists():
        return str(out)
    if shape == "circle":
        img = Image.new("RGBA", (size, size), (0, 0, 0, 0))
        d = ImageDraw.Draw(img)
        box = [6, 6, size - 6, size - 6]
        for i in range(parts):
            a0, a1 = 360 * i / parts - 90, 360 * (i + 1) / parts - 90
            d.pieslice(box, a0, a1, fill=GOLD if i < shaded else "white", outline=NAVY, width=4)
    else:
        w, h = int(size * 1.4), int(size * 0.6)
        img = Image.new("RGBA", (w, h), (0, 0, 0, 0))
        d = ImageDraw.Draw(img)
        cell = (w - 6) / parts
        for i in range(parts):
            d.rectangle([3 + i * cell, 3, 3 + (i + 1) * cell, h - 3], fill=GOLD if i < shaded else "white", outline=NAVY, width=4)
    img.save(out)
    return str(out)


def rect_svg(l, w, unit=18):
    W, H = l * unit + 4, w * unit + 4
    cells = "".join(f'<line x1="{2 + i * unit}" y1="2" x2="{2 + i * unit}" y2="{H - 2}" stroke="#BFC8E6"/>' for i in range(1, l)) + \
            "".join(f'<line x1="2" y1="{2 + j * unit}" x2="{W - 2}" y2="{2 + j * unit}" stroke="#BFC8E6"/>' for j in range(1, w))
    return f'<svg xmlns="http://www.w3.org/2000/svg" width="{W}" height="{H}"><rect x="2" y="2" width="{l * unit}" height="{w * unit}" fill="#E6EEFF" stroke="#172A6E" stroke-width="3"/>{cells}</svg>'


# ---------------------------------------------------------------- base-ten blocks
def _base10_parts(h, t, o, u):
    """Rectangles (x, y, w, h, kind) for hundreds flats, tens rods and ones cubes."""
    out, x = [], 0
    for _ in range(h):
        out.append((x, 0, 10 * u, 10 * u, "flat"))
        x += 10 * u + u
    for _ in range(t):
        out.append((x, 0, u, 10 * u, "rod"))
        x += u + u * 0.6
    x += u * 0.4
    for i in range(o):
        out.append((x + (i % 2) * (u * 1.3), 10 * u - (i // 2 + 1) * (u * 1.3), u, u, "cube"))
    width = x + (2 * u * 1.3 if o > 1 else (u * 1.3 if o else 0))
    return out, width, 10 * u


def base10_svg(h, t, o, unit=6):
    parts, w, hgt = _base10_parts(h, t, o, unit)
    fill = {"flat": "#FFE3C4", "rod": "#FFD08A", "cube": "#FBB829"}
    svg = [f'<svg xmlns="http://www.w3.org/2000/svg" width="{w + 4:.0f}" height="{hgt + 4:.0f}" viewBox="-2 -2 {w + 4:.0f} {hgt + 4:.0f}">']
    for x, y, pw, ph, kind in parts:
        svg.append(f'<rect x="{x:.1f}" y="{y:.1f}" width="{pw:.1f}" height="{ph:.1f}" fill="{fill[kind]}" stroke="#AE4E0B" stroke-width="1.2"/>')
        if kind in ("flat", "rod"):
            for k in range(1, 10):
                yy = y + k * unit
                svg.append(f'<line x1="{x:.1f}" y1="{yy:.1f}" x2="{x + pw:.1f}" y2="{yy:.1f}" stroke="#E0A060" stroke-width=".6"/>')
        if kind == "flat":
            for k in range(1, 10):
                xx = x + k * unit
                svg.append(f'<line x1="{xx:.1f}" y1="{y:.1f}" x2="{xx:.1f}" y2="{y + ph:.1f}" stroke="#E0A060" stroke-width=".6"/>')
    return "".join(svg) + "</svg>"


def base10_png(h, t, o, unit=14):
    out = _cache(f"base10-{h}-{t}-{o}-{unit}.png")
    if out.exists():
        return str(out)
    parts, w, hgt = _base10_parts(h, t, o, unit)
    img = Image.new("RGBA", (int(w) + 6, int(hgt) + 6), (0, 0, 0, 0))
    d = ImageDraw.Draw(img)
    fill = {"flat": (255, 227, 196), "rod": (255, 208, 138), "cube": GOLD}
    for x, y, pw, ph, kind in parts:
        x, y = x + 3, y + 3
        d.rectangle([x, y, x + pw, y + ph], fill=fill[kind], outline=(174, 78, 11), width=2)
        if kind in ("flat", "rod"):
            for k in range(1, 10):
                d.line([(x, y + k * unit), (x + pw, y + k * unit)], fill=(224, 160, 96), width=1)
        if kind == "flat":
            for k in range(1, 10):
                d.line([(x + k * unit, y), (x + k * unit, y + ph)], fill=(224, 160, 96), width=1)
    img.save(out)
    return str(out)
