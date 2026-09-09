#!/usr/bin/env node
"use strict";

const fs = require("fs");
const path = require("path");
const { spawnSync } = require("child_process");

const cvDir = process.argv[2] || "/Users/ropafadzoyasheushe/Downloads/CVs";
const outDir =
  process.argv[3] ||
  path.join(process.cwd(), "reports", "tailored-cv-rewrite-samples");

const weakVerbRules = [
  [/^\s*provided support\b/i, "Supported"],
  [/^\s*provided assistance\b/i, "Supported"],
  [/^\s*played a role in\b/i, "Contributed to"],
  [/^\s*was involved in\b/i, "Contributed to"],
  [/^\s*was assigned to\b/i, "Delivered"],
  [/^\s*was asked to\b/i, "Delivered"],
  [/^\s*was given responsibility for\b/i, "Managed"],
  [/^\s*participated in\b/i, "Contributed to"],
  [/^\s*helped with\b/i, "Supported"],
  [/^\s*helped\b/i, "Supported"],
  [/^\s*aided\b/i, "Supported"],
  [/^\s*assisted in\b/i, "Supported"],
  [/^\s*assisted with\b/i, "Supported"],
  [/^\s*assisted\b/i, "Supported"],
  [/^\s*worked on\b/i, "Contributed to"],
  [/^\s*working on\b/i, "Contributing to"],
  [/^\s*involved in\b/i, "Contributed to"],
  [/^\s*responsible for\b/i, "Managed"],
  [/^\s*tasked with\b/i, "Delivered"],
  [/^\s*duties included\b/i, "Delivered"],
  [/^\s*took care of\b/i, "Managed"],
  [/^\s*looked after\b/i, "Managed"],
  [/^\s*handled\b/i, "Managed"],
  [/^\s*dealt with\b/i, "Managed"],
  [/^\s*made\b/i, "Created"],
  [/^\s*did\b/i, "Delivered"],
  [/^\s*got\b/i, "Secured"],
  [/^\s*performed\s+(?:tasks?|duties|work|activities)\b/i, "Executed"],
  [/^\s*facilitated\b/i, "Coordinated"],
  [/^\s*conduct\b/i, "Conducted"],
];

const weakLanguageRules = [
  {
    key: "weak_opening_verb",
    pattern:
      /^(?:helped|assisted|aided|supported|worked on|working on|participated in|involved in|handled|dealt with|did|made|got|performed\s+(?:tasks?|duties|work|activities)|facilitated|responsible for|tasked with|provided support|provided assistance|took care of)\b/i,
  },
  {
    key: "passive_voice",
    pattern: /\b(?:was|were)\s+(?:assigned|asked|given|tasked|responsible|involved|required|expected)\b/i,
  },
  {
    key: "vague_quantity",
    pattern: /\b(?:various|several|multiple|many|numerous|some|a number of|a variety of|different|wide range of)\b/i,
  },
  {
    key: "filler_language",
    pattern:
      /\b(?:successfully|effectively|efficiently|proactively|actively|strong|excellent|good|great|dynamic|innovative|hard[-\s]?working|team player|detail[-\s]?oriented)\b/i,
  },
  {
    key: "buzzword_overload",
    pattern:
      /\b(?:synergy|synergies|best[-\s]?in[-\s]?class|world[-\s]?class|cutting[-\s]?edge|leverage|leveraged|utili[sz]ed|fast[-\s]?paced|results[-\s]?driven|self[-\s]?starter)\b/i,
  },
  {
    key: "generic_responsibility",
    pattern: /\b(?:responsible for|duties included|tasks included|day to day|day-to-day|exposure to|familiar with)\b/i,
  },
  {
    key: "unsupported_soft_skill",
    pattern:
      /\b(?:strong|excellent|good|great)\s+(?:communication|leadership|teamwork|analytical|interpersonal|organisational|organizational|problem[-\s]?solving)\s+skills?\b/i,
  },
  {
    key: "first_person_language",
    pattern: /\b(?:i|me|my|we|our)\b/i,
  },
  {
    key: "missing_outcome_signal",
    pattern:
      /^(?!.*\b(?:supporting|resulting|contributing|improving|reducing|increasing|accelerating|enhancing|informing|driving|enabling|achieving|delivering|ensuring|saving|generating|securing)\b).{45,}$/i,
  },
];

