import fs from "node:fs/promises";
import os from "node:os";
import path from "node:path";
import puppeteer from "puppeteer";

const roleUrl = process.env.SFFC_WORKDAY_TEST_URL || process.argv[2] || "";
const timeoutMs = Number(process.env.SFFC_WORKDAY_INSPECT_TIMEOUT_MS || 60000);

if (!roleUrl) {
  console.error("Usage: SFFC_WORKDAY_TEST_URL=https://... node scripts/inspect-workday-application-flow.js");
  process.exit(1);
}

const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();

async function collectPageState(page, label) {
  const state = await page.evaluate(() => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const visible = (element) => {
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.display !== "none" && style.visibility !== "hidden" && rect.width > 0 && rect.height > 0;
    };
    const controls = Array.from(document.querySelectorAll("input, textarea, select")).filter(visible);
    const buttons = Array.from(document.querySelectorAll("button, a, [role='button']"))
      .filter(visible)
      .map((element) => ({
        text: clean(`${element.textContent || ""} ${element.getAttribute("aria-label") || ""}`),
        href: element.href || "",
        automation_id: element.getAttribute("data-automation-id") || "",
        role: element.getAttribute("role") || "",
      }))
      .filter((item) => item.text || item.href || item.automation_id);
    const automation = Array.from(document.querySelectorAll("[data-automation-id]"))
      .filter(visible)
      .map((element) => ({
        tag: element.tagName.toLowerCase(),
        id: element.getAttribute("data-automation-id") || "",
        text: clean(element.textContent || "").slice(0, 160),
      }))
      .filter((item) => item.id || item.text);
    const fields = controls.map((field) => ({
      tag: field.tagName.toLowerCase(),
      type: field.getAttribute("type") || "",
      name: field.getAttribute("name") || "",
      id: field.getAttribute("id") || "",
      autocomplete: field.getAttribute("autocomplete") || "",
      automation_id: field.getAttribute("data-automation-id") || "",
      aria_label: field.getAttribute("aria-label") || "",
      placeholder: field.getAttribute("placeholder") || "",
      required: field.required || field.getAttribute("aria-required") === "true",
    }));
    const links = Array.from(document.querySelectorAll("a[href]"))
      .filter(visible)
      .map((link) => ({ text: clean(link.textContent || ""), href: link.href }))
      .filter((link) => link.text || link.href);

    return {
      title: document.title || "",
      url: location.href,
      body_sample: clean(document.body?.innerText || "").slice(0, 1200),
      field_count: fields.length,
      file_input_count: fields.filter((field) => field.type === "file").length,
      buttons: buttons.slice(0, 80),
      fields: fields.slice(0, 120),
      automation: automation.slice(0, 160),
      links: links.slice(0, 80),
    };
  });
  return { label, ...state };
}

async function clickFirstMatching(page, patterns) {
  return page.evaluate((patternSources) => {
    const patterns = patternSources.map((source) => new RegExp(source, "i"));
    const visible = (element) => {
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.display !== "none" && style.visibility !== "hidden" && rect.width > 0 && rect.height > 0;
    };
    const candidates = Array.from(document.querySelectorAll("button, a, [role='button'], input[type='button'], input[type='submit']"));
    const target = candidates.find((element) => {
      if (!visible(element)) {
        return false;
      }
      const text = `${element.textContent || ""} ${element.getAttribute("aria-label") || ""} ${element.getAttribute("value") || ""} ${
        element.getAttribute("data-automation-id") || ""
      }`;
      return patterns.some((pattern) => pattern.test(text));
    });
    if (!target) {
      return false;
    }
    target.scrollIntoView({ block: "center", inline: "center" });
    target.click();
    return true;
  }, patterns.map((pattern) => pattern.source));
}

