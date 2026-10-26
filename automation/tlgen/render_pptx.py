"""Editable lesson slides (.pptx, 16:9) for Pro packs.

Built from the spec: title → objectives → one slide per vocabulary word →
optional teaching slides (spec["slides"]) → lesson steps → quick-check
questions with answer slides → wrap-up.
"""
from pathlib import Path

from pptx import Presentation
from pptx.dml.color import RGBColor
from pptx.enum.shapes import MSO_SHAPE
from pptx.enum.text import PP_ALIGN
from pptx.util import Inches, Pt

from . import config, illustrations, spec as S

W, H = Inches(13.333), Inches(7.5)


def _rgb(h):
    h = h.lstrip("#")
    return RGBColor(int(h[:2], 16), int(h[2:4], 16), int(h[4:], 16))


class Deck:
    def __init__(self, spec):
        self.spec = spec
        self.c = config.SUBJECT_COLORS[spec["meta"]["subject"]]
        self.p = Presentation()
        self.p.slide_width, self.p.slide_height = W, H
        self.blank = self.p.slide_layouts[6]

    def _bg(self, slide, color):
        fill = slide.background.fill
        fill.solid()
        fill.fore_color.rgb = _rgb(color)

    def _box(self, slide, x, y, w, h, text, size=28, bold=False, color=None, align=PP_ALIGN.LEFT, font="Arial"):
        tb = slide.shapes.add_textbox(x, y, w, h)
        tf = tb.text_frame
        tf.word_wrap = True
        lines = text if isinstance(text, list) else [text]
        for i, line in enumerate(lines):
            para = tf.paragraphs[0] if i == 0 else tf.add_paragraph()
            para.alignment = align
            run = para.add_run()
            run.text = str(line)
            run.font.size, run.font.bold, run.font.name = Pt(size), bold, font
            run.font.color.rgb = _rgb(color or config.BRAND["ink"])
            para.space_after = Pt(size * 0.45)
        return tb

    def _bar(self, slide, title):
        bar = slide.shapes.add_shape(MSO_SHAPE.RECTANGLE, 0, 0, W, Inches(1.25))
        bar.fill.solid()
        bar.fill.fore_color.rgb = _rgb(config.BRAND["navy"])
        bar.line.fill.background()
        accent = slide.shapes.add_shape(MSO_SHAPE.RECTANGLE, 0, Inches(1.25), W, Inches(0.09))
        accent.fill.solid()
        accent.fill.fore_color.rgb = _rgb(self.c["main"])
        accent.line.fill.background()
        self._box(slide, Inches(0.6), Inches(0.22), Inches(11.5), Inches(0.9), title, 34, True, "#FFFFFF")
        self._box(slide, Inches(10.3), Inches(6.9), Inches(2.8), Inches(0.4), "teachluma.com", 12, True, config.BRAND["muted"], PP_ALIGN.RIGHT)

    def _content(self, title, bullets, emoji=None):
        s = self.p.slides.add_slide(self.blank)
        self._bg(s, "#FFFFFF")
        self._bar(s, title)
        width = Inches(8.2) if emoji else Inches(12)
        self._box(s, Inches(0.7), Inches(1.7), width, Inches(5), ["• " + b for b in S.as_lines(bullets)], 26)
        if emoji:
            s.shapes.add_picture(illustrations.emoji_png(emoji, 320), Inches(9.4), Inches(2.1), height=Inches(3.4))
        return s

    def title(self):
        m = self.spec["meta"]
        s = self.p.slides.add_slide(self.blank)
        self._bg(s, config.BRAND["navy"])
        circle = s.shapes.add_shape(MSO_SHAPE.OVAL, Inches(8.4), Inches(-1.6), Inches(6.6), Inches(6.6))
        circle.fill.solid()
        circle.fill.fore_color.rgb = _rgb(self.c["main"])
        circle.line.fill.background()
        for i, em in enumerate((self.spec.get("cover_emoji") or [])[:4]):
            s.shapes.add_picture(illustrations.emoji_png(em, 300), Inches(9.5 + (i % 2) * 2.1), Inches(0.5 + (i // 2) * 2.0), height=Inches(1.7))
        self._box(s, Inches(0.7), Inches(2.0), Inches(7.6), Inches(2.6), m["title"], 46, True, "#FFFFFF")
        self._box(s, Inches(0.7), Inches(4.7), Inches(7.6), Inches(0.7), f"{m['grade']} · {self.c['name']}", 24, True, config.BRAND["gold"])
        self._box(s, Inches(0.7), Inches(6.6), Inches(6), Inches(0.5), "TeachLuma · teachluma.com", 14, True, "#C9D2F0")

    def vocab(self):
        for v in self.spec.get("vocabulary") or []:
            s = self.p.slides.add_slide(self.blank)
            self._bg(s, config.BRAND["cream"])
            card = s.shapes.add_shape(MSO_SHAPE.ROUNDED_RECTANGLE, Inches(0.8), Inches(0.8), Inches(11.7), Inches(5.9))
            card.fill.solid()
            card.fill.fore_color.rgb = RGBColor(255, 255, 255)
            card.line.color.rgb = _rgb(self.c["soft"])
            if v.get("emoji"):
                s.shapes.add_picture(illustrations.emoji_png(v["emoji"], 360), Inches(1.5), Inches(1.6), height=Inches(4.0))
            self._box(s, Inches(6.0), Inches(1.6), Inches(6.2), Inches(1.4), v["word"], 60, True, config.BRAND["navy"])
            self._box(s, Inches(6.0), Inches(3.2), Inches(6.2), Inches(1.4), v["definition"], 24, False, config.BRAND["muted"])
            if v.get("example"):
                self._box(s, Inches(6.0), Inches(4.6), Inches(6.2), Inches(1.6), f"“{v['example']}”", 26, True, self.c["ink"])

    def quick_check(self):
        qs = []
        for part in ("quiz", "worksheet"):
            for sec in (self.spec.get(part) or {}).get("sections", []):
                # Listening items need the teacher's voice, and picture options
                # don't work as slide text, so those stay on paper.
                if sec["type"] in ("multiple_choice", "true_false", "math", "fill_blank") and not sec.get("teacher_script"):
                    qs += [(sec["type"], it) for it in sec["items"][:3]
                           if not (sec["type"] == "multiple_choice" and any(illustrations.is_emoji(str(o)) for o in it["options"]))
                           and not (it.get("render") or {}).get("objects")]
            if len(qs) >= 4:
                break
        for n, (t, it) in enumerate(qs[:5], 1):
            if t == "multiple_choice":
                q, opts, ans = str(it["q"]), [f"{S.LETTERS[k]})  {o}" for k, o in enumerate(it["options"])], f"{S.LETTERS[it['options'].index(it['answer'])]})  {it['answer']}"
            elif t == "true_false":
                q, opts, ans = it["statement"], ["True", "False"], "True" if it["answer"] else "False"
            elif t == "fill_blank":
                q, opts, ans = it["text"].replace("____", "_____"), [], str(it["answer"])
            else:
                q, opts, ans = it["prompt"], [], str(it["answer"])
            for reveal in (False, True):
                s = self.p.slides.add_slide(self.blank)
                self._bg(s, "#FFFFFF")
                self._bar(s, f"Quick check {n}" + (" · answer" if reveal else ""))
                pic = (it.get("render") or {}).get("clock") if isinstance(it, dict) else None
                if illustrations.is_emoji(q):
                    s.shapes.add_picture(illustrations.emoji_png(q, 300), Inches(0.9), Inches(1.8), height=Inches(2.2))
                else:
                    self._box(s, Inches(0.8), Inches(1.7), Inches(8.3 if pic else 11.7), Inches(1.8), q, 34, True)
                if pic:
                    s.shapes.add_picture(illustrations.clock_png(pic[0], pic[1], 400, it["render"].get("hands", True)), Inches(9.4), Inches(1.7), height=Inches(3.4))
                if opts:
                    self._box(s, Inches(1.0), Inches(3.6), Inches(8), Inches(3), opts, 28)
                if reveal:
                    tag = s.shapes.add_shape(MSO_SHAPE.ROUNDED_RECTANGLE, Inches(0.8), Inches(5.6), Inches(7.5), Inches(1.0))
                    tag.fill.solid()
                    tag.fill.fore_color.rgb = _rgb(self.c["main"])
                    tag.line.fill.background()
                    tf = tag.text_frame
                    tf.text = f"Answer: {ans}"
                    tf.paragraphs[0].runs[0].font.size = Pt(30)
                    tf.paragraphs[0].runs[0].font.bold = True
                    tf.paragraphs[0].runs[0].font.color.rgb = RGBColor(255, 255, 255)

    def build(self, out):
        m, lp = self.spec["meta"], self.spec["lesson_plan"]
        self.title()
        self._content("Today we will…", m["learning_objectives"], (self.spec.get("cover_emoji") or [None])[0])
        self.vocab()
        for sl in self.spec.get("slides") or []:
            self._content(sl["title"], sl["bullets"], sl.get("emoji"))
        for st in lp["stages"]:
            self._content(f"{st['name']} · {st['minutes']} min", st["steps"])
        self.quick_check()
        self._content("Great work today!", [f"Remember: {x}" for x in S.as_lines(m["learning_objectives"])[:3]], "🌟")
        self.p.core_properties.title = m["title"]
        self.p.core_properties.author = "TeachLuma"
        self.p.save(out)
        return Path(out)


def build(spec, out):
    return Deck(spec).build(out)
