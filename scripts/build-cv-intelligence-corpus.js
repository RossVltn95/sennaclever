#!/usr/bin/env node
"use strict";

const fs = require("fs");
const os = require("os");
const path = require("path");
const { spawnSync } = require("child_process");

const downloadsDir = "/Users/ropafadzoyasheushe/Downloads";
const defaultCvDir = path.join(downloadsDir, "CVs");
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
].map((file) => path.join(downloadsDir, file));

const outDir =
  process.argv[2] ||
  path.join(process.cwd(), "reports", "cv-intelligence-corpus");
const maxOcrFiles = Number(process.env.SFFC_CV_OCR_MAX || 3);
let ocrFilesAttempted = 0;

const sectionDefinitions = [
  ["summary", /^(summary|profile|professional summary|personal profile|career profile|objective|about me|chi sono|profil|profilo)$/i],
  ["experience", /^(work experience|professional experience|experience|experiences|employment|career history|work history|internships?|experiences professionnelles|expériences professionnelles|esperienze lavorative|expérience professionnelle)$/i],
  ["education", /^(education|academic background|academic qualifications|qualifications|formations?|formation|formazione|education and qualifications)$/i],
  ["skills", /^(skills|technical skills|core skills|key skills|additional skills|competencies|comp[eé]tences(?: et centres d.?int[eé]r[eê]t)?|competenze tecniche)$/i],
  ["projects", /^(projects|selected projects|selected transactions|transactions|project experience)$/i],
  ["certifications", /^(certifications|licenses|professional certifications|professional qualifications)$/i],
  ["languages", /^(languages|language skills|langues|lingue)$/i],
  ["additional", /^(interests|additional information|extracurricular activities|activities|centres d.?int[eé]r[eê]t|dati personali|awards|achievements)$/i],
];

const roleFamilies = [
  ["investment", /\b(investment|valuation|financial model|financial modelling|financial modeling|lbo|dcf|m&a|merger|acquisition|transaction|deal|due diligence|portfolio|private equity|venture capital|capital markets|credit|equity research|asset management)\b/i],
  ["business_development", /\b(business development|sales pipeline|pipeline|lead generation|leads|prospect|client|account|crm|partnership|go-to-market|commercial|revenue|market expansion|relationship management)\b/i],
  ["operations", /\b(operations|process|workflow|automation|efficiency|stakeholder|reporting|compliance|controls|reconciliation|accounts payable|accounts receivable|kpi|dashboard)\b/i],
  ["technology", /\b(python|sql|tableau|power bi|excel|vba|automation|data|analytics|machine learning|sap|bloomberg|capital iq|factset|refinitiv|workday)\b/i],
  ["risk_compliance", /\b(risk|compliance|audit|regulatory|controls|kyc|aml|policy|governance|legal)\b/i],
  ["finance_reporting", /\b(accounting|financial reporting|budget|forecast|variance|p&l|balance sheet|cash flow|month-end|management reporting|mis)\b/i],
];

const weakLanguageRules = [
  ["weak_opening_verb", /^(helped|assisted|aided|supported|worked on|working on|participated in|involved in|handled|dealt with|did|made|got|performed\s+(tasks?|duties|work|activities)|facilitated|responsible for|tasked with|provided support|provided assistance|took care of)\b/i],
  ["passive_voice", /\b(was|were)\s+(assigned|asked|given|tasked|responsible|involved|required|expected)\b/i],
  ["vague_quantity", /\b(various|several|multiple|many|numerous|some|a number of|a variety of|different|wide range of)\b/i],
  ["filler_language", /\b(successfully|effectively|efficiently|proactively|actively|strong|excellent|good|great|dynamic|innovative|hard[-\s]?working|team player|detail[-\s]?oriented)\b/i],
  ["buzzword_overload", /\b(synergy|synergies|best[-\s]?in[-\s]?class|world[-\s]?class|cutting[-\s]?edge|leverage|leveraged|utili[sz]ed|fast[-\s]?paced|results[-\s]?driven|self[-\s]?starter)\b/i],
  ["generic_responsibility", /\b(responsible for|duties included|tasks included|day to day|day-to-day|exposure to|familiar with)\b/i],
  ["unsupported_soft_skill", /\b(strong|excellent|good|great)\s+(communication|leadership|teamwork|analytical|interpersonal|organisational|organizational|problem[-\s]?solving)\s+skills?\b/i],
  ["first_person_language", /\b(i|me|my|we|our)\b/i],
];

const reviewCritiqueRules = [
  {
    key: "missing_metrics",
    label: "Metrics and quantification",
    weight: 90,
    pattern: /\b(metric|quantif|number|measur|impact|result|outcome|kpi|percentage|amount|value|scale|scope|how many|how much)\b/i,
    rewriteAction: "ask_for_or_preserve_metrics",
  },
  {
    key: "weak_summary",
    label: "Profile summary positioning",
    weight: 78,
    pattern: /\b(summary|profile|headline|opening|professional profile|personal statement|objective|positioning statement)\b/i,
    rewriteAction: "rewrite_profile_around_role_evidence",
  },
  {
    key: "ats_keywords",
    label: "ATS and role keywords",
    weight: 84,
    pattern: /\b(ats|keyword|keywords|screening|job description|role requirement|matching|relevance|tailor|target)\b/i,
    rewriteAction: "align_role_keywords_without_stuffing",
  },
  {
    key: "weak_action_verbs",
    label: "Action verbs and ownership",
    weight: 82,
    pattern: /\b(action verb|verbs?|responsible for|helped|assisted|supported|ownership|led|managed|delivered|achieved)\b/i,
    rewriteAction: "strengthen_action_with_seniority_guard",
  },
  {
    key: "formatting_readability",
    label: "Formatting and readability",
    weight: 72,
    pattern: /\b(format|formatting|layout|readability|readable|scan|scannable|structure|spacing|font|bullet|one page|two page|section)\b/i,
    rewriteAction: "clean_structure_and_section_order",
  },
  {
    key: "achievement_orientation",
    label: "Achievement-led bullets",
    weight: 86,
    pattern: /\b(achievement|accomplishment|impact|result|delivered|improved|reduced|increased|generated|saved|created value)\b/i,
    rewriteAction: "convert_duties_to_evidence_led_bullets",
  },
  {
    key: "role_alignment",
    label: "Role alignment",
    weight: 88,
    pattern: /\b(role alignment|target role|targeted|relevant experience|fit|match|employer|job advert|job ad|requirements)\b/i,
    rewriteAction: "rank_evidence_by_target_role",
  },
  {
    key: "skills_section_noise",
    label: "Skills section quality",
    weight: 62,
    pattern: /\b(skills section|technical skills|soft skills|competenc|language|tools|excel|python|sql|powerpoint)\b/i,
    rewriteAction: "dedupe_and_group_skills",
  },
  {
    key: "contact_identity",
    label: "Contact and identity clarity",
    weight: 65,
    pattern: /\b(contact|email|phone|linkedin|location|address|name|header)\b/i,
    rewriteAction: "confirm_identity_and_contact",
  },
  {
    key: "grammar_clarity",
    label: "Grammar and clarity",
    weight: 74,
    pattern: /\b(grammar|clarity|concise|wording|sentence|tense|punctuation|spelling|read smoothly|awkward)\b/i,
    rewriteAction: "polish_sentence_quality",
  },
];

const actionLexicon = {
  weak: ["helped", "assisted", "aided", "worked", "participated", "involved", "handled", "dealt", "did", "made", "got"],
  analyst: ["analyzed", "analysed", "prepared", "built", "created", "conducted", "evaluated", "researched", "modeled", "modelled", "validated", "monitored"],
  associate: ["managed", "executed", "developed", "coordinated", "streamlined", "sourced", "qualified", "delivered", "implemented", "reviewed"],
  senior: ["led", "owned", "oversaw", "directed", "spearheaded", "defined", "advised", "guided"],
};

const targetRoleLens = {
  title: "Business Development & Operations Senior Associate",
  families: ["business_development", "operations", "investment", "technology", "finance_reporting"],
  requirements: [
    { key: "business_development", label: "Business development", priority: "important", weight: 70, patterns: ["business development", "origination", "sourcing", "pipeline", "partnership", "client relationship", "commercial growth"] },
    { key: "operations", label: "Operations", priority: "important", weight: 65, patterns: ["operations", "process improvement", "workflow", "controls", "automation", "operational"] },
    { key: "financial_modelling", label: "Financial modelling", priority: "important", weight: 70, patterns: ["financial modelling", "financial modeling", "valuation model", "lbo", "dcf", "forecasting"] },
    { key: "valuation", label: "Valuation", priority: "important", weight: 65, patterns: ["valuation", "enterprise value", "ev/ebitda", "comparable companies", "precedent transactions"] },
    { key: "market_research", label: "Market research", priority: "positioning", weight: 45, patterns: ["market research", "market mapping", "market analysis", "competitive analysis"] },
    { key: "performance_monitoring", label: "Performance monitoring", priority: "important", weight: 70, patterns: ["performance monitoring", "kpi", "dashboard", "management reporting", "portfolio monitoring", "variance analysis"] },
    { key: "stakeholder_management", label: "Stakeholder management", priority: "positioning", weight: 45, patterns: ["stakeholder management", "senior stakeholders", "cross-functional", "client-facing", "presentation"] },
    { key: "reporting", label: "Reporting", priority: "positioning", weight: 45, patterns: ["reporting", "management reporting", "dashboard", "analytics"] },
  ],
};

targetRoleLens.keywords = targetRoleLens.requirements.flatMap((item) => item.patterns);

function detectRequirementMatches(text) {
  const source = clean(text).toLowerCase();
  return (targetRoleLens.requirements || [])
    .filter((requirement) =>
      (requirement.patterns || []).some((keyword) => source.includes(keyword.toLowerCase()))
    )
    .map((requirement) => ({
      key: requirement.key,
      label: requirement.label,
      priority: requirement.priority,
      weight: requirement.weight,
    }));
}

function summarizeRequirementCoverage(evidenceUnits) {
  return (targetRoleLens.requirements || []).map((requirement) => {
    const matchingEvidence = (evidenceUnits || []).filter((unit) =>
      ((unit && unit.requirementMatches) || []).some((match) => match.key === requirement.key)
    );
    const totalRelevance = matchingEvidence.reduce((sum, unit) => sum + evidenceRelevanceScore(unit), 0);
    const evidenceStrength = matchingEvidence.length
      ? Math.min(100, Math.round(totalRelevance / matchingEvidence.length))
      : 0;
    return {
      key: requirement.key,
      label: requirement.label,
      priority: requirement.priority,
      weight: requirement.weight,
      evidenceCount: matchingEvidence.length,
      evidenceStrength,
      status: matchingEvidence.length
        ? evidenceStrength >= 55
          ? "strong"
          : "partial"
        : "missing",
      examples: matchingEvidence.slice(0, 3).map((unit) => unit.source),
    };
  });
}

const rewriteFamilies = {
  investment: {
    action: { weak: "Supported", analyst: "Analyzed", associate: "Executed", senior: "Led", unknown: "Analyzed" },
    object: "investment and transaction analysis",
    outcome: "supporting deal evaluation and senior review",
  },
  business_development: {
    action: { weak: "Supported", analyst: "Built", associate: "Managed", senior: "Led", unknown: "Built" },
    object: "commercial pipeline and business development activity",
    outcome: "supporting growth, conversion, and stakeholder engagement",
  },
  operations: {
    action: { weak: "Supported", analyst: "Improved", associate: "Streamlined", senior: "Led", unknown: "Improved" },
    object: "operational and reporting workflows",
    outcome: "improving clarity, control, and execution consistency",
  },
  technology: {
    action: { weak: "Applied", analyst: "Applied", associate: "Developed", senior: "Led", unknown: "Applied" },
    object: "data and analytical workflows",
    outcome: "improving insight generation and decision support",
  },
  risk_compliance: {
    action: { weak: "Supported", analyst: "Reviewed", associate: "Managed", senior: "Oversaw", unknown: "Reviewed" },
    object: "risk, compliance, and control activity",
    outcome: "strengthening governance and execution quality",
  },
  finance_reporting: {
    action: { weak: "Prepared", analyst: "Prepared", associate: "Developed", senior: "Managed", unknown: "Prepared" },
    object: "financial reporting and analysis",
    outcome: "supporting management visibility and business planning",
  },
  general: {
    action: { weak: "Supported", analyst: "Delivered", associate: "Managed", senior: "Led", unknown: "Delivered" },
    object: "relevant business activity",
    outcome: "supporting execution quality and stakeholder needs",
  },
};

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
    .slice(0, 84) || "cv";
}

function isReviewFile(file) {
  return /\b(review|checker|ats|results)\b/i.test(path.basename(file));
}

function meaningfulTextScore(text) {
  const value = String(text || "");
  const lines = value.split(/\r?\n/).map(clean).filter(Boolean);
  const wordCount = (value.match(/[A-Za-zÀ-ÿ]{2,}/g) || []).length;
  const contactScore = (detectEmail(value) ? 12 : 0) + (detectPhone(value) ? 8 : 0);
  const sectionScore = Math.min(40, lines.filter((line) => sectionKey(line)).length * 10);
  const professionalScore = Math.min(45, lines.filter((line) => hasProfessionalSignal(line) || isBulletLine(line)).length * 5);
  return Math.min(200, Math.round(wordCount / 8) + contactScore + sectionScore + professionalScore);
}

function runTextCommand(command, args) {
  const result = spawnSync(command, args, {
    encoding: "utf8",
    maxBuffer: 1024 * 1024 * 24,
  });
  if (result.error || result.status !== 0) {
    return {
      text: "",
      error: result.error ? result.error.message : result.stderr || "extract_failed",
    };
  }
  return { text: String(result.stdout || ""), error: "" };
}

