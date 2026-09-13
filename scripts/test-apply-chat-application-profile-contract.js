#!/usr/bin/env node

const fs = require("fs");
const path = require("path");

const root = path.resolve(__dirname, "..");
const chatSource = fs.readFileSync(
  path.join(root, "assets/js/crm/crm-apply-chat-article.js"),
  "utf8"
);
const phpSource = fs.readFileSync(
  path.join(root, "includes/crm/class-crm-shortcodes.php"),
  "utf8"
);
const workerSource = fs.readFileSync(
  path.join(root, "application-worker/src/worker.js"),
  "utf8"
);

function assertIncludes(source, label, needle) {
  if (!source.includes(needle)) {
    throw new Error(`Missing ${label}: ${needle}`);
  }
}

function assertNotIncludes(source, label, needle) {
  if (source.includes(needle)) {
    throw new Error(`Unexpected ${label}: ${needle}`);
  }
}

[
  ["profile field helper", "function buildApplicationProfileField("],
  ["profile object helper", "function getApplicationProfileField("],
  ["profile value helper", "function getApplicationProfileFieldValue("],
  ["profile extractor", "function extractApplicationProfileFromCurrentCv("],
  ["profile section extraction helper", "function getApplicationProfileSectionItems("],
  ["profile experience entries helper", "function getApplicationProfileExperienceEntries("],
  ["profile role extraction helper", "function extractApplicationProfileRoleFromEntry("],
  ["profile employer extraction helper", "function extractApplicationProfileEmployerFromEntry("],
  ["profile years extraction helper", "function extractApplicationProfileYearsExperience("],
  ["profile seniority inference helper", "function inferApplicationProfileSeniority("],
  ["profile language extraction helper", "function extractApplicationProfileLanguages("],
  ["profile skills extraction helper", "function extractApplicationProfileSkills("],
  ["profile education extraction helper", "function extractApplicationProfileEducation("],
  ["profile certifications extraction helper", "function extractApplicationProfileCertifications("],
  ["profile professional signals helper", "function extractApplicationProfileProfessionalSignals("],
  ["profile current resolver", "function getCurrentApplicationProfile("],
  ["apply-chat access is open", "var hasPremiumAccess = true;"],
  ["premium chat access is open", "function hasPremiumMemberChatAccess() {\n      return true;\n    }"],
  ["application worker access is open", "function hasApplicationWorkerProAccess() {\n      return true;\n    }"],
  ["profile storage key", "function getApplicationProfileStorageKey("],
  ["profile storage reader", "function readStoredApplicationProfile("],
  ["profile storage writer", "function storeApplicationProfile("],
  ["profile server save", "function saveApplicationProfileToServer("],
  ["profile server load", "function loadApplicationProfileFromServer("],
  ["profile server ajax action", 'formData.append("action", "sffc_crm_apply_chat_application_profile");'],
  ["profile guarded merge", "function shouldMergeSavedApplicationProfileField("],
  ["profile merge helper", "function mergeApplicationProfiles("],
  ["profile merge preserves array values", "savedValue,"],
  ["probabilistic classifier scoped web-search flag", "var highConfidenceWebSearch = looksLikeHighConfidenceWebSearchRequest(\n        clean,"],
  ["remote browser transport config", "remoteBrowserTransport"],
  ["remote browser public novnc guard", "allowPublicNoVnc"],
  ["remote browser default is iframe first", 'policy.defaultMode = "iframe_embed";\n      policy.requiresRemoteBrowserForApply = false;'],
  ["remote browser no public novnc unless allowed", "(!isNoVncTransport || allowPublicNoVnc)"],
  ["profile panel renderer", "function renderApplicationProfilePanel("],
  ["job result card render isolation", "[sffc-apply-chat] job result card render failed"],
  ["job result card fallback does not collapse all results", "if (!renderedResultCards) {\n        return emptyResultsHtml;"],
  ["profile panel opener", "function openApplicationProfilePanel("],
  ["profile panel persister", "function persistApplicationProfileFromPanel("],
  ["profile structured detector", "function containsApplicationProfileHtml("],
  ["profile identity group", "identity: {"],
  ["profile location group", "location: {"],
  ["profile work auth group", "workAuthorization: {"],
  ["profile professional group", "professional: {"],
  ["profile current title field", "currentTitle: trackField("],
  ["profile current employer field", "currentEmployer: trackField("],
  ["profile years experience field", "yearsExperience: trackField("],
  ["profile seniority field", "seniority: trackField("],
  ["profile sectors field", "sectors: trackField("],
  ["profile functions field", "functions: trackField("],
  ["profile skills field", "skills: trackField("],
  ["profile languages field", "languages: trackField("],
  ["profile education field", "education: trackField("],
  ["profile certifications field", "certifications: trackField("],
  ["profile defaults group", "applicationDefaults: {"],
  ["profile sponsorship default", 'requiresSponsorship: buildApplicationProfileField("No", "default"'],
  ["profile emirati default", 'emiratiNational: buildApplicationProfileField("No", "default"'],
  ["profile adjustments default", 'reasonableAdjustments: buildApplicationProfileField("No", "default"'],
  ["profile other roles default", 'considerOtherRoles: buildApplicationProfileField("Yes", "default"'],
  ["profile readiness confirmation field", 'readinessConfirmedAt: buildApplicationProfileField("", "unknown"'],
  ["profile unsafe field list", "unsafeFields: ["],
  ["profile answer memory group", "answerMemory: {"],
  ["profile evidence group", "evidence: {"],
  ["profile readiness helper", "function buildApplicationProfileReadinessReview("],
  ["profile readiness gate", "function ensureApplicationProfileReadinessThenQueue("],
  ["profile readiness field prompt", "function askApplicationProfileReadinessField("],
  ["profile readiness card", "function renderApplicationProfileReadinessCard("],
  ["profile readiness confirmation marker", "function markApplicationProfileReadinessConfirmed("],
  ["profile readiness prompt state", '"application_profile_readiness_confirm"'],
  ["profile readiness field prompt state", '"application_profile_readiness_field"'],
  ["profile readiness queue integration", "ensureApplicationProfileReadinessThenQueue(\n            item,"],
  ["profile version helper", "function getApplicationProfileVersionId("],
  ["submit preference helper", "function getApplicationProfileSubmitPreference("],
  ["answer memory snapshot helper", "function buildApplicationAnswerMemorySnapshot("],
  ["queue profile variable", "var applicationProfile = getCurrentApplicationProfile();"],
  ["queue profile payload", 'formData.append(\n          "application_profile",'],
  ["queue profile version payload", 'formData.append("profile_version_id", applicationProfileVersionId);'],
  ["queue answer memory payload", '"answer_memory_snapshot",'],
  ["queue submit preference payload", 'formData.append("submit_preference", applicationSubmitPreference);'],
  ["workday final submit follows submit preference", "final_submit: workdayFinalSubmitAllowed,"],
  ["successfactors final submit follows submit preference", "final_submit: successFactorsFinalSubmitAllowed,"],
  ["canonical full name queue lookup", '"identity.fullName"'],
  ["canonical email queue lookup", '"identity.email"'],
  ["canonical phone queue lookup", '"identity.phone"'],
  ["profile rail click binding", 'button.hasAttribute("data-sffc-apply-chat-open-profile")'],
  ["profile panel save binding", "[data-sffc-apply-profile-save]"],
  ["profile panel use binding", "[data-sffc-apply-profile-use]"],
  ["profile readiness confirm binding", "[data-sffc-application-readiness-confirm]"],
  ["profile readiness edit binding", "[data-sffc-application-readiness-edit]"],
  ["profile readiness debug hook", "getApplicationProfileReadinessReview: function (queueItem)"],
  ["logged-in persistent profile key", '"sffcApplyChatApplicationProfile:v1:user:"'],
  ["guest session profile key", '"sffcApplyChatApplicationProfile:v1:guest:"'],
].forEach(([label, needle]) => assertIncludes(chatSource, label, needle));

