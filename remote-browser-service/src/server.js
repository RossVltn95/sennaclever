import http from "node:http";
import { URL } from "node:url";
import {
  cleanText,
  assertSafeEmployerUrl,
  getCorsHeaders,
  isAuthorized,
  parseJsonBody,
  sendError,
  sendJson,
} from "./auth.js";
import { auditEvent, auditSessionEvent, getHost } from "./audit.js";
import {
  clickSession,
  getBrowserExecutablePath,
  pressKeySession,
  screenshotSession,
  scrollSession,
  typeSession,
} from "./browser.js";
import { startCleanupLoop, stopCleanupLoop } from "./cleanup.js";
import {
  closeAllSessions,
  closeSession,
  getCapacitySnapshot,
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
import { isNoVncAvailable, isNoVncStaticAssetPath, proxyNoVncHttp, proxyNoVncUpgrade } from "./novnc.js";
import { checkRateLimit, getClientKey } from "./rate-limit.js";

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

function enforceRateLimit(request, response, corsHeaders, scope, limit, windowMs) {
  const clientKey = `${scope}:${getClientKey(request)}`;
  const result = checkRateLimit(clientKey, { limit, windowMs });
  if (result.allowed) {
    return true;
  }
  response.setHeader("Retry-After", String(Math.max(1, Math.ceil((result.resetAt - Date.now()) / 1000))));
  auditEvent("remote_browser_rate_limited", {
    scope,
    client: getClientKey(request),
    limit: result.limit,
  });
  sendError(response, 429, "Too many assisted browser requests. Please wait and try again.", {}, corsHeaders);
  return false;
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
  const capacity = getCapacitySnapshot();
  const runtime = {
    transport: cleanText(process.env.SFFC_REMOTE_BROWSER_TRANSPORT || "novnc").toLowerCase(),
    internalFallback: cleanText(process.env.SFFC_REMOTE_BROWSER_INTERNAL_FALLBACK || "novnc").toLowerCase(),
    chromeAvailable: Boolean(getBrowserExecutablePath()),
    noVncAvailable: isNoVncAvailable(),
  };
  sendJson(
    response,
    200,
    {
      ok: true,
      service: "sffc-remote-browser",
      status: "healthy",
      sessions: getSessionCount(),
      maxSessions: getMaxSessions(),
      capacity,
      runtime,
      now: new Date().toISOString(),
    },
    corsHeaders
  );
}

async function handleCapacity(response, corsHeaders) {
  const capacity = getCapacitySnapshot();
  sendJson(
    response,
    200,
    {
      ok: true,
      capacity,
      sessions: listSessionSummaries(),
      now: new Date().toISOString(),
    },
    corsHeaders
  );
}

async function handleCreateSession(request, response, corsHeaders) {
  const startedAt = Date.now();
  if (
    !enforceRateLimit(
      request,
      response,
      corsHeaders,
      "create_session",
      Number(process.env.SFFC_REMOTE_BROWSER_CREATE_RATE_LIMIT || 10),
      Number(process.env.SFFC_REMOTE_BROWSER_RATE_LIMIT_WINDOW_MS || 60000)
    )
  ) {
    return;
  }
  const body = await parseJsonBody(request);
  const employerUrl = await assertSafeEmployerUrl(body.employerUrl || body.employer_url || body.url || "");
  auditEvent("remote_browser_create_requested", {
    provider: cleanText(body.provider || "unknown"),
    employerHost: getHost(employerUrl),
    roleId: cleanText(body.roleId || body.role_id || body.jobsPostId || body.jobs_post_id || ""),
    userId: Number(body.userId || body.user_id || 0) || 0,
    conversationId: Number(body.conversationId || body.conversation_id || 0) || 0,
  });
  const session = await createSession({ ...body, employerUrl });
  auditEvent("remote_browser_ready", {
    sessionId: session.sessionId,
    provider: session.provider,
    employerHost: getHost(session.employerUrl),
    roleId: session.roleId,
    userId: session.userId,
    conversationId: session.conversationId,
    transport: session.transport,
    startupMs: Date.now() - startedAt,
  });
  auditEvent("remote_browser_created", {
    sessionId: session.sessionId,
    provider: session.provider,
    employerHost: getHost(session.employerUrl),
    roleId: session.roleId,
    userId: session.userId,
    conversationId: session.conversationId,
    transport: session.transport,
    status: session.status,
    control: session.control,
  });
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
  if (
    ["navigate", "control", "click", "type", "key", "scroll", "upload"].includes(action) &&
    !enforceRateLimit(
      request,
      response,
      corsHeaders,
      `session_${action}`,
      Number(process.env.SFFC_REMOTE_BROWSER_ACTION_RATE_LIMIT || 120),
      Number(process.env.SFFC_REMOTE_BROWSER_RATE_LIMIT_WINDOW_MS || 60000)
    )
  ) {
    return;
  }
  if (action === "navigate") {
    const targetUrl = await assertSafeEmployerUrl(body.employerUrl || body.employer_url || body.url || "");
    try {
      const nextSession = await navigateExistingSession(sessionId, targetUrl);
      auditSessionEvent("remote_browser_navigated", nextSession, {
        employerHost: getHost(targetUrl),
      });
      sendJson(response, 200, { ok: true, session: nextSession }, corsHeaders);
    } catch (error) {
      auditSessionEvent("remote_browser_navigation_failed", session, {
        employerHost: getHost(targetUrl),
        error: cleanText(error.message || "Navigation failed."),
      });
      throw error;
    }
    return;
  }
  if (action === "control") {
    const requestedControl = cleanText(body.control || body.state || "user_control");
    if (!["user_control", "emily_control", "waiting_for_user", "read_only"].includes(requestedControl)) {
      sendError(response, 422, "Unsupported remote browser control state.", {}, corsHeaders);
      return;
    }
    const nextSession = setSessionControl(sessionId, requestedControl);
    auditSessionEvent("remote_browser_control_changed", nextSession, {
      requestedControl,
    });
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
    auditSessionEvent("remote_browser_upload_blocked", session, {
      reason: "upload_requires_explicit_shared_control_implementation",
    });
    sendError(response, 501, "Remote browser file upload is reserved for the shared-control phase.", {}, corsHeaders);
    return;
  } else if (action === "close") {
    auditSessionEvent("remote_browser_close_requested", session);
    await closeSession(sessionId);
    auditEvent("remote_browser_closed", {
      sessionId,
      durationMs: Date.now() - Number(session.createdAt || Date.now()),
    });
    sendJson(response, 200, { ok: true, closed: true, sessionId }, corsHeaders);
    return;
  } else {
    sendError(response, 404, "Unknown remote browser action.", {}, corsHeaders);
    return;
  }
  session.finalUrl = snapshot.finalUrl;
  session.title = snapshot.title;
  touchSession(session);
  auditSessionEvent(`remote_browser_${action}`, session);
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
  if (isNoVncStaticAssetPath(parts)) {
    touchSession(session);
    return proxyNoVncHttp(request, response, session, corsHeaders);
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
    if (parsedUrl.pathname === "/capacity" && request.method === "GET") {
      await handleCapacity(response, corsHeaders);
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
    if (error.code === "capacity_full") {
      auditEvent("remote_browser_capacity_exhausted", {
        path: parsedUrl.pathname,
        method: request.method,
        sessions: getSessionCount(),
        maxSessions: getMaxSessions(),
      });
    }
    if (error.code === "upstream_rate_limited") {
      response.setHeader(
        "Retry-After",
        String(Math.max(1, Math.ceil(Number(error.retryAfterSeconds || 60))))
      );
      auditEvent("remote_browser_upstream_rate_limited", {
        path: parsedUrl.pathname,
        method: request.method,
        retryAfterSeconds: Math.max(
          1,
          Math.ceil(Number(error.retryAfterSeconds || 60))
        ),
      });
    }
    auditEvent("remote_browser_request_failed", {
      path: parsedUrl.pathname,
      method: request.method,
      statusCode: error.statusCode || (error.code === "capacity_full" ? 429 : 500),
      error: cleanText(error.message || "Remote browser request failed."),
    });
    sendError(
      response,
      error.statusCode || (error.code === "capacity_full" ? 429 : 500),
      error.message || "Remote browser request failed.",
      error.retryAfterSeconds
        ? { retryAfterSeconds: Math.max(1, Math.ceil(Number(error.retryAfterSeconds))) }
        : {},
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
