import hashlib
import os
import re
import time
from urllib.parse import urlparse

import requests
import trafilatura
from flask import Flask, jsonify, request


app = Flask(__name__)

SEARXNG_ENDPOINT = os.getenv("SEARXNG_ENDPOINT", "").strip()
ANSWER_TOKEN = os.getenv("SENNA_ANSWER_TOKEN", "").strip()
MAX_SOURCES = max(1, min(4, int(os.getenv("MAX_SOURCES", "3") or "3")))
REQUEST_TIMEOUT = max(2, min(12, int(os.getenv("REQUEST_TIMEOUT", "5") or "5")))


STOP_WORDS = {
    "the",
    "and",
    "for",
    "with",
    "that",
    "this",
    "what",
    "when",
    "where",
    "which",
    "who",
    "how",
    "why",
    "are",
    "is",
    "in",
    "on",
    "at",
    "to",
    "of",
    "a",
    "an",
    "me",
    "my",
    "i",
    "you",
    "can",
    "could",
    "please",
    "tell",
    "about",
    "best",
    "top",
    "good",
    "latest",
    "current",
}


TYPE_PATTERNS = {
    "salary": re.compile(r"\b(salary|salaries|pay range|pay scale|compensation|bonus|benefits)\b", re.I),
    "visa_legal": re.compile(r"\b(visa|work permit|employment law|labou?r law|golden visa|probation|notice period|tax|income tax|vat)\b", re.I),
    "market_timing": re.compile(r"\b(best time|best month|hiring season|when should|when is|start applying|apply for jobs)\b", re.I),
    "comparison": re.compile(r"\b(compare|versus| vs |which is better|difference between|pros and cons)\b", re.I),
    "current_market": re.compile(r"\b(latest|current|currently|recent|today|this week|this month|news|trend|trends|outlook|forecast)\b", re.I),
    "company_research": re.compile(r"\b(what does|who is|who are|tell me about|company profile|competitors|ownership|founder|revenue|aum|assets under management)\b", re.I),
    "country_life": re.compile(r"\b(life|living|cost of living|culture|lifestyle|safe|safety|expat|relocation|move|work culture|country|city|what is .* like)\b", re.I),
    "recruiter_directory": re.compile(r"\b(recruitment agencies|recruiting agencies|recruiters|headhunter|headhunters|executive search|staffing agencies|search firms)\b", re.I),
}


PASSAGE_PATTERNS = {
    "salary": re.compile(r"\b(salary|salaries|aed|sar|usd|month|annual|compensation|bonus|pay)\b", re.I),
    "market_timing": re.compile(r"\b(january|february|march|april|may|june|july|august|september|october|november|december|quarter|hiring|recruitment|apply|season)\b", re.I),
    "recruiter_directory": re.compile(r"\b(recruitment|recruiter|agency|executive search|headhunter|staffing)\b", re.I),
    "company_research": re.compile(r"\b(company|investment|founded|owned|headquartered|portfolio|assets|business|subsidiary)\b", re.I),
    "visa_legal": re.compile(r"\b(visa|permit|law|legal|sponsor|sponsorship|employment|tax|probation|notice)\b", re.I),
    "country_life": re.compile(r"\b(living|life|culture|cost|safe|safety|expat|housing|healthcare|transport|work)\b", re.I),
    "comparison": re.compile(r"\b(compared|whereas|while|better|higher|lower|cost|salary|market)\b", re.I),
    "current_market": re.compile(r"\b(latest|current|market|trend|growth|hiring|forecast|outlook|202[0-9])\b", re.I),
}


HEADLINES = {
    "country_life": "Here is the practical read from the sources I found.",
    "market_timing": "The useful answer is timing plus action, not timing alone.",
    "salary": "Treat salary figures as a market range, not a fixed number.",
    "recruiter_directory": "I found public sources that can help build a recruiter shortlist.",
    "company_research": "Here is the company-level read from public sources.",
    "visa_legal": "This is source-sensitive, so use official guidance before acting.",
    "comparison": "The better answer depends on your role, sector, and constraints.",
    "current_market": "Here is what the current public sources suggest.",
    "general_web_answer": "Here is the clearest answer I can give from the sources I found.",
}


