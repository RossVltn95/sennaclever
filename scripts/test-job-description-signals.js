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
  ["parser function", "function parseJobDescriptionSignals(item)"],
  ["signal cache key", "jobDescriptionSignalsCacheKey"],
  ["signal cache value", "jobDescriptionSignalsCacheValue"],
  ["profile integration", "jobDescriptionSignals: jobSignals"],
  ["test hook", "parseJobDescriptionSignals: function (item)"],
  ["required years", "requiredYearsMin"],
  ["preferred years", "preferredYears"],
  ["required skills", "requiredSkills"],
  ["preferred skills", "preferredSkills"],
  ["role families", "roleFamilies"],
  ["seniority level", "seniorityLevel"],
  ["remote mode", "remoteMode"],
  ["visa signals", "visaSignals"],
  ["management signals", "managementSignals"],
  ["compensation", "compensation"],
  ["deal breakers", "dealBreakers"],
  ["industry domains", "industryDomains"],
  ["experience parser reuse", "extractRoleExperienceRequirement(text)"],
  ["role family reuse", "inferCvRoleFamilies("],
  ["skill extraction reuse", "extractSearchSkillSignals(text)"],
].forEach(([label, needle]) => assertIncludes(label, needle));

[
  /\bRecruitment\b/,
  /\bPayroll\b/,
  /\bFinancial Modelling\b/,
  /\bDue Diligence\b/,
  /\bCredit Analysis\b/,
  /\bProject Management\b/,
  /\bSQL\b/,
  /\bPower BI\b/,
  /visa_sponsorship_available/,
  /no_visa_sponsorship/,
  /relocation_support/,
  /people_management/,
  /work_authorization_required/,
].forEach((pattern) => {
  if (!pattern.test(source)) {
    throw new Error(`Missing job-description signal pattern: ${pattern}`);
  }
});

console.log("Job description signal parser wiring checks passed.");
