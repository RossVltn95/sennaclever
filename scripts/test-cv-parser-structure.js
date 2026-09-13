#!/usr/bin/env node

const fs = require("fs");
const path = require("path");
const { execFileSync } = require("child_process");
const crypto = require("crypto");
const vm = require("vm");

const repoRoot = path.resolve(__dirname, "..");
const cvDir =
  process.env.SFFC_CV_FIXTURE_DIR ||
  process.argv[2] ||
  "/Users/ropafadzoyasheushe/Downloads/CVs";
const limit = Math.max(1, Number(process.env.SFFC_CV_FIXTURE_LIMIT || 30));
const explicitFiles = process.argv.slice(2).filter((item) => /\.pdf$/i.test(item));
const printModel = /^(?:1|true|yes)$/i.test(
  String(process.env.SFFC_CV_PRINT_MODEL || "")
);
const writeArtifacts = /^(?:1|true|yes)$/i.test(
  String(process.env.SFFC_CV_WRITE_ARTIFACTS || "")
);
const collectServiceDiagnostics = /^(?:1|true|yes)$/i.test(
  String(process.env.SFFC_CV_SERVICE_DIAGNOSTICS || "")
);
const artifactRoot = path.resolve(
  process.env.SFFC_CV_ARTIFACT_DIR ||
    path.join(repoRoot, "reports", "cv-parser-structure")
);
const liteparseEndpoint = String(process.env.SFFC_LITEPARSE_ENDPOINT || "").trim();
const liteparseToken = String(process.env.SFFC_LITEPARSE_TOKEN || "").trim();

const fixtureExpectations = [
  {
    id: "dana_baranbo",
    filePattern: /Dana Baranbo -Resume FV\.pdf$/i,
    minEntries: 3,
    minBullets: 12,
    minEducationEntries: 2,
    minEditableFields: 12,
    minReviewSuggestions: 1,
    minInlineActions: 1,
    expectedQuality: "document",
    requiredSections: ["experience", "education", "skills", "languages"],
    requiredModelText: [
      { pattern: /\bHR Operations Manager\b/i, failure: "dana_hr_operations_manager_missing" },
      { pattern: /\bSenior HR officer\b/i, failure: "dana_senior_hr_officer_missing" },
      { pattern: /\bHR officer\b/i, failure: "dana_hr_officer_missing" },
      { pattern: /\bPremi[eè]re Urgence International\b/i, failure: "dana_premiere_urgence_missing" },
      { pattern: /\bDar Al Omran\b/i, failure: "dana_dar_al_omran_missing" },
    ],
  },
  {
    id: "daniele_mondi",
    filePattern: /CV - Daniele Mondi \(4\)\.pdf$/i,
    minEntries: 4,
    minBullets: 8,
    minEducationEntries: 3,
    minEditableFields: 12,
    minReviewSuggestions: 1,
    minInlineActions: 1,
    minDatedEntries: 4,
    expectedQuality: "document",
    requiredSections: ["experience", "education"],
    requiredModelText: [
      { pattern: /\bMediobanca\b/i, failure: "daniele_mediobanca_missing" },
      { pattern: /\bMare Holding\b/i, failure: "daniele_mare_holding_missing" },
      { pattern: /\bTilad Investment\b/i, failure: "daniele_tilad_missing" },
      { pattern: /\bXTAL Strategies\b/i, failure: "daniele_xtal_missing" },
      { pattern: /\bPhilmark\b/i, failure: "daniele_philmark_missing" },
      { pattern: /\bQi4M\b/i, failure: "daniele_qi4m_missing" },
    ],
  },
  {
    id: "ryzhechkin_vladislav",
    filePattern: /Ryzhechkin_Vladislav_CV_2026\.pdf$/i,
    minEntries: 3,
    minBullets: 8,
    minEducationEntries: 2,
    minEditableFields: 12,
    minReviewSuggestions: 1,
    minInlineActions: 1,
    minDatedEntries: 3,
    expectedQuality: "document",
    requiredSections: ["experience", "education", "skills"],
    requiredModelText: [
      {
        pattern: /\bPrivate Family Office\b/i,
        failure: "ryzhechkin_private_family_office_missing",
      },
      { pattern: /\bS8 Capital\b/i, failure: "ryzhechkin_s8_capital_missing" },
      { pattern: /\bNovus Capital\b/i, failure: "ryzhechkin_novus_capital_missing" },
    ],
  },
  {
    id: "syed_mustafa_zamin",
    filePattern: /Syed Mustafa Zamin - CV\.pdf$/i,
    minEntries: 4,
    minBullets: 8,
    minEducationEntries: 2,
    minEditableFields: 12,
    minReviewSuggestions: 1,
    minInlineActions: 1,
    minDatedEntries: 4,
    expectedQuality: "document",
    requiredSections: ["experience", "education", "skills"],
    requiredModelText: [
      { pattern: /\bAskari\b/i, failure: "syed_askari_missing" },
      { pattern: /\bSenior Vice President\b/i, failure: "syed_svp_missing" },
      { pattern: /\bSenior Associate\b/i, failure: "syed_senior_associate_missing" },
      { pattern: /\bSenior Equity Analyst\b/i, failure: "syed_equity_analyst_missing" },
      { pattern: /\bTaurus Securities\b/i, failure: "syed_taurus_missing" },
    ],
  },
];

