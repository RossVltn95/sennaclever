"use strict";

const assert = require("assert");
const { buildMeaning, serviceInfo } = require("./meaning-engine");

const DEFAULT_CONTEXT = {
  selectedRole: { title: "Credit Analyst", company: "Merak Capital", location: "Riyadh" },
  activeTask: { type: "application", promptState: "" },
  hasCv: true,
  cvProfile: {
    title: "Investment Analyst",
    families: ["investment"],
    skills: ["financial modelling", "valuation", "due diligence"],
    locations: ["Dubai"],
  },
};

const FIXTURES = [
  {
    category: "direct_job_search",
    message: "find investment analyst jobs in Dubai",
    intent: "job_search",
    type: "jobs_database_search",
    jobQueryIncludes: ["investment analyst", "Dubai"],
  },
  {
    category: "direct_job_search",
    message: "show me HR manager roles in Riyadh",
    context: {
      hasCv: true,
      cvProfile: {
        title: "HR Recruitment Manager",
        families: ["human_resources"],
        skills: ["recruitment", "talent acquisition", "payroll"],
      },
    },
    intent: "job_search",
    type: "jobs_database_search",
    jobQueryIncludes: ["hr manager", "Riyadh"],
  },
  {
    category: "negative_constraints",
    message: "finance jobs in Dubai but not banking",
    intent: "job_search",
    type: "jobs_database_search",
    jobQueryIncludes: ["finance", "Dubai"],
    jobQueryExcludes: ["banking"],
    constraints: {
      excludeSectors: ["banking"],
    },
  },
  {
    category: "negative_constraints",
    message: "investment roles in Riyadh no junior roles",
    intent: "job_search",
    type: "jobs_database_search",
    jobQueryIncludes: ["investment", "Riyadh"],
    jobQueryExcludes: ["junior"],
    constraints: {
      excludeSeniority: ["junior"],
    },
  },
  {
    category: "indirect_job_search",
    message: "i need help to find jobs in dubai",
    intent: "job_search",
    type: "jobs_database_search",
    jobQueryIncludes: ["Dubai"],
  },
  {
    category: "indirect_job_search",
    message: "what about Riyadh?",
    context: {
      hasCv: true,
      activeTask: { type: "search" },
      jobSearchContext: { query: "investment analyst", location: "Dubai" },
      searchPreferences: { currentQuery: "investment analyst", preferredLocation: "Dubai" },
    },
    intent: "job_search",
    type: "jobs_database_search",
    jobQueryIncludes: ["investment analyst", "Riyadh"],
  },
  {
    category: "career_frustration",
    message: "you know emily im so tired of my job search what do you suggest",
    intent: "career_advice",
    type: "career_advice_with_web_support",
    webQueryIncludes: ["job search"],
  },
  {
    category: "career_frustration",
    message: "i keep applying and getting no replies",
    context: {
      hasCv: true,
      cvProfile: {
        title: "HR Recruitment Manager",
        families: ["human_resources"],
        skills: ["recruitment", "talent acquisition", "payroll"],
      },
    },
    intent: "career_advice",
    type: "career_advice_with_web_support",
    webQueryIncludes: ["HR Recruitment Manager"],
  },
  {
    category: "recruiter_questions",
    message: "best recruiters in dubai",
    intent: "web_research",
    type: "web_answer",
    webQueryIncludes: ["recruitment agencies", "Dubai"],
  },
  {
    category: "recruiter_questions",
    message: "should i use recruiters or apply directly in Saudi",
    intent: "web_research",
    type: "web_answer",
    webQueryIncludes: ["recruitment agencies", "Saudi Arabia"],
  },
  {
    category: "salary_questions",
    message: "what salary should i expect for HR manager in Dubai",
    intent: "web_research",
    type: "web_answer",
    webQueryIncludes: ["salary guide", "hr manager", "Dubai"],
  },
  {
    category: "salary_questions",
    message: "average compensation for investment analyst in Riyadh",
    intent: "web_research",
    type: "web_answer",
    webQueryIncludes: ["salary guide", "investment analyst", "Riyadh"],
  },
  {
    category: "country_life_questions",
    message: "what is Saudi Arabia like",
    intent: "web_research",
    type: "web_answer",
    webQueryIncludes: ["Saudi Arabia"],
  },
  {
    category: "country_life_questions",
    message: "is Dubai a good place to work in finance",
    intent: "web_research",
    type: "web_answer",
    webQueryIncludes: ["Dubai"],
  },
  {
    category: "selected_role_interruptions",
    message: "what is Saudi Arabia like",
    context: DEFAULT_CONTEXT,
    intent: "web_research",
    type: "web_answer",
    webQueryIncludes: ["Saudi Arabia"],
    notType: "clarify",
  },
  {
    category: "selected_role_interruptions",
    message: "when is the best time to apply for jobs in Dubai",
    context: DEFAULT_CONTEXT,
    intent: "web_research",
    type: "web_answer",
    webQueryIncludes: ["best time", "Dubai"],
    notType: "clarify",
  },
  {
    category: "selected_role_interruptions",
    message: "we are talking through a career question",
    context: {
      selectedRole: { title: "Credit Analyst", company: "Merak Capital", location: "Riyadh" },
      activeTask: { type: "application", promptState: "conversation_route_clarify" },
      hasCv: true,
    },
    intent: "career_advice",
    type: "career_context_confirmation",
    notType: "clarify",
  },
  {
    category: "multilingual_typo_cases",
    message: "i need help to fund a job in dubai",
    intent: "job_search",
    type: "jobs_database_search",
    jobQueryIncludes: ["Dubai"],
  },
  {
    category: "multilingual_typo_cases",
    message: "abgha jobs in riyadh",
    intent: "job_search",
    type: "jobs_database_search",
    jobQueryIncludes: ["Riyadh"],
  },
  {
    category: "multilingual_typo_cases",
    message: "best recruiter in saudia",
    intent: "web_research",
    type: "web_answer",
    webQueryIncludes: ["recruitment agencies", "Saudi Arabia"],
  },
];

