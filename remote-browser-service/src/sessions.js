import crypto from "node:crypto";
import { cleanText } from "./auth.js";
import {
  closeBrowserSession,
  createBrowserSession,
  createManagedLiveBrowserSession,
  getPageSnapshot,
  navigateSession,
} from "./browser.js";
import { closeNoVncSession, createNoVncSession, getNoVncStreamPath } from "./novnc.js";

const sessions = new Map();
const managedBrowserRateLimitCooldowns = new Map();

function isManagedBrowserTransport(transport) {
  const clean = cleanText(transport).toLowerCase();
  return [
    "cloudflare",
    "cloudflare_live_view",
    "browserless",
    "browserless_live_url",
    "managed_live_browser",
  ].includes(clean);
}

function normalizeManagedBrowserTransportKey(transport) {
  const clean = cleanText(transport).toLowerCase();
  if (clean === "cloudflare" || clean === "cloudflare_live_view") {
    return "cloudflare_live_view";
  }
  if (clean === "browserless" || clean === "browserless_live_url") {
    return "browserless_live_url";
  }
  return "managed_live_browser";
}

function getManagedBrowserRateLimitCooldownMs() {
  return Math.max(
    10000,
    Number(process.env.SFFC_REMOTE_BROWSER_UPSTREAM_429_COOLDOWN_MS || 120000) ||
      120000
  );
}

function getRetryAfterSeconds(error) {
  const retryAfter =
    Number(error?.retryAfterSeconds || error?.retryAfter || error?.retry_after || 0) ||
    0;
  return retryAfter > 0 ? Math.max(1, Math.ceil(retryAfter)) : 0;
}

function isUpstreamRateLimitError(error) {
  const message = cleanText(error?.message || "").toLowerCase();
  return (
    Number(error?.statusCode || error?.status || 0) === 429 ||
    /\b429\b/.test(message) ||
    message.includes("too many requests") ||
    message.includes("rate limit")
  );
}

function createManagedBrowserRateLimitError(retryAfterSeconds) {
  const seconds = Math.max(1, Number(retryAfterSeconds) || 1);
  const error = new Error(
    `Managed browser provider is rate limiting new sessions. Please wait ${seconds} seconds and try again.`
  );
  error.code = "upstream_rate_limited";
  error.statusCode = 429;
  error.retryAfterSeconds = seconds;
  return error;
}

export function getManagedBrowserRateLimitCooldown(transport) {
  const key = normalizeManagedBrowserTransportKey(transport);
  const retryAt = Number(managedBrowserRateLimitCooldowns.get(key) || 0);
  const now = Date.now();
  if (!retryAt || retryAt <= now) {
    managedBrowserRateLimitCooldowns.delete(key);
    return null;
  }
  return {
    transport: key,
    retryAt,
    retryAfterSeconds: Math.max(1, Math.ceil((retryAt - now) / 1000)),
  };
}

export function clearManagedBrowserRateLimitsForTest() {
  if (process.env.NODE_ENV !== "test") {
    throw new Error("clearManagedBrowserRateLimitsForTest is only available during tests.");
  }
  managedBrowserRateLimitCooldowns.clear();
}

export function seedManagedBrowserRateLimitForTest(transport, retryAfterSeconds = 60) {
  if (process.env.NODE_ENV !== "test") {
    throw new Error("seedManagedBrowserRateLimitForTest is only available during tests.");
  }
  managedBrowserRateLimitCooldowns.set(
    normalizeManagedBrowserTransportKey(transport),
    Date.now() + Math.max(1, Number(retryAfterSeconds) || 60) * 1000
  );
}

export function getMaxSessions() {
  return Math.max(1, Number(process.env.SFFC_REMOTE_BROWSER_MAX_SESSIONS || 5) || 5);
}

export function getSessionTtlMs() {
  return Math.max(60, Number(process.env.SFFC_REMOTE_BROWSER_SESSION_TTL_SECONDS || 900) || 900) * 1000;
}

export function getIdleTtlMs() {
  return Math.max(30, Number(process.env.SFFC_REMOTE_BROWSER_IDLE_TTL_SECONDS || 180) || 180) * 1000;
}

export function getPublicBaseUrl() {
  return cleanText(process.env.SFFC_REMOTE_BROWSER_PUBLIC_URL || "");
}

export function getSessionCount() {
  return sessions.size;
}

export function getAllocatedSlots() {
  return Array.from(sessions.values())
    .map((session) => Number(session.slot))
    .filter((slot) => Number.isInteger(slot) && slot >= 0)
    .sort((a, b) => a - b);
}

export function getNextAvailableSlot() {
  const used = new Set(getAllocatedSlots());
  const maxSessions = getMaxSessions();
  for (let slot = 0; slot < maxSessions; slot += 1) {
    if (!used.has(slot)) {
      return slot;
    }
  }
  return -1;
}

