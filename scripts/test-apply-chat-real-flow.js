#!/usr/bin/env node
const fs = require("fs");
const path = require("path");
const { execFileSync } = require("child_process");
const { chromium } = require("playwright");

const repoRoot = path.resolve(__dirname, "..");
const chatScript = path.join(
  repoRoot,
  "assets/js/crm/crm-apply-chat-article.js"
);
const cvDir = process.argv[2] || "/Users/ropafadzoyasheushe/Downloads/CVs";
const uploadBookDir =
  process.argv[3] || "/Users/ropafadzoyasheushe/Documents/UPLOADS-BOOK";

function clean(text) {
  return String(text || "")
    .replace(/[’]/g, "'")
    .replace(/[^\S\r\n]+/g, " ")
    .replace(/\s+\n/g, "\n")
    .replace(/\n{3,}/g, "\n\n")
    .trim();
}

function normalize(text) {
  return clean(text).toLowerCase().replace(/&/g, " and ");
}

function extractPdf(file) {
  try {
    return execFileSync("pdftotext", [file, "-"], {
      encoding: "utf8",
      maxBuffer: 1024 * 1024 * 8,
    });
  } catch (error) {
    return "";
  }
}

function extractDocx(file) {
  try {
    const xml = execFileSync("unzip", ["-p", file, "word/document.xml"], {
      encoding: "utf8",
      maxBuffer: 1024 * 1024 * 8,
    });
    return xml
      .replace(/<\/w:p>/g, "\n")
      .replace(/<[^>]+>/g, " ")
      .replace(/&amp;/g, "&")
      .replace(/&lt;/g, "<")
      .replace(/&gt;/g, ">")
      .replace(/\s+/g, " ");
  } catch (error) {
    return "";
  }
}

function loadCvTexts() {
  return fs
    .readdirSync(cvDir)
    .filter((name) => /\.(?:pdf|docx)$/i.test(name))
    .map((name) => {
      const file = path.join(cvDir, name);
      return {
        name,
        text: /\.docx$/i.test(name) ? extractDocx(file) : extractPdf(file),
      };
    })
    .filter((item) => normalize(item.text).length > 80);
}

function parseCsvRows(text, limit = 12) {
  const rows = [];
  let row = [];
  let value = "";
  let quoted = false;
  for (let i = 0; i < text.length; i += 1) {
    const ch = text[i];
    const next = text[i + 1];
    if (quoted && ch === '"' && next === '"') {
      value += '"';
      i += 1;
    } else if (ch === '"') {
      quoted = !quoted;
    } else if (!quoted && ch === ",") {
      row.push(value);
      value = "";
    } else if (!quoted && (ch === "\n" || ch === "\r")) {
      if (ch === "\r" && next === "\n") i += 1;
      row.push(value);
      if (row.some(Boolean)) rows.push(row);
      row = [];
      value = "";
      if (rows.length > limit) break;
    } else {
      value += ch;
    }
  }
  return rows;
}

function loadUploadBookJobs(maxFiles = 1000, perFile = 10) {
  if (!fs.existsSync(uploadBookDir)) return [];
  return fs
    .readdirSync(uploadBookDir)
    .filter((name) => /\.csv$/i.test(name))
    .slice(0, maxFiles)
    .flatMap((file) => {
      const rows = parseCsvRows(
        fs.readFileSync(path.join(uploadBookDir, file), "utf8"),
        perFile + 1
      );
      if (rows.length < 2) return [];
      const header = rows[0].map(normalize);
      return rows.slice(1, perFile + 1).flatMap((row) => {
        const get = (...names) => {
          for (const name of names) {
            const index = header.indexOf(normalize(name));
            if (index >= 0 && row[index]) return row[index];
          }
          return "";
        };
        const title = get("title", "job title", "role");
        const description = get(
          "description",
          "job description",
          "responsibilities front end"
        );
        return normalize(title + " " + description).length > 80
          ? [{ file, title, description }]
          : [];
      });
    });
}

