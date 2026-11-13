"""Vector emoji: each emoji as a standalone SVG, from Noto Color Emoji.

Why: the system emoji font (NotoColorEmoji.ttf) stores every emoji as a
136-pixel bitmap, so a large picture (a flashcard prints its emoji about
5 cm tall) comes out blurred and blocky. The same Noto artwork is also
published as a vector web font (@fontsource/noto-color-emoji, OFL-1.1),
whose OpenType "SVG " table holds one SVG drawing per emoji. This module
indexes those drawings and returns them as standalone SVGs:

  * render_html inlines them, so PDFs carry plain vector paths that print
    sharply at any size and look the same in every PDF viewer (embedding
    the colour font itself is not an option: most viewers can't draw it);
  * png() rasterizes them with cairosvg at any size, for the Word and
    PowerPoint files and the thumbnails.

The font package is fetched into .cache/ by bootstrap.sh (npm). When it is
missing, available() is False and callers fall back to the bitmap font, so
nothing breaks.
"""
import hashlib
import json
import re
from functools import lru_cache
from pathlib import Path

from . import config

PACKAGE = config.CACHE / "noto-color-emoji" / "package"
INDEX = config.CACHE / "noto-color-emoji-index.json"
VS16, ZWJ = "️", "‍"


def available() -> bool:
    return (PACKAGE / "files").is_dir()


@lru_cache(maxsize=1)
def _index():
    """{emoji sequence: [font file, glyph id, advance]} built once and cached on disk."""
    if INDEX.exists():
        return json.loads(INDEX.read_text())
    from fontTools.ttLib import TTFont
    index = {}
    for path in sorted((PACKAGE / "files").glob("*.woff2")):
        font = TTFont(str(path))
        if "SVG " not in font:
            continue
        with_svg = set()
        for doc in font["SVG "].docList:
            with_svg.update(range(doc.startGlyphID, doc.endGlyphID + 1))
        cmap = font.getBestCmap()
        names = {name: chr(cp) for cp, name in cmap.items()}

        def add(seq, glyph):
            gid = font.getGlyphID(glyph)
            if gid in with_svg and seq not in index:
                index[seq] = [path.name, gid, font["hmtx"][glyph][0]]

        for cp, glyph in cmap.items():
            add(chr(cp), glyph)
        # Multi-character emoji (flags, keycaps, ZWJ sequences, skin tones)
        # are ligatures in the GSUB table.
        for lookup in font["GSUB"].table.LookupList.Lookup if "GSUB" in font else []:
            for sub in lookup.SubTable:
                sub = getattr(sub, "ExtSubTable", sub)
                for first, ligs in (getattr(sub, "ligatures", None) or {}).items():
                    for lig in ligs:
                        parts = [first, *lig.Component]
                        if all(p in names for p in parts):
                            add("".join(names[p] for p in parts), lig.LigGlyph)
    INDEX.write_text(json.dumps(index, ensure_ascii=False))
    return index


def _lookup(text):
    index = _index()
    for candidate in (text, text.replace(VS16, ""), text.rstrip(VS16)):
        if candidate in index:
            return index[candidate]
    return None


@lru_cache(maxsize=4096)
def svg(text: str) -> str | None:
    """A standalone SVG for one emoji (None if unknown or the font is missing)."""
    if not available() or not text:
        return None
    hit = _lookup(text)
    if not hit:
        return None
    fname, gid, advance = hit
    font = _font(fname)
    for doc in font["SVG "].docList:
        if doc.startGlyphID <= gid <= doc.endGlyphID:
            body = _extract(doc.data, f"glyph{gid}")
            if body is None:
                return None
            # Glyph coordinates are font units with the baseline at y=0 and y
            # growing downwards; ids get a per-emoji prefix so several emoji
            # inlined in one page never clash.
            uid = "e" + hashlib.md5(text.encode()).hexdigest()[:8]
            body = re.sub(r'id="([^"]+)"', lambda m: f'id="{uid}-{m.group(1)}"', body)
            body = re.sub(r'(url\(#|href="#)([^)"]+)', lambda m: f"{m.group(1)}{uid}-{m.group(2)}", body)
            return (f'<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" '
                    f'viewBox="0 -950 {advance} 1200" preserveAspectRatio="xMidYMid meet">{body}</svg>')
    return None


def _extract(doc: str, glyph_id: str) -> str | None:
    """Just one glyph from an SVG document that may hold hundreds, plus the
    gradients/paths it references (recursively) — so a page carries only
    the artwork it shows."""
    import xml.etree.ElementTree as ET
    ns = "{http://www.w3.org/2000/svg}"
    ET.register_namespace("", "http://www.w3.org/2000/svg")
    ET.register_namespace("xlink", "http://www.w3.org/1999/xlink")
    root = ET.fromstring(doc)
    by_id = {el.get("id"): el for el in root.iter() if el.get("id")}
    glyph = by_id.get(glyph_id)
    if glyph is None:
        return None
    refs = re.compile(r'url\(#([^)]+)\)|href="#([^"]+)"')

    def deps(el, seen):
        for a, b in refs.findall(ET.tostring(el, encoding="unicode")):
            ref = a or b
            if ref and ref not in seen and ref in by_id and by_id[ref] is not el:
                seen.add(ref)
                deps(by_id[ref], seen)
        return seen

    needed = deps(glyph, set())
    defs = "".join(ET.tostring(by_id[i], encoding="unicode") for i in sorted(needed)
                   if not _contains(glyph, by_id[i]))
    body = ET.tostring(glyph, encoding="unicode")
    out = (f"<defs>{defs}</defs>" if defs else "") + body
    return out.replace(f"ns0:", "").replace(' xmlns:ns0="http://www.w3.org/2000/svg"', "").replace(ns, "")


def _contains(parent, el):
    return any(x is el for x in parent.iter())


@lru_cache(maxsize=16)
def _font(fname):
    from fontTools.ttLib import TTFont
    return TTFont(str(PACKAGE / "files" / fname))


def png(text: str, size: int) -> str | None:
    """A transparent square PNG of the emoji, size x size, rasterized from the vector (cached)."""
    data = svg(text)
    if data is None:
        return None
    key = hashlib.md5(f"vec:{text}:{size}".encode()).hexdigest()[:12]
    out = config.CACHE / "img" / f"emoji-vec-{key}.png"
    if not out.exists():
        import cairosvg
        from PIL import Image
        out.parent.mkdir(parents=True, exist_ok=True)
        cairosvg.svg2png(bytestring=data.encode(), write_to=str(out), output_width=size * 2, output_height=size * 2)
        with Image.open(out) as im:      # trim the transparent margin, then centre on a square canvas
            im = im.convert("RGBA")
            box = im.getbbox()
            if box:
                im = im.crop(box)
            im.thumbnail((size, size), Image.LANCZOS)
            canvas = Image.new("RGBA", (size, size), (0, 0, 0, 0))
            canvas.paste(im, ((size - im.width) // 2, (size - im.height) // 2), im)
            canvas.save(out)
    return str(out)
