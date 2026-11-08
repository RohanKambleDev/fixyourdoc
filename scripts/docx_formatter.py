#!/usr/bin/env python3
"""
docx_formatter.py (with References styling)
-------------------------------------------
Format a .docx manuscript using a JSON specification.

Requires:
  pip install python-docx

Usage:
  python docx_formatter.py --input manuscript.docx --spec spec.json --output formatted.docx [--force-runs]
"""

import argparse
import json
import re
from typing import Optional, Dict, Any, List

from docx import Document
from docx.shared import Inches, Pt, Mm, Cm, Length
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.enum.section import WD_SECTION_START
from docx.oxml import OxmlElement
from docx.oxml.ns import qn

# ---------- Helpers for units ----------

def _to_length(s: str) -> Optional[Length]:
    """
    Convert length strings like '1in', '25mm', '2.54cm' to docx length objects (EMU).
    Accepts float values without unit as inches.
    """
    if s is None:
        return None
    if isinstance(s, (int, float)):
        return Inches(float(s))
    s = str(s).strip().lower()
    m = re.match(r'^([0-9]*\.?[0-9]+)\s*(in|inch|inches|mm|millimeter|millimeters|cm|centimeter|centimeters)?$', s)
    if not m:
        raise ValueError(f"Invalid length: {s}")
    val = float(m.group(1))
    unit = m.group(2) or 'in'
    if unit in ('in', 'inch', 'inches'):
        return Inches(val)
    if unit in ('mm','millimeter','millimeters'):
        return Mm(val)
    if unit in ('cm','centimeter','centimeters'):
        return Cm(val)
    raise ValueError(f"Unsupported unit in length: {s}")

def _page_size(doc, size: str):
    size = (size or '').strip().upper()
    # A4: 210 x 297 mm ; Letter: 8.5 x 11 in
    if size == 'A4':
        w, h = Mm(210), Mm(297)
    elif size in ('LETTER','US LETTER','A'):
        w, h = Inches(8.5), Inches(11)
    else:
        # default to A4
        w, h = Mm(210), Mm(297)
    for section in doc.sections:
        section.page_width = w
        section.page_height = h

def _align_map(value: Optional[str]):
    if not value:
        return None
    v = value.strip().lower()
    return {
        'left': WD_ALIGN_PARAGRAPH.LEFT,
        'center': WD_ALIGN_PARAGRAPH.CENTER,
        'right': WD_ALIGN_PARAGRAPH.RIGHT,
        'justify': WD_ALIGN_PARAGRAPH.JUSTIFY,
    }.get(v)

# ---------- Page numbers (field) ----------

def _add_page_number_to(paragraph):
    """
    Insert a PAGE field into the given paragraph (works for header/footer).
    """
    run = paragraph.add_run()
    fldChar1 = OxmlElement('w:fldChar')
    fldChar1.set(qn('w:fldCharType'), 'begin')
    run._r.append(fldChar1)

    instrRun = paragraph.add_run()
    instrText = OxmlElement('w:instrText')
    instrText.set(qn('xml:space'), 'preserve')
    instrText.text = ' PAGE '
    instrRun._r.append(instrText)

    run = paragraph.add_run()
    fldChar2 = OxmlElement('w:fldChar')
    fldChar2.set(qn('w:fldCharType'), 'end')
    run._r.append(fldChar2)

