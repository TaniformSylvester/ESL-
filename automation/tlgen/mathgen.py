"""Math item generators with independent solvers.

Every generated item stores its parameters; qc.py re-solves each item from
those parameters with solve() and fails the resource if any stored answer
differs. Answers are therefore computed, never typed by a person or model.
"""
import ast
import random
from fractions import Fraction
from math import gcd

NAMES = ["Ana", "Ben", "Chen", "Dara", "Eli", "Fatima", "Gus", "Hana", "Ivan", "Jia", "Kofi", "Lina", "Mateo", "Nok", "Omar", "Priya"]
THINGS = [("apple", "apples"), ("pencil", "pencils"), ("sticker", "stickers"), ("marble", "marbles"), ("book", "books"),
          ("shell", "shells"), ("crayon", "crayons"), ("card", "cards"), ("button", "buttons"), ("balloon", "balloons")]


def _carries(a, b):
    while a or b:
        if a % 10 + b % 10 >= 10:
            return True
        a, b = a // 10, b // 10
    return False


def _borrows(a, b):
    while b:
        if a % 10 < b % 10:
            return True
        a, b = a // 10, b // 10
    return False


def evaluate(expr: str) -> Fraction:
    """Exact value of an integer expression with + - * / and brackets (no other syntax allowed)."""
    ops = {ast.Add: lambda a, b: a + b, ast.Sub: lambda a, b: a - b, ast.Mult: lambda a, b: a * b, ast.Div: lambda a, b: a / b}

    def ev(node):
        if isinstance(node, ast.Expression):
            return ev(node.body)
        if isinstance(node, ast.BinOp) and type(node.op) in ops:
            return ops[type(node.op)](ev(node.left), ev(node.right))
        if isinstance(node, ast.Constant) and isinstance(node.value, int):
            return Fraction(node.value)
        raise ValueError(f"unsupported expression: {expr}")
    return ev(ast.parse(expr, mode="eval"))


def pretty(expr: str) -> str:
    return expr.replace("*", "×").replace("/", "÷").replace("-", "−")


PICTO_ASK = {   # (how many…, how many more…) wording per theme
    "fruit": ("How many children chose {a}?", "How many more children chose {a} than {b}?"),
    "pets": ("How many {a} are there?", "How many more {a} than {b}?"),
    "weather": ("How many {a} were there?", "How many more {a} than {b}?"),
    "toys": ("How many children chose {a}?", "How many more children chose {a} than {b}?"),
}
PICTO_THEMES = {
    "fruit": ("Favorite fruit", [("🍎", "apples"), ("🍌", "bananas"), ("🍇", "grapes")]),
    "pets": ("Our pets", [("🐶", "dogs"), ("🐱", "cats"), ("🐟", "fish")]),
    "weather": ("Weather this month", [("☀️", "sunny days"), ("🌧️", "rainy days"), ("☁️", "cloudy days")]),
    "toys": ("Favorite toys", [("⚽", "balls"), ("🧸", "teddy bears"), ("🪁", "kites")]),
}


def picto_data(theme, seed):
    """Counts for a picture graph: fixed by theme + seed so every question in a section uses the same graph."""
    rng = random.Random(f"picto:{theme}:{seed}")
    while True:
        counts = [rng.randint(2, 9) for _ in range(3)]
        if len(set(counts)) == 3:          # distinct counts: "most" and "fewest" have one answer
            return counts


def fmt_time(h, m):
    return f"{h}:{m:02d}"


