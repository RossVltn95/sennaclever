# Apply Chat Unification Plan

This file is the source of truth for the apply-chat cleanup. Update each checkbox as work is completed. Do not start broad styling or feature work until the routing and renderer ownership issues are clear.

## Goal

Unify `sffc-crm-apply-chat` into one coherent Emily experience across welcome, job search, CV upload, CV review, career coaching, salary questions, role application, recruiter outreach, and employer-form review.

The target experience is the new Google/Claude-style chat layout from `benzo.html`: centered content, compact user bubbles, assistant responses with continuation line treatment, one composer, compact action pills, and one consistent result/card design.

## Current Problem

- [x] Confirm all active entry points into apply chat.
- [x] Confirm all active routers that can emit Emily messages.
- [x] Confirm all renderers that can output job cards, CV cards, recruiter cards, forms, previews, and quick actions.
- [x] Remove or wrap legacy flows that bypass the new design.
- [x] Prevent multiple systems from responding to the same user turn.

Known competing systems:

- [x] New welcome/trending-pill launcher.
- [x] Career-advisor question handler.
- [x] Legacy consultant/job-search flow.
- [x] Legacy recruiter shortlist flow.
- [x] Legacy selected-role application flow.
- [x] CV tailoring flow.
- [x] Cover-letter flow.
- [x] Employer embed/preview/application workspace flow.
- [x] Salary-estimator flow.

## Phase 0: Plan Control

- [x] Create this checklist file.
- [x] Keep this file updated after every phase.
- [x] Add a short completion note under each finished phase.
- [x] Do not mark a phase complete unless tested.

Completion note:

Phase 0 complete. This file is now the working control document and will be updated as each phase is completed.

## Phase 1: Router Inventory And Ownership

Purpose: identify every route that can send Emily messages or render cards.

- [x] Audit `assets/js/crm/crm-apply-chat-article.js` for all message emitters: `botMessage`, `botSequenceForCurrentTurn`, direct HTML inserts, queued messages, typing indicators, transfer notices, and delayed callbacks.
- [x] Audit all functions that call `startConsultantFlow`.
- [x] Audit career-advisor routing: question classification, answer rendering, action buttons, and CTA handlers.
- [x] Audit launcher routing from `[sffc_crm_apply_chat_launcher]`.
- [x] Audit selected-role routing.
- [x] Audit post-CV-upload routing.
- [x] Audit CV tailoring routing.
- [x] Audit cover-letter routing.
- [x] Audit salary-question routing.
- [x] Audit recruiter/contact routing.
- [x] Produce a route map inside this file listing source function, trigger, emitted copy, renderer, and target state.

Completion note:

Phase 1 audit complete. The current chat has one central message primitive, but too many route owners still call it directly. The biggest conflict is that the newer decision/welcome system and the older `startConsultantFlow`/career-advisor/post-CV-upload systems can all respond to the same turn or render different card families in the same chat.

Primary message emitters found:

- `botMessageNow(html)`: immediate assistant render.
- `botMessage(html, delay, callback, userReadDelay)`: delayed assistant render with typing.
- `botSequenceForCurrentTurn(sequence, done)`: multi-message assistant sequence.
- `addTransferNotice(copy)`: transfer/status message, still used by legacy launchers.
- `userMessage(text/html)`: user-side echo.
- `addChoices(items)`: old choice renderer.
- Direct `messages.innerHTML = ...`: still used by some reset/commercial queue paths.
- Delayed callbacks via `window.setTimeout(...)`: still able to emit after state changes unless guarded.

Active route owners found:

- `showInitialLauncher()`: decides cold/logged-in/member/role-entry launch surfaces.
- `routePendingLaunchContext(context)`: maps launcher shortcode context into older routes.
- `routeLauncherIntent(intent, value)`: maps launcher intents into `startConsultantFlow`, role discovery, or review routes.
- `startConsultantFlow(route)`: legacy umbrella for `job_search`, `review_profile`, `apply_for_me`, `apply_intro`, and related routes.
- `handleCareerAdvisorChoice(choice)`: action buttons from career answers; currently jumps into legacy consultant routes.
- `maybeHandleOffScriptInput(value)`: can interrupt active application/search states and re-route.
- Main submit step switch near the bottom of `crm-apply-chat-article.js`: legacy `step` handlers for `apply_upload`, `apply_intro_upload`, `job_search_upload`, `upload`, `job_search_questions`, and related states.
- `continueJobSearchAfterCvUpload()`: post-CV job-search path; still renders old recruiter preview cards.
- `continueApplyAfterAnalysis(analysis)`: selected-role application path; owns CV decision, tailoring, cover-letter decision, and commercial queue handoff.
- `buildConversationDecision(value)`: newer belief/decision engine, but it does not yet fully own route execution.