const rewriteFamilies = [
  {
    key: "financial_modelling",
    label: "Financial Modelling",
    pattern: /\b(?:model|modelling|modeling|valuation|dcf|lbo|forecast|sensitivity|scenario|financial analysis)\b/i,
    actions: { junior: "Prepared", mid: "Built", senior: "Reviewed" },
    object: "financial analysis",
    outcome: "supporting investment and business decision-making",
  },
  {
    key: "due_diligence",
    label: "Due Diligence",
    pattern: /\b(?:due diligence|diligence|risk analysis|market-fit|assessment|review)\b/i,
    actions: { junior: "Supported", mid: "Executed", senior: "Led" },
    object: "due diligence workstreams",
    outcome: "surfacing risks, findings, and decision-relevant insight",
  },
  {
    key: "transactions",
    label: "Transactions",
    pattern: /\b(?:deal|transaction|investment|acquisition|m&a|merger|ipo|structuring|negotiation|capital raise)\b/i,
    actions: { junior: "Supported", mid: "Executed", senior: "Managed" },
    object: "transaction analysis and execution",
    outcome: "supporting deal evaluation and senior review",
  },
  {
    key: "business_development",
    label: "Business Development",
    pattern: /\b(?:pipeline|leads|prospect|cold contact|c-level|account|client|sales|commercial|partnership|revenue|crm)\b/i,
    actions: { junior: "Built", mid: "Managed", senior: "Led" },
    object: "commercial pipeline activity",
    outcome: "supporting growth, conversion, and stakeholder engagement",
  },
  {
    key: "operations",
    label: "Operations",
    pattern: /\b(?:automation|macro|workflow|process|streamlined|efficiency|reconciliation|control|compliance|operations)\b/i,
    actions: { junior: "Improved", mid: "Streamlined", senior: "Led" },
    object: "operational workflows",
    outcome: "improving accuracy, control, and execution consistency",
  },
  {
    key: "technology",
    label: "Technology & Analytics",
    pattern: /\b(?:python|sql|tableau|power bi|excel|vba|automation|data|analytics|machine learning|sap|bloomberg|capital iq|factset|refinitiv)\b/i,
    actions: { junior: "Applied", mid: "Developed", senior: "Led" },
    object: "data and analytical workflows",
    outcome: "improving insight generation and decision support",
  },
  {
    key: "strategy",
    label: "Strategy",
    pattern: /\b(?:consulting|strategy|recommendation|stakeholder|presentation|pitch|memorandum|market research)\b/i,
    actions: { junior: "Prepared", mid: "Developed", senior: "Led" },
    object: "strategic analysis and stakeholder materials",
    outcome: "supporting recommendations and decision-making",
  },
];

function html(value) {
  return String(value == null ? "" : value)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}

function slug(value) {
  return String(value || "cv")
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "")
    .slice(0, 80) || "cv";
}

function clean(text) {
  return String(text || "")
    .replace(/\s+/g, " ")
    .replace(/^[•▪●○◦❖\-–—]+\s*/, "")
    .trim();
}

function extractText(file) {
  const ext = path.extname(file).toLowerCase();
  const command =
    ext === ".pdf"
      ? ["pdftotext", file, "-"]
      : ext === ".docx" || ext === ".doc"
      ? ["textutil", "-convert", "txt", "-stdout", file]
      : ext === ".txt"
      ? ["cat", file]
      : [];
  if (!command.length) return { text: "", error: "unsupported_file_type" };
  const result = spawnSync(command[0], command.slice(1), {
    encoding: "utf8",
    maxBuffer: 1024 * 1024 * 20,
  });
  if (result.error || result.status !== 0) {
    return {
      text: "",
      error: result.error ? result.error.message : result.stderr || "extract_failed",
    };
  }
  return {
    text: String(result.stdout || "")
      .replace(/[•▪●○◦❖]\s*/g, "\n• ")
      .replace(/\u0000/g, ""),
    error: "",
  };
}

