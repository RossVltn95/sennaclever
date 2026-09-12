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
const testQueries = (process.env.SFFC_LIVE_CHAT_QUERIES || "show me private credit roles in Dubai")
  .split("|")
  .map((item) => item.trim())
  .filter(Boolean);
const outputDir = process.env.SFFC_LIVE_CHAT_REPORT_DIR || "/tmp/senna-live-apply-chat";
const cvPath = process.env.SFFC_LIVE_CHAT_CV_PATH || "";
const preCvCareerQuestion = process.env.SFFC_LIVE_CHAT_PRE_CV_QUESTION || "how can I land interviews in Dubai?";
const salaryQuestion = process.env.SFFC_LIVE_CHAT_SALARY_QUESTION || "what salary should I expect for this kind of role in Dubai?";
const interviewQuestion = process.env.SFFC_LIVE_CHAT_INTERVIEW_QUESTION || "how should I prepare for interviews for this role?";

fs.mkdirSync(outputDir, { recursive: true });

const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

function shouldSkipBrowserConnectionError(error) {
  const message = error && error.message ? error.message : String(error || "");
  return /connect (?:EPERM|ECONNREFUSED) 127\.0\.0\.1:9222|ECONNREFUSED.*9222|EPERM.*9222/i.test(message);
}

async function getPage(browser) {
  const page = await browser.newPage();
  await page.goto(targetUrl, { waitUntil: "domcontentloaded", timeout: 90000 });
  return page;
}

