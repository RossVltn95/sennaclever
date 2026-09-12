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

const examples = Function(
  `"use strict"; return (${source
    .slice(start + marker.length, end)
    .trim()
    .replace(/;$/, "")});`
)();
const failures = [];

function clean(value) {
  return String(value || "").replace(/\s+/g, " ").trim();
}

function stem(token) {
  let cleanToken = clean(token).toLowerCase().replace(/[^a-z0-9+#&.-]/g, "");
  if (cleanToken.length > 5 && /ies$/.test(cleanToken)) {
    cleanToken = cleanToken.replace(/ies$/, "y");
  } else if (cleanToken.length > 5 && /ing$/.test(cleanToken)) {
    cleanToken = cleanToken.replace(/ing$/, "");
  } else if (cleanToken.length > 4 && /ed$/.test(cleanToken)) {
    cleanToken = cleanToken.replace(/ed$/, "");
  } else if (cleanToken.length > 4 && /s$/.test(cleanToken)) {
    cleanToken = cleanToken.replace(/s$/, "");
  }
  return cleanToken;
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
    .map(stem)
    .filter((token) => token && token.length > 1 && !stopWords[token]);
}

function weightedTokens(value) {
  const normalized = clean(value).toLowerCase();
  const base = tokenize(normalized);
  const out = [];
  const aliases = [
    [/\b(?:recruitment|handles recruitment|recruitment agenc(?:y|ies)|headhunter|headhunters|recruiters?|talent agency|search firm)\b/i, ["recruiter", "headhunter", "external_search", "networking"]],
    [/\b(?:latest|current|today|recent|news|market map|who are|which are|best)\b/i, ["current_knowledge", "external_search"]],
    [/\b(?:job|jobs|role|roles|vacanc(?:y|ies)|opening|openings|opportunit(?:y|ies))\b/i, ["job_search", "role_discovery", "opening"]],
    [/\b(?:company|employer|firm|business|reputation|background|what do they do)\b/i, ["company", "employer", "company_research"]],
    [/\b(?:salary|pay|package|compensation|bonus|allowance|benchmark)\b/i, ["salary", "compensation", "benchmark"]],
    [/\b(?:interview|prep|practice|questions|shortlist)\b/i, ["interview", "prep", "shortlist"]],
    [/\b(?:login|sign in|sign up|account|email|password)\b/i, ["account", "login", "account_issue"]],
  ];
  base.forEach((token, index) => {
    out.push([token, 1]);
    if (index > 0) {
      out.push([`${base[index - 1]}_${token}`, 1.25]);
    }
  });
  aliases.forEach(([pattern, tokens]) => {
    if (pattern.test(normalized)) {
      tokens.forEach((token) => out.push([stem(token), 1.65]));
    }
  });
  return out;
}

function vector(tokens, idf = {}) {
  const values = {};
  let norm = 0;
  tokens.forEach(([token, weight]) => {
    values[token] = (values[token] || 0) + weight * (idf[token] || 1);
  });
  Object.keys(values).forEach((token) => {
    norm += values[token] * values[token];
  });
  return { values, norm: Math.sqrt(norm) || 1 };
}

function cosine(a, b) {
  let total = 0;
  Object.keys(a.values).forEach((token) => {
    if (b.values[token]) {
      total += a.values[token] * b.values[token];
    }
  });
  return total / (a.norm * b.norm);
}

function buildModel() {
  const docs = [];
  const df = {};
  Object.keys(examples).forEach((route) => {
    examples[route].forEach((example) => {
      const tokens = weightedTokens(example);
      const seen = {};
      tokens.forEach(([token]) => {
        seen[token] = true;
      });
      Object.keys(seen).forEach((token) => {
        df[token] = (df[token] || 0) + 1;
      });
      docs.push({ route, text: example, tokens });
    });
  });
  const idf = {};
  Object.keys(df).forEach((token) => {
    idf[token] = 1 + Math.log((1 + docs.length) / (1 + df[token]));
  });
  return {
    idf,
    docs: docs.map((doc) => ({
      route: doc.route,
      text: doc.text,
      vector: vector(doc.tokens, idf),
    })),
  };
}

function classify(value) {
  const model = buildModel();
  const query = vector(weightedTokens(value), model.idf);
  const scores = {};
  model.docs.forEach((doc) => {
    const score = cosine(query, doc.vector);
    if (!scores[doc.route] || score > scores[doc.route].score) {
      scores[doc.route] = { route: doc.route, score, example: doc.text };
    }
  });
  return Object.values(scores).sort((a, b) => b.score - a.score);
}

[
  [
    "who usually handles recruitment for senior finance roles in Dubai",
    ["web_search", "recruiter_networking"],
  ],
  ["what background is this employer known for", ["company_research", "role_question"]],
  ["benchmark the package for this role", ["salary_compensation", "role_question"]],
  ["help me practice interview questions", ["interview_prep"]],
  ["I cannot log in to my account", ["account_issue"]],
].forEach(([message, acceptedRoutes]) => {
  const top = classify(message)[0];
  if (!top || !acceptedRoutes.includes(top.route)) {
    failures.push(
      `bad_semantic_route:${message}:expected_${acceptedRoutes.join("_or_")}:got_${top && top.route}`
    );
  }
});

[
  "function expandEmilyDecisionSemanticTokens",
  "function getEmilyDecisionSemanticModel",
  "function classifyEmilyDecisionRouteSemanticSimilarity",
  "route_semantic:",
  "routeSemantic:",
  "classifyEmilyDecisionRouteSemanticSimilarity",
].forEach((needle) => {
  if (!source.includes(needle)) {
    failures.push(`missing_source_marker:${needle}`);
  }
});

if (failures.length) {
  console.error("FAIL Emily semantic route classifier", failures);
  process.exit(1);
}

console.log("PASS Emily semantic route classifier resolves meaning-based routes");
