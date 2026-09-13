#!/usr/bin/env python3
import json
import math
import os
import re
import statistics
import sys
import traceback


SECTION_ALIASES = {
    "summary": [
        "profile",
        "summary",
        "professional summary",
        "career summary",
        "career profile",
        "personal profile",
        "objective",
        "about",
        "about me",
        "profil",
        "profil professionnel",
        "resume",
        "résumé",
        "riassunto",
        "profilo",
        "perfil",
        "resumen",
        "objetivo",
        "kurzprofil",
        "über mich",
        "ملخص",
        "نبذة",
        "الهدف المهني",
    ],
    "experience": [
        "experience",
        "work experience",
        "professional experience",
        "employment",
        "employment history",
        "career history",
        "work history",
        "professional history",
        "professional background",
        "esperienza",
        "esperienza professionale",
        "esperienze professionali",
        "experiencia",
        "experiencia profesional",
        "experiencia laboral",
        "expérience",
        "expérience professionnelle",
        "expériences professionnelles",
        "berufserfahrung",
        "berufliche erfahrung",
        "الخبرة العملية",
        "الخبرات العملية",
    ],
    "education": [
        "education",
        "academic",
        "academic background",
        "academic qualifications",
        "qualifications",
        "training",
        "formazione",
        "contatto formazione",
        "contatto_formazione",
        "formazione e istruzione",
        "istruzione",
        "formation",
        "formation académique",
        "educación",
        "formación",
        "formación académica",
        "ausbildung",
        "bildung",
        "التعليم",
        "المؤهلات",
        "المؤهلات العلمية",
    ],
    "skills": [
        "skills",
        "technical skills",
        "core skills",
        "key skills",
        "professional skills",
        "competencies",
        "competences",
        "competenze",
        "competenze tecniche",
        "compétences",
        "compétences techniques",
        "habilidades",
        "habilidades técnicas",
        "fähigkeiten",
        "kenntnisse",
        "مهارات",
        "المهارات",
    ],
    "languages": [
        "languages",
        "language",
        "lingue",
        "langues",
        "idiomas",
        "sprachen",
        "اللغات",
    ],
    "projects": [
        "projects",
        "project experience",
        "selected projects",
        "publications",
        "publication",
        "pubblicazioni",
        "publicaciones",
        "publications et projets",
        "projekte",
        "المشاريع",
        "المنشورات",
    ],
    "certifications": [
        "certifications",
        "certificates",
        "licenses",
        "licences",
        "certificazioni",
        "attestati",
        "certificats",
        "certificados",
        "zertifikate",
        "الشهادات",
    ],
    "interests": [
        "interests",
        "additional interests",
        "other interests",
        "hobbies",
        "interessi",
        "centres d'intérêt",
        "intereses",
        "interessen",
        "الاهتمامات",
    ],
}


def clean(value):
    return " ".join(str(value or "").replace("\u00a0", " ").split()).strip()


def normalize_heading(value):
    text = clean(value).lower().replace("_", " ")
    text = re.sub(r"[/|]+", " ", text)
    text = re.sub(r"^[\s:;.,&-]+|[\s:;.,&-]+$", "", text)
    return clean(text)


def section_key(text):
    needle = normalize_heading(text)
    if not needle or needle == "&":
        return ""
    word_count = len(needle.split())
    for key, aliases in SECTION_ALIASES.items():
        if needle in aliases:
            return key
    for key, aliases in SECTION_ALIASES.items():
        if word_count <= 5 and any(
            needle.startswith(alias + " ") or needle.endswith(" " + alias) for alias in aliases
        ):
            return key
    return ""


def mostly_upper(text):
    letters = [char for char in text if char.isalpha()]
    if len(letters) < 4:
        return False
    return sum(1 for char in letters if char.isupper()) / len(letters) >= 0.72


def line_from_pdf_line(page_number, block_number, line_number, line, page_width, page_height):
    spans = line.get("spans") or []
    text = clean(" ".join(span.get("text", "") for span in spans))
    if not text:
        return None
    x0, y0, x1, y1 = line.get("bbox") or (0, 0, 0, 0)
    font_sizes = [float(span.get("size") or 0) for span in spans if span.get("size")]
    flags = [int(span.get("flags") or 0) for span in spans]
    font_size = statistics.median(font_sizes) if font_sizes else 0
    return {
        "text": text,
        "page": page_number,
        "block": block_number,
        "line": line_number,
        "x": round(float(x0), 2),
        "y": round(float(y0), 2),
        "width": round(float(x1 - x0), 2),
        "height": round(float(y1 - y0), 2),
        "fontSize": round(float(font_size), 2),
        "bold": any(flag & 16 for flag in flags),
        "pageWidth": round(float(page_width), 2),
        "pageHeight": round(float(page_height), 2),
    }


