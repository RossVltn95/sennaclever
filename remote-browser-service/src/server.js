import http from "node:http";
import { URL } from "node:url";
import {
  cleanText,
  getCorsHeaders,
  isAuthorized,
  isValidEmployerUrl,
  parseJsonBody,
  sendError,
  sendJson,
} from "./auth.js";
import {
  clickSession,
  pressKeySession,
  screenshotSession,
  scrollSession,
  typeSession,
} from "./browser.js";
import { startCleanupLoop, stopCleanupLoop } from "./cleanup.js";
import {
  closeAllSessions,
  closeSession,
  createSession,
  getMaxSessions,
  getSession,
  getSessionCount,
  hasValidViewerToken,
  listSessionSummaries,
  navigateExistingSession,
  serializeSession,
  setSessionControl,
  touchSession,
} from "./sessions.js";
import { proxyNoVncHttp, proxyNoVncUpgrade } from "./novnc.js";

const port = Number(process.env.PORT || 3000);
const token = cleanText(process.env.SFFC_REMOTE_BROWSER_TOKEN || "");
let server;

function routeParts(pathname) {
  return pathname.split("/").filter(Boolean).map(decodeURIComponent);
}

function sendCors(response, request) {
  const headers = getCorsHeaders(request);
  response.writeHead(204, headers);
  response.end();
}

function requireAuth(request, response, corsHeaders) {
  if (!token) {
    sendError(response, 503, "Remote browser token is not configured.", {}, corsHeaders);
    return false;
  }
  if (!isAuthorized(request, token)) {
    sendError(response, 401, "Unauthorized remote browser request.", {}, corsHeaders);
    return false;
  }
  return true;
}

function getViewerCookieToken(request, sessionId) {
  const cookieHeader = cleanText(request.headers.cookie || "");
  const cookieName = `sffc_rbs_${sessionId}`;
  const parts = cookieHeader.split(";").map((part) => part.trim());
  for (const part of parts) {
    const separatorIndex = part.indexOf("=");
    if (separatorIndex <= 0) {
      continue;
    }
    const name = part.slice(0, separatorIndex);
    const value = part.slice(separatorIndex + 1);
    if (name === cookieName) {
      return decodeURIComponent(value);
    }
  }
  return "";
}

function setViewerCookie(response, session) {
  response.setHeader(
    "set-cookie",
    `sffc_rbs_${session.sessionId}=${encodeURIComponent(session.viewerToken)}; Path=/sessions/${encodeURIComponent(session.sessionId)}/novnc; HttpOnly; SameSite=None; Secure; Max-Age=900`
  );
}

async function handleHealth(response, corsHeaders) {
  sendJson(
    response,
    200,
    {
      ok: true,
      service: "sffc-remote-browser",
      status: "healthy",
      sessions: getSessionCount(),
      maxSessions: getMaxSessions(),
      now: new Date().toISOString(),
    },
    corsHeaders
  );
}

async function handleCreateSession(request, response, corsHeaders) {
  const body = await parseJsonBody(request);
  const employerUrl = cleanText(body.employerUrl || body.employer_url || body.url || "");
  if (!isValidEmployerUrl(employerUrl)) {
    sendError(response, 422, "A valid external employer URL is required.", {}, corsHeaders);
    return;
  }
  const session = await createSession({ ...body, employerUrl });
  sendJson(response, 201, { ok: true, session }, corsHeaders);
}

function getSessionOr404(sessionId, response, corsHeaders) {
  const session = getSession(sessionId);
  if (!session) {
    sendError(response, 404, "Remote browser session was not found.", {}, corsHeaders);
    return null;
  }
  touchSession(session);
  return session;
}

