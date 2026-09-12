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
const marker = "var emilyDecisionRouteTaxonomy = ";
const start = source.indexOf(marker);
const end = source.indexOf("\n\n    function cloneEmilyDecisionRouteDefinition", start);

if (start === -1 || end === -1) {
  throw new Error("Could not locate emilyDecisionRouteTaxonomy");
}

const literal = source.slice(start + marker.length, end).trim().replace(/;$/, "");
const taxonomy = Function(`"use strict"; return (${literal});`)();

const requiredRoutes = [
  "answer_active_prompt",
  "web_search",
  "job_search",
  "search_refinement",
  "search_filter_status",
  "search_filter_reset",
  "search_results_question",
  "apply_action",
  "cv_role_comparison",
  "role_reference",
  "role_question",
  "application_status",
  "application_pause",
  "application_resume",
  "answer_career_question",
  "cv_review",
  "cv_tailoring",
  "company_research",
  "recruiter_networking",
  "salary_compensation",
  "interview_prep",
  "application_material",
  "support_payment",
  "account_issue",
  "human_takeover",
  "clarify",
];

const requiredIntentCoverage = [
  "answer_pending_question",
  "web_search",
  "job_search",
  "search_refinement",
  "search_filter_status",
  "search_filter_reset",
  "search_results_question",
  "apply_action",
  "cv_role_comparison",
  "role_reference",
  "role_question",
  "application_status",
  "application_pause",
  "application_resume",
  "career_question",
  "career_planning",
  "cv_request",
  "unknown",
];

const requiredActionCoverage = [
  "defer_to_prompt_handler",
  "web_search",
  "show_job_results",
  "update_search_preferences",
  "answer_search_filters",
  "reset_search_preferences",
  "answer_search_results_question",
  "start_apply_execution",
  "compare_selected_role_cv",
  "render_selected_role",
  "answer_role_question",
  "answer_application_status",
  "answer_directly",
  "resume_task",
  "ask_clarifying_question",
];

const failures = [];
const allIntents = new Set();
const allActions = new Set();

requiredRoutes.forEach((route) => {
  if (!taxonomy[route]) {
    failures.push(`missing_route:${route}`);
  }
});

Object.entries(taxonomy).forEach(([route, definition]) => {
  if (!Array.isArray(definition.intents) || !definition.intents.length) {
    failures.push(`route_missing_intents:${route}`);
  }
  if (!Array.isArray(definition.actions) || !definition.actions.length) {
    failures.push(`route_missing_actions:${route}`);
  }
  if (!Array.isArray(definition.requires)) {
    failures.push(`route_requires_not_array:${route}`);
  }
  if (!Array.isArray(definition.forbids)) {
    failures.push(`route_forbids_not_array:${route}`);
  }
  if (typeof definition.canInterrupt !== "boolean") {
    failures.push(`route_can_interrupt_not_boolean:${route}`);
  }
  if (typeof definition.canExecuteWithoutCv !== "boolean") {
    failures.push(`route_can_execute_without_cv_not_boolean:${route}`);
  }
  if (typeof definition.canTriggerExternalSearch !== "boolean") {
    failures.push(`route_external_search_not_boolean:${route}`);
  }
  if (typeof definition.canTriggerApplicationExecution !== "boolean") {
    failures.push(`route_application_execution_not_boolean:${route}`);
  }
  if (
    typeof definition.minimumConfidence !== "number" ||
    definition.minimumConfidence < 0 ||
    definition.minimumConfidence > 1
  ) {
    failures.push(`route_bad_minimum_confidence:${route}`);
  }
  if (!definition.fallbackPrompt) {
    failures.push(`route_missing_fallback_prompt:${route}`);
  }
  definition.intents.forEach((intent) => allIntents.add(intent));
  definition.actions.forEach((action) => allActions.add(action));
});

requiredIntentCoverage.forEach((intent) => {
  if (!allIntents.has(intent)) {
    failures.push(`missing_intent_coverage:${intent}`);
  }
});

requiredActionCoverage.forEach((action) => {
  if (!allActions.has(action)) {
    failures.push(`missing_action_coverage:${action}`);
  }
});

[
  "function getEmilyDecisionRouteDefinition",
  "function getEmilyDecisionRouteForIntent",
  "function getEmilyDecisionRouteForAction",
  "function getEmilyDecisionRouteKey",
  "getEmilyDecisionRouteTaxonomy",
  "routeMinimumConfidence",
].forEach((needle) => {
  if (!source.includes(needle)) {
    failures.push(`missing_source_marker:${needle}`);
  }
});

if (failures.length) {
  console.error("FAIL emily decision route taxonomy", failures);
  process.exit(1);
}

console.log(
  `PASS ${Object.keys(taxonomy).length} Emily decision routes cover ${allIntents.size} intents and ${allActions.size} actions`
);

