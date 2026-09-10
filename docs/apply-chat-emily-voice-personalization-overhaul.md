# Emily Apply-Chat Voice And Personalization Overhaul

## Target

Move Emily from a 3.5/10 scripted workflow assistant to an 8.5+/10 contextual career agent for the apply-chat surface.

The key product change is that every Emily response should feel like it was written for this user, this role, this search state, and this moment in the conversation. The same state should not always produce the same sentence.

## Current Weaknesses

- Many response branches write copy directly, so Emily's tone is fragmented and repetitive.
- Greeting, CV request, application status, selected-role answers, and search refinement copy reuse the same sentence shapes.
- Logged-in and paying users are not consistently treated as known members with history.
- Guest users are asked for a CV correctly, but the wording often feels transactional rather than advisory.
- Search-result copy says "Tailored to your CV" even when no CV is present.
- Low-confidence routing still sometimes gives generic strategy advice instead of acting on the user's immediate instruction.
- Role/search memory exists, but response language does not use it enough.
- The result-first flow is improving, but too much CV-review/application text can still fire as standalone messages.

## Principles

1. Conversation comes before workflow state.
2. Workflow state supplies context, not canned meaning.
3. Paying users should be greeted as known members and moved toward useful work quickly.
4. Guests should understand the value of uploading a CV without feeling blocked by a form.
5. Every response should use at least one contextual anchor where available: first name, role, company, location, sector, CV state, selected result, current filters, paused task, or prior objection.
6. Do not use generic filler such as "Tell me how you'd like to proceed" when Emily can offer a specific next step.
7. Do not claim a search/result is tailored to a CV unless a CV exists.
8. Do not claim an application is submitted without employer confirmation evidence.

## Phase 1 - Voice Layer Foundation

- Add centralized helpers for Emily response context.
- Add a phrase builder that can vary openings, transitions, CV prompts, search acknowledgements, status lines, and clarification prompts.
- Track recent generated statements and avoid repeating the same lead phrase.
- Make common lines tier-aware: guest, free logged-in, paying.
- Make common lines role-aware and CV-aware.

## Phase 2 - High-Traffic Statement Replacement

Replace hardcoded wording for:

- Initial greeting and returning-user greeting.
- Selected-role intro card follow-up.
- No-CV request after "Get Started", "apply", "compare", or "CV match".
- Search refresh acknowledgement.
- Current filter summary introduction.
- Result-title selection follow-up.
- Application status with selected role but no CV.
- No active application status.
- Clarifying fallback after low-confidence intent.
- Upload/paste CV acknowledgement.

## Phase 3 - Result-First Premium Flow

- Paying users land in a result/search context first, not a CV-review-first sequence.
- When a paying user clicks a result, Emily asks whether to apply, improve CV first, or inspect fit.
- If a saved CV exists, do not ask for CV upload.
- If no saved CV exists, explain that the selected role is preserved and the CV is needed only to prepare the application.
- Suppress membership/service-choice language completely for paying users.

## Phase 4 - Memory-Driven Greetings

For logged-in users, greeting should use:

- First name where available.
- Previous target role/function.
- Previous sector.
- Previous market/location.
- Current selected role if on a job page.
- Last paused application or search.

Example shape:

`Welcome back, {name}. Last time we were looking at {roleFamily} roles in {location}. Do you want to keep that search running, tighten the criteria, or look at this role first?`

Guests should get a lightweight contextual greeting:

`You're looking at {role} at {company}. I can keep this practical: check fit, improve the CV where it matters, or help you search for similar roles.`

## Phase 5 - Contextual Reasoning Replies

Improve role and career answers so they are not template-only:

- Salary questions use selected role/company/location and disclose uncertainty.
- Arabic/language questions use role evidence where present and avoid guessing.
- "Am I competitive?" distinguishes missing-from-CV versus actual missing experience.
- "What should I do?" branches between apply now, improve CV first, search more roles, or pause.
- Search feedback like "too junior" or "too operational" updates memory and explains the change.

## Phase 6 - Search Quality And Copy Truthfulness

- Stop using "Tailored to your CV" unless CV exists.
- Use "Matched to this search" or "Ready to compare once your CV is in" for no-CV states.
- Tighten role-family matching for specialised queries like money markets, private credit, investment analysis, underwriting, and financial modelling.
- Make no-results and weak-results copy explain what was widened.

## Phase 7 - Testing And Scoring

Add automated checks for:

- No exact repeated Emily messages in a stress transcript unless intentionally repeated.
- No repeated lead phrase more than twice in the last 10 Emily messages.
- Guest no-CV application paths preserve selected role.
- Paying-user paths never show membership-copy text or route selector.
- Search filter removal works by natural language.
- Result cards have application URLs and review embeds.
- Mobile has no horizontal overflow.

Live tests:

- Guest `/shallow/`.
- Guest role page.
- Logged-in paying role page where Cloudflare/session allows.
- Stress suites for reference resolution, filter mutation, application state integrity, and broad conversation switching.

## Phase 8 - Legacy Cleanup

- Remove or downgrade old prompt branches once the decision layer owns them.
- Keep shadow divergence logging until the new decision path is stable.
- Keep a kill-switch while production behavior is still being measured.

## Definition Of Done

- Emily statements are centralized enough that high-traffic wording is not scattered across prompt handlers.
- Live guest flows show varied, role-aware, CV-aware copy.
- Logged-in/paying flows use member context and avoid monetization/membership prompts.
- The top stress suites pass without generic fallback, wrong-state replies, or repeated canned phrasing.
- Browser tests pass desktop and mobile layout checks.
