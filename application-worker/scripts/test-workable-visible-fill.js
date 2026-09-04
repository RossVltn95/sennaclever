import puppeteer from "puppeteer";

const url =
  process.env.SFFC_WORKABLE_TEST_URL ||
  "https://apply.workable.com/qiddiya-investment-company-1/j/F2F2483923/apply/";

const answers = [
  { label: "First name", answer: "Luca" },
  { label: "Last name", answer: "Rosati" },
  { label: "Email", answer: "workable-dry-run@example.com" },
  { label: "Phone", answer: "+447911123456" },
  { label: "Address", answer: "Dubai, United Arab Emirates" },
  { label: "Highest Education Level", answer: "Bachelor Degree" },
  { label: "politically exposed person", answer: "No" },
  { label: "conflict of interest", answer: "No" },
  { label: "Current Location", answer: "Dubai, UAE" },
  { label: "Date of Birth", answer: "01/01/1990" },
  { label: "Expected Salary", answer: "SAR 45000" },
  { label: "Current monthly salary", answer: "SAR 35000" },
  { label: "Salutation", answer: "Mr" },
  { label: "Nationality", answer: "United Kingdom" },
  { label: "Years of relevant experience", answer: "8" },
  { label: "delivery partner", answer: "No" },
  { label: "privacy notice", answer: "Yes" },
  { label: "terms of use", answer: "Yes" },
];

const browser = await puppeteer.launch({
  headless: "new",
  timeout: Number(process.env.SFFC_BROWSER_LAUNCH_TIMEOUT_MS || 90000),
  executablePath: process.env.PUPPETEER_EXECUTABLE_PATH || undefined,
  args: ["--no-sandbox", "--disable-setuid-sandbox"],
});

function norm(value) {
  return String(value || "").replace(/\s+/g, " ").trim().toLowerCase();
}

async function clickCookie(page) {
  await page.evaluate(() => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const button = Array.from(document.querySelectorAll("button, a")).find((node) =>
      /^(accept all|accept|allow all|agree|ok)$/i.test(clean(node.textContent || node.getAttribute("aria-label") || ""))
    );
    if (button) button.click();
  }).catch(() => {});
}

async function getVisibleControls(page) {
  return page.evaluate(() => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const visible = (node) => {
      if (!node) return false;
      const style = window.getComputedStyle(node);
      const rect = node.getBoundingClientRect();
      return style.display !== "none" && style.visibility !== "hidden" && rect.width > 0 && rect.height > 0;
    };
    return Array.from(document.querySelectorAll("input, textarea, button, [role='button'], [role='combobox'], label"))
      .filter(visible)
      .map((node) => {
        const rect = node.getBoundingClientRect();
        const id = node.getAttribute("id") || "";
        const label = id ? document.querySelector(`label[for="${CSS.escape(id)}"]`) : null;
        const scope = node.closest("fieldset, section, li, div") || node.parentElement;
        return {
          tag: node.tagName,
          type: node.getAttribute("type") || "",
          role: node.getAttribute("role") || "",
          id,
          name: node.getAttribute("name") || "",
          checked: Boolean(node.checked),
          value: node.type === "file" ? "" : clean(node.value || ""),
          text: clean(`${label?.textContent || ""} ${node.textContent || ""} ${node.getAttribute("placeholder") || ""} ${node.getAttribute("aria-label") || ""}`),
          scope: clean(scope?.textContent || "").slice(0, 240),
          x: Math.round(rect.left + rect.width / 2),
          y: Math.round(rect.top + rect.height / 2),
          width: Math.round(rect.width),
          height: Math.round(rect.height),
        };
      });
  });
}

