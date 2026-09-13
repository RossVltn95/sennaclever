#!/usr/bin/env node

const DEFAULT_TIMEOUT_MS = Number(process.env.SFFC_SERVICE_SMOKE_TIMEOUT_MS || 6000);

const services = [
  {
    id: "emily_nlp",
    env: "SFFC_SMOKE_EMILY_NLP_ENDPOINT",
    tokenEnv: "SFFC_SMOKE_EMILY_NLP_TOKEN",
    method: "POST",
    body: {
      message: "finance jobs in Dubai but not banking",
      context: { hasCv: false },
    },
    expect(payload) {
      return (
        payload &&
        payload.ok &&
        payload.meaning &&
        payload.meaning.action &&
        payload.meaning.action.type === "jobs_database_search"
      );
    },
  },
  {
    id: "web_answer",
    env: "SFFC_SMOKE_SEARCH_ANSWER_ENDPOINT",
    tokenEnv: "SFFC_SMOKE_SEARCH_ANSWER_TOKEN",
    method: "POST",
    body: {
      query: "when is the best time to apply for jobs in Dubai",
      locale: "en",
      limit: 3,
    },
    expect(payload) {
      return payload && payload.ok && payload.answer;
    },
  },
  {
    id: "searxng_search",
    env: "SFFC_SMOKE_SEARCH_ENDPOINT",
    tokenEnv: "SFFC_SMOKE_SEARCH_TOKEN",
    method: "GET",
    query: { q: "Dubai recruitment agencies", format: "json" },
    expect(payload) {
      return payload && Array.isArray(payload.results);
    },
  },
  {
    id: "liteparse",
    env: "SFFC_SMOKE_LITEPARSE_HEALTH_ENDPOINT",
    method: "GET",
    expect(payload) {
      return payload && payload.ok;
    },
  },
];

function endpointWithQuery(endpoint, query) {
  if (!query) return endpoint;
  const url = new URL(endpoint);
  Object.entries(query).forEach(([key, value]) => {
    url.searchParams.set(key, String(value));
  });
  return url.toString();
}

async function fetchJson(service, endpoint) {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), DEFAULT_TIMEOUT_MS);
  const headers = { Accept: "application/json" };
  const token = service.tokenEnv ? process.env[service.tokenEnv] || "" : "";
  let url = endpoint;
  const init = {
    method: service.method || "GET",
    headers,
    signal: controller.signal,
  };
  if (token) headers.Authorization = `Bearer ${token}`;
  if (service.method === "POST") {
    headers["Content-Type"] = "application/json";
    init.body = JSON.stringify(service.body || {});
  } else {
    url = endpointWithQuery(endpoint, service.query);
  }
  try {
    const response = await fetch(url, init);
    const text = await response.text();
    let payload = null;
    try {
      payload = text ? JSON.parse(text) : null;
    } catch (error) {
      payload = { raw: text.slice(0, 500) };
    }
    return {
      ok: response.ok && service.expect(payload),
      status: response.status,
      payload,
    };
  } finally {
    clearTimeout(timeout);
  }
}

async function main() {
  if (typeof fetch !== "function") {
    throw new Error("Node fetch is not available. Use Node 20+.");
  }
  const configured = services
    .map((service) => ({
      service,
      endpoint: String(process.env[service.env] || "").trim(),
    }))
    .filter((item) => item.endpoint);
  if (!configured.length) {
    console.log(
      "SKIP Emily service smoke checks: no SFFC_SMOKE_* endpoints were provided."
    );
    return;
  }
  const results = [];
  for (const item of configured) {
    const started = Date.now();
    try {
      const result = await fetchJson(item.service, item.endpoint);
      results.push({
        id: item.service.id,
        ok: !!result.ok,
        status: result.status,
        durationMs: Date.now() - started,
      });
    } catch (error) {
      results.push({
        id: item.service.id,
        ok: false,
        error: String((error && error.message) || error),
        durationMs: Date.now() - started,
      });
    }
  }
  const failed = results.filter((result) => !result.ok);
  console.log(JSON.stringify({ ok: !failed.length, results }, null, 2));
  if (failed.length) process.exit(1);
}

main().catch((error) => {
  console.error(String((error && error.stack) || error));
  process.exit(1);
});