def require_auth():
    if not ANSWER_TOKEN:
        return None
    header = request.headers.get("Authorization", "")
    if header != f"Bearer {ANSWER_TOKEN}":
        return jsonify({"ok": False, "error": "unauthorized"}), 401
    return None


def classify_query(query):
    clean = query.lower().strip()
    for key, pattern in TYPE_PATTERNS.items():
        if pattern.search(clean):
            return key
    return "general_web_answer"


def query_terms(query):
    terms = []
    for token in re.split(r"[^a-z0-9+&.-]+", query.lower()):
        if len(token) >= 3 and token not in STOP_WORDS and token not in terms:
            terms.append(token)
    return terms


def is_public_http_url(url):
    try:
        parsed = urlparse(url)
    except ValueError:
        return False
    if parsed.scheme not in ("http", "https") or not parsed.hostname:
        return False
    host = parsed.hostname.lower()
    if host in {"localhost", "0.0.0.0"} or host.endswith((".local", ".localhost", ".internal", ".test")):
        return False
    if re.match(r"^(10\.|127\.|169\.254\.|172\.(1[6-9]|2[0-9]|3[0-1])\.|192\.168\.)", host):
        return False
    return True


def normalize_result(raw):
    url = str(raw.get("url") or "").strip()
    title = str(raw.get("title") or "").strip()
    snippet = str(raw.get("content") or raw.get("snippet") or "").strip()
    source = urlparse(url).hostname or str(raw.get("engine") or "").strip()
    return {
        "title": title,
        "url": url,
        "snippet": snippet,
        "source": source,
        "publishedAt": str(raw.get("publishedDate") or raw.get("pubdate") or "").strip(),
        "score": float(raw.get("score") or 0),
    }


def score_source(result, qtype, terms):
    host = (result.get("source") or "").lower()
    haystack = " ".join([result.get("title", ""), result.get("snippet", ""), host]).lower()
    score = result.get("score", 0) or 0
    for term in terms:
        if term in haystack:
            score += 1.2
    if re.search(r"\b(gov|government|official|ministry|authority)\b", haystack):
        score += 4.0 if qtype in {"visa_legal", "country_life"} else 1.5
    if re.search(r"(linkedin|glassdoor|indeed|bayt|gulf|hays|michael page|mercer|payscale)", haystack):
        score += 2.5 if qtype in {"salary", "recruiter_directory", "market_timing"} else 1.0
    if re.search(r"(bloomberg|reuters|ft\.com|thenationalnews|arabianbusiness|zawya|gulfnews)", host):
        score += 2.5 if qtype in {"current_market", "company_research"} else 1.0
    if re.search(r"(utm_|facebook|instagram|tiktok|pinterest)", host):
        score -= 2
    return score


def call_search(query, locale, limit):
    if not SEARXNG_ENDPOINT:
        raise RuntimeError("SEARXNG_ENDPOINT is not configured")
    response = requests.get(
        SEARXNG_ENDPOINT,
        params={"q": query, "format": "json", "language": locale, "safesearch": "1"},
        timeout=REQUEST_TIMEOUT,
        headers={"Accept": "application/json"},
    )
    response.raise_for_status()
    body = response.json()
    results = [normalize_result(item) for item in body.get("results", []) if isinstance(item, dict)]
    return [item for item in results if item.get("url")]


