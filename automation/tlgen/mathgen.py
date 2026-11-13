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
THING_SETS = {   # pass things="christmas" to a word-problem generator for seasonal objects
    "christmas": [("ornament", "ornaments"), ("candy cane", "candy canes"), ("cookie", "cookies"), ("present", "presents"),
                  ("bell", "bells"), ("star", "stars"), ("candle", "candles"), ("card", "cards")],
}


WEEKDAYS = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"]


MONTHS = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"]


def _dnorm(d):
    """Decimal without trailing zeros: 31.40 -> 31.4, 7.00 -> 7."""
    t = format(Decimal(d).quantize(Decimal("0.01")).normalize(), "f")
    return t


def _things(kw):
    return THING_SETS.get(kw.get("things"), THINGS)


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
    "beans": ("Height of bean plants (in meters)", "bean plants", "meter"),
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


PIE_THEMES = {   # title, people noun, verb phrase, categories
    "sports": ("Favorite sports in Class 5B", "students", "chose", ["football", "swimming", "basketball", "badminton", "running"]),
    "fruit": ("Favorite fruit at the school picnic", "children", "chose", ["mango", "apple", "banana", "watermelon", "orange"]),
    "transport": ("How students travel to school", "students", "travel by", ["bus", "car", "bike", "scooter", "train"]),
    "pets": ("Pets owned by families in Year 6", "families", "have a", ["cat", "dog", "fish", "rabbit", "bird"]),
}
PIE_SPLITS = [   # eighths that add up to 8, biggest slice unique
    (4, 2, 1, 1), (3, 2, 2, 1), (4, 2, 2), (2, 2, 1, 1, 2), (3, 3, 1, 1), (5, 2, 1), (4, 1, 1, 1, 1), (3, 2, 1, 1, 1),
]


def pie_data(theme, seed):
    """[(category, eighths)] and the total number of people, fixed by theme + seed."""
    rng = random.Random(f"pie:{theme}:{seed}")
    while True:
        split = list(rng.choice(PIE_SPLITS))
        rng.shuffle(split)
        if sorted(split)[-1] != sorted(split)[-2]:
            break
    cats = rng.sample(PIE_THEMES[theme][3], len(split))
    total = rng.choice([24, 32, 40, 48, 64, 80])
    return list(zip(cats, split)), total


def dec_str(d) -> str:
    """A decimal without trailing zeros (5.30 → 5.3, 4.0 → 4)."""
    t = format(Decimal(str(d)).normalize(), "f")
    return t


SUPERSCRIPT = str.maketrans("0123456789", "⁰¹²³⁴⁵⁶⁷⁸⁹")


def fmt_time(h, m):
    return f"{h}:{m:02d}"


ROMAN = [(1000, "M"), (900, "CM"), (500, "D"), (400, "CD"), (100, "C"), (90, "XC"), (50, "L"), (40, "XL"),
         (10, "X"), (9, "IX"), (5, "V"), (4, "IV"), (1, "I")]


def fmt_12h(h24, m):
    """24-hour clock (0-23) to a 12-hour time with a.m./p.m. (midnight is 12:00 a.m., noon is 12:00 p.m.)."""
    return f"{(h24 - 1) % 12 + 1}:{m:02d} {'a.m.' if h24 < 12 else 'p.m.'}"


def fmt_24h(h24, m):
    return f"{h24:02d}:{m:02d}"


def to_roman(n: int) -> str:
    """Standard Roman numerals with subtractive pairs (4 = IV, 90 = XC), 1 to 3999."""
    out = ""
    for value, letters in ROMAN:
        while n >= value:
            out += letters
            n -= value
    return out


QUAD_SHAPES = ("square", "rectangle", "kite", "quadrilateral")

SYMMETRY_LINES = {"square": 4, "rectangle": 2, "equilateral triangle": 3, "isosceles triangle": 1, "scalene triangle": 0,
                  "regular pentagon": 5, "regular hexagon": 6, "regular octagon": 8, "kite": 1, "rhombus": 2,
                  "parallelogram": 0}

