import test from "node:test";
import assert from "node:assert/strict";
import {
  clearSessionsForTest,
  getAllocatedSlots,
  getCapacitySnapshot,
  getNextAvailableSlot,
  getSession,
  seedSessionForTest,
  serializeSession,
  setSessionControl,
} from "../src/sessions.js";
import { isNoVncStaticAssetPath } from "../src/novnc.js";

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

test("serializes noVNC stream URL with auto-connect and viewer token", async () => {
  await clearSessionsForTest();
  const session = seedSessionForTest({
    sessionId: "novnc-stream-test",
    viewerToken: "viewer-token-123",
    runtime: {
      kind: "novnc",
      webPort: 7900,
    },
  });
  const serialized = serializeSession(session);
  const streamUrl = new URL(serialized.streamUrl, "https://remote.example.test");

  assert.equal(streamUrl.pathname, "/sessions/novnc-stream-test/novnc/vnc.html");
  assert.equal(streamUrl.searchParams.get("autoconnect"), "1");
  assert.equal(streamUrl.searchParams.get("resize"), "remote");
  assert.equal(streamUrl.searchParams.get("token"), "viewer-token-123");
  assert.match(
    streamUrl.searchParams.get("path") || "",
    /^sessions\/novnc-stream-test\/novnc\/websockify\?token=viewer-token-123$/
  );
});

test("identifies noVNC static assets separately from protected entrypoints", () => {
  assert.equal(
    isNoVncStaticAssetPath(["sessions", "abc", "novnc", "app", "styles", "base.css"]),
    true
  );
  assert.equal(
    isNoVncStaticAssetPath(["sessions", "abc", "novnc", "core", "rfb.js"]),
    true
  );
  assert.equal(
    isNoVncStaticAssetPath(["sessions", "abc", "novnc", "vnc.html"]),
    false
  );
  assert.equal(
    isNoVncStaticAssetPath(["sessions", "abc", "novnc", "websockify"]),
    false
  );
});

test("allocates the lowest available remote browser slot", async () => {
  await clearSessionsForTest();
  seedSessionForTest({ sessionId: "slot-0", slot: 0 });
  seedSessionForTest({ sessionId: "slot-2", slot: 2 });

  assert.deepEqual(getAllocatedSlots(), [0, 2]);
  assert.equal(getNextAvailableSlot(), 1);
});

test("reports capacity from active sessions and max sessions", async () => {
  await clearSessionsForTest();
  seedSessionForTest({ sessionId: "capacity-0", slot: 0 });
  const capacity = getCapacitySnapshot();

  assert.equal(capacity.activeSessions, 1);
  assert.equal(capacity.availableSessions, capacity.maxSessions - 1);
  assert.equal(capacity.hasCapacity, capacity.maxSessions > 1);
  assert.deepEqual(capacity.allocatedSlots, [0]);
  assert.ok(capacity.sessionTtlSeconds >= 60);
  assert.ok(capacity.idleTtlSeconds >= 30);
});
