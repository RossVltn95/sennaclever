const expectRealSubmit = process.env.SFFC_WORKABLE_EXPECT_REAL_SUBMIT === "1";
process.env.SFFC_WORKER_ALLOW_FINAL_SUBMIT ||= "1";
process.env.SFFC_WORKER_INTERCEPT_FINAL_SUBMIT ||= expectRealSubmit ? "0" : "1";
process.env.SFFC_WORKABLE_TWO_TAB_FLOW ||= "1";
process.env.SFFC_WORKABLE_HUMAN_PACE ||= "1";
process.env.SFFC_WORKABLE_CLAUDE_ANSWERS ||= "1";

import fs from "node:fs/promises";
import path from "node:path";

const { processTask } = await import("../src/worker.js");

const WORKABLE_URL =
  process.env.SFFC_WORKABLE_TEST_URL ||
  "https://apply.workable.com/qiddiya-investment-company-1/j/F2F2483923/apply/";

async function getCvFixture() {
  const cvPath = process.env.SFFC_WORKABLE_CV_PATH || "";
  if (cvPath) {
    const buffer = await fs.readFile(cvPath);
    const mime = cvPath.toLowerCase().endsWith(".pdf") ? "application/pdf" : "application/octet-stream";
    return {
      fileName: path.basename(cvPath),
      dataUrl: `data:${mime};base64,${buffer.toString("base64")}`,
    };
  }

  return {
    fileName: "sffc-workable-test-cv.pdf",
    dataUrl:
      "data:application/pdf;base64," +
      Buffer.from(
        "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n4 0 obj\n<< /Length 93 >>\nstream\nBT /F1 12 Tf 72 720 Td (Luca Valentino Rosati - FP&A and investment analysis CV) Tj ET\nendstream\nendobj\nxref\n0 5\n0000000000 65535 f \n0000000009 00000 n \n0000000058 00000 n \n0000000115 00000 n \n0000000204 00000 n \ntrailer\n<< /Root 1 0 R /Size 5 >>\nstartxref\n347\n%%EOF\n"
      ).toString("base64"),
  };
}

async function getOptionalFileFixture(filePath, fallbackMime = "application/octet-stream") {
  if (!filePath) {
    return null;
  }
  const buffer = await fs.readFile(filePath);
  const lowerPath = filePath.toLowerCase();
  const mime = lowerPath.endsWith(".png")
    ? "image/png"
    : lowerPath.endsWith(".jpg") || lowerPath.endsWith(".jpeg")
      ? "image/jpeg"
      : fallbackMime;
  return {
    fileName: path.basename(filePath),
    dataUrl: `data:${mime};base64,${buffer.toString("base64")}`,
  };
}

function getApplicationAnswers() {
  const candidatePhone = process.env.SFFC_WORKABLE_CANDIDATE_PHONE || "+447911123456";
  const chatLikeAnswers = {
    address: "Dubai, United Arab Emirates",
    phone: candidatePhone,
  };
  const legacyFullAnswers = {
    ...chatLikeAnswers,
    CA_35795: "Bachelor Degree",
    CA_46626: "No",
    CA_35810: "No",
    CA_49900: "No",
    CA_49991: "Dubai, UAE",
    CA_50334: "01/01/1990",
    CA_50380: "18000",
    CA_35796: "18000",
    CA_50967: "Mr",
    CA_50968: "United Kingdom",
    CA_33138: "8",
    CA_50017: "No",
    CA_46634: "Yes",
    519266: "Yes",
    519267: "Yes",
  };
  const mode = String(process.env.SFFC_WORKABLE_TEST_ANSWER_MODE || "chat").toLowerCase();
  const baseAnswers = mode === "full" ? legacyFullAnswers : mode === "none" ? {} : chatLikeAnswers;

  if (!process.env.SFFC_WORKABLE_TEST_ANSWERS_JSON) {
    return baseAnswers;
  }

  return {
    ...baseAnswers,
    ...JSON.parse(process.env.SFFC_WORKABLE_TEST_ANSWERS_JSON),
  };
}