def _ensure_page_numbers(doc, where='footer', align='center', start_at: Optional[int]=1):
    for i, section in enumerate(doc.sections):
        if start_at is not None and i == 0:
            section.start_type = WD_SECTION_START.NEW_PAGE
            section.footer.is_linked_to_previous = False
            section.header.is_linked_to_previous = False
            section.start_type = WD_SECTION_START.CONTINUOUS
            if start_at != 1:
                # Set page numbering start using sectPr->pgNumType/@w:start
                sectPr = section._sectPr
                pgNumType = sectPr.find(qn('w:pgNumType'))
                if pgNumType is None:
                    pgNumType = OxmlElement('w:pgNumType')
                    sectPr.append(pgNumType)
                pgNumType.set(qn('w:start'), str(start_at))

        container = section.footer if where.lower() == 'footer' else section.header
        para = container.paragraphs[0] if container.paragraphs else container.add_paragraph()
        # Clear existing runs to avoid duplicates
        try:
            para.clear()
        except Exception:
            # fallback: remove all runs
            for run in list(para.runs):
                run._r.getparent().remove(run._r)
        # Compare with None: WD_ALIGN_PARAGRAPH.LEFT has the value 0, which is falsy
        alignment = _align_map(align)
        para.alignment = alignment if alignment is not None else WD_ALIGN_PARAGRAPH.CENTER
        _add_page_number_to(para)

# ---------- Formatting ----------

def _set_style_font(style, family: Optional[str], size_pt: Optional[float], bold: Optional[bool]=None):
    try:
        if family:
            style.font.name = family
        if size_pt:
            style.font.size = Pt(size_pt)
        if bold is not None:
            style.font.bold = bold
    except Exception:
        pass

def _clear_run_fonts(doc):
    """
    Remove manually set font family and size from every run so the text falls back
    to its style (Normal, Heading 1, ...). Run before the spec is applied, so the
    style, heading and table rules then take effect.
    """
    runs = [r for p in doc.paragraphs for r in p.runs]
    for t in doc.tables:
        for row in t.rows:
            for cell in row.cells:
                for p in cell.paragraphs:
                    runs.extend(p.runs)
    for r in runs:
        rPr = r._r.rPr
        if rPr is None:
            continue
        for tag in ('w:rFonts', 'w:sz', 'w:szCs'):
            for el in rPr.findall(qn(tag)):
                rPr.remove(el)

def _line_spacing_value(v) -> Optional[float]:
    if v is None:
        return None
    if isinstance(v, (int, float)):
        return float(v)
    s = str(v).strip().lower()
    if s in ('single','1','1.0'): return 1.0
    if s in ('1.5','1.50','onehalf','one-half'): return 1.5
    if s in ('double','2','2.0'): return 2.0
    try:
        return float(s)
    except ValueError:
        return None

def _format_paragraphs(doc, line_spacing=None, space_before_pt=None, space_after_pt=None, alignment=None):
    align_enum = _align_map(alignment) if alignment else None
    for p in doc.paragraphs:
        if line_spacing is not None:
            p.paragraph_format.line_spacing = _line_spacing_value(line_spacing)
        if space_before_pt is not None:
            p.paragraph_format.space_before = Pt(space_before_pt)
        if space_after_pt is not None:
            p.paragraph_format.space_after = Pt(space_after_pt)
        if align_enum is not None:
            p.alignment = align_enum

    for t in doc.tables:
        for row in t.rows:
            for cell in row.cells:
                for p in cell.paragraphs:
                    if line_spacing is not None:
                        p.paragraph_format.line_spacing = _line_spacing_value(line_spacing)
                    if space_before_pt is not None:
                        p.paragraph_format.space_before = Pt(space_before_pt)
                    if space_after_pt is not None:
                        p.paragraph_format.space_after = Pt(space_after_pt)
                    if align_enum is not None:
                        p.alignment = align_enum

def _set_table_width(tbl, width_emu: int):
    """
    Set the table's preferred width (w:tblW, in twentieths of a point).
    python-docx has no table width property, so the XML is set directly, in the
    position the schema requires inside w:tblPr.
    """
    tblPr = tbl._tbl.tblPr
    tblW = tblPr.find(qn('w:tblW'))
    if tblW is None:
        tblW = OxmlElement('w:tblW')
        tblPr.insert_element_before(
            tblW, 'w:jc', 'w:tblCellSpacing', 'w:tblInd', 'w:tblBorders', 'w:shd',
            'w:tblLayout', 'w:tblCellMar', 'w:tblLook', 'w:tblCaption', 'w:tblDescription',
        )
    tblW.set(qn('w:type'), 'dxa')
    tblW.set(qn('w:w'), str(round(width_emu / 635)))  # 1 twip = 635 EMU