export function getCapacitySnapshot() {
  const activeSessions = getSessionCount();
  const maxSessions = getMaxSessions();
  return {
    activeSessions,
    maxSessions,
    availableSessions: Math.max(0, maxSessions - activeSessions),
    hasCapacity: activeSessions < maxSessions && getNextAvailableSlot() >= 0,
    allocatedSlots: getAllocatedSlots(),
    sessionTtlSeconds: Math.round(getSessionTtlMs() / 1000),
    idleTtlSeconds: Math.round(getIdleTtlMs() / 1000),
  };
}

export function listSessionSummaries() {
  return Array.from(sessions.values()).map(serializeSession);
}

export function serializeSession(session) {
  const publicBase = getPublicBaseUrl().replace(/\/+$/g, "");
  const liveStreamUrl =
    session.runtime?.kind === "cloudflare_live_view" ||
    session.runtime?.kind === "browserless_live_url"
      ? cleanText(session.runtime.liveUrl || "")
      : "";
  const relativeStreamUrl = session.runtime?.kind === "novnc"
    ? getNoVncStreamPath(session.sessionId, session.viewerToken, publicBase)
    : liveStreamUrl ||
      `/sessions/${encodeURIComponent(session.sessionId)}/screenshot`;
  return {
    sessionId: session.sessionId,
    taskUuid: session.taskUuid,
    userId: session.userId,
    conversationId: session.conversationId,
    roleId: session.roleId,
    provider: session.provider,
    employerUrl: session.employerUrl,
    finalUrl: session.finalUrl,
    title: session.title,
    status: session.status,
    control: session.control,
    streamUrl:
      /^https?:\/\//i.test(relativeStreamUrl) || !publicBase
        ? relativeStreamUrl
        : publicBase + relativeStreamUrl,
    controlUrl: publicBase ? `${publicBase}/sessions/${encodeURIComponent(session.sessionId)}/control` : `/sessions/${encodeURIComponent(session.sessionId)}/control`,
    transport: session.runtime?.kind || session.transport,
    expiresAt: new Date(session.expiresAt).toISOString(),
    lastHeartbeatAt: new Date(session.lastHeartbeatAt).toISOString(),
    lastError: session.lastError,
  };
}

export function getSession(sessionId) {
  return sessions.get(cleanText(sessionId));
}

export function seedSessionForTest(overrides = {}) {
  if (process.env.NODE_ENV !== "test") {
    throw new Error("seedSessionForTest is only available during tests.");
  }
  const now = Date.now();
  const sessionId = cleanText(overrides.sessionId || crypto.randomUUID());
  const session = {
    sessionId,
    taskUuid: cleanText(overrides.taskUuid || ""),
    userId: Number(overrides.userId || 0) || 0,
    conversationId: Number(overrides.conversationId || 0) || 0,
    roleId: cleanText(overrides.roleId || ""),
    provider: cleanText(overrides.provider || "unknown").toLowerCase() || "unknown",
    employerUrl: cleanText(overrides.employerUrl || "https://apply.workable.com/example"),
    finalUrl: cleanText(overrides.finalUrl || ""),
    title: cleanText(overrides.title || ""),
    status: cleanText(overrides.status || "ready"),
    control: cleanText(overrides.control || "user_control"),
    transport: cleanText(overrides.transport || "test"),
    slot: Number(overrides.slot || 0) || 0,
    viewerToken: cleanText(overrides.viewerToken || "test-viewer-token"),
    runtime: overrides.runtime || null,
    createdAt: now,
    expiresAt: now + getSessionTtlMs(),
    lastHeartbeatAt: now,
    lastError: cleanText(overrides.lastError || ""),
  };
  sessions.set(sessionId, session);
  return session;
}

export async function clearSessionsForTest() {
  if (process.env.NODE_ENV !== "test") {
    throw new Error("clearSessionsForTest is only available during tests.");
  }
  sessions.clear();
}

export function hasValidViewerToken(session, token) {
  return Boolean(session?.viewerToken && cleanText(token) === session.viewerToken);
}

export function touchSession(session) {
  session.lastHeartbeatAt = Date.now();
  return session;
}

