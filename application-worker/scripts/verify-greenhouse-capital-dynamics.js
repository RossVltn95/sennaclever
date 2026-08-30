import path from "node:path";
import fsSync from "node:fs";
import puppeteer from "puppeteer";

const url =
  process.argv[2] ||
  "https://job-boards.eu.greenhouse.io/capitaldynamicsag/jobs/4904305101";
const cvPath =
  process.argv[3] ||
  "/tmp/sffc-greenhouse-test-cv.pdf";

const answers = {
  first_name: "Luca",
  last_name: "Valentino Rosati",
  email: "greenhouse-dry-run@example.com",
  question_9413079101: "40000",
  question_9413080101: "2 months",
  question_9413081101: "Yes",
  question_9413082101: "Male",
  question_9413083101: "White",
  question_9413084101: "No",
  question_9413085101: "No",
  question_9413087101: "No",
  question_9413088101: "No",
  question_9413089101: "1",
  question_9413090101: "Yes",
  question_9413091101: "No",
  question_9413092101: "Bachelors",
  question_9413093101: "4",
  question_9413094101: "Yes",
};

const choices = {
  question_9413081101: ["Yes", "No"],
  question_9413082101: ["Male", "Female", "Non-Binary / Non-confirming", "Prefer not to Say"],
  question_9413083101: [
    "American Indian or Alaska Native",
    "Arab",
    "Asian",
    "Black or African American",
    "Hispanic or Latino",
    "Native Hawaiian or Other Pacific Islander",
    "White",
    "Two or More Races",
  ],
  question_9413084101: ["Yes", "No", "Prefer Not to Say"],
  question_9413085101: ["Yes", "No"],
  question_9413087101: ["Yes", "No"],
  question_9413088101: ["Yes", "No"],
  question_9413089101: ["1 Language", "1-2 Languages", "2-4 Languages", "4+ Languages"],
  question_9413090101: ["Yes", "No"],
  question_9413091101: ["Yes", "No"],
  question_9413092101: [
    "Doctorate (PHD or Equivalent)",
    "Masters Degree (MA/MSc or Equivalent)",
    "Bachelors Degree (BA/BSc or Equivalent)",
    "College Diploma or Equivalent",
    "Secondary School or Equivalent",
    "Apprenticeship or Vocational Qualification",
    "No Formal Education",
    "Other",
    "Prefer not to Say",
  ],
  question_9413094101: ["Yes", "No"],
};

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
  return [
    "/usr/bin/google-chrome-stable",
    "/usr/bin/google-chrome",
    "/root/.nix-profile/bin/chromium",
    "/nix/var/nix/profiles/default/bin/chromium",
    "/usr/bin/chromium",
    "/usr/bin/chromium-browser",
  ].find(isUsableBrowserExecutable);
}

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function scoreChoice(answer, candidate) {
  const normalize = (value) => String(value || "").replace(/\s+/g, " ").trim().toLowerCase();
  const compact = (value) => normalize(value).replace(/[^a-z0-9]+/g, "");
  const wanted = normalize(answer);
  const option = normalize(candidate);
  const compactWanted = compact(answer);
  const compactOption = compact(candidate);
  if (!wanted || !option) return 0;
  if (option === wanted || compactOption === compactWanted) return 100;
  if (/^\d+$/.test(compactWanted) && compactOption.startsWith(compactWanted)) return 92;
  if (compactWanted && compactOption.includes(compactWanted)) return 86;
  if (compactWanted && compactWanted.includes(compactOption)) return 82;
  if (option.includes(wanted)) return 78;
  if (wanted.includes(option)) return 74;
  return 0;
}

function bestChoice(answer, optionLabels) {
  return (
    optionLabels
      .map((choice) => ({ choice, score: scoreChoice(answer, choice) }))
      .filter((entry) => entry.score > 0)
      .sort((a, b) => b.score - a.score)[0]?.choice || answer
  );
}

async function setInput(page, id, value) {
  const selector = `#${id}`;
  const exists = await page.$(selector);
  if (!exists) {
    return { id, ok: false, reason: "missing" };
  }
  await page.$eval(
    selector,
    (input, text) => {
      const descriptor = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, "value");
      input.scrollIntoView({ block: "center" });
      input.focus();
      if (descriptor && descriptor.set) {
        descriptor.set.call(input, text);
      } else {
        input.value = text;
      }
      input.dispatchEvent(new Event("input", { bubbles: true }));
      input.dispatchEvent(new Event("change", { bubbles: true }));
      input.dispatchEvent(new Event("blur", { bubbles: true }));
    },
    value
  );
  await sleep(100);
  const actual = await page.$eval(selector, (input) => input.value || "");
  return { id, ok: actual === value, actual };
}

