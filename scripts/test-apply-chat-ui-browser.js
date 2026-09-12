#!/usr/bin/env node

let puppeteer;
const fs = require("fs");
const { getChromeBrowserWsEndpoint } = require("./chrome-debug");

try {
  puppeteer = require("puppeteer");
} catch (error) {
  try {
    puppeteer = require("../application-worker/node_modules/puppeteer");
  } catch (innerError) {
    console.log("SKIP apply-chat browser UI fixtures: puppeteer is not installed.");
    process.exit(0);
  }
}

const localChromePath = "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome";
const browserLaunchOptions = {
  headless: "new",
  timeout: 60000,
  args: ["--no-sandbox", "--disable-setuid-sandbox", "--disable-dev-shm-usage"],
};

async function openBrowser() {
  if (process.env.SFFC_USE_EXISTING_CHROME === "1") {
    return {
      browser: await puppeteer.connect({
        browserWSEndpoint: await getChromeBrowserWsEndpoint(),
      }),
      connected: true,
    };
  }
  return {
    browser: await puppeteer.launch(browserLaunchOptions),
    connected: false,
  };
}

if (process.env.PUPPETEER_EXECUTABLE_PATH) {
  browserLaunchOptions.executablePath = process.env.PUPPETEER_EXECUTABLE_PATH;
} else if (fs.existsSync(localChromePath)) {
  browserLaunchOptions.executablePath = localChromePath;
}

