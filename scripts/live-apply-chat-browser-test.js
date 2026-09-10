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

fs.mkdirSync(outputDir, { recursive: true });

const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

async function getPage(browser) {
  const pages = await browser.pages();
  const existing = pages.find((page) => page.url().replace(/\/$/, "") === targetUrl.replace(/\/$/, ""));
  if (existing) return existing;
  const page = await browser.newPage();
  await page.goto(targetUrl, { waitUntil: "domcontentloaded", timeout: 90000 });
  return page;
}

async function collectState(page, label) {
  const state = await page.evaluate((stateLabel) => {
    const text = (node) => (node ? String(node.textContent || "").replace(/\s+/g, " ").trim() : "");
    const root = document.querySelector("[data-sffc-apply-chat], .sffc-crm-apply-chat");
    const messages = Array.from(document.querySelectorAll(".sffc-crm-apply-chat__message")).map((node) => ({
      className: node.className,
      text: text(node).slice(0, 500),
    }));
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
    }));
    const rootRect = root ? root.getBoundingClientRect() : null;
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
      documentScrollWidth: document.documentElement.scrollWidth,
      windowInnerWidth: window.innerWidth,
      horizontalOverflow: document.documentElement.scrollWidth > window.innerWidth + 1,
      messageCount: messages.length,
      messages: messages.slice(-10),
      hasApplyResultsCard: !!document.querySelector(".sffc-crm-apply-chat__message.has-apply-results-card"),
      hasApplyResultsList: !!document.querySelector(".sffc-crm-apply-results__list"),
      resultCount: resultCards.length,
      resultCards: resultCards.slice(0, 5),
      hasRouteSelector: !!document.querySelector(".sffc-crm-apply-chat__route-selector, .sffc-crm-apply-chat__quick-route-selector"),
      hasMembershipChoiceText: /membership choice|sign me up|service/i.test(document.body.textContent || ""),
      hasCvReviewFirstCards: !!document.querySelector(
        ".sffc-crm-apply-chat__analysis-progress-card, .sffc-crm-apply-chat__application-insight-card, .sffc-crm-apply-chat__search-strategy-card"
      ),
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
  await page.click(selector, { clickCount: 3 });
  await page.keyboard.type(value, { delay: 8 });
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

(async () => {
  const consoleMessages = [];
  const pageErrors = [];
  const failedRequests = [];
  const browser = await puppeteer.connect({ browserWSEndpoint: await getChromeBrowserWsEndpoint() });
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
  await page.goto(targetUrl, { waitUntil: "networkidle2", timeout: 120000 });
  await wait(2500);

  const states = [];
  states.push(await collectState(page, "01-initial-desktop"));

  const clickedIntro = await clickFirstVisible(page, [
    "[data-sffc-apply-chat-intro-start]",
    ".sffc-crm-apply-chat__intro-cta",
    ".sffc-crm-apply-chat button",
  ]);
  await wait(4500);
  states.push(await collectState(page, "02-after-intro-click"));

  const typedQueries = [];
  for (let index = 0; index < testQueries.length; index += 1) {
    const query = testQueries[index];
    typedQueries.push({ query, sent: await typeChatMessage(page, query) });
    await wait(6000);
    states.push(await collectState(page, `03-after-search-message-${index + 1}`));
  }

  const clickedResultTitle = await clickFirstVisible(page, [
    ".sffc-crm-apply-results__title",
    "[data-sffc-apply-results-toggle-review]",
  ]);
  await wait(2500);
  states.push(await collectState(page, "04-after-result-title-click"));

  const clickedPrimary = await clickFirstVisible(page, [
    ".sffc-crm-apply-results__btn--primary",
    "[data-sffc-apply-results-apply-tailored]",
  ]);
  await wait(5000);
  states.push(await collectState(page, "05-after-primary-apply-click"));

  const clickedJumpToApplication = await clickButtonByText(page, "jump\\s+to\\s+application|continue\\s+with\\s+original");
  await wait(6000);
  states.push(await collectState(page, "05b-after-jump-to-application"));

  await page.setViewport({ width: 390, height: 844, deviceScaleFactor: 2 });
  await wait(1000);
  states.push(await collectState(page, "06-mobile"));

  const report = {
    targetUrl,
    clickedIntro,
    typedQueries,
    clickedResultTitle,
    clickedPrimary,
    clickedJumpToApplication,
    consoleMessages,
    pageErrors,
    failedRequests: failedRequests.filter((item) => !/favicon|analytics|googletagmanager|doubleclick/i.test(item.url)).slice(0, 50),
    states,
  };
  const reportPath = path.join(outputDir, "report.json");
  fs.writeFileSync(reportPath, JSON.stringify(report, null, 2));
  console.log(JSON.stringify({ reportPath, summary: report.states.map((state) => ({
    label: state.label,
    messageCount: state.messageCount,
    resultCount: state.resultCount,
    hasApplyResultsCard: state.hasApplyResultsCard,
    hasRouteSelector: state.hasRouteSelector,
    hasCvReviewFirstCards: state.hasCvReviewFirstCards,
    horizontalOverflow: state.horizontalOverflow,
    introCtaText: state.introCtaText,
    composerPlaceholder: state.composerPlaceholder,
    screenshot: state.screenshot,
  })), consoleErrors: consoleMessages.length, pageErrors: pageErrors.length, failedRequests: report.failedRequests.length }, null, 2));
  await browser.disconnect();
})().catch((error) => {
  console.error(error && error.stack ? error.stack : error);
  process.exit(1);
});