[
  ["php first rail profile button", 'data-sffc-apply-chat-open-profile aria-label="<?php esc_attr_e(\'My profile\', \'senna-finance\'); ?>"'],
  ["php disabled apply-chat membership workspace", "private function render_apply_chat_membership_workspace()\n        {\n            return ['html' => '', 'init_shortcode' => ''];"],
  ["php profile ajax hook", "add_action('wp_ajax_sffc_crm_apply_chat_application_profile'"],
  ["php profile ajax handler", "public function ajax_crm_apply_chat_application_profile()"],
  ["php profile meta key", "'sffc_crm_apply_chat_application_profile'"],
  ["php profile user guard", "if (!is_user_logged_in()) {"],
  ["php profile user meta save", "update_user_meta($user_id, $meta_key, $profile);"],
  ["php profile user meta load", "get_user_meta($user_id, $meta_key, true);"],
  ["php localizes user id", "'currentUserId' => get_current_user_id(),"],
  ["php localizes user email", "'currentUserEmail' => is_user_logged_in() ? (string) wp_get_current_user()->user_email : '',"],
  ["php localizes remote browser transport", "'remoteBrowserTransport' => $this->get_crm_apply_chat_remote_browser_transport(),"],
  ["php localizes public novnc guard", "'remoteBrowserAllowNoVncPublic' => $this->can_crm_apply_chat_expose_public_novnc(),"],
  ["php remote browser transport helper", "private function get_crm_apply_chat_remote_browser_transport()"],
  ["php no public novnc helper", "private function can_crm_apply_chat_expose_public_novnc()"],
  ["php no public novnc blocks frontend", "$transport === 'novnc' && !$this->can_crm_apply_chat_expose_public_novnc()"],
  ["php remote browser broker configured transport", "'transport' => $this->get_crm_apply_chat_remote_browser_transport(),"],
  ["php workable iframe-first default", "if ($provider === 'workable' || $provider === 'workable_board'"],
  ["php unknown iframe-first default", "return 'embed';\n        }\n\n        private function build_crm_apply_chat_review_surface_decision"],
  ["php reads application_profile", "$application_profile_raw = wp_unslash((string) ($_POST['application_profile'] ?? '{}'));"],
  ["php sanitizes application_profile", "$application_profile = $this->sanitize_crm_application_task_diagnostic_value($application_profile_decoded);"],
  ["php reads profile_version_id", "$profile_version_id = sanitize_text_field(wp_unslash((string) ($_POST['profile_version_id'] ?? '')));"],
  ["php reads answer_memory_snapshot", "$answer_memory_snapshot_raw = wp_unslash((string) ($_POST['answer_memory_snapshot'] ?? '{}'));"],
  ["php reads submit_preference", "$submit_preference = sanitize_key((string) wp_unslash($_POST['submit_preference'] ?? 'ask_before_submit'));"],
  ["php fullName fallback", "$profile_name_field = is_array($profile_identity['fullName'] ?? null) ? $profile_identity['fullName'] : [];"],
  ["php email fallback", "$profile_email_field = is_array($profile_identity['email'] ?? null) ? $profile_identity['email'] : [];"],
  ["php phone fallback", "$profile_phone_field = is_array($profile_identity['phone'] ?? null) ? $profile_identity['phone'] : [];"],
  ["php stores profile in payload", "'application_profile' => $application_profile,"],
  ["php stores profile version in payload", "'profile_version_id' => $profile_version_id,"],
  ["php stores answer memory in payload", "'answer_memory_snapshot' => $answer_memory_snapshot,"],
  ["php stores submit preference in payload", "'submit_preference' => $submit_preference,"],
].forEach(([label, needle]) => assertIncludes(phpSource, label, needle));