const html = String.raw`<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { margin: 0; font-family: Inter, Arial, sans-serif; background: #eef4fb; }
    .sffc-crm-apply-chat { width: 100%; min-height: 100vh; overflow-x: hidden; padding: 32px 32px 180px; box-sizing: border-box; }
    .sffc-crm-apply-chat__message.is-emily.has-apply-results-card { width: min(920px, 100%); margin: 0 auto; }
    .sffc-crm-apply-results { width: 100%; box-sizing: border-box; }
    .sffc-crm-apply-results__topbar,
    .sffc-crm-apply-results__filters,
    .sffc-crm-apply-results__result {
      background: #fff;
      border: 1px solid #dfe5ee;
      border-radius: 8px;
      box-sizing: border-box;
    }
    .sffc-crm-apply-results__topbar { padding: 16px; }
    .sffc-crm-apply-results__filters { display: flex; gap: 8px; flex-wrap: wrap; margin: 12px 0; padding: 12px; }
    .sffc-crm-apply-results__list { display: grid; gap: 12px; }
    .sffc-crm-apply-results__result { padding: 18px; }
    .sffc-crm-apply-results__title { display: block; max-width: 100%; border: 0; padding: 0; background: transparent; color: #1a0dab; font-size: 20px; text-align: left; overflow-wrap: anywhere; }
    .sffc-crm-apply-results__actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 14px; }
    .sffc-crm-apply-results__btn { border: 1px solid #dfe5ee; border-radius: 999px; padding: 10px 14px; background: #fff; }
    .sffc-crm-apply-results__btn--primary { background: #1a73e8; color: #fff; border-color: #1a73e8; }
    .sffc-crm-apply-results__review[hidden] { display: none; }
    .sffc-crm-apply-results__review { margin-top: 14px; border-top: 1px solid #e8edf4; padding-top: 14px; }
    .sffc-crm-apply-results__review-frame { width: 100%; min-height: 180px; border: 1px solid #dfe5ee; border-radius: 8px; }
    .sffc-crm-apply-chat__composer {
      position: fixed;
      left: 50%;
      bottom: 24px;
      transform: translateX(-50%);
      width: min(920px, calc(100vw - 64px));
      min-height: 112px;
      padding: 13px;
      border: 1px solid #ddddda;
      border-radius: 22px;
      background: rgba(255, 255, 255, 0.96);
      box-shadow: 0 16px 44px rgba(24, 24, 24, 0.1);
      box-sizing: border-box;
      z-index: 20;
    }
    .sffc-crm-apply-chat__composer input {
      width: 100%;
      height: 54px;
      border: 0;
      outline: 0;
      font: inherit;
      font-size: 15.5px;
      background: transparent;
      box-sizing: border-box;
    }
    .sffc-crm-apply-chat__composer-bottom { display: flex; align-items: center; justify-content: space-between; }
    .sffc-crm-apply-chat__composer-tools { display: flex; gap: 8px; align-items: center; }
    .sffc-crm-apply-chat__composer-mode,
    .sffc-crm-apply-chat__composer-tool { border: 0; border-radius: 999px; padding: 7px 10px; background: transparent; }
    .sffc-crm-apply-chat__composer-mode { border: 1px solid #e2e2df; background: #fbfbfa; }
    .sffc-crm-apply-chat__send { width: 38px; height: 38px; border: 0; border-radius: 10px; background: #191919; color: #fff; }
    @media (max-width: 640px) {
      .sffc-crm-apply-chat { padding: 12px 12px 170px; }
      .sffc-crm-apply-chat__composer { width: calc(100vw - 24px); bottom: 12px; }
      .sffc-crm-apply-results__actions { display: grid; }
      .sffc-crm-apply-results__title { font-size: 17px; }
    }
  </style>
</head>
<body>
  <main class="sffc-crm-apply-chat">
    <section class="sffc-crm-apply-chat__message is-emily has-apply-results-card">
      <div class="sffc-crm-apply-results sffc-crm-apply-results--job-search" data-sffc-apply-results-query="private credit dubai" data-sffc-apply-results-count-value="1">
        <div class="sffc-crm-apply-results__topbar">
          <input data-sffc-apply-results-search value="" aria-label="Search role">
        </div>
        <div class="sffc-crm-apply-results__filters">
          <button data-sffc-apply-results-filter="all" class="is-active">All</button>
          <button data-sffc-apply-results-filter="high">Match: High</button>
        </div>
        <div class="sffc-crm-apply-results__list">
          <article class="sffc-crm-apply-results__result is-shortlisted" data-sffc-apply-results-item data-sffc-apply-results-index="1" data-sffc-apply-results-key="role-1" data-sffc-apply-chat-role-title="Business Development & Operations Senior Associate" data-sffc-apply-chat-company="Tam Development Co" data-sffc-apply-chat-location="Dubai" data-sffc-apply-chat-salary="AED 30k+" data-sffc-apply-chat-seniority="Senior Associate" data-sffc-apply-chat-sector="Private credit" data-sffc-apply-chat-description="Commercial operations role with investment-adjacent execution." data-sffc-apply-chat-match-reason="Strong overlap with finance, operations and GCC market exposure." data-sffc-apply-chat-match-missing="Direct private credit execution is not fully visible.">
            <button type="button" class="sffc-crm-apply-results__title" data-sffc-apply-results-toggle-review="role-1" aria-expanded="false" aria-controls="review-1">Business Development & Operations Senior Associate</button>
            <p class="sffc-crm-apply-results__snippet">Matched against your CV and current search.</p>
            <div class="sffc-crm-apply-results__actions">
              <button class="sffc-crm-apply-results__btn sffc-crm-apply-results__btn--primary">Apply with Tailored CV</button>
              <button class="sffc-crm-apply-results__btn sffc-crm-apply-results__btn--secondary">Continue with Original CV</button>
            </div>
            <div class="sffc-crm-apply-results__review" hidden id="review-1" data-sffc-apply-results-review-panel="role-1">
              <a class="sffc-crm-apply-results__review-link" href="https://example.com/apply" target="_blank" rel="noopener noreferrer">Open form</a>
              <iframe class="sffc-crm-apply-results__review-frame" data-sffc-apply-results-review-frame data-src="https://example.com/apply"></iframe>
            </div>
          </article>
        </div>
      </div>
    </section>
    <form class="sffc-crm-apply-chat__composer" data-sffc-apply-chat-composer>
      <div class="sffc-crm-apply-chat__composer-input-row">
        <input type="text" data-sffc-apply-chat-input placeholder="Ask Emily about a role, company, CV or job search...">
      </div>
      <div class="sffc-crm-apply-chat__composer-bottom">
        <div class="sffc-crm-apply-chat__composer-tools">
          <button type="button" class="sffc-crm-apply-chat__composer-tool">+</button>
          <button type="button" class="sffc-crm-apply-chat__composer-mode">Search jobs</button>
          <button type="button" class="sffc-crm-apply-chat__composer-tool">Use my CV</button>
        </div>
        <button type="submit" class="sffc-crm-apply-chat__send">↑</button>
      </div>
    </form>
  </main>
  <script>
    document.addEventListener("click", function (event) {
      const toggle = event.target.closest("[data-sffc-apply-results-toggle-review]");
      if (!toggle) return;
      const card = toggle.closest(".sffc-crm-apply-results__result");
      const panel = card.querySelector("[data-sffc-apply-results-review-panel]");
      const frame = panel.querySelector("[data-sffc-apply-results-review-frame]");
      panel.hidden = !panel.hidden;
      toggle.setAttribute("aria-expanded", panel.hidden ? "false" : "true");
      if (!panel.hidden && !frame.getAttribute("src")) {
        frame.setAttribute("src", frame.getAttribute("data-src"));
      }
    });
  </script>
</body>
</html>`;

