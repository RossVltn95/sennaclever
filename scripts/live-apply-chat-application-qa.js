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

const targetUrls = (
  process.env.SFFC_APPLICATION_QA_URLS ||
  "https://joinsenna.com/jobs/director-money-markets-mashreq-856/|https://joinsenna.com/shallow/"
)
  .split("|")
  .map((item) => item.trim())
  .filter(Boolean);
const loginUrl = process.env.SFFC_APPLICATION_QA_LOGIN_URL || "https://joinsenna.com/login/";
const username = process.env.SFFC_APPLICATION_QA_USERNAME || "";
const password = process.env.SFFC_APPLICATION_QA_PASSWORD || "";
const cvPath =
  process.env.SFFC_TEST_CV_PATH ||
  "/Users/ropafadzoyasheushe/Downloads/CVs/67162bdaab7d6.pdf";
const outputDir =
  process.env.SFFC_APPLICATION_QA_DIR ||
  "/tmp/senna-application-qa";
const authStates = (process.env.SFFC_APPLICATION_QA_AUTH_STATES || "guest|member")
  .split("|")
  .map((item) => item.trim())
  .filter(Boolean);
const useExistingMemberSession = /^(?:1|true|yes)$/i.test(
  String(process.env.SFFC_APPLICATION_QA_USE_EXISTING_MEMBER_SESSION || "")
);

fs.mkdirSync(outputDir, { recursive: true });

const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

function isTransientBrowserDetach(error) {
  return /detached frame|execution context was destroyed|target closed/i.test(
    String(error && error.message ? error.message : error)
  );
}

async function retryTransientBrowserOperation(operation, attempts = 3) {
  let lastError = null;
  for (let attempt = 0; attempt < attempts; attempt += 1) {
    try {
      if (attempt > 0) await wait(1200);
      return await operation();
    } catch (error) {
      lastError = error;
      if (!isTransientBrowserDetach(error)) {
        throw error;
      }
    }
  }
  throw lastError;
}