IO_OPS = {"+": lambda x, k: x + k, "−": lambda x, k: x - k, "×": lambda x, k: x * k, "÷": lambda x, k: x // k}


def seq_terms(start, step, n):
    """First n terms of an arithmetic sequence as printable strings (decimals and negatives allowed)."""
    a, d = Decimal(start), Decimal(step)
    return [num(dec_str(a + d * i)) for i in range(n)]


def seq_rule_text(step):
    d = Decimal(step)
    return f"add {dec_str(d)}" if d > 0 else f"subtract {dec_str(-d)}"


def io_apply(rule, x):
    """rule is a list of [op, k] steps, applied in order."""
    for op, k in rule:
        x = IO_OPS[op](x, k)
    return x


def io_rule_text(rule):
    return ", then ".join(f"{op} {k}" for op, k in rule)


def _io_unique_rule(pairs):
    """True when exactly one one-step rule (+, −, ×, ÷ by 1–20) fits every input-output pair."""
    fits = [(op, k) for op in IO_OPS for k in range(1, 21)
            if all((op != "÷" or a % k == 0) and IO_OPS[op](a, k) == b for a, b in pairs)]
    return len(fits) == 1


# ---------------------------------------------------------------- solvers
def solve(gen, p):
    """Recompute the answer for an item from its parameters."""
    if gen == "add":
        return _c(p["a"] + p["b"])
    if gen == "sub":
        return _c(p["a"] - p["b"])
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
    if gen == "mass_cubes":
        heavy, light = (p["x"], p["y"]) if p["a"] > p["b"] else (p["y"], p["x"])
        return {"heavier": heavy, "lighter": light, "diff": abs(p["a"] - p["b"])}[p["ask"]]
    if gen == "estimate":
        r = lambda n: (n + p["place"] // 2) // p["place"] * p["place"]
        return r(p["a"]) + r(p["b"]) if p["op"] == "+" else r(p["a"]) - r(p["b"])
    if gen == "improper_to_mixed":
        w, r = divmod(p["n"], p["d"])
        return f"{w} {r}/{p['d']}"
    if gen == "mixed_to_improper":
        return f"{p['w'] * p['d'] + p['n']}/{p['d']}"
    if gen == "ineq_solve":
        a, c = p["a"], p["c"]
        bound = {"+": c - a, "-": c + a, "*": c // a}[p["op"]]
        return f"x {p['sym']} {bound}"
    if gen == "ineq_check":
        v = {"+": p["x"] + p["a"], "-": p["x"] - p["a"], "*": p["x"] * p["a"]}[p["op"]]
        ok = {"<": v < p["c"], ">": v > p["c"], "≤": v <= p["c"], "≥": v >= p["c"]}[p["sym"]]
        return "yes" if ok else "no"
    if gen == "month_after":
        return MONTHS[(MONTHS.index(p["month"]) + p["n"]) % 12]
    if gen == "one_more_less":
        return p["n"] + 1 if p["dir"] == "more" else p["n"] - 1
    if gen == "turns":
        compass = ["north", "east", "south", "west"]
        steps = {"quarter": 1, "half": 2, "three-quarter": 3}[p["turn"]] * (1 if p["way"] == "clockwise" else -1)
        return compass[(compass.index(p["start"]) + steps) % 4]
    if gen == "capacity_cups":
        big, small = (p["x"], p["y"]) if p["a"] > p["b"] else (p["y"], p["x"])
        return {"more": big, "less": small, "diff": abs(p["a"] - p["b"])}[p["ask"]]
    if gen == "prime_factor":
        n, f, k = p["n"], [], 2
        while n > 1:
            while n % k == 0:
                f.append(k); n //= k
            k += 1
        return " × ".join(str(x) for x in f)
    if gen == "area_trap":
        return (p["a"] + p["b"]) * p["h"] // 2
    if gen == "ten_more_less":
        return p["n"] + 10 if p["dir"] == "more" else p["n"] - 10
    if gen == "prime_check":
        n = p["n"]
        return "prime" if n > 1 and all(n % k for k in range(2, int(n ** 0.5) + 1)) else "composite"
    if gen == "square_num":
        return p["n"] * p["n"]
    if gen == "is_square":
        return "yes" if int(p["n"] ** 0.5) ** 2 == p["n"] else "no"
    if gen == "fdp_convert":
        v = Fraction(p["num"], p["den"])
        if p["to"] == "percent":
            return f"{_dnorm(Decimal(v.numerator) * 100 / Decimal(v.denominator))}%"
        if p["to"] == "decimal":
            return _dnorm(Decimal(v.numerator) / Decimal(v.denominator))
        return f"{v.numerator}/{v.denominator}"
    if gen == "two_step_eq":
        return p["x"]
    if gen == "circle":
        r = Decimal(p["r"])
        if p["ask"] == "diameter":
            return _dnorm(2 * r)
        if p["ask"] == "radius":
            return _dnorm(r)
        return _dnorm(Decimal("3.14") * 2 * r)
    if gen in ("compensate", "bridge_ten"):
        return p["a"] + p["b"]
    if gen == "dec_order":
        return ", ".join(sorted(p["nums"], key=Decimal))
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
        whole, _, frac = dec_str(v).partition(".")
        return f"{int(whole):,}" + (f".{frac}" if frac else "")
    if gen == "gcf":
        return gcd(p["a"], p["b"])
    if gen == "lcm":
        return p["a"] * p["b"] // gcd(p["a"], p["b"])
    if gen == "dec_div":
        return dec_str(Decimal(p["a"]) / Decimal(p["b"]))
    if gen == "distributive":
        return p["a"] * (p["b"] + p["c"])
    if gen == "length_units":
        return p["a"] - p["b"] if p["form"] == "diff" else p["a"] + p["b"]
    if gen == "ordinal":
        n = {"next": p["n"] + 1, "before": p["n"] - 1, "write": p["n"]}[p["form"]]
        return ordinal(n)
    if gen == "frac_numberline":
        return f"{p['k']}/{p['d']}"
    if gen == "sub_zeros":
        return _c(p["a"] - p["b"])
    if gen == "mult_property":
        f = p["form"]
        if f == "identity":
            return p["a"]
        if f == "zero":
            return 0
        return {"comm": p["a"], "assoc": p["b"], "dist": p["c"]}[f]
    if gen == "mult_2x2":
        return _c(p["a"] * p["b"])
    if gen == "time_convert":
        f, a, b = p["form"], p["a"], p.get("b", 0)
        return {"h_min": a * 60, "min_h": a // 60, "hm_min": a * 60 + b, "d_h": a * 24,
                "w_d": a * 7, "min_s": a * 60, "h_min_left": f"{a // 60} h {a % 60} min"}[f]
    if gen == "dec_compare":
        a, b = Decimal(p["a"]), Decimal(p["b"])
        return "<" if a < b else (">" if a > b else "=")
    if gen == "dec_digit":
        d = p["n"].split(".")[1][p["pos"] - 1]
        return dec_str(Decimal(d).scaleb(-p["pos"]))
    if gen == "volume_prism":
        return _c(p["l"] * p["w"] * p["h"])
    if gen == "surface_area":
        l, w, h = p["l"], p["w"], p["h"]
        return _c(2 * (l * w + l * h + w * h))
    if gen == "metric_convert":
        factor = {"m_cm": 100, "km_m": 1000, "kg_g": 1000, "l_ml": 1000, "cm_mm": 10}
        f = p["form"]
        if f in factor:
            v = Decimal(p["v"]) * factor[f]
        else:
            v = Decimal(p["v"]) / {"cm_m": 100, "m_km": 1000, "g_kg": 1000, "ml_l": 1000, "mm_cm": 10}[f]
        whole, _, frac = dec_str(v).partition(".")
        return f"{int(whole):,}" + (f".{frac}" if frac else "")
    if gen == "quadrant":
        if p["form"] == "dist":
            return abs(p["y1"] - p["y2"]) if p["x1"] == p["x2"] else abs(p["x1"] - p["x2"])
        x, y = p["x"], p["y"]
        return "I" if x > 0 and y > 0 else "II" if x < 0 < y else "III" if x < 0 and y < 0 else "IV"
    if gen == "percent_of":
        v = Fraction(p["pct"] * p["n"], 100)
        return int(v) if v.denominator == 1 else frac_str(v)
    if gen == "roman_read":
        return p["n"]
    if gen == "roman_write":
        return to_roman(p["n"])
    if gen == "perimeter_quad":
        return sum(p["sides"])
    if gen == "symmetry_lines":
        return SYMMETRY_LINES[p["shape"]]
    if gen == "date_diff":
        return p["b"] - p["a"]
    if gen == "weekday_after":
        return WEEKDAYS[(WEEKDAYS.index(p["day"]) + p["n"]) % 7]
    if gen == "frac_of":
        return _c(p["total"] * p["n"] // p["d"])
    if gen == "recipe_scale":
        v = Fraction(p["q"]) * p["to"] / p["serves"]
        return int(v) if v.denominator == 1 else dec_str(Decimal(v.numerator) / v.denominator)
    if gen == "nth_rule":
        a, d = p["a"], p["d"]
        c = a - d
        return f"{d}n" + ("" if c == 0 else f" + {c}" if c > 0 else f" − {-c}")
    if gen == "nth_value":
        return _c(p["m"] * p["n"] + p["c"])
    if gen == "angle_missing":
        return {"triangle": lambda: 180 - sum(p["known"]), "line": lambda: 180 - sum(p["known"]),
                "point": lambda: 360 - sum(p["known"]), "isosceles_apex": lambda: (180 - p["known"][0]) // 2,
                "isosceles_base": lambda: 180 - 2 * p["known"][0]}[p["form"]]()
    if gen == "speed":
        f = p["form"]
        if f == "speed":
            return _c(p["d"] // p["t"])
        if f == "distance":
            return _c(p["s"] * p["t"])
        return p["d"] // p["s"]
    if gen == "map_scale":
        f = p["form"]
        if f == "to_real":
            return dec_str(Decimal(p["map"]) * p["per"])
        if f == "to_map":
            return dec_str(Decimal(p["real"]) / p["per"])
        return dec_str(Decimal(p["map"]) * p["ratio"] / 100000)      # 1 : n ratio, map cm → real km
    if gen == "prob_fraction":
        return frac_str(Fraction(p["want"], p["total"]))
    if gen == "prob_word":
        f = Fraction(p["want"], p["total"])
        return ("impossible" if f == 0 else "certain" if f == 1 else "even chance" if f == Fraction(1, 2)
                else "unlikely" if f < Fraction(1, 2) else "likely")
    if gen == "formula":
        f, v = p["form"], p["v"]
        return {"perim": lambda: 2 * (v["l"] + v["w"]), "area": lambda: v["l"] * v["w"],
                "cook": lambda: 40 * v["m"] + 20, "chairs": lambda: 4 * v["t"] + 2,
                "linear": lambda: v["a"] * v["x"] + v["b"], "game": lambda: 5 * v["n"] + 10,
                "area_back": lambda: v["A"] // v["l"]}[f]()
    if gen == "tri_sides":
        n = len(set(p["sides"]))
        return {1: "equilateral", 2: "isosceles", 3: "scalene"}[n]
    if gen == "tri_angles":
        m = max(p["angles"])
        return "right" if m == 90 else ("obtuse" if m > 90 else "acute")
    if gen == "pie":
        data, total = pie_data(p["theme"], p["data_seed"])
        parts = dict(data)
        if p["q"] == "fraction":
            return frac_str(Fraction(parts[p["a"]], 8))
        if p["q"] == "count":
            return total * parts[p["a"]] // 8
        if p["q"] == "more":
            return total * (parts[p["a"]] - parts[p["b"]]) // 8
        if p["q"] == "percent":
            return dec_str(Decimal(parts[p["a"]]) * 100 / 8) + "%"
        if p["q"] == "most":
            return max(data, key=lambda x: x[1])[0]
    if gen == "seq_next":
        return seq_terms(p["start"], p["step"], 5)[4]
    if gen == "seq_missing":
        return seq_terms(p["start"], p["step"], 6)[p["blank"]]
    if gen == "seq_rule":
        return seq_rule_text(p["step"])
    if gen == "seq_term":
        return seq_terms(p["start"], p["step"], p["n"])[-1]
    if gen == "translate_point":
        return f"({p['x'] + p['dx']}, {p['y'] + p['dy']})"
    if gen == "reflect_point":
        if p["axis"] == "x":            # vertical mirror line x = k
            return f"({2 * p['k'] - p['x']}, {p['y']})"
        return f"({p['x']}, {2 * p['k'] - p['y']})"
    if gen in ("io_out", "io_two"):
        return io_apply(p["rule"], p["x"])
    if gen == "io_in":
        return p["x"]
    if gen == "io_rule":
        return io_rule_text(p["rule"])
    if gen == "time_24_to_12":
        return fmt_12h(p["h"], p["m"])
    if gen == "time_12_to_24":
        return fmt_24h(p["h"], p["m"])
    raise ValueError(f"unknown generator {gen}")


def _c(n):
    """Whole number with thousands commas from 1,000 up (matches the prompts)."""
    return f"{n:,}" if n >= 1000 else n


ORD_WORDS = {1: "first", 2: "second", 3: "third", 4: "fourth", 5: "fifth", 6: "sixth", 7: "seventh", 8: "eighth",
             9: "ninth", 10: "tenth", 11: "eleventh", 12: "twelfth", 13: "thirteenth", 14: "fourteenth", 15: "fifteenth",
             16: "sixteenth", 17: "seventeenth", 18: "eighteenth", 19: "nineteenth", 20: "twentieth"}


def art(word: str) -> str:
    return "an" if word[0] in "aeiou" else "a"


def ordinal(n: int) -> str:
    suf = "th" if 10 <= n % 100 <= 20 else {1: "st", 2: "nd", 3: "rd"}.get(n % 10, "th")
    return f"{n}{suf}"


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
         parts=(2, 3, 4), denominators=(2, 3, 4, 6, 8), max_side=10, theme="fruit", brackets=True, whole=False, unit=False, _seed=1, **_):
    if gen == "add":
        while True:
            a, b = rng.randint(lo, hi), rng.randint(lo, hi)
            if regroup is None or _carries(a, b) == regroup:
                return _item("add", {"a": a, "b": b}, f"{_c(a)} + {_c(b)} = ____")
    if gen == "sub":
        while True:
            a, b = sorted((rng.randint(lo, hi), rng.randint(lo, hi)), reverse=True)
            if a != b and (regroup is None or _borrows(a, b) == regroup):
                return _item("sub", {"a": a, "b": b}, f"{_c(a)} − {_c(b)} = ____")
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
        return _item("div", {"a": b * q, "b": b}, f"{_c(b * q)} ÷ {b} = ____")
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
        kind = rng.choice(["wf", "fw"] if unit else ["ff", "ff", "wf", "fw"])
        if unit:                                # Grade 5: unit fraction ÷ whole, whole ÷ unit fraction
            if kind == "wf":
                n1, d1, n2, d2 = rng.randint(2, 9), 1, 1, rng.choice([2, 3, 4, 5, 6, 8])
            else:
                n1, d1, n2, d2 = 1, rng.choice([2, 3, 4, 5, 6, 8]), rng.randint(2, 6), 1
        elif kind == "wf":                      # whole ÷ fraction
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
        if _.get("nonneg"):   # younger grades: whole positive temperatures only
            start = rng.randint(4, 32)
            change = rng.choice([x for x in range(-10, 11) if x != 0 and 0 <= start + x <= 40])
        else:
            start = rng.randint(-12, 8)
            change = rng.choice([x for x in range(-10, 11) if x != 0])
        verb = f"rises by {change}" if change > 0 else f"falls by {-change}"
        return _item(gen, {"start": start, "change": change},
                     f"The temperature is {num(start)} °C. It {verb} {'degree' if abs(change) == 1 else 'degrees'}. What is the new temperature? ____ °C")
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
                return _item(gen, {"a": a, "b": b}, f"{_c(a)} ÷ {b} = ____")
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
        name, (one, many) = rng.choice(NAMES), rng.choice(_things(_))
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
    if gen == "mass_cubes":
        x, y = rng.sample(["apple", "book", "shoe", "banana", "cup", "orange", "toy car", "potato", "brush", "lemon"], 2)
        a, b = rng.sample(range(max(2, lo), max(hi, lo + 3) + 1), 2)
        ask = rng.choice(list(kw_ask)) if (kw_ask := _.get("ask")) else rng.choice(["heavier", "lighter", "diff"])
        q = {"heavier": "Which is heavier? ____", "lighter": "Which is lighter? ____",
             "diff": "How many more cubes is the heavier one? ____"}[ask]
        return _item(gen, {"x": x, "y": y, "a": a, "b": b, "ask": ask}, f"The {x} weighs {a} cubes. The {y} weighs {b} cubes. {q}")
    if gen == "estimate":
        place = _.get("place", 10)
        op = rng.choice(list(_.get("ops", ["+", "-"])))
        while True:
            a, b = rng.randint(lo, hi), rng.randint(lo, hi)
            if op == "-":
                a, b = max(a, b), min(a, b)
            ra, rb = (a + place // 2) // place, (b + place // 2) // place
            if a % place and b % place and ra != rb and (a % place) * 2 != place and (b % place) * 2 != place:
                break
        word = "hundred" if place == 100 else "ten"
        return _item(gen, {"a": a, "b": b, "op": op, "place": place},
                     f"Round to the nearest {word}, then estimate: {_c(a)} {'+' if op == '+' else '−'} {_c(b)} ≈ ____")
    if gen == "improper_to_mixed":
        d = rng.choice(list(denominators))
        while True:
            n = rng.randint(d + 1, d * max(3, hi))
            if n % d and gcd(n % d, d) == 1:
                break
        return _item(gen, {"n": n, "d": d}, f"{n}/{d} = ____ (mixed number)")
    if gen == "mixed_to_improper":
        d = rng.choice(list(denominators))
        w = rng.randint(1, max(2, hi))
        n = rng.choice([k for k in range(1, d) if gcd(k, d) == 1])
        return _item(gen, {"w": w, "n": n, "d": d}, f"{w} {n}/{d} = ____ (improper fraction)")
    if gen in ("ineq_solve", "ineq_check"):
        op = rng.choice(list(_.get("ops", ["+", "-", "*"])))
        sym = rng.choice(["<", ">", "≤", "≥"])
        a = rng.randint(2, 9)
        c = a * rng.randint(2, 12) if op == "*" else rng.randint(a + 2, 30)
        lhs = {"+": f"x + {a}", "-": f"x − {a}", "*": f"{a}x"}[op]
        if gen == "ineq_solve":
            return _item(gen, {"a": a, "c": c, "op": op, "sym": sym}, f"Solve: {lhs} {sym} {c}     ____")
        x = rng.randint(1, 20)
        return _item(gen, {"a": a, "c": c, "op": op, "sym": sym, "x": x}, f"Is x = {x} a solution of {lhs} {sym} {c}? (yes or no) ____")
    if gen == "month_after":
        m = rng.choice(MONTHS); n = rng.choice(list(_.get("steps") or [1]))
        word = "comes after" if n == 1 else f"is {n} months after"
        return _item(gen, {"month": m, "n": n}, f"Which month {word} {m}? ________")
    if gen == "one_more_less":
        n = rng.randint(max(lo, 1), min(hi, 99)); d = rng.choice(list(_.get("dirs") or ["more", "less"]))
        return _item(gen, {"n": n, "dir": d}, f"1 {d} than {n} is ____")
    if gen == "turns":
        start = rng.choice(["north", "east", "south", "west"])
        turn = rng.choice(list(_.get("turn_sizes") or ["quarter", "half", "three-quarter"]))
        way = rng.choice(["clockwise", "counterclockwise"])
        return _item(gen, {"start": start, "turn": turn, "way": way},
                     f"Face {start}. Make a {turn} turn {way}. Now you face ________")
    if gen == "capacity_cups":
        x, y = rng.sample(["jug", "bottle", "bucket", "bowl", "mug", "teapot", "vase", "watering can", "glass", "pan"], 2)
        a, b = rng.sample(range(max(2, lo), max(hi, lo + 3) + 1), 2)
        ask = rng.choice(list(kw_ask)) if (kw_ask := _.get("ask")) else rng.choice(["more", "less", "diff"])
        q = {"more": "Which holds more? ____", "less": "Which holds less? ____",
             "diff": "How many more cups does the bigger one hold? ____"}[ask]
        return _item(gen, {"x": x, "y": y, "a": a, "b": b, "ask": ask}, f"The {x} holds {a} cups. The {y} holds {b} cups. {q}")
    if gen == "prime_factor":
        while True:
            n = rng.randint(max(lo, 6), max(hi, 12))
            if any(n % k == 0 for k in range(2, int(n ** 0.5) + 1)) and len(solve(gen, {"n": n}).split(" × ")) >= 2:
                return _item(gen, {"n": n}, f"Write {n} as a product of prime factors: {n} = ____________")
    if gen == "area_trap":
        while True:
            a, b = sorted(rng.sample(range(2, max_side + 1), 2))
            h = rng.randint(2, max_side)
            if (a + b) * h % 2 == 0:
                return _item(gen, {"a": a, "b": b, "h": h},
                             f"A trapezoid has parallel sides of {a} cm and {b} cm and a height of {h} cm. What is its area? ____ cm²")
    if gen == "ten_more_less":
        n = rng.randint(max(lo, 11), min(hi, 89)); d = rng.choice(list(_.get("dirs") or ["more", "less"]))
        return _item(gen, {"n": n, "dir": d}, f"10 {d} than {n} is ____")
    if gen == "prime_check":
        n = rng.randint(max(lo, 2), hi)
        return _item(gen, {"n": n}, f"Is {n} prime or composite? ________")
    if gen == "square_num":
        n = rng.randint(max(lo, 1), min(hi, 12))
        return _item(gen, {"n": n}, f"{n}² = {n} × {n} = ____")
    if gen == "is_square":
        n = rng.choice([k * k for k in range(2, 11)]) if rng.random() < 0.5 else rng.choice([k for k in range(5, 101) if int(k ** 0.5) ** 2 != k])
        return _item(gen, {"n": n}, f"Is {n} a square number? (yes or no) ____")
    if gen == "fdp_convert":
        to = rng.choice(list(_.get("convert_to") or ["percent", "decimal", "fraction"]))
        den = rng.choice([2, 4, 5, 10, 20, 25])
        num = rng.choice([k for k in range(1, den) if gcd(k, den) == 1])
        v = Fraction(num, den)
        if to == "fraction":
            src = rng.choice(["percent", "decimal"])
            shown = f"{_dnorm(Decimal(num) * 100 / Decimal(den))}%" if src == "percent" else _dnorm(Decimal(num) / Decimal(den))
            return _item(gen, {"num": num, "den": den, "to": to, "from": src}, f"Write {shown} as a fraction in simplest form. ____")
        return _item(gen, {"num": num, "den": den, "to": to}, f"Write {num}/{den} as a {to}. ____")
    if gen == "two_step_eq":
        a, x = rng.randint(2, 9), rng.randint(1, 12); b = rng.randint(1, 20); sign = rng.choice(["+", "-"])
        if sign == "-" and a * x - b < 0:
            sign = "+"
        c = a * x + b if sign == "+" else a * x - b
        return _item(gen, {"a": a, "b": b, "c": c, "sign": sign, "x": x}, f"{a}x {'+' if sign == '+' else '−'} {b} = {c}      x = ____")
    if gen == "circle":
        ask = rng.choice(list(_.get("asks") or ["diameter", "radius", "circumference"]))
        r = rng.randint(2, 15)
        if ask == "diameter":
            return _item(gen, {"r": r, "ask": ask}, f"A circle has a radius of {r} cm. What is its diameter? ____ cm")
        if ask == "radius":
            return _item(gen, {"r": r, "ask": ask}, f"A circle has a diameter of {2 * r} cm. What is its radius? ____ cm")
        given = rng.choice(["radius", "diameter"])
        v = r if given == "radius" else 2 * r
        return _item(gen, {"r": r, "ask": ask, "given": given}, f"A circle has a {given} of {v} cm. Use π ≈ 3.14. Circumference = ____ cm")
    if gen == "compensate":
        a = rng.randint(max(lo, 11), min(hi, 79)); b = rng.choice(list(_.get("adds") or [9, 19, 11, 21]))
        hint = f"add {b + 1}, then take away 1" if b % 10 == 9 else f"add {b - 1}, then add 1 more"
        return _item(gen, {"a": a, "b": b}, f"{a} + {b} = ____   ({hint})")
    if gen == "bridge_ten":
        while True:
            a, b = rng.randint(5, 9), rng.randint(3, 9)
            if a + b > 10:
                break
        return _item(gen, {"a": a, "b": b}, f"{a} + {b} = {a} + {10 - a} + {b - (10 - a)} = ____")
    if gen == "dec_order":
        while True:
            whole = rng.randint(0, 3)
            nums = {dec_str(Decimal(whole) + (Decimal(rng.randint(1, 9)).scaleb(-1) if rng.random() < 0.5 else Decimal(rng.randint(1, 99)).scaleb(-2))) for _i in range(3)}
            if len(nums) == 3:
                nums = list(nums); rng.shuffle(nums)
                return _item(gen, {"nums": nums}, f"Order from smallest to largest: {',  '.join(nums)}   ____")
    if gen == "tens_ones":
        t, o = rng.randint(1, 9), rng.randint(0, 9)
        if rng.random() < 0.5:
            return _item(gen, {"t": t, "o": o, "show": "blocks"}, "What number do the blocks show? ____", {"base10": [0, t, o]})
        unit = lambda n, word: f"{n} {word}" + ("" if n == 1 else "s")
        return _item(gen, {"t": t, "o": o, "show": "words"}, f"{unit(t, 'ten')} and {unit(o, 'one')} = ____")
    if gen == "word_two_step":
        name, (one, many) = rng.choice(NAMES), rng.choice(_things(_))
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
    if gen == "length_units":
        if rng.random() < 0.7:     # small things measured with small units
            unit = rng.choice(["paper clips", "cubes"])
            a1, a2 = rng.choice([("pencil", "crayon"), ("book", "eraser"), ("ribbon", "straw"), ("ruler", "glue stick"), ("shoe", "spoon")])
        else:                      # big things measured with big units
            unit = rng.choice(["hand spans", "footsteps"])
            a1, a2 = rng.choice([("table", "chair"), ("rug", "mat"), ("door", "window"), ("bench", "box")])
        a = rng.randint(max(lo, 4), hi); b = rng.randint(2, a - 1)
        form = rng.choice(["diff", "total"])
        if form == "diff":
            q = f"{art(a1).capitalize()} {a1} is {a} {unit} long. {art(a2).capitalize()} {a2} is {b} {unit} long. How many {unit} longer is the {a1}? ____"
        else:
            q = f"Mia puts {art(a1)} {a1} ({a} {unit} long) and {art(a2)} {a2} ({b} {unit} long) end to end. How long are they together? ____ {unit}"
        return _item(gen, {"form": form, "a": a, "b": b, "unit": unit}, q)
    if gen == "ordinal":
        form = rng.choice(["next", "before", "write"])
        n = rng.randint(2 if form == "before" else 1, hi - (1 if form == "next" else 0))
        words = {"next": f"What comes after {ordinal(n)}? ____", "before": f"What comes before {ordinal(n)}? ____",
                 "write": f"Write {ORD_WORDS[n]} as a number with letters (like 1st): ____"}
        return _item(gen, {"form": form, "n": n}, words[form])
    if gen == "frac_numberline":
        d = rng.choice(list(denominators)); k = rng.randint(1, d - 1)
        return _item(gen, {"d": d, "k": k},
                     "What fraction is at the dot? ____",
                     {"numberline": [d, k]})
    if gen == "sub_zeros":
        while True:
            h = rng.randint(2, 9)
            a = rng.choice([h * 100, h * 100 + rng.randint(1, 9), h * 1000, h * 1000 + rng.randint(1, 9)])
            if a > hi:
                continue
            b = rng.randint(a // 4, a - 11)
            if b % 10 > a % 10:          # must borrow across the zero
                return _item(gen, {"a": a, "b": b}, f"{_c(a)} − {_c(b)} = ____")
    if gen == "mult_property":
        form = rng.choice(["comm", "comm", "assoc", "dist", "dist", "identity", "zero"])
        a, b, c = rng.randint(2, 9), rng.randint(2, 9), rng.randint(2, 9)
        q = {"comm": f"{a} × {b} = {b} × ____", "assoc": f"({a} × {b}) × {c} = {a} × (____ × {c})",
             "dist": f"{a} × {10 + c} = {a} × 10 + {a} × ____", "identity": f"{a} × 1 = ____", "zero": f"{a} × 0 = ____"}[form]
        return _item(gen, {"form": form, "a": a, "b": b, "c": c}, q)
    if gen == "mult_2x2":
        a, b = rng.randint(max(lo, 11), min(hi, 99)), rng.randint(11, 99)
        return _item(gen, {"a": a, "b": b}, f"{a} × {b} = ____")
    if gen == "time_convert":
        form = rng.choice(list(_.get("forms") or ["h_min", "min_h", "hm_min", "d_h", "w_d", "min_s"]))
        if form == "min_h":
            a = 60 * rng.randint(2, 8); q = f"{a} minutes = ____ hours"
        elif form == "hm_min":
            a = rng.randint(1, 5); b = rng.choice([10, 15, 20, 25, 30, 40, 45, 50]); q = f"{a} hours {b} minutes = ____ minutes"
            return _item(gen, {"form": form, "a": a, "b": b}, q)
        else:
            a = rng.randint(2, 9)
            q = {"h_min": f"{a} hours = ____ minutes", "d_h": f"{a} days = ____ hours", "w_d": f"{a} weeks = ____ days",
                 "min_s": f"{a} minutes = ____ seconds"}[form]
        return _item(gen, {"form": form, "a": a}, q)
    if gen == "dec_compare":
        while True:
            a = Decimal(rng.randint(1, 99)).scaleb(-rng.choice([1, 2]))
            b = Decimal(rng.randint(1, 99)).scaleb(-rng.choice([1, 2]))
            if a != b:
                return _item(gen, {"a": dec_str(a), "b": dec_str(b)}, f"{dec_str(a)} ____ {dec_str(b)}")
    if gen == "dec_digit":
        while True:
            n = Decimal(rng.randint(101, 9999)).scaleb(-2)
            t = format(n, "f"); fr = t.split(".")[1]
            pos = rng.choice([1, 2]); d = fr[pos - 1]
            if d != "0" and t.replace(".", "").count(d) == 1:
                return _item(gen, {"n": t, "pos": pos}, f"What is the value of the {d} in {t}? ____")
    if gen == "volume_prism":
        l, w, h = rng.randint(2, max_side), rng.randint(2, max_side), rng.randint(2, max_side)
        return _item(gen, {"l": l, "w": w, "h": h},
                     f"A box is {l} cm long, {w} cm wide and {h} cm high. What is its volume? ____ cubic centimeters")
    if gen == "surface_area":
        l, w, h = rng.randint(2, max_side), rng.randint(2, max_side), rng.randint(2, max_side)
        return _item(gen, {"l": l, "w": w, "h": h},
                     f"A box is {l} cm long, {w} cm wide and {h} cm high. What is its surface area? ____ square centimeters")
    if gen == "metric_convert":
        form = rng.choice(list(_.get("forms") or ["m_cm", "cm_m", "km_m", "m_km", "kg_g", "g_kg", "l_ml", "ml_l", "cm_mm", "mm_cm"]))
        unit = {"m_cm": ("m", "cm"), "cm_m": ("cm", "m"), "km_m": ("km", "m"), "m_km": ("m", "km"), "kg_g": ("kg", "g"),
                "g_kg": ("g", "kg"), "l_ml": ("L", "mL"), "ml_l": ("mL", "L"), "cm_mm": ("cm", "mm"), "mm_cm": ("mm", "cm")}[form]
        if form in ("m_cm", "km_m", "kg_g", "l_ml", "cm_mm"):
            v = Decimal(rng.randint(2, 95)).scaleb(-rng.choice([0, 1]))
        else:
            v = Decimal(rng.randint(15, 950 if form == "mm_cm" else 9500))
        shown = dec_str(v); whole, _, frac = shown.partition(".")
        shown = f"{int(whole):,}" + (f".{frac}" if frac else "")
        return _item(gen, {"form": form, "v": dec_str(v)}, f"{shown} {unit[0]} = ____ {unit[1]}")
    if gen == "quadrant":
        if rng.random() < 0.6:
            x, y = rng.choice([-1, 1]) * rng.randint(1, 9), rng.choice([-1, 1]) * rng.randint(1, 9)
            return _item(gen, {"form": "quad", "x": x, "y": y}, f"In which quadrant is the point ({num(x)}, {num(y)})? ____")
        x1 = rng.randint(-8, 8); y1, y2 = rng.randint(1, 9), -rng.randint(1, 9)
        if rng.random() < 0.5:
            return _item(gen, {"form": "dist", "x1": x1, "y1": y1, "x2": x1, "y2": y2},
                         f"How far apart are ({num(x1)}, {num(y1)}) and ({num(x1)}, {num(y2)})? ____ units")
        return _item(gen, {"form": "dist", "x1": y2, "y1": x1, "x2": y1, "y2": x1},
                     f"How far apart are ({num(y2)}, {num(x1)}) and ({num(y1)}, {num(x1)})? ____ units")
    if gen == "percent_of":
        while True:
            pct = rng.choice([10, 20, 25, 50, 75, 5, 30, 40, 60, 15, 80, 90])
            n = rng.randrange(20, 401, 4 if pct in (25, 75) else 10 if pct in (10, 30, 90) else 5 if pct in (20, 40, 60, 80) else 20)
            if pct * n % 100 == 0:
                return _item(gen, {"pct": pct, "n": n}, f"{pct}% of {n} = ____")
    if gen == "distributive":
        a, b, c = rng.randint(2, 9), rng.choice([10, 20, 30, 40]), rng.randint(1, 9)
        return _item(gen, {"a": a, "b": b, "c": c}, f"{a} × ({b} + {c}) = {a} × {b} + {a} × {c} = ____")
    if gen in ("roman_read", "roman_write"):
        n = rng.randint(max(1, lo), min(hi, 3999))
        prompt = f"{to_roman(n)} = ____" if gen == "roman_read" else f"Write {n} in Roman numerals: ____"
        return _item(gen, {"n": n}, prompt)
    if gen == "perimeter_quad":
        shape = rng.choice(QUAD_SHAPES)
        a, b = rng.randint(2, max_side), rng.randint(2, max_side)
        while b == a:
            b = rng.randint(2, max_side)
        if shape == "square":
            sides = [a, a, a, a]
            prompt = f"A square has sides of {a} cm. What is its perimeter? ____ cm"
        elif shape == "rectangle":
            sides = [a, b, a, b]
            prompt = f"A rectangle is {max(a, b)} cm long and {min(a, b)} cm wide. What is its perimeter? ____ cm"
        elif shape == "kite":
            sides = [a, a, b, b]
            prompt = f"A kite has two sides of {a} cm and two sides of {b} cm. What is its perimeter? ____ cm"
        else:
            sides = [a, b, rng.randint(2, max_side), rng.randint(2, max_side)]
            prompt = ("A quadrilateral has sides of " + ", ".join(f"{x} cm" for x in sides[:3]) +
                      f" and {sides[3]} cm. What is its perimeter? ____ cm")
        return _item(gen, {"shape": shape, "sides": sides}, prompt)
    if gen == "date_diff":
        month = _.get("month") or "December"
        a = rng.randint(1, 20); b = rng.randint(a + 3, 31 if month in ("December", "January") else 28)
        return _item(gen, {"a": a, "b": b, "month": month}, f"How many days is it from {month} {a} to {month} {b}? ____ days")
    if gen == "weekday_after":
        day = rng.choice(WEEKDAYS); n = rng.choice(list(_.get("days") or [2, 3, 5, 7, 8, 9, 10, 14, 15, 21]))
        return _item(gen, {"day": day, "n": n}, f"Today is {day}. What day will it be in {n} {'day' if n == 1 else 'days'}? ________")
    if gen == "frac_of":
        d = rng.choice([2, 3, 4, 5, 6, 8, 10]) if not _.get("denoms") else rng.choice(list(_.get("denoms")))
        n = 1 if _.get("unit") else rng.choice([k for k in range(2 if _.get("nonunit") else 1, d) if gcd(k, d) == 1] or [1])
        total = d * rng.randint(2, 12)
        one, many = rng.choice(_things(_))
        return _item(gen, {"n": n, "d": d, "total": total}, f"What is {n}/{d} of {total} {many}? ____ {many}")
    if gen == "recipe_scale":
        serves = rng.choice([2, 4, 6, 8])
        to = rng.choice([x for x in (2, 3, 4, 6, 8, 10, 12) if x != serves])
        ing = rng.choice([("flour", "g", [100, 200, 240, 300, 400, 480]), ("milk", "ml", [200, 240, 300, 400, 600]),
                          ("sugar", "g", [80, 120, 160, 200]), ("butter", "g", [40, 80, 100, 120, 200]),
                          ("eggs", "", [2, 4, 6]), ("ginger", "teaspoons", [1, 2, 4])])
        name, unit, qs = ing
        while True:
            q = rng.choice(qs)
            v = Fraction(q) * to / serves
            if (unit == "" and v.denominator == 1) or (unit and (v * 2).denominator == 1):
                break
            serves = rng.choice([2, 4, 6, 8]); to = rng.choice([x for x in (2, 3, 4, 6, 8, 10, 12) if x != serves])
        amount = f"{q} {unit} of {name}" if unit else f"{q} {name}"
        ask = f"How many {name}" if unit == "" else f"How much {name}"
        tail = f"____ {unit}" if unit else "____"
        return _item(gen, {"q": q, "serves": serves, "to": to, "ing": name},
                     f"A recipe for {serves} people uses {amount}. {ask} do you need for {to} people? {tail}")
    if gen in ("nth_rule", "nth_value"):
        d = rng.randint(2, 9)
        c = rng.randint(-5, 9)
        if gen == "nth_rule":
            a = d + c
            if a <= 0:
                c = rng.randint(1, 9); a = d + c
            terms = ",  ".join(str(a + d * i) for i in range(4))
            return _item(gen, {"a": a, "d": d}, f"{terms}, …   nth term = ________")
        n = rng.choice([10, 12, 15, 20, 25, 50, 100])
        rule = f"{d}n" + ("" if c == 0 else f" + {c}" if c > 0 else f" − {-c}")
        return _item(gen, {"m": d, "c": c, "n": n}, f"The nth term is {rule}. Find term {n}: ____")
    if gen == "angle_missing":
        forms = _.get("forms") or ["triangle", "line", "point", "isosceles_apex", "isosceles_base"]
        f = rng.choice(list(forms))
        if f == "triangle":
            a = rng.randint(20, 110); b = rng.randint(15, 160 - a)
            return _item(gen, {"form": f, "known": [a, b]}, f"A triangle has angles of {a}° and {b}°. The third angle = ____°")
        if f == "line":
            if rng.random() < 0.5:
                a = rng.randint(15, 165)
                return _item(gen, {"form": f, "known": [a]}, f"Two angles make a straight line. One is {a}°. The other = ____°")
            a = rng.randint(20, 100); b = rng.randint(15, 160 - a)
            return _item(gen, {"form": f, "known": [a, b]}, f"Three angles on a straight line: {a}°, {b}° and ____°")
        if f == "point":
            a = rng.randint(60, 150); b = rng.randint(40, 300 - a - 20); 
            return _item(gen, {"form": f, "known": [a, b]}, f"Three angles meet at a point: {a}°, {b}° and ____°")
        if f == "isosceles_apex":
            base = rng.randrange(30, 86, 5)
            return _item(gen, {"form": f, "known": [180 - 2 * base]},
                         f"An isosceles triangle has a top angle of {180 - 2 * base}°. Each base angle = ____°")
        base = rng.randrange(25, 86, 5)
        return _item(gen, {"form": f, "known": [base]}, f"An isosceles triangle has two base angles of {base}°. The top angle = ____°")
    if gen == "speed":
        f = rng.choice(["speed", "speed", "distance", "time"])
        ctx = rng.choice([("A cyclist", "km", "h", "km/h", (10, 30), (1, 5)), ("A runner", "m", "s", "m/s", (3, 8), (10, 60)),
                          ("A train", "km", "h", "km/h", (60, 200), (1, 4)), ("A snail", "cm", "min", "cm/min", (2, 9), (3, 20)),
                          ("A car", "km", "h", "km/h", (40, 110), (1, 4)), ("A swimmer", "m", "s", "m/s", (1, 2), (20, 100))])
        who, du, tu, su, (s_lo, s_hi), (t_lo, t_hi) = ctx
        sp, t = rng.randint(s_lo, s_hi), rng.randint(t_lo, t_hi)
        if su == "km/h" and sp > 30:
            sp = sp // 5 * 5
        tname = {"h": "hours", "s": "seconds", "min": "minutes"}[tu]
        if t == 1:
            t_lo = 2
            t = 2
        dist = sp * t
        if f == "speed":
            return _item(gen, {"form": f, "d": dist, "t": t}, f"{who} travels {dist:,} {du} in {t} {tname}. Speed = ____ {su}")
        if f == "distance":
            return _item(gen, {"form": f, "s": sp, "t": t}, f"{who} travels at {sp} {su} for {t} {tname}. Distance = ____ {du}")
        return _item(gen, {"form": f, "d": dist, "s": sp}, f"{who} travels {dist:,} {du} at {sp} {su}. Time = ____ {tname}")
    if gen == "map_scale":
        f = rng.choice(["to_real", "to_real", "to_map", "ratio"])
        unit = rng.choice(["km", "km", "m"])
        if f == "ratio":
            ratio = rng.choice([25000, 50000, 100000, 200000])
            m = rng.choice([2, 3, 4, 5, 6, 8, 10, 12]) if ratio != 25000 else rng.choice([4, 8, 12, 2, 6])
            return _item(gen, {"form": f, "map": m, "ratio": ratio},
                         f"Map scale 1 : {ratio:,}. Two towns are {m} cm apart on the map. Real distance: ____ km")
        per = rng.choice([2, 5, 10, 20, 25, 50]) if unit == "km" else rng.choice([10, 20, 50, 100, 200])
        if f == "to_real":
            m = rng.choice(["1.5", "2", "3", "4", "4.5", "6", "7", "8", "9", "12"])
            return _item(gen, {"form": f, "map": m, "per": per},
                         f"Scale: 1 cm = {per} {unit}. Map distance {m} cm. Real distance: ____ {unit}")
        real = per * rng.choice([2, 3, 4, 5, 6, 8, 10, 11])
        if rng.random() < 0.3 and per % 2 == 0:     # half-centimeter map distances only
            real = real + per // 2
        return _item(gen, {"form": f, "real": real, "per": per},
                     f"Scale: 1 cm = {per} {unit}. Real distance {real} {unit}. Map distance: ____ cm")
    if gen in ("prob_fraction", "prob_word"):
        kind = rng.choice(["bag", "bag", "dice", "spinner"])
        if kind == "dice":
            ask = rng.choice([("an even number", 3), ("a 6", 1), ("a number less than 5", 4), ("a number greater than 6", 0),
                              ("a number from 1 to 6", 6), ("a 3 or a 4", 2), ("an odd number", 3), ("a number greater than 1", 5)])
            prm = {"kind": "dice", "event": ask[0], "want": ask[1], "total": 6}
            text = f"You roll a normal dice with the numbers 1 to 6. Rolling {ask[0]}"
        elif kind == "spinner":
            n = rng.choice([4, 5, 8, 10])
            k = rng.randint(0, n)
            col = rng.choice(["red", "blue", "green", "yellow"])
            prm = {"kind": "spinner", "want": k, "total": n, "color": col}
            text = f"A spinner has {n} equal parts and {k} of them {'is' if k == 1 else 'are'} {col}. Landing on {col}"
        else:
            a, b = rng.randint(0, 9), rng.randint(1, 9)
            c1, c2 = rng.sample(["red", "blue", "green", "yellow"], 2)
            prm = {"kind": "bag", "want": a, "total": a + b, "color": c1, "other": c2, "b": b}
            cnt = lambda n, c: f"{n} {c} counter{'' if n == 1 else 's'}"
            text = f"A bag has {cnt(a, c1)} and {cnt(b, c2)}. Picking a {c1} counter"
        if gen == "prob_fraction":
            return _item(gen, prm, f"{text}: probability = ____")
        return _item(gen, prm, f"{text} is impossible, unlikely, an even chance, likely or certain? ________")
    if gen == "formula":
        forms = kw_forms if (kw_forms := _.get("forms")) else ["perim", "area", "cook", "chairs", "linear", "game", "area_back"]
        f = rng.choice(list(forms))
        if f == "perim":
            w = rng.randint(2, 15)
            v = {"l": rng.randint(w + 1, 25), "w": w}
            prompt = f"P = 2 × (l + w). Find P when l = {v['l']} cm and w = {v['w']} cm. P = ____ cm"
        elif f == "area":
            v = {"l": rng.randint(3, 15), "w": rng.randint(2, 12)}
            prompt = f"A = l × w. Find A when l = {v['l']} m and w = {v['w']} m. A = ____ m²"
        elif f == "cook":
            v = {"m": rng.randint(1, 6)}
            prompt = f"Cooking time T = 40m + 20 (T in minutes, m in kg). Find T when m = {v['m']}. T = ____ minutes"
        elif f == "chairs":
            v = {"t": rng.randint(2, 15)}
            prompt = f"Chairs needed c = 4t + 2 (t = number of tables). Find c when t = {v['t']}. c = ____"
        elif f == "linear":
            v = {"a": rng.randint(2, 9), "x": rng.randint(2, 12), "b": rng.randint(1, 20)}
            prompt = f"y = {v['a']}x + {v['b']}. Find y when x = {v['x']}. y = ____"
        elif f == "game":
            v = {"n": rng.randint(2, 20)}
            prompt = f"Points p = 5n + 10 (n = stars collected). Find p when n = {v['n']}. p = ____"
        else:
            l, w = rng.randint(3, 12), rng.randint(2, 12)
            v = {"A": l * w, "l": l}
            prompt = f"A = l × w. A rectangle has A = {l * w} cm² and l = {l} cm. Find w. w = ____ cm"
        return _item(gen, {"form": f, "v": v}, prompt)
    if gen == "tri_sides":
        kind = rng.choice(["equilateral", "isosceles", "isosceles", "scalene", "scalene"])
        while True:
            a = rng.randint(3, 12)
            if kind == "equilateral":
                sides = [a, a, a]
            elif kind == "isosceles":
                b = rng.randint(2, 2 * a - 1)
                sides = [a, a, b]
            else:
                sides = rng.sample(range(3, 15), 3)
            x, y, z = sorted(sides)
            if x + y > z and len(set(sides)) == {"equilateral": 1, "isosceles": 2, "scalene": 3}[kind]:
                break
        rng.shuffle(sides)
        return _item(gen, {"sides": sides}, f"A triangle has sides of {sides[0]} cm, {sides[1]} cm and {sides[2]} cm. Is it equilateral, isosceles or scalene? ________")
    if gen == "tri_angles":
        kind = rng.choice(["acute", "right", "obtuse"])
        while True:
            if kind == "right":
                a = rng.randrange(15, 76, 5); angles = [90, a, 90 - a]
            elif kind == "obtuse":
                big = rng.randrange(95, 151, 5); a = rng.randrange(10, 180 - big - 5 + 1, 5); angles = [big, a, 180 - big - a]
            else:
                a = rng.randrange(40, 86, 5); b = rng.randrange(40, 86, 5); angles = [a, b, 180 - a - b]
            if all(0 < t < 180 for t in angles) and (kind != "acute" or max(angles) < 90):
                break
        rng.shuffle(angles)
        return _item(gen, {"angles": angles}, f"A triangle has angles of {angles[0]}°, {angles[1]}° and {angles[2]}°. Is it acute, right or obtuse? ________")
    if gen == "pie":
        title, people, verb, _cats = PIE_THEMES[theme]
        data, total = pie_data(theme, _seed)
        names = [c for c, _ in data]
        q = rng.choice(["fraction", "count", "count", "more", "percent", "most"])
        params = {"theme": theme, "data_seed": _seed, "q": q}
        if q in ("fraction", "count", "percent"):
            params["a"] = rng.choice(names)
            ask = {"fraction": "What fraction of the {p} {v} {a}? ____",
                   "count": "How many {p} {v} {a}? ____",
                   "percent": "What percentage of the {p} {v} {a}? ____"}[q]
            prompt = ask.format(p=people, v=verb, a=params["a"])
        elif q == "more":
            parts = dict(data)
            a, b = rng.sample(names, 2)
            if parts[a] == parts[b]:
                return _one(gen, rng, theme=theme, _seed=_seed)
            if parts[a] < parts[b]:
                a, b = b, a
            params.update(a=a, b=b)
            prompt = f"How many more {people} {verb} {a} than {b}? ____"
        else:
            prompt = f"Which is the biggest group? ____"
        return _item(gen, params, prompt, {"pie": [theme, _seed]})
    if gen in ("seq_next", "seq_missing", "seq_rule", "seq_term"):
        kind = rng.choice(["int", "int", "dec", "neg"])
        if kind == "int":
            step = rng.choice([-1, 1]) * rng.randint(3, 25)
            start = rng.randint(30, 150) if step < 0 else rng.randint(1, 60)
        elif kind == "dec":
            step = rng.choice(["0.2", "0.5", "1.5", "0.25", "2.5", "0.3"])
            if rng.random() < 0.4:
                step = "-" + step
            start = dec_str(Decimal(rng.randint(10, 80)) / 2) if step.startswith("-") else dec_str(Decimal(rng.randint(0, 20)) / 2)
        else:                                  # counting down through zero
            step = -rng.randint(2, 9)
            start = abs(step) * rng.randint(1, 3) + rng.randint(0, abs(step) - 1)
        if gen == "seq_term":
            n = rng.choice([8, 10, 12, 15, 20])
            if kind == "neg" or (Decimal(str(step)) < 0 and Decimal(str(start)) + Decimal(str(step)) * (n - 1) < 0):
                step = abs(Decimal(str(step)))
            return _item(gen, {"start": str(start), "step": str(step), "n": n},
                         f"A sequence starts at {num(dec_str(Decimal(str(start))))} and the rule is {seq_rule_text(step)}. What is term {n}? ____")
        terms = seq_terms(start, step, 6)
        if gen == "seq_next":
            return _item(gen, {"start": str(start), "step": str(step)}, ",  ".join(terms[:4]) + ",  ____")
        if gen == "seq_missing":
            blank = rng.randint(1, 4)
            shown = ["____" if i == blank else t for i, t in enumerate(terms)]
            return _item(gen, {"start": str(start), "step": str(step), "blank": blank}, ",  ".join(shown))
        return _item(gen, {"start": str(start), "step": str(step)}, ",  ".join(terms[:5]) + "  Rule: ________")
    if gen == "translate_point":
        while True:
            x, y = rng.randint(0, 10), rng.randint(0, 10)
            dx, dy = rng.randint(-6, 6), rng.randint(-6, 6)
            if (dx or dy) and 0 <= x + dx <= 10 and 0 <= y + dy <= 10:
                break
        sq = lambda n: f"{abs(n)} square{'' if abs(n) == 1 else 's'}"
        moves = [f"{sq(dx)} {'right' if dx > 0 else 'left'}"] if dx else []
        moves += [f"{sq(dy)} {'up' if dy > 0 else 'down'}"] if dy else []
        return _item(gen, {"x": x, "y": y, "dx": dx, "dy": dy},
                     f"Translate ({x}, {y}) {' and '.join(moves)}. New point: ____")
    if gen == "reflect_point":
        while True:
            axis, k = rng.choice(["x", "y"]), rng.randint(2, 8)
            x, y = rng.randint(0, 10), rng.randint(0, 10)
            v = x if axis == "x" else y
            if v != k and 0 <= 2 * k - v <= 10:
                break
        line = f"the vertical mirror line x = {k}" if axis == "x" else f"the horizontal mirror line y = {k}"
        return _item(gen, {"axis": axis, "k": k, "x": x, "y": y}, f"Reflect ({x}, {y}) in {line}. New point: ____")
    if gen == "symmetry_lines":
        shape = rng.choice(sorted(SYMMETRY_LINES))
        return _item(gen, {"shape": shape}, f"How many lines of symmetry does this {shape} have? ____", {"shape": shape})
    if gen in ("io_out", "io_in", "io_rule", "io_two"):
        def one_step():
            op = rng.choice(["+", "−", "×", "÷"])
            k = rng.randint(2, 10) if op in "×÷" else rng.randint(3, 25)
            return [op, k]
        while True:
            if gen == "io_two":
                rule = [["×", rng.randint(2, 5)], [rng.choice(["+", "−"]), rng.randint(1, 9)]]
            else:
                rule = [one_step()]
            op, k = rule[0]
            x = rng.randint(1, 12) * (k if op == "÷" else 1) if gen != "io_two" else rng.randint(2, 12)
            if op == "−" and x <= k:
                x += k + rng.randint(1, 10)
            y = io_apply(rule, x)
            if y < 0:
                continue
            r = io_rule_text(rule)
            if gen == "io_out" or gen == "io_two":
                return _item(gen, {"rule": rule, "x": x}, f"Rule: {r}.   In: {x}  →  Out: ____")
            if gen == "io_in":
                return _item(gen, {"rule": rule, "x": x}, f"Rule: {r}.   In: ____  →  Out: {y}")
            ins = sorted(rng.sample(range(1, 13), 3))
            if op == "÷":
                ins = [i * k for i in ins]
            if op == "−":
                ins = [i + k for i in ins]
            pairs = [(a, io_apply(rule, a)) for a in ins]
            if _io_unique_rule(pairs):
                shown = ",   ".join(f"{a} → {b}" for a, b in pairs)
                return _item(gen, {"rule": rule, "ins": ins}, f"{shown}.   Rule: ________")
    if gen in ("time_24_to_12", "time_12_to_24"):
        # mostly afternoon/evening times (the hard part), with some mornings, noon and midnight hours
        h = rng.choice([0, 12] if rng.random() < 0.15 else list(range(1, 12)) if rng.random() < 0.3 else list(range(13, 24)))
        m = rng.randrange(0, 60, step)
        if gen == "time_24_to_12":
            return _item(gen, {"h": h, "m": m}, f"Write {fmt_24h(h, m)} as a 12-hour time: ________")
        return _item(gen, {"h": h, "m": m}, f"Write {fmt_12h(h, m)} as a 24-hour time: ________")
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
