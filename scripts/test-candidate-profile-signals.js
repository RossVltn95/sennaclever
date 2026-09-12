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
  ["profile signal cache key", "candidateProfileSignalsCacheKey"],
  ["profile signal cache value", "candidateProfileSignalsCacheValue"],
  ["builder function", "function buildCandidateProfileSignals(options)"],
  ["management extraction", "function extractCandidateProfileManagementSignals"],
  ["language extraction", "function extractCandidateProfileLanguages"],
  ["blocked family helper", "function getCandidateProfileBlockedFamilies"],
  ["confidence helper", "function buildCandidateProfileConfidence"],
  ["shared runtime export", "buildCandidateProfileSignals: buildCandidateProfileSignals"],
  ["test hook", "buildCandidateProfileSignals: function (options)"],
  ["current title", "currentTitle"],
  ["current employer", "currentEmployer"],
  ["role family", "roleFamily"],
  ["seniority", "seniorityLevel"],
  ["total months", "totalExperienceMonths"],
  ["recent relevant months", "recentRelevantMonths"],
  ["relevant months by family", "relevantMonthsByFamily"],
  ["recent months by family", "recentRelevantMonthsByFamily"],
  ["management signals", "managementSignals"],
  ["core skills", "coreSkills"],
  ["domain skills", "domainSkills"],
  ["locations", "locations"],
  ["languages", "languages"],
  ["qualifications", "qualifications"],
  ["blocked families", "blockedFamilies"],
  ["overall confidence", "overallConfidence"],
].forEach(([label, needle]) => assertIncludes(label, needle));

[
  /people_management/,
  /budget_or_financial_ownership/,
  /stakeholder_management/,
  /strategic_ownership/,
  /Arabic/,
  /English/,
  /human_resources/,
  /investment/,
  /credit/,
].forEach((pattern) => {
  if (!pattern.test(source)) {
    throw new Error(`Missing candidate-profile signal pattern: ${pattern}`);
  }
});

console.log("Candidate profile signal model wiring checks passed.");
