import puppeteer from "puppeteer";

const url =
  process.env.SFFC_WORKABLE_TEST_URL ||
  "https://apply.workable.com/qiddiya-investment-company-1/j/75DCF9FFBC/apply/";

const browser = await puppeteer.launch({
  headless: "new",
  executablePath: process.env.PUPPETEER_EXECUTABLE_PATH || undefined,
  args: ["--no-sandbox", "--disable-setuid-sandbox"],
});

try {
  const page = await browser.newPage();
  await page.setViewport({ width: 1440, height: 1200 });
  await page.goto(url, { waitUntil: "domcontentloaded", timeout: 90000 });
  await page.waitForNetworkIdle({ idleTime: 1000, timeout: 30000 }).catch(() => {});
  await page.evaluate(() => {
    const button = Array.from(document.querySelectorAll("button")).find((node) =>
      /accept/i.test(node.textContent || "")
    );
    if (button) button.click();
  }).catch(() => {});

  for (const fieldName of ["CA_50967", "CA_50968"]) {
    const visibleId = `input_${fieldName}_input`;
    const point = await page.evaluate((id) => {
      const input = document.getElementById(id);
      if (!input) return null;
      input.scrollIntoView({ block: "center", inline: "nearest" });
      const rect = input.getBoundingClientRect();
      return {
        left: rect.left,
        top: rect.top,
        width: rect.width,
        height: rect.height,
        centerX: rect.left + rect.width / 2,
        centerY: rect.top + rect.height / 2,
        arrowX: rect.right - 18,
        arrowY: rect.top + rect.height / 2,
        outerHTML: input.outerHTML,
      };
    }, visibleId);
    console.log(`\n===== ${fieldName} visible control =====`);
    console.log(JSON.stringify(point, null, 2));
    if (!point) continue;
    await page.mouse.click(point.arrowX, point.arrowY);
    await new Promise((resolve) => setTimeout(resolve, 800));
    const state = await page.evaluate((fieldName) => {
      const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
      const controls = Array.from(document.querySelectorAll("[role='option'], [role='listbox'] *, li, button, div"))
        .map((node) => {
          const style = window.getComputedStyle(node);
          const rect = node.getBoundingClientRect();
          return {
            tag: node.tagName,
            role: node.getAttribute("role") || "",
            id: node.getAttribute("id") || "",
            ariaSelected: node.getAttribute("aria-selected") || "",
            text: clean(node.textContent || node.getAttribute("aria-label") || ""),
            visible:
              style.visibility !== "hidden" &&
              style.display !== "none" &&
              rect.width > 0 &&
              rect.height > 0,
            x: Math.round(rect.left),
            y: Math.round(rect.top),
            width: Math.round(rect.width),
            height: Math.round(rect.height),
          };
        })
        .filter((entry) => entry.visible && entry.text && entry.text.length < 160);
      const hidden = document.querySelector(`[name="${CSS.escape(fieldName)}"]`);
      const visible = document.getElementById(`input_${fieldName}_input`);
      return {
        activeElement: document.activeElement?.outerHTML?.slice(0, 500) || "",
        expanded: visible?.getAttribute("aria-expanded") || "",
        visibleValue: visible?.value || "",
        hiddenValue: hidden?.value || "",
        controls: controls.slice(-80),
      };
    }, fieldName);
    console.log(JSON.stringify(state, null, 2));
    await page.keyboard.press("Escape").catch(() => {});
  }
} finally {
  await browser.close();
}
