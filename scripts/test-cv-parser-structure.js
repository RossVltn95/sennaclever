#!/usr/bin/env node

const fs = require("fs");
const path = require("path");
const { execFileSync } = require("child_process");
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

function hasEmbeddedBoundary(text) {
  return /\.\s+[A-Z0-9À-Ý][A-Za-zÀ-ÿ0-9&().,'’/ -]{2,100}\b(?:Limited|Ltd|LLC|LLP|PLC|Inc|Corp|Corporation|Company|Co\.?|Bank|Capital|Partners|Group|Holdings|Fund|Trust|Analytics|Consulting|Securities|S\.p\.A\.|S\.R\.L\.|University|College|School|Institute|Academy)\b/i.test(
    String(text || "")
  );
}

function analyseFile(api, filePath) {
  const text = extractPdfText(filePath);
  if (!text.replace(/\f/g, "").trim()) {
    return {
      file: path.basename(filePath),
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
    (section) => String((section && section.key) || "").toLowerCase() === "education"
  );
  const failures = [];

  if (!sections.length) failures.push("no_sections");
  if (!entries.length) failures.push("no_experience_entries");
  if (bullets.length < 2) failures.push("too_few_experience_bullets");
  if (malformedBullets.length) failures.push("embedded_entry_boundary_in_bullet");
  if (hasEducationSection && !educationEntries.length) failures.push("education_not_structured");
  if (!editableFieldCount) failures.push("tailored_cv_not_editable");
  if (!reviewSuggestionCount) failures.push("tailored_cv_inline_review_missing");
  if (!highlightedFieldCount) failures.push("tailored_cv_highlights_missing");
  if (!inlineSuggestionActionCount) failures.push("tailored_cv_inline_actions_missing");
  if (!inlineRewriteSuggestionCount) failures.push("tailored_cv_inline_rewrite_comments_missing");
  if (hasMalformedEditableWrapper) failures.push("malformed_tailored_cv_editable_wrapper");
  if (editedPayloadText && !/^Edited Candidate\b/.test(editedPayloadText)) {
    failures.push("edited_tailored_cv_payload_not_used");
  }
  if (/Ryzhechkin_Vladislav_CV_2026\.pdf$/i.test(filePath)) {
    const modelCompanyText = entries
      .map((entry) => `${entry && entry.company} ${entry && entry.role}`)
      .join(" ");
    if (entries.length < 3) failures.push("ryzhechkin_expected_three_entries");
    if (bullets.length < 8) failures.push("ryzhechkin_expected_eight_bullets");
    if (!/\bPrivate Family Office\b/i.test(modelCompanyText)) {
      failures.push("ryzhechkin_private_family_office_missing");
    }
    if (!/\bS8 Capital\b/i.test(modelCompanyText)) {
      failures.push("ryzhechkin_s8_capital_missing");
    }
    if (!/\bNovus Capital\b/i.test(modelCompanyText)) {
      failures.push("ryzhechkin_novus_capital_missing");
    }
    if (model && model.documentQuality && model.documentQuality.status !== "document") {
      failures.push("ryzhechkin_expected_document_quality");
    }
  }
  if (/CV - Daniele Mondi \(4\)\.pdf$/i.test(filePath)) {
    const modelCompanyText = entries
      .map((entry) => `${entry && entry.company} ${entry && entry.role}`)
      .join(" ");
    const entriesWithDates = entries.filter((entry) => entry && entry.dates).length;
    if (entries.length < 4) failures.push("daniele_expected_four_model_entries");
    if (bullets.length < 8) failures.push("daniele_expected_eight_bullets");
    if (entriesWithDates < 4) failures.push("daniele_expected_dated_entries");
    [
      ["Mediobanca", "daniele_mediobanca_missing"],
      ["Mare Holding", "daniele_mare_holding_missing"],
      ["Tilad Investment", "daniele_tilad_missing"],
      ["XTAL Strategies", "daniele_xtal_missing"],
      ["Philmark", "daniele_philmark_missing"],
      ["Qi4M", "daniele_qi4m_missing"],
    ].forEach(([needle, failure]) => {
      if (!new RegExp(`\\b${needle}\\b`, "i").test(modelCompanyText)) {
        failures.push(failure);
      }
    });
    if (model && model.documentQuality && model.documentQuality.status !== "document") {
      failures.push("daniele_expected_document_quality");
    }
  }
  const result = {
    file: path.basename(filePath),
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
    quality: model && model.documentQuality ? model.documentQuality.status : "",
    qualityDetails: model && model.documentQuality ? model.documentQuality : null,
    warnings: model && model.validationWarnings ? model.validationWarnings : [],
    failures,
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

function main() {
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
  const skipped = results.filter((result) => result.skipped);
  const failed = results.filter((result) => result.failures.length);
  console.log(
    JSON.stringify(
      {
        cvDir,
        tested: results.length,
        skipped: skipped.length,
        passed: results.length - failed.length,
        failed: failed.length,
        failures: failed,
        sample: results.slice(0, 10),
      },
      null,
      2
    )
  );
  process.exit(failed.length ? 1 : 0);
}

main();
