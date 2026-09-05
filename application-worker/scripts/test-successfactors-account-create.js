import crypto from "node:crypto";

process.env.SFFC_WORKER_ALLOW_SUCCESSFACTORS_ACCOUNT_CREATION =
  process.env.SFFC_WORKER_ALLOW_SUCCESSFACTORS_ACCOUNT_CREATION || "1";
process.env.SFFC_WORKER_ALLOW_FINAL_SUBMIT = process.env.SFFC_WORKER_ALLOW_FINAL_SUBMIT || "0";

const { processTask } = await import("../src/worker.js");

const testUrl = process.env.SFFC_SUCCESSFACTORS_TEST_URL || "";
const testEmail = process.env.SFFC_SUCCESSFACTORS_TEST_EMAIL || "";
const testName = process.env.SFFC_SUCCESSFACTORS_TEST_NAME || "Yassine Touati";
const cvUrl = process.env.SFFC_SUCCESSFACTORS_TEST_CV_URL || "";
const cvPath = process.env.SFFC_SUCCESSFACTORS_TEST_CV_PATH || "";
const password =
  process.env.SFFC_SUCCESSFACTORS_TEST_PASSWORD ||
  `Sf!${crypto.randomBytes(3).toString("hex")}${String(Date.now()).slice(-4)}`;

if (!testUrl || !testEmail || (!cvUrl && !cvPath)) {
  console.error([
    "Set SFFC_SUCCESSFACTORS_TEST_URL, SFFC_SUCCESSFACTORS_TEST_EMAIL, and either",
    "SFFC_SUCCESSFACTORS_TEST_CV_URL or SFFC_SUCCESSFACTORS_TEST_CV_PATH.",
  ].join(" "));
  process.exit(1);
}

const task = {
  task_uuid: `sf-create-${Date.now()}`,
  provider: "successfactors",
  application_url: testUrl,
  candidate_name: testName,
  candidate_email: testEmail,
  candidate_phone: process.env.SFFC_SUCCESSFACTORS_TEST_PHONE || "",
  cv_file_url: cvUrl || `file://${cvPath}`,
  cv_file_name: process.env.SFFC_SUCCESSFACTORS_TEST_CV_NAME || "cv.pdf",
  payload: {
    successfactors_consent: {
      account_route: "create",
      create_account: true,
      scope: "consented_successfactors_account_creation_test",
    },
    successfactors_account: {
      account_route: "create",
      create_account: true,
      email: testEmail,
      password,
      first_name: testName.split(/\s+/).slice(0, -1).join(" ") || testName,
      last_name: testName.split(/\s+/).slice(-1).join(""),
      allow_generated_password: false,
    },
    application_answers: {},
  },
};

try {
  const result = await processTask(task);
  console.log(JSON.stringify({
    generated_password: password,
    result,
  }, null, 2));
} catch (error) {
  console.log(JSON.stringify({
    generated_password: password,
    error: error && error.stack ? error.stack : String(error),
  }, null, 2));
  process.exit(1);
}
