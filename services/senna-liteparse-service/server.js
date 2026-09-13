import express from "express";
import multer from "multer";
import { LiteParse } from "@llamaindex/liteparse";
import { binary } from "harper.js/binary";
import { Dialect, LocalLinter, SuggestionKind } from "harper.js";
import { createRequire } from "module";
import { randomUUID } from "crypto";
import { execFile } from "child_process";
import os from "os";
import path from "path";
import { writeFile, unlink } from "fs/promises";
import { promisify } from "util";

const require = createRequire(import.meta.url);
const { parseResumeAsync } = require("resume-parser-ats");
const execFileAsync = promisify(execFile);

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
const pyresumeEnabled = process.env.PYRESUME_ENABLED !== "0";
const pyresumeTimeoutMs = Number(process.env.PYRESUME_TIMEOUT_MS || 12000);
const layoutParseEnabled = process.env.LAYOUT_PARSE_ENABLED !== "0";
const layoutParseTimeoutMs = Number(process.env.LAYOUT_PARSE_TIMEOUT_MS || 12000);
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

const DATE_MONTH_ALIASES = {
  jan: 1,
  january: 1,
  janvier: 1,
  gennaio: 1,
  enero: 1,
  januar: 1,
  feb: 2,
  february: 2,
  fevrier: 2,
  février: 2,
  febbraio: 2,
  febrero: 2,
  februar: 2,
  mar: 3,
  march: 3,
  mars: 3,
  marzo: 3,
  märz: 3,
  maerz: 3,
  apr: 4,
  april: 4,
  avril: 4,
  aprile: 4,
  abril: 4,
  may: 5,
  mai: 5,
  maggio: 5,
  mayo: 5,
  jun: 6,
  june: 6,
  juin: 6,
  giugno: 6,
  junio: 6,
  juni: 6,
  jul: 7,
  july: 7,
  juillet: 7,
  luglio: 7,
  julio: 7,
  juli: 7,
  aug: 8,
  august: 8,
  aout: 8,
  août: 8,
  agosto: 8,
  sep: 9,
  sept: 9,
  september: 9,
  septembre: 9,
  settembre: 9,
  septiembre: 9,
  okt: 10,
  oct: 10,
  october: 10,
  octobre: 10,
  ottobre: 10,
  octubre: 10,
  oktober: 10,
  nov: 11,
  november: 11,
  novembre: 11,
  noviembre: 11,
  dec: 12,
  december: 12,
  decembre: 12,
  décembre: 12,
  dicembre: 12,
  diciembre: 12,
  dezember: 12,
};
const MONTH_PATTERN = `(?:${Object.keys(DATE_MONTH_ALIASES)
  .sort((left, right) => right.length - left.length)
  .map((month) => month.replace(/[.*+?^${}()|[\]\\]/g, "\\$&"))
  .join("|")})`;
const PRESENT_DATE_PATTERN =
  /\b(?:present|current|now|ongoing|to date|till date|today|oggi|attuale|actualidad|présent|presentement|présentement|heute|aktuell|bis heute)\b|(?:حتى الآن|حاليا|حالياً|الآن)/i;
const DATE_RANGE_REGEX = new RegExp(
  `(?:${MONTH_PATTERN}\\.?\\s*,?\\s*)?\\d{2,4}\\s*(?:-|–|—|to|until|au|à|bis|حتى)\\s*(?:present|current|now|oggi|attuale|actualidad|(?:${MONTH_PATTERN}\\.?\\s*,?\\s*)?\\d{2,4})`,
  "i"
);
const SINGLE_DATE_REGEX = new RegExp(`(?:${MONTH_PATTERN}\\.?\\s*,?\\s*)?\\d{4}`, "i");
const BULLET_REGEX = /^\s*(?:[•▪●◦*·-]|§)\s*/;
const SECTION_KEY_ALIASES = {
  summary: [
    "profile",
    "summary",
    "professional summary",
    "career summary",
    "career profile",
    "personal profile",
    "objective",
    "about",
    "about me",
    "profil",
    "profil professionnel",
    "resume",
    "résumé",
    "riassunto",
    "profilo",
    "perfil",
    "resumen",
    "objetivo",
    "kurzprofil",
    "über mich",
    "ملخص",
    "نبذة",
    "الهدف المهني",
  ],
  experience: [
    "experience",
    "work experience",
    "professional experience",
    "employment",
    "employment history",
    "career history",
    "work history",
    "professional history",
    "professional background",
    "professional experience",
    "esperienza",
    "esperienza professionale",
    "esperienze professionali",
    "experiencia",
    "experiencia profesional",
    "experiencia laboral",
    "expérience",
    "expérience professionnelle",
    "expériences professionnelles",
    "berufserfahrung",
    "berufliche erfahrung",
    "الخبرة العملية",
    "الخبرات العملية",
  ],
  education: [
    "education",
    "academic",
    "academic background",
    "academic qualifications",
    "qualifications",
    "training",
    "formazione",
    "contatto formazione",
    "contatto_formazione",
    "formazione e istruzione",
    "istruzione",
    "formation",
    "formation académique",
    "educación",
    "formación",
    "formación académica",
    "ausbildung",
    "bildung",
    "التعليم",
    "المؤهلات",
    "المؤهلات العلمية",
  ],
  skills: [
    "skills",
    "technical skills",
    "core skills",
    "key skills",
    "professional skills",
    "competencies",
    "competences",
    "competenze",
    "competenze tecniche",
    "compétences",
    "compétences techniques",
    "habilidades",
    "habilidades técnicas",
    "fähigkeiten",
    "kenntnisse",
    "مهارات",
    "المهارات",
  ],
  languages: [
    "languages",
    "language",
    "lingue",
    "langues",
    "idiomas",
    "sprachen",
    "اللغات",
  ],
  projects: [
    "projects",
    "project experience",
    "selected projects",
    "publications",
    "publication",
    "pubblicazioni",
    "publicaciones",
    "publications et projets",
    "projekte",
    "المشاريع",
    "المنشورات",
  ],
  certifications: [
    "certifications",
    "certificates",
    "licenses",
    "licences",
    "certificazioni",
    "attestati",
    "certificats",
    "certificados",
    "zertifikate",
    "الشهادات",
  ],
  interests: [
    "interests",
    "additional interests",
    "other interests",
    "hobbies",
    "interessi",
    "centres d'intérêt",
    "intereses",
    "interessen",
    "الاهتمامات",
  ],
};

