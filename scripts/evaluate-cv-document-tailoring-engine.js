#!/usr/bin/env node
"use strict";

const fs = require("fs");
const path = require("path");
const { spawnSync } = require("child_process");

const defaultCvDir = "/Users/ropafadzoyasheushe/Downloads/CVs";
const downloads = "/Users/ropafadzoyasheushe/Downloads";
const defaultExtraFiles = [
  "hiredCV-SkillFarm.pdf",
  "Resume_rishi_RB (1).pdf",
  "TRY- Resume.pdf",
  "6752e25571a56 - MI.pdf",
  "Matilde Iori CV Review.pdf",
  "Hugo Geffroy - Resume Checker Results.pdf",
  "674f35c95dcab - HG.pdf",
  "Hugo Geffroy - CV Review.pdf",
  " Mrunmayee Dhumal - Resume Checker Results.pdf",
  "CV_Mrunmayee Dhumall (1).pdf",
  "CV_Mrunmayee Dhumall.pdf",
  "IMG_6669-combined_1.pdf",
  "Aida - Resume Checker Results.pdf",
  "6752ff5c738e9 - AIDA.pdf",
  "Aida Azzouni - CV Review.pdf",
  "Maria Padierna Resume Checker Results.pdf",
  "Maria Padierna CV Review 2024 (1).pdf",
  "CV_ENG_MARIAPADIERNA2024.pdf",
  "Tom Ruchier - Resume Checker Results.pdf",
  "Tom Berquet - ATS.pdf",
  "Tom Ruchier-Berquet CV Review.pdf",
  "christopher clowes - ATS.pdf",
  "Christopher Clowes Resume Review.pdf",
  "Salman Tanveer - resume Checker Results.pdf",
  "SalVVS.pdf",
  "Salman Tanveer CV Review.pdf",
  "Daniel Schessler CV Review.pdf",
  "Darya Selicka - Resume Checker Results.pdf",
  "darya selicka CV.pdf",
  "Darya Selicka Resume Review.pdf",
  "Israel Elanga Resume Checker Results.pdf",
].map((file) => path.join(downloads, file));

const outDir =
  process.argv[2] ||
  path.join(process.cwd(), "reports", "cv-document-tailoring-evaluation");

const roleLens = {
  title: "Business Development & Operations Senior Associate",
  company: "Senna Target Employer",
  keywords: [
    "business development",
    "operations",
    "financial modelling",
    "valuation",
    "market research",
    "performance monitoring",
    "stakeholder management",
    "reporting",
  ],
};

const sectionMap = [
  ["summary", /^(summary|profile|professional summary|personal profile|career profile|objective|about me|chi sono)$/i],
  ["education", /^(education|academic background|qualifications|formations?|formation|formazione)$/i],
  ["experience", /^(work experience|professional experience|professional experience and leadership|experience|experiences|employment|employment history|career history|work history|organisational experience|organizational experience|internships?|experiences professionnelles|expériences professionnelles|esperienze lavorative)$/i],
  ["skills", /^(skills|technical skills|core skills|key skills|additional skills|comp[eé]tences(?: et centres d.?int[eé]r[eê]t)?|competenze tecniche)$/i],
  ["projects", /^(projects|selected projects|selected transactions|transactions)$/i],
  ["languages", /^(languages|language skills|langues|lingue)$/i],
  ["interests", /^(interests|additional information|extracurricular activities|activities|centres d.?int[eé]r[eê]t|dati personali)$/i],
];

const weakVerbRules = [
  [/^\s*provided support\b/i, "Supported"],
  [/^\s*provided assistance\b/i, "Supported"],
  [/^\s*played a role in\b/i, "Contributed to"],
  [/^\s*was involved in\b/i, "Contributed to"],
  [/^\s*participated in\b/i, "Contributed to"],
  [/^\s*helped with\b/i, "Supported"],
  [/^\s*helped\b/i, "Supported"],
  [/^\s*assisted in\b/i, "Supported"],
  [/^\s*assisted with\b/i, "Supported"],
  [/^\s*assisted\b/i, "Supported"],
  [/^\s*worked on\b/i, "Contributed to"],
  [/^\s*responsible for\b/i, "Managed"],
  [/^\s*tasked with\b/i, "Delivered"],
  [/^\s*duties included\b/i, "Delivered"],
  [/^\s*handled\b/i, "Managed"],
  [/^\s*dealt with\b/i, "Managed"],
  [/^\s*made\b/i, "Created"],
  [/^\s*did\b/i, "Delivered"],
  [/^\s*got\b/i, "Secured"],
  [/^\s*performed\s+(?:tasks?|duties|work|activities)\b/i, "Executed"],
  [/^\s*perform\b/i, "Performed"],
  [/^\s*ensure\b/i, "Ensured"],
  [/^\s*facilitate\b/i, "Facilitated"],
  [/^\s*facilitated\b/i, "Coordinated"],
  [/^\s*review\b/i, "Reviewed"],
  [/^\s*guiding\b/i, "Guided"],
  [/^\s*enabling\b/i, "Enabled"],
  [/^\s*get experience with\b/i, "Gained experience with"],
  [/^\s*pmo for\b/i, "Supported project management for"],
  [/^\s*conducting\b/i, "Conducted"],
  [/^\s*management of\b/i, "Managed"],
  [/^\s*identification of\b/i, "Identified"],
  [/^\s*analysis of\b/i, "Analyzed"],
  [/^\s*development of\b/i, "Developed"],
  [/^\s*acquiring\b/i, "Acquired"],
  [/^\s*grant\b/i, "Granted"],
  [/^\s*analyze\b/i, "Analyzed"],
  [/^\s*liaise\b/i, "Liaised"],
  [/^\s*support\b/i, "Supported"],
  [/^\s*sviluppo\b/i, "Developed"],
  [/^\s*gestione\b/i, "Managed"],
  [/^\s*politique de\b/i, "Analyzed"],
  [/^\s*mod[eé]lisation\b/i, "Modeled"],
  [/^\s*conduct\b/i, "Conducted"],
];

const weakPatterns = [
  ["weak_opening", /^(helped|assisted|worked on|participated in|handled|responsible for|duties included|tasked with)\b/i],
  ["passive_voice", /\b(was|were)\s+(assigned|asked|given|tasked|responsible|involved|required|expected)\b/i],
  ["vague_quantity", /\b(various|several|multiple|many|numerous|some|a number of|a variety of|different|wide range of)\b/i],
  ["filler", /\b(successfully|effectively|efficiently|proactively|actively|strong|excellent|good|great|dynamic|innovative|hard[-\s]?working|team player|detail[-\s]?oriented)\b/i],
  ["generic_responsibility", /\b(responsible for|duties included|tasks included|day to day|day-to-day|exposure to|familiar with)\b/i],
  ["missing_outcome", /^(?!.*\b(supporting|support|resulting|contributing|improving|reducing|increasing|accelerating|enhancing|informing|inform|driving|enabling|achieving|delivering|ensuring|saving|generating|securing|facilitating|facilitate|to\s+(?:inform|support|improve|reduce|increase|enhance|drive|enable|deliver|facilitate))\b).{45,}$/i],
];

