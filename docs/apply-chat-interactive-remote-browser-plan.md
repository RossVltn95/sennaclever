# Apply Chat Interactive Employer Page Plan

## Purpose

Replace the weak static screenshot fallback with a provider-aware employer page review system inside apply-chat.

The desired fallback order is:

1. Direct iframe embed if the employer page allows it.
2. Interactive remote browser session if the page blocks embedding or needs real user interaction.
3. Static screenshot fallback only when the remote browser cannot be started or safely controlled.

This should make `sffc-crm-apply-results__review-frame` feel like a real application window where possible, not a dead preview image.

## Current Problem

The current review area can render an iframe or a screenshot-like preview. When an employer blocks iframe embedding, the fallback is visually useful but not operationally useful:

- the user cannot click through the employer page;
- the user cannot complete account, verification, or custom fields in-place;
- Emily cannot share control of the visible page with the candidate;
- the static image can make the application look more broken than it is;
- the same fallback is used for very different cases: blocked iframe, unsupported provider, missing URL, account wall, or worker issue.

The core mistake to avoid is trying to hack around iframe restrictions. Browser security will still block pages with `X-Frame-Options` or `Content-Security-Policy: frame-ancestors`. The stronger architecture is to run the employer page in a server-side browser and stream that browser into the chat.

## Target User Experience

When a user opens or processes a role:

```text
Emily: I’m checking whether the employer form can open inside Senna.
```

If iframe is allowed:

```text
Emily: The employer form opens here. I’ll keep the role and CV context beside it.
[Embedded employer form]
```

If iframe is blocked but remote browser is available:

```text
Emily: This employer blocks normal embeds, so I’ve opened it in a secure Senna browser window instead.
[Interactive browser window]
```

If remote browser is unavailable:

```text
Emily: I couldn’t open an interactive browser window right now, so I’m showing a preview and keeping the employer link ready.
[Static preview + Open employer form]
```

The user should always understand which state they are in:

- `Embedded employer form`
- `Secure browser session`
- `Preview only`
- `Waiting for verification`
- `Needs user input`
- `Submitted`
- `Could not continue`

## Rendering Decision Model

Create a single decision object for the review area.

```ts
type EmployerReviewSurfaceDecision = {
  roleId: string;
  taskUuid?: string;
  provider: 'workable' | 'greenhouse' | 'workday' | 'successfactors' | 'teamtailor' | 'simple_form' | 'unknown';
  employerUrl: string;
  embedUrl: string;
  mode: 'iframe_embed' | 'remote_browser' | 'static_preview' | 'external_link_only';
  reason:
    | 'known_embed_allowed'
    | 'known_embed_blocked'
    | 'iframe_probe_passed'
    | 'iframe_probe_failed'
    | 'remote_browser_unavailable'
    | 'missing_employer_url'
    | 'unsupported_provider'
    | 'security_policy';
  capabilities: {
    userCanInteract: boolean;
    emilyCanObserve: boolean;
    emilyCanAutofill: boolean;
    fileUploadSupported: boolean;
    finalSubmitAllowed: boolean;
  };
  fallback: {
    nextMode: 'remote_browser' | 'static_preview' | 'external_link_only' | null;
    message: string;
  };
};
```

Every UI render path should consume this decision. No component should independently decide iframe vs screenshot vs remote browser.

## GitHub And Railway Deployment Model

The implementation should live in the existing GitHub project:

```text
https://github.com/RossVltn95/sennaclever
```

Use the same Railway project if preferred, but keep the runtime responsibilities separated into distinct Railway services.

```text
GitHub repo: RossVltn95/sennaclever
  ├─ WordPress plugin code
  │    └─ deployed to the WordPress/Senna site by the normal plugin deployment process
  ├─ application-worker/
  │    └─ Railway service: senna-application-worker
  └─ remote-browser-service/
       └─ Railway service: senna-remote-browser
```

The two Railway services must not be merged into one process:

