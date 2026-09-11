import express from "express";
import multer from "multer";
import { LiteParse } from "@llamaindex/liteparse";
import { binary } from "harper.js/binary";
import { Dialect, LocalLinter, SuggestionKind } from "harper.js";
import { createRequire } from "module";
import { randomUUID } from "crypto";
import os from "os";
import path from "path";
import { writeFile, unlink } from "fs/promises";

const require = createRequire(import.meta.url);
const { parseResumeAsync } = require("resume-parser-ats");

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

function cleanText(value) {
  return String(value || "")
    .replace(/\u00a0/g, " ")
    .replace(/[ \t]+/g, " ")
    .replace(/\s+\n/g, "\n")
    .replace(/\n\s+/g, "\n")
    .trim();
}

function isNotParsed(value) {
  return !value || /^not\s+parsed$/i.test(cleanText(value));
}

function normalizeStringList(values) {
  const seen = new Set();
  return (Array.isArray(values) ? values : [])
    .map(cleanText)
    .filter((value) => value && !isNotParsed(value))
    .filter((value) => {
      const key = value.toLowerCase();
      if (seen.has(key)) return false;
      seen.add(key);
      return true;
    });
}

function normalizeProfile(profile, fallbackText) {
  const text = cleanText(fallbackText);
  const profileContactText = cleanText(
    [profile?.email, profile?.phone, profile?.url, fallbackText].join(" ")
  );
  const contactLine =
    text
      .split(/\n/)
      .map(cleanText)
      .find((line) => /@|\+?\d[\d\s().-]{6,}|linkedin\.com/i.test(line)) || "";
  const contactParts = contactLine
    .split(/\s*(?:§|\||•)\s*/)
    .map(cleanText)
    .filter(Boolean);
  const inferredLocation =
    contactParts.find(
      (part) =>
        looksLikeLocation(part) &&
        !/@/.test(part) &&
        !/\+?\d[\d\s().-]{6,}/.test(part)
    ) || "";
  const email = cleanText(
    (profileContactText.match(/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i) || [])[0]
  );
  const phone = cleanText(
    (
      profileContactText.match(
        /(?:\+\d{1,3}[\s().-]*)?(?:\(?\d{2,4}\)?[\s().-]*){2,}\d{2,4}/
      ) || []
    )[0]
  );
  const linkedin =
    cleanText(
      (
        profileContactText.match(
          /(?:https?:\/\/)?(?:www\.)?linkedin\.com\/in\/[^\s|,;]+/i
        ) || []
      )[0]
    ) || cleanText(profile?.url);

  return {
    name: isNotParsed(profile?.name) ? "" : cleanText(profile?.name),
    email: isNotParsed(email) ? "" : email,
    phone: isNotParsed(phone) ? "" : phone,
    linkedin: isNotParsed(linkedin) ? "" : linkedin,
    location: isNotParsed(profile?.location)
      ? inferredLocation
      : cleanText(profile?.location) || inferredLocation,
    website: "",
    summary: isNotParsed(profile?.summary) ? "" : cleanText(profile?.summary),
  };
}

const MONTH_PATTERN =
  "(?:jan|january|feb|february|mar|march|apr|april|may|jun|june|jul|july|aug|august|sep|sept|september|oct|october|nov|november|dec|december)";
const DATE_RANGE_REGEX = new RegExp(
  `(?:${MONTH_PATTERN}\\.?\\s*,?\\s*)?\\d{2,4}\\s*(?:-|–|—|to)\\s*(?:present|current|now|(?:${MONTH_PATTERN}\\.?\\s*,?\\s*)?\\d{2,4})`,
  "i"
);
const SINGLE_DATE_REGEX = new RegExp(`(?:${MONTH_PATTERN}\\.?\\s*,?\\s*)?\\d{4}`, "i");
const BULLET_REGEX = /^\s*(?:[•▪●◦*·-]|§)\s*/;
const EMBEDDED_SECTION_REGEX =
  /^(?:profile|summary|education|work experience|professional experience|employment|career history|skills(?:\s+and\s+interests)?|technical skills|projects|certifications|languages|awards|achievements)$/i;

