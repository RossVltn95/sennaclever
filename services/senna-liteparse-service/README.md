# Senna LiteParse Service

Small HTTP wrapper around `@llamaindex/liteparse` for CV extraction.

It is intentionally separate from the WordPress plugin so native parser
dependencies stay out of the plugin zip.

## Endpoints

- `GET /health`
- `POST /parse` with multipart field `file`

`/parse` returns:

```json
{
  "ok": true,
  "parser": "liteparse",
  "text": "...",
  "totalPages": 2,
  "pages": []
}
```

## Railway Variables

- `CORS_ORIGIN=https://joinsenna.com`
- `LITEPARSE_TOKEN=...` optional
- `LITEPARSE_OCR_ENABLED=1`
- `LITEPARSE_OCR_LANGUAGE=eng`
- `LITEPARSE_MAX_PAGES=20`
- `LITEPARSE_TIMEOUT_SECONDS=20`

## WordPress Variables

Set these in the WordPress environment:

- `SFFC_LITEPARSE_ENDPOINT=https://your-service.up.railway.app/parse`
- `SFFC_LITEPARSE_PUBLIC_TOKEN=...` only if the service requires a bearer token

If the endpoint is blank or the request fails, the chat falls back to PDF.js.
