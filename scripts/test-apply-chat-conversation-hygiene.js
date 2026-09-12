#!/usr/bin/env node

const fs = require("fs");
const path = require("path");

const repoRoot = path.join(__dirname, "..");
const sourcePath = path.join(
  repoRoot,
  "assets/js/crm/crm-apply-chat-article.js"
);
const fixturesPath = path.join(
  repoRoot,
  "assets/data/apply-chat-conversation-fixtures.json"
);
const liveSuitesPath = path.join(
  repoRoot,
  "assets/data/apply-chat-live-stress-suites.json"
);
const reportPath = path.join(
  repoRoot,
  "reports/apply-chat-conversation-hygiene.json"
);

const source = fs.readFileSync(sourcePath, "utf8");
const failures = [];

function countMatches(pattern, text = source) {
  const matches = text.match(pattern);
  return matches ? matches.length : 0;
}

function getFunctionBody(name) {
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

function assertIncludes(label, needle) {
  if (!source.includes(needle)) failures.push(`Missing ${label}: ${needle}`);
}

[
  ["audit log exposure", "window.__sffcApplyChatConversationAudit"],
  ["turn audit start", "function beginConversationTurnAudit("],
  ["turn owner marker", "function markConversationTurnOwner("],
  ["output schedule audit", "function recordConversationScheduledOutput("],
  ["output emit audit", "function recordConversationEmittedOutput("],
  ["watchdog audit", "response_watchdog_fallback"],
  ["question-first preflight", "function handleQuestionFirstTurnPreflight("],
  ["application status question preflight", "function looksLikeApplicationStatusQuestion("],
  ["CV version question preflight", "function looksLikeCvVersionQuestion("],
  ["application status preflight answer", "function getApplicationStatusPreflightLine("],
  ["CV version preflight answer", "function getCurrentCvVersionStatusLine("],
  ["duplicate prompt complaint detector", "function looksLikeDuplicatePromptComplaint("],
  ["email purpose detector", "function looksLikeEmailPurposeQuestion("],
  ["prompt slot exposure", "window.__sffcApplyChatPromptSlots"],
  ["canonical prompt slots", "function getCanonicalPromptSlotId("],
  ["prompt slot asked marker", "function markPromptSlotAsked("],
  ["prompt slot resolved marker", "function markPromptSlotResolved("],
  ["duplicate prompt slot suppression", "function shouldSuppressRepeatedPromptSlotAsk("],
  ["pending output timer registry", "pendingEmilyOutputTimers"],
  ["output generation guard", "emilyOutputGeneration"],
  ["output pacing helper", "function getConversationTurnOutputPacing("],
  ["live tailoring run token", "liveTailoringRunToken"],
  ["tailoring completion flag", "applyTailoringSequenceComplete"],
  ["question-aware resume prompt helper", "function getQuestionAwareResumePrompt("],
  ["question-aware resume html helper", "function getQuestionAwareResumeHtml("],
  ["resume prompt normalizer", "function normalizeResumePromptCopy("],
  ["CV fact sync canonical resolver", "function getCanonicalCvProfileForFactSync("],
  ["CV fact canonical state sync guard", "function syncApplyChatCanonicalStateForCvFacts("],
  ["message-start scroll anchor", "function scrollToMessageStart("],
].forEach(([label, needle]) => assertIncludes(label, needle));

const setPromptStateBody = getFunctionBody("setPromptState");
if (!setPromptStateBody.includes("markPromptSlotAsked(nextState")) {
  failures.push("setPromptState does not register prompt-slot asks");
}

const promptSlotSpecBody = getFunctionBody("getPromptSlotSpec");
if (!promptSlotSpecBody.includes("slot: getCanonicalPromptSlotId(promptKey)")) {
  failures.push("getPromptSlotSpec does not expose canonical slots");
}
if (!promptSlotSpecBody.includes("resume: getQuestionAwareResumePrompt(promptKey)")) {
  failures.push("getPromptSlotSpec does not use question-aware resume copy");
}

const canonicalCvFactSyncResolverBody = getFunctionBody(
  "getCanonicalCvProfileForFactSync"
);
if (
  !canonicalCvFactSyncResolverBody.includes(
    'typeof getSafeCanonicalCvProfile === "function"'
  )
) {
  failures.push("CV fact sync resolver does not guard getSafeCanonicalCvProfile scope");
}
if (
  !canonicalCvFactSyncResolverBody.includes(
    'typeof buildCanonicalCvProfile === "function"'
  )
) {
  failures.push("CV fact sync resolver does not guard buildCanonicalCvProfile scope");
}
const syncCvFactsFromCanonicalProfileBody = getFunctionBody(
  "syncCvFactsFromCanonicalProfile"
);
if (
  /profile\s*\|\|\s*getSafeCanonicalCvProfile\s*\(/.test(
    syncCvFactsFromCanonicalProfileBody
  )
) {
  failures.push(
    "syncCvFactsFromCanonicalProfile still directly calls out-of-scope canonical helper"
  );
}
if (
  !syncCvFactsFromCanonicalProfileBody.includes(
    "getCanonicalCvProfileForFactSync(profile)"
  )
) {
  failures.push("syncCvFactsFromCanonicalProfile does not use safe canonical resolver");
}
const mergeCvFactsBody = getFunctionBody("mergeCvFacts");
if (/syncApplyChatCanonicalState\("cv_facts_merged"\)/.test(mergeCvFactsBody)) {
  failures.push("mergeCvFacts still directly calls the scoped canonical state sync function");
}
if (!mergeCvFactsBody.includes('syncApplyChatCanonicalStateForCvFacts("cv_facts_merged")')) {
  failures.push("mergeCvFacts does not use the safe canonical state sync guard");
}
const canonicalStateForCvFactsBody = getFunctionBody(
  "syncApplyChatCanonicalStateForCvFacts"
);
if (!canonicalStateForCvFactsBody.includes('typeof syncApplyChatCanonicalState === "function"')) {
  failures.push("CV fact canonical state guard does not check syncApplyChatCanonicalState scope");
}
if (!canonicalStateForCvFactsBody.includes("careerConversationMemory.cvFacts")) {
  failures.push("CV fact canonical state guard does not preserve facts when canonical sync is unavailable");
}

const scrollToMessageStartBody = getFunctionBody("scrollToMessageStart");
if (!scrollToMessageStartBody.includes("getScrollTopForMessageTarget(row")) {
  failures.push("message-start scroll anchor does not use the scroller-relative target helper");
}
if (!scrollToMessageStartBody.includes("pendingMessagesScrollRaf")) {
  failures.push("message-start scroll anchor does not coordinate with pending scroll RAF");
}
const scrollTargetBody = getFunctionBody("getScrollTopForMessageTarget");
if (!scrollTargetBody.includes("getBoundingClientRect")) {
  failures.push("scroll target helper does not use viewport geometry for nested cards");
}
if (!scrollTargetBody.includes("messages.scrollTop")) {
  failures.push("scroll target helper does not account for the current messages scrollTop");
}
const scrollToWorkspaceCardBody = getFunctionBody("scrollToWorkspaceCard");
if (/target\.offsetTop\s*-/.test(scrollToWorkspaceCardBody)) {
  failures.push("workspace card scroll still uses nested target.offsetTop directly");
}
if (!scrollToWorkspaceCardBody.includes("getScrollTopForMessageTarget(target")) {
  failures.push("workspace card scroll does not use the scroller-relative target helper");
}
const typeEmilyPlainMessageBody = getFunctionBody("typeEmilyPlainMessage");
if (/nextIndex !== index[\s\S]{0,220}scrollToLatest\(\)/.test(typeEmilyPlainMessageBody)) {
  failures.push("typewriter frames still force bottom scrolling while text is revealing");
}
const botMessageNowBody = getFunctionBody("botMessageNow");
if (!botMessageNowBody.includes("scrollToMessageStart(row)")) {
  failures.push("botMessageNow does not anchor normal messages to the new row start");
}
const userMessageBody = getFunctionBody("userMessage");
if (!userMessageBody.includes("scrollToMessageStart(row)")) {
  failures.push("userMessage does not anchor user messages to the new row start");
}

const selectedRoleCvAskBody = getFunctionBody(
  "askForCvBeforeSelectedRoleApplication"
);
if (
  !selectedRoleCvAskBody.includes(
    'shouldSuppressRepeatedPromptSlotAsk("cv_upload"'
  )
) {
  failures.push("selected-role CV ask does not suppress repeated CV prompts");
}
if (!selectedRoleCvAskBody.includes('markPromptSlotAsked("apply_upload"')) {
  failures.push("selected-role CV ask does not register the CV prompt slot");
}
if (!source.includes('markPromptSlotResolved("cv_upload", file.name)')) {
  failures.push("file uploads do not resolve the CV prompt slot");
}
if (countMatches(/markPromptSlotResolved\("cv_upload", value\)/g) < 4) {
  failures.push("pasted CV branches do not consistently resolve the CV slot");
}

const beginTurnAuditBody = getFunctionBody("beginConversationTurnAudit");
if (!beginTurnAuditBody.includes("cancelPendingEmilyOutput()")) {
  failures.push("new user turns do not cancel pending Emily output");
}

const submitHandlerStart = source.indexOf('composer.addEventListener("submit"');
const submitHandlerEnd = source.indexOf('if (isStandaloneMemberDesk', submitHandlerStart);
const submitHandlerBody =
  submitHandlerStart === -1 || submitHandlerEnd === -1
    ? ""
    : source.slice(submitHandlerStart, submitHandlerEnd);
if (!submitHandlerBody) {
  failures.push("Could not inspect composer submit handler ordering");
} else {
  const questionFirstIndex = submitHandlerBody.indexOf(
    "handleQuestionFirstTurnPreflight(value)"
  );
  const decisionIndex = submitHandlerBody.indexOf(
    "handleConversationDecisionBeforePrompt(value)"
  );
  const promptReplyIndex = submitHandlerBody.indexOf("maybeHandlePromptReply(value)");
  if (questionFirstIndex === -1 || promptReplyIndex === -1) {
    failures.push("Composer submit handler is missing question-first or prompt reply checks");
  } else if (questionFirstIndex > promptReplyIndex) {
    failures.push("Question-first preflight must run before prompt reply handling");
  }
  if (decisionIndex === -1 || decisionIndex > promptReplyIndex) {
    failures.push("Decision engine must run before legacy prompt reply fallback");
  }
}

const questionFirstAnswerBody = getFunctionBody("getQuestionFirstPreflightAnswer");
[
  "looksLikeApplicationStatusQuestion(clean)",
  "getApplicationStatusPreflightLine()",
  "looksLikeCvVersionQuestion(clean)",
  "getCurrentCvVersionStatusLine()",
  "getPromptResumeInlineSuffix()",
].forEach((needle) => {
  if (!questionFirstAnswerBody.includes(needle)) {
    failures.push(`Question-first preflight is missing ${needle}`);
  }
});

[
  /Back to this step/i,
  /Back to the application/i,
  /Back to Workday/i,
  /Back to SuccessFactors/i,
  /Back to the employer verification/i,
  /Back to the next step/i,
].forEach((pattern) => {
  if (pattern.test(source)) {
    failures.push(`Question-aware resume copy still contains ${pattern}`);
  }
});

const answerUnifiedSideConversationBody = getFunctionBody(
  "answerUnifiedSideConversation"
);
[
  "getQuestionAwareResumePrompt(",
  "getQuestionAwareResumeHtml(",
  "getWildcardSocialReply(",
].forEach((needle) => {
  if (!answerUnifiedSideConversationBody.includes(needle)) {
    failures.push(`Unified side conversation is missing ${needle}`);
  }
});
if (/botSequenceForCurrentTurn\s*\(/.test(answerUnifiedSideConversationBody)) {
  failures.push("Unified side conversation still uses multi-message resume bursts");
}

const maybeHandleGlobalConversationRouteBody = getFunctionBody(
  "maybeHandleGlobalConversationRoute"
);
[
  "getQuestionAwareResumePrompt(",
  "getQuestionAwareResumeHtml(",
].forEach((needle) => {
  if (!maybeHandleGlobalConversationRouteBody.includes(needle)) {
    failures.push(`Global prompt side conversation is missing ${needle}`);
  }
});
if (/botSequenceForCurrentTurn\s*\(/.test(maybeHandleGlobalConversationRouteBody)) {
  failures.push("Global prompt side conversation still uses multi-message resume bursts");
}

const openQuestionDetourBody = getFunctionBody("openQuestionDetour");
[
  "getQuestionAwareResumePrompt(",
  "getQuestionAwareResumeHtml(",
].forEach((needle) => {
  if (!openQuestionDetourBody.includes(needle)) {
    failures.push(`Question detour is missing ${needle}`);
  }
});

const botMessageBody = getFunctionBody("botMessage");
if (!botMessageBody.includes("registerPendingEmilyOutputTimer")) {
  failures.push("botMessage does not register scheduled output timers");
}
if (!botMessageBody.includes("generation !== emilyOutputGeneration")) {
  failures.push("botMessage does not drop stale generated output");
}
if (!botMessageBody.includes("applyChatUserTurnSerial !== turnSerial")) {
  failures.push("botMessage does not guard scheduled output by user turn");
}
if (!botMessageBody.includes("getConversationTurnOutputPacing(kind)")) {
  failures.push("botMessage does not apply turn-level output pacing");
}

const scheduleLiveDraftFrameBody = getFunctionBody("scheduleLiveDraftFrame");
if (!scheduleLiveDraftFrameBody.includes("expectedRunToken")) {
  failures.push("live tailoring frames are not guarded by run token");
}
if (!scheduleLiveDraftFrameBody.includes("expectedRunToken !== liveTailoringRunToken")) {
  failures.push("stale live tailoring frames are not dropped");
}

const runLiveTailoringSequenceBody = getFunctionBody("runLiveTailoringSequence");
if (!runLiveTailoringSequenceBody.includes("finishTailoringAfter")) {
  failures.push("live tailoring sequence has no completion callback path");
}

const continueApplyAfterAnalysisBody = getFunctionBody("continueApplyAfterAnalysis");
if (/},\s*9000\)/.test(continueApplyAfterAnalysisBody)) {
  failures.push("CV tailoring ready prompt still uses fixed 9 second timeout");
}
if (!continueApplyAfterAnalysisBody.includes("applyTailoringSequenceComplete = true")) {
  failures.push("CV tailoring flow does not mark sequence completion");
}
if (!continueApplyAfterAnalysisBody.includes("!applyTailoringSequenceComplete")) {
  failures.push("CV tailoring proceed prompt is not gated by sequence completion");
}
[
  ["old first-fix phrasing", /first fix/i],
  ["old tailoring progress headline", /Transforming wording, keywords, and role evidence/],
  ["old tailoring step label", /Fixing your header|Adding key skills/],
  ["old cover-letter filler", /sort the profile out/i],
  ["old current-CV risk warning", /weaker points treated as known risk/i],
  ["old pre-decision evidence filler", /One more pass on the evidence/i],
].forEach(([label, pattern]) => {
  if (pattern.test(source)) {
    failures.push(`CV tailoring copy still contains ${label}`);
  }
});
if (!source.includes("Building the tailored application version")) {
  failures.push("CV tailoring preview is missing the structured progress headline");
}
if (!source.includes("Checking evidence already in your CV")) {
  failures.push("CV tailoring preview is missing evidence-safety copy");
}
const showAnalysisBody = getFunctionBody("showAnalysis");
const introMessagesStart = showAnalysisBody.indexOf("var introMessages = [");
const introMessagesEnd = showAnalysisBody.indexOf("].filter(Boolean)", introMessagesStart);
if (introMessagesStart === -1 || introMessagesEnd === -1) {
  failures.push("Could not inspect showAnalysis intro message sequence");
} else {
  const introMessagesBody = showAnalysisBody.slice(introMessagesStart, introMessagesEnd);
  const visibleMessageBlocks = countMatches(/\{\s*html:/g, introMessagesBody);
  if (visibleMessageBlocks > 4) {
    failures.push(
      `CV analysis sequence emits too many pre-decision messages (${visibleMessageBlocks})`
    );
  }
}

const highRiskFunctions = [
  "startConsultantFlow",
  "promptForCv",
  "continueJobSearchAfterCvUpload",
  "maybeHandlePromptReply",
  "processCommercialApplyQueueItem",
  "processCommercialApplyQueueShortlist",
  "requestHumanFollowUpWithProgress",
  "requestHumanFollowUp",
  "requestRealPersonJoin",
];

const functionMetrics = highRiskFunctions.map((name) => {
  const body = getFunctionBody(name);
  if (!body) failures.push(`Missing high-risk function: ${name}`);
  return {
    name,
    botMessageCalls: countMatches(/\bbotMessage\s*\(/g, body),
    botSequenceCalls: countMatches(/\bbotSequence(?:ForCurrentTurn)?\s*\(/g, body),
    rawSetTimeoutCalls: countMatches(/\bwindow\.setTimeout\s*\(/g, body),
    currentTurnTimerCalls: countMatches(/\bscheduleForCurrentUserTurn\s*\(/g, body),
    promptStateWrites: countMatches(/\bsetPromptState\s*\(/g, body),
    clearPromptStateCalls: countMatches(/\bclearPromptState\s*\(/g, body),
  };
});

[
  "beginPremiumApplyIntroSelectedRoleFlow",
  "showApplyIntroPostAnalysisDecision",
  "askApplyIntroMembershipHandoff",
  "presentLiveJobSearchResults",
  "showLoggedInMemberSearchResults",
  "showProfileReviewAnalysis",
  "showRecruiterOutreachReviewAnalysis",
].forEach((name) => {
  const body = getFunctionBody(name);
  if (!body) {
    failures.push(`Missing flow-compression function: ${name}`);
    return;
  }
  const sequenceCount = countMatches(/\bbotSequenceForCurrentTurn\s*\(/g, body);
  if (sequenceCount > 0) {
    failures.push(
      `Flow compression regression: ${name} still uses botSequenceForCurrentTurn (${sequenceCount})`
    );
  }
});

const duplicateLiteralCounts = new Map();
const literalPattern = /(["'`])((?:(?!\1)[^\\]|\\.){35,})\1/g;
let literalMatch;
while ((literalMatch = literalPattern.exec(source))) {
  const text = literalMatch[2]
    .replace(/\\n/g, " ")
    .replace(/\s+/g, " ")
    .trim();
  const wordTokens = text.match(/[a-z][a-z']+/gi) || [];
  if (wordTokens.length < 5 || /[{};<>\[\]=]/.test(text)) continue;
  duplicateLiteralCounts.set(text, (duplicateLiteralCounts.get(text) || 0) + 1);
}
const duplicateLiterals = Array.from(duplicateLiteralCounts.entries())
  .filter(([, count]) => count > 1)
  .sort((a, b) => b[1] - a[1])
  .slice(0, 40)
  .map(([text, count]) => ({ count, text }));

[
  {
    label: "old language gate greeting",
    pattern: /Hi, I'm Emily, your job search assistant\. Would you prefer English or Arabic\?/,
  },
  {
    label: "legacy Emily transfer copy",
    pattern: /Transferring to Emily/,
  },
  {
    label: "legacy recruiter shortlist copy",
    pattern: /Here are recruiters hiring for your profile|Recruiters Hiring for Your Profile/,
  },
  {
    label: "managed service quick route CTA",
    pattern: /Yes, sign me up to the service|No, apply for this role only/,
  },
  {
    label: "broken repeated tailoring progress",
    pattern: /I(?:'|’)m tailoring the CV for this the employer application now/,
  },
].forEach((check) => {
  if (check.pattern.test(source)) {
    failures.push(`Conversation regression still present: ${check.label}`);
  }
});

[
  "renderWelcomeTrendingPillsHtml",
  "submitWelcomePillPrompt",
  "data-sffc-apply-chat-welcome-prompt",
  "data-sffc-apply-chat-language-label",
  "syncApplyChatCanonicalState",
  "claimConversationTurn",
].forEach((needle) => {
  if (!source.includes(needle)) {
    failures.push(`Missing unified conversation guard: ${needle}`);
  }
});

const welcomeBody = getFunctionBody("getEmilyGuestWelcomeMessageItem");
if (!welcomeBody.includes("renderWelcomeTrendingPillsHtml")) {
  failures.push("Welcome renderer does not attach trending pills");
}
if (/showLanguageChoiceButtons|showLanguageGate|is-clarify/.test(welcomeBody)) {
  failures.push("Welcome renderer can still use the old language gate choices");
}

const welcomeSubmitBody = getFunctionBody("submitWelcomePillPrompt");
if (!/composer\.dispatchEvent/.test(welcomeSubmitBody)) {
  failures.push("Welcome pill clicks must route through the composer submit event");
}
if (/startConsultantFlow|addTransferNotice/.test(welcomeSubmitBody)) {
  failures.push("Welcome pill clicks still route through legacy consultant/transfer flow");
}

const liveTailoringBody = getFunctionBody("runLiveTailoringSequence");
const tailoringProgressCopies = countMatches(
  /tailoring the CV for this(?: the)? employer application now/gi,
  liveTailoringBody
);
if (tailoringProgressCopies > 1) {
  failures.push(
    `Repeated tailoring progress copy still appears in live tailoring (${tailoringProgressCopies})`
  );
}

const cssPath = path.join(
  repoRoot,
  "assets/css/crm/crm-apply-chat-article.css"
);
const cssSource = fs.existsSync(cssPath) ? fs.readFileSync(cssPath, "utf8") : "";
[
  "/* Phase 6 layout contract: one content axis, one composer, no legacy overlays. */",
  "max-width: 1000px !important",
  "scrollbar-width: none !important",
  "content: none !important",
  "pointer-events: auto !important",
  "data-sffc-apply-chat-language-label]::before",
  "content: \"Languages\"",
].forEach((needle) => {
  if (!cssSource.includes(needle)) {
    failures.push(`Layout smoke CSS missing: ${needle}`);
  }
});

let fixtureSummary = null;
if (!fs.existsSync(fixturesPath)) {
  failures.push("Missing apply-chat conversation fixture file");
} else {
  const parsed = JSON.parse(fs.readFileSync(fixturesPath, "utf8"));
  const scenarios = Array.isArray(parsed.scenarios) ? parsed.scenarios : [];
  fixtureSummary = {
    scenarios: scenarios.length,
    turns: scenarios.reduce(
      (total, scenario) =>
        total + (Array.isArray(scenario.turns) ? scenario.turns.length : 0),
      0
    ),
    phase1Scenarios: scenarios.filter((scenario) =>
      /^phase1_/i.test(String(scenario.id || ""))
    ).length,
  };
  if (!fixtureSummary.phase1Scenarios) {
    failures.push("Missing Phase 1 conversation fixtures");
  }
}

let liveSuiteSummary = null;
if (!fs.existsSync(liveSuitesPath)) {
  failures.push("Missing apply-chat live stress suite file");
} else {
  const parsed = JSON.parse(fs.readFileSync(liveSuitesPath, "utf8"));
  const suites = parsed && parsed.suites && typeof parsed.suites === "object"
    ? parsed.suites
    : {};
  liveSuiteSummary = {
    suites: Object.keys(suites).length,
    phase1Suites: Object.keys(suites).filter((key) => /^phase1_/i.test(key))
      .length,
  };
  if (!liveSuiteSummary.phase1Suites) {
    failures.push("Missing Phase 1 live stress suite");
  }
}

const report = {
  generatedAt: new Date().toISOString(),
  sourcePath: path.relative(repoRoot, sourcePath),
  sourceLines: source.split(/\r?\n/).length,
  totalBotMessageCalls: countMatches(/\bbotMessage\s*\(/g),
  totalBotSequenceCalls: countMatches(/\bbotSequence(?:ForCurrentTurn)?\s*\(/g),
  totalRawSetTimeoutCalls: countMatches(/\bwindow\.setTimeout\s*\(/g),
  totalCurrentTurnTimerCalls: countMatches(/\bscheduleForCurrentUserTurn\s*\(/g),
  totalPromptStateWrites: countMatches(/\bsetPromptState\s*\(/g),
  totalPromptStateClears: countMatches(/\bclearPromptState\s*\(/g),
  highRiskFunctions: functionMetrics,
  duplicateLiterals,
  fixtures: fixtureSummary,
  liveSuites: liveSuiteSummary,
};

fs.mkdirSync(path.dirname(reportPath), { recursive: true });
fs.writeFileSync(reportPath, `${JSON.stringify(report, null, 2)}\n`);

if (failures.length) {
  console.error("FAIL apply-chat conversation hygiene");
  failures.forEach((failure) => console.error(`- ${failure}`));
  process.exit(1);
}

console.log(
  `PASS apply-chat conversation hygiene (${report.sourceLines} source lines, ${report.totalBotMessageCalls} botMessage calls, ${report.totalRawSetTimeoutCalls} raw timers)`
);
console.log(`Report: ${path.relative(repoRoot, reportPath)}`);