function makeElement(attrs = {}) {
  return {
    attrs: { ...attrs },
    hidden: false,
    disabled: false,
    value: "",
    textContent: "",
    innerHTML: "",
    children: [],
    style: {},
    dataset: {},
    checked: false,
    classList: {
      add() {},
      remove() {},
      contains() {
        return false;
      },
      toggle() {
        return false;
      },
    },
    getAttribute(name) {
      return this.attrs[name] || "";
    },
    setAttribute(name, value) {
      this.attrs[name] = String(value);
    },
    hasAttribute(name) {
      return Object.prototype.hasOwnProperty.call(this.attrs, name);
    },
    removeAttribute(name) {
      delete this.attrs[name];
    },
    querySelector() {
      return makeElement();
    },
    querySelectorAll() {
      return [];
    },
    addEventListener() {},
    removeEventListener() {},
    appendChild(child) {
      this.children.push(child);
      return child;
    },
    remove() {},
    closest() {
      return null;
    },
    focus() {},
    click() {},
    select() {},
    getBoundingClientRect() {
      return { width: 1, height: 1, top: 0, left: 0, right: 1, bottom: 1 };
    },
  };
}

function buildHarness() {
  const root = makeElement({ "data-sffc-apply-chat": "1" });
  const document = {
    readyState: "complete",
    body: makeElement(),
    head: makeElement(),
    documentElement: makeElement(),
    createElement() {
      return makeElement();
    },
    querySelector(selector) {
      return selector === "[data-sffc-apply-chat]" ? root : makeElement();
    },
    querySelectorAll(selector) {
      return selector === "[data-sffc-apply-chat]" ? [root] : [];
    },
    addEventListener(event, callback) {
      if (event === "DOMContentLoaded") {
        callback();
      }
    },
    removeEventListener() {},
    execCommand() {
      return true;
    },
  };
  const window = {
    document,
    navigator: {},
    location: { href: "http://localhost/" },
    sffcCrmApplyChatArticle: { enableTestHooks: true },
    addEventListener() {},
    removeEventListener() {},
    setTimeout,
    clearTimeout,
    fetch: undefined,
    URL: {
      createObjectURL() {
        return "blob:test";
      },
      revokeObjectURL() {},
    },
    matchMedia() {
      return {
        matches: false,
        addEventListener() {},
        removeEventListener() {},
      };
    },
  };
  const context = {
    window,
    document,
    navigator: window.navigator,
    console,
    setTimeout,
    clearTimeout,
    URL: window.URL,
    File: function File() {},
    Blob: function Blob() {},
    FormData: function FormData() {},
    RegExp,
    Date,
    Math,
    Array,
    Object,
    String,
    Number,
    Boolean,
    Promise,
    Error,
    Map,
    Set,
  };
  context.globalThis = context.window;
  vm.createContext(context);
  vm.runInContext(
    fs.readFileSync(
      path.join(repoRoot, "assets/js/crm/crm-apply-chat-article.js"),
      "utf8"
    ),
    context,
    { filename: "crm-apply-chat-article.js" }
  );
  if (!root.__sffcApplyChatTest) {
    throw new Error("Apply chat parser test hook was not exposed.");
  }
  return root.__sffcApplyChatTest;
}

