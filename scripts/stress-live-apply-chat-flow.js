#!/usr/bin/env node

const fs = require("fs");
const path = require("path");
const { getChromeBrowserWsEndpoint } = require("./chrome-debug");

let puppeteer;
try {
  puppeteer = require("puppeteer");
} catch (error) {
  puppeteer = require("../application-worker/node_modules/puppeteer");
}

const targetUrl =
  process.argv[2] ||
  "https://joinsenna.com/jobs/business-development-operations-senior-associate-tam-development-co-853/";
const outputDir = process.env.SFFC_LIVE_CHAT_STRESS_DIR || "/tmp/senna-live-apply-chat-stress";
const loginUrl = process.env.SFFC_LIVE_CHAT_LOGIN_URL || "https://joinsenna.com/login/";
const loginEnabled = /^(?:1|true|yes)$/i.test(String(process.env.SFFC_LIVE_CHAT_LOGIN || ""));
const loginUsername = String(process.env.SFFC_LIVE_CHAT_USERNAME || "").trim();
const loginPassword = String(process.env.SFFC_LIVE_CHAT_PASSWORD || "");
const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

async function gotoLiveChatPage(page, url, options = {}) {
  try {
    await page.goto(url, {
      waitUntil: options.waitUntil || "domcontentloaded",
      timeout: options.timeout || 120000,
    });
  } catch (error) {
    const message = error && error.message ? error.message : String(error || "");
    if (!/ERR_ABORTED|Navigating frame was detached/i.test(message)) {
      throw error;
    }
    await wait(2500);
  }
}

const defaultPromptMatrix = [
  { category: "greeting_context", message: "hey emily what were we doing?" },
  { category: "market_advice", message: "how do i get a job in Dubai without already being there?" },
  { category: "career_uncertainty", message: "i dont know what i want but i need a better job" },
  { category: "job_search", message: "show me senior associate private equity jobs in Dubai" },
  { category: "negative_feedback", message: "no these are too operational and too junior" },
  { category: "location_refinement", message: "forget Riyadh, only Dubai and Abu Dhabi" },
  { category: "sector_refinement", message: "not consulting, more investment or private credit" },
  { category: "salary_refinement", message: "nothing below AED 30000 a month" },
  { category: "contextual_search", message: "find me something better than these" },
  { category: "role_reference", message: "what about the second one?" },
  { category: "cv_role_question_no_cv", message: "how does my cv match the first one?" },
  { category: "apply_by_reference", message: "apply to the first one with the original cv" },
  { category: "application_control_no_cv", message: "use original not tailored" },
  { category: "application_pause", message: "wait don't apply yet, explain why you picked it" },
  { category: "application_resume", message: "ok carry on with the application" },
  { category: "application_status", message: "what is happening with my application right now?" },
  { category: "malformed_search", message: "dubai pe assoc not ops pls" },
  { category: "messy_search_refinement", message: "ok find roles closer to investment analysis" },
  { category: "company_feedback", message: "these companies don't feel right" },
  { category: "new_goal_interrupt", message: "actually should I target Riyadh instead of Dubai?" },
];
const defaultMessagePlan = defaultPromptMatrix.map((item) => item.message);
const defaultProviderQueries = [
  "find Workday private equity jobs in Dubai",
  "show Greenhouse finance roles in Abu Dhabi",
  "any Workable senior associate jobs in Riyadh?",
  "Teamtailor investment roles only",
  "SuccessFactors banking jobs in UAE",
  "show simple form application roles",
];
function loadStressSuites() {
  const suitePath =
    process.env.SFFC_LIVE_CHAT_STRESS_SUITES_FILE ||
    path.join(__dirname, "../assets/data/apply-chat-live-stress-suites.json");
  if (!fs.existsSync(suitePath)) {
    return {};
  }
  const parsed = JSON.parse(fs.readFileSync(suitePath, "utf8"));
  return parsed && parsed.suites && typeof parsed.suites === "object" ? parsed.suites : {};
}

function normalizeStressPromptEntry(entry, fallbackCategory) {
  if (typeof entry === "string") {
    return { category: fallbackCategory || "custom", message: entry.trim() };
  }
  if (entry && typeof entry === "object") {
    return {
      category: String(entry.category || fallbackCategory || "custom").trim(),
      message: String(entry.message || "").trim(),
    };
  }
  return { category: fallbackCategory || "custom", message: "" };
}

