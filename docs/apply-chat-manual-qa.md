# Apply Chat Manual QA

This is the live QA runbook for the unified `sffc-crm-apply-chat` flow.

## Browser Setup

1. Open Chrome normally.
2. Open `https://joinsenna.com/shallow/`.
3. Trigger apply chat from the search box or an opened job result.
4. Keep DevTools closed unless inspecting a failure screenshot.

## Automated Live Harness

Run the live harness against the open Chrome session:

```bash
node scripts/live-apply-chat-browser-test.js https://joinsenna.com/shallow/
```

With a CV upload:

```bash
SFFC_LIVE_CHAT_CV_PATH="/absolute/path/to/cv.pdf" node scripts/live-apply-chat-browser-test.js https://joinsenna.com/shallow/
```

Reports and screenshots are written to `/tmp/senna-live-apply-chat` unless `SFFC_LIVE_CHAT_REPORT_DIR` is set.

## Required Checks

- Cold open shows the new apply chat shell.
- Welcome appears once: `Hi, I’m Emily. I’ll help you search for roles, compare them against your CV, and decide what to apply for.`
- Old bilingual language gate does not appear.
- Compact welcome/trending pills appear below the welcome message.
- A career question before CV upload receives a career answer, not a forced CV/search response.
- Search can be triggered from a welcome pill or the composer.
- CV upload produces one receipt/progress sequence, not duplicate receipt messages.
- Tailored CV document card appears after analysis.
- CV score appears inside the tailored CV document card.
- Old quick insights card does not appear.
- User can choose `Use Tailored CV` or `Continue with original`.
- Cover letter preview appears after tailored CV selection.
- User can choose `Apply with cover letter` or `Continue without cover letter`.
- Employer review can open from a result card.
- Blocked embed fallback shows employer URL, provider label, open employer form, try live embed, and refresh preview where applicable.
- Salary question mid-flow receives salary-aware output.
- Interview question mid-flow receives interview-aware output.
- Emily resumes the pending task without dumping old scripted resume prompts.
- Old recruiter shortlist/member-only recruiter surface does not appear in the apply flow.
- Old managed-service CTA route does not appear.
- Composer remains visible, aligned with the message column, focusable, and usable throughout.
- No horizontal scrollbar appears in the apply chat shell.

## Pass Criteria

The final `manualQaFindings` array in `report.json` should have zero failed checks. Any failed check must include a screenshot label, and the corresponding PNG should be attached to the bug report.