async function collectState(page, label) {
  const state = await page.evaluate((stateLabel) => {
    const text = (node) => (node ? String(node.textContent || "").replace(/\s+/g, " ").trim() : "");
    const visible = (node) => {
      if (!node) return false;
      const rect = node.getBoundingClientRect();
      const style = window.getComputedStyle(node);
      return rect.width > 0 && rect.height > 0 && style.visibility !== "hidden" && style.display !== "none";
    };
    const root = document.querySelector("[data-sffc-apply-chat], .sffc-crm-apply-chat");
    const composer = document.querySelector("[data-sffc-apply-chat-composer], .sffc-crm-apply-chat__composer");
    const input = document.querySelector("[data-sffc-apply-chat-input], .sffc-crm-apply-chat textarea, .sffc-crm-apply-chat input[type='text']");
    const messagesRoot = document.querySelector("[data-sffc-apply-chat-messages], .sffc-crm-apply-chat__messages");
    const messages = Array.from(document.querySelectorAll(".sffc-crm-apply-chat__message")).map((node) => ({
      className: node.className,
      text: text(node).slice(0, 500),
    }));
    const bodyText = text(document.body);
    const welcomeText = "Hi, I’m Emily. I’ll help you search for roles, compare them against your CV, and decide what to apply for.";
    const legacyLanguageText = "Would you prefer English or Arabic";
    const tailoringProgressText = "I’m tailoring the CV for this the employer application now.";
    const resultCards = Array.from(document.querySelectorAll(".sffc-crm-apply-results__result")).map((node) => ({
      className: node.className,
      title: text(node.querySelector(".sffc-crm-apply-results__title")).slice(0, 200),
      actions: Array.from(node.querySelectorAll(".sffc-crm-apply-results__actions button, .sffc-crm-apply-results__actions a")).map(text),
      hasReview: !!node.querySelector(".sffc-crm-apply-results__review"),
      reviewHidden: node.querySelector(".sffc-crm-apply-results__review")
        ? node.querySelector(".sffc-crm-apply-results__review").hasAttribute("hidden")
        : null,
      applicationUrl:
        node.getAttribute("data-sffc-apply-results-application-url") ||
        node.querySelector("[data-sffc-apply-results-application-url]")?.getAttribute("data-sffc-apply-results-application-url") ||
        node.querySelector(".sffc-crm-apply-results__review-link")?.getAttribute("href") ||
        "",
      reviewFrameSrc:
        node.querySelector(".sffc-crm-apply-results__review-frame")?.getAttribute("src") ||
        node.querySelector(".sffc-crm-apply-results__review-frame")?.getAttribute("data-src") ||
        "",
      reviewFrameHidden:
        node.querySelector(".sffc-crm-apply-results__review-frame-wrap")?.hasAttribute("hidden") || false,
      reviewPreviewState:
        node.querySelector(".sffc-crm-apply-results__review-screenshot")?.getAttribute("data-sffc-preview-state") ||
        (node.querySelector(".sffc-crm-apply-results__review-screenshot")?.hasAttribute("hidden") ? "hidden" : ""),
      hasReviewPreviewImage: !!node.querySelector(".sffc-crm-apply-results__review-screenshot img"),
      reviewPreviewText: text(node.querySelector(".sffc-crm-apply-results__review-screenshot")).slice(0, 200),
    }));
    const rootRect = root ? root.getBoundingClientRect() : null;
    const composerRect = composer ? composer.getBoundingClientRect() : null;
    const messagesRect = messagesRoot ? messagesRoot.getBoundingClientRect() : null;
    const activeElement = document.activeElement;
    const visibleButtons = Array.from(document.querySelectorAll(".sffc-crm-apply-chat button, .sffc-crm-apply-chat a"))
      .filter(visible)
      .map((node) => text(node))
      .filter(Boolean);
    const welcomePills = Array.from(document.querySelectorAll("[data-sffc-apply-chat-welcome-pills] button, .sffc-crm-apply-chat__welcome-pills button"))
      .filter(visible)
      .map((node) => ({
        text: text(node),
        reply: node.getAttribute("data-sffc-apply-chat-reply") || "",
      }));
    const languageControl = document.querySelector(".sffc-crm-apply-chat__desk-language, [data-sffc-apply-chat-language]");
    return {
      label: stateLabel,
      url: location.href,
      title: document.title,
      viewport: { width: window.innerWidth, height: window.innerHeight },
      hasApplyChatRoot: !!root,
      rootClassName: root ? root.className : "",
      rootRect: rootRect
        ? { left: rootRect.left, top: rootRect.top, width: rootRect.width, height: rootRect.height }
        : null,
      composerRect: composerRect
        ? { left: composerRect.left, right: composerRect.right, top: composerRect.top, bottom: composerRect.bottom, width: composerRect.width, height: composerRect.height }
        : null,
      messagesRect: messagesRect
        ? { left: messagesRect.left, right: messagesRect.right, top: messagesRect.top, bottom: messagesRect.bottom, width: messagesRect.width, height: messagesRect.height }
        : null,
      documentScrollWidth: document.documentElement.scrollWidth,
      windowInnerWidth: window.innerWidth,
      horizontalOverflow: document.documentElement.scrollWidth > window.innerWidth + 1,
      messageCount: messages.length,
      messages: messages.slice(-10),
      allMessageText: messages.map((message) => message.text).join("\n"),
      welcomeCount: bodyText.split(welcomeText).length - 1,
      legacyLanguageGateCount: bodyText.split(legacyLanguageText).length - 1,
      tailoringProgressRepeatCount: bodyText.split(tailoringProgressText).length - 1,
      hasWelcomePills: welcomePills.length > 0,
      welcomePills,
      visibleButtons: visibleButtons.slice(-50),
      languageControlText: text(languageControl),
      languageControlVisible: visible(languageControl),
      hasApplyResultsCard: !!document.querySelector(".sffc-crm-apply-chat__message.has-apply-results-card"),
      hasApplyResultsList: !!document.querySelector(".sffc-crm-apply-results__list"),
      resultCount: resultCards.length,
      resultCards: resultCards.slice(0, 5),
      hasRouteSelector: !!document.querySelector(".sffc-crm-apply-chat__route-selector, .sffc-crm-apply-chat__quick-route-selector"),
      quickRouteActionCount: document.querySelectorAll(".sffc-crm-apply-chat__quick-route-actions").length,
      quickInsightsCount: document.querySelectorAll(".sffc-crm-apply-chat__quick-insights").length,
      tailoredCvCardCount: document.querySelectorAll(".sffc-crm-apply-chat__tailored-cv-document-card, .sffc-crm-apply-chat__tailored-version-card").length,
      quickScoreCardCount: document.querySelectorAll(".sffc-crm-apply-chat__quick-score-card").length,
      coverLetterCardCount: document.querySelectorAll(".sffc-crm-apply-chat__draft-review-chat-card, .sffc-crm-apply-chat__cover-letter-preview, [data-sffc-apply-chat-cover-letter]").length,
      oldRecruiterShortlistCount: (bodyText.match(/Recruiters Hiring for Your Profile|Recruiter contact available with membership/g) || []).length,
      hasManagedServicePitch: /I have an idea that could work much better|sign me up to the service|No, apply for this role only/i.test(bodyText),
      hasMembershipChoiceText: /membership choice|sign me up|service/i.test(document.body.textContent || ""),
      hasCvReviewFirstCards: !!document.querySelector(
        ".sffc-crm-apply-chat__analysis-progress-card, .sffc-crm-apply-chat__application-insight-card, .sffc-crm-apply-chat__search-strategy-card"
      ),
      composerUsable: !!input && visible(input) && !input.disabled && !input.readOnly,
      composerHasFocus: !!input && activeElement === input,
      composerAlignedWithMessages:
        !!composerRect &&
        !!messagesRect &&
        Math.abs((composerRect.left + composerRect.right) / 2 - (messagesRect.left + messagesRect.right) / 2) < 24,
      introCtaText: text(document.querySelector(".sffc-crm-apply-chat__intro-cta, [data-sffc-apply-chat-intro-start]")),
      composerPlaceholder:
        document.querySelector("[data-sffc-apply-chat-input]")?.getAttribute("placeholder") ||
        document.querySelector(".sffc-crm-apply-chat textarea, .sffc-crm-apply-chat input[type='text']")?.getAttribute("placeholder") ||
        "",
    };
  }, label);
  const screenshotPath = path.join(outputDir, `${label}.png`);
  await page.screenshot({ path: screenshotPath, fullPage: false });
  state.screenshot = screenshotPath;
  return state;
}

