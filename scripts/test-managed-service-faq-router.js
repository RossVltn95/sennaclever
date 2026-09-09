#!/usr/bin/env node

const fs = require("fs");
const path = require("path");
const vm = require("vm");

const root = path.resolve(__dirname, "..");
const sourcePath = path.join(root, "assets/js/crm/crm-apply-chat-article.js");
const source = fs.readFileSync(sourcePath, "utf8");
const startMarker = "    function getManagedServiceFaqContext() {";
const endMarker = "    function continueManagedServiceAfterNameConfirmed() {";
const start = source.indexOf(startMarker);
const end = source.indexOf(endMarker, start);

if (start === -1 || end === -1) {
  throw new Error("Unable to locate managed service FAQ router block.");
}

const routerSource = source
  .slice(start, end)
  .replace(/^    /gm, "");

const sandbox = {
  console,
  managedServiceFaqLastTopics: [],
  roleTitle: "Private Equity Analyst",
  applyOnboardingPreferredEmail: "fabienne@example.com",
  applyOnboardingFullName: "Fabienne Zere",
  cleanMessageText(value) {
    return String(value || "").replace(/\s+/g, " ").trim();
  },
  getManagedServiceLocationLabel() {
    return "Dubai";
  },
};

vm.createContext(sandbox);
vm.runInContext(
  `${routerSource}
this.trace = traceManagedServiceFaqDecision;
this.answer = getManagedServiceFaqAnswer;`,
  sandbox
);

const cases = [
  {
    q: "Can you apply in Dubai but not contact my current employer, and what about visa sponsorship?",
    must: ["confidentiality", "visa_work_authorisation"],
    mustAnswer: ["I’m reading it as about", "I’d separate", "targeting dubai"],
    plan: { action: "set_boundary", hasConstraint: "current employer", hasLocation: "dubai" },
  },
  {
    q: "Can you log into my LinkedIn and message recruiters for me?",
    must: ["passwords", "outreach_channels"],
    mustAnswer: ["channel matters", "Do not send passwords"],
    plan: { action: "set_boundary", hasChannel: "linkedin" },
  },
  {
    q: "I already applied to this role, will you apply again?",
    must: ["duplicate_applications"],
  },
  {
    q: "The signup form is blank and my card payment failed",
    must: ["checkout_problem", "pricing_payment"],
  },
  {
    q: "Who do I contact if I need support?",
    must: ["support_contact"],
  },
  {
    q: "What's Emily's email?",
    must: ["emily_contact"],
  },
  {
    q: "How do you decide whether a role is suitable?",
    must: ["role_quality"],
  },
  {
    q: "Can I approve applications before they go out?",
    must: ["consent", "control_review"],
    mustAnswer: ["control question", "approval rule"],
    plan: { action: "set_boundary", hasConstraint: "approval first" },
  },
  {
    q: "What if Workday does not load in the embed?",
    must: ["portal_fallback"],
  },
  {
    q: "I want private equity in Riyadh but no internships",
    must: ["sector", "seniority"],
    forbiddenSelected: ["privacy_data", "confidentiality"],
  },
  {
    q: "Can I delete my CV and data later?",
    must: ["privacy_data"],
  },
  {
    q: "Will this guarantee interviews?",
    must: ["guarantee"],
  },
  {
    q: "How many applications do you send each week?",
    must: ["volume"],
  },
  {
    q: "What do I still need to do myself?",
    must: ["candidate_responsibility"],
  },
  {
    q: "Can I change the email on my account?",
    must: ["account_identity_changes"],
  },
  {
    q: "pls don't message anyone from where I work",
    must: ["confidentiality"],
  },
  {
    q: "Are you allowed to answer right to work questions for me?",
    must: ["visa_work_authorisation", "consent"],
  },
  {
    q: "Do you need my password or 2fa code for Workday?",
    must: ["passwords", "portal_fallback"],
  },
  {
    q: "Can I pause for two weeks and then restart?",
    must: ["cancel_refund"],
  },
  {
    q: "Will you send me updates when recruiters reply?",
    must: ["updates", "recruiter_replies"],
    mustAnswer: ["recruiter replies are not just status updates"],
  },
  {
    q: "Do you write cover letters or just submit the CV?",
    must: ["cover_letters", "tailored_applications"],
  },
  {
    q: "What happens if there are no good roles in Abu Dhabi?",
    must: ["no_roles", "location"],
  },
  {
    q: "I need discreet outreach because my boss cannot know",
    must: ["confidentiality"],
  },
  {
    q: "can you make my experience look stronger than it is",
    must: ["boundaries_truth"],
  },
  {
    q: "is my data safe and can i remove my cv",
    must: ["privacy_data"],
  },
  {
    q: "what about invoices and receipts",
    must: ["pricing_payment"],
  },
  {
    q: "same",
    must: [],
    expectClarify: true,
  },
  {
    q: "Can you apply without telling me first?",
    must: ["control_review", "consent"],
  },
  {
    q: "What plan is this and what does it cost?",
    must: ["included", "pricing_payment"],
  },
  {
    q: "Can you send recruiter DMs from my LinkedIn account?",
    must: ["outreach_channels", "recruiter_outreach"],
  },
  {
    q: "If they ask gender, disability or citizenship can you answer it?",
    must: ["consent", "visa_work_authorisation"],
  },
  {
    q: "Do you use AI to mass apply everywhere?",
    must: ["role_quality", "boundaries_truth"],
    mustAnswer: ["quality over volume", "I won’t lie"],
  },
  {
    q: "Can I get a refund if nothing happens?",
    must: ["cancel_refund", "no_roles"],
  },
  {
    q: "Do I need to complete psychometric tests myself?",
    must: ["assessments_tests", "candidate_responsibility"],
    mustAnswer: ["what I can prepare and what only you can complete", "complete it yourself"],
    plan: { action: "set_boundary" },
  },
  {
    q: "Is this handled by a real person or automated bot?",
    must: ["human_vs_automation"],
    mustAnswer: ["judgement layer matters", "blind automation"],
  },
  {
    q: "How often will you update me?",
    must: ["communication_cadence", "updates"],
    mustAnswer: ["meaningful movement", "applications sent"],
  },
  {
    q: "Who gets my details after payment?",
    must: ["admin_handoff", "admin_team"],
    mustAnswer: ["enough context to continue from this chat", "MENA Careers team"],
  },
  {
    q: "Can I choose target companies, hybrid roles and salary range?",
    must: ["search_preferences"],
    mustAnswer: ["Search preferences matter", "target companies"],
  },
  {
    q: "Is it monthly or annual and can I cancel?",
    must: ["subscription_terms", "cancel_refund"],
    mustAnswer: ["search instruction from the billing/account action", "support.team@joinsenna.com"],
  },
  {
    q: "If they ask about background checks or disciplinary issues can you answer?",
    must: ["legal_regulated_answers", "consent"],
    mustAnswer: ["consent and accuracy issue", "won’t infer or guess"],
    plan: { action: "set_boundary" },
  },
];