function looksLikeLocation(value) {
  return /\b(?:uae|united arab emirates|dubai|abu dhabi|riyadh|saudi|qatar|doha|kuwait|bahrain|oman|london|uk|united kingdom|milan|rome|moscow|russia|italy|france|germany|spain|remote)\b/i.test(
    value || ""
  );
}

function extractDateRange(value) {
  const clean = cleanText(value);
  const range = clean.match(DATE_RANGE_REGEX);
  if (range) return cleanText(range[0]);
  const single = clean.match(SINGLE_DATE_REGEX);
  return single ? cleanText(single[0]) : "";
}

function removeDateRange(value) {
  return cleanText(String(value || "").replace(DATE_RANGE_REGEX, " ").replace(/\s{2,}/g, " "));
}

function splitLineByColumns(rawLine) {
  return String(rawLine || "")
    .split(/\s{3,}/)
    .map(cleanText)
    .filter(Boolean);
}

function getLogicalSectionLines(sections, wantedPattern) {
  const out = [];
  (Array.isArray(sections) ? sections : []).forEach((section) => {
    let active = wantedPattern.test(cleanText(section?.title || ""));
    (Array.isArray(section?.lines) ? section.lines : []).forEach((line) => {
      const raw = String(line?.text || line || "");
      const clean = cleanText(raw);
      if (!clean) return;
      if (EMBEDDED_SECTION_REGEX.test(clean)) {
        active = wantedPattern.test(clean);
        return;
      }
      if (active) {
        out.push({ raw, text: clean });
      }
    });
  });
  return out;
}

function parseOrganizationLine(line) {
  const columns = splitLineByColumns(line?.raw || line?.text || "");
  const text = cleanText(line?.text || line?.raw || "");
  if (columns.length >= 2 && looksLikeLocation(columns[columns.length - 1])) {
    return {
      name: cleanText(columns.slice(0, -1).join(" ")),
      location: cleanText(columns[columns.length - 1]),
    };
  }
  return { name: text, location: "" };
}

function parseRoleDateLine(line) {
  const raw = String(line?.raw || line?.text || "");
  const columns = splitLineByColumns(raw);
  const text = cleanText(line?.text || raw);
  let dates = extractDateRange(text);
  let role = dates ? removeDateRange(text) : text;
  if (columns.length >= 2) {
    const last = columns[columns.length - 1];
    const columnDate = extractDateRange(last);
    if (columnDate) {
      dates = columnDate;
      role = cleanText(columns.slice(0, -1).join(" "));
    }
  }
  return { role, dates };
}

function parseStructuredExperienceFromSections(sections) {
  const lines = getLogicalSectionLines(sections, /experience|employment|history/i);
  const entries = [];
  let pendingOrg = null;
  let current = null;

  function pushCurrent() {
    if (!current) return;
    current.bullets = normalizeStringList(current.bullets);
    current.lines = normalizeStringList(
      [current.role, current.company, current.dates, current.location].concat(
        current.bullets
      )
    );
    if (current.role || current.company || current.bullets.length) {
      entries.push(current);
    }
    current = null;
  }

  lines.forEach((line, index) => {
    const text = cleanText(line.text);
    const next = lines[index + 1];
    const isBullet = BULLET_REGEX.test(line.raw) || BULLET_REGEX.test(text);
    const date = extractDateRange(text);

    if (isBullet) {
      const bullet = cleanText(text.replace(BULLET_REGEX, ""));
      if (current && bullet) {
        current.bullets.push(bullet);
      }
      return;
    }

    if (date) {
      const roleMeta = parseRoleDateLine(line);
      pushCurrent();
      current = {
        type: "experience",
        role: roleMeta.role,
        title: roleMeta.role,
        company: pendingOrg?.name || "",
        dates: roleMeta.dates || date,
        location: pendingOrg?.location || "",
        bullets: [],
        lines: [],
        parserSource: "resume-parser-ats-lines",
      };
      pendingOrg = null;
      return;
    }

    if (next && extractDateRange(next.text) && !BULLET_REGEX.test(next.text)) {
      pendingOrg = parseOrganizationLine(line);
      return;
    }

    if (
      current &&
      current.bullets.length &&
      !date &&
      !BULLET_REGEX.test(text) &&
      text.split(/\s+/).length > 2
    ) {
      current.bullets[current.bullets.length - 1] = cleanText(
        current.bullets[current.bullets.length - 1] + " " + text
      );
      return;
    }

    if (!current && text && text.split(/\s+/).length <= 8) {
      pendingOrg = parseOrganizationLine(line);
      return;
    }
  });

  pushCurrent();
  return entries;
}

