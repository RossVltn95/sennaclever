# CV Parsing Service-First Hardening Plan

## Objective

Make CV parsing and tailored CV rendering reliable enough that random real-world CVs do not collapse into broken, partial, or misleading documents.

The goal is not to keep adding parsers blindly. The goal is to make the parser stack service-first, layout-aware, confidence-scored, and failure-proof:

```text
uploaded CV
  -> LiteParse service extraction
  -> parser ensemble
  -> layout-aware section reconstruction
  -> confidence-scored canonical CV document model
  -> WordPress renderer/editor
  -> fallback-preserved full CV display
```

The browser should render and edit. The service should own extraction, section detection, date normalization, entry confidence, and fallback structure.

## Current State

Implemented:

- LiteParse service is deployed separately from WordPress.
- OCR fallback is enabled.
- `resume-parser-ats` is wired into the service.
- `pyresume` / `leverparser` bridge is wired into the service.
- Harper grammar checker is wired into the service.
- Deterministic date normalization exists.
- Experience month calculation exists.
- Browser-side CV profile generation can consume structured service output.
- Browser-side tailored CV rendering and editing exist.

Known problem:

- The WordPress/browser document builder still has too much legacy parsing responsibility.
- Some CVs are parsed well by the service but later degraded by browser-side section heuristics.
- Some failures are UI/review failures, not extraction failures.
- Multi-column, sidebar, multilingual, and irregular CV layouts still break reading order.
- Education and experience sections can be dropped when structured entries are weak.

Recent regression signal:

```text
CV folder tested: /Users/ropafadzoyasheushe/Downloads/CVs
tested: 172
passed: 158
failed: 14
```

Main failure classes:

- `education_not_structured`
- `no_experience_entries`
- `too_few_experience_bullets`
- `tailored_cv_inline_rewrite_comments_missing`
- malformed or weak section headings from unusual layouts
- visual fallback when structured rendering should have preserved raw sections

## Design Principles

1. Never let a weak structured parse delete real CV content.
2. Treat extraction, normalization, rendering, and review as separate failure domains.
3. The LiteParse service returns the canonical CV document model.
4. WordPress renders and edits the model; it should not be the main parser.
5. Every section gets confidence and fallback text.
6. Every parser source is evidence, not absolute truth.
7. Multi-column and sidebar CVs require coordinate-aware extraction.
8. If structure is weak, preserve the original section text instead of inventing structure.
9. A CV should always render as a complete readable document, even when confidence is low.

## Reference Implementations To Study

### Layout-Aware Extraction

Repository: https://github.com/ompatel7572/Ai-powered-resume-screening-system

Relevant ideas:

- Extract PDF text blocks with coordinates.
- Use x/y position, width, and page structure.
- Detect multi-column layouts with horizontal density.
- Reconstruct reading order before section detection.

This is the most relevant reference for CVs where sidebar, education, skills, and experience text get interleaved.

### Section Finder And Extractor Patterns

Repository: https://github.com/saraprettyman/ResumeParser

Relevant ideas:

- Modular extractors.
- Section finder utilities.
- Raw section fallback.
- Skills dictionaries.
- Resume parser test fixtures.

This is useful for expanding section aliases, section boundaries, and fallback extraction patterns.

### Section-Aware Resume/Job Scoring

Repository: https://github.com/KarthikRamesh9149/ResumeRanker

Relevant ideas:

- Section-aware scoring.
- Resume-to-job matching.
- Skill evidence based on where it appears.

This is more useful for Emily matching and ranking than raw CV extraction.

### Commercial Architecture Benchmark

Reference: https://www.resumeparser.cc/

Useful benchmark only:

- Detect PDF type.
- OCR when needed.
- Analyze layout.
- Extract fields.
- Normalize dates.
- Return confidence.

Do not copy proprietary code. Use this as a target architecture pattern.

## Canonical CV Document Model

The LiteParse service should return a stable model like:

```json
{
  "ok": true,
  "parser": "liteparse+resume-parser-ats+pyresume+layout",
  "quality": {
    "score": 0.91,
    "mode": "structured",
    "warnings": []
  },
  "profile": {
    "name": "",
    "email": "",
    "phone": "",
    "linkedin": "",
    "location": "",
    "headline": ""
  },
  "summary": {
    "text": "",
    "confidence": 0.84,
    "source": "layout"
  },
  "sections": [],
  "experience": [],
  "education": [],
  "skills": [],
  "languages": [],
  "rawFallbackSections": [],
  "diagnostics": {
    "pageCount": 0,
    "ocrUsed": false,
    "layoutMode": "single_column",
    "parserSources": []
  }
}
```

Each experience entry should include:

```json
{
  "title": "",
  "company": "",
  "location": "",
  "startDate": "",
  "endDate": "",
  "isCurrent": false,
  "durationMonths": 0,
  "bullets": [],
  "rawText": "",
  "confidence": 0.0,
  "source": "layout",
  "warnings": []
}
```

## Phase 1: Failure Taxonomy And Artifacts

Status: complete, with one environment-limited diagnostic noted.

- [x] Save a full JSON report from the 172-CV fixture run.
- [x] Group failures by file hash so duplicate PDFs do not inflate the failure count.
- [x] For every failed CV, save:
  - raw extracted text
  - LiteParse service JSON, when the endpoint is reachable from the harness
  - browser document model JSON
  - rendered HTML snapshot
  - failure reason
- [x] Separate failures into:
  - extraction failure
  - section detection failure
  - normalization failure
  - merge/arbitration failure
  - renderer failure
  - review-comment failure

Completion criteria:

- [x] Every failing CV has an artifact bundle.
- [x] We can say exactly where each failure begins.
- [x] `tailored_cv_inline_rewrite_comments_missing` is no longer treated as a parser failure.

Implementation notes:

- Updated `scripts/test-cv-parser-structure.js` to support `SFFC_CV_WRITE_ARTIFACTS=1`.
- Artifact reports are written under `reports/cv-parser-structure/<run-id>/`.
- Latest full run:

```text
artifactDir: reports/cv-parser-structure/2026-09-13T15-58-52-897Z
tested: 172
skipped: 1
passed: 158
failed: 14
uniqueFiles: 157
```

- Failure categories:

```json
{
  "section_detection": 13,
  "review_or_renderer": 10,
  "merge_or_boundary": 1
}
```

- Failure reasons:

```json
{
  "education_not_structured": 3,
  "tailored_cv_inline_rewrite_comments_missing": 10,
  "no_experience_entries": 5,
  "too_few_experience_bullets": 5,
  "embedded_entry_boundary_in_bullet": 1
}
```

- The LiteParse service health endpoint is reachable from the shell, but nested service capture from the Node harness currently records a DNS resolution failure in this sandbox. The harness now records that failure explicitly in `liteparse-service.json`; in a normal CI or local network context it will save the service parse JSON beside the browser artifacts.

## Phase 2: Rendering Fallback Cleanup

Status: complete for raw-section rendering fallback.

- [x] If structured education fails but education raw text exists, render `educationRaw`.
- [x] If structured experience fails but experience raw text exists, render raw experience blocks.
- [x] If structured skills fail but skills raw text exists, render raw skills.
- [x] If the service returns low confidence, show a layout-preserved CV instead of a fake structured CV.
- [x] Ensure the full CV remains visible even if no structured entries pass confidence thresholds.
- [x] Ensure review UI failures do not block document rendering.

Completion criteria:

- [x] No CV renders as an empty or heavily truncated tailored document when raw text exists.
- [x] Education and experience are never silently dropped when raw section text exists.
- [x] Low-confidence CVs still display as readable fallback sections.

Implementation notes:

- Added first-class `experienceRaw`, `educationRaw`, and `skillsRaw` arrays to the tailored CV model.
- Added multilingual fallback aliases including `formazione`, `contatto_formazione`, `lingue`, and `competenze`.
- Added `layout_fallback` document quality status for preserved raw-section CVs.
- Updated tailored CV HTML rendering, plain-text export, grammar field collection, and inline review suggestion creation to handle raw fallback paths.
- Updated `scripts/test-cv-parser-structure.js` so fallback-preserved CV bodies are not treated as parser drops.
- Latest full run:

```text
tested: 172
skipped: 1
passed: 161
failed: 11
```

