#!/usr/bin/env node

const fs = require("fs");
const path = require("path");

const root = path.resolve(__dirname, "..");
const sourcePath = path.join(root, "assets/js/crm/crm-apply-chat-article.js");
const phpPath = path.join(root, "includes/crm/class-crm-shortcodes.php");
const adminPhpPath = path.join(root, "includes/crm/admin/class-crm-admin.php");
const feedAdminPhpPath = path.join(root, "includes/class-feed-manager-admin.php");
const source = fs.readFileSync(sourcePath, "utf8");
const php = fs.readFileSync(phpPath, "utf8");
const adminPhp = fs.readFileSync(adminPhpPath, "utf8");
const feedAdminPhp = fs.readFileSync(feedAdminPhpPath, "utf8");
const failures = [];

function getFunctionBody(name) {
  const marker = `function ${name}`;
  const start = source.indexOf(marker);
  if (start === -1) {
    failures.push(`Missing function ${name}`);
    return "";
  }
  const braceStart = source.indexOf("{", start);
  let depth = 0;
  for (let index = braceStart; index < source.length; index += 1) {
    const char = source[index];
    if (char === "{") depth += 1;
    if (char === "}") depth -= 1;
    if (depth === 0) {
      return source.slice(braceStart + 1, index);
    }
  }
  failures.push(`Could not parse function ${name}`);
  return "";
}

const policyBody = getFunctionBody("getApplyChatProviderReviewPolicy");
const embedPolicyBody = getFunctionBody("getApplicationEmbedPolicy");
const decisionBody = getFunctionBody("getEmployerReviewSurfaceDecision");
const normalizeActualJobPostSearchItemBody = getFunctionBody(
  "normalizeActualJobPostSearchItem"
);
const inlineReviewBody = getFunctionBody("renderInlineApplicationReview");
const fullReviewBody = getFunctionBody("renderResultApplicationReview");
const inlineReviewStart = source.indexOf("function renderInlineApplicationReview");
const inlineReviewRegion =
  inlineReviewStart >= 0
    ? source.slice(inlineReviewStart, source.indexOf("function renderLogo", inlineReviewStart))
    : "";
const phpDefaultHelperStart = php.indexOf("private function get_crm_apply_chat_provider_default_embed_mode");
const phpDefaultHelperRegion =
  phpDefaultHelperStart >= 0
    ? php.slice(
        phpDefaultHelperStart,
        php.indexOf("private function is_crm_apply_chat_remote_browser_enabled", phpDefaultHelperStart)
      )
    : "";
const reviewSurfaceEndpointStart = php.indexOf(
  "public function ajax_crm_apply_chat_review_surface_decision"
);
const reviewSurfaceEndpointRegion =
  reviewSurfaceEndpointStart >= 0
    ? php.slice(
        reviewSurfaceEndpointStart,
        php.indexOf("public function ajax_crm_apply_chat_remote_browser_create", reviewSurfaceEndpointStart)
      )
    : "";
const reviewSurfaceBuilderStart = php.indexOf(
  "private function build_crm_apply_chat_review_surface_decision"
);
const reviewSurfaceBuilderRegion =
  reviewSurfaceBuilderStart >= 0
    ? php.slice(
        reviewSurfaceBuilderStart,
        php.indexOf("private function is_crm_apply_chat_remote_browser_enabled", reviewSurfaceBuilderStart)
      )
    : "";

[
  {
    provider: "workable",
    label: "Workable",
    expectedMode: "remote_browser",
    reason: "remote_browser_default",
    route: "Workable secure browser route",
    allowedHost: "apply.workable.com",
  },
  {
    provider: "greenhouse",
    label: "Greenhouse",
    expectedMode: "remote_browser",
    reason: "remote_browser_default",
    route: "Greenhouse secure browser route",
    allowedHost: "job-boards.greenhouse.io",
  },
  {
    provider: "workday",
    label: "Workday",
    expectedMode: "remote_browser",
    reason: "workday_remote_browser_default",
    route: "Workday secure browser route",
    blockedHost: "myworkdayjobs.com",
  },
  {
    provider: "successfactors",
    label: "SAP SuccessFactors",
    expectedMode: "remote_browser",
    reason: "successfactors_remote_browser_default",
    route: "SAP SuccessFactors secure browser route",
    blockedHost: "successfactors.com",
  },
  {
    provider: "teamtailor",
    label: "Teamtailor",
    expectedMode: "remote_browser",
    reason: "teamtailor_application_remote_browser_default",
    route: "Teamtailor secure browser route",
    blockedHost: "teamtailor.com",
  },
  {
    provider: "simple_form",
    label: "Simple form",
    expectedMode: "remote_browser",
    reason: "remote_browser_default",
    route: "Simple form secure browser route",
  },
].forEach((check) => {
  if (!policyBody.includes(check.provider)) {
    failures.push(`Provider policy missing ${check.provider}`);
  }
  if (!policyBody.includes(check.label)) {
    failures.push(`Provider policy missing label ${check.label}`);
  }
  if (!policyBody.includes(check.reason)) {
    failures.push(`Provider policy missing reason ${check.reason}`);
  }
  if (
    check.route &&
    check.reason !== "remote_browser_default" &&
    !policyBody.includes(check.route)
  ) {
    failures.push(`Provider policy missing status label ${check.route}`);
  }
  if (check.allowedHost && !policyBody.includes(check.allowedHost)) {
    failures.push(`Provider policy missing allowed iframe host ${check.allowedHost}`);
  }
  if (check.blockedHost && !policyBody.includes(check.blockedHost)) {
    failures.push(`Provider policy missing blocked iframe host ${check.blockedHost}`);
  }
});

