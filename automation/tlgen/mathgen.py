"""Math item generators with independent solvers.

Every generated item stores its parameters; qc.py re-solves each item from
those parameters with solve() and fails the resource if any stored answer
differs. Answers are therefore computed, never typed by a person or model.
"""
import ast
import random
from decimal import Decimal
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


def num(n) -> str:
    """Integer with a real minus sign (−5), for printing."""
    return str(n).replace("-", "−")


def frac_str(f: Fraction) -> str:
    """Simplest form; improper fractions as mixed numbers (7/2 → 3 1/2)."""
    if f.denominator == 1:
        return str(f.numerator)
    whole, rest = divmod(f.numerator, f.denominator)
    return f"{whole} {rest}/{f.denominator}" if whole else f"{rest}/{f.denominator}"


LINEPLOT_THEMES = {
    "ribbons": ("Length of ribbons (in meters)", "ribbons", "meter"),
    "beans": ("Height of bean plants (in feet)", "bean plants", "foot"),
    "water": ("Water in each bottle (in liters)", "bottles", "liter"),
}


def lineplot_data(theme, seed):
    """Measurements in eighths, fixed by theme + seed so every question uses the same plot."""
    rng = random.Random(f"lineplot:{theme}:{seed}")
    values = [Fraction(1, 8), Fraction(1, 4), Fraction(3, 8), Fraction(1, 2), Fraction(5, 8), Fraction(3, 4)]
    while True:
        counts = {v: rng.randint(0, 4) for v in values}
        if sum(counts.values()) >= 10 and sorted(counts.values())[-1] != sorted(counts.values())[-2] and min(counts.values()) >= 0:
            return counts


def dec_str(d) -> str:
    """A decimal without trailing zeros (5.30 → 5.3, 4.0 → 4)."""
    t = format(Decimal(str(d)).normalize(), "f")
    return t


