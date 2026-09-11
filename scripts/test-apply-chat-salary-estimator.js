#!/usr/bin/env node

const fs = require("fs");
const path = require("path");
const vm = require("vm");

const repoRoot = path.resolve(__dirname, "..");

function createElement(tagName) {
  return {
    tagName: String(tagName || "div").toUpperCase(),
    dataset: {},
    style: {},
    attrs: {},
    children: [],
    hidden: false,
    classList: {
      add() {},
      remove() {},
      contains() {
        return false;
      },
      toggle() {},
    },
    appendChild(child) {
      this.children.push(child);
      return child;
    },
    insertAdjacentHTML() {},
    setAttribute(name, value) {
      this.attrs[name] = String(value);
    },
    getAttribute(name) {
      return this.attrs[name] || "";
    },
    hasAttribute(name) {
      return Object.prototype.hasOwnProperty.call(this.attrs, name);
    },
    removeAttribute(name) {
      delete this.attrs[name];
    },
    querySelector() {
      return createElement("div");
    },
    querySelectorAll() {
      return [];
    },
    addEventListener() {},
    removeEventListener() {},
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

function loadApi() {
  const root = createElement("section");
  root.setAttribute("data-role-title", "Private Equity Analyst");
  root.setAttribute("data-role-company", "Test Capital");
  root.setAttribute("data-role-location", "Dubai");
  root.setAttribute("data-role-sector", "Private Equity");
  root.setAttribute("data-role-salary", "");
  root.setAttribute("data-application-url", "#");
  root.setAttribute("data-launch-mode", "role_entry");
  root.setAttribute("data-is-logged-in", "0");
  root.querySelector = function querySelector(selector) {
    if (selector === "[data-sffc-apply-chat-salary-guides]") {
      return { textContent: "[]" };
    }
    return createElement("div");
  };

  const document = {
    readyState: "complete",
    body: createElement("body"),
    createElement,
    querySelector(selector) {
      return selector === "[data-sffc-apply-chat]" ? root : createElement("div");
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
    throw new Error("Apply chat salary test hook was not exposed.");
  }
  return root.__sffcApplyChatTest;
}

const api = loadApi();

const cases = [
  {
    label: "Dubai private equity analyst",
    role: {
      title: "Private Equity Analyst",
      company: "Gulf Capital",
      location: "Dubai, United Arab Emirates",
      sector: "Private Equity",
    },
    currency: "AED",
    min: 20000,
    max: 40000,
    source: "gulf_finance_benchmark",
  },
  {
    label: "Dubai finance manager",
    role: {
      title: "Finance Manager",
      company: "International Group",
      location: "Dubai",
      sector: "Finance & Accounting",
    },
    currency: "AED",
    min: 25000,
    max: 45000,
    source: "gulf_finance_benchmark",
  },
  {
    label: "Dubai senior finance analyst",
    role: {
      title: "Senior FP&A Analyst",
      company: "Regional HQ",
      location: "Dubai",
      sector: "Finance",
    },
    currency: "AED",
    min: 23000,
    max: 32000,
    source: "gulf_finance_benchmark",
  },
  {
    label: "Riyadh investment associate",
    role: {
      title: "Investment Associate",
      company: "Saudi investment platform",
      location: "Riyadh, Saudi Arabia",
      sector: "Investment Banking",
    },
    currency: "SAR",
    min: 30000,
    max: 50000,
    source: "gulf_finance_benchmark",
  },
  {
    label: "Riyadh finance director",
    role: {
      title: "Finance Director",
      company: "Saudi operating company",
      location: "KSA",
      sector: "Finance",
    },
    currency: "SAR",
    min: 55000,
    max: 80000,
    source: "gulf_finance_benchmark",
  },
  {
    label: "Riyadh senior accountant",
    role: {
      title: "Senior Accountant",
      company: "Saudi operating company",
      location: "Saudi Arabia",
      sector: "Accounting",
    },
    currency: "SAR",
    min: 20000,
    max: 28000,
    source: "gulf_finance_benchmark",
  },
  {
    label: "Dubai treasury director inferred from head",
    role: {
      title: "Treasury Director",
      company: "Regional bank",
      location: "UAE",
      sector: "Treasury",
    },
    currency: "AED",
    minAbove: 45000,
    maxAbove: 70000,
  },
];

let failures = 0;

cases.forEach((testCase) => {
  const result = api.estimateSalaryForRole(testCase.role);
  const errors = [];
  if (!result) {
    errors.push("no result");
  } else {
    if (testCase.currency && result.currency !== testCase.currency) {
      errors.push(`currency ${result.currency}`);
    }
    if (testCase.min && result.min !== testCase.min) {
      errors.push(`min ${result.min}`);
    }
    if (testCase.max && result.max !== testCase.max) {
      errors.push(`max ${result.max}`);
    }
    if (testCase.source && result.source !== testCase.source) {
      errors.push(`source ${result.source}`);
    }
    if (testCase.minAbove && result.min < testCase.minAbove) {
      errors.push(`min below ${testCase.minAbove}: ${result.min}`);
    }
    if (testCase.maxAbove && result.max < testCase.maxAbove) {
      errors.push(`max below ${testCase.maxAbove}: ${result.max}`);
    }
    if (!/monthly/i.test(result.display || "")) {
      errors.push(`display missing monthly: ${result.display}`);
    }
  }
  if (errors.length) {
    failures += 1;
    console.error(`FAIL ${testCase.label}: ${errors.join(", ")}`);
    console.error(result);
  } else {
    console.log(`PASS ${testCase.label}: ${result.display} (${result.source})`);
  }
});

const reply = api.buildSelectedRoleQuestionReply("what salary should I expect?", {
  title: "Private Equity Analyst",
  company: "Gulf Capital",
  location: "Dubai",
  sector: "Private Equity",
});

if (!/AED 20,000 - 40,000 monthly/.test(reply) || !/market benchmark/i.test(reply)) {
  failures += 1;
  console.error("FAIL salary reply did not include benchmark wording");
  console.error(reply);
} else {
  console.log("PASS selected-role salary reply uses Gulf benchmark");
}

const directDubaiReply = api.buildSalaryBenchmarkAnswerFromMessage(
  "what salary should a Finance Manager expect in Dubai?"
);
if (
  !/Finance Manager in Dubai/.test(directDubaiReply) ||
  !/AED 25,000 - 45,000 monthly/.test(directDubaiReply) ||
  !/upper half/.test(directDubaiReply)
) {
  failures += 1;
  console.error("FAIL direct Dubai salary question did not use benchmark model");
  console.error(directDubaiReply);
} else {
  console.log("PASS direct Dubai salary question uses benchmark model");
}

const directRiyadhReply = api.buildSalaryBenchmarkAnswerFromMessage(
  "how much should an Investment Associate earn in Riyadh?"
);
if (
  !/Investment Associate in Riyadh/.test(directRiyadhReply) ||
  !/SAR 30,000 - 50,000 monthly/.test(directRiyadhReply) ||
  !/role family and seniority/.test(directRiyadhReply)
) {
  failures += 1;
  console.error("FAIL direct Riyadh salary question did not use benchmark model");
  console.error(directRiyadhReply);
} else {
  console.log("PASS direct Riyadh salary question uses benchmark model");
}

const knowledgeReply = api.previewKnowledgeReply(
  "what salary should a Finance Manager expect in Dubai?",
  "salary_question"
);
if (
  !knowledgeReply ||
  !/AED 25,000 - 45,000 monthly/.test(knowledgeReply.answer || "") ||
  !/not just converting a UK salary/.test(knowledgeReply.answer || "")
) {
  failures += 1;
  console.error("FAIL normal Emily knowledge reply did not use salary model");
  console.error(knowledgeReply);
} else {
  console.log("PASS normal Emily knowledge reply uses salary model");
}

if (failures) {
  process.exitCode = 1;
}

process.exit(failures ? 1 : 0);
