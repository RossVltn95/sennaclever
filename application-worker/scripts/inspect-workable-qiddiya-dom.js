import puppeteer from "puppeteer";

const url =
  process.env.SFFC_WORKABLE_TEST_URL ||
  "https://apply.workable.com/qiddiya-investment-company-1/j/F2F2483923/apply/";

const browser = await puppeteer.launch({
  headless: "new",
  timeout: Number(process.env.SFFC_BROWSER_LAUNCH_TIMEOUT_MS || 90000),
  args: ["--no-sandbox", "--disable-setuid-sandbox"],
});

try {
  const page = await browser.newPage();
  await page.setViewport({ width: 1440, height: 1200 });
  await page.goto(url, { waitUntil: "domcontentloaded", timeout: 90000 });
  await new Promise((resolve) => setTimeout(resolve, Number(process.env.SFFC_WORKABLE_INSPECT_WAIT_MS || 25000)));
  await page.evaluate(() => {
    const buttons = Array.from(document.querySelectorAll("button, a, input[type='button']"));
    const target = buttons.find((button) =>
      /^(accept all|accept|allow all|agree|ok)$/i.test(
        `${button.textContent || ""} ${button.getAttribute("value") || ""} ${button.getAttribute("aria-label") || ""}`
          .replace(/\s+/g, " ")
          .trim()
      )
    );
    if (target) {
      target.click();
    }
  }).catch(() => {});
  await new Promise((resolve) => setTimeout(resolve, 2000));
  const dump = await page.evaluate(() => {
    const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
    const isVisible = (element) => {
      if (!element) return false;
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.visibility !== "hidden" && style.display !== "none" && rect.width > 0 && rect.height > 0;
    };
    const labelFor = (control) => {
      const id = control.getAttribute("id") || "";
      const explicit = id ? document.querySelector(`label[for="${CSS.escape(id)}"]`) : null;
      const wrapper =
        explicit ||
        control.closest("label") ||
        control.closest("[role='radio']") ||
        control.closest("[role='checkbox']") ||
        control.parentElement;
      return clean(
        `${explicit ? explicit.textContent || "" : ""} ${wrapper ? wrapper.textContent || "" : ""} ${
          control.getAttribute("aria-label") || ""
        } ${control.value || ""}`
      );
    };
    return {
      title: document.title,
      url: window.location.href,
      bodyTextSample: clean(document.body?.innerText || "").slice(0, 1200),
      labels: Array.from(document.querySelectorAll("label")).map((label) => {
        const rect = label.getBoundingClientRect();
        return {
          visible: isVisible(label),
          for: label.getAttribute("for") || "",
          text: clean(label.textContent || "").slice(0, 180),
          x: Math.round(rect.left),
          y: Math.round(rect.top),
          width: Math.round(rect.width),
          height: Math.round(rect.height),
        };
      }),
      inputs: Array.from(document.querySelectorAll("input, textarea")).map((input) => {
        const rect = input.getBoundingClientRect();
        return {
          visible: isVisible(input),
          tag: input.tagName,
          type: input.type || "",
          id: input.id || "",
          name: input.name || "",
          placeholder: input.getAttribute("placeholder") || "",
          aria: input.getAttribute("aria-label") || "",
          value: input.type === "file" ? "" : clean(input.value || "").slice(0, 100),
          required: input.required || input.getAttribute("aria-required") === "true",
          x: Math.round(rect.left),
          y: Math.round(rect.top),
          width: Math.round(rect.width),
          height: Math.round(rect.height),
          nearbyText: clean(input.closest("label, fieldset, section, li, div")?.textContent || "").slice(0, 220),
        };
      }),
      interactiveControls: Array.from(
        document.querySelectorAll("button, [role='button'], [role='combobox'], [aria-haspopup], [tabindex]")
      ).map((control) => {
        const rect = control.getBoundingClientRect();
        return {
          visible: isVisible(control),
          tag: control.tagName,
          role: control.getAttribute("role") || "",
          ariaHaspopup: control.getAttribute("aria-haspopup") || "",
          ariaExpanded: control.getAttribute("aria-expanded") || "",
          id: control.id || "",
          name: control.getAttribute("name") || "",
          type: control.getAttribute("type") || "",
          text: clean(control.textContent || control.getAttribute("aria-label") || "").slice(0, 180),
          x: Math.round(rect.left),
          y: Math.round(rect.top),
          width: Math.round(rect.width),
          height: Math.round(rect.height),
          nearbyText: clean(control.closest("label, fieldset, section, li, div")?.textContent || "").slice(0, 220),
        };
      }),
      selects: Array.from(document.querySelectorAll("select")).map((select) => ({
        visible: isVisible(select),
        id: select.id || "",
        name: select.name || "",
        aria: select.getAttribute("aria-label") || "",
        text: clean(select.closest("div, fieldset, section")?.textContent || "").slice(0, 220),
        options: Array.from(select.options || []).map((option) => clean(option.textContent || option.value || "")),
      })),
      radios: Array.from(document.querySelectorAll("input[type='radio']")).map((input) => ({
        visible: isVisible(input),
        id: input.id || "",
        name: input.name || "",
        value: input.value || "",
        checked: input.checked,
        label: labelFor(input).slice(0, 220),
        scope: clean(input.closest("fieldset, section, li, div")?.textContent || "").slice(0, 260),
      })),
      checkboxes: Array.from(document.querySelectorAll("input[type='checkbox']")).map((input) => ({
        visible: isVisible(input),
        id: input.id || "",
        name: input.name || "",
        value: input.value || "",
        checked: input.checked,
        label: labelFor(input).slice(0, 260),
      })),
    };
  });
  console.log(JSON.stringify(dump, null, 2));
} finally {
  await browser.close();
}