def _format_tables(doc, spec_tables: Dict[str, Any], page_width_usable_emu: Optional[int]):
    if not spec_tables:
        return
    from docx.enum.table import WD_TABLE_ALIGNMENT
    align_map = {
        'left': WD_TABLE_ALIGNMENT.LEFT,
        'center': WD_TABLE_ALIGNMENT.CENTER,
        'right': WD_TABLE_ALIGNMENT.RIGHT,
    }
    for tbl in doc.tables:
        if spec_tables.get('auto_fit', True):
            try:
                tbl.autofit = True
            except Exception:
                pass
        pref = spec_tables.get('preferred_width')
        if pref:
            width_emu = None
            if str(pref).strip().lower() == 'page' and page_width_usable_emu:
                width_emu = page_width_usable_emu
            else:
                try:
                    width_emu = _to_length(pref)
                except Exception:
                    pass
            if width_emu:
                _set_table_width(tbl, width_emu)
        al = spec_tables.get('alignment')
        if al:
            try:
                tbl.alignment = align_map.get(al.lower(), WD_TABLE_ALIGNMENT.CENTER)
            except Exception:
                pass
        # Font for table text
        fam = spec_tables.get('font_family')
        size_pt = spec_tables.get('size_pt')
        if fam or size_pt:
            for row in tbl.rows:
                for cell in row.cells:
                    for p in cell.paragraphs:
                        for r in p.runs:
                            if fam:
                                r.font.name = fam
                            if size_pt:
                                r.font.size = Pt(size_pt)

def _format_images(doc, spec_images: Dict[str, Any], page_width_usable_emu: Optional[int]):
    if not spec_images:
        return
    center = bool(spec_images.get('center', True))
    maxw = spec_images.get('max_width')
    max_width_emu = None
    if maxw:
        if str(maxw).strip().lower() == 'page' and page_width_usable_emu:
            max_width_emu = page_width_usable_emu
        else:
            try:
                max_width_emu = _to_length(maxw)
            except Exception:
                pass
    # Resize and center inline shapes
    for shape in doc.inline_shapes:
        try:
            if max_width_emu and shape.width and shape.width > max_width_emu:
                # Scale the height by the same factor so the picture is not distorted
                shape.height = int(shape.height * max_width_emu / shape.width)
                shape.width = max_width_emu
            if center:
                # Center the paragraph hosting the shape
                inline = shape._inline
                # The drawing element is inside a run; go up until we hit p
                p = inline.getparent()
                while p is not None and p.tag != qn('w:p'):
                    p = p.getparent()
                if p is not None:
                    from docx.text.paragraph import Paragraph
                    para = Paragraph(p, doc)
                    para.alignment = WD_ALIGN_PARAGRAPH.CENTER
        except Exception:
            pass

def _is_empty_paragraph(p) -> bool:
    """
    True only when the paragraph holds nothing except optional formatting
    properties. A paragraph with no text can still carry a section break, a
    bookmark or a field, so having no text or runs is not enough.
    """
    has_content = any(child.tag != qn('w:pPr') for child in p._p)
    carries_section_break = bool(p._p.xpath('./w:pPr/w:sectPr'))
    return not has_content and not carries_section_break

def _cleanup(doc, spec_cleanup: Dict[str, Any]):
    if not spec_cleanup:
        return
    remove_empty = bool(spec_cleanup.get('remove_empty_paragraphs', True))
    collapse_newlines = bool(spec_cleanup.get('collapse_multiple_newlines', True))

    if remove_empty or collapse_newlines:
        # Iterate with a simple state machine
        prev_empty = False
        for p in list(doc.paragraphs):
            is_empty = _is_empty_paragraph(p)
            if remove_empty and is_empty:
                p._element.getparent().remove(p._element)
                continue
            if collapse_newlines and is_empty and prev_empty:
                p._element.getparent().remove(p._element)
                continue
            prev_empty = is_empty