[
  "getApplyChatProviderReviewPolicy(cleanProvider, url)",
  "explicit_embed_mode",
  "explicit_static_preview_mode",
  "explicit_remote_browser_mode",
  "known_blocked_host_remote_browser_default",
  "remote_browser_default",
  "policy.defaultMode = \"remote_browser\"",
].forEach((needle) => {
  if (!embedPolicyBody.includes(needle)) {
    failures.push(`Embed policy missing ${needle}`);
  }
});

[
  "providerStatusLabel: policy.statusLabel || providerLabel",
  "shouldRenderIframe: surface === \"iframe_embed\"",
  "shouldRequestScreenshot:",
  "shouldStartRemoteBrowser: surface === \"remote_browser\"",
].forEach((needle) => {
  if (!decisionBody.includes(needle)) {
    failures.push(`Review surface decision missing ${needle}`);
  }
});

if (!inlineReviewRegion.includes("reviewDecision.providerStatusLabel")) {
  failures.push("Inline review card must render the provider status label.");
}
if (!inlineReviewRegion.includes("renderApplyResultsRemoteBrowser(")) {
  failures.push("Inline review card must render the remote browser window.");
}
if (!fullReviewBody.includes("providerStatusLabel")) {
  failures.push("Full review card must render the provider status label.");
}
if (!normalizeActualJobPostSearchItemBody.includes("getExternalEmployerApplicationUrlFromItem(source)")) {
  failures.push("Actual job search item normalizer must use the external employer URL guard.");
}
if (!normalizeActualJobPostSearchItemBody.includes("applyUrl: externalEmployerUrl")) {
  failures.push("Actual job search item normalizer must not expose raw/internal apply URLs.");
}

[
  "get_crm_apply_chat_provider_default_embed_mode",
  "workday",
  "successfactors",
  "teamtailor",
  "remote_browser",
].forEach((needle) => {
  if (!phpDefaultHelperRegion.includes(needle)) {
    failures.push(`PHP provider default embed-mode helper missing ${needle}`);
  }
});

[
  "iframe_embed",
  "iframe",
  "embed",
].forEach((needle) => {
  if (!php.includes(needle) || !source.includes(needle)) {
    failures.push(`Explicit iframe/embed override support missing ${needle}`);
  }
});

[
  "wp_ajax_sffc_crm_apply_chat_review_surface_decision",
  "wp_ajax_nopriv_sffc_crm_apply_chat_review_surface_decision",
  "wp_ajax_sffc_crm_apply_chat_review_surface_event",
  "wp_ajax_nopriv_sffc_crm_apply_chat_review_surface_event",
  "reviewSurfaceDecisionNonce",
  "sffc_crm_apply_chat_review_surface_decision",
  "sffc_crm_apply_chat_review_surface_event",
].forEach((needle) => {
  if (!php.includes(needle)) {
    failures.push(`PHP review-surface endpoint wiring missing ${needle}`);
  }
});

[
  "build_crm_apply_chat_review_surface_decision",
  "persist_crm_apply_chat_review_surface_metadata",
  "remote_browser_supported",
  "invalid_external_url",
  "remote_browser_unavailable",
  "remote_browser_default",
  "remote_browser_default_unavailable",
  "static_preview",
].forEach((needle) => {
  if (!reviewSurfaceBuilderRegion.includes(needle) && !reviewSurfaceEndpointRegion.includes(needle)) {
    failures.push(`PHP review-surface decision support missing ${needle}`);
  }
});

[
  "_sffc_application_embed_mode",
  "_sffc_application_embed_last_checked_at",
  "_sffc_application_embed_last_status",
  "_sffc_application_remote_browser_supported",
].forEach((needle) => {
  if (!php.includes(needle)) {
    failures.push(`PHP review-surface metadata persistence missing ${needle}`);
  }
});

[
  "logApplyResultsReviewSurfaceEvent",
  "iframe_probe_started",
  "iframe_load_event",
  "iframe_error_event",
  "iframe_probe_timeout",
  "iframe_fallback_shown",
  "remote_browser_requested",
  "remote_browser_ready",
  "remote_browser_error",
  "screenshot_preview_requested",
  "screenshot_preview_ready",
  "screenshot_preview_error",
].forEach((needle) => {
  if (!source.includes(needle) && !php.includes(needle)) {
    failures.push(`Review-surface telemetry missing ${needle}`);
  }
});

if (source.includes("This employer form blocks a standard embed. I’ll use the secure browser route when it is available")) {
  failures.push("Review-surface copy still describes secure browser as conditional iframe fallback.");
}
if (!source.includes("I’ll open this employer form in Senna’s secure browser")) {
  failures.push("Review-surface copy does not describe secure browser as the default route.");
}

[
  "remote_browser",
  "<option value=\"remote_browser\"",
  "Secure browser",
].forEach((needle) => {
  if (!adminPhp.includes(needle)) {
    failures.push(`Admin embed-mode enum/UI missing ${needle}`);
  }
});

if (adminPhp.includes("['auto', 'embed', 'screenshot']")) {
  failures.push("Admin embed-mode validation still rejects remote_browser.");
}

[
  "return 'remote_browser';",
  "workday",
  "successfactors",
  "teamtailor",
].forEach((needle) => {
  if (!feedAdminPhp.includes(needle)) {
    failures.push(`Feed/import embed-mode inference missing ${needle}`);
  }
});

[
  "$auto_submit_payload['provider']",
  "$apply_chat_auto_submit['provider']",
  "$launch_auto_submit['provider']",
].forEach((needle) => {
  if (!php.includes(needle)) {
    failures.push(`PHP apply-chat payloads must use provider defaults from ${needle}`);
  }
});

if (failures.length) {
  console.error(failures.map((failure) => `FAIL ${failure}`).join("\n"));
  process.exit(1);
}

console.log("PASS apply-chat provider review policy checks");