[
  ["apply-chat membership rail gate", "data-sffc-apply-chat-membership-gated"],
  ["apply-chat membership upgrade copy", "Upgrade to Senna Pro"],
  ["apply-chat membership choice copy", "membership choice"],
  ["apply-chat completed signup CTA", "Complete sign up"],
  ["apply-chat recruiter pro unlock copy", "Unlock MENA Careers Pro"],
  ["apply-chat recruiter pro access copy", "Unlock recruiter access with MENA Careers Pro"],
  ["apply-chat recruiter unlock hook", "data-sffc-apply-chat-unlock-pro"],
].forEach(([label, needle]) =>
  assertNotIncludes(chatSource + "\n" + phpSource, label, needle)
);

[
  ["locked results upgrade label", "Upgrade for Details"],
  ["locked results login label", "Login for Details"],
  ["worker locked action", "data-sffc-application-worker-locked"],
  ["pro subscription worker copy", "Apply for me needs a Pro+ subscription"],
].forEach(([label, needle]) => assertNotIncludes(chatSource, label, needle));

[
  ["worker flattener", "function flattenApplicationProfileAnswers(profile)"],
  ["worker profile contract accessor", "function getApplicationProfileContract(task)"],
  ["worker profile version accessor", "function getApplicationProfileVersionId(task)"],
  ["worker answer memory accessor", "function getApplicationAnswerMemorySnapshot(task)"],
  ["worker submit preference accessor", "function getApplicationSubmitPreference(task)"],
  ["worker adapter provider normalizer", "function normalizeApplicationAdapterProvider(task = {}, url = \"\")"],
  ["worker adapter candidate builder", "function buildApplicationAdapterCandidate(task = {})"],
  ["worker adapter input builder", "function buildApplicationAdapterInput(task = {}, overrides = {})"],
  ["worker adapter input summary", "function summarizeApplicationAdapterInput(adapterInput = {})"],
  ["worker adapter result wrapper", "function withApplicationAdapterResultContract(task = {}, result = {}, adapterInput = null)"],
  ["worker answer memory flattener", "function flattenApplicationAnswerMemorySnapshot(snapshot)"],
  ["worker field accessor", "function getApplicationProfileFieldValue(profile, group, key)"],
  ["worker universal default policy", "function getUniversalApplicationQuestionDefault(question, context = {})"],
  ["worker universal default answer helper", "function getUniversalApplicationQuestionDefaultAnswer(question, context = {})"],
  ["worker application choice matcher", "function matchApplicationChoice(value, choices = [], patterns = [])"],
  ["worker emirati default policy", "eligibility_default_confirm"],
  ["worker sponsorship default policy", "sponsorship_default_confirm"],
  ["worker work auth default policy", "work_authorization_default_confirm"],
  ["worker declaration default policy", "declaration_default_confirm"],
  ["worker unsafe sensitive fact policy", "unsupported_sensitive_fact"],
  ["worker no broad Workday default", "return [];"],
  ["worker profile precedence", "...applicationProfileAnswers,"],
  ["worker answer memory precedence", "...answerMemoryAnswers,"],
  ["worker candidate profile answers before task columns", "const profileAnswers = getCandidateProfileAnswers(task);"],
  ["worker adapter context in process task", "const adapterInput = buildApplicationAdapterInput(task, { url });"],
  ["worker stores adapter context on task", "task.__sffc_adapter_input = adapterInput;"],
  ["worker adapter contract debug log", "\"adapter_contract\""],
  ["worker adapter context passed to Workday", "processWorkdayTask(page, task, candidate, cvPath, url, adapterInput)"],
  ["worker adapter context passed to SuccessFactors", "processSuccessFactorsTask(page, task, candidate, cvPath, url, adapterInput)"],
  ["worker adapter context passed to Teamtailor", "processTeamtailorTask(page, task, candidate, cvPath, url, adapterInput)"],
  ["worker adapter context passed to simple form", "processSimpleFormTask(page, task, candidate, cvPath, url, adapterInput)"],
  ["worker final result adapter wrapper", "const result = withApplicationAdapterResultContract(task, await processTask(task));"],
  ["worker verification result adapter wrapper", "withApplicationAdapterResultContract(task, await buildVerificationRequiredResult(message, submitResult))"],
  ["worker adapter contract version result", "application_adapter_contract_version"],
  ["worker adapter input result metadata", "application_adapter_input"],
  ["worker custom question detector", "function isCustomNarrativeApplicationQuestion(question = {})"],
  ["worker custom question classifier", "function classifyCustomNarrativeQuestion(question = {})"],
  ["worker custom question draft builder", "function buildCustomQuestionDraft(task = {}, candidate = {}, question = {}, provider = \"unknown\", index = 0)"],
  ["worker custom question draft plan merger", "function addCustomQuestionDraftsToPlan(plan = {}, task = {}, candidate = {}, unresolved = [], provider = \"unknown\")"],
  ["worker custom question draft result metadata", "custom_question_drafts_count"],
  ["worker internal draft engine", "engine: \"senna_rule_draft\""],
  ["worker external answer generator disabled", "claude_enabled: false"],
  ["worker custom narrative skip before fill", "isCustomNarrativeApplicationQuestion(question)"],
  ["worker cover letter must be requested", "return coverLetterRequested;"],
  ["worker Workday answer plan top-level result", "workday_answer_plan: task.__sffc_workday_answer_plan || null"],
  ["worker field resolution order", "\"user_confirmed_profile\""],
  ["worker candidate name profile first", "profileAnswers.name || profileAnswers.full_name || task.candidate_name"],
  ["worker candidate email profile first", "profileAnswers.email || task.candidate_email"],
  ["worker candidate phone profile first", "profileAnswers.phone || profileAnswers.mobile || getCandidatePhone(task)"],
  ["worker candidate location profile first", "profileAnswers.current_location || profileAnswers.location || getCandidateAddress(task)"],
  ["worker notice default mapping", '"notice_period", "availability"'],
  ["worker sponsorship mapping", '"requires_sponsorship", "visa_sponsorship", "sponsorship"'],
  ["worker emirati mapping", '"emirati_national", "uae_national"'],
  ["worker adjustments mapping", '"reasonable_adjustments", "accommodation"'],
  ["worker other opportunities mapping", '"consider_other_roles", "other_opportunities"'],
  ["worker salary mapping", '"salary_expectation", "expected_salary"'],
].forEach(([label, needle]) => assertIncludes(workerSource, label, needle));

