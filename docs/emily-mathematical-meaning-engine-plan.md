# Emily Mathematical Meaning Engine Plan

## Objective

Upgrade Emily from a trigger/rule-based chat flow into a deterministic meaning engine that can interpret messy user messages, infer the likely task, rewrite the message into the right internal action, and decide whether to use the jobs database, CV/profile context, application flow, or web answer service.

The goal is not to build a general LLM. The goal is to combine mathematical NLP, dictionaries, taxonomies, proximity scoring, memory, and browser-backed evidence so Emily can handle real user language such as:

- "Emily I'm tired of my job search, what do you suggest?"
- "I need something in Dubai but not banking"
- "Are recruiters better than applying directly in Saudi?"
- "Can you help me figure out why I'm not getting replies?"
- "Find finance jobs in Riyadh"
- "What is it like working in Dubai?"

## Core Principle

Never treat the raw user message as the final query by default.

Pipeline:

```text
raw user message
  -> normalize text
  -> tokenize and lemmatize
  -> detect entities
  -> score word families
  -> score word proximity
  -> classify intent probabilities
  -> merge memory/CV/application context
  -> decide action
  -> rewrite into structured job query or web query
  -> render the right Emily response
```

## Recommended Open-Source Stack

### 1. winkNLP

Use for tokenization, sentence detection, POS tags, lemmas/stems, named entities, n-grams, negation handling, similarity, BM25 vectorization, and word vectors.

Repository: https://github.com/winkjs/wink-nlp

Why:

- Runs in Node/browser.
- Fast enough for chat.
- Provides linguistic structure without external APIs.
- Gives us clean tokens, stems, entities, and similarity primitives.

### 2. wink-naive-bayes-text-classifier

Use for intent probability classification.

Repository: https://github.com/winkjs/wink-naive-bayes-text-classifier

Why:

- Lightweight and deterministic.
- Good for chatbot-style intent classification.
- Can be trained with our own utterances.
- Produces intent predictions that can be combined with our rule scores.

### 3. wink-bm25-text-search

Use for matching user messages against intent archetypes and action templates.

Repository: https://github.com/winkjs/wink-bm25-text-search

Why:

- BM25 is a probabilistic relevance algorithm.
- Better than raw keyword matching.
- Lets us keep a library of example prompts/archetypes and retrieve the closest meaning.

### 4. NLP.js

Use as an optional bot-oriented intent/entity layer.

Repository: https://github.com/axa-group/nlp.js

Why:

- Built for chatbot intent classification.
- Supports entity extraction, sentiment, and language detection.
- Useful as a second opinion behind Emily's deterministic scoring.
- Should not own the action router; it should provide additional probabilities.

### 5. ESCO / Tabiya Open Dataset

Use as a job, occupation, and skill taxonomy.

Repository: https://github.com/tabiya-tech/tabiya-open-dataset

Why:

- Large occupation and skill dictionary.
- Gives role families, skill labels, and occupation relationships.
- Helps identify terms like "finance", "talent acquisition", "private equity", "data analyst", "payroll", etc.

### 6. WordNet

Use for synonyms and word-family expansion.

Repositories:

- https://github.com/morungos/wordnet
- https://github.com/nlp-compromise/wordnet.js

Why:

- Helps map "tired", "exhausted", "burned out", "frustrated".
- Helps map "job", "role", "position", "opportunity".
- Helps map "find", "search", "look for", "hunt".

### 7. Chrono

Use for natural-language dates and timing.

Repository: https://github.com/wanasit/chrono

Why:

- Parses natural dates like "last month", "next week", and "Sep 2022 - Present".
- Useful for CV experience calculations and time-aware job queries.
- Reduces bespoke date parsing over time.

### 8. Location Dictionary

Use a city/country dictionary for offline location recognition.

Repositories:

- https://github.com/jetsetexpert/cities-json
- https://github.com/WebReflection/geo2city

Why:

- Detects "Dubai", "Riyadh", "Abu Dhabi", "Saudi", "UAE", etc.
- Avoids maintaining location lists manually.
- Allows location-aware job queries and web queries.