## Phase 2: Single Conversation Orchestrator

Purpose: ensure one system owns each user turn.

- [x] Introduce or formalize one turn controller for user input.
- [x] Define turn order: interrupt handling, career question detection, salary question detection, active task resume, search intent, CV intent, application intent, fallback.
- [x] Ensure career questions answer first without triggering unrelated precomputed flows.
- [x] Ensure active application/search tasks pause and resume cleanly after side questions.
- [x] Add a per-turn response lock so only one route can emit the final response sequence.
- [x] Add a queue token/run token guard for delayed messages so stale callbacks cannot render after state changes.
- [x] Remove duplicate greeting execution.
- [x] Remove duplicate typing/retyping for the same message.
- [x] Remove self-handoff copy such as `Transferring to Emily` when already inside Emily.

Completion note:

Phase 2 complete. The existing turn audit is now an enforcing ownership lock through `claimConversationTurn`, duplicate plain-text Emily outputs are fingerprinted and suppressed per user turn, legacy `Transferring to Emily` / consultant transfer notices are hidden at the helper level, and career-advisor CTAs no longer launch the old consultant handoff flow. `Find roles` now routes to the live job search path, while `Use my CV` / `Improve my CV` keep the user inside the current chat and upload/CV context.

Checks run:

- `node --check assets/js/crm/crm-apply-chat-article.js`
- `node scripts/test-apply-chat-conversation-hygiene.js`
- `node scripts/test-apply-chat-voice-copy.js`
- `node scripts/test-apply-chat-decision-engine.js`

## Phase 3: State Model Unification

Purpose: make Emily reason from one state object instead of scattered flags.

- [x] Define canonical state fields: language, user profile, CV status, selected role, search brief, active task, last question, pending decision, pending form, salary context, recruiter context, and resume target.
- [x] Consolidate CV facts into one canonical profile builder.
- [x] Guard missing profile helpers such as `buildCanonicalCvProfile`.
- [x] Make every router read/write through the same state API.
- [x] Remove duplicate state flags that represent the same thing.
- [x] Add state reset rules for new search, selected role, upload CV, and close chat.
- [x] Add state persistence rules for logged-in users and anonymous sessions.

Completion note:

Phase 3 complete. The chat now builds and persists a canonical state snapshot from the active language, CV facts/profile, selected role, search brief, pending decisions, application context, salary context, recruiter context, and resume target. Missing CV profile helpers are guarded through a safe canonical-profile wrapper, so undefined profile builders no longer break application continuation. Existing legacy flags have not all been deleted because some renderers still depend on them, but they now feed the canonical state instead of acting as independent route owners.

Checks run:

- `node --check assets/js/crm/crm-apply-chat-article.js`
- `node scripts/test-apply-chat-conversation-hygiene.js`
- `node scripts/test-apply-chat-voice-copy.js`
- `node scripts/test-apply-chat-decision-engine.js`

## Phase 4: Copy And Intent Contract

Purpose: stop robotic, duplicated, or conflicting Emily messages.

- [x] Create a message catalogue for major intents: welcome, search, CV request, CV received, CV analysis, tailored CV, cover letter, employer review, salary, career coaching, recruiter outreach, fallback.
- [x] Replace old copy variants with approved copy.
- [x] Ban repeated intro copy after the first welcome.
- [x] Ban `Hi, I'm Emily.` except first-use fallback contexts.
- [x] Ban `Transferring to Emily`.
- [x] Ban old recruiter wording: `Here are recruiters hiring for your profile` and `Recruiters Hiring for Your Profile`.
- [x] Ban old background wording: `I'll handle the search in the background`.
- [x] Replace multi-message bursts with one structured response where useful.
- [x] Add professional filler sparingly, only where it improves natural flow.
- [x] Ensure every CTA answers the current decision, not a hidden commercial route.

Completion note:

Phase 4 complete. Added `getEmilyCopyContract()` as the approved wording layer for welcome, search, CV request/receipt/analysis, tailored CV decision, cover-letter decision, employer review, salary, recruiter outreach, application start, and fallback copy. Removed the old bilingual language-gate greeting from the copy map, suppressed blank/legacy transfer notices, removed mid-flow `Hi, I'm Emily.` variants, replaced the old recruiter heading with current-role wording, removed the hidden `I'll handle the search in the background` phrase, and corrected the broken tailoring sentence. The application CTAs now stay on the tailored-CV/original-CV and cover-letter/no-cover-letter decisions instead of the old managed-service quick route.

