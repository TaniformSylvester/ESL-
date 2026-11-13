"""Geometry and coordinate diagrams for math questions, drawn with matplotlib.

One drawing function per figure gives both outputs from the same geometry:
  * svg(item)  -> inline SVG (text converted to paths) for the PDFs: vector,
                  crisp at any zoom, no font needed;
  * png(item)  -> a PNG of the same figure for the Word and PowerPoint files.

Each figure shows only what the question states (the given lengths and
angles), with the unknown marked "?" where that helps, and is drawn to
scale. Questions whose point is to reason from the numbers alone (classify
a triangle from its angles, compare two fractions) deliberately get no
figure, because an accurate drawing would give the answer away.

The figure is chosen from the item's generator and params, so a spec built
before this module existed gets the same diagrams when it is re-rendered.
"""
import hashlib
import io
import math
import re
from functools import lru_cache

from . import config

STROKE = config.PALETTE["heading"]       # outlines
FILL = "#EEF2F7"                         # shape fill (soft slate)
ACCENT = "#C4613F"                       # measurements, mirror lines, "?" (coral ink)
POINT = config.SUBJECT_COLORS["esl"]["ink"]
GRID = "#D9DEE6"
TEXT = config.PALETTE["text"]

SUPPORTED = {"area_tri", "area_para", "area_trap", "angle_missing", "circle", "perimeter_quad",
             "volume_prism", "surface_area", "reflect_point", "translate_point"}


def supports(item) -> bool:
    return isinstance(item, dict) and item.get("gen") in SUPPORTED and isinstance(item.get("params"), dict)


def _plt():
    import matplotlib
    matplotlib.use("Agg")
    import matplotlib.pyplot as plt
    from matplotlib import font_manager
    try:
        path = config.ttf("nunito-latin-700-normal")
        font_manager.fontManager.addfont(path)
        family = font_manager.FontProperties(fname=path).get_name()
    except Exception:
        family = "DejaVu Sans"
    plt.rcParams.update({"font.family": family, "font.size": 9, "svg.fonttype": "path",
                         "svg.hashsalt": "teachluma", "path.simplify": False})
    return plt


def _fig(w_in, h_in):
    plt = _plt()
    fig, ax = plt.subplots(figsize=(w_in, h_in))
    ax.set_aspect("equal")
    ax.axis("off")
    fig.subplots_adjust(0, 0, 1, 1)
    return plt, fig, ax


def _label(ax, x, y, text, color=TEXT, size=9, bg=True, **kw):
    """A label; measurement labels get a soft white backing so they stay readable where they cross a line."""
    if bg and "bbox" not in kw:
        kw["bbox"] = dict(boxstyle="round,pad=0.12", fc="white", ec="none", alpha=0.9)
    ax.text(x, y, text, color=color, fontsize=size, ha=kw.pop("ha", "center"), va=kw.pop("va", "center"), zorder=6, **kw)


def _right_angle(ax, x, y, dx, dy, s):
    """Small square marking a right angle at (x, y) between directions (dx,0) and (0,dy)."""
    ax.plot([x + dx * s, x + dx * s, x], [y, y + dy * s, y + dy * s], color=STROKE, lw=1.1)


def _fit(b, h, lo=0.45, hi=1.6):
    """Draw b:h to scale but keep very flat or very tall shapes readable."""
    ratio = max(lo, min(hi, h / b))
    return 4.0, 4.0 * ratio


# ---------------------------------------------------------------- figures
def _area_tri(ax, p):
    b, h = p["b"], p["h"]
    W, H = _fit(b, h)
    apex = (W * 0.35, H)
    ax.fill([0, W, apex[0]], [0, 0, H], color=FILL, zorder=1)
    ax.plot([0, W, apex[0], 0], [0, 0, H, 0], color=STROKE, lw=1.5, zorder=2)
    ax.plot([apex[0], apex[0]], [0, H], color=ACCENT, lw=1.6, ls=(0, (4, 3)), zorder=3)
    _right_angle(ax, apex[0], 0, 1, 1, 0.22)
    _label(ax, W / 2, -0.32, f"{b} cm")
    _label(ax, apex[0] + 0.12, H / 2, f"{h} cm", color=ACCENT, ha="left")
    return (-0.3, W + 0.3), (-0.6, H + 0.25)


