import fs from "node:fs";
import fsPromises from "node:fs/promises";
import os from "node:os";
import path from "node:path";
import puppeteer from "puppeteer";
import { cleanText, isValidEmployerUrl } from "./auth.js";

export function debugLog(...parts) {
  if (process.env.SFFC_REMOTE_BROWSER_DEBUG === "1") {
    console.log("[sffc-remote-browser]", ...parts);
  }
}

function isUsableBrowserExecutable(candidate) {
  if (!candidate || !fs.existsSync(candidate)) {
    return false;
  }
  try {
    const stat = fs.statSync(candidate);
    return stat.isFile() || stat.isSymbolicLink();
  } catch (error) {
    return false;
  }
}

export function getBrowserExecutablePath() {
  const configured = cleanText(
    process.env.SFFC_REMOTE_BROWSER_CHROME_EXECUTABLE ||
      process.env.PUPPETEER_EXECUTABLE_PATH ||
      ""
  );
  if (isUsableBrowserExecutable(configured)) {
    return configured;
  }
  const candidates = [
    ...(process.platform === "darwin"
      ? ["/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"]
      : []),
    "/usr/bin/google-chrome-stable",
    "/usr/bin/google-chrome",
    "/usr/bin/chromium",
    "/usr/bin/chromium-browser",
  ];
  return candidates.find(isUsableBrowserExecutable) || "";
}

export function getProfileRoot() {
  return cleanText(process.env.SFFC_REMOTE_BROWSER_PROFILE_ROOT || "") || path.join(os.tmpdir(), "sffc-remote-browser");
}

export async function createBrowserSession(sessionId, employerUrl, provider = "") {
  const cleanUrl = cleanText(employerUrl);
  const executablePath = getBrowserExecutablePath();
  const profileRoot = getProfileRoot();
  const userDataDir = path.join(profileRoot, sessionId);
  if (!isValidEmployerUrl(cleanUrl)) {
    throw new Error("Remote browser sessions require a valid external employer URL.");
  }
  await fsPromises.mkdir(userDataDir, { recursive: true });
  const browser = await puppeteer.launch({
    executablePath: executablePath || undefined,
    headless: process.env.SFFC_REMOTE_BROWSER_HEADLESS === "0" ? false : "new",
    userDataDir,
    timeout: Number(process.env.SFFC_REMOTE_BROWSER_LAUNCH_TIMEOUT_MS || 90000),
    args: [
      "--no-sandbox",
      "--disable-setuid-sandbox",
      "--disable-dev-shm-usage",
      "--no-first-run",
      "--no-default-browser-check",
      "--disable-blink-features=AutomationControlled",
      "--lang=en-GB,en-US,en",
      "--window-size=1366,1000",
    ],
  });
  const page = await browser.newPage();
  await page.setViewport({ width: 1366, height: 1000, deviceScaleFactor: 1 });
  await page.setExtraHTTPHeaders({
    "Accept-Language": "en-GB,en-US;q=0.9,en;q=0.8",
  }).catch(() => {});
  await page.goto(cleanUrl, {
    waitUntil: "domcontentloaded",
    timeout: Number(process.env.SFFC_REMOTE_BROWSER_NAVIGATION_TIMEOUT_MS || 60000),
  });
  debugLog("session ready", sessionId, provider, page.url());
  return {
    browser,
    page,
    userDataDir,
  };
}

function buildCloudflareBrowserEndpoint() {
  const configured = cleanText(
    process.env.SFFC_CLOUDFLARE_BROWSER_WS_ENDPOINT ||
      process.env.CLOUDFLARE_BROWSER_WS_ENDPOINT ||
      ""
  );
  if (configured) {
    return configured;
  }
  const accountId = cleanText(
    process.env.SFFC_CLOUDFLARE_ACCOUNT_ID ||
      process.env.CLOUDFLARE_ACCOUNT_ID ||
      ""
  );
  if (!accountId) {
    return "";
  }
  const keepAlive = Math.max(
    60,
    Number(process.env.SFFC_CLOUDFLARE_BROWSER_KEEP_ALIVE_MS || 600000) ||
      600000
  );
  return `wss://api.cloudflare.com/client/v4/accounts/${encodeURIComponent(accountId)}/browser-run/devtools/browser?keep_alive=${encodeURIComponent(String(keepAlive))}`;
}