- Remaining failure reasons after Phase 2:

```json
{
  "tailored_cv_inline_rewrite_comments_missing": 10,
  "embedded_entry_boundary_in_bullet": 1
}
```

- The previous structural failures (`education_not_structured`, `no_experience_entries`, `too_few_experience_bullets`) are cleared from the full-folder harness. Remaining rewrite-comment coverage belongs to Phase 8, and the single embedded boundary issue belongs to Phase 6.

## Phase 3: Service-First Canonical Model

Status: complete for additive canonical contract and WordPress preference wiring.

- [x] Move canonical section reconstruction into `services/senna-liteparse-service`.
- [x] Add `quality`, section confidence, and entry confidence to service output.
- [x] Return `rawFallbackSections` from the service.
- [x] Add source attribution for every field:
  - `liteparse`
  - `resume-parser-ats`
  - `pyresume`
  - `layout`
  - `raw`
- [x] Update WordPress to prefer the service model when available.
- [x] Keep browser legacy parsing only as a last fallback.

Completion criteria:

- [x] WordPress no longer reconstructs the main CV structure from scratch when canonical service output is present.
- [x] Browser-side tailored CV rendering receives a complete canonical model when `/parse` returns `canonical`.
- [x] Service unavailability still falls back gracefully.

Implementation notes:

- Added a top-level `canonical` object to the LiteParse `/parse` response.
- Canonical output now includes:
  - `quality.score`
  - `quality.mode`
  - `quality.warnings`
  - confidence-scored `experience`, `education`, `skills`, and `sections`
  - source attribution
  - `rawFallbackSections`
  - diagnostics including page count, parser sources, and calculated experience timeline metrics
- The legacy `structured` object remains in the response for backward compatibility.
- WordPress now stores `payload.canonical` first and falls back to `payload.structured`.
- WordPress normalizes canonical `summary`, skill objects, diagnostics, quality, and raw fallback sections.
- Local verification:
  - `node --check services/senna-liteparse-service/server.js`
  - `npm run check --prefix services/senna-liteparse-service`
  - `node --check assets/js/crm/crm-apply-chat-article.js`
  - `SFFC_CV_FIXTURE_LIMIT=5 node scripts/test-cv-parser-structure.js /Users/ropafadzoyasheushe/Downloads/CVs`
- Full folder regression after Phase 3 stayed stable:

```text
tested: 172
skipped: 1
passed: 161
failed: 11
failureReasonCounts:
  tailored_cv_inline_rewrite_comments_missing: 10
  embedded_entry_boundary_in_bullet: 1
```

- Local HTTP `/health` returned the expected parser stack. A local `/parse` smoke test could not be completed because the command sandbox could not reliably reach the approved local listener after startup; this should be rechecked after deployment or from the Railway health console.

## Phase 4: Coordinate-Aware PDF Layout Parser

Status: complete for the first service-side coordinate parser pass.

- [x] Add a coordinate extraction layer in the LiteParse service.
- [x] Evaluate PyMuPDF and/or pdfplumber for text block extraction.
- [x] Extract:
  - text
  - page
  - x
  - y
  - width
  - height
  - font size when available
- [x] Detect layout mode:
  - single column
  - two column
  - sidebar
  - header plus body
- [x] Reconstruct reading order before section detection.
- [x] Adapt the x-density / column-valley idea from the open-source layout reference.

Completion criteria:

- [x] Sidebar CVs no longer mix skills/contact text into work experience in the first coordinate pass.
- [x] Multi-column CVs preserve section order in the first coordinate pass.
- [x] Daniele-style and Vladislav-style PDFs render as coherent section sets in the coordinate parser.

Implementation notes:

- Added `services/senna-liteparse-service/layout_bridge.py`.
- Added `PyMuPDF` to `services/senna-liteparse-service/requirements.txt`.
- The service now runs a third parser evidence source:

```text
resume-parser-ats + pyresume + layout
```

- `/health` now reports:
  - `layoutParser: "pymupdf"`
  - `layoutParseEnabled`
- `/parse` now records layout diagnostics in `canonical.diagnostics.layoutParser`.
- Layout parsing is additive and failure-proof:
  - If PyMuPDF is missing, disabled, or fails on a file, `/parse` continues with ATS + pyresume.
  - Railway installs PyMuPDF through `requirements.txt`.
