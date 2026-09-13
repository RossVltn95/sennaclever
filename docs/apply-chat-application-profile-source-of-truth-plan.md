# Apply Chat Application Profile Source Of Truth Plan

## Purpose

The apply-chat application engine needs one canonical source of truth for candidate data. The current system has useful pieces already: CV text extraction, CV matching, tailored CV generation, provider-specific adapters, worker queueing, and application status polling. The weakness is that those pieces do not consistently share one verified application profile.

The goal is to make Senna do as much of the application work as possible for the user:

- extract application details from the CV automatically;
- infer safe values where appropriate;
- ask the user only for missing, risky, or legally/materially sensitive fields;
- complete most employer forms through the provider adapters;
- stop for review, approval, verification, or final submission boundaries;
- preserve every answer and preference so future applications require less effort.

This plan defines the architecture and phased implementation.

## Product Principle

The user should not feel like they are filling out another form inside Senna.

Emily should do the heavy lifting:

1. Read the CV.
2. Build a structured application profile.
3. Confirm only the fields that matter.
4. Tailor the CV and application answers.
5. Fill the employer form through the correct adapter.
6. Ask for review or missing input only when needed.
7. Save what was learned for the next application.

The application experience should feel like:

> "I have most of what I need. I will complete the employer form and stop before anything sensitive, uncertain, or final needs your approval."

Not:

> "Please answer every possible field manually before I start."

## Current Evidence

The existing implementation already has partial support for this:

- Uploaded CVs are parsed into `capturedCvText` in `assets/js/crm/crm-apply-chat-article.js`.
- The frontend can extract some details, such as phone numbers, from CV text.
- Application tasks are queued through `sffc_crm_apply_chat_queue_application_task`.
- The queue endpoint stores `candidate_name`, `candidate_email`, `candidate_phone`, `cv_file_url`, `cv_text`, `cv_mode`, `tailored_cv_text`, `tailored_cv_model`, `application_answers`, and provider metadata.
- The worker downloads the CV file, extracts text from PDFs if needed, builds a candidate object, splits full name into first and last names, and fills provider forms.
- The worker has provider logic for Workable, Greenhouse, Teamtailor, Workday, SuccessFactors, and simple forms.
- The worker can detect verification, challenges, missing fields, validation errors, resume upload state, and submit-stage readiness.

The missing part is not extraction itself. The missing part is a single verified profile object that all of those layers trust.

## Target Data Model

Create a canonical `ApplicationProfile` object.

```ts
type ApplicationProfile = {
  identity: {
    fullName: ProfileField<string>;
    firstName: ProfileField<string>;
    lastName: ProfileField<string>;
    email: ProfileField<string>;
    phone: ProfileField<string>;
    linkedin: ProfileField<string>;
    portfolioUrl: ProfileField<string>;
  };

  location: {
    currentLocation: ProfileField<string>;
    city: ProfileField<string>;
    country: ProfileField<string>;
    willingToRelocate: ProfileField<boolean | null>;
    targetMarkets: ProfileField<string[]>;
    noticePeriod: ProfileField<string>;
  };

  workAuthorization: {
    uae: ProfileField<string>;
    saudi: ProfileField<string>;
    uk: ProfileField<string>;
    eu: ProfileField<string>;
    requiresSponsorship: ProfileField<boolean | null>;
    notes: ProfileField<string>;
  };

  professional: {
    currentTitle: ProfileField<string>;
    currentEmployer: ProfileField<string>;
    yearsExperience: ProfileField<number | null>;
    seniority: ProfileField<string>;
    sectors: ProfileField<string[]>;
    functions: ProfileField<string[]>;
    skills: ProfileField<string[]>;
    languages: ProfileField<string[]>;
    education: ProfileField<string[]>;
    certifications: ProfileField<string[]>;
  };

  applicationDefaults: {
    salaryExpectation: ProfileField<string>;
    currentSalary: ProfileField<string>;
    sourceAnswer: ProfileField<string>;
    motivationBase: ProfileField<string>;
    relocationReason: ProfileField<string>;
    preferredCvMode: ProfileField<"tailored" | "original" | "ask">;
    submitPreference: ProfileField<"prepare_only" | "ask_before_submit" | "allow_submit">;
  };

  answerMemory: {
    screeningAnswers: ApplicationAnswerMemoryItem[];
    customQuestionDrafts: ApplicationAnswerMemoryItem[];
    approvedAnswers: ApplicationAnswerMemoryItem[];
    rejectedAnswers: ApplicationAnswerMemoryItem[];
  };

  evidence: {
    sourceCvId: string;
    sourceCvFileName: string;
    extractedFields: string[];
    confirmedFields: string[];
    inferredFields: string[];
    defaultedFields: string[];
    missingFields: string[];
    confidenceByField: Record<string, number>;
  };
};

type ProfileField<T> = {
  value: T;
  source: "user_confirmed" | "cv" | "profile" | "answer_memory" | "inferred" | "default" | "unknown";
  confidence: number;
  confirmed: boolean;
  updatedAt: string;
  evidence?: string;
};
```

