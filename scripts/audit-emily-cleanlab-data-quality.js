#!/usr/bin/env node

const fs = require("fs");
const path = require("path");

function parseArgs(argv) {
  const args = { input: "", output: "", markdown: "" };
  for (let index = 2; index < argv.length; index += 1) {
    const value = argv[index];
    if (value === "--out") {
      args.output = argv[++index] || "";
    } else if (value === "--markdown") {
      args.markdown = argv[++index] || "";
    } else if (!args.input) {
      args.input = value;
    }
  }
  return args;
}

function clean(value) {
  return String(value || "").replace(/\s+/g, " ").trim();
}

function asNumber(value, fallback = 0) {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : fallback;
}

function readEvents(inputPath) {
  if (!inputPath) return [];
  const parsed = JSON.parse(fs.readFileSync(inputPath, "utf8"));
  if (Array.isArray(parsed)) return parsed;
  if (Array.isArray(parsed.events)) return parsed.events;
  if (Array.isArray(parsed.rows)) return parsed.rows;
  if (Array.isArray(parsed.outcomes)) return parsed.outcomes;
  return [];
}

function getEventLabel(event) {
  const labels = {
    decision: 0,
    job_impression: 0,
    user_feedback: 1,
    job_review_fit: 2,
    job_save: 2,
    job_original_cv: 2,
    job_tailor_cv: 3,
    job_apply: 4,
    job_dismiss: -1,
  };
  return Object.prototype.hasOwnProperty.call(labels, event.type)
    ? labels[event.type]
    : 0;
}

function getDecisionLabel(event) {
  const decision = event.decision || {};
  return clean(
    decision.chosenRoute ||
      decision.actionType ||
      decision.intentType ||
      event.route ||
      event.action ||
      ""
  );
}

function getDecisionProbabilities(event) {
  const routes =
    event &&
    event.decision &&
    Array.isArray(event.decision.topRoutes)
      ? event.decision.topRoutes
      : [];
  return routes.slice(0, 8).map((route) => ({
    label: clean(route.route || route.label || ""),
    probability: asNumber(route.calibratedProbability || route.score, 0),
  }));
}

function buildCleanlabRows(events) {
  return events.map((event, index) => {
    const label = event.type === "decision" ? getDecisionLabel(event) : event.type;
    return {
      id: clean(event.id || event.seq || index + 1),
      type: clean(event.type || "event"),
      text: clean(
        event.message ||
          (event.role && [event.role.title, event.role.company, event.role.location]
            .filter(Boolean)
            .join(" | ")) ||
          ""
      ),
      label,
      numericLabel: getEventLabel(event),
      predProbs: getDecisionProbabilities(event),
      source: clean(event.source || ""),
      outcome: clean(event.outcome || ""),
    };
  });
}

function addIssue(issues, event, index, code, severity, detail) {
  issues.push({
    id: clean(event.id || event.seq || index + 1),
    seq: event.seq || null,
    type: clean(event.type || "event"),
    code,
    severity,
    detail,
    message: clean(event.message || ""),
    role: event.role
      ? {
          title: clean(event.role.title || ""),
          company: clean(event.role.company || ""),
          fitScore: asNumber(event.role.fitScore, 0),
          rankingScore: asNumber(event.role.rankingScore, 0),
        }
      : null,
  });
}