def _area_para(ax, p):
    b, h = p["b"], p["h"]
    W, H = _fit(b, h)
    s = min(1.3, W * 0.3)
    xs, ys = [0, W, W + s, s, 0], [0, 0, H, H, 0]
    ax.fill(xs, ys, color=FILL, zorder=1)
    ax.plot(xs, ys, color=STROKE, lw=1.5, zorder=2)
    ax.plot([s, s], [0, H], color=ACCENT, lw=1.6, ls=(0, (4, 3)), zorder=3)
    _right_angle(ax, s, 0, 1, 1, 0.22)
    _label(ax, W / 2, -0.32, f"{b} cm")
    _label(ax, s + 0.12, H / 2, f"{h} cm", color=ACCENT, ha="left")
    return (-0.3, W + s + 0.3), (-0.6, H + 0.25)


def _area_trap(ax, p):
    a, b, h = p["a"], p["b"], p["h"]           # a = short parallel side (top), b = long (bottom)
    W, H = _fit(b, h)
    top = W * a / b
    x0 = (W - top) * 0.35                      # slightly off-centre, like a textbook figure
    xs, ys = [0, W, x0 + top, x0, 0], [0, 0, H, H, 0]
    ax.fill(xs, ys, color=FILL, zorder=1)
    ax.plot(xs, ys, color=STROKE, lw=1.5, zorder=2)
    ax.plot([x0, x0], [0, H], color=ACCENT, lw=1.6, ls=(0, (4, 3)), zorder=3)
    _right_angle(ax, x0, 0, 1, 1, 0.22)
    _label(ax, W / 2, -0.32, f"{b} cm")
    _label(ax, x0 + top / 2, H + 0.3, f"{a} cm")
    _label(ax, x0 + 0.12, H / 2, f"{h} cm", color=ACCENT, ha="left")
    return (-0.3, W + 0.3), (-0.6, H + 0.6)


def _arc(ax, cx, cy, r, a0, a1, text, color=ACCENT, label_r=None):
    import numpy as np
    t = np.radians(np.linspace(a0, a1, 40))
    ax.plot(cx + r * np.cos(t), cy + r * np.sin(t), color=color, lw=1.4, zorder=4)
    mid = math.radians((a0 + a1) / 2)
    lr = label_r or r + 0.38
    _label(ax, cx + lr * math.cos(mid), cy + lr * math.sin(mid), text, color=color, size=8.5)


def _triangle_from_angles(a, b):
    """Vertices of a triangle with angles a (at left) and b (at right), base 4."""
    A, B = math.radians(a), math.radians(b)
    base = 4.0
    # Apex from the left vertex: law of sines.
    c = math.radians(180 - a - b)
    left_side = base * math.sin(B) / math.sin(c)
    return (0, 0), (base, 0), (left_side * math.cos(A), left_side * math.sin(A))