const profileRailCount = (
  phpSource.match(/data-sffc-apply-chat-open-profile/g) || []
).length;
if (profileRailCount < 2) {
  throw new Error(
    `Expected My profile rail button in both apply-chat render paths; found ${profileRailCount}`
  );
}

const cssSource = fs.readFileSync(
  path.join(root, "assets/css/crm/crm-apply-chat-article.css"),
  "utf8"
);
[
  ["profile card message shell", ".sffc-crm-apply-chat__message.has-application-profile-card"],
  ["profile panel css", ".sffc-crm-apply-chat__application-profile"],
  ["profile field css", ".sffc-crm-apply-chat__application-profile-field"],
  ["readiness card css", ".sffc-crm-apply-chat__application-readiness"],
  ["readiness list css", ".sffc-crm-apply-chat__application-readiness-list"],
  ["readiness actions css", ".sffc-crm-apply-chat__application-readiness-actions"],
  ["custom question draft card css", ".sffc-crm-apply-chat__question-drafts"],
  ["custom question draft answer css", ".sffc-crm-apply-chat__question-draft-answer"],
  ["custom question draft approval css", ".sffc-crm-apply-chat__question-draft.is-approved"],
  ["application progress card css", ".sffc-crm-apply-chat__application-progress"],
  ["application progress row css", ".sffc-crm-apply-chat__application-progress-row"],
  ["application progress active css", ".sffc-crm-apply-chat__application-progress-row.is-active"],
  ["application progress blocked css", ".sffc-crm-apply-chat__application-progress-row.is-blocked"],
  ["profile mobile css", "@media (max-width: 900px)"],
].forEach(([label, needle]) => assertIncludes(cssSource, label, needle));