## Target Output Shape

Every user message should produce an internal meaning object:

```json
{
  "raw": "Emily I'm tired of my job search, what do you suggest?",
  "normalized": "emily i am tired of my job search what do you suggest",
  "primaryIntent": "career_advice",
  "confidence": 0.86,
  "secondaryIntents": [
    { "intent": "job_search", "score": 0.42 },
    { "intent": "web_answer", "score": 0.58 }
  ],
  "emotion": {
    "label": "frustrated",
    "score": 0.78
  },
  "entities": {
    "roles": [],
    "sectors": [],
    "skills": [],
    "locations": [],
    "companies": []
  },
  "context": {
    "hasCv": true,
    "selectedRole": null,
    "activeApplicationFlow": false
  },
  "action": {
    "type": "career_advice_with_web_support",
    "shouldSearchJobs": false,
    "shouldSearchWeb": true,
    "shouldAskClarification": false
  },
  "rewrittenQueries": {
    "web": "job search burnout improve application response rate practical steps",
    "jobs": null
  },
  "explanation": [
    "Detected frustration words near job-search terms.",
    "No concrete role or location requested.",
    "Question asks for advice rather than live job listings."
  ]
}
```

## Mathematical Scoring Model

### Token Score

Each token gets a base score by dictionary family.

```text
token_score =
  dictionary_weight
+ entity_weight
+ part_of_speech_weight
+ rarity_weight
- stop_word_penalty
```

Examples:

- "finance" -> sector score
- "jobs" -> job-search object score
- "dubai" -> location score
- "tired" -> frustration score
- "suggest" -> advice request score

### Proximity Score

Nearby meaningful tokens should form stronger concepts.

```text
proximity_score(a, b) =
  pair_weight(a_family, b_family) * (1 / (1 + token_distance))
```

Examples:

- "finance" + "jobs" close together -> job search intent
- "jobs" + "dubai" close together -> location-specific job search
- "tired" + "job search" close together -> job-search frustration
- "recruiters" + "dubai" close together -> recruiter-directory web answer

### Phrase Score

Known multi-word phrases get stronger weight than individual words.

```text
phrase_score =
  exact_phrase_weight
+ fuzzy_phrase_weight
+ synonym_phrase_weight
```

Examples:

- "job search"
- "best recruiters"
- "recruitment agencies"
- "not getting replies"
- "burned out"
- "apply directly"
- "work in Dubai"

### Intent Score

```text
intent_score =
  dictionary_signal_score
+ phrase_score
+ proximity_score
+ bm25_archetype_score
+ naive_bayes_probability
+ memory_context_score
+ cv_context_score
+ sentiment_context_score
- ambiguity_penalty
- active_flow_conflict_penalty
```

### Action Decision

```text
if job_search_score >= threshold and has_role_or_sector_or_location:
    action = jobs_database_search
elif recruiter_directory_score >= threshold:
    action = web_answer
elif market_info_score >= threshold:
    action = web_answer
elif career_advice_score >= threshold:
    action = career_advice_with_optional_web
elif selected_role_question_score >= threshold:
    action = selected_role_answer
elif active_application_flow and message_is_ambiguous:
    action = clarification
else:
    action = general_web_answer
```

## Phase 1 Audit Findings

Status: completed on 2026-09-13.

The audit shows that Emily does not need a completely separate routing system first. The plugin already contains a decision-engine scaffold, but it is still being constrained by older trigger gates and legacy fallback handlers.

### Current Routing Map

Main submit path:

- `assets/js/crm/crm-apply-chat-article.js:155940` starts the composer submit flow.
- It runs admin commands, human takeover checks, social/off-script checks, high-priority commands, then `handleConversationDecisionBeforePrompt(value)`.
- If the decision engine does not claim the turn, control falls through to `maybeHandlePromptReply(value)` and then legacy `step` handlers such as `job_search_questions`, `apply_upload`, `profile_review_complete`, and selected-role flows.

Decision-engine path:

- `buildEmilyDecisionFeatures(value, context)` builds the current feature packet.
- `scoreEmilyRoutes(...)` already combines deterministic, probability, semantic, context, memory, workflow-safety, and penalty components.
- `classifyLocalProbabilisticApplyChatIntent(...)` is a custom weighted rule model, not a true learned probability model.
- `classifyEmilyDecisionIntent(...)` still uses hard priority ordering.
- `getConversationDecisionNextAction(...)` chooses the action and currently passes either a normalized job query or a lightly cleaned web query.
- `executeConversationDecision(...)` calls job search, web search, direct career answer, application flow, selected-role answer, or clarification.
- `validateConversationDecision(...)` can force clarification when the route is considered unsafe, low-confidence, or conflicting with an active prompt.

### What Already Exists

- A weighted route ensemble already exists. We should enhance it rather than create a second decision engine beside it.
- A web-answer service already exists and can return source-grounded answers.
- A chat memory layer already exists and contributes previous intent, selected role, paused tasks, CV availability, and visible search state.
- CV/job matching now has richer parsing and scoring than before.
- Search result rendering and web-answer rendering already exist.

### Core Failures Found

1. Multiple routers still compete.
   The decision engine is not the only authority. When confidence is below threshold or the next action defers, older prompt and step handlers still decide what happens.

2. Web search is too hard-gated.
   `looksLikeHighConfidenceWebSearchRequest(...)` handles explicit market/recruiter/company/salary/country questions, but indirect natural prompts can miss the gate and fall into selected-role clarification or direct career fallback.

3. Job search uses weak query meaning.
   `looksLikeConcreteApplyChatJobSearch(...)` can classify messages like "find jobs in Riyadh" correctly as job search, but the subsequent query still does not become a structured meaning object with role, sector, location, and constraints.

4. Web search query rewriting is too shallow.
   `buildApplyChatWebSearchQuery(...)` mostly strips polite filler and sends the raw user sentence. This is why Emily cannot reliably convert "I'm tired of my job search, what do you suggest?" into a useful research query.

5. The probabilistic classifier is not truly probabilistic yet.
   `classifyLocalProbabilisticApplyChatIntent(...)` is a weighted regex rule model. It is useful, but it has no external dictionary, token-family proximity, BM25 archetype matching, or trained Naive Bayes model.

6. Selected-role context still overpowers general questions.
   Active role/application context contributes useful safety, but the guard can still trigger clarification when the user is clearly asking a general market/life/career question.

7. Direct career answers are too hardcoded.
   `answer_directly` can return local canned career advice. For unknown/general questions, the better default should be web-backed synthesis unless the message is clearly about a current role, CV, or application step.

8. There is no single canonical meaning object consumed by all routes.
   `buildEmilyDecisionFeatures(...)` is close, but downstream actions still operate on raw strings and legacy prompt state.

### Audit-Driven Direction Change

The implementation should upgrade the existing decision engine in place:

- Add a canonical meaning object on top of `buildEmilyDecisionFeatures(...)`.
- Make query rewriting a first-class output, not a helper after routing.
- Add dictionaries, proximity scoring, BM25, and Naive Bayes into the current route ensemble.
- Make active role/application state a context modifier, not a hard guard except for genuine application commands.
- Route unknown/general questions to web-answer by default when no internal job/CV/application action is clearly stronger.
- Keep legacy handlers during migration, but make the decision engine claim more turns as confidence improves.

## Intent Families

### Job Search

Signals:

- find, search, show, look for, recommend
- jobs, roles, positions, openings, opportunities
- role title, sector, location

Action:

- Search internal jobs database.
- Use CV fit if available.
- Do not call web answer unless no internal results and query is broad market research.

### Career Advice

Signals:

- suggest, advice, help, stuck, tired, frustrated, not getting replies
- job search, applications, interviews, recruiters

Action:

- Give conversational advice.
- If the question is general/current, use web answer service as supporting evidence.
- If CV exists, use CV/profile context.

### Web Research

Signals:

- best recruiters, agencies, salary, visa, market, timing, company research, country/city lifestyle

Action:

- Rewrite query.
- Call web answer service.
- Render summary first, sources second.

### Application Flow

