#!/usr/bin/env node
"use strict";

const fs = require("fs");
const path = require("path");
const { spawnSync } = require("child_process");

const cvDir = process.argv[2] || "/Users/ropafadzoyasheushe/Downloads/CVs";
const outFile =
  process.argv[3] ||
  path.join(process.cwd(), "reports", "cv-rewrite-ontology-training-report.json");
const ontologyFile = path.join(process.cwd(), "assets", "data", "cv-rewrite-ontology.json");

const weakVerbMap = new Map([
  ["aided", "supported"],
  ["aimed to", "focused on"],
  ["assisted", "supported"],
  ["assisted in", "supported"],
  ["assisted with", "supported"],
  ["collaborated on", "contributed to"],
  ["conduct", "conducted"],
  ["dealt with", "managed"],
  ["did", "delivered"],
  ["duties included", "delivered"],
  ["facilitated", "coordinated"],
  ["got", "secured"],
  ["had to", "delivered"],
  ["handled", "managed"],
  ["helped", "supported"],
  ["helped with", "supported"],
  ["involved in", "contributed to"],
  ["looked after", "managed"],
  ["made", "created"],
  ["participated in", "contributed to"],
  ["performed activities", "executed"],
  ["performed duties", "executed"],
  ["performed tasks", "executed"],
  ["performed work", "executed"],
  ["played a role in", "contributed to"],
  ["provided support", "supported"],
  ["responsible for", "managed"],
  ["tasked with", "delivered"],
  ["support", "supported"],
  ["took care of", "managed"],
  ["was assigned", "delivered"],
  ["was asked", "delivered"],
  ["was given", "delivered"],
  ["worked on", "contributed to"],
  ["working on", "contributing to"],
]);

