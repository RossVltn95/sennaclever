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
  ["event log state", "var emilyOutcomeEventLog = []"],
  ["event sequence state", "var emilyOutcomeEventSeq = 0"],
  ["impression dedupe state", "var emilyOutcomeImpressionKeys = {}"],
  ["workflow state helper", "function getEmilyOutcomeWorkflowState()"],
  ["decision sanitizer", "function sanitizeEmilyOutcomeDecision(decision)"],
  ["role sanitizer", "function sanitizeEmilyOutcomeRole(item, match)"],
  ["event logger", "function logEmilyOutcomeEvent(type, payload)"],
  ["decision logger", "function logEmilyDecisionOutcome(value, decision, validation, mode)"],
  ["job event logger", "function logEmilyJobOutcomeEvent(type, item, match, extra)"],
  ["impression logger", "function logEmilyJobResultImpressions(items, source)"],
  ["decision hook", "logEmilyDecisionOutcome(value, decision, validation, mode);"],
  ["impression hook", "logEmilyJobResultImpressions(visibleItems, \"apply_results\");"],
  ["browser event log", "window.__sffcEmilyOutcomeEventLog"],
  ["browser latest event", "window.__sffcEmilyOutcomeEventLatest"],
].forEach(([label, needle]) => assertIncludes(label, needle));

[
  "decision",
  "job_impression",
  "job_review_fit",
  "job_tailor_cv",
  "job_original_cv",
  "job_save",
  "job_dismiss",
  "job_apply",
  "user_feedback",
].forEach((eventType) => {
  assertIncludes(`${eventType} event`, `"${eventType}"`);
});

[
  "schemaVersion: 1",
  "conversationId: Number(applyChatConversationId || 0) || 0",
  "userTier: getApplyChatUserTier()",
  "workflowState: getEmilyOutcomeWorkflowState()",
  "outcome: \"shown\"",
  "outcome: \"selected\"",
  "outcome: \"attempted\"",
].forEach((needle) => assertIncludes(`schema field ${needle}`, needle));

console.log("Emily outcome logging wiring checks passed.");
