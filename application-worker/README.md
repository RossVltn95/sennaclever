# SFFC Application Worker

Puppeteer worker for candidate-authorised application tasks created from the `sffc-crm-apply-chat` flow.

## Environment

```bash
SFFC_WP_AJAX_URL=https://joinsenna.com/wp-admin/admin-ajax.php
SFFC_APPLICATION_WORKER_TOKEN=replace-with-wordpress-token
SFFC_WORKER_ID=railway-worker-1
SFFC_WORKER_POLL_INTERVAL_MS=15000
SFFC_WORKER_ALLOW_WORKDAY_ACCOUNT_CREATION=0
SFFC_WORKER_ALLOW_SUCCESSFACTORS_ACCOUNT_CREATION=0
SFFC_WORKER_ALLOW_FINAL_SUBMIT=0
PUPPETEER_EXECUTABLE_PATH=/usr/bin/google-chrome-stable
```

Use the same token in WordPress, preferably in `wp-config.php`:

```php
define('SFFC_APPLICATION_WORKER_TOKEN', 'replace-with-wordpress-token');
```

Set `SFFC_WORKER_ALLOW_FINAL_SUBMIT=1` only when the service is ready to make real submissions. With the default `0`, the worker fills the form, uploads the CV where possible, captures evidence, and reports `dry_run_ready`.

Workday has a separate safety switch because many tenants require a candidate account before the application form is available. Keep `SFFC_WORKER_ALLOW_WORKDAY_ACCOUNT_CREATION=0` for dry runs. Set it to `1` only after the candidate has explicitly consented to creating or using a tenant-specific Workday account. Final application submission is still controlled separately by `SFFC_WORKER_ALLOW_FINAL_SUBMIT`.

SuccessFactors has the same account-creation constraint on many tenants. Keep `SFFC_WORKER_ALLOW_SUCCESSFACTORS_ACCOUNT_CREATION=0` unless the candidate has explicitly consented to creating or using a tenant-specific SAP SuccessFactors account.

## Run

```bash
npm install
npm start
```

Dry-inspect a Workday role without account creation or final submission:

```bash
SFFC_WORKDAY_TEST_URL=https://blackstone.wd1.myworkdayjobs.com/en-US/Blackstone_Careers/job/Luxembourg/Assistant-Vice-President---Valuation_41559 npm run inspect:workday
```

Dry-inspect a SuccessFactors role without account creation or final submission:

```bash
SFFC_SUCCESSFACTORS_TEST_URL=https://career2.successfactors.eu/career?career_ns=job_listing\&company=thecommerc\&career_job_req_id=7725 npm run inspect:successfactors
```

On Railway, deploy this folder with the included `Dockerfile`, not a Nixpacks-generated image. The Docker image installs Google Chrome Stable at `/usr/bin/google-chrome-stable`, sets `PUPPETEER_EXECUTABLE_PATH`, and verifies the browser during build with `google-chrome-stable --version`.

The worker claims one queued task at a time from WordPress, processes it in Chromium, and posts the result back to WordPress.
