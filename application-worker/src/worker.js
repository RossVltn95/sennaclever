import fs from "node:fs/promises";
import fsSync from "node:fs";
import http from "node:http";
import os from "node:os";
import path from "node:path";
import { fileURLToPath } from "node:url";
import puppeteer from "puppeteer";

const ajaxUrl = process.env.SFFC_WP_AJAX_URL || "";
const workerToken = process.env.SFFC_APPLICATION_WORKER_TOKEN || "";
const workerId = process.env.SFFC_WORKER_ID || `sffc-worker-${os.hostname()}`;
const pollIntervalMs = Number(process.env.SFFC_WORKER_POLL_INTERVAL_MS || 15000);
const verificationWaitMs = Number(process.env.SFFC_WORKER_VERIFICATION_WAIT_MS || 10 * 60 * 1000);
const browserLaunchTimeoutMs = Number(process.env.SFFC_BROWSER_LAUNCH_TIMEOUT_MS || 90000);
const navigationTimeoutMs = Number(process.env.SFFC_BROWSER_NAVIGATION_TIMEOUT_MS || 90000);
const allowFinalSubmit = process.env.SFFC_WORKER_ALLOW_FINAL_SUBMIT === "1";
const interceptFinalSubmit = process.env.SFFC_WORKER_INTERCEPT_FINAL_SUBMIT === "1";
const healthPort = Number(process.env.PORT || 0);
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
    await field.evaluate((element) => element.scrollIntoView({ block: "center" })).catch(() => {});
    await field.click({ clickCount: 3 }).catch(() => {});
    await field.type(String(value), { delay: 10 }).catch(() => {});
    await field
      .evaluate((element, text) => {
        const prototype =
          element instanceof HTMLTextAreaElement ? HTMLTextAreaElement.prototype : HTMLInputElement.prototype;
        const descriptor = Object.getOwnPropertyDescriptor(prototype, "value");
        element.focus();
        if (descriptor && descriptor.set) {
          descriptor.set.call(element, text);
        } else {
          element.value = text;
        }
        element.dispatchEvent(new Event("input", { bubbles: true }));
        element.dispatchEvent(new Event("change", { bubbles: true }));
        element.dispatchEvent(new Event("blur", { bubbles: true }));
      }, String(value))
      .catch(() => {});
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
    return true;
  }
  return false;
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
          required: questionIsRequired(question) || questionIsRequired(match),
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
  const matched = Object.entries(answers || {}).find(([key, value]) => {
    if (!answerHasValue(value)) {
      return false;
    }
    const keyCompact = compact(key);
    return keyCompact && (labelKey.includes(keyCompact) || keyCompact.includes(labelKey));
  });
  return matched ? matched[1] : "";
}