- `senna-application-worker` is the existing background automation worker. It polls WordPress for queued application tasks, opens employer pages in Puppeteer, fills fields, captures evidence, and reports task status back to WordPress.
- `senna-remote-browser` is the new interactive browser service. It creates short-lived live Chromium sessions and streams the viewport/control surface into apply-chat when iframe embedding fails.

Why keep them separate:

- different lifecycle: background task polling versus live user session streaming;
- different scaling: workers scale by queued task volume, remote browsers scale by concurrent open sessions;
- different timeout model: application tasks can run and finish, browser sessions need heartbeat/expiry;
- different security boundary: remote sessions expose an interactive employer page to the user;
- different failure mode: a streaming failure should not crash or block application task polling.

Railway service layout:

```text
Railway project
  ├─ Service: senna-application-worker
  │    Root directory: application-worker
  │    Build: application-worker/Dockerfile
  │    Start: xvfb-run -a npm start
  │    Health: /health
  │
  └─ Service: senna-remote-browser
       Root directory: remote-browser-service
       Build: remote-browser-service/Dockerfile
       Start: npm start
       Health: /health
```

Required environment variables for the existing application worker:

```bash
SFFC_WP_AJAX_URL=https://joinsenna.com/wp-admin/admin-ajax.php
SFFC_APPLICATION_WORKER_TOKEN=...
SFFC_WORKER_ID=railway-worker-1
SFFC_WORKER_ALLOW_FINAL_SUBMIT=0
SFFC_WORKER_ALLOW_WORKDAY_ACCOUNT_CREATION=0
SFFC_WORKER_ALLOW_SUCCESSFACTORS_ACCOUNT_CREATION=0
PUPPETEER_EXECUTABLE_PATH=/usr/bin/google-chrome-stable
```

Required environment variables for the new remote-browser service:

```bash
SFFC_REMOTE_BROWSER_TOKEN=...
SFFC_REMOTE_BROWSER_PUBLIC_URL=https://<railway-remote-browser-domain>
SFFC_REMOTE_BROWSER_MAX_SESSIONS=5
SFFC_REMOTE_BROWSER_SESSION_TTL_SECONDS=900
SFFC_REMOTE_BROWSER_IDLE_TTL_SECONDS=180
SFFC_REMOTE_BROWSER_ALLOWED_ORIGIN=https://joinsenna.com
SFFC_REMOTE_BROWSER_CHROME_EXECUTABLE=/usr/bin/google-chrome-stable
SFFC_REMOTE_BROWSER_PROFILE_ROOT=/tmp/sffc-remote-browser
```

Required WordPress/plugin configuration:

```php
define('SFFC_REMOTE_BROWSER_URL', 'https://<railway-remote-browser-domain>');
define('SFFC_REMOTE_BROWSER_TOKEN', 'same-token-as-railway-service');
```

WordPress should be the broker between apply-chat and the remote-browser service:

```text
Apply-chat UI
  -> WordPress AJAX/REST endpoint
  -> validates user, role, provider, and external employer URL
  -> signs request to Railway remote-browser service
  -> returns session status + stream URL to apply-chat
```

The browser service should not accept arbitrary public browser-session creation from the frontend. It should require a signed server-side request from WordPress, using `SFFC_REMOTE_BROWSER_TOKEN`.

Recommended new repo files:

```text
remote-browser-service/
  ├─ Dockerfile
  ├─ railway.json
  ├─ package.json
  ├─ README.md
  └─ src/
       ├─ server.js
       ├─ sessions.js
       ├─ browser.js
       ├─ auth.js
       └─ cleanup.js
```

Initial implementation should use one of these stream approaches:

- first pass: Chromium + noVNC/websockify if the fastest working interactive browser is the priority;
- stronger later pass: Chromium CDP + custom streamed viewport/control protocol if tighter Emily automation and UI control are needed.

Rollout flags:

```php
define('SFFC_REMOTE_BROWSER_ENABLED', false);
define('SFFC_REMOTE_BROWSER_IFRAME_BLOCKED_ONLY', true);
define('SFFC_REMOTE_BROWSER_ALLOWED_PROVIDERS', 'workable,greenhouse,workday,successfactors,teamtailor,simple_form');
```