## Source Priority

Every adapter must resolve candidate data using this order:

1. User-confirmed profile value.
2. Existing logged-in profile value.
3. Approved answer memory.
4. CV-extracted value with sufficient confidence.
5. Conservative inference from CV/job context.
6. Safe default.
7. Ask the user.

Adapters should not independently invent answers from raw chat state.

## Safe Defaults

Safe defaults can be used to keep the application moving, but they must be marked as defaults.

Safe examples:

- `How did you hear about us?` -> `LinkedIn` or `Company website`, depending on source.
- Optional LinkedIn field when unknown -> `N/A`.
- Optional portfolio field when unknown -> blank or `N/A`.
- Notice period -> `1 month`, only if the employer allows free text and the value is not material to eligibility.
- Salary expectation -> `Open to discussion`, only when optional or free text.

Unsafe examples that should not be guessed:

- work authorization;
- visa sponsorship;
- nationality;
- criminal record declarations;
- sanctions or politically exposed person questions;
- professional licences;
- language fluency;
- mandatory certifications;
- current salary where legally/materially relevant;
- willingness to relocate when the answer affects eligibility;
- final submission consent.

Unsafe fields trigger a user confirmation checkpoint.

## My Profile Rail Section

Add a new apply-chat rail item:

```text
My profile
```

This section stores and edits the `ApplicationProfile`.

Suggested groups:

- Contact details
- Location and relocation
- Work authorization
- Experience
- Education
- Languages
- Salary and notice period
- Application preferences
- Saved screening answers

Each field should visibly show status:

```text
Confirmed
From CV
Inferred
Default
Missing
Needs confirmation
```

For paying users, this should become persistent memory. For guests, it should exist for the current session and be portable into signup.

### Apply-Chat UI Rules

The profile UI must look like part of apply-chat, not a separate dashboard dropped into the chat.

- Use `sffc-crm-apply-chat__*` classes for the new profile surface.
- Keep the rail item visually consistent with the current app rail: same icon size, spacing, active state, radius, and typography.
- Render the profile as a native Emily structured surface with Emily's avatar, not as an unowned floating card.
- Avoid card-inside-card layouts. Use a single white surface with compact sections and rows.
- Keep field status labels quiet and scannable: `Confirmed`, `From CV`, `Default`, `Missing`, and `Needs confirmation`.
- Make fields editable inline with stable row dimensions so labels, inputs, and badges do not resize the chat unexpectedly.
- On mobile, stack the profile fields into one column, keep controls within the chat width, and allow the content to scroll with the conversation.
- Do not use a marketing-style hero, decorative gradients, or oversized headings.
- The profile panel should explain what Senna can use for applications, not ask the user to manually complete every possible field.
- Any later richer profile UI should reuse the same field classes and data attributes so the application queue continues to consume one canonical profile.

## Application Readiness Gate

Before queueing a provider task, run:

```ts
validateApplicationReadiness({
  profile,
  role,
  provider,
  cvMode,
  submitPreference
});
```

Return:

```ts
type ApplicationReadiness = {
  readyToQueue: boolean;
  readyToFill: boolean;
  readyToSubmit: boolean;
  blockers: string[];
  warnings: string[];
  missingCriticalFields: string[];
  fieldsUsingDefaults: string[];
  requiresUserConfirmation: string[];
};
```

The target behavior is not to block too early. Senna should still queue and fill most of the application when it can, but it must stop before unsafe or uncertain submission points.

