# Apply Chat Source-Grounded Web Answer Plan

## Target

Upgrade Emily from showing raw web-search snippets to giving source-grounded, conversational answers.

The current web-search flow is useful but shallow:

```text
User question
  -> SearXNG/search service
  -> WordPress AJAX
  -> generic summary
  -> raw source cards with snippets
```

The desired flow is:

```text
User question
  -> intent gate
  -> web search
  -> source selection
  -> fetch top pages
  -> extract readable page text
  -> rank useful passages
  -> synthesize Emily answer
  -> show answer + cited source cards
```

The goal is not to add a paid LLM. The goal is to use deterministic extraction, scoring, and formatting so Emily can answer like a knowledgeable assistant while staying grounded in public sources.

## Why This Matters

Snippets alone are too weak:

- They are short and often cut mid-sentence.
- They are shaped by search-engine ranking, not user intent.
- They often contain SEO boilerplate.
- They do not let Emily compare sources or explain caveats.
- They make Emily feel like a search UI instead of a useful assistant.

Fetching the actual page content lets us build answers from real source text, then render sources underneath for verification.

## Non-Goals

- Do not scrape private, logged-in, paywalled, or personal-data pages.
- Do not run page fetching directly from browser JavaScript.
- Do not expose search/fetch tokens to the frontend.
- Do not copy long passages from sources into the chat.
- Do not pretend the answer is certain when sources disagree.
- Do not replace local job-search against `jobs` / `sffc-crm-posts`.
- Do not turn CV/application/selected-role commands into web search.

## Recommended Extraction Libraries

### Preferred: Trafilatura

Use `trafilatura` in the search service or a companion content-extraction service.

Why:

- Designed for robust web text extraction.
- Extracts main text and metadata.
- Supports useful output formats including text, JSON, HTML, Markdown, XML.
- Has fallback algorithms and strong boilerplate removal.
- Apache 2.0 for current versions.
- Better fit for Python/Railway services than stuffing extraction into WordPress.

Use cases:

- Articles
- Market reports
- Salary pages
- Career-advice pages
- Company/public profile pages
- Government explainer pages

### Alternative: Mozilla Readability

Use `@mozilla/readability` if the extraction service is Node-based.

Why:

- The same family of logic used by Firefox Reader View.
- Good article extraction.
- Apache 2.0.
- Works with `jsdom` in Node.

Tradeoff:

- Less broad than Trafilatura for mixed page types.
- Needs DOM parsing and careful HTML sanitization.

### Supporting Options To Evaluate Later

- `readability-lxml`: Python article extraction, older but useful fallback.
- `newspaper4k`: article/news extraction candidate.
- `go-readability` / `go-trafilatura`: useful if we later build a small Go extraction service.

Do not start with browser automation for this. Use plain HTTP fetch + extraction first. Browser rendering should only be a fallback for specific sites where content is useful but JS-rendered.

## High-Level Architecture

Current deployment note:

- Search is already live at `https://clever-appreciation-production-3057.up.railway.app/search`.
- WordPress already calls the configured `SFFC_SEARCH_ENDPOINT`.
- Phase 1 should not create a new search service. It should keep the current SearXNG `/search` endpoint and add source-grounded answer synthesis in the WordPress adapter first.
- A later phase can move fetching/extraction from WordPress into a dedicated `/answer` service if latency or dependency needs justify it.

```text
Apply Chat JS
  -> WordPress AJAX: sffc_crm_apply_chat_web_search
    -> Existing Railway search service: /search
      -> SearXNG result list
    -> WordPress source-grounding adapter
      -> Source selector
      -> Page fetcher
      -> Text extractor
      -> Passage ranker
      -> Answer composer
    -> WordPress normalized payload
  -> Emily answer renderer
```

Preferred backend split:

```text
WordPress plugin
  - intent gate
  - AJAX nonce/auth
  - cache
  - render answer payload
  - phase 1 source selector/fetcher/composer

Search service
  - SearXNG query
```

Later backend split:

```text
WordPress plugin
  - intent gate
  - AJAX nonce/auth
  - cache
  - render answer payload

Search/answer service
  - SearXNG query
  - source fetch
  - extraction
  - scoring
  - answer object
```