Production rollout order:

1. Deploy `senna-remote-browser` service to Railway with `/health` only.
2. Add WordPress health probe and admin diagnostic status.
3. Enable remote browser for admin/test users only.
4. Enable for one provider with known iframe blocking.
5. Enable for paying users.
6. Keep screenshot fallback active for all failures.

## Phase 1: Audit And Stabilise Existing Embed Logic

Goal: make the current iframe path deterministic before adding remote browser complexity.

Status: implemented in the plugin contract as the first stabilisation pass. The review surface now receives a single normalized mode (`auto`, `embed`, `remote_browser`, or `screenshot`), internal Senna URLs are stripped from application workspace URLs, and the frontend renders iframe/screenshot/future remote-browser states from one decision helper.

Tasks:

- Identify every render path that creates `sffc-crm-apply-results__review-frame`.
- Identify every render path that creates `sffc-crm-apply-results__review-image-link`.
- Confirm the source of `application_url`, `application_workspace_url`, `application_embed_url`, and `application_embed_mode`.
- Confirm internal Senna URLs are blocked from review/application execution paths.
- Add a single helper that returns the employer URL used for review.
- Add a single helper that returns the employer URL used for application execution.
- Keep Senna public job URLs as `role_url` or `view_url` only.
- Do not use `joinsenna.com/opportunity/...` or job permalinks as employer application URLs.

Acceptance criteria:

- Workable jobs with a real Workable URL use the Workable URL.
- Greenhouse jobs with a real Greenhouse URL use the Greenhouse URL.
- SuccessFactors jobs use the external SuccessFactors/career-site URL.
- Teamtailor jobs use the external Teamtailor URL.
- Missing employer URLs show a clear no-application-link state.
- No review surface navigates to a Senna 404 page.

## Phase 2: Iframe Probe And Provider Rules

Goal: use direct iframe only when it is likely to work.

Status: implemented as the second stabilisation pass. The frontend now has a provider-aware embed policy, emits review mode/reason diagnostics, probes iframe loads with explicit `loading`, `loaded`, `blocked`, and `timeout` states, and keeps static screenshot fallback active until the remote-browser service is available.

Add provider-aware embed policy:

```ts
type EmbedPolicy = {
  provider: string;
  defaultMode: 'iframe_embed' | 'remote_browser' | 'static_preview';
  iframeAllowedHosts: string[];
  iframeBlockedHosts: string[];
  requiresRemoteBrowserForApply: boolean;
};
```

Provider defaults:

- Workable: try iframe for review pages, remote browser for active apply if blocked.
- Greenhouse: try iframe only for hosted boards known to allow it; otherwise remote browser.
- Workday: remote browser by default.
- SuccessFactors: remote browser by default.
- Teamtailor: try iframe for public posting, remote browser for application form.
- Simple form: iframe when same-origin or explicitly marked embed-safe; otherwise remote browser.
- Unknown: probe iframe once, then remote browser.

Iframe probe behaviour:

- Render iframe with `data-src`.
- Set a short load timer.
- Mark `data-sffc-review-frame-state="loaded"` if `load` fires.
- Mark as `blocked` if load fails, timeout expires, or visible content is unusable.
- Never keep showing an empty iframe as if it worked.

Acceptance criteria:

- Iframe state is visible in diagnostics.
- A blocked iframe automatically escalates to remote browser when enabled.
- The user sees one coherent transition message, not several repeated Emily updates.

## Phase 3: Remote Browser Service

Goal: create a short-lived, interactive browser session that can live inside apply-chat.

Status: implemented as a separate `remote-browser-service/` Railway service foundation. The service now supports a proven noVNC transport by default: per session it starts an isolated `Xvfb` display, Google Chrome, `x11vnc`, and `websockify`, then proxies the noVNC client and WebSocket through the Node service under `/sessions/:id/novnc/...`. The existing Puppeteer screenshot/control path remains available as `transport: puppeteer` for development fallback and last-resort preview behaviour.

