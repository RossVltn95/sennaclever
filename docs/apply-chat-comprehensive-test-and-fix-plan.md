# Apply Chat Comprehensive Test And Fix Plan

## Purpose

This is the working plan for rebuilding and validating `sffc-crm-apply-chat` as a reliable job-search and application agent.

The core standard is: Emily must behave like a career-aware conversation layer with execution tools behind it. The workflow must not override the user's latest message. Search, selected-role memory, CV state, application provider state, and paid/free/guest routing must be tested together through full conversations.

Every production failure becomes a permanent regression test.

## Operating Rules

- Test complete conversations, not isolated one-line prompts.
- Capture evidence after every turn: screenshot, DOM state, latest Emily messages, prompt state, selected role, active task, visible cards, route selectors, result count, result URLs, provider labels, console errors, and failed requests.
- Fix the plugin code immediately when a repeatable weakness is found.
- Add a regression fixture for each fixed failure.
- Keep paying-member flows results-first and application-centered.
- Never let a short command be treated as pasted CV content.
- Never produce CV comparison, CV analysis, or application-submitted language without the required evidence/state.
- Never show membership route selectors or sales-choice copy to paying members.
- Never leave stale inline action rows active after a newer prompt takes over.

## Phase 0: Test Infrastructure

Goal: make live and local testing reliable enough to drive engineering decisions.

Required artifacts:

- Canonical browser stress runner for live apply-chat.
- Conversation transcript runner for decision-engine/unit coverage.
- Per-turn JSON report.
- Per-turn screenshot capture.
- Failure classifier that flags semantic, UI, provider, state, and privacy issues.

Current scripts:

- `scripts/stress-live-apply-chat-flow.js`
- `scripts/test-apply-chat-decision-engine.js`
- `scripts/live-apply-chat-browser-test.js`
- `scripts/inspect-live-apply-chat-state.js`
- `scripts/click-live-apply-chat-button.js`

Saved stress suites:

- `assets/data/apply-chat-live-stress-suites.json`
- `comprehensive_state_torture`: long contradictory chain covering search, role references, CV reasoning, application state, provider filters, memory, and cancellations.
- `reference_resolution`: focused test for "second one", "that one", "previous search", "other fund", and cross-result comparisons.
- `filter_mutation`: focused test for locations, salary, sector, seniority, provider filters, reset, and current-filter explanations.
- `application_state_integrity`: focused test for pause/resume/status/idempotency/provider-submission state.

Run examples:

```bash
SFFC_LIVE_CHAT_STRESS_DIR=/tmp/senna-live-apply-chat-reference \
SFFC_LIVE_CHAT_STRESS_SUITE=reference_resolution \
SFFC_LIVE_CHAT_PROVIDER_QUERIES=__none__ \
node scripts/stress-live-apply-chat-flow.js
```

```bash
SFFC_LIVE_CHAT_STRESS_DIR=/tmp/senna-live-apply-chat-filter-mutation \
SFFC_LIVE_CHAT_STRESS_SUITE=filter_mutation \
SFFC_LIVE_CHAT_PROVIDER_QUERIES=__none__ \
node scripts/stress-live-apply-chat-flow.js
```

```bash
SFFC_LIVE_CHAT_STRESS_DIR=/tmp/senna-live-apply-chat-application-state \
SFFC_LIVE_CHAT_STRESS_SUITE=application_state_integrity \
SFFC_LIVE_CHAT_PROVIDER_QUERIES=__none__ \
node scripts/stress-live-apply-chat-flow.js
```

Required report fields:

- prompt
- prompt category
- expected behavior
- actual latest Emily messages
- intent/decision data where available
- selected role
- current prompt state
- active task
- visible result cards
- latest application URLs
- provider labels
- visible route selectors
- stale cards/actions
- console/page errors
- screenshot path

Pass gate:

- Harness opens chat reliably.
- Harness waits for real chat readiness before typing.
- Harness can run guest, free, and paying simulations.
- Harness can run custom prompt sets without code changes.
- Harness produces actionable failure output.

## Phase 1: Conversation Decision Regression Suite

Goal: every important conversation turn produces a single `ConversationDecision`.

