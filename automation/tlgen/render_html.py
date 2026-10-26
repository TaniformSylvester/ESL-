"""HTML for the printable PDFs and the cover cards (rendered by Chromium).

Layout follows the TeachLuma brand: Fredoka headings, Nunito body, navy +
gold, with the subject colour (ESL blue, Math orange, Science green).
`compact` (0–2) tightens spacing/type; fixes.py raises it when a pack runs
over its page budget.
"""
import re
from html import escape

from . import config, illustrations, spec as S


def _font_css():
    rules = []
    for fam, weight, name in (("Fredoka", 600, "fredoka-latin-600-normal"), ("Fredoka", 700, "fredoka-latin-700-normal"),
                              ("Nunito", 400, "nunito-latin-400-normal"), ("Nunito", 600, "nunito-latin-600-normal"),
                              ("Nunito", 700, "nunito-latin-700-normal"), ("Nunito", 800, "nunito-latin-800-normal")):
        rules.append(f"@font-face{{font-family:'{fam}';font-weight:{weight};src:url('{(config.FONT_DIR / (name + '.woff2')).as_uri()}') format('woff2');}}")
    return "".join(rules)


def _css(subject, compact=0, footer=""):
    c = config.SUBJECT_COLORS[subject]
    b = config.BRAND
    base = [11.5, 10.8, 10.2][compact]
    gap = [1.0, 0.75, 0.55][compact]
    return f"""
{_font_css()}
:root{{--main:{c['main']};--ink:{c['ink']};--soft:{c['soft']};--navy:{b['navy']};--gold:{b['gold']};--text:{b['ink']};--muted:{b['muted']};--gap:{gap};}}
@page{{size:A4;margin:13mm 13mm 15mm 13mm;
  @bottom-left{{content:"{escape(footer)}";font:600 8pt Nunito;color:#8A93A8;}}
  @bottom-right{{content:"Page " counter(page) " of " counter(pages);font:600 8pt Nunito;color:#8A93A8;}}}}
@page cover{{margin:0;@bottom-left{{content:none}}@bottom-right{{content:none}}}}
*{{box-sizing:border-box}}
html,body{{margin:0;padding:0}}
body{{font-family:Nunito,Arial,sans-serif;font-size:{base}pt;color:var(--text);line-height:1.38;-webkit-print-color-adjust:exact;print-color-adjust:exact}}
h1,h2,h3{{font-family:Fredoka,Nunito,sans-serif;color:var(--navy);margin:0}}
.emoji{{font-family:'Noto Color Emoji';line-height:1}}
.emoji.big{{font-size:1.9em;vertical-align:-0.25em;margin-right:1.5mm}}
.page{{break-before:page}}
.page:first-child{{break-before:auto}}
.cover{{page:cover;height:297mm;width:210mm;position:relative;overflow:hidden;background:#FFF8EC}}
.cover .band{{background:var(--navy);color:#fff;padding:22mm 18mm 16mm;position:relative}}
.cover .band:after{{content:"";position:absolute;left:0;right:0;bottom:-14mm;height:28mm;background:var(--navy);border-radius:0 0 50% 50%/0 0 100% 100%}}
.cover .logo{{height:12mm;margin-bottom:12mm}}
.cover h1{{color:#fff;font-size:30pt;line-height:1.1;position:relative;z-index:1}}
.cover .sub{{color:var(--gold);font:700 13pt Nunito;margin-top:4mm;position:relative;z-index:1}}
.badges{{display:flex;gap:3mm;flex-wrap:wrap;margin-top:6mm;position:relative;z-index:1}}
.badge{{display:inline-block;border-radius:20px;padding:1.6mm 4.5mm;font:800 9.5pt Nunito;background:#fff;color:var(--navy)}}
.badge.subj{{background:var(--main);color:#fff}}
.badge.pro{{background:var(--gold);color:var(--navy)}}
.badge.free{{background:#1FB5A8;color:#fff}}
.cover .art{{text-align:center;margin:26mm 0 8mm;font-size:54pt;letter-spacing:6mm}}
.cover .inside{{margin:0 18mm;background:#fff;border-radius:6mm;padding:7mm 9mm;border:2px solid var(--soft)}}
.cover .inside h3{{font-size:14pt;margin-bottom:3mm}}
.cover .inside ul{{margin:0;padding-left:5mm;columns:2;column-gap:10mm}}
.cover .inside li{{margin:1mm 0;font-weight:600}}
.cover .foot{{position:absolute;bottom:12mm;left:18mm;right:18mm;display:flex;justify-content:space-between;font:700 10pt Nunito;color:var(--muted)}}
.head{{display:flex;justify-content:space-between;align-items:flex-end;border-bottom:3px solid var(--main);padding-bottom:2mm;margin-bottom:calc(5mm*var(--gap))}}
.head .kicker{{font:800 8.5pt Nunito;letter-spacing:.08em;text-transform:uppercase;color:var(--main)}}
.head>div{{flex:1;padding-right:6mm}}
.head h2{{font-size:19pt;line-height:1.15}}
.head img{{height:8mm;flex:none}}
.namebar{{display:flex;gap:6mm;margin:0 0 calc(5mm*var(--gap));font-weight:700}}
.namebar span{{flex:1;border-bottom:1.5px solid #9AA3B8;padding-bottom:1mm}}
.namebar span.short{{flex:0 0 45mm}}
.grid{{display:grid;grid-template-columns:repeat(4,1fr);gap:3mm;margin-bottom:calc(5mm*var(--gap))}}
.grid div{{background:var(--soft);border-radius:3mm;padding:2.5mm 3mm}}
.grid b{{display:block;font-size:8pt;text-transform:uppercase;color:var(--ink);letter-spacing:.05em}}
.sect,.blk{{break-inside:avoid}}
.lp{{font-size:.9em}}
.lp table.t td{{padding-top:calc(1.6mm*var(--gap));padding-bottom:calc(1.6mm*var(--gap))}}
h3.sec{{break-after:avoid;font-size:13pt;margin:calc(5mm*var(--gap)) 0 calc(2mm*var(--gap));display:flex;align-items:center;gap:2.5mm}}
h3.sec .num{{background:var(--main);color:#fff;border-radius:50%;width:7.5mm;height:7.5mm;display:inline-flex;align-items:center;justify-content:center;font-size:11pt;flex:none}}
.instr{{color:var(--muted);font-weight:700;margin:0 0 calc(3mm*var(--gap))}}
ul.tight,ol.tight{{margin:0 0 calc(3mm*var(--gap));padding-left:6mm}}
ul.tight li,ol.tight li{{margin:calc(1mm*var(--gap)) 0}}
table{{border-collapse:collapse;width:100%}}
table.t th{{background:var(--navy);color:#fff;font:700 9.5pt Nunito;text-align:left;padding:2mm 3mm}}
table.t td{{border-bottom:1px solid #E1E5EF;padding:calc(2.2mm*var(--gap)) 3mm;vertical-align:top}}
table.t tr:nth-child(even) td{{background:#F8F9FD}}
table.t td.min{{white-space:nowrap;font-weight:800;color:var(--ink)}}
.item{{break-inside:avoid}}
.bank{{border:2px dashed var(--main);border-radius:3mm;padding:2.5mm 4mm;margin:0 0 calc(4mm*var(--gap));display:flex;flex-wrap:wrap;gap:2mm 7mm;font-weight:800;color:var(--ink);background:#fff}}
.bank b{{color:var(--muted);font-weight:800;margin-right:2mm}}
.line{{display:inline-block;min-width:32mm;border-bottom:1.5px solid #6B7488;height:1.1em;vertical-align:baseline}}
.wline{{border-bottom:1.5px solid #B7BED0;height:calc(9mm*var(--gap) + 2mm)}}
ol.items{{margin:0;padding-left:7mm}}
ol.items>li{{margin:0 0 calc(3.2mm*var(--gap));break-inside:avoid}}
.opts{{display:flex;gap:7mm;flex-wrap:wrap;margin-top:1mm}}
.opt:before{{content:"";display:inline-block;width:3.6mm;height:3.6mm;border:1.6px solid var(--navy);border-radius:50%;margin-right:1.6mm;vertical-align:-0.5mm}}
.match{{display:grid;grid-template-columns:1fr 18mm 1fr;row-gap:calc(3.5mm*var(--gap));align-items:center}}
.match .l,.match .r{{border:1.5px solid #D5DAE8;border-radius:3mm;padding:2mm 3mm;font-weight:700;display:flex;align-items:center;gap:3mm}}
.match .dot{{text-align:center;color:#9AA3B8;font-weight:800}}
.match .emoji{{font-size:20pt}}
.cards{{display:grid;grid-template-columns:repeat(4,1fr);gap:4mm}}
.card{{border:1.5px solid #D5DAE8;border-radius:3mm;padding:3mm;text-align:center;break-inside:avoid}}
.card .emoji{{font-size:30pt;display:block;margin:1mm 0 3mm}}
.seq td{{padding:2mm 3mm;border:1.5px solid #D5DAE8}}
.seq td.box{{width:14mm;text-align:center;font-weight:800;color:#B7BED0}}
.sort th{{background:var(--soft);color:var(--ink);font:800 10.5pt Nunito;padding:2mm;border:1.5px solid #D5DAE8}}
.sort td{{border:1.5px solid #D5DAE8;height:calc(9mm*var(--gap) + 2mm)}}
.draw{{border:1.5px solid #D5DAE8;border-radius:3mm;height:calc(48mm*var(--gap) + 10mm);margin:2mm 0}}
.mathgrid{{display:grid;grid-template-columns:1fr 1fr;gap:calc(5mm*var(--gap)) 8mm}}
.mathgrid.cols3{{grid-template-columns:1fr 1fr 1fr}}
.mq{{display:flex;gap:2.5mm;align-items:flex-start;break-inside:avoid;font-size:1.06em;font-weight:700}}
.mq .n{{color:var(--main);font-weight:800;min-width:6mm}}
.cmp{{display:inline-block;width:9mm;height:9mm;border:2px solid var(--navy);border-radius:50%;vertical-align:middle;margin:0 2.5mm}}
u.dig{{text-decoration:underline 2px var(--main);text-underline-offset:3px;color:var(--ink)}}
.mq svg{{display:block;margin-top:1.5mm}}
.flash{{display:grid;grid-template-columns:1fr 1fr;grid-auto-rows:78mm;gap:0}}
.flash .fc{{border:1.5px dashed #9AA3B8;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:5mm}}
.flash .fc .emoji{{font-size:62pt;margin-bottom:5mm}}
.flash .fc .w{{font:700 26pt Fredoka;color:var(--navy)}}
.flash .fc .d{{font-size:10pt;color:var(--muted);margin-top:2mm}}
.rub th,.rub td{{border:1.5px solid #D5DAE8;padding:2.5mm;vertical-align:top;font-size:.93em}}
.rub th{{background:var(--navy);color:#fff}}
.rub td:first-child{{font-weight:800;background:var(--soft);width:30mm}}
.key h3{{font-size:11.5pt;margin:4mm 0 1.5mm;color:var(--ink)}}
.key .ans{{columns:2;column-gap:10mm}}
.key .ans div{{break-inside:avoid;margin:0 0 1.2mm}}
.key .ans b{{color:var(--main);display:inline-block;min-width:8mm}}
.note{{background:#FFF6DD;border-left:4px solid var(--gold);padding:2.5mm 4mm;border-radius:2mm;margin:calc(3mm*var(--gap)) 0}}
.two{{display:grid;grid-template-columns:1fr 1fr;gap:6mm}}
"""