function auditEvents(events) {
  const issues = [];
  events.forEach((event, index) => {
    const type = clean(event.type || "event");
    const message = clean(event.message || "").toLowerCase();
    if (type === "decision") {
      const decision = event.decision || {};
      const confidence = asNumber(decision.confidence, 0);
      const action = clean(decision.actionType || "");
      const chosenRoute = clean(decision.chosenRoute || "");
      const topRoutes = Array.isArray(decision.topRoutes)
        ? decision.topRoutes.slice(0, 3)
        : [];
      const first = asNumber(topRoutes[0] && (topRoutes[0].calibratedProbability || topRoutes[0].score), 0);
      const second = asNumber(topRoutes[1] && (topRoutes[1].calibratedProbability || topRoutes[1].score), 0);
      if (confidence > 0 && confidence < 0.45) {
        addIssue(
          issues,
          event,
          index,
          "low_confidence_decision",
          "medium",
          `Decision confidence was ${confidence.toFixed(2)}.`
        );
      }
      if (first && second && Math.abs(first - second) < 0.08) {
        addIssue(
          issues,
          event,
          index,
          "ambiguous_route_margin",
          "high",
          `Top route margin was ${(first - second).toFixed(2)}.`
        );
      }
      if (
        /\b(?:best|top|leading|list of|which)\b.*\b(?:agenc(?:y|ies)|firm|recruiter|headhunter|salary|trend)\b/.test(
          message
        ) &&
        action !== "web_search" &&
        chosenRoute !== "web_search"
      ) {
        addIssue(
          issues,
          event,
          index,
          "possible_web_search_mislabel",
          "high",
          "External/current-market query did not route to web search."
        );
      }
      if (
        /\b(?:show|find|search|look for|get me)\b.*\b(?:job|role|vacanc|position)s?\b/.test(
          message
        ) &&
        action !== "show_job_results" &&
        chosenRoute !== "job_search"
      ) {
        addIssue(
          issues,
          event,
          index,
          "possible_job_search_mislabel",
          "high",
          "Internal job-search query did not route to job results."
        );
      }
      if (event.validation && event.validation.valid === false) {
        addIssue(
          issues,
          event,
          index,
          "invalid_decision_validation",
          "high",
          clean(event.validation.reason || "Decision validation failed.")
        );
      }
    }
    if (/^job_/.test(type)) {
      const role = event.role || {};
      const fitScore = asNumber(role.fitScore, 0);
      if (/^(job_save|job_tailor_cv|job_original_cv|job_apply)$/.test(type) && fitScore > 0 && fitScore < 35) {
        addIssue(
          issues,
          event,
          index,
          "positive_engagement_low_fit",
          "medium",
          `User engaged with a low-fit job (${fitScore}).`
        );
      }
      if (type === "job_dismiss" && fitScore >= 70) {
        addIssue(
          issues,
          event,
          index,
          "dismissed_high_fit_job",
          "high",
          `User dismissed a high-fit job (${fitScore}).`
        );
      }
      if (
        type === "job_impression" &&
        (!clean(role.title || "") || !clean(role.company || ""))
      ) {
        addIssue(
          issues,
          event,
          index,
          "incomplete_job_impression",
          "medium",
          "Shown job result was missing title or company."
        );
      }
    }
  });
  return issues;
}

function summarize(events, issues) {
  const byType = {};
  const byIssue = {};
  events.forEach((event) => {
    const type = clean(event.type || "event");
    byType[type] = (byType[type] || 0) + 1;
  });
  issues.forEach((issue) => {
    byIssue[issue.code] = (byIssue[issue.code] || 0) + 1;
  });
  return {
    eventCount: events.length,
    issueCount: issues.length,
    byType,
    byIssue,
    status: events.length ? "audited" : "needs_data",
  };
}

function renderMarkdown(report) {
  const lines = [
    "# Emily Data Quality Audit",
    "",
    `Status: ${report.summary.status}`,
    `Events: ${report.summary.eventCount}`,
    `Issues: ${report.summary.issueCount}`,
    "",
    "## Issue Counts",
    "",
  ];
  Object.entries(report.summary.byIssue).forEach(([code, count]) => {
    lines.push(`- ${code}: ${count}`);
  });
  if (!Object.keys(report.summary.byIssue).length) {
    lines.push("- None");
  }
  lines.push("", "## Human Review Queue", "");
  report.reviewQueue.slice(0, 50).forEach((issue) => {
    lines.push(
      `- [${issue.severity}] ${issue.code}: ${issue.detail} (${issue.id})`
    );
  });
  if (!report.reviewQueue.length) {
    lines.push("- Empty");
  }
  lines.push("");
  return lines.join("\n");
}

function main() {
  const args = parseArgs(process.argv);
  const events = readEvents(args.input);
  const cleanlabRows = buildCleanlabRows(events);
  const issues = auditEvents(events);
  const reviewQueue = issues
    .slice()
    .sort((a, b) => {
      const weight = { high: 3, medium: 2, low: 1 };
      return (weight[b.severity] || 0) - (weight[a.severity] || 0);
    });
  const report = {
    version: 1,
    generatedAt: new Date().toISOString(),
    summary: summarize(events, issues),
    cleanlabRows,
    reviewQueue,
  };
  const outputPath =
    args.output ||
    path.join(__dirname, "..", "tmp", "emily-data-quality-audit.json");
  fs.mkdirSync(path.dirname(outputPath), { recursive: true });
  fs.writeFileSync(outputPath, JSON.stringify(report, null, 2));
  if (args.markdown) {
    fs.mkdirSync(path.dirname(args.markdown), { recursive: true });
    fs.writeFileSync(args.markdown, renderMarkdown(report));
  }
  console.log(
    `Emily data quality audit wrote ${events.length} events and ${issues.length} issue(s) to ${outputPath}`
  );
}

main();