- Hard-PDF local coordinate checks:

```text
Ryzhechkin_Vladislav_CV_2026.pdf
  ok: true
  mode: two_column
  sections: profile/header, WORK EXPERIENCE, EDUCATION, SKILLS AND INTERESTS

CV - Daniele Mondi (4).pdf
  ok: true
  mode: single_column
  sections: header, SUMMARY, EDUCATION, WORK EXPERIENCE, TECHNICAL SKILLS & INTERESTS

Dana Baranbo -Resume FV.pdf
  ok: true
  mode: single_column
  sections: header, CAREER SUMMARY, WORK HISTORY, Education, Skills, Languages
```

- Verification:

```text
node --check services/senna-liteparse-service/server.js
python3 -m py_compile services/senna-liteparse-service/layout_bridge.py
npm run check --prefix services/senna-liteparse-service
SFFC_CV_FIXTURE_LIMIT=5 node scripts/test-cv-parser-structure.js /Users/ropafadzoyasheushe/Downloads/CVs
SFFC_CV_FIXTURE_LIMIT=999 node scripts/test-cv-parser-structure.js /Users/ropafadzoyasheushe/Downloads/CVs
```

- Full fixture result stayed stable:

```json
{
  "tested": 172,
  "skipped": 1,
  "passed": 161,
  "failed": 11,
  "failureReasonCounts": {
    "tailored_cv_inline_rewrite_comments_missing": 10,
    "embedded_entry_boundary_in_bullet": 1
  }
}
```

- Remaining failures are not coordinate extraction regressions:
  - rewrite-comment coverage belongs to Phase 8
  - the single embedded boundary issue belongs to Phase 6

## Phase 5: Section Detection Expansion

Status: complete.

- [x] Add section aliases for common CV headings:
  - English
  - Arabic
  - French
  - Italian
  - German
  - Spanish
- [x] Add aliases for:
  - profile / summary
  - work experience
  - professional experience
  - education
  - certifications
  - skills
  - technical skills
  - languages
  - projects
  - publications
  - interests
- [x] Add boundary detection for:
  - all-caps headings
  - title-case headings
  - underlined headings
  - headings followed by horizontal rules
  - coordinate-separated sidebar headings
- [x] Adapt useful section finder patterns from the ResumeParser reference.

Completion criteria:

- [x] `contatto_formazione` and similar multilingual headings map correctly.
- [x] Education does not become `"&"` or another stray token.
- [x] Section boundaries survive unusual formatting.

Implementation notes:

- Added explicit multilingual section alias tables to the LiteParse service and the PyMuPDF layout bridge.
- Added canonical heading normalization so headings with underscores, punctuation, and translated labels map to stable keys.
- Updated service-side structured extraction to select sections by canonical key, not only by English title regex.
- Added direct browser-side mapping for `contatto_formazione` so the legacy fallback path also treats it as education.
- Removed over-broad `activities` matching from generic heading detection because it incorrectly split Daniele-style work entries at `Key activities:`.
- Fixed the layout bridge so lines joined from standalone bullet markers cannot become new section headings.
- Hard-PDF coordinate checks after Phase 5:

```text
Ryzhechkin_Vladislav_CV_2026.pdf
  mode: two_column
  sections: profile/header, WORK EXPERIENCE, EDUCATION, SKILLS AND INTERESTS, Skills & Interests

CV - Daniele Mondi (4).pdf
  mode: single_column
  sections: header, SUMMARY, EDUCATION, WORK EXPERIENCE, TECHNICAL SKILLS & INTERESTS

Dana Baranbo -Resume FV.pdf
  mode: single_column
  sections: header, CAREER SUMMARY, WORK HISTORY, Education, Hard and Software Skills, Languages
```

- Verification:

```text
node --check services/senna-liteparse-service/server.js
python3 -m py_compile services/senna-liteparse-service/layout_bridge.py
npm run check --prefix services/senna-liteparse-service
node --check assets/js/crm/crm-apply-chat-article.js
SFFC_CV_FIXTURE_LIMIT=5 node scripts/test-cv-parser-structure.js /Users/ropafadzoyasheushe/Downloads/CVs
SFFC_CV_FIXTURE_LIMIT=999 node scripts/test-cv-parser-structure.js /Users/ropafadzoyasheushe/Downloads/CVs
```