Recommended architecture:

```text
WordPress plugin
  ↓ create session request
Railway service: senna-remote-browser
  ↓ starts isolated Chromium
Streaming gateway
  ↓ WebSocket/VNC/WebRTC stream
Apply-chat remote browser component
```

This is a new Railway service in the same `RossVltn95/sennaclever` GitHub project, separate from the existing `application-worker` service.

Implemented first production transport:

- Node HTTP broker with bearer-token API auth.
- Short-lived per-session viewer token for the noVNC iframe.
- Isolated Chrome profile directory per session.
- Isolated X display and VNC port per session.
- noVNC stream URL returned as the session `streamUrl`.
- Capacity guard, TTL cleanup, idle cleanup, and explicit close.
- Internal Senna URLs, localhost, and invalid URLs rejected before browser launch.

Deferred transport upgrades:

- BrowserBox/Hyper-Frame style browser-in-browser if the noVNC UX is not polished enough.
- Chromium CDP + custom viewport stream if we need tighter Emily automation and lower bandwidth.

Remote browser session object:

```ts
type RemoteBrowserSession = {
  sessionId: string;
  taskUuid?: string;
  userId?: number;
  conversationId?: number;
  roleId: string;
  provider: string;
  employerUrl: string;
  status:
    | 'starting'
    | 'ready'
    | 'navigating'
    | 'user_control'
    | 'emily_control'
    | 'waiting_for_user'
    | 'closed'
    | 'failed';
  streamUrl: string;
  controlUrl?: string;
  cdpEndpoint?: string;
  expiresAt: string;
  lastHeartbeatAt: string;
};
```

Required service endpoints:

- `POST /sessions` creates a remote browser session for a validated employer URL.
- `GET /sessions/:id` returns status and stream URLs.
- `GET /sessions/:id/novnc/vnc.html?...` serves the interactive browser client.
- `GET/WS /sessions/:id/novnc/websockify` proxies the noVNC WebSocket.
- `GET /sessions/:id/screenshot` returns screenshot fallback output for Puppeteer transport sessions.
- `POST /sessions/:id/navigate` navigates to an employer URL.
- `POST /sessions/:id/control` switches between user control and Emily/worker control.
- `POST /sessions/:id/upload` attaches a CV file to the browser session.
- `POST /sessions/:id/close` closes the session.
- `GET /health` reports capacity.

Acceptance criteria:

- A blocked iframe can create a remote session within an acceptable timeout once WordPress broker endpoints are added.
- The returned noVNC `streamUrl` can be embedded by the apply-chat remote browser component.
- The user can click, type, scroll, and interact with the employer page inside chat through noVNC.
- The remote browser is isolated per user/session with its own display, Chrome profile, VNC port, and viewer token.
- Closing the chat or switching roles can call `POST /sessions/:id/close`.
- Expired and idle sessions are cleaned up automatically.

## Phase 4: Apply-Chat Remote Browser Component

Goal: add a polished UI surface that fits the chat.

Status: implemented as the first apply-chat integration pass. The chat now has a `sffc-crm-apply-results__remote-browser` component, WordPress broker endpoints for create/status/close, server-side session ownership checks, and iframe-failure escalation from direct embed to secure browser before static screenshot fallback.

New component class:

```text
sffc-crm-apply-results__remote-browser
```

Suggested structure:

```html
<section class="sffc-crm-apply-results__remote-browser">
  <header class="sffc-crm-apply-results__remote-browser-bar">
    <span>Secure browser session</span>
    <strong>Workable application route</strong>
    <a>Open employer form</a>
    <button>Refresh</button>
    <button>Close</button>
  </header>
  <div class="sffc-crm-apply-results__remote-browser-viewport">
    <!-- noVNC/browser stream/web component -->
  </div>
  <footer class="sffc-crm-apply-results__remote-browser-status">
    Emily can help fill fields, but final submission needs explicit confirmation.
  </footer>
</section>
```

Design requirements:

- Must fit inside the apply-chat column.
- Must not overflow on mobile.
- Use stable height with responsive constraints.
- Include loading, ready, failed, expired, and closed states.
- Avoid looking like a random external embed.
- Clearly label that this is a secure remote browser, not a static screenshot.

Mobile behaviour:

- Use a taller viewport when opened.
- Allow full-screen mode.
- Keep the composer accessible.
- Do not trap scrolling permanently.
- Provide a clear close/collapse action.

Acceptance criteria:

- Desktop and mobile screenshots show no overflow.
- The remote browser status is understandable without reading debug text.
- User can recover if the session expires.

## Phase 5: Shared Control With Emily

Goal: support both candidate interaction and Emily automation.

Status: first shared-control layer implemented. The remote browser service already stores `user_control`, `emily_control`, `waiting_for_user`, and `read_only` states; WordPress now brokers `sffc_crm_apply_chat_remote_browser_control` with the same session ownership checks as create/status/close; and the apply-chat remote browser component exposes compact in-card controls for "Take control" and "Let Emily drive" without creating extra Emily messages. Unsupported control states now fail with a clean validation error, and unit coverage guards the remote service state transitions.

Control states:

- `user_control`: user can interact directly.
- `emily_control`: worker/automation is filling fields.
- `waiting_for_user`: Emily needs verification, login, custom answer, CAPTCHA, or final confirmation.
- `read_only`: user can view but not control during sensitive automation.

Rules:

- Emily may fill standard fields only after user has requested application processing.
- Emily must not final-submit unless the configured final-submit permission is enabled and the user explicitly confirms.
- If CAPTCHA appears, switch to `waiting_for_user`.
- If email verification appears, switch to `waiting_for_user`.
- If custom employer question appears and confidence is low, ask the user.
- If the user takes control, pause automation.
- If Emily resumes, log the transition.

Acceptance criteria:

- No double-submits. Not fully complete until the application worker consumes the remote-browser control state before final submission.
- No hidden final submit. Partially complete: UI copy and state labels reinforce explicit confirmation; worker-side enforcement remains in provider execution.
- User sees what Emily is doing. Partially complete: remote browser state/status copy is visible; provider-specific step telemetry still belongs to Phase 6 and Phase 11.
- Automation pauses cleanly when the user interacts. Partially complete: state handoff exists; application-worker integration with this handoff is still required.

## Phase 6: Provider Integration

Goal: map provider-specific behaviour to the three rendering modes.

Status: first provider integration layer implemented. The frontend review-surface decision now consumes a single provider policy catalog for Workable, Greenhouse, Workday, SuccessFactors, Teamtailor, and simple forms. The policy controls default mode, iframe allow/block hints, remote-browser requirement, provider label, status label, and diagnostic reason. PHP payload generation now applies matching provider-informed default embed modes so apply-chat results do not rely on ambiguous `auto` where the provider is already known. A dedicated regression script guards the provider matrix across the frontend policy and PHP defaults.

Workable:

- Try public page iframe where allowed.
- Remote browser for apply flow.
- Detect account gates and verification prompts.

Greenhouse:

- Try iframe for hosted board/application form only when allowed.
- Remote browser for blocked boards or security-code flows.
- Surface security-code request clearly.

Workday:

- Remote browser by default.
- Account creation/login must be explicit.
- Show account and verification state.

SuccessFactors:

- Remote browser by default.
- Use existing schema extraction for field awareness.
- Keep final submit guarded.

Teamtailor:

- Public job page may iframe.
- Application form should escalate to remote browser when blocked.
- Detect file upload and screening questions.

Simple form:

- Iframe if same-origin or known embed-safe.
- Remote browser if cross-origin or uncertain.
- Static fallback only if remote browser unavailable.

Acceptance criteria:

- Each provider has a documented default mode. Complete for the review surface and PHP payload defaults.
- Each provider has a fallback mode. Complete for iframe -> remote browser -> screenshot routing; provider-specific worker fallbacks still need live adapter validation.
- Provider status messages use the same state names across the UI. Partially complete: review cards now use provider policy labels; application-worker step telemetry remains in Phase 11 observability.