def e(s):
    return escape(str(s))


def _emoji(s, size=None):
    style = f' style="font-size:{size}"' if size else ""
    return f'<span class="emoji"{style}>{e(s)}</span>'


EMOJI_RUN = re.compile("([\U0001F000-\U0001FAFF\u2600-\u27BF\u2B00-\u2BFF](?:[\uFE0F\u200D\u20E3]|[\U0001F000-\U0001FAFF\u2600-\u27BF])*)")


def _text(s):
    """Escaped text with any emoji wrapped so they render large and in colour."""
    return EMOJI_RUN.sub(lambda m: f'<span class="emoji big">{m.group(1)}</span>', e(s))


def _cell(s):
    """Text, or a big emoji when the value is an emoji."""
    return _emoji(s) if illustrations.is_emoji(str(s)) else e(s)


def _head(kicker, title, logo=True):
    img = f'<img src="{config.LOGO_SVG.as_uri()}" alt="">' if logo else ""
    return f'<div class="head"><div><div class="kicker">{e(kicker)}</div><h2>{e(title)}</h2></div>{img}</div>'


# ---------------------------------------------------------------- sections
def render_section(sec, n, seed):
    t = sec["type"]
    title = sec.get("title") or {"match": "Match", "fill_blank": "Fill in the blanks", "multiple_choice": "Choose the answer",
                                 "true_false": "True or false?", "short_answer": "Answer the questions", "label": "Label the pictures",
                                 "sequence": "Put them in order", "sort": "Sort the words", "draw_write": "Draw and write",
                                 "math": "Solve"}[t]
    out = [f'<div class="sect"><h3 class="sec"><span class="num">{n}</span>{e(title)}</h3><p class="instr">{e(sec["instructions"])}</p>']
    bank = S.word_bank(sec, seed)
    if bank and t != "sort":
        out.append('<div class="bank"><b>Word bank:</b>' + "".join(f"<span>{e(w)}</span>" for w in bank) + "</div>")
    items = sec.get("items", [])
    if t == "match":
        rights = S.shuffled([it["right"] for it in items], seed)
        rows = []
        for i, it in enumerate(items):
            rows.append(f'<div class="l item"><b>{i + 1}.</b> {_cell(it["left"])}</div><div class="dot">• &nbsp; •</div>'
                        f'<div class="r item"><b>{S.LETTERS[i]}.</b> {_cell(rights[i])}</div>')
        out.append(f'<div class="match">{"".join(rows)}</div>')
    elif t == "fill_blank":
        def blank(it):
            width = "12mm" if len(str(it["answer"])) <= 2 else "32mm"
            return _text(it["text"]).replace("____", "___").replace("___", f'<span class="line" style="min-width:{width}"></span>')
        out.append('<ol class="items">' + "".join(f"<li>{blank(it)}</li>" for it in items) + "</ol>")
    elif t == "multiple_choice":
        out.append('<ol class="items">' + "".join(
            f"<li>{_emoji(it['q'], '28pt') if illustrations.is_emoji(str(it['q'])) else _text(it['q'])}<div class='opts'>" + "".join(f"<span class='opt'>{S.LETTERS[k]}) {_cell(o)}</span>" for k, o in enumerate(it["options"]))
            + "</div></li>" for it in items) + "</ol>")
    elif t == "true_false":
        out.append('<ol class="items">' + "".join(
            f"<li>{e(it['statement'])}<div class='opts'><span class='opt'>True</span><span class='opt'>False</span></div></li>" for it in items) + "</ol>")
    elif t == "short_answer":
        lines = sec.get("lines", 2)
        out.append('<ol class="items">' + "".join(
            f"<li>{e(it['q'])}" + '<div class="wline"></div>' * lines + "</li>" for it in items) + "</ol>")
    elif t == "label":
        out.append('<div class="cards">' + "".join(
            f'<div class="card"><b>{i + 1}</b>{_emoji(it["emoji"])}<span class="line" style="min-width:90%"></span></div>' for i, it in enumerate(items)) + "</div>")
    elif t == "sequence":
        order = S.shuffled(items, seed)
        out.append('<table class="seq">' + "".join(f'<tr class="item"><td class="box">&nbsp;</td><td><b>{S.LETTERS[i]}.</b> {e(s)}</td></tr>'
                                                  for i, s in enumerate(order)) + "</table>")
    elif t == "sort":
        out.append('<div class="bank"><b>Words:</b>' + "".join(f"<span>{e(w)}</span>" for w in bank) + "</div>")
        groups = list(sec["groups"])
        rows = max(len(v) for v in sec["groups"].values())
        out.append('<table class="sort"><tr>' + "".join(f"<th>{e(g)}</th>" for g in groups) + "</tr>"
                   + "".join("<tr>" + "<td></td>" * len(groups) + "</tr>" for _ in range(rows)) + "</table>")
    elif t == "draw_write":
        out.append('<ol class="items">' + "".join(
            f"<li>{e(it['prompt'])}<div class='draw'></div>" + '<div class="wline"></div>' * it.get("lines", 1) + "</li>" for it in items) + "</ol>")
    elif t == "math":
        has_pic = any(it.get("render") for it in items)
        cols = "" if has_pic or any(len(it["prompt"]) > 24 for it in items) else " cols3"
        cells = []
        for i, it in enumerate(items):
            pic = ""
            r = it.get("render") or {}
            if "clock" in r:
                pic = illustrations.clock_svg(r["clock"][0], r["clock"][1], 140, r.get("hands", True))
            elif "fraction" in r:
                pic = illustrations.fraction_svg(*r["fraction"], size=100)
            elif "base10" in r:
                pic = illustrations.base10_svg(*r["base10"])
            elif "rect" in r and max(r["rect"]) <= 12:
                pic = illustrations.rect_svg(*r["rect"], unit=12)
            prompt = e(it["prompt"])
            if it["gen"] == "expanded_form":
                prompt = prompt.replace("______________", '<span class="line" style="min-width:52mm"></span>')
            if it["gen"] == "digit_value":
                # Underline the digit being asked about inside the number.
                n, pos = str(it["params"]["n"]), it["params"]["pos"]
                marked = f"{n[:pos]}<u class='dig'>{n[pos]}</u>{n[pos + 1:]}"
                prompt = prompt[:prompt.rfind(n)] + marked + prompt[prompt.rfind(n) + len(n):]
            prompt = prompt.replace("◯", '<span class="cmp"></span>').replace("____", '<span class="line" style="min-width:18mm"></span>')
            cells.append(f'<div class="mq"><span class="n">{i + 1}.</span><div>{prompt}{pic}</div></div>')
        out.append(f'<div class="mathgrid{cols}">{"".join(cells)}</div>')
    out.append("</div>")
    return "".join(out)


