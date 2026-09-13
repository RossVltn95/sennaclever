"use strict";

const crypto = require("crypto");

let chrono = null;
let winkNLP = null;
let winkModel = null;
let winkBm25 = null;
let winkNaiveBayes = null;

try {
  chrono = require("chrono-node");
} catch (error) {
  chrono = null;
}

try {
  winkNLP = require("wink-nlp");
  winkModel = require("wink-eng-lite-web-model");
} catch (error) {
  winkNLP = null;
  winkModel = null;
}

try {
  winkBm25 = require("wink-bm25-text-search");
} catch (error) {
  winkBm25 = null;
}

try {
  winkNaiveBayes = require("wink-naive-bayes-text-classifier");
} catch (error) {
  winkNaiveBayes = null;
}

const nlp = winkNLP && winkModel ? winkNLP(winkModel) : null;

const STOP_WORDS = new Set(
  "a an and are as at be been being but by can could did do does doing for from had has have having he her here hers herself him himself his how i if in into is it its itself just me my myself of on or our ours ourselves please she should so than that the their theirs them themselves then there these they this those through to too us very was we were what when where which who why will with would you your yours yourself yourselves im i'm ill i'll ive i've".split(
    /\s+/
  )
);

const LEXICON = {
  routes: {
    job_search: {
      weight: 1.15,
      phrases: [
        "find jobs",
        "find roles",
        "search jobs",
        "show jobs",
        "show roles",
        "job openings",
        "live roles",
        "roles in",
        "jobs in",
        "opportunities in",
        "i need a job",
      ],
      terms: [
        "job",
        "jobs",
        "role",
        "roles",
        "opening",
        "openings",
        "opportunity",
        "opportunities",
        "vacancy",
        "vacancies",
        "hiring",
        "apply",
      ],
    },
    web_research: {
      weight: 1.2,
      phrases: [
        "best recruiters",
        "recruitment agencies",
        "best agencies",
        "salary guide",
        "best time",
        "what is it like",
        "company research",
        "market outlook",
        "visa rules",
        "cost of living",
        "work culture",
      ],
      terms: [
        "best",
        "agency",
        "agencies",
        "recruiter",
        "recruiters",
        "salary",
        "visa",
        "market",
        "timing",
        "season",
        "culture",
        "living",
        "research",
        "compare",
        "list",
        "guide",
      ],
    },
    career_advice: {
      weight: 1.1,
      phrases: [
        "what do you suggest",
        "not getting replies",
        "job search is hard",
        "tired of my job search",
        "career advice",
        "what should i do",
        "how do i improve",
        "why am i not",
        "help me figure out",
      ],
      terms: [
        "advice",
        "suggest",
        "stuck",
        "tired",
        "frustrated",
        "burned",
        "burnt",
        "exhausted",
        "reply",
        "replies",
        "improve",
        "strategy",
        "help",
      ],
    },
    cv_profile: {
      weight: 1,
      phrases: [
        "my cv",
        "my resume",
        "fit check",
        "am i qualified",
        "what roles suit me",
        "review my cv",
        "tailor my cv",
      ],
      terms: ["cv", "resume", "profile", "qualified", "fit", "tailor", "rewrite"],
    },
    application_flow: {
      weight: 1,
      phrases: [
        "apply for this",
        "submit application",
        "continue application",
        "employer form",
        "just this role",
        "tailor and apply",
      ],
      terms: ["apply", "submit", "continue", "form", "application"],
    },
  },
  roles: [
    "accountant",
    "analyst",
    "associate",
    "auditor",
    "banker",
    "business analyst",
    "chief financial officer",
    "credit analyst",
    "data analyst",
    "director",
    "finance manager",
    "financial analyst",
    "hr manager",
    "hr operations manager",
    "hr recruitment manager",
    "investment analyst",
    "investment associate",
    "manager",
    "portfolio analyst",
    "product manager",
    "recruiter",
    "relationship manager",
    "talent acquisition manager",
  ],
  sectors: [
    "asset management",
    "banking",
    "capital markets",
    "corporate banking",
    "credit",
    "finance",
    "fintech",
    "human resources",
    "investment banking",
    "islamic finance",
    "private credit",
    "private equity",
    "real estate",
    "recruitment",
    "venture capital",
  ],
  skills: [
    "accounting",
    "audit",
    "benefits",
    "budgeting",
    "capital iq",
    "credit analysis",
    "data analysis",
    "due diligence",
    "employee engagement",
    "excel",
    "financial modelling",
    "ifrs",
    "lbo",
    "payroll",
    "power bi",
    "python",
    "recruitment",
    "sourcing",
    "talent acquisition",
    "valuation",
  ],
  locations: [
    "abu dhabi",
    "bahrain",
    "doha",
    "dubai",
    "jeddah",
    "kuwait",
    "london",
    "manama",
    "qatar",
    "riyadh",
    "saudi",
    "saudi arabia",
    "uae",
    "united arab emirates",
    "united kingdom",
  ],
  markets: [
    "dubai job market",
    "gulf hiring",
    "mena careers",
    "saudi hiring",
    "uae hiring",
  ],
  seniority: [
    "intern",
    "graduate",
    "junior",
    "analyst",
    "associate",
    "manager",
    "senior manager",
    "director",
    "vp",
    "head",
    "cfo",
  ],
  companies: [
    "adcb",
    "al futtaim",
    "mashreq",
    "merak capital",
    "michael page",
    "mubadala",
    "permira",
    "savills",
    "standard chartered",
    "tikehau",
  ],
};