def _angle_missing(ax, p):
    form, known = p["form"], p["known"]
    if form in ("triangle", "isosceles_apex", "isosceles_base"):
        if form == "triangle":
            a, b = known
            labels = (f"{a}°", f"{b}°", "?")
        elif form == "isosceles_apex":
            a = b = (180 - known[0]) / 2
            labels = ("?", "?", f"{known[0]}°")
        else:
            a = b = known[0]
            labels = (f"{a}°", f"{b}°", "?")
        P, Q, R = _triangle_from_angles(a, b)
        ax.fill([P[0], Q[0], R[0]], [P[1], Q[1], R[1]], color=FILL, zorder=1)
        ax.plot([P[0], Q[0], R[0], P[0]], [P[1], Q[1], R[1], P[1]], color=STROKE, lw=1.5, zorder=2)
        r = 0.55
        far = lambda ang: r + 0.42
        _arc(ax, *P, r, 0, a, labels[0], color=ACCENT if labels[0] == "?" else STROKE, label_r=far(a))
        _arc(ax, *Q, r, 180 - b, 180, labels[1], color=ACCENT if labels[1] == "?" else STROKE, label_r=far(b))
        top = math.degrees(math.atan2(P[1] - R[1], P[0] - R[0])) % 360
        top2 = math.degrees(math.atan2(Q[1] - R[1], Q[0] - R[0])) % 360
        lo, hi = sorted((top, top2))
        _arc(ax, *R, r, lo, hi, labels[2], color=ACCENT if labels[2] == "?" else STROKE, label_r=r + 0.42)
        if form.startswith("isosceles"):              # equal-side tick marks
            for (x0, y0), (x1, y1) in ((P, R), (Q, R)):
                mx, my = x0 + (x1 - x0) * 0.62, y0 + (y1 - y0) * 0.62   # clear of the base-angle labels
                dx, dy = x1 - x0, y1 - y0
                n = math.hypot(dx, dy)
                ux, uy = -dy / n * 0.16, dx / n * 0.16
                ax.plot([mx - ux, mx + ux], [my - uy, my + uy], color=STROKE, lw=1.6, zorder=3)
        ys = [P[1], Q[1], R[1]]
        return (-0.6, 4.6), (-0.5, max(ys) + 0.7)
    # Angles on a straight line or around a point: rays from the centre.
    angles = list(known)
    total = 180 if form == "line" else 360
    angles.append(total - sum(angles))
    labels = [f"{x}°" for x in known] + ["?"]
    start = 0
    L = 2.2
    if form == "line":
        ax.plot([-L, L], [0, 0], color=STROKE, lw=1.5)
    for ang, lab in zip(angles, labels):
        end = start + ang
        if not (form == "line" and end >= 180):
            t = math.radians(end)
            ax.plot([0, L * math.cos(t)], [0, L * math.sin(t)], color=STROKE, lw=1.5)
        if form == "point" and start == 0:
            ax.plot([0, L], [0, 0], color=STROKE, lw=1.5)
        _arc(ax, 0, 0, 0.45 + 0.1 * labels.index(lab), start, end, lab, color=ACCENT if lab == "?" else STROKE, label_r=1.2)
        start = end
    ax.plot([0], [0], "o", color=STROKE, ms=3)
    return (-L - 0.2, L + 0.2), ((-0.3 if form == "line" else -L - 0.2), L + 0.2)


def _circle(ax, p):
    from matplotlib.patches import Circle
    r = p["r"]
    ask, given = p.get("ask"), p.get("given")
    show = "diameter" if ask == "radius" or given == "diameter" else "radius"
    R = 1.6
    ax.add_patch(Circle((0, 0), R, facecolor=FILL, edgecolor=STROKE, lw=1.5, zorder=1))
    ax.plot([0], [0], "o", color=STROKE, ms=3, zorder=3)
    if show == "diameter":
        ax.plot([-R, R], [0, 0], color=ACCENT, lw=1.8, zorder=2)
        _label(ax, 0, 0.28, f"{2 * r} cm", color=ACCENT)
    else:
        ax.plot([0, R], [0, 0], color=ACCENT, lw=1.8, zorder=2)
        _label(ax, R / 2, 0.28, f"{r} cm", color=ACCENT)
    return (-R - 0.2, R + 0.2), (-R - 0.2, R + 0.2)


def _perimeter_quad(ax, p):
    sides = p["sides"]
    shape = p.get("shape", "rectangle")
    if shape not in ("square", "rectangle") or len(sides) != 4:
        return None
    l, w = max(sides[0], sides[1]), min(sides[0], sides[1])   # long side along the bottom
    W, H = _fit(l, w, 0.3, 1.0)
    ax.fill([0, W, W, 0], [0, 0, H, H], color=FILL, zorder=1)
    ax.plot([0, W, W, 0, 0], [0, 0, H, H, 0], color=STROKE, lw=1.5, zorder=2)
    for x, y, dx, dy in ((0, 0, 1, 1), (W, 0, -1, 1), (W, H, -1, -1), (0, H, 1, -1)):
        _right_angle(ax, x, y, dx, dy, 0.2)
    _label(ax, W / 2, -0.3, f"{l} cm")
    if shape == "square":                      # equal sides: one tick on each
        for (x0, y0, x1, y1) in ((0, 0, W, 0), (W, 0, W, H), (0, H, W, H), (0, 0, 0, H)):
            mx, my = (x0 + x1) / 2, (y0 + y1) / 2
            if y0 == y1:
                ax.plot([mx, mx], [my - 0.13, my + 0.13], color=STROKE, lw=1.6, zorder=3)
            else:
                ax.plot([mx - 0.13, mx + 0.13], [my, my], color=STROKE, lw=1.6, zorder=3)
    else:
        _label(ax, W + 0.15, H / 2, f"{w} cm", ha="left")
    return (-0.3, W + 1.35), (-0.6, H + 0.25)