function extractPdfTextWithOcr(file) {
  const tmp = fs.mkdtempSync(path.join(os.tmpdir(), "sffc-cv-ocr-"));
  const prefix = path.join(tmp, "page");
  try {
    const raster = spawnSync("pdftoppm", ["-f", "1", "-l", "1", "-r", "140", "-png", file, prefix], {
      encoding: "utf8",
      maxBuffer: 1024 * 1024 * 8,
      timeout: 7000,
    });
    if (raster.error || raster.status !== 0) {
      return {
        text: "",
        error: raster.error ? raster.error.message : raster.stderr || "pdf_rasterize_failed",
      };
    }
    const images = fs.readdirSync(tmp)
      .filter((item) => /\.png$/i.test(item))
      .sort()
      .map((item) => path.join(tmp, item));
    const pages = images.map((image) => {
      const ocr = spawnSync("tesseract", [image, "stdout", "--psm", "6"], {
        encoding: "utf8",
        maxBuffer: 1024 * 1024 * 12,
        timeout: 9000,
      });
      if (ocr.error || ocr.status !== 0) return "";
      return String(ocr.stdout || "");
    }).filter(Boolean);
    return {
      text: pages.join("\n\n"),
      error: pages.length ? "" : "ocr_no_text",
    };
  } finally {
    fs.rmSync(tmp, { recursive: true, force: true });
  }
}

function extractText(file) {
  const ext = path.extname(file).toLowerCase();
  if (ext === ".pdf") {
    const candidates = [
      { source: "pdftotext", ...runTextCommand("pdftotext", [file, "-"]) },
      { source: "pdftotext_layout", ...runTextCommand("pdftotext", ["-layout", file, "-"]) },
    ].map((candidate) => ({
      ...candidate,
      score: meaningfulTextScore(candidate.text),
    }));
    const bestBeforeOcr = candidates
      .filter((candidate) => candidate.text)
      .sort((left, right) => right.score - left.score)[0];
    if (
      ocrFilesAttempted < maxOcrFiles &&
      (!bestBeforeOcr || bestBeforeOcr.score < 12 || String(bestBeforeOcr.text || "").trim().length < 120)
    ) {
      ocrFilesAttempted += 1;
      const ocr = extractPdfTextWithOcr(file);
      candidates.push({
        source: "ocr_tesseract",
        ...ocr,
        score: meaningfulTextScore(ocr.text),
      });
    }
    const best = candidates
      .filter((candidate) => candidate.text)
      .sort((left, right) => right.score - left.score)[0];
    if (best) {
      return {
        text: best.text,
        error: "",
        source: best.source,
        score: best.score,
        attemptedSources: candidates.map((candidate) => ({
          source: candidate.source,
          score: candidate.score,
          error: candidate.error || "",
        })),
      };
    }
    return {
      text: "",
      error: candidates.map((candidate) => candidate.error).filter(Boolean).join("; ") || "extract_failed",
      source: "none",
      score: 0,
      attemptedSources: candidates,
    };
  }
  if (ext === ".docx" || ext === ".doc") {
    const extracted = runTextCommand("textutil", ["-convert", "txt", "-stdout", file]);
    return {
      ...extracted,
      source: "textutil",
      score: meaningfulTextScore(extracted.text),
      attemptedSources: [{ source: "textutil", score: meaningfulTextScore(extracted.text), error: extracted.error || "" }],
    };
  }
  return { text: "", error: "unsupported_file_type", source: "none", score: 0, attemptedSources: [] };
}

function sectionKey(line) {
  const normalized = clean(line).replace(/[:\-–—]+$/g, "");
  const hit = sectionDefinitions.find((item) => item[1].test(normalized));
  if (hit) return hit[0];
  if (
    normalized.length <= 70 &&
    normalized.split(/\s+/).length <= 6 &&
    normalized === normalized.toUpperCase() &&
    /\b(SUMMARY|PROFILE|OBJECTIVE|EDUCATION|QUALIFICATION|EXPERIENCE|EMPLOYMENT|SKILLS|COMPETENCIES|LANGUAGES|PROJECTS|CERTIFICATIONS|INTERESTS|FORMATION|FORMAZIONE|ESPERIENZE|COMPETENCES|COMPÉTENCES)\b/.test(normalized)
  ) {
    if (/EDUCATION|QUALIFICATION|FORMATION|FORMAZIONE/.test(normalized)) return "education";
    if (/EXPERIENCE|EMPLOYMENT|ESPERIENZE/.test(normalized)) return "experience";
    if (/SKILLS|COMPETENCIES|COMPETENCES|COMPÉTENCES/.test(normalized)) return "skills";
    if (/LANGUAGES/.test(normalized)) return "languages";
    if (/PROJECTS/.test(normalized)) return "projects";
    return "summary";
  }
  return "";
}

function isSectionHeading(line) {
  return !!sectionKey(line);
}

function hasDate(line) {
  return /\b(?:19|20)\d{2}\b|\b(?:jan|feb|mar|apr|may|jun|jul|aug|sep|sept|oct|nov|dec|janvier|février|fevrier|mars|avril|mai|juin|juillet|ao[uû]t|septembre|octobre|novembre|d[ée]cembre|gennaio|febbraio|marzo|aprile|maggio|giugno|luglio|agosto|settembre|ottobre|novembre|dicembre)\b/i.test(clean(line));
}

function dateText(line) {
  const value = clean(line);
  const match =
    value.match(/\b(?:(?:jan|feb|mar|apr|may|jun|jul|aug|sep|sept|oct|nov|dec)[a-z]*\s+)?(?:19|20)\d{2}\s*(?:-|–|—|to)\s*(?:present|current|(?:jan|feb|mar|apr|may|jun|jul|aug|sep|sept|oct|nov|dec)[a-z]*\s+)?(?:19|20)?\d{0,4}\b/i) ||
    value.match(/\b(?:19|20)\d{2}\b/);
  return match ? clean(match[0]).replace(/\bcurrent\b/i, "Present") : "";
}

function isContactLine(line) {
  return /@|linkedin\.com|www\.|https?:\/\/|\+\d{6,}|\b(?:phone|email|e-mail|mobile)\b/i.test(clean(line));
}

function looksLikeNameStopLine(line) {
  return /\b(profile|summary|objective|customer success|software skills|professional experiences?|organizational experience|education|school|college|university|business school|maturit|bachelor|master|degree|bsc|msc|mba|analyst|associate|manager|director|consultant|officer|specialist|engineer|developer|assistant|capital|bank|company|ltd|limited|group)\b/i.test(clean(line));
}

function isCompanyLike(line) {
  const value = clean(line);
  if (!value || value.split(/\s+/).length > 8 || isContactLine(value) || hasDate(value)) return false;
  if (isBadCompanyValue(value)) return false;
  if (/^(excel|word|powerpoint|python|r|sql|tableau|power bi|modelling|modeling|communication|leadership|accounting|marketing|auditing|budgeting|forecasting|reconciliation|analytical thinking|adaptability|time management|problem solving)$/i.test(value)) return false;
  if (/^(financial analysis and reporting|tax preparations?|bank reconciliation|cost accounting|regulatory compliance|gaap knowledge|technical skill|soft skill)$/i.test(value)) return false;
  if (/\b(analyst|associate|manager|director|consultant|intern|officer|specialist|engineer|developer|assistant|lawyer|accountant|auditor)\b/i.test(value)) return false;
  if (/\b(ltd|limited|inc|corp|corporation|llc|llp|plc|company|co\.?|group|capital|partners|advisors?|advisory|investments?|management|bank|consulting|finance|financial|holdings|ventures|sa|sarl|gmbh|ag|spa|s\.p\.a\.|s\.r\.l\.)\b/i.test(value)) return true;
  return value.split(/\s+/).length >= 2 && /^[A-ZÀ-Ý][A-Za-zÀ-ÿ&'’.-]+(?:\s+[A-ZÀ-Ý][A-Za-zÀ-ÿ&'’.-]+){1,4}$/.test(value);
}

function isBadCompanyValue(value, candidateName) {
  const cleanValue = clean(value);
  if (!cleanValue) return true;
  if (candidateName && cleanValue.toLowerCase() === clean(candidateName).toLowerCase()) return true;
  return /^(period|current|present|completed|matric|qualification|qualifications|berufserfahrung|thesis|kontakt|contact|education|experience|skills|languages?|profile|about me|date of birth|status|nationality|madrid|london|dubai|riyadh|milan|paris|rome|toronto|india|uk|usa|uae|egypt|india|spain|switzerland|france|germany|johannesburg|fujairah|kerala|calicut)$/i.test(cleanValue);
}

function looksLikePersonalDetailLine(line) {
  return /\b(date of birth|birth date|dob|date of issue|date of expiry|place of issue|nationality|marital status|status:\s*single|gender|passport|visa status|driving licence|driving license)\b/i.test(clean(line));
}

function looksLikeUnsafeEvidenceLine(line) {
  const value = clean(line).replace(/^[•▪●○◦❖\-–—]\s*/, "");
  if (!value) return true;
  if (isContactLine(value) || looksLikePersonalDetailLine(value) || isSectionHeading(value)) return true;
  if (/^(?:available|availability|notice period|references available|current location|location|address|contact|phone|email|website|linkedin)\b/i.test(value)) return true;
  if (/^(?:course|courses|certificate|certification|education|training|project|language|languages|skills|interests|hobbies|profile|summary)\b/i.test(value)) return true;
  if (/^(?:london|dubai|riyadh|abu dhabi|doha|milan|rome|paris|toronto|new york|singapore|hong kong|karachi|kerala|calicut)$/i.test(value)) return true;
  if (/^(?:answer|arrange|book|send|track|offer|tidy|welcome|based on|regular use|order supplies|pre-start|offboarding|leavers|chairs|laptops|mobiles|photocopier|mots|tour of the building)\b/i.test(value)) return true;
  if (/^\[?\s*(?:jan|feb|mar|apr|may|jun|jul|aug|sep|sept|oct|nov|dec)[a-z]*\.?\s+(?:19|20)\d{2}\s*(?:-|–|—|to)\s*(?:present|current|(?:jan|feb|mar|apr|may|jun|jul|aug|sep|sept|oct|nov|dec)[a-z]*\.?\s+(?:19|20)\d{2}|(?:19|20)\d{2})\s*\]?$/i.test(value)) return true;
  if (/^(?:portuguese|english|french|german|spanish|arabic|italian|russian|hindi|urdu|mandarin|cantonese)\s+(?:native|fluent|proficient|professional|advanced|intermediate|basic)\b/i.test(value)) return true;
  if (/\b(?:native|fluent|proficient|professional working proficiency|advanced|intermediate)\b/i.test(value) && (value.match(/\b(?:english|french|german|spanish|arabic|italian|portuguese|russian|hindi|urdu|mandarin|cantonese)\b/gi) || []).length >= 2) return true;
  if (/^(?:architecture|architectural|design|urban planning|bsc|msc|mba|matric|degree)\b/i.test(value) && value.split(/\s+/).length < 10) return true;
  if ((value.match(/\b(?:analyst|associate|manager|director|finance|banking|investment|excel|powerpoint|english|french|degree|university|education|software|retail|logistics)\b/gi) || []).length >= 8 && !/[.,;:]/.test(value)) return true;
  return false;
}