# ---------------------------------------------------------------- pages
def whats_included(spec):
    m = spec["meta"]
    lines = ["Teacher lesson plan with timings, materials and differentiation"]
    if spec.get("vocabulary"):
        lines.append(f"Key vocabulary list ({len(spec['vocabulary'])} words with meanings and examples)")
    ws = spec["worksheet"]["sections"]
    lines.append(f"Student worksheet ({len(ws)} activities)")
    if m["tier"] == "pro":
        lines.append(f"Quiz / assessment ({len(spec['quiz']['sections'])} parts)")
        lines.append("Homework practice page")
        lines.append(f"Printable flashcards ({len(spec['vocabulary'])} picture cards)")
        lines.append("Assessment rubric (4 levels)")
        lines.append("Lesson slides (editable PowerPoint)")
        lines.append("Editable Word version of all student pages")
    else:
        lines.append("Editable Word version of the worksheet")
    lines.append("Complete answer key (also as a separate PDF)")
    return lines


def cover(spec):
    m = spec["meta"]
    c = config.SUBJECT_COLORS[m["subject"]]
    tier = '<span class="badge pro">PRO</span>' if m["tier"] == "pro" else '<span class="badge free">FREE</span>'
    art = "".join(_emoji(x) for x in (spec.get("cover_emoji") or [])[:4])
    inside = "".join(f"<li>{e(x)}</li>" for x in whats_included(spec))
    return f"""<section class="cover page"><div class="band"><img class="logo" src="{config.LOGO_WHITE_SVG.as_uri()}" alt="TeachLuma">
<h1>{e(m['title'])}</h1><div class="sub">{e(m['topic'])}</div>
<div class="badges"><span class="badge subj">{e(c['name'])}</span><span class="badge">{e(m['grade'])}</span>{tier}<span class="badge">{e(m['suggested_duration'])}</span></div></div>
<div class="art">{art}</div>
<div class="inside"><h3>Inside this pack</h3><ul>{inside}</ul></div>
<div class="foot"><span>teachluma.com</span><span>For classroom use by the purchasing teacher</span></div></section>"""


