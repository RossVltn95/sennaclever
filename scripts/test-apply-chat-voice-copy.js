#!/usr/bin/env node

const fs = require("fs");
const path = require("path");

const sourcePath = path.join(__dirname, "../assets/js/crm/crm-apply-chat-article.js");
const source = fs.readFileSync(sourcePath, "utf8");
const cssPath = path.join(__dirname, "../assets/css/crm/crm-apply-chat-article.css");
const cssSource = fs.existsSync(cssPath) ? fs.readFileSync(cssPath, "utf8") : "";
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
    pattern: /Talk me through the service|Run wider search|This role only|Yes, sign me up to the service|No, apply for this role only/,
  },
  {
    label: "Old quick route positioning copy",
    pattern: /This role can stay as a one-role application|wider route makes sense|I have an idea that could work much better|actively source suitable roles/i,
  },
  {
    label: "Old quick route selector bindings",
    pattern: /data-sffc-apply-chat-quick-route-(?:membership|single)|quickRouteMembershipChoice|quickRouteSingleChoice|__sffcOpenMembershipFromQuickRouteSelector|__sffcContinueSingleRoleFromQuickRouteSelector/,
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
    label: "Old language gate greeting",
    pattern: /Hi, I'm Emily, your job search assistant\. Would you prefer English or Arabic\?/,
  },
  {
    label: "Old self-transfer notice",
    pattern: /Transferring to Emily/,
  },
  {
    label: "Old recruiter shortlist heading",
    pattern: /Here are recruiters hiring for your profile|Recruiters Hiring for Your Profile/,
  },
  {
    label: "Old recruiter launcher CTA",
    pattern: /Find Recruiters Hiring|Sample shortlist/,
  },
  {
    label: "Old recruiter shortlist renderer",
    pattern: /sffc-crm-apply-chat__match-preview/,
  },
  {
    label: "Old hidden background-search wording",
    pattern: /I'll handle the search in the background/,
  },
  {
    label: "Old mid-flow Emily re-introduction",
    pattern: /Hi, I'm Emily\./,
  },
  {
    label: "Broken tailoring provider sentence",
    pattern: /I'm tailoring the CV for this the employer|I’m tailoring the CV for this the employer/,
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
  "function getEmilyCopyContract(",
  "function getContextualCvRequestLine(",
  "function getContextualSearchPreferenceAck(",
  "function getApplyResultsPersonalizationLabel(",
  "Matched to this search",
  "Matched to your CV",
  "function getSelectedApplicationStartLine(",
  "function getContextualSocialReply(",
  "function renderApplicationMaterialChoice(",
  "Do you want me to include this tailored version with your application?",
  "Use Tailored CV",
  "Continue with original",
  "I also drafted a cover letter for this based on your skills and experience.",
  "Apply with cover letter",
  "Continue without cover letter",
  "sffc-crm-apply-chat__tailored-cv-document-card is-done has-quality-review",
  "sffc-crm-apply-chat__quick-score-card",
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
  "function renderCoverLetterDecisionPackageHtml(",
  "applicationMaterials:",
  "coverLetterDecision:",
  "syncApplyChatCanonicalState(\"cover_letter_decision\")",
  "Hi, I’m Emily. I’ll help you search for roles, compare them against your CV, and decide what to apply for.",
  "function buildPersonalizedWelcomePills(",
  "function getGeneralWelcomePillLibrary(",
  "function getWelcomeTrendingPillItems(",
  "function getWelcomePillSessionSeed(",
  "function getRecentlyShownWelcomePillLabels(",
  "function rememberShownWelcomePillLabels(",
  "function renderWelcomeTrendingPillsHtml(",
  "data-sffc-apply-chat-welcome-prompt",
  "data-sffc-apply-chat-welcome-pills",
];