function mergeWrappedLines(text) {
  const merged = [];
  let pendingBullet = false;
  String(text || "")
    .split(/\r?\n/)
    .forEach((rawLine) => {
      const raw = String(rawLine || "");
      if (/^\s*[•▪●○◦❖\-–—]\s*$/.test(raw)) {
        pendingBullet = true;
        return;
      }
      const value = clean(raw);
      if (!value) return;
      const line = pendingBullet ? "• " + value : value;
      pendingBullet = false;
      const previous = merged[merged.length - 1] || "";
      const startsBullet = /^[•▪●○◦❖\-–—]\s+/.test(line);
      const heading = isHeading(line);
      const shouldMerge =
        previous &&
        !startsBullet &&
        !heading &&
        !/[.;)]$/.test(previous) &&
        previous.length < 260 &&
        (/[,;:]$/.test(previous) || /^[a-z(]/.test(line));
      if (shouldMerge) {
        merged[merged.length - 1] = clean(previous + " " + line);
        return;
      }
      merged.push(line);
    });
  return merged;
}

function isHeading(line) {
  const value = clean(line);
  return (
    value.length <= 80 &&
    (/^[A-Z][A-Z\s/&,-]+$/.test(value) ||
      /^(?:summary|profile|education|work experience|professional experience|experience|skills|technical skills|certifications|awards|projects|languages|interests)$/i.test(value))
  );
}

function isLikelyBullet(line) {
  const value = clean(line);
  if (value.length < 35 || value.length > 420) return false;
  if (/@|www\.|linkedin\.com/i.test(value)) return false;
  if (isHeading(value)) return false;
  return (
    /^[•▪●○◦❖\-–—]\s+/.test(String(line || "")) ||
    /^(?:helped|assisted|aided|supported|worked|participated|handled|managed|led|built|prepared|executed|developed|created|streamlined|monitored|evaluated|sourced|qualified|coordinated|facilitated|validated|performed|provided|contributed|improved|reduced|increased|analy[sz]ed|reviewed|delivered|owned|oversaw|directed|collect|read|measure|assessing|analyse|prepare|develop|conduct|support|manage|coordinate|control|berat(?:ung|en)|erstellung|übernahme|uebernahme|betreuung|koordination|fachliche|controlling|création|creation|préparation|preparation|gestion|analyse|desenvolvimento|gestão|gestao)\b/i.test(value) ||
    /^[A-Z][A-Za-z/& -]{2,38}:\s+[A-Z]/.test(value)
  );
}

function detectEmail(text) {
  const match = String(text || "").match(/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i);
  return match ? match[0].toLowerCase() : "";
}