# ---------------------------------------------------------------- solvers
def solve(gen, p):
    """Recompute the answer for an item from its parameters."""
    if gen == "add":
        return p["a"] + p["b"]
    if gen == "sub":
        return p["a"] - p["b"]
    if gen == "missing_addend":
        return p["total"] - p["a"]
    if gen == "mult":
        return p["a"] * p["b"]
    if gen == "div":
        return p["a"] // p["b"]
    if gen == "compare":
        return "<" if p["a"] < p["b"] else (">" if p["a"] > p["b"] else "=")
    if gen == "expanded_form":
        n = p["n"]
        parts = [int(d) * 10 ** i for i, d in enumerate(reversed(str(n))) if d != "0"]
        return " + ".join(str(x) for x in reversed(parts))
    if gen == "digit_value":
        return int(str(p["n"])[p["pos"]]) * 10 ** (len(str(p["n"])) - 1 - p["pos"])
    if gen == "skip_count":
        return p["start"] + p["step"] * p["blank_index"]
    if gen == "odd_even":
        return "even" if p["n"] % 2 == 0 else "odd"
    if gen == "round":
        n, to = p["n"], p["to"]
        return (n + to // 2) // to * to
    if gen in ("clock_read", "clock_draw"):
        return fmt_time(p["h"], p["m"])
    if gen == "elapsed":
        total = p["h"] * 60 + p["m"] + p["add"]
        h = (total // 60 - 1) % 12 + 1
        return fmt_time(h, total % 60)
    if gen == "fraction_shade":
        return f"{p['shaded']}/{p['parts']}"
    if gen == "fraction_compare":
        a, b = Fraction(p["n1"], p["d1"]), Fraction(p["n2"], p["d2"])
        return "<" if a < b else (">" if a > b else "=")
    if gen == "equivalent_fraction":
        return p["n"] * p["d2"] // p["d"]
    if gen == "perimeter_rect":
        return 2 * (p["l"] + p["w"])
    if gen == "area_rect":
        return p["l"] * p["w"]
    if gen == "odd_even_objects":
        return "even" if p["n"] % 2 == 0 else "odd"
    if gen == "thermometer_read":
        return p["t"]
    if gen == "order_ops":
        v = evaluate(p["expr"])
        assert v.denominator == 1 and v >= 0
        return int(v)
    if gen == "div_remainder":
        return f"{p['a'] // p['b']} R {p['a'] % p['b']}"
    if gen == "word_div":
        return p["total"] // p["each"]
    if gen == "pictograph":
        counts = picto_data(p["theme"], p["data_seed"])
        names = [n for _, n in PICTO_THEMES[p["theme"]][1]]
        if p["q"] == "count":
            return counts[p["i"]]
        if p["q"] == "more":
            return counts[p["i"]] - counts[p["j"]]
        if p["q"] == "total":
            return sum(counts)
        if p["q"] == "most":
            return names[counts.index(max(counts))]
        if p["q"] == "fewest":
            return names[counts.index(min(counts))]
    if gen == "doubles":
        return f"{p['n'] // 2} + {p['n'] // 2}"
    if gen in ("from_place_values", "base_ten_read"):
        return p["h"] * 100 + p["t"] * 10 + p["o"]
    if gen == "word_add":
        return p["a"] + p["b"]
    if gen == "word_sub":
        return p["a"] - p["b"]
    if gen == "word_mult":
        return p["groups"] * p["each"]
    raise ValueError(f"unknown generator {gen}")


# ---------------------------------------------------------------- generators
def _item(gen, params, prompt, render=None):
    item = {"gen": gen, "params": params, "prompt": prompt, "answer": solve(gen, params)}
    if render:
        item["render"] = render
    return item


def generate(gen, count=6, seed=1, **kw):
    """Return `count` distinct items for generator `gen`."""
    rng = random.Random(f"{gen}:{seed}:{sorted(kw.items())}")
    items, seen, guard = [], set(), 0
    while len(items) < count and guard < 2000:
        guard += 1
        it = _one(gen, rng, _seed=seed, **kw)
        sig = repr(sorted(it["params"].items()))
        if sig in seen:
            continue
        seen.add(sig)
        items.append(it)
    if len(items) < count:
        raise ValueError(f"{gen}: could only make {len(items)} distinct items with {kw}")
    return items


def _one(gen, rng, lo=1, hi=20, regroup=None, factors=(2, 5, 10), max_other=10, step=5, to=10, digits=3,
         parts=(2, 3, 4), denominators=(2, 3, 4, 6, 8), max_side=10, theme="fruit", brackets=True, _seed=1, **_):
    if gen == "add":
        while True:
            a, b = rng.randint(lo, hi), rng.randint(lo, hi)
            if regroup is None or _carries(a, b) == regroup:
                return _item("add", {"a": a, "b": b}, f"{a} + {b} = ____")
    if gen == "sub":
        while True:
            a, b = sorted((rng.randint(lo, hi), rng.randint(lo, hi)), reverse=True)
            if a != b and (regroup is None or _borrows(a, b) == regroup):
                return _item("sub", {"a": a, "b": b}, f"{a} − {b} = ____")
    if gen == "missing_addend":
        total = rng.randint(max(lo, 5), hi)
        a = rng.randint(1, total - 1)
        return _item("missing_addend", {"a": a, "total": total}, f"{a} + ____ = {total}")
    if gen == "mult":
        a, b = rng.choice(list(factors)), rng.randint(1, max_other)
        if rng.random() < 0.5:
            a, b = b, a
        return _item("mult", {"a": a, "b": b}, f"{a} × {b} = ____")
    if gen == "div":
        b, q = rng.choice(list(factors)), rng.randint(1, max_other)
        return _item("div", {"a": b * q, "b": b}, f"{b * q} ÷ {b} = ____")
    if gen == "compare":
        a, b = rng.randint(lo, hi), rng.randint(lo, hi)
        if rng.random() < 0.12:
            b = a
        return _item("compare", {"a": a, "b": b}, f"{a}  ◯  {b}")
    if gen == "expanded_form":
        n = rng.randint(10 ** (digits - 1), 10 ** digits - 1)
        return _item("expanded_form", {"n": n}, f"{n} = ______________")
    if gen == "digit_value":
        while True:   # the asked-about digit must be non-zero and appear only once
            n = rng.randint(10 ** (digits - 1), 10 ** digits - 1)
            pos = rng.randrange(digits)
            if str(n)[pos] != "0" and str(n).count(str(n)[pos]) == 1:
                break
        return _item("digit_value", {"n": n, "pos": pos}, f"What is the value of the {str(n)[pos]} in {n}?", {"underline": pos})
    if gen == "skip_count":
        stp = rng.choice(list(factors))
        start = stp * rng.randint(0, 6)
        blank = rng.randint(2, 5)
        seq = [start + stp * i for i in range(6)]
        shown = ["____" if i == blank else str(x) for i, x in enumerate(seq)]
        return _item("skip_count", {"start": start, "step": stp, "blank_index": blank}, ",  ".join(shown))
    if gen == "odd_even":
        n = rng.randint(lo, hi)
        return _item("odd_even", {"n": n}, f"Is {n} odd or even? ____")
    if gen == "round":
        n = rng.randint(lo, hi)
        return _item("round", {"n": n, "to": to}, f"Round {n} to the nearest {to}: ____")
    if gen in ("clock_read", "clock_draw"):
        h, m = rng.randint(1, 12), rng.randrange(0, 60, step)
        prompt = "What time does the clock show? ________" if gen == "clock_read" else f"Draw the hands to show {fmt_time(h, m)}."
        return _item(gen, {"h": h, "m": m}, prompt, {"clock": [h, m], "hands": gen == "clock_read"})
    if gen == "elapsed":
        h, m = rng.randint(1, 11), rng.randrange(0, 60, step)
        add = rng.choice([15, 30, 45, 60, 90]) if step >= 15 else rng.randrange(10, 125, 5)
        return _item("elapsed", {"h": h, "m": m, "add": add}, f"It is {fmt_time(h, m)}. What time will it be in {add} minutes? ________", {"clock": [h, m], "hands": True})
    if gen == "fraction_shade":
        n = rng.choice(list(parts))
        k = rng.randint(1, n - 1) if n > 1 else 1
        shape = rng.choice(["rect", "circle"])
        return _item("fraction_shade", {"parts": n, "shaded": k}, "What fraction is shaded? ____", {"fraction": [n, k, shape]})
    if gen == "fraction_compare":
        d1, d2 = rng.choice(list(denominators)), rng.choice(list(denominators))
        n1, n2 = rng.randint(1, d1 - 1), rng.randint(1, d2 - 1)
        return _item("fraction_compare", {"n1": n1, "d1": d1, "n2": n2, "d2": d2}, f"{n1}/{d1}  ◯  {n2}/{d2}")
    if gen == "equivalent_fraction":
        d = rng.choice([2, 3, 4, 5])
        n = rng.randint(1, d - 1)
        k = rng.randint(2, 4)
        return _item("equivalent_fraction", {"n": n, "d": d, "d2": d * k}, f"{n}/{d} = ____/{d * k}")
    if gen == "thermometer_read":
        t = rng.randrange(max(0, lo), min(40, hi) + 1, 2)
        return _item(gen, {"t": t}, "What temperature does it show? ____ °C", {"thermo": t})
    if gen == "order_ops":
        templates = ["{a} + {b} * {c}", "{a} * {b} - {c}", "{a} - {b} * {c}", "{a} + {b} / {c}", "{a} * {b} + {c} * {d}",
                     "{a} + {b} * {c} - {d}"]
        if brackets:
            templates += ["({a} + {b}) * {c}", "{a} * ({b} - {c})", "({a} + {b}) / {c}", "{a} - ({b} + {c})", "({a} - {b}) * ({c} + {d})"]
        while True:
            t = rng.choice(templates)
            v = {k: rng.randint(2, 12) for k in "abcd"}
            if "/" in t:                       # make every division exact
                if "({a} + {b}) / {c}" == t:
                    v["a"] = v["c"] * rng.randint(2, 9) - v["b"]
                else:
                    v["b"] = v["c"] * rng.randint(2, 9)
            expr = t.format(**v)
            try:
                val = evaluate(expr)
            except ZeroDivisionError:
                continue
            if val.denominator == 1 and 0 <= val <= 200 and all(x > 0 for x in v.values()):
                return _item(gen, {"expr": expr}, f"{pretty(expr)} = ____")
    if gen == "div_remainder":
        while True:
            b = rng.choice(list(factors))
            a = rng.randint(max(lo, b * 2), hi)
            if a % b:
                return _item(gen, {"a": a, "b": b}, f"{a} ÷ {b} = ____")
    if gen == "word_div":
        each = rng.choice(list(factors))
        total = each * rng.randint(max(2, lo), max_other)
        name = rng.choice(NAMES)
        things, one, many = rng.choice([("books", "shelf", "shelves"), ("eggs", "box", "boxes"), ("chairs", "row", "rows"),
                                        ("stickers", "page", "pages"), ("pencils", "pack", "packs")])
        return _item(gen, {"total": total, "each": each, "kind": things},
                     f"{name} has {total} {things}. Each {one} holds {each} {things}. How many {many} does {name} need? ____")
    if gen == "pictograph":
        title, cats = PICTO_THEMES[theme]
        q = rng.choice(["count", "count", "more", "total", "most", "fewest"])
        counts = picto_data(theme, _seed)
        i, j = rng.sample(range(3), 2)
        if q == "more" and counts[i] < counts[j]:
            i, j = j, i
        params = {"theme": theme, "data_seed": _seed, "q": q}
        if q == "count":
            params["i"] = i
            prompt = PICTO_ASK[theme][0].format(a=cats[i][1]) + " ____"
        elif q == "more":
            params.update(i=i, j=j)
            prompt = PICTO_ASK[theme][1].format(a=cats[i][1], b=cats[j][1]) + " ____"
        elif q == "total":
            prompt = "How many are there in all? ____"
        elif q == "most":
            prompt = "Which has the most? ________"
        else:
            prompt = "Which has the fewest? ________"
        return _item(gen, params, prompt, {"pictograph": [theme, _seed]})
    if gen == "odd_even_objects":
        n = rng.randint(max(lo, 3), min(hi, 20))
        thing = rng.choice(["🍎", "⭐", "🐟", "🌸", "🧦", "🍪", "🎈", "🐞"])
        return _item(gen, {"n": n, "thing": thing}, "Circle pairs. Odd or even? ____", {"objects": [thing, n]})
    if gen == "doubles":
        n = 2 * rng.randint(max(2, lo // 2), max(3, min(hi, 20) // 2))
        return _item(gen, {"n": n}, f"{n} = ____ + ____")
    if gen == "from_place_values":
        h, t, o = rng.randint(1, 9), rng.randint(0, 9), rng.randint(0, 9)
        unit = lambda n, word: f"{n} {word}" + ("" if n == 1 else "s")
        return _item(gen, {"h": h, "t": t, "o": o}, f"{unit(h, 'hundred')}, {unit(t, 'ten')} and {unit(o, 'one')} = ____")
    if gen == "base_ten_read":
        h, t, o = rng.randint(1, 3), rng.randint(1, 6), rng.randint(0, 9)
        return _item(gen, {"h": h, "t": t, "o": o}, "What number do the blocks show? ____", {"base10": [h, t, o]})
    if gen in ("perimeter_rect", "area_rect"):
        l, w = rng.randint(2, max_side), rng.randint(2, max_side)
        what = "perimeter" if gen == "perimeter_rect" else "area"
        unit = "cm" if gen == "perimeter_rect" else "square units"
        return _item(gen, {"l": l, "w": w}, f"A rectangle is {l} units long and {w} units wide. What is its {what}? ____ {unit}", {"rect": [l, w]})
    if gen in ("word_add", "word_sub", "word_mult"):
        name, (one, many) = rng.choice(NAMES), rng.choice(THINGS)
        if gen == "word_add":
            a, b = rng.randint(lo, hi), rng.randint(lo, hi)
            return _item(gen, {"a": a, "b": b}, f"{name} has {a} {many if a != 1 else one}. A friend gives {name} {b} more. How many {many} does {name} have now? ____")
        if gen == "word_sub":
            a, b = sorted((rng.randint(lo, hi), rng.randint(lo, hi)), reverse=True)
            if a == b:
                a += 1
            return _item(gen, {"a": a, "b": b}, f"{name} has {a} {many}. {name} gives away {b}. How many {many} are left? ____")
        groups, each = rng.randint(2, 6), rng.choice(list(factors))
        return _item(gen, {"groups": groups, "each": each}, f"There are {groups} bags. Each bag has {each} {many}. How many {many} are there in all? ____")
    raise ValueError(f"unknown generator {gen}")


def expand_sections(spec):
    """Fill every math section that declares a generator with generated items (in place)."""
    for part in ("worksheet", "quiz", "homework"):
        for sec in (spec.get(part) or {}).get("sections", []):
            g = sec.get("generator")
            if g and not sec.get("items"):
                opts = {k: v for k, v in g.items() if k not in ("type", "count", "seed")}
                sec["items"] = generate(g["type"], g.get("count", 6), g.get("seed", 1), **opts)
    return spec


def simplify(fr: str) -> str:
    n, d = map(int, fr.split("/"))
    g = gcd(n, d)
    return f"{n // g}/{d // g}"
