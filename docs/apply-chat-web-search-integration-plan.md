# Apply Chat Web Search Integration Plan

## Target

Give Emily a controlled web-search capability for questions that require current or external knowledge, while keeping normal CV matching, application review, and job-search flows deterministic.

Example user question:

`Which are the best recruitment agencies in Dubai?`

Expected Emily behavior:

- Recognize that this cannot be answered reliably from local CV/job data alone.
- Search the web through a backend service.
- Summarize the answer conversationally.
- Render source-backed results in compact, polished cards.
- Preserve links and source labels so the user can verify the answer.

## Non-Goals

- Do not run search directly from browser JavaScript.
- Do not expose search service tokens in frontend code.
- Do not turn every vague career question into a web search.
- Do not replace existing job matching against `jobs` / `sffc-crm-posts`.
- Do not scrape private or login-only pages.
- Do not present uncited live web answers as if Emily already knew them.

## Recommended Architecture

```text
Apply Chat JS
  -> WordPress AJAX / REST endpoint
    -> Senna Search Adapter
      -> SearXNG or SearchForge service
    -> normalized search result JSON
  -> Emily summary + search result cards
```

## Preferred Backend

Start with SearXNG because it is simple, open source, and has a JSON API.

SearchForge can be evaluated later if we want AI-oriented routing, MCP support, or richer search-source orchestration.

Required WordPress config:

```php
define('SFFC_SEARCH_ENDPOINT', 'https://your-search-service.example/search');
define('SFFC_SEARCH_TOKEN', 'optional-secret-token');
```

## Result Shape

WordPress should normalize search results before returning them to the browser.

```json
{
  "ok": true,
  "query": "best recruitment agencies in Dubai",
  "summary": "Short Emily-style answer.",
  "results": [
    {
      "title": "Recruitment Agency Name",
      "url": "https://example.com",
      "source": "example.com",
      "snippet": "Short relevant extract.",
      "publishedAt": "",
      "type": "web",
      "category": "agency"
    }
  ]
}
```

## Search Trigger Rules

Emily should search when the user asks for information that is likely:

- current,
- external to the WordPress job database,
- market/company/source-specific,
- time-sensitive,
- best-of/list-style,
- salary/trend/news related.

Examples:

- `best recruitment agencies in Dubai`
- `best recruiters in Dubai`
- `top executive search firms in Riyadh`
- `latest hiring trends in Saudi finance`
- `which banks are hiring analysts in UAE right now`
- `average salary for HR manager Dubai`
- `what is Saudi Arabia like`
- `what is life like in Riyadh for expats`
- `when is the best time to apply for jobs in Dubai`
- `what is the latest on Qiddiya hiring`

Emily should not search when:

- the answer can be handled from uploaded CV data,
- the user is asking to tailor/apply/save a role,
- the user is filtering local job results,
- the user is asking about a selected role already in our database,
- the user is asking a personal fit question that should use their CV.

## UI Direction

Adapt the visual feel of `.result` from:

`file:///Users/ropafadzoyasheushe/Downloads/benzo.html#`

But make it search-specific.

Proposed classes:

```html
<div class="sffc-crm-apply-chat__web-search">
  <div class="sffc-crm-apply-chat__web-search-header">
    <span>Web results</span>
    <small>Sources Emily checked</small>
  </div>
  <div class="sffc-crm-apply-chat__web-search-results">
    <article class="sffc-crm-apply-chat__web-result">
      <div class="sffc-crm-apply-chat__web-result-source">example.com</div>
      <h3>Result title</h3>
      <p>Snippet...</p>
      <a href="https://example.com" target="_blank" rel="noopener">Open source</a>
    </article>
  </div>
</div>
```

Card requirements:

- Compact, not paywall-like.
- Clear source/domain label.
- Short title and snippet.
- Open-source link.
- Mobile-safe.
- Arabic-compatible.
- Visually separate from job cards and CV cards.
- No horizontal overflow.
- No huge block of search text before the cards.

## Phase 1 - Discovery And Design Lock

Tasks:

- [x] Inspect `benzo.html .result` and identify reusable visual ideas.
- [x] Inspect current apply-chat message/card CSS conventions.
- [x] Decide exact search card class names and markup.
- [x] Decide whether first implementation uses SearXNG directly or SearchForge in front of SearXNG.

Acceptance:

- [x] Final card markup shape is documented.
- [x] Backend option is selected.
- [x] No production code changed yet except this plan if still in planning mode.

### Phase 1 Findings

Reference design from `benzo.html .result`:

- Compact white card with light border, soft shadow, and small hover lift.
- Meta row first: icon/source, location or context, freshness.
- Strong linked title.
- Short explanatory paragraph.
- Small action row.
- Width is constrained and readable, not full-bleed.

Existing apply-chat conventions:

- Emily text uses `.sffc-crm-apply-chat__bubble`.
- Job/application results use `.sffc-crm-apply-results__result`.
- Larger reasoning cards use `.sffc-crm-apply-chat__results-briefing`.
- Search result cards should not reuse job-result classes because source-backed web answers are informational, not application actions.

### Locked Backend Decision

Phase 2 starts with **SearXNG directly** behind the WordPress adapter.

Reasoning:

- SearXNG is enough for the first production-quality web-search path.
- It has a simple JSON API.
- It avoids adding another routing layer before we know we need it.
- SearchForge remains a later upgrade if we need intent routing across multiple search backends.

### Locked Card Markup

Use a dedicated search card family:

```html
<section class="sffc-crm-apply-chat__web-search" data-sffc-apply-chat-web-search>
  <header class="sffc-crm-apply-chat__web-search-head">
    <div>
      <span class="sffc-crm-apply-chat__web-search-kicker">Web results</span>
      <strong class="sffc-crm-apply-chat__web-search-title">Sources Emily checked</strong>
    </div>
    <span class="sffc-crm-apply-chat__web-search-count">5 sources</span>
  </header>
  <div class="sffc-crm-apply-chat__web-search-list">
    <article class="sffc-crm-apply-chat__web-result">
      <div class="sffc-crm-apply-chat__web-result-meta">
        <span class="sffc-crm-apply-chat__web-result-icon">R</span>
        <span class="sffc-crm-apply-chat__web-result-source">example.com</span>
        <span class="sffc-crm-apply-chat__web-result-type">Agency</span>
      </div>
      <h3 class="sffc-crm-apply-chat__web-result-title">
        <a href="https://example.com" target="_blank" rel="noopener noreferrer">Result title</a>
      </h3>
      <p class="sffc-crm-apply-chat__web-result-snippet">Snippet...</p>
      <div class="sffc-crm-apply-chat__web-result-actions">
        <a class="sffc-crm-apply-chat__web-result-open" href="https://example.com" target="_blank" rel="noopener noreferrer">Open source</a>
      </div>
    </article>
  </div>
</section>
```

### Locked Visual Direction

- Width: `min(100%, 820px)` inside an Emily message.
- Card radius: `8px` to stay consistent with the app.
- Border: light neutral blue/grey.
- Background: white, with very subtle warm/off-white group background if needed.
- Source icon: small square with first source letter, not a logo fetch.
- Title: blue link, compact, two-line safe.
- Snippet: muted text, max 2-3 lines where possible.
- Actions: one text/link-style action, not a heavy CTA row.
- Arabic: use `dir="auto"` on source text/title/snippet containers where rendered dynamically.

## Phase 2 - Backend Search Service

Tasks:

- [x] Configure SearXNG service files.
- [x] Enable JSON search output.
- [x] Document Railway deployment settings.
- [x] Prepare SearXNG/SearchForge deployment scaffold.
- [x] Document optional bearer/shared token boundary.
- [x] Document a basic structured JSON verification query.
- [x] Keep live service verification in Phase 8 deployment.

Acceptance:

- [x] A query like `best recruitment agencies in Dubai` has a documented JSON verification command.
- [x] Result count, title, URL, source, and snippet fields are documented for normalization.
- [x] Service timeout behavior is documented.

### Phase 2 Service Scaffold

Added deployable service scaffold:

`services/senna-search-service/`

Files:

- `Dockerfile`
- `settings.yml`
- `railway.json`
- `README.md`

Deploy target:

```text
services/senna-search-service
```

Railway port:

```text
8080
```

Health check path:

```text
/
```

Test query after deployment:

```bash
curl "https://your-search-service.up.railway.app/search?q=best+recruitment+agencies+in+Dubai&format=json"
```

Expected SearXNG fields:

- `query`
- `number_of_results`
- `results[].title`
- `results[].url`
- `results[].content`
- `results[].engine`
- `results[].category`

Live service deployment and production query confirmation are tracked in Phase 8,
because they require the final service URL and WordPress constants.

## Phase 3 - WordPress Search Adapter

Tasks:

- [x] Add a WordPress AJAX endpoint for apply-chat web search.
- [x] Read `SFFC_SEARCH_ENDPOINT`.
- [x] Read optional `SFFC_SEARCH_TOKEN`.
- [x] Sanitize query and locale.
- [x] Apply request timeout.
- [x] Normalize backend response into the agreed result shape.
- [x] Add transient caching by normalized query and locale.
- [x] Add a clean empty-results response and failure response for timeout/service errors.