function extractPdfText(filePath) {
  return execFileSync("pdftotext", ["-layout", filePath, "-"], {
    encoding: "utf8",
    maxBuffer: 8 * 1024 * 1024,
  });
}

function hashFile(filePath) {
  return crypto.createHash("sha256").update(fs.readFileSync(filePath)).digest("hex");
}

function safeArtifactName(value) {
  return String(value || "cv")
    .replace(/\.pdf$/i, "")
    .replace(/[^a-z0-9._-]+/gi, "-")
    .replace(/^-+|-+$/g, "")
    .slice(0, 90);
}

function writeJson(filePath, value) {
  fs.writeFileSync(filePath, `${JSON.stringify(value, null, 2)}\n`);
}

function classifyFailure(failure) {
  const clean = String(failure || "");
  if (
    /tailored_cv_inline_(?:review|actions|highlights)|tailored_cv_not_editable|malformed_tailored_cv|edited_tailored_cv/i.test(
      clean
    )
  ) {
    return "review_or_renderer";
  }
  if (/no_sections|no_extractable_pdf_text|exception/i.test(clean)) {
    return "extraction";
  }
  if (/education_not_structured|no_experience_entries|too_few_experience_bullets/i.test(clean)) {
    return "section_detection";
  }
  if (/expected_dated_entries|date/i.test(clean)) {
    return "normalization";
  }
  if (/expected_.*missing|embedded_entry_boundary/i.test(clean)) {
    return "merge_or_boundary";
  }
  if (/expected_document_quality|visual_fallback/i.test(clean)) {
    return "quality_gate";
  }
  return "unknown";
}

function summarizeFailureCategories(failures) {
  const categories = {};
  (Array.isArray(failures) ? failures : []).forEach((failure) => {
    const category = classifyFailure(failure);
    categories[category] = (categories[category] || 0) + 1;
  });
  return categories;
}

function findFixtureExpectation(filePath) {
  const fileName = path.basename(filePath || "");
  return fixtureExpectations.find((fixture) => fixture.filePattern.test(fileName)) || null;
}

function buildModelSearchText(model, entries) {
  return [
    model && model.candidateName,
    model && model.summary,
    (entries || [])
      .map((entry) =>
        [
          entry && entry.role,
          entry && entry.company,
          entry && entry.dates,
          ...((entry && entry.bullets) || []).map(
            (bullet) => (bullet && bullet.rewritten) || bullet || ""
          ),
        ].join(" ")
      )
      .join(" "),
    ((model && model.skills) || []).join(" "),
    ((model && model.experienceRaw) || []).join(" "),
    ((model && model.educationRaw) || []).join(" "),
  ]
    .filter(Boolean)
    .join(" ");
}

