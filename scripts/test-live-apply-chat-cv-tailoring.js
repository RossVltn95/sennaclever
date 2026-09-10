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
  "https://joinsenna.com/jobs/vice-president-ma-ecm-aventus-global-864/";
const cvPath =
  process.env.SFFC_TEST_CV_PATH ||
  "/Users/ropafadzoyasheushe/Downloads/hiredCV-SkillFarm.pdf";
const outputDir =
  process.env.SFFC_LIVE_CV_TAILORING_DIR ||
  "/tmp/senna-live-apply-chat-cv-tailoring";
const partialReportPath = path.join(outputDir, "partial-report.json");
const loginEnabled = /^(?:1|true|yes)$/i.test(String(process.env.SFFC_LIVE_CHAT_LOGIN || ""));
const loginUrl = process.env.SFFC_LIVE_CHAT_LOGIN_URL || "https://joinsenna.com/login/";
const loginUsername = String(process.env.SFFC_LIVE_CHAT_USERNAME || "").trim();
const loginPassword = String(process.env.SFFC_LIVE_CHAT_PASSWORD || "");
const isolatedContextEnabled = process.env.SFFC_ISOLATED_CONTEXT !== "0";

fs.mkdirSync(outputDir, { recursive: true });

const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

async function visibleSelector(page, selectors) {
  for (const selector of selectors) {
    const handle = await page.$(selector);
    if (!handle) continue;
    const visible = await handle.evaluate((node) => {
      const rect = node.getBoundingClientRect();
      const style = window.getComputedStyle(node);
      return (
        rect.width > 0 &&
        rect.height > 0 &&
        style.visibility !== "hidden" &&
        style.display !== "none"
      );
    });
    if (visible) return selector;
  }
  return "";
}

async function clickVisible(page, selectors) {
  const selector = await visibleSelector(page, selectors);
  if (!selector) return "";
  await page.click(selector);
  return selector;
}

