import crypto from "node:crypto";

export function cleanText(value) {
  return String(value ?? "").replace(/\s+/g, " ").trim();
}

export function getBearerToken(request) {
  const authorization = cleanText(request.headers.authorization || "");
  const headerToken = cleanText(request.headers["x-sffc-remote-browser-token"] || "");
  const match = authorization.match(/^Bearer\s+(.+)$/i);
  return cleanText(headerToken || (match ? match[1] : ""));
}

export function safeTokenEquals(actual, expected) {
  const cleanActual = cleanText(actual);
  const cleanExpected = cleanText(expected);
  if (!cleanActual || !cleanExpected) {
    return false;
  }
  const actualBuffer = Buffer.from(cleanActual);
  const expectedBuffer = Buffer.from(cleanExpected);
  if (actualBuffer.length !== expectedBuffer.length) {
    return false;
  }
  return crypto.timingSafeEqual(actualBuffer, expectedBuffer);
}

export function isAuthorized(request, expectedToken) {
  return safeTokenEquals(getBearerToken(request), expectedToken);
}

export function getAllowedOrigin() {
  return cleanText(process.env.SFFC_REMOTE_BROWSER_ALLOWED_ORIGIN || "https://joinsenna.com");
}

export function getCorsHeaders(request) {
  const allowedOrigin = getAllowedOrigin();
  const origin = cleanText(request.headers.origin || "");
  const allowOrigin = origin && origin === allowedOrigin ? origin : allowedOrigin;
  return {
    "access-control-allow-origin": allowOrigin,
    "access-control-allow-methods": "GET,POST,OPTIONS",
    "access-control-allow-headers": "content-type,authorization,x-sffc-remote-browser-token",
    "access-control-max-age": "600",
    vary: "Origin",
  };
}

export function sendJson(response, statusCode, payload, headers = {}) {
  response.writeHead(statusCode, {
    "content-type": "application/json; charset=utf-8",
    ...headers,
  });
  response.end(JSON.stringify(payload));
}

export function sendError(response, statusCode, message, details = {}, headers = {}) {
  sendJson(
    response,
    statusCode,
    {
      ok: false,
      error: cleanText(message || "Request failed."),
      ...details,
    },
    headers
  );
}

export function parseJsonBody(request, limitBytes = 1024 * 1024) {
  return new Promise((resolve, reject) => {
    let received = 0;
    const chunks = [];
    request.on("data", (chunk) => {
      received += chunk.length;
      if (received > limitBytes) {
        reject(new Error("Request body is too large."));
        request.destroy();
        return;
      }
      chunks.push(chunk);
    });
    request.on("end", () => {
      const raw = Buffer.concat(chunks).toString("utf8").trim();
      if (!raw) {
        resolve({});
        return;
      }
      try {
        resolve(JSON.parse(raw));
      } catch (error) {
        reject(new Error("Request body must be valid JSON."));
      }
    });
    request.on("error", reject);
  });
}

export function isInternalSennaUrl(url) {
  try {
    const parsed = new URL(cleanText(url));
    return /(^|\.)joinsenna\.com$/i.test(parsed.hostname);
  } catch (error) {
    return false;
  }
}

export function isValidEmployerUrl(url) {
  try {
    const parsed = new URL(cleanText(url));
    return /^https?:$/i.test(parsed.protocol) && !!parsed.hostname && !isInternalSennaUrl(parsed.href);
  } catch (error) {
    return false;
  }
}
