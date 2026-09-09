#!/usr/bin/env node

let puppeteer;
try {
  puppeteer = require("puppeteer");
} catch (error) {
  puppeteer = require("../application-worker/node_modules/puppeteer");
}

const browserWs =
  process.env.SFFC_CHROME_WS ||
  process.env.BROWSER_WS_ENDPOINT ||
  "ws://127.0.0.1:9222/devtools/browser/1744e14a-7356-49a9-9be7-2e5111942700";
const textPattern = process.argv.slice(2).join(" ") || "Jump to application";

(async () => {
  const browser = await puppeteer.connect({ browserWSEndpoint: browserWs });
  const page = (await browser.pages()).find((item) => /joinsenna\.com\/jobs\//.test(item.url()));
  if (!page) throw new Error("No joinsenna.com job page found.");
  const clicked = await page.evaluate((patternText) => {
    const pattern = new RegExp(patternText, "i");
    const candidates = Array.from(document.querySelectorAll(".sffc-crm-apply-chat button, .sffc-crm-apply-chat a"));
    const candidate = candidates.find((node) => {
      const label = String(node.textContent || "").replace(/\s+/g, " ").trim();
      const rect = node.getBoundingClientRect();
      const visible =
        rect.width > 0 &&
        rect.height > 0 &&
        window.getComputedStyle(node).visibility !== "hidden" &&
        window.getComputedStyle(node).display !== "none";
      return visible && pattern.test(label);
    });
    if (!candidate) return "";
    candidate.click();
    return String(candidate.textContent || "").replace(/\s+/g, " ").trim();
  }, textPattern);
  console.log(JSON.stringify({ clicked }, null, 2));
  await browser.disconnect();
})().catch((error) => {
  console.error(error && error.stack ? error.stack : error);
  process.exit(1);
});
