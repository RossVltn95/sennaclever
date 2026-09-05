import fs from "node:fs/promises";
import fsSync from "node:fs";
import { execFile } from "node:child_process";
import http from "node:http";
import os from "node:os";
import path from "node:path";
import { promisify } from "node:util";
import { fileURLToPath } from "node:url";
import puppeteer from "puppeteer";

const ajaxUrl = process.env.SFFC_WP_AJAX_URL || "";
const workerToken = process.env.SFFC_APPLICATION_WORKER_TOKEN || "";
const workerId = process.env.SFFC_WORKER_ID || `sffc-worker-${os.hostname()}`;
const pollIntervalMs = Number(process.env.SFFC_WORKER_POLL_INTERVAL_MS || 15000);
const verificationWaitMs = Number(process.env.SFFC_WORKER_VERIFICATION_WAIT_MS || 10 * 60 * 1000);
const browserLaunchTimeoutMs = Number(process.env.SFFC_BROWSER_LAUNCH_TIMEOUT_MS || 90000);
const navigationTimeoutMs = Number(process.env.SFFC_BROWSER_NAVIGATION_TIMEOUT_MS || 90000);
const workdayFetchTimeoutMs = Number(process.env.SFFC_WORKDAY_FETCH_TIMEOUT_MS || 30000);
const workdaySchemaFetchTimeoutMs = Number(process.env.SFFC_WORKDAY_SCHEMA_FETCH_TIMEOUT_MS || 8000);
const workdayShellTimeoutMs = Number(process.env.SFFC_WORKDAY_SHELL_TIMEOUT_MS || 30000);
const allowFinalSubmit = process.env.SFFC_WORKER_ALLOW_FINAL_SUBMIT === "1";
const allowWorkdayAccountCreation = process.env.SFFC_WORKER_ALLOW_WORKDAY_ACCOUNT_CREATION === "1";
const allowSuccessFactorsAccountCreation = process.env.SFFC_WORKER_ALLOW_SUCCESSFACTORS_ACCOUNT_CREATION === "1";
const interceptFinalSubmit = process.env.SFFC_WORKER_INTERCEPT_FINAL_SUBMIT === "1";
const disableVerificationCallback = process.env.SFFC_WORKER_DISABLE_VERIFICATION_CALLBACK === "1";
const browserHeadless = process.env.SFFC_BROWSER_HEADLESS === "0" ? false : "new";
const browserPipe = process.env.SFFC_BROWSER_PIPE === "1";
const browserUserDataDir = cleanText(process.env.SFFC_BROWSER_USER_DATA_DIR || "");
const workdayStopAfterStage = cleanText(process.env.SFFC_WORKDAY_STOP_AFTER_STAGE || "");
const healthPort = Number(process.env.PORT || 0);
const execFileAsync = promisify(execFile);
let lastHeartbeat = new Date().toISOString();
let lastTaskStatus = "idle";

function debugLog(...parts) {
  if (process.env.SFFC_WORKER_DEBUG === "1") {
    console.log("[sffc-worker-debug]", ...parts);
  }
}

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
    ...(process.platform === "darwin"
      ? ["/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"]
      : []),
    "/usr/bin/google-chrome-stable",
    "/usr/bin/google-chrome",
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
    "/usr/bin/chromium-browser",
  ];
  return candidates.find(isUsableBrowserExecutable);
}

function attachPageDiagnostics(page) {
  const diagnostics = {
    console: [],
    page_errors: [],
    request_failed: [],
    response_statuses: [],
  };
  const pushLimited = (list, entry, limit = 120) => {
    list.push({ at: new Date().toISOString(), ...entry });
    if (list.length > limit) {
      list.splice(0, list.length - limit);
    }
  };
  page.on("console", (message) => {
    const type = message.type();
    if (!/^(error|warning|warn|assert)$/i.test(type)) {
      return;
    }
    pushLimited(diagnostics.console, {
      type,
      text: cleanText(message.text()).slice(0, 1000),
      location: message.location(),
    });
  });
  page.on("pageerror", (error) => {
    pushLimited(diagnostics.page_errors, {
      message: error && error.message ? error.message : String(error),
      stack: error && error.stack ? String(error.stack).slice(0, 1600) : "",
    });
  });
  page.on("requestfailed", (request) => {
    const url = request.url();
    if (!/workday|wday|candidate|apply|resume|questionnaire|jobapplication|auth|account/i.test(url)) {
      return;
    }
    pushLimited(diagnostics.request_failed, {
      method: request.method(),
      url: url.slice(0, 800),
      failure: request.failure()?.errorText || "",
    });
  });
  page.on("response", (response) => {
    const status = response.status();
    const url = response.url();
    if (
      status < 400 ||
      !/workday|wday|candidate|apply|resume|questionnaire|jobapplication|auth|account/i.test(url)
    ) {
      return;
    }
    pushLimited(diagnostics.response_statuses, {
      status,
      url: url.slice(0, 800),
      request_method: response.request().method(),
    });
  });
  return diagnostics;
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

async function getTaskUpdate(taskUuid) {
  const data = await postAjax("sffc_crm_application_worker_get_task", {
    task_uuid: taskUuid,
  });
  return data.task || null;
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
  if (/^file:\/\//i.test(url) || (!/^https?:\/\//i.test(url) && fsSync.existsSync(url))) {
    const sourcePath = /^file:\/\//i.test(url) ? fileURLToPath(url) : url;
    const dir = await fs.mkdtemp(path.join(os.tmpdir(), "sffc-application-"));
    const safeName = String(fileName || path.basename(sourcePath) || "cv.pdf").replace(/[^a-z0-9._-]/gi, "_");
    const filePath = path.join(dir, safeName);
    await fs.copyFile(sourcePath, filePath);
    return filePath;
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

async function extractTextFromLocalCvFile(filePath) {
  if (!filePath || !/\.pdf$/i.test(filePath) || !fsSync.existsSync(filePath)) {
    return "";
  }
  try {
    const { stdout } = await execFileAsync("pdftotext", [filePath, "-"], {
      timeout: 15000,
      maxBuffer: 2 * 1024 * 1024,
    });
    return String(stdout || "")
      .replace(/[^\S\r\n]+/g, " ")
      .replace(/\n{3,}/g, "\n\n")
      .trim();
  } catch {
    return "";
  }
}

async function fillBySelectors(page, selectors, value) {
  if (!value) {
    return false;
  }
  for (const selector of selectors) {
    const fields = await page.$$(selector);
    let field = null;
    for (const candidate of fields) {
      const visible = await candidate.evaluate((element) => {
        const style = window.getComputedStyle(element);
        const rect = element.getBoundingClientRect();
        return (
          !element.disabled &&
          element.type !== "hidden" &&
          style.visibility !== "hidden" &&
          style.display !== "none" &&
          rect.width > 0 &&
          rect.height > 0
        );
      });
      if (visible) {
        field = candidate;
        break;
      }
    }
    if (!field) {
      continue;
    }
    const current = await field.evaluate((element) => String(element.value || "").trim()).catch(() => "");
    const wanted = String(value).trim();
    if (current && current.toLowerCase() === wanted.toLowerCase()) {
      return true;
    }
    if (await fillInputHandleWithVerification(page, field, wanted)) {
      return true;
    }
  }
  return false;
}

async function fillInputHandleWithVerification(page, field, value) {
  const text = String(value || "");
  if (!field || !text) {
    return false;
  }
  await field.evaluate((element) => element.scrollIntoView({ block: "center", inline: "nearest" })).catch(() => {});
  await field
    .evaluate((element, nextValue) => {
      const prototype =
        element instanceof HTMLTextAreaElement ? HTMLTextAreaElement.prototype : HTMLInputElement.prototype;
      const descriptor = Object.getOwnPropertyDescriptor(prototype, "value");
      element.focus();
      if (descriptor && descriptor.set) {
        descriptor.set.call(element, "");
      } else {
        element.value = "";
      }
      element.dispatchEvent(new InputEvent("input", { bubbles: true, inputType: "deleteContentBackward", data: null }));
      if (descriptor && descriptor.set) {
        descriptor.set.call(element, nextValue);
      } else {
        element.value = nextValue;
      }
      element.dispatchEvent(new InputEvent("input", { bubbles: true, inputType: "insertText", data: nextValue }));
      element.dispatchEvent(new Event("change", { bubbles: true }));
      element.dispatchEvent(new Event("blur", { bubbles: true }));
    }, text)
    .catch(() => {});
  await new Promise((resolve) => setTimeout(resolve, 250));
  return field
    .evaluate((element, expected) => String(element.value || "") === String(expected), text)
    .catch(() => false);
}

async function fillByLabelText(page, labelPatterns, value) {
  if (!value) {
    return false;
  }
  return page.evaluate(
    ({ labels, text }) => {
      const matches = (candidate) =>
        labels.some((pattern) => new RegExp(pattern, "i").test(candidate || ""));
      const setNativeValue = (element, value) => {
        const prototype = element instanceof HTMLTextAreaElement ? HTMLTextAreaElement.prototype : HTMLInputElement.prototype;
        const descriptor = Object.getOwnPropertyDescriptor(prototype, "value");
        element.focus();
        if (descriptor && descriptor.set) {
          descriptor.set.call(element, value);
        } else {
          element.value = value;
        }
        element.dispatchEvent(new Event("input", { bubbles: true }));
        element.dispatchEvent(new Event("change", { bubbles: true }));
      };
      const isVisible = (control) => {
        const style = window.getComputedStyle(control);
        const rect = control.getBoundingClientRect();
        return (
          !control.disabled &&
          control.type !== "hidden" &&
          style.visibility !== "hidden" &&
          style.display !== "none" &&
          rect.width > 0 &&
          rect.height > 0
        );
      };
      const controls = Array.from(document.querySelectorAll("input, textarea"));
      for (const control of controls) {
        if (!isVisible(control)) {
          continue;
        }
        const id = control.getAttribute("id");
        const aria = control.getAttribute("aria-label") || "";
        const name = control.getAttribute("name") || "";
        const placeholder = control.getAttribute("placeholder") || "";
        const label = id
          ? document.querySelector(`label[for="${CSS.escape(id)}"]`)?.textContent || ""
          : "";
        const wrapperText = control.closest("label, div, fieldset")?.textContent || "";
        if (matches(`${aria} ${name} ${placeholder} ${label} ${wrapperText}`)) {
          setNativeValue(control, text);
          return true;
        }
      }
      return false;
    },
    { labels: labelPatterns, text: String(value) }
  );
}

async function discoverWorkableLiveSchema(page) {
  return page.evaluate(() => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const compact = (value) => clean(value).toLowerCase().replace(/[^a-z0-9]+/g, "");
    const isVisible = (element) => {
      if (!element) return false;
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
    };
    const getLabelElements = (control) => {
      const elements = [];
      const id = control.getAttribute("id") || "";
      if (id) {
        const explicit = document.querySelector(`label[for="${CSS.escape(id)}"]`);
        if (explicit) elements.push(explicit);
      }
      const labelledBy = control.getAttribute("aria-labelledby") || "";
      labelledBy.split(/\s+/).forEach((part) => {
        const element = document.getElementById(part);
        if (element) elements.push(element);
      });
      return elements;
    };
    const directLabel = (control) => {
      return clean(
        [
          ...getLabelElements(control).map((element) => element.textContent || ""),
          control.getAttribute("aria-label") || "",
          control.getAttribute("placeholder") || "",
        ].join(" ")
      ).replace(/\*+$/, "");
    };
    const fieldScope = (control) => {
      let current = control;
      for (let depth = 0; depth < 9 && current && current.parentElement; depth += 1) {
        current = current.parentElement;
        const text = clean(current.textContent || "");
        const inputs = current.querySelectorAll("input, textarea, select, [role='combobox']");
        if (
          text &&
          inputs.length &&
          (current.matches("fieldset, section, li") ||
            current.querySelector("label, [class*='label' i], [id*='label' i]") ||
            /\*/.test(text))
        ) {
          return current;
        }
      }
      return control.closest("fieldset, section, li, div") || control.parentElement || control;
    };
    const scopeLabel = (scope, control) => {
      const direct = directLabel(control);
      if (direct && !/^select\b/i.test(direct)) {
        return direct;
      }
      const label = Array.from(scope.querySelectorAll("label, [class*='label' i], [id*='label' i]"))
        .map((node) => clean(node.textContent || ""))
        .filter((text) => text && !/^svg/i.test(text) && !/^select\b/i.test(text))
        .sort((a, b) => a.length - b.length)[0];
      if (label) {
        return label.replace(/\*+$/, "");
      }
      const lines = clean(scope.textContent || "")
        .split(/(?=\*)|(?:\s{2,})/)
        .map(clean)
        .filter(Boolean);
      return (lines[0] || direct || control.getAttribute("name") || control.getAttribute("id") || "Required field").replace(/\*+$/, "");
    };
    const valueFilled = (control, scope) => {
      if (control.type === "file") {
        return Boolean(control.files && control.files.length);
      }
      if (control.type === "radio" || control.type === "checkbox") {
        const name = control.getAttribute("name") || "";
        return name ? Boolean(document.querySelector(`[name="${CSS.escape(name)}"]:checked`)) : Boolean(control.checked);
      }
      if (control.tagName === "SELECT") {
        return clean(control.value || "") !== "";
      }
      if (control.getAttribute("role") === "combobox" || control.getAttribute("aria-autocomplete") === "list") {
        const visibleValue = clean(control.value || "");
        const hiddenValue = Array.from(scope.querySelectorAll("input[type='hidden'], input[aria-hidden='true']"))
          .map((input) => clean(input.value || ""))
          .find(Boolean);
        return Boolean((visibleValue && !/^select/i.test(visibleValue)) || hiddenValue);
      }
      return clean(control.value || "") !== "";
    };
    const optionTextForControl = (control, index, groupControls) => {
      const id = control.getAttribute("id") || "";
      const explicit = id ? document.querySelector(`label[for="${CSS.escape(id)}"]`) : null;
      const wrapper =
        explicit ||
        (id && document.getElementById(`wrapper_${id}`)) ||
        control.closest("label") ||
        control.closest("[role='radio']") ||
        control.closest("[role='checkbox']") ||
        control.parentElement;
      const text = clean(`${explicit?.textContent || ""} ${wrapper?.textContent || ""} ${control.getAttribute("aria-label") || ""}`);
      if (text && !/svg/i.test(text)) {
        return text;
      }
      const scope = fieldScope(control);
      const lines = clean(scope.textContent || "").split(/\n|(?<=\?)\s+|(?<=\*)\s+/).map(clean).filter(Boolean);
      const yesNo = ["Yes", "No", "I don't know"];
      if (groupControls.length <= yesNo.length && yesNo[index]) {
        return yesNo[index];
      }
      return clean(control.value || `Option ${index + 1}`);
    };
    const controls = Array.from(document.querySelectorAll("input, textarea, select, [role='combobox']"))
      .filter((control) => {
        if (control.disabled) return false;
        if (control.type === "hidden") return false;
        if (control.getAttribute("aria-hidden") === "true" && !document.getElementById(`input_${control.name || ""}_input`)) return false;
        if (control.type === "file") return true;
        return isVisible(control);
      });
    const groups = new Map();
    controls.forEach((control) => {
      const name = control.getAttribute("name") || control.getAttribute("id") || directLabel(control);
      if (!name) return;
      const type = (control.getAttribute("type") || control.tagName || control.getAttribute("role") || "").toLowerCase();
      const key = type === "radio" ? `radio:${name}` : `${name}:${groups.size}`;
      if (type === "radio") {
        if (!groups.has(key)) groups.set(key, []);
        groups.get(key).push(control);
      } else {
        groups.set(key, [control]);
      }
    });
    const questions = [];
    for (const groupControls of groups.values()) {
      const first = groupControls[0];
      const scope = fieldScope(first);
      const name = first.getAttribute("name") || first.getAttribute("id") || "";
      const type = (first.getAttribute("type") || first.tagName || first.getAttribute("role") || "").toLowerCase();
      const role = first.getAttribute("role") || "";
      const required =
        groupControls.some((control) => control.required || control.getAttribute("aria-required") === "true") ||
        /\*/.test(clean(scope.textContent || "")) ||
        /required/i.test(clean(scope.textContent || ""));
      const isDropdown =
        first.tagName === "SELECT" ||
        role === "combobox" ||
        first.getAttribute("aria-autocomplete") === "list" ||
        Boolean(name && document.getElementById(`input_${name}_input`));
      const liveType =
        type === "radio"
          ? "radio"
          : type === "checkbox"
            ? "checkbox"
            : type === "file"
              ? "file"
              : isDropdown
                ? "select"
                : /date of birth|birth date|\bdob\b/i.test(clean(scope.textContent || ""))
                  ? "date"
                  : first.tagName === "TEXTAREA"
                    ? "textarea"
                    : "text";
      let options = [];
      if (liveType === "radio" || liveType === "checkbox") {
        options = groupControls.map((control, index) => optionTextForControl(control, index, groupControls));
      } else if (first.tagName === "SELECT") {
        options = Array.from(first.options || []).map((option) => clean(option.textContent || option.value || "")).filter(Boolean);
      }
      const label = scopeLabel(scope, first);
      const fieldNames = Array.from(new Set(groupControls.map((control) => control.getAttribute("name") || control.getAttribute("id") || "").filter(Boolean)));
      const filled = groupControls.some((control) => valueFilled(control, scope));
      questions.push({
        name: fieldNames[0] || compact(label),
        label,
        type: liveType,
        required,
        options: Array.from(new Set(options)).filter(Boolean),
        fields: fieldNames.map((fieldName) => ({ name: fieldName, type: liveType })),
        filled,
      });
    }
    return {
      provider: "workable",
      questions: questions
        .filter((question) => question.label && !/captcha|recaptcha|turnstile/i.test(question.label))
        .filter((question, index, list) => {
          const key = `${compact(question.name)}:${compact(question.label)}`;
          return list.findIndex((candidate) => `${compact(candidate.name)}:${compact(candidate.label)}` === key) === index;
        }),
    };
  }).catch(() => ({ provider: "workable", questions: [] }));
}

async function hasApplicationFormFields(page) {
  return page.evaluate(() => {
    const fields = Array.from(
      document.querySelectorAll(
        [
          "#first_name",
          "#last_name",
          "#email",
          "#phone",
          'input[name="first_name"]',
          'input[name="firstName"]',
          'input[name="last_name"]',
          'input[name="lastName"]',
          'input[type="email"]',
          'input[name*="email" i]',
          'input[type="file"]',
        ].join(",")
      )
    );
    return fields.some((field) => {
      const style = window.getComputedStyle(field);
      const rect = field.getBoundingClientRect();
      return (
        !field.disabled &&
        field.type !== "hidden" &&
        style.visibility !== "hidden" &&
        style.display !== "none" &&
        rect.width > 0 &&
        rect.height > 0
      );
    });
  });
}

async function ensureApplicationFormReady(page) {
  if (await hasApplicationFormFields(page)) {
    return { opened: false, ready: true };
  }

  await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight)).catch(() => {});
  if (await hasApplicationFormFields(page)) {
    return { opened: false, ready: true };
  }

  const clicked = await page.evaluate(() => {
    const targets = Array.from(document.querySelectorAll("button, a, input[type=button], input[type=submit]"));
    const applyTarget = targets.find((target) => {
      const text = `${target.textContent || ""} ${target.getAttribute("value") || ""} ${target.getAttribute("aria-label") || ""}`.trim();
      return /\bapply now\b|\bapply for this job\b|\bapply\b/i.test(text);
    });
    if (!applyTarget) {
      return false;
    }
    applyTarget.scrollIntoView({ block: "center" });
    applyTarget.click();
    return true;
  });

  if (clicked) {
    await page.waitForNetworkIdle({ idleTime: 1000, timeout: 12000 }).catch(() => {});
    await page
      .waitForSelector(
        [
          "#first_name",
          "#last_name",
          "#email",
          "#phone",
          'input[name="first_name"]',
          'input[name="firstName"]',
          'input[name="last_name"]',
          'input[name="lastName"]',
          'input[type="email"]',
          'input[type="file"]',
        ].join(","),
        { timeout: 15000 }
      )
      .catch(() => {});
  }

  return {
    opened: clicked,
    ready: await hasApplicationFormFields(page),
  };
}

async function uploadResume(page, cvPath) {
  if (!cvPath) {
    return false;
  }
  const inputs = await page.$$("input[type=file]");
  for (const input of inputs) {
    await input.evaluate((element) => element.scrollIntoView({ block: "center" })).catch(() => {});
    await input.uploadFile(cvPath);
    await clickButtonByText(page, [/^upload$/i]).catch(() => false);
    await page.waitForNetworkIdle({ idleTime: 1000, timeout: 15000 }).catch(() => {});
    return true;
  }
  return false;
}

async function waitForWorkableResumeImport(page) {
  const imported = await page
    .waitForFunction(
      () => {
        const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
        const bodyText = clean(document.body ? document.body.innerText : "");
        if (/autofill completed|resume imported|cv imported|resume parsed|parsed your resume/i.test(bodyText)) {
          return true;
        }
        const coreFields = ["firstname", "lastname", "email", "phone"]
          .map((name) => document.querySelector(`[name="${name}"]`))
          .filter(Boolean);
        return coreFields.some((field) => clean(field.value || "") !== "");
      },
      { timeout: 60000 }
    )
    .then(() => true)
    .catch(() => false);
  await page.waitForNetworkIdle({ idleTime: 1000, timeout: 12000 }).catch(() => {});
  await new Promise((resolve) => setTimeout(resolve, 1000));
  return imported;
}

async function uploadWorkableResumeViaImport(page, cvPath) {
  if (!cvPath) {
    return { uploaded: false, imported: false, clicked_import: false, fallback_upload: false };
  }

  const clickedImport = await page.evaluate(() => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const isVisible = (element) => {
      if (!element) return false;
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
    };
    const target = Array.from(document.querySelectorAll("button, a, [role='button']"))
      .filter(isVisible)
      .find((node) => /import resume from|import cv from|import resume|import cv/i.test(clean(`${node.textContent || ""} ${node.getAttribute("aria-label") || ""}`)));
    if (!target) {
      return false;
    }
    target.scrollIntoView({ block: "center", inline: "nearest" });
    target.click();
    return true;
  }).catch(() => false);

  if (clickedImport) {
    await new Promise((resolve) => setTimeout(resolve, 500));
    try {
      const [fileChooser] = await Promise.all([
        page.waitForFileChooser({ timeout: 15000 }),
        page.evaluate(() => {
          const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
          const isVisible = (element) => {
            if (!element) return false;
            const style = window.getComputedStyle(element);
            const rect = element.getBoundingClientRect();
            return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
          };
          const computerTarget = Array.from(document.querySelectorAll("button, a, [role='button'], li, div, span"))
            .filter(isVisible)
            .filter((node) => clean(node.textContent || "").length < 80)
            .find((node) => /^my computer$|computer|device|upload from computer/i.test(clean(node.textContent || "")));
          if (computerTarget) {
            computerTarget.scrollIntoView({ block: "center", inline: "nearest" });
            computerTarget.click();
          }
        }),
      ]);
      await fileChooser.accept([cvPath]);
      return {
        uploaded: true,
        imported: await waitForWorkableResumeImport(page),
        clicked_import: true,
        fallback_upload: false,
      };
    } catch (error) {
      debugLog("workable_resume_import_filechooser_failed", error && error.message ? error.message : String(error));
      await page.keyboard.press("Escape").catch(() => {});
    }
  }

  const fallbackUpload = await uploadResume(page, cvPath);
  return {
    uploaded: fallbackUpload,
    imported: false,
    clicked_import: clickedImport,
    fallback_upload: fallbackUpload,
  };
}

async function clickWorkableResumeImport(page) {
  return page.evaluate(() => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const isVisible = (element) => {
      if (!element) return false;
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
    };
    const target = Array.from(document.querySelectorAll("button, a, [role='button'], label"))
      .filter(isVisible)
      .find((node) => {
        const text = clean(`${node.textContent || ""} ${node.getAttribute("aria-label") || ""}`);
        return /\b(import|parse|use|fill|extract)\b.*\b(resume|cv|profile)\b|\b(resume|cv)\b.*\b(import|parse|fill)\b/i.test(text);
      });
    if (!target) {
      return false;
    }
    target.scrollIntoView({ block: "center", inline: "nearest" });
    target.click();
    return true;
  }).catch(() => false);
}

async function dismissCookieBanners(page) {
  return page.evaluate(() => {
    const buttons = Array.from(document.querySelectorAll("button, a, input[type='button']"));
    const target = buttons.find((button) => {
      const text = `${button.textContent || ""} ${button.getAttribute("value") || ""} ${
        button.getAttribute("aria-label") || ""
      }`.replace(/\s+/g, " ").trim();
      return /^(accept all|accept|allow all|agree|ok)$/i.test(text);
    });
    if (!target) {
      return false;
    }
    target.scrollIntoView({ block: "center", inline: "nearest" });
    target.click();
    return true;
  }).catch(() => false);
}

function cleanText(value) {
  return String(value || "").replace(/\s+/g, " ").trim();
}

function cssIdSelector(id) {
  return `[id="${String(id || "").replace(/\\/g, "\\\\").replace(/"/g, '\\"')}"]`;
}

function getTaskPayload(task) {
  const payload = task && task.payload;
  if (!payload) {
    return {};
  }
  if (typeof payload === "string") {
    try {
      const decoded = JSON.parse(payload);
      return decoded && typeof decoded === "object" ? decoded : {};
    } catch (error) {
      return {};
    }
  }
  return typeof payload === "object" ? payload : {};
}

function getApplicationSchema(task) {
  const payload = getTaskPayload(task);
  const schema = payload.application_schema || task.application_schema || {};
  return schema && typeof schema === "object" ? schema : {};
}

function getSchemaQuestions(schema) {
  const questions = [];
  if (Array.isArray(schema.questions)) {
    questions.push(...schema.questions);
  }
  if (Array.isArray(schema.location_questions)) {
    questions.push(...schema.location_questions);
  }
  if (!questions.length && Array.isArray(schema.fields)) {
    questions.push(...schema.fields);
  }
  return questions;
}

function getQuestionFields(question) {
  if (Array.isArray(question?.fields)) {
    return question.fields;
  }
  return question?.name ? [question] : [];
}

function getQuestionLabel(question) {
  return cleanText(
    question?.label ||
      question?.question ||
      question?.name ||
      question?.title ||
      question?.description ||
      ""
  );
}

function getQuestionFieldNames(question) {
  return getQuestionFields(question)
    .map((field) => cleanText(field?.name || field?.id || field?.key || ""))
    .filter(Boolean);
}

function getQuestionFieldTypes(question) {
  return getQuestionFields(question)
    .map((field) => cleanText(field?.type || field?.field_type || field?.input_type || field?.component || ""))
    .filter(Boolean);
}

function normalizeChoiceList(choices) {
  return (Array.isArray(choices) ? choices : [])
    .map((choice) =>
      cleanText(
        typeof choice === "string" || typeof choice === "number"
          ? choice
          : choice?.label ||
          choice?.name ||
          choice?.text ||
          choice?.value ||
          choice?.display_name ||
          choice?.title ||
          ""
      )
    )
    .filter(Boolean);
}

function getQuestionChoiceLabels(question) {
  const choices = [
    ...normalizeChoiceList(question?.values),
    ...normalizeChoiceList(question?.options),
    ...normalizeChoiceList(question?.choices),
  ];
  for (const field of getQuestionFields(question)) {
    choices.push(...normalizeChoiceList(field?.values));
    choices.push(...normalizeChoiceList(field?.options));
    choices.push(...normalizeChoiceList(field?.choices));
  }
  return Array.from(new Set(choices));
}

function mergeQuestionsByFieldOrLabel(primaryQuestions, fallbackQuestions) {
  const normalizeKey = (value) => cleanText(value).toLowerCase().replace(/[^a-z0-9]+/g, "");
  const hasRequiredFlag = (question) => Object.prototype.hasOwnProperty.call(question || {}, "required");
  const merged = [];
  const seen = new Set();
  const fallbackByField = new Map();
  const fallbackByLabel = new Map();

  for (const question of fallbackQuestions || []) {
    for (const fieldName of getQuestionFieldNames(question)) {
      fallbackByField.set(normalizeKey(fieldName), question);
    }
    const labelKey = normalizeKey(getQuestionLabel(question));
    if (labelKey) {
      fallbackByLabel.set(labelKey, question);
    }
  }

  for (const question of primaryQuestions || []) {
    const fieldNames = getQuestionFieldNames(question);
    const label = getQuestionLabel(question);
    const match =
      fieldNames.map((name) => fallbackByField.get(normalizeKey(name))).find(Boolean) ||
      fallbackByLabel.get(normalizeKey(label)) ||
      null;
    const mergedQuestion = match
      ? {
          ...match,
          ...question,
          label: getQuestionLabel(question) || getQuestionLabel(match),
          options: getQuestionChoiceLabels(question).length
            ? getQuestionChoiceLabels(question)
            : getQuestionChoiceLabels(match),
          required: hasRequiredFlag(question) ? questionIsRequired(question) : questionIsRequired(match),
        }
      : question;
    const key = fieldNames.map(normalizeKey).find(Boolean) || normalizeKey(label);
    if (!key || seen.has(key)) {
      continue;
    }
    seen.add(key);
    merged.push(mergedQuestion);
  }

  for (const question of fallbackQuestions || []) {
    const key = getQuestionFieldNames(question).map(normalizeKey).find(Boolean) || normalizeKey(getQuestionLabel(question));
    if (key && !seen.has(key)) {
      seen.add(key);
      merged.push(question);
    }
  }

  return merged;
}

function questionLooksChoiceBased(question) {
  const fieldTypes = getQuestionFieldTypes(question).join(" ").toLowerCase();
  if (getQuestionChoiceLabels(question).length > 0) {
    return true;
  }
  return /select|dropdown|choice|multi_value|single_value|boolean|radio|checkbox/.test(fieldTypes);
}

function questionIsRequired(question) {
  return question?.required === true || question?.required === "true" || question?.required === 1;
}

function getApplicationAnswers(task) {
  const payload = getTaskPayload(task);
  const answers = payload.application_answers || task.application_answers || {};
  return answers && typeof answers === "object" ? answers : {};
}

function getCandidateProfileAnswers(task) {
  const payload = getTaskPayload(task);
  const answers = {
    ...(payload.candidate_profile && typeof payload.candidate_profile === "object" ? payload.candidate_profile : {}),
    ...(payload.successfactors_profile && typeof payload.successfactors_profile === "object" ? payload.successfactors_profile : {}),
    ...getApplicationAnswers(task),
  };
  return answers && typeof answers === "object" ? answers : {};
}

function getCvText(task) {
  const payload = getTaskPayload(task);
  return String(task?.cv_text || payload.cv_text || payload.cvText || task?.__sffc_cv_text || "")
    .replace(/[^\S\r\n]+/g, " ")
    .replace(/\n{3,}/g, "\n\n")
    .trim();
}

function answerByAnyPattern(answers, patterns) {
  return answerByPatterns(answers, patterns);
}

function extractFirstMatch(text, patterns) {
  for (const pattern of patterns) {
    const match = String(text || "").match(pattern);
    if (match && cleanText(match[1] || match[0])) {
      return cleanText(match[1] || match[0]);
    }
  }
  return "";
}

function inferCountryFromText(text) {
  const countryAliases = [
    ["United Arab Emirates", /\b(?:united arab emirates|uae|dubai|abu dhabi|sharjah)\b/i],
    ["Saudi Arabia", /\b(?:saudi arabia|riyadh|jeddah|ksa)\b/i],
    ["Qatar", /\b(?:qatar|doha)\b/i],
    ["United Kingdom", /\b(?:united kingdom|uk|london|england|scotland|wales)\b/i],
    ["United States", /\b(?:united states|usa|new york|california|texas)\b/i],
    ["France", /\b(?:france|paris|nice|toulouse|lille)\b/i],
    ["Morocco", /\b(?:morocco|casablanca|rabat)\b/i],
    ["Singapore", /\bsingapore\b/i],
    ["India", /\b(?:india|mumbai|delhi|bengaluru|bangalore|chennai)\b/i],
  ];
  const match = countryAliases.find(([, pattern]) => pattern.test(text || ""));
  return match ? match[0] : "";
}

function inferTypeOfBusiness(text) {
  const haystack = cleanText(text).toLowerCase();
  if (/investment banking|m&a|mergers|acquisitions|corporate finance|capital markets|bank\b/.test(haystack)) {
    return "Banking";
  }
  if (/private equity|venture capital|asset management|investment management|fund/.test(haystack)) {
    return "Financial Services";
  }
  if (/audit|accounting|tax|assurance/.test(haystack)) {
    return "Accounting";
  }
  if (/consulting|consultant|advisory/.test(haystack)) {
    return "Consulting";
  }
  return "";
}

function getCvSection(text, startPatterns, stopPatterns = []) {
  const source = String(text || "");
  const starts = startPatterns
    .map((pattern) => {
      const match = source.match(pattern);
      return match ? match.index : -1;
    })
    .filter((index) => index >= 0)
    .sort((a, b) => a - b);
  if (!starts.length) return source;
  const start = starts[0];
  const rest = source.slice(start);
  const stops = stopPatterns
    .map((pattern) => {
      const match = rest.slice(20).match(pattern);
      return match ? match.index + 20 : -1;
    })
    .filter((index) => index > 20)
    .sort((a, b) => a - b);
  return stops.length ? rest.slice(0, stops[0]) : rest;
}

function extractLatestExperienceFromCv(text) {
  const experienceText = getCvSection(text, [
    /\b(?:professional\s+)?experiences?\b/i,
    /\bwork\s+(?:history|experience)\b/i,
    /\bemployment\b/i,
    /\bberufserfahrung\b/i,
  ], [
    /\beducation\b/i,
    /\bskills\b/i,
    /\blanguages\b/i,
    /\bausbildung\b/i,
  ]);
  const lines = experienceText.split(/\r?\n/).map(cleanText).filter(Boolean);
  for (let index = 0; index < lines.length; index += 1) {
    const line = lines[index];
    if (!/\b(?:analyst|associate|manager|director|consultant|accountant|auditor|intern|engineer|officer|controller|specialist|executive|vice president|vp)\b/i.test(line)) {
      continue;
    }
    const role = cleanText(line.replace(/\s*[-–]\s*(?:permanent contract|internship|current|present).*$/i, ""));
    const companyLine = lines.slice(index + 1, index + 5).find((candidateLine) =>
      /\b(?:bank|capital|partners|group|llc|ltd|limited|inc|corp|corporate|consulting|advisory|company|systems|software|university|paribas|eurazeo|soci[eé]t[eé]|exxon|bmw|star)\b/i.test(candidateLine)
    ) || "";
    if (role && companyLine) {
      const companyParts = companyLine.split(/\s+[–|-]\s+/);
      return {
        title: role.slice(0, 120),
        company: cleanText(companyParts[0]).slice(0, 120),
        country: inferCountryFromText(companyParts.slice(1).join(" ") || companyLine),
      };
    }
  }
  const compact = cleanText(experienceText);
  const match = compact.match(/\b((?:[A-Z][A-Za-z/&.,'-]*\s+){0,8}(?:Analyst|Associate|Manager|Director|Consultant|Accountant|Auditor|Engineer|Officer|Controller|Specialist|Executive|Intern)[A-Za-z/&.,' -]{0,80})\s+(?:\d{1,2}\/\d{4}|[A-Za-z]{3,9}\s+\d{4}|\d{4}|current|present|heute|oggi)\s*(?:[-–]\s*(?:\d{1,2}\/\d{4}|[A-Za-z]{3,9}\s+\d{4}|\d{4}|current|present|heute|oggi))?\s+([A-Z][A-Za-z0-9 &.,'()/-]{2,90})/i);
  if (match) {
    const company = cleanText(match[2]).replace(/\s+(?:Collect|Read|Analysis|Working|Develop|Acquiring)\b.*$/i, "");
    return {
      title: cleanText(match[1]).slice(0, 120),
      company: company.slice(0, 120),
      country: inferCountryFromText(company),
    };
  }
  return { title: "", company: "", country: "" };
}

function extractEducationFromCv(text) {
  const educationText = getCvSection(text, [
    /\beducation\b/i,
    /\bformation\b/i,
    /\bausbildung\b/i,
  ], [
    /\b(?:professional\s+)?experience\b/i,
    /\bwork\s+(?:history|experience)\b/i,
    /\bskills\b/i,
    /\blanguages\b/i,
  ]);
  const compact = cleanText(educationText);
  const schoolMatch = compact.match(/\b([A-Z][A-Za-zÀ-ÿ0-9 &.'-]{2,100}?(?:University|School|College|Business School|Lyc[eé]e|Institute|Academy|Universit[aàé]|Hochschule)[A-Za-zÀ-ÿ0-9 &.'-]{0,80})\b/);
  const degreeMatch = compact.match(/\b(PhD|MSc|Master(?:'s)?|MBA|Bachelor(?:'s)?|BSc|BA|BBA|B-Tech|Baccalaureate|Diploma)\b/i);
  const subjectMatch =
    compact.match(/(?:subjects?|major|concentration|field of study)\s*[:\-]?\s*([A-Za-zÀ-ÿ &,/]{3,90})/i) ||
    compact.match(/\b(?:in|of)\s+([A-Z]?[A-Za-zÀ-ÿ &]{3,70}?(?:Finance|Accounting|Economics|Management|Business|Law|Engineering|Mathematics|Technology))\b/i);
  const years = Array.from(compact.matchAll(/\b(19\d{2}|20\d{2})\b/g))
    .map((match) => Number(match[1]))
    .filter((year) => year >= 1950 && year <= new Date().getFullYear() + 10);
  return {
    school: cleanText(schoolMatch?.[1] || "").slice(0, 120),
    degree: cleanText(degreeMatch?.[1] || ""),
    subject: cleanText(subjectMatch?.[1] || "").replace(/\s+(?:Achieved|Grade|GPA)\b.*$/i, "").slice(0, 120),
    passingYear: years.length ? String(Math.max(...years)) : "",
    country: inferCountryFromText(educationText),
  };
}

function normalizeMonthNameToNumber(value) {
  const text = cleanText(value).toLowerCase();
  const months = {
    jan: "01",
    january: "01",
    feb: "02",
    february: "02",
    mar: "03",
    march: "03",
    apr: "04",
    april: "04",
    may: "05",
    jun: "06",
    june: "06",
    jul: "07",
    july: "07",
    aug: "08",
    august: "08",
    sep: "09",
    sept: "09",
    september: "09",
    oct: "10",
    october: "10",
    nov: "11",
    november: "11",
    dec: "12",
    december: "12",
  };
  if (/^\d{1,2}$/.test(text)) {
    const number = Number(text);
    return number >= 1 && number <= 12 ? String(number).padStart(2, "0") : "";
  }
  return months[text] || "";
}

function parseCvDateRange(value) {
  const text = cleanText(value);
  const datePattern = /([A-Za-z]{3,9}|\d{1,2})\s+(\d{4}|Today|Present|Current)|(\d{1,2})\/(\d{4}|Today|Present|Current)|\b(Today|Present|Current)\b/gi;
  const matches = Array.from(text.matchAll(datePattern));
  const parsed = matches.map((match) => {
    const present = cleanText(match[2] || match[4] || match[5] || "").toLowerCase();
    if (/today|present|current/.test(present)) {
      return { month: "", year: "", current: true };
    }
    const month = normalizeMonthNameToNumber(match[1] || match[3] || "");
    const year = cleanText(match[2] || match[4] || "");
    return { month, year, current: false };
  });
  const start = parsed[0] || { month: "", year: "", current: false };
  const end = parsed.slice(1).find((entry) => entry.current || entry.year) || { month: "", year: "", current: /today|present|current/i.test(text) };
  return {
    startMonth: start.month,
    startYear: start.year,
    endMonth: end.month,
    endYear: end.year,
    current: Boolean(end.current || /today|present|current/i.test(text)),
  };
}

function extractCvDateRanges(text) {
  const parseEndpoint = (endpoint, fallbackMonth = "") => {
    const value = cleanText(endpoint);
    if (/today|present|current/i.test(value)) {
      return { month: "", year: "", current: true };
    }
    const monthYear = value.match(/^([A-Za-z]{3,9}|\d{1,2})\s+(\d{4})$/i);
    if (monthYear) {
      return { month: normalizeMonthNameToNumber(monthYear[1]), year: monthYear[2], current: false };
    }
    const numericMonthYear = value.match(/^(\d{1,2})\/(\d{4})$/);
    if (numericMonthYear) {
      return { month: normalizeMonthNameToNumber(numericMonthYear[1]), year: numericMonthYear[2], current: false };
    }
    const yearOnly = value.match(/^(\d{4})$/);
    if (yearOnly && fallbackMonth) {
      return { month: fallbackMonth, year: yearOnly[1], current: false };
    }
    return { month: "", year: "", current: false };
  };
  return String(text || "")
    .split(/\r?\n/)
    .map(cleanText)
    .filter((line) => /\b(?:19|20)\d{2}\b/.test(line) && /[-–—−]/.test(line))
    .map((line) => {
      const parts = line.split(/\s*[-–—−]\s*/).map(cleanText).filter(Boolean);
      if (parts.length < 2) {
        return null;
      }
      const start = parseEndpoint(parts[0]);
      const end = parseEndpoint(parts.slice(1).join(" "), start.month);
      const current = Boolean(end.current);
      return {
        startMonth: start.month,
        startYear: start.year,
        endMonth: current ? "" : end.month,
        endYear: current ? "" : end.year,
        current,
      };
    })
    .filter(Boolean)
    .filter((range) => range.startMonth && range.startYear && (range.current || (range.endMonth && range.endYear)));
}

function extractWorkdayEducationEntriesFromCv(text) {
  const educationText = getCvSection(text, [
    /\beducation\b/i,
    /\bformation\b/i,
    /\bausbildung\b/i,
  ], [
    /\b(?:professional\s+)?experiences?\b/i,
    /\bwork\s+(?:history|experience)\b/i,
    /\bskills\b/i,
    /\blanguages\b/i,
  ]);
  const lines = educationText
    .split(/\r?\n/)
    .map(cleanText)
    .filter(Boolean)
    .filter((line) => !/^education$/i.test(line));
  const isSchoolLine = (line) =>
    /(?:university|college|business school|lyc[eé]e|groupe scolaire|institute|academy|school)/i.test(line) &&
    !/^(?:subjects?|majors?|preparatory classes|three-year programme|achieved)\b/i.test(line);
  const blocks = [];
  for (let index = 0; index < lines.length; index += 1) {
    if (!isSchoolLine(lines[index])) {
      continue;
    }
    const blockLines = [lines[index]];
    for (let nextIndex = index + 1; nextIndex < lines.length; nextIndex += 1) {
      if (isSchoolLine(lines[nextIndex])) {
        break;
      }
      blockLines.push(lines[nextIndex]);
    }
    blocks.push({ schoolLine: lines[index], text: blockLines.join(" ") });
  }
  return blocks.map(({ schoolLine, text: block }) => {
    const school =
      cleanText(
        (
          schoolLine.match(/([A-Z][A-Za-zÀ-ÿ0-9 &'’.-]{1,100}?(?:University|School|College|Business School|Lyc[eé]e|Institute|Academy|Résidence|Residence)[A-Za-zÀ-ÿ0-9 &'’.-]{0,80})/i) ||
          []
        )[1] || ""
      )
        .replace(/^Education\s+/i, "")
        .replace(/\s+[–-]\s+.*$/, "");
    const dateRange = parseCvDateRange(block);
    const degreeSource =
      cleanText((block.match(/\b(?:Double Degree Master|Master(?:'s)?|MSc|MBA|Bachelor(?:'s)?|BSc|BA|BBA|Baccalaureate|Preparatory classes|Diploma)\b/i) || [])[0] || "");
    let degree = degreeSource;
    if (/double degree master|master|msc/i.test(block)) {
      degree = "Master's Degree";
    } else if (/baccalaureate|lyc[eé]e|preparatory classes|high honours/i.test(block)) {
      degree = "High School Diploma";
    } else if (/bachelor|bsc|ba|bba/i.test(block)) {
      degree = "Bachelor's Degree";
    }
    const subject =
      cleanText(
        (block.match(/Subjects?\s*:\s*([^.;\n]{3,140})/i) || [])[1] ||
          (block.match(/Majors?\s*:\s*([^.;\n]{3,100})/i) || [])[1] ||
          (block.match(/\b(?:in|of)\s+([A-Z]?[A-Za-zÀ-ÿ &/]{3,80}?(?:Finance|Management|Mathematics|Physics|Economics|Business))\b/i) || [])[1] ||
          ""
      )
        .replace(/\s+Achieved\b.*$/i, "")
        .slice(0, 120);
    const gpa =
      cleanText((block.match(/\bGPA\s*(?:over|of|:)?\s*([0-9]+(?:\.[0-9]+)?(?:\s*\/\s*[0-9]+(?:\.[0-9]+)?)?)/i) || [])[1] || "") ||
      (/high honours/i.test(block) ? "High honours" : "");
    return {
      school,
      degree,
      fieldOfStudy: subject,
      gradeAverage: gpa,
      firstYear: dateRange.startYear,
      lastYear: dateRange.endYear,
      country: inferCountryFromText(block),
    };
  }).filter((entry) => entry.school);
}

function extractWorkdayExperienceEntriesFromCv(text) {
  const experienceText = getCvSection(text, [
    /\b(?:professional\s+)?experiences?\b/i,
    /\bwork\s+(?:history|experience)\b/i,
    /\bemployment\b/i,
  ], [
    /\blanguages\b/i,
    /\bskills\b/i,
    /\bcertifications?\b/i,
    /\badditional experience\b/i,
  ]);
  const lines = experienceText.split(/\r?\n/).map(cleanText).filter(Boolean);
  const entries = [];
  for (let index = 0; index < lines.length; index += 1) {
    const titleLine = lines[index];
    if (!/\b(?:analyst|associate|manager|director|consultant|accountant|auditor|intern|engineer|officer|controller|specialist|executive|vice president|vp)\b/i.test(titleLine)) {
      continue;
    }
    const companyLine = lines.slice(index + 1, index + 5).find((line) => /[–-].*\b(?:france|morocco|united|uae|saudi|qatar|singapore|london|paris|dubai|riyadh)\b/i.test(line)) || "";
    const nearby = lines.slice(index, index + 8).join(" ");
    const dateRange = parseCvDateRange(nearby);
    entries.push({
      title: cleanText(titleLine.replace(/\s*\([^)]*\)\s*/g, " ")).slice(0, 120),
      company: cleanText(companyLine.split(/\s+[–-]\s+/)[0] || "").slice(0, 120),
      location: cleanText(companyLine.split(/\s+[–-]\s+/).slice(1).join(" - ")).slice(0, 120),
      ...dateRange,
    });
  }
  return entries.filter((entry) => entry.title || entry.company || entry.current);
}

function extractSuccessFactorsProfileFromCv(task, candidate) {
  const text = getCvText(task);
  const answers = getCandidateProfileAnswers(task);
  const latestExperience = extractLatestExperienceFromCv(text);
  const education = extractEducationFromCv(text);
  const phone =
    cleanText(candidate.phone) ||
    answerByAnyPattern(answers, [/\bphone\b/, /\bmobile\b/, /telephone/]) ||
    extractFirstMatch(text, [
      /(?:tel\.?|telephone|mobile|phone)\s*[:.]?\s*([+\d][\d\s().-]{7,})/i,
      /(\+\d{1,4}[\d\s().-]{7,})/,
    ]);
  return {
    phone,
    type_of_business: answerByAnyPattern(answers, [/type of business/, /industry/, /sector/]) || inferTypeOfBusiness([latestExperience.title, latestExperience.company, text].join(" ")),
    company_name: answerByAnyPattern(answers, [/company name/, /employer/]) || latestExperience.company,
    employment_country: answerByAnyPattern(answers, [/employment country/, /^country$/]) || latestExperience.country,
    title: answerByAnyPattern(answers, [/^title$/, /job title/, /role title/]) || latestExperience.title,
    school: answerByAnyPattern(answers, [/school|university|college/]) || education.school,
    subject: answerByAnyPattern(answers, [/subject|major|field of study/]) || education.subject,
    degree_type: answerByAnyPattern(answers, [/degree type|degree/]) || education.degree,
    passing_year: answerByAnyPattern(answers, [/passing year|graduation year|end year/]) || education.passingYear,
    education_country: answerByAnyPattern(answers, [/country of education|education country/]) || education.country,
    gender: answerByAnyPattern(answers, [/^gender$/, /gender identity/]),
    marital_status: answerByAnyPattern(answers, [/marital/]),
    nationality: answerByAnyPattern(answers, [/nationality|citizenship/]) || extractFirstMatch(text, [/nationality\s*[:\-]\s*([A-Za-z ]{2,40})/i]),
    country_of_residence: answerByAnyPattern(answers, [/country of residence|residence country/]) || inferCountryFromText(candidate.address || text.slice(0, 800)),
  };
}

function getVerificationCode(task) {
  const payload = getTaskPayload(task);
  return cleanText(payload.verification_code || task.verification_code || "");
}

function isWorkableApplication(task, url = "") {
  const provider = cleanText(task?.provider || "").toLowerCase();
  return (
    provider === "workable" ||
    provider === "workable_board" ||
    /(?:^|\/\/)apply\.workable\.com\//i.test(cleanText(url || task?.application_workspace_url || task?.application_url || ""))
  );
}

function isWorkdayApplication(task, url = "") {
  const provider = cleanText(task?.provider || "").toLowerCase();
  const candidateUrl = cleanText(url || task?.application_workspace_url || task?.application_url || "");
  return (
    provider === "workday" ||
    /(?:^|\/\/)[^/]*\.myworkdayjobs\.com\//i.test(candidateUrl) ||
    /(?:^|\/\/)[^/]*\.workdayjobs\.com\//i.test(candidateUrl) ||
    /\/wday\/cxs\//i.test(candidateUrl)
  );
}

function isSuccessFactorsApplication(task, url = "") {
  const provider = cleanText(task?.provider || "").toLowerCase();
  const schema = getApplicationSchema(task);
  const candidateUrl = cleanText(
    url ||
      schema.application_embed_url ||
      schema.hosted_url ||
      task?.application_workspace_url ||
      task?.application_url ||
      ""
  );
  return (
    provider === "successfactors" ||
    provider === "sap successfactors" ||
    cleanText(schema.provider || "").toLowerCase() === "successfactors" ||
    /(?:^|\/\/)[^/]*\.successfactors\.(?:com|eu)\//i.test(candidateUrl) ||
    /(?:^|\/\/)[^/]*\.sapsf\.com\//i.test(candidateUrl) ||
    /\/talentcommunity\/apply\//i.test(candidateUrl)
  );
}

function parseWorkdayUrl(url) {
  try {
    const parsed = new URL(url);
    const parts = parsed.pathname.split("/").filter(Boolean);
    const localeIndex = parts.findIndex((part) => /^[a-z]{2}-[A-Z]{2}$/.test(part));
    const siteIndex = localeIndex >= 0 ? localeIndex + 1 : 0;
    const jobIndex = parts.indexOf("job");
    const detailsIndex = parts.indexOf("details");
    const tenant = (parsed.hostname.match(/^([^.]+)\./) || [])[1] || "";
    const siteId = parts[siteIndex] || "";
    const locale = localeIndex >= 0 ? parts[localeIndex] : "en-US";
    const externalPath =
      jobIndex >= 0
        ? "/" + parts.slice(jobIndex).join("/")
        : detailsIndex >= 0
          ? "/job/" + parts.slice(detailsIndex + 1).join("/")
          : "";
    const jobPostingId = parts[parts.length - 1] || "";
    return {
      origin: parsed.origin,
      tenant,
      siteId,
      locale,
      externalPath,
      jobPostingId,
      canonicalUrl:
        parsed.origin +
        "/" +
        [locale, siteId, externalPath.replace(/^\/+/, "")].filter(Boolean).join("/"),
    };
  } catch {
    return { origin: "", tenant: "", siteId: "", locale: "en-US", externalPath: "", jobPostingId: "", canonicalUrl: url };
  }
}

function getWorkdayAccount(task) {
  const payload = getTaskPayload(task);
  const account = payload.workday_account && typeof payload.workday_account === "object" ? payload.workday_account : {};
  const consent = payload.workday_consent && typeof payload.workday_consent === "object" ? payload.workday_consent : {};
  const accountRoute = cleanText(account.account_route || consent.account_route || "");
  return {
    account_route: accountRoute,
    create_account: Boolean(
      accountRoute === "create" || account.create_account || payload.workday_create_account || consent.create_account
    ),
    sign_in: Boolean(
      accountRoute === "sign_in" || account.sign_in || account.use_existing_account || payload.workday_sign_in
    ),
    email: cleanText(account.email || task.candidate_email || ""),
    password: cleanText(account.password || payload.workday_password || ""),
    allow_generated_password: Boolean(account.allow_generated_password || payload.workday_allow_generated_password),
    consent_scope: cleanText(consent.scope || payload.consent || ""),
  };
}

function generateSuccessFactorsPassword() {
  return `Sf!${Math.random().toString(36).slice(2, 8)}${String(Date.now()).slice(-4)}`;
}

function generateWorkdayPassword() {
  return `Sffc!${Date.now().toString(36).slice(-6)}${Math.random().toString(36).slice(2, 6)}A7`;
}

async function fetchWorkdayJson(url, options = {}) {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), Number(options.timeoutMs || workdayFetchTimeoutMs));
  const response = await fetch(url, {
    redirect: "follow",
    signal: controller.signal,
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      "User-Agent":
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36",
      ...(options.headers || {}),
    },
    method: options.method || "GET",
    body: options.body ? JSON.stringify(options.body) : undefined,
  }).finally(() => clearTimeout(timeout));
  const text = await response.text();
  let data = null;
  try {
    data = text ? JSON.parse(text) : null;
  } catch {
    data = null;
  }
  return { ok: response.ok, status: response.status, url: response.url, data, text };
}

async function fetchWorkdayOptionalJson(url, options = {}) {
  try {
    const response = await fetchWorkdayJson(url, options);
    return {
      ok: response.ok,
      status: response.status,
      url: response.url || url,
      data: response.data,
      error: response.ok ? "" : cleanText(response.text || "").slice(0, 180),
    };
  } catch (error) {
    return {
      ok: false,
      status: 0,
      url,
      data: null,
      error: error && error.message ? error.message : String(error),
    };
  }
}

function extractWorkdayQuestionsFromJson(data) {
  const questions = [];
  const seenObjects = new WeakSet();
  const compact = (value) => cleanText(value).toLowerCase().replace(/[^a-z0-9]+/g, "");
  const valueText = (value) => {
    if (typeof value === "string" || typeof value === "number" || typeof value === "boolean") {
      return cleanText(value);
    }
    if (value && typeof value === "object") {
      return cleanText(
        value.descriptor ||
          value.label ||
          value.name ||
          value.text ||
          value.title ||
          value.value ||
          value.id ||
          ""
      );
    }
    return "";
  };
  const optionList = (object) => {
    const candidates = [
      object.options,
      object.choices,
      object.answers,
      object.values,
      object.instances,
      object.allowedValues,
      object.pickListValues,
    ];
    const options = [];
    for (const list of candidates) {
      if (Array.isArray(list)) {
        options.push(...list.map(valueText).filter(Boolean));
      }
    }
    return Array.from(new Set(options)).slice(0, 80);
  };
  const questionLabel = (object) =>
    cleanText(
      object.questionText ||
        object.question ||
        object.prompt ||
        object.label ||
        object.title ||
        object.descriptor ||
        object.displayName ||
        object.name ||
        ""
    );
  const fieldName = (object, label) =>
    cleanText(
      object.fieldName ||
        object.fieldId ||
        object.referenceID ||
        object.referenceId ||
        object.id ||
        object.name ||
        compact(label)
    );
  const fieldType = (object, options) => {
    const raw = cleanText(
      object.type ||
        object.fieldType ||
        object.answerType ||
        object.componentType ||
        object.inputType ||
        object.responseType ||
        ""
    ).toLowerCase();
    if (/radio|single|choice/.test(raw) && options.length) return "radio";
    if (/multi|checkbox/.test(raw)) return "checkbox";
    if (/date/.test(raw)) return "date";
    if (/textarea|long|paragraph/.test(raw)) return "textarea";
    if (options.length) return "select";
    return raw || "text";
  };
  const requiredFlag = (object) =>
    object.required === true ||
    object.required === "true" ||
    object.isRequired === true ||
    object.mandatory === true ||
    object.optional === false;
  const maybeAddQuestion = (object, pathKey) => {
    const label = questionLabel(object);
    if (!label || label.length > 240 || /job description|job posting|about us|summary/i.test(label)) {
      return;
    }
    const options = optionList(object);
    const hasQuestionShape =
      requiredFlag(object) ||
      options.length > 0 ||
      /question|answer|field|prompt|questionnaire|response/i.test(Object.keys(object).join(" "));
    if (!hasQuestionShape) {
      return;
    }
    const name = fieldName(object, label);
    const type = fieldType(object, options);
    questions.push({
      name,
      label,
      type,
      required: requiredFlag(object),
      options,
      fields: [{ name, type }],
      source: "workday_api",
      path: pathKey,
    });
  };
  const walk = (value, pathKey = "$") => {
    if (!value || typeof value !== "object") {
      return;
    }
    if (seenObjects.has(value)) {
      return;
    }
    seenObjects.add(value);
    if (Array.isArray(value)) {
      value.forEach((entry, index) => walk(entry, `${pathKey}[${index}]`));
      return;
    }
    maybeAddQuestion(value, pathKey);
    for (const [key, child] of Object.entries(value)) {
      if (child && typeof child === "object") {
        walk(child, pathKey === "$" ? key : `${pathKey}.${key}`);
      }
    }
  };
  walk(data);
  return mergeQuestionsByFieldOrLabel(questions, []).slice(0, 120);
}

async function discoverWorkdayApiSchema(preflight) {
  const info = preflight || {};
  const base =
    info.origin && info.tenant && info.siteId
      ? `${info.origin}/wday/cxs/${encodeURIComponent(info.tenant)}/${encodeURIComponent(info.siteId)}`
      : "";
  const probes = [];
  const questions = [];
  if (!base) {
    return { provider: "workday", questions, probes };
  }
  const questionnaireId = cleanText(info.questionnaire_id || "");
  const jobPostingId = cleanText(
    info.job_info?.job_posting_id || info.job_info?.job_req_id || info.jobPostingId || info.jobPostingId || ""
  );
  const encodedQuestionnaireId = encodeURIComponent(questionnaireId);
  const encodedJobPostingId = encodeURIComponent(jobPostingId);
  const candidates = [
    questionnaireId ? `${base}/questionnaire/${encodedQuestionnaireId}` : "",
    questionnaireId ? `${base}/questionnaires/${encodedQuestionnaireId}` : "",
    questionnaireId ? `${base}/jobapplication/questionnaire/${encodedQuestionnaireId}` : "",
    questionnaireId ? `${base}/jobApplicationQuestionnaire/${encodedQuestionnaireId}` : "",
    jobPostingId ? `${base}/jobapplication/${encodedJobPostingId}` : "",
    jobPostingId ? `${base}/jobapplication/${encodedJobPostingId}/questionnaire` : "",
  ].filter(Boolean);

  for (const endpoint of Array.from(new Set(candidates))) {
    const probe = await fetchWorkdayOptionalJson(endpoint, { timeoutMs: workdaySchemaFetchTimeoutMs });
    const extracted = probe.data ? extractWorkdayQuestionsFromJson(probe.data) : [];
    probes.push({
      url: endpoint,
      status: probe.status,
      ok: probe.ok,
      question_count: extracted.length,
      error: probe.error || "",
    });
    if (extracted.length) {
      questions.push(...extracted);
    }
  }
  return {
    provider: "workday",
    questions: mergeQuestionsByFieldOrLabel(questions, []),
    probes,
  };
}

function buildWorkdayLocationExternalPath(externalPath, location) {
  const cleanPath = cleanText(externalPath || "");
  const cleanLocation = cleanText(location || "");
  if (!cleanPath || !cleanLocation || !/^\/job\/[^/]+$/i.test(cleanPath)) {
    return cleanPath;
  }
  const locationSlug = cleanLocation
    .split(",")[0]
    .trim()
    .replace(/\s+/g, "-")
    .replace(/[^A-Za-z0-9._-]/g, "");
  if (!locationSlug) {
    return cleanPath;
  }
  return cleanPath.replace(/^\/job\//i, `/job/${locationSlug}/`);
}

async function getWorkdayPreflight(task, url) {
  const info = parseWorkdayUrl(url);
  debugLog(task?.task_uuid || "workday", "workday_preflight_start", JSON.stringify(info));
  const preflight = {
    ...info,
    account_required: false,
    account_verification: false,
    can_apply: false,
    include_resume_parsing: false,
    questionnaire_id: "",
    apply_url: "",
    job_title: task.role_title || "",
    company_name: task.company_name || "",
    location: "",
    errors: [],
  };
  if (!info.origin || !info.tenant || !info.siteId) {
    preflight.errors.push("Could not parse Workday tenant/site from URL.");
    return preflight;
  }

  const approotUrl = `${info.origin}/wday/cxs/${encodeURIComponent(info.tenant)}/${encodeURIComponent(info.siteId)}/approot`;
  const approot = await fetchWorkdayJson(approotUrl).catch((error) => ({ ok: false, status: 0, data: null, text: error.message }));
  debugLog(task?.task_uuid || "workday", "workday_preflight_approot", approot.status || 0, Boolean(approot.ok));
  if (approot.ok && approot.data) {
    preflight.account_required = Boolean(approot.data.featureFlags?.requireCandidateAccounts);
    preflight.account_verification = Boolean(approot.data.featureFlags?.accountVerification);
    preflight.feature_flags = approot.data.featureFlags || {};
  } else {
    preflight.errors.push(`Workday approot failed with ${approot.status || "network error"}.`);
  }

  let resolvedExternalPath = info.externalPath;
  if (info.externalPath) {
    let jobUrl = `${info.origin}/wday/cxs/${encodeURIComponent(info.tenant)}/${encodeURIComponent(info.siteId)}${resolvedExternalPath}`;
    let job = await fetchWorkdayJson(jobUrl).catch((error) => ({ ok: false, status: 0, data: null, text: error.message }));
    debugLog(task?.task_uuid || "workday", "workday_preflight_job", job.status || 0, Boolean(job.ok), resolvedExternalPath);
    if ((!job.ok || !job.data?.jobPostingInfo) && info.jobPostingId) {
      const searchText = info.jobPostingId.split("_").pop() || info.jobPostingId;
      const searchUrl = `${info.origin}/wday/cxs/${encodeURIComponent(info.tenant)}/${encodeURIComponent(info.siteId)}/jobs`;
      const search = await fetchWorkdayJson(searchUrl, {
        method: "POST",
        body: { appliedFacets: {}, limit: 20, offset: 0, searchText },
      }).catch((error) => ({ ok: false, status: 0, data: null, text: error.message }));
      debugLog(task?.task_uuid || "workday", "workday_preflight_search", search.status || 0, Boolean(search.ok));
      const posting = Array.isArray(search.data?.jobPostings)
        ? search.data.jobPostings.find((item) => {
            const externalPath = cleanText(item?.externalPath || "");
            const bullets = Array.isArray(item?.bulletFields) ? item.bulletFields.map(cleanText) : [];
            return externalPath.endsWith(info.jobPostingId) || bullets.includes(searchText);
          }) || search.data.jobPostings[0]
        : null;
      if (posting?.externalPath) {
        resolvedExternalPath = posting.externalPath;
        jobUrl = `${info.origin}/wday/cxs/${encodeURIComponent(info.tenant)}/${encodeURIComponent(info.siteId)}${resolvedExternalPath}`;
        job = await fetchWorkdayJson(jobUrl).catch((error) => ({ ok: false, status: 0, data: null, text: error.message }));
        preflight.resolved_from_search = true;
      }
    }
    if (job.ok && job.data?.jobPostingInfo) {
      const jobInfo = job.data.jobPostingInfo;
      if (cleanText(jobInfo.externalPath || "")) {
        resolvedExternalPath = cleanText(jobInfo.externalPath);
      }
      preflight.can_apply = Boolean(jobInfo.canApply);
      preflight.include_resume_parsing = Boolean(jobInfo.includeResumeParsing);
      preflight.questionnaire_id = cleanText(jobInfo.questionnaireId || "");
      preflight.job_title = cleanText(jobInfo.title || preflight.job_title);
      preflight.location = cleanText(jobInfo.location || jobInfo.jobRequisitionLocation?.descriptor || "");
      resolvedExternalPath = buildWorkdayLocationExternalPath(resolvedExternalPath, preflight.location);
      preflight.externalPath = resolvedExternalPath;
      preflight.canonicalUrl =
        info.origin +
        "/" +
        [info.locale, info.siteId, resolvedExternalPath.replace(/^\/+/, "")].filter(Boolean).join("/");
      preflight.apply_url = `${preflight.canonicalUrl.replace(/\/$/, "")}/apply`;
      preflight.job_info = {
        id: cleanText(jobInfo.id || ""),
        job_req_id: cleanText(jobInfo.jobReqId || ""),
        job_posting_id: cleanText(jobInfo.jobPostingId || ""),
        job_posting_site_id: cleanText(jobInfo.jobPostingSiteId || ""),
      };
      preflight.api_schema = await discoverWorkdayApiSchema(preflight);
      debugLog(
        task?.task_uuid || "workday",
        "workday_preflight_schema",
        getSchemaQuestions(preflight.api_schema || {}).length,
        JSON.stringify((preflight.api_schema?.probes || []).map((probe) => ({ status: probe.status, questions: probe.question_count })))
      );
    } else {
      preflight.errors.push(`Workday job details failed with ${job.status || "network error"}.`);
    }
  }

  return preflight;
}

async function waitForWorkdayShell(page) {
  await page.waitForNetworkIdle({ idleTime: 1200, timeout: workdayShellTimeoutMs }).catch(() => {});
  await page
    .waitForFunction(
      () => {
        const text = String(document.body?.innerText || "").replace(/\s+/g, " ").trim();
        return /apply|sign in|create account|job requisition|search for jobs|page is loaded/i.test(text);
      },
      { timeout: workdayShellTimeoutMs }
    )
    .catch(() => {});
}

async function waitForWorkdayApplicationContent(page) {
  await waitForWorkdayShell(page);
  await page
    .waitForFunction(
      () => {
        const text = String(document.body?.innerText || "").replace(/\s+/g, " ").trim();
        const hasFields = document.querySelectorAll("input:not([type='hidden']), textarea, select").length > 0;
        const hasFileInput = document.querySelectorAll("input[type='file']").length > 0;
        const hasUploadControl = Array.from(document.querySelectorAll("button, a, [role='button'], label"))
          .some((element) => /select file|upload.*(?:resume|cv)|attach.*(?:resume|cv)/i.test(String(element.textContent || element.getAttribute("aria-label") || "")));
        return (
          hasFields ||
          hasFileInput ||
          hasUploadControl ||
          /create account|sign in|verification code|security code|check your email/i.test(text)
        );
      },
      { timeout: workdayShellTimeoutMs + 10000 }
    )
    .catch(() => {});
}

async function waitForWorkdayLoginContent(page) {
  await withTimeout(waitForWorkdayShell(page), 25000, null).catch(() => {});
  await page.waitForFunction(
    () => {
      const text = String(document.body?.innerText || "").replace(/\s+/g, " ").trim();
      const fields = Array.from(document.querySelectorAll("input:not([type='hidden']), textarea, select"));
      return fields.some((field) =>
        /email|password|username|user/i.test(
          `${field.name || ""} ${field.id || ""} ${field.getAttribute("autocomplete") || ""} ${field.getAttribute("aria-label") || ""}`
        )
      ) || /email address|password|sign in|log in|login/i.test(text);
    },
    { timeout: 25000 }
  ).catch(() => {});
}

async function clickWorkdayByAutomationOrText(page, automationIds, patterns) {
  for (const automationId of automationIds || []) {
    const selector = `[data-automation-id="${String(automationId).replace(/\\/g, "\\\\").replace(/"/g, '\\"')}"]`;
    const handles = await page.$$(selector).catch(() => []);
    const scored = [];
    for (const candidate of handles) {
      const meta = await candidate
        .evaluate((element) => {
          const style = window.getComputedStyle(element);
          const rect = element.getBoundingClientRect();
          const centerX = rect.left + rect.width / 2;
          const centerY = rect.top + rect.height / 2;
          const topElement =
            centerX >= 0 && centerY >= 0 && centerX <= window.innerWidth && centerY <= window.innerHeight
              ? document.elementFromPoint(centerX, centerY)
              : null;
          const visible =
            style.visibility !== "hidden" &&
            style.display !== "none" &&
            rect.width > 0 &&
            rect.height > 0;
          return {
            visible,
            in_dialog: Boolean(element.closest('[role="dialog"], [aria-modal="true"]')),
            frontmost: Boolean(topElement && (topElement === element || element.contains(topElement))),
            top: rect.top,
          };
        })
        .catch(() => ({ visible: false }));
      if (meta.visible) {
        scored.push({ handle: candidate, meta });
      }
    }
    scored.sort((a, b) => {
      const aScore = (a.meta.in_dialog ? 4 : 0) + (a.meta.frontmost ? 2 : 0);
      const bScore = (b.meta.in_dialog ? 4 : 0) + (b.meta.frontmost ? 2 : 0);
      return bScore - aScore || b.meta.top - a.meta.top;
    });
    const handle = scored[0]?.handle || null;
    if (!handle) {
      continue;
    }
    const interactable = await handle
      .evaluate((element) => {
        const style = window.getComputedStyle(element);
        const rect = element.getBoundingClientRect();
        if (
          element.disabled ||
          element.getAttribute("aria-disabled") === "true" ||
          style.visibility === "hidden" ||
          style.display === "none" ||
          rect.width <= 0 ||
          rect.height <= 0
        ) {
          return false;
        }
        element.scrollIntoView({ block: "center", inline: "center" });
        return true;
      })
      .catch(() => false);
    const clicked = interactable ? await handle.click({ delay: 25 }).then(() => true).catch(() => false) : false;
    await Promise.all(handles.map((item) => item.dispose().catch(() => {})));
    if (clicked) {
      return true;
    }
  }
  return page.evaluate(
    ({ automationIds, patternSources }) => {
      const regexes = patternSources.map((source) => new RegExp(source, "i"));
      const isVisible = (element) => {
        const style = window.getComputedStyle(element);
        const rect = element.getBoundingClientRect();
        return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
      };
      const candidates = Array.from(document.querySelectorAll("button, a, [role='button'], input[type='submit'], input[type='button']"));
      const matches = candidates
        .filter((element) => {
        if (!isVisible(element)) {
          return false;
        }
        const automationId = element.getAttribute("data-automation-id") || "";
        const text = `${element.textContent || ""} ${element.getAttribute("aria-label") || ""} ${element.getAttribute("value") || ""}`;
        return automationIds.includes(automationId) || regexes.some((regex) => regex.test(text));
      })
        .map((element) => {
          const rect = element.getBoundingClientRect();
          const centerX = rect.left + rect.width / 2;
          const centerY = rect.top + rect.height / 2;
          const topElement =
            centerX >= 0 && centerY >= 0 && centerX <= window.innerWidth && centerY <= window.innerHeight
              ? document.elementFromPoint(centerX, centerY)
              : null;
          const score =
            (element.closest('[role="dialog"], [aria-modal="true"]') ? 4 : 0) +
            (topElement && (topElement === element || element.contains(topElement)) ? 2 : 0);
          return { element, score, top: rect.top };
        })
        .sort((a, b) => b.score - a.score || b.top - a.top);
      const target = matches[0]?.element || null;
      if (!target) {
        return false;
      }
      target.scrollIntoView({ block: "center", inline: "center" });
      target.click();
      return true;
    },
    { automationIds, patternSources: patterns.map((pattern) => pattern.source) }
  ).catch(() => false);
}

async function getWorkdayApplyHref(page) {
  return page.evaluate(() => {
    const link = Array.from(document.querySelectorAll("a[href]")).find((candidate) => {
      const automationId = candidate.getAttribute("data-automation-id") || "";
      const text = `${candidate.textContent || ""} ${candidate.getAttribute("aria-label") || ""}`;
      return automationId === "adventureButton" || /\bapply\b/i.test(text);
    });
    return link ? link.href || "" : "";
  }).catch(() => "");
}

async function chooseWorkdayApplicationStartRoute(page, preferResume = true, applyUrl = "") {
  if (applyUrl) {
    const directRoute = `${applyUrl.replace(/\/$/, "")}/${preferResume ? "autofillWithResume" : "applyManually"}`;
    await withTimeout(
      page.goto(directRoute, { waitUntil: "domcontentloaded", timeout: Math.min(navigationTimeoutMs, 25000) }),
      30000,
      null
    ).catch(() => {});
    await withTimeout(waitForWorkdayShell(page), 25000, null).catch(() => {});
    return {
      attempted: true,
      clicked: true,
      route: preferResume ? "autofill_with_resume" : "apply_manually",
      href: directRoute,
      direct_navigation: true,
    };
  }
  const state = await getWorkdayVisibleState(page);
  const buttonText = cleanText((state.buttons || []).map((button) => `${button.automation_id} ${button.text}`).join(" "));
  const hasStartRouteOptions = /autofillWithResume|applyManually|useMyLastApplication|Autofill with Resume|Apply Manually/i.test(buttonText);
  if (!hasStartRouteOptions && applyUrl) {
    const directRoute = `${applyUrl.replace(/\/$/, "")}/${preferResume ? "autofillWithResume" : "applyManually"}`;
    await withTimeout(
      page.goto(directRoute, { waitUntil: "domcontentloaded", timeout: Math.min(navigationTimeoutMs, 25000) }),
      30000,
      null
    ).catch(() => {});
    await withTimeout(waitForWorkdayShell(page), 25000, null).catch(() => {});
    return {
      attempted: true,
      clicked: true,
      route: preferResume ? "autofill_with_resume" : "apply_manually",
      href: directRoute,
      direct_navigation: true,
    };
  }
  if (!hasStartRouteOptions) {
    return { attempted: false, clicked: false, route: "" };
  }
  const routes = preferResume
    ? [
        { route: "autofill_with_resume", ids: ["autofillWithResume"], patterns: [/autofill with resume/, /use resume/, /resume/] },
        { route: "apply_manually", ids: ["applyManually"], patterns: [/apply manually/, /manual/] },
      ]
    : [
        { route: "apply_manually", ids: ["applyManually"], patterns: [/apply manually/, /manual/] },
        { route: "autofill_with_resume", ids: ["autofillWithResume"], patterns: [/autofill with resume/, /use resume/, /resume/] },
      ];
  for (const option of routes) {
    const clicked = await withTimeout(clickWorkdayByAutomationOrText(page, option.ids, option.patterns), workdayShellTimeoutMs, false);
    if (clicked) {
      await withTimeout(waitForWorkdayShell(page), workdayShellTimeoutMs + 1000, null);
      return { attempted: true, clicked: true, route: option.route };
    }
  }
  const href = await page.evaluate((wantedIds) => {
    const link = Array.from(document.querySelectorAll("a[href]")).find((candidate) =>
      wantedIds.includes(candidate.getAttribute("data-automation-id") || "")
    );
    return link ? link.href || "" : "";
  }, routes.flatMap((route) => route.ids)).catch(() => "");
  if (href) {
    await withTimeout(
      page.goto(href, { waitUntil: "domcontentloaded", timeout: Math.min(navigationTimeoutMs, 25000) }),
      30000,
      null
    ).catch(() => {});
    await withTimeout(waitForWorkdayShell(page), 25000, null).catch(() => {});
    return { attempted: true, clicked: true, route: routes[0]?.route || "apply_start_href", href };
  }
  return { attempted: true, clicked: false, route: "" };
}

function buildWorkdayCandidateHomeUrl(preflight = {}) {
  return [
    cleanText(preflight.origin || "").replace(/\/$/, ""),
    cleanText(preflight.locale || "en-US"),
    cleanText(preflight.siteId || ""),
    "userHome",
  ].filter(Boolean).join("/");
}

async function continueWorkdayDraftFromCandidateHome(page, task, preflight) {
  const candidateHomeUrl = buildWorkdayCandidateHomeUrl(preflight);
  const debugId = task.task_uuid || "task";
  const result = {
    attempted: false,
    candidate_home_url: candidateHomeUrl,
    opened_candidate_home: false,
    found_draft: false,
    opened_action_menu: false,
    clicked_continue: false,
    state: {},
    reason: "",
  };
  if (!candidateHomeUrl || !preflight.account_required) {
    result.reason = "candidate_home_not_applicable";
    return result;
  }
  result.attempted = true;
  debugLog(debugId, "workday_candidate_home_goto", candidateHomeUrl);
  await withTimeout(
    page.goto(candidateHomeUrl, { waitUntil: "domcontentloaded", timeout: Math.min(navigationTimeoutMs, 25000) }),
    30000,
    null
  ).catch(() => {});
  await withTimeout(waitForWorkdayShell(page), 25000, null).catch(() => {});
  result.opened_candidate_home = /\/userHome\b/i.test(page.url()) || /candidate home/i.test(cleanText(await page.title().catch(() => "")));
  let state = await withTimeout(getWorkdayVisibleState(page), 10000, {});
  debugLog(debugId, "workday_candidate_home_state", JSON.stringify({
    opened: result.opened_candidate_home,
    url: page.url(),
    field_count: state.field_count,
    has_sign_in: state.has_sign_in,
    has_create_account: state.has_create_account,
    text: cleanText(state.text_sample || "").slice(0, 180),
  }));
  const homeText = cleanText(state.text_sample || "");
  if (state.has_sign_in || state.has_create_account || !/candidate home|my applications|continue application|not submitted/i.test(homeText)) {
    result.state = state;
    result.reason = "candidate_home_not_authenticated_or_no_drafts";
    return result;
  }
  const targetTitle = cleanText(preflight.job_title || task.role_title || "");
  const targetReq = cleanText(preflight.job_info?.job_req_id || task.job_req_id || "");
  result.found_draft = await page.evaluate(({ title, req }) => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const compact = (value) => clean(value).toLowerCase().replace(/[^a-z0-9]+/g, "");
    const titleKey = compact(title);
    const rows = Array.from(document.querySelectorAll("tr, [role='row'], li, section, div"))
      .filter((row) => {
        const text = clean(row.textContent || "");
        if (!/not submitted|continue application|view application|delete application/i.test(text)) return false;
        return (titleKey && compact(text).includes(titleKey.slice(0, Math.min(titleKey.length, 30)))) || (req && text.includes(req));
      })
      .sort((a, b) => clean(a.textContent || "").length - clean(b.textContent || "").length);
    const row = rows[0] || null;
    if (!row) return false;
    row.setAttribute("data-sffc-workday-target-draft", "1");
    return true;
  }, { title: targetTitle, req: targetReq }).catch(() => false);
  debugLog(debugId, "workday_candidate_home_draft", JSON.stringify({
    found: result.found_draft,
    title: targetTitle,
    req: targetReq,
  }));
  if (!result.found_draft) {
    result.state = state;
    result.reason = "matching_draft_not_found";
    return result;
  }
  result.opened_action_menu = await page.evaluate(() => {
    const row = document.querySelector("[data-sffc-workday-target-draft='1']");
    if (!row) return false;
    const visible = (element) => {
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
    };
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const buttons = Array.from(row.querySelectorAll("button, [role='button'], a"))
      .filter(visible)
      .map((button) => ({
        button,
        text: clean(`${button.textContent || ""} ${button.getAttribute("aria-label") || ""} ${button.getAttribute("title") || ""}`),
        automation: button.getAttribute("data-automation-id") || "",
        rect: button.getBoundingClientRect(),
      }))
      .sort((a, b) => b.rect.left - a.rect.left);
    const menuButton =
      buttons.find((entry) => /actions?|more|ellipsis|menu|⋯|\.{3}/i.test(`${entry.text} ${entry.automation}`)) ||
      buttons[buttons.length - 1];
    if (!menuButton) return false;
    menuButton.button.scrollIntoView({ block: "center", inline: "center" });
    menuButton.button.click();
    return true;
  }).catch(() => false);
  debugLog(debugId, "workday_candidate_home_action_menu", result.opened_action_menu);
  if (!result.opened_action_menu) {
    result.state = await getWorkdayVisibleState(page);
    result.reason = "action_menu_not_found";
    return result;
  }
  await new Promise((resolve) => setTimeout(resolve, 700));
  result.clicked_continue = await clickWorkdayByAutomationOrText(
    page,
    ["continueApplication", "continueApplicationButton"],
    [/continue application/i]
  );
  debugLog(debugId, "workday_candidate_home_continue", result.clicked_continue);
  if (result.clicked_continue) {
    await withTimeout(waitForWorkdayApplicationContent(page), 30000, null).catch(() => {});
    result.state = await withTimeout(getWorkdayVisibleState(page), 10000, {});
    if (
      !Number(result.state.field_count || 0) &&
      !Number(result.state.file_input_count || 0) &&
      !Number(result.state.upload_control_count || 0)
    ) {
      const stepState = await withTimeout(getWorkdayStepState(page), 10000, {});
      const stage = getWorkdayStageFromState(result.state, stepState);
      if (["my_information", "my_experience", "application_questions", "voluntary_disclosures", "review"].includes(stage)) {
        await withTimeout(waitForWorkdayStageControls(page, stage), 35000, null).catch(() => {});
        result.state = await withTimeout(getWorkdayVisibleState(page), 10000, result.state);
      }
    }
    debugLog(debugId, "workday_candidate_home_resume_result", JSON.stringify({
      url: page.url(),
      field_count: result.state.field_count,
      text: cleanText(result.state.text_sample || "").slice(0, 180),
    }));
    return result;
  }
  result.state = await getWorkdayVisibleState(page);
  result.reason = "continue_application_not_found";
  return result;
}

async function getWorkdayVisibleState(page) {
  const fallback = () => ({ title: "", url: page.url(), text_sample: "", field_count: 0, file_input_count: 0, fields: [], buttons: [] });
  return withTimeout(page.evaluate(() => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const visible = (element) => {
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
    };
    const fields = Array.from(document.querySelectorAll("input, textarea, select"))
      .filter((field) => field.type !== "hidden" && visible(field))
      .map((field) => ({
        tag: field.tagName.toLowerCase(),
        type: field.getAttribute("type") || "",
        name: field.getAttribute("name") || "",
        id: field.getAttribute("id") || "",
        automation_id: field.getAttribute("data-automation-id") || "",
        autocomplete: field.getAttribute("autocomplete") || "",
        required: field.required || field.getAttribute("aria-required") === "true",
      }));
    const buttons = Array.from(document.querySelectorAll("button, a, [role='button']"))
      .filter(visible)
      .map((button) => ({
        text: clean(`${button.textContent || ""} ${button.getAttribute("aria-label") || ""}`),
        href: button.href || "",
        automation_id: button.getAttribute("data-automation-id") || "",
      }))
      .filter((button) => button.text || button.href || button.automation_id);
    const uploadControls = buttons.filter((button) =>
      /select file|upload.*(?:resume|cv)|attach.*(?:resume|cv)/i.test(`${button.text} ${button.automation_id}`)
    );
    const text = clean(document.body?.innerText || "");
    return {
      title: document.title || "",
      url: location.href,
      text_sample: text.slice(0, 1600),
      field_count: fields.length,
      file_input_count: fields.filter((field) => field.type === "file").length,
      upload_control_count: uploadControls.length,
      fields: fields.slice(0, 120),
      buttons: buttons.slice(0, 80),
      has_apply: buttons.some((button) => /apply/i.test(button.text) || button.automation_id === "adventureButton"),
      has_create_account: /create account/i.test(text) || buttons.some((button) => /create account/i.test(button.text)),
      has_sign_in: /sign in|log in|login/i.test(text) || buttons.some((button) => /sign in|log in|login/i.test(button.text)),
      has_verification: /verification code|security code|check your email|verify your email/i.test(text),
      has_submission_confirmation: /thank you for submitting|application submitted|successfully submitted/i.test(text),
    };
  }), workdayShellTimeoutMs, fallback()).catch(() => fallback());
}

async function getWorkdayStepState(page) {
  return page.evaluate(() => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const compact = (value) => clean(value).toLowerCase().replace(/[^a-z0-9]+/g, "");
    const isVisible = (element) => {
      if (!element) return false;
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
    };
    const bodyText = clean(document.body?.innerText || "");
    const stepNodes = Array.from(
      document.querySelectorAll(
        [
          "[aria-current='step']",
          "[data-automation-id*='step' i]",
          "[data-automation-id*='progress' i]",
          "[role='listitem']",
          "nav li",
        ].join(",")
      )
    )
      .filter(isVisible)
      .map((node) => clean(`${node.textContent || ""} ${node.getAttribute("aria-label") || ""}`))
      .filter(Boolean)
      .slice(0, 40);
    const signals = [
      { step: "account", patterns: [/create account/i, /sign in/i, /password requirements/i] },
      { step: "verification", patterns: [/verification code/i, /security code/i, /verify your email/i, /check your email/i] },
      { step: "my_experience", patterns: [/my experience/i, /work experience/i, /school or university/i, /overall result/i] },
      { step: "resume", patterns: [/upload.*(?:resume|cv)/i, /resume\/cv/i, /autofill/i] },
      { step: "my_information", patterns: [/my information/i, /legal name/i, /contact information/i, /address/i, /phone/i] },
      { step: "application_questions", patterns: [/application questions/i, /questionnaire/i, /work authorization/i, /sponsorship/i] },
      { step: "voluntary_disclosures", patterns: [/voluntary/i, /self-identification/i, /diversity/i, /gender/i, /disability/i, /veteran/i] },
      { step: "review", patterns: [/review/i, /summary/i, /submit application/i] },
      { step: "submitted", patterns: [/application submitted/i, /successfully submitted/i, /thank you for applying/i] },
    ];
    const currentStepText = clean(
      (bodyText.match(/current step\s+\d+\s+of\s+\d+\s+([A-Za-z ]{2,80}?)(?:\s+step\s+\d+\s+of|\s+[A-Z][a-z]+\s+\*|\s+\*\s+Indicates|$)/i) || [])[1] ||
        stepNodes.find((text) => /current step/i.test(text)) ||
        ""
    );
    const activeMatched = currentStepText
      ? signals.find((signal) => signal.patterns.some((pattern) => pattern.test(currentStepText)))
      : null;
    const matched = activeMatched || signals.find((signal) =>
      signal.patterns.some((pattern) => pattern.test(bodyText) || stepNodes.some((text) => pattern.test(text)))
    );
    const activeStep = matched ? matched.step : "";
    const errorSelectors = [
      "[role='alert']",
      "[aria-live='assertive']",
      "[data-automation-id*='error' i]",
      "[data-automation-id*='validation' i]",
      "[class*='error' i]",
      "[class*='invalid' i]",
    ];
    const validationErrors = Array.from(document.querySelectorAll(errorSelectors.join(",")))
      .filter(isVisible)
      .map((node) => clean(node.textContent || ""))
      .filter((text, index, list) => text && !/^svg$/i.test(text) && list.indexOf(text) === index)
      .slice(0, 20);
    return {
      active_step: activeStep,
      step_labels: Array.from(new Set(stepNodes.map((text) => text.slice(0, 120)))),
      validation_errors: validationErrors,
      has_resume_signal: /resume|cv|upload|attachment/i.test(bodyText),
      has_review_signal: /review|submit application/i.test(bodyText),
      has_submission_signal: /application submitted|successfully submitted|thank you for applying/i.test(bodyText),
      body_compact_sample: compact(bodyText).slice(0, 600),
    };
  }).catch(() => ({
    active_step: "",
    step_labels: [],
    validation_errors: [],
    has_resume_signal: false,
    has_review_signal: false,
    has_submission_signal: false,
    body_compact_sample: "",
  }));
}

async function discoverWorkdayLiveSchema(page) {
  return page.evaluate(() => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const compact = (value) => clean(value).toLowerCase().replace(/[^a-z0-9]+/g, "");
    const isVisible = (element) => {
      if (!element) return false;
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
    };
    const setUnique = (items) => Array.from(new Set(items.map(clean).filter(Boolean)));
    const getScope = (control) => {
      const labelledBy = clean(control.getAttribute("aria-labelledby") || "");
      const labelScope = labelledBy
        .split(/\s+/)
        .map((id) => document.getElementById(id))
        .filter(Boolean)
        .map((label) => label.closest("div, fieldset, section, li"))
        .find((scope) => scope && scope.contains(control));
      if (labelScope) return labelScope;
      let current = control;
      for (let depth = 0; depth < 9 && current && current.parentElement; depth += 1) {
        current = current.parentElement;
        if (
          current.querySelector("input, textarea, select, [role='combobox'], [role='radio'], [role='checkbox']") &&
          (current.querySelector("label, [data-automation-id*='Label'], [id*='label' i]") ||
            /\*/.test(clean(current.textContent || "")))
        ) {
          return current;
        }
      }
      return control.closest("fieldset, section, li, div") || control.parentElement || control;
    };
    const getLabel = (scope, control) => {
      const id = clean(control.getAttribute("id") || "");
      const explicit = id ? clean(document.querySelector(`label[for="${CSS.escape(id)}"]`)?.textContent || "") : "";
      const labelledBy = clean(control.getAttribute("aria-labelledby") || "")
        .split(/\s+/)
        .map((part) => clean(document.getElementById(part)?.textContent || ""))
        .filter(Boolean)
        .join(" ");
      const direct = clean(
        explicit ||
          labelledBy ||
          control.getAttribute("aria-label") ||
          control.getAttribute("placeholder") ||
          control.getAttribute("data-automation-id") ||
          control.getAttribute("name") ||
          ""
      ).replace(/\*+$/, "");
      if (direct && !/^select\b/i.test(direct)) {
        return direct;
      }
      const labels = Array.from(scope.querySelectorAll("label, [data-automation-id*='Label'], [id*='label' i]"))
        .map((node) => clean(node.textContent || ""))
        .filter((text) => text && !/^select\b/i.test(text))
        .sort((a, b) => a.length - b.length);
      if (labels[0]) {
        return labels[0].replace(/\*+$/, "");
      }
      return clean(scope.textContent || "").split(/\s{2,}|(?=\*)/).map(clean).filter(Boolean)[0] || direct || "Required field";
    };
    const getOptions = (scope, controls) => {
      const options = [];
      controls.forEach((control) => {
        if (control.tagName === "SELECT") {
          options.push(...Array.from(control.options || []).map((option) => clean(option.textContent || option.value || "")));
        }
        const id = clean(control.getAttribute("id") || "");
        if (id) {
          const label = document.querySelector(`label[for="${CSS.escape(id)}"]`);
          if (label) options.push(clean(label.textContent || ""));
        }
        const wrapper = control.closest("label, [role='radio'], [role='checkbox']");
        if (wrapper) options.push(clean(wrapper.textContent || ""));
      });
      options.push(
        ...Array.from(scope.querySelectorAll("[role='option'], [data-automation-id*='promptOption'], [data-automation-id*='radio']"))
          .filter(isVisible)
          .map((node) => clean(node.textContent || node.getAttribute("aria-label") || ""))
      );
      return setUnique(options).filter((option) => !/^select$/i.test(option));
    };
    const controls = Array.from(
      document.querySelectorAll("input, textarea, select, [role='combobox'], [role='radio'], [role='checkbox']")
    ).filter((control) => {
      if (control.disabled || control.getAttribute("aria-hidden") === "true") return false;
      if (control.type === "hidden") return false;
      if (control.type === "file") return true;
      return isVisible(control);
    });
    const groups = new Map();
    controls.forEach((control) => {
      const type = clean(control.getAttribute("type") || control.getAttribute("role") || control.tagName).toLowerCase();
      const name = clean(control.getAttribute("name") || control.getAttribute("data-automation-id") || control.getAttribute("id") || "");
      const scope = getScope(control);
      const label = getLabel(scope, control);
      const key = type === "radio" || control.getAttribute("role") === "radio"
        ? `radio:${name || compact(label)}`
        : `${name || compact(label)}:${groups.size}`;
      if (!groups.has(key)) groups.set(key, { scope, controls: [] });
      groups.get(key).controls.push(control);
    });
    const questions = [];
    for (const group of groups.values()) {
      const first = group.controls[0];
      const type = clean(first.getAttribute("type") || first.getAttribute("role") || first.tagName).toLowerCase();
      const role = clean(first.getAttribute("role") || "");
      const label = getLabel(group.scope, first);
      const required =
        group.controls.some((control) => control.required || control.getAttribute("aria-required") === "true") ||
        /\*/.test(clean(group.scope.textContent || "")) ||
        /required/i.test(clean(group.scope.textContent || ""));
      const isChoice = /radio|checkbox/.test(type) || /radio|checkbox/.test(role);
      const isSelect =
        first.tagName === "SELECT" ||
        role === "combobox" ||
        first.getAttribute("aria-haspopup") === "listbox" ||
        first.getAttribute("aria-autocomplete") === "list";
      const liveType = isChoice
        ? type.includes("checkbox") || role === "checkbox"
          ? "checkbox"
          : "radio"
        : first.type === "file"
          ? "file"
          : isSelect
            ? "select"
            : first.tagName === "TEXTAREA"
              ? "textarea"
              : /date/i.test(label)
                ? "date"
                : "text";
      const names = setUnique(
        group.controls.map((control) =>
          clean(control.getAttribute("name") || control.getAttribute("data-automation-id") || control.getAttribute("id") || "")
        )
      );
      questions.push({
        name: names[0] || compact(label),
        label,
        type: liveType,
        required,
        options: getOptions(group.scope, group.controls),
        fields: names.map((name) => ({ name, type: liveType })),
      });
    }
    return {
      provider: "workday",
      questions: questions.filter((question, index, list) => {
        const key = `${compact(question.name)}:${compact(question.label)}`;
        return question.label && !/captcha|recaptcha|turnstile/i.test(question.label) && list.findIndex((item) => `${compact(item.name)}:${compact(item.label)}` === key) === index;
      }),
    };
  }).catch(() => ({ provider: "workday", questions: [] }));
}

async function fillWorkdayInput(page, automationId, value) {
  if (!value) {
    return false;
  }
  const selectors = [
    `[role="dialog"] input[data-automation-id="${automationId}"]`,
    `[role="dialog"] textarea[data-automation-id="${automationId}"]`,
    `[aria-modal="true"] input[data-automation-id="${automationId}"]`,
    `[aria-modal="true"] textarea[data-automation-id="${automationId}"]`,
    `input[data-automation-id="${automationId}"]`,
    `textarea[data-automation-id="${automationId}"]`,
  ];
  const fields = [];
  for (const selector of selectors) {
    fields.push(...(await page.$$(selector)));
  }
  const seen = new Set();
  const candidates = [];
  for (const field of fields) {
    const meta = await field
      .evaluate((element) => {
        const style = window.getComputedStyle(element);
        const rect = element.getBoundingClientRect();
        const centerX = rect.left + rect.width / 2;
        const centerY = rect.top + rect.height / 2;
        const topElement =
          centerX >= 0 && centerY >= 0 && centerX <= window.innerWidth && centerY <= window.innerHeight
            ? document.elementFromPoint(centerX, centerY)
            : null;
        const visible =
          !element.disabled &&
          element.type !== "hidden" &&
          style.visibility !== "hidden" &&
          style.display !== "none" &&
          rect.width > 0 &&
          rect.height > 0;
        return {
          visible,
          in_dialog: Boolean(element.closest('[role="dialog"], [aria-modal="true"]')),
          frontmost: Boolean(topElement && (topElement === element || element.contains(topElement))),
          top: rect.top,
          left: rect.left,
          id: element.getAttribute("id") || element.getAttribute("name") || element.getAttribute("data-automation-id") || "",
        };
      })
      .catch(() => ({ visible: false }));
    const key = `${meta.id}:${meta.top}:${meta.left}`;
    if (!meta.visible || seen.has(key)) {
      continue;
    }
    seen.add(key);
    candidates.push({ field, meta });
  }
  candidates.sort((a, b) => {
    const aScore = (a.meta.in_dialog ? 4 : 0) + (a.meta.frontmost ? 2 : 0);
    const bScore = (b.meta.in_dialog ? 4 : 0) + (b.meta.frontmost ? 2 : 0);
    return bScore - aScore || b.meta.top - a.meta.top;
  });
  let filled = false;
  for (const candidate of candidates) {
    if (await fillInputHandleWithVerification(page, candidate.field, value)) {
      filled = true;
    }
  }
  if (filled) {
    return true;
  }
  return fillBySelectors(page, selectors, value);
}

async function fillWorkdayAccountFields(page, email, password) {
  const filledEmail = await fillWorkdayInput(page, "email", email);
  const filledPassword = await fillWorkdayInput(page, "password", password);
  const filledVerify = await fillWorkdayInput(page, "verifyPassword", password);
  return { email: filledEmail, password: filledPassword, verify_password: filledVerify };
}

async function fillWorkdayVerificationCode(page, code) {
  if (!code) {
    return false;
  }
  const selectors = [
    'input[data-automation-id*="verification" i]',
    'input[data-automation-id*="code" i]',
    'input[name*="verification" i]',
    'input[name*="code" i]',
    'input[autocomplete="one-time-code"]',
    'input[maxlength="6"]',
    'input[maxlength="8"]',
  ];
  const filled = await fillBySelectors(page, selectors, code);
  if (filled) {
    return true;
  }
  return fillByLabelText(page, [/verification code/, /security code/, /enter.*code/, /check.*email/], code);
}

function isWorkdayLoggedInState(state, account = {}) {
  const sample = cleanText(`${state?.title || ""} ${state?.text_sample || ""}`);
  const email = cleanText(account?.email || "");
  return /Candidate Home|My Account|Settings/i.test(sample) || Boolean(email && sample.includes(email));
}

function isWorkdayApplicationFormState(state = {}) {
  const sample = cleanText(`${state.title || ""} ${state.text_sample || ""}`);
  const hasControls =
    Number(state.field_count || 0) > 0 ||
    Number(state.file_input_count || 0) > 0 ||
    Number(state.upload_control_count || 0) > 0;
  const hasApplicationStep = /Autofill with Resume|Drop file here|Select file|My Information|My Experience|Application Questions|Voluntary Disclosures|Review/i.test(sample);
  const hasAccountStep = /Create Account|Sign In|Verification Code|Security Code/i.test(sample);
  if (!hasControls && !hasAccountStep) {
    return false;
  }
  return Boolean(
    hasControls ||
      hasApplicationStep ||
      hasAccountStep
  );
}

async function createWorkdayAccountIfAllowed(page, task, preflight) {
  const account = getWorkdayAccount(task);
  const generatedPassword = account.allow_generated_password && !account.password ? generateWorkdayPassword() : "";
  const password = account.password || generatedPassword;
  const result = {
    attempted: false,
    clicked_create_account: false,
    submitted_create_account: false,
    generated_password: Boolean(generatedPassword),
    requires_consent: false,
    verification_required: false,
    account_action_blocked: false,
    field_fill: {},
    last_error: "",
  };
  if (!preflight.account_required) {
    return result;
  }
  debugLog(task?.task_uuid || "workday", "workday_account_route_start", JSON.stringify({
    create_account: account.create_account,
    sign_in: account.sign_in,
    email: Boolean(account.email),
    password: Boolean(password),
  }));
  if (!account.create_account && account.sign_in && account.email && password) {
    result.attempted = true;
    await clickWorkdayByAutomationOrText(page, ["utilityButtonSignIn", "signInLink"], [/sign in/, /log in/, /login/]);
    await waitForWorkdayLoginContent(page);
    result.field_fill = {
      email: await fillWorkdayInput(page, "email", account.email),
      password: await fillWorkdayInput(page, "password", password),
      verify_password: true,
    };
    if (!result.field_fill.email || !result.field_fill.password) {
      result.last_error = "The Workday sign-in fields were not available.";
      return result;
    }
    if (!allowWorkdayAccountCreation) {
      result.account_action_blocked = true;
      result.last_error = "Dry run stopped before signing into the tenant-specific Workday account.";
      return result;
    }
    result.submitted_create_account = await clickWorkdayByAutomationOrText(
      page,
      ["signInSubmitButton", "submitButton"],
      [/^sign in$/, /^log in$/, /^login$/]
    );
    await withTimeout(waitForWorkdayShell(page), 25000, null).catch(() => {});
    const after = await withTimeout(getWorkdayVisibleState(page), 10000, {});
    result.after_submit_state = after;
    result.verification_required = Boolean(after.has_verification || preflight.account_verification);
    if (!result.submitted_create_account) {
      result.last_error = "The worker could not click the Workday sign-in button.";
    } else if (result.verification_required) {
      result.last_error = "Workday appears to require email verification before the application can continue.";
    }
    return result;
  }
  if (!account.create_account || !account.email || !password) {
    result.requires_consent = true;
    result.last_error =
      "This Workday tenant requires a candidate account. Get explicit consent and provide tenant-specific account credentials before the worker can continue.";
    return result;
  }

  result.attempted = true;
  debugLog(task?.task_uuid || "workday", "workday_account_click_create_start");
  result.clicked_create_account = await clickWorkdayByAutomationOrText(
    page,
    ["createAccountLink"],
    [/create account/, /register/, /sign up/]
  );
  debugLog(task?.task_uuid || "workday", "workday_account_click_create_result", result.clicked_create_account);
  if (!result.clicked_create_account) {
    const clickedSignIn = await withTimeout(
      clickWorkdayByAutomationOrText(page, ["utilityButtonSignIn"], [/sign in/, /log in/, /login/]),
      workdayShellTimeoutMs,
      false
    );
    if (clickedSignIn) {
      await withTimeout(waitForWorkdayShell(page), workdayShellTimeoutMs + 1000, null);
      result.clicked_create_account = await withTimeout(
        clickWorkdayByAutomationOrText(page, ["createAccountLink"], [/create account/, /register/, /sign up/]),
        workdayShellTimeoutMs,
        false
      );
    }
  }
  await withTimeout(waitForWorkdayShell(page), workdayShellTimeoutMs + 1000, null);
  const accountFormState = await getWorkdayVisibleState(page);
  if (!/create account/i.test(accountFormState.title || accountFormState.text_sample || "")) {
    result.last_error = "The worker could not switch the Workday sign-in modal into create-account mode.";
    result.before_submit_state = accountFormState;
    return result;
  }
  debugLog(task?.task_uuid || "workday", "workday_account_fill_fields_start");
  result.field_fill = await fillWorkdayAccountFields(page, account.email, password);
  const state = await getWorkdayVisibleState(page);
  debugLog(task?.task_uuid || "workday", "workday_account_fill_fields_result", JSON.stringify({
    field_fill: result.field_fill,
    state: {
      url: state.url,
      field_count: state.field_count,
      has_create_account: state.has_create_account,
      has_sign_in: state.has_sign_in,
      has_verification: state.has_verification,
      buttons: (state.buttons || []).map((button) => `${button.automation_id}:${button.text}`).slice(0, 12),
    },
  }));
  if (!result.field_fill.email || !result.field_fill.password || !result.field_fill.verify_password) {
    result.last_error = "The Workday account creation fields were not all available.";
    return result;
  }
  if (!allowWorkdayAccountCreation) {
    result.account_action_blocked = true;
    result.last_error = "Dry run stopped before creating a tenant-specific Workday account.";
    return result;
  }
  debugLog(task?.task_uuid || "workday", "workday_account_submit_create_start");
  result.submitted_create_account = await clickWorkdayByAutomationOrText(
    page,
    ["createAccountSubmitButton"],
    [/^create account$/]
  );
  debugLog(task?.task_uuid || "workday", "workday_account_submit_create_result", result.submitted_create_account);
  await waitForWorkdayApplicationContent(page);
  let after = await getWorkdayVisibleState(page);
  let afterCompletion = await getRequiredFormCompletionState(page).catch(() => ({
    complete_required_fields: [],
    missing_required_fields: [],
  }));
  result.after_first_submit_completion = afterCompletion;
  if (
    result.submitted_create_account &&
    after.has_create_account &&
    afterCompletion.missing_required_fields.some((field) => /password/i.test(field))
  ) {
    debugLog(task?.task_uuid || "workday", "workday_account_retry_password_fill_start");
    result.retry_field_fill = await fillWorkdayAccountFields(page, account.email, password);
    result.retried_create_account = await clickWorkdayByAutomationOrText(
      page,
      ["createAccountSubmitButton"],
      [/^create account$/]
    );
    await waitForWorkdayApplicationContent(page);
    after = await getWorkdayVisibleState(page);
    afterCompletion = await getRequiredFormCompletionState(page).catch(() => ({
      complete_required_fields: [],
      missing_required_fields: [],
    }));
    result.after_retry_submit_completion = afterCompletion;
    debugLog(task?.task_uuid || "workday", "workday_account_retry_result", JSON.stringify({
      retry_field_fill: result.retry_field_fill,
      retried_create_account: result.retried_create_account,
      missing_required_fields: afterCompletion.missing_required_fields,
      title: after.title,
      url: after.url,
    }));
  }
  result.verification_required = Boolean(after.has_verification || preflight.account_verification);
  if (!result.submitted_create_account) {
    result.last_error = "The worker could not click the Workday create account button.";
  } else if (result.verification_required) {
    result.last_error = "Workday appears to require email verification before the application can continue.";
  }
  result.before_submit_state = state;
  result.after_submit_state = after;
  return result;
}

async function ensureWorkdayApplicationFlowReady(page, task, preflight) {
  const result = {
    opened_apply: false,
    account_flow: null,
    candidate_home_resume: null,
    form_ready: false,
    state: {},
  };
  await waitForWorkdayShell(page);
  result.candidate_home_resume = await continueWorkdayDraftFromCandidateHome(page, task, preflight).catch((error) => ({
    attempted: true,
    clicked_continue: false,
    reason: error?.message || String(error),
  }));
  if (result.candidate_home_resume?.clicked_continue) {
    result.opened_apply = true;
    result.opened_apply_from_candidate_home = true;
    const state = await getWorkdayVisibleState(page);
    result.form_ready = Boolean(
      isWorkdayApplicationFormState(state) &&
        !state.has_create_account &&
        !state.has_sign_in
    );
    result.state = state;
    debugLog(task?.task_uuid || "workday", "workday_candidate_home_resume_result", JSON.stringify({
      clicked_continue: true,
      url: state.url,
      field_count: state.field_count,
      file_input_count: state.file_input_count,
      upload_control_count: state.upload_control_count,
    }));
    return result;
  }
  debugLog(task?.task_uuid || "workday", "workday_candidate_home_resume_result", JSON.stringify({
    attempted: Boolean(result.candidate_home_resume?.attempted),
    clicked_continue: false,
    reason: result.candidate_home_resume?.reason || "",
  }));
  debugLog(task?.task_uuid || "workday", "workday_click_apply_start");
  if (cleanText(preflight.apply_url || "")) {
    await withTimeout(
      page.goto(preflight.apply_url, { waitUntil: "domcontentloaded", timeout: Math.min(navigationTimeoutMs, 25000) }),
      30000,
      null
    ).catch(() => {});
    await withTimeout(waitForWorkdayShell(page), 25000, null).catch(() => {});
    result.opened_apply = true;
    result.opened_apply_direct = true;
    debugLog(task?.task_uuid || "workday", "workday_click_apply_result", "direct", preflight.apply_url);
  } else {
    result.opened_apply = await withTimeout(clickWorkdayByAutomationOrText(page, ["adventureButton"], [/\bapply\b/]), workdayShellTimeoutMs, false);
    debugLog(task?.task_uuid || "workday", "workday_click_apply_result", result.opened_apply);
    if (result.opened_apply) {
      await withTimeout(waitForWorkdayShell(page), 25000, null).catch(() => {});
    }
    const applyHref = (await getWorkdayApplyHref(page)) || cleanText(preflight.apply_url || "");
    if (applyHref && page.url() !== applyHref) {
      await withTimeout(
        page.goto(applyHref, { waitUntil: "domcontentloaded", timeout: Math.min(navigationTimeoutMs, 25000) }),
        30000,
        null
      ).catch(() => {});
      await withTimeout(waitForWorkdayShell(page), 25000, null).catch(() => {});
      result.opened_apply = true;
    }
  }
  debugLog(task?.task_uuid || "workday", "workday_start_route_start");
  result.start_route = await chooseWorkdayApplicationStartRoute(page, Boolean(task.__sffc_cv_path), preflight.apply_url || "");
  debugLog(task?.task_uuid || "workday", "workday_start_route_result", JSON.stringify(result.start_route));
  let state = await getWorkdayVisibleState(page);
  if (
    result.start_route?.route === "autofill_with_resume" &&
    !state.field_count &&
    !state.file_input_count &&
    !(state.buttons || []).length &&
    preflight.apply_url
  ) {
    const manualHref = `${preflight.apply_url.replace(/\/$/, "")}/applyManually`;
    debugLog(task?.task_uuid || "workday", "workday_start_route_blank_retry_manual", manualHref);
    await page.goto(manualHref, { waitUntil: "domcontentloaded", timeout: navigationTimeoutMs }).catch(() => {});
    await waitForWorkdayShell(page);
    result.start_route_retry = {
      attempted: true,
      clicked: true,
      route: "apply_manually",
      href: manualHref,
      direct_navigation: true,
      reason: "autofill_route_blank",
    };
    state = await getWorkdayVisibleState(page);
  }
  debugLog(task?.task_uuid || "workday", "workday_state_before_account", JSON.stringify({
    url: state.url,
    field_count: state.field_count,
    has_create_account: state.has_create_account,
    has_sign_in: state.has_sign_in,
    has_verification: state.has_verification,
    buttons: (state.buttons || []).map((button) => `${button.automation_id}:${button.text}`).slice(0, 12),
  }));
  const hasAccountUi =
    /\/login(?:\?|$)/i.test(state.url || page.url()) ||
    state.has_create_account ||
    state.has_sign_in ||
    /create account|sign in|log in|login|password/i.test(cleanText(state.text_sample || ""));
  if (hasAccountUi) {
    result.account_flow = await createWorkdayAccountIfAllowed(page, task, preflight);
    state = await getWorkdayVisibleState(page);
    const workdayAccount = getWorkdayAccount(task);
    if (
      result.account_flow?.submitted_create_account &&
      !result.account_flow?.verification_required &&
      isWorkdayLoggedInState(state, workdayAccount) &&
      preflight.apply_url
    ) {
      if (workdayAccount.sign_in && !workdayAccount.create_account) {
        debugLog(task?.task_uuid || "workday", "workday_account_signed_in_resume_candidate_home_start");
        const resumedAfterSignIn = await continueWorkdayDraftFromCandidateHome(page, task, preflight).catch((error) => ({
          attempted: true,
          clicked_continue: false,
          reason: error?.message || String(error),
        }));
        result.candidate_home_resume_after_sign_in = resumedAfterSignIn;
        state = resumedAfterSignIn.state || await withTimeout(getWorkdayVisibleState(page), 10000, {});
        debugLog(task?.task_uuid || "workday", "workday_account_signed_in_resume_candidate_home_result", JSON.stringify({
          clicked_continue: Boolean(resumedAfterSignIn.clicked_continue),
          reason: resumedAfterSignIn.reason || "",
          url: state.url || page.url(),
          field_count: state.field_count,
        }));
      }
      if (!state.field_count && !state.file_input_count && !state.upload_control_count && !state.has_verification) {
        const routePath = task.__sffc_cv_path ? "autofillWithResume" : "applyManually";
        const freshApplyHref = `${preflight.apply_url.replace(/\/$/, "")}/${routePath}`;
        debugLog(task?.task_uuid || "workday", "workday_account_created_reload_apply_route", freshApplyHref);
        await withTimeout(
          page.goto(freshApplyHref, { waitUntil: "domcontentloaded", timeout: Math.min(navigationTimeoutMs, 25000) }),
          30000,
          null
        ).catch(() => {});
        await withTimeout(waitForWorkdayApplicationContent(page), 30000, null).catch(() => {});
        state = await withTimeout(getWorkdayVisibleState(page), 10000, {});
      }
    }
    if (
      result.account_flow?.last_error &&
      /account creation fields were not all available/i.test(result.account_flow.last_error)
    ) {
      const retriedRoute = await chooseWorkdayApplicationStartRoute(page, Boolean(task.__sffc_cv_path), preflight.apply_url || "");
      if (retriedRoute.clicked) {
        result.start_route_retry = retriedRoute;
        result.account_flow = await createWorkdayAccountIfAllowed(page, task, preflight);
        state = await getWorkdayVisibleState(page);
      }
    }
    if (!state.field_count && !state.file_input_count && !state.has_verification && !result.account_flow?.requires_consent) {
      await clickWorkdayByAutomationOrText(page, ["adventureButton"], [/\bapply\b/]);
      await waitForWorkdayApplicationContent(page);
      await chooseWorkdayApplicationStartRoute(page, Boolean(task.__sffc_cv_path), preflight.apply_url || "");
      state = await getWorkdayVisibleState(page);
    }
    if (!state.field_count && !state.file_input_count && !state.has_verification && preflight.apply_url) {
      const routePaths = task.__sffc_cv_path
        ? ["autofillWithResume", "applyManually"]
        : ["applyManually", "autofillWithResume"];
      for (const routePath of routePaths) {
        const freshApplyHref = `${preflight.apply_url.replace(/\/$/, "")}/${routePath}`;
        debugLog(task?.task_uuid || "workday", "workday_blank_after_account_reload_apply_route", freshApplyHref);
        await page.goto(freshApplyHref, { waitUntil: "domcontentloaded", timeout: navigationTimeoutMs }).catch(() => {});
        await waitForWorkdayApplicationContent(page);
        state = await getWorkdayVisibleState(page);
        if (state.field_count || state.file_input_count || state.upload_control_count || state.has_verification || isWorkdayApplicationFormState(state)) {
          break;
        }
      }
    }
  }
  result.form_ready = Boolean(
    isWorkdayApplicationFormState(state) &&
      !result.account_flow?.requires_consent &&
      !result.account_flow?.account_action_blocked &&
      !state.has_create_account &&
      !state.has_sign_in
  );
  result.state = state;
  return result;
}

async function waitForWorkdayVerificationAndContinue(page, task, preflight, flow) {
  const accountFlow = flow.account_flow || {};
  if (!accountFlow.verification_required) {
    return { attempted: false, completed: false, timed_out: false, state: await getWorkdayVisibleState(page) };
  }
  const usedCodes = [];
  for (let attempt = 0; attempt < 3; attempt += 1) {
    const message =
      attempt > 0
        ? "Workday did not accept that code. Paste the latest verification code from the candidate email and I’ll try again in the same browser session."
        : "Workday has asked for an email verification code before this application can continue. Paste the code from the candidate email and I’ll enter it in the active Workday session.";
    if (disableVerificationCallback || !ajaxUrl || !workerToken) {
      return {
        attempted: true,
        completed: false,
        timed_out: false,
        state: await getWorkdayVisibleState(page),
        last_error: message,
      };
    }
    await completeTask(task.task_uuid, "verification_required", {
      provider: "workday",
      url: task.application_workspace_url || task.application_url || "",
      workday: { preflight, flow, final_state: await getWorkdayVisibleState(page) },
      browser_diagnostics: task.__sffc_browser_diagnostics || {},
      allow_final_submit: allowFinalSubmit,
      form_opened: flow.opened_apply,
      form_ready: flow.form_ready,
      verification_required: true,
      submission_confirmed: false,
      last_error: message,
      status: "verification_required",
    });
    const code = await waitForVerificationCode(task.task_uuid, usedCodes);
    if (!code) {
      return { attempted: true, completed: false, timed_out: true, state: await getWorkdayVisibleState(page) };
    }
    usedCodes.push(code);
    const filled = await fillWorkdayVerificationCode(page, code);
    if (!filled) {
      return {
        attempted: true,
        completed: false,
        timed_out: false,
        state: await getWorkdayVisibleState(page),
        last_error: "The worker could not find the Workday verification-code field.",
      };
    }
    const clicked = await clickWorkdayByAutomationOrText(
      page,
      ["verifyEmailSubmitButton", "submitButton", "signInSubmitButton", "createAccountSubmitButton"],
      [/verify/, /continue/, /^submit$/, /^sign in$/, /^create account$/]
    );
    await waitForWorkdayShell(page);
    const state = await getWorkdayVisibleState(page);
    if (!state.has_verification) {
      if (!state.field_count && !state.file_input_count) {
        await clickWorkdayByAutomationOrText(page, ["adventureButton"], [/\bapply\b/]);
        await waitForWorkdayShell(page);
      }
      return {
        attempted: true,
        completed: true,
        clicked,
        state: await getWorkdayVisibleState(page),
      };
    }
  }
  return {
    attempted: true,
    completed: false,
    timed_out: false,
    state: await getWorkdayVisibleState(page),
    last_error: "Workday still requires email verification after multiple code attempts.",
  };
}

async function uploadWorkdayFileInput(page, cvPath) {
  if (!cvPath) {
    return false;
  }
  const inputs = await page.$$("input[type=file]");
  const scoredInputs = [];
  for (const input of inputs) {
    const score = await input.evaluate((element) => {
      if (element.disabled) return -1;
      const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
      const scope = element.closest("section, fieldset, form, div") || element.parentElement;
      const text = clean(
        `${element.getAttribute("accept") || ""} ${element.getAttribute("aria-label") || ""} ${
          element.getAttribute("name") || ""
        } ${element.getAttribute("data-automation-id") || ""} ${(scope && scope.textContent) || ""}`
      );
      if (/resume|cv|curriculum/i.test(text)) return 100;
      if (/upload|attach|file/i.test(text)) return 60;
      return 10;
    }).catch(() => -1);
    if (score >= 0) {
      scoredInputs.push({ input, score });
    } else {
      await input.dispose().catch(() => {});
    }
  }
  scoredInputs.sort((a, b) => b.score - a.score);
  for (const entry of scoredInputs) {
    try {
      await entry.input.uploadFile(cvPath);
      await entry.input.dispose().catch(() => {});
      for (const rest of scoredInputs) {
        if (rest !== entry) {
          await rest.input.dispose().catch(() => {});
        }
      }
      return true;
    } catch {
      await entry.input.dispose().catch(() => {});
    }
  }
  return false;
}

async function clickWorkdayResumeSelectFile(page) {
  const handle = await page.evaluateHandle(() => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const visible = (element) => {
      if (!element) return false;
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
    };
    const candidates = Array.from(document.querySelectorAll("button, a, [role='button'], label"))
      .filter(visible)
      .filter((element) => {
        const text = clean(`${element.textContent || ""} ${element.getAttribute("aria-label") || ""}`);
        if (!/^select file$/i.test(text) && !/select file/i.test(text)) return false;
        const scope = element.closest("section, fieldset, form, div") || element.parentElement;
        const scopeText = clean(scope?.textContent || "");
        return /autofill with resume|drop file here|resume|cv/i.test(scopeText);
      })
      .sort((a, b) => {
        const ar = a.getBoundingClientRect();
        const br = b.getBoundingClientRect();
        return ar.top - br.top;
      });
    return candidates[0] || null;
  });
  const element = handle.asElement();
  if (!element) {
    await handle.dispose().catch(() => {});
    return false;
  }
  await element.evaluate((node) => node.scrollIntoView({ block: "center", inline: "center" })).catch(() => {});
  const clicked = await element.click({ delay: 25 }).then(() => true).catch(() => false);
  await element.dispose().catch(() => {});
  await handle.dispose().catch(() => {});
  return clicked;
}

async function getWorkdayResumeUploadState(page, cvPath) {
  return page.evaluate((fileName) => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const bodyText = clean(document.body?.innerText || "");
    const filename = clean(fileName || "").split(/[\\/]/).pop();
    const fileInputs = Array.from(document.querySelectorAll("input[type='file']"));
    const inputHasFile = fileInputs.some((input) => input.files && input.files.length > 0);
    const visibleAttachmentText = /uploaded|attached|successfully uploaded|file uploaded|resume uploaded|cv uploaded/i.test(bodyText);
    return {
      input_has_file: inputHasFile,
      filename_visible: Boolean(filename && bodyText.includes(filename)),
      visible_attachment_text: visibleAttachmentText,
      file_input_count: fileInputs.length,
      body_resume_sample: bodyText.match(/.{0,80}(?:resume|cv|uploaded|attached|file).{0,140}/i)?.[0] || "",
    };
  }, cvPath || "").catch(() => ({
    input_has_file: false,
    filename_visible: false,
    visible_attachment_text: false,
    file_input_count: 0,
    body_resume_sample: "",
  }));
}

async function uploadWorkdayResume(page, cvPath) {
  if (!cvPath) {
    return { attempted: false, uploaded: false, confirmed: false, clicked_upload: false, state: {} };
  }
  const existingUploadState = await getWorkdayResumeUploadState(page, cvPath);
  if (existingUploadState.input_has_file || existingUploadState.filename_visible || existingUploadState.visible_attachment_text) {
    return {
      attempted: false,
      uploaded: false,
      confirmed: true,
      clicked_upload: false,
      state: existingUploadState,
    };
  }
  let fileChooserPromise = page.waitForFileChooser({ timeout: 7000 }).catch(() => null);
  let clickedUpload = await clickWorkdayResumeSelectFile(page);
  if (!clickedUpload) {
    fileChooserPromise = page.waitForFileChooser({ timeout: 7000 }).catch(() => null);
    clickedUpload = await clickWorkdayByAutomationOrText(
      page,
      ["file-upload-input-ref", "resumeUpload", "resumeUploadButton", "attachmentUpload"],
      [/upload.*(resume|cv)|select.*file|attach.*(resume|cv)/]
    );
  }
  const fileChooser = clickedUpload ? await fileChooserPromise : null;
  let uploaded = false;
  if (fileChooser) {
    uploaded = await fileChooser.accept([cvPath]).then(() => true).catch(() => false);
  }
  if (clickedUpload && !uploaded) {
    await page.waitForSelector("input[type='file']", { timeout: 7000 }).catch(() => {});
  }
  uploaded = uploaded || await uploadWorkdayFileInput(page, cvPath);
  if (uploaded) {
    await page.waitForNetworkIdle({ idleTime: 1200, timeout: 30000 }).catch(() => {});
    await new Promise((resolve) => setTimeout(resolve, 1000));
  }
  const uploadState = await getWorkdayResumeUploadState(page, cvPath);
  return {
    attempted: true,
    uploaded: Boolean(uploaded),
    confirmed: Boolean(uploaded || uploadState.input_has_file || uploadState.filename_visible || uploadState.visible_attachment_text),
    clicked_upload: Boolean(clickedUpload),
    state: uploadState,
  };
}

function buildWorkdayApplicationQuestionRepairItems(task) {
  const answers = getApplicationAnswers(task);
  const get = (patterns) => answerByPatterns(answers, patterns);
  return [
    { kind: "textarea", label: "motivation", patterns: [/why do you want to apply/i], answer: get([/why do you want to apply/, /motivation/, /why.*blackstone/]) },
    { kind: "choice", label: "work_authorization", patterns: [/legally authorized to work/i, /employment eligibility/i], answer: get([/legally authorized/, /work authorized/, /employment authorization/]) },
    { kind: "choice", label: "sponsorship", patterns: [/require blackstone to sponsor/i, /sponsor.*employment authorization/i, /visa/i], answer: get([/require.*sponsor/, /sponsorship/, /visa/]) },
    { kind: "choice", label: "political_self_state", patterns: [/have you donated.*state or local political campaign/i], answer: get([/have you donated.*state or local political campaign/, /political.*self.*state/]) },
    { kind: "choice", label: "political_spouse_state", patterns: [/has your spouse donated.*state or local political campaign/i], answer: get([/spouse donated.*state or local political campaign/, /political.*spouse.*state/]) },
    { kind: "choice", label: "political_self_federal", patterns: [/have you donated.*candidate for any federal office/i], answer: get([/have you donated.*candidate for any federal office/, /political.*self.*federal/]) },
    { kind: "choice", label: "political_spouse_federal", patterns: [/has your spouse donated.*candidate for any federal office/i], answer: get([/spouse donated.*candidate for any federal office/, /political.*spouse.*federal/]) },
    { kind: "choice", label: "political_self_party", patterns: [/have you donated.*political party or political action committee/i], answer: get([/have you donated.*political party/, /political.*self.*party/]) },
    { kind: "choice", label: "political_spouse_party", patterns: [/has your spouse donated.*political party or political action committee/i], answer: get([/spouse donated.*political party/, /political.*spouse.*party/]) },
    { kind: "choice", label: "previous_blackstone_employment", patterns: [/ever been employed by blackstone/i], answer: get([/ever been employed by blackstone/, /previous.*blackstone/]) },
    { kind: "choice", label: "relatives_blackstone", patterns: [/relatives or members of your household employed by blackstone/i], answer: get([/relatives.*blackstone/, /household.*blackstone/]) },
    { kind: "choice", label: "relatives_deloitte", patterns: [/relatives or members of your household employed by deloitte/i], answer: get([/relatives.*deloitte/, /household.*deloitte/]) },
    { kind: "choice", label: "family_business_relationship", patterns: [/spouse, sibling, parent.*child currently/i, /material business relationship/i], answer: get([/spouse.*sibling.*parent.*child/, /family.*business.*relationship/, /material business relationship/]) },
    { kind: "choice", label: "government_official_or_affiliated", patterns: [/government official/i, /related to or affiliated with blackstone/i], answer: get([/government official/, /affiliated with blackstone/]) },
    { kind: "choice", label: "outside_business_activities", patterns: [/outside business activities/i, /board affiliations/i, /consulting engagements/i], answer: get([/outside business activities/, /board affiliations/, /consulting engagements/]) },
    { kind: "checkbox", label: "business_groups", patterns: [/opportunities are available.*business groups/i, /select those you are most interested/i], answer: get([/business groups/, /business units/, /opportunities.*interested/]) },
  ].filter((item) => answerHasValue(item.answer));
}

async function repairWorkdayApplicationQuestionsPage(page, task) {
  const repairItems = buildWorkdayApplicationQuestionRepairItems(task);
  if (!repairItems.length) {
    return { attempted: 0, filled: 0, items: [] };
  }
  let filled = 0;
  const diagnostics = [];
  for (const item of repairItems) {
    const answer = Array.isArray(item.answer) ? item.answer[0] : item.answer;
    let result = { found: false, filled: false, scope_text: "" };
    try {
      if (item.kind === "textarea") {
        result = await withTimeout(fillWorkdayTextareaByQuestionPatterns(page, item.patterns, answer), 5000, result);
      } else if (item.kind === "choice") {
        result = await withTimeout(selectWorkdayDropdownByQuestionPatterns(page, item.patterns, answer), 4500, result);
      } else if (item.kind === "checkbox") {
        result = await withTimeout(clickWorkdayCheckboxesByAnswer(page, item.answer), 5000, result);
      }
    } catch (error) {
      result = {
        found: Boolean(result.found),
        filled: false,
        error: error?.message || String(error),
        scope_text: result.scope_text || "",
      };
    }
    if (result.filled) {
      filled += 1;
    }
    diagnostics.push({
      label: item.label,
      kind: item.kind,
      answer_present: answerHasValue(item.answer),
      scope_found: Boolean(result.found),
      filled: Boolean(result.filled),
      scope_text: cleanText(result.scope_text || "").slice(0, 160),
      error: result.error || undefined,
    });
    await page.keyboard.press("Escape").catch(() => {});
    await new Promise((resolve) => setTimeout(resolve, 250));
  }
  return { attempted: repairItems.length, filled, items: diagnostics };
}

async function findWorkdayQuestionControl(page, patterns, kind) {
  const patternSources = (patterns || []).map((pattern) => pattern.source || String(pattern)).filter(Boolean);
  if (!patternSources.length) {
    return { element: null, scopeText: "" };
  }
  const handle = await page.evaluateHandle(
    ({ sources, kind: controlKind }) => {
      const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
      const visible = (element) => {
        if (!element) return false;
        const style = window.getComputedStyle(element);
        const rect = element.getBoundingClientRect();
        return (
          style.visibility !== "hidden" &&
          style.display !== "none" &&
          rect.width > 0 &&
          rect.height > 0 &&
          !element.disabled &&
          element.getAttribute("aria-disabled") !== "true"
        );
      };
      const rejectScopeText = (text) => /^errors and alerts found\b|^error[-\s]/i.test(clean(text));
      const regexes = sources.map((source) => new RegExp(source, "i"));
      const controls = Array.from(
        document.querySelectorAll("textarea, input:not([type='hidden']), button, [role='button'], [role='combobox'], [aria-haspopup='listbox']")
      ).filter(visible);
      const candidates = [];
      for (const control of controls) {
        const tag = control.tagName;
        const type = control.type || "";
        const ownText = clean(`${control.textContent || ""} ${control.value || ""} ${control.getAttribute("aria-label") || ""}`);
        if (controlKind === "textarea" && tag !== "TEXTAREA") {
          continue;
        }
        if (controlKind === "choice") {
          const choiceLike =
            control.getAttribute("role") === "combobox" ||
            control.getAttribute("aria-haspopup") === "listbox" ||
            /select|prompt|questionnaire/i.test(`${control.id || ""} ${control.name || ""} ${control.getAttribute("data-automation-id") || ""}`) ||
            /select one|choose|yes|no/i.test(ownText);
          if (!choiceLike || /^(file|radio|checkbox|submit)$/i.test(type)) {
            continue;
          }
        }
        let cursor = control;
        for (let depth = 0; cursor && depth < 10; depth += 1) {
          const text = clean(cursor.textContent || "");
          if (text && rejectScopeText(text)) {
            break;
          }
          if (text && regexes.some((regex) => regex.test(text))) {
            const hasSelect = /select one|choose|yes|no/i.test(text) || /select one|choose|yes|no/i.test(ownText);
            const hasTextarea = Boolean(cursor.querySelector("textarea")) || tag === "TEXTAREA";
            const fieldish =
              cursor.matches?.("[data-automation-id*='formField'], fieldset, [role='group']") ||
              /formField|form-field|questionnaire/i.test(`${cursor.className || ""} ${cursor.getAttribute("data-automation-id") || ""}`);
            const score =
              (fieldish ? 60 : 0) +
              (controlKind === "choice" && hasSelect ? 90 : 0) +
              (controlKind === "textarea" && hasTextarea ? 90 : 0) -
              Math.min(text.length / 50, 40) -
              depth;
            candidates.push({ control, scopeText: text, score });
            break;
          }
          cursor = cursor.parentElement;
        }
      }
      candidates.sort((a, b) => b.score - a.score);
      return candidates[0] || null;
    },
    { sources: patternSources, kind }
  );
  const propertyHandle = await handle.getProperty("control").catch(() => null);
  const element = propertyHandle?.asElement() || null;
  const scopeText = await handle.getProperty("scopeText").then((property) => property.jsonValue()).catch(() => "");
  if (propertyHandle && !element) {
    await propertyHandle.dispose().catch(() => {});
  }
  await handle.dispose().catch(() => {});
  return { element, scopeText };
}

async function fillWorkdayTextareaByQuestionPatterns(page, patterns, answer) {
  const value = cleanText(answer);
  if (!value) {
    return { found: false, filled: false, scope_text: "" };
  }
  const { element, scopeText } = await findWorkdayQuestionControl(page, patterns, "textarea");
  if (!element) {
    return { found: false, filled: false, scope_text: "" };
  }
  const filled = await fillInputHandleWithVerification(page, element, value).catch(() => false);
  await element.dispose().catch(() => {});
  return { found: true, filled: Boolean(filled), scope_text: scopeText };
}

async function selectWorkdayDropdownByQuestionPatterns(page, patterns, answer) {
  const aliases = (Array.isArray(answer) ? answer : [answer]).map(cleanText).filter(Boolean);
  const value = aliases[0] || "";
  if (!aliases.length) {
    return { found: false, filled: false, scope_text: "" };
  }
  const { element, scopeText } = await findWorkdayQuestionControl(page, patterns, "choice");
  if (!element) {
    const opened = await openWorkdayPromptByQuestionPatterns(page, patterns);
    const filledFromPrompt = opened
      ? await selectVisibleWorkdayOption(page, aliases).catch(() => false)
      : false;
    return { found: Boolean(opened), filled: Boolean(filledFromPrompt), scope_text: "" };
  }
  await element.evaluate((control) => control.scrollIntoView({ block: "center", inline: "nearest" })).catch(() => {});
  await element.click().catch(() => {});
  const isInput = await element.evaluate((control) => control.tagName === "INPUT").catch(() => false);
  if (isInput) {
    await fillInputHandleWithVerification(page, element, value).catch(() => false);
  }
  await new Promise((resolve) => setTimeout(resolve, isInput ? 700 : 350));
  const filled =
    (await selectVisibleWorkdayOption(page, aliases).catch(() => false)) ||
    (await openWorkdayDropdownNearQuestionLabel(page, patterns).then((opened) =>
      opened ? selectVisibleWorkdayOption(page, aliases) || selectVisibleWorkdayOptOutOption(page) : false
    ).catch(() => false)) ||
    (await openFocusedWorkdayDropdownAndSelect(page, aliases).catch(() => false)) ||
    (await openWorkdayPromptByQuestionPatterns(page, patterns).then((opened) =>
      opened ? selectVisibleWorkdayOption(page, aliases) || openFocusedWorkdayDropdownAndSelect(page, aliases) : false
    ).catch(() => false)) ||
    (await openWorkdayQuestionDropdownByPatterns(page, patterns).then((opened) =>
      opened ? selectVisibleWorkdayOption(page, aliases) || openFocusedWorkdayDropdownAndSelect(page, aliases) : false
    ).catch(() => false)) ||
    (await selectVisibleWorkdayOption(page, aliases.map((alias) => (alias === "Yes" ? "Yes" : alias === "No" ? "No" : alias))).catch(() => false));
  await element.dispose().catch(() => {});
  return { found: true, filled: Boolean(filled), scope_text: scopeText };
}

async function openWorkdayDropdownNearQuestionLabel(page, patterns) {
  const patternSources = (patterns || []).map((pattern) => pattern.source || String(pattern)).filter(Boolean);
  if (!patternSources.length) {
    return false;
  }
  const point = await page.evaluate((sources) => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const visible = (element) => {
      if (!element) return false;
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
    };
    const regexes = sources.map((source) => new RegExp(source, "i"));
    const labels = Array.from(document.querySelectorAll("label, span, div"))
      .filter(visible)
      .map((element) => ({ element, text: clean(element.textContent || ""), rect: element.getBoundingClientRect() }))
      .filter((entry) => {
        if (!entry.text || /^error[-\s]|errors found|errors and alerts/i.test(entry.text)) {
          return false;
        }
        if (!regexes.some((regex) => regex.test(entry.text))) {
          return false;
        }
        return entry.text.length <= 120;
      })
      .sort((a, b) => a.text.length - b.text.length);
    const controls = Array.from(
      document.querySelectorAll(
        "button, [role='button'], [role='combobox'], [aria-haspopup='listbox'], input:not([type='hidden']), [data-automation-id*='prompt']"
      )
    )
      .filter(visible)
      .map((element) => {
        const rect = element.getBoundingClientRect();
        const text = clean(`${element.textContent || ""} ${element.value || ""} ${element.getAttribute("aria-label") || ""}`);
        const idish = `${element.id || ""} ${element.name || ""} ${element.getAttribute("data-automation-id") || ""}`;
        return { element, rect, text, idish };
      })
      .filter((entry) => {
        if (/navigation|utility|logo|back|footer/i.test(entry.idish)) {
          return false;
        }
        return /select one|choose|please select/i.test(entry.text) || /prompt|select|combobox/i.test(entry.idish);
      });
    for (const label of labels) {
      const ranked = controls
        .map((control) => {
          const verticalGap = control.rect.top - label.rect.bottom;
          const horizontalOverlap = Math.min(label.rect.right + 520, control.rect.right) - Math.max(label.rect.left - 20, control.rect.left);
          const belowLabel = verticalGap >= -12 && verticalGap <= 120;
          const wideSelect = control.rect.width >= 180 && control.rect.height >= 28;
          const score =
            (belowLabel ? 120 : 0) +
            (horizontalOverlap > 0 ? 80 : 0) +
            (wideSelect ? 60 : 0) +
            (/select one|choose|please select/i.test(control.text) ? 80 : 0) +
            (/prompt|select|combobox/i.test(control.idish) ? 50 : 0) -
            Math.abs(verticalGap);
          return { ...control, score };
        })
        .filter((entry) => entry.score > 80)
        .sort((a, b) => b.score - a.score);
      const selected = ranked[0];
      if (selected) {
        return {
          x: Math.round(selected.rect.left + Math.max(12, selected.rect.width - 28)),
          y: Math.round(selected.rect.top + selected.rect.height / 2),
          text: selected.text,
        };
      }
    }
    return null;
  }, patternSources).catch(() => null);
  if (!point) {
    return false;
  }
  await page.mouse.move(point.x, point.y, { steps: 8 }).catch(() => {});
  await page.mouse.click(point.x, point.y, { delay: 100 }).catch(() => {});
  await new Promise((resolve) => setTimeout(resolve, 700));
  return true;
}

async function selectVisibleWorkdayOptOutOption(page) {
  const aliases = [
    "I do not wish to answer",
    "I don't wish to answer",
    "Prefer not to say",
    "Prefer not to answer",
    "I do not wish to disclose",
    "I don't wish to disclose",
    "I do not wish to provide this information",
    "Decline to self-identify",
    "Choose not to disclose",
    "Not disclosed",
  ];
  return selectVisibleWorkdayOption(page, aliases);
}

async function openFocusedWorkdayDropdownAndSelect(page, aliases) {
  const wanted = (aliases || []).map(cleanText).filter(Boolean);
  if (!wanted.length) {
    return false;
  }
  const openAttempts = [
    async () => page.keyboard.press("Enter"),
    async () => page.keyboard.press("Space"),
    async () => {
      await page.keyboard.down("Alt").catch(() => {});
      await page.keyboard.press("ArrowDown").catch(() => {});
      await page.keyboard.up("Alt").catch(() => {});
    },
    async () => page.keyboard.press("ArrowDown"),
  ];
  for (const open of openAttempts) {
    await open().catch(() => {});
    await new Promise((resolve) => setTimeout(resolve, 500));
    if (await selectVisibleWorkdayOption(page, wanted).catch(() => false)) {
      return true;
    }
  }
  for (const alias of wanted) {
    await page.keyboard.type(alias, { delay: 20 }).catch(() => {});
    await new Promise((resolve) => setTimeout(resolve, 700));
    if (await selectVisibleWorkdayOption(page, wanted).catch(() => false)) {
      return true;
    }
    await page.keyboard.press("Enter").catch(() => {});
    await new Promise((resolve) => setTimeout(resolve, 500));
    const activeText = await page.evaluate(() => {
      const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
      const active = document.activeElement;
      let scope = active;
      for (let depth = 0; scope && depth < 6; depth += 1) {
        const text = clean(scope.textContent || "");
        if (text && !/select one/i.test(text)) {
          return text;
        }
        scope = scope.parentElement;
      }
      return "";
    }).catch(() => "");
    if (wanted.some((aliasText) => scoreChoice(aliasText, activeText) >= 80)) {
      return true;
    }
  }
  return false;
}

async function openWorkdayPromptByQuestionPatterns(page, patterns) {
  const patternSources = (patterns || []).map((pattern) => pattern.source || String(pattern)).filter(Boolean);
  if (!patternSources.length) {
    return false;
  }
  const point = await page.evaluate((sources) => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const visible = (element) => {
      if (!element) return false;
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
    };
    const regexes = sources.map((source) => new RegExp(source, "i"));
    const rejectText = (text) => /^errors and alerts found\b|^error[-\s]/i.test(clean(text));
    const allScopes = Array.from(document.querySelectorAll("fieldset, [data-automation-id*='formField'], div, section, li"))
      .filter(visible)
      .map((scope) => ({ scope, text: clean(scope.textContent || "") }))
      .filter((entry) => entry.text && !rejectText(entry.text) && regexes.some((regex) => regex.test(entry.text)))
      .sort((a, b) => a.text.length - b.text.length);
    for (const { scope } of allScopes) {
      const controls = Array.from(
        scope.querySelectorAll(
          "[data-automation-id*='promptIcon'], [data-automation-id*='prompt'], [data-automation-id*='select'], [aria-haspopup='listbox'], [role='combobox'], button, [role='button'], input:not([type='hidden'])"
        )
      ).filter(visible);
      const ranked = controls
        .map((control) => {
          const rect = control.getBoundingClientRect();
          const text = clean(`${control.textContent || ""} ${control.value || ""} ${control.getAttribute("aria-label") || ""}`);
          const idish = `${control.id || ""} ${control.name || ""} ${control.getAttribute("data-automation-id") || ""}`;
          const score =
            (/select one|choose|please select/i.test(text) ? 90 : 0) +
            (/prompt|select|combobox/i.test(idish) ? 70 : 0) +
            (rect.width >= 220 && rect.height >= 30 ? 65 : 0) +
            (control.getAttribute("aria-haspopup") === "listbox" || control.getAttribute("role") === "combobox" ? 80 : 0) +
            Math.min(rect.right / 200, 20);
          return { control, rect, score };
        })
        .filter((entry) => entry.score > 0)
        .sort((a, b) => b.score - a.score);
      const selected = ranked[0];
      if (!selected) {
        const rect = scope.getBoundingClientRect();
        if (rect.width > 240 && rect.height > 30) {
          return {
            x: Math.round(rect.left + Math.min(rect.width - 24, Math.max(220, rect.width * 0.92))),
            y: Math.round(rect.top + Math.min(rect.height - 12, Math.max(34, rect.height * 0.65))),
          };
        }
        continue;
      }
      return {
        x: Math.round(selected.rect.left + Math.max(12, selected.rect.width - 24)),
        y: Math.round(selected.rect.top + selected.rect.height / 2),
      };
    }
    return null;
  }, patternSources).catch(() => null);
  if (!point) {
    return false;
  }
  await page.mouse.move(point.x, point.y, { steps: 8 }).catch(() => {});
  await page.mouse.click(point.x, point.y, { delay: 80 }).catch(() => {});
  await new Promise((resolve) => setTimeout(resolve, 650));
  return true;
}

async function openWorkdayQuestionDropdownByPatterns(page, patterns) {
  const patternSources = (patterns || []).map((pattern) => pattern.source || String(pattern)).filter(Boolean);
  if (!patternSources.length) {
    return false;
  }
  const point = await page.evaluate((sources) => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const visible = (element) => {
      if (!element) return false;
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
    };
    const regexes = sources.map((source) => new RegExp(source, "i"));
    const controls = Array.from(
      document.querySelectorAll("button, [role='button'], [role='combobox'], [aria-haspopup='listbox'], input:not([type='hidden'])")
    ).filter(visible);
    const candidates = [];
    for (const control of controls) {
      let scope = control;
      for (let depth = 0; scope && depth < 10; depth += 1) {
        const text = clean(scope.textContent || "");
        if (/^errors and alerts found\b|^error[-\s]/i.test(text)) {
          break;
        }
        if (text && regexes.some((regex) => regex.test(text))) {
          const selectish = /select one|choose|please select/i.test(text);
          const rectTarget = visible(control) ? control : scope;
          const rect = rectTarget.getBoundingClientRect();
          candidates.push({
            x: Math.round(rect.left + Math.max(12, rect.width - 24)),
            y: Math.round(rect.top + rect.height / 2),
            score: (selectish ? 80 : 0) - depth - Math.min(text.length / 80, 25),
          });
          break;
        }
        scope = scope.parentElement;
      }
    }
    candidates.sort((a, b) => b.score - a.score);
    return candidates[0] || null;
  }, patternSources).catch(() => null);
  if (!point) {
    return false;
  }
  await page.mouse.move(point.x, point.y, { steps: 6 }).catch(() => {});
  await page.mouse.click(point.x, point.y, { delay: 80 }).catch(() => {});
  await new Promise((resolve) => setTimeout(resolve, 500));
  return true;
}

async function clickWorkdayCheckboxesByAnswer(page, answer) {
  const values = Array.isArray(answer) ? answer : cleanText(answer).split(/\s*,\s*|\s*;\s*/).filter(Boolean);
  const wanted = values.map(cleanText).filter(Boolean);
  if (!wanted.length) {
    return { found: false, filled: false, scope_text: "" };
  }
  const result = await page.evaluate((targets) => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const compact = (value) => clean(value).toLowerCase().replace(/[^a-z0-9]+/g, "");
    const visible = (element) => {
      if (!element) return false;
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
    };
    let found = 0;
    let filled = 0;
    for (const target of targets) {
      const targetCompact = compact(target);
      const label = Array.from(document.querySelectorAll("label, span, div"))
        .filter(visible)
        .filter((element) => compact(element.textContent || "") === targetCompact)
        .sort((a, b) => clean(a.textContent || "").length - clean(b.textContent || "").length)[0] || null;
      let scope = label;
      for (let depth = 0; scope && depth < 6; depth += 1) {
        const scopeText = clean(scope.textContent || "");
        if (scopeText && compact(scopeText).includes(targetCompact)) {
          break;
        }
        scope = scope.parentElement;
      }
      const input = label?.getAttribute?.("for")
        ? document.getElementById(label.getAttribute("for"))
        : label?.closest("label")?.querySelector("input[type='checkbox']") ||
          scope?.querySelector("input[type='checkbox']") ||
          Array.from(document.querySelectorAll("input[type='checkbox']")).find((checkbox) => {
            const scopeText = clean(checkbox.closest("label, div, li, fieldset")?.textContent || "");
            return compact(scopeText) === targetCompact || compact(scopeText).includes(targetCompact);
          });
      const customCheckbox =
        label?.closest("[role='checkbox']") ||
        scope?.querySelector("[role='checkbox']") ||
        Array.from(document.querySelectorAll("[role='checkbox']")).find((checkbox) => {
          const scopeText = clean(checkbox.closest("label, div, li, fieldset")?.textContent || "");
          return compact(scopeText) === targetCompact || compact(scopeText).includes(targetCompact);
        });
      if (!input && !customCheckbox && !label) {
        continue;
      }
      found += 1;
      if (input) {
        if (!input.checked) {
          (label || input).click();
        }
        if (input.checked) {
          filled += 1;
        }
        continue;
      }
      const checked = customCheckbox?.getAttribute("aria-checked") === "true";
      if (!checked) {
        (customCheckbox || label).click();
      }
      const checkedAfter = customCheckbox?.getAttribute("aria-checked") === "true";
      if (checked || checkedAfter || !customCheckbox) {
        filled += 1;
      }
    }
    return { found: found > 0, filled: filled > 0, scope_text: targets.join(", ") };
  }, wanted).catch((error) => ({ found: false, filled: false, error: error?.message || String(error), scope_text: "" }));
  return result;
}

async function clickWorkdayConsentTermsCheckboxes(page) {
  return page.evaluate(() => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const visible = (element) => {
      if (!element) return false;
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
    };
    const cssEscape = (value) =>
      window.CSS && CSS.escape ? CSS.escape(value) : String(value || "").replace(/"/g, '\\"');
    const consentPattern = /\b(read|agree|accept|acknowledge|consent|terms|conditions|privacy|notice)\b/i;
    const blockedPattern = /\b(gender|ethnicity|race|veteran|disability|sexual orientation|pronouns|date of birth)\b/i;
    const getLabelText = (control) => {
      const id = control.getAttribute("id") || "";
      const explicit = id ? document.querySelector(`label[for="${cssEscape(id)}"]`) : null;
      const labelledBy = (control.getAttribute("aria-labelledby") || "")
        .split(/\s+/)
        .map((part) => document.getElementById(part)?.textContent || "")
        .join(" ");
      const scope =
        explicit ||
        control.closest("label") ||
        control.closest("[role='checkbox']") ||
        control.closest("fieldset, div, li, section") ||
        control.parentElement;
      return clean(`${explicit?.textContent || ""} ${labelledBy} ${control.getAttribute("aria-label") || ""} ${scope?.textContent || ""}`);
    };
    const isChecked = (control) => {
      if (control.matches("input[type='checkbox']")) {
        return Boolean(control.checked);
      }
      return control.getAttribute("aria-checked") === "true";
    };
    const clickTargetFor = (control) => {
      const id = control.getAttribute("id") || "";
      const explicit = id ? document.querySelector(`label[for="${cssEscape(id)}"]`) : null;
      return explicit || control.closest("label") || control.closest("[role='checkbox']") || control;
    };
    const controls = Array.from(document.querySelectorAll("input[type='checkbox'], [role='checkbox']"))
      .filter(visible)
      .map((control) => ({ control, label: getLabelText(control) }))
      .filter((entry) => consentPattern.test(entry.label) && !blockedPattern.test(entry.label));
    const diagnostics = [];
    let filled = 0;
    for (const entry of controls) {
      const before = isChecked(entry.control);
      if (!before) {
        const target = clickTargetFor(entry.control);
        target.scrollIntoView({ block: "center", inline: "nearest" });
        target.click();
        if (entry.control.matches("input[type='checkbox']") && !entry.control.checked) {
          const descriptor = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, "checked");
          if (descriptor && descriptor.set) {
            descriptor.set.call(entry.control, true);
          } else {
            entry.control.checked = true;
          }
          entry.control.dispatchEvent(new Event("input", { bubbles: true }));
          entry.control.dispatchEvent(new Event("change", { bubbles: true }));
        }
      }
      const after = isChecked(entry.control);
      if (after) {
        filled += 1;
      }
      diagnostics.push({
        label: clean(entry.label).slice(0, 180),
        checked_before: before,
        checked_after: after,
      });
    }
    return {
      attempted: controls.length,
      filled,
      items: diagnostics,
    };
  }).catch((error) => ({ attempted: 0, filled: 0, error: error?.message || String(error), items: [] }));
}

async function repairWorkdayVoluntaryDisclosuresPage(page, task) {
  const answers = getApplicationAnswers(task);
  const consentRepair = await clickWorkdayConsentTermsCheckboxes(page);
  const disclosureItems = [
    {
      label: "gender",
      patterns: [/please select your gender/i, /^gender\b/i, /\bgender\*/i],
      answer:
        answerByPatterns(answers, [/^gender$/i, /please select your gender/i]) ||
        [
          "I do not wish to answer",
          "I don't wish to answer",
          "Prefer not to say",
          "Prefer not to answer",
          "I do not wish to disclose",
          "I don't wish to disclose",
          "I do not wish to provide this information",
          "Decline to self-identify",
          "Choose not to disclose",
          "Not disclosed",
        ],
    },
  ];
  const choiceDiagnostics = [];
  let choiceFilled = 0;
  for (const item of disclosureItems) {
    const repairPromise =
      item.label === "gender"
        ? selectWorkdayGenderDisclosure(page, item.answer)
        : selectWorkdayDropdownByQuestionPatterns(page, item.patterns, item.answer);
    const result = await withTimeout(
      repairPromise,
      7000,
      { found: false, filled: false, timeout: true, scope_text: "" }
    ).catch((error) => ({ found: false, filled: false, error: error?.message || String(error), scope_text: "" }));
    if (result.filled) {
      choiceFilled += 1;
    }
    choiceDiagnostics.push({
      label: item.label,
      kind: "choice",
      scope_found: Boolean(result.found),
      filled: Boolean(result.filled),
      scope_text: cleanText(result.scope_text || "").slice(0, 160),
      option_texts: result.option_texts || undefined,
      error: result.error || undefined,
      timeout: Boolean(result.timeout),
    });
  }
  return {
    attempted: (consentRepair.attempted || 0) + disclosureItems.length,
    filled: (consentRepair.filled || 0) + choiceFilled,
    items: [
      ...(consentRepair.items || []).map((item) => ({ ...item, kind: "checkbox" })),
      ...choiceDiagnostics,
    ],
    consent: consentRepair,
  };
}

async function selectWorkdayGenderDisclosure(page, answer) {
  const aliases = (Array.isArray(answer) ? answer : [answer]).map(cleanText).filter(Boolean);
  if (!aliases.length) {
    return { found: false, filled: false, scope_text: "" };
  }
  const directOpened = await openWorkdayButtonByText(page, [/gender/i, /select one|choose/i]).catch(() => false);
  if (directOpened) {
    const directFilled = await selectVisibleWorkdayOption(page, aliases).catch(() => false);
    if (directFilled) {
      return { found: true, filled: true, scope_text: "Gender prompt button" };
    }
    const optionTexts = await getVisibleWorkdayOptionTexts(page).catch(() => []);
    if (optionTexts.length) {
      return { found: true, filled: false, scope_text: "Gender prompt button", option_texts: optionTexts.slice(0, 12) };
    }
  }
  const pointResult = await page.evaluate(() => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const visible = (element) => {
      if (!element) return false;
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
    };
    const rectData = (rect) => ({
      x: Math.round(rect.left + Math.max(12, rect.width - 28)),
      y: Math.round(rect.top + rect.height / 2),
    });
    const labelCandidates = Array.from(document.querySelectorAll("label, [id*='label' i], [data-automation-id*='label' i], span"))
      .filter(visible)
      .map((element) => ({ element, text: clean(element.textContent || ""), rect: element.getBoundingClientRect() }))
      .filter((entry) => /^gender\*?$/i.test(entry.text) || /^gender\*?\s*select one/i.test(entry.text))
      .sort((a, b) => a.text.length - b.text.length);
    const controlCandidates = Array.from(
      document.querySelectorAll(
        "button, [role='button'], [role='combobox'], [aria-haspopup='listbox'], input:not([type='hidden']), [data-automation-id*='prompt'], [data-automation-id*='select']"
      )
    )
      .filter(visible)
      .map((element) => {
        const rect = element.getBoundingClientRect();
        const text = clean(`${element.textContent || ""} ${element.value || ""} ${element.getAttribute("aria-label") || ""}`);
        const idish = `${element.id || ""} ${element.name || ""} ${element.getAttribute("data-automation-id") || ""} ${element.getAttribute("aria-labelledby") || ""}`;
        return { element, rect, text, idish };
      })
      .filter((entry) => {
        if (/navigation|utility|logo|back|footer/i.test(entry.idish)) {
          return false;
        }
        return /gender|select one|choose|prompt|combobox|select/i.test(`${entry.text} ${entry.idish}`);
      });
    for (const label of labelCandidates) {
      const inferredSelectPoint = {
        x: Math.round(label.rect.left + 460),
        y: Math.round(label.rect.bottom + 34),
      };
      const inferredElement = document.elementFromPoint(inferredSelectPoint.x, inferredSelectPoint.y);
      if (inferredElement && visible(inferredElement)) {
        return {
          found: true,
          scope_text: label.text,
          x: inferredSelectPoint.x,
          y: inferredSelectPoint.y,
        };
      }
      const explicitFor = label.element.getAttribute("for") || "";
      const explicitControl = explicitFor ? document.getElementById(explicitFor) : null;
      if (visible(explicitControl)) {
        return { found: true, scope_text: label.text, ...rectData(explicitControl.getBoundingClientRect()) };
      }
      let scope = label.element;
      for (let depth = 0; scope && depth < 6; depth += 1) {
        const inScope = controlCandidates
          .filter((entry) => scope.contains(entry.element) && entry.element !== label.element)
          .map((entry) => ({
            ...entry,
            score:
              (/select one|choose/i.test(entry.text) ? 120 : 0) +
              (/gender|prompt|select|combobox/i.test(entry.idish) ? 90 : 0) +
              (entry.rect.width >= 180 ? 70 : 0) +
              (entry.rect.top >= label.rect.top - 12 ? 40 : 0) -
              Math.abs(entry.rect.top - label.rect.bottom),
          }))
          .filter((entry) => entry.score > 50)
          .sort((a, b) => b.score - a.score);
        if (inScope[0]) {
          return { found: true, scope_text: clean(scope.textContent || "").slice(0, 180), ...rectData(inScope[0].rect) };
        }
        scope = scope.parentElement;
      }
      const nearBelow = controlCandidates
        .map((entry) => {
          const verticalGap = entry.rect.top - label.rect.bottom;
          const horizontalDistance = Math.abs(entry.rect.left - label.rect.left);
          return {
            ...entry,
            score:
              (verticalGap >= -12 && verticalGap <= 140 ? 140 : 0) +
              (horizontalDistance <= 80 ? 80 : 0) +
              (/select one|choose/i.test(entry.text) ? 110 : 0) +
              (entry.rect.width >= 180 ? 50 : 0) -
              Math.abs(verticalGap) -
              horizontalDistance / 8,
          };
        })
        .filter((entry) => entry.score > 80)
        .sort((a, b) => b.score - a.score);
      if (nearBelow[0]) {
        return { found: true, scope_text: label.text, ...rectData(nearBelow[0].rect) };
      }
    }
    const directGenderControl = controlCandidates
      .filter((entry) => /gender/i.test(entry.idish))
      .sort((a, b) => b.rect.width - a.rect.width)[0];
    if (directGenderControl) {
      return { found: true, scope_text: clean(`${directGenderControl.text} ${directGenderControl.idish}`).slice(0, 180), ...rectData(directGenderControl.rect) };
    }
    return { found: false, scope_text: "" };
  }).catch((error) => ({ found: false, error: error?.message || String(error), scope_text: "" }));
  if (!pointResult?.found) {
    return { found: false, filled: false, scope_text: pointResult?.scope_text || "", error: pointResult?.error };
  }
  await page.mouse.move(pointResult.x, pointResult.y, { steps: 8 }).catch(() => {});
  await page.mouse.click(pointResult.x, pointResult.y, { delay: 90 }).catch(() => {});
  await new Promise((resolve) => setTimeout(resolve, 700));
  let filled = await selectVisibleWorkdayOption(page, aliases).catch(() => false);
  if (!filled) {
    await page.keyboard.press("Enter").catch(() => {});
    await new Promise((resolve) => setTimeout(resolve, 450));
    filled = await selectVisibleWorkdayOption(page, aliases).catch(() => false);
  }
  if (!filled) {
    await page.keyboard.press("Space").catch(() => {});
    await new Promise((resolve) => setTimeout(resolve, 450));
    filled = await selectVisibleWorkdayOption(page, aliases).catch(() => false);
  }
  if (!filled) {
    await page.keyboard.down("Alt").catch(() => {});
    await page.keyboard.press("ArrowDown").catch(() => {});
    await page.keyboard.up("Alt").catch(() => {});
    await new Promise((resolve) => setTimeout(resolve, 600));
    filled = await selectVisibleWorkdayOption(page, aliases).catch(() => false);
  }
  const optionTexts = filled ? [] : await getVisibleWorkdayOptionTexts(page).catch(() => []);
  return {
    found: true,
    filled: Boolean(filled),
    scope_text: pointResult.scope_text || "Gender",
    option_texts: optionTexts.slice(0, 12),
  };
}

async function getVisibleWorkdayOptionTexts(page) {
  return page.evaluate(() => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const visible = (element) => {
      if (!element) return false;
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
    };
    return Array.from(
      document.querySelectorAll("[role='option'], [data-automation-id*='promptOption'], [id*='promptOption'], [role='listbox'] *, li")
    )
      .filter(visible)
      .map((element) => clean(`${element.textContent || ""} ${element.getAttribute("aria-label") || ""}`))
      .filter((text) => !/my information|my experience|application questions|voluntary disclosures|review|completed step|current step/i.test(text))
      .filter((text, index, list) => text && list.indexOf(text) === index)
      .slice(0, 40);
  });
}

async function openWorkdayButtonByText(page, patterns) {
  const patternSources = (patterns || []).map((pattern) => pattern.source || String(pattern)).filter(Boolean);
  if (!patternSources.length) {
    return false;
  }
  const handle = await page.evaluateHandle((sources) => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const visible = (element) => {
      if (!element) return false;
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
    };
    const regexes = sources.map((source) => new RegExp(source, "i"));
    const controls = Array.from(document.querySelectorAll("button, [role='button'], [role='combobox'], [aria-haspopup='listbox']"))
      .filter(visible)
      .map((element) => ({
        element,
        text: clean(`${element.textContent || ""} ${element.getAttribute("aria-label") || ""}`),
        auto: element.getAttribute("data-automation-id") || "",
      }))
      .filter((entry) => {
        if (/utility|navigation|logo|backToJobPosting|pageFooter/i.test(entry.auto)) {
          return false;
        }
        if (entry.text.length > 220 || /my information|my experience|application questions|voluntary disclosures|review/i.test(entry.text)) {
          return false;
        }
        return regexes.every((regex) => regex.test(entry.text));
      })
      .sort((a, b) => a.text.length - b.text.length);
    return controls[0]?.element || null;
  }, patternSources);
  const element = handle.asElement();
  if (!element) {
    await handle.dispose().catch(() => {});
    return false;
  }
  await element.evaluate((control) => control.scrollIntoView({ block: "center", inline: "nearest" })).catch(() => {});
  await element.click().catch(async () => {
    const rect = await element.evaluate((control) => {
      const box = control.getBoundingClientRect();
      return { x: box.left + Math.max(12, box.width - 28), y: box.top + box.height / 2 };
    });
    await page.mouse.click(rect.x, rect.y, { delay: 80 }).catch(() => {});
  });
  await handle.dispose().catch(() => {});
  await new Promise((resolve) => setTimeout(resolve, 700));
  return true;
}

async function fillWorkdayCoreCandidateFields(page, candidate) {
  const firstName = normalizePersonNameCase(candidate.firstName);
  const lastName = normalizePersonNameCase(candidate.lastName);
  const direct = {
    first_name: await fillWorkdayInput(page, "legalNameSection_firstName", firstName),
    last_name: await fillWorkdayInput(page, "legalNameSection_lastName", lastName),
    email: await fillWorkdayInput(page, "email", candidate.email),
    address: await fillWorkdayInput(page, "addressLine1", candidate.address),
  };
  return {
    first_name: Boolean(direct.first_name),
    last_name: Boolean(direct.last_name),
    email: Boolean(direct.email),
    phone: false,
    address: Boolean(direct.address),
  };
}

async function repairWorkdayKnownFields(page, task, candidate) {
  const answers = getApplicationAnswers(task);
  const payload = getTaskPayload(task);
  const profileAnswers = getCandidateProfileAnswers(task);
  const fullName = cleanText(
    task.candidate_name ||
      payload.candidate_name ||
      profileAnswers.name ||
      profileAnswers.full_name ||
      candidate.name ||
      ""
  );
  const nameParts = fullName.split(/\s+/).filter(Boolean);
  const firstName = normalizePersonNameCase(candidate.firstName || answers["Given Name"] || answers["Given Name(s)"] || nameParts[0] || "");
  const lastName =
    normalizeWorkdayAnswerForLabel(
      "Family Name",
      answers["Family Name"] || answers["family name"] || candidate.lastName || nameParts.slice(1).join(" ")
    );
  const addressLine1 = cleanText(answers["Address Line 1"] || answers.address || "");
  const city = cleanText(answers["City or Town"] || answers.city || "");
  const postalCode = cleanText(answers["Postal Code"] || answers.postal_code || "");
  const phone = normalizePhoneForDialCode(answers["Phone Number"] || candidate.phone || "", answers["Country Phone Code"] || "");
  const repairs = [
    { label: "Given Name", value: firstName, selectors: ['input[name="legalName--firstName"]', 'input[id="name--legalName--firstName"]'] },
    { label: "Family Name", value: lastName, selectors: ['input[name="legalName--lastName"]', 'input[id="name--legalName--lastName"]'] },
    { label: "Address Line 1", value: addressLine1, selectors: ['input[name="addressLine1"]', 'input[id="address--addressLine1"]'] },
    { label: "City or Town", value: city, selectors: ['input[name="city"]', 'input[id="address--city"]'] },
    { label: "Postal Code", value: postalCode, selectors: ['input[name="postalCode"]', 'input[id="address--postalCode"]'] },
    { label: "Phone Number", value: phone, selectors: ['input[name="phoneNumber"]', 'input[id="phoneNumber--phoneNumber"]'] },
  ].filter((item) => item.value);
  let repaired = 0;
  for (const item of repairs) {
    let field = null;
    for (const selector of item.selectors || []) {
      const candidateField = await page.$(selector).catch(() => null);
      if (!candidateField) {
        continue;
      }
      const visible = await candidateField
        .evaluate((element) => {
          const style = window.getComputedStyle(element);
          const rect = element.getBoundingClientRect();
          return !element.disabled && style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
        })
        .catch(() => false);
      if (visible) {
        field = candidateField;
        break;
      }
      await candidateField.dispose().catch(() => {});
    }
    field = field || await findVisibleControlForItem(page, {
      label: item.label,
      fieldNames: [],
      fieldTypes: ["text"],
      choices: [],
      choiceLike: false,
      answer: item.value,
    }).catch(() => null);
    if (!field) {
      continue;
    }
    const ok =
      await withTimeout(fillWorkdayExactTextField(page, field, item.value), 6000, false).catch(() => false) ||
      await withTimeout(typeTextControl(page, field, item), 6000, false).catch(() => false);
    await field.dispose().catch(() => {});
    if (ok) {
      repaired += 1;
    }
  }
  return repaired;
}

async function fillWorkdayExactTextField(page, field, value) {
  const answer = cleanText(value);
  if (!field || !answer) {
    return false;
  }
  const meta = await field
    .evaluate((element) => ({
      tagName: element.tagName,
      type: element.type || "",
      disabled: Boolean(element.disabled),
    }))
    .catch(() => null);
  if (!meta || meta.disabled || meta.tagName !== "INPUT" || /^(hidden|file|radio|checkbox|submit|button)$/i.test(meta.type)) {
    return false;
  }
  await field.evaluate((element) => element.scrollIntoView({ block: "center", inline: "nearest" })).catch(() => {});
  await field.click({ clickCount: 3 }).catch(() => {});
  await field
    .evaluate((element, nextValue) => {
      const descriptor = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, "value");
      element.focus();
      if (descriptor && descriptor.set) {
        descriptor.set.call(element, "");
      } else {
        element.value = "";
      }
      element.dispatchEvent(new InputEvent("input", { bubbles: true, inputType: "deleteContentBackward", data: null }));
      element.dispatchEvent(new Event("change", { bubbles: true }));
      if (typeof element.select === "function") {
        element.select();
      }
      if (descriptor && descriptor.set) {
        descriptor.set.call(element, nextValue);
      } else {
        element.value = nextValue;
      }
      element.dispatchEvent(new InputEvent("input", { bubbles: true, inputType: "insertText", data: nextValue }));
      element.dispatchEvent(new Event("change", { bubbles: true }));
      element.dispatchEvent(new Event("blur", { bubbles: true }));
    }, answer)
    .catch(() => {});
  await new Promise((resolve) => setTimeout(resolve, 300));
  let valueAfterSet = await field.evaluate((element) => String(element.value || "").trim()).catch(() => "");
  if (valueAfterSet === answer) {
    return true;
  }
  await field.click({ clickCount: 3 }).catch(() => {});
  await field
    .evaluate((element) => {
      element.focus();
      if (typeof element.select === "function") {
        element.select();
      }
    })
    .catch(() => {});
  const modifier = process.platform === "darwin" ? "Meta" : "Control";
  await page.keyboard.down(modifier).catch(() => {});
  await page.keyboard.press("KeyA").catch(() => {});
  await page.keyboard.up(modifier).catch(() => {});
  await page.keyboard.press("Backspace").catch(() => {});
  await page.keyboard.type(answer, { delay: 12 }).catch(() => {});
  await page.keyboard.press("Tab").catch(() => {});
  await new Promise((resolve) => setTimeout(resolve, 300));
  valueAfterSet = await field.evaluate((element) => String(element.value || "").trim()).catch(() => "");
  if (valueAfterSet === answer) {
    return true;
  }
  if (valueAfterSet.toLowerCase() === `${answer}${answer}`.toLowerCase()) {
    return fillInputHandleWithVerification(page, field, answer);
  }
  return false;
}

async function repairWorkdayKnownChoices(page, task) {
  const answers = getApplicationAnswers(task);
  const items = [
    { label: "How Did You Hear About Us?", value: cleanText(answers["How Did You Hear About Us?"] || "Company Website") },
    { label: "Country Phone Code", value: cleanText(answers["Country Phone Code"] || "United Kingdom (+44)") },
  ].filter((item) => item.value);
  let repaired = 0;
  for (const item of items) {
    const directOk = await selectWorkdayChoiceByLabel(page, item.label, item.value).catch(() => false);
    const isPhoneCode = /country phone code/i.test(item.label);
    const ok =
      directOk ||
      (!isPhoneCode &&
        (await fillChoiceByVisibleQuestion(page, {
          label: item.label,
          answer: item.value,
          choices: [],
          choiceLike: true,
          fieldNames: [],
          fieldTypes: [],
        }).catch(() => false)));
    if (ok) {
      repaired += 1;
    }
    await page.keyboard.press("Escape").catch(() => {});
    await new Promise((resolve) => setTimeout(resolve, 250));
  }
  return repaired;
}

function cssEscapeIdentifier(value) {
  return String(value || "").replace(/([!"#$%&'()*+,./:;<=>?@[\\\]^`{|}~])/g, "\\$1");
}

async function selectVisibleWorkdayOption(page, aliases) {
  const wanted = (aliases || []).map(cleanText).filter(Boolean);
  if (!wanted.length) {
    return false;
  }
  const optionHandle = await page.evaluateHandle((targets) => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const compact = (value) => clean(value).toLowerCase().replace(/[^a-z0-9]+/g, "");
    const visible = (element) => {
      if (!element) return false;
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
    };
    const targetCompacts = targets.map(compact).filter(Boolean);
    const score = (text) => {
      const normalized = compact(text);
      if (!normalized) return 0;
      if (targetCompacts.some((target) => normalized === target)) return 100;
      if (targetCompacts.some((target) => normalized.includes(target))) return 90;
      if (targetCompacts.some((target) => target.includes(normalized))) return 80;
      return 0;
    };
    const options = Array.from(
      document.querySelectorAll("[role='option'], [data-automation-id*='promptOption'], [id*='promptOption'], li, [role='listbox'] *")
    )
      .filter(visible)
      .map((node) => ({ node, text: clean(`${node.textContent || ""} ${node.getAttribute("aria-label") || ""}`) }))
      .filter((entry) => !/my information|my experience|application questions|voluntary disclosures|review|completed step|current step/i.test(entry.text))
      .map((entry) => ({ ...entry, score: score(entry.text) }))
      .filter((entry) => entry.score > 0)
      .sort((a, b) => b.score - a.score);
    return options[0]?.node || null;
  }, wanted);
  const option = optionHandle.asElement();
  if (!option) {
    await optionHandle.dispose().catch(() => {});
    return false;
  }
  await option.click().catch(() => {});
  await optionHandle.dispose().catch(() => {});
  await new Promise((resolve) => setTimeout(resolve, 450));
  return true;
}

async function fillWorkdayPromptInputBySelector(page, selector, value) {
  const answer = cleanText(value);
  if (!answer) {
    return false;
  }
  const field = await page.$(selector).catch(() => null);
  if (!field) {
    return false;
  }
  await field.evaluate((element) => element.scrollIntoView({ block: "center", inline: "nearest" })).catch(() => {});
  await field.click({ clickCount: 3 }).catch(() => {});
  await field
    .evaluate((element) => {
      element.focus();
      const descriptor = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, "value");
      if (descriptor && descriptor.set) {
        descriptor.set.call(element, "");
      } else {
        element.value = "";
      }
      element.dispatchEvent(new InputEvent("input", { bubbles: true, inputType: "deleteContentBackward", data: null }));
      element.dispatchEvent(new Event("change", { bubbles: true }));
    })
    .catch(() => {});
  await page.keyboard.type(answer, { delay: 20 }).catch(() => {});
  await new Promise((resolve) => setTimeout(resolve, 900));
  const selected = await selectVisibleWorkdayOption(page, [answer]);
  if (!selected) {
    await page.keyboard.press("Enter").catch(() => {});
    await new Promise((resolve) => setTimeout(resolve, 400));
  }
  const ok = await field
    .evaluate((element, expected) => {
      const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
      return clean(element.value).toLowerCase().includes(clean(expected).toLowerCase().slice(0, 12));
    }, answer)
    .catch(() => false);
  await field.dispose().catch(() => {});
  await page.keyboard.press("Escape").catch(() => {});
  return Boolean(ok || selected);
}

async function fillWorkdayDateInputBySelector(page, selector, value) {
  const answer = cleanText(value);
  if (!answer) {
    return false;
  }
  const field = await page.$(selector).catch(() => null);
  if (!field) {
    return false;
  }
  await field.evaluate((element) => element.scrollIntoView({ block: "center", inline: "nearest" })).catch(() => {});
  await field
    .evaluate((element) => {
      const descriptor = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, "value");
      element.focus();
      if (typeof element.select === "function") {
        element.select();
      }
      if (descriptor && descriptor.set) {
        descriptor.set.call(element, "");
      } else {
        element.value = "";
      }
      element.dispatchEvent(new InputEvent("input", { bubbles: true, inputType: "deleteContentBackward", data: null }));
      element.dispatchEvent(new Event("change", { bubbles: true }));
    })
    .catch(() => {});
  await field.click({ clickCount: 3 }).catch(() => {});
  await field
    .evaluate((element) => {
      element.focus();
      if (typeof element.select === "function") {
        element.select();
      }
    })
    .catch(() => {});
  const modifier = process.platform === "darwin" ? "Meta" : "Control";
  await page.keyboard.down(modifier).catch(() => {});
  await page.keyboard.press("KeyA").catch(() => {});
  await page.keyboard.up(modifier).catch(() => {});
  await page.keyboard.press("Backspace").catch(() => {});
  await page.keyboard.type(answer, { delay: 35 }).catch(() => {});
  await page.keyboard.press("Tab").catch(() => {});
  await new Promise((resolve) => setTimeout(resolve, 350));
  const ok = await field
    .evaluate((element, expected) => {
      const normalize = (value) => String(value || "").replace(/\s+/g, "").trim();
      const actual = normalize(element.value);
      const wanted = normalize(expected);
      if (!actual || !wanted) {
        return false;
      }
      return actual === wanted || actual === String(Number(wanted)) || actual.endsWith(`/${wanted}`);
    }, answer)
    .catch(() => false);
  await field.dispose().catch(() => {});
  return Boolean(ok);
}

async function selectWorkdayEducationDegree(page, educationPrefix, degree) {
  const answer = cleanText(degree);
  if (!answer) {
    return false;
  }
  const aliases = [answer];
  if (/master|msc/i.test(answer)) aliases.push("Master", "Masters", "Master's Degree", "Master Degree");
  if (/bachelor|bsc|ba|bba/i.test(answer)) aliases.push("Bachelor", "Bachelor's Degree", "Bachelor Degree");
  if (/high school|baccalaureate|preparatory/i.test(answer)) aliases.push("High School", "High School Diploma", "Secondary Education", "Diploma");
  const buttonHandle = await page.evaluateHandle((prefix) => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const visible = (element) => {
      if (!element) return false;
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
    };
    const school = document.getElementById(`${prefix}--school`);
    if (!school) return null;
    let scope = school;
    for (let depth = 0; depth < 12 && scope; depth += 1) {
      const text = clean(scope.textContent || "");
      if (/school or university/i.test(text) && /degree/i.test(text) && /overall result|field of study/i.test(text)) {
        break;
      }
      scope = scope.parentElement;
    }
    scope = scope || school.closest("section, fieldset, div") || document.body;
    const schoolRect = school.getBoundingClientRect();
    const buttons = Array.from(scope.querySelectorAll("button, [role='button'], [aria-haspopup='listbox']"))
      .filter(visible)
      .map((button) => ({
        button,
        text: clean(`${button.textContent || ""} ${button.getAttribute("aria-label") || ""} ${button.getAttribute("data-automation-id") || ""}`),
        rect: button.getBoundingClientRect(),
      }))
      .filter((entry) => /degree|select one|required/i.test(entry.text) && entry.rect.top >= schoolRect.top)
      .sort((a, b) => a.rect.top - b.rect.top);
    return buttons[0]?.button || null;
  }, educationPrefix);
  const button = buttonHandle.asElement();
  if (!button) {
    await buttonHandle.dispose().catch(() => {});
    return false;
  }
  await button.click().catch(() => {});
  await buttonHandle.dispose().catch(() => {});
  await new Promise((resolve) => setTimeout(resolve, 700));
  const selected = await selectVisibleWorkdayOption(page, aliases);
  await page.keyboard.press("Escape").catch(() => {});
  return Boolean(selected);
}

async function repairWorkdayExperiencePage(page, task) {
  const cvText = getCvText(task);
  const educationEntries = extractWorkdayEducationEntriesFromCv(cvText);
  const experienceEntries = extractWorkdayExperienceEntriesFromCv(cvText);
  const cvDateRanges = extractCvDateRanges(cvText);
  const currentExperience = experienceEntries.find((entry) => entry.current) || experienceEntries[0] || {};
  const state = await getWorkdayStepState(page);
  if (state.active_step !== "my_experience" && !/myexperience/i.test(state.body_compact_sample || "")) {
    return { current_experience_fixed: 0, education_rows_fixed: 0, education_entries: educationEntries.length, experience_entries: experienceEntries.length };
  }
  let currentExperienceFixed = await page.evaluate(({ current, dateRanges }) => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const setInputValue = (field, value) => {
      if (!field) return;
      const descriptor = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, "value");
      field.focus();
      if (descriptor && descriptor.set) {
        descriptor.set.call(field, value);
      } else {
        field.value = value;
      }
      field.dispatchEvent(new InputEvent("input", { bubbles: true, inputType: value ? "insertText" : "deleteContentBackward", data: value || null }));
      field.dispatchEvent(new Event("change", { bubbles: true }));
      field.dispatchEvent(new Event("blur", { bubbles: true }));
    };
    const setChecked = (checkbox, checked) => {
      if (!checkbox || checkbox.checked === checked) return;
      checkbox.scrollIntoView({ block: "center", inline: "nearest" });
      checkbox.click();
      const checkedDescriptor = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, "checked");
      if (checkedDescriptor && checkedDescriptor.set) {
        checkedDescriptor.set.call(checkbox, checked);
      } else {
        checkbox.checked = checked;
      }
      checkbox.dispatchEvent(new Event("input", { bubbles: true }));
      checkbox.dispatchEvent(new Event("change", { bubbles: true }));
    };
    const datePart = (field, part) => {
      const value = clean(field?.value || "");
      if (part === "month") {
        const month = value.includes("/") ? value.split("/")[0] : value;
        return /^\d{1,2}$/.test(month) ? String(Number(month)).padStart(2, "0") : "";
      }
      const year = value.includes("/") ? value.split("/")[1] : value;
      return /^\d{4}$/.test(year) ? year : "";
    };
    const findMatchingRange = (startMonth, startYear, endYear) =>
      (dateRanges || []).find((range) =>
        range.startMonth === startMonth &&
        range.startYear === startYear &&
        ((range.current && !endYear) || range.endYear === endYear)
      ) ||
      (dateRanges || []).find((range) =>
        range.startMonth === startMonth &&
        range.startYear === startYear &&
        !range.current
      ) ||
      null;
    const prefixes = Array.from(document.querySelectorAll('input[id^="workExperience-"][id$="--currentlyWorkHere"]'))
      .map((input) => input.id.replace(/--currentlyWorkHere$/, ""));
    let fixed = 0;
    prefixes.forEach((prefix, index) => {
      const checkbox = document.getElementById(`${prefix}--currentlyWorkHere`);
      const startMonth = document.getElementById(`${prefix}--startDate-dateSectionMonth-input`);
      const startYear = document.getElementById(`${prefix}--startDate-dateSectionYear-input`);
      const endMonth = document.getElementById(`${prefix}--endDate-dateSectionMonth-input`);
      const endYear = document.getElementById(`${prefix}--endDate-dateSectionYear-input`);
      const title = clean(document.getElementById(`${prefix}--jobTitle`)?.value || "");
      const company = clean(document.getElementById(`${prefix}--companyName`)?.value || "");
      const startMonthValue = datePart(startMonth, "month") || datePart(startYear, "month");
      const startYearValue = datePart(startYear, "year") || datePart(startMonth, "year");
      const endMonthValue = datePart(endMonth, "month") || datePart(endYear, "month");
      const endYearValue = datePart(endYear, "year") || datePart(endMonth, "year");
      const matchingRange = findMatchingRange(startMonthValue, startYearValue, endYearValue);
      const shouldBeCurrent = Boolean(matchingRange?.current);
      if (checkbox && shouldBeCurrent) {
        setChecked(checkbox, true);
        setInputValue(endMonth, "");
        setInputValue(endYear, "");
        fixed += 1;
        return;
      }
      if (checkbox?.checked && !shouldBeCurrent) {
        setChecked(checkbox, false);
        fixed += 1;
      }
      const needsEndMonth =
        endMonth &&
        endYearValue &&
        (!endMonthValue || /^0?0$|^mm$/i.test(clean(endMonth?.value || "")) || Number(endMonthValue) < 1 || Number(endMonthValue) > 12);
      if (needsEndMonth && matchingRange?.endMonth) {
        setInputValue(endMonth, matchingRange.endMonth);
        fixed += 1;
      }
      if (endYear && matchingRange?.endYear && !endYearValue) {
        setInputValue(endYear, matchingRange.endYear);
        fixed += 1;
      }
    });
    return fixed;
  }, { current: currentExperience, dateRanges: cvDateRanges }).catch(() => 0);

  const workExperienceDateRows = await page.evaluate(() => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const getPart = (field, part) => {
      const value = clean(field?.value || "");
      if (part === "month") {
        const month = value.includes("/") ? value.split("/")[0] : value;
        return /^\d{1,2}$/.test(month) ? String(Number(month)).padStart(2, "0") : "";
      }
      const year = value.includes("/") ? value.split("/")[1] : value;
      return /^\d{4}$/.test(year) ? year : "";
    };
    return Array.from(document.querySelectorAll('input[id^="workExperience-"][id$="--currentlyWorkHere"]'))
      .map((checkbox) => {
        const prefix = checkbox.id.replace(/--currentlyWorkHere$/, "");
        const startMonth = document.getElementById(`${prefix}--startDate-dateSectionMonth-input`);
        const startYear = document.getElementById(`${prefix}--startDate-dateSectionYear-input`);
        const endMonth = document.getElementById(`${prefix}--endDate-dateSectionMonth-input`);
        const endYear = document.getElementById(`${prefix}--endDate-dateSectionYear-input`);
        return {
          prefix,
          checked: Boolean(checkbox.checked),
          startMonth: getPart(startMonth, "month") || getPart(startYear, "month"),
          startYear: getPart(startYear, "year") || getPart(startMonth, "year"),
          endMonth: getPart(endMonth, "month") || getPart(endYear, "month"),
          endYear: getPart(endYear, "year") || getPart(endMonth, "year"),
          rawEndMonth: clean(endMonth?.value || ""),
          rawEndYear: clean(endYear?.value || ""),
        };
      });
  }).catch(() => []);

  const dateRepairs = [];
  for (const row of workExperienceDateRows) {
    const matchingRange = cvDateRanges.find((range) =>
      range.startMonth === row.startMonth &&
      range.startYear === row.startYear &&
      (range.current || !row.endYear || range.endYear === row.endYear)
    );
    if (!matchingRange || matchingRange.current) {
      continue;
    }
    const safePrefix = cssEscapeIdentifier(row.prefix);
    const needsMonth =
      !row.endMonth ||
      /^mm$/i.test(row.rawEndMonth) ||
      Number(row.endMonth) < 1 ||
      Number(row.endMonth) > 12;
    if (needsMonth && matchingRange.endMonth) {
      const dateText = row.rawEndMonth.includes("/") || !row.rawEndYear
        ? `${matchingRange.endMonth}/${matchingRange.endYear}`
        : matchingRange.endMonth;
      const ok = await fillWorkdayDateInputBySelector(
        page,
        `#${safePrefix}--endDate-dateSectionMonth-input`,
        dateText
      ).catch(() => false);
      dateRepairs.push({
        prefix: row.prefix,
        start: `${row.startMonth}/${row.startYear}`,
        previous_end_month: row.rawEndMonth,
        previous_end_year: row.rawEndYear,
        target: dateText,
        filled: Boolean(ok),
      });
      if (ok) {
        currentExperienceFixed += 1;
      }
    }
    if (!row.endYear && matchingRange.endYear) {
      const ok = await fillWorkdayDateInputBySelector(
        page,
        `#${safePrefix}--endDate-dateSectionYear-input`,
        matchingRange.endYear
      ).catch(() => false);
      if (ok) {
        currentExperienceFixed += 1;
      }
    }
  }

  const educationPrefixes = await page.evaluate(() =>
    Array.from(document.querySelectorAll('input[id^="education-"][id$="--school"]'))
      .map((input) => input.id.replace(/--school$/, ""))
      .slice(0, 4)
  ).catch(() => []);
  let educationRowsFixed = 0;
  const educationRepairs = [];
  for (let index = 0; index < educationPrefixes.length; index += 1) {
    const prefix = educationPrefixes[index];
    const education = educationEntries[index] || educationEntries[0] || {};
    if (!education.school) {
      continue;
    }
    const safePrefix = cssEscapeIdentifier(prefix);
    let schoolOk = await fillWorkdayPromptInputBySelector(page, `#${safePrefix}--school`, education.school).catch(() => false);
    let schoolValue = education.school;
    if (!schoolOk) {
      schoolOk = await fillWorkdayPromptInputBySelector(page, `#${safePrefix}--school`, "Other").catch(() => false);
      schoolValue = "Other";
    }
    const fieldOk = education.fieldOfStudy
      ? await fillWorkdayPromptInputBySelector(page, `#${safePrefix}--fieldOfStudy`, education.fieldOfStudy).catch(() => false)
      : false;
    const gradeField = await page.$(`#${safePrefix}--gradeAverage`).catch(() => null);
    let gradeOk = gradeField && education.gradeAverage
      ? await fillWorkdayExactTextField(page, gradeField, education.gradeAverage).finally(() => gradeField.dispose().catch(() => {}))
      : false;
    if (!gradeOk && education.gradeAverage) {
      const fallbackGradeField = await findVisibleControlForItem(page, {
        label: "Overall Result (GPA)",
        fieldNames: [],
        fieldTypes: ["text"],
        choices: [],
        choiceLike: false,
        answer: education.gradeAverage,
      }).catch(() => null);
      gradeOk = fallbackGradeField
        ? await fillWorkdayExactTextField(page, fallbackGradeField, education.gradeAverage)
            .finally(() => fallbackGradeField.dispose().catch(() => {}))
            .catch(() => false)
        : false;
    }
    const firstYearField = await page.$(`#${safePrefix}--firstYearAttended-dateSectionYear-input`).catch(() => null);
    const firstYearOk = firstYearField && education.firstYear
      ? await fillWorkdayExactTextField(page, firstYearField, education.firstYear).finally(() => firstYearField.dispose().catch(() => {}))
      : false;
    const lastYearField = await page.$(`#${safePrefix}--lastYearAttended-dateSectionYear-input`).catch(() => null);
    const lastYearOk = lastYearField && education.lastYear
      ? await fillWorkdayExactTextField(page, lastYearField, education.lastYear).finally(() => lastYearField.dispose().catch(() => {}))
      : false;
    const degreeOk = await selectWorkdayEducationDegree(page, prefix, education.degree).catch(() => false);
    educationRepairs.push({
      prefix,
      school: schoolValue,
      degree: education.degree,
      field_of_study: education.fieldOfStudy,
      grade_average: education.gradeAverage,
      first_year: education.firstYear,
      last_year: education.lastYear,
      school_ok: Boolean(schoolOk),
      degree_ok: Boolean(degreeOk),
      field_ok: Boolean(fieldOk),
      grade_ok: Boolean(gradeOk),
      first_year_ok: Boolean(firstYearOk),
      last_year_ok: Boolean(lastYearOk),
    });
    if (schoolOk || fieldOk || gradeOk || firstYearOk || lastYearOk || degreeOk) {
      educationRowsFixed += 1;
    }
  }
  return {
    current_experience_fixed: currentExperienceFixed,
    date_rows: workExperienceDateRows,
    date_repairs: dateRepairs,
    education_rows_fixed: educationRowsFixed,
    education_repairs: educationRepairs,
    education_entries: educationEntries.length,
    experience_entries: experienceEntries.length,
  };
}

async function selectWorkdayChoiceByLabel(page, label, answer) {
  const labelText = cleanText(label).toLowerCase();
  const isPhoneCode = /country phone code/.test(labelText);
  const isSource = /how did you hear about us|source/.test(labelText);
  if (isPhoneCode) {
    return setWorkdayPhoneCountryCode(page, answer);
  }
  let directControl = null;
  const handle = await page.evaluateHandle(
    ({ label: targetLabel, exactOnly }) => {
      const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
      const compact = (value) => clean(value).toLowerCase().replace(/[^a-z0-9]+/g, "");
      const wanted = compact(targetLabel);
      const visible = (element) => {
        if (!element || element.disabled || element.type === "hidden") return false;
        const style = window.getComputedStyle(element);
        const rect = element.getBoundingClientRect();
        return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
      };
      const labels = Array.from(document.querySelectorAll("label, legend, [class*='label' i], [id*='label' i]"))
        .filter((node) => {
          const text = compact(`${node.textContent || ""} ${node.getAttribute("aria-label") || ""}`);
          if (!text || !wanted) return false;
          return exactOnly ? text === wanted || text.includes(wanted) : text === wanted || text.includes(wanted) || wanted.includes(text);
        })
        .sort((a, b) => clean(a.textContent || "").length - clean(b.textContent || "").length);
      for (const labelNode of labels) {
        const forId = labelNode.getAttribute("for");
        const byFor = forId ? document.getElementById(forId) : null;
        if (visible(byFor)) return byFor;
        let scope = labelNode.closest("fieldset, section, li, div") || labelNode.parentElement;
        for (let depth = 0; depth < 6 && scope; depth += 1) {
          const control = Array.from(
            scope.querySelectorAll("[role='combobox'], [aria-haspopup='listbox'], input:not([type='hidden']), button, select")
          ).find((element) => {
            if (!visible(element)) return false;
            if (element.closest("header, nav")) return false;
            const text = compact(`${element.textContent || ""} ${element.getAttribute("aria-label") || ""}`);
            return element.tagName === "SELECT" || element.tagName === "INPUT" || element.getAttribute("role") === "combobox" || /select|choose|itemselected|\+\d+/.test(text);
          });
          if (control) return control;
          scope = scope.parentElement;
        }
      }
      return null;
    },
    { label, exactOnly: isPhoneCode }
  );
  const control = directControl || handle.asElement();
  if (!control) {
    await handle.dispose().catch(() => {});
    return false;
  }

  const target = cleanText(answer);
  const wantedCode = (target.match(/\+?\d{1,4}/) || [""])[0].replace(/\D/g, "");
  const nativeSelect = await control.evaluate((element) => element.tagName === "SELECT").catch(() => false);
  if (nativeSelect) {
    const selected = await selectNativeOption(control, target, []);
    await control.dispose().catch(() => {});
    await handle.dispose().catch(() => {});
    return selected;
  }

  if (isPhoneCode) {
    const chipClearPoint = await control
      .evaluate((element) => {
        const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
        let scope = element.closest("fieldset, section, li, div") || element.parentElement;
        for (let depth = 0; depth < 5 && scope; depth += 1) {
          const text = clean(scope.textContent || "");
          if (/country phone code|item selected|\+\d{1,4}/i.test(text)) {
            break;
          }
          scope = scope.parentElement;
        }
        const clearTarget = scope
          ? Array.from(scope.querySelectorAll("button, [role='button'], [aria-label], svg"))
              .map((node) => node.closest("button, [role='button']") || node)
              .find((node) => {
                const label = clean(`${node.textContent || ""} ${node.getAttribute("aria-label") || ""}`);
                return /clear|remove|delete|close|×|x$/i.test(label) || clean(node.textContent || "") === "×";
              })
          : null;
        if (clearTarget) {
          clearTarget.click();
          return null;
        }
        const rect = element.getBoundingClientRect();
        return { x: rect.left + 18, y: rect.top + rect.height / 2 };
      })
      .catch(() => null);
    if (chipClearPoint && Number.isFinite(chipClearPoint.x) && Number.isFinite(chipClearPoint.y)) {
      await page.mouse.click(chipClearPoint.x, chipClearPoint.y).catch(() => {});
    }
    await new Promise((resolve) => setTimeout(resolve, 250));
  }

  await control.evaluate((element) => element.scrollIntoView({ block: "center", inline: "nearest" })).catch(() => {});
  await control.click({ clickCount: 3 }).catch(() => {});
  const tagMeta = await control.evaluate((element) => ({ tag: element.tagName, value: element.value || "" })).catch(() => ({ tag: "", value: "" }));
  if (tagMeta.tag === "INPUT") {
    const textToType = isPhoneCode ? target.replace(/\s*\([^)]*\)\s*/g, "") : target;
    const modifier = process.platform === "darwin" ? "Meta" : "Control";
    await page.keyboard.down(modifier).catch(() => {});
    await page.keyboard.press("KeyA").catch(() => {});
    await page.keyboard.up(modifier).catch(() => {});
    await page.keyboard.press("Backspace").catch(() => {});
    if (isPhoneCode) {
      await page.keyboard.press("Backspace").catch(() => {});
      await page.keyboard.press("Backspace").catch(() => {});
    }
    await page.keyboard.type(textToType, { delay: 15 }).catch(() => {});
  }
  await new Promise((resolve) => setTimeout(resolve, 600));

  const option = await page.evaluateHandle(
    ({ answer: targetAnswer, isPhoneCode: phoneCode, isSource: sourceField, wantedCode: phoneCodeDigits }) => {
      const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
      const normalize = (value) => clean(value).toLowerCase();
      const compact = (value) => normalize(value).replace(/[^a-z0-9]+/g, "");
      const visible = (element) => {
        if (!element) return false;
        const style = window.getComputedStyle(element);
        const rect = element.getBoundingClientRect();
        return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
      };
      const score = (candidate) => {
        const wanted = normalize(targetAnswer);
        const option = normalize(candidate);
        const wantedCompact = compact(targetAnswer);
        const optionCompact = compact(candidate);
        if (!option || /select one|choose|please select/.test(option)) return 0;
        const candidateCode = (option.match(/\+?\d{1,4}/) || [""])[0].replace(/\D/g, "");
        if (phoneCode) {
          return phoneCodeDigits && candidateCode === phoneCodeDigits ? 200 : 0;
        }
        if (wanted && (option === wanted || optionCompact === wantedCompact)) return 160;
        if (wantedCompact && optionCompact.includes(wantedCompact)) return 130;
        if (wantedCompact && wantedCompact.includes(optionCompact)) return 110;
        if (sourceField) {
          if (/employee|referral|referred/i.test(candidate) && !/employee|referral|referred/i.test(targetAnswer)) return 5;
          if (/company website|careers website|career site|company site|website/i.test(candidate)) return 100;
          if (/job board|linkedin/i.test(candidate)) return 80;
          if (/other/i.test(candidate)) return 60;
          return 20;
        }
        return 0;
      };
      const options = Array.from(
        document.querySelectorAll("[role='option'], [data-automation-id*='promptOption'], [id*='promptOption'], li, [role='listbox'] div")
      )
        .filter(visible)
        .map((node) => ({ node, text: clean(`${node.textContent || ""} ${node.getAttribute("aria-label") || ""}`) }))
        .filter((entry) => entry.text)
        .map((entry) => ({ ...entry, score: score(entry.text) }))
        .filter((entry) => entry.score > 0)
        .sort((a, b) => b.score - a.score);
      return options[0]?.node || null;
    },
    { answer: target, isPhoneCode, isSource, wantedCode }
  );
  const optionElement = option.asElement();
  if (optionElement) {
    await optionElement.click().catch(() => {});
    await option.dispose().catch(() => {});
    await control.dispose().catch(() => {});
    await handle.dispose().catch(() => {});
    await new Promise((resolve) => setTimeout(resolve, 450));
    return true;
  }
  await option.dispose().catch(() => {});
  await control.dispose().catch(() => {});
  await handle.dispose().catch(() => {});
  return false;
}

async function setWorkdayPhoneCountryCode(page, answer) {
  const target = cleanText(answer || "France (+33)");
  const wantedCode = (target.match(/\+?\d{1,4}/) || [""])[0].replace(/\D/g, "");
  const countryText = cleanText(target.replace(/\s*\([^)]*\)\s*/g, "")) || "France";
  const selector = 'input[id="phoneNumber--countryPhoneCode"], input[name="phoneNumber--countryPhoneCode"]';
  let input = await page.$(selector).catch(() => null);
  if (!input) {
    return false;
  }

  const hasSelectedCode = async () =>
    input
      .evaluate((element, code) => {
      const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
      const hasCode = (text) => {
        if (!code) {
          return /\+\d{1,4}/.test(text);
        }
        return new RegExp(`\\+${code}\\b`).test(text);
      };
      let scope = element;
      for (let depth = 0; depth < 10 && scope; depth += 1) {
        const text = clean(scope.textContent || "");
        if (/country phone code/i.test(text) && hasCode(text)) {
          return true;
        }
        scope = scope.parentElement;
      }
      const body = clean(document.body?.innerText || "");
      const labelIndex = body.toLowerCase().indexOf("country phone code");
      if (labelIndex >= 0 && hasCode(body.slice(labelIndex, labelIndex + 260))) {
        return true;
      }
      return false;
    }, wantedCode)
      .catch(() => false);

  if (await hasSelectedCode()) {
    await input.dispose().catch(() => {});
    return true;
  }

  await input.evaluate((element) => element.scrollIntoView({ block: "center", inline: "nearest" })).catch(() => {});
  await input
    .evaluate((element) => {
      const rect = element.getBoundingClientRect();
      return { x: rect.left + Math.max(24, rect.width - 24), y: rect.top + rect.height / 2 };
    })
    .then((point) => page.mouse.click(point.x, point.y))
    .catch(() => input.click());
  await new Promise((resolve) => setTimeout(resolve, 300));
  await input.evaluate((element) => element.focus()).catch(() => {});
  const searchText = wantedCode ? `+${wantedCode}` : countryText;
  await page.keyboard.type(searchText, { delay: 30 }).catch(() => {});
  await new Promise((resolve) => setTimeout(resolve, 1400));

  const optionHandle = await page.evaluateHandle(({ code, country }) => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const visible = (element) => {
      if (!element) return false;
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
    };
    const codePattern = code ? new RegExp(`\\+${code}\\b`) : null;
    const countryPattern = country ? new RegExp(country.replace(/[.*+?^${}()|[\]\\]/g, "\\$&"), "i") : null;
    const options = Array.from(document.querySelectorAll("[role='option'], [data-automation-id*='promptOption'], [id*='promptOption'], li, [role='listbox'] *"))
      .filter(visible)
      .map((node) => ({ node, text: clean(`${node.textContent || ""} ${node.getAttribute("aria-label") || ""}`) }))
      .filter((entry) => entry.text && ((codePattern && codePattern.test(entry.text)) || (countryPattern && countryPattern.test(entry.text))));
    return options[0]?.node || null;
  }, { code: wantedCode, country: countryText });
  const option = optionHandle.asElement();
  if (option) {
    await option.click().catch(() => {});
    await optionHandle.dispose().catch(() => {});
  } else {
    await optionHandle.dispose().catch(() => {});
    await input.dispose().catch(() => {});
    await page.keyboard.press("Escape").catch(() => {});
    return false;
  }
  await input.dispose().catch(() => {});
  await new Promise((resolve) => setTimeout(resolve, 700));

  const recheckInput = await page.$(selector).catch(() => null);
  if (!recheckInput) {
    return false;
  }
  input = recheckInput;
  const verified = await hasSelectedCode();
  await input.dispose().catch(() => {});
  return Boolean(verified);
}

function getWorkdayStageFromState(state = {}, stepState = {}) {
  if (state.has_submission_confirmation || stepState.has_submission_signal) {
    return "submitted";
  }
  if (state.has_verification || stepState.active_step === "verification") {
    return "verification";
  }
  if (state.has_create_account || state.has_sign_in || stepState.active_step === "account") {
    return "account";
  }
  if (stepState.active_step) {
    return stepState.active_step;
  }
  const text = cleanText(state.text_sample || "").toLowerCase();
  if (/submit application|review your application|review and submit/.test(text)) {
    return "review";
  }
  if (/my experience|work experience|school or university|overall result|field of study/.test(text)) {
    return "my_experience";
  }
  if (/resume|cv|upload/.test(text) || Number(state.file_input_count || 0) > 0) {
    return "resume";
  }
  if (/legal name|contact information|address|phone|email/.test(text)) {
    return "my_information";
  }
  if (/application questions|questionnaire|sponsorship|work authorization/.test(text)) {
    return "application_questions";
  }
  if (/voluntary|self-identification|gender|disability|veteran/.test(text)) {
    return "voluntary_disclosures";
  }
  return state.field_count || state.file_input_count ? "form" : "";
}

function getWorkdayProgressSignature(state = {}, stepState = {}) {
  return [
    cleanText(state.url || ""),
    getWorkdayStageFromState(state, stepState),
    Number(state.field_count || 0),
    Number(state.file_input_count || 0),
    cleanText(stepState.body_compact_sample || state.text_sample || "").slice(0, 220),
  ].join("|");
}

async function waitForWorkdayStepTransition(page, previousSignature = "") {
  await page
    .waitForFunction(
      (signature) => {
        const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
        const text = clean(document.body?.innerText || "");
        const fields = Array.from(document.querySelectorAll("input, textarea, select")).filter((field) => {
          if (field.type === "hidden" || field.disabled) return false;
          const style = window.getComputedStyle(field);
          const rect = field.getBoundingClientRect();
          return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
        });
        const activeStep =
          (text.match(/current step\s+\d+\s+of\s+\d+\s+([A-Za-z ]{2,80}?)(?:\s+step\s+\d+\s+of|\s+[A-Z][a-z]+\s+\*|\s+\*\s+Indicates|$)/i) || [])[1] ||
          "";
        const stage =
          /application questions|questionnaire|work authorization|sponsorship/i.test(text)
            ? "application_questions"
            : /voluntary|self-identification|gender|disability|veteran/i.test(text)
              ? "voluntary_disclosures"
              : /review|submit application|review and submit/i.test(text)
                ? "review"
                : /my experience|work experience|school or university|overall result/i.test(text)
                  ? "my_experience"
                  : /my information|legal name|contact information|address|phone/i.test(text)
                    ? "my_information"
                    : "";
        const nextSignature = [
          clean(location.href),
          stage || clean(activeStep).toLowerCase().replace(/[^a-z0-9]+/g, "_"),
          fields.length,
          0,
          text.slice(0, 220),
        ].join("|");
        return Boolean(nextSignature && nextSignature !== signature && (fields.length || stage));
      },
      { timeout: 30000 },
      previousSignature
    )
    .catch(() => {});
  await new Promise((resolve) => setTimeout(resolve, 800));
}

async function waitForWorkdayStageControls(page, stage) {
  const wantedStage = cleanText(stage);
  if (!wantedStage) {
    return;
  }
  await page
    .waitForFunction(
      (targetStage) => {
        const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
        const text = clean(document.body?.innerText || "");
        const fields = Array.from(document.querySelectorAll("input, textarea, select")).filter((field) => {
          if (field.type === "hidden" || field.disabled) return false;
          const style = window.getComputedStyle(field);
          const rect = field.getBoundingClientRect();
          return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
        });
        const uploadControl = Array.from(document.querySelectorAll("button, a, [role='button'], label")).some((element) =>
          /select file|upload.*(?:resume|cv)|attach.*(?:resume|cv)/i.test(String(element.textContent || element.getAttribute("aria-label") || ""))
        );
        const hasTargetText =
          targetStage === "my_experience"
            ? /my experience|work experience|school or university|education|resume\/cv/i.test(text)
            : targetStage === "application_questions"
              ? /application questions|work authorization|sponsorship|questionnaire/i.test(text)
              : targetStage === "voluntary_disclosures"
                ? /voluntary disclosures|self-identification|gender|disability|veteran/i.test(text)
                : targetStage === "review"
                  ? /review|submit application|review and submit/i.test(text)
                  : true;
        return Boolean(hasTargetText && (fields.length || uploadControl));
      },
      { timeout: 45000 },
      wantedStage
    )
    .catch(() => {});
  await new Promise((resolve) => setTimeout(resolve, 500));
}

function isWorkdayLoadingOnlyStage(state = {}) {
  const sample = cleanText(state.text_sample || "");
  return Boolean(
    /Loading/i.test(sample) &&
      !Number(state.field_count || 0) &&
      !Number(state.file_input_count || 0) &&
      !Number(state.upload_control_count || 0)
  );
}

function getEffectiveWorkdayStage(stage, stepState = {}) {
  const activeStep = cleanText(stepState.active_step || "");
  if (["my_information", "my_experience", "application_questions", "voluntary_disclosures", "review"].includes(activeStep)) {
    return activeStep;
  }
  return stage;
}

async function clickWorkdayNextForStage(page, stage) {
  if (stage === "review" || stage === "submitted" || stage === "verification" || stage === "account") {
    return false;
  }
  const automationIds = [
    "pageFooterNextButton",
    "bottom-navigation-next-button",
    "bottom-navigation-next",
    "saveAndContinueButton",
    "nextButton",
    "continueButton",
  ];
  const patterns = [
    /save and continue/,
    /^next$/,
    /^continue$/,
    /^review$/,
    /review application/,
  ];
  await page.keyboard.press("Escape").catch(() => {});
  const clicked = await clickWorkdayByAutomationOrText(page, automationIds, patterns);
  if (clicked) {
    return true;
  }
  return page.evaluate((patternSources) => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const regexes = patternSources.map((source) => new RegExp(source, "i"));
    const visible = (element) => {
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
    };
    window.scrollTo(0, document.body.scrollHeight);
    const buttons = Array.from(document.querySelectorAll("button, a, [role='button'], input[type='submit']"));
    const target = buttons.find((button) => {
      const automationId = button.getAttribute("data-automation-id") || "";
      if (/utility|navigation|social|logo|language|locale|menu|backToJobPosting/i.test(automationId)) {
        return false;
      }
      if (button.closest("header, nav")) {
        return false;
      }
      const text = clean(`${button.textContent || ""} ${button.getAttribute("aria-label") || ""} ${button.value || ""}`);
      return visible(button) && regexes.some((regex) => regex.test(text));
    });
    if (!target) {
      return false;
    }
    target.scrollIntoView({ block: "center", inline: "center" });
    target.click();
    return true;
  }, patterns.map((pattern) => pattern.source)).catch(() => false);
}

async function ensureWorkdayPreferredLocale(page, preflight = {}) {
  const preferredLocale = cleanText(preflight.locale || "en-US");
  if (!preferredLocale) {
    return false;
  }
  const currentUrl = page.url();
  const localeMatch = currentUrl.match(/\/([a-z]{2}-[A-Z]{2})\//);
  if (!localeMatch || localeMatch[1] === preferredLocale) {
    return false;
  }
  const preferredUrl = currentUrl.replace(`/${localeMatch[1]}/`, `/${preferredLocale}/`);
  await withTimeout(
    page.goto(preferredUrl, { waitUntil: "domcontentloaded", timeout: Math.min(navigationTimeoutMs, 25000) }),
    30000,
    null
  ).catch(() => {});
  await withTimeout(waitForWorkdayShell(page), 25000, null).catch(() => {});
  return true;
}

async function waitForWorkdayPostTransitionReady(page, stage, preflight = {}) {
  await ensureWorkdayPreferredLocale(page, preflight).catch(() => false);
  await withTimeout(waitForWorkdayStepTransition(page, ""), 12000, null).catch(() => {});
  let nextState = await withTimeout(getWorkdayVisibleState(page), 10000, {});
  let nextStepState = await withTimeout(getWorkdayStepState(page), 10000, {});
  let nextStage = getEffectiveWorkdayStage(getWorkdayStageFromState(nextState, nextStepState) || stage, nextStepState);
  if (isWorkdayLoadingOnlyStage(nextState)) {
    await withTimeout(waitForWorkdayStageControls(page, nextStage), 30000, null).catch(() => {});
    await ensureWorkdayPreferredLocale(page, preflight).catch(() => false);
    nextState = await withTimeout(getWorkdayVisibleState(page), 10000, {});
    nextStepState = await withTimeout(getWorkdayStepState(page), 10000, {});
    nextStage = getEffectiveWorkdayStage(getWorkdayStageFromState(nextState, nextStepState) || nextStage, nextStepState);
  }
  return { state: nextState, stepState: nextStepState, stage: nextStage };
}

async function advanceWorkdaySteps(page, task, candidate, apiSchema = null, preflight = {}) {
  const states = [];
  let uploadedResume = false;
  let resumeUpload = { attempted: false, uploaded: false, confirmed: false, clicked_upload: false, state: {} };
  let answersFilled = { attempted: 0, filled: 0, choice_attempted: 0, choice_filled: 0, field_diagnostics: [], items: [] };
  let previousSignature = "";
  let stagnantIterations = 0;
  for (let index = 0; index < 16; index += 1) {
    let state = await getWorkdayVisibleState(page);
    let stepState = await getWorkdayStepState(page);
    let liveSchema = await discoverWorkdayLiveSchema(page);
    let mergedSchema = apiSchema
      ? {
          provider: "workday",
          questions: mergeQuestionsByFieldOrLabel(liveSchema.questions || [], getSchemaQuestions(apiSchema)),
        }
      : liveSchema;
    let stage = getEffectiveWorkdayStage(getWorkdayStageFromState(state, stepState), stepState);
    if (
      ["my_experience", "application_questions", "voluntary_disclosures", "review"].includes(stage) &&
      !state.field_count &&
      !state.file_input_count &&
      !state.upload_control_count
    ) {
      debugLog(task.task_uuid || "task", "workday_wait_stage_controls_start", JSON.stringify({ step: index + 1, stage }));
      await waitForWorkdayStageControls(page, stage);
      state = await getWorkdayVisibleState(page);
      stepState = await getWorkdayStepState(page);
      liveSchema = await discoverWorkdayLiveSchema(page);
      mergedSchema = apiSchema
        ? {
            provider: "workday",
            questions: mergeQuestionsByFieldOrLabel(liveSchema.questions || [], getSchemaQuestions(apiSchema)),
          }
        : liveSchema;
      stage = getEffectiveWorkdayStage(getWorkdayStageFromState(state, stepState), stepState);
      debugLog(task.task_uuid || "task", "workday_wait_stage_controls_result", JSON.stringify({
        step: index + 1,
        stage,
        field_count: state.field_count,
        file_input_count: state.file_input_count,
        upload_control_count: state.upload_control_count,
      }));
    }
    const signature = getWorkdayProgressSignature(state, stepState);
    states.push({
      step: index + 1,
      stage,
      workday_step: stepState,
      live_question_count: liveSchema.questions.length,
      api_question_count: getSchemaQuestions(apiSchema || {}).length,
      merged_question_count: mergedSchema.questions.length,
      ...state,
    });
    debugLog(task.task_uuid || "task", "workday_advance_step", JSON.stringify({
      step: index + 1,
      stage,
      active_step: stepState.active_step,
      field_count: state.field_count,
      file_input_count: state.file_input_count,
      upload_control_count: state.upload_control_count,
      live_question_count: liveSchema.questions.length,
    }));
    if (stage === "submitted" || stage === "review" || stage === "verification" || stage === "account") {
      break;
    }
    if (!resumeUpload.confirmed && (stepState.has_resume_signal || state.file_input_count > 0)) {
      debugLog(task.task_uuid || "task", "workday_resume_upload_start", JSON.stringify({ step: index + 1, stage }));
      resumeUpload = await uploadWorkdayResume(page, task.__sffc_cv_path || "");
      debugLog(task.task_uuid || "task", "workday_resume_upload_result", JSON.stringify(resumeUpload));
      uploadedResume = uploadedResume || resumeUpload.confirmed;
    }
    let experienceRepair = null;
    if (stage === "my_experience" || stepState.active_step === "my_experience") {
      debugLog(task.task_uuid || "task", "workday_experience_repair_start", JSON.stringify({ step: index + 1 }));
      experienceRepair = await repairWorkdayExperiencePage(page, task).catch(() => ({
        current_experience_fixed: 0,
        date_rows: [],
        date_repairs: [],
        education_rows_fixed: 0,
      }));
      debugLog(task.task_uuid || "task", "workday_experience_repair_result", JSON.stringify(experienceRepair));
      if (experienceRepair.current_experience_fixed || experienceRepair.education_rows_fixed || experienceRepair.date_repairs?.length) {
        answersFilled.filled += experienceRepair.current_experience_fixed + experienceRepair.education_rows_fixed;
        answersFilled.field_diagnostics.push({ workday_experience_repair: experienceRepair });
      }
    }
    const shouldRunGenericFill = stage !== "my_experience" && stepState.active_step !== "my_experience";
    debugLog(task.task_uuid || "task", "workday_generic_fill_start", JSON.stringify({ step: index + 1, run: shouldRunGenericFill }));
    const stepAnswers = shouldRunGenericFill
      ? await withTimeout(fillApplicationAnswers(page, task, mergedSchema), 20000, {
          attempted: 0,
          filled: 0,
          choice_attempted: 0,
          choice_filled: 0,
          field_diagnostics: [{ timeout: true, stage }],
          items: [],
        }).catch(() => ({
          attempted: 0,
          filled: 0,
          choice_attempted: 0,
          choice_filled: 0,
          field_diagnostics: [],
          items: [],
        }))
      : {
          attempted: 0,
          filled: 0,
          choice_attempted: 0,
          choice_filled: 0,
          field_diagnostics: [{ skipped: "workday_experience_uses_specific_repair" }],
          items: [],
        };
    debugLog(task.task_uuid || "task", "workday_generic_fill_result", JSON.stringify({
      step: index + 1,
      attempted: stepAnswers.attempted,
      filled: stepAnswers.filled,
      timed_out: Boolean(stepAnswers.timeout || stepAnswers.field_diagnostics?.some((entry) => entry.timeout)),
    }));
    if (stage === "application_questions" && state.field_count > 0) {
      debugLog(task.task_uuid || "task", "workday_application_questions_repair_start", JSON.stringify({ step: index + 1 }));
      const questionnaireRepair = await withTimeout(
        repairWorkdayApplicationQuestionsPage(page, task),
        90000,
        { attempted: 0, filled: 0, timeout: true, items: [] }
      ).catch((error) => ({ attempted: 0, filled: 0, error: error?.message || String(error), items: [] }));
      debugLog(task.task_uuid || "task", "workday_application_questions_repair_result", JSON.stringify(questionnaireRepair));
      if (questionnaireRepair.attempted || questionnaireRepair.filled) {
        stepAnswers.attempted += questionnaireRepair.attempted || 0;
        stepAnswers.filled += questionnaireRepair.filled || 0;
        stepAnswers.choice_attempted += (questionnaireRepair.items || []).filter((item) => item.kind !== "textarea").length;
        stepAnswers.choice_filled += (questionnaireRepair.items || []).filter((item) => item.kind !== "textarea" && item.filled).length;
        stepAnswers.field_diagnostics = [
          ...(stepAnswers.field_diagnostics || []),
          { workday_application_questions_repair: questionnaireRepair },
        ];
      }
    }
    if (stage === "voluntary_disclosures" && state.field_count > 0) {
      debugLog(task.task_uuid || "task", "workday_voluntary_disclosures_repair_start", JSON.stringify({ step: index + 1 }));
      const voluntaryRepair = await withTimeout(
        repairWorkdayVoluntaryDisclosuresPage(page, task),
        12000,
        { attempted: 0, filled: 0, timeout: true, items: [] }
      ).catch((error) => ({ attempted: 0, filled: 0, error: error?.message || String(error), items: [] }));
      debugLog(task.task_uuid || "task", "workday_voluntary_disclosures_repair_result", JSON.stringify(voluntaryRepair));
      if (voluntaryRepair.attempted || voluntaryRepair.filled) {
        stepAnswers.attempted += voluntaryRepair.attempted || 0;
        stepAnswers.filled += voluntaryRepair.filled || 0;
        stepAnswers.choice_attempted += voluntaryRepair.attempted || 0;
        stepAnswers.choice_filled += voluntaryRepair.filled || 0;
        stepAnswers.field_diagnostics = [
          ...(stepAnswers.field_diagnostics || []),
          { workday_voluntary_disclosures_repair: voluntaryRepair },
        ];
      }
    }
    answersFilled = {
      attempted: answersFilled.attempted + stepAnswers.attempted,
      filled: answersFilled.filled + stepAnswers.filled,
      choice_attempted: answersFilled.choice_attempted + stepAnswers.choice_attempted,
      choice_filled: answersFilled.choice_filled + stepAnswers.choice_filled,
      field_diagnostics: [...(answersFilled.field_diagnostics || []), ...(stepAnswers.field_diagnostics || [])],
      items: [...(answersFilled.items || []), ...(stepAnswers.items || [])],
    };
    if (stage === "my_information" || stage === "form") {
      debugLog(task.task_uuid || "task", "workday_known_repairs_start", JSON.stringify({ step: index + 1, stage }));
      const repairedChoices = await withTimeout(repairWorkdayKnownChoices(page, task), 12000, 0).catch(() => 0);
      const repairedFields = await withTimeout(repairWorkdayKnownFields(page, task, candidate), 12000, 0).catch(() => 0);
      debugLog(task.task_uuid || "task", "workday_known_repairs_result", JSON.stringify({
        step: index + 1,
        choices: repairedChoices,
        fields: repairedFields,
      }));
      if (repairedChoices || repairedFields) {
        answersFilled.filled += repairedChoices + repairedFields;
        answersFilled.choice_filled += repairedChoices;
      }
    }
    if (stage === "application_questions" && isWorkdayLoadingOnlyStage(state)) {
      const completion = await getRequiredFormCompletionState(page).catch(() => ({
        complete_required_fields: [],
        missing_required_fields: [],
      }));
      answersFilled.field_diagnostics.push({
        workday_application_questions_loading_only: true,
        missing_required_fields: completion.missing_required_fields || [],
      });
      if (completion.missing_required_fields?.length) {
        break;
      }
    }
    if (workdayStopAfterStage && stage === workdayStopAfterStage) {
      debugLog(task.task_uuid || "task", "workday_stop_after_stage", JSON.stringify({ step: index + 1, stage }));
      break;
    }
    const clickedNext = await clickWorkdayNextForStage(page, stage);
    debugLog(task.task_uuid || "task", "workday_click_next_result", JSON.stringify({ step: index + 1, stage, clicked: clickedNext }));
    if (!clickedNext) {
      break;
    }
    await waitForWorkdayStepTransition(page, signature);
    const transition = await waitForWorkdayPostTransitionReady(page, stage, preflight);
    const nextState = transition.state;
    const nextStepState = transition.stepState;
    const nextSignature = getWorkdayProgressSignature(nextState, nextStepState);
    stagnantIterations = nextSignature === signature || nextSignature === previousSignature ? stagnantIterations + 1 : 0;
    previousSignature = signature;
    if (stagnantIterations >= 2) {
      states.push({
        step: index + 1,
        stage: "stalled",
        workday_step: nextStepState,
        live_question_count: 0,
        api_question_count: getSchemaQuestions(apiSchema || {}).length,
        merged_question_count: 0,
        ...nextState,
      });
      break;
    }
  }
  return { states, uploaded_resume: uploadedResume, resume_upload: resumeUpload, application_answers: answersFilled };
}

async function processWorkdayTask(page, task, candidate, cvPath, url) {
  task.__sffc_cv_path = cvPath;
  const preflight = await getWorkdayPreflight(task, url);
  const targetUrl = preflight.canonicalUrl || url;
  const screenshotPath = path.join(os.tmpdir(), `${task.task_uuid}-workday.png`);
  if (page.url() !== targetUrl) {
    debugLog(task.task_uuid || "task", "workday_goto_target", targetUrl);
    await page.goto(targetUrl, { waitUntil: "domcontentloaded", timeout: navigationTimeoutMs });
  }
  debugLog(task.task_uuid || "task", "workday_wait_shell_initial");
  await waitForWorkdayShell(page);
  debugLog(task.task_uuid || "task", "workday_flow_ready_start");
  const flow = await ensureWorkdayApplicationFlowReady(page, task, preflight);
  debugLog(
    task.task_uuid || "task",
    "workday_flow_ready_result",
    JSON.stringify({
      opened_apply: flow.opened_apply,
      opened_apply_from_candidate_home: Boolean(flow.opened_apply_from_candidate_home),
      candidate_home_resume: flow.candidate_home_resume
        ? {
            attempted: flow.candidate_home_resume.attempted,
            opened_candidate_home: flow.candidate_home_resume.opened_candidate_home,
            found_draft: flow.candidate_home_resume.found_draft,
            opened_action_menu: flow.candidate_home_resume.opened_action_menu,
            clicked_continue: flow.candidate_home_resume.clicked_continue,
            reason: flow.candidate_home_resume.reason,
          }
        : null,
      form_ready: flow.form_ready,
      account_flow: flow.account_flow
        ? {
            attempted: flow.account_flow.attempted,
            clicked_create_account: flow.account_flow.clicked_create_account,
            submitted_create_account: flow.account_flow.submitted_create_account,
            requires_consent: flow.account_flow.requires_consent,
            verification_required: flow.account_flow.verification_required,
            account_action_blocked: flow.account_flow.account_action_blocked,
            last_error: flow.account_flow.last_error,
          }
        : null,
      state: {
        url: flow.state?.url,
        field_count: flow.state?.field_count,
        file_input_count: flow.state?.file_input_count,
        has_verification: flow.state?.has_verification,
        has_create_account: flow.state?.has_create_account,
        has_sign_in: flow.state?.has_sign_in,
      },
    })
  );
  debugLog(task.task_uuid || "task", "workday_verification_start");
  const verificationFlow = await waitForWorkdayVerificationAndContinue(page, task, preflight, flow);
  debugLog(
    task.task_uuid || "task",
    "workday_verification_result",
    JSON.stringify({
      attempted: verificationFlow.attempted,
      completed: verificationFlow.completed,
      timed_out: verificationFlow.timed_out,
      last_error: verificationFlow.last_error,
    })
  );
  if (verificationFlow.attempted) {
    flow.account_verification = verificationFlow;
    flow.state = verificationFlow.state || flow.state;
    flow.form_ready = Boolean(
      verificationFlow.completed && isWorkdayApplicationFormState(flow.state) && !flow.state?.has_verification
    );
    if (verificationFlow.completed) {
      const readyState = await getWorkdayVisibleState(page);
      flow.state = readyState;
      flow.form_ready = isWorkdayApplicationFormState(readyState);
    }
  }
  debugLog(task.task_uuid || "task", "workday_core_fields_start", Boolean(flow.form_ready));
  const coreFields = flow.form_ready ? await fillWorkdayCoreCandidateFields(page, candidate) : {};
  debugLog(task.task_uuid || "task", "workday_advance_start", Boolean(flow.form_ready));
  const advanced = flow.form_ready
    ? await advanceWorkdaySteps(page, task, candidate, preflight.api_schema || null, preflight)
    : { states: [], uploaded_resume: false, application_answers: { attempted: 0, filled: 0, choice_attempted: 0, choice_filled: 0, field_diagnostics: [], items: [] } };
  debugLog(
    task.task_uuid || "task",
    "workday_advance_result",
    JSON.stringify({
      states: advanced.states.length,
      uploaded_resume: advanced.uploaded_resume,
      answers_filled: advanced.application_answers.filled,
      answers_attempted: advanced.application_answers.attempted,
    })
  );
  const shouldRunPostAdvanceRepairs = Boolean(flow.form_ready && !workdayStopAfterStage);
  const repairedKnownChoices = shouldRunPostAdvanceRepairs
    ? await withTimeout(repairWorkdayKnownChoices(page, task), 12000, 0).catch(() => 0)
    : 0;
  debugLog(task.task_uuid || "task", "workday_repair_known_choices", repairedKnownChoices);
  const repairedKnownFields = shouldRunPostAdvanceRepairs
    ? await withTimeout(repairWorkdayKnownFields(page, task, candidate), 12000, 0).catch(() => 0)
    : 0;
  debugLog(task.task_uuid || "task", "workday_repair_known_fields", repairedKnownFields);
  const formCompletion = await withTimeout(getRequiredFormCompletionState(page), 10000, {
    complete_required_fields: [],
    missing_required_fields: [],
  }).catch(() => ({
    complete_required_fields: [],
    missing_required_fields: [],
  }));
  await page.screenshot({ path: screenshotPath, fullPage: true }).catch(() => {});
  const finalState = await withTimeout(getWorkdayVisibleState(page), 10000, {});
  const finalStepState = await withTimeout(getWorkdayStepState(page), 10000, {});
  const accountFlow = flow.account_flow || {};

  let status = "dry_run_ready";
  let lastError = "";
  if (verificationFlow.attempted && !verificationFlow.completed) {
    status = "verification_required";
    lastError = verificationFlow.timed_out
      ? "Workday asked for an email verification code, but the worker did not receive a code before the active browser session timed out."
      : verificationFlow.last_error || "Workday still requires email verification before the application can continue.";
  } else if (accountFlow.requires_consent) {
    status = "verification_required";
    lastError = accountFlow.last_error;
  } else if (accountFlow.account_action_blocked) {
    status = "review_required";
    lastError = accountFlow.last_error;
  } else if (accountFlow.verification_required && !verificationFlow.completed) {
    status = "verification_required";
    lastError = accountFlow.last_error;
  } else if (!flow.form_ready) {
    status = "review_required";
    lastError = flow.opened_apply
      ? "The worker opened the Workday application, but the current step did not finish rendering usable fields before the timeout."
      : preflight.account_required
      ? accountFlow.last_error || "Workday requires a tenant-specific candidate account before the application form is available."
      : "The worker could not reach the visible Workday application form.";
  } else if (formCompletion.missing_required_fields.length > 0) {
    status = "review_required";
    lastError =
      "The Workday application still has required fields that need review: " +
      formCompletion.missing_required_fields.slice(0, 10).join("; ");
  } else if (workdayStopAfterStage) {
    status = "dry_run_ready";
    lastError = "";
  } else if (allowFinalSubmit) {
    const submitResult = await clickLikelyApplyButton(page);
    await page.screenshot({ path: screenshotPath, fullPage: true }).catch(() => {});
    const submitted = Boolean(submitResult.submission_confirmed);
    status = submitted ? "submitted" : "review_required";
    lastError = submitted
      ? ""
      : "The worker reached the Workday application flow but did not receive a clear submission confirmation.";
    return {
      provider: "workday",
      url,
      workday: { preflight, flow, final_state: await getWorkdayVisibleState(page) },
      allow_final_submit: allowFinalSubmit,
      clicked_submit: Boolean(submitResult.clicked),
      form_opened: flow.opened_apply,
      form_ready: flow.form_ready,
      uploaded_resume: advanced.uploaded_resume,
      resume_upload: advanced.resume_upload || {},
      core_fields: coreFields,
      application_answers_attempted: advanced.application_answers.attempted,
      application_answers_filled: advanced.application_answers.filled,
      application_choice_answers_attempted: advanced.application_answers.choice_attempted,
      application_choice_answers_filled: advanced.application_answers.choice_filled,
      application_field_diagnostics: advanced.application_answers.field_diagnostics || [],
      application_answer_items: advanced.application_answers.items || [],
      complete_required_fields: formCompletion.complete_required_fields,
      missing_required_fields: submitResult.missing_required_fields || formCompletion.missing_required_fields,
      page_title: await page.title().catch(() => ""),
      final_url: page.url(),
      local_screenshot_path: screenshotPath,
      submission_confirmed: submitted,
      intercepted_submit_request: submitResult.intercepted_submit_request || null,
      observed_post_requests: submitResult.observed_post_requests || [],
      validation_detected: Boolean(submitResult.validation_detected),
      validation_errors: [
        ...(submitResult.validation_errors || []),
        ...(finalStepState.validation_errors || []),
      ].filter((value, index, list) => value && list.indexOf(value) === index),
      last_error: lastError,
      status,
    };
  }

  return {
    provider: "workday",
    url,
    workday: { preflight, flow, final_state: finalState, final_step_state: finalStepState },
    browser_diagnostics: task.__sffc_browser_diagnostics || {},
    allow_final_submit: allowFinalSubmit,
    clicked_submit: false,
    form_opened: flow.opened_apply,
    form_ready: flow.form_ready,
    uploaded_resume: advanced.uploaded_resume,
    resume_upload: advanced.resume_upload || {},
    core_fields: coreFields,
    application_answers_attempted: advanced.application_answers.attempted,
    application_answers_filled: advanced.application_answers.filled,
    application_choice_answers_attempted: advanced.application_answers.choice_attempted,
    application_choice_answers_filled: advanced.application_answers.choice_filled,
    application_field_diagnostics: advanced.application_answers.field_diagnostics || [],
    application_answer_items: advanced.application_answers.items || [],
    complete_required_fields: formCompletion.complete_required_fields,
    missing_required_fields: formCompletion.missing_required_fields,
    page_title: await page.title().catch(() => ""),
    final_url: page.url(),
    local_screenshot_path: screenshotPath,
    submission_confirmed: false,
    verification_required: status === "verification_required",
    validation_detected: formCompletion.missing_required_fields.length > 0 || finalStepState.validation_errors.length > 0,
    validation_errors: finalStepState.validation_errors || [],
    last_error: lastError,
    status,
  };
}

function answerByPatterns(answers, patterns) {
  const entries = Object.entries(answers || {});
  for (const [key, value] of entries) {
    const haystack = cleanText(key).toLowerCase();
    if (patterns.some((pattern) => pattern.test(haystack)) && answerHasValue(value)) {
      return cleanText(Array.isArray(value) ? value[0] : value);
    }
  }
  return "";
}

function getCandidatePhone(task) {
  const payload = getTaskPayload(task);
  const answers = payload.application_answers || task.application_answers || {};
  return (
    cleanText(task?.candidate_phone || "") ||
    answerByPatterns(answers, [/\bphone\b/, /\bmobile\b/, /telephone/])
  );
}

function getCandidateAddress(task) {
  const payload = getTaskPayload(task);
  const answers = payload.application_answers || task.application_answers || {};
  return answerByPatterns(answers, [
    /\baddress\b/,
    /current location/,
    /\blocation\b/,
    /\bcity\b/,
  ]);
}

function normalizePersonNameCase(value) {
  const cleaned = cleanText(value);
  if (!cleaned) {
    return "";
  }
  return cleaned
    .split(/\s+/)
    .map((part) =>
      part
        .split("-")
        .map((piece) => (piece ? piece.charAt(0).toUpperCase() + piece.slice(1).toLowerCase() : ""))
        .join("-")
    )
    .join(" ");
}

function normalizeWorkdayAnswerForLabel(label, answer) {
  const labelText = cleanText(label).toLowerCase();
  const value = cleanText(answer);
  if (!value) {
    return "";
  }
  if (/family name|last name|surname|given name|first name/.test(labelText)) {
    return normalizePersonNameCase(value);
  }
  if (/phone number/.test(labelText) && !/country phone code/.test(labelText)) {
    return value.replace(/[^\d]/g, "").replace(/^(44|33)/, "").replace(/^0+/, "");
  }
  return value;
}

function normalizePhoneForDialCode(phone, countryPhoneCode = "") {
  const raw = cleanText(phone);
  const digits = raw.replace(/[^\d]/g, "");
  if (!digits) {
    return "";
  }
  const codeDigits = (cleanText(countryPhoneCode).match(/\+?\d{1,4}/) || [""])[0].replace(/\D/g, "");
  if (codeDigits === "44") {
    const local = digits.replace(/^0044/, "").replace(/^44/, "").replace(/^0+/, "");
    return local ? `0${local}`.replace(/(\d{5})(?=\d)/, "$1 ") : "";
  }
  if (codeDigits === "33") {
    return digits.replace(/^0033/, "").replace(/^33/, "").replace(/^0+/, "");
  }
  if (codeDigits && digits.startsWith(codeDigits)) {
    return digits.slice(codeDigits.length).replace(/^0+/, "");
  }
  return digits;
}

async function waitForVerificationCode(taskUuid, ignoredCodes = []) {
  const ignored = new Set((ignoredCodes || []).map((code) => cleanText(code)).filter(Boolean));
  const startedAt = Date.now();
  while (Date.now() - startedAt < verificationWaitMs) {
    const latest = await getTaskUpdate(taskUuid).catch(() => null);
    const payload = latest && typeof latest.payload === "object" && latest.payload ? latest.payload : {};
    const code = cleanText(payload.verification_code || latest?.verification_code || "");
    if (code && !ignored.has(code)) {
      return code;
    }
    await new Promise((resolve) => setTimeout(resolve, 5000));
  }
  return "";
}

function answerHasValue(value) {
  if (Array.isArray(value)) {
    return value.some(answerHasValue);
  }
  return cleanText(value) !== "";
}

function hasAnswerForQuestion(question, answers) {
  const label = getQuestionLabel(question).toLowerCase();
  if (label && answerHasValue(answers[label])) {
    return true;
  }
  return getQuestionFieldNames(question).some((name) => {
    const lowerName = name.toLowerCase();
    return answerHasValue(answers[name]) || answerHasValue(answers[lowerName]);
  });
}

function isCoveredByCandidateData(question, candidate, hasResume, coverLetterRequested) {
  const haystack = `${getQuestionLabel(question)} ${getQuestionFieldNames(question).join(" ")}`.toLowerCase();
  if (/first[_\s-]*name|given[_\s-]*name/.test(haystack)) {
    return cleanText(candidate.firstName) !== "";
  }
  if (/last[_\s-]*name|family[_\s-]*name|surname/.test(haystack)) {
    return cleanText(candidate.lastName) !== "";
  }
  if (/\bemail\b|e-mail/.test(haystack)) {
    return cleanText(candidate.email) !== "";
  }
  if (/\bphone\b|\bmobile\b|telephone/.test(haystack)) {
    return cleanText(candidate.phone) !== "";
  }
  if (/resume|cv|curriculum/.test(haystack)) {
    return hasResume;
  }
  if (/cover[_\s-]*letter/.test(haystack)) {
    return !coverLetterRequested;
  }
  if (/linkedin/.test(haystack)) {
    return true;
  }
  return false;
}

function getMissingRequiredSchemaQuestions(task, candidate, hasResume, overrideSchema = null) {
  const questions = getSchemaQuestions(overrideSchema || getApplicationSchema(task));
  const answers = getApplicationAnswers(task);
  const coverLetterRequested = Number(task.cover_letter_requested || 0) === 1;
  return questions
    .filter((question) => questionIsRequired(question))
    .filter((question) => question?.filled !== true)
    .filter(
      (question) =>
        !isCoveredByCandidateData(question, candidate, hasResume, coverLetterRequested) &&
        !hasAnswerForQuestion(question, answers)
    )
    .map((question) => getQuestionLabel(question) || getQuestionFieldNames(question).join(", "))
    .filter(Boolean);
}

function getAnswerForQuestion(question, answers) {
  const label = getQuestionLabel(question);
  const directKeys = [
    ...getQuestionFieldNames(question),
    ...getQuestionFieldNames(question).map((name) => String(name).toLowerCase()),
    label,
    label.toLowerCase(),
  ].filter(Boolean);
  for (const key of directKeys) {
    if (answerHasValue(answers[key])) {
      return answers[key];
    }
  }

  const compact = (value) => cleanText(value).toLowerCase().replace(/[^a-z0-9]+/g, "");
  const labelKey = compact(label);
  if (!labelKey) {
    return "";
  }
  const selectedGroupOption = Object.entries(answers || {}).find(([key, value]) => {
    if (!answerHasValue(value)) {
      return false;
    }
    const keyCompact = compact(key);
    if (!/businessgroups|businessunits|opportunities|interested/i.test(keyCompact)) {
      return false;
    }
    const values = Array.isArray(value)
      ? value
      : cleanText(value).split(/\s*,\s*|\s*;\s*/).filter(Boolean);
    return values.some((entry) => compact(entry) === labelKey);
  });
  if (selectedGroupOption) {
    return "Yes";
  }
  const matched = Object.entries(answers || {}).find(([key, value]) => {
    if (!answerHasValue(value)) {
      return false;
    }
    const keyCompact = compact(key);
    if (!keyCompact) {
      return false;
    }
    if (labelKey === "country") {
      return keyCompact === "country";
    }
    if (keyCompact === "countryphonecode" && labelKey !== "countryphonecode") {
      return false;
    }
    return keyCompact.length >= 4 && labelKey.length >= 4 && (labelKey.includes(keyCompact) || keyCompact.includes(labelKey));
  });
  return matched ? matched[1] : "";
}

async function fillApplicationAnswers(page, task, overrideSchema = null) {
  const schema = getApplicationSchema(task);
  const answers = getApplicationAnswers(task);
  const isWorkable = isWorkableApplication(task);
  const isWorkday = isWorkdayApplication(task);
  const questions = overrideSchema
    ? mergeQuestionsByFieldOrLabel(getSchemaQuestions(overrideSchema), getSchemaQuestions(schema))
    : getSchemaQuestions(schema);
  if (!questions.length || !Object.keys(answers).length) {
    return { attempted: 0, filled: 0, choice_attempted: 0, choice_filled: 0, items: [] };
  }

  const fillItems = questions
    .map((question) => {
      const label = getQuestionLabel(question);
      const key = getQuestionFieldNames(question)[0] || label.toLowerCase();
      const rawAnswer = getAnswerForQuestion(question, answers) || answers[key] || answers[String(key).toLowerCase()] || answers[label.toLowerCase()];
      const answer =
        isWorkday && /phone number/i.test(label) && !/country phone code/i.test(label)
          ? normalizePhoneForDialCode(rawAnswer, answers["Country Phone Code"] || answers.country_phone_code || "")
          : isWorkday
            ? normalizeWorkdayAnswerForLabel(label, rawAnswer)
            : rawAnswer;
      const fieldTypes = getQuestionFieldTypes(question);
      const rawChoices = getQuestionChoiceLabels(question);
      const workdayPromptField =
        isWorkday &&
        /how did you hear about us|country phone code|phone device type|^country$/i.test(cleanText(label));
      const textOnlyWorkdayField =
        isWorkday &&
        !workdayPromptField &&
        fieldTypes.length > 0 &&
        fieldTypes.every((type) => /^(text|textarea|email|tel|phone|date)$/i.test(String(type || "")));
      const looksLikeChoice = workdayPromptField || (textOnlyWorkdayField ? false : questionLooksChoiceBased(question));
      const workdayTextLike =
        isWorkday &&
        !looksLikeChoice &&
        textOnlyWorkdayField;
      const choices = workdayTextLike ? [] : rawChoices;
      return {
        provider: isWorkday ? "workday" : isWorkable ? "workable" : "generic",
        label,
        fieldNames: getQuestionFieldNames(question),
        fieldTypes,
        choices,
        choiceLike: workdayTextLike ? false : looksLikeChoice,
        answer: isWorkable
          ? normalizeWorkableChoiceAnswer(label, answer, choices)
          : normalizeDateAnswerForMask(label, answer),
      };
    })
    .filter((item) => item.answer && (item.fieldNames.length || item.label));

  if (!fillItems.length) {
    return { attempted: 0, filled: 0, choice_attempted: 0, choice_filled: 0, items: [] };
  }

  if (isWorkable) {
    return fillWorkableApplicationAnswers(page, fillItems);
  }

  let filled = isWorkday ? 0 : await page.evaluate(async (items) => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const scoreText = (value) => clean(value).toLowerCase();
    const compactText = (value) => scoreText(value).replace(/[^a-z0-9]+/g, "");
    const setNativeValue = (element, value) => {
      const prototype = element instanceof HTMLTextAreaElement ? HTMLTextAreaElement.prototype : HTMLInputElement.prototype;
      const descriptor = Object.getOwnPropertyDescriptor(prototype, "value");
      element.focus();
      if (descriptor && descriptor.set) {
        descriptor.set.call(element, "");
        descriptor.set.call(element, value);
      } else {
        element.value = "";
        element.value = value;
      }
      element.dispatchEvent(new Event("input", { bubbles: true }));
      element.dispatchEvent(new Event("input", { bubbles: true }));
      element.dispatchEvent(new Event("change", { bubbles: true }));
    };
    const isFillableVisibleControl = (element) => {
      if (!element || element.disabled || element.type === "hidden") {
        return false;
      }
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
    };
    const findByName = (names) => {
      for (const name of names) {
        const selector = `[name="${CSS.escape(name)}"], #${CSS.escape(name)}`;
        const elements = Array.from(document.querySelectorAll(selector));
        for (const element of elements) {
          if (element && isFillableVisibleControl(element)) {
            return element;
          }
        }
      }
      return null;
    };
    const findByLabel = (labelText) => {
      const wanted = scoreText(labelText);
      if (!wanted) {
        return null;
      }
      const labels = Array.from(document.querySelectorAll("label"));
      for (const label of labels) {
        const text = scoreText(label.textContent || "");
        if (!text || (!text.includes(wanted) && !wanted.includes(text))) {
          continue;
        }
        const forId = label.getAttribute("for");
        if (forId) {
          const byFor = document.getElementById(forId);
          if (byFor && isFillableVisibleControl(byFor)) {
            return byFor;
          }
        }
        const nested = label.querySelector("input, textarea, select");
        if (nested && isFillableVisibleControl(nested)) {
          return nested;
        }
      }
      return null;
    };
    const findQuestionContainer = (labelText) => {
      const wanted = compactText(labelText);
      if (!wanted) {
        return null;
      }
      const candidates = Array.from(document.querySelectorAll("div, fieldset, section, li"));
      return (
        candidates
          .filter((candidate) =>
            candidate.querySelector(
              "input:not([type='hidden']), textarea, select, button, [role='combobox'], [aria-haspopup='listbox']"
            )
          )
          .sort((a, b) => clean(a.textContent || "").length - clean(b.textContent || "").length)
          .find((candidate) => {
          const text = compactText(candidate.textContent || "");
          return text && (text.includes(wanted) || wanted.includes(text));
          }) || null
      );
    };
    const selectOption = (select, answer) => {
      const wanted = scoreText(answer);
      const compactWanted = compactText(answer);
      const wantedCode = (clean(answer).match(/\+?\d{1,4}/) || [""])[0].replace(/\D/g, "");
      const isPhoneCode = /country\s*phone\s*code/i.test(select.closest("div, fieldset, section, li")?.textContent || "");
      const options = Array.from(select.options || []);
      const option = options.find((candidate) => {
        const label = scoreText(candidate.textContent || candidate.label || "");
        const value = scoreText(candidate.value || "");
        const compactLabel = compactText(candidate.textContent || candidate.label || "");
        const compactValue = compactText(candidate.value || "");
        const candidateCode = (label.match(/\+?\d{1,4}/) || [""])[0].replace(/\D/g, "");
        if (isPhoneCode && wantedCode) {
          return candidateCode === wantedCode;
        }
        return (
          label === wanted ||
          value === wanted ||
          compactLabel === compactWanted ||
          compactValue === compactWanted ||
          (compactWanted && /^\d+$/.test(compactWanted) && compactLabel && compactLabel.indexOf(compactWanted) === 0) ||
          label.includes(wanted) ||
          wanted.includes(label) ||
          (compactWanted && compactLabel && compactLabel.includes(compactWanted)) ||
          (compactWanted && compactLabel && compactWanted.includes(compactLabel))
        );
      });
      if (!option) {
        return false;
      }
      select.value = option.value;
      select.dispatchEvent(new Event("input", { bubbles: true }));
      select.dispatchEvent(new Event("change", { bubbles: true }));
      return true;
    };
    const clickCustomChoice = async (container, answer, field) => {
      if (!container) {
        return false;
      }
      const wanted = scoreText(answer);
      const compactWanted = compactText(answer);
      const labelText = scoreText(target.label || "");
      const isSourceQuestion = /how did you hear about us|source/i.test(labelText);
      const isPhoneCode = /country phone code/i.test(labelText);
      const wantedCode = (answer.match(/\+?\d{1,4}/) || [""])[0].replace(/\D/g, "");
      const opener = field || container.querySelector(
        "button, [role='combobox'], [aria-haspopup='listbox'], [data-testid*='select' i]"
      );
      if (opener) {
        opener.scrollIntoView({ block: "center" });
        opener.click();
        if (opener.matches("input, textarea")) {
          setNativeValue(opener, answer);
          opener.dispatchEvent(new KeyboardEvent("keydown", { key: "ArrowDown", bubbles: true }));
          opener.dispatchEvent(new KeyboardEvent("keyup", { key: "ArrowDown", bubbles: true }));
        }
        await new Promise((resolve) => setTimeout(resolve, 250));
      }
      const optionSelectors = [
        "[role='option']",
        "li",
        "button",
        "[data-testid*='option' i]",
      ];
      const options = Array.from(document.querySelectorAll(optionSelectors.join(",")));
      const rankedOptions = options
        .map((candidate) => {
        const text = scoreText(candidate.textContent || candidate.getAttribute("aria-label") || "");
        const compact = compactText(candidate.textContent || candidate.getAttribute("aria-label") || "");
          const candidateCode = (text.match(/\+?\d{1,4}/) || [""])[0].replace(/\D/g, "");
          let score = 0;
          if (isPhoneCode && wantedCode) {
            score = candidateCode === wantedCode ? 120 : 0;
          } else if (text === wanted || compact === compactWanted) {
            score = 100;
          } else if (compactWanted && compact && compact.includes(compactWanted)) {
            score = 86;
          } else if (compactWanted && compact && compactWanted.includes(compact)) {
            score = 82;
          } else if (isSourceQuestion && !/select one|choose|please select/i.test(text)) {
            score = /website|career|company|blackstone/i.test(text) ? 75 : 25;
          }
          return { candidate, text, score };
        })
        .filter((entry) => entry.score > 0)
        .sort((a, b) => b.score - a.score);
      const option = rankedOptions[0]?.candidate || null;
      if (!option) {
        if (opener) {
          opener.dispatchEvent(new KeyboardEvent("keydown", { key: "Enter", bubbles: true }));
          opener.dispatchEvent(new KeyboardEvent("keyup", { key: "Enter", bubbles: true }));
          await new Promise((resolve) => setTimeout(resolve, 150));
          const selectedText = scoreText(container.textContent || "");
          return selectedText.includes(wanted) || compactText(selectedText).includes(compactWanted);
        }
        return false;
      }
      option.click();
      return true;
    };
    let filledCount = 0;
    for (const item of items) {
      const answer = clean(item.answer);
      if (item.choiceLike) {
        continue;
      }
      const container = findQuestionContainer(item.label);
      const field =
        findByName(item.fieldNames) ||
        findByLabel(item.label) ||
        (container ? container.querySelector("input:not([type='hidden']), textarea, select") : null);
      if (!field || !answer) {
        if (answer && await clickCustomChoice(container, answer, field)) {
          filledCount += 1;
        }
        continue;
      }
      const existing = clean(field.value || "");
      if (existing && existing.toLowerCase() === answer.toLowerCase()) {
        filledCount += 1;
        continue;
      }
      if (
        field.getAttribute("role") === "combobox" ||
        field.getAttribute("aria-haspopup") === "true" ||
        field.getAttribute("aria-autocomplete") === "list"
      ) {
        if (await clickCustomChoice(container || field.closest("div, fieldset, section, li"), answer, field)) {
          filledCount += 1;
        }
        continue;
      }
      if (field.tagName === "SELECT") {
        if (selectOption(field, answer)) {
          filledCount += 1;
        } else if (await clickCustomChoice(container, answer, field)) {
          filledCount += 1;
        }
        continue;
      }
      if (field.type === "checkbox" || field.type === "radio") {
        const group = field.name
          ? Array.from(document.querySelectorAll(`[name="${CSS.escape(field.name)}"]`))
          : [field];
        const wanted = scoreText(answer);
        const compactWanted = compactText(answer);
        const option = group.find((candidate) => {
          const id = candidate.getAttribute("id") || "";
          const label = id ? document.querySelector(`label[for="${CSS.escape(id)}"]`) : null;
          const optionText = scoreText(
            (label && label.textContent) ||
              candidate.getAttribute("aria-label") ||
              candidate.value ||
              ""
          );
          const compactOption = compactText(optionText);
          return (
            optionText === wanted ||
            compactOption === compactWanted ||
            (compactWanted && /^\d+$/.test(compactWanted) && compactOption && compactOption.indexOf(compactWanted) === 0) ||
            optionText.includes(wanted) ||
            wanted.includes(optionText) ||
            (compactWanted && compactOption && compactOption.includes(compactWanted)) ||
            (compactWanted && compactOption && compactWanted.includes(compactOption))
          );
        });
        if (option) {
          option.click();
          filledCount += 1;
        } else if (await clickCustomChoice(container, answer, field)) {
          filledCount += 1;
        }
        continue;
      }
      setNativeValue(field, answer);
      filledCount += 1;
    }
    return filledCount;
  }, fillItems);

  const choiceFilled = await fillInteractiveChoiceAnswers(page, fillItems);
  filled += choiceFilled;
  await page.keyboard.press("Escape").catch(() => {});
  const fieldDiagnostics = await inspectApplicationFieldState(page, fillItems).catch(() => []);

  return {
    attempted: fillItems.length,
    filled,
    choice_attempted: fillItems.filter((item) => item.choiceLike).length,
    choice_filled: choiceFilled,
    field_diagnostics: fieldDiagnostics,
    items: fillItems.map((item) => ({
      label: item.label,
      field_names: item.fieldNames,
      field_types: item.fieldTypes,
      choice_like: Boolean(item.choiceLike),
      choice_count: item.choices.length,
      answer_present: Boolean(item.answer),
    })),
  };
}

function scoreChoice(answer, candidate) {
  const normalize = (value) => cleanText(value).toLowerCase();
  const compact = (value) => normalize(value).replace(/[^a-z0-9]+/g, "");
  const wanted = normalize(answer);
  const option = normalize(candidate);
  const compactWanted = compact(answer);
  const compactOption = compact(candidate);

  if (!wanted || !option) {
    return 0;
  }
  if (option === wanted || compactOption === compactWanted) {
    return 100;
  }
  if (/^\d+$/.test(compactWanted) && compactOption.startsWith(compactWanted)) {
    return 92;
  }
  if (compactWanted && compactOption.includes(compactWanted)) {
    return 86;
  }
  if (compactWanted && compactWanted.includes(compactOption)) {
    return 82;
  }
  if (option.includes(wanted)) {
    return 78;
  }
  if (wanted.includes(option)) {
    return 74;
  }
  return 0;
}

function getBestChoiceLabel(answer, choices) {
  const ranked = (choices || [])
    .map((choice) => ({ choice, score: scoreChoice(answer, choice) }))
    .filter((entry) => entry.score > 0)
    .sort((a, b) => b.score - a.score);
  return ranked[0]?.choice || cleanText(answer);
}

function normalizeWorkableChoiceAnswer(label, answer, choices = []) {
  const clean = cleanText(answer);
  if (!clean) {
    return "";
  }
  const labelText = cleanText(label).toLowerCase();
  const compact = (value) => cleanText(value).toLowerCase().replace(/[^a-z0-9]+/g, "");
  const choiceLabels = Array.isArray(choices) ? choices.map(cleanText).filter(Boolean) : [];
  const exactChoice = choiceLabels.find((choice) => compact(choice) === compact(clean));
  if (exactChoice) {
    return exactChoice;
  }
  const nationalityAliases = {
    british: ["United Kingdom", "UK", "United Kingdom of Great Britain and Northern Ireland", "British"],
    english: ["United Kingdom", "UK", "United Kingdom of Great Britain and Northern Ireland", "British"],
    scottish: ["United Kingdom", "UK", "United Kingdom of Great Britain and Northern Ireland", "British"],
    welsh: ["United Kingdom", "UK", "United Kingdom of Great Britain and Northern Ireland", "British"],
    american: ["United States", "United States of America", "US", "USA", "American"],
    emirati: ["United Arab Emirates", "UAE", "Emirati"],
    saudi: ["Saudi Arabia", "Saudi Arabian", "Saudi"],
  };
  if (/nationality|citizenship|country/i.test(labelText)) {
    const aliases = nationalityAliases[compact(clean)] || [];
    const matchedChoice = choiceLabels.find((choice) =>
      aliases.some((alias) => compact(choice) === compact(alias))
    );
    if (matchedChoice) {
      return matchedChoice;
    }
    return aliases[0] || clean;
  }
  return getBestChoiceLabel(clean, choiceLabels);
}

function normalizeDateAnswerForMask(label, answer) {
  const clean = cleanText(answer);
  if (!/date of birth|birth date|\bdob\b/i.test(label || "")) {
    return clean;
  }
  const digits = clean.replace(/\D+/g, "");
  return digits.length === 8 ? digits : clean;
}

async function fillWorkableTextFieldByLabel(page, labelPatterns, value) {
  if (!cleanText(value)) {
    return false;
  }
  const handle = await page.evaluateHandle((patterns) => {
    const clean = (candidate) => String(candidate || "").replace(/\s+/g, " ").trim();
    const isVisible = (element) => {
      if (!element || element.disabled || element.type === "hidden") {
        return false;
      }
      if (element.closest("header, nav, [data-automation-id*='utility' i]")) {
        return false;
      }
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
    };
    const matches = (text) => patterns.some((pattern) => new RegExp(pattern, "i").test(text));
    const labels = Array.from(document.querySelectorAll("label"));
    for (const label of labels) {
      const text = clean(label.textContent || "");
      if (!matches(text)) {
        continue;
      }
      const forId = label.getAttribute("for");
      const byFor = forId ? document.getElementById(forId) : null;
      if (byFor && isVisible(byFor) && /^(INPUT|TEXTAREA)$/i.test(byFor.tagName || "")) {
        return byFor;
      }
      let scope = label.parentElement;
      for (let depth = 0; depth < 5 && scope; depth += 1) {
        const input = Array.from(scope.querySelectorAll("input:not([type='hidden']), textarea")).find((control) => {
          const type = control.type || "";
          return isVisible(control) && !/^(file|radio|checkbox|submit|button)$/i.test(type);
        });
        if (input) {
          return input;
        }
        scope = scope.parentElement;
      }
      const labelRect = label.getBoundingClientRect();
      const nearby = Array.from(document.querySelectorAll("input:not([type='hidden']), textarea"))
        .filter((control) => {
          const type = control.type || "";
          if (!isVisible(control) || /^(file|radio|checkbox|submit|button)$/i.test(type)) {
            return false;
          }
          const rect = control.getBoundingClientRect();
          const belowOrAligned = rect.top >= labelRect.top - 8;
          const closeVertically = Math.abs(rect.top - labelRect.bottom) < 140 || Math.abs(rect.top - labelRect.top) < 80;
          const closeHorizontally =
            rect.left >= labelRect.left - 40 && rect.left <= labelRect.right + 420;
          return belowOrAligned && closeVertically && closeHorizontally;
        })
        .sort((a, b) => {
          const aRect = a.getBoundingClientRect();
          const bRect = b.getBoundingClientRect();
          const aDistance = Math.abs(aRect.top - labelRect.bottom) + Math.abs(aRect.left - labelRect.left) / 10;
          const bDistance = Math.abs(bRect.top - labelRect.bottom) + Math.abs(bRect.left - labelRect.left) / 10;
          return aDistance - bDistance;
        })[0];
      if (nearby) {
        return nearby;
      }
    }
    return null;
  }, labelPatterns);
  const element = handle.asElement();
  if (!element) {
    await handle.dispose().catch(() => {});
    return false;
  }
  await element.evaluate((control) => control.scrollIntoView({ block: "center", inline: "nearest" })).catch(() => {});
  await element.click({ clickCount: 3 }).catch(() => {});
  const modifier = process.platform === "darwin" ? "Meta" : "Control";
  await page.keyboard.down(modifier).catch(() => {});
  await page.keyboard.press("KeyA").catch(() => {});
  await page.keyboard.up(modifier).catch(() => {});
  await page.keyboard.press("Backspace").catch(() => {});
  await page.keyboard.type(cleanText(value), { delay: 25 });
  await page.keyboard.press("Tab").catch(() => {});
  const filled = await element.evaluate((control) => String(control.value || "").trim() !== "").catch(() => false);
  await element.dispose().catch(() => {});
  return Boolean(filled);
}

async function fillWorkableCoreTextByVisualLabel(page, valuesByLabel) {
  return page.evaluate((entries) => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const isVisible = (element) => {
      if (!element || element.disabled || element.type === "hidden") {
        return false;
      }
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
    };
    const setNativeValue = (element, value) => {
      const prototype = element instanceof HTMLTextAreaElement ? HTMLTextAreaElement.prototype : HTMLInputElement.prototype;
      const descriptor = Object.getOwnPropertyDescriptor(prototype, "value");
      element.focus();
      if (descriptor && descriptor.set) {
        descriptor.set.call(element, "");
        descriptor.set.call(element, value);
      } else {
        element.value = "";
        element.value = value;
      }
      element.dispatchEvent(new Event("input", { bubbles: true }));
      element.dispatchEvent(new Event("input", { bubbles: true }));
      element.dispatchEvent(new Event("change", { bubbles: true }));
      element.dispatchEvent(new Event("blur", { bubbles: true }));
    };
    const controls = Array.from(document.querySelectorAll("input:not([type='hidden']), textarea"))
      .filter((control) => {
        const type = control.type || "";
        return isVisible(control) && !/^(file|radio|checkbox|submit|button)$/i.test(type);
      })
      .map((control) => ({ control, rect: control.getBoundingClientRect() }));
    const labels = Array.from(document.querySelectorAll("label"))
      .filter(isVisible)
      .map((label) => ({ label, text: clean(label.textContent || ""), rect: label.getBoundingClientRect() }));
    const results = {};
    for (const entry of entries) {
      const patterns = (entry.patterns || []).map((pattern) => new RegExp(pattern, "i"));
      const value = clean(entry.value || "");
      if (!value) {
        results[entry.key] = false;
        continue;
      }
      const label = labels.find((candidate) => patterns.some((pattern) => pattern.test(candidate.text)));
      if (!label) {
        results[entry.key] = false;
        continue;
      }
      const field = controls
        .filter(({ rect }) => {
          const verticallyInsideLabel = rect.top >= label.rect.top && rect.bottom <= label.rect.bottom + 8;
          const justBelowLabel = rect.top >= label.rect.top && rect.top - label.rect.bottom < 24;
          const horizontalOverlap = rect.left < label.rect.right && rect.right > label.rect.left;
          return (verticallyInsideLabel || justBelowLabel) && horizontalOverlap;
        })
        .sort((a, b) => {
          const aDistance = Math.abs(a.rect.top - label.rect.top) + Math.abs(a.rect.left - label.rect.left);
          const bDistance = Math.abs(b.rect.top - label.rect.top) + Math.abs(b.rect.left - label.rect.left);
          return aDistance - bDistance;
        })[0]?.control;
      if (!field) {
        results[entry.key] = false;
        continue;
      }
      setNativeValue(field, value);
      results[entry.key] = clean(field.value || "") !== "";
    }
    return results;
  }, valuesByLabel);
}

async function selectWorkableDropdownByVisibleLabel(page, label, answer) {
  const clickPoint = await page.evaluate(
    ({ label, answer }) => {
      const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
      const compact = (value) => clean(value).toLowerCase().replace(/[^a-z0-9]+/g, "");
      const wantedLabel = compact(label);
      const isVisible = (element) => {
        if (!element) {
          return false;
        }
        const style = window.getComputedStyle(element);
        const rect = element.getBoundingClientRect();
        return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
      };
      const scopes = Array.from(document.querySelectorAll("label, fieldset, section, li, div"))
        .filter((scope) => {
          const text = compact(scope.textContent || "");
          return (
            isVisible(scope) &&
            text &&
            wantedLabel &&
            text.includes(wantedLabel) &&
            /selectanoption/i.test(text)
          );
        })
        .sort((a, b) => clean(a.textContent || "").length - clean(b.textContent || "").length);
      const scope = scopes[0] || null;
      if (!scope) {
        return null;
      }
      const controls = Array.from(scope.querySelectorAll("button, [role='button'], [role='combobox'], input"))
        .filter(isVisible)
        .filter((control) => {
          const type = control.type || "";
          return !/^(radio|checkbox|file|hidden|submit)$/i.test(type);
        });
      const target =
        controls.find((control) => /select an option/i.test(clean(control.textContent || control.value || ""))) ||
        controls.find((control) => control.getBoundingClientRect().width > 20 && control.getBoundingClientRect().height > 20) ||
        scope;
      const rect = target.getBoundingClientRect();
      return {
        x: Math.round(rect.left + Math.min(rect.width - 16, Math.max(16, rect.width / 2))),
        y: Math.round(rect.top + Math.min(rect.height - 12, Math.max(12, rect.height / 2))),
        answer,
      };
    },
    { label, answer }
  );
  if (!clickPoint) {
    return false;
  }
  await page.mouse.click(clickPoint.x, clickPoint.y);
  await new Promise((resolve) => setTimeout(resolve, 350));
  const optionPoint = await page.evaluate((answer) => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const normalize = (value) => clean(value).toLowerCase();
    const compact = (value) => normalize(value).replace(/[^a-z0-9]+/g, "");
    const wanted = normalize(answer);
    const wantedCompact = compact(answer);
    const score = (candidate) => {
      const text = normalize(candidate);
      const textCompact = compact(candidate);
      if (!wanted || !text) return 0;
      if (text === wanted || textCompact === wantedCompact) return 100;
      if (textCompact.includes(wantedCompact)) return 86;
      if (wantedCompact.includes(textCompact)) return 82;
      if (text.includes(wanted)) return 78;
      if (wanted.includes(text)) return 74;
      return 0;
    };
    const isVisible = (element) => {
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
    };
    const option = Array.from(
      document.querySelectorAll("[role='option'], li, button, div")
    )
      .filter(isVisible)
      .map((node) => ({ node, text: clean(node.textContent || node.getAttribute("aria-label") || "") }))
      .filter((entry) => entry.text.length > 0 && entry.text.length < 120)
      .map((entry) => ({ ...entry, score: score(entry.text) }))
      .filter((entry) => entry.score > 0)
      .sort((a, b) => b.score - a.score)[0]?.node;
    if (!option) {
      return null;
    }
    const rect = option.getBoundingClientRect();
    return { x: Math.round(rect.left + rect.width / 2), y: Math.round(rect.top + rect.height / 2) };
  }, answer);
  if (!optionPoint) {
    await page.keyboard.press("Escape").catch(() => {});
    return false;
  }
  await page.mouse.click(optionPoint.x, optionPoint.y);
  await new Promise((resolve) => setTimeout(resolve, 350));
  return page.evaluate(
    ({ label, answer }) => {
      const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
      const compact = (value) => clean(value).toLowerCase().replace(/[^a-z0-9]+/g, "");
      const wantedLabel = compact(label);
      const wantedAnswer = compact(answer);
      return Array.from(document.querySelectorAll("label, fieldset, section, li, div")).some((scope) => {
        const text = compact(scope.textContent || "");
        return text && text.includes(wantedLabel) && text.includes(wantedAnswer) && !/selectanoption/i.test(text);
      });
    },
    { label, answer }
  ).catch(() => true);
}

async function selectWorkableDropdownByFieldName(page, fieldName, answer, label = "", choices = []) {
  if (!cleanText(fieldName) || !cleanText(answer)) {
    return false;
  }
  const answerCandidates = Array.from(new Set([
    cleanText(answer),
    normalizeWorkableChoiceAnswer(label || fieldName, answer, choices),
  ].filter(Boolean)));
  const clickVisibleDropdownOption = async () => {
    const optionPoint = await page.evaluate((answers) => {
      const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
      const normalize = (value) => clean(value).toLowerCase();
      const compact = (value) => normalize(value).replace(/[^a-z0-9]+/g, "");
      const wanted = answers.map((answer) => ({
        raw: clean(answer),
        normal: normalize(answer),
        compact: compact(answer),
      })).filter((answer) => answer.raw);
      const score = (candidate) => {
        const text = normalize(candidate);
        const textCompact = compact(candidate);
        if (!text || !wanted.length) return 0;
        for (const answer of wanted) {
          if (text === answer.normal || textCompact === answer.compact) return 100;
        }
        for (const answer of wanted) {
          if (/^\d+$/.test(answer.compact) && textCompact.startsWith(answer.compact)) return 92;
        }
        for (const answer of wanted) {
          if (answer.compact.length >= 4 && textCompact.includes(answer.compact)) return 84;
        }
        return 0;
      };
      const isVisible = (element) => {
        const style = window.getComputedStyle(element);
        const rect = element.getBoundingClientRect();
        return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
      };
      const option = Array.from(document.querySelectorAll("[role='option'], li, button, div"))
        .filter(isVisible)
        .map((node) => ({ node, text: clean(node.textContent || node.getAttribute("aria-label") || "") }))
        .filter((entry) => entry.text.length > 0 && entry.text.length < 120)
        .map((entry) => ({ ...entry, score: score(entry.text) }))
        .filter((entry) => entry.score > 0)
        .sort((a, b) => b.score - a.score)[0]?.node;
      if (!option) {
        return null;
      }
      const rect = option.getBoundingClientRect();
      return { x: Math.round(rect.left + rect.width / 2), y: Math.round(rect.top + rect.height / 2) };
    }, answerCandidates).catch(() => null);
    if (!optionPoint) {
      return false;
    }
    await page.mouse.click(optionPoint.x, optionPoint.y);
    await new Promise((resolve) => setTimeout(resolve, 500));
    return true;
  };
  const hasSelectedValue = async () => page.evaluate(
    ({ name, visibleId, answers }) => {
      const hidden = document.querySelector(`[name="${CSS.escape(name)}"]`);
      const visible = document.getElementById(visibleId);
      const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
      const compact = (value) => clean(value).toLowerCase().replace(/[^a-z0-9]+/g, "");
      const wanted = (answers || []).map(compact).filter(Boolean);
      const visibleValue = clean(visible && visible.value);
      const visibleOk =
        visibleValue &&
        !/select an option/i.test(visibleValue) &&
        (!wanted.length || wanted.some((answer) => compact(visibleValue) === answer));
      return Boolean(
        (hidden && String(hidden.value || "").trim() && visibleOk) ||
          visibleOk
      );
    },
    { name: fieldName, visibleId: `input_${fieldName}_input`, answers: answerCandidates }
  ).catch(() => false);

  const visibleComboboxSelector = cssIdSelector(`input_${fieldName}_input`);
  const combobox = await page.$(visibleComboboxSelector).catch(() => null);
  if (combobox) {
    const visible = await combobox.evaluate((input) => {
      const style = window.getComputedStyle(input);
      const rect = input.getBoundingClientRect();
      return (
        !input.disabled &&
        style.visibility !== "hidden" &&
        style.display !== "none" &&
        rect.width > 40 &&
        rect.height > 20
      );
    }).catch(() => false);
    if (visible) {
      await combobox.evaluate((input) => input.scrollIntoView({ block: "center", inline: "nearest" })).catch(() => {});
      await combobox.click().catch(() => {});
      await new Promise((resolve) => setTimeout(resolve, 500));
      if ((await clickVisibleDropdownOption()) && (await hasSelectedValue())) {
        await combobox.dispose().catch(() => {});
        return true;
      }
      const searchHandle = await page.evaluateHandle(() => {
        const search = Array.from(document.querySelectorAll("input"))
          .filter((input) => {
            const style = window.getComputedStyle(input);
            const rect = input.getBoundingClientRect();
            return (
              !input.disabled &&
              style.visibility !== "hidden" &&
              style.display !== "none" &&
              rect.width > 80 &&
              rect.height > 20 &&
              /search/i.test(`${input.placeholder || ""} ${input.getAttribute("aria-label") || ""}`)
            );
          })
          .sort((a, b) => b.getBoundingClientRect().top - a.getBoundingClientRect().top)[0];
        return search || null;
      }).catch(() => null);
      const searchElement = searchHandle && searchHandle.asElement ? searchHandle.asElement() : null;
      if (searchElement) {
        await searchElement.click({ clickCount: 3 }).catch(() => {});
        const modifier = process.platform === "darwin" ? "Meta" : "Control";
        await page.keyboard.down(modifier).catch(() => {});
        await page.keyboard.press("KeyA").catch(() => {});
        await page.keyboard.up(modifier).catch(() => {});
        await page.keyboard.press("Backspace").catch(() => {});
        await page.keyboard.type(answerCandidates[0], { delay: 30 }).catch(() => {});
        await new Promise((resolve) => setTimeout(resolve, 650));
        if ((await clickVisibleDropdownOption()) && (await hasSelectedValue())) {
          await searchElement.dispose().catch(() => {});
          await combobox.dispose().catch(() => {});
          return true;
        }
        await searchElement.dispose().catch(() => {});
      } else if (searchHandle && searchHandle.dispose) {
        await searchHandle.dispose().catch(() => {});
      }
      await combobox.click({ clickCount: 3 }).catch(() => {});
      const modifier = process.platform === "darwin" ? "Meta" : "Control";
      await page.keyboard.down(modifier).catch(() => {});
      await page.keyboard.press("KeyA").catch(() => {});
      await page.keyboard.up(modifier).catch(() => {});
      await page.keyboard.press("Backspace").catch(() => {});
      await page.keyboard.type(answerCandidates[0], { delay: 25 }).catch(() => {});
      await new Promise((resolve) => setTimeout(resolve, 450));
      await page.keyboard.press("Enter").catch(() => {});
      await new Promise((resolve) => setTimeout(resolve, 450));
      if (await hasSelectedValue()) {
        await combobox.dispose().catch(() => {});
        return true;
      }
      await page.keyboard.press("ArrowDown").catch(() => {});
      await page.keyboard.press("Enter").catch(() => {});
      await new Promise((resolve) => setTimeout(resolve, 450));
      if (await hasSelectedValue()) {
        await combobox.dispose().catch(() => {});
        return true;
      }
      if ((await clickVisibleDropdownOption()) && (await hasSelectedValue())) {
        await combobox.dispose().catch(() => {});
        return true;
      }
    }
    await combobox.dispose().catch(() => {});
  }

  const clickPoint = await page.evaluate((name) => {
    const isVisible = (element) => {
      if (!element) {
        return false;
      }
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
    };
    const input = document.querySelector(`[name="${CSS.escape(name)}"]`);
    if (!input || !isVisible(input)) {
      return null;
    }
    const rect = input.getBoundingClientRect();
    let scope = input.closest("label, fieldset, section, li, div") || input.parentElement;
    for (let depth = 0; depth < 5 && scope; depth += 1) {
      const scopeRect = scope.getBoundingClientRect();
      if (scopeRect.width > 120 && scopeRect.height > 28) {
        const clickable = Array.from(scope.querySelectorAll("button, [role='button'], [role='combobox'], div"))
          .filter(isVisible)
          .filter((candidate) => {
            const candidateRect = candidate.getBoundingClientRect();
            return (
              candidateRect.width > 120 &&
              candidateRect.height > 24 &&
              Math.abs(candidateRect.top - rect.top) < 80
            );
          })
          .sort((a, b) => {
            const aRect = a.getBoundingClientRect();
            const bRect = b.getBoundingClientRect();
            return Math.abs(aRect.top - rect.top) - Math.abs(bRect.top - rect.top);
          })[0];
        const targetRect = clickable ? clickable.getBoundingClientRect() : scopeRect;
        return {
          x: Math.round(targetRect.left + targetRect.width / 2),
          y: Math.round(targetRect.top + Math.min(Math.max(targetRect.height / 2, 14), targetRect.height - 8)),
        };
      }
      scope = scope.parentElement;
    }
    return { x: Math.round(rect.left + 320), y: Math.round(rect.top + 20) };
  }, fieldName).catch(() => null);
  if (!clickPoint) {
    return false;
  }
  await page.mouse.click(clickPoint.x, clickPoint.y);
  await new Promise((resolve) => setTimeout(resolve, 450));
  const optionPoint = await page.evaluate((answers) => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const normalize = (value) => clean(value).toLowerCase();
    const compact = (value) => normalize(value).replace(/[^a-z0-9]+/g, "");
    const wanted = answers.map((answer) => ({
      raw: clean(answer),
      normal: normalize(answer),
      compact: compact(answer),
    })).filter((answer) => answer.raw);
    const score = (candidate) => {
      const text = normalize(candidate);
      const textCompact = compact(candidate);
      if (!text || !wanted.length) return 0;
      for (const answer of wanted) {
        if (text === answer.normal || textCompact === answer.compact) return 100;
      }
      for (const answer of wanted) {
        if (/^\d+$/.test(answer.compact) && textCompact.startsWith(answer.compact)) return 92;
      }
      for (const answer of wanted) {
        if (answer.compact.length >= 4 && textCompact.includes(answer.compact)) return 84;
      }
      return 0;
    };
    const isVisible = (element) => {
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
    };
    const option = Array.from(document.querySelectorAll("[role='option'], li, button, div"))
      .filter(isVisible)
      .map((node) => ({ node, text: clean(node.textContent || node.getAttribute("aria-label") || "") }))
      .filter((entry) => entry.text.length > 0 && entry.text.length < 120)
      .map((entry) => ({ ...entry, score: score(entry.text) }))
      .filter((entry) => entry.score > 0)
      .sort((a, b) => b.score - a.score)[0]?.node;
    if (!option) {
      return null;
    }
    const rect = option.getBoundingClientRect();
    return { x: Math.round(rect.left + rect.width / 2), y: Math.round(rect.top + rect.height / 2) };
  }, answerCandidates).catch(() => null);
  if (!optionPoint) {
    await page.keyboard.press("Escape").catch(() => {});
    return false;
  }
  await page.mouse.click(optionPoint.x, optionPoint.y);
  await new Promise((resolve) => setTimeout(resolve, 450));
  return page.evaluate(
    ({ name }) => {
      const input = document.querySelector(`[name="${CSS.escape(name)}"]`);
      return Boolean(input && String(input.value || "").trim());
    },
    { name: fieldName }
  ).catch(() => true);
}

async function fillWorkableDateByFieldName(page, fieldName, answer) {
  if (!cleanText(fieldName) || !cleanText(answer)) {
    return false;
  }
  const normalized = (() => {
    const clean = cleanText(answer);
    const digits = clean.replace(/\D+/g, "");
    if (digits.length === 8) {
      return `${digits.slice(0, 2)}/${digits.slice(2, 4)}/${digits.slice(4)}`;
    }
    return clean;
  })();
  const direct = await page.evaluate(
    ({ fieldName, value }) => {
      const input = document.querySelector(`[name="${CSS.escape(fieldName)}"]`);
      if (!input) {
        return false;
      }
      const descriptor = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, "value");
      input.scrollIntoView({ block: "center", inline: "nearest" });
      input.focus();
      if (descriptor && descriptor.set) {
        descriptor.set.call(input, value);
      } else {
        input.value = value;
      }
      input.dispatchEvent(new Event("input", { bubbles: true }));
      input.dispatchEvent(new Event("change", { bubbles: true }));
      input.dispatchEvent(new Event("blur", { bubbles: true }));
      return Boolean(String(input.value || "").trim() && !input.matches(":invalid"));
    },
    { fieldName, value: normalized }
  ).catch(() => false);
  await page.keyboard.press("Escape").catch(() => {});
  if (direct) {
    return true;
  }

  const handle = await page.$(`[name="${fieldName.replace(/\\/g, "\\\\").replace(/"/g, '\\"')}"]`).catch(() => null);
  if (!handle) {
    return false;
  }
  const digits = normalized.replace(/\D+/g, "");
  const text = digits.length === 8 ? digits : normalized;
  await handle.evaluate((input) => input.scrollIntoView({ block: "center", inline: "nearest" })).catch(() => {});
  await handle.click({ clickCount: 3 }).catch(() => {});
  const modifier = process.platform === "darwin" ? "Meta" : "Control";
  await page.keyboard.down(modifier).catch(() => {});
  await page.keyboard.press("KeyA").catch(() => {});
  await page.keyboard.up(modifier).catch(() => {});
  await page.keyboard.press("Backspace").catch(() => {});
  await page.keyboard.type(text, { delay: 35 }).catch(() => {});
  await page.keyboard.press("Tab").catch(() => {});
  await new Promise((resolve) => setTimeout(resolve, 350));
  const ok = await handle.evaluate((input) => {
    const value = String(input.value || "").trim();
    return Boolean(value && !input.matches(":invalid"));
  }).catch(() => false);
  await handle.dispose().catch(() => {});
  return Boolean(ok);
}

async function fillWorkableTextByFieldName(page, fieldName, answer) {
  if (!cleanText(fieldName) || !cleanText(answer)) {
    return false;
  }
  const selector = `[name="${String(fieldName).replace(/\\/g, "\\\\").replace(/"/g, '\\"')}"]`;
  const handle = await page.$(selector).catch(() => null);
  if (!handle) {
    return false;
  }
  const fillable = await handle.evaluate((input) => {
    if (!input || input.disabled || input.type === "hidden") {
      return false;
    }
    const style = window.getComputedStyle(input);
    const rect = input.getBoundingClientRect();
    const tag = input.tagName || "";
    const type = input.type || "";
    return (
      /^(INPUT|TEXTAREA)$/i.test(tag) &&
      !/^(file|radio|checkbox|submit|button)$/i.test(type) &&
      style.visibility !== "hidden" &&
      style.display !== "none" &&
      rect.width > 2 &&
      rect.height > 2
    );
  }).catch(() => false);
  if (!fillable) {
    await handle.dispose().catch(() => {});
    return false;
  }
  await handle.evaluate((input) => input.scrollIntoView({ block: "center", inline: "nearest" })).catch(() => {});
  await handle.click({ clickCount: 3 }).catch(() => {});
  const modifier = process.platform === "darwin" ? "Meta" : "Control";
  await page.keyboard.down(modifier).catch(() => {});
  await page.keyboard.press("KeyA").catch(() => {});
  await page.keyboard.up(modifier).catch(() => {});
  await page.keyboard.press("Backspace").catch(() => {});
  await page.keyboard.type(cleanText(answer), { delay: 20 }).catch(() => {});
  await page.keyboard.press("Tab").catch(() => {});
  await new Promise((resolve) => setTimeout(resolve, 250));
  const ok = await handle.evaluate((input, expected) => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    return Boolean(clean(input.value) || clean(expected));
  }, cleanText(answer)).catch(() => false);
  await handle.dispose().catch(() => {});
  return Boolean(ok);
}

async function scrollWorkableQuestionIntoView(page, item) {
  const label = cleanText(item?.label || "");
  const fieldNames = Array.isArray(item?.fieldNames) ? item.fieldNames.map(cleanText).filter(Boolean) : [];
  if (!label && !fieldNames.length) {
    return false;
  }
  return page.evaluate(
    ({ label, fieldNames }) => {
      const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
      const compact = (value) => clean(value).toLowerCase().replace(/[^a-z0-9]+/g, "");
      const wantedLabel = compact(label);
      const names = fieldNames.map(compact).filter(Boolean);
      const visible = (node) => {
        if (!node) return false;
        const style = window.getComputedStyle(node);
        const rect = node.getBoundingClientRect();
        return style.display !== "none" && style.visibility !== "hidden" && rect.width > 0 && rect.height > 0;
      };
      const nameTarget = fieldNames
        .map((name) => document.querySelector(`[name="${CSS.escape(name)}"], #${CSS.escape(name)}`))
        .find(Boolean);
      if (nameTarget) {
        const scope = nameTarget.closest("fieldset, section, li, div") || nameTarget;
        scope.scrollIntoView({ block: "center", inline: "nearest" });
        return true;
      }
      const scopes = Array.from(document.querySelectorAll("fieldset, section, li, div, label"))
        .filter((scope) => {
          const text = compact(scope.textContent || "");
          if (!text) return false;
          if (wantedLabel && (text.includes(wantedLabel) || wantedLabel.includes(text))) return true;
          return names.some((name) => text.includes(name));
        })
        .sort((a, b) => clean(a.textContent || "").length - clean(b.textContent || "").length);
      const target = scopes.find(visible) || scopes[0] || null;
      if (!target) {
        return false;
      }
      target.scrollIntoView({ block: "center", inline: "nearest" });
      return true;
    },
    { label, fieldNames }
  ).catch(() => false);
}

async function selectWorkableChoiceByVisibleBlock(page, fieldName, answer, choices = []) {
  if (!cleanText(fieldName) || !cleanText(answer)) {
    return false;
  }

  const choicePoint = await page.evaluate(
    ({ fieldName, answer, choices }) => {
      const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
      const normalize = (value) => clean(value).toLowerCase();
      const compact = (value) => normalize(value).replace(/[^a-z0-9]+/g, "");
      const cssEscape = (value) =>
        window.CSS && CSS.escape ? CSS.escape(value) : String(value).replace(/"/g, '\\"');
      const score = (candidate) => {
        const wanted = normalize(answer);
        const option = normalize(candidate);
        const wantedCompact = compact(answer);
        const optionCompact = compact(candidate);
        if (!wanted || !option) return 0;
        if (option === wanted || optionCompact === wantedCompact) return 100;
        if (/^\d+$/.test(wantedCompact) && optionCompact.startsWith(wantedCompact)) return 92;
        if (wantedCompact.length >= 4 && optionCompact.includes(wantedCompact)) return 86;
        if (optionCompact.length >= 4 && wantedCompact.includes(optionCompact)) return 82;
        return 0;
      };
      const isVisible = (element) => {
        if (!element) return false;
        const style = window.getComputedStyle(element);
        const rect = element.getBoundingClientRect();
        return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
      };
      const controls = Array.from(document.querySelectorAll(`[name="${cssEscape(fieldName)}"]`))
        .filter((control) => /^(radio|checkbox)$/i.test(control.type || "") && !control.disabled);
      if (!controls.length) {
        return null;
      }

      const exactChoices = Array.isArray(choices) ? choices.map(clean).filter(Boolean) : [];
      let targetControl = null;
      if (exactChoices.length === controls.length) {
        const choiceIndex = exactChoices
          .map((choice, index) => ({ index, score: score(choice) }))
          .filter((entry) => entry.score >= 90)
          .sort((a, b) => b.score - a.score)[0]?.index;
        if (choiceIndex !== undefined) {
          targetControl = controls[choiceIndex] || null;
        }
      }

      const optionText = (control) => {
        const id = control.getAttribute("id") || "";
        const explicit = id ? document.querySelector(`label[for="${cssEscape(id)}"]`) : null;
        const wrapper =
          (id && document.getElementById(`wrapper_${id}`)) ||
          explicit ||
          control.closest("label") ||
          control.closest("[role='radio']") ||
          control.closest("[role='checkbox']") ||
          control.parentElement;
        return clean(
          `${explicit?.textContent || ""} ${wrapper?.textContent || ""} ${control.getAttribute("aria-label") || ""} ${
            control.value || ""
          }`
        );
      };

      if (!targetControl) {
        targetControl = controls
          .map((control) => ({ control, score: score(optionText(control)) }))
          .filter((entry) => entry.score > 0)
          .sort((a, b) => b.score - a.score)[0]?.control || null;
      }

      if (!targetControl) {
        if (controls.length === 1 && /^(yes|y|true|agree|agreed|consent|accepted|checked|1)$/i.test(clean(answer))) {
          targetControl = controls[0];
        } else {
          return null;
        }
      }

      const id = targetControl.getAttribute("id") || "";
      const explicit = id ? document.querySelector(`label[for="${cssEscape(id)}"]`) : null;
      const target =
        (id && document.getElementById(`wrapper_${id}`)) ||
        explicit ||
        targetControl.closest("label") ||
        targetControl.closest("[role='radio']") ||
        targetControl.closest("[role='checkbox']") ||
        targetControl.parentElement ||
        targetControl;
      const rectTarget = isVisible(target) ? target : targetControl;
      const rect = rectTarget.getBoundingClientRect();
      if (!rect.width || !rect.height) {
        return null;
      }
      return {
        x: Math.round(rect.left + Math.min(Math.max(rect.width / 2, 12), rect.width - 6)),
        y: Math.round(rect.top + Math.min(Math.max(rect.height / 2, 12), rect.height - 6)),
      };
    },
    { fieldName, answer: cleanText(answer), choices: (choices || []).map(cleanText).filter(Boolean) }
  ).catch(() => null);

  if (!choicePoint) {
    return false;
  }

  await page.mouse.move(choicePoint.x, choicePoint.y, { steps: 8 }).catch(() => {});
  await page.mouse.click(choicePoint.x, choicePoint.y, { delay: 80 }).catch(() => {});
  await new Promise((resolve) => setTimeout(resolve, 350));

  return page.evaluate((fieldName) => {
    const controls = Array.from(document.querySelectorAll(`[name="${CSS.escape(fieldName)}"]`))
      .filter((control) => /^(radio|checkbox)$/i.test(control.type || ""));
    if (!controls.length) {
      return false;
    }
    if (controls.length === 1 && controls[0].type === "checkbox") {
      return Boolean(controls[0].checked || !controls[0].required);
    }
    return controls.some((control) => control.checked);
  }, fieldName).catch(() => false);
}

async function inspectWorkableNamedChoice(page, fieldName, choices = []) {
  if (!cleanText(fieldName)) {
    return null;
  }
  return page.evaluate(
    ({ fieldName, choices }) => {
      const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
      const cssEscape = (value) =>
        window.CSS && CSS.escape ? CSS.escape(value) : String(value).replace(/"/g, '\\"');
      const controls = Array.from(document.querySelectorAll(`[name="${cssEscape(fieldName)}"]`));
      return {
        field_name: fieldName,
        schema_choices: choices,
        control_count: controls.length,
        controls: controls.map((control) => {
          const rect = control.getBoundingClientRect();
          const id = control.getAttribute("id") || "";
          const explicit = id ? document.querySelector(`label[for="${cssEscape(id)}"]`) : null;
          const wrapper =
            (id && document.getElementById(`wrapper_${id}`)) ||
            explicit ||
            control.closest("label") ||
            control.closest("[role='radio']") ||
            control.closest("[role='checkbox']") ||
            control.parentElement;
          const wrapperRect = wrapper ? wrapper.getBoundingClientRect() : null;
          return {
            id,
            name: control.getAttribute("name") || "",
            type: control.getAttribute("type") || "",
            checked: Boolean(control.checked),
            disabled: Boolean(control.disabled),
            value: clean(control.value || ""),
            input_rect: {
              x: Math.round(rect.left),
              y: Math.round(rect.top),
              width: Math.round(rect.width),
              height: Math.round(rect.height),
            },
            wrapper_tag: wrapper ? wrapper.tagName : "",
            wrapper_text: clean((wrapper && wrapper.textContent) || "").slice(0, 220),
            wrapper_rect: wrapperRect
              ? {
                  x: Math.round(wrapperRect.left),
                  y: Math.round(wrapperRect.top),
                  width: Math.round(wrapperRect.width),
                  height: Math.round(wrapperRect.height),
                }
              : null,
          };
        }),
      };
    },
    { fieldName, choices: (choices || []).map(cleanText).filter(Boolean) }
  ).catch(() => null);
}

async function isWorkableNamedFieldAnswered(page, fieldName) {
  if (!cleanText(fieldName)) {
    return false;
  }
  return page.evaluate((fieldName) => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const controls = Array.from(document.querySelectorAll(`[name="${CSS.escape(fieldName)}"]`));
    if (!controls.length) {
      return false;
    }
    const radiosOrCheckboxes = controls.filter((control) => /^(radio|checkbox)$/i.test(control.type || ""));
    if (radiosOrCheckboxes.length) {
      if (radiosOrCheckboxes.length === 1 && radiosOrCheckboxes[0].type === "checkbox") {
        return Boolean(radiosOrCheckboxes[0].checked || !radiosOrCheckboxes[0].required);
      }
      return radiosOrCheckboxes.some((control) => control.checked);
    }
    return controls.some((control) => {
      if (control.type === "file") {
        return Boolean(control.files && control.files.length);
      }
      return Boolean(clean(control.value || "") && !/^select/i.test(clean(control.value || "")));
    });
  }, fieldName).catch(() => false);
}

async function selectWorkableChoiceByFieldName(page, fieldName, answer, choices = []) {
  if (!cleanText(fieldName) || !cleanText(answer)) {
    return false;
  }
  const selector = `[name="${String(fieldName).replace(/\\/g, "\\\\").replace(/"/g, '\\"')}"]`;
  const handles = await page.$$(selector).catch(() => []);
  const inputHandles = [];
  for (const handle of handles) {
    const usable = await handle.evaluate((control) =>
      /^(radio|checkbox)$/i.test(control.type || "") && !control.disabled
    ).catch(() => false);
    if (usable) {
      inputHandles.push(handle);
    } else {
      await handle.dispose().catch(() => {});
    }
  }
  if (inputHandles.length) {
    const normalizedChoices = (choices || []).map(cleanText).filter(Boolean);
    const wantedIndex =
      normalizedChoices.length === inputHandles.length
        ? normalizedChoices
            .map((choice, index) => ({ index, score: scoreChoice(answer, choice) }))
            .filter((entry) => entry.score >= 90)
            .sort((a, b) => b.score - a.score)[0]?.index
        : undefined;
    const isTruthy = /^(yes|y|true|agree|agreed|consent|accepted|checked|1)$/i.test(cleanText(answer));
    const isFalsey = /^(no|n|false|0)$/i.test(cleanText(answer));
    const targetIndex =
      wantedIndex !== undefined
        ? wantedIndex
        : inputHandles.length === 1 && inputHandles[0]
          ? (isFalsey ? -1 : 0)
          : undefined;
    let clicked = false;
    if (targetIndex !== undefined && targetIndex >= 0 && inputHandles[targetIndex]) {
      await inputHandles[targetIndex].evaluate((control) => control.scrollIntoView({ block: "center", inline: "nearest" })).catch(() => {});
      await inputHandles[targetIndex].click().catch(async () => {
        // Workable can render custom radio wrappers; direct state sync below is the reliable fallback.
      });
      await inputHandles[targetIndex].evaluate((control) => {
        const descriptor = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, "checked");
        if (descriptor && descriptor.set) {
          descriptor.set.call(control, true);
        } else {
          control.checked = true;
        }
        control.dispatchEvent(new Event("input", { bubbles: true }));
        control.dispatchEvent(new Event("change", { bubbles: true }));
      }).catch(() => {});
      clicked = true;
    } else if (inputHandles.length === 1 && isTruthy) {
      await inputHandles[0].evaluate((control) => control.scrollIntoView({ block: "center", inline: "nearest" })).catch(() => {});
      await inputHandles[0].click().catch(() => {});
      await inputHandles[0].evaluate((control) => {
        const descriptor = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, "checked");
        if (descriptor && descriptor.set) {
          descriptor.set.call(control, true);
        } else {
          control.checked = true;
        }
        control.dispatchEvent(new Event("input", { bubbles: true }));
        control.dispatchEvent(new Event("change", { bubbles: true }));
      }).catch(() => {});
      clicked = true;
    } else if (inputHandles.length === 1 && isFalsey) {
      clicked = true;
    }
    await new Promise((resolve) => setTimeout(resolve, 200));
    const checked = await page.evaluate((name) => {
      const controls = Array.from(document.querySelectorAll(`[name="${CSS.escape(name)}"]`))
        .filter((control) => /^(radio|checkbox)$/i.test(control.type || ""));
      if (controls.length === 1 && controls[0].type === "checkbox") {
        return controls[0].checked || !controls[0].required;
      }
      return controls.some((control) => control.checked);
    }, fieldName).catch(() => false);
    await Promise.all(inputHandles.map((handle) => handle.dispose().catch(() => {})));
    if (clicked && checked) {
      return true;
    }
  }

  return page.evaluate(
    ({ fieldName, answer, choices }) => {
      const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
      const normalize = (value) => clean(value).toLowerCase();
      const compact = (value) => normalize(value).replace(/[^a-z0-9]+/g, "");
      const score = (candidate) => {
        const wanted = normalize(answer);
        const option = normalize(candidate);
        const wantedCompact = compact(answer);
        const optionCompact = compact(candidate);
        if (!wanted || !option) return 0;
        if (option === wanted || optionCompact === wantedCompact) return 100;
        if (/^\d+$/.test(wantedCompact) && optionCompact.startsWith(wantedCompact)) return 92;
        if (wantedCompact.length >= 4 && optionCompact.includes(wantedCompact)) return 84;
        if (option.length >= 4 && wantedCompact.includes(optionCompact)) return 80;
        return 0;
      };
      const clickControl = (control) => {
        const id = control.getAttribute("id") || "";
        const wrapper =
          (id && document.getElementById(`wrapper_${id}`)) ||
          (id && document.querySelector(`label[for="${CSS.escape(id)}"]`)) ||
          control.closest("label") ||
          control.closest("[role='radio']") ||
          control.closest("[role='checkbox']") ||
          control.parentElement ||
          control;
        wrapper.scrollIntoView({ block: "center", inline: "nearest" });
        wrapper.click();
        if (!control.checked) {
          const descriptor = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, "checked");
          if (descriptor && descriptor.set) {
            descriptor.set.call(control, true);
          } else {
            control.checked = true;
          }
        }
        control.dispatchEvent(new Event("input", { bubbles: true }));
        control.dispatchEvent(new Event("change", { bubbles: true }));
      };
      const controls = Array.from(document.querySelectorAll(`[name="${CSS.escape(fieldName)}"]`))
        .filter((control) => /^(radio|checkbox)$/i.test(control.type || "") && !control.disabled);
      if (!controls.length) {
        return false;
      }
      if (controls.length === 1 && controls[0].type === "checkbox") {
        const truthy = /^(yes|y|true|agree|agreed|consent|accepted|checked|1)$/i.test(clean(answer));
        const falsey = /^(no|n|false|0)$/i.test(clean(answer));
        if (truthy && !controls[0].checked) {
          clickControl(controls[0]);
        }
        return truthy ? Boolean(controls[0].checked) : Boolean(falsey || controls[0].checked);
      }
      if (Array.isArray(choices) && choices.length === controls.length) {
        const selectedIndex = choices
          .map((choice, index) => ({ index, score: score(choice) }))
          .filter((entry) => entry.score >= 90)
          .sort((a, b) => b.score - a.score)[0]?.index;
        if (selectedIndex !== undefined && controls[selectedIndex]) {
          clickControl(controls[selectedIndex]);
          return Boolean(controls[selectedIndex].checked);
        }
      }
      const optionText = (control) => {
        const id = control.getAttribute("id") || "";
        const explicit = id ? document.querySelector(`label[for="${CSS.escape(id)}"]`) : null;
        const wrapper =
          (id && document.getElementById(`wrapper_${id}`)) ||
          explicit ||
          control.closest("label") ||
          control.closest("[role='radio']") ||
          control.closest("[role='checkbox']") ||
          control.parentElement;
        const nearby = wrapper
          ? Array.from((wrapper.parentElement || wrapper).querySelectorAll("label, span, div"))
              .map((node) => clean(node.textContent || ""))
              .filter(Boolean)
              .join(" ")
          : "";
        return clean(`${explicit?.textContent || ""} ${wrapper?.textContent || ""} ${nearby} ${control.value || ""}`);
      };
      const selected = controls
        .map((control) => ({ control, score: score(optionText(control)) }))
        .filter((entry) => entry.score > 0)
        .sort((a, b) => b.score - a.score)[0]?.control;
      if (!selected) {
        return false;
      }
      clickControl(selected);
      return Boolean(selected.checked);
    },
    { fieldName, answer: cleanText(answer), choices: (choices || []).map(cleanText).filter(Boolean) }
  ).catch(() => false);
}

async function fillWorkableApplicationAnswers(page, fillItems) {
  let filled = 0;
  let choiceFilled = 0;
  const attemptedItems = [];

  for (const rawItem of fillItems) {
    const item = {
      ...rawItem,
      fieldNames: Array.isArray(rawItem.fieldNames) ? rawItem.fieldNames : [],
      fieldTypes: Array.isArray(rawItem.fieldTypes) ? rawItem.fieldTypes : [],
      choices: Array.isArray(rawItem.choices) ? rawItem.choices : [],
      answer: normalizeWorkableChoiceAnswer(rawItem.label, rawItem.answer, rawItem.choices || []),
    };
    if (!cleanText(item.answer)) {
      continue;
    }

    const primaryFieldName = item.fieldNames.find(Boolean) || "";
    const fieldLooksDropdown =
      item.choiceLike && item.fieldTypes.some((type) => /select|dropdown|choice/i.test(type));
    const fieldLooksDate = /date of birth|birth date|\bdob\b/i.test(item.label || "");
    let ok = false;
    let strategy = "none";

    debugLog("workable_field_start", primaryFieldName || item.label, "answer", item.answer);
    await withTimeout(scrollWorkableQuestionIntoView(page, item), 3500, false);
    await new Promise((resolve) => setTimeout(resolve, 250));

    if (fieldLooksDropdown && primaryFieldName) {
      strategy = "workable_dropdown";
      ok = await withTimeout(
        selectWorkableDropdownByFieldName(page, primaryFieldName, item.answer, item.label, item.choices),
        10000,
        false
      );
    } else if (item.choiceLike || item.choices.length) {
      strategy = "workable_choice_visible_block";
      ok = primaryFieldName
        ? await withTimeout(selectWorkableChoiceByVisibleBlock(page, primaryFieldName, item.answer, item.choices), 8000, false)
        : false;
      if (ok) {
        await page.keyboard.press("Tab").catch(() => {});
        await new Promise((resolve) => setTimeout(resolve, 250));
      }
      if (!ok && primaryFieldName) {
        ok = await isWorkableNamedFieldAnswered(page, primaryFieldName);
        if (ok) {
          strategy = "workable_choice_verified";
        }
      }
      if (!ok) {
        strategy = "workable_choice_name";
        ok = primaryFieldName
          ? await withTimeout(selectWorkableChoiceByFieldName(page, primaryFieldName, item.answer, item.choices), 8000, false)
          : false;
      }
      if (!ok && primaryFieldName) {
        ok = await isWorkableNamedFieldAnswered(page, primaryFieldName);
        if (ok) {
          strategy = "workable_choice_verified";
        }
      }
      if (!ok) {
        const element = await withTimeout(findVisibleControlForItem(page, item), 5000, null);
        if (element) {
          ok = await withTimeout(selectRadioOrCheckboxOption(page, element, item), 8000, false);
          await element.dispose().catch(() => {});
          strategy = "workable_choice_control";
        }
      }
      if (!ok && primaryFieldName) {
        ok = await isWorkableNamedFieldAnswered(page, primaryFieldName);
        if (ok) {
          strategy = "workable_choice_verified";
        }
      }
      if (!ok) {
        ok = await withTimeout(fillChoiceByVisibleQuestion(page, item), 8000, false);
        strategy = "workable_choice_visible";
      }
      if (!ok && primaryFieldName) {
        ok = await isWorkableNamedFieldAnswered(page, primaryFieldName);
        if (ok) {
          strategy = "workable_choice_verified";
        }
      }
      if (!ok && primaryFieldName) {
        const namedChoiceDiagnostics = await inspectWorkableNamedChoice(page, primaryFieldName, item.choices);
        debugLog("workable_choice_probe", JSON.stringify(namedChoiceDiagnostics || {}));
      }
    } else if (fieldLooksDate && primaryFieldName) {
      strategy = "workable_date";
      ok = await withTimeout(fillWorkableDateByFieldName(page, primaryFieldName, item.answer), 8000, false);
    } else {
      strategy = "workable_text_name";
      ok = primaryFieldName
        ? await withTimeout(fillWorkableTextByFieldName(page, primaryFieldName, item.answer), 8000, false)
        : false;
      if (!ok) {
        ok = await withTimeout(fillTextByVisibleQuestion(page, item), 8000, false);
        strategy = "workable_text_visible";
      }
    }

    if (ok) {
      filled += 1;
      if (item.choiceLike || item.choices.length) {
        choiceFilled += 1;
      }
    }

    attemptedItems.push({
      label: item.label,
      field_names: item.fieldNames,
      field_types: item.fieldTypes,
      choice_like: Boolean(item.choiceLike),
      choice_count: item.choices.length,
      answer_present: Boolean(item.answer),
      strategy,
      filled: ok,
    });
    debugLog("workable_field_done", primaryFieldName || item.label, strategy, ok ? "filled" : "not_filled");
  }

  const workableFinalDiagnostics = await inspectApplicationFieldState(page, fillItems).catch(() => []);
  return {
    attempted: fillItems.length,
    filled,
    choice_attempted: fillItems.filter((item) => item.choiceLike).length,
    choice_filled: choiceFilled,
    field_diagnostics: Array.isArray(workableFinalDiagnostics) ? workableFinalDiagnostics : attemptedItems,
    items: attemptedItems,
  };

  const result = await page.evaluate((items) => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const normalize = (value) => clean(value).toLowerCase();
    const compact = (value) => normalize(value).replace(/[^a-z0-9]+/g, "");
    const isVisible = (element) => {
      if (!element || element.disabled || element.type === "hidden") {
        return false;
      }
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
    };
    const setNativeValue = (element, value) => {
      const prototype = element instanceof HTMLTextAreaElement ? HTMLTextAreaElement.prototype : HTMLInputElement.prototype;
      const descriptor = Object.getOwnPropertyDescriptor(prototype, "value");
      element.focus();
      if (descriptor && descriptor.set) {
        descriptor.set.call(element, value);
      } else {
        element.value = value;
      }
      element.dispatchEvent(new Event("input", { bubbles: true }));
      element.dispatchEvent(new Event("change", { bubbles: true }));
      element.dispatchEvent(new Event("blur", { bubbles: true }));
    };
    const scoreChoice = (answer, candidate) => {
      const wanted = normalize(answer);
      const option = normalize(candidate);
      const wantedCompact = compact(answer);
      const optionCompact = compact(candidate);
      if (!wanted || !option) return 0;
      if (option === wanted || optionCompact === wantedCompact) return 100;
      if (/^\d+$/.test(wantedCompact) && optionCompact.startsWith(wantedCompact)) return 92;
      if (wantedCompact && optionCompact.includes(wantedCompact)) return 86;
      if (wantedCompact && wantedCompact.includes(optionCompact)) return 82;
      if (option.includes(wanted)) return 78;
      if (wanted.includes(option)) return 74;
      return 0;
    };
    const cssEscape = (value) => (window.CSS && CSS.escape ? CSS.escape(value) : String(value).replace(/"/g, '\\"'));
    const selectorsForName = (name) => {
      const escaped = cssEscape(name);
      return [
        `#${escaped}`,
        `[name="${escaped}"]`,
        `[id="${String(name).replace(/\\/g, "\\\\").replace(/"/g, '\\"')}"]`,
        `[name*="${String(name).replace(/\\/g, "\\\\").replace(/"/g, '\\"')}"]`,
        `[id*="${String(name).replace(/\\/g, "\\\\").replace(/"/g, '\\"')}"]`,
        `[data-ui*="${String(name).replace(/\\/g, "\\\\").replace(/"/g, '\\"')}"]`,
        `[data-testid*="${String(name).replace(/\\/g, "\\\\").replace(/"/g, '\\"')}"]`,
      ];
    };
    const findControlsByName = (fieldNames) => {
      const controls = [];
      for (const name of fieldNames || []) {
        for (const selector of selectorsForName(name)) {
          try {
            controls.push(...Array.from(document.querySelectorAll(selector)));
          } catch (error) {
            // Continue with other selector shapes.
          }
        }
      }
      return Array.from(new Set(controls)).filter((control) => {
        const tag = control.tagName || "";
        return /^(INPUT|TEXTAREA|SELECT|BUTTON)$/i.test(tag) || control.getAttribute("role");
      });
    };
    const getOptionText = (control) => {
      const id = control.getAttribute("id") || "";
      const explicit = id ? document.querySelector(`label[for="${cssEscape(id)}"]`) : null;
      const wrapper =
        explicit ||
        control.closest("label") ||
        control.closest("li") ||
        control.closest("[role='radio']") ||
        control.closest("[role='checkbox']") ||
        control.parentElement;
      return clean(
        `${explicit ? explicit.textContent || "" : ""} ${wrapper ? wrapper.textContent || "" : ""} ${
          control.getAttribute("aria-label") || ""
        } ${control.value || ""}`
      );
    };
    const clickControl = (control) => {
      const id = control.getAttribute("id") || "";
      const explicit = id ? document.querySelector(`label[for="${cssEscape(id)}"]`) : null;
      const target =
        explicit ||
        control.closest("label") ||
        control.closest("li") ||
        control.closest("[role='radio']") ||
        control.closest("[role='checkbox']") ||
        control;
      target.scrollIntoView({ block: "center", inline: "nearest" });
      target.click();
      control.dispatchEvent(new Event("input", { bubbles: true }));
      control.dispatchEvent(new Event("change", { bubbles: true }));
    };
    const findScopeByLabel = (label) => {
      const wanted = compact(label);
      if (!wanted) {
        return null;
      }
      return Array.from(document.querySelectorAll("fieldset, section, li, div"))
        .filter((scope) => {
          const text = compact(scope.textContent || "");
          return (
            text &&
            (text.includes(wanted) || wanted.includes(text)) &&
            scope.querySelector("input:not([type='hidden']), textarea, select")
          );
        })
        .sort((a, b) => clean(a.textContent || "").length - clean(b.textContent || "").length)[0] || null;
    };
    const selectRadioOrCheckbox = (controls, item) => {
      const answer = clean(item.answer);
      const choiceIndex = Array.isArray(item.choices)
        ? item.choices.findIndex((choice) => scoreChoice(answer, choice) >= 90)
        : -1;
      let groups = [];
      const exactNames = Array.from(new Set((item.fieldNames || []).filter(Boolean)));
      exactNames.forEach((name) => {
        groups.push(...Array.from(document.querySelectorAll(`[name="${cssEscape(name)}"]`)));
      });
      if (!groups.length) {
        const names = Array.from(new Set(controls.map((control) => control.getAttribute("name") || "").filter(Boolean)));
        names.forEach((name) => {
          groups.push(...Array.from(document.querySelectorAll(`[name="${cssEscape(name)}"]`)));
        });
      }
      if (!groups.length) {
        groups.push(...controls);
      }
      const candidates = Array.from(new Set(groups)).filter((control) => /^(radio|checkbox)$/i.test(control.type || ""));
      if (candidates.length === 1 && candidates[0].type === "checkbox") {
        if (/^(yes|y|true|agree|agreed|consent|accepted|checked|1)$/i.test(answer)) {
          if (!candidates[0].checked) {
            clickControl(candidates[0]);
          }
          return Boolean(candidates[0].checked);
        }
        return !candidates[0].required || candidates[0].checked;
      }
      if (
        choiceIndex >= 0 &&
        candidates[choiceIndex] &&
        Array.isArray(item.choices) &&
        item.choices.length === candidates.length
      ) {
        if (!candidates[choiceIndex].checked) {
          clickControl(candidates[choiceIndex]);
        }
        return Boolean(candidates[choiceIndex].checked);
      }
      const selected = candidates
        .map((control) => ({ control, score: scoreChoice(answer, getOptionText(control)) }))
        .filter((entry) => entry.score > 0)
        .sort((a, b) => b.score - a.score)[0]?.control;
      if (!selected) {
        return false;
      }
      if (!selected.checked) {
        clickControl(selected);
      }
      return Boolean(selected.checked);
    };
    const fillSelect = (select, item) => {
      const answer = clean(item.answer);
      const selected = Array.from(select.options || [])
        .map((option) => ({ option, score: scoreChoice(answer, option.textContent || option.label || option.value || "") }))
        .filter((entry) => entry.score > 0)
        .sort((a, b) => b.score - a.score)[0]?.option;
      if (!selected) {
        return false;
      }
      select.value = selected.value;
      select.dispatchEvent(new Event("input", { bubbles: true }));
      select.dispatchEvent(new Event("change", { bubbles: true }));
      select.dispatchEvent(new Event("blur", { bubbles: true }));
      return clean(select.value) !== "";
    };
    const diagnostics = [];
    let filled = 0;
    let choiceFilled = 0;
    for (const item of items) {
      const answer = clean(item.answer);
      if (!answer) {
        continue;
      }
      const namedControls = findControlsByName(item.fieldNames || []);
      const scope = namedControls[0]?.closest("fieldset, section, li, div") || findScopeByLabel(item.label);
      const scopedControls = scope
        ? Array.from(scope.querySelectorAll("input:not([type='hidden']), textarea, select"))
        : [];
      const controls = Array.from(new Set([...namedControls, ...scopedControls])).filter(isVisible);
      let ok = false;
      let strategy = "none";
      const textControl = controls.find((control) => {
        const type = control.type || "";
        return /^(INPUT|TEXTAREA)$/i.test(control.tagName || "") && !/^(file|radio|checkbox|submit|button)$/i.test(type);
      });
      const select = controls.find((control) => control.tagName === "SELECT");
      const choices = controls.filter((control) => /^(radio|checkbox)$/i.test(control.type || ""));
      const schemaLooksDropdown = item.fieldTypes.some((type) => /select|dropdown|choice/i.test(type));
      if (item.choiceLike || choices.length || select) {
        if (select) {
          ok = fillSelect(select, item);
          strategy = "select";
        }
        if (!ok && choices.length) {
          ok = selectRadioOrCheckbox(choices, item);
          strategy = "radio_checkbox";
        }
        if (!ok && textControl && !schemaLooksDropdown) {
          setNativeValue(textControl, answer);
          ok = clean(textControl.value || "") !== "";
          strategy = "choice_text";
        }
        if (ok) {
          choiceFilled += 1;
        }
      } else if (textControl) {
        setNativeValue(textControl, answer);
        ok = clean(textControl.value || "") !== "";
        strategy = "text";
      }
      if (ok) {
        filled += 1;
      }
      diagnostics.push({
        label: item.label,
        field_names: item.fieldNames || [],
        choice_like: Boolean(item.choiceLike),
        answer: answer.slice(0, 80),
        control_count: controls.length,
        strategy,
        filled: ok,
        scope_text: clean((scope && scope.textContent) || "").slice(0, 180),
      });
    }
    return { filled, choice_filled: choiceFilled, field_diagnostics: diagnostics };
  }, fillItems);
  let extraFilled = 0;
  let extraChoiceFilled = 0;
  const failedDiagnostics = Array.isArray(result?.field_diagnostics)
    ? result.field_diagnostics.filter((entry) => !entry.filled)
    : [];
  for (const item of fillItems) {
    const failed = failedDiagnostics.some((entry) =>
      (entry.label && entry.label === item.label) ||
      (Array.isArray(entry.field_names) && entry.field_names.some((name) => item.fieldNames.includes(name)))
    );
    const looksLikeWorkableDropdown =
      failed &&
      item.choiceLike &&
      item.answer &&
      item.fieldTypes.some((type) => /select|dropdown|choice/i.test(type));
    if (!looksLikeWorkableDropdown) {
      continue;
    }
    const primaryFieldName = (item.fieldNames || []).find(Boolean) || "";
    debugLog("workable_dropdown", primaryFieldName || item.label, "answer", item.answer);
    if (
      primaryFieldName &&
      (await withTimeout(
        selectWorkableDropdownByFieldName(page, primaryFieldName, item.answer, item.label, item.choices),
        7000,
        false
      ))
    ) {
      extraFilled += 1;
      extraChoiceFilled += 1;
      continue;
    }
    if (await withTimeout(selectWorkableDropdownByVisibleLabel(page, item.label, item.answer), 7000, false)) {
      extraFilled += 1;
      extraChoiceFilled += 1;
    }
  }
  const finalDiagnostics = await inspectApplicationFieldState(page, fillItems).catch(
    () => result?.field_diagnostics || []
  );

  return {
    attempted: fillItems.length,
    filled: Number(result?.filled || 0) + extraFilled,
    choice_attempted: fillItems.filter((item) => item.choiceLike).length,
    choice_filled: Number(result?.choice_filled || 0) + extraChoiceFilled,
    field_diagnostics: Array.isArray(finalDiagnostics) ? finalDiagnostics : [],
    items: fillItems.map((item) => ({
      label: item.label,
      field_names: item.fieldNames,
      field_types: item.fieldTypes,
      choice_like: Boolean(item.choiceLike),
      choice_count: item.choices.length,
      answer_present: Boolean(item.answer),
    })),
  };
}

async function withTimeout(promise, timeoutMs, fallbackValue) {
  let timeoutId;
  try {
    return await Promise.race([
      promise,
      new Promise((resolve) => {
        timeoutId = setTimeout(() => resolve(fallbackValue), timeoutMs);
      }),
    ]);
  } finally {
    if (timeoutId) {
      clearTimeout(timeoutId);
    }
  }
}

async function findVisibleControlForItem(page, item) {
  const handle = await page.evaluateHandle((target) => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const compact = (value) => clean(value).toLowerCase().replace(/[^a-z0-9]+/g, "");
    const isVisible = (element) => {
      if (!element || element.disabled || element.type === "hidden") {
        return false;
      }
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
    };
    const wanted = compact(target.label || "");
    const findByLabelScope = () => {
      if (!wanted) {
        return null;
      }
      const labelNodes = Array.from(document.querySelectorAll("label, legend, [class*='label' i]"));
      for (const label of labelNodes) {
        const labelText = compact(`${label.textContent || ""} ${label.getAttribute("aria-label") || ""}`);
        if (!labelText || (!labelText.includes(wanted) && !wanted.includes(labelText))) {
          continue;
        }
        const forId = label.getAttribute("for");
        if (forId && isVisible(document.getElementById(forId))) {
          return document.getElementById(forId);
        }
        const container = label.closest("fieldset, section, li, div") || label.parentElement;
        const nested = container
          ? Array.from(
              container.querySelectorAll(
                "select, input[type='radio'], input[type='checkbox'], input:not([type='hidden']), textarea, [role='combobox'], [aria-haspopup='listbox']"
              )
            ).find(isVisible)
          : null;
        if (nested) {
          return nested;
        }
      }
      return null;
    };
    if (target.choiceLike || /date of birth|birth date|\bdob\b/i.test(target.label || "")) {
      const labelled = findByLabelScope();
      if (labelled) {
        return labelled;
      }
    }
    for (const name of target.fieldNames || []) {
      const direct = document.getElementById(name);
      if (isVisible(direct)) {
        return direct;
      }
      const named = Array.from(document.getElementsByName(name)).find(isVisible);
      if (named) {
        return named;
      }
    }

    if (!wanted) {
      return null;
    }
    const labels = Array.from(document.querySelectorAll("label, [id], [aria-label]"));
    for (const label of labels) {
      const labelText = compact(
        `${label.textContent || ""} ${label.getAttribute("aria-label") || ""}`
      );
      if (!labelText || (!labelText.includes(wanted) && !wanted.includes(labelText))) {
        continue;
      }
      const forId = label.getAttribute("for");
      if (forId && isVisible(document.getElementById(forId))) {
        return document.getElementById(forId);
      }
      const container = label.closest("div, fieldset, section, li") || label.parentElement;
      const nested = container
        ? Array.from(
            container.querySelectorAll(
              "input:not([type='hidden']), textarea, select, [role='combobox'], [aria-haspopup='listbox']"
            )
          ).find(isVisible)
        : null;
      if (nested) {
        return nested;
      }
    }
    return null;
  }, item);

  const element = handle.asElement();
  if (!element) {
    await handle.dispose();
    return null;
  }
  return element;
}

async function selectNativeOption(element, answer, choices) {
  const selected = await element.evaluate(
    (select, payload) => {
      const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
      const normalize = (value) => clean(value).toLowerCase();
      const compact = (value) => normalize(value).replace(/[^a-z0-9]+/g, "");
      const score = (candidate) => {
        const wanted = normalize(payload.answer);
        const option = normalize(candidate);
        const compactWanted = compact(payload.answer);
        const compactOption = compact(candidate);
        if (!wanted || !option) return 0;
        if (option === wanted || compactOption === compactWanted) return 100;
        if (/^\d+$/.test(compactWanted) && compactOption.startsWith(compactWanted)) return 92;
        if (compactWanted && compactOption.includes(compactWanted)) return 86;
        if (compactWanted && compactWanted.includes(compactOption)) return 82;
        if (option.includes(wanted)) return 78;
        if (wanted.includes(option)) return 74;
        return 0;
      };
      const options = Array.from(select.options || [])
        .map((option) => ({
          option,
          text: clean(option.textContent || option.label || option.value || ""),
        }))
        .filter((entry) => entry.text);
      const ranked = options
        .map((entry) => ({ ...entry, score: score(entry.text) }))
        .filter((entry) => entry.score > 0)
        .sort((a, b) => b.score - a.score);
      const match = ranked[0];
      if (!match) {
        return false;
      }
      select.value = match.option.value;
      select.dispatchEvent(new Event("input", { bubbles: true }));
      select.dispatchEvent(new Event("change", { bubbles: true }));
      return true;
    },
    { answer: getBestChoiceLabel(answer, choices) }
  );
  return Boolean(selected);
}

async function selectInteractiveOption(page, element, item) {
  const answer = getBestChoiceLabel(item.answer, item.choices);
  const modifier = process.platform === "darwin" ? "Meta" : "Control";
  await element.evaluate((control) => control.scrollIntoView({ block: "center" })).catch(() => {});
  await element.click({ clickCount: 3 }).catch(() => {});
  await page.keyboard.down(modifier).catch(() => {});
  await page.keyboard.press("KeyA").catch(() => {});
  await page.keyboard.up(modifier).catch(() => {});
  await page.keyboard.type(answer, { delay: 15 }).catch(() => {});
  await new Promise((resolve) => setTimeout(resolve, 350));

  const option = await page.evaluateHandle((wanted) => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const normalize = (value) => clean(value).toLowerCase();
    const compact = (value) => normalize(value).replace(/[^a-z0-9]+/g, "");
    const score = (candidate) => {
      const wantedNorm = normalize(wanted);
      const optionNorm = normalize(candidate);
      const wantedCompact = compact(wanted);
      const optionCompact = compact(candidate);
      if (!wantedNorm || !optionNorm) return 0;
      if (optionNorm === wantedNorm || optionCompact === wantedCompact) return 100;
      if (/^\d+$/.test(wantedCompact) && optionCompact.startsWith(wantedCompact)) return 92;
      if (wantedCompact && optionCompact.includes(wantedCompact)) return 86;
      if (wantedCompact && wantedCompact.includes(optionCompact)) return 82;
      if (optionNorm.includes(wantedNorm)) return 78;
      if (wantedNorm.includes(optionNorm)) return 74;
      return 0;
    };
    const isVisible = (element) => {
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
    };
    const options = Array.from(document.querySelectorAll("[role='option'], [id*='-option-']"))
      .filter(isVisible)
      .map((node) => ({ node, text: clean(node.textContent || node.getAttribute("aria-label") || "") }))
      .filter((entry) => entry.text)
      .map((entry) => ({ ...entry, score: score(entry.text) }))
      .filter((entry) => entry.score > 0)
      .sort((a, b) => b.score - a.score);
    return options[0]?.node || null;
  }, answer);

  const optionElement = option.asElement();
  if (optionElement) {
    await optionElement.click().catch(() => {});
    await option.dispose();
    await new Promise((resolve) => setTimeout(resolve, 250));
    return true;
  }
  await option.dispose();

  await page.keyboard.press("ArrowDown").catch(() => {});
  await page.keyboard.press("Enter").catch(() => {});
  await new Promise((resolve) => setTimeout(resolve, 250));
  return true;
}

async function selectRadioOrCheckboxOption(page, element, item) {
  const meta = await element.evaluate((control) => ({
    type: control.type || "",
    name: control.getAttribute("name") || "",
    id: control.getAttribute("id") || "",
  }));
  if (meta.type !== "radio" && meta.type !== "checkbox") {
    return false;
  }
  const answer = getBestChoiceLabel(item.answer, item.choices || []);
  return page.evaluate(
    ({ fieldNames, currentName, currentId, answer, choices }) => {
      const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
      const normalize = (value) => clean(value).toLowerCase();
      const compact = (value) => normalize(value).replace(/[^a-z0-9]+/g, "");
      const score = (candidate) => {
        const wanted = normalize(answer);
        const option = normalize(candidate);
        const wantedCompact = compact(answer);
        const optionCompact = compact(candidate);
        if (!wanted || !option) return 0;
        if (option === wanted || optionCompact === wantedCompact) return 100;
        if (/^\d+$/.test(wantedCompact) && optionCompact.startsWith(wantedCompact)) return 92;
        if (wantedCompact && optionCompact.includes(wantedCompact)) return 86;
        if (wantedCompact && wantedCompact.includes(optionCompact)) return 82;
        if (option.includes(wanted)) return 78;
        if (wanted.includes(option)) return 74;
        return 0;
      };
      const names = Array.from(new Set([...(fieldNames || []), currentName].filter(Boolean)));
      let controls = [];
      names.forEach((name) => {
        controls.push(...Array.from(document.querySelectorAll(`[name="${CSS.escape(name)}"]`)));
      });
      if (!controls.length && currentId) {
        const byId = document.getElementById(currentId);
        if (byId) {
          controls.push(byId);
        }
      }
      controls = controls.filter((control) => control && !control.disabled);
      const getOptionText = (control) => {
        const id = control.getAttribute("id") || "";
        const explicit = id ? document.querySelector(`label[for="${CSS.escape(id)}"]`) : null;
        const wrapper =
          control.closest("label") ||
          control.closest("li") ||
          control.closest("[role='radio']") ||
          control.closest("[role='checkbox']") ||
          control.parentElement;
        return clean(
          (explicit && explicit.textContent) ||
            control.getAttribute("aria-label") ||
            control.value ||
            (wrapper && wrapper.textContent) ||
            ""
        );
      };
      const clickOption = (control) => {
        const id = control.getAttribute("id") || "";
        const explicit = id ? document.querySelector(`label[for="${CSS.escape(id)}"]`) : null;
        const wrapper =
          explicit ||
          control.closest("label") ||
          control.closest("li") ||
          control.closest("[role='radio']") ||
          control.closest("[role='checkbox']") ||
          control.parentElement ||
          control;
        wrapper.scrollIntoView({ block: "center", inline: "nearest" });
        wrapper.click();
        if (!control.checked) {
          const descriptor = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, "checked");
          if (descriptor && descriptor.set) {
            descriptor.set.call(control, true);
          } else {
            control.checked = true;
          }
        }
        control.dispatchEvent(new Event("input", { bubbles: true }));
        control.dispatchEvent(new Event("change", { bubbles: true }));
      };
      if (controls.length === 1 && controls[0].type === "checkbox") {
        const truthy = /^(yes|y|true|agree|agreed|consent|accepted|checked|1)$/i.test(clean(answer));
        const falsey = /^(no|n|false|0)$/i.test(clean(answer));
        if ((truthy && !controls[0].checked) || (falsey && controls[0].checked)) {
          clickOption(controls[0]);
        }
        return truthy || falsey || controls[0].checked;
      }
      if (controls.length > 1 && Array.isArray(choices) && choices.length === controls.length) {
        const indexedChoice = choices
          .map((choice, index) => ({ index, score: score(choice) }))
          .filter((entry) => entry.score >= 90)
          .sort((a, b) => b.score - a.score)[0];
        if (indexedChoice && controls[indexedChoice.index]) {
          const selectedByIndex = controls[indexedChoice.index];
          if (!selectedByIndex.checked) {
            clickOption(selectedByIndex);
          }
          return Boolean(selectedByIndex.checked);
        }
      }
      const ranked = controls
        .map((control) => ({ control, text: getOptionText(control), score: score(getOptionText(control)) }))
        .filter((entry) => entry.score > 0)
        .sort((a, b) => b.score - a.score);
      const selected = ranked[0]?.control || null;
      if (!selected) {
        return false;
      }
      if (!selected.checked) {
        clickOption(selected);
      }
      return Boolean(selected.checked);
    },
    {
      fieldNames: item.fieldNames || [],
      currentName: meta.name,
      currentId: meta.id,
      answer,
      choices: item.choices || [],
    }
  );
}

async function typeTextControl(page, element, item) {
  const meta = await element.evaluate((control) => ({
    tagName: control.tagName,
    type: control.type || "",
  }));
  if (meta.tagName !== "INPUT" && meta.tagName !== "TEXTAREA") {
    return false;
  }
  if (/^(hidden|file|radio|checkbox|submit|button)$/i.test(meta.type)) {
    return false;
  }
  const answer = cleanText(item.answer);
  if (!answer) {
    return false;
  }
  const existing = await element.evaluate((control) => String(control.value || "").trim()).catch(() => "");
  if (existing === answer) {
    return true;
  }
  if (await fillInputHandleWithVerification(page, element, answer)) {
    return true;
  }
  await element.evaluate((control) => control.scrollIntoView({ block: "center", inline: "nearest" })).catch(() => {});
  await element.click({ clickCount: 3 }).catch(() => {});
  await element
    .evaluate((control) => {
      const prototype =
        control instanceof HTMLTextAreaElement ? HTMLTextAreaElement.prototype : HTMLInputElement.prototype;
      const descriptor = Object.getOwnPropertyDescriptor(prototype, "value");
      control.focus();
      if (descriptor && descriptor.set) {
        descriptor.set.call(control, "");
      } else {
        control.value = "";
      }
      if (typeof control.select === "function") {
        control.select();
      }
      control.dispatchEvent(new InputEvent("input", { bubbles: true, inputType: "deleteContentBackward", data: null }));
      control.dispatchEvent(new Event("change", { bubbles: true }));
    })
    .catch(() => {});
  const modifier = process.platform === "darwin" ? "Meta" : "Control";
  await page.keyboard.down(modifier).catch(() => {});
  await page.keyboard.press("KeyA").catch(() => {});
  await page.keyboard.up(modifier).catch(() => {});
  await page.keyboard.press("Backspace").catch(() => {});
  await page.keyboard.type(answer, { delay: 15 }).catch(() => {});
  await page.keyboard.press("Tab").catch(() => {});
  await new Promise((resolve) => setTimeout(resolve, 300));
  const finalValue = await element.evaluate((control) => String(control.value || "").trim()).catch(() => "");
  if (finalValue === answer) {
    return true;
  }
  if (finalValue === `${answer}${answer}` || finalValue.toLowerCase() === `${answer}${answer}`.toLowerCase()) {
    return fillInputHandleWithVerification(page, element, answer);
  }
  return fillInputHandleWithVerification(page, element, answer);
}

async function fillChoiceByVisibleQuestion(page, item) {
  return page.evaluate((target) => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const normalize = (value) => clean(value).toLowerCase();
    const compact = (value) => normalize(value).replace(/[^a-z0-9]+/g, "");
    const wantedLabel = compact(target.label || "");
    const answer = clean(target.answer || "");
    const labelText = normalize(target.label || "");
    const isPhoneCode = /country phone code/.test(labelText);
    const isSourceQuestion = /how did you hear about us|source/.test(labelText);
    const wantedCode = (answer.match(/\+?\d{1,4}/) || [""])[0].replace(/\D/g, "");
    const score = (candidate) => {
      const wanted = normalize(answer);
      const option = normalize(candidate);
      const wantedCompact = compact(answer);
      const optionCompact = compact(candidate);
      if (!wanted || !option) return 0;
      const candidateCode = (option.match(/\+?\d{1,4}/) || [""])[0].replace(/\D/g, "");
      if (isPhoneCode && wantedCode) {
        return candidateCode === wantedCode ? 120 : 0;
      }
      if (option === wanted || optionCompact === wantedCompact) return 100;
      if (/^\d+$/.test(wantedCompact) && optionCompact.startsWith(wantedCompact)) return 92;
      if (wantedCompact && optionCompact.includes(wantedCompact)) return 86;
      if (wantedCompact && wantedCompact.includes(optionCompact)) return 82;
      if (option.includes(wanted)) return 78;
      if (wanted.includes(option)) return 74;
      if (isSourceQuestion && !/select one|choose|please select/.test(option)) {
        if (/employee|referral|referred/i.test(candidate) && !/employee|referral|referred/i.test(answer)) return 5;
        return /website|career|company site|job board|other/.test(option) ? 75 : 25;
      }
      return 0;
    };
    const isVisible = (element) => {
      if (!element || element.type === "hidden") return false;
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
    };
    const findControlFromLabel = () => {
      const labels = Array.from(document.querySelectorAll("label, legend, [class*='label' i]"))
        .filter((label) => {
          const text = compact(`${label.textContent || ""} ${label.getAttribute("aria-label") || ""}`);
          return text && wantedLabel && (text === wantedLabel || text.includes(wantedLabel) || wantedLabel.includes(text));
        })
        .sort((a, b) => clean(a.textContent || "").length - clean(b.textContent || "").length);
      for (const label of labels) {
        const forId = label.getAttribute("for");
        const byFor = forId ? document.getElementById(forId) : null;
        if (isVisible(byFor)) {
          return { control: byFor, scope: label.closest("fieldset, section, li, div") || label.parentElement };
        }
        let scope = label.parentElement;
        for (let depth = 0; depth < 4 && scope; depth += 1) {
          const control = Array.from(
            scope.querySelectorAll("select, input[type='radio'], input[type='checkbox'], [role='combobox'], button")
          ).find(isVisible);
          if (control) {
            return { control, scope };
          }
          scope = scope.parentElement;
        }
      }
      return { control: null, scope: null };
    };
    const labelledControl = findControlFromLabel();
    if (labelledControl.control && labelledControl.control.tagName === "SELECT") {
      const nativeSelect = labelledControl.control;
      const selected = Array.from(nativeSelect.options || [])
        .map((option) => ({
          option,
          text: clean(option.textContent || option.label || option.value || ""),
        }))
        .filter((entry) => entry.text)
        .map((entry) => ({ ...entry, score: score(entry.text) }))
        .filter((entry) => entry.score > 0)
        .sort((a, b) => b.score - a.score)[0];
      if (selected) {
        nativeSelect.value = selected.option.value;
        nativeSelect.dispatchEvent(new Event("input", { bubbles: true }));
        nativeSelect.dispatchEvent(new Event("change", { bubbles: true }));
        return clean(nativeSelect.value) !== "";
      }
    }
    const scopes = Array.from(document.querySelectorAll("fieldset, section, li, div"))
      .filter((scope) => {
        const text = compact(scope.textContent || "");
        return (
          text &&
          wantedLabel &&
          (text.includes(wantedLabel) || wantedLabel.includes(text)) &&
          scope.querySelector("select, input[type='radio'], input[type='checkbox'], [role='combobox'], button")
        );
      })
      .sort((a, b) => clean(a.textContent || "").length - clean(b.textContent || "").length);
    const scope = labelledControl.scope || scopes[0] || null;
    if (!scope) {
      return false;
    }

    const nativeSelect = Array.from(scope.querySelectorAll("select")).find(isVisible);
    if (nativeSelect) {
      const options = Array.from(nativeSelect.options || [])
        .map((option) => ({
          option,
          text: clean(option.textContent || option.label || option.value || ""),
        }))
        .filter((entry) => entry.text);
      const selected = options
        .map((entry) => ({ ...entry, score: score(entry.text) }))
        .filter((entry) => entry.score > 0)
        .sort((a, b) => b.score - a.score)[0];
      if (selected) {
        nativeSelect.value = selected.option.value;
        nativeSelect.dispatchEvent(new Event("input", { bubbles: true }));
        nativeSelect.dispatchEvent(new Event("change", { bubbles: true }));
        return clean(nativeSelect.value) !== "";
      }
    }

    const controls = Array.from(scope.querySelectorAll("input[type='radio'], input[type='checkbox']"))
      .filter((control) => !control.disabled);
    const optionText = (control) => {
      const id = control.getAttribute("id") || "";
      const explicit = id ? document.querySelector(`label[for="${CSS.escape(id)}"]`) : null;
      const label =
        explicit ||
        control.closest("label") ||
        control.closest("[role='radio']") ||
        control.closest("[role='checkbox']") ||
        control;
      return clean(
        `${explicit ? explicit.textContent || "" : ""} ${label.textContent || ""} ${
          control.getAttribute("aria-label") || ""
        } ${control.value || ""}`
      );
    };
    if (controls.length === 1 && controls[0].type === "checkbox") {
      const truthy = /^(yes|y|true|agree|agreed|consent|accepted|checked|1)$/i.test(answer);
      if (truthy && !controls[0].checked) {
        controls[0].scrollIntoView({ block: "center", inline: "nearest" });
        controls[0].click();
      }
      return truthy ? Boolean(controls[0].checked) : true;
    }
    if (
      controls.length > 1 &&
      Array.isArray(target.choices) &&
      target.choices.length === controls.length
    ) {
      const choiceIndex = target.choices.findIndex((choice) => score(choice) >= 90);
      if (choiceIndex >= 0 && controls[choiceIndex]) {
        const selected = controls[choiceIndex];
        selected.scrollIntoView({ block: "center", inline: "nearest" });
        if (!selected.checked) {
          selected.click();
        }
        selected.dispatchEvent(new Event("input", { bubbles: true }));
        selected.dispatchEvent(new Event("change", { bubbles: true }));
        return Boolean(selected.checked);
      }
    }
    const selectedControl = controls
      .map((control) => ({ control, text: optionText(control), score: score(optionText(control)) }))
      .filter((entry) => entry.score > 0)
      .sort((a, b) => b.score - a.score)[0]?.control;
    if (selectedControl) {
      selectedControl.scrollIntoView({ block: "center", inline: "nearest" });
      if (!selectedControl.checked) {
        selectedControl.click();
      }
      selectedControl.dispatchEvent(new Event("input", { bubbles: true }));
      selectedControl.dispatchEvent(new Event("change", { bubbles: true }));
      return Boolean(selectedControl.checked);
    }
    return false;
  }, item);
}

async function fillTextByVisibleQuestion(page, item) {
  return page.evaluate((target) => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const compact = (value) => clean(value).toLowerCase().replace(/[^a-z0-9]+/g, "");
    const wantedLabel = compact(target.label || "");
    const answer = clean(target.answer || "");
    const isVisible = (element) => {
      if (!element || element.type === "hidden") return false;
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
    };
    const setNativeValue = (element, value) => {
      const prototype = element instanceof HTMLTextAreaElement ? HTMLTextAreaElement.prototype : HTMLInputElement.prototype;
      const descriptor = Object.getOwnPropertyDescriptor(prototype, "value");
      element.focus();
      if (descriptor && descriptor.set) {
        descriptor.set.call(element, value);
      } else {
        element.value = value;
      }
      element.dispatchEvent(new Event("input", { bubbles: true }));
      element.dispatchEvent(new Event("change", { bubbles: true }));
      element.dispatchEvent(new Event("blur", { bubbles: true }));
    };
    const scopes = Array.from(document.querySelectorAll("fieldset, section, li, div"))
      .filter((scope) => {
        const text = compact(scope.textContent || "");
        return (
          text &&
          wantedLabel &&
          (text.includes(wantedLabel) || wantedLabel.includes(text)) &&
          scope.querySelector("input:not([type='hidden']), textarea")
        );
      })
      .sort((a, b) => clean(a.textContent || "").length - clean(b.textContent || "").length);
    const scope = scopes[0] || null;
    const field = scope
      ? Array.from(scope.querySelectorAll("input:not([type='hidden']), textarea")).find((control) => {
          const type = control.type || "";
          return isVisible(control) && !/^(file|radio|checkbox|submit|button)$/i.test(type);
        })
      : null;
    if (!field || !answer) {
      return false;
    }
    const existing = clean(field.value || "");
    if (existing && existing.toLowerCase() === answer.toLowerCase()) {
      return true;
    }
    setNativeValue(field, answer);
    return clean(field.value || "") !== "";
  }, item);
}

async function selectGreenhouseReactSelect(page, item) {
  const fieldName = (item.fieldNames || [])[0] || "";
  if (!fieldName) {
    return false;
  }
  const answer = getBestChoiceLabel(item.answer, item.choices || []);
  const selector = cssIdSelector(fieldName);
  const input = await page.$(selector);
  if (!input) {
    return false;
  }
  const isCombobox = await input.evaluate((element) => element.getAttribute("role") === "combobox");
  if (!isCombobox) {
    await input.dispose();
    return false;
  }
  await input.evaluate((element) => element.scrollIntoView({ block: "center" })).catch(() => {});
  await input.click().catch(() => {});
  await new Promise((resolve) => setTimeout(resolve, 150));
  const modifier = process.platform === "darwin" ? "Meta" : "Control";
  await page.keyboard.down(modifier).catch(() => {});
  await page.keyboard.press("KeyA").catch(() => {});
  await page.keyboard.up(modifier).catch(() => {});
  await page.keyboard.type(answer, { delay: 15 }).catch(() => {});
  await new Promise((resolve) => setTimeout(resolve, 300));
  const option = await page.evaluateHandle((wanted) => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const normalize = (value) => clean(value).toLowerCase();
    const compact = (value) => normalize(value).replace(/[^a-z0-9]+/g, "");
    const score = (candidate) => {
      const wantedNorm = normalize(wanted);
      const optionNorm = normalize(candidate);
      const wantedCompact = compact(wanted);
      const optionCompact = compact(candidate);
      if (!wantedNorm || !optionNorm) return 0;
      if (optionNorm === wantedNorm || optionCompact === wantedCompact) return 100;
      if (/^\d+$/.test(wantedCompact) && optionCompact.startsWith(wantedCompact)) return 92;
      if (wantedCompact && optionCompact.includes(wantedCompact)) return 86;
      if (wantedCompact && wantedCompact.includes(optionCompact)) return 82;
      if (optionNorm.includes(wantedNorm)) return 78;
      if (wantedNorm.includes(optionNorm)) return 74;
      return 0;
    };
    const isVisible = (element) => {
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
    };
    const options = Array.from(document.querySelectorAll("[role='option'], [id*='-option-']"))
      .filter(isVisible)
      .map((node) => ({ node, text: clean(node.textContent || node.getAttribute("aria-label") || "") }))
      .filter((entry) => entry.text)
      .map((entry) => ({ ...entry, score: score(entry.text) }))
      .filter((entry) => entry.score > 0)
      .sort((a, b) => b.score - a.score);
    return options[0]?.node || null;
  }, answer);
  const optionElement = option.asElement();
  if (optionElement) {
    await optionElement.click().catch(() => {});
  } else {
    await page.keyboard.press("Enter").catch(() => {});
  }
  await option.dispose();
  await new Promise((resolve) => setTimeout(resolve, 450));
  const selected = await page.evaluate(
    ({ id, wanted }) => {
      const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
      const normalize = (value) => clean(value).toLowerCase();
      const compact = (value) => normalize(value).replace(/[^a-z0-9]+/g, "");
      const score = (candidate) => {
        const wantedNorm = normalize(wanted);
        const optionNorm = normalize(candidate);
        const wantedCompact = compact(wanted);
        const optionCompact = compact(candidate);
        if (!wantedNorm || !optionNorm) return 0;
        if (optionNorm === wantedNorm || optionCompact === wantedCompact) return 100;
        if (/^\d+$/.test(wantedCompact) && optionCompact.startsWith(wantedCompact)) return 92;
        if (wantedCompact && optionCompact.includes(wantedCompact)) return 86;
        if (wantedCompact && wantedCompact.includes(optionCompact)) return 82;
        if (optionNorm.includes(wantedNorm)) return 78;
        if (wantedNorm.includes(optionNorm)) return 74;
        return 0;
      };
      const getSelectedText = (input) => {
        const shell = input.closest(".select-shell") || input.closest(".select") || input.parentElement;
        return clean(
          shell?.querySelector(
            ".select__single-value, [class*='singleValue'], [class*='single-value' i], [data-testid*='single-value' i]"
          )?.textContent || ""
        );
      };
      const input = document.getElementById(id);
      if (!input || input.getAttribute("role") !== "combobox") {
        return { ok: false, reason: "missing_combobox", selected: "" };
      }
      const selectedText = getSelectedText(input);
      if (score(selectedText) > 0) {
        return { ok: true, selected: selectedText };
      }
      return { ok: false, reason: "option_not_selected", selected: selectedText };
    },
    { id: fieldName, wanted: answer }
  );
  await input.dispose();
  return Boolean(selected && selected.ok);
}

async function fillInteractiveChoiceAnswers(page, fillItems) {
  let filled = 0;
  for (const item of fillItems) {
    const isWorkdayPrompt =
      item.provider === "workday" &&
      /how did you hear about us|country phone code|phone device type|^country$/i.test(cleanText(item.label));
    if (isWorkdayPrompt) {
      if (await withTimeout(selectWorkdayChoiceByLabel(page, item.label, item.answer), 7000, false)) {
        filled += 1;
      }
      await page.keyboard.press("Escape").catch(() => {});
      continue;
    }
    if (item.choiceLike || item.choices.length) {
      if (await withTimeout(fillChoiceByVisibleQuestion(page, item), 6000, false)) {
        filled += 1;
        continue;
      }
      if (await withTimeout(selectGreenhouseReactSelect(page, item), 6000, false)) {
        filled += 1;
        continue;
      }
    }
    const element = await findVisibleControlForItem(page, item);
    if (!element) {
      continue;
    }
    const meta = await element.evaluate((control) => ({
      tagName: control.tagName,
      type: control.type || "",
      role: control.getAttribute("role") || "",
      ariaAutocomplete: control.getAttribute("aria-autocomplete") || "",
      ariaHaspopup: control.getAttribute("aria-haspopup") || "",
    }));

    if (meta.tagName === "SELECT") {
      if (await selectNativeOption(element, item.answer, item.choices)) {
        filled += 1;
      }
      await element.dispose();
      continue;
    }

    if (meta.type === "radio" || meta.type === "checkbox") {
      if (await withTimeout(selectRadioOrCheckboxOption(page, element, item), 6000, false)) {
        filled += 1;
      }
      await element.dispose();
      continue;
    }

    const looksLikeChoice =
      item.choices.length > 0 ||
      meta.role === "combobox" ||
      meta.ariaAutocomplete === "list" ||
      meta.ariaHaspopup === "listbox" ||
      meta.ariaHaspopup === "true";
    if (looksLikeChoice) {
      if (await withTimeout(selectInteractiveOption(page, element, item), 6000, false)) {
        filled += 1;
      }
    } else if (!looksLikeChoice || /date of birth|birth date|\bdob\b/i.test(item.label || "")) {
      if (item.provider === "workday") {
        if (await withTimeout(typeTextControl(page, element, item), 6000, false)) {
          filled += 1;
        }
        await element.dispose();
        continue;
      }
      if (
        (await withTimeout(fillTextByVisibleQuestion(page, item), 6000, false)) ||
        (await withTimeout(typeTextControl(page, element, item), 6000, false))
      ) {
        filled += 1;
      }
    }
    await element.dispose();
  }
  return filled;
}

async function extractSubmissionState(page) {
  return page.evaluate(() => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const bodyText = clean(document.body ? document.body.innerText : "");
    const successPatterns = [
      /thank you for applying/i,
      /application submitted/i,
      /successfully submitted/i,
      /we received your application/i,
      /your application has been submitted/i,
      /thanks for applying/i,
    ];
    const validationPatterns = [
      /required/i,
      /please complete/i,
      /please fill/i,
      /field is required/i,
      /can't be blank/i,
      /must be selected/i,
    ];
    const errorSelectors = [
      "[role='alert']",
      "[aria-live='assertive']",
      ".error",
      ".field-error",
      ".form-error",
      ".error-message",
      ".validation-error",
      "[class*='error' i]",
    ];
    const errors = Array.from(document.querySelectorAll(errorSelectors.join(",")))
      .map((node) => clean(node.innerText || node.textContent || ""))
      .filter((text, index, list) => text && list.indexOf(text) === index)
      .slice(0, 12);
    const missingRequired = Array.from(
      document.querySelectorAll("input[required], textarea[required], select[required], [aria-required='true']")
    )
      .filter((control) => {
        if (control.type === "hidden" || control.disabled) {
          return false;
        }
        const rect = control.getBoundingClientRect();
        const fieldName = control.getAttribute("name") || "";
        const pairedWorkableCombobox = fieldName ? document.getElementById(`input_${fieldName}_input`) : null;
        const pairedWorkableComboboxValue = clean(pairedWorkableCombobox && pairedWorkableCombobox.value);
        if (
          pairedWorkableCombobox &&
          rect.width <= 2 &&
          rect.height <= 2 &&
          pairedWorkableComboboxValue !== "" &&
          !/^select/i.test(pairedWorkableComboboxValue)
        ) {
          return false;
        }
        if (pairedWorkableCombobox && rect.width <= 2 && rect.height <= 2) {
          return true;
        }
        if (control.type === "checkbox" || control.type === "radio") {
          const name = control.getAttribute("name");
          if (!name) {
            return !control.checked;
          }
          return !document.querySelector(`[name="${CSS.escape(name)}"]:checked`);
        }
        if (control.type === "file") {
          return !control.files || control.files.length === 0;
        }
        return clean(control.value) === "";
      })
      .map((control) => {
        const id = control.getAttribute("id") || "";
        const label = id ? document.querySelector(`label[for="${CSS.escape(id)}"]`) : null;
        return clean(
          (label && label.textContent) ||
            control.getAttribute("aria-label") ||
            control.getAttribute("placeholder") ||
            control.getAttribute("name") ||
            control.closest("label, fieldset, div")?.textContent ||
            "Required field"
        );
      })
      .filter((text, index, list) => text && list.indexOf(text) === index)
      .slice(0, 12);
    return {
      submission_confirmed: successPatterns.some((pattern) => pattern.test(bodyText)),
      validation_detected:
        errors.length > 0 ||
        missingRequired.length > 0 ||
        validationPatterns.some((pattern) => pattern.test(bodyText)),
      validation_errors: errors,
      missing_required_fields: missingRequired,
      page_text_sample: bodyText.slice(0, 1200),
    };
  });
}

async function getRequiredFormCompletionState(page) {
  return page.evaluate(() => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const bodyText = clean(document.body?.innerText || "");
    const isVisible = (element) => {
      if (!element) {
        return false;
      }
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.visibility !== "hidden" && style.display !== "none" && rect.width > 2 && rect.height > 2;
    };
    const getAriaLabelElements = (control) => {
      const labelledBy = control.getAttribute("aria-labelledby") || "";
      return labelledBy
        .split(/\s+/)
        .map((part) => document.getElementById(part))
        .filter(Boolean);
    };
    const getFieldScope = (control) => {
      const labelElements = getAriaLabelElements(control);
      const labelledScope = labelElements
        .map((label) => label.closest("div, fieldset, section, li"))
        .find((scope) => scope && scope.contains(control));
      if (labelledScope) {
        return labelledScope;
      }
      let scope = control;
      for (let depth = 0; depth < 8 && scope && scope.parentElement; depth += 1) {
        scope = scope.parentElement;
        if (
          scope.querySelector("label, [id*='label' i], [class*='label' i]") &&
          scope.querySelector("input, textarea, select, [role='combobox']")
        ) {
          return scope;
        }
      }
      return control.parentElement;
    };
    const labelFor = (control) => {
      const id = control.getAttribute("id") || "";
      const explicit = id ? document.querySelector(`label[for="${CSS.escape(id)}"]`) : null;
      const ariaLabelled = getAriaLabelElements(control)
        .map((part) => part.textContent || "")
        .join(" ");
      return clean(
        (explicit && explicit.textContent) ||
          ariaLabelled ||
          control.getAttribute("aria-label") ||
          control.getAttribute("name") ||
          "Required field"
      ).replace(/\*+$/, "");
    };
    const controls = Array.from(
      document.querySelectorAll("input[aria-required='true'], textarea[aria-required='true'], select[aria-required='true'], input[required], textarea[required], select[required]")
    );
    const missing = [];
    const complete = [];
    for (const control of controls) {
      if (control.getAttribute("aria-hidden") === "true" || control.type === "hidden" || control.disabled) {
        continue;
      }
      const wrapper = getFieldScope(control);
      const label = labelFor(control);
      let hasValue = false;
      const isWorkdayCountryPhoneCode = /country phone code/i.test(label);
      if (control.type === "checkbox" || control.type === "radio") {
        const name = control.getAttribute("name");
        hasValue = name
          ? Boolean(document.querySelector(`[name="${CSS.escape(name)}"]:checked`))
          : control.checked;
      } else if (control.tagName === "SELECT") {
        hasValue = clean(control.value) !== "";
      } else if (control.getAttribute("role") === "combobox" || control.getAttribute("aria-autocomplete") === "list") {
        const hiddenSelected = wrapper
          ? Array.from(
              wrapper.querySelectorAll("input[type='hidden'], input[aria-hidden='true'], input[class*='requiredInput']")
            ).some((input) => clean(input.value) !== "")
          : false;
        const selected = wrapper
          ? clean(
              wrapper.querySelector(
                ".select__single-value, [class*='singleValue'], [data-testid*='single-value' i], [class*='single-value' i]"
              )?.textContent || ""
            )
          : "";
        const placeholder = wrapper
          ? clean(wrapper.querySelector(".select__placeholder, [class*='placeholder']")?.textContent || "")
          : "";
        const controlValue = clean(control.value);
        hasValue =
          (isWorkdayCountryPhoneCode && /country phone code\*?.{0,120}\+\d{1,4}/i.test(bodyText)) ||
          hiddenSelected ||
          selected !== "" ||
          (controlValue !== "" && !/^select/i.test(controlValue) && controlValue !== placeholder);
      } else if (control.type === "file") {
        hasValue = Boolean(control.files && control.files.length);
      } else {
        const name = control.getAttribute("name") || "";
        const id = control.getAttribute("id") || "";
        const rect = control.getBoundingClientRect();
        const pairedWorkableCombobox = name ? document.getElementById(`input_${name}_input`) : null;
        const pairedWorkableComboboxValue = clean(pairedWorkableCombobox && pairedWorkableCombobox.value);
        const wrapperText = clean(wrapper && wrapper.textContent);
        const workdaySelectedText =
          (isWorkdayCountryPhoneCode || /countryphonecode|phoneNumber--countryPhoneCode/i.test(`${name} ${id}`)) &&
          /item selected|selected,\s*[^,]+|\+\d{1,4}/i.test(wrapperText) &&
          !/select one|choose|please select/i.test(wrapperText);
        if (pairedWorkableCombobox && rect.width <= 2 && rect.height <= 2) {
          hasValue = pairedWorkableComboboxValue !== "" && !/^select/i.test(pairedWorkableComboboxValue);
        } else {
        hasValue =
          (isWorkdayCountryPhoneCode && /country phone code\*?.{0,120}\+\d{1,4}/i.test(bodyText)) ||
          workdaySelectedText ||
          clean(control.value) !== "" ||
          Boolean(
            pairedWorkableCombobox &&
              rect.width <= 2 &&
              rect.height <= 2 &&
              pairedWorkableComboboxValue !== "" &&
              !/^select/i.test(pairedWorkableComboboxValue)
          );
        }
      }
      if (hasValue) {
        complete.push(label);
      } else {
        missing.push(label);
      }
    }
    return {
      complete_required_fields: complete.filter((value, index, list) => value && list.indexOf(value) === index),
      missing_required_fields: missing.filter((value, index, list) => value && list.indexOf(value) === index),
    };
  });
}

async function inspectApplicationFieldState(page, fillItems) {
  return page.evaluate((items) => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const isVisible = (element) => {
      if (!element) {
        return false;
      }
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
    };
    const findByNames = (names) => {
      for (const name of names || []) {
        const byId = document.getElementById(name);
        if (byId) {
          return byId;
        }
        const byName = document.querySelector(`[name="${CSS.escape(name)}"]`);
        if (byName) {
          return byName;
        }
      }
      return null;
    };
    const findByLabel = (labelText) => {
      const wanted = clean(labelText).toLowerCase();
      if (!wanted) {
        return null;
      }
      for (const label of Array.from(document.querySelectorAll("label"))) {
        const text = clean(label.textContent || "").toLowerCase();
        if (!text || (!text.includes(wanted) && !wanted.includes(text))) {
          continue;
        }
        const forId = label.getAttribute("for");
        if (forId && document.getElementById(forId)) {
          return document.getElementById(forId);
        }
        const nested = label.querySelector("input, textarea, select, [role='combobox']");
        if (nested) {
          return nested;
        }
      }
      return null;
    };
    return items.map((item) => {
      const control = findByNames(item.fieldNames) || findByLabel(item.label);
      const wrapper = control
        ? control.closest(".select-shell") ||
          control.closest(".select") ||
          control.closest("fieldset") ||
          control.closest("section") ||
          control.closest("li") ||
          control.closest("div")
        : null;
      const selectedText = wrapper
        ? clean(
            wrapper.querySelector(
              ".select__single-value, [class*='singleValue'], [data-testid*='single-value' i], [class*='single-value' i]"
            )?.textContent || ""
          )
        : "";
      const hiddenValues = wrapper
        ? Array.from(
            wrapper.querySelectorAll("input[type='hidden'], input[aria-hidden='true'], input[class*='requiredInput']")
          )
            .map((input) => clean(input.value || ""))
            .filter(Boolean)
        : [];
      const value = control ? clean(control.value || "") : "";
      return {
        label: item.label,
        field_names: item.fieldNames || [],
        field_types: item.fieldTypes || [],
        choice_like: Boolean(item.choiceLike),
        choice_count: Array.isArray(item.choices) ? item.choices.length : 0,
        answer_present: Boolean(clean(item.answer)),
        control_found: Boolean(control),
        control_visible: control ? isVisible(control) : false,
        tag: control ? control.tagName : "",
        type: control ? control.type || "" : "",
        role: control ? control.getAttribute("role") || "" : "",
        aria_expanded: control ? control.getAttribute("aria-expanded") || "" : "",
        value_present: Boolean(value && !/^select/i.test(value)),
        selected_text: selectedText,
        hidden_value_present: hiddenValues.length > 0,
        hidden_value_count: hiddenValues.length,
        wrapper_text_sample: clean((wrapper && wrapper.textContent) || "").slice(0, 180),
      };
    });
  }, fillItems);
}

async function fillGreenhouseVerificationCode(page, code) {
  if (!code) {
    return false;
  }
  const handle = await page.evaluateHandle(() => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const controls = Array.from(document.querySelectorAll("input, textarea"));
    const candidates = controls.filter((control) => {
      if (control.disabled || control.type === "hidden") {
        return false;
      }
      const id = control.getAttribute("id") || "";
      const name = control.getAttribute("name") || "";
      const label = id ? document.querySelector(`label[for="${CSS.escape(id)}"]`)?.textContent || "" : "";
      const placeholder = control.getAttribute("placeholder") || "";
      const aria = control.getAttribute("aria-label") || "";
      const text = clean(`${id} ${name} ${label} ${placeholder} ${aria} ${control.closest("div, label, fieldset")?.textContent || ""}`);
      return /security code|verification code|enter.*code|code field|confirmation code/i.test(text);
    });
    return candidates[0] || controls.find((control) => {
      if (control.disabled || control.type === "hidden") {
        return false;
      }
      const maxLength = Number(control.getAttribute("maxlength") || 0);
      return maxLength >= 4 && maxLength <= 20;
    }) || null;
  });
  const element = handle.asElement();
  if (!element) {
    await handle.dispose().catch(() => {});
    return false;
  }
  await element.evaluate((target) => target.scrollIntoView({ block: "center", inline: "nearest" })).catch(() => {});
  await element.click({ clickCount: 3 }).catch(async () => {
    await element.focus().catch(() => {});
  });
  const modifier = process.platform === "darwin" ? "Meta" : "Control";
  await page.keyboard.down(modifier).catch(() => {});
  await page.keyboard.press("KeyA").catch(() => {});
  await page.keyboard.up(modifier).catch(() => {});
  await page.keyboard.type(String(code), { delay: 35 });
  await element.evaluate((target) => {
    target.dispatchEvent(new Event("input", { bubbles: true }));
    target.dispatchEvent(new Event("change", { bubbles: true }));
    target.dispatchEvent(new Event("blur", { bubbles: true }));
  }).catch(() => {});
  await element.dispose().catch(() => {});
  await new Promise((resolve) => setTimeout(resolve, 350));
  return true;
}

async function pageHasIncorrectGreenhouseVerificationCode(page) {
  return page.evaluate(() => {
    const text = String(document.body?.innerText || "").replace(/\s+/g, " ").trim();
    return /incorrect security code|security code incorrect|invalid security code|incorrect verification code|invalid verification code/i.test(text);
  });
}

async function getBrowserEnvironmentDiagnostics(page) {
  return page.evaluate(() => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const bodyText = clean(document.body?.innerText || "");
    const verificationInputs = Array.from(document.querySelectorAll("input, textarea"))
      .map((control) => {
        const id = control.getAttribute("id") || "";
        const name = control.getAttribute("name") || "";
        const label = id ? document.querySelector(`label[for="${CSS.escape(id)}"]`)?.textContent || "" : "";
        const placeholder = control.getAttribute("placeholder") || "";
        const aria = control.getAttribute("aria-label") || "";
        const surroundingText = control.closest("div, label, fieldset")?.textContent || "";
        return clean(`${id} ${name} ${label} ${placeholder} ${aria} ${surroundingText}`);
      })
      .filter((text) => /security code|verification code|confirmation code|code field/i.test(text))
      .slice(0, 5);
    return {
      user_agent: navigator.userAgent || "",
      webdriver: Boolean(navigator.webdriver),
      languages: Array.from(navigator.languages || []),
      platform: navigator.platform || "",
      vendor: navigator.vendor || "",
      timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || "",
      viewport_width: window.innerWidth,
      viewport_height: window.innerHeight,
      has_greenhouse_verification_text: /security code|verification code|copy and paste.*code|enter.*code.*resubmit|code field/i.test(bodyText),
      has_incorrect_verification_text: /incorrect security code|security code incorrect|invalid security code|incorrect verification code|invalid verification code/i.test(bodyText),
      verification_input_samples: verificationInputs,
    };
  });
}

async function pageNeedsGreenhouseVerification(page) {
  return page.evaluate(() => {
    const text = String(document.body?.innerText || "").replace(/\s+/g, " ").trim();
    return /security code|verification code|copy and paste.*code|enter.*code.*resubmit|code field/i.test(text);
  });
}

function summarizeInterceptedSubmitRequest(request) {
  const postData = request.postData() || "";
  const requestUrl = request.url();
  const collectStructure = (value, prefix = "", output = []) => {
    if (!value || typeof value !== "object" || output.length >= 80) {
      return output;
    }
    if (Array.isArray(value)) {
      output.push({ path: prefix || "$", type: "array", length: value.length });
      value.slice(0, 3).forEach((entry, index) => collectStructure(entry, `${prefix}[${index}]`, output));
      return output;
    }
    const keys = Object.keys(value);
    output.push({ path: prefix || "$", type: "object", keys: keys.slice(0, 30) });
    keys.slice(0, 20).forEach((key) => {
      const next = value[key];
      if (next && typeof next === "object") {
        collectStructure(next, prefix ? `${prefix}.${key}` : key, output);
      }
    });
    return output;
  };
  const summary = {
    captured: true,
    provider: /\/wday\/cxs\//i.test(requestUrl) ? "workday" : /apply\.workable\.com/i.test(requestUrl) ? "workable" : /greenhouse/i.test(requestUrl) ? "greenhouse" : "",
    url: requestUrl,
    method: request.method(),
    post_data_length: postData.length,
    top_level_keys: [],
    has_job_application: false,
    has_workday_application_payload: false,
    candidate_fields_present: {
      first_name: false,
      last_name: false,
      email: false,
      phone: false,
      resume: false,
    },
    workday_payload_signals: {
      application: false,
      candidate: false,
      questionnaire: false,
      resume: false,
      review_or_submit: false,
    },
    question_field_count: 0,
    question_fields: [],
    payload_structure: [],
  };

  try {
    const parsed = JSON.parse(postData);
    summary.top_level_keys = Object.keys(parsed || {});
    const application = parsed?.job_application || parsed?.application || parsed || {};
    summary.payload_structure = collectStructure(application);
    summary.has_job_application = Boolean(parsed?.job_application || parsed?.application);
    const serialized = JSON.stringify(application);
    const fullSerialized = JSON.stringify(parsed);
    summary.candidate_fields_present = {
      first_name: /first[_-]?name/i.test(serialized),
      last_name: /last[_-]?name|surname|family[_-]?name/i.test(serialized),
      email: /email/i.test(serialized),
      phone: /phone|mobile|telephone/i.test(serialized),
      resume: /resume|cv|attachment/i.test(serialized),
    };
    summary.workday_payload_signals = {
      application: /jobapplication|job_application|application/i.test(fullSerialized),
      candidate: /candidate|legalName|email|phone|address/i.test(fullSerialized),
      questionnaire: /questionnaire|question|answer|response/i.test(fullSerialized),
      resume: /resume|cv|attachment|file|document/i.test(fullSerialized),
      review_or_submit: /submit|review|complete/i.test(`${requestUrl} ${fullSerialized}`),
    };
    summary.has_workday_application_payload =
      summary.provider === "workday" &&
      Object.values(summary.workday_payload_signals).filter(Boolean).length >= 2;
    const questionFields = Array.from(new Set(fullSerialized.match(/question_\d+|\b\d{8,}\b|questionnaire[^",}]*/gi) || []));
    summary.question_field_count = questionFields.length;
    summary.question_fields = questionFields.slice(0, 40);
  } catch (error) {
    summary.parse_error = error && error.message ? error.message : String(error);
    summary.post_data_sample = postData.slice(0, 240);
    summary.workday_payload_signals = {
      application: /jobapplication|job_application|application/i.test(postData),
      candidate: /candidate|legalName|email|phone|address/i.test(postData),
      questionnaire: /questionnaire|question|answer|response/i.test(postData),
      resume: /resume|cv|attachment|file|document/i.test(postData),
      review_or_submit: /submit|review|complete/i.test(`${requestUrl} ${postData}`),
    };
    summary.has_workday_application_payload =
      summary.provider === "workday" &&
      Object.values(summary.workday_payload_signals).filter(Boolean).length >= 2;
  }

  return summary;
}

function isCloudflareChallengeRequest(request) {
  const url = request.url();
  return /\/cdn-cgi\/challenge-platform\/|challenges\.cloudflare\.com\/turnstile/i.test(url);
}

function looksLikeFinalApplicationSubmitRequest(request) {
  if (request.method() !== "POST" || isCloudflareChallengeRequest(request)) {
    return false;
  }
  const url = request.url();
  const postData = request.postData() || "";
  const headers = request.headers() || {};
  const contentType = String(headers["content-type"] || headers["Content-Type"] || "");
  if (/greenhouse/i.test(url) && (/job_application/i.test(postData) || /multipart\/form-data/i.test(contentType))) {
    return true;
  }
  if (/apply\.workable\.com/i.test(url)) {
    const pathLooksRight =
      /\/api\/|\/candidate|\/candidates|\/applications|\/apply|\/accounts|\/jobs\//i.test(url);
    const bodyLooksRight =
      /firstname|first[_-]?name|lastname|last[_-]?name|email|phone|resume|cv|candidate|application|custom_attributes|answers|CA_\d+/i.test(
        postData
      ) || /multipart\/form-data/i.test(contentType);
    return pathLooksRight && bodyLooksRight;
  }
  if (/\/wday\/cxs\//i.test(url)) {
    const pathLooksRight =
      /\/jobapplication|\/candidate|\/applications|\/apply|\/submit|\/questionnaire/i.test(url);
    const bodyLooksRight =
      /email|resume|cv|candidate|application|questionnaire|jobapplication|source|legalName|phone|address/i.test(postData) ||
      /multipart\/form-data/i.test(contentType) ||
      /application\/json/i.test(contentType);
    return pathLooksRight && bodyLooksRight;
  }
  if (/successfactors\.(?:com|eu)|sapsf\.com/i.test(url)) {
    const pathLooksRight =
      /\/career|\/rcmcjsup|\/careerportal|\/candidate|\/jobapplication|\/apply/i.test(url);
    const bodyLooksRight =
      /email|resume|cv|candidate|application|first|last|phone|attachment|career_job_req_id|jobReq/i.test(postData) ||
      /multipart\/form-data/i.test(contentType) ||
      /application\/x-www-form-urlencoded/i.test(contentType);
    return pathLooksRight && bodyLooksRight;
  }
  return (
    /job_application/i.test(postData) ||
    (/\/jobs\/\d+|\/applications|\/candidate|\/candidates/i.test(url) &&
      /email|resume|cv|candidate|application|answers/i.test(postData))
  );
}

async function clickLikelyApplyButton(page) {
  const beforeUrl = page.url();
  let interceptedSubmitRequest = null;
  const observedPostRequests = [];
  let requestHandler = null;
  await page.keyboard.press("Escape").catch(() => {});
  await new Promise((resolve) => setTimeout(resolve, 250));
  if (interceptFinalSubmit) {
    await page.setRequestInterception(true);
    requestHandler = (request) => {
      if (request.method() === "POST") {
        observedPostRequests.push(summarizeInterceptedSubmitRequest(request));
      }
      if (looksLikeFinalApplicationSubmitRequest(request) && !interceptedSubmitRequest) {
        interceptedSubmitRequest = summarizeInterceptedSubmitRequest(request);
        request.abort("aborted").catch(() => {});
        return;
      }
      request.continue().catch(() => {});
    };
    page.on("request", requestHandler);
  }
  const submitHandle = await page.evaluateHandle(() => {
    const buttons = Array.from(document.querySelectorAll("button, input[type=submit]"));
    return buttons.find((button) =>
      /submit application|send application|submit$/i.test(
        `${button.textContent || ""} ${button.getAttribute("value") || ""} ${button.getAttribute("aria-label") || ""}`
      )
    ) || null;
  });
  const submitElement = submitHandle.asElement();
  let clicked = false;
  if (submitElement) {
    await submitElement.evaluate((button) => button.scrollIntoView({ block: "center", inline: "nearest" })).catch(() => {});
    await new Promise((resolve) => setTimeout(resolve, 250));
    await submitElement.click().then(() => {
      clicked = true;
    }).catch(() => {});
    await submitElement.dispose().catch(() => {});
  } else {
    await submitHandle.dispose().catch(() => {});
    const submitPoint = await page.evaluate(() => {
      const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
      const isVisible = (element) => {
        const style = window.getComputedStyle(element);
        const rect = element.getBoundingClientRect();
        return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
      };
      const buttons = Array.from(document.querySelectorAll("button, input[type=submit], [role='button']"));
      const button = buttons.find((candidate) => {
        const text = clean(
          `${candidate.textContent || ""} ${candidate.getAttribute("value") || ""} ${
            candidate.getAttribute("aria-label") || ""
          }`
        );
        return isVisible(candidate) && /submit application|send application|submit$/i.test(text);
      });
      if (!button) {
        window.scrollTo(0, document.body.scrollHeight);
        const bottomButton = buttons.find((candidate) => {
          const text = clean(
            `${candidate.textContent || ""} ${candidate.getAttribute("value") || ""} ${
              candidate.getAttribute("aria-label") || ""
            }`
          );
          return /submit application|send application|submit$/i.test(text);
        });
        if (!bottomButton) return null;
        bottomButton.scrollIntoView({ block: "center", inline: "nearest" });
        const rect = bottomButton.getBoundingClientRect();
        return { x: Math.round(rect.left + rect.width / 2), y: Math.round(rect.top + rect.height / 2) };
      }
      button.scrollIntoView({ block: "center", inline: "nearest" });
      const rect = button.getBoundingClientRect();
      return { x: Math.round(rect.left + rect.width / 2), y: Math.round(rect.top + rect.height / 2) };
    }).catch(() => null);
    if (submitPoint) {
      await new Promise((resolve) => setTimeout(resolve, 250));
      await page.mouse.click(submitPoint.x, submitPoint.y).then(() => {
        clicked = true;
      }).catch(() => {});
    }
  }
  if (clicked) {
    if (interceptFinalSubmit) {
      await new Promise((resolve) => setTimeout(resolve, 1800));
    } else {
      await page.waitForNetworkIdle({ idleTime: 1200, timeout: 10000 }).catch(() => {});
      await new Promise((resolve) => setTimeout(resolve, 1500));
    }
  }
  if (requestHandler) {
    page.off("request", requestHandler);
    await page.setRequestInterception(false).catch(() => {});
  }
  const submissionState = await extractSubmissionState(page).catch(() => ({}));
  return {
    clicked,
    beforeUrl,
    afterUrl: page.url(),
    intercepted_submit_request: interceptedSubmitRequest,
    observed_post_requests: observedPostRequests.slice(0, 12),
    ...submissionState,
  };
}

async function fillCoreCandidateFields(page, candidate, isWorkable) {
  debugLog("core_fields", "fill_first_name");
  const filledFirstName = await withTimeout(fillBySelectors(page, [
    "#first_name",
    "#firstname",
    'input[name="first_name"]',
    'input[name="firstname"]',
    'input[name="firstName"]',
    'input[name*="first" i]',
  ], candidate.firstName), 7000, false);
  debugLog("core_fields", "fill_last_name");
  const filledLastName = await withTimeout(fillBySelectors(page, [
    "#last_name",
    "#lastname",
    'input[name="last_name"]',
    'input[name="lastname"]',
    'input[name="lastName"]',
    'input[name*="last" i]',
  ], candidate.lastName), 7000, false);
  debugLog("core_fields", "fill_email");
  const filledEmail = await withTimeout(fillBySelectors(page, [
    "#email",
    'input[type="email"]',
    'input[name="email"]',
    'input[name*="email" i]',
  ], candidate.email), 7000, false);
  debugLog("core_fields", "fill_phone");
  const filledPhone = await withTimeout(fillBySelectors(page, [
    "#phone",
    'input[type="tel"]',
    'input[name*="phone" i]',
    'input[name*="mobile" i]',
  ], candidate.phone), 7000, false);
  debugLog("core_fields", "fill_address");
  const filledAddress = await withTimeout(fillBySelectors(page, [
    "#address",
    'input[name="address"]',
    'input[name*="address" i]',
    'input[name*="location" i]',
    'input[name*="city" i]',
  ], candidate.address), 7000, false);

  debugLog("core_fields", "label_fallbacks");
  if (!filledFirstName) {
    await withTimeout(fillByLabelText(page, ["first name", "given name"], candidate.firstName), 7000, false);
  }
  if (!filledLastName) {
    await withTimeout(fillByLabelText(page, ["last name", "family name", "surname"], candidate.lastName), 7000, false);
  }
  if (!filledEmail) {
    await withTimeout(fillByLabelText(page, ["email"], candidate.email), 7000, false);
  }
  if (!filledPhone) {
    await withTimeout(fillByLabelText(page, ["phone", "mobile"], candidate.phone), 7000, false);
  }
  if (!filledAddress) {
    await withTimeout(fillByLabelText(page, ["address", "current location", "location", "city"], candidate.address), 7000, false);
  }
  if (isWorkable) {
    debugLog("core_fields", "workable_visual_repair");
    await withTimeout(fillWorkableCoreTextByVisualLabel(page, [
      { key: "first_name", patterns: ["^\\*?\\s*first\\s*name\\b"], value: candidate.firstName },
      { key: "last_name", patterns: ["^\\*?\\s*last\\s*name\\b"], value: candidate.lastName },
      { key: "email", patterns: ["^\\*?\\s*email\\b"], value: candidate.email },
      { key: "phone", patterns: ["^\\*?\\s*phone\\b", "^\\*?\\s*mobile\\b"], value: candidate.phone },
    ]), 7000, {});
  }

  return {
    first_name: filledFirstName,
    last_name: filledLastName,
    email: filledEmail,
    phone: filledPhone,
    address: filledAddress,
  };
}

function getSuccessFactorsAccount(task) {
  const payload = getTaskPayload(task);
  const account =
    payload.successfactors_account && typeof payload.successfactors_account === "object"
      ? payload.successfactors_account
      : {};
  const consent =
    payload.successfactors_consent && typeof payload.successfactors_consent === "object"
      ? payload.successfactors_consent
      : {};
  const accountRoute = cleanText(account.account_route || consent.account_route || "");
  return {
    account_route: accountRoute,
    create_account: Boolean(
      accountRoute === "create" ||
        account.create_account ||
        payload.successfactors_create_account ||
        consent.create_account
    ),
    sign_in: Boolean(
      accountRoute === "sign_in" ||
        account.sign_in ||
        account.use_existing_account ||
        payload.successfactors_sign_in
    ),
    firstName: cleanText(account.firstName || account.first_name || ""),
    lastName: cleanText(account.lastName || account.last_name || ""),
    email: cleanText(account.email || task.candidate_email || ""),
    password: cleanText(account.password || payload.successfactors_password || ""),
    allow_generated_password: Boolean(
      account.allow_generated_password || payload.successfactors_allow_generated_password
    ),
    consent_scope: cleanText(consent.scope || payload.consent || ""),
  };
}

function buildSuccessFactorsApplicationUrlFromSchema(task, fallbackUrl = "") {
  const schema = getApplicationSchema(task);
  const directUrl = cleanText(
    schema.application_embed_url ||
      schema.hosted_url ||
      schema.absolute_url ||
      task?.application_workspace_url ||
      ""
  );
  if (directUrl && /[?&]career_ns=job_application\b/i.test(directUrl)) {
    return directUrl;
  }
  const ssoUrl = cleanText(schema.sso_url || "");
  const companyCode = cleanText(schema.company_code || "");
  const internalJobId = cleanText(schema.internal_job_id || schema.job_id || schema.external_job_id || "");
  const locale = cleanText(schema.locale || schema.lang || "en_GB");
  if (!ssoUrl || !companyCode || !internalJobId) {
    return fallbackUrl;
  }
  const url = new URL("/career", ssoUrl.replace(/\/+$/, ""));
  url.searchParams.set("company", companyCode);
  url.searchParams.set("site", "");
  url.searchParams.set("lang", locale);
  url.searchParams.set("login_ns", "register");
  url.searchParams.set("career_ns", "job_application");
  url.searchParams.set("career_job_req_id", internalJobId);
  url.searchParams.set("jobPipeline", "Direct");
  url.searchParams.set("clientId", "jobs2web");
  return url.toString();
}

async function getSuccessFactorsVisibleState(page) {
  return page.evaluate(() => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const visible = (element) => {
      if (!element) return false;
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
    };
    const bodyText = clean(document.body?.innerText || "");
    const fields = Array.from(document.querySelectorAll("input, textarea, select"))
      .filter((field) => field.type !== "hidden" && visible(field))
      .map((field) => ({
        tag: field.tagName.toLowerCase(),
        type: field.getAttribute("type") || "",
        name: field.getAttribute("name") || "",
        id: field.getAttribute("id") || "",
        aria_label: field.getAttribute("aria-label") || "",
        required: field.required || field.getAttribute("aria-required") === "true",
      }));
    const buttons = Array.from(document.querySelectorAll("button, a, input[type='button'], input[type='submit'], [role='button']"))
      .filter(visible)
      .map((button) => ({
        text: clean(`${button.textContent || ""} ${button.getAttribute("value") || ""} ${button.getAttribute("aria-label") || ""}`),
        id: button.getAttribute("id") || "",
        href: button.href || "",
      }))
      .filter((button) => button.text || button.href || button.id);
    return {
      title: document.title || "",
      url: location.href,
      text_sample: bodyText.slice(0, 1800),
      field_count: fields.length,
      file_input_count: fields.filter((field) => field.type === "file").length,
      fields: fields.slice(0, 120),
      buttons: buttons.slice(0, 100),
      has_listing_apply: buttons.some((button) => /apply/i.test(button.text) || /applyButton/i.test(button.id)),
      has_create_account: /create account|new user|register|create candidate profile/i.test(bodyText),
      has_sign_in: /sign in|log in|login|returning candidate/i.test(bodyText),
      has_account_exists: /account already exists|already an account with the email|send password reset email/i.test(bodyText),
      has_password: fields.some((field) => field.type === "password") || /password/i.test(bodyText),
      has_resume: /resume|cv|curriculum vitae|upload|attachment/i.test(bodyText) || fields.some((field) => field.type === "file"),
      has_verification: /verification code|security code|check your email|verify your email|confirm your email/i.test(bodyText),
      has_captcha: /captcha|recaptcha|not a robot/i.test(bodyText),
      has_submission_confirmation: /thank you for applying|application submitted|successfully submitted|application has been received|we received your application/i.test(bodyText),
    };
  }).catch(() => ({
    title: "",
    url: page.url(),
    text_sample: "",
    field_count: 0,
    file_input_count: 0,
    fields: [],
    buttons: [],
  }));
}

async function clickSuccessFactorsApplyFromListing(page) {
  const clicked = await page.evaluate(() => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const visible = (element) => {
      if (!element) return false;
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
    };
    const targets = Array.from(document.querySelectorAll("button, a, input[type='button'], input[type='submit']"));
    const target = targets.find((node) => {
      const text = clean(`${node.textContent || ""} ${node.getAttribute("value") || ""} ${node.getAttribute("aria-label") || ""}`);
      const id = node.getAttribute("id") || "";
      return visible(node) && (/^apply$/i.test(text) || /apply now|apply for this job/i.test(text) || /^applyButton/i.test(id));
    });
    if (!target) return false;
    target.scrollIntoView({ block: "center", inline: "nearest" });
    target.click();
    return true;
  }).catch(() => false);
  if (clicked) {
    await page.waitForNavigation({ waitUntil: "domcontentloaded", timeout: 20000 }).catch(() => {});
    await page.waitForNetworkIdle({ idleTime: 1000, timeout: 20000 }).catch(() => {});
    await new Promise((resolve) => setTimeout(resolve, 1000));
  }
  return clicked;
}

async function fillSuccessFactorsAccountCreationFields(page, account, password) {
  const details = {
    email: account.email || "",
    firstName: account.first_name || account.firstName || "",
    lastName: account.last_name || account.lastName || "",
    password,
  };
  const typed = {};
  const typeInto = async (key, selector, value) => {
    if (!value) return false;
    const field = await page.$(selector).catch(() => null);
    if (!field) return false;
    await field.click({ clickCount: 3 }).catch(() => {});
    await page.keyboard.press("Backspace").catch(() => {});
    await field.type(value, { delay: 12 }).catch(() => {});
    await page.evaluate((targetSelector) => {
      const fieldNode = document.querySelector(targetSelector);
      if (!fieldNode) return;
      fieldNode.dispatchEvent(new Event("input", { bubbles: true }));
      fieldNode.dispatchEvent(new Event("change", { bubbles: true }));
      fieldNode.dispatchEvent(new Event("blur", { bubbles: true }));
    }, selector).catch(() => {});
    typed[key] = true;
    return true;
  };
  await typeInto("email", "#fbclc_userName", details.email);
  await typeInto("email_confirm", "#fbclc_emailConf", details.email);
  await typeInto("password", "#fbclc_pwd", details.password);
  await typeInto("password_confirm", "#fbclc_pwdConf", details.password);
  await typeInto("first_name", "#fbclc_fName", details.firstName);
  await typeInto("last_name", "#fbclc_lName", details.lastName);
  await new Promise((resolve) => setTimeout(resolve, 300));

  const evaluated = await page.evaluate((details) => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const visible = (element) => {
      if (!element) return false;
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
    };
    const fieldLabel = (field) => {
      const labels = [];
      const id = field.getAttribute("id") || "";
      if (id) {
        const explicit = document.querySelector(`label[for="${CSS.escape(id)}"]`);
        if (explicit) labels.push(explicit.textContent || "");
      }
      if (field.closest("label")) {
        labels.push(field.closest("label").textContent || "");
      }
      let cursor = field.parentElement;
      for (let depth = 0; cursor && depth < 3; depth += 1, cursor = cursor.parentElement) {
        const text = clean(cursor.innerText || "");
        if (text && text.length < 220) labels.push(text);
      }
      return clean([
        field.getAttribute("aria-label") || "",
        field.getAttribute("placeholder") || "",
        field.getAttribute("name") || "",
        id,
        ...labels,
      ].join(" "));
    };
    const setValue = (field, value) => {
      field.scrollIntoView({ block: "center", inline: "nearest" });
      field.focus();
      field.value = value;
      field.dispatchEvent(new Event("input", { bubbles: true }));
      field.dispatchEvent(new Event("change", { bubbles: true }));
      field.dispatchEvent(new Event("blur", { bubbles: true }));
    };
    const inputs = Array.from(document.querySelectorAll("input, textarea"))
      .filter((field) => visible(field) && field.type !== "hidden" && !field.disabled && !field.readOnly);
    const result = {
      filled_email_count: 0,
      filled_password_count: 0,
      filled_first_name: false,
      filled_last_name: false,
      visible_fields: inputs.map((field) => ({
        type: field.getAttribute("type") || "",
        name: field.getAttribute("name") || "",
        id: field.getAttribute("id") || "",
        label: fieldLabel(field).slice(0, 180),
      })).slice(0, 80),
    };
    for (const field of inputs) {
      if (field.value) continue;
      const label = fieldLabel(field);
      const type = (field.getAttribute("type") || "").toLowerCase();
      if (type === "password" || /password|passcode/i.test(label)) {
        setValue(field, details.password);
        result.filled_password_count += 1;
        continue;
      }
      if (type === "email" || /e-?mail|email address|username|user id/i.test(label)) {
        setValue(field, details.email);
        result.filled_email_count += 1;
        continue;
      }
      if (!result.filled_first_name && /first name|given name|forename/i.test(label)) {
        setValue(field, details.firstName);
        result.filled_first_name = true;
        continue;
      }
      if (!result.filled_last_name && /last name|family name|surname/i.test(label)) {
        setValue(field, details.lastName);
        result.filled_last_name = true;
      }
    }
    return result;
  }, details).catch((error) => ({ error: error.message || String(error) }));
  return { typed, ...evaluated };
}

async function openSuccessFactorsCreateAccount(page) {
  const href = await page.evaluate(() => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const visible = (element) => {
      if (!element) return false;
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
    };
    const target = Array.from(document.querySelectorAll("a, button, input[type='button'], input[type='submit'], [role='button']"))
      .filter(visible)
      .find((node) => /create (an )?account|create profile|new user|register/i.test(clean(`${node.textContent || ""} ${node.getAttribute("value") || ""} ${node.getAttribute("aria-label") || ""}`)));
    if (!target) return "";
    target.scrollIntoView({ block: "center", inline: "nearest" });
    return target.href || "";
  }).catch(() => "");
  if (href && /^https?:\/\//i.test(href)) {
    await page.goto(href, { waitUntil: "domcontentloaded", timeout: navigationTimeoutMs }).catch(() => {});
  } else {
    await clickButtonByText(page, [/create (an )?account/i, /create profile/i, /new user/i, /register/i]);
  }
  await page.waitForNavigation({ waitUntil: "domcontentloaded", timeout: 20000 }).catch(() => {});
  await page.waitForNetworkIdle({ idleTime: 1000, timeout: 20000 }).catch(() => {});
  await new Promise((resolve) => setTimeout(resolve, 1000));
  return Boolean(href);
}

async function fillSuccessFactorsSignInFields(page, account) {
  const typed = {};
  const typeInto = async (key, selectors, value) => {
    if (!value) return false;
    for (const selector of selectors) {
      const field = await page.$(selector).catch(() => null);
      if (!field) continue;
      await field.click({ clickCount: 3 }).catch(() => {});
      await page.keyboard.press("Backspace").catch(() => {});
      await field.type(value, { delay: 12 }).catch(() => {});
      await page.evaluate((targetSelector) => {
        const fieldNode = document.querySelector(targetSelector);
        if (!fieldNode) return;
        fieldNode.dispatchEvent(new Event("input", { bubbles: true }));
        fieldNode.dispatchEvent(new Event("change", { bubbles: true }));
        fieldNode.dispatchEvent(new Event("blur", { bubbles: true }));
      }, selector).catch(() => {});
      typed[key] = true;
      return true;
    }
    return false;
  };
  await typeInto("email", ["#username", 'input[name="username"]', 'input[type="email"]', 'input[name*="email" i]'], account.email);
  await typeInto("password", ["#password", 'input[name="password"]', 'input[type="password"]'], account.password);
  await new Promise((resolve) => setTimeout(resolve, 300));
  return typed;
}

async function clickSuccessFactorsSignInSubmit(page) {
  const clicked = await page.evaluate(() => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const visible = (element) => {
      if (!element) return false;
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
    };
    const targets = Array.from(document.querySelectorAll("button, input[type='button'], input[type='submit'], [role='button']"))
      .filter(visible)
      .filter((node) => !node.href)
      .filter((node) => /^sign in$/i.test(clean(`${node.textContent || ""} ${node.getAttribute("value") || ""} ${node.getAttribute("aria-label") || ""}`)));
    const target = targets[0] || document.querySelector("#loginSubmit, #loginButton");
    if (!target || !visible(target)) return false;
    target.scrollIntoView({ block: "center", inline: "nearest" });
    target.click();
    return true;
  }).catch(() => false);
  if (!clicked) {
    const passwordField = await page.$("#password, input[name='password'], input[type='password']").catch(() => null);
    if (passwordField) {
      await passwordField.focus().catch(() => {});
      await page.keyboard.press("Enter").catch(() => {});
      return true;
    }
  }
  return clicked;
}

async function fillSuccessFactorsCandidateProfile(page, task, candidate, cvPath) {
  const profile = extractSuccessFactorsProfileFromCv(task, candidate);
  await page.waitForFunction(() => {
    const clean = (input) => String(input || "").replace(/\s+/g, " ").trim();
    const visible = (element) => {
      if (!element) return false;
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
    };
    return Array.from(document.querySelectorAll("input, select, button, a, [role='button']"))
      .filter(visible)
      .some((element) => {
        const signature = clean(`${element.getAttribute("name") || ""} ${element.getAttribute("aria-label") || ""} ${element.textContent || ""}`);
        return /cellPhone|Type of Business|Company Name|Name of School\/University|Marital Status|Nationality/i.test(signature);
      });
  }, { timeout: 30000 }).catch(() => {});
  await new Promise((resolve) => setTimeout(resolve, 700));
  const uploadedResume = await withTimeout(uploadResume(page, cvPath), 30000, false).catch(() => false);
  const fillResult = await page.evaluate((profile) => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const visible = (element) => {
      if (!element || element.disabled || element.type === "hidden") return false;
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
    };
    const setValue = (field, value) => {
      if (!field || !clean(value)) return false;
      field.scrollIntoView({ block: "center", inline: "nearest" });
      field.focus();
      field.value = value;
      field.dispatchEvent(new Event("input", { bubbles: true }));
      field.dispatchEvent(new Event("change", { bubbles: true }));
      field.dispatchEvent(new Event("blur", { bubbles: true }));
      return true;
    };
    const byName = (name) => Array.from(document.querySelectorAll(`[name="${CSS.escape(name)}"]`)).find(visible);
    const byAria = (label) => Array.from(document.querySelectorAll("input, textarea, select"))
      .find((field) => visible(field) && clean(field.getAttribute("aria-label") || "").toLowerCase() === label.toLowerCase());
    const fillNamed = (key, field, value) => {
      const ok = setValue(field, value);
      return { key, ok, value_present: clean(value) !== "" };
    };
    const pickSelect = (key, field, value) => {
      if (!field || !clean(value)) return { key, ok: false, value_present: clean(value) !== "" };
      const wanted = clean(value).toLowerCase();
      const compact = (input) => clean(input).toLowerCase().replace(/[^a-z0-9]+/g, "");
      const option = Array.from(field.options || []).find((candidate) => {
        const label = clean(candidate.textContent || candidate.label || candidate.value);
        return (
          label.toLowerCase() === wanted ||
          compact(label) === compact(wanted) ||
          label.toLowerCase().includes(wanted) ||
          wanted.includes(label.toLowerCase())
        );
      });
      if (!option) return { key, ok: false, value_present: true };
      field.value = option.value;
      field.dispatchEvent(new Event("input", { bubbles: true }));
      field.dispatchEvent(new Event("change", { bubbles: true }));
      return { key, ok: true, value_present: true, selected: clean(option.textContent || option.label || option.value) };
    };
    const results = [
      fillNamed("phone", byName("cellPhone"), profile.phone),
      fillNamed("type_of_business", byAria("Type of Business"), profile.type_of_business),
      fillNamed("company_name", byAria("Company Name") || byName("VFLD1"), profile.company_name),
      fillNamed("employment_country", byAria("Country"), profile.employment_country),
      fillNamed("title", byAria("Title") || byName("VFLD3"), profile.title),
      fillNamed("school", byAria("Name of School/University"), profile.school),
      fillNamed("subject", byAria("Subject"), profile.subject),
      fillNamed("degree_type", byAria("Degree Type"), profile.degree_type),
      fillNamed("passing_year", byAria("Passing Year") || byName("VFLD5"), profile.passing_year),
      fillNamed("education_country", byAria("Country of Education"), profile.education_country),
      pickSelect("gender", byName("gender"), profile.gender),
      fillNamed("marital_status", byAria("Marital Status"), profile.marital_status),
      fillNamed("nationality", byAria("Nationality"), profile.nationality),
      fillNamed("country_of_residence", byAria("Country of Residence"), profile.country_of_residence),
    ];
    return {
      filled: results.filter((item) => item.ok).length,
      attempted: results.filter((item) => item.value_present).length,
      results,
    };
  }, profile).catch((error) => ({ error: error.message || String(error), filled: 0, attempted: 0, results: [] }));
  const pickerResults = [];
  for (const [label, value] of [
    ["Type of Business", profile.type_of_business],
    ["Country", profile.employment_country],
    ["Subject", profile.subject],
    ["Degree Type", profile.degree_type],
    ["Country of Education", profile.education_country],
    ["Marital Status", profile.marital_status],
    ["Nationality", profile.nationality],
    ["Country of Residence", profile.country_of_residence],
    ["City", profile.city],
  ]) {
    if (cleanText(value)) {
      pickerResults.push(await selectSuccessFactorsPickerOption(page, label, value));
    }
  }
  return {
    profile,
    uploaded_resume: uploadedResume,
    ...fillResult,
    picker_results: pickerResults,
  };
}

async function selectSuccessFactorsPickerOption(page, label, value) {
  const opened = await page.evaluate((label) => {
    const clean = (input) => String(input || "").replace(/\s+/g, " ").trim();
    const visible = (element) => {
      if (!element) return false;
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
    };
    const field = Array.from(document.querySelectorAll("input, select"))
      .find((candidate) => visible(candidate) && clean(candidate.getAttribute("aria-label") || "").toLowerCase() === clean(label).toLowerCase());
    if (!field) return { opened: false };
    field.scrollIntoView({ block: "center", inline: "nearest" });
    field.focus();
    const id = field.getAttribute("id") || "";
    const button = id
      ? document.getElementById(id.replace(/_input$/, "_selectButton")) || document.getElementById(id.replace(/_select$/, "_selectButton"))
      : null;
    if (button && visible(button)) {
      button.click();
      return { opened: true, field_id: id };
    }
    field.click();
    return { opened: true, field_id: id };
  }, label).catch(() => ({ opened: false }));
  if (!opened.opened) {
    return { label, value, opened: false, selected: false };
  }
  await new Promise((resolve) => setTimeout(resolve, 900));
  const selected = await page.evaluate((label, value) => {
    const clean = (input) => String(input || "").replace(/\s+/g, " ").trim();
    const compact = (input) => clean(input).toLowerCase().replace(/[^a-z0-9]+/g, "");
    const visible = (element) => {
      if (!element) return false;
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
    };
    const aliasesFor = (fieldLabel, rawValue) => {
      const normalizedLabel = clean(fieldLabel).toLowerCase();
      const normalizedValue = clean(rawValue);
      const aliases = [normalizedValue];
      if (/nationality/.test(normalizedLabel) && /moroccan/i.test(normalizedValue)) aliases.push("Morocco");
      if (/subject/.test(normalizedLabel) && /(finance|m&a|valuation|financial|accounting)/i.test(normalizedValue)) {
        aliases.push("Finance", "Accounting/Finance", "Business Administration", "Economics");
      }
      if (/type of business/.test(normalizedLabel) && /(bank|financial|investment|asset|private equity)/i.test(normalizedValue)) {
        aliases.push("Banking", "Financial Services", "Financial & Insurance", "Finance");
      }
      if (/degree type/.test(normalizedLabel) && /master/i.test(normalizedValue)) aliases.push("Master's Degree", "Masters", "Master");
      if (/degree type/.test(normalizedLabel) && /bachelor/i.test(normalizedValue)) aliases.push("Bachelor's Degree", "Bachelor", "Bachelors");
      return aliases.filter(Boolean);
    };
    const dialogScopes = Array.from(document.querySelectorAll("[role='dialog'], [role='listbox'], [id*='dlg'], [id*='popup'], .sapUiDlg, .sapUiPopup, .sapMDialog, .sapMPopover"))
      .filter(visible)
      .map((element) => {
        const rect = element.getBoundingClientRect();
        const zIndex = Number.parseInt(window.getComputedStyle(element).zIndex || "0", 10) || 0;
        const text = clean(element.innerText || element.textContent || "");
        return { element, area: rect.width * rect.height, zIndex, hasOk: /\bOK\b/i.test(text) };
      })
      .sort((a, b) => Number(b.hasOk) - Number(a.hasOk) || b.zIndex - a.zIndex || a.area - b.area);
    const topScope = dialogScopes[0]?.element || document.body;
    const candidates = Array.from(document.querySelectorAll("[role='option'], li, tr, td, span, a, div"))
      .filter(visible)
      .map((element) => {
        const text = clean(element.textContent || element.getAttribute("aria-label") || "");
        const selectable = element.closest("[role='option'], tr, li, a, button, [role='button']") || element;
        const inTopScope = topScope === document.body || topScope.contains(element);
        const rect = element.getBoundingClientRect();
        return { element, selectable, text, inTopScope, top: rect.top };
      })
      .filter((item) => item.text && item.text.length <= 120 && !/^(ok|cancel|close|search|no selection)$/i.test(item.text));
    const target = candidates
      .map((item) => {
        const text = item.text.toLowerCase();
        const itemCompact = compact(item.text);
        const score = aliasesFor(label, value).reduce((best, alias) => {
          const aliasText = clean(alias).toLowerCase();
          const aliasCompact = compact(alias);
          if (!aliasText || !aliasCompact) return best;
          if (text === aliasText) return Math.max(best, 100);
          if (itemCompact === aliasCompact) return Math.max(best, 95);
          if (itemCompact.includes(aliasCompact)) return Math.max(best, 80);
          if (aliasCompact.includes(itemCompact) && itemCompact.length >= 4) return Math.max(best, 65);
          if (text.includes(aliasText)) return Math.max(best, 55);
          return best;
        }, 0);
        return { ...item, score: score + (item.inTopScope ? 5 : 0) };
      })
      .filter((item) => item.score > 0)
      .sort((a, b) => (b.score - a.score) || (a.text.length - b.text.length) || (a.top - b.top))[0];
    if (!target) {
      return { selected: false, options: candidates.map((item) => item.text).slice(0, 30) };
    }
    target.selectable.scrollIntoView({ block: "center", inline: "nearest" });
    target.selectable.dispatchEvent(new MouseEvent("mousedown", { bubbles: true }));
    target.selectable.dispatchEvent(new MouseEvent("mouseup", { bubbles: true }));
    target.selectable.click();
    return { selected: true, matched: target.text, score: target.score };
  }, label, value).catch((error) => ({ selected: false, error: error.message || String(error) }));
  if (!selected.selected) {
    await page.keyboard.press("Escape").catch(() => {});
    await new Promise((resolve) => setTimeout(resolve, 300));
    return { label, value, opened: true, selected: false, ...selected };
  }
  await new Promise((resolve) => setTimeout(resolve, 250));
  const committed = await page.evaluate(() => {
    const clean = (input) => String(input || "").replace(/\s+/g, " ").trim();
    const visible = (element) => {
      if (!element) return false;
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
    };
    const buttons = Array.from(document.querySelectorAll("button, input[type='button'], input[type='submit'], [role='button'], a"))
      .filter(visible)
      .map((element) => {
        const text = clean(`${element.textContent || ""} ${element.getAttribute("value") || ""} ${element.getAttribute("aria-label") || ""}`);
        const rect = element.getBoundingClientRect();
        const zIndex = Number.parseInt(window.getComputedStyle(element).zIndex || "0", 10) || 0;
        const hasDialog = Boolean(element.closest("[role='dialog'], [id*='dlg'], [id*='popup'], .sapUiDlg, .sapUiPopup, .sapMDialog, .sapMPopover"));
        return { element, text, zIndex, top: rect.top, hasDialog };
      })
      .filter((item) => /^ok$/i.test(item.text));
    const target = buttons.sort((a, b) => Number(b.hasDialog) - Number(a.hasDialog) || b.zIndex - a.zIndex || b.top - a.top)[0];
    if (!target) return { clicked_ok: false };
    target.element.scrollIntoView({ block: "center", inline: "nearest" });
    target.element.click();
    return { clicked_ok: true, ok_text: target.text };
  }).catch((error) => ({ clicked_ok: false, error: error.message || String(error) }));
  if (!committed.clicked_ok) {
    await page.keyboard.press("Enter").catch(() => {});
  }
  await page.waitForNetworkIdle({ idleTime: 500, timeout: 8000 }).catch(() => {});
  await new Promise((resolve) => setTimeout(resolve, 700));
  const verified = await page.evaluate((fieldId, label, matched) => {
    const clean = (input) => String(input || "").replace(/\s+/g, " ").trim();
    const visible = (element) => {
      if (!element) return false;
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
    };
    const field = fieldId ? document.getElementById(fieldId) : Array.from(document.querySelectorAll("input, select"))
      .find((candidate) => visible(candidate) && clean(candidate.getAttribute("aria-label") || "").toLowerCase() === clean(label).toLowerCase());
    const fieldValue = clean(field?.value || field?.textContent || "");
    const fieldText = clean(field?.closest("td, tr, div")?.innerText || "");
    const dialogOpen = Array.from(document.querySelectorAll("button, input[type='button'], [role='button']"))
      .filter(visible)
      .some((button) => /^ok$/i.test(clean(`${button.textContent || ""} ${button.getAttribute("value") || ""} ${button.getAttribute("aria-label") || ""}`)));
    return {
      verified: Boolean(clean(matched) && (fieldValue.includes(clean(matched)) || fieldText.includes(clean(matched)))),
      field_value: fieldValue,
      field_text: fieldText.slice(0, 180),
      dialog_open: dialogOpen,
    };
  }, opened.field_id, label, selected.matched).catch((error) => ({ verified: false, error: error.message || String(error) }));
  if (verified.dialog_open || !verified.verified) {
    await page.keyboard.press("Escape").catch(() => {});
    await new Promise((resolve) => setTimeout(resolve, 300));
  }
  return { label, value, opened: true, selected: true, ...selected, committed, ...verified };
}

async function chooseSuccessFactorsCandidateRoute(page, account) {
  const state = await getSuccessFactorsVisibleState(page);
  if (state.has_captcha || state.has_verification) {
    return { blocked: true, status: "verification_required", reason: "SuccessFactors requires verification before the worker can continue." };
  }
  if (!state.has_create_account && !state.has_sign_in) {
    return { blocked: false, clicked: false, route: "" };
  }

  if (account.sign_in) {
    if (!account.email || !account.password) {
      return {
        blocked: true,
        status: "verification_required",
        reason: "SuccessFactors needs the candidate email and password for this employer account before the worker can sign in.",
      };
    }
    if (!state.has_password) {
      await clickButtonByText(page, [/sign in/i, /log in/i, /login/i, /returning candidate/i]);
      await page.waitForNetworkIdle({ idleTime: 1000, timeout: 15000 }).catch(() => {});
    }
    const filledAccount = await fillSuccessFactorsSignInFields(page, account);
    const clicked = await clickSuccessFactorsSignInSubmit(page);
    if (clicked) {
      await page.waitForNavigation({ waitUntil: "domcontentloaded", timeout: 20000 }).catch(() => {});
      await page.waitForNetworkIdle({ idleTime: 1000, timeout: 20000 }).catch(() => {});
    }
    return { blocked: false, clicked: true, route: "sign_in", account_field_diagnostics: filledAccount };
  }

  if (account.create_account && allowSuccessFactorsAccountCreation) {
    if (!state.has_create_account) {
      return { blocked: true, status: "review_required", reason: "SuccessFactors did not expose a create-account route on this page." };
    }
    const password =
      account.password ||
      (account.allow_generated_password
        ? generateSuccessFactorsPassword()
        : "");
    if (!account.email || !password) {
      return {
        blocked: true,
        status: "verification_required",
        reason: "SuccessFactors account creation needs the candidate email and an employer-account password or generated-password consent.",
      };
    }
    await openSuccessFactorsCreateAccount(page);
    const filledAccount = await fillSuccessFactorsAccountCreationFields(page, account, password);
    const clicked = await clickButtonByText(page, [/create account/i, /create profile/i, /^register$/i, /^submit$/i, /^continue$/i]);
    if (clicked) {
      await page.waitForNavigation({ waitUntil: "domcontentloaded", timeout: 20000 }).catch(() => {});
      await page.waitForNetworkIdle({ idleTime: 1000, timeout: 15000 }).catch(() => {});
    }
    return {
      blocked: false,
      clicked: true,
      route: "create_account",
      account_field_diagnostics: filledAccount,
      generated_password: !account.password && account.allow_generated_password ? password : "",
    };
  }

  if (state.has_create_account || state.has_sign_in || state.has_password) {
    return {
      blocked: true,
      status: "verification_required",
      reason: "SuccessFactors is asking the candidate to sign in or create an account. Candidate account details and consent are required before the worker can continue.",
    };
  }

  return { blocked: false, clicked: false, route: "" };
}

async function clickButtonByText(page, patterns) {
  return page.evaluate((sources) => {
    const regexes = sources.map((source) => new RegExp(source, "i"));
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const visible = (element) => {
      if (!element) return false;
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
    };
    const target = Array.from(document.querySelectorAll("button, a, input[type='button'], input[type='submit'], [role='button']"))
      .filter(visible)
      .find((node) => regexes.some((regex) => regex.test(clean(`${node.textContent || ""} ${node.getAttribute("value") || ""} ${node.getAttribute("aria-label") || ""}`))));
    if (!target) return false;
    target.scrollIntoView({ block: "center", inline: "nearest" });
    target.click();
    return true;
  }, patterns.map((pattern) => pattern.source)).catch(() => false);
}

async function processSuccessFactorsTask(page, task, candidate, cvPath, url) {
  const schemaUrl = buildSuccessFactorsApplicationUrlFromSchema(task, url);
  if (schemaUrl && schemaUrl !== page.url() && /[?&]career_ns=job_application\b/i.test(schemaUrl)) {
    await page.goto(schemaUrl, { waitUntil: "domcontentloaded", timeout: navigationTimeoutMs }).catch(() => {});
    await page.waitForNetworkIdle({ idleTime: 1000, timeout: 20000 }).catch(() => {});
  } else if (!/[?&]career_ns=job_application\b/i.test(page.url())) {
    await clickSuccessFactorsApplyFromListing(page);
  }

  await dismissCookieBanners(page);
  let state = await getSuccessFactorsVisibleState(page);
  const account = getSuccessFactorsAccount(task);
  account.firstName = account.firstName || candidate.firstName || "";
  account.lastName = account.lastName || candidate.lastName || "";
  const route = await chooseSuccessFactorsCandidateRoute(page, account);
  const generatedSuccessFactorsPassword = cleanText(route.generated_password || "");
  if (route.blocked) {
    const screenshotPath = path.join(os.tmpdir(), `${task.task_uuid}.png`);
    await page.screenshot({ path: screenshotPath, fullPage: true }).catch(() => {});
    return {
      provider: "successfactors",
      url,
      final_url: page.url(),
      allow_final_submit: allowFinalSubmit,
      allow_successfactors_account_creation: allowSuccessFactorsAccountCreation,
      clicked_submit: false,
      form_opened: /[?&]career_ns=job_application\b/i.test(page.url()),
      form_ready: false,
      uploaded_resume: false,
      successfactors_state: state,
      local_screenshot_path: screenshotPath,
      verification_required: route.status === "verification_required",
      last_error: route.reason,
      status: route.status || "review_required",
      generated_password: generatedSuccessFactorsPassword,
    };
  }

  state = await getSuccessFactorsVisibleState(page);
  if (state.has_account_exists) {
    const screenshotPath = path.join(os.tmpdir(), `${task.task_uuid}.png`);
    await page.screenshot({ path: screenshotPath, fullPage: true }).catch(() => {});
    return {
      provider: "successfactors",
      url,
      final_url: page.url(),
      allow_final_submit: allowFinalSubmit,
      allow_successfactors_account_creation: allowSuccessFactorsAccountCreation,
      clicked_submit: false,
      form_opened: true,
      form_ready: false,
      uploaded_resume: false,
      successfactors_state: state,
      local_screenshot_path: screenshotPath,
      verification_required: true,
      last_error: "SuccessFactors says an account already exists for this email. The candidate must sign in or approve/use password reset before the worker can continue.",
      status: "verification_required",
    };
  }
  const formReady = {
    opened: /[?&]career_ns=job_application\b/i.test(page.url()) || route.clicked,
    ready: state.field_count > 0 || state.has_resume,
  };
  await page.waitForNetworkIdle({ idleTime: 1000, timeout: 20000 }).catch(() => {});
  const isCandidateProfile = /candidate profile|my profile/i.test(`${state.title} ${state.text_sample}`) || /\/portalcareer/i.test(page.url());
  const successFactorsProfileFill = isCandidateProfile
    ? await fillSuccessFactorsCandidateProfile(page, task, candidate, cvPath)
    : { uploaded_resume: false, attempted: 0, filled: 0, results: [] };
  const coreFieldFill = isCandidateProfile
    ? { skipped: "successfactors_candidate_profile" }
    : await withTimeout(fillCoreCandidateFields(page, candidate, false), 20000, {})
      .catch((error) => ({ error: error.message || String(error) }));
  await page.waitForNetworkIdle({ idleTime: 1000, timeout: 20000 }).catch(() => {});
  const uploadedResume = successFactorsProfileFill.uploaded_resume || await withTimeout(uploadResume(page, cvPath), 30000, false).catch(() => false);
  const applicationAnswers = await fillApplicationAnswers(page, task, null).catch((error) => ({
    attempted: 0,
    filled: 0,
    choice_attempted: 0,
    choice_filled: 0,
    field_diagnostics: [{ error: error.message || String(error) }],
  }));
  const formCompletion = await getRequiredFormCompletionState(page).catch(() => ({
    complete_required_fields: [],
    missing_required_fields: [],
  }));
  state = await getSuccessFactorsVisibleState(page);
  const screenshotPath = path.join(os.tmpdir(), `${task.task_uuid}.png`);
  await page.screenshot({ path: screenshotPath, fullPage: true }).catch(() => {});

  if (state.has_captcha || state.has_verification) {
    return {
      provider: "successfactors",
      url,
      final_url: page.url(),
      allow_final_submit: allowFinalSubmit,
      allow_successfactors_account_creation: allowSuccessFactorsAccountCreation,
      clicked_submit: false,
      form_opened: formReady.opened,
      form_ready: formReady.ready,
      uploaded_resume: uploadedResume,
      application_answers_attempted: applicationAnswers.attempted,
      application_answers_filled: applicationAnswers.filled,
      application_choice_answers_attempted: applicationAnswers.choice_attempted,
      application_choice_answers_filled: applicationAnswers.choice_filled,
      application_field_diagnostics: applicationAnswers.field_diagnostics || [],
      complete_required_fields: formCompletion.complete_required_fields,
      missing_required_fields: formCompletion.missing_required_fields,
      successfactors_state: state,
      local_screenshot_path: screenshotPath,
      verification_required: true,
      last_error: "SuccessFactors requires candidate verification or CAPTCHA before submission can continue.",
      status: "verification_required",
      generated_password: generatedSuccessFactorsPassword,
    };
  }

  const missingRequiredQuestions = getMissingRequiredSchemaQuestions(task, candidate, Boolean(cvPath), null);
  const missingRequiredFields = Array.from(new Set([
    ...missingRequiredQuestions,
    ...formCompletion.missing_required_fields,
  ])).filter(Boolean);
  if (allowFinalSubmit && missingRequiredFields.length > 0) {
    return {
      provider: "successfactors",
      url,
      final_url: page.url(),
      allow_final_submit: allowFinalSubmit,
      allow_successfactors_account_creation: allowSuccessFactorsAccountCreation,
      clicked_submit: false,
      form_opened: formReady.opened,
      form_ready: formReady.ready,
      uploaded_resume: uploadedResume,
      application_answers_attempted: applicationAnswers.attempted,
      application_answers_filled: applicationAnswers.filled,
      application_choice_answers_attempted: applicationAnswers.choice_attempted,
      application_choice_answers_filled: applicationAnswers.choice_filled,
      application_field_diagnostics: applicationAnswers.field_diagnostics || [],
      complete_required_fields: formCompletion.complete_required_fields,
      missing_required_fields: missingRequiredFields,
      successfactors_state: state,
      local_screenshot_path: screenshotPath,
      last_error: "SuccessFactors still has required fields needing review: " + missingRequiredFields.slice(0, 10).join("; "),
      status: "review_required",
      generated_password: generatedSuccessFactorsPassword,
    };
  }

  let clickedSubmit = false;
  let submitResult = { clicked: false, beforeUrl: page.url(), afterUrl: page.url() };
  if (allowFinalSubmit) {
    submitResult = await clickLikelyApplyButton(page);
    clickedSubmit = Boolean(submitResult.clicked);
    await page.screenshot({ path: screenshotPath, fullPage: true }).catch(() => {});
    state = await getSuccessFactorsVisibleState(page);
  }

  let status = "dry_run_ready";
  let lastError = "";
  if (allowFinalSubmit) {
    if (submitResult.submission_confirmed || state.has_submission_confirmation) {
      status = "submitted";
    } else if (state.has_captcha || state.has_verification) {
      status = "verification_required";
      lastError = "SuccessFactors requires candidate verification before submission can be confirmed.";
    } else {
      status = "review_required";
      lastError = clickedSubmit
        ? "The worker clicked submit, but SuccessFactors did not show a clear submission confirmation."
        : "The worker could not find a final SuccessFactors submit button.";
    }
  }

  return {
    provider: "successfactors",
    url,
    final_url: page.url(),
    allow_final_submit: allowFinalSubmit,
    allow_successfactors_account_creation: allowSuccessFactorsAccountCreation,
    clicked_submit: clickedSubmit,
    form_opened: formReady.opened,
    form_ready: formReady.ready,
    submit_before_url: submitResult.beforeUrl,
    submit_after_url: submitResult.afterUrl,
    uploaded_resume: uploadedResume,
    successfactors_profile_fill: successFactorsProfileFill,
    core_field_fill: coreFieldFill,
    application_answers_attempted: applicationAnswers.attempted,
    application_answers_filled: applicationAnswers.filled,
    application_choice_answers_attempted: applicationAnswers.choice_attempted,
    application_choice_answers_filled: applicationAnswers.choice_filled,
    application_field_diagnostics: applicationAnswers.field_diagnostics || [],
    complete_required_fields: formCompletion.complete_required_fields,
    missing_required_fields: submitResult.missing_required_fields || missingRequiredFields,
    intercepted_submit_request: submitResult.intercepted_submit_request || null,
    observed_post_requests: submitResult.observed_post_requests || [],
    submission_confirmed: Boolean(submitResult.submission_confirmed || state.has_submission_confirmation),
    validation_detected: Boolean(submitResult.validation_detected),
    validation_errors: submitResult.validation_errors || [],
    successfactors_state: state,
    local_screenshot_path: screenshotPath,
    verification_required: status === "verification_required",
    last_error: lastError,
    status,
    generated_password: generatedSuccessFactorsPassword,
  };
}

async function processTask(task) {
  const url = task.application_workspace_url || task.application_url;
  const verificationCode = getVerificationCode(task);
  const candidate = {
    name: task.candidate_name || "",
    email: task.candidate_email || "",
    phone: getCandidatePhone(task),
    address: getCandidateAddress(task),
  };
  const { firstName, lastName } = splitName(candidate.name);
  candidate.firstName = firstName;
  candidate.lastName = lastName;
  const cvPath = await downloadFile(task.cv_file_url, task.cv_file_name);
  task.__sffc_cv_text = getCvText(task) || await extractTextFromLocalCvFile(cvPath);
  debugLog(task.task_uuid || "task", "cv_downloaded", Boolean(cvPath));
  const executablePath = getBrowserExecutablePath();
  debugLog(task.task_uuid || "task", "launching_browser", executablePath || "puppeteer-managed");
  const browser = await puppeteer.launch({
    headless: browserHeadless,
    executablePath: executablePath || undefined,
    pipe: browserPipe,
    userDataDir: browserUserDataDir || undefined,
    timeout: browserLaunchTimeoutMs,
    args: ["--no-sandbox", "--disable-setuid-sandbox"],
  });

  try {
    const page = await browser.newPage();
    task.__sffc_browser_diagnostics = attachPageDiagnostics(page);
    await page.setViewport({ width: 1440, height: 1200 });
    debugLog(task.task_uuid || "task", "goto", url);
    await page.goto(url, { waitUntil: "domcontentloaded", timeout: navigationTimeoutMs });
    await page.waitForNetworkIdle({ idleTime: 1000, timeout: 20000 }).catch(() => {});
    if (isWorkdayApplication(task, url)) {
      debugLog(task.task_uuid || "task", "workday_adapter_start");
      return await processWorkdayTask(page, task, candidate, cvPath, url);
    }
    if (isSuccessFactorsApplication(task, url)) {
      debugLog(task.task_uuid || "task", "successfactors_adapter_start");
      return await processSuccessFactorsTask(page, task, candidate, cvPath, url);
    }
    debugLog(task.task_uuid || "task", "form_ready_check");
    const formReady = await ensureApplicationFormReady(page);
    const dismissedCookies = await dismissCookieBanners(page);
    debugLog(task.task_uuid || "task", "cookies_dismissed", Boolean(dismissedCookies));
    debugLog(task.task_uuid || "task", "form_ready", JSON.stringify(formReady));

    const isWorkable = isWorkableApplication(task, url);
    let uploadedResume = false;
    let workableResumeImport = null;
    if (isWorkable) {
      debugLog(task.task_uuid || "task", "workable_resume_import_start");
      workableResumeImport = await withTimeout(
        uploadWorkableResumeViaImport(page, cvPath),
        90000,
        { uploaded: false, imported: false, clicked_import: false, fallback_upload: false }
      );
      uploadedResume = Boolean(workableResumeImport.uploaded);
      debugLog(task.task_uuid || "task", "workable_resume_import", JSON.stringify(workableResumeImport));
      await withTimeout(fillCoreCandidateFields(page, candidate, true), 45000, {});
    } else {
      debugLog(task.task_uuid || "task", "fill_core_candidate_fields");
      await withTimeout(fillCoreCandidateFields(page, candidate, false), 12000, {});
      debugLog(task.task_uuid || "task", "upload_resume");
      uploadedResume = await uploadResume(page, cvPath);
    }
    let liveApplicationSchema = null;
    if (isWorkable) {
      debugLog(task.task_uuid || "task", "workable_live_schema_discovery");
      liveApplicationSchema = await discoverWorkableLiveSchema(page).catch(() => null);
      debugLog(
        task.task_uuid || "task",
        "workable_live_schema",
        `${getSchemaQuestions(liveApplicationSchema || {}).length} fields`
      );
    }
    debugLog(task.task_uuid || "task", "fill_answers");
    const applicationAnswers = await fillApplicationAnswers(page, task, liveApplicationSchema);
    if (isWorkableApplication(task, url)) {
      const answers = getApplicationAnswers(task);
      const dobAnswer = answerByPatterns(answers, [/date.*birth/, /\bdob\b/, /birth.*date/, /CA_50334/i]);
      if (dobAnswer) {
        debugLog(task.task_uuid || "task", "workable_dob_repair");
        const filledDob = await withTimeout(fillWorkableDateByFieldName(page, "CA_50334", dobAnswer), 7000, false);
        if (!filledDob) {
          await withTimeout(fillWorkableCoreTextByVisualLabel(page, [
            { key: "date_of_birth", patterns: ["date\\s*of\\s*birth", "\\bdob\\b", "birth\\s*date"], value: dobAnswer },
          ]), 7000, {});
        }
      }
    }
    debugLog(
      task.task_uuid || "task",
      "answers_filled",
      `${applicationAnswers.filled}/${applicationAnswers.attempted}`,
      `choices=${applicationAnswers.choice_filled}/${applicationAnswers.choice_attempted}`
    );
    let browserDiagnostics = await getBrowserEnvironmentDiagnostics(page).catch(() => ({}));
    if (workableResumeImport) {
      browserDiagnostics = {
        ...browserDiagnostics,
        workable_resume_import: workableResumeImport,
      };
    }

    const screenshotPath = path.join(os.tmpdir(), `${task.task_uuid}.png`);
    debugLog(task.task_uuid || "task", "screenshot_before_submit", screenshotPath);
    await page.screenshot({ path: screenshotPath, fullPage: true });

    const missingRequiredQuestions = getMissingRequiredSchemaQuestions(task, candidate, Boolean(cvPath), liveApplicationSchema);
    if (allowFinalSubmit && missingRequiredQuestions.length > 0) {
      return {
        provider: task.provider || "",
        url,
        allow_final_submit: allowFinalSubmit,
        clicked_submit: false,
        form_opened: formReady.opened,
        form_ready: formReady.ready,
        uploaded_resume: uploadedResume,
        browser_diagnostics: browserDiagnostics,
        intercept_final_submit: interceptFinalSubmit,
        application_answers_attempted: applicationAnswers.attempted,
        application_answers_filled: applicationAnswers.filled,
        application_choice_answers_attempted: applicationAnswers.choice_attempted,
        application_choice_answers_filled: applicationAnswers.choice_filled,
        application_field_diagnostics: applicationAnswers.field_diagnostics || [],
        application_answer_items: applicationAnswers.items || [],
        page_title: await page.title().catch(() => ""),
        final_url: page.url(),
        local_screenshot_path: screenshotPath,
        missing_required_fields: missingRequiredQuestions,
        last_error:
          "The worker has not submitted this because required employer answers are missing: " +
          missingRequiredQuestions.slice(0, 10).join("; "),
        status: "review_required",
      };
    }

    const formCompletion = await getRequiredFormCompletionState(page).catch(() => ({
      complete_required_fields: [],
      missing_required_fields: [],
    }));
    debugLog(
      task.task_uuid || "task",
      "form_completion",
      `complete=${formCompletion.complete_required_fields.length}`,
      `missing=${formCompletion.missing_required_fields.length}`
    );
    if (allowFinalSubmit && formCompletion.missing_required_fields.length > 0) {
      return {
        provider: task.provider || "",
        url,
        allow_final_submit: allowFinalSubmit,
        clicked_submit: false,
        form_opened: formReady.opened,
        form_ready: formReady.ready,
        uploaded_resume: uploadedResume,
        browser_diagnostics: browserDiagnostics,
        intercept_final_submit: interceptFinalSubmit,
        application_answers_attempted: applicationAnswers.attempted,
        application_answers_filled: applicationAnswers.filled,
        application_choice_answers_attempted: applicationAnswers.choice_attempted,
        application_choice_answers_filled: applicationAnswers.choice_filled,
        application_field_diagnostics: applicationAnswers.field_diagnostics || [],
        application_answer_items: applicationAnswers.items || [],
        complete_required_fields: formCompletion.complete_required_fields,
        page_title: await page.title().catch(() => ""),
        final_url: page.url(),
        local_screenshot_path: screenshotPath,
        missing_required_fields: formCompletion.missing_required_fields,
        last_error:
          "The worker filled the available data but these required fields are still empty on the live employer form: " +
          formCompletion.missing_required_fields.slice(0, 10).join("; "),
        status: "review_required",
      };
    }

    const buildVerificationRequiredResult = async (message, submitState = {}) => {
      browserDiagnostics = await getBrowserEnvironmentDiagnostics(page).catch(() => browserDiagnostics || {});
      await page.screenshot({ path: screenshotPath, fullPage: true }).catch(() => {});
      return {
        provider: task.provider || "",
        url,
        allow_final_submit: allowFinalSubmit,
        clicked_submit: Boolean(submitState.clicked),
        form_opened: formReady.opened,
        form_ready: formReady.ready,
        submit_before_url: submitState.beforeUrl || page.url(),
        submit_after_url: submitState.afterUrl || page.url(),
        uploaded_resume: uploadedResume,
        browser_diagnostics: browserDiagnostics,
        intercept_final_submit: interceptFinalSubmit,
        application_answers_attempted: applicationAnswers.attempted,
        application_answers_filled: applicationAnswers.filled,
        application_choice_answers_attempted: applicationAnswers.choice_attempted,
        application_choice_answers_filled: applicationAnswers.choice_filled,
        application_field_diagnostics: applicationAnswers.field_diagnostics || [],
        application_answer_items: applicationAnswers.items || [],
        complete_required_fields: formCompletion.complete_required_fields,
        page_title: await page.title().catch(() => ""),
        final_url: page.url(),
        local_screenshot_path: screenshotPath,
        submission_confirmed: false,
        verification_required: true,
        verification_code_used: Boolean(submitState.verification_code_used),
        verification_code_filled: Boolean(submitState.verification_code_filled),
        verification_code_incorrect: Boolean(submitState.verification_code_used),
        intercepted_submit_request: submitState.intercepted_submit_request || null,
        validation_detected: Boolean(submitState.validation_detected),
        validation_errors: submitState.validation_errors || [],
        missing_required_fields: submitState.missing_required_fields || [],
        last_error: message,
        status: "verification_required",
      };
    };

    let clickedSubmit = false;
    let submitResult = { clicked: false, beforeUrl: page.url(), afterUrl: page.url() };
    let verificationAttempts = 0;
    const usedVerificationCodes = [];
    let verificationWaitTimedOut = false;
    if (allowFinalSubmit) {
      debugLog(task.task_uuid || "task", "click_submit");
      submitResult = await clickLikelyApplyButton(page);
      debugLog(
        task.task_uuid || "task",
        "submit_result",
        JSON.stringify({
          clicked: submitResult.clicked,
          confirmed: submitResult.submission_confirmed,
          intercepted: Boolean(submitResult.intercepted_submit_request),
          missing: (submitResult.missing_required_fields || []).length,
        })
      );
      clickedSubmit = submitResult.clicked;
      while (
        clickedSubmit &&
        !submitResult.submission_confirmed &&
        (await pageNeedsGreenhouseVerification(page).catch(() => false)) &&
        verificationAttempts < 3
      ) {
        let nextVerificationCode =
          verificationAttempts === 0 && verificationCode && !usedVerificationCodes.includes(verificationCode)
            ? verificationCode
            : "";
        if (!nextVerificationCode) {
          const message =
            verificationAttempts > 0
              ? "Greenhouse rejected that security code. Paste the latest code exactly as shown in the email, including capital letters."
              : "Greenhouse sent a security code to the candidate email. Paste the code here so the worker can resubmit the employer form in the same browser session.";
          await completeTask(
            task.task_uuid,
            "verification_required",
            await buildVerificationRequiredResult(message, submitResult)
          );
          nextVerificationCode = await waitForVerificationCode(task.task_uuid, usedVerificationCodes);
        }
        if (!nextVerificationCode) {
          verificationWaitTimedOut = true;
          break;
        }
        usedVerificationCodes.push(nextVerificationCode);
        verificationAttempts += 1;
        const filledVerificationCode = await fillGreenhouseVerificationCode(page, nextVerificationCode);
        if (filledVerificationCode) {
          const verificationSubmitResult = await clickLikelyApplyButton(page);
          submitResult = {
            ...verificationSubmitResult,
            verification_code_used: true,
            verification_code_filled: true,
            first_submit_result: submitResult,
          };
          clickedSubmit = verificationSubmitResult.clicked;
        } else {
          submitResult = {
            ...submitResult,
            verification_code_used: true,
            verification_code_filled: false,
          };
          break;
        }
      }
      await page.screenshot({ path: screenshotPath, fullPage: true }).catch(() => {});
      browserDiagnostics = await getBrowserEnvironmentDiagnostics(page).catch(() => browserDiagnostics || {});
    }

    let status = "dry_run_ready";
    let lastError = "";
    if (allowFinalSubmit) {
      const needsVerification = clickedSubmit && (await pageNeedsGreenhouseVerification(page).catch(() => false));
      const incorrectVerificationCode =
        Boolean(verificationCode) && (await pageHasIncorrectGreenhouseVerificationCode(page).catch(() => false));
      if (submitResult.submission_confirmed) {
        status = "submitted";
      } else if (needsVerification || incorrectVerificationCode) {
        status = "verification_required";
        lastError = verificationWaitTimedOut
          ? "Greenhouse sent a security code, but the worker did not receive a code before the active browser session timed out. Start the application again and paste the newest code as soon as it arrives."
          : incorrectVerificationCode
          ? "Greenhouse rejected that security code. Paste the latest code exactly as shown in the email, including capital letters."
          : "Greenhouse sent a security code to the candidate email. Enter the code in the chat so the worker can resubmit the employer form.";
      } else {
        status = "review_required";
        if (!clickedSubmit) {
          lastError = "The worker could not find a final submit button on the employer form.";
        } else if (
          submitResult.validation_detected ||
          (submitResult.validation_errors || []).length > 0 ||
          (submitResult.missing_required_fields || []).length > 0
        ) {
          const details = [
            ...(submitResult.validation_errors || []),
            ...(submitResult.missing_required_fields || []),
          ].slice(0, 10);
          lastError =
            "The employer form did not confirm submission and appears to need more information" +
            (details.length ? ": " + details.join("; ") : ".");
        } else {
          lastError =
            "The worker clicked submit, but the employer page did not show a clear submission confirmation.";
        }
      }
    }

    return {
      provider: task.provider || "",
      url,
      allow_final_submit: allowFinalSubmit,
      clicked_submit: clickedSubmit,
      form_opened: formReady.opened,
      form_ready: formReady.ready,
      submit_before_url: submitResult.beforeUrl,
      submit_after_url: submitResult.afterUrl,
      uploaded_resume: uploadedResume,
      browser_diagnostics: browserDiagnostics,
      intercept_final_submit: interceptFinalSubmit,
      application_answers_attempted: applicationAnswers.attempted,
      application_answers_filled: applicationAnswers.filled,
      application_choice_answers_attempted: applicationAnswers.choice_attempted,
      application_choice_answers_filled: applicationAnswers.choice_filled,
      application_field_diagnostics: applicationAnswers.field_diagnostics || [],
      application_answer_items: applicationAnswers.items || [],
      complete_required_fields: formCompletion.complete_required_fields,
      page_title: await page.title().catch(() => ""),
      final_url: page.url(),
      local_screenshot_path: screenshotPath,
      submission_confirmed: Boolean(submitResult.submission_confirmed),
      verification_required: status === "verification_required",
      verification_code_used: Boolean(submitResult.verification_code_used),
      verification_code_filled: Boolean(submitResult.verification_code_filled),
      verification_code_incorrect: Boolean(submitResult.verification_code_used) && status === "verification_required",
      intercepted_submit_request: submitResult.intercepted_submit_request || null,
      observed_post_requests: submitResult.observed_post_requests || [],
      validation_detected: Boolean(submitResult.validation_detected),
      validation_errors: submitResult.validation_errors || [],
      missing_required_fields: submitResult.missing_required_fields || [],
      last_error: lastError,
      status,
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
    console.log(
      `[${new Date().toISOString()}] ${task.task_uuid} ${result.status}` +
        ` answers=${result.application_answers_filled || 0}/${result.application_answers_attempted || 0}` +
        ` choices=${result.application_choice_answers_filled || 0}/${result.application_choice_answers_attempted || 0}` +
        ` missing=${(result.missing_required_fields || []).length}`
    );
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
          allow_workday_account_creation: allowWorkdayAccountCreation,
          allow_successfactors_account_creation: allowSuccessFactorsAccountCreation,
          intercept_final_submit: interceptFinalSubmit,
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

export {
  advanceWorkdaySteps,
  discoverWorkdayLiveSchema,
  discoverWorkableLiveSchema,
  extractWorkdayQuestionsFromJson,
  fillApplicationAnswers,
  extractSuccessFactorsProfileFromCv,
  extractCvDateRanges,
  extractWorkdayEducationEntriesFromCv,
  getSuccessFactorsVisibleState,
  getWorkdayStageFromState,
  getWorkdayStepState,
  isSuccessFactorsApplication,
  getMissingRequiredSchemaQuestions,
  getRequiredFormCompletionState,
  processSuccessFactorsTask,
  uploadWorkdayResume,
  processTask,
};

const isMainModule = process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url);
if (isMainModule) {
  main().catch((error) => {
    console.error(error);
    process.exit(1);
  });
}