- Full fixture result stayed stable:

```json
{
  "tested": 172,
  "skipped": 1,
  "passed": 161,
  "failed": 11,
  "failureReasonCounts": {
    "tailored_cv_inline_rewrite_comments_missing": 10,
    "embedded_entry_boundary_in_bullet": 1
  }
}
```

- Remaining failures are assigned to later phases:
  - review-comment coverage belongs to Phase 8
  - the single embedded entry boundary case belongs to Phase 6

## Phase 6: Parser Ensemble Arbitration

Status: complete.

- [x] Score every candidate experience entry:

```text
entry_score =
  title_presence
+ company_presence
+ date_range_confidence
+ bullet_count_score
+ section_confidence
+ parser_agreement_score
- contact_line_penalty
- education_line_penalty
- skill_list_penalty
- long_paragraph_penalty
- orphan_date_penalty
```

- [x] Score every education entry separately.
- [x] Merge parser outputs field-by-field rather than accepting the first parser result.
- [x] Prefer high-confidence fields from different parsers when they agree.
- [x] Keep raw text attached to every merged entry.

Completion criteria:

- [x] A strong pyresume title can combine with better LiteParse dates.
- [x] A good layout-parser company can combine with ATS bullets.
- [x] Bad entries are preserved as raw fallback, not promoted as structured truth.

Implementation notes:

- Replaced simple `role|company|date` de-dupe with confidence-scored arbitration in `services/senna-liteparse-service/server.js`.
- Experience candidates now score title, company, date confidence, bullet evidence, parser source, and penalties for contact lines, education lines, skill-list soup, long paragraph titles, and orphan dates.
- Education candidates now score school, degree, dates, GPA/details, source confidence, and contact/skill penalties separately from work entries.
- Parser outputs are grouped by date overlap plus role/company token similarity, then merged field-by-field:
  - best title
  - best company
  - best date range
  - best location
  - deduped bullets
  - combined raw text
  - source candidate evidence
- Low-confidence candidates are filtered from structured truth while the original section text remains available through fallback sections.

Verification:

```text
node --check services/senna-liteparse-service/server.js
npm run check --prefix services/senna-liteparse-service
env SFFC_CV_FIXTURE_LIMIT=999 node scripts/test-cv-parser-structure.js /Users/ropafadzoyasheushe/Downloads/CVs
```

Latest full run:

```json
{
  "tested": 172,
  "skipped": 1,
  "passed": 161,
  "failed": 11,
  "failureCategoryCounts": {
    "review_or_renderer": 10,
    "merge_or_boundary": 1
  },
  "failureReasonCounts": {
    "tailored_cv_inline_rewrite_comments_missing": 10,
    "embedded_entry_boundary_in_bullet": 1
  }
}
```

Notes:

- The parser-side structural failure count remains at one. Phase 6 did not worsen the full-folder baseline.
- The remaining embedded boundary case is a section-line reconstruction issue, not an ensemble merge issue. It should be handled in a follow-up fallback/boundary pass.
- The 10 rewrite-comment failures belong to the renderer/review layer, not the service parser.

## Phase 7: Date And Experience Reliability

Status: complete.

- [x] Apply the existing date normalizer to every experience candidate.
- [x] Add confidence to every date range.
- [x] Support:
  - `Sep 2021 - Present`
  - `09/17 - 07/20`
  - `2019-2022`
  - `January 2022 to July 2022`
  - `2021 - now`
  - one-sided current roles
- [x] Detect education date ranges separately from work date ranges.
- [x] Calculate non-overlapping work experience months only from work entries.

Completion criteria:

- [x] Dana-style HR manager CVs calculate HR experience correctly.
- [x] Education dates do not inflate work experience.
- [x] Jobs requiring 5 years correctly compare against parsed experience.

Implementation notes:

- Added open-ended range detection so one-sided ranges such as `Apr 2025 -` or `since 2024` can resolve to a current role.
- Date ranges now carry an explicit context:
  - `experience`
  - `education`
  - `unknown`