function normalizeSectionHeadingText(value) {
  return cleanText(value)
    .toLowerCase()
    .replace(/[_/|]+/g, " ")
    .replace(/[’']/g, "")
    .replace(/^[\s:;.,&-]+|[\s:;.,&-]+$/g, "")
    .replace(/\s+/g, " ")
    .trim();
}

function inferSectionKeyFromHeading(title) {
  const clean = normalizeSectionHeadingText(title);
  if (!clean || clean === "&") return "";
  const wordCount = clean.split(/\s+/).filter(Boolean).length;
  for (const [key, aliases] of Object.entries(SECTION_KEY_ALIASES)) {
    if (aliases.includes(clean)) return key;
  }
  if (wordCount <= 6) {
    for (const [key, aliases] of Object.entries(SECTION_KEY_ALIASES)) {
      if (
        aliases.some(
          (alias) => clean.startsWith(`${alias} `) || clean.endsWith(` ${alias}`)
        )
      ) {
        return key;
      }
    }
  }
  return "";
}

function isEmbeddedSectionHeading(value) {
  return !!inferSectionKeyFromHeading(value);
}

function looksLikeLocation(value) {
  return /\b(?:uae|united arab emirates|dubai|abu dhabi|riyadh|saudi|qatar|doha|kuwait|bahrain|oman|london|uk|united kingdom|milan|rome|moscow|russia|italy|france|germany|spain|remote)\b/i.test(
    value || ""
  );
}

function normalizeDateDigits(value) {
  const arabicIndic = "٠١٢٣٤٥٦٧٨٩";
  const easternArabic = "۰۱۲۳۴۵۶۷۸۹";
  return String(value || "").replace(/[٠-٩۰-۹]/g, (char) => {
    const arabicIndex = arabicIndic.indexOf(char);
    if (arabicIndex !== -1) return String(arabicIndex);
    const easternIndex = easternArabic.indexOf(char);
    return easternIndex !== -1 ? String(easternIndex) : char;
  });
}

function normalizeCvDateText(value) {
  return cleanText(normalizeDateDigits(value))
    .toLowerCase()
    .replace(/[–—−]/g, "-")
    .replace(/\s+(?:to|until|au|à|bis|حتى)\s+/gi, " - ")
    .replace(/\b(?:present day|to date|till date|today|now|current|ongoing|oggi|attuale|actualidad|présent|presentement|présentement|heute|aktuell|bis heute)\b/gi, "present")
    .replace(/(?:حتى الآن|حاليا|حالياً|الآن)/g, "present")
    .replace(/\s+/g, " ")
    .trim();
}

function normalizeTwoDigitYear(year) {
  const numeric = Number(year);
  if (!Number.isFinite(numeric)) return 0;
  if (numeric >= 100) return numeric;
  return numeric <= 35 ? 2000 + numeric : 1900 + numeric;
}

function normalizeCvDatePoint(point, preferEnd) {
  const raw = normalizeCvDateText(point);
  let match;
  if (!raw) return null;
  if (PRESENT_DATE_PATTERN.test(raw) || raw === "present") {
    const now = new Date();
    return {
      year: now.getFullYear(),
      month: now.getMonth() + 1,
      precision: "current",
      isCurrent: true,
      raw,
    };
  }
  match = raw.match(/\b(0?[1-9]|1[0-2])\s*[/.-]\s*(\d{2}|19\d{2}|20\d{2})\b/);
  if (match) {
    return {
      year: normalizeTwoDigitYear(match[2]),
      month: Number(match[1]),
      precision: "month",
      isCurrent: false,
      raw,
    };
  }
  match = raw.match(/\b(19\d{2}|20\d{2})\s*[/.-]\s*(0?[1-9]|1[0-2])\b/);
  if (match) {
    return {
      year: Number(match[1]),
      month: Number(match[2]),
      precision: "month",
      isCurrent: false,
      raw,
    };
  }
  match = raw.match(new RegExp(`\\b(${MONTH_PATTERN})\\.?\\s*,?\\s*(\\d{2}|19\\d{2}|20\\d{2})\\b`, "i"));
  if (match && DATE_MONTH_ALIASES[match[1]]) {
    return {
      year: normalizeTwoDigitYear(match[2]),
      month: DATE_MONTH_ALIASES[match[1]],
      precision: "month",
      isCurrent: false,
      raw,
    };
  }
  match = raw.match(new RegExp(`\\b(\\d{2}|19\\d{2}|20\\d{2})\\s+(${MONTH_PATTERN})\\.?\\b`, "i"));
  if (match && DATE_MONTH_ALIASES[match[2]]) {
    return {
      year: normalizeTwoDigitYear(match[1]),
      month: DATE_MONTH_ALIASES[match[2]],
      precision: "month",
      isCurrent: false,
      raw,
    };
  }
  match = raw.match(/\b(19\d{2}|20\d{2})\b/);
  if (match) {
    return {
      year: Number(match[1]),
      month: preferEnd ? 12 : 1,
      precision: "year",
      isCurrent: false,
      raw,
    };
  }
  return null;
}

function getCvDateTokenPattern() {
  return new RegExp(
    [
      "\\b(?:0?[1-9]|1[0-2])\\s*[/.-]\\s*(?:\\d{2}|19\\d{2}|20\\d{2})\\b",
      "\\b(?:19\\d{2}|20\\d{2})\\s*[/.-]\\s*(?:0?[1-9]|1[0-2])\\b",
      `\\b(?:${MONTH_PATTERN})\\.?\\s*,?\\s*(?:\\d{2}|19\\d{2}|20\\d{2})\\b`,
      `\\b(?:\\d{2}|19\\d{2}|20\\d{2})\\s+(?:${MONTH_PATTERN})\\.?\\b`,
      "\\b(?:19\\d{2}|20\\d{2})\\b",
      "\\bpresent\\b",
    ].join("|"),
    "gi"
  );
}

function findCvDateTokens(value) {
  const normalized = normalizeCvDateText(value);
  const tokens = [];
  const pattern = getCvDateTokenPattern();
  let match;
  while ((match = pattern.exec(normalized))) {
    const token = cleanText(match[0]);
    const parsed = normalizeCvDatePoint(token, tokens.length > 0);
    if (!parsed) continue;
    tokens.push({
      token,
      startIndex: match.index,
      endIndex: match.index + token.length,
      parsed,
    });
  }
  return { normalized, tokens };
}

function getCvDateOrderValue(point) {
  return point && point.year && point.month ? point.year * 12 + point.month : 0;
}

function getCvDateDurationMonths(range) {
  if (!range?.start || !range?.end) return 0;
  return Math.max(
    1,
    (range.end.year - range.start.year) * 12 + (range.end.month - range.start.month) + 1
  );
}

function getCvDateConfidence(start, end, source) {
  let score = source === "explicit_start_end" ? 0.92 : 0.72;
  if (start?.precision === "month") score += 0.08;
  if (end?.precision === "month" || end?.precision === "current") score += 0.08;
  if (start?.precision === "year" || end?.precision === "year") score -= 0.12;
  if (!start || !end) score -= 0.35;
  return Math.max(0, Math.min(0.98, Number(score.toFixed(2))));
}

function looksLikeOpenEndedDateRange(value) {
  const normalized = normalizeCvDateText(value);
  return (
    /(?:^|\s)(?:from|since)\s+/i.test(normalized) ||
    /(?:-|to|until)\s*$/i.test(normalized)
  );
}

function normalizeCvDateContext(value) {
  const clean = cleanText(value).toLowerCase();
  if (/education|academic|qualification|school|university|degree|gpa/.test(clean)) {
    return "education";
  }
  if (/work|experience|employment|career|professional|job|role/.test(clean)) {
    return "experience";
  }
  return clean || "unknown";
}

function normalizeCvDateRange(input) {
  const dates = cleanText(input?.dates || input?.date || "");
  const startDate = cleanText(input?.startDate || input?.start_date || "");
  const endDate = cleanText(input?.endDate || input?.end_date || "");
  const context = normalizeCvDateContext(input?.context || input?.section || "");
  let start = startDate ? normalizeCvDatePoint(startDate, false) : null;
  let end = endDate ? normalizeCvDatePoint(endDate, true) : null;
  let rawMatched = cleanText([startDate, endDate].filter(Boolean).join(" - "));
  let source = start || end ? "explicit_start_end" : "";
  let issues = [];

  if (!start && !end) {
    const found = findCvDateTokens(dates);
    const tokens = found.tokens;
    if (!tokens.length) return null;
    source = "dates_text";
    if (tokens.length === 1) {
      start = tokens[0].parsed.isCurrent ? null : normalizeCvDatePoint(tokens[0].token, false);
      end = tokens[0].parsed.isCurrent
        ? tokens[0].parsed
        : PRESENT_DATE_PATTERN.test(found.normalized)
        ? normalizeCvDatePoint("present", true)
        : looksLikeOpenEndedDateRange(found.normalized)
        ? normalizeCvDatePoint("present", true)
        : normalizeCvDatePoint(tokens[0].token, true);
      issues.push("single_date");
      if (end?.isCurrent) issues.push("open_ended_current_role");
      rawMatched = tokens[0].token;
    } else {
      const first = tokens.find((token) => !token.parsed.isCurrent) || tokens[0];
      const last = tokens[tokens.length - 1];
      start = normalizeCvDatePoint(first.token, false);
      end = normalizeCvDatePoint(last.token, true);
      rawMatched = cleanText(found.normalized.slice(first.startIndex, last.endIndex));
    }
  } else if (start && !end) {
    end = PRESENT_DATE_PATTERN.test(dates)
      ? normalizeCvDatePoint("present", true)
      : normalizeCvDatePoint(startDate, true);
    issues.push("missing_end_date");
  } else if (!start && end && !end.isCurrent) {
    start = {
      year: end.year,
      month: Math.max(1, end.month - 3),
      precision: "inferred",
      isCurrent: false,
      raw: end.raw,
    };
    issues.push("missing_start_date");
  }

  if (!start || !end) return null;
  if (getCvDateOrderValue(end) < getCvDateOrderValue(start)) {
    const swap = start;
    start = end;
    end = swap;
    issues.push("swapped_range_order");
  }
  const durationMonths = getCvDateDurationMonths({ start, end });
  if (durationMonths > 720) issues.push("very_long_duration");
  const confidence = getCvDateConfidence(start, end, source);
  return {
    start,
    end,
    isCurrent: !!end.isCurrent,
    raw: dates || rawMatched,
    rawMatched: rawMatched || dates,
    durationMonths,
    durationYears: Number((durationMonths / 12).toFixed(1)),
    confidence: context === "education" ? Math.max(0, Number((confidence - 0.08).toFixed(2))) : confidence,
    source,
    context,
    contributesToWorkExperience: context === "experience" && confidence >= 0.42,
    issues,
  };
}

function calculateNonOverlappingExperienceMonths(entries) {
  const intervals = (Array.isArray(entries) ? entries : [])
    .filter((entry) => {
      const range = entry?.dateRange;
      if (!range?.start || !range?.end) return false;
      if (range.context === "education") return false;
      if (Number(range.confidence || entry?.dateConfidence || 0) < 0.38) return false;
      return true;
    })
    .map((entry) => entry.dateRange)
    .filter((range) => range?.start && range?.end)
    .map((range) => ({
      start: getCvDateOrderValue(range.start),
      end: getCvDateOrderValue(range.end),
    }))
    .filter((range) => range.start && range.end && range.end >= range.start)
    .sort((left, right) => left.start - right.start);
  const merged = [];
  intervals.forEach((range) => {
    const last = merged[merged.length - 1];
    if (!last || range.start > last.end + 1) {
      merged.push({ ...range });
      return;
    }
    last.end = Math.max(last.end, range.end);
  });
  return merged.reduce((sum, range) => sum + Math.max(0, range.end - range.start + 1), 0);
}

function extractDateRange(value) {
  const clean = cleanText(value);
  const range = clean.match(DATE_RANGE_REGEX);
  if (range) return cleanText(range[0]);
  const normalized = normalizeCvDateRange({ dates: clean });
  if (normalized?.rawMatched) return normalized.rawMatched;
  const single = clean.match(SINGLE_DATE_REGEX);
  return single ? cleanText(single[0]) : "";
}

function removeDateRange(value) {
  const clean = String(value || "");
  const normalized = normalizeCvDateRange({ dates: clean });
  const withoutNormalized =
    normalized?.rawMatched && normalized.rawMatched.length >= 4
      ? clean.replace(normalized.rawMatched, " ")
      : clean;
  return cleanText(withoutNormalized.replace(DATE_RANGE_REGEX, " ").replace(/\s{2,}/g, " "));
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
    const sectionTitle = cleanText(section?.title || "");
    const sectionKey = inferCanonicalSectionKey(
      cleanText(section?.key || "") || sectionTitle
    );
    let active =
      wantedPattern.test(sectionKey) ||
      wantedPattern.test(sectionTitle) ||
      wantedPattern.test(normalizeSectionHeadingText(sectionTitle));
    (Array.isArray(section?.lines) ? section.lines : []).forEach((line) => {
      const raw = String(line?.text || line || "");
      const clean = cleanText(raw);
      if (!clean) return;
      if (isEmbeddedSectionHeading(clean)) {
        const embeddedKey = inferCanonicalSectionKey(clean);
        active =
          wantedPattern.test(embeddedKey) ||
          wantedPattern.test(clean) ||
          wantedPattern.test(normalizeSectionHeadingText(clean));
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
  const lines = getLogicalSectionLines(
    sections,
    /^(?:experience|employment|work|professional_experience|career_history|work_history|experience_previous)$|experience|employment|history/i
  );
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
  const lines = getLogicalSectionLines(
    sections,
    /^(?:education|academic|qualification|qualifications|training|formation|formazione|istruzione|educacion|educación|ausbildung|bildung|contatto_formazione)$|education|academic|qualification|formation|formazione|educación|ausbildung|التعليم|المؤهلات/i
  );
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
  const lines = getLogicalSectionLines(
    sections,
    /^(?:skills|technical_skills|core_skills|key_skills|professional_skills|competencies|competences)$|skills|technical|competenc|competenze|compétences|habilidades|fähigkeiten|kenntnisse|المهارات/i
  );
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
    parserSource: cleanText(section?.parserSource || "resume-parser-ats"),
    confidence: Number(section?.confidence || 0) || 0,
  }));
}

function normalizeLayoutSections(sections) {
  return (Array.isArray(sections) ? sections : [])
    .map((section) => ({
      title: cleanText(section?.title),
      key: cleanText(section?.key),
      lines: (Array.isArray(section?.lines) ? section.lines : [])
        .map((line) => cleanText(line?.text || line))
        .filter(Boolean),
      parserSource: cleanText(section?.parserSource || "layout"),
      confidence: Number(section?.confidence || 0) || 0.62,
    }))
    .filter((section) => section.title || section.lines.length);
}

function normalizeParserExperienceEntry(entry, parserSource) {
  const role = cleanText(entry?.role || entry?.title || entry?.jobTitle);
  const company = cleanText(entry?.company);
  const dates = cleanText(entry?.dates || entry?.date);
  const startDate = cleanText(entry?.startDate || entry?.start_date);
  const endDate = cleanText(entry?.endDate || entry?.end_date);
  const location = cleanText(entry?.location);
  const bullets = normalizeStringList(entry?.bullets || entry?.descriptions || []);
  const dateRange = normalizeCvDateRange({
    dates,
    startDate,
    endDate,
    context: "experience",
  });
  const durationMonths = dateRange?.durationMonths || 0;
  return {
    type: "experience",
    role,
    title: role,
    company,
    dates,
    startDate,
    endDate,
    dateRange,
    months: durationMonths,
    years: durationMonths ? Number((durationMonths / 12).toFixed(1)) : 0,
    dateConfidence: dateRange?.confidence || 0,
    dateIssues: dateRange?.issues || [],
    location,
    bullets,
    lines: normalizeStringList(
      [role, company, dates, location].concat(entry?.lines || []).concat(bullets)
    ),
    parserSource: cleanText(entry?.parserSource || parserSource),
    confidence: Number(entry?.confidence || 0) || 0,
  };
}

function normalizeParserEducationEntry(entry, parserSource) {
  const school = cleanText(entry?.school || entry?.institution);
  const degree = cleanText(entry?.degree);
  const dates = cleanText(entry?.dates || entry?.date);
  const details = normalizeStringList(entry?.details || entry?.descriptions || []);
  const dateRange = normalizeCvDateRange({
    dates,
    context: "education",
  });
  return {
    school,
    degree,
    dates,
    gpa: cleanText(entry?.gpa),
    details,
    dateRange,
    dateConfidence: Number(dateRange?.confidence || 0) || 0,
    dateIssues: dateRange?.issues || [],
    parserSource: cleanText(entry?.parserSource || parserSource),
  };
}

function getParserSourceWeight(source) {
  const clean = cleanText(source).toLowerCase();
  if (/layout/.test(clean)) return 0.16;
  if (/pyresume|leverparser/.test(clean)) return 0.15;
  if (/resume-parser-ats/.test(clean)) return 0.12;
  if (/lines/.test(clean)) return 0.1;
  if (/ensemble/.test(clean)) return 0.08;
  return 0.06;
}

function normalizeComparableText(value) {
  return cleanText(value)
    .toLowerCase()
    .replace(/https?:\/\/\S+/g, " ")
    .replace(/[^a-z0-9+&.#]+/g, " ")
    .replace(/\s+/g, " ")
    .trim();
}

function getComparableTokens(value) {
  const stop = new Set([
    "and",
    "the",
    "for",
    "with",
    "from",
    "this",
    "that",
    "present",
    "current",
    "at",
    "in",
    "of",
    "to",
  ]);
  return normalizeComparableText(value)
    .split(/\s+/)
    .filter((token) => token.length >= 2 && !stop.has(token));
}

function getTokenSimilarity(left, right) {
  const leftTokens = new Set(getComparableTokens(left));
  const rightTokens = new Set(getComparableTokens(right));
  if (!leftTokens.size || !rightTokens.size) return 0;
  let overlap = 0;
  leftTokens.forEach((token) => {
    if (rightTokens.has(token)) overlap += 1;
  });
  return overlap / Math.max(leftTokens.size, rightTokens.size);
}

function hasSameDateEvidence(left, right) {
  const leftDate = left?.dateRange || normalizeCvDateRange(left || {});
  const rightDate = right?.dateRange || normalizeCvDateRange(right || {});
  if (!leftDate || !rightDate) return false;
  if (
    getCvDateOrderValue(leftDate.start) === getCvDateOrderValue(rightDate.start) &&
    getCvDateOrderValue(leftDate.end) === getCvDateOrderValue(rightDate.end)
  ) {
    return true;
  }
  const leftStart = getCvDateOrderValue(leftDate.start);
  const leftEnd = getCvDateOrderValue(leftDate.end);
  const rightStart = getCvDateOrderValue(rightDate.start);
  const rightEnd = getCvDateOrderValue(rightDate.end);
  return leftStart <= rightEnd && rightStart <= leftEnd;
}

function getEntryRawText(entry) {
  return cleanText(
    (entry && entry.rawText) ||
      [entry?.role || entry?.title, entry?.company, entry?.dates, entry?.location]
        .concat(entry?.bullets || [])
        .concat(entry?.lines || [])
        .join("\n")
  );
}

function looksContactLikeText(value) {
  const clean = cleanText(value);
  return /@|linkedin\.com|https?:\/\/|www\.|\b(?:email|phone|mobile|tel)\b/i.test(clean);
}

function looksEducationLikeText(value) {
  return /\b(?:university|college|school|bsc|msc|mba|phd|degree|diploma|gpa|grade|course|modules?|bachelor|master|faculty|thesis)\b/i.test(
    cleanText(value)
  );
}

function looksSkillSoupText(value) {
  const clean = cleanText(value);
  const words = clean.split(/\s+/).filter(Boolean);
  const separators = (clean.match(/[,;|]/g) || []).length;
  if (separators >= 4) return true;
  if (words.length >= 16 && separators >= 2) return true;
  return /\b(?:excel|python|sql|power\s*bi|tableau|bloomberg|factset|capital iq|matlab|vba)\b/i.test(
    clean
  ) && separators >= 2;
}

function looksOverlongStructuredField(value) {
  const clean = cleanText(value);
  const words = clean.split(/\s+/).filter(Boolean);
  return clean.length > 130 || words.length > 18;
}

function looksExperienceTitleLike(value) {
  const clean = cleanText(value);
  if (!clean || looksContactLikeText(clean) || looksEducationLikeText(clean)) return false;
  if (looksSkillSoupText(clean) || looksOverlongStructuredField(clean)) return false;
  return true;
}

function scoreParserExperienceEntry(entry) {
  const normalized = normalizeParserExperienceEntry(entry || {}, entry?.parserSource || "ensemble");
  const rawText = getEntryRawText(normalized);
  let score = 0;
  const penalties = [];
  if (normalized.role) score += looksExperienceTitleLike(normalized.role) ? 0.2 : 0.04;
  if (normalized.company) score += looksOverlongStructuredField(normalized.company) ? 0.04 : 0.16;
  if (normalized.dateRange) score += 0.18 * Number(normalized.dateRange.confidence || 0.7);
  if (normalized.bullets.length) score += Math.min(0.2, normalized.bullets.length * 0.04);
  if (normalized.lines.length) score += Math.min(0.08, normalized.lines.length * 0.01);
  score += getParserSourceWeight(normalized.parserSource);
  if (!normalized.role && !normalized.company && !normalized.bullets.length) {
    score -= 0.26;
    penalties.push("empty_experience_entry");
  }
  if (looksContactLikeText([normalized.role, normalized.company].join(" "))) {
    score -= 0.24;
    penalties.push("contact_line_penalty");
  }
  if (looksEducationLikeText([normalized.role, normalized.company].join(" "))) {
    score -= 0.2;
    penalties.push("education_line_penalty");
  }
  if (looksSkillSoupText([normalized.role, normalized.company].join(" "))) {
    score -= 0.16;
    penalties.push("skill_list_penalty");
  }
  if (looksOverlongStructuredField(normalized.role)) {
    score -= 0.12;
    penalties.push("long_title_penalty");
  }
  if (normalized.dateRange && !normalized.role && !normalized.company) {
    score -= 0.16;
    penalties.push("orphan_date_penalty");
  }
  if (normalized.bullets.some((line) => looksEducationLikeText(line))) {
    score -= 0.08;
    penalties.push("education_bullet_penalty");
  }
  normalized.rawText = rawText;
  normalized.confidence = Math.max(0, Math.min(0.98, Number(score.toFixed(2))));
  normalized.warnings = normalizeStringList([].concat(normalized.dateIssues || []).concat(penalties));
  return normalized;
}

function scoreParserEducationEntry(entry) {
  const normalized = normalizeParserEducationEntry(entry || {}, entry?.parserSource || "ensemble");
  let score = 0;
  const penalties = [];
  if (normalized.school) score += looksOverlongStructuredField(normalized.school) ? 0.05 : 0.26;
  if (normalized.degree) score += looksOverlongStructuredField(normalized.degree) ? 0.04 : 0.2;
  if (normalized.dates) score += 0.12;
  if (normalized.gpa) score += 0.08;
  if (normalized.details.length) score += Math.min(0.12, normalized.details.length * 0.03);
  score += getParserSourceWeight(normalized.parserSource);
  if (looksContactLikeText([normalized.school, normalized.degree].join(" "))) {
    score -= 0.22;
    penalties.push("contact_line_penalty");
  }
  if (looksSkillSoupText([normalized.school, normalized.degree].join(" "))) {
    score -= 0.16;
    penalties.push("skill_list_penalty");
  }
  if (!normalized.school && !normalized.degree && !normalized.details.length) {
    score -= 0.2;
    penalties.push("empty_education_entry");
  }
  normalized.rawText = cleanText(
    [normalized.school, normalized.degree, normalized.dates, normalized.gpa]
      .concat(normalized.details || [])
      .join("\n")
  );
  normalized.confidence = Math.max(0, Math.min(0.98, Number(score.toFixed(2))));
  normalized.warnings = normalizeStringList(penalties);
  return normalized;
}

function entriesLikelySameExperience(left, right) {
  if (!left || !right) return false;
  const dateOverlap = hasSameDateEvidence(left, right);
  const roleSimilarity = getTokenSimilarity(left.role || left.title, right.role || right.title);
  const companySimilarity = getTokenSimilarity(left.company, right.company);
  if (dateOverlap && (roleSimilarity >= 0.28 || companySimilarity >= 0.34)) return true;
  if (dateOverlap && !left.company && !right.company && roleSimilarity >= 0.5) return true;
  if (companySimilarity >= 0.72 && (dateOverlap || roleSimilarity >= 0.24)) return true;
  if (roleSimilarity >= 0.72 && dateOverlap) return true;
  return false;
}

function entriesLikelySameEducation(left, right) {
  if (!left || !right) return false;
  const schoolSimilarity = getTokenSimilarity(left.school, right.school);
  const degreeSimilarity = getTokenSimilarity(left.degree, right.degree);
  const dateOverlap = left.dates && right.dates && hasSameDateEvidence(left, right);
  if (schoolSimilarity >= 0.55) return true;
  if (degreeSimilarity >= 0.6 && dateOverlap) return true;
  if (dateOverlap && schoolSimilarity >= 0.25 && degreeSimilarity >= 0.25) return true;
  return false;
}

function pickBestExperienceField(candidates, field) {
  let best = "";
  let bestScore = -1;
  candidates.forEach((candidate) => {
    const value = cleanText(candidate?.[field]);
    if (!value) return;
    let score = Number(candidate.confidence || 0) + getParserSourceWeight(candidate.parserSource);
    if (field === "role") {
      if (looksExperienceTitleLike(value)) score += 0.2;
      if (looksOverlongStructuredField(value) || looksSkillSoupText(value)) score -= 0.24;
    }
    if (field === "company") {
      if (looksOverlongStructuredField(value) || looksEducationLikeText(value)) score -= 0.22;
      if (/\b(?:ltd|llc|plc|inc|corp|group|capital|bank|company|consulting|partners|s\.?p\.?a\.?)\b/i.test(value)) {
        score += 0.08;
      }
    }
    if (field === "location" && looksContactLikeText(value)) score -= 0.2;
    if (score > bestScore) {
      best = value;
      bestScore = score;
    }
  });
  return best;
}

function pickBestDateCandidate(candidates) {
  let best = null;
  let bestScore = -1;
  candidates.forEach((candidate) => {
    if (!candidate?.dateRange && !candidate?.dates && !candidate?.startDate && !candidate?.endDate) {
      return;
    }
    const range =
      candidate.dateRange ||
      normalizeCvDateRange({
        dates: candidate.dates,
        startDate: candidate.startDate,
        endDate: candidate.endDate,
      });
    let score = Number(range?.confidence || 0) + Number(candidate.confidence || 0) * 0.2;
    if (range?.durationMonths) score += Math.min(0.08, range.durationMonths / 240);
    if (candidate.dates) score += Math.min(0.04, cleanText(candidate.dates).length / 120);
    if (score > bestScore) {
      best = { ...candidate, dateRange: range };
      bestScore = score;
    }
  });
  return best;
}

function mergeExperienceCandidateGroup(candidates) {
  const sorted = candidates.slice().sort((left, right) => {
    const rightScore = Number(right.confidence || 0) + getParserSourceWeight(right.parserSource);
    const leftScore = Number(left.confidence || 0) + getParserSourceWeight(left.parserSource);
    return rightScore - leftScore;
  });
  const dateCandidate = pickBestDateCandidate(sorted) || sorted[0] || {};
  const dateRange = dateCandidate.dateRange || null;
  const bullets = normalizeStringList(
    sorted.reduce((list, candidate) => list.concat(candidate.bullets || []), [])
  );
  const lines = normalizeStringList(
    sorted.reduce((list, candidate) => list.concat(candidate.lines || []), [])
  );
  const confidence = Math.max(
    0,
    Math.min(
      0.98,
      Number(
        (
          sorted.reduce((max, candidate) => Math.max(max, Number(candidate.confidence || 0)), 0) +
          Math.min(0.12, (sorted.length - 1) * 0.04)
        ).toFixed(2)
      )
    )
  );
  const role = pickBestExperienceField(sorted, "role");
  const company = pickBestExperienceField(sorted, "company");
  return {
    type: "experience",
    role,
    title: role,
    company,
    dates: cleanText(dateCandidate.dates || dateRange?.raw || ""),
    startDate: cleanText(dateCandidate.startDate || ""),
    endDate: cleanText(dateCandidate.endDate || ""),
    dateRange,
    months: Number(dateRange?.durationMonths || 0) || 0,
    years: dateRange?.durationMonths
      ? Number((Number(dateRange.durationMonths || 0) / 12).toFixed(1))
      : 0,
    dateConfidence: Number(dateRange?.confidence || 0) || 0,
    dateIssues: normalizeStringList(sorted.reduce((list, candidate) => list.concat(candidate.dateIssues || []), [])),
    location: pickBestExperienceField(sorted, "location"),
    bullets,
    lines,
    parserSource: "ensemble",
    confidence,
    rawText: normalizeStringList(sorted.map(getEntryRawText)).join("\n\n"),
    sourceCandidates: sorted.map((candidate) => ({
      parserSource: candidate.parserSource,
      confidence: candidate.confidence,
      role: candidate.role,
      company: candidate.company,
      dates: candidate.dates,
    })),
    warnings: normalizeStringList(sorted.reduce((list, candidate) => list.concat(candidate.warnings || []), [])),
  };
}

function mergeEducationCandidateGroup(candidates) {
  const sorted = candidates.slice().sort((left, right) => {
    const rightScore = Number(right.confidence || 0) + getParserSourceWeight(right.parserSource);
    const leftScore = Number(left.confidence || 0) + getParserSourceWeight(left.parserSource);
    return rightScore - leftScore;
  });
  const details = normalizeStringList(
    sorted.reduce((list, candidate) => list.concat(candidate.details || []), [])
  );
  const pickField = (field) => {
    let best = "";
    let bestScore = -1;
    sorted.forEach((candidate) => {
      const value = cleanText(candidate?.[field]);
      if (!value) return;
      let score = Number(candidate.confidence || 0) + getParserSourceWeight(candidate.parserSource);
      if (looksContactLikeText(value) || looksSkillSoupText(value)) score -= 0.2;
      if (field === "school" && looksEducationLikeText(value)) score += 0.08;
      if (score > bestScore) {
        best = value;
        bestScore = score;
      }
    });
    return best;
  };
  const bestDates = pickBestDateCandidate(sorted);
  const confidence = Math.max(
    0,
    Math.min(
      0.98,
      Number(
        (
          sorted.reduce((max, candidate) => Math.max(max, Number(candidate.confidence || 0)), 0) +
          Math.min(0.1, (sorted.length - 1) * 0.035)
        ).toFixed(2)
      )
    )
  );
  return {
    school: pickField("school"),
    degree: pickField("degree"),
    dates: cleanText(bestDates?.dates || bestDates?.dateRange?.raw || pickField("dates")),
    gpa: pickField("gpa"),
    details,
    dateRange: bestDates?.dateRange || null,
    dateConfidence: Number(bestDates?.dateRange?.confidence || bestDates?.dateConfidence || 0) || 0,
    dateIssues: normalizeStringList(sorted.reduce((list, candidate) => list.concat(candidate.dateIssues || []), [])),
    parserSource: "ensemble",
    confidence,
    rawText: normalizeStringList(sorted.map((candidate) => candidate.rawText)).join("\n\n"),
    sourceCandidates: sorted.map((candidate) => ({
      parserSource: candidate.parserSource,
      confidence: candidate.confidence,
      school: candidate.school,
      degree: candidate.degree,
      dates: candidate.dates,
    })),
    warnings: normalizeStringList(sorted.reduce((list, candidate) => list.concat(candidate.warnings || []), [])),
  };
}

function flattenEntryLists(lists) {
  return lists.reduce((out, entries) => out.concat(Array.isArray(entries) ? entries : []), []);
}

function mergeParserEntriesFromSources(entryLists) {
  const candidates = flattenEntryLists(entryLists)
    .map((entry) => scoreParserExperienceEntry(entry))
    .filter((entry) => entry.confidence >= 0.24 || entry.role || entry.company || entry.bullets.length);
  const groups = [];
  candidates.forEach((candidate) => {
    let group = groups.find((item) =>
      item.some((existing) => entriesLikelySameExperience(existing, candidate))
    );
    if (!group) {
      group = [];
      groups.push(group);
    }
    group.push(candidate);
  });
  return groups
    .map(mergeExperienceCandidateGroup)
    .filter((entry) => entry.confidence >= 0.28 && (entry.role || entry.company || entry.bullets.length))
    .sort((left, right) => {
      const leftCurrent = left.dateRange?.isCurrent ? 1 : 0;
      const rightCurrent = right.dateRange?.isCurrent ? 1 : 0;
      if (leftCurrent !== rightCurrent) return rightCurrent - leftCurrent;
      const leftEnd = getCvDateOrderValue(left.dateRange?.end);
      const rightEnd = getCvDateOrderValue(right.dateRange?.end);
      if (leftEnd !== rightEnd) return rightEnd - leftEnd;
      return Number(right.confidence || 0) - Number(left.confidence || 0);
    });
}

function mergeEducationEntriesFromSources(entryLists) {
  const candidates = flattenEntryLists(entryLists)
    .map((entry) => scoreParserEducationEntry(entry))
    .filter((entry) => entry.confidence >= 0.22 || entry.school || entry.degree || entry.details.length);
  const groups = [];
  candidates.forEach((candidate) => {
    let group = groups.find((item) =>
      item.some((existing) => entriesLikelySameEducation(existing, candidate))
    );
    if (!group) {
      group = [];
      groups.push(group);
    }
    group.push(candidate);
  });
  return groups
    .map(mergeEducationCandidateGroup)
    .filter((entry) => entry.confidence >= 0.26 && (entry.school || entry.degree || entry.details.length))
    .sort((left, right) => Number(right.confidence || 0) - Number(left.confidence || 0));
}

function mergeParserEntries(primary, secondary, parserSource) {
  return mergeParserEntriesFromSources([
    Array.isArray(primary) ? primary : [],
    (Array.isArray(secondary) ? secondary : []).map((entry) => ({
      ...entry,
      parserSource: entry?.parserSource || parserSource,
    })),
  ]);
}

function mergeEducationEntries(primary, secondary, parserSource) {
  return mergeEducationEntriesFromSources([
    Array.isArray(primary) ? primary : [],
    (Array.isArray(secondary) ? secondary : []).map((entry) => ({
      ...entry,
      parserSource: entry?.parserSource || parserSource,
    })),
  ]);
}

function buildExperienceTimelineSummary(entries, pyresumeYears) {
  const timeline = (Array.isArray(entries) ? entries : [])
    .filter((entry) => entry && (entry.role || entry.company || entry.dates))
    .map((entry) => ({
      role: entry.role || entry.title || "",
      company: entry.company || "",
      dates: entry.dates || "",
      startDate: entry.startDate || "",
      endDate: entry.endDate || "",
      dateRange: entry.dateRange || null,
      durationMonths: Number(entry.months || 0) || 0,
      durationYears: Number(entry.years || 0) || 0,
      isCurrent: !!(entry.dateRange && entry.dateRange.isCurrent),
      dateConfidence: Number(entry.dateConfidence || 0) || 0,
      parserSource: entry.parserSource || "",
    }))
    .sort((left, right) => {
      const leftCurrent = left.isCurrent ? 1 : 0;
      const rightCurrent = right.isCurrent ? 1 : 0;
      if (leftCurrent !== rightCurrent) return rightCurrent - leftCurrent;
      const leftEnd = getCvDateOrderValue(left.dateRange?.end);
      const rightEnd = getCvDateOrderValue(right.dateRange?.end);
      return rightEnd - leftEnd;
    });
  const totalMonths = calculateNonOverlappingExperienceMonths(timeline);
  const current = timeline.find((entry) => entry.isCurrent) || timeline[0] || null;
  return {
    timeline,
    totalExperienceMonths: totalMonths,
    totalExperienceYears: totalMonths
      ? Number((totalMonths / 12).toFixed(1))
      : Number(pyresumeYears || 0) || 0,
    currentRoleMonths: current?.isCurrent ? Number(current.durationMonths || 0) || 0 : 0,
    datedEntryCount: timeline.filter((entry) => entry.dateRange).length,
    dateConfidence:
      timeline.length && timeline.some((entry) => entry.dateRange)
        ? Number(
            (
              timeline.reduce((sum, entry) => sum + Number(entry.dateConfidence || 0), 0) /
              timeline.length
            ).toFixed(2)
          )
        : 0,
  };
}

function normalizeCanonicalSource(source, fallback) {
  return cleanText(source || fallback || "raw") || "raw";
}

function inferCanonicalSectionKey(title) {
  const direct = inferSectionKeyFromHeading(title);
  if (direct) return direct;
  const clean = normalizeSectionHeadingText(title);
  if (!clean) return "unknown";
  if (/\b(?:profile|summary|objective|about|professional summary)\b/.test(clean)) {
    return "summary";
  }
  if (
    /\b(?:experience|employment|history|career|work|professional experience|esperienza|experiencia|expérience)\b/.test(
      clean
    )
  ) {
    return "experience";
  }
  if (
    /\b(?:education|academic|qualification|formazione|formation|educación|ausbildung|contatto_formazione)\b/.test(
      clean
    )
  ) {
    return "education";
  }
  if (
    /\b(?:skills|technical|competenc|competenze|competences|habilidades|fähigkeiten)\b/.test(
      clean
    )
  ) {
    return "skills";
  }
  if (/\b(?:language|languages|lingue|langues|idiomas|sprachen)\b/.test(clean)) {
    return "languages";
  }
  if (/\b(?:project|projects|publication|publications)\b/.test(clean)) {
    return "projects";
  }
  if (/\b(?:certification|certificate|license|licence)\b/.test(clean)) {
    return "certifications";
  }
  return clean.replace(/[^a-z0-9]+/g, "_").replace(/^_+|_+$/g, "") || "unknown";
}

function scoreCanonicalExperienceEntry(entry) {
  let score = 0.08;
  if (cleanText(entry?.role || entry?.title)) score += 0.2;
  if (cleanText(entry?.company)) score += 0.18;
  if (entry?.dateRange) score += 0.18 * Number(entry.dateRange.confidence || 0.7);
  if ((entry?.bullets || []).length) score += Math.min(0.22, entry.bullets.length * 0.055);
  if ((entry?.lines || []).length) score += 0.06;
  if (/ensemble|pyresume|resume-parser/i.test(cleanText(entry?.parserSource))) score += 0.08;
  if (!cleanText(entry?.role || entry?.title) && !cleanText(entry?.company)) score -= 0.22;
  if ((entry?.bullets || []).some((line) => /\b(?:university|school|gpa|degree)\b/i.test(line))) {
    score -= 0.16;
  }
  return Math.max(0, Math.min(0.98, Number(score.toFixed(2))));
}

function scoreCanonicalEducationEntry(entry) {
  let score = 0.08;
  if (cleanText(entry?.school)) score += 0.28;
  if (cleanText(entry?.degree)) score += 0.22;
  if (cleanText(entry?.dates)) score += 0.12;
  if ((entry?.details || []).length) score += 0.08;
  if (/resume-parser|ensemble|pyresume/i.test(cleanText(entry?.parserSource))) score += 0.08;
  return Math.max(0, Math.min(0.98, Number(score.toFixed(2))));
}

function canonicalizeExperienceEntry(entry, index) {
  const normalized = normalizeParserExperienceEntry(entry || {}, entry?.parserSource || "ensemble");
  const confidence = Number(entry?.confidence || 0) || scoreCanonicalExperienceEntry(normalized);
  const rawText = cleanText(
    (entry && entry.rawText) ||
      [normalized.role, normalized.company, normalized.dates, normalized.location]
        .concat(normalized.bullets || [])
        .concat(normalized.lines || [])
        .join("\n")
  );
  return {
    id: `experience-${index + 1}`,
    type: "experience",
    title: normalized.role,
    role: normalized.role,
    company: normalized.company,
    location: normalized.location,
    dates: normalized.dates,
    startDate: normalized.startDate,
    endDate: normalized.endDate,
    isCurrent: !!(normalized.dateRange && normalized.dateRange.isCurrent),
    durationMonths: Number(normalized.months || 0) || 0,
    durationYears: Number(normalized.years || 0) || 0,
    bullets: normalized.bullets || [],
    lines: normalized.lines || [],
    rawText,
    confidence,
    source: normalizeCanonicalSource(normalized.parserSource, "ensemble"),
    warnings: (normalized.dateIssues || []).slice(0, 6),
    dateRange: normalized.dateRange || null,
  };
}

function canonicalizeEducationEntry(entry, index) {
  const normalized = normalizeParserEducationEntry(entry || {}, entry?.parserSource || "ensemble");
  const confidence = Number(entry?.confidence || 0) || scoreCanonicalEducationEntry(normalized);
  return {
    id: `education-${index + 1}`,
    type: "education",
    school: normalized.school,
    degree: normalized.degree,
    dates: normalized.dates,
    gpa: normalized.gpa,
    details: normalized.details || [],
    rawText: cleanText(
      [normalized.school, normalized.degree, normalized.dates, normalized.gpa]
        .concat(normalized.details || [])
        .join("\n")
    ),
    confidence,
    source: normalizeCanonicalSource(normalized.parserSource, "ensemble"),
    warnings: (normalized.dateIssues || []).slice(0, 6),
    dateRange: normalized.dateRange || null,
    dateConfidence: Number(normalized.dateConfidence || 0) || 0,
  };
}

function canonicalizeSections(structured) {
  return (Array.isArray(structured?.sections) ? structured.sections : [])
    .map((section, index) => {
      const title = cleanText(section?.title || section?.key || "");
      const lines = normalizeStringList(section?.lines || section?.items || []);
      return {
        id: `section-${index + 1}`,
        key: cleanText(section?.key) || inferCanonicalSectionKey(title),
        title,
        lines,
        confidence:
          Number(section?.confidence || 0) ||
          (title && lines.length ? 0.78 : lines.length ? 0.48 : 0.2),
        source: normalizeCanonicalSource(section?.parserSource, "resume-parser-ats"),
      };
    })
    .filter((section) => section.title || section.lines.length);
}

function buildRawFallbackSections(structured, normalized) {
  const sections = canonicalizeSections(structured);
  const structuredKeys = new Set(
    []
      .concat((structured?.experience || []).length ? ["experience"] : [])
      .concat((structured?.education || []).length ? ["education"] : [])
      .concat((structured?.skills || []).length ? ["skills"] : [])
  );
  const fallbacks = sections
    .filter((section) => section.lines.length)
    .filter((section) => section.key === "unknown" || !structuredKeys.has(section.key))
    .map((section) => ({
      key: section.key,
      title: section.title || section.key,
      text: section.lines.join("\n"),
      lines: section.lines,
      confidence: Math.min(0.72, section.confidence || 0.5),
      source: section.source || "raw",
    }));
  if (!fallbacks.length && cleanText(normalized?.text)) {
    const lines = cleanText(normalized.text)
      .split(/\n+/)
      .map(cleanText)
      .filter(Boolean)
      .slice(0, 160);
    if (lines.length) {
      fallbacks.push({
        key: "raw",
        title: "Original CV Text",
        text: lines.join("\n"),
        lines,
        confidence: 0.36,
        source: "liteparse-text",
      });
    }
  }
  return fallbacks;
}

function getCanonicalQualityMode(score, experience, education, rawFallbackSections) {
  if (score >= 0.78 && experience.length && education.length) return "structured";
  if (experience.length || education.length) return "partial_structured";
  if ((rawFallbackSections || []).length) return "raw_fallback";
  return "text_only";
}

function buildCanonicalResumeModel(normalized, structured) {
  const source = structured || {};
  const experience = (Array.isArray(source.experience) ? source.experience : [])
    .map(canonicalizeExperienceEntry)
    .filter((entry) => entry.role || entry.company || entry.bullets.length || entry.rawText);
  const education = (Array.isArray(source.education) ? source.education : [])
    .map(canonicalizeEducationEntry)
    .filter((entry) => entry.school || entry.degree || entry.details.length || entry.rawText);
  const skills = sanitizeStructuredSkills(source.skills || []).map((skill, index) => ({
    id: `skill-${index + 1}`,
    label: skill,
    confidence: 0.72,
    source: "ensemble",
  }));
  const sections = canonicalizeSections(source);
  const rawFallbackSections = buildRawFallbackSections(source, normalized);
  const experienceSummary = buildExperienceTimelineSummary(
    experience.map((entry) => ({
      role: entry.role,
      company: entry.company,
      dates: entry.dates,
      dateRange: entry.dateRange,
      months: entry.durationMonths,
      years: entry.durationYears,
      parserSource: entry.source,
    })),
    source?.metadata?.yearsExperience
  );
  const confidenceValues = []
    .concat(experience.map((entry) => entry.confidence))
    .concat(education.map((entry) => entry.confidence))
    .concat(sections.map((section) => section.confidence))
    .filter((value) => Number.isFinite(Number(value)));
  const score = confidenceValues.length
    ? Number(
        (
          confidenceValues.reduce((sum, value) => sum + Number(value || 0), 0) /
          confidenceValues.length
        ).toFixed(2)
      )
    : rawFallbackSections.length
    ? 0.42
    : 0.2;
  const warnings = [];
  if (!experience.length) warnings.push("experience_not_structured");
  if (!education.length) warnings.push("education_not_structured");
  if (!skills.length) warnings.push("skills_not_structured");
  return {
    ok: source?.ok !== false,
    parser: cleanText(source?.parser || "resume-parser-ats+pyresume"),
    quality: {
      score,
      mode: getCanonicalQualityMode(score, experience, education, rawFallbackSections),
      warnings,
    },
    profile: {
      ...(source.profile || {}),
      headline: cleanText(
        source.profile?.headline ||
          experience[0]?.role ||
          source.profile?.summary ||
          ""
      ),
    },
    summary: {
      text: cleanText(source.profile?.summary || ""),
      confidence: source.profile?.summary ? 0.76 : 0,
      source: "profile",
    },
    sections,
    experience,
    education,
    skills,
    languages: [],
    projects: Array.isArray(source.projects) ? source.projects : [],
    rawFallbackSections,
    diagnostics: {
      pageCount: Number(normalized?.totalPages || 0) || 0,
      ocrUsed: process.env.LITEPARSE_OCR_ENABLED !== "0",
      layoutMode: cleanText(source?.metadata?.layout?.layoutMode || "text_order"),
      layoutParser:
        source?.metadata?.layout && typeof source.metadata.layout === "object"
          ? {
              ok: !!source.metadata.layout.ok,
              mode: cleanText(source.metadata.layout.layoutMode || "text_order"),
              lineCount: Number(source.metadata.layout.lineCount || 0) || 0,
              sectionCount: Number(source.metadata.layout.sectionCount || 0) || 0,
              error: cleanText(source.metadata.layout.error || ""),
            }
          : null,
      parserSources: (source.parsers || []).map((item) => ({
        parser: cleanText(item?.parser),
        ok: !!item?.ok,
        error: cleanText(item?.error),
      })),
      totalExperienceMonths: experienceSummary.totalExperienceMonths,
      totalExperienceYears: experienceSummary.totalExperienceYears,
      currentRoleMonths: experienceSummary.currentRoleMonths,
      datedExperienceCount: experienceSummary.datedEntryCount,
      dateConfidence: experienceSummary.dateConfidence,
    },
  };
}

function mergeProfiles(primary, secondary) {
  const left = primary || {};
  const right = secondary || {};
  return {
    name: cleanText(left.name || right.name),
    email: cleanText(left.email || right.email),
    phone: cleanText(left.phone || right.phone),
    linkedin: cleanText(left.linkedin || right.linkedin),
    location: cleanText(left.location || right.location),
    website: cleanText(left.website || right.website),
    summary: cleanText(left.summary || right.summary),
  };
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

async function parsePyresumeFromPath(tempPath) {
  if (!pyresumeEnabled) {
    return {
      ok: false,
      parser: "pyresume",
      error: "disabled",
    };
  }
  try {
    const bridgePath = path.join(process.cwd(), "pyresume_bridge.py");
    const { stdout } = await execFileAsync("python3", [bridgePath, tempPath], {
      timeout: pyresumeTimeoutMs,
      maxBuffer: 4 * 1024 * 1024,
    });
    const parsed = JSON.parse(String(stdout || "{}"));
    return parsed && typeof parsed === "object"
      ? parsed
      : { ok: false, parser: "pyresume", error: "empty_response" };
  } catch (error) {
    return {
      ok: false,
      parser: "pyresume",
      error: "pyresume_exec_failed",
      message: error && error.message ? error.message : String(error),
    };
  }
}

async function parseLayoutFromPath(tempPath) {
  if (!layoutParseEnabled) {
    return {
      ok: false,
      parser: "layout",
      error: "disabled",
    };
  }
  try {
    const bridgePath = path.join(process.cwd(), "layout_bridge.py");
    const { stdout } = await execFileAsync("python3", [bridgePath, tempPath], {
      timeout: layoutParseTimeoutMs,
      maxBuffer: 10 * 1024 * 1024,
    });
    const parsed = JSON.parse(String(stdout || "{}"));
    return parsed && typeof parsed === "object"
      ? parsed
      : { ok: false, parser: "layout", error: "empty_response" };
  } catch (error) {
    return {
      ok: false,
      parser: "layout",
      error: "layout_exec_failed",
      message: error && error.message ? error.message : String(error),
    };
  }
}

async function parseStructuredResumeEnsembleFromUpload(file, fallbackText) {
  if (!file?.buffer) {
    return null;
  }
  const extension = path.extname(file.originalname || "") || ".pdf";
  const tempPath = path.join(
    os.tmpdir(),
    `senna-resume-ensemble-${Date.now()}-${randomUUID()}${extension}`
  );
  try {
    await writeFile(tempPath, file.buffer);
    const [atsResult, pyresumeResult, layoutResult] = await Promise.all([
      parseResumeAsync({ filePath: tempPath })
        .then((parsed) => normalizeStructuredResume(parsed, fallbackText))
        .catch((error) => ({
          ok: false,
          parser: "resume-parser-ats",
          error: "structured_parse_failed",
          message: error && error.message ? error.message : String(error),
        })),
      parsePyresumeFromPath(tempPath),
      parseLayoutFromPath(tempPath),
    ]);
    const atsOk = !!(atsResult && atsResult.ok);
    const pyresumeOk = !!(pyresumeResult && pyresumeResult.ok);
    const layoutOk = !!(layoutResult && layoutResult.ok);
    const atsSections = atsOk ? atsResult.sections || [] : [];
    const layoutSections = layoutOk
      ? normalizeLayoutSections(layoutResult.sections || [])
      : [];
    const sections =
      layoutSections.length >= Math.min(2, atsSections.length || 2)
        ? layoutSections
        : atsSections;
    const layoutExperience = layoutSections.length
      ? parseStructuredExperienceFromSections(layoutSections).map((entry) => ({
          ...entry,
          parserSource: "layout",
        }))
      : [];
    const layoutEducation = layoutSections.length
      ? parseStructuredEducationFromSections(layoutSections).map((entry) => ({
          ...entry,
          parserSource: "layout",
        }))
      : [];
    const layoutSkills = layoutSections.length
      ? parseStructuredSkillsFromSections(layoutSections)
      : [];
    const profile = mergeProfiles(
      atsOk ? atsResult.profile : {},
      pyresumeOk ? pyresumeResult.profile : {}
    );
    const experience = mergeParserEntriesFromSources([
      layoutExperience,
      atsOk ? atsResult.experience || [] : [],
      pyresumeOk ? pyresumeResult.experience || [] : [],
    ]);
    const experienceSummary = buildExperienceTimelineSummary(
      experience,
      pyresumeResult?.metadata?.yearsExperience
    );
    const education = mergeEducationEntriesFromSources([
      layoutEducation,
      atsOk ? atsResult.education || [] : [],
      pyresumeOk ? pyresumeResult.education || [] : [],
    ]);
    const skills = sanitizeStructuredSkills(
      normalizeStringList([])
        .concat(layoutOk ? layoutSkills || [] : [])
        .concat(atsOk ? atsResult.skills || [] : [])
        .concat(pyresumeOk ? pyresumeResult.skills || [] : [])
    );
    return {
      ok: atsOk || pyresumeOk || layoutOk,
      parser:
        [
          atsOk ? "resume-parser-ats" : "",
          pyresumeOk ? "pyresume" : "",
          layoutOk ? "layout" : "",
        ]
          .filter(Boolean)
          .join("+") || "resume-parser-ats",
      profile,
      sections,
      experience,
      experienceTimeline: experienceSummary.timeline,
      education,
      skills,
      projects: atsOk ? atsResult.projects || [] : [],
      lines: layoutOk
        ? normalizeStringList((layoutResult.lines || []).map((line) => line?.text || line))
        : atsOk
        ? atsResult.lines || []
        : [],
      metadata: {
        ats: atsResult?.metadata || {},
        pyresume: pyresumeResult?.metadata || {},
        layout: {
          ok: layoutOk,
          error: layoutResult?.error || "",
          message: layoutResult?.message || "",
          layoutMode: layoutResult?.layoutMode || "text_order",
          lineCount: Number(layoutResult?.lineCount || 0) || 0,
          sectionCount: Array.isArray(layoutResult?.sections)
            ? layoutResult.sections.length
            : 0,
          pageCount: Array.isArray(layoutResult?.pages) ? layoutResult.pages.length : 0,
          source: layoutResult?.metadata?.source || "",
        },
        yearsExperience:
          experienceSummary.totalExperienceYears ||
          Number(pyresumeResult?.metadata?.yearsExperience || 0) ||
          0,
        totalExperienceMonths: experienceSummary.totalExperienceMonths,
        totalExperienceYears: experienceSummary.totalExperienceYears,
        currentRoleMonths: experienceSummary.currentRoleMonths,
        datedExperienceCount: experienceSummary.datedEntryCount,
        dateConfidence: experienceSummary.dateConfidence,
      },
      parsers: [
        {
          parser: "resume-parser-ats",
          ok: atsOk,
          error: atsResult?.error || "",
          message: atsResult?.message || "",
          experienceCount: Array.isArray(atsResult?.experience)
            ? atsResult.experience.length
            : 0,
        },
        {
          parser: "pyresume",
          ok: pyresumeOk,
          error: pyresumeResult?.error || "",
          message: pyresumeResult?.message || "",
          experienceCount: Array.isArray(pyresumeResult?.experience)
            ? pyresumeResult.experience.length
            : 0,
        },
        {
          parser: "layout",
          ok: layoutOk,
          error: layoutResult?.error || "",
          message: layoutResult?.message || "",
          layoutMode: layoutResult?.layoutMode || "",
          sectionCount: Array.isArray(layoutResult?.sections)
            ? layoutResult.sections.length
            : 0,
          lineCount: Number(layoutResult?.lineCount || 0) || 0,
        },
      ],
      ensemble: {
        ats: atsResult || null,
        pyresume: pyresumeResult || null,
        layout: layoutResult || null,
      },
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
app.use(express.json({ limit: "1mb" }));

let skillExtractorModulePromise = null;
let skillExtractorInstancePromise = null;
const skillExtractorTimeoutMs = Math.max(
  1000,
  Number(process.env.SKILL_EXTRACTOR_TIMEOUT_MS || 6500)
);

function withTimeout(promise, timeoutMs, label) {
  let timer = null;
  return Promise.race([
    promise,
    new Promise((_, reject) => {
      timer = setTimeout(() => {
        reject(new Error(label || "operation_timeout"));
      }, timeoutMs);
    }),
  ]).finally(() => {
    if (timer) clearTimeout(timer);
  });
}

function getSkillExtractorModule() {
  if (!skillExtractorModulePromise) {
    skillExtractorModulePromise = import("skill-extractor");
  }
  return skillExtractorModulePromise;
}

async function getSkillExtractorInstance() {
  if (!skillExtractorInstancePromise) {
    skillExtractorInstancePromise = getSkillExtractorModule().then((module) => {
      if (!module || !module.SkillExtractor) {
        throw new Error("skill_extractor_missing_export");
      }
      return new module.SkillExtractor({
        quantized: process.env.SKILL_EXTRACTOR_QUANTIZED === "1",
      });
    });
  }
  return skillExtractorInstancePromise;
}

function normalizeSkillLabel(value) {
  return cleanText(value)
    .toLowerCase()
    .replace(/[’']/g, "")
    .replace(/&/g, " and ")
    .replace(/[^a-z0-9+#./\s-]+/g, " ")
    .replace(/\s+/g, " ")
    .trim();
}

function dedupeSkillLabels(values) {
  const seen = new Set();
  return (Array.isArray(values) ? values : [])
    .map(normalizeSkillLabel)
    .filter(Boolean)
    .filter((value) => {
      if (seen.has(value)) return false;
      seen.add(value);
      return true;
    });
}

function getStructuredCvSkillText(structured) {
  const source = structured || {};
  const experience = Array.isArray(source.experience) ? source.experience : [];
  const education = Array.isArray(source.education) ? source.education : [];
  const projects = Array.isArray(source.projects) ? source.projects : [];
  return cleanText(
    []
      .concat(source.skills || [])
      .concat(source.profile?.summary || "")
      .concat(
        experience.map((entry) =>
          [
            entry?.role,
            entry?.title,
            entry?.company,
            ...(Array.isArray(entry?.bullets) ? entry.bullets : []),
          ].join(" ")
        )
      )
      .concat(
        education.map((entry) =>
          [entry?.degree, entry?.school, ...(Array.isArray(entry?.details) ? entry.details : [])].join(
            " "
          )
        )
      )
      .concat(
        projects.map((entry) =>
          [entry?.name, ...(Array.isArray(entry?.bullets) ? entry.bullets : [])].join(" ")
        )
      )
      .join(" ")
  );
}

function getStructuredCvYears(structured) {
  const metadata = structured?.metadata || {};
  return (
    Number(metadata.totalExperienceYears || 0) ||
    Number(metadata.yearsExperience || 0) ||
    Number(metadata.total_experience_years || 0) ||
    0
  );
}

async function extractHrSkills(text, options = {}) {
  const clean = cleanText(text).slice(0, Number(options.maxLength || 45000));
  if (!clean) return { skills: [], engine: "none", error: "" };
  try {
    const extractor = await withTimeout(
      getSkillExtractorInstance(),
      skillExtractorTimeoutMs,
      "skill_extractor_load_timeout"
    );
    const skills = await withTimeout(
      extractor.extract(clean, Number(options.threshold || 0.5)),
      skillExtractorTimeoutMs,
      "skill_extractor_timeout"
    );
    return { skills: dedupeSkillLabels(skills), engine: "skill-extractor", error: "" };
  } catch (error) {
    try {
      const module = await getSkillExtractorModule();
      const extractor = new module.SkillExtractor({
        quantized: process.env.SKILL_EXTRACTOR_QUANTIZED === "1",
      });
      const candidates = extractor.candidates(clean).map((candidate) => candidate.skill);
      return {
        skills: dedupeSkillLabels(candidates).slice(0, 80),
        engine: "skill-extractor-candidates",
        error: error && error.message ? error.message : String(error),
      };
    } catch (fallbackError) {
      return {
        skills: [],
        engine: "failed",
        error:
          (fallbackError && fallbackError.message ? fallbackError.message : String(fallbackError)) ||
          (error && error.message ? error.message : String(error)),
      };
    }
  }
}

function extractJobExperienceRequirement(text) {
  const clean = cleanText(text).toLowerCase();
  const patterns = [
    /\b(?:minimum|min\.?|at least|no less than|over|more than)\s+(\d{1,2})\+?\s*(?:years?|yrs?)\b.{0,90}\b(?:experience|exp)\b/i,
    /\b(\d{1,2})\s*[-–—]\s*(\d{1,2})\+?\s*(?:years?|yrs?)\b.{0,90}\b(?:experience|exp)\b/i,
    /\b(\d{1,2})\+?\s*(?:years?|yrs?)\b.{0,90}\b(?:experience|exp)\b/i,
    /\b(\d{1,2})\+?\s*(?:years?|yrs?)\b.{0,60}\b(?:required|requirement|minimum|min\.?|mandatory|essential|needed)\b/i,
    /\b(?:experience|exp)\b.{0,60}\b(?:of|:)?\s*(\d{1,2})\+?\s*(?:years?|yrs?)\b/i,
  ];
  let best = { min: 0, max: 0, required: false, preferred: false, confidence: 0, raw: "" };
  patterns.forEach((pattern) => {
    const match = clean.match(pattern);
    if (!match) return;
    const min = Number(match[1] || 0) || 0;
    const max = Number(match[2] || 0) || min;
    if (!min || min > 40) return;
    const raw = cleanText(match[0] || "");
    const context = cleanText(
      clean.slice(Math.max(0, match.index - 90), Math.min(clean.length, match.index + raw.length + 110))
    );
    const preferred = /\b(?:preferred|desirable|nice to have|advantage|ideally|plus|bonus)\b/i.test(
      context
    );
    const required =
      !preferred ||
      /\b(?:require|requires|required|must|minimum|min\.?|mandatory|essential|need(?:ed|s)?|should have)\b/i.test(
        context
      );
    const confidence = required ? 0.92 : 0.68;
    if (min > best.min || (min === best.min && confidence > best.confidence)) {
      best = { min, max: Math.max(min, max), required, preferred, confidence, raw };
    }
  });
  return best;
}

function inferJobSeniority(text) {
  const clean = cleanText(text).toLowerCase();
  if (/\b(?:chief|cfo|ceo|coo|cto|partner|vp|vice president|head of|director)\b/.test(clean)) {
    return { level: 5, label: "executive" };
  }
  if (/\b(?:senior manager|lead manager|principal|senior|lead)\b/.test(clean)) {
    return { level: 4, label: "senior" };
  }
  if (/\b(?:manager|management|supervisor|team lead)\b/.test(clean)) {
    return { level: 3, label: "manager" };
  }
  if (/\b(?:associate|analyst|officer|specialist|consultant)\b/.test(clean)) {
    return { level: 2, label: "mid" };
  }
  if (/\b(?:intern|graduate|entry level|junior|trainee)\b/.test(clean)) {
    return { level: 1, label: "junior" };
  }
  return { level: 0, label: "" };
}

function inferCvSeniority(structured, text) {
  const entries = Array.isArray(structured?.experience) ? structured.experience : [];
  const current = entries.find((entry) => entry?.dateRange?.isCurrent) || entries[0] || {};
  const haystack = cleanText([current.role, current.title, text].join(" "));
  return inferJobSeniority(haystack);
}

function calculateSkillCoverage(cvSkills, jobSkills) {
  const cvSet = new Set(dedupeSkillLabels(cvSkills));
  const job = dedupeSkillLabels(jobSkills);
  const matched = job.filter((skill) => cvSet.has(skill));
  const missing = job.filter((skill) => !cvSet.has(skill));
  return {
    matched,
    missing,
    score: job.length ? Math.round((matched.length / job.length) * 100) : 55,
  };
}

function evaluateYearsFit(candidateYears, requirement) {
  const years = Number(candidateYears || 0) || 0;
  const min = Number(requirement?.min || 0) || 0;
  if (!min) {
    return { status: "unknown", score: 55, candidateYears: years, requiredYears: 0, deficit: 0 };
  }
  const deficit = Math.max(0, min - years);
  if (years >= min) {
    return { status: "qualified", score: 100, candidateYears: years, requiredYears: min, deficit: 0 };
  }
  if (deficit <= 1 || years >= min * 0.8) {
    return {
      status: "stretch",
      score: Math.max(58, Math.round((years / Math.max(min, 1)) * 100)),
      candidateYears: years,
      requiredYears: min,
      deficit,
    };
  }
  return {
    status: "underqualified",
    score: Math.max(10, Math.round((years / Math.max(min, 1)) * 100)),
    candidateYears: years,
    requiredYears: min,
    deficit,
  };
}

function buildJobText(job) {
  return cleanText(
    [
      job?.title,
      job?.company,
      job?.location,
      job?.seniority,
      job?.sector,
      job?.description,
      job?.description_preview,
      job?.description_html,
      job?.job_description,
      job?.requirements,
      job?.responsibilities,
      job?.snippet,
      job?.excerpt,
      job?.content,
    ].join(" ")
  );
}

app.get("/health", (req, res) => {
  res.json({
    ok: true,
    parser:
      "liteparse+resume-parser-ats" +
      (pyresumeEnabled ? "+pyresume" : "") +
      (layoutParseEnabled ? "+layout" : ""),
    grammar: "harper",
    jobMatching: "skill-extractor",
    pyresumeEnabled,
    layoutParser: "pymupdf",
    layoutParseEnabled,
    ocrEnabled: process.env.LITEPARSE_OCR_ENABLED !== "0",
  });
});

app.post("/match-job", requireToken, async (req, res) => {
  try {
    const body = req.body || {};
    const job = body.job || {};
    const structured = body.cvStructured || body.structured || {};
    const cvText = cleanText(
      body.cvText || body.resumeText || body.text || getStructuredCvSkillText(structured)
    );
    const jobText = buildJobText(job);
    if (!cvText && !getStructuredCvSkillText(structured)) {
      res.status(400).json({ ok: false, error: "missing_cv_text" });
      return;
    }
    if (!jobText) {
      res.status(400).json({ ok: false, error: "missing_job_text" });
      return;
    }

    const [cvSkillResult, jobSkillResult] = await Promise.all([
      extractHrSkills([cvText, getStructuredCvSkillText(structured)].join(" "), {
        threshold: 0.48,
      }),
      extractHrSkills(jobText, { threshold: 0.5 }),
    ]);
    const structuredSkills = dedupeSkillLabels(structured?.skills || []);
    const cvSkills = dedupeSkillLabels(
      structuredSkills.concat(cvSkillResult.skills || [])
    );
    const jobSkills = dedupeSkillLabels(jobSkillResult.skills || []);
    const coverage = calculateSkillCoverage(cvSkills, jobSkills);
    const experienceRequirement = extractJobExperienceRequirement(jobText);
    const candidateYears =
      Number(body.cvYears || 0) || getStructuredCvYears(structured) || 0;
    const experienceFit = evaluateYearsFit(candidateYears, experienceRequirement);
    const jobSeniority = inferJobSeniority([job?.title, jobText].join(" "));
    const cvSeniority = inferCvSeniority(structured, cvText);
    const seniorityFit =
      jobSeniority.level && cvSeniority.level
        ? Math.max(0, 100 - Math.abs(jobSeniority.level - cvSeniority.level) * 22)
        : 55;
    const titleText = cleanText([job?.title, job?.seniority].join(" ")).toLowerCase();
    const cvTitleText = cleanText(
      [
        structured?.experience?.[0]?.role,
        structured?.experience?.[0]?.title,
        structured?.profile?.summary,
      ].join(" ")
    ).toLowerCase();
    const titleTokens = dedupeSkillLabels(titleText.split(/\s+/)).filter(
      (token) => token.length > 2
    );
    const cvTitleTokens = new Set(
      dedupeSkillLabels(cvTitleText.split(/\s+/)).filter((token) => token.length > 2)
    );
    const titleOverlap = titleTokens.filter((token) => cvTitleTokens.has(token));
    const titleScore = titleTokens.length
      ? Math.min(100, Math.round((titleOverlap.length / titleTokens.length) * 100) + 20)
      : 55;
    const fitScore = Math.max(
      0,
      Math.min(
        100,
        Math.round(
          coverage.score * 0.42 +
            experienceFit.score * 0.24 +
            seniorityFit * 0.18 +
            titleScore * 0.16
        )
      )
    );
    const fitBand =
      experienceFit.status === "underqualified" && experienceFit.deficit > 1
        ? "weak"
        : fitScore >= 78
        ? "strong"
        : fitScore >= 58
        ? "consider"
        : "weak";

    res.json({
      ok: true,
      engine: "skill-extractor",
      fitScore,
      fitBand,
      matchedSkills: coverage.matched.slice(0, 30),
      missingSkills: coverage.missing.slice(0, 30),
      cvSkills: cvSkills.slice(0, 80),
      jobSkills: jobSkills.slice(0, 80),
      skillCoverageScore: coverage.score,
      experienceRequirement,
      experienceFit,
      seniorityFit: {
        score: Math.round(seniorityFit),
        cv: cvSeniority,
        job: jobSeniority,
      },
      titleFit: {
        score: Math.round(titleScore),
        matchedTerms: titleOverlap.slice(0, 12),
      },
      diagnostics: {
        cvSkillEngine: cvSkillResult.engine,
        jobSkillEngine: jobSkillResult.engine,
        cvSkillError: cvSkillResult.error,
        jobSkillError: jobSkillResult.error,
      },
    });
  } catch (error) {
    res.status(500).json({
      ok: false,
      error: "job_match_failed",
      message: error && error.message ? error.message : String(error),
    });
  }
});

app.post("/parse", requireToken, upload.single("file"), async (req, res) => {
  try {
    if (!req.file || !req.file.buffer) {
      res.status(400).json({ ok: false, error: "missing_file" });
      return;
    }

    const result = await parser.parse(req.file.buffer);
    const normalized = normalizeParseResult(result);
    normalized.structured = await parseStructuredResumeEnsembleFromUpload(
      req.file,
      normalized.text
    );
    normalized.canonical = buildCanonicalResumeModel(
      normalized,
      normalized.structured
    );
    if (normalized.structured && typeof normalized.structured === "object") {
      normalized.structured.quality = normalized.canonical.quality;
      normalized.structured.rawFallbackSections =
        normalized.canonical.rawFallbackSections;
      normalized.structured.canonicalDiagnostics =
        normalized.canonical.diagnostics;
    }
    normalized.parser = normalized.structured?.ok
      ? `liteparse+${normalized.structured.parser}`
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
