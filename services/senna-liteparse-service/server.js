import express from "express";
import multer from "multer";
import { LiteParse } from "@llamaindex/liteparse";

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
const parser = new LiteParse({
  outputFormat: "json",
  ocrEnabled: process.env.LITEPARSE_OCR_ENABLED !== "0",
  ocrLanguage: process.env.LITEPARSE_OCR_LANGUAGE || "eng",
  maxPages: Number(process.env.LITEPARSE_MAX_PAGES || 20),
  parseTimeout: Number(process.env.LITEPARSE_TIMEOUT_SECONDS || 20),
  poolSize: Number(process.env.LITEPARSE_POOL_SIZE || 1),
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

app.use((req, res, next) => {
  setCorsHeaders(req, res);
  if (req.method === "OPTIONS") {
    res.status(204).end();
    return;
  }
  next();
});

app.get("/health", (req, res) => {
  res.json({
    ok: true,
    parser: "liteparse",
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

const server = app.listen(port, "0.0.0.0", () => {
  console.log(`Senna LiteParse service listening on ${port}`);
});

server.on("error", (error) => {
  console.error("server error", error);
  process.exitCode = 1;
});