Example:

```text
I can fill most of this now. Before anything is submitted, I need to confirm your UAE work authorization and salary expectation.
```

## Updated Application Flow

### Role Selected

```text
User selects a job
↓
Emily resolves selectedRole
↓
ApplicationProfile is loaded or built
↓
Readiness gate checks what is missing
↓
Emily asks only for critical missing fields, or proceeds
```

### CV Uploaded

```text
CV uploaded
↓
Parse CV text
↓
Extract ApplicationProfile fields
↓
Merge with existing profile
↓
Mark field source and confidence
↓
Ask for confirmation only where needed
```

### Apply With Tailored CV

```text
User chooses Apply with Tailored CV
↓
Build tailored CV
↓
Create ApplicationTask using ApplicationProfile
↓
Adapter fills employer form
↓
Emily shows progress
↓
Pause only for verification, unsafe fields, custom answers, or final review
```

### Continue With Original CV

```text
User chooses Continue with Original CV
↓
Create ApplicationTask using ApplicationProfile
↓
Adapter fills employer form with original CV
↓
Emily shows progress
↓
Pause only when needed
```

### Improve CV First

```text
User chooses Improve CV first
↓
Emily shows concrete CV changes
↓
User approves or edits
↓
ApplicationProfile keeps the same factual source of truth
↓
ApplicationTask starts only after approval
```

## Provider Adapter Contract

Each adapter should accept the same input:

```ts
type AdapterInput = {
  provider: "workable" | "greenhouse" | "teamtailor" | "workday" | "successfactors" | "simple_form";
  applicationUrl: string;
  role: RoleEntity;
  applicationProfile: ApplicationProfile;
  cv: {
    mode: "original" | "tailored";
    fileUrl: string;
    fileName: string;
    text: string;
    tailoredText?: string;
    tailoredModel?: Record<string, unknown>;
  };
  answerMemory: ApplicationAnswerMemoryItem[];
  submitPolicy: "prepare_only" | "ask_before_submit" | "allow_submit";
};
```

Each adapter should return structured progress:

```ts
type AdapterEvent = {
  taskId: string;
  provider: string;
  status:
    | "opening"
    | "detecting_form"
    | "uploading_cv"
    | "filling_profile"
    | "answering_questions"
    | "waiting_for_user"
    | "ready_for_review"
    | "submitted"
    | "blocked"
    | "failed";
  message: string;
  fieldsFilled?: string[];
  fieldsMissing?: string[];
  questionDraft?: ApplicationQuestionDraft;
  evidenceUrl?: string;
  screenshotUrl?: string;
};
```

Emily should narrate from these events instead of firing unrelated canned messages.

## Custom Question Handling

Custom questions are not normal autofill fields.

Examples:

- `Describe how your experience would fit into our team.`
- `Why are you interested in this role?`
- `What makes you suitable?`
- `Why are you leaving your current role?`
- `Tell us about a relevant project.`

Flow:

```text
Adapter detects custom question
↓
Create ApplicationQuestionDraft
↓
Draft answer from ApplicationProfile + CV evidence + role context
↓
Show draft to user
↓
User approves, edits, regenerates, or asks for a tone change
↓
Approved answer is inserted into the form
↓
Answer is saved to answer memory with context
```

User controls:

```text
Use this answer
Make it shorter
Make it more senior
Make it more commercial
I'll edit it
```

Rules:

- Do not invent experience.
- Do not claim unconfirmed language, certifications, salary, authorization, or sector history.
- Do not submit custom answers without approval unless the user has explicitly enabled that behavior.
- Save approved answers semantically so similar questions can be reused later.

## Progress UI

Replace scattered progress messages with a stable application progress card.

Example:

```text
Application progress

✓ Employer page opened
✓ Workable form detected
✓ CV uploaded
✓ Contact details filled
! Waiting for answer: work authorization
○ Final review
○ Submit
```

Emily can still send short messages, but the progress card is the source of truth.

## Submission Boundary

There must be a hard distinction between:

- prepared;
- filled;
- ready for review;
- submitted.

Emily must never say an application has been submitted unless the adapter has confirmation evidence.

Submit policy:

```text
prepare_only:
  Fill what can be filled, but never submit.

ask_before_submit:
  Fill the form, stop at final review, ask the user.

allow_submit:
  Submit only if all unsafe fields are confirmed and the user previously opted in.
```

For paying users, the default should likely be `ask_before_submit` until the user explicitly enables stronger automation.

## noVNC And Remote Browser Role

The visible remote browser should not be the default user experience.

Preferred hierarchy:

```text
Provider adapter
↓
Invisible browser automation
↓
noVNC for takeover / verification / debugging
↓
Static screenshot fallback
```

noVNC is useful when:

- the adapter reaches an unusual widget;
- the employer requires login or verification;
- the user must take control;
- the team needs debugging evidence.

It should not replace structured provider adapters.

## Paying User Experience

Paying members should not be dragged through guest onboarding or repeated CV review.

Expected behavior:

```text
Welcome back, {name}. I still have your CV, profile, and recent search preferences.
```

When they select a role:

```text
I can apply with the tailored CV, improve the CV first, or use the original CV and move faster.
```

After they choose:

```text
I’ll complete as much of the employer form as I can and stop only if I need your confirmation.
```

The next visible state should be application progress, not another route selector.

## Guest User Experience

Guest users should still get a lightweight path:

```text
Upload CV
↓
Extract ApplicationProfile for session
↓
Confirm name/email
↓
Choose one-role application or ongoing Senna search
↓
Queue task or monetization handoff
```

Guest monetization should happen after value is shown:

- after CV/profile extraction;
- after job fit is explained;
- after Senna can say what it will do;
- before managed ongoing execution.

## Answer Memory

Save reusable answers by semantic meaning, not exact text only.

Examples:

- notice period;
- salary expectation;
- work authorization;
- sponsorship;
- relocation;
- source of application;
- motivation answer;
- role-fit answer;
- company-interest answer.

Each saved answer should include:

```ts
{
  normalizedQuestionType: string;
  originalQuestionText: string;
  answer: string;
  source: "user_approved" | "profile_default" | "cv_inferred";
  scope: "global" | "market" | "role_family" | "company" | "provider";
  roleContext?: string;
  approvedAt?: string;
}
```

If context differs materially, Emily should re-confirm instead of pasting a stale answer.

## Integration Phases

### Phase 1: Profile Contract

- Define `ApplicationProfile`.
- Define `ProfileField`.
- Define source, confidence, and confirmation semantics.
- Add compatibility mapping from old loose task fields.

Status: implemented.

### Phase 2: My Profile Rail UI

- Add `My profile` rail section.
- Display editable profile groups.
- Show field status badges.
- Persist guest profile in session storage.
- Persist logged-in profile server-side.

Status: implemented.

### Phase 3: CV To Profile Extraction

- Route CV extraction into `ApplicationProfile`.
- Extract contact, role, employer, education, skills, languages, location, and years experience.
- Store field confidence and evidence.
- Avoid overwriting confirmed fields without user approval.

Status: implemented.

### Phase 4: Profile Defaults And Safety Rules

- Implement safe defaults.
- Implement unsafe field list.
- Add source labels for defaults.
- Prevent unsafe values from being guessed silently.

Default behavior:

- Emily should not turn the application flow into a long questionnaire.
- If a field has a safe draft default, Emily may fill it and later ask the user to confirm/edit when it matters.
- Example: if an employer asks `Are you an Emirati national?`, the default draft answer is `No`. Emily should say: `I've got a question from the employer: Are you an Emirati national? I've put "No". Is this correct?`
- Eligibility/legal defaults are allowed as draft answers, but they remain confirmation-required before final submission when not user-confirmed.
- Unsupported sensitive facts are never guessed.

Current implemented default categories:

- `Emirati national / UAE national`: default `No`, confirmation required.
- `Requires sponsorship`: default `No`, confirmation required.
- `Work authorization / right to work`: default `Yes`, confirmation required unless profile/answer memory confirms it.
- `Reasonable adjustments`: default `No`, confirmation required unless profile/answer memory confirms it.
- `Current salary`: never guessed; use profile/answer memory or prefer-not-to-say only if the employer provides that option.
- `Expected salary`: use profile/answer memory, otherwise `Open to discussion` where compatible.
- `How did you hear about this role?`: default `LinkedIn`.
- `Consider other opportunities`: default `Yes`.
- `Consent/privacy/declarations`: default affirmative, confirmation required before final submission.

