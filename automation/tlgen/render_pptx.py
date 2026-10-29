"""Editable lesson slides (.pptx, 16:9) for Pro packs.

A teaching deck that follows the lesson plan, with speaker notes on every
slide:

  title → objectives → lesson agenda → warm-up → key words overview →
  one slide per word → teaching slides (spec["slides"]) → guided practice →
  one slide per worksheet activity (with a "try one together" example) →
  independent practice → quick check (question, then answer) → what we
  learned → exit ticket → wrap-up

Teaching slides support several layouts (see AGENT_GUIDE.md):
  bullets (default), steps, compare, example, question, diagram.
"""
import re
from pathlib import Path

from pptx import Presentation
from pptx.dml.color import RGBColor
from pptx.enum.shapes import MSO_SHAPE
from pptx.enum.text import MSO_ANCHOR, PP_ALIGN
from pptx.util import Inches, Pt

from . import config, illustrations, spec as S

W, H = Inches(13.333), Inches(7.5)
WHITE = "#FFFFFF"


def _rgb(h):
    h = h.lstrip("#")
    return RGBColor(int(h[:2], 16), int(h[2:4], 16), int(h[4:], 16))


ANSWER_AFTER_QUOTE = re.compile(r'^(.*["”])\s*\(([^()]+)\)\s*$')


def _split_answer(step):
    """'Ask: "Which is colder?" (−9 °C)' → ('Ask: "Which is colder?"', '−9 °C'); otherwise (step, None)."""
    m = ANSWER_AFTER_QUOTE.match(step)
    return (m.group(1), m.group(2)) if m else (step, None)


