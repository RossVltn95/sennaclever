#!/usr/bin/env node

let puppeteer;
try {
  puppeteer = require("puppeteer");
} catch (error) {
  puppeteer = require("../application-worker/node_modules/puppeteer");
}

const { getChromeBrowserWsEndpoint } = require("./chrome-debug");

(async () => {
  const browser = await puppeteer.connect({ browserWSEndpoint: await getChromeBrowserWsEndpoint() });
  const pages = await browser.pages();
  const page = pages.find((item) => /joinsenna\.com\/jobs\//.test(item.url())) || pages[0];
  const state = await page.evaluate(() => {
    const text = (node) => (node ? String(node.textContent || "").replace(/\s+/g, " ").trim() : "");
    const messages = Array.from(document.querySelectorAll(".sffc-crm-apply-chat__message")).map((node) => ({
      className: node.className,
      text: text(node).slice(0, 700),
    }));
    const buttons = Array.from(document.querySelectorAll(".sffc-crm-apply-chat button, .sffc-crm-apply-chat a")).map((node) => {
      const rect = node.getBoundingClientRect();
      return {
        text: text(node).slice(0, 100),
        className: node.className,
        href: node.getAttribute("href") || "",
        visible: rect.width > 0 && rect.height > 0 && window.getComputedStyle(node).display !== "none",
      };
    });
    return {
      url: location.href,
      messageCount: messages.length,
      messages: messages.slice(-14),
      buttons: buttons.filter((button) => button.visible).slice(-30),
      routeSelectors: document.querySelectorAll(".sffc-crm-apply-chat__route-selector, .sffc-crm-apply-chat__quick-route-selector").length,
      membershipPanels: document.querySelectorAll(".sffc-crm-apply-chat__membership, .sffc-crm-apply-chat__membership-panel").length,
      applyResults: document.querySelectorAll(".sffc-crm-apply-results__result").length,
      iframes: Array.from(document.querySelectorAll("iframe")).map((iframe) => iframe.getAttribute("src") || "").filter(Boolean).slice(0, 20),
      overflow: document.documentElement.scrollWidth > window.innerWidth + 1,
    };
  });
  console.log(JSON.stringify(state, null, 2));
  await browser.disconnect();
})().catch((error) => {
  console.error(error && error.stack ? error.stack : error);
  process.exit(1);
});