def lesson_plan(spec):
    m, lp = spec["meta"], spec["lesson_plan"]
    out = [f'<section class="page lp">{_head("Teacher lesson plan", m["title"])}',
           '<div class="grid">' + "".join(f"<div><b>{k}</b>{e(v)}</div>" for k, v in (
               ("Subject", config.SUBJECT_COLORS[m["subject"]]["name"]), ("Grade", m["grade"]),
               ("Duration", m["suggested_duration"]), ("Level", m.get("recommended_level") or m["grade"]))) + "</div>",
           f'<p>{e(m["overview"])}</p>',
           '<div class="two blk"><div><h3 class="sec">Learning objectives</h3><ul class="tight">' + "".join(f"<li>{e(x)}</li>" for x in S.as_lines(m["learning_objectives"])) + "</ul></div>",
           '<div><h3 class="sec">Materials</h3><ul class="tight">' + "".join(f"<li>{e(x)}</li>" for x in S.as_lines(lp["materials"])) + "</ul></div></div>"]
    if spec.get("vocabulary"):
        out.append('<div class="blk"><h3 class="sec">Key vocabulary</h3><table class="t"><thead><tr><th></th><th>Word</th><th>Meaning</th><th>Example</th></tr></thead>' + "".join(
            f'<tr class="item"><td>{_emoji(v.get("emoji", ""), "15pt")}</td><td><b>{e(v["word"])}</b></td><td>{e(v["definition"])}</td><td>{e(v.get("example", ""))}</td></tr>'
            for v in spec["vocabulary"]) + "</table></div>")
    out.append('<div class="blk"><h3 class="sec">Lesson procedure</h3><table class="t"><thead><tr><th>Stage</th><th>Time</th><th>What to do</th></tr></thead>' + "".join(
        f'<tr class="item"><td><b>{e(st["name"])}</b></td><td class="min">{st["minutes"]} min</td><td><ul class="tight">'
        + "".join(f"<li>{e(x)}</li>" for x in S.as_lines(st["steps"])) + "</ul></td></tr>" for st in lp["stages"]) + "</table></div>")
    diff = lp.get("differentiation") or {}
    if diff:
        out.append('<div class="two blk">' + "".join(
            f'<div><h3 class="sec">{e(lbl)}</h3><ul class="tight">' + "".join(f"<li>{e(x)}</li>" for x in S.as_lines(diff.get(k))) + "</ul></div>"
            for k, lbl in (("support", "Support"), ("challenge", "Challenge")) if diff.get(k)) + "</div>")
    tail = ""
    if lp.get("assessment"):
        tail += f'<h3 class="sec">Assessment</h3><p>{e(lp["assessment"])}</p>'
    if m.get("teacher_tips"):
        tail += f'<div class="note"><b>Teacher tip:</b> {e(m["teacher_tips"])}</div>'
    out.append(f'<div class="blk">{tail}</div>')
    out.append("</section>")
    return "".join(out)


