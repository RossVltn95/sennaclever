import { cleanText } from "./auth.js";

const buckets = new Map();

export function getClientKey(request) {
  const forwarded = cleanText(request.headers["x-forwarded-for"] || "");
  const firstForwarded = forwarded.split(",")[0]?.trim() || "";
  return (
    firstForwarded ||
    cleanText(request.socket?.remoteAddress || "") ||
    "unknown-client"
  );
}

export function checkRateLimit(key, options = {}) {
  const cleanKey = cleanText(key || "unknown-client");
  const limit = Math.max(1, Number(options.limit || 20) || 20);
  const windowMs = Math.max(1000, Number(options.windowMs || 60000) || 60000);
  const now = Date.now();
  const bucket = buckets.get(cleanKey) || { count: 0, resetAt: now + windowMs };
  if (bucket.resetAt <= now) {
    bucket.count = 0;
    bucket.resetAt = now + windowMs;
  }
  bucket.count += 1;
  buckets.set(cleanKey, bucket);
  return {
    allowed: bucket.count <= limit,
    limit,
    remaining: Math.max(0, limit - bucket.count),
    resetAt: bucket.resetAt,
  };
}

export function clearRateLimitsForTest() {
  if (process.env.NODE_ENV !== "test") {
    throw new Error("clearRateLimitsForTest is only available during tests.");
  }
  buckets.clear();
}