let failures = 0;
for (const testCase of cases) {
  sandbox.managedServiceFaqLastTopics = [];
  const trace = sandbox.trace(testCase.q);
  const ids = trace.intents.map((intent) => intent.id);
  const selected = trace.selected.map((intent) => intent.id);
  const missing = testCase.must.filter((intent) => !ids.includes(intent));
  const missingSelected = testCase.must.filter((intent) => !selected.includes(intent));
  const forbiddenSelected = (testCase.forbiddenSelected || []).filter((intent) =>
    selected.includes(intent)
  );
  const answer = sandbox.answer(testCase.q);
  const clarified = /I want to answer that properly/.test(answer);
  const missingAnswer = (testCase.mustAnswer || []).filter((text) => !answer.includes(text));
  const plan = trace.plan || {};
  const planFailures = [];
  if (testCase.plan) {
    if (testCase.plan.action && plan.action !== testCase.plan.action) {
      planFailures.push(`action:${plan.action}`);
    }
    if (
      testCase.plan.hasConstraint &&
      (!plan.entities || !plan.entities.constraints.includes(testCase.plan.hasConstraint))
    ) {
      planFailures.push(`constraint:${testCase.plan.hasConstraint}`);
    }
    if (
      testCase.plan.hasLocation &&
      (!plan.entities || !plan.entities.locations.includes(testCase.plan.hasLocation))
    ) {
      planFailures.push(`location:${testCase.plan.hasLocation}`);
    }
    if (
      testCase.plan.hasChannel &&
      (!plan.entities || !plan.entities.channels.includes(testCase.plan.hasChannel))
    ) {
      planFailures.push(`channel:${testCase.plan.hasChannel}`);
    }
  }
  const failed =
    missing.length ||
    missingSelected.length ||
    forbiddenSelected.length ||
    missingAnswer.length ||
    planFailures.length ||
    (testCase.expectClarify && !clarified);
  const line = `${failed ? "FAIL" : "PASS"} ${testCase.q}\n  selected=${selected.join(
    ", "
  )}\n  top=${ids.slice(0, 4).join(", ")} confidence=${JSON.stringify(trace.confidence)}`;
  console.log(line);
  if (failed) {
    console.log(`  missing=${missing.join(", ")}`);
    console.log(`  missingSelected=${missingSelected.join(", ")}`);
    console.log(`  forbiddenSelected=${forbiddenSelected.join(", ")}`);
    console.log(`  missingAnswer=${missingAnswer.join(" | ")}`);
    console.log(`  planFailures=${planFailures.join(" | ")}`);
    console.log(`  clarified=${clarified}`);
    console.log(`  answer=${answer}`);
    failures += 1;
  }
}

process.exitCode = failures ? 1 : 0;