def _compute_usable_page_width(section):
    # usable width = page_width - left_margin - right_margin
    return int(section.page_width - section.left_margin - section.right_margin)

# ---------- References formatting ----------

_REF_TITLES_DEFAULT = ['references', 'reference', 'bibliography', 'works cited']

def _is_heading_paragraph(p) -> bool:
    try:
        name = getattr(p.style, "name", None)
        if not name:
            return False
        return (name.startswith('Heading') or name == 'Title')
    except Exception:
        return False

def _norm(s: str) -> str:
    return (s or '').strip().lower()

def _find_references_start_idx(doc: Document, titles: List[str]) -> Optional[int]:
    titles_norm = {t.lower() for t in titles}
    for i, p in enumerate(doc.paragraphs):
        txt = _norm(p.text)
        if not txt:
            continue
        if txt in titles_norm:
            return i
        # If it's a heading and starts with "references" etc.
        if _is_heading_paragraph(p):
            for t in titles_norm:
                if txt.startswith(t):
                    return i
    return None

def _format_references(doc: Document, spec_refs: Dict[str, Any]):
    if not spec_refs or not spec_refs.get('enabled', False):
        return

    titles = spec_refs.get('titles', _REF_TITLES_DEFAULT)
    stop_at_heading = bool(spec_refs.get('stop_at_next_heading', True))

    start_idx = _find_references_start_idx(doc, titles)
    if start_idx is None:
        return

    # Determine hanging indent amount
    hanging_amt = spec_refs.get('hanging_indent', '0.5in')
    try:
        hanging_len = _to_length(hanging_amt)
    except Exception:
        hanging_len = Inches(0.5)

    # Spacing and alignment
    ls = spec_refs.get('line_spacing', 'single')
    space_before_pt = spec_refs.get('space_before_pt', 0)
    space_after_pt = spec_refs.get('space_after_pt', 0)
    align = spec_refs.get('alignment')  # often left

    # Numbering
    apply_numbering = bool(spec_refs.get('apply_numbering_style', True))
    numbering_style_name = spec_refs.get('numbering_style', 'List Number')

    paragraphs = doc.paragraphs
    end = len(paragraphs)

    # Format from first paragraph after the title to end or to next heading
    i = start_idx + 1
    while i < end:
        p = paragraphs[i]
        txt = _norm(p.text)

        if stop_at_heading and _is_heading_paragraph(p) and txt:
            break  # another section begins

        # Skip empty lines
        if not txt:
            i += 1
            continue

        # Apply numbering style if requested and available
        if apply_numbering:
            try:
                _ = doc.styles[numbering_style_name]  # ensure it exists
                p.style = numbering_style_name
            except Exception:
                # If the style isn't available, silently continue
                pass

        # Apply hanging indent: left indent = +amount; first line indent = -amount
        try:
            pf = p.paragraph_format
            pf.left_indent = hanging_len
            pf.first_line_indent = -hanging_len
            # Spacing
            val_ls = _line_spacing_value(ls)
            if val_ls is not None:
                pf.line_spacing = val_ls
            if space_before_pt is not None:
                pf.space_before = Pt(space_before_pt)
            if space_after_pt is not None:
                pf.space_after = Pt(space_after_pt)
            # Alignment (Vancouver is typically left)
            if align:
                # Compare with None: WD_ALIGN_PARAGRAPH.LEFT has the value 0, which is falsy
                alignment = _align_map(align)
                if alignment is not None:
                    p.alignment = alignment
        except Exception:
            pass

        i += 1