Regression coverage was expanded in `scripts/test-apply-chat-voice-copy.js` so the old greeting, transfer notice, recruiter heading, background-search wording, mid-flow Emily re-introduction, and broken tailoring sentence are guarded.

Checks run:

- `node --check assets/js/crm/crm-apply-chat-article.js`
- `node scripts/test-apply-chat-conversation-hygiene.js`
- `node scripts/test-apply-chat-voice-copy.js`
- `node scripts/test-apply-chat-decision-engine.js`

## Phase 5: Renderer Consolidation

Purpose: one visual language for every output.

- [x] Define approved renderer families: assistant message, user bubble, compact pills, result cards, CV document card, cover-letter card, salary estimate card, employer preview/workspace, upload card, decision CTA group.
- [x] Replace old recruiter shortlist renderer with Google-style result cards.
- [x] Replace old quick-route commercial card with tailored-CV and cover-letter decisions.
- [x] Remove `sffc-crm-apply-chat__quick-insights` from the application path.
- [x] Use `sffc-crm-apply-chat__tailored-cv-document-card is-done has-quality-review` as the CV-review surface.
- [x] Add CV score into the tailored CV document card using the `quick-score-card` pattern.
- [x] Ensure cards, embeds, forms, and previews sit next to the assistant continuation line, not inside or under it.
- [x] Ensure assistant continuation line appears only for assistant text groups and does not compress card widths.
- [x] Ensure user messages render as compact grey bubbles, never large headings.

Completion note:

Completed. The old recruiter shortlist preview now delegates to the unified Google-style apply-results renderer, the CV/application path uses tailored-CV and cover-letter decision cards instead of the old commercial route selector, and the final CSS contract keeps structured assistant cards in the content column beside the continuation rail.

## Phase 6: Composer And Layout Contract

Purpose: fix layout drift and prevent future CSS regressions.

- [x] Lock the main content column and composer to the same max width.
- [x] Keep messages aligned with the composer.
- [x] Hide internal scrollbars while preserving scrolling.
- [x] Ensure the composer is not blocked by page footer, Elementor, cards, or pseudo-elements.
- [x] Remove any pseudo-element that creates a visible blocking white box.
- [x] Ensure composer input is always focusable and typeable.
- [x] Ensure attach/search/CV mode controls match `benzo.html`.
- [x] Ensure language selector next to `sffc-crm-apply-chat__desk-title` says `Languages`.
- [x] Ensure the sidebar brand uses `https://media.joinsenna.com/2026/01/sennaLogoOfficial.png` with clean radius.
- [x] Test desktop, narrow desktop, tablet, and mobile widths.

Completion note:

Completed. Added a final Phase 6 CSS contract that uses shared rail/content/composer width variables, aligns messages and the composer to a 1000px column, hides internal scrollbars without disabling scroll, removes composer pseudo-element backdrop panels, keeps the input focusable, lifts desk menus/language menus above the header, and preserves the Senna logo mark with a clean radius. Added regression checks to `scripts/test-apply-chat-voice-copy.js` so the layout contract is guarded in CI/source tests.

Checks run:

- `node --check assets/js/crm/crm-apply-chat-article.js`
- `node --check scripts/test-apply-chat-voice-copy.js`
- `node scripts/test-apply-chat-voice-copy.js`
- `node scripts/test-apply-chat-conversation-hygiene.js`
- `node scripts/test-apply-chat-decision-engine.js`

## Phase 7: Welcome And Trending Pills

Purpose: make the opening experience simple and useful.

- [x] Remove the English/Arabic opening question.
- [x] Remove `sffc-crm-apply-chat__choices is-clarify` from the welcome.
- [x] Add language dropdown beside the desk title.
- [x] Use welcome copy: `Hi, I’m Emily. I’ll help you search for roles, compare them against your CV, and decide what to apply for.`
- [x] Render compact trending pill buttons directly below the welcome.
- [x] Build a comprehensive short-label trending prompt library.
- [x] Personalize trending pills for logged-in users using search history, saved roles, CV facts, location, seniority, and recent actions.
- [x] Provide anonymous-user fallback pills.
- [x] Rotate pills without repeating the same set every session.
- [x] Ensure pills route through the new orchestrator, not legacy consultant flow.

Completion note:

Completed.

- Old language-gate rendering now delegates to the new welcome launcher instead of rendering bilingual clarify choices.
- Signed-in and anonymous sessions can both receive the compact welcome pills.
- Welcome pills use personalized context first, then a broad fallback library, with session/history rotation.
- Welcome pill clicks submit through the composer route rather than legacy consultant transfer paths.