async function clickFirstVisible(page, selectors) {
  for (const selector of selectors) {
    const handle = await page.$(selector);
    if (!handle) continue;
    const visible = await handle.evaluate((node) => {
      const rect = node.getBoundingClientRect();
      const style = window.getComputedStyle(node);
      return rect.width > 0 && rect.height > 0 && style.visibility !== "hidden" && style.display !== "none";
    });
    if (!visible) continue;
    await handle.click();
    return selector;
  }
  return "";
}

async function typeChatMessage(page, value) {
  const selector = await page.evaluate(() => {
    const candidates = [
      "[data-sffc-apply-chat-input]",
      ".sffc-crm-apply-chat textarea",
      ".sffc-crm-apply-chat input[type='text']",
    ];
    return candidates.find((candidate) => {
      const node = document.querySelector(candidate);
      if (!node) return false;
      const rect = node.getBoundingClientRect();
      return rect.width > 0 && rect.height > 0 && !node.disabled;
    }) || "";
  });
  if (!selector) return false;
  const filled = await page.evaluate(
    ({ selector: inputSelector, value: inputValue }) => {
      const node = document.querySelector(inputSelector);
      if (!node) return false;
      node.focus();
      node.value = inputValue;
      node.dispatchEvent(new InputEvent("input", { bubbles: true, inputType: "insertText", data: inputValue }));
      node.dispatchEvent(new Event("change", { bubbles: true }));
      return true;
    },
    { selector, value }
  );
  if (!filled) return false;
  const sendSelector = await page.evaluate(() => {
    const candidates = [
      "[data-sffc-apply-chat-send]",
      ".sffc-crm-apply-chat__composer-send",
      ".sffc-crm-apply-chat button[type='submit']",
    ];
    return candidates.find((candidate) => {
      const node = document.querySelector(candidate);
      if (!node) return false;
      const rect = node.getBoundingClientRect();
      return rect.width > 0 && rect.height > 0 && !node.disabled;
    }) || "";
  });
  if (sendSelector) {
    await page.click(sendSelector);
  } else {
    await page.keyboard.press("Enter");
  }
  return true;
}

async function clickNewestButtonByText(page, patternText) {
  return page.evaluate((source) => {
    const pattern = new RegExp(source, "i");
    const visible = (node) => {
      if (!node) return false;
      const rect = node.getBoundingClientRect();
      const style = window.getComputedStyle(node);
      return rect.width > 0 && rect.height > 0 && style.visibility !== "hidden" && style.display !== "none" && !node.disabled;
    };
    const buttons = Array.from(document.querySelectorAll(".sffc-crm-apply-chat button, .sffc-crm-apply-chat a")).filter(visible);
    const button = buttons.reverse().find((node) => pattern.test(String(node.textContent || "").replace(/\s+/g, " ").trim()));
    if (!button) return "";
    button.click();
    return String(button.textContent || "").replace(/\s+/g, " ").trim();
  }, patternText);
}