def extract_lines(path):
    import pymupdf as fitz

    doc = fitz.open(path)
    pages = []
    all_lines = []
    for page_index, page in enumerate(doc, start=1):
        rect = page.rect
        page_lines = []
        data = page.get_text("dict", sort=False)
        for block_index, block in enumerate(data.get("blocks") or []):
            if block.get("type") != 0:
                continue
            for line_index, line in enumerate(block.get("lines") or []):
                item = line_from_pdf_line(
                    page_index,
                    block_index,
                    line_index,
                    line,
                    rect.width,
                    rect.height,
                )
                if item:
                    page_lines.append(item)
                    all_lines.append(item)
        pages.append(
            {
                "page": page_index,
                "width": round(float(rect.width), 2),
                "height": round(float(rect.height), 2),
                "lineCount": len(page_lines),
            }
        )
    doc.close()
    return pages, all_lines


def detect_layout_mode(pages, lines):
    body = [
        line
        for line in lines
        if len(line["text"]) >= 3
        and not re_is_bullet_marker(line["text"])
        and line["width"] > 20
        and line["y"] > line["pageHeight"] * 0.08
    ]
    if not body:
        return "text_order"
    left_sidebar = [
        line
        for line in body
        if line["x"] < line["pageWidth"] * 0.32
        and line["x"] + line["width"] < line["pageWidth"] * 0.48
    ]
    main_right = [
        line
        for line in body
        if line["x"] > line["pageWidth"] * 0.38
        and line["width"] > line["pageWidth"] * 0.22
    ]
    center_lines = [
        line
        for line in body
        if line["x"] < line["pageWidth"] * 0.22
        and line["x"] + line["width"] > line["pageWidth"] * 0.68
    ]
    if len(left_sidebar) >= 6 and len(main_right) >= 10:
        sidebar_y = (min(line["y"] for line in left_sidebar), max(line["y"] for line in left_sidebar))
        main_y = (min(line["y"] for line in main_right), max(line["y"] for line in main_right))
        overlap = max(0, min(sidebar_y[1], main_y[1]) - max(sidebar_y[0], main_y[0]))
        if overlap > 80:
            return "sidebar"
    x_sorted_lines = sorted(body, key=lambda line: line["x"] / max(line["pageWidth"], 1))
    x_positions = [line["x"] / max(line["pageWidth"], 1) for line in x_sorted_lines]
    if len(x_positions) >= 16:
        gaps = [
            (x_positions[index + 1] - x_positions[index], index)
            for index in range(len(x_positions) - 1)
        ]
        largest_gap, gap_index = max(gaps, key=lambda item: item[0])
        left_count = gap_index + 1
        right_count = len(x_positions) - left_count
        left_widths = [
            line["width"] / max(line["pageWidth"], 1) for line in x_sorted_lines[:left_count]
        ]
        right_widths = [
            line["width"] / max(line["pageWidth"], 1) for line in x_sorted_lines[left_count:]
        ]
        if (
            largest_gap > 0.16
            and left_count >= 5
            and right_count >= 5
            and statistics.median(left_widths or [0]) > 0.16
            and statistics.median(right_widths or [0]) > 0.16
        ):
            return "two_column"
    if center_lines:
        return "single_column"
    return "single_column"


def reading_order(lines, mode):
    def page_sort_key(line):
        return (line["page"], line["y"], line["x"])

    if mode != "sidebar":
        return sorted(lines, key=page_sort_key)

    ordered = []
    for page in sorted(set(line["page"] for line in lines)):
        page_lines = [line for line in lines if line["page"] == page]
        if not page_lines:
            continue
        width = max(line["pageWidth"] for line in page_lines)
        header = [line for line in page_lines if line["y"] <= line["pageHeight"] * 0.13]
        body = [line for line in page_lines if line not in header]
        left = [
            line
            for line in body
            if line["x"] < width * 0.34 and line["x"] + line["width"] < width * 0.52
        ]
        main = [line for line in body if line not in left]
        ordered.extend(sorted(header, key=lambda line: (line["y"], line["x"])))
        ordered.extend(sorted(main, key=lambda line: (line["y"], line["x"])))
        ordered.extend(sorted(left, key=lambda line: (line["y"], line["x"])))
    return ordered


def infer_heading_threshold(lines):
    sizes = [line["fontSize"] for line in lines if line["fontSize"] > 0]
    if not sizes:
        return 0
    median = statistics.median(sizes)
    return median + 1.2


