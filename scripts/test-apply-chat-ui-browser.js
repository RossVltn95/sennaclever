#!/usr/bin/env node

let puppeteer;
const fs = require("fs");

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
    .sffc-crm-apply-chat { width: 100%; min-height: 100vh; overflow-x: hidden; padding: 32px; box-sizing: border-box; }
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
    @media (max-width: 640px) {
      .sffc-crm-apply-chat { padding: 12px; }
      .sffc-crm-apply-results__actions { display: grid; }
      .sffc-crm-apply-results__title { font-size: 17px; }
    }
  </style>
</head>
<body>
  <main class="sffc-crm-apply-chat">
    <section class="sffc-crm-apply-chat__message is-emily has-apply-results-card">
      <div class="sffc-crm-apply-results">
        <div class="sffc-crm-apply-results__topbar">
          <input data-sffc-apply-results-search value="" aria-label="Search role">
        </div>
        <div class="sffc-crm-apply-results__filters">
          <button data-sffc-apply-results-filter="all" class="is-active">All</button>
          <button data-sffc-apply-results-filter="high">Match: High</button>
        </div>
        <div class="sffc-crm-apply-results__list">
          <article class="sffc-crm-apply-results__result is-shortlisted" data-sffc-apply-results-item>
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
  await page.click(".sffc-crm-apply-results__title");
  const expanded = await page.$eval(".sffc-crm-apply-results__title", (node) => node.getAttribute("aria-expanded"));
  const frameSrc = await page.$eval(".sffc-crm-apply-results__review-frame", (node) => node.getAttribute("src"));
  if (expanded !== "true" || !frameSrc) {
    throw new Error("review panel did not expand and hydrate iframe");
  }
}

(async () => {
  const browser = await puppeteer.launch(browserLaunchOptions);
  try {
    const page = await browser.newPage();
    await assertViewport(page, { width: 1366, height: 900 });
    await assertViewport(page, { width: 390, height: 844 });
    console.log("PASS apply-chat browser UI fixtures");
  } finally {
    await browser.close();
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
