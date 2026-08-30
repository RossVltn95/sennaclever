#!/usr/bin/env node
const fs = require("fs");
const path = require("path");
const { execFileSync } = require("child_process");

const cvDir = process.argv[2] || "/Users/ropafadzoyasheushe/Downloads/CVs";
const jdPath =
  process.argv[3] || "/Users/ropafadzoyasheushe/Downloads/Job descriptions.md";
const uploadBookDir =
  process.argv[4] || "/Users/ropafadzoyasheushe/Documents/UPLOADS-BOOK";

function clean(text) {
  return String(text || "")
    .replace(/[’]/g, "'")
    .replace(/[^\S\r\n]+/g, " ")
    .replace(/\s+\n/g, "\n")
    .replace(/\n{3,}/g, "\n\n")
    .trim();
}

function normalize(text) {
  return clean(text).toLowerCase().replace(/&/g, " and ");
}

function hasRoleKeyword(text) {
  return /\b(?:analyst|associate|assistant|manager|director|consultant|intern|officer|controller|accountant|auditor|banker|specialist|advisor|adviser|vice president|vp|principal|executive|lawyer|graduate|scientist|programmer|engineer|developer|procurement|treasury|compliance|customer service|supply chain|logistics|credit|risk|finance|investment|strategy|trader|trading|partner|portfolio management|data analyst|business analyst|data scientist|human resources|supervisor|chief|project manager)\b/i.test(
    clean(text)
  );
}

function hasCoreRoleKeyword(text) {
  return /\b(?:analyst|associate|assistant|manager|director|consultant|intern|officer|controller|accountant|auditor|banker|specialist|advisor|adviser|vice president|vp|principal|executive|lawyer|graduate|scientist|programmer|engineer|developer|procurement|treasury|compliance|customer service|supply chain|logistics|trader|trading|partner|data scientist|supervisor|chief|project manager)\b/i.test(
    clean(text)
  );
}

function hasRoleTitleNoun(text) {
  return /\b(?:analyst|associate|assistant|manager|director|consultant|advisor|adviser|specialist|officer|banker|accountant|auditor|controller|engineer|developer|principal|avp|vp|vice president|intern|internship|trainee|graduate|executive|partner|supervisor|chief)\b/i.test(
    clean(text)
  );
}

function looksLikeBlockedCvFragment(text) {
  const value = normalize(text);
  return (
    /^(?:education|academic background|qualifications?|certifications?|professional qualifications?|career objectives?|objective|profile summary|professional summary|summary|personal statement|personal profile|relevant experience|work experience|professional experience|employment history|skills summary|skills|projects|languages|personal details|area of interest)\b/i.test(
      value
    ) ||
    /\b(?:bsc|msc|mba|ba|ma|b\.com|m\.com|bachelor'?s?|master'?s?|diploma|coursework|module|modules|dissertation|thesis|secondary education|school of|college|university)\b/i.test(
      value
    )
  );
}

function isUsefulQuery(query) {
  const value = normalize(query);
  const words = value.split(/\s+/).filter(Boolean);
  if (!value || value.length < 3) return false;
  if (
    /(?:^|\b)(?:19|20)\d{2}\b|(?:jan|feb|mar|apr|may|jun|jul|aug|sep|sept|oct|nov|dec)[a-z]*\s+(?:19|20)?\d{2}\b|\b(?:present|current)\b/i.test(
      value
    ) &&
    !hasCoreRoleKeyword(value)
  ) {
    return false;
  }
  if (
    /^(?:education|academic|academics|school|schools|college|university|universities|degree|bachelor(?:'s|s)? degree|master(?:'s|s)? degree|msc|bsc|ba|ma|qualification|qualifications|certification|certifications|professional qualifications|skills|technical skills|other skills|experience|relevant experience|work experience|professional experience|employment history|projects|personal projects|profile|personal profile|summary|professional summary|personal statement|objective|career objective|contact|languages|interests|references)$/i.test(
      value
    )
  ) {
    return false;
  }
  if (
    words.length === 1 &&
    /^(?:education|academic|student|member|team|coursework|project|projects|exam|merit|grade|school|university|college|degree|qualification|qualifications|certification|certifications|language|languages|skill|skills|experience|profile|statement|summary|finance|investment|analysis|management)$/i.test(
      value
    )
  ) {
    return false;
  }
  if (
    /\b(?:university|college|school|msc|bsc|ba|ma|bachelor|master|diploma|coursework|module|modules|exam|education|qualification|certification|certificate|language|languages)\b/i.test(
      value
    ) &&
    !hasRoleKeyword(value)
  ) {
    return false;
  }
  if (
    /\b(?:road|street|avenue|drive|lane|london|dubai|riyadh|doha|abu dhabi|singapore|united kingdom|united arab emirates|saudi arabia|qatar|email|phone|linkedin|www\.|https?)\b/i.test(
      value
    ) &&
    !hasCoreRoleKeyword(value)
  ) {
    return false;
  }
  return true;
}