async function handleSessionAction(request, response, pathname, corsHeaders) {
  const parts = routeParts(pathname);
  const sessionId = parts[1] || "";
  const action = parts[2] || "";
  const session = getSessionOr404(sessionId, response, corsHeaders);
  let body = {};
  let snapshot;
  if (!session) {
    return;
  }
  if (!action && request.method === "GET") {
    sendJson(response, 200, { ok: true, session: serializeSession(session) }, corsHeaders);
    return;
  }
  if (action === "screenshot" && request.method === "GET") {
    if (session.status === "failed" || !session.runtime) {
      sendError(response, 409, "Remote browser session is not ready.", { status: session.status }, corsHeaders);
      return;
    }
    const image = await screenshotSession(session.runtime);
    response.writeHead(200, {
      ...corsHeaders,
      "content-type": "image/png",
      "cache-control": "no-store",
    });
    response.end(image);
    return;
  }
  if (request.method !== "POST") {
    sendError(response, 405, "Method not allowed.", {}, corsHeaders);
    return;
  }
  body = await parseJsonBody(request);
  if (action === "navigate") {
    const targetUrl = cleanText(body.employerUrl || body.employer_url || body.url || "");
    if (!isValidEmployerUrl(targetUrl)) {
      sendError(response, 422, "A valid external employer URL is required.", {}, corsHeaders);
      return;
    }
    const nextSession = await navigateExistingSession(sessionId, targetUrl);
    sendJson(response, 200, { ok: true, session: nextSession }, corsHeaders);
    return;
  }
  if (action === "control") {
    const requestedControl = cleanText(body.control || body.state || "user_control");
    if (!["user_control", "emily_control", "waiting_for_user", "read_only"].includes(requestedControl)) {
      sendError(response, 422, "Unsupported remote browser control state.", {}, corsHeaders);
      return;
    }
    const nextSession = setSessionControl(sessionId, requestedControl);
    sendJson(response, 200, { ok: true, session: nextSession }, corsHeaders);
    return;
  }
  if (action === "click") {
    snapshot = await clickSession(session.runtime, body.x, body.y);
  } else if (action === "type") {
    snapshot = await typeSession(session.runtime, body.text || "");
  } else if (action === "key") {
    snapshot = await pressKeySession(session.runtime, body.key || "Enter");
  } else if (action === "scroll") {
    snapshot = await scrollSession(session.runtime, body.deltaX || 0, body.deltaY || 0);
  } else if (action === "upload") {
    sendError(response, 501, "Remote browser file upload is reserved for the shared-control phase.", {}, corsHeaders);
    return;
  } else if (action === "close") {
    await closeSession(sessionId);
    sendJson(response, 200, { ok: true, closed: true, sessionId }, corsHeaders);
    return;
  } else {
    sendError(response, 404, "Unknown remote browser action.", {}, corsHeaders);
    return;
  }
  session.finalUrl = snapshot.finalUrl;
  session.title = snapshot.title;
  touchSession(session);
  sendJson(response, 200, { ok: true, session: serializeSession(session) }, corsHeaders);
}

function handleNoVncRequest(request, response, parsedUrl, corsHeaders) {
  const parts = routeParts(parsedUrl.pathname);
  if (parts[0] !== "sessions" || !parts[1] || parts[2] !== "novnc") {
    return false;
  }
  const session = getSession(parts[1]);
  if (!session) {
    sendError(response, 404, "Remote browser session was not found.", {}, corsHeaders);
    return true;
  }
  const queryToken = parsedUrl.searchParams.get("token") || "";
  const cookieToken = getViewerCookieToken(request, session.sessionId);
  if (!hasValidViewerToken(session, queryToken || cookieToken)) {
    sendError(response, 401, "Unauthorized remote browser stream request.", {}, corsHeaders);
    return true;
  }
  if (queryToken) {
    setViewerCookie(response, session);
  }
  touchSession(session);
  return proxyNoVncHttp(request, response, session, corsHeaders);
}

async function handleRequest(request, response) {
  const parsedUrl = new URL(request.url || "/", "http://localhost");
  const corsHeaders = getCorsHeaders(request);
  try {
    if (request.method === "OPTIONS") {
      sendCors(response, request);
      return;
    }
    if (parsedUrl.pathname === "/" || parsedUrl.pathname === "/health") {
      await handleHealth(response, corsHeaders);
      return;
    }
    if (handleNoVncRequest(request, response, parsedUrl, corsHeaders)) {
      return;
    }
    if (!requireAuth(request, response, corsHeaders)) {
      return;
    }
    if (parsedUrl.pathname === "/sessions" && request.method === "GET") {
      sendJson(response, 200, { ok: true, sessions: listSessionSummaries() }, corsHeaders);
      return;
    }
    if (parsedUrl.pathname === "/sessions" && request.method === "POST") {
      await handleCreateSession(request, response, corsHeaders);
      return;
    }
    if (/^\/sessions\/[^/]+(?:\/[^/]+)?$/.test(parsedUrl.pathname)) {
      await handleSessionAction(request, response, parsedUrl.pathname, corsHeaders);
      return;
    }
    sendError(response, 404, "Remote browser endpoint was not found.", {}, corsHeaders);
  } catch (error) {
    sendError(
      response,
      error.code === "capacity_full" ? 429 : 500,
      error.message || "Remote browser request failed.",
      {},
      corsHeaders
    );
  }
}

server = http.createServer((request, response) => {
  handleRequest(request, response);
});

server.on("upgrade", (request, socket, head) => {
  const parsedUrl = new URL(request.url || "/", "http://localhost");
  const parts = routeParts(parsedUrl.pathname);
  if (parts[0] !== "sessions" || !parts[1] || parts[2] !== "novnc") {
    socket.destroy();
    return;
  }
  const session = getSession(parts[1]);
  const queryToken = parsedUrl.searchParams.get("token") || "";
  const cookieToken = getViewerCookieToken(request, parts[1]);
  if (!hasValidViewerToken(session, queryToken || cookieToken)) {
    socket.destroy();
    return;
  }
  touchSession(session);
  proxyNoVncUpgrade(request, socket, head, session);
});

server.listen(port, "0.0.0.0", () => {
  startCleanupLoop();
  console.log(`[sffc-remote-browser] listening on ${port}`);
});

async function shutdown() {
  stopCleanupLoop();
  await closeAllSessions().catch(() => {});
  server.close(() => process.exit(0));
}

process.on("SIGTERM", shutdown);
process.on("SIGINT", shutdown);
