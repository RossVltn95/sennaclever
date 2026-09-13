# SFFC Remote Browser Service

Interactive employer-page browser service for `sffc-crm-apply-chat`.

This is a separate Railway service from `application-worker`. It creates short-lived Chromium sessions for employer application pages that cannot be embedded directly in an iframe.

## Railway

Deploy this directory as its own Railway service inside the existing `RossVltn95/sennaclever` project.

```text
Railway project
  ├─ senna-application-worker  -> application-worker/
  └─ senna-remote-browser      -> remote-browser-service/
```

Use `remote-browser-service/Dockerfile`.

Railway service settings:

```text
Root Directory: remote-browser-service
Builder: Dockerfile
Dockerfile Path: Dockerfile
Start Command: npm start
Healthcheck Path: /health
Healthcheck Timeout: 60s
```

If the service is created from the repository root instead of setting the Railway root directory to `remote-browser-service`, set the Dockerfile path to `remote-browser-service/Dockerfile` and make sure the build context includes this directory.

## Environment

```bash
SFFC_REMOTE_BROWSER_TOKEN=replace-with-shared-token
SFFC_REMOTE_BROWSER_PUBLIC_URL=https://replace-with-railway-domain
SFFC_REMOTE_BROWSER_MAX_SESSIONS=5
SFFC_REMOTE_BROWSER_SESSION_TTL_SECONDS=900
SFFC_REMOTE_BROWSER_IDLE_TTL_SECONDS=180
SFFC_REMOTE_BROWSER_ALLOWED_ORIGIN=https://joinsenna.com
SFFC_REMOTE_BROWSER_CHROME_EXECUTABLE=/usr/bin/google-chrome-stable
SFFC_REMOTE_BROWSER_PROFILE_ROOT=/tmp/sffc-remote-browser
SFFC_REMOTE_BROWSER_TRANSPORT=novnc
SFFC_REMOTE_BROWSER_ALLOW_NOVNC_PUBLIC=0
SFFC_REMOTE_BROWSER_NOVNC_WEB_ROOT=/usr/share/novnc
SFFC_REMOTE_BROWSER_DISPLAY_BASE=100
SFFC_REMOTE_BROWSER_RFB_PORT_BASE=5900
SFFC_REMOTE_BROWSER_NOVNC_PORT_BASE=7900
SFFC_REMOTE_BROWSER_CREATE_RATE_LIMIT=10
SFFC_REMOTE_BROWSER_ACTION_RATE_LIMIT=120
SFFC_REMOTE_BROWSER_RATE_LIMIT_WINDOW_MS=60000
PORT=3000
```

WordPress should call this service server-to-server using the same token:

```php
define('SFFC_REMOTE_BROWSER_URL', 'https://replace-with-railway-domain');
define('SFFC_REMOTE_BROWSER_TOKEN', 'replace-with-shared-token');
```

The browser service should not be called directly by unauthenticated frontend code.

## Security Controls

The service is intentionally not a general-purpose browser proxy.

- Only server-to-server API calls with `SFFC_REMOTE_BROWSER_TOKEN` can create or control sessions.
- Public noVNC access requires a short-lived per-session viewer token.
- Employer URLs must be external `http` or `https` URLs.
- `joinsenna.com`, localhost, private IP ranges, link-local/metadata addresses, and DNS records resolving to private/reserved addresses are rejected.
- Sessions use isolated Chrome profiles and are automatically closed by TTL/idle cleanup.
- Create and action requests are rate-limited per client.
- Sensitive lifecycle events are logged as JSON lines in Railway logs.
- File upload and final submit are not exposed through the remote browser API until explicit consent and worker-side safeguards are implemented.

## Observability

The service writes structured JSON lines to stdout for Railway log drains.

Important events:

- `remote_browser_create_requested`
- `remote_browser_created`
- `remote_browser_ready`
- `remote_browser_control_changed`
- `remote_browser_navigation_failed`
- `remote_browser_capacity_exhausted`
- `remote_browser_closed`
- `remote_browser_expired_cleanup`
- `remote_browser_rate_limited`