function removeLeadingDateNoise(value) {
  return clean(value)
    .replace(/^\[?\s*(?:jan|feb|mar|apr|may|jun|jul|aug|sep|sept|oct|nov|dec)[a-z]*,?\.?\s+(?:19|20)\d{2}\s*(?:-|–|—|to)?\s*/i, "")
    .replace(/^\[?\s*(?:19|20)\d{2}\s*(?:-|–|—|to)\s*(?:present|current)?\s*/i, "")
    .replace(/^\]\s*/, "")
    .trim();
}

function cleanExperienceMetaFragment(value) {
  const date = dateText(value);
  return clean(value)
    .replace(date, " ")
    .replace(/\b\d{1,2}\/\s*\/?(?:19|20)?\d{2}\b/g, " ")
    .replace(/\b(?:19|20)\d{2}\s*(?:-|–|—|to)\s*(?:present|current|(?:19|20)\d{2})\b/gi, " ")
    .replace(/\(\s*\)/g, "")
    .replace(/\b(?:current|present)\b$/i, "")
    .replace(/\b(?:jan|feb|mar|apr|may|jun|jul|aug|sep|sept|oct|nov|dec)[a-z]*\.?\s*$/i, "")
    .replace(/(?:,|\||•|·|-|–|—)\s*$/g, "")
    .trim();
}

function splitCompositeRoleCompany(entry, candidateName) {
  const role = cleanExperienceMetaFragment(entry.role || "");
  const company = cleanExperienceMetaFragment(entry.company || "");
  const rolePattern = "(?:senior\\s+|junior\\s+|associate\\s+|assistant\\s+|avp\\s+|vp\\s+)?(?:financial\\s+crime\\s+|finance\\s+|credit\\s+|investment\\s+|business\\s+development\\s+|client\\s+services\\s+|payments?\\s+and\\s+client\\s+services\\s+|kyc\\s+(?:&|and)\\s+aml\\s+|kyc\\s+(?:&|and)\\s+cdd\\s+)?(?:analyst|associate|manager|consultant|intern|officer|specialist|engineer|developer|accountant|auditor)";
  const composite = role.match(new RegExp("^(.{4,80}?)\\s+(" + rolePattern + ")$", "i"));
  if (composite && !company && looksLikeStrongCompanyName(composite[1]) && !isBadCompanyValue(composite[1], candidateName)) {
    return {
      ...entry,
      role: cleanExperienceMetaFragment(composite[2]),
      company: cleanExperienceMetaFragment(composite[1]),
    };
  }
  const embeddedCompanyRole = role.match(new RegExp("^(.{4,90}?)\\s+(" + rolePattern + ")(?:\\s*\\([^)]*\\))?$", "i"));
  if (embeddedCompanyRole && looksLikeStrongCompanyName(embeddedCompanyRole[1])) {
    return {
      ...entry,
      role: cleanExperienceMetaFragment(embeddedCompanyRole[2]),
      company: company || cleanExperienceMetaFragment(embeddedCompanyRole[1]),
    };
  }
  if (/^(?:analyst|associate|manager|consultant|specialist)$/i.test(role) && /\b(financial|finance|credit|investment|business development|client services|kyc|aml|cdd)\s*$/i.test(company)) {
    const moved = company.match(/\b(financial|finance|credit|investment|business development|client services|kyc|aml|cdd)\s*$/i)[1];
    return {
      ...entry,
      role: cleanExperienceMetaFragment(`${moved} ${role}`),
      company: cleanExperienceMetaFragment(company.replace(new RegExp("\\b" + moved.replace(/\s+/g, "\\s+") + "\\s*$", "i"), "")),
    };
  }
  if (company && isBadCompanyValue(company, candidateName)) {
    return { ...entry, role, company: "" };
  }
  return { ...entry, role, company };
}

function looksLikeStrongCompanyName(value) {
  const cleanValue = clean(value);
  if (!cleanValue || cleanValue.split(/\s+/).length < 2) return false;
  return /\b(ltd|limited|inc|corp|corporation|llc|llp|plc|company|co\.?|group|capital|partners|advisors?|advisory|investments?|management|bank|consulting|finance|financial|holdings|ventures|services|systems|payments|pvt|ag|gmbh|sarl|sa|spa|s\.p\.a\.|s\.r\.l\.)\b/i.test(cleanValue) || /^[A-Z0-9&().,\s-]{8,}$/.test(cleanValue);
}

function sanitizeRenderedRole(value) {
  const role = cleanExperienceMetaFragment(value || "");
  if (!role) return "";
  if (/^(with|for|at|in|on|and|to)\b/i.test(role)) return "";
  if (role.split(/\s+/).length > 8) return "";
  if (!isRoleLike(role)) return "";
  return role;
}

function sanitizeRenderedCompany(value, candidateName) {
  const company = cleanExperienceMetaFragment(value || "");
  if (!company || isBadCompanyValue(company, candidateName) || isEducationLine(company) || isRoleLike(company)) return "";
  if (company.split(/\s+/).length > 9) return "";
  return company;
}

function chooseEntryRole(lines) {
  return lines
    .map(cleanExperienceMetaFragment)
    .find((line) => line && isRoleLike(line) && !isEducationLine(line) && !isContactLine(line) && !looksLikePersonalDetailLine(line)) || "";
}

function chooseEntryCompany(lines, candidateName) {
  return lines
    .map(cleanExperienceMetaFragment)
    .find((line) => line && isCompanyLike(line) && !isBadCompanyValue(line, candidateName) && !isEducationLine(line) && !looksLikePersonalDetailLine(line)) || "";
}

function isRoleLike(line) {
  const value = clean(line);
  if (!value || value.split(/\s+/).length > 10 || isContactLine(value) || isSectionHeading(value)) return false;
  return /\b(chief|ceo|cfo|coo|president|vice president|vp|partner|director|manager|lead|head|analyst|analista|associate|assistant|officer|banker|consultant|consultor|intern|advisor|advisory|trading|banking|lawyer|graduate|scientist|programmer|specialist|engineer|developer|accountant|auditor|treasurer|supervisor|controller|finance|credit|risk|strategy|operations)\b/i.test(value);
}

function isEducationLine(line) {
  return /\b(university|université|universita|università|universidad|college|school|business school|bsc|msc|mba|ba\b|ma\b|bachelor|master|degree|diploma|gpa|cum laude|honou?rs|economics|finance|accounting|engineering|management|statistics|data science)\b/i.test(clean(line));
}

function isNonExperienceEvidenceLine(line) {
  const value = clean(line);
  if (!value) return true;
  if (looksLikeUnsafeEvidenceLine(value)) return true;
  if (isEducationLine(value) || isContactLine(value) || isCompanyLike(value) || isSectionHeading(value)) return true;
  if (/^(resume|cv|curriculum vitae)\s+(page\s*)?\|?\s*\d+\b/i.test(value)) return true;
  if (/^page\s+\d+\s*(?:of\s+\d+)?$/i.test(value)) return true;
  if (/^(jan|feb|mar|apr|may|jun|jul|aug|sep|sept|oct|nov|dec)[a-z]*\.?\s+(?:19|20)\d{2}\s*(?:-|–|—|to)\s*/i.test(value) && !isBulletLine(value)) return true;
  if (/^(?:19|20)\d{2}\s*(?:-|–|—|to)\s*(?:present|current|(?:19|20)\d{2})\b/i.test(value) && !isBulletLine(value)) return true;
  if (/^[A-Z][A-Za-zÀ-ÿ'’.-]+(?:\s{2,}|\s+)[A-Z][A-Za-zÀ-ÿ'’.-]+(?:\s{2,}|\s+)(?:analyst|associate|manager|consultant|intern|officer|specialist|engineer|developer|assistant)\b/i.test(value) && !isBulletLine(value)) return true;
  if (value.split(/\s+/).length >= 18 && !hasStrongOpening(value) && !/\b(supporting|resulting|improving|reducing|increasing|managed|led|built|prepared|analy[sz]ed|developed|executed|created|delivered|implemented|monitored|evaluated|reviewed|conducted)\b/i.test(value)) return true;
  if ((value.match(/\b(?:analyst|associate|manager|consultant|intern|university|degree|bachelor|master|excel|powerpoint|english|french|german|arabic|finance|banking|investment)\b/gi) || []).length >= 8 && !isBulletLine(value)) return true;
  if (value.split(/\s+/).length <= 7 && /^(determinazione|analisi|gestione|competenze|lingue|formazione)\b/i.test(value)) return true;
  if (value.split(/\s+/).length <= 7 && !hasStrongOpening(value) && /^[A-ZÀ-Ý][A-Za-zÀ-ÿ'’.-]+(?:\s+[A-ZÀ-Ý][A-Za-zÀ-ÿ'’.-]+){2,}$/.test(value)) return true;
  if (/^(grade|gpa|languages?|language|skills?|technical|software|certificates?|certifications?|interests?|hobbies?)\b/i.test(value)) return true;
  if (/\b(additional skills|proficiency|life interests|technical skills|softwares?|languages?:|certificates?:)\b/i.test(value)) return true;
  return false;
}

function isBulletLine(rawLine) {
  const raw = String(rawLine || "");
  const value = clean(raw);
  if (looksLikeUnsafeEvidenceLine(value)) return false;
  if (/^\s*[•▪●○◦❖\-–—]\s+/.test(raw)) return true;
  if (value.split(/\s+/).length < 5 || isSectionHeading(value) || isContactLine(value)) return false;
  return /^(analy[sz](?:ed|ing)|built|prepared|preparing|preaparing|managed|managing|led|executed|executing|supported|supporting|conducted|conducting|developed|developing|created|creating|streamlined|streamlining|monitored|monitoring|evaluated|evaluating|sourced|sourcing|qualified|coordinated|coordinating|facilitated|facilitating|validated|validating|processed|processing|posted|posting|reconciled|reconciling|performed|provided|contributed|improved|reduced|increased|responsible for|helped|assisted|worked on|participated in|handled|reviewed|reviewing|identified|designed|implemented|delivered|maintained|maintaining|generated|compiled|collaborated)\b/i.test(value);
}

function mergeLines(text) {
  const lines = [];
  let pendingBullet = false;
  String(text || "").split(/\r?\n/).forEach((raw) => {
    const rawSegments = String(raw || "")
      .replace(/([^\s])([•▪●○◦❖])/g, "$1\n$2")
      .split(/\n+/);
    rawSegments.forEach((segment) => {
    raw = segment;
    if (/^\s*[•▪●○◦❖\-–—]\s*$/.test(raw)) {
      pendingBullet = true;
      return;
    }
    const value = clean(raw);
    if (!value) return;
    const startsBullet = pendingBullet || /^\s*[•▪●○◦❖\-–—]\s+/.test(raw);
    const line = startsBullet ? "• " + value : value;
    pendingBullet = false;
    const previous = lines[lines.length - 1] || "";
    const shouldMerge =
      previous &&
      !startsBullet &&
      !isSectionHeading(line) &&
      !isRoleLike(line) &&
      !isCompanyLike(line) &&
      !hasDate(line) &&
      previous.length < 320 &&
      (/^•\s+/.test(previous) || !/[.!?)]$/.test(previous) || /^[a-z(,;:]/.test(line));
    if (shouldMerge) {
      lines[lines.length - 1] = /^•\s+/.test(previous)
        ? "• " + clean(previous + " " + line)
        : clean(previous + " " + line);
      return;
    }
    lines.push(line);
    });
  });
  return lines;
}

function parseSections(lines) {
  const sections = [{ key: "header", title: "Header", lines: [] }];
  lines.forEach((line) => {
    const key = sectionKey(line);
    if (key) {
      sections.push({ key, title: clean(line), lines: [] });
      return;
    }
    sections[sections.length - 1].lines.push(line);
  });
  return sections.filter((section) => section.key === "header" || section.lines.length);
}

function detectEmail(text) {
  const match = String(text || "").match(/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i);
  return match ? match[0].toLowerCase() : "";
}

function detectPhone(text) {
  const match = String(text || "").match(/(?:\+\d{1,3}[\s.-]?)?(?:\(?\d{2,4}\)?[\s.-]?){2,5}\d{3,4}/);
  return match ? clean(match[0]) : "";
}

function detectName(lines, file, email) {
  const candidates = lines.slice(0, 18).map(clean).filter((line) => {
    if (!line || isContactLine(line) || hasDate(line) || isSectionHeading(line) || isRoleLike(line) || isCompanyLike(line) || isEducationLine(line) || looksLikeNameStopLine(line)) return false;
    const words = line.split(/\s+/).filter(Boolean);
    return words.length >= 2 && words.length <= 4 && /^[A-ZÀ-Ý][A-Za-zÀ-ÿ'’.-]+(?:\s+[A-ZÀ-Ý][A-Za-zÀ-ÿ'’.-]+){1,3}$/.test(line);
  });
  if (candidates.length) return candidates[0];
  const fromEmail = String(email || "").split("@")[0].replace(/[._-]+/g, " ").replace(/[0-9]/g, " ").trim();
  if (fromEmail.split(/\s+/).length >= 2) {
    return fromEmail.replace(/\b\w/g, (char) => char.toUpperCase()).replace(/\s+/g, " ").trim();
  }
  const fromFile = path.basename(file, path.extname(file))
    .replace(/[-_()[\].]+/g, " ")
    .replace(/\b(?:cv|resume|ats|review|checker|results|combined|final|latest|updated|for|credit|all|new|fv|uae|ch)\b/gi, " ")
    .replace(/\b(?:19|20)\d{2}\b/g, " ")
    .replace(/\b\d+\b/g, " ")
    .replace(/\s+/g, " ")
    .trim();
  if (
    !/^[a-f0-9]{8,}$/i.test(fromFile.replace(/\s+/g, "")) &&
    fromFile.replace(/[^a-z]/gi, " ").trim().split(/\s+/).filter((part) => part.length > 1).length >= 2
  ) {
    return clean(fromFile).replace(/\b\w/g, (char) => char.toUpperCase()).replace(/\s+/g, " ").trim();
  }
  return "Candidate Name";
}

function isLowConfidenceName(name) {
  const cleanName = clean(name);
  if (!cleanName || cleanName === "Candidate Name") return true;
  if (/^[a-f0-9]{8,}$/i.test(cleanName.replace(/\s+/g, ""))) return true;
  if (looksLikeNameStopLine(cleanName)) return true;
  if (/\d/.test(cleanName)) return true;
  return false;
}

function sectionLines(sections, key) {
  return sections.filter((section) => section.key === key).flatMap((section) => section.lines);
}

function allDocumentLines(sections) {
  return sections.flatMap((section) => section.lines || []);
}

function inferExperienceLines(sections) {
  const explicit = sectionLines(sections, "experience").concat(sectionLines(sections, "projects"));
  if (explicit.length) return explicit;
  return sections.flatMap((section) => section.lines).filter((line) => isBulletLine(line) || isRoleLike(line) || isCompanyLike(line) || hasDate(line));
}

function splitEntryHeading(line) {
  const value = clean(line);
  const date = dateText(value);
  const withoutDate = date ? clean(value.replace(date, " ")) : value;
  const parts = withoutDate.split(/\s*[|•·▪◦–—]\s*|\s+-\s+/).map(clean).filter(Boolean);
  return { date, parts: parts.length ? parts : [withoutDate].filter(Boolean) };
}

function evidenceTextFromLine(line) {
  const value = clean(line);
  const jobDefinition = value.match(/\b(?:job definition|responsibilities?|responsibility|role description|duties|key achievements?)\s*:\s*(.+)$/i);
  if (jobDefinition) return clean(jobDefinition[1]);
  const afterDate = value.match(/\b(?:19|20)\d{2}\b\s*(?:-|–|—|to|present|still working|current|[./]\d{1,2})*\s+(.{24,})$/i);
  if (afterDate && hasProfessionalSignal(afterDate[1])) return clean(afterDate[1]);
  const withoutLeadingDate = removeLeadingDateNoise(value);
  return withoutLeadingDate || value;
}

