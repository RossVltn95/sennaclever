#!/usr/bin/env node

const fs = require("fs");
const path = require("path");

const root = path.resolve(__dirname, "..");
const phpPath = path.join(root, "includes/crm/class-crm-shortcodes.php");
const jsPath = path.join(root, "assets/js/crm/crm-apply-chat-article.js");
const planPath = path.join(root, "docs/emily-mathematical-meaning-engine-plan.md");
const php = fs.readFileSync(phpPath, "utf8");
const js = fs.readFileSync(jsPath, "utf8");
const plan = fs.readFileSync(planPath, "utf8");
const failures = [];

function requireMarker(label, source, marker) {
  if (!source.includes(marker)) {
    failures.push(`missing_${label}:${marker}`);
  }
}

function requireFile(label, relativePath) {
  if (!fs.existsSync(path.join(root, relativePath))) {
    failures.push(`missing_${label}:${relativePath}`);
  }
}

[
  ["php_emily_endpoint_constant", php, "SFFC_EMILY_NLP_ENDPOINT"],
  ["php_emily_token_constant", php, "SFFC_EMILY_NLP_TOKEN"],
  ["php_emily_endpoint_getter", php, "get_crm_apply_chat_emily_nlp_endpoint"],
  ["php_emily_token_getter", php, "get_crm_apply_chat_emily_nlp_token"],
  ["php_emily_localized_endpoint", php, "'emilyNlpEndpoint' => $this->get_crm_apply_chat_emily_nlp_endpoint()"],
  ["php_emily_localized_token", php, "'emilyNlpToken' => $this->get_crm_apply_chat_emily_nlp_token()"],
  ["php_search_endpoint_constant", php, "SFFC_SEARCH_ENDPOINT"],
  ["php_search_token_constant", php, "SFFC_SEARCH_TOKEN"],
  ["php_answer_endpoint_constant", php, "SFFC_SEARCH_ANSWER_ENDPOINT"],
  ["php_answer_token_constant", php, "SFFC_SEARCH_ANSWER_TOKEN"],
  ["php_answer_endpoint_getter", php, "get_crm_apply_chat_web_answer_endpoint"],
  ["php_answer_token_getter", php, "get_crm_apply_chat_web_answer_token"],
  ["php_web_search_ajax_action", php, "wp_ajax_sffc_crm_apply_chat_web_search"],
  ["php_web_search_nonce", php, "wp_create_nonce('sffc_crm_apply_chat_web_search')"],
  ["php_liteparse_endpoint_constant", php, "SFFC_LITEPARSE_ENDPOINT"],
  ["php_liteparse_token_constant", php, "SFFC_LITEPARSE_PUBLIC_TOKEN"],
  ["php_liteparse_localized_endpoint", php, "'liteParseEndpoint' => $this->get_liteparse_endpoint()"],
  ["php_liteparse_review_endpoint", php, "'liteParseReviewEndpoint' => $this->get_liteparse_review_endpoint()"],
  ["php_liteparse_job_match_endpoint", php, "'liteParseJobMatchEndpoint' => $this->get_liteparse_job_match_endpoint()"],
  ["js_emily_endpoint_consumer", js, "getEmilyNlpServiceEndpoint"],
  ["js_emily_endpoint_config", js, "getConfig().emilyNlpEndpoint"],
  ["js_emily_service_fetch", js, "fetchEmilyNlpServiceMeaning"],
  ["js_web_search_ajax_consumer", js, "sffc_crm_apply_chat_web_search"],
  ["js_web_search_nonce_consumer", js, "config.webSearchNonce"],
  ["js_web_answer_renderer", js, "sffc-crm-apply-chat__web-answer"],
  ["plan_phase_14", plan, "### Phase 14: Production Service Readiness"],
  ["plan_emily_constant", plan, "SFFC_EMILY_NLP_ENDPOINT"],
  ["plan_web_answer_constant", plan, "SFFC_SEARCH_ANSWER_ENDPOINT"],
  ["plan_search_constant", plan, "SFFC_SEARCH_ENDPOINT"],
  ["plan_liteparse_constant", plan, "SFFC_LITEPARSE_ENDPOINT"],
  ["plan_phase_15", plan, "### Phase 15: Gap Closure - Constraints and Live Smoke Hooks"],
  ["plan_smoke_endpoint", plan, "SFFC_SMOKE_EMILY_NLP_ENDPOINT"],
].forEach(([label, source, marker]) => requireMarker(label, source, marker));

[
  ["emily_service_package", "services/senna-emily-nlp-service/package.json"],
  ["emily_service_server", "services/senna-emily-nlp-service/server.js"],
  ["web_answer_service_app", "services/senna-web-answer-service/app.py"],
  ["web_answer_service_dockerfile", "services/senna-web-answer-service/Dockerfile"],
  ["search_service_dockerfile", "services/senna-search-service/Dockerfile"],
  ["liteparse_package", "services/senna-liteparse-service/package.json"],
  ["liteparse_server", "services/senna-liteparse-service/server.js"],
  ["service_smoke_script", "scripts/smoke-emily-services.js"],
].forEach(([label, relativePath]) => requireFile(label, relativePath));

if (failures.length) {
  console.error("FAIL Emily production readiness wiring", failures);
  process.exit(1);
}

console.log("PASS Emily production service wiring is documented and guarded");