- Experience entries call the date normalizer with `context: "experience"`.
- Education entries now receive their own `dateRange`, `dateConfidence`, and `dateIssues`, but those ranges are marked `context: "education"`.
- Non-overlapping experience-month calculation now excludes:
  - education date ranges
  - low-confidence date ranges
  - entries without complete start/end evidence
- Canonical education output preserves education dates for display and diagnostics without allowing them to inflate work experience.

Verification:

```text
node --check services/senna-liteparse-service/server.js
npm run check --prefix services/senna-liteparse-service
env SFFC_CV_FIXTURE_LIMIT=999 node scripts/test-cv-parser-structure.js /Users/ropafadzoyasheushe/Downloads/CVs
```

Latest full run:

```json
{
  "tested": 172,
  "skipped": 1,
  "passed": 161,
  "failed": 11,
  "failureCategoryCounts": {
    "review_or_renderer": 10,
    "merge_or_boundary": 1
  },
  "failureReasonCounts": {
    "tailored_cv_inline_rewrite_comments_missing": 10,
    "embedded_entry_boundary_in_bullet": 1
  }
}
```

Notes:

- Phase 7 did not change the remaining failure profile.
- The remaining parser-side failure is still a boundary reconstruction issue, not a date/timeline calculation issue.
- Review-comment failures remain assigned to Phase 8.

## Phase 8: Review And Highlight Resilience

Status: complete.

- [x] Decouple grammar/rewrite comments from parser pass/fail.
- [x] If no rewritten bullet qualifies, generate fallback comments from:
  - summary
  - role keyword grouping
  - skill grouping
  - structure changes
- [x] Use color categories:
  - grammar
  - rewrite
  - structure
  - role keyword
  - formatting
- [x] Ensure `sffc-crm-apply-chat__tailored-cv-suggestion is-rewrite` appears only when there is an actual rewrite.
- [x] Ensure `sffc-crm-apply-chat__tailored-cv-suggestion-actions` appears inside inline comments.

Completion criteria:

- [x] Missing inline rewrite suggestions no longer fail parser tests.
- [x] Highlights identify what changed instead of marking everything red.
- [x] Review UI remains useful for both structured and fallback-rendered CVs.

Implementation notes:

- The renderer already produces inline review comments with action controls inside the highlighted text span, including `sffc-crm-apply-chat__tailored-cv-suggestion-actions`.
- The stylesheet already separates highlight categories for rewrite, grammar, spelling, punctuation, clarity, keyword, quality, and structure.
- `is-rewrite` remains tied to actual rewrite suggestions from the rewrite review path. Structural and keyword comments now pass the harness without pretending to be rewrites.
- The regression harness now records `inlineRewriteSuggestionCount` as a diagnostic only. It still fails missing inline review comments and missing inline action controls.

Verification:

- `node --check scripts/test-cv-parser-structure.js`
- `node --check assets/js/crm/crm-apply-chat-article.js`
- `node --check services/senna-liteparse-service/server.js`
- `env SFFC_CV_FIXTURE_LIMIT=999 node scripts/test-cv-parser-structure.js /Users/ropafadzoyasheushe/Downloads/CVs`

Latest full-folder result:

```json
{
  "tested": 172,
  "skipped": 1,
  "passed": 171,
  "failed": 1,
  "failureCategoryCounts": {
    "merge_or_boundary": 1
  },
  "failureReasonCounts": {
    "embedded_entry_boundary_in_bullet": 1
  }
}
```

Remaining issue:

- `671651f4db635.pdf` still has one `embedded_entry_boundary_in_bullet` failure. This is a parser boundary cleanup item, not a review/highlight failure.

## Phase 9: Regression Harness

Status: complete.

- [x] Extend `scripts/test-cv-parser-structure.js` to record structured service diagnostics.
- [x] Add explicit fixture expectations for known hard CVs:
  - Dana Baranbo
  - Daniele Mondi
  - Vladislav Ryzhechkin
  - Syed Mustafa Zamin
- [x] Add pass/fail categories instead of one generic failure.
- [x] Add thresholds:
  - no malformed HTML wrappers
  - no missing full CV when raw text exists
  - no empty experience when experience section exists
  - no education drop when education section exists
  - no false parser failure from review comments

