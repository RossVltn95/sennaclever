#!/usr/bin/env node

function clean(value) {
  return String(value || "").replace(/\s+/g, " ").trim();
}

function unique(items) {
  return [...new Set((items || []).map(clean).filter(Boolean))];
}

function formatNaturalList(items) {
  const list = unique(items);
  if (list.length <= 1) return list[0] || "";
  if (list.length === 2) return `${list[0]} and ${list[1]}`;
  return `${list.slice(0, -1).join(", ")} and ${list[list.length - 1]}`;
}

function evidence(sourceText, overrides = {}) {
  const text = clean(sourceText).toLowerCase();
  return {
    sourceText: clean(sourceText),
    cleanText: text,
    hasCv: !!overrides.hasCv,
    cvProfile: clean(overrides.cvProfile),
    searchFocus: clean(overrides.searchFocus),
    cvSignals: unique(overrides.cvSignals || []),
    targetRoles: unique(overrides.targetRoles || []),
    targetLocations: unique(
      []
        .concat(overrides.targetLocations || [])
        .concat(/\b(?:dubai|dxb|uae)\b/i.test(text) ? ["Dubai"] : [])
        .concat(/\briyadh|saudi|ksa\b/i.test(text) ? ["Riyadh"] : [])
        .concat(/\babu\s+dhabi\b/i.test(text) ? ["Abu Dhabi"] : [])
    ),
    role: overrides.role || null,
    applicationHistory: overrides.applicationHistory || [],
  };
}

