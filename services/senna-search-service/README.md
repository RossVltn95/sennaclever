# Senna Search Service

Small SearXNG deployment for Emily's apply-chat web-search capability.

This service is intentionally separate from the WordPress plugin. WordPress
will call this service server-side, normalize results, cache them, and render
source-backed cards in apply-chat.

## Backend

- SearXNG official Docker image
- JSON API enabled through `search.formats`
- Runs on port `8080`
- Intended first endpoint:

```text
GET /search?q=best+recruitment+agencies+in+Dubai&format=json
```

## Railway Setup

Create a new Railway service from this folder:

```text
services/senna-search-service
```

Railway settings:

- Port: `8080`
- Health check path: `/`

Recommended environment variables:

```text
SEARXNG_SECRET=<long-random-string>
SEARXNG_BASE_URL=https://your-search-service.up.railway.app/
SEARXNG_LIMITER=false
SEARXNG_IMAGE_PROXY=false
```

The service itself does not expose the token used by the frontend. Token
protection will happen at the WordPress adapter layer in Phase 3.

## Verify

After deployment:

```bash
curl "https://your-search-service.up.railway.app/search?q=best+recruitment+agencies+in+Dubai&format=json"
```

Expected response shape:

```json
{
  "query": "best recruitment agencies in Dubai",
  "number_of_results": 10,
  "results": [
    {
      "url": "https://...",
      "title": "...",
      "content": "...",
      "engine": "duckduckgo",
      "category": "general"
    }
  ]
}
```

If JSON returns `403`, confirm `settings.yml` includes:

```yaml
search:
  formats:
    - html
    - json
```

## WordPress Integration Later

Phase 3 will add:

```php
define('SFFC_SEARCH_ENDPOINT', 'https://your-search-service.up.railway.app/search');
define('SFFC_SEARCH_TOKEN', 'optional-wordpress-adapter-token');
```

WordPress will call SearXNG with `format=json`, then return a smaller normalized
payload to the browser.