The later split keeps WordPress lightweight and avoids Python/model dependencies inside the plugin. The first implementation can still be done in WordPress with plain HTTP fetch and conservative HTML-to-text extraction, because the search service already exists and the immediate quality gap is the missing synthesis layer.

## New Response Shape

Current shape:

```json
{
  "summary": "I found some sources worth reviewing.",
  "results": [
    {
      "title": "...",
      "url": "...",
      "snippet": "..."
    }
  ]
}
```

Target shape:

```json
{
  "ok": true,
  "query": "when is the best time to apply for jobs in Dubai",
  "answer": {
    "type": "market_timing",
    "confidence": 0.76,
    "headline": "The strongest application windows are usually early year and post-summer.",
    "shortAnswer": "Based on the sources I found, January-March and September-November are the safest windows to prioritise Dubai applications, because hiring activity tends to restart after budget cycles and slower holiday periods.",
    "keyPoints": [
      "January-March is usually strong because companies reopen headcount and budgets.",
      "September-November often improves after the summer slowdown.",
      "For specialist finance roles, targeted recruiter outreach matters more than waiting for a perfect month."
    ],
    "caveat": "Hiring cycles vary by sector and company, so use timing as an advantage rather than a reason to delay strong applications.",
    "nextStep": "If you want, I can now search live Dubai roles and prioritise the ones closest to your CV."
  },
  "evidence": [
    {
      "sourceId": "s1",
      "title": "...",
      "url": "...",
      "source": "example.com",
      "publishedAt": "",
      "sourceType": "career_advice",
      "reliability": 0.62,
      "usedPassages": [
        {
          "text": "Short extracted passage, not a long copy.",
          "score": 0.82
        }
      ]
    }
  ],
  "results": []
}
```

Frontend should render:

1. Emily-style answer block.
2. Key points.
3. Caveat if needed.
4. Source cards.
5. Follow-up action.

## Query Classification

Before synthesis, classify the question. This controls extraction, ranking, and answer formatting.

Initial types:

- `country_life`
  - Example: `what is Saudi Arabia like`
  - Needs: lifestyle, work culture, safety, cost, practical caveats.
- `market_timing`
  - Example: `when is the best time to apply for jobs in Dubai`
  - Needs: dates/months, hiring-cycle language, recency.
- `salary`
  - Example: `average salary for HR manager Dubai`
  - Needs: salary ranges, currency, source date, role/location match.
- `recruiter_directory`
  - Example: `best recruitment agencies in Dubai`
  - Needs: named agencies, sectors, source reliability.
- `company_research`
  - Example: `what does Mubadala Investment Company do`
  - Needs: official site, company profile, ownership, sectors.
- `visa_legal`
  - Example: `visa rules for working in Dubai`
  - Needs: government/official sources first, date sensitivity.
- `comparison`
  - Example: `compare Dubai and Riyadh for finance careers`
  - Needs: side-by-side answer, pros/cons, market caveat.
- `current_market`
  - Example: `latest hiring trends in Saudi finance`
  - Needs: recent pages first, news/market reports.
- `general_factual`
  - Example: `what is VAT in Saudi Arabia`
  - Needs: official/current source preference.

Fallback type:

- `general_web_answer`

## Source Selection

Start with search results from SearXNG/SearchForge. Then select pages to fetch.

Rules:

- Fetch at most 3 pages initially.
- Allow 5 pages only for directory/list questions.
- Prefer:
  - official government sources,
  - company official pages,
  - established news/business publications,
  - reputable salary/career sites,
  - recruiter/company directories for agency questions.
- Avoid or deprioritize:
  - obvious SEO farms,
  - pages with no snippet/title,
  - irrelevant aggregator pages,
  - social media unless explicitly asked,
  - PDF unless extractor supports it safely,
  - login/paywall pages,
  - pages that block bots repeatedly.

Source scoring:

```text
source_score =
  domain_reliability
+ query_title_match
+ snippet_relevance
+ recency_bonus
+ official_source_bonus
- seo_farm_penalty
- stale_content_penalty
- weak_snippet_penalty
- duplicate_domain_penalty
```

## Page Fetching Constraints

Hard limits:

- 3-5 seconds per page fetch.
- 8-10 seconds total answer budget.
- 1 MB HTML max per page initially.
- No cookies except default public fetch.
- No authentication.
- No form submission.
- Follow redirects, but cap redirects.
- Respect block/failure states.
- Never execute page JavaScript in phase 1.