function parseExperienceEntries(lines) {
  const entries = [];
  let current = null;

  function push() {
    if (!current) return;
    current.lines = [...new Set(current.lines.map(clean).filter(Boolean))];
    current.bullets = [...new Set(current.bullets.map(clean).filter(Boolean))];
    if (!current.role) current.role = chooseEntryRole(current.lines);
    if (!current.company) current.company = chooseEntryCompany(current.lines);
    if (!current.dates && current.lines.some(hasDate)) current.dates = dateText(current.lines.find(hasDate));
    if (current.role || current.company || current.bullets.length) entries.push(current);
  }

  lines.forEach((line) => {
    const value = clean(line);
    const evidenceValue = evidenceTextFromLine(value);
    const heading = splitEntryHeading(value);
    const hasRole = heading.parts.some(isRoleLike);
    const hasCompany = heading.parts.some(isCompanyLike);
    const isNewEntry = !isBulletLine(line) && (hasRole || (hasCompany && (heading.date || !current)) || (hasDate(value) && current && current.bullets.length));

    if (isNewEntry) {
      push();
      current = { role: "", company: "", location: "", dates: heading.date, lines: [value], bullets: [] };
      heading.parts.forEach((part) => {
        if (!current.role && isRoleLike(part)) current.role = part;
        else if (!current.company && isCompanyLike(part)) current.company = part;
      });
      return;
    }

    if (!current) current = { role: "", company: "", location: "", dates: "", lines: [], bullets: [] };
    current.lines.push(value);
    if (isBulletLine(line)) {
      current.bullets.push(evidenceValue);
    } else if (evidenceValue !== value && isExperienceRescueLine(evidenceValue)) {
      current.bullets.push(evidenceValue);
    } else if (isExperienceRescueLine(value) && !isRoleLike(value) && !isCompanyLike(value)) {
      current.bullets.push(value);
    } else if (!current.role && isRoleLike(value)) {
      current.role = value;
    } else if (!current.company && isCompanyLike(value)) {
      current.company = value;
    } else if (!current.dates && hasDate(value)) {
      current.dates = dateText(value);
    }
  });
  push();
  return entries.slice(0, 10);
}

function hasProfessionalSignal(line) {
  const value = clean(line);
  if (!value) return false;
  if (looksLikeUnsafeEvidenceLine(value)) return false;
  return (
    hasStrongOpening(value) ||
    /\b(onboarded|trained|contributed|designed|sourced|created|monitored|strengthened|adapted|implemented|posting|processing|reconcil(?:e|ed|ing)|prepar(?:e|ed|ing)|preaparing|maintain(?:ed|ing)?|validat(?:e|ed|ing)|manag(?:e|ed|ing)|oversee(?:ing)?|tutor(?:ed|ing)?|gestito|analizzato|sviluppato|coordinato|supportato|preparato|monitorato|valutato|realizzato|creato)\b/i.test(value) ||
    roleFamilies.some((item) => item[1].test(value)) ||
    metrics(value).length > 0 ||
    tools(value).length > 0
  );
}