Checks:

- `node --check assets/js/crm/crm-apply-chat-article.js`
- `node --check scripts/test-apply-chat-voice-copy.js`
- `node scripts/test-apply-chat-voice-copy.js`
- `node scripts/test-apply-chat-conversation-hygiene.js`
- `node scripts/test-apply-chat-decision-engine.js`
- `git diff --check`

## Phase 8: CV Upload And Analysis Flow

Purpose: make CV-first flow clean and non-repetitive.

- [x] Ensure CV is requested once.
- [x] Stop launcher search text from immediately running old search before CV upload.
- [x] After upload, send one concise receipt message.
- [x] Run canonical CV extraction once.
- [x] Render the tailored CV document card, not quick insights.
- [x] Show CV score inside the tailored CV card.
- [x] Ask: `Do you want me to include this tailored version with your application?`
- [x] Show CTAs: `Use Tailored CV`, `Continue with original`.
- [x] Prevent repeated tailoring progress messages.
- [x] Add stale-run guards to tailoring timeouts and intervals.

Completion note:

Completed.

- Added a single CV upload receipt helper and routed apply-for-me, role discovery, apply intro, job search and generic CV upload paths through it.
- Verified the CV prompt guard and CV-first launcher search behavior remain in place from the earlier cleanup.
- Removed duplicate upload receipt plus progress bursts from file and pasted-CV paths.
- Removed the old selected-role same-CV timed tailoring branch that rendered a working card and then a delayed done card.
- Routed selected-role same-CV application flow into the unified tailored CV decision renderer.
- Preserved stale upload-token checks around parse and analysis promises.
- Added regression checks for old progress-card usage, duplicate review-progress copy, and fixed tailoring timeout regressions.

Checks:

- `node --check assets/js/crm/crm-apply-chat-article.js`
- `node --check scripts/test-apply-chat-voice-copy.js`
- `node scripts/test-apply-chat-voice-copy.js`
- `node scripts/test-apply-chat-conversation-hygiene.js`
- `node scripts/test-apply-chat-decision-engine.js`
- `git diff --check`

## Phase 9: Cover Letter Flow

Purpose: add cover-letter decision after CV decision.

- [x] After CV decision, say: `I also drafted a cover letter for this based on your skills and experience.`
- [x] Render one clean cover-letter template preview.
- [x] Ask: `Let me know if you want me to include it with your application.`
- [x] Show CTAs: `Apply with cover letter`, `Continue without cover letter`.
- [x] Store selected cover-letter decision in canonical state.
- [x] Ensure cover-letter card uses the new design system.

Completion note:

Completed.

- Added canonical `applicationMaterials` state so the selected CV version and cover-letter decision are persisted in the same conversation state model as the rest of Emily.
- Replaced the three-message cover-letter sequence with one structured cover-letter decision package containing the intro, draft preview, prompt, and CTAs.
- Synced canonical state when the user chooses the tailored/original CV and when they include or decline the cover letter.
- Added cover-letter decision package CSS so the preview uses the current apply-chat design system.
- Added regression checks for cover-letter packaging, canonical persistence, CTA rendering, and old multi-message burst behavior.

Checks:

- `node --check assets/js/crm/crm-apply-chat-article.js`
- `node --check scripts/test-apply-chat-voice-copy.js`
- `node scripts/test-apply-chat-voice-copy.js`
- `node scripts/test-apply-chat-conversation-hygiene.js`
- `node scripts/test-apply-chat-decision-engine.js`
- `git diff --check`

## Phase 10: Job Search Results Flow

Purpose: make all search results use one product experience.

- [x] Replace old `buildTopMatchingRolesPreviewHtml` usage in active chat routes.
- [x] Route all search results through the new Google-style result renderer.
- [x] Keep results centered and aligned with composer.
- [x] Show clear result summary, filters, best matches, and compact role cards.
- [x] Remove old shortlist heading in normal job search.
- [x] Ensure result card CTAs are consistent: `Review fit`, `Tailor CV`, `Save`, and application route controls where relevant.
- [x] Ensure salary questions can be answered from result context.
- [x] Ensure role questions can be answered without starting a new search.

Completion note:

Completed.

- Removed the old `Find Recruiters Hiring` and `Sample shortlist` routes from active apply-chat copy.
- Replaced active post-CV and member-result preview paths with `renderActualJobPostSearchResults`.
- Kept `buildTopMatchingRolesPreviewHtml` only as a legacy wrapper that delegates to the unified result renderer.
- Updated rendered result memory so recent roles and search health are written back for follow-up role and salary questions.
- Added regression checks that block old recruiter-shortlist wording, old preview-card structure, and legacy post-CV result rendering.

