#!/usr/bin/env node

const fs = require("fs");
const path = require("path");

const root = path.resolve(__dirname, "..");
const sourcePath = path.join(root, "assets/js/crm/crm-apply-chat-article.js");
const phpPath = path.join(root, "includes/crm/class-crm-shortcodes.php");
const source = fs.readFileSync(sourcePath, "utf8");
const php = fs.readFileSync(phpPath, "utf8");
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

[
  {
    provider: "workable",
    label: "Workable",
    expectedMode: "iframe_embed",
    reason: "workable_iframe_probe",
    route: "Workable route",
    allowedHost: "apply.workable.com",
  },
  {
    provider: "greenhouse",
    label: "Greenhouse",
    expectedMode: "iframe_embed",
    reason: "greenhouse_iframe_probe",
    route: "Greenhouse route",
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
    expectedMode: "iframe_embed",
    reason: "simple_form_iframe_probe",
    route: "Simple form route",
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
  if (!policyBody.includes(check.route)) {
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
if (!fullReviewBody.includes("providerStatusLabel")) {
  failures.push("Full review card must render the provider status label.");
}

[
  "get_crm_apply_chat_provider_default_embed_mode",
  "workable",
  "greenhouse",
  "workday",
  "successfactors",
  "teamtailor",
  "simple_form",
  "remote_browser",
  "embed",
].forEach((needle) => {
  if (!phpDefaultHelperRegion.includes(needle)) {
    failures.push(`PHP provider default embed-mode helper missing ${needle}`);
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