def fetch_source_text(url):
    if not is_public_http_url(url):
        return {"fetchStatus": "private_or_invalid_url", "text": "", "metadata": {}}
    try:
        downloaded = trafilatura.fetch_url(url, no_ssl=True)
        if not downloaded:
            return {"fetchStatus": "blocked", "text": "", "metadata": {}}
        metadata = trafilatura.extract_metadata(downloaded)
        text = trafilatura.extract(
            downloaded,
            include_comments=False,
            include_tables=False,
            favor_precision=True,
            url=url,
        )
        return {
            "fetchStatus": "fetched" if text else "empty",
            "text": (text or "")[:18000],
            "metadata": {
                "title": getattr(metadata, "title", "") if metadata else "",
                "date": getattr(metadata, "date", "") if metadata else "",
                "language": getattr(metadata, "language", "") if metadata else "",
            },
        }
    except Exception as exc:
        return {"fetchStatus": "blocked", "text": "", "metadata": {"error": type(exc).__name__}}


def split_passages(text):
    text = re.sub(r"\s+", " ", (text or "")).strip()
    if not text:
        return []
    sentences = re.split(r"(?<=[.!?])\s+", text)
    passages = []
    buffer = ""
    for sentence in sentences:
        sentence = sentence.strip()
        if len(sentence) < 45:
            continue
        if len(f"{buffer} {sentence}") > 380:
            if buffer:
                passages.append(buffer)
            buffer = sentence
        else:
            buffer = f"{buffer} {sentence}".strip()
        if len(passages) >= 8:
            break
    if buffer and len(passages) < 8:
        passages.append(buffer)
    return passages


def score_passage(passage, qtype, terms):
    text = passage.lower()
    score = 0.0
    for term in terms:
        if term in text:
            score += 1.4
    pattern = PASSAGE_PATTERNS.get(qtype)
    if pattern and pattern.search(passage):
        score += 3.0
    if re.search(r"\b(202[0-9]|19|20)\b|(\d+(,\d{3})*(\.\d+)?\s*(aed|sar|usd|%|per month|monthly|annually))", passage, re.I):
        score += 1.4
    return score


def trim_words(text, words=42):
    parts = text.split()
    if len(parts) <= words:
        return text
    return " ".join(parts[:words]) + "..."


def quality_score(text, status, ranked):
    score = 0.0
    if status == "fetched":
        score += 0.35
    elif status in {"snippet", "empty"}:
        score += 0.12
    length = len(text or "")
    if length >= 800:
        score += 0.25
    elif length >= 300:
        score += 0.16
    elif length >= 120:
        score += 0.08
    score += 0.18 if len(ranked) >= 2 else 0.09 if len(ranked) == 1 else 0.0
    score += min(0.22, max([item["score"] for item in ranked] or [0]) / 30)
    return round(max(0.0, min(1.0, score)), 2)


def build_evidence(query, qtype, results):
    terms = query_terms(query)
    selected = sorted(
        [item for item in results if is_public_http_url(item.get("url", ""))],
        key=lambda item: score_source(item, qtype, terms),
        reverse=True,
    )[:MAX_SOURCES]
    evidence = []
    for index, source in enumerate(selected, start=1):
        fetched = fetch_source_text(source["url"])
        text = fetched["text"] or source.get("snippet", "")
        status = fetched["fetchStatus"] if fetched["text"] else "snippet"
        ranked = [
            {"text": trim_words(passage), "score": score_passage(passage, qtype, terms)}
            for passage in split_passages(text)
        ]
        ranked = sorted(ranked, key=lambda item: item["score"], reverse=True)[:2]
        evidence.append(
            {
                "sourceId": f"s{index}",
                "title": source.get("title") or fetched.get("metadata", {}).get("title", ""),
                "url": source.get("url", ""),
                "source": source.get("source", ""),
                "publishedAt": source.get("publishedAt") or fetched.get("metadata", {}).get("date", ""),
                "language": fetched.get("metadata", {}).get("language", ""),
                "sourceType": qtype,
                "reliability": round(max(0.0, min(1.0, score_source(source, qtype, terms) / 8)), 2),
                "fetchStatus": status,
                "qualityScore": quality_score(text, status, ranked),
                "usedPassages": ranked,
            }
        )
    return evidence


