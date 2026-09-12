import crypto from "node:crypto";
import { cleanText } from "./auth.js";
import {
  closeBrowserSession,
  createBrowserSession,
  getPageSnapshot,
  navigateSession,
} from "./browser.js";
import { closeNoVncSession, createNoVncSession, getNoVncStreamPath } from "./novnc.js";

const sessions = new Map();

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

export function listSessionSummaries() {
  return Array.from(sessions.values()).map(serializeSession);
}

export function serializeSession(session) {
  const publicBase = getPublicBaseUrl().replace(/\/+$/g, "");
  const relativeStreamUrl = session.runtime?.kind === "novnc"
    ? `${getNoVncStreamPath(session.sessionId)}&token=${encodeURIComponent(session.viewerToken)}`
    : `/sessions/${encodeURIComponent(session.sessionId)}/screenshot`;
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
    streamUrl: publicBase ? publicBase + relativeStreamUrl : relativeStreamUrl,
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
  if (sessions.size >= getMaxSessions()) {
    const error = new Error("Remote browser capacity is full.");
    error.code = "capacity_full";
    throw error;
  }
  const now = Date.now();
  const sessionId = crypto.randomUUID();
  const transport = cleanText(payload.transport || process.env.SFFC_REMOTE_BROWSER_TRANSPORT || "novnc").toLowerCase();
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
    slot: sessions.size,
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
