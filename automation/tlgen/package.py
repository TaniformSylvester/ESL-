"""Render every file of a resource package from its spec."""
import re
import shutil
from pathlib import Path

from . import images, render_docx, render_html, render_pdf, render_pptx


def slugify(text, n=60):
    return re.sub(r"[^a-z0-9]+", "-", text.lower()).strip("-")[:n].strip("-")


def render(spec, out_dir: Path, compact=0):
    """Write all files into out_dir and return the package manifest."""
    out_dir = Path(out_dir)
    if out_dir.exists():
        shutil.rmtree(out_dir)
    out_dir.mkdir(parents=True)
    m = spec["meta"]
    base = slugify(f"{m['grade']}-{m['topic']}")
    pro = m["tier"] == "pro"

    main = render_pdf.html_to_pdf(render_html.main_pack(spec, compact), out_dir / f"{base}-lesson-pack.pdf")
    key = render_pdf.html_to_pdf(render_html.key_only(spec, compact), out_dir / f"{base}-answer-key.pdf")
    extras, components = [], ["main_pdf", "answer_key"]
    if pro:
        pptx = render_pptx.build(spec, out_dir / f"{base}-slides.pptx")
        extras.append(("Lesson Slides (PowerPoint, editable)", pptx))
        components.append("pptx")
        docx = render_docx.build(spec, out_dir / f"{base}-editable-pack.docx")
        extras.append(("Editable Student Pages + Lesson Plan (Word)", docx))
        fc = render_pdf.html_to_pdf(render_html.flashcards_only(spec, compact), out_dir / f"{base}-flashcards.pdf")
        extras.append(("Flashcards (PDF, A5, one card per page)", fc))
        components += ["flashcards"] + [p for p in ("quiz", "homework", "rubric") if spec.get(p)]
    else:
        docx = render_docx.build(spec, out_dir / f"{base}-editable-worksheet.docx")
        extras.append(("Editable Worksheet (Word)", docx))
    components.append("docx")
    extras.append(("Answer Key (PDF)", key))

    from pypdf import PdfReader
    pages = len(PdfReader(str(main)).pages)
    thumb = images.thumbnail(spec, out_dir / "thumbnail.jpg")
    thumb_fit = images.thumbnail_fits(spec, out_dir / "thumb-check")
    prev = images.preview(spec, main, pages, out_dir / "preview.jpg")

    included = render_html.whats_included(spec)
    included[0] = f"{pages}-page printable PDF lesson pack: " + included[0][0].lower() + included[0][1:]
    return {"main": str(main), "extras": [(lbl, str(p)) for lbl, p in extras], "thumbnail": str(thumb), "thumbnail_fit": thumb_fit, "preview": str(prev),
            "components": components, "whats_included": included, "pages": pages, "compact": compact}