SUPERSCRIPT = str.maketrans("0123456789", "⁰¹²³⁴⁵⁶⁷⁸⁹")


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
        v = (n + to // 2) // to * to
        return f"{v:,}" if v >= 1000 else v
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
    if gen == "frac_div":
        return frac_str(Fraction(p["n1"], p["d1"]) / Fraction(p["n2"], p["d2"]))
    if gen == "abs_value":
        return abs(p["n"])
    if gen == "opposite":
        return num(-p["n"])
    if gen == "order_ints":
        return ", ".join(num(x) for x in sorted(p["nums"]))
    if gen == "temp_change":
        return num(p["start"] + p["change"])
    if gen == "lineplot":
        counts = lineplot_data(p["theme"], p["data_seed"])
        present = [v for v, c in counts.items() if c]
        if p["q"] == "count":
            return counts[Fraction(p["v"])]
        if p["q"] == "total_items":
            return sum(counts.values())
        if p["q"] == "difference":
            return frac_str(max(present) - min(present))
        if p["q"] == "combined":
            return frac_str(counts[Fraction(p["v"])] * Fraction(p["v"]))
        if p["q"] == "most":
            return frac_str(max(counts, key=lambda v: counts[v]))
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
    if gen == "frac_mult":
        return frac_str(Fraction(p["n1"], p["d1"]) * Fraction(p["n2"], p["d2"]))
    if gen == "dec_add":
        return dec_str(Decimal(p["a"]) + Decimal(p["b"]))
    if gen == "dec_sub":
        return dec_str(Decimal(p["a"]) - Decimal(p["b"]))
    if gen == "one_step_eq":
        a, b = p["a"], p["b"]
        return {"add": b - a, "sub": b + a, "mult": b // a, "div": b * a}[p["form"]]
    if gen == "area_tri":
        return p["b"] * p["h"] // 2           # generated with an even product
    if gen == "area_para":
        return p["b"] * p["h"]
    if gen == "power":
        return p["base"] ** p["exp"]
    if gen == "tens_ones":
        return p["t"] * 10 + p["o"]
    if gen == "word_two_step":
        if p["form"] == "add_sub":
            return p["a"] + p["b"] - p["c"]
        if p["form"] == "groups_sub":
            return p["groups"] * p["each"] - p["c"]
        return p["groups"] * p["each"] + p["c"]
    if gen == "add3":
        return p["a"] + p["b"] + p["c"]
    if gen == "near_double":
        return p["a"] + p["a"] + 1
    if gen == "missing_sub":
        return p["a"] - p["diff"] if p["blank"] == "b" else p["b"] + p["diff"]
    if gen == "frac_add_like":
        return frac_str(Fraction(p["n1"] + p["n2"], p["d"]))
    if gen == "mult_big":
        return f"{p['a'] * p['b']:,}"
    if gen == "round_decimal":
        q = Decimal(1).scaleb(-p["places"])
        from decimal import ROUND_HALF_UP
        return format(Decimal(p["n"]).quantize(q, rounding=ROUND_HALF_UP), "f")
    if gen == "dec_pow10":
        v = Decimal(p["n"]) * Decimal(p["p"]) if p["op"] == "mult" else Decimal(p["n"]) / Decimal(p["p"])
        return dec_str(v)
    if gen == "gcf":
        return gcd(p["a"], p["b"])
    if gen == "lcm":
        return p["a"] * p["b"] // gcd(p["a"], p["b"])
    if gen == "dec_div":
        return dec_str(Decimal(p["a"]) / Decimal(p["b"]))
    if gen == "distributive":
        return p["a"] * (p["b"] + p["c"])
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
         parts=(2, 3, 4), denominators=(2, 3, 4, 6, 8), max_side=10, theme="fruit", brackets=True, whole=False, _seed=1, **_):
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
        return _item("compare", {"a": a, "b": b}, f"{num(a)}  ◯  {num(b)}")
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
        shown = lambda x: f"{x:,}" if x >= 1000 else str(x)
        return _item("round", {"n": n, "to": to}, f"Round {shown(n)} to the nearest {shown(to)}: ____")
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
        return _item("fraction_shade", {"parts": n, "shaded": k, "shape": shape}, "What fraction is shaded? ____", {"fraction": [n, k, shape]})
    if gen == "fraction_compare":
        d1, d2 = rng.choice(list(denominators)), rng.choice(list(denominators))
        n1, n2 = rng.randint(1, d1 - 1), rng.randint(1, d2 - 1)
        return _item("fraction_compare", {"n1": n1, "d1": d1, "n2": n2, "d2": d2}, f"{n1}/{d1}  ◯  {n2}/{d2}")
    if gen == "equivalent_fraction":
        d = rng.choice([2, 3, 4, 5])
        n = rng.randint(1, d - 1)
        k = rng.randint(2, 4)
        return _item("equivalent_fraction", {"n": n, "d": d, "d2": d * k}, f"{n}/{d} = ____/{d * k}")
    if gen == "frac_div":
        kind = rng.choice(["ff", "ff", "wf", "fw"])
        if kind == "wf":                        # whole ÷ fraction
            n1, d1 = rng.randint(2, 6), 1
            d2 = rng.choice([2, 3, 4, 5, 6, 8]); n2 = rng.randint(1, d2 - 1)
        elif kind == "fw":                      # fraction ÷ whole
            d1 = rng.choice([2, 3, 4, 5, 6, 8]); n1 = rng.randint(1, d1 - 1)
            n2, d2 = rng.randint(2, 6), 1
        else:
            d1, d2 = rng.choice([2, 3, 4, 5, 6, 8]), rng.choice([2, 3, 4, 5, 6, 8])
            n1, n2 = rng.randint(1, d1 - 1), rng.randint(1, d2 - 1)
        show = lambda n, d: str(n) if d == 1 else f"{n}/{d}"
        return _item(gen, {"n1": n1, "d1": d1, "n2": n2, "d2": d2}, f"{show(n1, d1)} ÷ {show(n2, d2)} = ____")
    if gen == "abs_value":
        n = -rng.randint(1, max(1, -lo)) if rng.random() < 0.7 else rng.randint(1, max(1, hi))
        return _item(gen, {"n": n}, f"|{num(n)}| = ____")
    if gen == "opposite":
        n = rng.choice([x for x in range(lo, hi + 1) if x != 0])
        return _item(gen, {"n": n}, f"The opposite of {num(n)} is ____")
    if gen == "order_ints":
        nums = rng.sample([x for x in range(lo, hi + 1)], 5)
        return _item(gen, {"nums": nums}, "Order from least to greatest: " + ",  ".join(num(x) for x in nums) + "  ____")
    if gen == "temp_change":
        start = rng.randint(-12, 8)
        change = rng.choice([x for x in range(-10, 11) if x != 0])
        verb = f"rises by {change}" if change > 0 else f"falls by {-change}"
        return _item(gen, {"start": start, "change": change},
                     f"The temperature is {num(start)} °C. It {verb} degrees. What is the new temperature? ____ °C")
    if gen == "lineplot":
        title, things, unit = LINEPLOT_THEMES[theme]
        counts = lineplot_data(theme, _seed)
        present = [v for v, c in counts.items() if c]
        q = rng.choice(["count", "count", "total_items", "difference", "combined", "most"])
        params = {"theme": theme, "data_seed": _seed, "q": q}
        if q == "count":
            v = rng.choice(list(counts)); params["v"] = str(v)
            prompt = f"How many {things} measured {v} {unit}? ____"
        elif q == "total_items":
            prompt = f"How many {things} were measured in all? ____"
        elif q == "difference":
            prompt = f"What is the difference between the longest and shortest measurement? ____ {unit}"
        elif q == "combined":
            v = rng.choice(present); params["v"] = str(v)
            prompt = f"What is the total of all the {v} {unit} measurements? ____ {unit}"
        else:
            prompt = "Which measurement appears most often? ____"
        return _item(gen, params, prompt, {"lineplot": [theme, _seed]})
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
        unit = "units" if gen == "perimeter_rect" else "square units"
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
    if gen == "frac_mult":
        d1, d2 = rng.choice(list(denominators)), rng.choice(list(denominators))
        n1 = rng.choice([n for n in range(1, d1) if gcd(n, d1) == 1])
        n2 = rng.choice([n for n in range(1, d2) if gcd(n, d2) == 1])
        if whole or rng.random() < 0.3:         # whole × fraction
            n1, d1 = rng.randint(2, 6), 1
        show = lambda n, d: str(n) if d == 1 else f"{n}/{d}"
        return _item(gen, {"n1": n1, "d1": d1, "n2": n2, "d2": d2}, f"{show(n1, d1)} × {show(n2, d2)} = ____")
    if gen in ("dec_add", "dec_sub"):
        places = rng.choice([1, 2]) if digits >= 2 else 1
        q = Decimal(1).scaleb(-places)
        while True:
            a = (Decimal(rng.randint(lo * 10 ** places, hi * 10 ** places)) * q)
            b = (Decimal(rng.randint(1, hi * 10 ** places)) * q)
            if gen == "dec_sub" and b >= a:
                a, b = b, a
            if a != b and dec_str(a) != dec_str(a.to_integral_value()):
                break
        sign = "+" if gen == "dec_add" else "−"
        return _item(gen, {"a": dec_str(a), "b": dec_str(b)}, f"{dec_str(a)} {sign} {dec_str(b)} = ____")
    if gen == "one_step_eq":
        form = rng.choice(["add", "sub", "mult", "div"])
        a = rng.randint(2, 12)
        x = a + rng.randint(1, max(3, hi)) if form == "sub" else rng.randint(2, max(3, hi))
        if form == "div":
            x = a * rng.randint(2, 12)
        b = {"add": x + a, "sub": x - a, "mult": a * x, "div": x // a}[form]
        shown = {"add": f"x + {a} = {b}", "sub": f"x − {a} = {b}", "mult": f"{a}x = {b}", "div": f"x ÷ {a} = {b}"}[form]
        return _item(gen, {"form": form, "a": a, "b": b}, f"{shown}      x = ____")
    if gen in ("area_tri", "area_para"):
        while True:
            b, h = rng.randint(3, max_side), rng.randint(2, max_side)
            if gen == "area_para" or (b * h) % 2 == 0:
                break
        shape = "triangle" if gen == "area_tri" else "parallelogram"
        return _item(gen, {"b": b, "h": h}, f"A {shape} has a base of {b} cm and a height of {h} cm. What is its area? ____ cm²")
    if gen == "power":
        base = rng.randint(2, 10)
        exp = rng.randint(2, 3 if base > 5 else 4)
        return _item(gen, {"base": base, "exp": exp}, f"{base}{str(exp).translate(SUPERSCRIPT)} = ____")
    if gen == "tens_ones":
        t, o = rng.randint(1, 9), rng.randint(0, 9)
        if rng.random() < 0.5:
            return _item(gen, {"t": t, "o": o, "show": "blocks"}, "What number do the blocks show? ____", {"base10": [0, t, o]})
        unit = lambda n, word: f"{n} {word}" + ("" if n == 1 else "s")
        return _item(gen, {"t": t, "o": o, "show": "words"}, f"{unit(t, 'ten')} and {unit(o, 'one')} = ____")
    if gen == "word_two_step":
        name, (one, many) = rng.choice(NAMES), rng.choice(THINGS)
        form = rng.choice(["add_sub", "groups_sub", "groups_add"])
        if form == "add_sub":
            a, b = rng.randint(lo, hi), rng.randint(lo, hi)
            c = rng.randint(2, a + b - 1)
            return _item(gen, {"form": form, "a": a, "b": b, "c": c},
                         f"{name} has {a} {many}. {name} gets {b} more, then gives {c} to a friend. How many {many} does {name} have now? ____")
        groups, each = rng.randint(2, 9), rng.randint(2, max(10, max_other))
        c = rng.randint(2, groups * each - 1) if form == "groups_sub" else rng.randint(2, max(20, 2 * max_other))
        if form == "groups_sub":
            text = f"{name} buys {groups} packs of {many}. Each pack has {each} {many}. {name} gives away {c} of them. How many {many} are left? ____"
        else:
            text = f"There are {groups} boxes with {each} {many} in each box. There are also {c} more {many} on the table. How many {many} are there in all? ____"
        return _item(gen, {"form": form, "groups": groups, "each": each, "c": c}, text)
    if gen == "add3":
        a, b, c = (rng.randint(lo, hi) for _ in range(3))
        return _item(gen, {"a": a, "b": b, "c": c}, f"{a} + {b} + {c} = ____")
    if gen == "near_double":
        a = rng.randint(max(lo, 2), hi)
        first, second = (a, a + 1) if rng.random() < 0.5 else (a + 1, a)
        return _item(gen, {"a": a, "first": first}, f"{first} + {second} = ____")
    if gen == "missing_sub":
        a = rng.randint(max(lo, 6), hi)
        diff = rng.randint(1, a - 1)
        if rng.random() < 0.5:
            return _item(gen, {"a": a, "diff": diff, "b": a - diff, "blank": "b"}, f"{a} − ____ = {diff}")
        return _item(gen, {"a": a, "diff": diff, "b": a - diff, "blank": "a"}, f"____ − {a - diff} = {diff}")
    if gen == "frac_add_like":
        d = rng.choice(list(denominators))
        n1 = rng.randint(1, d - 1)
        n2 = rng.randint(1, d - 1)
        return _item(gen, {"n1": n1, "n2": n2, "d": d}, f"{n1}/{d} + {n2}/{d} = ____")
    if gen == "mult_big":
        a, b = rng.randint(101, 999), rng.randint(11, 99)
        return _item(gen, {"a": a, "b": b}, f"{a} × {b} = ____")
    if gen == "round_decimal":
        places = rng.choice([0, 1, 1, 2]) if digits >= 2 else rng.choice([0, 1])
        n = Decimal(rng.randint(lo * 1000, hi * 1000)).scaleb(-3)
        word = {0: "the nearest whole number", 1: "the nearest tenth", 2: "the nearest hundredth"}[places]
        return _item(gen, {"n": dec_str(n), "places": places}, f"Round {dec_str(n)} to {word}: ____")
    if gen == "dec_pow10":
        n = Decimal(rng.randint(1, 9999)).scaleb(-rng.choice([1, 2]))
        pw, op = rng.choice([10, 100, 1000]), rng.choice(["mult", "mult", "div"])
        sign = "×" if op == "mult" else "÷"
        return _item(gen, {"n": dec_str(n), "p": pw, "op": op}, f"{dec_str(n)} {sign} {pw:,} = ____")
    if gen in ("gcf", "lcm"):
        while True:
            a, b = rng.randint(4, max(12, hi)), rng.randint(4, max(12, hi))
            if a != b and (gen == "lcm" or gcd(a, b) > 1):
                break
        name = "greatest common factor" if gen == "gcf" else "least common multiple"
        return _item(gen, {"a": a, "b": b}, f"The {name} of {a} and {b} is ____")
    if gen == "dec_div":
        b = rng.randint(2, 9)
        q = Decimal(rng.randint(11, 999)).scaleb(-rng.choice([1, 2]))
        a = q * b
        return _item(gen, {"a": dec_str(a), "b": b}, f"{dec_str(a)} ÷ {b} = ____")
    if gen == "distributive":
        a, b, c = rng.randint(2, 9), rng.choice([10, 20, 30, 40]), rng.randint(1, 9)
        return _item(gen, {"a": a, "b": b, "c": c}, f"{a} × ({b} + {c}) = {a} × {b} + {a} × {c} = ____")
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