## Phase 7: Static Screenshot As Last Resort

Goal: keep screenshots useful without pretending they are interactive.

Static preview should show only when:

- remote browser service is disabled;
- remote browser capacity is exhausted;
- remote browser session creation fails;
- security policy prevents safe remote interaction;
- employer page requires unsupported browser/device checks;
- worker cannot load page after retries.

Screenshot card must include:

- clear `Preview only` label;
- employer URL host;
- provider;
- reason why it is not interactive;
- `Open employer form` button;
- `Retry interactive browser` button if eligible.

Acceptance criteria:

- No screenshot is presented as an interactive form.
- User always has an external open option.
- Retry path is available when the failure is temporary.

## Phase 8: Security And Privacy

Remote browser introduces higher risk than screenshots.

Status: first security layer implemented. The remote-browser service now rejects Senna/internal URLs, localhost, private/reserved IP ranges, metadata hosts, and hostnames that resolve to private/reserved addresses before browser launch or navigation. It also rate-limits session creation and browser actions, logs lifecycle/security events as JSON lines, keeps isolated short-lived sessions, and keeps file upload unavailable until explicit shared-control safeguards exist. WordPress now rate-limits create/status/control/close broker requests, blocks obvious provider/URL mismatches, audits remote-browser lifecycle events to the PHP error log, and keeps session access tied to logged-in user or guest session token.

Required controls:

- Short-lived signed session tokens. Complete for viewer tokens; API token remains server-to-server.
- Session tied to user/session/conversation/task. Complete at WordPress broker level and service metadata level.
- Employer URL allowlist/validation. Complete as denylist plus provider/URL matching; a stricter positive allowlist can be added later if required.
- Block internal admin URLs and localhost/private network targets. Complete in WordPress broker and remote-browser service, including DNS resolution guard.
- Clear session expiry. Complete via TTL and idle TTL.
- Automatic browser cleanup. Complete, with cleanup audit event.
- Per-user isolation. Complete for browser profile/display/session process separation.
- No cross-user browser reuse. Complete at broker/session-token layer.
- No persistent cookies unless explicitly required and scoped. Complete for current session model: no shared browser profiles.
- CV files stored temporarily and deleted after use. Not applicable to remote browser yet because upload is deliberately disabled; must be revisited when upload ships.
- Audit logs for navigation, upload, autofill, control handoff, final-submit attempts, and session close. Partially complete: session create, navigation, control, upload-blocked, close, failure, rate-limit, and cleanup are logged; autofill/final-submit belong to worker integration.
- Rate limits per user and globally. Partially complete: per-client broker/service limits and max session capacity exist; distributed/global limits need Redis or Railway-side service coordination if scaling horizontally.
- Capacity guard before creating a browser. Complete in service.

Never allow:

- remote browser navigation to WordPress admin;
- arbitrary URL browsing from user text;
- access to another user's application session;
- final submission without explicit permission;
- hidden file uploads without user consent.

## Phase 9: WordPress Plugin Changes

Status: first pass implemented. The apply-chat plugin now exposes the review-surface decision AJAX endpoint, localizes its nonce, accepts `remote_browser` as a valid admin/import/runtime embed mode, persists the last review decision metadata on jobs posts, and keeps provider-to-URL safety checks in the broker before starting secure browser sessions.

New AJAX endpoints:

- `sffc_crm_apply_chat_review_surface_decision` - complete
- `sffc_crm_apply_chat_remote_browser_create` - complete
- `sffc_crm_apply_chat_remote_browser_status` - complete
- `sffc_crm_apply_chat_remote_browser_close` - complete
- `sffc_crm_apply_chat_remote_browser_control` - complete

New persisted metadata:

- `_sffc_application_embed_mode`: `auto|embed|remote_browser|screenshot` - complete
- `_sffc_application_embed_last_checked_at` - complete
- `_sffc_application_embed_last_status` - complete
- `_sffc_application_remote_browser_supported` - complete

Update existing mode enum:

Current values appear to use:

```text
auto
embed
screenshot
```

Extend to:

```text
auto
embed
remote_browser
screenshot
```

Compatibility rule:

- Existing `screenshot` stays valid.
- Existing `auto` can choose any of the three modes.
- `embed` forces iframe-first but still escalates if blocked.
- `remote_browser` skips iframe probing.

Implementation notes:

- The admin job editor now preserves `remote_browser` instead of downgrading it to `auto`.
- Feed/import inference now stores `remote_browser` for Workday, SuccessFactors, and Teamtailor application-form URLs that should use the secure browser route.
- The review-surface endpoint rejects missing/internal Senna URLs as static fallback decisions rather than allowing them into employer application routing.
- Review decisions are logged and persisted so later UI and worker phases can inspect the last chosen surface.

## Phase 10: Worker / Railway Changes

Status: first pass implemented as a separate Railway service in `remote-browser-service/`. The service has its own Dockerfile, `railway.json`, Chrome/noVNC runtime dependencies, isolated session storage, TTL/idle cleanup, health/capacity reporting, max concurrent session enforcement, and deterministic noVNC slot allocation so active sessions do not collide on display/VNC ports.

Either extend the existing application worker or create a separate remote-browser service.

Recommended:

- Keep the current application worker focused on queued application tasks.
- Add a separate remote browser service for interactive sessions.

Why:

- Application tasks are queued/background.
- Remote browser sessions are interactive/real-time.
- Different scaling and timeout needs.
- A user-controlled browser should not block application task polling.

Railway/service needs:

- Chrome installed. Complete in `remote-browser-service/Dockerfile`.
- noVNC/streaming server or BrowserBox service. Complete with noVNC, Xvfb, x11vnc, and websockify.
- Session storage. Complete in in-memory session registry for the first single-instance Railway service.
- Cleanup loop. Complete for TTL and idle expiry.
- Health and capacity endpoint. Complete with public `/health` and token-protected `/capacity`.
- Memory/CPU limits. Configure in Railway service settings; the app enforces max sessions and TTLs.
- Max concurrent sessions. Complete via `SFFC_REMOTE_BROWSER_MAX_SESSIONS`.
- Session TTL, likely 10-20 minutes. Complete via `SFFC_REMOTE_BROWSER_SESSION_TTL_SECONDS` and `SFFC_REMOTE_BROWSER_IDLE_TTL_SECONDS`.

Minimum environment variables:

```text
SFFC_REMOTE_BROWSER_ENABLED=1
SFFC_REMOTE_BROWSER_SERVICE_URL=
SFFC_REMOTE_BROWSER_SERVICE_TOKEN=
SFFC_REMOTE_BROWSER_MAX_SESSIONS=
SFFC_REMOTE_BROWSER_SESSION_TTL_SECONDS=1200
SFFC_REMOTE_BROWSER_ALLOWED_HOSTS=
```

Railway setup:

```text
Project: RossVltn95/sennaclever
Service: senna-remote-browser
Root Directory: remote-browser-service
Dockerfile Path: Dockerfile
Start Command: npm start
Healthcheck Path: /health
Healthcheck Timeout: 60s
```

If Railway is configured from the repository root instead of a service root directory, use `remote-browser-service/Dockerfile` as the Dockerfile path.

## Phase 11: Observability

Status: first pass implemented. Review-surface decisions and frontend surface outcomes now log through WordPress, while the remote-browser service logs lifecycle, readiness, capacity, navigation failure, control, close, cleanup, and rate-limit events as structured JSON.

Log every surface decision:

```json
{
  "event": "apply_review_surface_decision",
  "role_id": "853",
  "provider": "workable",
  "mode": "remote_browser",
  "reason": "iframe_probe_failed",
  "employer_host": "jobs.workable.com"
}
```

Log remote session lifecycle:

- `remote_browser_create_requested` - complete
- `remote_browser_created` - complete
- `remote_browser_ready` - complete
- `remote_browser_control_changed` - complete
- `remote_browser_navigation_failed` - complete
- `remote_browser_expired` - partial via `remote_browser_expired_cleanup`
- `remote_browser_closed` - complete with duration
- `remote_browser_capacity_exhausted` - complete

