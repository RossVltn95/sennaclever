import fs from "node:fs/promises";
import fsSync from "node:fs";
import http from "node:http";
import os from "node:os";
import path from "node:path";
import puppeteer from "puppeteer";

const ajaxUrl = process.env.SFFC_WP_AJAX_URL || "";
const workerToken = process.env.SFFC_APPLICATION_WORKER_TOKEN || "";
const workerId = process.env.SFFC_WORKER_ID || `sffc-worker-${os.hostname()}`;
const pollIntervalMs = Number(process.env.SFFC_WORKER_POLL_INTERVAL_MS || 15000);
const allowFinalSubmit = process.env.SFFC_WORKER_ALLOW_FINAL_SUBMIT === "1";
const healthPort = Number(process.env.PORT || 0);
let lastHeartbeat = new Date().toISOString();
let lastTaskStatus = "idle";

function isUsableBrowserExecutable(candidate) {
  if (!candidate || !fsSync.existsSync(candidate)) {
    return false;
  }
  try {
    const stat = fsSync.statSync(candidate);
    if (!stat.isFile() && !stat.isSymbolicLink()) {
      return false;
    }
    const sample = fsSync.readFileSync(candidate, "utf8").slice(0, 2000);
    if (/snap install chromium|requires the chromium snap/i.test(sample)) {
      return false;
    }
  } catch (error) {
    return true;
  }
  return true;
}

function getBrowserExecutablePath() {
  const configuredPath = process.env.PUPPETEER_EXECUTABLE_PATH || "";
  if (isUsableBrowserExecutable(configuredPath)) {
    return configuredPath;
  }

  const candidates = [
    "/root/.nix-profile/bin/chromium",
    "/nix/var/nix/profiles/default/bin/chromium",
    ...String(process.env.PATH || "")
      .split(path.delimiter)
      .filter(Boolean)
      .flatMap((dir) => [
        path.join(dir, "chromium"),
        path.join(dir, "google-chrome"),
        path.join(dir, "google-chrome-stable"),
      ]),
    "/usr/bin/chromium",
    "/usr/bin/google-chrome",
    "/usr/bin/google-chrome-stable",
    "/usr/bin/chromium-browser",
  ];
  return candidates.find(isUsableBrowserExecutable);
}

function requireConfig() {
  if (!ajaxUrl || !workerToken) {
    throw new Error("SFFC_WP_AJAX_URL and SFFC_APPLICATION_WORKER_TOKEN are required.");
  }
}

async function postAjax(action, fields) {
  const body = new FormData();
  body.append("action", action);
  body.append("worker_token", workerToken);
  Object.entries(fields || {}).forEach(([key, value]) => {
    body.append(key, typeof value === "string" ? value : JSON.stringify(value));
  });

  const response = await fetch(ajaxUrl, {
    method: "POST",
    body,
  });
  const payload = await response.json().catch(() => null);
  if (!response.ok || !payload || !payload.success) {
    const message =
      payload && payload.data && payload.data.message
        ? payload.data.message
        : `WordPress AJAX ${action} failed with ${response.status}`;
    throw new Error(message);
  }
  return payload.data || {};
}

async function claimTask() {
  const data = await postAjax("sffc_crm_application_worker_claim", {
    worker_id: workerId,
  });
  return data.task || null;
}

async function completeTask(taskUuid, status, result) {
  await postAjax("sffc_crm_application_worker_complete", {
    task_uuid: taskUuid,
    status,
    last_error: result.last_error || "",
    evidence_url: result.evidence_url || "",
    screenshot_url: result.screenshot_url || "",
    result_payload: result,
  });
}

function splitName(fullName) {
  const parts = String(fullName || "").trim().split(/\s+/).filter(Boolean);
  return {
    firstName: parts[0] || "",
    lastName: parts.length > 1 ? parts.slice(1).join(" ") : "",
  };
}

async function downloadFile(url, fileName) {
  if (!url) {
    return "";
  }
  const response = await fetch(url);
  if (!response.ok) {
    throw new Error(`Could not download CV file: ${response.status}`);
  }
  const buffer = Buffer.from(await response.arrayBuffer());
  const dir = await fs.mkdtemp(path.join(os.tmpdir(), "sffc-application-"));
  const safeName = String(fileName || "cv.pdf").replace(/[^a-z0-9._-]/gi, "_");
  const filePath = path.join(dir, safeName);
  await fs.writeFile(filePath, buffer);
  return filePath;
}

async function fillBySelectors(page, selectors, value) {
  if (!value) {
    return false;
  }
  for (const selector of selectors) {
    const field = await page.$(selector);
    if (!field) {
      continue;
    }
    await field.click({ clickCount: 3 }).catch(() => {});
    await field.type(String(value), { delay: 10 }).catch(() => {});
    return true;
  }
  return false;
}

async function fillByLabelText(page, labelPatterns, value) {
  if (!value) {
    return false;
  }
  return page.evaluate(
    ({ labels, text }) => {
      const matches = (candidate) =>
        labels.some((pattern) => new RegExp(pattern, "i").test(candidate || ""));
      const controls = Array.from(document.querySelectorAll("input, textarea"));
      for (const control of controls) {
        const id = control.getAttribute("id");
        const aria = control.getAttribute("aria-label") || "";
        const name = control.getAttribute("name") || "";
        const placeholder = control.getAttribute("placeholder") || "";
        const label = id
          ? document.querySelector(`label[for="${CSS.escape(id)}"]`)?.textContent || ""
          : "";
        const wrapperText = control.closest("label, div, fieldset")?.textContent || "";
        if (matches(`${aria} ${name} ${placeholder} ${label} ${wrapperText}`)) {
          control.focus();
          control.value = text;
          control.dispatchEvent(new Event("input", { bubbles: true }));
          control.dispatchEvent(new Event("change", { bubbles: true }));
          return true;
        }
      }
      return false;
    },
    { labels: labelPatterns, text: String(value) }
  );
}

