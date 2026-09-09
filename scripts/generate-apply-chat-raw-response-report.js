#!/usr/bin/env node
"use strict";

const fs = require("fs");
const path = require("path");

const repoRoot = path.resolve(__dirname, "..");
const sourcePath = path.join(repoRoot, "assets/js/crm/crm-apply-chat-article.js");
const outputPath = path.join(
  repoRoot,
  "reports/sffc-crm-apply-chat-raw-response-catalogue.html"
);

const source = fs.readFileSync(sourcePath, "utf8");
const lines = source.split(/\n/);
const lineStarts = [];
let offset = 0;
for (const line of lines) {
  lineStarts.push(offset);
  offset += line.length + 1;
}

function escapeHtml(value) {
  return String(value || "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}

function getLineNumber(index) {
  let lo = 0;
  let hi = lineStarts.length - 1;
  while (lo <= hi) {
    const mid = (lo + hi) >> 1;
    if (lineStarts[mid] <= index) {
      lo = mid + 1;
    } else {
      hi = mid - 1;
    }
  }
  return Math.max(1, hi + 1);
}

function getLineIndex(lineNumber) {
  return Math.max(0, Math.min(lines.length - 1, lineNumber - 1));
}

function getEnclosingFunction(lineNumber) {
  const start = Math.max(0, lineNumber - 220);
  for (let i = lineNumber - 1; i >= start; i -= 1) {
    const line = lines[i] || "";
    const fnMatch =
      line.match(/\bfunction\s+([A-Za-z0-9_$]+)\s*\(/) ||
      line.match(/\b(?:var|let|const)\s+([A-Za-z0-9_$]+)\s*=\s*function\b/) ||
      line.match(/\b([A-Za-z0-9_$]+)\s*:\s*function\s*\(/);
    if (fnMatch) {
      return fnMatch[1];
    }
  }
  return "module scope";
}

function captureCall(openParenIndex) {
  let depth = 0;
  let i = openParenIndex;
  let quote = "";
  let escaped = false;
  let inLineComment = false;
  let inBlockComment = false;
  for (; i < source.length; i += 1) {
    const ch = source[i];
    const next = source[i + 1];
    if (inLineComment) {
      if (ch === "\n") inLineComment = false;
      continue;
    }
    if (inBlockComment) {
      if (ch === "*" && next === "/") {
        inBlockComment = false;
        i += 1;
      }
      continue;
    }
    if (quote) {
      if (escaped) {
        escaped = false;
        continue;
      }
      if (ch === "\\") {
        escaped = true;
        continue;
      }
      if (ch === quote) {
        quote = "";
      }
      continue;
    }
    if (ch === "/" && next === "/") {
      inLineComment = true;
      i += 1;
      continue;
    }
    if (ch === "/" && next === "*") {
      inBlockComment = true;
      i += 1;
      continue;
    }
    if (ch === "'" || ch === '"' || ch === "`") {
      quote = ch;
      continue;
    }
    if (ch === "(") depth += 1;
    if (ch === ")") {
      depth -= 1;
      if (depth === 0) return source.slice(openParenIndex, i + 1);
    }
  }
  return source.slice(openParenIndex, Math.min(source.length, openParenIndex + 1600));
}

function decodeLiteral(raw) {
  const quote = raw[0];
  const body = raw.slice(1, -1);
  if (quote === "`") return body.replace(/\$\{[^}]+\}/g, "${...}");
  try {
    return Function("return " + raw)();
  } catch (error) {
    return body;
  }
}

function extractStringLiterals(block) {
  const results = [];
  let i = 0;
  while (i < block.length) {
    const ch = block[i];
    if (ch !== "'" && ch !== '"' && ch !== "`") {
      i += 1;
      continue;
    }
    const quote = ch;
    const start = i;
    i += 1;
    let escaped = false;
    for (; i < block.length; i += 1) {
      const c = block[i];
      if (escaped) {
        escaped = false;
        continue;
      }
      if (c === "\\") {
        escaped = true;
        continue;
      }
      if (c === quote) {
        const raw = block.slice(start, i + 1);
        const value = decodeLiteral(raw);
        results.push({ raw, value });
        i += 1;
        break;
      }
    }
  }
  return results;
}

function isUsefulLiteral(value) {
  const text = String(value || "").trim();
  if (!text) return false;
  if (text.length < 2) return false;
  if (/^[-_a-z0-9]+$/i.test(text) && !/\s/.test(text)) return false;
  if (/^sffc-/.test(text)) return false;
  if (/^data-sffc-/.test(text)) return false;
  if (/^https?:\/\//.test(text)) return false;
  if (/^[.#][A-Za-z0-9_-]+$/.test(text)) return false;
  return true;
}

function looksConversational(value) {
  const text = String(value || "").trim();
  if (!isUsefulLiteral(text)) return false;
  if (/[.!?؟]$/.test(text)) return true;
  if (/\b(?:I|I'm|I’ll|I'll|you|your|we|role|CV|application|email|name|membership|search|apply|recruiter|question|upload|choose|continue|confirm|send|tell|type|yes|no)\b/i.test(text)) {
    return true;
  }
  return text.split(/\s+/).length >= 3 && text.length >= 18;
}

const callTargets = [
  "botMessage",
  "botMessageNow",
  "botSequenceForCurrentTurn",
  "composeSemanticReply",
  "setPromptState",
  "showPromptRecoveryMessage",
  "focusComposer",
  "addChoices",
  "openQuestionDetour",
  "handleWildcardSocialInput",
  "answerKnowledgeQuestion",
  "requestRealPersonJoin",
  "showExplicitQuickRoleComparison",
];

const callRegex = new RegExp("\\b(" + callTargets.join("|") + ")\\s*\\(", "g");
const callEntries = [];
let match;
while ((match = callRegex.exec(source))) {
  const fn = match[1];
  const openParen = source.indexOf("(", match.index);
  const block = captureCall(openParen);
  const line = getLineNumber(match.index);
  const literals = extractStringLiterals(block)
    .map((item) => item.value)
    .filter(isUsefulLiteral);
  const unique = Array.from(new Set(literals));
  callEntries.push({
    fn,
    line,
    enclosing: getEnclosingFunction(line),
    literals: unique,
    snippet: block.slice(0, 1800),
  });
}

const sentenceEntries = [];
const fullLiteralRegex = /(['"`])(?:\\.|(?!\1)[\s\S])*?\1/g;
while ((match = fullLiteralRegex.exec(source))) {
  const raw = match[0];
  const value = decodeLiteral(raw);
  if (!looksConversational(value)) continue;
  const line = getLineNumber(match.index);
  sentenceEntries.push({
    line,
    enclosing: getEnclosingFunction(line),
    value: String(value).trim(),
  });
}

const seenSentence = new Set();
const uniqueSentences = sentenceEntries.filter((entry) => {
  const key = entry.line + "|" + entry.value;
  if (seenSentence.has(key)) return false;
  seenSentence.add(key);
  return true;
});

const grouped = new Map();
for (const entry of callEntries) {
  if (!grouped.has(entry.fn)) grouped.set(entry.fn, []);
  grouped.get(entry.fn).push(entry);
}

function renderLiteralList(literals) {
  if (!literals.length) {
    return '<p class="empty">No direct raw string literal in this call. The visible message is assembled from variables or helper functions.</p>';
  }
  return (
    "<ol>" +
    literals
      .map((literal) => "<li><code>" + escapeHtml(literal) + "</code></li>")
      .join("") +
    "</ol>"
  );
}

function renderCallEntry(entry) {
  return (
    '<details class="call-entry">' +
    '<summary><span class="badge">' +
    escapeHtml(entry.fn) +
    '</span><strong>Line ' +
    entry.line +
    "</strong><em>" +
    escapeHtml(entry.enclosing) +
    "</em></summary>" +
    '<div class="call-body">' +
    "<h4>Extracted raw strings</h4>" +
    renderLiteralList(entry.literals) +
    "<h4>Call snippet</h4>" +
    "<pre>" +
    escapeHtml(entry.snippet) +
    "</pre>" +
    "</div>" +
    "</details>"
  );
}

const html = `<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>sffc-crm-apply-chat Raw Emily Response Catalogue</title>
    <style>
      :root {
        --ink: #172033;
        --muted: #5f6f85;
        --line: #d9e1ec;
        --paper: #f5f7fb;
        --card: #ffffff;
        --blue: #3762d6;
        --soft: #eef2ff;
      }
      * { box-sizing: border-box; }
      body {
        margin: 0;
        background: var(--paper);
        color: var(--ink);
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
        line-height: 1.5;
      }
      main {
        width: min(1240px, calc(100vw - 32px));
        margin: 0 auto;
        padding: 34px 0 70px;
      }
      header, section {
        border: 1px solid var(--line);
        border-radius: 16px;
        background: var(--card);
        box-shadow: 0 16px 40px rgba(23, 32, 51, 0.07);
      }
      header { padding: 30px; }
      section { padding: 22px; margin-top: 18px; }
      h1, h2, h3, h4, p { margin-top: 0; }
      h1 { margin-bottom: 10px; font-size: clamp(28px, 4vw, 42px); line-height: 1.08; }
      h2 { margin-bottom: 12px; font-size: 23px; }
      h3 { margin-bottom: 8px; font-size: 17px; }
      h4 { margin: 16px 0 8px; color: var(--muted); font-size: 12px; letter-spacing: .04em; text-transform: uppercase; }
      .meta { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 18px; }
      .pill, .badge {
        display: inline-flex;
        align-items: center;
        min-height: 26px;
        padding: 3px 9px;
        border-radius: 999px;
        background: var(--soft);
        color: #2448ad;
        font-size: 12px;
        font-weight: 800;
      }
      .summary-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
      }
      .stat {
        padding: 14px;
        border: 1px solid var(--line);
        border-radius: 12px;
        background: #fbfcfe;
      }
      .stat strong { display: block; font-size: 26px; line-height: 1; }
      .stat span { color: var(--muted); font-size: 12px; font-weight: 750; }
      details.call-entry {
        border: 1px solid var(--line);
        border-radius: 12px;
        background: #fbfcfe;
        overflow: hidden;
      }
      details.call-entry + details.call-entry { margin-top: 10px; }
      summary {
        display: grid;
        grid-template-columns: auto auto minmax(0, 1fr);
        gap: 10px;
        align-items: center;
        padding: 12px 14px;
        cursor: pointer;
      }
      summary strong { font-size: 13px; }
      summary em {
        min-width: 0;
        overflow: hidden;
        color: var(--muted);
        font-size: 12px;
        font-style: normal;
        font-weight: 700;
        text-overflow: ellipsis;
        white-space: nowrap;
      }
      .call-body {
        padding: 0 14px 14px;
        border-top: 1px solid var(--line);
      }
      ol {
        margin: 0;
        padding-left: 22px;
      }
      li + li { margin-top: 5px; }
      code {
        white-space: pre-wrap;
        overflow-wrap: anywhere;
        color: #172033;
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        font-size: 12px;
      }
      pre {
        max-height: 360px;
        overflow: auto;
        margin: 0;
        padding: 13px;
        border-radius: 10px;
        background: #101828;
        color: #e5e7eb;
        font-size: 12px;
        line-height: 1.45;
      }
      .empty {
        margin: 0;
        color: var(--muted);
        font-size: 13px;
      }
      table {
        width: 100%;
        border-collapse: collapse;
        border: 1px solid var(--line);
        border-radius: 12px;
        overflow: hidden;
      }
      th, td {
        padding: 10px 12px;
        border-bottom: 1px solid var(--line);
        vertical-align: top;
        text-align: left;
      }
      th {
        background: #eef3f9;
        color: #26364f;
        font-size: 11px;
        letter-spacing: .04em;
        text-transform: uppercase;
      }
      tr:last-child td { border-bottom: 0; }
      @media (max-width: 760px) {
        main { width: min(100vw - 18px, 1240px); padding-top: 18px; }
        header, section { padding: 16px; }
        .summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        summary { grid-template-columns: 1fr; }
        table { display: block; overflow-x: auto; white-space: nowrap; }
      }
    </style>
  </head>
  <body>
    <main>
      <header>
        <span class="pill">Raw response catalogue</span>
        <h1>sffc-crm-apply-chat Emily Responses</h1>
        <p>This report is generated from <code>assets/js/crm/crm-apply-chat-article.js</code>. It lists response-related call sites and the raw string literals found inside them, then adds an appendix of sentence-like conversational strings found across the full script.</p>
        <div class="meta">
          <span class="pill">Generated: 2026-09-09</span>
          <span class="pill">Response call sites: ${callEntries.length}</span>
          <span class="pill">Sentence-like literals: ${uniqueSentences.length}</span>
        </div>
      </header>

      <section>
        <h2>Extraction Summary</h2>
        <div class="summary-grid">
          ${Array.from(grouped.entries())
            .map(([fn, entries]) => `<div class="stat"><strong>${entries.length}</strong><span>${escapeHtml(fn)}</span></div>`)
            .join("")}
        </div>
      </section>

      ${Array.from(grouped.entries())
        .map(([fn, entries]) => `<section><h2>${escapeHtml(fn)}</h2><p class="empty">${entries.length} call site${entries.length === 1 ? "" : "s"} found.</p>${entries.map(renderCallEntry).join("")}</section>`)
        .join("")}

      <section>
        <h2>Appendix: Sentence-Like Conversational String Literals</h2>
        <p class="empty">This section catches additional raw copy, labels, placeholders, and response fragments that may not sit directly inside a message function call.</p>
        <table>
          <thead>
            <tr>
              <th>Line</th>
              <th>Enclosing function</th>
              <th>Raw string</th>
            </tr>
          </thead>
          <tbody>
            ${uniqueSentences
              .map((entry) => `<tr><td>${entry.line}</td><td>${escapeHtml(entry.enclosing)}</td><td><code>${escapeHtml(entry.value)}</code></td></tr>`)
              .join("")}
          </tbody>
        </table>
      </section>
    </main>
  </body>
</html>
`;

fs.mkdirSync(path.dirname(outputPath), { recursive: true });
fs.writeFileSync(outputPath, html);
console.log(outputPath);
console.log("call_entries=" + callEntries.length);
console.log("sentence_like_literals=" + uniqueSentences.length);