Current unsafe categories:

- passport/government/tax/social-security IDs;
- licence or certification numbers;
- reference contacts;
- exact GPA/grades/test scores;
- unsupported nationality/citizenship;
- salary history/current salary when no profile answer or prefer-not-to-say option exists.

Status: implemented.

### Phase 5: Application Readiness Gate

- Add pre-queue readiness validation.
- Allow fill-first behavior where safe.
- Stop only for blockers, unsafe fields, or final review.

Implemented behavior:

- The application worker queue now calls `ensureApplicationProfileReadinessThenQueue()` before collecting employer-specific questions or queueing the worker.
- The readiness gate blocks only on essentials that would make the application fail immediately:
  - missing usable employer application link;
  - missing candidate full name;
  - missing candidate email;
  - missing CV text/file.
- Non-blocking gaps such as an unconfirmed phone number are warnings, not a reason to stop the flow.
- Safe draft defaults are shown in one compact confirmation card:
  - sponsorship: `No`;
  - Emirati/UAE national: `No`;
  - reasonable adjustments: `No`;
  - notice period: `1 month`.
- Once the user confirms the card, the profile stores `applicationDefaults.readinessConfirmedAt` so logged-in users are not repeatedly asked the same readiness question.
- The card includes `Looks right` and `Edit profile`; edit opens the same `My profile` surface, preserving the one-source-of-truth model.
- A debug hook is available as `__sffcApplyChatDebug.getApplicationProfileReadinessReview(item)` for browser investigations.

Status: implemented.

### Phase 6: Queue Payload Refactor

- Queue tasks with `application_profile`.
- Keep legacy fields for backward compatibility.
- Include `profile_version_id`, `answer_memory_snapshot`, and `submit_preference`.

Implementation rules:

- `candidate_name`, `candidate_email`, `candidate_phone`, `cv_text`, and provider fields remain in the request and database columns for backward compatibility.
- The canonical task payload also carries:
  - `application_profile`: full structured profile snapshot;
  - `profile_version_id`: stable profile/CV snapshot identifier for debugging and future resume logic;
  - `answer_memory_snapshot`: approved/draft answers known at queue time;
  - `submit_preference`: currently `ask_before_submit` or `submit_when_ready`.
- Explicit `application_answers` still outrank profile and answer-memory values.
- The worker exposes accessor helpers for Phase 7 adapter refactoring:
  - `getApplicationProfileContract(task)`;
  - `getApplicationProfileVersionId(task)`;
  - `getApplicationAnswerMemorySnapshot(task)`;
  - `getApplicationSubmitPreference(task)`.
- Answer memory is merged into worker candidate answers as a fallback between profile fields and explicit application answers.

Status: implemented.

### Phase 7: Adapter Contract Refactor

- Make all provider adapters consume `ApplicationProfile`.
- Standardize adapter input and output.
- Normalize field resolution order across providers.

Implemented behavior:

- The application worker now builds a normalized `AdapterInput` through `buildApplicationAdapterInput()` before provider dispatch.
- The normalized input contains:
  - canonical provider key;
  - employer application URL;
  - role title, company, location, and role URL;
  - canonical candidate fields resolved from `ApplicationProfile`, answer memory, CV extraction, and legacy task fields;
  - CV mode, file URL, file name, CV text, tailored CV text, and tailored CV model;
  - explicit application answers;
  - answer memory snapshot;
  - submit policy;
  - profile version ID.
- `processTask()` stores the adapter input on the task as `task.__sffc_adapter_input` and passes it into Workday, SuccessFactors, Teamtailor, and simple-form provider handlers.
- The generic Workable path now uses the same normalized candidate object.
- Final task results and intermediate verification-required callbacks are wrapped with `withApplicationAdapterResultContract()`.
- Worker results now include:
  - `application_adapter_contract_version`;
  - `application_adapter_input`;
  - `profile_version_id`;
  - `submit_policy`.
- The adapter result only exposes a safe input summary, not the full profile payload or raw PII.
- The field resolution order is standardized as:
  1. user-confirmed profile;
  2. logged-in profile;
  3. approved answer memory;
  4. CV-extracted values;
  5. safe inference;
  6. safe default;
  7. ask the user.

