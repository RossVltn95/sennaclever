import fs from "node:fs";
import fsPromises from "node:fs/promises";
import httpProxy from "http-proxy";
import path from "node:path";
import { spawn } from "node:child_process";
import { cleanText, sendError } from "./auth.js";
import { debugLog, getBrowserExecutablePath, getProfileRoot } from "./browser.js";

const proxy = httpProxy.createProxyServer({ ws: true, xfwd: true });

function commandExists(command) {
  const candidates = (process.env.PATH || "").split(path.delimiter).map((dir) => path.join(dir, command));
  return candidates.some((candidate) => fs.existsSync(candidate));
}

function getNoVncWebRoot() {
  const configured = cleanText(process.env.SFFC_REMOTE_BROWSER_NOVNC_WEB_ROOT || "");
  const candidates = [
    configured,
    "/usr/share/novnc",
    "/usr/share/novnc/web",
  ].filter(Boolean);
  return candidates.find((candidate) => fs.existsSync(candidate)) || "";
}

function spawnRuntimeProcess(command, args, options = {}) {
  const child = spawn(command, args, {
    stdio: process.env.SFFC_REMOTE_BROWSER_DEBUG === "1" ? "inherit" : "ignore",
    detached: false,
    ...options,
  });
  child.on("error", (error) => {
    debugLog("process error", command, error.message);
  });
  return child;
}

function stopProcess(child) {
  if (!child || child.killed) {
    return;
  }
  try {
    child.kill("SIGTERM");
  } catch (error) {
    debugLog("process stop failed", error.message);
  }
}