const INTENT_EXAMPLES = [
  ["job_search", "find finance jobs in dubai"],
  ["job_search", "show me investment analyst roles in riyadh"],
  ["job_search", "i need to find jobs in saudi arabia"],
  ["job_search", "search for private equity associate openings"],
  ["web_research", "best recruiters in dubai"],
  ["web_research", "when is the best time to apply for jobs in dubai"],
  ["web_research", "what is saudi arabia like for expats"],
  ["web_research", "salary for credit analysts in riyadh"],
  ["career_advice", "i am tired of my job search what do you suggest"],
  ["career_advice", "why am i not getting replies"],
  ["career_advice", "how can i improve my job search"],
  ["cv_profile", "review my cv and tell me what roles suit me"],
  ["cv_profile", "am i qualified for this role"],
  ["application_flow", "apply for this role"],
  ["application_flow", "continue the application"],
];

const PROXIMITY_PAIRS = [
  [["sector", "role"], "job_search", 0.26],
  [["sector", "job_object"], "job_search", 0.34],
  [["role", "location"], "job_search", 0.28],
  [["job_object", "location"], "job_search", 0.34],
  [["recruiter", "location"], "web_research", 0.42],
  [["salary", "location"], "web_research", 0.34],
  [["timing", "job_object"], "web_research", 0.36],
  [["emotion", "job_object"], "career_advice", 0.42],
  [["advice", "job_object"], "career_advice", 0.35],
  [["apply", "role"], "application_flow", 0.3],
  [["cv", "fit"], "cv_profile", 0.36],
];

const bm25Engine = buildBm25Engine();
const bayesEngine = buildBayesEngine();

function normalizeText(value) {
  return String(value || "")
    .replace(/[’‘]/g, "'")
    .replace(/[“”]/g, '"')
    .replace(/&/g, " and ")
    .replace(/\bfund\s+(?=(?:a\s+)?(?:job|jobs|role|roles|opening|openings)\b)/gi, "find ")
    .replace(/\bsaudia\b/gi, "saudi arabia")
    .replace(/\bsaudiya\b/gi, "saudi arabia")
    .replace(/\bksa\b/gi, "saudi arabia")
    .replace(/\buae\b/gi, "united arab emirates")
    .replace(/\brecuiters?\b/gi, "recruiters")
    .replace(/\brecruiterers?\b/gi, "recruiters")
    .replace(/\s+/g, " ")
    .trim()
    .toLowerCase();
}