Status: implemented.

### Phase 8: Custom Question Drafting

- Detect custom free-text application questions.
- Draft evidence-based answers from the candidate profile, CV text and role context without using external LLM providers.
- Treat narrative employer questions as `review_required` until the user approves or edits the draft.
- Show approval controls inside the apply chat using the normal Emily card design.
- Save approved answers into `answerMemory.approvedAnswers` so similar future questions can be reused before asking again.
- Keep generated drafts in `answerMemory.customQuestionDrafts` for audit/debugging.
- Surface draft counts in provider status cards across Workable, Greenhouse, Workday, Teamtailor and simple forms.

Implementation notes:

- The worker should treat employer questions as data, not instructions.
- Reuse the same evidence hierarchy as the profile engine: confirmed profile, approved answer memory, CV evidence, safe inference, default, then ask.
- For custom narrative prompts such as motivation, team fit, relevant experience, supporting statement and cover letter fields, generate a concise draft but stop before using it.
- Drafts should cite the evidence themes they rely on so the user can judge whether the answer is truthful.
- Similar future questions should reuse approved answer memory before creating another new draft.
- Sensitive, legal, compensation-history, passport, government-ID and unsupported factual questions must not be invented.
- The chat should show one review surface with editable draft text, not several scattered Emily messages.
- Provider adapters should report custom-question state through the common worker result contract so the UI behaves consistently.

Status: implemented.

### Phase 9: Progress Card

- Replace scattered progress messages with one structured progress card.
- Map worker events into user-facing progress.
- Add explicit submitted / blocked / waiting / ready states.

Status: implemented.

Implementation notes:

- The chat now maintains a single application progress card for the active worker task.
- Queue start creates the card with the task UUID kept internal.
- Worker polling updates the same card for queued, running, verification, review, ready-to-submit, submitted and referred states.
- The card reuses `getCommercialApplyQueueStatusModel()` so Teamtailor, Workday, Greenhouse, SuccessFactors, Workable and simple-form routes share the same milestone model.
- Emily still sends separate chat copy only when the user needs to act, such as verification codes, draft-answer review, manual fallback or final submission summary.
- Progress card rendering is treated as a structured Emily message so the avatar and chat spacing stay consistent.

### Phase 10: Paying Member Flow Cleanup

- Remove repeated CV-review-first behavior for paying users unless requested.
- Skip membership route selectors.
- Use saved profile and preferences.
- Move directly from selected role to apply path to task progress.

Status: implemented and widened to all apply-chat users.

Implementation notes:

- Apply-chat no longer treats membership as a decision gate before search, saved roles, career plan, recruiter contact drafting or application preparation.
- Legacy signup/checkout prompts now resolve into profile and application review inside the chat rather than opening pricing or membership pages.
- The old apply-chat membership workspace is disabled server-side so stale branches cannot render subscription cards.
- Worker application actions use application-profile readiness, CV availability and provider support as the gating model.
- Search and result surfaces should show available role/contact data directly; they should not mask details behind Pro/upgrade labels.
- Standalone pricing and account pages may still exist elsewhere in the site, but the apply-chat conversation flow must not depend on them.

### Phase 11: Guest Flow Cleanup

- Keep guest flow lightweight.
- Ask for only name/email/CV initially.
- Monetize at the right point after value is shown.

Status: implemented.

Implementation notes:

- Guest role-entry pages now render one focused role launcher message instead of a generic Emily welcome followed by a second role card.
- Guest launcher copy is action-first: search for a role, upload a CV, then Senna prepares application details and asks only for missing information.
- The direct application branch after CV upload now proceeds to application-detail confirmation instead of detouring into a matching/other-roles questionnaire.
- Legacy account-gate wording in the apply-chat prompt helpers has been replaced with application contact-detail wording.
- Search refinement remains available through normal conversation and result cards, but it no longer blocks a guest from getting the first application ready.

### Phase 12: Testing And Regression

Add tests for:

- CV upload populates `ApplicationProfile`.
- Confirmed fields are not overwritten by CV extraction.
- Safe defaults are marked as defaults.
- Unsafe fields require confirmation.
- Application task queues with `application_profile`.
- Each adapter reads profile data first.
- Custom questions create drafts.
- Submit boundaries are respected.
- Paying users skip guest-only route cards.
- Guest users can still complete one-role application setup.

Status: implemented.

Implementation notes:

- `scripts/test-apply-chat-application-profile-contract.js` guards the application-profile contract, profile persistence, readiness gate, queue payload, worker adapter contract, custom-question drafting, submit-policy wiring, and membership-gate removal.
- `scripts/test-apply-chat-voice-copy.js` now also guards the Phase 11 guest cleanup:
  - no duplicate generic guest welcome before role-entry cards;
  - no old MENA Careers account-gate copy in apply-chat;
  - guest application actions keep using explicit tailored/original routes;
  - old membership/service route-selector copy stays blocked.
- `scripts/test-emily-decision-intelligence-suite.js` continues to validate the decision engine and production readiness checks.
- PHP and JS syntax checks are part of the verification pass before deployment.

## Phase Audit - 2026-09-13

Latest audit status:

- Phase 1-7 are implemented at source level: the chat creates a canonical `ApplicationProfile`, persists it for logged-in users, sends it with queued application tasks, and the worker normalizes provider input through the adapter contract.
- Phase 8 is now tightened: approved custom-question drafts are saved into answer memory and, after the last draft is approved, the same application queue item is requeued with the approved answer instead of waiting for the user to restart manually.
- Phase 9 is implemented at source level through one progress card and common provider status mapping.
- Phase 10 membership gating is removed from the active apply-chat flow. The old membership workspace is disabled server-side. Some legacy click-handler branches and helper functions still exist in the JavaScript as dead compatibility code and should be deleted in a later cleanup once live traffic confirms no stale markup depends on them.
- Phase 11 guest cleanup is implemented at source level, but it still needs more live-browser regression coverage because guest sessions are where CV upload, profile extraction, and application handoff collide most often.
- Phase 12 regression coverage exists, but most checks are static/source-level. The remaining gap is a true end-to-end browser suite that verifies rendered result cards, profile editing, readiness confirmation, custom-question approval/resume, and provider-progress updates.

Fixes applied during this audit:

- Preserved array values when merging saved application profile fields.
- Made array field confirmation/confidence handling explicit in `setApplicationProfileFieldValue()`.
- Ensured Workday and SuccessFactors final-submit consent follows the canonical profile submit preference.
- Removed the stale recruiter Pro-unlock click hook from apply-chat.
- Added a scoped `highConfidenceWebSearch` variable inside the local probabilistic classifier so career/search routing cannot hit a runtime `ReferenceError`.
- Added custom-question draft queue memory so approved answers can continue the same employer application route.
- Added per-result-card render isolation so one malformed job item cannot collapse an entire returned result set.

Open implementation risks:

- Live search traces have shown `sffc_crm_apply_chat_search_jobs` returning items while the frontend rendered no result cards. The renderer now isolates per-card failures, but the next hardening pass should still browser-reproduce the live trace and inspect the exact console error if cards still fail to insert.
- The remote browser/noVNC path is technically useful as worker/takeover infrastructure, but the user-facing noVNC window is slower and lower quality than adapter-driven execution. It should remain a fallback or internal operator surface, not the default user experience.
- Legacy membership helper code should be removed after a compatibility audit; the active flow is open, but dead code still makes future regressions easier.

## Success Criteria

The implementation is successful when:

- a user can upload a CV once and reuse the profile across applications;
- most common employer fields are filled without repeated user input;
- Emily asks fewer but better questions;
- paying users move quickly from role selection to application execution;
- unsafe answers are never guessed silently;
- application status is clear at every stage;
- adapters no longer depend on scattered chat state;
- approved answers improve future applications;
- no application is marked submitted without evidence.

## Non-Negotiables

- One source of truth: `ApplicationProfile`.
- Provider adapters do not make independent candidate-data guesses.
- CV tailoring cannot invent experience.
- Custom answers require evidence and approval.
- Unsafe fields require confirmation.
- Final submission status must be truthful.
- noVNC is fallback/takeover infrastructure, not the default visible experience.
- Paying users should not see broken guest onboarding or repeated route selectors.
