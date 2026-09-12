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
  throw new Error("Could not locate route training examples");
}

const examples = Function(
  `"use strict"; return (${source
    .slice(start + marker.length, end)
    .trim()
    .replace(/;$/, "")});`
)();

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
  Object.entries(examples).forEach(([routeKey, routeExamples]) => {
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

function classifyBayes(value) {
  const model = buildModel();
  const tokens = Array.from(new Set(tokenize(value)));
  const smoothing = 0.65;
  const candidates = Object.entries(model.routes).map(([routeKey, route]) => {
    let logit = Math.log(Math.max(0.001, route.exampleCount / model.totalExamples));
    let evidenceCount = 0;
    tokens.forEach((token) => {
      const count = route.tokenCounts[token] || 0;
      if (count > 0) {
        const tokenProbability =
          (count + smoothing) /
          (route.tokenTotal + smoothing * model.vocabularySize);
        logit += 1.15 + Math.log(1 + count) + tokenProbability * 3.5;
        evidenceCount += 1;
      }
    });
    logit += evidenceCount ? Math.min(2.2, evidenceCount * 0.45) : -1.4;
    return { route: routeKey, logit, probability: 0 };
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

function getCalibrationBand(probability, margin, safety, penalty) {
  if (safety < 0.45 || penalty >= 0.28) return "unsafe";
  if (probability >= 0.82 && margin >= 0.18) return "very_high";
  if (probability >= 0.72 && margin >= 0.12) return "high";
  if (probability >= 0.58 && margin >= 0.07) return "medium";
  return "ambiguous";
}

function calibrate(score, margin, components) {
  const safety = Number(components.workflowSafety || 0.86);
  const penalty = Number(components.penalty || 0);
  const logistic = 1 / (1 + Math.exp(-8 * (score - 0.48)));
  const probability = Math.max(
    0.02,
    Math.min(
      0.98,
      logistic +
        Math.min(0.16, Math.max(0, margin) * 0.45) +
        (safety - 0.75) * 0.12 -
        penalty * 0.5
    )
  );
  return {
    probability,
    band: getCalibrationBand(probability, margin, safety, penalty),
  };
}

const rows = [];
Object.entries(examples).forEach(([expectedRoute, routeExamples]) => {
  routeExamples.forEach((example) => {
    const ranked = classifyBayes(example);
    const top = ranked[0] || { route: "clarify", probability: 0 };
    const second = ranked[1] || { route: "", probability: 0 };
    const score = 0.3 + top.probability * 0.25 + 0.2 + 0.1 + 0.08 + 0.86 * 0.07;
    const margin = Math.max(0, top.probability - second.probability);
    const calibration = calibrate(score, margin, {
      workflowSafety: 0.86,
      penalty: 0,
    });
    rows.push({
      expectedRoute,
      predictedRoute: top.route,
      correct: top.route === expectedRoute,
      score,
      calibratedProbability: calibration.probability,
      band: calibration.band,
      text: example,
    });
  });
});

const confusion = {};
const buckets = {};
let brier = 0;
let logLoss = 0;
rows.forEach((row) => {
  confusion[row.expectedRoute] = confusion[row.expectedRoute] || {};
  confusion[row.expectedRoute][row.predictedRoute] =
    (confusion[row.expectedRoute][row.predictedRoute] || 0) + 1;
  buckets[row.band] = buckets[row.band] || { total: 0, correct: 0 };
  buckets[row.band].total += 1;
  buckets[row.band].correct += row.correct ? 1 : 0;
  const y = row.correct ? 1 : 0;
  const p = Math.max(0.001, Math.min(0.999, row.calibratedProbability));
  brier += Math.pow(p - y, 2);
  logLoss += -(y * Math.log(p) + (1 - y) * Math.log(1 - p));
});

Object.keys(buckets).forEach((bucket) => {
  buckets[bucket].accuracy = Number(
    (buckets[bucket].correct / Math.max(1, buckets[bucket].total)).toFixed(4)
  );
});

const report = {
  examples: rows.length,
  accuracy: Number(
    (rows.filter((row) => row.correct).length / Math.max(1, rows.length)).toFixed(4)
  ),
  brierScore: Number((brier / Math.max(1, rows.length)).toFixed(4)),
  logLoss: Number((logLoss / Math.max(1, rows.length)).toFixed(4)),
  buckets,
  confusion,
  misses: rows
    .filter((row) => !row.correct)
    .slice(0, 12)
    .map((row) => ({
      expectedRoute: row.expectedRoute,
      predictedRoute: row.predictedRoute,
      band: row.band,
      text: row.text,
    })),
};

console.log(JSON.stringify(report, null, 2));