const frames = [
  {
    id: "interview_trouble",
    patterns: [
      /\b(?:not getting interviews|no interviews|not getting calls|no calls|no replies|low response|rejected|rejections|ghosted|no response)\b/i,
      /\b(?:why|how come|what)\b.*\b(?:not|never|no)\b.*\b(?:interviews?|responses?|replies|calls)\b/i,
    ],
    requiredSignals: ["cv", "search"],
  },
  {
    id: "role_apply_decision",
    patterns: [
      /\b(?:should i apply|worth applying|good idea to apply|do i have a chance|am i qualified|qualified enough|apply to this|apply for this)\b.*\b(?:role|job|one|position|application|apply)\b/i,
      /\b(?:would they interview me|interview chance|fit for this|match this role|too junior|too senior|underqualified|overqualified)\b/i,
    ],
    requiredSignals: ["role", "cv"],
  },
  {
    id: "career_direction",
    patterns: [
      /\b(?:career direction|what jobs suit me|which roles suit me|what jobs should i target|which jobs should i target|what roles should i target|what should i target|what should i do next|don'?t know what i want|dont know what i want|don'?t know what jobs|dont know what jobs|help me plan|career plan|stuck)\b/i,
      /\b(?:should i move|move jobs or stay|stay where i am|best direction|next move)\b/i,
    ],
    requiredSignals: ["cv", "search"],
  },
  {
    id: "search_strategy",
    patterns: [
      /\b(?:job search strategy|search strategy|structure my search|structure my job search|organise my search|organise my job search|organize my search|organize my job search|how should i search|how should i structure my search|how should i structure my job search|how many applications|applications per week|spray and pray|direct applications|job boards)\b/i,
      /\b(?:how|what)\b.*\b(?:find|land|get)\b.*\b(?:better|stronger|relevant)\b.*\b(?:jobs|roles|opportunities)\b/i,
    ],
    requiredSignals: ["cv", "search"],
  },
  {
    id: "market_relocation",
    patterns: [
      /\b(?:move to|relocat|visa|sponsorship|visit visa|work authorisation|work authorization|right to work)\b.*\b(?:dubai|uae|riyadh|saudi|ksa|abu dhabi|gcc|middle east|mena)\b/i,
      /\b(?:dubai|uae|riyadh|saudi|ksa|abu dhabi|gcc|middle east|mena)\b.*\b(?:move|relocat|visa|sponsorship|market|better|worth it|right market)\b/i,
    ],
    requiredSignals: ["cv"],
  },
  {
    id: "cv_positioning",
    patterns: [
      /\b(?:cv positioning|resume positioning|position my cv|make my cv stronger|why is my cv not working|cv not working|profile positioning|linkedin positioning)\b/i,
      /\b(?:cv|resume|profile|linkedin)\b.*\b(?:stronger|weak|positioning|not working|screening|shortlist|stand out|first page|page one)\b/i,
    ],
    requiredSignals: ["cv", "search"],
  },
  {
    id: "linkedin_positioning",
    patterns: [
      /\b(?:linkedin|profile headline|profile summary|about section|open to work|linkedin profile)\b.*\b(?:position|improve|stronger|better|optimise|optimize|visible|visibility|recruiter|search)\b/i,
      /\b(?:recruiters?|hiring managers?)\b.*\b(?:find me|notice me|see my profile|linkedin|profile)\b/i,
    ],
    requiredSignals: ["cv", "search"],
  },
  {
    id: "recruiter_outreach",
    patterns: [
      /\b(?:recruiter|recruiters|hiring manager|talent acquisition|referral|intro|introduction|network|networking|linkedin message|cold message|follow up)\b.*\b(?:message|contact|approach|reach out|reply|respond|strategy|script|note)\b/i,
      /\b(?:message|contact|approach|reach out|email|dm|write to)\b.*\b(?:recruiter|recruiters|hiring manager|talent acquisition|referral|people|alumni)\b/i,
      /\b(?:who should i contact|how should i follow up|how do i get a referral|what should i message)\b/i,
    ],
    requiredSignals: ["cv", "search"],
  },
  {
    id: "interview_prep",
    patterns: [
      /\b(?:prepare|prep|practice|mock|coach)\b.*\b(?:interview|interviews|case study|technical round|behavioural|behavioral)\b/i,
      /\b(?:interview questions|what will they ask|case interview|technical interview|behavioural interview|behavioral interview|tell me about yourself)\b/i,
    ],
    requiredSignals: ["cv"],
  },
  {
    id: "offer_decision",
    patterns: [
      /\b(?:should i accept|accept the offer|reject the offer|take the offer|offer decision|counter offer|counteroffer|negotiate the offer)\b/i,
      /\b(?:offer|salary increase|higher salary|package)\b.*\b(?:worth it|accept|reject|take|negotiate|counter)\b/i,
    ],
    requiredSignals: ["cv"],
  },
  {
    id: "salary_negotiation",
    patterns: [
      /\b(?:salary|compensation|package|bonus|allowance|benefits|equity|housing)\b.*\b(?:negotiate|ask for|expect|range|fair|market|counter|increase|push back)\b/i,
      /\b(?:how much should i ask|what should i ask for|salary expectation|expected salary|counter offer|counteroffer)\b/i,
    ],
    requiredSignals: ["cv"],
  },
  {
    id: "application_follow_up",
    patterns: [
      /\b(?:follow up|chase|check in|checking in|after applying|after i applied|after the application)\b.*\b(?:application|recruiter|hiring manager|email|message|reply|response)\b/i,
      /\b(?:when should i follow up|should i chase|no response after applying|haven't heard back|havent heard back)\b/i,
    ],
    requiredSignals: ["search"],
  },
];

function scoreFrame(ev, frame) {
  let score = 0;
  const reasons = [];
  frame.patterns.forEach((pattern) => {
    if (pattern.test(ev.cleanText)) {
      score += 18;
      reasons.push(`matched_${frame.id}`);
    }
  });
  if (!score) {
    return { frame, score: 0, reasons };
  }
  frame.requiredSignals.forEach((signal) => {
    if (signal === "role" && ev.role) {
      score += 10;
      reasons.push("has_role");
    }
    if (signal === "cv" && ev.hasCv) {
      score += 8;
      reasons.push("has_cv");
    }
    if (signal === "search" && ev.searchFocus) {
      score += 6;
      reasons.push("has_search_context");
    }
  });
  if (frame.id === "interview_trouble" && ev.applicationHistory.length) {
    score += 5;
    reasons.push("has_application_history");
  }
  if (frame.id === "career_direction" && ev.targetLocations.length) {
    score += 4;
    reasons.push("has_market_preference");
  }
  if (frame.id === "market_relocation" && ev.targetLocations.length) {
    score += 8;
    reasons.push("has_target_market");
  }
  if (frame.id === "search_strategy" && (ev.targetRoles.length || ev.targetLocations.length)) {
    score += 6;
    reasons.push("has_search_preferences");
  }
  if (frame.id === "cv_positioning" && (ev.cvSignals.length || ev.cvProfile)) {
    score += 7;
    reasons.push("has_cv_signals");
  }
  if (
    frame.id === "offer_decision" &&
    /\b(?:salary|package|bonus|counter|counteroffer|notice|start date|probation|relocation)\b/i.test(ev.cleanText)
  ) {
    score += 7;
    reasons.push("has_offer_factors");
  }
  if (frame.id === "recruiter_outreach" && (ev.targetRoles.length || ev.role)) {
    score += 7;
    reasons.push("has_outreach_target");
  }
  if (frame.id === "interview_prep" && (ev.role || ev.cvSignals.length)) {
    score += 8;
    reasons.push("has_interview_context");
  }
  if (
    frame.id === "salary_negotiation" &&
    /\b(?:base|bonus|allowance|benefits|equity|housing|relocation|counter|range|expectation|package)\b/i.test(ev.cleanText)
  ) {
    score += 8;
    reasons.push("has_compensation_terms");
  }
  if (frame.id === "linkedin_positioning" && (ev.targetRoles.length || ev.cvSignals.length)) {
    score += 6;
    reasons.push("has_profile_positioning_context");
  }
  return { frame, score, reasons };
}

function rank(scored) {
  const entries = scored.map((entry) => ({
    id: entry.frame.id,
    score: Math.max(0, Math.min(60, entry.score)),
    reasons: entry.reasons,
  }));
  const max = entries.reduce((current, entry) => Math.max(current, entry.score), 0);
  const total = entries.reduce((sum, entry) => sum + Math.exp((entry.score - max) / 8), 0);
  return entries
    .map((entry) => ({
      ...entry,
      probability: total ? Math.exp((entry.score - max) / 8) / total : 0,
    }))
    .sort((a, b) => b.probability - a.probability);
}

function confidence(entries) {
  const top = entries[0] ? entries[0].probability : 0;
  const second = entries[1] ? entries[1].probability : 0;
  return {
    top,
    margin: top - second,
    label:
      top >= 0.68 && top - second >= 0.2
        ? "strong"
        : top >= 0.48 && top - second >= 0.08
        ? "usable"
        : "ambiguous",
  };
}

function roleFit(ev) {
  const role = ev.role || {};
  const haystack = [
    role.title,
    role.company,
    role.location,
    role.sector,
    role.description,
    role.summary,
  ]
    .map(clean)
    .join(" ")
    .toLowerCase();
  const matchedSignals = ev.cvSignals.filter((signal) =>
    haystack.includes(clean(signal).toLowerCase())
  );
  let score = 42;
  if (ev.hasCv) score += 10;
  if (role.title) score += 8;
  score += Math.min(24, matchedSignals.length * 6);
  return { score: Math.max(0, Math.min(100, score)), matchedSignals };
}

function missingEvidence(ev, frameId) {
  const missing = [];
  if (!ev.hasCv) missing.push("CV context");
  if (frameId === "role_apply_decision" && !ev.role) missing.push("selected role");
  if (frameId === "career_direction" && !ev.targetRoles.length && !ev.cvProfile) {
    missing.push("target role lane");
  }
  if (frameId === "interview_trouble" && !ev.searchFocus && !ev.targetRoles.length) {
    missing.push("recent search target");
  }
  if (frameId === "market_relocation" && !ev.targetLocations.length) {
    missing.push("target market");
  }
  if (frameId === "search_strategy" && !ev.targetRoles.length && !ev.searchFocus) {
    missing.push("target role lane");
  }
  if (
    frameId === "offer_decision" &&
    !/\b(?:salary|package|bonus|equity|allowance|benefits|notice|probation|start date|relocation)\b/i.test(ev.cleanText)
  ) {
    missing.push("offer details");
  }
  if (frameId === "recruiter_outreach" && !ev.targetRoles.length && !ev.role) {
    missing.push("outreach target");
  }
  if (frameId === "interview_prep" && !ev.role && !ev.searchFocus) {
    missing.push("interview target");
  }
  if (
    frameId === "salary_negotiation" &&
    !/\b(?:salary|package|bonus|equity|allowance|benefits|base|range|current|expectation|offer|counter)\b/i.test(ev.cleanText)
  ) {
    missing.push("compensation details");
  }
  if (frameId === "application_follow_up" && !ev.role && !ev.applicationHistory.length) {
    missing.push("application context");
  }
  return missing;
}

function resolvePolicy(frameId, ev, missing) {
  const roleFitResult = frameId === "role_apply_decision" ? roleFit(ev) : null;
  if (!frameId) return { id: "answer_with_caveat", roleFit: roleFitResult };
  if (missing.includes("CV context")) return { id: "ask_for_cv", roleFit: roleFitResult };
  if (frameId === "role_apply_decision") {
    if (missing.includes("selected role")) return { id: "ask_for_role", roleFit: roleFitResult };
    if (roleFitResult && roleFitResult.score >= 78) return { id: "apply_now", roleFit: roleFitResult };
    if (roleFitResult && roleFitResult.score >= 58) return { id: "tailor_then_apply", roleFit: roleFitResult };
    return { id: "deprioritise_role", roleFit: roleFitResult };
  }
  if (frameId === "interview_trouble") return { id: "diagnose_search", roleFit: roleFitResult };
  if (frameId === "career_direction") {
    return { id: missing.includes("target role lane") ? "clarify_target_lane" : "score_target_lanes", roleFit: roleFitResult };
  }
  if (frameId === "search_strategy") {
    return { id: missing.includes("target role lane") ? "clarify_target_lane" : "quality_search_plan", roleFit: roleFitResult };
  }
  if (frameId === "market_relocation") {
    return { id: missing.includes("target market") ? "clarify_market" : "score_market_move", roleFit: roleFitResult };
  }
  if (frameId === "cv_positioning") return { id: "reposition_cv", roleFit: roleFitResult };
  if (frameId === "linkedin_positioning") return { id: "reposition_linkedin", roleFit: roleFitResult };
  if (frameId === "recruiter_outreach") {
    return {
      id: missing.includes("outreach target")
        ? "ask_for_outreach_target"
        : "plan_recruiter_outreach",
      roleFit: roleFitResult,
    };
  }
  if (frameId === "interview_prep") {
    return {
      id: missing.includes("interview target")
        ? "ask_for_interview_target"
        : "prepare_interview_scorecard",
      roleFit: roleFitResult,
    };
  }
  if (frameId === "offer_decision") {
    return { id: missing.includes("offer details") ? "ask_for_offer_terms" : "negotiate_offer", roleFit: roleFitResult };
  }
  if (frameId === "salary_negotiation") {
    return {
      id: missing.includes("compensation details")
        ? "ask_for_compensation_context"
        : "negotiate_compensation",
      roleFit: roleFitResult,
    };
  }
  if (frameId === "application_follow_up") {
    return {
      id: missing.includes("application context")
        ? "ask_for_application_context"
        : "send_application_follow_up",
      roleFit: roleFitResult,
    };
  }
  return { id: "answer_with_caveat", roleFit: roleFitResult };
}

function readinessBand(score) {
  const numeric = Math.max(0, Math.min(100, Number(score) || 0));
  if (numeric >= 76) return "strong";
  if (numeric >= 55) return "developing";
  return "weak";
}

function readinessArea(label) {
  return { label, score: 35, reasons: [] };
}

function addSignal(area, weight, reason) {
  if (!area || !weight) return;
  area.score += weight;
  if (reason) area.reasons.push(reason);
}

function finishArea(area) {
  const score = Math.max(0, Math.min(100, Math.round(area.score || 0)));
  return {
    label: area.label,
    score,
    band: readinessBand(score),
    reasons: area.reasons.slice(0, 6),
  };
}

function weakestArea(scores, allowedKeys) {
  const keys = allowedKeys || Object.keys(scores || {});
  return keys.reduce((weakest, key) => {
    if (!scores[key]) return weakest;
    if (!weakest || scores[key].score < scores[weakest].score) return key;
    return weakest;
  }, "");
}

function policyFocus(policyId, scores) {
  if (policyId === "ask_for_cv") return "cvPositioning";
  if (policyId === "ask_for_role" || policyId === "clarify_target_lane") {
    return "targetClarity";
  }
  if (policyId === "score_target_lanes") return "targetClarity";
  if (policyId === "diagnose_search") {
    return weakestArea(scores, ["cvPositioning", "targetClarity", "searchExecution"]);
  }
  if (policyId === "tailor_then_apply" || policyId === "reposition_cv") {
    return "cvPositioning";
  }
  if (policyId === "reposition_linkedin") return "outreachReadiness";
  if (policyId === "ask_for_outreach_target" || policyId === "plan_recruiter_outreach") {
    return "outreachReadiness";
  }
  if (policyId === "ask_for_interview_target" || policyId === "prepare_interview_scorecard") {
    return "interviewReadiness";
  }
  if (policyId === "quality_search_plan") return "searchExecution";
  if (policyId === "clarify_market" || policyId === "score_market_move") {
    return "relocationClarity";
  }
  if (policyId === "ask_for_offer_terms" || policyId === "negotiate_offer") {
    return "offerDecisionReadiness";
  }
  if (policyId === "ask_for_compensation_context" || policyId === "negotiate_compensation") {
    return "offerDecisionReadiness";
  }
  if (policyId === "ask_for_application_context" || policyId === "send_application_follow_up") {
    return "searchExecution";
  }
  if (policyId === "apply_now") return "interviewReadiness";
  return weakestArea(scores);
}

function readiness(ev, frameId, policy) {
  const areas = {
    cvPositioning: readinessArea("CV positioning"),
    targetClarity: readinessArea("Target clarity"),
    searchExecution: readinessArea("Search execution"),
    outreachReadiness: readinessArea("Outreach readiness"),
    interviewReadiness: readinessArea("Interview readiness"),
    relocationClarity: readinessArea("Relocation clarity"),
    offerDecisionReadiness: readinessArea("Offer decision readiness"),
  };
  if (ev.hasCv) {
    addSignal(areas.cvPositioning, 20, "CV available");
    addSignal(areas.outreachReadiness, 5, "CV available");
    addSignal(areas.interviewReadiness, 10, "CV available");
  }
  addSignal(areas.cvPositioning, Math.min(10, ev.cvSignals.length * 2), "CV evidence signals detected");
  if (ev.cvProfile) addSignal(areas.cvPositioning, 8, "CV profile summary available");
  if (ev.targetRoles.length) {
    addSignal(areas.targetClarity, 12, "Target roles known");
    addSignal(areas.searchExecution, 6, "Target roles known");
    addSignal(areas.outreachReadiness, 8, "Target roles known");
  }
  if ((ev.targetSectors || []).length) addSignal(areas.targetClarity, 10, "Target sectors known");
  if (ev.targetLocations.length) {
    addSignal(areas.targetClarity, 10, "Target markets known");
    addSignal(areas.outreachReadiness, 8, "Target markets known");
    addSignal(areas.relocationClarity, 18, "Target market named");
  }
  if (ev.searchFocus) {
    addSignal(areas.targetClarity, 8, "Search focus known");
    addSignal(areas.searchExecution, 12, "Search focus known");
    addSignal(areas.outreachReadiness, 8, "Search focus known");
  }
  if (ev.applicationHistory.length) addSignal(areas.searchExecution, 8, "Application outcomes available");
  if (ev.targetRoles.length && ev.targetLocations.length) {
    addSignal(areas.searchExecution, 10, "Role and market are both known");
  }
  if (ev.role && ev.role.title) {
    addSignal(areas.interviewReadiness, 10, "Selected role known");
    addSignal(areas.offerDecisionReadiness, 8, "Role context available");
  }
  if (ev.cvSignals.length) addSignal(areas.interviewReadiness, 5, "Interview proof points available");
  if (/\b(?:visa|sponsorship|relocation|relocate|notice period|work authorisation|work authorization|right to work)\b/i.test(ev.cleanText)) {
    addSignal(areas.relocationClarity, 8, "Mobility constraint mentioned");
  }
  if (ev.role && ev.role.location) addSignal(areas.relocationClarity, 4, "Role location available");
  if (/\b(?:offer|package|salary|bonus|equity|allowance|benefits|notice|probation|start date|counteroffer|counter offer)\b/i.test(ev.cleanText)) {
    addSignal(areas.offerDecisionReadiness, 18, "Offer terms mentioned");
  }
  const scores = Object.fromEntries(Object.entries(areas).map(([key, area]) => [key, finishArea(area)]));
  const ordered = Object.entries(scores)
    .map(([id, area]) => ({ id, ...area }))
    .sort((a, b) => a.score - b.score);
  const nextFocus = policyFocus(policy.id, scores);
  const focusArea = scores[nextFocus] || ordered[0];
  const average = ordered.reduce((sum, entry) => sum + entry.score, 0) / Math.max(1, ordered.length);
  return {
    frame: frameId,
    overall: { score: Math.round(average), band: readinessBand(average) },
    scores,
    weakestAreas: ordered.slice(0, 3),
    strongestAreas: ordered.slice(-3).reverse(),
    nextFocus,
    nextFocusLabel: focusArea ? focusArea.label : "",
    nextFocusScore: focusArea ? focusArea.score : 0,
    nextFocusBand: focusArea ? focusArea.band : "",
  };
}

function run(sourceText, overrides) {
  const ev = evidence(sourceText, overrides);
  const ranked = rank(frames.map((frame) => scoreFrame(ev, frame)));
  const conf = confidence(ranked);
  const selectedFrame = ranked[0] && ranked[0].score > 0 ? ranked[0].id : "";
  const missing = missingEvidence(ev, selectedFrame);
  const policy = resolvePolicy(selectedFrame, ev, missing);
  const readinessResult = readiness(ev, selectedFrame, policy);
  return {
    handled: !!selectedFrame && conf.label !== "ambiguous",
    selectedFrame,
    confidence: conf.label,
    missingEvidence: missing,
    policy: policy.id,
    roleFit: policy.roleFit,
    readiness: readinessResult,
    readinessNextFocus: readinessResult.nextFocus,
    naturalTargets: formatNaturalList(ev.targetRoles),
  };
}

const cases = [
  {
    q: "Why am I not getting interviews?",
    ctx: {
      hasCv: true,
      searchFocus: "private credit analyst roles in Dubai",
      cvSignals: ["private credit", "financial modelling"],
    },
    frame: "interview_trouble",
    handled: true,
    policy: "diagnose_search",
    readinessNextFocus: "targetClarity",
  },
  {
    q: "Should I apply to this role?",
    ctx: {
      hasCv: true,
      cvSignals: ["private credit", "financial modelling", "investment analysis"],
      role: {
        title: "Private Credit Analyst",
        company: "Example Capital",
        description: "Private credit investment analysis and financial modelling",
      },
    },
    frame: "role_apply_decision",
    handled: true,
    policy: "apply_now",
    minFit: 70,
    readinessNextFocus: "interviewReadiness",
  },
  {
    q: "Am I qualified enough for this role?",
    ctx: {
      hasCv: true,
      cvSignals: ["financial modelling"],
      role: {
        title: "Private Credit Analyst",
        company: "Example Capital",
        description: "Private credit investment analysis and stakeholder reporting",
      },
    },
    frame: "role_apply_decision",
    handled: true,
    policy: "tailor_then_apply",
    readinessNextFocus: "cvPositioning",
  },
  {
    q: "I don't know what jobs I should target in Dubai",
    ctx: {
      hasCv: true,
      cvProfile: "finance analyst with investment analysis experience",
      searchFocus: "Dubai finance roles",
      targetRoles: ["investment analyst", "private credit analyst"],
    },
    frame: "career_direction",
    handled: true,
    policy: "score_target_lanes",
    readinessNextFocus: "targetClarity",
  },
  {
    q: "hello",
    ctx: {},
    frame: "",
    handled: false,
    policy: "answer_with_caveat",
    readinessNextFocus: "cvPositioning",
  },
  {
    q: "How should I structure my job search in Dubai?",
    ctx: {
      hasCv: true,
      searchFocus: "finance analyst roles in Dubai",
      targetRoles: ["finance analyst"],
      targetLocations: ["Dubai"],
    },
    frame: "search_strategy",
    handled: true,
    policy: "quality_search_plan",
    readinessNextFocus: "searchExecution",
  },
  {
    q: "Is it worth relocating to Riyadh with visa sponsorship?",
    ctx: {
      hasCv: true,
      cvProfile: "investment analyst",
      targetLocations: ["Riyadh"],
    },
    frame: "market_relocation",
    handled: true,
    policy: "score_market_move",
    readinessNextFocus: "relocationClarity",
  },
  {
    q: "Why is my CV not working for shortlist screening?",
    ctx: {
      hasCv: true,
      searchFocus: "private equity analyst roles",
      cvSignals: ["financial modelling", "investment analysis"],
    },
    frame: "cv_positioning",
    handled: true,
    policy: "reposition_cv",
    readinessNextFocus: "cvPositioning",
  },
  {
    q: "Should I accept the offer or negotiate the salary package?",
    ctx: {
      hasCv: true,
      cvProfile: "finance manager",
    },
    frame: "offer_decision",
    handled: true,
    policy: "negotiate_offer",
    readinessNextFocus: "offerDecisionReadiness",
  },
  {
    q: "Should I accept the offer?",
    ctx: {
      hasCv: true,
      cvProfile: "finance manager",
    },
    frame: "offer_decision",
    handled: true,
    policy: "ask_for_offer_terms",
    readinessNextFocus: "offerDecisionReadiness",
  },
  {
    q: "How should I message recruiters for private credit roles?",
    ctx: {
      hasCv: true,
      searchFocus: "private credit analyst roles in Dubai",
      targetRoles: ["private credit analyst"],
      cvSignals: ["credit analysis", "financial modelling"],
    },
    frame: "recruiter_outreach",
    handled: true,
    policy: "plan_recruiter_outreach",
    readinessNextFocus: "outreachReadiness",
  },
  {
    q: "Can you help me improve my LinkedIn so recruiters notice me?",
    ctx: {
      hasCv: true,
      searchFocus: "investment analyst roles",
      targetRoles: ["investment analyst"],
      cvSignals: ["investment analysis", "portfolio reporting"],
    },
    frame: "linkedin_positioning",
    handled: true,
    policy: "reposition_linkedin",
    readinessNextFocus: "outreachReadiness",
  },
  {
    q: "Prepare me for this interview",
    ctx: {
      hasCv: true,
      cvSignals: ["financial modelling", "stakeholder management"],
      role: {
        title: "Investment Analyst",
        company: "Example Capital",
      },
    },
    frame: "interview_prep",
    handled: true,
    policy: "prepare_interview_scorecard",
    readinessNextFocus: "interviewReadiness",
  },
  {
    q: "What salary range should I ask for and how should I negotiate the package?",
    ctx: {
      hasCv: true,
      cvProfile: "finance manager",
    },
    frame: "salary_negotiation",
    handled: true,
    policy: "negotiate_compensation",
    readinessNextFocus: "offerDecisionReadiness",
  },
  {
    q: "When should I follow up after applying to the recruiter?",
    ctx: {
      hasCv: true,
      searchFocus: "private equity analyst roles",
      applicationHistory: [{ status: "submitted", title: "Investment Analyst" }],
    },
    frame: "application_follow_up",
    handled: true,
    policy: "send_application_follow_up",
    readinessNextFocus: "searchExecution",
  },
];

let failed = 0;
cases.forEach((item) => {
  const result = run(item.q, item.ctx);
  const ok =
    result.selectedFrame === item.frame &&
    result.handled === item.handled &&
    (!item.policy || result.policy === item.policy) &&
    (!item.readinessNextFocus ||
      result.readinessNextFocus === item.readinessNextFocus) &&
    (!item.minFit || (result.roleFit && result.roleFit.score >= item.minFit));
  if (!ok) {
    failed += 1;
    console.error("FAIL", { item, result });
  }
});

if (failed) {
  process.exit(1);
}

console.log(`PASS ${cases.length} career-coaching engine fixtures`);