Signals:

- apply, tailor, submit, continue application, employer form

Action:

- Continue selected application flow.
- Ask clarification only if user message genuinely conflicts with active role context.

### CV/Profile Reasoning

Signals:

- my CV, my profile, fit, am I qualified, what roles suit me

Action:

- Use parsed CV profile.
- Use job matching if role exists.
- Use career advice if no role exists.

## Query Rewriting Rules

### Raw Message

```text
you know emily im so tired of my job search what do you suggest
```

### Meaning

```json
{
  "intent": "career_advice",
  "emotion": "frustrated",
  "domain": "job_search"
}
```

### Rewritten Web Query

```text
job search burnout improve response rate practical application strategy
```

### Emily Response Strategy

1. Acknowledge the problem briefly.
2. Give practical diagnosis.
3. Offer next action.
4. Use web answer only if useful, not as the whole response.

## Phased Implementation

### Phase 1: Baseline Audit

Status: complete.

- [x] Map all current intent decision points in `crm-apply-chat-article.js`.
- [x] Identify where raw user messages are used directly as job/web queries.
- [x] Identify where selected-role guard can hijack general questions.
- [x] Document current action paths:
  - jobs search
  - web search
  - selected role
  - CV upload
  - application flow
  - fallback clarification
- [x] Confirm the existing weighted decision engine should be enhanced in place.

Completion criteria:

- A code map exists.
- Known failure prompts are listed.
- Current false positives are reproduced.
- Phase 2 can build on the existing decision-engine functions rather than adding a separate router.

### Phase 2: Meaning Object Contract

- Status: complete.

- [x] Define the canonical meaning object.
- [x] Implement it as a wrapper around the existing `buildEmilyDecisionFeatures(...)` output rather than a parallel classifier.
- [x] Add stable fields:
  - `primaryIntent`
  - `confidence`
  - `secondaryIntents`
  - `emotion`
  - `entities`
  - `action`
  - `rewrittenQueries`
  - `explanation`
- [x] Decide where it lives:
  - frontend-only for speed, or
  - service-backed for larger dictionaries.
- [x] Store rewritten job and web queries on the meaning object before any action executes.

Completion criteria:

- A single function can return a meaning object for any message.
- Existing flows can consume it without behavior changes yet.
- Debug output can show the raw message, features, route scores, selected action, and rewritten query.

Implementation notes:

- `buildConversationDecision(...)` now includes `decision.meaning`.
- `root.__sffcApplyChatTest.buildEmilyMeaningObject("...")` returns the canonical object when test hooks are enabled.
- `window.sffcDebugEmilyMeaning("...")` is available under the same test-hook guard for console inspection.
- Phase 2 intentionally does not change live routing; it adds a stable contract for Phase 3 onward.

### Phase 3: Dictionary Layer

Status: complete.

- [x] Create dictionary families:
  - action words
  - job-search words
  - career-advice words
  - frustration/emotion words
  - web-research words
  - application-flow words
  - CV/profile words
  - salary/visa/company/country/recruiter words
- [x] Extend the existing semantic frame with skill, company/employer, and market-research dictionaries.
- [x] Surface the new dictionary matches in the canonical meaning object.
- [x] Reuse the existing location dictionary as the first location layer.
- [ ] Add ESCO/Tabiya occupation and skill terms as a larger optional dictionary import.
- [ ] Add WordNet synonym expansion for selected families.

Completion criteria:

- [x] "Dubai", "Riyadh", "Saudi Arabia", "UAE" detected as locations.
- [x] "finance", "private equity", "HR", "recruitment", "credit analyst" detected as role/sector terms.
- [x] "tired", "stuck", "frustrated" detected as emotional job-search signals.

Implementation notes:

- The dictionary layer was added inside `chatSignalLexicon` rather than as a separate router.
- `extractMeaningFrame()` now returns `skills`, `companies`, and `markets` alongside existing action/topic/role/location/seniority signals.
- `buildEmilyDecisionFeatures()` and `buildConversationMeaningObject()` now expose those fields for later probability and proximity scoring.
- The first pass is deliberately curated and local to avoid adding a large bundle before the scoring formula is stable.

