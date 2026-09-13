#!/usr/bin/env node

const fs = require("fs");
const path = require("path");

const root = path.resolve(__dirname, "..");
const sourcePath = path.join(
  root,
  "assets/js/crm/crm-apply-chat-article.js"
);
const planPath = path.join(
  root,
  "docs/emily-mathematical-meaning-engine-plan.md"
);
const source = fs.readFileSync(sourcePath, "utf8");
const plan = fs.readFileSync(planPath, "utf8");
const failures = [];

function requireSourceMarker(marker) {
  if (!source.includes(marker)) {
    failures.push(`missing_source_marker:${marker}`);
  }
}

function requirePlanMarker(marker) {
  if (!plan.includes(marker)) {
    failures.push(`missing_plan_marker:${marker}`);
  }
}

function getFunctionBlock(functionName) {
  const start = source.indexOf(`function ${functionName}`);
  if (start === -1) {
    failures.push(`missing_function:${functionName}`);
    return "";
  }
  const nextFunction = source.indexOf("\n    function ", start + 1);
  return source.slice(start, nextFunction === -1 ? undefined : nextFunction);
}

function requireInFunction(functionName, marker) {
  const block = getFunctionBlock(functionName);
  if (block && !block.includes(marker)) {
    failures.push(`${functionName}_missing:${marker}`);
  }
}

[
  "function buildEmilyMeaningDebugReport",
  "function compactEmilyDebugDecision",
  "function buildEmilyMeaningDebugSummary",
  "function getEmilyMeaningDebugContext",
  "debugEmilyMeaning: function (value)",
  "debugEmilyMeaningWithService: function (value)",
  "window.sffcDebugEmilyMeaning = function (value)",
  "window.sffcDebugEmilyMeaningWithService = function (value)",
  "window.sffcDebugEmilyMeaningObject = function (value)",
].forEach(requireSourceMarker);

[
  "local",
  "final",
  "recent",
  "conversationDecisionLog.slice(-8)",
  "emilyRuntimeSafetyTelemetryLog.slice(-12)",
  "conversationTurnAuditLog.slice(-12)",
].forEach((marker) => requireInFunction("buildEmilyMeaningDebugReport", marker));

[
  "primaryIntent",
  "actionType",
  "routeKey",
  "rewrittenQueries",
  "shouldSearchJobs",
  "shouldSearchWeb",
  "shouldAskClarification",
].forEach((marker) => requireInFunction("buildEmilyMeaningDebugSummary", marker));

[
  "endpointConfigured",
  "selectedRole",
  "activeTask",
  "jobSearchContext",
  "serviceContext",
].forEach((marker) => requireInFunction("getEmilyMeaningDebugContext", marker));

[
  "Status: complete.",
  "window.sffcDebugEmilyMeaning(",
  "window.sffcDebugEmilyMeaningWithService(",
  "window.sffcDebugEmilyMeaningObject",
].forEach(requirePlanMarker);

if (failures.length) {
  console.error("FAIL Emily observability wiring", failures);
  process.exit(1);
}

console.log("PASS Emily observability diagnostics are wired and documented");