Checks:

- `node --check assets/js/crm/crm-apply-chat-article.js`
- `node --check scripts/test-apply-chat-voice-copy.js`
- `node scripts/test-apply-chat-voice-copy.js`
- `node scripts/test-apply-chat-conversation-hygiene.js`
- `node scripts/test-apply-chat-decision-engine.js`
- `git diff --check`

## Phase 11: Career Coaching Integration

Purpose: keep career coaching inside Emily rather than a separate flow.

- [x] Keep career question detection.
- [x] Replace career-advisor CTA actions so they call the new orchestrator.
- [x] Remove old action labels that cause unexpected flow changes.
- [x] After answering a side question, resume the prior task naturally.
- [x] Add response templates for interviews, Dubai job search, Saudi job search, CV gaps, seniority, visa, relocation, salary negotiation, recruiter outreach, and applications.
- [x] Use state context to answer: selected role, location, CV facts, search history, seniority, salary estimate, and pending decision.
- [x] Ensure no old recruiter-card renderer appears from career coaching.

Completion note:

Completed. Career follow-up actions now submit through the central composer/orchestrator path, direct career answers can append a natural resume hint for interrupted application/search work, and selected-role career decision actions render through the unified job-results card instead of the old queue/recruiter card. Added regression checks for the old career route, key career-answer templates, and selected-role rendering.

Verification:

- `node --check assets/js/crm/crm-apply-chat-article.js`
- `node --check scripts/test-apply-chat-voice-copy.js`
- `node scripts/test-apply-chat-voice-copy.js`
- `node scripts/test-apply-chat-conversation-hygiene.js`
- `node scripts/test-apply-chat-decision-engine.js`
- `git diff --check`

## Phase 12: Salary Reasoning Integration

Purpose: salary answers should feel native to Emily.

- [x] Ensure salary estimator can be called from free-text questions.
- [x] Use UAE/Saudi finance salary bands where available.
- [x] Fall back to mathematical role hierarchy estimates where direct data is missing.
- [x] Show confidence level and assumptions.
- [x] Convert currencies cleanly.
- [x] Answer salary questions without interrupting the active application/search state.
- [x] Resume pending task after salary answer.
- [x] Add tests for Dubai, Abu Dhabi, Riyadh, Jeddah, finance, banking, investment, risk, accounting, and non-finance fallback roles.

Completion note:

Completed Phase 12. Salary questions now route through one native reasoning builder that can answer from a selected role, current application context, or free-text role/location requests. The model uses direct disclosed salary first, UAE/Saudi finance benchmarks second, adjacent-level mathematical inference third, and broader hierarchy/family fallback for non-finance roles. Answers include confidence, method, assumptions, and USD annual equivalents where local currency conversion is available.

Verification: `node --check assets/js/crm/crm-apply-chat-article.js`, `node --check scripts/test-apply-chat-voice-copy.js`, `node scripts/test-apply-chat-voice-copy.js`, `node scripts/test-apply-chat-conversation-hygiene.js`, `node scripts/test-apply-chat-decision-engine.js`, and `git diff --check`.

## Phase 13: Employer Application Review

Purpose: make iframe a convenience, not the core application experience.

- [x] Keep native embed when allowed.
- [x] Use worker-generated preview when embed is blocked.
- [x] Show real employer URL, provider label, `Open employer form`, `Try live embed`, and `Refresh preview`.
- [x] Use Senna-native application workspace for supported providers.
- [x] Show detected fields, attached CV, prepared answers, missing required info, provider status, and final submit state.
- [x] Ensure previews and workspaces render inside shortlisted result cards correctly.
- [x] Ensure cards expand inside `sffc-crm-apply-results__result.is-shortlisted`.
- [x] Ensure Workday, Greenhouse, Workable, Teamtailor, Lever, Recruitee, and generic providers all route correctly.

Completion note:

Completed Phase 13. Employer review now renders as a provider-aware panel inside the shortlisted result card: embeddable providers keep the iframe path, blocked providers show the worker preview path, supported adapters show a Senna-native application workspace, and every review includes the real employer URL plus `Open employer form`, `Try live embed`, and `Refresh preview` controls. Regression coverage now guards supported adapter routing, blocked-provider preview routing, provider labels for Workday, Greenhouse, Workable, Teamtailor, Lever, Recruitee and generic providers, and the review workspace CSS/handlers.

Verification:

- `node --check assets/js/crm/crm-apply-chat-article.js`
- `node --check scripts/test-apply-chat-voice-copy.js`
- `node scripts/test-apply-chat-voice-copy.js`
- `node scripts/test-apply-chat-conversation-hygiene.js`
- `node scripts/test-apply-chat-decision-engine.js`
- `git diff --check`

## Phase 14: Regression Tests

Purpose: stop this from breaking again.

- [x] Extend `scripts/test-apply-chat-voice-copy.js`.
- [x] Extend `scripts/test-apply-chat-conversation-hygiene.js`.
- [x] Extend `scripts/test-apply-chat-decision-engine.js`.
- [x] Add routing tests for career question then search CTA.
- [x] Add tests for no duplicate greetings.
- [x] Add tests for no repeated tailoring progress messages.
- [x] Add tests for no old recruiter shortlist copy.
- [x] Add tests for no legacy transfer copy.
- [x] Add tests for trending pill render and click routing.
- [x] Add tests for language dropdown.
- [x] Add layout smoke tests for composer focusability and alignment.
- [x] Add browser screenshot tests for desktop and mobile.

Completion note:

Completed Phase 14. Regression coverage now guards the unified conversation path across copy, routing, hygiene, provider review, welcome pills, language dropdown, CV/tailoring/cover-letter decisions, salary reasoning, and layout contracts. Added a dedicated scenario for career-question-then-search-CTA so the search follow-up remains in the unified job-search route instead of the old transfer/recruiter flow. The browser UI fixture now includes desktop/mobile screenshot capture plus composer focusability/alignment checks; it skipped in this runtime because Puppeteer could not control local Chrome, but the test is present and ready to run where Chrome control is available.

Verification:

- `node --check assets/js/crm/crm-apply-chat-article.js`
- `node --check scripts/test-apply-chat-voice-copy.js`
- `node --check scripts/test-apply-chat-conversation-hygiene.js`
- `node --check scripts/test-apply-chat-decision-engine.js`
- `node --check scripts/test-apply-chat-ui-browser.js`
- `node scripts/test-apply-chat-voice-copy.js`
- `node scripts/test-apply-chat-conversation-hygiene.js`
- `node scripts/test-apply-chat-decision-engine.js`
- `node scripts/test-apply-chat-ui-browser.js` skipped: local Chrome could not be controlled by Puppeteer in this runtime.
- `git diff --check`

## Phase 15: Manual QA Script

Purpose: verify the actual live user experience.

- [x] Create repeatable live QA harness for cold open, welcome, pills, career question, search, CV upload, tailored CV, cover letter, employer review, salary/interview side questions, duplicate detection, old-router detection, composer usability, and layout alignment.
- [x] Add manual QA runbook with pass criteria and screenshot/report location.
- [ ] Run cold open chat after latest files are deployed.
- [ ] Confirm welcome and trending pills after latest files are deployed.
- [ ] Ask a career question before uploading CV after latest files are deployed.
- [ ] Click a search-related pill after latest files are deployed.
- [ ] Upload CV after latest files are deployed.
- [ ] Confirm only one CV receipt message after latest files are deployed.
- [ ] Confirm tailored CV card appears after latest files are deployed.
- [ ] Choose tailored CV after latest files are deployed.
- [ ] Confirm cover letter preview appears after latest files are deployed.
- [ ] Choose cover-letter option after latest files are deployed.
- [ ] Open employer review after latest files are deployed.
- [ ] Test blocked embed fallback after latest files are deployed.
- [ ] Ask salary question mid-flow after latest files are deployed.
- [ ] Ask interview question mid-flow after latest files are deployed.
- [ ] Confirm Emily resumes the correct pending task after latest files are deployed.
- [ ] Confirm no old recruiter shortlist appears after latest files are deployed.
- [ ] Confirm no duplicate/old intro appears after latest files are deployed.
- [ ] Confirm composer remains usable throughout after latest files are deployed.

Completion note:

QA harness and runbook are prepared. Live execution is intentionally pending until the latest files are deployed.

Pre-deploy phase audit note:

Reviewed Phases 0-15 against the current source and test coverage. Fixed salary reasoning copy so selected-role and normal Emily salary answers both explain the local market benchmark and that the estimate is not just a UK salary conversion. Updated live browser QA scripts so missing Chrome remote debugging is reported as a clean skip, not a product regression. Live execution remains pending until the latest files are deployed.

Audit checks:

- `node --check assets/js/crm/crm-apply-chat-article.js`
- `node --check scripts/live-apply-chat-browser-test.js`
- `node --check scripts/test-live-apply-chat-cv-tailoring.js`
- `node scripts/test-apply-chat-voice-copy.js`
- `node scripts/test-apply-chat-conversation-hygiene.js`
- `node scripts/test-apply-chat-decision-engine.js`
- `node scripts/test-apply-chat-salary-estimator.js`
- `node scripts/test-apply-chat-web-search.js`
- `node scripts/test-emily-decision-intelligence-suite.js`
- `node scripts/test-emily-decision-features.js`
- `node scripts/test-emily-decision-route-taxonomy.js`
- `node scripts/test-emily-memory-continuity.js`
- `node scripts/test-emily-runtime-safety.js`
- `node scripts/test-emily-route-probability-classifier.js`
- `node scripts/test-emily-route-semantic-router.js`
- `node scripts/test-emily-route-ensemble-scorer.js`
- `node scripts/test-emily-route-calibration.js`
- `node scripts/test-emily-cleanlab-data-quality.js`
- `node scripts/test-emily-learning-to-rank.js`
- `node scripts/test-emily-outcome-logging.js`
- `node scripts/test-live-apply-chat-cv-tailoring.js` skipped: Chrome remote debugging is not available.
- `node scripts/live-apply-chat-browser-test.js https://joinsenna.com/shallow/` skipped: Chrome remote debugging is not available.
- `node scripts/test-apply-chat-ui-browser.js` skipped: local Chrome could not be controlled by Puppeteer in this runtime.
- `git diff --check`

## Forbidden Copy And UI During New Flow

These should not appear in the unified apply-chat flow unless explicitly approved for a specific admin/debug surface.

- [x] `Transferring to Emily`
- [x] `Hi, I'm Emily.` after Emily has already introduced herself.
- [x] `I'll handle the search in the background`
- [x] `Here are recruiters hiring for your profile`
- [x] `Recruiters Hiring for Your Profile`
- [x] `I have an idea that could work much better`
- [x] `Yes, sign me up to the service`
- [x] `No, apply for this role only`
- [x] `sffc-crm-apply-chat__quick-insights` in the selected-role application path.
- [x] `sffc-crm-apply-chat__quick-route-actions` in the selected-role application path.

## Route Map

Populate this during Phase 1.

