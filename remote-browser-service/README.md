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
SFFC_REMOTE_BROWSER_NOVNC_WEB_ROOT=/usr/share/novnc
SFFC_REMOTE_BROWSER_DISPLAY_BASE=100
SFFC_REMOTE_BROWSER_RFB_PORT_BASE=5900
SFFC_REMOTE_BROWSER_NOVNC_PORT_BASE=7900
PORT=3000
```

WordPress should call this service server-to-server using the same token:

```php
define('SFFC_REMOTE_BROWSER_URL', 'https://replace-with-railway-domain');
define('SFFC_REMOTE_BROWSER_TOKEN', 'replace-with-shared-token');
```

The browser service should not be called directly by unauthenticated frontend code.

## API

All endpoints except `/health` require `Authorization: Bearer <token>` or `X-SFFC-Remote-Browser-Token: <token>`.

- `GET /health`
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

The first production transport uses noVNC over a per-session reverse proxy so the chat can show a real interactive browser window. The older Puppeteer screenshot/control protocol remains available as a development fallback by passing `transport: "puppeteer"`.

## Transport

The production default is `SFFC_REMOTE_BROWSER_TRANSPORT=novnc`.

For each session the service starts:

- isolated `Xvfb` display;
- Google Chrome on that display;
- `x11vnc` bound to localhost;
- `websockify` serving noVNC;
- a Node reverse proxy under `/sessions/:id/novnc/...`.

The returned `streamUrl` points to:

```text
/sessions/:id/novnc/vnc.html?autoconnect=true&resize=remote&path=sessions/:id/novnc/websockify&token=...
```

The token in the URL is a short-lived viewer token for that single browser session. API actions still require the server token.

For local development on machines without `Xvfb`, `x11vnc`, and `websockify`, create sessions with:

```json
{ "transport": "puppeteer" }
```

That uses the fallback screenshot/control protocol and is not the target production UX.
