import test from "node:test";
import assert from "node:assert/strict";
import {
  isBlockedEmployerHostname,
  isInternalSennaUrl,
  isPrivateOrReservedIp,
  isValidEmployerUrl,
  safeTokenEquals,
} from "../src/auth.js";

test("validates external employer URLs", () => {
  assert.equal(isValidEmployerUrl("https://apply.workable.com/example/j/123"), true);
  assert.equal(isValidEmployerUrl("https://job-boards.greenhouse.io/example/jobs/123"), true);
  assert.equal(isValidEmployerUrl("https://joinsenna.com/jobs/example"), false);
  assert.equal(isValidEmployerUrl("https://localhost/jobs/example"), false);
  assert.equal(isValidEmployerUrl("https://127.0.0.1/jobs/example"), false);
  assert.equal(isValidEmployerUrl("https://10.0.0.5/jobs/example"), false);
  assert.equal(isValidEmployerUrl("https://169.254.169.254/latest/meta-data"), false);
  assert.equal(isValidEmployerUrl("javascript:alert(1)"), false);
  assert.equal(isValidEmployerUrl("mailto:test@example.com"), false);
});

test("detects internal Senna URLs", () => {
  assert.equal(isInternalSennaUrl("https://joinsenna.com/jobs/example"), true);
  assert.equal(isInternalSennaUrl("https://www.joinsenna.com/jobs/example"), true);
  assert.equal(isInternalSennaUrl("https://apply.workable.com/example"), false);
});

test("compares tokens safely", () => {
  assert.equal(safeTokenEquals("abc", "abc"), true);
  assert.equal(safeTokenEquals("abc", "abcd"), false);
  assert.equal(safeTokenEquals("", "abc"), false);
});

test("blocks private and reserved employer hosts", () => {
  assert.equal(isPrivateOrReservedIp("10.0.0.1"), true);
  assert.equal(isPrivateOrReservedIp("172.16.0.1"), true);
  assert.equal(isPrivateOrReservedIp("192.168.1.1"), true);
  assert.equal(isPrivateOrReservedIp("169.254.169.254"), true);
  assert.equal(isPrivateOrReservedIp("8.8.8.8"), false);
  assert.equal(isBlockedEmployerHostname("localhost"), true);
  assert.equal(isBlockedEmployerHostname("metadata.google.internal"), true);
  assert.equal(isBlockedEmployerHostname("apply.workable.com"), false);
});
