# Emily Decision Intelligence Upgrade Plan

## Objective

Upgrade Emily's scoring brain while preserving the existing apply-chat workflow system.

The current apply-chat architecture already has useful pieces: prompt-state handling, selected-role context, CV availability checks, memory snapshots, web search routing, CV/job matching, application execution, and safety/payment/support guards. The weakness is that many decisions are still driven by hand-weighted rules and scattered additive scores. This plan upgrades those rules into a calibrated decision system without rebuilding the product from scratch.

The target is for Emily to decide, with measurable confidence:

- What the user is trying to do.
- Whether the request should interrupt, continue, change, or pause the current workflow.
- Whether to answer directly, search the web, search internal jobs, refine filters, compare a role, apply, review the CV, or ask a clarifying question.
- Which jobs genuinely fit the user's CV, seniority, experience, preferences, and recent context.

## Existing Foundation

The current system already includes:

- `buildConversationDecision()` as the main decision entry point.
- Context snapshots for selected role, active task, job search state, memory, and user tier.
- Rule-based intent detection and belief-state handling.
- Response expectation scoring for question/request/query/statement/answer.
- Archetype scoring for common career-advice patterns.
- Deterministic workflow guards for prompts, applications, CV state, and selected roles.
- CV parsing via LiteParse and secondary parsers.
- CV/job matching, seniority scoring, interview likelihood, salary ranking, and result ranking.
- Search memory and user preference refinement.
- Web search routing through the SearXNG service.

The upgrade should keep these systems and make the scoring layer more mathematical, measurable, and self-improving.

## Design Principle

Do not replace deterministic workflow logic with a generic chatbot.

Instead:

1. Keep hard guards for workflow-critical actions.
2. Convert intent and route selection into a probabilistic model.
3. Convert job matching into a feature-based ranking model.
4. Log decision outcomes so the model can improve from real user behavior.
5. Add calibration so scores mean something operational.

## Phase 1 - Decision Inventory And Route Taxonomy [x]

Status: implemented as the first non-behavioral foundation pass.

Create a single canonical route taxonomy for Emily.

Routes should include:

- `answer_career_question`
- `web_search`
- `job_search`
- `search_refinement`
- `search_filter_status`
- `search_filter_reset`
- `apply_action`
- `cv_role_comparison`
- `cv_review`
- `cv_tailoring`
- `application_status`
- `role_reference`
- `role_question`
- `company_research`
- `recruiter_networking`
- `salary_compensation`
- `interview_prep`
- `application_material`
- `support_payment`
- `account_issue`
- `human_takeover`
- `clarify`
- `defer_to_prompt_handler`

For each route define:

- Required context.
- Forbidden context.
- Whether it can interrupt an active workflow.
- Whether it can execute without a CV.
- Whether it can trigger external search.
- Whether it can trigger application execution.
- Minimum confidence required.
- Clarifying question fallback.

Deliverables:

- Route taxonomy JSON or JS object.
- Route definitions documented in code comments.
- Test matrix covering every route.

Implemented:

- Added `emilyDecisionRouteTaxonomy` in `crm-apply-chat-article.js`.
- Added lookup helpers for route-by-intent, route-by-action, and route definition snapshots.
- Added route metadata to the decision output and decision logging payload.
- Exposed taxonomy inspection through the existing apply-chat test hook.
- Added `scripts/test-emily-decision-route-taxonomy.js` to validate route metadata and core intent/action coverage.

## Phase 2 - Feature Extraction Layer [x]

Status: implemented as a non-routing observability layer.

Build a unified feature object for every user message.

Feature groups:

- Text signals:
  - normalized text
  - token count
  - question markers
  - imperative markers
  - search markers
  - apply markers
  - support/payment markers
  - CV markers
  - role/company/location markers

- Context signals:
  - active prompt state
  - active workflow
  - selected role exists
  - visible results exist
  - CV exists
  - logged-in or guest
  - paying or free
  - last route
  - unresolved memory goals

- Semantic signals:
  - extracted topics
  - extracted actions
  - extracted roles
  - extracted locations
  - extracted seniority
  - extracted entities

- Risk signals:
  - low confidence
  - multiple strong candidate routes
  - action would submit/apply
  - route conflicts with active prompt
  - user correction/frustration
  - external/current info required