function includesAll(value, expected) {
  const clean = String(value || "").toLowerCase();
  return expected.every((item) => clean.includes(String(item || "").toLowerCase()));
}

function failDetails(fixture, meaning) {
  return [
    `[${fixture.category}] ${fixture.message}`,
    `intent=${meaning.primaryIntent}`,
    `action=${meaning.action && meaning.action.type}`,
    `jobs=${JSON.stringify(meaning.rewrittenQueries && meaning.rewrittenQueries.jobs)}`,
    `web=${JSON.stringify(meaning.rewrittenQueries && meaning.rewrittenQueries.web)}`,
    `explanation=${JSON.stringify(meaning.explanation || [])}`,
  ].join(" | ");
}

function assertFixture(fixture) {
  const meaning = buildMeaning({
    message: fixture.message,
    context: fixture.context || DEFAULT_CONTEXT,
  });
  assert.strictEqual(meaning.primaryIntent, fixture.intent, failDetails(fixture, meaning));
  assert.strictEqual(meaning.action.type, fixture.type, failDetails(fixture, meaning));
  if (fixture.notType) {
    assert.notStrictEqual(meaning.action.type, fixture.notType, failDetails(fixture, meaning));
  }
  if (fixture.jobQueryIncludes) {
    assert(
      includesAll(meaning.rewrittenQueries.jobs && meaning.rewrittenQueries.jobs.query, fixture.jobQueryIncludes),
      failDetails(fixture, meaning)
    );
  }
  if (fixture.jobQueryExcludes) {
    const jobQuery = meaning.rewrittenQueries.jobs && meaning.rewrittenQueries.jobs.query;
    assert(
      fixture.jobQueryExcludes.every((item) => !String(jobQuery || "").toLowerCase().includes(String(item).toLowerCase())),
      failDetails(fixture, meaning)
    );
  }
  if (fixture.constraints) {
    Object.entries(fixture.constraints).forEach(([key, expectedValues]) => {
      const actual =
        (meaning.rewrittenQueries.constraints && meaning.rewrittenQueries.constraints[key]) ||
        (meaning.rewrittenQueries.jobs &&
          meaning.rewrittenQueries.jobs.constraints &&
          meaning.rewrittenQueries.jobs.constraints[key]) ||
        [];
      assert(includesAll(actual.join(" "), expectedValues), failDetails(fixture, meaning));
    });
  }
  if (fixture.webQueryIncludes) {
    assert(
      includesAll(meaning.rewrittenQueries.web, fixture.webQueryIncludes),
      failDetails(fixture, meaning)
    );
  }
  return meaning;
}

console.log(serviceInfo());

const categories = new Map();
FIXTURES.forEach((fixture) => {
  assertFixture(fixture);
  categories.set(fixture.category, (categories.get(fixture.category) || 0) + 1);
});

console.log(
  `PASS ${FIXTURES.length} Emily NLP service fixtures across ${categories.size} categories`
);
Array.from(categories.entries()).forEach(([category, count]) => {
  console.log(` - ${category}: ${count}`);
});