function detectName(lines) {
  const rejected =
    /@|www|linkedin|github|phone|curriculum|vitae|resume|cv|education|experience|summary|profile|skills|personal|information|details|contact|about|objective|objectives|career|professional|accountant|finance|auditing|analyst|associate|manager|intern|consultant|engineer|university|school/i;
  const splitUppercaseName = lines
    .slice(0, 8)
    .map(clean)
    .reduce((found, line, index, list) => {
      if (found) return found;
      const next = list[index + 1] || "";
      if (
        /^[A-Z][A-Z'’-]{2,}$/.test(line) &&
        /^[A-Z][A-Z'’-]{2,}$/.test(next) &&
        !rejected.test(line) &&
        !rejected.test(next)
      ) {
        return `${line} ${next}`;
      }
      return "";
    }, "");
  if (splitUppercaseName) return splitUppercaseName;
  const candidate = lines
    .slice(0, 12)
    .map(clean)
    .find((line) => {
      const words = line.split(/\s+/).filter(Boolean);
      return (
        words.length >= 2 &&
        words.length <= 4 &&
        line.length <= 60 &&
        !rejected.test(line) &&
        words.every((word) => /^[A-Z][A-Za-z'’-]+$/.test(word))
      );
    });
  return candidate || "Candidate";
}

function detectSkills(lines) {
  const skillText = lines.join(" ");
  const skills = [
    "Financial Modelling",
    "Valuation",
    "Due Diligence",
    "Business Development",
    "Market Research",
    "SQL",
    "Python",
    "Excel",
    "Tableau",
    "Power BI",
    "Bloomberg",
    "Capital IQ",
    "CRM",
    "Stakeholder Management",
    "Operations",
    "Reporting",
    "Automation",
    "Data Analysis",
  ];
  return skills.filter((skill) => new RegExp("\\b" + skill.replace(/\s+/g, "\\s+") + "\\b", "i").test(skillText)).slice(0, 12);
}

function detectSeniority(lines) {
  const text = lines.slice(0, 80).join(" ");
  if (/\b(?:director|head of|vp|vice president|chief|principal|lead)\b/i.test(text)) return "senior";
  if (/\b(?:associate|manager|consultant|senior analyst|engineer)\b/i.test(text)) return "mid";
  return "junior";
}

function detectTargetFamily(bullets) {
  const counts = new Map();
  bullets.forEach((bullet) => {
    rewriteFamilies.forEach((family) => {
      if (family.pattern.test(bullet)) {
        counts.set(family.key, (counts.get(family.key) || 0) + 1);
      }
    });
  });
  return (
    [...counts.entries()]
      .sort((left, right) => right[1] - left[1])
      .map(([key]) => rewriteFamilies.find((family) => family.key === key))[0] || rewriteFamilies[0]
  );
}

function collectMetrics(text) {
  return (
    clean(text).match(
      /(?:\+?\d+(?:[.,]\d+)?%?|[$€£]\s*\+?[0-9.,]+\s*(?:m|mn|mln|bn|billion|million)?|[0-9.,]+\s*(?:transactions|investments|leads|start-?ups|ventures|countries|markets|hours|facilities|clients|companies|teams))/gi
    ) || []
  ).map(clean);
}

function collectTools(text) {
  return (
    clean(text).match(/\b(?:LBO|DCF|SQL|Python|Tableau|Excel|VBA|SAP|Bloomberg|Capital IQ|FactSet|Refinitiv|Power BI|ARIMA|R|CRM)\b/gi) || []
  ).map(clean);
}

function detectWeakIssues(text) {
  const value = clean(text);
  return weakLanguageRules.filter((rule) => rule.pattern.test(value)).map((rule) => rule.key);
}

function cleanFillerLanguage(text) {
  return clean(text)
    .replace(/^\s*(?:i|we)\s+/i, "")
    .replace(/\bmy responsibilities included\b/gi, "responsibilities included")
    .replace(/\bour responsibilities included\b/gi, "responsibilities included")
    .replace(/\b(?:successfully|effectively|efficiently|proactively|actively)\b\s*/gi, "")
    .replace(/\b(?:strong|excellent|good|great)\s+(?=(?:communication|interpersonal|analytical|team|teamwork|leadership|problem[-\s]?solving|time management|relationship|organisational|organizational))/gi, "")
    .replace(/\b(?:dynamic|innovative|results[-\s]?driven|self[-\s]?starter|hard[-\s]?working)\b/gi, "")
    .replace(/\butili[sz]ed\b/gi, "Used")
    .replace(/\bleverage\b/gi, "use")
    .replace(/\bleveraged\b/gi, "Used")
    .replace(/\bsynergies\b/gi, "cost and revenue opportunities")
    .replace(/\bfamiliar with\b/gi, "Used")
    .replace(/\bexposure to\b/gi, "Experience with")
    .replace(/\bvarious\b/gi, "relevant")
    .replace(/\bseveral\b/gi, "multiple")
    .replace(/\ba variety of\b/gi, "multiple")
    .replace(/\ba wide range of\b/gi, "multiple")
    .replace(/\s+/g, " ")
    .trim();
}

function classifyEvidence(text, seniority) {
  const family = rewriteFamilies.find((item) => item.pattern.test(text)) || null;
  return {
    source: clean(text),
    family,
    seniority,
    weakIssues: detectWeakIssues(text),
    metrics: collectMetrics(text),
    tools: collectTools(text),
    hasOwnership: /\b(?:led|owned|managed|oversaw|directed|executed|built|developed|prepared|streamlined|validated|sourced|monitored)\b/i.test(text),
    hasOutcome: /\b(?:supporting|resulting|contributing|improving|reducing|increasing|accelerating|enhancing|informing|driving|enabling|achieving|delivering|ensuring|saving|generating|securing)\b/i.test(text),
  };
}

function chooseRewriteSource(text) {
  const withoutLabel = cleanFillerLanguage(text).replace(/^[A-Z][A-Za-z/& -]{2,42}:\s*/, "");
  const sentences = withoutLabel
    .split(/(?<=[.!?])\s+/)
    .map(clean)
    .filter((item) => item.split(/\s+/).length >= 7);
  if (!sentences.length) return withoutLabel;
  const scored = sentences.map((sentence) => {
    let score = 0;
    if (collectMetrics(sentence).length) score += 20;
    if (collectTools(sentence).length) score += 8;
    rewriteFamilies.forEach((family) => {
      if (family.pattern.test(sentence)) score += 10;
    });
    if (detectWeakIssues(sentence).length) score += 4;
    if (sentence.split(/\s+/).length <= 34) score += 8;
    return { sentence, score };
  });
  return scored.sort((left, right) => right.score - left.score)[0].sentence;
}

function replaceWeakOpening(text) {
  let value = cleanFillerLanguage(text);
  weakVerbRules.some(([pattern, replacement]) => {
    if (pattern.test(value)) {
      value = clean(value.replace(pattern, replacement));
      return true;
    }
    return false;
  });
  return value;
}

function stripOpening(text) {
  return clean(text).replace(
    /^(?:led|built|prepared|managed|executed|developed|analy[sz]ed|evaluated|streamlined|sourced|monitored|validated|provided|created|coordinated|facilitated|supported|contributed to|conducted|delivered|improved|reviewed|oversaw|applied)\s+/i,
    ""
  );
}

function buildCandidates(evidence) {
  const base = replaceWeakOpening(evidence.source);
  const candidates = [base];
  if (evidence.family) {
    const action = evidence.family.actions[evidence.seniority] || evidence.family.actions.mid;
    const detail = stripOpening(base);
    candidates.push(clean(`${action} ${detail}`));
    if (!evidence.hasOutcome) {
      candidates.push(clean(`${action} ${detail}, ${evidence.family.outcome}`));
    }
    if (evidence.tools.length) {
      candidates.push(clean(`${action} ${evidence.family.object} using ${evidence.tools.join(", ")}, ${evidence.family.outcome}`));
    }
  }
  return [...new Set(candidates.map((item) => ensureSentence(item)).filter(Boolean))];
}

function ensureSentence(text) {
  const value = clean(text)
    .replace(/\s+([,.;:])/g, "$1")
    .replace(/(?:,\s*)?(?:and|or|with|through|across|including|using|by|in|of|to|for|the|a)\s*\.?$/i, "")
    .replace(/,\s*$/, "")
    .trim();
  if (!value) return "";
  return /[.!?]$/.test(value) ? value : `${value}.`;
}

function validates(candidate, evidence) {
  const sourceLower = evidence.source.toLowerCase();
  const candidateLower = candidate.toLowerCase();
  const ownershipInflated =
    /\b(?:led|owned|oversaw|directed)\b/i.test(candidate) &&
    !/\b(?:led|owned|oversaw|directed|managed|head|supervised|team|workstream|responsible)\b/i.test(evidence.source);
  const metricsPreserved = evidence.metrics.every((metric) => candidateLower.includes(metric.toLowerCase()));
  const unsupportedTools = collectTools(candidate).filter((tool) => !sourceLower.includes(tool.toLowerCase()));
  const tooLong = candidate.split(/\s+/).length > 38;
  return {
    safe: !ownershipInflated && metricsPreserved && !unsupportedTools.length,
    ownershipInflated,
    metricsPreserved,
    unsupportedTools,
    tooLong,
  };
}

function scoreCandidate(candidate, evidence, targetFamily) {
  let score = 0;
  if (/^(?:led|built|prepared|managed|executed|developed|analy[sz]ed|evaluated|streamlined|sourced|monitored|validated|supported|contributed|delivered|improved|applied)\b/i.test(candidate)) score += 18;
  if (evidence.family && evidence.family.key === targetFamily.key) score += 18;
  if (collectMetrics(candidate).length) score += 14;
  if (collectTools(candidate).length) score += 8;
  if (/\b(?:supporting|resulting|contributing|improving|reducing|increasing|accelerating|enhancing|informing|driving|enabling|ensuring)\b/i.test(candidate)) score += 12;
  score -= detectWeakIssues(candidate).length * 5;
  score -= Math.abs(candidate.split(/\s+/).length - 24);
  return score;
}

function rewriteBullet(text, seniority, targetFamily) {
  const sourceForRewrite = chooseRewriteSource(text);
  const evidence = classifyEvidence(sourceForRewrite, seniority);
  const candidates = buildCandidates(evidence)
    .map((candidate) => ({
      text: candidate,
      validation: validates(candidate, evidence),
      score: scoreCandidate(candidate, evidence, targetFamily),
    }))
    .filter((candidate) => candidate.validation.safe)
    .sort((left, right) => right.score - left.score);
  const selected = candidates[0] || {
    text: ensureSentence(replaceWeakOpening(text)),
    validation: validates(replaceWeakOpening(text), evidence),
    score: 0,
  };
  return {
    original: clean(text),
    rewritten: selected.text,
    changed: clean(text) !== clean(selected.text),
    weakIssues: evidence.weakIssues,
    family: evidence.family ? evidence.family.key : "general",
    validation: selected.validation,
    score: selected.score,
    candidatesConsidered: candidates.length,
  };
}

function buildSummary(name, targetFamily, skills, bullets) {
  const topSkills = skills.slice(0, 4);
  const hasMetrics = bullets.some((item) => collectMetrics(item.rewritten).length);
  return clean(
    `${name === "Candidate" ? "Candidate" : name} brings evidence across ${targetFamily.label.toLowerCase()}${topSkills.length ? `, with strengths in ${topSkills.join(", ")}` : ""}${hasMetrics ? ", supported by measurable achievements" : ""}.`
  );
}

function renderCvHtml(model) {
  const bullets = model.rewrites
    .slice(0, 28)
    .map((item) => `<li>${html(item.rewritten)}</li>`)
    .join("");
  const skills = model.skills.length
    ? `<p><strong>Skills:</strong> ${html(model.skills.join(", "))}</p>`
    : "";
  const contact = [model.email].filter(Boolean).join(" | ");
  return `<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>${html(model.name)} - Tailored CV</title>
  <style>
    * { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; }
    body { background: #ffffff; color: #000000; font-family: "EB Garamond", "Times New Roman", Times, serif; font-size: 16px; line-height: 1.42; }
    .sheet { max-width: 800px; margin: 0 auto; padding: 52px 60px 70px; }
    header { text-align: center; margin-bottom: 22px; }
    h1 { font-size: 28px; font-weight: 700; letter-spacing: 0.03em; text-transform: uppercase; margin: 0 0 6px; }
    .contact-line { font-size: 14.5px; }
    hr.top-rule { border: none; border-top: 1.5px solid #000; margin: 0 0 18px; }
    .summary-text { font-size: 15px; text-align: justify; margin-bottom: 22px; }
    section { margin-bottom: 20px; }
    .sec-head { font-size: 14px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; border-bottom: 1px solid #000; padding-bottom: 2px; margin-bottom: 10px; }
    .entry { margin-bottom: 12px; }
    .entry-head { display: flex; justify-content: space-between; align-items: baseline; font-weight: 700; font-size: 15.5px; }
    .entry-head .dates { font-weight: 400; font-style: italic; white-space: nowrap; padding-left: 12px; }
    ul.bullets { margin: 4px 0 0; padding-left: 20px; }
    ul.bullets li { margin-bottom: 3px; font-size: 15px; text-align: justify; }
    .skills-list p { font-size: 15px; margin: 0 0 6px; }
    .skills-list strong { font-weight: 700; }
    @media print { .sheet { padding: 24px 40px; } body { font-size: 14px; } }
    @media (max-width: 640px) { .sheet { padding: 30px 20px; } .entry-head { flex-direction: column; } .entry-head .dates { padding-left: 0; } }
  </style>
</head>
<body>
  <div class="sheet">
    <header>
      <h1>${html(model.name)}</h1>
      ${contact ? `<div class="contact-line">${html(contact)}</div>` : ""}
    </header>
    <hr class="top-rule">
    <div class="summary-text">${html(model.summary)}</div>
    <section>
      <div class="sec-head">Work Experience</div>
      <div class="entry">
        <div class="entry-head">
          <span>${html(model.targetFamily.label)}</span>
        </div>
        <ul class="bullets">${bullets}</ul>
      </div>
    </section>
    ${skills ? `<section><div class="sec-head">Technical Skills & Interests</div><div class="skills-list">${skills}</div></section>` : ""}
  </div>
</body>
</html>`;
}

function evaluateFile(file, index) {
  const extracted = extractText(file);
  if (!extracted.text) {
    return { file: path.basename(file), failed: true, error: extracted.error };
  }
  const lines = mergeWrappedLines(extracted.text);
  const bullets = lines.filter(isLikelyBullet).slice(0, 80);
  const name = detectName(lines);
  const email = detectEmail(extracted.text);
  const skills = detectSkills(lines);
  const seniority = detectSeniority(lines);
  const targetFamily = detectTargetFamily(bullets);
  const rewrites = bullets.map((bullet) => rewriteBullet(bullet, seniority, targetFamily));
  const edgeCases = [];
  if (bullets.length < 4) edgeCases.push("low_bullet_extraction");
  if (!email) edgeCases.push("email_not_detected");
  if (name === "Candidate") edgeCases.push("name_not_detected");
  if (!skills.length) edgeCases.push("skills_not_detected");
  if (rewrites.filter((item) => item.changed).length < Math.min(2, bullets.length)) edgeCases.push("low_rewrite_yield");
  if (rewrites.some((item) => !item.validation.safe)) edgeCases.push("unsafe_candidate_rejected");
  if (rewrites.some((item) => item.validation.tooLong)) edgeCases.push("long_rewrite_candidate");
  const model = {
    sourceFile: path.basename(file),
    name,
    email,
    skills,
    seniority,
    targetFamily,
    summary: buildSummary(name, targetFamily, skills, rewrites),
    rewrites,
    edgeCases,
    stats: {
      bullets: bullets.length,
      weakBullets: rewrites.filter((item) => item.weakIssues.length).length,
      changedBullets: rewrites.filter((item) => item.changed).length,
      safeRewrites: rewrites.filter((item) => item.validation.safe).length,
    },
  };
  const base = `${String(index + 1).padStart(3, "0")}-${slug(name)}-${slug(path.basename(file, path.extname(file)))}`;
  model.htmlFile = `${base}.html`;
  model.jsonFile = `${base}.json`;
  fs.writeFileSync(path.join(outDir, model.htmlFile), renderCvHtml(model));
  fs.writeFileSync(path.join(outDir, model.jsonFile), JSON.stringify(model, null, 2));
  return model;
}

function renderIndex(results) {
  const rows = results
    .map((item) => {
      if (item.failed) {
        return `<tr><td>${html(item.file)}</td><td colspan="6">Failed: ${html(item.error)}</td></tr>`;
      }
      return `<tr>
        <td><a href="${html(item.htmlFile)}">${html(item.name)}</a><small>${html(item.sourceFile)}</small></td>
        <td>${html(item.targetFamily.label)}</td>
        <td>${item.stats.bullets}</td>
        <td>${item.stats.weakBullets}</td>
        <td>${item.stats.changedBullets}</td>
        <td>${item.stats.safeRewrites}</td>
        <td>${item.edgeCases.map(html).join(", ") || "none"}</td>
      </tr>`;
    })
    .join("");
  const parsed = results.filter((item) => !item.failed);
  const totals = parsed.reduce(
    (sum, item) => {
      sum.bullets += item.stats.bullets;
      sum.weak += item.stats.weakBullets;
      sum.changed += item.stats.changedBullets;
      sum.safe += item.stats.safeRewrites;
      sum.edge += item.edgeCases.length;
      return sum;
    },
    { bullets: 0, weak: 0, changed: 0, safe: 0, edge: 0 }
  );
  return `<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>CV Rewrite Evaluation</title>
  <style>
    body { margin: 0; background: #eef4fb; color: #172033; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
    main { width: min(1240px, calc(100% - 40px)); margin: 34px auto; }
    h1 { margin: 0 0 8px; font-size: 34px; letter-spacing: 0; }
    p { color: #667085; }
    .stats { display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px; margin: 24px 0; }
    .stat { background: #fff; border: 1px solid #d9e0ea; border-radius: 8px; padding: 16px; box-shadow: 0 14px 40px rgba(20, 33, 61, .08); }
    .stat strong { display: block; font-size: 26px; }
    .stat span { color: #667085; font-size: 12px; }
    table { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #d9e0ea; box-shadow: 0 18px 50px rgba(20, 33, 61, .1); }
    th, td { text-align: left; border-bottom: 1px solid #e6ebf2; padding: 12px 14px; vertical-align: top; font-size: 13px; }
    th { background: #132c52; color: #fff; position: sticky; top: 0; }
    a { color: #2f61e8; font-weight: 800; text-decoration: none; }
    small { display: block; color: #667085; margin-top: 4px; }
    @media (max-width: 900px) { .stats { grid-template-columns: repeat(2, 1fr); } table { font-size: 12px; } }
  </style>
</head>
<body>
  <main>
    <h1>CV Rewrite Evaluation</h1>
    <p>Generated from ${html(cvDir)}. Each linked file is a modern tailored CV preview plus rewrite QA.</p>
    <div class="stats">
      <div class="stat"><strong>${parsed.length}/${results.length}</strong><span>CVs parsed</span></div>
      <div class="stat"><strong>${totals.bullets}</strong><span>bullets evaluated</span></div>
      <div class="stat"><strong>${totals.weak}</strong><span>weak points found</span></div>
      <div class="stat"><strong>${totals.changed}</strong><span>rewrites made</span></div>
      <div class="stat"><strong>${totals.edge}</strong><span>edge-case flags</span></div>
    </div>
    <table>
      <thead><tr><th>CV</th><th>Target Lens</th><th>Bullets</th><th>Weak</th><th>Changed</th><th>Safe</th><th>Edge Cases</th></tr></thead>
      <tbody>${rows}</tbody>
    </table>
  </main>
</body>
</html>`;
}

function main() {
  fs.mkdirSync(outDir, { recursive: true });
  fs.readdirSync(outDir)
    .filter((file) => /\.(?:html|json)$/i.test(file))
    .forEach((file) => {
      fs.unlinkSync(path.join(outDir, file));
    });
  const files = fs
    .readdirSync(cvDir)
    .filter((file) => /\.(pdf|docx?|txt)$/i.test(file))
    .map((file) => path.join(cvDir, file));
  const results = files.map(evaluateFile);
  fs.writeFileSync(path.join(outDir, "index.html"), renderIndex(results));
  fs.writeFileSync(path.join(outDir, "evaluation-report.json"), JSON.stringify({ cvDir, outDir, generatedAt: new Date().toISOString(), results }, null, 2));
  const parsed = results.filter((item) => !item.failed);
  const totals = parsed.reduce(
    (sum, item) => {
      sum.bullets += item.stats.bullets;
      sum.weak += item.stats.weakBullets;
      sum.changed += item.stats.changedBullets;
      sum.safe += item.stats.safeRewrites;
      sum.edge += item.edgeCases.length;
      return sum;
    },
    { bullets: 0, weak: 0, changed: 0, safe: 0, edge: 0 }
  );
  console.log(
    `Evaluated ${parsed.length}/${files.length} CVs, ${totals.bullets} bullets, ${totals.weak} weak points, ${totals.changed} rewrites, ${totals.edge} edge-case flags. Output: ${outDir}`
  );
}

main();