export async function createSession(payload) {
  const slot = getNextAvailableSlot();
  if (sessions.size >= getMaxSessions() || slot < 0) {
    const error = new Error("Remote browser capacity is full.");
    error.code = "capacity_full";
    throw error;
  }
  const now = Date.now();
  const sessionId = crypto.randomUUID();
  const transport = cleanText(payload.transport || process.env.SFFC_REMOTE_BROWSER_TRANSPORT || "novnc").toLowerCase();
  if (isManagedBrowserTransport(transport)) {
    const cooldown = getManagedBrowserRateLimitCooldown(transport);
    if (cooldown) {
      throw createManagedBrowserRateLimitError(cooldown.retryAfterSeconds);
    }
  }
  const session = {
    sessionId,
    taskUuid: cleanText(payload.taskUuid || payload.task_uuid || ""),
    userId: Number(payload.userId || payload.user_id || 0) || 0,
    conversationId: Number(payload.conversationId || payload.conversation_id || 0) || 0,
    roleId: cleanText(payload.roleId || payload.role_id || payload.jobsPostId || payload.jobs_post_id || ""),
    provider: cleanText(payload.provider || "unknown").toLowerCase() || "unknown",
    employerUrl: cleanText(payload.employerUrl || payload.employer_url || payload.url || ""),
    finalUrl: "",
    title: "",
    status: "starting",
    control: "user_control",
    transport,
    slot,
    viewerToken: crypto.randomBytes(24).toString("base64url"),
    runtime: null,
    createdAt: now,
    expiresAt: now + getSessionTtlMs(),
    lastHeartbeatAt: now,
    lastError: "",
  };
  sessions.set(sessionId, session);
  try {
    if (transport === "novnc") {
      session.runtime = await createNoVncSession(sessionId, session.employerUrl, session.provider, session.slot);
      session.finalUrl = session.employerUrl;
      session.title = "";
    } else if (
      transport === "cloudflare" ||
      transport === "cloudflare_live_view" ||
      transport === "browserless" ||
      transport === "browserless_live_url" ||
      transport === "managed_live_browser"
    ) {
      session.runtime = await createManagedLiveBrowserSession(
        sessionId,
        session.employerUrl,
        session.provider,
        transport
      );
      const snapshot = await getPageSnapshot(session.runtime);
      session.finalUrl = snapshot.finalUrl;
      session.title = snapshot.title;
    } else {
      session.runtime = await createBrowserSession(sessionId, session.employerUrl, session.provider);
      const snapshot = await getPageSnapshot(session.runtime);
      session.finalUrl = snapshot.finalUrl;
      session.title = snapshot.title;
    }
    session.status = "ready";
    touchSession(session);
    return serializeSession(session);
  } catch (error) {
    session.status = "failed";
    session.lastError = cleanText(error.message || "Remote browser failed to start.");
    await closeSession(sessionId);
    if (isManagedBrowserTransport(transport) && isUpstreamRateLimitError(error)) {
      const retryAfterSeconds =
        getRetryAfterSeconds(error) ||
        Math.ceil(getManagedBrowserRateLimitCooldownMs() / 1000);
      const retryAt = Date.now() + retryAfterSeconds * 1000;
      managedBrowserRateLimitCooldowns.set(
        normalizeManagedBrowserTransportKey(transport),
        retryAt
      );
      throw createManagedBrowserRateLimitError(retryAfterSeconds);
    }
    throw error;
  }
}

export async function navigateExistingSession(sessionId, url) {
  const session = getSession(sessionId);
  if (!session) {
    return null;
  }
  session.status = "navigating";
  touchSession(session);
  const snapshot = await navigateSession(session.runtime, url);
  session.employerUrl = cleanText(url);
  session.finalUrl = snapshot.finalUrl;
  session.title = snapshot.title;
  session.status = "ready";
  touchSession(session);
  return serializeSession(session);
}

export function setSessionControl(sessionId, control) {
  const session = getSession(sessionId);
  const cleanControl = cleanText(control || "");
  if (!session) {
    return null;
  }
  if (!["user_control", "emily_control", "waiting_for_user", "read_only"].includes(cleanControl)) {
    throw new Error("Unsupported remote browser control state.");
  }
  session.control = cleanControl;
  session.status = cleanControl === "waiting_for_user" ? "waiting_for_user" : "ready";
  touchSession(session);
  return serializeSession(session);
}

export async function closeSession(sessionId) {
  const session = getSession(sessionId);
  if (!session) {
    return false;
  }
  sessions.delete(session.sessionId);
  session.status = "closed";
  if (session.runtime?.kind === "novnc") {
    await closeNoVncSession(session.runtime);
  } else {
    await closeBrowserSession(session.runtime);
  }
  return true;
}

export async function closeExpiredSessions() {
  const now = Date.now();
  const expired = [];
  for (const session of sessions.values()) {
    if (session.expiresAt <= now || session.lastHeartbeatAt + getIdleTtlMs() <= now) {
      expired.push(session.sessionId);
    }
  }
  await Promise.all(expired.map((sessionId) => closeSession(sessionId)));
  return expired.length;
}

export async function closeAllSessions() {
  const ids = Array.from(sessions.keys());
  await Promise.all(ids.map((sessionId) => closeSession(sessionId)));
}