Deliverables:

- `buildEmilyDecisionFeatures(message, context)`.
- Debug view for feature output.
- Snapshot tests for representative messages.

Implemented:

- Added `buildEmilyDecisionFeatures()` in `crm-apply-chat-article.js`.
- Added text, context, semantic, route-hint, and risk feature groups for each message.
- Wired the feature snapshot into `buildConversationDecision()` without changing route behavior yet.
- Added feature flags, route-hint flags, and risk flags to decision logging.
- Exposed `getEmilyDecisionFeatures()` through the apply-chat test hook for browser-console inspection.
- Added `scripts/test-emily-decision-features.js` to guard the feature schema and wiring.

## Phase 3 - Local Probabilistic Intent Classifier [x]

Status: implemented as a local route-probability evidence layer.

Add a local classifier trained on Emily route examples.

Recommended starting point:

- `nlp.js` Bayes NLU, because it is lightweight, local, explainable, and fits route classification.

Why:

- We need fast intent probabilities, not a general chatbot.
- It can run without paid APIs.
- It gives ranked classifications.
- It can be trained from our own examples.

Training data structure:

```json
{
  "route": "web_search",
  "examples": [
    "best recruitment agencies in Dubai",
    "who are the top headhunters in Riyadh",
    "what are the latest hiring trends in private equity"
  ]
}
```

Classifier output:

```json
{
  "route": "web_search",
  "probability": 0.84,
  "runner_up": "job_search",
  "runner_up_probability": 0.31,
  "margin": 0.53
}
```

Deliverables:

- Training examples for every route.
- Classifier wrapper.
- Offline test script.
- Debug console method to inspect route probabilities.

Implemented:

- Added `emilyDecisionRouteTrainingExamples` covering every route in the taxonomy.
- Added a local multinomial Naive Bayes style route classifier with Laplace smoothing.
- Added Phase 2 feature boosts so hard-won signals like web-search, job-search, apply, CV, and account/support markers influence route probabilities.
- Wired the route classifier into `classifyLocalProbabilisticApplyChatIntent()` as weighted evidence using `route_bayes:*` signals.
- Added `routeProbability` to decision intent output and decision logging.
- Exposed browser-console hooks:
  - `getEmilyDecisionRouteTraining()`
  - `classifyEmilyDecisionRouteProbability(message, context)`
- Added `scripts/test-emily-route-probability-classifier.js` for training coverage and representative classification checks.

## Phase 4 - Semantic Routing Layer [x]

Status: implemented as a local semantic-router style similarity layer.

Add a semantic similarity layer for meaning-based routing.

Recommended options:

- Python service using `sentence-transformers`.
- Or Node/local embeddings using a semantic-router style implementation.

This layer should not replace rules or Bayes. It should answer:

> Which route examples is this message semantically closest to?

Example:

User says:

`who usually handles recruitment for senior finance roles in Dubai`

Regex may not detect it cleanly, but semantic routing should place it near:

- `web_search`
- `recruiter_networking`
- `company_research`

Deliverables:

- Route example embeddings.
- Top-k semantic route scorer.
- Similarity threshold per route.
- Caching for route embeddings.
- Fallback if semantic service is unavailable.

Implemented:

- Added local semantic token expansion for route meaning rather than simple literal keyword matching.
- Added route example vectors with IDF weighting and cosine similarity.
- Added `classifyEmilyDecisionRouteSemanticSimilarity(message, context)` to return top-k semantic route candidates.
- Wired semantic route evidence into `classifyLocalProbabilisticApplyChatIntent()` via `route_semantic:*` signals.
- Added semantic route output to decision intent and decision logging.
- Exposed browser-console hook `classifyEmilyDecisionRouteSemanticSimilarity(message, context)`.
- Added `scripts/test-emily-route-semantic-router.js` for meaning-based routing cases such as recruiter/recruitment queries, company research, salary, interview prep, and account issues.

## Phase 5 - Ensemble Route Scoring [x]

Status: implemented as a weighted route-evidence layer with hard guards preserved.

Combine deterministic, probabilistic, semantic, context, and memory scores into one route score.

Initial formula:

```text
route_score =
  0.30 * deterministic_signal
+ 0.25 * bayes_probability
+ 0.20 * semantic_similarity
+ 0.10 * active_context_fit
+ 0.08 * memory_continuity
+ 0.07 * workflow_safety
- penalties
```

Penalties:

- Active prompt conflict.
- Application execution risk.
- Missing CV when route needs CV.
- Selected role missing when route needs role.
- Web search requested but query is private/sensitive.
- User correction says Emily misunderstood.
- Top two routes too close.

Decision thresholds:

```text
if hard_guard applies:
  use guard
else if top_score >= 0.78 and margin >= 0.18:
  execute top route
else if top_score >= 0.62 and safe route:
  execute but phrase with uncertainty
else:
  ask focused clarification
```

Deliverables:

- `scoreEmilyRoutes(features, context)`.
- Top 3 routes included in decision log.
- Margin-based clarification.
- Kill switch to fall back to current rule system.

Implemented:

- Added `scoreEmilyRoutes(features, context, routeProbability, routeSemantic)`.
- Combined deterministic route hints, Bayes probabilities, semantic similarity, active context fit, memory continuity, workflow safety, and penalties using the planned weights.
- Added penalties for active prompt conflict, applying without a role/CV, CV work without a CV, role-bound work without a role, and web-vs-job-search ambiguity.
- Wired ensemble route evidence into `classifyLocalProbabilisticApplyChatIntent()` via `route_ensemble:*` signals.
- Added `routeEnsemble` to decision intent output and decision logging, including top route, runner-up, margin, confidence band, and top candidates.
- Exposed browser-console hook `scoreEmilyRoutes(message, context)`.
- Added `scripts/test-emily-route-ensemble-scorer.js` to guard the formula, safety penalties, logging, and hook wiring.

## Phase 6 - Probability Calibration [x]

Status: implemented as lightweight route-score calibration and offline evaluation.

Raw scores are not reliable probabilities. Add calibration.

Use calibration concepts from scikit-learn:

- Holdout examples.
- Reliability checks.
- Brier score / log loss.
- Calibration mapping from raw score to probability.

Initial lightweight approach:

- Bucket scores into bands.
- Compare predictions against expected route labels in test data.
- Adjust thresholds and weights.

Later service-based approach:

- Python `scikit-learn` classifier with `CalibratedClassifierCV`.
- Export probability outputs to WordPress.

Deliverables:

- Calibration evaluation script.
- Confusion matrix by route.
- Confidence buckets:
  - `very_high`
  - `high`
  - `medium`
  - `ambiguous`
  - `unsafe`

Implemented:

- Added `calibrateEmilyRouteScore(score, margin, components)` to convert ensemble scores into calibrated probability metadata.
- Added `getEmilyDecisionCalibrationBand()` with the required buckets: `very_high`, `high`, `medium`, `ambiguous`, and `unsafe`.
- Added `calibratedProbability`, `calibrationBand`, and `shouldClarify` to `routeEnsemble` output and decision logging.
- Exposed browser-console hook `calibrateEmilyRouteScore(score, margin, components)`.
- Added `scripts/evaluate-emily-route-calibration.js` to report accuracy, Brier score, log loss, confidence buckets, misses, and a confusion matrix by expected route.
- Added `scripts/test-emily-route-calibration.js` to guard runtime calibration helpers and the evaluator.

## Phase 7 - Job Description Feature Extraction [x]

Create a structured job profile for every `jobs` and `sffc-crm-posts` item.

Status: [x] implemented.

Extract:

- Role title.
- Role family.
- Seniority.
- Required years.
- Preferred years.
- Required skills.
- Preferred skills.
- Industry/domain.
- Management responsibility.
- Location / remote / hybrid.
- Visa/relocation signals.
- Application route.
- Freshness.
- Compensation if available.
- Deal-breakers.

Example:

```json
{
  "title": "HR Operations Manager",
  "family": "human_resources",
  "seniority": "manager",
  "required_years_min": 5,
  "required_years_preferred": 8,
  "skills_required": ["recruitment", "payroll", "HRIS"],
  "location": "Dubai",
  "route": "workday",
  "confidence": 0.82
}
```

Deliverables:

