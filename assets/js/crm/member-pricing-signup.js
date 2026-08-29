(function () {
  function q(root, selector) {
    return root ? root.querySelector(selector) : null;
  }

  function qa(root, selector) {
    return Array.prototype.slice.call(
      root ? root.querySelectorAll(selector) : []
    );
  }

  function escapeSelector(value) {
    if (window.CSS && typeof window.CSS.escape === "function") {
      return window.CSS.escape(value);
    }
    return String(value || "").replace(/["\\]/g, "\\$&");
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
      String(60 * 60 * 24 * 30) +
      "; SameSite=Lax";

    if (
      name.indexOf("sffc_signup_") === 0 ||
      name === "sffc_contact_data" ||
      name === "sffc_recruiter_intro_onboarding" ||
      name === "sffc_dubai_career_flow"
    ) {
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

  function syncSignupPrefill(data) {
    var config = window.sffcMemberPricingSignup || {};
    if (!config.ajaxUrl || !config.prefillNonce || !window.fetch) {
      return;
    }

    var body = new URLSearchParams();
    body.set("action", "sffc_sync_signup_prefill");
    body.set("nonce", config.prefillNonce);
    body.set("email", String(data.email || ""));
    body.set("first_name", String(data.firstName || ""));
    body.set("last_name", String(data.lastName || ""));
    body.set("mepr_job_id", String(data.meprJobId || ""));
    body.set("study_level", String(data.study || ""));
    body.set("account_type", String(data.account || ""));

    uniqueValues(data.financeInterest).forEach(function (value) {
      body.append("finance_interest[]", value);
    });
    uniqueValues(data.seniorityLevel).forEach(function (value) {
      body.append("seniority_level[]", value);
    });
    uniqueValues(data.supportNeed).forEach(function (value) {
      body.append("support_need[]", value);
    });

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

  function state(root) {
    if (!root._sffcMemberSignupState) {
      root._sffcMemberSignupState = {
        firstName: "",
        lastName: "",
        email: "",
        meprJobId: "",
        study: "",
        account: "",
        accountDetail: "",
        city: "",
        country: "",
        financeInterest: [],
        seniorityLevel: [],
        supportNeed: [],
        selectedMethod: "",
      };
    }
    return root._sffcMemberSignupState;
  }

  function uniqueValues(values) {
    return (Array.isArray(values) ? values : []).filter(function (
      value,
      index,
      array
    ) {
      return value && array.indexOf(value) === index;
    });
  }

  function toggleArrayValue(values, value, limit) {
    var nextValues = uniqueValues(values);
    var valueIndex = nextValues.indexOf(value);

    if (valueIndex !== -1) {
      nextValues.splice(valueIndex, 1);
      return nextValues;
    }

    if (limit && nextValues.length >= limit) {
      return nextValues;
    }

    nextValues.push(value);
    return nextValues;
  }

  function hasArrayValue(values, value) {
    return uniqueValues(values).indexOf(value) !== -1;
  }

  function joinSummary(values) {
    var list = uniqueValues(values);
    if (!list.length) {
      return "";
    }
    if (list.length === 1) {
      return list[0];
    }
    if (list.length === 2) {
      return list[0] + " and " + list[1];
    }
    return list.slice(0, -1).join(", ") + ", and " + list[list.length - 1];
  }

  function parseFullName(fullName) {
    var parts = String(fullName || "")
      .trim()
      .split(/\s+/)
      .filter(Boolean);
    var firstName = parts.shift() || "";
    var lastName = parts.length ? parts[parts.length - 1] : "";

    return {
      firstName: firstName,
      lastName: lastName,
    };
  }

  function normalizeJobId(value) {
    var normalized = String(value || "").trim();
    var match;

    if (!normalized) {
      return "";
    }

    if (/^\d+$/.test(normalized)) {
      return normalized;
    }

    match = normalized.match(
      /(?:^|[?&])sffc_recruiter_contact_job_id=(\d+)/i
    );
    if (match && match[1]) {
      return String(match[1]);
    }

    match = normalized.match(/\b(\d+)\b/);
    return match && match[1] ? String(match[1]) : "";
  }

  function getRecruiterContactJobId(root, fallbackValue) {
    var attributeValue = normalizeJobId(fallbackValue);
    var urlValue = "";

    if (attributeValue) {
      return attributeValue;
    }

    try {
      urlValue = normalizeJobId(
        new URL(window.location.href).searchParams.get(
          "sffc_recruiter_contact_job_id"
        ) || ""
      );
    } catch (error) {
      urlValue = "";
    }

    if (urlValue) {
      return urlValue;
    }

    return root
      ? normalizeJobId(
          root.getAttribute("data-member-signup-recruiter-contact-job-id") || ""
        )
      : "";
  }

  function inferNameFromEmail(email) {
    var localPart = String(email || "").split("@")[0] || "";
    var parts = localPart
      .split(/[._+\-\d]+/)
      .map(function (part) {
        return String(part || "").trim();
      })
      .filter(Boolean);

    var firstName = parts[0] || "MENA Careers";
    var lastName = parts.length > 1 ? parts[parts.length - 1] : "";

    return {
      firstName: firstName.charAt(0).toUpperCase() + firstName.slice(1),
      lastName: lastName
        ? lastName.charAt(0).toUpperCase() + lastName.slice(1)
        : "",
    };
  }

  function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(email || "").trim());
  }

  function setIdentityCookies(data) {
    setCookie("sffc_signup_first_name", data.firstName);
    setCookie("sffc_signup_last_name", data.lastName);
    setCookie("sffc_signup_email", data.email);
    setCookie("sffc_signup_mepr_job_id", normalizeJobId(data.meprJobId));
    setCookie("sffc_signup_study_level", data.study);
    setCookie("sffc_signup_account_type", data.account);
    setCookie(
      "sffc_signup_finance_interest",
      uniqueValues(data.financeInterest).join("|")
    );
    setCookie(
      "sffc_signup_seniority_level",
      uniqueValues(data.seniorityLevel).join("|")
    );
    setCookie(
      "sffc_signup_support_need",
      uniqueValues(data.supportNeed).join("|")
    );
    syncSignupPrefill(data);
  }

  function fillMemberPressForm(container, data) {
    if (!container) {
      return;
    }

    var username = data.email ? String(data.email).split("@")[0] : "";
    var fields = {
      'input[name="user_email"], input[name="mepr_user_email"], input[name="email"], #user_email, #user_email1, #mepr_email, #mepr_user_email':
        data.email,
      'input[name="user_first_name"], input[name="mepr_first_name"], input[name="first_name"], #user_first_name, #user_first_name1, #mepr_first_name':
        data.firstName,
      'input[name="user_last_name"], input[name="mepr_last_name"], input[name="last_name"], #user_last_name, #user_last_name1, #mepr_last_name':
        data.lastName,
      'input[name="user_login"], input[name="mepr_user_login"], #user_login, #mepr_user_login':
        username,
      'input[name*="city" i], #city': data.city,
      'input[name*="country" i], #country': data.country,
      'input[name="mepr_job_id"], #mepr_job_id, #mepr_job_id1':
        data.meprJobId,
    };

    Object.keys(fields).forEach(function (selector) {
      var value = fields[selector];
      if (!value) {
        return;
      }
      qa(container, selector).forEach(function (input) {
        input.value = value;
        input.dispatchEvent(new Event("input", { bubbles: true }));
        input.dispatchEvent(new Event("change", { bubbles: true }));
      });
    });
  }

  function markMemberPressSubmitting(target) {
    var form = target ? target.closest("form") : null;
    var panel = target
      ? target.closest("[data-member-signup-memberpress]")
      : null;

    if (form) {
      form.classList.add("is-memberpress-submitting");
    }
    if (panel) {
      panel.classList.add("is-memberpress-submitting");
    }
  }

  function clearMemberPressSubmitting(root) {
    qa(root, ".is-memberpress-submitting").forEach(function (node) {
      node.classList.remove("is-memberpress-submitting");
    });
  }

  function executeEmbeddedScripts(container) {
    if (!container) {
      return;
    }

    qa(container, "script").forEach(function (script) {
      var replacement = document.createElement("script");

      Array.prototype.slice
        .call(script.attributes || [])
        .forEach(function (attribute) {
          replacement.setAttribute(attribute.name, attribute.value);
        });

      if (script.textContent) {
        replacement.textContent = script.textContent;
      }

      script.parentNode.replaceChild(replacement, script);
    });
  }

  function refreshMemberPressPaymentUI(container) {
    if (!container) {
      return;
    }

    window.setTimeout(function () {
      var selectedGateway =
        q(
          container,
          '.mepr-payment-method input[type="radio"]:checked, input[type="radio"][name*="gateway"]:checked, input[type="radio"][name*="payment_method"]:checked'
        ) ||
        q(
          container,
          '.mepr-payment-method input[type="radio"], input[type="radio"][name*="gateway"], input[type="radio"][name*="payment_method"]'
        );

      if (!selectedGateway) {
        return;
      }

      if (!selectedGateway.checked) {
        selectedGateway.checked = true;
      }

      selectedGateway.dispatchEvent(new Event("click", { bubbles: true }));
      selectedGateway.dispatchEvent(new Event("input", { bubbles: true }));
      selectedGateway.dispatchEvent(new Event("change", { bubbles: true }));
    }, 80);
  }

  function rerunMemberPressOverlayInitializers(container) {
    if (!container) {
      return Promise.resolve();
    }

    var scriptPatterns = [
      /\/wp-content\/plugins\/memberpress\/js\/signup\.js/i,
      /\/wp-content\/plugins\/memberpress\/app\/gateways\/stripe\/form\.js/i,
    ];

    var sources = qa(document, "script[src]")
      .map(function (script) {
        return script.getAttribute("src") || "";
      })
      .filter(function (src) {
        return scriptPatterns.some(function (pattern) {
          return pattern.test(src);
        });
      });

    sources = sources.filter(function (src, index, list) {
      return src && list.indexOf(src) === index;
    });

    if (!sources.length) {
      return Promise.resolve();
    }

    return sources.reduce(function (chain, src) {
      return chain.then(function () {
        return new Promise(function (resolve) {
          var script = document.createElement("script");
          var cachebuster = "sffc_mp_overlay_init=" + String(Date.now());
          script.src =
            src + (src.indexOf("?") === -1 ? "?" : "&") + cachebuster;
          script.async = false;
          script.onload = resolve;
          script.onerror = resolve;
          document.body.appendChild(script);
        });
      });
    }, Promise.resolve());
  }

  function init(root) {
    if (!root || root._sffcMemberSignupInitialized) {
      return;
    }
    root._sffcMemberSignupInitialized = true;

    var entrySection = q(root, ".sffc-member-signup__entry");
    var form = q(root, "[data-member-signup-form]");
    var entryStudyCards = qa(root, "[data-member-signup-entry-study]");
    var simpleStudyButtons = qa(root, "[data-member-signup-simple-study]");
    var fullNameInput = q(root, "[data-member-signup-full-name]");
    var emailInput = q(root, "[data-member-signup-email]");
    var error = q(root, "[data-member-signup-error]");
    var loginState = q(root, "[data-member-signup-login]");
    var progressive = q(root, "[data-member-signup-progressive]");
    var accountGroups = qa(root, "[data-member-signup-account-group]");
    var defaultActions = q(root, "[data-member-signup-default-actions]");
    var defaultProceed = q(root, "[data-member-signup-default-proceed]");
    var plans = q(root, "[data-member-signup-plans]");
    var methods = q(root, "[data-member-signup-methods]") || plans;
    var pricing = q(root, "[data-member-signup-job-pricing]");
    var review = q(root, "[data-member-signup-review]");
    var checkout = q(root, "[data-member-signup-checkout]");
    var checkoutPrice = q(root, "[data-member-signup-checkout-price]");
    var card = q(root, "[data-member-signup-card]");
    var onboarding = q(root, "[data-member-signup-onboarding]");
    var routeStep = q(root, "[data-member-signup-route]");
    var routeModal = q(root, "[data-member-signup-route-modal]");
    var brandAccountMenu = q(root, "[data-member-signup-brand-account-menu]");
    var jobDescription = q(root, "[data-member-signup-job-description]");
    var localizedMessages =
      (window.sffcMemberPricingSignup &&
        window.sffcMemberPricingSignup.messages) ||
      {};
    var variant = root.getAttribute("data-member-signup-variant") || "default";
    var helpUrl = root.getAttribute("data-member-signup-help-url") || "";
    var membershipUrl =
      root.getAttribute("data-member-signup-membership-url") || "/memberships/";
    var jobId = root.getAttribute("data-member-signup-job-id") || "";
    var jobTitle = root.getAttribute("data-member-signup-job-title") || "";
    var recruiterContactJobId = getRecruiterContactJobId(root, jobId);

    state(root).meprJobId = recruiterContactJobId;

    function syncIdentityInputs() {
      var currentState = state(root);
      if (!currentState.meprJobId) {
        currentState.meprJobId = recruiterContactJobId;
      }
      var fullName = [currentState.firstName, currentState.lastName]
        .filter(Boolean)
        .join(" ")
        .trim();

      if (
        fullNameInput &&
        fullName &&
        String(fullNameInput.value || "").trim() !== fullName
      ) {
        fullNameInput.value = fullName;
      }

      if (
        emailInput &&
        currentState.email &&
        String(emailInput.value || "").trim() !== currentState.email
      ) {
        emailInput.value = currentState.email;
      }

      qa(root, "[data-member-signup-memberpress]").forEach(function (panel) {
        fillMemberPressForm(panel, currentState);
      });
    }

    function pulseTransition() {
      root.classList.add("is-transitioning");
      if (root._sffcTransitionTimer) {
        window.clearTimeout(root._sffcTransitionTimer);
      }
      root._sffcTransitionTimer = window.setTimeout(function () {
        root.classList.remove("is-transitioning");
        root._sffcTransitionTimer = null;
      }, 360);
    }

    function closeBrandAccountMenu() {
      var toggle = q(root, "[data-member-signup-brand-account-toggle]");
      if (brandAccountMenu) {
        brandAccountMenu.hidden = true;
      }
      if (toggle) {
        toggle.setAttribute("aria-expanded", "false");
      }
    }

    function toggleBrandAccountMenu(toggle) {
      if (!brandAccountMenu || !toggle) {
        return;
      }

      var isOpen = !brandAccountMenu.hidden;
      brandAccountMenu.hidden = isOpen;
      toggle.setAttribute("aria-expanded", isOpen ? "false" : "true");
    }

    function trackExternalApply(externalLink) {
      if (!externalLink) {
        return;
      }

      var ajaxUrl =
        (window.sffcMemberPricingSignup || {}).ajaxUrl ||
        "/wp-admin/admin-ajax.php";
      var nonce = (window.sffcMemberPricingSignup || {}).nonce || "";
      var email =
        state(root).email ||
        (emailInput ? String(emailInput.value || "").trim() : "");
      var body = new URLSearchParams({
        action: "sffc_member_pricing_track_external_apply",
        nonce: nonce,
        route: "external_apply",
        variant: variant,
        job_id: jobId,
        job_title: jobTitle,
        email: email,
        apply_url: externalLink.href || "",
      }).toString();

      if (navigator.sendBeacon) {
        try {
          navigator.sendBeacon(
            ajaxUrl,
            new Blob([body], {
              type: "application/x-www-form-urlencoded; charset=UTF-8",
            })
          );
          return;
        } catch (error) {}
      }

      if (window.fetch) {
        fetch(ajaxUrl, {
          method: "POST",
          credentials: "same-origin",
          keepalive: true,
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: body,
        }).catch(function () {});
      }
    }

    function showError(message) {
      if (!error) {
        return;
      }
      error.textContent = message || "";
      error.hidden = !message;
    }

    function setStep(index) {
      qa(root, ".sffc-member-signup__steps li").forEach(function (
        node,
        nodeIndex
      ) {
        node.classList.toggle("is-active", nodeIndex === index);
      });
    }

    function hasPlanPath(path) {
      return qa(root, "[data-member-signup-plan-card]").some(function (card) {
        return (
          (card.getAttribute("data-member-signup-plan-path") || "platform") ===
          path
        );
      });
    }

    function firstAvailablePlanPath(paths, fallback) {
      for (var index = 0; index < paths.length; index += 1) {
        if (hasPlanPath(paths[index])) {
          return paths[index];
        }
      }
      return fallback || "platform";
    }

    function accountForSimpleStudy(study) {
      if (study === "daily_alerts") {
        return firstAvailablePlanPath(
          ["ongoing_contacts", "extra_contacts", "one_contact", "platform"],
          "platform"
        );
      }
      if (study === "profile_guidance") {
        return "all_access";
      }
      return "platform";
    }

    function syncSimpleStudyButtons() {
      simpleStudyButtons.forEach(function (button) {
        var isActive =
          (button.getAttribute("data-member-signup-simple-study") || "") ===
          state(root).study;
        button.classList.toggle("is-active", isActive);
        button.setAttribute("aria-pressed", isActive ? "true" : "false");
      });
    }

    function showOnboardingStep(stepName) {
      qa(root, "[data-member-signup-onboarding-step]").forEach(function (step) {
        var isActive =
          (step.getAttribute("data-member-signup-onboarding-step") || "") ===
          stepName;
        step.hidden = !isActive;
        step.classList.toggle("is-active", isActive);
      });
    }

    function selectionLabels(type, values) {
      return uniqueValues(values)
        .map(function (item) {
          if (type === "finance") {
            if (item === "investment-banking") return "Investment Banking (PE feeder)";
            if (item === "private-equity") return "Private Equity";
            if (item === "accounting-finance") return "Fund Finance";
            if (item === "venture-capital") return "Growth Equity / Venture Capital";
            if (item === "asset-management") return "Real Assets / Infrastructure";
            if (item === "consulting") return "Portfolio Operations";
            if (item === "risk-compliance") return "Investor Relations / Fundraising";
            if (item === "other") return "Other PE track";
          }
          if (type === "seniority") {
            if (item === "analyst") return "Analyst";
            if (item === "associate") return "Associate";
            if (item === "manager") return "Manager";
            if (item === "director") return "Director";
          }
          if (type === "support") {
            if (item === "finding-relevant-roles") return "ATS Score";
            if (item === "recruiter-visibility") return "Profile / Role Fit";
            if (item === "career-direction") return "CV Rewrite";
            if (item === "career-pivot") return "LinkedIn Profile";
            if (item === "cv-materials") return "Career Pivot";
            if (item === "mentorship-guidance") return "Recruiter Feedback";
          }
          return "";
        })
        .filter(Boolean);
    }

    function renderReviewList(target, labels) {
      if (!target) {
        return;
      }
      target.innerHTML = labels
        .map(function (label) {
          return (
            '<li><span aria-hidden="true">✓</span><span>' +
            label +
            "</span></li>"
          );
        })
        .join("");
    }

    function populateReview() {
      renderReviewList(
        q(root, "[data-member-signup-review-finance]"),
        selectionLabels("finance", state(root).financeInterest)
      );
      renderReviewList(
        q(root, "[data-member-signup-review-seniority]"),
        selectionLabels("seniority", state(root).seniorityLevel)
      );
      renderReviewList(
        q(root, "[data-member-signup-review-support]"),
        selectionLabels("support", state(root).supportNeed)
      );
    }

    function syncOnboardingState() {
      var financeSelections = uniqueValues(state(root).financeInterest);
      var senioritySelections = uniqueValues(state(root).seniorityLevel);
      var supportSelections = uniqueValues(state(root).supportNeed);
      var financeLimitReached = financeSelections.length >= 3;
      var seniorityLimitReached = senioritySelections.length >= 2;
      var supportLimitReached = supportSelections.length >= 3;

      qa(root, "[data-member-signup-finance-interest]").forEach(function (
        button
      ) {
        var value =
          button.getAttribute("data-member-signup-finance-interest") || "";
        var isActive = hasArrayValue(state(root).financeInterest, value);
        button.classList.toggle("is-active", isActive);
        button.classList.toggle(
          "is-disabled",
          financeLimitReached && !isActive
        );
        button.disabled = financeLimitReached && !isActive;
        button.setAttribute(
          "aria-disabled",
          button.disabled ? "true" : "false"
        );
      });

      qa(root, "[data-member-signup-seniority]").forEach(function (button) {
        var value = button.getAttribute("data-member-signup-seniority") || "";
        var isActive = hasArrayValue(state(root).seniorityLevel, value);
        button.classList.toggle("is-active", isActive);
        button.classList.toggle(
          "is-disabled",
          seniorityLimitReached && !isActive
        );
        button.disabled = seniorityLimitReached && !isActive;
        button.setAttribute(
          "aria-disabled",
          button.disabled ? "true" : "false"
        );
      });

      qa(root, "[data-member-signup-support-need]").forEach(function (button) {
        var value =
          button.getAttribute("data-member-signup-support-need") || "";
        var isActive = hasArrayValue(state(root).supportNeed, value);
        button.classList.toggle("is-active", isActive);
        button.classList.toggle(
          "is-disabled",
          supportLimitReached && !isActive
        );
        button.disabled = supportLimitReached && !isActive;
        button.setAttribute(
          "aria-disabled",
          button.disabled ? "true" : "false"
        );
      });

      qa(root, "[data-member-signup-limit-hint]").forEach(function (hint) {
        var hintType = hint.getAttribute("data-member-signup-limit-hint") || "";
        var isVisible =
          (hintType === "finance-interest" && financeLimitReached) ||
          (hintType === "seniority" && seniorityLimitReached) ||
          (hintType === "support" && supportLimitReached);
        hint.hidden = !isVisible;
      });

      qa(root, "[data-member-signup-onboarding-next]").forEach(function (
        button
      ) {
        var nextStep =
          button.getAttribute("data-member-signup-onboarding-next") || "";
        if (nextStep === "seniority") {
          button.disabled = financeSelections.length === 0;
        } else if (nextStep === "support") {
          button.disabled = senioritySelections.length === 0;
        }
      });

      qa(root, "[data-member-signup-onboarding-complete]").forEach(function (
        button
      ) {
        button.disabled = supportSelections.length === 0;
      });
    }

    function startJobOnboarding() {
      if (routeModal) {
        routeModal.hidden = true;
      }
      root.classList.add("is-job-flow-active");
      root.classList.remove("is-job-pricing");
      if (card) {
        card.hidden = true;
      }
      if (routeStep) {
        routeStep.hidden = true;
      }
      if (methods) {
        methods.hidden = true;
      }
      if (pricing) {
        pricing.hidden = true;
      }
      if (review) {
        review.hidden = true;
      }
      if (jobDescription) {
        jobDescription.hidden = true;
      }
      if (onboarding) {
        onboarding.hidden = false;
      }
      showOnboardingStep("finance-interest");
      syncOnboardingState();
      setStep(2);
    }

    function showRouteModal() {
      if (!routeModal) {
        return;
      }
      routeModal.hidden = false;
    }

    function hideRouteModal() {
      if (!routeModal) {
        return;
      }
      routeModal.hidden = true;
      var routeModalFeedback = q(
        root,
        "[data-member-signup-route-modal-feedback]"
      );
      if (routeModalFeedback) {
        routeModalFeedback.hidden = true;
        routeModalFeedback.textContent = "";
      }
    }

    function showRouteModalFeedback(message) {
      var routeModalFeedback = q(
        root,
        "[data-member-signup-route-modal-feedback]"
      );
      if (!routeModalFeedback) {
        return;
      }
      routeModalFeedback.textContent = message || "";
      routeModalFeedback.hidden = !message;
    }

    function copyRouteMembershipLink() {
      var text = membershipUrl || "/memberships/";

      if (
        navigator.clipboard &&
        typeof navigator.clipboard.writeText === "function"
      ) {
        return navigator.clipboard.writeText(text);
      }

      return new Promise(function (resolve, reject) {
        var input = document.createElement("input");
        input.type = "text";
        input.value = text;
        input.setAttribute("readonly", "readonly");
        input.style.position = "absolute";
        input.style.left = "-9999px";
        document.body.appendChild(input);
        input.select();
        input.setSelectionRange(0, input.value.length);

        try {
          var success = document.execCommand("copy");
          document.body.removeChild(input);
          if (success) {
            resolve();
          } else {
            reject(new Error("copy_failed"));
          }
        } catch (error) {
          document.body.removeChild(input);
          reject(error);
        }
      });
    }

    function findExistingJobDescriptionTarget() {
      var selectors = [
        "[data-job-description]",
        "#job-description",
        "#role-summary",
        ".job_description",
        ".sffc-job-landing-description",
        ".sffc-job-summary",
        ".sffc-job-apply__content",
      ];

      for (var i = 0; i < selectors.length; i += 1) {
        var target = document.querySelector(selectors[i]);
        if (target && !root.contains(target)) {
          return target;
        }
      }

      return null;
    }

    function showJobRoute() {
      pulseTransition();
      state(root).selectedMethod = "";
      root.classList.add("is-job-flow-active");
      root.classList.remove("is-job-pricing");
      if (card) {
        card.hidden = false;
      }
      if (form) {
        form.hidden = true;
      }
      if (loginState) {
        loginState.hidden = true;
      }
      if (routeStep) {
        routeStep.hidden = false;
      }
      if (methods) {
        methods.hidden = true;
      }
      if (pricing) {
        pricing.hidden = true;
      }
      if (review) {
        review.hidden = true;
      }
      if (jobDescription) {
        jobDescription.hidden = true;
      }
      if (onboarding) {
        onboarding.hidden = true;
      }
      root.classList.remove("has-plans");
      setStep(0);
    }

    function revealProgressive() {
      pulseTransition();
      if (variant === "job") {
        startJobOnboarding();
        return;
      }
      syncDefaultAccountGroups();
      updateDefaultPlanSummary();
      if (progressive) {
        progressive.hidden = false;
      }
      root.classList.add("is-progressing");
      setStep(1);
    }

    function showLogin() {
      pulseTransition();
      if (entrySection) {
        entrySection.hidden = true;
      }
      if (card) {
        card.hidden = false;
      }
      if (form) {
        form.hidden = true;
      }
      if (progressive) {
        progressive.hidden = true;
      }
      if (loginState) {
        loginState.hidden = false;
      }
      if (card) {
        card.classList.add("is-login");
      }
    }

    function syncDefaultEntryState() {
      if (variant === "job" || !card) {
        return;
      }

      if (variant === "simple") {
        if (entrySection) {
          entrySection.hidden = true;
        }
        if (!root.classList.contains("has-plans")) {
          card.hidden = false;
          if (form) {
            form.hidden = false;
          }
          if (plans) {
            plans.hidden = true;
          }
        }
        syncIdentityInputs();
        return;
      }

      var hasStudy = !!state(root).study;
      if (entrySection) {
        entrySection.hidden = hasStudy;
      }
      card.hidden = !hasStudy;

      if (!hasStudy) {
        root.classList.remove("is-progressing");
        root.classList.remove("has-plans");
        if (progressive) {
          progressive.hidden = true;
        }
        if (plans) {
          plans.hidden = true;
        }
        syncIdentityInputs();
        return;
      }

      if (!state(root).account) {
        if (progressive) {
          progressive.hidden = false;
        }
        if (plans) {
          plans.hidden = true;
        }
        syncIdentityInputs();
        return;
      }

      if (progressive) {
        progressive.hidden = false;
      }
      if (plans) {
        plans.hidden = true;
      }
      syncIdentityInputs();
    }

    function syncDefaultAccountGroups() {
      var selectedStudy;

      if (variant === "job" || !accountGroups.length) {
        return;
      }

      selectedStudy = state(root).study || "";
      accountGroups.forEach(function (group) {
        group.hidden =
          !!state(root).account ||
          (group.getAttribute("data-member-signup-account-group") || "") !==
            selectedStudy;
      });
    }

    function syncEntryStudyCards() {
      var selectedStudy = state(root).study || "";

      entryStudyCards.forEach(function (cardButton) {
        var isActive =
          (cardButton.getAttribute("data-member-signup-entry-study") || "") ===
          selectedStudy;
        cardButton.classList.toggle("is-active", isActive);
        cardButton.setAttribute("aria-pressed", isActive ? "true" : "false");
      });
    }

    function resetDefaultPlans() {
      if (variant === "job") {
        return;
      }

      state(root).account = "";
      state(root).accountDetail = "";
      root.classList.remove("has-plans");

      if (plans) {
        plans.hidden = true;
      }
      if (defaultActions) {
        defaultActions.hidden = true;
      }

      updateDefaultPlanSummary();
      qa(root, "[data-member-signup-account]").forEach(function (button) {
        button.classList.remove("is-active");
      });
    }

    function updateDefaultPlanSummary() {
      var summary = q(root, "[data-member-signup-plan-summary]");
      var summaryList = q(root, "[data-member-signup-plan-summary-list]");
      var summaryHeading = summary ? q(summary, "strong") : null;
      var items = [];
      var recruiterPreviewImage;
      var recruiterAvatarSrc =
        "https://joinsenna.com/wp-content/uploads/2024/04/266217121-designer-woman-portrait-and-ha.jpeg";
      var sennaLogoSrc =
        "https://media.joinsenna.com/2026/01/sennaLogoOfficial.png?1767629924";
      var gmailLogoSrc =
        "https://upload.wikimedia.org/wikipedia/commons/thumb/7/7e/Gmail_icon_%282020%29.svg/1280px-Gmail_icon_%282020%29.svg.png";

      if (variant === "job" || !summary || !summaryList) {
        return;
      }

      recruiterPreviewImage = q(
        root,
        ".sffc-member-signup__contact-preview-card .sffc-member-signup__route-reveal-avatar img"
      );
      recruiterAvatarSrc =
        recruiterPreviewImage && (recruiterPreviewImage.getAttribute("src") || "")
          ? recruiterPreviewImage.getAttribute("src") || recruiterAvatarSrc
          : recruiterAvatarSrc;

      if (state(root).study === "recruiter_contacts") {
        items = [
          {
            label:
              state(root).account === "one_contact"
                ? "Recruiter contact for this role"
                : "Recruiter contacts",
            image: recruiterAvatarSrc,
            imageClass: "is-recruiter",
          },
        ];
        if (state(root).account === "extra_contacts" || state(root).account === "ongoing_contacts") {
          items.push({
            label: "Find similar relevant open roles",
            image: sennaLogoSrc,
            imageClass: "is-senna",
          });
        }
        if (state(root).account === "ongoing_contacts") {
          items.push({
            label: "Ongoing recruiter hiring alerts",
            image: gmailLogoSrc,
            imageClass: "is-alert",
          });
        }
      } else if (state(root).study === "profile_positioning") {
        items = [{ label: "Profile positioning feedback" }, { label: "Sharper LinkedIn and CV positioning" }, { label: "Role fit and messaging" }];
      } else if (state(root).study === "both") {
        items = [
          {
            label: "Recruiter contacts",
            image: recruiterAvatarSrc,
            imageClass: "is-recruiter",
          },
          {
            label: "Find similar relevant open roles",
            image: sennaLogoSrc,
            imageClass: "is-senna",
          },
          {
            label: "Ongoing recruiter hiring alerts",
            image: gmailLogoSrc,
            imageClass: "is-alert",
          },
        ];
      }

      if (!items.length || !state(root).account) {
        summary.hidden = true;
        summaryList.innerHTML = "";
        if (summaryHeading) {
          summaryHeading.hidden = false;
        }
        summaryList.hidden = false;
        summary.classList.remove("is-form-only");
        return;
      }

      summaryList.innerHTML = items
        .map(function (item) {
          var label = typeof item === "string" ? item : item.label || "";
          var image = typeof item === "object" && item.image ? item.image : "";
          var imageClass =
            typeof item === "object" && item.imageClass ? " " + item.imageClass : "";
          var visual = image
            ? '<span class="sffc-member-signup__plan-summary-avatar' +
              imageClass +
              '" aria-hidden="true"><img src="' +
              image +
              '" alt=""></span>'
            : '<span class="sffc-member-signup__plan-summary-check" aria-hidden="true">✓</span>';
          return (
            "<li>" +
            visual +
            "<span>" +
            label +
            "</span></li>"
          );
        })
        .join("");
      if (summaryHeading) {
        summaryHeading.hidden = false;
      }
      summaryList.hidden = false;
      summary.classList.remove("is-form-only");
      summary.hidden = false;
    }

    function showRecruiterContactFormStep() {
      var summary = q(root, "[data-member-signup-plan-summary]");
      var summaryList = q(root, "[data-member-signup-plan-summary-list]");
      var summaryHeading = summary ? q(summary, "strong") : null;

      if (!summary) {
        return;
      }

      pulseTransition();
      accountGroups.forEach(function (group) {
        group.hidden = true;
      });
      if (plans) {
        plans.hidden = true;
      }
      root.classList.remove("has-plans");
      if (summaryHeading) {
        summaryHeading.hidden = true;
      }
      if (summaryList) {
        summaryList.hidden = true;
      }
      summary.classList.add("is-form-only");
      summary.hidden = false;
      syncIdentityInputs();
    }

    function updatePlanBadges() {
      qa(root, "[data-member-signup-plan-card]").forEach(function (planCard) {
        var badge = q(planCard, "[data-member-signup-plan-badge]");
        var defaultLabel;
        var reviewLabel;
        var useReviewLabel;

        if (!badge) {
          return;
        }

        defaultLabel =
          badge.getAttribute("data-member-signup-plan-badge-default") || "";
        reviewLabel =
          badge.getAttribute("data-member-signup-plan-badge-review") ||
          defaultLabel;
        useReviewLabel =
          state(root).study === "recruiter_contacts" &&
          state(root).account === "ongoing_contacts" &&
          (planCard.getAttribute("data-member-signup-plan-path") ||
            "platform") === "ongoing_contacts";

        badge.textContent = useReviewLabel ? reviewLabel : defaultLabel;
      });
    }

    function showDefaultPlans() {
      if (variant === "job" || !state(root).account) {
        return;
      }
      pulseTransition();
      if (card) {
        card.hidden = true;
      }
      if (progressive) {
        progressive.hidden = true;
      }
      if (plans) {
        plans.hidden = false;
      }
      root.classList.add("has-plans");
      if (variant === "simple") {
        setStep(1);
      }
      filterPlansForAccount();
      updatePlanBadges();
    }

    function findPlanCardForAccount(accountPath) {
      var preferredCycle = activeBillingCycle();
      var matchingCards = qa(root, "[data-member-signup-plan-card]").filter(
        function (planCard) {
          return (
            (planCard.getAttribute("data-member-signup-plan-path") ||
              "platform") === accountPath
          );
        }
      );

      return (
        matchingCards.find(function (planCard) {
          return (
            (planCard.getAttribute("data-plan-cycle") || "monthly") ===
            preferredCycle
          );
        }) ||
        matchingCards.find(function (planCard) {
          return (
            (planCard.getAttribute("data-plan-cycle") || "monthly") ===
            "monthly"
          );
        }) ||
        matchingCards[0] ||
        null
      );
    }

    function openCheckoutForPlanCard(planCard) {
      var planId;
      var planPath;
      var target;
      var priceStrong;
      var priceEm;
      var priceLabel;

      if (!planCard) {
        return;
      }

      planId = planCard.getAttribute("data-plan-id") || "";
      planPath = planCard.getAttribute("data-member-signup-plan-path") || "";
      if (!planId) {
        return;
      }

      target = q(
        root,
        '[data-member-signup-memberpress="' + escapeSelector(planId) + '"]'
      );
      if (!target) {
        return;
      }

      pulseTransition();
      if (fullNameInput) {
        var nameParts = parseFullName(fullNameInput.value);
        state(root).firstName = nameParts.firstName;
        state(root).lastName = nameParts.lastName;
      }
      if (!state(root).email && emailInput) {
        state(root).email = String(emailInput.value || "").trim();
      }
      setIdentityCookies(state(root));
      qa(root, "[data-member-signup-memberpress]").forEach(function (panel) {
        panel.hidden = panel !== target;
      });
      if (plans) {
        plans.hidden = true;
      }
      if (card) {
        card.hidden = true;
      }
      if (pricing) {
        pricing.hidden = true;
      }
      if (checkout) {
        checkout.hidden = false;
      }
      if (checkoutPrice) {
        priceStrong = q(planCard, ".sffc-member-signup__price strong");
        priceEm = q(planCard, ".sffc-member-signup__price em");
        priceLabel = [
          priceStrong ? priceStrong.textContent.trim() : "",
          priceEm ? priceEm.textContent.trim() : "",
        ]
          .filter(Boolean)
          .join(" ");
        checkoutPrice.textContent = priceLabel;
        checkoutPrice.hidden = priceLabel === "";
      }
      updateCheckoutTrust(planPath || state(root).account || "platform");
      clearMemberPressSubmitting(root);
      fillMemberPressForm(target, state(root));
      refreshMemberPressPaymentUI(target);
      setTimeout(function () {
        fillMemberPressForm(target, state(root));
        refreshMemberPressPaymentUI(target);
      }, 500);
    }

    function updateCheckoutTrust(accountPath) {
      var avatars = q(root, "[data-member-signup-checkout-avatars]");
      var isSingleContact = String(accountPath || "") === "one_contact";

      if (avatars) {
        avatars.hidden = isSingleContact;
      }
    }

    function submitJobCommunityJoin() {
      if (!state(root).email) {
        showError(
          localizedMessages.invalidEmailOnly ||
            "Enter a valid email address to get started."
        );
        return;
      }

      var joinButton = q(root, "[data-member-signup-review-proceed]");
      if (joinButton) {
        joinButton.disabled = true;
        joinButton.textContent = "Joining...";
      }

      var params = new URLSearchParams({
        action: "sffc_member_pricing_join_job_community",
        nonce: (window.sffcMemberPricingSignup || {}).nonce || "",
        email: state(root).email,
        jobs_post_id: jobId,
      });

      uniqueValues(state(root).financeInterest).forEach(function (value) {
        params.append("finance_interest[]", value);
      });
      uniqueValues(state(root).seniorityLevel).forEach(function (value) {
        params.append("seniority_level[]", value);
      });
      uniqueValues(state(root).supportNeed).forEach(function (value) {
        params.append("support_need[]", value);
      });

      fetch(
        (window.sffcMemberPricingSignup || {}).ajaxUrl ||
          "/wp-admin/admin-ajax.php",
        {
          method: "POST",
          credentials: "same-origin",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: params.toString(),
        }
      )
        .then(function (response) {
          return response.json().then(function (payload) {
            return { status: response.status, payload: payload };
          });
        })
        .then(function (result) {
          var payload = result.payload || {};

          if (!payload.success) {
            if (
              payload.data &&
              payload.data.code === "exists" &&
              payload.data.login_url
            ) {
              window.location.href = payload.data.login_url;
              return;
            }

            throw new Error(
              (payload.data && payload.data.message) ||
                localizedMessages.joinFailed ||
                "We could not continue to memberships right now."
            );
          }

          window.location.href =
            (payload.data && payload.data.redirect) ||
            helpUrl ||
            "/memberships/";
        })
        .catch(function (error) {
          showError(
            error && error.message
              ? error.message
              : localizedMessages.joinFailed ||
                  "We could not continue to memberships right now."
          );
          if (joinButton) {
            joinButton.disabled = false;
            joinButton.textContent = "Join MENA Careers";
          }
        });
    }

    function activeBillingCycle() {
      var activeButton = q(root, "[data-member-signup-cycle].is-active");
      return activeButton
        ? activeButton.getAttribute("data-member-signup-cycle") || "monthly"
        : "monthly";
    }

    function setActiveBillingCycle(selectedCycle) {
      qa(root, "[data-member-signup-cycle]").forEach(function (button) {
        button.classList.toggle(
          "is-active",
          !button.hidden &&
            button.getAttribute("data-member-signup-cycle") === selectedCycle
        );
      });
      qa(root, "[data-member-signup-plan-grid]").forEach(function (grid) {
        grid.classList.toggle(
          "is-active",
          grid.getAttribute("data-member-signup-plan-grid") === selectedCycle
        );
      });
    }

    function hasVisiblePlanForCycle(selectedCycle, accountPath) {
      return qa(root, "[data-member-signup-plan-card]").some(function (card) {
        return (
          (card.getAttribute("data-plan-cycle") || "monthly") ===
            selectedCycle &&
          (card.getAttribute("data-member-signup-plan-path") || "platform") ===
            accountPath
        );
      });
    }

    function filterPlansForAccount() {
      var accountPath = state(root).account || "platform";
      var hasMonthly = hasVisiblePlanForCycle("monthly", accountPath);
      var hasAnnual = hasVisiblePlanForCycle("annual", accountPath);
      var selectedCycle = activeBillingCycle();
      var annualToggle = q(root, '[data-member-signup-cycle="annual"]');

      if (
        !hasMonthly &&
        !hasAnnual &&
        accountPath !== "platform"
      ) {
        accountPath = "platform";
        state(root).account = accountPath;
        hasMonthly = hasVisiblePlanForCycle("monthly", accountPath);
        hasAnnual = hasVisiblePlanForCycle("annual", accountPath);
      }

      qa(root, "[data-member-signup-plan-card]").forEach(function (card) {
        card.hidden =
          (card.getAttribute("data-member-signup-plan-path") || "platform") !==
          accountPath;
      });

      if (annualToggle) {
        annualToggle.hidden = !hasAnnual;
      }

      if (selectedCycle === "annual" && !hasAnnual) {
        selectedCycle = "monthly";
      }
      if (selectedCycle === "monthly" && !hasMonthly && hasAnnual) {
        selectedCycle = "annual";
      }
      setActiveBillingCycle(selectedCycle);

      qa(root, "[data-member-signup-plan-grid]").forEach(function (grid) {
        var gridCycle =
          grid.getAttribute("data-member-signup-plan-grid") || "monthly";
        var visibleCards = qa(grid, "[data-member-signup-plan-card]").filter(
          function (card) {
            return !card.hidden;
          }
        );
        var hasMatchingCard = visibleCards.length > 0;
        grid.hidden = !hasMatchingCard;
        grid.classList.toggle("is-single", visibleCards.length === 1);
        grid.classList.toggle(
          "is-active",
          hasMatchingCard && gridCycle === selectedCycle
        );
      });

      updatePlanBadges();
    }

    function recommendationPath() {
      if (
        (hasArrayValue(state(root).supportNeed, "finding-relevant-roles") ||
          hasArrayValue(state(root).supportNeed, "recruiter-visibility")) &&
        (hasArrayValue(state(root).supportNeed, "cv-materials") ||
          hasArrayValue(state(root).supportNeed, "mentorship-guidance"))
      ) {
        return "all_access";
      }
      if (
        hasArrayValue(state(root).supportNeed, "finding-relevant-roles") ||
        hasArrayValue(state(root).supportNeed, "recruiter-visibility")
      ) {
        return "platform";
      }
      return "mentorship";
    }

    function recommendationCopy() {
      return "";
    }

    function summarizeFinanceInterest(value) {
      var mappedValues = uniqueValues(value)
        .map(function (item) {
          if (item === "investment-banking") {
            return "investment banking (PE feeder)";
          }
          if (item === "private-equity") {
            return "private equity";
          }
          if (item === "accounting-finance") {
            return "fund finance";
          }
          if (item === "venture-capital") {
            return "growth equity / venture capital";
          }
          if (item === "asset-management") {
            return "real assets / infrastructure";
          }
          if (item === "consulting") {
            return "portfolio operations";
          }
          if (item === "risk-compliance") {
            return "investor relations / fundraising";
          }
          if (item === "other") {
            return "other private equity paths";
          }
          return "";
        })
        .filter(Boolean);

      return joinSummary(mappedValues) || "your chosen private equity path";
    }

    function summarizeSeniority(value) {
      var mappedValues = uniqueValues(value)
        .map(function (item) {
          if (item === "analyst") {
            return "analyst";
          }
          if (item === "associate") {
            return "associate";
          }
          if (item === "manager") {
            return "manager";
          }
          if (item === "director") {
            return "director";
          }
          return "";
        })
        .filter(Boolean);

      return joinSummary(mappedValues) || "your chosen seniority";
    }

    function summarizeSupport(value) {
      var mappedValues = uniqueValues(value)
        .map(function (item) {
          if (item === "finding-relevant-roles") {
            return "finding relevant roles";
          }
          if (item === "recruiter-visibility") {
            return "getting recruiter visibility";
          }
          if (item === "career-direction") {
            return "clarifying your career direction";
          }
          if (item === "career-pivot") {
            return "application guidance";
          }
          if (item === "cv-materials") {
            return "Career Assessment";
          }
          if (item === "mentorship-guidance") {
            return "mentorship and guidance";
          }
          return "";
        })
        .filter(Boolean);

      return joinSummary(mappedValues) || "MENA Careers support";
    }

    function buildHeardSummary() {
      return (
        "You’re exploring " +
        summarizeFinanceInterest(state(root).financeInterest) +
        " roles at " +
        summarizeSeniority(state(root).seniorityLevel) +
        " level, with the strongest need around " +
        summarizeSupport(state(root).supportNeed) +
        "."
      );
    }

    function nextStepsCopy() {
      return "";
    }

    function planFitCopy(planPath) {
      if (planPath === recommendationPath()) {
        if (planPath === "all_access") {
          return "Best fit if you want recruiter access, ongoing recruiter hiring alerts, and the fullest version of the MENA Careers workflow in one plan.";
        }
        if (planPath === "platform") {
          return "Best fit if you want broader finance opportunities, stronger recruiter visibility, and a more focused search.";
        }
        return "Best fit if you want expert guidance, stronger positioning, and more structured support around your next move.";
      }

      if (planPath === "all_access") {
        return "Best if you want recruiter access plus ongoing recruiter hiring alerts without losing focus on direct outreach.";
      }

      if (planPath === "platform") {
        return "Best if you mainly want lighter-touch access to roles and visibility across the market.";
      }

      return "Best if you want a more guided path with hands-on support beyond a single application.";
    }

    function updateJobRecommendation() {
      var recommendedPath = recommendationPath();
      var financeSummary = q(root, "[data-member-signup-summary-finance]");
      var senioritySummary = q(root, "[data-member-signup-summary-seniority]");
      var supportSummary = q(root, "[data-member-signup-summary-support]");
      var recommendationSummary = q(
        root,
        "[data-member-signup-recommendation-copy]"
      );
      var nextStepsSummary = q(root, "[data-member-signup-next-steps]");

      if (financeSummary) {
        financeSummary.textContent = summarizeFinanceInterest(
          state(root).financeInterest
        );
      }
      if (senioritySummary) {
        senioritySummary.textContent = summarizeSeniority(
          state(root).seniorityLevel
        );
      }
      if (supportSummary) {
        supportSummary.textContent = summarizeSupport(state(root).supportNeed);
      }
      if (recommendationSummary) {
        recommendationSummary.textContent = recommendationCopy();
        recommendationSummary.hidden = recommendationSummary.textContent === "";
      }
      if (nextStepsSummary) {
        nextStepsSummary.textContent = nextStepsCopy();
        nextStepsSummary.hidden = nextStepsSummary.textContent === "";
      }

      qa(root, "[data-member-signup-plan-card]").forEach(function (planCard) {
        var planPath =
          planCard.getAttribute("data-member-signup-plan-path") || "platform";
        var isRecommended = planPath === recommendedPath;
        var badge = q(planCard, "[data-member-signup-recommended-badge]");
        var fit = q(planCard, "[data-member-signup-plan-fit]");
        planCard.classList.toggle("is-recommended", isRecommended);
        if (badge) {
          badge.hidden = !isRecommended;
        }
        if (fit) {
          fit.textContent = planFitCopy(planPath);
          fit.hidden = false;
        }
      });
    }

    function showJobPricing() {
      pulseTransition();
      root.classList.add("is-job-flow-active");
      root.classList.add("is-job-pricing");
      if (onboarding) {
        onboarding.hidden = true;
      }
      if (review) {
        review.hidden = true;
      }
      if (pricing) {
        pricing.hidden = false;
      }
      updateJobRecommendation();
      setStep(2);
    }

    function showJobReview() {
      pulseTransition();
      root.classList.add("is-job-flow-active");
      root.classList.remove("is-job-pricing");
      if (onboarding) {
        onboarding.hidden = true;
      }
      if (pricing) {
        pricing.hidden = true;
      }
      if (review) {
        review.hidden = false;
      }
      populateReview();
      setStep(2);
    }

    if (variant !== "job") {
      filterPlansForAccount();
    }
    setIdentityCookies(state(root));
    syncDefaultEntryState();
    syncEntryStudyCards();
    syncSimpleStudyButtons();
    syncDefaultAccountGroups();
    syncOnboardingState();
    syncIdentityInputs();

    if (form) {
      form.addEventListener("submit", function (event) {
        var fullName = fullNameInput ? fullNameInput.value.trim() : "";
        var nameParts = parseFullName(fullName);
        var email = emailInput ? emailInput.value.trim() : "";
        event.preventDefault();
        showError("");

        if (variant === "job") {
          if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            showError(
              localizedMessages.invalidEmailOnly ||
                "Enter a valid email address to get started."
            );
            return;
          }
        } else if (
          !nameParts.firstName ||
          !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)
        ) {
          showError(
            localizedMessages.invalidIdentity ||
              "Enter your full name and a valid email address."
          );
          return;
        }

        if (variant === "simple") {
          if (!state(root).study) {
            showError(
              localizedMessages.missingStudy ||
                "Choose what you would like help with first."
            );
            return;
          }
          state(root).account = accountForSimpleStudy(state(root).study);
          state(root).accountDetail = state(root).study.replace(/_/g, " ");
          syncSimpleStudyButtons();
        }

        state(root).firstName = nameParts.firstName;
        state(root).lastName = nameParts.lastName;
        state(root).email = email;
        if (variant === "job" && !state(root).firstName) {
          var inferredName = inferNameFromEmail(email);
          state(root).firstName = inferredName.firstName;
          state(root).lastName = inferredName.lastName;
        }
        setIdentityCookies(state(root));
        syncIdentityInputs();

        if (variant === "job") {
          fetch(
            (window.sffcMemberPricingSignup || {}).ajaxUrl ||
              "/wp-admin/admin-ajax.php",
            {
              method: "POST",
              credentials: "same-origin",
              headers: { "Content-Type": "application/x-www-form-urlencoded" },
              body: new URLSearchParams({
                action: "sffc_member_pricing_check_email",
                nonce: (window.sffcMemberPricingSignup || {}).nonce || "",
                email: email,
              }).toString(),
            }
          )
            .then(function (response) {
              return response.json();
            })
            .then(function (payload) {
              if (
                payload &&
                payload.success &&
                payload.data &&
                payload.data.exists
              ) {
                showLogin();
                return;
              }
              showJobRoute();
            })
            .catch(function () {
              showJobRoute();
            });
          return;
        }

        fetch(
          (window.sffcMemberPricingSignup || {}).ajaxUrl ||
            "/wp-admin/admin-ajax.php",
          {
            method: "POST",
            credentials: "same-origin",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: new URLSearchParams({
              action: "sffc_member_pricing_check_email",
              nonce: (window.sffcMemberPricingSignup || {}).nonce || "",
              email: email,
            }).toString(),
          }
        )
          .then(function (response) {
            return response.json();
          })
          .then(function (payload) {
            if (
              payload &&
              payload.success &&
              payload.data &&
              payload.data.exists
            ) {
              showLogin();
              return;
            }
            if (variant === "recruiter_contact") {
              pulseTransition();
              state(root).study = "recruiter_contacts";
              state(root).account = "";
              state(root).accountDetail = "";
              syncEntryStudyCards();
              syncDefaultAccountGroups();
              syncDefaultEntryState();
              if (card && typeof card.scrollIntoView === "function") {
                card.scrollIntoView({ behavior: "smooth", block: "start" });
              }
              return;
            }
            showDefaultPlans();
          })
          .catch(function () {
            if (variant === "recruiter_contact") {
              pulseTransition();
              state(root).study = "recruiter_contacts";
              state(root).account = "";
              state(root).accountDetail = "";
              syncEntryStudyCards();
              syncDefaultAccountGroups();
              syncDefaultEntryState();
              if (card && typeof card.scrollIntoView === "function") {
                card.scrollIntoView({ behavior: "smooth", block: "start" });
              }
              return;
            }
            showDefaultPlans();
          });
      });
    }

    root.addEventListener("click", function (event) {
      var meprSubmit = event.target.closest(
        ".mepr-submit, .mp-form-submit input[type='submit'], .mp-form-submit button, button[type='submit'], input[type='submit']"
      );
      var study = event.target.closest("[data-member-signup-study]");
      var externalLink = event.target.closest(
        "[data-member-signup-external-link]"
      );
      var account = event.target.closest("[data-member-signup-account]");
      var cycle = event.target.closest("[data-member-signup-cycle]");
      var choose = event.target.closest("[data-member-signup-choose-plan]");
      var back = event.target.closest("[data-member-signup-back-to-plans]");
      var financeInterest = event.target.closest(
        "[data-member-signup-finance-interest]"
      );
      var seniority = event.target.closest("[data-member-signup-seniority]");
      var supportNeed = event.target.closest(
        "[data-member-signup-support-need]"
      );
      var onboardingNext = event.target.closest(
        "[data-member-signup-onboarding-next]"
      );
      var onboardingBack = event.target.closest(
        "[data-member-signup-onboarding-back]"
      );
      var onboardingComplete = event.target.closest(
        "[data-member-signup-onboarding-complete]"
      );
      var reviewBack = event.target.closest("[data-member-signup-review-back]");
      var reviewEdit = event.target.closest("[data-member-signup-review-edit]");
      var reviewProceed = event.target.closest(
        "[data-member-signup-review-proceed]"
      );
      var editOnboarding = event.target.closest(
        "[data-member-signup-edit-onboarding]"
      );
      var viewDescription = event.target.closest(
        "[data-member-signup-view-description]"
      );
      var showMethod = event.target.closest("[data-member-signup-show-method]");
      var entryContinue = event.target.closest(
        "[data-member-signup-entry-continue]"
      );
      var simpleStudy = event.target.closest(
        "[data-member-signup-simple-study]"
      );
      var routeModalClose = event.target.closest(
        "[data-member-signup-route-modal-close]"
      );
      var routeModalCopy = event.target.closest(
        "[data-member-signup-route-modal-copy]"
      );
      var hideDescription = event.target.closest(
        "[data-member-signup-hide-description]"
      );
      var brandAccountToggle = event.target.closest(
        "[data-member-signup-brand-account-toggle]"
      );
      if (
        meprSubmit &&
        root.contains(meprSubmit) &&
        meprSubmit.closest("[data-member-signup-memberpress]")
      ) {
        markMemberPressSubmitting(meprSubmit);
        return;
      }

      if (brandAccountToggle && root.contains(brandAccountToggle)) {
        event.preventDefault();
        event.stopPropagation();
        toggleBrandAccountMenu(brandAccountToggle);
        return;
      }

      if (study && root.contains(study)) {
        event.preventDefault();
        pulseTransition();
        state(root).study =
          study.getAttribute("data-member-signup-study") || "";
        state(root).account = "";
        state(root).accountDetail = "";
        qa(root, "[data-member-signup-study]").forEach(function (button) {
          button.classList.toggle(
            "is-active",
            (button.getAttribute("data-member-signup-study") || "") ===
              state(root).study
          );
        });
        syncEntryStudyCards();
        syncDefaultAccountGroups();
        resetDefaultPlans();
        syncDefaultEntryState();
        setIdentityCookies(state(root));
        syncIdentityInputs();
        return;
      }

      if (externalLink && root.contains(externalLink)) {
        var fallbackPanel = q(
          externalLink.closest(".sffc-member-signup__plan"),
          "[data-member-signup-external-fallback]"
        );
        if (fallbackPanel) {
          fallbackPanel.hidden = false;
        }
        trackExternalApply(externalLink);
        return;
      }

      if (viewDescription && root.contains(viewDescription)) {
        event.preventDefault();
        if (jobDescription) {
          jobDescription.hidden = false;
          window.requestAnimationFrame(function () {
            if (typeof jobDescription.scrollIntoView === "function") {
              jobDescription.scrollIntoView({
                behavior: "smooth",
                block: "start",
              });
            }
          });
        } else {
          var fallbackDescriptionTarget = findExistingJobDescriptionTarget();
          if (
            fallbackDescriptionTarget &&
            typeof fallbackDescriptionTarget.scrollIntoView === "function"
          ) {
            fallbackDescriptionTarget.scrollIntoView({
              behavior: "smooth",
              block: "start",
            });
          }
        }
        return;
      }

      if (hideDescription && root.contains(hideDescription)) {
        event.preventDefault();
        if (jobDescription) {
          jobDescription.hidden = true;
        }
        if (
          routeStep &&
          !routeStep.hidden &&
          typeof card.scrollIntoView === "function"
        ) {
          card.scrollIntoView({ behavior: "smooth", block: "start" });
        }
        return;
      }

      if (showMethod && root.contains(showMethod)) {
        event.preventDefault();
        if (
          (showMethod.getAttribute("data-member-signup-show-method") || "") ===
          "help"
        ) {
          try {
            var openedWindow = window.open(membershipUrl, "_blank", "noopener");
            if (openedWindow) {
              openedWindow.opener = null;
            }
          } catch (error) {}
          showRouteModal();
        }
        return;
      }

      if (routeModalClose && root.contains(routeModalClose)) {
        event.preventDefault();
        hideRouteModal();
        return;
      }

      if (routeModalCopy && root.contains(routeModalCopy)) {
        event.preventDefault();
        copyRouteMembershipLink()
          .then(function () {
            showRouteModalFeedback("Membership link copied.");
          })
          .catch(function () {
            showRouteModalFeedback("Copy failed. Use /memberships/.");
          });
        return;
      }

      if (account && root.contains(account)) {
        event.preventDefault();
        pulseTransition();
        state(root).account =
          account.getAttribute("data-member-signup-account") || "";
        state(root).accountDetail = account.textContent
          ? account.textContent.trim()
          : "";
        qa(root, "[data-member-signup-account]").forEach(function (button) {
          button.classList.toggle("is-active", button === account);
        });
        if (variant === "recruiter_contact") {
          openCheckoutForPlanCard(findPlanCardForAccount(state(root).account));
        } else {
          updateDefaultPlanSummary();
          syncDefaultEntryState();
        }
        if (card && typeof card.scrollIntoView === "function") {
          card.scrollIntoView({ behavior: "smooth", block: "start" });
        }
        return;
      }

      if (simpleStudy && root.contains(simpleStudy)) {
        event.preventDefault();
        state(root).study =
          simpleStudy.getAttribute("data-member-signup-simple-study") || "";
        state(root).account = accountForSimpleStudy(state(root).study);
        state(root).accountDetail = simpleStudy.textContent
          ? simpleStudy.textContent.trim()
          : "";
        syncSimpleStudyButtons();
        setIdentityCookies(state(root));
        syncIdentityInputs();
        showError("");
        return;
      }

      if (entryContinue && root.contains(entryContinue)) {
        event.preventDefault();
        pulseTransition();
        state(root).study =
          entryContinue.getAttribute("data-member-signup-entry-continue") ||
          "recruiter_contacts";
        state(root).account = "";
        state(root).accountDetail = "";
        syncEntryStudyCards();
        syncDefaultAccountGroups();
        resetDefaultPlans();
        syncDefaultEntryState();
        setIdentityCookies(state(root));
        syncIdentityInputs();
        if (card && typeof card.scrollIntoView === "function") {
          card.scrollIntoView({ behavior: "smooth", block: "start" });
        }
        return;
      }

      var entryStudy = event.target.closest("[data-member-signup-entry-study]");
      if (entryStudy && root.contains(entryStudy)) {
        event.preventDefault();
        pulseTransition();
        state(root).study =
          entryStudy.getAttribute("data-member-signup-entry-study") || "";
        state(root).account = "";
        state(root).accountDetail = "";
        syncEntryStudyCards();
        syncDefaultAccountGroups();
        resetDefaultPlans();
        syncDefaultEntryState();
        setIdentityCookies(state(root));
        syncIdentityInputs();
        if (card && typeof card.scrollIntoView === "function") {
          card.scrollIntoView({ behavior: "smooth", block: "start" });
        }
        return;
      }

      if (financeInterest && root.contains(financeInterest)) {
        event.preventDefault();
        state(root).financeInterest = toggleArrayValue(
          state(root).financeInterest,
          financeInterest.getAttribute("data-member-signup-finance-interest") ||
            "",
          3
        );
        setIdentityCookies(state(root));
        syncOnboardingState();
        return;
      }

      if (seniority && root.contains(seniority)) {
        event.preventDefault();
        state(root).seniorityLevel = toggleArrayValue(
          state(root).seniorityLevel,
          seniority.getAttribute("data-member-signup-seniority") || "",
          2
        );
        setIdentityCookies(state(root));
        syncOnboardingState();
        return;
      }

      if (supportNeed && root.contains(supportNeed)) {
        event.preventDefault();
        state(root).supportNeed = toggleArrayValue(
          state(root).supportNeed,
          supportNeed.getAttribute("data-member-signup-support-need") || "",
          3
        );
        setIdentityCookies(state(root));
        syncOnboardingState();
        return;
      }

      if (onboardingBack && root.contains(onboardingBack)) {
        event.preventDefault();
        showOnboardingStep(
          onboardingBack.getAttribute("data-member-signup-onboarding-back") ||
            "finance-interest"
        );
        return;
      }

      if (onboardingNext && root.contains(onboardingNext)) {
        event.preventDefault();
        showOnboardingStep(
          onboardingNext.getAttribute("data-member-signup-onboarding-next") ||
            "finance-interest"
        );
        syncOnboardingState();
        return;
      }

      if (onboardingComplete && root.contains(onboardingComplete)) {
        event.preventDefault();
        setIdentityCookies(state(root));
        showJobReview();
        return;
      }

      if (reviewBack && root.contains(reviewBack)) {
        event.preventDefault();
        if (review) {
          review.hidden = true;
        }
        if (onboarding) {
          onboarding.hidden = false;
        }
        showOnboardingStep("support");
        setStep(2);
        return;
      }

      if (reviewEdit && root.contains(reviewEdit)) {
        event.preventDefault();
        if (review) {
          review.hidden = true;
        }
        if (onboarding) {
          onboarding.hidden = false;
        }
        showOnboardingStep("finance-interest");
        setStep(2);
        return;
      }

      if (reviewProceed && root.contains(reviewProceed)) {
        event.preventDefault();
        submitJobCommunityJoin();
        return;
      }

      if (editOnboarding && root.contains(editOnboarding)) {
        event.preventDefault();
        root.classList.remove("is-job-pricing");
        if (pricing) {
          pricing.hidden = true;
        }
        if (review) {
          review.hidden = true;
        }
        if (onboarding) {
          onboarding.hidden = false;
        }
        showOnboardingStep("finance-interest");
        setStep(2);
        return;
      }

      if (cycle && root.contains(cycle)) {
        var selectedCycle =
          cycle.getAttribute("data-member-signup-cycle") || "monthly";
        event.preventDefault();
        if (
          cycle.hidden ||
          !hasVisiblePlanForCycle(
            selectedCycle,
            state(root).account || "platform"
          )
        ) {
          return;
        }
        setActiveBillingCycle(selectedCycle);
        filterPlansForAccount();
        return;
      }

      if (choose && root.contains(choose)) {
        var planId =
          choose.getAttribute("data-member-signup-choose-plan") || "";
        var planCard = choose.closest("[data-member-signup-plan-card]");
        event.preventDefault();
        if (!planCard && planId) {
          planCard = q(
            root,
            '[data-plan-id="' + escapeSelector(planId) + '"]'
          );
        }
        openCheckoutForPlanCard(planCard);
        return;
      }

      if (back && root.contains(back)) {
        event.preventDefault();
        pulseTransition();
        if (checkout) {
          checkout.hidden = true;
        }
        if (checkoutPrice) {
          checkoutPrice.textContent = "";
          checkoutPrice.hidden = true;
        }
        if (plans) {
          plans.hidden = false;
        }
        if (card && variant !== "job") {
          card.hidden = true;
        }
        if (pricing && variant === "job") {
          pricing.hidden = false;
          updateJobRecommendation();
        }
        if (variant !== "job") {
          filterPlansForAccount();
        }
        clearMemberPressSubmitting(root);
      }
    });

    document.addEventListener("click", function (event) {
      if (
        brandAccountMenu &&
        !brandAccountMenu.hidden &&
        root.contains(brandAccountMenu) &&
        !event.target.closest("[data-member-signup-brand-account]")
      ) {
        closeBrandAccountMenu();
      }

      if (!routeModal || routeModal.hidden || !root.contains(event.target)) {
        return;
      }

      var routeGroup = event.target.closest(
        ".sffc-member-signup__route-option-group"
      );
      if (routeGroup && routeGroup.contains(routeModal)) {
        return;
      }

      if (
        !event.target.closest("[data-member-signup-show-method]") &&
        !event.target.closest("[data-member-signup-route-modal]")
      ) {
        hideRouteModal();
      }
    });

    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape") {
        closeBrandAccountMenu();
        hideRouteModal();
      }
    });

    root.addEventListener(
      "submit",
      function (event) {
        if (
          event.target &&
          event.target.closest("[data-member-signup-memberpress]")
        ) {
          markMemberPressSubmitting(event.target);
        }
      },
      true
    );
  }

  function initJobSignupOverlayRoot(root) {
    if (!root || root._sffcJobSignupOverlayInitialized) {
      return;
    }
    root._sffcJobSignupOverlayInitialized = true;

    var overlay = q(root, "[data-sffc-job-signup-overlay]");
    var overlayBody = q(root, "[data-sffc-job-signup-overlay-body]");
    var signupConfig = window.sffcMemberPricingSignup || {};
    var overlayMessages = signupConfig.messages || {};
    var ajaxUrl = signupConfig.ajaxUrl || "/wp-admin/admin-ajax.php";
    var nonce = signupConfig.nonce || "";
    var overlayAction =
      signupConfig.overlayAction || "sffc_load_job_member_signup_overlay";
    var cache = {};
    var activeOverlayKey = "";
    var loadingOverlayKey = "";

    if (!overlay || !overlayBody) {
      return;
    }

    function setOverlayOpen(isOpen) {
      overlay.hidden = !isOpen;
      document.documentElement.classList.toggle(
        "has-sffc-job-signup-overlay",
        isOpen
      );
      document.body.classList.toggle("has-sffc-job-signup-overlay", isOpen);
    }

    function closeOverlay() {
      setOverlayOpen(false);
    }

    function findOverlaySourceEmail(trigger) {
      var scope = trigger
        ? trigger.closest(".sffc-cv-match-studio__job-post-card")
        : null;
      var input = q(
        scope || root,
        ".sffc-cv-match-studio__job-post-joinform input[name='email'], .sffc-cv-match-studio__job-post-joininput"
      );
      var email = input ? String(input.value || "").trim() : "";

      return isValidEmail(email) ? email : "";
    }

    function hydrateOverlayEmail(email) {
      var signupRoot;
      var emailInput;
      var fullNameInput;
      var inferredName;

      if (!isValidEmail(email)) {
        return;
      }

      signupRoot = q(overlayBody, "[data-member-signup]");
      emailInput = q(overlayBody, "[data-member-signup-email]");
      fullNameInput = q(overlayBody, "[data-member-signup-full-name]");
      if (!signupRoot || !emailInput) {
        return;
      }

      emailInput.value = email;
      emailInput.dispatchEvent(new Event("input", { bubbles: true }));
      emailInput.dispatchEvent(new Event("change", { bubbles: true }));

      state(signupRoot).email = email;
      if (!state(signupRoot).firstName) {
        inferredName = inferNameFromEmail(email);
        state(signupRoot).firstName = inferredName.firstName;
        state(signupRoot).lastName = inferredName.lastName;
        if (fullNameInput && !String(fullNameInput.value || "").trim()) {
          fullNameInput.value = [inferredName.firstName, inferredName.lastName]
            .filter(Boolean)
            .join(" ")
            .trim();
        }
      }
      setIdentityCookies(state(signupRoot));
    }

    function getOverlayRequest(openButton) {
      var request = {
        jobsPostId: String(
          openButton.getAttribute("data-sffc-job-signup-id") || ""
        ).trim(),
        wpPostId: String(
          openButton.getAttribute("data-sffc-job-signup-wp-id") || ""
        ).trim(),
        crmPostId: String(
          openButton.getAttribute("data-sffc-job-signup-crm-id") || ""
        ).trim(),
      };

      request.cacheKey = [
        request.jobsPostId || "0",
        request.wpPostId || "0",
        request.crmPostId || "0",
      ].join(":");

      return request;
    }

    function loadOverlay(request) {
      if (
        !request ||
        (!request.jobsPostId && !request.wpPostId && !request.crmPostId)
      ) {
        return Promise.reject(new Error("missing_job_id"));
      }
      if (cache[request.cacheKey]) {
        return typeof cache[request.cacheKey].then === "function"
          ? cache[request.cacheKey]
          : Promise.resolve(cache[request.cacheKey]);
      }

      cache[request.cacheKey] = fetch(ajaxUrl, {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams({
          action: overlayAction,
          nonce: nonce,
          jobs_post_id: request.jobsPostId,
          wp_post_id: request.wpPostId,
          crm_post_id: request.crmPostId,
        }).toString(),
      })
        .then(function (response) {
          return response.json();
        })
        .then(function (payload) {
          if (
            !payload ||
            !payload.success ||
            !payload.data ||
            !payload.data.html
          ) {
            throw new Error(
              (payload && payload.data && payload.data.message) ||
                overlayMessages.overlayLoadFailed ||
                "overlay_load_failed"
            );
          }
          cache[request.cacheKey] = payload.data.html;
          return payload.data.html;
        })
        .catch(function (error) {
          delete cache[request.cacheKey];
          throw error;
        });

      return cache[request.cacheKey];
    }

    function maybePrefetchOverlay(request) {
      if (
        !request ||
        !request.cacheKey ||
        (!request.jobsPostId && !request.wpPostId && !request.crmPostId) ||
        cache[request.cacheKey] ||
        loadingOverlayKey === request.cacheKey
      ) {
        return;
      }

      loadOverlay(request).catch(function () {});
    }

    root.addEventListener("click", function (event) {
      var openButton = event.target.closest(
        "[data-sffc-job-signup-overlay-open]"
      );
      var closeButton = event.target.closest(
        "[data-sffc-job-signup-overlay-close]"
      );

      if (closeButton && root.contains(closeButton)) {
        event.preventDefault();
        closeOverlay();
        return;
      }

      if (!openButton || !root.contains(openButton)) {
        return;
      }

      event.preventDefault();
      var overlayRequest = getOverlayRequest(openButton);
      var sourceEmail = findOverlaySourceEmail(openButton);

      if (
        overlayRequest.cacheKey &&
        activeOverlayKey === overlayRequest.cacheKey &&
        overlayBody.children.length
      ) {
        hydrateOverlayEmail(sourceEmail);
        setOverlayOpen(true);
        return;
      }

      if (loadingOverlayKey === overlayRequest.cacheKey) {
        setOverlayOpen(true);
        return;
      }

      loadingOverlayKey = overlayRequest.cacheKey;
      overlayBody.innerHTML =
        '<div class="sffc-member-signup-overlay__loading">Loading...</div>';
      setOverlayOpen(true);

      loadOverlay(overlayRequest)
        .then(function (html) {
          overlayBody.innerHTML = html;
          executeEmbeddedScripts(overlayBody);
          return rerunMemberPressOverlayInitializers(overlayBody).then(
            function () {
              qa(overlayBody, "[data-member-signup]").forEach(init);
              hydrateOverlayEmail(sourceEmail);
              qa(overlayBody, "[data-member-signup-memberpress]").forEach(
                refreshMemberPressPaymentUI
              );
              activeOverlayKey = overlayRequest.cacheKey;
              loadingOverlayKey = "";
            }
          );
        })
        .catch(function (error) {
          loadingOverlayKey = "";
          overlayBody.innerHTML =
            '<div class="sffc-member-signup-overlay__error">' +
            (error && error.message
              ? error.message
              : overlayMessages.overlayLoadFailed || "Unable to load.") +
            "</div>";
        });
    });

    root.addEventListener(
      "touchstart",
      function (event) {
        var openButton = event.target.closest(
          "[data-sffc-job-signup-overlay-open]"
        );
        if (!openButton || !root.contains(openButton)) {
          return;
        }

        maybePrefetchOverlay(getOverlayRequest(openButton));
      },
      { passive: true }
    );

    root.addEventListener("focusin", function (event) {
      var openButton = event.target.closest(
        "[data-sffc-job-signup-overlay-open]"
      );
      if (!openButton || !root.contains(openButton)) {
        return;
      }

      maybePrefetchOverlay(getOverlayRequest(openButton));
    });

    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape" && !overlay.hidden) {
        closeOverlay();
      }
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    qa(document, "[data-member-signup]").forEach(init);
    qa(document, "[data-sffc-job-signup-overlay-root]").forEach(
      initJobSignupOverlayRoot
    );
  });
})();
