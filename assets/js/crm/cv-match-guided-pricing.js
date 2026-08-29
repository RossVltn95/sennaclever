(function () {
  "use strict";

  var config = window.sffcCvMatchGuidedPricing || {};
  var wizard = config.wizard || {};
  var copy = config.copy || {};
  var ENTRY_CONTEXT_KEY = config.entryContextKey || "sffcGuidedPricingEntryContext";
  var stepOrder = [
    "help",
    "constraint",
    "deliverable",
    "commitment",
    "recommendation",
  ];

  function $(root, selector) {
    if (!root || !root.querySelector) {
      return null;
    }
    return root.querySelector(selector);
  }

  function $all(root, selector) {
    if (!root || !root.querySelectorAll) {
      return [];
    }
    return Array.prototype.slice.call(root.querySelectorAll(selector));
  }

  function escapeHtml(value) {
    return String(value || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function parseTagList(value) {
    return String(value || "")
      .split("|")
      .map(function (item) {
        return item.trim();
      })
      .filter(Boolean);
  }

  function extractFirstName(fullName) {
    var normalized = String(fullName || "").trim().replace(/\s+/g, " ");
    if (!normalized) {
      return "";
    }
    return normalized.split(" ")[0] || "";
  }

  function capitalizeFirstName(value) {
    var normalized = String(value || "").trim();
    if (!normalized) {
      return "";
    }
    return normalized.charAt(0).toUpperCase() + normalized.slice(1).toLowerCase();
  }

  function toPossessive(firstName) {
    var normalized = String(firstName || "").trim();
    if (!normalized) {
      return "";
    }
    return /s$/i.test(normalized) ? normalized + "'" : normalized + "'s";
  }

  function getIdentity(root) {
    var fullNameInput = $(root, "[data-guided-full-name]");
    var emailInput = $(root, "[data-guided-email]");
    var fullName = fullNameInput ? String(fullNameInput.value || "").trim() : "";
    var email = emailInput ? String(emailInput.value || "").trim() : "";
    var firstName = capitalizeFirstName(extractFirstName(fullName));

    return {
      fullName: fullName,
      email: email,
      firstName: firstName,
    };
  }

  function setCookie(name, value) {
    if (!name) {
      return;
    }
    document.cookie =
      name +
      "=" +
      encodeURIComponent(String(value || "")) +
      "; path=/; max-age=" +
      String(60 * 60 * 24 * 30);

    if (name.indexOf("sffc_signup_") === 0 || name === "sffc_contact_data") {
      var scopeMatch = document.cookie.match(
        /(?:^|;\s*)sffc_signup_scope=([^;]+)/
      );
      var scope = scopeMatch ? decodeURIComponent(scopeMatch[1]) : "";
      if (scope) {
        document.cookie =
          "sffc_signup_owner=" +
          encodeURIComponent(scope) +
          "; path=/; max-age=" +
          String(60 * 60 * 24 * 30) +
          "; SameSite=Lax";
      }
    }
  }

  function syncSignupPrefill(identity) {
    if (!config.ajaxUrl || !config.prefillNonce || !window.fetch) {
      return;
    }

    var body = new URLSearchParams();
    body.set("action", "sffc_sync_signup_prefill");
    body.set("nonce", config.prefillNonce);
    body.set("email", String(identity.email || ""));
    body.set("first_name", String(identity.firstName || ""));

    fetch(config.ajaxUrl, {
      method: "POST",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
      },
      body: body.toString(),
      keepalive: true,
    }).catch(function () {});
  }

  function applyCheckoutIdentity(root, identity) {
    var fullName = String(identity.fullName || "").trim();
    var firstName = String(identity.firstName || "").trim();
    var email = String(identity.email || "").trim();

    $all(root, "[data-pricing-checkout] input").forEach(function (input) {
      var name = String(input.getAttribute("name") || "").toLowerCase();
      var type = String(input.getAttribute("type") || "").toLowerCase();

      if ((type === "email" || name.indexOf("email") !== -1) && email) {
        input.value = email;
      }

      if (
        (name === "user_first_name" ||
          name === "mepr_first_name" ||
          name === "first_name") &&
        firstName
      ) {
        input.value = firstName;
      }

      if (
        (name === "user_firstname" || name === "mepr_firstname") &&
        firstName
      ) {
        input.value = firstName;
      }

      if (
        (name === "user_full_name" ||
          name === "full_name" ||
          name === "name" ||
          name === "mepr_name") &&
        fullName
      ) {
        input.value = fullName;
      }
    });

    if (email) {
      setCookie("sffc_signup_email", email);
    }
    if (firstName) {
      setCookie("sffc_signup_first_name", firstName);
    }
    syncSignupPrefill(identity);
  }

  function getStoredEntryContext() {
    if (!window.sessionStorage) {
      return null;
    }

    try {
      var raw = window.sessionStorage.getItem(ENTRY_CONTEXT_KEY);
      if (!raw) {
        return null;
      }
      window.sessionStorage.removeItem(ENTRY_CONTEXT_KEY);
      var parsed = JSON.parse(raw);
      return parsed && typeof parsed === "object" ? parsed : null;
    } catch (error) {
      return null;
    }
  }

  function personalizeTemplate(template, firstName) {
    var cleanFirstName = String(firstName || "").trim();
    return String(template || "").replace(/\{first_name\}/g, cleanFirstName);
  }

  function normalizeChoiceLabel(value) {
    return String(value || "").trim().toLowerCase();
  }

  function normalizeChoiceTitle(value, fallback) {
    var label = String(value || "").trim();
    return label || String(fallback || "");
  }

  function normalizeChoiceSlug(value) {
    return String(value || "")
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, "-")
      .replace(/^-+|-+$/g, "");
  }

  function applyIdentity(root, identity) {
    var firstName = String(identity.firstName || "").trim();
    var possessive = toPossessive(firstName);
    var dialogTitleNode = $(root, "[data-guided-dialog-title]");

    root.dataset.guidedFirstName = firstName;
    root.dataset.guidedFullName = String(identity.fullName || "").trim();
    root.dataset.guidedEmail = String(identity.email || "").trim();

    if (dialogTitleNode) {
      dialogTitleNode.textContent = possessive
        ? personalizeTemplate(copy.dialogTitle, firstName)
        : String(copy.dialogTitleFallback || "Building your path into private equity");
    }

    applyCheckoutIdentity(root, identity);
  }

  function getIconMarkup(key) {
    var icons = {
      signal:
        '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 18a7 7 0 0 1 14 0"/><path d="M8.5 18a3.5 3.5 0 0 1 7 0"/><circle cx="12" cy="18" r="1.2"/></svg>',
      globe:
        '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3c2.8 3 4.2 6 4.2 9s-1.4 6-4.2 9c-2.8-3-4.2-6-4.2-9s1.4-6 4.2-9Z"/></svg>',
      document:
        '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 3h6l5 5v13H8z"/><path d="M14 3v5h5"/><path d="M10 13h6"/><path d="M10 17h6"/></svg>',
      workflow:
        '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="7" height="6" rx="1.5"/><rect x="14" y="4" width="7" height="6" rx="1.5"/><rect x="8.5" y="14" width="7" height="6" rx="1.5"/><path d="M10 7h4"/><path d="M12 10v4"/></svg>',
      network:
        '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="6" cy="8" r="2.2"/><circle cx="18" cy="8" r="2.2"/><circle cx="12" cy="17" r="2.2"/><path d="M8 9.2l2.8 5.2"/><path d="M16 9.2l-2.8 5.2"/><path d="M8.2 8h7.6"/></svg>',
      compass:
        '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m15.8 8.2-2.3 6-6 2.3 2.3-6z"/><circle cx="12" cy="12" r="1.3"/></svg>',
      edit:
        '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h4l10-10-4-4L4 16z"/><path d="m13.5 5.5 4 4"/><path d="M4 20h16"/></svg>',
      layers:
        '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 4 8 4-8 4-8-4z"/><path d="m4 12 8 4 8-4"/><path d="m4 16 8 4 8-4"/></svg>',
      briefcase:
        '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="7" width="18" height="12" rx="2"/><path d="M9 7V5h6v2"/><path d="M3 12h18"/></svg>',
      funnel:
        '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16l-6 7v5l-4 2v-7z"/></svg>',
      target:
        '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="4"/><circle cx="12" cy="12" r="1.5"/></svg>',
      stack:
        '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="5" width="14" height="4" rx="1"/><rect x="5" y="10" width="14" height="4" rx="1"/><rect x="5" y="15" width="14" height="4" rx="1"/></svg>',
      route:
        '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="6" cy="18" r="2"/><circle cx="18" cy="6" r="2"/><path d="M8 18c3.5 0 3.5-5 7-5h1"/><path d="M16 6h-2c-4 0-4 5-8 5"/></svg>',
      dashboard:
        '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="7" height="7" rx="1.5"/><rect x="13" y="4" width="7" height="4" rx="1.5"/><rect x="13" y="10" width="7" height="10" rx="1.5"/><rect x="4" y="13" width="7" height="7" rx="1.5"/></svg>',
    };

    return (
      icons[key] ||
      '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"/></svg>'
    );
  }

  function renderChoice(option, stepName, isSuggested) {
    return (
      '<button type="button" class="sffc-cv-guided-pricing__choice' +
      (isSuggested ? " is-suggested" : "") +
      '" data-guided-choice data-guided-step-key="' +
      escapeHtml(stepName) +
      '" data-guided-value="' +
      escapeHtml(option.key) +
      '">' +
      '<span class="sffc-cv-guided-pricing__choice-icon">' +
      getIconMarkup(option.icon) +
      "</span>" +
      '<span class="sffc-cv-guided-pricing__choice-copy">' +
      "<strong>" +
      escapeHtml(option.label) +
      "</strong>" +
      '<em>' +
      escapeHtml(option.description || "") +
      "</em>" +
      "</span>" +
      '<span class="sffc-cv-guided-pricing__choice-arrow" aria-hidden="true">' +
      (isSuggested
        ? '<svg viewBox="0 0 16 16" focusable="false"><path d="M3.5 8.4 6.3 11 12.5 4.9" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>'
        : '↗') +
      "</span>" +
      "</button>"
    );
  }

  function getSuggestedValue(root, stepName, answers) {
    if (stepName === "help" && !answers.help) {
      return String(root.dataset.guidedSuggestedHelp || "").trim();
    }
    if (stepName === "constraint" && !answers.constraint) {
      return String(root.dataset.guidedSuggestedConstraint || "").trim();
    }
    if (stepName === "deliverable" && !answers.deliverable) {
      return String(root.dataset.guidedSuggestedDeliverable || "").trim();
    }
    if (stepName === "commitment" && !answers.commitment) {
      return String(root.dataset.guidedSuggestedCommitment || "").trim();
    }
    return "";
  }

  function getStepConfig(stepName, answers) {
    if (stepName === "help") {
      return wizard.help || {};
    }
    if (stepName === "constraint") {
      return wizard.constraint || {};
    }
    if (stepName === "deliverable") {
      var deliverableMap = wizard.deliverable || {};
      return (
        deliverableMap[answers.constraint] ||
        deliverableMap.no_interviews ||
        {}
      );
    }
    if (stepName === "commitment") {
      var commitmentMap = wizard.commitment || {};
      return commitmentMap[answers.help] || commitmentMap.land_interviews || {};
    }
    return {};
  }

  function setActiveStep(root, stepName) {
    var activeIndex = stepName === "lead" ? 1 : stepOrder.indexOf(stepName) + 1;

    $all(root, "[data-guided-step]").forEach(function (step) {
      var active = step.getAttribute("data-guided-step") === stepName;
      step.hidden = !active;
      step.classList.toggle("is-active", active);
    });

    $all(root, "[data-guided-progress]").forEach(function (dot) {
      var dotIndex = parseInt(dot.getAttribute("data-guided-progress") || "0", 10);
      dot.classList.toggle("is-active", dotIndex <= activeIndex);
    });
  }

  function renderStep(root, stepName, answers) {
    var configForStep = getStepConfig(stepName, answers);
    var eyebrowNode = $(root, '[data-guided-step-eyebrow="' + stepName + '"]');
    var titleNode = $(root, '[data-guided-step-title="' + stepName + '"]');
    var copyNode = $(root, '[data-guided-step-copy="' + stepName + '"]');
    var gridNode = $(root, '[data-guided-choice-grid="' + stepName + '"]');
    var options = Array.isArray(configForStep.options) ? configForStep.options.slice() : [];
    var firstName = String(root.dataset.guidedFirstName || "").trim();
    var resolvedTitle = configForStep.title || "";
    var suggestedValue = getSuggestedValue(root, stepName, answers);

    if (stepName === "help") {
      resolvedTitle = firstName
        ? personalizeTemplate(copy.helpTitle, firstName)
        : "How Can MENA Careers Help You Break Into Financial Services?";
    } else if (stepName === "constraint") {
      resolvedTitle = firstName
        ? personalizeTemplate(copy.constraintTitle, firstName)
        : "Where is the biggest friction right now?";
    }

    if (eyebrowNode) {
      eyebrowNode.textContent = configForStep.eyebrow || "";
    }
    if (titleNode) {
      titleNode.textContent = resolvedTitle;
    }
    if (copyNode) {
      copyNode.textContent = configForStep.description || "";
    }
    if (gridNode) {
      if (suggestedValue) {
        options.sort(function (left, right) {
          if (left && left.key === suggestedValue) {
            return -1;
          }
          if (right && right.key === suggestedValue) {
            return 1;
          }
          return 0;
        });
      }
      gridNode.innerHTML = options
        .map(function (option) {
          return renderChoice(option, stepName, suggestedValue && option && option.key === suggestedValue);
        })
        .join("");
    }
  }

  function findOptionForStep(stepName, key, answers) {
    var configForStep = getStepConfig(stepName, answers);
    var options = Array.isArray(configForStep.options) ? configForStep.options : [];
    var match = null;

    options.some(function (option) {
      if ((option && option.key) === key) {
        match = option;
        return true;
      }
      return false;
    });

    return match;
  }

  function resolveNextStepFromAnswers(answers) {
    if (!answers.help) {
      return "help";
    }
    if (!answers.constraint) {
      return "constraint";
    }
    if (!answers.deliverable) {
      return "deliverable";
    }
    if (!answers.commitment) {
      return "commitment";
    }
    return "recommendation";
  }

  function updateSummary(root, answers) {
    var stepsToRead = {
      need: {
        step: "help",
        key: answers.help,
        label: answers.helpLabel,
        selectors: ["[data-guided-answer-need]", "[data-guided-answer-need-secondary]"],
      },
      commitment: {
        step: "commitment",
        key: answers.commitment,
        label: answers.commitmentLabel,
        selectors: ["[data-guided-answer-commitment]", "[data-guided-answer-commitment-secondary]"],
      },
      constraint: {
        step: "constraint",
        key: answers.constraint,
        label: answers.constraintLabel,
        selectors: ["[data-guided-answer-constraint]", "[data-guided-answer-constraint-secondary]"],
      },
      deliverable: {
        step: "deliverable",
        key: answers.deliverable,
        label: answers.deliverableLabel,
        selectors: ["[data-guided-answer-deliverable]", "[data-guided-answer-deliverable-secondary]"],
      },
    };

    Object.keys(stepsToRead).forEach(function (summaryKey) {
      var meta = stepsToRead[summaryKey];
      var stepConfig = getStepConfig(meta.step, answers);
      var label = String(meta.label || "").trim() || "To be selected";

      if (!String(meta.label || "").trim()) {
        (stepConfig.options || []).some(function (option) {
          if (option.key === meta.key) {
            label = option.label;
            return true;
          }
          return false;
        });
      }

      meta.selectors.forEach(function (selector) {
        var node = $(root, selector);
        if (node) {
          node.textContent = label;
        }
      });
    });
  }

  function getRecommendationProblemText(answers) {
    var map = {
      land_interviews: "Land more interviews",
      attract_more_roles: "Attract more private equity roles",
      fix_cv_before_apply: "Fix your CV before you apply",
      get_recruiter_attention: "Get recruiter attention",
    };

    return (
      map[String(answers.help || "").trim()] ||
      String(answers.helpLabel || "").trim() ||
      "Not selected yet"
    );
  }

  function getRecommendationSolutionText(answers) {
    var map = {
      cv_rewrite: "Stronger CV positioning for the roles you want",
      application_pack: "Tailored applications for live roles",
      recruiter_route: "A stronger recruiter-facing route",
      full_support: "A full search strategy around your profile",
    };

    return (
      map[String(answers.deliverable || "").trim()] ||
      String(answers.deliverableLabel || "").trim() ||
      "Not selected yet"
    );
  }

  function getCurrentPlanCard(root, cycle) {
    var selector =
      '[data-guided-pricing-plans] [data-pricing-card]' +
      (cycle ? '[data-plan-cycle="' + cycle + '"]' : "");
    var cards = $all(root, selector).filter(function (card) {
      return !card.hidden;
    });

    return (
      cards.find(function (card) {
        return card.classList.contains("is-active");
      }) ||
      cards.find(function (card) {
        return card.classList.contains("is-guided-primary");
      }) ||
      cards[0] ||
      null
    );
  }

  function getBestPlanUnlock(answers, planCard) {
    if (!planCard) {
      return "Support matched to your current priorities";
    }

    var features = $all(planCard, "li")
      .map(function (item) {
        return String(item.textContent || "").trim();
      })
      .filter(Boolean);
    var featureScores = features.map(function (feature) {
      var lower = feature.toLowerCase();
      var score = 0;

      if (answers.help === "land_interviews" && /cv|review|scan|cover letter|interview|tailor/.test(lower)) {
        score += 4;
      }
      if (answers.help === "attract_more_roles" && /role|match|alert|career|search|recruiter/.test(lower)) {
        score += 4;
      }
      if (answers.help === "fix_cv_before_apply" && /cv|review|scan|cover letter|interview|tailor/.test(lower)) {
        score += 4;
      }
      if (answers.help === "get_recruiter_attention" && /recruit|outreach|contact|referral|match/.test(lower)) {
        score += 4;
      }
      if (answers.deliverable === "cv_rewrite" && /cv|review|scan/.test(lower)) {
        score += 5;
      }
      if (answers.deliverable === "application_pack" && /cover letter|interview|hiring guide|tailor/.test(lower)) {
        score += 5;
      }
      if (answers.deliverable === "recruiter_route" && /recruit|outreach|contact|referral|match/.test(lower)) {
        score += 5;
      }
      if (answers.deliverable === "full_support" && /unlimited|match|review|tailor|outreach|career/.test(lower)) {
        score += 3;
      }

      return { feature: feature, score: score };
    });

    featureScores.sort(function (left, right) {
      return right.score - left.score;
    });

    if (featureScores[0] && featureScores[0].score > 0) {
      return featureScores[0].feature;
    }

    if (features[0]) {
      return features[0];
    }

    return planCard.getAttribute("data-plan-copy") || "Support matched to your current priorities";
  }

  function buildPlanSummaryCopy(answers, planCard, unlockText) {
    var problem = getRecommendationProblemText(answers);
    var solution = getRecommendationSolutionText(answers);
    var planName = planCard ? (planCard.getAttribute("data-plan-name") || "this plan") : "this plan";
    var cleanUnlock = String(unlockText || "").trim();

    return (
      problem +
      " usually improves fastest with " +
      solution.toLowerCase() +
      ". " +
      planName +
      " unlocks " +
      (cleanUnlock || "the support matched to your current priorities") +
      "."
    );
  }

  function updateRecommendationSummaryStrip(root, answers, planCard) {
    var problemNode = $(root, "[data-guided-summary-problem]");
    var solutionNode = $(root, "[data-guided-summary-solution]");
    var unlockNode = $(root, "[data-guided-summary-unlock]");
    var problemText = getRecommendationProblemText(answers);
    var solutionText = getRecommendationSolutionText(answers);
    var unlockText = getBestPlanUnlock(answers, planCard);

    if (problemNode) {
      problemNode.textContent = problemText;
    }
    if (solutionNode) {
      solutionNode.textContent = solutionText;
    }
    if (unlockNode) {
      unlockNode.textContent = unlockText;
    }

    return {
      problem: problemText,
      solution: solutionText,
      unlock: unlockText,
    };
  }

  function clearDownstreamAnswers(answers, stepName) {
    if (stepName === "help") {
      answers.constraint = "";
      answers.constraintLabel = "";
      answers.deliverable = "";
      answers.deliverableLabel = "";
      answers.commitment = "";
      answers.commitmentLabel = "";
      return;
    }

    if (stepName === "constraint") {
      answers.deliverable = "";
      answers.deliverableLabel = "";
      answers.commitment = "";
      answers.commitmentLabel = "";
      return;
    }

    if (stepName === "deliverable") {
      answers.commitment = "";
      answers.commitmentLabel = "";
    }
  }

  function setIdentityInputs(root, context) {
    if (!context) {
      return;
    }

    var fullNameInput = $(root, "[data-guided-full-name]");
    var emailInput = $(root, "[data-guided-email]");

    if (fullNameInput && typeof context.fullName === "string" && context.fullName.trim()) {
      fullNameInput.value = context.fullName.trim();
    }
    if (emailInput && typeof context.email === "string" && context.email.trim()) {
      emailInput.value = context.email.trim();
    }
  }

  function syncOverlayLeadInputs(root, identity) {
    var overlayFullNameInput = $(root, "[data-guided-overlay-full-name]");
    var overlayEmailInput = $(root, "[data-guided-overlay-email]");
    var fullName = String((identity && identity.fullName) || "").trim();
    var email = String((identity && identity.email) || "").trim();

    if (overlayFullNameInput) {
      overlayFullNameInput.value = fullName;
    }

    if (overlayEmailInput) {
      overlayEmailInput.value = email;
    }
  }

  function hasRequiredIdentity(root) {
    var identity = getIdentity(root);
    return !!(identity.fullName && identity.email);
  }

  function getInsightState(root, answers, currentStep) {
    var commitmentChoice = normalizeChoiceSlug(answers.commitmentLabel);
    var deliverableTitle = normalizeChoiceTitle(
      answers.deliverableLabel,
      "this first move"
    );
    var suggestedHelp = String((root && root.dataset && root.dataset.guidedSuggestedHelp) || "").trim();

    var defaultState = {
      kicker: "How MENA Careers diagnoses the right route",
      title: "Choose the main problem to see what it usually signals.",
      copy: "MENA Careers will use your selections to show what tends to break private equity applications first, so the path starts with diagnosis rather than guesswork.",
      chart: {
        rows: [
          { label: "Interview conversion", value: 76, tone: "blue", note: "Shortlist problems usually start before the interview stage" },
          { label: "Recruiter visibility", value: 67, tone: "gold", note: "Visibility improves when the market can classify you quickly" },
          { label: "Evidence clarity", value: 84, tone: "green", note: "Clear proof usually matters more than extra effort" },
        ],
        takeaway: "The strongest route starts with the layer that is actually failing first.",
      },
    };

    var leadState = {
      kicker: "Before MENA Careers tailors the route",
      title: "Tell MENA Careers who this plan is for.",
      copy: "Your name and email keep the route, recommendation, and checkout personalized from the start instead of dropping you into a generic upgrade flow.",
      chart: {
        rows: [
          { label: "Personalized route", value: 88, tone: "green", note: "Your guidance will read like it was built for you" },
          { label: "Prefilled checkout", value: 76, tone: "blue", note: "The membership step stays faster and cleaner later" },
          { label: "Relevant follow-up", value: 81, tone: "gold", note: "MENA Careers can keep the same context across the whole flow" },
        ],
        takeaway: "The strongest guidance feels personal before pricing ever appears.",
      },
    };

    var helpMap = {
      land_interviews: {
        kicker: "What low interview rates usually mean",
        title: "If you're applying regularly for private equity roles but not getting interviews, the issue is usually:",
        copy: "MENA Careers will help identify which of these is reducing your shortlist rate.",
        chart: {
          rows: [
            { label: "Positioning mismatch", value: 82, tone: "blue", note: "The profile reads adjacent, but not targeted enough" },
            { label: "Weak evidence on the CV", value: 89, tone: "green", note: "The proof is too vague or buried too low" },
            { label: "Poor role targeting", value: 68, tone: "gold", note: "The role mix may be broader than your strongest fit" },
          ],
          takeaway: "It is rarely a lack of applications.",
        },
      },
      attract_more_roles: {
        kicker: "Why recruiters don't reach out",
        title: "Recruiters search for specific signals:",
        copy: "MENA Careers will identify which signals are currently missing.",
        chart: {
          rows: [
            { label: "Relevant job titles", value: 88, tone: "green", note: "Titles quickly shape whether your profile enters the right search" },
            { label: "Recognisable employers or market signals", value: 72, tone: "gold", note: "Brand names help, but they do not replace proof" },
            { label: "Clear progression", value: 76, tone: "blue", note: "Recruiters want an obvious investing story, not mixed signals" },
            { label: "Keywords matching their searches", value: 84, tone: "blue", note: "Small wording gaps can suppress strong profiles" },
          ],
          takeaway: "Small profile changes can dramatically increase inbound recruiter interest.",
        },
      },
      fix_cv_before_apply: {
        kicker: "What hiring managers actually scan first",
        title: "Most finance hiring managers spend less than a minute reviewing a CV.",
        copy: "They typically look for:",
        chart: {
          rows: [
            { label: "Relevant transaction, project, or market work", value: 86, tone: "green", note: "The first screen looks for direct fit, not broad promise" },
            { label: "Quantified achievements", value: 83, tone: "blue", note: "Numbers make impact easier to trust quickly" },
            { label: "Brand names", value: 67, tone: "gold", note: "Recognisable firms or universities help, but they do not replace evidence" },
            { label: "Career progression", value: 74, tone: "blue", note: "A coherent career story reduces doubt fast in investing markets" },
          ],
          takeaway: "Formatting matters far less than most candidates think. MENA Careers will identify what is weakening your story.",
        },
      },
      get_recruiter_attention: {
        kicker: "What gets recruiter outreach ignored",
        title: "Even strong candidates lose recruiter attention when the private equity path lacks:",
        copy: "MENA Careers will identify which part of the recruiter-facing route is currently costing you replies.",
        chart: {
          rows: [
            { label: "Targeting the right recruiters", value: 86, tone: "green", note: "Wrong contacts waste even strong profiles" },
            { label: "Sharper recruiter-facing messaging", value: 78, tone: "blue", note: "Generic outreach rarely earns a serious response" },
            { label: "Credible profile positioning", value: 82, tone: "gold", note: "The profile has to read clearly before the message can land" },
          ],
          takeaway: "Recruiter traction usually improves when targeting, message quality, and credibility move together.",
        },
      },
    };

    var commitmentMap = {
      "one-strong-fix": {
        kicker: "What One strong fix usually means",
        title: "A one-off reset makes sense when the core problem is quality, not process.",
        copy: "This usually suits candidates who know which roles they want, but need the CV to read more convincingly before sending another application.",
        chart: {
          rows: [
            { label: "Sharper CV narrative", value: 87, tone: "green", note: "The main gain usually comes from clearer proof and positioning" },
            { label: "More applications", value: 34, tone: "gold", note: "Volume rarely fixes a weak first screen" },
            { label: "Repeatable system", value: 52, tone: "blue", note: "Less urgent if the problem is concentrated in one asset" },
          ],
          takeaway: "A one-off fix works best when the shortlist problem is concentrated in the CV itself.",
        },
      },
      "tailor-every-application": {
        kicker: "What Tailor every application usually means",
        title: "When the same role-fit issue repeats, one generic CV usually underperforms.",
        copy: "Candidates tend to get stronger response rates when each application reflects the exact mandate, not just a broad background that leaves too much for the market to interpret.",
        chart: {
          rows: [
            { label: "Role-specific evidence", value: 88, tone: "green", note: "Each brief usually needs slightly different proof up front" },
            { label: "Generic application reuse", value: 41, tone: "gold", note: "Often where conversion starts to fall" },
            { label: "Repeatable application discipline", value: 79, tone: "blue", note: "This matters when you are applying across multiple live roles" },
          ],
          takeaway: "Tailoring usually matters most when the candidate is directionally strong but not converting consistently.",
        },
      },
      "build-a-recruiter-route": {
        kicker: "What Build a recruiter route usually means",
        title: "A recruiter route usually matters when opportunities exist, but access and follow-through are weak.",
        copy: "This often means the candidate is credible enough for the roles they want, but the market is not seeing a disciplined outreach path yet.",
        chart: {
          rows: [
            { label: "Clear target angle", value: 84, tone: "green", note: "The recruiter should know what to place you into quickly" },
            { label: "Message quality", value: 77, tone: "blue", note: "Outreach needs a sharper proof-based opening" },
            { label: "Cold-volume outreach", value: 39, tone: "gold", note: "More messages rarely help if the angle is weak" },
          ],
          takeaway: "A recruiter route is strongest when targeting, message, and profile all reinforce each other.",
        },
      },
      "refocus-my-target-roles": {
        kicker: "What Refocus my target roles usually means",
        title: "Interview conversion often improves when the role mix gets narrower and more intentional.",
        copy: "This usually means the background has genuine strengths, but too many adjacent roles are being treated as equally viable when they are not.",
        chart: {
          rows: [
            { label: "Role-fit discipline", value: 88, tone: "green", note: "A tighter role mix usually lifts conversion first" },
            { label: "Evidence concentration", value: 76, tone: "blue", note: "The strongest proof should align to fewer, better-fit mandates" },
            { label: "Broad application spread", value: 42, tone: "gold", note: "Applying too widely often dilutes the signal" },
          ],
          takeaway: "A better target list often fixes more than sending stronger materials to the wrong roles.",
        },
      },
      "reposition-me-once": {
        kicker: "What Reposition me once usually means",
        title: "A one-time repositioning is most useful when the market is not reading the profile the way it should.",
        copy: "This usually means the underlying experience is credible, but the titles, story, employer signals, or investment positioning are not doing enough work.",
        chart: {
          rows: [
            { label: "Target role clarity", value: 84, tone: "green", note: "The market needs a clearer signal about what you are" },
            { label: "Employer and title signalling", value: 78, tone: "blue", note: "These shape inbound quality faster than most candidates expect" },
            { label: "Weekly maintenance", value: 49, tone: "gold", note: "Less urgent if the issue is mostly narrative" },
          ],
          takeaway: "One repositioning pass works best when the story is the problem, not the market itself.",
        },
      },
      "keep-me-visible-weekly": {
        kicker: "What Keep me visible weekly usually means",
        title: "When better roles are not coming to you, the issue is often consistency rather than one isolated gap.",
        copy: "Recruiter interest tends to improve when the same signals show up clearly across searches, applications, and market-facing materials over time.",
        chart: {
          rows: [
            { label: "Consistent market signal", value: 86, tone: "green", note: "Visibility rises when the message stays coherent across touchpoints" },
            { label: "Search alignment", value: 79, tone: "blue", note: "Weekly alignment matters more in recruiter-led markets" },
            { label: "One-off edits", value: 43, tone: "gold", note: "Useful, but often not enough to sustain inbound" },
          ],
          takeaway: "Ongoing visibility matters most when the candidate wants stronger inbound rather than one-off application help.",
        },
      },
      "target-a-new-market": {
        kicker: "What Target a new market usually means",
        title: "Market moves usually fail when the experience does not translate clearly enough for the new audience.",
        copy: "The challenge is rarely ambition. It is proving that your background travels well across a new city, sector, or hiring pool.",
        chart: {
          rows: [
            { label: "Transferable signals", value: 87, tone: "green", note: "The new market needs immediate evidence that the profile travels" },
            { label: "Local market framing", value: 78, tone: "blue", note: "The move has to sound deliberate rather than opportunistic" },
            { label: "Application volume", value: 36, tone: "gold", note: "Usually not the lever that fixes cross-market credibility" },
          ],
          takeaway: "Breaking into a new market works best when the profile is reframed for how that market actually screens.",
        },
      },
      "sharpen-my-profile-for-search": {
        kicker: "What Sharpen my profile for search usually means",
        title: "Inbound improves when recruiter search signals become clearer before anything else changes.",
        copy: "This usually means the profile has the right raw ingredients, but titles, keywords, and signal ordering are still too weak for search-led discovery.",
        chart: {
          rows: [
            { label: "Search keyword alignment", value: 87, tone: "green", note: "The right search terms need to appear earlier and more cleanly" },
            { label: "Role-title clarity", value: 81, tone: "blue", note: "Recruiters classify profiles quickly from titles and positioning" },
            { label: "Passive visibility", value: 46, tone: "gold", note: "Visibility rises once the profile is easier to classify" },
          ],
          takeaway: "Search visibility improves most when the profile becomes easier for recruiters to understand at a glance.",
        },
      },
      "one-polished-rewrite": {
        kicker: "What One polished rewrite usually means",
        title: "Sometimes the main issue is not experience quality but how the story is being presented.",
        copy: "A single rewrite is often enough when the profile already has relevant experience, but the wording, structure, or proof order is weakening it.",
        chart: {
          rows: [
            { label: "Narrative clarity", value: 86, tone: "green", note: "The biggest gain usually comes from a clearer first read" },
            { label: "Quantified proof", value: 79, tone: "blue", note: "Impact is stronger when numbers appear earlier" },
            { label: "Repeated tailoring", value: 47, tone: "gold", note: "Less urgent if the immediate issue is one weak base CV" },
          ],
          takeaway: "A polished rewrite matters most when the base CV is the thing holding everything else back.",
        },
      },
      "tailor-every-brief": {
        kicker: "What Tailor every brief usually means",
        title: "A strong base CV still misses shortlists when it does not mirror the live brief closely enough.",
        copy: "This usually affects candidates who are broadly credible, but not making the fit obvious role by role.",
        chart: {
          rows: [
            { label: "Mandate-specific language", value: 88, tone: "green", note: "The brief often needs to be reflected more directly" },
            { label: "Reusable base profile", value: 61, tone: "blue", note: "Still useful, but not enough for every role" },
            { label: "One-off polish", value: 42, tone: "gold", note: "Rarely enough when multiple live briefs matter" },
          ],
          takeaway: "Tailoring matters most when the candidate is viable across several roles but not converting evenly.",
        },
      },
      "build-one-application-pack": {
        kicker: "What Build one application pack usually means",
        title: "When one live role matters, depth usually beats breadth.",
        copy: "A strong application pack helps candidates go deeper on one mandate instead of sending another generic application that blends in.",
        chart: {
          rows: [
            { label: "CV fit to the brief", value: 85, tone: "green", note: "Usually the anchor of the pack" },
            { label: "Cover-letter angle", value: 73, tone: "blue", note: "Most useful when it sharpens the narrative rather than repeats the CV" },
            { label: "Breadth of applications", value: 38, tone: "gold", note: "Less valuable if one role is the priority" },
          ],
          takeaway: "One strong pack usually outperforms several fast applications when the mandate is a serious target.",
        },
      },
      "build-a-reusable-application-base": {
        kicker: "What Build a reusable application base usually means",
        title: "A reusable base matters when the candidate wants speed without falling back into generic applications.",
        copy: "This usually suits candidates who apply across several related roles and need a stronger core CV and cover-letter base before tailoring each brief.",
        chart: {
          rows: [
            { label: "Base CV strength", value: 86, tone: "green", note: "A stronger core asset lifts every later application" },
            { label: "Adaptation speed", value: 78, tone: "blue", note: "A reusable base makes tailoring faster and more consistent" },
            { label: "One-off pack dependence", value: 49, tone: "gold", note: "Useful for one role, but less scalable across a full search" },
          ],
          takeaway: "A better base usually makes every later application easier to tailor well.",
        },
      },
      "find-the-right-recruiters-to-target": {
        kicker: "What Find the right recruiters to target usually means",
        title: "Outreach quality improves dramatically when the contact list is right before the message is even written.",
        copy: "Many candidates waste strong profiles on the wrong recruiters, teams, or firms and then assume the market is unreceptive.",
        chart: {
          rows: [
            { label: "Targeting quality", value: 88, tone: "green", note: "The right contact list changes the odds before outreach begins" },
            { label: "Message quality", value: 71, tone: "blue", note: "Important, but second to targeting accuracy" },
            { label: "Outreach volume", value: 37, tone: "gold", note: "Sending more messages rarely fixes the wrong list" },
          ],
          takeaway: "The best outreach usually starts with who to contact, not with how many people to contact.",
        },
      },
      "build-a-stronger-outreach-message": {
        kicker: "What Build a stronger outreach message usually means",
        title: "Recruiter messages usually fail because the fit is implied instead of stated clearly.",
        copy: "Candidates often know they are relevant, but the outreach does not surface the right deal, leadership, execution, or commercially relevant proof quickly enough for a recruiter to act on it.",
        chart: {
          rows: [
            { label: "Clear fit statement", value: 84, tone: "green", note: "The recruiter should know the angle in seconds" },
            { label: "Proof selection", value: 78, tone: "blue", note: "The wrong examples weaken even a good background" },
            { label: "Message length", value: 46, tone: "gold", note: "Length is less important than clarity and proof order" },
          ],
          takeaway: "A good outreach message removes guesswork rather than adding more background.",
        },
      },
      "get-help-securing-referrals": {
        kicker: "What Get help securing referrals usually means",
        title: "A referral is strongest when the candidate already sounds easy to advocate for.",
        copy: "The challenge is not just getting introduced. It is making the profile and ask clear enough that someone credible wants to back it.",
        chart: {
          rows: [
            { label: "Advocacy readiness", value: 83, tone: "green", note: "The profile has to be easy to endorse" },
            { label: "Referral target quality", value: 77, tone: "blue", note: "The person making the intro matters as much as the intro itself" },
            { label: "Cold outreach dependence", value: 44, tone: "gold", note: "Referrals usually work best when they are not the only angle" },
          ],
          takeaway: "Referrals work best when the ask is credible, targeted, and easy to support.",
        },
      },
      "reset-my-recruiter-facing-profile": {
        kicker: "What Reset my recruiter-facing profile usually means",
        title: "Recruiters decide quickly whether they know how to place a profile.",
        copy: "If that answer is unclear, even a strong background can get ignored because the candidate sounds harder to place than they really are.",
        chart: {
          rows: [
            { label: "Target role clarity", value: 86, tone: "green", note: "Recruiters need an obvious lane for the profile" },
            { label: "Employer and background framing", value: 79, tone: "blue", note: "The story should sound easier to sell forward" },
            { label: "Generic finance language", value: 41, tone: "gold", note: "Broad language usually weakens recruiter confidence" },
          ],
          takeaway: "A recruiter-facing profile reset works best when the issue is how the candidate is being interpreted, not the underlying quality.",
        },
      },
    };

    var constraintMap = {
      no_interviews: {
        kicker: "What I apply but rarely get interviews usually means",
        title: "Low response rates usually mean one layer is quietly breaking the application.",
        copy: "This usually means the roles are plausible, but something in the profile or evidence is still weakening the first screen for private equity roles.",
        chart: {
          rows: [
            { label: "Weak evidence density", value: 88, tone: "green", note: "The proof is there, but not visible enough" },
            { label: "Generic positioning", value: 81, tone: "blue", note: "The profile reads too broad for the target role" },
            { label: "Application volume", value: 34, tone: "gold", note: "Rarely the real issue on its own" },
          ],
          takeaway: "Most candidates try harder before they diagnose why the shortlist is stalling.",
        },
      },
      not_targeted: {
        kicker: "What My profile reads too generic usually means",
        title: "Broad profiles force recruiters to do too much interpretation.",
        copy: "This usually happens when the profile is broad enough to sound capable, but not specific enough to sound like the obvious fit.",
        chart: {
          rows: [
            { label: "Specific role signal", value: 84, tone: "green", note: "Makes the target function obvious faster" },
            { label: "Clear progression", value: 76, tone: "blue", note: "Reduces ambiguity in how the profile is read" },
            { label: "Broad market language", value: 69, tone: "gold", note: "Often attracts weaker-fit opportunities" },
          ],
          takeaway: "Specificity usually beats polish on its own.",
        },
      },
      no_materials: {
        kicker: "What I do not have tailored materials ready usually means",
        title: "Strong intent still underperforms when the materials are not role-specific.",
        copy: "This usually means you know the roles you want, but are not yet showing up with the seniority, transaction, leadership, or execution proof needed to apply confidently.",
        chart: {
          rows: [
            { label: "CV specificity", value: 86, tone: "green", note: "The base layer recruiters and managers read first" },
            { label: "Tailored supporting materials", value: 78, tone: "blue", note: "Useful once the CV reads credibly" },
            { label: "Speed of application", value: 41, tone: "gold", note: "Urgency rarely beats a cleaner pack" },
          ],
          takeaway: "Prepared materials beat urgency almost every time.",
        },
      },
      market_move: {
        kicker: "What I am trying to break into a new market usually means",
        title: "Cross-market or cross-role pivots usually fail on credibility, not ambition.",
        copy: "This usually means the background has value, but the new audience is not yet seeing why the experience transfers cleanly.",
        chart: {
          rows: [
            { label: "Transferable proof", value: 87, tone: "green", note: "The new market needs clean evidence fast" },
            { label: "Local market framing", value: 76, tone: "blue", note: "Makes the move feel intentional" },
            { label: "Ambition alone", value: 38, tone: "gold", note: "Rarely enough to carry the switch" },
          ],
          takeaway: "Credibility has to travel faster than the story.",
        },
      },
    };

    var deliverableMap = {
      cv_rewrite: {
        kicker: "Why CV rewrite should come first",
        title: "A recruiter-facing CV reset is usually the highest-leverage first move here.",
        copy: "This is usually the right first move when the underlying experience is relevant, but the CV is not presenting market-ready proof strongly enough for recruiters hiring across the private equity market.",
        chart: {
          rows: [
            { label: "Story clarity", value: 86, tone: "green", note: "Lead with the role fit faster" },
            { label: "Impact proof", value: 81, tone: "blue", note: "Bring stronger evidence higher in the CV" },
            { label: "Formatting polish", value: 43, tone: "gold", note: "Useful, but rarely the main unlock" },
          ],
          takeaway: "The CV should do more of the convincing before the recruiter has to infer anything.",
        },
      },
      application_pack: {
        kicker: "Why Application pack should come first",
        title: "One strong application pack usually beats several rushed applications.",
        copy: "This is usually the right first move when one live role matters enough to justify deeper, role-specific preparation for a serious mid-senior finance opportunity.",
        chart: {
          rows: [
            { label: "CV tailoring", value: 85, tone: "green", note: "The base layer of the pack" },
            { label: "Cover-letter angle", value: 72, tone: "blue", note: "Useful once the CV is credible" },
            { label: "Interview prep", value: 66, tone: "gold", note: "Best built around the live brief" },
          ],
          takeaway: "A cleaner pack usually changes response rates faster than applying to more roles.",
        },
      },
      recruiter_route: {
        kicker: "Why Recruiter route should come first",
        title: "The recruiter route needs the right contacts, the right angle, and the right proof order.",
        copy: "This is usually the right first move when access, outreach, or recruiter confidence is the bottleneck rather than the CV alone.",
        chart: {
          rows: [
            { label: "Who to target", value: 84, tone: "green", note: "Targeting quality compounds the rest" },
            { label: "What to say", value: 74, tone: "blue", note: "Messaging matters once the contact is right" },
            { label: "Referral angle", value: 67, tone: "gold", note: "Works best after targeting and credibility are clear" },
          ],
          takeaway: "The route matters before the message can work.",
        },
      },
      full_support: {
        kicker: "Why Full support layer should come first",
        title: "A broader support layer is usually right when the problem is repeating across roles.",
        copy: "This is usually the right first move when the problem is not isolated to one asset and keeps repeating across the search process.",
        chart: {
          rows: [
            { label: "Profile positioning", value: 77, tone: "blue", note: "Needs to read more intentionally" },
            { label: "Application materials", value: 81, tone: "green", note: "Need stronger role-specific proof" },
            { label: "Recruiter route", value: 72, tone: "gold", note: "Needs more disciplined follow-through" },
          ],
          takeaway: "When all three layers need work, one-off fixes usually underperform.",
        },
      },
    };

    var recommendationState = {
      kicker: "What your route is pointing to",
      title: "This route usually works best when the underlying problem is diagnosed before you push harder.",
      copy: "Your selections suggest the biggest gain will come from fixing the right layer first, not from simply sending more applications into the market without a sharper positioning layer.",
      chart: {
        rows: [
          { label: answers.helpLabel || "Primary problem", value: 86, tone: "green", note: "The top-level finance search problem you want MENA Careers to solve" },
          { label: answers.constraintLabel || "Biggest friction", value: 78, tone: "blue", note: "Where momentum is most likely getting lost today" },
          { label: answers.deliverableLabel || "First move", value: 82, tone: "gold", note: "The first asset or route most likely to change the outcome" },
        ],
        takeaway: "The best next move is usually the one that fixes the weakest hiring signal first.",
      },
    };

    if (currentStep === "lead") {
      return leadState;
    }

    if (currentStep === "help" && !answers.help && suggestedHelp && helpMap[suggestedHelp]) {
      return helpMap[suggestedHelp];
    }

    if (currentStep === "commitment") {
      if (!answers.commitmentLabel) {
        return (
          deliverableMap[answers.deliverable] ||
          constraintMap[answers.constraint] ||
          helpMap[answers.help] ||
          defaultState
        );
      }
      return (
        commitmentMap[commitmentChoice] ||
        deliverableMap[answers.deliverable] ||
        constraintMap[answers.constraint] ||
        helpMap[answers.help] ||
        defaultState
      );
    }

    if (currentStep === "constraint") {
      if (!answers.constraintLabel) {
        return helpMap[answers.help] || defaultState;
      }
      return (
        constraintMap[answers.constraint] ||
        helpMap[answers.help] ||
        defaultState
      );
    }

    if (currentStep === "deliverable") {
      if (!answers.deliverableLabel) {
        return (
          constraintMap[answers.constraint] ||
          helpMap[answers.help] ||
          defaultState
        );
      }
      var deliverableState =
        deliverableMap[answers.deliverable] ||
        constraintMap[answers.constraint] ||
        helpMap[answers.help] ||
        defaultState;

      deliverableState = Object.assign({}, deliverableState);
      deliverableState.kicker = "Why " + deliverableTitle + " should come first";
      return deliverableState;
    }

    if (currentStep === "recommendation") {
      return recommendationState;
    }

    return helpMap[answers.help] || defaultState;
  }

  function renderInsightChart(config) {
    var chart = (config && config.chart) || {};
    var rows = Array.isArray(chart.rows) ? chart.rows : [];
    if (!rows.length) {
      return "";
    }

    return (
      '<div class="sffc-cv-guided-pricing__aside-chart">' +
      rows
        .map(function (row) {
          var tone = String(row.tone || "blue");
          var value = Math.max(12, Math.min(100, parseInt(row.value || 0, 10)));
          return (
            '<div class="sffc-cv-guided-pricing__aside-chart-row">' +
            '<div class="sffc-cv-guided-pricing__aside-chart-head">' +
            "<strong>" + escapeHtml(row.label || "") + "</strong>" +
            '<span>' + escapeHtml(row.note || "") + "</span>" +
            "</div>" +
            '<div class="sffc-cv-guided-pricing__aside-chart-track">' +
            '<i class="is-' + escapeHtml(tone) + '" style="width:' + String(value) + '%"></i>' +
            "</div>" +
            "</div>"
          );
        })
        .join("") +
      (chart.takeaway
        ? '<p class="sffc-cv-guided-pricing__aside-chart-takeaway">' + escapeHtml(chart.takeaway) + "</p>"
        : "") +
      "</div>"
    );
  }

  function updateInsights(root, answers, currentStep) {
    var insights = getInsightState(root, answers, currentStep || root.dataset.guidedCurrentStep || "help");

    var kicker = $(root, "[data-guided-insight-kicker]");
    var title = $(root, "[data-guided-insight-title]");
    var copyNode = $(root, "[data-guided-insight-copy]");
    var chartNode = $(root, "[data-guided-insight-chart]");

    if (kicker) {
      kicker.textContent = insights.kicker || "";
    }
    if (title) {
      title.textContent = insights.title || "";
    }
    if (copyNode) {
      copyNode.textContent = insights.copy || "";
    }
    if (chartNode) {
      chartNode.innerHTML = renderInsightChart(insights);
    }
  }

  function getSignalScore(signals, patterns) {
    var score = 0;
    patterns.forEach(function (pattern) {
      if (pattern.test(signals)) {
        score += 1;
      }
    });
    return score;
  }

  function rankPlans(root, answers) {
    var cards = $all(root, "[data-guided-pricing-plans] [data-pricing-card]");
    var visibleByCycle = {};
    var bestCard = null;
    var bestScore = -1;

    var constraintPatterns = {
      no_interviews: [/interview/i, /\bats\b/i, /\bcv\b/i, /recruit/i, /outreach/i],
      not_targeted: [/role/i, /search/i, /position/i, /career report/i, /matching/i],
      no_materials: [/cover letter/i, /application pack/i, /hiring guide/i, /interview questions/i, /template/i],
      market_move: [/recruit/i, /outreach/i, /search/i, /career report/i, /live roles/i],
    };

    var deliverablePatterns = {
      cv_rewrite: [/\bcv\b/i, /ats/i, /review/i, /rewrite/i, /template/i],
      application_pack: [/application pack/i, /cover letter/i, /interview/i, /hiring guide/i],
      recruiter_route: [/recruit/i, /outreach/i, /network/i, /referral/i],
      full_support: [/career report/i, /live roles/i, /search/i, /recruit/i, /outreach/i, /membership/i],
    };

    cards.forEach(function (card) {
      var helpTags = parseTagList(card.getAttribute("data-guided-help-needs"));
      var commitmentTags = parseTagList(card.getAttribute("data-guided-commitments"));
      var signals = (card.getAttribute("data-guided-signals") || "").toLowerCase();
      var score = 0;

      if (helpTags.indexOf(answers.help) !== -1) {
        score += 6;
      }
      if (commitmentTags.indexOf(answers.commitment) !== -1) {
        score += 4;
      }
      if ((card.getAttribute("data-guided-featured") || "") === "1") {
        score += 1;
      }

      score += getSignalScore(signals, constraintPatterns[answers.constraint] || []) * 1.5;
      score += getSignalScore(signals, deliverablePatterns[answers.deliverable] || []) * 2;

      card.dataset.guidedScore = String(score);
      card.hidden = score <= 0;
      card.classList.remove("is-guided-primary");
      card.style.order = String(score > 0 ? 100 - score : 999);

      if (!card.hidden) {
        var cycle = card.getAttribute("data-plan-cycle") || "monthly";
        visibleByCycle[cycle] = (visibleByCycle[cycle] || 0) + 1;

        if (score > bestScore) {
          bestScore = score;
          bestCard = card;
        }
      }
    });

    if (!bestCard) {
      cards.forEach(function (card, index) {
        card.hidden = false;
        card.dataset.guidedScore = "0";
        card.style.order = String(index);
      });
      bestCard = cards[0] || null;
      bestScore = 0;
    }

    if (bestCard) {
      bestCard.classList.add("is-guided-primary");
    }

    return {
      bestCard: bestCard,
      bestScore: bestScore,
      visibleByCycle: visibleByCycle,
    };
  }

  function syncCycleVisibility(root, visibleByCycle) {
    var toggleWrap = $(root, ".sffc-cv-match-pricing__billing");
    var firstVisibleCycle = "";

    $all(root, "[data-pricing-cycle-toggle]").forEach(function (toggle) {
      var cycle = toggle.getAttribute("data-pricing-cycle-toggle") || "";
      var visible = !!visibleByCycle[cycle];

      toggle.hidden = !visible;
      if (visible && !firstVisibleCycle) {
        firstVisibleCycle = cycle;
      }
    });

    if (toggleWrap) {
      toggleWrap.hidden = !$all(
        toggleWrap,
        "[data-pricing-cycle-toggle]"
      ).some(function (toggle) {
        return !toggle.hidden;
      });
    }

    $all(root, "[data-pricing-grid]").forEach(function (grid) {
      var cycle = grid.getAttribute("data-pricing-grid") || "";
      var hasVisible = !!visibleByCycle[cycle];
      grid.dataset.guidedHasVisible = hasVisible ? "1" : "0";
    });

    return firstVisibleCycle;
  }

  function setActivePlanCard(root, card, answers) {
    var checkout = $(root, "[data-pricing-checkout]");
    var titleNode = $(root, "[data-pricing-summary-title]");
    var copyNode = $(root, "[data-pricing-summary-copy]");
    var priceNode = $(root, "[data-pricing-summary-price]");
    var recommendationCopyNode = $(root, "[data-guided-recommendation-copy]");
    var planHeadlineNode = $(root, "[data-guided-plan-headline]");
    var planSubheadlineNode = $(root, "[data-guided-plan-subheadline]");
    var helpNeed = (config.helpNeeds && config.helpNeeds[answers.help]) || {};
    var summaryState;

    $all(root, "[data-guided-pricing-plans] [data-pricing-card]").forEach(function (planCard) {
      planCard.classList.toggle("is-active", planCard === card);
    });

    $all(root, "[data-pricing-shell]").forEach(function (shell) {
      shell.hidden = !card || shell.getAttribute("data-pricing-shell") !== card.getAttribute("data-plan-slug");
    });

    if (checkout) {
      checkout.hidden = !card;
    }

    if (!card) {
      return;
    }

    summaryState = updateRecommendationSummaryStrip(root, answers, card);

    if (planHeadlineNode) {
      planHeadlineNode.textContent = "Here is the best next move for you.";
    }
    if (planSubheadlineNode) {
      planSubheadlineNode.textContent =
        "MENA Careers has enough context to recommend the support level most likely to help you land private equity roles faster.";
    }
    if (recommendationCopyNode) {
      recommendationCopyNode.textContent =
        helpNeed.final_copy || buildPlanSummaryCopy(answers, card, summaryState.unlock);
    }
    if (titleNode) {
      titleNode.textContent = card.getAttribute("data-plan-name") || "Select a plan";
    }
    if (copyNode) {
      copyNode.textContent =
        buildPlanSummaryCopy(answers, card, summaryState.unlock) ||
        card.getAttribute("data-plan-copy") ||
        "";
    }
    if (priceNode) {
      priceNode.textContent = card.getAttribute("data-plan-price") || "";
    }
  }

  function activateRecommendedPlan(root, answers) {
    var ranked = rankPlans(root, answers);
    var targetCard = ranked.bestCard;
    var targetCycle = targetCard
      ? (targetCard.getAttribute("data-plan-cycle") || "")
      : (root.getAttribute("data-default-cycle") || "");
    var firstVisibleCycle = syncCycleVisibility(root, ranked.visibleByCycle);

    if (!targetCycle || !ranked.visibleByCycle[targetCycle]) {
      targetCycle = firstVisibleCycle || root.getAttribute("data-default-cycle") || "monthly";
    }

    $all(root, "[data-pricing-cycle-toggle]").forEach(function (toggle) {
      var cycle = toggle.getAttribute("data-pricing-cycle-toggle") || "";
      var active = cycle === targetCycle;
      toggle.classList.toggle("is-active", active);
      toggle.setAttribute("aria-selected", active ? "true" : "false");
    });

    $all(root, "[data-pricing-grid]").forEach(function (grid) {
      var cycle = grid.getAttribute("data-pricing-grid") || "";
      var active = cycle === targetCycle;
      grid.hidden = !active || grid.dataset.guidedHasVisible === "0";
      grid.classList.toggle("is-active", active && !grid.hidden);
    });

    if (!targetCard || targetCard.hidden || targetCard.getAttribute("data-plan-cycle") !== targetCycle) {
      targetCard = getCurrentPlanCard(root, targetCycle);
    }

    setActivePlanCard(root, targetCard, answers);
  }

  function resetPlanSelection(root, initialState) {
    $all(root, "[data-guided-pricing-plans] [data-pricing-card]").forEach(function (card) {
      card.hidden = false;
      card.classList.remove("is-guided-primary");
      card.classList.toggle(
        "is-active",
        (card.getAttribute("data-plan-slug") || "") === initialState.defaultPlanSlug
      );
      card.style.order = "";
    });

    $all(root, "[data-pricing-cycle-toggle]").forEach(function (toggle) {
      var cycle = toggle.getAttribute("data-pricing-cycle-toggle") || "";
      var active = cycle === initialState.defaultCycle;
      toggle.hidden = false;
      toggle.classList.toggle("is-active", active);
      toggle.setAttribute("aria-selected", active ? "true" : "false");
    });

    $all(root, "[data-pricing-grid]").forEach(function (grid) {
      var cycle = grid.getAttribute("data-pricing-grid") || "";
      var active = cycle === initialState.defaultCycle;
      grid.hidden = !active;
      grid.classList.toggle("is-active", active);
      delete grid.dataset.guidedHasVisible;
    });

    $all(root, "[data-pricing-shell]").forEach(function (shell) {
      shell.hidden = (shell.getAttribute("data-pricing-shell") || "") !== initialState.defaultPlanSlug;
    });
  }

  function resetWizard(root, answers, initialState) {
    answers.help = "";
    answers.helpLabel = "";
    answers.commitment = "";
    answers.commitmentLabel = "";
    answers.constraint = "";
    answers.constraintLabel = "";
    answers.deliverable = "";
    answers.deliverableLabel = "";

    renderStep(root, "help", answers);
    updateSummary(root, answers);
    updateInsights(root, answers, "help");
    syncOverlayLeadInputs(root, getIdentity(root));
    resetPlanSelection(root, initialState);

    var recommendationCopyNode = $(root, "[data-guided-recommendation-copy]");
    var planHeadlineNode = $(root, "[data-guided-plan-headline]");
    var planSubheadlineNode = $(root, "[data-guided-plan-subheadline]");
    var checkoutTitleNode = $(root, "[data-pricing-summary-title]");
    var checkoutCopyNode = $(root, "[data-pricing-summary-copy]");
    var checkoutPriceNode = $(root, "[data-pricing-summary-price]");
    var checkoutNode = $(root, "[data-pricing-checkout]");

    if (recommendationCopyNode) {
      recommendationCopyNode.textContent = initialState.recommendationCopy;
    }
    if (planHeadlineNode) {
      planHeadlineNode.textContent = initialState.planHeadline;
    }
    if (planSubheadlineNode) {
      planSubheadlineNode.textContent = initialState.planSubheadline;
    }
    if (checkoutTitleNode) {
      checkoutTitleNode.textContent = initialState.checkoutTitle;
    }
    if (checkoutCopyNode) {
      checkoutCopyNode.textContent = initialState.checkoutCopy;
    }
    if (checkoutPriceNode) {
      checkoutPriceNode.textContent = initialState.checkoutPrice;
    }
    if (checkoutNode) {
      checkoutNode.hidden = initialState.checkoutHidden;
    }

    if ($(root, "[data-guided-summary-problem]")) {
      $(root, "[data-guided-summary-problem]").textContent = initialState.summaryProblem;
    }
    if ($(root, "[data-guided-summary-solution]")) {
      $(root, "[data-guided-summary-solution]").textContent = initialState.summarySolution;
    }
    if ($(root, "[data-guided-summary-unlock]")) {
      $(root, "[data-guided-summary-unlock]").textContent = initialState.summaryUnlock;
    }
  }

  function openOverlay(root) {
    var overlay = $(root, "[data-guided-overlay]");
    if (!overlay) {
      return;
    }
    root.dataset.guidedStarted = "1";
    overlay.hidden = false;
    document.body.classList.add("sffc-guided-pricing-modal-open");
  }

  function closeOverlay(root) {
    var overlay = $(root, "[data-guided-overlay]");
    if (!overlay) {
      return;
    }
    overlay.hidden = true;
    document.body.classList.remove("sffc-guided-pricing-modal-open");
  }

  function setMobilePane(root, pane) {
    var resolvedPane = pane === "guidance" ? "guidance" : "questions";
    root.dataset.guidedMobilePane = resolvedPane;

    $all(root, "[data-guided-mobile-nav]").forEach(function (button) {
      var active = (button.getAttribute("data-guided-mobile-nav") || "") === resolvedPane;
      button.classList.toggle("is-active", active);
      button.setAttribute("aria-selected", active ? "true" : "false");
    });
  }

  function goToStep(root, stepName, answers) {
    root.dataset.guidedCurrentStep = stepName;
    setActiveStep(root, stepName);
    updateInsights(root, answers || {}, stepName);
    var wizardPanel = $(root, "[data-guided-pricing-flow]");
    if (wizardPanel) {
      wizardPanel.scrollTop = 0;
    }
  }

  function initPreview(root) {
    var preview = $(root, "[data-cv-match-landing-preview]");
    var viewport = $(preview, "[data-cv-match-landing-preview-viewport]");
    var track = $(preview, "[data-cv-match-landing-preview-track]");
    var slides = $all(preview, "[data-cv-match-landing-preview-screen]");
    var dots = $all(preview, "[data-cv-match-landing-preview-dot]");
    var prevButton = $(preview, "[data-cv-match-landing-preview-prev]");
    var nextButton = $(preview, "[data-cv-match-landing-preview-next]");
    var statusNode = $(preview, "[data-cv-match-landing-preview-status]");
    var currentIndex = 0;

    if (!preview || !slides.length || !track) {
      return;
    }

    function paintBars(slide) {
      $all(slide, "[data-preview-bar]").forEach(function (bar) {
        var width = parseInt(bar.getAttribute("data-preview-bar") || "0", 10);
        bar.style.width = Math.max(0, Math.min(100, width)) + "%";
      });
    }

    function update(index) {
      currentIndex = Math.max(0, Math.min(slides.length - 1, index));
      track.style.transform = "translateX(" + String(currentIndex * -100) + "%)";

      slides.forEach(function (slide, slideIndex) {
        var active = slideIndex === currentIndex;
        slide.classList.toggle("is-active", active);
        if (active) {
          paintBars(slide);
          if (statusNode) {
            statusNode.textContent =
              slide.getAttribute("data-preview-status") || "Preview";
          }
        }
      });

      dots.forEach(function (dot, dotIndex) {
        dot.classList.toggle("is-active", dotIndex === currentIndex);
      });
    }

    if (viewport) {
      viewport.addEventListener(
        "pointerenter",
        function () {
          preview.dataset.previewHover = "1";
        },
        { passive: true }
      );
      viewport.addEventListener(
        "pointerleave",
        function () {
          preview.dataset.previewHover = "0";
        },
        { passive: true }
      );
    }

    if (prevButton) {
      prevButton.addEventListener("click", function () {
        update(currentIndex === 0 ? slides.length - 1 : currentIndex - 1);
      });
    }

    if (nextButton) {
      nextButton.addEventListener("click", function () {
        update(currentIndex === slides.length - 1 ? 0 : currentIndex + 1);
      });
    }

    dots.forEach(function (dot, index) {
      dot.addEventListener("click", function () {
        update(index);
      });
    });

    window.setInterval(function () {
      if (preview.dataset.previewHover === "1") {
        return;
      }
      update(currentIndex === slides.length - 1 ? 0 : currentIndex + 1);
    }, 4800);

    update(0);
  }

  function initGuidedPricing(root) {
    var leadForm = $(root, "[data-guided-lead-form]");
    var overlayLeadForm = $(root, "[data-guided-overlay-lead-form]");
    var startButton = $(root, "[data-guided-start]");
    var overlay = $(root, "[data-guided-overlay]");
    var answers = {
      help: "",
      helpLabel: "",
      commitment: "",
      commitmentLabel: "",
      constraint: "",
      constraintLabel: "",
      deliverable: "",
      deliverableLabel: "",
    };
    var initialState = {
      recommendationCopy:
        ($(root, "[data-guided-recommendation-copy]") || {}).textContent || "",
      planHeadline:
        ($(root, "[data-guided-plan-headline]") || {}).textContent || "",
      planSubheadline:
        ($(root, "[data-guided-plan-subheadline]") || {}).textContent || "",
      checkoutTitle:
        ($(root, "[data-pricing-summary-title]") || {}).textContent || "",
      checkoutCopy:
        ($(root, "[data-pricing-summary-copy]") || {}).textContent || "",
      checkoutPrice:
        ($(root, "[data-pricing-summary-price]") || {}).textContent || "",
      checkoutHidden: !!($(root, "[data-pricing-checkout]") || {}).hidden,
      defaultCycle:
        root.getAttribute("data-default-cycle") ||
        (($(root, "[data-guided-pricing-plans]") || {}).getAttribute &&
          $(root, "[data-guided-pricing-plans]").getAttribute("data-default-cycle")) ||
        "monthly",
      defaultPlanSlug:
        (getCurrentPlanCard(root) && getCurrentPlanCard(root).getAttribute("data-plan-slug")) || "",
      summaryProblem:
        ($(root, "[data-guided-summary-problem]") || {}).textContent || "",
      summarySolution:
        ($(root, "[data-guided-summary-solution]") || {}).textContent || "",
      summaryUnlock:
        ($(root, "[data-guided-summary-unlock]") || {}).textContent || "",
    };

    root.dataset.guidedStarted = "0";
    setMobilePane(root, "questions");
    initPreview(root);
    applyIdentity(root, getIdentity(root));
    syncOverlayLeadInputs(root, getIdentity(root));
    resetWizard(root, answers, initialState);

    function continueWithContext(context) {
      var runtimeContext = context || {};

      if (typeof runtimeContext.help === "string" && runtimeContext.help) {
        var helpOption = findOptionForStep("help", runtimeContext.help, answers);
        if (helpOption) {
          answers.help = helpOption.key;
          answers.helpLabel = helpOption.label || "";
          renderStep(root, "constraint", answers);
        }
      }

      if (answers.help && typeof runtimeContext.constraint === "string" && runtimeContext.constraint) {
        var constraintOption = findOptionForStep("constraint", runtimeContext.constraint, answers);
        if (constraintOption) {
          answers.constraint = constraintOption.key;
          answers.constraintLabel = constraintOption.label || "";
          renderStep(root, "deliverable", answers);
        }
      }

      if (answers.constraint && typeof runtimeContext.deliverable === "string" && runtimeContext.deliverable) {
        var deliverableOption = findOptionForStep("deliverable", runtimeContext.deliverable, answers);
        if (deliverableOption) {
          answers.deliverable = deliverableOption.key;
          answers.deliverableLabel = deliverableOption.label || "";
          renderStep(root, "commitment", answers);
        }
      }

      if (answers.deliverable && typeof runtimeContext.commitment === "string" && runtimeContext.commitment) {
        var commitmentOption = findOptionForStep("commitment", runtimeContext.commitment, answers);
        if (commitmentOption) {
          answers.commitment = commitmentOption.key;
          answers.commitmentLabel = commitmentOption.label || "";
        }
      }

      updateSummary(root, answers);
      setMobilePane(root, "questions");
      openOverlay(root);

      var nextStep = resolveNextStepFromAnswers(answers);
      if (nextStep === "recommendation") {
        activateRecommendedPlan(root, answers);
      }
      goToStep(root, nextStep, answers);
    }

    function openWithContext(context) {
      var runtimeContext = context || {};
      root.__guidedPendingContext = runtimeContext;
      root.dataset.guidedSuggestedHelp = String(runtimeContext.suggestedHelp || "").trim();
      root.dataset.guidedSuggestedConstraint = String(runtimeContext.suggestedConstraint || "").trim();
      root.dataset.guidedSuggestedDeliverable = String(runtimeContext.suggestedDeliverable || "").trim();
      root.dataset.guidedSuggestedCommitment = String(runtimeContext.suggestedCommitment || "").trim();
      setIdentityInputs(root, runtimeContext);
      applyIdentity(root, getIdentity(root));
      syncOverlayLeadInputs(root, getIdentity(root));
      resetWizard(root, answers, initialState);

      if (root.dataset.guidedEmbedded === "true" && !hasRequiredIdentity(root)) {
        setMobilePane(root, "questions");
        openOverlay(root);
        goToStep(root, "lead", answers);
        return;
      }

      continueWithContext(runtimeContext);
    }

    root.__guidedPricingOpen = openWithContext;

    var storedEntryContext = getStoredEntryContext();
    if (storedEntryContext) {
      openWithContext(storedEntryContext);
    }

    function handleClose() {
      closeOverlay(root);
      root.__guidedPendingContext = null;
      resetWizard(root, answers, initialState);
      setMobilePane(root, "questions");
      goToStep(root, "help", answers);
    }

    if (leadForm) {
      leadForm.addEventListener("submit", function (event) {
        event.preventDefault();

        if (!leadForm.checkValidity()) {
          if (typeof leadForm.reportValidity === "function") {
            leadForm.reportValidity();
          }
          return;
        }

        applyIdentity(root, getIdentity(root));
        openWithContext({});
      });
    } else if (startButton) {
      startButton.addEventListener("click", function () {
        openWithContext({});
      });
    }

    if (overlayLeadForm) {
      overlayLeadForm.addEventListener("submit", function (event) {
        var overlayFullNameInput = $(root, "[data-guided-overlay-full-name]");
        var overlayEmailInput = $(root, "[data-guided-overlay-email]");
        var fullName = overlayFullNameInput
          ? String(overlayFullNameInput.value || "").trim()
          : "";
        var email = overlayEmailInput
          ? String(overlayEmailInput.value || "").trim()
          : "";

        event.preventDefault();

        if (!overlayLeadForm.checkValidity()) {
          if (typeof overlayLeadForm.reportValidity === "function") {
            overlayLeadForm.reportValidity();
          }
          return;
        }

        setIdentityInputs(root, {
          fullName: fullName,
          email: email,
        });
        applyIdentity(root, getIdentity(root));
        syncOverlayLeadInputs(root, getIdentity(root));
        resetWizard(root, answers, initialState);
        continueWithContext(root.__guidedPendingContext || {});
      });
    }

    $all(root, "[data-guided-close]").forEach(function (button) {
      button.addEventListener("click", function () {
        handleClose();
      });
    });

    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape" && overlay && !overlay.hidden) {
        handleClose();
      }
    });

    root.addEventListener("click", function (event) {
      var choice = event.target.closest("[data-guided-choice]");
      var back = event.target.closest("[data-guided-back]");
      var mobileNav = event.target.closest("[data-guided-mobile-nav]");
      var pricingToggle = event.target.closest("[data-pricing-cycle-toggle]");
      var pricingSelect = event.target.closest("[data-pricing-select]");

      if (mobileNav) {
        setMobilePane(root, mobileNav.getAttribute("data-guided-mobile-nav") || "questions");
        return;
      }
      if (back) {
        setMobilePane(root, "questions");
        goToStep(root, back.getAttribute("data-guided-back") || "help", answers);
        return;
      }
      if (pricingToggle) {
        var targetCycle = pricingToggle.getAttribute("data-pricing-cycle-toggle") || initialState.defaultCycle || "monthly";

        $all(root, "[data-pricing-cycle-toggle]").forEach(function (toggle) {
          var active = toggle === pricingToggle;
          toggle.classList.toggle("is-active", active);
          toggle.setAttribute("aria-selected", active ? "true" : "false");
        });

        $all(root, "[data-pricing-grid]").forEach(function (grid) {
          var active = (grid.getAttribute("data-pricing-grid") || "") === targetCycle;
          grid.hidden = !active || grid.dataset.guidedHasVisible === "0";
          grid.classList.toggle("is-active", active && !grid.hidden);
        });

        setActivePlanCard(root, getCurrentPlanCard(root, targetCycle), answers);
        return;
      }
      if (pricingSelect) {
        setActivePlanCard(root, pricingSelect.closest("[data-pricing-card]"), answers);
        return;
      }
      if (!choice) {
        return;
      }

      var stepName = choice.getAttribute("data-guided-step-key") || "";
      var value = choice.getAttribute("data-guided-value") || "";
      var labelNode = choice.querySelector("strong");
      var selectedLabel = labelNode ? String(labelNode.textContent || "").trim() : "";

      if (stepName === "help") {
        clearDownstreamAnswers(answers, "help");
        answers.help = value;
        answers.helpLabel = selectedLabel;
        renderStep(root, "constraint", answers);
        updateSummary(root, answers);
        setMobilePane(root, "questions");
        goToStep(root, "constraint", answers);
        return;
      }

      if (stepName === "constraint") {
        clearDownstreamAnswers(answers, "constraint");
        answers.constraint = value;
        answers.constraintLabel = selectedLabel;
        renderStep(root, "deliverable", answers);
        updateSummary(root, answers);
        setMobilePane(root, "questions");
        goToStep(root, "deliverable", answers);
        return;
      }

      if (stepName === "deliverable") {
        clearDownstreamAnswers(answers, "deliverable");
        answers.deliverable = value;
        answers.deliverableLabel = selectedLabel;
        renderStep(root, "commitment", answers);
        updateSummary(root, answers);
        setMobilePane(root, "questions");
        goToStep(root, "commitment", answers);
        return;
      }

      if (stepName === "commitment") {
        answers.commitment = value;
        answers.commitmentLabel = selectedLabel;
        updateSummary(root, answers);
        setMobilePane(root, "questions");
        activateRecommendedPlan(root, answers);
        goToStep(root, "recommendation", answers);
      }
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    $all(document, "[data-guided-pricing]").forEach(function (root) {
      initGuidedPricing(root);
    });
  });
})();