function parseStructuredEducationFromSections(sections) {
  const lines = getLogicalSectionLines(sections, /^education$/i);
  const entries = [];
  let current = null;

  function pushCurrent() {
    if (!current) return;
    current.details = normalizeStringList(current.details);
    if (current.school || current.degree || current.details.length) {
      entries.push(current);
    }
    current = null;
  }

  lines.forEach((line, index) => {
    const text = cleanText(line.text);
    const next = lines[index + 1];
    const date = extractDateRange(text);
    const isBullet = BULLET_REGEX.test(text);
    if (!text) return;
    if (!current || (next && extractDateRange(next.text) && !isBullet)) {
      if (!isBullet && !date && next && extractDateRange(next.text)) {
        pushCurrent();
        current = {
          school: parseOrganizationLine(line).name,
          degree: "",
          dates: "",
          gpa: "",
          details: [],
          parserSource: "resume-parser-ats-lines",
        };
        return;
      }
    }
    if (date && current && !current.dates) {
      current.dates = date;
      const degree = removeDateRange(text);
      if (degree) current.degree = degree;
      return;
    }
    if (!current) {
      current = {
        school: "",
        degree: "",
        dates: date,
        gpa: "",
        details: [],
        parserSource: "resume-parser-ats-lines",
      };
    }
    if (/gpa|grade/i.test(text) && !current.gpa) {
      current.gpa = text;
    } else if (!current.degree && !isBullet && !date) {
      current.degree = text;
    } else {
      current.details.push(cleanText(text.replace(BULLET_REGEX, "")));
    }
  });

  pushCurrent();
  return entries;
}

function parseStructuredSkillsFromSections(sections) {
  const lines = getLogicalSectionLines(sections, /skills/i);
  return normalizeStringList(
    lines.reduce((list, line) => {
      const text = cleanText(line.text).replace(BULLET_REGEX, "");
      if (
        /\b(?:achievement|deloitte|mckinsey|changellenge|finalist|semi-finalist|case club)\b/i.test(
          text
        )
      ) {
        return list;
      }
      return list.concat(text.split(/\s*[;,|]\s*/));
    }, [])
  );
}

function sanitizeStructuredSkills(values) {
  return normalizeStringList(values).filter((value) => {
    const clean = cleanText(value);
    if (!clean || clean.length < 2) return false;
    if (/^\d{2,4}\s*(?:-|–|—)?\s*(?:\d{2,4})?$/.test(clean)) return false;
    if (
      /\b(?:achievement|achievements|finalist|semi-finalist|interests?|languages?)\b/i.test(
        clean
      )
    ) {
      return false;
    }
    if (/^\(?cursor$/i.test(clean) || /^llm tools\)?$/i.test(clean)) {
      return true;
    }
    return true;
  });
}

function normalizeAtsExperience(entry) {
  const title = isNotParsed(entry?.jobTitle) ? "" : cleanText(entry?.jobTitle);
  const company = isNotParsed(entry?.company) ? "" : cleanText(entry?.company);
  return {
    type: "experience",
    role: title,
    company,
    title,
    dates: isNotParsed(entry?.date) ? "" : cleanText(entry?.date),
    location: "",
    bullets: normalizeStringList(entry?.descriptions),
    lines: normalizeStringList([title, company, entry?.date].concat(entry?.descriptions || [])),
    parserSource: "resume-parser-ats",
  };
}