def _box(ax, p):
    l, w, h = p["l"], p["w"], p["h"]
    big = max(l, w, h)
    L, Wd, H = 3.2 * l / big, 3.2 * w / big, 3.2 * h / big
    L, Wd, H = max(L, 1.0), max(Wd, 0.9), max(H, 0.9)
    ox, oy = Wd * 0.55, Wd * 0.4                 # oblique depth
    front = [(0, 0), (L, 0), (L, H), (0, H)]
    ax.fill(*zip(*front), color=FILL, zorder=1)
    ax.fill([0, L, L + ox, ox], [H, H, H + oy, H + oy], color="#E0E7F0", zorder=1)
    ax.fill([L, L + ox, L + ox, L], [0, oy, H + oy, H], color="#D3DCE8", zorder=1)
    solid = [((0, 0), (L, 0)), ((L, 0), (L, H)), ((L, H), (0, H)), ((0, H), (0, 0)),
             ((0, H), (ox, H + oy)), ((L, H), (L + ox, H + oy)), ((ox, H + oy), (L + ox, H + oy)),
             ((L, 0), (L + ox, oy)), ((L + ox, oy), (L + ox, H + oy))]
    for (x0, y0), (x1, y1) in solid:
        ax.plot([x0, x1], [y0, y1], color=STROKE, lw=1.4, zorder=2)
    for (x0, y0), (x1, y1) in (((0, 0), (ox, oy)), ((ox, oy), (L + ox, oy)), ((ox, oy), (ox, H + oy))):
        ax.plot([x0, x1], [y0, y1], color=STROKE, lw=1.1, ls=(0, (3, 3)), zorder=2)
    _label(ax, L / 2, -0.3, f"{l} cm")
    _label(ax, L + ox / 2 + 0.25, oy / 2 - 0.12, f"{w} cm", ha="left")
    _label(ax, -0.15, H / 2, f"{h} cm", ha="right")
    return (-1.75, L + ox + 1.4), (-0.6, H + oy + 0.25)


def _coord(ax, p):
    x, y = p["x"], p["y"]
    k = p.get("k")
    axis = p.get("axis")
    n = max(6, x, y, (k or 0)) + 2
    n = min(n, 12)
    for i in range(n + 1):
        ax.plot([i, i], [0, n], color=GRID, lw=0.8, zorder=0)
        ax.plot([0, n], [i, i], color=GRID, lw=0.8, zorder=0)
    ax.annotate("", xy=(n + 0.5, 0), xytext=(0, 0), arrowprops=dict(arrowstyle="-|>", color=STROKE, lw=1.6), zorder=1)
    ax.annotate("", xy=(0, n + 0.5), xytext=(0, 0), arrowprops=dict(arrowstyle="-|>", color=STROKE, lw=1.6), zorder=1)
    step = 1 if n <= 8 else 2
    for i in range(0, n + 1, step):
        _label(ax, i, -0.45, str(i), size=7, color=TEXT, bg=False)
        if i:
            _label(ax, -0.45, i, str(i), size=7, color=TEXT, bg=False)
    _label(ax, n + 0.95, 0, "x", size=8.5, color=STROKE, bg=False)
    _label(ax, 0, n + 1.0, "y", size=8.5, color=STROKE, bg=False)
    if axis in ("x", "y") and k is not None:
        if axis == "x":            # vertical mirror line x = k
            ax.plot([k, k], [0, n], color=ACCENT, lw=1.5, ls=(0, (5, 3)), zorder=2)
            _label(ax, k, n + 0.45, f"x = {k}", color=ACCENT, size=8, ha="left" if k < n / 2 else "right")
        else:                      # horizontal mirror line y = k
            ax.plot([0, n], [k, k], color=ACCENT, lw=1.5, ls=(0, (5, 3)), zorder=2)
            _label(ax, n - 0.1, k + 0.4, f"y = {k}", color=ACCENT, size=8, ha="right",
                   bbox=dict(boxstyle="round,pad=0.15", fc="white", ec="none"))
    ax.plot([x], [y], "o", color=POINT, ms=5, zorder=4)
    _label(ax, x + 0.25, y + 0.38, "A", color=POINT, size=9, ha="left", fontweight="bold")
    return (-1.0, n + 1.4), (-0.9, n + 1.4)