Cache:

- Cache search results by normalized query.
- Cache fetched/extracted page content by URL.
- Cache final answer by query + locale + source set.

TTL:

- News/current-market: 1 hour.
- Salary/visa/legal: 6-12 hours.
- General country/company pages: 24 hours.
- Failed fetches: 15-30 minutes.

## Extraction Pipeline

For each selected URL:

```text
fetch HTML
  -> detect content type
  -> reject unsupported/private content
  -> extract with Trafilatura
  -> fallback extractor if needed
  -> normalize whitespace
  -> split into passages
  -> score passages against query
```

Passage structure:

```json
{
  "sourceId": "s1",
  "text": "Extracted passage...",
  "heading": "Optional heading",
  "score": 0.82,
  "signals": ["salary", "dubai", "2026"],
  "charStart": 1200,
  "charEnd": 1500
}
```

Passage scoring:

```text
passage_score =
  query_term_overlap
+ entity_overlap
+ type_signal_match
+ number/date_signal_match
+ heading_match
+ source_score
- boilerplate_penalty
- too_short_penalty
- too_long_penalty
```

## Fact Extraction

Extract lightweight facts without an LLM.

General:

- dates and years,
- currencies,
- salary ranges,
- percentages,
- locations,
- companies,
- named agencies,
- visa/legal terms,
- positive/negative phrases,
- repeated claims across sources.

Examples:

Salary question:

```json
{
  "currency": "AED",
  "ranges": ["25,000-35,000 AED/month"],
  "role": "HR Manager",
  "location": "Dubai",
  "sourceCount": 3
}
```

Company question:

```json
{
  "company": "Mubadala Investment Company",
  "description": "Abu Dhabi sovereign investor",
  "sectors": ["technology", "healthcare", "energy", "real estate"],
  "officialSourceFound": true
}
```

## Answer Composition

Use deterministic answer templates by query type.

Template shape:

```text
Short direct answer.

Why:
- point 1
- point 2
- point 3

Caveat:
...

Next step:
...
```

Rules:

- Keep Emily conversational but not fluffy.
- Do not dump source snippets.
- Do not overstate certainty.
- Mention if sources are thin or disagree.
- Use “from the sources I found” when answer depends on web.
- Keep source cards visible below.

No-answer states:

- `no_sources`: “I could not find reliable sources for that.”
- `blocked_sources`: “The best-looking sources blocked extraction, so I can only show search-result snippets.”
- `conflicting_sources`: “Sources disagree; here are the ranges/positions.”
- `needs_specificity`: “This needs a sector/location/title to answer usefully.”

## Formatting Method

Use a structured answer object, not HTML strings as the source of truth.

Backend returns:

```json
{
  "answer": {
    "headline": "",
    "shortAnswer": "",
    "sections": [
      {
        "label": "Why",
        "items": []
      },
      {
        "label": "What to do",
        "items": []
      }
    ],
    "caveat": "",
    "nextStep": ""
  }
}
```

Frontend renders to:

```html
<section class="sffc-crm-apply-chat__web-answer">
  <p class="sffc-crm-apply-chat__web-answer-lead">...</p>
  <div class="sffc-crm-apply-chat__web-answer-section">...</div>
  <p class="sffc-crm-apply-chat__web-answer-caveat">...</p>
</section>
```

This avoids building the answer as one fragile hardcoded paragraph.

## Source Cards

Keep source cards, but change their role:

- They verify the answer.
- They are not the answer.
- They should show why each source was used.

Proposed card fields:

- source domain,
- source type,
- title,
- one short passage/snippet,
- “Used for: salary range / company background / visa rule / timing signal”,
- open link.

## WordPress Integration

Existing endpoint:

```php
sffc_crm_apply_chat_web_search
```

Enhance response normalization:

- Accept `answer`.
- Accept `evidence`.
- Keep old `summary` / `results` for fallback.

Required config:

```php
define('SFFC_SEARCH_ENDPOINT', 'https://your-search-service/search');
define('SFFC_SEARCH_TOKEN', 'optional');
define('SFFC_WEB_SOURCE_FETCH_ENABLED', true);
define('SFFC_WEB_SOURCE_FETCH_LIMIT', 3);
```

