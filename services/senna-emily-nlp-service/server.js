"use strict";

const express = require("express");
const { buildMeaning, serviceInfo } = require("./meaning-engine");

const app = express();
const port = Number(process.env.PORT || 3000);
const token = String(process.env.EMILY_NLP_TOKEN || "").trim();
const allowedOrigin = String(process.env.CORS_ORIGIN || "*").trim() || "*";

app.use(express.json({ limit: process.env.MAX_JSON_BODY || "128kb" }));

app.use((req, res, next) => {
  setCorsHeaders(req, res);
  if (req.method === "OPTIONS") {
    res.status(204).end();
    return;
  }
  next();
});

app.use((req, res, next) => {
  if (!token) {
    next();
    return;
  }
  const header = String(req.headers.authorization || "");
  if (header === `Bearer ${token}`) {
    next();
    return;
  }
  res.status(401).json({ ok: false, error: "unauthorized" });
});

app.get("/", (req, res) => {
  res.json(serviceInfo());
});

app.get("/health", (req, res) => {
  res.json(serviceInfo());
});

app.post("/meaning", (req, res) => {
  try {
    const meaning = buildMeaning(req.body || {});
    res.json({ ok: true, meaning });
  } catch (error) {
    console.error("[emily-nlp] meaning failed", error);
    res.status(500).json({ ok: false, error: "meaning_failed" });
  }
});

app.post("/classify", (req, res) => {
  try {
    const meaning = buildMeaning(req.body || {});
    res.json({
      ok: true,
      classification: {
        primaryIntent: meaning.primaryIntent,
        confidence: meaning.confidence,
        secondaryIntents: meaning.secondaryIntents,
        action: meaning.action,
      },
    });
  } catch (error) {
    console.error("[emily-nlp] classify failed", error);
    res.status(500).json({ ok: false, error: "classify_failed" });
  }
});

app.post("/rewrite", (req, res) => {
  try {
    const meaning = buildMeaning(req.body || {});
    res.json({
      ok: true,
      rewrittenQueries: meaning.rewrittenQueries,
      action: meaning.action,
      entities: meaning.entities,
    });
  } catch (error) {
    console.error("[emily-nlp] rewrite failed", error);
    res.status(500).json({ ok: false, error: "rewrite_failed" });
  }
});

app.use((req, res) => {
  res.status(404).json({ ok: false, error: "not_found" });
});

app.listen(port, () => {
  console.log(`Senna Emily NLP service listening on ${port}`);
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
