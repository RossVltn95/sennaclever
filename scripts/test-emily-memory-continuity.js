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
  "function buildConversationDecisionMemorySnapshot",
  "function getEmilyDecisionMemoryContinuity",
  "memory: buildConversationDecisionMemorySnapshot()",
  "context.decisionFeatures = buildEmilyDecisionFeatures(value, context)",
  "context.routeEnsemble = safeScoreEmilyRoutes",
  "memoryContinuity * 0.08",
  "rememberCareerConversationContextTurn",
  "recordConversationBeliefState",
].forEach(requireSourceMarker);

[
  "conversationContextState",
  "conversationContextHistory",
  "conversationSummary",
  "profileSnapshot",
  "memoryFacts",
  "canonicalState",
  "beliefState",
  "recentResultEntities",
  "currentSelectedRole",
  "pausedTasks",
].forEach((marker) =>
  requireInFunction("buildConversationDecisionMemorySnapshot", marker)
);

[
  "previousIntent",
  "getEmilyDecisionRouteForIntent(previousIntent)",
  "previousRoute && previousRoute === routeKey",
  "jobSearchContext",
  "selectedRole || context.referencedRole",
  "answer_active_prompt",
].forEach((marker) =>
  requireInFunction("getEmilyDecisionMemoryContinuity", marker)
);

[
  "hasOpenMemoryGoals",
  "previousIntent",
  "memory.conversationContextState",
].forEach((marker) => requireInFunction("buildEmilyDecisionFeatures", marker));

if (failures.length) {
  console.error("FAIL Emily memory continuity wiring", failures);
  process.exit(1);
}

console.log("PASS Emily memory continuity is wired into features and route scoring");
