import test from "node:test";
import assert from "node:assert/strict";
import {
  clearSessionsForTest,
  getSession,
  seedSessionForTest,
  serializeSession,
  setSessionControl,
} from "../src/sessions.js";

test("updates remote browser control states", async () => {
  await clearSessionsForTest();
  const session = seedSessionForTest({
    sessionId: "control-test",
    control: "user_control",
  });

  assert.equal(session.control, "user_control");
  assert.equal(session.status, "ready");

  const emilyControl = setSessionControl("control-test", "emily_control");
  assert.equal(emilyControl.control, "emily_control");
  assert.equal(emilyControl.status, "ready");

  const waiting = setSessionControl("control-test", "waiting_for_user");
  assert.equal(waiting.control, "waiting_for_user");
  assert.equal(waiting.status, "waiting_for_user");

  const readOnly = setSessionControl("control-test", "read_only");
  assert.equal(readOnly.control, "read_only");
  assert.equal(readOnly.status, "ready");

  const userControl = setSessionControl("control-test", "user_control");
  assert.equal(userControl.control, "user_control");
  assert.equal(userControl.status, "ready");

  assert.equal(getSession("control-test").control, "user_control");
});

test("rejects unsupported remote browser control states", async () => {
  await clearSessionsForTest();
  seedSessionForTest({ sessionId: "bad-control-test" });

  assert.throws(
    () => setSessionControl("bad-control-test", "submit_without_user"),
    /Unsupported remote browser control state/
  );
});

test("serializes control state for the WordPress broker", async () => {
  await clearSessionsForTest();
  const session = seedSessionForTest({
    sessionId: "serialize-control-test",
    control: "waiting_for_user",
    status: "waiting_for_user",
  });
  const serialized = serializeSession(session);

  assert.equal(serialized.control, "waiting_for_user");
  assert.equal(serialized.status, "waiting_for_user");
  assert.match(serialized.controlUrl, /\/sessions\/serialize-control-test\/control$/);
});