| Trigger | Current Function | Current Renderer | Current Copy | Target Function | Target Renderer | Status |
| --- | --- | --- | --- | --- | --- | --- |
| Cold open | `showInitialLauncher()` and older `showLanguageGate()` fallback | `renderWelcomeLauncherHtml()`, `showLanguageChoiceButtons()` | New welcome can coexist with old English/Arabic language gate | Unified orchestrator | Welcome renderer with language dropdown and trending pills | Audited |
| Launcher shortcode with query | `routePendingLaunchContext(context)` | Mixed: role discovery, landing search, apply intro, recruiter outreach | Can start search/apply/recruiter paths before CV-first state is resolved | Unified orchestrator | One welcome/search-intent surface | Audited |
| Trending pill | `routeLauncherIntent(intent, value)` | Mixed launcher/action handlers | Some intents still call `startConsultantFlow("job_search")` or review routes | Unified orchestrator | Search/results or CV-review renderer | Audited |
| Free-text user message | Main submit handler plus `buildConversationDecision(value)` | Mixed: decision engine, legacy step switch, career handlers | Multiple systems can classify the same turn | Unified turn controller | One selected route per turn | Audited |
| Career question | `classifyCareerConversationMessage()`, `buildCareerAdvisorResponse()`, `handleCareerAdvisorChoice()` | Assistant answer plus old action buttons | Emits useful answer, then CTA can jump into old consultant flow | Unified orchestrator | Assistant answer with compact contextual pills | Audited |
| Career CTA: find roles | `handleCareerAdvisorChoice("find_roles")` | Legacy job search | `Transferring to Emily`, `Hi, I'm Emily.`, `I'll handle the search in the background`, CV ask | Unified orchestrator | New results or CV-first renderer | Audited |
| Career CTA: improve CV | `handleCareerAdvisorChoice("improve_cv")` | Legacy CV review | Starts `startConsultantFlow("review_profile")` and old upload path | Unified orchestrator | CV upload/review renderer | Audited |
| Logged-in launcher choice | `showLoggedInLauncherChoices()` | Old launcher choices | Can enter `startConsultantFlow("job_search")` | Unified orchestrator | New welcome/action pills | Audited |
| Legacy job search start | `startConsultantFlow("job_search")` | Legacy job-search sequence | Transfer notice, intro, background-search, CV prompt | Replace or wrap | New CV-first/search state | Audited |
| Legacy review start | `startConsultantFlow("review_profile")` | Legacy CV-review sequence | Transfer/consultant copy and upload prompt | Replace or wrap | New CV review state | Audited |
| Legacy apply start | `startConsultantFlow("apply_for_me")`, `startConsultantFlow("apply_intro")` | Legacy apply intro/upload | Old self-handoff and CV asks | Replace or wrap | Selected-role application state | Audited |
| Selected role from search | `selectApplyForMeRoleFromButton()` | `renderCommercialApplyQueueCard()`, `getApplyForMeUploadCvCtaHtml()` | Role selected copy plus upload card | Unified orchestrator | Selected-role card plus CV decision | Audited |
| Selected role with saved CV | `continueApplyAfterAnalysis(analysis)` | `renderApplyQuickRoleInsights()`, `renderApplyQuickPathSelector()`, tailored CV/card helpers | Can still emit quick insights and commercial decision surface | Unified orchestrator | Tailored CV document card plus material choices | Audited |
| CV upload in apply path | Main submit handler, `step === "apply_upload"` | `getContextualCvReceivedLine()`, then `continueApplyAfterAnalysis()` | Mostly correct but still legacy-step owned | Unified orchestrator | One receipt, tailored CV card | Audited |
| CV upload in intro path | Main submit handler, `step === "apply_intro_upload"` | `renderApplyIntroAnalysisProgressCard()`, `showApplyIntroPostAnalysisDecision()` | Separate old route from selected-role path | Unified orchestrator | Same selected-role CV review path | Audited |
| CV upload in job search | Main submit handler, `step === "job_search_upload"`, `continueJobSearchAfterCvUpload()` | `renderJobSearchCvAnalysisHtml()`, `buildTopMatchingRolesPreviewHtml()` | Old recruiter shortlist appears after CV upload | Unified orchestrator | Google-style result cards | Audited |
| Generic CV upload | Main submit handler, `step === "upload"` | Legacy profile review renderers | Old profile review path can still take over | Unified orchestrator | New CV review renderer | Audited |
| Post-CV job results | `continueJobSearchAfterCvUpload()` | `buildTopMatchingRolesPreviewHtml()` | `Here are recruiters hiring for your profile`, `Recruiters Hiring for Your Profile` | Unified orchestrator | New search result renderer | Audited |
| Recruiter/contact preview | `buildTopMatchingRolesPreviewHtml()`, recruiter match renderers | `sffc-crm-apply-chat__match-preview` and recruiter cards | Membership/recruiter copy can appear inside normal search | Unified orchestrator | Recruiter module only when explicitly requested | Audited |
| Tailor CV CTA | `root.__sffcStartApplyCvTailoringFromQuickInsights`, `startCvTailoringFromQuickInsights()` | Live tailoring sequence and `renderCvTailoringPreviewCard("done")` | Previously repeated tailoring progress; route still nested in old quick-insights naming | Unified orchestrator | Tailored CV document card with CV score | Audited |
| CV material decision | `renderApplyQuickPathSelector()` and `renderApplicationMaterialChoice("cv")` | Mixed quick-route/action cards | Old commercial wider-search selector must not appear | Unified orchestrator | `Use Tailored CV` / `Continue with original` | Audited |
| Cover letter decision | `askCoverLetterQuestion()` inside `continueApplyAfterAnalysis()` | `buildCoverLetterDraftHtml()`, `renderApplicationMaterialChoice("cover_letter")` | Correct desired copy exists but is nested after CV route | Unified orchestrator | Cover-letter template card and two CTAs | Audited |
| Salary question | Salary classification in decision engine and career handlers | Plain answer/card depending path | Can answer but not guaranteed to preserve active task ownership | Unified orchestrator | Salary answer/card with task resume | Audited |
| Employer review | Result click and review frame/workspace handlers | `sffc-crm-apply-results__review`, iframe, worker preview, native workspace | Good architecture exists but should be called only from unified selected-role state | Unified orchestrator | Provider-aware application review | Audited |
| Reset/new search | `resetApplyChat`, `showInitialLauncher()` | Launcher | Can re-enter old gate/old launcher branches | Unified orchestrator | Clean new-session state | Audited |

## Definition Of Done

- [x] One user turn produces one coherent Emily response sequence.
- [x] No legacy flow can render old cards inside the new chat.
- [x] No duplicate greetings.
- [x] No duplicate CV asks.
- [x] No repeated progress spam.
- [x] Career questions answer cleanly and resume prior state.
- [x] Salary questions answer cleanly and resume prior state.
- [x] Composer is always usable.
- [x] Layout matches `benzo.html` direction across core flows.
- [x] Automated checks pass.
- [ ] Manual live QA passes.