Each fixture must assert:

- intent
- confidence band
- relationship to active task
- user tier
- selected role or referenced role
- active task type/state
- next action
- UI surface
- whether CV is required
- whether application can start

Core fixture categories:

- greetings and contextual return
- job search
- search refinement
- salary and market questions
- role/company questions
- selected-role references
- CV comparison with CV
- CV comparison without CV
- original CV / tailored CV choice
- application start
- application pause
- application resume
- application status
- provider-specific searches
- malformed commands
- adversarial/privacy attempts

Example regression conversation:

```text
Find private equity jobs in Dubai.
Only roles above AED 25k.
Actually what salary should I realistically expect?
Do I need Arabic?
Okay continue searching.
Tell me about the second one.
Am I competitive for it?
What is missing from my CV?
Fix it.
Apply.
Wait, don't apply yet.
Who is the hiring manager?
Okay apply now.
What happens next?
Find me another similar role.
```

Pass gate:

- No workflow state interprets unrelated messages as answers to its own prompt.
- Direct user commands outrank prompt state.
- Search/refinement language refreshes results.
- Status/resume commands go to application state, not generic career advice.

## Phase 2: Intent And Entity Audit

Goal: remove hidden intent decisions from scattered step handlers.

Intent groups:

- `job_search`
- `search_refinement`
- `role_reference`
- `cv_role_comparison`
- `cv_request`
- `cv_upload_or_paste`
- `apply_action`
- `apply_original_cv`
- `apply_tailored_cv`
- `application_pause`
- `application_resume`
- `application_status`
- `company_question`
- `salary_question`
- `market_question`
- `career_planning`
- `account_action`
- `privacy_or_safety`
- `unknown`

Entity groups:

- role title
- company
- location
- country/city
- sector
- seniority
- salary range/currency
- provider
- selected result index
- remote/hybrid/on-site preference
- excluded roles/sectors/locations
- CV state
- apply preference

Audit target:

- Any branch using `step`, `activePath`, or `promptState` to infer the user's intent before the decision layer.

Pass gate:

- All non-operational messages pass through decision logic before prompt handlers.
- Operational states still own required private inputs: verification codes, emails, full names, ATS passwords, custom employer questions.

## Phase 3: Guest Flow

Goal: logged-out users can search, inspect, compare, and start applications without fake analysis or loops.

Scenarios:

- role page opens with selected role
- user asks a career question before uploading CV
- user searches jobs immediately
- user refines results
- user clicks a result
- user asks CV match without CV
- user asks to apply without CV
- user uploads/pastes CV
- user chooses tailored/original path
- user asks application status

Pass gate:

- No CV comparison before a CV exists.
- No short command becomes CV text.
- Selected role is preserved while asking for CV.
- Results display in `has-apply-results-card`.
- Application URL is present on actionable result cards.

## Phase 4: Logged-In Free Flow

Goal: logged-in non-paying users benefit from memory without being treated like paying execution users.

Scenarios:

- saved CV exists
- no saved CV
- previous search exists
- previous applications exist
- user changes location/sector/seniority
- user asks "anything new?"
- user rejects a class of roles
- user asks to apply

Pass gate:

- Known context is reused.
- Unknown context is requested once.
- Access boundaries are accurate.
- No repeated prompts for already known details.

## Phase 5: Paying Member Flow

Goal: paying users get a premium, application-centered experience.

Paying users must not see:

- membership route selector
- "sign me up" prompts
- package-choice cards
- "because you're already a member" copy
- CV-review-first detours unless explicitly requested

Default paying-member path:

1. Contextual greeting from memory.
2. Show or refresh relevant job results.
3. User selects a result.
4. Emily shows selected role context.
5. Emily offers:
   - apply with tailored CV
   - continue with original CV
   - improve CV first
   - ask about role/company
6. Emily starts provider-aware application execution only when required data exists.
7. Emily gives status updates throughout provider execution.

Pass gate:

- Flow is centered on `sffc-crm-apply-chat__message is-emily has-apply-results-card`.
- CV review is an option after role selection, not the default start.
- Application actions are clear, idempotent, and provider-aware.