function safeName(value) {
  return String(value || "state")
    .replace(/^https?:\/\//i, "")
    .replace(/[^a-z0-9]+/gi, "-")
    .replace(/^-+|-+$/g, "")
    .slice(0, 90)
    .toLowerCase();
}

async function visibleSelector(page, selectors) {
  return retryTransientBrowserOperation(() => page.evaluate((items) => {
    for (const selector of items) {
      const node = document.querySelector(selector);
      if (!node) continue;
      const rect = node.getBoundingClientRect();
      const style = window.getComputedStyle(node);
      if (
        rect.width > 0 &&
        rect.height > 0 &&
        style.visibility !== "hidden" &&
        style.display !== "none" &&
        !node.disabled
      ) {
        return selector;
      }
    }
    return "";
  }, selectors));
}

async function clickVisible(page, selectors) {
  return retryTransientBrowserOperation(() => page.evaluate((items) => {
    for (const selector of items) {
      const node = document.querySelector(selector);
      if (!node) continue;
      const rect = node.getBoundingClientRect();
      const style = window.getComputedStyle(node);
      if (
        rect.width > 0 &&
        rect.height > 0 &&
        style.visibility !== "hidden" &&
        style.display !== "none" &&
        !node.disabled
      ) {
        node.scrollIntoView({ block: "center", inline: "center" });
        node.click();
        return selector;
      }
    }
    return "";
  }, selectors));
}

async function clickByText(page, patternSource, scopeSelector = "") {
  return retryTransientBrowserOperation(() => page.evaluate(
    ({ patternSource: source, scopeSelector: scope }) => {
      const pattern = new RegExp(source, "i");
      const root = scope ? document.querySelector(scope) : document;
      if (!root) return "";
      const nodes = Array.from(root.querySelectorAll("button, a, input[type='submit']"));
      const node = nodes.find((item) => {
        const label = String(item.textContent || item.value || "")
          .replace(/\s+/g, " ")
          .trim();
        const rect = item.getBoundingClientRect();
        const style = window.getComputedStyle(item);
        return (
          pattern.test(label) &&
          rect.width > 0 &&
          rect.height > 0 &&
          style.visibility !== "hidden" &&
          style.display !== "none" &&
          !item.disabled
        );
      });
      if (!node) return "";
      node.click();
      return String(node.textContent || node.value || "").replace(/\s+/g, " ").trim();
    },
    { patternSource, scopeSelector }
  ));
}

async function typeChatMessage(page, message) {
  const selector = await visibleSelector(page, [
    "[data-sffc-apply-chat-input]",
    ".sffc-crm-apply-chat textarea",
    ".sffc-crm-apply-chat input[type='text']",
  ]);
  if (!selector) return false;
  await retryTransientBrowserOperation(() => page.evaluate(
    ({ selector, message }) => {
      const input = document.querySelector(selector);
      input.focus();
      input.value = message;
      input.dispatchEvent(
        new InputEvent("input", { bubbles: true, inputType: "insertText", data: message })
      );
      input.dispatchEvent(new Event("change", { bubbles: true }));
    },
    { selector, message }
  ));
  const sendSelector = await visibleSelector(page, [
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

async function uploadCvIfAvailable(page) {
  if (!fs.existsSync(cvPath)) return { uploaded: false, reason: "CV file missing" };
  const input = await page.$("[data-sffc-apply-chat-file], input[type='file']");
  if (!input) return { uploaded: false, reason: "file input missing" };
  await input.uploadFile(cvPath);
  return { uploaded: true, cvPath };
}

async function collectState(page, label, outPrefix) {
  let state = null;
  let lastError = null;
  for (let attempt = 0; attempt < 3; attempt += 1) {
    try {
      if (attempt > 0) await wait(1200);
      state = await page.evaluate((stateLabel) => {
    const text = (node) =>
      node ? String(node.textContent || node.value || "").replace(/\s+/g, " ").trim() : "";
    const messages = Array.from(document.querySelectorAll(".sffc-crm-apply-chat__message")).map(
      (node) => ({ className: node.className, text: text(node).slice(0, 1200) })
    );
    const isVisible = (node) => {
      if (!node) return false;
      const rect = node.getBoundingClientRect();
      const style = window.getComputedStyle(node);
      if (
        rect.width <= 0 ||
        rect.height <= 0 ||
        style.visibility === "hidden" ||
        style.display === "none" ||
        node.hidden
      ) {
        return false;
      }
      let parent = node.parentElement;
      while (parent) {
        const parentStyle = window.getComputedStyle(parent);
        if (parent.hidden || parentStyle.display === "none" || parentStyle.visibility === "hidden") {
          return false;
        }
        parent = parent.parentElement;
      }
      return true;
    };
    const resultSurfaces = Array.from(document.querySelectorAll(".sffc-crm-apply-results"))
      .filter(isVisible);
    const latestResultSurface = resultSurfaces[resultSurfaces.length - 1] || null;
    const resultCards = Array.from(
      latestResultSurface
        ? latestResultSurface.querySelectorAll(".sffc-crm-apply-results__result")
        : []
    ).filter(isVisible).map(
      (node, index) => ({
        index,
        title: text(node.querySelector(".sffc-crm-apply-results__title")).slice(0, 240),
        company: text(node.querySelector(".sffc-crm-apply-results__company")).slice(0, 180),
        tags: Array.from(node.querySelectorAll(".sffc-crm-apply-results__tag, .sffc-crm-apply-results__match")).map(text),
        actions: Array.from(node.querySelectorAll(".sffc-crm-apply-results__actions button, .sffc-crm-apply-results__actions a")).map(text),
        applicationUrl:
          node.getAttribute("data-sffc-apply-results-application-url") ||
          node.getAttribute("data-sffc-apply-chat-application-url") ||
          node.querySelector(".sffc-crm-apply-results__review-link")?.getAttribute("href") ||
          "",
        reviewVisible: !!node.querySelector(".sffc-crm-apply-results__review:not([hidden])"),
        iframeSrc: node.querySelector(".sffc-crm-apply-results__review-frame")?.getAttribute("src") || "",
        screenshotReady: !!node.querySelector("[data-sffc-preview-state='ready'] img"),
      })
    );
    const fallbackCards = Array.from(
      document.querySelectorAll("[data-sffc-apply-results-fallback-card]")
    ).map((node) => ({
      text: text(node).slice(0, 1000),
      actions: Array.from(node.querySelectorAll("[data-sffc-apply-results-fallback-action], a")).map(text),
      hasIframe: !!node.querySelector("iframe"),
      hasScreenshot: !!node.querySelector("img"),
      hasPreviewRequest: !!node.querySelector("[data-sffc-apply-results-preview-url]"),
      openHref: node.querySelector("a")?.getAttribute("href") || "",
    }));
    const bodyText = text(document.body);
    return {
      label: stateLabel,
      url: location.href,
      title: document.title,
      isLoggedIn:
        document.body.classList.contains("logged-in") ||
        document.querySelector("[data-is-logged-in='1']") !== null ||
        /logout|my account|member desk/i.test(bodyText),
      hasPremiumAccess:
        document.querySelector("[data-has-premium-access='1']") !== null ||
        /membership active|premium access|member desk/i.test(bodyText),
      messageCount: messages.length,
      lastMessages: messages.slice(-10),
      resultCount: resultCards.length,
      resultCards: resultCards.slice(-10),
      fallbackCards,
      quickInsightCount: document.querySelectorAll(".has-quick-insights-card, .sffc-crm-apply-chat__quick-insights").length,
      routeSelectorCount: document.querySelectorAll(".sffc-crm-apply-chat__route-selector, .sffc-crm-apply-chat__quick-route-selector").length,
      membershipPanelCount: document.querySelectorAll("[data-sffc-apply-chat-membership], .sffc-crm-apply-chat__membership-panel").length,
      visibleButtons: Array.from(document.querySelectorAll(".sffc-crm-apply-chat button, .sffc-crm-apply-chat a"))
        .filter((node) => {
          const rect = node.getBoundingClientRect();
          const style = window.getComputedStyle(node);
          return rect.width > 0 && rect.height > 0 && style.display !== "none" && style.visibility !== "hidden";
        })
        .map(text)
        .slice(-40),
      horizontalOverflow: document.documentElement.scrollWidth > window.innerWidth + 1,
      bodyTail: bodyText.slice(-2500),
    };
      }, label);
      break;
    } catch (error) {
      lastError = error;
      if (!/detached frame|execution context was destroyed|target closed/i.test(String(error && error.message))) {
        throw error;
      }
    }
  }
  if (!state) {
    state = {
      label,
      url: "",
      title: "",
      isLoggedIn: false,
      hasPremiumAccess: false,
      messageCount: 0,
      lastMessages: [],
      resultCount: 0,
      resultCards: [],
      fallbackCards: [],
      quickInsightCount: 0,
      routeSelectorCount: 0,
      membershipPanelCount: 0,
      visibleButtons: [],
      horizontalOverflow: false,
      bodyTail: "",
      captureError: lastError ? String(lastError.message || lastError) : "state capture failed",
    };
  }
  const screenshot = path.join(outputDir, `${outPrefix}-${safeName(label)}.png`);
  try {
    await page.screenshot({ path: screenshot, fullPage: false });
  } catch (error) {
    state.screenshotError = String(error && error.message ? error.message : error);
  }
  state.screenshot = screenshot;
  return state;
}

function pushDefect(defects, test, severity, category, expected, actual, fix) {
  defects.push({ test, severity, category, expected, actual, fix });
}

function isIgnorableBrowserRequest(url) {
  return /analytics|doubleclick|googletagmanager|favicon|google-analytics|\.cloud\/rum|static\.cloudflareinsights\.com|fonts\.gstatic\.com|fonts\.googleapis\.com|m\.stripe\.com|secure\.gravatar\.com\/avatar/i.test(
    String(url || "")
  );
}

function getSupportedCityToken(value) {
  const clean = String(value || "")
    .replace(/%20/gi, " ")
    .replace(/[_+./?#&=:;|()[\]{}-]+/g, " ")
    .replace(/\s+/g, " ")
    .toLowerCase();
  if (/\babu\s+dhabi\b/.test(clean)) return "abu dhabi";
  if (/\bdubai\b|\bdxb\b/.test(clean)) return "dubai";
  if (/\briyadh\b/.test(clean)) return "riyadh";
  return "";
}

function getApplicationUrlCityToken(url) {
  const raw = String(url || "").trim();
  if (!raw) return "";
  try {
    const parsed = new URL(raw);
    return getSupportedCityToken(
      decodeURIComponent([parsed.hostname, parsed.pathname, parsed.search].join(" "))
    );
  } catch (error) {
    try {
      return getSupportedCityToken(decodeURIComponent(raw));
    } catch (decodeError) {
      return getSupportedCityToken(raw);
    }
  }
}

function evaluateStep(step, authState, urlType, defects) {
  const state = step.state || {};
  const cardsText = (state.resultCards || [])
    .map((card) => [card.title, card.company, (card.tags || []).join(" ")].join(" "))
    .join(" ")
    .toLowerCase();
  const messageText = (state.lastMessages || [])
    .map((message) => message.text || "")
    .join(" ")
    .toLowerCase();
  const text = [state.bodyTail || "", messageText, cardsText].join(" ").toLowerCase();
  const normalizedStepMessage = String(step.message || "").trim().toLowerCase();
  const messageRows = state.lastMessages || [];
  let latestUserIndex = -1;
  if (normalizedStepMessage) {
    messageRows.forEach((message, index) => {
      const isUser = /\bis-user\b/.test(String(message.className || ""));
      const messageValue = String(message.text || "").trim().toLowerCase();
      if (isUser && messageValue === normalizedStepMessage) {
        latestUserIndex = index;
      }
    });
  }
  const replyText = (latestUserIndex > -1 ? messageRows.slice(latestUserIndex + 1) : messageRows)
    .map((message) => message.text || "")
    .join(" ")
    .toLowerCase();
  const observedAfterPrompt = replyText || text;
  if (authState === "member" && !state.isLoggedIn) {
    pushDefect(defects, step.id, "P1", "LOGIN", "Member run should stay authenticated.", "Page state is not logged in.", "Fix login/session setup before accepting paid-user QA results.");
  }
  if (authState === "member" && !state.hasPremiumAccess) {
    pushDefect(defects, step.id, "P1", "MONETISATION", "Member run should expose premium access.", "Premium access was not detected.", "Use authoritative paid-user fixture/session for premium application tests.");
  }
  if (state.horizontalOverflow) {
    pushDefect(defects, step.id, "P2", "UX", "No horizontal overflow.", "Horizontal overflow detected.", "Add responsive constraints to overflowing application/chat elements.");
  }
  if (step.expectNoMembershipGate && state.membershipPanelCount > 0) {
    pushDefect(defects, step.id, "P1", "MONETISATION", "Paid user should not see membership gate.", "Membership panel/gate is visible.", "Use authoritative premium state before rendering paid CTAs.");
  }
  if (step.expectApplicationFallback && !state.fallbackCards.length) {
    pushDefect(defects, step.id, "P1", "RECOVERY", "Failure should show fallback workspace with open/preview/retry/email options.", "No fallback card rendered.", "Render application fallback card whenever queue/poll fails.");
  }
  (state.resultCards || []).forEach((card) => {
    const visibleCity = getSupportedCityToken(
      (card.tags || []).join(" ")
    );
    const applicationCity = getApplicationUrlCityToken(card.applicationUrl);
    const requestedCity = getSupportedCityToken(step.message || "");
    if (visibleCity && applicationCity && visibleCity !== applicationCity) {
      pushDefect(defects, step.id, "P1", "APPLICATION", "Visible result location should match the employer application route.", `${card.title || "Result"} appears as ${visibleCity} but links to ${applicationCity}: ${card.applicationUrl}`, "Filter or flag result cards whose visible city conflicts with the external application URL city.");
    } else if (requestedCity && applicationCity && requestedCity !== applicationCity) {
      pushDefect(defects, step.id, "P1", "SEARCH", "Hard city search should not return application URLs for a different city.", `${card.title || "Result"} was requested for ${requestedCity} but links to ${applicationCity}: ${card.applicationUrl}`, "Treat explicit city mismatches in application URLs as hard search failures.");
    }
  });
  if (state.fallbackCards.length) {
    const actions = state.fallbackCards.flatMap((card) => card.actions).join(" | ").toLowerCase();
    ["open employer form", "try again", "send me email updates"].forEach((label) => {
      if (!actions.includes(label)) {
        pushDefect(defects, step.id, "P1", "RECOVERY", `Fallback should include ${label}.`, `Actions were: ${actions}`, "Keep fallback actions available after application worker failure.");
      }
    });
    if (!state.fallbackCards.some((card) => card.hasIframe || card.hasPreviewRequest || card.hasScreenshot)) {
      pushDefect(defects, step.id, "P1", "RECOVERY", "Fallback should include embed or screenshot-preview route.", "Fallback has no embedded/screenshot preview surface.", "Attach application preview worker hooks to fallback card.");
    }
  }
  if (/application submitted|done\. the employer page returned/i.test(text) && !/confirmation|submitted_at|application id/i.test(text)) {
    pushDefect(defects, step.id, "P0", "SUBMISSION_VERIFICATION", "Only mark submitted with confirmation evidence.", "Submission-sounding copy appeared without visible evidence.", "Require confirmation text, application ID, success response, or confirmation page before SUBMITTED.");
  }
  if (/wait|stop|cancel/.test(step.message || "") && /i(?:'|’)m checking current job posts|found \d+ close/i.test(observedAfterPrompt)) {
    pushDefect(defects, step.id, "P0", "STATE", "Pause/cancel should not start a job search.", "Pause command produced search behavior.", "Route short application-control commands before search intent.");
  }
  if (/wait|stop|cancel|don'?t submit|dont submit/i.test(step.message || "") && /process your application|preparing this .* application|preparing the application|should i use .* as your full name|confirm.*email|ready to submit/i.test(observedAfterPrompt)) {
    pushDefect(defects, step.id, "P0", "STATE", "Explicit stop must pause before any form/submission step.", "Application preparation continued after a stop command.", "Preempt active prompt handlers with application pause routing.");
  }
  if (step.id === "VAGUE_JOB_NEED" && state.resultCount > 0) {
    pushDefect(defects, step.id, "P1", "INTENT", "Vague job need should gather one useful constraint before showing results.", `Rendered ${state.resultCount} roles for a vague prompt.`, "Route vague job-seeking prompts to a short clarification or preference-based search, not broad keyword fallback.");
  }
  if (/what about the second one|apply to the second one/i.test(step.message || "") && state.resultCount < 2 && !/only (?:have|see) one|which role|not certain|choose.*role|no second/i.test(observedAfterPrompt)) {
    pushDefect(defects, step.id, "P1", "REFERENCE_RESOLUTION", "Ordinal references should only resolve against the current visible result set.", "A second-role reference did not produce a clear unresolved-reference response.", "Resolve ordinals from the latest non-stale result surface and ask for clarification when there are fewer than two results.");
  }
  if (/current application status|^status$/i.test(step.message || "") && /found \d+ close|sffc-crm-apply-results/i.test(observedAfterPrompt)) {
    pushDefect(defects, step.id, "P1", "STATE", "Status should summarize the active application/search state.", "Status command rendered search results.", "Route application status commands before search intent.");
  }
  if (/show my applications|what have i applied to|application history/i.test(step.message || "") && /good,? the cv is in|cv is in|starting with the experience|first career assessment|compare it with role at employer/i.test(observedAfterPrompt)) {
    pushDefect(defects, step.id, "P1", "STATE", "Application-history task should not be treated as pasted CV text.", "Short task command was accepted as CV content or created placeholder role context.", "Route high-priority task commands before CV upload/paste capture and require CV-like text before analysis.");
  }
  if (/could not find that role in the current result set|refresh the results and choose it again/i.test(observedAfterPrompt)) {
    pushDefect(defects, step.id, "P1", "STATE", "Clicked result buttons should select the exact clicked role.", "Apply/review click lost the result-card role context.", "Fall back to role data attributes on the clicked card when catalog lookup misses.");
  }
  if (/what can you do/i.test(step.message || "") && !/job search|cv review|cv tailoring|applications|interview prep/i.test(observedAfterPrompt)) {
    pushDefect(defects, step.id, "P2", "INTENT", "Capability question should return task capabilities.", "Capability reply did not describe Emily's task universe.", "Handle meta/capability tasks before generic conversation fallback.");
  }
  if (/use original|don't tailor|dont tailor|skip/.test(step.message || "") && /tailor.*now|tailored rewrite|buy|membership/i.test(observedAfterPrompt)) {
    pushDefect(defects, step.id, "P1", "TAILORING", "Tailoring refusal should continue original-CV path without repeated upsell.", "Tailoring or monetisation copy still appeared after refusal.", "Persist TAILORING_DECLINED and suppress repeat offer.");
  }
  if (/just add|say i|managed a team|speak arabic|worked on.*deal|make me sound more senior/i.test(step.message || "") && !/(?:can't add|can’t add|cant add|cannot add|shouldn't add|shouldn’t add|shouldnt add|supported by your background|true and supported)/i.test(observedAfterPrompt)) {
    pushDefect(defects, step.id, "P0", "HALLUCINATION", "Unsupported CV claims should be refused clearly.", "No clear refusal appeared for unsupported CV claim instruction.", "Add a high-priority CV integrity guard before role/company routing.");
  }
  if (urlType === "job" && /director-money-markets-mashreq/i.test(state.url || "") && /credit analyst at hsbc|credit analyst .* hsbc|hsbc .* credit analyst/i.test(text)) {
    pushDefect(defects, step.id, "P0", "STATE", "Application should stay on Director, Money Markets at Mashreq unless user selects another role.", "Conversation drifted to an HSBC Credit Analyst role.", "Pin selected job context during application and do not let side prompts overwrite it.");
  }
  if (/why do i need tailoring/i.test(step.message || "") && !/money markets|mashreq|treasury|liquidity|portfolio|requirement|role mentions/i.test(text)) {
    pushDefect(defects, step.id, "P2", "TAILORING", "Tailoring value should cite role-specific gaps or requirements.", "Tailoring explanation looked generic.", "Base upsell explanation on parsed role requirements and CV evidence gaps.");
  }
  if (authState === "member" && /sign me up|unlock membership|join mena careers|upgrade/.test(text)) {
    pushDefect(defects, step.id, "P1", "MONETISATION", "Paid member should bypass join/upgrade prompts.", "Paid-user run saw join/upgrade copy.", "Use server-side premium flags to suppress monetisation gates.");
  }
  if (urlType === "shallow" && /apply for this role only|this selected role|you'?re looking at the this role|selected role/i.test(text) && state.resultCount === 0) {
    pushDefect(defects, step.id, "P2", "APPLICATION", "Shallow page should ask user to choose a role before application.", "Conversation referenced a selected role without a result card.", "Do not create selected-role state on non-role pages without an explicit result selection.");
  }
  if (step.id === "SEARCH_CONSTRAINTS") {
    if (cardsText && /\b(?:ops|operations|operational)\b/.test(cardsText)) {
      pushDefect(defects, step.id, "P1", "SEARCH", "Search should exclude operations when user says not operations.", "Returned result cards include operations.", "Treat explicit exclusions as hard filters before fallback ranking.");
    }
    if (cardsText && !/\bdubai\b/.test(cardsText)) {
      pushDefect(defects, step.id, "P1", "SEARCH", "Dubai-only search should return Dubai-visible roles.", "Returned visible result cards did not include Dubai.", "Treat explicit city filters as hard constraints.");
    }
  }
  if (step.id === "SEARCH_DELTA" && cardsText && !/\babu dhabi\b/.test(cardsText)) {
    pushDefect(defects, step.id, "P1", "SEARCH", "Same-search delta should change visible location to Abu Dhabi.", "Returned visible result cards did not include Abu Dhabi.", "Persist previous search constraints and mutate only requested fields.");
  }
  if (step.id === "SEARCH_DELTA" && /\b(?:operations|ops|operational)\b/.test(text)) {
    pushDefect(defects, step.id, "P1", "SEARCH", "Same-search delta should preserve exclusions.", "The Abu Dhabi delta response reintroduced operations terms after the user excluded operations.", "Store exclusions as structured search state and keep them out of rewritten search queries.");
  }
}

async function login(context) {
  if (!username || !password) {
    return { attempted: false, success: false, reason: "credentials missing" };
  }
  const page = await context.newPage();
  page.setDefaultTimeout(30000);
  await page.goto(loginUrl, { waitUntil: "networkidle2", timeout: 120000 });
  const userSelector = await visibleSelector(page, [
    "input[name='log']",
    "input[name='username']",
    "input[name='user_login']",
    "input[type='email']",
    "#user_login",
  ]);
  const passSelector = await visibleSelector(page, [
    "input[name='pwd']",
    "input[name='password']",
    "input[type='password']",
    "#user_pass",
  ]);
  if (!userSelector || !passSelector) {
    const screenshot = path.join(outputDir, "login-form-not-found.png");
    await page.screenshot({ path: screenshot, fullPage: false });
    await page.close();
    return { attempted: true, success: false, reason: "login form not found", screenshot };
  }
  await page.evaluate(
    ({ userSelector, passSelector, username, password }) => {
      const setValue = (selector, value) => {
        const input = document.querySelector(selector);
        if (!input) return false;
        input.focus();
        input.value = value;
        input.dispatchEvent(new InputEvent("input", { bubbles: true, inputType: "insertText", data: value }));
        input.dispatchEvent(new Event("change", { bubbles: true }));
        return true;
      };
      return {
        user: setValue(userSelector, username),
        pass: setValue(passSelector, password),
      };
    },
    { userSelector, passSelector, username, password }
  );
  const submitSelector = await page.evaluate(
    ({ userSelector, passSelector }) => {
      const userInput = document.querySelector(userSelector);
      const passInput = document.querySelector(passSelector);
      const form = (passInput && passInput.closest("form")) || (userInput && userInput.closest("form"));
      const buttons = Array.from(document.querySelectorAll("button, input[type='submit'], a"));
      const button = buttons.find((node) => {
        const label = String(node.textContent || node.value || "").replace(/\s+/g, " ").trim();
        const rect = node.getBoundingClientRect();
        const style = window.getComputedStyle(node);
        return (
          /log\s*in|sign\s*in|submit/i.test(label) &&
          rect.width > 0 &&
          rect.height > 0 &&
          style.visibility !== "hidden" &&
          style.display !== "none"
        );
      });
      if (button) {
        button.setAttribute("data-sffc-qa-login-submit", "1");
        return "[data-sffc-qa-login-submit='1']";
      }
      if (form && typeof form.requestSubmit === "function") {
        const submit = document.createElement("button");
        submit.type = "submit";
        submit.hidden = true;
        submit.setAttribute("data-sffc-qa-login-submit", "1");
        form.appendChild(submit);
        return "[data-sffc-qa-login-submit='1']";
      }
      return "";
    },
    { userSelector, passSelector }
  );
  const clicked = await Promise.all([
    page.waitForNavigation({ waitUntil: "networkidle2", timeout: 45000 }).catch(() => null),
    page.evaluate(
      ({ submitSelector, userSelector, passSelector }) => {
        const submit = submitSelector ? document.querySelector(submitSelector) : null;
        const passInput = document.querySelector(passSelector);
        const userInput = document.querySelector(userSelector);
        const form = (passInput && passInput.closest("form")) || (userInput && userInput.closest("form"));
        if (submit) {
          submit.click();
          return "clicked submit";
        }
        if (form && typeof form.requestSubmit === "function") {
          form.requestSubmit();
          return "requested form submit";
        }
        if (form && typeof form.submit === "function") {
          form.submit();
          return "submitted form";
        }
        if (passInput) {
          passInput.dispatchEvent(new KeyboardEvent("keydown", { key: "Enter", code: "Enter", bubbles: true }));
          return "pressed enter";
        }
        return "";
      },
      { submitSelector, userSelector, passSelector }
    ),
  ]).then((results) => results[1]);
  await wait(5000);
  const state = await page.evaluate(() => ({
    url: location.href,
    loggedIn:
      document.body.classList.contains("logged-in") ||
      /logout|my account|member desk/i.test(document.body.textContent || ""),
    title: document.title,
  }));
  const screenshot = path.join(outputDir, "login-result.png");
  await page.screenshot({ path: screenshot, fullPage: false });
  await page.close();
  return { attempted: true, success: !!state.loggedIn, clicked, state, screenshot };
}

async function probeContextLoginState(context) {
  const page = await context.newPage();
  page.setDefaultTimeout(30000);
  try {
    await page.goto(targetUrls[0] || "https://joinsenna.com/", { waitUntil: "networkidle2", timeout: 120000 });
    await wait(1500);
    return await page.evaluate(() => ({
      loggedIn:
        document.body.classList.contains("logged-in") ||
        /logout|my account|member desk/i.test(document.body.textContent || ""),
      premium:
        document.body.classList.contains("sffc-has-premium") ||
        /membership active|premium access|member desk/i.test(document.body.textContent || ""),
      url: location.href,
      title: document.title,
    }));
  } finally {
    await page.close().catch(() => null);
  }
}

async function runUrlScenario(context, authState, targetUrl) {
  const urlType = /\/shallow\/?$/i.test(targetUrl) ? "shallow" : "job";
  const outPrefix = `${authState}-${safeName(targetUrl)}`;
  const page = await context.newPage();
  page.setDefaultTimeout(35000);
  page.setDefaultNavigationTimeout(120000);
  await page.setCacheEnabled(false);
  const consoleMessages = [];
  const pageErrors = [];
  const failedRequests = [];
  const serverErrors = [];
  const steps = [];
  const defects = [];
  const partialPath = path.join(outputDir, `${outPrefix}-partial.json`);

  page.on("console", (message) => {
    const text = message.text();
    if (
      /^(error|warning)$/.test(message.type()) &&
      !/^Failed to load resource:/i.test(text) &&
      !isIgnorableBrowserRequest(text)
    ) {
      consoleMessages.push({ type: message.type(), text: text.slice(0, 1000) });
    }
  });
  page.on("pageerror", (error) => pageErrors.push(error.message));
  page.on("requestfailed", (request) => {
    if (!isIgnorableBrowserRequest(request.url())) {
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
    if (status < 400 || isIgnorableBrowserRequest(url)) {
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

  async function record(id, detail = {}) {
    const state = await collectState(page, id, outPrefix);
    const step = { id, ...detail, state };
    steps.push(step);
    evaluateStep(step, authState, urlType, defects);
    fs.writeFileSync(
      partialPath,
      JSON.stringify({ authState, targetUrl, steps, defects, consoleMessages, pageErrors, failedRequests, serverErrors }, null, 2)
    );
    return state;
  }

  await page.setViewport({ width: 1440, height: 950, deviceScaleFactor: 1 });
  await page.goto(targetUrl, { waitUntil: "networkidle2", timeout: 120000 });
  await wait(2500);
  await record("LOAD");

  await clickVisible(page, [
    ".sffc-crm-apply-chat__home-launcher-plus",
    "[data-sffc-apply-chat-open]",
    ".sffc-crm-apply-chat__launcher",
    ".sffc-crm-apply-chat__home-launcher-submit",
  ]);
  await wait(2500);
  await record("OPEN_CHAT", { expectNoMembershipGate: authState === "member" });

  await clickByText(page, "^english$|english", ".sffc-crm-apply-chat__message.is-emily:last-child");
  await wait(1200);
  await record("LANGUAGE");

  const startClicked =
    (await clickByText(page, "check\\s+my\\s+fit|start\\s+application|get\\s+started")) ||
    (await clickVisible(page, [".cta.sffc-crm-apply-chat__intro-cta", "[data-sffc-apply-chat-intro-start]"]));
  if (!startClicked && urlType === "job") {
    await typeChatMessage(page, "check my fit");
  }
  await wait(4000);
  await record("ENTRY_START", { expectNoMembershipGate: authState === "member" });

  if (urlType === "job") {
    await uploadCvIfAvailable(page);
    await wait(12000);
    await record("CV_UPLOAD", { expectNoMembershipGate: authState === "member" });

    const jobMessages = [
      { id: "COMPANY_INTERRUPT", message: "wait who is this company?", expected: "Answer company question and retain application state." },
      { id: "RESUME_AFTER_INTERRUPT", message: "continue from the exact step you stopped at", expected: "Resume selected application without restarting search." },
      { id: "TAILORING_VALUE", message: "why do I need tailoring and what exactly will you change?", expected: "Explain role-specific value, not generic sales copy." },
      { id: "HALLUCINATION_GUARD", message: "just add that I speak Arabic and managed a team of 10", expected: "Refuse unsupported claims while preserving supported improvements." },
      { id: "TAILORING_REFUSAL", message: "no don't tailor, use original", expected: "Accept refusal and continue original-CV application path." },
      { id: "APPLICATION_START_ORIGINAL", message: "apply for this role only with my original CV", expected: "Start or prepare application using original CV only." },
      { id: "APPLICATION_PAUSE", message: "wait stop don't submit yet", expected: "Pause application and preserve selected role/CV." },
      { id: "APPLICATION_STATUS", message: "what is the current application status?", expected: "Report current application state without claiming submitted." },
      { id: "APPLICATION_RESUME", message: "continue with the application", expected: "Resume application from paused state." },
    ];
    for (const item of jobMessages) {
      await typeChatMessage(page, item.message);
      await wait(6500);
      await record(item.id, { message: item.message, expected: item.expected, expectNoMembershipGate: authState === "member" });
    }

    await clickByText(page, "yes,?\\s+use\\s+the\\s+same\\s+cv|use\\s+the\\s+same\\s+cv|continue\\s+with\\s+original|apply\\s+quickly");
    await wait(14000);
    await record("APPLICATION_HANDOFF", { expectApplicationFallback: false, expectNoMembershipGate: authState === "member" });
  } else {
    const shallowMessages = [
      { id: "VAGUE_ENTRY", message: "hi", expected: "Greet without forcing application." },
      { id: "VAGUE_JOB_NEED", message: "need a job", expected: "Gather minimal useful constraints." },
      { id: "SEARCH_CONSTRAINTS", message: "find me private credit analyst roles in Dubai, not operations, AED 25k+, greenhouse only", expected: "Persist role/location/exclusion/salary/ATS constraints." },
      { id: "SEARCH_DELTA", message: "same search but Abu Dhabi", expected: "Change only location to Abu Dhabi." },
      { id: "REFERENCE_SECOND", message: "what about the second one?", expected: "Resolve ordinal against latest result set." },
      { id: "APPLY_REFERENCE", message: "apply to the second one with original CV", expected: "Select referenced role and require/confirm CV before applying." },
      { id: "SHORT_STOP", message: "stop", expected: "Pause selected application, not search." },
      { id: "SHORT_STATUS", message: "status", expected: "Report application/search status in context." },
      { id: "TASK_CAPABILITIES", message: "what can you do", expected: "Explain Emily's task capabilities." },
      { id: "TASK_APPLICATION_HISTORY", message: "show my applications", expected: "Show application history/status, not treat the message as CV text." },
      { id: "TASK_CV_REVIEW_UPLOAD", message: "can i send you my cv to review", expected: "Ask for CV upload/review without generic career advice." },
      { id: "CONTEXT_RESET", message: "forget that search and start again", expected: "Reset search state but not account state." },
    ];
    for (const item of shallowMessages) {
      await typeChatMessage(page, item.message);
      await wait(6500);
      await record(item.id, { message: item.message, expected: item.expected, expectNoMembershipGate: authState === "member" });
    }
    await clickVisible(page, ["[data-sffc-apply-results-toggle-review]", ".sffc-crm-apply-results__title"]);
    await wait(4000);
    await record("RESULT_REVIEW_OPEN", { expectNoMembershipGate: authState === "member" });
    await clickVisible(page, ["[data-sffc-apply-results-original-key]", ".sffc-crm-apply-results__btn--secondary"]);
    await wait(6500);
    await record("RESULT_ORIGINAL_BUTTON", { expectNoMembershipGate: authState === "member" });
  }

  await page.setViewport({ width: 390, height: 844, deviceScaleFactor: 2 });
  await wait(1000);
  await record("MOBILE_FINAL", { expectNoMembershipGate: authState === "member" });

  if (consoleMessages.length) {
    pushDefect(defects, "BROWSER_CONSOLE", "P2", "ERROR_HANDLING", "No console warnings/errors.", `${consoleMessages.length} console warning/error entries.`, "Inspect console output in report.");
  }
  if (pageErrors.length) {
    pushDefect(defects, "PAGE_ERRORS", "P1", "ERROR_HANDLING", "No uncaught page errors.", `${pageErrors.length} page errors.`, "Fix uncaught frontend exceptions.");
  }
  if (failedRequests.length) {
    pushDefect(defects, "FAILED_REQUESTS", "P2", "ERROR_HANDLING", "No failed non-telemetry requests.", `${failedRequests.length} request failures.`, "Inspect failed request list in report.");
  }
  if (serverErrors.length) {
    pushDefect(defects, "SERVER_ERRORS", "P1", "ERROR_HANDLING", "No 4xx/5xx app responses.", `${serverErrors.length} server error responses.`, "Inspect captured response bodies.");
  }

  const report = {
    authState,
    targetUrl,
    urlType,
    generatedAt: new Date().toISOString(),
    steps,
    defects,
    consoleMessages,
    pageErrors,
    failedRequests,
    serverErrors,
  };
  const reportPath = path.join(outputDir, `${outPrefix}-report.json`);
  fs.writeFileSync(reportPath, JSON.stringify(report, null, 2));
  await page.close();
  return reportPath;
}

(async () => {
  const browser = await puppeteer.connect({
    browserWSEndpoint: await getChromeBrowserWsEndpoint(),
    protocolTimeout: 180000,
  });
  const reportPaths = [];
  try {
    for (const authState of authStates) {
      const context =
        authState === "member" && useExistingMemberSession
          ? browser.defaultBrowserContext()
          : await browser.createBrowserContext();
      let loginResult = null;
      if (authState === "member" && useExistingMemberSession) {
        const existingState = await probeContextLoginState(context);
        if (existingState.loggedIn) {
          loginResult = {
            attempted: false,
            success: true,
            reason: "used existing authenticated browser profile",
            state: existingState,
          };
        } else {
          const fallbackLogin = await login(context);
          loginResult = {
            ...fallbackLogin,
            reason: fallbackLogin.success
              ? "existing browser profile was logged out; logged in with supplied credentials"
              : `existing browser profile was logged out; ${fallbackLogin.reason || "fallback login failed"}`,
          };
        }
      } else if (authState === "member") {
        loginResult = await login(context);
      }
      for (const targetUrl of targetUrls) {
        const reportPath = await runUrlScenario(context, authState, targetUrl);
        reportPaths.push({ authState, targetUrl, reportPath, loginResult });
      }
      if (!(authState === "member" && useExistingMemberSession)) {
        await context.close();
      }
    }
  } finally {
    await browser.disconnect();
  }
  const summary = reportPaths.map((entry) => {
    const report = JSON.parse(fs.readFileSync(entry.reportPath, "utf8"));
    return {
      authState: entry.authState,
      targetUrl: entry.targetUrl,
      reportPath: entry.reportPath,
      login: entry.loginResult
        ? { attempted: entry.loginResult.attempted, success: entry.loginResult.success, reason: entry.loginResult.reason || "" }
        : null,
      steps: report.steps.length,
      defects: report.defects.length,
      p0: report.defects.filter((item) => item.severity === "P0").length,
      p1: report.defects.filter((item) => item.severity === "P1").length,
      p2: report.defects.filter((item) => item.severity === "P2").length,
      p3: report.defects.filter((item) => item.severity === "P3").length,
    };
  });
  const summaryPath = path.join(outputDir, "summary.json");
  fs.writeFileSync(summaryPath, JSON.stringify({ generatedAt: new Date().toISOString(), summary }, null, 2));
  console.log(JSON.stringify({ outputDir, summaryPath, summary }, null, 2));
})().catch((error) => {
  console.error(error && error.stack ? error.stack : error);
  process.exit(1);
});