const families = [
  ["due_diligence", "Due Diligence", /\b(due diligence|diligence|risk analysis|market-fit|assessment|review)\b/i, "due diligence workstreams", "surfacing risks, findings and decision-relevant insight"],
  ["financial_modelling", "Financial Modelling", /\b(model|modelling|modeling|valuation|dcf|lbo|forecast|sensitivity|scenario|financial analysis)\b/i, "financial analysis", "supporting investment and business decision-making"],
  ["transactions", "Transactions", /\b(deals?|transactions?|investments?|acquisitions?|m&a|mergers?|ipo|structuring|negotiation|capital raise)\b/i, "transaction analysis", "supporting deal evaluation and senior review"],
  ["business_development", "Business Development", /\b(pipeline|leads|prospect|client|sales|commercial|partnership|revenue|crm|market expansion)\b/i, "commercial pipeline activity", "supporting growth and stakeholder engagement"],
  ["operations", "Operations", /\b(automation|workflow|process|streamlined|efficiency|reconciliation|control|compliance|operations|kpi|reporting)\b/i, "operational and reporting workflows", "improving clarity, control and execution consistency"],
  ["technology", "Technology & Analytics", /\b(python|sql|tableau|power bi|excel|vba|automation|data|analytics|machine learning|sap|bloomberg|capital iq|factset)\b/i, "data and analytical workflows", "improving insight generation and decision support"],
];

function html(value) {
  return String(value == null ? "" : value)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}

function clean(value) {
  return String(value || "")
    .replace(/\u0000/g, "")
    .replace(/[•▪●○◦❖]/g, " • ")
    .replace(/\s+/g, " ")
    .replace(/^\s*[•▪●○◦❖\-–—]+\s*/, "")
    .trim();
}

function slug(value) {
  return clean(value)
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "")
    .slice(0, 86) || "cv";
}

function isReviewFile(file) {
  return /\b(review|checker|ats|results)\b/i.test(path.basename(file));
}

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
    maxBuffer: 1024 * 1024 * 24,
  });
  if (result.error || result.status !== 0) {
    return { text: "", error: result.error ? result.error.message : result.stderr || "extract_failed" };
  }
  return { text: String(result.stdout || ""), error: "" };
}

function mergeLines(text) {
  const lines = [];
  String(text || "").split(/\r?\n/).forEach((raw) => {
    const startsBullet = /^[\s•▪●○◦❖\-–—]+/.test(String(raw || "")) && /[•▪●○◦❖\-–—]/.test(String(raw || "").slice(0, 6));
    const cleanLine = clean(raw);
    const line = startsBullet ? "• " + cleanLine : cleanLine;
    if (!cleanLine) return;
    const previous = lines[lines.length - 1] || "";
    const bulletContinuation =
      previous &&
      /^•\s+/.test(previous) &&
      !startsBullet &&
      !isHeading(line) &&
      !hasDate(line) &&
      !looksExperienceRoleLine(line) &&
      previous.length < 360;
    const merge =
      bulletContinuation ||
      (previous &&
      !startsBullet &&
      !isHeading(line) &&
      !/[.;:)]$/.test(previous) &&
      previous.length < 240 &&
      (/[,;:]$/.test(previous) ||
        /^[a-z(]/.test(line) ||
        (/^•\s+/.test(previous) && !hasDate(line) && !looksExperienceRoleLine(line) && !looksCompanyLine(line))));
    if (merge) {
      lines[lines.length - 1] = /^•\s+/.test(previous)
        ? "• " + clean(previous + " " + line)
        : clean(previous + " " + line);
      return;
    }
    lines.push(line);
  });
  return lines;
}

function getSectionKey(line) {
  const normalized = clean(line).replace(/[:\-–—]+$/g, "");
  const hit = sectionMap.find((entry) => entry[1].test(normalized));
  if (hit) return hit[0];
  return "";
}

function isHeading(line) {
  return !!getSectionKey(line);
}

function parseSections(lines) {
  const sections = [{ key: "header", title: "Header", lines: [] }];
  lines.forEach((line) => {
    const key = getSectionKey(line);
    if (key && sections[sections.length - 1].lines.length) {
      sections.push({ key, title: clean(line), lines: [] });
      return;
    }
    sections[sections.length - 1].lines.push(line);
  });
  return sections;
}

function detectEmail(text) {
  const match = String(text || "").match(/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i);
  return match ? match[0].toLowerCase() : "";
}