function wait(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

export function isNoVncAvailable() {
  return Boolean(
    getBrowserExecutablePath() &&
      getNoVncWebRoot() &&
      commandExists("Xvfb") &&
      commandExists("x11vnc") &&
      commandExists("websockify")
  );
}

export function getSessionPorts(slot) {
  const displayBase = Number(process.env.SFFC_REMOTE_BROWSER_DISPLAY_BASE || 100) || 100;
  const rfbBase = Number(process.env.SFFC_REMOTE_BROWSER_RFB_PORT_BASE || 5900) || 5900;
  const webBase = Number(process.env.SFFC_REMOTE_BROWSER_NOVNC_PORT_BASE || 7900) || 7900;
  return {
    display: displayBase + slot,
    rfbPort: rfbBase + slot,
    webPort: webBase + slot,
  };
}

export async function createNoVncSession(sessionId, employerUrl, provider = "", slot = 0) {
  if (!isNoVncAvailable()) {
    throw new Error("noVNC runtime is not available in this environment.");
  }
  const cleanUrl = cleanText(employerUrl);
  const { display, rfbPort, webPort } = getSessionPorts(slot);
  const displayName = `:${display}`;
  const profileRoot = getProfileRoot();
  const userDataDir = path.join(profileRoot, sessionId);
  const browserExecutable = getBrowserExecutablePath();
  const webRoot = getNoVncWebRoot();
  await fsPromises.mkdir(userDataDir, { recursive: true });

  const xvfb = spawnRuntimeProcess("Xvfb", [
    displayName,
    "-screen",
    "0",
    "1366x1000x24",
    "-ac",
    "+extension",
    "RANDR",
  ]);
  await wait(350);

  const windowManager = commandExists("openbox")
    ? spawnRuntimeProcess("openbox", [], { env: { ...process.env, DISPLAY: displayName } })
    : null;

  const chrome = spawnRuntimeProcess(browserExecutable, [
    `--display=${displayName}`,
    "--no-sandbox",
    "--disable-setuid-sandbox",
    "--disable-dev-shm-usage",
    "--no-first-run",
    "--no-default-browser-check",
    "--disable-infobars",
    "--disable-session-crashed-bubble",
    "--disable-features=Translate,AutomationControlled",
    "--lang=en-GB,en-US,en",
    "--window-size=1366,1000",
    `--user-data-dir=${userDataDir}`,
    cleanUrl,
  ]);
  await wait(700);

  const x11vnc = spawnRuntimeProcess("x11vnc", [
    "-display",
    displayName,
    "-localhost",
    "-forever",
    "-shared",
    "-nopw",
    "-rfbport",
    String(rfbPort),
  ]);
  await wait(350);

  const websockify = spawnRuntimeProcess("websockify", [
    "--web",
    webRoot,
    String(webPort),
    `localhost:${rfbPort}`,
  ]);

  debugLog("novnc session ready", sessionId, provider, displayName, rfbPort, webPort);
  return {
    kind: "novnc",
    provider,
    employerUrl: cleanUrl,
    finalUrl: cleanUrl,
    title: "",
    display: displayName,
    webPort,
    rfbPort,
    userDataDir,
    processes: [websockify, x11vnc, chrome, windowManager, xvfb].filter(Boolean),
  };
}

export async function closeNoVncSession(runtime) {
  if (!runtime || runtime.kind !== "novnc") {
    return;
  }
  for (const child of runtime.processes || []) {
    stopProcess(child);
  }
  if (runtime.userDataDir) {
    await fsPromises.rm(runtime.userDataDir, { recursive: true, force: true }).catch(() => {});
  }
}

export function getNoVncStreamPath(sessionId, viewerToken = "", publicBaseUrl = "") {
  const encoded = encodeURIComponent(sessionId);
  const token = cleanText(viewerToken || "");
  const params = new URLSearchParams({
    autoconnect: "1",
    reconnect: "1",
    reconnect_delay: "1000",
    resize: "remote",
    quality: "6",
    compression: "2",
    path: `sessions/${encoded}/novnc/websockify${token ? `?token=${encodeURIComponent(token)}` : ""}`,
  });
  let publicUrl;
  if (token) {
    params.set("token", token);
  }
  try {
    publicUrl = publicBaseUrl ? new URL(publicBaseUrl) : null;
  } catch (error) {
    publicUrl = null;
  }
  if (publicUrl && publicUrl.hostname) {
    params.set("host", publicUrl.hostname);
    params.set("port", publicUrl.port || (publicUrl.protocol === "https:" ? "443" : "80"));
    params.set("encrypt", publicUrl.protocol === "https:" ? "1" : "0");
  }
  return `/sessions/${encoded}/novnc/vnc.html?${params.toString()}`;
}

function rewriteNoVncPath(request, sessionId) {
  const prefix = `/sessions/${encodeURIComponent(sessionId)}/novnc`;
  const unencodedPrefix = `/sessions/${sessionId}/novnc`;
  let nextPath = request.url || "/";
  if (nextPath.startsWith(prefix)) {
    nextPath = nextPath.slice(prefix.length) || "/";
  } else if (nextPath.startsWith(unencodedPrefix)) {
    nextPath = nextPath.slice(unencodedPrefix.length) || "/";
  }
  request.url = nextPath || "/";
}

export function proxyNoVncHttp(request, response, session, corsHeaders) {
  if (!session?.runtime || session.runtime.kind !== "novnc" || !session.runtime.webPort) {
    sendError(response, 409, "Remote browser stream is not ready.", { status: session?.status || "unknown" }, corsHeaders);
    return true;
  }
  rewriteNoVncPath(request, session.sessionId);
  proxy.web(request, response, {
    target: `http://127.0.0.1:${session.runtime.webPort}`,
  });
  return true;
}

export function proxyNoVncUpgrade(request, socket, head, session) {
  if (!session?.runtime || session.runtime.kind !== "novnc" || !session.runtime.webPort) {
    socket.destroy();
    return true;
  }
  rewriteNoVncPath(request, session.sessionId);
  proxy.ws(request, socket, head, {
    target: `ws://127.0.0.1:${session.runtime.webPort}`,
  });
  return true;
}
