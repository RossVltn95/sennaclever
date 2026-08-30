process.env.SFFC_WORKER_ALLOW_FINAL_SUBMIT ||= "1";
process.env.SFFC_WORKER_INTERCEPT_FINAL_SUBMIT ||= "1";

const { processTask } = await import("../src/worker.js");

const WORKABLE_URL =
  process.env.SFFC_WORKABLE_TEST_URL ||
  "https://apply.workable.com/qiddiya-investment-company-1/j/75DCF9FFBC/apply/";

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
    { name: "CA_49991", label: "Current Location", type: "text", required: true },
    { name: "CA_50334", label: "Date of Birth", type: "text", required: true },
    { name: "CA_50380", label: "Expected Salary", type: "text", required: true },
    { name: "CA_35796", label: "Current monthly salary", type: "text", required: true },
    {
      name: "CA_50967",
      label: "Salutation",
      type: "select",
      required: true,
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

const task = {
  task_uuid: "workable-qiddiya-dry-run",
  provider: "workable",
  application_url: WORKABLE_URL,
  application_workspace_url: WORKABLE_URL,
  candidate_name: "Luca Valentino Rosati",
  candidate_email: "workable-dry-run@example.com",
  candidate_phone: "+447911123456",
  cv_file_name: "sffc-workable-test-cv.pdf",
  cv_file_url:
    "data:application/pdf;base64," +
    Buffer.from(
      "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n4 0 obj\n<< /Length 93 >>\nstream\nBT /F1 12 Tf 72 720 Td (Luca Valentino Rosati - FP&A and investment analysis CV) Tj ET\nendstream\nendobj\nxref\n0 5\n0000000000 65535 f \n0000000009 00000 n \n0000000058 00000 n \n0000000115 00000 n \n0000000204 00000 n \ntrailer\n<< /Root 1 0 R /Size 5 >>\nstartxref\n347\n%%EOF\n"
    ).toString("base64"),
  payload: {
    application_schema: schema,
    application_answers: {
      address: "Dubai, United Arab Emirates",
      phone: "+447911123456",
      CA_35795: "Bachelor Degree",
      CA_46626: "No",
      CA_35810: "No",
      CA_49991: "Dubai, UAE",
      CA_50334: "01/01/1990",
      CA_50380: "SAR 45000",
      CA_35796: "SAR 35000",
      CA_50967: "Mr",
      CA_50968: "United Kingdom",
      CA_33138: "8",
      CA_50017: "No",
      CA_46634: "Yes",
      519266: "Yes",
      519267: "Yes",
    },
  },
  cover_letter_requested: 0,
};

const result = await processTask(task);
console.log(JSON.stringify(result, null, 2));

if (result.status !== "dry_run_ready") {
  throw new Error(`Expected dry_run_ready, got ${result.status}: ${result.last_error || ""}`);
}
if (!result.clicked_submit) {
  throw new Error("Expected the worker to click the final submit button.");
}
if (!result.intercepted_submit_request) {
  throw new Error("Expected final application submit request to be intercepted.");
}
if (/cdn-cgi\/challenge-platform|cloudflare/i.test(result.intercepted_submit_request.url || "")) {
  throw new Error("Intercepted Cloudflare challenge instead of application submit.");
}
if ((result.missing_required_fields || []).length) {
  throw new Error(`Required fields still missing: ${result.missing_required_fields.join("; ")}`);
}
