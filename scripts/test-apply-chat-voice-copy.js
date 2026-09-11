#!/usr/bin/env node

const fs = require("fs");
const path = require("path");

const sourcePath = path.join(__dirname, "../assets/js/crm/crm-apply-chat-article.js");
const source = fs.readFileSync(sourcePath, "utf8");
const shortcodePath = path.join(__dirname, "../includes/crm/class-crm-shortcodes.php");
const shortcodeSource = fs.existsSync(shortcodePath)
  ? fs.readFileSync(shortcodePath, "utf8")
  : "";

const forbidden = [
  {
    label: "Repeated generic launcher prompt",
    pattern: /pickVariant\("launcher_intro_(?:warm|sharp)"[\s\S]*?How would you like to proceed\?[\s\S]*?How would you like to proceed\?/,
  },
  {
    label: "Old CV upload explanation",
    pattern: /The point of uploading the CV is just to anchor the matching to your background/,
  },
  {
    label: "Old generic search update acknowledgement",
    pattern: /"Got it\. I(?:'|’)ll update the search around that and refresh the roles\."/,
  },
  {
    label: "Old no-active-application line outside contextual helper",
    pattern: /botMessage\(\s*"There is not an active application in this chat right now\./,
  },
  {
    label: "Old decision validation fallback",
    pattern: /"I want to make sure I answer the right thing\. Do you want to update the search, continue the application, or ask a career question\?"/,
  },
  {
    label: "Old membership-vs-single-role route prompt",
    pattern: /sign up to the service or apply for this role only/i,
  },
  {
    label: "Old quick route service CTA",
    pattern: /Talk me through the service|Run wider search|This role only/,
  },
  {
    label: "Old quick route positioning copy",
    pattern: /This role can stay as a one-role application|wider route makes sense/i,
  },
  {
    label: "Old generic CV-to-search progress",
    pattern: /I'm searching for jobs that match your skills and experience/,
  },
  {
    label: "Old generic same-CV application prompt",
    pattern: /Great\. Let's get your application ready\. Am I still using/,
  },
  {
    label: "Old generic current-CV warning",
    pattern: /Sure\. I’ll use the current CV\. Just keep the points above in mind/,
  },
  {
    label: "Old generic quick-insights strength line",
    pattern: /You've got a good base here/,
  },
  {
    label: "Old repeated profile-review follow-up line",
    pattern: /I would start here: make the most relevant proof easier to find/,
  },
  {
    label: "Old generic cover-letter help filler",
    pattern: /I can help write it[\s\S]{0,120}tighten it so it sounds targeted and credible/,
  },
  {
    label: "Robotic direct CV command",
    pattern: /"Send me your CV| "Send your CV| "Send me the CV| "Send the CV/,
  },
  {
    label: "Casual positive emphasis filler",
    pattern: /I know, right|Love that/,
  },
  {
    label: "Awkward firstly phrasing",
    pattern: /Firstly, can I get/,
  },
  {
    label: "Old managed-service completion burst",
    pattern: /All set up! I'll get everything prepared on my side/,
  },
  {
    label: "Repeated vague check filler",
    pattern: /Let me just check one more thing/,
  },
];

const required = [
  "function getEmilyVoiceContext(",
  "function getContextualCvRequestLine(",
  "function getContextualSearchPreferenceAck(",
  "function getApplyResultsPersonalizationLabel(",
  "Matched to this search",
  "Matched to your CV",
  "function getSelectedApplicationStartLine(",
  "function getContextualSocialReply(",
  "Keep finding me roles",
  "Just this role",
  "approach relevant recruiters",
  "function looksLikeCareerDecisionQuestion(",
  "function looksLikeRecruiterNonResponseQuestion(",
  "function looksLikeMisroutedSearchComplaint(",
  "function getRecruiterNonResponseAnswer(",
  "function getMoveOrStayCareerDecisionAnswer(",
  "function getSennaContactAnswer(",
  "function renderApplyResultsCvImprovementPlanHtml(",
  "function getApplyChatCompanyLogoValue(",
  "function buildCoverLetterDraftHtml(",
  "function getCoverLetterTemplatePool(",
  "function getNextCoverLetterTemplate(",
  "function getCoverLetterTemplateLibraryHtml(",
  "function handleCoverLetterDraftTask(",
  "function handleApplicationMaterialChoice(",
];

const normalizationChecks = [
  {
    label: "find me search query must not become fund me",
    input: "find me investment analyst roles in Dubai",
    expectedAbsent: "fund me",
    expectedPresent: "investment analyst dubai",
  },
  {
    label: "application intent language must not render as search text",
    input: "i want to apply riyadh",
    expectedAbsent: "i want to apply",
    expectedPresent: "riyadh",
  },
  {
    label: "help me apply language must clean to criteria",
    input: "help me apply for private credit roles in Abu Dhabi",
    expectedAbsent: "help me apply",
    expectedPresent: "private credit abu dhabi",
  },
  {
    label: "conversational London search must clean to London",
    input: "ok look for jobs in London",
    expectedAbsent: "ok look",
    expectedPresent: "london",
  },
];

function getFunctionBody(name) {
  const marker = `function ${name}(`;
  const start = source.indexOf(marker);
  if (start === -1) {
    failures.push(`Missing function for source-flow check: ${name}`);
    return "";
  }
  const bodyStart = source.indexOf("{", start);
  let depth = 0;
  for (let index = bodyStart; index < source.length; index += 1) {
    const char = source[index];
    if (char === "{") depth += 1;
    if (char === "}") {
      depth -= 1;
      if (depth === 0) {
        return source.slice(bodyStart + 1, index);
      }
    }
  }
  failures.push(`Could not parse function body for source-flow check: ${name}`);
  return "";
}

function normalizeApplyChatJobSearchQueryForTest(value) {
  const clean = String(value || "").replace(/\s+/g, " ").trim();
  const normalized = clean
    .replace(/^(?:please\s+)?(?:can you|could you|would you|will you|please)?\s*/i, "")
    .replace(/^(?:ok|okay|well|right|so|cool|fine|great|thanks|thank you)[,.\s]+/i, "")
    .replace(/^(?:ok|okay|well|right|so)\s+(?:ok|okay|well|right|so)[,.\s]+/i, "")
    .replace(/^(?:i\s+)?(?:want|wanna|would like|need|am trying|i'm trying|im trying)\s+(?:to\s+)?(?:apply|apply for|find|look for|search for|get|see|view)\s+(?:to\s+|for\s+)?/i, "")
    .replace(/^(?:help me|can you help me|could you help me)\s+(?:apply|apply for|find|look for|search for|get|see|view)\s+(?:to\s+|for\s+)?/i, "")
    .replace(/^(?:apply|apply for)\s+(?:to\s+|for\s+)?/i, "")
    .replace(/^(?:show|find|search|look for|list|recommend|pull up|give me|send me)\s+(?:me\s+)?/i, "")
    .replace(/\b(?:jobs?|roles?|openings?|vacanc(?:y|ies)|opportunit(?:y|ies))\b/gi, " ")
    .replace(/\b(?:for me|please|available|current|open|live|any)\b/gi, " ")
    .replace(/\b(?:in|at|with|for)\s+/gi, " ")
    .replace(/\s+/g, " ")
    .trim();
  return normalized || clean;
}

const failures = [];

forbidden.forEach((check) => {
  if (check.pattern.test(source)) {
    failures.push(check.label);
  }
});

required.forEach((needle) => {
  if (!source.includes(needle)) {
    failures.push(`Missing required voice-copy hook: ${needle}`);
  }
});

normalizationChecks.forEach((check) => {
  const actual = normalizeApplyChatJobSearchQueryForTest(check.input).toLowerCase();
  if (actual.includes(check.expectedAbsent) || !actual.includes(check.expectedPresent)) {
    failures.push(`${check.label}: "${actual}"`);
  }
});

const compareThenConfirmBody = getFunctionBody("showApplyResultsComparisonThenConfirmCv");
if (/humanComposeDelay\("CV role comparison\."[\s\S]{0,300}askApplyResultsSameCvThenStart/.test(compareThenConfirmBody)) {
  failures.push("CV comparison should not auto-start same-CV/application flow");
}

if (!/data-sffc-apply-results-selected-next="original">Jump to application/.test(source)) {
  failures.push("Guest Jump to application must use original/direct application route");
}

if (!/data-sffc-apply-results-selected-next="tailored">Tailor CV/.test(source)) {
  failures.push("Tailored CV button must use explicit tailored route");
}

const containsIntroApplyCardBody = getFunctionBody("containsIntroApplyCardHtml");
if (/quick-route-chat/.test(containsIntroApplyCardBody)) {
  failures.push("Quick route chat must render inside the normal Emily message bubble with avatar");
}

const frameBlockedBody = getFunctionBody("isKnownFrameBlockedApplicationUrl");
if (/jobs\\\.workable\\\.com\$/.test(frameBlockedBody)) {
  failures.push("Workable job pages must attempt iframe embed before screenshot fallback");
}

const concreteSearchBody = getFunctionBody("looksLikeConcreteApplyChatJobSearch");
[
  "looksLikeCareerDecisionQuestion(clean)",
  "looksLikeRecruiterNonResponseQuestion(clean)",
  "looksLikeSennaContactQuestion(clean)",
  "looksLikeMisroutedSearchComplaint(clean)",
].forEach((needle) => {
  if (!concreteSearchBody.includes(needle)) {
    failures.push(`Concrete search guard missing ${needle}`);
  }
});

const hardConstraintsBody = getFunctionBody("getApplyResultsHardSearchConstraints");
if (!/locations\.push\("london"\)/.test(hardConstraintsBody)) {
  failures.push("London must be a hard search location constraint");
}

const careerClassifierBody = getFunctionBody("classifyCareerConversationMessage");
[
  "RECRUITER_NON_RESPONSE",
  "CAREER_DECISION",
  "SUPPORT_CONTACT",
  "ANSWER_QUALITY_COMPLAINT",
].forEach((needle) => {
  if (!careerClassifierBody.includes(needle)) {
    failures.push(`Career classifier missing ${needle}`);
  }
});

const directCareerAnswerBody = getFunctionBody("getDirectCareerQuestionAnswer");
[
  "getRecruiterNonResponseAnswer()",
  "getMoveOrStayCareerDecisionAnswer()",
  "getSennaContactAnswer()",
  "getAnswerQualityRecoveryAnswer(clean)",
].forEach((needle) => {
  if (!directCareerAnswerBody.includes(needle)) {
    failures.push(`Direct career answer missing ${needle}`);
  }
});

const premiumImprovementBody = getFunctionBody("showApplyResultsComparisonThenChoice");
if (!/renderApplyResultsCvImprovementPlanHtml\(pendingApplyResultsSelection\)/.test(premiumImprovementBody)) {
  failures.push("Premium Improve CV first must render the dedicated CV improvement plan");
}
if (/botMessage\(\s*renderApplyResultsSelectedRoleActions/.test(premiumImprovementBody)) {
  failures.push("Premium Improve CV first must not render an action-only repeated options bubble");
}
if (/continueApplyResultsSelectedRole\("compare"\)/.test(premiumImprovementBody)) {
  failures.push("Premium Improve CV first must not loop back into compare");
}
if (!/cleanProvider === "workable"[\s\S]{0,220}return false/.test(frameBlockedBody)) {
  failures.push("Workable provider must be treated as embeddable first");
}
if (!/cleanProvider === "greenhouse"[\s\S]{0,240}return false/.test(frameBlockedBody)) {
  failures.push("Greenhouse provider must be treated as embeddable first");
}

const hydrateReviewBody = getFunctionBody("hydrateApplyResultsReviewPanel");
if (!/if \(preview && !preview\.hidden\)[\s\S]{0,120}requestApplyResultsApplicationPreview\(preview\)/.test(hydrateReviewBody)) {
  failures.push("Screenshot preview should only auto-request when the fallback preview is visible");
}

const roleModalUrlBody = getFunctionBody("getApplicationQueueRoleModalUrl");
if (/source\.viewUrl|source\.url/.test(roleModalUrlBody)) {
  failures.push("Employer application URL resolver must not fall back to Senna role view URLs");
}
if (!/isCurrentRole[\s\S]{0,120}!isInternalSennaApplicationUrl\(applicationUrl\)[\s\S]{0,80}applicationUrl/.test(roleModalUrlBody)) {
  failures.push("Global applicationUrl fallback must be limited to the current selected role");
}

const setContextBody = getFunctionBody("setCurrentApplicationContextFromQueueItem");
if (/source\.viewUrl[\s\S]{0,120}applicationUrl|applicationUrl[\s\S]{0,120}source\.viewUrl/.test(setContextBody)) {
  failures.push("Selected application context must not promote role viewUrl into applicationUrl");
}

const actualResultsBody = getFunctionBody("renderActualJobPostSearchResults");
if (!/var displayQuery = normalizeApplyChatJobSearchQuery\(\s*query \|\| ""\s*\)/.test(actualResultsBody)) {
  failures.push("Actual job results must normalize command text before rendering the search input");
}
if (/escapeHtml\(query \|\| ""\)/.test(actualResultsBody)) {
  failures.push("Actual job results search input must not render the raw user command");
}
const inlineReviewBody = getFunctionBody("getInlineReviewUrl");
if (/viewUrl|\.url/.test(inlineReviewBody)) {
  failures.push("Inline employer review URL must not fall back to Senna role URLs");
}
if (!/getExternalEmployerApplicationUrlFromItem\(item \|\| \{\}\)/.test(inlineReviewBody)) {
  failures.push("Inline employer review URL must use the external employer URL resolver");
}
if (/var itemApplicationUrl = cleanMessageText\(/.test(source)) {
  failures.push("Provider worker helpers must not build application URLs directly from raw item fields");
}

const jobsWorkspaceBody = getFunctionBody("renderJobsWorkspaceHtml");
if (!/var displayQuery = normalizeApplyChatJobSearchQuery\(\s*jobsWorkspaceSearchQuery \|\| ""\s*\)/.test(jobsWorkspaceBody)) {
  failures.push("Jobs workspace must normalize command text before filtering and rendering");
}
if (/escapeHtml\(jobsWorkspaceSearchQuery \|\| ""\)/.test(jobsWorkspaceBody)) {
  failures.push("Jobs workspace search input must not render raw user commands");
}

const queueCardBody = getFunctionBody("renderCommercialApplyQueueCard");
if (!/var displayQuery = normalizeApplyChatJobSearchQuery\(\s*jobsWorkspaceSearchQuery \|\| ""\s*\)/.test(queueCardBody)) {
  failures.push("Apply results queue must normalize command text before filtering and rendering");
}
if (/escapeHtml\(jobsWorkspaceSearchQuery \|\| ""\)/.test(queueCardBody)) {
  failures.push("Apply results queue search input must not render raw user commands");
}

const profileReviewFollowUpBody = getFunctionBody("handleProfileReviewFollowUp");
if (!/profile_review_social_ack/.test(profileReviewFollowUpBody)) {
  failures.push("Profile review follow-up must acknowledge social/flattery messages instead of rerunning CV advice");
}
if (!/looksLikeConcreteApplyChatJobSearch\(value, intent\)[\s\S]{0,120}searchActualJobPostsInChat\(value\)/.test(profileReviewFollowUpBody)) {
  failures.push("Profile review follow-up must route job-search messages to job results");
}
if (!/directCareerAnswer = getDirectCareerQuestionAnswer\(intent, value\)/.test(profileReviewFollowUpBody)) {
  failures.push("Profile review follow-up must answer substantive career questions before CV fallback");
}
const executeDecisionBody = getFunctionBody("executeConversationDecision");
if (
  !/directAnswerIntent/.test(executeDecisionBody) ||
  !/directCareerAnswer = getDirectCareerQuestionAnswer/.test(executeDecisionBody) ||
  !/botMessage\(\s*directCareerAnswer/.test(executeDecisionBody)
) {
  failures.push("Conversation decision answer_directly must use the career answer generator before generic fallback");
}
if (!/The highest-impact move is usually not more applications/.test(source)) {
  failures.push("Dubai improvement question must have a concrete answer, not a generic bridge");
}

const queueTaskBody = getFunctionBody("queueBrowserApplicationTask");
const itemApplicationUrlAssignment = (queueTaskBody.match(
  /var itemApplicationUrl =[\s\S]*?;\n/
) || [""])[0];
if (/item\.viewUrl|item\.url\s*\|\||\|\|\s*applicationUrl/.test(itemApplicationUrlAssignment)) {
  failures.push("Application worker must only receive resolved external employer application URLs");
}
if (!/isInternalSennaApplicationUrl\(itemApplicationUrl\)/.test(queueTaskBody)) {
  failures.push("Application worker must reject internal Senna URLs before queueing");
}
if (!source.includes("function isInternalSennaProviderValue(")) {
  failures.push("Provider labels must sanitize internal Senna provider values");
}

const coverLetterTemplatePoolBody = getFunctionBody("getCoverLetterTemplatePool");
const coverLetterTemplateIds = coverLetterTemplatePoolBody.match(/id:\s*"[^"]+"/g) || [];
if (coverLetterTemplateIds.length !== 20) {
  failures.push(`Cover letter template pool must contain exactly 20 templates; found ${coverLetterTemplateIds.length}`);
}
[
  "Direct Role Fit",
  "Career Switch",
  "Dubai Relocation",
  "Riyadh Relocation",
  "Investment Analyst",
  "Financial Analyst",
  "Post-Application Follow-Up",
].forEach((needle) => {
  if (!coverLetterTemplatePoolBody.includes(needle)) {
    failures.push(`Cover letter template pool missing ${needle}`);
  }
});

const nextCoverLetterTemplateBody = getFunctionBody("getNextCoverLetterTemplate");
[
  "shownCoverLetterTemplateIds",
  "queueApplyChatMemoryPersist",
  "dedupeList",
].forEach((needle) => {
  if (!nextCoverLetterTemplateBody.includes(needle)) {
    failures.push(`Cover letter template rotation missing ${needle}`);
  }
});

const coverLetterTemplateHtmlBody = getFunctionBody("getCoverLetterTemplateLibraryHtml");
if (!/getNextCoverLetterTemplate\(\)/.test(coverLetterTemplateHtmlBody)) {
  failures.push("Cover letter tips must render the next unseen template, not the whole pool");
}
if (/\.(map|forEach)\(\s*function/.test(coverLetterTemplateHtmlBody) || /<ol|<li/.test(coverLetterTemplateHtmlBody)) {
  failures.push("Cover letter tips must not render all templates at once");
}
if (!source.includes('"shownCoverLetterTemplateIds"')) {
  failures.push("Apply-chat memory normalizer must persist shown cover letter template ids");
}

const coverLetterDraftBody = getFunctionBody("buildCoverLetterDraftHtml");
[
  "Dear Hiring Team",
  "Kind regards",
  "getCoverLetterEvidenceSignals",
  "renderApplicationMaterialCoverLetterDocument",
].forEach((needle) => {
  if (!coverLetterDraftBody.includes(needle)) {
    failures.push(`Cover letter draft renderer missing ${needle}`);
  }
});

const coverLetterDocumentBody = getFunctionBody("renderApplicationMaterialCoverLetterDocument");
[
  "sffc-crm-apply-chat__cover-letter-window",
  "sffc-crm-apply-chat__cover-letter-sheet",
  "sffc-crm-apply-chat__cover-letter-recipient",
  "sffc-crm-apply-chat__cover-letter-body-text",
  "sffc-crm-apply-chat__cover-letter-sheet-signoff",
].forEach((needle) => {
  if (!coverLetterDocumentBody.includes(needle)) {
    failures.push(`Cover letter document renderer missing ${needle}`);
  }
});

const appMaterialTaskBody = getFunctionBody("handleApplicationMaterialTask");
if (!/materialChoice === "cover_letter"[\s\S]{0,80}handleCoverLetterDraftTask/.test(appMaterialTaskBody)) {
  failures.push("Explicit cover-letter draft requests must render a draft immediately");
}
if (!/setPromptState\(\s*"application_material_choice"/.test(appMaterialTaskBody)) {
  failures.push("Application material task must keep a material-choice prompt state");
}

const appMaterialChoiceBody = getFunctionBody("handleApplicationMaterialChoice");
if (!/choice === "cover_letter"[\s\S]{0,80}handleCoverLetterDraftTask/.test(appMaterialChoiceBody)) {
  failures.push("Application material choice must route cover letter to a real draft");
}

const promptStateBody = getFunctionBody("isConversationalPromptState");
if (!/application_material_choice/.test(promptStateBody)) {
  failures.push("Application material choice must be registered as a conversational prompt state");
}

const strictPromptStateBody = getFunctionBody("isStrictPromptOwnedState");
if (!/application_material_choice/.test(strictPromptStateBody)) {
  failures.push("Application material choice must be registered as a strict prompt-owned state");
}

const logoNormalizerBody = getFunctionBody("getApplyChatCompanyLogoValue");
[
  "companyLogoUrl",
  "company_logo_url",
  "logo_url",
  "thumbnail_url",
  "featured_image",
  "image_url",
].forEach((needle) => {
  if (!logoNormalizerBody.includes(needle)) {
    failures.push(`Apply results logo normalizer missing ${needle}`);
  }
});

const actualItemBody = getFunctionBody("normalizeActualJobPostSearchItem");
if (!/companyLogo:\s*getCommercialApplyQueueSafeLogoUrl\(\s*getApplyChatCompanyLogoValue\(source\)/.test(actualItemBody)) {
  failures.push("Actual job results must normalize and validate company logo URLs");
}

const queueItemBody = getFunctionBody("normalizeCommercialApplyQueueItem");
if (!/var companyLogo = getApplyChatCompanyLogoValue\(/.test(queueItemBody)) {
  failures.push("Apply queue items must preserve company logos from all supported source fields");
}

if (!/function normalize_crm_apply_chat_job_search_item/.test(shortcodeSource)) {
  failures.push("Missing PHP apply-chat job search normalizer");
} else {
  const phpNormalizer = shortcodeSource.slice(
    shortcodeSource.indexOf("private function normalize_crm_apply_chat_job_search_item"),
    shortcodeSource.indexOf("private function search_crm_apply_chat_actual_jobs")
  );
  [
    "'company_logo' => $company_logo",
    "'companyLogo' => $company_logo",
    "'logo' => $company_logo",
    "$item['logo_url']",
    "$item['thumbnail_url']",
    "$item['featured_image']",
    "$item['image_url']",
  ].forEach((needle) => {
    if (!phpNormalizer.includes(needle)) {
      failures.push(`PHP job-search logo payload missing ${needle}`);
    }
  });
}

if (failures.length) {
  console.error("FAIL apply-chat voice-copy checks");
  failures.forEach((failure) => console.error(`- ${failure}`));
  process.exit(1);
}

console.log(`PASS apply-chat voice-copy checks (${required.length} hooks, ${forbidden.length} regressions guarded)`);