async function clickWelcomeSearchPill(page) {
  return page.evaluate(() => {
    const visible = (node) => {
      if (!node) return false;
      const rect = node.getBoundingClientRect();
      const style = window.getComputedStyle(node);
      return rect.width > 0 && rect.height > 0 && style.visibility !== "hidden" && style.display !== "none" && !node.disabled;
    };
    const pills = Array.from(document.querySelectorAll("[data-sffc-apply-chat-welcome-pills] button, .sffc-crm-apply-chat__welcome-pills button")).filter(visible);
    const searchPill = pills.find((node) => /dubai|saudi|riyadh|jobs|roles|market/i.test(node.textContent || node.getAttribute("data-sffc-apply-chat-reply") || "")) || pills[0];
    if (!searchPill) return "";
    searchPill.click();
    return String(searchPill.textContent || "").replace(/\s+/g, " ").trim();
  });
}

async function uploadCv(page) {
  if (!cvPath || !fs.existsSync(cvPath)) {
    return { attempted: false, uploaded: false, reason: cvPath ? "CV path does not exist." : "Set SFFC_LIVE_CHAT_CV_PATH to enable upload." };
  }
  const handle = await page.$("[data-sffc-apply-chat-file], .sffc-crm-apply-chat input[type='file'], input[type='file']");
  if (!handle) {
    return { attempted: true, uploaded: false, reason: "No visible or hidden file input found." };
  }
  await handle.uploadFile(cvPath);
  return { attempted: true, uploaded: true, path: cvPath };
}

function buildManualQaFindings(report) {
  const states = report.states || [];
  const latest = states[states.length - 1] || {};
  const text = states.map((state) => state.allMessageText || "").join("\n");
  const findings = [];
  const pass = (id, ok, detail = "") => {
    findings.push({ id, ok: !!ok, detail });
  };
  const stateByLabel = (pattern) => states.find((state) => pattern.test(state.label || "")) || {};
  pass("cold_open_chat", !!stateByLabel(/01-initial/).hasApplyChatRoot, "Apply chat root exists on cold open.");
  pass(
    "welcome_and_trending_pills",
    states.some((state) => state.welcomeCount === 1 && state.hasWelcomePills),
    "Welcome should render once with compact trending pills."
  );
  pass(
    "career_question_before_cv",
    !!report.sentPreCvCareerQuestion && /interview|prepare|story|evidence|claims|criteria/i.test(text),
    "Career question should be answered before CV upload."
  );
  pass("search_pill_or_query", !!report.clickedWelcomePill || (report.typedQueries || []).some((item) => item.sent), "Search should be triggered by a pill or query.");
  pass("upload_cv", !cvPath || !!(report.uploadCv && report.uploadCv.uploaded), cvPath ? "CV upload attempted." : "Skipped because SFFC_LIVE_CHAT_CV_PATH was not set.");
  pass(
    "single_cv_receipt",
    (text.match(/Thanks, (?:I’ve got it|your CV is through|I have the CV)/gi) || []).length <= 1,
    "CV receipt/progress copy should not duplicate."
  );
  pass("tailored_cv_card", !cvPath || states.some((state) => state.tailoredCvCardCount > 0), "Tailored CV card should appear after CV processing.");
  pass("choose_tailored_cv", !!report.clickedUseTailored || !cvPath, "Use Tailored CV option should be selectable.");
  pass("cover_letter_preview", !cvPath || states.some((state) => state.coverLetterCardCount > 0), "Cover letter preview should appear after tailored CV choice.");
  pass("choose_cover_letter_option", !!report.clickedCoverLetterOption || !cvPath, "Cover-letter option should be selectable.");
  pass("open_employer_review", !!report.clickedResultTitle || !!report.clickedEmployerReview, "A result/review should be openable.");
  pass(
    "blocked_embed_fallback",
    states.some((state) => state.resultCards.some((card) => card.reviewPreviewState || card.hasReviewPreviewImage || /open employer form|refresh preview|try live embed/i.test(card.reviewPreviewText || ""))),
    "Blocked embed fallback should expose preview/link controls when applicable."
  );
  pass("salary_question_mid_flow", !!report.sentSalaryQuestion && /salary|compensation|aed|sar|range/i.test(text), "Salary question should receive a salary-aware answer.");
  pass("interview_question_mid_flow", !!report.sentInterviewQuestion && /interview|prepare|story|evidence|criteria/i.test(text), "Interview question should receive an interview-aware answer.");
  pass("resume_pending_task", !/Yes\. Ask it|bring us back to the application if needed/i.test(text), "Side questions should not dump old resume prompts.");
  pass("no_old_recruiter_shortlist", !states.some((state) => state.oldRecruiterShortlistCount > 0), "Old recruiter shortlist surface should not appear in apply flow.");
  pass("no_duplicate_old_intro", !states.some((state) => state.welcomeCount > 1 || state.legacyLanguageGateCount > 0), "No duplicate welcome or old bilingual language gate.");
  pass("composer_usable", states.every((state) => state.composerUsable !== false), "Composer input should remain visible, enabled and focusable.");
  pass("no_old_route_selector", !states.some((state) => state.hasRouteSelector || state.quickRouteActionCount > 0 || state.hasManagedServicePitch), "Old quick route/service CTAs must stay removed.");
  pass("no_quick_insights", !states.some((state) => state.quickInsightsCount > 0), "Quick insights card should be replaced by tailored CV card.");
  pass("no_tailing_progress_loop", !states.some((state) => state.tailoringProgressRepeatCount > 1), "Tailoring progress message must not loop.");
  pass("layout_contract", states.every((state) => !state.horizontalOverflow && state.composerAlignedWithMessages !== false), "No horizontal overflow and composer/messages remain aligned.");
  return findings;
}

