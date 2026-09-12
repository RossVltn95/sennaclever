#!/usr/bin/env node

const fs = require("fs");
const path = require("path");

const repoRoot = path.join(__dirname, "..");
const jsPath = path.join(repoRoot, "assets/js/crm/crm-apply-chat-article.js");
const phpPath = path.join(repoRoot, "includes/crm/class-crm-shortcodes.php");
const cssPath = path.join(repoRoot, "assets/css/crm/crm-apply-chat-article.css");
const serviceDir = path.join(repoRoot, "services/senna-search-service");
const planPath = path.join(repoRoot, "docs/apply-chat-web-search-integration-plan.md");

const js = fs.readFileSync(jsPath, "utf8");
const php = fs.readFileSync(phpPath, "utf8");
const css = fs.readFileSync(cssPath, "utf8");
const plan = fs.readFileSync(planPath, "utf8");
const failures = [];

function fail(message) {
  failures.push(message);
}

function assertIncludes(label, source, needle) {
  if (!source.includes(needle)) {
    fail(`Missing ${label}: ${needle}`);
  }
}

function assertRegex(label, source, pattern) {
  if (!pattern.test(source)) {
    fail(`Missing ${label}: ${pattern}`);
  }
}

function getFunctionBody(source, name) {
  const marker = `function ${name}(`;
  const start = source.indexOf(marker);
  if (start === -1) return "";
  const bodyStart = source.indexOf("{", start);
  let depth = 0;
  for (let index = bodyStart; index < source.length; index += 1) {
    const char = source[index];
    if (char === "{") depth += 1;
    if (char === "}") {
      depth -= 1;
      if (depth === 0) return source.slice(bodyStart + 1, index);
    }
  }
  return "";
}

const webSearchRouteBody = getFunctionBody(
  js,
  "looksLikeHighConfidenceWebSearchRequest"
);
const webSearchRenderBody = getFunctionBody(
  js,
  "renderApplyChatWebSearchAnswerHtml"
);
const webSearchFetchBody = getFunctionBody(js, "fetchApplyChatWebSearch");
const webSearchActionBody = getFunctionBody(js, "searchWebInApplyChat");
const promptReplyBody = getFunctionBody(js, "maybeHandlePromptReply");
const profileReviewBody = getFunctionBody(
  js,
  "handleProfileReviewFollowUp"
);

if (!webSearchRouteBody) fail("Could not inspect web-search route function");
if (!webSearchRenderBody) fail("Could not inspect web-search renderer");
if (!webSearchFetchBody) fail("Could not inspect web-search fetcher");
if (!webSearchActionBody) fail("Could not inspect web-search action");
if (!promptReplyBody) fail("Could not inspect prompt reply handler");
if (!profileReviewBody) fail("Could not inspect profile review input handler");

[
  "best recruitment agencies in Dubai",
  "best recruiters in Dubai",
  "best finance recruitment agencies in Dubai",
  "top executive search firms in Riyadh",
  "latest hiring trends in Saudi finance",
  "average salary for HR manager Dubai",
].forEach((prompt) => {
  assertIncludes(`Phase 7 prompt in plan: ${prompt}`, plan, prompt);
});

[
  "recruitment agenc",
  "looksLikeExternalRecruiterDirectorySearch(clean)",
  "executive search",
  "salary benchmark",
  "average salary",
  "pay range",
  "salary guide",
  "hiring trend",
  "hiring market",
  "dubai",
  "riyadh",
  "saudi arabia",
  "uae",
].forEach((needle) => {
  assertIncludes(`web-search route signal ${needle}`, webSearchRouteBody, needle);
});

[
  "looksLikePastedCvText(clean)",
  "looksLikeConcreteApplyChatJobSearch(clean, detectedIntent)",
  "looksLikeActualJobPostSearch(clean, detectedIntent)",
  "looksLikeShortRoleDiscoverySearch(clean, detectedIntent)",
  "looksLikeSelectedRoleQuestion(clean)",
  "looksLikeRoleCvComparisonQuestion(clean)",
].forEach((needle) => {
  assertIncludes(`job/CV flow exclusion ${needle}`, webSearchRouteBody, needle);
});

