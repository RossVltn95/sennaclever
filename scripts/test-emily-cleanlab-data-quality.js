#!/usr/bin/env node

const childProcess = require("child_process");
const fs = require("fs");
const os = require("os");
const path = require("path");

const root = path.resolve(__dirname, "..");
const fixturePath = path.join(
  os.tmpdir(),
  `emily-data-quality-fixture-${process.pid}.json`
);
const reportPath = path.join(
  os.tmpdir(),
  `emily-data-quality-report-${process.pid}.json`
);
const markdownPath = path.join(
  os.tmpdir(),
  `emily-data-quality-report-${process.pid}.md`
);

const events = [
  {
    seq: 1,
    type: "decision",
    message: "best recruitment agencies in Dubai",
    decision: {
      chosenRoute: "job_search",
      actionType: "show_job_results",
      confidence: 0.41,
      topRoutes: [
        { route: "job_search", calibratedProbability: 0.51 },
        { route: "web_search", calibratedProbability: 0.48 },
      ],
    },
  },
  {
    seq: 2,
    type: "job_dismiss",
    role: {
      title: "HR Recruitment Manager",
      company: "Example Co",
      fitScore: 82,
    },
  },
  {
    seq: 3,
    type: "job_save",
    role: {
      title: "Credit Analyst",
      company: "Example Bank",
      fitScore: 21,
    },
  },
  {
    seq: 4,
    type: "job_impression",
    role: {
      title: "",
      company: "",
    },
  },
];

fs.writeFileSync(fixturePath, JSON.stringify(events, null, 2));

childProcess.execFileSync(
  process.execPath,
  [
    path.join(root, "scripts/audit-emily-cleanlab-data-quality.js"),
    fixturePath,
    "--out",
    reportPath,
    "--markdown",
    markdownPath,
  ],
  { stdio: "pipe" }
);

const report = JSON.parse(fs.readFileSync(reportPath, "utf8"));
const issueCodes = new Set(report.reviewQueue.map((issue) => issue.code));

[
  "low_confidence_decision",
  "ambiguous_route_margin",
  "possible_web_search_mislabel",
  "dismissed_high_fit_job",
  "positive_engagement_low_fit",
  "incomplete_job_impression",
].forEach((code) => {
  if (!issueCodes.has(code)) {
    throw new Error(`Missing expected data-quality issue: ${code}`);
  }
});

if (!Array.isArray(report.cleanlabRows) || report.cleanlabRows.length !== 4) {
  throw new Error("Cleanlab handoff rows were not exported correctly.");
}

if (!fs.readFileSync(markdownPath, "utf8").includes("Human Review Queue")) {
  throw new Error("Markdown review queue was not generated.");
}

console.log("Emily Cleanlab-style data quality audit checks passed.");
