#!/usr/bin/env node

const http = require("http");

const DEFAULT_CHROME_VERSION_URL = "http://127.0.0.1:9222/json/version";

function fetchJson(url) {
  return new Promise((resolve, reject) => {
    const request = http.get(url, (response) => {
      let body = "";
      response.setEncoding("utf8");
      response.on("data", (chunk) => {
        body += chunk;
      });
      response.on("end", () => {
        if (response.statusCode < 200 || response.statusCode >= 300) {
          reject(new Error(`Chrome DevTools returned HTTP ${response.statusCode}`));
          return;
        }
        try {
          resolve(JSON.parse(body));
        } catch (error) {
          reject(new Error(`Chrome DevTools returned invalid JSON: ${error.message}`));
        }
      });
    });
    request.on("error", reject);
    request.setTimeout(5000, () => {
      request.destroy(new Error("Timed out connecting to Chrome DevTools."));
    });
  });
}

async function getChromeBrowserWsEndpoint() {
  if (process.env.SFFC_CHROME_WS) return process.env.SFFC_CHROME_WS;
  if (process.env.BROWSER_WS_ENDPOINT) return process.env.BROWSER_WS_ENDPOINT;

  const version = await fetchJson(process.env.SFFC_CHROME_VERSION_URL || DEFAULT_CHROME_VERSION_URL);
  if (!version || !version.webSocketDebuggerUrl) {
    throw new Error("Chrome DevTools is reachable, but no browser websocket endpoint was returned.");
  }
  return version.webSocketDebuggerUrl;
}

module.exports = {
  getChromeBrowserWsEndpoint,
};
