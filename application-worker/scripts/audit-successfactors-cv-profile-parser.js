import { execFileSync } from "node:child_process";
import fs from "node:fs";
import path from "node:path";
import { extractSuccessFactorsProfileFromCv } from "../src/worker.js";

const cvDir = process.argv[2] || "/Users/ropafadzoyasheushe/Downloads/CVs";
const limit = Number(process.env.SFFC_SUCCESSFACTORS_CV_AUDIT_LIMIT || 12);

function extractCvText(filePath) {
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
  return "";
}

const files = fs
  .readdirSync(cvDir)
  .filter((name) => /\.(?:pdf|docx)$/i.test(name))
  .map((name) => path.join(cvDir, name))
  .filter((filePath) => fs.statSync(filePath).size > 1000)
  .slice(0, limit);

const requiredKeys = [
  "phone",
  "type_of_business",
  "company_name",
  "employment_country",
  "title",
  "school",
  "subject",
  "degree_type",
  "passing_year",
  "education_country",
];

const results = files.map((filePath) => {
  const cvText = extractCvText(filePath);
  const profile = extractSuccessFactorsProfileFromCv({
    cv_text: cvText,
    payload: {
      application_answers: {
        gender: "Male",
        marital_status: "Single",
        nationality: "Test Nationality",
      },
    },
  }, {
    phone: "",
    address: "",
  });
  const missing = requiredKeys.filter((key) => !profile[key]);
  return {
    file: path.basename(filePath),
    text_length: cvText.length,
    filled_required_from_cv: requiredKeys.length - missing.length,
    missing,
    profile,
  };
});

console.log(JSON.stringify({
  cv_dir: cvDir,
  tested: results.length,
  required_keys: requiredKeys,
  average_required_from_cv: results.length
    ? results.reduce((sum, item) => sum + item.filled_required_from_cv, 0) / results.length
    : 0,
  results,
}, null, 2));