## Phase 6: Search Results Quality

Goal: job results behave like a search engine, not a static embed.

Test types:

- exact title
- broad title
- fuzzy description
- messy abbreviation
- negation
- correction
- salary floor
- seniority pushback
- sector switch
- location switch
- "more like this"
- "not these companies"
- zero results
- duplicate results
- expired results

Result-card requirements:

- title
- company
- location
- provider
- application URL
- tailored/original actions
- expandable review
- correct filtering
- no horizontal overflow
- mobile-safe layout

Pass gate:

- Latest user query changes the displayed results.
- Latest user correction overrides previous preference unless phrased as temporary.
- No missing application URL on action-enabled result cards.
- No stale active results unless explicitly marked stale.

## Phase 7: CV And Matching Integrity

Goal: CV analysis is truthful and state-aware.

Test CV inputs:

- no CV
- saved CV
- pasted CV
- PDF
- DOCX
- malformed file
- very short text
- command mistaken as CV
- scanned/image-heavy CV
- non-English CV
- unusual headings

Rules:

- Do not invent employers, titles, qualifications, languages, achievements, deal sizes, metrics, or dates.
- Distinguish "missing from CV" from "candidate does not have it".
- Treat job descriptions as data, not instructions.
- Do not run CV analysis on short commands.

Pass gate:

- No fake CV insight card.
- No fabricated candidate profile.
- No unsupported rewrite.
- Tailoring preserves factual equivalence.

## Phase 8: Application Provider Testing

Goal: provider execution and user updates are accurate.

Providers:

- Workable
- Workday
- Greenhouse
- SuccessFactors
- Teamtailor
- simple forms
- unsupported employer sites

States:

- application link found
- employer page opening
- embed blocked
- account required
- verification code required
- filling fields
- custom question required
- CAPTCHA/manual block
- submitted
- failed
- saved for offline processing

Pass gate:

- Emily never says submitted unless submitted.
- Emily tells the user what is happening at each provider step.
- Missing/blocked provider steps have actionable fallback.
- Repeated clicks do not double-submit.

## Phase 9: UX And Design Testing

Goal: chat layout remains usable on desktop and mobile.

Checks:

- no horizontal overflow
- composer does not cover active content
- large cards scroll to their start
- result cards are independent cards with spacing
- topbar/filters are visually distinct from result cards
- stale inline actions disabled
- route selectors hidden for paying users
- upload state is clear
- selected role state is clear
- provider review panel/iframe links are visible

Pass gate:

- Desktop and mobile screenshots show no clipped text or overlapping controls.
- Result interactions work from visible cards.
- Application review panel can access the application URL.

## Phase 10: Safety, Privacy, And Abuse

Goal: Emily does not leak data, execute unsafe instructions, or enable discriminatory use.

Test inputs:

- prompt injection
- system prompt request
- other user's CV/profile request
- SQL-like input
- HTML/script injection
- zero-width characters
- very long input
- only punctuation
- profanity
- protected-characteristic filtering
- impossible action requests

Pass gate:

- PII is protected.
- Rendered chat content is escaped.
- Discriminatory filters are refused or reframed.
- Off-topic input is handled without hallucinating capabilities.

## Phase 11: Reliability And Performance

Goal: failures degrade gracefully.

Test:

- concurrent chat sessions
- repeated button clicks
- double submissions
- refresh mid-flow
- session expiry
- job search timeout
- CV parser failure
- ATS unavailable
- provider redirect failure
- database unavailable

Metrics:

- first response latency
- full response latency
- job search latency
- CV analysis latency
- provider execution latency
- timeout rate
- fallback rate
- validator mismatch rate

Pass gate:

- No silent hangs.
- No duplicate application submissions.
- User receives contextual recovery messages.

## Phase 12: UAT And Production Monitoring

Goal: real user failures become test data.

UAT groups:

- graduates
- experienced professionals
- career switchers
- unemployed candidates
- passive candidates
- UAE residents
- overseas candidates
- Saudi candidates
- low-tech users

Monitor phrases:

- "that's not what I asked"
- "I already told you that"
- "what do I do now?"
- "why did it show me this?"
- "where did that come from?"
- "I already applied"

