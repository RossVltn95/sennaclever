import crypto from "node:crypto";
import dns from "node:dns/promises";

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

export function isPrivateOrReservedIp(hostname) {
  const value = cleanText(hostname).replace(/^\[|\]$/g, "");
  const ipv4 = value.match(/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/);
  if (ipv4) {
    const parts = ipv4.slice(1).map(Number);
    if (parts.some((part) => part < 0 || part > 255)) {
      return true;
    }
    const [a, b] = parts;
    return (
      a === 0 ||
      a === 10 ||
      a === 127 ||
      (a === 100 && b >= 64 && b <= 127) ||
      (a === 169 && b === 254) ||
      (a === 172 && b >= 16 && b <= 31) ||
      (a === 192 && b === 168) ||
      (a === 198 && (b === 18 || b === 19)) ||
      a >= 224
    );
  }
  const lower = value.toLowerCase();
  return (
    lower === "::1" ||
    lower === "::" ||
    lower.startsWith("fc") ||
    lower.startsWith("fd") ||
    lower.startsWith("fe80:")
  );
}

export function isBlockedEmployerHostname(hostname) {
  const host = cleanText(hostname).toLowerCase().replace(/\.$/, "");
  if (!host) {
    return true;
  }
  return (
    host === "localhost" ||
    host.endsWith(".localhost") ||
    host === "metadata.google.internal" ||
    host === "169.254.169.254" ||
    host === "metadata" ||
    /(^|\.)joinsenna\.com$/i.test(host) ||
    isPrivateOrReservedIp(host)
  );
}

export function isValidEmployerUrl(url) {
  try {
    const parsed = new URL(cleanText(url));
    return (
      /^https?:$/i.test(parsed.protocol) &&
      !!parsed.hostname &&
      !isInternalSennaUrl(parsed.href) &&
      !isBlockedEmployerHostname(parsed.hostname)
    );
  } catch (error) {
    return false;
  }
}

export async function assertSafeEmployerUrl(url) {
  const cleanUrl = cleanText(url);
  if (!isValidEmployerUrl(cleanUrl)) {
    throw Object.assign(new Error("A valid external employer URL is required."), {
      statusCode: 422,
    });
  }
  const parsed = new URL(cleanUrl);
  const host = parsed.hostname;
  if (isBlockedEmployerHostname(host)) {
    throw Object.assign(new Error("This employer URL is not allowed."), {
      statusCode: 422,
    });
  }
  const addresses = await dns.lookup(host, { all: true }).catch(() => []);
  if (addresses.some((entry) => isPrivateOrReservedIp(entry.address))) {
    throw Object.assign(
      new Error("This employer URL resolves to a private or reserved network address."),
      { statusCode: 422 }
    );
  }
  return cleanUrl;
}
