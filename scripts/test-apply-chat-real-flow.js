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
          "descriptions",
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

  const selectedJobLaunch = await page.evaluate(() => {
    const root = document.querySelector("[data-sffc-apply-chat]");
    root.__sffcApplyChatTest.setSelectedJobContext({
      roleTitle: "Director, Finance & Asset Performance Film Studios",
      roleCompany: "Qiddiya Investment Company",
      roleLocation: "Riyadh, Saudi Arabia",
      postId: "123",
      jobsPostId: "456",
      applicationUrl: "https://apply.workable.com/qiddiya-investment-company-1/",
    });
    return {
      hasSelectedJobContext: root.__sffcApplyChatTest.hasSelectedJobContext(),
      queries: root.__sffcApplyChatTest.getRoleDiscoveryQueries({}).slice(0, 3),
    };
  });

  if (
    !selectedJobLaunch.hasSelectedJobContext ||
    !/^director,?\s+finance\s+and\s+asset\s+performance\s+film\s+studios$/i.test(
      normalize(selectedJobLaunch.queries[0] || "")
    )
  ) {
    qualityFailures.push({
      reason: "detected job launch context was not preserved before CV analysis",
      selectedJobLaunch,
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
        expected: [
          "Before you apply",
          "Application could be stronger",
          "Arabic is not visible",
          "Tailor my CV to this role",
        ],
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
        expected: [
          "Application could be stronger",
          "financial modelling",
          "does not make that evidence obvious",
          "Apply with current CV",
        ],
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
        expected: [
          "Application could be stronger",
          "ACCA",
          "does not mention that qualification",
          "Missing from CV or not obvious enough",
        ],
        expectedClass: "is-gap",
      },
      {
        name: "non qualification function gap is not called a qualification",
        analysis: {
          requirement_gaps: [
            {
              type: "qualification",
              label: "Corporate Finance",
              message:
                "I cannot confirm Corporate Finance from the CV yet, so Emily needs to check that with you rather than assume it.",
            },
          ],
        },
        expected: [
          "Application could be stronger",
          "Corporate Finance",
          "does not make that evidence obvious",
        ],
        forbidden: ["does not mention that qualification", "Corporate Finance is not on the CV"],
        expectedClass: "is-gap",
      },
      {
        name: "footer keywords are suppressed from problem chips",
        analysis: {
          missing_keywords: [
            "budgeting",
            "asset management",
            "private equity",
            "software",
            "technology",
            "Corporate Finance",
          ],
          quick_role_insights: [
            {
              title: "Budgeting is not obvious yet",
              message:
                "I noticed the role asks for budgeting, but the CV does not make that evidence obvious yet.",
              tone: "watch",
              type: "missing_signal",
              label: "budgeting",
            },
          ],
          requirement_gaps: [],
        },
        roleContext: {
          roleTitle: "Director, Finance & Asset Performance Film Studios",
          roleCompany: "Qiddiya Investment Company",
          roleLocation: "Riyadh, Saudi Arabia",
          roleSector: "corporate",
        },
        expected: ["budgeting", "asset management"],
        forbidden: ["private equity", "software", "technology", "Corporate Finance"],
        expectedClass: "",
        allowNoInsightCard: true,
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
        expected: [
          "Application could be stronger",
          "Renewable Energy",
          "sector exposure",
          "Role match",
        ],
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
      if (item.roleContext) {
        root.__sffcApplyChatTest.setRoleContext(item.roleContext);
      }
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
        hasTone: item.expectedClass
          ? !!holder.querySelector(
              ".sffc-crm-apply-chat__quick-insight." + item.expectedClass
            )
          : true,
        passed:
          item.expected.every((needle) => text.indexOf(needle) !== -1) &&
          !(item.forbidden || []).some((needle) => text.indexOf(needle) !== -1) &&
          (item.allowNoInsightCard ||
            holder.querySelectorAll(".sffc-crm-apply-chat__quick-insight").length >
              0) &&
          (!item.expectedClass ||
            !!holder.querySelector(
              ".sffc-crm-apply-chat__quick-insight." + item.expectedClass
            )),
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
    /Before you apply/i.test(insightCases.analysis_text) ||
    /Application could be stronger/i.test(insightCases.analysis_text) ||
    /Tailor my CV to this role/i.test(insightCases.analysis_text) ||
    !/Ok, let.s process your application/i.test(insightCases.analysis_text) ||
    !/Firstly, can I get your full name/i.test(insightCases.analysis_text) ||
    /Do you want me to add a cover letter/i.test(insightCases.analysis_text)
  ) {
    qualityFailures.push({
      cv: "synthetic-chat-insights",
      reason: "normal selected-role upload should collect details instead of showing quick role insights",
      failedInsightCases,
      analysisText: insightCases.analysis_text,
    });
  }

  const explicitComparisonFlow = await page.evaluate(async () => {
    const root = document.querySelector("[data-sffc-apply-chat]");
    const messages = document.querySelector("[data-sffc-apply-chat-messages]");
    const composer = document.querySelector("[data-sffc-apply-chat-composer]");
    const input = document.querySelector("[data-sffc-apply-chat-input]");
    messages.innerHTML = "";
    root.__sffcApplyChatTest.setRoleContext({
      roleTitle: "Director, Finance & Asset Performance Film Studios",
      roleCompany: "Qiddiya Investment Company",
      activePath: "apply_for_me",
    });
    root.__sffcApplyChatTest.continueApplyAfterAnalysis({
      company_signal: "Qiddiya Investment Company",
      experience_title: "Finance Manager",
      matched_keywords: ["asset performance"],
      missing_keywords: ["film studios", "asset performance"],
      quick_role_insights: [
        {
          title: "Asset performance needs stronger evidence",
          message:
            "I noticed the role asks for asset performance, but the CV does not make that evidence obvious yet.",
          tone: "watch",
          type: "missing_signal",
          label: "asset performance",
        },
      ],
      requirement_gaps: [],
    });
    for (let i = 0; i < 18; i += 1) {
      if (/Firstly, can I get your full name/i.test(messages.textContent || "")) {
        break;
      }
      await new Promise((resolve) => setTimeout(resolve, 500));
    }
    input.value = "compare my CV against this role";
    composer.dispatchEvent(
      new Event("submit", { bubbles: true, cancelable: true })
    );
    let quickInsights = null;
    for (let i = 0; i < 16; i += 1) {
      quickInsights = messages.querySelector(".sffc-crm-apply-chat__quick-insights");
      if (quickInsights) break;
      await new Promise((resolve) => setTimeout(resolve, 500));
    }
    const quickInsightsMessage =
      quickInsights && quickInsights.closest(".sffc-crm-apply-chat__message");
    return {
      text: messages.textContent || "",
      quickInsightsFound: !!quickInsights,
      hasTailorButton: !!messages.querySelector(
        ".sffc-crm-apply-chat__quick-insights-primary"
      ),
      quickInsightsWorkspace:
        !!quickInsightsMessage &&
        quickInsightsMessage.classList.contains("has-workspace-card") &&
        !quickInsights.closest(".sffc-crm-apply-chat__bubble"),
    };
  });

  if (
    !explicitComparisonFlow.quickInsightsFound ||
    !/Before you apply/i.test(explicitComparisonFlow.text) ||
    !/Application could be stronger/i.test(explicitComparisonFlow.text) ||
    !/Asset performance/i.test(explicitComparisonFlow.text) ||
    explicitComparisonFlow.hasTailorButton ||
    !explicitComparisonFlow.quickInsightsWorkspace ||
    /Ok, I.ll tailor the CV to this role|Say it in your own words|I can tell you what is dragging|I usually look at role fit/i.test(
      explicitComparisonFlow.text
    )
  ) {
    qualityFailures.push({
      reason: "explicit CV comparison did not render the read-only insight card cleanly",
      explicitComparisonFlow,
    });
  }

  const queueRemovalFlow = await page.evaluate(async () => {
    const root = document.querySelector("[data-sffc-apply-chat]");
    const messages = document.querySelector("[data-sffc-apply-chat-messages]");
    root.__sffcApplyChatTest.resetCommercialApplyFlowState();
    messages.innerHTML = "";
    root.__sffcApplyChatTest.renderCommercialQueueForReview([
      {
        title: "Investment Analyst",
        company: "Parrot Analytics",
        location: "Qatar (Remote)",
        applyUrl: "https://example.com/parrot/apply",
      },
      {
        title: "Capital Transactions Intern",
        company: "ESR Group",
        location: "Singapore",
        applyUrl: "https://example.com/esr/apply",
      },
    ]);
    const beforeCard = messages.querySelector(
      ".sffc-crm-apply-chat__apply-queue-card"
    );
    const beforeText = beforeCard ? beforeCard.textContent || "" : "";
    const removeButton = beforeCard
      ? beforeCard.querySelector(
          ".sffc-crm-apply-chat__apply-queue-row-action.is-remove"
        )
      : null;
    if (removeButton) {
      removeButton.click();
    }
    await new Promise((resolve) => setTimeout(resolve, 100));
    const afterCard = messages.querySelector(
      ".sffc-crm-apply-chat__apply-queue-card"
    );
    const afterText = afterCard ? afterCard.textContent || "" : "";
    const stateAfterRemove =
      root.__sffcApplyChatTest.getCommercialApplyQueueState();
    const allJobsTab = afterCard
      ? afterCard.querySelector('[data-sffc-apply-chat-queue-tab="all"]')
      : null;
    if (allJobsTab) {
      allJobsTab.click();
    }
    await new Promise((resolve) => setTimeout(resolve, 100));
    const allJobsCard = messages.querySelector(
      ".sffc-crm-apply-chat__apply-queue-card"
    );
    const allJobsText = allJobsCard ? allJobsCard.textContent || "" : "";
    return {
      beforeText,
      afterText,
      allJobsText,
      removeButtonFound: !!removeButton,
      stateAfterRemoveCount: stateAfterRemove.length,
      stateAfterRemoveTitles: stateAfterRemove.map((item) => item.title),
    };
  });

  if (
    !queueRemovalFlow.removeButtonFound ||
    !/Investment Analyst/i.test(queueRemovalFlow.beforeText) ||
    /Investment Analyst/i.test(queueRemovalFlow.afterText) ||
    !/Capital Transactions Intern/i.test(queueRemovalFlow.afterText) ||
    queueRemovalFlow.stateAfterRemoveCount !== 1 ||
    queueRemovalFlow.stateAfterRemoveTitles.includes("Investment Analyst")
  ) {
    qualityFailures.push({
      reason:
        "apply queue remove action did not remove the role from the candidate queue card",
      queueRemovalFlow,
    });
  }

  const successFactorsCommandFlow = await page.evaluate(async () => {
    window.sffcCrmApplyChatArticle = Object.assign(
      {},
      window.sffcCrmApplyChatArticle || {},
      {
        isAdminTester: true,
        jobsSearchNonce: "test-nonce",
      }
    );
    window.__sffcSuccessFactorsProviderRequest = null;
    const originalFetch = window.fetch;
    window.fetch = function (url, options) {
      const body = options && options.body;
      const entries = {};
      if (body && typeof body.forEach === "function") {
        body.forEach((value, key) => {
          entries[key] = String(value);
        });
      }
      if (entries.action === "sffc_crm_apply_chat_queue_application_task") {
        window.__sffcSuccessFactorsQueueRequest = {
          url: String(url || ""),
          entries,
        };
        return Promise.resolve(
          new Response(
            JSON.stringify({
              success: true,
              data: {
                task_uuid: "sf-test-task-1",
              },
            }),
            { status: 200, headers: { "Content-Type": "application/json" } }
          )
        );
      }
      window.__sffcSuccessFactorsProviderRequest = {
        url: String(url || ""),
        entries,
      };
      return Promise.resolve(
        new Response(
          JSON.stringify({
            success: true,
            data: {
              provider: "successfactors",
              query: "successfactors",
              items: [
                {
                  title: "Investment Analyst",
                  company: "Commercial Bank",
                  location: "Qatar",
                  apply_url:
                    "https://career2.successfactors.eu/career?company=thecommerc&career_ns=job_listing&career_job_req_id=7725",
                  application_workspace_url:
                    "https://career2.successfactors.eu/career?company=thecommerc&career_ns=job_application&career_job_req_id=7725",
                  source_platform: "SAP SuccessFactors",
                },
                {
                  title: "Strategy Analyst",
                  company: "Elm",
                  location: "Riyadh",
                  apply_url:
                    "https://career.elm.sa/elm/job/Riyadh-Strategy-Analyst-12345/123456/",
                  application_workspace_url:
                    "https://career.elm.sa/elm/job/Riyadh-Strategy-Analyst-12345/123456/",
                  source_platform: "SAP SuccessFactors",
                },
                {
                  title: "Wrong Provider Role",
                  company: "Other",
                  location: "Dubai",
                  apply_url: "https://example.com/apply",
                  application_workspace_url: "https://example.com/apply",
                  source_platform: "Other",
                },
              ],
            },
          }),
          { status: 200, headers: { "Content-Type": "application/json" } }
        )
      );
    };
    const root = document.querySelector("[data-sffc-apply-chat]");
    const messages = document.querySelector("[data-sffc-apply-chat-messages]");
    const form = document.querySelector("[data-sffc-apply-chat-composer]");
    const input = document.querySelector("[data-sffc-apply-chat-input]");
    root.__sffcApplyChatTest.resetCommercialApplyFlowState();
    messages.innerHTML = "";
    input.value = "test successfactors";
    form.dispatchEvent(new Event("submit", { bubbles: true, cancelable: true }));
    for (let index = 0; index < 30; index += 1) {
      await new Promise((resolve) => setTimeout(resolve, 250));
      if (/Upload the test CV first/i.test(messages.textContent || "")) {
        break;
      }
    }
    const state = root.__sffcApplyChatTest.getCommercialApplyQueueState();
    window.fetch = originalFetch;
    return {
      text: messages.textContent || "",
      request: window.__sffcSuccessFactorsProviderRequest,
      enabled: root.__sffcApplyChatTest.isSuccessFactorsAdminTestEnabled(),
      queueCount: state.length,
      hasAutoApplyQueue: !!messages.querySelector(".sffc-crm-apply-chat__apply-queue-card"),
      titles: state.map((item) => item.title),
      providers: state.map(
        (item) => item.autoSubmitProvider || item.auto_submit_provider || item.provider || ""
      ),
      successFactorsFlags: state.map((item) =>
        root.__sffcApplyChatTest.isSuccessFactorsQueueItem(item)
      ),
    };
  });

  if (
    successFactorsCommandFlow.request ||
    !successFactorsCommandFlow.enabled ||
    !/focused SuccessFactors test/i.test(successFactorsCommandFlow.text) ||
    !/Upload the test CV first/i.test(successFactorsCommandFlow.text) ||
    successFactorsCommandFlow.hasAutoApplyQueue ||
    successFactorsCommandFlow.queueCount !== 0
  ) {
    qualityFailures.push({
      reason:
        "test successfactors command did not start the lightweight setup conversation",
      successFactorsCommandFlow,
    });
  }

  const successFactorsSetupConversationFlow = await page.evaluate(async () => {
    const root = document.querySelector("[data-sffc-apply-chat]");
    const messages = document.querySelector("[data-sffc-apply-chat-messages]");
    const form = document.querySelector("[data-sffc-apply-chat-composer]");
    const input = document.querySelector("[data-sffc-apply-chat-input]");
    const test = root.__sffcApplyChatTest;
    const originalFetch = window.fetch;
    window.fetch = function (url, options) {
      const body = options && options.body;
      const entries = {};
      if (body && typeof body.forEach === "function") {
        body.forEach((value, key) => {
          entries[key] = String(value);
        });
      }
      if (entries.action === "sffc_crm_apply_chat_queue_application_task") {
        window.__sffcSuccessFactorsQueueRequest = {
          url: String(url || ""),
          entries,
        };
        return Promise.resolve(
          new Response(
            JSON.stringify({
              success: true,
              data: {
                task_uuid: "sf-test-task-1",
              },
            }),
            { status: 200, headers: { "Content-Type": "application/json" } }
          )
        );
      }
      window.__sffcSuccessFactorsProviderRequest = {
        url: String(url || ""),
        entries,
      };
      return Promise.resolve(
        new Response(
          JSON.stringify({
            success: true,
            data: {
              provider: "successfactors",
              query: "successfactors",
              items: [
                {
                  title: "Investment Analyst",
                  company: "Commercial Bank",
                  location: "Qatar",
                  apply_url:
                    "https://career2.successfactors.eu/career?company=thecommerc&career_ns=job_listing&career_job_req_id=7725",
                  application_workspace_url:
                    "https://career2.successfactors.eu/career?company=thecommerc&career_ns=job_application&career_job_req_id=7725",
                  source_platform: "SAP SuccessFactors",
                },
                {
                  title: "Strategy Analyst",
                  company: "Elm",
                  location: "Riyadh",
                  apply_url:
                    "https://career.elm.sa/elm/job/Riyadh-Strategy-Analyst-12345/123456/",
                  application_workspace_url:
                    "https://career.elm.sa/elm/job/Riyadh-Strategy-Analyst-12345/123456/",
                  source_platform: "SAP SuccessFactors",
                },
                {
                  title: "Wrong Provider Role",
                  company: "Other",
                  location: "Dubai",
                  apply_url: "https://example.com/apply",
                  application_workspace_url: "https://example.com/apply",
                  source_platform: "Other",
                },
              ],
            },
          }),
          { status: 200, headers: { "Content-Type": "application/json" } }
        )
      );
    };
    function submit(value) {
      input.value = value;
      form.dispatchEvent(new Event("submit", { bubbles: true, cancelable: true }));
    }
    async function waitFor(pattern) {
      for (let index = 0; index < 40; index += 1) {
        await new Promise((resolve) => setTimeout(resolve, 250));
        if (pattern.test(messages.textContent || "")) {
          return true;
        }
      }
      return false;
    }
    async function waitForPrompt(state) {
      for (let index = 0; index < 40; index += 1) {
        await new Promise((resolve) => setTimeout(resolve, 250));
        if (test.getPromptState && test.getPromptState() === state) {
          return true;
        }
      }
      return false;
    }
    test.setCurrentCvFileForTest("successfactors-test-cv.pdf");
    test.continueSuccessFactorsTestSetup();
    const askedName = await waitFor(/candidate full name/i);
    await waitForPrompt("successfactors_test_full_name");
    submit("Ross Valentino");
    const askedEmail = await waitFor(/What email should I use for the SuccessFactors test application/i);
    await waitForPrompt("successfactors_test_email");
    submit("rossvltn@gmail.com");
    const askedConfirm = await waitFor(/Just double-checking - is that rossvltn@gmail\.com/i);
    await waitForPrompt("successfactors_test_confirm_email");
    submit("yes");
    const showedPicker = await waitFor(/Choose one job to test/i);
    const state = test.getCommercialApplyQueueState();
    const testButtons = Array.from(
      messages.querySelectorAll("[data-sffc-successfactors-test-job]")
    );
    if (testButtons[0]) {
      testButtons[0].click();
    }
    const queued = await waitFor(/SuccessFactors test queued in Railway/i);
    window.fetch = originalFetch;
    return {
      text: messages.textContent || "",
      askedName,
      askedEmail,
      askedConfirm,
      showedPicker,
      request: window.__sffcSuccessFactorsProviderRequest,
      queueRequest: window.__sffcSuccessFactorsQueueRequest,
      queued,
      queueCount: state.length,
      titles: state.map((item) => item.title),
      hasAutoApplyQueue: !!messages.querySelector(".sffc-crm-apply-chat__apply-queue-card"),
      testButtonCount: testButtons.length,
      firstButtonText: testButtons[0] ? testButtons[0].textContent : "",
      enabled: test.isSuccessFactorsAdminTestEnabled(),
    };
  });

  if (
    !successFactorsSetupConversationFlow.enabled ||
    !successFactorsSetupConversationFlow.askedName ||
    !successFactorsSetupConversationFlow.askedEmail ||
    !successFactorsSetupConversationFlow.askedConfirm ||
    !successFactorsSetupConversationFlow.showedPicker ||
    !successFactorsSetupConversationFlow.request ||
    successFactorsSetupConversationFlow.request.entries.provider !== "successfactors" ||
    !successFactorsSetupConversationFlow.queueRequest ||
    successFactorsSetupConversationFlow.queueRequest.entries.action !==
      "sffc_crm_apply_chat_queue_application_task" ||
    successFactorsSetupConversationFlow.queueRequest.entries.provider !== "successfactors" ||
    !/create_account":true/.test(
      successFactorsSetupConversationFlow.queueRequest.entries.successfactors_account || ""
    ) ||
    !/allow_generated_password":true/.test(
      successFactorsSetupConversationFlow.queueRequest.entries.successfactors_account || ""
    ) ||
    !successFactorsSetupConversationFlow.queued ||
    successFactorsSetupConversationFlow.queueCount !== 2 ||
    successFactorsSetupConversationFlow.testButtonCount !== 2 ||
    successFactorsSetupConversationFlow.firstButtonText !== "Queued" ||
    !successFactorsSetupConversationFlow.titles.includes("Investment Analyst") ||
    !successFactorsSetupConversationFlow.titles.includes("Strategy Analyst") ||
    successFactorsSetupConversationFlow.titles.includes("Wrong Provider Role") ||
    successFactorsSetupConversationFlow.hasAutoApplyQueue ||
    /Send the role, location, or career question/i.test(
      successFactorsSetupConversationFlow.text
    ) ||
    /Send the detail for this step/i.test(successFactorsSetupConversationFlow.text)
  ) {
    qualityFailures.push({
      reason:
        "successfactors admin test setup did not keep CV/name/email inside the test conversation",
      successFactorsSetupConversationFlow,
    });
  }

  const workableSetupConversationFlow = await page.evaluate(async () => {
    const root = document.querySelector("[data-sffc-apply-chat]");
    const messages = document.querySelector("[data-sffc-apply-chat-messages]");
    const form = document.querySelector("[data-sffc-apply-chat-composer]");
    const input = document.querySelector("[data-sffc-apply-chat-input]");
    const test = root.__sffcApplyChatTest;
    const originalFetch = window.fetch;
    window.sffcCrmApplyChatArticle = {
      ...(window.sffcCrmApplyChatArticle || {}),
      isAdminTester: true,
    };
    window.fetch = async (_url, options) => {
      const body = options && options.body;
      const entries = body && typeof body.entries === "function"
        ? Object.fromEntries(body.entries())
        : {};
      window.__sffcWorkableProviderRequest = { entries };
      return new Response(
        JSON.stringify({
          success: true,
          data: {
            items: [
              {
                title: "Leasing & Tenant Relations Manager",
                company: "Qiddiya Investment Company",
                location: "Riyadh, Saudi Arabia",
                apply_url:
                  "https://apply.workable.com/qiddiya-investment-company-1/j/F2F2483923/apply/",
                source_platform: "Workable",
                auto_submit_provider: "workable",
              },
              {
                title: "Director, Delivery Contracts",
                company: "Qiddiya Investment Company",
                location: "Riyadh, Saudi Arabia",
                apply_url:
                  "https://apply.workable.com/qiddiya-investment-company-1/j/30C888938C/apply/",
                source_platform: "Workable",
                auto_submit_provider: "workable",
              },
              {
                title: "Wrong Provider Role",
                company: "Other",
                location: "Dubai",
                apply_url: "https://example.com/apply",
                source_platform: "Other",
              },
            ],
          },
        }),
        { status: 200, headers: { "Content-Type": "application/json" } }
      );
    };

    function submit(value) {
      input.value = value;
      form.dispatchEvent(new Event("submit", { bubbles: true, cancelable: true }));
    }
    async function waitFor(pattern) {
      for (let index = 0; index < 40; index += 1) {
        await new Promise((resolve) => setTimeout(resolve, 250));
        if (pattern.test(messages.textContent || "")) {
          return true;
        }
      }
      return false;
    }
    async function waitForPrompt(state) {
      for (let index = 0; index < 40; index += 1) {
        await new Promise((resolve) => setTimeout(resolve, 250));
        if (test.getPromptState && test.getPromptState() === state) {
          return true;
        }
      }
      return false;
    }
    async function waitForSelector(selector) {
      for (let index = 0; index < 40; index += 1) {
        await new Promise((resolve) => setTimeout(resolve, 250));
        if (messages.querySelector(selector)) {
          return true;
        }
      }
      return false;
    }

    test.resetCommercialApplyFlowState();
    messages.innerHTML = "";
    submit("test workable");
    const askedCv = await waitFor(/Upload the test CV first/i);
    test.setCurrentCvFileForTest("workable-test-cv.pdf");
    const continuedAfterCv = test.continueWorkableAdminTestAfterCvForTest();
    const askedName = await waitFor(/candidate full name/i);
    await waitForPrompt("workable_test_full_name");
    submit("Viktor Milanov");
    const askedEmail = await waitFor(/What email should I use for the Workable test application/i);
    await waitForPrompt("workable_test_email");
    submit("rossvltn@gmail.com");
    const askedConfirm = await waitFor(/Just double-checking - is that rossvltn@gmail\.com/i);
    await waitForPrompt("workable_test_confirm_email");
    submit("yes");
    const askedPhone = await waitFor(/What phone number should I use for the test application/i);
    await waitForPrompt("workable_test_phone");
    submit("+33782707653");
    const showedReady = await waitFor(/Choose one Workable job to test/i);
    await waitForSelector(".sffc-crm-apply-chat__apply-queue-card");
    const state = test.getCommercialApplyQueueState();
    const card = messages.querySelector(".sffc-crm-apply-chat__apply-queue-card");
    const startButton = messages.querySelector("[data-sffc-apply-chat-start-auto-apply]");
    const testButtons = messages.querySelectorAll("[data-sffc-workable-test-job]");
    const hasTestColumn = /Role\s*Company\s*Location\s*Status\s*Test/i.test(
      card ? card.textContent || "" : ""
    );
    let queuedWorkerItem = null;
    let statusRequest = null;
    let statusPollCount = 0;
    const originalSetTimeout = window.setTimeout;
    root.__sffcQueueBrowserApplicationTask = async (item) => {
      queuedWorkerItem = item;
      return { task_uuid: "workable-test-task-1" };
    };
    window.fetch = async (_url, options) => {
      const body = options && options.body;
      const entries = body && typeof body.entries === "function"
        ? Object.fromEntries(body.entries())
        : {};
      if (entries.action === "sffc_crm_apply_chat_application_task_status") {
        statusPollCount += 1;
        statusRequest = { entries };
        return new Response(
          JSON.stringify({
            success: true,
            data: {
              status: "submitted",
              uploaded_resume: true,
              application_answers_attempted: 4,
              application_answers_filled: 4,
              application_choice_answers_attempted: 3,
              application_choice_answers_filled: 3,
            },
          }),
          { status: 200, headers: { "Content-Type": "application/json" } }
        );
      }
      return new Response(JSON.stringify({ success: true, data: {} }), {
        status: 200,
        headers: { "Content-Type": "application/json" },
      });
    };
    window.setTimeout = (callback) => {
      originalSetTimeout(callback, 0);
      return 1;
    };
    const firstTestButton = messages.querySelector("[data-sffc-workable-test-job='0']");
    if (firstTestButton) {
      firstTestButton.click();
    }
    await new Promise((resolve) => originalSetTimeout(resolve, 1200));
    let showedResult = /Workable test result|Railway worker diagnostics/i.test(
      messages.textContent || ""
    );
    if (!showedResult) {
      showedResult = await waitFor(/Workable test result|Railway worker diagnostics/i);
    }
    await new Promise((resolve) => originalSetTimeout(resolve, 1200));
    const statusTextAfterClick = messages.textContent || "";
    showedResult =
      showedResult ||
      /Workable test result|Railway worker diagnostics/i.test(statusTextAfterClick);
    const cardAfterClick = messages.querySelector(".sffc-crm-apply-chat__apply-queue-card");
    const disabledAfterRender = Array.from(
      messages.querySelectorAll("[data-sffc-workable-test-job]")
    ).filter((button) => button.disabled).length;
    window.setTimeout = originalSetTimeout;
    delete root.__sffcQueueBrowserApplicationTask;
    window.fetch = originalFetch;
    return {
      text: messages.textContent || "",
      request: window.__sffcWorkableProviderRequest,
      enabled: test.isWorkableAdminTestEnabled(),
      continuedAfterCv,
      askedCv,
      askedName,
      askedEmail,
      askedConfirm,
      askedPhone,
      showedReady,
      queueCount: state.length,
      titles: state.map((item) => item.title),
      providers: state.map(
        (item) => item.autoSubmitProvider || item.auto_submit_provider || item.provider || ""
      ),
      hasCard: !!card,
      hasStartButton: !!startButton,
      testButtonCount: testButtons.length,
      hasTestColumn,
      queuedWorkerItem,
      statusRequest,
      statusPollCount,
      showedResult,
      hasCardAfterClick: !!cardAfterClick,
      disabledAfterRender,
      statusTextAfterClick,
    };
  });

  if (
    !workableSetupConversationFlow.request ||
    workableSetupConversationFlow.request.entries.provider !== "workable" ||
    !workableSetupConversationFlow.enabled ||
    !workableSetupConversationFlow.continuedAfterCv ||
    !workableSetupConversationFlow.askedCv ||
    !workableSetupConversationFlow.askedName ||
    !workableSetupConversationFlow.askedEmail ||
    !workableSetupConversationFlow.askedConfirm ||
    !workableSetupConversationFlow.askedPhone ||
    !workableSetupConversationFlow.showedReady ||
    workableSetupConversationFlow.queueCount !== 2 ||
    !workableSetupConversationFlow.titles.includes("Leasing & Tenant Relations Manager") ||
    !workableSetupConversationFlow.titles.includes("Director, Delivery Contracts") ||
    workableSetupConversationFlow.titles.includes("Wrong Provider Role") ||
    !workableSetupConversationFlow.providers.every((provider) =>
      /workable/i.test(provider)
    ) ||
    !workableSetupConversationFlow.hasCard ||
    workableSetupConversationFlow.hasStartButton ||
    workableSetupConversationFlow.testButtonCount !== 2 ||
    !workableSetupConversationFlow.hasTestColumn ||
    !workableSetupConversationFlow.queuedWorkerItem ||
    workableSetupConversationFlow.queuedWorkerItem.title !==
      "Leasing & Tenant Relations Manager" ||
    !workableSetupConversationFlow.statusRequest ||
    workableSetupConversationFlow.statusRequest.entries.task_uuid !==
      "workable-test-task-1" ||
    workableSetupConversationFlow.statusPollCount < 1 ||
    !workableSetupConversationFlow.showedResult ||
    !workableSetupConversationFlow.hasCardAfterClick ||
    !/Submitted/i.test(workableSetupConversationFlow.statusTextAfterClick) ||
    !/Railway worker diagnostics/i.test(
      workableSetupConversationFlow.statusTextAfterClick
    ) ||
    /Send the role, location, or career question/i.test(
      workableSetupConversationFlow.text
    ) ||
    /Send the detail for this step/i.test(workableSetupConversationFlow.text)
  ) {
    qualityFailures.push({
      reason:
        "workable admin test setup did not keep CV/name/email/phone inside the test conversation",
      workableSetupConversationFlow,
    });
  }

  const autoApplyQueueFlow = await page.evaluate(async () => {
    const root = document.querySelector("[data-sffc-apply-chat]");
    const messages = document.querySelector("[data-sffc-apply-chat-messages]");
    const composer = document.querySelector("[data-sffc-apply-chat-composer]");
    const input = document.querySelector("[data-sffc-apply-chat-input]");
    const originalFetch = window.fetch;
    window.fetch = async () => ({
      ok: true,
      status: 200,
      json: async () => ({
        success: true,
        data: {
          task_id: "test-task",
          status: "queued",
          message: "Queued",
        },
      }),
      text: async () => JSON.stringify({ success: true, data: {} }),
    });
    root.__sffcApplyChatTest.resetCommercialApplyFlowState();
    messages.innerHTML = "";
    root.__sffcApplyChatTest.setSelectedJobContext({
      roleTitle: "Director, Finance & Asset Performance Film Studios",
      roleCompany: "Qiddiya Investment Company",
      roleLocation: "Riyadh, Saudi Arabia",
      applicationUrl: "https://apply.workable.com/qiddiya-investment-company-1/j/test/apply/",
      activePath: "apply_for_me",
    });
    root.__sffcApplyChatTest.continueApplyAfterAnalysis({
      company_signal: "Qiddiya Investment Company",
      experience_title: "Finance Manager",
      matched_keywords: ["asset performance"],
      missing_keywords: ["film studios", "asset performance"],
      quick_role_insights: [],
      requirement_gaps: [],
    });
    for (let i = 0; i < 18; i += 1) {
      if (
        /Ok, let.s process your application/i.test(messages.textContent || "") &&
        /Firstly, can I get your full name/i.test(messages.textContent || "")
      ) {
        break;
      }
      await new Promise((resolve) => setTimeout(resolve, 500));
    }
    const preStartHasQueueCard = !!messages.querySelector(
      ".sffc-crm-apply-chat__apply-queue-card"
    );
    const afterStartText = messages.textContent || "";
    const afterStartHasQueueCard = !!messages.querySelector(
      ".sffc-crm-apply-chat__apply-queue-card"
    );
    input.value = "Rohith Roy";
    composer.dispatchEvent(
      new Event("submit", { bubbles: true, cancelable: true })
    );
    for (let i = 0; i < 18; i += 1) {
      if (/What email would you like to be reached at/i.test(messages.textContent || "")) {
        break;
      }
      await new Promise((resolve) => setTimeout(resolve, 500));
    }
    const afterNameText = messages.textContent || "";
    const afterNameHasQueueCard = !!messages.querySelector(
      ".sffc-crm-apply-chat__apply-queue-card"
    );
    input.value = "rossvltn@gmail.com";
    composer.dispatchEvent(
      new Event("submit", { bubbles: true, cancelable: true })
    );
    const afterEmailText = messages.textContent || "";
    for (let i = 0; i < 18; i += 1) {
      if (/Just double-checking/i.test(messages.textContent || "")) {
        break;
      }
      await new Promise((resolve) => setTimeout(resolve, 500));
    }
    input.value = "yes";
    composer.dispatchEvent(
      new Event("submit", { bubbles: true, cancelable: true })
    );
    for (let i = 0; i < 16; i += 1) {
      if (
        /Review the shortlist before I start/i.test(messages.textContent || "") &&
        messages.querySelector(".sffc-crm-apply-chat__apply-queue-card")
      ) {
        break;
      }
      await new Promise((resolve) => setTimeout(resolve, 500));
    }
    const afterConfirmText = messages.textContent || "";
    const queueCards = Array.from(messages.querySelectorAll(
      ".sffc-crm-apply-chat__apply-queue-card"
    ));
    const queueCard = queueCards[queueCards.length - 1] || null;
    const queueText = queueCard ? queueCard.textContent || "" : "";
    const queueCardIndex = afterConfirmText.indexOf(
      "Review the shortlist before I start"
    );
    const detailsIndex = afterConfirmText.indexOf(
      "Great. I’ve got what I need to get started."
    );
    const startButton = messages.querySelector(
      "[data-sffc-apply-chat-start-auto-apply]"
    );
    if (startButton) {
      startButton.click();
    }
    for (let i = 0; i < 18; i += 1) {
      if (/Emily is (?:working through this list|finishing the shortlist)/i.test(messages.textContent || "")) {
        break;
      }
      await new Promise((resolve) => setTimeout(resolve, 500));
    }
    const afterQueueStartText = messages.textContent || "";
    const activeQueueCards = Array.from(messages.querySelectorAll(
      ".sffc-crm-apply-chat__apply-queue-card"
    ));
    const activeQueueCard = activeQueueCards[activeQueueCards.length - 1] || null;
    const activeQueueText = activeQueueCard ? activeQueueCard.textContent || "" : "";
    window.fetch = originalFetch;
    return {
      startButtonFound: !!startButton,
      preStartHasQueueCard,
      afterStartHasQueueCard,
      afterNameHasQueueCard,
      afterStartText,
      afterNameText,
      afterEmailText,
      afterConfirmText,
      queueText,
      afterQueueStartText,
      activeQueueText,
      queueAppearsAfterDetails:
        queueCardIndex >= 0 && detailsIndex >= 0 && queueCardIndex > detailsIndex,
    };
  });

  if (
    autoApplyQueueFlow.preStartHasQueueCard ||
    autoApplyQueueFlow.afterStartHasQueueCard ||
    autoApplyQueueFlow.afterNameHasQueueCard ||
    !/Ok, let.s process your application/i.test(
      autoApplyQueueFlow.afterStartText
    ) ||
    !/Firstly, can I get your full name/i.test(
      autoApplyQueueFlow.afterStartText
    ) ||
    !/What email would you like to be reached at/i.test(
      autoApplyQueueFlow.afterNameText
    ) ||
    !/Great. I.ve got what I need to get started/i.test(
      autoApplyQueueFlow.afterConfirmText
    ) ||
    !/Review the shortlist before I start/i.test(autoApplyQueueFlow.queueText) ||
    !/Start Auto Apply/i.test(autoApplyQueueFlow.queueText) ||
    !/Ready/i.test(autoApplyQueueFlow.queueText) ||
    !/In Queue|Shortlist 1/i.test(autoApplyQueueFlow.queueText) ||
    !autoApplyQueueFlow.queueAppearsAfterDetails ||
    !autoApplyQueueFlow.startButtonFound ||
    !/Emily is (?:working through this list|finishing the shortlist)/i.test(autoApplyQueueFlow.afterQueueStartText) ||
    !/Preparing|Tailoring CV|Reviewing form|Submitted|Referred/i.test(
      autoApplyQueueFlow.afterQueueStartText
    )
  ) {
    qualityFailures.push({
      reason:
        "Start Auto Apply queue rendered out of sequence or did not update from the post-details card",
      autoApplyQueueFlow,
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
        allowed: ["support_complaint"],
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
    const promptRouteCases = [
      {
        name: "name slot accepts real name",
        state: "apply_collect_full_name",
        input: "Rohith Roy",
        expectedType: "PROMPT_ANSWER",
        expectedSlot: "full_name",
      },
      {
        name: "name slot diverts question",
        state: "apply_collect_full_name",
        input: "why do you need my name?",
        expectedType: "SIDE_CONVERSATION",
        expectedSlot: "full_name",
      },
      {
        name: "name slot rejects salary as name",
        state: "apply_collect_full_name",
        input: "AED 45,000",
        expectedType: "PROMPT_CLARIFY",
        expectedSlot: "full_name",
      },
      {
        name: "email slot accepts email",
        state: "apply_collect_preferred_email",
        input: "candidate@example.com",
        expectedType: "PROMPT_ANSWER",
        expectedSlot: "email",
      },
      {
        name: "email slot diverts privacy question",
        state: "apply_collect_preferred_email",
        input: "what will you do with my email?",
        expectedType: "SIDE_CONVERSATION",
        expectedSlot: "email",
      },
      {
        name: "email confirmation accepts yes",
        state: "apply_confirm_preferred_email",
        input: "yes",
        expectedType: "PROMPT_ANSWER",
        expectedSlot: "confirmation",
      },
      {
        name: "employer answer accepts simple answer",
        state: "apply_employer_question",
        input: "No",
        expectedType: "PROMPT_ANSWER",
        expectedSlot: "employer_answer",
      },
      {
        name: "employer answer diverts why question",
        state: "apply_employer_question",
        input: "why are they asking this?",
        expectedType: "SIDE_CONVERSATION",
        expectedSlot: "employer_answer",
      },
      {
        name: "successfactors password accepts password",
        state: "successfactors_account_password",
        input: "ValidPass123!",
        expectedType: "PROMPT_ANSWER",
        expectedSlot: "password",
      },
      {
        name: "successfactors gender accepts answer",
        state: "successfactors_profile_gender",
        input: "Male",
        expectedType: "PROMPT_ANSWER",
        expectedSlot: "employer_answer",
      },
      {
        name: "successfactors marital status accepts answer",
        state: "successfactors_profile_marital_status",
        input: "Single",
        expectedType: "PROMPT_ANSWER",
        expectedSlot: "employer_answer",
      },
      {
        name: "successfactors nationality accepts answer",
        state: "successfactors_profile_nationality",
        input: "Moroccan",
        expectedType: "PROMPT_ANSWER",
        expectedSlot: "employer_answer",
      },
      {
        name: "successfactors profile diverts why question",
        state: "successfactors_profile_gender",
        input: "why do you need gender?",
        expectedType: "SIDE_CONVERSATION",
        expectedSlot: "employer_answer",
      },
      {
        name: "auto apply accepts start",
        state: "apply_ready_to_start_auto_apply",
        input: "Start Auto Apply",
        expectedType: "PROMPT_ANSWER",
        expectedSlot: "workflow_choice",
      },
      {
        name: "auto apply diverts role value question",
        state: "apply_ready_to_start_auto_apply",
        input: "is this role worth applying to first?",
        expectedType: "SIDE_CONVERSATION",
        expectedSlot: "workflow_choice",
      },
      {
        name: "search setup accepts target role",
        state: "job_search_target_roles_text",
        input: "private banking and wealth management",
        expectedType: "PROMPT_ANSWER",
        expectedSlot: "search_context",
      },
      {
        name: "search setup diverts question first",
        state: "job_search_target_roles_text",
        input: "can I ask a question first?",
        expectedType: "SIDE_CONVERSATION",
        expectedSlot: "search_context",
      },
      {
        name: "intro volume accepts number",
        state: "apply_intro_application_volume",
        input: "10",
        expectedType: "PROMPT_ANSWER",
        expectedSlot: "workflow_choice",
      },
      {
        name: "intro volume diverts why question",
        state: "apply_intro_application_volume",
        input: "why do you need to know that?",
        expectedType: "SIDE_CONVERSATION",
        expectedSlot: "workflow_choice",
      },
      {
        name: "intro constraints accepts context",
        state: "apply_intro_constraints",
        input: "Only UAE roles and no roles needing Arabic",
        expectedType: "PROMPT_ANSWER",
        expectedSlot: "workflow_choice",
      },
      {
        name: "highlight detail accepts wording",
        state: "apply_highlight_detail",
        input: "Emphasise GCC asset management and stakeholder work",
        expectedType: "PROMPT_ANSWER",
        expectedSlot: "workflow_choice",
      },
      {
        name: "final question text accepts actual question",
        state: "apply_final_question_text",
        input: "Can they consider candidates already based in Dubai?",
        expectedType: "PROMPT_ANSWER",
        expectedSlot: "workflow_choice",
      },
      {
        name: "support complaint detail accepts issue",
        state: "support_complaint_detail",
        input: "The job matches were not relevant to my CV",
        expectedType: "PROMPT_ANSWER",
        expectedSlot: "workflow_choice",
      },
      {
        name: "different question detail accepts question",
        state: "different_question_detail",
        input: "What salary should I expect for this role?",
        expectedType: "PROMPT_ANSWER",
        expectedSlot: "workflow_choice",
      },
    ].map((item) => {
      const actual = test.classifyPromptRoute(item.input, item.state);
      return {
        name: item.name,
        state: item.state,
        input: item.input,
        actual,
        passed:
          actual.type === item.expectedType &&
          actual.slotType === item.expectedSlot,
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
      promptRouteCases,
      staleContextQueries,
      passed:
        detailCases.every((item) => item.actual === item.expected) &&
        normalizationCases.every((item) => item.passed) &&
        intentCases.every((item) => item.passed) &&
        promptRouteCases.every((item) => item.passed) &&
        /Arabic is not visible/.test(insightText) &&
        /Tailor my CV to this role/.test(insightText) &&
        /Apply with current CV/.test(insightText) &&
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
