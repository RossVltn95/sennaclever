#!/usr/bin/env node

const fs = require("fs");
const path = require("path");

const jsPath = path.join(
  __dirname,
  "..",
  "assets",
  "js",
  "crm",
  "crm-apply-chat-article.js"
);
const evalPath = path.join(__dirname, "evaluate-emily-route-calibration.js");
const source = fs.readFileSync(jsPath, "utf8");
const evalSource = fs.readFileSync(evalPath, "utf8");
const failures = [];

function requireMarker(haystack, marker, label) {
  if (!haystack.includes(marker)) {
    failures.push(`missing_${label}:${marker}`);
  }
}

[
  "function getEmilyDecisionCalibrationBand",
  "function calibrateEmilyRouteScore",
  "calibratedProbability",
  "calibrationBand",
  "shouldClarify",
  "very_high",
  "high",
  "medium",
  "ambiguous",
  "unsafe",
  "calibrateEmilyRouteScore: function",
].forEach((marker) => requireMarker(source, marker, "runtime_marker"));

[
  "brierScore",
  "logLoss",
  "confusion",
  "buckets",
  "misses",
  "getCalibrationBand",
].forEach((marker) => requireMarker(evalSource, marker, "evaluation_marker"));

if (failures.length) {
  console.error("FAIL Emily route calibration", failures);
  process.exit(1);
}

console.log("PASS Emily route calibration helpers and evaluator are wired");