function getCloudflareBrowserToken() {
  return cleanText(
    process.env.SFFC_CLOUDFLARE_API_TOKEN ||
      process.env.CLOUDFLARE_API_TOKEN ||
      ""
  );
}

function getBrowserlessEndpoint() {
  return cleanText(
    process.env.SFFC_BROWSERLESS_WS_ENDPOINT ||
      process.env.BROWSERLESS_WS_ENDPOINT ||
      ""
  );
}

function normalizeManagedTransport(transport = "") {
  const clean = cleanText(transport).toLowerCase();
  if (clean === "cloudflare" || clean === "cloudflare_live_view") {
    return "cloudflare_live_view";
  }
  if (clean === "browserless" || clean === "browserless_live_url") {
    return "browserless_live_url";
  }
  return "managed_live_browser";
}

async function getPrimaryPage(browser) {
  const pages = await browser.pages().catch(() => []);
  if (pages && pages[0]) {
    return pages[0];
  }
  return browser.newPage();
}

async function getBrowserlessLiveUrl(page) {
  const cdp = await page.createCDPSession();
  const result = await cdp.send("Browserless.liveURL", {
    quality: Number(process.env.SFFC_BROWSERLESS_LIVE_VIEW_QUALITY || 80) || 80,
    type: cleanText(process.env.SFFC_BROWSERLESS_LIVE_VIEW_TYPE || "jpeg") || "jpeg",
    timeout:
      Number(process.env.SFFC_BROWSERLESS_LIVE_VIEW_TIMEOUT_MS || 300000) ||
      300000,
    interactable: true,
    resizable: true,
  });
  const liveUrl = cleanText(
    result &&
      (result.liveURL ||
        result.liveUrl ||
        result.url ||
        result.browserUrl ||
        result.browserURL)
  );
  if (!liveUrl) {
    throw new Error("Browserless did not return a usable live view URL.");
  }
  return liveUrl;
}

async function getCloudflareLiveViewUrl(page) {
  const cdp = await page.createCDPSession();
  const targets = await cdp.send("Target.getTargets").catch(() => ({}));
  const targetInfos = Array.isArray(targets.targetInfos) ? targets.targetInfos : [];
  const currentUrl = cleanText(page.url());
  const target =
    targetInfos.find((item) => item.type === "page" && currentUrl && item.url === currentUrl) ||
    targetInfos.find((item) => item.type === "page") ||
    null;
  const params = {
    mode: cleanText(process.env.SFFC_CLOUDFLARE_LIVE_VIEW_MODE || "tab") || "tab",
    expiresInMs:
      Number(process.env.SFFC_CLOUDFLARE_LIVE_VIEW_EXPIRES_MS || 300000) ||
      300000,
  };
  if (target && target.targetId) {
    params.targetId = target.targetId;
  }
  const result = await cdp.send("Cloudflare.getLiveView", params);
  const liveUrl = cleanText(
    result &&
      (result.devtoolsFrontendUrl ||
        result.liveViewUrl ||
        result.liveViewURL ||
        result.liveUrl ||
        result.liveURL ||
        result.url)
  );
  if (!liveUrl) {
    throw new Error("Cloudflare Browser Run did not return a usable live view URL.");
  }
  return liveUrl;
}

