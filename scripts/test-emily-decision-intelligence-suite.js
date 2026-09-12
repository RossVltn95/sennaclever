#!/usr/bin/env node

const childProcess = require("child_process");
const fs = require("fs");
const path = require("path");

const root = path.resolve(__dirname, "..");
const outputPath =
  process.argv[2] ||
  path.join(root, "reports", "emily-decision-intelligence-suite.json");

const checks = [
  {
    id: "syntax_apply_chat",
    command: [process.execPath, "--check", "assets/js/crm/crm-apply-chat-article.js"],
  },
  {
    id: "route_taxonomy",
    command: [process.execPath, "scripts/test-emily-decision-route-taxonomy.js"],
  },
  {
    id: "decision_features",
    command: [process.execPath, "scripts/test-emily-decision-features.js"],
  },
  {
    id: "route_probability_classifier",
    command: [process.execPath, "scripts/test-emily-route-probability-classifier.js"],
  },
  {
    id: "semantic_router",
    command: [process.execPath, "scripts/test-emily-route-semantic-router.js"],
  },
  {
    id: "route_ensemble",
    command: [process.execPath, "scripts/test-emily-route-ensemble-scorer.js"],
  },
  {
    id: "route_calibration",
    command: [process.execPath, "scripts/test-emily-route-calibration.js"],
  },
  {
    id: "job_description_signals",
    command: [process.execPath, "scripts/test-job-description-signals.js"],
  },
  {
    id: "candidate_profile_signals",
    command: [process.execPath, "scripts/test-candidate-profile-signals.js"],
  },
  {
    id: "structured_job_fit",
    command: [process.execPath, "scripts/test-structured-job-fit-score.js"],
  },
  {
    id: "learning_to_rank",
    command: [process.execPath, "scripts/test-emily-learning-to-rank.js"],
  },
  {
    id: "outcome_logging",
    command: [process.execPath, "scripts/test-emily-outcome-logging.js"],
  },
  {
    id: "memory_continuity",
    command: [process.execPath, "scripts/test-emily-memory-continuity.js"],
  },
  {
    id: "cleanlab_data_quality",
    command: [process.execPath, "scripts/test-emily-cleanlab-data-quality.js"],
  },
  {
    id: "runtime_safety",
    command: [process.execPath, "scripts/test-emily-runtime-safety.js"],
  },
  {
    id: "golden_decision_engine",
    command: [process.execPath, "scripts/test-apply-chat-decision-engine.js"],
  },
];

function runCheck(check) {
  const started = Date.now();
  try {
    const output = childProcess.execFileSync(check.command[0], check.command.slice(1), {
      cwd: root,
      encoding: "utf8",
      stdio: ["ignore", "pipe", "pipe"],
    });
    return {
      id: check.id,
      ok: true,
      durationMs: Date.now() - started,
      output: output.trim(),
    };
  } catch (error) {
    return {
      id: check.id,
      ok: false,
      durationMs: Date.now() - started,
      output: String((error && error.stdout) || "").trim(),
      error: String((error && error.stderr) || error.message || "").trim(),
    };
  }
}

function runCalibrationReport() {
  try {
    const output = childProcess.execFileSync(
      process.execPath,
      ["scripts/evaluate-emily-route-calibration.js"],
      {
        cwd: root,
        encoding: "utf8",
        stdio: ["ignore", "pipe", "pipe"],
      }
    );
    return JSON.parse(output);
  } catch (error) {
    return {
      error: String((error && error.message) || error || ""),
    };
  }
}

const results = checks.map(runCheck);
const calibration = runCalibrationReport();
const failed = results.filter((result) => !result.ok);
const report = {
  version: 1,
  generatedAt: new Date().toISOString(),
  status: failed.length ? "failed" : "passed",
  summary: {
    total: results.length,
    passed: results.length - failed.length,
    failed: failed.length,
    calibrationAccuracy: calibration && calibration.accuracy,
    calibrationBrierScore: calibration && calibration.brierScore,
    calibrationLogLoss: calibration && calibration.logLoss,
  },
  goldenExamplesCovered: [
    "best recruitment agencies in Dubai -> web_search",
    "show me HR manager jobs in Dubai -> job_search",
    "apply to this role with selected role -> apply_action",
    "am I a fit for this job? -> cv_role_comparison",
    "these are too junior -> search_refinement",
    "only show me Dubai -> search_refinement",
    "what did I apply to -> application_status",
    "who should I contact at this company -> recruiter/company task",
  ],
  checks: results,
  calibration,
};

fs.mkdirSync(path.dirname(outputPath), { recursive: true });
fs.writeFileSync(outputPath, JSON.stringify(report, null, 2));

if (failed.length) {
  console.error(
    `Emily decision intelligence suite failed ${failed.length}/${results.length}; report: ${outputPath}`
  );
  failed.forEach((failure) => {
    console.error(`- ${failure.id}: ${failure.error || failure.output}`);
  });
  process.exit(1);
}

console.log(
  `Emily decision intelligence suite passed ${results.length}/${results.length}; report: ${outputPath}`
);
