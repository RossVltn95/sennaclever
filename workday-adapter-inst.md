What is still missing before it works properly:

1. No real Workday account payload from the chat
   The worker expects payload.workday_account / payload.workday_consent, but the current chat/PHP queue flow does not send those fields. So for Blackstone-style Workday tenants, it will always stop at
   the account gate.

2. No proper Workday email verification flow
   The worker can detect “verification required”, but it does not persist a browser session, resume the same Workday flow, or fill a Workday verification code after the user provides it. The current
   verification endpoint is generic/Greenhouse-shaped and not enough for Workday.

3. No Workday-specific application schema extraction
   We are not yet reading Workday’s actual application/questionnaire structure. That means questions like sponsorship, source, country, privacy terms, custom questionnaires, diversity questions, and
   dropdowns are not reliably known before the browser hits them.

4. Field filling is too generic
   Workday uses custom React components, search dropdowns, radio groups, checkboxes, country pickers, address widgets, and multi-step forms. The current code fills only basic candidate fields plus
   generic answers. That will fail often.

5. Resume upload is basic
   It uploads to the first visible file input, but does not reliably verify Workday parsed the CV, attached the file, or moved past resume parsing. This needs Workday-specific confirmation checks.

6. Step navigation is blind
   advanceWorkdaySteps() loops up to 8 times and clicks Next/Continue/Review. It does not yet understand Workday stages like resume, my information, application questions, voluntary disclosures,
   review, submit.

7. Final submit detection is not Workday-specific
   clickLikelyApplyButton() is generic. It may click a submit button, but it does not robustly detect Workday’s submit request, validation errors, or confirmation state.

8. No real tenant test beyond the account wall
   Blackstone correctly classifies as account-gated, but we have not tested a consented account through account creation/sign-in, CV upload, questions, review page, and dry-run submit interception.

The minimum next build to make this real is:

1. Add Workday consent/account capture in the chat UI.
2. Pass workday_account and workday_consent through ajax_crm_apply_chat_queue_application_task().
3. Add Workday session persistence so verification can resume.
4. Build a Workday form-state extractor that returns current step, required fields, controls, labels, options, and validation errors.
5. Replace the blind step loop with a Workday state machine.
6. Add dry-run final submit interception for Workday before allowing real submits.
