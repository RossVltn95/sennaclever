import test from "node:test";
import assert from "node:assert/strict";
import { isInternalSennaUrl, isValidEmployerUrl, safeTokenEquals } from "../src/auth.js";

test("validates external employer URLs", () => {
  assert.equal(isValidEmployerUrl("https://apply.workable.com/example/j/123"), true);
  assert.equal(isValidEmployerUrl("https://job-boards.greenhouse.io/example/jobs/123"), true);
  assert.equal(isValidEmployerUrl("https://joinsenna.com/jobs/example"), false);
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