Potential new config:

```php
define('SFFC_WEB_ANSWER_ENDPOINT', 'https://your-search-service/answer');
```

Recommended: keep one service endpoint:

```text
POST /answer
{
  "query": "...",
  "locale": "en",
  "limit": 6,
  "fetchPages": true
}
```

## Search Service Endpoint

Recommended endpoint:

```http
POST /answer
```

Request:

```json
{
  "query": "when is the best time to apply for jobs in Dubai",
  "locale": "en",
  "limit": 6,
  "fetchPages": true,
  "maxPages": 3
}
```

Response:

```json
{
  "ok": true,
  "query": "...",
  "classification": {
    "type": "market_timing",
    "confidence": 0.84
  },
  "answer": {},
  "evidence": [],
  "results": [],
  "timings": {
    "searchMs": 800,
    "fetchMs": 2800,
    "extractMs": 350,
    "composeMs": 20,
    "totalMs": 3970
  }
}
```

## Phased Implementation

### Phase 1 - Audit Current Web Search Path

- [x] Map current JS rendering path.
- [x] Map current PHP AJAX path.
- [x] Confirm search service payload shape.
- [x] Identify where `summary`, `results`, and snippets are created.
- [x] Add debug logging for query type, source count, and answer mode.

Acceptance:

- We can trace a web question from user input to rendered HTML.
- Confirmed current endpoint: `https://clever-appreciation-production-3057.up.railway.app/search`.
- Confirmed current WordPress adapter normalizes SearXNG results in `normalize_crm_apply_chat_web_search_results()`.
- Confirmed current frontend renders only generic `summary` plus raw source snippet cards in `renderApplyChatWebSearchAnswerHtml()`.

### Phase 2 - Add Answer Payload Compatibility

- [x] Update WordPress endpoint to return `answer` and `evidence`.
- [x] Update JS renderer to prefer `payload.answer`.
- [x] Keep old snippet-only rendering as fallback.
- [x] Add empty/blocked fallback states.

Acceptance:

- If the service returns `answer`, Emily renders answer first and source cards second.
- Existing search payloads still work.

### Phase 3 - Build Query Classifier

- [x] Implement deterministic query classification.
- [x] Add types: country, timing, salary, recruiter directory, company, visa/legal, comparison, current market.
- [x] Add tests for each type family in `scripts/test-apply-chat-web-search.js`.
- [x] Return classification in payload.

Acceptance:

- `what is Saudi Arabia like` => `country_life`.
- `best recruiters in Dubai` => `recruiter_directory`.
- `average salary for HR manager Dubai` => `salary`.
- `what does Mubadala do` => `company_research`.

### Phase 4 - Source Selection

- [x] Score search results before fetching pages.
- [x] Prefer official/reputable/recent sources by query type.
- [x] Cap selected pages.
- [x] Deduplicate by domain and normalized URL.
- [x] Add basic URL eligibility rules before source fetching.

Acceptance:

- Top 3 selected sources are explainable and relevant.

### Phase 5 - Page Fetching

- [x] Add backend fetcher with timeout, redirect cap, content-type check, content-length cap.
- [x] Fetch pages server-side only.
- [x] Cache fetch failures.
- [x] Track per-source status: fetched, blocked, unsupported, timeout.

Acceptance:

- Service can fetch public pages without blocking Emily for too long.
- Blocked pages degrade to snippets.

### Phase 6 - Content Extraction

- [x] Add optional Trafilatura answer service scaffold.
- [x] Wire WordPress to use `SFFC_SEARCH_ANSWER_ENDPOINT` when configured.
- [x] Integrate Trafilatura in the optional answer service.
- [x] Extract title, text, metadata, date, source language where available.
- [x] Fallback to snippet if extraction fails.
- [x] Add extraction quality score.
- [ ] Deploy the optional answer service on Railway and set `SFFC_SEARCH_ANSWER_ENDPOINT`.

MVP note: source text extraction currently still works in the WordPress adapter with lightweight HTML cleanup. The optional Trafilatura service now exists under `services/senna-web-answer-service/` and WordPress will use it when configured.

Acceptance:

- At least 2 of top 3 public pages produce useful extracted text for normal market questions.

### Phase 7 - Passage Ranking