async function main() {
  const watchdog = setTimeout(() => {
    console.error(`Workday inspection timed out after ${timeoutMs}ms.`);
    process.exit(124);
  }, timeoutMs + 15000);
  const outputDir = await fs.mkdtemp(path.join(os.tmpdir(), "sffc-workday-inspect-"));
  const executablePath =
    process.env.PUPPETEER_EXECUTABLE_PATH ||
    process.env.PUPPETEER_BROWSER_PATH ||
    (process.platform === "darwin" && process.arch === "x64"
      ? path.join(
          os.homedir(),
          ".cache/puppeteer/chrome-headless-shell/mac-152.0.7977.54/chrome-headless-shell-mac-x64/chrome-headless-shell"
        )
      : "") ||
    (process.platform === "darwin" && process.arch === "arm64"
      ? path.join(
          os.homedir(),
          ".cache/puppeteer/chrome-headless-shell/mac_arm-137.0.7151.119/chrome-headless-shell-mac-arm64/chrome-headless-shell"
        )
      : "") ||
    (process.platform === "darwin" ? "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" : "");
  const browser = await puppeteer.launch({
    headless: "new",
    executablePath: executablePath || undefined,
    timeout: 90000,
    args: ["--no-sandbox", "--disable-setuid-sandbox"],
  });

  const network = [];
  const interceptedPosts = [];
  const states = [];
  const page = await browser.newPage();
  page.setDefaultTimeout(Math.min(timeoutMs, 15000));
  page.setDefaultNavigationTimeout(timeoutMs);
  page.on("requestfinished", (request) => {
    const url = request.url();
    if (/workday|wday|candidate|apply|resume|questionnaire|jobapplication/i.test(url)) {
      network.push({ method: request.method(), url: url.slice(0, 500) });
    }
  });
  page.on("requestfailed", (request) => {
    const url = request.url();
    if (/workday|wday|candidate|apply|resume|questionnaire|jobapplication/i.test(url)) {
      network.push({ method: request.method(), url: url.slice(0, 500), failed: request.failure()?.errorText || true });
    }
  });

  try {
    await page.setViewport({ width: 1440, height: 1200 });
    await page.goto(roleUrl, { waitUntil: "domcontentloaded", timeout: timeoutMs });
    await page.waitForNetworkIdle({ idleTime: 1200, timeout: 30000 }).catch(() => {});
    await page.waitForFunction(
      () => !/Loading\s*$/i.test(document.body?.innerText || "") || /apply|sign in|assistant vice president|valuation/i.test(document.body?.innerText || ""),
      { timeout: 30000 }
    ).catch(() => {});
    states.push(await collectPageState(page, "job_details_loaded"));
    await page.screenshot({ path: path.join(outputDir, "01-job-details.png"), fullPage: true }).catch(() => {});

    const clickedApply = await clickFirstMatching(page, [/\bapply\b/, /start application/, /applyButton/]).catch(() => false);
    await page.waitForNetworkIdle({ idleTime: 1200, timeout: 30000 }).catch(() => {});
    await new Promise((resolve) => setTimeout(resolve, 2000));
    states.push({ label: "clicked_apply", clicked: clickedApply });
    states.push(await collectPageState(page, "after_apply_click"));
    await page.screenshot({ path: path.join(outputDir, "02-after-apply.png"), fullPage: true }).catch(() => {});

    const clickedSignIn = await clickFirstMatching(page, [/sign in/, /utilityButtonSignIn/, /log in/, /login/]).catch(() => false);
    if (clickedSignIn) {
      await page.waitForNetworkIdle({ idleTime: 1200, timeout: 30000 }).catch(() => {});
      await new Promise((resolve) => setTimeout(resolve, 1500));
      states.push({ label: "clicked_sign_in", clicked: true });
      states.push(await collectPageState(page, "after_sign_in_click"));
      await page.screenshot({ path: path.join(outputDir, "03-sign-in.png"), fullPage: true }).catch(() => {});
    }

    const clickedCreate = await clickFirstMatching(page, [/create account/, /createAccount/, /sign up/, /register/]).catch(() => false);
    if (clickedCreate) {
      await page.waitForNetworkIdle({ idleTime: 1200, timeout: 30000 }).catch(() => {});
      await new Promise((resolve) => setTimeout(resolve, 1500));
      states.push({ label: "clicked_create_account", clicked: true });
      states.push(await collectPageState(page, "after_create_account_click"));
      await page.screenshot({ path: path.join(outputDir, "04-create-account.png"), fullPage: true }).catch(() => {});

      if (process.env.SFFC_WORKDAY_INTERCEPT_CREATE === "1") {
        await page.setRequestInterception(true);
        page.on("request", (request) => {
          if (request.method() === "POST") {
            interceptedPosts.push({
              method: request.method(),
              url: request.url().slice(0, 500),
              post_data_length: (request.postData() || "").length,
              post_data_sample: clean(request.postData() || "").slice(0, 500),
            });
            request.abort("aborted").catch(() => {});
            return;
          }
          request.continue().catch(() => {});
        });
        const email = `sffc-workday-test-${Date.now()}@example.com`;
        const password = `SffcTest!${Date.now()}`;
        await page.locator('input[data-automation-id="email"]').fill(email).catch(() => {});
        await page.locator('input[data-automation-id="password"]').fill(password).catch(() => {});
        await page.locator('input[data-automation-id="verifyPassword"]').fill(password).catch(() => {});
        await page.locator('button[data-automation-id="createAccountSubmitButton"]').click().catch(() => {});
        await new Promise((resolve) => setTimeout(resolve, 3000));
        states.push(await collectPageState(page, "after_intercepted_create_submit"));
      }
    }

    const result = {
      role_url: roleUrl,
      final_url: page.url(),
      output_dir: outputDir,
      states,
      intercepted_posts: interceptedPosts,
      network: network.slice(-120),
    };
    await fs.writeFile(path.join(outputDir, "workday-flow-report.json"), JSON.stringify(result, null, 2));
    console.log(JSON.stringify(result, null, 2));
  } finally {
    await browser.close().catch(() => {});
    clearTimeout(watchdog);
  }
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
