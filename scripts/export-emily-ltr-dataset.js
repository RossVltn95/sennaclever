#!/usr/bin/env node

const fs = require("fs");
const path = require("path");

const outputPath =
  process.argv[2] ||
  path.join(__dirname, "..", "tmp", "emily-ltr-dataset-schema.json");

const schema = {
  version: 1,
  model: "local_weighted_ltr_v1",
  labels: {
    clicked: 1,
    saved: 2,
    review_fit: 2,
    tailor_cv: 3,
    applied: 4,
    dismissed: -1,
    too_junior: -2,
    too_senior: -2,
    wrong_sector: -3,
    application_succeeded: 5,
    application_failed: -2,
    application_blocked: -3,
  },
  features: [
    "structuredFit",
    "familyMatch",
    "titleSimilarity",
    "skillMatch",
    "seniorityMatch",
    "experienceFit",
    "locationMatch",
    "freshness",
    "routeQuality",
    "queryIntent",
    "cvConfidence",
    "sameFamily",
    "directTitle",
    "strongSkillCount",
    "requiredYearsStatus",
    "roleFamily",
    "candidateFamily",
    "penalties",
  ],
  rows: [],
};

fs.mkdirSync(path.dirname(outputPath), { recursive: true });
fs.writeFileSync(outputPath, JSON.stringify(schema, null, 2));
console.log(`Wrote Emily LTR dataset schema to ${outputPath}`);