`remote_browser_ready` includes `startupMs`; `remote_browser_closed` includes `durationMs`. WordPress logs matching broker-side and review-surface events with the `[sffc-apply-chat-remote-browser]` prefix.

## API

All endpoints except `/health` require `Authorization: Bearer <token>` or `X-SFFC-Remote-Browser-Token: <token>`.

- `GET /health`
- `GET /capacity`
- `POST /sessions`
- `GET /sessions/:id`
- `GET /sessions/:id/screenshot`
- `POST /sessions/:id/navigate`
- `POST /sessions/:id/control`
- `POST /sessions/:id/click`
- `POST /sessions/:id/type`
- `POST /sessions/:id/key`
- `POST /sessions/:id/scroll`
- `POST /sessions/:id/upload`
- `POST /sessions/:id/close`

`POST /sessions` body:

```json
{
  "roleId": "123",
  "taskUuid": "optional",
  "userId": 456,
  "conversationId": 789,
  "provider": "workable",
  "employerUrl": "https://apply.workable.com/example/j/ABC123/"
}
```

The original transport uses noVNC over a per-session reverse proxy. noVNC is now intended for internal/debug fallback only because it exposes a remote-desktop style UI. The preferred user-facing fallback is a managed live browser provider, configured with `SFFC_REMOTE_BROWSER_TRANSPORT=cloudflare_live_view` or `SFFC_REMOTE_BROWSER_TRANSPORT=browserless_live_url`.

The older Puppeteer screenshot/control protocol remains available as a development fallback by passing `transport: "puppeteer"`.

`GET /health` is public and returns capacity plus runtime availability for Railway health checks. `GET /capacity` requires the service token and returns active session summaries for WordPress/admin diagnostics.

## Transport

The safe default is `SFFC_REMOTE_BROWSER_TRANSPORT=novnc` with `SFFC_REMOTE_BROWSER_ALLOW_NOVNC_PUBLIC=0`, which prevents the WordPress apply-chat UI from exposing noVNC to candidates. Set a managed transport before enabling the public assisted browser path.

Managed live browser transports:

```bash
# Browserless
SFFC_REMOTE_BROWSER_TRANSPORT=browserless_live_url
SFFC_BROWSERLESS_WS_ENDPOINT=wss://production-sfo.browserless.io?token=...

# Cloudflare Browser Run
SFFC_REMOTE_BROWSER_TRANSPORT=cloudflare_live_view
SFFC_CLOUDFLARE_ACCOUNT_ID=...
SFFC_CLOUDFLARE_API_TOKEN=...
# Optional override:
SFFC_CLOUDFLARE_BROWSER_WS_ENDPOINT=wss://api.cloudflare.com/client/v4/accounts/.../browser-run/devtools/browser?keep_alive=600000
```

noVNC public exposure should only be enabled deliberately:

```bash
SFFC_REMOTE_BROWSER_TRANSPORT=novnc
SFFC_REMOTE_BROWSER_ALLOW_NOVNC_PUBLIC=1
```

For noVNC sessions the service starts:

- isolated `Xvfb` display;
- Google Chrome on that display;
- `x11vnc` bound to localhost;
- `websockify` serving noVNC;
- a Node reverse proxy under `/sessions/:id/novnc/...`.

The returned `streamUrl` points to:

```text
/sessions/:id/novnc/vnc.html?autoconnect=1&resize=remote&path=sessions/:id/novnc/websockify%3Ftoken%3D...&token=...
```

The token in the URL is a short-lived viewer token for that single browser session. It is included both on the noVNC page request and inside the proxied WebSocket path so iframe cookie restrictions do not leave the user on the raw noVNC connection screen. API actions still require the server token.

For local development on machines without `Xvfb`, `x11vnc`, and `websockify`, create sessions with:

```json
{ "transport": "puppeteer" }
```

That uses the fallback screenshot/control protocol and is not the target production UX.