function normalizeRoleQuery(line) {
  let value = clean(line)
    .replace(/\b(?:team member|member|candidate|student)\b/gi, " ")
    .replace(/\s*\/\s*/g, " ")
    .replace(/\s+/g, " ")
    .trim();
  if (!value || looksLikeBlockedCvFragment(value) || !hasRoleTitleNoun(value)) {
    return "";
  }
  const match = value.match(
    /\b(?:(?:junior|senior|lead|assistant|associate|executive|investment|financial|business|research|credit|risk|portfolio|technical|quantitative|data|finance|corporate|strategy|operations|compliance|project|product|fund|management|commercial|customer|success)\s+){0,4}(?:analyst|associate|manager|director|consultant|advisor|adviser|specialist|officer|banker|accountant|auditor|controller|engineer|developer|principal|avp|vp|vice president|intern|internship|trainee)\b/i
  );
  if (match && match[0]) {
    value = clean(match[0]);
  } else if (!hasRoleTitleNoun(value)) {
    return "";
  } else if (value.split(/\s+/).length > 5) {
    return "";
  }
  if (/^(?:[-–—/:;|]+|\W{2,})/.test(value)) {
    return "";
  }
  if (
    /\b(?:worked|working|responsible|supported|assisted|managed|led|developed|created|prepared|reported|collaborated|communicated|skilled|passionate|keen|experience|expertise|currently|seeking|targeting)\b/i.test(
      value
    )
  ) {
    return "";
  }
  return isUsefulQuery(value) ? value : "";
}

function extractRoleQueries(text) {
  const lines = clean(text)
    .split(/\r?\n/)
    .map(clean)
    .filter(Boolean)
    .slice(0, 140);
  const queries = [];
  const normalizedText = normalize(text);
  if (
    /\b(?:investment fund|portfolio|equities|stocks|asset pricing|bloomberg|dcf|m and a|valuation|financial modelling|financial modeling|corporate finance|investment analysis|monte carlo|capital markets)\b/i.test(
      normalizedText
    )
  ) {
    queries.push("investment analyst", "financial analyst");
  }
  if (
    /\b(?:credit valuation|credit risk|credit analyst|credit underwriting|structured credit|fixed income)\b/i.test(
      normalizedText
    )
  ) {
    queries.push("credit analyst");
  }
  if (
    /\b(?:portfolio management|asset management|fund performance|investment mandate|aum)\b/i.test(
      normalizedText
    )
  ) {
    queries.push("portfolio analyst", "asset management analyst");
  }
  lines.forEach((line, index) => {
    const window = lines
      .slice(Math.max(0, index - 4), Math.min(lines.length, index + 5))
      .join(" ");
    const nearExperience =
      /\b(?:experience|employment|work history|internship|investment fund|professional experience|relevant experience)\b/i.test(
        window
      );
    if (
      looksLikeBlockedCvFragment(line) ||
      (!nearExperience &&
        !/\b(?:analyst|associate|manager|director|intern|consultant|advisor|specialist|officer|banker|accountant|auditor|project manager)\b/i.test(
          line
        ))
    ) {
      return;
    }
    const query = normalizeRoleQuery(line);
    if (query && !queries.map(normalize).includes(normalize(query))) {
      queries.push(query);
    }
  });
  if (!queries.length) {
    queries.push("financial analyst", "investment analyst");
  }
  return queries.slice(0, 6);
}

function extractPdf(file) {
  try {
    return execFileSync("pdftotext", [file, "-"], {
      encoding: "utf8",
      maxBuffer: 1024 * 1024 * 8,
    });
  } catch (error) {
    return "";
  }
}