async function fillApplicationAnswers(page, task, overrideSchema = null) {
  const schema = getApplicationSchema(task);
  const answers = getApplicationAnswers(task);
  const isWorkable = isWorkableApplication(task);
  const questions = isWorkable && overrideSchema
    ? mergeQuestionsByFieldOrLabel(getSchemaQuestions(overrideSchema), getSchemaQuestions(schema))
    : getSchemaQuestions(schema);
  if (!questions.length || !Object.keys(answers).length) {
    return { attempted: 0, filled: 0, choice_attempted: 0, choice_filled: 0, items: [] };
  }

  const fillItems = questions
    .map((question) => {
      const label = getQuestionLabel(question);
      const key = getQuestionFieldNames(question)[0] || label.toLowerCase();
      const answer = getAnswerForQuestion(question, answers) || answers[key] || answers[String(key).toLowerCase()] || answers[label.toLowerCase()];
      const choices = getQuestionChoiceLabels(question);
      return {
        label,
        fieldNames: getQuestionFieldNames(question),
        fieldTypes: getQuestionFieldTypes(question),
        choices,
        choiceLike: questionLooksChoiceBased(question),
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

  let filled = await page.evaluate(async (items) => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const scoreText = (value) => clean(value).toLowerCase();
    const compactText = (value) => scoreText(value).replace(/[^a-z0-9]+/g, "");
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
      const options = Array.from(select.options || []);
      const option = options.find((candidate) => {
        const label = scoreText(candidate.textContent || candidate.label || "");
        const value = scoreText(candidate.value || "");
        const compactLabel = compactText(candidate.textContent || candidate.label || "");
        const compactValue = compactText(candidate.value || "");
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
      const option = options.find((candidate) => {
        const text = scoreText(candidate.textContent || candidate.getAttribute("aria-label") || "");
        const compact = compactText(candidate.textContent || candidate.getAttribute("aria-label") || "");
        return (
          text === wanted ||
          compact === compactWanted ||
          (compactWanted && compact && compact.includes(compactWanted)) ||
          (compactWanted && compact && compactWanted.includes(compact))
        );
      });
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
        descriptor.set.call(element, value);
      } else {
        element.value = value;
      }
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
  await element.evaluate((control) => control.scrollIntoView({ block: "center", inline: "nearest" })).catch(() => {});
  await element.click({ clickCount: 3 }).catch(() => {});
  const modifier = process.platform === "darwin" ? "Meta" : "Control";
  await page.keyboard.down(modifier).catch(() => {});
  await page.keyboard.press("KeyA").catch(() => {});
  await page.keyboard.up(modifier).catch(() => {});
  await page.keyboard.type(cleanText(item.answer), { delay: 20 }).catch(() => {});
  await page.keyboard.press("Tab").catch(() => {});
  await new Promise((resolve) => setTimeout(resolve, 250));
  return element.evaluate((control) => String(control.value || "").trim() !== "").catch(() => false);
}

async function fillChoiceByVisibleQuestion(page, item) {
  return page.evaluate((target) => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const normalize = (value) => clean(value).toLowerCase();
    const compact = (value) => normalize(value).replace(/[^a-z0-9]+/g, "");
    const wantedLabel = compact(target.label || "");
    const answer = clean(target.answer || "");
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
    const isVisible = (element) => {
      if (!element || element.disabled || element.type === "hidden") return false;
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
      if (!element || element.disabled || element.type === "hidden") return false;
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
    } else if (/date of birth|birth date|\bdob\b/i.test(item.label || "")) {
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
          hiddenSelected ||
          selected !== "" ||
          (controlValue !== "" && !/^select/i.test(controlValue) && controlValue !== placeholder);
      } else if (control.type === "file") {
        hasValue = Boolean(control.files && control.files.length);
      } else {
        const name = control.getAttribute("name") || "";
        const rect = control.getBoundingClientRect();
        const pairedWorkableCombobox = name ? document.getElementById(`input_${name}_input`) : null;
        const pairedWorkableComboboxValue = clean(pairedWorkableCombobox && pairedWorkableCombobox.value);
        if (pairedWorkableCombobox && rect.width <= 2 && rect.height <= 2) {
          hasValue = pairedWorkableComboboxValue !== "" && !/^select/i.test(pairedWorkableComboboxValue);
        } else {
        hasValue =
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
    url: request.url(),
    method: request.method(),
    post_data_length: postData.length,
    top_level_keys: [],
    has_job_application: false,
    candidate_fields_present: {
      first_name: false,
      last_name: false,
      email: false,
      phone: false,
      resume: false,
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
    summary.candidate_fields_present = {
      first_name: /first[_-]?name/i.test(serialized),
      last_name: /last[_-]?name|surname|family[_-]?name/i.test(serialized),
      email: /email/i.test(serialized),
      phone: /phone|mobile|telephone/i.test(serialized),
      resume: /resume|cv|attachment/i.test(serialized),
    };
    const questionFields = Array.from(new Set(serialized.match(/question_\d+|\b\d{8,}\b/g) || []));
    summary.question_field_count = questionFields.length;
    summary.question_fields = questionFields.slice(0, 40);
  } catch (error) {
    summary.parse_error = error && error.message ? error.message : String(error);
    summary.post_data_sample = postData.slice(0, 240);
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
  debugLog(task.task_uuid || "task", "cv_downloaded", Boolean(cvPath));
  const executablePath = getBrowserExecutablePath();
  debugLog(task.task_uuid || "task", "launching_browser", executablePath || "puppeteer-managed");
  const browser = await puppeteer.launch({
    headless: "new",
    executablePath: executablePath || undefined,
    timeout: browserLaunchTimeoutMs,
    args: ["--no-sandbox", "--disable-setuid-sandbox"],
  });

  try {
    const page = await browser.newPage();
    await page.setViewport({ width: 1440, height: 1200 });
    debugLog(task.task_uuid || "task", "goto", url);
    await page.goto(url, { waitUntil: "domcontentloaded", timeout: navigationTimeoutMs });
    await page.waitForNetworkIdle({ idleTime: 1000, timeout: 20000 }).catch(() => {});
    debugLog(task.task_uuid || "task", "form_ready_check");
    const formReady = await ensureApplicationFormReady(page);
    const dismissedCookies = await dismissCookieBanners(page);
    debugLog(task.task_uuid || "task", "cookies_dismissed", Boolean(dismissedCookies));
    debugLog(task.task_uuid || "task", "form_ready", JSON.stringify(formReady));

    debugLog(task.task_uuid || "task", "fill_core_first_name");
    const filledFirstName = await withTimeout(fillBySelectors(page, [
      "#first_name",
      "#firstname",
      'input[name="first_name"]',
      'input[name="firstname"]',
      'input[name="firstName"]',
      'input[name*="first" i]',
    ], firstName), 7000, false);
    debugLog(task.task_uuid || "task", "fill_core_last_name");
    const filledLastName = await withTimeout(fillBySelectors(page, [
      "#last_name",
      "#lastname",
      'input[name="last_name"]',
      'input[name="lastname"]',
      'input[name="lastName"]',
      'input[name*="last" i]',
    ], lastName), 7000, false);
    debugLog(task.task_uuid || "task", "fill_core_email");
    const filledEmail = await withTimeout(fillBySelectors(page, [
      "#email",
      'input[type="email"]',
      'input[name="email"]',
      'input[name*="email" i]',
    ], candidate.email), 7000, false);
    debugLog(task.task_uuid || "task", "fill_core_phone");
    const filledPhone = await withTimeout(fillBySelectors(page, [
      "#phone",
      'input[type="tel"]',
      'input[name*="phone" i]',
      'input[name*="mobile" i]',
    ], candidate.phone), 7000, false);
    debugLog(task.task_uuid || "task", "fill_core_address");
    const filledAddress = await withTimeout(fillBySelectors(page, [
      "#address",
      'input[name="address"]',
      'input[name*="address" i]',
      'input[name*="location" i]',
      'input[name*="city" i]',
    ], candidate.address), 7000, false);

    debugLog(task.task_uuid || "task", "fill_core_label_fallbacks");
    if (!filledFirstName) {
      await withTimeout(fillByLabelText(page, ["first name", "given name"], firstName), 7000, false);
    }
    if (!filledLastName) {
      await withTimeout(fillByLabelText(page, ["last name", "family name", "surname"], lastName), 7000, false);
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
    if (isWorkableApplication(task, url)) {
      debugLog(task.task_uuid || "task", "workable_core_repair");
      await withTimeout(fillWorkableCoreTextByVisualLabel(page, [
        { key: "first_name", patterns: ["^\\*?\\s*first\\s*name\\b"], value: firstName },
        { key: "last_name", patterns: ["^\\*?\\s*last\\s*name\\b"], value: lastName },
      ]), 7000, {});
    }
    debugLog(task.task_uuid || "task", "upload_resume");
    const uploadedResume = await uploadResume(page, cvPath);
    let liveApplicationSchema = null;
    if (isWorkableApplication(task, url)) {
      const clickedResumeImport = await withTimeout(clickWorkableResumeImport(page), 5000, false);
      debugLog(task.task_uuid || "task", "workable_resume_import_clicked", Boolean(clickedResumeImport));
      if (clickedResumeImport) {
        await page.waitForNetworkIdle({ idleTime: 1000, timeout: 12000 }).catch(() => {});
        await new Promise((resolve) => setTimeout(resolve, 1000));
      }
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
  discoverWorkableLiveSchema,
  fillApplicationAnswers,
  getMissingRequiredSchemaQuestions,
  getRequiredFormCompletionState,
  processTask,
};

const isMainModule = process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url);
if (isMainModule) {
  main().catch((error) => {
    console.error(error);
    process.exit(1);
  });
}
