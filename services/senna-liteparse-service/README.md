# Senna LiteParse Service

Small HTTP wrapper around `@llamaindex/liteparse` for CV text extraction,
`resume-parser-ats` plus optional `pyresume`/`leverparser` for structured CV
parsing, and `harper.js` for offline grammar review.

It is intentionally separate from the WordPress plugin so native parser
dependencies stay out of the plugin zip.

## Endpoints

- `GET /health`
- `POST /parse` with multipart field `file`
- `POST /review-text` with JSON body `{ "text": "..." }`
- `POST /match-job` with JSON body `{ "cvText": "...", "cvStructured": {}, "cvYears": 5, "job": {} }`

`/parse` returns:

```json
{
  "ok": true,
  "parser": "liteparse+resume-parser-ats+pyresume",
  "text": "...",
  "totalPages": 2,
  "pages": [],
  "structured": {
    "parser": "resume-parser-ats+pyresume",
    "profile": {},
    "experience": [],
    "education": [],
    "skills": [],
    "parsers": [
      { "parser": "resume-parser-ats", "ok": true },
      { "parser": "pyresume", "ok": true }
    ]
  }
}
```

`/review-text` returns:

```json
{
  "ok": true,
  "engine": "harper",
  "dialect": "american",
  "matches": [
    {
      "start": 8,
      "end": 9,
      "problemText": "a",
      "kind": "Miscellaneous",
      "message": "Incorrect indefinite article.",
      "suggestions": [{ "kind": "replace", "replacement": "an" }]
    }
  ]
}
```

`/match-job` compares a parsed CV against a job description using
`skill-extractor` plus deterministic seniority and experience-requirement
checks. It returns:

```json
{
  "ok": true,
  "engine": "skill-extractor",
  "fitScore": 79,
  "fitBand": "strong",
  "matchedSkills": ["recruitment", "payroll"],
  "missingSkills": ["workday"],
  "skillCoverageScore": 67,
  "experienceRequirement": { "min": 5, "max": 5 },
  "experienceFit": { "status": "qualified", "candidateYears": 10 }
}
```

The skill classifier loads lazily on the first `/match-job` call. If the ONNX
classifier cannot load, the service falls back to the package gazetteer
candidates so Emily can still score skills without blocking the chat.

## Railway Variables

- `CORS_ORIGIN=https://joinsenna.com`
- `LITEPARSE_TOKEN=...` optional
- `LITEPARSE_OCR_ENABLED=1`
- `LITEPARSE_OCR_LANGUAGE=eng`
- `LITEPARSE_MAX_PAGES=20`
- `LITEPARSE_TIMEOUT_SECONDS=20`
- `PYRESUME_ENABLED=1` optional, set `0` to disable the Python parser
- `PYRESUME_TIMEOUT_MS=12000`
- `HARPER_DIALECT=american` optional, supports `american`, `british`, `canadian`, `australian`
- `HARPER_MAX_TEXT_LENGTH=20000`
- `SKILL_EXTRACTOR_QUANTIZED=0` optional, set `1` to try the smaller quantized ONNX model
- `SKILL_EXTRACTOR_TIMEOUT_MS=6500` optional timeout before falling back to deterministic skill candidates

## WordPress Variables

Set these in the WordPress environment:

- `SFFC_LITEPARSE_ENDPOINT=https://your-service.up.railway.app/parse`
- `SFFC_LITEPARSE_PUBLIC_TOKEN=...` only if the service requires a bearer token

If the endpoint is blank or the request fails, the chat falls back to PDF.js.