Acceptance:

- [x] Browser never calls SearXNG/SearchForge directly.
- [x] Raw backend output is never sent unfiltered to the frontend.
- [x] Repeated identical queries hit cache.
- [x] Endpoint returns predictable JSON for success, empty, and failure.

Implementation notes:

- WordPress action: `sffc_crm_apply_chat_web_search`.
- Frontend config nonce: `sffcCrmApplyChatArticle.webSearchNonce`.
- Service config:
  - `SFFC_SEARCH_ENDPOINT` should point at the SearXNG `/search` endpoint.
  - `SFFC_SEARCH_TOKEN` is optional and sent as a Bearer token if present.
- The endpoint returns `success: true` with `data.results`, `data.summary`, `data.source`, `data.cached`, and `data.searchedAt`.
- Phase 3 is backend-ready. Live search still depends on deploying/configuring the SearXNG service URL in WordPress.

## Phase 4 - Emily Search Intent Routing

Tasks:

- [x] Add a high-confidence route for external/current knowledge questions.
- [x] Keep existing CV/job/application intents higher priority where appropriate.
- [x] Build a search query from user text, selected locale, and context.
- [x] Do not mutate job filters unless the user explicitly asks to search jobs.
- [x] Add fallback when search is unavailable.

Acceptance:

- [x] `best recruitment agencies in Dubai` triggers web search.
- [x] `show me HR manager jobs in Dubai` still uses job search.
- [x] `am I a fit for this role?` still uses CV/job matching.
- [x] Search failure produces a calm, useful Emily response.

Implementation notes:

- New decision intent: `web_search`.
- New next action: `web_search`.
- Routing is deliberately conservative:
  - external/current career knowledge questions can search the web;
  - explicit role/job listing searches still use the internal jobs search;
  - CV, application, selected-role, and prompt-answer paths retain priority.
- Phase 4 uses a basic summary/link response. Designed cards are deferred to Phase 5.

## Phase 5 - Search Result Rendering

Tasks:

- [x] Add JS renderer for web search result cards.
- [x] Add CSS for the search result group.
- [x] Render Emily's short summary first, then cards.
- [x] Support Arabic labels.
- [x] Keep cards compact and scannable.
- [x] Add source-domain display.
- [x] Add "searched web" / source confidence microcopy without making the UI noisy.

Acceptance:

- [x] Cards look designed and organized.
- [x] Cards do not resemble pricing/paywall blocks.
- [x] Cards work on desktop and mobile.
- [x] Links open correctly.
- [x] Long titles/snippets do not overflow.

Implementation notes:

- Renderer: `renderApplyChatWebSearchAnswerHtml`.
- Surface class: `sffc-crm-apply-chat__web-search-card`.
- Message class: `has-web-search-card`.
- Result links open in a new tab with `rel="noopener noreferrer"`.
- Snippets are clamped to two lines and titles can wrap long domains/phrases safely.

## Phase 6 - Caching, Guardrails, And Safety

Tasks:

- [x] Cache results for generic questions for 12-24 hours.
- [x] Use shorter cache TTL for obviously current/news questions.
- [x] Block or decline searches for sensitive personal/private-data lookups.
- [x] Limit result count to 5-8.
- [x] Strip tracking-heavy URLs if possible.
- [x] Log failures for debugging without logging sensitive user content unnecessarily.

Acceptance:

- [x] Repeated generic searches are fast.
- [x] No direct token leakage.
- [x] No sensitive personal lookup support.
- [x] Bad/empty results are handled without breaking chat.

Implementation notes:

- Generic searches cache for 12 hours; current/news-style searches cache for 1 hour.
- Empty result payloads cache for only 30 minutes.
- Server-side result limits are clamped to 5-8 results.
- Search result URLs remove common tracking parameters such as `utm_*`, `fbclid`, `gclid`, `gbraid`, `wbraid`, and newsletter/referral IDs.
- Sensitive lookup guard blocks emails, phone-like strings, IDs, addresses, DOB, passwords/OTP, and doxxing-style searches.
- Failure logs use a short query hash and sanitized context only; tokens, headers, raw URLs, and raw query text are not logged.

## Phase 7 - Test Matrix

Prompts to test:

- [x] `best recruitment agencies in Dubai`
- [x] `best recruiters in Dubai`
- [x] `best finance recruitment agencies in Dubai`
- [x] `top executive search firms in Riyadh`
- [x] `latest hiring trends in Saudi finance`
- [x] `average salary for HR manager Dubai`
- [x] `what is Saudi Arabia like`
- [x] `what is life like in Riyadh for expats`
- [x] `when is the best time to apply for jobs in Dubai`
- [x] `what is the cost of living in Riyadh`
- [x] `what are visa rules for working in Dubai`
- [x] `what does Mubadala Investment Company do`
- [x] `compare Dubai and Riyadh for finance careers`
- [x] Arabic version of recruitment-agency query.
- [x] `show me HR manager jobs in Dubai`
- [x] `find finance jobs in Dubai`
- [x] `am I a fit for this job?`

Checks:

- [x] Correct route chosen.
- [x] Source cards render.
- [x] No console errors.
- [x] No horizontal overflow.
- [x] Search does not override CV/job/application state.
- [x] Timeout and no-result states work.
- [x] Cached repeat query works.

Implementation notes:

- Added `scripts/test-apply-chat-web-search.js` to verify the web-search route signals, CV/job-flow exclusions, AJAX wiring, card markup, mobile CSS, SearXNG service scaffold, caching, tracking cleanup, and sensitive-query guardrails.
- Added a `has-web-search-card` CSS layout hook so source cards follow the same structured-card sizing path as the other apply-chat cards.
- Phase 7 audit fixed plain `average salary` / `pay range` route coverage, added `dir="auto"` to dynamic web-result text, and corrected URL cleanup to use `http_build_query()` instead of WordPress `build_query()`.
- Added the web-search prompt matrix to `scripts/test-apply-chat-decision-engine.js`, so `best recruitment agencies in Dubai`, `best recruiters in Dubai`, `top executive search firms in Riyadh`, `latest hiring trends in Saudi finance`, `average salary for HR manager Dubai`, `what is Saudi Arabia like`, `what is life like in Riyadh for expats`, and `when is the best time to apply for jobs in Dubai` must route to `web_search`.
- Expanded the active-role guard so living/cost-of-living, visa/legal, salary/tax, company research, market-comparison, and current-market questions interrupt selected-role/application state and route to web search instead of clarification. Added negative coverage so concrete job requests such as `find finance jobs in Dubai` still route to local job search.
- The audit also fixed the broad selected-role salary detector so market salary questions with a named location no longer get misclassified as selected-role questions.
- Ran the existing apply-chat decision suite to confirm web search did not break job/CV/application routing.

## Phase 8 - Deployment

Tasks:

- [ ] Deploy search service.
- [ ] Add WordPress constants.
- [ ] Deploy plugin changes.
- [ ] Test production `/shallow/`.
- [ ] Test logged-in and guest flows.
- [ ] Confirm service cost/uptime behavior.

Acceptance:

- [ ] Production chat can answer source-backed external questions.
- [x] Existing CV/job/application flows still pass locally.
- [x] Search can be disabled by removing endpoint config.

Implementation notes:

- Railway CLI is not installed locally, so service deployment must be completed from Railway's GitHub UI or after installing/logging into Railway CLI.
- Search service source is ready at `services/senna-search-service/`.
- Railway root directory: `services/senna-search-service`.
- Railway app port: `8080`.
- Railway health check path: `/`.
- After Railway deploys, set WordPress:

```php
define('SFFC_SEARCH_ENDPOINT', 'https://YOUR-RAILWAY-DOMAIN.up.railway.app/search');
```

- `SFFC_SEARCH_TOKEN` is optional in the current SearXNG-only setup. Use it only if a token-checking wrapper is added in front of SearXNG.
- Production smoke query:

```bash
curl "https://YOUR-RAILWAY-DOMAIN.up.railway.app/search?q=best+recruitment+agencies+in+Dubai&format=json"
```

- Production Emily test prompts:
  - `best recruitment agencies in Dubai`
  - `top executive search firms in Riyadh`
  - `latest hiring trends in Saudi finance`
  - `average salary for HR manager Dubai`
  - `what is Saudi Arabia like`
  - `what is life like in Riyadh for expats`
  - `when is the best time to apply for jobs in Dubai`
  - `show me HR manager jobs in Dubai`
  - `am I a fit for this job?`

## Open Decisions

- Use SearXNG only, or SearchForge wrapping SearXNG?
- Railway or VPS hosting?
- Cache TTL defaults.
- Whether search summaries should be generated client-side from snippets or server-side in PHP.
- Whether to add a visible "Web" chip to Emily messages that used search.

## Definition Of Done

- Emily can answer external/current career questions with cited web cards.
- Search is routed through WordPress, not browser-side code.
- Search results are normalized, cached, and failure-safe.
- UI is compact, polished, source-forward, and mobile-safe.
- Existing job matching and CV tailoring behavior remains intact.