function applyFixtureExpectation({
  filePath,
  sections,
  model,
  entries,
  bullets,
  educationEntries,
  editableFieldCount,
  reviewSuggestionCount,
  inlineSuggestionActionCount,
  failures,
}) {
  const fixture = findFixtureExpectation(filePath);
  if (!fixture) {
    return null;
  }
  const sectionKeys = new Set(
    (sections || []).map((section) => String((section && section.key) || ""))
  );
  const modelSearchText = buildModelSearchText(model, entries);
  const datedEntries = (entries || []).filter((entry) => entry && entry.dates).length;

  if (entries.length < fixture.minEntries) {
    failures.push(`${fixture.id}_expected_${fixture.minEntries}_entries`);
  }
  if (bullets.length < fixture.minBullets) {
    failures.push(`${fixture.id}_expected_${fixture.minBullets}_bullets`);
  }
  if (educationEntries.length < fixture.minEducationEntries) {
    failures.push(`${fixture.id}_expected_${fixture.minEducationEntries}_education_entries`);
  }
  if (
    fixture.minDatedEntries &&
    datedEntries < fixture.minDatedEntries
  ) {
    failures.push(`${fixture.id}_expected_${fixture.minDatedEntries}_dated_entries`);
  }
  if (editableFieldCount < fixture.minEditableFields) {
    failures.push(`${fixture.id}_expected_editable_fields`);
  }
  if (reviewSuggestionCount < fixture.minReviewSuggestions) {
    failures.push(`${fixture.id}_expected_review_suggestions`);
  }
  if (inlineSuggestionActionCount < fixture.minInlineActions) {
    failures.push(`${fixture.id}_expected_inline_actions`);
  }
  (fixture.requiredSections || []).forEach((sectionKey) => {
    if (!sectionKeys.has(sectionKey)) {
      failures.push(`${fixture.id}_expected_${sectionKey}_section`);
    }
  });
  (fixture.requiredModelText || []).forEach((check) => {
    if (!check.pattern.test(modelSearchText)) {
      failures.push(check.failure);
    }
  });
  if (
    fixture.expectedQuality &&
    model &&
    model.documentQuality &&
    model.documentQuality.status !== fixture.expectedQuality
  ) {
    failures.push(`${fixture.id}_expected_document_quality`);
  }
  return {
    id: fixture.id,
    datedEntries,
    expected: {
      minEntries: fixture.minEntries,
      minBullets: fixture.minBullets,
      minEducationEntries: fixture.minEducationEntries,
      minDatedEntries: fixture.minDatedEntries || 0,
      requiredSections: fixture.requiredSections || [],
      expectedQuality: fixture.expectedQuality || "",
    },
  };
}

function summarizeLiteparseServiceResult(serviceResult) {
  if (!serviceResult) {
    return null;
  }
  const body = serviceResult.body || {};
  const structured = body.structured || {};
  const grammar = body.grammar || {};
  const diagnostics = body.diagnostics || structured.diagnostics || {};
  return {
    ok: !!serviceResult.ok,
    status: serviceResult.status || 0,
    parser: body.parser || "",
    service: body.service || "",
    structuredParser: structured.parser || "",
    structuredOk: !!structured.ok,
    experienceCount: Array.isArray(structured.experience)
      ? structured.experience.length
      : 0,
    educationCount: Array.isArray(structured.education)
      ? structured.education.length
      : 0,
    skillsCount: Array.isArray(structured.skills) ? structured.skills.length : 0,
    grammar: grammar.name || body.grammar || "",
    pyresumeEnabled: !!body.pyresumeEnabled,
    ocrEnabled: !!body.ocrEnabled,
    diagnostics,
    error: serviceResult.error || "",
    code: serviceResult.code || "",
  };
}

async function getLiteparseServiceResult(filePath) {
  if (!liteparseEndpoint) {
    return null;
  }
  const endpoint = liteparseEndpoint.replace(/\/+$/, "");
  const url = /\/parse$/i.test(endpoint) ? endpoint : `${endpoint}/parse`;
  const curlArgs = ["-sS", "-X", "POST"];
  if (liteparseToken) {
    curlArgs.push("-H", `Authorization: Bearer ${liteparseToken}`);
  }
  curlArgs.push("-F", `file=@${filePath}`, url);
  try {
    const text = execFileSync("curl", curlArgs, {
      encoding: "utf8",
      maxBuffer: 16 * 1024 * 1024,
    });
    let body = null;
    try {
      body = JSON.parse(text);
    } catch (error) {
      body = { ok: false, raw: text.slice(0, 4000) };
    }
    return {
      ok: !!body?.ok,
      status: body?.ok ? 200 : 0,
      url,
      body,
    };
  } catch (error) {
    return {
      ok: false,
      url,
      error: error && error.message ? error.message : String(error),
      cause:
        error && error.cause
          ? error.cause.message || String(error.cause)
          : "",
      code: error && error.cause && error.cause.code ? error.cause.code : "",
    };
  }
}