function hasRoleTitleNoun(query) {
  return /\b(?:analyst|associate|assistant|manager|director|consultant|advisor|adviser|specialist|officer|banker|accountant|auditor|controller|engineer|developer|principal|avp|vp|vice president|intern|internship|trainee|graduate|executive|partner|supervisor|chief)\b/i.test(
    query
  );
}

function queryHasJobMatchSignal(query, job) {
  const q = normalize(query);
  const title = normalize(job.title || "");
  const description = normalize(job.description || "");
  return (
    (title && title.includes(q)) ||
    q
      .split(/\s+/)
      .filter((word) => word.length > 3)
      .some((word) => title.includes(word) || description.includes(word))
  );
}

const blockedQueryPattern =
  /\b(?:education|personal profile|personal statement|skills|projects|languages|qualifications|university|college|school|msc finance|bsc business|king's college|date|present|road|street|email|phone|linkedin)\b/i;
const sentenceLeakPattern =
  /\b(?:worked|working|responsible|supported|assisted|managed|led|developed|created|prepared|reported|collaborated|communicated|skilled|passionate|keen|experience|expertise|currently|seeking|targeting)\b/i;

async function main() {
  const cvs = loadCvTexts();
  const uploadJobs = loadUploadBookJobs();
  const focusCvText =
    (cvs.find((cv) => cv.name === "67164a0f476b8.pdf") || cvs[0] || {}).text ||
    "";
  const browser = await chromium.launch();
  const page = await browser.newPage();
  const pageErrors = [];
  page.on("pageerror", (error) => pageErrors.push(error.message));

  await page.setContent(
    `<!doctype html>
    <html>
      <head></head>
      <body>
        <section class="sffc-crm-apply-chat" data-sffc-apply-chat data-role-title="" data-role-company="" data-role-location="">
          <button type="button" data-sffc-apply-chat-open></button>
          <section data-sffc-apply-chat-desk hidden>
            <div data-sffc-apply-chat-desk-body>
              <div data-sffc-apply-chat-conversation-stage>
                <div data-sffc-apply-chat-messages></div>
              </div>
              <div data-sffc-apply-chat-results-stage>
                <div data-sffc-apply-chat-results-empty></div>
                <div data-sffc-apply-chat-results-body></div>
              </div>
              <form data-sffc-apply-chat-composer>
                <input data-sffc-apply-chat-input />
                <input data-sffc-apply-chat-file type="file" />
                <button data-sffc-apply-chat-upload type="button"></button>
              </form>
            </div>
          </section>
        </section>
      </body>
    </html>`
  );
  await page.addInitScript(() => {
    window.sffcCrmApplyChatArticle = {
      enableTestHooks: true,
      isLoggedIn: false,
      ajaxUrl: "/wp-admin/admin-ajax.php",
    };
  });
  await page.addScriptTag({ path: chatScript });
  await page.evaluate(() => {
    window.sffcCrmApplyChatArticle = {
      enableTestHooks: true,
      isLoggedIn: false,
      ajaxUrl: "/wp-admin/admin-ajax.php",
    };
    document.dispatchEvent(new Event("DOMContentLoaded", { bubbles: true }));
  });

  const ready = await page.evaluate(() => {
    const root = document.querySelector("[data-sffc-apply-chat]");
    return !!(root && root.__sffcApplyChatTest);
  });
  if (!ready) {
    throw new Error("Apply chat test hook did not initialise.");
  }

  const failures = [];
  const qualityFailures = [];
  const samples = [];
  let focusCv = null;
  let uploadBookPairs = 0;
  let uploadBookMatched = 0;

  for (const cv of cvs) {
    const queries = await page.evaluate((payload) => {
      const root = document.querySelector("[data-sffc-apply-chat]");
      root.__sffcApplyChatTest.setCapturedCvText(payload.text);
      return root.__sffcApplyChatTest.getRoleDiscoveryQueries({});
    }, cv);
    const bad = queries.find((query) => {
      const normalized = normalize(query);
      return (
        blockedQueryPattern.test(query) ||
        sentenceLeakPattern.test(query) ||
        query.split(/\s+/).length > 5 ||
        !hasRoleTitleNoun(query) ||
        /^(?:finance|investment|analysis|management|education)$/i.test(
          normalized
        )
      );
    });
    if (!queries.length || bad) {
      failures.push({ cv: cv.name, queries, bad });
    }
    if (cv.name === "67164a0f476b8.pdf") {
      focusCv = { cv: cv.name, queries: queries.slice(0, 10) };
      const first = normalize(queries[0] || "");
      if (
        !/^(?:investment analyst|financial analyst|portfolio analyst|asset management analyst)$/.test(
          first
        )
      ) {
        qualityFailures.push({
          cv: cv.name,
          reason: "finance CV should start with a finance role query",
          queries,
        });
      }
    }
    if (samples.length < 12) {
      samples.push({ cv: cv.name, queries: queries.slice(0, 5) });
    }
  }

  for (const cv of cvs.slice(0, 80)) {
    const queries = await page.evaluate((payload) => {
      const root = document.querySelector("[data-sffc-apply-chat]");
      root.__sffcApplyChatTest.setCapturedCvText(payload.text);
      return root.__sffcApplyChatTest.getRoleDiscoveryQueries({});
    }, cv);
    for (const job of uploadJobs) {
      uploadBookPairs += 1;
      if (queries.some((query) => queryHasJobMatchSignal(query, job))) {
        uploadBookMatched += 1;
      }
    }
  }

  const visibleFlow = await page.evaluate(async (text) => {
    const root = document.querySelector("[data-sffc-apply-chat]");
    const messages = document.querySelector("[data-sffc-apply-chat-messages]");
    const calls = [];
    messages.innerHTML = "";
    window.fetch = function (_url, options) {
      const body = options && options.body;
      const query = body && typeof body.get === "function" ? body.get("query") : "";
      calls.push(String(query || ""));
      const items =
        String(query || "").toLowerCase() === "investment analyst"
          ? [
              {
                post_id: "test-post-1",
                jobs_post_id: "test-jobs-1",
                wp_post_id: "test-wp-1",
                title: "Investment Analyst",
                role_title: "Investment Analyst",
                company: "Test Capital",
                location: "Dubai, United Arab Emirates",
                seniority: "analyst",
                sector: "asset_management",
                domain_label: "Asset Management",
                fit_label: "To Consider",
                score: 3.4,
                posted_label: "Live now",
                view_url: "#",
                application_url: "#",
              },
            ]
          : [];
      return Promise.resolve({
        json: function () {
          return Promise.resolve({ success: true, data: { items } });
        },
        text: function () {
          return Promise.resolve(
            JSON.stringify({ success: true, data: { items } })
          );
        },
      });
    };
    root.__sffcApplyChatTest.setCapturedCvText(text);
    await root.__sffcApplyChatTest.showRoleSelection({}, "67164a0f476b8.pdf");
    await new Promise((resolve) => setTimeout(resolve, 6500));
    return {
      calls,
      text: messages.textContent || "",
      resultCards: messages.querySelectorAll(
        ".sffc-community-editorial__post-result"
      ).length,
    };
  }, focusCvText);

  if (
    normalize(visibleFlow.calls[0] || "") !== "investment analyst" ||
    visibleFlow.calls.some((call) => normalize(call) === "education") ||
    !/i found 1 current job posts? for investment analyst/i.test(visibleFlow.text) ||
    !/investment analyst/i.test(visibleFlow.text) ||
    visibleFlow.resultCards < 1
  ) {
    qualityFailures.push({
      cv: "67164a0f476b8.pdf",
      reason: "real chat role-selection flow did not search/render the expected CV role",
      visibleFlow,
    });
  }

  const insightCases = await page.evaluate(async () => {
    const root = document.querySelector("[data-sffc-apply-chat]");
    const messages = document.querySelector("[data-sffc-apply-chat-messages]");
    const cases = [
      {
        name: "backend Arabic language warning",
        analysis: {
          quick_role_insights: [
            {
              title: "Arabic is not visible",
              message:
                "I noticed this role appears to require Arabic, but that language is not visible on the CV. If you use it professionally, it should be visible before you apply.",
              tone: "gap",
              type: "language",
            },
          ],
        },
        expected: ["Application signals", "Arabic is not visible", "After this one is submitted"],
        expectedClass: "is-gap",
      },
      {
        name: "missing hard skill fallback",
        analysis: {
          unconfirmed_signals: [
            {
              category: "hard_skill",
              label: "financial modelling",
              needs_confirmation_if_unmatched: true,
            },
          ],
        },
        expected: ["Application signals", "financial modelling", "does not make that evidence obvious"],
        expectedClass: "is-watch",
      },
      {
        name: "missing qualification fallback",
        analysis: {
          requirement_gaps: [
            {
              type: "qualification",
              label: "ACCA",
              message:
                "I cannot confirm ACCA from the CV yet, so Emily needs to check that with you rather than assume it.",
            },
          ],
        },
        expected: ["Application signals", "ACCA", "does not mention that qualification"],
        expectedClass: "is-gap",
      },
      {
        name: "sector gap fallback",
        analysis: {
          cv_signal_profile: {
            sectors: [
              {
                label: "Renewable Energy",
                cv_score: 0,
                role_score: 12,
              },
            ],
          },
        },
        expected: ["Application signals", "Renewable Energy", "sector exposure"],
        expectedClass: "is-watch",
      },
      {
        name: "expanded finance keyword fallback",
        analysis: {
          unconfirmed_signals: [
            {
              category: "hard_skill",
              label: "investment committee materials",
              needs_confirmation_if_unmatched: true,
            },
            {
              category: "hard_skill",
              label: "credit underwriting",
              needs_confirmation_if_unmatched: true,
            },
          ],
        },
        expected: ["investment committee materials", "credit underwriting"],
        expectedClass: "is-watch",
      },
    ];
    const rendered = cases.map((item) => {
      const html = root.__sffcApplyChatTest.renderQuickRoleInsights(
        item.analysis
      );
      const holder = document.createElement("div");
      holder.innerHTML = html;
      const text = holder.textContent || "";
      return {
        name: item.name,
        text,
        html,
        cardCount: holder.querySelectorAll(
          ".sffc-crm-apply-chat__quick-insight"
        ).length,
        hasTone: !!holder.querySelector(
          ".sffc-crm-apply-chat__quick-insight." + item.expectedClass
        ),
        passed:
          item.expected.every((needle) => text.indexOf(needle) !== -1) &&
          holder.querySelectorAll(".sffc-crm-apply-chat__quick-insight").length >
            0 &&
          !!holder.querySelector(
            ".sffc-crm-apply-chat__quick-insight." + item.expectedClass
          ),
      };
    });

    messages.innerHTML = "";
    root.__sffcApplyChatTest.setRoleContext({
      roleTitle: "Investment Analyst",
      activePath: "apply_for_me",
    });
    root.__sffcApplyChatTest.continueApplyAfterAnalysis({
      company_signal: "current experience",
      experience_title: "Financial Analyst",
      matched_keywords: ["valuation"],
      missing_keywords: ["Arabic"],
      confirmed_signals: [
        {
          category: "hard_skill",
          label: "valuation",
          safe_to_state_if_matched: true,
        },
      ],
      adjacent_signals: [],
      unconfirmed_signals: [
        {
          category: "hard_skill",
          label: "financial modelling",
          needs_confirmation_if_unmatched: true,
        },
      ],
      quick_role_insights: [
        {
          title: "Financial modelling is not obvious yet",
          message:
            "I noticed the role asks for financial modelling, but the CV does not make that evidence obvious yet. If it is part of your background, I can help bring it forward before you apply.",
          tone: "watch",
          type: "missing_signal",
          label: "financial modelling",
        },
      ],
      requirement_gaps: [],
      issue_count: 4,
      has_numbers: true,
    });
    await new Promise((resolve) => setTimeout(resolve, 26000));
    return {
      rendered,
      analysis_text: messages.textContent || "",
    };
  });

  const failedInsightCases = insightCases.rendered.filter((item) => !item.passed);
  if (
    failedInsightCases.length ||
    !/Application signals/i.test(insightCases.analysis_text) ||
    !/financial modelling/i.test(insightCases.analysis_text) ||
    !/does not make that evidence obvious yet/i.test(insightCases.analysis_text) ||
    !/After this one is submitted/i.test(insightCases.analysis_text) ||
    !/I just need your name and the email/i.test(insightCases.analysis_text) ||
    /Do you want me to add a cover letter/i.test(insightCases.analysis_text)
  ) {
    qualityFailures.push({
      cv: "synthetic-chat-insights",
      reason: "quick role insights did not render clearly in the real chat flow",
      failedInsightCases,
      analysisText: insightCases.analysis_text,
    });
  }

  const languageStress = await page.evaluate(async () => {
    const root = document.querySelector("[data-sffc-apply-chat]");
    const messages = document.querySelector("[data-sffc-apply-chat-messages]");
    const test = root.__sffcApplyChatTest;
    const q = (label, choices = [], name = "") => ({
      label,
      required: true,
      fields: [
        {
          name,
          values: choices.map((choice) => ({ label: choice, value: choice })),
        },
      ],
    });
    const schema = {
      questions: [
        q("First Name", [], "first_name"),
        q("Last Name", [], "last_name"),
        q("Email", [], "email"),
        q("What is your desired base salary?"),
        q("What is your current notice period?"),
        q("Data Use Declaration: I consent to the processing of my data.", [
          "Yes",
          "No",
        ]),
        q("How do you describe your gender identity?", [
          "Male",
          "Female",
          "Non-Binary",
          "Non-confirming",
          "Prefer not to Say",
        ]),
        q("What is your ethnic group or background?", [
          "American Indian or Alaska Native",
          "Arab",
          "Asian",
          "Black or African American",
          "Hispanic or Latino",
          "Native Hawaiian or Other Pacific Islander",
          "White",
          "Two or More Races",
        ]),
        q("Do you Identify as LGBTQ+?", ["Yes", "No", "Prefer Not to Say"]),
        q("Are you living with a disability?", ["Yes", "No"]),
        q("Are you a Veteran?", ["Yes", "No"]),
        q("Are you a Parent or Care-taker?", ["Yes", "No"]),
        q("How many languages do you speak?", [
          "1 Language",
          "1-2 Languages",
          "2-4 Languages",
          "4+ Languages",
        ]),
        q("Are you local to the office applied to?", ["Yes", "No"]),
        q("Do you require a visa, to work in the country you are applying to?", [
          "Yes",
          "No",
        ]),
        q("What is your highest level of education?", [
          "Doctorate (PHD or Equivalent)",
          "Masters Degree (MA/MSc or Equivalent)",
          "Bachelors Degree (BA/BSc or Equivalent)",
          "College Diploma or Equivalent",
          "Secondary School or Equivalent",
          "Apprenticeship or Vocational Qualification",
          "No Formal Education",
          "Other",
          "Prefer not to Say",
        ]),
        q("How many days do you currently work from the office?"),
        q("Are you able to work from the CD office 4 days per week?", [
          "Yes",
          "No",
        ]),
      ],
    };
    const choiceCases = [
      {
        name: "case-insensitive yes",
        actual: test.normalizeChoiceAnswer("i agree", ["Yes", "No"]),
        expected: "Yes",
      },
      {
        name: "lowercase male",
        actual: test.normalizeChoiceAnswer("male", ["Male", "Female"]),
        expected: "Male",
      },
      {
        name: "non binary spelling",
        actual: test.normalizeChoiceAnswer("non binary", [
          "Non-Binary",
          "Non-confirming",
        ]),
        expected: "Non-Binary",
      },
      {
        name: "prefer not shorthand",
        actual: test.normalizeChoiceAnswer("prefer not say", [
          "Yes",
          "No",
          "Prefer Not to Say",
        ]),
        expected: "Prefer Not to Say",
      },
      {
        name: "bachelors shorthand",
        actual: test.normalizeChoiceAnswer("bachelors", [
          "Masters Degree (MA/MSc or Equivalent)",
          "Bachelors Degree (BA/BSc or Equivalent)",
        ]),
        expected: "Bachelors Degree (BA/BSc or Equivalent)",
      },
      {
        name: "one language numeric",
        actual: test.normalizeChoiceAnswer("1", [
          "1 Language",
          "1-2 Languages",
          "2-4 Languages",
          "4+ Languages",
        ]),
        expected: "1 Language",
      },
    ];
    const detailCases = [
      {
        name: "extract name from phrase",
        actual: test.extractReasonableFullName("my full name is Luca Valentino Rosati thanks"),
        expected: "Luca Valentino Rosati",
      },
      {
        name: "literal email",
        actual: test.analysePreferredEmail("use RossVltn@Gmail.con", "").confirmed,
        expected: "rossvltn@gmail.com",
      },
      {
        name: "obfuscated email",
        actual: test.analysePreferredEmail("rossvltn at gmail dot com", "").confirmed,
        expected: "rossvltn@gmail.com",
      },
      {
        name: "security code phrase preserves case",
        actual: test.extractSecurityCode("the code is netVJV2Y thanks"),
        expected: "netVJV2Y",
      },
    ];
    const normalizationCases = [
      {
        name: "long compliance interest with certifications",
        input:
          "Greeting, I am seeking a new opportunity in Compliance, with extensive experience as: 1-Head of Compliance & AML/CFT. 2-Manager of KYC & Account Opening. I hold Compliance certifications: CCO, CFCS, CME-1, CME-2 and expertise in compliance policies, risk management, and regulatory adherence.",
        expected: [
          "new opportunities",
          "compliance",
          "head of compliance",
          "aml",
          "cft",
          "manager",
          "kyc",
          "cco",
          "cfcs",
          "cme",
          "risk management",
        ],
      },
      {
        name: "polite head of compliance interest",
        input:
          "I hope you are doing well. I noticed that you have an open position for Head Of Compliance and I would like to express my interest in the opportunity. I have over 9 years of experience in Compliance, Contracts Management, and Governance, and I believe my background could be a good match for the role.",
        expected: [
          "open role",
          "head of compliance",
          "interested",
          "9 years",
          "contracts management",
          "governance",
          "match",
        ],
      },
      {
        name: "uae valuation big four request",
        input:
          "Hope you are doing well. I am looking for a job opportunity in UAE. I have 15 years of experience in the field of valuation and finance primarily with Big4 firms. Please suggest. Thank",
        expected: [
          "looking for",
          "job opportunity",
          "united arab emirates",
          "15 years",
          "valuation",
          "finance",
          "big4",
        ],
      },
      {
        name: "role and similar-location transfer",
        input:
          "I came across the Investment Operations Analyst role in Riyadh and am interested in the opportunity. I wanted to reach out to see if there are any similar opportunities available in Dubai. If not, I am excited to explore this role, as it closely aligns with my experience and interests.",
        expected: [
          "i found",
          "investment operations analyst",
          "riyadh",
          "similar opportunities",
          "available",
          "dubai",
          "explore this role",
        ],
      },
      {
        name: "career typos and gulf locations",
        input:
          "complience finacial investement private equitiy analystt oppertunity assesment recomend Riyad Dubia Quatar",
        expected: [
          "compliance",
          "financial",
          "investment",
          "private equity",
          "analyst",
          "opportunity",
          "assessment",
          "recommend",
          "riyadh",
          "dubai",
          "qatar",
        ],
      },
      {
        name: "finance acronyms are preserved",
        input: "EBIDTA EBITDA IRR MOIC AUM NAV LBO DCF FP&A IFRS GAAP CFA ACCA Big 4 ADGM DIFC PIF M&A",
        expected: [
          "ebitda",
          "irr",
          "moic",
          "aum",
          "nav",
          "lbo",
          "dcf",
          "fp&a",
          "ifrs",
          "gaap",
          "cfa",
          "acca",
          "big 4",
          "adgm",
          "difc",
          "pif",
          "m&a",
        ],
      },
    ].map((item) => {
      const normalized = test.normalizeCareerIntentText(item.input);
      return {
        name: item.name,
        normalized,
        passed: item.expected.every((expected) => normalized.includes(expected)),
        missing: item.expected.filter((expected) => !normalized.includes(expected)),
      };
    });
    const allowedOpportunityIntents = [
      "matching_jobs",
      "role_targeting",
      "role_help",
      "specific_vacancy_details",
      "profile_fit",
      "career_direction",
      "market_opportunities",
      "leadership_transition_strategy",
    ];
    const intentCases = [
      {
        name: "long compliance request",
        input:
          "Greeting, I am seeking a new opportunity in Compliance, with extensive experience as Head of Compliance and AML/CFT Manager of KYC.",
        allowed: allowedOpportunityIntents,
      },
      {
        name: "uae valuation request",
        input:
          "Hope you are doing well. I am looking for a job opportunity in UAE. I have 15 years of experience in valuation and finance with Big4 firms. Please suggest.",
        allowed: allowedOpportunityIntents,
      },
      {
        name: "specific role plus alternatives",
        input:
          "I came across the Investment Operations Analyst role in Riyadh and am interested. Are there any similar opportunities available in Dubai?",
        allowed: allowedOpportunityIntents.concat(["role_question"]),
      },
      {
        name: "hiya greeting",
        input: "Hiya",
        allowed: ["greeting", "social_check"],
      },
      {
        name: "yo greeting",
        input: "Yo!!",
        allowed: ["greeting", "social_check"],
      },
      {
        name: "social check",
        input: "Hi Emily, are you good",
        allowed: ["social_check", "greeting"],
      },
      {
        name: "frustration not abuse",
        input: "wtf this sounds like a waste of time",
        allowed: ["reassurance", "role_question", "market_opportunities", ""],
      },
    ].map((item) => {
      const actual = test.detectIntent(item.input);
      const semantics = test.parseMessageSemantics(item.input);
      return {
        name: item.name,
        actual,
        lower: semantics.lower,
        abusive: !!semantics.abusive_profanity,
        passed:
          item.allowed.includes(actual) &&
          (item.name !== "frustration not abuse" || !semantics.abusive_profanity),
      };
    });
    const bulk = test.parseEmployerAnswers(
      "salary 40,000; notice 1 month; data consent yes; gender male; ethnicity white; LGBTQ no; disability no; veteran no; parent no; languages 1; local yes; visa no; education bachelors; office days 4; 4-day office yes",
      {
        questions: schema.questions.slice(3),
      }
    );
    const ordered = test.parseEmployerAnswers(
      "40000, 1 month, yes, male, white, no, no, no, no, 1, yes, no, bachelors, 4, yes",
      {
        questions: schema.questions.slice(3),
      }
    );
    messages.innerHTML = test.renderQuickRoleInsights({
      quick_role_insights: [
        {
          title: "Arabic is not visible",
          message:
            "I noticed this role asks for Arabic, but that language is not visible on the CV. If you use it professionally, it should be visible before you apply.",
          tone: "gap",
          type: "language",
        },
      ],
    });
    const insightText = messages.textContent || "";
    const missingContact = test.detectCvProfileGapInsights(
      "Luca Rosati\nInvestment Analyst\nBuilt LBO models and prepared investment committee materials."
    );
    const completeContact = test.detectCvProfileGapInsights(
      "Luca Rosati\nLondon, UK\nross@example.com\n+44 7700 900000\nhttps://www.linkedin.com/in/lucar\nInvestment Analyst\nBuilt LBO models and prepared investment committee materials."
    );
    test.setCapturedCvText(
      "Luca Rosati\nInvestment Analyst\nBuilt LBO models and prepared investment committee materials."
    );
    const missingContactHtml = test.renderQuickRoleInsights({});
    const missingContactHolder = document.createElement("div");
    missingContactHolder.innerHTML = missingContactHtml;
    const missingContactText = missingContactHolder.textContent || "";
    const repeatedCannotSeeCount = (
      missingContactText.match(/I cannot see/gi) || []
    ).length;
    test.setCapturedCvText(
      "Maya Haddad\nHead of Compliance and AML/CFT\nManaged KYC and account opening controls, compliance policies, risk management, regulatory adherence, governance and sanctions screening."
    );
    test.setRoleContext({
      activePath: "apply_for_me_role_discovery",
      jobSearchSeedContext: {
        roleTarget: "investment banking",
        query: "investment banking",
      },
    });
    const staleContextQueries = test.getRoleDiscoveryQueries({
      experience_title: "Head of Compliance",
      matched_keywords: ["compliance", "kyc", "aml", "risk management"],
    });
    return {
      choiceCases,
      detailCases,
      bulk,
      ordered,
      insightText,
      missingContact,
      completeContact,
      missingContactText,
      repeatedCannotSeeCount,
      normalizationCases,
      intentCases,
      staleContextQueries,
      passed:
        choiceCases.every((item) => item.actual === item.expected) &&
        detailCases.every((item) => item.actual === item.expected) &&
        normalizationCases.every((item) => item.passed) &&
        intentCases.every((item) => item.passed) &&
        bulk.applied >= 15 &&
        Object.values(bulk.answers).includes("Bachelors Degree (BA/BSc or Equivalent)") &&
        Object.values(bulk.answers).includes("1 Language") &&
        ordered.applied >= 15 &&
        Object.values(ordered.answers).includes("Bachelors Degree (BA/BSc or Equivalent)") &&
        Object.values(ordered.answers).includes("1 Language") &&
        /Arabic is not visible/.test(insightText) &&
        missingContact.length >= 3 &&
        completeContact.length === 0 &&
        /CV contact section is missing an email address/.test(missingContactText) &&
        /CV does not show a phone number/.test(missingContactText) &&
        missingContact.some((item) => /LinkedIn link would make this stronger/.test(item.message || "")) &&
        staleContextQueries[0] !== "investment banking" &&
        staleContextQueries.some((query) => /compliance|risk/i.test(query)) &&
        repeatedCannotSeeCount === 0,
    };
  });

  if (!languageStress.passed) {
    qualityFailures.push({
      cv: "language-processing-stress",
      reason: "language processing stress test failed",
      languageStress,
    });
  }

  await browser.close();

  const result = {
    chat_flow: "sffc-crm-apply-chat",
    cvs_tested: cvs.length,
    upload_book_jobs_sampled: uploadJobs.length,
    upload_book_query_pairs: uploadBookPairs,
    upload_book_pairs_with_query_signal: uploadBookMatched,
    page_errors: pageErrors,
    failures: failures.length,
    quality_failures: qualityFailures.length,
    focus_cv: focusCv,
    visible_flow: {
      fetch_queries: visibleFlow.calls,
      result_cards: visibleFlow.resultCards,
      message_sample: clean(visibleFlow.text).slice(0, 500),
    },
    insight_flow: {
      cases: insightCases.rendered.map((item) => ({
        name: item.name,
        passed: item.passed,
        text: item.text,
      })),
      analysis_message_sample: clean(insightCases.analysis_text).slice(0, 600),
    },
    language_stress: languageStress,
    samples,
    failed_samples: failures.slice(0, 10),
    quality_failed_samples: qualityFailures.slice(0, 10),
  };
  console.log(JSON.stringify(result, null, 2));
  if (pageErrors.length || failures.length || qualityFailures.length) {
    process.exit(1);
  }
}

main().catch(async (error) => {
  console.error(error);
  process.exit(1);
});