def re_is_bullet_marker(text):
    return clean(text) in {"•", "§", "-", "–", "—", "−", "", "*"}


def looks_like_spaced_location(text):
    compact = clean(text).replace(" ", "")
    return bool(compact) and compact.upper() == compact and compact in {
        "MILAN,ITALY",
        "ROME,ITALY",
        "DUBAI,UAE",
        "ABUDHABI,UAE",
        "RIYADH,SAUDIARABIA",
        "LONDON,UK",
        "LONDON,UNITEDKINGDOM",
    }


def looks_like_date_only(text):
    compact = clean(text).lower()
    if not compact:
        return False
    return bool(
        re.fullmatch(
            r"(?:\d{1,2}[/.-]\d{2,4}|\d{4}|\d{2}\s*[-–—]\s*\d{2}|\d{4}\s*[-–—]\s*(?:\d{4}|present|current))",
            compact,
        )
    )


def looks_like_heading(line, heading_threshold):
    text = line["text"]
    words = text.split()
    if clean(text).lower().strip(":") in {"key activities", "selected transactions activities performed detail"}:
        return False
    key = section_key(text)
    if key:
        return True
    if normalize_heading(text) in {"", "&"}:
        return False
    if looks_like_date_only(text):
        return False
    if re_is_bullet_marker(text):
        return False
    if clean(text).startswith(("- ", "• ", "§ ", "− ", " ")):
        return False
    if looks_like_spaced_location(text):
        return False
    if len(words) > 8:
        return False
    if len(words) <= 6 and mostly_upper(text):
        return True
    if line["fontSize"] >= heading_threshold and len(words) <= 8:
        return True
    return False


def build_sections(ordered_lines):
    heading_threshold = infer_heading_threshold(ordered_lines)
    sections = []
    current = None
    preface = []
    pending_bullet = ""
    for line in ordered_lines:
        text = clean(line["text"])
        if not text:
            continue
        if re_is_bullet_marker(text):
            pending_bullet = "-"
            continue
        was_pending_bullet = False
        if pending_bullet:
            text = clean(f"{pending_bullet} {text}")
            pending_bullet = ""
            was_pending_bullet = True
        if not was_pending_bullet and looks_like_heading(line, heading_threshold):
            if current:
                sections.append(current)
            elif preface:
                sections.append(
                    {
                        "title": "Profile",
                        "key": "summary",
                        "lines": preface,
                        "parserSource": "layout",
                        "confidence": 0.54,
                    }
                )
                preface = []
            current = {
                "title": text,
                "key": section_key(text) or "",
                "lines": [],
                "parserSource": "layout",
                "confidence": 0.78 if section_key(text) else 0.52,
            }
            continue
        if current:
            current["lines"].append(text)
        else:
            preface.append(text)
    if current:
        sections.append(current)
    elif preface:
        sections.append(
            {
                "title": "Profile",
                "key": "summary",
                "lines": preface,
                "parserSource": "layout",
                "confidence": 0.54,
            }
        )
    return [section for section in sections if section["lines"] or section["key"]]


def main():
    if len(sys.argv) < 2:
        print(json.dumps({"ok": False, "parser": "layout", "error": "missing_file"}))
        return 2
    path = sys.argv[1]
    if os.path.splitext(path)[1].lower() != ".pdf":
        print(json.dumps({"ok": False, "parser": "layout", "error": "unsupported_file"}))
        return 0
    try:
        pages, lines = extract_lines(path)
        mode = detect_layout_mode(pages, lines)
        ordered = reading_order(lines, mode)
        sections = build_sections(ordered)
        text = "\n".join(line["text"] for line in ordered)
        result = {
            "ok": bool(lines),
            "parser": "layout",
            "layoutMode": mode,
            "pages": pages,
            "lineCount": len(lines),
            "orderedLineCount": len(ordered),
            "sections": sections,
            "lines": ordered[:1200],
            "text": text,
            "metadata": {
                "source": "pymupdf",
                "sectionCount": len(sections),
                "averageFontSize": round(
                    statistics.mean([line["fontSize"] for line in lines if line["fontSize"] > 0])
                    if any(line["fontSize"] > 0 for line in lines)
                    else 0,
                    2,
                ),
            },
        }
        print(json.dumps(result, ensure_ascii=False))
        return 0
    except Exception as error:
        print(
            json.dumps(
                {
                    "ok": False,
                    "parser": "layout",
                    "error": "layout_parse_failed",
                    "message": clean(str(error)),
                    "trace": traceback.format_exc(limit=3),
                }
            )
        )
        return 0


if __name__ == "__main__":
    raise SystemExit(main())