const normalizationChecks = [
  {
    label: "find me search query must not become fund me",
    input: "find me investment analyst roles in Dubai",
    expectedAbsent: "fund me",
    expectedPresent: "investment analyst in dubai",
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
    expectedPresent: "private credit in abu dhabi",
  },
  {
    label: "conversational London search must clean to London",
    input: "ok look for jobs in London",
    expectedAbsent: "ok look",
    expectedPresent: "jobs in london",
  },
  {
    label: "help-to-find generic city search must not leak the full sentence",
    input: "i need help to find jobs in dubai",
    expectedAbsent: "i need help",
    expectedPresent: "jobs in dubai",
  },
  {
    label: "Riyadh job search must not become fund/fund a",
    input: "find jobs in riyadh",
    expectedAbsent: "fund",
    expectedPresent: "jobs in riyadh",
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
  const explicitLocationJobSearch = clean.match(
    /^(?:please\s+)?(?:i\s+)?(?:(?:need|want|would like)\s+(?:help\s+)?(?:to\s+|with\s+)?|help me\s+|can you\s+|could you\s+|would you\s+)?(?:find|show|search|look for|list|recommend|get|get me)\s+(?:me\s+)?([\s\S]{0,80}?)\b(?:job|jobs|role|roles|opening|openings|vacanc(?:y|ies)|opportunit(?:y|ies))\b[\s\S]{0,24}\b(?:in|near|around)\s+(dubai|abu dhabi|riyadh|jeddah|doha|qatar|saudi(?: arabia)?|uae|united arab emirates|kuwait|bahrain|oman|muscat|london|middle east|mena|gcc)\b/i
  );
  if (explicitLocationJobSearch && explicitLocationJobSearch[2]) {
    const explicitRole = explicitLocationJobSearch[1]
      .replace(/\b(?:a|an|the|some|any|current|open|live|available|for me|please)\b/gi, " ")
      .replace(/\s+/g, " ")
      .trim();
    const explicitLocation = explicitLocationJobSearch[2].trim().toLowerCase();
    return explicitRole
      ? explicitRole.toLowerCase() + " in " + explicitLocation
      : "jobs in " + explicitLocation;
  }
  const normalized = clean
    .replace(/^(?:please\s+)?(?:can you|could you|would you|will you|please)?\s*/i, "")
    .replace(/^(?:ok|okay|well|right|so|cool|fine|great|thanks|thank you)[,.\s]+/i, "")
    .replace(/^(?:ok|okay|well|right|so)\s+(?:ok|okay|well|right|so)[,.\s]+/i, "")
    .replace(/^(?:new|fresh)\s+search\s*(?:for\s+)?/i, "")
    .replace(/^(?:same|current|that)\s+search\s+(?:but|with|for)\s+/i, "")
    .replace(/^(?:i\s+)?(?:want|wanna|would like|need|am trying|i'm trying|im trying)\s+(?:to\s+)?(?:apply|apply for|find|find me|look for|search for|get|get me|see|view)\s+(?:to\s+|for\s+)?/i, "")
    .replace(/^(?:i\s+)?(?:need|want|would like)\s+help\s+(?:to\s+|with\s+)?(?:find|finding|look for|looking for|search for|searching for|get|get me)\s+(?:to\s+|for\s+)?/i, "")
    .replace(/^(?:help me|can you help me|could you help me)\s+(?:apply|apply for|find|find me|look for|search for|get|get me|see|view)\s+(?:to\s+|for\s+)?/i, "")
    .replace(/^(?:apply|apply for)\s+(?:to\s+|for\s+)?/i, "")
    .replace(/^(?:(?:let'?s|lets)\s+)?(?:show|find|search|look for|list|recommend|pull up|give me|send me)\s+(?:me\s+)?/i, "")
    .replace(/^(?:(?:let'?s|lets)\s+)?(?:look|hunt|search)\s+(?:for\s+)?/i, "")
    .replace(/^(?:me\s+)/i, "")
    .replace(/\b(?:jobs?|roles?|openings?|vacanc(?:y|ies)|opportunit(?:y|ies))\b/gi, " ")
    .replace(/\b(?:for me|please|available|current|open|live|any)\b/gi, " ")
    .replace(/\b(?:only|just|solely)\b/gi, " ")
    .replace(/^\s*(?:in|at|with|for)\s+/i, "")
    .replace(/\s+/g, " ")
    .trim();
  if (
    /^(?:dubai|abu dhabi|riyadh|jeddah|doha|qatar|saudi|saudi arabia|uae|united arab emirates|kuwait|bahrain|oman|muscat|london|middle east|mena|gcc)$/i.test(normalized) &&
    /\b(?:job|jobs|role|roles|opening|openings|vacanc(?:y|ies)|opportunit(?:y|ies))\b/i.test(clean)
  ) {
    return "jobs in " + normalized;
  }
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
if (!/application-material-(?:card|choice)/.test(containsIntroApplyCardBody)) {
  failures.push("Structured message detector must include application material choice cards");
}

const quickPathSelectorBody = getFunctionBody("renderApplyQuickPathSelector");
if (/quick-route-chat|quick-route-actions|data-sffc-apply-chat-quick-route/.test(quickPathSelectorBody)) {
  failures.push("Apply quick path selector must render the material CV choice, not old quick-route upsell");
}
if (!/renderApplicationMaterialChoice\("cv"\)/.test(quickPathSelectorBody)) {
  failures.push("Apply quick path selector must ask for tailored CV vs original CV");
}

const quickRoleInsightsBody = getFunctionBody("renderApplyQuickRoleInsights");
if (/sffc-crm-apply-chat__quick-insights/.test(quickRoleInsightsBody)) {
  failures.push("Apply role insight surface must no longer render the old quick-insights card");
}
if (!/renderCvTailoringPreviewCard\("done"\)/.test(quickRoleInsightsBody)) {
  failures.push("Apply role insight surface must render the tailored CV document card");
}

const cvTailoringPreviewBody = getFunctionBody("renderCvTailoringPreviewCard");
[
  "sffc-crm-apply-chat__tailored-cv-document-card is-done has-quality-review",
  "tailoredScoreCard",
  "renderApplyQuickInsightsScoreSummary",
].forEach((needle) => {
  if (!cvTailoringPreviewBody.includes(needle)) {
    failures.push(`Tailored CV document card missing ${needle}`);
  }
});

const continueApplyAfterAnalysisBody = getFunctionBody("continueApplyAfterAnalysis");
[
  "I also drafted a cover letter for this based on your skills and experience.",
  "buildCoverLetterDraftHtml",
  "renderCoverLetterDecisionPackageHtml",
].forEach((needle) => {
  if (!continueApplyAfterAnalysisBody.includes(needle)) {
    failures.push(`Apply analysis flow missing ${needle}`);
  }
});
[
  {
    label: "apply_cv_decision",
    pattern: /setPromptState\(\s*"apply_cv_decision"/,
  },
  {
    label: "apply_cover_letter_check",
    pattern: /setPromptState\(\s*"apply_cover_letter_check"/,
  },
].forEach((check) => {
  if (!check.pattern.test(continueApplyAfterAnalysisBody)) {
    failures.push(`Apply analysis flow missing ${check.label} prompt state`);
  }
});
if (/showManagedSearchSampleResults|Keep finding me roles|Just this role|apply_route_choice/.test(continueApplyAfterAnalysisBody)) {
  failures.push("Apply analysis flow must not route into the old managed-service quick path");
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
if (!/careerConversationMemory\.recentResultEntities\s*=[\s\S]{0,120}normalizeApplyChatMemoryRoleEntityList\(displayItems\)\.slice\(0,\s*12\)/.test(actualResultsBody)) {
  failures.push("Actual job results must write recent result entities back into conversation memory");
}
if (!/careerConversationMemory\.searchHealth\s*=/.test(actualResultsBody)) {
  failures.push("Actual job results must update search health so follow-up role and salary questions have context");
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

const topMatchingPreviewBody = getFunctionBody("buildTopMatchingRolesPreviewHtml");
if (!/renderActualJobPostSearchResults\(topItems,\s*"",\s*\{[\s\S]*hideControls:\s*true/.test(topMatchingPreviewBody)) {
  failures.push("Top matching roles preview must delegate to the unified Google-style result renderer");
}
if (/match-preview|match-preview-list|match-preview-card/.test(topMatchingPreviewBody)) {
  failures.push("Top matching roles preview must not render the old recruiter shortlist card structure");
}
const postCvSearchBody = getFunctionBody("continueJobSearchAfterCvUpload");
if (!/renderActualJobPostSearchResults/.test(postCvSearchBody)) {
  failures.push("Post-CV job search flow must render current role matches through the unified result renderer");
}
if (/buildTopMatchingRolesPreviewHtml/.test(postCvSearchBody)) {
  failures.push("Post-CV job search flow must not use the legacy top-matching preview wrapper");
}
if (/sffc-crm-apply-chat__quick-insights/.test(postCvSearchBody)) {
  failures.push("Post-CV job search flow must not render old quick-insights cards");
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

const languageChoiceButtonsBody = getFunctionBody("showLanguageChoiceButtons");
if (/addChoices|is-clarify|English|العربية/.test(languageChoiceButtonsBody)) {
  failures.push("Welcome must not render the old English/Arabic clarify choices");
}
const languageGateBody = getFunctionBody("showLanguageGate");
if (/language_gate_intro|language_selection|language_gate_reminder|addChoices/.test(languageGateBody)) {
  failures.push("Language gate fallback must delegate to the new welcome launcher");
}
const guestWelcomeBody = getFunctionBody("getEmilyGuestWelcomeMessageItem");
if (/hasLoggedInChatSession\(\)/.test(guestWelcomeBody)) {
  failures.push("Signed-in users must be eligible for the welcome pills");
}
if (!/data-sffc-apply-chat-welcome="1"/.test(guestWelcomeBody)) {
  failures.push("Welcome message must be tagged with the generic welcome marker");
}
const welcomePillLibraryBody = getFunctionBody("getGeneralWelcomePillLibrary");
const welcomePillCount = (welcomePillLibraryBody.match(/createWelcomePill\(/g) || []).length;
if (welcomePillCount < 90) {
  failures.push(`Welcome pill library must be comprehensive; found ${welcomePillCount}`);
}
const personalizedWelcomeBody = getFunctionBody("buildPersonalizedWelcomePills");
[
  "lastLauncherSearch",
  "savedRoleTitles",
  "recentTopics",
  "targetLocations",
  "targetFunctions",
  "targetSectors",
  "hasLoggedInChatSession()",
  "hasLoggedInSavedCvSource()",
].forEach((needle) => {
  if (!personalizedWelcomeBody.includes(needle)) {
    failures.push(`Personalized welcome pills missing ${needle}`);
  }
});
const welcomeTrendingBody = getFunctionBody("getWelcomeTrendingPillItems");
[
  "getWelcomePillSessionSeed()",
  "getRecentlyShownWelcomePillLabels()",
  "rememberShownWelcomePillLabels(items)",
  "Math.floor(Date.now() / 300000)",
].forEach((needle) => {
  if (!welcomeTrendingBody.includes(needle)) {
    failures.push(`Welcome pill rotation missing ${needle}`);
  }
});
const submitWelcomeBody = getFunctionBody("submitWelcomePillPrompt");
if (!/composer\.dispatchEvent/.test(submitWelcomeBody) || /startConsultantFlow/.test(submitWelcomeBody)) {
  failures.push("Welcome pills must route through the composer submit path, not legacy consultant flow");
}
const careerAdvisorActionsBody = getFunctionBody("getCareerAdvisorChoiceActions");
if (!/data-sffc-apply-chat-welcome-prompt/.test(careerAdvisorActionsBody)) {
  failures.push("Career advisor follow-up actions must route through the central composer prompt path");
}
if (/data-sffc-career-advisor-choice|Find roles that fit me|Review my search|Improve my CV/.test(careerAdvisorActionsBody)) {
  failures.push("Career advisor actions must not render the old independent router labels");
}
if (/data-sffc-career-advisor-choice/.test(source)) {
  failures.push("Apply chat must not keep the old career-advisor click router active");
}
[
  "For Saudi, I would treat the search as a quality-and-access problem",
  "Visa and relocation should be handled as friction points",
  "evidence gaps",
  "Seniority is not just the job title",
  "Salary negotiation should be framed around total package",
  "Recruiter outreach works best when it is specific",
  "The application strategy should depend on fit strength",
].forEach((needle) => {
  if (!source.includes(needle)) {
    failures.push(`Career answer library missing: ${needle}`);
  }
});
[
  "function buildNativeSalaryReasoningAnswer(",
  "function normalizeSalaryFallbackFamily(",
  "function getSalaryMarketDisplayLabel(",
  "function formatSalaryUsdAnnualEquivalent(",
  "function getSalaryMethodLabel(",
  "Abu Dhabi",
  "Jeddah",
  "UAE/Saudi finance salary bands",
  "adjacent finance salary bands and seniority ratios",
  "mathematical role hierarchy fallback",
  "base salary only; bonus, housing, transport, relocation and end-of-service",
].forEach((needle) => {
  if (!source.includes(needle)) {
    failures.push(`Salary reasoning integration missing ${needle}`);
  }
});
const nativeSalaryBody = getFunctionBody("buildNativeSalaryReasoningAnswer");
[
  "buildSalaryBenchmarkRoleFromMessage(value)",
  "pendingApplyResultsSelection",
  "estimateRoleSalary(normalizedRole, null)",
  "formatSalaryUsdAnnualEquivalent(estimate)",
  "Confidence:",
  "Assumptions:",
  "includePositioning",
].forEach((needle) => {
  if (!nativeSalaryBody.includes(needle)) {
    failures.push(`Native salary answer missing ${needle}`);
  }
});
const fallbackSalaryFamilyBody = getFunctionBody("normalizeSalaryFallbackFamily");
[
  "investment",
  "technology",
  "legal",
  "risk_compliance",
  "accounting",
  "commercial",
  "hr",
  "supply_chain",
  "operations",
].forEach((needle) => {
  if (!fallbackSalaryFamilyBody.includes(needle)) {
    failures.push(`Salary fallback family model missing ${needle}`);
  }
});
const roleSalaryBody = getFunctionBody("buildSelectedRoleQuestionReply");
if (!/buildNativeSalaryReasoningAnswer\(value,\s*role/.test(roleSalaryBody)) {
  failures.push("Selected-role salary questions must use the native salary reasoning answer");
}
const salaryTaskBody = getFunctionBody("handleSalaryCompensationTask");
if (!/buildNativeSalaryReasoningAnswer\(value,\s*role/.test(salaryTaskBody)) {
  failures.push("High-priority salary task handler must use the native salary reasoning answer");
}
if (/Ask salary context/.test(salaryTaskBody) && /I can help with compensation, but I need/.test(salaryTaskBody)) {
  failures.push("Salary task handler must not fall back to the old generic context ask");
}
if (!/buildNativeSalaryReasoningAnswer\(\s*sourceText,\s*careerAnswerRole/.test(source)) {
  failures.push("Direct career salary questions must use the native salary reasoning answer");
}
[
  "HR Manager in Dubai",
  "Risk Director in Riyadh",
  "Investment Associate in Jeddah",
].forEach((needle) => {
  if (!source.includes(needle)) {
    failures.push(`Salary free-text examples missing ${needle}`);
  }
});
if (!/appendCareerResumeHint\(\s*directCareerAnswer/.test(executeDecisionBody)) {
  failures.push("Direct career answers must append a natural resume hint when they interrupt a task");
}
if (!/focusCareerFollowUpOrResume\(decision\)/.test(executeDecisionBody)) {
  failures.push("Direct career answers must focus the composer around the interrupted task when relevant");
}
["render_selected_role", "compare_selected_role_cv"].forEach((actionName) => {
  const branchStart = executeDecisionBody.indexOf(`action.type === "${actionName}"`);
  const nextBranchStart = branchStart === -1
    ? -1
    : executeDecisionBody.indexOf('if (action.type === "', branchStart + 1);
  const branchBody = branchStart === -1
    ? ""
    : executeDecisionBody.slice(
        branchStart,
        nextBranchStart === -1 ? executeDecisionBody.length : nextBranchStart
      );
  if (!branchBody || !/renderActualJobPostSearchResults/.test(branchBody)) {
    failures.push(`${actionName} must use the unified result renderer`);
  }
  if (/renderCommercialApplyQueueCard/.test(branchBody)) {
    failures.push(`${actionName} must not render the old commercial queue card`);
  }
});

const sameCvStartBody = getFunctionBody("askApplyResultsSameCvThenStart");
if (/renderCvTailoringPreviewCard\("working"\)/.test(sameCvStartBody)) {
  failures.push("Selected-role same-CV flow must not use the old working tailored-CV card");
}
if (/6500/.test(sameCvStartBody)) {
  failures.push("Selected-role same-CV flow must not use the old fixed 6.5s tailoring timeout");
}
if (!/continueApplyAfterAnalysis\(/.test(sameCvStartBody)) {
  failures.push("Selected-role same-CV flow must route through the unified CV decision renderer");
}
if (!source.includes("function sendSingleCvUploadReceipt(")) {
  failures.push("CV upload flow is missing the single receipt helper");
}
[
  "apply_for_me_role_discovery",
  "activePath === \"apply_for_me\"",
  "activePath === \"apply_intro\"",
  "activePath === \"job_search\"",
].forEach((needle) => {
  if (!source.includes(needle)) {
    failures.push(`Could not inspect CV upload branch ${needle}`);
  }
});
if (/activePath === "apply_intro"[\s\S]{0,3000}renderApplyIntroAnalysisProgressCard/.test(source)) {
  failures.push("Apply intro file upload must not render the old analysis progress card");
}
if (/step === "apply_intro_upload"[\s\S]{0,2500}renderApplyIntroAnalysisProgressCard/.test(source)) {
  failures.push("Apply intro pasted-CV upload must not render the old analysis progress card");
}
if (/activePath === "apply_for_me"[\s\S]{0,900}getApplyFileReviewInProgressCopy/.test(source)) {
  failures.push("Apply-for-me file upload must not emit a second review-progress message");
}
if (/activePath === "job_search"[\s\S]{0,1800}job_search_upload_progress/.test(source)) {
  failures.push("Job-search CV upload must not emit old progress variants after the receipt");
}

if (!cssSource.includes("/* Phase 6 layout contract: one content axis, one composer, no legacy overlays. */")) {
  failures.push("CSS missing Phase 6 final layout contract");
}
[
  "--sffc-chat-rail-width: 228px",
  "--sffc-chat-content-width: min(100%, 1000px)",
  "--sffc-chat-composer-width: min(100%, 1000px)",
  "max-width: 1000px !important",
  "scrollbar-width: none !important",
  "-ms-overflow-style: none !important",
  "content: none !important",
  "pointer-events: auto !important",
  "z-index: 1000007 !important",
  "object-fit: cover !important",
].forEach((needle) => {
  if (!cssSource.includes(needle)) {
    failures.push(`Phase 6 layout CSS missing ${needle}`);
  }
});
if (!/form\.sffc-crm-apply-chat__composer\[data-sffc-apply-chat-composer\][\s\S]{0,900}left:\s*calc\(var\(--sffc-chat-rail-width\)/.test(cssSource)) {
  failures.push("Composer must align from the shared rail/content width variables");
}
if (!/\[data-sffc-apply-chat-desk\]:not\(\[hidden\]\) \[data-sffc-apply-chat-messages\][\s\S]{0,500}width:\s*var\(--sffc-chat-content-width\)/.test(cssSource)) {
  failures.push("Messages must align from the shared content width variable");
}
if (
  !/sffc-crm-apply-chat__message \.sffc-crm-apply-results__result[\s\S]{0,1400}width:\s*100% !important/.test(
    cssSource
  )
) {
  failures.push("Result cards must fill the assistant content column beside the rail");
}
if (!/data-sffc-apply-chat-language-label\]\s*::before[\s\S]{0,120}content:\s*"Languages"/.test(cssSource)) {
  failures.push("Language selector must visibly say Languages");
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

const coverLetterDecisionPackageBody = getFunctionBody("renderCoverLetterDecisionPackageHtml");
[
  "sffc-crm-apply-chat__cover-letter-decision",
  "sffc-crm-apply-chat__cover-letter-decision-intro",
  'renderApplicationMaterialChoice("cover_letter")',
].forEach((needle) => {
  if (!coverLetterDecisionPackageBody.includes(needle)) {
    failures.push(`Cover letter decision package missing ${needle}`);
  }
});

const continueApplyAnalysisCoverBody = getFunctionBody("continueApplyAfterAnalysis");
const askCoverLetterIndex = continueApplyAnalysisCoverBody.indexOf("function askCoverLetterQuestion");
const askTailoringIndex = continueApplyAnalysisCoverBody.indexOf("function askTailoringProceedQuestion");
const askCoverLetterBody =
  askCoverLetterIndex !== -1 && askTailoringIndex !== -1
    ? continueApplyAnalysisCoverBody.slice(askCoverLetterIndex, askTailoringIndex)
    : "";
if (!askCoverLetterBody.includes("renderCoverLetterDecisionPackageHtml")) {
  failures.push("Cover letter decision must render as one structured package");
}
if (/botSequenceForCurrentTurn\(/.test(askCoverLetterBody)) {
  failures.push("Cover letter decision must not be split into a multi-message burst");
}
if (!/applyNeedsCoverLetter = "yes"[\s\S]{0,160}syncApplyChatCanonicalState\("cover_letter_decision"\)/.test(askCoverLetterBody)) {
  failures.push("Cover-letter yes decision must sync canonical state");
}
if (!/applyNeedsCoverLetter = "no"[\s\S]{0,160}syncApplyChatCanonicalState\("cover_letter_decision"\)/.test(askCoverLetterBody)) {
  failures.push("Cover-letter no decision must sync canonical state");
}

const canonicalNormalizeBody = getFunctionBody("normalizeApplyChatCanonicalState");
[
  "applicationMaterialsSource",
  "applicationMaterials:",
  "coverLetterDecision",
  "cover_letter_decision",
].forEach((needle) => {
  if (!canonicalNormalizeBody.includes(needle)) {
    failures.push(`Canonical state normalizer missing ${needle}`);
  }
});

const canonicalBuildBody = getFunctionBody("buildApplyChatCanonicalState");
[
  "applicationMaterials:",
  'cvVersion: applyCvReviewSkipped ? "original" : "tailored"',
  "coverLetterDecision: cleanMessageText(applyNeedsCoverLetter || \"\")",
  "coverLetterStatus:",
].forEach((needle) => {
  if (!canonicalBuildBody.includes(needle)) {
    failures.push(`Canonical state builder missing ${needle}`);
  }
});

if (!cssSource.includes(".sffc-crm-apply-chat__cover-letter-decision")) {
  failures.push("Cover letter decision package missing CSS");
}

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

const reviewBody = getFunctionBody("renderResultApplicationReview");
[
  "Senna application workspace",
  "Fields detected",
  "CV attached",
  "Answers prepared",
  "Required missing info",
  "Provider status",
  "Final submit state",
  "Open employer form",
  "Try live embed",
  "Refresh preview",
  "data-sffc-apply-results-native-workspace",
  "data-sffc-apply-results-try-live-embed",
  "data-sffc-apply-results-refresh-preview",
].forEach((needle) => {
  if (!reviewBody.includes(needle)) {
    failures.push(`Provider-aware application review missing ${needle}`);
  }
});
if (!/greenhouse\|workable\|workday\|successfactors\|teamtailor\|simple_form/.test(reviewBody)) {
  failures.push("Provider-aware application review must route supported adapters to the native workspace");
}
if (!/This employer form may block embeds/.test(reviewBody)) {
  failures.push("Blocked application providers must explain the live-preview fallback");
}
const providerKeyBody = getFunctionBody("getCommercialApplyQueueProviderKey");
[
  "workday",
  "successfactors",
  "greenhouse",
  "workable",
  "teamtailor",
  "simple_form",
].forEach((needle) => {
  if (!providerKeyBody.includes(needle)) {
    failures.push(`Provider key routing missing ${needle}`);
  }
});
[
  { label: "greenhouse.io", pattern: /greenhouse\\\.io/ },
  { label: "myworkdayjobs", pattern: /myworkdayjobs/ },
  { label: "workdaysite", pattern: /workdaysite/ },
  { label: "lever.co", pattern: /lever\\\.co/ },
  { label: "teamtailor.com", pattern: /teamtailor\\\.com/ },
  { label: "workable.com", pattern: /workable\\\.com/ },
  { label: "recruitee.com", pattern: /recruitee\\\.com/ },
  { label: "successfactors", pattern: /successfactors/ },
].forEach((check) => {
  if (!check.pattern.test(source)) {
    failures.push(`Application review provider labelling missing ${check.label}`);
  }
});
const reviewFrameBlockedBody = getFunctionBody("isKnownFrameBlockedApplicationUrl");
[
  { label: "workable", pattern: /workable/ },
  { label: "greenhouse", pattern: /greenhouse/ },
  { label: "successfactors", pattern: /successfactors/ },
  { label: "recruitee.com", pattern: /recruitee\\\.com/ },
  { label: "teamtailor.com", pattern: /teamtailor\\\.com/ },
  { label: "smartrecruiters.com", pattern: /smartrecruiters\\\.com/ },
  { label: "comeet.com", pattern: /comeet\\\.com/ },
].forEach((check) => {
  if (!check.pattern.test(source)) {
    failures.push(`Application review embed/preview routing missing ${check.label}`);
  }
});

[
  "sffc-crm-apply-results__review-url",
  "sffc-crm-apply-results__native-workspace",
  "sffc-crm-apply-results__native-workspace-grid",
].forEach((needle) => {
  if (!cssSource.includes(needle)) {
    failures.push(`Provider-aware application review CSS missing ${needle}`);
  }
});

[
  "applyResultsTryLiveEmbed",
  "applyResultsRefreshPreview",
  "requestApplyResultsApplicationPreview(refreshPreview)",
].forEach((needle) => {
  if (!source.includes(needle)) {
    failures.push(`Provider-aware application review handler missing ${needle}`);
  }
});

if (failures.length) {
  console.error("FAIL apply-chat voice-copy checks");
  failures.forEach((failure) => console.error(`- ${failure}`));
  process.exit(1);
}

console.log(`PASS apply-chat voice-copy checks (${required.length} hooks, ${forbidden.length} regressions guarded)`);
