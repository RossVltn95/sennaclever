(function () {
  "use strict";

  var redditConfig = window.sffcCrmRedditFeed || {};
  var dashboardTabCache = Object.create(null);
  var dashboardTopbarCache = Object.create(null);
  var draggingGroupItem = null;
  var gapLoadingTimers = [];
  var gapLoadingCountdownTimer = null;
  var GUIDED_ENTRY_CONTEXT_KEY = "sffcGuidedPricingEntryContext";
  var upgradeCaptureEmailTimer = null;

  function escapeHtml(value) {
    return String(value == null ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function escapeSelector(value) {
    if (window.CSS && typeof window.CSS.escape === "function") {
      return window.CSS.escape(value);
    }

    return String(value || "").replace(/["\\]/g, "\\$&");
  }

  function redirectToMembership() {
    window.location.href = redditConfig.membershipUrl || "/memberships/";
  }

  function normalizePathname(pathname) {
    var normalized = String(pathname || "").trim();
    if (!normalized) {
      return "/";
    }
    normalized = normalized.replace(/\/+$/, "");
    return normalized || "/";
  }

  function isMembershipUrl(url) {
    if (!url) {
      return false;
    }

    try {
      var membershipUrl = new URL(
        redditConfig.membershipUrl || "/memberships/",
        window.location.origin
      );
      var candidateUrl = new URL(url, window.location.origin);
      return (
        normalizePathname(candidateUrl.pathname) ===
          normalizePathname(membershipUrl.pathname) &&
        candidateUrl.origin === membershipUrl.origin
      );
    } catch (error) {
      return String(url).indexOf("/memberships") !== -1;
    }
  }

  function parseJsonAttribute(node, attributeName) {
    if (!node || !node.getAttribute) {
      return {};
    }

    var raw = node.getAttribute(attributeName) || "";
    if (!raw) {
      return {};
    }

    try {
      var parsed = JSON.parse(raw);
      return parsed && typeof parsed === "object" ? parsed : {};
    } catch (error) {
      return {};
    }
  }

  function getUpgradeCaptureModal(trigger) {
    var shell =
      trigger && trigger.closest
        ? trigger.closest(".sffc-crm-reddit-shell")
        : null;
    return shell ? shell.querySelector("[data-upgrade-capture-modal]") : null;
  }

  function buildGuidedEntryContext(intent, context) {
    var base = context && typeof context === "object" ? context : {};
    var roleTitle = String(base.roleTitle || "").trim() || "this role";
    var companyName = String(base.companyName || "").trim() || "the firm";
    var recruiterName =
      String(base.recruiterName || "").trim() || "the recruiter";
    var resolved = {
      intent: intent || "generic",
      source: String(base.source || intent || "single_job").trim(),
      roleTitle: String(base.roleTitle || "").trim(),
      companyName: String(base.companyName || "").trim(),
      recruiterName: String(base.recruiterName || "").trim(),
    };

    if (intent === "recruiter_contact") {
      resolved.headline =
        "Join MENA Careers & access the recruiter contact and hiring guide for " +
        recruiterName +
        " @ " +
        companyName;
      resolved.subheadline =
        "Direct recruiter visibility, better timing, and stronger positioning can materially improve whether your application gets noticed.";
      resolved.suggestedHelp = "get_recruiter_attention";
      return resolved;
    }

    if (intent === "application_pack") {
      resolved.headline =
        "Join MENA Careers & access tailored materials for " +
        roleTitle +
        " at " +
        companyName;
      resolved.subheadline =
        "Tailored CVs, cover letters, interview prep, and hiring guides help experienced finance professionals apply with stronger role-specific proof and fewer avoidable mistakes.";
      resolved.suggestedHelp = "fix_cv_before_apply";
      resolved.suggestedConstraint = "no_materials";
      resolved.suggestedDeliverable = "application_pack";
      return resolved;
    }

    if (intent === "networking") {
      resolved.headline =
        "Join MENA Careers & access the networking strategy for " +
        roleTitle +
        " at " +
        companyName;
      resolved.subheadline =
        "A stronger recruiter strategy helps you target the right people, use the right message, and improve your chances of getting noticed before and after you apply.";
      resolved.suggestedHelp = "get_recruiter_attention";
      resolved.suggestedDeliverable = "recruiter_route";
      return resolved;
    }

    if (intent === "cv_report") {
      resolved.headline =
        "Join MENA Careers & unlock the CV report for " +
        roleTitle +
        " at " +
        companyName;
      resolved.subheadline =
        "Seeing where your CV is too broad, missing proof, or underselling ownership is one of the fastest ways to improve shortlist chances before you apply.";
      resolved.suggestedHelp = "fix_cv_before_apply";
      resolved.suggestedConstraint = "no_interviews";
      resolved.suggestedDeliverable = "cv_rewrite";
      return resolved;
    }

    if (intent === "keyword_unlock") {
      resolved.headline =
        "Join MENA Careers & unlock the strongest keywords for " +
        roleTitle +
        " at " +
        companyName;
      resolved.subheadline =
        "Seeing the highest-value role keywords helps you tighten your CV around the signals recruiters, hiring managers, and ATS screens are already rewarding.";
      resolved.suggestedHelp = "fix_cv_before_apply";
      resolved.suggestedConstraint = "generic_positioning";
      resolved.suggestedDeliverable = "cv_rewrite";
      return resolved;
    }

    resolved.headline =
      "Join MENA Careers & tailor your profile to get hired for " +
      roleTitle +
      " at " +
      companyName;
    resolved.subheadline =
      "A more tailored route helps MENA Careers recommend the right support around this role before you commit to anything.";
    return resolved;
  }

  function renderUpgradeCapturePreview(context) {
    var preview =
      context && context.preview && typeof context.preview === "object"
        ? context.preview
        : {};
    var previewType = String(
      context && context.previewType ? context.previewType : ""
    ).trim();

    if (previewType === "recruiter") {
      var recruiterName = String(
        preview.name || context.recruiterName || "Recruiter"
      ).trim();
      var recruiterFirm = String(
        preview.firm || context.companyName || ""
      ).trim();
      var recruiterEmailMasked = String(preview.emailMasked || "").trim();
      var recruiterPhoto = String(preview.photo || "").trim();
      var recruiterInitial = recruiterName
        ? recruiterName.charAt(0).toUpperCase()
        : "R";
      return [
        '<div class="sffc-crm-reddit-upgrade-capture-hero sffc-crm-reddit-upgrade-capture-hero--inline sffc-crm-reddit-upgrade-capture-hero--recruiter">',
        '<div class="sffc-crm-reddit-upgrade-capture-recruiter-row">',
        '<span class="sffc-crm-reddit-upgrade-capture-avatar">',
        recruiterPhoto
          ? '<img src="' + escapeHtml(recruiterPhoto) + '" alt="">'
          : "<span>" + escapeHtml(recruiterInitial) + "</span>",
        "</span>",
        '<span class="sffc-crm-reddit-upgrade-capture-recruiter-copy">',
        '<span class="sffc-crm-reddit-upgrade-capture-recruiter-heading"><strong>' +
          escapeHtml(recruiterName) +
          '</strong><span class="sffc-crm-reddit-upgrade-capture-recruiter-badge">Recruiter</span></span>',
        "<span>" +
          escapeHtml(
            "@" +
              recruiterFirm +
              (recruiterEmailMasked ? " · " + recruiterEmailMasked : "")
          ) +
          "</span>",
        "</span>",
        "</div>",
        "</div>",
      ].join("");
    }

    if (previewType === "materials") {
      var materialsItems = Array.isArray(preview.items) ? preview.items : [];
      return [
        '<div class="sffc-crm-reddit-upgrade-capture-hero sffc-crm-reddit-upgrade-capture-hero--inline">',
        '<div class="sffc-crm-reddit-upgrade-capture-inline-pills">',
        materialsItems
          .map(function (item) {
            return (
              '<span class="sffc-crm-reddit-upgrade-capture-pill">' +
              escapeHtml(item.label || "Material") +
              "</span>"
            );
          })
          .join(""),
        "</div>",
        "</div>",
      ].join("");
    }

    if (
      previewType === "keywords" ||
      previewType === "diagnostic" ||
      previewType === "route"
    ) {
      var chartItems = Array.isArray(preview.items) ? preview.items : [];
      return [
        '<div class="sffc-crm-reddit-upgrade-capture-hero sffc-crm-reddit-upgrade-capture-hero--inline">',
        '<div class="sffc-crm-reddit-upgrade-capture-inline-metrics">',
        chartItems
          .map(function (item) {
            var value = Math.max(
              18,
              Math.min(100, parseInt(item.value || 0, 10) || 0)
            );
            return [
              '<div class="sffc-crm-reddit-upgrade-capture-inline-metric">',
              "<strong>" + escapeHtml(item.label || "") + "</strong>",
              '<span class="sffc-crm-reddit-upgrade-capture-inline-metric-bar"><span style="width:' +
                value +
                '%"></span></span>',
              "</div>",
            ].join("");
          })
          .join(""),
        "</div>",
        "</div>",
      ].join("");
    }

    return "";
  }

  function renderUpgradeCaptureSubtitle(context) {
    var leadText = String(
      context && context.leadText ? context.leadText : ""
    ).trim();
    var subheadline = String(
      context && context.subheadline ? context.subheadline : ""
    ).trim();
    if (!subheadline) {
      return "";
    }
    if (!leadText) {
      return escapeHtml(subheadline);
    }
    if (subheadline.indexOf(leadText) === 0) {
      var remainder = subheadline.slice(leadText.length).trim();
      return (
        '<strong class="sffc-crm-reddit-upgrade-capture-lead">' +
        escapeHtml(leadText) +
        "</strong>" +
        (remainder ? " " + escapeHtml(remainder) : "")
      );
    }
    return (
      '<strong class="sffc-crm-reddit-upgrade-capture-lead">' +
      escapeHtml(leadText) +
      "</strong> " +
      escapeHtml(subheadline)
    );
  }

  function setUpgradeCaptureFeedback(modal, message, isError) {
    if (!modal) {
      return;
    }
    var feedback = modal.querySelector("[data-upgrade-capture-feedback]");
    if (!feedback) {
      return;
    }
    var text = String(message || "").trim();
    if (!text) {
      feedback.hidden = true;
      feedback.textContent = "";
      feedback.classList.remove("is-error");
      return;
    }
    feedback.hidden = false;
    feedback.textContent = text;
    feedback.classList.toggle("is-error", !!isError);
  }

  function updateUpgradeCaptureAccountState(modal, state) {
    if (!modal) {
      return;
    }
    var loginState = modal.querySelector("[data-upgrade-capture-login-state]");
    var loginTitle = modal.querySelector("[data-upgrade-capture-login-title]");
    var loginCopy = modal.querySelector("[data-upgrade-capture-login-copy]");
    var loginLink = modal.querySelector("[data-upgrade-capture-login]");
    var submitButton = modal.querySelector("[data-upgrade-capture-submit]");
    var formLabel = modal.querySelector("[data-upgrade-capture-form-label]");
    var context = modal.__upgradeCaptureContext || {};
    if (!loginState || !submitButton) {
      return;
    }

    if (state && state.exists) {
      loginState.hidden = false;
      if (loginTitle) {
        loginTitle.textContent = "Your MENA Careers account already exists.";
      }
      if (loginCopy) {
        loginCopy.textContent =
          (state.displayName ? state.displayName + ", " : "") +
          "log in to continue with this route and keep everything tied to your existing account.";
      }
      if (loginLink) {
        loginLink.href =
          state.loginUrl || modal.getAttribute("data-login-url") || "/login/";
      }
      submitButton.hidden = true;
      if (formLabel) {
        formLabel.textContent =
          context.loginPrompt ||
          "Already joined MENA Careers? Log in to continue with this route.";
      }
      setUpgradeCaptureFeedback(modal, "", false);
    } else {
      loginState.hidden = true;
      submitButton.hidden = false;
      if (formLabel) {
        formLabel.textContent = context.formLabel || "Join MENA Careers to continue";
      }
    }
  }

  function checkUpgradeCaptureEmail(modal, email) {
    if (
      !modal ||
      !redditConfig.ajaxUrl ||
      !redditConfig.crmNonce ||
      typeof window.fetch !== "function"
    ) {
      return Promise.resolve(null);
    }
    var normalizedEmail = String(email || "").trim();
    if (!normalizedEmail || normalizedEmail.indexOf("@") === -1) {
      updateUpgradeCaptureAccountState(modal, { exists: false });
      return Promise.resolve({ exists: false, valid: false });
    }

    if (
      modal.__upgradeCaptureEmailLookup === normalizedEmail &&
      modal.__upgradeCaptureEmailState
    ) {
      updateUpgradeCaptureAccountState(modal, modal.__upgradeCaptureEmailState);
      return Promise.resolve(modal.__upgradeCaptureEmailState);
    }

    var body = new URLSearchParams();
    body.append("action", "sffc_crm_reddit_check_account_email");
    body.append("nonce", redditConfig.crmNonce);
    body.append("email", normalizedEmail);
    body.append(
      "redirect_to",
      modal.getAttribute("data-membership-url") ||
        redditConfig.membershipUrl ||
        "/memberships/"
    );

    return window
      .fetch(redditConfig.ajaxUrl, {
        method: "POST",
        credentials: "same-origin",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
        },
        body: body.toString(),
      })
      .then(function (response) {
        return response.json();
      })
      .then(function (payload) {
        var state =
          payload && payload.success && payload.data
            ? payload.data
            : { exists: false };
        modal.__upgradeCaptureEmailLookup = normalizedEmail;
        modal.__upgradeCaptureEmailState = state;
        updateUpgradeCaptureAccountState(modal, state);
        return state;
      })
      .catch(function () {
        return { exists: false, valid: true };
      });
  }

  function inferUpgradeIntent(trigger) {
    if (!trigger) {
      return "generic";
    }

    var text = String(trigger.textContent || "").toLowerCase();

    if (
      trigger.closest("[data-single-networking-modal]") ||
      trigger.classList.contains("sffc-crm-reddit-networking-action") ||
      trigger.classList.contains("sffc-crm-reddit-networking-start-btn")
    ) {
      return "networking";
    }

    if (
      trigger.closest(".sffc-crm-reddit-single-pack-card") ||
      trigger.classList.contains("sffc-crm-reddit-single-pack-cta") ||
      /application pack|cover letter|interview questions|hiring guide|tailored materials|cv template/.test(
        text
      )
    ) {
      return "application_pack";
    }

    if (
      trigger.classList.contains(
        "sffc-crm-reddit-single-analysis-recruiter-action"
      ) ||
      trigger.classList.contains(
        "sffc-crm-reddit-single-apply-choice-action"
      ) ||
      /recruiter|referral|linkedin|contact details|email recruiter|contact /.test(
        text
      )
    ) {
      return "recruiter_contact";
    }

    if (/cv report|cv review|cv audit|ats|compare job with cv/.test(text)) {
      return "cv_report";
    }

    return "generic";
  }

  function buildUpgradeCaptureContext(trigger, modal) {
    var defaultContext = parseJsonAttribute(
      modal,
      "data-upgrade-capture-default-context"
    );
    var explicitContext = parseJsonAttribute(
      trigger,
      "data-upgrade-capture-context"
    );
    var merged = Object.assign({}, defaultContext, explicitContext);
    var intent = String(
      merged.intent || inferUpgradeIntent(trigger) || "generic"
    ).trim();
    return Object.assign({}, buildGuidedEntryContext(intent, merged), merged);
  }

  function isUpgradeCaptureEligible(trigger) {
    if (!trigger) {
      return false;
    }

    if (trigger.closest(".sffc-crm-reddit-single-shell-actions")) {
      return false;
    }

    return !!(
      trigger.closest(".sffc-crm-reddit-single-job-main") ||
      trigger.closest(".sffc-crm-reddit-single-mobile-nav") ||
      trigger.closest("[data-single-apply-modal]") ||
      trigger.closest("[data-single-material-modal]") ||
      trigger.closest("[data-single-networking-modal]")
    );
  }

  function openUpgradeCapture(trigger) {
    var modal = getUpgradeCaptureModal(trigger);
    if (!modal) {
      return false;
    }

    var context = buildUpgradeCaptureContext(trigger, modal);
    var titleNode = modal.querySelector("[data-upgrade-capture-title]");
    var subtitleNode = modal.querySelector("[data-upgrade-capture-subtitle]");
    var kickerNode = modal.querySelector("[data-upgrade-capture-kicker]");
    var previewNode = modal.querySelector("[data-upgrade-capture-preview]");
    var formNoteNode = modal.querySelector("[data-upgrade-capture-form-note]");
    var nameInput = modal.querySelector("[data-upgrade-capture-name]");
    var emailInput = modal.querySelector("[data-upgrade-capture-email]");

    modal.__upgradeCaptureContext = context;
    modal.__upgradeCaptureTrigger = trigger;
    modal.__upgradeCaptureEmailLookup = "";
    modal.__upgradeCaptureEmailState = null;

    if (titleNode) {
      titleNode.textContent = context.headline || "";
    }
    if (subtitleNode) {
      subtitleNode.innerHTML = renderUpgradeCaptureSubtitle(context);
    }
    if (kickerNode) {
      kickerNode.textContent = context.joinLabel || "Join MENA Careers";
    }
    if (previewNode) {
      previewNode.innerHTML = renderUpgradeCapturePreview(context);
    }
    if (formNoteNode) {
      formNoteNode.textContent =
        context.formNote ||
        "Your name and email personalize the route, recommendation, and secure checkout from the start.";
    }
    updateUpgradeCaptureAccountState(modal, { exists: false });
    setUpgradeCaptureFeedback(modal, "", false);

    modal.hidden = false;
    document.body.classList.add("sffc-crm-reddit-upgrade-capture-open");

    window.requestAnimationFrame(function () {
      if (nameInput && !String(nameInput.value || "").trim()) {
        nameInput.focus();
      } else if (emailInput && !String(emailInput.value || "").trim()) {
        emailInput.focus();
      } else if (modal.querySelector("[data-upgrade-capture-submit]")) {
        modal.querySelector("[data-upgrade-capture-submit]").focus();
      } else if (emailInput) {
        emailInput.focus();
      }
    });

    return true;
  }

  function closeUpgradeCapture(modal) {
    if (!modal) {
      return;
    }

    modal.hidden = true;
    modal.__upgradeCaptureContext = null;
    modal.__upgradeCaptureEmailLookup = "";
    modal.__upgradeCaptureEmailState = null;
    document.body.classList.remove("sffc-crm-reddit-upgrade-capture-open");
    setUpgradeCaptureFeedback(modal, "", false);

    if (
      modal.__upgradeCaptureTrigger &&
      typeof modal.__upgradeCaptureTrigger.focus === "function"
    ) {
      modal.__upgradeCaptureTrigger.focus();
    }
  }

  function submitUpgradeCapture(form) {
    var modal = form ? form.closest("[data-upgrade-capture-modal]") : null;
    if (!modal) {
      redirectToMembership();
      return;
    }

    if (!form.checkValidity()) {
      if (typeof form.reportValidity === "function") {
        form.reportValidity();
      }
      return;
    }

    var nameInput = form.querySelector("[data-upgrade-capture-name]");
    var emailInput = form.querySelector("[data-upgrade-capture-email]");
    var context = Object.assign({}, modal.__upgradeCaptureContext || {});
    var membershipUrl =
      modal.getAttribute("data-membership-url") ||
      redditConfig.membershipUrl ||
      "/memberships/";

    setUpgradeCaptureFeedback(modal, "", false);
    context.fullName = nameInput ? String(nameInput.value || "").trim() : "";
    context.email = emailInput ? String(emailInput.value || "").trim() : "";

    var continueWithRedirect = function () {
      try {
        if (window.sessionStorage) {
          window.sessionStorage.setItem(
            GUIDED_ENTRY_CONTEXT_KEY,
            JSON.stringify(context)
          );
        }
      } catch (error) {
        // Ignore storage failures and continue with the redirect.
      }

      window.location.href = membershipUrl;
    };

    checkUpgradeCaptureEmail(modal, context.email).then(function (state) {
      if (state && state.exists) {
        setUpgradeCaptureFeedback(
          modal,
          "Your MENA Careers account already exists. Log in to continue with this route.",
          false
        );
        return;
      }

      continueWithRedirect();
    });
  }

  function getGapLoadingMarkup() {
    return [
      '<div class="sffc-crm-reddit-gap-loading" data-reddit-gap-loading>',
      '<div class="sffc-crm-reddit-gap-loading-visual" aria-hidden="true">',
      '<div class="sffc-crm-reddit-gap-loading-orbit sffc-crm-reddit-gap-loading-orbit--one"></div>',
      '<div class="sffc-crm-reddit-gap-loading-orbit sffc-crm-reddit-gap-loading-orbit--two"></div>',
      '<div class="sffc-crm-reddit-gap-loading-orbit sffc-crm-reddit-gap-loading-orbit--three"></div>',
      '<div class="sffc-crm-reddit-gap-loading-core">',
      '<div class="sffc-crm-reddit-gap-spinner"></div>',
      "</div>",
      "</div>",
      '<div class="sffc-crm-reddit-gap-loading-card">',
      '<p class="sffc-crm-reddit-gap-loading-kicker">Tailored review in progress</p>',
      "<h3>We&rsquo;re improving your application.</h3>",
      "<p>MENA Careers is reading the live role, mapping the hiring signals, and preparing a stronger application workspace so the wait results in sharper next steps.</p>",
      '<div class="sffc-crm-reddit-gap-loading-meta">',
      '<span class="sffc-crm-reddit-gap-loading-meta-label">Estimated completion</span>',
      "<strong data-gap-eta>~90 seconds</strong>",
      "</div>",
      '<div class="sffc-crm-reddit-gap-loading-progress"><span data-gap-progress-fill></span></div>',
      '<div class="sffc-crm-reddit-gap-loading-stages">',
      '<div class="sffc-crm-reddit-gap-loading-stage is-active" data-gap-stage="0"><span class="sffc-crm-reddit-gap-loading-stage-dot"></span><div><strong>Parsing job description</strong><span>Reading the live brief and extracting the core role requirements.</span></div></div>',
      '<div class="sffc-crm-reddit-gap-loading-stage" data-gap-stage="1"><span class="sffc-crm-reddit-gap-loading-stage-dot"></span><div><strong>Extracting candidate signals</strong><span>Identifying the seniority, profile cues, and hiring signals that matter most.</span></div></div>',
      '<div class="sffc-crm-reddit-gap-loading-stage" data-gap-stage="2"><span class="sffc-crm-reddit-gap-loading-stage-dot"></span><div><strong>Mapping skills and experience</strong><span>Comparing your background against the role&rsquo;s skills, requirements, and experience level.</span></div></div>',
      '<div class="sffc-crm-reddit-gap-loading-stage" data-gap-stage="3"><span class="sffc-crm-reddit-gap-loading-stage-dot"></span><div><strong>Building tailored recommendations</strong><span>Preparing strengths, gaps, and ways to sharpen your positioning for this role.</span></div></div>',
      '<div class="sffc-crm-reddit-gap-loading-stage" data-gap-stage="4"><span class="sffc-crm-reddit-gap-loading-stage-dot"></span><div><strong>Preparing downloads and outreach tools</strong><span>Finalising the review workspace, toolkit, and export-ready outputs.</span></div></div>',
      "</div>",
      '<div class="sffc-crm-reddit-gap-loading-value">',
      "<span>What you&rsquo;ll get:</span>",
      "<strong>detected requirements, missing signals, stronger positioning, and clearer application direction</strong>",
      "</div>",
      "</div>",
      "</div>",
    ].join("");
  }

  function clearGapLoadingTimers() {
    gapLoadingTimers.forEach(function (timerId) {
      window.clearTimeout(timerId);
    });
    gapLoadingTimers = [];

    if (gapLoadingCountdownTimer) {
      window.clearInterval(gapLoadingCountdownTimer);
      gapLoadingCountdownTimer = null;
    }
  }

  function startGapLoadingState() {
    clearGapLoadingTimers();

    var body = document.querySelector("[data-reddit-gap-body]");
    if (!body) {
      return;
    }

    body.innerHTML = getGapLoadingMarkup();

    var etaNode = body.querySelector("[data-gap-eta]");
    var progressFill = body.querySelector("[data-gap-progress-fill]");
    var stages = body.querySelectorAll("[data-gap-stage]");
    var remainingSeconds = 90;

    if (etaNode) {
      etaNode.textContent = "~" + remainingSeconds + " seconds";
    }

    if (progressFill) {
      progressFill.style.width = "12%";
    }

    gapLoadingCountdownTimer = window.setInterval(function () {
      remainingSeconds = Math.max(8, remainingSeconds - 1);
      if (etaNode) {
        etaNode.textContent = "~" + remainingSeconds + " seconds";
      }
    }, 1000);

    [0, 1, 2, 3, 4].forEach(function (stageIndex) {
      var timerId = window.setTimeout(function () {
        stages.forEach(function (stageNode, index) {
          stageNode.classList.toggle("is-complete", index < stageIndex);
          stageNode.classList.toggle("is-active", index === stageIndex);
        });

        if (progressFill) {
          var widths = ["12%", "28%", "48%", "72%", "94%"];
          progressFill.style.width = widths[stageIndex];
        }
      }, [0, 14000, 32000, 54000, 76000][stageIndex]);

      gapLoadingTimers.push(timerId);
    });
  }

  function getStoredGroupPrefs() {
    if (
      !redditConfig.storageKey ||
      typeof window.localStorage === "undefined"
    ) {
      return null;
    }

    try {
      var raw = window.localStorage.getItem(redditConfig.storageKey);
      return raw ? JSON.parse(raw) : null;
    } catch (error) {
      return null;
    }
  }

  function setStoredGroupPrefs(preferences) {
    if (
      !redditConfig.storageKey ||
      typeof window.localStorage === "undefined"
    ) {
      return;
    }

    try {
      window.localStorage.setItem(
        redditConfig.storageKey,
        JSON.stringify(preferences)
      );
    } catch (error) {
      // Ignore storage errors.
    }
  }

  function getDashboardCacheKey(href) {
    return href ? String(href) : "";
  }

  function readDashboardTabCache(href) {
    var cacheKey = getDashboardCacheKey(href);
    return cacheKey && dashboardTabCache[cacheKey]
      ? dashboardTabCache[cacheKey]
      : null;
  }

  function writeDashboardTabCache(href, markup) {
    var cacheKey = getDashboardCacheKey(href);
    if (!cacheKey || !markup) {
      return;
    }

    dashboardTabCache[cacheKey] = {
      markup: String(markup),
    };
  }

  function clearDashboardTabCache() {
    dashboardTabCache = Object.create(null);
  }

  function getDashboardTabFromHref(href) {
    if (!href) {
      return "";
    }

    try {
      var parsedUrl = new window.URL(href, window.location.origin);
      return parsedUrl.searchParams.get("dashboard_tab") || "";
    } catch (error) {
      return "";
    }
  }

  function getDashboardSkeletonMarkup(activeTab) {
    var tab = activeTab || "recruiter-matches";

    if (tab === "my-profile") {
      return [
        '<div class="sffc-crm-dashboard-app-board-skeleton sffc-crm-dashboard-app-board-skeleton--profile" aria-hidden="true">',
        '<div class="sffc-crm-dashboard-app-skeleton-block sffc-crm-dashboard-app-skeleton-block--hero"></div>',
        '<div class="sffc-crm-dashboard-app-skeleton-grid sffc-crm-dashboard-app-skeleton-grid--two">',
        '<div class="sffc-crm-dashboard-app-skeleton-card"></div>',
        '<div class="sffc-crm-dashboard-app-skeleton-card"></div>',
        "</div>",
        '<div class="sffc-crm-dashboard-app-skeleton-grid sffc-crm-dashboard-app-skeleton-grid--two">',
        '<div class="sffc-crm-dashboard-app-skeleton-card sffc-crm-dashboard-app-skeleton-card--tall"></div>',
        '<div class="sffc-crm-dashboard-app-skeleton-card sffc-crm-dashboard-app-skeleton-card--tall"></div>',
        "</div>",
        "</div>",
      ].join("");
    }

    if (tab === "review-cv") {
      return [
        '<div class="sffc-crm-dashboard-app-board-skeleton sffc-crm-dashboard-app-board-skeleton--review" aria-hidden="true">',
        '<div class="sffc-crm-dashboard-app-skeleton-row sffc-crm-dashboard-app-skeleton-row--head"></div>',
        '<div class="sffc-crm-dashboard-app-skeleton-table">',
        '<div class="sffc-crm-dashboard-app-skeleton-table-row sffc-crm-dashboard-app-skeleton-table-row--editor"></div>',
        '<div class="sffc-crm-dashboard-app-skeleton-table-row"></div>',
        '<div class="sffc-crm-dashboard-app-skeleton-table-row"></div>',
        '<div class="sffc-crm-dashboard-app-skeleton-table-row"></div>',
        "</div>",
        "</div>",
      ].join("");
    }

    if (tab === "recruiter-outreach") {
      return [
        '<div class="sffc-crm-dashboard-app-board-skeleton sffc-crm-dashboard-app-board-skeleton--outreach" aria-hidden="true">',
        '<div class="sffc-crm-dashboard-app-skeleton-grid sffc-crm-dashboard-app-skeleton-grid--workspace">',
        '<div class="sffc-crm-dashboard-app-skeleton-pane sffc-crm-dashboard-app-skeleton-pane--form">',
        '<div class="sffc-crm-dashboard-app-skeleton-line sffc-crm-dashboard-app-skeleton-line--short"></div>',
        '<div class="sffc-crm-dashboard-app-skeleton-card"></div>',
        '<div class="sffc-crm-dashboard-app-skeleton-card"></div>',
        '<div class="sffc-crm-dashboard-app-skeleton-card"></div>',
        "</div>",
        '<div class="sffc-crm-dashboard-app-skeleton-pane sffc-crm-dashboard-app-skeleton-pane--document">',
        '<div class="sffc-crm-dashboard-app-skeleton-line sffc-crm-dashboard-app-skeleton-line--medium"></div>',
        '<div class="sffc-crm-dashboard-app-skeleton-block sffc-crm-dashboard-app-skeleton-block--document"></div>',
        "</div>",
        "</div>",
        "</div>",
      ].join("");
    }

    return [
      '<div class="sffc-crm-dashboard-app-board-skeleton sffc-crm-dashboard-app-board-skeleton--matches" aria-hidden="true">',
      '<div class="sffc-crm-dashboard-app-skeleton-grid sffc-crm-dashboard-app-skeleton-grid--metrics">',
      '<div class="sffc-crm-dashboard-app-skeleton-metric"></div>',
      '<div class="sffc-crm-dashboard-app-skeleton-metric"></div>',
      '<div class="sffc-crm-dashboard-app-skeleton-metric"></div>',
      "</div>",
      '<div class="sffc-crm-dashboard-app-skeleton-list">',
      '<div class="sffc-crm-dashboard-app-skeleton-list-row"></div>',
      '<div class="sffc-crm-dashboard-app-skeleton-list-row"></div>',
      '<div class="sffc-crm-dashboard-app-skeleton-list-row"></div>',
      '<div class="sffc-crm-dashboard-app-skeleton-list-row"></div>',
      '<div class="sffc-crm-dashboard-app-skeleton-list-row"></div>',
      "</div>",
      "</div>",
    ].join("");
  }

  function showDashboardBoardSkeleton(board, activeTab) {
    if (!board) {
      return;
    }

    var skeleton = board.querySelector("[data-dashboard-board-skeleton]");
    if (!skeleton) {
      skeleton = document.createElement("div");
      skeleton.setAttribute("data-dashboard-board-skeleton", "");
      board.appendChild(skeleton);
    }

    skeleton.innerHTML = getDashboardSkeletonMarkup(activeTab);
  }

  function clearDashboardBoardSkeleton(board) {
    if (!board) {
      return;
    }

    var skeleton = board.querySelector("[data-dashboard-board-skeleton]");
    if (skeleton) {
      skeleton.remove();
    }
  }

  function syncDashboardNavState(shell, activeTab) {
    if (!shell || !activeTab) {
      return;
    }

    [
      ".sffc-crm-dashboard-app-primary [data-dashboard-nav]",
      ".sffc-crm-dashboard-app-mobile-nav [data-dashboard-nav]",
      ".sffc-crm-dashboard-app-profile[data-dashboard-nav]",
    ].forEach(function (selector) {
      shell.querySelectorAll(selector).forEach(function (link) {
        var linkTab = getDashboardTabFromHref(link.getAttribute("href") || "");
        if (!linkTab) {
          return;
        }

        var isActive = linkTab === activeTab;
        link.classList.toggle("is-active", isActive);
        link.classList.toggle(
          "is-current",
          isActive &&
            activeTab === "my-profile" &&
            link.classList.contains("sffc-crm-dashboard-app-nav-link")
        );
      });
    });

    shell.setAttribute("data-dashboard-active-tab", activeTab);
  }

  function collectGroupPreferences(shell) {
    var items = Array.prototype.slice.call(
      shell.querySelectorAll("[data-reddit-group-item][data-group-slug]")
    );
    var order = [];
    var pinned = [];

    items.forEach(function (item) {
      var slug = item.getAttribute("data-group-slug") || "";
      if (!slug) {
        return;
      }

      order.push(slug);
      if (item.classList.contains("is-pinned")) {
        pinned.push(slug);
      }
    });

    return { order: order, pinned: pinned };
  }

  function applyGroupPreferences(shell, preferences) {
    var navList = shell.querySelector(".sffc-crm-reddit-nav-list");
    if (!navList || !preferences) {
      return;
    }

    var allRolesItem = navList.querySelector(
      '[data-reddit-group-item][data-group-slug=""]'
    );
    var items = Array.prototype.slice
      .call(
        navList.querySelectorAll("[data-reddit-group-item][data-group-slug]")
      )
      .filter(function (item) {
        return (item.getAttribute("data-group-slug") || "") !== "";
      });
    var orderLookup = {};
    var pinnedLookup = {};

    (preferences.order || []).forEach(function (slug, index) {
      orderLookup[slug] = index;
    });

    (preferences.pinned || []).forEach(function (slug) {
      pinnedLookup[slug] = true;
    });

    items.forEach(function (item) {
      var slug = item.getAttribute("data-group-slug") || "";
      var isPinned = !!pinnedLookup[slug];
      item.classList.toggle("is-pinned", isPinned);

      var pinButton = item.querySelector("[data-group-pin]");
      if (pinButton) {
        pinButton.setAttribute("aria-pressed", isPinned ? "true" : "false");
      }
    });

    items.sort(function (left, right) {
      var leftSlug = left.getAttribute("data-group-slug") || "";
      var rightSlug = right.getAttribute("data-group-slug") || "";
      var leftPinned = pinnedLookup[leftSlug] ? 1 : 0;
      var rightPinned = pinnedLookup[rightSlug] ? 1 : 0;

      if (leftPinned !== rightPinned) {
        return rightPinned - leftPinned;
      }

      var leftOrder = Object.prototype.hasOwnProperty.call(
        orderLookup,
        leftSlug
      )
        ? orderLookup[leftSlug]
        : Number.MAX_SAFE_INTEGER;
      var rightOrder = Object.prototype.hasOwnProperty.call(
        orderLookup,
        rightSlug
      )
        ? orderLookup[rightSlug]
        : Number.MAX_SAFE_INTEGER;

      return leftOrder - rightOrder;
    });

    items.forEach(function (item) {
      navList.appendChild(item);
    });

    if (allRolesItem) {
      navList.insertBefore(allRolesItem, navList.firstChild);
    }
  }

  function persistGroupPreferences(preferences) {
    if (
      !redditConfig.isLoggedIn ||
      !redditConfig.ajaxUrl ||
      !redditConfig.nonce ||
      typeof window.fetch !== "function"
    ) {
      return;
    }

    var body = new window.FormData();
    body.append("action", "sffc_crm_save_reddit_group_nav_prefs");
    body.append("nonce", redditConfig.nonce);

    (preferences.order || []).forEach(function (slug) {
      body.append("order[]", slug);
    });

    (preferences.pinned || []).forEach(function (slug) {
      body.append("pinned[]", slug);
    });

    window
      .fetch(redditConfig.ajaxUrl, {
        method: "POST",
        credentials: "same-origin",
        body: body,
      })
      .catch(function () {
        // Ignore preference save failures on the frontend.
      });
  }

  function syncGroupPreferences(shell) {
    var preferences = collectGroupPreferences(shell);
    applyGroupPreferences(shell, preferences);
    setStoredGroupPrefs(preferences);
    persistGroupPreferences(preferences);
  }

  function clearDragTargets(shell) {
    shell
      .querySelectorAll(".sffc-crm-reddit-nav-item.is-drop-target")
      .forEach(function (item) {
        item.classList.remove("is-drop-target");
      });
  }

  function handleGroupDragOver(event, targetItem) {
    if (!draggingGroupItem || !targetItem || draggingGroupItem === targetItem) {
      return;
    }

    event.preventDefault();

    var bounds = targetItem.getBoundingClientRect();
    var shouldInsertAfter = event.clientY > bounds.top + bounds.height / 2;
    var referenceNode = shouldInsertAfter ? targetItem.nextSibling : targetItem;

    if (referenceNode !== draggingGroupItem) {
      targetItem.parentNode.insertBefore(draggingGroupItem, referenceNode);
    }

    clearDragTargets(targetItem.closest(".sffc-crm-reddit-shell"));
    targetItem.classList.add("is-drop-target");
  }

  function getDashboardPipelineStages() {
    return redditConfig.pipelineStages &&
      typeof redditConfig.pipelineStages === "object"
      ? redditConfig.pipelineStages
      : {};
  }

  function getDashboardMatchStageMeta(stage, explicitMeta) {
    if (explicitMeta && (explicitMeta.label || explicitMeta.color)) {
      return {
        label: explicitMeta.label || "Tracked",
        color: explicitMeta.color || "#0d353e",
      };
    }

    var stages = getDashboardPipelineStages();
    var stageMeta = Object.prototype.hasOwnProperty.call(stages, stage)
      ? stages[stage]
      : null;

    return {
      label: stageMeta && stageMeta.label ? stageMeta.label : "Tracked",
      color: stageMeta && stageMeta.color ? stageMeta.color : "#0d353e",
    };
  }

  function getDashboardMatchRowIdentity(row) {
    if (!row) {
      return null;
    }

    return {
      pipelineId:
        parseInt(row.getAttribute("data-pipeline-id") || "0", 10) || 0,
      crmPostId: parseInt(row.getAttribute("data-post-id") || "0", 10) || 0,
      wpPostId: parseInt(row.getAttribute("data-wp-post-id") || "0", 10) || 0,
      roleTitle: row.getAttribute("data-role-title") || "",
      company: row.getAttribute("data-company") || "",
    };
  }

  function dashboardMatchRowsReferToSameRole(rowA, rowB) {
    var identityA = getDashboardMatchRowIdentity(rowA);
    var identityB = getDashboardMatchRowIdentity(rowB);

    if (!identityA || !identityB) {
      return false;
    }

    if (identityA.pipelineId > 0 && identityB.pipelineId > 0) {
      return identityA.pipelineId === identityB.pipelineId;
    }

    if (identityA.crmPostId > 0 && identityB.crmPostId > 0) {
      return identityA.crmPostId === identityB.crmPostId;
    }

    if (identityA.wpPostId > 0 && identityB.wpPostId > 0) {
      return identityA.wpPostId === identityB.wpPostId;
    }

    return (
      identityA.roleTitle !== "" &&
      identityA.company !== "" &&
      identityA.roleTitle === identityB.roleTitle &&
      identityA.company === identityB.company
    );
  }

  function updateDashboardMatchStatusMarkup(
    row,
    stage,
    pipelineId,
    explicitMeta
  ) {
    if (!row) {
      return;
    }

    var stageMeta = getDashboardMatchStageMeta(stage, explicitMeta);
    var statusNode = row.querySelector("[data-dashboard-match-status]");

    row.setAttribute("data-current-stage", stage || "");
    row.setAttribute("data-pipeline-id", pipelineId ? String(pipelineId) : "");
    row.setAttribute("data-pipeline-stage-label", stageMeta.label);
    row.setAttribute("data-pipeline-stage-color", stageMeta.color);
    row.classList.toggle("is-tracked", !!pipelineId);

    if (statusNode) {
      statusNode.textContent = stageMeta.label;
      statusNode.setAttribute(
        "data-dashboard-match-status-label",
        stageMeta.label
      );
      statusNode.style.setProperty("--match-status-color", stageMeta.color);
    }
  }

  function syncDashboardCachedMatchRowState(
    sourceRow,
    stage,
    pipelineId,
    explicitMeta
  ) {
    var identity = getDashboardMatchRowIdentity(sourceRow);

    if (!identity) {
      return;
    }

    Object.keys(dashboardTabCache).forEach(function (cacheKey) {
      var cached = dashboardTabCache[cacheKey];
      if (!cached || !cached.markup) {
        return;
      }

      try {
        var parser = new window.DOMParser();
        var doc = parser.parseFromString(String(cached.markup), "text/html");
        var changed = false;

        Array.prototype.slice
          .call(doc.querySelectorAll("[data-dashboard-match-row]"))
          .forEach(function (cachedRow) {
            if (!dashboardMatchRowsReferToSameRole(sourceRow, cachedRow)) {
              return;
            }

            updateDashboardMatchStatusMarkup(
              cachedRow,
              stage,
              pipelineId,
              explicitMeta
            );
            changed = true;
          });

        if (changed) {
          dashboardTabCache[cacheKey] = {
            markup: doc.body.innerHTML,
          };
        }
      } catch (error) {
        delete dashboardTabCache[cacheKey];
      }
    });
  }

  function updateDashboardMatchRowState(row, stage, pipelineId, explicitMeta) {
    if (!row) {
      return;
    }

    updateDashboardMatchStatusMarkup(row, stage, pipelineId, explicitMeta);
    syncDashboardCachedMatchRowState(row, stage, pipelineId, explicitMeta);
  }

  function syncDashboardMatchesBulkBar(shell) {
    if (!shell) {
      return;
    }

    var bulkBar = shell.querySelector("[data-dashboard-matches-bulkbar]");
    if (!bulkBar) {
      return;
    }

    var selected = shell.querySelectorAll(
      "[data-dashboard-match-select]:checked"
    );
    var countNode = bulkBar.querySelector("[data-dashboard-match-count]");
    var feedbackNode = bulkBar.querySelector("[data-dashboard-match-feedback]");

    if (!selected.length) {
      bulkBar.hidden = true;
      bulkBar.classList.remove("is-visible");
      if (countNode) {
        countNode.textContent = "0 roles selected";
      }
      if (feedbackNode) {
        feedbackNode.textContent =
          "Update the status for tracked roles and save it to your CRM pipeline.";
      }
      return;
    }

    bulkBar.hidden = false;
    bulkBar.classList.add("is-visible");

    if (countNode) {
      countNode.textContent =
        selected.length === 1
          ? "1 role selected"
          : selected.length + " roles selected";
    }
  }

  function setDashboardActionLoading(actionNode, isLoading, loadingLabel) {
    if (!actionNode) {
      return;
    }

    if (isLoading) {
      if (!actionNode.hasAttribute("data-loading-original-label")) {
        actionNode.setAttribute(
          "data-loading-original-label",
          actionNode.textContent || ""
        );
      }

      actionNode.classList.add("is-loading");
      actionNode.setAttribute("aria-busy", "true");

      if (loadingLabel && actionNode.children.length === 0) {
        actionNode.textContent = loadingLabel;
      }

      return;
    }

    actionNode.classList.remove("is-loading");
    actionNode.removeAttribute("aria-busy");

    if (
      actionNode.hasAttribute("data-loading-original-label") &&
      actionNode.children.length === 0
    ) {
      actionNode.textContent =
        actionNode.getAttribute("data-loading-original-label") || "";
    }

    actionNode.removeAttribute("data-loading-original-label");
  }

  function initDashboardSearchAutocomplete(scope) {
    scope = scope || document;

    Array.prototype.slice
      .call(
        scope.querySelectorAll(".sffc-crm-dashboard-app-search input[list]")
      )
      .forEach(function (input) {
        if (input.getAttribute("data-dashboard-autocomplete-ready") === "1") {
          return;
        }

        var sourceListId = input.getAttribute("list");
        var datalist = sourceListId
          ? document.getElementById(sourceListId)
          : null;
        var form = input.closest(".sffc-crm-dashboard-app-search");
        if (!datalist || !form) {
          return;
        }

        input.setAttribute("data-dashboard-autocomplete-ready", "1");
        input.removeAttribute("list");
        input.setAttribute("role", "combobox");
        input.setAttribute("aria-expanded", "false");

        var panel = document.createElement("div");
        panel.className = "sffc-crm-dashboard-app-search-suggestions";
        panel.setAttribute("role", "listbox");
        panel.hidden = true;
        form.appendChild(panel);
        var clickArmed = false;

        function getOptions() {
          return Array.prototype.slice
            .call(datalist.querySelectorAll("option"))
            .map(function (option) {
              return option.value || "";
            })
            .filter(function (value) {
              return value;
            });
        }

        function closePanel(resetClickArm) {
          panel.hidden = true;
          input.setAttribute("aria-expanded", "false");
          input.removeAttribute("aria-activedescendant");
          if (resetClickArm) {
            clickArmed = false;
          }
        }

        function choose(value) {
          input.value = value;
          closePanel(true);
          input.dispatchEvent(new Event("change", { bubbles: true }));
        }

        function render() {
          if (!clickArmed) {
            closePanel(false);
            return;
          }

          var query = (input.value || "").trim().toLowerCase();
          var options = getOptions()
            .filter(function (value) {
              return !query || value.toLowerCase().indexOf(query) !== -1;
            })
            .slice(0, 8);

          if (!options.length) {
            closePanel();
            return;
          }

          panel.innerHTML = options
            .map(function (value, index) {
              var optionId = "dashboard-search-option-" + index;
              return (
                '<button type="button" id="' +
                optionId +
                '" role="option" data-value="' +
                escapeHtml(value) +
                '"' +
                (index === 0 ? ' class="is-active"' : "") +
                "><span>" +
                escapeHtml(value) +
                "</span></button>"
              );
            })
            .join("");
          panel.hidden = false;
          input.setAttribute("aria-expanded", "true");
        }

        function armAndRender() {
          clickArmed = true;
          render();
        }

        form.addEventListener("click", function (event) {
          if (
            event.target.closest(".sffc-crm-dashboard-app-search-suggestions")
          ) {
            return;
          }
          armAndRender();
        });

        input.addEventListener("focus", function () {
          if (clickArmed) {
            render();
          }
        });
        input.addEventListener("input", render);
        input.addEventListener("keydown", function (event) {
          if (panel.hidden) {
            return;
          }

          var items = Array.prototype.slice.call(
            panel.querySelectorAll("button")
          );
          var activeIndex = items.findIndex(function (item) {
            return item.classList.contains("is-active");
          });

          if (event.key === "ArrowDown" || event.key === "ArrowUp") {
            event.preventDefault();
            if (!items.length) {
              return;
            }
            items.forEach(function (item) {
              item.classList.remove("is-active");
            });
            activeIndex =
              event.key === "ArrowDown" ? activeIndex + 1 : activeIndex - 1;
            if (activeIndex < 0) {
              activeIndex = items.length - 1;
            }
            if (activeIndex >= items.length) {
              activeIndex = 0;
            }
            items[activeIndex].classList.add("is-active");
            input.setAttribute("aria-activedescendant", items[activeIndex].id);
          } else if (event.key === "Enter") {
            var activeItem = items[activeIndex] || items[0];
            if (activeItem) {
              event.preventDefault();
              choose(activeItem.getAttribute("data-value"));
            }
          } else if (event.key === "Escape") {
            closePanel(true);
          }
        });

        panel.addEventListener("mousedown", function (event) {
          var button = event.target.closest("button[data-value]");
          if (!button) {
            return;
          }
          event.preventDefault();
          choose(button.getAttribute("data-value"));
        });

        document.addEventListener("click", function (event) {
          if (!form.contains(event.target)) {
            closePanel(true);
          }
        });
      });
  }

  function initDashboardNewsSlideshows(scope) {
    scope = scope || document;

    Array.prototype.slice
      .call(scope.querySelectorAll("[data-dashboard-news-slideshow]"))
      .forEach(function (tile) {
        var slides = Array.prototype.slice.call(
          tile.querySelectorAll("[data-dashboard-news-slide]")
        );
        var intervalMs = 4200;
        var currentIndex = 0;

        if (tile._dashboardNewsTimer) {
          window.clearInterval(tile._dashboardNewsTimer);
          tile._dashboardNewsTimer = null;
        }

        if (slides.length <= 1) {
          slides.forEach(function (slide, index) {
            slide.classList.toggle("is-active", index === 0);
            slide.setAttribute("aria-hidden", index === 0 ? "false" : "true");
          });
          return;
        }

        function showSlide(nextIndex) {
          slides.forEach(function (slide, index) {
            var isActive = index === nextIndex;
            slide.classList.toggle("is-active", isActive);
            slide.setAttribute("aria-hidden", isActive ? "false" : "true");
          });
          currentIndex = nextIndex;
        }

        showSlide(0);

        tile._dashboardNewsTimer = window.setInterval(function () {
          showSlide((currentIndex + 1) % slides.length);
        }, intervalMs);
      });
  }

  function getDashboardTopbarPanel(shell, type) {
    return shell
      ? shell.querySelector('[data-dashboard-topbar-panel="' + type + '"]')
      : null;
  }

  function getDashboardModal(shell, type) {
    return shell
      ? shell.querySelector('[data-dashboard-modal="' + type + '"]')
      : null;
  }

  function getDashboardTopbarCacheKey(shell, kind, type, extraData) {
    if (!shell || !type) {
      return "";
    }

    return JSON.stringify({
      kind: kind || "panel",
      type: type,
      url: shell.getAttribute("data-dashboard-current-url") || "",
      extra: extraData || {},
    });
  }

  function readDashboardTopbarCache(shell, kind, type, extraData) {
    var cacheKey = getDashboardTopbarCacheKey(shell, kind, type, extraData);
    return cacheKey && dashboardTopbarCache[cacheKey]
      ? dashboardTopbarCache[cacheKey]
      : null;
  }

  function writeDashboardTopbarCache(shell, kind, type, extraData, payload) {
    var cacheKey = getDashboardTopbarCacheKey(shell, kind, type, extraData);
    if (!cacheKey || !payload) {
      return;
    }

    dashboardTopbarCache[cacheKey] = payload;
  }

  function clearDashboardTopbarCache(type) {
    Object.keys(dashboardTopbarCache).forEach(function (cacheKey) {
      if (!type || cacheKey.indexOf('"type":"' + String(type) + '"') !== -1) {
        delete dashboardTopbarCache[cacheKey];
      }
    });
  }

  function closeDashboardTopbarPanels(shell, exceptType) {
    if (!shell) {
      return;
    }

    shell
      .querySelectorAll("[data-dashboard-topbar-panel]")
      .forEach(function (panel) {
        var panelType = panel.getAttribute("data-dashboard-topbar-panel") || "";
        if (exceptType && panelType === exceptType) {
          return;
        }
        panel.hidden = true;
        panel.classList.remove("is-open", "is-loading");
      });
  }

  function closeDashboardModals(shell, exceptType) {
    if (!shell) {
      return;
    }

    shell.querySelectorAll("[data-dashboard-modal]").forEach(function (modal) {
      var modalType = modal.getAttribute("data-dashboard-modal") || "";
      if (exceptType && modalType === exceptType) {
        return;
      }
      modal.hidden = true;
      modal.setAttribute("aria-hidden", "true");
      modal.classList.remove("is-open", "is-loading");
      document.body.classList.remove("sffc-dashboard-modal-open");
    });
  }

  function updateDashboardTopbarBadge(shell, type, count) {
    if (!shell) {
      return;
    }

    var trigger = shell.querySelector(
      '[data-dashboard-topbar-trigger="' + type + '"]'
    );
    if (!trigger) {
      return;
    }

    var badge = trigger.querySelector(".sffc-crm-dashboard-app-topbar-badge");
    var safeCount = Math.max(0, parseInt(count, 10) || 0);

    if (safeCount > 0) {
      trigger.classList.add("has-badge");
      if (!badge) {
        badge = document.createElement("span");
        badge.className = "sffc-crm-dashboard-app-topbar-badge";
        trigger.appendChild(badge);
      }
      badge.textContent = String(Math.min(99, safeCount));
    } else {
      trigger.classList.remove("has-badge");
      if (badge) {
        badge.remove();
      }
    }
  }

  function fetchDashboardTopbarPanel(shell, type, extraData) {
    if (
      !shell ||
      !redditConfig.ajaxUrl ||
      !redditConfig.accountNonce ||
      typeof window.fetch !== "function"
    ) {
      return Promise.reject(new Error("Dashboard topbar unavailable"));
    }

    var panel = getDashboardTopbarPanel(shell, type);
    var actionMap = {
      notifications: "sffc_crm_reddit_dashboard_notifications",
      messages: "sffc_crm_reddit_dashboard_inbox",
    };

    if (!panel || !actionMap[type]) {
      return Promise.reject(new Error("Dashboard panel missing"));
    }

    var cachedPayload = readDashboardTopbarCache(
      shell,
      "panel",
      type,
      extraData
    );
    if (cachedPayload && cachedPayload.markup) {
      panel.innerHTML = cachedPayload.markup;
      panel.classList.remove("is-loading");
      panel.hidden = false;
      panel.classList.add("is-open");

      if (
        type === "messages" &&
        typeof cachedPayload.unread_count !== "undefined"
      ) {
        updateDashboardTopbarBadge(
          shell,
          "messages",
          cachedPayload.unread_count
        );
      }
      if (
        type === "notifications" &&
        typeof cachedPayload.unread_count !== "undefined"
      ) {
        updateDashboardTopbarBadge(
          shell,
          "notifications",
          cachedPayload.unread_count
        );
      }

      return Promise.resolve(cachedPayload);
    }

    panel.hidden = false;
    panel.classList.add("is-open", "is-loading");
    panel.innerHTML =
      '<div class="sffc-crm-dashboard-app-topbar-panel-loading">Loading...</div>';

    var body = new window.FormData();
    body.append("action", actionMap[type]);
    body.append("nonce", redditConfig.accountNonce);
    body.append(
      "current_url",
      shell.getAttribute("data-dashboard-current-url") || ""
    );

    Object.keys(extraData || {}).forEach(function (key) {
      body.append(key, extraData[key]);
    });

    return window
      .fetch(redditConfig.ajaxUrl, {
        method: "POST",
        credentials: "same-origin",
        body: body,
      })
      .then(function (response) {
        return response.json();
      })
      .then(function (response) {
        if (
          !response ||
          !response.success ||
          !response.data ||
          !response.data.markup
        ) {
          throw new Error("Dashboard panel failed");
        }

        panel.innerHTML = response.data.markup;
        panel.classList.remove("is-loading");
        panel.hidden = false;
        panel.classList.add("is-open");
        writeDashboardTopbarCache(
          shell,
          "panel",
          type,
          extraData,
          response.data
        );

        if (
          type === "messages" &&
          typeof response.data.unread_count !== "undefined"
        ) {
          updateDashboardTopbarBadge(
            shell,
            "messages",
            response.data.unread_count
          );
        }
        if (
          type === "notifications" &&
          typeof response.data.unread_count !== "undefined"
        ) {
          updateDashboardTopbarBadge(
            shell,
            "notifications",
            response.data.unread_count
          );
        }

        return response.data;
      })
      .catch(function (error) {
        panel.classList.remove("is-loading");
        panel.hidden = false;
        panel.classList.add("is-open");
        panel.innerHTML =
          '<div class="sffc-crm-dashboard-app-topbar-empty">Unable to load right now.</div>';
        throw error;
      });
  }

  function fetchDashboardModal(shell, type, extraData) {
    if (
      !shell ||
      !redditConfig.ajaxUrl ||
      !redditConfig.accountNonce ||
      typeof window.fetch !== "function"
    ) {
      return Promise.reject(new Error("Dashboard modal unavailable"));
    }

    var modal = getDashboardModal(shell, type);
    var dialog = modal
      ? modal.querySelector(".sffc-crm-dashboard-app-modal-dialog")
      : null;
    var actionMap = {
      messages: "sffc_crm_reddit_dashboard_inbox",
      "match-explainer": "sffc_crm_reddit_match_explainer",
      "ats-explainer": "sffc_crm_reddit_ats_explainer",
    };

    if (!modal || !dialog || !actionMap[type]) {
      return Promise.reject(new Error("Dashboard modal missing"));
    }

    var cachedPayload = readDashboardTopbarCache(
      shell,
      "modal",
      type,
      extraData
    );
    if (cachedPayload && cachedPayload.markup) {
      modal.hidden = false;
      modal.setAttribute("aria-hidden", "false");
      modal.classList.add("is-open");
      modal.classList.remove("is-loading");
      dialog.innerHTML = cachedPayload.markup;
      filterDashboardInboxThreads(dialog);
      initDashboardInboxCompose(dialog);
      document.body.classList.add("sffc-dashboard-modal-open");

      if (
        type === "messages" &&
        typeof cachedPayload.unread_count !== "undefined"
      ) {
        updateDashboardTopbarBadge(
          shell,
          "messages",
          cachedPayload.unread_count
        );
      }

      return Promise.resolve(cachedPayload);
    }

    modal.hidden = false;
    modal.setAttribute("aria-hidden", "false");
    modal.classList.add("is-open", "is-loading");
    dialog.innerHTML = getDashboardModalSkeletonMarkup(type);
    document.body.classList.add("sffc-dashboard-modal-open");

    var body = new window.FormData();
    body.append("action", actionMap[type]);
    body.append("nonce", redditConfig.accountNonce);
    body.append(
      "current_url",
      shell.getAttribute("data-dashboard-current-url") || ""
    );

    Object.keys(extraData || {}).forEach(function (key) {
      body.append(key, extraData[key]);
    });

    return window
      .fetch(redditConfig.ajaxUrl, {
        method: "POST",
        credentials: "same-origin",
        body: body,
      })
      .then(function (response) {
        return response.json();
      })
      .then(function (response) {
        if (
          !response ||
          !response.success ||
          !response.data ||
          !response.data.markup
        ) {
          throw new Error("Dashboard modal failed");
        }

        dialog.innerHTML = response.data.markup;
        if (type === "messages") {
          filterDashboardInboxThreads(dialog);
          initDashboardInboxCompose(dialog);
        }
        modal.classList.remove("is-loading");
        writeDashboardTopbarCache(
          shell,
          "modal",
          type,
          extraData,
          response.data
        );

        if (
          type === "messages" &&
          typeof response.data.unread_count !== "undefined"
        ) {
          updateDashboardTopbarBadge(
            shell,
            "messages",
            response.data.unread_count
          );
        }

        return response.data;
      })
      .catch(function (error) {
        modal.classList.remove("is-loading");
        dialog.innerHTML =
          '<div class="sffc-crm-dashboard-app-topbar-empty">Unable to load right now.</div>';
        throw error;
      });
  }

  function openDashboardStaticModal(shell, type) {
    var modal = getDashboardModal(shell, type);
    if (!modal) {
      return;
    }

    closeDashboardTopbarPanels(shell);
    closeDashboardModals(shell, type);
    modal.hidden = false;
    modal.setAttribute("aria-hidden", "false");
    modal.classList.add("is-open");
    modal.classList.remove("is-loading");
    document.body.classList.add("sffc-dashboard-modal-open");
  }

  function setDashboardCancelSubscriptionFeedback(form, message, isError) {
    if (!form) {
      return;
    }

    var feedback = form.querySelector(
      "[data-dashboard-cancel-subscription-feedback]"
    );
    if (!feedback) {
      return;
    }

    feedback.hidden = !message;
    feedback.textContent = message || "";
    feedback.classList.toggle("is-error", !!isError);
    feedback.classList.toggle("is-success", !!message && !isError);
  }

  function submitDashboardCancelSubscriptionForm(form) {
    var shell = form ? form.closest("[data-dashboard-shell]") : null;
    var modal = form
      ? form.closest('[data-dashboard-modal="cancel-subscription"]')
      : null;
    var reasonField = form ? form.querySelector('[name="reason"]') : null;
    var deleteAccountField = form
      ? form.querySelector('[name="delete_account"]')
      : null;
    var submitButton = form
      ? form.querySelector('button[type="submit"]')
      : null;
    var reason = reasonField ? (reasonField.value || "").trim() : "";

    if (
      !shell ||
      !modal ||
      !redditConfig.ajaxUrl ||
      !redditConfig.accountNonce ||
      typeof window.fetch !== "function"
    ) {
      setDashboardCancelSubscriptionFeedback(
        form,
        "Cancellation request is unavailable right now.",
        true
      );
      return;
    }

    if (!reason) {
      setDashboardCancelSubscriptionFeedback(
        form,
        "Please explain why you want to cancel your subscription.",
        true
      );
      if (reasonField) {
        reasonField.focus();
      }
      return;
    }

    var body = new window.FormData();
    body.append("action", "sffc_crm_reddit_cancel_subscription_request");
    body.append("nonce", redditConfig.accountNonce);
    body.append("reason", reason);
    if (deleteAccountField && deleteAccountField.checked) {
      body.append("delete_account", "1");
    }

    setDashboardCancelSubscriptionFeedback(
      form,
      "Sending your request...",
      false
    );
    if (submitButton) {
      setDashboardActionLoading(submitButton, true, "Sending...");
      submitButton.disabled = true;
    }

    window
      .fetch(redditConfig.ajaxUrl, {
        method: "POST",
        credentials: "same-origin",
        body: body,
      })
      .then(function (response) {
        return response.json();
      })
      .then(function (response) {
        if (!response || !response.success || !response.data) {
          throw new Error(
            response && response.data && response.data.message
              ? response.data.message
              : "We could not send your request right now."
          );
        }

        setDashboardCancelSubscriptionFeedback(
          form,
          response.data.message || "Your request has been sent.",
          false
        );
        window.setTimeout(function () {
          closeDashboardModals(shell);
          if (reasonField) {
            reasonField.value = "";
          }
          if (deleteAccountField) {
            deleteAccountField.checked = false;
          }
          setDashboardCancelSubscriptionFeedback(form, "", false);
        }, 1200);
      })
      .catch(function (error) {
        setDashboardCancelSubscriptionFeedback(
          form,
          error && error.message
            ? error.message
            : "We could not send your request right now.",
          true
        );
      })
      .finally(function () {
        if (submitButton) {
          setDashboardActionLoading(submitButton, false);
          submitButton.disabled = false;
        }
      });
  }

  function setDashboardExpertSupportFeedback(form, message, isError) {
    if (!form) {
      return;
    }

    var feedback = form.querySelector(
      "[data-dashboard-expert-support-feedback]"
    );
    if (!feedback) {
      return;
    }

    feedback.hidden = !message;
    feedback.textContent = message || "";
    feedback.classList.toggle("is-error", !!isError);
    feedback.classList.toggle("is-success", !!message && !isError);
  }

  function updateDashboardExpertSupportCallFields(scope) {
    var shell = scope || document;
    Array.prototype.slice
      .call(shell.querySelectorAll("[data-dashboard-expert-support-form]"))
      .forEach(function (form) {
        var toggle = form.querySelector(
          "[data-dashboard-expert-support-call-toggle]"
        );
        var fields = form.querySelector(
          "[data-dashboard-expert-support-call-fields]"
        );
        if (!toggle || !fields) {
          return;
        }

        fields.hidden = !toggle.checked;
      });
  }

  function submitDashboardExpertSupportForm(form) {
    var supportTypes = Array.prototype.slice
      .call(form.querySelectorAll('input[name="support_types[]"]:checked'))
      .map(function (input) {
        return input.value;
      })
      .filter(Boolean);
    var requestCall =
      !!form.querySelector('[name="request_call"]') &&
      !!form.querySelector('[name="request_call"]').checked;
    var callReason =
      (form.querySelector('[name="call_reason"]') || {}).value || "";
    var preferredTiming =
      (form.querySelector('[name="preferred_timing"]') || {}).value || "";
    var details = (
      (form.querySelector('[name="details"]') || {}).value || ""
    ).trim();
    var submitButton = form.querySelector('button[type="submit"]');

    if (
      !redditConfig.ajaxUrl ||
      !redditConfig.accountNonce ||
      typeof window.fetch !== "function"
    ) {
      setDashboardExpertSupportFeedback(
        form,
        "Expert support is unavailable right now.",
        true
      );
      return;
    }

    if (!supportTypes.length) {
      setDashboardExpertSupportFeedback(
        form,
        "Choose at least one support area.",
        true
      );
      return;
    }

    if (!details) {
      setDashboardExpertSupportFeedback(
        form,
        "Please explain what you want help with.",
        true
      );
      return;
    }

    if (requestCall && !callReason) {
      setDashboardExpertSupportFeedback(
        form,
        "Choose why you want a call so we can route it properly.",
        true
      );
      return;
    }

    var body = new window.FormData();
    body.append("action", "sffc_crm_reddit_expert_support_request");
    body.append("nonce", redditConfig.accountNonce);
    supportTypes.forEach(function (type) {
      body.append("support_types[]", type);
    });
    body.append("details", details);
    if (requestCall) {
      body.append("request_call", "1");
      body.append("call_reason", callReason);
      body.append("preferred_timing", preferredTiming);
    }

    setDashboardExpertSupportFeedback(
      form,
      "Sending your support request...",
      false
    );
    if (submitButton) {
      setDashboardActionLoading(submitButton, true, "Sending...");
      submitButton.disabled = true;
    }

    window
      .fetch(redditConfig.ajaxUrl, {
        method: "POST",
        credentials: "same-origin",
        body: body,
      })
      .then(function (response) {
        return response.json();
      })
      .then(function (response) {
        if (!response || !response.success || !response.data) {
          throw new Error(
            response && response.data && response.data.message
              ? response.data.message
              : "We could not send your support request right now."
          );
        }

        form.reset();
        updateDashboardExpertSupportCallFields(form);
        setDashboardExpertSupportFeedback(
          form,
          response.data.message || "Your request has been sent.",
          false
        );
      })
      .catch(function (error) {
        setDashboardExpertSupportFeedback(
          form,
          error && error.message
            ? error.message
            : "We could not send your support request right now.",
          true
        );
      })
      .finally(function () {
        if (submitButton) {
          setDashboardActionLoading(submitButton, false);
          submitButton.disabled = false;
        }
      });
  }

  function getDashboardModalSkeletonMarkup(type) {
    if (type === "match-explainer" || type === "ats-explainer") {
      return [
        '<div class="sffc-crm-dashboard-app-modal-skeleton sffc-crm-dashboard-app-modal-skeleton--match-explainer" aria-hidden="true">',
        '<div class="sffc-crm-dashboard-app-modal-skeleton-head">',
        '<span class="sffc-crm-dashboard-app-modal-skeleton-block sffc-crm-dashboard-app-modal-skeleton-block--title"></span>',
        '<span class="sffc-crm-dashboard-app-modal-skeleton-block sffc-crm-dashboard-app-modal-skeleton-block--score"></span>',
        "</div>",
        '<span class="sffc-crm-dashboard-app-modal-skeleton-block sffc-crm-dashboard-app-modal-skeleton-block--summary"></span>',
        '<div class="sffc-crm-dashboard-app-modal-skeleton-grid sffc-crm-dashboard-app-modal-skeleton-grid--two">',
        '<div class="sffc-crm-dashboard-app-modal-skeleton-card"></div>',
        '<div class="sffc-crm-dashboard-app-modal-skeleton-card"></div>',
        "</div>",
        '<div class="sffc-crm-dashboard-app-modal-skeleton-card sffc-crm-dashboard-app-modal-skeleton-card--wide"></div>',
        "</div>",
      ].join("");
    }

    return [
      '<div class="sffc-crm-dashboard-app-modal-skeleton sffc-crm-dashboard-app-modal-skeleton--messages" aria-hidden="true">',
      '<div class="sffc-crm-dashboard-app-modal-skeleton-layout">',
      '<div class="sffc-crm-dashboard-app-modal-skeleton-sidebar">',
      '<span class="sffc-crm-dashboard-app-modal-skeleton-block sffc-crm-dashboard-app-modal-skeleton-block--search"></span>',
      '<div class="sffc-crm-dashboard-app-modal-skeleton-chips">',
      '<span class="sffc-crm-dashboard-app-modal-skeleton-chip"></span>',
      '<span class="sffc-crm-dashboard-app-modal-skeleton-chip"></span>',
      '<span class="sffc-crm-dashboard-app-modal-skeleton-chip"></span>',
      "</div>",
      '<div class="sffc-crm-dashboard-app-modal-skeleton-list">',
      '<div class="sffc-crm-dashboard-app-modal-skeleton-row"></div>',
      '<div class="sffc-crm-dashboard-app-modal-skeleton-row"></div>',
      '<div class="sffc-crm-dashboard-app-modal-skeleton-row"></div>',
      '<div class="sffc-crm-dashboard-app-modal-skeleton-row"></div>',
      "</div>",
      "</div>",
      '<div class="sffc-crm-dashboard-app-modal-skeleton-main">',
      '<span class="sffc-crm-dashboard-app-modal-skeleton-block sffc-crm-dashboard-app-modal-skeleton-block--header"></span>',
      '<div class="sffc-crm-dashboard-app-modal-skeleton-thread"></div>',
      '<div class="sffc-crm-dashboard-app-modal-skeleton-thread"></div>',
      '<div class="sffc-crm-dashboard-app-modal-skeleton-compose"></div>',
      "</div>",
      "</div>",
      "</div>",
    ].join("");
  }

  function filterDashboardInboxThreads(scope) {
    if (!scope) {
      return;
    }

    scope
      .querySelectorAll("[data-dashboard-inbox-search]")
      .forEach(function (input) {
        if (input._dashboardInboxBound) {
          return;
        }
        input._dashboardInboxBound = true;

        input.addEventListener("input", function () {
          var query = (input.value || "").trim().toLowerCase();
          var modalCard = input.closest(
            ".sffc-crm-dashboard-app-modal-card--messages"
          );
          if (!modalCard) {
            return;
          }

          modalCard
            .querySelectorAll("[data-dashboard-inbox-label]")
            .forEach(function (thread) {
              var haystack = (
                thread.getAttribute("data-dashboard-inbox-label") || ""
              ).toLowerCase();
              thread.hidden = query !== "" && haystack.indexOf(query) === -1;
            });
        });
      });
  }

  function initDashboardInboxCompose(scope) {
    if (!scope) {
      return;
    }

    scope
      .querySelectorAll("[data-dashboard-compose-search]")
      .forEach(function (input) {
        if (input._dashboardComposeBound) {
          return;
        }
        input._dashboardComposeBound = true;

        input.addEventListener("input", function () {
          var query = (input.value || "").trim();
          var modalCard = input.closest(
            ".sffc-crm-dashboard-app-modal-card--messages"
          );
          var results = modalCard
            ? modalCard.querySelector("[data-dashboard-compose-results]")
            : null;

          if (
            !modalCard ||
            !results ||
            !redditConfig.ajaxUrl ||
            !redditConfig.accountNonce
          ) {
            return;
          }

          if (input._dashboardComposeTimer) {
            window.clearTimeout(input._dashboardComposeTimer);
          }

          input._dashboardComposeTimer = window.setTimeout(function () {
            results.classList.add("is-loading");

            var body = new window.FormData();
            body.append("action", "sffc_crm_reddit_dashboard_message_users");
            body.append("nonce", redditConfig.accountNonce);
            body.append("search", query);

            window
              .fetch(redditConfig.ajaxUrl, {
                method: "POST",
                credentials: "same-origin",
                body: body,
              })
              .then(function (response) {
                return response.json();
              })
              .then(function (response) {
                if (
                  !response ||
                  !response.success ||
                  !response.data ||
                  typeof response.data.markup !== "string"
                ) {
                  throw new Error("Compose search failed");
                }

                results.innerHTML = response.data.markup;
              })
              .catch(function () {
                results.innerHTML =
                  '<div class="sffc-crm-dashboard-app-inbox-user-empty">Unable to load members right now.</div>';
              })
              .finally(function () {
                results.classList.remove("is-loading");
              });
          }, 180);
        });
      });
  }

  function closeDashboardInboxCompose(modalCard) {
    if (!modalCard) {
      return;
    }

    var panel = modalCard.querySelector("[data-dashboard-compose-panel]");
    if (!panel) {
      return;
    }

    panel.hidden = true;
    panel.classList.remove("is-open");
  }

  function openDashboardInboxCompose(modalCard) {
    if (!modalCard) {
      return;
    }

    var panel = modalCard.querySelector("[data-dashboard-compose-panel]");
    var search = panel
      ? panel.querySelector("[data-dashboard-compose-search]")
      : null;
    if (!panel) {
      return;
    }

    panel.hidden = false;
    panel.classList.add("is-open");
    if (search) {
      window.setTimeout(function () {
        search.focus();
      }, 10);
    }
  }

  function clearDashboardMatchesSelection(shell) {
    if (!shell) {
      return;
    }

    shell
      .querySelectorAll("[data-dashboard-match-select]:checked")
      .forEach(function (input) {
        input.checked = false;
      });

    syncDashboardMatchesBulkBar(shell);
  }

  function refreshDashboardCurrentBoard(shell) {
    if (
      !shell ||
      !redditConfig.ajaxUrl ||
      !redditConfig.dashboardTabNonce ||
      typeof window.fetch !== "function"
    ) {
      return Promise.reject(new Error("Dashboard refresh unavailable."));
    }

    var href = window.location.href;
    var board = shell.querySelector("[data-dashboard-board]");

    if (!board) {
      return Promise.reject(new Error("Dashboard board missing."));
    }

    shell.classList.add("is-loading");
    board.classList.add("is-loading");
    showDashboardBoardSkeleton(board, getDashboardTabFromHref(href));

    var body = new window.FormData();
    body.append("action", "sffc_crm_reddit_dashboard_tab");
    body.append("nonce", redditConfig.dashboardTabNonce);
    body.append("href", href);
    body.append(
      "jobs_post_id",
      shell.getAttribute("data-dashboard-jobs-post-id") || ""
    );
    body.append(
      "per_page",
      shell.getAttribute("data-dashboard-per-page") || ""
    );
    body.append(
      "fallback_role",
      shell.getAttribute("data-dashboard-fallback-role") || ""
    );
    body.append(
      "current_url",
      shell.getAttribute("data-dashboard-current-url") || ""
    );

    return window
      .fetch(redditConfig.ajaxUrl, {
        method: "POST",
        credentials: "same-origin",
        body: body,
      })
      .then(function (response) {
        return response.json();
      })
      .then(function (response) {
        if (
          !response ||
          !response.success ||
          !response.data ||
          !response.data.markup
        ) {
          throw new Error("Dashboard refresh failed");
        }

        clearDashboardTabCache();
        applyDashboardMarkup(shell, board, href, response.data.markup, false);
        syncDashboardMatchesBulkBar(shell);
        return response;
      })
      .finally(function () {
        shell.classList.remove("is-loading");
        board.classList.remove("is-loading");
        clearDashboardBoardSkeleton(board);
      });
  }

  function saveDashboardMatchRow(row, stage) {
    if (
      !row ||
      !stage ||
      !redditConfig.ajaxUrl ||
      !redditConfig.crmNonce ||
      typeof window.fetch !== "function"
    ) {
      return Promise.reject(new Error("Tracking is unavailable."));
    }

    var pipelineId = parseInt(row.getAttribute("data-pipeline-id") || "0", 10);
    var body = new window.FormData();
    var recruiterId = parseInt(
      row.getAttribute("data-recruiter-id") || "0",
      10
    );
    var postId = parseInt(row.getAttribute("data-post-id") || "0", 10);
    var wpPostId = parseInt(row.getAttribute("data-wp-post-id") || "0", 10);

    if (pipelineId <= 0 && recruiterId <= 0 && postId <= 0 && wpPostId <= 0) {
      return Promise.reject(
        new Error("This role does not have enough tracking data to save yet.")
      );
    }

    body.append("action", "sffc_crm_save_dashboard_tracking_status");
    body.append("nonce", redditConfig.crmNonce);
    body.append("pipeline_id", String(pipelineId || 0));
    body.append("stage", stage);
    body.append("role_title", row.getAttribute("data-role-title") || "");
    body.append("company", row.getAttribute("data-company") || "");
    body.append("location", row.getAttribute("data-location") || "");
    body.append("external_url", row.getAttribute("data-external-url") || "");

    if (recruiterId > 0) {
      body.append("recruiter_id", String(recruiterId));
    }
    if (postId > 0) {
      body.append("post_id", String(postId));
    }
    if (wpPostId > 0) {
      body.append("wp_post_id", String(wpPostId));
    }

    return window
      .fetch(redditConfig.ajaxUrl, {
        method: "POST",
        credentials: "same-origin",
        body: body,
      })
      .then(function (response) {
        return response
          .json()
          .catch(function () {
            return { success: false };
          })
          .then(function (payload) {
            if (!response.ok || !payload || payload.success !== true) {
              var errorMessage =
                payload && payload.data && payload.data.message
                  ? payload.data.message
                  : "Unable to save tracking status.";
              throw new Error(errorMessage);
            }

            var nextPipelineId = pipelineId;
            if (!nextPipelineId && payload.data && payload.data.pipeline_id) {
              nextPipelineId = parseInt(payload.data.pipeline_id, 10) || 0;
            }

            updateDashboardMatchRowState(row, stage, nextPipelineId, {
              label: payload && payload.data ? payload.data.stage_label : "",
              color: payload && payload.data ? payload.data.stage_color : "",
            });
            return payload;
          });
      });
  }

  function initDashboardMatchTracking() {
    document
      .querySelectorAll("[data-dashboard-shell]")
      .forEach(function (shell) {
        syncDashboardMatchesBulkBar(shell);
      });

    document.addEventListener("click", function (event) {
      var row = event.target.closest("[data-dashboard-match-row]");
      if (!row) {
        return;
      }

      if (
        event.target.closest(
          ".sffc-crm-dashboard-app-matches-cell--select, a, button, select, option, input, label"
        )
      ) {
        return;
      }

      var rowUrl = row.getAttribute("data-dashboard-row-url");
      if (!rowUrl) {
        return;
      }

      window.open(rowUrl, "_blank", "noopener");
    });

    document.addEventListener("change", function (event) {
      var checkbox = event.target.closest("[data-dashboard-match-select]");
      if (!checkbox) {
        return;
      }

      var shell = checkbox.closest("[data-dashboard-shell]");
      syncDashboardMatchesBulkBar(shell);
    });

    document.addEventListener("click", function (event) {
      var clearButton = event.target.closest("[data-dashboard-match-clear]");
      if (clearButton) {
        var clearShell = clearButton.closest("[data-dashboard-shell]");
        clearDashboardMatchesSelection(clearShell);
        return;
      }

      var saveButton = event.target.closest("[data-dashboard-match-save]");
      if (!saveButton) {
        return;
      }

      event.preventDefault();

      var shell = saveButton.closest("[data-dashboard-shell]");
      var bulkBar = saveButton.closest("[data-dashboard-matches-bulkbar]");
      var stageSelect = bulkBar
        ? bulkBar.querySelector("[data-dashboard-match-stage]")
        : null;
      var feedbackNode = bulkBar
        ? bulkBar.querySelector("[data-dashboard-match-feedback]")
        : null;
      var selectedRows = shell
        ? Array.prototype.slice
            .call(
              shell.querySelectorAll("[data-dashboard-match-select]:checked")
            )
            .map(function (input) {
              return input.closest("[data-dashboard-match-row]");
            })
            .filter(Boolean)
        : [];

      if (!shell || !bulkBar || !stageSelect || !selectedRows.length) {
        return;
      }

      if (!redditConfig.isLoggedIn) {
        window.location.href = redditConfig.membershipUrl || "/memberships/";
        return;
      }

      setDashboardActionLoading(saveButton, true, "Saving...");
      saveButton.disabled = true;
      bulkBar.classList.add("is-saving");

      if (feedbackNode) {
        feedbackNode.textContent = "Saving selected role statuses...";
      }

      Promise.allSettled(
        selectedRows.map(function (row) {
          return saveDashboardMatchRow(row, stageSelect.value);
        })
      )
        .then(function (results) {
          var failed = results.filter(function (result) {
            return result.status !== "fulfilled";
          });

          if (feedbackNode) {
            if (!failed.length) {
              feedbackNode.textContent =
                selectedRows.length === 1
                  ? "Tracking status saved."
                  : "Tracking statuses saved.";
            } else {
              feedbackNode.textContent =
                failed[0] && failed[0].reason && failed[0].reason.message
                  ? failed[0].reason.message
                  : "Some roles could not be updated.";
            }
          }

          if (!failed.length) {
            clearDashboardMatchesSelection(shell);
            refreshDashboardCurrentBoard(shell).catch(function () {});
          }
        })
        .finally(function () {
          setDashboardActionLoading(saveButton, false);
          saveButton.disabled = false;
          bulkBar.classList.remove("is-saving");
        });
    });
  }

  function initDashboardApp() {
    if (
      typeof window.fetch !== "function" ||
      typeof window.DOMParser !== "function"
    ) {
      return;
    }

    initDashboardSearchAutocomplete(document);
    initDashboardNewsSlideshows(document);
    filterDashboardInboxThreads(document);
    initDashboardInboxCompose(document);
    updateDashboardExpertSupportCallFields(document);
    document
      .querySelectorAll("[data-dashboard-outreach-shell]")
      .forEach(initDashboardOutreach);

    document.addEventListener("click", function (event) {
      var topbarTrigger = event.target.closest(
        "[data-dashboard-topbar-trigger]"
      );
      if (topbarTrigger) {
        var topbarShell = topbarTrigger.closest("[data-dashboard-shell]");
        var topbarType = topbarTrigger.getAttribute(
          "data-dashboard-topbar-trigger"
        );
        var topbarPanel = getDashboardTopbarPanel(topbarShell, topbarType);
        var topbarModal = getDashboardModal(topbarShell, topbarType);
        var isOpen =
          (topbarPanel &&
            !topbarPanel.hidden &&
            topbarPanel.classList.contains("is-open")) ||
          (topbarModal &&
            !topbarModal.hidden &&
            topbarModal.classList.contains("is-open"));

        event.preventDefault();
        if (!topbarShell || !topbarType) {
          return;
        }

        if (isOpen) {
          closeDashboardTopbarPanels(topbarShell);
          closeDashboardModals(topbarShell);
          return;
        }

        closeDashboardTopbarPanels(
          topbarShell,
          topbarType === "notifications" ? topbarType : ""
        );
        closeDashboardModals(
          topbarShell,
          topbarType === "messages" ? topbarType : ""
        );

        if (topbarType === "messages") {
          fetchDashboardModal(topbarShell, topbarType).catch(function () {});
          return;
        }

        fetchDashboardTopbarPanel(topbarShell, topbarType).catch(
          function () {}
        );
        return;
      }

      var inboxThreadButton = event.target.closest(
        "[data-dashboard-inbox-open]"
      );
      if (inboxThreadButton) {
        var inboxShell = inboxThreadButton.closest("[data-dashboard-shell]");
        var conversationId =
          inboxThreadButton.getAttribute("data-dashboard-inbox-open") || "";

        event.preventDefault();
        fetchDashboardModal(inboxShell, "messages", {
          conversation_id: conversationId,
        }).catch(function () {});
        return;
      }

      var composeToggle = event.target.closest(
        "[data-dashboard-compose-toggle]"
      );
      if (composeToggle) {
        var composeModalCard = composeToggle.closest(
          ".sffc-crm-dashboard-app-modal-card--messages"
        );
        var composePanel = composeModalCard
          ? composeModalCard.querySelector("[data-dashboard-compose-panel]")
          : null;
        event.preventDefault();
        if (!composeModalCard || !composePanel) {
          return;
        }

        if (
          !composePanel.hidden &&
          composePanel.classList.contains("is-open")
        ) {
          closeDashboardInboxCompose(composeModalCard);
        } else {
          openDashboardInboxCompose(composeModalCard);
        }
        return;
      }

      var composeUser = event.target.closest("[data-dashboard-compose-user]");
      if (composeUser) {
        var composeShell = composeUser.closest("[data-dashboard-shell]");
        var recipientUserId =
          composeUser.getAttribute("data-dashboard-compose-user") || "";
        var composeSubmitButton = composeUser;

        event.preventDefault();
        if (
          !composeShell ||
          !recipientUserId ||
          !redditConfig.ajaxUrl ||
          !redditConfig.accountNonce
        ) {
          return;
        }

        setDashboardActionLoading(composeSubmitButton, true, "");

        var composeBody = new window.FormData();
        composeBody.append("action", "sffc_crm_reddit_dashboard_start_message");
        composeBody.append("nonce", redditConfig.accountNonce);
        composeBody.append("recipient_user_id", recipientUserId);

        window
          .fetch(redditConfig.ajaxUrl, {
            method: "POST",
            credentials: "same-origin",
            body: composeBody,
          })
          .then(function (response) {
            return response.json();
          })
          .then(function (response) {
            if (
              !response ||
              !response.success ||
              !response.data ||
              !response.data.markup
            ) {
              throw new Error("Conversation start failed");
            }

            var modal = getDashboardModal(composeShell, "messages");
            var dialog = modal
              ? modal.querySelector(".sffc-crm-dashboard-app-modal-dialog")
              : null;
            if (!modal || !dialog) {
              throw new Error("Messages modal missing");
            }

            dialog.innerHTML = response.data.markup;
            filterDashboardInboxThreads(dialog);
            initDashboardInboxCompose(dialog);
            modal.hidden = false;
            modal.classList.add("is-open");
            modal.classList.remove("is-loading");
            modal.setAttribute("aria-hidden", "false");
            clearDashboardTopbarCache("messages");
            writeDashboardTopbarCache(
              composeShell,
              "modal",
              "messages",
              {},
              response.data
            );
            writeDashboardTopbarCache(
              composeShell,
              "modal",
              "messages",
              { conversation_id: response.data.conversation_id || "" },
              response.data
            );
            updateDashboardTopbarBadge(
              composeShell,
              "messages",
              response.data.unread_count || 0
            );
          })
          .catch(function () {})
          .finally(function () {
            setDashboardActionLoading(composeSubmitButton, false);
          });
        return;
      }

      var matchExplainButton = event.target.closest(
        "[data-dashboard-match-explainer]"
      );
      if (matchExplainButton) {
        var matchRow = matchExplainButton.closest("[data-dashboard-match-row]");
        var matchShell = matchExplainButton.closest("[data-dashboard-shell]");

        event.preventDefault();
        if (!matchRow || !matchShell) {
          return;
        }

        fetchDashboardModal(matchShell, "match-explainer", {
          wp_post_id: matchRow.getAttribute("data-wp-post-id") || "",
          crm_post_id: matchRow.getAttribute("data-post-id") || "",
        }).catch(function () {});
        return;
      }

      var atsExplainButton = event.target.closest(
        "[data-dashboard-ats-explainer]"
      );
      if (atsExplainButton) {
        var atsShell = atsExplainButton.closest("[data-dashboard-shell]");
        event.preventDefault();
        if (!atsShell) {
          return;
        }

        closeDashboardTopbarPanels(atsShell);
        closeDashboardModals(atsShell, "ats-explainer");
        fetchDashboardModal(atsShell, "ats-explainer").catch(function () {});
        return;
      }

      var cancelSubscriptionOpen = event.target.closest(
        "[data-dashboard-cancel-subscription-open]"
      );
      if (cancelSubscriptionOpen) {
        var cancelShell = cancelSubscriptionOpen.closest(
          "[data-dashboard-shell]"
        );
        event.preventDefault();
        if (!cancelShell) {
          return;
        }
        openDashboardStaticModal(cancelShell, "cancel-subscription");
        return;
      }

      var modalClose = event.target.closest("[data-dashboard-modal-close]");
      if (modalClose) {
        var modalShell = modalClose.closest("[data-dashboard-shell]");
        event.preventDefault();
        closeDashboardModals(modalShell);
        return;
      }

      var markAllButton = event.target.closest(
        "[data-dashboard-notifications-markall]"
      );
      if (markAllButton) {
        var notificationShell = markAllButton.closest("[data-dashboard-shell]");
        event.preventDefault();

        if (!redditConfig.ajaxUrl || !redditConfig.crmNonce) {
          return;
        }

        var markAllBody = new window.FormData();
        markAllBody.append("action", "sffc_crm_mark_all_notifications_read");
        markAllBody.append("nonce", redditConfig.crmNonce);

        window
          .fetch(redditConfig.ajaxUrl, {
            method: "POST",
            credentials: "same-origin",
            body: markAllBody,
          })
          .then(function () {
            clearDashboardTopbarCache("notifications");
            return fetchDashboardTopbarPanel(
              notificationShell,
              "notifications"
            );
          })
          .catch(function () {});
        return;
      }

      var notificationItem = event.target.closest(
        "[data-dashboard-notification-id]"
      );
      if (notificationItem) {
        var notificationId =
          notificationItem.getAttribute("data-dashboard-notification-id") || "";
        var notificationShell = notificationItem.closest(
          "[data-dashboard-shell]"
        );

        if (
          notificationId &&
          redditConfig.ajaxUrl &&
          redditConfig.crmNonce &&
          notificationItem.classList.contains("is-unread")
        ) {
          var markBody = new window.FormData();
          markBody.append("action", "sffc_crm_mark_notification_read");
          markBody.append("nonce", redditConfig.crmNonce);
          markBody.append("notification_id", notificationId);

          window
            .fetch(redditConfig.ajaxUrl, {
              method: "POST",
              credentials: "same-origin",
              body: markBody,
            })
            .then(function () {
              clearDashboardTopbarCache("notifications");
              notificationItem.classList.remove("is-unread");
              var currentBadge = notificationShell.querySelector(
                '[data-dashboard-topbar-trigger="notifications"] .sffc-crm-dashboard-app-topbar-badge'
              );
              var nextCount = currentBadge
                ? Math.max(0, (parseInt(currentBadge.textContent, 10) || 0) - 1)
                : 0;
              updateDashboardTopbarBadge(
                notificationShell,
                "notifications",
                nextCount
              );
            })
            .catch(function () {});
        }
      }

      var dashboardLink = event.target.closest("[data-dashboard-nav]");
      if (!dashboardLink) {
        var anyShell = event.target.closest("[data-dashboard-shell]");
        var anyMessagesModal = event.target.closest(
          ".sffc-crm-dashboard-app-modal-card--messages"
        );
        if (
          anyMessagesModal &&
          !event.target.closest("[data-dashboard-compose-panel]")
        ) {
          closeDashboardInboxCompose(anyMessagesModal);
        }
        if (
          anyShell &&
          !event.target.closest("[data-dashboard-topbar-panel]") &&
          !event.target.closest("[data-dashboard-modal-dialog]")
        ) {
          closeDashboardTopbarPanels(anyShell);
        }
        return;
      }

      var shell = dashboardLink.closest("[data-dashboard-shell]");
      if (!shell) {
        return;
      }

      var href = dashboardLink.getAttribute("href");
      if (!href) {
        return;
      }
      var shouldAppend = dashboardLink.hasAttribute("data-dashboard-append");

      event.preventDefault();

      if (shell.classList.contains("is-loading")) {
        return;
      }

      var board = shell.querySelector("[data-dashboard-board]");
      if (!board) {
        window.location.href = href;
        return;
      }

      if (!shouldAppend) {
        var cachedDashboard = readDashboardTabCache(href);
        if (cachedDashboard && cachedDashboard.markup) {
          try {
            applyDashboardMarkup(
              shell,
              board,
              href,
              cachedDashboard.markup,
              false
            );
            syncDashboardMatchesBulkBar(shell);
            return;
          } catch (error) {
            delete dashboardTabCache[getDashboardCacheKey(href)];
          }
        }
      }

      shell.classList.add("is-loading");
      board.classList.add("is-loading");
      showDashboardBoardSkeleton(board, getDashboardTabFromHref(href));
      setDashboardActionLoading(
        dashboardLink,
        true,
        shouldAppend ? "Loading..." : ""
      );

      if (!redditConfig.ajaxUrl || !redditConfig.dashboardTabNonce) {
        window.location.href = href;
        return;
      }

      var body = new window.FormData();
      body.append("action", "sffc_crm_reddit_dashboard_tab");
      body.append("nonce", redditConfig.dashboardTabNonce);
      body.append("href", href);
      body.append(
        "jobs_post_id",
        shell.getAttribute("data-dashboard-jobs-post-id") || ""
      );
      body.append(
        "per_page",
        shell.getAttribute("data-dashboard-per-page") || ""
      );
      body.append(
        "fallback_role",
        shell.getAttribute("data-dashboard-fallback-role") || ""
      );
      body.append(
        "current_url",
        shell.getAttribute("data-dashboard-current-url") || ""
      );

      window
        .fetch(redditConfig.ajaxUrl, {
          method: "POST",
          credentials: "same-origin",
          body: body,
        })
        .then(function (response) {
          return response.json();
        })
        .then(function (response) {
          if (
            !response ||
            !response.success ||
            !response.data ||
            !response.data.markup
          ) {
            throw new Error("Dashboard refresh failed");
          }

          applyDashboardMarkup(
            shell,
            board,
            href,
            response.data.markup,
            shouldAppend
          );
          syncDashboardMatchesBulkBar(shell);
        })
        .catch(function () {
          window.location.href = href;
        })
        .finally(function () {
          setDashboardActionLoading(dashboardLink, false);
          shell.classList.remove("is-loading");
          board.classList.remove("is-loading");
          clearDashboardBoardSkeleton(board);
        });
    });

    document.addEventListener("submit", function (event) {
      var expertSupportForm = event.target.closest(
        "[data-dashboard-expert-support-form]"
      );
      if (expertSupportForm) {
        event.preventDefault();
        submitDashboardExpertSupportForm(expertSupportForm);
        return;
      }

      var cancelSubscriptionForm = event.target.closest(
        "[data-dashboard-cancel-subscription-form]"
      );
      if (cancelSubscriptionForm) {
        event.preventDefault();
        submitDashboardCancelSubscriptionForm(cancelSubscriptionForm);
        return;
      }

      var form = event.target.closest("[data-dashboard-inbox-form]");
      if (!form) {
        return;
      }

      var shell = form.closest("[data-dashboard-shell]");
      var submitButton = form.querySelector('button[type="submit"]');
      var bodyField = form.querySelector('textarea[name="body"]');
      var conversationField = form.querySelector(
        'input[name="conversation_id"]'
      );

      event.preventDefault();

      if (
        !shell ||
        !bodyField ||
        !conversationField ||
        !redditConfig.ajaxUrl ||
        !redditConfig.accountNonce
      ) {
        return;
      }

      var messageText = (bodyField.value || "").trim();
      if (!messageText) {
        return;
      }

      setDashboardActionLoading(submitButton, true, "Sending...");

      var body = new window.FormData();
      body.append("action", "sffc_crm_reddit_dashboard_send_message");
      body.append("nonce", redditConfig.accountNonce);
      body.append("conversation_id", conversationField.value || "");
      body.append("body", messageText);

      window
        .fetch(redditConfig.ajaxUrl, {
          method: "POST",
          credentials: "same-origin",
          body: body,
        })
        .then(function (response) {
          return response.json();
        })
        .then(function (response) {
          if (
            !response ||
            !response.success ||
            !response.data ||
            !response.data.markup
          ) {
            throw new Error("Message send failed");
          }
          var modal = getDashboardModal(shell, "messages");
          var dialog = modal
            ? modal.querySelector(".sffc-crm-dashboard-app-modal-dialog")
            : null;
          if (modal && dialog) {
            dialog.innerHTML = response.data.markup;
            filterDashboardInboxThreads(dialog);
            initDashboardInboxCompose(dialog);
            modal.hidden = false;
            modal.classList.add("is-open");
            modal.classList.remove("is-loading");
            modal.setAttribute("aria-hidden", "false");
            clearDashboardTopbarCache("messages");
            writeDashboardTopbarCache(
              shell,
              "modal",
              "messages",
              {},
              response.data
            );
            writeDashboardTopbarCache(
              shell,
              "modal",
              "messages",
              { conversation_id: conversationField.value || "" },
              response.data
            );
          }
          updateDashboardTopbarBadge(
            shell,
            "messages",
            response.data.unread_count || 0
          );
        })
        .catch(function () {})
        .finally(function () {
          setDashboardActionLoading(submitButton, false);
        });
    });

    document.addEventListener("change", function (event) {
      if (
        !event.target.closest("[data-dashboard-expert-support-call-toggle]")
      ) {
        return;
      }

      updateDashboardExpertSupportCallFields(
        event.target.closest("[data-dashboard-expert-support-form]") || document
      );
    });

    document.addEventListener("keydown", function (event) {
      if (event.key !== "Escape") {
        return;
      }

      document
        .querySelectorAll("[data-dashboard-shell]")
        .forEach(function (shell) {
          closeDashboardTopbarPanels(shell);
          closeDashboardModals(shell);
        });
    });
  }

  function applyDashboardMarkup(shell, board, href, markup, shouldAppend) {
    var container = document.createElement("div");
    container.innerHTML = markup;
    var nextShell = container.querySelector("[data-dashboard-shell]");
    var nextBoard = container.querySelector("[data-dashboard-board]");

    if (!nextShell || !nextBoard) {
      throw new Error("Dashboard content missing");
    }

    if (shouldAppend) {
      var currentMatchesBody = board.querySelector(
        ".sffc-crm-dashboard-app-matches-body"
      );
      var nextMatchesBody = nextBoard.querySelector(
        ".sffc-crm-dashboard-app-matches-body"
      );
      var currentPagination = board.querySelector(
        "[data-dashboard-matches-pagination]"
      );
      var nextPagination = nextBoard.querySelector(
        "[data-dashboard-matches-pagination]"
      );

      if (!currentMatchesBody || !nextMatchesBody) {
        throw new Error("Dashboard matches content missing");
      }

      Array.prototype.slice
        .call(nextMatchesBody.children)
        .forEach(function (child) {
          currentMatchesBody.appendChild(child);
        });

      if (currentPagination && nextPagination) {
        currentPagination.innerHTML = nextPagination.innerHTML;
      }

      return;
    }

    clearDashboardBoardSkeleton(board);
    board.innerHTML = nextBoard.innerHTML;

    [
      "data-dashboard-active-tab",
      "data-dashboard-jobs-post-id",
      "data-dashboard-per-page",
      "data-dashboard-fallback-role",
      "data-dashboard-current-url",
    ].forEach(function (attributeName) {
      var nextValue = nextShell.getAttribute(attributeName);
      if (nextValue !== null) {
        shell.setAttribute(attributeName, nextValue);
      }
    });

    syncDashboardNavState(
      shell,
      nextShell.getAttribute("data-dashboard-active-tab") ||
        getDashboardTabFromHref(href)
    );

    var currentMain = shell.querySelector(".sffc-crm-dashboard-app-main");
    var nextMain = nextShell.querySelector(".sffc-crm-dashboard-app-main");
    if (currentMain && nextMain) {
      currentMain.className = nextMain.className;
    }

    var currentTopbar = shell.querySelector(".sffc-crm-dashboard-app-topbar");
    var nextTopbar = nextShell.querySelector(".sffc-crm-dashboard-app-topbar");
    if (currentTopbar && nextTopbar) {
      currentTopbar.outerHTML = nextTopbar.outerHTML;
    } else if (currentTopbar && !nextTopbar) {
      currentTopbar.remove();
    } else if (!currentTopbar && nextTopbar && currentMain) {
      currentMain.insertAdjacentHTML("afterbegin", nextTopbar.outerHTML);
    }

    var currentMessagesModal = getDashboardModal(shell, "messages");
    var nextMessagesModal = getDashboardModal(nextShell, "messages");
    if (currentMessagesModal && nextMessagesModal) {
      currentMessagesModal.outerHTML = nextMessagesModal.outerHTML;
    } else if (currentMessagesModal && !nextMessagesModal) {
      currentMessagesModal.remove();
    } else if (!currentMessagesModal && nextMessagesModal && currentMain) {
      currentMain.insertAdjacentHTML("beforeend", nextMessagesModal.outerHTML);
    }

    var currentCancelSubscriptionModal = getDashboardModal(
      shell,
      "cancel-subscription"
    );
    var nextCancelSubscriptionModal = getDashboardModal(
      nextShell,
      "cancel-subscription"
    );
    if (currentCancelSubscriptionModal && nextCancelSubscriptionModal) {
      currentCancelSubscriptionModal.outerHTML =
        nextCancelSubscriptionModal.outerHTML;
    } else if (currentCancelSubscriptionModal && !nextCancelSubscriptionModal) {
      currentCancelSubscriptionModal.remove();
    } else if (
      !currentCancelSubscriptionModal &&
      nextCancelSubscriptionModal &&
      currentMain
    ) {
      currentMain.insertAdjacentHTML(
        "beforeend",
        nextCancelSubscriptionModal.outerHTML
      );
    }

    board.className = nextBoard.className;

    var currentSearchForm = shell.querySelector(
      ".sffc-crm-dashboard-app-search"
    );
    var nextSearchForm = nextShell.querySelector(
      ".sffc-crm-dashboard-app-search"
    );
    if (currentSearchForm && nextSearchForm) {
      currentSearchForm.setAttribute(
        "action",
        nextSearchForm.getAttribute("action") ||
          currentSearchForm.getAttribute("action") ||
          ""
      );
      var currentSearchInput = currentSearchForm.querySelector(
        'input[name="sffc_reddit_search"]'
      );
      var nextSearchInput = nextSearchForm.querySelector(
        'input[name="sffc_reddit_search"]'
      );
      if (currentSearchInput && nextSearchInput) {
        currentSearchInput.value = nextSearchInput.value;
      }
      var currentTabInput = currentSearchForm.querySelector(
        'input[name="dashboard_tab"]'
      );
      var nextTabInput = nextSearchForm.querySelector(
        'input[name="dashboard_tab"]'
      );
      if (currentTabInput && nextTabInput) {
        currentTabInput.value = nextTabInput.value;
      }
      var currentMatchesPageInput = currentSearchForm.querySelector(
        'input[name="matches_page"]'
      );
      var nextMatchesPageInput = nextSearchForm.querySelector(
        'input[name="matches_page"]'
      );
      if (currentMatchesPageInput && nextMatchesPageInput) {
        currentMatchesPageInput.value = nextMatchesPageInput.value;
      }
      var currentMetricsWindowInput = currentSearchForm.querySelector(
        'input[name="metrics_window"]'
      );
      var nextMetricsWindowInput = nextSearchForm.querySelector(
        'input[name="metrics_window"]'
      );
      if (currentMetricsWindowInput && nextMetricsWindowInput) {
        currentMetricsWindowInput.value = nextMetricsWindowInput.value;
      }
    }

    initDashboardSearchAutocomplete(shell);
    initDashboardNewsSlideshows(shell);
    updateDashboardExpertSupportCallFields(shell);
    shell
      .querySelectorAll("[data-dashboard-outreach-shell]")
      .forEach(initDashboardOutreach);

    writeDashboardTabCache(href, markup);

    if (window.history && window.history.pushState) {
      window.history.pushState({ dashboardUrl: href }, "", href);
    }
  }

  function initGroupNav() {
    document
      .querySelectorAll(".sffc-crm-reddit-shell")
      .forEach(function (shell) {
        if (!redditConfig.isLoggedIn) {
          var storedPreferences = getStoredGroupPrefs();
          if (storedPreferences) {
            applyGroupPreferences(shell, storedPreferences);
          }
        }

        shell
          .querySelectorAll("[data-reddit-group-item][data-group-slug]")
          .forEach(function (item) {
            var slug = item.getAttribute("data-group-slug") || "";
            var link = item.querySelector(".sffc-crm-reddit-nav-link");
            var pinButton = item.querySelector("[data-group-pin]");

            if (link) {
              link.setAttribute("draggable", "false");
            }

            if (pinButton) {
              pinButton.setAttribute("draggable", "false");
            }

            if (!slug) {
              return;
            }

            item.addEventListener("dragstart", function (event) {
              draggingGroupItem = item;
              item.classList.add("is-dragging");
              if (event.dataTransfer) {
                event.dataTransfer.effectAllowed = "move";
                event.dataTransfer.setData("text/plain", slug);
              }
            });

            item.addEventListener("dragover", function (event) {
              handleGroupDragOver(event, item);
            });

            item.addEventListener("drop", function (event) {
              if (!draggingGroupItem) {
                return;
              }

              event.preventDefault();
              var shellNode = item.closest(".sffc-crm-reddit-shell");
              if (shellNode) {
                clearDragTargets(shellNode);
                syncGroupPreferences(shellNode);
              }
            });

            item.addEventListener("dragend", function () {
              var shellNode = item.closest(".sffc-crm-reddit-shell");
              item.classList.remove("is-dragging");
              if (shellNode) {
                clearDragTargets(shellNode);
                syncGroupPreferences(shellNode);
              }
              draggingGroupItem = null;
            });
          });
      });
  }

  function closeAutocomplete(field) {
    var input = field.querySelector("[data-reddit-search-input]");
    var dropdown = field.querySelector("[data-reddit-search-dropdown]");

    if (!input || !dropdown) {
      return;
    }

    dropdown.hidden = true;
    input.setAttribute("aria-expanded", "false");
    dropdown
      .querySelectorAll(".sffc-crm-reddit-search-option")
      .forEach(function (option) {
        option.classList.remove("is-active");
      });
  }

  function openAutocomplete(field) {
    var input = field.querySelector("[data-reddit-search-input]");
    var dropdown = field.querySelector("[data-reddit-search-dropdown]");

    if (!input || !dropdown) {
      return;
    }

    var visibleCount = 0;
    dropdown
      .querySelectorAll(".sffc-crm-reddit-search-option")
      .forEach(function (option) {
        if (!option.hidden) {
          visibleCount += 1;
        }
      });

    dropdown.hidden = visibleCount === 0;
    input.setAttribute("aria-expanded", visibleCount > 0 ? "true" : "false");
  }

  function filterAutocomplete(field, query) {
    var dropdown = field.querySelector("[data-reddit-search-dropdown]");
    if (!dropdown) {
      return;
    }

    var normalizedQuery = (query || "").trim().toLowerCase();

    dropdown
      .querySelectorAll(".sffc-crm-reddit-search-option")
      .forEach(function (option) {
        var value = (
          option.getAttribute("data-suggestion-value") || ""
        ).toLowerCase();
        var type = (
          option.getAttribute("data-suggestion-type") || ""
        ).toLowerCase();
        var matches =
          normalizedQuery === "" ||
          value.indexOf(normalizedQuery) !== -1 ||
          type.indexOf(normalizedQuery) !== -1;
        option.hidden = !matches;
        option.classList.remove("is-active");
      });

    openAutocomplete(field);
  }

  function activateAutocompleteOption(field, direction) {
    var dropdown = field.querySelector("[data-reddit-search-dropdown]");
    if (!dropdown || dropdown.hidden) {
      return null;
    }

    var options = Array.prototype.slice.call(
      dropdown.querySelectorAll(".sffc-crm-reddit-search-option:not([hidden])")
    );
    if (!options.length) {
      return null;
    }

    var activeIndex = options.findIndex(function (option) {
      return option.classList.contains("is-active");
    });

    if (activeIndex === -1) {
      activeIndex = direction > 0 ? 0 : options.length - 1;
    } else {
      activeIndex = (activeIndex + direction + options.length) % options.length;
    }

    options.forEach(function (option) {
      option.classList.remove("is-active");
    });

    options[activeIndex].classList.add("is-active");
    options[activeIndex].scrollIntoView({ block: "nearest" });

    return options[activeIndex];
  }

  function chooseAutocompleteOption(field, option) {
    var input = field.querySelector("[data-reddit-search-input]");
    if (!input || !option) {
      return;
    }

    input.value = option.getAttribute("data-suggestion-value") || "";
    closeAutocomplete(field);
    var shouldSubmit =
      field.getAttribute("data-reddit-search-submit-on-select") === "true";

    if (shouldSubmit) {
      var form = field.closest("form");
      if (form) {
        form.requestSubmit ? form.requestSubmit() : form.submit();
        return;
      }
    }

    input.focus();
  }

  function initAutocomplete() {
    document
      .querySelectorAll("[data-reddit-search-field]")
      .forEach(function (field) {
        var input = field.querySelector("[data-reddit-search-input]");
        var dropdown = field.querySelector("[data-reddit-search-dropdown]");

        if (!input || !dropdown) {
          return;
        }

        input.addEventListener("focus", function () {
          filterAutocomplete(field, input.value);
        });

        input.addEventListener("input", function () {
          filterAutocomplete(field, input.value);
        });

        input.addEventListener("keydown", function (event) {
          if (event.key === "ArrowDown") {
            event.preventDefault();
            activateAutocompleteOption(field, 1);
            openAutocomplete(field);
            return;
          }

          if (event.key === "ArrowUp") {
            event.preventDefault();
            activateAutocompleteOption(field, -1);
            openAutocomplete(field);
            return;
          }

          if (event.key === "Enter" && !dropdown.hidden) {
            var activeOption = dropdown.querySelector(
              ".sffc-crm-reddit-search-option.is-active"
            );
            if (activeOption) {
              event.preventDefault();
              chooseAutocompleteOption(field, activeOption);
            }
          }
        });
      });
  }

  function closeProfileMenus(exceptShell) {
    document
      .querySelectorAll(".sffc-crm-reddit-shell [data-reddit-profile]")
      .forEach(function (profile) {
        var shell = profile.closest(".sffc-crm-reddit-shell");
        if (exceptShell && shell === exceptShell) {
          return;
        }

        var toggle = profile.querySelector("[data-reddit-profile-toggle]");
        var menu = profile.querySelector("[data-reddit-profile-menu]");
        if (toggle) {
          toggle.setAttribute("aria-expanded", "false");
        }
        if (menu) {
          menu.hidden = true;
        }
      });
  }

  function toggleProfileMenu(shell) {
    if (!shell) {
      return;
    }

    var profile = shell.querySelector("[data-reddit-profile]");
    if (!profile) {
      return;
    }

    var toggle = profile.querySelector("[data-reddit-profile-toggle]");
    var menu = profile.querySelector("[data-reddit-profile-menu]");
    if (!toggle || !menu) {
      return;
    }

    var willOpen = menu.hidden;
    closeProfileMenus(willOpen ? shell : null);
    menu.hidden = !willOpen;
    toggle.setAttribute("aria-expanded", willOpen ? "true" : "false");
  }

  function openAccountPanel(shell, panelName) {
    if (!shell || !panelName) {
      return;
    }

    var accountShell = shell.querySelector("[data-reddit-account-shell]");
    if (!accountShell) {
      return;
    }

    accountShell.hidden = false;
    accountShell.classList.add("is-open");
    shell.classList.add("is-account-view");

    accountShell
      .querySelectorAll("[data-reddit-account-panel]")
      .forEach(function (panel) {
        var isActive =
          panel.getAttribute("data-reddit-account-panel") === panelName;
        panel.hidden = !isActive;
        panel.classList.toggle("is-active", isActive);
      });

    accountShell
      .querySelectorAll("[data-reddit-account-target]")
      .forEach(function (button) {
        button.classList.toggle(
          "is-active",
          button.getAttribute("data-reddit-account-target") === panelName
        );
      });

    closeProfileMenus();
  }

  function closeAccountShell(shell) {
    if (!shell) {
      return;
    }

    var accountShell = shell.querySelector("[data-reddit-account-shell]");
    if (!accountShell) {
      return;
    }

    accountShell.hidden = true;
    accountShell.classList.remove("is-open");
    shell.classList.remove("is-account-view");
  }

  function setProfileFeedback(panel, message, isError) {
    if (!panel) {
      return;
    }

    var feedback = panel.querySelector("[data-reddit-profile-feedback]");
    if (!feedback) {
      return;
    }

    feedback.hidden = !message;
    feedback.textContent = message || "";
    feedback.classList.toggle("is-error", !!isError);
    feedback.classList.toggle("is-success", !!message && !isError);
  }

  function replaceProfilePanel(shell, markup) {
    if (!shell || !markup) {
      return null;
    }

    var currentPanel = shell.querySelector(
      '[data-reddit-account-panel="profile"]'
    );
    if (!currentPanel) {
      return null;
    }

    currentPanel.outerHTML = markup;
    return shell.querySelector('[data-reddit-account-panel="profile"]');
  }

  function replaceDashboardReview(shell, markup) {
    if (!shell || !markup) {
      return null;
    }

    var currentReview = shell.querySelector("[data-dashboard-review-shell]");
    if (!currentReview) {
      return null;
    }

    currentReview.outerHTML = markup;
    return shell.querySelector("[data-dashboard-review-shell]");
  }

  function replaceDashboardProfile(shell, markup) {
    if (!shell || !markup) {
      return null;
    }

    var currentProfile =
      shell.matches && shell.matches("[data-dashboard-profile-shell]")
        ? shell
        : shell.querySelector("[data-dashboard-profile-shell]");
    if (!currentProfile) {
      return null;
    }

    var parent = currentProfile.parentNode;
    currentProfile.outerHTML = markup;
    return shell.matches && shell.matches("[data-dashboard-profile-shell]")
      ? parent
        ? parent.querySelector("[data-dashboard-profile-shell]")
        : null
      : shell.querySelector("[data-dashboard-profile-shell]");
  }

  function updateDashboardShellAvatar(shell, url) {
    if (!shell || !url) {
      return;
    }

    shell
      .querySelectorAll(
        ".sffc-crm-dashboard-app-profile-avatar, [data-dashboard-profile-avatar-media], [data-community-profile-avatar-media], .sffc-crm-apply-chat__app-rail-profile"
      )
      .forEach(function (node) {
        node.classList.add("has-image");
        node.classList.remove("is-guest");
        node.innerHTML =
          '<img src="' +
          url.replace(/"/g, "&quot;") +
          '" alt="Profile avatar">';
      });
  }

  function updateDashboardShellCover(shell, url) {
    if (!shell || !url) {
      return;
    }

    shell
      .querySelectorAll("[data-dashboard-profile-cover]")
      .forEach(function (node) {
        node.style.backgroundImage =
          'linear-gradient(180deg, rgba(0,0,0,0.08), rgba(0,0,0,0.18)), url("' +
          url.replace(/"/g, "&quot;") +
          '")';
      });
  }

  function getDashboardProfileTagValues(form, fieldName) {
    var values = [];
    var seen = {};
    var containers;

    if (!form || !fieldName) {
      return [];
    }

    containers = form.querySelectorAll(
      '[data-profile-tags][data-field="' + fieldName + '"]'
    );

    Array.prototype.forEach.call(containers, function (container) {
      Array.prototype.forEach.call(
        container.querySelectorAll("[data-profile-tag]"),
        function (tag) {
          var value = (tag.getAttribute("data-value") || "").trim();
          var key = value.toLowerCase();
          if (value && !seen[key]) {
            seen[key] = true;
            values.push(value);
          }
        }
      );
    });

    return values;
  }

  function createDashboardProfileTag(fieldContainer, value) {
    if (!fieldContainer || !value) {
      return null;
    }

    var normalizedValue = value.trim();
    if (!normalizedValue) {
      return null;
    }

    var existing = getDashboardProfileTagValues(
      fieldContainer.closest("[data-dashboard-profile-form]"),
      fieldContainer.getAttribute("data-field")
    ).map(function (entry) {
      return entry.toLowerCase();
    });

    if (existing.indexOf(normalizedValue.toLowerCase()) !== -1) {
      return null;
    }

    var tag = document.createElement("span");
    tag.className = "sffc-crm-dashboard-app-profile-tag";
    tag.setAttribute("data-profile-tag", "");
    tag.setAttribute("data-value", normalizedValue);
    tag.innerHTML =
      '<span></span><button type="button" class="sffc-crm-dashboard-app-profile-tag-remove" data-dashboard-profile-tag-remove aria-label="Remove value">&times;</button>';
    tag.querySelector("span").textContent = normalizedValue;

    var addButton = fieldContainer.querySelector(
      "[data-dashboard-profile-add]"
    );
    if (addButton) {
      fieldContainer.insertBefore(tag, addButton);
    } else {
      fieldContainer.appendChild(tag);
    }

    return tag;
  }

  function openDashboardProfileTagInput(button) {
    var container = button ? button.closest("[data-profile-tags]") : null;
    if (!container) {
      return;
    }

    var existingInput = container.querySelector(
      ".sffc-crm-dashboard-app-profile-tag-input"
    );
    if (existingInput) {
      existingInput.focus();
      return;
    }

    var input = document.createElement("input");
    input.type = "text";
    input.className = "sffc-crm-dashboard-app-profile-tag-input";
    input.placeholder =
      container.getAttribute("data-add-label") || "Type and press Enter";

    button.hidden = true;
    container.insertBefore(input, button);
    input.focus();
  }

  function closeDashboardProfileTagInput(input, shouldCreateTag) {
    if (!input) {
      return;
    }

    var container = input.closest("[data-profile-tags]");
    var addButton = container
      ? container.querySelector("[data-dashboard-profile-add]")
      : null;
    var value = input.value ? input.value.trim() : "";

    if (shouldCreateTag && container && value) {
      createDashboardProfileTag(container, value);
    }

    input.remove();
    if (addButton) {
      addButton.hidden = false;
    }
  }

  function closeDashboardProfileFilters(root) {
    var scope = root || document;
    Array.prototype.forEach.call(
      scope.querySelectorAll("[data-dashboard-profile-filter]"),
      function (filter) {
        var button = filter.querySelector("[data-dashboard-profile-filter-toggle]");
        var menu = filter.querySelector("[data-dashboard-profile-filter-menu]");
        filter.classList.remove("is-open");
        if (button) {
          button.setAttribute("aria-expanded", "false");
        }
        if (menu) {
          menu.hidden = true;
        }
      }
    );
  }

  function toggleDashboardProfileFilter(button) {
    var filter = button ? button.closest("[data-dashboard-profile-filter]") : null;
    if (!filter) {
      return;
    }

    var shell = filter.closest("[data-dashboard-profile-shell]") || document;
    var menu = filter.querySelector("[data-dashboard-profile-filter-menu]");
    var isOpen = filter.classList.contains("is-open");

    closeDashboardProfileFilters(shell);
    if (!isOpen && menu) {
      filter.classList.add("is-open");
      button.setAttribute("aria-expanded", "true");
      menu.hidden = false;
    }
  }

  function refreshDashboardProfileFilterButton(filter, label) {
    var button = filter ? filter.querySelector("[data-dashboard-profile-filter-toggle]") : null;
    var labelNode = button ? button.querySelector("span") : null;
    if (!button || !labelNode || !label) {
      return;
    }

    labelNode.textContent = label.length > 28 ? label.slice(0, 25) + "..." : label;
    button.classList.add("is-active");
  }

  function selectDashboardProfileFilterOption(option) {
    var filter = option ? option.closest("[data-dashboard-profile-filter]") : null;
    var form = option ? option.closest("[data-dashboard-profile-form]") : null;
    var fieldName = option ? option.getAttribute("data-field") || "" : "";
    var mode = option ? option.getAttribute("data-mode") || "tag" : "tag";
    var value = option ? option.getAttribute("data-value") || "" : "";
    var label = option ? option.getAttribute("data-label") || value : value;
    var fieldContainer;
    var checkbox;

    if (!filter || !form || !fieldName || !value) {
      return;
    }

    if (mode === "checkbox") {
      checkbox = form.querySelector(
        'input[name="' + fieldName + '[]"][value="' + escapeSelector(value) + '"]'
      );
      if (checkbox) {
        checkbox.checked = true;
        checkbox.dispatchEvent(new Event("change", { bubbles: true }));
      }
    } else {
      fieldContainer = form.querySelector(
        '[data-profile-tags][data-field="' + fieldName + '"]'
      );
      if (fieldContainer) {
        createDashboardProfileTag(fieldContainer, label);
      }
    }

    Array.prototype.forEach.call(
      filter.querySelectorAll("[data-dashboard-profile-filter-option]"),
      function (item) {
        var isSelected = item === option;
        item.classList.toggle("is-selected", isSelected);
        item.setAttribute("aria-selected", isSelected ? "true" : "false");
      }
    );
    refreshDashboardProfileFilterButton(filter, label);
    closeDashboardProfileFilters(filter.closest("[data-dashboard-profile-shell]") || document);
  }

  function activateDashboardProfileTab(shell, tabName) {
    if (!shell || !tabName) {
      return;
    }

    shell.setAttribute("data-active-profile-tab", tabName);

    shell
      .querySelectorAll("[data-dashboard-profile-tab]")
      .forEach(function (button) {
        var isActive =
          (button.getAttribute("data-dashboard-profile-tab") || "") === tabName;
        button.classList.toggle("is-active", isActive);
        button.setAttribute("aria-selected", isActive ? "true" : "false");
      });

    shell
      .querySelectorAll("[data-dashboard-profile-panel]")
      .forEach(function (panel) {
        var isActive =
          (panel.getAttribute("data-dashboard-profile-panel") || "") ===
          tabName;
        panel.classList.toggle("is-active", isActive);
        panel.hidden = !isActive;
      });
  }

  function submitDashboardProfileForm(form) {
    var shell = form ? form.closest("[data-dashboard-shell]") : null;
    var panel = form ? form.closest("[data-dashboard-profile-shell]") : null;
    var activeTabButton = panel
      ? panel.querySelector("[data-dashboard-profile-tab].is-active")
      : null;
    var activeTabName = activeTabButton
      ? activeTabButton.getAttribute("data-dashboard-profile-tab") || "overview"
      : "overview";
    var saveButtons = form
      ? form.querySelectorAll(".sffc-crm-dashboard-app-profile-save")
      : [];
    var profileNonce = redditConfig.accountNonce || "";

    if (
      !shell ||
      !panel ||
      !redditConfig.ajaxUrl ||
      !profileNonce ||
      typeof window.fetch !== "function"
    ) {
      setProfileFeedback(panel, "Profile save is unavailable right now.", true);
      return;
    }

    var body = new window.FormData();
    body.append("action", "sffc_crm_reddit_save_profile");
    body.append("nonce", profileNonce);

    var headlineNode = form.querySelector('[name="headline"]');
    if (headlineNode) {
      body.append("headline", headlineNode.value || "");
    }

    var aboutNode = form.querySelector('[name="about"]');
    if (aboutNode) {
      body.append("about", aboutNode.value || "");
    }

    var frequencyNode = form.querySelector('[name="alert_frequency"]:checked');
    if (frequencyNode) {
      body.append("alert_frequency", frequencyNode.value || "daily");
    }

    form
      .querySelectorAll('input[name="target_sectors[]"]:checked')
      .forEach(function (input) {
        body.append("target_sectors[]", input.value || "");
      });

    [
      "target_roles",
      "target_locations",
      "technical_skills",
      "qualifications",
      "languages",
      "excluded_roles",
      "excluded_locations",
      "excluded_sectors",
    ].forEach(function (fieldName) {
      getDashboardProfileTagValues(form, fieldName).forEach(function (value) {
        body.append(fieldName + "[]", value);
      });
    });

    setProfileFeedback(panel, "Saving your profile...", false);
    Array.prototype.forEach.call(saveButtons, function (button) {
      setDashboardActionLoading(button, true, "Saving...");
      button.disabled = true;
    });

    window
      .fetch(redditConfig.ajaxUrl, {
        method: "POST",
        credentials: "same-origin",
        body: body,
      })
      .then(function (response) {
        return response.json();
      })
      .then(function (response) {
        if (
          !response ||
          !response.success ||
          !response.data ||
          !response.data.markup
        ) {
          throw new Error(
            response && response.data && response.data.message
              ? response.data.message
              : "We could not save that profile."
          );
        }

        var nextPanel = replaceDashboardProfile(shell, response.data.markup);
        if (nextPanel) {
          activateDashboardProfileTab(nextPanel, activeTabName);
        }
        clearDashboardTabCache();
        setProfileFeedback(
          nextPanel,
          response.data.message || "Profile updated successfully.",
          false
        );
      })
      .catch(function (error) {
        setProfileFeedback(
          panel,
          error && error.message
            ? error.message
            : "We could not save that profile.",
          true
        );
      })
      .finally(function () {
        Array.prototype.forEach.call(saveButtons, function (button) {
          setDashboardActionLoading(button, false);
          button.disabled = false;
        });
      });
  }

  function submitDashboardProfileImage(input) {
    var shell = input
      ? input.closest("[data-dashboard-shell], [data-sffc-community-editorial]")
      : null;
    var panel = input
      ? input.closest(
          "[data-dashboard-profile-shell], [data-community-profile-shell]"
        )
      : null;
    var file = input && input.files ? input.files[0] : null;
    var isCover = !!(input && input.matches("[data-dashboard-profile-cover-input]"));

    if (
      !shell ||
      !panel ||
      !file ||
      !redditConfig.ajaxUrl ||
      !redditConfig.avatarNonce ||
      typeof window.fetch !== "function"
    ) {
      setProfileFeedback(panel, "Choose an image to upload.", true);
      return;
    }

    var body = new window.FormData();
    body.append("action", "sffc_crm_upload_avatar");
    body.append("nonce", redditConfig.avatarNonce);
    body.append(isCover ? "cover_file" : "avatar_file", file);

    setProfileFeedback(panel, isCover ? "Uploading your cover image..." : "Uploading your avatar...", false);

    window
      .fetch(redditConfig.ajaxUrl, {
        method: "POST",
        credentials: "same-origin",
        body: body,
      })
      .then(function (response) {
        return response.json();
      })
      .then(function (response) {
        if (
          !response ||
          !response.success ||
          !response.data ||
          !response.data.url
        ) {
          throw new Error(
            response && response.data && response.data.message
              ? response.data.message
              : isCover
                ? "We could not upload that cover image."
                : "We could not upload that avatar."
          );
        }

        if ((response.data.kind || "") === "cover" || isCover) {
          updateDashboardShellCover(shell, response.data.url);
        } else {
          updateDashboardShellAvatar(shell, response.data.url);
        }
        clearDashboardTabCache();
        setProfileFeedback(panel, isCover ? "Cover image updated." : "Avatar updated.", false);
      })
      .catch(function (error) {
        setProfileFeedback(
          panel,
          error && error.message
            ? error.message
            : isCover
              ? "We could not upload that cover image."
              : "We could not upload that avatar.",
          true
        );
      })
      .finally(function () {
        input.value = "";
      });
  }

  function refreshProfilePanel(shell, message, isError) {
    if (
      !shell ||
      !redditConfig.ajaxUrl ||
      !redditConfig.accountNonce ||
      typeof window.fetch !== "function"
    ) {
      return Promise.resolve(null);
    }

    var body = new window.FormData();
    body.append("action", "sffc_crm_reddit_refresh_profile_panel");
    body.append("nonce", redditConfig.accountNonce);

    return window
      .fetch(redditConfig.ajaxUrl, {
        method: "POST",
        credentials: "same-origin",
        body: body,
      })
      .then(function (response) {
        return response.json();
      })
      .then(function (response) {
        if (
          !response ||
          !response.success ||
          !response.data ||
          !response.data.markup
        ) {
          throw new Error(
            response && response.data && response.data.message
              ? response.data.message
              : "Unable to refresh your profile panel."
          );
        }

        var panel = replaceProfilePanel(shell, response.data.markup);
        openAccountPanel(shell, "profile");
        setProfileFeedback(panel, message || "", !!isError);
        return panel;
      });
  }

  function saveActiveResume(shell, uploadId, successMessage) {
    if (
      !shell ||
      !uploadId ||
      !redditConfig.ajaxUrl ||
      !redditConfig.accountNonce ||
      typeof window.fetch !== "function"
    ) {
      return Promise.reject(
        new Error("Resume selection is unavailable right now.")
      );
    }

    var body = new window.FormData();
    body.append("action", "sffc_crm_reddit_set_active_resume");
    body.append("nonce", redditConfig.accountNonce);
    body.append("upload_id", uploadId);

    return window
      .fetch(redditConfig.ajaxUrl, {
        method: "POST",
        credentials: "same-origin",
        body: body,
      })
      .then(function (response) {
        return response.json();
      })
      .then(function (response) {
        if (!response || !response.success || !response.data) {
          throw new Error(
            response && response.data && response.data.message
              ? response.data.message
              : "Unable to update your active resume."
          );
        }

        if (response.data.active_cv_text && window.CustomEvent) {
          window.dispatchEvent(
            new window.CustomEvent("sffc:cv-updated", {
              detail: {
                cvText: String(response.data.active_cv_text || ""),
                atsState: response.data.ats_state || null,
                source: "review_cv",
              },
            })
          );
        }

        var inDashboardReview = !!shell.querySelector(
          "[data-dashboard-review-shell]"
        );
        if (inDashboardReview) {
          var dashboardReviewShell = response.data.review_markup
            ? replaceDashboardReview(shell, response.data.review_markup)
            : null;
          if (dashboardReviewShell) {
            clearDashboardTabCache();
            clearDashboardTopbarCache("match-explainer");
            clearDashboardTopbarCache("ats-explainer");
            setProfileFeedback(
              dashboardReviewShell,
              successMessage || "Resume updated.",
              false
            );
            return dashboardReviewShell;
          }
        }

        var panel = response.data.markup
          ? replaceProfilePanel(shell, response.data.markup)
          : null;
        if (panel) {
          clearDashboardTabCache();
          clearDashboardTopbarCache("match-explainer");
          clearDashboardTopbarCache("ats-explainer");
          openAccountPanel(shell, "profile");
          setProfileFeedback(panel, successMessage || "Resume updated.", false);
          return panel;
        }

        var reviewShell = response.data.review_markup
          ? replaceDashboardReview(shell, response.data.review_markup)
          : null;
        if (reviewShell) {
          clearDashboardTabCache();
          clearDashboardTopbarCache("match-explainer");
          clearDashboardTopbarCache("ats-explainer");
          setProfileFeedback(
            reviewShell,
            successMessage || "Resume updated.",
            false
          );
        } else {
          window.location.reload();
        }
      });
  }

  function submitPastedCv(form) {
    var shell = form.closest(".sffc-crm-reddit-shell");
    var textarea = form.querySelector('textarea[name="cv_text"]');
    var submitButton = form.querySelector('button[type="submit"]');
    var panel =
      form.closest('[data-reddit-account-panel="profile"]') ||
      form.closest(".sffc-crm-reddit-community-gate-card") ||
      form.closest("[data-dashboard-review-shell]");
    var originalLabel = submitButton ? submitButton.textContent : "";
    var cvText = textarea ? textarea.value.trim() : "";

    if (
      !shell ||
      !cvText ||
      !redditConfig.ajaxUrl ||
      !redditConfig.accountNonce ||
      typeof window.fetch !== "function"
    ) {
      setProfileFeedback(panel, "Paste your CV text to continue.", true);
      return;
    }

    setProfileFeedback(panel, "Saving your CV text...", false);

    if (submitButton) {
      submitButton.disabled = true;
      submitButton.textContent = "Saving...";
    }

    var body = new window.FormData();
    body.append("action", "sffc_crm_reddit_save_pasted_cv");
    body.append("nonce", redditConfig.accountNonce);
    body.append("cv_text", cvText);

    window
      .fetch(redditConfig.ajaxUrl, {
        method: "POST",
        credentials: "same-origin",
        body: body,
      })
      .then(function (response) {
        return response.json();
      })
      .then(function (response) {
        if (!response || !response.success) {
          throw new Error(
            response && response.data && response.data.message
              ? response.data.message
              : "We could not save that CV text."
          );
        }

        if (
          response.data &&
          response.data.active_cv_text &&
          window.CustomEvent
        ) {
          window.dispatchEvent(
            new window.CustomEvent("sffc:cv-updated", {
              detail: {
                cvText: String(response.data.active_cv_text || ""),
                atsState: response.data.ats_state || null,
                source: "review_cv",
              },
            })
          );
        }

        var inDashboardReview = !!shell.querySelector(
          "[data-dashboard-review-shell]"
        );
        if (inDashboardReview && response.data && response.data.review_markup) {
          var dashboardReviewShell = replaceDashboardReview(
            shell,
            response.data.review_markup
          );
          if (dashboardReviewShell) {
            clearDashboardTabCache();
            clearDashboardTopbarCache("match-explainer");
            clearDashboardTopbarCache("ats-explainer");
            setProfileFeedback(
              dashboardReviewShell,
              response.data.message || "CV text saved.",
              false
            );
            return;
          }
        }

        if (response.data && response.data.markup) {
          var profilePanel = replaceProfilePanel(shell, response.data.markup);
          if (profilePanel) {
            clearDashboardTabCache();
            clearDashboardTopbarCache("match-explainer");
            clearDashboardTopbarCache("ats-explainer");
            openAccountPanel(shell, "profile");
            setProfileFeedback(
              profilePanel,
              response.data.message || "CV text saved.",
              false
            );
            return;
          }
        }

        if (response.data && response.data.review_markup) {
          var reviewShell = replaceDashboardReview(
            shell,
            response.data.review_markup
          );
          if (reviewShell) {
            clearDashboardTabCache();
            clearDashboardTopbarCache("match-explainer");
            clearDashboardTopbarCache("ats-explainer");
            setProfileFeedback(
              reviewShell,
              response.data.message || "CV text saved.",
              false
            );
            return;
          }
        }

        window.location.reload();
      })
      .catch(function (error) {
        setProfileFeedback(
          panel,
          error && error.message
            ? error.message
            : "We could not save that CV text.",
          true
        );
      })
      .finally(function () {
        if (submitButton) {
          submitButton.disabled = false;
          submitButton.textContent = originalLabel;
        }
      });
  }

  function submitResumeUpload(form) {
    var shell = form.closest(".sffc-crm-reddit-shell");
    var fileInput = form.querySelector('input[type="file"][name="cv_file"]');
    var submitButton = form.querySelector('button[type="submit"]');
    var panel =
      form.closest('[data-reddit-account-panel="profile"]') ||
      form.closest("[data-dashboard-review-shell]");
    var originalLabel = submitButton ? submitButton.textContent : "";
    var file = fileInput && fileInput.files ? fileInput.files[0] : null;

    if (
      !shell ||
      !file ||
      !redditConfig.ajaxUrl ||
      !redditConfig.uploadNonce ||
      typeof window.fetch !== "function"
    ) {
      setProfileFeedback(panel, "Select a resume file to continue.", true);
      return;
    }

    setProfileFeedback(panel, "Uploading your resume...", false);

    if (submitButton) {
      submitButton.disabled = true;
      submitButton.textContent = "Uploading...";
    }

    var body = new window.FormData();
    body.append("action", "sffc_crm_reddit_upload_resume");
    body.append("nonce", redditConfig.uploadNonce);
    body.append("cv_file", file);

    window
      .fetch(redditConfig.ajaxUrl, {
        method: "POST",
        credentials: "same-origin",
        body: body,
      })
      .then(function (response) {
        return response.json();
      })
      .then(function (response) {
        if (
          !response ||
          !response.success ||
          !response.data ||
          !response.data.upload_id
        ) {
          throw new Error(
            response && response.data && response.data.message
              ? response.data.message
              : "We could not upload that resume."
          );
        }

        return saveActiveResume(
          shell,
          response.data.upload_id,
          "Resume uploaded and set as active."
        );
      })
      .catch(function (error) {
        setProfileFeedback(
          panel,
          error && error.message
            ? error.message
            : "We could not upload that resume.",
          true
        );
      })
      .finally(function () {
        if (submitButton) {
          submitButton.disabled = false;
          submitButton.textContent = originalLabel;
        }

        if (fileInput) {
          fileInput.value = "";
        }

        updateDashboardReviewDropzone(form);
      });
  }

  function updateDashboardReviewDropzone(form) {
    var fileInput = form
      ? form.querySelector('input[type="file"][name="cv_file"]')
      : null;
    var labelNode = form
      ? form.querySelector("[data-dashboard-review-dropzone] span")
      : null;
    var file = fileInput && fileInput.files ? fileInput.files[0] : null;

    if (!labelNode) {
      return;
    }

    labelNode.textContent = file
      ? file.name
      : "Drop DOCX here or click to upload";
  }

  function getDashboardOutreachTargetTips() {
    return {
      linkedin_inmail:
        "Lead with one role-aware hook, keep it under five short lines, and suggest a light next step or voice note if it fits the tone.",
      linkedin_message:
        "Keep it concise and contextual. Sound like someone already following the brief rather than someone spraying networking requests.",
      email:
        "Use a strong subject line, one clear value signal, and close with an easy-to-answer question instead of a broad ask.",
      text: "Shorter wins. The opener should make the context obvious in the first line and remove any pressure from the ask.",
      whatsapp:
        "Make it warm but efficient. Reference how you got the number or intro path and keep the message very easy to respond to.",
    };
  }

  function updateDashboardOutreachTargetCopy(shell) {
    if (!shell) {
      return;
    }

    var targetSelect = shell.querySelector("[data-dashboard-outreach-target]");
    var copyNode = shell.querySelector("[data-dashboard-outreach-target-copy]");
    if (!targetSelect || !copyNode) {
      return;
    }

    var tips = getDashboardOutreachTargetTips();
    var key = targetSelect.value || "linkedin_inmail";
    copyNode.textContent = tips[key] || tips.linkedin_inmail;
  }

  function syncDashboardOutreachRoleDefaults(shell) {
    if (!shell) {
      return;
    }

    var roleSelect = shell.querySelector(
      '[data-dashboard-outreach-form] select[name="jobs_post_id"]'
    );
    var nameInput = shell.querySelector(
      '[data-dashboard-outreach-form] input[name="recipient_name"]'
    );
    var roleInput = shell.querySelector(
      '[data-dashboard-outreach-form] input[name="recipient_role"]'
    );
    if (!roleSelect || !nameInput || !roleInput) {
      return;
    }

    var selectedOption = roleSelect.options[roleSelect.selectedIndex];
    if (!selectedOption) {
      return;
    }

    var recipientName =
      selectedOption.getAttribute("data-recipient-name") || "";
    var recipientRole =
      selectedOption.getAttribute("data-recipient-role") || "";

    if (!nameInput.value.trim() && recipientName) {
      nameInput.value = recipientName;
    }

    if (!roleInput.value.trim()) {
      roleInput.value = recipientRole || "Recruiter";
    }
  }

  function updateDashboardOutreachRoleVisibility(shell) {
    if (!shell) {
      return;
    }

    var contextSelect = shell.querySelector(
      '[data-dashboard-outreach-form] select[name="outreach_context"]'
    );
    var roleField = shell.querySelector("[data-dashboard-outreach-role-field]");
    if (!contextSelect || !roleField) {
      return;
    }

    var shouldShow = contextSelect.value === "enquiring_about_role";
    roleField.hidden = !shouldShow;
  }

	  function getDashboardOutreachState(shell) {
    if (!shell) {
      return null;
    }

	    if (!shell._dashboardOutreachState) {
	      shell._dashboardOutreachState = {
	        lists: [],
	        activeListId: 0,
	        members: [],
	        activeMemberIndex: -1,
	        initialized: false,
	        loaded: false,
	        listsRequest: null,
	        listRequest: null,
	        requestedListId: 0,
	        loadedListId: 0,
	      };
	    }

    return shell._dashboardOutreachState;
  }

  function buildDashboardOutreachContext(member) {
    if (!member) {
      return "";
    }

    var parts = [];
    if (member.reasons && member.reasons.length) {
      parts.push("Match signals: " + member.reasons.slice(0, 3).join("; "));
    }
    if (member.insight) {
      parts.push("Route insight: " + member.insight);
    }
    parts.push("Keep the message concise, role-aware, and easy to reply to.");
    return parts.join(" ");
  }

  function ensureDashboardOutreachRoleOption(form, member) {
    var roleSelect = form
      ? form.querySelector('select[name="jobs_post_id"]')
      : null;
    var value = String(member && member.post_id ? member.post_id : "");
    var option;
    if (!roleSelect || !value) {
      return;
    }

    option = roleSelect.querySelector('option[value="' + value + '"]');
    if (!option) {
      option = document.createElement("option");
      option.value = value;
      option.textContent = [
        member.role_title || "Role",
        member.company || member.recruiter_firm || "",
      ]
        .filter(Boolean)
        .join(" · ");
      option.setAttribute("data-recipient-name", member.recruiter_name || "");
      option.setAttribute("data-recipient-role", member.recruiter_title || "");
      roleSelect.appendChild(option);
    }

    roleSelect.value = value;
  }

  function renderDashboardOutreachSavedResult(member) {
    var subject =
      member && member.generated_subject ? member.generated_subject : "";
    var body = member && member.generated_body ? member.generated_body : "";
    if (!body) {
      return "";
    }

    return [
      '<article class="sffc-crm-dashboard-app-outreach-result-card sffc-crm-dashboard-app-outreach-result-card--saved">',
      '<div class="sffc-crm-dashboard-app-outreach-result-top">',
      '<div class="sffc-crm-dashboard-app-outreach-result-copy">',
      '<span class="sffc-crm-dashboard-app-outreach-result-kicker">Saved draft</span>',
      "<h3>" + escapeHtml(member.role_title || "Recruiter outreach") + "</h3>",
      "<p>" +
        escapeHtml(
          member.generated_with_claude
            ? "Prepared by MENA Careers and saved to this outreach queue."
            : "Saved to this outreach queue."
        ) +
        "</p>",
      "</div>",
      '<div class="sffc-crm-dashboard-app-outreach-result-pills">',
      '<span class="sffc-crm-dashboard-app-matches-pill">' +
        escapeHtml(member.target_channel || "email") +
        "</span>",
      '<span class="sffc-crm-dashboard-app-matches-pill is-positive">' +
        escapeHtml(member.outreach_status || "generated") +
        "</span>",
      "</div>",
      "</div>",
      '<section class="sffc-crm-dashboard-app-outreach-message">',
      subject
        ? '<div class="sffc-crm-dashboard-app-outreach-message-subject"><strong>Subject</strong><span>' +
          escapeHtml(subject) +
          "</span></div>"
        : "",
      '<div class="sffc-crm-dashboard-app-outreach-message-body">' +
        escapeHtml(String(body || "")).replace(/\n/g, "<br>") +
        "</div>",
      "</section>",
      "</article>",
    ].join("");
  }

  function updateDashboardOutreachSequenceUI(shell) {
    var state = getDashboardOutreachState(shell);
    var summary = shell
      ? shell.querySelector("[data-dashboard-outreach-sequence-summary]")
      : null;
    var countNode = shell
      ? shell.querySelector("[data-dashboard-outreach-sequence-count]")
      : null;
    var copyNode = shell
      ? shell.querySelector("[data-dashboard-outreach-sequence-copy]")
      : null;
    var actionWrap = shell
      ? shell.querySelector("[data-dashboard-outreach-sequence-actions]")
      : null;
    var prevButton = shell
      ? shell.querySelector("[data-dashboard-outreach-prev]")
      : null;
    var nextButton = shell
      ? shell.querySelector("[data-dashboard-outreach-next]")
      : null;

    if (!state || !summary || !countNode || !copyNode) {
      return;
    }

    if (
      !state.members.length ||
      state.activeMemberIndex < 0 ||
      !state.members[state.activeMemberIndex]
    ) {
      summary.hidden = true;
      if (actionWrap) {
        actionWrap.hidden = true;
      }
      countNode.textContent = "0 / 0";
      copyNode.textContent = "Choose a queue to begin.";
      return;
    }

    summary.hidden = false;
    if (actionWrap) {
      actionWrap.hidden = false;
    }
    countNode.textContent =
      String(state.activeMemberIndex + 1) +
      " / " +
      String(state.members.length);
    copyNode.textContent =
      (state.members[state.activeMemberIndex].role_title || "Selected role") +
      " · " +
      (state.members[state.activeMemberIndex].recruiter_name ||
        "Recruiter route");

    if (prevButton) {
      prevButton.disabled = state.activeMemberIndex <= 0;
    }
    if (nextButton) {
      nextButton.disabled = state.activeMemberIndex >= state.members.length - 1;
    }
  }

  function applyDashboardOutreachMember(shell, index) {
    var state = getDashboardOutreachState(shell);
    var form = shell
      ? shell.querySelector("[data-dashboard-outreach-form]")
      : null;
    var results = shell
      ? shell.querySelector("[data-dashboard-outreach-results]")
      : null;
    var listField = form
      ? form.querySelector("[data-dashboard-outreach-list-id]")
      : null;
    var memberField = form
      ? form.querySelector("[data-dashboard-outreach-member-id]")
      : null;
    var nameInput = form
      ? form.querySelector('input[name="recipient_name"]')
      : null;
    var roleInput = form
      ? form.querySelector('input[name="recipient_role"]')
      : null;
    var customRoleInput = form
      ? form.querySelector('input[name="custom_role_title"]')
      : null;
    var contextInput = form
      ? form.querySelector('textarea[name="user_context"]')
      : null;
    var contextSelect = form
      ? form.querySelector('select[name="outreach_context"]')
      : null;
    var targetSelect = form
      ? form.querySelector('select[name="target_channel"]')
      : null;
    var membersWrap = shell
      ? shell.querySelector("[data-dashboard-outreach-members]")
      : null;
    var member = state && state.members[index] ? state.members[index] : null;

    if (!state || !form || !member) {
      return;
    }

    state.activeMemberIndex = index;
    if (listField) {
      listField.value = String(state.activeListId || "");
    }
    if (memberField) {
      memberField.value = String(member.id || "");
    }
    if (nameInput) {
      nameInput.value = member.recruiter_name || "";
    }
    if (roleInput) {
      roleInput.value = member.recruiter_title || "Recruiter";
    }
    if (customRoleInput) {
      customRoleInput.value = "";
    }
    if (contextInput) {
      contextInput.value = buildDashboardOutreachContext(member);
    }
    if (contextSelect) {
      contextSelect.value = "enquiring_about_role";
    }
    if (targetSelect) {
      targetSelect.value = member.target_channel || "email";
    }

    ensureDashboardOutreachRoleOption(form, member);
    updateDashboardOutreachTargetCopy(shell);
    updateDashboardOutreachRoleVisibility(shell);

    if (membersWrap) {
      membersWrap
        .querySelectorAll("[data-dashboard-outreach-member-row]")
        .forEach(function (row, rowIndex) {
          row.classList.toggle("is-active", rowIndex === index);
        });
    }

    if (results) {
      results.innerHTML = member.generated_body
        ? renderDashboardOutreachSavedResult(member)
        : "";
    }

    updateDashboardOutreachSequenceUI(shell);
  }

  function renderDashboardOutreachMembers(shell) {
    var state = getDashboardOutreachState(shell);
    var wrap = shell
      ? shell.querySelector("[data-dashboard-outreach-members]")
      : null;
    var title = shell
      ? shell.querySelector("[data-dashboard-outreach-members-title]")
      : null;
    var subtitle = shell
      ? shell.querySelector("[data-dashboard-outreach-members-subtitle]")
      : null;
    if (!state || !wrap) {
      return;
    }

    if (!state.members.length) {
      wrap.innerHTML =
        '<div class="sffc-crm-dashboard-app-outreach-empty"><strong>No roles queued yet</strong><p>Once a queue is selected, each role will appear here with recruiter details, sequence status, and draft state.</p></div>';
      if (title) {
        title.textContent = "Queued roles";
      }
      if (subtitle) {
        subtitle.textContent =
          "Select a queue to review each role and recruiter route.";
      }
      updateDashboardOutreachSequenceUI(shell);
      return;
    }

    if (title) {
      title.textContent = state.activeListName || "Queued roles";
    }
    if (subtitle) {
      subtitle.textContent =
        "Generate, save, send, or skip each recruiter outreach draft in order.";
    }

    wrap.innerHTML = [
      '<div class="sffc-crm-dashboard-app-outreach-members-table">',
      state.members
        .map(function (member, index) {
          return [
            '<button type="button" class="sffc-crm-dashboard-app-outreach-member' +
              (index === state.activeMemberIndex ? " is-active" : "") +
              '" data-dashboard-outreach-member-row data-member-index="' +
              index +
              '">',
            '<span class="sffc-crm-dashboard-app-outreach-member-rank">' +
              escapeHtml(String(index + 1)) +
              "</span>",
            '<span class="sffc-crm-dashboard-app-outreach-member-copy"><strong>' +
              escapeHtml(member.role_title || "Role") +
              "</strong><em>" +
              escapeHtml(
                member.recruiter_name || member.company || "Recruiter"
              ) +
              "</em></span>",
            '<span class="sffc-crm-dashboard-app-outreach-member-meta"><small>' +
              escapeHtml(member.company || member.location || "") +
              '</small><b class="is-' +
              escapeHtml(member.outreach_status || "queued") +
              '">' +
              escapeHtml(member.outreach_status || "queued") +
              "</b></span>",
            "</button>",
          ].join("");
        })
        .join(""),
      "</div>",
    ].join("");

    if (state.activeMemberIndex < 0 && state.members.length) {
      applyDashboardOutreachMember(shell, 0);
      return;
    }

    updateDashboardOutreachSequenceUI(shell);
  }

  function renderDashboardOutreachLists(shell) {
    var state = getDashboardOutreachState(shell);
    var wrap = shell
      ? shell.querySelector("[data-dashboard-outreach-lists]")
      : null;
    if (!state || !wrap) {
      return;
    }

    if (!state.lists.length) {
      wrap.innerHTML =
        '<div class="sffc-crm-dashboard-app-outreach-empty"><strong>No outreach queue yet</strong><p>Select roles in CV Match Studio and use Add to Recruiter Outreach to create your first queue.</p></div>';
      return;
    }

    wrap.innerHTML = [
      '<div class="sffc-crm-dashboard-app-outreach-lists-table">',
      state.lists
        .map(function (list) {
          var total = Number(list.job_count || list.total_items || 0);
          var sent = Number(list.sent_count || list.sent_items || 0);
          var generated = Number(
            list.generated_count || list.generated_items || 0
          );
          return [
            '<button type="button" class="sffc-crm-dashboard-app-outreach-list' +
              (Number(list.id) === Number(state.activeListId)
                ? " is-active"
                : "") +
              '" data-dashboard-outreach-list-row data-list-id="' +
              escapeHtml(String(list.id || 0)) +
              '">',
            '<span class="sffc-crm-dashboard-app-outreach-list-copy"><strong>' +
              escapeHtml(list.list_name || "Outreach queue") +
              "</strong><em>" +
              escapeHtml(
                list.description || "Queued recruiter outreach roles"
              ) +
              "</em></span>",
            '<span class="sffc-crm-dashboard-app-outreach-list-stats"><small>' +
              escapeHtml(String(total)) +
              " roles</small><b>" +
              escapeHtml(String(generated)) +
              " ready · " +
              escapeHtml(String(sent)) +
              " sent</b></span>",
            "</button>",
          ].join("");
        })
        .join(""),
      "</div>",
    ].join("");
  }

	  function loadDashboardOutreachList(shell, listId, preferredMemberId) {
	    var state = getDashboardOutreachState(shell);
	    if (!shell || !state || !redditConfig.ajaxUrl || !redditConfig.crmNonce) {
	      return Promise.resolve();
	    }

	    listId = Number(listId || 0);
	    preferredMemberId = Number(preferredMemberId || 0);

	    if (state.listRequest && Number(state.requestedListId || 0) === listId) {
	      return state.listRequest;
	    }

	    if (
	      state.loadedListId === listId &&
	      state.members.length &&
	      !preferredMemberId
	    ) {
	      return Promise.resolve();
	    }

	    var body = new window.FormData();
	    body.append("action", "sffc_crm_get_job_outreach_list_details");
	    body.append("nonce", redditConfig.crmNonce);
	    body.append("list_id", String(listId || 0));

	    state.requestedListId = listId;
	    state.listRequest = window
	      .fetch(redditConfig.ajaxUrl, {
	        method: "POST",
	        credentials: "same-origin",
        body: body,
      })
      .then(function (response) {
        return response.json();
      })
      .then(function (response) {
        if (!response || !response.success || !response.data) {
          throw new Error(
            response && response.data
              ? response.data.message || "Unable to load the outreach queue."
              : "Unable to load the outreach queue."
          );
        }

	        state.activeListId = Number(listId || 0);
	        state.activeListName =
	          response.data.list && response.data.list.list_name
	            ? response.data.list.list_name
	            : "";
	        state.members = Array.isArray(response.data.jobs)
	          ? response.data.jobs
	          : [];
	        state.activeMemberIndex = -1;
	        state.loadedListId = Number(listId || 0);
	        state.loaded = true;
	        renderDashboardOutreachLists(shell);
	        renderDashboardOutreachMembers(shell);

        if (state.members.length) {
          var nextIndex = 0;
          if (preferredMemberId) {
            state.members.some(function (member, index) {
              if (Number(member.id) === Number(preferredMemberId)) {
                nextIndex = index;
                return true;
              }
              return false;
            });
          } else {
            state.members.some(function (member, index) {
              if (
                (member.outreach_status || "queued") !== "sent" &&
                (member.outreach_status || "queued") !== "skipped"
              ) {
                nextIndex = index;
                return true;
              }
              return false;
            });
          }
          applyDashboardOutreachMember(shell, nextIndex);
        }
      })
	      .catch(function (error) {
	        var wrap = shell.querySelector("[data-dashboard-outreach-members]");
        if (wrap) {
          wrap.innerHTML =
            '<div class="sffc-crm-dashboard-app-outreach-empty"><strong>Unable to load queue</strong><p>' +
            escapeHtml(
              error && error.message
                ? error.message
                : "Unable to load the outreach queue."
            ) +
            "</p></div>";
	        }
	        updateDashboardOutreachSequenceUI(shell);
	        throw error;
	      })
	      .finally(function () {
	        state.listRequest = null;
	        state.requestedListId = 0;
	      });

	    return state.listRequest;
	  }

	  function loadDashboardOutreachLists(
	    shell,
	    preferredListId,
	    preferredMemberId
	  ) {
	    var state = getDashboardOutreachState(shell);
	    if (!shell || !state || !redditConfig.ajaxUrl || !redditConfig.crmNonce) {
	      return Promise.resolve();
	    }

	    if (state.listsRequest) {
	      return state.listsRequest;
	    }

	    var body = new window.FormData();
	    body.append("action", "sffc_crm_get_job_outreach_lists");
	    body.append("nonce", redditConfig.crmNonce);

	    state.listsRequest = window
	      .fetch(redditConfig.ajaxUrl, {
	        method: "POST",
	        credentials: "same-origin",
        body: body,
      })
      .then(function (response) {
        return response.json();
      })
      .then(function (response) {
        if (!response || !response.success) {
          throw new Error(
            response && response.data
              ? response.data.message || "Unable to load outreach queues."
              : "Unable to load outreach queues."
          );
        }

	        state.lists = Array.isArray(response.data) ? response.data : [];
	        state.loaded = true;
	        renderDashboardOutreachLists(shell);

        if (state.lists.length) {
          var targetListId =
            preferredListId ||
            state.activeListId ||
            Number(state.lists[0].id || 0);
          return loadDashboardOutreachList(
            shell,
            targetListId,
            preferredMemberId
          );
        }

        state.members = [];
        state.activeListId = 0;
        state.activeMemberIndex = -1;
        renderDashboardOutreachMembers(shell);
        updateDashboardOutreachSequenceUI(shell);
        return null;
      })
	      .catch(function () {
	        renderDashboardOutreachLists(shell);
	        renderDashboardOutreachMembers(shell);
	        return null;
	      })
	      .finally(function () {
	        state.listsRequest = null;
	      });

	    return state.listsRequest;
	  }

	  function ensureDashboardOutreachLoaded(
	    shell,
	    preferredListId,
	    preferredMemberId
	  ) {
	    var state = getDashboardOutreachState(shell);

	    if (!shell || !state) {
	      return Promise.resolve();
	    }

	    if (state.listsRequest) {
	      return state.listsRequest;
	    }

	    if (state.loaded) {
	      if (preferredListId) {
	        return loadDashboardOutreachList(shell, preferredListId, preferredMemberId);
	      }
	      return Promise.resolve();
	    }

	    return loadDashboardOutreachLists(shell, preferredListId, preferredMemberId);
	  }

  function persistDashboardOutreachDraft(shell, response) {
    var form = shell
      ? shell.querySelector("[data-dashboard-outreach-form]")
      : null;
    var listId = form
      ? form.querySelector("[data-dashboard-outreach-list-id]")
      : null;
    var memberId = form
      ? form.querySelector("[data-dashboard-outreach-member-id]")
      : null;
    var targetChannelField = form
      ? form.querySelector('select[name="target_channel"]')
      : null;
    var result = response && response.data ? response.data.result || {} : {};
    var message = result.message || {};
    var bodyText = String(message.body || "").trim();

    if (
      !form ||
      !listId ||
      !memberId ||
      !listId.value ||
      !memberId.value ||
      !bodyText ||
      !redditConfig.ajaxUrl ||
      !redditConfig.crmNonce
    ) {
      return Promise.resolve();
    }

    var body = new window.FormData();
    body.append("action", "sffc_crm_save_job_outreach_draft");
    body.append("nonce", redditConfig.crmNonce);
    body.append("list_id", listId.value);
    body.append("member_id", memberId.value);
    body.append("subject", String(message.subject || ""));
    body.append("body", bodyText);
    body.append(
      "target_channel",
      String(
        message.channel ||
          (targetChannelField ? targetChannelField.value : "") ||
          "email"
      )
    );
    body.append(
      "generated_with_claude",
      response && response.data && response.data.generated_with_claude
        ? "1"
        : ""
    );
    body.append("generated_payload", JSON.stringify(result));

    return window
      .fetch(redditConfig.ajaxUrl, {
        method: "POST",
        credentials: "same-origin",
        body: body,
      })
      .then(function (saveResponse) {
        return saveResponse.json();
      })
      .then(function () {
        return loadDashboardOutreachList(
          shell,
          Number(listId.value || 0),
          Number(memberId.value || 0)
        );
      })
      .catch(function () {
        return null;
      });
  }

  function updateDashboardOutreachMemberStatus(shell, status) {
    var form = shell
      ? shell.querySelector("[data-dashboard-outreach-form]")
      : null;
    var listId = form
      ? form.querySelector("[data-dashboard-outreach-list-id]")
      : null;
    var memberId = form
      ? form.querySelector("[data-dashboard-outreach-member-id]")
      : null;
    var state = getDashboardOutreachState(shell);
    if (
      !shell ||
      !form ||
      !listId ||
      !memberId ||
      !listId.value ||
      !memberId.value ||
      !status ||
      !redditConfig.ajaxUrl ||
      !redditConfig.crmNonce ||
      !state
    ) {
      return;
    }

    var body = new window.FormData();
    body.append("action", "sffc_crm_update_job_outreach_member_status");
    body.append("nonce", redditConfig.crmNonce);
    body.append("list_id", listId.value);
    body.append("member_id", memberId.value);
    body.append("status", status);

    window
      .fetch(redditConfig.ajaxUrl, {
        method: "POST",
        credentials: "same-origin",
        body: body,
      })
      .then(function (response) {
        return response.json();
      })
      .then(function (response) {
        if (!response || !response.success) {
          throw new Error(
            response && response.data
              ? response.data.message || "Unable to update the queue item."
              : "Unable to update the queue item."
          );
        }
        return loadDashboardOutreachLists(
          shell,
          Number(listId.value || 0),
          Number(memberId.value || 0)
        );
      })
      .then(function () {
        if (status === "sent" || status === "skipped") {
          var nextIndex = Math.min(
            state.activeMemberIndex + 1,
            Math.max(0, state.members.length - 1)
          );
          if (state.members.length) {
            applyDashboardOutreachMember(shell, nextIndex);
          }
        }
      })
      .catch(function () {});
  }

	  function initDashboardOutreach(shell) {
	    var queue;
	    var state;

	    if (!shell) {
	      return;
	    }

	    state = getDashboardOutreachState(shell);
	    if (!state) {
	      return;
	    }

	    updateDashboardOutreachTargetCopy(shell);
	    updateDashboardOutreachRoleVisibility(shell);
	    syncDashboardOutreachRoleDefaults(shell);

	    if (state.initialized) {
	      return;
	    }

	    state.initialized = true;
	    queue = shell.querySelector("[data-dashboard-outreach-queue]");
	    if (queue && queue.tagName === "DETAILS") {
	      queue.addEventListener("toggle", function () {
	        if (queue.open) {
	          ensureDashboardOutreachLoaded(shell);
	        }
	      });
	    }

	    if (queue && queue.tagName === "DETAILS" && !queue.open) {
	      return;
	    }

	    ensureDashboardOutreachLoaded(shell);
	  }

  function initDashboardProfile() {
    document.addEventListener("click", function (event) {
      var footerSaveButton = event.target.closest(
        ".sffc-crm-dashboard-app-profile-save--footer"
      );
      if (footerSaveButton) {
        var footerForm = footerSaveButton.closest(
          "[data-dashboard-profile-form]"
        );
        if (footerForm) {
          event.preventDefault();
          submitDashboardProfileForm(footerForm);
          return;
        }
      }

      var tabButton = event.target.closest("[data-dashboard-profile-tab]");
      if (tabButton) {
        event.preventDefault();
        activateDashboardProfileTab(
          tabButton.closest("[data-dashboard-profile-shell]"),
          tabButton.getAttribute("data-dashboard-profile-tab") || "overview"
        );
        return;
      }

      var addButton = event.target.closest("[data-dashboard-profile-add]");
      if (addButton) {
        event.preventDefault();
        openDashboardProfileTagInput(addButton);
        return;
      }

      var filterToggle = event.target.closest(
        "[data-dashboard-profile-filter-toggle]"
      );
      if (filterToggle) {
        event.preventDefault();
        toggleDashboardProfileFilter(filterToggle);
        return;
      }

      var filterOption = event.target.closest(
        "[data-dashboard-profile-filter-option]"
      );
      if (filterOption) {
        event.preventDefault();
        selectDashboardProfileFilterOption(filterOption);
        return;
      }

      var coverEditButton = event.target.closest(
        ".sffc-crm-dashboard-app-profile-cover-edit"
      );
      if (coverEditButton) {
        var cover = coverEditButton.closest("[data-dashboard-profile-cover]");
        var coverInput = cover
          ? cover.querySelector("[data-dashboard-profile-cover-input]")
          : null;
        if (coverInput) {
          event.preventDefault();
          coverInput.click();
          return;
        }
      }

      var removeButton = event.target.closest(
        "[data-dashboard-profile-tag-remove]"
      );
      if (removeButton) {
        event.preventDefault();
        var tag = removeButton.closest("[data-profile-tag]");
        if (tag) {
          tag.remove();
        }
        return;
      }

      if (!event.target.closest("[data-dashboard-profile-filter]")) {
        closeDashboardProfileFilters(document);
      }
    });

    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape") {
        closeDashboardProfileFilters(document);
      }

      var input = event.target.closest(
        ".sffc-crm-dashboard-app-profile-tag-input"
      );
      if (!input) {
        return;
      }

      if (event.key === "Enter") {
        event.preventDefault();
        closeDashboardProfileTagInput(input, true);
      } else if (event.key === "Escape") {
        event.preventDefault();
        closeDashboardProfileTagInput(input, false);
      }
    });

    document.addEventListener(
      "blur",
      function (event) {
        var input = event.target.closest(
          ".sffc-crm-dashboard-app-profile-tag-input"
        );
        if (!input) {
          return;
        }
        closeDashboardProfileTagInput(input, true);
      },
      true
    );

    document.addEventListener("submit", function (event) {
      var form = event.target.closest("[data-dashboard-profile-form]");
      if (!form) {
        return;
      }

      event.preventDefault();
      submitDashboardProfileForm(form);
    });

    document.addEventListener("change", function (event) {
      var frequencyInput = event.target.closest('input[name="alert_frequency"]');
      if (frequencyInput) {
        var frequencyShell = frequencyInput.closest(
          ".sffc-crm-dashboard-app-profile-alerts"
        );
        if (frequencyShell) {
          frequencyShell
            .querySelectorAll(".sffc-crm-dashboard-app-profile-frequency")
            .forEach(function (label) {
              var input = label.querySelector('input[name="alert_frequency"]');
              label.classList.toggle("is-active", !!(input && input.checked));
            });
        }
      }

      var industryInput = event.target.closest(
        'input[name="target_sectors[]"]'
      );
      if (industryInput) {
        var industryLabel = industryInput.closest(
          ".sffc-crm-dashboard-app-profile-industry"
        );
        if (industryLabel) {
          industryLabel.classList.toggle("is-active", industryInput.checked);
        }
      }

      var avatarInput = event.target.closest(
        "[data-dashboard-profile-avatar-input], [data-dashboard-profile-cover-input]"
      );
      if (!avatarInput) {
        return;
      }

      submitDashboardProfileImage(avatarInput);
    });
  }

  function resetDashboardOutreachForm(button) {
    var shell = button
      ? button.closest("[data-dashboard-outreach-shell]")
      : null;
    var form = shell
      ? shell.querySelector("[data-dashboard-outreach-form]")
      : null;
    var results = shell
      ? shell.querySelector("[data-dashboard-outreach-results]")
      : null;
    var state = getDashboardOutreachState(shell);
    if (!form) {
      return;
    }

    form.reset();
    updateDashboardOutreachTargetCopy(shell);
    updateDashboardOutreachRoleVisibility(shell);
    syncDashboardOutreachRoleDefaults(shell);

    if (results) {
      results.innerHTML = "";
    }

    if (state && state.members.length) {
      applyDashboardOutreachMember(shell, Math.max(0, state.activeMemberIndex));
    }
  }

  function submitDashboardOutreachForm(form) {
    var shell = form.closest("[data-dashboard-outreach-shell]");
    var submitButton = form.querySelector("[data-dashboard-outreach-submit]");
    var results = shell
      ? shell.querySelector("[data-dashboard-outreach-results]")
      : null;
    var originalLabel = submitButton ? submitButton.textContent : "";

    if (
      !shell ||
      !results ||
      !redditConfig.ajaxUrl ||
      !redditConfig.outreachNonce ||
      typeof window.fetch !== "function"
    ) {
      return;
    }

    if (typeof form.reportValidity === "function" && !form.reportValidity()) {
      return;
    }

    if (submitButton) {
      submitButton.disabled = true;
      submitButton.textContent = "Generating...";
      submitButton.classList.add("is-loading");
    }

    results.classList.add("is-loading");

    var body = new window.FormData(form);
    body.append("action", "sffc_crm_reddit_generate_outreach");
    body.append("nonce", redditConfig.outreachNonce);

    window
      .fetch(redditConfig.ajaxUrl, {
        method: "POST",
        credentials: "same-origin",
        body: body,
      })
      .then(function (response) {
        return response.json();
      })
      .then(function (response) {
        if (
          !response ||
          !response.success ||
          !response.data ||
          !response.data.markup
        ) {
          throw new Error(
            response && response.data && response.data.message
              ? response.data.message
              : "Unable to generate outreach right now."
          );
        }

        results.innerHTML = response.data.markup;
        return persistDashboardOutreachDraft(shell, response);
      })
      .catch(function (error) {
        results.innerHTML =
          '<article class="sffc-crm-dashboard-app-outreach-result-card"><span class="sffc-crm-dashboard-app-outreach-result-kicker">Generation issue</span><h3>We could not build the outreach just now</h3><p>' +
          (error && error.message
            ? error.message
            : "Unable to generate outreach right now.") +
          "</p></article>";
      })
      .finally(function () {
        results.classList.remove("is-loading");
        if (submitButton) {
          submitButton.disabled = false;
          submitButton.textContent = originalLabel;
          submitButton.classList.remove("is-loading");
        }
      });
  }

  function loadMoreFeed(button) {
    if (
      !button ||
      !redditConfig.ajaxUrl ||
      !redditConfig.loadMoreNonce ||
      typeof window.fetch !== "function"
    ) {
      return;
    }

    var shell = button.closest(".sffc-crm-reddit-shell");
    var feed = shell ? shell.querySelector("[data-reddit-feed]") : null;
    if (!feed) {
      return;
    }

    var loadedIds = (feed.getAttribute("data-loaded-crm-ids") || "")
      .split(",")
      .map(function (value) {
        return parseInt(value, 10);
      })
      .filter(function (value) {
        return !window.isNaN(value) && value > 0;
      });
    var originalLabel = button.textContent;

    button.disabled = true;
    button.textContent = "Loading...";

    var body = new window.FormData();
    body.append("action", "sffc_crm_reddit_load_more");
    body.append("nonce", redditConfig.loadMoreNonce);
    body.append("jobs_post_id", feed.getAttribute("data-jobs-post-id") || "");
    body.append("per_page", feed.getAttribute("data-per-page") || "8");
    body.append("reddit_group", feed.getAttribute("data-current-group") || "");
    body.append("search_term", feed.getAttribute("data-search-term") || "");
    loadedIds.forEach(function (id) {
      body.append("loaded_ids[]", String(id));
    });

    window
      .fetch(redditConfig.ajaxUrl, {
        method: "POST",
        credentials: "same-origin",
        body: body,
      })
      .then(function (response) {
        return response.json();
      })
      .then(function (response) {
        if (!response || !response.success || !response.data) {
          throw new Error(
            response && response.data && response.data.message
              ? response.data.message
              : "Unable to load more roles."
          );
        }

        if (response.data.markup) {
          feed.insertAdjacentHTML("beforeend", response.data.markup);
        }

        if (response.data.loaded_ids) {
          feed.setAttribute(
            "data-loaded-crm-ids",
            response.data.loaded_ids.join(",")
          );
        }

        if (typeof response.data.loaded_count !== "undefined") {
          feed.setAttribute(
            "data-loaded-crm-count",
            String(response.data.loaded_count)
          );
        }

        if (!response.data.has_more) {
          var moreWrap = button.closest(".sffc-crm-reddit-feed-more");
          if (moreWrap) {
            moreWrap.hidden = true;
          }
        }
      })
      .catch(function () {
        button.disabled = false;
        button.textContent = originalLabel;
      })
      .finally(function () {
        if (!button.closest(".sffc-crm-reddit-feed-more[hidden]")) {
          button.disabled = false;
          button.textContent = originalLabel;
        }
      });
  }

  function activateStoryPanel(modal, storyKey) {
    var panels = modal.querySelectorAll("[data-story-panel]");

    panels.forEach(function (panel) {
      var isActive = panel.getAttribute("data-story-panel") === storyKey;
      panel.hidden = !isActive;
    });
  }

  function openStoryModal(shell, storyKey) {
    var modal = shell.querySelector("[data-reddit-modal]");
    if (!modal) {
      return;
    }

    activateStoryPanel(modal, storyKey);
    modal.hidden = false;
    document.documentElement.classList.add("sffc-crm-reddit-modal-open");
    document.body.classList.add("sffc-crm-reddit-modal-open");

    var dialog = modal.querySelector(".sffc-crm-reddit-modal-dialog");
    if (dialog) {
      dialog.focus();
    }
  }

  function closeStoryModal(shell) {
    var modal = shell.querySelector("[data-reddit-modal]");
    if (!modal) {
      return;
    }

    modal.hidden = true;
    document.documentElement.classList.remove("sffc-crm-reddit-modal-open");
    document.body.classList.remove("sffc-crm-reddit-modal-open");
  }

  function activateJobPanel(modal, panelKey) {
    var panels = modal.querySelectorAll("[data-job-panel]");

    panels.forEach(function (panel) {
      panel.hidden = panel.getAttribute("data-job-panel") !== panelKey;
    });
  }

  function openJobModal(shell, panelKey) {
    var modal = shell.querySelector("[data-reddit-job-modal]");
    if (!modal) {
      return;
    }

    activateJobPanel(modal, panelKey);
    modal.hidden = false;
    document.documentElement.classList.add("sffc-crm-reddit-modal-open");
    document.body.classList.add("sffc-crm-reddit-modal-open");

    var dialog = modal.querySelector(".sffc-crm-reddit-job-dialog");
    if (dialog) {
      dialog.focus();
    }
  }

  function getJobPanelShell(shell, panelKey) {
    var modal = shell ? shell.querySelector("[data-reddit-job-modal]") : null;
    if (!modal || !panelKey) {
      return null;
    }

    var panels = Array.prototype.slice.call(
      modal.querySelectorAll("[data-job-panel]")
    );
    var matchedPanel = panels.find(function (panel) {
      return panel.getAttribute("data-job-panel") === panelKey;
    });

    return matchedPanel
      ? matchedPanel.querySelector(".sffc-crm-reddit-shell")
      : null;
  }

  function closeJobModal(shell) {
    var modal = shell.querySelector("[data-reddit-job-modal]");
    if (!modal) {
      return;
    }

    modal.hidden = true;
    document.documentElement.classList.remove("sffc-crm-reddit-modal-open");
    document.body.classList.remove("sffc-crm-reddit-modal-open");
  }

  function openSingleApplyModal(shell) {
    var modal = shell.querySelector("[data-single-apply-modal]");
    if (!modal) {
      return;
    }

    modal.hidden = false;
    document.documentElement.classList.add("sffc-crm-reddit-modal-open");
    document.body.classList.add("sffc-crm-reddit-modal-open");

    var dialog = modal.querySelector(".sffc-crm-reddit-single-apply-dialog");
    if (dialog) {
      dialog.focus();
    }
  }

  function closeSingleApplyModal(shell) {
    var modal = shell.querySelector("[data-single-apply-modal]");
    if (!modal) {
      return;
    }

    modal.hidden = true;
    document.documentElement.classList.remove("sffc-crm-reddit-modal-open");
    document.body.classList.remove("sffc-crm-reddit-modal-open");
  }

  function closeTopbarApplyChoice(choice) {
    if (!choice) {
      return;
    }

    var toggle = choice.querySelector("[data-topbar-apply-choice-toggle]");
    var menu = choice.querySelector("[data-topbar-apply-choice-menu]");
    choice.classList.remove("is-open");
    if (toggle) {
      toggle.setAttribute("aria-expanded", "false");
    }
    if (menu) {
      menu.hidden = true;
    }
  }

  function closeAllTopbarApplyChoices(exceptChoice) {
    document
      .querySelectorAll("[data-topbar-apply-choice]")
      .forEach(function (choice) {
        if (exceptChoice && choice === exceptChoice) {
          return;
        }
        closeTopbarApplyChoice(choice);
      });
  }

  function toggleTopbarApplyChoice(choice) {
    if (!choice) {
      return;
    }

    var toggle = choice.querySelector("[data-topbar-apply-choice-toggle]");
    var menu = choice.querySelector("[data-topbar-apply-choice-menu]");
    if (!toggle || !menu) {
      return;
    }

    var willOpen = !choice.classList.contains("is-open");
    closeAllTopbarApplyChoices(willOpen ? choice : null);
    choice.classList.toggle("is-open", willOpen);
    toggle.setAttribute("aria-expanded", willOpen ? "true" : "false");
    menu.hidden = !willOpen;
  }

  function getNetworkingLoadingMarkup() {
    return [
      '<div class="sffc-crm-reddit-networking-loading">',
      '<div class="sffc-crm-reddit-networking-loading-card">',
      '<p class="sffc-crm-reddit-networking-loading-kicker">Networking strategy in progress</p>',
      "<h3>Building a sharper recruiter strategy for this role</h3>",
      "<p>MENA Careers is grounding the networking brief in the live job description, recruiter context, company, industry, location, and application timing.</p>",
      '<div class="sffc-crm-reddit-networking-loading-progress">',
      '<div class="sffc-crm-reddit-networking-loading-progress-bar"><span data-networking-loading-bar></span></div>',
      '<div class="sffc-crm-reddit-networking-loading-progress-meta">',
      "<strong data-networking-loading-percent>12%</strong>",
      "<span data-networking-loading-status>Preparing networking strategy</span>",
      "</div>",
      "</div>",
      '<div class="sffc-crm-reddit-networking-loading-steps">',
      '<span class="is-active" data-networking-loading-step>Preparing networking strategy</span>',
      "<span data-networking-loading-step>Finding suitable contacts</span>",
      "<span data-networking-loading-step>Creating templates</span>",
      "<span data-networking-loading-step>Refining strategy</span>",
      "</div>",
      "</div>",
      "</div>",
    ].join("");
  }

  function getNetworkingErrorMarkup(message) {
    return (
      '<div class="sffc-crm-reddit-networking-error"><p>' +
      (message ||
        "We couldn&rsquo;t build the networking view for this role right now.") +
      "</p></div>"
    );
  }

  function getMaterialLoadingConfig(materialType) {
    var type = String(materialType || "").trim();
    var configs = {
      cv_template: {
        label: "CV template",
        steps: [
          "Reading the role brief",
          "Extracting the strongest CV proof points",
          "Drafting the tailored CV",
          "Finalising the download-ready CV",
        ],
      },
      cover_letter: {
        label: "cover letter template",
        steps: [
          "Reading the role brief",
          "Mapping company and recruiter signals",
          "Drafting the cover letter",
          "Finalising the download-ready letter",
        ],
      },
      interview_questions: {
        label: "interview questions template",
        steps: [
          "Reading the role brief",
          "Identifying likely screening themes",
          "Drafting the interview questions",
          "Finalising the prep sheet",
        ],
      },
      hiring_guide: {
        label: "hiring guide template",
        steps: [
          "Reading the role brief",
          "Mapping company and role priorities",
          "Drafting the hiring guide",
          "Finalising the recruiter-ready guide",
        ],
      },
    };

    return (
      configs[type] || {
        label: "tailored material",
        steps: [
          "Reading the role brief",
          "Mapping company signals",
          "Drafting the material",
          "Preparing copy and download",
        ],
      }
    );
  }

  function getMaterialLoadingMarkup(materialType, companyName) {
    var config = getMaterialLoadingConfig(materialType);
    var materialLabel = config.label;
    var headline = "Building tailored material for this company";
    if (materialLabel && companyName) {
      headline = "Building " + materialLabel + " for " + companyName;
    } else if (materialLabel) {
      headline = "Building " + materialLabel;
    }

    return [
      '<div class="sffc-crm-reddit-material-loading">',
      '<div class="sffc-crm-reddit-material-loading-card">',
      '<p class="sffc-crm-reddit-material-loading-kicker">Tailored material in progress</p>',
      "<h3>" + headline + "</h3>",
      "<p>MENA Careers is reading the live role, company context, recruiter signal, and requirements so the material is specific enough to use straight away.</p>",
      '<div class="sffc-crm-reddit-material-loading-progress">',
      '<div class="sffc-crm-reddit-material-loading-progress-bar"><span data-material-loading-bar></span></div>',
      '<div class="sffc-crm-reddit-material-loading-progress-meta">',
      "<strong data-material-loading-percent>12%</strong>",
      "<span data-material-loading-status>" + config.steps[0] + "</span>",
      "</div>",
      "</div>",
      '<div class="sffc-crm-reddit-material-loading-steps">',
      '<span class="is-active" data-material-loading-step>' +
        config.steps[0] +
        "</span>",
      "<span data-material-loading-step>" + config.steps[1] + "</span>",
      "<span data-material-loading-step>" + config.steps[2] + "</span>",
      "<span data-material-loading-step>" + config.steps[3] + "</span>",
      "</div>",
      "</div>",
      "</div>",
    ].join("");
  }

  function getMaterialErrorMarkup(message) {
    return (
      '<div class="sffc-crm-reddit-material-error"><p>' +
      (message || "We couldn&rsquo;t prepare this material right now.") +
      "</p></div>"
    );
  }

  function stopMaterialLoadingAnimation(modal) {
    if (!modal || !modal.__materialLoaderTimer) {
      return;
    }

    window.clearInterval(modal.__materialLoaderTimer);
    modal.__materialLoaderTimer = null;
  }

  function startMaterialLoadingAnimation(modal) {
    if (!modal) {
      return;
    }

    stopMaterialLoadingAnimation(modal);

    var steps = Array.prototype.slice.call(
      modal.querySelectorAll("[data-material-loading-step]")
    );
    var percent = modal.querySelector("[data-material-loading-percent]");
    var status = modal.querySelector("[data-material-loading-status]");
    var bar = modal.querySelector("[data-material-loading-bar]");

    if (!steps.length || !percent || !status || !bar) {
      return;
    }

    var labels = steps.map(function (step) {
      return step.textContent.trim();
    });
    var widths = [12, 34, 68, 100];
    var activeIndex = 0;

    function renderStep(index) {
      activeIndex = Math.min(index, steps.length - 1);
      steps.forEach(function (step, idx) {
        step.classList.toggle("is-active", idx === activeIndex);
        step.classList.toggle("is-complete", idx < activeIndex);
      });
      percent.textContent = widths[activeIndex] + "%";
      status.textContent = labels[activeIndex] || "";
      bar.style.width = widths[activeIndex] + "%";
    }

    renderStep(0);
    modal.__materialLoaderTimer = window.setInterval(function () {
      if (activeIndex < steps.length - 1) {
        renderStep(activeIndex + 1);
        return;
      }

      percent.textContent = "100%";
      status.textContent =
        labels[steps.length - 1] || "Finalising the download-ready draft";
      bar.style.width = "100%";
    }, 1800);
  }

  function getApplyKitResources(trigger) {
    var scope = trigger
      ? trigger.closest(
          ".sffc-crm-reddit-single-analysis-card--materials-panel, .sffc-match-method-card--materials, .sffc-crm-reddit-pack, .sffc-crm-reddit-shell"
        )
      : null;
    var previewFiles = [];
    if (scope) {
      previewFiles = scope.querySelectorAll(
        "[data-kit-preview-file], .sffc-crm-reddit-single-pack-grid [data-material-type], .sffc-crm-reddit-apply-material-strip [data-material-type]"
      );
    }
    var jobsPostId = trigger
      ? trigger.getAttribute("data-jobs-post-id") || ""
      : "";
    var crmPostId = trigger
      ? trigger.getAttribute("data-crm-post-id") || ""
      : "";
    var company = trigger ? trigger.getAttribute("data-company") || "" : "";
    var hasAccess = trigger
      ? trigger.getAttribute("data-material-access") === "true"
      : false;
    var resources = [];

    previewFiles.forEach(function (fileNode) {
      resources.push({
        label:
          fileNode.getAttribute("data-material-label") ||
          "Application Material",
        materialType: fileNode.getAttribute("data-material-type") || "",
        fileType: fileNode.getAttribute("data-material-file-type") || "DOCX",
        kind: fileNode.className.indexOf("--pdf") !== -1 ? "pdf" : "word",
        jobsPostId: jobsPostId,
        crmPostId: crmPostId,
        company: company,
        hasAccess: hasAccess,
      });
    });

    return resources;
  }

  function getMaterialCacheKey(materialType, jobsPostId, cvText) {
    return materialType + "::" + jobsPostId + "::" + hashString(cvText || "");
  }

  function primeMaterialCache(modal, resource, payload, cvText) {
    if (!modal || !resource || !payload || !payload.markup) {
      return;
    }

    modal.__materialCache = modal.__materialCache || {};
    modal.__materialCache[
      getMaterialCacheKey(
        resource.materialType || "",
        resource.jobsPostId || "",
        cvText || ""
      )
    ] = {
      title: payload.title || resource.label || "Application Material",
      markup: payload.markup,
    };
  }

  function getMaterialKitLoadingMarkup(resources, companyName) {
    var calmRows = renderMaterialKitCards(resources, false, true);
    return [
      '<div class="sffc-crm-reddit-material-kit" data-material-kit-flow>',
      '<div class="sffc-crm-reddit-material-kit-head">',
      '<p class="sffc-crm-reddit-material-kit-kicker">Apply+ Tailored Materials</p>',
      "<h3>Preparing your kit for " +
        escapeHtml(companyName || "this company") +
        "</h3>",
      "<p>MENA Careers is organising role-specific application files from the live job brief so you can review everything in one place.</p>",
      "</div>",
      '<div class="sffc-crm-reddit-material-kit-toolbar"><span class="sffc-crm-reddit-material-kit-toolbar-pill is-active">Files</span><span class="sffc-crm-reddit-material-kit-toolbar-note">Building your role-specific set</span><button type="button" class="sffc-crm-reddit-single-pack-cta sffc-crm-reddit-single-pack-cta--toolbar" data-kit-generate-pack>Get Application Pack</button></div>',
      '<div class="sffc-crm-reddit-material-kit-files">' + calmRows + "</div>",
      "</div>",
    ].join("");

    var rows = resources
      .map(function (resource, index) {
        return [
          '<div class="sffc-crm-reddit-kit-file is-loading sffc-crm-reddit-kit-file--' +
            resource.kind +
            '" data-kit-file-row data-kit-file-index="' +
            index +
            '">',
          '<span class="sffc-crm-reddit-kit-file-icon" aria-hidden="true"></span>',
          '<div class="sffc-crm-reddit-kit-file-body">',
          '<div class="sffc-crm-reddit-kit-file-head">',
          "<strong>" + escapeHtml(resource.label) + "</strong>",
          "<span data-kit-file-progress>0%</span>",
          "</div>",
          '<div class="sffc-crm-reddit-kit-file-bar"><span data-kit-file-bar></span></div>',
          '<div class="sffc-crm-reddit-kit-file-meta"><span data-kit-file-size>Preparing file...</span></div>',
          "</div>",
          "</div>",
        ].join("");
      })
      .join("");

    return [
      '<div class="sffc-crm-reddit-material-kit" data-material-kit-flow>',
      '<div class="sffc-crm-reddit-material-kit-head">',
      '<p class="sffc-crm-reddit-material-kit-kicker">Apply+ Tailored Materials</p>',
      "<h3>Your kit is being prepared for " +
        escapeHtml(companyName || "this company") +
        "</h3>",
      "<p>MENA Careers is assembling practical application materials from the live role brief so you can move faster on this application.</p>",
      "</div>",
      '<div class="sffc-crm-reddit-material-kit-files">' + rows + "</div>",
      "</div>",
    ].join("");
  }

  function getMaterialKitReadyMarkup(resources, hasAccess) {
    var calmRows = renderMaterialKitCards(resources, hasAccess, false);
    return [
      '<div class="sffc-crm-reddit-material-kit" data-material-kit-flow>',
      '<div class="sffc-crm-reddit-material-kit-head">',
      '<p class="sffc-crm-reddit-material-kit-kicker">Apply+ Tailored Materials</p>',
      "<h3>Your kit is ready</h3>",
      "<p>These materials are lined up for this role so you can move straight into tailoring and submission.</p>",
      "</div>",
      '<div class="sffc-crm-reddit-material-kit-toolbar"><span class="sffc-crm-reddit-material-kit-toolbar-pill is-active">Files</span><span class="sffc-crm-reddit-material-kit-toolbar-note">Role-specific documents ready to use</span><button type="button" class="sffc-crm-reddit-single-pack-cta sffc-crm-reddit-single-pack-cta--toolbar" data-kit-generate-pack>Get Application Pack</button></div>',
      '<div class="sffc-crm-reddit-material-kit-files">' + calmRows + "</div>",
      '<div class="sffc-crm-reddit-material-kit-gate" data-kit-gate hidden></div>',
      "</div>",
    ].join("");

    var rows = resources
      .map(function (resource, index) {
        return [
          '<div class="sffc-crm-reddit-kit-file is-ready sffc-crm-reddit-kit-file--' +
            resource.kind +
            '" data-kit-file-row data-kit-file-index="' +
            index +
            '">',
          '<span class="sffc-crm-reddit-kit-file-icon" aria-hidden="true"></span>',
          '<div class="sffc-crm-reddit-kit-file-body">',
          '<div class="sffc-crm-reddit-kit-file-head">',
          "<strong>" + escapeHtml(resource.label) + "</strong>",
          '<span class="sffc-crm-reddit-kit-file-status">' +
            (hasAccess ? "Ready" : "Locked") +
            "</span>",
          "</div>",
          '<div class="sffc-crm-reddit-kit-file-bar is-complete"><span></span></div>',
          '<div class="sffc-crm-reddit-kit-file-meta"><span>' +
            escapeHtml(resource.fileType) +
            " • Specific to this role</span></div>",
          "</div>",
          '<button type="button" class="sffc-crm-reddit-kit-file-download" data-kit-download data-kit-index="' +
            index +
            '">' +
            (hasAccess ? "Open" : "Download") +
            "</button>",
          "</div>",
        ].join("");
      })
      .join("");

    return [
      '<div class="sffc-crm-reddit-material-kit" data-material-kit-flow>',
      '<div class="sffc-crm-reddit-material-kit-head">',
      '<p class="sffc-crm-reddit-material-kit-kicker">Apply+ Tailored Materials</p>',
      "<h3>Your kit is ready</h3>",
      "<p>These materials are lined up for this role so you can move straight into tailoring and submission.</p>",
      "</div>",
      '<div class="sffc-crm-reddit-material-kit-files">' + rows + "</div>",
      '<div class="sffc-crm-reddit-material-kit-gate" data-kit-gate hidden></div>',
      "</div>",
    ].join("");
  }

  function openMaterialKitModal(shell, trigger) {
    var modal = shell
      ? shell.querySelector("[data-single-material-modal]")
      : null;
    if (!modal) {
      return;
    }

    var resources = getApplyKitResources(trigger);
    var companyName = trigger ? trigger.getAttribute("data-company") || "" : "";
    var titleNode = modal.querySelector("[data-material-title]");
    var results = modal.querySelector("[data-material-results]");

    modal.__kitResources = resources;
    modal.__kitHasAccess = trigger
      ? trigger.getAttribute("data-material-access") === "true"
      : false;
    modal.__kitSelectedFile = "";
    modal.__kitTrigger = trigger || null;

    if (titleNode) {
      titleNode.textContent = "Application Materials Kit";
    }
    if (results) {
      results.innerHTML = getMaterialKitLoadingMarkup(resources, companyName);
    }

    modal.hidden = false;
    modal.setAttribute("data-material-mode", "kit");
    document.documentElement.classList.add("sffc-crm-reddit-modal-open");
    document.body.classList.add("sffc-crm-reddit-modal-open");

    var dialog = modal.querySelector(".sffc-crm-reddit-materials-dialog");
    if (dialog) {
      dialog.focus();
    }
  }

  function renderMaterialKitCards(resources, hasAccess, isLoading) {
    return (resources || [])
      .map(function (resource, index) {
        var statusLabel = isLoading
          ? "Preparing"
          : hasAccess
          ? "Ready"
          : "Preview";
        var actionLabel = isLoading ? "..." : hasAccess ? "Open" : "Download";

        return [
          '<div class="sffc-crm-reddit-kit-card sffc-crm-reddit-kit-card--' +
            escapeHtml(resource.kind || "word") +
            (isLoading ? " is-loading" : "") +
            '" data-kit-file-row data-kit-file-index="' +
            index +
            '">',
          '<button type="button" class="sffc-crm-reddit-kit-card-menu" aria-label="More options" tabindex="-1">',
          '<svg viewBox="0 0 20 20"><path d="M10 4.5a1.25 1.25 0 1 0 0 .01M10 10a1.25 1.25 0 1 0 0 .01M10 15.5a1.25 1.25 0 1 0 0 .01" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
          "</button>",
          '<div class="sffc-crm-reddit-kit-card-main">',
          '<span class="sffc-crm-reddit-kit-card-icon" aria-hidden="true"></span>',
          '<div class="sffc-crm-reddit-kit-card-copy">',
          "<strong>" + escapeHtml(resource.label) + "</strong>",
          "<span>" +
            escapeHtml(resource.fileType) +
            " • " +
            statusLabel +
            "</span>",
          "</div>",
          "</div>",
          '<div class="sffc-crm-reddit-kit-card-foot">',
          '<div class="sffc-crm-reddit-kit-card-agent"><span class="sffc-crm-reddit-kit-card-agent-dot"></span><span>MENA Careers Materials</span></div>',
          isLoading
            ? '<span class="sffc-crm-reddit-kit-card-status">Building</span>'
            : '<button type="button" class="sffc-crm-reddit-kit-card-action" data-kit-download data-kit-index="' +
              index +
              '">' +
              actionLabel +
              "</button>",
          "</div>",
          "</div>",
        ].join("");
      })
      .join("");
  }

  function stopMaterialKitAnimation(modal) {
    if (!modal || !modal.__kitTimer) {
      return;
    }
    window.clearTimeout(modal.__kitTimer);
    window.clearInterval(modal.__kitTimer);
    modal.__kitTimer = null;
  }

  function startMaterialKitAnimation(modal) {
    if (!modal) {
      return;
    }

    stopMaterialKitAnimation(modal);
    var calmResults = modal.querySelector("[data-material-results]");
    if (!calmResults) {
      return;
    }

    modal.__kitTimer = window.setTimeout(function () {
      stopMaterialKitAnimation(modal);
      calmResults.innerHTML = getMaterialKitReadyMarkup(
        modal.__kitResources || [],
        !!modal.__kitHasAccess
      );
    }, 1200);
    return;

    stopMaterialKitAnimation(modal);
    var rows = Array.prototype.slice.call(
      modal.querySelectorAll("[data-kit-file-row]")
    );
    if (!rows.length) {
      return;
    }

    var progress = 0;
    modal.__kitTimer = window.setInterval(function () {
      progress += 8;

      rows.forEach(function (row, rowIndex) {
        var rowProgress = Math.max(0, Math.min(100, progress - rowIndex * 18));
        var percentNode = row.querySelector("[data-kit-file-progress]");
        var barNode = row.querySelector("[data-kit-file-bar]");
        var metaNode = row.querySelector("[data-kit-file-size]");

        if (percentNode) {
          percentNode.textContent = Math.min(rowProgress, 100) + "%";
        }
        if (barNode) {
          barNode.style.width = Math.min(rowProgress, 100) + "%";
        }
        if (metaNode) {
          metaNode.textContent =
            rowProgress >= 100
              ? "Download-ready file"
              : "Preparing download...";
        }
      });

      if (progress >= 160) {
        stopMaterialKitAnimation(modal);
        var results = modal.querySelector("[data-material-results]");
        if (results) {
          results.innerHTML = getMaterialKitReadyMarkup(
            modal.__kitResources || [],
            !!modal.__kitHasAccess
          );
        }
      }
    }, 180);
  }

  function fetchFeaturedSignupPlan() {
    if (window.__sffcFeaturedSignupPlanPromise) {
      return window.__sffcFeaturedSignupPlanPromise;
    }

    if (
      !redditConfig.ajaxUrl ||
      !redditConfig.nonce ||
      typeof window.fetch !== "function"
    ) {
      return Promise.reject(
        new Error("Membership plans are unavailable right now.")
      );
    }

    var body = new window.FormData();
    body.append("action", "sffc_crm_get_signup_plans");
    body.append("nonce", redditConfig.nonce);

    window.__sffcFeaturedSignupPlanPromise = window
      .fetch(redditConfig.ajaxUrl, {
        method: "POST",
        credentials: "same-origin",
        body: body,
      })
      .then(function (response) {
        return response.json();
      })
      .then(function (payload) {
        if (!payload || !payload.success || !payload.data) {
          throw new Error("Membership plans are unavailable right now.");
        }
        return payload.data.featured || payload.data.annual || null;
      });

    return window.__sffcFeaturedSignupPlanPromise;
  }

  function renderMaterialKitGateMarkup(plan, email, selectedFile, resources) {
    var price = plan && plan.price ? plan.price : "Membership";
    var materials = (resources || [])
      .map(function (resource) {
        return "<li>" + escapeHtml(resource.label) + "</li>";
      })
      .join("");

    return [
      '<div class="sffc-crm-reddit-material-kit-subscribe">',
      '<div class="sffc-crm-reddit-material-kit-subscribe-copy">',
      '<p class="sffc-crm-reddit-material-kit-subscribe-kicker">Access Unlimited Application Materials</p>',
      "<h4>" + escapeHtml(selectedFile || "Unlock this download") + "</h4>",
      "<p>Unlock the full materials kit for this role, including the documents below and future application materials across MENA Careers.</p>",
      "</div>",
      '<div class="sffc-crm-reddit-material-kit-subscribe-price">' +
        escapeHtml(price) +
        "</div>",
      '<div class="sffc-crm-reddit-material-kit-subscribe-list"><strong>Your kit includes</strong><ul>' +
        materials +
        "</ul></div>",
      '<div class="sffc-crm-reddit-material-kit-subscribe-form">',
      '<label for="sffc-material-kit-email">Email</label>',
      '<input type="email" id="sffc-material-kit-email" data-kit-email value="' +
        escapeHtml(email || "") +
        '" placeholder="you@example.com">',
      '<button type="button" class="sffc-crm-reddit-material-kit-subscribe-btn" data-kit-unlock>Continue to Membership</button>',
      "</div>",
      '<div class="sffc-crm-reddit-material-kit-membership-form" data-kit-membership-form hidden></div>',
      "</div>",
    ].join("");
  }

  function prefillMaterialMembershipForm(container, email) {
    if (!container) {
      return;
    }
    [
      'input[name="user_email"]',
      'input[name="email"]',
      "#user_email",
      "#mepr_email",
      'input[name="mepr_user_email"]',
    ].forEach(function (selector) {
      container.querySelectorAll(selector).forEach(function (field) {
        field.value = email || "";
      });
    });
  }

  function revealMaterialKitGate(modal, selectedFile) {
    if (!modal || !modal.__kitResources || modal.__kitHasAccess) {
      return;
    }

    var gate = modal.querySelector("[data-kit-gate]");
    if (!gate) {
      return;
    }

    gate.hidden = false;
    gate.innerHTML =
      '<div class="sffc-crm-reddit-material-kit-subscribe is-loading"><p>Loading membership options...</p></div>';

    fetchFeaturedSignupPlan()
      .then(function (plan) {
        modal.__kitPlan = plan;
        var email =
          redditConfig.currentUser && redditConfig.currentUser.email
            ? redditConfig.currentUser.email
            : "";
        modal.__kitSelectedFile = selectedFile || "";
        gate.innerHTML = renderMaterialKitGateMarkup(
          plan || {},
          email,
          modal.__kitSelectedFile,
          modal.__kitResources || []
        );
      })
      .catch(function (error) {
        gate.innerHTML =
          '<div class="sffc-crm-reddit-material-kit-subscribe is-error"><p>' +
          escapeHtml(
            error && error.message
              ? error.message
              : "Membership is unavailable right now."
          ) +
          "</p></div>";
      });
  }

  function loadMaterialMembershipForm(modal, email) {
    if (!modal || !modal.__kitPlan) {
      return;
    }

    if (!modal.__kitPlan.shortcode) {
      if (modal.__kitPlan.url) {
        window.open(modal.__kitPlan.url, "_blank", "noopener");
      }
      return;
    }

    if (
      !redditConfig.ajaxUrl ||
      !redditConfig.nonce ||
      typeof window.fetch !== "function"
    ) {
      return;
    }

    var formShell = modal.querySelector("[data-kit-membership-form]");
    if (!formShell) {
      return;
    }

    formShell.hidden = false;
    formShell.innerHTML =
      '<div class="sffc-crm-reddit-material-kit-membership-loading">Loading membership form...</div>';

    var body = new window.FormData();
    body.append("action", "sffc_crm_render_membership_form");
    body.append("nonce", redditConfig.nonce);
    body.append("shortcode", modal.__kitPlan.shortcode);

    window
      .fetch(redditConfig.ajaxUrl, {
        method: "POST",
        credentials: "same-origin",
        body: body,
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
              "Unable to load membership form."
          );
        }

        formShell.innerHTML = payload.data.html;
        prefillMaterialMembershipForm(formShell, email);
      })
      .catch(function (error) {
        formShell.innerHTML =
          '<div class="sffc-crm-reddit-material-kit-subscribe is-error"><p>' +
          escapeHtml(
            error && error.message
              ? error.message
              : "Unable to load membership form."
          ) +
          "</p></div>";
      });
  }

  function buildVirtualMaterialTrigger(resource) {
    var trigger = document.createElement("button");
    trigger.setAttribute("data-material-type", resource.materialType || "");
    trigger.setAttribute(
      "data-material-label",
      resource.label || "Application Material"
    );
    trigger.setAttribute("data-material-kind", resource.kind || "word");
    trigger.setAttribute(
      "data-material-file-type",
      resource.fileType || "DOCX"
    );
    trigger.setAttribute("data-material-use-cv", "false");
    trigger.setAttribute("data-company", resource.company || "");
    trigger.setAttribute("data-jobs-post-id", resource.jobsPostId || "");
    trigger.setAttribute("data-crm-post-id", resource.crmPostId || "");
    return trigger;
  }

  function openKitResource(shell, modal, resource) {
    if (!shell || !modal || !resource) {
      return;
    }

    var titleNode = modal.querySelector("[data-material-title]");
    var results = modal.querySelector("[data-material-results]");
    var virtualTrigger = buildVirtualMaterialTrigger(resource);

    if (titleNode) {
      titleNode.textContent = resource.label || "Application Material";
    }
    if (results) {
      results.innerHTML = getMaterialLoadingMarkup(
        resource.materialType || "",
        resource.company || ""
      );
    }

    modal.setAttribute("data-material-mode", "single");
    startMaterialLoadingAnimation(modal);
    requestMaterial(shell, virtualTrigger);
  }

  function openSingleMaterialModal(shell, trigger) {
    var modal = shell
      ? shell.querySelector("[data-single-material-modal]")
      : null;
    if (!modal) {
      return;
    }

    var title = trigger
      ? trigger.getAttribute("data-material-label") || "Application Material"
      : "Application Material";
    var materialType = trigger
      ? trigger.getAttribute("data-material-type") || ""
      : "";
    var companyName = trigger ? trigger.getAttribute("data-company") || "" : "";
    var titleNode = modal.querySelector("[data-material-title]");
    var results = modal.querySelector("[data-material-results]");
    if (titleNode) {
      titleNode.textContent = title;
    }
    if (results) {
      results.innerHTML = getMaterialLoadingMarkup(materialType, companyName);
    }

    modal.hidden = false;
    modal.setAttribute("data-material-mode", "single");
    modal.setAttribute("data-current-material-type", materialType);
    document.documentElement.classList.add("sffc-crm-reddit-modal-open");
    document.body.classList.add("sffc-crm-reddit-modal-open");

    var dialog = modal.querySelector(".sffc-crm-reddit-materials-dialog");
    if (dialog) {
      dialog.focus();
    }

    startMaterialLoadingAnimation(modal);
  }

  function closeSingleMaterialModal(shell) {
    var modal = shell
      ? shell.querySelector("[data-single-material-modal]")
      : null;
    if (!modal) {
      return;
    }

    stopMaterialLoadingAnimation(modal);
    stopMaterialKitAnimation(modal);
    modal.__kitResources = null;
    modal.__kitPlan = null;
    modal.hidden = true;
    document.documentElement.classList.remove("sffc-crm-reddit-modal-open");
    document.body.classList.remove("sffc-crm-reddit-modal-open");
  }

  function requestMaterial(shell, trigger) {
    var modal = shell
      ? shell.querySelector("[data-single-material-modal]")
      : null;
    var results = modal ? modal.querySelector("[data-material-results]") : null;

    if (
      !modal ||
      !results ||
      !trigger ||
      !redditConfig.ajaxUrl ||
      !redditConfig.materialNonce ||
      typeof window.fetch !== "function"
    ) {
      if (results) {
        results.innerHTML = getMaterialErrorMarkup(
          "This material is unavailable right now."
        );
      }
      return Promise.resolve();
    }

    var materialType = trigger.getAttribute("data-material-type") || "";
    var jobsPostId = trigger.getAttribute("data-jobs-post-id") || "";
    var useCvText =
      trigger.getAttribute("data-material-use-cv") === "true" ||
      !!trigger.closest("[data-reddit-gap-modal]");
    var liveCvText = useCvText ? getActiveGapCvText() : "";
    var cacheKey = getMaterialCacheKey(materialType, jobsPostId, liveCvText);

    modal.__materialCache = modal.__materialCache || {};
    if (modal.__materialCache[cacheKey]) {
      stopMaterialLoadingAnimation(modal);
      var cached = modal.__materialCache[cacheKey];
      var titleNode = modal.querySelector("[data-material-title]");
      if (titleNode && cached.title) {
        titleNode.textContent = cached.title;
      }
      results.innerHTML = cached.markup;
      return Promise.resolve();
    }

    return fetchMaterialData(trigger)
      .then(function (data) {
        stopMaterialLoadingAnimation(modal);
        modal.__materialCache[cacheKey] = {
          title:
            data.title ||
            trigger.getAttribute("data-material-label") ||
            "Application Material",
          markup: data.markup,
        };

        var titleNode = modal.querySelector("[data-material-title]");
        if (titleNode) {
          titleNode.textContent = modal.__materialCache[cacheKey].title;
        }

        results.innerHTML = data.markup;
      })
      .catch(function (error) {
        stopMaterialLoadingAnimation(modal);
        results.innerHTML = getMaterialErrorMarkup(
          error && error.message
            ? error.message
            : "We couldn't prepare this material."
        );
      });
  }

  function fetchMaterialData(trigger) {
    if (
      !trigger ||
      !redditConfig.ajaxUrl ||
      !redditConfig.materialNonce ||
      typeof window.fetch !== "function"
    ) {
      return Promise.reject(
        new Error("This material is unavailable right now.")
      );
    }

    var body = new window.FormData();
    var useCvText =
      trigger.getAttribute("data-material-use-cv") === "true" ||
      !!trigger.closest("[data-reddit-gap-modal]");
    var liveCvText = useCvText ? getActiveGapCvText() : "";

    body.append("action", "sffc_crm_reddit_material_generate");
    body.append("nonce", redditConfig.materialNonce);
    body.append(
      "jobs_post_id",
      trigger.getAttribute("data-jobs-post-id") || ""
    );
    body.append("crm_post_id", trigger.getAttribute("data-crm-post-id") || "");
    body.append(
      "material_type",
      trigger.getAttribute("data-material-type") || ""
    );
    body.append("cv_text", liveCvText);

    return window
      .fetch(redditConfig.ajaxUrl, {
        method: "POST",
        credentials: "same-origin",
        body: body,
      })
      .then(function (response) {
        return response.json();
      })
      .then(function (payload) {
        if (
          !payload ||
          !payload.success ||
          !payload.data ||
          !payload.data.markup
        ) {
          throw new Error(
            (payload && payload.data && payload.data.message) ||
              "We couldn't prepare this material."
          );
        }
        return payload.data;
      });
  }

  function fetchMaterialPackData(trigger) {
    if (
      !trigger ||
      !redditConfig.ajaxUrl ||
      !redditConfig.materialNonce ||
      typeof window.fetch !== "function"
    ) {
      return Promise.reject(
        new Error("This application pack is unavailable right now.")
      );
    }

    var body = new window.FormData();
    body.append("action", "sffc_crm_reddit_material_pack_generate");
    body.append("nonce", redditConfig.materialNonce);
    body.append(
      "jobs_post_id",
      trigger.getAttribute("data-jobs-post-id") || ""
    );
    body.append("crm_post_id", trigger.getAttribute("data-crm-post-id") || "");
    body.append("cv_text", "");

    return window
      .fetch(redditConfig.ajaxUrl, {
        method: "POST",
        credentials: "same-origin",
        body: body,
      })
      .then(function (response) {
        return response.json();
      })
      .then(function (payload) {
        if (
          !payload ||
          !payload.success ||
          !payload.data ||
          !payload.data.resources
        ) {
          throw new Error(
            (payload && payload.data && payload.data.message) ||
              "We couldn't prepare this application pack."
          );
        }
        return payload.data;
      });
  }

  function generateMaterialPackSequentially(shell, modal, resources) {
    var queue = (resources || []).map(function (resource) {
      var virtualTrigger = buildVirtualMaterialTrigger(resource);
      return fetchMaterialData(virtualTrigger).then(function (data) {
        primeMaterialCache(modal, resource, data, "");
        return {
          label: resource.label,
          materialType: resource.materialType,
          fileType: resource.fileType,
          kind: resource.kind,
          company: resource.company,
          jobsPostId: resource.jobsPostId,
          crmPostId: resource.crmPostId,
          hasAccess: true,
        };
      });
    });

    return Promise.all(queue);
  }

  function requestMaterialPack(shell, trigger) {
    var modal = shell
      ? shell.querySelector("[data-single-material-modal]")
      : null;
    var results = modal ? modal.querySelector("[data-material-results]") : null;
    var resources = getApplyKitResources(trigger);

    if (!modal || !results || !resources.length) {
      if (results) {
        results.innerHTML = getMaterialErrorMarkup(
          "This application pack is unavailable right now."
        );
      }
      return Promise.resolve();
    }

    modal.__kitResources = resources;
    modal.__kitHasAccess = true;
    modal.__kitSelectedFile = "";
    modal.__materialCache = modal.__materialCache || {};

    return fetchMaterialPackData(trigger)
      .then(function (data) {
        var hydratedResources = (data.resources || []).map(function (resource) {
          var normalized = {
            label: resource.label || "Application Material",
            materialType: resource.material_type || "",
            fileType: resource.file_type || "DOCX",
            kind: resource.kind || "word",
            company:
              resource.company || trigger.getAttribute("data-company") || "",
            jobsPostId: String(
              resource.jobs_post_id ||
                trigger.getAttribute("data-jobs-post-id") ||
                ""
            ),
            crmPostId: String(
              resource.crm_post_id ||
                trigger.getAttribute("data-crm-post-id") ||
                ""
            ),
            hasAccess: true,
          };
          primeMaterialCache(
            modal,
            normalized,
            {
              title: resource.title || normalized.label,
              markup: resource.markup || "",
            },
            ""
          );
          return normalized;
        });

        modal.__kitResources = hydratedResources;
        results.innerHTML = getMaterialKitReadyMarkup(hydratedResources, true);
      })
      .catch(function () {
        return generateMaterialPackSequentially(shell, modal, resources)
          .then(function (fallbackResources) {
            modal.__kitResources = fallbackResources;
            results.innerHTML = getMaterialKitReadyMarkup(
              fallbackResources,
              true
            );
          })
          .catch(function (error) {
            results.innerHTML = getMaterialErrorMarkup(
              error && error.message
                ? error.message
                : "We couldn't prepare this application pack."
            );
          });
      });
  }

  function refreshMaterialPack(modal) {
    if (!modal) {
      return;
    }

    var shell = modal.closest(".sffc-crm-reddit-shell");
    var trigger = modal.__kitTrigger || null;
    var results = modal.querySelector("[data-material-results]");
    var companyName = trigger ? trigger.getAttribute("data-company") || "" : "";

    if (!shell || !trigger || !results) {
      return;
    }

    if (trigger.getAttribute("data-material-access") !== "true") {
      revealMaterialKitGate(modal, "Application Pack");
      return;
    }

    results.innerHTML = getMaterialKitLoadingMarkup(
      modal.__kitResources || getApplyKitResources(trigger),
      companyName
    );
    requestMaterialPack(shell, trigger);
  }

  function getActiveGapCvText() {
    var shell = document.getElementById("sffc-gap-analyzer-shell");
    var field = shell
      ? shell.querySelector('.inst-gap-textarea[data-input="cv"]')
      : null;
    return field && typeof field.value === "string" ? field.value.trim() : "";
  }

  function hashString(value) {
    if (!value) {
      return "0";
    }

    var hash = 0;
    for (var i = 0; i < value.length; i++) {
      hash = (hash << 5) - hash + value.charCodeAt(i);
      hash |= 0;
    }
    return String(hash);
  }

  function copyMaterial(trigger) {
    var article = trigger.closest("[data-material-article]");
    var source = article
      ? article.querySelector("[data-material-copy-source]")
      : null;
    var text = source ? source.value : "";

    if (!text) {
      return;
    }

    var original = trigger.textContent;
    var reset = function () {
      window.setTimeout(function () {
        trigger.textContent = original;
      }, 1400);
    };

    if (
      navigator.clipboard &&
      typeof navigator.clipboard.writeText === "function"
    ) {
      navigator.clipboard
        .writeText(text)
        .then(function () {
          trigger.textContent = "Copied";
          reset();
        })
        .catch(function () {
          trigger.textContent = "Copy failed";
          reset();
        });
      return;
    }

    source.hidden = false;
    source.focus();
    source.select();
    try {
      document.execCommand("copy");
      trigger.textContent = "Copied";
    } catch (error) {
      trigger.textContent = "Copy failed";
    }
    source.hidden = true;
    reset();
  }

  function downloadMaterial(trigger) {
    var article = trigger.closest("[data-material-article]");
    var directUrl = article
      ? article.getAttribute("data-download-url") || ""
      : "";
    if (directUrl) {
      var externalLink = document.createElement("a");
      externalLink.href = directUrl;
      externalLink.target = "_blank";
      externalLink.rel = "noopener noreferrer";
      document.body.appendChild(externalLink);
      externalLink.click();
      document.body.removeChild(externalLink);
      return;
    }

    var source = article
      ? article.querySelector("[data-material-copy-source]")
      : null;
    var text = source ? source.value : "";
    if (!text) {
      return;
    }

    var name = article.getAttribute("data-download-name") || "senna-material";
    var extension = article.getAttribute("data-download-extension") || "txt";
    var blob = new window.Blob([text], { type: "text/plain;charset=utf-8" });
    var url = window.URL.createObjectURL(blob);
    var link = document.createElement("a");
    link.href = url;
    link.download = name + "." + extension;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.setTimeout(function () {
      window.URL.revokeObjectURL(url);
    }, 1000);
  }

  function getMaterialLoadingLabel(materialType) {
    var labels = {
      cv_template: "CV template",
      cover_letter: "cover letter template",
      interview_questions: "interview questions template",
      hiring_guide: "hiring guide template",
    };

    return labels[materialType] || "tailored material";
  }

  function setNetworkingModalState(modal, state) {
    if (!modal) {
      return;
    }

    modal.setAttribute("data-networking-loaded", state);

    var refine = modal.querySelector("[data-networking-refine]");
    if (refine) {
      refine.hidden = state !== "true";
    }
  }

  function stopNetworkingLoadingAnimation(modal) {
    if (!modal) {
      return;
    }

    if (modal.__networkingLoaderTimer) {
      window.clearInterval(modal.__networkingLoaderTimer);
      modal.__networkingLoaderTimer = null;
    }
  }

  function startNetworkingLoadingAnimation(modal) {
    if (!modal) {
      return;
    }

    stopNetworkingLoadingAnimation(modal);

    var steps = Array.prototype.slice.call(
      modal.querySelectorAll("[data-networking-loading-step]")
    );
    var percent = modal.querySelector("[data-networking-loading-percent]");
    var status = modal.querySelector("[data-networking-loading-status]");
    var bar = modal.querySelector("[data-networking-loading-bar]");

    if (!steps.length || !percent || !status || !bar) {
      return;
    }

    var stepLabels = steps.map(function (step) {
      return step.textContent.trim();
    });
    var stepPercents = [12, 38, 64, 86];
    var activeIndex = 0;

    function renderStep(index) {
      activeIndex = Math.min(index, steps.length - 1);
      steps.forEach(function (step, idx) {
        step.classList.toggle("is-active", idx === activeIndex);
        step.classList.toggle("is-complete", idx < activeIndex);
      });
      percent.textContent = stepPercents[activeIndex] + "%";
      status.textContent = stepLabels[activeIndex] || "";
      bar.style.width = stepPercents[activeIndex] + "%";
    }

    renderStep(0);
    modal.__networkingLoaderTimer = window.setInterval(function () {
      if (activeIndex < steps.length - 1) {
        renderStep(activeIndex + 1);
        return;
      }

      percent.textContent = "92%";
      status.textContent = "Finalising role-specific strategy";
      bar.style.width = "92%";
    }, 1400);
  }

  function openSingleNetworkingModal(shell) {
    var modal = shell.querySelector("[data-single-networking-modal]");
    if (!modal) {
      return;
    }

    activateNetworkingPanel(modal, "templates");
    setNetworkingModalState(
      modal,
      modal.getAttribute("data-networking-loaded") === "true" ? "true" : "idle"
    );
    modal.hidden = false;
    document.documentElement.classList.add("sffc-crm-reddit-modal-open");
    document.body.classList.add("sffc-crm-reddit-modal-open");

    var dialog = modal.querySelector(".sffc-crm-reddit-networking-dialog");
    if (dialog) {
      dialog.focus();
    }
  }

  function closeSingleNetworkingModal(shell) {
    var modal = shell.querySelector("[data-single-networking-modal]");
    if (!modal) {
      return;
    }

    modal.hidden = true;
    document.documentElement.classList.remove("sffc-crm-reddit-modal-open");
    document.body.classList.remove("sffc-crm-reddit-modal-open");
  }

  function activateNetworkingPanel(modal, panelKey) {
    if (!modal || !panelKey) {
      return;
    }

    modal.querySelectorAll("[data-networking-panel]").forEach(function (panel) {
      var isActive = panel.getAttribute("data-networking-panel") === panelKey;
      panel.hidden = !isActive;
      panel.classList.toggle("is-active", isActive);
    });

    modal
      .querySelectorAll("[data-networking-target]")
      .forEach(function (button) {
        var isCurrent =
          button.getAttribute("data-networking-target") === panelKey;
        button.classList.toggle("is-active", isCurrent);
        button.setAttribute("aria-pressed", isCurrent ? "true" : "false");
        button.setAttribute("aria-current", isCurrent ? "page" : "false");
      });

    var resultsShell = modal.querySelector(
      ".sffc-crm-reddit-networking-results-shell"
    );
    var canvas = modal.querySelector(".sffc-crm-reddit-networking-canvas");
    var body = modal.querySelector(".sffc-crm-reddit-networking-body");
    var scrollContainer =
      canvas && canvas.scrollHeight > canvas.clientHeight ? canvas : body;
    if (scrollContainer && resultsShell) {
      window.setTimeout(function () {
        var bodyRect = scrollContainer.getBoundingClientRect();
        var shellRect = resultsShell.getBoundingClientRect();
        var nextTop =
          scrollContainer.scrollTop + (shellRect.top - bodyRect.top) - 18;
        scrollContainer.scrollTo({
          top: Math.max(0, nextTop),
          behavior: "smooth",
        });
      }, 20);
    }
  }

  function requestNetworkingStrategy(shell, submitButton, userContext) {
    var modal = shell
      ? shell.querySelector("[data-single-networking-modal]")
      : null;
    var results = modal
      ? modal.querySelector("[data-networking-results]")
      : null;
    if (
      !modal ||
      !results ||
      !redditConfig.ajaxUrl ||
      !redditConfig.networkingNonce ||
      typeof window.fetch !== "function"
    ) {
      if (results) {
        results.innerHTML = getNetworkingErrorMarkup(
          "Networking strategy is unavailable right now."
        );
      }
      return Promise.resolve();
    }

    var button = submitButton || modal.querySelector("[data-jobs-post-id]");
    var jobsPostId = button
      ? button.getAttribute("data-jobs-post-id") || ""
      : "";
    var crmPostId = button ? button.getAttribute("data-crm-post-id") || "" : "";

    results.innerHTML = getNetworkingLoadingMarkup();
    setNetworkingModalState(modal, "loading");
    startNetworkingLoadingAnimation(modal);

    if (submitButton) {
      submitButton.disabled = true;
    }

    var body = new window.FormData();
    body.append("action", "sffc_crm_reddit_networking_strategy");
    body.append("nonce", redditConfig.networkingNonce);
    body.append("jobs_post_id", jobsPostId);
    body.append("crm_post_id", crmPostId);
    body.append("user_context", userContext || "");

    return window
      .fetch(redditConfig.ajaxUrl, {
        method: "POST",
        credentials: "same-origin",
        body: body,
      })
      .then(function (response) {
        return response.json();
      })
      .then(function (payload) {
        if (
          !payload ||
          !payload.success ||
          !payload.data ||
          !payload.data.markup
        ) {
          throw new Error(
            (payload && payload.data && payload.data.message) ||
              "We couldn't build the networking strategy."
          );
        }

        results.innerHTML = payload.data.markup;
        stopNetworkingLoadingAnimation(modal);
        setNetworkingModalState(modal, "true");
      })
      .catch(function (error) {
        results.innerHTML = getNetworkingErrorMarkup(
          error && error.message
            ? error.message
            : "We couldn't build the networking strategy."
        );
        stopNetworkingLoadingAnimation(modal);
        setNetworkingModalState(modal, "error");
      })
      .finally(function () {
        stopNetworkingLoadingAnimation(modal);
        if (submitButton) {
          submitButton.disabled = false;
        }
      });
  }

  function restoreGapAnalyzerShell() {
    var shell = document.getElementById("sffc-gap-analyzer-shell");
    var park = document.getElementById("sffc-gap-analyzer-park");
    var body = document.querySelector("[data-reddit-gap-body]");

    clearGapLoadingTimers();

    if (body) {
      body.classList.remove("is-loaded", "is-error");
      body.innerHTML = getGapLoadingMarkup();
    }

    if (shell && park && shell.parentNode !== park) {
      shell.setAttribute("hidden", "hidden");
      shell.style.display = "none";
      park.appendChild(shell);
    }
  }

  function hydrateGapAnalyzerShell(shell, payload) {
    var analyzer = null;
    var jobTitleNode = shell.querySelector("[data-gap-job-title]");
    var backButton = shell.querySelector("[data-gap-back]");
    var materialSlots = shell.querySelectorAll("[data-gap-material-slot]");
    var quickMaterials = shell.querySelector("[data-gap-quick-materials]");
    if (typeof window.jQuery !== "undefined") {
      analyzer = window
        .jQuery(shell)
        .closest("[data-component='gap-analyzer']")
        .data("gapAnalyzerInstance");
    }

    var jdText = payload && payload.jd_text ? payload.jd_text : "";
    var cvText = payload && payload.cv_text ? payload.cv_text : "";
    var hasCv = !!(payload && payload.has_cv);
    var jobTitle =
      payload && typeof payload.job_title === "string" ? payload.job_title : "";
    var company =
      payload && typeof payload.company === "string" ? payload.company : "";
    var materialsMarkup =
      payload && typeof payload.materials_markup === "string"
        ? payload.materials_markup
        : "";
    hydrateGapRecruiter(shell, payload || {});

    if (jobTitleNode) {
      var titleText = jobTitle || "";
      if (company) {
        titleText = titleText ? titleText + " · " + company : company;
      }
      jobTitleNode.textContent = titleText || "Optimize this application";
    }

    if (backButton) {
      var modal = shell.closest("[data-reddit-gap-modal]");
      backButton.hidden = !(modal && modal.__returnToApply);
    }

    if (materialSlots && materialSlots.length) {
      var materialMap = {};
      if (materialsMarkup) {
        var materialParser = document.createElement("div");
        materialParser.innerHTML = materialsMarkup;
        materialParser
          .querySelectorAll(
            ".sffc-crm-reddit-single-pack-card[data-material-type]"
          )
          .forEach(function (card) {
            var materialType = card.getAttribute("data-material-type");
            if (materialType && !materialMap[materialType]) {
              materialMap[materialType] = card.outerHTML;
            }
          });
      }

      if (quickMaterials) {
        if (materialsMarkup) {
          quickMaterials.innerHTML = materialsMarkup;
          quickMaterials.hidden = false;
        } else {
          quickMaterials.innerHTML = "";
          quickMaterials.hidden = true;
        }
      }

      materialSlots.forEach(function (slot) {
        var type = slot.getAttribute("data-gap-material-slot");
        var cardMarkup = type ? materialMap[type] : "";
        if (cardMarkup) {
          slot.innerHTML = cardMarkup;
          slot.hidden = false;
        } else {
          slot.innerHTML = "";
          slot.hidden = true;
        }
      });
    } else if (quickMaterials) {
      quickMaterials.innerHTML = materialsMarkup || "";
      quickMaterials.hidden = !materialsMarkup;
    }

    if (analyzer && typeof analyzer.loadDocuments === "function") {
      var titleText = jobTitle || "";
      if (company) {
        titleText = titleText ? titleText + " · " + company : company;
      }
      analyzer.loadDocuments(jdText, cvText, {
        autoAnalyze: hasCv,
        resetView: true,
        statusLabel: hasCv ? "Detected" : "JD ready",
        statusDetails: hasCv
          ? "MENA Careers detected the job description and your CV."
          : "MENA Careers detected the job description. Add your CV or LinkedIn text to continue.",
        hintText: hasCv
          ? "MENA Careers detected the job description and your CV"
          : "MENA Careers detected the job description. Add your CV or LinkedIn text to continue",
        jobTitleText: titleText || "Optimize this application",
      });

      window.requestAnimationFrame(function () {
        var visibleCvField = shell.querySelector(
          '.inst-gap-textarea[data-input="cv"]'
        );
        var visibleJdField = shell.querySelector(
          '.inst-gap-textarea[data-input="jd"]'
        );
        if (visibleCvField) {
          visibleCvField.value = cvText;
          visibleCvField.dispatchEvent(new Event("input", { bubbles: true }));
        }
        if (visibleJdField) {
          visibleJdField.value = jdText;
          visibleJdField.dispatchEvent(new Event("input", { bubbles: true }));
        }
      });
      return;
    }

    var jdField = shell.querySelector('.inst-gap-textarea[data-input="jd"]');
    var cvField = shell.querySelector('.inst-gap-textarea[data-input="cv"]');
    if (jdField) {
      jdField.value = jdText;
      jdField.dispatchEvent(new Event("input", { bubbles: true }));
    }
    if (cvField) {
      cvField.value = cvText;
      cvField.dispatchEvent(new Event("input", { bubbles: true }));
    }
  }

  function getDraftCvTextFromTrigger(trigger) {
    var reviewShell = trigger
      ? trigger.closest("[data-dashboard-review-shell]")
      : null;
    var draftField = reviewShell
      ? reviewShell.querySelector('textarea[name="cv_text"]')
      : null;
    if (!draftField) {
      return "";
    }

    return (draftField.value || "").trim();
  }

  function hydrateGapRecruiter(shell, payload) {
    if (!shell) {
      return;
    }

    var recruiterName =
      payload && typeof payload.recruiter_name === "string"
        ? payload.recruiter_name.trim()
        : "";
    var recruiterPhoto =
      payload && typeof payload.recruiter_photo === "string"
        ? payload.recruiter_photo
        : "";
    var recruiterTitle =
      payload && typeof payload.recruiter_title === "string"
        ? payload.recruiter_title.trim()
        : "";
    var nodes = shell.querySelectorAll(
      "[data-gap-header-recruiter], [data-gap-networking-recruiter]"
    );

    if (!nodes.length) {
      return;
    }

    var maskedName = getMaskedRecruiterName(recruiterName);
    var initial = maskedName ? maskedName.charAt(0).toUpperCase() : "R";

    nodes.forEach(function (node) {
      var photo = node.querySelector(
        "[data-gap-recruiter-photo], [data-gap-networking-recruiter-photo]"
      );
      var initialNode = node.querySelector(
        "[data-gap-recruiter-initial], [data-gap-networking-recruiter-initial]"
      );
      var nameNode = node.querySelector(
        "[data-gap-recruiter-name], [data-gap-networking-recruiter-name]"
      );
      var roleNode = node.querySelector(
        "[data-gap-recruiter-role], [data-gap-networking-recruiter-role]"
      );

      if (nameNode) {
        nameNode.textContent = maskedName || "Recruiter contact";
      }
      if (roleNode) {
        roleNode.textContent = recruiterTitle || "Recruitment Team";
      }
      if (initialNode) {
        initialNode.textContent = initial;
      }
      if (photo) {
        if (recruiterPhoto) {
          photo.src = recruiterPhoto;
          photo.hidden = false;
          photo.classList.add("is-gap-blurred");
        } else {
          photo.hidden = true;
        }
      }

      node.hidden = !(maskedName || recruiterTitle || recruiterPhoto);
    });
  }

  function getMaskedRecruiterName(fullName) {
    if (!fullName) {
      return "";
    }

    var parts = fullName.trim().split(/\s+/).filter(Boolean);
    if (!parts.length) {
      return "";
    }

    var firstName = parts[0];
    var lastName = parts.length > 1 ? parts[parts.length - 1] : "";
    var lastInitial = lastName ? lastName.charAt(0).toUpperCase() + "." : "";
    return (firstName + (lastInitial ? " " + lastInitial : "")).trim();
  }

  function renderGapAnalyzer(shell, payload) {
    var modal = document.querySelector("[data-reddit-gap-modal]");
    var body = document.querySelector("[data-reddit-gap-body]");
    var terminal = shell.querySelector(".inst-terminal");

    if (!modal || !body || !shell) {
      return;
    }

    clearGapLoadingTimers();
    body.classList.remove("is-error");
    body.classList.add("is-loaded");
    body.innerHTML = "";

    shell.removeAttribute("hidden");
    shell.style.display = "block";
    body.appendChild(shell);

    if (
      terminal &&
      terminal.__instTerminal &&
      typeof terminal.__instTerminal.refreshLayoutWhenVisible === "function"
    ) {
      terminal.__instTerminal.refreshLayoutWhenVisible();
    } else {
      window.dispatchEvent(new Event("resize"));
    }

    hydrateGapAnalyzerShell(shell, payload || {});

    modal.hidden = false;
    document.documentElement.classList.add("sffc-crm-reddit-modal-open");
    document.body.classList.add("sffc-crm-reddit-modal-open");

    var dialog = modal.querySelector(".sffc-crm-reddit-gap-dialog");
    if (dialog) {
      dialog.focus();
    }
  }

  function showGapAnalyzerError(message) {
    var modal = document.querySelector("[data-reddit-gap-modal]");
    var body = document.querySelector("[data-reddit-gap-body]");

    if (!modal || !body) {
      return;
    }

    clearGapLoadingTimers();
    body.classList.add("is-error");

    body.innerHTML =
      '<div class="sffc-crm-reddit-gap-error"><p>' + message + "</p></div>";
    modal.hidden = false;
    document.documentElement.classList.add("sffc-crm-reddit-modal-open");
    document.body.classList.add("sffc-crm-reddit-modal-open");
  }

  function closeGapModal() {
    var modal = document.querySelector("[data-reddit-gap-modal]");
    if (!modal) {
      return;
    }

    clearGapLoadingTimers();
    modal.hidden = true;
    document.documentElement.classList.remove("sffc-crm-reddit-modal-open");
    document.body.classList.remove("sffc-crm-reddit-modal-open");
    restoreGapAnalyzerShell();
  }

  function returnFromGapModalToApply() {
    var modal = document.querySelector("[data-reddit-gap-modal]");
    var returnShell = modal ? modal.__returnShell : null;
    var returnToApply = modal ? !!modal.__returnToApply : false;

    closeGapModal();

    if (returnShell && returnToApply) {
      openSingleApplyModal(returnShell);
    }
  }

  function openGapModal(trigger) {
    if (
      !redditConfig.ajaxUrl ||
      !redditConfig.gapPayloadNonce ||
      typeof window.fetch !== "function"
    ) {
      return false;
    }

    var shell = document.getElementById("sffc-gap-analyzer-shell");
    var modal = document.querySelector("[data-reddit-gap-modal]");
    var body = document.querySelector("[data-reddit-gap-body]");
    var jobsPostId = trigger.getAttribute("data-jobs-post-id") || "";
    var crmPostId = trigger.getAttribute("data-crm-post-id") || "";
    var draftCvText = getDraftCvTextFromTrigger(trigger);
    var returnShell = trigger.closest(".sffc-crm-reddit-shell");
    var returnToApply = !!trigger.closest("[data-single-apply-modal]");

    if (!shell || !modal || !body || (!jobsPostId && !crmPostId)) {
      return false;
    }

    modal.__returnShell = returnShell || null;
    modal.__returnToApply = returnToApply;
    restoreGapAnalyzerShell();
    startGapLoadingState();

    modal.hidden = false;
    document.documentElement.classList.add("sffc-crm-reddit-modal-open");
    document.body.classList.add("sffc-crm-reddit-modal-open");

    var dialog = modal.querySelector(".sffc-crm-reddit-gap-dialog");
    if (dialog) {
      dialog.focus();
    }

    var requestBody = new window.FormData();
    requestBody.append("action", "sffc_crm_reddit_gap_payload");
    requestBody.append("nonce", redditConfig.gapPayloadNonce);
    requestBody.append("jobs_post_id", jobsPostId);
    requestBody.append("crm_post_id", crmPostId);

    window
      .fetch(redditConfig.ajaxUrl, {
        method: "POST",
        credentials: "same-origin",
        body: requestBody,
      })
      .then(function (response) {
        return response.json();
      })
      .then(function (response) {
        if (!response || !response.success || !response.data) {
          throw new Error(
            response && response.data && response.data.message
              ? response.data.message
              : "Unable to load Career Assessment + Tailored Materials."
          );
        }

        if (draftCvText) {
          response.data.cv_text = draftCvText;
          response.data.has_cv = true;
        }

        renderGapAnalyzer(shell, response.data);
      })
      .catch(function (error) {
        showGapAnalyzerError(
          error && error.message
            ? error.message
            : "Unable to load Career Assessment + Tailored Materials."
        );
      });

    return true;
  }

  function setApplyFeedback(form, message, isError) {
    var feedback = form.querySelector("[data-reddit-apply-feedback]");
    if (!feedback) {
      return;
    }

    feedback.hidden = false;
    feedback.textContent = message || "";
    feedback.classList.toggle("is-error", !!isError);
    feedback.classList.toggle("is-success", !isError);
  }

  function clearApplyFeedback(form) {
    var feedback = form.querySelector("[data-reddit-apply-feedback]");
    if (!feedback) {
      return;
    }

    feedback.hidden = true;
    feedback.textContent = "";
    feedback.classList.remove("is-error", "is-success");
  }

  function submitApplyPreparation(payload, applyWindow, onSuccess, onError) {
    var body = new window.FormData();
    body.append("action", "sffc_crm_reddit_prepare_apply");
    body.append("nonce", redditConfig.applyNonce);

    Object.keys(payload).forEach(function (key) {
      body.append(key, payload[key] || "");
    });

    window
      .fetch(redditConfig.ajaxUrl, {
        method: "POST",
        credentials: "same-origin",
        body: body,
      })
      .then(function (response) {
        return response.json();
      })
      .then(function (response) {
        if (
          !response ||
          !response.success ||
          !response.data ||
          !response.data.redirect
        ) {
          throw new Error(
            response && response.data && response.data.message
              ? response.data.message
              : "We could not prepare your application."
          );
        }

        applyWindow.location = response.data.redirect;
        if (typeof onSuccess === "function") {
          onSuccess(response);
        }
      })
      .catch(function (error) {
        if (applyWindow && !applyWindow.closed) {
          applyWindow.close();
        }
        if (typeof onError === "function") {
          onError(error);
        }
      });
  }

  document.addEventListener("click", function (event) {
    var upgradeCaptureClose = event.target.closest(
      "[data-upgrade-capture-close]"
    );
    if (upgradeCaptureClose) {
      event.preventDefault();
      closeUpgradeCapture(
        upgradeCaptureClose.closest("[data-upgrade-capture-modal]")
      );
      return;
    }

    var upgradeCaptureTrigger =
      event.target.closest("[data-upgrade-capture-open]") ||
      event.target.closest("a[href]");
    if (upgradeCaptureTrigger) {
      var shouldOpenUpgradeCapture = false;
      if (upgradeCaptureTrigger.hasAttribute("data-upgrade-capture-open")) {
        shouldOpenUpgradeCapture = true;
      } else if (
        upgradeCaptureTrigger.tagName === "A" &&
        isMembershipUrl(upgradeCaptureTrigger.getAttribute("href")) &&
        isUpgradeCaptureEligible(upgradeCaptureTrigger)
      ) {
        shouldOpenUpgradeCapture = true;
      }

      if (
        shouldOpenUpgradeCapture &&
        openUpgradeCapture(upgradeCaptureTrigger)
      ) {
        event.preventDefault();
        return;
      }
    }

    var upgradeCaptureLogin = event.target.closest(
      "[data-upgrade-capture-login]"
    );
    if (upgradeCaptureLogin) {
      var loginModal = upgradeCaptureLogin.closest(
        "[data-upgrade-capture-modal]"
      );
      if (loginModal) {
        var loginContext = Object.assign(
          {},
          loginModal.__upgradeCaptureContext || {}
        );
        var loginNameInput = loginModal.querySelector(
          "[data-upgrade-capture-name]"
        );
        var loginEmailInput = loginModal.querySelector(
          "[data-upgrade-capture-email]"
        );
        loginContext.fullName = loginNameInput
          ? String(loginNameInput.value || "").trim()
          : "";
        loginContext.email = loginEmailInput
          ? String(loginEmailInput.value || "").trim()
          : "";
        try {
          if (window.sessionStorage) {
            window.sessionStorage.setItem(
              GUIDED_ENTRY_CONTEXT_KEY,
              JSON.stringify(loginContext)
            );
          }
        } catch (error) {
          // Ignore storage failures and continue to login.
        }
      }
      return;
    }

    var storyButton = event.target.closest("[data-company-story]");
    if (storyButton) {
      var shell = storyButton.closest(".sffc-crm-reddit-shell");
      if (!shell) {
        return;
      }

      openStoryModal(shell, storyButton.getAttribute("data-company-story"));
      return;
    }

    var feedMaterialTrigger = event.target.closest("[data-feed-material-open]");
    if (feedMaterialTrigger) {
      var feedShell = feedMaterialTrigger.closest(".sffc-crm-reddit-shell");
      var feedPanelKey = feedMaterialTrigger.getAttribute("data-job-panel");
      if (feedShell && feedPanelKey) {
        event.preventDefault();
        openJobModal(feedShell, feedPanelKey);
        var feedPanelShell = getJobPanelShell(feedShell, feedPanelKey);
        if (feedPanelShell) {
          openSingleMaterialModal(feedPanelShell, feedMaterialTrigger);
          requestMaterial(feedPanelShell, feedMaterialTrigger);
        }
      }
      return;
    }

    var jobCard = event.target.closest("[data-reddit-card-url]");
    if (jobCard) {
      var clickedAction = event.target.closest(
        ".sffc-crm-reddit-card-actions a, .sffc-crm-reddit-card-actions button, .sffc-crm-reddit-card-actions span.is-disabled"
      );
      var clickedPackLink = event.target.closest("[data-membership-scroll]");
      var clickedFormControl = event.target.closest(
        "button, input, textarea, select, form"
      );
      var cardUrl = jobCard.getAttribute("data-reddit-card-url");

      if (
        !clickedAction &&
        !clickedPackLink &&
        !clickedFormControl &&
        cardUrl
      ) {
        event.preventDefault();
        window.open(cardUrl, "_blank", "noopener");
        return;
      }
    }

    var membershipScrollLink = event.target.closest("[data-membership-scroll]");
    if (membershipScrollLink) {
      var membershipCard = document.getElementById(
        "sffc-crm-reddit-membership-card"
      );
      if (membershipCard) {
        event.preventDefault();
        membershipCard.scrollIntoView({ behavior: "smooth", block: "start" });
      }
      return;
    }

    var loadMoreButton = event.target.closest("[data-reddit-load-more]");
    if (loadMoreButton) {
      event.preventDefault();
      loadMoreFeed(loadMoreButton);
      return;
    }

    var singleApplyOpen = event.target.closest("[data-single-apply-open]");
    if (singleApplyOpen) {
      var applyShell = singleApplyOpen.closest(".sffc-crm-reddit-shell");
      if (applyShell) {
        event.preventDefault();
        openSingleApplyModal(applyShell);
      }
      return;
    }

    var topbarApplyToggle = event.target.closest(
      "[data-topbar-apply-choice-toggle]"
    );
    if (topbarApplyToggle) {
      event.preventDefault();
      event.stopPropagation();
      toggleTopbarApplyChoice(
        topbarApplyToggle.closest("[data-topbar-apply-choice]")
      );
      return;
    }

    var topbarApplyDirect = event.target.closest(
      "[data-topbar-apply-choice-link]"
    );
    if (topbarApplyDirect) {
      event.stopPropagation();
      closeTopbarApplyChoice(
        topbarApplyDirect.closest("[data-topbar-apply-choice]")
      );
      return;
    }

    var materialTrigger = event.target.closest("[data-single-material-open]");
    if (materialTrigger) {
      if (!redditConfig.isLoggedIn) {
        event.preventDefault();
        redirectToMembership();
        return;
      }
      var materialShell = materialTrigger.closest(".sffc-crm-reddit-shell");
      if (!materialShell) {
        var gapModal = document.querySelector("[data-reddit-gap-modal]");
        materialShell =
          gapModal && gapModal.__returnShell ? gapModal.__returnShell : null;
      }
      if (materialShell) {
        event.preventDefault();
        var withinGapModal = !!materialTrigger.closest(
          "[data-reddit-gap-modal]"
        );
        if (withinGapModal) {
          closeGapModal();
        } else {
          closeSingleApplyModal(materialShell);
        }
        openSingleMaterialModal(materialShell, materialTrigger);
        requestMaterial(materialShell, materialTrigger);
      }
      return;
    }

    var materialPackTrigger = event.target.closest("[data-material-pack-open]");
    if (materialPackTrigger) {
      if (!redditConfig.isLoggedIn) {
        event.preventDefault();
        redirectToMembership();
        return;
      }
      var materialPackShell = materialPackTrigger.closest(
        ".sffc-crm-reddit-shell"
      );
      if (materialPackShell) {
        event.preventDefault();
        openMaterialKitModal(materialPackShell, materialPackTrigger);
        requestMaterialPack(materialPackShell, materialPackTrigger);
      }
      return;
    }

    var recommendedReviewCard = event.target.closest(
      ".sffc-match-method-card--recommended"
    );
    if (
      recommendedReviewCard &&
      recommendedReviewCard.closest("[data-single-apply-modal]")
    ) {
      var reviewTrigger = recommendedReviewCard.querySelector(
        "[data-reddit-open-gap]"
      );
      var reviewShell = reviewTrigger
        ? reviewTrigger.closest(".sffc-crm-reddit-shell")
        : null;
      if (reviewTrigger && reviewShell) {
        event.preventDefault();
        closeSingleApplyModal(reviewShell);
        openGapModal(reviewTrigger);
      }
      return;
    }

    var materialKitTrigger = event.target.closest("[data-material-kit-open]");
    if (materialKitTrigger) {
      if (!redditConfig.isLoggedIn) {
        event.preventDefault();
        redirectToMembership();
        return;
      }
      var materialKitShell = materialKitTrigger.closest(
        ".sffc-crm-reddit-shell"
      );
      if (materialKitShell) {
        event.preventDefault();
        event.stopPropagation();
        closeTopbarApplyChoice(
          materialKitTrigger.closest("[data-topbar-apply-choice]")
        );
        closeSingleApplyModal(materialKitShell);
        openMaterialKitModal(materialKitShell, materialKitTrigger);
        if (
          materialKitTrigger.getAttribute("data-material-access") === "true"
        ) {
          requestMaterialPack(materialKitShell, materialKitTrigger);
        } else {
          var previewModal = materialKitShell.querySelector(
            "[data-single-material-modal]"
          );
          startMaterialKitAnimation(previewModal);
        }
      }
      return;
    }

    var materialPackRefresh = event.target.closest("[data-kit-generate-pack]");
    if (materialPackRefresh) {
      if (!redditConfig.isLoggedIn) {
        event.preventDefault();
        redirectToMembership();
        return;
      }
      var refreshModal = materialPackRefresh.closest(
        "[data-single-material-modal]"
      );
      if (refreshModal) {
        event.preventDefault();
        refreshMaterialPack(refreshModal);
      }
      return;
    }

    var gapNetworkingTrigger = event.target.closest(
      "[data-gap-open-networking]"
    );
    if (gapNetworkingTrigger) {
      var gapModal = document.querySelector("[data-reddit-gap-modal]");
      var returnShell =
        gapModal && gapModal.__returnShell ? gapModal.__returnShell : null;
      if (returnShell) {
        event.preventDefault();
        closeGapModal();
        openSingleNetworkingModal(returnShell);
        var returnNetworkingModal = returnShell.querySelector(
          "[data-single-networking-modal]"
        );
        if (
          returnNetworkingModal &&
          returnNetworkingModal.getAttribute("data-networking-loaded") !==
            "true"
        ) {
          requestNetworkingStrategy(
            returnShell,
            returnNetworkingModal.querySelector("[data-jobs-post-id]"),
            ""
          );
        }
      }
      return;
    }

    var materialClose = event.target.closest("[data-single-material-close]");
    if (materialClose) {
      var materialCloseShell = materialClose.closest(".sffc-crm-reddit-shell");
      if (materialCloseShell) {
        event.preventDefault();
        closeSingleMaterialModal(materialCloseShell);
      }
      return;
    }

    var materialBack = event.target.closest("[data-single-material-back]");
    if (materialBack) {
      var materialBackShell = materialBack.closest(".sffc-crm-reddit-shell");
      if (materialBackShell) {
        event.preventDefault();
        closeSingleMaterialModal(materialBackShell);
        openSingleApplyModal(materialBackShell);
      }
      return;
    }

    var materialCopy = event.target.closest("[data-material-copy]");
    if (materialCopy) {
      event.preventDefault();
      copyMaterial(materialCopy);
      return;
    }

    var materialDownload = event.target.closest("[data-material-download]");
    if (materialDownload) {
      event.preventDefault();
      downloadMaterial(materialDownload);
      return;
    }

    var kitDownload = event.target.closest("[data-kit-download]");
    if (kitDownload) {
      if (!redditConfig.isLoggedIn) {
        event.preventDefault();
        redirectToMembership();
        return;
      }
      var kitModal = kitDownload.closest("[data-single-material-modal]");
      var kitShell = kitDownload.closest(".sffc-crm-reddit-shell");
      var kitIndex = parseInt(
        kitDownload.getAttribute("data-kit-index") || "-1",
        10
      );
      var resource =
        kitModal && kitModal.__kitResources && kitIndex >= 0
          ? kitModal.__kitResources[kitIndex]
          : null;

      if (kitModal && resource) {
        event.preventDefault();
        if (kitModal.__kitHasAccess) {
          openKitResource(kitShell, kitModal, resource);
        } else {
          revealMaterialKitGate(kitModal, resource.label || "");
        }
      }
      return;
    }

    var kitUnlock = event.target.closest("[data-kit-unlock]");
    if (kitUnlock) {
      var unlockModal = kitUnlock.closest("[data-single-material-modal]");
      if (unlockModal) {
        event.preventDefault();
        var emailField = unlockModal.querySelector("[data-kit-email]");
        var email =
          emailField && typeof emailField.value === "string"
            ? emailField.value.trim()
            : "";
        loadMaterialMembershipForm(unlockModal, email);
      }
      return;
    }

    var networkingTrigger = event.target.closest(
      "[data-single-networking-open]"
    );
    if (networkingTrigger) {
      var networkingShell = networkingTrigger.closest(".sffc-crm-reddit-shell");
      if (networkingShell) {
        event.preventDefault();
        closeSingleApplyModal(networkingShell);
        openSingleNetworkingModal(networkingShell);
        var networkingModal = networkingShell.querySelector(
          "[data-single-networking-modal]"
        );
        if (
          networkingModal &&
          networkingModal.getAttribute("data-networking-loaded") !== "true"
        ) {
          requestNetworkingStrategy(
            networkingShell,
            networkingModal.querySelector("[data-jobs-post-id]"),
            ""
          );
        }
      }
      return;
    }

    var networkingNav = event.target.closest("[data-networking-target]");
    if (networkingNav) {
      var networkingModal = networkingNav.closest(
        "[data-single-networking-modal]"
      );
      if (networkingModal) {
        event.preventDefault();
        if (networkingNav.classList.contains("is-locked")) {
          redirectToMembership();
          return;
        }
        activateNetworkingPanel(
          networkingModal,
          networkingNav.getAttribute("data-networking-target")
        );
      }
      return;
    }

    var gapTrigger = event.target.closest("[data-reddit-open-gap]");
    if (gapTrigger) {
      var applyModalShell = gapTrigger.closest(".sffc-crm-reddit-shell");
      var insideApplyModal = !!gapTrigger.closest("[data-single-apply-modal]");
      if (insideApplyModal && applyModalShell) {
        closeSingleApplyModal(applyModalShell);
      }

      var opened = openGapModal(gapTrigger);
      if (opened) {
        event.preventDefault();
      }
      return;
    }

    var loggedApplyLink = event.target.closest("[data-reddit-logged-apply]");
    if (loggedApplyLink) {
      if (
        !redditConfig.ajaxUrl ||
        !redditConfig.applyNonce ||
        typeof window.fetch !== "function"
      ) {
        return;
      }

      event.preventDefault();

      var loggedApplyWindow = window.open("", "_blank", "noopener");
      if (!loggedApplyWindow) {
        return;
      }

      loggedApplyWindow.document.write(
        "<p style='font-family:Arial,sans-serif;padding:20px'>Preparing your application...</p>"
      );

      submitApplyPreparation(
        {
          apply_url:
            loggedApplyLink.getAttribute("data-apply-url") ||
            loggedApplyLink.href ||
            "",
          role_title: loggedApplyLink.getAttribute("data-role-title") || "",
          company_name: loggedApplyLink.getAttribute("data-company-name") || "",
          location: loggedApplyLink.getAttribute("data-location") || "",
          senna_url: loggedApplyLink.getAttribute("data-senna-url") || "",
          similar_roles_url:
            loggedApplyLink.getAttribute("data-similar-roles-url") || "",
        },
        loggedApplyWindow
      );

      return;
    }

    var pinButton = event.target.closest("[data-group-pin]");
    if (pinButton) {
      var pinItem = pinButton.closest("[data-reddit-group-item]");
      var pinShell = pinButton.closest(".sffc-crm-reddit-shell");
      if (pinItem && pinShell) {
        pinItem.classList.toggle("is-pinned");
        pinButton.setAttribute(
          "aria-pressed",
          pinItem.classList.contains("is-pinned") ? "true" : "false"
        );
        syncGroupPreferences(pinShell);
      }
      return;
    }

    var profileToggle = event.target.closest("[data-reddit-profile-toggle]");
    if (profileToggle) {
      event.preventDefault();
      toggleProfileMenu(profileToggle.closest(".sffc-crm-reddit-shell"));
      return;
    }

    var accountTarget = event.target.closest("[data-reddit-account-target]");
    if (accountTarget) {
      event.preventDefault();
      openAccountPanel(
        accountTarget.closest(".sffc-crm-reddit-shell"),
        accountTarget.getAttribute("data-reddit-account-target")
      );
      return;
    }

    var accountClose = event.target.closest("[data-reddit-account-close]");
    if (accountClose) {
      event.preventDefault();
      closeAccountShell(accountClose.closest(".sffc-crm-reddit-shell"));
      return;
    }

    var activeResumeButton = event.target.closest("[data-reddit-set-resume]");
    if (activeResumeButton) {
      event.preventDefault();
      saveActiveResume(
        activeResumeButton.closest(".sffc-crm-reddit-shell"),
        activeResumeButton.getAttribute("data-reddit-set-resume"),
        "Active resume updated."
      ).catch(function (error) {
        var shell = activeResumeButton.closest(".sffc-crm-reddit-shell");
        var panel = shell
          ? shell.querySelector('[data-reddit-account-panel="profile"]') ||
            shell.querySelector("[data-dashboard-review-shell]")
          : null;
        setProfileFeedback(
          panel,
          error && error.message
            ? error.message
            : "Unable to update your active resume.",
          true
        );
      });
      return;
    }

    var outreachReset = event.target.closest("[data-dashboard-outreach-reset]");
    if (outreachReset) {
      event.preventDefault();
      resetDashboardOutreachForm(outreachReset);
      return;
    }

    var outreachListRow = event.target.closest(
      "[data-dashboard-outreach-list-row]"
    );
    if (outreachListRow) {
      event.preventDefault();
      loadDashboardOutreachList(
        outreachListRow.closest("[data-dashboard-outreach-shell]"),
        Number(outreachListRow.getAttribute("data-list-id") || 0)
      );
      return;
    }

    var outreachMemberRow = event.target.closest(
      "[data-dashboard-outreach-member-row]"
    );
    if (outreachMemberRow) {
      event.preventDefault();
      applyDashboardOutreachMember(
        outreachMemberRow.closest("[data-dashboard-outreach-shell]"),
        Number(outreachMemberRow.getAttribute("data-member-index") || 0)
      );
      return;
    }

    var outreachPrev = event.target.closest("[data-dashboard-outreach-prev]");
    if (outreachPrev) {
      var outreachPrevShell = outreachPrev.closest(
        "[data-dashboard-outreach-shell]"
      );
      var outreachPrevState = getDashboardOutreachState(outreachPrevShell);
      if (outreachPrevState && outreachPrevState.activeMemberIndex > 0) {
        event.preventDefault();
        applyDashboardOutreachMember(
          outreachPrevShell,
          outreachPrevState.activeMemberIndex - 1
        );
      }
      return;
    }

    var outreachNext = event.target.closest("[data-dashboard-outreach-next]");
    if (outreachNext) {
      var outreachNextShell = outreachNext.closest(
        "[data-dashboard-outreach-shell]"
      );
      var outreachNextState = getDashboardOutreachState(outreachNextShell);
      if (
        outreachNextState &&
        outreachNextState.activeMemberIndex <
          outreachNextState.members.length - 1
      ) {
        event.preventDefault();
        applyDashboardOutreachMember(
          outreachNextShell,
          outreachNextState.activeMemberIndex + 1
        );
      }
      return;
    }

    var outreachMarkSent = event.target.closest(
      "[data-dashboard-outreach-mark-sent]"
    );
    if (outreachMarkSent) {
      event.preventDefault();
      updateDashboardOutreachMemberStatus(
        outreachMarkSent.closest("[data-dashboard-outreach-shell]"),
        "sent"
      );
      return;
    }

    var outreachSkip = event.target.closest("[data-dashboard-outreach-skip]");
    if (outreachSkip) {
      event.preventDefault();
      updateDashboardOutreachMemberStatus(
        outreachSkip.closest("[data-dashboard-outreach-shell]"),
        "skipped"
      );
      return;
    }

    var closeButton = event.target.closest("[data-story-close]");
    if (closeButton) {
      var closeShell = closeButton.closest(".sffc-crm-reddit-shell");
      if (closeShell) {
        closeStoryModal(closeShell);
      }
      return;
    }

    var closeJobButton = event.target.closest("[data-job-close]");
    if (closeJobButton) {
      var closeJobShell = closeJobButton.closest(".sffc-crm-reddit-shell");
      if (closeJobShell) {
        closeJobModal(closeJobShell);
      }
      return;
    }

    var closeGapButton = event.target.closest("[data-gap-close]");
    if (closeGapButton) {
      event.preventDefault();
      closeGapModal();
      return;
    }

    var gapBackButton = event.target.closest("[data-gap-back]");
    if (gapBackButton) {
      event.preventDefault();
      returnFromGapModalToApply();
      return;
    }

    var closeSingleApplyButton = event.target.closest(
      "[data-single-apply-close]"
    );
    if (closeSingleApplyButton) {
      var closeApplyShell = closeSingleApplyButton.closest(
        ".sffc-crm-reddit-shell"
      );
      if (closeApplyShell) {
        event.preventDefault();
        closeSingleApplyModal(closeApplyShell);
      }
      return;
    }

    var closeSingleNetworkingButton = event.target.closest(
      "[data-single-networking-close]"
    );
    if (closeSingleNetworkingButton) {
      var closeNetworkingShell = closeSingleNetworkingButton.closest(
        ".sffc-crm-reddit-shell"
      );
      if (closeNetworkingShell) {
        event.preventDefault();
        closeSingleNetworkingModal(closeNetworkingShell);
      }
      return;
    }

    var networkingBackButton = event.target.closest(
      "[data-single-networking-back]"
    );
    if (networkingBackButton) {
      var backNetworkingShell = networkingBackButton.closest(
        ".sffc-crm-reddit-shell"
      );
      if (backNetworkingShell) {
        event.preventDefault();
        closeSingleNetworkingModal(backNetworkingShell);
        openSingleApplyModal(backNetworkingShell);
      }
      return;
    }

    var suggestionOption = event.target.closest(
      ".sffc-crm-reddit-search-option"
    );
    if (suggestionOption) {
      var searchField = suggestionOption.closest("[data-reddit-search-field]");
      if (searchField) {
        chooseAutocompleteOption(searchField, suggestionOption);
      }
      return;
    }

    var applySummary = event.target.closest(
      ".sffc-crm-reddit-single-apply-gate > summary"
    );
    if (applySummary) {
      window.setTimeout(function () {
        var applyGate = applySummary.parentNode;
        if (!applyGate || !applyGate.open) {
          return;
        }

        var nameInput = applyGate.querySelector('input[name="full_name"]');
        if (nameInput) {
          nameInput.focus();
        }
      }, 0);
    }

    document
      .querySelectorAll("[data-reddit-search-field]")
      .forEach(function (field) {
        if (!field.contains(event.target)) {
          closeAutocomplete(field);
        }
      });

    if (!event.target.closest("[data-topbar-apply-choice]")) {
      closeAllTopbarApplyChoices();
    }

    var clickedInsideProfile = false;
    document
      .querySelectorAll(".sffc-crm-reddit-shell [data-reddit-profile]")
      .forEach(function (profile) {
        if (profile.contains(event.target)) {
          clickedInsideProfile = true;
        }
      });

    if (!clickedInsideProfile) {
      closeProfileMenus();
    }
  });

  document.addEventListener("submit", function (event) {
    var upgradeCaptureForm = event.target.closest(
      "[data-upgrade-capture-form]"
    );
    if (!upgradeCaptureForm) {
      return;
    }

    event.preventDefault();
    submitUpgradeCapture(upgradeCaptureForm);
  });

  document.addEventListener("input", function (event) {
    var emailInput = event.target.closest("[data-upgrade-capture-email]");
    if (!emailInput) {
      return;
    }

    var emailModal = emailInput.closest("[data-upgrade-capture-modal]");
    if (!emailModal) {
      return;
    }

    window.clearTimeout(upgradeCaptureEmailTimer);
    upgradeCaptureEmailTimer = window.setTimeout(function () {
      checkUpgradeCaptureEmail(emailModal, emailInput.value || "");
    }, 280);
  });

  document.addEventListener("keydown", function (event) {
    if (event.key !== "Escape") {
      return;
    }

    var activeUpgradeCapture = document.querySelector(
      "[data-upgrade-capture-modal]:not([hidden])"
    );
    if (activeUpgradeCapture) {
      closeUpgradeCapture(activeUpgradeCapture);
    }
  });

  document.addEventListener("submit", function (event) {
    var pastedCvForm = event.target.closest("[data-reddit-cv-paste-form]");
    if (pastedCvForm) {
      event.preventDefault();
      submitPastedCv(pastedCvForm);
      return;
    }

    var resumeUploadForm = event.target.closest("[data-reddit-resume-upload]");
    if (resumeUploadForm) {
      event.preventDefault();
      submitResumeUpload(resumeUploadForm);
      return;
    }

    var applyForm = event.target.closest("[data-reddit-apply-form]");
    if (applyForm) {
      event.preventDefault();

      if (
        !redditConfig.ajaxUrl ||
        !redditConfig.applyNonce ||
        typeof window.fetch !== "function"
      ) {
        setApplyFeedback(
          applyForm,
          "Application form is unavailable right now.",
          true
        );
        return;
      }

      var fullNameInput = applyForm.querySelector('input[name="full_name"]');
      var emailInput = applyForm.querySelector('input[name="email"]');
      var submitButton = applyForm.querySelector('button[type="submit"]');
      var applyUrl = applyForm.getAttribute("data-apply-url") || "";
      var fullName = fullNameInput ? fullNameInput.value.trim() : "";
      var email = emailInput ? emailInput.value.trim() : "";
      var originalLabel = submitButton ? submitButton.textContent : "";
      var applyWindow = window.open("", "_blank", "noopener");

      clearApplyFeedback(applyForm);

      if (!fullName) {
        if (applyWindow) {
          applyWindow.close();
        }
        setApplyFeedback(applyForm, "Enter your full name to continue.", true);
        if (fullNameInput) {
          fullNameInput.focus();
        }
        return;
      }

      if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        if (applyWindow) {
          applyWindow.close();
        }
        setApplyFeedback(
          applyForm,
          "Enter a valid email address to continue.",
          true
        );
        if (emailInput) {
          emailInput.focus();
        }
        return;
      }

      if (!applyWindow) {
        setApplyFeedback(
          applyForm,
          "Allow pop-ups so we can open the application in a new window.",
          true
        );
        return;
      }

      applyWindow.document.write(
        "<p style='font-family:Arial,sans-serif;padding:20px'>Preparing your application...</p>"
      );

      if (submitButton) {
        submitButton.disabled = true;
        submitButton.textContent = "Preparing...";
      }

      submitApplyPreparation(
        {
          full_name: fullName,
          email: email,
          apply_url: applyUrl,
          role_title: applyForm.getAttribute("data-role-title") || "",
          company_name: applyForm.getAttribute("data-company-name") || "",
          location: applyForm.getAttribute("data-location") || "",
          senna_url: applyForm.getAttribute("data-senna-url") || "",
          similar_roles_url:
            applyForm.getAttribute("data-similar-roles-url") || "",
        },
        applyWindow,
        function (response) {
          setApplyFeedback(
            applyForm,
            response.data.message || "Opening application now.",
            false
          );

          var applyGate = applyForm.closest(
            ".sffc-crm-reddit-single-apply-gate"
          );
          if (applyGate) {
            applyGate.open = false;
          }

          if (submitButton) {
            submitButton.disabled = false;
            submitButton.textContent = originalLabel;
          }
        },
        function (error) {
          setApplyFeedback(
            applyForm,
            error && error.message
              ? error.message
              : "We could not prepare your application.",
            true
          );

          if (submitButton) {
            submitButton.disabled = false;
            submitButton.textContent = originalLabel;
          }
        }
      );

      return;
    }

    var networkingForm = event.target.closest("[data-networking-form]");
    if (networkingForm) {
      event.preventDefault();
      var networkingShell = networkingForm.closest(".sffc-crm-reddit-shell");
      var networkingInput = networkingForm.querySelector(
        'input[name="networking_context"]'
      );
      var networkingSubmit = networkingForm.querySelector(
        "[data-jobs-post-id]"
      );
      requestNetworkingStrategy(
        networkingShell,
        networkingSubmit,
        networkingInput ? networkingInput.value.trim() : ""
      );
      return;
    }

    var outreachForm = event.target.closest("[data-dashboard-outreach-form]");
    if (outreachForm) {
      event.preventDefault();
      submitDashboardOutreachForm(outreachForm);
      return;
    }

    var searchForm = event.target.closest(".sffc-crm-reddit-search");
    if (!searchForm) {
      return;
    }

    var field = searchForm.querySelector("[data-reddit-search-field]");
    if (field) {
      closeAutocomplete(field);
    }
  });

  document.addEventListener("change", function (event) {
    var outreachTarget = event.target.closest(
      "select[data-dashboard-outreach-target]"
    );
    if (outreachTarget) {
      updateDashboardOutreachTargetCopy(
        outreachTarget.closest("[data-dashboard-outreach-shell]")
      );
      return;
    }

    var outreachContext = event.target.closest(
      '[data-dashboard-outreach-form] select[name="outreach_context"]'
    );
    if (outreachContext) {
      updateDashboardOutreachRoleVisibility(
        outreachContext.closest("[data-dashboard-outreach-shell]")
      );
      return;
    }

    var outreachRole = event.target.closest(
      '[data-dashboard-outreach-form] select[name="jobs_post_id"]'
    );
    if (outreachRole) {
      syncDashboardOutreachRoleDefaults(
        outreachRole.closest("[data-dashboard-outreach-shell]")
      );
      return;
    }

    var fileInput = event.target.closest(
      '[data-reddit-resume-upload] input[type="file"][name="cv_file"]'
    );
    if (!fileInput) {
      return;
    }

    var form = fileInput.closest("[data-reddit-resume-upload]");
    if (!form) {
      return;
    }

    updateDashboardReviewDropzone(form);

    if (
      form.closest("[data-dashboard-review-shell]") &&
      fileInput.files &&
      fileInput.files.length
    ) {
      submitResumeUpload(form);
    }
  });

  ["dragenter", "dragover"].forEach(function (eventName) {
    document.addEventListener(eventName, function (event) {
      var dropzone = event.target.closest("[data-dashboard-review-dropzone]");
      if (!dropzone) {
        return;
      }

      event.preventDefault();
      dropzone.classList.add("is-dragover");
    });
  });

  ["dragleave", "dragend", "drop"].forEach(function (eventName) {
    document.addEventListener(eventName, function (event) {
      var dropzone = event.target.closest("[data-dashboard-review-dropzone]");
      if (!dropzone) {
        return;
      }

      if (eventName !== "drop") {
        if (event.relatedTarget && dropzone.contains(event.relatedTarget)) {
          return;
        }
      }

      event.preventDefault();
      dropzone.classList.remove("is-dragover");

      if (eventName !== "drop") {
        return;
      }

      var form = dropzone.closest("[data-reddit-resume-upload]");
      var fileInput = form
        ? form.querySelector('input[type="file"][name="cv_file"]')
        : null;
      var files =
        event.dataTransfer && event.dataTransfer.files
          ? event.dataTransfer.files
          : null;
      if (!form || !fileInput || !files || !files.length) {
        return;
      }

      try {
        fileInput.files = files;
      } catch (error) {
        return;
      }

      updateDashboardReviewDropzone(form);
      submitResumeUpload(form);
    });
  });

  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") {
      closeAllTopbarApplyChoices();
    }

    if (event.key !== "Escape") {
      return;
    }

    document
      .querySelectorAll(".sffc-crm-reddit-shell [data-reddit-modal]")
      .forEach(function (modal) {
        if (!modal.hidden) {
          var shell = modal.closest(".sffc-crm-reddit-shell");
          if (shell) {
            closeStoryModal(shell);
          }
        }
      });

    document
      .querySelectorAll(".sffc-crm-reddit-shell [data-reddit-job-modal]")
      .forEach(function (modal) {
        if (!modal.hidden) {
          var shell = modal.closest(".sffc-crm-reddit-shell");
          if (shell) {
            closeJobModal(shell);
          }
        }
      });

    var gapModal = document.querySelector("[data-reddit-gap-modal]");
    if (gapModal && !gapModal.hidden) {
      closeGapModal();
    }

    document
      .querySelectorAll(".sffc-crm-reddit-shell [data-single-apply-modal]")
      .forEach(function (modal) {
        if (!modal.hidden) {
          var shell = modal.closest(".sffc-crm-reddit-shell");
          if (shell) {
            closeSingleApplyModal(shell);
          }
        }
      });

    document
      .querySelectorAll(".sffc-crm-reddit-shell [data-single-networking-modal]")
      .forEach(function (modal) {
        if (!modal.hidden) {
          var shell = modal.closest(".sffc-crm-reddit-shell");
          if (shell) {
            closeSingleNetworkingModal(shell);
          }
        }
      });

    document
      .querySelectorAll(".sffc-crm-reddit-shell [data-single-material-modal]")
      .forEach(function (modal) {
        if (!modal.hidden) {
          var shell = modal.closest(".sffc-crm-reddit-shell");
          if (shell) {
            closeSingleMaterialModal(shell);
          }
        }
      });

    document
      .querySelectorAll("[data-reddit-search-field]")
      .forEach(function (field) {
        closeAutocomplete(field);
      });

    closeProfileMenus();

    document
      .querySelectorAll(".sffc-crm-reddit-shell")
      .forEach(function (shell) {
        closeAccountShell(shell);
      });
  });

  window.addEventListener("sffc:outreach-queue-focus", function (event) {
    var detail = event && event.detail ? event.detail : {};
    var listId = Number(detail.listId || 0);
    var memberId = Number(detail.memberId || 0);

    if (!listId) {
      return;
    }

    document
      .querySelectorAll("[data-dashboard-outreach-shell]")
      .forEach(function (shell) {
        var queue = shell.querySelector("[data-dashboard-outreach-queue]");
        if (queue && queue.tagName === "DETAILS") {
          queue.open = true;
        }
        loadDashboardOutreachLists(shell, listId, memberId);
      });
  });

  initGroupNav();
  initAutocomplete();
  initDashboardApp();
  initDashboardMatchTracking();
  initDashboardProfile();
})();
