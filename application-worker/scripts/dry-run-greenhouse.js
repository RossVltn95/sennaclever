import path from "node:path";
import fsSync from "node:fs";
import puppeteer from "puppeteer";

const url =
  process.argv[2] ||
  "https://job-boards.eu.greenhouse.io/capitaldynamicsag/jobs/4904305101";
const cvPath =
  process.argv[3] ||
  "/Users/ropafadzoyasheushe/Downloads/CVs/67162bdaab7d6.pdf";

const textAnswers = {
  first_name: "Jamie",
  last_name: "Clements",
  email: "dryrun@example.com",
  phone: "+447700900000",
  question_9413077101: "https://www.linkedin.com/in/dry-run-candidate",
  question_9413078101: "GBP 35,000",
  question_9413079101: "GBP 40,000",
  question_9413080101: "1 month",
  question_9413093101: "5",
};

const selectAnswers = {
  question_9413081101: "Yes",
  question_9413082101: "Male",
  question_9413083101: "Asian",
  question_9413084101: "No",
  question_9413085101: "No",
  question_9413087101: "No",
  question_9413088101: "No",
  question_9413089101: "2-4 Languages",
  question_9413090101: "Yes",
  question_9413091101: "No",
  question_9413092101: "Bachelors Degree (BA/BSc or Equivalent)",
  question_9413094101: "Yes",
};

function getBrowserExecutablePath() {
  const configuredPath = process.env.PUPPETEER_EXECUTABLE_PATH || "";
  if (configuredPath && fsSync.existsSync(configuredPath)) {
    return configuredPath;
  }

  const candidates = [
    "/usr/bin/chromium",
    "/usr/bin/chromium-browser",
    "/usr/bin/google-chrome",
    "/usr/bin/google-chrome-stable",
  ];
  return candidates.find((candidate) => fsSync.existsSync(candidate));
}

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function idSelector(id) {
  return `#${String(id).replace(/[^a-zA-Z0-9_-]/g, "\\$&")}`;
}

async function fillInput(page, id, value) {
  const selector = idSelector(id);
  const exists = await page.$(selector);
  if (!exists) {
    return { id, ok: false, reason: "missing" };
  }
  await page.focus(selector);
  await page.keyboard.down(process.platform === "darwin" ? "Meta" : "Control");
  await page.keyboard.press("A");
  await page.keyboard.up(process.platform === "darwin" ? "Meta" : "Control");
  await page.keyboard.type(value, { delay: 8 });
  const actual = await page.$eval(selector, (input) => input.value);
  return { id, ok: actual === value, actual };
}

async function chooseReactSelect(page, id, value) {
  const selector = idSelector(id);
  const exists = await page.$(selector);
  if (!exists) {
    return { id, ok: false, reason: "missing" };
  }
  await page.click(selector);
  await page.keyboard.type(value, { delay: 10 });
  await page.keyboard.press("Enter");
  await sleep(250);
  const selected = await page.evaluate((inputId) => {
    const input = document.getElementById(inputId);
    const shell = input && input.closest(".select-shell");
    return shell ? shell.textContent.replace(/\s+/g, " ").trim() : "";
  }, id);
  return {
    id,
    ok: selected.toLowerCase().includes(value.toLowerCase().replace(/\s+/g, " ").trim()),
    selected,
  };
}

async function main() {
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
  for (const [id, value] of Object.entries(textAnswers)) {
    inputResults.push(await fillInput(page, id, value));
  }

  let uploadedResume = false;
  const resumeInput = await page.$("#resume");
  if (resumeInput) {
    await resumeInput.uploadFile(path.resolve(cvPath));
    uploadedResume = true;
  }

  const selectResults = [];
  for (const [id, value] of Object.entries(selectAnswers)) {
    selectResults.push(await chooseReactSelect(page, id, value));
  }

  const submitState = await page.evaluate(() => {
    const buttons = Array.from(document.querySelectorAll("button, input[type=submit]"));
    return buttons.map((button) => ({
      text: `${button.textContent || ""} ${button.getAttribute("value") || ""}`.replace(/\s+/g, " ").trim(),
      disabled: Boolean(button.disabled || button.getAttribute("aria-disabled") === "true"),
      type: button.getAttribute("type") || "",
    }));
  });

  const screenshotPath = "/tmp/sffc-greenhouse-dry-run-capital-dynamics.png";
  await page.screenshot({ path: screenshotPath, fullPage: true });
  await browser.close();

  console.log(
    JSON.stringify(
      {
        url,
        title: "Associate, Clean Energy",
        uploadedResume,
        inputResults,
        selectResults,
        submitState,
        screenshotPath,
        wouldSubmit: false,
      },
      null,
      2
    )
  );
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