function looksLikeKeywordSoup(line) {
  const value = clean(line);
  const words = value.split(/\s+/).filter(Boolean);
  if (words.length < 10) return false;
  const shortWords = words.filter((word) => word.length <= 3).length;
  const titleCaseWords = words.filter((word) => /^[A-ZÀ-Ý][A-Za-zÀ-ÿ'’.-]+$/.test(word)).length;
  const punctuation = (value.match(/[.;:,]/g) || []).length;
  const signalCount = (value.match(/\b(?:and|with|for|to|by|using|through|supporting|resulting|including|across|from|worth|over|daily|monthly)\b/gi) || []).length;
  return titleCaseWords >= Math.ceil(words.length * 0.65) && shortWords <= 2 && punctuation <= 1 && signalCount <= 1;
}

function isExperienceRescueLine(line) {
  const value = evidenceTextFromLine(line);
  const words = value.split(/\s+/).filter(Boolean);
  if (words.length < 7 || words.length > 70) return false;
  if (isContactLine(value) || isSectionHeading(value) || looksLikePersonalDetailLine(value)) return false;
  if (isEducationLine(value) && !hasProfessionalSignal(value)) return false;
  if (isCompanyLike(value) && words.length <= 8) return false;
  if (looksLikeKeywordSoup(value)) return false;
  if (/^(?:pdf|page|curriculum vitae|resume|cv)\b/i.test(value)) return false;
  return hasProfessionalSignal(value);
}

function inferRescueExperienceEntries(sections, candidateName) {
  const allLines = allDocumentLines(sections);
  const rescueLines = [];
  allLines.forEach((line, index) => {
    const value = clean(line).replace(/^•\s*/, "");
    if (!isExperienceRescueLine(value)) return;
    const contextLines = allLines.slice(Math.max(0, index - 5), index + 1).map(clean).filter(Boolean);
    rescueLines.push({
      line: value,
      contextLines,
      role: chooseEntryRole(contextLines),
      company: chooseEntryCompany(contextLines, candidateName),
      dates: contextLines.find(hasDate) ? dateText(contextLines.find(hasDate)) : "",
    });
  });
  if (!rescueLines.length) return [];
  const grouped = [];
  rescueLines.forEach((item) => {
    const last = grouped[grouped.length - 1];
    const sameContext =
      last &&
      (last.role || item.role || last.company || item.company) &&
      clean(last.role).toLowerCase() === clean(item.role).toLowerCase() &&
      clean(last.company).toLowerCase() === clean(item.company).toLowerCase();
    if (sameContext) {
      last.lines = [...new Set(last.lines.concat(item.contextLines, [item.line]).map(clean).filter(Boolean))];
      last.bullets = [...new Set(last.bullets.concat(item.line).map(clean).filter(Boolean))];
      if (!last.dates && item.dates) last.dates = item.dates;
      return;
    }
    grouped.push({
      role: item.role || "Relevant Experience",
      company: item.company || "",
      location: "",
      dates: item.dates || "",
      lines: [...new Set(item.contextLines.concat(item.line).map(clean).filter(Boolean))],
      bullets: [item.line],
      parserRescue: true,
    });
  });
  return grouped
    .map((entry) => ({
      ...entry,
      bullets: entry.bullets.filter((line) => !isNonExperienceEvidenceLine(line)).slice(0, 8),
    }))
    .filter((entry) => entry.bullets.length)
    .slice(0, 8);
}

function mergeExperienceEntry(left, right) {
  return {
    role: cleanExperienceMetaFragment(left.role || right.role || ""),
    company: cleanExperienceMetaFragment(left.company || right.company || ""),
    location: clean(left.location || right.location || ""),
    dates: clean(left.dates || right.dates || ""),
    lines: [...new Set([].concat(left.lines || [], right.lines || []).map(clean).filter(Boolean))],
    bullets: [...new Set([].concat(left.bullets || [], right.bullets || []).map(clean).filter(Boolean))],
  };
}

function normalizeExperienceEntries(entries, candidateName) {
  const normalized = [];
  (entries || []).forEach((entry) => {
    let current = splitCompositeRoleCompany({
      role: cleanExperienceMetaFragment(entry.role || ""),
      company: cleanExperienceMetaFragment(entry.company || ""),
      location: clean(entry.location || ""),
      dates: clean(entry.dates || ""),
      lines: [...new Set((entry.lines || []).map(clean).filter(Boolean))],
      bullets: [...new Set((entry.bullets || []).map(clean).filter(Boolean))],
      parserRescue: Boolean(entry.parserRescue),
    }, candidateName);
    current.parserRescue = Boolean(entry.parserRescue || current.parserRescue);
    current.role = isEducationLine(current.role) || looksLikePersonalDetailLine(current.role) ? "" : current.role;
    current.company = isEducationLine(current.company) || isBadCompanyValue(current.company, candidateName) || looksLikePersonalDetailLine(current.company) ? "" : current.company;
    if (!current.role) current.role = chooseEntryRole(current.lines);
    if (!current.company) current.company = chooseEntryCompany(current.lines, candidateName);
    current = splitCompositeRoleCompany(current, candidateName);
    current.bullets = current.bullets.filter((line) => !looksLikePersonalDetailLine(line));
    const previous = normalized[normalized.length - 1];
    const currentIsIdentityOnly = !current.bullets.length && (current.role || current.company);
    const previousIsIdentityOnly = previous && !previous.bullets.length && (previous.role || previous.company);
    const complementary =
      previous &&
      ((previous.role && !previous.company && !current.role && current.company) ||
        (!previous.role && previous.company && current.role && !current.company) ||
        (previousIsIdentityOnly && current.bullets.length && (!current.role || !current.company)) ||
        (currentIsIdentityOnly && previous && previous.bullets.length && (!previous.role || !previous.company)));
    if (complementary) {
      normalized[normalized.length - 1] = mergeExperienceEntry(previous, current);
      return;
    }
    if (!current.role && !current.company && !current.bullets.length) return;
    normalized.push(current);
  });
  return normalized
    .map((entry) => ({
      ...entry,
      role: cleanExperienceMetaFragment(entry.role),
      company: isBadCompanyValue(entry.company, candidateName) ? "" : cleanExperienceMetaFragment(entry.company),
      bullets: entry.bullets.filter((line) => !isNonExperienceEvidenceLine(line)),
      parserRescue: Boolean(entry.parserRescue),
    }))
    .filter((entry) => entry.role || entry.company || entry.bullets.length)
    .slice(0, 10);
}

function metrics(text) {
  return (clean(text).match(/(?:\+?\d+(?:[.,]\d+)?%?|[$€£]\s*\+?[0-9.,]+\s*(?:m|mn|mln|bn|billion|million)?|[0-9.,]+\s*(?:transactions|investments|leads|start-?ups|ventures|countries|markets|hours|facilities|clients|companies|teams|reports|models))/gi) || []).map(clean);
}

function tools(text) {
  return (clean(text).match(/\b(?:LBO|DCF|SQL|Python|Tableau|Excel|VBA|SAP|Bloomberg|Capital IQ|FactSet|Refinitiv|Power BI|Workday|Salesforce|HubSpot|Xero|QuickBooks|Zoho)\b/gi) || []).map(clean);
}

function openingAction(text) {
  const match = clean(text).match(/^([A-Za-zÀ-ÿ]+(?:\s+[A-Za-zÀ-ÿ]+){0,2})\b/);
  return match ? match[0].toLowerCase() : "";
}

function extractEvidenceSemanticFrame(text) {
  const source = clean(text);
  const withoutOpening = stripWeakOpening(source)
    .replace(/^(led|owned|oversaw|directed|spearheaded|managed|executed|developed|coordinated|streamlined|sourced|qualified|delivered|implemented|reviewed|analy[sz]ed|prepared|built|created|conducted|evaluated|researched|modeled|modelled|validated|monitored|applied|improved|generated|compiled|identified|designed|maintained|advised|guided|negotiated|liaised|formulated|collaborated|ensured|performed|collected|assessed|examined|uncovered|allocated|drafted|interpreted|explained|constructed|partnered|used|reduced|increased|backtested|forecasted|tracked|reported|presented|calculated)\b\s*/i, "")
    .trim();
  const methodMatch = source.match(/\b(?:using|through|via|with|by)\s+([^.;]+?)(?=,\s*(?:supporting|resulting|improving|reducing|increasing|informing|enabling)|;|\.|$)/i);
  const outcomeMatch = source.match(/\b(?:supporting|resulting in|contributing to|improving|reducing|increasing|accelerating|enhancing|informing|driving|enabling|delivering|ensuring|facilitating)\s+([^.;]+?)(?=;|\.|$)/i);
  const stakeholderMatch = source.match(/\b(?:for|with|to)\s+(senior stakeholders|senior leadership|management|clients|customers|investment committee|portfolio companies|board|executive team|c-level executives|sales team|contract management team)\b/i);
  let object = withoutOpening
    .replace(/\b(?:using|through|via|with|by)\s+[^.;]+/i, " ")
    .replace(/\b(?:supporting|resulting in|contributing to|improving|reducing|increasing|accelerating|enhancing|informing|driving|enabling|delivering|ensuring|facilitating)\s+[^.;]+/i, " ")
    .replace(/\s+/g, " ")
    .trim();
  if (object.length > 160) {
    object = object.slice(0, 157).replace(/\s+\S*$/, "") + "...";
  }
  const method = methodMatch ? clean(methodMatch[1]).replace(/[,;:]+$/g, "") : "";
  const outcome = outcomeMatch ? clean(outcomeMatch[0]).replace(/[,;:]+$/g, "") : "";
  const malformedObject = /(?:^|\s)(?:the|a|an|and|or|with|through|using|by|for|to)$/i.test(object) ||
    /\b(?:manage over task|task the|through status meetings)\b/i.test(object);
  return {
    action: openingAction(source),
    object,
    method,
    outcome,
    stakeholder: stakeholderMatch ? clean(stakeholderMatch[1]) : "",
    metric: metrics(source)[0] || "",
    tool: tools(source)[0] || "",
    complete:
      Boolean(object && object.split(/\s+/).length >= 3) &&
      !malformedObject &&
      Boolean(methodMatch || outcomeMatch || stakeholderMatch || metrics(source).length || tools(source).length),
  };
}

function detectSenioritySignal(text) {
  const lower = clean(text).toLowerCase();
  if (actionLexicon.senior.some((word) => lower.startsWith(word))) return "senior";
  if (actionLexicon.associate.some((word) => lower.startsWith(word))) return "associate";
  if (actionLexicon.analyst.some((word) => lower.startsWith(word))) return "analyst";
  if (actionLexicon.weak.some((word) => lower.startsWith(word))) return "weak";
  return "unknown";
}

function classifyFamilies(text) {
  const found = roleFamilies.filter((item) => item[1].test(text)).map((item) => item[0]);
  return found.length ? found : ["general"];
}

function detectWeaknesses(text) {
  const value = clean(text);
  const weaknesses = weakLanguageRules.filter((item) => item[1].test(value)).map((item) => item[0]);
  if (value.length >= 45 && !/\b(supporting|support|resulting|contributing|improving|reducing|increasing|accelerating|enhancing|informing|inform|driving|enabling|achieving|delivering|ensuring|saving|generating|securing|facilitating|facilitate|to\s+(inform|support|improve|reduce|increase|enhance|drive|enable|deliver|facilitate))\b/i.test(value)) {
    weaknesses.push("missing_outcome_signal");
  }
  if (!metrics(value).length && value.length >= 55) weaknesses.push("missing_metric");
  return [...new Set(weaknesses)];
}

function isCleanEducationDisplayLine(line) {
  const raw = String(line || "");
  if (/^\s*[•▪●○◦❖\-–—]\s+/.test(raw)) return false;
  const value = clean(raw).replace(/^[•▪●○◦❖\-–—]\s*/, "");
  if (!value || looksLikeUnsafeEvidenceLine(value) || isContactLine(value) || isRoleLike(value) || isCompanyLike(value)) return false;
  if (/^(?:recruited|established|directed|managed|optimized|optimised|implemented|supported|prepared|built|developed|executed|analy[sz]ed|led|responsible|arrange|book|travel|exercising|day to day|track)\b/i.test(value)) return false;
  if (/\b(?:officer|manager|director|analyst|associate|consultant|specialist|recruiter|recruitment|company|consultants|ltd|limited|llc|inc|group|bank|capital)\b/i.test(value)) return false;
  if (value.length > 180) return false;
  return /\b(university|université|universita|università|universidad|college|school|business school|bsc|msc|mba|ba\b|ma\b|bachelor|master|degree|diploma|gpa|cum laude|honou?rs)\b/i.test(value);
}

function isCleanSkillDisplayItem(item) {
  const value = clean(item);
  if (!value || value.length > 44 || value.split(/\s+/).length > 4) return false;
  if (looksLikeUnsafeEvidenceLine(value) || isContactLine(value) || hasDate(value) || isRoleLike(value) || isCompanyLike(value) || isEducationLine(value)) return false;
  if (/^(?:experience|based on|including|regularly|updating|assist|responsible|track|dinner|location|office|reception|candidate|job roles?)\b/i.test(value)) return false;
  return /^[A-Za-z][A-Za-z0-9+#&(). /-]{1,42}$/.test(value);
}

function hasBrokenGeneratedRewrite(value) {
  const text = clean(value);
  return /\b(Supported|Managed|Prepared|Built|Applied|Delivered|Improved|Reviewed|Analyzed|Analysed|Executed|Developed)\s+(supported|managed|prepared|built|applied|delivered|improved|reviewed|analy[sz]ed|executed|developed|formulated|collaborated|ensured|performed|liaised|negotiated|examined|uncovered|collected|assessed)\b/i.test(text) ||
    /\b(?:Delivered|Managed|Prepared|Built|Applied|Executed|Developed|Reviewed|Analyzed|Analysed)\s+(?:available|availability|date of|place of|gender|nationality|passport|visa|architecture|design and urban planning|course|courses|certificate|education|training|language|languages|jan|feb|mar|apr|april|may|jun|jul|aug|sep|sept|oct|october|nov|dec)\b/i.test(text) ||
    /\b(?:lead|leads|leading)\s+the\s+(?:Implemented|Managed|Prepared|Developed|Executed|Supported|Led)\b/i.test(text) ||
    /\busing\s+Delivered\b|\busing\s+(?:Improved|Managed|Prepared|Developed|Executed)\b|\bkey point of contact leading for\b|\bReframe this\b|\bshow the action taken\b|\bconnect it more directly\b|\b(?:aligning|provide|including|using|for|and|the)\.$/i.test(text) ||
    /\.\.|;\.|,\.|,\s*,|\bmanage over task\b|\btask the through\b|\bthe through\b/i.test(text);
}

function buildEvidenceUnit(text, context, index) {
  const source = removeLeadingDateNoise(clean(text));
  return {
    id: `${context.entryId}:e${index + 1}`,
    source,
    section: "experience",
    role: context.role || "",
    company: context.company || "",
    dates: context.dates || "",
    action: openingAction(source),
    families: classifyFamilies(source),
    requirementMatches: detectRequirementMatches(source),
    metrics: metrics(source),
    tools: tools(source),
    semanticFrame: extractEvidenceSemanticFrame(source),
    weaknesses: detectWeaknesses(source),
    senioritySignal: detectSenioritySignal(source),
    provenance: {
      source: "original_cv",
      confidence: 0.96,
    },
    rewritePermission: "safe_reframe_only",
  };
}

function preferredFamily(unit) {
  const families = unit && unit.families && unit.families.length ? unit.families : ["general"];
  return targetRoleLens.families.find((family) => families.includes(family)) || families[0] || "general";
}

function evidenceRelevanceScore(unit) {
  const source = clean(unit && unit.source).toLowerCase();
  const family = preferredFamily(unit);
  let score = targetRoleLens.families.includes(family) ? 35 : 12;
  const requirementMatches = (unit && unit.requirementMatches) || detectRequirementMatches(source);
  requirementMatches.forEach((requirement) => {
    score += Math.max(4, Math.round((Number(requirement.weight) || 40) / 10));
  });
  score += Math.min(18, ((unit && unit.metrics) || []).length * 6);
  score += Math.min(12, ((unit && unit.tools) || []).length * 4);
  if ((unit && unit.senioritySignal) === "senior") score += 8;
  if ((unit && unit.senioritySignal) === "associate") score += 6;
  if ((unit && unit.senioritySignal) === "analyst") score += 4;
  if (((unit && unit.weaknesses) || []).includes("missing_outcome_signal")) score -= 4;
  if (((unit && unit.weaknesses) || []).includes("weak_opening_verb")) score -= 3;
  return Math.max(0, Math.min(100, score));
}

function sentenceCaseAction(value) {
  const cleanValue = clean(value);
  return cleanValue ? cleanValue.charAt(0).toUpperCase() + cleanValue.slice(1) : "";
}

function stripWeakOpening(value) {
  return clean(value)
    .replace(/^(helped with|helped|assisted in|assisted with|assisted|aided|worked on|working on|participated in|involved in|handled|dealt with|did|made|got|responsible for|tasked with|provided support|provided assistance|took care of)\b\s*/i, "")
    .replace(/^performed\s+(tasks?|duties|work|activities)\b\s*/i, "")
    .replace(/^(successfully|effectively|efficiently|proactively|actively)\b\s*/i, "")
    .trim();
}

function normalizeRewriteLanguage(value) {
  return clean(value)
    .replace(/^preaparing\b/i, "Preparing")
    .replace(/\bpreaparing\b/gi, "preparing")
    .replace(/^collect\b/i, "Collected")
    .replace(/^posting\b/i, "Posted")
    .replace(/^processing\b/i, "Processed")
    .replace(/^reconciling\b/i, "Reconciled")
    .replace(/^preparing\b/i, "Prepared")
    .replace(/^maintaining\b/i, "Maintained")
    .replace(/^validating\b/i, "Validated")
    .replace(/^managing\b/i, "Managed")
    .replace(/\band conduct\b/gi, "and conducted")
    .replace(/\band analyse\b/gi, "and analysed")
    .replace(/\band analyze\b/gi, "and analyzed")
    .replace(/\bRead and analyse\b/g, "read and analysed")
    .replace(/\bRead and analyze\b/g, "read and analyzed")
    .replace(/\bcarry out\b/gi, "carried out")
    .replace(/([a-z])\s+(Read and analysed|Read and analyzed|Analysed|Analyzed|Prepared|Built|Managed|Led|Developed|Executed|Conducted|Reviewed|Monitored|Evaluated)\b/g, "$1; $2")
    .replace(/;\s+(Read and analysed|Read and analyzed)\b/g, (match) => match.toLowerCase())
    .replace(/^assess\b/i, "Assessed")
    .replace(/^assessing\b/i, "Assessed")
    .replace(/^analyse\b/i, "Analysed")
    .replace(/^analyze\b/i, "Analyzed")
    .replace(/^examined\b/i, "Examined")
    .replace(/^formulated\b/i, "Formulated")
    .replace(/^collaborated\b/i, "Collaborated")
    .replace(/^ensured\b/i, "Ensured")
    .replace(/^liaised\b/i, "Liaised")
    .replace(/^performed\b/i, "Performed")
    .replace(/\b(successfully|effectively|efficiently|proactively|actively)\b\s*/gi, "")
    .replace(/\butili[sz]ed\b/gi, "used")
    .replace(/\bleveraged\b/gi, "used")
    .replace(/\bfamiliar with\b/gi, "used")
    .replace(/\bvarious\b/gi, "multiple")
    .replace(/\ba variety of\b/gi, "multiple")
    .replace(/\ba wide range of\b/gi, "multiple")
    .replace(/\bstrong\s+(communication|leadership|teamwork|analytical|interpersonal|organisational|organizational)\s+skills?\b/gi, "$1 capability")
    .replace(/\s+([,.;:])/g, "$1")
    .replace(/,\s*,+/g, ",")
    .replace(/([,;:])\s*\./g, ".")
    .replace(/\.{2,}/g, ".")
    .replace(/[,:;]+$/g, "")
    .replace(/(?:,\s*)?(?:\s|^)(and|or|with|through|across|including|using|by|in|of|to|for|the|a)\s*\.?$/i, "")
    .trim();
}

function hasOutcomeSignal(value) {
  return /\b(supporting|support|resulting|contributing|improving|reducing|increasing|accelerating|enhancing|informing|inform|driving|enabling|achieving|delivering|ensuring|saving|generating|securing|facilitating|facilitate|to\s+(inform|support|improve|reduce|increase|enhance|drive|enable|deliver|facilitate))\b/i.test(clean(value));
}

function canUseOwnershipAction(unit, action) {
  if (!/^(Led|Owned|Oversaw|Directed|Spearheaded)$/i.test(action)) return true;
  return /\b(led|owned|oversaw|directed|spearheaded|managed|team|workstream|project|responsible for)\b/i.test(clean(unit && unit.source));
}

function hasStrongOpening(value) {
  if (looksLikeUnsafeEvidenceLine(value)) return false;
  return /^(led|owned|oversaw|directed|spearheaded|managed|managing|executed|executing|developed|developing|coordinated|coordinating|streamlined|streamlining|sourced|sourcing|qualified|delivered|delivering|implemented|implementing|reviewed|reviewing|analy[sz]ed|analy[sz]ing|prepared|preparing|preaparing|built|building|created|creating|conducted|conducting|evaluated|evaluating|researched|researching|modeled|modelled|validated|validating|processed|processing|posted|posting|reconciled|reconciling|monitored|monitoring|applied|improved|generated|compiled|identified|designed|maintained|maintaining|advised|guided|negotiated|liaised|formulated|collaborated|ensured|performed|collected|collect|assessed|assess|examined|uncovered|allocated|drafted|interpreted|explained|constructed|partnered|used|reduced|increased|backtested|forecast|forecasted|tracked|reported|presented|calculated)\b/i.test(clean(value));
}

function planRewrite(unit) {
  const familyKey = preferredFamily(unit);
  const family = rewriteFamilies[familyKey] || rewriteFamilies.general;
  const seniority = (unit && unit.senioritySignal) || "unknown";
  const action = family.action[seniority] || family.action.unknown;
  const actions = [];
  const weaknesses = (unit && unit.weaknesses) || [];
  if (weaknesses.includes("weak_opening_verb") || seniority === "weak") actions.push("strengthen_action");
  if (weaknesses.includes("filler_language") || weaknesses.includes("buzzword_overload")) actions.push("remove_filler");
  if (weaknesses.includes("vague_quantity")) actions.push("reduce_vague_quantity");
  if (weaknesses.includes("missing_outcome_signal")) actions.push("surface_safe_outcome");
  if (weaknesses.includes("missing_metric")) actions.push("flag_metric_gap");
  if (familyKey !== "general") actions.push("align_to_" + familyKey);
  return {
    evidenceId: unit.id,
    family: familyKey,
    relevance: evidenceRelevanceScore(unit),
    action: canUseOwnershipAction(unit, action) ? action : family.action.associate || family.action.analyst || "Delivered",
    object: family.object,
    outcome: hasOutcomeSignal(unit.source) ? "" : family.outcome,
    rewriteActions: [...new Set(actions)],
  };
}

function buildRewriteCandidates(unit, plan) {
  const source = clean(unit && unit.source);
  if (looksLikeUnsafeEvidenceLine(source)) return [];
  const base = normalizeRewriteLanguage(source);
  const stripped = normalizeRewriteLanguage(stripWeakOpening(source));
  if (looksLikeUnsafeEvidenceLine(base) || looksLikeUnsafeEvidenceLine(stripped)) return [];
  const candidates = [];
  const action = plan.action || "Delivered";
  const lowerStripped = stripped ? stripped.charAt(0).toLowerCase() + stripped.slice(1) : "";
  const hasWeakOpening = ((unit && unit.weaknesses) || []).includes("weak_opening_verb") || (unit && unit.senioritySignal) === "weak";
  const needsAction = hasWeakOpening && !hasStrongOpening(base);
  const canAppendOutcome = Boolean(plan.outcome && stripped && !/[.!?].{8,}/.test(base) && base.length <= 220);

  candidates.push(sentenceCaseAction(base));
  const semanticCandidate = buildSemanticRewriteCandidate(unit, plan);
  if (semanticCandidate) {
    candidates.push(semanticCandidate);
  }
  if (needsAction && stripped && stripped !== base) {
    candidates.push(`${action} ${lowerStripped}`);
  }
  if (canAppendOutcome && (needsAction || hasWeakOpening)) {
    candidates.push(
      needsAction
        ? `${action} ${lowerStripped}, ${plan.outcome}`
        : `${base.replace(/[.!?]+$/g, "")}, ${plan.outcome}`
    );
  }
  return [...new Set(candidates.map((candidate) => {
    const normalized = normalizeRewriteLanguage(candidate);
    return /[.!?]$/.test(normalized) ? normalized : normalized + ".";
  }).filter((candidate) => candidate && !hasBrokenGeneratedRewrite(candidate) && !looksLikeUnsafeEvidenceLine(candidate)))];
}

function buildSemanticRewriteCandidate(unit, plan) {
  const frame = unit && unit.semanticFrame;
  if (!frame || !frame.complete || !frame.object) return "";
  const action = plan.action || "Delivered";
  if (!canUseOwnershipAction(unit, action)) return "";
  const object = normalizeRewriteLanguage(frame.object).replace(/[.!?]+$/g, "");
  if (!object || object.split(/\s+/).length < 3) return "";
  const clauses = [];
  if (frame.method && !object.toLowerCase().includes(frame.method.toLowerCase())) {
    clauses.push("using " + frame.method);
  } else if (frame.tool && !object.toLowerCase().includes(frame.tool.toLowerCase())) {
    clauses.push("using " + frame.tool);
  }
  if (frame.stakeholder && !object.toLowerCase().includes(frame.stakeholder.toLowerCase())) {
    clauses.push("for " + frame.stakeholder);
  }
  if (frame.outcome && !object.toLowerCase().includes(frame.outcome.toLowerCase())) {
    clauses.push(frame.outcome);
  } else if (plan.outcome && !hasOutcomeSignal(object)) {
    clauses.push(plan.outcome);
  }
  return normalizeRewriteLanguage(
    action + " " + object.charAt(0).toLowerCase() + object.slice(1) +
      (clauses.length ? ", " + clauses.join(", ") : "")
  );
}

function validateRewrite(unit, candidate) {
  const source = clean(unit && unit.source);
  const rewritten = clean(candidate);
  const warnings = [];
  if (looksLikeUnsafeEvidenceLine(source)) warnings.push("unsafe_source_line");
  if (looksLikeUnsafeEvidenceLine(rewritten)) warnings.push("unsafe_rewrite_line");
  if (!rewritten || rewritten.split(/\s+/).length < 5) warnings.push("too_short");
  if (rewritten.length > 360) warnings.push("too_long");
  if (/\bmanage over task\b|\btask the through\b|\bthe through\b/i.test(source + " " + rewritten)) warnings.push("malformed_extracted_line");
  if (hasBrokenGeneratedRewrite(rewritten)) warnings.push("broken_rewrite_text");
  if (/\b(Supported|Managed|Prepared|Built|Applied|Delivered|Improved|Reviewed|Analyzed|Analysed|Executed|Developed)\s+(supported|managed|prepared|built|applied|delivered|improved|reviewed|analy[sz]ed|executed|developed|formulated|collaborated|ensured|performed|liaised|negotiated|examined|uncovered|collected|assessed)\b/i.test(rewritten)) warnings.push("double_action");
  if (/\.\.|;\.|,\.|,\s*,/.test(rewritten)) warnings.push("broken_punctuation");
  const inventedDemoMetrics = ["25 close-cycle", "12 monthly", "$12.5m", "$45m", "100%", "18%", "20%"];
  if (inventedDemoMetrics.some((metric) => rewritten.toLowerCase().includes(metric.toLowerCase()) && !source.toLowerCase().includes(metric.toLowerCase()))) warnings.push("unsupported_demo_metric");
  metrics(source).forEach((metric) => {
    if (!rewritten.toLowerCase().includes(metric.toLowerCase())) warnings.push("metric_lost");
  });
  tools(source).forEach((tool) => {
    if (!rewritten.toLowerCase().includes(tool.toLowerCase())) warnings.push("tool_lost");
  });
  if (/^(Led|Owned|Oversaw|Directed|Spearheaded)\b/.test(rewritten) && !canUseOwnershipAction(unit, rewritten.split(/\s+/)[0])) {
    warnings.push("ownership_inflation");
  }
  if (/\b(revenue|profit|saved|increased|reduced)\b/i.test(rewritten) && !/\b(revenue|profit|saved|increased|reduced)\b/i.test(source)) {
    warnings.push("unsupported_commercial_result");
  }
  return {
    passed: !warnings.length,
    warnings: [...new Set(warnings)],
  };
}

function scoreRewriteCandidate(unit, plan, candidate) {
  const validation = validateRewrite(unit, candidate);
  let score = plan.relevance || 0;
  if (/^(Led|Built|Prepared|Managed|Executed|Developed|Analyzed|Analysed|Evaluated|Streamlined|Sourced|Monitored|Validated|Supported|Contributed|Delivered|Improved|Applied|Coordinated|Conducted|Created|Reviewed)\b/.test(candidate)) score += 18;
  if (hasOutcomeSignal(candidate)) score += 12;
  if (((unit && unit.metrics) || []).length) score += 8;
  if (((unit && unit.tools) || []).length) score += 5;
  if (/\b(helped|assisted|participated in|worked on|responsible for|successfully|effectively|proactively)\b/i.test(candidate)) score -= 12;
  score -= validation.warnings.length * 30;
  return score;
}

function rewriteEvidenceUnit(unit) {
  const plan = planRewrite(unit);
  if (hasStrongOpening(unit.source) && !/(filler_language|buzzword_overload|vague_quantity|first_person_language)/.test((unit.weaknesses || []).join(" "))) {
    let preserved = normalizeRewriteLanguage(unit.source);
    if (plan.outcome && plan.family !== "general" && !hasOutcomeSignal(preserved) && preserved.length <= 220) {
      const withOutcome = normalizeRewriteLanguage(`${preserved.replace(/[.!?]+$/g, "")}, ${plan.outcome}`);
      if (!hasBrokenGeneratedRewrite(withOutcome) && !looksLikeUnsafeEvidenceLine(withOutcome)) {
        preserved = withOutcome;
      }
    }
    return {
      evidenceId: unit.id,
      original: unit.source,
      rewritten: /[.!?]$/.test(preserved) ? preserved : preserved + ".",
      family: plan.family,
      relevance: plan.relevance,
      rewriteActions: plan.rewriteActions.filter((action) => action !== "surface_safe_outcome" && action !== "flag_metric_gap"),
      validation: validateRewrite(unit, preserved),
      newClaimsAdded: false,
    };
  }
  const candidates = buildRewriteCandidates(unit, plan)
    .map((candidate) => ({
      text: candidate,
      score: scoreRewriteCandidate(unit, plan, candidate),
      validation: validateRewrite(unit, candidate),
    }))
    .filter((candidate) => candidate.validation.passed)
    .sort((left, right) => right.score - left.score);
  const selected = candidates[0] || {
    text: normalizeRewriteLanguage(unit.source).replace(/[.!?]?$/, "."),
    score: 0,
    validation: validateRewrite(unit, unit.source),
  };
  return {
    evidenceId: unit.id,
    original: unit.source,
    rewritten: selected.text,
    family: plan.family,
    relevance: plan.relevance,
    rewriteActions: plan.rewriteActions,
    validation: selected.validation,
    newClaimsAdded: false,
  };
}

function isRenderableRewrite(rewrite) {
  if (!rewrite || !(rewrite.validation && rewrite.validation.passed)) return false;
  const original = clean(rewrite.original || "");
  const rewritten = clean(rewrite.rewritten || "");
  if (!rewritten || looksLikeUnsafeEvidenceLine(original) || looksLikeUnsafeEvidenceLine(rewritten)) return false;
  if (hasBrokenGeneratedRewrite(rewritten)) return false;
  return true;
}

function buildTailoredSummary(ast, plannedEntries) {
  const familyCounts = {};
  const requirementCoverage = summarizeRequirementCoverage(ast.evidenceUnits || []);
  plannedEntries.forEach((entry) => {
    entry.rewrites.forEach((rewrite) => {
      familyCounts[rewrite.family] = (familyCounts[rewrite.family] || 0) + 1;
    });
  });
  const families = topCounts(familyCounts, 3).map((item) => item.key.replace(/_/g, " "));
  const strongestRequirements = requirementCoverage
    .filter((item) => item.status !== "missing")
    .sort((left, right) => right.weight + right.evidenceStrength - (left.weight + left.evidenceStrength))
    .slice(0, 3)
    .map((item) => item.label.toLowerCase());
  const title = targetRoleLens.title;
  if (ast.summary && ast.summary.split(/\s+/).length >= 10 && ast.summary.length <= 420) {
    return normalizeRewriteLanguage(ast.summary);
  }
  if (strongestRequirements.length) {
    return normalizeRewriteLanguage(
      `${ast.candidate.name === "Candidate Name" ? "Candidate" : ast.candidate.name} brings experience across ${strongestRequirements.join(", ")}, with evidence positioned for ${title}.`
    );
  }
  return normalizeRewriteLanguage(
    `${ast.candidate.name === "Candidate Name" ? "Candidate" : ast.candidate.name} brings experience across ${families.join(", ") || "business, finance, and operations"}, with a profile positioned for ${title}.`
  );
}

function scorePlannedEntry(entry) {
  const rewrites = (entry && entry.rewrites) || [];
  if (!rewrites.length) return 0;
  const best = rewrites.reduce((max, rewrite) => Math.max(max, rewrite.relevance || 0), 0);
  const average = Math.round(rewrites.reduce((sum, rewrite) => sum + (rewrite.relevance || 0), 0) / rewrites.length);
  const identityBonus = (entry.role ? 6 : 0) + (entry.company ? 6 : 0) + (entry.dates ? 3 : 0);
  return best * 0.65 + average * 0.35 + identityBonus;
}

function rankPlannedEntryRewrites(rewrites) {
  const safe = (rewrites || []).filter((rewrite) => rewrite && rewrite.validation && rewrite.validation.passed);
  const ranked = safe.sort((left, right) => {
    const rightCoverage = ((right.requirementMatches || []).reduce((sum, item) => sum + (item.weight || 0), 0));
    const leftCoverage = ((left.requirementMatches || []).reduce((sum, item) => sum + (item.weight || 0), 0));
    return (right.relevance + rightCoverage / 10) - (left.relevance + leftCoverage / 10);
  });
  const strong = ranked.filter((rewrite) => rewrite.relevance >= 30);
  return (strong.length >= 2 ? strong : ranked).slice(0, 4);
}

function buildTailoredDocumentPlan(ast, plannedEntries, requirementCoverage) {
  const hasExperiencedProfile = plannedEntries.length >= 2 || (ast.evidenceUnits || []).length >= 6;
  const missingCritical = (requirementCoverage || []).filter((item) => item.priority === "critical" && item.status === "missing");
  const partialImportant = (requirementCoverage || []).filter((item) => item.priority === "important" && item.status !== "strong");
  const allRewrites = plannedEntries.flatMap((entry) => entry.rewrites || []);
  const hasMetrics = allRewrites.some((rewrite) => (metrics(rewrite.original || rewrite.rewritten) || []).length);
  const hasOutcomeLanguage = allRewrites.some((rewrite) => hasOutcomeSignal(rewrite.rewritten || ""));
  const hasRequirementEvidence = (requirementCoverage || []).some((item) => item.status === "strong");
  return {
    version: "phase_6_document_planner",
    targetRole: targetRoleLens.title,
    sectionOrder: hasExperiencedProfile
      ? ["profile", "professional_experience", "education", "skills", "certifications", "languages"]
      : ["profile", "education", "professional_experience", "skills", "certifications", "languages"],
    evidenceStrategy: {
      leadWith: topCounts(
        plannedEntries.reduce((counts, entry) => {
          (entry.rewrites || []).forEach((rewrite) => {
            counts[rewrite.family] = (counts[rewrite.family] || 0) + 1;
          });
          return counts;
        }, {}),
        4
      ).map((item) => item.key),
      missingCritical: missingCritical.map((item) => item.label),
      partialImportant: partialImportant.map((item) => item.label),
    },
    qualityGates: {
      requiresNameConfirmation: (ast.warnings || []).includes("missing_name"),
      useOriginalPreviewFallback: (ast.warnings || []).includes("weak_experience_ast"),
      experienceEntriesPlanned: plannedEntries.length,
      hasMetricEvidence: hasMetrics,
      hasOutcomeLanguage,
      hasStrongRequirementEvidence: hasRequirementEvidence,
      rewriteValidationPassed: plannedEntries.every((entry) =>
        (entry.rewrites || []).every((rewrite) => rewrite.validation && rewrite.validation.passed)
      ),
    },
    reviewLearnedPriorities: [
      !hasMetrics ? "Quantification is weak or missing; do not invent metrics, but preserve any source numbers and ask when material." : "",
      !hasOutcomeLanguage ? "Outcome language is thin; add conservative supported outcomes only where the source evidence allows it." : "",
      !hasRequirementEvidence ? "Role-requirement coverage is weak; keep original preview fallback available and avoid overstating fit." : "",
    ].filter(Boolean),
  };
}

function buildTailoredDocument(ast) {
  const plannedEntries = ast.experience
    .map((entry, index) => {
      const role = sanitizeRenderedRole(entry.role);
      const company = sanitizeRenderedCompany(entry.company, ast.candidate && ast.candidate.name);
      const rewrites = (entry.evidence || [])
        .map(rewriteEvidenceUnit)
        .map((rewrite) => ({
          ...rewrite,
          requirementMatches: detectRequirementMatches(rewrite.original || rewrite.rewritten || ""),
        }))
        .filter(isRenderableRewrite);
      return {
        id: entry.id,
        index,
        role,
        company,
        dates: entry.dates,
        confidence: entry.confidence,
        rewrites: rankPlannedEntryRewrites(rewrites),
      };
    })
    .filter((entry) => entry.rewrites.length && (entry.role || entry.company))
    .sort((left, right) => scorePlannedEntry(right) - scorePlannedEntry(left))
    .slice(0, 6);
  const allRewrites = plannedEntries.flatMap((entry) => entry.rewrites);
  const requirementCoverage = summarizeRequirementCoverage(ast.evidenceUnits || []);
  const documentPlan = buildTailoredDocumentPlan(ast, plannedEntries, requirementCoverage);
  const warnings = [];
  if (!plannedEntries.length) warnings.push("no_planned_experience");
  if (allRewrites.some((rewrite) => !rewrite.validation.passed)) warnings.push("rewrite_validation_failed");
  if ((ast.warnings || []).includes("missing_name")) warnings.push("confirm_candidate_name");
  if ((ast.warnings || []).includes("weak_experience_ast")) warnings.push("fallback_to_original_preview_recommended");
  return {
    targetRole: targetRoleLens.title,
    candidate: ast.candidate,
    summary: buildTailoredSummary(ast, plannedEntries),
    skills: ast.skills.slice(0, 18),
    education: ast.education.slice(0, 6),
    certifications: ast.certifications.slice(0, 6),
    languages: ast.languages.slice(0, 6),
    experience: plannedEntries,
    rewrites: allRewrites,
    requirementCoverage,
    documentPlan,
    warnings,
    quality: {
      relevance: allRewrites.length ? Math.round(allRewrites.reduce((sum, item) => sum + item.relevance, 0) / allRewrites.length) : 0,
      changed: allRewrites.filter((item) => item.original !== item.rewritten).length,
      validationPassed: allRewrites.filter((item) => item.validation.passed).length,
      requirementCoverage: requirementCoverage.length
        ? Math.round(
            requirementCoverage.filter((item) => item.status === "strong").length /
              requirementCoverage.length *
              100
          )
        : 0,
    },
  };
}

function renderTailoredCvHtml(document) {
  const contact = [document.candidate.phone, document.candidate.email].filter(Boolean).join(" | ");
  const cleanEducation = (document.education || []).filter(isCleanEducationDisplayLine).slice(0, 5);
  const education = cleanEducation.length
    ? `<section><h2>Education</h2>${cleanEducation.map((line) => `<p>${html(line)}</p>`).join("")}</section>`
    : "";
  const skills = document.skills.length
    ? `<section><h2>Skills</h2><div class="skills">${document.skills.map((item) => `<span>${html(item)}</span>`).join("")}</div></section>`
    : "";
  const certifications = document.certifications && document.certifications.length
    ? `<section><h2>Certifications</h2>${document.certifications.map((line) => `<p>${html(line)}</p>`).join("")}</section>`
    : "";
  const languages = document.languages && document.languages.length
    ? `<section><h2>Languages</h2><p>${html(document.languages.join(" · "))}</p></section>`
    : "";
  const experience = document.experience.length
    ? `<section><h2>Professional Experience</h2>${document.experience.map((entry) => `<article><div class="entry-head"><div><strong>${html(entry.role || "Relevant Experience")}</strong>${entry.company ? `<em>${html(entry.company)}</em>` : ""}</div>${entry.dates ? `<span>${html(entry.dates)}</span>` : ""}</div><ul>${entry.rewrites.map((rewrite) => `<li>${html(rewrite.rewritten)}</li>`).join("")}</ul></article>`).join("")}</section>`
    : "";
  return `<!doctype html><html lang="en"><head><meta charset="utf-8"><title>${html(document.candidate.name)} - Planned Tailored CV</title><style>
*{box-sizing:border-box}body{margin:0;background:#eef4fb;color:#111827;font-family:Inter,Arial,sans-serif}.sheet{width:min(850px,calc(100% - 40px));margin:32px auto;background:#fff;padding:52px 64px;border:1px solid #d9e0ea;box-shadow:0 24px 70px rgba(20,33,61,.14)}header{text-align:center;margin-bottom:28px}h1{font-family:Georgia,serif;font-size:31px;letter-spacing:.08em;text-transform:uppercase;margin:0 0 8px;color:#050505}.contact{font-size:13px;color:#374151}h2{font-size:13px;text-transform:uppercase;letter-spacing:.13em;border-bottom:1.5px solid #111827;padding-bottom:5px;margin:24px 0 10px;color:#050505}p{font-size:13.5px;line-height:1.48;margin:0 0 8px}article{margin:0 0 17px}.entry-head{display:flex;justify-content:space-between;gap:18px;align-items:flex-start;font-size:13.5px}.entry-head strong{display:block;font-size:14px}.entry-head em{display:block;font-style:normal;color:#374151;margin-top:2px}.entry-head span{white-space:nowrap;color:#4b5563;font-size:12.5px}ul{margin:7px 0 0;padding-left:18px}li{font-size:13.25px;line-height:1.46;margin:0 0 5px}.skills{display:flex;flex-wrap:wrap;gap:6px}.skills span{border:1px solid #d5dce8;border-radius:999px;padding:4px 8px;font-size:12px;background:#f8fafc}@media(max-width:680px){.sheet{width:100%;margin:0;padding:32px 22px;border-width:0}.entry-head{display:block}.entry-head span{display:block;white-space:normal;margin-top:3px}.skills span{font-size:11.5px}}</style></head><body><main class="sheet"><header><h1>${html(document.candidate.name)}</h1>${contact ? `<div class="contact">${html(contact)}</div>` : ""}</header><section><h2>Profile</h2><p>${html(document.summary)}</p></section>${experience}${education}${skills}${certifications}${languages}</main></body></html>`;
}

function buildAst(file, text, extraction = {}) {
  const lines = mergeLines(text);
  const sections = parseSections(lines);
  const email = detectEmail(text);
  const name = detectName(lines, file, email);
  const buildExperienceModels = (entries) => normalizeExperienceEntries(entries, name).map((entry, index) => {
    const normalized = {
      id: `exp${index + 1}`,
      role: clean(entry.role),
      company: clean(entry.company),
      location: clean(entry.location),
      dates: clean(entry.dates),
      rawLines: entry.lines,
      bullets: entry.bullets,
      parserRescue: Boolean(entry.parserRescue),
      confidence: {
        role: entry.parserRescue ? (entry.role && entry.role !== "Relevant Experience" ? 0.58 : 0.28) : (entry.role ? 0.82 : 0.2),
        company: entry.parserRescue ? (entry.company ? 0.52 : 0.18) : (entry.company ? 0.78 : 0.2),
        dates: entry.parserRescue ? (entry.dates ? 0.62 : 0.18) : (entry.dates ? 0.88 : 0.25),
      },
    };
    normalized.evidence = normalized.bullets
      .filter((line) => !isNonExperienceEvidenceLine(line))
      .slice(0, 8)
      .map((line, bulletIndex) => buildEvidenceUnit(line, {
        entryId: normalized.id,
        role: normalized.role,
        company: normalized.company,
        dates: normalized.dates,
      }, bulletIndex));
    if (normalized.parserRescue) {
      normalized.evidence = normalized.evidence.map((unit) => ({
        ...unit,
        provenance: {
          source: "original_cv_parser_rescue",
          confidence: 0.78,
        },
      }));
    }
    return normalized;
  });
  let experienceEntries = buildExperienceModels(parseExperienceEntries(inferExperienceLines(sections)));
  let evidenceUnits = experienceEntries.flatMap((entry) => entry.evidence);
  const parserRescueEntries = evidenceUnits.length < 3 ? inferRescueExperienceEntries(sections, name) : [];
  if (parserRescueEntries.length) {
    experienceEntries = buildExperienceModels(
      parseExperienceEntries(inferExperienceLines(sections)).concat(parserRescueEntries)
    );
    evidenceUnits = experienceEntries.flatMap((entry) => entry.evidence);
  }
  let education = sectionLines(sections, "education").filter(isCleanEducationDisplayLine).slice(0, 8);
  if (!education.length) {
    education = allDocumentLines(sections)
      .filter(isCleanEducationDisplayLine)
      .slice(0, 8);
  }
  const skills = sectionLines(sections, "skills")
    .join(", ")
    .split(/\s*[,|/•]\s*/)
    .map(clean)
    .filter(isCleanSkillDisplayItem)
    .slice(0, 40);
  const certifications = sectionLines(sections, "certifications")
    .map(clean)
    .filter((line) => line && !isContactLine(line) && !looksLikeUnsafeEvidenceLine(line))
    .slice(0, 12);
  const languages = sectionLines(sections, "languages")
    .join(", ")
    .split(/\s*[,|/•]\s*/)
    .map(clean)
    .filter((item) => item && item.split(/\s+/).length <= 6 && !hasDate(item))
    .slice(0, 12);
  const warnings = [];
  if (isLowConfidenceName(name)) warnings.push("missing_name");
  if (!email) warnings.push("missing_email");
  if (!education.length) warnings.push("missing_education");
  if (!experienceEntries.length || evidenceUnits.length < 3) warnings.push("weak_experience_ast");
  if (parserRescueEntries.length) warnings.push("parser_rescue_used");
  if (extraction.source === "ocr_tesseract") warnings.push("ocr_text_extracted");
  if (extraction.source === "pdftotext_layout") warnings.push("layout_text_extracted");
  if (experienceEntries.some((entry) => !entry.role && !entry.company)) warnings.push("entry_identity_low_confidence");
  return {
    sourceFile: path.basename(file),
    type: "candidate_cv",
    extraction: {
      source: extraction.source || "unknown",
      score: extraction.score || meaningfulTextScore(text),
      attemptedSources: extraction.attemptedSources || [],
    },
    candidate: {
      name,
      email,
      phone: detectPhone(text),
    },
    sections: sections.map((section) => ({ key: section.key, count: section.lines.length })),
    summary: sectionLines(sections, "summary").find((line) => clean(line).split(/\s+/).length >= 8) || "",
    experience: experienceEntries,
    education,
    skills: [...new Set(skills)],
    certifications: [...new Set(certifications)],
    languages: [...new Set(languages)],
    evidenceUnits,
    quality: scoreAstQuality({ name, email, education, experienceEntries, evidenceUnits, skills }),
    warnings,
  };
}

function scoreAstQuality(model) {
  const identity = (model.name !== "Candidate Name" ? 30 : 0) + (model.email ? 20 : 0);
  const structure = Math.min(25, model.experienceEntries.length * 5) + Math.min(15, model.education.length * 3);
  const evidence = Math.min(20, model.evidenceUnits.length * 2) + Math.min(10, model.skills.length);
  return Math.min(100, identity + structure + evidence);
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

function splitReviewSentences(text) {
  return String(text || "")
    .replace(/\s+/g, " ")
    .split(/(?<=[.!?])\s+|\n+/)
    .map(clean)
    .filter((line) => line.split(/\s+/).length >= 6)
    .filter((line) => !/^page\s+\d+\b/i.test(line))
    .slice(0, 900);
}

function analyzeReviewReport(file, text) {
  const sentences = splitReviewSentences(text);
  const themes = {};
  sentences.forEach((sentence) => {
    reviewCritiqueRules.forEach((rule) => {
      if (!rule.pattern.test(sentence)) return;
      themes[rule.key] = themes[rule.key] || {
        key: rule.key,
        label: rule.label,
        weight: rule.weight,
        rewriteAction: rule.rewriteAction,
        count: 0,
        examples: [],
      };
      themes[rule.key].count += 1;
      if (themes[rule.key].examples.length < 5) {
        themes[rule.key].examples.push(sentence.length > 260 ? sentence.slice(0, 257) + "..." : sentence);
      }
    });
  });
  const rankedThemes = Object.values(themes).sort((left, right) => {
    return right.count * right.weight - left.count * left.weight;
  });
  return {
    sourceFile: path.basename(file),
    type: "review_report",
    textLength: String(text || "").length,
    sentenceCount: sentences.length,
    themes: rankedThemes,
    topThemes: rankedThemes.slice(0, 6).map((theme) => theme.key),
    rewritePriorities: rankedThemes.slice(0, 8).map((theme) => ({
      key: theme.key,
      label: theme.label,
      weight: theme.weight,
      rewriteAction: theme.rewriteAction,
      evidenceCount: theme.count,
    })),
  };
}

function summarise(results) {
  const cvs = results.filter((item) => item.type === "candidate_cv");
  const reviewReports = results.filter((item) => item.type === "review_report");
  const failed = results.filter((item) => item.failed);
  const evidenceUnits = cvs.flatMap((item) => item.evidenceUnits || []);
  const plannedRewrites = cvs.flatMap((item) => (item.tailoredDocument && item.tailoredDocument.rewrites) || []);
  const warningCounts = {};
  const familyCounts = {};
  const weaknessCounts = {};
  const rewriteActionCounts = {};
  const reviewThemeCounts = {};
  const reviewRewriteActionCounts = {};
  cvs.forEach((item) => (item.warnings || []).forEach((warning) => {
    warningCounts[warning] = (warningCounts[warning] || 0) + 1;
  }));
  evidenceUnits.forEach((unit) => {
    (unit.families || []).forEach((family) => {
      familyCounts[family] = (familyCounts[family] || 0) + 1;
    });
    (unit.weaknesses || []).forEach((weakness) => {
      weaknessCounts[weakness] = (weaknessCounts[weakness] || 0) + 1;
    });
  });
  plannedRewrites.forEach((rewrite) => {
    (rewrite.rewriteActions || []).forEach((action) => {
      rewriteActionCounts[action] = (rewriteActionCounts[action] || 0) + 1;
    });
  });
  reviewReports.forEach((report) => {
    (report.themes || []).forEach((theme) => {
      reviewThemeCounts[theme.key] = (reviewThemeCounts[theme.key] || 0) + theme.count;
      reviewRewriteActionCounts[theme.rewriteAction] = (reviewRewriteActionCounts[theme.rewriteAction] || 0) + theme.count;
    });
  });
  return {
    sources: results.length,
    candidateCvs: cvs.length,
    reviewReports: reviewReports.length,
    failed: failed.length,
    extractionSources: cvs.reduce((counts, item) => {
      const source = item.extraction && item.extraction.source ? item.extraction.source : "unknown";
      counts[source] = (counts[source] || 0) + 1;
      return counts;
    }, {}),
    experienceEntries: cvs.reduce((sum, item) => sum + item.experience.length, 0),
    structuredExperienceEntries: cvs.reduce((sum, item) => sum + item.experience.filter((entry) => entry.role && entry.company && entry.evidence && entry.evidence.length).length, 0),
    roleCompanyEntryCoverage: cvs.reduce((sum, item) => sum + item.experience.filter((entry) => entry.role && entry.company).length, 0),
    evidenceUnits: evidenceUnits.length,
    plannedRewrites: plannedRewrites.length,
    changedRewrites: plannedRewrites.filter((item) => item.original !== item.rewritten).length,
    validatedRewrites: plannedRewrites.filter((item) => item.validation && item.validation.passed).length,
    averageAstQuality: cvs.length ? Math.round(cvs.reduce((sum, item) => sum + item.quality, 0) / cvs.length) : 0,
    averageRewriteRelevance: plannedRewrites.length ? Math.round(plannedRewrites.reduce((sum, item) => sum + item.relevance, 0) / plannedRewrites.length) : 0,
    warnings: warningCounts,
    families: familyCounts,
    weaknesses: weaknessCounts,
    rewriteActions: rewriteActionCounts,
    reviewThemes: reviewThemeCounts,
    reviewRewriteActions: reviewRewriteActionCounts,
  };
}

function renderIndex(results, summary) {
  const rows = results.map((item) => {
    if (item.failed) {
      return `<tr><td>${html(item.file)}</td><td>Failed</td><td colspan="5">${html(item.error)}</td></tr>`;
    }
    if (item.type === "review_report") {
      return `<tr><td>${html(item.sourceFile)}</td><td>Review report</td><td colspan="5">${html((item.topThemes || []).join(", ") || "No critique themes detected")}</td></tr>`;
    }
    return `<tr><td><a href="${html(item.jsonFile)}">${html(item.candidate.name)}</a><small>${html(item.sourceFile)}</small>${item.tailoredHtmlFile ? `<small><a href="${html(item.tailoredHtmlFile)}">planned tailored CV</a></small>` : ""}</td><td>${item.quality}</td><td>${item.experience.length}</td><td>${item.evidenceUnits.length}</td><td>${html((item.warnings || []).join(", ") || "pass")}</td><td>${html([...new Set(item.evidenceUnits.flatMap((unit) => unit.families || []))].slice(0, 4).join(", "))}</td></tr>`;
  }).join("");
  return `<!doctype html><html lang="en"><head><meta charset="utf-8"><title>CV Intelligence Corpus</title><style>
body{margin:0;background:#eef4fb;color:#172033;font-family:Inter,system-ui,sans-serif}main{width:min(1280px,calc(100% - 40px));margin:34px auto}.stats{display:grid;grid-template-columns:repeat(9,1fr);gap:12px;margin:22px 0}.stat,table{background:#fff;border:1px solid #d9e0ea;border-radius:8px;box-shadow:0 14px 40px rgba(20,33,61,.08)}.stat{padding:16px}.stat strong{display:block;font-size:28px}table{width:100%;border-collapse:collapse;overflow:hidden}th,td{padding:12px 14px;border-bottom:1px solid #e6ebf2;text-align:left;vertical-align:top;font-size:13px}th{background:#132c52;color:#fff}a{color:#2f61e8;font-weight:800;text-decoration:none}small{display:block;color:#667085;margin-top:4px}pre{white-space:pre-wrap;background:#fff;border:1px solid #d9e0ea;padding:16px;border-radius:8px}@media(max-width:1100px){.stats{grid-template-columns:repeat(3,1fr)}}@media(max-width:700px){.stats{grid-template-columns:repeat(2,1fr)}}</style></head><body><main><h1>CV Intelligence Corpus</h1><p>Phase 7 adds review-report learning on top of AST normalization, evidence planning, rewrite validation, semantic frames, and clean tailored-document generation.</p><div class="stats"><div class="stat"><strong>${summary.candidateCvs}</strong><span>candidate CVs</span></div><div class="stat"><strong>${summary.reviewReports}</strong><span>review reports</span></div><div class="stat"><strong>${summary.experienceEntries}</strong><span>experience entries</span></div><div class="stat"><strong>${summary.structuredExperienceEntries}</strong><span>structured entries</span></div><div class="stat"><strong>${summary.roleCompanyEntryCoverage}</strong><span>role + company</span></div><div class="stat"><strong>${summary.evidenceUnits}</strong><span>evidence units</span></div><div class="stat"><strong>${summary.plannedRewrites}</strong><span>planned rewrites</span></div><div class="stat"><strong>${summary.changedRewrites}</strong><span>changed rewrites</span></div><div class="stat"><strong>${summary.averageRewriteRelevance}</strong><span>avg relevance</span></div></div><h2>Warnings</h2><pre>${html(JSON.stringify(summary.warnings, null, 2))}</pre><h2>Rewrite Actions</h2><pre>${html(JSON.stringify(summary.rewriteActions, null, 2))}</pre><h2>Review-Learned Themes</h2><pre>${html(JSON.stringify(summary.reviewThemes, null, 2))}</pre><h2>Review-Learned Rewrite Actions</h2><pre>${html(JSON.stringify(summary.reviewRewriteActions, null, 2))}</pre><table><thead><tr><th>CV</th><th>AST Quality</th><th>Entries</th><th>Evidence</th><th>Warnings</th><th>Families</th></tr></thead><tbody>${rows}</tbody></table></main></body></html>`;
}

function topCounts(object, limit) {
  return Object.entries(object || {})
    .sort((left, right) => right[1] - left[1])
    .slice(0, limit)
    .map(([key, count]) => ({ key, count }));
}

function abstractEvidencePattern(text) {
  return clean(text)
    .replace(/(?:\+?\d+(?:[.,]\d+)?%?|[$€£]\s*\+?[0-9.,]+\s*(?:m|mn|mln|bn|billion|million)?|[0-9.,]+\s*(?:transactions|investments|leads|start-?ups|ventures|countries|markets|hours|facilities|clients|companies|teams|reports|models))/gi, "[METRIC]")
    .replace(/\b(?:LBO|DCF|SQL|Python|Tableau|Excel|VBA|SAP|Bloomberg|Capital IQ|FactSet|Refinitiv|Power BI|Workday|Salesforce|HubSpot|Xero|QuickBooks|Zoho)\b/gi, "[TOOL]")
    .replace(/\b(?:investment committee|senior stakeholders|senior leadership|C-Level executives|clients|customers|management|portfolio companies|board|executive team)\b/gi, "[STAKEHOLDER]")
    .replace(/\s+/g, " ")
    .trim();
}

function buildCorpusOntology(results, summary) {
  const cvs = results.filter((item) => item.type === "candidate_cv");
  const reviewReports = results.filter((item) => item.type === "review_report");
  const evidenceUnits = cvs.flatMap((item) => item.evidenceUnits || []);
  const archetypeCounts = {};
  const examplesByFamily = {};
  const reviewExamplesByTheme = {};
  const reviewPriorities = {};
  evidenceUnits.forEach((unit) => {
    const pattern = abstractEvidencePattern(unit.source);
    const words = pattern.split(/\s+/).filter(Boolean);
    if (words.length >= 7 && words.length <= 42 && !/[,:;]$/.test(pattern)) {
      archetypeCounts[pattern] = (archetypeCounts[pattern] || 0) + 1;
    }
    (unit.families || ["general"]).forEach((family) => {
      examplesByFamily[family] = examplesByFamily[family] || [];
      if (examplesByFamily[family].length < 12) {
        examplesByFamily[family].push({
          source: unit.source,
          pattern,
          weaknesses: unit.weaknesses,
          metrics: unit.metrics,
          tools: unit.tools,
        });
      }
    });
  });
  reviewReports.forEach((report) => {
    (report.themes || []).forEach((theme) => {
      reviewExamplesByTheme[theme.key] = reviewExamplesByTheme[theme.key] || {
        key: theme.key,
        label: theme.label,
        weight: theme.weight,
        rewriteAction: theme.rewriteAction,
        count: 0,
        examples: [],
      };
      reviewExamplesByTheme[theme.key].count += theme.count;
      reviewExamplesByTheme[theme.key].examples = reviewExamplesByTheme[theme.key].examples
        .concat(theme.examples || [])
        .slice(0, 10);
      reviewPriorities[theme.rewriteAction] = (reviewPriorities[theme.rewriteAction] || 0) + theme.count * theme.weight;
    });
  });
  return {
    generatedAt: new Date().toISOString(),
    phase: "phase_7_review_report_learning",
    source: {
      corpus: defaultCvDir,
      candidateCvs: summary.candidateCvs,
      reviewReports: summary.reviewReports,
      evidenceUnits: summary.evidenceUnits,
      structuredExperienceEntries: summary.structuredExperienceEntries,
      roleCompanyEntryCoverage: summary.roleCompanyEntryCoverage,
      extractionSources: summary.extractionSources,
    },
    documentAstSchema: {
      candidate: ["name", "email", "phone"],
      sections: ["header", "summary", "experience", "education", "skills", "projects", "certifications", "languages", "additional"],
      experienceEntry: ["role", "company", "location", "dates", "rawLines", "bullets", "confidence", "evidence"],
      evidenceUnit: ["source", "action", "families", "requirementMatches", "metrics", "tools", "semanticFrame", "weaknesses", "senioritySignal", "provenance", "rewritePermission"],
      rewritePlan: ["evidenceId", "family", "relevance", "action", "object", "outcome", "rewriteActions"],
      tailoredDocument: ["targetRole", "candidate", "summary", "skills", "education", "certifications", "languages", "experience", "rewrites", "requirementCoverage", "documentPlan", "warnings", "quality"],
    },
    familyCoverage: topCounts(summary.families, 12),
    weaknessCoverage: topCounts(summary.weaknesses, 20),
    reviewLearning: {
      critiqueThemes: Object.values(reviewExamplesByTheme).sort((left, right) => {
        return right.count * right.weight - left.count * left.weight;
      }),
      rewritePriorities: topCounts(reviewPriorities, 20),
      appliedPolicy: [
        "Professional review reports are used as critique signals, not as text to copy.",
        "Recurring review themes increase the priority of document structure, metrics, role alignment, ATS clarity, and achievement-led bullets.",
        "Review-learned feedback is only used to rank and explain rewrite actions; it does not authorize unsupported claims."
      ],
    },
    validationWarnings: topCounts(summary.warnings, 20),
    sentenceArchetypes: topCounts(archetypeCounts, 120),
    examplesByFamily,
    rewritePlanner: {
      targetRoleLens,
      families: rewriteFamilies,
      scoring: {
        familyRoleMatch: 35,
        keywordMatch: 6,
        metricEvidence: 6,
        toolEvidence: 4,
        senioritySignal: {
          analyst: 4,
          associate: 6,
          senior: 8,
        },
        weakOpeningPenalty: -3,
        missingOutcomePenalty: -4,
      },
    },
    rewriteSafetyPolicy: [
      "Every rewritten bullet must be traceable to one evidenceUnit.source.",
      "Metrics, tools, companies, dates, locations and named stakeholders may be preserved but not invented.",
      "Ownership language can only increase to led/owned/oversaw when the source or role context already implies ownership.",
      "Missing metrics create a prompt or a conservative wording choice; they never create fabricated numbers.",
      "Non-English evidence should be preserved unless a language-specific rewrite family exists.",
      "If candidate identity confidence is low, Emily confirms the name before application submission.",
      "If experience AST confidence is low, show original CV preview rather than a reconstructed CV.",
      "The tailored document should rank the most role-relevant evidence first instead of preserving noisy source order.",
      "Section order is planned from the evidence profile: experienced candidates lead with experience, lower-confidence or education-led profiles keep education prominent."
    ],
  };
}

function main() {
  fs.mkdirSync(outDir, { recursive: true });
  fs.readdirSync(outDir)
    .filter((file) => /\.(html|json)$/i.test(file))
    .forEach((file) => fs.unlinkSync(path.join(outDir, file)));

  const results = collectFiles().map((file, index) => {
    const extracted = extractText(file);
    if (!extracted.text) return { file: path.basename(file), failed: true, error: extracted.error };
    if (isReviewFile(file)) {
      return analyzeReviewReport(file, extracted.text);
    }
    const ast = buildAst(file, extracted.text, extracted);
    const base = `${String(index + 1).padStart(3, "0")}-${slug(ast.candidate.name)}-${slug(path.basename(file, path.extname(file)))}`;
    ast.tailoredDocument = buildTailoredDocument(ast);
    ast.tailoredHtmlFile = `${base}.tailored.html`;
    ast.jsonFile = `${base}.ast.json`;
    fs.writeFileSync(path.join(outDir, ast.tailoredHtmlFile), renderTailoredCvHtml(ast.tailoredDocument));
    fs.writeFileSync(path.join(outDir, ast.jsonFile), JSON.stringify(ast, null, 2));
    return ast;
  });
  const summary = summarise(results);
  const ontology = buildCorpusOntology(results, summary);
  fs.writeFileSync(path.join(outDir, "corpus-report.json"), JSON.stringify({ generatedAt: new Date().toISOString(), summary, results }, null, 2));
  fs.writeFileSync(path.join(outDir, "cv-intelligence-ontology.json"), JSON.stringify(ontology, null, 2));
  fs.mkdirSync(path.join(process.cwd(), "assets", "data"), { recursive: true });
  fs.writeFileSync(path.join(process.cwd(), "assets", "data", "cv-intelligence-ontology.json"), JSON.stringify(ontology, null, 2));
  fs.writeFileSync(path.join(outDir, "index.html"), renderIndex(results, summary));
  console.log(`Built CV intelligence corpus: ${summary.candidateCvs} CVs, ${summary.evidenceUnits} evidence units, ${summary.failed} failed, avg quality ${summary.averageAstQuality}`);
}

main();
