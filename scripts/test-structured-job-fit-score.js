#!/usr/bin/env node

const fs = require("fs");
const path = require("path");

const root = path.resolve(__dirname, "..");
const sourcePath = path.join(
  root,
  "assets/js/crm/crm-apply-chat-article.js"
);
const source = fs.readFileSync(sourcePath, "utf8");

function assertIncludes(label, needle) {
  if (!source.includes(needle)) {
    throw new Error(`Missing ${label}: ${needle}`);
  }
}

[
  ["structured scorer", "function buildStructuredJobFitScore("],
  ["fit band", "function getStructuredFitBand(score)"],
  ["freshness score", "function getStructuredJobFitFreshnessScore(item)"],
  ["route score", "function getStructuredJobFitApplicationRouteScore(item, roleProfile)"],
  ["family override", "function hasExplicitStructuredFitFamilyOverride(familyKey)"],
  ["reason formatter", "function formatStructuredFitReasonCode(code)"],
  ["risk formatter", "function formatStructuredFitRiskCode(code)"],
  ["candidate profile use", "buildCandidateProfileSignals({ cvOnly: true })"],
  ["fit score output", "fitScore"],
  ["interview band output", "interviewProbabilityBand"],
  ["reason code output", "reasonCodes"],
  ["risk output", "risks"],
  ["penalty output", "penalties"],
  ["component output", "componentScores"],
  ["match integration", "structuredFit: structuredFit"],
  ["reason integration", "reasonCodes: structuredFit ? structuredFit.reasonCodes : []"],
  ["risk integration", "risks: structuredFit ? structuredFit.risks : []"],
].forEach(([label, needle]) => assertIncludes(label, needle));

[
  /0\.22/,
  /0\.18/,
  /0\.16/,
  /0\.14/,
  /0\.12/,
  /0\.08/,
  /0\.05/,
  /0\.03/,
  /0\.02/,
  /blocked_role_family_mismatch/,
  /under_required_experience_by_3_plus_years/,
  /seniority_mismatch/,
  /specialist_mismatch/,
  /core_requirement_evidence_weak/,
  /same_role_family/,
  /required_years_met/,
  /strong_skill_overlap/,
].forEach((pattern) => {
  if (!pattern.test(source)) {
    throw new Error(`Missing structured fit pattern: ${pattern}`);
  }
});

console.log("Structured job fit scoring wiring checks passed.");