assertRegex(
  "job-search prompts are excluded from web search",
  webSearchRouteBody,
  /show\|find\|search\|look for\|list\|recommend/
);
assertIncludes("web-search AJAX action", webSearchFetchBody, "sffc_crm_apply_chat_web_search");
assertIncludes("web-search nonce", webSearchFetchBody, "webSearchNonce");
assertIncludes("web-search fallback copy", webSearchActionBody, "I can’t reach web search right now.");
assertIncludes("web-search route debug hook", js, "classifyWebSearchRoute");
assertIncludes("web-search decision action", js, 'type: "web_search"');
assertIncludes("web-search action executor", js, "searchWebInApplyChat(");

if (
  promptReplyBody &&
  promptReplyBody.indexOf("looksLikeHighConfidenceWebSearchRequest(value, offScriptIntent)") >
    promptReplyBody.indexOf("looksLikeConcreteApplyChatJobSearch(value, offScriptIntent)")
) {
  fail("Prompt reply handler checks concrete job search before high-confidence web search");
}

if (
  profileReviewBody &&
  profileReviewBody.indexOf("looksLikeHighConfidenceWebSearchRequest(value, intent)") >
    profileReviewBody.indexOf("looksLikeConcreteApplyChatJobSearch(value, intent)")
) {
  fail("Profile review handler checks concrete job search before high-confidence web search");
}

[
  "sffc-crm-apply-chat__web-search-card",
  "sffc-crm-apply-chat__web-search-item",
  "sffc-crm-apply-chat__web-search-source",
  "sffc-crm-apply-chat__web-search-snippet",
  'target="_blank"',
  'rel="noopener noreferrer"',
  'dir="auto"',
  "Cached result",
  "Public sources",
  "is-empty",
].forEach((needle) => {
  assertIncludes(`web-search renderer ${needle}`, webSearchRenderBody, needle);
});

[
  ".sffc-crm-apply-chat__message.has-web-search-card",
  ".sffc-crm-apply-chat__web-search-card",
  ".sffc-crm-apply-chat__web-search-list",
  ".sffc-crm-apply-chat__web-search-title",
  ".sffc-crm-apply-chat__web-search-snippet",
  "@media (max-width: 640px)",
].forEach((needle) => {
  assertIncludes(`web-search CSS ${needle}`, css, needle);
});

[
  "wp_ajax_sffc_crm_apply_chat_web_search",
  "wp_ajax_nopriv_sffc_crm_apply_chat_web_search",
  "get_crm_apply_chat_web_search_endpoint",
  "SFFC_SEARCH_ENDPOINT",
  "SFFC_SEARCH_TOKEN",
  "normalize_crm_apply_chat_web_search_results",
  "get_crm_apply_chat_web_search_cache_ttl",
  "set_transient($cache_key",
  "30 * MINUTE_IN_SECONDS",
  "is_crm_apply_chat_web_search_sensitive_query",
  "is_crm_apply_chat_web_search_tracking_param",
  "log_crm_apply_chat_web_search_event",
  "query_hash",
  "http_build_query($query_args",
].forEach((needle) => {
  assertIncludes(`web-search PHP adapter ${needle}`, php, needle);
});

assertRegex(
  "URL cleanup must not call WordPress build_query with PHP arguments",
  php,
  /http_build_query\(\$query_args,\s*'',\s*'&',\s*PHP_QUERY_RFC3986\)/
);

[
  "private email",
  "phone number",
  "passport",
  "emirates id",
  "otp",
  "doxxing",
].forEach((needle) => {
  assertIncludes(`sensitive lookup guard ${needle}`, php, needle);
});

[
  "utm_",
  "fbclid",
  "gclid",
  "gbraid",
  "wbraid",
  "mc_cid",
].forEach((needle) => {
  assertIncludes(`tracking URL cleanup ${needle}`, php, needle);
});

[
  "Dockerfile",
  "README.md",
  "railway.json",
  "settings.yml",
].forEach((relativePath) => {
  const fullPath = path.join(serviceDir, relativePath);
  if (!fs.existsSync(fullPath)) {
    fail(`Missing search service file: ${relativePath}`);
  }
});

assertIncludes(
  "Phase 7 cached repeat query check",
  plan,
  "Cached repeat query works."
);
assertIncludes(
  "Phase 7 no-result check",
  plan,
  "Timeout and no-result states work."
);

if (failures.length) {
  console.error("Apply-chat web search Phase 7 checks failed:");
  failures.forEach((failure) => console.error(`- ${failure}`));
  process.exit(1);
}

console.log("Apply-chat web search Phase 7 checks passed.");
