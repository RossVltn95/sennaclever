import express from "express";
import multer from "multer";
import { LiteParse } from "@llamaindex/liteparse";
import { binary } from "harper.js/binary";
import { Dialect, LocalLinter, SuggestionKind } from "harper.js";

process.on("uncaughtException", (error) => {
  console.error("uncaughtException", error);
});

process.on("unhandledRejection", (error) => {
  console.error("unhandledRejection", error);
});

const app = express();
const upload = multer({
  storage: multer.memoryStorage(),
  limits: {
    fileSize: Number(process.env.MAX_UPLOAD_BYTES || 16 * 1024 * 1024),
  },
});

const port = Number(process.env.PORT || 3000);
const allowedOrigin = String(process.env.CORS_ORIGIN || "*").trim() || "*";
const token = String(process.env.LITEPARSE_TOKEN || "").trim();
const harperDialect = String(process.env.HARPER_DIALECT || "american").toLowerCase();
const harperMaxTextLength = Number(process.env.HARPER_MAX_TEXT_LENGTH || 20000);
const parser = new LiteParse({
  outputFormat: "json",
  ocrEnabled: process.env.LITEPARSE_OCR_ENABLED !== "0",
  ocrLanguage: process.env.LITEPARSE_OCR_LANGUAGE || "eng",
  maxPages: Number(process.env.LITEPARSE_MAX_PAGES || 20),
  parseTimeout: Number(process.env.LITEPARSE_TIMEOUT_SECONDS || 20),
  poolSize: Number(process.env.LITEPARSE_POOL_SIZE || 1),
});
const harperDialectMap = {
  american: Dialect.American,
  british: Dialect.British,
  canadian: Dialect.Canadian,
  australian: Dialect.Australian,
};
const harperLinter = new LocalLinter({
  binary,
  dialect: harperDialectMap[harperDialect] || Dialect.American,
});

function setCorsHeaders(req, res) {
  const requestOrigin = req.headers.origin || "";
  const origin =
    allowedOrigin === "*" || allowedOrigin === requestOrigin
      ? allowedOrigin === "*"
        ? "*"
        : requestOrigin
      : allowedOrigin;
  res.setHeader("Access-Control-Allow-Origin", origin);
  res.setHeader("Access-Control-Allow-Methods", "GET,POST,OPTIONS");
  res.setHeader("Access-Control-Allow-Headers", "Authorization,Content-Type");
}

function requireToken(req, res, next) {
  const header = String(req.headers.authorization || "");
  if (!token) {
    next();
    return;
  }
  if (header === `Bearer ${token}`) {
    next();
    return;
  }
  res.status(401).json({ ok: false, error: "unauthorized" });
}

function normalizeParseResult(result) {
  const pages = Array.isArray(result?.pages) ? result.pages : [];
  return {
    ok: true,
    parser: "liteparse",
    text: String(result?.text || "").trim(),
    totalPages: Number(result?.totalPages || result?.total_pages || pages.length || 0),
    pages,
  };
}

function normalizeHarperSuggestion(suggestion) {
  if (!suggestion) {
    return null;
  }
  return {
    kind:
      suggestion.kind() === SuggestionKind.Remove ? "remove" : "replace",
    replacement: String(suggestion.get_replacement_text() || ""),
  };
}

function normalizeHarperLint(lint, text) {
  const span = lint.span();
  const start = Number(span?.start || 0);
  const end = Number(span?.end || start);
  const suggestions = Array.from(lint.suggestions ? lint.suggestions() : [])
    .map(normalizeHarperSuggestion)
    .filter(Boolean)
    .slice(0, 5);
  return {
    start,
    end,
    problemText: String(
      lint.get_problem_text ? lint.get_problem_text() : text.slice(start, end)
    ),
    kind: String(lint.lint_kind_pretty ? lint.lint_kind_pretty() : lint.lint_kind?.() || "Grammar"),
    message: String(lint.message ? lint.message() : ""),
    suggestions,
  };
}

app.use((req, res, next) => {
  setCorsHeaders(req, res);
  if (req.method === "OPTIONS") {
    res.status(204).end();
    return;
  }
  next();
});
app.use(express.json({ limit: "256kb" }));

app.get("/health", (req, res) => {
  res.json({
    ok: true,
    parser: "liteparse",
    grammar: "harper",
    ocrEnabled: process.env.LITEPARSE_OCR_ENABLED !== "0",
  });
});

app.post("/parse", requireToken, upload.single("file"), async (req, res) => {
  try {
    if (!req.file || !req.file.buffer) {
      res.status(400).json({ ok: false, error: "missing_file" });
      return;
    }

    const result = await parser.parse(req.file.buffer);
    res.json(normalizeParseResult(result));
  } catch (error) {
    res.status(500).json({
      ok: false,
      error: "parse_failed",
      message: error && error.message ? error.message : String(error),
    });
  }
});

app.post("/review-text", requireToken, async (req, res) => {
  try {
    const text = String(req.body?.text || "").slice(0, harperMaxTextLength);
    if (!text.trim()) {
      res.status(400).json({ ok: false, error: "missing_text" });
      return;
    }

    const lints = await harperLinter.lint(text);
    res.json({
      ok: true,
      engine: "harper",
      dialect: harperDialectMap[harperDialect] ? harperDialect : "american",
      matches: lints.map((lint) => normalizeHarperLint(lint, text)),
    });
  } catch (error) {
    res.status(500).json({
      ok: false,
      error: "review_failed",
      message: error && error.message ? error.message : String(error),
    });
  }
});

async function shutdown() {
  try {
    await harperLinter.dispose();
  } catch (error) {}
}

process.on("SIGTERM", () => {
  shutdown().finally(() => process.exit(0));
});

process.on("SIGINT", () => {
  shutdown().finally(() => process.exit(0));
});

const server = app.listen(port, "0.0.0.0", () => {
  console.log(`Senna LiteParse service listening on ${port}`);
});

server.on("error", (error) => {
  console.error("server error", error);
  process.exitCode = 1;
});
