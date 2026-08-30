import fs from "node:fs/promises";
import os from "node:os";
import path from "node:path";
import puppeteer from "puppeteer";

const timeoutMs = Number(process.env.SFFC_AUDIT_TIMEOUT_MS || 35000);
const selectedProviders = new Set(
  String(process.env.SFFC_AUDIT_PROVIDERS || "")
    .split(",")
    .map((item) => item.trim().toLowerCase())
    .filter(Boolean)
);

const fixtures = [
  {
    provider: "greenhouse",
    source: "Capital Dynamics",
    jobUrl: "https://job-boards.eu.greenhouse.io/capitaldynamicsag/jobs/4904305101",
    expected: "worker_submit_candidate",
  },
  {
    provider: "lever",
    source: "Aldar",
    discoveryUrl: "https://api.lever.co/v0/postings/aldar?mode=json",
    expected: "embed_or_worker_candidate",
  },
  {
    provider: "workable",
    source: "Hayfin",
    jobUrl: process.env.SFFC_AUDIT_WORKABLE_URL || "",
    discoveryUrl: "https://apply.workable.com/hayfin-capital-management/jobs.md",
    expected: "embed_or_worker_candidate",
  },
  {
    provider: "recruitee",
    source: "IK Partners",
    discoveryUrl: "https://ikpartners.recruitee.com/api/offers",
    expected: "schema_and_embed_candidate",
  },
  {
    provider: "successfactors",
    source: "Red Sea Global",
    jobUrl: "https://careers.theredsea.sa/job/Riyadh-Associate-Director-Compliance-Al-R/857331023/",
    expected: "embed_candidate",
  },
  {
    provider: "pinpoint",
    source: "Malaa",
    discoveryUrl: "https://malaa.pinpointhq.com/postings",
    expected: "schema_and_embed_candidate",
  },
  {
    provider: "teamtailor",
    source: "Savills Middle East",
    discoveryUrl: "https://careers.savills.me/jobs.rss",
    expected: "schema_and_embed_candidate",
  },
  {
    provider: "smartrecruiters",
    source: "Masdar",
    discoveryUrl: "https://api.smartrecruiters.com/v1/companies/masdar/postings",
    expected: "needs_provider_adapter",
  },
  {
    provider: "ashby",
    source: "Bunch",
    discoveryUrl: "https://jobs.ashbyhq.com/bunch",
    expected: "needs_provider_adapter",
  },
  {
    provider: "bamboohr",
    source: "Gulf Strategic Equities",
    discoveryUrl: "https://gsequity.bamboohr.com/careers/list",
    expected: "needs_provider_adapter",
  },
  {
    provider: "bayt",
    source: "Al Rajhi Bank",
    jobUrl: "https://careers.alrajhibank.com.sa/en/job-search-results/",
    expected: "external_or_provider_adapter",
  },
  {
    provider: "oracle_cx",
    source: "JP Morgan",
    jobUrl: "https://jpmc.fa.oraclecloud.com/hcmUI/CandidateExperience/en/sites/CX_1001/jobs?location=Dubai%2C+United%20Arab%20Emirates&locationId=300000020333038&locationLevel=state&mode=location",
    expected: "external_or_provider_adapter",
  },
  {
    provider: "workday",
    source: "Capital Group",
    jobUrl: "https://capgroup.wd1.myworkdayjobs.com/en-US/capitalgroupcareers/job/Senior-Analyst--Executive-Office_JR7169-1",
    expected: "external_only",
  },
  {
    provider: "michael_page",
    source: "Michael Page UAE",
    discoveryUrl: "https://www.michaelpage.ae/jobs/investment/dubai-dubai?sort_by=most_recent",
    expected: "captcha_risk",
  },
];

function clean(value) {
  return String(value || "").replace(/\s+/g, " ").trim();
}

function absolutize(url, base) {
  try {
    return new URL(url, base).toString();
  } catch {
    return "";
  }
}