### Phase 4: Token, Phrase, and Proximity Scoring

Status: complete.

- [x] Implement token scoring.
- [x] Implement phrase scoring.
- [x] Implement proximity scoring between token families.
- [x] Add pair weights:
  - sector + jobs
  - jobs + location
  - recruiter + location
  - salary + role/location
  - tired + job search
  - suggest + job search
  - apply + role/company

Completion criteria:

- [x] "finance jobs in Dubai" scores as job search.
- [x] "best recruiters in Dubai" scores as web research.
- [x] "tired of my job search" scores as career advice.
- [x] "what is Saudi Arabia like" scores as country/life web answer.

Implementation notes:

- `extractMeaningFrame()` now emits `scoring.token_scores`, `scoring.phrase_scores`, and `scoring.proximity_scores`.
- The scoring layer is attached to `buildEmilyDecisionFeatures().semantic.scoring` and `meaning.scores`.
- Scores are advisory metadata at this phase; routing replacement happens later so this can be inspected safely through the debug meaning object first.
- Proximity uses character distance between canonical lexicon hits as the first deterministic model. This can later be upgraded to token-distance from `wink-nlp`.

### Phase 5: BM25 Archetype Matching

- Status: complete.

- [x] Build a small local corpus of intent archetypes.
- Example archetypes:
  - "find finance jobs in Dubai"
  - "best recruiters in Saudi Arabia"
  - "why am I not getting interviews"
  - "when is the best time to apply"
  - "tell me about living in Riyadh"
  - "tailor my CV for this role"
- [x] Add BM25-style score into the meaning/scoring formula.
- [ ] Replace the local browser-safe scorer with `wink-bm25-text-search` if/when the plugin gets a frontend bundling step.

Completion criteria:

- [x] Messy prompts map to closest archetype.
- [x] Raw phrase mismatch no longer breaks routing metadata.

Implementation notes:

- A local BM25-style archetype scorer was added because this WordPress plugin does not currently have a root npm/bundler setup.
- `chatSignalLexicon.intentArchetypes` contains the first corpus of Emily-specific examples.
- `meaning.scores.archetype_scores` now exposes ranked matches with `intent`, `route`, score, normalized score, and matched tokens.
- The top archetype matches contribute advisory route evidence through `meaning.scores.token_scores`.
- This phase still does not replace live routing; that is reserved for the action-router phase after the evidence model is easier to inspect.

### Phase 6: Naive Bayes Intent Classifier

Status: complete.

- [x] Add a Naive Bayes-style intent classifier.
- [x] Train on curated examples for each intent family.
- [x] Load the model at runtime from local in-script examples.
- [x] Combine classifier probability with deterministic scores.
- [ ] Replace the local browser-safe classifier with `wink-naive-bayes-text-classifier` if/when the plugin gets a frontend bundling step.
- [ ] Export a generated trained model JSON once the example corpus becomes large enough to justify a build step.

Completion criteria:

- [x] Intent classifier returns ranked intents.
- [x] Classifier alone does not control action.
- [x] Final decision uses ensemble score metadata.

Implementation notes:

- `chatSignalLexicon.intentClassifierExamples` contains the first curated training set.
- `meaning.scores.classifier_scores` now exposes ranked intent probabilities.
- The classifier contributes conservative advisory evidence into `meaning.scores.token_scores`.
- Live routing still remains unchanged until the router replacement phase.

### Phase 6.5: Dependency-Backed Emily NLP Service

Status: complete for service build; WordPress bridge remains pending.

- [x] Create a separate Railway-ready service under `services/senna-emily-nlp-service`.
- [x] Add dependency-backed NLP packages:
  - `wink-nlp`
  - `wink-eng-lite-web-model`
  - `wink-bm25-text-search`
  - `wink-naive-bayes-text-classifier`
  - `chrono-node`
- [x] Add deterministic fallback logic so the service still answers if a package API changes or fails to load.
- [x] Expose stable endpoints:
  - `GET /health`
  - `POST /meaning`
  - `POST /classify`
  - `POST /rewrite`