_DRAW = {"area_tri": _area_tri, "area_para": _area_para, "area_trap": _area_trap, "angle_missing": _angle_missing, "circle": _circle,
         "perimeter_quad": _perimeter_quad, "volume_prism": _box, "surface_area": _box,
         "reflect_point": _coord, "translate_point": _coord}
# Figure width in inches = its printed width, so label sizes are true print sizes.
PRINT_MM = {"reflect_point": 46, "translate_point": 46, "circle": 27}
DEFAULT_MM = 38
MAX_HEIGHT_MM = 36
_SIZE = {g: (mm / 25.4, mm / 25.4) for g, mm in PRINT_MM.items()}


@lru_cache(maxsize=512)
def _render(gen, params_key, fmt):
    import json
    params = json.loads(params_key)
    w_in, h_in = _SIZE.get(gen, (DEFAULT_MM / 25.4, DEFAULT_MM / 25.4 * 0.75))
    plt, fig, ax = _fig(w_in, h_in)
    try:
        bounds = _DRAW[gen](ax, params)
        if bounds is None:
            return None
        (x0, x1), (y0, y1) = bounds
        ax.set_xlim(x0, x1)
        ax.set_ylim(y0, y1)
        # Size the figure to the drawing's own aspect ratio (no empty margins),
        # capping the height so a tall shape never dominates a worksheet row.
        aspect = (y1 - y0) / (x1 - x0)
        max_h = MAX_HEIGHT_MM / 25.4
        if w_in * aspect > max_h:
            w_in = max_h / aspect
        h_in = w_in * aspect
        fig.set_size_inches(w_in, h_in)
        buf = io.BytesIO()
        if fmt == "svg":
            fig.savefig(buf, format="svg", transparent=True, metadata={"Date": None, "Creator": None})
            svg = buf.getvalue().decode()
            svg = svg[svg.index("<svg"):]
            svg = re.sub(r'<metadata>.*?</metadata>', "", svg, flags=re.S)
            return svg, w_in
        fig.savefig(buf, format="png", dpi=220, transparent=False, facecolor="white")
        return buf.getvalue(), w_in
    finally:
        plt.close(fig)


def _key(item):
    import json
    return item["gen"], json.dumps(item["params"], sort_keys=True)


def svg(item, width_mm=None) -> str | None:
    """Inline SVG markup for the item's figure (None when it has none)."""
    if not supports(item):
        return None
    try:
        res = _render(*_key(item), "svg")
    except Exception:
        return None
    if not res:
        return None
    data, w_in = res
    w = width_mm or round(w_in * 25.4, 1)
    # Print width = the figure's own size; height follows its aspect ratio.
    data = re.sub(r'width="[^"]+" height="[^"]+"', f'width="{w}mm"', data, count=1)
    return f'<span class="diagram">{data}</span>'


def png(item) -> str | None:
    """Path to a cached PNG of the item's figure, for Word/PowerPoint (None when it has none)."""
    if not supports(item):
        return None
    gen, key = _key(item)
    out = config.CACHE / "img" / f"diagram-{gen}-{hashlib.md5(key.encode()).hexdigest()[:12]}.png"
    if not out.exists():
        try:
            res = _render(gen, key, "png")
        except Exception:
            return None
        if not res:
            return None
        data = res[0]
        out.parent.mkdir(parents=True, exist_ok=True)
        out.write_bytes(data)
    return str(out)