const schema = {
  provider: "workable",
  hosted_url: WORKABLE_URL,
  application_embed_url: WORKABLE_URL,
  questions: [
    { name: "firstname", label: "First Name", type: "text", required: true },
    { name: "lastname", label: "Last Name", type: "text", required: true },
    { name: "email", label: "Email", type: "email", required: true },
    { name: "phone", label: "Phone", type: "tel", required: true },
    { name: "address", label: "Address", type: "text", required: true },
    { name: "resume", label: "Resume/CV", type: "file", required: true },
    {
      name: "CA_35795",
      label: "Highest Education Level",
      type: "radio",
      required: true,
      options: ["Highschool", "Diploma", "Bachelor Degree", "Masters", "PhD", "Other"],
    },
    {
      name: "CA_46626",
      label:
        "Do you, or any immediate family member or close personal associate, have a relationship with a politically exposed person?",
      type: "radio",
      required: true,
      options: ["Yes", "No"],
    },
    {
      name: "CA_35810",
      label: "Do you have any conflict of interest or previous relationship that should be declared?",
      type: "radio",
      required: true,
      options: ["Yes", "No", "I don't know"],
    },
    {
      name: "CA_49900",
      label: "Do you have any social media accounts such as Twitter, Facebook, LinkedIn, TikTok, YouTube or other platforms?",
      type: "text",
      required: true,
    },
    { name: "CA_49991", label: "Current Location", type: "text", required: true },
    { name: "CA_50334", label: "Date of Birth", type: "text", required: false },
    { name: "CA_50380", label: "Expected Salary", type: "text", required: false },
    { name: "CA_35796", label: "Current monthly salary", type: "text", required: false },
    {
      name: "CA_50967",
      label: "Salutation",
      type: "select",
      required: false,
      options: ["Mr", "Mrs", "Ms", "Miss", "Dr"],
    },
    {
      name: "CA_50968",
      label: "Nationality Field",
      type: "select",
      required: true,
      options: ["Saudi Arabia", "United Arab Emirates", "United Kingdom", "United States"],
    },
    { name: "CA_33138", label: "Years of relevant experience", type: "text", required: true },
    {
      name: "CA_50017",
      label: "Are you currently involved or working directly for Qiddiya either through a delivery partner or as a consultant?",
      type: "radio",
      required: true,
      options: ["Yes", "No"],
    },
    {
      name: "CA_46634",
      label: "Do you agree to Qiddiya processing your application data?",
      type: "radio",
      required: true,
      options: ["Yes", "No"],
    },
    {
      name: "519266",
      label: "I certify that the information provided is true and complete.",
      type: "checkbox",
      required: true,
      options: ["Yes"],
    },
    {
      name: "519267",
      label: "I agree to the privacy policy and application terms.",
      type: "checkbox",
      required: true,
      options: ["Yes"],
    },
  ],
};

const cvFixture = await getCvFixture();
const photoFixture = await getOptionalFileFixture(process.env.SFFC_WORKABLE_PHOTO_PATH || "", "image/jpeg");

const task = {
  task_uuid: "workable-qiddiya-dry-run",
  provider: "workable",
  application_url: WORKABLE_URL,
  application_workspace_url: WORKABLE_URL,
  role_url: WORKABLE_URL,
  role_title:
    process.env.SFFC_WORKABLE_ROLE_TITLE ||
    "Director, Finance & Asset Performance Film Studios",
  company_name: process.env.SFFC_WORKABLE_COMPANY_NAME || "Qiddiya Investment Company",
  candidate_name: process.env.SFFC_WORKABLE_CANDIDATE_NAME || "Luca Valentino Rosati",
  candidate_email: process.env.SFFC_WORKABLE_CANDIDATE_EMAIL || "workable-dry-run@example.com",
  candidate_phone: process.env.SFFC_WORKABLE_CANDIDATE_PHONE || "+447911123456",
  cv_file_name: cvFixture.fileName,
  cv_file_url: cvFixture.dataUrl,
  photo_file_name: photoFixture?.fileName || "",
  photo_file_url: photoFixture?.dataUrl || "",
  payload: {
    page_url: process.env.SFFC_WORKABLE_PAGE_URL || "https://joinsenna.com/shallow/",
    role_url: WORKABLE_URL,
    source: "apply_chat",
    consent: "admin_clicked_workable_test_worker",
    application_schema: schema,
    application_answers: getApplicationAnswers(),
    candidate_profile: {
      location:
        process.env.SFFC_WORKABLE_PROFILE_LOCATION ||
        process.env.SFFC_WORKABLE_CANDIDATE_LOCATION ||
        "Dubai, United Arab Emirates",
      notice_period: process.env.SFFC_WORKABLE_NOTICE_PERIOD || "1 month",
      current_salary_monthly: process.env.SFFC_WORKABLE_CURRENT_SALARY || "18000",
      expected_salary: process.env.SFFC_WORKABLE_EXPECTED_SALARY || "18000",
    },
  },
  cover_letter_requested: 0,
};

