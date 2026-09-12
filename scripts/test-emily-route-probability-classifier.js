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
const marker = "var emilyDecisionRouteTrainingExamples = ";
const start = source.indexOf(marker);
const end = source.indexOf(
  "\n    var emilyDecisionRouteBayesModel = null;",
  start
);

if (start === -1 || end === -1) {
  throw new Error("Could not locate emilyDecisionRouteTrainingExamples");
}

const literal = source.slice(start + marker.length, end).trim().replace(/;$/, "");
const examples = Function(`"use strict"; return (${literal});`)();

const failures = [];
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

requiredRoutes.forEach((route) => {
  if (!Array.isArray(examples[route]) || examples[route].length < 2) {
    failures.push(`missing_training_examples:${route}`);
  }
});

function clean(value) {
  return String(value || "").replace(/\s+/g, " ").trim();
}

function tokenize(value) {
  const stopWords = {
    a: true,
    an: true,
    and: true,
    are: true,
    at: true,
    be: true,
    for: true,
    i: true,
    in: true,
    is: true,
    it: true,
    me: true,
    my: true,
    of: true,
    on: true,
    or: true,
    that: true,
    the: true,
    this: true,
    to: true,
    with: true,
    you: true,
  };
  return clean(value)
    .toLowerCase()
    .replace(/[^a-z0-9+#&.\s-]/g, " ")
    .split(/\s+/)
    .map((token) => clean(token).replace(/^-+|-+$/g, ""))
    .filter((token) => token && token.length > 1 && !stopWords[token]);
}

function buildModel() {
  const vocabulary = {};
  const routes = {};
  let totalExamples = 0;
  Object.keys(examples).forEach((routeKey) => {
    const routeExamples = examples[routeKey] || [];
    const route = { exampleCount: routeExamples.length, tokenCounts: {}, tokenTotal: 0 };
    totalExamples += routeExamples.length;
    routeExamples.forEach((example) => {
      tokenize(example).forEach((token) => {
        route.tokenCounts[token] = (route.tokenCounts[token] || 0) + 1;
        route.tokenTotal += 1;
        vocabulary[token] = true;
      });
    });
    routes[routeKey] = route;
  });
  return {
    routes,
    vocabularySize: Math.max(1, Object.keys(vocabulary).length),
    totalExamples: Math.max(1, totalExamples),
  };
}

function classify(value) {
  const model = buildModel();
  const tokens = Array.from(new Set(tokenize(value)));
  const smoothing = 0.65;
  const candidates = Object.keys(model.routes).map((routeKey) => {
    const route = model.routes[routeKey];
    let logit = Math.log(Math.max(0.001, route.exampleCount / model.totalExamples));
    const evidence = [];
    let evidenceCount = 0;
    tokens.forEach((token) => {
      const count = route.tokenCounts[token] || 0;
      if (count > 0) {
        const tokenProbability =
          (count + smoothing) /
          (route.tokenTotal + smoothing * model.vocabularySize);
        logit += 1.15 + Math.log(1 + count) + tokenProbability * 3.5;
        evidence.push(token);
        evidenceCount += 1;
      }
    });
    if (evidenceCount) {
      logit += Math.min(2.2, evidenceCount * 0.45);
    } else {
      logit -= 1.4;
    }
    return { route: routeKey, logit, evidence, probability: 0 };
  });
  const maxLogit = candidates.reduce((max, candidate) => Math.max(max, candidate.logit), -Infinity);
  let total = 0;
  candidates.forEach((candidate) => {
    candidate.exp = Math.exp(Math.max(-60, Math.min(60, candidate.logit - maxLogit)));
    total += candidate.exp;
  });
  candidates.forEach((candidate) => {
    candidate.probability = total > 0 ? candidate.exp / total : 0;
  });
  return candidates.sort((a, b) => b.probability - a.probability);
}

[
  ["best recruitment agencies in Dubai", "web_search"],
  ["find me HR manager jobs in Dubai", "job_search"],
  ["not these roles, show me more senior jobs", "search_refinement"],
  ["apply to this role now", "apply_action"],
  ["compare my CV to this job", "cv_role_comparison"],
  ["what stage is the application at", "application_status"],
  ["prepare me for the interview", "interview_prep"],
  ["I cannot log in", "account_issue"],
].forEach(([message, expectedRoute]) => {
  const top = classify(message)[0];
  if (!top || top.route !== expectedRoute) {
    failures.push(
      `bad_classification:${message}:expected_${expectedRoute}:got_${top && top.route}`
    );
  }
});

[
  "function classifyEmilyDecisionRouteProbability",
  "function getEmilyDecisionRouteBayesModel",
  "route_bayes:",
  "routeProbability:",
  "classifyEmilyDecisionRouteProbability",
].forEach((needle) => {
  if (!source.includes(needle)) {
    failures.push(`missing_source_marker:${needle}`);
  }
});

if (failures.length) {
  console.error("FAIL Emily route probability classifier", failures);
  process.exit(1);
}

console.log(
  `PASS Emily route probability classifier covers ${Object.keys(examples).length} routes`
);