- [x] Return the same high-level meaning contract used by the WordPress plugin:
  - `primaryIntent`
  - `confidence`
  - `secondaryIntents`
  - `emotion`
  - `entities`
  - `scores`
  - `action`
  - `rewrittenQueries`
  - `explanation`
- [x] Add Railway deployment files:
  - `Dockerfile`
  - `railway.json`
  - `README.md`
- [x] Add fixture tests for the current critical failures:
  - `i need help to find jobs in dubai`
  - `best recruiters in dubai`
  - `when is the best time to apply for jobs in Dubai`
  - `you know emily im so tired of my job search what do you suggest`
  - `what is saudi arabia like`

Completion criteria:

- [x] The service can run independently on Railway.
- [x] The service produces structured job queries instead of raw filler prompts.
- [x] General/country/timing/recruiter questions route to web-answer style actions instead of selected-role clarification.
- [x] Career-frustration prompts route to career advice with web support.
- [x] WordPress calls this service before falling back to the local browser-safe meaning engine.

Implementation notes:

- This is the stronger implementation path that replaces the earlier "wait for a frontend bundler" blocker.
- The WordPress plugin should keep its local meaning engine as a fallback for latency, offline service failure, or missing service URL.
- Recommended WordPress constant for the next bridge phase:

```php
define('SFFC_EMILY_NLP_ENDPOINT', 'https://your-emily-nlp-service.up.railway.app/meaning');
```

### Phase 7: Query Rewriter

- Status: partially complete in the service; WordPress bridge remains pending.

- [x] Convert service meaning objects into structured queries.
- For job search:
  - [x] extract role/sector/location
  - [x] avoid filler words
  - [ ] preserve negative constraints
- Prefer structured query components over raw text:
  - [x] `role`
  - [x] `sector`
  - [x] `location`
  - [x] `seniority`
  - [ ] `constraints`
- For web answer:
  - [x] rewrite into concise research query
  - [x] include location/sector/context if available
- For career advice:
  - [x] generate support query only when useful in the service.
- [x] Replace the current shallow `buildApplyChatWebSearchQuery(...)` behavior with service-backed meaning-aware rewrite output.

Completion criteria:

- [x] "i need help to find jobs in dubai" becomes internal job query `{ location: "Dubai" }`, not raw text in the service.
- [x] "best recruiters in dubai" becomes web query `best recruitment agencies Dubai` in the service.
- [x] "I'm tired of applying and getting no replies" becomes web query `job search burnout no replies improve application strategy practical steps` in the service.
- [x] WordPress consumes the service rewrite output in live chat.

Implementation notes:

- `SFFC_EMILY_NLP_ENDPOINT` is now localized into the apply-chat article config as `emilyNlpEndpoint`.
- The main composer submit flow calls the service through `buildConversationDecisionWithService(...)`.
- If the service fails, times out, has low confidence, or returns an unsupported action, the existing local browser-safe decision engine remains the fallback.
- Service-backed actions currently override only the highest-impact routes:
  - `jobs_database_search` -> local `show_job_results`
  - `web_answer` -> local `web_search`
  - `career_advice_with_web_support` -> local `web_search`
- Browser debug:

```js
window.sffcDebugEmilyMeaningWithService("when is the best time to apply for jobs in Dubai")
```

### Phase 8: Context and Memory Integration

- Add prior CV/profile signals into scoring.
- Add current selected role into scoring, but make it a context bonus, not a hard guard.
- Add recent user messages.
- Add active flow state.

Completion criteria:

- General questions are not hijacked by selected-role context.
- If user has uploaded CV, career advice can reference their profile.
- If user asks "what about Riyadh?", memory can infer the last discussed job/sector where appropriate.

### Phase 9: Action Router Replacement

- Replace brittle trigger ordering with meaning-object routing.
- Job database is called only for job-search actions.
- Web answer service is called for market/country/recruiter/salary/timing/general unknown actions.
- Clarification is used only when confidence is low or action conflict is real.
- Move selected-role/application context into route scoring unless the message contains explicit apply/submit/tailor/status language.
- Keep legacy step handlers as safety fallback until the prompt suite proves the decision engine can claim the turn.