const result = await processTask(task);
console.log(JSON.stringify(result, null, 2));

const browserDiagnostics = result.browser_diagnostics || {};
const workableTwoTab = browserDiagnostics.workable_two_tab || {};
const workableAnswerPlan = browserDiagnostics.workable_answer_plan || {};
const answerMode = String(process.env.SFFC_WORKABLE_TEST_ANSWER_MODE || "chat").toLowerCase();

console.log(
  JSON.stringify(
    {
      verification_summary: {
        answer_mode: answerMode,
        two_tab_enabled: Boolean(workableTwoTab.enabled),
        first_tab_prepared: Boolean(workableTwoTab.first_tab_prepared),
        first_tab_uploaded_resume: Boolean(workableTwoTab.first_tab_uploaded_resume),
        first_tab_core_fields: Boolean(workableTwoTab.first_tab_core_fields),
        second_tab_ready: Boolean(workableTwoTab.second_tab_ready),
        answer_plan_present: Boolean(Object.keys(workableAnswerPlan).length),
        answer_plan_generated: workableAnswerPlan.generated_count || 0,
        answer_plan_unresolved: workableAnswerPlan.unresolved_count || 0,
        claude_enabled: Boolean(workableAnswerPlan.claude_enabled),
        claude_available: Boolean(workableAnswerPlan.claude_available),
        uploaded_resume: Boolean(result.uploaded_resume),
        answers: `${result.application_answers_filled || 0}/${result.application_answers_attempted || 0}`,
        choices: `${result.application_choice_answers_filled || 0}/${result.application_choice_answers_attempted || 0}`,
        status: result.status,
        reached_submit_stage: Boolean(result.reached_submit_stage),
        challenge_detected: Boolean(result.challenge_detected),
        challenge_provider: result.challenge_provider || "",
      },
    },
    null,
    2
  )
);

if (!workableTwoTab.enabled) {
  throw new Error("Expected Workable two-tab flow to be enabled.");
}
if (!workableTwoTab.first_tab_prepared) {
  throw new Error("Expected Workable first tab to be prepared before opening the second tab.");
}
if (!workableTwoTab.second_tab_ready) {
  throw new Error("Expected Workable second tab to load the application form.");
}
if (!Object.keys(workableAnswerPlan).length) {
  throw new Error("Expected Workable answer planner diagnostics in browser_diagnostics.workable_answer_plan.");
}
if (answerMode !== "full" && !workableAnswerPlan.generated_count) {
  throw new Error("Expected the latest Workable answer planner to generate answers from the chat-like payload.");
}

if (expectRealSubmit) {
  if (result.status !== "submitted") {
    throw new Error(`Expected submitted, got ${result.status}: ${result.last_error || ""}`);
  }
  if (!result.submission_confirmed) {
    throw new Error("Expected Workable to show a submission confirmation.");
  }
  process.exit(0);
}

if (!result.clicked_submit) {
  throw new Error("Expected the worker to click the final submit button.");
}
const applicationMissingFields = (result.missing_required_fields || []).filter(
  (field) => !/hatelove|feedback|survey/i.test(String(field || ""))
);
if (applicationMissingFields.length) {
  throw new Error(`Required fields still missing: ${applicationMissingFields.join("; ")}`);
}
const reachedSubmitStage = Boolean(result.reached_submit_stage || (result.clicked_submit && result.uploaded_resume));
const stoppedByChallenge = Boolean(
  reachedSubmitStage &&
    result.challenge_detected &&
    /cloudflare/i.test(String(result.challenge_provider || result.last_error || ""))
);
if (stoppedByChallenge) {
  console.log("Workable reached submit stage; Cloudflare stopped the final application POST.");
  process.exit(0);
}
if (result.status !== "dry_run_ready") {
  throw new Error(`Expected dry_run_ready, got ${result.status}: ${result.last_error || ""}`);
}
if (!result.intercepted_submit_request) {
  throw new Error("Expected final application submit request to be intercepted.");
}
if (/cdn-cgi\/challenge-platform|cloudflare/i.test(result.intercepted_submit_request.url || "")) {
  throw new Error("Intercepted Cloudflare challenge instead of application submit.");
}