- [x] Split extracted text into passages.
- [x] Score passages against query and query type.
- [x] Keep top passages per source.
- [x] Remove duplicate selected passages.

Acceptance:

- Evidence passages are short, relevant, and source-linked.

### Phase 8 - Deterministic Answer Composer

- [x] Build answer templates by query type.
- [x] Fill templates from ranked passages and extracted facts.
- [x] Add caveat and next-step generation.
- [x] Add no-answer/blocked fallback handling.

Acceptance:

- Emily answers the question in a useful paragraph before showing sources.

### Phase 9 - Frontend Rendering

- [x] Add `sffc-crm-apply-chat__web-answer`.
- [x] Render headline, lead, sections, caveat, next step.
- [x] Render source cards below answer.
- [x] Use `dir="auto"` for dynamic text.
- [x] Render the synthesized answer as a normal Emily bubble before the source-results card.
- [ ] Mobile QA.

Acceptance:

- The answer feels like Emily speaking, not a search-results page.

### Phase 10 - Caching and Performance

- [x] Cache search responses.
- [x] Cache extracted source text.
- [x] Cache final answer payload.
- [ ] Add stale-while-revalidate option later.
- [x] Track timings.

Acceptance:

- Fresh source-grounded answer returns within target latency or gracefully falls back.

Target:

- Cached: under 800ms.
- Fresh snippet-only fallback: under 2s.
- Fresh source-grounded answer: under 8-10s.

### Phase 11 - Safety and Compliance

- [x] Block sensitive/personal-data queries.
- [x] Avoid long direct excerpts.
- [x] Do not fetch non-public/non-HTTP result URLs.
- [x] Show source links.
- [x] Mark uncertainty when extraction falls back to snippets.
- [x] Respect no-result/failure states.

Acceptance:

- Emily does not hallucinate certainty from thin sources.

### Phase 12 - QA Matrix

Test prompts:

- [ ] `what is Saudi Arabia like`
- [ ] `what is life like in Riyadh for expats`
- [ ] `when is the best time to apply for jobs in Dubai`
- [ ] `average salary for HR manager Dubai`
- [ ] `what does Mubadala Investment Company do`
- [ ] `visa rules for working in Dubai`
- [ ] `compare Dubai and Riyadh for finance careers`
- [ ] `best recruitment agencies in Dubai`
- [ ] `latest hiring trends in Saudi finance`
- [ ] Arabic equivalents.

Checks:

- [x] Correct query type covered by static route/adapter tests.
- [x] Sources fetched when possible.
- [x] Snippet fallback works when blocked.
- [x] Answer appears before sources.
- [x] Source cards render.
- [x] No selected-role clarification for random/general questions.
- [x] No local job search for market-info questions.
- [x] Local job search still works for job-search commands.

## Fallback Strategy

If page fetching fails:

```text
Use snippets -> classify snippets -> compose lower-confidence answer -> show source cards.
```

If snippets are also weak:

```text
Ask for specificity OR say sources were too thin.
```

Do not fall back to hardcoded generic advice unless the query is clearly a general career-advice question.

## Deployment Notes

Likely service changes:

- Add Python dependencies to the search service:
  - `trafilatura`
  - `httpx` or `requests`
  - optional `beautifulsoup4`
- Add endpoint:
  - `POST /answer`
- Keep existing `/search`.

WordPress changes:

- Support the new `answer` payload.
- Keep old `results`-only payload.
- Add config toggles.

Frontend changes:

- Add answer renderer.
- Update source cards.
- Add tests around routing and rendering.

## Risks

- Some pages will block server fetches.
- Extracted pages can contain boilerplate or irrelevant content.
- Source quality varies heavily.
- Fresh answers can be slower.
- Deterministic templates can still feel rigid if too narrow.

Mitigations:

- Use source scoring.
- Keep snippet fallback.
- Cache aggressively.
- Start with a small query-type set.
- Add per-query debug logs.
- Keep answer templates concise and evidence-led.

## Success Criteria

Emily should be able to answer:

`when is the best time to apply for jobs in Dubai`

with:

- a clear answer,
- 2-4 useful supporting points,
- caveat,
- source cards,
- no selected-role clarification,
- no generic hardcoded summary,
- no raw snippet dump as the main answer.

This is the difference between “search results in chat” and “source-grounded assistant response.”