export async function createManagedLiveBrowserSession(
  sessionId,
  employerUrl,
  provider = "",
  transport = ""
) {
  const cleanUrl = cleanText(employerUrl);
  const managedTransport = normalizeManagedTransport(transport);
  if (!isValidEmployerUrl(cleanUrl)) {
    throw new Error("Managed browser sessions require a valid external employer URL.");
  }

  let browser;
  let page;
  let liveUrl = "";
  if (managedTransport === "browserless_live_url") {
    const endpoint = getBrowserlessEndpoint();
    if (!endpoint) {
      throw new Error("Browserless live browser endpoint is not configured.");
    }
    browser = await puppeteer.connect({ browserWSEndpoint: endpoint });
    page = await getPrimaryPage(browser);
    await page.goto(cleanUrl, {
      waitUntil: "domcontentloaded",
      timeout: Number(process.env.SFFC_REMOTE_BROWSER_NAVIGATION_TIMEOUT_MS || 60000),
    });
    liveUrl = await getBrowserlessLiveUrl(page);
  } else if (managedTransport === "cloudflare_live_view") {
    const endpoint = buildCloudflareBrowserEndpoint();
    const token = getCloudflareBrowserToken();
    if (!endpoint || !token) {
      throw new Error("Cloudflare Browser Run endpoint or token is not configured.");
    }
    browser = await puppeteer.connect({
      browserWSEndpoint: endpoint,
      headers: {
        Authorization: `Bearer ${token}`,
      },
    });
    page = await getPrimaryPage(browser);
    await page.goto(cleanUrl, {
      waitUntil: "domcontentloaded",
      timeout: Number(process.env.SFFC_REMOTE_BROWSER_NAVIGATION_TIMEOUT_MS || 60000),
    });
    liveUrl = await getCloudflareLiveViewUrl(page);
  } else {
    throw new Error("A managed live browser provider is not configured.");
  }

  debugLog("managed live browser ready", sessionId, provider, managedTransport, page.url());
  return {
    kind: managedTransport,
    browser,
    page,
    liveUrl,
  };
}

export async function closeBrowserSession(runtime) {
  if (!runtime) {
    return;
  }
  await runtime.browser?.close?.().catch(() => {});
  if (runtime.userDataDir) {
    await fsPromises.rm(runtime.userDataDir, { recursive: true, force: true }).catch(() => {});
  }
}

export async function navigateSession(runtime, url) {
  const cleanUrl = cleanText(url);
  if (!runtime || !runtime.page) {
    throw new Error("Browser session is not ready.");
  }
  if (!isValidEmployerUrl(cleanUrl)) {
    throw new Error("Navigation requires a valid external employer URL.");
  }
  await runtime.page.goto(cleanUrl, {
    waitUntil: "domcontentloaded",
    timeout: Number(process.env.SFFC_REMOTE_BROWSER_NAVIGATION_TIMEOUT_MS || 60000),
  });
  return getPageSnapshot(runtime);
}

export async function getPageSnapshot(runtime) {
  if (!runtime || !runtime.page) {
    throw new Error("Browser session is not ready.");
  }
  return {
    finalUrl: cleanText(runtime.page.url()),
    title: cleanText(await runtime.page.title().catch(() => "")),
  };
}

export async function screenshotSession(runtime) {
  if (!runtime || !runtime.page) {
    throw new Error("Browser session is not ready.");
  }
  return runtime.page.screenshot({
    type: "png",
    fullPage: false,
    captureBeyondViewport: false,
  });
}

export async function clickSession(runtime, x, y) {
  if (!runtime || !runtime.page) {
    throw new Error("Browser session is not ready.");
  }
  await runtime.page.mouse.click(Number(x), Number(y));
  return getPageSnapshot(runtime);
}

export async function typeSession(runtime, text) {
  if (!runtime || !runtime.page) {
    throw new Error("Browser session is not ready.");
  }
  const value = String(text ?? "").slice(0, Number(process.env.SFFC_REMOTE_BROWSER_MAX_TYPE_CHARS || 20000));
  await runtime.page.keyboard.type(value, { delay: 20 });
  return getPageSnapshot(runtime);
}

export async function pressKeySession(runtime, key) {
  if (!runtime || !runtime.page) {
    throw new Error("Browser session is not ready.");
  }
  await runtime.page.keyboard.press(cleanText(key || "Enter"));
  return getPageSnapshot(runtime);
}

export async function scrollSession(runtime, deltaX, deltaY) {
  if (!runtime || !runtime.page) {
    throw new Error("Browser session is not ready.");
  }
  await runtime.page.mouse.wheel({
    deltaX: Number(deltaX) || 0,
    deltaY: Number(deltaY) || 0,
  });
  return getPageSnapshot(runtime);
}