const weakLanguagePatterns = [
  {
    key: "weak_opening_verb",
    pattern:
      /^(?:helped|assisted|aided|supported|worked on|working on|participated in|involved in|handled|dealt with|did|made|got|performed\s+(?:tasks?|duties|work|activities)|facilitated|responsible for|tasked with|provided support|took care of)\b/i,
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
    pattern: /\b(?:successfully|effectively|efficiently|proactively|actively|strong|excellent|good|dynamic|innovative|hard[-\s]?working|team player|detail[-\s]?oriented)\b/i,
  },
  {
    key: "buzzword_overload",
    pattern: /\b(?:synergy|synergies|best[-\s]?in[-\s]?class|world[-\s]?class|cutting[-\s]?edge|leverage|leveraged|fast[-\s]?paced|results[-\s]?driven|self[-\s]?starter)\b/i,
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

const domainPatterns = [
  {
    key: "investment",
    pattern:
      /\b(?:investment|valuation|financial model|financial modelling|financial modeling|lbo|dcf|m&a|merger|acquisition|transaction|deal|due diligence|portfolio|private equity|venture capital|capital markets|credit|equity research)\b/i,
  },
  {
    key: "business_development",
    pattern:
      /\b(?:business development|sales pipeline|pipeline|leads|prospect|client|account|crm|partnership|go-to-market|commercial|revenue|market expansion)\b/i,
  },
  {
    key: "operations",
    pattern:
      /\b(?:operations|process|workflow|automation|efficiency|stakeholder|reporting|compliance|controls|reconciliation|accounts payable|accounts receivable)\b/i,
  },
  {
    key: "technology",
    pattern:
      /\b(?:python|sql|tableau|power bi|excel|vba|automation|data|analytics|ai|machine learning|sap|bloomberg|capital iq|factset|refinitiv)\b/i,
  },
];

function extractText(file) {
  const ext = path.extname(file).toLowerCase();
  const command =
    ext === ".pdf"
      ? ["pdftotext", file, "-"]
      : ext === ".docx" || ext === ".doc"
      ? ["textutil", "-convert", "txt", "-stdout", file]
      : [];
  if (!command.length) return { text: "", error: "unsupported" };
  const result = spawnSync(command[0], command.slice(1), {
    encoding: "utf8",
    maxBuffer: 1024 * 1024 * 12,
  });
  if (result.error || result.status !== 0) {
    return {
      text: "",
      error: result.error ? result.error.message : result.stderr || "extract_failed",
    };
  }
  return {
    text: String(result.stdout || "").replace(/[•▪●○◦]\s*/g, "\n• "),
    error: "",
  };
}

function cleanLine(line) {
  return String(line || "")
    .replace(/\s+/g, " ")
    .replace(/^[•▪●○◦\-–—]+\s*/, "")
    .trim();
}

function isLikelyBullet(line) {
  const raw = String(line || "");
  const clean = cleanLine(raw);
  return (
    /^[•▪●○◦\-–—]\s+/.test(raw) ||
    /\b(?:analy[sz]ed|built|prepared|managed|led|executed|supported|conducted|developed|created|streamlined|monitored|evaluated|sourced|qualified|coordinated|facilitated|validated|performed|provided|contributed|improved|reduced|increased)\b/i.test(clean)
  );
}

function isLikelyHeading(line) {
  const clean = cleanLine(line);
  return (
    clean.length <= 70 &&
    (/^[A-Z][A-Z\s/&,-]+$/.test(clean) ||
      /^(?:summary|profile|education|work experience|professional experience|experience|skills|certifications|awards|projects|other interests)/i.test(clean))
  );
}

function mergeWrappedCvLines(lines) {
  const merged = [];
  lines.map(cleanLine).filter(Boolean).forEach((line) => {
    const previous = merged[merged.length - 1] || "";
    const startsNewBullet = /^[•▪●○◦\-–—]\s+/.test(line);
    const previousLooksOpen =
      previous &&
      !/[.;:)]$/.test(previous) &&
      previous.length < 260 &&
      !isLikelyHeading(previous) &&
      (isLikelyBullet(previous) || /^[a-z(]/.test(line) || /[,;:]$/.test(previous));
    if (
      merged.length &&
      previousLooksOpen &&
      !startsNewBullet &&
      !isLikelyHeading(line)
    ) {
      merged[merged.length - 1] = cleanLine(previous + " " + line);
      return;
    }
    merged.push(line);
  });
  return merged;
}

function isReusableArchetypeCandidate(text) {
  const clean = cleanLine(text);
  const words = clean.split(/\s+/).filter(Boolean);
  if (words.length < 7 || words.length > 42) return false;
  if (/[,;:]$/.test(clean)) return false;
  if (/@|www\.|linkedin|phone|e-mail|email/i.test(clean)) return false;
  if (/(?:additional skills|proficiency|education|certifications|other interests)/i.test(clean)) return false;
  if (/(?:\b(?:London|Mumbai|Dubai|Bangalore|Barcelona|Milan|Gurgaon|Sofia|Toronto|Geneva)\b.*,.*\b(?:Intern|Associate|Analyst|Manager)\b)/i.test(clean)) {
    return false;
  }
  if ((clean.match(/\b(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+\[?METRIC\]?/gi) || []).length > 1) {
    return false;
  }
  if ((clean.match(/\b(?:intern|analyst|associate|manager|director)\b/gi) || []).length > 2 && !/^(?:led|managed|developed|executed|prepared|analy[sz]ed|built|streamlined|validated|sourced)\b/i.test(clean)) {
    return false;
  }
  if (/[a-z0-9)]\s+(?:Led|Managed|Developed|Executed|Prepared|Analy[sz]ed|Built|Streamlined|Validated|Sourced|Backtested|Compiled|Created|Conducted)\s/.test(clean)) {
    return false;
  }
  return true;
}

function classifyDomain(text) {
  const matched = domainPatterns
    .filter((item) => item.pattern.test(text))
    .map((item) => item.key);
  return matched.length ? matched : ["general"];
}

function getOpeningVerb(text) {
  const clean = cleanLine(text).toLowerCase();
  const phrase = Array.from(weakVerbMap.keys())
    .sort((left, right) => right.length - left.length)
    .find((verb) => clean.startsWith(verb + " "));
  if (phrase) return phrase;
  const match = clean.match(/^([a-z]+(?:ed|ing)?)/);
  return match ? match[1] : "";
}

function detectWeakLanguage(text) {
  const clean = cleanLine(text);
  return weakLanguagePatterns
    .filter((item) => item.pattern.test(clean))
    .map((item) => item.key);
}

function abstractBullet(text) {
  return cleanLine(text)
    .replace(/\b(?:\+?\d+(?:[.,]\d+)?%?|\$[0-9.,]+[mbk]?|€\s*\+?[0-9.,]+[a-z]*|£[0-9.,]+[mbk]?|[0-9.,]+\s*(?:million|billion|hours|teams|clients|leads|start-?ups|transactions|investments|facilities|countries|markets|years))\b/gi, "[METRIC]")
    .replace(/\b(?:LBO|DCF|SQL|Python|Tableau|Excel|VBA|SAP|Bloomberg|Capital IQ|FactSet|Refinitiv|Power BI)\b/g, "[TOOL]")
    .replace(/\b(?:investment committee|senior leadership|c-level executives|stakeholders|clients|management)\b/gi, "[STAKEHOLDER]")
    .replace(/\s+/g, " ")
    .trim();
}

function scoreBullet(text) {
  let score = 0;
  if (/\b(?:led|built|executed|managed|developed|analy[sz]ed|evaluated|streamlined|validated|sourced|monitored)\b/i.test(text)) score += 2;
  if (/\b(?:\+?\d|%|\$|€|£|million|billion|hours|transactions|clients|leads|start-?ups|facilities)\b/i.test(text)) score += 2;
  if (/\b(?:using|leveraging|through|via|across|for|supporting|resulting|contributing)\b/i.test(text)) score += 1;
  if (weakVerbMap.has(getOpeningVerb(text))) score -= 1;
  return score;
}

function isCompleteTrainingBullet(text) {
  const clean = cleanLine(text);
  if (clean.split(/\s+/).length < 7) return false;
  if (/[,;:]$/.test(clean)) return false;
  if (/\b(?:and|or|for|to|with|through|across|including|using|by|in|of|a|the)$/i.test(clean)) {
    return false;
  }
  return true;
}

function rewriteForSafetyTest(text) {
  let rewritten = cleanLine(text);
  Array.from(weakVerbMap.entries()).some(([weak, strong]) => {
    const pattern = new RegExp(`^${weak}\\b`, "i");
    if (pattern.test(rewritten)) {
      rewritten = rewritten.replace(pattern, strong.charAt(0).toUpperCase() + strong.slice(1));
      return true;
    }
    return false;
  });
  if (!/[.;]$/.test(rewritten)) rewritten += ".";
  return rewritten;
}

function collectMetrics(text) {
  return (
    cleanLine(text).match(
      /(?:\+?\d+(?:[.,]\d+)?%?|[$€£]\s*\+?[0-9.,]+\s*(?:m|mn|mln|bn|billion|million)?|[0-9.,]+\s*(?:transactions|investments|leads|start-?ups|ventures|countries|markets|hours|facilities|clients|companies|teams))/gi
    ) || []
  ).map(cleanLine);
}

function metricsArePreserved(source, rewritten) {
  const sourceMetrics = collectMetrics(source);
  const rewrittenLower = rewritten.toLowerCase();
  return sourceMetrics.every((metric) => rewrittenLower.includes(metric.toLowerCase()));
}

function topObjectEntries(object, limit) {
  return Object.entries(object || {})
    .sort((left, right) => right[1] - left[1])
    .slice(0, limit)
    .map(([key, count]) => ({ key, count }));
}

function main() {
  const files = fs
    .readdirSync(cvDir)
    .filter((file) => /\.(pdf|docx?|txt)$/i.test(file))
    .map((file) => path.join(cvDir, file));
  const report = {
    cvDir,
    generatedAt: new Date().toISOString(),
    filesSeen: files.length,
    filesParsed: 0,
    filesFailed: 0,
    bulletsSeen: 0,
    strongBullets: 0,
    weakOpeners: {},
    weakLanguage: {},
    domains: {},
    archetypes: {},
    examples: [],
    tests: {
      passed: false,
      checks: [],
    },
  };

  files.forEach((file) => {
    const extracted = extractText(file);
    if (!extracted.text) {
      report.filesFailed += 1;
      return;
    }
    report.filesParsed += 1;
    const lines = mergeWrappedCvLines(extracted.text.split(/\r?\n/));
    lines.filter(isLikelyBullet).forEach((line) => {
      if (!isCompleteTrainingBullet(line)) {
        return;
      }
      const score = scoreBullet(line);
      const domains = classifyDomain(line);
      const opener = getOpeningVerb(line);
      const archetype = abstractBullet(line);
      report.bulletsSeen += 1;
      if (score >= 3) report.strongBullets += 1;
      if (weakVerbMap.has(opener)) {
        report.weakOpeners[opener] = (report.weakOpeners[opener] || 0) + 1;
      }
      detectWeakLanguage(line).forEach((issue) => {
        report.weakLanguage[issue] = (report.weakLanguage[issue] || 0) + 1;
      });
      domains.forEach((domain) => {
        report.domains[domain] = (report.domains[domain] || 0) + 1;
      });
      if (score >= 3 && isReusableArchetypeCandidate(line)) {
        report.archetypes[archetype] = (report.archetypes[archetype] || 0) + 1;
        if (report.examples.length < 40) {
          report.examples.push({
            file: path.basename(file),
            domain: domains[0],
            score,
            source: line,
            archetype,
          });
        }
      }
    });
  });

  const rewriteSafetySample = report.examples.slice(0, 25).map((item) => {
    const rewritten = rewriteForSafetyTest(item.source);
    return {
      source: item.source,
      rewritten,
      metricsPreserved: metricsArePreserved(item.source, rewritten),
    };
  });
  report.rewriteSafetySample = rewriteSafetySample;
  report.tests.checks = [
    {
      name: "all_supported_files_parsed",
      passed: report.filesParsed === report.filesSeen && report.filesFailed === 0,
      actual: `${report.filesParsed}/${report.filesSeen}`,
    },
    {
      name: "bullet_extraction_coverage",
      passed: report.bulletsSeen >= 500,
      actual: report.bulletsSeen,
    },
    {
      name: "strong_pattern_library_size",
      passed: report.strongBullets >= 100,
      actual: report.strongBullets,
    },
    {
      name: "domain_coverage",
      passed: ["investment", "business_development", "operations", "technology"].every(
        (domain) => Number(report.domains[domain] || 0) > 0
      ),
      actual: report.domains,
    },
    {
      name: "rewrite_safety_metrics_preserved",
      passed: rewriteSafetySample.every((item) => item.metricsPreserved),
      actual: rewriteSafetySample.filter((item) => !item.metricsPreserved).length,
    },
  ];
  report.tests.passed = report.tests.checks.every((check) => check.passed);
  const ontology = {
    generatedAt: report.generatedAt,
    source: {
      cvDir,
      filesParsed: report.filesParsed,
      bulletsSeen: report.bulletsSeen,
      strongBullets: report.strongBullets,
    },
    domains: topObjectEntries(report.domains, 12),
    weakOpeners: topObjectEntries(report.weakOpeners, 20),
    weakLanguage: topObjectEntries(report.weakLanguage, 20),
    sentenceArchetypes: topObjectEntries(report.archetypes, 80),
    rewriteFamilies: [
      {
        key: "financial_modelling",
        triggers: ["model", "modelling", "modeling", "valuation", "dcf", "lbo", "forecast", "sensitivity"],
        pattern: "[ACTION] [MODEL/ANALYSIS] for [TRANSACTION/COMPANY/PORTFOLIO], supporting [DECISION].",
        evidenceRequired: ["model_or_analysis"],
      },
      {
        key: "due_diligence",
        triggers: ["due diligence", "diligence", "risk analysis", "market-fit", "assessment"],
        pattern: "[ACTION] due diligence across [TARGET/SCOPE], identifying [FINDINGS/RISKS].",
        evidenceRequired: ["diligence_activity"],
      },
      {
        key: "transactions",
        triggers: ["deal", "transaction", "investment", "acquisition", "m&a", "ipo", "structuring", "negotiation"],
        pattern: "[ACTION] transaction execution across [SCOPE], preserving any stated deal values and outcomes.",
        evidenceRequired: ["transaction_reference"],
      },
      {
        key: "business_development",
        triggers: ["pipeline", "leads", "prospect", "client", "sales", "commercial", "partnership"],
        pattern: "[ACTION] commercial pipeline across [MARKET/CLIENTS], supporting growth and stakeholder engagement.",
        evidenceRequired: ["commercial_activity"],
      },
      {
        key: "operations",
        triggers: ["automation", "workflow", "process", "reconciliation", "controls", "compliance"],
        pattern: "[ACTION] operational workflows across [PROCESS/SCOPE], improving control, accuracy, or efficiency where evidenced.",
        evidenceRequired: ["process_activity"],
      },
    ],
    safetyRules: [
      "Never add a metric that is not present in the source bullet.",
      "Do not upgrade to Led/Oversaw/Directed unless ownership is explicit or strongly implied.",
      "Preserve named tools, markets, sectors, stakeholders, and transaction values from the source.",
      "If outcome is missing, only add generic support language when the source already implies decision, growth, control, or efficiency.",
      "Prefer pattern abstraction over copying sentence text from source CVs.",
      "Remove filler words only when the sentence keeps the same factual meaning.",
      "Flag vague quantities for user confirmation instead of inventing exact counts.",
      "Treat generic soft skills as evidence gaps unless they are tied to a concrete action or result.",
    ],
    weakLanguageRules: [
      {
        key: "weak_opening_verb",
        examples: ["helped", "assisted", "worked on", "participated in", "handled"],
        rewriteAction: "replace_with_evidence_safe_action",
      },
      {
        key: "passive_voice",
        examples: ["was assigned", "was asked", "was given responsibility"],
        rewriteAction: "convert_to_active_voice_without_ownership_inflation",
      },
      {
        key: "vague_quantity",
        examples: ["various", "several", "multiple", "many"],
        rewriteAction: "flag_for_quantification_or_preserve_as_multiple",
      },
      {
        key: "filler_language",
        examples: ["successfully", "effectively", "proactively", "strong", "excellent"],
        rewriteAction: "remove_or_replace_with_specific_evidence",
      },
      {
        key: "buzzword_overload",
        examples: ["synergies", "best-in-class", "cutting-edge", "results-driven"],
        rewriteAction: "replace_with_concrete_business_language",
      },
      {
        key: "generic_responsibility",
        examples: ["responsible for", "duties included", "exposure to", "familiar with"],
        rewriteAction: "replace_with_specific_action_or_preserve_as_gap",
      },
      {
        key: "unsupported_soft_skill",
        examples: ["strong communication skills", "excellent leadership skills"],
        rewriteAction: "tie_to_concrete_evidence_or_remove",
      },
      {
        key: "first_person_language",
        examples: ["I managed", "my responsibilities", "we delivered"],
        rewriteAction: "convert_to_cv_style_action_language",
      },
    ],
  };

  fs.mkdirSync(path.dirname(outFile), { recursive: true });
  fs.writeFileSync(outFile, JSON.stringify(report, null, 2));
  fs.mkdirSync(path.dirname(ontologyFile), { recursive: true });
  fs.writeFileSync(ontologyFile, JSON.stringify(ontology, null, 2));
  console.log(
    `Parsed ${report.filesParsed}/${report.filesSeen} CVs, extracted ${report.bulletsSeen} bullets, tests ${report.tests.passed ? "passed" : "failed"}, wrote ${outFile} and ${ontologyFile}`
  );
  if (!report.tests.passed) {
    process.exitCode = 1;
  }
}

main();