async function chooseGreenhouseSelect(page, id, answer) {
  const wanted = bestChoice(answer, choices[id] || []);
  return page.evaluate(
    async ({ fieldId, label }) => {
      const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
      const normalize = (value) => clean(value).toLowerCase();
      const compact = (value) => normalize(value).replace(/[^a-z0-9]+/g, "");
      const score = (candidate) => {
        const wantedNorm = normalize(label);
        const optionNorm = normalize(candidate);
        const wantedCompact = compact(label);
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
      const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
      const dispatchClick = (element) => {
        element.scrollIntoView({ block: "center" });
        element.dispatchEvent(new MouseEvent("mousedown", { bubbles: true, cancelable: true, view: window }));
        element.dispatchEvent(new MouseEvent("mouseup", { bubbles: true, cancelable: true, view: window }));
        element.click();
      };
      const input = document.getElementById(fieldId);
      if (!input) {
        return { id: fieldId, ok: false, reason: "missing" };
      }
      const shell = input.closest(".select-shell") || input.closest(".select");
      const control = shell?.querySelector(".select__control") || input;
      for (let attempt = 0; attempt < 3; attempt += 1) {
        dispatchClick(control);
        input.focus();
        await sleep(350);
        const options = Array.from(document.querySelectorAll(`[id^="react-select-${CSS.escape(fieldId)}-option-"], [role='option']`))
          .map((node) => ({ node, text: clean(node.textContent || node.getAttribute("aria-label") || "") }))
          .filter((entry) => entry.text)
          .map((entry) => ({ ...entry, score: score(entry.text) }))
          .filter((entry) => entry.score > 0)
          .sort((a, b) => b.score - a.score);
        if (!options[0]) {
          continue;
        }
        dispatchClick(options[0].node);
        await sleep(350);
        const selected = clean(
          shell?.querySelector(".select__single-value, [class*='singleValue'], [class*='single-value' i]")?.textContent || ""
        );
        if (score(selected) > 0) {
          return { id: fieldId, ok: true, selected };
        }
      }
      const selected = clean(
        shell?.querySelector(".select__single-value, [class*='singleValue'], [class*='single-value' i]")?.textContent || ""
      );
      return { id: fieldId, ok: false, selected };
    },
    { fieldId: id, label: wanted }
  );
}

async function requiredState(page) {
  return page.evaluate(() => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    return Array.from(document.querySelectorAll("[aria-required='true'], input[required], textarea[required], select[required]"))
      .filter((control) => control.type !== "hidden" && !control.disabled)
      .map((control) => {
        const id = control.id || "";
        const label = clean(document.querySelector(`label[for="${CSS.escape(id)}"]`)?.textContent || control.getAttribute("aria-label") || id);
        const shell = control.closest(".select-shell") || control.closest(".select");
        const selected = clean(shell?.querySelector(".select__single-value, [class*='singleValue'], [class*='single-value' i]")?.textContent || "");
        const value = clean(control.value || selected);
        const ok = control.type === "file" ? Boolean(control.files && control.files.length) : value !== "" && !/^select/i.test(value);
        return { id, label, ok, value: value || selected };
      });
  });
}

async function main() {
  if (!fsSync.existsSync(cvPath)) {
    fsSync.writeFileSync(cvPath, "%PDF-1.4\n% dry run placeholder\n");
  }
  const executablePath = getBrowserExecutablePath();
  const browser = await puppeteer.launch({
    headless: "new",
    executablePath: executablePath || undefined,
    args: ["--no-sandbox", "--disable-setuid-sandbox"],
  });
  const page = await browser.newPage();
  await page.setViewport({ width: 1440, height: 1200 });
  await page.goto(url, { waitUntil: "networkidle2", timeout: 60000 });

  const inputResults = [];
  for (const [id, value] of Object.entries(answers)) {
    if (choices[id]) {
      continue;
    }
    inputResults.push(await setInput(page, id, value));
  }

  const resumeInput = await page.$("#resume");
  const uploadedResume = Boolean(resumeInput);
  if (resumeInput) {
    await resumeInput.uploadFile(path.resolve(cvPath));
  }

  const selectResults = [];
  for (const [id, optionLabels] of Object.entries(choices)) {
    selectResults.push(await chooseGreenhouseSelect(page, id, answers[id] || optionLabels[0]));
  }

  const fields = await requiredState(page);
  const screenshotPath = "/tmp/sffc-greenhouse-verify-capital-dynamics.png";
  await page.screenshot({ path: screenshotPath, fullPage: true });
  await browser.close();

  const missing = fields.filter((field) => !field.ok);
  console.log(
    JSON.stringify(
      {
        url,
        uploadedResume,
        inputResults,
        selectResults,
        requiredFields: fields,
        missing,
        screenshotPath,
        success: missing.length === 0 && selectResults.every((result) => result.ok),
      },
      null,
      2
    )
  );
  process.exit(missing.length === 0 && selectResults.every((result) => result.ok) ? 0 : 1);
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