async function clickBestVisibleText(page, label, answer) {
  const point = await page.evaluate(
    ({ label, answer }) => {
      const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
      const compact = (value) => clean(value).toLowerCase().replace(/[^a-z0-9]+/g, "");
      const wantedLabel = compact(label);
      const wantedAnswer = compact(answer);
      const visible = (node) => {
        if (!node) return false;
        const style = window.getComputedStyle(node);
        const rect = node.getBoundingClientRect();
        return style.display !== "none" && style.visibility !== "hidden" && rect.width > 0 && rect.height > 0;
      };
      const scoreAnswer = (text) => {
        const c = compact(text);
        if (!c || !wantedAnswer) return 0;
        if (c === wantedAnswer) return 100;
        if (/^\d+$/.test(wantedAnswer) && c.startsWith(wantedAnswer)) return 92;
        if (wantedAnswer.length >= 4 && c.includes(wantedAnswer)) return 86;
        if (c.length >= 4 && wantedAnswer.includes(c)) return 82;
        return 0;
      };
      const fieldScopes = Array.from(document.querySelectorAll("fieldset, section, li, div"))
        .filter((scope) => {
          const text = compact(scope.textContent || "");
          return text && text.includes(wantedLabel) && scope.querySelector("input, button, [role='button'], [role='combobox']");
        })
        .sort((a, b) => clean(a.textContent || "").length - clean(b.textContent || "").length);
      for (const scope of fieldScopes) {
        const candidates = Array.from(scope.querySelectorAll("label, button, [role='button'], [role='radio'], [role='checkbox'], span, div"))
          .filter(visible)
          .map((node) => ({ node, score: scoreAnswer(node.textContent || node.getAttribute("aria-label") || "") }))
          .filter((item) => item.score >= 82)
          .sort((a, b) => b.score - a.score);
        if (candidates[0]) {
          const rect = candidates[0].node.getBoundingClientRect();
          return { x: Math.round(rect.left + rect.width / 2), y: Math.round(rect.top + rect.height / 2), text: clean(candidates[0].node.textContent || "") };
        }
      }
      return null;
    },
    { label, answer }
  );
  if (!point) return false;
  await page.mouse.move(point.x, point.y, { steps: 8 });
  await page.mouse.click(point.x, point.y, { delay: 80 });
  await new Promise((resolve) => setTimeout(resolve, 350));
  return true;
}

async function typeBestVisibleField(page, label, answer) {
  const point = await page.evaluate(
    ({ label }) => {
      const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
      const compact = (value) => clean(value).toLowerCase().replace(/[^a-z0-9]+/g, "");
      const wantedLabel = compact(label);
      const visible = (node) => {
        if (!node || node.disabled || /^(hidden|file|radio|checkbox)$/i.test(node.type || "")) return false;
        const style = window.getComputedStyle(node);
        const rect = node.getBoundingClientRect();
        return style.display !== "none" && style.visibility !== "hidden" && rect.width > 0 && rect.height > 0;
      };
      const controls = Array.from(document.querySelectorAll("input, textarea")).filter(visible);
      const ranked = controls
        .map((node) => {
          const id = node.id || "";
          const explicit = id ? document.querySelector(`label[for="${CSS.escape(id)}"]`) : null;
          const scope = node.closest("fieldset, section, li, div") || node.parentElement;
          const text = compact(`${explicit?.textContent || ""} ${node.placeholder || ""} ${node.getAttribute("aria-label") || ""} ${scope?.textContent || ""} ${node.name || ""}`);
          const score = text.includes(wantedLabel) ? clean(scope?.textContent || "").length : 0;
          return { node, score };
        })
        .filter((item) => item.score > 0)
        .sort((a, b) => a.score - b.score);
      const node = ranked[0]?.node;
      if (!node) return null;
      const rect = node.getBoundingClientRect();
      return { x: Math.round(rect.left + rect.width / 2), y: Math.round(rect.top + rect.height / 2) };
    },
    { label }
  );
  if (!point) return false;
  await page.mouse.move(point.x, point.y, { steps: 8 });
  await page.mouse.click(point.x, point.y, { delay: 80 });
  const modifier = process.platform === "darwin" ? "Meta" : "Control";
  await page.keyboard.down(modifier);
  await page.keyboard.press("KeyA");
  await page.keyboard.up(modifier);
  await page.keyboard.press("Backspace");
  await page.keyboard.type(answer, { delay: 35 });
  await page.keyboard.press("Tab");
  await new Promise((resolve) => setTimeout(resolve, 300));
  return true;
}

try {
  const page = await browser.newPage();
  await page.setViewport({ width: 1440, height: 1200 });
  await page.goto(url, { waitUntil: "domcontentloaded", timeout: 90000 });
  await new Promise((resolve) => setTimeout(resolve, Number(process.env.SFFC_WORKABLE_INSPECT_WAIT_MS || 20000)));
  await clickCookie(page);

  console.log("loaded", await page.title());
  console.log("visible-controls-before", (await getVisibleControls(page)).length);

  const results = [];
  for (const item of answers) {
    const typed = await typeBestVisibleField(page, item.label, item.answer);
    const clicked = typed ? false : await clickBestVisibleText(page, item.label, item.answer);
    results.push({ label: item.label, answer: item.answer, typed, clicked, ok: typed || clicked });
  }

  await page.screenshot({ path: "/tmp/workable-visible-fill.png", fullPage: true }).catch(() => {});
  console.log(JSON.stringify({ results, controlsAfter: await getVisibleControls(page) }, null, 2));
} finally {
  await browser.close();
}