function normalizeAtsEducation(entry) {
  const school = isNotParsed(entry?.school) ? "" : cleanText(entry?.school);
  const degree = isNotParsed(entry?.degree) ? "" : cleanText(entry?.degree);
  const gpa = isNotParsed(entry?.gpa) ? "" : cleanText(entry?.gpa);
  return {
    school,
    degree,
    dates: isNotParsed(entry?.date) ? "" : cleanText(entry?.date),
    gpa,
    details: normalizeStringList([degree, gpa].concat(entry?.descriptions || [])),
    parserSource: "resume-parser-ats",
  };
}

function normalizeAtsSections(sections) {
  return (Array.isArray(sections) ? sections : []).map((section) => ({
    title: cleanText(section?.title),
    lines: (Array.isArray(section?.lines) ? section.lines : [])
      .map((line) => cleanText(line?.text || line))
      .filter(Boolean),
  }));
}

function normalizeStructuredResume(parseResult, fallbackText) {
  const data = parseResult?.data || {};
  const profile = normalizeProfile(data.profile || {}, fallbackText);
  const lineExperience = parseStructuredExperienceFromSections(data.sections);
  const packageExperience = (Array.isArray(data.experience) ? data.experience : [])
    .map(normalizeAtsExperience)
    .filter((entry) => entry.role || entry.company || entry.bullets.length);
  const experience =
    lineExperience.length >= Math.min(2, packageExperience.length || 2)
      ? lineExperience
      : packageExperience;
  const lineEducation = parseStructuredEducationFromSections(data.sections);
  const packageEducation = (Array.isArray(data.education) ? data.education : [])
    .map(normalizeAtsEducation)
    .filter((entry) => entry.school || entry.degree || entry.details.length);
  const education =
    lineEducation.length >= Math.min(1, packageEducation.length || 1)
      ? lineEducation
      : packageEducation;
  const packageSkills = (Array.isArray(data.skills) ? data.skills : []).reduce(
    (list, group) => list.concat(normalizeStringList(group?.descriptions)),
    []
  );
  const lineSkills = parseStructuredSkillsFromSections(data.sections);
  const skills = sanitizeStructuredSkills(lineSkills.length ? lineSkills : packageSkills);
  const projects = (Array.isArray(data.projects) ? data.projects : []).map((project) => ({
    name: isNotParsed(project?.name) ? "" : cleanText(project?.name),
    dates: isNotParsed(project?.date) ? "" : cleanText(project?.date),
    bullets: normalizeStringList(project?.descriptions),
    parserSource: "resume-parser-ats",
  }));

  return {
    ok: Boolean(parseResult?.success),
    parser: "resume-parser-ats",
    profile,
    sections: normalizeAtsSections(data.sections),
    experience,
    education,
    skills,
    projects,
    lines: (Array.isArray(data.lines) ? data.lines : [])
      .map((line) => cleanText(line?.text || line))
      .filter(Boolean),
    metadata: parseResult?.metadata || {},
  };
}

async function parseStructuredResumeFromUpload(file, fallbackText) {
  if (!file?.buffer) {
    return null;
  }
  const extension = path.extname(file.originalname || "") || ".pdf";
  const tempPath = path.join(
    os.tmpdir(),
    `senna-resume-${Date.now()}-${randomUUID()}${extension}`
  );

  try {
    await writeFile(tempPath, file.buffer);
    const parsed = await parseResumeAsync({ filePath: tempPath });
    return normalizeStructuredResume(parsed, fallbackText);
  } catch (error) {
    return {
      ok: false,
      parser: "resume-parser-ats",
      error: "structured_parse_failed",
      message: error && error.message ? error.message : String(error),
    };
  } finally {
    try {
      await unlink(tempPath);
    } catch (error) {}
  }
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
    parser: "liteparse+resume-parser-ats",
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
    const normalized = normalizeParseResult(result);
    normalized.structured = await parseStructuredResumeFromUpload(
      req.file,
      normalized.text
    );
    normalized.parser = normalized.structured?.ok
      ? "liteparse+resume-parser-ats"
      : "liteparse";
    res.json(normalized);
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