function hasEmbeddedBoundary(text) {
  return /\.\s+[A-Z0-9À-Ý][A-Za-zÀ-ÿ0-9&().,'’/ -]{2,100}\b(?:Limited|Ltd|LLC|LLP|PLC|Inc|Corp|Corporation|Company|Co\.?|Bank|Capital|Partners|Group|Holdings|Fund|Trust|Analytics|Consulting|Securities|S\.p\.A\.|S\.R\.L\.|University|College|School|Institute|Academy)\b/i.test(
    String(text || "")
  );
}

function analyseFile(api, filePath) {
  const text = extractPdfText(filePath);
  const fileHash = hashFile(filePath);
  if (!text.replace(/\f/g, "").trim()) {
    return {
      file: path.basename(filePath),
      path: filePath,
      sha256: fileHash,
      skipped: true,
      skipReason: "no_extractable_pdf_text",
      sections: [],
      sectionEntries: [],
      sectionCount: 0,
      entries: 0,
      editableFieldCount: 0,
      reviewSuggestionCount: 0,
      highlightedFieldCount: 0,
      inlineSuggestionActionCount: 0,
      inlineRewriteSuggestionCount: 0,
      hasMalformedEditableWrapper: false,
      bullets: 0,
      educationEntries: 0,
      quality: "needs_ocr",
      qualityDetails: null,
      warnings: ["no_extractable_pdf_text"],
      failures: [],
      artifactData: {
        rawText: text,
        sections: [],
        model: null,
        renderedHtml: "",
      },
    };
  }
  api.setCapturedCvText(text);
  api.setCurrentCvFileForTest(path.basename(filePath));
  api.setRoleContext({ roleTitle: "Finance Analyst", activePath: "apply_for_me" });
  const sections = api.getParsedCvSections();
  const model = api.getTailoredCvDocumentModel(
    {
      experience_title: "Finance Analyst",
      matched_keywords: ["finance", "analysis", "risk", "modelling"],
    },
    ["finance", "analysis", "risk", "modelling"]
  );
  const entries = (model && model.entries) || [];
  const educationEntries = (model && model.educationEntries) || [];
  const experienceRawCount = ((model && model.experienceRaw) || []).filter(Boolean)
    .length;
  const educationRawCount = ((model && model.educationRaw) || []).filter(Boolean)
    .length;
  const skillsRawCount = ((model && model.skillsRaw) || []).filter(Boolean).length;
  const renderedHtml =
    typeof api.renderTailoredCvDocumentHtml === "function"
      ? api.renderTailoredCvDocumentHtml(
          {
            experience_title: "Finance Analyst",
            matched_keywords: ["finance", "analysis", "risk", "modelling"],
          },
          ["finance", "analysis", "risk", "modelling"]
        )
      : "";
  const editableFieldCount = (
    renderedHtml.match(/data-sffc-tailored-cv-field=/g) || []
  ).length;
  const reviewSuggestionCount = (
    renderedHtml.match(/data-sffc-tailored-cv-review-id=/g) || []
  ).length;
  const highlightedFieldCount = (
    renderedHtml.match(/sffc-crm-apply-chat__tailored-cv-highlight/g) || []
  ).length;
  const inlineSuggestionActionCount = (
    renderedHtml.match(/sffc-crm-apply-chat__tailored-cv-suggestion-actions/g) || []
  ).length;
  const inlineRewriteSuggestionCount = (
    renderedHtml.match(/sffc-crm-apply-chat__tailored-cv-suggestion is-rewrite/g) || []
  ).length;
  const hasMalformedEditableWrapper = /class="[^"]*&gt;&lt;span class=/.test(
    renderedHtml
  );
  let editedPayloadText = "";
  if (typeof api.updateTailoredCvFieldForTest === "function") {
    api.updateTailoredCvFieldForTest("candidateName", "Edited Candidate");
    editedPayloadText =
      typeof api.getTailoredCvPayloadForTest === "function"
        ? String((api.getTailoredCvPayloadForTest() || {}).text || "")
        : "";
  }
  const bullets = entries.reduce(
    (list, entry) =>
      list.concat(
        ((entry && entry.bullets) || []).map(
          (bullet) => (bullet && bullet.rewritten) || bullet || ""
        )
      ),
    []
  );
  const malformedBullets = bullets.filter(hasEmbeddedBoundary);
  const hasEducationSection = sections.some(
    (section) =>
      /^(education|academic|qualifications|formazione|contatto_formazione|academic_background)$/i.test(
        String((section && section.key) || "")
      )
  );
  const hasExperienceSection = sections.some((section) =>
    /^(experience|experience_previous|work|employment|professional_experience|career_history)$/i.test(
      String((section && section.key) || "")
    )
  );
  const hasAnyPreservedCvBody =
    experienceRawCount ||
    educationRawCount ||
    skillsRawCount ||
    educationEntries.length ||
    ((model && model.skills) || []).length;
  const failures = [];

  if (!sections.length) failures.push("no_sections");
  if (!entries.length && !experienceRawCount && hasExperienceSection)
    failures.push("no_experience_entries");
  if (bullets.length < 2 && !experienceRawCount && hasExperienceSection)
    failures.push("too_few_experience_bullets");
  if (!hasAnyPreservedCvBody) failures.push("no_preserved_cv_body");
  if (malformedBullets.length) failures.push("embedded_entry_boundary_in_bullet");
  if (hasEducationSection && !educationEntries.length && !educationRawCount)
    failures.push("education_not_structured");
  if (!editableFieldCount) failures.push("tailored_cv_not_editable");
  if (!reviewSuggestionCount) failures.push("tailored_cv_inline_review_missing");
  if (!highlightedFieldCount) failures.push("tailored_cv_highlights_missing");
  if (!inlineSuggestionActionCount) failures.push("tailored_cv_inline_actions_missing");
  if (hasMalformedEditableWrapper) failures.push("malformed_tailored_cv_editable_wrapper");
  if (editedPayloadText && !/^Edited Candidate\b/.test(editedPayloadText)) {
    failures.push("edited_tailored_cv_payload_not_used");
  }
  const fixtureExpectation = applyFixtureExpectation({
    filePath,
    sections,
    model,
    entries,
    bullets,
    educationEntries,
    editableFieldCount,
    reviewSuggestionCount,
    inlineSuggestionActionCount,
    failures,
  });
  const result = {
    file: path.basename(filePath),
    path: filePath,
    sha256: fileHash,
    sections: sections.map((section) => section.key),
    sectionEntries: sections.map((section) => ({
      key: section.key,
      items: ((section && section.items) || []).length,
      entries: ((section && section.entries) || []).length,
      headings: ((section && section.entries) || [])
        .map((entry) => entry && entry.heading)
        .filter(Boolean)
        .slice(0, 4),
      bullets: ((section && section.entries) || [])
        .map((entry) => ((entry && entry.bullets) || []).length)
        .slice(0, 4),
    })),
    sectionCount: sections.length,
    entries: entries.length,
    editableFieldCount,
    reviewSuggestionCount,
    highlightedFieldCount,
    inlineSuggestionActionCount,
    inlineRewriteSuggestionCount,
    hasMalformedEditableWrapper,
    bullets: bullets.length,
    educationEntries: educationEntries.length,
    experienceRawCount,
    educationRawCount,
    skillsRawCount,
    quality: model && model.documentQuality ? model.documentQuality.status : "",
    qualityDetails: model && model.documentQuality ? model.documentQuality : null,
    warnings: model && model.validationWarnings ? model.validationWarnings : [],
    fixtureExpectation,
    failures,
    failureCategories: summarizeFailureCategories(failures),
  };
  result.artifactData = {
    rawText: text,
    sections,
    model,
    renderedHtml,
    editedPayloadText,
  };
  if (printModel) {
    result.parsedExperienceEntries = sections
      .filter((section) =>
        /^(experience|experience_previous)$/i.test(
          String((section && section.key) || "")
        )
      )
      .reduce(
        (list, section) => list.concat((section && section.entries) || []),
        []
      )
      .map((entry) => ({
        heading: entry && entry.heading,
        lines: ((entry && entry.lines) || []).slice(0, 6),
        bullets: ((entry && entry.bullets) || []).slice(0, 6),
      }));
    result.experienceItems = sections
      .filter((section) =>
        /^(experience|experience_previous)$/i.test(
          String((section && section.key) || "")
        )
      )
      .reduce((list, section) => list.concat((section && section.items) || []), [])
      .slice(0, 80);
    result.modelEntries = entries.map((entry) => ({
      role: entry && entry.role,
      company: entry && entry.company,
      dates: entry && entry.dates,
      bullets: ((entry && entry.bullets) || [])
        .map((bullet) => (bullet && bullet.rewritten) || bullet || "")
        .slice(0, 3),
    }));
    result.modelSkills = ((model && model.skills) || []).slice(0, 8);
    result.modelSummary = model && model.summary;
  }
  return result;
}

