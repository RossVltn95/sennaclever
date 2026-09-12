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

function requireInFunction(functionName, marker) {
  const start = source.indexOf(`function ${functionName}`);
  if (start === -1) {
    failures.push(`missing_function:${functionName}`);
    return;
  }
  const nextFunction = source.indexOf("\n    function ", start + 1);
  const block = source.slice(start, nextFunction === -1 ? undefined : nextFunction);
  if (!block.includes(marker)) {
    failures.push(`${functionName}_missing:${marker}`);
  }
}

[
  "function scoreEmilyRoutes",
  "function getEmilyDecisionDeterministicRouteSignal",
  "function getEmilyDecisionContextFit",
  "function getEmilyDecisionMemoryContinuity",
  "function getEmilyDecisionWorkflowSafety",
  "function getEmilyDecisionRoutePenalty",
  "context.routeEnsemble = safeScoreEmilyRoutes",
  "route_ensemble:",
  "routeEnsemble:",
  "scoreEmilyRoutes: function",
].forEach(requireSourceMarker);

[
  "deterministic * 0.3",
  "bayes * 0.25",
  "semantic * 0.2",
  "contextFit * 0.1",
  "memoryContinuity * 0.08",
  "workflowSafety * 0.07",
  "penalty",
  "confidenceBand",
  "candidates.slice(0, 6)",
].forEach((marker) => requireInFunction("scoreEmilyRoutes", marker));

[
  "apply_without_role",
  "apply_without_cv",
  "cv_task_without_cv",
  "role_task_without_role",
  "route_conflict_web_vs_jobs",
].forEach((marker) => requireInFunction("getEmilyDecisionRoutePenalty", marker));

[
  "active_prompt",
  "apply_without_role",
  "apply_without_cv",
  "sensitive_account_or_payment",
].forEach((marker) => requireInFunction("getEmilyDecisionWorkflowSafety", marker));

if (failures.length) {
  console.error("FAIL Emily route ensemble scorer", failures);
  process.exit(1);
}

console.log("PASS Emily route ensemble scorer is wired and guarded");