[
  ["chat custom draft extractor", "function getApplicationWorkerCustomQuestionDrafts(data)"],
  ["chat custom draft renderer", "function renderApplicationCustomQuestionDraftsCard(drafts, queueItem)"],
  ["chat custom draft approval persistence", "function saveApplicationCustomQuestionDraftApproval(draft, approvedAnswer)"],
  ["chat custom draft pending state", "pendingApplicationCustomQuestionDrafts"],
  ["chat custom draft queue item state", "pendingApplicationCustomQuestionDraftQueueItem"],
  ["chat custom draft resumes application", "I’ll continue the employer form with the approved answer now."],
  ["chat custom draft requeues approved answer", "The application is queued again with the approved employer answer."],
  ["chat review draft status label", "Review draft answers"],
  ["chat custom draft use action", "data-sffc-application-question-draft-use"],
  ["chat custom draft edit action", "data-sffc-application-question-draft-edit"],
  ["chat answer memory merge", "saved.answerMemory"],
  ["chat application progress state", "applicationProgressCardState"],
  ["chat application progress detector", "function containsApplicationProgressHtml(html)"],
  ["chat application progress renderer", "function renderApplicationProgressCard(progress)"],
  ["chat application progress updater", "function showOrUpdateApplicationProgressCard(progress, options)"],
  ["chat application progress worker bridge", "function updateApplicationProgressForTask("],
  ["chat application progress status source", "getCommercialApplyQueueStatusModel(item)"],
  ["chat application progress card hook", "data-sffc-application-progress-card"],
].forEach(([label, needle]) => assertIncludes(chatSource, label, needle));

console.log("Apply chat ApplicationProfile phase 1-9 contract checks passed.");
