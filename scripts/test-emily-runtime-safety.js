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
  ["runtime safety config", "runtimeSafetyEnabled: config.emilyRuntimeSafetyEnabled !== false"],
  ["probability kill switch", "disableProbabilisticRouting:"],
  ["semantic kill switch", "disableSemanticRouting:"],
  ["route ensemble kill switch", "disableRouteEnsemble:"],
  ["web search kill switch", "disableWebSearch:"],
  ["application execution kill switch", "disableApplicationExecution:"],
  ["strict prompt protection", "strictPromptProtection:"],
  ["active prompt override threshold", "activePromptOverrideConfidence: 0.88"],
  ["runtime telemetry state", "var emilyRuntimeSafetyTelemetryLog = []"],
  ["telemetry helper", "function recordEmilyRuntimeSafetyTelemetry(type, details)"],
  ["disabled route signal", "function buildDisabledEmilyRouteSignal(reason)"],
  ["safe probability wrapper", "function safeClassifyEmilyDecisionRouteProbability(value, features, config)"],
  ["safe semantic wrapper", "function safeClassifyEmilyDecisionRouteSemantic(value, features, config)"],
  ["safe ensemble wrapper", "function safeScoreEmilyRoutes(features, context, probability, semantic)"],
  ["probability wrapper use", "context.routeProbability = safeClassifyEmilyDecisionRouteProbability("],
  ["semantic wrapper use", "context.routeSemantic = safeClassifyEmilyDecisionRouteSemantic("],
  ["ensemble wrapper use", "context.routeEnsemble = safeScoreEmilyRoutes("],
  ["probabilistic intent disabled", "probabilistic_intent_disabled"],
  ["web search blocked", "web_search_kill_switch"],
  ["application execution blocked", "application_execution_kill_switch"],
  ["prompt low-confidence block", "active_prompt_low_confidence_override"],
  ["prompt probabilistic apply block", "probabilistic_prompt_apply_override_blocked"],
  ["ambiguous ensemble block", "route_ensemble_requires_clarification"],
  ["cv fit without cv block", "cv_fit_without_cv"],
  ["probabilistic application block", "probabilistic_application_execution_blocked"],
  ["application without cv block", "application_execution_without_cv"],
  ["application without role block", "application_execution_without_role"],
  ["debug hook", "debugEmilyDecisionRoute: function (value, context)"],
  ["runtime config hook", "getEmilyDecisionRuntimeSafetyConfig: function ()"],
  ["telemetry hook", "getEmilyRuntimeSafetyTelemetryLog: function ()"],
].forEach(([label, needle]) => assertIncludes(label, needle));

[
  /if\s*\(\s*config\.runtimeSafetyEnabled[\s\S]*config\.disableWebSearch[\s\S]*actionType === "web_search"/,
  /if\s*\(\s*config\.runtimeSafetyEnabled[\s\S]*config\.disableApplicationExecution[\s\S]*actionType === "start_apply_execution"/,
  /actionType === "start_apply_execution"[\s\S]*source === "local_probabilistic"/,
  /actionType === "compare_selected_role_cv"[\s\S]*!hasApplyChatCvAvailable\(\)/,
  /routeEnsemble[\s\S]*routeEnsemble\.shouldClarify[\s\S]*source === "local_probabilistic"/,
].forEach((pattern) => {
  if (!pattern.test(source)) {
    throw new Error(`Missing runtime safety pattern: ${pattern}`);
  }
});

console.log("Emily runtime safety wiring checks passed.");