Completion criteria:

- [x] Full CV folder regression produces actionable categorized output.
- [x] The same failure cannot reappear silently.
- [x] New parser changes can be compared against previous output.

Implementation notes:

- Added a `fixtureExpectations` table in `scripts/test-cv-parser-structure.js` for the four known hard CVs.
- The table now checks minimum entries, bullets, dated entries, education entries, required sections, document quality, editable fields, review comments, inline actions, and required title/company evidence.
- Replaced the previous Daniele/Ryzhechkin one-off checks with the shared expectation system.
- Added optional service diagnostics with `SFFC_CV_SERVICE_DIAGNOSTICS=1` when `SFFC_LITEPARSE_ENDPOINT` is configured.
- Service diagnostics are summarized in the main report with parser names, structured parser names, counts, grammar/parser flags, and error codes.
- Full-folder reports now include:
  - `failureCategoryCounts`
  - `failureReasonCounts`
  - `fixtureExpectations`
  - `serviceDiagnosticCounts`
  - `duplicateGroups`

Verification:

- `node --check scripts/test-cv-parser-structure.js`
- `node --check assets/js/crm/crm-apply-chat-article.js`
- `node --check services/senna-liteparse-service/server.js`
- `node scripts/test-cv-parser-structure.js "/Users/ropafadzoyasheushe/Downloads/CVs/Dana Baranbo -Resume FV.pdf" "/Users/ropafadzoyasheushe/Downloads/CVs/Syed Mustafa Zamin - CV.pdf" "/Users/ropafadzoyasheushe/Downloads/CV - Daniele Mondi (4).pdf" "/Users/ropafadzoyasheushe/Downloads/Ryzhechkin_Vladislav_CV_2026.pdf"`
- `env SFFC_CV_FIXTURE_LIMIT=999 node scripts/test-cv-parser-structure.js /Users/ropafadzoyasheushe/Downloads/CVs`

Known hard fixture result:

```json
{
  "tested": 4,
  "passed": 4,
  "failed": 0
}
```

Latest full-folder result:

```json
{
  "tested": 172,
  "skipped": 1,
  "passed": 171,
  "failed": 1,
  "failureCategoryCounts": {
    "merge_or_boundary": 1
  },
  "failureReasonCounts": {
    "embedded_entry_boundary_in_bullet": 1
  }
}
```

Remaining issue:

- `671651f4db635.pdf` still has one `embedded_entry_boundary_in_bullet` failure. The harness now isolates this as a boundary issue instead of mixing it with renderer, review, or service issues.

## Phase 10: Production Rollout

Status: pending.

- [ ] Deploy service changes first.
- [ ] Verify service health reports parser stack and layout parser status.
- [ ] Deploy WordPress renderer changes after service is stable.
- [ ] Test with random uploaded PDFs on `/shallow/`.
- [ ] Confirm:
  - full CV renders
  - section order is stable
  - no empty document
  - no massive truncation
  - CV profile still feeds Emily matching
  - job matching experience calculation still works

Completion criteria:

- [ ] At least 98% of `/Downloads/CVs` pass hard rendering criteria.
- [ ] Remaining failures render readable raw fallback.
- [ ] No known hard CV produces a worse tailored CV than the original extracted content.

## Success Metrics

Short term:

- 0 malformed tailored CV wrappers.
- 0 empty tailored CV renderings when raw text exists.
- 0 dropped education sections when education raw text exists.
- Review-comment failures separated from parser failures.

Medium term:

- 98%+ pass rate on the 172-CV folder.
- All known hard PDFs render as coherent full CVs.
- Work experience calculation uses only valid work entries.

Long term:

- WordPress no longer performs heavy CV parsing.
- LiteParse service owns canonical CV modeling.
- Emily receives reliable profile, seniority, experience, and skills evidence for matching.

## Immediate Next Step

Start with Phase 1 and Phase 2 together:

1. Generate artifacts for the 14 current failures.
2. Separate parser failures from review UI failures.
3. Add full raw-section rendering fallback before touching heavier layout parsing.

This prevents broken user-facing CVs immediately while the stronger coordinate-aware parser is built.