function stripArtifactData(result) {
  const copy = { ...result };
  delete copy.artifactData;
  return copy;
}

function writeFailureArtifacts(result, runDir, serviceResult) {
  if (!result || !result.failures || !result.failures.length) {
    return null;
  }
  const artifactName = `${safeArtifactName(result.file)}-${String(result.sha256 || "").slice(0, 10)}`;
  const dir = path.join(runDir, artifactName);
  fs.mkdirSync(dir, { recursive: true });
  const artifactData = result.artifactData || {};
  fs.writeFileSync(path.join(dir, "raw-text.txt"), artifactData.rawText || "");
  fs.writeFileSync(path.join(dir, "rendered.html"), artifactData.renderedHtml || "");
  writeJson(path.join(dir, "browser-model.json"), artifactData.model || null);
  writeJson(path.join(dir, "browser-sections.json"), artifactData.sections || []);
  writeJson(path.join(dir, "liteparse-service.json"), serviceResult || null);
  writeJson(path.join(dir, "summary.json"), {
    ...stripArtifactData(result),
    liteparseService: summarizeLiteparseServiceResult(serviceResult),
  });
  return dir;
}

async function main() {
  if (!explicitFiles.length && !fs.existsSync(cvDir)) {
    throw new Error(`CV fixture directory does not exist: ${cvDir}`);
  }
  const files = explicitFiles.length
    ? explicitFiles.map((file) => path.resolve(file))
    : fs
        .readdirSync(cvDir)
        .filter((file) => /\.pdf$/i.test(file))
        .sort()
        .slice(0, limit)
        .map((file) => path.join(cvDir, file));
  const api = buildHarness();
  const results = files.map((filePath) => {
    try {
      return analyseFile(api, filePath);
    } catch (error) {
      return {
        file: path.basename(filePath),
        failures: ["exception"],
        error: error && error.message ? error.message : String(error),
      };
    }
  });
  if (collectServiceDiagnostics && liteparseEndpoint) {
    for (const result of results) {
      if (!result.path || result.skipped || result.error) continue;
      const serviceResult = await getLiteparseServiceResult(result.path);
      result.liteparseServiceSummary = summarizeLiteparseServiceResult(serviceResult);
    }
  }
  const runId = new Date().toISOString().replace(/[:.]/g, "-");
  const runDir = path.join(artifactRoot, runId);
  const hashGroups = results.reduce((groups, result) => {
    const key = result.sha256 || `missing:${result.file}`;
    if (!groups[key]) groups[key] = [];
    groups[key].push(result.file);
    return groups;
  }, {});
  const duplicateGroups = Object.entries(hashGroups)
    .filter((entry) => entry[1].length > 1)
    .map(([sha256, files]) => ({ sha256, files }));
  if (writeArtifacts) {
    fs.mkdirSync(runDir, { recursive: true });
    for (const result of results) {
      if (!result.failures || !result.failures.length || result.error) continue;
      const serviceResult = await getLiteparseServiceResult(result.path);
      result.artifactDir = writeFailureArtifacts(result, runDir, serviceResult);
      result.liteparseServiceSummary = summarizeLiteparseServiceResult(serviceResult);
    }
  }
  const skipped = results.filter((result) => result.skipped);
  const failed = results.filter((result) => result.failures.length);
  const failureCategoryCounts = failed.reduce((counts, result) => {
    Object.keys(result.failureCategories || summarizeFailureCategories(result.failures)).forEach(
      (category) => {
        counts[category] =
          (counts[category] || 0) +
          (result.failureCategories && result.failureCategories[category]
            ? result.failureCategories[category]
            : 1);
      }
    );
    return counts;
  }, {});
  const failureReasonCounts = failed.reduce((counts, result) => {
    (result.failures || []).forEach((failure) => {
      counts[failure] = (counts[failure] || 0) + 1;
    });
    return counts;
  }, {});
  const serviceDiagnostics = results
    .filter((result) => result.liteparseServiceSummary)
    .map((result) => ({
      file: result.file,
      ...result.liteparseServiceSummary,
    }));
  const serviceDiagnosticCounts = serviceDiagnostics.reduce(
    (counts, diagnostic) => {
      counts.total += 1;
      if (diagnostic.ok) counts.ok += 1;
      if (diagnostic.structuredOk) counts.structuredOk += 1;
      if (diagnostic.parser) {
        counts.parsers[diagnostic.parser] = (counts.parsers[diagnostic.parser] || 0) + 1;
      }
      if (diagnostic.structuredParser) {
        counts.structuredParsers[diagnostic.structuredParser] =
          (counts.structuredParsers[diagnostic.structuredParser] || 0) + 1;
      }
      return counts;
    },
    { total: 0, ok: 0, structuredOk: 0, parsers: {}, structuredParsers: {} }
  );
  const consoleResults = results.map(stripArtifactData);
  const report = {
    cvDir,
    tested: results.length,
    skipped: skipped.length,
    passed: results.length - failed.length,
    failed: failed.length,
    uniqueFiles: Object.keys(hashGroups).length,
    duplicateGroups,
    artifactDir: writeArtifacts ? runDir : null,
    serviceDiagnosticsEnabled: collectServiceDiagnostics && !!liteparseEndpoint,
    serviceDiagnosticCounts,
    failureCategoryCounts,
    failureReasonCounts,
    fixtureExpectations: consoleResults
      .filter((result) => result.fixtureExpectation)
      .map((result) => ({
        file: result.file,
        fixtureExpectation: result.fixtureExpectation,
        failures: result.failures,
      })),
    serviceDiagnostics,
    failures: consoleResults.filter((result) => result.failures.length),
    sample: consoleResults.slice(0, 10),
  };
  if (writeArtifacts) {
    writeJson(path.join(runDir, "report.json"), report);
  }
  console.log(
    JSON.stringify(report, null, 2)
  );
  process.exit(failed.length ? 1 : 0);
}

main().catch((error) => {
  console.error(error && error.stack ? error.stack : error);
  process.exit(1);
});
