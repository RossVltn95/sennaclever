import fs from "node:fs/promises";
import os from "node:os";
import path from "node:path";
import puppeteer from "puppeteer";
import {
  discoverWorkdayLiveSchema,
  extractWorkdayQuestionsFromJson,
  fillApplicationAnswers,
  getRequiredFormCompletionState,
  getWorkdayStageFromState,
  getWorkdayStepState,
  uploadWorkdayResume,
} from "../src/worker.js";

function assert(condition, message, details = {}) {
  if (!condition) {
    const error = new Error(message);
    error.details = details;
    throw error;
  }
}

async function main() {
  const executablePath = process.env.PUPPETEER_EXECUTABLE_PATH || undefined;
  const browser = await puppeteer.launch({
    headless: "new",
    executablePath,
    timeout: 90000,
    args: ["--no-sandbox", "--disable-setuid-sandbox"],
  });
  const tmpDir = await fs.mkdtemp(path.join(os.tmpdir(), "sffc-workday-static-"));
  const cvPath = path.join(tmpDir, "candidate-cv.pdf");
  await fs.writeFile(cvPath, "%PDF-1.4\n% static test cv\n");

  try {
    const page = await browser.newPage();
    await page.setContent(
      `<!doctype html>
      <html>
        <body>
          <nav aria-label="Application steps">
            <ol>
              <li data-automation-id="progressStepResume" aria-current="step">Resume</li>
              <li data-automation-id="progressStepMyInformation">My Information</li>
              <li data-automation-id="progressStepQuestions">Application Questions</li>
              <li data-automation-id="progressStepReview">Review</li>
            </ol>
          </nav>
          <main>
            <h1>Resume/CV</h1>
            <section data-automation-id="resumeSection">
              <label for="resumeUpload">Upload resume/CV*</label>
              <input id="resumeUpload" data-automation-id="resumeUpload" type="file" style="display:none" required>
            </section>
            <section data-automation-id="legalNameSection">
              <label for="firstName">First Name*</label>
              <input id="firstName" data-automation-id="legalNameSection_firstName" required>
              <label for="lastName">Last Name*</label>
              <input id="lastName" data-automation-id="legalNameSection_lastName" required>
            </section>
            <section data-automation-id="sourceSection">
              <label for="source">How did you hear about us?*</label>
              <select id="source" name="source" required>
                <option value="">Select One</option>
                <option value="LinkedIn">LinkedIn</option>
                <option value="Company Website">Company Website</option>
              </select>
            </section>
            <section data-automation-id="sponsorshipSection">
              <fieldset>
                <legend>Will you now or in the future require sponsorship?*</legend>
                <label><input type="radio" name="sponsorship" value="Yes" required> Yes</label>
                <label><input type="radio" name="sponsorship" value="No" required> No</label>
              </fieldset>
            </section>
            <button data-automation-id="bottom-navigation-next-button">Next</button>
          </main>
        </body>
      </html>`,
      { waitUntil: "domcontentloaded" }
    );

    const stepState = await getWorkdayStepState(page);
    assert(stepState.active_step === "resume", "Expected Workday step detector to identify resume step.", stepState);

    const schema = await discoverWorkdayLiveSchema(page);
    const labels = schema.questions.map((question) => question.label);
    assert(labels.some((label) => /first name/i.test(label)), "Expected first name question in Workday schema.", schema);
    assert(labels.some((label) => /sponsorship/i.test(label)), "Expected sponsorship question in Workday schema.", schema);
    const apiSchemaQuestions = extractWorkdayQuestionsFromJson({
      questionnaire: {
        id: "Q-1",
        sections: [
          {
            title: "Application questions",
            questions: [
              {
                id: "source",
                questionText: "How did you hear about us?",
                required: true,
                options: [{ descriptor: "LinkedIn" }, { descriptor: "Company Website" }],
              },
              {
                referenceID: "visa",
                prompt: "Do you require sponsorship?",
                isRequired: true,
                answers: [{ label: "Yes" }, { label: "No" }],
              },
            ],
          },
        ],
      },
    });
    assert(
      apiSchemaQuestions.length >= 2,
      "Expected Workday JSON questionnaire extractor to return required questions.",
      apiSchemaQuestions
    );
    assert(
      getWorkdayStageFromState({ field_count: 4 }, stepState) === "resume",
      "Expected stage helper to prefer detected Workday resume step."
    );

    const upload = await uploadWorkdayResume(page, cvPath);
    assert(upload.confirmed, "Expected hidden Workday resume input upload to be confirmed.", upload);

    const fill = await fillApplicationAnswers(page, {
      provider: "workday",
      payload: {
        application_answers: {
          "First Name": "Latifa",
          "Last Name": "Ahli",
          "How did you hear about us?": "LinkedIn",
          "Will you now or in the future require sponsorship?": "No",
        },
      },
    }, schema);
    assert(fill.filled >= 3, "Expected Workday static form answers to be filled.", fill);

    const completion = await getRequiredFormCompletionState(page);
    assert(
      completion.missing_required_fields.length === 0,
      "Expected no missing required fields after static Workday fill.",
      completion
    );

    console.log(
      JSON.stringify(
        {
          ok: true,
          active_step: stepState.active_step,
          question_count: schema.questions.length,
          api_question_count: apiSchemaQuestions.length,
          filled: fill.filled,
          missing_required_fields: completion.missing_required_fields.length,
          resume_confirmed: upload.confirmed,
        },
        null,
        2
      )
    );
  } finally {
    await browser.close().catch(() => {});
  }
}

main().catch((error) => {
  console.error(error.message);
  if (error.details) {
    console.error(JSON.stringify(error.details, null, 2));
  }
  process.exit(1);
});