def apply_spec(doc: Document, spec: Dict[str, Any], force_runs: bool = False) -> Document:
    # Drop manual run fonts first so styles, headings and tables apply cleanly
    if force_runs:
        _clear_run_fonts(doc)

    # Page size
    _page_size(doc, spec.get('page', {}).get('size', 'A4'))

    # Margins
    margins = spec.get('page', {}).get('margins', {})
    for section in doc.sections:
        if 'top' in margins: section.top_margin = _to_length(margins['top'])
        if 'bottom' in margins: section.bottom_margin = _to_length(margins['bottom'])
        if 'left' in margins: section.left_margin = _to_length(margins['left'])
        if 'right' in margins: section.right_margin = _to_length(margins['right'])

    # Styles / Font on styles
    font_spec = spec.get('font', {})
    fam = font_spec.get('family')
    size_pt = font_spec.get('size_pt')
    apply_to = font_spec.get('apply_to', ['*'])

    # Apply to specific styles first
    style_names = set()
    try:
        style_names = {getattr(s, "name", None) for s in doc.styles if getattr(s, "name", None)}
    except Exception:
        pass
    for target in apply_to:
        if target == '*':
            continue
        if target in style_names:
            try:
                _set_style_font(doc.styles[target], fam, size_pt)
            except Exception:
                pass

    # Fallback: apply to Normal + all other styles if "*" present
    if '*' in apply_to:
        for style in doc.styles:
            try:
                _set_style_font(style, fam, size_pt)
            except Exception:
                pass

    # Headings adjustments (hardened)
    headings = spec.get('headings', {})
    heading_family = headings.get('font_family') or fam
    size_overrides = headings.get('size_overrides_pt', {})
    bold_list = set(headings.get('bold', []))

    for style in doc.styles:
        try:
            name = getattr(style, "name", None)
            if not name:
                continue
            if (name.startswith('Heading')
                or name in size_overrides
                or name in bold_list
                or name == 'Title'):
                _set_style_font(style, heading_family, size_overrides.get(name), bold=(name in bold_list))
        except Exception:
            continue

    # Paragraph formatting (global)
    para = spec.get('paragraph', {})
    _format_paragraphs(
        doc,
        line_spacing=para.get('line_spacing'),
        space_before_pt=para.get('space_before_pt'),
        space_after_pt=para.get('space_after_pt'),
        alignment=para.get('alignment')
    )

    # Page numbers
    pn = spec.get('page_numbers', {})
    if pn.get('enabled', False):
        _ensure_page_numbers(
            doc,
            where=pn.get('position', 'footer'),
            align=pn.get('alignment', 'center'),
            start_at=pn.get('start_at', 1)
        )

    # Images + Tables (need usable page width)
    first_section = doc.sections[0]
    usable_width = _compute_usable_page_width(first_section)

    _format_images(doc, spec.get('images', {}), usable_width)
    _format_tables(doc, spec.get('tables', {}), usable_width)

    # References (after global paragraph formatting to allow overrides)
    _format_references(doc, spec.get('references', {}))

    # Cleanup
    _cleanup(doc, spec.get('cleanup', {}))

    return doc

def load_json(path: str) -> Dict[str, Any]:
    with open(path, 'r', encoding='utf-8') as f:
        return json.load(f)

def main():
    ap = argparse.ArgumentParser(description="Format a .docx using a JSON spec.")
    ap.add_argument('--input', required=True, help='Input .docx path')
    ap.add_argument('--spec', required=True, help='JSON spec path')
    ap.add_argument('--output', required=True, help='Output .docx path')
    ap.add_argument('--force-runs', action='store_true', help='Remove manual font family and size from every run so styles apply')
    args = ap.parse_args()

    spec = load_json(args.spec)

    doc = Document(args.input)
    doc = apply_spec(doc, spec, force_runs=args.force_runs)

    doc.save(args.output)
    print(f"Saved formatted file to: {args.output}")

if __name__ == '__main__':
    main()