class Deck:
    def __init__(self, spec):
        self.spec = spec
        self.m = spec["meta"]
        self.c = config.SUBJECT_COLORS[self.m["subject"]]
        self.p = Presentation()
        self.p.slide_width, self.p.slide_height = W, H
        self.blank = self.p.slide_layouts[6]

    # ------------------------------------------------------------ helpers
    def _slide(self, bg=WHITE, notes=""):
        s = self.p.slides.add_slide(self.blank)
        fill = s.background.fill
        fill.solid()
        fill.fore_color.rgb = _rgb(bg)
        if notes:
            s.notes_slide.notes_text_frame.text = notes.strip()
        n = len(self.p.slides)
        if n > 1:
            self._box(s, Inches(0.4), Inches(6.95), Inches(1.5), Inches(0.4), str(n), 12, True, config.BRAND["muted"])
            self._box(s, Inches(8.8), Inches(6.95), Inches(4.2), Inches(0.4), f"{self.m['topic']} · teachluma.com", 12, True,
                      config.BRAND["muted"], PP_ALIGN.RIGHT)
        return s

    def _box(self, slide, x, y, w, h, text, size=26, bold=False, color=None, align=PP_ALIGN.LEFT, anchor=None):
        tb = slide.shapes.add_textbox(x, y, w, h)
        tf = tb.text_frame
        tf.word_wrap = True
        if anchor:
            tf.vertical_anchor = anchor
        for i, line in enumerate(text if isinstance(text, list) else [text]):
            para = tf.paragraphs[0] if i == 0 else tf.add_paragraph()
            para.alignment = align
            run = para.add_run()
            run.text = str(line)
            run.font.size, run.font.bold, run.font.name = Pt(size), bold, "Arial"
            run.font.color.rgb = _rgb(color or config.BRAND["ink"])
            para.space_after = Pt(size * 0.4)
        return tb

    def _shape(self, slide, kind, x, y, w, h, fill, line=None, text=None, size=22, color=WHITE, bold=True):
        sh = slide.shapes.add_shape(kind, x, y, w, h)
        sh.fill.solid()
        sh.fill.fore_color.rgb = _rgb(fill)
        if line:
            sh.line.color.rgb = _rgb(line)
        else:
            sh.line.fill.background()
        if text is not None:
            tf = sh.text_frame
            tf.word_wrap = True
            tf.vertical_anchor = MSO_ANCHOR.MIDDLE
            for i, t in enumerate(text if isinstance(text, list) else [text]):
                para = tf.paragraphs[0] if i == 0 else tf.add_paragraph()
                para.alignment = PP_ALIGN.CENTER
                run = para.add_run()
                run.text = str(t)
                run.font.size, run.font.bold, run.font.name = Pt(size), bold, "Arial"
                run.font.color.rgb = _rgb(color)
        return sh

    def _bar(self, slide, title, kicker=None):
        self._shape(slide, MSO_SHAPE.RECTANGLE, 0, 0, W, Inches(1.3), config.BRAND["navy"])
        self._shape(slide, MSO_SHAPE.RECTANGLE, 0, Inches(1.3), W, Inches(0.09), self.c["main"])
        if kicker:
            self._box(slide, Inches(0.6), Inches(0.12), Inches(11), Inches(0.4), kicker.upper(), 13, True, config.BRAND["gold"])
        self._box(slide, Inches(0.6), Inches(0.38 if kicker else 0.24), Inches(12), Inches(0.9), title, 32, True, WHITE)

    def _pic(self, slide, emoji, x, y, h):
        if emoji:
            slide.shapes.add_picture(illustrations.emoji_png(emoji, 360), x, y, height=h)

    def _bullets(self, title, bullets, emoji=None, notes="", kicker=None):
        s = self._slide(notes=notes)
        self._bar(s, title, kicker)
        width = Inches(8.3) if emoji else Inches(12)
        self._box(s, Inches(0.7), Inches(1.8), width, Inches(4.9), ["• " + b for b in S.as_lines(bullets)], 26)
        if emoji:
            self._pic(s, emoji, Inches(9.4), Inches(2.1), Inches(3.4))
        return s

    # ------------------------------------------------------------ fixed slides
    def title(self):
        lp = self.spec["lesson_plan"]
        s = self._slide(config.BRAND["navy"], notes=(
            f"{self.m['title']} ({self.m['grade']}, {self.m['suggested_duration']}).\n{self.m['overview']}\n"
            f"Materials: {'; '.join(S.as_lines(lp['materials']))}."))
        self._shape(s, MSO_SHAPE.OVAL, Inches(8.4), Inches(-1.6), Inches(6.6), Inches(6.6), self.c["main"])
        for i, em in enumerate((self.spec.get("cover_emoji") or [])[:4]):
            self._pic(s, em, Inches(9.5 + (i % 2) * 2.1), Inches(0.5 + (i // 2) * 2.0), Inches(1.7))
        self._box(s, Inches(0.7), Inches(1.9), Inches(7.6), Inches(2.7), self.m["title"], 44, True, WHITE)
        self._box(s, Inches(0.7), Inches(4.7), Inches(7.6), Inches(0.7), f"{self.m['grade']} · {self.c['name']} · {self.m['suggested_duration']}",
                  22, True, config.BRAND["gold"])
        self._box(s, Inches(0.7), Inches(6.6), Inches(6), Inches(0.5), "TeachLuma · teachluma.com", 14, True, "#C9D2F0")

    def objectives(self):
        objs = S.as_lines(self.m["learning_objectives"])
        s = self._slide(notes="Read the goals aloud. Ask students what they already know about the topic, "
                              "and come back to these goals in the summary at the end of the lesson.")
        self._bar(s, "Today we will…", "Learning goals")
        for i, o in enumerate(objs[:4]):
            y = Inches(1.75 + i * 1.25)
            self._shape(s, MSO_SHAPE.OVAL, Inches(0.7), y + Inches(0.1), Inches(0.75), Inches(0.75), self.c["main"], text=str(i + 1), size=24)
            self._box(s, Inches(1.7), y, Inches(10.8), Inches(1.15), o, 24, False, config.BRAND["ink"], anchor=MSO_ANCHOR.MIDDLE)

    def agenda(self):
        stages = self.spec["lesson_plan"]["stages"]
        total = sum(st["minutes"] for st in stages)
        s = self._slide(notes="Lesson plan at a glance: " + "; ".join(f"{st['name']} ({st['minutes']} min)" for st in stages) + ".")
        self._bar(s, "Our lesson today", f"{total} minutes")
        n = len(stages)
        gap = Inches(0.2)
        w = (W - Inches(1.2) - gap * (n - 1)) / n
        shades = [self.c["main"], config.BRAND["navy"], self.c["ink"], config.BRAND["teal"], config.BRAND["gold"], self.c["main"]]
        for i, st in enumerate(stages):
            x = Inches(0.6) + (w + gap) * i
            self._shape(s, MSO_SHAPE.ROUNDED_RECTANGLE, x, Inches(2.0), w, Inches(1.5), shades[i % len(shades)],
                        text=[st["name"], f"{st['minutes']} min"], size=20,
                        color=config.BRAND["navy"] if shades[i % len(shades)] == config.BRAND["gold"] else WHITE)
            first = S.as_lines(st["steps"])[0] if S.as_lines(st["steps"]) else ""
            self._box(s, x, Inches(3.7), w, Inches(3), first, 15, False, config.BRAND["muted"])

    def stage(self, st, kicker="Lesson stage"):
        steps = S.as_lines(st["steps"])
        s = self._slide(notes=f"{st['name']} ({st['minutes']} minutes).\n" + "\n".join(f"- {x}" for x in steps))
        self._bar(s, st["name"], f"{kicker} · {st['minutes']} min")
        # Answers in brackets after a quoted question stay in the notes, off the screen.
        self._box(s, Inches(0.7), Inches(1.8), Inches(12), Inches(4.9), ["• " + _split_answer(x)[0] for x in steps], 24)

    def vocab_overview(self):
        vocab = self.spec.get("vocabulary") or []
        if not vocab:
            return
        s = self._slide(notes="Introduce all the key words first. Say each word, students repeat, then point to a picture and ask "
                              "students to say the word. Each word has its own slide next.")
        self._bar(s, "Key words", f"{len(vocab)} words")
        cols = 5 if len(vocab) > 8 else 4
        cw, ch = Inches(12.1) / cols, Inches(2.6)
        for i, v in enumerate(vocab[:10]):
            x = Inches(0.6) + cw * (i % cols)
            y = Inches(1.65) + ch * (i // cols)
            self._shape(s, MSO_SHAPE.ROUNDED_RECTANGLE, x + Inches(0.08), y, cw - Inches(0.16), ch - Inches(0.15), WHITE, line=self.c["soft"])
            self._pic(s, v.get("emoji"), x + cw / 2 - Inches(0.65), y + Inches(0.2), Inches(1.3))
            self._box(s, x, y + Inches(1.6), cw, Inches(0.8), v["word"], 18, True, config.BRAND["navy"], PP_ALIGN.CENTER)

    def vocab(self):
        for v in self.spec.get("vocabulary") or []:
            s = self._slide(config.BRAND["cream"], notes=(
                f"Say \"{v['word']}\" clearly; students repeat twice. Meaning: {v['definition']}. "
                f"Read the example together, then ask two students to make their own sentence with \"{v['word']}\"."))
            self._shape(s, MSO_SHAPE.ROUNDED_RECTANGLE, Inches(0.8), Inches(0.7), Inches(11.7), Inches(6.0), WHITE, line=self.c["soft"])
            self._pic(s, v.get("emoji"), Inches(1.4), Inches(1.6), Inches(3.8))
            self._box(s, Inches(5.8), Inches(1.2), Inches(6.4), Inches(1.3), v["word"], 56, True, config.BRAND["navy"])
            self._box(s, Inches(5.8), Inches(2.6), Inches(6.4), Inches(1.3), v["definition"], 24, False, config.BRAND["muted"])
            if v.get("example"):
                self._box(s, Inches(5.8), Inches(3.9), Inches(6.4), Inches(1.2), f"“{v['example']}”", 24, True, self.c["ink"])
            self._shape(s, MSO_SHAPE.ROUNDED_RECTANGLE, Inches(5.8), Inches(5.4), Inches(6.2), Inches(0.8), self.c["soft"],
                        text=f"Use it: make your own sentence with “{v['word']}”.", size=17, color=self.c["ink"], bold=True)

    # ------------------------------------------------------------ teaching slides
    def teaching(self, sl):
        kind = sl.get("type", "bullets")
        notes = sl.get("notes", "")
        if kind == "steps":
            steps = S.as_lines(sl["steps"])
            s = self._slide(notes=notes)
            self._bar(s, sl["title"], sl.get("kicker", "Step by step"))
            n = len(steps)
            if n <= 4:            # horizontal flow with arrows
                gap = Inches(0.45)
                w = (W - Inches(1.2) - gap * (n - 1)) / n
                for i, st in enumerate(steps):
                    x = Inches(0.6) + (w + gap) * i
                    self._shape(s, MSO_SHAPE.OVAL, x + w / 2 - Inches(0.4), Inches(1.9), Inches(0.8), Inches(0.8), self.c["main"], text=str(i + 1), size=24)
                    self._shape(s, MSO_SHAPE.ROUNDED_RECTANGLE, x, Inches(2.9), w, Inches(3.2), self.c["soft"], text=st, size=19, color=config.BRAND["ink"])
                    if i < n - 1:
                        self._shape(s, MSO_SHAPE.RIGHT_ARROW, x + w + Inches(0.05), Inches(4.2), gap - Inches(0.1), Inches(0.5), config.BRAND["gold"])
            else:                 # vertical numbered list
                for i, st in enumerate(steps[:6]):
                    y = Inches(1.7 + i * 0.85)
                    self._shape(s, MSO_SHAPE.OVAL, Inches(0.7), y, Inches(0.65), Inches(0.65), self.c["main"], text=str(i + 1), size=20)
                    self._box(s, Inches(1.6), y - Inches(0.05), Inches(11), Inches(0.8), st, 22, False, anchor=MSO_ANCHOR.MIDDLE)
        elif kind == "compare":
            s = self._slide(notes=notes)
            self._bar(s, sl["title"], sl.get("kicker", "Compare"))
            for i, side in enumerate((sl["left"], sl["right"])):
                x = Inches(0.6 + i * 6.25)
                self._shape(s, MSO_SHAPE.ROUNDED_RECTANGLE, x, Inches(1.7), Inches(5.95), Inches(0.8), self.c["main"] if i == 0 else config.BRAND["navy"],
                            text=side["title"], size=24)
                self._shape(s, MSO_SHAPE.ROUNDED_RECTANGLE, x, Inches(2.6), Inches(5.95), Inches(4.1), WHITE, line=self.c["soft"])
                has_pic = bool(side.get("emoji"))
                if has_pic:
                    self._pic(s, side["emoji"], x + Inches(4.3), Inches(2.8), Inches(1.4))
                self._box(s, x + Inches(0.25), Inches(2.8), Inches(4.0 if has_pic else 5.4), Inches(3.8), ["• " + b for b in S.as_lines(side["bullets"])], 21)
        elif kind == "example":
            for reveal in (False, True):
                s = self._slide(notes=notes + ("" if reveal else "\nLet students try first, then show the next slide."))
                self._bar(s, sl["title"], "Worked example" + (" · solution" if reveal else ""))
                self._shape(s, MSO_SHAPE.ROUNDED_RECTANGLE, Inches(0.7), Inches(1.75), Inches(11.9), Inches(1.3), self.c["soft"],
                            text=sl["problem"], size=28, color=config.BRAND["navy"])
                if reveal:
                    self._box(s, Inches(0.9), Inches(3.3), Inches(11.5), Inches(2.4), [f"{i + 1}.  {x}" for i, x in enumerate(S.as_lines(sl["steps"]))], 23)
                    self._shape(s, MSO_SHAPE.ROUNDED_RECTANGLE, Inches(0.7), Inches(5.85), Inches(7.5), Inches(0.85), self.c["main"],
                                text=f"Answer: {sl['answer']}", size=26)
                else:
                    self._box(s, Inches(0.9), Inches(3.5), Inches(11.5), Inches(1.5), "Try it on your whiteboard. Show your steps!", 26, True, self.c["ink"])
        elif kind == "question":
            s = self._slide(config.BRAND["cream"], notes=notes + (f"\nPossible answer: {sl['answer']}" if sl.get("answer") else ""))
            self._bar(s, sl.get("title", "Think, pair, share"), sl.get("kicker", "Discuss"))
            self._pic(s, sl.get("emoji") or "💬", Inches(0.9), Inches(2.1), Inches(2.6))
            self._box(s, Inches(4.0), Inches(2.0), Inches(8.6), Inches(3.0), sl["question"], 34, True, config.BRAND["navy"], anchor=MSO_ANCHOR.MIDDLE)
            self._box(s, Inches(4.0), Inches(5.2), Inches(8.6), Inches(1.2), "Think on your own · talk to a partner · share with the class", 18, True,
                      config.BRAND["muted"])
        elif kind == "diagram":
            labels = S.as_lines(sl["labels"])
            s = self._slide(notes=notes)
            self._bar(s, sl["title"], sl.get("kicker", "Look closely"))
            self._pic(s, sl.get("emoji"), Inches(4.65), Inches(1.9), Inches(4.0))
            half = (len(labels) + 1) // 2
            for col, chunk in enumerate((labels[:half], labels[half:])):
                for i, lab in enumerate(chunk):
                    x = Inches(0.6) if col == 0 else Inches(9.0)
                    self._shape(s, MSO_SHAPE.ROUNDED_RECTANGLE, x, Inches(1.9 + i * 1.15), Inches(3.75), Inches(0.95),
                                self.c["soft"], text=lab, size=18, color=config.BRAND["ink"])
        else:
            self._bullets(sl["title"], sl["bullets"], sl.get("emoji"), notes, sl.get("kicker"))

    # ------------------------------------------------------------ activities and checks
    def _sample(self, sec, seed):
        """A first item shown as 'let's try one together', with its answer for the notes."""
        t, items = sec["type"], sec.get("items") or []
        ans = S.section_answers(sec, seed)
        if t == "sort":
            groups = list(sec["groups"])
            word = sec["groups"][groups[0]][0]
            return f"Where does “{word}” go?", f"{word} → {groups[0]}"
        if not items:
            return None, None
        it = items[0]
        if t == "match":
            return f"1. {it['left']}  →  ?", f"{it['left']} → {it['right']}"
        if t == "multiple_choice":
            if any(illustrations.is_picture(str(o)) for o in it["options"]):    # drawings can't be written as slide text
                return None, S._shown(it["answer"])
            return f"{it['q']}   ({' / '.join(map(str, it['options']))})", str(it["answer"])
        if t == "true_false":
            return it["statement"] + "   True or false?", "True" if it["answer"] else "False"
        if t == "fill_blank":
            return it["text"], str(it["answer"])
        if t == "label":
            return None, str(it["answer"])
        if t == "sequence":
            return "Which step comes first?", items[0]
        if t in ("short_answer",):
            return it["q"], str(it["answer"])
        if t == "draw_write":
            return it["prompt"], it.get("guidance", "")
        if t == "math":
            if (it.get("render") or {}).get("pictograph") or (it.get("render") or {}).get("lineplot"):
                return None, str(it["answer"])
            return it["prompt"], str(it["answer"])
        return None, ans[0][1] if ans else None

    def activities(self, part="worksheet"):
        doc = self.spec.get(part) or {}
        for i, sec in enumerate(doc.get("sections", [])):
            title = sec.get("title") or sec["type"].replace("_", " ").capitalize()
            sample, answer = self._sample(sec, S.seed_for(self.spec, part, i))
            count = len(sec.get("items") or []) or sum(len(v) for v in (sec.get("groups") or {}).values())
            notes = f"Activity {i + 1}: {title}. {sec['instructions']}"
            if sec.get("teacher_script"):
                notes += "\nTeacher script (read aloud twice):\n" + "\n".join(S.as_lines(sec["teacher_script"]))
            if answer:
                notes += f"\nModel the first one together. Answer: {answer}"
            s = self._slide(notes=notes)
            self._bar(s, title, f"{S.PART_TITLES[part]} · activity {i + 1} of {len(doc['sections'])}")
            self._shape(s, MSO_SHAPE.ROUNDED_RECTANGLE, Inches(0.7), Inches(1.75), Inches(11.9), Inches(1.1), self.c["soft"],
                        text=sec["instructions"], size=24, color=config.BRAND["navy"])
            self._box(s, Inches(0.8), Inches(3.05), Inches(6), Inches(0.5), f"{count} questions" if count else "", 18, True, config.BRAND["muted"])
            if sample:
                self._box(s, Inches(0.8), Inches(3.6), Inches(11.6), Inches(0.5), "Let's try one together:", 20, True, self.c["ink"])
                self._shape(s, MSO_SHAPE.ROUNDED_RECTANGLE, Inches(0.8), Inches(4.2), Inches(11.6), Inches(1.6), WHITE, line=self.c["main"],
                            text=sample, size=24, color=config.BRAND["ink"], bold=False)
            elif sec["type"] == "label" and sec.get("items"):
                self._pic(s, sec["items"][0]["emoji"], Inches(5.4), Inches(3.6), Inches(2.4))
            if sec.get("teacher_script"):
                self._box(s, Inches(0.8), Inches(6.0), Inches(11.6), Inches(0.6), "Listen carefully: your teacher will read it twice.", 18, True,
                          config.BRAND["muted"])

    def quick_check(self):
        qs = []
        for part in ("worksheet", "homework"):     # never the quiz: it is the later assessment
            for sec in (self.spec.get(part) or {}).get("sections", []):
                # Listening items need the teacher's voice, and picture options
                # don't work as slide text, so those stay on paper.
                if sec["type"] in ("multiple_choice", "true_false", "math", "fill_blank") and not sec.get("teacher_script"):
                    # The worksheet's first item is already the "let's try one together" sample.
                    first = 1 if part == "worksheet" else 0
                    qs += [(sec["type"], it) for it in sec["items"][first:first + 2]
                           if not (sec["type"] == "multiple_choice" and any(illustrations.is_emoji(str(o)) for o in it["options"]))
                           and not (it.get("render") or {}).get("objects") and not (it.get("render") or {}).get("pictograph")
                           and not (it.get("render") or {}).get("lineplot")]
            if len(qs) >= 6:
                break
        for n, (t, it) in enumerate(qs[:6], 1):
            if t == "multiple_choice":
                q, opts, ans = str(it["q"]), [f"{S.LETTERS[k]})  {o}" for k, o in enumerate(it["options"])], f"{S.LETTERS[it['options'].index(it['answer'])]})  {it['answer']}"
            elif t == "true_false":
                q, opts, ans = it["statement"], ["True", "False"], "True" if it["answer"] else "False"
            elif t == "fill_blank":
                q, opts, ans = it["text"].replace("____", "_____"), [], str(it["answer"])
            else:
                q, opts, ans = it["prompt"], [], str(it["answer"])
            r = it.get("render") or {}
            for reveal in (False, True):
                s = self._slide(notes=("Ask students to show their answer on whiteboards or with fingers before revealing."
                                       if not reveal else f"Answer: {ans}. Ask a student to explain how they got it."))
                self._bar(s, f"Quick check {n}" + (" · answer" if reveal else ""), "Show me")
                pic = None
                if "clock" in r:
                    pic = illustrations.clock_png(r["clock"][0], r["clock"][1], 400, r.get("hands", True))
                elif "thermo" in r:
                    pic = illustrations.thermometer_png(r["thermo"])
                elif "base10" in r:
                    pic = illustrations.base10_png(*r["base10"])
                if illustrations.is_emoji(q):
                    self._pic(s, q, Inches(0.9), Inches(1.8), Inches(2.2))
                else:
                    self._box(s, Inches(0.8), Inches(1.7), Inches(8.3 if pic else 11.7), Inches(1.8), q, 32, True)
                if pic:
                    s.shapes.add_picture(pic, Inches(9.4), Inches(1.7), height=Inches(3.6))
                if opts:
                    self._box(s, Inches(1.0), Inches(3.6), Inches(8), Inches(2.4), opts, 28)
                if reveal:
                    self._shape(s, MSO_SHAPE.ROUNDED_RECTANGLE, Inches(0.8), Inches(5.7), Inches(7.8), Inches(0.95), self.c["main"],
                                text=f"Answer: {ans}", size=28)

    def summary(self):
        objs = S.as_lines(self.m["learning_objectives"])
        words = [v["word"] for v in self.spec.get("vocabulary") or []]
        s = self._slide(notes="Go back to the learning goals. Students give a thumbs up, sideways or down for each goal. "
                              "Ask one student to use two key words in a sentence.")
        self._bar(s, "What we learned", "Summary")
        self._box(s, Inches(0.7), Inches(1.75), Inches(12), Inches(3.6), ["✓  " + o for o in objs], 23)
        if words:
            self._shape(s, MSO_SHAPE.ROUNDED_RECTANGLE, Inches(0.7), Inches(5.5), Inches(11.9), Inches(1.1), self.c["soft"],
                        text="Key words: " + " · ".join(words), size=20, color=self.c["ink"])

    def exit_ticket(self):
        question, answer = None, None
        last = self.spec["lesson_plan"]["stages"][-1]
        for step in S.as_lines(last["steps"]):
            if "exit" in step.lower() and ":" in step:
                question, answer = _split_answer(step.split(":", 1)[1].strip())
                question = question.strip('"“”')
                break
        if not question:
            for sec in self.spec["worksheet"]["sections"]:
                if sec["type"] == "short_answer":
                    question, answer = sec["items"][0]["q"], sec["items"][0]["answer"]
                    break
        if not question:
            return
        s = self._slide(config.BRAND["navy"], notes="Students answer on a sticky note or slip of paper before leaving."
                        + (f" Expected answer: {answer}" if answer else ""))
        self._box(s, Inches(0.8), Inches(0.6), Inches(11), Inches(0.6), "EXIT TICKET", 20, True, config.BRAND["gold"])
        self._pic(s, "🎟️", Inches(10.6), Inches(0.5), Inches(1.6))
        self._box(s, Inches(0.8), Inches(1.8), Inches(11.6), Inches(3.8), question, 38, True, WHITE, anchor=MSO_ANCHOR.MIDDLE)
        self._box(s, Inches(0.8), Inches(6.0), Inches(11), Inches(0.6), "Write your answer before you leave.", 20, True, "#C9D2F0")

    def build(self, out):
        lp = self.spec["lesson_plan"]
        stages = lp["stages"]
        self.title()
        self.objectives()
        self.agenda()
        self.stage(stages[0])
        self.vocab_overview()
        self.vocab()
        if len(stages) > 1:
            self.stage(stages[1])
        for sl in self.spec.get("slides") or []:
            self.teaching(sl)
        for st in stages[2:-1]:
            self.stage(st)
        self.activities("worksheet")
        self.quick_check()
        self.summary()
        self.exit_ticket()
        if len(stages) > 2:
            self.stage(stages[-1], "Wrap-up")
        end = self._slide(config.BRAND["navy"], notes="Thank students for their work and remind them about the homework.")
        self._box(end, Inches(0.8), Inches(2.4), Inches(11.7), Inches(1.4), "Great work today!", 54, True, WHITE, PP_ALIGN.CENTER)
        self._pic(end, "🌟", Inches(5.9), Inches(4.0), Inches(1.6))
        self.p.core_properties.title = self.m["title"]
        self.p.core_properties.author = "TeachLuma"
        self.p.save(out)
        return Path(out)


def build(spec, out):
    return Deck(spec).build(out)
