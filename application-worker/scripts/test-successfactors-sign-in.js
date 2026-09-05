import { execFileSync } from "node:child_process";
import fs from "node:fs";

process.env.SFFC_WORKER_ALLOW_FINAL_SUBMIT = process.env.SFFC_WORKER_ALLOW_FINAL_SUBMIT || "0";

const { processTask } = await import("../src/worker.js");

const testUrl = process.env.SFFC_SUCCESSFACTORS_TEST_URL || "";
const testEmail = process.env.SFFC_SUCCESSFACTORS_TEST_EMAIL || "";
const testPassword = process.env.SFFC_SUCCESSFACTORS_TEST_PASSWORD || "";
const testName = process.env.SFFC_SUCCESSFACTORS_TEST_NAME || "Yassine Touati";
const cvUrl = process.env.SFFC_SUCCESSFACTORS_TEST_CV_URL || "";
const cvPath = process.env.SFFC_SUCCESSFACTORS_TEST_CV_PATH || "";

function extractCvText(filePath) {
  if (!filePath || !fs.existsSync(filePath)) return "";
  if (/\.pdf$/i.test(filePath)) {
    try {
      return execFileSync("pdftotext", [filePath, "-"], {
        encoding: "utf8",
        maxBuffer: 1024 * 1024 * 8,
      });
    } catch {
      return "";
    }
  }
  if (/\.docx$/i.test(filePath)) {
    try {
      const xml = execFileSync("unzip", ["-p", filePath, "word/document.xml"], {
        encoding: "utf8",
        maxBuffer: 1024 * 1024 * 8,
      });
      return xml
        .replace(/<\/w:p>/g, "\n")
        .replace(/<[^>]+>/g, " ")
        .replace(/&amp;/g, "&")
        .replace(/&lt;/g, "<")
        .replace(/&gt;/g, ">")
        .replace(/\s+/g, " ");
    } catch {
      return "";
    }
  }
  try {
    return fs.readFileSync(filePath, "utf8");
  } catch {
    return "";
  }
}

function parseExtraAnswers(raw) {
  if (!raw) return {};
  try {
    const decoded = JSON.parse(raw);
    return decoded && typeof decoded === "object" ? decoded : {};
  } catch {
    return {};
  }
}

if (!testUrl || !testEmail || !testPassword || (!cvUrl && !cvPath)) {
  console.error([
    "Set SFFC_SUCCESSFACTORS_TEST_URL, SFFC_SUCCESSFACTORS_TEST_EMAIL,",
    "SFFC_SUCCESSFACTORS_TEST_PASSWORD, and either SFFC_SUCCESSFACTORS_TEST_CV_URL",
    "or SFFC_SUCCESSFACTORS_TEST_CV_PATH.",
  ].join(" "));
  process.exit(1);
}

const task = {
  task_uuid: `sf-sign-in-${Date.now()}`,
  provider: "successfactors",
  application_url: testUrl,
  candidate_name: testName,
  candidate_email: testEmail,
  candidate_phone: process.env.SFFC_SUCCESSFACTORS_TEST_PHONE || "",
  cv_file_url: cvUrl || `file://${cvPath}`,
  cv_file_name: process.env.SFFC_SUCCESSFACTORS_TEST_CV_NAME || "cv.pdf",
  cv_text: process.env.SFFC_SUCCESSFACTORS_TEST_CV_TEXT || extractCvText(cvPath),
  payload: {
    successfactors_consent: {
      account_route: "sign_in",
      scope: "consented_successfactors_sign_in_test",
    },
    successfactors_account: {
      account_route: "sign_in",
      sign_in: true,
      email: testEmail,
      password: testPassword,
    },
    application_answers: parseExtraAnswers(process.env.SFFC_SUCCESSFACTORS_TEST_ANSWERS || "{}"),
  },
};

try {
  const result = await processTask(task);
  console.log(JSON.stringify({ result }, null, 2));
} catch (error) {
  console.log(JSON.stringify({
    error: error && error.stack ? error.stack : String(error),
  }, null, 2));
  process.exit(1);
}
