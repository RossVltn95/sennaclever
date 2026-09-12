#!/usr/bin/env node

const fs = require("fs");
const path = require("path");

const outputPath =
  process.argv[2] ||
  path.join(__dirname, "..", "tmp", "emily-outcome-events-schema.json");

const schema = {
  version: 1,
  source: "apply_chat_browser_outcome_log",
  browserGlobals: [
    "__sffcEmilyOutcomeEventLog",
    "__sffcEmilyOutcomeEventLatest",
  ],
  eventTypes: {
    decision: {
      label: 0,
      description: "Emily selected a route or next action for a user message.",
    },
    job_impression: {
      label: 0,
      description: "A job result was shown to the user.",
    },
    job_review_fit: {
      label: 2,
      description: "The user opened or closed the fit-review panel.",
    },
    job_save: {
      label: 2,
      description: "The user saved or shortlisted a job.",
    },
    job_tailor_cv: {
      label: 3,
      description: "The user chose to tailor a CV for a job.",
    },
    job_original_cv: {
      label: 2,
      description: "The user chose to continue with the original CV.",
    },
    job_apply: {
      label: 4,
      description: "The user attempted to apply or queue a worker application.",
    },
    job_dismiss: {
      label: -1,
      description: "The user removed or dismissed a job.",
    },
    user_feedback: {
      label: 1,
      description: "The user corrected or refined Emily's search preferences.",
    },
  },
  fields: [
    "schemaVersion",
    "seq",
    "type",
    "createdAt",
    "conversationId",
    "userTier",
    "workflowState",
    "message",
    "decision.chosenRoute",
    "decision.intentType",
    "decision.actionType",
    "decision.confidence",
    "decision.confidenceBand",
    "decision.topRoutes",
    "role.id",
    "role.title",
    "role.company",
    "role.location",
    "role.provider",
    "role.score",
    "role.fitScore",
    "role.fitBand",
    "role.reasonCodes",
    "role.risks",
    "role.rankingScore",
    "role.rankingModel",
    "rank",
    "source",
    "outcome",
  ],
  rows: [],
};

fs.mkdirSync(path.dirname(outputPath), { recursive: true });
fs.writeFileSync(outputPath, JSON.stringify(schema, null, 2));
console.log(`Wrote Emily outcome event schema to ${outputPath}`);