def student_part(spec, part):
    m, doc = spec["meta"], spec[part]
    out = [f'<section class="page">{_head(S.PART_TITLES[part] + " · " + m["grade"], doc.get("title") or m["topic"])}',
           '<div class="namebar"><span>Name:</span><span class="short">Date:</span>'
           + ('<span class="short">Score: &nbsp;&nbsp;&nbsp;&nbsp; / ' + str(_points(doc)) + "</span>" if part == "quiz" else "") + "</div>"]
    for i, sec in enumerate(doc["sections"]):
        out.append(render_section(sec, i + 1, S.seed_for(spec, part, i)))
    out.append("</section>")
    return "".join(out)


def _points(doc):
    return sum(len(s.get("items", [])) or sum(len(v) for v in s.get("groups", {}).values()) for s in doc["sections"])


def flashcards(spec, standalone=False):
    vocab = spec["vocabulary"]
    pages = []
    for i in range(0, len(vocab), 6):
        cards = "".join(f'<div class="fc">{_emoji(v.get("emoji", ""))}<div class="w">{e(v["word"])}</div><div class="d">{e(v["definition"])}</div></div>'
                        for v in vocab[i:i + 6])
        head = _head("Flashcards · cut along the dashed lines", spec["meta"]["topic"]) if i == 0 else ""
        pages.append(f'<section class="page">{head}<div class="flash">{cards}</div></section>')
    return "".join(pages)