async function clickButtonByText(page, patternText) {
  return page.evaluate((source) => {
    const pattern = new RegExp(source, "i");
    const buttons = Array.from(document.querySelectorAll("button, a"));
    const button = buttons.find((node) => {
      const label = String(node.textContent || "").replace(/\s+/g, " ").trim();
      if (!pattern.test(label)) return false;
      const rect = node.getBoundingClientRect();
      const style = window.getComputedStyle(node);
      return rect.width > 0 && rect.height > 0 && style.visibility !== "hidden" && style.display !== "none";
    });
    if (!button) return "";
    button.click();
    return String(button.textContent || "").replace(/\s+/g, " ").trim();
  }, patternText);
}

async function waitForVisibleResultAfterSearch(page) {
  try {
    await page.waitForFunction(
      () => {
        const cards = Array.from(
          document.querySelectorAll(".sffc-crm-apply-results__result")
        );
        return cards.some((node) => {
          const rect = node.getBoundingClientRect();
          const style = window.getComputedStyle(node);
          return (
            rect.width > 0 &&
            rect.height > 0 &&
            style.visibility !== "hidden" &&
            style.display !== "none"
          );
        });
      },
      { timeout: 18000 }
    );
  } catch (error) {}
}

(async () => {
  const consoleMessages = [];
  const pageErrors = [];
  const failedRequests = [];
  let browser;
  try {
    browser = await puppeteer.connect({ browserWSEndpoint: await getChromeBrowserWsEndpoint() });
  } catch (error) {
    if (shouldSkipBrowserConnectionError(error)) {
      console.log("SKIP live apply-chat browser QA: Chrome remote debugging is not available.");
      return;
    }
    throw error;
  }
  const page = await getPage(browser);

  page.on("console", (message) => {
    const type = message.type();
    if (type === "error" || type === "warning") {
      consoleMessages.push({ type, text: message.text().slice(0, 1000) });
    }
  });
  page.on("pageerror", (error) => {
    pageErrors.push(error.message);
  });
  page.on("requestfailed", (request) => {
    failedRequests.push({
      url: request.url().slice(0, 500),
      failure: request.failure() ? request.failure().errorText : "",
      resourceType: request.resourceType(),
    });
  });

  await page.setViewport({ width: 1440, height: 950, deviceScaleFactor: 1 });
  await page.goto(targetUrl, { waitUntil: "domcontentloaded", timeout: 90000 });
  await wait(2500);

  const states = [];
  states.push(await collectState(page, "01-initial-desktop"));

  const clickedIntro = await clickFirstVisible(page, [
    "[data-sffc-apply-chat-intro-start]",
    ".sffc-crm-apply-chat__intro-cta",
    "[data-sffc-apply-chat-open]",
    ".sffc-crm-apply-chat__launcher",
  ]);
  await wait(4500);
  states.push(await collectState(page, "02-after-intro-click"));

  const sentPreCvCareerQuestion = await typeChatMessage(page, preCvCareerQuestion);
  await wait(5500);
  states.push(await collectState(page, "03-after-pre-cv-career-question"));

  const clickedWelcomePill = await clickWelcomeSearchPill(page);
  await wait(6500);
  states.push(await collectState(page, "04-after-welcome-search-pill"));

  const typedQueries = [];
  for (let index = 0; index < testQueries.length; index += 1) {
    const query = testQueries[index];
    typedQueries.push({ query, sent: await typeChatMessage(page, query) });
    if (index === 0) {
      await waitForVisibleResultAfterSearch(page);
    }
    await wait(6000);
    states.push(await collectState(page, `05-after-search-message-${index + 1}`));
  }

  const clickedResultTitle = await clickFirstVisible(page, [
    ".sffc-crm-apply-results__title",
    "[data-sffc-apply-results-toggle-review]",
  ]);
  await wait(7500);
  states.push(await collectState(page, "06-after-result-title-click"));

  const clickedEmployerReview = await clickNewestButtonByText(
    page,
    "review\\s+fit|open\\s+employer\\s+form|try\\s+live\\s+embed|refresh\\s+preview"
  );
  await wait(6500);
  states.push(await collectState(page, "07-after-employer-review-control"));

  const clickedPrimary = await clickFirstVisible(page, [
    ".sffc-crm-apply-results__btn--primary",
    "[data-sffc-apply-results-apply-tailored]",
  ]);
  await wait(5000);
  states.push(await collectState(page, "08-after-primary-apply-click"));

  const uploadCvResult = await uploadCv(page);
  await wait(uploadCvResult.uploaded ? 10000 : 1000);
  states.push(await collectState(page, "09-after-cv-upload"));

  const clickedUseTailored = await clickNewestButtonByText(page, "use\\s+tailored\\s+cv|tailored\\s+cv");
  await wait(7000);
  states.push(await collectState(page, "10-after-use-tailored-cv"));

  const clickedCoverLetterOption = await clickNewestButtonByText(
    page,
    "apply\\s+with\\s+cover\\s+letter|continue\\s+without\\s+cover\\s+letter|use\\s+cover\\s+letter|without\\s+cover\\s+letter"
  );
  await wait(6500);
  states.push(await collectState(page, "11-after-cover-letter-choice"));

  const clickedJumpToApplication = await clickButtonByText(page, "jump\\s+to\\s+application|continue\\s+with\\s+original");
  await wait(6000);
  states.push(await collectState(page, "12-after-jump-to-application"));

  const sentSalaryQuestion = await typeChatMessage(page, salaryQuestion);
  await wait(5500);
  states.push(await collectState(page, "13-after-salary-question"));

  const sentInterviewQuestion = await typeChatMessage(page, interviewQuestion);
  await wait(5500);
  states.push(await collectState(page, "14-after-interview-question"));

  await page.setViewport({ width: 390, height: 844, deviceScaleFactor: 2 });
  await wait(1000);
  states.push(await collectState(page, "15-mobile"));

  const report = {
    targetUrl,
    clickedIntro,
    sentPreCvCareerQuestion,
    clickedWelcomePill,
    typedQueries,
    clickedResultTitle,
    clickedEmployerReview,
    clickedPrimary,
    uploadCv: uploadCvResult,
    clickedUseTailored,
    clickedCoverLetterOption,
    clickedJumpToApplication,
    sentSalaryQuestion,
    sentInterviewQuestion,
    consoleMessages,
    pageErrors,
    failedRequests: failedRequests.filter((item) => !/favicon|analytics|googletagmanager|doubleclick/i.test(item.url)).slice(0, 50),
    states,
  };
  report.manualQaFindings = buildManualQaFindings(report);
  const reportPath = path.join(outputDir, "report.json");
  fs.writeFileSync(reportPath, JSON.stringify(report, null, 2));
  console.log(JSON.stringify({ reportPath, summary: report.states.map((state) => ({
    label: state.label,
    messageCount: state.messageCount,
    resultCount: state.resultCount,
    hasApplyResultsCard: state.hasApplyResultsCard,
    hasRouteSelector: state.hasRouteSelector,
    hasCvReviewFirstCards: state.hasCvReviewFirstCards,
    hasWelcomePills: state.hasWelcomePills,
    tailoredCvCardCount: state.tailoredCvCardCount,
    coverLetterCardCount: state.coverLetterCardCount,
    composerUsable: state.composerUsable,
    composerAlignedWithMessages: state.composerAlignedWithMessages,
    horizontalOverflow: state.horizontalOverflow,
    introCtaText: state.introCtaText,
    composerPlaceholder: state.composerPlaceholder,
    screenshot: state.screenshot,
  })),
  manualQaFindings: report.manualQaFindings,
  failedManualQaChecks: report.manualQaFindings.filter((finding) => !finding.ok).length,
  consoleErrors: consoleMessages.length,
  pageErrors: pageErrors.length,
  failedRequests: report.failedRequests.length }, null, 2));
  await browser.disconnect();
})().catch((error) => {
  console.error(error && error.stack ? error.stack : error);
  process.exit(1);
});
