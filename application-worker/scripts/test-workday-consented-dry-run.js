import fs from "node:fs/promises";
import os from "node:os";
import path from "node:path";

process.env.SFFC_WORKER_ALLOW_WORKDAY_ACCOUNT_CREATION = "1";
process.env.SFFC_WORKER_ALLOW_FINAL_SUBMIT = "1";
process.env.SFFC_WORKER_INTERCEPT_FINAL_SUBMIT = "1";
process.env.SFFC_WORKER_DISABLE_VERIFICATION_CALLBACK = "1";
process.env.SFFC_BROWSER_NAVIGATION_TIMEOUT_MS = process.env.SFFC_BROWSER_NAVIGATION_TIMEOUT_MS || "120000";
process.env.SFFC_BROWSER_LAUNCH_TIMEOUT_MS = process.env.SFFC_BROWSER_LAUNCH_TIMEOUT_MS || "120000";
process.env.SFFC_WORKDAY_FETCH_TIMEOUT_MS = process.env.SFFC_WORKDAY_FETCH_TIMEOUT_MS || "20000";
process.env.SFFC_WORKDAY_SCHEMA_FETCH_TIMEOUT_MS = process.env.SFFC_WORKDAY_SCHEMA_FETCH_TIMEOUT_MS || "5000";
process.env.SFFC_WORKDAY_SHELL_TIMEOUT_MS = process.env.SFFC_WORKDAY_SHELL_TIMEOUT_MS || "10000";

const { processTask } = await import("../src/worker.js");

const roleUrl =
  process.env.SFFC_WORKDAY_TEST_URL ||
  "https://blackstone.wd1.myworkdayjobs.com/en-US/Blackstone_Careers/details/Assistant-Vice-President---Valuation_41559";
const cvPath =
  process.env.SFFC_WORKDAY_TEST_CV ||
  "/Users/ropafadzoyasheushe/Downloads/CVs/67164a2630fde.pdf";
const candidateName = process.env.SFFC_WORKDAY_TEST_NAME || "Yassine Touati";
const candidateEmail = process.env.SFFC_WORKDAY_TEST_EMAIL || "rossvltn@gmail.com";
const candidatePhone = process.env.SFFC_WORKDAY_TEST_PHONE || "+447552771926";
const candidateAddressLine1 = process.env.SFFC_WORKDAY_TEST_ADDRESS_LINE_1 || "111 Victoria Rd";
const candidateCity = process.env.SFFC_WORKDAY_TEST_CITY || "Darlington";
const candidatePostalCode = process.env.SFFC_WORKDAY_TEST_POSTAL_CODE || "DL1 5JH";
const accountRoute = process.env.SFFC_WORKDAY_TEST_ACCOUNT_ROUTE === "sign_in" ? "sign_in" : "create";
const generatedPassword =
  process.env.SFFC_WORKDAY_TEST_PASSWORD ||
  `SffcWd!${Date.now()}${Math.random().toString(36).slice(2, 8)}`;

function assert(condition, message, details = {}) {
  if (!condition) {
    const error = new Error(message);
    error.details = details;
    throw error;
  }
}

async function main() {
  const timeoutMs = Number(process.env.SFFC_WORKDAY_CONSENTED_TIMEOUT_MS || 150000);
  const watchdog = setTimeout(() => {
    console.error(`Consented Workday dry-run timed out after ${timeoutMs}ms.`);
    process.exit(124);
  }, timeoutMs);
  const stat = await fs.stat(cvPath).catch(() => null);
  assert(stat && stat.isFile(), "The consented Workday test CV path does not exist.", { cvPath });

  const task = {
    task_uuid: `local-workday-${Date.now()}`,
    provider: "workday",
    application_url: roleUrl,
    application_workspace_url: roleUrl,
    role_title: "Assistant Vice President - Valuation",
    company_name: "Blackstone",
    candidate_name: candidateName,
    candidate_email: candidateEmail,
    candidate_phone: candidatePhone,
    cv_file_url: cvPath,
    cv_file_name: path.basename(cvPath),
    payload: {
      source: "local_consented_workday_dry_run",
      consent: "candidate_provided_workday_test_details",
      workday_consent: {
        scope: "candidate_provided_workday_test_details",
        account_route: accountRoute,
        create_account: accountRoute === "create",
        sign_in: accountRoute === "sign_in",
        final_submit: true,
        captured_at: new Date().toISOString(),
      },
      workday_account: {
        account_route: accountRoute,
        create_account: accountRoute === "create",
        sign_in: accountRoute === "sign_in",
        email: candidateEmail,
        password: generatedPassword,
        allow_generated_password: accountRoute === "create",
      },
      application_answers: {
        "How Did You Hear About Us?": process.env.SFFC_WORKDAY_TEST_SOURCE || "Other",
        "Country Phone Code": process.env.SFFC_WORKDAY_TEST_COUNTRY_PHONE_CODE || "United Kingdom (+44)",
        "Phone Number": process.env.SFFC_WORKDAY_TEST_LOCAL_PHONE || "+447552771926",
        Country: process.env.SFFC_WORKDAY_TEST_COUNTRY || "United Kingdom",
        "Family Name": "Touati",
        ...(candidateAddressLine1 ? { "Address Line 1": candidateAddressLine1, Address: candidateAddressLine1 } : {}),
        ...(candidateCity ? { "City or Town": candidateCity, City: candidateCity } : {}),
        ...(candidatePostalCode ? { "Postal Code": candidatePostalCode } : {}),
      },
      application_schema: {},
    },
  };

  const startedAt = new Date().toISOString();
  const result = await processTask(task);
  const report = {
    ok: true,
    started_at: startedAt,
    finished_at: new Date().toISOString(),
    candidate_name: candidateName,
    candidate_email: candidateEmail,
    account_route: accountRoute,
    password_length: generatedPassword.length,
    password_saved_in_report: false,
    role_url: roleUrl,
    cv_path: cvPath,
    result,
  };
  const outputDir = await fs.mkdtemp(path.join(os.tmpdir(), "sffc-workday-consented-"));
  const reportPath = path.join(outputDir, "workday-consented-dry-run-report.json");
  await fs.writeFile(reportPath, JSON.stringify(report, null, 2));

  console.log(
    JSON.stringify(
      {
        ok: true,
        report_path: reportPath,
        status: result.status,
        form_opened: Boolean(result.form_opened),
        form_ready: Boolean(result.form_ready),
        verification_required: Boolean(result.verification_required),
        uploaded_resume: Boolean(result.uploaded_resume),
        application_answers_filled: result.application_answers_filled || 0,
        application_answers_attempted: result.application_answers_attempted || 0,
        missing_required_fields: result.missing_required_fields || [],
        last_error: result.last_error || "",
        screenshot: result.local_screenshot_path || "",
      },
      null,
      2
    )
  );
  clearTimeout(watchdog);
}

main().catch((error) => {
  console.error(error.message);
  if (error.details) {
    console.error(JSON.stringify(error.details, null, 2));
  }
  process.exit(1);
});