async function uploadResume(page, cvPath) {
  if (!cvPath) {
    return false;
  }
  const inputs = await page.$$("input[type=file]");
  for (const input of inputs) {
    await input.uploadFile(cvPath);
    return true;
  }
  return false;
}

async function clickLikelyApplyButton(page) {
  const beforeUrl = page.url();
  const clicked = await page.evaluate(() => {
    const buttons = Array.from(document.querySelectorAll("button, input[type=submit]"));
    const target = buttons.find((button) =>
      /submit application|send application|apply now|submit/i.test(
        `${button.textContent || ""} ${button.getAttribute("value") || ""} ${button.getAttribute("aria-label") || ""}`
      )
    );
    if (!target) {
      return false;
    }
    target.click();
    return true;
  });
  if (clicked) {
    await page.waitForNetworkIdle({ idleTime: 1200, timeout: 10000 }).catch(() => {});
  }
  return {
    clicked,
    beforeUrl,
    afterUrl: page.url(),
  };
}

async function processTask(task) {
  const url = task.application_workspace_url || task.application_url;
  const candidate = {
    name: task.candidate_name || "",
    email: task.candidate_email || "",
    phone: task.candidate_phone || "",
  };
  const { firstName, lastName } = splitName(candidate.name);
  const cvPath = await downloadFile(task.cv_file_url, task.cv_file_name);
  const executablePath = getBrowserExecutablePath();
  const browser = await puppeteer.launch({
    headless: "new",
    executablePath: executablePath || undefined,
    args: ["--no-sandbox", "--disable-setuid-sandbox"],
  });

  try {
    const page = await browser.newPage();
    await page.setViewport({ width: 1440, height: 1200 });
    await page.goto(url, { waitUntil: "networkidle2", timeout: 60000 });

    await fillBySelectors(page, [
      'input[name="first_name"]',
      'input[name="firstName"]',
      'input[name*="first" i]',
    ], firstName);
    await fillBySelectors(page, [
      'input[name="last_name"]',
      'input[name="lastName"]',
      'input[name*="last" i]',
    ], lastName);
    await fillBySelectors(page, [
      'input[type="email"]',
      'input[name="email"]',
      'input[name*="email" i]',
    ], candidate.email);
    await fillBySelectors(page, [
      'input[type="tel"]',
      'input[name*="phone" i]',
      'input[name*="mobile" i]',
    ], candidate.phone);

    await fillByLabelText(page, ["first name", "given name"], firstName);
    await fillByLabelText(page, ["last name", "family name", "surname"], lastName);
    await fillByLabelText(page, ["email"], candidate.email);
    await fillByLabelText(page, ["phone", "mobile"], candidate.phone);
    const uploadedResume = await uploadResume(page, cvPath);

    const screenshotPath = path.join(os.tmpdir(), `${task.task_uuid}.png`);
    await page.screenshot({ path: screenshotPath, fullPage: true });

    let clickedSubmit = false;
    let submitResult = { clicked: false, beforeUrl: page.url(), afterUrl: page.url() };
    if (allowFinalSubmit) {
      submitResult = await clickLikelyApplyButton(page);
      clickedSubmit = submitResult.clicked;
    }

    return {
      provider: task.provider || "",
      url,
      allow_final_submit: allowFinalSubmit,
      clicked_submit: clickedSubmit,
      submit_before_url: submitResult.beforeUrl,
      submit_after_url: submitResult.afterUrl,
      uploaded_resume: uploadedResume,
      page_title: await page.title().catch(() => ""),
      final_url: page.url(),
      local_screenshot_path: screenshotPath,
      status: allowFinalSubmit && clickedSubmit ? "submitted" : "dry_run_ready",
    };
  } finally {
    await browser.close();
  }
}

async function runOnce() {
  lastHeartbeat = new Date().toISOString();
  const task = await claimTask();
  if (!task) {
    lastTaskStatus = "idle";
    return;
  }

  try {
    lastTaskStatus = `processing:${task.task_uuid}`;
    const result = await processTask(task);
    await completeTask(task.task_uuid, result.status, result);
    lastTaskStatus = `${task.task_uuid}:${result.status}`;
    console.log(`[${new Date().toISOString()}] ${task.task_uuid} ${result.status}`);
  } catch (error) {
    await completeTask(task.task_uuid, "failed", {
      last_error: error && error.message ? error.message : String(error),
    }).catch(() => {});
    lastTaskStatus = `${task.task_uuid}:failed`;
    console.error(`[${new Date().toISOString()}] ${task.task_uuid} failed`, error);
  }
}

function startHealthServer() {
  if (!healthPort) {
    return;
  }
  const server = http.createServer((request, response) => {
    if (request.url === "/health" || request.url === "/") {
      response.writeHead(200, { "content-type": "application/json" });
      response.end(
        JSON.stringify({
          ok: true,
          worker_id: workerId,
          last_heartbeat: lastHeartbeat,
          last_task_status: lastTaskStatus,
          allow_final_submit: allowFinalSubmit,
        })
      );
      return;
    }
    response.writeHead(404, { "content-type": "application/json" });
    response.end(JSON.stringify({ ok: false }));
  });
  server.listen(healthPort, () => {
    console.log(`SFFC application worker health server listening on ${healthPort}`);
  });
}

async function main() {
  requireConfig();
  console.log(
    `SFFC application worker browser path: ${getBrowserExecutablePath() || "puppeteer-managed"}`
  );
  startHealthServer();
  while (true) {
    await runOnce();
    await new Promise((resolve) => setTimeout(resolve, pollIntervalMs));
  }
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