def rubric(spec):
    r = spec["rubric"]
    head = "<tr><th>Criteria</th>" + "".join(f"<th>{lvl}</th>" for lvl in ("4 · Excellent", "3 · Good", "2 · Developing", "1 · Beginning")) + "</tr>"
    rows = "".join("<tr class='item'><td>" + e(c["name"]) + "</td>" + "".join(f"<td>{e(x)}</td>" for x in c["levels"]) + "</tr>" for c in r["criteria"])
    note = f'<p class="note">{e(r["note"])}</p>' if r.get("note") else ""
    return f'<section class="page">{_head("Assessment rubric", spec["meta"]["topic"])}<table class="rub">{head}{rows}</table>{note}</section>'


def answer_key(spec, standalone=False):
    out = [f'<section class="page key">{_head("Answer key", spec["meta"]["title"])}']
    for part in S.parts(spec):
        out.append(f'<h2 style="font-size:14pt;margin-top:3mm">{S.PART_TITLES[part]}</h2>')
        for i, sec in enumerate(spec[part]["sections"]):
            title = sec.get("title") or sec["type"].replace("_", " ").title()
            ans = S.section_answers(sec, S.seed_for(spec, part, i))
            out.append(f"<div class='blk'><h3>{i + 1}. {e(title)}</h3><div class='ans'>" + "".join(f"<div><b>{e(a)}</b> {e(b)}</div>" for a, b in ans) + "</div></div>")
    out.append("</section>")
    return "".join(out)