async function clickButtonText(page, patternSource) {
  return page.evaluate((source) => {
    const pattern = new RegExp(source, "i");
    const nodes = Array.from(document.querySelectorAll("button, a"));
    const node = nodes.find((item) => {
      const label = String(item.textContent || "").replace(/\s+/g, " ").trim();
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
    return String(node.textContent || "").replace(/\s+/g, " ").trim();
  }, patternSource);
}

async function chooseLanguageIfPrompted(page) {
  return clickButtonText(page, "^english$|english");
}

async function uploadCv(page) {
  const input = await page.$("[data-sffc-apply-chat-file], input[type='file']");
  if (!input) return { uploaded: false, reason: "file input not found" };
  await input.uploadFile(cvPath);
  return { uploaded: true, cvPath };
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

async function typeChatMessage(page, value) {
  const selector = await visibleSelector(page, [
    "[data-sffc-apply-chat-input]",
    ".sffc-crm-apply-chat textarea",
    ".sffc-crm-apply-chat input[type='text']",
  ]);
  if (!selector) return false;
  await page.click(selector, { clickCount: 3 });
  await page.keyboard.type(value, { delay: 8 });
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

async function collectState(page, label) {
  const state = await page.evaluate((stateLabel) => {
    const text = (node) =>
      node ? String(node.textContent || "").replace(/\s+/g, " ").trim() : "";
    const root = document.querySelector(".sffc-crm-apply-chat, [data-sffc-apply-chat]");
    const rect = root ? root.getBoundingClientRect() : null;
    const messages = Array.from(
      document.querySelectorAll(".sffc-crm-apply-chat__message")
    ).map((node) => ({
      className: node.className,
      text: text(node).slice(0, 800),
    }));
    const tailoredPreview = document.querySelector(
      ".sffc-crm-apply-chat__tailored-cv-preview"
    );
    const tailoredCard = document.querySelector(
      ".sffc-crm-apply-chat__tailored-cv-document-card, .sffc-crm-apply-chat__tailoring-preview-card, .sffc-crm-apply-chat__tailored-version-card"
    );
    const quickInsights = document.querySelector(
      ".sffc-crm-apply-chat__quick-insights"
    );
    const routeCards = Array.from(
      document.querySelectorAll(
        ".sffc-crm-apply-chat__quick-route-plan, .sffc-crm-apply-chat__quick-route-card, .sffc-crm-apply-chat__route-card"
      )
    ).map((node) => text(node).slice(0, 500));
    const previewRect = tailoredPreview
      ? tailoredPreview.getBoundingClientRect()
      : null;
    return {
      label: stateLabel,
      url: location.href,
      title: document.title,
      viewport: { width: window.innerWidth, height: window.innerHeight },
      rootRect: rect
        ? { left: rect.left, top: rect.top, width: rect.width, height: rect.height }
        : null,
      documentScrollWidth: document.documentElement.scrollWidth,
      horizontalOverflow: document.documentElement.scrollWidth > window.innerWidth + 1,
      messageCount: messages.length,
      lastMessages: messages.slice(-8),
      hasIntroCard: !!document.querySelector(
        ".sffc-crm-apply-chat__intro-apply-card"
      ),
      hasUploadPreview: !!document.querySelector(
        ".sffc-crm-apply-chat__upload-preview-card, .sffc-crm-apply-chat__upload-preview-image"
      ),
      hasQuickInsights: !!quickInsights,
      quickInsightsText: quickInsights ? text(quickInsights).slice(0, 1500) : "",
      hasRouteCards: routeCards.length > 0,
      routeCards,
      hasTailoredCard: !!tailoredCard,
      tailoredCardClass: tailoredCard ? tailoredCard.className : "",
      hasTailoredPreview: !!tailoredPreview,
      tailoredPreviewClass: tailoredPreview ? tailoredPreview.className : "",
      tailoredPreviewRect: previewRect
        ? {
            left: previewRect.left,
            top: previewRect.top,
            width: previewRect.width,
            height: previewRect.height,
          }
        : null,
      hasVisualFallback: !!document.querySelector(
        ".sffc-crm-apply-chat__tailored-cv-preview.is-visual-fallback"
      ),
      hasProfessionalSheet: !!document.querySelector(
        ".sffc-crm-apply-chat__tailored-cv-sheet"
      ),
      sheetText: text(
        document.querySelector(".sffc-crm-apply-chat__tailored-cv-sheet")
      ).slice(0, 2000),
      composerPlaceholder:
        document
          .querySelector("[data-sffc-apply-chat-input], .sffc-crm-apply-chat textarea")
          ?.getAttribute("placeholder") || "",
    };
  }, label);
  const screenshotPath = path.join(
    outputDir,
    label.toLowerCase().replace(/[^a-z0-9]+/g, "-") + ".png"
  );
  await page.screenshot({ path: screenshotPath, fullPage: false });
  state.screenshot = screenshotPath;
  return state;
}

(async () => {
  if (!fs.existsSync(cvPath)) {
    throw new Error(`CV file does not exist: ${cvPath}`);
  }

  const shouldLaunchChrome = process.env.SFFC_LAUNCH_CHROME === "1";
  const launchWithPipe = /^(?:1|true|yes)$/i.test(
    String(process.env.SFFC_BROWSER_PIPE || "")
  );
  const browser = shouldLaunchChrome
    ? await puppeteer.launch({
        executablePath:
          process.env.PUPPETEER_EXECUTABLE_PATH ||
          "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome",
        headless: process.env.SFFC_BROWSER_HEADLESS !== "0",
        pipe: launchWithPipe,
        protocolTimeout: 180000,
        args: [
          "--no-first-run",
          "--no-default-browser-check",
          "--disable-dev-shm-usage",
        ],
      })
    : await puppeteer.connect({
        browserWSEndpoint: await getChromeBrowserWsEndpoint(),
        protocolTimeout: 180000,
      });
  let browserContext = null;
  if (isolatedContextEnabled) {
    if (typeof browser.createBrowserContext === "function") {
      browserContext = await browser.createBrowserContext();
    } else if (typeof browser.createIncognitoBrowserContext === "function") {
      browserContext = await browser.createIncognitoBrowserContext();
    }
  }
  const page = browserContext ? await browserContext.newPage() : await browser.newPage();
  const consoleMessages = [];
  const pageErrors = [];
  const failedRequests = [];
  const serverErrors = [];
  const states = [];
  const actions = [];
  const authState = await loginIfRequested(page);

  function writePartialReport() {
    fs.writeFileSync(
      partialReportPath,
      JSON.stringify(
        {
          targetUrl,
          cvPath,
          authState,
          actions,
          consoleMessages,
          pageErrors,
          serverErrors: serverErrors.slice(0, 80),
          failedRequests: failedRequests
            .filter((item) => !/favicon|analytics|googletagmanager|doubleclick|\.cloud\/rum/i.test(item.url))
            .slice(0, 80),
          states,
        },
        null,
        2
      )
    );
  }

  page.on("console", (message) => {
    if (["error", "warning"].includes(message.type())) {
      consoleMessages.push({
        type: message.type(),
        text: message.text().slice(0, 1000),
      });
    }
  });
  page.on("pageerror", (error) => pageErrors.push(error.message));
  page.on("requestfailed", (request) => {
    failedRequests.push({
      url: request.url().slice(0, 500),
      resourceType: request.resourceType(),
      failure: request.failure() ? request.failure().errorText : "",
    });
  });
  page.on("response", async (response) => {
    const status = response.status();
    const url = response.url();
    if (
      status < 400 ||
      /favicon|analytics|googletagmanager|doubleclick|\.cloud\/rum/i.test(url)
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

  await page.setViewport({ width: 1440, height: 950, deviceScaleFactor: 1 });
  await page.goto(targetUrl, { waitUntil: "networkidle2", timeout: 120000 });
  await wait(2500);
  states.push(await collectState(page, "01 initial"));
  writePartialReport();

  actions.push({
    name: "open-chat",
    selector: await clickVisible(page, [
      ".sffc-crm-apply-chat__home-launcher-plus",
      "[data-sffc-apply-chat-open]",
      ".sffc-crm-apply-chat__launcher",
      ".sffc-crm-apply-chat__home-launcher-submit",
      ".sffc-crm-apply-chat",
    ]),
  });
  await wait(2500);
  states.push(await collectState(page, "02 chat-open"));
  writePartialReport();

  actions.push({
    name: "choose-language",
    label: await chooseLanguageIfPrompted(page),
  });
  if (actions[actions.length - 1].label) {
    await wait(1200);
    states.push(await collectState(page, "02b after-language"));
    writePartialReport();
  }

  actions.push({
    name: "check-fit",
    label:
      (await clickButtonText(page, "check\\s+my\\s+fit|start\\s+application")) ||
      (await clickVisible(page, [
        ".cta.sffc-crm-apply-chat__intro-cta",
        "[data-sffc-apply-chat-intro-start]",
      ])),
  });
  if (!actions[actions.length - 1].label) {
    actions[actions.length - 1].typed = await typeChatMessage(page, "check my fit");
  }
  await wait(2500);
  states.push(await collectState(page, "03 after-check-fit"));
  writePartialReport();

  actions.push({ name: "upload-cv", ...(await uploadCv(page)) });
  await wait(12000);
  states.push(await collectState(page, "04 after-upload"));
  writePartialReport();

  actions.push({
    name: "continue-this-role",
    label: await clickButtonText(
      page,
      "no,?\\s+apply\\s+for\\s+this\\s+role\\s+only|apply\\s+just\\s+to\\s+this\\s+role|continue\\s+with\\s+this\\s+role|apply\\s+to\\s+this\\s+role|just\\s+this\\s+application"
    ),
  });
  await wait(5000);
  states.push(await collectState(page, "05 after-continue-role"));
  writePartialReport();

  if (!actions[actions.length - 1].label) {
    actions.push({
      name: "continue-this-role-text",
      typed: await typeChatMessage(page, "apply for this role only"),
    });
    await wait(5000);
    states.push(await collectState(page, "05b after-continue-role-text"));
    writePartialReport();
  }

  actions.push({
    name: "tailor-or-apply",
    label: await clickButtonText(
      page,
      "tailor\\s+my\\s+cv|apply\\s+with\\s+tailored\\s+cv|continue\\s+with\\s+this\\s+role"
    ),
  });
  await wait(14000);
  states.push(await collectState(page, "06 after-tailoring-click"));
  writePartialReport();

  actions.push({
    name: "confirm-same-cv",
    label: await clickButtonText(page, "yes,?\\s+use\\s+the\\s+same\\s+cv|use\\s+the\\s+same\\s+cv"),
  });
  await wait(14000);
  states.push(await collectState(page, "07 after-confirm-same-cv"));
  writePartialReport();

  await page.setViewport({ width: 390, height: 844, deviceScaleFactor: 2 });
  await wait(1200);
  states.push(await collectState(page, "08 mobile-tailoring"));
  writePartialReport();

  const report = {
    targetUrl,
    cvPath,
    authState,
    actions,
    consoleMessages,
    pageErrors,
    serverErrors: serverErrors.slice(0, 80),
    failedRequests: failedRequests
      .filter((item) => !/favicon|analytics|googletagmanager|doubleclick|\.cloud\/rum/i.test(item.url))
      .slice(0, 80),
    states,
  };
  const reportPath = path.join(outputDir, "report.json");
  fs.writeFileSync(reportPath, JSON.stringify(report, null, 2));
  console.log(
    JSON.stringify(
      {
        reportPath,
        actions,
        summary: states.map((state) => ({
          label: state.label,
          messageCount: state.messageCount,
          hasQuickInsights: state.hasQuickInsights,
          hasRouteCards: state.hasRouteCards,
          hasTailoredCard: state.hasTailoredCard,
          hasTailoredPreview: state.hasTailoredPreview,
          hasVisualFallback: state.hasVisualFallback,
          hasProfessionalSheet: state.hasProfessionalSheet,
          horizontalOverflow: state.horizontalOverflow,
          screenshot: state.screenshot,
        })),
        consoleErrors: consoleMessages.length,
        pageErrors: pageErrors.length,
        serverErrors: report.serverErrors.length,
        failedRequests: report.failedRequests.length,
      },
      null,
      2
    )
  );
  if (shouldLaunchChrome) {
    await browser.close();
  } else {
    if (browserContext && typeof browserContext.close === "function") {
      await browserContext.close();
    }
    await browser.disconnect();
  }
})().catch((error) => {
  console.error(error && error.stack ? error.stack : error);
  process.exit(1);
});
