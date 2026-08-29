# SFFC Application Worker

Puppeteer worker for candidate-authorised application tasks created from the `sffc-crm-apply-chat` flow.

## Environment

```bash
SFFC_WP_AJAX_URL=https://joinsenna.com/wp-admin/admin-ajax.php
SFFC_APPLICATION_WORKER_TOKEN=replace-with-wordpress-token
SFFC_WORKER_ID=railway-worker-1
SFFC_WORKER_POLL_INTERVAL_MS=15000
SFFC_WORKER_ALLOW_FINAL_SUBMIT=0
PUPPETEER_EXECUTABLE_PATH=
```

Use the same token in WordPress, preferably in `wp-config.php`:

```php
define('SFFC_APPLICATION_WORKER_TOKEN', 'replace-with-wordpress-token');
```

Set `SFFC_WORKER_ALLOW_FINAL_SUBMIT=1` only when the service is ready to make real submissions. With the default `0`, the worker fills the form, uploads the CV where possible, captures evidence, and reports `dry_run_ready`.

## Run

```bash
npm install
npm start
```

On Railway, Nixpacks installs system Chromium for this service. The worker prefers `PUPPETEER_EXECUTABLE_PATH` when set, then common Linux Chromium paths such as `/usr/bin/chromium`, and only falls back to Puppeteer's bundled browser lookup if no system browser is found.

The worker claims one queued task at a time from WordPress, processes it in Chromium, and posts the result back to WordPress.
