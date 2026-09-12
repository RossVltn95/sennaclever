import test from "node:test";
import assert from "node:assert/strict";
import { checkRateLimit, clearRateLimitsForTest, getClientKey } from "../src/rate-limit.js";

test("rate limiter blocks after the configured threshold", () => {
  clearRateLimitsForTest();
  assert.equal(checkRateLimit("client-a", { limit: 2, windowMs: 60000 }).allowed, true);
  assert.equal(checkRateLimit("client-a", { limit: 2, windowMs: 60000 }).allowed, true);
  const blocked = checkRateLimit("client-a", { limit: 2, windowMs: 60000 });
  assert.equal(blocked.allowed, false);
  assert.equal(blocked.remaining, 0);
});

test("rate limiter scopes by key", () => {
  clearRateLimitsForTest();
  assert.equal(checkRateLimit("client-a", { limit: 1, windowMs: 60000 }).allowed, true);
  assert.equal(checkRateLimit("client-a", { limit: 1, windowMs: 60000 }).allowed, false);
  assert.equal(checkRateLimit("client-b", { limit: 1, windowMs: 60000 }).allowed, true);
});

test("client key prefers first forwarded address", () => {
  const request = {
    headers: {
      "x-forwarded-for": "203.0.113.20, 10.0.0.1",
    },
    socket: {
      remoteAddress: "127.0.0.1",
    },
  };
  assert.equal(getClientKey(request), "203.0.113.20");
});