function stripTags(value) {
  return clean(String(value || "").replace(/<[^>]+>/g, " "));
}

async function fetchText(url, accept = "*/*") {
  const response = await fetch(url, {
    redirect: "follow",
    headers: {
      accept,
      "user-agent":
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36",
    },
  });
  const text = await response.text();
  return { response, text };
}

async function resolveJobUrlWithBrowser(browser, fixture, currentUrl) {
  if (!browser || !["pinpoint", "ashby", "smartrecruiters"].includes(fixture.provider)) {
    return currentUrl;
  }

  const page = await browser.newPage();
  await page.setViewport({ width: 1440, height: 1200 });
  try {
    await page.goto(currentUrl || fixture.discoveryUrl || fixture.jobUrl, { waitUntil: "domcontentloaded", timeout: timeoutMs });
    await page.waitForNetworkIdle({ idleTime: 1000, timeout: 18000 }).catch(() => {});
    const resolved = await page.evaluate((provider) => {
      const visible = (element) => {
        const style = window.getComputedStyle(element);
        const rect = element.getBoundingClientRect();
        return style.display !== "none" && style.visibility !== "hidden" && rect.width > 0 && rect.height > 0;
      };
      const links = Array.from(document.querySelectorAll("a[href]"))
        .filter(visible)
        .map((link) => ({ href: link.href, text: String(link.textContent || "").replace(/\s+/g, " ").trim() }));
      if (provider === "pinpoint") {
        return links.find((link) => /\/jobs\/|\/postings\//i.test(link.href) || /apply|view|engineer|analyst|manager/i.test(link.text))?.href || "";
      }
      if (provider === "ashby") {
        return links.find((link) => /jobs\.ashbyhq\.com\/[^/]+\/[a-f0-9-]{20,}/i.test(link.href))?.href || "";
      }
      if (provider === "smartrecruiters") {
        return links.find((link) => /jobs\.smartrecruiters\.com\/.+\/\d+/i.test(link.href) || /apply|view/i.test(link.text))?.href || "";
      }
      return "";
    }, fixture.provider);
    await page.close().catch(() => {});
    return resolved || currentUrl;
  } catch {
    await page.close().catch(() => {});
    return currentUrl;
  }
}

function buildSuccessFactorsEmbedUrl(html, jobUrl) {
  const ssoUrl =
    (html.match(/ssoUrl\s*:\s*['"]([^'"]+)['"]/i) || [])[1] ||
    (html.match(/https:\/\/career\d+\.successfactors\.(?:com|eu)/i) || [])[0] ||
    "";
  const sourceId = (html.match(/sourceId\s*:\s*['"]([^'"]+)['"]/i) || [])[1] || "";
  const locale =
    (html.match(/locale\s*:\s*['"]([A-Za-z_-]+)['"]/i) || [])[1] ||
    (jobUrl.match(/[?&]locale=([A-Za-z_-]+)/i) || [])[1] ||
    "en_US";
  const internalId =
    (html.match(/internalId\s*:\s*"([^"-]+)(?:-[^"]*)?"/i) || [])[1] ||
    (html.match(/"internalId"\s*:\s*"([^"-]+)(?:-[^"]*)?"/i) || [])[1] ||
    (jobUrl.match(/\/([0-9]{4,})\/?$/) || [])[1] ||
    "";
  const company =
    sourceId.toLowerCase().startsWith("jats-")
      ? sourceId.slice(5)
      : (html.match(/[?&](?:company|career_company)=([^&"']+)/i) || [])[1] || "";

  if (!ssoUrl || !company || !internalId) {
    return "";
  }

  return `${ssoUrl.replace(/\/$/, "")}/career?company=${encodeURIComponent(company)}&site=&lang=${encodeURIComponent(
    locale
  )}&login_ns=register&career_ns=job_application&career_job_req_id=${encodeURIComponent(
    internalId
  )}&jobPipeline=Direct&clientId=jobs2web`;
}

async function getFrameHeaders(url) {
  try {
    const { response } = await fetchText(url, "text/html,application/xhtml+xml,*/*");
    const csp = response.headers.get("content-security-policy") || "";
    const xfo = response.headers.get("x-frame-options") || "";
    const frameAncestors = (csp.match(/frame-ancestors\s+([^;]+)/i) || [])[1] || "";
    return {
      status: response.status,
      final_url: response.url,
      x_frame_options: xfo,
      frame_ancestors: frameAncestors,
      iframe_likely_allowed:
        !/deny|sameorigin/i.test(xfo) &&
        !/(^|\s)'none'(\s|$)|(^|\s)'self'(\s|$)/i.test(frameAncestors),
    };
  } catch (error) {
    return { error: error.message, iframe_likely_allowed: false };
  }
}

async function resolveJobUrl(fixture) {
  if (fixture.jobUrl) {
    if (fixture.provider === "successfactors") {
      const { text } = await fetchText(fixture.jobUrl, "text/html,application/xhtml+xml,*/*");
      const embedUrl = buildSuccessFactorsEmbedUrl(text, fixture.jobUrl);
      return embedUrl || fixture.jobUrl;
    }
    return fixture.jobUrl;
  }

  const { provider, discoveryUrl } = fixture;
  const { response, text } = await fetchText(discoveryUrl, "application/json,text/html,text/markdown,application/rss+xml,*/*");
  if (!response.ok) {
    throw new Error(`Discovery failed with HTTP ${response.status}`);
  }

  if (provider === "lever") {
    const postings = JSON.parse(text);
    const first = postings.find((item) => item && (item.applyUrl || item.hostedUrl));
    const url = first?.applyUrl || first?.hostedUrl || "";
    return url && !/\/apply\/?$/i.test(url) ? `${url.replace(/\/$/, "")}/apply` : url;
  }

  if (provider === "workable") {
    const apply = text.match(/\[Apply[^\]]*\]\(([^)]+)\)/i);
    if (apply) {
      return apply[1].trim();
    }
    const firstDetail = text.match(/\((https:\/\/apply\.workable\.com\/[^)]+)\)/i);
    if (!firstDetail) {
      return "";
    }
    const detailUrl = firstDetail[1].trim();
    if (!/\.md(?:$|\?)/i.test(detailUrl)) {
      return detailUrl;
    }
    const detail = await fetchText(detailUrl, "text/markdown,text/plain,*/*");
    const detailApply = detail.text.match(/\[Apply[^\]]*\]\(([^)]+)\)/i);
    return detailApply ? detailApply[1].trim() : detailUrl.replace(/\.md(?:\?.*)?$/i, "");
  }

  if (provider === "recruitee") {
    const payload = JSON.parse(text);
    const offers = Array.isArray(payload.offers) ? payload.offers : Array.isArray(payload) ? payload : [];
    const first = offers.find((offer) => offer && (offer.careers_apply_url || offer.careers_url));
    return first?.careers_apply_url || first?.careers_url || "";
  }

  if (provider === "successfactors") {
    const embedUrl = buildSuccessFactorsEmbedUrl(text, discoveryUrl);
    return embedUrl || discoveryUrl;
  }

  if (provider === "pinpoint") {
    if (/^\s*\{|\[\s*\{/i.test(text)) {
      const payload = JSON.parse(text);
      const items = Array.isArray(payload.data) ? payload.data : Array.isArray(payload) ? payload : [];
      const first = items.find((item) => item?.id);
      if (first) {
        return `${discoveryUrl.replace(/\/postings.*$/i, "")}/postings/${first.id}/applications/new`;
      }
    }
    const href = text.match(/href=["']([^"']+\/postings\/[^"']+)["']/i) || text.match(/href=["']([^"']+)["']/i);
    const postingUrl = href ? absolutize(href[1], discoveryUrl) : "";
    return postingUrl ? `${postingUrl.replace(/\/$/, "")}/applications/new` : "";
  }

  if (provider === "teamtailor") {
    const item = text.match(/<item\b[\s\S]*?<\/item>/i)?.[0] || "";
    const link = item.match(/<link>([^<]+)<\/link>/i);
    const jobUrl = link ? clean(link[1]) : "";
    return jobUrl ? `${jobUrl.replace(/\/$/, "")}/applications/new` : "";
  }

  if (provider === "smartrecruiters") {
    const payload = JSON.parse(text);
    const postings = payload.content || payload.postings || [];
    const first = postings.find((item) => item?.postingUrl || item?.ref || item?.id);
    if (!first) {
      return "";
    }
    if (first.postingUrl) {
      return first.postingUrl;
    }
    const detailUrl = first.ref || `${discoveryUrl.replace(/\/$/, "")}/${first.id}`;
    const detail = await fetchText(detailUrl, "application/json,*/*");
    const detailPayload = JSON.parse(detail.text);
    return detailPayload.postingUrl || detailPayload.applyUrl || detailPayload.ref || "";
  }

  if (provider === "ashby") {
    const href =
      text.match(/href=["'](\/bunch\/[a-f0-9-]{20,}[^"']*)["']/i) ||
      text.match(/href=["'](https:\/\/jobs\.ashbyhq\.com\/bunch\/[^"']+)["']/i);
    return href ? absolutize(href[1], discoveryUrl) : "";
  }

  if (provider === "bamboohr") {
    const payload = JSON.parse(text);
    const items = Array.isArray(payload.result) ? payload.result : Array.isArray(payload) ? payload : [];
    const first = items.find((item) => item?.id || item?.url);
    if (!first) {
      return "";
    }
    return first.url || `https://gsequity.bamboohr.com/careers/${first.id}`;
  }

  if (provider === "michael_page") {
    const href = text.match(/href=["']([^"']*\/job-detail\/[^"']+)["']/i);
    const detail = href ? absolutize(href[1], discoveryUrl) : "";
    return detail ? detail.replace("/job-detail/", "/job-apply/") : "";
  }

  return "";
}

async function inspectWithBrowser(browser, fixture, jobUrl) {
  const page = await browser.newPage();
  await page.setViewport({ width: 1440, height: 1200 });
  const consoleErrors = [];
  const failedRequests = [];
  page.on("console", (message) => {
    if (["error", "warning"].includes(message.type())) {
      consoleErrors.push(clean(message.text()).slice(0, 180));
    }
  });
  page.on("requestfailed", (request) => {
    failedRequests.push({
      url: request.url().slice(0, 180),
      failure: request.failure()?.errorText || "",
    });
  });

  let interceptedSubmit = null;
  await page.setRequestInterception(true);
  page.on("request", (request) => {
    const method = request.method();
    const postData = request.postData() || "";
    const url = request.url();
    const looksLikeSubmit =
      method === "POST" &&
      (/application|candidate|resume|greenhouse|lever|workable|recruitee|pinpoint|teamtailor|ashby|bamboohr|smartrecruiters/i.test(url) ||
        /first|last|email|resume|candidate|application/i.test(postData));
    if (looksLikeSubmit && !interceptedSubmit) {
      interceptedSubmit = {
        url,
        method,
        post_data_length: postData.length,
        post_data_sample: postData.slice(0, 300),
      };
      request.abort("aborted").catch(() => {});
      return;
    }
    request.continue().catch(() => {});
  });

  try {
    await page.goto(jobUrl, { waitUntil: "domcontentloaded", timeout: timeoutMs });
    await page.waitForNetworkIdle({ idleTime: 1000, timeout: 12000 }).catch(() => {});

    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight)).catch(() => {});
    await new Promise((resolve) => setTimeout(resolve, 750));
    await page.evaluate(() => {
      const candidates = Array.from(document.querySelectorAll("button, a, input[type='button'], input[type='submit']"));
      const target = candidates.find((item) =>
        /\bapply\b|apply now|apply for this job|submit application/i.test(
          `${item.textContent || ""} ${item.getAttribute("value") || ""} ${item.getAttribute("aria-label") || ""}`
        )
      );
      if (target) {
        target.scrollIntoView({ block: "center" });
        target.click();
      }
    }).catch(() => {});
    await page.waitForNetworkIdle({ idleTime: 1000, timeout: 15000 }).catch(() => {});

    const dom = await page.evaluate(() => {
      const clean = (value) => String(value || "").replace(/\s+/g, " ").trim();
      const visible = (element) => {
        const style = window.getComputedStyle(element);
        const rect = element.getBoundingClientRect();
        return !element.disabled && style.display !== "none" && style.visibility !== "hidden" && rect.width > 0 && rect.height > 0;
      };
      const controls = Array.from(document.querySelectorAll("input, textarea, select")).filter(visible);
      const labels = Array.from(document.querySelectorAll("label, legend, [aria-label], [data-qa], [data-testid]"))
        .map((element) =>
          clean(
            `${element.textContent || ""} ${element.getAttribute("aria-label") || ""} ${element.getAttribute("data-qa") || ""} ${
              element.getAttribute("data-testid") || ""
            }`
          )
        )
        .filter(Boolean);
      const buttons = Array.from(document.querySelectorAll("button, input[type='submit'], a"))
        .filter(visible)
        .map((element) => clean(`${element.textContent || ""} ${element.getAttribute("value") || ""} ${element.getAttribute("aria-label") || ""}`))
        .filter(Boolean);
      const bodyText = clean(document.body?.innerText || "");
      const fieldSummaries = controls.slice(0, 80).map((field) => {
        const id = field.getAttribute("id") || "";
        const name = field.getAttribute("name") || "";
        const label = id ? clean(document.querySelector(`label[for="${CSS.escape(id)}"]`)?.textContent || "") : "";
        return {
          tag: field.tagName.toLowerCase(),
          type: field.getAttribute("type") || "",
          name,
          id,
          label,
          placeholder: field.getAttribute("placeholder") || "",
          required: field.required || field.getAttribute("aria-required") === "true" || /\brequired\b/i.test(field.className || ""),
        };
      });
      const haystack = clean(`${labels.join(" ")} ${fieldSummaries.map((field) => `${field.name} ${field.id} ${field.label} ${field.placeholder}`).join(" ")}`);
      return {
        title: document.title || "",
        url: location.href,
        body_sample: bodyText.slice(0, 700),
        field_count: controls.length,
        required_field_count: fieldSummaries.filter((field) => field.required).length,
        file_input_count: controls.filter((field) => field.matches("input[type='file']")).length,
        candidate_fields: {
          first_name: /first.name|given.name|fname/i.test(haystack),
          last_name: /last.name|family.name|surname|lname/i.test(haystack),
          email: /email/i.test(haystack),
          phone: /phone|mobile|telephone/i.test(haystack),
        },
        submit_button_found: buttons.some((button) => /submit|send application|apply/i.test(button)),
        auth_required: /sign in|log in|login|create account|already have an account/i.test(bodyText),
        captcha_detected: /captcha|recaptcha|hcaptcha|verify you are human/i.test(bodyText),
        verification_detected: /verification code|security code|confirm.*human|copy and paste.*code/i.test(bodyText),
        labels: labels.slice(0, 40),
        fields: fieldSummaries,
        buttons: buttons.slice(0, 25),
      };
    });

    await page.close().catch(() => {});
    return {
      ...dom,
      console_errors: Array.from(new Set(consoleErrors)).slice(0, 10),
      failed_requests: failedRequests.slice(0, 10),
      intercepted_submit: interceptedSubmit,
    };
  } catch (error) {
    await page.close().catch(() => {});
    return {
      error: error.message,
      console_errors: Array.from(new Set(consoleErrors)).slice(0, 10),
      failed_requests: failedRequests.slice(0, 10),
    };
  }
}

function classify(result) {
  const browser = result.browser || {};
  const frame = result.frame || {};
  const hasCore = Boolean(browser.candidate_fields?.first_name && browser.candidate_fields?.last_name && browser.candidate_fields?.email);
  const hasResume = Number(browser.file_input_count || 0) > 0;
  const hasForm = Number(browser.field_count || 0) > 0;

  if (browser.error || result.discovery_error) {
    return "not_feasible_without_provider_work";
  }
  if (browser.captcha_detected) {
    return "external_or_manual_review";
  }
  if (browser.auth_required && !hasCore) {
    return "external_or_account_flow";
  }
  if (hasCore && hasResume && browser.submit_button_found && ["greenhouse"].includes(result.provider)) {
    return "worker_submit_supported_now";
  }
  if (hasCore && hasResume && browser.submit_button_found) {
    return "worker_submit_possible_needs_provider_adapter";
  }
  if (frame.iframe_likely_allowed && hasForm) {
    return "embedded_self_submit_supported";
  }
  if (hasForm || Number(browser.required_field_count || 0) > 0) {
    return "question_insights_possible_external_submit";
  }
  return "external_only";
}

async function main() {
  const wanted = fixtures.filter((fixture) => selectedProviders.size === 0 || selectedProviders.has(fixture.provider));
  const tmp = await fs.mkdtemp(path.join(os.tmpdir(), "sffc-provider-audit-"));
  const executablePath =
    process.env.PUPPETEER_EXECUTABLE_PATH ||
    process.env.PUPPETEER_BROWSER_PATH ||
    (process.platform === "darwin" && process.arch === "x64"
      ? path.join(
          os.homedir(),
          ".cache/puppeteer/chrome-headless-shell/mac-152.0.7977.54/chrome-headless-shell-mac-x64/chrome-headless-shell"
        )
      : "") ||
    (process.platform === "darwin" ? "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" : "");
  const browser = await puppeteer.launch({
    headless: "new",
    executablePath: executablePath || undefined,
    timeout: 90000,
    args: ["--no-sandbox", "--disable-setuid-sandbox"],
  });

  const results = [];
  try {
    for (const fixture of wanted) {
      const result = {
        provider: fixture.provider,
        source: fixture.source,
        expected: fixture.expected,
        discovery_url: fixture.discoveryUrl || "",
        job_url: fixture.jobUrl || "",
      };
      try {
        result.job_url = await resolveJobUrl(fixture);
        result.job_url = await resolveJobUrlWithBrowser(browser, fixture, result.job_url);
        if (!result.job_url) {
          throw new Error("No representative job URL resolved");
        }
        result.frame = await getFrameHeaders(result.job_url);
        result.browser = await inspectWithBrowser(browser, fixture, result.job_url);
      } catch (error) {
        result.discovery_error = error.message;
      }
      result.mock_result = classify(result);
      results.push(result);
      console.log(`${result.provider.padEnd(15)} ${result.mock_result} ${result.job_url || result.discovery_url}`);
    }
  } finally {
    await browser.close().catch(() => {});
  }

  const reportPath = path.join(tmp, "provider-feasibility-report.json");
  await fs.writeFile(reportPath, JSON.stringify(results, null, 2));
  console.log(`\nReport: ${reportPath}`);
  console.log("\nSummary:");
  for (const result of results) {
    const browserResult = result.browser || {};
    console.log(
      `- ${result.provider}: ${result.mock_result}; fields=${browserResult.field_count || 0}; required=${browserResult.required_field_count || 0}; file=${browserResult.file_input_count || 0}; iframe=${Boolean(result.frame?.iframe_likely_allowed)}`
    );
  }
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
