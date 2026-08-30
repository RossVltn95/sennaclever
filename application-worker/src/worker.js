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
const allowFinalSubmit = process.env.SFFC_WORKER_ALLOW_FINAL_SUBMIT === "1";
const interceptFinalSubmit = process.env.SFFC_WORKER_INTERCEPT_FINAL_SUBMIT === "1";
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

function cleanText(value) {
  return String(value || "").replace(/\s+/g, " ").trim();
}

function cssIdSelector(id) {
  return `#${String(id || "").replace(/[^a-zA-Z0-9_-]/g, "\\$&")}`;
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
        choice?.label ||
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
    return true;
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

function getMissingRequiredSchemaQuestions(task, candidate, hasResume) {
  const questions = getSchemaQuestions(getApplicationSchema(task));
  const answers = getApplicationAnswers(task);
  const coverLetterRequested = Number(task.cover_letter_requested || 0) === 1;
  return questions
    .filter((question) => questionIsRequired(question))
    .filter(
      (question) =>
        !isCoveredByCandidateData(question, candidate, hasResume, coverLetterRequested) &&
        !hasAnswerForQuestion(question, answers)
    )
    .map((question) => getQuestionLabel(question) || getQuestionFieldNames(question).join(", "))
    .filter(Boolean);
}

async function fillApplicationAnswers(page, task) {
  const schema = getApplicationSchema(task);
  const answers = getApplicationAnswers(task);
  const questions = getSchemaQuestions(schema);
  if (!questions.length || !Object.keys(answers).length) {
    return { attempted: 0, filled: 0, choice_attempted: 0, choice_filled: 0, items: [] };
  }

  const fillItems = questions
    .map((question) => {
      const label = getQuestionLabel(question);
      const key = getQuestionFieldNames(question)[0] || label.toLowerCase();
      const answer = answers[key] ?? answers[String(key).toLowerCase()] ?? answers[label.toLowerCase()];
      const choices = getQuestionChoiceLabels(question);
      return {
        label,
        fieldNames: getQuestionFieldNames(question),
        fieldTypes: getQuestionFieldTypes(question),
        choices,
        choiceLike: questionLooksChoiceBased(question),
        answer: cleanText(answer),
      };
    })
    .filter((item) => item.answer && (item.fieldNames.length || item.label));

  if (!fillItems.length) {
    return { attempted: 0, filled: 0, choice_attempted: 0, choice_filled: 0, items: [] };
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

    const wanted = compact(target.label || "");
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
      if (await selectGreenhouseReactSelect(page, item)) {
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

    const looksLikeChoice =
      item.choices.length > 0 ||
      meta.role === "combobox" ||
      meta.ariaAutocomplete === "list" ||
      meta.ariaHaspopup === "listbox" ||
      meta.ariaHaspopup === "true";
    if (looksLikeChoice) {
      if (await selectInteractiveOption(page, element, item)) {
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
      return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
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
        hasValue = clean(control.value) !== "";
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

async function clickLikelyApplyButton(page) {
  const beforeUrl = page.url();
  let interceptedSubmitRequest = null;
  let requestHandler = null;
  if (interceptFinalSubmit) {
    await page.setRequestInterception(true);
    requestHandler = (request) => {
      const method = request.method();
      const postData = request.postData() || "";
      const url = request.url();
      const looksLikeApplicationSubmit =
        method === "POST" &&
        (/job_application/i.test(postData) ||
          /greenhouse/i.test(url) ||
          /\/jobs\/\d+/.test(url) ||
          /\/applications/.test(url));
      if (looksLikeApplicationSubmit && !interceptedSubmitRequest) {
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
    ...submissionState,
  };
}

async function processTask(task) {
  const url = task.application_workspace_url || task.application_url;
  const verificationCode = getVerificationCode(task);
  const candidate = {
    name: task.candidate_name || "",
    email: task.candidate_email || "",
    phone: task.candidate_phone || "",
  };
  const { firstName, lastName } = splitName(candidate.name);
  candidate.firstName = firstName;
  candidate.lastName = lastName;
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
    const formReady = await ensureApplicationFormReady(page);

    await fillBySelectors(page, [
      "#first_name",
      'input[name="first_name"]',
      'input[name="firstName"]',
      'input[name*="first" i]',
    ], firstName);
    await fillBySelectors(page, [
      "#last_name",
      'input[name="last_name"]',
      'input[name="lastName"]',
      'input[name*="last" i]',
    ], lastName);
    await fillBySelectors(page, [
      "#email",
      'input[type="email"]',
      'input[name="email"]',
      'input[name*="email" i]',
    ], candidate.email);
    await fillBySelectors(page, [
      "#phone",
      'input[type="tel"]',
      'input[name*="phone" i]',
      'input[name*="mobile" i]',
    ], candidate.phone);

    await fillByLabelText(page, ["first name", "given name"], firstName);
    await fillByLabelText(page, ["last name", "family name", "surname"], lastName);
    await fillByLabelText(page, ["email"], candidate.email);
    await fillByLabelText(page, ["phone", "mobile"], candidate.phone);
    const uploadedResume = await uploadResume(page, cvPath);
    const applicationAnswers = await fillApplicationAnswers(page, task);
    let browserDiagnostics = await getBrowserEnvironmentDiagnostics(page).catch(() => ({}));

    const screenshotPath = path.join(os.tmpdir(), `${task.task_uuid}.png`);
    await page.screenshot({ path: screenshotPath, fullPage: true });

    const missingRequiredQuestions = getMissingRequiredSchemaQuestions(task, candidate, Boolean(cvPath));
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

    let clickedSubmit = false;
    let submitResult = { clicked: false, beforeUrl: page.url(), afterUrl: page.url() };
    if (allowFinalSubmit) {
      submitResult = await clickLikelyApplyButton(page);
      clickedSubmit = submitResult.clicked;
      if (clickedSubmit && verificationCode && (await pageNeedsGreenhouseVerification(page).catch(() => false))) {
        const filledVerificationCode = await fillGreenhouseVerificationCode(page, verificationCode);
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
        lastError = incorrectVerificationCode
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