async function assertViewport(page, viewport) {
  await page.setViewport(viewport);
  await page.setContent(html, { waitUntil: "domcontentloaded" });
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1);
  if (overflow) {
    throw new Error(`horizontal overflow at ${viewport.width}x${viewport.height}`);
  }
  const buttonCount = await page.$$eval(".sffc-crm-apply-results__actions .sffc-crm-apply-results__btn", (buttons) => buttons.length);
  if (buttonCount < 2) {
    throw new Error("missing result action buttons");
  }
  const layout = await page.evaluate(() => {
    const result = document.querySelector(".sffc-crm-apply-chat__message.has-apply-results-card");
    const composer = document.querySelector("[data-sffc-apply-chat-composer]");
    const input = document.querySelector("[data-sffc-apply-chat-input]");
    input.focus();
    const resultRect = result.getBoundingClientRect();
    const composerRect = composer.getBoundingClientRect();
    return {
      activeInput: document.activeElement === input,
      leftDelta: Math.abs(resultRect.left - composerRect.left),
      rightDelta: Math.abs(resultRect.right - composerRect.right),
      composerVisible:
        composerRect.width > 200 &&
        composerRect.height > 80 &&
        composerRect.bottom <= window.innerHeight + 1,
    };
  });
  if (!layout.activeInput) {
    throw new Error("composer input is not focusable");
  }
  if (!layout.composerVisible) {
    throw new Error("composer is not visible inside the viewport");
  }
  if (layout.leftDelta > 1 || layout.rightDelta > 1) {
    throw new Error(
      `composer is not aligned with result column: left ${layout.leftDelta}, right ${layout.rightDelta}`
    );
  }
  const resultContext = await page.$eval(".sffc-crm-apply-results--job-search", (surface) => {
    const card = surface.querySelector(".sffc-crm-apply-results__result");
    return {
      query: surface.getAttribute("data-sffc-apply-results-query"),
      count: surface.getAttribute("data-sffc-apply-results-count-value"),
      index: card && card.getAttribute("data-sffc-apply-results-index"),
      key: card && card.getAttribute("data-sffc-apply-results-key"),
      title: card && card.getAttribute("data-sffc-apply-chat-role-title"),
      company: card && card.getAttribute("data-sffc-apply-chat-company"),
      salary: card && card.getAttribute("data-sffc-apply-chat-salary"),
      seniority: card && card.getAttribute("data-sffc-apply-chat-seniority"),
      sector: card && card.getAttribute("data-sffc-apply-chat-sector"),
      description: card && card.getAttribute("data-sffc-apply-chat-description"),
      matchReason: card && card.getAttribute("data-sffc-apply-chat-match-reason"),
      matchMissing: card && card.getAttribute("data-sffc-apply-chat-match-missing"),
    };
  });
  const missingContext = Object.entries(resultContext)
    .filter(([, value]) => !value)
    .map(([key]) => key);
  if (missingContext.length) {
    throw new Error(`missing structured result context: ${missingContext.join(", ")}`);
  }
  await page.click(".sffc-crm-apply-results__title");
  const expanded = await page.$eval(".sffc-crm-apply-results__title", (node) => node.getAttribute("aria-expanded"));
  const frameSrc = await page.$eval(".sffc-crm-apply-results__review-frame", (node) => node.getAttribute("src"));
  if (expanded !== "true" || !frameSrc) {
    throw new Error("review panel did not expand and hydrate iframe");
  }
  const screenshotDir = "reports/apply-chat-ui";
  fs.mkdirSync(screenshotDir, { recursive: true });
  await page.screenshot({
    path: `${screenshotDir}/apply-chat-${viewport.width}x${viewport.height}.png`,
    fullPage: true,
  });
}

(async () => {
  const session = await openBrowser();
  const browser = session.browser;
  try {
    const page = await browser.newPage();
    await assertViewport(page, { width: 1366, height: 900 });
    await assertViewport(page, { width: 390, height: 844 });
    console.log("PASS apply-chat browser UI fixtures");
  } finally {
    if (session.connected && typeof browser.disconnect === "function") {
      browser.disconnect();
    } else {
      await browser.close();
    }
  }
})().catch((error) => {
  if (/(waiting for the WS endpoint URL|Failed to launch the browser process)/i.test(error && error.message ? error.message : "")) {
    console.log(
      "SKIP apply-chat browser UI fixtures: local Chrome could not be controlled by Puppeteer in this runtime."
    );
    process.exit(0);
  }
  console.error("FAIL apply-chat browser UI fixtures:", error.message);
  process.exit(1);
});