Completion criteria:

- "when is the best time to apply for jobs in Dubai" triggers web answer.
- "find jobs in Riyadh" triggers job database.
- "what is Saudi Arabia like" triggers web answer.
- "we are talking through a career question" resolves current clarification without looping.

### Phase 10: Response Composition

- Use action type to choose Emily response style.
- Career advice:
  - acknowledge
  - diagnose
  - next step
- Web answer:
  - direct answer
  - concise bullets
  - source card
- Job search:
  - search summary
  - result cards
- Clarification:
  - only one direct question.

Completion criteria:

- Emily feels conversational, not like a database.
- Web answers are not source dumps.
- Job searches do not include filler query words.

### Phase 11: Evaluation Harness

- Build a fixed prompt suite.
- Include:
  - direct job searches
  - indirect job searches
  - career frustration
  - recruiter questions
  - salary questions
  - country/life questions
  - selected-role interruptions
  - multilingual/typo cases
- Assert expected:
  - intent
  - action
  - rewritten query
  - no clarification loops

Completion criteria:

- Test suite blocks regressions.
- Each phase updates expected behavior.

### Phase 12: Observability and Debug Console

- Add a dev-only console diagnostic:

```js
window.sffcDebugEmilyMeaning("I'm tired of my job search")
```

Output:

```json
{
  "primaryIntent": "career_advice",
  "scores": {},
  "entities": {},
  "rewrittenQueries": {},
  "action": {}
}
```

Completion criteria:

- We can diagnose routing failures without guessing.
- User can paste console output when Emily behaves incorrectly.

## Success Metrics

### Intent Accuracy

- At least 90% correct action routing on the prompt suite.
- No raw full-sentence job searches for common help phrases.
- Selected-role guard does not hijack general questions.

### Query Quality

- Job queries contain role/sector/location, not filler words.
- Web queries are concise and research-oriented.
- Recruiter/country/salary/timing questions go to web answer service.

### Response Quality

- Emily answers direct questions directly.
- Emily only asks clarification when genuinely needed.
- Emily uses CV context when available.
- Emily renders source-backed answers cleanly.

## Risks

### Overclassification

The system may become too confident and route ambiguous prompts incorrectly.

Mitigation:

- Keep confidence thresholds.
- Use clarification only when top two actions are close.

### Dictionary Bloat

Large dictionaries may slow the frontend.

Mitigation:

- Keep lightweight dictionaries in WordPress.
- Move larger ESCO/WordNet/location lookups to a service if needed.
- Cache compressed subsets.

### False Web Search

Career-support messages may overuse web search.

Mitigation:

- Web search is optional support for career advice, not always required.
- Use cached answers where possible.

### Context Hijacking

Selected role or previous job search may hijack unrelated questions.

Mitigation:

- Treat context as a score modifier, not a hard route.
- Require explicit application/apply intent to continue application flow.

## Initial Prompt Suite

```text
find jobs in dubai
i need help to find jobs in dubai
finance jobs in riyadh
best recruiters in dubai
best recruitment agencies in saudi arabia
when is the best time to apply for jobs in dubai
what is saudi arabia like
what is life like in riyadh for expats
i'm tired of my job search what do you suggest
i keep applying and getting no replies
why am i not getting interviews
should i use recruiters or apply directly
what salary should i expect for HR manager in Dubai
tell me about Mubadala
is this role good for me
tailor my CV
apply to this role
we are talking through a career question
```

## Recommended Implementation Order

1. Phase 1: audit current routing.
2. Phase 2: meaning object contract.
3. Phase 3: lightweight dictionaries.
4. Phase 4: proximity formula.
5. Phase 7: query rewriting.
6. Phase 9: action router.
7. Phase 11: test harness.
8. Phase 5/6: BM25 and Naive Bayes.
9. Phase 8: memory/CV context.
10. Phase 10/12: response polish and diagnostics.

This order gives immediate practical improvements before adding heavier NLP dependencies.