def summarize_evidence(evidence):
    count = len(evidence)
    fetched = sum(1 for item in evidence if item.get("fetchStatus") == "fetched")
    blocked = sum(1 for item in evidence if item.get("fetchStatus") in {"blocked", "unsupported", "timeout", "private_or_invalid_url"})
    snippet = count - fetched - blocked
    average = round(sum(float(item.get("qualityScore") or 0) for item in evidence) / count, 2) if count else 0.0
    return {
        "sourceCount": count,
        "fetchedCount": fetched,
        "snippetFallbackCount": snippet,
        "blockedCount": blocked,
        "averageQuality": average,
        "answerMode": "source_grounded" if fetched and average >= 0.45 else "snippet_grounded" if count else "no_sources",
    }


def compose_answer(qtype, evidence):
    points = []
    for source in evidence:
        for passage in source.get("usedPassages", []):
            text = passage.get("text", "").strip()
            if text and text not in points:
                points.append(text)
    points = points[:4]
    has_fetched = any(source.get("fetchStatus") == "fetched" for source in evidence)
    return {
        "type": qtype,
        "confidence": 0.78 if points and has_fetched else 0.58 if points else 0.42,
        "headline": HEADLINES.get(qtype, HEADLINES["general_web_answer"]),
        "shortAnswer": "I checked the sources and pulled out the strongest signals instead of just listing links."
        if points
        else "I could not extract enough readable page text, so I’m showing the best available source results as a fallback.",
        "keyPoints": points,
        "caveat": "These are public-source signals, so verify anything legal, salary-related, or time-sensitive before relying on it."
        if has_fetched
        else "Some sources blocked full extraction, so this answer relies more heavily on search snippets.",
        "nextStep": "I can turn this into a job search, shortlist, or practical next-step plan.",
    }


@app.get("/health")
def health():
    return jsonify(
        {
            "ok": True,
            "service": "senna-web-answer-service",
            "searchConfigured": bool(SEARXNG_ENDPOINT),
            "extractor": "trafilatura",
        }
    )


@app.route("/answer", methods=["GET", "POST"])
def answer():
    auth = require_auth()
    if auth:
        return auth
    started = time.perf_counter()
    payload = request.get_json(silent=True) if request.method == "POST" else {}
    query = (payload.get("query") if isinstance(payload, dict) else None) or request.args.get("q") or request.args.get("query") or ""
    locale = ((payload.get("locale") if isinstance(payload, dict) else None) or request.args.get("locale") or "en")[:5]
    limit = int((payload.get("limit") if isinstance(payload, dict) else None) or request.args.get("limit") or 6)
    query = str(query).strip()[:220]
    limit = max(5, min(8, limit))
    if len(query) < 3:
        return jsonify({"ok": False, "error": "query_too_short"}), 400

    qtype = classify_query(query)
    search_started = time.perf_counter()
    results = call_search(query, locale, limit)
    search_ms = round((time.perf_counter() - search_started) * 1000, 1)
    answer_started = time.perf_counter()
    evidence = build_evidence(query, qtype, results)
    evidence_summary = summarize_evidence(evidence)
    answer_payload = compose_answer(qtype, evidence)
    answer_ms = round((time.perf_counter() - answer_started) * 1000, 1)
    return jsonify(
        {
            "ok": True,
            "query": query,
            "locale": locale,
            "classification": {"type": qtype, "confidence": 0.78 if evidence_summary["averageQuality"] >= 0.45 else 0.58},
            "answer": answer_payload,
            "evidence": evidence,
            "evidenceSummary": evidence_summary,
            "results": results[:limit],
            "source": "searxng+trafilatura",
            "searchedAt": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
            "timings": {
                "cacheHit": False,
                "searchMs": search_ms,
                "answerMs": answer_ms,
                "totalMs": round((time.perf_counter() - started) * 1000, 1),
            },
            "requestId": hashlib.md5(f"{query}|{time.time()}".encode("utf-8")).hexdigest()[:12],
        }
    )

