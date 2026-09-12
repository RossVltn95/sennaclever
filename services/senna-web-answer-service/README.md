# Senna Web Answer Service

Optional source-grounded answer service for Emily.

This service sits next to the existing SearXNG search service. It does not use
an LLM. It calls SearXNG, selects the best public sources, extracts readable page
text with Trafilatura, ranks passages, and returns a compact `answer + evidence`
payload for WordPress.

## Endpoints

```text
GET /health
POST /answer
GET /answer?q=when+is+the+best+time+to+apply+for+jobs+in+Dubai
```

## Environment

```text
SEARXNG_ENDPOINT=https://clever-appreciation-production-3057.up.railway.app/search
SENNA_ANSWER_TOKEN=optional-shared-token
MAX_SOURCES=3
REQUEST_TIMEOUT=5
```

If `SENNA_ANSWER_TOKEN` is set, WordPress must call the service with:

```text
Authorization: Bearer <token>
```

## WordPress

Configure:

```php
define('SFFC_SEARCH_ANSWER_ENDPOINT', 'https://your-answer-service.up.railway.app/answer');
define('SFFC_SEARCH_ANSWER_TOKEN', 'optional-shared-token');
```

If this service is unavailable, WordPress falls back to its local lightweight
answer synthesis.