Metrics:

- iframe success rate by provider/host - supported by `apply_review_surface_iframe_*` events.
- remote browser creation success rate - supported by broker/service create, ready, failed events.
- time to interactive browser - supported by `remote_browser_ready.startupMs`.
- session duration - supported by `remote_browser_closed.durationMs`.
- screenshot fallback rate - supported by `apply_review_surface_screenshot_preview_*` events.
- application completion rate after remote browser - pending worker/application outcome correlation.
- failure rate by provider - supported by provider/host on service and broker failure events.
- user exits after static preview - pending frontend close/abandon analytics beyond review-surface telemetry.

## Phase 12: Testing Plan

Unit tests:

- URL validation rejects `joinsenna.com`, localhost, private IPs, empty URLs, and invalid URLs.
- URL validation accepts known ATS domains.
- decision model chooses iframe, remote browser, or screenshot correctly.
- provider policy defaults are stable.

Component tests:

- iframe loading state.
- blocked iframe escalation.
- remote browser loading/ready/failed/expired states.
- screenshot fallback messaging.
- mobile layout.

Integration tests:

- Workable embed allowed path.
- Workable blocked path to remote browser.
- Greenhouse blocked path to remote browser.
- Workday remote browser default.
- SuccessFactors remote browser default.
- Teamtailor public page iframe then application remote browser.
- Missing URL external-link-only failure.

Browser tests:

- Open role result.
- Render review surface.
- Force iframe fail.
- Create remote browser.
- Interact with page.
- Close session.
- Reopen session.
- Verify no horizontal overflow.

Load tests:

- 1, 5, 10, 25 concurrent sessions.
- Session cleanup after TTL.
- Capacity exhausted fallback.
- Worker/task polling continues while remote sessions are active.

Security tests:

- Attempt to navigate remote browser to Senna admin.
- Attempt localhost/private IP.
- Attempt cross-user session access.
- Attempt stale token reuse.
- Attempt final submit without confirmation.

## Rollout Plan

Stage 1: Decision logging only

- Compute mode decision.
- Keep current UI.
- Log what would have happened.

Stage 2: Iframe probe cleanup

- Make iframe success/failure explicit.
- Screenshot fallback remains.

Stage 3: Remote browser internal test

- Enable for admins only.
- Use a small provider set: Workable, Teamtailor, Greenhouse.

Stage 4: Paying members beta

- Enable for paying users only.
- Limit concurrency.
- Keep screenshots as fallback.

Stage 5: Provider expansion

- Add Workday and SuccessFactors once account/verification flows are stable.

Stage 6: General availability

- Enable for all eligible application flows.
- Maintain kill switch.

Required kill switches:

```text
SFFC_REMOTE_BROWSER_ENABLED=0
SFFC_REMOTE_BROWSER_FORCE_SCREENSHOT=1
SFFC_REMOTE_BROWSER_ADMIN_ONLY=1
```

## Implementation Checklist

- [ ] Create `EmployerReviewSurfaceDecision`.
- [ ] Centralise employer URL resolution.
- [ ] Add provider embed policy.
- [ ] Add iframe probe state machine.
- [x] Add remote browser service abstraction.
- [x] Add WordPress AJAX endpoints for remote sessions.
- [x] Add `sffc-crm-apply-results__remote-browser` UI.
- [x] Add control handoff states.
- [x] Add provider-specific defaults.
- [ ] Add static screenshot last-resort card.
- [x] Add observability events.
- [x] Add unit tests.
- [ ] Add browser tests.
- [x] Add Railway service configuration.
- [ ] Add admin-only rollout flag.
- [ ] Add paying-member beta flag.

## Product Principle

The user should never feel like the application page has become a dead image.

If an employer can embed, show the iframe.

If it cannot embed, show a secure interactive browser.

If even that fails, show a static preview honestly labelled as preview-only, with a clear next action.
