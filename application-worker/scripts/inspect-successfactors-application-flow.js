import fs from "node:fs/promises";
import fsSync from "node:fs";
import os from "node:os";
import path from "node:path";
import puppeteer from "puppeteer";
import { getSuccessFactorsVisibleState } from "../src/worker.js";

const testUrl = process.env.SFFC_SUCCESSFACTORS_TEST_URL || "";
if (!testUrl) {
  console.error("Set SFFC_SUCCESSFACTORS_TEST_URL to inspect a SuccessFactors application.");
  process.exit(1);
}

function isUsableBrowserExecutable(candidate) {
  return Boolean(candidate && fsSync.existsSync(candidate));
}

function getBrowserExecutablePath() {
  const configuredPath = process.env.PUPPETEER_EXECUTABLE_PATH || "";
  if (isUsableBrowserExecutable(configuredPath)) {
    return configuredPath;
  }
  return [
    "/usr/bin/google-chrome-stable",
    "/usr/bin/google-chrome",
    "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome",
    "/Applications/Chromium.app/Contents/MacOS/Chromium",
    "/usr/bin/chromium",
    "/usr/bin/chromium-browser",
  ].find(isUsableBrowserExecutable);
}

async function clickApply(page) {
  const clicked = await page.evaluate(() => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const visible = (element) => {
      if (!element) return false;
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
    };
    const target = Array.from(document.querySelectorAll("button, a, input[type='button'], input[type='submit']"))
      .filter(visible)
      .find((node) => {
        const text = clean(`${node.textContent || ""} ${node.getAttribute("value") || ""} ${node.getAttribute("aria-label") || ""}`);
        const id = node.getAttribute("id") || "";
        return /^apply$/i.test(text) || /apply now|apply for this job/i.test(text) || /^applyButton/i.test(id);
      });
    if (!target) return false;
    target.scrollIntoView({ block: "center", inline: "nearest" });
    target.click();
    return true;
  });
  if (clicked) {
    await page.waitForNavigation({ waitUntil: "domcontentloaded", timeout: 25000 }).catch(() => {});
    await page.waitForNetworkIdle({ idleTime: 1200, timeout: 25000 }).catch(() => {});
  }
  return clicked;
}

const browser = await puppeteer.launch({
  headless: "new",
  executablePath: getBrowserExecutablePath() || undefined,
  timeout: Number(process.env.SFFC_BROWSER_LAUNCH_TIMEOUT_MS || 90000),
  args: ["--no-sandbox", "--disable-setuid-sandbox"],
});

try {
  const page = await browser.newPage();
  await page.setViewport({ width: 1440, height: 1200 });
  await page.goto(testUrl, { waitUntil: "domcontentloaded", timeout: Number(process.env.SFFC_BROWSER_NAVIGATION_TIMEOUT_MS || 90000) });
  await page.waitForNetworkIdle({ idleTime: 1200, timeout: 25000 }).catch(() => {});
  const before = await getSuccessFactorsVisibleState(page);
  const clickedApply = await clickApply(page);
  const after = await getSuccessFactorsVisibleState(page);
  const screenshotPath = path.join(os.tmpdir(), `sffc-successfactors-inspect-${Date.now()}.png`);
  await page.screenshot({ path: screenshotPath, fullPage: true }).catch(() => {});
  const result = {
    input_url: testUrl,
    clicked_apply: clickedApply,
    before,
    after,
    screenshot_path: screenshotPath,
  };
  const outputPath = path.join(os.tmpdir(), `sffc-successfactors-inspect-${Date.now()}.json`);
  await fs.writeFile(outputPath, JSON.stringify(result, null, 2));
  console.log(JSON.stringify({ output_path: outputPath, ...result }, null, 2));
} finally {
  await browser.close();
}