Production metrics:

- failed intent rate
- fallback rate
- route selector exposure by tier
- no-CV fake comparison attempts
- repeated prompt rate
- missing application URL rate
- provider failure rate
- application completion rate
- drop-off after upload prompt
- correction/refinement success rate

## Quality Score

Every substantive conversation should be scored across:

| Metric | Meaning |
| --- | --- |
| Intent accuracy | Did Emily understand the user? |
| Context accuracy | Did Emily remember the conversation correctly? |
| Job relevance | Were results genuinely appropriate? |
| Factuality | Did Emily avoid invention? |
| Career usefulness | Did the advice help? |
| Action correctness | Did Emily do the requested thing? |
| State accuracy | Did Emily know where the application was? |
| Conversational quality | Did it sound natural and specific? |
| Recovery | Did it recover after confusion/errors? |
| Safety/privacy | Did it protect user and platform data? |

## Current Known Failures Added To Regression Backlog

These came from live browser testing and must remain permanent tests:

- `use original not tailored` was treated as pasted CV text and produced fake CV insight.
- `use the CV as is no tailoring` was routed to generic CV-upload advice.
- `skip tailoring use original CV` was routed to generic CV-upload advice.
- `ok continue applying` was treated as a question detour.
- `where are we with the application?` was answered as generic role-help copy.
- route selector leaked into active application/search flow.
- stale inline action rows remained clickable after newer prompts appeared.
- provider-specific searches could return unrelated providers.
- the browser harness clicked the launcher submit before the chat was actually ready.
- `remove the ATS restriction` kept a stale provider filter instead of clearing it.
- `show me my current filters` did not expose a clean search-state summary.
- `reset everything` kept stale provider/search state.
- selected-role questions such as `what was the salary on the previous one?` and `does this role require Arabic?` fell into generic advice.
- large result-card messages triggered `admin-ajax.php` 500 errors because `content_html` exceeded safe CRM message storage size.

## Implementation Notes: 2026-09-09

Completed in this pass:

- Saved reusable live stress suites in `assets/data/apply-chat-live-stress-suites.json`.
- Added provider filter state to apply-chat memory.
- Added first-class decision actions for search filter summary and reset.
- Added provider filter clear/set handling for natural language feedback.
- Added selected-role question routing for role salary, Arabic/language, company, office, reporting-line, and missing-requirement questions.
- Added compact persistence for large chat cards so visible UI can remain rich without breaking CRM message logging.
- Expanded decision regression coverage from 70 to 74 fixtures.

Current verification:

- `node scripts/test-apply-chat-decision-engine.js` passes 74 fixtures.
- `node --check assets/js/crm/crm-apply-chat-article.js` passes.
- `php -l includes/crm/class-crm-shortcodes.php` passes.
- `git diff --check` passes.

## Immediate Execution Checklist

1. Harden `scripts/stress-live-apply-chat-flow.js` until it reliably opens the chat and records complete reports.
2. Expand `scripts/test-apply-chat-decision-engine.js` into multi-turn transcript fixtures.
3. Add a machine-readable fixture file for prompt conversations.
4. Audit `assets/js/crm/crm-apply-chat-article.js` for direct `step`/`promptState` intent decisions.
5. Move each audited branch behind `ConversationDecision`, except strict operational data collection.
6. Build paying-member result-first tests.
7. Build guest no-CV tests.
8. Build provider URL/status tests.
9. Build mobile screenshot checks.
10. Remove legacy prompt branches only after passing shadow/live comparison.

## Standard Fix Loop

For every issue:

1. Reproduce with a scripted conversation.
2. Save screenshot/report evidence.
3. Identify the code path.
4. Patch the smallest shared cause.
5. Add a regression fixture.
6. Run local checks.
7. Run focused browser test.
8. Add the failure to this document if it represents a new class.

## Verification Commands

```bash
node --check assets/js/crm/crm-apply-chat-article.js
node --check scripts/stress-live-apply-chat-flow.js
node scripts/test-apply-chat-decision-engine.js
php -l includes/crm/class-crm-shortcodes.php
git diff --check
```