function nameFromEmail(email, lines) {
  const local = String(email || "").split("@")[0] || "";
  const compact = local.replace(/[0-9]+/g, "").toLowerCase();
  if (!compact || compact.length < 5) return "";
  const lineHit = lines.slice(0, 20).find((line) => {
    const letters = clean(line).replace(/[^a-z]/gi, "").toLowerCase();
    return letters.length >= 5 && (letters.includes(compact) || compact.includes(letters));
  });
  if (lineHit) {
    const candidate = clean(lineHit)
      .replace(/\b(?:phd|mba|msc|bsc|aca|cfa)\b\.?/gi, "")
      .replace(/[,|].*$/g, "")
      .replace(/[^A-Za-zÀ-ÿ'’.\-\s]/g, " ")
      .replace(/\s+/g, " ")
      .trim();
    if (candidate.split(/\s+/).length >= 2 && candidate.split(/\s+/).length <= 5) {
      return candidate;
    }
  }
  const tokens = local
    .replace(/[0-9]+/g, " ")
    .split(/[._+\-]+/)
    .map(clean)
    .filter((token) => token.length >= 2)
    .slice(0, 3);
  if (tokens.length >= 2) {
    return tokens.map((token) => token.charAt(0).toUpperCase() + token.slice(1).toLowerCase()).join(" ");
  }
  return "";
}

function detectPhone(text) {
  const match = String(text || "").match(/(?:\+?\d[\d\s().-]{7,}\d)/);
  return match ? clean(match[0]) : "";
}

function extractNameFromFileName(file) {
  const rejected = /^(cv|resume|review|checker|results|ats|updated|final|draft|copy|complete|skillfarm|skill|farm|flowcv|for|credit)$/i;
  const base = clean(path.basename(file, path.extname(file)))
    .replace(/\([^)]*\)/g, " ")
    .replace(/\b(19|20)\d{2,}\b/g, " ")
    .replace(/[_-]+/g, " ")
    .replace(/\b(cv|resume|review|checker|results|ats|updated|final|draft|copy|complete|skillfarm|skill farm|flowcv|for credit)\b/gi, " ")
    .replace(/\s+/g, " ")
    .trim();
  const words = base
    .split(/\s+/)
    .filter((word) => word && !rejected.test(word) && !/^\d+$/.test(word))
    .slice(0, 5);
  if (words.length < 2) return "";
  const candidate = words
    .map((word) => {
      if (/^[A-Z]{2,}$/.test(word)) return word.charAt(0) + word.slice(1).toLowerCase();
      return word.charAt(0).toUpperCase() + word.slice(1);
    })
    .join(" ");
  return /\b(resume|review|checker|results|ats)\b/i.test(candidate) ? "" : candidate;
}

function detectName(lines, file, email) {
  const rejected = /@|www|linkedin|resume|curriculum|vitae|education|experience|summary|profile|skills|analyst|associate|manager|engineer|consultant|university|school|college/i;
  const split = lines.slice(0, 8).reduce((found, line, index, list) => {
    if (found) return found;
    const next = clean(list[index + 1] || "");
    if (/^[A-Z][A-Z'’-]{2,}$/.test(line) && /^[A-Z][A-Z'’-]{2,}$/.test(next) && !rejected.test(line + " " + next)) {
      return `${line} ${next}`;
    }
    return "";
  }, "");
  if (split) return split;
  const topLineName = lines.slice(0, 12).find((line) => {
    const candidate = clean(line)
      .replace(/\b(?:phd|mba|msc|bsc|aca|cfa)\b\.?/gi, "")
      .replace(/[,|].*$/g, "")
      .trim();
    const words = candidate.split(/\s+/).filter(Boolean);
    return words.length >= 2 && words.length <= 5 && candidate.length <= 70 && !rejected.test(candidate) && words.every((word) => /^[A-ZÀ-Ý][A-Za-zÀ-ÿ'’.-]+$/.test(word) || /^[A-ZÀ-Ý]{2,}$/.test(word));
  });
  if (topLineName) {
    return clean(topLineName)
      .replace(/\b(?:phd|mba|msc|bsc|aca|cfa)\b\.?/gi, "")
      .replace(/[,|].*$/g, "")
      .trim();
  }
  return (
    lines.slice(0, 12).find((line) => {
      const words = clean(line).split(/\s+/).filter(Boolean);
      return words.length >= 2 && words.length <= 5 && line.length <= 70 && !rejected.test(line) && words.every((word) => /^[A-ZÀ-Ý][A-Za-zÀ-ÿ'’.-]+$/.test(word));
    }) || extractNameFromFileName(file) || nameFromEmail(email, lines) || "Candidate Name"
  );
}

function hasDate(line) {
  return /\b(19|20)\d{2}\b|\b(present|current)\b|\b(?:jan|january|feb|february|mar|march|apr|april|may|jun|june|jul|july|aug|august|sep|sept|september|oct|october|nov|november|dec|december)\b/i.test(line);
}

function dateText(line) {
  const value = clean(line);
  let match = value.match(/\b(?:19|20)\d{2}[./-]\d{1,2}\s*(?:[-–—]|to)\s*(?:present|current|(?:19|20)\d{2}[./-]\d{1,2})\b/i);
  if (!match) {
    match = value.match(/\b(?:(?:(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Sept|Oct|Nov|Dec)[a-z]*|\d{1,2})[ ./-]+)?(?:19|20)\d{2}\s*(?:[-–—]|to)\s*(?:present|current|now|(?:(?:(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Sept|Oct|Nov|Dec)[a-z]*|\d{1,2})[ ./-]+)?(?:19|20)\d{2})\b/i);
  }
  return match ? clean(match[0]).replace(/\b(?:current|now)\b/i, "Present") : "";
}

function looseDateText(line) {
  const value = clean(line);
  return (
    dateText(value) ||
    ((value.match(/\b(?:(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Sept|Oct|Nov|Dec)[a-z]*|\d{1,2})[ ./-]+(?:19|20)\d{2}\b/i) || [])[0]) ||
    ((value.match(/\b(?:19|20)\d{2}\b/) || [])[0]) ||
    ""
  );
}

function removeLooseDateText(line, date) {
  const value = clean(line);
  const token = clean(date);
  return token
    ? clean(value.replace(new RegExp(token.replace(/[.*+?^${}()|[\]\\]/g, "\\$&"), "i"), "").replace(/^[|,:;–—-]+|[|,:;–—-]+$/g, ""))
    : value;
}

function looksDateLine(line) {
  const value = clean(line);
  const date = dateText(value);
  if (!date || looksLikeBullet(value)) return false;
  return value.length <= 90 && (date.length / Math.max(value.length, 1) > 0.35 || value.split(/\s+/).length <= 8);
}

function looksLikePersonalOrAdminLine(line) {
  const value = clean(line);
  if (!value) return true;
  if (/@|linkedin|www\.|https?:/i.test(value)) return true;
  if (/^(?:date of birth|date of issue|date of expiry|place of issue|gender|marital status|nationality|passport|visa status|driving licence|address|contact|phone|email|website|linkedin)\b/i.test(value)) return true;
  if (/\b(?:date of birth|date of issue|date of expiry|place of issue|gender|marital status|nationality|passport|visa status)\b/i.test(value)) return true;
  if (/^(?:available|availability|notice period|references available|current location|location)\b/i.test(value)) return true;
  if (/^(?:jan|feb|mar|apr|may|jun|jul|aug|sep|sept|oct|nov|dec)[a-z]*\.?\s+(?:19|20)\d{2}\b/i.test(value)) return true;
  if (/^\[?\s*(?:jan|feb|mar|apr|may|jun|jul|aug|sep|sept|oct|nov|dec)[a-z]*\.?\s+(?:19|20)\d{2}\s*(?:-|–|—|to)\s*(?:present|current|(?:jan|feb|mar|apr|may|jun|jul|aug|sep|sept|oct|nov|dec)[a-z]*\.?\s+(?:19|20)\d{2}|(?:19|20)\d{2})\s*\]?$/i.test(value)) return true;
  if (/^(?:course|courses|certificate|certification|education|training|project|language|languages|skills|interests|hobbies|profile|summary)\b/i.test(value)) return true;
  if (/^(?:portuguese|english|french|german|spanish|arabic|italian|russian|hindi|urdu|mandarin|cantonese)\s+(?:native|fluent|proficient|professional|advanced|intermediate|basic)\b/i.test(value)) return true;
  if (/\b(?:native|fluent|proficient|professional working proficiency|advanced|intermediate)\b/i.test(value) && (value.match(/\b(?:english|french|german|spanish|arabic|italian|portuguese|russian|hindi|urdu|mandarin|cantonese)\b/gi) || []).length >= 2) return true;
  return false;
}

function looksLikeUnsafeEvidenceLine(line) {
  const value = clean(line).replace(/^[•▪●○◦❖\-–—]\s*/, "");
  if (!value) return true;
  if (looksLikePersonalOrAdminLine(value)) return true;
  if (looksEducationLine(value) && !/\b(?:taught|trained|researched|analysed|analyzed|built|prepared|developed|managed|led|conducted)\b/i.test(value)) return true;
  if (value.length <= 90 && (/,\s*[A-Z][A-Za-z.'’ -]{2,}$/.test(value) || /\b(remote|hybrid|riyadh|dubai|abu dhabi|doha|london|milan|rome|barcelona|geneva|toronto|paris|new york|singapore|hong kong|india|uae|united kingdom|uk|italy|spain|france|canada|saudi arabia|qatar|germany|switzerland|netherlands)\b/i.test(value))) return true;
  if (/^(?:architecture|architectural|design|urban planning|bsc|msc|mba|matric|degree)\b/i.test(value) && value.split(/\s+/).length < 10) return true;
  if (/^[A-Z][A-Za-zÀ-ÿ'’.-]+(?:\s+[A-Z][A-Za-zÀ-ÿ'’.-]+){6,}$/.test(value) && !/[.,;:]/.test(value)) return true;
  if ((value.match(/\b(?:analyst|associate|manager|director|finance|banking|investment|excel|powerpoint|english|french|degree|university|education|software|retail|logistics)\b/gi) || []).length >= 8 && !/[.,;:]/.test(value)) return true;
  return false;
}

function looksLikeBullet(line) {
  const value = clean(line);
  if (value.length < 24 || value.length > 430 || isHeading(value) || looksLikeUnsafeEvidenceLine(value)) return false;
  return /^[•▪●○◦❖\-–—]\s+/.test(line) || /^(helped|assisted|supported|worked|participated|handled|managed|led|built|prepared|executed|developed|created|streamlined|monitored|evaluated|sourced|coordinated|validated|performed|provided|contributed|improved|reduced|increased|analy[sz]ed|reviewed|delivered|owned|oversaw|directed|applied|conducted|conducting|management of|identification of|analysis of|development of|acquiring|grant|sviluppo|gestione|politique de|mod[eé]lisation)\b/i.test(value);
}

function familyFor(text) {
  const hit = families.find((item) => item[2].test(text));
  return hit ? { key: hit[0], label: hit[1], object: hit[3], outcome: hit[4] } : null;
}

function weakIssues(text) {
  return weakPatterns.filter((item) => item[1].test(text)).map((item) => item[0]);
}

function metrics(text) {
  return clean(text).match(/(?:\+?\d+(?:[.,]\d+)?%?|[$€£]\s*\+?[0-9][0-9.,]*\s*(?:m|mn|mln|bn|billion|million)?|[0-9][0-9.,]*\s*(?:transactions|investments|leads|start-?ups|clients|companies|teams|markets|hours))/gi) || [];
}

function tools(text) {
  return clean(text).match(/\b(?:LBO|DCF|SQL|Python|Tableau|Excel|VBA|SAP|Bloomberg|Capital IQ|FactSet|Refinitiv|Power BI|CRM)\b/gi) || [];
}

function looksNonEnglishSentence(text) {
  const value = ` ${clean(text).toLowerCase()} `;
  const hits = (value.match(/\b(?:di|della|delle|degli|con|per|nel|nella|des|du|avec|pour|aux|dans|sur|données|projets|risques|étude|évaluation|gestione|redazione|monitoraggio|fornitori|contrattualistica|competenze)\b/g) || []).length;
  return hits >= 3;
}

function finishSentence(text) {
  const value = clean(text)
    .replace(/^\s*Supported\s+(Directed|Identified|Acquired|Modeled|Modelled|Performed|Used|Reviewed|Guided|Enabled|Analy[sz]ed|Developed|Conducted|Managed|Spearheaded|Acted|Advised|Demonstrated|Planned|Communicated|Prioriti[sz]ed)\b/i, "$1")
    .replace(/\bGained experience with ([^.!?]+?) as ([^.!?]+?)\.\s*e\.g\.,\s*/i, "Gained experience with $1, including $2, ")
    .replace(/\bGained experience with ([^.!?]+?) as ([^.!?]+?)(?=,|\.)/i, "Gained experience with $1, including $2")
    .replace(/\s+([,.;:])/g, "$1")
    .replace(/\.{2,}/g, ".")
    .replace(/[;:]\./g, ".")
    .replace(/[;:]+$/g, "")
    .replace(/,+$/g, "")
    .replace(/(?:,\s*)?(and|or|with|through|across|including|using|by|in|of|to|for|the|a)\s*\.?$/i, "")
    .trim();
  return value ? (/[.!?]$/.test(value) ? value : `${value}.`) : "";
}

function rewriteBullet(text, targetKeywords) {
  const source = clean(text).replace(/^[A-Z][A-Za-z/& -]{2,42}:\s*/, "");
  if (looksLikeUnsafeEvidenceLine(source)) {
    return {
      original: source,
      rewritten: "",
      family: "unsafe",
      weakIssues: ["unsafe_source_line"],
      metrics: [],
      tools: [],
      changed: false,
    };
  }
  if (looksNonEnglishSentence(source)) {
    const original = finishSentence(source);
    return {
      original: source,
      rewritten: original,
      family: familyFor(source) ? familyFor(source).key : "general",
      weakIssues: [],
      metrics: metrics(source),
      tools: tools(source),
      changed: clean(source) !== clean(original),
    };
  }
  let base = source
    .replace(/\b(successfully|effectively|efficiently|proactively|actively)\b\s*/gi, "")
    .replace(/\b(strong|excellent|good|great)\s+(?=(communication|interpersonal|analytical|teamwork|leadership|problem))/gi, "")
    .replace(/\butili[sz]ed\b/gi, "Used")
    .replace(/\bleveraged\b/gi, "Used")
    .replace(/\bfamiliar with\b/gi, "Used")
    .replace(/\bvarious\b/gi, "relevant");
  weakVerbRules.some(([pattern, replacement]) => {
    if (pattern.test(base)) {
      base = clean(base.replace(pattern, replacement));
      return true;
    }
    return false;
  });
  const family = familyFor(base);
  const sourceMetrics = metrics(source);
  const sourceTools = tools(source);
  const hasOutcome = /\b(supporting|support|resulting|contributing|improving|reducing|increasing|accelerating|enhancing|informing|inform|driving|enabling|ensuring|saving|securing|facilitating|facilitate|to\s+(?:inform|support|improve|reduce|increase|enhance|drive|enable|deliver|facilitate))\b/i.test(base);
  const hasStrongAction = /^(Led|Built|Prepared|Managed|Executed|Developed|Analy[sz]ed|Evaluated|Streamlined|Sourced|Monitored|Validated|Supported|Contributed|Delivered|Improved|Applied|Coordinated|Conducted|Created|Reviewed|Provided|Gained|Performed|Ensured|Facilitated|Guided|Enabled|Used|Identified|Acquired|Modeled|Modelled|Granted|Liaised|Directed|Spearheaded|Acted|Advised|Demonstrated|Planned|Communicated|Prioriti[sz]ed)\b/i.test(base);
  const outcomeBase = base.replace(/[.!?]+$/g, "").trim();
  const candidates = [finishSentence(base)];
  if (family && !hasStrongAction) {
    candidates.push(finishSentence(`Supported ${base}`));
  }
  if (family && !hasOutcome && base.split(/\s+/).length <= 30) {
    candidates.push(finishSentence(`${outcomeBase}, ${family.outcome}`));
    if (!hasStrongAction) {
      candidates.push(finishSentence(`Supported ${outcomeBase}, ${family.outcome}`));
    }
  }
  const selected = candidates
    .filter(Boolean)
    .filter((candidate) => !hasBrokenRewriteText(candidate) && !looksLikeUnsafeRewrite(candidate, source))
    .filter((candidate) => sourceMetrics.every((metric) => candidate.toLowerCase().includes(metric.toLowerCase())))
    .filter((candidate) => tools(candidate).every((tool) => sourceTools.map((item) => item.toLowerCase()).includes(tool.toLowerCase())))
    .sort((a, b) => scoreBullet(b, family, targetKeywords) - scoreBullet(a, family, targetKeywords))[0] || finishSentence(base);
  return {
    original: source,
    rewritten: selected,
    family: family ? family.key : "general",
    weakIssues: weakIssues(source),
    metrics: sourceMetrics,
    tools: sourceTools,
    changed: clean(source) !== clean(selected),
  };
}

function scoreBullet(text, family, targetKeywords) {
  if (hasBrokenRewriteText(text) || looksLikeUnsafeRewrite(text, "")) return -1000;
  let score = 0;
  if (/^(Led|Built|Prepared|Managed|Executed|Developed|Analy[sz]ed|Evaluated|Streamlined|Sourced|Monitored|Validated|Supported|Contributed|Delivered|Improved|Applied|Coordinated|Conducted|Identified|Acquired|Modeled|Modelled|Granted|Liaised|Directed|Spearheaded|Acted|Advised|Demonstrated|Planned|Communicated|Prioriti[sz]ed)\b/.test(text)) score += 20;
  if (family) score += 12;
  if (metrics(text).length) score += 12;
  if (tools(text).length) score += 6;
  if (/\b(supporting|resulting|contributing|improving|reducing|increasing|informing|driving|enabling)\b/i.test(text)) score += 10;
  (targetKeywords || []).forEach((keyword) => {
    if (text.toLowerCase().includes(keyword.toLowerCase())) score += 3;
  });
  score -= weakIssues(text).length * 5;
  score -= Math.abs(text.split(/\s+/).length - 24);
  return score;
}

function extractSkills(lines, targetKeywords) {
  const known = [
    "Financial Modelling", "Valuation", "Due Diligence", "Business Development", "Market Research", "Performance Monitoring",
    "SQL", "Python", "Excel", "VBA", "Tableau", "Power BI", "Bloomberg", "Capital IQ", "CRM", "Reporting", "Stakeholder Management",
  ];
  const text = lines.join(" ");
  return [...new Set(known.concat(targetKeywords).filter((skill) => new RegExp(`\\b${skill.replace(/\s+/g, "\\s+")}\\b`, "i").test(text) || targetKeywords.includes(skill)).slice(0, 16))];
}

function looksEducationLine(line) {
  const value = clean(line);
  if (!value || /@|linkedin|www\./i.test(value)) return false;
  return /\b(university|université|universita|università|universidad|college|school|business school|bsc|msc|mba|ba\b|ma\b|bachelor|master|degree|diploma|gpa|cum laude|honou?rs|economics|finance|accounting|engineering|management|statistics|data science)\b/i.test(value);
}

function looksExperienceRoleLine(line) {
  const value = clean(line);
  if (!value || value.length > 120 || /@|linkedin|www\./i.test(value) || isHeading(value)) return false;
  return /\b(analyst|associate|assistant|manager|director|consultant|intern|internship|officer|specialist|advisor|adviser|lead|controller|accountant|auditor|researcher|engineer|developer|representative|agent|coordinator|executive|banker|trader|asset manager|business development|operations|customer support|call center|claims assessor|commercial finance|commercial manager|wholesale specialist|consultante?|stagiaire|cheffe? de projet|analyste|data analyst|finance|project manager)\b/i.test(value);
}

function looksCompanyLine(line) {
  const value = clean(line);
  if (!value || value.length > 120 || /@|linkedin|www\./i.test(value) || isHeading(value) || looksExperienceRoleLine(value)) return false;
  return /\b(ltd|limited|llc|inc|corp|corporation|company|group|bank|capital|partners|ventures|university|school|insurance|pharmacy|consulting|solutions|technologies|systems|s\.p\.a|gmbh|plc|llp|fund|holdings|management|jio|vodafone|blackrock|bnp|mediobanca|star)\b/i.test(value) || /^[A-Z][A-Za-z0-9&().,'’ -]{2,70}$/.test(value);
}

function looksLocationLine(line) {
  const value = clean(line);
  if (!value || value.length > 90 || /@|linkedin|www\.|degree|university|school/i.test(value) || looksLikeBullet(value) || looksExperienceRoleLine(value)) return false;
  return /,\s*[A-Z][A-Za-z.'’ -]{2,}$/.test(value) || /\b(remote|hybrid|riyadh|dubai|abu dhabi|doha|london|milan|rome|barcelona|geneva|toronto|paris|new york|singapore|hong kong|india|uae|united kingdom|uk|italy|spain|france|canada|saudi arabia|qatar|germany|switzerland|netherlands)\b/i.test(value);
}

function inferEducationLines(sections, lines) {
  const direct = (sections.find((item) => item.key === "education") || { lines: [] }).lines
    .filter((line) => !isHeading(line))
    .slice(0, 8);
  if (direct.length) return direct;
  return lines
    .filter(looksEducationLine)
    .filter((line) => !looksLikeBullet(line))
    .slice(0, 8);
}

function inferExperienceLines(sections, allLines) {
  const direct = (sections.find((item) => item.key === "experience") || { lines: [] }).lines;
  if (direct.length) {
    const firstRole = direct.findIndex((line, index) => {
      const next = direct[index + 1] || "";
      return looksExperienceRoleLine(line) || looksLikeBullet(line) || (hasDate(line) && (looksExperienceRoleLine(next) || looksCompanyLine(next)));
    });
    return firstRole > 0 ? direct.slice(firstRole) : direct;
  }
  const start = allLines.findIndex((line) => /^(employment|work history|experience|experiences|professional experience|organisational experience|organizational experience)$/i.test(clean(line)));
  if (start >= 0) {
    const collected = [];
    for (let i = start + 1; i < allLines.length; i += 1) {
      if (i > start + 1 && /^(education|skills|languages|interests|hobbies|certifications|projects)$/i.test(clean(allLines[i]))) break;
      collected.push(allLines[i]);
    }
    return collected;
  }
  return allLines.filter((line, index) => {
    const next = allLines[index + 1] || "";
    return looksLikeBullet(line) || (hasDate(line) && (looksExperienceRoleLine(next) || looksCompanyLine(next)));
  });
}

function parseEntries(linesOrSection) {
  const lines = Array.isArray(linesOrSection) ? linesOrSection : (linesOrSection && linesOrSection.lines) || [];
  const entries = [];
  let current = null;
  function push() {
    if (current && (current.heading || current.company || current.bullets.length)) entries.push(current);
  }
  lines.forEach((line, index) => {
    const next = lines[index + 1] || "";
    const value = clean(line);
    if (!value || isHeading(value)) return;
    if (looksLikeBullet(line)) {
      if (!current) current = { heading: "Relevant Experience", company: "", location: "", dates: "", bullets: [], pendingDateFirst: false };
      current.bullets.push(line);
      return;
    }
    if (looksDateLine(value)) {
      if (current && !current.bullets.length && (current.heading || current.company) && !current.dates) {
        current.dates = dateText(value) || value;
        return;
      }
      if (!current || current.bullets.length || current.heading || current.company) {
        push();
        current = { heading: "", company: "", dates: dateText(value) || value, bullets: [], pendingDateFirst: true };
        return;
      }
      current.dates = dateText(value) || value;
      return;
    }
    if (!current) current = { heading: "", company: "", dates: dateText(value), bullets: [], pendingDateFirst: false };
    if (looksExperienceRoleLine(value) && current.heading && current.bullets.length) {
      push();
      current = { heading: value, company: "", location: "", dates: "", bullets: [], pendingDateFirst: false };
      return;
    }
    if (!current.heading && looksExperienceRoleLine(value)) {
      current.heading = value;
      current.pendingDateFirst = false;
      return;
    }
    if (current.heading && !current.bullets.length && looksExperienceRoleLine(value) && value !== current.heading) {
      current.heading = clean(current.heading + " - " + value);
      current.pendingDateFirst = false;
      return;
    }
    if (looksLocationLine(value)) {
      current.location = value;
      return;
    }
    if (!current.company && looksCompanyLine(value)) {
      current.company = value;
      return;
    }
    if (!current.heading && !hasDate(value) && !looksEducationLine(value)) {
      current.heading = value;
    } else if (!current.company && !hasDate(value) && !looksEducationLine(value) && value !== current.heading) {
      current.company = value;
    }
  });
  push();
  return entries
    .map((entry) => ({
      heading: [removeLooseDateText(entry.heading, entry.dates || looseDateText(entry.heading)), entry.company].filter(Boolean).join(entry.heading && entry.company ? " - " : ""),
      location: entry.location || "",
      dates: entry.dates || looseDateText(entry.heading),
      bullets: entry.bullets,
    }))
    .filter((entry) => entry.heading || entry.bullets.length)
    .slice(0, 7);
}

function buildModel(file, text) {
  const lines = mergeLines(text);
  const sections = parseSections(lines);
  const section = (key) => sections.find((item) => item.key === key) || { lines: [] };
  const bullets = sections.reduce((list, item) => list.concat(item.lines.filter(looksLikeBullet)), []);
  const rewrites = bullets.slice(0, 40)
    .map((line) => rewriteBullet(line, roleLens.keywords))
    .filter((item) => item.rewritten && !hasBrokenRewriteText(item.rewritten) && !looksLikeUnsafeRewrite(item.rewritten, item.original));
  const skills = extractSkills(lines, roleLens.keywords);
  const email = detectEmail(text);
  const name = detectName(lines, file, email);
  const phone = detectPhone(text);
  const educationLines = inferEducationLines(sections, lines)
    .map((line) => clean(line).replace(/^•\s*/, ""))
    .slice(0, 6);
  let experienceEntries = parseEntries(inferExperienceLines(sections, lines)).map((entry) => ({
    heading: clean(entry.heading || "Relevant Experience"),
    location: clean(entry.location || ""),
    dates: clean(entry.dates || dateText(entry.heading || "")),
    bullets: (entry.bullets && entry.bullets.length ? entry.bullets : [])
      .slice(0, 6)
      .map((line) => rewriteBullet(line, roleLens.keywords))
      .filter((item) => item.rewritten && !hasBrokenRewriteText(item.rewritten) && !looksLikeUnsafeRewrite(item.rewritten, item.original)),
  })).filter((entry) => entry.heading || entry.bullets.length);
  if (experienceEntries.length && rewrites.length && !experienceEntries.some((entry) => entry.bullets.length)) {
    experienceEntries[0].bullets = rewrites.slice(0, 8);
  }
  if (experienceEntries.some((entry) => entry.bullets.length)) {
    experienceEntries = experienceEntries.filter((entry) => entry.bullets.length || (entry.heading && entry.dates));
  }
  if (!experienceEntries.length && rewrites.length >= 3) {
    experienceEntries = [{
      heading: "Relevant Experience",
      dates: "",
      bullets: rewrites.slice(0, 10),
    }];
  }
  const entryRewriteCount = experienceEntries.reduce((sum, entry) => sum + entry.bullets.length, 0);
  const summary =
    section("summary").lines.find((line) => clean(line).split(/\s+/).length > 10) ||
    `${name} brings relevant experience across ${skills.slice(0, 4).join(", ") || "business, finance and operations"}, with analytical, commercial and stakeholder-facing work aligned to ${roleLens.title}.`;
  return {
    sourceFile: path.basename(file),
    name,
    contact: [phone, email].filter(Boolean).join(" | "),
    summary: finishSentence(summary),
    educationLines,
    experienceEntries,
    rewrites,
    documentRewrites: entryRewriteCount ? experienceEntries.reduce((list, entry) => list.concat(entry.bullets), []) : rewrites,
    skills,
    validation: validateModel({ name, email, educationLines, experienceEntries, rewrites: entryRewriteCount ? experienceEntries.reduce((list, entry) => list.concat(entry.bullets), []) : rewrites, skills }),
  };
}

function validateModel(model) {
  const warnings = [];
  if (model.name === "Candidate Name") warnings.push("missing_name");
  if (!model.email) warnings.push("missing_email");
  if (!model.educationLines.length) warnings.push("missing_education_section");
  if (!model.experienceEntries.length && model.rewrites.length < 3) warnings.push("weak_experience_structure");
  if (model.skills.join(" ").split(/\s+/).length > 80) warnings.push("possible_skill_overload");
  if (model.rewrites.some((item) => item.metrics.some((metric) => !item.rewritten.toLowerCase().includes(metric.toLowerCase())))) warnings.push("metric_loss");
  return warnings;
}

function renderCv(model) {
  const education = model.educationLines.length
    ? `<section><div class="sec-head">Education</div>${model.educationLines.map((line) => `<div class="entry"><div class="txn-note">${html(line)}</div></div>`).join("")}</section>`
    : "";
  const entryHtml = model.experienceEntries && model.experienceEntries.length
    ? model.experienceEntries.map((entry) => `<div class="entry"><div class="entry-head"><span class="place">${html(entry.heading || "Relevant Experience")}</span>${entry.dates ? `<span class="dates">${html(entry.dates)}</span>` : ""}</div>${entry.location ? `<div class="txn-note">${html(entry.location)}</div>` : ""}${entry.bullets.length ? `<ul class="bullets">${entry.bullets.map((item) => `<li>${html(item.rewritten)}</li>`).join("")}</ul>` : ""}</div>`).join("")
    : "";
  const experience = entryHtml || model.rewrites.length
    ? `<section><div class="sec-head">Work Experience</div>${entryHtml || `<div class="entry"><ul class="bullets">${model.rewrites.map((item) => `<li>${html(item.rewritten)}</li>`).join("")}</ul></div>`}</section>`
    : "";
  const skills = model.skills.length
    ? `<section><div class="sec-head">Skills</div><div class="skills-list"><p><strong>Relevant skills:</strong> ${html(model.skills.join(", "))}</p></div></section>`
    : "";
  return `<!doctype html><html lang="en"><head><meta charset="utf-8"><title>${html(model.name)} - Tailored CV</title><style>
*{box-sizing:border-box}html,body{margin:0;padding:0}body{background:#fff;color:#000;font-family:"EB Garamond","Times New Roman",Times,serif;font-size:16px;line-height:1.42}.sheet{max-width:800px;margin:0 auto;padding:52px 60px 70px}header{text-align:center;margin-bottom:22px}h1{font-size:28px;font-weight:700;letter-spacing:.03em;text-transform:uppercase;margin:0 0 6px}.contact-line{font-size:14.5px}hr.top-rule{border:none;border-top:1.5px solid #000;margin:0 0 18px}.summary-text{font-size:15px;text-align:justify;margin-bottom:22px}section{margin-bottom:20px}.sec-head{font-size:14px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;border-bottom:1px solid #000;padding-bottom:2px;margin-bottom:10px}.entry{margin-bottom:12px}.entry-head{display:flex;justify-content:space-between;align-items:baseline;font-weight:700;font-size:15.5px}.entry-head .dates{font-weight:400;font-style:italic;white-space:nowrap;padding-left:12px}.txn-note{font-size:14.5px;margin:3px 0 4px;text-align:justify}ul.bullets{margin:4px 0 0;padding-left:20px}ul.bullets li{margin-bottom:3px;font-size:15px;text-align:justify}.skills-list p{font-size:15px;margin:0 0 6px}@media(max-width:640px){.sheet{padding:30px 20px}.entry-head{display:block}.entry-head .dates{display:block;padding-left:0;white-space:normal}}
</style></head><body><div class="sheet"><header><h1>${html(model.name)}</h1>${model.contact ? `<div class="contact-line">${html(model.contact)}</div>` : ""}</header><hr class="top-rule"><div class="summary-text">${html(model.summary)}</div>${education}${experience}${skills}</div></body></html>`;
}

function collectFiles() {
  const files = [];
  if (fs.existsSync(defaultCvDir)) {
    fs.readdirSync(defaultCvDir)
      .filter((file) => /\.(pdf|docx?)$/i.test(file))
      .forEach((file) => files.push(path.join(defaultCvDir, file)));
  }
  defaultExtraFiles.filter((file) => fs.existsSync(file)).forEach((file) => files.push(file));
  return [...new Set(files)];
}

function hasBrokenRewriteText(value) {
  return /\b(Supported|Managed|Prepared|Built|Applied|Delivered|Improved|Reviewed|Analyzed|Analysed|Executed|Developed|Collected)\s+(supported|managed|prepared|built|applied|delivered|improved|reviewed|analy[sz]ed|executed|developed|formulated|collaborated|ensured|performed|liaised|negotiated|examined|uncovered|collected|assessed)\b/i.test(value || "") ||
    /\.\.|;\.|,\.|undefined|null|\band conduct\b|\bRead and analyse\b|\bsurveys Read\b|\bcarry out\b|,\s*,|\busing using\b|\bfor for\b|\bmanage over task\b|\b(?:Delivered|Managed|Prepared|Built|Applied|Executed|Developed|Reviewed|Analyzed|Analysed)\s+(?:PROFILE|available|availability|april|october|date of|place of|architecture|course|certificate|education|training|language|languages|jan|feb|mar|apr|may|jun|jul|aug|sep|sept|oct|nov|dec)\b|\busing\s+(?:Delivered|Improved|Managed|Prepared|Developed|Executed)\b|\bkey point of contact leading for\b|\b(?:aligning|provide|including|using|for|and|the)\.$/i.test(value || "");
}

function looksLikeUnsafeRewrite(value, source) {
  const text = clean(value);
  const sourceText = clean(source);
  if (!text) return true;
  if (looksLikeUnsafeEvidenceLine(text)) return true;
  if (/^(?:Delivered|Managed|Prepared|Built|Applied|Executed|Developed|Reviewed|Analyzed|Analysed)\s+(?:available|availability|date of|place of|gender|nationality|passport|visa|architecture|design and urban planning|course|courses|certificate|education|training|language|languages)\b/i.test(text)) return true;
  if (/^(?:Delivered|Managed|Prepared|Built|Applied|Executed|Developed|Reviewed|Analyzed|Analysed)\s+\[?(?:jan|feb|mar|apr|may|jun|jul|aug|sep|sept|oct|nov|dec)[a-z]*\.?\s+(?:19|20)\d{2}/i.test(text)) return true;
  const inventedDemoMetrics = ["25 close-cycle", "12 monthly", "$12.5m", "$45m", "100%", "18%", "20%"];
  if (inventedDemoMetrics.some((metric) => text.toLowerCase().includes(metric.toLowerCase()) && !sourceText.toLowerCase().includes(metric.toLowerCase()))) return true;
  if (/\b(?:Reframe this|show the action taken|connect it more directly)\b/i.test(text)) return true;
  if (text.split(/\s+/).length < 5) return true;
  return false;
}

function evaluateTailoredAst(ast) {
  const doc = ast && ast.tailoredDocument ? ast.tailoredDocument : {};
  const rewrites = doc.rewrites || [];
  const entries = doc.experience || [];
  const coverage = doc.requirementCoverage || [];
  const warnings = [];
  let score = 100;

  if (!doc.documentPlan) {
    warnings.push("missing_document_plan");
    score -= 20;
  }
  if (!entries.length) {
    warnings.push("missing_tailored_experience");
    score -= 30;
  }
  if (!rewrites.length) {
    warnings.push("missing_rewrites");
    score -= 35;
  }
  if (!doc.summary || String(doc.summary).split(/\s+/).length < 8) {
    warnings.push("weak_or_missing_summary");
    score -= 12;
  }
  if (!ast.candidate || !ast.candidate.email) {
    warnings.push("missing_email");
    score -= 8;
  }
  if (!ast.candidate || !ast.candidate.name || ast.candidate.name === "Candidate Name") {
    warnings.push("confirm_candidate_name");
    score -= 8;
  }
  const invalidRewrites = rewrites.filter((rewrite) => !(rewrite.validation && rewrite.validation.passed));
  if (invalidRewrites.length) {
    warnings.push("rewrite_validation_failed");
    score -= Math.min(30, invalidRewrites.length * 10);
  }
  const brokenRewrites = rewrites.filter((rewrite) => hasBrokenRewriteText(rewrite.rewritten));
  if (brokenRewrites.length) {
    warnings.push("broken_rewrite_text");
    score -= Math.min(40, brokenRewrites.length * 20);
  }
  const changedCount = rewrites.filter((rewrite) => rewrite.original !== rewrite.rewritten).length;
  if (rewrites.length && changedCount / rewrites.length < 0.25) {
    warnings.push("low_rewrite_change_rate");
    score -= 8;
  }
  const strongCoverage = coverage.filter((item) => item.status === "strong").length;
  if (coverage.length && strongCoverage === 0) {
    warnings.push("no_strong_requirement_coverage");
    score -= 14;
  }
  const hasMetric = rewrites.some((rewrite) => /(?:\+?\d+(?:[.,]\d+)?%?|[$€£]\s*\+?[0-9.,]+|[0-9.,]+\s*(?:transactions|investments|leads|companies|teams|reports|models))/i.test(rewrite.rewritten || ""));
  if (!hasMetric) {
    warnings.push("no_metric_visible");
    score -= 7;
  }
  const hasOutcome = rewrites.some((rewrite) => /\b(?:supporting|informing|improving|strengthening|enabling|driving|contributing|delivering|reducing|increasing)\b/i.test(rewrite.rewritten || ""));
  if (!hasOutcome) {
    warnings.push("no_outcome_language");
    score -= 9;
  }
  const likelyKeywordSoup = (doc.skills || []).some((skill) => String(skill || "").split(/\s+/).length > 14);
  if (likelyKeywordSoup) {
    warnings.push("skills_keyword_soup");
    score -= 8;
  }

  return {
    sourceFile: ast.sourceFile,
    candidate: ast.candidate && ast.candidate.name,
    tailoredHtmlFile: ast.tailoredHtmlFile,
    score: Math.max(0, Math.min(100, Math.round(score))),
    status: score >= 82 && !brokenRewrites.length && !invalidRewrites.length ? "pass" : score >= 68 ? "review" : "fail",
    warnings: [...new Set(warnings.concat(doc.warnings || []))]
      .filter((warning) => warning !== "candidate_name_needs_confirmation"),
    metrics: {
      entries: entries.length,
      rewrites: rewrites.length,
      changedRewrites: changedCount,
      validationPassed: rewrites.filter((rewrite) => rewrite.validation && rewrite.validation.passed).length,
      strongRequirementCoverage: strongCoverage,
      requirementCount: coverage.length,
      hasMetric,
      hasOutcome,
    },
  };
}

function summarizeQuality(items) {
  const warningCounts = {};
  items.forEach((item) => {
    (item.warnings || []).forEach((warning) => {
      warningCounts[warning] = (warningCounts[warning] || 0) + 1;
    });
  });
  return {
    evaluated: items.length,
    pass: items.filter((item) => item.status === "pass").length,
    review: items.filter((item) => item.status === "review").length,
    fail: items.filter((item) => item.status === "fail").length,
    averageScore: items.length
      ? Math.round(items.reduce((sum, item) => sum + item.score, 0) / items.length)
      : 0,
    warningCounts,
    lowestScoring: items
      .slice()
      .sort((left, right) => left.score - right.score)
      .slice(0, 12),
  };
}

function recommendedQaAction(item) {
  const warnings = new Set(item.warnings || []);
  if (warnings.has("missing_tailored_experience") || warnings.has("missing_rewrites")) {
    return "Parser rescue needed: recover experience bullets from low-structure or scanned CV text.";
  }
  if (warnings.has("broken_rewrite_text") || warnings.has("rewrite_validation_failed")) {
    return "Rewrite guard needed: block malformed generated text before rendering.";
  }
  if (warnings.has("confirm_candidate_name") || warnings.has("missing_email")) {
    return "Identity confirmation needed before application submission.";
  }
  if (warnings.has("no_strong_requirement_coverage")) {
    return "Role matching needs review: evidence may be valid but not mapped to this target lens.";
  }
  if (warnings.has("no_metric_visible")) {
    return "Ask for metrics only if material; do not invent numbers.";
  }
  if (warnings.has("low_rewrite_change_rate")) {
    return "Rewrite ambition can increase, but only where source evidence supports it.";
  }
  return "Manual spot check.";
}

function renderQualityReportHtml(summary, items) {
  const rows = items
    .slice()
    .sort((left, right) => left.score - right.score)
    .map((item) => {
      const href = item.tailoredHtmlFile || "#";
      return `<tr class="is-${html(item.status)}"><td><a href="${html(href)}">${html(item.candidate || "Candidate")}</a><small>${html(item.sourceFile || "")}</small></td><td><strong>${html(item.score)}</strong></td><td>${html(item.status)}</td><td>${html((item.warnings || []).join(", ") || "pass")}<small>${html(recommendedQaAction(item))}</small></td><td>${html(JSON.stringify(item.metrics))}</td></tr>`;
    })
    .join("");
  return `<!doctype html><html lang="en"><head><meta charset="utf-8"><title>CV Tailoring QA</title><style>
*{box-sizing:border-box}body{margin:0;background:#eef4fb;color:#172033;font-family:Inter,Arial,sans-serif}main{width:min(1280px,calc(100% - 40px));margin:34px auto}.stats{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin:22px 0}.stat,table,pre{background:#fff;border:1px solid #d9e0ea;border-radius:8px;box-shadow:0 14px 40px rgba(20,33,61,.08)}.stat{padding:16px}.stat strong{display:block;font-size:30px}table{width:100%;border-collapse:collapse;overflow:hidden}th,td{padding:12px 14px;border-bottom:1px solid #e6ebf2;text-align:left;vertical-align:top;font-size:13px}th{background:#132c52;color:#fff}a{color:#2f61e8;font-weight:800;text-decoration:none}small{display:block;color:#667085;margin-top:4px}pre{white-space:pre-wrap;padding:16px}.is-fail td{background:#fff7f7}.is-review td{background:#fffaf0}@media(max-width:900px){.stats{grid-template-columns:repeat(2,1fr)}td{overflow-wrap:anywhere}}</style></head><body><main><h1>CV Tailoring QA</h1><p>Automated quality gates for generated tailored CVs. Low-scoring rows should be manually inspected before production changes ship.</p><div class="stats"><div class="stat"><strong>${summary.evaluated}</strong><span>evaluated</span></div><div class="stat"><strong>${summary.pass}</strong><span>pass</span></div><div class="stat"><strong>${summary.review}</strong><span>review</span></div><div class="stat"><strong>${summary.fail}</strong><span>fail</span></div><div class="stat"><strong>${summary.averageScore}</strong><span>avg score</span></div></div><h2>Warning Counts</h2><pre>${html(JSON.stringify(summary.warningCounts, null, 2))}</pre><table><thead><tr><th>CV</th><th>Score</th><th>Status</th><th>Warnings</th><th>Metrics</th></tr></thead><tbody>${rows}</tbody></table></main></body></html>`;
}

function main() {
  fs.mkdirSync(outDir, { recursive: true });
  fs.readdirSync(outDir)
    .filter((file) => /\.(html|json)$/i.test(file))
    .forEach((file) => fs.unlinkSync(path.join(outDir, file)));
  const builder = path.join(__dirname, "build-cv-intelligence-corpus.js");
  const result = spawnSync(process.execPath, [builder, outDir], {
    encoding: "utf8",
    stdio: "pipe",
  });
  if (result.status !== 0) {
    process.stderr.write(result.stderr || result.stdout || "Structured CV corpus builder failed.\n");
    process.exit(result.status || 1);
  }
  const reportPath = path.join(outDir, "corpus-report.json");
  const report = fs.existsSync(reportPath)
    ? JSON.parse(fs.readFileSync(reportPath, "utf8"))
    : { summary: {} };
  const candidateAsts = fs.readdirSync(outDir)
    .filter((file) => /\.ast\.json$/i.test(file))
    .map((file) => JSON.parse(fs.readFileSync(path.join(outDir, file), "utf8")));
  const qualityItems = candidateAsts.map((ast) => {
    const item = evaluateTailoredAst(ast);
    return Object.assign({}, item, {
      recommendedAction: recommendedQaAction(item),
    });
  });
  const qualitySummary = summarizeQuality(qualityItems);
  fs.writeFileSync(
    path.join(outDir, "tailored-cv-quality-report.json"),
    JSON.stringify({
      generatedAt: new Date().toISOString(),
      summary: qualitySummary,
      items: qualityItems,
    }, null, 2)
  );
  fs.writeFileSync(
    path.join(outDir, "tailored-cv-quality-report.html"),
    renderQualityReportHtml(qualitySummary, qualityItems)
  );
  fs.writeFileSync(
    path.join(outDir, "evaluation-report.json"),
    JSON.stringify({
      generatedAt: new Date().toISOString(),
      roleLens,
      source: "build-cv-intelligence-corpus",
      summary: report.summary || {},
      quality: qualitySummary,
    }, null, 2)
  );
  console.log(
    `Generated ${report.summary && report.summary.candidateCvs ? report.summary.candidateCvs : 0} structured tailored CVs and captured ${report.summary && report.summary.reviewReports ? report.summary.reviewReports : 0} review reports in ${outDir}. QA pass=${qualitySummary.pass}, review=${qualitySummary.review}, fail=${qualitySummary.fail}, avg=${qualitySummary.averageScore}`
  );
}

main();
