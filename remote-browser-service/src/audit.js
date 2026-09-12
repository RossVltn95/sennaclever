import { cleanText } from "./auth.js";

export function auditEvent(event, details = {}) {
  const payload = {
    event: cleanText(event || "remote_browser_event"),
    service: "sffc-remote-browser",
    at: new Date().toISOString(),
    ...details,
  };
  console.log(JSON.stringify(payload));
}

export function auditSessionEvent(event, session, details = {}) {
  auditEvent(event, {
    sessionId: cleanText(session?.sessionId || session?.session_id || ""),
    roleId: cleanText(session?.roleId || session?.role_id || ""),
    provider: cleanText(session?.provider || "unknown"),
    userId: Number(session?.userId || session?.user_id || 0) || 0,
    conversationId:
      Number(session?.conversationId || session?.conversation_id || 0) || 0,
    status: cleanText(session?.status || ""),
    control: cleanText(session?.control || ""),
    employerHost: getHost(session?.employerUrl || session?.employer_url || ""),
    ...details,
  });
}

export function getHost(url) {
  try {
    return new URL(cleanText(url)).hostname.toLowerCase();
  } catch (error) {
    return "";
  }
}