function buildMessagePlan() {
  const suites = loadStressSuites();
  const suiteName = String(process.env.SFFC_LIVE_CHAT_STRESS_SUITE || "").trim();
  let promptEntries;
  if (process.env.SFFC_LIVE_CHAT_STRESS_MESSAGES) {
    promptEntries = process.env.SFFC_LIVE_CHAT_STRESS_MESSAGES.split("|").map((message) =>
      normalizeStressPromptEntry(message, "custom")
    );
  } else if (suiteName && Array.isArray(suites[suiteName])) {
    promptEntries = suites[suiteName].map((entry) => normalizeStressPromptEntry(entry, suiteName));
  } else {
    promptEntries = defaultPromptMatrix.map((entry) => normalizeStressPromptEntry(entry, "default"));
  }
  return {
    suiteName: suiteName && Array.isArray(suites[suiteName]) ? suiteName : process.env.SFFC_LIVE_CHAT_STRESS_MESSAGES ? "env" : "default",
    promptEntries: promptEntries.filter((entry) => entry.message),
    availableSuites: Object.keys(suites),
  };
}

const selectedMessagePlan = buildMessagePlan();
const messagePlan = selectedMessagePlan.promptEntries.map((item) => item.message);
const promptCategoryByMessage = new Map(
  defaultPromptMatrix
    .concat(selectedMessagePlan.promptEntries)
    .map((item) => [item.message.toLowerCase(), item.category])
);
const providerQueriesSource = Object.prototype.hasOwnProperty.call(
  process.env,
  "SFFC_LIVE_CHAT_PROVIDER_QUERIES"
)
  ? process.env.SFFC_LIVE_CHAT_PROVIDER_QUERIES
  : defaultProviderQueries.join("|");
const providerQueries = providerQueriesSource
  .split("|")
  .map((item) => item.trim())
  .filter(Boolean)
  .filter((item) => !/^__none__$/i.test(item));

fs.mkdirSync(outputDir, { recursive: true });

function withTimeout(promise, timeoutMs, label) {
  return Promise.race([
    promise,
    wait(timeoutMs).then(() => {
      throw new Error(`${label || "operation"} timed out after ${timeoutMs}ms`);
    }),
  ]);
}

function safeName(value) {
  return String(value || "state").replace(/[^a-z0-9]+/gi, "-").replace(/^-|-$/g, "").toLowerCase();
}

async function getVisibleSelector(page, selectors) {
  return page.evaluate((items) => {
    return (
      items.find((selector) => {
        const node = document.querySelector(selector);
        if (!node) return false;
        const rect = node.getBoundingClientRect();
        const style = window.getComputedStyle(node);
        return rect.width > 0 && rect.height > 0 && style.visibility !== "hidden" && style.display !== "none" && !node.disabled;
      }) || ""
    );
  }, selectors);
}

async function clickVisible(page, selectors) {
  return page.evaluate((items) => {
    for (const selector of items) {
      const node = document.querySelector(selector);
      if (!node) continue;
      const rect = node.getBoundingClientRect();
      const style = window.getComputedStyle(node);
      if (rect.width > 0 && rect.height > 0 && style.visibility !== "hidden" && style.display !== "none" && !node.disabled) {
        node.click();
        return selector;
      }
    }
    return "";
  }, selectors);
}

async function waitForAnyVisible(page, selectors, timeoutMs = 12000) {
  const started = Date.now();
  while (Date.now() - started < timeoutMs) {
    const selector = await getVisibleSelector(page, selectors);
    if (selector) return selector;
    await wait(350);
  }
  return "";
}

async function clickInNewestResults(page, selectors) {
  return page.evaluate((items) => {
    const surfaces = Array.from(document.querySelectorAll(".sffc-crm-apply-results--job-search"));
    const surface = surfaces.reverse().find((node) => {
      const rect = node.getBoundingClientRect();
      const style = window.getComputedStyle(node);
      return rect.width > 0 && rect.height > 0 && style.visibility !== "hidden" && style.display !== "none";
    });
    if (!surface) return "";
    for (const selector of items) {
      const candidates = Array.from(surface.querySelectorAll(selector));
      const candidate = candidates.find((node) => {
        const rect = node.getBoundingClientRect();
        const style = window.getComputedStyle(node);
        return rect.width > 0 && rect.height > 0 && style.visibility !== "hidden" && style.display !== "none" && !node.disabled;
      });
      if (candidate) {
        candidate.click();
        return selector;
      }
    }
    return "";
  }, selectors);
}

async function clickByText(page, patternText, scopeSelector) {
  return page.evaluate(
    ({ patternText: source, scopeSelector: scope }) => {
      const pattern = new RegExp(source, "i");
      const root = scope ? document.querySelector(scope) : document;
      if (!root) return "";
      const candidates = Array.from(root.querySelectorAll("button, a"));
      const item = candidates.find((node) => {
        const label = String(node.textContent || "").replace(/\s+/g, " ").trim();
        const rect = node.getBoundingClientRect();
        const style = window.getComputedStyle(node);
        return (
          pattern.test(label) &&
          rect.width > 0 &&
          rect.height > 0 &&
          style.visibility !== "hidden" &&
          style.display !== "none" &&
          !node.disabled
        );
      });
      if (!item) return "";
      item.click();
      return String(item.textContent || "").replace(/\s+/g, " ").trim();
    },
    { patternText, scopeSelector }
  );
}

