"use strict";

const assert = require("assert");
const { buildMeaning, serviceInfo } = require("./meaning-engine");

const fixtures = [
  {
    message: "i need help to find jobs in dubai",
    intent: "job_search",
    type: "jobs_database_search",
    jobQueryIncludes: "Dubai",
  },
  {
    message: "best recruiters in dubai",
    intent: "web_research",
    type: "web_answer",
    webQueryIncludes: "recruitment agencies",
  },
  {
    message: "when is the best time to apply for jobs in Dubai",
    intent: "web_research",
    type: "web_answer",
    webQueryIncludes: "best time",
  },
  {
    message: "you know emily im so tired of my job search what do you suggest",
    intent: "career_advice",
    type: "career_advice_with_web_support",
    webQueryIncludes: "job search",
  },
  {
    message: "what is saudi arabia like",
    intent: "web_research",
    type: "web_answer",
    webQueryIncludes: "Saudi Arabia",
  },
];

console.log(serviceInfo());

fixtures.forEach((fixture) => {
  const meaning = buildMeaning({
    message: fixture.message,
    context: { selectedRole: "Credit Analyst at Merak Capital", hasCv: true },
  });
  assert.strictEqual(
    meaning.primaryIntent,
    fixture.intent,
    `${fixture.message} primaryIntent ${meaning.primaryIntent}`
  );
  assert.strictEqual(meaning.action.type, fixture.type, `${fixture.message} action ${meaning.action.type}`);
  if (fixture.jobQueryIncludes) {
    assert(
      meaning.rewrittenQueries.jobs?.query.includes(fixture.jobQueryIncludes),
      `${fixture.message} jobs query ${JSON.stringify(meaning.rewrittenQueries.jobs)}`
    );
  }
  if (fixture.webQueryIncludes) {
    const web = meaning.rewrittenQueries.web || "";
    assert(
      web.toLowerCase().includes(fixture.webQueryIncludes.toLowerCase()),
      `${fixture.message} web query ${web}`
    );
  }
});

console.log(`PASS ${fixtures.length} Emily NLP service fixtures`);
