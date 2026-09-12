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
  ["default weights", "function getEmilyLearningToRankDefaultWeights()"],
  ["feature clamp", "function clampEmilyRankFeature(value)"],
  ["feature vector", "function buildEmilyLearningToRankFeatureVector(item, matchEntry, context)"],
  ["feature scoring", "function scoreEmilyLearningToRankFeatures(features, weights)"],
  ["score wrapper", "function buildEmilyLearningToRankScore(item, matchEntry, context)"],
  ["primary ranking integration", "learningRank = buildEmilyLearningToRankScore(item, matchEntry"],
  ["supplemental ranking integration", "var learningRank = buildEmilyLearningToRankScore(item, matchEntry"],
  ["diagnostic hook", "buildEmilyLearningToRankScore: function (item)"],
  ["model id", "local_weighted_ltr_v1"],
  ["structured fit feature", "structuredFit"],
  ["family match feature", "familyMatch"],
  ["title feature", "titleSimilarity"],
  ["skill feature", "skillMatch"],
  ["seniority feature", "seniorityMatch"],
  ["experience feature", "experienceFit"],
  ["location feature", "locationMatch"],
  ["freshness feature", "freshness"],
  ["route feature", "routeQuality"],
  ["query feature", "queryIntent"],
  ["penalty feature", "penalties"],
].forEach(([label, needle]) => assertIncludes(label, needle));

[
  /structuredFit:\s*0\.34/,
  /familyMatch:\s*0\.13/,
  /titleSimilarity:\s*0\.11/,
  /skillMatch:\s*0\.11/,
  /seniorityMatch:\s*0\.08/,
  /experienceFit:\s*0\.08/,
  /rankingScore = learningRank\.score \* 0\.72/,
  /requiredYearsStatus === "underqualified"/,
].forEach((pattern) => {
  if (!pattern.test(source)) {
    throw new Error(`Missing learning-to-rank pattern: ${pattern}`);
  }
});

console.log("Emily learning-to-rank wiring checks passed.");