async function chooseLanguageIfPrompted(page, languagePattern = "english") {
  return clickByText(
    page,
    languagePattern,
    ".sffc-crm-apply-chat__message.is-emily:last-child"
  );
}

async function isLoggedInPageState(page) {
  return page.evaluate(() => {
    const body = String(document.body ? document.body.textContent || "" : "").replace(/\s+/g, " ");
    return {
      hasLogoutLink: !!document.querySelector('a[href*="logout"], a[href*="wp-login.php?action=logout"]'),
      hasAdminBar: !!document.querySelector("#wpadminbar"),
      hasAccountText: /logout|log out|my account|account details|dashboard/i.test(body),
      hasLoginForm: !!document.querySelector('input[type="password"], input[name="log"], input[name="user_login"], input[name="username"]'),
    };
  });
}

async function loginIfRequested(page) {
  if (!loginEnabled) {
    return { attempted: false, authenticated: false };
  }
  if (!loginUsername || !loginPassword) {
    return {
      attempted: true,
      authenticated: false,
      error: "Missing login username or password environment variables.",
    };
  }
  await page.goto(loginUrl, { waitUntil: "networkidle2", timeout: 120000 });
  await wait(1500);
  const before = await isLoggedInPageState(page);
  if (before.hasLogoutLink || (before.hasAccountText && !before.hasLoginForm)) {
    return { attempted: true, authenticated: true, alreadyLoggedIn: true };
  }
  const selectors = await page.evaluate(() => {
    const visible = (node) => {
      if (!node) return false;
      const rect = node.getBoundingClientRect();
      const style = window.getComputedStyle(node);
      return rect.width > 0 && rect.height > 0 && style.visibility !== "hidden" && style.display !== "none" && !node.disabled;
    };
    const userCandidates = [
      'input[name="log"]',
      'input[name="username"]',
      'input[name="user_login"]',
      'input[name="mepr_user_login"]',
      'input[type="email"]',
      "#user_login",
      "#mepr_user_login",
    ];
    const passCandidates = [
      'input[name="pwd"]',
      'input[name="password"]',
      'input[name="user_pass"]',
      'input[name="mepr_user_pass"]',
      'input[type="password"]',
      "#user_pass",
      "#mepr_user_pass",
    ];
    const submitCandidates = [
      'button[type="submit"]',
      'input[type="submit"]',
      ".mepr-submit",
      "#wp-submit",
    ];
    return {
      userSelector: userCandidates.find((selector) => visible(document.querySelector(selector))) || "",
      passSelector: passCandidates.find((selector) => visible(document.querySelector(selector))) || "",
      submitSelector: submitCandidates.find((selector) => visible(document.querySelector(selector))) || "",
    };
  });
  if (!selectors.userSelector || !selectors.passSelector) {
    return {
      attempted: true,
      authenticated: false,
      error: "Login form inputs were not found.",
    };
  }
  await page.click(selectors.userSelector, { clickCount: 3 });
  await page.keyboard.type(loginUsername, { delay: 5 });
  await page.click(selectors.passSelector, { clickCount: 3 });
  await page.keyboard.type(loginPassword, { delay: 5 });
  if (selectors.submitSelector) {
    await page.click(selectors.submitSelector);
  } else {
    await page.keyboard.press("Enter");
  }
  try {
    await page.waitForNavigation({ waitUntil: "networkidle2", timeout: 45000 });
  } catch (error) {
    await wait(3500);
  }
  const after = await isLoggedInPageState(page);
  return {
    attempted: true,
    authenticated: !!(after.hasLogoutLink || (after.hasAccountText && !after.hasLoginForm)),
    alreadyLoggedIn: false,
    hasLoginFormAfterSubmit: after.hasLoginForm,
  };
}

async function typeChatMessage(page, message) {
  const selector = await getVisibleSelector(page, [
    "[data-sffc-apply-chat-input]",
    ".sffc-crm-apply-chat textarea",
    ".sffc-crm-apply-chat input[type='text']",
  ]);
  if (!selector) return false;
  const filled = await page.evaluate(
    ({ selector, message }) => {
      const input = document.querySelector(selector);
      if (!input) return false;
      input.focus();
      input.value = message;
      input.dispatchEvent(new InputEvent("input", { bubbles: true, inputType: "insertText", data: message }));
      input.dispatchEvent(new Event("change", { bubbles: true }));
      return true;
    },
    { selector, message }
  );
  if (!filled) return false;
  const sendSelector = await getVisibleSelector(page, [
    "[data-sffc-apply-chat-send]",
    ".sffc-crm-apply-chat__composer-send",
    ".sffc-crm-apply-chat button[type='submit']",
  ]);
  if (sendSelector) {
    await page.click(sendSelector);
  } else {
    await page.keyboard.press("Enter");
  }
  return true;
}