def document(body, subject, compact=0, footer="", title=""):
    return f'<!doctype html><html><head><meta charset="utf-8"><title>{e(title)}</title><style>{_css(subject, compact, footer)}</style></head><body>{body}</body></html>'


def main_pack(spec, compact=0):
    m = spec["meta"]
    body = [cover(spec), lesson_plan(spec)] + [student_part(spec, p) for p in S.parts(spec)]
    if m["tier"] == "pro":
        body += [flashcards(spec), rubric(spec)]
    body.append(answer_key(spec))
    return document("".join(body), m["subject"], compact, f"TeachLuma · {m['title']}", m["title"])


def key_only(spec, compact=0):
    m = spec["meta"]
    return document(answer_key(spec, True), m["subject"], compact, f"TeachLuma · Answer key · {m['title']}", "Answer key")


def flashcards_only(spec, compact=0):
    m = spec["meta"]
    return document(flashcards(spec, True), m["subject"], compact, f"TeachLuma · Flashcards · {m['topic']}", "Flashcards")


# ---------------------------------------------------------------- cover cards (thumbnail)
def thumb_card(spec, width=1200, height=675):
    m = spec["meta"]
    c = config.SUBJECT_COLORS[m["subject"]]
    b = config.BRAND
    tier = ("PRO", b["gold"], b["navy"]) if m["tier"] == "pro" else ("FREE", "#1FB5A8", "#fff")
    art = "".join(_emoji(x) for x in (spec.get("cover_emoji") or [])[:3])
    title = m["title"]
    size = 64 if len(title) <= 34 else (54 if len(title) <= 50 else 46)
    return f"""<!doctype html><html><head><meta charset="utf-8"><style>{_font_css()}
html,body{{margin:0;width:{width}px;height:{height}px;overflow:hidden}}
body{{background:linear-gradient(135deg,{b['navy']} 0%,{b['navy_dark']} 100%);font-family:Nunito;color:#fff;position:relative}}
.blob{{position:absolute;right:-120px;top:-120px;width:620px;height:620px;border-radius:50%;background:{c['main']};opacity:.95}}
.blob2{{position:absolute;right:250px;bottom:-200px;width:340px;height:340px;border-radius:50%;background:{b['gold']};opacity:.9}}
.art{{position:absolute;right:70px;top:120px;width:420px;display:flex;flex-wrap:wrap;justify-content:center;gap:10px 26px;font-family:'Noto Color Emoji';font-size:120px;line-height:1.1}}
.txt{{position:absolute;left:64px;top:56px;width:620px}}
img{{height:46px}}
h1{{font-family:Fredoka;font-weight:700;font-size:{size}px;line-height:1.05;margin:44px 0 18px;text-shadow:0 3px 0 rgba(0,0,0,.18)}}
.sub{{font-size:26px;font-weight:800;color:{b['gold']}}}
.badges{{position:absolute;left:64px;bottom:56px;display:flex;gap:14px}}
.badges span{{border-radius:40px;padding:10px 24px;font-weight:800;font-size:24px;background:#fff;color:{b['navy']}}}
</style></head><body><div class="blob"></div><div class="blob2"></div><div class="art">{art}</div>
<div class="txt"><img src="{config.LOGO_WHITE_SVG.as_uri()}"><h1>{e(title)}</h1><div class="sub">{e(m['grade'])} · {e(c['name'])}</div></div>
<div class="badges"><span style="background:{c['main']};color:#fff">{e(m['resource_type'])}</span><span style="background:{tier[1]};color:{tier[2]}">{tier[0]}</span></div>
</body></html>"""