- Added `parseJobDescriptionSignals()` in `assets/js/crm/crm-apply-chat-article.js`.
- Added local extractors for role families, seniority, required/preferred years, skills, industries, work setup, visa/relocation, management responsibility, compensation, and deal-breakers.
- Added `jobDescriptionSignalsCacheKey` / `jobDescriptionSignalsCacheValue` and reset handling.
- Wired parsed signals into `buildCandidateRoleProfile()` so CV/job matching receives richer role profile data.
- Exposed `parseJobDescriptionSignals` through the existing apply-chat test hook.
- Added `scripts/test-job-description-signals.js`.

## Phase 8 - CV Profile Feature Extraction [x]

Use the upgraded LiteParse output to produce a candidate profile model.

Status: [x] implemented.

Extract:

- Current title.
- Current employer.
- Role family.
- Seniority.
- Total non-overlapping experience months.
- Recent relevant experience months.
- Management signals.
- Budget/team/ownership signals.
- Core skills.
- Domain skills.
- Locations.
- Languages.
- Qualifications.
- Blocked/irrelevant role families.
- Confidence per field.

Example:

```json
{
  "current_title": "HR Recruitment Manager",
  "role_family": "human_resources",
  "seniority": "manager",
  "total_experience_months": 122,
  "recent_relevant_months": 84,
  "core_skills": ["recruitment", "payroll", "HR operations"],
  "blocked_families": ["credit_analysis", "investment_banking"],
  "confidence": 0.86
}
```

Deliverables:

- Added `buildCandidateProfileSignals()` in `assets/js/crm/crm-apply-chat-article.js`.
- Added candidate profile cache reset handling.
- Added structured fields for current title, current employer, role family, seniority, non-overlapping experience months, current role months, recent relevant months, relevant months by family, management signals, core skills, domain skills, tools, locations, languages, qualifications, recommended queries, blocked queries, blocked families, and confidence scores.
- Exposed `buildCandidateProfileSignals` through the shared runtime and apply-chat test hook.
- Added `scripts/test-candidate-profile-signals.js`.
- Known sample CV runtime validation remains part of Phase 14 golden fixtures, because the browser state owns uploaded CV data.

## Phase 9 - Job Fit Scoring Model [x]

Replace simple additive matching with a structured fit model.

Status: [x] implemented.

Initial formula:

```text
fit_score =
  0.22 * role_family_match
+ 0.18 * title_similarity
+ 0.16 * required_skill_match
+ 0.14 * seniority_match
+ 0.12 * years_experience_match
+ 0.08 * domain_match
+ 0.05 * location_match
+ 0.03 * freshness
+ 0.02 * application_route_quality
- penalties
```

Penalties:

- User under required experience by 3+ years.
- Role family mismatch.
- Seniority mismatch.
- Specialist mismatch.
- CV evidence missing for core requirement.
- Overqualified by a large margin when the role is junior.
- Location/visa mismatch.

Output:

```json
{
  "fit_score": 81,
  "interview_probability_band": "strong",
  "reason_codes": [
    "same_role_family",
    "seniority_aligned",
    "required_years_met",
    "strong_skill_overlap"
  ],
  "risks": [
    "industry_not_explicit"
  ]
}
```

Deliverables:

- Added `buildStructuredJobFitScore()` in `assets/js/crm/crm-apply-chat-article.js`.
- Added weighted fit components for role family, title similarity, required skill match, seniority, years, domain, location, freshness, and application route quality.
- Added reason codes, risks, penalties, component scores, and interview probability bands.
- Blended structured fit score into `buildCvRoleMatch()` so role ranking and cards use the stronger scoring model.
- Added visible fit reason/risk summaries to the existing match narrative blocks.
- Added blocked-family penalties so HR profiles are strongly discouraged from finance roles unless the user explicitly asks for that family.
- Added `scripts/test-structured-job-fit-score.js`.

## Phase 10 - Learning-To-Rank Upgrade [x]

Once enough outcome data exists, train a ranking model.

Status: [x] implemented as local weighted LTR v1; future outcome-trained LightGBM/XGBoost can replace the default weights.

Recommended models:

- LightGBM LambdaRank.
- XGBoost ranking objective.

Training labels can come from:

- User clicked.
- User saved.
- User asked to review fit.
- User asked to tailor CV.
- User applied.
- User dismissed.
- User said too junior / too senior / wrong sector.
- Application succeeded / failed / blocked.

Feature vector:

- Current fit score components.
- CV profile features.
- Job profile features.
- Query features.
- User preference memory.
- Freshness.
- Route quality.
- Historical engagement.

Deliverables:

- Added `buildEmilyLearningToRankFeatureVector()`.
- Added `scoreEmilyLearningToRankFeatures()`.
- Added `buildEmilyLearningToRankScore()` using `local_weighted_ltr_v1`.
- Integrated LTR score into primary and supplemental job ranking.
- Exposed `buildEmilyLearningToRankScore` through the apply-chat test hook.
- Added `scripts/export-emily-ltr-dataset.js` as the offline dataset schema/export scaffold.
- Added `scripts/test-emily-learning-to-rank.js`.
- Future training metrics:
  - NDCG@5
  - NDCG@10
  - precision@5
  - user-dismissal rate
- Shadow ranking before production rollout.

## Phase 11 - Outcome Logging And Feedback Loop [x]

Every material Emily decision should log:

```json
{
  "message": "...",
  "features": {},
  "top_routes": [
    {"route": "web_search", "score": 0.86},
    {"route": "job_search", "score": 0.42}
  ],
  "chosen_route": "web_search",
  "confidence": "high",
  "workflow_state": "search",
  "user_tier": "guest",
  "outcome": "continued"
}
```

For job results, log:

- Impressions.
- Clicks.
- Saves.
- Fit review.
- Tailor CV.
- Apply.
- Dismiss.
- User feedback.

Deliverables:

- Added privacy-conscious event schema through `logEmilyOutcomeEvent()`.
- Added decision outcome logging through `logEmilyDecisionOutcome()`.
- Added job outcome logging through `logEmilyJobOutcomeEvent()`.
- Added deduped job impression logging for rendered results.
- Added interaction events for fit review, save, dismiss, tailor CV, original CV, apply attempt, and search-preference feedback.
- Exposed local/session debugging through `window.__sffcEmilyOutcomeEventLog` and `window.__sffcEmilyOutcomeEventLatest`.
- Added `scripts/export-emily-outcome-events.js` as the offline outcome schema/export scaffold.
- Added `scripts/test-emily-outcome-logging.js`.
- Optional server-side logging remains a later deployment choice; the browser schema is stable enough to send to WordPress or a service endpoint when needed.

## Phase 12 - Cleanlab Data Quality Pass [x]

Use cleanlab after we collect enough labeled examples.

Purpose:

- Find mislabeled route examples.
- Find ambiguous training messages.
- Find examples where users corrected Emily.
- Find noisy outcome labels.
- Improve route and ranking datasets without changing the model first.

Deliverables:

- Added `scripts/audit-emily-cleanlab-data-quality.js`.
- The audit accepts exported Emily outcome events and produces:
  - Cleanlab-ready rows.
  - Suspicious label/route examples.
  - Noisy outcome-label examples.
  - A prioritized human review queue.
  - Optional markdown report output.
- Added practical heuristics for:
  - Low-confidence decisions.
  - Ambiguous route margins.
  - Web-search vs internal-job-search mislabels.
  - Positive engagement on low-fit jobs.
  - Dismissals on high-fit jobs.
  - Incomplete job impressions.
- Added `scripts/test-emily-cleanlab-data-quality.js`.
- Full Cleanlab library scoring should run after enough labeled browser outcome events have been collected; the exported rows are structured for that handoff.

## Phase 13 - Runtime Safety And Fallbacks [x]

Keep hard constraints:

- Do not auto-submit applications based only on probabilistic classification.
- Do not claim CV fit without CV evidence.
- Do not claim live/current facts without web search or internal data.
- Do not override active prompt answers unless confidence is high and route is safe.
- Do not show monetization prompts to paying users.

Fallbacks:

- If probabilistic scorer fails, use current rule engine.
- If semantic service fails, use Bayes + deterministic score.
- If Bayes model fails, use current rule engine.
- If route confidence is low, ask a focused clarification.

Deliverables:

- Added runtime safety config to `getEmilyDecisionEngineConfig()`.
- Added kill switches:
  - `emilyDisableProbabilisticRouting`
  - `emilyDisableSemanticRouting`
  - `emilyDisableRouteEnsemble`
  - `emilyDisableWebSearch`
  - `emilyDisableApplicationExecution`
  - `emilyRuntimeSafetyEnabled`
  - `emilyStrictPromptProtection`
- Added safe classifier wrappers so probability, semantic, and ensemble failures return clarify-safe route signals instead of breaking chat.
- Added hard validation blocks for:
  - Probabilistic application execution.
  - Application execution without CV.
  - Application execution without role context.
  - CV-fit comparison without CV evidence.
  - Web search when disabled.
  - Application execution when disabled.
  - Active-prompt overrides below the strict confidence threshold.
  - Ambiguous/unsafe ensemble routes from probabilistic classification.
- Added runtime telemetry through `recordEmilyRuntimeSafetyTelemetry()`.
- Exposed browser debug hooks:
  - `debugEmilyDecisionRoute()`
  - `getEmilyDecisionRuntimeSafetyConfig()`
  - `getEmilyRuntimeSafetyTelemetryLog()`
- Added `scripts/test-emily-runtime-safety.js`.

## Phase 14 - Testing Strategy [x]

Core tests:

- Route classifier tests.
- Prompt-state conflict tests.
- Web search vs internal job search tests.
- Apply action safety tests.
- Search refinement tests.
- CV/profile extraction tests.
- Job description extraction tests.
- Fit score tests.
- Ranking tests.
- Memory continuity tests.

Golden examples:

- `best recruitment agencies in Dubai` -> web search.
- `recruiter jobs in Dubai` -> internal job search.
- `apply to this role` with selected role -> apply action.
- `is this too senior for me` -> CV-role comparison.
- `these are too junior` -> search refinement.
- `show me only Dubai` -> search refinement.
- `what did I apply to` -> application status.
- `who should I contact at this company` -> recruiter networking or company research depending on selected role.

Deliverables:

- Added `scripts/test-emily-decision-intelligence-suite.js` as the end-to-end Phase 1-14 suite runner.
- The suite runs JS syntax validation plus the route taxonomy, feature extraction, probabilistic routing, semantic routing, ensemble scoring, calibration, job-description signals, candidate-profile signals, structured fit score, learning-to-rank, outcome logging, memory continuity, Cleanlab audit, runtime safety, and legacy decision-engine checks.
- The suite writes a JSON report with pass/fail status, command output, calibration metrics, and golden-example coverage.
- Added `scripts/test-emily-memory-continuity.js` so Phase 14 explicitly checks that memory snapshots feed feature extraction and route scoring.
- Current validation passed `16/16` with report output at `/tmp/emily-decision-intelligence-suite-final-audit.json`.

## Recommended Open-Source Components

Use as building blocks, not as product replacements:

- `nlp.js`: local Bayes intent classification.
- `semantic-router` or Node semantic router equivalent: semantic route similarity.
- `sentence-transformers`: local embedding service if we use Python.
- `scikit-learn`: calibrated classifier and probability evaluation.
- `LightGBM` or `XGBoost`: learning-to-rank once outcome data exists.
- `cleanlab`: training-data and label-quality improvement.

## Implementation Order

Recommended order:

1. Route taxonomy.
2. Unified feature extraction.
3. Route training examples.
4. Bayes classifier.
5. Ensemble route scoring.
6. Debug console and tests.
7. Job description profiles.
8. Candidate profile features.
9. Structured job fit model.
10. Semantic routing service.
11. Calibration.
12. Logging and feedback.
13. Learning-to-rank.
14. Cleanlab quality loop.

## Definition Of Done

Emily is upgraded when:

- Every material decision has a top route, score, margin, and reason codes.
- Ambiguous messages produce clarifying questions instead of wrong actions.
- Web-search questions do not become internal job searches.
- Internal job-search queries do not become web searches.
- CV-based matching blocks obvious role-family mismatches.
- Job recommendations account for years of experience and seniority.
- User feedback changes future results.
- Tests show fewer route regressions than the current rule-only system.
- The old workflow still works: selected role, CV upload, tailoring, fit review, and application execution.