async function collectState(page, label) {
  for (let attempt = 0; attempt < 3; attempt += 1) {
    try {
      return await collectStateOnce(page, label);
    } catch (error) {
      const message = error && error.message ? error.message : String(error || "");
      if (!/detached Frame|Execution context was destroyed|Cannot find context/i.test(message) || attempt === 2) {
        throw error;
      }
      await wait(1200);
    }
  }
  return collectStateOnce(page, label);
}

async function collectStateOnce(page, label) {
  const state = await page.evaluate((stateLabel) => {
    const text = (node) => (node ? String(node.textContent || "").replace(/\s+/g, " ").trim() : "");
    const visibleText = (node) => (node ? String(node.innerText || node.textContent || "").replace(/\s+/g, " ").trim() : "");
    const getResultData = (node) => ({
      className: node.className,
      title: text(node.querySelector(".sffc-crm-apply-results__title")).slice(0, 240),
      provider: text(node.querySelector(".sffc-crm-apply-results__tag.is-provider")).replace(/\s+route$/i, ""),
      actions: Array.from(node.querySelectorAll(".sffc-crm-apply-results__actions button, .sffc-crm-apply-results__actions a")).map(text),
      applicationUrl:
        node.getAttribute("data-sffc-apply-results-application-url") ||
        node.getAttribute("data-sffc-apply-chat-application-url") ||
        node.querySelector("[data-sffc-apply-results-application-url]")?.getAttribute("data-sffc-apply-results-application-url") ||
        node.querySelector("[data-sffc-apply-chat-application-url]")?.getAttribute("data-sffc-apply-chat-application-url") ||
        node.querySelector(".sffc-crm-apply-results__review-link")?.getAttribute("href") ||
        "",
      hasReview: !!node.querySelector(".sffc-crm-apply-results__review"),
      reviewHidden: node.querySelector(".sffc-crm-apply-results__review")
        ? node.querySelector(".sffc-crm-apply-results__review").hasAttribute("hidden")
        : null,
      reviewFrameSrc: node.querySelector(".sffc-crm-apply-results__review-frame")?.getAttribute("src") || "",
    });
    const messageNodes = Array.from(document.querySelectorAll(".sffc-crm-apply-chat__message"));
    const messages = messageNodes.slice(-8).map((node) => ({
      className: node.className,
      text: text(node).slice(0, 900),
    }));
    const latestSurface = Array.from(document.querySelectorAll(".sffc-crm-apply-results--job-search")).pop() || null;
    const activeResults = Array.from(document.querySelectorAll(".sffc-crm-apply-results__result")).filter((node) => {
      return !node.closest("[data-sffc-apply-results-stale='1']");
    });
    const resultCards = activeResults.slice(0, 10).map(getResultData);
    const latestResultCards = latestSurface
      ? Array.from(latestSurface.querySelectorAll(".sffc-crm-apply-results__result")).map(getResultData)
      : [];
    const layoutIssues = Array.from(
      document.querySelectorAll(
        ".sffc-crm-apply-chat, .sffc-crm-apply-chat__message, .sffc-crm-apply-results, .sffc-crm-apply-results__result, .sffc-crm-apply-results__title, .sffc-crm-apply-results__actions, .sffc-crm-apply-results__btn, .sffc-crm-apply-chat__composer"
      )
    )
      .map((node) => {
        const rect = node.getBoundingClientRect();
        if (!rect.width || !rect.height) return null;
        if (rect.right > window.innerWidth + 2 || rect.left < -2) {
          return {
            className: node.className || node.tagName,
            text: text(node).slice(0, 140),
            left: Math.round(rect.left),
            right: Math.round(rect.right),
            width: Math.round(rect.width),
          };
        }
        return null;
      })
      .filter(Boolean)
      .slice(0, 20);
    const visibleButtons = Array.from(document.querySelectorAll(".sffc-crm-apply-chat button, .sffc-crm-apply-chat a"))
      .map((node) => {
        const rect = node.getBoundingClientRect();
        const style = window.getComputedStyle(node);
        return {
          text: text(node).slice(0, 160),
          className: node.className,
          href: node.getAttribute("href") || "",
          visible: rect.width > 0 && rect.height > 0 && style.visibility !== "hidden" && style.display !== "none" && !node.disabled,
        };
      })
      .filter((item) => item.visible);
    return {
      label: stateLabel,
      url: location.href,
      viewport: { width: window.innerWidth, height: window.innerHeight },
      scrollTop: (document.documentElement && document.documentElement.scrollTop) || (document.body && document.body.scrollTop) || 0,
      horizontalOverflow: document.documentElement.scrollWidth > window.innerWidth + 1,
      messageCount: messageNodes.length,
      lastMessages: messages,
      resultCount: activeResults.length,
      resultCards: resultCards.slice(0, 10),
      providers: Array.from(new Set(latestResultCards.map((item) => item.provider).filter(Boolean))),
      latestResultCount: latestResultCards.length,
      latestResultCards: latestResultCards.slice(0, 10),
      latestProviders: Array.from(new Set(latestResultCards.map((item) => item.provider).filter(Boolean))),
      latestMissingUrlCount: latestResultCards.filter((item) => !item.applicationUrl).length,
      staleResultCount: document.querySelectorAll("[data-sffc-apply-results-stale='1']").length,
      activeResultsCardCount: document.querySelectorAll(
        ".sffc-crm-apply-chat__message.has-apply-results-card:not([data-sffc-apply-results-stale='1'])"
      ).length,
      totalApplyResultsCardCount: document.querySelectorAll(".sffc-crm-apply-chat__message.has-apply-results-card").length,
      routeSelectorCount: document.querySelectorAll(".sffc-crm-apply-chat__route-selector, .sffc-crm-apply-chat__quick-route-selector").length,
      membershipTextVisible: /membership choice|already a member|showing you a membership choice/i.test(document.body.textContent || ""),
      cvReviewFirstCardCount: document.querySelectorAll(
        ".sffc-crm-apply-chat__analysis-progress-card, .sffc-crm-apply-chat__application-insight-card, .sffc-crm-apply-chat__search-strategy-card"
      ).length,
      layoutIssues,
      visibleButtons: visibleButtons.slice(-32),
      composerPlaceholder:
        document.querySelector("[data-sffc-apply-chat-input]")?.getAttribute("placeholder") ||
        document.querySelector(".sffc-crm-apply-chat textarea, .sffc-crm-apply-chat input[type='text']")?.getAttribute("placeholder") ||
        "",
      bodyTextTail: visibleText(document.body).slice(-2200),
    };
  }, label);
  const screenshot = path.join(outputDir, `${safeName(label)}.png`);
  await page.screenshot({ path: screenshot, fullPage: false });
  state.screenshot = screenshot;
  return state;
}

