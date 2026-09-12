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
const source = fs.readFileSync(jsPath, "utf8");

const failures = [];

function requireSourceMarker(marker) {
  if (!source.includes(marker)) {
    failures.push(`missing_source_marker:${marker}`);
  }
}

function requireInFeatureBuilder(marker) {
  const start = source.indexOf("function buildEmilyDecisionFeatures");
  const end = source.indexOf("\n\n    function getApplyChatUserTier", start);
  if (start === -1 || end === -1) {
    failures.push("missing_feature_builder_block");
    return;
  }
  const block = source.slice(start, end);
  if (!block.includes(marker)) {
    failures.push(`feature_builder_missing:${marker}`);
  }
}

[
  "function pushEmilyDecisionFeatureFlag",
  "function buildEmilyDecisionFeatures",
  "context.decisionFeatures = buildEmilyDecisionFeatures(value, context)",
  "decisionFeatures: context.decisionFeatures",
  "featureFlags:",
  "routeHintFlags:",
  "riskFeatureFlags:",
  "getEmilyDecisionFeatures",
].forEach(requireSourceMarker);

[
  "schemaVersion: 1",
  "text:",
  "context:",
  "semantic:",
  "routeHints:",
  "risk:",
  "normalized:",
  "wordCount:",
  "detectedIntent:",
  "preferenceFeedback:",
  "likelyExternalKnowledge:",
  "likelyInternalJobSearch:",
  "blocksApplicationExecution:",
  "route_conflict_web_vs_jobs",
  "apply_without_cv",
  "cv_task_without_cv",
  "sensitive_account_or_payment",
  "parseMessageSemantics(clean)",
  "extractMeaningFrame(clean)",
  "classifySearchPreferenceFeedback(clean)",
].forEach(requireInFeatureBuilder);

if (failures.length) {
  console.error("FAIL emily decision features", failures);
  process.exit(1);
}

console.log("PASS Emily decision feature extraction is wired into decisions");
