import { auditEvent } from "./audit.js";
import { closeExpiredSessions } from "./sessions.js";

let cleanupTimer = null;

export function startCleanupLoop(intervalMs = 15000) {
  if (cleanupTimer) {
    return cleanupTimer;
  }
  cleanupTimer = setInterval(() => {
    closeExpiredSessions().then((count) => {
      if (count > 0) {
        auditEvent("remote_browser_expired_cleanup", { count });
      }
    }).catch((error) => {
      console.error("[sffc-remote-browser] cleanup failed", error);
    });
  }, Math.max(5000, Number(intervalMs) || 15000));
  cleanupTimer.unref?.();
  return cleanupTimer;
}

export function stopCleanupLoop() {
  if (!cleanupTimer) {
    return;
  }
  clearInterval(cleanupTimer);
  cleanupTimer = null;
}