function providerTokenFromPrompt(value) {
  const clean = String(value || "").toLowerCase().replace(/[_-]+/g, " ");
  if (/\bworkday\b/.test(clean)) return "workday";
  if (/\bgreenhouse\b/.test(clean)) return "greenhouse";
  if (/\bworkable\b/.test(clean)) return "workable";
  if (/\bteam\s*tailor\b|\bteamtailor\b/.test(clean)) return "teamtailor";
  if (/\bsuccess\s*factors\b|\bsuccessfactors\b|\bsap\s+success\s*factors\b/.test(clean)) return "successfactors";
  if (/\bsimple\s+form\b|\bbasic\s+form\b/.test(clean)) return "simple form";
  return "";
}

function providerLabelMatchesPrompt(providerLabel, expectedProvider) {
  const label = String(providerLabel || "").toLowerCase().replace(/[_-]+/g, " ");
  const expected = String(expectedProvider || "").toLowerCase();
  if (!expected) return true;
  if (expected === "successfactors") return /success\s*factors|sap/i.test(label);
  if (expected === "teamtailor") return /team\s*tailor|teamtailor/i.test(label);
  if (expected === "simple form") return /simple\s+form|employer site|direct/i.test(label);
  return label.includes(expected);
}

function analyseWeaknesses(steps, consoleMessages, pageErrors, failedRequests) {
  const issues = [];
  const states = steps.map((step) => step.state);
  const last = states[states.length - 1] || {};
  steps.forEach((step) => {
    const state = step.state || {};
    const detail = step.detail || {};
    if (state.horizontalOverflow) issues.push({ label: state.label, issue: "Horizontal overflow detected." });
    if (state.layoutIssues && state.layoutIssues.length) issues.push({ label: state.label, issue: "Element-level layout overflow detected.", details: state.layoutIssues });
    if (state.routeSelectorCount > 0) issues.push({ label: state.label, issue: "Route selector is visible in apply chat." });
    if (state.membershipTextVisible) issues.push({ label: state.label, issue: "Membership/paying-user internal copy is visible." });
    if (/\bthe this role\b|\bSelected role\s*Senna\b/i.test(state.bodyTextTail || "")) {
      issues.push({ label: state.label, issue: "Placeholder role text is visible as if it were a real selected role." });
    }
    if (/Content Control Global Settings|Admins Are Excluded\?|Main Query Is Restricted\?/i.test(state.bodyTextTail || "")) {
      issues.push({ label: state.label, issue: "WordPress/debug restriction output is visible in the page body." });
    }
    if (state.latestMissingUrlCount > 0) {
      issues.push({ label: state.label, issue: `${state.latestMissingUrlCount} latest result card(s) missing application URL.` });
    }
    if (state.totalApplyResultsCardCount > 1 && state.staleResultCount === 0) {
      issues.push({ label: state.label, issue: "Multiple result cards remain active in chat history." });
    }
    if (detail.category === "cv_role_question_no_cv" && /CV comparison for|Match estimate:/i.test(state.bodyTextTail || "")) {
      issues.push({ label: state.label, issue: "CV-match question produced a comparison before a CV was available." });
    }
    if (
      detail.category === "application_control_no_cv" &&
      /Thank you.{0,80}look through it alongside|You've got a good base here|Candidate Name|sffc-crm-apply-chat__application-insight-card|sffc-crm-apply-chat__analysis-progress-card/i.test(
        state.bodyTextTail || ""
      )
    ) {
      issues.push({ label: state.label, issue: "Short application-control command was treated as pasted CV content." });
    }
    if (
      /(?:skip tailoring|use original|original cv|without tailoring|apply quickly)/i.test(detail.message || "") &&
      /The point of uploading the CV|doesn't mean recruiters suddenly see it|You've got a good base here|Thank you.{0,80}look through it alongside/i.test(
        state.bodyTextTail || ""
      )
    ) {
      issues.push({ label: state.label, issue: "Original-CV/apply-control wording was routed to generic CV-upload or CV-analysis copy." });
    }
    if (
      /\b(?:continue|carry on|resume|proceed)\s+(?:with\s+)?(?:the\s+)?(?:application|applying|apply)\b/i.test(detail.message || "") &&
      /Yes\. Ask it|bring us back to the application if needed/i.test(state.bodyTextTail || "")
    ) {
      issues.push({ label: state.label, issue: "Application resume command was mistaken for a question detour." });
    }
    if (
      /\b(?:continue|carry on|resume|proceed)\s+(?:from\s+)?(?:where|exactly where)\s+(?:we|you)\s+(?:stopped|left off|paused)\b/i.test(detail.message || "") &&
      /Yes\. Ask it|bring us back to the application if needed|Send the detail for this step/i.test(state.bodyTextTail || "")
    ) {
      issues.push({ label: state.label, issue: "Application resume-from-paused command was mistaken for a prompt detour." });
    }
    if (
      /\b(?:where are we|status|progress|what is happening|what's happening)\b.*\b(?:application|applying|apply)\b/i.test(detail.message || "") &&
      /If you want help on the role itself|whether the strongest evidence is visible|Tell me what you want to focus on here/i.test(
        state.bodyTextTail || ""
      )
    ) {
      issues.push({ label: state.label, issue: "Application status question was routed to generic role-help copy." });
    }
    if (
      detail.category === "messy_search_refinement" &&
      !state.latestResultCount &&
      /\bSend the role, location, or career question\b|\bTell me what you want to focus on here\b/i.test(state.bodyTextTail || "")
    ) {
      issues.push({ label: state.label, issue: "Messy search refinement fell back to generic prompt copy instead of refreshing results." });
    }
    if (
      /\b(?:show me|find|search|anything new|similar)\b/i.test(detail.message || "") &&
      /\b(?:job|jobs|role|roles|private credit|private equity|dubai|abu dhabi|riyadh)\b/i.test(detail.message || "") &&
      !state.latestResultCount &&
      /Got it\. I(?:'|’)ll update the search around that and refresh the roles|I(?:'|’)ll treat this as a market/i.test(state.bodyTextTail || "")
    ) {
      issues.push({ label: state.label, issue: "Search/refinement copy appeared without rendering current result cards." });
    }
    if (
      /\b(?:what about|show me|open|apply to)\b.*\b(?:first|second|third|last|previous|other)\b/i.test(detail.message || "") &&
      !state.latestResultCount &&
      /I found \d+ relevant opportunities|Upload your CV and I(?:'|’)ll compare it with this role/i.test(state.bodyTextTail || "")
    ) {
      issues.push({ label: state.label, issue: "Ordinal role reference used stale or hidden result memory." });
    }
    if (
      /(?:role before that|job before that|one before that|previous one|previous role|show me the role before that)/i.test(detail.message || "") &&
      /\bI(?:'|’)m checking the current job posts for that\b|\bfound \d+ close result/i.test(state.bodyTextTail || "")
    ) {
      issues.push({ label: state.label, issue: "Previous-role reference was treated as a fresh job search." });
    }
    if (
      /(?:compare those two|compare them|compare these two)/i.test(detail.message || "") &&
      /\bSend the role, location, or career question\b|\bTell me what you want to focus on here\b/i.test(state.bodyTextTail || "")
    ) {
      issues.push({ label: state.label, issue: "Two-role comparison fell back to generic prompt copy." });
    }
    if (state.error) {
      issues.push({ label: state.label, issue: "Stress harness failed to record this turn cleanly." });
    }
    if (detail.category === "application_status" && /\bTell me what you want to focus on here\b/i.test(state.bodyTextTail || "")) {
      issues.push({ label: state.label, issue: "Application status fell back to a vague focus prompt." });
    }
    if (detail.message && /show me Workday roles/i.test(detail.message) && state.lastMessages.some((message) => /upload your cv/i.test(message.text || ""))) {
      issues.push({ label: state.label, issue: "Provider search was routed into the CV-upload intro instead of job results." });
    }
    const expectedProvider = providerTokenFromPrompt(detail.message || detail.providerQuery || "");
    if (expectedProvider && state.latestResultCount && !state.latestProviders.some((provider) => providerLabelMatchesPrompt(provider, expectedProvider))) {
      issues.push({
        label: state.label,
        issue: `Provider-specific query returned results outside ${expectedProvider}.`,
        providers: state.latestProviders,
      });
    }
    if (/how do i get a job in dubai/i.test(state.bodyTextTail) && /summari[sz]e|this role only|send the detail/i.test(state.bodyTextTail)) {
      issues.push({ label: state.label, issue: "Career question may have been pulled back into workflow copy." });
    }
  });
  if (consoleMessages.length) issues.push({ label: "browser", issue: `${consoleMessages.length} console warning/error entries.` });
  if (pageErrors.length) issues.push({ label: "browser", issue: `${pageErrors.length} page errors.` });
  if (failedRequests.length) issues.push({ label: "browser", issue: `${failedRequests.length} failed non-analytics requests.` });
  if (last.resultCount && !last.providers.length) {
    issues.push({ label: last.label, issue: "Result cards rendered without provider route labels." });
  }
  return issues;
}

(async () => {
  const browser = await puppeteer.connect({
    browserWSEndpoint: await getChromeBrowserWsEndpoint(),
    protocolTimeout: 180000,
  });
  let browserContext = null;
  let page = null;
  try {
  const reusePage = /^(?:1|true|yes)$/i.test(String(process.env.SFFC_LIVE_CHAT_REUSE_PAGE || ""));
  browserContext =
    !reusePage && typeof browser.createBrowserContext === "function"
      ? await browser.createBrowserContext()
      : null;
  page = reusePage
    ? (await browser.pages()).find((item) => /joinsenna\.com\/jobs\//.test(item.url())) || (await browser.newPage())
    : browserContext
    ? await browserContext.newPage()
    : await browser.newPage();
  page.setDefaultTimeout(30000);
  page.setDefaultNavigationTimeout(120000);
  await page.setCacheEnabled(false);
  const consoleMessages = [];
  const pageErrors = [];
  const failedRequests = [];
  const serverErrors = [];
  const steps = [];
  const partialReportPath = path.join(outputDir, "partial-report.json");

  page.on("console", (message) => {
    if (/^(error|warning)$/.test(message.type())) {
      consoleMessages.push({ type: message.type(), text: message.text().slice(0, 1000) });
    }
  });
  page.on("pageerror", (error) => pageErrors.push(error.message));
  page.on("requestfailed", (request) => {
    if (!/analytics|doubleclick|googletagmanager|favicon|google-analytics/i.test(request.url())) {
      failedRequests.push({
        url: request.url().slice(0, 500),
        resourceType: request.resourceType(),
        failure: request.failure() ? request.failure().errorText : "",
      });
    }
  });
  page.on("response", async (response) => {
    const status = response.status();
    const url = response.url();
    if (
      status < 400 ||
      /analytics|doubleclick|googletagmanager|favicon|google-analytics|\.cloud\/rum/i.test(url)
    ) {
      return;
    }
    const entry = {
      status,
      url: url.slice(0, 500),
      requestMethod: response.request().method(),
      resourceType: response.request().resourceType(),
      body: "",
    };
    try {
      entry.body = (await response.text()).slice(0, 1000);
    } catch (error) {
      entry.body = `Unable to read response body: ${error.message}`;
    }
    serverErrors.push(entry);
  });

  async function record(label, detail) {
    const state = await collectState(page, label);
    steps.push({ label, detail: detail || {}, state });
    fs.writeFileSync(
      partialReportPath,
      JSON.stringify(
        {
          targetUrl,
          suiteName: selectedMessagePlan.suiteName,
          authState,
          generatedAt: new Date().toISOString(),
          steps,
          consoleMessages,
          pageErrors,
          failedRequests: failedRequests.slice(0, 40),
          serverErrors: serverErrors.slice(0, 40),
        },
        null,
        2
      )
    );
    return state;
  }

  await page.setViewport({ width: 1440, height: 950, deviceScaleFactor: 1 });
  const authState = await loginIfRequested(page);
  await gotoLiveChatPage(page, targetUrl, { waitUntil: "domcontentloaded", timeout: 120000 });
  await wait(2500);
  await record("01 initial");

  const launcherSelector = await clickVisible(page, [
    ".sffc-crm-apply-chat__home-launcher-plus",
    "[data-sffc-apply-chat-open]",
    ".sffc-crm-apply-chat__launcher",
    ".sffc-crm-apply-chat__home-launcher-submit",
  ]);
  await waitForAnyVisible(page, [
    ".cta.sffc-crm-apply-chat__intro-cta",
    "[data-sffc-apply-chat-intro-start]",
    "[data-sffc-apply-chat-input]",
  ], 14000);
  await wait(1000);
  await record("02 after launcher open", { launcherSelector });

  const languageChoice = await chooseLanguageIfPrompted(page, "english");
  if (languageChoice) {
    await wait(1200);
    await record("02b after language choice", { languageChoice });
  }

  const introSelector = await clickVisible(page, [".cta.sffc-crm-apply-chat__intro-cta", "[data-sffc-apply-chat-intro-start]"]);
  if (introSelector) {
    await waitForAnyVisible(page, [
      "[data-sffc-apply-chat-input]",
      ".sffc-crm-apply-results--job-search",
      ".sffc-crm-apply-chat__upload",
    ], 12000);
  }
  await wait(5000);
  await record("03 after get started", { introSelector });

  for (const message of messagePlan) {
    try {
      const sent = await typeChatMessage(page, message);
      await wait(6500);
      await withTimeout(
        record(`message ${message}`, {
          sent,
          message,
          category: promptCategoryByMessage.get(message.toLowerCase()) || "custom",
        }),
        30000,
        `record message "${message}"`
      );
    } catch (error) {
      steps.push({
        label: `message ${message}`,
        detail: {
          message,
          category: promptCategoryByMessage.get(message.toLowerCase()) || "custom",
          error: error && error.message ? error.message : String(error || "unknown error"),
        },
        state: { label: `message ${message}`, error: true },
      });
    }
  }

  const titleClick = await clickInNewestResults(page, [".sffc-crm-apply-results__title", "[data-sffc-apply-results-toggle-review]"]);
  await wait(3500);
  await record("after result title click", { titleClick });

  const tailoredClick = await clickInNewestResults(page, [
    ".sffc-crm-apply-results__btn--primary",
    "[data-sffc-apply-results-apply-key]",
    "[data-sffc-apply-results-apply-tailored]",
  ]);
  await wait(5500);
  await record("after apply with tailored cv", { tailoredClick });

  const compareClick = await clickByText(page, "compare\\s+to\\s+my\\s+cv|see\\s+how.*cv|improve\\s+cv");
  await wait(5500);
  await record("after compare or improve cv option", { compareClick });

  const originalClick = await clickByText(page, "continue\\s+with\\s+original|jump\\s+to\\s+application|apply\\s+quickly");
  await wait(6500);
  await record("after original or jump option", { originalClick });

  const statusSent = await typeChatMessage(page, "what is happening with my application");
  await wait(5000);
  await record("after application status question", { statusSent });

  const providerOutcomes = [];
  for (const providerQuery of providerQueries) {
    try {
      const sent = await typeChatMessage(page, providerQuery);
      await wait(6500);
      const state = await withTimeout(
        record(`provider query ${providerQuery}`, { sent, providerQuery }),
        30000,
        `record provider query "${providerQuery}"`
      );
      providerOutcomes.push({ providerQuery, providers: state.providers, resultCount: state.resultCount });
    } catch (error) {
      const message = error && error.message ? error.message : String(error || "unknown error");
      steps.push({
        label: `provider query ${providerQuery}`,
        detail: { providerQuery, error: message },
        state: { label: `provider query ${providerQuery}`, error: true },
      });
      providerOutcomes.push({ providerQuery, providers: [], resultCount: 0, error: message });
    }
  }

  await page.setViewport({ width: 390, height: 844, deviceScaleFactor: 2 });
  await wait(1000);
  await record("mobile final");

  const states = steps.map((step) => step.state);
  const report = {
    targetUrl,
    suiteName: selectedMessagePlan.suiteName,
    reusePage,
    authState,
    availableSuites: selectedMessagePlan.availableSuites,
    generatedAt: new Date().toISOString(),
    steps,
    providerOutcomes,
    consoleMessages,
    pageErrors,
    failedRequests: failedRequests.slice(0, 80),
    serverErrors: serverErrors.slice(0, 80),
    weaknesses: analyseWeaknesses(steps, consoleMessages, pageErrors, failedRequests),
  };
  const reportPath = path.join(outputDir, "report.json");
  fs.writeFileSync(reportPath, JSON.stringify(report, null, 2));
  console.log(
    JSON.stringify(
      {
        reportPath,
        suiteName: selectedMessagePlan.suiteName,
        promptCount: messagePlan.length,
        providerQueryCount: providerQueries.length,
        steps: steps.map((step) => ({
          label: step.label,
          results: step.state.resultCount,
          providers: step.state.providers,
          messages: step.state.messageCount,
          overflow: step.state.horizontalOverflow,
          routeSelectors: step.state.routeSelectorCount,
          screenshot: step.state.screenshot,
        })),
        weaknesses: report.weaknesses,
        consoleMessages: consoleMessages.length,
        pageErrors: pageErrors.length,
        failedRequests: report.failedRequests.length,
      },
      null,
      2
    )
  );
  } finally {
    if (page && !page.isClosed()) {
      await page.close().catch(() => {});
    }
    if (browserContext) {
      await browserContext.close().catch(() => {});
    }
    await browser.disconnect();
  }
})().catch((error) => {
  console.error(error && error.stack ? error.stack : error);
  process.exit(1);
});