function tokenize(value) {
  const normalized = normalizeText(value);
  if (!normalized) return [];
  if (nlp) {
    try {
      const tokens = [];
      const doc = nlp.readDoc(normalized);
      doc.tokens().each((token) => {
        const out = normalizeText(token.out());
        if (out && /^[a-z0-9][a-z0-9+'-]*$/.test(out) && !STOP_WORDS.has(out)) {
          tokens.push(stem(out));
        }
      });
      if (tokens.length) return tokens;
    } catch (error) {
      // Fall through to regex tokenization.
    }
  }
  return normalized
    .split(/[^a-z0-9+'-]+/i)
    .map((token) => stem(token))
    .filter((token) => token && !STOP_WORDS.has(token));
}

function stem(token) {
  return String(token || "")
    .toLowerCase()
    .replace(/ies$/i, "y")
    .replace(/(?:ing|ed|ers|er|s)$/i, "");
}

function escapeRegExp(value) {
  return String(value).replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
}

function findDictionaryMatches(text, list) {
  const normalized = normalizeText(text);
  const seen = new Set();
  return list
    .map((item) => {
      const key = normalizeText(item);
      let index = -1;
      if (key.includes(" ")) {
        index = normalized.indexOf(key);
      } else {
        const match = new RegExp(`\\b${escapeRegExp(key)}\\b`, "i").exec(normalized);
        index = match ? match.index : -1;
      }
      if (index < 0 || seen.has(key)) return null;
      seen.add(key);
      return {
        label: item,
        score: item.includes(" ") ? 0.9 : 0.68,
        index,
        end: index + key.length,
      };
    })
    .filter(Boolean)
    .sort((a, b) => {
      const aWords = normalizeText(a.label).split(/\s+/).length;
      const bWords = normalizeText(b.label).split(/\s+/).length;
      if (bWords !== aWords) return bWords - aWords;
      if (b.label.length !== a.label.length) return b.label.length - a.label.length;
      return b.score - a.score;
    });
}

function isNegatedDictionaryMatch(text, match) {
  const normalized = normalizeText(text);
  const start = Number(match?.index || 0);
  const before = normalized.slice(Math.max(0, start - 52), start);
  if (!before) return false;
  return /(?:^|\b)(?:but\s+)?(?:not|no|exclude|excluding|without|except|avoid|dont want|do not want|other than)\s+(?:any\s+)?(?:\w+\s+){0,4}$/i.test(
    before
  );
}

function splitExcludedMatches(text, list) {
  const matches = findDictionaryMatches(text, list);
  const included = [];
  const excluded = [];
  matches.forEach((match) => {
    if (isNegatedDictionaryMatch(text, match)) excluded.push(match);
    else included.push(match);
  });
  return { included, excluded };
}

function uniqueMatches(values) {
  const seen = new Set();
  return (values || []).filter((item) => {
    const key = normalizeText(item && item.label);
    if (!key || seen.has(key)) return false;
    seen.add(key);
    return true;
  });
}

function indexedFamilyHits(text) {
  const normalized = normalizeText(text);
  const words = normalized.split(/\s+/).filter(Boolean);
  const hits = [];
  words.forEach((word, index) => {
    const stemmed = stem(word);
    let family = "";
    if (LEXICON.routes.job_search.terms.some((term) => stem(term) === stemmed)) family = "job_object";
    if (LEXICON.routes.web_research.terms.some((term) => stem(term) === stemmed)) {
      if (/recruit/.test(stemmed)) family = "recruiter";
      else if (/salary|compensation|pay/.test(stemmed)) family = "salary";
      else if (/time|season|timing|when/.test(stemmed)) family = "timing";
      else family = "web";
    }
    if (LEXICON.routes.career_advice.terms.some((term) => stem(term) === stemmed)) {
      if (/tired|stuck|frustrat|burn|exhaust/.test(stemmed)) family = "emotion";
      else family = "advice";
    }
    if (LEXICON.routes.cv_profile.terms.some((term) => stem(term) === stemmed)) family = "cv";
    if (LEXICON.routes.application_flow.terms.some((term) => stem(term) === stemmed)) family = "apply";
    if (family) hits.push({ family, token: word, index });
  });

  LEXICON.roles.forEach((role) => addPhraseHit(hits, words, role, "role"));
  LEXICON.sectors.forEach((sector) => addPhraseHit(hits, words, sector, "sector"));
  LEXICON.locations.forEach((location) => addPhraseHit(hits, words, location, "location"));
  if (/\bfit\b/i.test(normalized)) addPhraseHit(hits, words, "fit", "fit");
  return hits;
}

function addPhraseHit(hits, words, phrase, family) {
  const phraseWords = normalizeText(phrase).split(/\s+/).filter(Boolean);
  if (!phraseWords.length) return;
  for (let i = 0; i <= words.length - phraseWords.length; i += 1) {
    if (phraseWords.every((word, offset) => stem(words[i + offset]) === stem(word))) {
      hits.push({ family, token: phrase, index: i });
      return;
    }
  }
}

function scoreRoutes(text, context) {
  const normalized = normalizeText(text);
  const routeScores = {};
  const explanation = [];

  Object.entries(LEXICON.routes).forEach(([route, config]) => {
    let score = 0;
    config.phrases.forEach((phrase) => {
      if (normalized.includes(phrase)) score += 0.36 * config.weight;
    });
    config.terms.forEach((term) => {
      if (new RegExp(`\\b${escapeRegExp(term)}\\b`, "i").test(normalized)) {
        score += 0.12 * config.weight;
      }
    });
    routeScores[route] = score;
  });

  const hits = indexedFamilyHits(normalized);
  PROXIMITY_PAIRS.forEach(([families, route, weight]) => {
    const aHits = hits.filter((hit) => hit.family === families[0]);
    const bHits = hits.filter((hit) => hit.family === families[1]);
    aHits.forEach((a) => {
      bHits.forEach((b) => {
        const distance = Math.abs(a.index - b.index);
        if (distance <= 6) routeScores[route] += weight / (1 + distance);
      });
    });
  });

  const bm25 = scoreBm25(normalized);
  const bayes = scoreBayes(normalized);
  bm25.forEach((item) => {
    routeScores[item.intent] = (routeScores[item.intent] || 0) + item.score * 0.42;
  });
  bayes.forEach((item) => {
    routeScores[item.intent] = (routeScores[item.intent] || 0) + item.score * 0.34;
  });

  const contextProfile = getCvProfileContext(context);
  const hasSelectedRole = !!getSelectedRoleLabel(context);
  const shortLocationFollowUp = isShortFollowUpLocationQuestion(normalized, {
    locations: findDictionaryMatches(normalized, LEXICON.locations),
  });
  const webResearchShape = hasWebResearchShape(normalized);
  const explicitApplicationCommand = /\b(apply|submit|continue|form|tailor)\b/i.test(normalized);

  if (hasSelectedRole && webResearchShape) {
    routeScores.web_research += 0.22;
    if (!explicitApplicationCommand) routeScores.application_flow -= 0.2;
  }
  if (context?.hasCv && routeScores.cv_profile > 0.2) routeScores.cv_profile += 0.12;
  if (contextProfile && routeScores.career_advice > 0.2) {
    routeScores.career_advice += 0.12;
  }
  if (shortLocationFollowUp && getContextRoleOrSector(context)) {
    routeScores.job_search += 0.3;
    routeScores.web_research -= 0.08;
    explanation.push("Short location follow-up reused the previous role or sector context.");
  }
  if (context?.activeTask?.type === "search" && shortLocationFollowUp) {
    routeScores.job_search += 0.14;
  }
  if (context?.activeTask?.type === "application" && !explicitApplicationCommand && webResearchShape) {
    routeScores.web_research += 0.16;
    routeScores.application_flow -= 0.18;
  }
  if (/\?$/.test(String(text || "").trim())) routeScores.web_research += 0.08;
  if (!hasConcreteJobSearchShape(normalized)) routeScores.job_search -= 0.1;

  const ranked = Object.entries(routeScores)
    .map(([intent, score]) => ({ intent, score: round(Math.max(0, score)) }))
    .sort((a, b) => b.score - a.score);

  const top = ranked[0] || { intent: "web_research", score: 0 };
  const confidence = round(Math.min(0.97, 0.28 + top.score / 1.9));
  explanation.push(`Top route ${top.intent} from dictionary, proximity, BM25, and classifier evidence.`);

  return {
    ranked,
    bm25,
    bayes,
    hits,
    confidence,
    primaryIntent: top.intent,
    explanation,
  };
}

function getCvProfileContext(context) {
  const profile = context?.cvProfile || context?.memory?.profileSnapshot || null;
  if (!profile || typeof profile !== "object") return null;
  return profile;
}

function normalizeContextList(values, limit = 8) {
  if (!Array.isArray(values)) return [];
  return values
    .map((value) => {
      if (typeof value === "string") return value;
      if (value && typeof value === "object") return value.label || value.role || value.title || value.name || "";
      return "";
    })
    .map((value) => normalizeText(value))
    .filter(Boolean)
    .slice(0, limit);
}

function getSelectedRoleLabel(context) {
  const role = context?.selectedRole;
  if (!role) return "";
  if (typeof role === "string") return cleanContextText(role);
  return [role.title, role.company, role.location].map(cleanContextText).filter(Boolean).join(" ");
}

function getContextLocation(context) {
  const direct =
    context?.searchPreferences?.preferredLocation ||
    context?.jobSearchContext?.location ||
    context?.selectedRole?.location ||
    "";
  if (direct) return normalizeLocation(direct);
  const profileLocations = normalizeContextList(getCvProfileContext(context)?.locations || [], 6);
  return normalizeLocation(profileLocations[0] || "");
}

function getContextRoleOrSector(context) {
  const currentQuery = cleanContextText(
    context?.searchPreferences?.currentQuery || context?.jobSearchContext?.query || ""
  );
  if (currentQuery) return currentQuery;
  const profile = getCvProfileContext(context);
  const title = cleanContextText(profile?.title || "");
  if (title) return title;
  const families = normalizeContextList(profile?.families || [], 4);
  if (families.length) return families[0].replace(/_/g, " ");
  const roleTerms = normalizeContextList(profile?.roleTerms || [], 6);
  return roleTerms.slice(0, 3).join(" ");
}

function getContextCareerFocus(context) {
  const profile = getCvProfileContext(context);
  if (!profile) return "";
  const title = cleanContextText(profile.title || "");
  const families = normalizeContextList(profile.families || [], 3).map((item) => item.replace(/_/g, " "));
  const skills = normalizeContextList(profile.skills || [], 5);
  return [title, families[0], skills.slice(0, 3).join(" ")].filter(Boolean).join(" ");
}

function isShortFollowUpLocationQuestion(text, entities) {
  const clean = normalizeText(text);
  const hasLocation = (entities?.locations || []).length > 0 || findDictionaryMatches(clean, LEXICON.locations).length > 0;
  if (!hasLocation) return false;
  const words = tokenize(clean);
  return (
    words.length <= 6 &&
    /\b(what about|how about|and|also|in|near|around)\b/i.test(clean) &&
    !findDictionaryMatches(clean, LEXICON.roles).length &&
    !findDictionaryMatches(clean, LEXICON.sectors).length
  );
}

function cleanContextText(value) {
  return String(value || "").replace(/\s+/g, " ").trim();
}

function hasConcreteJobSearchShape(text) {
  return (
    /\b(find|search|show|look|need|want)\b.{0,24}\b(job|jobs|role|roles|opening|opportunities)\b/i.test(text) ||
    /\b(job|jobs|role|roles|opening|opportunities)\b.{0,24}\b(in|near|around|for)\b/i.test(text) ||
    findDictionaryMatches(text, LEXICON.roles).length > 0
  );
}

function buildMeaning(input) {
  const message = String(input?.message || input?.text || "");
  const context = input?.context && typeof input.context === "object" ? input.context : {};
  const normalized = normalizeText(message);
  const tokens = tokenize(normalized);
  const route = scoreRoutes(normalized, context);
  const roleMatches = splitExcludedMatches(normalized, LEXICON.roles);
  const sectorMatches = splitExcludedMatches(normalized, LEXICON.sectors);
  const skillMatches = splitExcludedMatches(normalized, LEXICON.skills);
  const locationMatches = splitExcludedMatches(normalized, LEXICON.locations);
  const seniorityMatches = splitExcludedMatches(normalized, LEXICON.seniority);
  const constraints = {
    excludedRoles: uniqueMatches(roleMatches.excluded),
    excludedSectors: uniqueMatches(sectorMatches.excluded),
    excludedSkills: uniqueMatches(skillMatches.excluded),
    excludedLocations: uniqueMatches(locationMatches.excluded),
    excludedSeniority: uniqueMatches(seniorityMatches.excluded),
  };
  const entities = {
    roles: roleMatches.included,
    sectors: sectorMatches.included,
    skills: skillMatches.included,
    locations: locationMatches.included,
    companies: findDictionaryMatches(normalized, LEXICON.companies),
    markets: findDictionaryMatches(normalized, LEXICON.markets),
    seniority: seniorityMatches.included,
    dates: parseDates(message),
    constraints,
  };
  const emotion = detectEmotion(normalized);
  const rewrittenQueries = rewriteQueries(normalized, entities, route.primaryIntent, emotion, context);
  const action = decideAction(route, entities, context, rewrittenQueries, normalized);

  return {
    schemaVersion: 1,
    requestId: crypto.randomUUID(),
    raw: message,
    normalized,
    tokens,
    primaryIntent: action.intent,
    confidence: action.confidence,
    secondaryIntents: route.ranked.slice(1, 4),
    emotion,
    entities,
    scores: {
      routes: route.ranked,
      bm25: route.bm25,
      naiveBayes: route.bayes,
      proximityHits: route.hits.slice(0, 40),
    },
    action,
    rewrittenQueries,
    contextSignals: buildContextSignals(context, normalized, entities),
    explanation: route.explanation.concat(action.explanation),
  };
}

function buildContextSignals(context, normalized, entities) {
  const profile = getCvProfileContext(context);
  return {
    hasCv: !!context?.hasCv,
    selectedRole: getSelectedRoleLabel(context),
    activeTaskType: cleanContextText(context?.activeTask?.type || ""),
    currentQuery: cleanContextText(context?.searchPreferences?.currentQuery || context?.jobSearchContext?.query || ""),
    contextRoleOrSector: getContextRoleOrSector(context),
    contextLocation: getContextLocation(context),
    cvTitle: cleanContextText(profile?.title || ""),
    cvFamilies: normalizeContextList(profile?.families || [], 4),
    shortLocationFollowUp: isShortFollowUpLocationQuestion(normalized, entities),
  };
}

function detectEmotion(text) {
  const frustrated = /\b(tired|stuck|frustrated|exhausted|burn(?:ed|t)? out|no replies|not getting replies|fed up)\b/i.test(
    text
  );
  if (frustrated) return { label: "frustrated", score: 0.82 };
  if (/\b(confused|unsure|lost|don't know|do not know)\b/i.test(text)) {
    return { label: "uncertain", score: 0.7 };
  }
  return { label: "neutral", score: 0.35 };
}

function rewriteQueries(text, entities, primaryIntent, emotion, context = {}) {
  const role = firstLabel(entities.roles);
  const sector = firstLabel(entities.sectors);
  const location = normalizeLocation(firstLabel(entities.locations));
  const contextLocation = getContextLocation(context);
  const contextRoleOrSector = getContextRoleOrSector(context);
  const careerFocus = getContextCareerFocus(context);
  let seniority = firstLabel(entities.seniority);
  const company = firstLabel(entities.companies);
  const market = firstLabel(entities.markets);
  const skills = entities.skills.slice(0, 5).map((item) => item.label);
  const constraints = buildQueryConstraints(entities);
  const excludedLocationLabels = new Set(
    constraints.excludeLocations.map((item) => normalizeLocation(item).toLowerCase())
  );
  const usableContextLocation =
    contextLocation && !excludedLocationLabels.has(contextLocation.toLowerCase())
      ? contextLocation
      : "";

  const shortLocationFollowUp = isShortFollowUpLocationQuestion(text, entities);
  if (role && seniority && normalizeText(role).includes(normalizeText(seniority))) {
    seniority = "";
  }
  const jobsParts = [
    seniority,
    role || sector || (shortLocationFollowUp ? contextRoleOrSector : ""),
    location || (primaryIntent === "job_search" ? usableContextLocation : ""),
  ].filter(Boolean);
  let jobs = jobsParts.join(" ").trim() || null;
  if (!jobs && primaryIntent === "job_search" && location) jobs = location;

  let web = null;
  if (primaryIntent === "career_advice" || emotion.label !== "neutral") {
    web = [
      "job search burnout no replies improve application strategy practical steps",
      careerFocus,
    ]
      .filter(Boolean)
      .join(" ");
  } else if (/\bbest time\b|\bwhen\b|\btiming\b|\bseason\b/i.test(text)) {
    web = ["best time to apply for jobs", location || sector || "middle east"].filter(Boolean).join(" ");
  } else if (/\brecruit/i.test(text)) {
    web = ["best recruitment agencies", location || sector || "middle east"].filter(Boolean).join(" ");
  } else if (/\bsalary|compensation|pay\b/i.test(text)) {
    web = ["salary guide", role || sector || "", location || ""].filter(Boolean).join(" ");
  } else if (/\bwhat is|what's|like|culture|living|visa|market\b/i.test(text)) {
    web = [company || market || role || sector || "job market", location || "", "career guide"]
      .filter(Boolean)
      .join(" ");
  } else if (primaryIntent === "web_research") {
    web = [role || sector || "careers", location || "", skills[0] || ""].filter(Boolean).join(" ");
  }

  return {
    jobs: jobs
      ? {
          query: jobs,
          role: role || "",
          sector: sector || "",
          location: location || "",
          seniority: seniority || "",
          constraints,
        }
      : null,
    web: web ? compactQuery(web) : null,
    constraints,
  };
}

function buildQueryConstraints(entities) {
  const constraints = entities?.constraints || {};
  return {
    excludeRoles: matchLabels(constraints.excludedRoles),
    excludeSectors: matchLabels(constraints.excludedSectors),
    excludeSkills: matchLabels(constraints.excludedSkills),
    excludeLocations: matchLabels(constraints.excludedLocations).map(normalizeLocation),
    excludeSeniority: matchLabels(constraints.excludedSeniority),
  };
}

function matchLabels(values) {
  return (Array.isArray(values) ? values : [])
    .map((item) => cleanContextText(item && item.label))
    .filter(Boolean);
}

function decideAction(route, entities, context, rewrittenQueries, normalized) {
  const ranked = route.ranked;
  const primary = ranked[0] || { intent: "web_research", score: 0 };
  const byIntent = Object.fromEntries(ranked.map((item) => [item.intent, item.score]));
  const hasLocation = entities.locations.length > 0;
  const hasRoleOrSector = entities.roles.length > 0 || entities.sectors.length > 0;
  const webResearchShape = hasWebResearchShape(normalized);
  const explanation = [];
  let intent = primary.intent;
  let type = "web_answer";

  if (isCareerQuestionClarificationAnswer(normalized, context)) {
    intent = "career_advice";
    type = "career_context_confirmation";
    explanation.push("User answered the active clarification by choosing career advice.");
  } else if (webResearchShape && byIntent.web_research >= 0.24) {
    intent = "web_research";
    type = "web_answer";
    explanation.push("Market, timing, recruiter, country, salary, or company-research shape detected.");
  } else if (
    isShortFollowUpLocationQuestion(normalized, entities) &&
    getContextRoleOrSector(context) &&
    byIntent.job_search >= 0.24
  ) {
    intent = "job_search";
    type = "jobs_database_search";
    explanation.push("Location follow-up reused the previous role or search focus.");
  } else if (
    byIntent.job_search >= 0.32 &&
    (hasLocation || hasRoleOrSector || hasConcreteJobSearchShape(normalized)) &&
    byIntent.web_research < byIntent.job_search + 0.18
  ) {
    intent = "job_search";
    type = "jobs_database_search";
    explanation.push("Concrete job-search shape detected, so use the jobs database.");
  } else if (byIntent.cv_profile >= 0.4) {
    intent = "cv_profile";
    type = "cv_profile_reasoning";
    explanation.push("CV/profile language detected.");
  } else if (byIntent.application_flow >= 0.44 && /\b(apply|submit|continue|form)\b/i.test(normalized)) {
    intent = "application_flow";
    type = "application_flow";
    explanation.push("Explicit application-flow command detected.");
  } else if (byIntent.career_advice >= 0.34) {
    intent = "career_advice";
    type = "career_advice_with_web_support";
    explanation.push("Advice or job-search frustration language detected.");
  } else {
    intent = "web_research";
    type = "web_answer";
    explanation.push("No stronger internal action won, so default to web-backed answering.");
  }

  const ambiguousActiveRole =
    context?.selectedRole &&
    type !== "application_flow" &&
    byIntent.application_flow > 0.28 &&
    Math.abs((byIntent.application_flow || 0) - (byIntent.web_research || 0)) < 0.08;

  if (ambiguousActiveRole) {
    type = "clarify";
    explanation.push("Active selected role plus ambiguous application wording requires clarification.");
  }

  return {
    type,
    intent,
    confidence: round(Math.min(0.98, route.confidence + (rewrittenQueries.web || rewrittenQueries.jobs ? 0.06 : 0))),
    shouldSearchJobs: type === "jobs_database_search",
    shouldSearchWeb: type === "web_answer" || type === "career_advice_with_web_support",
    shouldAskClarification: type === "clarify",
    explanation,
  };
}

function isCareerQuestionClarificationAnswer(text, context) {
  const clean = normalizeText(text);
  if (!clean) return false;
  const promptState = cleanContextText(context?.activeTask?.promptState || "");
  const hasActivePrompt = !!promptState || !!context?.activeTask?.state;
  return (
    hasActivePrompt &&
    /\b(?:career question|career advice|general career advice|talking through a career question|answer this as general career advice)\b/i.test(
      clean
    )
  );
}

function hasWebResearchShape(text) {
  return /\b(best|top|list|when|timing|season|salary|compensation|visa|market|culture|living|like|research|compare|recruiter|recruiters|agenc(?:y|ies))\b/i.test(
    text
  );
}

function parseDates(text) {
  if (!chrono) return [];
  try {
    return chrono.parse(String(text || "")).map((result) => ({
      text: result.text,
      start: result.start ? result.start.date().toISOString() : null,
      end: result.end ? result.end.date().toISOString() : null,
    }));
  } catch (error) {
    return [];
  }
}

function firstLabel(values) {
  return Array.isArray(values) && values[0] ? values[0].label : "";
}

function normalizeLocation(value) {
  const clean = normalizeText(value);
  if (clean === "uae") return "United Arab Emirates";
  if (clean === "saudi") return "Saudi Arabia";
  if (clean === "saudi arabia") return "Saudi Arabia";
  if (clean === "united arab emirates") return "United Arab Emirates";
  if (clean === "dubai") return "Dubai";
  if (clean === "riyadh") return "Riyadh";
  if (clean === "abu dhabi") return "Abu Dhabi";
  if (clean === "jeddah") return "Jeddah";
  if (clean === "doha") return "Doha";
  if (clean === "qatar") return "Qatar";
  if (clean === "kuwait") return "Kuwait";
  if (clean === "bahrain") return "Bahrain";
  if (clean === "london") return "London";
  return value || "";
}

function compactQuery(value) {
  return normalizeText(value)
    .replace(/\b(i|me|my|you|emily|please|can|could|would|help|need|want|to|for|about)\b/g, " ")
    .replace(/\s+/g, " ")
    .trim();
}

function round(value) {
  return Math.round(Number(value || 0) * 1000) / 1000;
}

function buildBm25Engine() {
  if (!winkBm25) return null;
  try {
    const engine = winkBm25();
    engine.defineConfig({ fldWeights: { text: 1, intent: 1.2 } });
    engine.definePrepTasks([tokenize]);
    INTENT_EXAMPLES.forEach(([intent, text], index) => {
      engine.addDoc({ intent, text: `${intent} ${text}` }, index);
    });
    engine.consolidate();
    return engine;
  } catch (error) {
    return null;
  }
}

function scoreBm25(text) {
  if (bm25Engine) {
    try {
      return bm25Engine
        .search(text, 8)
        .map((row) => {
          const id = Array.isArray(row) ? row[0] : row.id;
          const score = Array.isArray(row) ? row[1] : row.score;
          const intent = INTENT_EXAMPLES[Number(id)]?.[0];
          return intent ? { intent, score: Math.min(1, Number(score || 0) / 10) } : null;
        })
        .filter(Boolean)
        .reduce(mergeIntentScores, [])
        .sort((a, b) => b.score - a.score);
    } catch (error) {
      // Fall through to local BM25-style scoring.
    }
  }
  const queryTokens = tokenize(text);
  return Object.keys(LEXICON.routes)
    .map((intent) => {
      const examples = INTENT_EXAMPLES.filter((row) => row[0] === intent).map((row) => tokenize(row[1]));
      const score = examples.reduce((max, exampleTokens) => {
        const overlap = queryTokens.filter((token) => exampleTokens.includes(token)).length;
        return Math.max(max, overlap / Math.max(3, exampleTokens.length));
      }, 0);
      return { intent, score: round(score) };
    })
    .sort((a, b) => b.score - a.score);
}

function buildBayesEngine() {
  if (!winkNaiveBayes) return null;
  try {
    const classifier = winkNaiveBayes();
    if (typeof classifier.definePrepTasks === "function") classifier.definePrepTasks([tokenize]);
    INTENT_EXAMPLES.forEach(([intent, text]) => {
      if (typeof classifier.learn === "function") classifier.learn(text, intent);
    });
    if (typeof classifier.consolidate === "function") classifier.consolidate();
    return classifier;
  } catch (error) {
    return null;
  }
}

function scoreBayes(text) {
  if (bayesEngine) {
    try {
      if (typeof bayesEngine.computeOdds === "function") {
        const odds = bayesEngine.computeOdds(text);
        return Object.entries(odds || {})
          .map(([intent, score]) => ({ intent, score: round(Number(score) || 0) }))
          .sort((a, b) => b.score - a.score);
      }
      if (typeof bayesEngine.predict === "function") {
        const intent = bayesEngine.predict(text);
        return intent ? [{ intent, score: 0.72 }] : [];
      }
    } catch (error) {
      // Fall through to local classifier.
    }
  }
  const queryTokens = tokenize(text);
  const scores = {};
  INTENT_EXAMPLES.forEach(([intent, example]) => {
    const exampleTokens = tokenize(example);
    const overlap = queryTokens.filter((token) => exampleTokens.includes(token)).length;
    scores[intent] = (scores[intent] || 0) + overlap / Math.max(1, exampleTokens.length);
  });
  const total = Object.values(scores).reduce((sum, value) => sum + value, 0) || 1;
  return Object.entries(scores)
    .map(([intent, score]) => ({ intent, score: round(score / total) }))
    .sort((a, b) => b.score - a.score);
}

function mergeIntentScores(items, item) {
  const existing = items.find((candidate) => candidate.intent === item.intent);
  if (existing) existing.score = round(Math.max(existing.score, item.score));
  else items.push({ intent: item.intent, score: round(item.score) });
  return items;
}

function serviceInfo() {
  return {
    ok: true,
    service: "senna-emily-nlp-service",
    version: "1.0.0",
    nlp: nlp ? "wink-nlp" : "regex-fallback",
    bm25: bm25Engine ? "wink-bm25-text-search" : "local-fallback",
    naiveBayes: bayesEngine ? "wink-naive-bayes-text-classifier" : "local-fallback",
    chrono: Boolean(chrono),
  };
}

module.exports = {
  buildMeaning,
  serviceInfo,
  tokenize,
};