function extractDocx(file) {
  try {
    const xml = execFileSync("unzip", ["-p", file, "word/document.xml"], {
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
  } catch (error) {
    return "";
  }
}

function loadCvTexts() {
  return fs
    .readdirSync(cvDir)
    .filter((name) => /\.(?:pdf|docx)$/i.test(name))
    .map((name) => {
      const file = path.join(cvDir, name);
      return {
        name,
        text: /\.docx$/i.test(name) ? extractDocx(file) : extractPdf(file),
      };
    })
    .filter((item) => normalize(item.text).length > 80);
}

function parseCsvRows(text, limit = 4) {
  const rows = [];
  let row = [];
  let value = "";
  let quoted = false;
  for (let i = 0; i < text.length; i += 1) {
    const ch = text[i];
    const next = text[i + 1];
    if (quoted && ch === '"' && next === '"') {
      value += '"';
      i += 1;
    } else if (ch === '"') {
      quoted = !quoted;
    } else if (!quoted && ch === ",") {
      row.push(value);
      value = "";
    } else if (!quoted && (ch === "\n" || ch === "\r")) {
      if (ch === "\r" && next === "\n") i += 1;
      row.push(value);
      if (row.some(Boolean)) rows.push(row);
      row = [];
      value = "";
      if (rows.length > limit) break;
    } else {
      value += ch;
    }
  }
  return rows;
}

function loadUploadBookJobs(maxFiles = 1000, perFile = 10) {
  if (!fs.existsSync(uploadBookDir)) return [];
  const files = fs
    .readdirSync(uploadBookDir)
    .filter((name) => /\.csv$/i.test(name))
    .slice(0, maxFiles);
  const jobs = [];
  files.forEach((file) => {
    const rows = parseCsvRows(
      fs.readFileSync(path.join(uploadBookDir, file), "utf8"),
      perFile + 1
    );
    if (rows.length < 2) return;
    const header = rows[0].map(normalize);
    rows.slice(1, perFile + 1).forEach((row) => {
      const get = (...names) => {
        for (const name of names) {
          const index = header.indexOf(normalize(name));
          if (index >= 0 && row[index]) return row[index];
        }
        return "";
      };
      const title = get("title", "job title", "role");
      const description = get(
        "description",
        "job description",
        "responsibilities front end"
      );
      if (normalize(title + " " + description).length > 80) {
        jobs.push({ file, title, description });
      }
    });
  });
  return jobs;
}

function queryHasJobMatchSignal(query, job) {
  const q = normalize(query);
  const title = normalize(job.title || "");
  const description = normalize(job.description || "");
  return (
    (title && title.includes(q)) ||
    q
      .split(/\s+/)
      .filter((word) => word.length > 3)
      .some((word) => title.includes(word) || description.includes(word))
  );
}

const forbidden = /\b(?:education|personal profile|personal statement|skills|projects|languages|qualifications|university|college|school|msc finance|bsc business|king's college|date|present|road|street|email|phone|linkedin)\b/i;
const sentenceLeak = /\b(?:worked|working|responsible|supported|assisted|managed|led|developed|created|prepared|reported|collaborated|communicated|skilled|passionate|keen|experience|expertise|currently|seeking|targeting)\b/i;
const cvs = loadCvTexts();
const uploadJobs = loadUploadBookJobs();
const failures = [];
const qualityFailures = [];
const samples = [];
let focusCv = null;

cvs.forEach((cv) => {
  const queries = extractRoleQueries(cv.text);
  const bad = queries.find(
    (query) =>
      forbidden.test(query) ||
      sentenceLeak.test(query) ||
      query.split(/\s+/).length > 5 ||
      !hasRoleTitleNoun(query)
  );
  if (!queries.length || bad) {
    failures.push({ cv: cv.name, queries });
  }
  if (cv.name === "67164a0f476b8.pdf") {
    const first = normalize(queries[0] || "");
    if (!/^(?:investment analyst|financial analyst|portfolio analyst|asset management analyst)$/.test(first)) {
      qualityFailures.push({ cv: cv.name, reason: "finance CV should not start with ambiguous technical/education query", queries });
    }
  }
  if (samples.length < 12) {
    samples.push({ cv: cv.name, queries: queries.slice(0, 4) });
  }
  if (cv.name === "67164a0f476b8.pdf") {
    focusCv = { cv: cv.name, queries: queries.slice(0, 8) };
  }
});

let jdCount = 0;
if (fs.existsSync(jdPath)) {
  jdCount = fs
    .readFileSync(jdPath, "utf8")
    .split(/\n---+\n/g)
    .filter((item) => normalize(item).length > 80).length;
}

let uploadBookPairs = 0;
let uploadBookMatched = 0;
for (const cv of cvs.slice(0, 80)) {
  const queries = extractRoleQueries(cv.text);
  for (const job of uploadJobs) {
    uploadBookPairs += 1;
    if (queries.some((query) => queryHasJobMatchSignal(query, job))) {
      uploadBookMatched += 1;
    }
  }
}

console.log(
  JSON.stringify(
    {
      cvs_tested: cvs.length,
      job_descriptions_seen: jdCount,
      upload_book_jobs_sampled: uploadJobs.length,
      upload_book_query_pairs: uploadBookPairs,
      upload_book_pairs_with_query_signal: uploadBookMatched,
      failures: failures.length,
      quality_failures: qualityFailures.length,
      focus_cv: focusCv,
      samples,
      failed_samples: failures.slice(0, 10),
      quality_failed_samples: qualityFailures.slice(0, 10),
    },
    null,
    2
  )
);

if (failures.length || qualityFailures.length) {
  process.exit(1);
}
