(function () {
  "use strict";

  var config = window.sffcCrmCvMatchStudio || {};
  var CV_GAP_PREFILL_KEY = "sffcGapAnalyzerPrefill";
  var PREFERRED_INDUSTRY_KEY = "sffcCvMatchPreferredIndustry";

  function $(root, selector) {
    if (!root || typeof root.querySelector !== "function") {
      return null;
    }
    return root.querySelector(selector);
  }

  function $all(root, selector) {
    if (!root || typeof root.querySelectorAll !== "function") {
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
      .replace(/'/g, "&#039;");
  }

  function tokenize(value) {
    return String(value || "")
      .replace(/\s+/g, " ")
      .trim()
      .split(" ")
      .filter(Boolean);
  }

  function initials(value) {
    var tokens = tokenize(value);
    if (!tokens.length) {
      return "S";
    }
    if (tokens.length === 1) {
      return tokens[0].slice(0, 2).toUpperCase();
    }
    return (tokens[0].charAt(0) + tokens[1].charAt(0)).toUpperCase();
  }

  function hashText(value) {
    var input = String(value || "");
    var hash = 0;
    var index;

    for (index = 0; index < input.length; index += 1) {
      hash = (hash << 5) - hash + input.charCodeAt(index);
      hash |= 0;
    }

    return String(Math.abs(hash));
  }

  function relativeTime(value) {
    if (!value) {
      return "";
    }

    var date = new Date(value.replace(" ", "T"));
    if (Number.isNaN(date.getTime())) {
      return value;
    }

    var diffSeconds = Math.max(
      1,
      Math.floor((Date.now() - date.getTime()) / 1000)
    );
    var units = [
      { limit: 60, size: 1, label: "second" },
      { limit: 3600, size: 60, label: "minute" },
      { limit: 86400, size: 3600, label: "hour" },
      { limit: 604800, size: 86400, label: "day" },
      { limit: 2629800, size: 604800, label: "week" },
      { limit: 31557600, size: 2629800, label: "month" },
      { limit: Infinity, size: 31557600, label: "year" },
    ];

    for (var i = 0; i < units.length; i += 1) {
      if (diffSeconds < units[i].limit) {
        var amount = Math.max(1, Math.floor(diffSeconds / units[i].size));
        return (
          amount + " " + units[i].label + (amount === 1 ? "" : "s") + " ago"
        );
      }
    }

    return value;
  }

  function hasPremiumRecruiterAccess() {
    return !!config.loggedIn && !!config.hasPremiumAccess;
  }

  function redirectToMembership() {
    if (hasPremiumRecruiterAccess()) {
      return false;
    }
    window.location.href = config.membershipUrl || "/memberships/";
    return true;
  }

  function maskEmail(value) {
    var email = String(value || "").trim();
    var parts;
    var local;
    var domainParts;
    var domainName;
    var tld;

    if (!email || email.indexOf("@") === -1) {
      return "Contact available";
    }

    parts = email.split("@");
    local = parts[0] || "";
    domainParts = (parts[1] || "").split(".");
    domainName = domainParts.shift() || "";
    tld = domainParts.length ? "." + domainParts.join(".") : "";

    return (
      local.charAt(0).toUpperCase() +
      "****" +
      (local.length > 1 ? local.charAt(local.length - 1).toLowerCase() : "") +
      "@" +
      (domainName ? domainName.charAt(0).toLowerCase() + "***" : "s***") +
      tld
    );
  }

  function maskPhone(value) {
    var phone = String(value || "").replace(/\D/g, "");

    if (!phone) {
      return "Call available";
    }

    if (phone.length <= 3) {
      return phone.charAt(0) + "****";
    }

    return phone.slice(0, 2) + "******" + phone.slice(-1);
  }

  function parseAjaxJson(response) {
    return response.text().then(function (text) {
      var payload = null;
      var trimmed = String(text || "").trim();

      try {
        payload = JSON.parse(text);
      } catch (error) {
        if (trimmed.slice(0, 1) === "<") {
          throw new Error(
            "The server returned HTML instead of JSON" +
              (response && response.status ? " (" + response.status + ")" : "") +
              "."
          );
        }
        throw new Error("The server returned an invalid JSON response.");
      }

      return payload;
    });
  }

  function loadExternalScript(url, globalName) {
    var normalizedUrl = String(url || "").trim();
    var pendingKey;
    var existing;

    if (globalName && typeof window[globalName] !== "undefined") {
      return Promise.resolve(window[globalName]);
    }

    if (!normalizedUrl) {
      return Promise.reject(new Error("Script URL is unavailable."));
    }

    pendingKey = "__cvMatchScriptPromise__" + normalizedUrl;
    if (window[pendingKey]) {
      return window[pendingKey];
    }

    existing = document.querySelector('script[src="' + normalizedUrl + '"]');
    if (existing && globalName && typeof window[globalName] !== "undefined") {
      return Promise.resolve(window[globalName]);
    }

    window[pendingKey] = new Promise(function (resolve, reject) {
      var script = existing || document.createElement("script");

      script.async = true;
      script.src = normalizedUrl;

      script.onload = function () {
        if (globalName && typeof window[globalName] === "undefined") {
          reject(new Error("Loaded script did not expose " + globalName + "."));
          return;
        }
        resolve(globalName ? window[globalName] : true);
      };

      script.onerror = function () {
        reject(new Error("Unable to load " + normalizedUrl + "."));
      };

      if (!existing) {
        document.head.appendChild(script);
      }
    });

    return window[pendingKey];
  }

  function setFeedback(root, message, isError) {
    var node = $(root, "[data-cv-match-feedback]");
    if (!node) {
      return;
    }

    if (!message) {
      node.hidden = true;
      node.textContent = "";
      node.classList.remove("is-error", "is-success");
      return;
    }

    node.hidden = false;
    node.textContent = message;
    node.classList.toggle("is-error", !!isError);
    node.classList.toggle("is-success", !isError);
  }

  function setState(root, stateName) {
    var mainPane = root.querySelector(
      root.getAttribute("data-component") === "crm-cv-match-job"
        ? ".sffc-cv-match-job__main"
        : ".sffc-cv-match-studio__main"
    );
    var mainStatePrefix =
      root.getAttribute("data-component") === "crm-cv-match-job"
        ? "sffc-cv-match-job__main--"
        : "sffc-cv-match-studio__main--";

    root.setAttribute("data-cv-match-view", stateName);
    if (root && typeof root._cvMatchCloseMainPanels === "function") {
      root._cvMatchCloseMainPanels();
    }
    if (root && typeof root._cvMatchSetMobileCommandOpen === "function") {
      root._cvMatchSetMobileCommandOpen(false);
    }

    if (mainPane) {
      Array.prototype.slice
        .call(mainPane.classList)
        .forEach(function (className) {
          if (className.indexOf(mainStatePrefix) === 0) {
            mainPane.classList.remove(className);
          }
        });
      mainPane.classList.add(mainStatePrefix + stateName + "-view");
      mainPane.setAttribute("data-cv-match-main-state", stateName);
    }

    $all(root, "[data-cv-match-state]").forEach(function (panel) {
      var active = panel.getAttribute("data-cv-match-state") === stateName;
      panel.hidden = !active;
      panel.classList.toggle("is-active", active);
      panel.classList.remove("is-entering");
      if (active) {
        window.requestAnimationFrame(function () {
          panel.classList.add("is-entering");
        });
      }
    });
    $all(root, "[data-cv-match-nav-trigger]").forEach(function (trigger) {
      var active =
        (trigger.getAttribute("data-cv-match-nav-trigger") || "") === stateName;
      trigger.classList.toggle("is-active", active);
      if (trigger.tagName === "BUTTON") {
        trigger.setAttribute("aria-pressed", active ? "true" : "false");
      }
    });
  }

  function startHeroTyping(root) {
    var heading = $(root, "[data-cv-match-hero-typing]");
    if (!heading || heading.dataset.typingReady === "1") {
      return;
    }

    var fullText = heading.textContent || "";
    if (!fullText) {
      return;
    }

    heading.dataset.typingReady = "1";
    heading.dataset.fullText = fullText;
    heading.textContent = "";

    var index = 0;
    var delay = 38;

    function tick() {
      index += 1;
      heading.textContent = fullText.slice(0, index);

      if (index >= fullText.length) {
        heading.classList.add("is-complete");
        return;
      }

      var nextDelay = fullText.charAt(index - 1) === "," ? 160 : delay;
      window.setTimeout(tick, nextDelay);
    }

    window.setTimeout(tick, 260);
  }

  function initLandingPreview(root) {
    var preview = $(root, "[data-cv-match-landing-preview]");
    var viewport;
    var track;
    var slides;
    var dots;
    var prevButton;
    var nextButton;
    var statusNode;
    var activeIndex = 0;
    var autoplayTimer = null;
    var scoreFrame = 0;
    var dragStartX = 0;
    var dragDeltaX = 0;
    var pointerActive = false;

    if (!preview || preview.dataset.previewReady === "1") {
      return;
    }

    viewport = $(preview, "[data-cv-match-landing-preview-viewport]");
    track = $(preview, "[data-cv-match-landing-preview-track]");
    slides = $all(preview, "[data-cv-match-landing-preview-screen]");
    dots = $all(preview, "[data-cv-match-landing-preview-dot]");
    prevButton = $(preview, "[data-cv-match-landing-preview-prev]");
    nextButton = $(preview, "[data-cv-match-landing-preview-next]");
    statusNode = $(preview, "[data-cv-match-landing-preview-status]");

    if (!viewport || !track || !slides.length) {
      return;
    }

    preview.dataset.previewReady = "1";

    slides.forEach(function (slide) {
      $all(slide, "[data-preview-bar]").forEach(function (bar) {
        bar.style.width = "0%";
      });
    });

    function stopAutoplay() {
      if (autoplayTimer) {
        window.clearInterval(autoplayTimer);
        autoplayTimer = null;
      }
    }

    function startAutoplay() {
      stopAutoplay();
      autoplayTimer = window.setInterval(function () {
        setActive(activeIndex + 1);
      }, 3800);
    }

    function animateActiveSlide(slide) {
      var donut = slide.querySelector(".sffc-cv-match-studio__landing-preview-donut");
      var scoreNode = $(slide, "[data-cv-match-landing-preview-score]");
      var target = Number(slide.getAttribute("data-preview-score") || 0);
      var start = Number(scoreNode && scoreNode.dataset.renderedScore
        ? scoreNode.dataset.renderedScore
        : 0);
      var startedAt = 0;

      window.cancelAnimationFrame(scoreFrame);

      $all(slide, "[data-preview-bar]").forEach(function (bar) {
        bar.style.width = "0%";
      });

      window.setTimeout(function () {
        $all(slide, "[data-preview-bar]").forEach(function (bar) {
          var width = Number(bar.getAttribute("data-preview-bar") || 0);
          bar.style.width = Math.max(0, Math.min(100, width)) + "%";
        });
      }, 90);

      if (!donut || !scoreNode) {
        return;
      }

      function tick(timestamp) {
        var progress;
        var eased;
        var value;

        if (!startedAt) {
          startedAt = timestamp;
        }

        progress = Math.min(1, (timestamp - startedAt) / 680);
        eased = 1 - Math.pow(1 - progress, 3);
        value = start + (target - start) * eased;

        donut.style.setProperty("--score", value.toFixed(2));
        scoreNode.textContent = Math.round(value) + "%";
        scoreNode.dataset.renderedScore = String(Math.round(value));

        if (progress < 1) {
          scoreFrame = window.requestAnimationFrame(tick);
        }
      }

      scoreFrame = window.requestAnimationFrame(tick);
    }

    function translateTrack(offsetPx, disableTransition) {
      if (disableTransition) {
        track.style.transition = "none";
      } else {
        track.style.transition = "";
      }

      if (typeof offsetPx === "number") {
        track.style.transform = "translate3d(" + offsetPx + "px, 0, 0)";
        return;
      }

      track.style.transform = "translate3d(" + -activeIndex * 100 + "%, 0, 0)";
    }

    function setActive(index, options) {
      var normalized = index;
      var activeSlide;

      if (!slides.length) {
        return;
      }

      if (normalized < 0) {
        normalized = slides.length - 1;
      }
      if (normalized >= slides.length) {
        normalized = 0;
      }

      activeIndex = normalized;
      activeSlide = slides[activeIndex];

      slides.forEach(function (slide, slideIndex) {
        var active = slideIndex === activeIndex;
        slide.classList.toggle("is-active", active);
        slide.setAttribute("aria-hidden", active ? "false" : "true");

        if (!active) {
          $all(slide, "[data-preview-bar]").forEach(function (bar) {
            bar.style.width = "0%";
          });
        }
      });

      dots.forEach(function (dot, dotIndex) {
        dot.classList.toggle("is-active", dotIndex === activeIndex);
        dot.setAttribute("aria-pressed", dotIndex === activeIndex ? "true" : "false");
      });

      if (statusNode) {
        statusNode.textContent =
          activeSlide.getAttribute("data-preview-status") || "";
      }

      translateTrack(null, false);
      animateActiveSlide(activeSlide);

      if (!options || !options.skipAutoplayRestart) {
        startAutoplay();
      }
    }

    if (prevButton) {
      prevButton.addEventListener("click", function () {
        setActive(activeIndex - 1);
      });
    }

    if (nextButton) {
      nextButton.addEventListener("click", function () {
        setActive(activeIndex + 1);
      });
    }

    dots.forEach(function (dot, dotIndex) {
      dot.addEventListener("click", function () {
        setActive(dotIndex);
      });
    });

    viewport.addEventListener("pointerdown", function (event) {
      pointerActive = true;
      dragStartX = event.clientX;
      dragDeltaX = 0;
      viewport.classList.add("is-dragging");
      viewport.setPointerCapture(event.pointerId);
      stopAutoplay();
      translateTrack(-activeIndex * viewport.clientWidth, true);
    });

    viewport.addEventListener("pointermove", function (event) {
      var baseOffset;

      if (!pointerActive) {
        return;
      }

      dragDeltaX = event.clientX - dragStartX;
      baseOffset = -activeIndex * viewport.clientWidth;
      translateTrack(baseOffset + dragDeltaX, true);
    });

    function releasePointer(event) {
      if (!pointerActive) {
        return;
      }

      pointerActive = false;
      viewport.classList.remove("is-dragging");

      if (event && typeof viewport.releasePointerCapture === "function") {
        try {
          viewport.releasePointerCapture(event.pointerId);
        } catch (error) {
          // Ignore pointer capture release errors from rapid interactions.
        }
      }

      if (Math.abs(dragDeltaX) > 60) {
        setActive(activeIndex + (dragDeltaX < 0 ? 1 : -1));
      } else {
        setActive(activeIndex);
      }
    }

    viewport.addEventListener("pointerup", releasePointer);
    viewport.addEventListener("pointercancel", releasePointer);
    viewport.addEventListener("mouseleave", function () {
      if (pointerActive) {
        releasePointer();
      }
    });

    preview.addEventListener("mouseenter", stopAutoplay);
    preview.addEventListener("mouseleave", startAutoplay);

    setActive(0, { skipAutoplayRestart: true });
    startAutoplay();
  }

  function normalizeItem(item) {
    var reasons = Array.isArray(item.match_reasons)
      ? item.match_reasons
      : Array.isArray(item.reasons)
      ? item.reasons
      : [];
    var warnings = Array.isArray(item.match_warnings)
      ? item.match_warnings
      : Array.isArray(item.warnings)
      ? item.warnings
      : [];
    var gaps = Array.isArray(item.gaps) ? item.gaps : [];
    var skills = [];
    var keywords = [];

    if (Array.isArray(item.skills_mentioned)) {
      skills = item.skills_mentioned;
    } else if (
      typeof item.skills_mentioned === "string" &&
      item.skills_mentioned
    ) {
      try {
        var parsed = JSON.parse(item.skills_mentioned);
        if (Array.isArray(parsed)) {
          skills = parsed;
        }
      } catch (error) {
        skills = [];
      }
    }

    if (Array.isArray(item.keywords)) {
      keywords = item.keywords;
    } else if (typeof item.keywords === "string" && item.keywords) {
      try {
        var parsedKeywords = JSON.parse(item.keywords);
        if (Array.isArray(parsedKeywords)) {
          keywords = parsedKeywords;
        } else {
          keywords = item.keywords.split(/[\r\n,|]+/);
        }
      } catch (error) {
        keywords = item.keywords.split(/[\r\n,|]+/);
      }
    }

    function keywordLabel(keyword) {
      if (
        keyword &&
        typeof keyword === "object" &&
        !Array.isArray(keyword)
      ) {
        return String(
          keyword.label || keyword.term || keyword.name || keyword.value || ""
        ).trim();
      }

      return String(keyword || "").trim();
    }

    return {
      id: Number(item.id || 0),
      jobsPostId: Number(item.jobs_post_id || 0),
      wpPostId: Number(item.wp_post_id || 0),
      roleTitle: item.role_title || "",
      company: item.company || "",
      companyLogo: item.company_logo || "",
      recruiterId: Number(item.recruiter_id || 0),
      recruiterName: item.recruiter_name || item.recruiter_display_name || "",
      recruiterTitle: item.recruiter_title || "",
      recruiterFirm:
        item.recruiter_firm || item.recruiter_display_company || "",
      recruiterPhoto: item.recruiter_photo || "",
      recruiterEmail: item.recruiter_email || "",
      recruiterLinkedIn: item.recruiter_linkedin || "",
      recruiterPhone: item.recruiter_phone || "",
      recruiterOpenRolesCount: Number(item.recruiter_open_roles_count || 0),
      location:
        item.location || item.location_city || item.location_country || "",
      seniority: item.seniority || "",
      sector: item.sector || "",
      salaryText: item.salary_text || "",
      salaryMin: Number(item.salary_min || 0),
      salaryMax: Number(item.salary_max || 0),
      salaryCurrency: item.salary_currency || "",
      postedAt: item.posted_at || "",
      internalPermalink: item.internal_permalink || item.url || "",
      score: Math.max(0, Math.min(99, Number(item.match_score || 0))),
      reasons: reasons,
      warnings: warnings,
      gaps: gaps,
      skills: skills,
      keywords: keywords
        .map(function (keyword) {
          return keywordLabel(keyword);
        })
        .filter(Boolean)
        .slice(0, 5),
      matchInsightBadge: item.response_badge || "",
      description: item.content_snippet || item.content || "",
      permalink:
        item.permalink ||
        item.url ||
        item.apply_url ||
        item.source_url ||
        item.application_url ||
        "",
      isSaved: !!item.is_saved,
    };
  }

  function normalizeMatchText(value) {
    return String(value || "")
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, " ")
      .replace(/\s+/g, " ")
      .trim();
  }

  function getCurrentCvTextForRoot(root) {
    var candidates = [];
    var textarea;
    var floatingInput;

    if (root && root._cvMatchActiveCvText) {
      candidates.push(root._cvMatchActiveCvText);
    }

    if (root) {
      textarea = $(root, "[data-cv-match-input]");
      floatingInput = $(root, "[data-cv-match-floating-input]");
      candidates.push(textarea ? textarea.value : "");
      candidates.push(floatingInput ? floatingInput.value : "");
    }

    for (var i = 0; i < candidates.length; i += 1) {
      var text = String(candidates[i] || "").trim();
      if (text) {
        return text;
      }
    }

    return "";
  }

  function keywordMatchesCv(keyword, cvText) {
    var normalizedKeyword = normalizeMatchText(keyword);
    var normalizedCv = normalizeMatchText(cvText);
    var keywordTokens;

    if (!normalizedKeyword || !normalizedCv) {
      return false;
    }

    if (normalizedCv.indexOf(normalizedKeyword) !== -1) {
      return true;
    }

    keywordTokens = normalizedKeyword.split(" ").filter(Boolean);
    if (keywordTokens.length <= 1) {
      return (" " + normalizedCv + " ").indexOf(" " + normalizedKeyword + " ") !== -1;
    }

    return keywordTokens.every(function (token) {
      return (" " + normalizedCv + " ").indexOf(" " + token + " ") !== -1;
    });
  }

  function atsKeywordsCellMarkup(item, cvText) {
    var keywords = Array.isArray(item.keywords) ? item.keywords.slice(0, 5) : [];
    var insightBadge = String(item.matchInsightBadge || "").trim();

    if (!keywords.length && !insightBadge) {
      return (
        '<div class="sffc-cv-match-studio__ats-keywords-cell is-empty">' +
        '<span class="sffc-cv-match-studio__ats-keywords-empty">No CRM keywords yet</span>' +
        "</div>"
      );
    }

    return (
      '<div class="sffc-cv-match-studio__ats-keywords-cell">' +
      (insightBadge
        ? '<span class="sffc-cv-match-studio__ats-keywords-badge">' +
          escapeHtml(insightBadge) +
          "</span>"
        : "") +
      (keywords.length
        ? '<div class="sffc-cv-match-studio__ats-keywords-list">' +
          keywords
            .map(function (keyword) {
              var isMatch = keywordMatchesCv(keyword, cvText);
              return (
                '<span class="sffc-cv-match-studio__ats-keyword-chip ' +
                (isMatch ? "is-match" : "is-missing") +
                '">' +
                '<span class="sffc-cv-match-studio__ats-keyword-icon" aria-hidden="true">' +
                (isMatch ? "✓" : "×") +
                "</span>" +
                "<span>" +
                escapeHtml(keyword) +
                "</span>" +
                "</span>"
              );
            })
            .join("") +
          "</div>"
        : "") +
      "</div>"
    );
  }

  function mailboxKeywordsMarkup(keywords, cvText) {
    var list = Array.isArray(keywords) ? keywords.filter(Boolean).slice(0, 6) : [];

    if (!list.length) {
      return "";
    }

    return (
      '<span class="sffc-cv-match-studio__ats-keywords-list sffc-cv-match-studio__jobs-mailbox-keywords">' +
      list
        .map(function (keyword) {
          var isMatch = keywordMatchesCv(keyword, cvText);
          return (
            '<span class="sffc-cv-match-studio__ats-keyword-chip ' +
            (isMatch ? "is-match" : "is-missing") +
            '">' +
            '<span class="sffc-cv-match-studio__ats-keyword-icon" aria-hidden="true">' +
            (isMatch ? "✓" : "×") +
            "</span>" +
            "<span>" +
            escapeHtml(keyword) +
            "</span>" +
            "</span>"
          );
        })
        .join("") +
      "</span>"
    );
  }

  function renderJobsMailboxKeywords(scope) {
    var scopedRoot =
      scope && scope.closest
        ? scope.closest(
            '[data-component="crm-cv-match-studio"], [data-component="crm-cv-match-job"], .sffc-cv-match-studio'
          )
        : null;
    var cvText = getCurrentCvTextForRoot(scopedRoot);
    var searchScope = scope || scopedRoot || document;

    $all(searchScope, "[data-cv-match-mailbox-keywords]").forEach(function (node) {
      var raw = String(
        node.getAttribute("data-cv-match-mailbox-keyword-list") || ""
      ).trim();
      var keywords = raw
        ? raw
            .split("|")
            .map(function (item) {
              return String(item || "").trim();
            })
            .filter(Boolean)
        : [];

      node.innerHTML = mailboxKeywordsMarkup(keywords, cvText);
      node.hidden = !keywords.length;
    });
  }

  function sortLandingTileRoles(items) {
    return (Array.isArray(items) ? items.slice() : []).sort(function (left, right) {
      var scoreDiff = Number(right.score || 0) - Number(left.score || 0);
      if (scoreDiff !== 0) {
        return scoreDiff;
      }

      return parsePostedTime(right.postedAt) - parsePostedTime(left.postedAt);
    });
  }

  function landingTileRoleCardMarkup(item, cvText) {
    var recruiterName = publicRecruiterName(item);
    var avatarMarkup = item.recruiterPhoto
      ? '<img src="' +
        escapeHtml(item.recruiterPhoto) +
        '" alt="" class="sffc-cv-match-studio__tile-role-avatar-image">'
      : '<span>' +
        escapeHtml(initials(recruiterName || item.recruiterFirm || item.company)) +
        "</span>";
    var postedLabel = relativeTime(item.postedAt) || "Recently added";
    var reason = Array.isArray(item.reasons) && item.reasons.length
      ? item.reasons[0]
      : scoreToneLabel(item.score);

    return (
      '<article class="sffc-cv-match-studio__tile-role-card">' +
      '<div class="sffc-cv-match-studio__tile-role-meta">' +
      '<span>' + escapeHtml(postedLabel) + "</span>" +
      (item.location ? "<span>" + escapeHtml(item.location) + "</span>" : "") +
      "</div>" +
      '<div class="sffc-cv-match-studio__tile-role-avatar">' +
      avatarMarkup +
      "</div>" +
      '<div class="sffc-cv-match-studio__tile-role-copy">' +
      "<strong>" + escapeHtml(item.roleTitle || "Role") + "</strong>" +
      "<p>" + escapeHtml(item.company || "Company") + "</p>" +
      "</div>" +
      '<div class="sffc-cv-match-studio__tile-role-signal">' +
      '<em>' + escapeHtml(String(item.score || 0) + "%") + "</em>" +
      "<span>" + escapeHtml(reason) + "</span>" +
      "</div>" +
      '<div class="sffc-cv-match-studio__tile-role-actions">' +
      '<a class="sffc-cv-match-studio__tile-role-link" href="' +
      escapeHtml(item.internalPermalink || "#") +
      '" target="_blank" rel="noopener noreferrer" data-open-role-title="' +
      escapeHtml(item.roleTitle || "Role") +
      '" data-open-role-href="' +
      escapeHtml(item.internalPermalink || item.permalink || "#") +
      '" data-open-role-id="' +
      escapeHtml(String(item.jobsPostId || 0)) +
      '" data-open-role-wp-id="' +
      escapeHtml(String(item.wpPostId || 0)) +
      '" data-open-role-crm-id="' +
      escapeHtml(String(item.id || 0)) +
      '" data-open-role-location="' +
      escapeHtml(item.location || "") +
      '" data-open-role-sector="' +
      escapeHtml(item.sector || "") +
      '" data-open-role-seniority="' +
      escapeHtml(item.seniority || "") +
      '" data-open-role-keywords="' +
      escapeHtml((Array.isArray(item.keywords) ? item.keywords : []).join("|")) +
      '">' +
      "Open role" +
      "</a>" +
      '<button type="button" class="sffc-cv-match-studio__tile-role-button" data-open-role-quick-view="1" data-open-role-id="' +
      escapeHtml(String(item.jobsPostId || 0)) +
      '" data-open-role-wp-id="' +
      escapeHtml(String(item.wpPostId || 0)) +
      '" data-open-role-crm-id="' +
      escapeHtml(String(item.id || 0)) +
      '" data-open-role-title="' +
      escapeHtml(item.roleTitle || "Role") +
      '" data-open-role-href="' +
      escapeHtml(item.permalink || "#") +
      '" data-open-role-location="' +
      escapeHtml(item.location || "") +
      '" data-open-role-sector="' +
      escapeHtml(item.sector || "") +
      '" data-open-role-seniority="' +
      escapeHtml(item.seniority || "") +
      '" data-open-role-keywords="' +
      escapeHtml((Array.isArray(item.keywords) ? item.keywords : []).join("|")) +
      '">' +
      "Quick view" +
      "</button>" +
      "</div>" +
      "</article>"
    );
  }

  function normalizePreviewItem(item) {
    var tags = Array.isArray(item.tags)
      ? item.tags.filter(Boolean).slice(0, 3)
      : [];

    return {
      roleTitle: item.roleTitle || item.role_title || "",
      company: item.company || "",
      companyLogo: item.companyLogo || item.company_logo || "",
      companyInitial:
        item.companyInitial ||
        item.company_initial ||
        initials(item.company || item.roleTitle || item.role_title || ""),
      recruiterName: item.recruiterName || item.recruiter_name || "",
      recruiterPhoto: item.recruiterPhoto || item.recruiter_photo || "",
      recruiterInitial:
        item.recruiterInitial ||
        item.recruiter_initial ||
        initials(item.recruiterName || item.recruiter_name || "Recruiter"),
      recruiterTitle: item.recruiterTitle || item.recruiter_title || "",
      tags: tags,
    };
  }

  function formatRecruiterShortName(name) {
    var value = String(name || "").trim();
    if (!value) {
      return "Recruiter";
    }

    if (
      /^(recruiter|recruiter contact|talent contact|hiring team|hiring manager|recruitment team|search firm)$/i.test(
        value
      )
    ) {
      return value;
    }

    var parts = value.split(/\s+/).filter(Boolean);
    if (!parts.length) {
      return "Recruiter";
    }

    var firstName = String(parts[0] || "").trim();
    var lastName = String(parts[parts.length - 1] || "").trim();
    if (!firstName) {
      return "Recruiter";
    }

    if (parts.length <= 1 || !lastName || firstName === lastName) {
      return firstName;
    }

    return firstName + " " + lastName.charAt(0).toUpperCase() + ".";
  }

  function publicRecruiterName(item) {
    var rawName =
      (item && (item.recruiterName || item.recruiter_name)) || "";
    if (hasPremiumRecruiterAccess()) {
      return rawName || item.recruiterFirm || "Recruiter";
    }
    return formatRecruiterShortName(rawName || item.recruiterFirm || "");
  }

  var RECENT_ROLES_KEY = "sffcCvMatchRecentRoles";
  var RECENT_ROLES_LIMIT = 10;
  var JOBS_MAILBOX_PINS_KEY = "sffcCvMatchJobsMailboxPins";
  var JOBS_MAILBOX_HIDDEN_KEY = "sffcCvMatchJobsMailboxHidden";
  var JOBS_MAILBOX_CLICKS_KEY = "sffcCvMatchJobsMailboxClicks";
  var JOBS_MAILBOX_SEEN_KEY = "sffcCvMatchJobsMailboxSeen";
  var JOBS_MAILBOX_DESKTOP_LIMIT = 6;
  var GUEST_SIGNALS_KEY = "sffcCvMatchGuestSignals";
  var GUEST_SIGNALS_LIMIT = 12;
  var SIDEBAR_STATE_KEY = "sffcCvMatchSidebarCollapsed";

  function isMobileNavViewport() {
    return window.matchMedia("(max-width: 1100px)").matches;
  }

  function parseGuestReferrerMeta(referrer) {
    var value = String(referrer || "").trim();
    var empty = {
      referrerDomain: "",
      referrerPath: "",
      sourceChannel: "",
    };

    if (!value) {
      return empty;
    }

    try {
      var parsed = new URL(value, window.location.origin);
      var hostname = String(parsed.hostname || "").toLowerCase();
      var path = String(parsed.pathname || "");
      var sourceChannel = "external";

      if (
        hostname === String(window.location.hostname || "").toLowerCase() ||
        hostname === ""
      ) {
        sourceChannel = "internal";
      } else if (
        hostname.indexOf("linkedin.com") !== -1 ||
        hostname.indexOf("lnkd.in") !== -1
      ) {
        sourceChannel = "linkedin";
      } else if (hostname.indexOf("google.") !== -1) {
        sourceChannel = "google";
      } else if (
        hostname.indexOf("instagram.com") !== -1 ||
        hostname.indexOf("facebook.com") !== -1 ||
        hostname.indexOf("twitter.com") !== -1 ||
        hostname.indexOf("x.com") !== -1 ||
        hostname.indexOf("t.co") !== -1
      ) {
        sourceChannel = "social";
      }

      return {
        referrerDomain: hostname,
        referrerPath: path,
        sourceChannel: sourceChannel,
      };
    } catch (error) {
      return empty;
    }
  }

  function normalizeGuestSignalRole(role, fallbackEventType, fallbackIndex) {
    if (!role || typeof role !== "object") {
      return null;
    }

    var keywords = role.keywords;
    if (typeof keywords === "string" && keywords) {
      keywords = keywords.split(/[\r\n,|]+/);
    }

    var normalized = {
      title: String(role.title || "").trim(),
      href: String(role.href || "").trim(),
      pinned: !!role.pinned,
      touchedAt:
        Number(role.touchedAt || role.touched_at || 0) ||
        Date.now() - Number(fallbackIndex || 0),
      id: Number(role.id || 0),
      jobsPostId: Number(role.jobsPostId || role.jobs_post_id || 0),
      wpPostId: Number(role.wpPostId || role.wp_post_id || 0),
      location: String(role.location || "").trim(),
      sector: String(role.sector || "").trim(),
      seniority: String(role.seniority || "").trim(),
      eventType: String(role.eventType || role.event_type || fallbackEventType || "view").trim(),
      referrerDomain: String(role.referrerDomain || role.referrer_domain || "").trim().toLowerCase(),
      referrerPath: String(role.referrerPath || role.referrer_path || "").trim(),
      sourceChannel: String(role.sourceChannel || role.source_channel || "").trim().toLowerCase(),
      keywords: Array.isArray(keywords)
        ? keywords
            .map(function (keyword) {
              if (keyword && typeof keyword === "object") {
                return String(
                  keyword.label || keyword.term || keyword.name || keyword.value || ""
                ).trim();
              }
              return String(keyword || "").trim();
            })
            .filter(Boolean)
            .slice(0, 6)
        : [],
    };

    if (!normalized.title && !normalized.href && !normalized.id) {
      return null;
    }

    return normalized;
  }

  function loadGuestSignals() {
    try {
      var raw = window.localStorage.getItem(GUEST_SIGNALS_KEY);
      var parsed = raw ? JSON.parse(raw) : {};
      var interactions = Array.isArray(parsed && parsed.interactions)
        ? parsed.interactions
        : [];
      var landingRole = parsed && parsed.landingRole
        ? normalizeGuestSignalRole(parsed.landingRole, "landing", 0)
        : null;

      return {
        sessionStartedAt: Number(parsed && parsed.sessionStartedAt) || 0,
        updatedAt: Number(parsed && parsed.updatedAt) || 0,
        referrerDomain: String((parsed && parsed.referrerDomain) || "").trim().toLowerCase(),
        referrerPath: String((parsed && parsed.referrerPath) || "").trim(),
        sourceChannel: String((parsed && parsed.sourceChannel) || "").trim().toLowerCase(),
        landingRole: landingRole,
        interactions: interactions
          .map(function (interaction, index) {
            return normalizeGuestSignalRole(interaction, "view", index);
          })
          .filter(Boolean)
          .sort(compareRecentRoles)
          .slice(0, GUEST_SIGNALS_LIMIT),
      };
    } catch (error) {
      return {
        sessionStartedAt: 0,
        updatedAt: 0,
        referrerDomain: "",
        referrerPath: "",
        sourceChannel: "",
        landingRole: null,
        interactions: [],
      };
    }
  }

  function saveGuestSignals(state) {
    try {
      window.localStorage.setItem(GUEST_SIGNALS_KEY, JSON.stringify(state || {}));
    } catch (error) {
      return;
    }
  }

  function trackGuestSignal(role, eventType, options) {
    if (config.loggedIn) {
      return;
    }

    var normalizedRole = normalizeGuestSignalRole(role, eventType, 0);
    var guestSignals;
    var referrerMeta;
    var dedupeKey;
    var nextInteractions;
    var now = Date.now();

    if (!normalizedRole) {
      return;
    }

    guestSignals = loadGuestSignals();
    referrerMeta = parseGuestReferrerMeta(
      (options && options.referrer) || document.referrer || ""
    );

    if (referrerMeta.referrerDomain && !normalizedRole.referrerDomain) {
      normalizedRole.referrerDomain = referrerMeta.referrerDomain;
    }
    if (referrerMeta.referrerPath && !normalizedRole.referrerPath) {
      normalizedRole.referrerPath = referrerMeta.referrerPath;
    }
    if (referrerMeta.sourceChannel && !normalizedRole.sourceChannel) {
      normalizedRole.sourceChannel = referrerMeta.sourceChannel;
    }

    if (!guestSignals.sessionStartedAt) {
      guestSignals.sessionStartedAt = now;
    }
    guestSignals.updatedAt = now;

    if (!guestSignals.referrerDomain && normalizedRole.referrerDomain) {
      guestSignals.referrerDomain = normalizedRole.referrerDomain;
    }
    if (!guestSignals.referrerPath && normalizedRole.referrerPath) {
      guestSignals.referrerPath = normalizedRole.referrerPath;
    }
    if (!guestSignals.sourceChannel && normalizedRole.sourceChannel) {
      guestSignals.sourceChannel = normalizedRole.sourceChannel;
    }

    if (normalizedRole.eventType === "landing") {
      normalizedRole.pinned = true;
      guestSignals.landingRole = normalizedRole;
    }

    dedupeKey = normalizedRole.href || String(normalizedRole.id || normalizedRole.jobsPostId || normalizedRole.wpPostId || "");
    nextInteractions = guestSignals.interactions.filter(function (item) {
      var itemKey = item.href || String(item.id || item.jobsPostId || item.wpPostId || "");
      return itemKey !== dedupeKey;
    });

    nextInteractions.unshift(normalizedRole);
    guestSignals.interactions = nextInteractions
      .sort(compareRecentRoles)
      .slice(0, GUEST_SIGNALS_LIMIT);

    saveGuestSignals(guestSignals);
  }

  function getGuestSignalsPayload() {
    var guestSignals = loadGuestSignals();
    return {
      sessionStartedAt: Number(guestSignals.sessionStartedAt || 0),
      updatedAt: Number(guestSignals.updatedAt || 0),
      referrerDomain: String(guestSignals.referrerDomain || ""),
      referrerPath: String(guestSignals.referrerPath || ""),
      sourceChannel: String(guestSignals.sourceChannel || ""),
      landingRole: guestSignals.landingRole
        ? normalizeGuestSignalRole(guestSignals.landingRole, "landing", 0)
        : null,
      interactions: Array.isArray(guestSignals.interactions)
        ? guestSignals.interactions
            .map(function (interaction, index) {
              return normalizeGuestSignalRole(interaction, "view", index);
            })
            .filter(Boolean)
            .slice(0, GUEST_SIGNALS_LIMIT)
        : [],
    };
  }

  function loadRecentRoles() {
    try {
      var raw = window.localStorage.getItem(RECENT_ROLES_KEY);
      var parsed = raw ? JSON.parse(raw) : [];

      if (!Array.isArray(parsed)) {
        return [];
      }

      return parsed
        .filter(function (item) {
          return item && typeof item === "object" && item.title && item.href;
        })
        .map(function (item, index) {
          return {
            title: item.title,
            href: item.href,
            pinned: !!item.pinned,
            touchedAt: Number(item.touchedAt || 0) || Date.now() - index,
            id: Number(item.id || 0),
            jobsPostId: Number(item.jobsPostId || 0),
            wpPostId: Number(item.wpPostId || 0),
            location: String(item.location || ""),
            sector: String(item.sector || ""),
            seniority: String(item.seniority || ""),
            keywords: Array.isArray(item.keywords)
              ? item.keywords.filter(Boolean).slice(0, 6)
              : [],
          };
        })
        .sort(compareRecentRoles)
        .slice(0, RECENT_ROLES_LIMIT);
    } catch (error) {
      return [];
    }
  }

  function loadJobsMailboxPins() {
    if (config.loggedIn) {
      return Array.isArray(config.pinnedMailboxKeys)
        ? config.pinnedMailboxKeys
            .map(function (key) {
              return String(key || "").trim();
            })
            .filter(Boolean)
        : [];
    }

    if (config.defaultMailboxPinKey) {
      return [String(config.defaultMailboxPinKey || "").trim()].filter(Boolean);
    }

    return [];
  }

  function saveJobsMailboxPins(keys) {
    if (config.loggedIn) {
      config.pinnedMailboxKeys = (Array.isArray(keys) ? keys : [])
        .map(function (key) {
          return String(key || "").trim();
        })
        .filter(Boolean);
      return;
    }

    config.defaultMailboxPinKey = (Array.isArray(keys) ? keys : [])
      .map(function (key) {
        return String(key || "").trim();
      })
      .filter(Boolean)[0] || "";
  }

  function buildJobsMailboxPinPayload(key, source) {
    var row = source
      ? source.closest("[data-cv-match-mailbox-row]")
      : null;

    return {
      mailbox_key: String(key || "").trim(),
      crm_post_id: row
        ? parseInt(
            row.getAttribute("data-cv-match-mailbox-crm-post-id") || "0",
            10
          ) || 0
        : 0,
      jobs_post_id: row
        ? parseInt(
            row.getAttribute("data-cv-match-mailbox-jobs-post-id") || "0",
            10
          ) || 0
        : 0,
      wp_post_id: row
        ? parseInt(
            row.getAttribute("data-cv-match-mailbox-wp-post-id") || "0",
            10
          ) || 0
        : 0,
      role_title: row
        ? String(
            row.getAttribute("data-cv-match-mailbox-role-title") || ""
          ).trim()
        : "",
      company: row
        ? String(row.getAttribute("data-cv-match-mailbox-company") || "").trim()
        : "",
      location: row
        ? String(row.getAttribute("data-cv-match-mailbox-location") || "").trim()
        : "",
    };
  }

  function toggleJobsMailboxPin(key, source) {
    var previousPins;
    var nextPins;
    if (!key) {
      return Promise.resolve(loadJobsMailboxPins());
    }

    previousPins = loadJobsMailboxPins();
    nextPins =
      previousPins.indexOf(key) !== -1
        ? previousPins.filter(function (entry) {
            return entry !== key;
          })
        : [key].concat(
            previousPins.filter(function (entry) {
              return entry !== key;
            })
          );

    if (config.loggedIn) {
      var payload = buildJobsMailboxPinPayload(key, source);
      var requestBody = new URLSearchParams();

      saveJobsMailboxPins(nextPins);

      requestBody.set("action", "sffc_crm_toggle_mailbox_pin");
      requestBody.set("nonce", String(config.nonce || ""));
      requestBody.set("mailbox_key", payload.mailbox_key);
      requestBody.set("crm_post_id", String(payload.crm_post_id || 0));
      requestBody.set("jobs_post_id", String(payload.jobs_post_id || 0));
      requestBody.set("wp_post_id", String(payload.wp_post_id || 0));
      requestBody.set("role_title", payload.role_title);
      requestBody.set("company", payload.company);
      requestBody.set("location", payload.location);

      return window
        .fetch(String(config.ajaxUrl || ""), {
          method: "POST",
          credentials: "same-origin",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
          },
          body: requestBody.toString(),
        })
        .then(parseAjaxJson)
        .then(function (response) {
          if (!response || response.success !== true) {
            throw new Error(
              response &&
                response.data &&
                response.data.message
                ? response.data.message
                : "Unable to update pinned role."
            );
          }

          saveJobsMailboxPins(
            response.data && Array.isArray(response.data.pinnedKeys)
              ? response.data.pinnedKeys
              : []
          );

          return loadJobsMailboxPins();
        })
        .catch(function (error) {
          saveJobsMailboxPins(previousPins);
          throw error;
        });
    }

    saveJobsMailboxPins(nextPins);
    return Promise.resolve(nextPins);
  }

  function loadJobsMailboxHidden() {
    try {
      var raw = window.localStorage.getItem(JOBS_MAILBOX_HIDDEN_KEY);
      var parsed = raw ? JSON.parse(raw) : [];
      return Array.isArray(parsed)
        ? parsed
            .map(function (key) {
              return String(key || "").trim();
            })
            .filter(Boolean)
        : [];
    } catch (error) {
      return [];
    }
  }

  function saveJobsMailboxHidden(keys) {
    try {
      window.localStorage.setItem(
        JOBS_MAILBOX_HIDDEN_KEY,
        JSON.stringify((Array.isArray(keys) ? keys : []).filter(Boolean))
      );
    } catch (error) {
      return;
    }
  }

  function addJobsMailboxHidden(key) {
    var nextHidden;
    if (!key) {
      return;
    }
    nextHidden = loadJobsMailboxHidden();
    if (nextHidden.indexOf(key) === -1) {
      nextHidden.unshift(key);
      saveJobsMailboxHidden(nextHidden);
    }
  }

  function clearJobsMailboxHidden() {
    saveJobsMailboxHidden([]);
  }

  function loadJobsMailboxSeen() {
    try {
      var raw = window.localStorage.getItem(JOBS_MAILBOX_SEEN_KEY);
      var parsed = raw ? JSON.parse(raw) : [];
      return Array.isArray(parsed)
        ? parsed
            .map(function (key) {
              return String(key || "").trim();
            })
            .filter(Boolean)
        : [];
    } catch (error) {
      return [];
    }
  }

  function saveJobsMailboxSeen(keys) {
    try {
      window.localStorage.setItem(
        JOBS_MAILBOX_SEEN_KEY,
        JSON.stringify((Array.isArray(keys) ? keys : []).filter(Boolean))
      );
    } catch (error) {
      return;
    }
  }

  function loadJobsMailboxClicks() {
    try {
      var raw = window.localStorage.getItem(JOBS_MAILBOX_CLICKS_KEY);
      var parsed = raw ? JSON.parse(raw) : {};
      return parsed && typeof parsed === "object" ? parsed : {};
    } catch (error) {
      return {};
    }
  }

  function saveJobsMailboxClicks(clicks) {
    try {
      window.localStorage.setItem(
        JOBS_MAILBOX_CLICKS_KEY,
        JSON.stringify(clicks && typeof clicks === "object" ? clicks : {})
      );
    } catch (error) {
      return;
    }
  }

  function incrementJobsMailboxClick(key) {
    var nextClicks;
    var normalizedKey = String(key || "").trim();

    if (!normalizedKey) {
      return;
    }

    nextClicks = loadJobsMailboxClicks();
    nextClicks[normalizedKey] = Math.max(
      0,
      parseInt(nextClicks[normalizedKey] || 0, 10) || 0
    ) + 1;
    saveJobsMailboxClicks(nextClicks);
  }

  function getJobsMailboxClickKey(node) {
    var container;

    if (!node) {
      return "";
    }

    container =
      node.closest("[data-mailbox-key]") ||
      node.closest("[data-cv-match-mailbox-mobileapp-detail]") ||
      node.closest("[data-cv-match-mailbox-mobileapp-open]");

    return String(
      node.getAttribute("data-cv-match-mailbox-click-key") ||
        node.getAttribute("data-mailbox-key") ||
        (container && container.getAttribute("data-mailbox-key")) ||
        (container &&
          container.getAttribute("data-cv-match-mailbox-mobileapp-open")) ||
        node.getAttribute("data-cv-match-mailbox-mobileapp-detail") ||
        ""
    ).trim();
  }

  function loadSidebarCollapsed() {
    try {
      var stored = window.localStorage.getItem(SIDEBAR_STATE_KEY);
      return stored === null ? false : stored === "1";
    } catch (error) {
      return false;
    }
  }

  function saveSidebarCollapsed(collapsed) {
    try {
      window.localStorage.setItem(SIDEBAR_STATE_KEY, collapsed ? "1" : "0");
    } catch (error) {
      return;
    }
  }

  function compareRecentRoles(left, right) {
    if (!!left.pinned !== !!right.pinned) {
      return left.pinned ? -1 : 1;
    }

    return Number(right.touchedAt || 0) - Number(left.touchedAt || 0);
  }

  function saveRecentRoles(items) {
    try {
      window.localStorage.setItem(
        RECENT_ROLES_KEY,
        JSON.stringify(items.slice(0, RECENT_ROLES_LIMIT))
      );
    } catch (error) {
      return;
    }
  }

  function trackRecentRole(role) {
    if (!role || !role.title || !role.href) {
      return;
    }

    var now = Date.now();
    var nextItems = loadRecentRoles().filter(function (item) {
      return item.href !== role.href;
    });

    nextItems.unshift({
      title: role.title,
      href: role.href,
      pinned: false,
      touchedAt: now,
      id: Number(role.id || 0),
      jobsPostId: Number(role.jobsPostId || 0),
      wpPostId: Number(role.wpPostId || 0),
      location: String(role.location || ""),
      sector: String(role.sector || ""),
      seniority: String(role.seniority || ""),
      keywords: Array.isArray(role.keywords)
        ? role.keywords.filter(Boolean).slice(0, 6)
        : [],
    });

    saveRecentRoles(nextItems.sort(compareRecentRoles));
  }

  function getRecentRolesPayload() {
    return loadRecentRoles().map(function (item) {
      return {
        id: Number(item.id || 0),
        jobsPostId: Number(item.jobsPostId || 0),
        wpPostId: Number(item.wpPostId || 0),
        title: String(item.title || ""),
        href: String(item.href || ""),
        location: String(item.location || ""),
        sector: String(item.sector || ""),
        seniority: String(item.seniority || ""),
        keywords: Array.isArray(item.keywords)
          ? item.keywords.filter(Boolean).slice(0, 6)
          : [],
        touchedAt: Number(item.touchedAt || 0),
        pinned: !!item.pinned,
      };
    });
  }

  function togglePinnedRecentRole(href) {
    if (!href) {
      return;
    }

    var nextItems = loadRecentRoles().map(function (item) {
      if (item.href === href) {
        item.pinned = !item.pinned;
      }
      return item;
    });

    saveRecentRoles(nextItems.sort(compareRecentRoles));
  }

  function renderRecentRoles(root) {
    var recentNode = $(root, "[data-cv-match-recent]");
    var searchNode = $(root, "[data-cv-match-recent-search]");
    var query = searchNode
      ? String(searchNode.value || "")
          .trim()
          .toLowerCase()
      : "";
    if (!recentNode) {
      return;
    }

    var items = loadRecentRoles().filter(function (item) {
      if (!query) {
        return true;
      }
      return (
        String(item.title || "")
          .toLowerCase()
          .indexOf(query) !== -1
      );
    });
    if (!items.length) {
      recentNode.innerHTML =
        "<span>" +
        escapeHtml(
          query
            ? "No recent roles match your search"
            : "No recently opened roles yet"
        ) +
        "</span>";
      return;
    }

    recentNode.innerHTML = items
      .map(function (item) {
        var pinLabel = item.pinned ? "Unpin role" : "Pin role";

        return (
          "" +
          '<div class="sffc-cv-match-studio__sidebar-recent-item' +
          (item.pinned ? " is-pinned" : "") +
          '">' +
          '<a class="sffc-cv-match-studio__sidebar-recent-link" href="' +
          escapeHtml(item.href) +
          '">' +
          escapeHtml(item.title) +
          "</a>" +
          '<button type="button" class="sffc-cv-match-studio__sidebar-recent-pin' +
          (item.pinned ? " is-pinned" : "") +
          '" data-recent-pin="' +
          escapeHtml(item.href) +
          '" aria-label="' +
          escapeHtml(pinLabel) +
          '" title="' +
          escapeHtml(pinLabel) +
          '">' +
          '<svg viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M10.85 2.35a1.5 1.5 0 0 1 1.06 2.56l-.78.78v2.2c0 .18-.07.35-.19.48l-1.76 1.76v2.12c0 .17-.1.33-.26.4a.44.44 0 0 1-.49-.08L7.1 11.24l-2.9 2.9a.62.62 0 0 1-.88-.88l2.9-2.9-1.33-1.33a.44.44 0 0 1-.08-.49c.08-.16.23-.26.4-.26h2.12l1.76-1.76c.13-.13.3-.19.48-.19h2.2l.78-.78a1.5 1.5 0 0 1-2.12-2.12l.42-.43Z" fill="currentColor"/></svg>' +
          "</button>" +
          "</div>"
        );
      })
      .join("");
  }

  function scanPreviewLogoMarkup(item) {
    if (item.companyLogo) {
      return (
        '<img src="' +
        escapeHtml(item.companyLogo) +
        '" alt="' +
        escapeHtml(item.company || item.roleTitle || "") +
        '">'
      );
    }

    return (
      "<span>" +
      escapeHtml(
        item.companyInitial || initials(item.company || item.roleTitle || "S")
      ) +
      "</span>"
    );
  }

  function scanPreviewAvatarMarkup(item) {
    var recruiterName = publicRecruiterName(item);
    if (item.recruiterPhoto) {
      return (
        '<img src="' +
        escapeHtml(item.recruiterPhoto) +
        '" alt="' +
        escapeHtml(recruiterName) +
        '">'
      );
    }

    return (
      "<span>" +
      escapeHtml(item.recruiterInitial || initials(recruiterName || "R")) +
      "</span>"
    );
  }

  function renderScanPreviewRow(item, index, mode) {
    var normalized = normalizePreviewItem(item);
    var rowMode =
      mode || (index === 0 ? "avatars" : index === 1 ? "tags" : "meter");
    var title = normalized.roleTitle || "Live opportunity";
    var subtitleParts = [normalized.company, normalized.recruiterTitle].filter(
      Boolean
    );
    var subtitle = subtitleParts.join(" • ");
    var railMarkup = "";

    if (rowMode === "avatars") {
      railMarkup =
        '<div class="sffc-cv-match-studio__scan-preview-recruiters">' +
        '<span class="sffc-cv-match-studio__scan-preview-avatar">' +
        scanPreviewAvatarMarkup(normalized) +
        "</span>" +
        "</div>";
    } else if (rowMode === "tags") {
      railMarkup =
        '<div class="sffc-cv-match-studio__scan-preview-tags">' +
        (normalized.tags.length
          ? normalized.tags
              .map(function (tag) {
                return "<span>" + escapeHtml(tag) + "</span>";
              })
              .join("")
          : "<span>Matching live role signals</span>") +
        "</div>";
    } else {
      var scoreSeed = 82 - index * 9;
      railMarkup =
        '<div class="sffc-cv-match-studio__scan-preview-meter">' +
        '<span class="is-strong">' +
        escapeHtml(String(scoreSeed) + "%") +
        "</span>" +
        (normalized.tags[0]
          ? "<span>" + escapeHtml(normalized.tags[0]) + "</span>"
          : "") +
        "</div>";
    }

    return (
      "" +
      '<article class="sffc-cv-match-studio__scan-preview-row">' +
      '<div class="sffc-cv-match-studio__scan-preview-company">' +
      '<span class="sffc-cv-match-studio__scan-preview-logo">' +
      scanPreviewLogoMarkup(normalized) +
      "</span>" +
      '<div class="sffc-cv-match-studio__scan-preview-copy">' +
      "<strong>" +
      escapeHtml(title) +
      "</strong>" +
      "<span>" +
      escapeHtml(subtitle || "Recruiter-led opportunity being scanned") +
      "</span>" +
      "</div>" +
      "</div>" +
      railMarkup +
      "</article>"
    );
  }

  function renderScanPreview(root, items) {
    var preview = $(root, "[data-cv-match-scan-preview]");
    if (!preview) {
      return;
    }

    var list = Array.isArray(items) ? items.slice(0, 3) : [];
    if (!list.length) {
      preview.innerHTML = "";
      return;
    }

    preview.innerHTML = list
      .map(function (item, index) {
        return renderScanPreviewRow(item, index);
      })
      .join("");
  }

  function roleCellMarkup(item) {
    var logoMarkup = item.companyLogo
      ? '<img src="' +
        escapeHtml(item.companyLogo) +
        '" alt="" class="sffc-cv-match-studio__company-logo">'
      : '<span class="sffc-cv-match-studio__company-fallback">' +
        escapeHtml(initials(item.company || item.roleTitle)) +
        "</span>";
    var whyNow = item.reasons.length
      ? item.reasons[0]
      : "Relevant overlap detected across title, role scope, and market signals.";
    var badgeBits = item.reasons.slice(0, 3).map(function (reason, badgeIndex) {
      return (
        '<span class="sffc-cv-match-studio__match-badge sffc-cv-match-studio__match-badge--' +
        (badgeIndex + 1) +
        '"><i aria-hidden="true"></i>' +
        escapeHtml(reason) +
        "</span>"
      );
    });

    if (!badgeBits.length && item.isSaved) {
      badgeBits.push(
        '<span class="sffc-cv-match-studio__match-badge sffc-cv-match-studio__match-badge--1"><i aria-hidden="true"></i>' +
          escapeHtml(
            config.labels && config.labels.trackSaved
              ? config.labels.trackSaved
              : "Saved"
          ) +
          "</span>"
      );
    }

    return (
      "" +
      '<div class="sffc-cv-match-studio__role-cell sffc-cv-match-studio__role-cell--board">' +
      '<div class="sffc-cv-match-studio__role-brand">' +
      logoMarkup +
      "</div>" +
      '<div class="sffc-cv-match-studio__role-copy">' +
      "<strong>" +
      escapeHtml(item.roleTitle || "Role") +
      "</strong>" +
      "<span>" +
      escapeHtml(item.company || "Company") +
      "</span>" +
      '<span class="sffc-cv-match-studio__role-why">[MENA Careers signal] ' +
      escapeHtml(whyNow) +
      "</span>" +
      (badgeBits.length
        ? '<div class="sffc-cv-match-studio__match-badges">' +
          badgeBits.join("") +
          "</div>"
        : "") +
      "</div>" +
      "</div>"
    );
  }

  function recruiterCellMarkup(item) {
    var recruiterName = publicRecruiterName(item);
    var avatarMarkup = item.recruiterPhoto
      ? '<img src="' +
        escapeHtml(item.recruiterPhoto) +
        '" alt="" class="sffc-cv-match-studio__recruiter-photo">'
      : '<span class="sffc-cv-match-studio__recruiter-fallback">' +
        escapeHtml(initials(recruiterName || item.recruiterFirm || item.company)) +
        "</span>";

    var intelBits = [];
    if (item.recruiterFirm) {
      intelBits.push(
        '<span class="sffc-cv-match-studio__intel-chip">' +
          escapeHtml(item.recruiterFirm) +
          "</span>"
      );
    }
    if (item.reasons.length > 1) {
      intelBits.push(
        '<span class="sffc-cv-match-studio__intel-chip is-soft">' +
          escapeHtml(item.reasons[1]) +
          "</span>"
      );
    }

    return (
      "" +
      '<div class="sffc-cv-match-studio__recruiter-cell sffc-cv-match-studio__recruiter-cell--board">' +
      '<div class="sffc-cv-match-studio__recruiter-avatar">' +
      avatarMarkup +
      '<span class="sffc-cv-match-studio__recruiter-verified" aria-hidden="true">' +
      '<svg viewBox="0 0 16 16" focusable="false"><path d="M6.7 11.2 3.9 8.4l1.1-1.1 1.7 1.7 4.3-4.3 1.1 1.1-5.4 5.4Z" fill="currentColor"/></svg>' +
      "</span>" +
      "</div>" +
      '<div class="sffc-cv-match-studio__recruiter-copy">' +
      "<strong>" +
      escapeHtml(recruiterName || item.recruiterFirm || "Recruiter") +
      "</strong>" +
      "<span>" +
      escapeHtml(item.recruiterTitle || item.recruiterFirm || "Hiring team") +
      "</span>" +
      (item.location
        ? '<span class="sffc-cv-match-studio__location-pill">' +
          escapeHtml(item.location) +
          "</span>"
        : "") +
      (intelBits.length
        ? '<div class="sffc-cv-match-studio__intel-chips">' +
          intelBits.join("") +
          "</div>"
        : "") +
      "</div>" +
      "</div>"
    );
  }

  function recruiterContactCellMarkup(item) {
    var contacts = [];
    var locked = !hasPremiumRecruiterAccess();
    var membershipUrl = config.membershipUrl || "/membership/";

    if (item.recruiterEmail) {
      if (locked) {
        contacts.push(
          '<span class="sffc-cv-match-studio__recruiter-contact-link is-email is-locked">' +
            escapeHtml(maskEmail(item.recruiterEmail)) +
            "</span>"
        );
      } else {
        contacts.push(
          '<a href="mailto:' +
            escapeHtml(item.recruiterEmail) +
            '" class="sffc-cv-match-studio__recruiter-contact-link is-email">' +
            escapeHtml(item.recruiterEmail) +
            "</a>"
        );
      }
    }

    if (item.recruiterLinkedIn || locked) {
      contacts.push(
        '<a href="' +
          escapeHtml(locked ? membershipUrl : item.recruiterLinkedIn) +
          '" class="sffc-cv-match-studio__recruiter-contact-link is-linkedin' +
          (locked ? " is-locked" : "") +
          '"' +
          (locked ? "" : ' target="_blank" rel="noopener noreferrer"') +
          ">LinkedIn</a>"
      );
    }

    if (item.recruiterPhone) {
      if (locked) {
        contacts.push(
          '<span class="sffc-cv-match-studio__recruiter-contact-link is-phone is-locked">' +
            escapeHtml(maskPhone(item.recruiterPhone)) +
            "</span>"
        );
      } else {
        contacts.push(
          '<a href="tel:' +
            escapeHtml(String(item.recruiterPhone).replace(/[^0-9+]/g, "")) +
            '" class="sffc-cv-match-studio__recruiter-contact-link is-phone">' +
            escapeHtml(item.recruiterPhone) +
            "</a>"
        );
      }
    }

    if (!contacts.length) {
      return '<span class="sffc-cv-match-studio__recruiter-contact-empty">No contact info</span>';
    }

    return (
      '<div class="sffc-cv-match-studio__recruiter-contact-cell">' +
      contacts.join("") +
      "</div>"
    );
  }

  function fitCellMarkup(item, index) {
    var tone = scoreTone(item.score);
    var fitSignals = item.reasons.slice(0, 3);

    return (
      "" +
      '<div class="sffc-cv-match-studio__fit-cell sffc-cv-match-studio__fit-card">' +
      scoreCellMarkup(item, index) +
      (fitSignals.length
        ? '<ul class="sffc-cv-match-studio__fit-list">' +
          fitSignals
            .map(function (signal) {
              return "<li>" + escapeHtml(signal) + "</li>";
            })
            .join("") +
          "</ul>"
        : "") +
      "</div>"
    );
  }

  function selectionCellMarkup(index) {
    return (
      '<label class="sffc-cv-match-studio__results-check" aria-label="Select role">' +
      '<input type="checkbox" data-cv-match-result-select="' +
      escapeHtml(String(index)) +
      '">' +
      '<span class="sffc-cv-match-studio__results-check-ui">' +
      '<svg viewBox="0 0 16 16" aria-hidden="true"><path d="M6.4 11.4 3.2 8.2l1.2-1.2 2 2 5.2-5.2 1.2 1.2z"></path></svg>' +
      "</span>" +
      "</label>"
    );
  }

  function dealbreakersMarkup(item) {
    var items = item.gaps.length
      ? item.gaps.slice(0, 3)
      : item.warnings.length
      ? item.warnings.slice(0, 3)
      : ["No hard blockers detected yet."];

    return (
      '<ul class="sffc-cv-match-studio__dealbreakers-list">' +
      items
        .map(function (entry) {
          return "<li>" + escapeHtml(entry) + "</li>";
        })
        .join("") +
      "</ul>"
    );
  }

  function detailSectionMarkup(title, items, modifier) {
    if (!items.length) {
      return "";
    }

    return (
      "" +
      '<section class="sffc-cv-match-studio__detail-block' +
      (modifier ? " " + modifier : "") +
      '">' +
      "<h4>" +
      escapeHtml(title) +
      "</h4>" +
      "<ul>" +
      items
        .map(function (item) {
          return "<li>" + escapeHtml(item) + "</li>";
        })
        .join("") +
      "</ul>" +
      "</section>"
    );
  }

  function scoreTone(score) {
    if (score >= 75) {
      return "strong";
    }
    if (score >= 55) {
      return "good";
    }
    if (score >= 35) {
      return "moderate";
    }
    return "weak";
  }

  function scoreCellMarkup(item, index) {
    var tone = scoreTone(item.score);

    return (
      "" +
      '<div class="sffc-cv-match-studio__score-cell sffc-cv-match-studio__score-cell--' +
      tone +
      '">' +
      "<strong>" +
      escapeHtml(item.score + "%") +
      "</strong>" +
      '<span class="sffc-cv-match-studio__score-bar" aria-hidden="true"><span class="sffc-cv-match-studio__score-bar-fill" style="width:' +
      escapeHtml(item.score + "%") +
      ';"></span></span>' +
      "</div>"
    );
  }

  function smartApplyCellMarkup(index) {
    return (
      "" +
      '<div class="sffc-cv-match-studio__smart-apply-cell">' +
      '<button type="button" class="sffc-cv-match-studio__smart-apply-trigger" data-smart-apply-open="' +
      index +
      '">Smart Intro</button>' +
      "</div>"
    );
  }

  function inferCandidateName(cvText) {
    var lines = String(cvText || "")
      .split(/\r?\n/)
      .map(function (line) {
        return line.trim();
      })
      .filter(Boolean);

    for (var i = 0; i < Math.min(lines.length, 6); i += 1) {
      var line = lines[i];
      if (line.indexOf("@") !== -1 || /\+?\d/.test(line)) {
        continue;
      }
      if (line.length > 2 && line.length < 72) {
        return line.replace(/\s*\|\s*.*/, "");
      }
    }

    return "Candidate";
  }

  function firstNameFromValue(value) {
    var parts = String(value || "")
      .trim()
      .split(/\s+/)
      .filter(Boolean);

    return parts.length ? parts[0] : "";
  }

  function currentUserFirstName() {
    return (
      (config.currentUser && config.currentUser.firstName) ||
      config.firstName ||
      firstNameFromValue(config.currentUser && config.currentUser.displayName) ||
      ""
    );
  }

  function smartApplyReadinessLabel(score) {
    if (score >= 80) {
      return "Ready to send with light refinement";
    }
    if (score >= 65) {
      return "Strong base with targeted improvements";
    }
    if (score >= 45) {
      return "Needs sharper role alignment before send";
    }
    return "Rework materials before outreach";
  }

  function smartApplyRiskTone(level) {
    if (level === "Low risk") {
      return "is-low";
    }
    if (level === "Medium risk") {
      return "is-medium";
    }
    return "is-high";
  }

  function smartApplyRiskLevel(value, lowCutoff, midCutoff, invert) {
    if (invert) {
      if (value <= lowCutoff) {
        return "Low risk";
      }
      if (value <= midCutoff) {
        return "Medium risk";
      }
      return "High risk";
    }

    if (value >= midCutoff) {
      return "Low risk";
    }
    if (value >= lowCutoff) {
      return "Medium risk";
    }
    return "High risk";
  }

  function smartApplyFromMarkup(cvText) {
    var displayName =
      inferCandidateName(cvText) ||
      (config.currentUser && config.currentUser.displayName) ||
      "Join MENA Careers Member";
    var email =
      (config.currentUser && config.currentUser.email) ||
      "member@joinsenna.com";

    return (
      "" +
      '<div class="sffc-cv-match-studio__smart-apply-from-chip">' +
      '<span class="sffc-cv-match-studio__smart-apply-from-avatar">' +
      escapeHtml(initials(displayName)) +
      "</span>" +
      '<span class="sffc-cv-match-studio__smart-apply-from-copy">' +
      "<strong>" +
      escapeHtml(displayName) +
      "</strong>" +
      "<em>" +
      escapeHtml(email) +
      "</em>" +
      "</span>" +
      '<span class="sffc-cv-match-studio__smart-apply-from-caret" aria-hidden="true">' +
      '<svg viewBox="0 0 16 16"><path d="M4.25 6.5 8 10.25 11.75 6.5" fill="none" stroke="currentColor" stroke-width="1.35" stroke-linecap="round" stroke-linejoin="round"/></svg>' +
      "</span>" +
      "</div>"
    );
  }

  function smartApplyRecipientMarkup(item) {
    var label = item.recruiterEmail || publicRecruiterName(item) || "Hiring team";

    return (
      "" +
      '<span class="sffc-cv-match-studio__smart-apply-recipient-pill">' +
      escapeHtml(label) +
      '<button type="button" class="sffc-cv-match-studio__smart-apply-recipient-remove" aria-label="Recipient selected">×</button>' +
      "</span>"
    );
  }

  function smartApplyPackGridMarkup() {
    var materialActions = [
      {
        type: "cv_template",
        label: "CV Template",
        meta: "Role-specific CV draft",
        kind: "word",
      },
      {
        type: "cover_letter",
        label: "Cover Letter",
        meta: "Tailored to the live brief",
        kind: "word",
      },
      {
        type: "interview_questions",
        label: "Interview Questions",
        meta: "Likely interview themes",
        kind: "pdf",
      },
      {
        type: "hiring_guide",
        label: "Hiring Guide",
        meta: "What to expect in the process",
        kind: "pdf",
      },
    ];

    return materialActions
      .map(function (material) {
        var artMarkup =
          material.kind === "pdf"
            ? "" +
              '<span class="sffc-cv-match-studio__job-material-art sffc-cv-match-studio__job-material-art--pdf" aria-hidden="true">' +
              '<span class="sffc-cv-match-studio__job-material-pdf-page">' +
              '<span class="sffc-cv-match-studio__job-material-pdf-fold"></span>' +
              '<svg viewBox="0 0 64 64" focusable="false" aria-hidden="true"><path d="M25.5 18.5c2.2 0 3.6 1.4 3.6 4.1 0 4.2-2 10-5.5 17.1 5 .9 10 2.3 15.4 4.7 2.3-2.2 4.7-3.7 7.1-3.7 2.6 0 4.4 1.5 4.4 3.9 0 3.8-4.4 6-9.8 6-2.2 0-4.6-.3-7.1-.9-2.9 3.8-6.1 7.1-9.7 7.1-2.8 0-4.6-1.5-4.6-3.7 0-3.1 3.5-5.4 9.3-6.5 2.7-4.7 5.1-10.1 6.4-14.5-1.9 4.1-5 6.7-8.1 6.7-3.4 0-5.8-2.4-5.8-6.1 0-6.1 3.6-12.1 6.4-12.1Zm-.1 3.7c-1.2 0-3.1 3.7-3.1 8.1 0 2 .8 3.1 2 3.1 2 0 4.6-4.1 4.6-8.3 0-1.9-1.1-2.9-3.5-2.9Zm14.8 20.5c-3.5-1.4-7.2-2.5-10.8-3.3-1.8 3.2-3.8 6-5.8 8.5 4.8.5 9.6-.4 16.6-5.2Zm-19 6c-2.7.6-4.3 1.6-4.3 2.7 0 .7.6 1.1 1.5 1.1 1.4 0 3-1.1 5-3.8-.7-.1-1.4-.1-2.2 0Zm22.1-1c1.2 0 2.1-.4 2.1-1.2 0-.5-.3-.7-.8-.7-.9 0-1.8.4-3.1 1.4.7.3 1.2.5 1.8.5Z" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" /></svg>' +
              '<span class="sffc-cv-match-studio__job-material-pdf-label">PDF</span>' +
              "</span>" +
              "</span>"
            : "" +
              '<span class="sffc-cv-match-studio__job-material-art sffc-cv-match-studio__job-material-art--word" aria-hidden="true">' +
              '<span class="sffc-cv-match-studio__job-material-word-sheet is-back"></span>' +
              '<span class="sffc-cv-match-studio__job-material-word-sheet is-mid"></span>' +
              '<span class="sffc-cv-match-studio__job-material-word-tile">W</span>' +
              "</span>";
        return (
          "" +
          '<button type="button" class="sffc-cv-match-studio__smart-apply-pack-item" data-smart-apply-pack-open="' +
          escapeHtml(material.type) +
          '">' +
          '<span class="sffc-cv-match-studio__smart-apply-pack-kind">' +
          artMarkup +
          "</span>" +
          '<span class="sffc-cv-match-studio__smart-apply-pack-copy">' +
          '<strong class="sffc-cv-match-studio__smart-apply-pack-label">' +
          escapeHtml(material.label) +
          "</strong>" +
          '<span class="sffc-cv-match-studio__smart-apply-pack-meta">' +
          escapeHtml(material.meta) +
          "</span>" +
          "</span>" +
          '<span class="sffc-cv-match-studio__smart-apply-pack-arrow" aria-hidden="true">' +
          '<svg viewBox="0 0 16 16"><path d="M5.5 3.75h6.75V10.5M11.95 4.05 4.05 11.95" fill="none" stroke="currentColor" stroke-width="1.35" stroke-linecap="round" stroke-linejoin="round"/></svg>' +
          "</span>" +
          "</button>"
        );
      })
      .join("");
  }

  function getMaterialsLoaderConfig(preferredMaterialType) {
    var materialType = String(preferredMaterialType || "").trim();
    var materialLabels = {
      cv_template: "Tailored CV",
      cover_letter: "Cover Letter",
      interview_questions: "Interview Questions",
      hiring_guide: "Hiring Guide",
    };
    var materialLabel = materialLabels[materialType] || "Application Material";

    if (materialType) {
      return {
        etaSeconds: 28,
        title: "MENA Careers is building your " + materialLabel + ".",
        description:
          "MENA Careers is matching your CV against the live role, then drafting and polishing this specific document for you.",
        steps: [
          {
            label: "Reading the live job brief and recruiter context…",
            percent: 12,
          },
          {
            label: "Extracting the strongest CV signals for this role…",
            percent: 28,
          },
          {
            label: "Mapping the exact ATS keywords for your " + materialLabel + "…",
            percent: 46,
          },
          {
            label: "Drafting your " + materialLabel + " with role-specific positioning…",
            percent: 68,
          },
          {
            label: "Polishing tone, evidence, and recruiter relevance…",
            percent: 86,
          },
          {
            label: "Finalising your " + materialLabel + " for download…",
            percent: 100,
          },
        ],
      };
    }

    return {
      etaSeconds: 75,
      title: "MENA Careers is building your application pack.",
      description:
        "MENA Careers is matching your CV against the live role, then generating each document in the pack one by one.",
      steps: [
        { label: "Reading the live job brief…", percent: 12 },
        {
          label: "Extracting the strongest CV signals from your resume…",
          percent: 24,
        },
        {
          label:
            "Generating Tailored CV with role-positioned bullets and keyword alignment…",
          percent: 42,
        },
        {
          label:
            "Generating Cover Letter with role-specific positioning and hiring rationale…",
          percent: 58,
        },
        {
          label:
            "Generating Interview Questions based on the brief, recruiter context, and likely screening themes…",
          percent: 76,
        },
        {
          label:
            "Generating Hiring Guide and packaging the full recruiter-ready application pack…",
          percent: 100,
        },
      ],
    };
  }

  function getMaterialsLoaderMarkup(loaderConfig) {
    var config = loaderConfig || getMaterialsLoaderConfig("");
    var stepsMarkup = (config.steps || [])
      .map(function (step, index) {
        return (
          '<div class="sffc-cv-match-studio__materials-loader-step' +
          (index === 0 ? " is-active" : "") +
          '" data-cv-match-materials-loader-step="' +
          index +
          '">' +
          '<span class="sffc-cv-match-studio__materials-loader-step-dot"></span>' +
          "<span>" +
          escapeHtml(step.label || "") +
          "</span>" +
          "</div>"
        );
      })
      .join("");

    return (
      "" +
      '<div class="sffc-cv-match-studio__materials-loader" data-cv-match-materials-loader>' +
      '<div class="sffc-cv-match-studio__materials-loader-visual" aria-hidden="true">' +
      '<div class="sffc-cv-match-studio__materials-loader-orbit sffc-cv-match-studio__materials-loader-orbit--one"></div>' +
      '<div class="sffc-cv-match-studio__materials-loader-orbit sffc-cv-match-studio__materials-loader-orbit--two"></div>' +
      '<div class="sffc-cv-match-studio__materials-loader-orbit sffc-cv-match-studio__materials-loader-orbit--three"></div>' +
      '<div class="sffc-cv-match-studio__materials-loader-core">' +
      '<div class="sffc-cv-match-studio__materials-loader-spinner"></div>' +
      "</div>" +
      "</div>" +
      '<div class="sffc-cv-match-studio__materials-loader-card">' +
      '<p class="sffc-cv-match-studio__materials-loader-kicker">Tailored materials in progress</p>' +
      "<h3>" +
      escapeHtml(config.title || "MENA Careers is building your application pack.") +
      "</h3>" +
      "<p>" +
      escapeHtml(
        config.description ||
          "MENA Careers is matching your CV against the live role, then generating each document in the pack one by one."
      ) +
      "</p>" +
      '<div class="sffc-cv-match-studio__materials-loader-meta">' +
      '<span>Estimated completion</span>' +
      '<strong data-cv-match-materials-loader-eta>~75 seconds</strong>' +
      '<strong data-cv-match-materials-loader-progress>12%</strong>' +
      "</div>" +
      '<div class="sffc-cv-match-studio__materials-loader-status" data-cv-match-materials-loader-status>Reading the live job brief…</div>' +
      '<div class="sffc-cv-match-studio__materials-loader-bar"><span data-cv-match-materials-loader-bar></span></div>' +
      '<div class="sffc-cv-match-studio__materials-loader-steps">' +
      stepsMarkup +
      "</div>" +
      "</div>" +
      "</div>"
    );
  }

  function smartApplyPreviewDraft(item, cvText) {
    var candidateName =
      currentUserFirstName() ||
      firstNameFromValue(inferCandidateName(cvText)) ||
      "Candidate";
    var recruiterName =
      publicRecruiterName(item) ||
      item.recruiterFirm ||
      item.company ||
      "Hiring Team";
    var roleTitle = item.roleTitle || "this role";
    var company = item.company || "your team";
    var primaryReason =
      (item.reasons && item.reasons[0]) ||
      "clear overlap with the brief and recruiter context";

    return {
      subject: roleTitle + " | " + candidateName,
      body: [
        "Hi " + recruiterName + ",",
        "",
        "I am reaching out regarding the " +
          roleTitle +
          " opportunity at " +
          company +
          ".",
        "",
        "My background looks especially relevant because of " +
          primaryReason.toLowerCase() +
          ". I have prepared a tailored CV, cover letter, and outreach pack for this role.",
        "",
        "I would welcome the chance to share why I could be a strong fit for this brief.",
        "",
        "Best,",
        candidateName,
      ].join("\n"),
    };
  }

  function formatSmartApplyBodyText(value) {
    return escapeHtml(String(value || "")).replace(/\n/g, "<br>");
  }

  function personalizeMaterialSignature(value) {
    var candidateFirstName =
      currentUserFirstName() ||
      firstNameFromValue(inferCandidateName(getCurrentCvText())) ||
      "Candidate";

    return String(value || "")
      .replace(/\[Your Name\]/gi, candidateFirstName)
      .replace(/\[your name\]/gi, candidateFirstName);
  }

  function getSmartApplyBodyText(node) {
    if (!node) {
      return "";
    }

    return String(node.innerText || node.textContent || "")
      .replace(/\n{3,}/g, "\n\n")
      .trim();
  }

  function smartApplyReasonBadgesMarkup(item) {
    var reasons = item.reasons.slice(0, 4);
    if (!reasons.length) {
      reasons = [
        "Relevant title alignment",
        "Good market overlap",
        "Strong application base",
      ];
    }
    return reasons
      .map(function (reason, index) {
        return (
          '<span class="sffc-cv-match-studio__match-badge sffc-cv-match-studio__match-badge--' +
          ((index % 3) + 1) +
          '"><i aria-hidden="true"></i>' +
          escapeHtml(reason) +
          "</span>"
        );
      })
      .join("");
  }

  function smartApplyGapListMarkup(item) {
    var entries = item.gaps.slice(0, 3);
    if (!entries.length) {
      entries = item.warnings.slice(0, 3);
    }
    if (!entries.length) {
      entries = [
        "No obvious blocker detected. Focus on sharper role-specific positioning.",
      ];
    }
    return entries
      .map(function (entry) {
        return "<li>" + escapeHtml(entry) + "</li>";
      })
      .join("");
  }

  function smartApplyRecommendation(item) {
    var score = item.score || 0;
    var title = "";
    var copy = "";

    if (score >= 80) {
      title = "Prioritise and send";
      copy =
        "This looks like a strong match. Lead with your most relevant evidence, generate the materials, and reach out directly rather than waiting.";
    } else if (score >= 65) {
      title = "Send after tightening";
      copy =
        "This is a credible match, but it will perform better if you sharpen the weak spots MENA Careers flagged before sending the pack.";
    } else if (score >= 45) {
      title = "Improve before applying";
      copy =
        "There is enough alignment to continue, but your application needs clearer positioning and stronger role-specific signals first.";
    } else {
      title = "Low-priority route";
      copy =
        "This role is currently a weak fit. Rework the narrative heavily or focus attention on stronger matches before spending outreach effort here.";
    }

    if (item.reasons.length) {
      copy +=
        " The strongest signal right now is " +
        item.reasons[0].toLowerCase() +
        ".";
    }

    return {
      title: title,
      copy: copy,
    };
  }

  function smartApplyRiskCardsMarkup(item) {
    var score = item.score || 0;
    var gapCount = item.gaps.length;
    var warningCount = item.warnings.length;
    var cards = [
      {
        label: "Matching score",
        value: score + "/100",
        level: smartApplyRiskLevel(score, 55, 75, false),
      },
      {
        label: "CV risk",
        value:
          gapCount === 0
            ? "No major missing signals"
            : gapCount + " gap" + (gapCount === 1 ? "" : "s"),
        level: smartApplyRiskLevel(gapCount, 1, 3, true),
      },
      {
        label: "Application risk",
        value:
          warningCount === 0
            ? "Few visible blockers"
            : warningCount + " watchpoint" + (warningCount === 1 ? "" : "s"),
        level: smartApplyRiskLevel(warningCount, 1, 3, true),
      },
    ];

    return cards
      .map(function (card) {
        return (
          "" +
          '<article class="sffc-cv-match-studio__smart-apply-risk-card">' +
          "<span>" +
          escapeHtml(card.label) +
          "</span>" +
          "<strong>" +
          escapeHtml(card.value) +
          "</strong>" +
          '<em class="' +
          smartApplyRiskTone(card.level) +
          '">' +
          escapeHtml(card.level) +
          "</em>" +
          "</article>"
        );
      })
      .join("");
  }

  function parsePostedTime(value) {
    if (!value) {
      return 0;
    }

    var date = new Date(String(value).replace(" ", "T"));
    return Number.isNaN(date.getTime()) ? 0 : date.getTime();
  }

  function updateResultsStats(root, items) {
    var totalNode = $(root, "[data-cv-match-stat-total]");
    var strongNode = $(root, "[data-cv-match-stat-strong]");
    var strongMetaNode = $(root, "[data-cv-match-stat-strong-meta]");
    var locationsNode = $(root, "[data-cv-match-stat-locations]");
    var strongBar = $(root, "[data-cv-match-stat-strong-bar]");
    var totalStrongBar = $(root, "[data-cv-match-stat-bar-strong]");
    var totalMidBar = $(root, "[data-cv-match-stat-bar-mid]");
    var totalWeakBar = $(root, "[data-cv-match-stat-bar-weak]");
    var totalStrongLabel = $(root, "[data-cv-match-stat-label-strong]");
    var totalMidLabel = $(root, "[data-cv-match-stat-label-mid]");
    var totalWeakLabel = $(root, "[data-cv-match-stat-label-weak]");
    var locationPills = $(root, "[data-cv-match-stat-locations-pills]");
    var locationBars = $all(root, "[data-cv-match-stat-location-bar]");
    var locationLabels = $all(root, "[data-cv-match-stat-location-label]");
    var locationCountLabels = $all(root, "[data-cv-match-stat-location-count]");
    var uniqueLocations = {};
    var locationCounts = {};
    var strongCount = 0;
    var midCount = 0;
    var weakCount = 0;

    items.forEach(function (item) {
      if (item.score >= 70) {
        strongCount += 1;
      } else if (item.score >= 35) {
        midCount += 1;
      } else {
        weakCount += 1;
      }

      if (item.location) {
        uniqueLocations[item.location.toLowerCase()] = true;
        locationCounts[item.location] =
          (locationCounts[item.location] || 0) + 1;
      }
    });

    if (totalNode) {
      totalNode.textContent = String(items.length);
    }
    if (strongNode) {
      strongNode.textContent = String(strongCount);
    }
    if (strongMetaNode) {
      strongMetaNode.textContent =
        (items.length ? Math.round((strongCount / items.length) * 100) : 0) +
        "% of live roles are already high fit";
    }
    if (locationsNode) {
      locationsNode.textContent = String(Object.keys(uniqueLocations).length);
    }
    if (strongBar) {
      strongBar.style.width =
        (items.length
          ? Math.max(10, Math.round((strongCount / items.length) * 100))
          : 0) + "%";
    }
    if (totalStrongBar || totalMidBar || totalWeakBar) {
      var total = Math.max(items.length, 1);
      var strongPct = Math.round((strongCount / total) * 100);
      var midPct = Math.round((midCount / total) * 100);
      var weakPct = Math.max(0, 100 - strongPct - midPct);

      if (totalStrongBar) {
        totalStrongBar.style.width =
          Math.max(strongCount ? 8 : 0, strongPct) + "%";
      }
      if (totalMidBar) {
        totalMidBar.style.width = Math.max(midCount ? 8 : 0, midPct) + "%";
      }
      if (totalWeakBar) {
        totalWeakBar.style.width = Math.max(weakCount ? 8 : 0, weakPct) + "%";
      }
      if (totalStrongLabel) {
        totalStrongLabel.textContent = String(strongCount);
      }
      if (totalMidLabel) {
        totalMidLabel.textContent = String(midCount);
      }
      if (totalWeakLabel) {
        totalWeakLabel.textContent = String(weakCount);
      }
    }
    var rankedLocations = Object.keys(locationCounts).sort(function (
      left,
      right
    ) {
      return locationCounts[right] - locationCounts[left];
    });
    if (locationPills) {
      locationPills.setAttribute(
        "data-location-extra",
        String(Math.max(0, rankedLocations.length - 3))
      );
    }
    if (locationBars.length) {
      var maxLocationCount = rankedLocations.length
        ? locationCounts[rankedLocations[0]]
        : 0;

      locationBars.forEach(function (bar, index) {
        var locationKey = rankedLocations[index] || "";
        var count = locationKey ? locationCounts[locationKey] : 0;
        var width = maxLocationCount
          ? Math.max(
              count ? 12 : 0,
              Math.round((count / maxLocationCount) * 100)
            )
          : 0;
        bar.style.width = width + "%";
        bar.classList.toggle("is-empty", !count);
      });
    }
    if (locationLabels.length) {
      locationLabels.forEach(function (labelNode, index) {
        var locationKey = rankedLocations[index] || "No market yet";
        labelNode.textContent = locationKey;
      });
    }
    if (locationCountLabels.length) {
      locationCountLabels.forEach(function (countNode, index) {
        var locationKey = rankedLocations[index] || "";
        countNode.textContent = String(
          locationKey ? locationCounts[locationKey] : 0
        );
      });
    }
  }

  function updateResultsCtaCard(root, atsState) {
    var ctaCard = $(root, "[data-cv-match-cta-card]");
    var scoreChart = $(root, "[data-cv-match-cta-score-chart]");
    var scoreText = $(root, "[data-cv-match-cta-score-text]");
    var titleNode = $(root, "[data-cv-match-cta-title]");
    var copyNode = $(root, "[data-cv-match-cta-copy]");
    var buttonNode = $(root, "[data-cv-match-cta-button]");
    var labelNode = ctaCard ? $(ctaCard, "span") : null;
    var score = atsState && atsState.score ? Number(atsState.score) : 0;
    var value =
      atsState && atsState.value ? String(atsState.value) : score > 0 ? score + "%" : "ATS";

    if (!config.loggedIn) {
      if (labelNode) {
        labelNode.textContent = "Career Assessment";
      }
      if (titleNode) {
        titleNode.textContent = "Unlock ATS score";
      }
      if (copyNode) {
        copyNode.textContent =
          "See your ATS match, missing signals, and what to tighten before you apply.";
      }
      if (buttonNode) {
        buttonNode.textContent = "Get Career Assessment";
      }
      if (scoreChart) {
        scoreChart.style.setProperty("--score", "84");
        scoreChart.classList.add("is-locked");
      }
      if (scoreText) {
        scoreText.classList.add("sffc-cv-match-studio__stat-score-text", "is-locked");
        scoreText.innerHTML =
          '<span class="sffc-cv-match-studio__stat-score-lock" aria-hidden="true"><svg viewBox="0 0 24 24" role="presentation" focusable="false"><path d="M8 10V7a4 4 0 1 1 8 0v3" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"></path><rect x="6.5" y="10" width="11" height="9" rx="2.2" fill="none" stroke="currentColor" stroke-width="1.8"></rect></svg></span><span class="sffc-cv-match-studio__stat-score-label">ATS</span>';
      }
      return;
    }

    if (labelNode) {
      labelNode.textContent = "Career Assessment";
    }
    if (titleNode) {
      titleNode.textContent = score > 0 ? "Current ATS score" : "Start your Career Assessment";
    }
    if (copyNode) {
      copyNode.textContent =
        score > 0
          ? "Use this score to decide what to tighten before you apply and where to focus your next CV edits."
          : "Start your Career Assessment to generate your ATS score, identify missing signals, and tighten your profile before you apply.";
    }
    if (buttonNode) {
      buttonNode.textContent = "Start Career Assessment";
    }
    if (scoreChart) {
      scoreChart.style.setProperty("--score", String(Math.max(0, Math.min(100, score))));
      scoreChart.classList.remove("is-locked");
    }
    if (scoreText) {
      scoreText.classList.remove("sffc-cv-match-studio__stat-score-text", "is-locked");
      scoreText.textContent = value;
    }
  }

  function getVisibleResults(root, items) {
    var searchNode = $(root, "[data-cv-match-search]");
    var sortNode = $(root, "[data-cv-match-sort]");
    var filterNode = $(root, "[data-cv-match-filter]");
    var query = searchNode
      ? String(searchNode.value || "")
          .trim()
          .toLowerCase()
      : "";
    var sortValue = sortNode
      ? String(sortNode.value || "match_desc")
      : "match_desc";
    var filterValue = filterNode ? String(filterNode.value || "all") : "all";
    var visible = items.filter(function (item) {
      var haystack = [
        item.roleTitle,
        item.company,
        item.recruiterName,
        item.recruiterTitle,
        item.location,
      ]
        .join(" ")
        .toLowerCase();

      if (query && haystack.indexOf(query) === -1) {
        return false;
      }

      if (filterValue === "strong" && item.score < 70) {
        return false;
      }

      if (filterValue === "mid" && (item.score < 35 || item.score >= 70)) {
        return false;
      }

      return true;
    });

    visible.sort(function (left, right) {
      if (sortValue === "posted_desc") {
        return parsePostedTime(right.postedAt) - parsePostedTime(left.postedAt);
      }
      if (sortValue === "role_asc") {
        return String(left.roleTitle || "").localeCompare(
          String(right.roleTitle || "")
        );
      }
      return right.score - left.score;
    });

    return visible;
  }

  function cellWithMobileLabel(label, content) {
    return (
      '<span class="sffc-cv-match-studio__mobile-cell-label">' +
      escapeHtml(label) +
      "</span>" +
      content
    );
  }

  function scoreToneLabel(score) {
    if (score >= 75) {
      return "Strong fit";
    }
    if (score >= 55) {
      return "Good fit";
    }
    if (score >= 35) {
      return "Partial fit";
    }
    return "Stretch fit";
  }

  function recommendedMatchRating(score) {
    var numeric = Number(score || 0);

    if (numeric >= 75) {
      return "perfect";
    }
    if (numeric >= 60) {
      return "strong";
    }
    if (numeric >= 50) {
      return "ok";
    }
    return "weak";
  }

  function recommendedMatchRatingLabel(score) {
    var rating = recommendedMatchRating(score);

    if (rating === "perfect") {
      return "Perfect Match";
    }
    if (rating === "strong") {
      return "Strong Match";
    }
    if (rating === "ok") {
      return "OK Match";
    }
    return "Weak Match";
  }

  function mobileResultCardMarkup(item, index, cvText) {
    var tone = scoreTone(item.score);
    var recruiterName = publicRecruiterName(item);
    var logoMarkup = item.companyLogo
      ? '<img src="' +
        escapeHtml(item.companyLogo) +
        '" alt="" class="sffc-cv-match-studio__company-logo">'
      : '<span class="sffc-cv-match-studio__company-fallback">' +
        escapeHtml(initials(item.company || item.roleTitle)) +
        "</span>";
    var avatarMarkup = item.recruiterPhoto
      ? '<img src="' +
        escapeHtml(item.recruiterPhoto) +
        '" alt="" class="sffc-cv-match-studio__recruiter-photo">'
      : '<span class="sffc-cv-match-studio__recruiter-fallback">' +
        escapeHtml(initials(recruiterName || item.recruiterFirm || item.company)) +
        "</span>";
    var whyNow = item.reasons.length
      ? item.reasons[0]
      : "Relevant overlap detected across title, scope, and role signals.";
    var openRoleLabel =
      config.labels && config.labels.openRole
        ? config.labels.openRole
        : "Open role";
    var quickViewLabel =
      config.labels && config.labels.quickView
        ? config.labels.quickView
        : "Quick view";

    return (
      '<article class="sffc-cv-match-studio__mobile-result-card">' +
      '<div class="sffc-cv-match-studio__mobile-result-select">' +
      selectionCellMarkup(index) +
      "</div>" +
      '<div class="sffc-cv-match-studio__mobile-result-top">' +
      '<div class="sffc-cv-match-studio__mobile-result-role">' +
      '<div class="sffc-cv-match-studio__mobile-result-logo">' +
      logoMarkup +
      "</div>" +
      '<div class="sffc-cv-match-studio__mobile-result-copy">' +
      "<strong>" +
      escapeHtml(item.roleTitle || "Role") +
      "</strong>" +
      "<span>" +
      escapeHtml(item.company || "Company") +
      "</span>" +
      "</div>" +
      "</div>" +
      '<div class="sffc-cv-match-studio__mobile-result-score sffc-cv-match-studio__mobile-result-score--' +
      tone +
      '">' +
      "<strong>" +
      escapeHtml(item.score + "%") +
      "</strong>" +
      "<span>" +
      escapeHtml(scoreToneLabel(item.score)) +
      "</span>" +
      "</div>" +
      "</div>" +
      '<p class="sffc-cv-match-studio__mobile-result-signal sffc-cv-match-studio__mobile-result-signal--' +
      tone +
      '">' +
      escapeHtml(whyNow) +
      "</p>" +
      '<div class="sffc-cv-match-studio__mobile-result-recruiter">' +
      '<div class="sffc-cv-match-studio__recruiter-avatar">' +
      avatarMarkup +
      '<span class="sffc-cv-match-studio__recruiter-verified" aria-hidden="true">' +
      '<svg viewBox="0 0 16 16" focusable="false"><path d="M6.7 11.2 3.9 8.4l1.1-1.1 1.7 1.7 4.3-4.3 1.1 1.1-5.4 5.4Z" fill="currentColor"/></svg>' +
      "</span>" +
      "</div>" +
      '<div class="sffc-cv-match-studio__mobile-result-recruiter-copy">' +
      "<strong>" +
      escapeHtml(recruiterName || item.recruiterFirm || "Recruiter") +
      "</strong>" +
      "<span>" +
      escapeHtml(item.recruiterTitle || item.recruiterFirm || "Hiring team") +
      "</span>" +
      "</div>" +
      (item.location
        ? '<span class="sffc-cv-match-studio__location-pill">' +
          escapeHtml(item.location) +
          "</span>"
        : "") +
      "</div>" +
      '<div class="sffc-cv-match-studio__mobile-result-meta">' +
      '<div class="sffc-cv-match-studio__mobile-result-materials">' +
      atsKeywordsCellMarkup(item, cvText) +
      "</div>" +
      '<div class="sffc-cv-match-studio__mobile-result-roles">' +
      smartApplyCellMarkup(index) +
      "</div>" +
      "</div>" +
      '<div class="sffc-cv-match-studio__mobile-result-contact">' +
      recruiterContactCellMarkup(item) +
      "</div>" +
      '<div class="sffc-cv-match-studio__mobile-result-action">' +
      '<a class="sffc-cv-match-studio__open-button sffc-cv-match-studio__open-button--listing" href="' +
      escapeHtml(item.permalink || "#") +
      '" target="_blank" rel="noopener noreferrer" data-open-role-title="' +
      escapeHtml(item.roleTitle || "Role") +
      '" data-open-role-href="' +
      escapeHtml(item.permalink || "#") +
      '" data-open-role-id="' +
      escapeHtml(String(item.jobsPostId || 0)) +
      '" data-open-role-wp-id="' +
      escapeHtml(String(item.wpPostId || 0)) +
      '" data-open-role-crm-id="' +
      escapeHtml(String(item.id || 0)) +
      '" data-open-role-location="' +
      escapeHtml(item.location || "") +
      '" data-open-role-sector="' +
      escapeHtml(item.sector || "") +
      '" data-open-role-seniority="' +
      escapeHtml(item.seniority || "") +
      '" data-open-role-keywords="' +
      escapeHtml((Array.isArray(item.keywords) ? item.keywords : []).join("|")) +
      '">' +
      escapeHtml(openRoleLabel) +
      "</a>" +
      '<button class="sffc-cv-match-studio__quick-view-button" type="button" data-open-role-quick-view="1" data-open-role-id="' +
      escapeHtml(String(item.jobsPostId || 0)) +
      '" data-open-role-wp-id="' +
      escapeHtml(String(item.wpPostId || 0)) +
      '" data-open-role-crm-id="' +
      escapeHtml(String(item.id || 0)) +
      '" data-open-role-title="' +
      escapeHtml(item.roleTitle || "Role") +
      '" data-open-role-href="' +
      escapeHtml(item.permalink || "#") +
      '" data-open-role-location="' +
      escapeHtml(item.location || "") +
      '" data-open-role-sector="' +
      escapeHtml(item.sector || "") +
      '" data-open-role-seniority="' +
      escapeHtml(item.seniority || "") +
      '" data-open-role-keywords="' +
      escapeHtml((Array.isArray(item.keywords) ? item.keywords : []).join("|")) +
      '">' +
      escapeHtml(quickViewLabel) +
      "</button>" +
      "</div>" +
      "</article>"
    );
  }

  function resultsReportAvatarMarkup(item, className) {
    var avatar = item && item.avatar ? String(item.avatar) : "";
    var fallback =
      item && item.avatar_fallback
        ? String(item.avatar_fallback)
        : initials(item && item.label ? item.label : "S");

    return avatar
      ? '<span class="' +
          className +
          '"><img src="' +
          escapeHtml(avatar) +
          '" alt=""></span>'
      : '<span class="' +
          className +
          '"><span>' +
          escapeHtml(fallback) +
          "</span></span>";
  }

  function resultsReportMetricCardsMarkup(metrics) {
    if (!Array.isArray(metrics) || !metrics.length) {
      return "";
    }

    return (
      '<div class="sffc-cv-match-studio__results-report-metrics">' +
      metrics
        .map(function (metric) {
          return (
            '<article class="sffc-cv-match-studio__results-report-metric">' +
            '<span class="sffc-cv-match-studio__results-report-metric-label">' +
            escapeHtml(metric.label || "") +
            "</span>" +
            "<strong>" +
            escapeHtml(metric.value || "") +
            "</strong>" +
            '<em class="sffc-cv-match-studio__results-report-metric-meta">' +
            escapeHtml(metric.meta || "") +
            "</em>" +
            "</article>"
          );
        })
        .join("") +
      "</div>"
    );
  }

  function normalizeResultsReportSalaryEstimate(source) {
    if (!source || typeof source !== "object") {
      return null;
    }

    var display = String(source.display || "").trim();
    var note = String(source.note || "").trim();

    if (!display && !note) {
      return null;
    }

    return {
      display: display,
      note: note,
    };
  }

  function buildResultsReportSalarySentence(estimate, topMarket) {
    var salaryEstimate = normalizeResultsReportSalaryEstimate(estimate);
    var marketLabel = String(topMarket || "").trim();

    if (!salaryEstimate || !salaryEstimate.display) {
      return "";
    }

    if (marketLabel) {
      return (
        "Estimated target salary in " +
        marketLabel +
        ": " +
        salaryEstimate.display +
        "."
      );
    }

    return "Estimated target salary: " + salaryEstimate.display + ".";
  }

  function buildResultsReportSummaryModel(summary, aiPayload) {
    var model = summary && typeof summary === "object" ? JSON.parse(JSON.stringify(summary)) : {};
    var aiEstimate = normalizeResultsReportSalaryEstimate(
      aiPayload && aiPayload.salary_estimate ? aiPayload.salary_estimate : null
    );
    var fallbackEstimate = normalizeResultsReportSalaryEstimate(
      model.salary_estimate || null
    );
    var activeEstimate = aiEstimate || fallbackEstimate;
    var salarySentence = buildResultsReportSalarySentence(
      activeEstimate,
      model.top_market || ""
    );
    var summaryCopy = String(model.copy || "").trim();

    if (salarySentence) {
      if (summaryCopy) {
        summaryCopy = summaryCopy.replace(
          /Estimated target salary in .*?: .*?\.(\s|$)|Estimated target salary: .*?\.(\s|$)/i,
          ""
        ).trim();
        summaryCopy = summaryCopy ? summaryCopy + " " + salarySentence : salarySentence;
      } else {
        summaryCopy = salarySentence;
      }
    }

    model.copy = summaryCopy;
    model.salary_estimate = activeEstimate || model.salary_estimate || null;

    if (Array.isArray(model.metrics) && model.metrics.length && activeEstimate && activeEstimate.display) {
      model.metrics = model.metrics.map(function (metric) {
        if (String(metric && metric.label || "").trim().toLowerCase() !== "estimated target salary") {
          return metric;
        }

        return {
          label: metric.label || "Estimated Target Salary",
          value: activeEstimate.display,
          meta:
            activeEstimate.note ||
            metric.meta ||
            "Estimated from your strongest live role set.",
        };
      });
    }

    return model;
  }

  function resultsReportListMarkup(items, itemClass) {
    if (!Array.isArray(items) || !items.length) {
      return "";
    }

    return (
      '<ul class="sffc-cv-match-studio__results-report-list' +
      (itemClass ? " " + itemClass : "") +
      '">' +
      items
        .map(function (item) {
          var label = "";
          var meta = "";
          var score = "";

          if (typeof item === "string") {
            label = item;
          } else if (item && typeof item === "object") {
            label = String(item.label || item.title || item.value || "").trim();
            meta = String(item.meta || item.copy || "").trim();
            if (typeof item.score === "number" && !Number.isNaN(item.score)) {
              score = String(item.score) + "%";
            }
          }

          if (!label) {
            return "";
          }

          return (
            "<li>" +
            "<strong>" +
            escapeHtml(label) +
            "</strong>" +
            (meta
              ? '<span class="sffc-cv-match-studio__results-report-item-meta">' +
                escapeHtml(meta) +
                "</span>"
              : "") +
            (score
              ? '<em class="sffc-cv-match-studio__results-report-item-score">' +
                escapeHtml(score) +
                "</em>"
              : "") +
            "</li>"
          );
        })
        .join("") +
      "</ul>"
    );
  }

  function resultsReportEntityListMarkup(items, variant) {
    if (!Array.isArray(items) || !items.length) {
      return "";
    }

    return (
      '<div class="sffc-cv-match-studio__results-report-entity-list' +
      (variant ? " is-" + variant : "") +
      '">' +
      items
        .map(function (item) {
          var metric =
            typeof item.score === "number" && !Number.isNaN(item.score)
              ? String(item.score) + "%"
              : "";
          return (
            '<article class="sffc-cv-match-studio__results-report-entity">' +
            resultsReportAvatarMarkup(
              item,
              "sffc-cv-match-studio__results-report-entity-avatar"
            ) +
            '<div class="sffc-cv-match-studio__results-report-entity-copy">' +
            "<strong>" +
            escapeHtml(item.label || "") +
            "</strong>" +
            (item.meta
              ? '<span class="sffc-cv-match-studio__results-report-entity-meta">' +
                escapeHtml(item.meta) +
                "</span>"
              : "") +
            "</div>" +
            (metric
              ? '<span class="sffc-cv-match-studio__results-report-entity-score">' +
                escapeHtml(metric) +
                "</span>"
              : "") +
            "</article>"
          );
        })
        .join("") +
      "</div>"
    );
  }

  function resultsReportBarChartMarkup(items, variant) {
    if (!Array.isArray(items) || !items.length) {
      return "";
    }

    return (
      '<div class="sffc-cv-match-studio__results-report-bars' +
      (variant ? " is-" + variant : "") +
      '">' +
      items
        .map(function (item) {
          var width = Math.max(
            8,
            Math.min(
              100,
              Number(
                typeof item.share === "number"
                  ? item.share
                  : typeof item.score === "number"
                  ? item.score
                  : 0
              ) || 0
            )
          );
          return (
            '<article class="sffc-cv-match-studio__results-report-bar-item">' +
            '<div class="sffc-cv-match-studio__results-report-bar-head">' +
            "<strong>" +
            escapeHtml(item.label || "") +
            "</strong>" +
            (item.meta
              ? '<span class="sffc-cv-match-studio__results-report-bar-meta">' +
                escapeHtml(item.meta) +
                "</span>"
              : "") +
            "</div>" +
            '<div class="sffc-cv-match-studio__results-report-bar-track">' +
            '<span class="sffc-cv-match-studio__results-report-bar-fill" style="width:' +
            escapeHtml(String(width)) +
            '%"></span>' +
            "</div>" +
            "</article>"
          );
        })
        .join("") +
      "</div>"
    );
  }

  function resultsReportKeywordMarkup(matched, missing) {
    var chips = [];

    (Array.isArray(matched) ? matched : []).forEach(function (term) {
      if (!term) {
        return;
      }
      chips.push(
        '<span class="sffc-cv-match-studio__results-report-chip is-match">' +
          '<i aria-hidden="true">+</i>' +
          "<span>" +
          escapeHtml(String(term)) +
          "</span>" +
          "</span>"
      );
    });

    (Array.isArray(missing) ? missing : []).forEach(function (term) {
      if (!term) {
        return;
      }
      chips.push(
        '<span class="sffc-cv-match-studio__results-report-chip is-missing">' +
          '<i aria-hidden="true">-</i>' +
          "<span>" +
          escapeHtml(String(term)) +
          "</span>" +
          "</span>"
      );
    });

    if (!chips.length) {
      return "";
    }

    return (
      '<div class="sffc-cv-match-studio__results-report-chiplist">' +
      chips.join("") +
      "</div>"
    );
  }

  function resultsReportAiMarkup(payload, status) {
    var analysis = payload && typeof payload === "object" ? payload : null;
    var panelKeys = ["readiness", "competition", "market_timing"];
    var panelCards = "";

    if (analysis && analysis.panels) {
      panelCards = panelKeys
        .map(function (key) {
          var panel = analysis.panels[key];
          if (!panel || !panel.label) {
            return "";
          }
          return (
            '<article class="sffc-cv-match-studio__results-report-ai-panel">' +
            '<div class="sffc-cv-match-studio__score-chart sffc-cv-match-studio__score-chart--ready sffc-cv-match-studio__results-report-ai-panel-chart" style="--score:' +
            escapeHtml(String(panel.score || 0)) +
            ';">' +
            '<span class="sffc-cv-match-studio__score-text">' +
            escapeHtml(String(panel.score || 0)) +
            "</span>" +
            "</div>" +
            '<div class="sffc-cv-match-studio__results-report-ai-panel-copy">' +
            "<strong>" +
            escapeHtml(panel.label || "") +
            "</strong>" +
            (panel.note ? "<span>" + escapeHtml(panel.note) + "</span>" : "") +
            "</div>" +
            "</article>"
          );
        })
        .join("");
    }

    return (
      '<section class="sffc-cv-match-studio__results-report-ai ' +
      (status === "loading" ? "is-loading" : "") +
      '">' +
      '<div class="sffc-cv-match-studio__results-report-ai-head">' +
      '<span class="sffc-cv-match-studio__results-report-eyebrow">AI Strategic Layer</span>' +
      "<h3>" +
      escapeHtml(
        (analysis && analysis.headline) ||
          "Synthesizing positioning, recruiter angle, and interview priorities"
      ) +
      "</h3>" +
      "<p>" +
      escapeHtml(
        (analysis && analysis.summary) ||
          "MENA Careers is layering in a sharper strategic read over your top current matches."
      ) +
      "</p>" +
      "</div>" +
      (status === "loading"
        ? '<div class="sffc-cv-match-studio__results-report-ai-loading"><span></span><span></span><span></span></div>'
        : "") +
      (panelCards
        ? '<div class="sffc-cv-match-studio__results-report-ai-panels">' +
          panelCards +
          "</div>"
        : "") +
      (analysis
        ? '<div class="sffc-cv-match-studio__results-report-ai-grid">' +
          resultsReportCardMarkup({
            type: "actions",
            title: "Positioning Moves",
            copy: "How to frame your background for these roles.",
            items: analysis.positioning_moves || [],
          }) +
          resultsReportCardMarkup({
            type: "actions",
            title: "Recruiter Angle",
            copy: "What signal to lead with when approaching hiring teams.",
            items: analysis.recruiter_angle || [],
          }) +
          resultsReportCardMarkup({
            type: "actions",
            title: "Interview Focus",
            copy: "What to sharpen before first-round conversations.",
            items: analysis.interview_focus || [],
          }) +
          resultsReportCardMarkup({
            type: "actions",
            title: "Application Pack Priorities",
            copy: "What to tighten in your CV and materials before you apply.",
            items: analysis.application_pack || [],
          }) +
          ((Array.isArray(analysis.watchouts) && analysis.watchouts.length)
            ? resultsReportCardMarkup({
                type: "actions",
                title: "Watchouts",
                copy: "Recurring risks across your top current roles.",
                items: analysis.watchouts || [],
              })
            : "") +
          "</div>"
        : "") +
      "</section>"
    );
  }

  function resultsReportCardMarkup(card) {
    if (!card || typeof card !== "object") {
      return "";
    }

    var body = "";
    if (card.type === "keywords") {
      body =
        '<div class="sffc-cv-match-studio__results-report-keywords">' +
        '<div class="sffc-cv-match-studio__results-report-keywords-score">' +
        '<div class="sffc-cv-match-studio__score-chart sffc-cv-match-studio__score-chart--ready sffc-cv-match-studio__job-match-chart sffc-cv-match-studio__results-report-chart" style="--score:' +
        escapeHtml(String(card.score || 0)) +
        ';">' +
        '<span class="sffc-cv-match-studio__score-text">' +
        escapeHtml(String(card.score || 0)) +
        "</span>" +
        "</div>" +
        '<span class="sffc-cv-match-studio__results-report-keywords-summary">' +
        escapeHtml(card.summary || "") +
        "</span>" +
        "</div>" +
        resultsReportKeywordMarkup(card.matched, card.missing) +
        "</div>";
    } else if (card.type === "roles" || card.type === "recruiters") {
      body = resultsReportEntityListMarkup(card.items || [], card.type);
    } else if (
      card.type === "lanes" ||
      card.type === "markets" ||
      card.type === "distribution" ||
      card.type === "blockers"
    ) {
      body = resultsReportBarChartMarkup(card.items || [], card.type);
    } else if (card.type === "salary") {
      body = resultsReportListMarkup(card.items || [], "");
    } else {
      body = resultsReportListMarkup(card.items || [], "is-plain");
    }

    return (
      '<article class="sffc-cv-match-studio__results-report-card is-' +
      escapeHtml(card.type || "generic") +
      '">' +
      '<div class="sffc-cv-match-studio__results-report-card-head">' +
      "<h3>" +
      escapeHtml(card.title || "") +
      "</h3>" +
      (card.copy
        ? "<p>" + escapeHtml(String(card.copy || "")) + "</p>"
        : "") +
      "</div>" +
      body +
      "</article>"
    );
  }

  function resultsReportAccordionMarkup(options) {
    if (!options || !options.content) {
      return "";
    }

    return (
      '<details class="sffc-cv-match-studio__results-report-accordion' +
      (options.className ? " " + options.className : "") +
      '"' +
      (options.open ? " open" : "") +
      ">" +
      '<summary class="sffc-cv-match-studio__results-report-accordion-toggle">' +
      '<div class="sffc-cv-match-studio__results-report-accordion-copy">' +
      (options.eyebrow
        ? '<span class="sffc-cv-match-studio__results-report-eyebrow">' +
          escapeHtml(options.eyebrow) +
          "</span>"
        : "") +
      "<strong>" +
      escapeHtml(options.title || "") +
      "</strong>" +
      (options.copy
        ? "<span>" + escapeHtml(String(options.copy || "")) + "</span>"
        : "") +
      "</div>" +
      '<span class="sffc-cv-match-studio__results-report-accordion-icon" aria-hidden="true"></span>' +
      "</summary>" +
      '<div class="sffc-cv-match-studio__results-report-accordion-panel">' +
      options.content +
      "</div>" +
      "</details>"
    );
  }

  function renderResultsReport(root, payload, items) {
    var mount = $(root, "[data-cv-match-results-report]");
    var cards = payload && Array.isArray(payload.cards) ? payload.cards : [];
    var summary = payload && payload.summary ? payload.summary : null;
    var aiPayload = root._cvMatchResultsReportAiPayload || null;
    var aiStatus = root._cvMatchResultsReportAiStatus || "";
    var resolvedSummary = null;

    if (!mount) {
      return;
    }

    if (!summary || !cards.length || !Array.isArray(items) || !items.length) {
      mount.hidden = true;
      mount.innerHTML = "";
      return;
    }

    resolvedSummary = buildResultsReportSummaryModel(summary, aiPayload);

    var summaryMarkup =
      '<article class="sffc-cv-match-studio__results-report-summary">' +
      '<div class="sffc-cv-match-studio__results-report-summary-head">' +
      '<div class="sffc-cv-match-studio__results-report-summary-copy">' +
      (resolvedSummary.eyebrow
        ? '<span class="sffc-cv-match-studio__results-report-eyebrow">' +
          escapeHtml(resolvedSummary.eyebrow) +
          "</span>"
        : "") +
      "<h3>" +
      escapeHtml(resolvedSummary.headline || "Your Match Report") +
      "</h3>" +
      (resolvedSummary.copy
        ? "<p>" + escapeHtml(String(resolvedSummary.copy || "")) + "</p>"
        : "") +
      "</div>" +
      '<div class="sffc-cv-match-studio__results-report-summary-score">' +
      '<div class="sffc-cv-match-studio__score-chart sffc-cv-match-studio__score-chart--ready sffc-cv-match-studio__job-match-chart sffc-cv-match-studio__results-report-summary-chart" style="--score:' +
      escapeHtml(String(resolvedSummary.score || 0)) +
      ';">' +
      '<span class="sffc-cv-match-studio__score-text">' +
      escapeHtml(String(resolvedSummary.score || 0)) +
      "</span>" +
      "</div>" +
      "<span>" +
      escapeHtml(resolvedSummary.score_label || "Top-fit average") +
      "</span>" +
      "</div>" +
      "</div>" +
      ((resolvedSummary.primary_lane || resolvedSummary.top_market)
        ? '<div class="sffc-cv-match-studio__results-report-summary-meta">' +
          (resolvedSummary.primary_lane
            ? '<span class="sffc-cv-match-studio__results-report-summary-pill">' +
              escapeHtml(resolvedSummary.primary_lane) +
              "</span>"
            : "") +
          (resolvedSummary.top_market
            ? '<span class="sffc-cv-match-studio__results-report-summary-pill">' +
              escapeHtml(resolvedSummary.top_market) +
              "</span>"
            : "") +
          '<span class="sffc-cv-match-studio__results-report-summary-pill">' +
          escapeHtml(items.length + " live matches scored") +
          "</span>" +
          "</div>"
        : "") +
      resultsReportMetricCardsMarkup(resolvedSummary.metrics || []) +
      "</article>";
    var aiMarkup = resultsReportAiMarkup(aiPayload, aiStatus);
    var cardsMarkup = cards
      .map(function (card) {
        return resultsReportCardMarkup(card);
      })
      .join("");
    var useAccordion = !!config.loggedIn;
    var reportInnerMarkup =
      summaryMarkup +
      aiMarkup +
      '<div class="sffc-cv-match-studio__results-report-grid">' +
      cardsMarkup +
      "</div>";
    var reportShellMarkup = useAccordion
      ? resultsReportAccordionMarkup({
          className: "sffc-cv-match-studio__results-report-accordion--shell",
          title: resolvedSummary.headline || "Your Match Report",
          eyebrow: resolvedSummary.eyebrow || "",
          copy:
            resolvedSummary.copy ||
            resolvedSummary.score_label ||
            "Open your full match report.",
          content: reportInnerMarkup,
          open: false,
        })
      : reportInnerMarkup;

    mount.hidden = false;
    mount.innerHTML =
      '<div class="sffc-cv-match-studio__results-report-shell">' +
      reportShellMarkup +
      "</div>";
  }

  function requestResultsReportAi(root, items) {
    var cvText = getCurrentCvTextForRoot(root);
    var report = root._cvMatchResultsReportPayload;
    var topMatches;
    var requestBody;
    var requestHash;

    if (!cvText || !report || !Array.isArray(items) || !items.length) {
      return;
    }

    topMatches = items.slice(0, 5).map(function (item) {
      return {
        roleTitle: item.roleTitle,
        company: item.company,
        location: item.location,
        sector: item.sector,
        seniority: item.seniority,
        score: item.score,
        salaryText: item.salaryText || "",
        salaryMin: Number(item.salaryMin || 0),
        salaryMax: Number(item.salaryMax || 0),
        salaryCurrency: item.salaryCurrency || "",
        keywords: item.keywords || [],
        reasons: item.reasons || [],
        warnings: item.warnings || [],
      };
    });

    requestHash = JSON.stringify({
      cvText: cvText.slice(0, 2000),
      topMatches: topMatches,
      report: report,
    });

    if (root._cvMatchResultsReportAiHash === requestHash) {
      return;
    }

    root._cvMatchResultsReportAiHash = requestHash;
    root._cvMatchResultsReportAiStatus = "loading";
    root._cvMatchResultsReportAiPayload = null;
    renderResultsReport(root, report, items);

    requestBody = new URLSearchParams();
    requestBody.append("action", "sffc_crm_get_match_report_ai");
    requestBody.append("nonce", config.nonce || "");
    requestBody.append("cv_text", cvText);
    requestBody.append("top_matches", JSON.stringify(topMatches));
    requestBody.append("report", JSON.stringify(report));

    fetch(config.ajaxUrl || "/wp-admin/admin-ajax.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
      },
      body: requestBody.toString(),
    })
      .then(parseAjaxJson)
      .then(function (payload) {
        root._cvMatchResultsReportAiStatus = "ready";
        root._cvMatchResultsReportAiPayload =
          payload && payload.success && payload.data && payload.data.analysis
            ? payload.data.analysis
            : null;
        renderResultsReport(root, report, items);
      })
      .catch(function () {
        root._cvMatchResultsReportAiStatus = "failed";
        root._cvMatchResultsReportAiPayload = null;
        renderResultsReport(root, report, items);
      });
  }

  function renderResults(root, items) {
    var body = $(root, "[data-cv-match-results-body]");
    var mobileResults = $(root, "[data-cv-match-results-mobile]");
    var count = $(root, "[data-cv-match-results-count]");
    var heading = $(root, "[data-cv-match-results-heading]");
    var cvText = getCurrentCvTextForRoot(root);
    root._cvMatchVisibleResults = items;
    root._cvMatchSelectedResults = [];

    if (!body || !count || !heading) {
      return;
    }

    var reportItems =
      root._cvMatchCustomSearchActive &&
      Array.isArray(root._cvMatchBaseResults) &&
      root._cvMatchBaseResults.length
        ? root._cvMatchBaseResults
        : items;
    var headingText = "";
    var emptyText = "";
    var customSummary = String(root._cvMatchCustomSearchSummary || "").trim();

    updateResultsStats(root, items);
    renderResultsReport(root, root._cvMatchResultsReportPayload, reportItems);
    if (reportItems.length && !root._cvMatchCustomSearchActive) {
      requestResultsReportAi(root, reportItems);
    }

    count.textContent =
      items.length + (items.length === 1 ? " role" : " roles");
    if (root._cvMatchCustomSearchActive) {
      headingText = customSummary
        ? "Controlled search: " + customSummary
        : "Controlled search results";
      emptyText = customSummary
        ? "No live roles matched " + customSummary + "."
        : "No live roles matched this controlled search.";
    } else {
      headingText = items.length
        ? "Your strongest live matches"
        : config.labels && config.labels.noMatches
        ? config.labels.noMatches
        : "No strong matches yet.";
      emptyText =
        config.labels && config.labels.noMatches
          ? config.labels.noMatches
          : "No strong matches yet.";
    }
    heading.textContent = headingText;

    if (!items.length) {
      updateResultsAccessLock(root, items);
      clearSelectedResults(root);
      body.innerHTML =
        '<tr><td colspan="7"><div class="sffc-cv-match-studio__empty">' +
        escapeHtml(emptyText) +
        "</div></td></tr>";
      if (mobileResults) {
        mobileResults.innerHTML =
          '<div class="sffc-cv-match-studio__empty sffc-cv-match-studio__empty--mobile">' +
          escapeHtml(emptyText) +
          "</div>";
      }
      return;
    }

    if (mobileResults) {
      mobileResults.innerHTML = items
        .map(function (item, index) {
          return mobileResultCardMarkup(item, index, cvText);
        })
        .join("");
    }

    body.innerHTML = items
      .map(function (item, index) {
        var openRoleLabel =
          config.labels && config.labels.openRole
            ? config.labels.openRole
            : "Open role";
        var quickViewLabel =
          config.labels && config.labels.quickView
            ? config.labels.quickView
            : "Quick view";
        var detailMarkup = [
          detailSectionMarkup("Why this matches", item.reasons, ""),
          detailSectionMarkup("What to watch", item.warnings, "is-warning"),
          detailSectionMarkup("What is still missing", item.gaps, "is-gap"),
        ].join("");

        if (!detailMarkup && item.skills.length) {
          detailMarkup = detailSectionMarkup(
            "Signals MENA Careers found",
            item.skills.slice(0, 6),
            ""
          );
        }

        return (
          "" +
          '<tr class="sffc-cv-match-studio__result-row" data-result-index="' +
          index +
          '">' +
          "<td>" +
          cellWithMobileLabel(
            "Match",
            '<div class="sffc-cv-match-studio__rank-cell">' +
              selectionCellMarkup(index) +
              '<span class="sffc-cv-match-studio__rank-label">#' +
              escapeHtml(String(index + 1)) +
              "</span>" +
              scoreCellMarkup(item, index) +
              "</div>"
          ) +
          "</td>" +
          "<td>" +
          cellWithMobileLabel("Role", roleCellMarkup(item)) +
          "</td>" +
          "<td>" +
          cellWithMobileLabel("Recruiter", recruiterCellMarkup(item)) +
          "</td>" +
          "<td>" +
          cellWithMobileLabel("Contact", recruiterContactCellMarkup(item)) +
          "</td>" +
          "<td>" +
          cellWithMobileLabel(
            "Top ATS Keywords",
            atsKeywordsCellMarkup(item, cvText)
          ) +
          "</td>" +
          "<td>" +
          cellWithMobileLabel("Add", smartApplyCellMarkup(index)) +
          "</td>" +
          "<td>" +
          cellWithMobileLabel(
            "Action",
            '<div class="sffc-cv-match-studio__action-cell"><a class="sffc-cv-match-studio__open-button sffc-cv-match-studio__open-button--listing" href="' +
              escapeHtml(item.permalink || "#") +
              '" target="_blank" rel="noopener noreferrer" data-open-role-title="' +
              escapeHtml(item.roleTitle || "Role") +
              '" data-open-role-href="' +
              escapeHtml(item.permalink || "#") +
              '" data-open-role-id="' +
              escapeHtml(String(item.jobsPostId || 0)) +
              '" data-open-role-wp-id="' +
              escapeHtml(String(item.wpPostId || 0)) +
              '" data-open-role-crm-id="' +
              escapeHtml(String(item.id || 0)) +
              '" data-open-role-location="' +
              escapeHtml(item.location || "") +
              '" data-open-role-sector="' +
              escapeHtml(item.sector || "") +
              '" data-open-role-seniority="' +
              escapeHtml(item.seniority || "") +
              '" data-open-role-keywords="' +
              escapeHtml((Array.isArray(item.keywords) ? item.keywords : []).join("|")) +
              '">' +
              escapeHtml(openRoleLabel) +
              '</a><button class="sffc-cv-match-studio__quick-view-button" type="button" data-open-role-quick-view="1" data-open-role-id="' +
              escapeHtml(String(item.jobsPostId || 0)) +
              '" data-open-role-wp-id="' +
              escapeHtml(String(item.wpPostId || 0)) +
              '" data-open-role-crm-id="' +
              escapeHtml(String(item.id || 0)) +
              '" data-open-role-title="' +
              escapeHtml(item.roleTitle || "Role") +
              '" data-open-role-href="' +
              escapeHtml(item.permalink || "#") +
              '" data-open-role-location="' +
              escapeHtml(item.location || "") +
              '" data-open-role-sector="' +
              escapeHtml(item.sector || "") +
              '" data-open-role-seniority="' +
              escapeHtml(item.seniority || "") +
              '" data-open-role-keywords="' +
              escapeHtml((Array.isArray(item.keywords) ? item.keywords : []).join("|")) +
              '">' +
              escapeHtml(quickViewLabel) +
              "</button></div>"
          ) +
          "</td>" +
          "</tr>" +
          '<tr class="sffc-cv-match-studio__detail-row" data-result-detail="' +
          index +
          '" hidden>' +
          '<td colspan="7">' +
          '<div class="sffc-cv-match-studio__detail-surface">' +
          detailMarkup +
          "</div>" +
          "</td>" +
          "</tr>"
        );
      })
      .join("");

    updateResultsAccessLock(root, items);
    syncResultsBulkBar(root);
  }

  function updateResultsAccessLock(root, items) {
    var tableWrap = $(root, ".sffc-cv-match-studio__table-wrap");
    var mobileWrap = $(root, ".sffc-cv-match-studio__mobile-results-wrap");
    var desktopLock = $(root, "[data-cv-match-results-lock]");
    var mobileLock = $(root, "[data-cv-match-results-mobile-lock]");
    var desktopLockLogos = $(root, "[data-cv-match-results-lock-logos]");
    var mobileLockLogos = $(root, "[data-cv-match-results-mobile-lock-logos]");
    var bulkBar = $(root, "[data-cv-match-results-bulkbar]");
    var hasItems = Array.isArray(items) && items.length > 0;
    var isLocked = hasItems && (!config.loggedIn || !config.hasPremiumAccess);
    var seenCompanies = {};
    var logoItems = [];
    var logoMarkup = "";

    if (tableWrap) {
      tableWrap.classList.toggle("is-locked", isLocked);
      tableWrap.setAttribute("data-results-locked", isLocked ? "true" : "false");
    }

    if (mobileWrap) {
      mobileWrap.classList.toggle("is-locked", isLocked);
      mobileWrap.setAttribute("data-results-locked", isLocked ? "true" : "false");
    }

    if (desktopLock) {
      desktopLock.hidden = !isLocked;
    }

    if (mobileLock) {
      mobileLock.hidden = !isLocked;
    }

    if (desktopLockLogos || mobileLockLogos) {
      if (hasItems) {
        logoItems = items
          .filter(function (item) {
            var companyKey = normalizeMatchText(item.company || item.roleTitle || "");
            if (!companyKey || seenCompanies[companyKey]) {
              return false;
            }
            seenCompanies[companyKey] = true;
            return true;
          })
          .slice(0, 5);

        logoMarkup = logoItems
          .map(function (item) {
            var label = item.company || item.roleTitle || "Company";
            if (item.companyLogo) {
              return (
                '<span class="sffc-cv-match-studio__results-upgrade-logo has-image">' +
                '<img src="' +
                escapeHtml(item.companyLogo) +
                '" alt="' +
                escapeHtml(label) +
                '">' +
                "</span>"
              );
            }

            return (
              '<span class="sffc-cv-match-studio__results-upgrade-logo">' +
              escapeHtml(initials(label).slice(0, 2)) +
              "</span>"
            );
          })
          .join("");
      }

      if (desktopLockLogos) {
        desktopLockLogos.innerHTML = logoMarkup;
      }

      if (mobileLockLogos) {
        mobileLockLogos.innerHTML = logoMarkup;
      }

      if (!hasItems) {
        if (desktopLockLogos) {
          desktopLockLogos.innerHTML = "";
        }
        if (mobileLockLogos) {
          mobileLockLogos.innerHTML = "";
        }
      }
    }

    if (isLocked) {
      clearSelectedResults(root);
      if (bulkBar) {
        bulkBar.hidden = true;
        bulkBar.classList.remove("is-visible");
      }
    }
  }

  function getSelectedResultIndexes(root) {
    return $all(root, "[data-cv-match-result-select]:checked")
      .map(function (input) {
        return Number(input.getAttribute("data-cv-match-result-select") || -1);
      })
      .filter(function (index) {
        return index >= 0;
      });
  }

  function getSelectedResults(root) {
    var visible = Array.isArray(root._cvMatchVisibleResults)
      ? root._cvMatchVisibleResults
      : [];

    return getSelectedResultIndexes(root)
      .map(function (index) {
        return visible[index] || null;
      })
      .filter(Boolean);
  }

  function syncResultsBulkBar(root) {
    var bulkBar = $(root, "[data-cv-match-results-bulkbar]");
    var countNode = $(root, "[data-cv-match-results-bulkcount]");
    var feedbackNode = $(root, "[data-cv-match-results-bulkfeedback]");
    var quickViewButton = $(root, "[data-cv-match-results-quick-view]");
    var outreachButton = $(root, "[data-cv-match-results-outreach]");
    var selected = getSelectedResults(root);
    var count = selected.length;

    if (!bulkBar) {
      return;
    }

    if (!count) {
      bulkBar.hidden = true;
      bulkBar.classList.remove("is-visible");
      if (countNode) {
        countNode.textContent = "0 roles selected";
      }
      if (feedbackNode) {
        feedbackNode.textContent =
          "Select roles to queue them for one-by-one recruiter outreach or quick view a single role in the workspace.";
      }
      if (quickViewButton) {
        quickViewButton.disabled = true;
      }
      if (outreachButton) {
        outreachButton.disabled = true;
      }
      return;
    }

    bulkBar.hidden = false;
    bulkBar.classList.add("is-visible");

    if (countNode) {
      countNode.textContent =
        count === 1 ? "1 role selected" : count + " roles selected";
    }
    if (feedbackNode) {
      feedbackNode.textContent =
        count === 1
          ? "Queue this role for recruiter outreach or quick view it in the workspace."
          : "Create a recruiter outreach queue from these roles or reduce to one role for quick view.";
    }
    if (quickViewButton) {
      quickViewButton.disabled = count !== 1;
    }
    if (outreachButton) {
      outreachButton.disabled = count < 1;
    }
  }

  function clearSelectedResults(root) {
    $all(root, "[data-cv-match-result-select]").forEach(function (input) {
      input.checked = false;
    });
    syncResultsBulkBar(root);
  }

  var openSmartApplyQueue = function (listId, memberId) {
    return getSmartApplyQueueDetails(listId).then(function (data) {
      var items = Array.isArray(data && data.jobs) ? data.jobs : [];
      var activeIndex = 0;

      if (!items.length) {
        throw new Error("No recruiter outreach items were added.");
      }

      if (memberId) {
        items.forEach(function (member, index) {
          if (Number(member && member.id ? member.id : 0) === Number(memberId)) {
            activeIndex = index;
          }
        });
      }

      smartApplySequenceState = {
        listId: Number(listId || 0),
        items: items,
        activeIndex: activeIndex,
      };
      updateSmartApplyQueueUi();
      openSmartApply(normalizeSmartApplyQueueMember(items[activeIndex]), {
        queueState: smartApplySequenceState,
        queueIndex: activeIndex,
      });
    });
  };

  function createOutreachQueueFromSelection(root) {
    var selected = getSelectedResults(root);
    var selectedCount = selected.length;
    var queueName;
    var requestBody;
    var crmNonce = config.crmNonce || config.nonce || "";
    var bulkFeedbackNode = $(root, "[data-cv-match-results-bulkfeedback]");
    var cvText = getCurrentCvTextForRoot(root);

    if (!selectedCount || !config.ajaxUrl || !crmNonce) {
      return Promise.resolve();
    }

    if (!config.loggedIn || !hasPremiumRecruiterAccess()) {
      smartApplySequenceState = {
        preview: true,
        listId: 0,
        items: selected.map(function (item) {
          return {
            id: 0,
            post_id: Number(item.jobsPostId || 0),
            crm_post_id: Number(item.id || 0),
            recruiter_id: Number(item.recruiterId || 0),
            role_title: String(item.roleTitle || "Role"),
            company: String(item.company || ""),
            location: String(item.location || ""),
            recruiter_name: String(item.recruiterName || ""),
            recruiter_title: String(item.recruiterTitle || ""),
            recruiter_email: String(item.recruiterEmail || ""),
            recruiter_linkedin: String(item.recruiterLinkedIn || ""),
            recruiter_firm: String(item.recruiterFirm || ""),
            reasons: Array.isArray(item.reasons) ? item.reasons : [],
            match_score: Number(item.score || 0),
          };
        }),
        activeIndex: 0,
      };
      updateSmartApplyQueueUi();
      openSmartApply(
        normalizeSmartApplyQueueMember(smartApplySequenceState.items[0]),
        {
          queueState: smartApplySequenceState,
          queueIndex: 0,
        }
      );
      return Promise.resolve();
    }

    if (!cvText) {
      setFeedback(
        root,
        "Upload or paste your CV before creating recruiter outreach emails.",
        true
      );
      return Promise.resolve();
    }

    queueName =
      "Recruiter Outreach Queue · " +
      new Date().toLocaleDateString("en-GB", {
        day: "2-digit",
        month: "short",
        year: "numeric",
      });

    requestBody = new window.FormData();
    requestBody.append("action", "sffc_crm_create_job_outreach_list");
    requestBody.append("nonce", crmNonce);
    requestBody.append("list_name", queueName);
    requestBody.append(
      "description",
      "Queued from CV Match Studio for one-by-one recruiter outreach."
    );
    requestBody.append("items", JSON.stringify(selected));

    return window
      .fetch(config.ajaxUrl, {
        method: "POST",
        credentials: "same-origin",
        body: requestBody,
      })
      .then(parseAjaxJson)
      .then(function (response) {
        if (!response || !response.success || !response.data) {
          throw new Error(
            (response && response.data && response.data.message) ||
              "Unable to create the recruiter outreach queue."
          );
        }

        clearSelectedResults(root);
        window.dispatchEvent(
          new window.CustomEvent("sffc:outreach-queue-focus", {
            detail: {
              listId: Number(response.data.list_id || 0),
            },
          })
        );
        if (bulkFeedbackNode) {
          bulkFeedbackNode.textContent =
            selectedCount === 1
              ? "1 role was added to Recruiter Outreach. Open the Recruiter Outreach tab when you want to work through it."
              : selectedCount +
                " roles were added to Recruiter Outreach. Open the Recruiter Outreach tab when you want to work through them.";
        }
        setFeedback(
          root,
          selectedCount === 1
            ? "1 role was added to Recruiter Outreach."
            : selectedCount + " roles were added to Recruiter Outreach.",
          false
        );
        return openSmartApplyQueue(Number(response.data.list_id || 0)).then(
          function () {
            return response;
          }
        );
      })
      .catch(function (error) {
        setFeedback(
          root,
          error && error.message
            ? error.message
            : "Unable to create the recruiter outreach queue right now.",
          true
        );
        return null;
      });
  }

  function buildScanner(root) {
    var progressFill = $(root, "[data-cv-match-progress-fill]");
    var progressValue = $(root, "[data-cv-match-progress-value]");
    var statusText = $(root, "[data-cv-match-scan-status]");
    var steps = $all(root, ".sffc-cv-match-studio__scan-step");
    var interval = null;
    var percent = 12;
    var stepIndex = 0;
    var seededPreview = Array.isArray(config.scanPreviewItems)
      ? config.scanPreviewItems
      : [];
    var statusLines = [
      "Reading structure and identifying role signals.",
      "Mapping your profile to live recruiter-led roles.",
      "Ranking strongest fits and recruiter context.",
    ];

    function paint() {
      if (progressFill) {
        progressFill.style.width = percent + "%";
      }
      if (progressValue) {
        progressValue.textContent = percent + "%";
      }
      if (statusText) {
        statusText.textContent =
          statusLines[stepIndex] || statusLines[statusLines.length - 1];
      }
      steps.forEach(function (step, index) {
        step.classList.toggle("is-active", index <= stepIndex);
      });
    }

    paint();
    renderScanPreview(root, seededPreview);

    interval = window.setInterval(function () {
      if (percent < 88) {
        percent += stepIndex === 0 ? 7 : 5;
      }
      if (percent > 88) {
        percent = 88;
      }
      if (stepIndex < steps.length - 1 && percent >= (stepIndex + 1) * 28) {
        stepIndex += 1;
      }
      paint();
    }, 520);

    return {
      updatePreview: function (items) {
        renderScanPreview(root, items);
      },
      complete: function () {
        window.clearInterval(interval);
        percent = 100;
        stepIndex = steps.length - 1;
        paint();
      },
      fail: function () {
        window.clearInterval(interval);
      },
    };
  }

  function parsePdf(file) {
    return loadExternalScript(config.pdfScriptUrl, "pdfjsLib")
      .then(function (pdfjs) {
        if (!pdfjs) {
          throw new Error("PDF parser is unavailable.");
        }

        if (config.pdfWorker) {
          pdfjs.GlobalWorkerOptions.workerSrc = config.pdfWorker;
        }

        return file.arrayBuffer().then(function (buffer) {
          return pdfjs.getDocument({ data: buffer }).promise;
        });
      })
      .then(function (pdf) {
        var pages = [];
        for (var i = 1; i <= pdf.numPages; i += 1) {
          pages.push(
            pdf.getPage(i).then(function (page) {
              return page.getTextContent().then(function (content) {
                return content.items
                  .map(function (item) {
                    return item.str || "";
                  })
                  .join(" ");
              });
            })
          );
        }

        return Promise.all(pages).then(function (parts) {
          return parts.join("\n").trim();
        });
      });
  }

  function parseDocx(file) {
    return loadExternalScript(config.mammothScriptUrl, "mammoth")
      .then(function (mammothLib) {
        if (!mammothLib) {
          throw new Error("DOCX parser is unavailable.");
        }

        return file.arrayBuffer().then(function (buffer) {
          return mammothLib.extractRawText({ arrayBuffer: buffer });
        });
      })
      .then(function (result) {
        return String(result && result.value ? result.value : "").trim();
      });
  }

  function parseTxt(file) {
    return file.text().then(function (content) {
      return String(content || "").trim();
    });
  }

  function parseFile(file) {
    if (!file) {
      return Promise.reject(new Error("No file selected."));
    }

    var name = String(file.name || "").toLowerCase();

    if (name.endsWith(".pdf")) {
      return parsePdf(file);
    }
    if (name.endsWith(".docx") || name.endsWith(".doc")) {
      return parseDocx(file);
    }
    if (name.endsWith(".txt")) {
      return parseTxt(file);
    }

    return Promise.reject(
      new Error(
        config.labels && config.labels.parseError
          ? config.labels.parseError
          : "Unsupported file type."
      )
    );
  }

  document.addEventListener("DOMContentLoaded", function () {
    $all(document, '[data-component="crm-cv-match-studio"], [data-component="crm-cv-match-job"], [data-component="crm-cv-match-pricing"], [data-component="crm-cv-match-job-posts"]').forEach(function (
      root
    ) {
      var componentType = root.getAttribute("data-component");
      startHeroTyping(root);
      initLandingPreview(root);
      if (componentType === "crm-cv-match-pricing") {
        return;
      }
      renderRecentRoles(root);
      var isStandaloneJob =
        componentType === "crm-cv-match-job";
      var mainPane = $(
        root,
        isStandaloneJob
          ? ".sffc-cv-match-job__main"
          : ".sffc-cv-match-studio__main"
      );
      var textarea = $(root, "[data-cv-match-input]");
      var fileInput = $(root, "[data-cv-match-file]");
      var runButton = $(root, "[data-cv-match-run]");
      var uploadTriggers = $all(root, "[data-cv-match-upload-trigger]");
      var cvSourceSelects = $all(root, "[data-cv-match-cv-source]");
      var expertCvReviewForm = $(root, "[data-cv-match-expert-cv-review-form]");
      var expertCvReviewUpload = $(
        root,
        "[data-cv-match-expert-cv-review-upload]"
      );
      var expertCvReviewUploadStatus = $(
        root,
        "[data-cv-match-expert-cv-review-upload-status]"
      );
      var expertCvReviewFeedback = $(
        root,
        "[data-cv-match-expert-cv-review-feedback]"
      );
      var expertCvReviewSubmit = $(
        root,
        "[data-cv-match-expert-cv-review-submit]"
      );
      var linkedinReviewForm = $(root, "[data-cv-match-linkedin-review-form]");
      var linkedinReviewFeedback = $(
        root,
        "[data-cv-match-linkedin-review-feedback]"
      );
      var linkedinReviewSubmit = $(
        root,
        "[data-cv-match-linkedin-review-submit]"
      );
      var resetButtons = $all(root, "[data-cv-match-reset]");
      var dropzone = $(root, "[data-cv-match-dropzone]");
      var fileStatus = $(root, "[data-cv-match-file-status]");
      var floatingShell = $(root, "[data-cv-match-floating-shell]");
      var floatingInput = $(root, "[data-cv-match-floating-input]");
      var floatingStatus = $(root, "[data-cv-match-floating-file-status]");
      var floatingRun = $(root, "[data-cv-match-floating-run]");
      var jobDock = $(root, "[data-cv-match-job-dock]");
      var floatingOpenButtons = $all(root, "[data-cv-match-floating-open]");
      var floatingCloseButtons = $all(root, "[data-cv-match-floating-close]");
      var floatingUploadTriggers = $all(
        root,
        "[data-cv-match-floating-upload-trigger]"
      );
      var searchNode = $(root, "[data-cv-match-search]");
      var sortNode = $(root, "[data-cv-match-sort]");
      var filterNode = $(root, "[data-cv-match-filter]");
      var controlledSearchForm = $(root, "[data-cv-match-controlled-search-form]");
      var controlledSearchBars = $(root, "[data-cv-match-controlled-bars]");
      var controlledSearchRole = $(root, "[data-cv-match-controlled-role]");
      var controlledSearchSeniority = $(root, "[data-cv-match-controlled-seniority]");
      var controlledSearchLocation = $(root, "[data-cv-match-controlled-location]");
      var controlledSearchSpecialisation = $(root, "[data-cv-match-controlled-specialisation]");
      var controlledSearchSubmit = $(root, "[data-cv-match-controlled-submit]");
      var controlledSearchReset = $(root, "[data-cv-match-controlled-reset]");
      var controlledSearchAdd = $(root, "[data-cv-match-controlled-add]");
      var controlledSearchFeedback = $(root, "[data-cv-match-controlled-feedback]");
      var recentSearchNode = $(root, "[data-cv-match-recent-search]");
      var smartApplyModal = $(root, "[data-cv-match-smart-apply]");
      var smartApplyMeta = $(root, "[data-smart-apply-meta]");
      var smartApplyFrom = $(root, "[data-smart-apply-from]");
      var smartApplyRecipient = $(root, "[data-smart-apply-recipient]");
      var smartApplyPackGrid = $(root, "[data-smart-apply-pack-grid]");
      var smartApplyEmailSubject = $(root, "[data-smart-apply-email-subject]");
      var smartApplyEmailBody = $(root, "[data-smart-apply-email-body]");
      var smartApplyStatus = $(root, "[data-smart-apply-status]");
      var smartApplyCopy = $(root, "[data-smart-apply-copy]");
      var smartApplyMailto = $(root, "[data-smart-apply-mailto]");
      var smartApplyLoader = $(root, "[data-smart-apply-loader]");
      var smartApplyLoaderTitle = $(root, "[data-smart-apply-loader-title]");
      var smartApplyLoaderStatus = $(root, "[data-smart-apply-loader-status]");
      var smartApplyLoaderBar = $(root, "[data-smart-apply-loader-bar]");
      var smartApplyLoaderSteps = $all(root, "[data-smart-apply-loader-step]");
      var smartApplyQueueProgress = $(root, "[data-smart-apply-queue-progress]");
      var smartApplyQueueMeta = $(root, "[data-smart-apply-queue-meta]");
      var smartApplyQueueNext = $(root, "[data-smart-apply-queue-next]");
      var smartApplyToolbarButtons = $all(root, "[data-smart-apply-toolbar]");
      var materialsModal = $(root, "[data-cv-match-materials-modal]");
      var materialsTitle = $(root, "[data-cv-match-materials-title]");
      var materialsSubtitle = $(root, "[data-cv-match-materials-subtitle]");
      var materialsTabs = $(root, "[data-cv-match-materials-tabs]");
      var materialsStatus = $(root, "[data-cv-match-materials-status]");
      var materialsActions = $(root, "[data-cv-match-materials-actions]");
      var materialsOutput = $(root, "[data-cv-match-materials-output]");
      var materialsCopy = $(root, "[data-cv-match-material-copy]");
      var materialsDownload = $(root, "[data-cv-match-material-download]");
      var recruiterModal = $(root, "[data-cv-match-recruiter-modal]");
      var recruiterModalBody = $(root, "[data-cv-match-recruiter-body]");
      var recruiterModalLoading = $(root, "[data-cv-match-recruiter-loading]");
      var recruiterModalError = $(root, "[data-cv-match-recruiter-error]");
      var messagesModal = $(root, "[data-cv-match-messages-modal]");
      var messagesDialog = $(root, "[data-cv-match-messages-dialog]");
      var supportModal = $(root, "[data-cv-match-support-modal]");
      var supportForm = $(root, "[data-cv-match-support-form]");
      var supportFeedback = $(root, "[data-cv-match-support-feedback]");
      var supportSubmit = $(root, "[data-cv-match-support-submit]");
      var customListDropdown = $(root, "[data-cv-match-custom-list-dropdown]");
      var customListForm = $(root, "[data-cv-match-custom-list-form]");
      var customListFeedback = $(root, "[data-cv-match-custom-list-feedback]");
      var customListSubmit = $(root, "[data-cv-match-custom-list-submit]");
      var dailyScanDropdown = $(root, "[data-cv-match-daily-scan-dropdown]");
      var dailyScanForm = $(root, "[data-cv-match-daily-scan-form]");
      var dailyScanFeedback = $(root, "[data-cv-match-daily-scan-feedback]");
      var dailyScanSubmit = $(root, "[data-cv-match-daily-scan-submit]");
      var emailListModal = $(root, "[data-cv-match-email-list-modal]");
      var emailListForm = $(root, "[data-cv-match-email-list-form]");
      var emailListFeedback = $(root, "[data-cv-match-email-list-feedback]");
      var emailListSubmit = $(root, "[data-cv-match-email-list-submit]");
      var emailListSummary = $(root, "[data-cv-match-email-list-summary]");
      var cvOnboardingModal = $(root, "[data-cv-match-cv-onboarding-modal]");
      var cvOnboardingDialog = $(
        root,
        ".sffc-cv-match-studio__cv-onboarding-dialog"
      );
      var cvOnboardingClose = $(
        root,
        "[data-cv-match-cv-onboarding-close]"
      );
      var cvOnboardingInput = $(root, "[data-cv-match-cv-onboarding-input]");
      var cvOnboardingUpload = $(root, "[data-cv-match-cv-onboarding-upload]");
      var cvOnboardingStatus = $(root, "[data-cv-match-cv-onboarding-status]");
      var cvOnboardingFeedback = $(
        root,
        "[data-cv-match-cv-onboarding-feedback]"
      );
      var cvOnboardingSubmit = $(root, "[data-cv-match-cv-onboarding-submit]");
      var welcomeModal = $(root, "[data-cv-match-welcome-modal]");
      var welcomeDialog = $(root, ".sffc-cv-match-studio__welcome-dialog");
      var welcomeCvCard = $(root, "[data-cv-match-welcome-cv-card]");
      var welcomeCvUpload = $(root, "[data-cv-match-welcome-upload]");
      var welcomeCvUploadStatus = $(
        root,
        "[data-cv-match-welcome-upload-status]"
      );
      var preferredIndustryInputs = $all(
        root,
        "[data-cv-match-preferred-industry]"
      );
      var preferredLocationInputs = $all(
        root,
        "[data-cv-match-preferred-location]"
      );
      var welcomeNewsletterInputs = $all(
        root,
        "[data-cv-match-welcome-newsletter]"
      );
      var welcomeProceedButton = $(root, "[data-cv-match-welcome-proceed]");
      var welcomePlansPanel = $(root, "[data-cv-match-welcome-plans]");
      var welcomeOverview = $(root, "[data-cv-match-welcome-overview]");
      var welcomeCheckoutPanel = $(root, "[data-cv-match-welcome-checkout]");
      var tourOverlay = $(root, "[data-cv-match-tour-overlay]");
      var tourPopover = $(root, "[data-cv-match-tour-popover]");
      var tourTitle = $(root, "[data-cv-match-tour-title]");
      var tourCopy = $(root, "[data-cv-match-tour-copy]");
      var tourProgress = $(root, "[data-cv-match-tour-progress]");
      var tourHint = $(root, "[data-cv-match-tour-hint]");
      var tourStepLabel = $(root, "[data-cv-match-tour-step]");
      var sidebarToggles = $all(root, "[data-cv-match-sidebar-toggle]");
      var sidebarToggle = sidebarToggles.length ? sidebarToggles[0] : null;
      var mobileNavToggle = $(root, "[data-cv-match-mobile-nav-toggle]");
      var mobileNavClose = $(root, "[data-cv-match-mobile-nav-close]");
      var mainUtilities = $all(root, "[data-cv-match-main-utility]");
      var mainUtility = mainUtilities.length ? mainUtilities[0] : null;
      var commandBar = $(root, "[data-cv-match-command-bar]");
      var mobileSearchToggle = $(root, "[data-cv-match-mobile-search-toggle]");
      var mobileSearchClose = $(root, "[data-cv-match-mobile-search-close]");
      var commandInput = $(root, "[data-cv-match-command-input]");
      var commandSubmit = $(root, "[data-cv-match-command-submit]");
      var commandUpload = $(root, "[data-cv-match-command-upload]");
      var commandStatus = $(root, "[data-cv-match-command-status]");
      var recommendedSearchForms = $all(root, "[data-cv-match-recommended-search-form]");
      var recommendedSearchInputs = $all(root, "[data-cv-match-recommended-search-input]");
      var recommendedSearchTimer = 0;
      var careerReportShell = $(root, "[data-cv-match-career-report-shell]");
      var careerReportBody = $(root, "[data-cv-match-career-report-body]");
      var careerReportLoading = $(root, "[data-cv-match-career-report-loading]");
      var careerReportError = $(root, "[data-cv-match-career-report-error]");
      var interviewForm = $(root, "[data-cv-match-interview-form]");
      var interviewFeedback = $(root, "[data-cv-match-interview-feedback]");
      var interviewLoader = $(root, "[data-cv-match-interview-loader]");
      var interviewPlaceholder = $(root, "[data-cv-match-interview-placeholder]");
      var interviewResult = $(root, "[data-cv-match-interview-result]");
      var interviewSubmit = interviewForm
        ? $(interviewForm, 'button[type="submit"]')
        : null;
      var salaryCheckerForm = $(root, "[data-cv-match-salary-checker-form]");
      var salaryCheckerResult = $(root, "[data-cv-match-salary-result]");
      var salaryCheckerFeedback = $(root, "[data-cv-match-salary-feedback]");
      var salaryCheckerSubmit = $(root, "[data-cv-match-salary-submit]");
      var salaryCheckerPrint = $(root, "[data-cv-match-salary-print]");
      var salaryCheckerTimer = 0;
      var salaryCheckerPrintRoot = null;
      var activeScanner = null;
      var activeResults = [];
      var baseResults = [];
      var activeCvText = String(
        (textarea && textarea.value) ||
          (floatingInput && floatingInput.value) ||
          config.initialCvText ||
          ""
      ).trim();
      var activeCvSource = String(config.initialCvSource || "").trim();
      var activePreferredIndustry = "";
      var activeJobItem = null;
      var smartApplyActiveItem = null;
      var activeMaterialsResource = null;
      var activeMaterialsResources = [];
      var materialsPackCache = {};
      var recruiterProfileCache = {};
      var smartApplyDraftCache = {};
      var smartApplySequenceState = null;
      var pendingSmartApplyAfterCv = null;
      var cvPersistTimer = null;
      var persistedCvHash = activeCvText ? hashText(activeCvText) : "";
      var pendingCvPersistHash = "";
      var pendingCvPersistPromise = null;
      var cvOnboardingCanClose = false;
      var materialsLoaderTimers = [];
      var materialsLoaderCountdownTimer = null;
      var smartApplyLoaderTimers = [];
      var smartApplyLoaderProgressTimer = null;
      var interviewLoaderTimer = null;
      var interviewLoaderProgress = 0;
      var interviewLoaderStartedAt = 0;
      var onboardingSeenPromise = null;
      var careerReportPromise = null;
      var careerReportRequestVersion = 0;
      var marketReportRequestPending = false;
      var onboardingSteps =
        config.onboarding && Array.isArray(config.onboarding.steps)
          ? config.onboarding.steps.slice()
          : [];
      var onboardingState = {
        active: false,
        index: -1,
        target: null,
      };
      var defaultState = String(
        root.getAttribute("data-cv-match-default-state") ||
          (isStandaloneJob ? "job" : "landing")
      ).trim() || (isStandaloneJob ? "job" : "landing");

      function normalizePreferredIndustry(value) {
        var normalized = String(value || "")
          .toLowerCase()
          .replace(/[-_/&]+/g, " ")
          .replace(/\s+/g, " ")
          .trim();
        var map = {
          ib: "investment_banking",
          "investment bank": "investment_banking",
          "investment banking": "investment_banking",
          pe: "private_equity",
          "finance": "private_equity",
          am: "asset_management",
          "asset management": "asset_management",
          "asset mgmt": "asset_management",
          "asset mgt": "asset_management",
          "hedge fund": "hedge_fund",
          "hedge funds": "hedge_fund",
          finance: "finance",
          consulting: "consulting",
          "strategy consulting": "consulting",
          other: "other",
        };

        if (value === "investment_banking" || value === "private_equity" || value === "asset_management" || value === "hedge_fund") {
          return value;
        }

        return map[normalized] || "";
      }

      function readPreferredIndustryStorage() {
        try {
          return normalizePreferredIndustry(
            window.localStorage.getItem(PREFERRED_INDUSTRY_KEY) || ""
          );
        } catch (error) {
          return "";
        }
      }

      function persistPreferredIndustry(value) {
        var normalized = normalizePreferredIndustry(value);
        if (!normalized) {
          return;
        }

        activePreferredIndustry = normalized;
        try {
          window.localStorage.setItem(PREFERRED_INDUSTRY_KEY, normalized);
        } catch (error) {
          return;
        }
      }

      function getCheckedPreferredIndustry() {
        var checked = preferredIndustryInputs.filter(function (input) {
          return input && input.checked;
        })[0];

        return normalizePreferredIndustry(checked ? checked.value : "");
      }

      function syncPreferredIndustryInputs(value) {
        var normalized = normalizePreferredIndustry(value);
        if (!normalized) {
          return;
        }

        activePreferredIndustry = normalized;
        preferredIndustryInputs.forEach(function (input) {
          input.checked = normalizePreferredIndustry(input.value) === normalized;
        });
      }

      function getSelectedPreferredIndustry() {
        return (
          getCheckedPreferredIndustry() ||
          activePreferredIndustry ||
          readPreferredIndustryStorage() ||
          normalizePreferredIndustry(config.initialPreferredIndustry || "")
        );
      }

      function handlePreferredIndustryChange(value) {
        var normalized = normalizePreferredIndustry(value);
        if (!normalized) {
          return;
        }

        syncPreferredIndustryInputs(normalized);
        persistPreferredIndustry(normalized);
        loadJobsMailboxSearchResults(root._cvMatchJobsMailboxSearchQuery || "", {
          keepActive: true,
        });
      }

      syncPreferredIndustryInputs(
        normalizePreferredIndustry(config.initialPreferredIndustry || "") ||
          readPreferredIndustryStorage() ||
          getCheckedPreferredIndustry()
      );

      function normalizePreferredLocation(value) {
        var normalized = String(value || "")
          .toLowerCase()
          .replace(/[-_/&]+/g, " ")
          .replace(/\s+/g, " ")
          .trim();
        var map = {
          ksa: "saudi_arabia",
          saudi: "saudi_arabia",
          "saudi arabia": "saudi_arabia",
          "saudi_arabia": "saudi_arabia",
          uae: "uae",
          "u a e": "uae",
          "united arab emirates": "uae",
          bahrain: "bahrain",
          qatar: "qatar",
          other: "other",
        };

        return map[normalized] || "";
      }

      function getSelectedPreferredLocation() {
        var checked = preferredLocationInputs.filter(function (input) {
          return input && input.checked;
        })[0];

        return (
          normalizePreferredLocation(checked ? checked.value : "") ||
          normalizePreferredLocation(config.initialPreferredLocation || "")
        );
      }

      function getSelectedWelcomeNewsletterIds() {
        return welcomeNewsletterInputs
          .filter(function (input) {
            return input && input.checked;
          })
          .map(function (input) {
            return String(input.value || "").trim();
          })
          .filter(Boolean);
      }

      function syncWelcomeOptionStates() {
        preferredLocationInputs.forEach(function (input) {
          var option = input.closest(".sffc-cv-match-studio__welcome-industry-option");
          if (option) {
            option.classList.toggle("is-active", !!input.checked);
          }
        });

        welcomeNewsletterInputs.forEach(function (input) {
          var option = input.closest(".sffc-cv-match-studio__welcome-industry-option");
          if (option) {
            option.classList.toggle("is-active", !!input.checked);
          }
        });
      }

      function syncWelcomeProceedButton() {
        if (!welcomeProceedButton) {
          return;
        }

        welcomeProceedButton.hidden = false;
        welcomeProceedButton.textContent = "Start Tour";
      }

      function refreshWelcomeCheckoutPaymentUI(container) {
        if (!container) {
          return;
        }

        window.setTimeout(function () {
          var selectedGateway =
            container.querySelector(
              '.mepr-payment-method input[type="radio"]:checked, input[type="radio"][name*="gateway"]:checked, input[type="radio"][name*="payment_method"]:checked'
            ) ||
            container.querySelector(
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

      syncWelcomeOptionStates();
      syncWelcomeProceedButton();

      function syncStandaloneJobDock() {
        if (!isStandaloneJob || !jobDock || !mainPane) {
          return;
        }

        if (window.innerWidth <= 780) {
          jobDock.style.setProperty("--cv-match-job-dock-left", "50vw");
          jobDock.style.setProperty("--cv-match-job-dock-width", "calc(100vw - 24px)");
          return;
        }

        var rect = mainPane.getBoundingClientRect();
        var viewportWidth = window.innerWidth || document.documentElement.clientWidth || rect.width;
        var width = Math.min(1120, Math.max(640, rect.width - 36));
        width = Math.min(width, viewportWidth - 48);
        var left = rect.left + rect.width / 2;

        jobDock.style.setProperty(
          "--cv-match-job-dock-left",
          Math.round(left) + "px"
        );
        jobDock.style.setProperty(
          "--cv-match-job-dock-width",
          Math.round(width) + "px"
        );
      }

      function hoistOverlay(node, target) {
        if (!node || node.dataset.overlayHoisted === "1") {
          return;
        }

        (target || root).appendChild(node);
        node.dataset.overlayHoisted = "1";
      }

      hoistOverlay(smartApplyModal);
      hoistOverlay(materialsModal);
      hoistOverlay(recruiterModal, mainPane);
      hoistOverlay(messagesModal);
      hoistOverlay(supportModal);
      hoistOverlay(cvOnboardingModal);
      hoistOverlay(welcomeModal);
      hoistOverlay(tourOverlay);

      function setMobileNavOpen(open) {
        root.classList.toggle("is-mobile-nav-open", !!open);
        if (mobileNavToggle) {
          mobileNavToggle.setAttribute(
            "aria-expanded",
            open ? "true" : "false"
          );
        }
        if (mobileNavClose) {
          mobileNavClose.hidden = !open;
        }
        if (open) {
          setMobileCommandOpen(false);
        }
        syncStandaloneJobDock();
      }

      function setMobileCommandOpen(open) {
        var shouldOpen = !!open && isMobileNavViewport();
        root.classList.toggle("is-mobile-command-open", shouldOpen);

        if (mainUtility) {
          mainUtility.classList.toggle("is-mobile-command-open", shouldOpen);
        }

        if (mobileSearchToggle) {
          mobileSearchToggle.setAttribute(
            "aria-expanded",
            shouldOpen ? "true" : "false"
          );
        }

        if (mobileSearchClose) {
          mobileSearchClose.setAttribute(
            "aria-expanded",
            shouldOpen ? "true" : "false"
          );
        }

        if (shouldOpen) {
          closeMainPanels();
          setMobileNavOpen(false);
          window.requestAnimationFrame(function () {
            if (commandInput) {
              commandInput.focus();
            } else if (recommendedSearchInputs.length) {
              recommendedSearchInputs[0].focus();
            }
          });
        }
      }

      root._cvMatchSetMobileCommandOpen = setMobileCommandOpen;

      function getMainUtilityScope(node) {
        if (node && typeof node.closest === "function") {
          var scopedUtility = node.closest("[data-cv-match-main-utility]");
          if (scopedUtility && root.contains(scopedUtility)) {
            return scopedUtility;
          }
        }

        return mainUtility;
      }

      function getMainUtilityScopes() {
        var scopes = $all(root, "[data-cv-match-main-utility]");
        return scopes.length ? scopes : mainUtility ? [mainUtility] : [];
      }

      function closeMainPanels(scope) {
        var scopes = scope ? [scope] : getMainUtilityScopes();
        if (!scopes.length) {
          return;
        }

        scopes.forEach(function (utilityScope) {
          $all(utilityScope, "[data-cv-match-main-panel]").forEach(function (
            panel
          ) {
            panel.hidden = true;
          });
          $all(utilityScope, "[data-cv-match-main-panel-trigger]").forEach(
            function (trigger) {
              trigger.setAttribute("aria-expanded", "false");
            }
          );
        });
      }

      root._cvMatchCloseMainPanels = closeMainPanels;

      function toggleMainPanel(name, scopeNode) {
        var utilityScope = getMainUtilityScope(scopeNode);

        if (!utilityScope || !name) {
          return;
        }

        var panel = $(
          utilityScope,
          '[data-cv-match-main-panel="' + name + '"]'
        );
        var trigger = $(
          utilityScope,
          '[data-cv-match-main-panel-trigger="' + name + '"]'
        );
        var willOpen = !!(panel && panel.hidden);

        closeMainPanels();

        if (panel && trigger && willOpen) {
          panel.hidden = false;
          trigger.setAttribute("aria-expanded", "true");
        }
      }

      function openMainPanel(name, scopeNode) {
        var utilityScope = getMainUtilityScope(scopeNode);

        if (!utilityScope || !name) {
          return;
        }

        var panel = $(
          utilityScope,
          '[data-cv-match-main-panel="' + name + '"]'
        );
        var trigger = $(
          utilityScope,
          '[data-cv-match-main-panel-trigger="' + name + '"]'
        );

        closeMainPanels();

        if (panel) {
          panel.hidden = false;
        }
        if (trigger) {
          trigger.setAttribute("aria-expanded", "true");
        }
      }

      function updateUtilityBadge(type, count) {
        var nextCount = Math.max(0, parseInt(count, 10) || 0);
        if (!type) {
          return;
        }

        getMainUtilityScopes().forEach(function (utilityScope) {
          var trigger = $(
            utilityScope,
            '[data-cv-match-main-panel-trigger="' + type + '"]'
          );
          var badge;

          if (!trigger) {
            return;
          }

          badge = $(
            trigger,
            ".sffc-cv-match-studio__utility-badge, .sffc-crm-dashboard-app-topbar-badge"
          );
          trigger.classList.toggle("has-badge", nextCount > 0);

          if (nextCount <= 0) {
            $all(
              trigger,
              ".sffc-cv-match-studio__utility-badge, .sffc-crm-dashboard-app-topbar-badge"
            ).forEach(function (existingBadge) {
              if (existingBadge.parentNode) {
                existingBadge.parentNode.removeChild(existingBadge);
              }
            });
            return;
          }

          if (!badge) {
            badge = document.createElement("span");
            badge.className =
              "sffc-crm-dashboard-app-topbar-badge sffc-cv-match-studio__utility-badge";
            trigger.appendChild(badge);
          }

          badge.textContent = String(Math.min(99, nextCount));
        });
      }

      function normalizePrivateEquityNotificationText(text, context) {
        var value = String(text || "").trim();
        var lower = value.toLowerCase();

        if (!value) {
          return context === "title"
            ? "Private equity role match"
            : "A private equity role match is ready in your MENA Careers workspace.";
        }

        if (context === "title") {
          if (
            lower.indexOf("new matching role added") !== -1 ||
            lower.indexOf("new finance match added") !== -1 ||
            lower.indexOf("new finance match added") !== -1
          ) {
            return "New private equity role match";
          }
          if (lower.indexOf("new role added") !== -1) {
            return "New private equity role added";
          }
          if (lower.indexOf("role alert") !== -1) {
            return "Private equity role alert";
          }
          if (
            lower.indexOf("role") === -1 &&
            lower.indexOf("job") === -1 &&
            lower.indexOf("recruiter") === -1 &&
            lower.indexOf("application") === -1
          ) {
            return "Private equity role match: " + value;
          }
          return value;
        }

        return value
          .replace(/finance role/gi, "private equity role")
          .replace(/finance match/gi, "private equity role match")
          .replace(/was just added to the platform/gi, "was just added to your private equity workspace")
          .replace(/role alerts/gi, "private equity role alerts")
          .replace(/career alerts/gi, "private equity career alerts")
          .replace(/saved roles/gi, "saved private equity roles")
          .replace(/application updates/gi, "private equity application updates");
      }

	      function renderCvMatchNotificationItems(notifications, unreadCount) {
        var panel = $(
          root,
          '[data-cv-match-main-panel="notifications"]'
        );
        var list = panel
          ? $(".sffc-cv-match-studio__main-panel-list", panel)
          : null;
        var countLabel = panel
          ? $(".sffc-cv-match-studio__main-panel-head span", panel)
          : null;

        if (!list) {
          return;
        }

        if (countLabel) {
          countLabel.textContent =
            (parseInt(unreadCount, 10) || 0) === 1
              ? "1 unread job match update"
              : String(parseInt(unreadCount, 10) || 0) + " unread job match updates";
        }

        if (!Array.isArray(notifications) || !notifications.length) {
          list.innerHTML =
            '<div class="sffc-cv-match-studio__main-panel-empty">No private equity role alerts yet. Job matches, recruiter activity, and application updates will show here.</div>';
          return;
        }

        list.innerHTML = notifications
          .slice(0, 4)
          .map(function (notification) {
            var actionUrl = notification.action_url || "";
            var isUnread = !notification.is_read;
            var title = normalizePrivateEquityNotificationText(
              notification.title || "",
              "title"
            );
            var message = normalizePrivateEquityNotificationText(
              notification.message || "",
              "message"
            );
            var attrs =
              ' data-cv-match-notification-item data-cv-match-notification-id="' +
              escapeHtml(notification.id || "") +
              '" data-cv-match-notification-action="' +
              escapeHtml(actionUrl) +
              '"';
            if (actionUrl || isUnread) {
              attrs += ' tabindex="0" role="button"';
            }

            return (
              '<article class="sffc-cv-match-studio__main-notification-item' +
              (isUnread ? " is-unread" : "") +
              '"' +
              attrs +
              ">" +
              '<div class="sffc-cv-match-studio__main-notification-copy">' +
              "<strong>" +
              escapeHtml(title) +
              "</strong>" +
              "<p>" +
              escapeHtml(message) +
              "</p>" +
              "<span>" +
              escapeHtml(relativeTime(notification.created_at || "")) +
              " ago</span>" +
              "</div>" +
              (actionUrl
                ? '<span class="sffc-cv-match-studio__main-panel-link">Open</span>'
                : "") +
              "</article>"
            );
          })
	          .join("");
	      }

	      var cvMatchNotificationsInFlight = null;
	      var cvMatchNotificationPollingStarted = false;

	      function refreshCvMatchNotifications(silent) {
	        var body;

	        if (!config.loggedIn || !config.ajaxUrl || !config.accountNonce) {
	          return Promise.resolve();
	        }

	        if (cvMatchNotificationsInFlight) {
	          return cvMatchNotificationsInFlight;
	        }

	        body = new window.FormData();
	        body.append("action", "sffc_crm_reddit_dashboard_notifications");
	        body.append("nonce", config.accountNonce);
	        body.append("current_url", window.location.href || "");

	        cvMatchNotificationsInFlight = window
	          .fetch(config.ajaxUrl, {
	            method: "POST",
	            credentials: "same-origin",
	            body: body,
          })
          .then(parseAjaxJson)
          .then(function (payload) {
            if (!payload || payload.success !== true || !payload.data) {
              return null;
            }

            updateUtilityBadge("notifications", payload.data.unread_count || 0);
            if (Array.isArray(payload.data.notifications)) {
              renderCvMatchNotificationItems(
                payload.data.notifications,
                payload.data.unread_count || 0
              );
            }

            return payload.data;
          })
          .catch(function (error) {
	            if (!silent) {
	              throw error;
	            }
	            return null;
	          })
	          .finally(function () {
	            cvMatchNotificationsInFlight = null;
	          });

	        return cvMatchNotificationsInFlight;
	      }

	      function startCvMatchNotificationPolling() {
	        if (!config.loggedIn || !config.ajaxUrl || !config.accountNonce) {
	          return;
	        }

	        if (cvMatchNotificationPollingStarted) {
	          return;
	        }

	        cvMatchNotificationPollingStarted = true;

	        window.setTimeout(function () {
	          refreshCvMatchNotifications(true);
        }, 8000);

        window.setInterval(function () {
          if (document.hidden) {
            return;
          }
          refreshCvMatchNotifications(true);
        }, 60000);

        document.addEventListener("visibilitychange", function () {
          if (!document.hidden) {
            refreshCvMatchNotifications(true);
          }
        });
      }

      function setCareerReportLoading(isLoading) {
        if (!careerReportLoading) {
          return;
        }

        careerReportLoading.hidden = !isLoading;
      }

      function setCareerReportError(message) {
        if (!careerReportError) {
          return;
        }

        if (!message) {
          careerReportError.hidden = true;
          careerReportError.textContent = "";
          return;
        }

        careerReportError.hidden = false;
        careerReportError.textContent = String(message);
      }

      function setCareerReportFeedback(message, isError) {
        if (!careerReportBody) {
          return;
        }

        var feedbackNode = careerReportBody.querySelector(
          "[data-cv-match-market-report-feedback]"
        );

        if (!feedbackNode) {
          return;
        }

        if (!message) {
          feedbackNode.hidden = true;
          feedbackNode.textContent = "";
          feedbackNode.classList.remove("is-error", "is-success");
          return;
        }

        feedbackNode.hidden = false;
        feedbackNode.textContent = String(message);
        feedbackNode.classList.toggle("is-error", !!isError);
        feedbackNode.classList.toggle("is-success", !isError);
      }

      function isCareerReportActive() {
        return (
          String(root.getAttribute("data-cv-match-view") || "").trim() ===
          "career-report"
        );
      }

      function invalidateCareerReport() {
        careerReportRequestVersion += 1;
        careerReportPromise = null;
        if (careerReportBody) {
          careerReportBody.innerHTML = "";
        }
        setCareerReportLoading(false);
        setCareerReportError("");
        setCareerReportFeedback("", false);
      }

      function loadCareerReport(force) {
        var requestVersion = careerReportRequestVersion;

        if (!careerReportShell || !config.loggedIn) {
          return Promise.resolve();
        }

        if (!force && careerReportBody && careerReportBody.innerHTML.trim()) {
          return Promise.resolve();
        }

        if (careerReportPromise) {
          return careerReportPromise;
        }

        setCareerReportError("");
        setCareerReportLoading(true);

        var formData = new window.FormData();
        formData.append("action", "sffc_load_cv_match_career_report");
        formData.append("nonce", config.nonce || "");
        if (force) {
          formData.append("force", "1");
        }

        careerReportPromise = window
          .fetch(config.ajaxUrl || "/wp-admin/admin-ajax.php", {
            method: "POST",
            body: formData,
            credentials: "same-origin",
          })
          .then(parseAjaxJson)
          .then(function (payload) {
            if (requestVersion !== careerReportRequestVersion) {
              return;
            }

            if (!payload || !payload.success || !payload.data) {
              throw new Error(
                (payload && payload.data && payload.data.message) ||
                  (config.labels && config.labels.careerReportError) ||
                  "We could not load your career report right now."
              );
            }

            if (careerReportBody) {
              careerReportBody.innerHTML = String(payload.data.html || "");
            }

            setCareerReportLoading(false);
            setCareerReportError("");
          })
          .catch(function (error) {
            if (requestVersion !== careerReportRequestVersion) {
              return;
            }

            setCareerReportLoading(false);
            setCareerReportError(
              error && error.message
                ? error.message
                : (config.labels && config.labels.careerReportError) ||
                    "We could not load your career report right now."
            );
          })
          .finally(function () {
            if (requestVersion === careerReportRequestVersion) {
              careerReportPromise = null;
            }
          });

        return careerReportPromise;
      }

      function requestCareerReportMarketReport(button) {
        if (!button || marketReportRequestPending) {
          return;
        }

        marketReportRequestPending = true;
        button.disabled = true;
        setCareerReportFeedback(
          (config.labels && config.labels.marketReportSending) ||
            "Sending market report request...",
          false
        );

        var formData = new window.FormData();
        formData.append("action", "sffc_cv_match_request_market_report");
        formData.append("nonce", config.nonce || "");

        window
          .fetch(config.ajaxUrl || "/wp-admin/admin-ajax.php", {
            method: "POST",
            body: formData,
            credentials: "same-origin",
          })
          .then(parseAjaxJson)
          .then(function (payload) {
            if (!payload || !payload.success) {
              throw new Error(
                (payload && payload.data && payload.data.message) ||
                  (config.labels && config.labels.marketReportError) ||
                  "We could not send your market report request right now."
              );
            }

            setCareerReportFeedback(
              (payload.data && payload.data.message) ||
                "Market report request sent.",
              false
            );
          })
          .catch(function (error) {
            setCareerReportFeedback(
              error && error.message
                ? error.message
                : (config.labels && config.labels.marketReportError) ||
                    "We could not send your market report request right now.",
              true
            );
          })
          .finally(function () {
            marketReportRequestPending = false;
            button.disabled = false;
          });
      }

      function closeMessagesModal() {
        if (!messagesModal) {
          return;
        }

        messagesModal.hidden = true;
        messagesModal.setAttribute("aria-hidden", "true");
        root.classList.remove("is-messages-modal-open");
      }

      function openMessagesModalWithMarkup(markup, unreadCount) {
        if (!messagesModal || !messagesDialog) {
          return;
        }

        messagesDialog.innerHTML = String(markup || "");
        syncInboxModalState(messagesDialog);
        messagesModal.hidden = false;
        messagesModal.setAttribute("aria-hidden", "false");
        root.classList.add("is-messages-modal-open");
        updateUtilityBadge("messages", unreadCount);
      }

      function syncInboxModalState(container) {
        var modalCard = container
          ? container.closest(".sffc-cv-match-studio__main-panel-surface--messages") ||
            (container.classList &&
            container.classList.contains("sffc-cv-match-studio__main-panel-surface--messages")
              ? container
              : null)
          : null;
        var activeChip;
        var activeTab;
        var searchInput;
        var query;
        var messagePanel;
        var messageDetail;
        var visibleMessageCount = 0;

        if (!modalCard) {
          return;
        }

        activeChip = modalCard.querySelector("[data-dashboard-inbox-tab].is-active");
        activeTab = activeChip
          ? String(activeChip.getAttribute("data-dashboard-inbox-tab") || "inbox")
          : "inbox";
        searchInput = modalCard.querySelector("[data-dashboard-inbox-search]");
        query = String(searchInput && searchInput.value ? searchInput.value : "")
          .trim()
          .toLowerCase();

        messagePanel = modalCard.querySelector('[data-dashboard-inbox-panel="messages"]');
        messageDetail = modalCard.querySelector('[data-dashboard-inbox-detail="messages"]');

        modalCard.setAttribute("data-dashboard-inbox-active-tab", activeTab);

        if (messagePanel) {
          messagePanel.hidden = false;
        }
        if (messageDetail) {
          messageDetail.hidden = false;
        }

        $all(modalCard, "[data-dashboard-inbox-open]").forEach(function (thread) {
          var haystack = String(
            thread.getAttribute("data-dashboard-inbox-label") || ""
          ).toLowerCase();
          var isUnread =
            String(thread.getAttribute("data-dashboard-inbox-unread") || "0") === "1";
          var visible =
            (activeTab !== "unread" || isUnread) &&
            (query === "" || haystack.indexOf(query) !== -1);

          thread.hidden = !visible;
          if (visible) {
            visibleMessageCount += 1;
          }
        });

        var messagesEmpty = modalCard.querySelector(
          '[data-dashboard-inbox-empty="messages"]'
        );
        if (messagesEmpty) {
          messagesEmpty.hidden = visibleMessageCount > 0;
        }
      }

      function getJobsMailboxContextFromNode(node) {
        var jdSource;
        if (!node) {
          return null;
        }

        jdSource = $(node, "[data-cv-match-mailbox-jd]");

      return {
          mailboxKey: String(node.getAttribute("data-mailbox-key") || ""),
          jobsPostId: Number(node.getAttribute("data-jobs-post-id") || 0),
          wpPostId: Number(node.getAttribute("data-wp-post-id") || 0),
          crmPostId: Number(node.getAttribute("data-crm-post-id") || 0),
          roleTitle: String(node.getAttribute("data-role-title") || ""),
          company: String(node.getAttribute("data-company") || ""),
          location: String(node.getAttribute("data-location") || ""),
          applyUrl: String(node.getAttribute("data-apply-url") || ""),
          externalUrl: String(node.getAttribute("data-external-url") || ""),
          recruiterId: Number(node.getAttribute("data-recruiter-id") || 0),
          recruiterName: String(node.getAttribute("data-recruiter-name") || ""),
          recruiterTitle: String(node.getAttribute("data-recruiter-title") || ""),
          recruiterEmail: String(node.getAttribute("data-recruiter-email") || ""),
          keywords: String(node.getAttribute("data-keywords") || ""),
          pipelineId: Number(node.getAttribute("data-pipeline-id") || 0),
          currentStage: String(node.getAttribute("data-current-stage") || ""),
          currentStageLabel: String(
            node.getAttribute("data-current-stage-label") || ""
          ),
          jdText: jdSource
            ? String(jdSource.value || jdSource.textContent || "").trim()
            : "",
        };
      }

      function getJobsMailboxContextByKey(mailboxKey) {
        var paneKey = String(mailboxKey || "").trim();
        var pane;

        if (!paneKey) {
          return null;
        }

        pane =
          $(root, '[data-cv-match-mailbox-pane="' + paneKey + '"]') ||
          $(root, '[data-cv-match-mailbox-mobileapp-detail="' + paneKey + '"]');
        return pane ? getJobsMailboxContextFromNode(pane) : null;
      }

      function closeJobsMailboxMenus(scope) {
        var menuScope = scope || root;
        $all(menuScope, "[data-cv-match-mailbox-menu]").forEach(function (menu) {
          var toggle = menu.parentNode
            ? $(menu.parentNode, "[data-cv-match-mailbox-menu-toggle]")
            : null;
          menu.hidden = true;
          if (toggle) {
            toggle.setAttribute("aria-expanded", "false");
          }
        });
        $all(menuScope, "[data-cv-match-mailbox-tracker-menu]").forEach(function (menu) {
          var toggle = menu.parentNode
            ? $(menu.parentNode, "[data-cv-match-mailbox-tracker-toggle]")
            : null;
          menu.hidden = true;
          if (toggle) {
            toggle.setAttribute("aria-expanded", "false");
          }
        });
      }

      function closeJobsMailboxContactModals(scope) {
        var modalScope = scope || root;
        $all(modalScope, "[data-cv-match-mailbox-contact-modal]").forEach(function (modal) {
          var wrap = modal.parentNode;
          var toggle = wrap ? $(wrap, "[data-cv-match-mailbox-contact-toggle]") : null;
          modal.hidden = true;
          if (toggle) {
            toggle.setAttribute("aria-expanded", "false");
          }
        });
      }

      function updateJobsMailboxCreditMeter(summary) {
        var limit;
        var used;
        var remaining;
        var percent;
        var headline;
        var meta;

        if (!summary || typeof summary !== "object") {
          return;
        }

        limit = Math.max(0, parseInt(summary.limit, 10) || 0);
        used = Math.max(0, parseInt(summary.used, 10) || 0);
        remaining = Math.max(0, parseInt(summary.remaining, 10) || 0);
        percent = limit > 0 ? Math.max(0, Math.min(100, Math.round((remaining / limit) * 100))) : 0;
        headline = limit > 0 ? String(remaining) + " left" : "No active plan";
        meta =
          limit > 0
            ? String(remaining) +
              " of " +
              String(limit) +
              " recruiter contacts left this week"
            : "Activate a recruiter contact plan to unlock contact credits.";

        $all(root, "[data-jobs-mailbox-creditmeter]").forEach(function (meter) {
          var copyStrong = $("strong", meter);
          var barFill = $(".sffc-cv-match-studio__jobs-mailbox-creditmeter-bar span", meter);
          var metaNode = $(".sffc-cv-match-studio__jobs-mailbox-creditmeter-meta", meter);

          meter.setAttribute("data-credit-limit", String(limit));
          meter.setAttribute("data-credit-used", String(used));
          meter.setAttribute("data-credit-remaining", String(remaining));

          if (copyStrong) {
            copyStrong.textContent = headline;
          }
          if (barFill) {
            barFill.style.width = String(percent) + "%";
          }
          if (metaNode) {
            metaNode.textContent = meta;
          }
        });
      }

      function trackJobsMailboxRecruiterCredit(toggle) {
        var wrap;
        var pane;
        var recruiterEmail;
        var recruiterName;
        var roleTitle;
        var companyName;
        var postId;
        var body;

        if (!toggle || !config.loggedIn || !config.ajaxUrl || !(config.crmNonce || config.nonce)) {
          return Promise.resolve(false);
        }

        wrap = toggle.closest(".sffc-cv-match-studio__jobs-mailbox-recruiter-contact");
        pane = toggle.closest("[data-cv-match-mailbox-pane]");

        if (!pane || !wrap || wrap.getAttribute("data-credit-tracked") === "1") {
          return Promise.resolve(false);
        }

        recruiterEmail = String(pane.getAttribute("data-recruiter-email") || "").trim();
        if (!recruiterEmail) {
          return Promise.resolve(false);
        }

        recruiterName = String(pane.getAttribute("data-recruiter-name") || "").trim();
        roleTitle = String(pane.getAttribute("data-role-title") || "").trim();
        companyName = String(pane.getAttribute("data-company") || "").trim();
        postId =
          parseInt(pane.getAttribute("data-jobs-post-id") || "0", 10) ||
          parseInt(pane.getAttribute("data-wp-post-id") || "0", 10) ||
          parseInt(pane.getAttribute("data-crm-post-id") || "0", 10) ||
          0;

        body = new URLSearchParams();
        body.append("action", "sffc_crm_track_message_email_credit");
        body.append("nonce", config.crmNonce || config.nonce || "");
        body.append("recruiter_email", recruiterEmail);
        body.append("recruiter_name", recruiterName);
        body.append("role_title", roleTitle);
        body.append("company_name", companyName);
        body.append("post_id", String(postId));

        return fetch(config.ajaxUrl || "/wp-admin/admin-ajax.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
          },
          body: body.toString(),
        })
          .then(function (response) {
            return parseAjaxJson(response);
          })
          .then(function (payload) {
            if (!payload || !payload.success || !payload.data) {
              throw new Error(
                payload && payload.data && payload.data.message
                  ? payload.data.message
                  : "Unable to update recruiter contact credits."
              );
            }

            wrap.setAttribute("data-credit-tracked", "1");
            if (payload.data.credit_summary) {
              updateJobsMailboxCreditMeter(payload.data.credit_summary);
            }
            return !!payload.data.credit_charged;
          })
          .catch(function (error) {
            console.error(error);
            return false;
          });
      }

      function toggleJobsMailboxContactModal(toggle) {
        var wrap;
        var modal;
        var willOpen;

        if (!toggle) {
          return;
        }

        wrap = toggle.closest(".sffc-cv-match-studio__jobs-mailbox-recruiter-contact");
        modal = wrap ? $(wrap, "[data-cv-match-mailbox-contact-modal]") : null;

        if (!modal) {
          return;
        }

        willOpen = modal.hidden;
        closeJobsMailboxContactModals(root);
        modal.hidden = !willOpen;
        toggle.setAttribute("aria-expanded", willOpen ? "true" : "false");
        if (willOpen) {
          trackJobsMailboxRecruiterCredit(toggle);
        }
      }

      function toggleJobsMailboxMenu(toggle) {
        var container;
        var menu;
        var willOpen;

        if (!toggle) {
          return;
        }

        container = toggle.closest(".sffc-cv-match-studio__jobs-mailbox-paneicons");
        menu = container ? $(container, "[data-cv-match-mailbox-menu]") : null;

        if (!menu) {
          return;
        }

        willOpen = menu.hidden;
        closeJobsMailboxMenus(root);
        menu.hidden = !willOpen;
        toggle.setAttribute("aria-expanded", willOpen ? "true" : "false");
      }

      function toggleJobsMailboxTrackerMenu(toggle) {
        var container;
        var menu;
        var willOpen;

        if (!toggle) {
          return;
        }

        container = toggle.closest(".sffc-cv-match-studio__jobs-mailbox-tracker");
        menu = container ? $(container, "[data-cv-match-mailbox-tracker-menu]") : null;

        if (!menu) {
          return;
        }

        willOpen = menu.hidden;
        closeJobsMailboxMenus(root);
        menu.hidden = !willOpen;
        toggle.setAttribute("aria-expanded", willOpen ? "true" : "false");
      }

      function getJobsMailboxActivePane() {
        var mailbox = $(root, "[data-cv-match-jobs-mailbox]");
        var mobileDetail;

        mobileDetail = $(
          root,
          '[data-cv-match-mailbox-mobileapp][data-mobile-view="detail"] [data-cv-match-mailbox-mobileapp-detail].is-active:not([hidden])'
        );
        if (mobileDetail) {
          return mobileDetail;
        }

        if (!mailbox) {
          return null;
        }
        return (
          mailbox.querySelector("[data-cv-match-mailbox-pane].is-active:not([hidden])") ||
          null
        );
      }

      function getJobsMailboxSmartApplyItem(activePane) {
        var context = getJobsMailboxContextFromNode(activePane);
        var keywords;

        if (!context || !context.jobsPostId) {
          return null;
        }

        keywords = String(context.keywords || "")
          .split("|")
          .map(function (keyword) {
            return String(keyword || "").trim();
          })
          .filter(Boolean);

        return {
          jobsPostId: context.jobsPostId,
          wpPostId: context.wpPostId,
          id: context.crmPostId,
          roleTitle: context.roleTitle,
          company: context.company,
          recruiterName: context.recruiterName,
          recruiterTitle: context.recruiterTitle,
          recruiterEmail: context.recruiterEmail,
          recruiterFirm: context.company,
          reasons: keywords.slice(0, 3),
          gaps: [],
        };
      }

      function getJobsMailboxSmartApplyItemByKey(mailboxKey) {
        var paneKey = String(mailboxKey || "").trim();
        var pane;

        if (!paneKey) {
          return null;
        }

        pane = $(root, '[data-cv-match-mailbox-pane="' + paneKey + '"]');
        return pane ? getJobsMailboxSmartApplyItem(pane) : null;
      }

      function openJobsMailboxContextCvReport(context) {
        var cvText = getCurrentCvText();
        var gapUrl = config.cvGapUrl || "/cv-gap-analyser/";

        if (!context || !context.jobsPostId || !context.jdText) {
          setFeedback(
            root,
            "We could not load the job description for this report yet.",
            true
          );
          return;
        }

        if (!cvText) {
          setFeedback(
            root,
            config.labels && config.labels.noCv
              ? config.labels.noCv
              : "Please upload your CV/Resume first to see matches.",
            true
          );
          return;
        }

        try {
          window.sessionStorage.setItem(
            CV_GAP_PREFILL_KEY,
            JSON.stringify({
              jd_text: context.jdText,
              cv_text: cvText,
              job_title: context.roleTitle || "",
              company: context.company || "",
              source: "cv_match_studio",
            })
          );
        } catch (error) {
          setFeedback(
            root,
            "We could not prepare your CV report right now.",
            true
          );
          return;
        }

        window.location.href = gapUrl;
      }

      function openJobsMailboxCvReport(activePane) {
        var context = getJobsMailboxContextFromNode(activePane);
        var cvText = getCurrentCvText();
        var gapUrl = config.cvGapUrl || "/cv-gap-analyser/";

        if (!context || !context.jobsPostId || !context.jdText) {
          setFeedback(
            root,
            "We could not load the job description for this report yet.",
            true
          );
          return;
        }

        if (!cvText) {
          setFeedback(
            root,
            config.labels && config.labels.noCv
              ? config.labels.noCv
              : "Please upload your CV/Resume first to see matches.",
            true
          );
          return;
        }

        try {
          window.sessionStorage.setItem(
            CV_GAP_PREFILL_KEY,
            JSON.stringify({
              jd_text: context.jdText,
              cv_text: cvText,
              job_title: context.roleTitle || "",
              company: context.company || "",
              source: "cv_match_studio",
            })
          );
        } catch (error) {
          setFeedback(
            root,
            "We could not prepare your CV report right now.",
            true
          );
          return;
        }

        window.location.href = gapUrl;
      }

      function handleJobsMailboxMenuAction(action, source) {
        var activePane = getJobsMailboxActivePane();
        var context = getJobsMailboxContextFromNode(activePane);
        var smartItem;

        if (!action || !activePane || !context) {
          return;
        }

        closeJobsMailboxMenus(root);

        if (action === "smart-apply") {
          smartItem = getJobsMailboxSmartApplyItem(activePane);
          if (smartItem) {
            openSmartApply(smartItem);
          }
          return;
        }

        if (action === "apply") {
          if (context.applyUrl) {
            window.open(context.applyUrl, "_blank", "noopener");
          }
          return;
        }

        if (action === "compare") {
          openJobsMailboxCvReport(activePane);
        }
      }

      function scrollJobsMailboxUtilityIntoView(activePane) {
        var utilityBar;
        var storyTrack;
        if (!activePane) {
          return;
        }

        utilityBar = $(
          activePane,
          ".sffc-cv-match-studio__jobs-mailbox-paneutility"
        );

        if (!utilityBar) {
          return;
        }

        window.requestAnimationFrame(function () {
          utilityBar.scrollLeft = 0;
          storyTrack = $(
            utilityBar,
            ".sffc-cv-match-studio__jobs-mailbox-stories-track"
          );
          if (storyTrack) {
            storyTrack.scrollLeft = 0;
          }
        });
      }

      function applyJobsMailboxPins(mailbox) {
        var pinnedKeys = loadJobsMailboxPins();
        var pinnedList = $(mailbox, '[data-cv-match-mailbox-list="pinned"]');
        var primaryList = $(mailbox, '[data-cv-match-mailbox-list="primary"]');
        var pinnedGroup = $(mailbox, '[data-cv-match-mailbox-group="pinned"]');
        var rows = $all(mailbox, "[data-cv-match-mailbox-row]");
        var pinnedCount = 0;

        if (!pinnedList || !primaryList || !rows.length) {
          return;
        }

        pinnedKeys.forEach(function (key) {
          var row = $(mailbox, '[data-cv-match-mailbox-row="' + key + '"]');
          var pinButton = row
            ? $(row, "[data-cv-match-mailbox-pin]")
            : null;
          var listButton = row
            ? $(row, "[data-cv-match-mailbox-open]")
            : null;
          if (!row) {
            return;
          }
          pinnedList.appendChild(row);
          row.classList.add("is-pinned-role");
          if (listButton) {
            listButton.classList.add(
              "sffc-cv-match-studio__jobs-mailbox-listitem--pinned"
            );
          }
          if (pinButton) {
            pinButton.classList.add("is-pinned");
            pinButton.setAttribute("aria-label", "Unpin role");
            pinButton.setAttribute("title", "Unpin role");
          }
          pinnedCount += 1;
        });

        rows.forEach(function (row) {
          var key = row.getAttribute("data-cv-match-mailbox-row") || "";
          var pinButton = $(row, "[data-cv-match-mailbox-pin]");
          var listButton = $(row, "[data-cv-match-mailbox-open]");
          if (pinnedKeys.indexOf(key) !== -1) {
            return;
          }
          primaryList.appendChild(row);
          row.classList.remove("is-pinned-role");
          if (listButton) {
            listButton.classList.remove(
              "sffc-cv-match-studio__jobs-mailbox-listitem--pinned"
            );
          }
          if (pinButton) {
            pinButton.classList.remove("is-pinned");
            pinButton.setAttribute("aria-label", "Pin role");
            pinButton.setAttribute("title", "Pin role");
          }
        });

        if (pinnedGroup) {
          pinnedGroup.hidden = pinnedCount === 0;
        }

      }

      function applyJobsMailboxHidden(mailbox) {
        var hiddenKeys = loadJobsMailboxHidden();

        $all(mailbox, "[data-cv-match-mailbox-row]").forEach(function (row) {
          var key = row.getAttribute("data-cv-match-mailbox-row") || "";
          row.classList.toggle("is-hidden-role", hiddenKeys.indexOf(key) !== -1);
        });

        $all(mailbox, "[data-cv-match-mailbox-pane]").forEach(function (pane) {
          var key =
            pane.getAttribute("data-mailbox-key") ||
            pane.getAttribute("data-cv-match-mailbox-pane") ||
            "";
          pane.classList.toggle("is-hidden-role", hiddenKeys.indexOf(key) !== -1);
        });
      }

      function updateJobsMailboxEmptyState(mailbox, visibleButtons) {
        var emptyState = $(mailbox, "[data-cv-match-mailbox-empty-state]");
        var hasVisible = Array.isArray(visibleButtons) && visibleButtons.length > 0;

        if (!emptyState) {
          return;
        }

        emptyState.hidden = hasVisible;
        mailbox.classList.toggle("is-empty-view", !hasVisible);
      }

      function syncJobsMailboxSearchCollapse(mailbox) {
        var groups = mailbox ? $(mailbox, ".sffc-cv-match-studio__jobs-mailbox-groups") : null;
        var searchInput = mailbox ? $(mailbox, "[data-cv-match-mailbox-search-input]") : null;
        var shouldCollapse = !!(
          groups &&
          groups.scrollTop > 18 &&
          !(searchInput && document.activeElement === searchInput)
        );

        if (!mailbox) {
          return;
        }

        mailbox.classList.toggle("is-search-collapsed", shouldCollapse);
      }

      function observeJobsMailboxSearchCollapse(mailbox) {
        var groups = mailbox ? $(mailbox, ".sffc-cv-match-studio__jobs-mailbox-groups") : null;
        var searchInput = mailbox ? $(mailbox, "[data-cv-match-mailbox-search-input]") : null;

        if (!mailbox || !groups || mailbox._cvMatchSearchCollapseBound) {
          if (mailbox) {
            syncJobsMailboxSearchCollapse(mailbox);
          }
          return;
        }

        mailbox._cvMatchSearchCollapseBound = true;
        mailbox._cvMatchSearchCollapseFrame = 0;

        groups.addEventListener(
          "scroll",
          function () {
            if (mailbox._cvMatchSearchCollapseFrame) {
              return;
            }
            mailbox._cvMatchSearchCollapseFrame = window.requestAnimationFrame(
              function () {
                mailbox._cvMatchSearchCollapseFrame = 0;
                syncJobsMailboxSearchCollapse(mailbox);
              }
            );
          },
          { passive: true }
        );

        if (searchInput) {
          searchInput.addEventListener("focus", function () {
            mailbox.classList.remove("is-search-collapsed");
          });
          searchInput.addEventListener("blur", function () {
            syncJobsMailboxSearchCollapse(mailbox);
          });
        }

        syncJobsMailboxSearchCollapse(mailbox);
      }

      function syncJobsMailboxRefreshPrompt(mailbox) {
        var groups = mailbox ? $(mailbox, ".sffc-cv-match-studio__jobs-mailbox-groups") : null;
        var refreshButton = mailbox ? $(mailbox, "[data-cv-match-mailbox-refresh]") : null;
        var hasMore = mailbox && mailbox.classList.contains("has-refreshable-jobs");
        var activeFilter = mailbox ? getJobsMailboxActiveFilter(mailbox) : "all";
        var shouldShow = !!(
          groups &&
          refreshButton &&
          hasMore &&
          activeFilter === "all" &&
          (mailbox._cvMatchRefreshPromptHasScrolledDown ||
            mailbox.classList.contains("has-scrolled-down")) &&
          (mailbox._cvMatchRefreshPromptVisible ||
            mailbox.classList.contains("is-scrolling-up"))
        );

        if (refreshButton) {
          refreshButton.hidden = !shouldShow;
        }
      }

      function observeJobsMailboxRefreshPrompt(mailbox) {
        var groups = mailbox ? $(mailbox, ".sffc-cv-match-studio__jobs-mailbox-groups") : null;

        if (!mailbox || !groups || mailbox._cvMatchRefreshPromptBound) {
          if (mailbox) {
            syncJobsMailboxRefreshPrompt(mailbox);
          }
          return;
        }

        mailbox._cvMatchRefreshPromptBound = true;
        mailbox._cvMatchRefreshPromptFrame = 0;
        mailbox._cvMatchRefreshPromptLastScrollTop = groups.scrollTop || 0;
        mailbox._cvMatchRefreshPromptMaxScrollTop = groups.scrollTop || 0;
        mailbox._cvMatchRefreshPromptHasScrolledDown = false;
        mailbox._cvMatchRefreshPromptIsScrollingUp = false;
        mailbox._cvMatchRefreshPromptVisible = false;
        mailbox.classList.remove("has-scrolled-down");
        mailbox.classList.remove("is-scrolling-up");

        function updateRefreshPromptScrollState(scrollTop) {
          var previous = mailbox._cvMatchRefreshPromptLastScrollTop || 0;
          var current = Math.max(0, scrollTop || 0);
          var delta = current - previous;
          var maxScroll = Math.max(
            mailbox._cvMatchRefreshPromptMaxScrollTop || 0,
            current
          );
          var scrollingUp = delta < -8;
          var scrollingDown = delta > 6;
          var hasScrolledDown =
            maxScroll > 80 ||
            mailbox.classList.contains("has-scrolled-down");
          var promptVisible = !!mailbox._cvMatchRefreshPromptVisible;

          if (scrollingDown) {
            hasScrolledDown = true;
            promptVisible = false;
          } else if (scrollingUp && hasScrolledDown && maxScroll - current > 24) {
            promptVisible = true;
          }

          if (current < 12) {
            promptVisible = false;
          }

          mailbox._cvMatchRefreshPromptLastScrollTop = current;
          mailbox._cvMatchRefreshPromptMaxScrollTop = maxScroll;
          mailbox._cvMatchRefreshPromptHasScrolledDown = hasScrolledDown;
          mailbox._cvMatchRefreshPromptIsScrollingUp = promptVisible;
          mailbox._cvMatchRefreshPromptVisible = promptVisible;
          mailbox.classList.toggle("has-scrolled-down", hasScrolledDown);
          mailbox.classList.toggle("is-scrolling-up", promptVisible);
          syncJobsMailboxRefreshPrompt(mailbox);
        }

        function requestRefreshPromptUpdate(scrollTop) {
          mailbox._cvMatchRefreshPromptPendingScrollTop = Math.max(
            0,
            scrollTop || 0
          );

          if (mailbox._cvMatchRefreshPromptFrame) {
            return;
          }

          mailbox._cvMatchRefreshPromptFrame = window.requestAnimationFrame(
            function () {
              mailbox._cvMatchRefreshPromptFrame = 0;
              updateRefreshPromptScrollState(
                mailbox._cvMatchRefreshPromptPendingScrollTop || 0
              );
            }
          );
        }

        groups.addEventListener(
          "scroll",
          function () {
            requestRefreshPromptUpdate(groups.scrollTop || 0);
          },
          { passive: true }
        );

        window.addEventListener(
          "scroll",
          function () {
            var statePanel = mailbox.closest("[data-cv-match-state]");
            if (statePanel && !statePanel.classList.contains("is-active")) {
              return;
            }
            requestRefreshPromptUpdate(groups.scrollTop || window.pageYOffset || 0);
          },
          { passive: true }
        );

        syncJobsMailboxRefreshPrompt(mailbox);
      }

      function syncJobsMailboxState(activeKey) {
        var mailbox = $(root, "[data-cv-match-jobs-mailbox]");
        var buttons;
        var visibleButtons;
        var panes;
        var activeButton = null;
        var activePane = null;
        var resolvedActiveKey = String(activeKey || "").trim();

        if (!mailbox) {
          return;
        }

        observeJobsMailboxSearchCollapse(mailbox);
        observeJobsMailboxRefreshPrompt(mailbox);
        applyJobsMailboxPins(mailbox);
        applyJobsMailboxHidden(mailbox);
        syncDesktopJobsMailboxMostClicked(mailbox);
        syncDesktopJobsMailboxRecent(mailbox);

        buttons = $all(mailbox, "[data-cv-match-mailbox-open]");
        visibleButtons = getVisibleJobsMailboxButtons(mailbox);
        panes = $all(mailbox, "[data-cv-match-mailbox-pane]");

        if (!buttons.length || !panes.length) {
          return;
        }

        if (resolvedActiveKey) {
          activeButton = $(mailbox, '[data-cv-match-mailbox-open="' + resolvedActiveKey + '"]');
          activePane = $(mailbox, '[data-cv-match-mailbox-pane="' + resolvedActiveKey + '"]');
        }

        if (!activeButton || !activePane) {
          activeButton =
            mailbox.querySelector("[data-cv-match-mailbox-open].is-active:not([hidden])") ||
            visibleButtons[0] ||
            null;
          activePane =
            (activeButton
              ? $(mailbox, '[data-cv-match-mailbox-pane="' + (activeButton.getAttribute("data-cv-match-mailbox-open") || "") + '"]')
              : null) ||
            null;
        }

        resolvedActiveKey =
          (activeButton && activeButton.getAttribute("data-cv-match-mailbox-open")) ||
          resolvedActiveKey;

        buttons.forEach(function (button) {
          var isActive =
            (button.getAttribute("data-cv-match-mailbox-open") || "") ===
            resolvedActiveKey;
          var row = button.closest("[data-cv-match-mailbox-row]");
          var mostClickedRow = button.closest("[data-cv-match-mailbox-mostclicked-row]");
          var recentRow = button.closest("[data-cv-match-mailbox-recent-row]");
          button.classList.toggle("is-active", isActive);
          button.setAttribute("aria-pressed", isActive ? "true" : "false");
          if (row) {
            row.classList.toggle("is-active", isActive);
          }
          if (mostClickedRow) {
            mostClickedRow.classList.toggle("is-active", isActive);
          }
          if (recentRow) {
            recentRow.classList.toggle("is-active", isActive);
          }
        });

        panes.forEach(function (pane) {
          var isActive = pane === activePane;
          pane.hidden = !isActive;
          pane.classList.toggle("is-active", isActive);
        });

        syncDesktopJobsMailboxLoadMore(mailbox);
        updateJobsMailboxEmptyState(mailbox, visibleButtons);
        closeJobsMailboxMenus(mailbox);
        scrollJobsMailboxUtilityIntoView(activePane);
        root._cvMatchJobsMailboxContext = getJobsMailboxContextFromNode(activePane);
      }

      function getJobsMailboxNewsletterGroupSlug(mailbox) {
        return mailbox
          ? String(mailbox._cvMatchNewsletterGroupSlug || "").trim()
          : "";
      }

      function nodeMatchesJobsMailboxNewsletterGroup(node, groupSlug) {
        var groups;

        groupSlug = String(groupSlug || "").trim();
        if (!groupSlug) {
          return true;
        }

        if (!node) {
          return false;
        }

        groups = String(
          node.getAttribute("data-cv-match-mailbox-groups") || ""
        )
          .split(/\s+/)
          .filter(Boolean);

        return groups.indexOf(groupSlug) !== -1;
      }

      function rowMatchesJobsMailboxNewsletterGroup(row, groupSlug) {
        var button;

        if (!groupSlug) {
          return true;
        }

        if (!row) {
          return false;
        }

        button =
          row.querySelector("[data-cv-match-mailbox-open]") ||
          row.querySelector("[data-cv-match-mailbox-mobileapp-open]");

        return nodeMatchesJobsMailboxNewsletterGroup(button, groupSlug);
      }

      function getJobsMailboxSearchQuery(mailbox) {
        var input = mailbox ? $(mailbox, "[data-cv-match-mailbox-search-input]") : null;
        return input ? String(input.value || "").trim() : "";
      }

      function getJobsMailboxSearchTokens(mailbox) {
        return getSearchTokens(getJobsMailboxSearchQuery(mailbox));
      }

      function getJobsMailboxRowSearchText(row) {
        var button = row
          ? row.querySelector("[data-cv-match-mailbox-open]") ||
            row.querySelector("[data-cv-match-mailbox-mobileapp-open]")
          : null;
        var fields = [
          row ? row.textContent || "" : "",
          row ? row.getAttribute("data-cv-match-mailbox-role-title") || "" : "",
          row ? row.getAttribute("data-cv-match-mailbox-company") || "" : "",
          row ? row.getAttribute("data-cv-match-mailbox-location") || "" : "",
          button ? button.textContent || "" : "",
          button ? button.getAttribute("data-cv-match-mailbox-groups") || "" : "",
          button ? button.getAttribute("data-cv-match-mailbox-fit") || "" : "",
        ];

        return fields.join(" ");
      }

      function rowMatchesJobsMailboxSearch(row, tokens) {
        return searchTokensMatchText(tokens || [], getJobsMailboxRowSearchText(row));
      }

      function applyJobsMailboxNewsletterGroupFilter(groupSlug, groupLabel) {
        var mailbox = $(root, "[data-cv-match-jobs-mailbox]");
        var firstVisibleKey = "";
        var searchTokens;

        groupSlug = String(groupSlug || "").trim();
        if (!mailbox || !groupSlug) {
          return;
        }

        mailbox._cvMatchNewsletterGroupSlug = groupSlug;
        mailbox._cvMatchNewsletterGroupLabel = String(groupLabel || "").trim();

        $all(mailbox, "[data-cv-match-mailbox-filter]").forEach(function (button) {
          button.classList.remove("is-active");
          button.setAttribute("aria-pressed", "false");
        });

        searchTokens = getJobsMailboxSearchTokens(mailbox);

        $all(mailbox, "[data-cv-match-mailbox-open]").forEach(function (button) {
          var row = button.closest(".sffc-cv-match-studio__jobs-mailbox-listrow");
          var visible =
            nodeMatchesJobsMailboxNewsletterGroup(button, groupSlug) &&
            rowMatchesJobsMailboxSearch(row, searchTokens);

          button.hidden = !visible;
          if (row) {
            row.hidden = !visible;
            row.classList.toggle("is-collapsed-extra", false);
          }
          if (visible && !firstVisibleKey) {
            firstVisibleKey = button.getAttribute("data-cv-match-mailbox-open") || "";
          }
        });

        $all(mailbox, "[data-cv-match-mailbox-mobileapp-open]").forEach(function (button) {
          var row = button.closest(".sffc-cv-match-studio__jobs-mailbox-mobileapp-item");
          var haystack = [button.textContent || "", row ? row.textContent || "" : ""].join(" ");
          button.hidden =
            !nodeMatchesJobsMailboxNewsletterGroup(button, groupSlug) ||
            !searchTokensMatchText(searchTokens, haystack);
        });

        $all(mailbox, "[data-cv-match-mailbox-group]").forEach(function (group) {
          var groupRows = $all(group, ".sffc-cv-match-studio__jobs-mailbox-listrow");
          if (!groupRows.length) {
            return;
          }
          group.hidden = !groupRows.some(function (row) {
            return !row.hidden && !row.classList.contains("is-hidden-role");
          });
        });

        syncJobsMailboxState(firstVisibleKey);
        syncJobsMailboxMobileApp(firstVisibleKey, "feed");
      }

      function setPendingJobsMailboxNewsletterGroup(groupSlug, groupLabel) {
        root._cvMatchPendingJobsMailboxNewsletterGroup = groupSlug
          ? {
              slug: String(groupSlug || "").trim(),
              label: String(groupLabel || "").trim(),
            }
          : null;
      }

      function clearJobsMailboxNewsletterGroup(mailbox) {
        if (mailbox) {
          mailbox._cvMatchNewsletterGroupSlug = "";
          mailbox._cvMatchNewsletterGroupLabel = "";
        }

        setPendingJobsMailboxNewsletterGroup("", "");
      }

      function getPendingJobsMailboxNewsletterGroup() {
        var pending = root._cvMatchPendingJobsMailboxNewsletterGroup;
        if (!pending || !pending.slug) {
          return null;
        }

        return {
          slug: String(pending.slug || "").trim(),
          label: String(pending.label || "").trim(),
        };
      }

      function startJobsMailboxPlaceholderLoading() {
        var placeholder = $(root, "[data-cv-match-jobs-mailbox-placeholder]");
        var percentNode = placeholder
          ? $(placeholder, "[data-cv-match-mailbox-skeleton-percent]")
          : null;
        var statusNode = placeholder
          ? $(placeholder, "[data-cv-match-mailbox-skeleton-status]")
          : null;
        var barNode = placeholder
          ? $(placeholder, "[data-cv-match-mailbox-skeleton-bar]")
          : null;
        var steps = [
          { at: 12, text: "Scanning role titles" },
          { at: 36, text: "Matching recruiter signals" },
          { at: 62, text: "Refreshing fit and market context" },
          { at: 82, text: "Building your mailbox layout" },
        ];
        var progress = 12;

        if (!placeholder) {
          return;
        }

        if (root._cvMatchJobsMailboxPlaceholderDelayTimer) {
          window.clearTimeout(root._cvMatchJobsMailboxPlaceholderDelayTimer);
          root._cvMatchJobsMailboxPlaceholderDelayTimer = null;
        }

        if (root._cvMatchJobsMailboxPlaceholderTimer) {
          window.clearInterval(root._cvMatchJobsMailboxPlaceholderTimer);
          root._cvMatchJobsMailboxPlaceholderTimer = null;
        }

        root._cvMatchJobsMailboxPlaceholderDelayTimer = window.setTimeout(function () {
          if (percentNode) {
            percentNode.textContent = progress + "%";
          }
          if (statusNode) {
            statusNode.textContent = steps[0].text;
          }
          if (barNode) {
            barNode.style.width = progress + "%";
          }

          root._cvMatchJobsMailboxPlaceholderTimer = window.setInterval(function () {
            var nextStep = steps[0];

            if (!placeholder.isConnected) {
              stopJobsMailboxPlaceholderLoading(true);
              return;
            }

            progress = Math.min(92, progress + (progress < 50 ? 6 : 4));

            steps.forEach(function (step) {
              if (progress >= step.at) {
                nextStep = step;
              }
            });

            if (percentNode) {
              percentNode.textContent = progress + "%";
            }
            if (statusNode) {
              statusNode.textContent = nextStep.text;
            }
            if (barNode) {
              barNode.style.width = progress + "%";
            }
          }, 260);
        }, 180);
      }

      function stopJobsMailboxPlaceholderLoading(immediate) {
        var placeholder = $(root, "[data-cv-match-jobs-mailbox-placeholder]");
        var percentNode = placeholder
          ? $(placeholder, "[data-cv-match-mailbox-skeleton-percent]")
          : null;
        var statusNode = placeholder
          ? $(placeholder, "[data-cv-match-mailbox-skeleton-status]")
          : null;
        var barNode = placeholder
          ? $(placeholder, "[data-cv-match-mailbox-skeleton-bar]")
          : null;

        if (root._cvMatchJobsMailboxPlaceholderDelayTimer) {
          window.clearTimeout(root._cvMatchJobsMailboxPlaceholderDelayTimer);
          root._cvMatchJobsMailboxPlaceholderDelayTimer = null;
        }

        if (root._cvMatchJobsMailboxPlaceholderTimer) {
          window.clearInterval(root._cvMatchJobsMailboxPlaceholderTimer);
          root._cvMatchJobsMailboxPlaceholderTimer = null;
        }

        if (immediate || !placeholder) {
          return;
        }

        if (percentNode) {
          percentNode.textContent = "100%";
        }
        if (statusNode) {
          statusNode.textContent = "Mailbox ready";
        }
        if (barNode) {
          barNode.style.width = "100%";
        }
      }

      function syncDesktopJobsMailboxMostClicked(mailbox) {
        var group;
        var list;
        var rows;
        var clicks;
        var hiddenKeys;
        var visibleRows;
        var hasTrackedClicks;
        var newsletterGroupSlug;

        if (!mailbox) {
          return;
        }

        group = $(mailbox, '[data-cv-match-mailbox-group="most-clicked"]');
        list = $(mailbox, '[data-cv-match-mailbox-list="most-clicked"]');

        if (!group || !list) {
          return;
        }

        rows = $all(list, "[data-cv-match-mailbox-mostclicked-row]");
        clicks = loadJobsMailboxClicks();
        hiddenKeys = loadJobsMailboxHidden();
        newsletterGroupSlug = getJobsMailboxNewsletterGroupSlug(mailbox);

        visibleRows = rows.filter(function (row) {
          var key =
            row.getAttribute("data-cv-match-mailbox-click-key") ||
            row.getAttribute("data-cv-match-mailbox-mostclicked-row") ||
            "";
          return (
            hiddenKeys.indexOf(key) === -1 &&
            rowMatchesJobsMailboxNewsletterGroup(row, newsletterGroupSlug)
          );
        });

        hasTrackedClicks = visibleRows.some(function (row) {
          var key =
            row.getAttribute("data-cv-match-mailbox-click-key") ||
            row.getAttribute("data-cv-match-mailbox-mostclicked-row") ||
            "";
          return (parseInt(clicks[key] || 0, 10) || 0) > 0;
        });

        visibleRows.sort(function (left, right) {
          var leftKey =
            left.getAttribute("data-cv-match-mailbox-click-key") ||
            left.getAttribute("data-cv-match-mailbox-mostclicked-row") ||
            "";
          var rightKey =
            right.getAttribute("data-cv-match-mailbox-click-key") ||
            right.getAttribute("data-cv-match-mailbox-mostclicked-row") ||
            "";
          var leftClicks = parseInt(clicks[leftKey] || 0, 10) || 0;
          var rightClicks = parseInt(clicks[rightKey] || 0, 10) || 0;
          var leftSeed =
            parseInt(
              left.getAttribute("data-cv-match-mailbox-mostclicked-seed") || "999",
              10
            ) || 999;
          var rightSeed =
            parseInt(
              right.getAttribute("data-cv-match-mailbox-mostclicked-seed") || "999",
              10
            ) || 999;

          if (hasTrackedClicks && rightClicks !== leftClicks) {
            return rightClicks - leftClicks;
          }

          return leftSeed - rightSeed;
        });

        visibleRows.forEach(function (row, index) {
          list.appendChild(row);
          row.hidden = index >= 5;
          row.classList.toggle("is-hidden-role", false);
        });

        rows.forEach(function (row) {
          if (visibleRows.indexOf(row) === -1) {
            row.hidden = true;
            row.classList.add("is-hidden-role");
          }
        });

        group.hidden = visibleRows.length === 0;
      }

      function syncDesktopJobsMailboxRecent(mailbox) {
        var group;
        var list;
        var rows;
        var hiddenKeys;
        var visibleRows;
        var newsletterGroupSlug;

        if (!mailbox) {
          return;
        }

        group = $(mailbox, '[data-cv-match-mailbox-group="recent"]');
        list = $(mailbox, '[data-cv-match-mailbox-list="recent"]');

        if (!group || !list) {
          return;
        }

        rows = $all(list, "[data-cv-match-mailbox-recent-row]");
        hiddenKeys = loadJobsMailboxHidden();
        newsletterGroupSlug = getJobsMailboxNewsletterGroupSlug(mailbox);

        visibleRows = rows.filter(function (row) {
          var key =
            row.getAttribute("data-cv-match-mailbox-click-key") ||
            row.getAttribute("data-cv-match-mailbox-recent-row") ||
            "";
          return (
            hiddenKeys.indexOf(key) === -1 &&
            rowMatchesJobsMailboxNewsletterGroup(row, newsletterGroupSlug)
          );
        });

        visibleRows.forEach(function (row) {
          row.hidden = false;
          row.classList.toggle("is-hidden-role", false);
        });

        rows.forEach(function (row) {
          if (visibleRows.indexOf(row) === -1) {
            row.hidden = true;
            row.classList.add("is-hidden-role");
          }
        });

        group.hidden = visibleRows.length === 0;
      }

      function syncDesktopJobsMailboxLoadMore(mailbox) {
        var primaryGroup;
        var primaryList;
        var rows;
        var loadMoreButton;
        var visibleKeys;
        var activeRow;
        var activeKey = "";
        var seenKeys;
        var nextSeenKeys;
        var visibleLookup = {};
        var activeFilter;

        if (!mailbox) {
          return;
        }

        primaryGroup = $(mailbox, '[data-cv-match-mailbox-group="primary"]');
        primaryList = $(mailbox, '[data-cv-match-mailbox-list="primary"]');
        loadMoreButton = $(mailbox, "[data-cv-match-mailbox-refresh]");

        if (!primaryGroup || !primaryList || !loadMoreButton) {
          return;
        }

        if (getJobsMailboxSearchTokens(mailbox).length) {
          mailbox.classList.remove("has-refreshable-jobs");
          mailbox._cvMatchRefreshPromptVisible = false;
          mailbox._cvMatchRefreshPromptIsScrollingUp = false;
          mailbox.classList.remove("is-scrolling-up");
          loadMoreButton.hidden = true;
          return;
        }

        activeFilter = getJobsMailboxActiveFilter(mailbox);
        if (activeFilter !== "all" || getJobsMailboxNewsletterGroupSlug(mailbox)) {
          mailbox.classList.remove("has-refreshable-jobs");
          mailbox._cvMatchRefreshPromptVisible = false;
          mailbox._cvMatchRefreshPromptIsScrollingUp = false;
          mailbox.classList.remove("is-scrolling-up");
          loadMoreButton.hidden = true;
          syncJobsMailboxRefreshPrompt(mailbox);
          return;
        }

        rows = $all(primaryGroup, "[data-cv-match-mailbox-row]").filter(function (row) {
          return !row.classList.contains("is-hidden-role");
        });

        if (!rows.length) {
          mailbox.classList.remove("has-refreshable-jobs");
          mailbox._cvMatchRefreshPromptVisible = false;
          mailbox._cvMatchRefreshPromptIsScrollingUp = false;
          mailbox.classList.remove("is-scrolling-up");
          loadMoreButton.hidden = true;
          return;
        }

        visibleKeys = Array.isArray(root._cvMatchJobsMailboxVisibleKeys)
          ? root._cvMatchJobsMailboxVisibleKeys.filter(function (key) {
              return rows.some(function (row) {
                return (row.getAttribute("data-cv-match-mailbox-row") || "") === key;
              });
            })
          : [];

        if (!visibleKeys.length) {
          visibleKeys = rows.slice(0, JOBS_MAILBOX_DESKTOP_LIMIT).map(function (row) {
            return row.getAttribute("data-cv-match-mailbox-row") || "";
          });
          root._cvMatchJobsMailboxVisibleKeys = visibleKeys;
        }

        visibleKeys.forEach(function (key) {
          visibleLookup[key] = true;
        });

        rows.forEach(function (row, index) {
          var key = row.getAttribute("data-cv-match-mailbox-row") || "";
          row.classList.toggle(
            "is-collapsed-extra",
            !visibleLookup[key] && rows.length > JOBS_MAILBOX_DESKTOP_LIMIT
          );
          row.hidden = false;
        });

        activeRow =
          primaryGroup.querySelector("[data-cv-match-mailbox-row].is-active:not(.is-collapsed-extra):not([hidden])") ||
          null;
        activeKey = activeRow
          ? activeRow.getAttribute("data-cv-match-mailbox-row") || ""
          : "";

        if (!activeKey && rows.length) {
          activeKey = visibleKeys[0] || rows[0].getAttribute("data-cv-match-mailbox-row") || "";
        }

        seenKeys = loadJobsMailboxSeen();
        nextSeenKeys = seenKeys.slice();
        visibleKeys.forEach(function (key) {
          if (key && nextSeenKeys.indexOf(key) === -1) {
            nextSeenKeys.unshift(key);
          }
        });
        saveJobsMailboxSeen(nextSeenKeys);

        mailbox.classList.toggle(
          "has-refreshable-jobs",
          rows.length > 0
        );
        syncJobsMailboxRefreshPrompt(mailbox);
      }

      function refreshDesktopJobsMailboxPrimary(mailbox) {
        var primaryGroup;
        var groups;
        var rows;
        var seenKeys;
        var currentKeys;
        var unseenRows;
        var nextRows;
        var nextKeys;
        var nextActiveKey = "";
        var currentIndexes;
        var currentLastIndex = -1;
        var startIndex = 0;
        var orderedRows;

        if (!mailbox) {
          return "";
        }

        primaryGroup = $(mailbox, '[data-cv-match-mailbox-group="primary"]');
        groups = $(mailbox, ".sffc-cv-match-studio__jobs-mailbox-groups");

        if (!primaryGroup) {
          return "";
        }

        rows = $all(primaryGroup, "[data-cv-match-mailbox-row]").filter(function (row) {
          return !row.classList.contains("is-hidden-role");
        });

        if (!rows.length) {
          return "";
        }

        seenKeys = loadJobsMailboxSeen();
        currentKeys = Array.isArray(root._cvMatchJobsMailboxVisibleKeys)
          ? root._cvMatchJobsMailboxVisibleKeys
          : [];

        if (!currentKeys.length) {
          currentKeys = rows
            .filter(function (row) {
              return !row.classList.contains("is-collapsed-extra");
            })
            .map(function (row) {
              return row.getAttribute("data-cv-match-mailbox-row") || "";
            })
            .filter(Boolean);
        }

        currentIndexes = currentKeys
          .map(function (key) {
            var index = -1;
            rows.some(function (row, rowIndex) {
              if ((row.getAttribute("data-cv-match-mailbox-row") || "") === key) {
                index = rowIndex;
                return true;
              }
              return false;
            });
            return index;
          })
          .filter(function (index) {
            return index >= 0;
          });

        if (currentIndexes.length) {
          currentLastIndex = Math.max.apply(Math, currentIndexes);
          startIndex = currentLastIndex + 1;
          if (startIndex >= rows.length) {
            startIndex = 0;
          }
        }

        orderedRows = rows.slice(startIndex).concat(rows.slice(0, startIndex));

        unseenRows = rows.filter(function (row) {
          var key = row.getAttribute("data-cv-match-mailbox-row") || "";
          return key && seenKeys.indexOf(key) === -1 && currentKeys.indexOf(key) === -1;
        });

        if (unseenRows.length) {
          nextRows = orderedRows.filter(function (row) {
            return unseenRows.indexOf(row) !== -1;
          });
        }

        if (!nextRows || !nextRows.length) {
          seenKeys = currentKeys.slice();
          saveJobsMailboxSeen(seenKeys);
          nextRows = orderedRows.filter(function (row) {
            var key = row.getAttribute("data-cv-match-mailbox-row") || "";
            return key && currentKeys.indexOf(key) === -1;
          });
        }

        nextRows = (nextRows.length ? nextRows : orderedRows).slice(
          0,
          JOBS_MAILBOX_DESKTOP_LIMIT
        );
        nextKeys = nextRows
          .map(function (row) {
            return row.getAttribute("data-cv-match-mailbox-row") || "";
          })
          .filter(Boolean);

        if (!nextKeys.length) {
          return "";
        }

        root._cvMatchJobsMailboxVisibleKeys = nextKeys;
        nextActiveKey = nextKeys[0] || "";
        syncDesktopJobsMailboxLoadMore(mailbox);

        if (groups && primaryGroup.scrollIntoView) {
          primaryGroup.scrollIntoView({ behavior: "smooth", block: "start" });
        }

        return nextActiveKey;
      }

      function refreshJobsMailboxMobileAppFeed() {
        var mobile = $(root, "[data-cv-match-mailbox-mobileapp]");
        var recommendedList = mobile
          ? $(mobile, '[data-cv-match-mailbox-mobileapp-list="recommended"]')
          : null;
        var inboxSection = mobile
          ? $(mobile, ".sffc-cv-match-studio__jobs-mailbox-mobileapp-inbox")
          : null;
        var items;
        var hiddenKeys;
        var seenKeys;
        var currentVisibleKeys;
        var nextItems;
        var nextKeys;
        var remainingItems;
        var newsletterGroupSlug = getJobsMailboxNewsletterGroupSlug(
          $(root, "[data-cv-match-jobs-mailbox]")
        );

        if (!mobile || !recommendedList) {
          return "";
        }

        items = $all(recommendedList, "[data-cv-match-mailbox-mobileapp-open]");
        hiddenKeys = loadJobsMailboxHidden();
        items = items.filter(function (item) {
          var key =
            item.getAttribute("data-cv-match-mailbox-mobileapp-open") || "";
          return (
            key &&
            hiddenKeys.indexOf(key) === -1 &&
            nodeMatchesJobsMailboxNewsletterGroup(item, newsletterGroupSlug)
          );
        });

        if (!items.length) {
          return "";
        }

        seenKeys = loadJobsMailboxSeen();
        currentVisibleKeys = items
          .filter(function (item) {
            return !item.hidden;
          })
          .map(function (item) {
            return item.getAttribute("data-cv-match-mailbox-mobileapp-open") || "";
          })
          .filter(Boolean);

        nextItems = items.filter(function (item) {
          var key =
            item.getAttribute("data-cv-match-mailbox-mobileapp-open") || "";
          return key && seenKeys.indexOf(key) === -1 && currentVisibleKeys.indexOf(key) === -1;
        });

        if (!nextItems.length) {
          seenKeys = currentVisibleKeys.slice();
          saveJobsMailboxSeen(seenKeys);
          nextItems = items.filter(function (item) {
            var key =
              item.getAttribute("data-cv-match-mailbox-mobileapp-open") || "";
            return key && currentVisibleKeys.indexOf(key) === -1;
          });
        }

        nextItems = (nextItems.length ? nextItems : items).slice(0, 5);
        nextKeys = nextItems
          .map(function (item) {
            return item.getAttribute("data-cv-match-mailbox-mobileapp-open") || "";
          })
          .filter(Boolean);

        if (!nextKeys.length) {
          return "";
        }

        remainingItems = items.filter(function (item) {
          return nextItems.indexOf(item) === -1;
        });

        nextItems.concat(remainingItems).forEach(function (item, index) {
          recommendedList.appendChild(item);
          item.setAttribute("data-cv-match-mailbox-mobileapp-feed-index", String(index));
        });

        saveJobsMailboxSeen(
          nextKeys.concat(
            seenKeys.filter(function (key) {
              return nextKeys.indexOf(key) === -1;
            })
          )
        );

        root._cvMatchJobsMailboxMobileAppExpanded = false;
        syncJobsMailboxMobileApp(nextKeys[0] || "", "feed");

        if (inboxSection && inboxSection.scrollIntoView) {
          inboxSection.scrollIntoView({ behavior: "smooth", block: "start" });
        }

        return nextKeys[0] || "";
      }

      function syncJobsMailboxMobileMostClicked(mobile, hiddenKeys) {
        var list = $(mobile, '[data-cv-match-mailbox-mobileapp-list="most-clicked"]');
        var section = $(mobile, "[data-cv-match-mailbox-mobileapp-mostclicked]");
        var items;
        var clicks;
        var visibleItems;
        var hasTrackedClicks;
        var newsletterGroupSlug = getJobsMailboxNewsletterGroupSlug(
          $(root, "[data-cv-match-jobs-mailbox]")
        );

        if (!list || !section) {
          return;
        }

        items = $all(list, "[data-cv-match-mailbox-mobileapp-open]");
        clicks = loadJobsMailboxClicks();

        visibleItems = items.filter(function (item) {
          var key =
            item.getAttribute("data-cv-match-mailbox-click-key") ||
            item.getAttribute("data-cv-match-mailbox-mobileapp-open") ||
            "";
          return (
            hiddenKeys.indexOf(key) === -1 &&
            nodeMatchesJobsMailboxNewsletterGroup(item, newsletterGroupSlug)
          );
        });

        hasTrackedClicks = visibleItems.some(function (item) {
          var key =
            item.getAttribute("data-cv-match-mailbox-click-key") ||
            item.getAttribute("data-cv-match-mailbox-mobileapp-open") ||
            "";
          return (parseInt(clicks[key] || 0, 10) || 0) > 0;
        });

        visibleItems.sort(function (left, right) {
          var leftKey =
            left.getAttribute("data-cv-match-mailbox-click-key") ||
            left.getAttribute("data-cv-match-mailbox-mobileapp-open") ||
            "";
          var rightKey =
            right.getAttribute("data-cv-match-mailbox-click-key") ||
            right.getAttribute("data-cv-match-mailbox-mobileapp-open") ||
            "";
          var leftClicks = parseInt(clicks[leftKey] || 0, 10) || 0;
          var rightClicks = parseInt(clicks[rightKey] || 0, 10) || 0;
          var leftSeed =
            parseInt(
              left.getAttribute("data-cv-match-mailbox-mostclicked-seed") || "999",
              10
            ) || 999;
          var rightSeed =
            parseInt(
              right.getAttribute("data-cv-match-mailbox-mostclicked-seed") || "999",
              10
            ) || 999;

          if (hasTrackedClicks && rightClicks !== leftClicks) {
            return rightClicks - leftClicks;
          }

          return leftSeed - rightSeed;
        });

        visibleItems.forEach(function (item, index) {
          list.appendChild(item);
          item.hidden = index >= 5;
        });

        items.forEach(function (item) {
          if (visibleItems.indexOf(item) === -1) {
            item.hidden = true;
          }
        });

        section.hidden = visibleItems.length === 0;
      }

      function syncJobsMailboxMobileApp(activeKey, viewMode) {
        var mobile = $(root, "[data-cv-match-mailbox-mobileapp]");
        var hiddenKeys = loadJobsMailboxHidden();
        var itemNodes;
        var details;
        var visibleKeys = [];
        var expanded = !!root._cvMatchJobsMailboxMobileAppExpanded;
        var visibleFeedCount = 0;
        var loadMoreButton;
        var selectedKey = String(activeKey || "").trim();
        var nextView = String(viewMode || "").trim() || "feed";
        var newsletterGroupSlug = getJobsMailboxNewsletterGroupSlug(
          $(root, "[data-cv-match-jobs-mailbox]")
        );

        if (!mobile) {
          return;
        }

        itemNodes = $all(mobile, "[data-cv-match-mailbox-mobileapp-open]");
        details = $all(mobile, "[data-cv-match-mailbox-mobileapp-detail]");

        itemNodes.forEach(function (node) {
          var key =
            node.getAttribute("data-cv-match-mailbox-mobileapp-open") || "";
          var group =
            node.getAttribute("data-cv-match-mailbox-mobileapp-group") || "recommended";
          var visible =
            hiddenKeys.indexOf(key) === -1 &&
            nodeMatchesJobsMailboxNewsletterGroup(node, newsletterGroupSlug);
          var feedIndex = parseInt(
            node.getAttribute("data-cv-match-mailbox-mobileapp-feed-index") || "0",
            10
          );
          var withinFeedLimit =
            group !== "recommended" ||
            expanded ||
            Number.isNaN(feedIndex) ||
            feedIndex < 5;
          node.hidden = !visible || !withinFeedLimit;
          if (visible) {
            visibleKeys.push(key);
          }
          if (visible && group === "recommended") {
            visibleFeedCount += 1;
          }
        });

        syncJobsMailboxMobileMostClicked(mobile, hiddenKeys);

        if (!selectedKey || visibleKeys.indexOf(selectedKey) === -1) {
          selectedKey = visibleKeys[0] || "";
        }

        itemNodes.forEach(function (node) {
          var isActive =
            (node.getAttribute("data-cv-match-mailbox-mobileapp-open") || "") ===
            selectedKey;
          node.classList.toggle("is-active", isActive);
        });

        details.forEach(function (detail) {
          var detailKey =
            detail.getAttribute("data-cv-match-mailbox-mobileapp-detail") || "";
          var isActive =
            detailKey === selectedKey && nextView === "detail";
          if (isActive) {
            if (detail._cvMatchMobileDetailTimer) {
              window.clearTimeout(detail._cvMatchMobileDetailTimer);
              detail._cvMatchMobileDetailTimer = 0;
            }
            detail.hidden = false;
            window.requestAnimationFrame(function () {
              detail.classList.add("is-active");
            });
          } else {
            detail.classList.remove("is-active");
            if (!detail.hidden) {
              if (detail._cvMatchMobileDetailTimer) {
                window.clearTimeout(detail._cvMatchMobileDetailTimer);
              }
              detail._cvMatchMobileDetailTimer = window.setTimeout(function () {
                detail.hidden = true;
                detail._cvMatchMobileDetailTimer = 0;
              }, 240);
            } else {
              detail.hidden = true;
            }
          }
        });

        mobile.setAttribute(
          "data-mobile-view",
          visibleKeys.length > 0 ? nextView : "feed"
        );
        root.classList.toggle(
          "is-mailbox-mobile-detail-open",
          visibleKeys.length > 0 && nextView === "detail"
        );
        loadMoreButton = $(mobile, "[data-cv-match-mailbox-mobileapp-loadmore]");
        if (loadMoreButton) {
          loadMoreButton.hidden = expanded || visibleFeedCount <= 5;
        }
        root._cvMatchJobsMailboxMobileAppKey = selectedKey;
      }

      function getVisibleJobsMailboxButtons(mailbox) {
        return $all(mailbox, "[data-cv-match-mailbox-open]").filter(function (button) {
          var row =
            button.closest("[data-cv-match-mailbox-row]") ||
            button.closest("[data-cv-match-mailbox-mostclicked-row]") ||
            button.closest("[data-cv-match-mailbox-recent-row]");
          return (
            !button.hidden &&
            (!row ||
              (!row.hidden &&
                !row.classList.contains("is-hidden-role") &&
                !row.classList.contains("is-collapsed-extra")))
          );
        });
      }

      function applyJobsMailboxFilter(filterName) {
        var mailbox = $(root, "[data-cv-match-jobs-mailbox]");
        var buttons;
        var filters;
        var firstVisibleKey = "";
        var visibleRows = [];
        var searchTokens = [];

        if (!mailbox) {
          return;
        }

        clearJobsMailboxNewsletterGroup(mailbox);

        renderJobsMailboxKeywords(mailbox);
        searchTokens = getJobsMailboxSearchTokens(mailbox);

        buttons = $all(mailbox, "[data-cv-match-mailbox-open]");
        filters = $all(mailbox, "[data-cv-match-mailbox-filter]");

        filters.forEach(function (button) {
          var isActive =
            (button.getAttribute("data-cv-match-mailbox-filter") || "") ===
            filterName;
          button.classList.toggle(
            "is-active",
            isActive
          );
          button.setAttribute("aria-pressed", isActive ? "true" : "false");
        });

        if (filterName !== "all") {
          mailbox._cvMatchRefreshPromptVisible = false;
          mailbox._cvMatchRefreshPromptIsScrollingUp = false;
          mailbox.classList.remove("is-scrolling-up");
        }

        buttons.forEach(function (button) {
          var fit = button.getAttribute("data-cv-match-mailbox-fit") || "";
          var packReady =
            button.getAttribute("data-cv-match-mailbox-pack-ready") === "true";
          var row = button.closest("[data-cv-match-mailbox-row]");
          var isPinned =
            !!row &&
            !!row.closest(
              '[data-cv-match-mailbox-group="pinned"]'
            );
          var isDismissed = row && row.classList.contains("is-hidden-role");
          var visible =
            !isDismissed &&
            rowMatchesJobsMailboxSearch(row, searchTokens) &&
            (filterName === "all" ||
              (filterName === "pinned" && isPinned) ||
              (filterName === "packs" && packReady) ||
              fit === filterName);

          button.hidden = !visible;
          if (row) {
            row.hidden = !visible;
            if (filterName !== "all" || searchTokens.length) {
              row.classList.remove("is-collapsed-extra");
            }
            if (visible && visibleRows.indexOf(row) === -1) {
              visibleRows.push(row);
            }
          }
          if (visible && !firstVisibleKey) {
            firstVisibleKey = button.getAttribute("data-cv-match-mailbox-open") || "";
          }
        });

        $all(mailbox, "[data-cv-match-mailbox-group]").forEach(function (group) {
          var groupRows = $all(group, "[data-cv-match-mailbox-row]");
          if (!groupRows.length) {
            return;
          }
          group.hidden = !groupRows.some(function (row) {
            return !row.hidden && !row.classList.contains("is-hidden-role");
          });
        });

        if (filterName === "all" && !searchTokens.length) {
          $all(mailbox, "[data-cv-match-mailbox-group]").forEach(function (group) {
            group.hidden = false;
          });
          syncDesktopJobsMailboxPinned(mailbox);
          syncDesktopJobsMailboxRecent(mailbox);
          syncDesktopJobsMailboxLoadMore(mailbox);
        } else {
          syncJobsMailboxRefreshPrompt(mailbox);
        }

        syncJobsMailboxState(firstVisibleKey);
      }

      function getJobsMailboxActiveFilter(mailbox) {
        var activeFilter = mailbox
          ? $(mailbox, "[data-cv-match-mailbox-filter].is-active")
          : null;

        return activeFilter
          ? activeFilter.getAttribute("data-cv-match-mailbox-filter") || "all"
          : "all";
      }

      function getJobsMailboxSearchMount() {
        return $(root, '[data-cv-match-state="jobs-mailbox"]');
      }

      function setJobsMailboxLoading(mailbox, isLoading) {
        var groups;
        var loader;

        if (!mailbox) {
          return;
        }

        groups = $(mailbox, ".sffc-cv-match-studio__jobs-mailbox-groups");
        loader = groups
          ? $(groups, "[data-cv-match-mailbox-loader]")
          : null;

        mailbox.classList.toggle("is-loading", !!isLoading);

        if (loader) {
          loader.hidden = !isLoading;
        }
      }

      function startJobsMailboxLoader(mailbox) {
        var percentNode;
        var statusNode;
        var barNode;
        var steps;
        var progress;
        var timer;

        if (!mailbox) {
          return;
        }

        percentNode = $(mailbox, "[data-cv-match-mailbox-loader-percent]");
        statusNode = $(mailbox, "[data-cv-match-mailbox-loader-status]");
        barNode = $(mailbox, "[data-cv-match-mailbox-loader-bar]");
        steps = [
          { at: 18, text: "Scanning role titles" },
          { at: 42, text: "Matching recruiter signals" },
          { at: 67, text: "Refreshing fit and market context" },
          { at: 84, text: "Rebuilding your mailbox" },
        ];
        progress = 12;

        setJobsMailboxLoading(mailbox, true);

        if (percentNode) {
          percentNode.textContent = progress + "%";
        }
        if (statusNode) {
          statusNode.textContent = steps[0].text;
        }
        if (barNode) {
          barNode.style.width = progress + "%";
        }

        if (root._cvMatchJobsMailboxLoaderTimer) {
          window.clearInterval(root._cvMatchJobsMailboxLoaderTimer);
          root._cvMatchJobsMailboxLoaderTimer = null;
        }

        timer = window.setInterval(function () {
          var nextStep = steps[0];

          progress = Math.min(92, progress + (progress < 50 ? 7 : 4));

          steps.forEach(function (step) {
            if (progress >= step.at) {
              nextStep = step;
            }
          });

          if (percentNode) {
            percentNode.textContent = progress + "%";
          }
          if (statusNode) {
            statusNode.textContent = nextStep.text;
          }
          if (barNode) {
            barNode.style.width = progress + "%";
          }
        }, 220);

        root._cvMatchJobsMailboxLoaderTimer = timer;
      }

      function stopJobsMailboxLoader(mailbox) {
        var percentNode;
        var statusNode;
        var barNode;

        if (root._cvMatchJobsMailboxLoaderTimer) {
          window.clearInterval(root._cvMatchJobsMailboxLoaderTimer);
          root._cvMatchJobsMailboxLoaderTimer = null;
        }

        if (!mailbox) {
          return;
        }

        percentNode = $(mailbox, "[data-cv-match-mailbox-loader-percent]");
        statusNode = $(mailbox, "[data-cv-match-mailbox-loader-status]");
        barNode = $(mailbox, "[data-cv-match-mailbox-loader-bar]");

        if (percentNode) {
          percentNode.textContent = "100%";
        }
        if (statusNode) {
          statusNode.textContent = "Mailbox refreshed";
        }
        if (barNode) {
          barNode.style.width = "100%";
        }

        window.setTimeout(function () {
          setJobsMailboxLoading(mailbox, false);
        }, 140);
      }

      function loadJobsMailboxSearchResults(query, options) {
        var mount = getJobsMailboxSearchMount();
        var mailbox = mount ? $(mount, "[data-cv-match-jobs-mailbox]") : null;
        var requestBody;
        var normalizedQuery = String(query || "").trim();
        var activeFilter = "all";
        var activeButton = null;
        var activeKey = "";
        var activeNewsletterGroup = getPendingJobsMailboxNewsletterGroup();
        var requestId;

        options = options || {};

        if (!mount || !config.ajaxUrl || !config.nonce) {
          return Promise.resolve();
        }

        if (mailbox) {
          activeFilter = getJobsMailboxActiveFilter(mailbox);
          activeButton = $(mailbox, "[data-cv-match-mailbox-open].is-active");
          activeKey = activeButton
            ? activeButton.getAttribute("data-cv-match-mailbox-open") || ""
            : "";
          if (!activeNewsletterGroup) {
            activeNewsletterGroup = {
              slug: getJobsMailboxNewsletterGroupSlug(mailbox),
              label: String(mailbox._cvMatchNewsletterGroupLabel || "").trim(),
            };
            if (!activeNewsletterGroup.slug) {
              activeNewsletterGroup = null;
            }
          }
          startJobsMailboxLoader(mailbox);
        }

        requestBody = new window.FormData();
        requestBody.append("action", "sffc_cv_match_jobs_mailbox_search");
        requestBody.append("nonce", config.nonce || "");
        requestBody.append("search", normalizedQuery);
        requestBody.append("active_key", activeKey);
        requestBody.append("cv_text", getCurrentCvText());
        requestBody.append("preferred_industry", getSelectedPreferredIndustry());

        requestId = (root._cvMatchJobsMailboxSearchRequestId || 0) + 1;
        root._cvMatchJobsMailboxSearchRequestId = requestId;
        root._cvMatchJobsMailboxSearchQuery = normalizedQuery;

        return window
          .fetch(config.ajaxUrl, {
            method: "POST",
            credentials: "same-origin",
            body: requestBody,
          })
          .then(parseAjaxJson)
          .then(function (response) {
            var nextMailbox;
            var nextSearchInput;

            if (requestId !== root._cvMatchJobsMailboxSearchRequestId) {
              return;
            }

            if (
              !response ||
              response.success !== true ||
              !response.data ||
              typeof response.data.html !== "string"
            ) {
              throw new Error("Unable to refresh mailbox.");
            }

            mount.innerHTML = response.data.html;
            stopJobsMailboxPlaceholderLoading();
            root._cvMatchJobsMailboxHydrated = true;
            nextMailbox = $(mount, "[data-cv-match-jobs-mailbox]");
            nextSearchInput = $(mount, "[data-cv-match-mailbox-search-input]");

            if (nextSearchInput) {
              nextSearchInput.value = normalizedQuery;
              if (options.focusInput) {
                nextSearchInput.focus();
                nextSearchInput.setSelectionRange(
                  normalizedQuery.length,
                  normalizedQuery.length
                );
              }
            }

            if (nextMailbox) {
              syncJobsMailboxState(activeKey);
              syncJobsMailboxMobileApp(activeKey, "feed");
              if (activeNewsletterGroup && activeNewsletterGroup.slug) {
                applyJobsMailboxNewsletterGroupFilter(
                  activeNewsletterGroup.slug,
                  activeNewsletterGroup.label
                );
              } else if (activeFilter && activeFilter !== "all") {
                applyJobsMailboxFilter(activeFilter);
              }
            }
          })
          .catch(function () {
            var input = $(mount, "[data-cv-match-mailbox-search-input]");
            if (input && options.focusInput) {
              input.focus();
            }
          })
          .finally(function () {
            var refreshedMailbox =
              mount && $(mount, "[data-cv-match-jobs-mailbox]");
            if (refreshedMailbox) {
              stopJobsMailboxLoader(refreshedMailbox);
            } else if (mailbox) {
              stopJobsMailboxLoader(mailbox);
            }
          });
      }

      function ensureJobsMailboxLoaded(forceReload) {
        var mount = getJobsMailboxSearchMount();
        var mailbox = mount ? $(mount, "[data-cv-match-jobs-mailbox]") : null;

        if (!mount || root._cvMatchJobsMailboxLoading) {
          return root._cvMatchJobsMailboxLoadingPromise || Promise.resolve();
        }

        if (!forceReload && (mailbox || root._cvMatchJobsMailboxHydrated)) {
          return Promise.resolve();
        }

        startJobsMailboxPlaceholderLoading();
        root._cvMatchJobsMailboxLoading = true;
        root._cvMatchJobsMailboxLoadingPromise = loadJobsMailboxSearchResults("", {})
          .finally(function () {
            root._cvMatchJobsMailboxLoading = false;
            root._cvMatchJobsMailboxLoadingPromise = null;
            stopJobsMailboxPlaceholderLoading(true);
          });
        return root._cvMatchJobsMailboxLoadingPromise;
      }

      function queueJobsMailboxSearch(input) {
        var normalizedQuery;
        var mailbox;
        var activeFilter;

        if (!input || !root.contains(input)) {
          return;
        }

        normalizedQuery = String(input.value || "").trim();
        root._cvMatchJobsMailboxSearchQuery = normalizedQuery;

        if (root._cvMatchJobsMailboxSearchTimer) {
          window.clearTimeout(root._cvMatchJobsMailboxSearchTimer);
        }

        root._cvMatchJobsMailboxSearchTimer = window.setTimeout(function () {
          mailbox = $(root, "[data-cv-match-jobs-mailbox]");
          activeFilter = getJobsMailboxActiveFilter(mailbox);

          if (mailbox && getJobsMailboxNewsletterGroupSlug(mailbox)) {
            applyJobsMailboxNewsletterGroupFilter(
              getJobsMailboxNewsletterGroupSlug(mailbox),
              String(mailbox._cvMatchNewsletterGroupLabel || "")
            );
            return;
          }

          applyJobsMailboxFilter(activeFilter);
        }, 80);
      }

      function performJobsMailboxAction(action, source) {
        var mailbox = $(root, "[data-cv-match-jobs-mailbox]");
        var visibleButtons;
        var activeButton;
        var activeIndex;
        var activePane;
        var materialButton;
        var quickViewButton;
        var context;
        var trackerToggle;

        if (!mailbox || !action) {
          return;
        }

        visibleButtons = getVisibleJobsMailboxButtons(mailbox);
        activeButton =
          mailbox.querySelector("[data-cv-match-mailbox-open].is-active:not([hidden])") ||
          visibleButtons[0] ||
          null;
        activeIndex = activeButton ? visibleButtons.indexOf(activeButton) : -1;
        activePane =
          mailbox.querySelector("[data-cv-match-mailbox-pane].is-active:not([hidden])") ||
          null;
        context = getJobsMailboxContextFromNode(activePane);

        if (action === "prev" || action === "next") {
          if (!visibleButtons.length) {
            return;
          }
          if (activeIndex < 0) {
            activeIndex = 0;
          }
          activeIndex += action === "next" ? 1 : -1;
          if (activeIndex < 0) {
            activeIndex = visibleButtons.length - 1;
          }
          if (activeIndex >= visibleButtons.length) {
            activeIndex = 0;
          }
          syncJobsMailboxState(
            visibleButtons[activeIndex].getAttribute("data-cv-match-mailbox-open") || ""
          );
          return;
        }

        if (action === "close") {
          setState(root, "results");
          return;
        }

        if (action === "smart-apply") {
          handleJobsMailboxMenuAction("smart-apply", source);
          return;
        }

        if (action === "not-interested") {
          var nextKey = "";
          var remainingButtons;
          if (context && context.mailboxKey) {
            remainingButtons = visibleButtons.filter(function (button) {
              return (
                (button.getAttribute("data-cv-match-mailbox-open") || "") !==
                context.mailboxKey
              );
            });
            if (remainingButtons.length) {
              if (activeIndex < 0) {
                activeIndex = 0;
              }
              if (activeIndex >= remainingButtons.length) {
                activeIndex = remainingButtons.length - 1;
              }
              nextKey =
                remainingButtons[activeIndex].getAttribute(
                  "data-cv-match-mailbox-open"
                ) || "";
            }
            addJobsMailboxHidden(context.mailboxKey);
            syncJobsMailboxState(nextKey);
            setFeedback(
              root,
              (context.roleTitle || "This role") + " removed from your mailbox.",
              false
            );
          }
          return;
        }

        if (action === "tracker") {
          trackerToggle =
            activePane &&
            $(activePane, "[data-cv-match-mailbox-tracker-toggle]");
          if (trackerToggle) {
            toggleJobsMailboxTrackerMenu(trackerToggle);
          }
          return;
        }

        if (action === "summary") {
          if (activePane) {
            var summaryBlock = activePane.querySelector(
              ".sffc-cv-match-studio__jobs-mailbox-summary"
            );
            if (summaryBlock) {
              summaryBlock.scrollIntoView({ behavior: "smooth", block: "start" });
            }
          }
          return;
        }

        if (action === "materials") {
          if (!activePane) {
            return;
          }
          materialButton = activePane.querySelector("[data-cv-match-mailbox-materials-open]");
          if (materialButton) {
            materialButton.click();
          }
          return;
        }

        if (action === "open-role") {
          if (!activePane) {
            return;
          }
          quickViewButton = activePane.querySelector("[data-open-role-quick-view]");
          if (quickViewButton) {
            quickViewButton.click();
          }
        }
      }

      function fetchMessagesModal(conversationId) {
        var body;

        if (!config.ajaxUrl || !config.accountNonce) {
          return Promise.reject(new Error("Messages are unavailable."));
        }

        body = new window.FormData();
        body.append("action", "sffc_crm_reddit_dashboard_inbox");
        body.append("nonce", config.accountNonce);
        if (conversationId) {
          body.append("conversation_id", String(conversationId));
        }

        if (messagesDialog) {
          messagesDialog.innerHTML =
            '<div class="sffc-cv-match-studio__messages-modal-loading">Loading messages...</div>';
        }
        if (messagesModal) {
          messagesModal.hidden = false;
          messagesModal.setAttribute("aria-hidden", "false");
          root.classList.add("is-messages-modal-open");
        }

        return window
          .fetch(config.ajaxUrl, {
            method: "POST",
            credentials: "same-origin",
            body: body,
          })
          .then(parseAjaxJson)
          .then(function (response) {
            if (
              !response ||
              response.success !== true ||
              !response.data ||
              typeof response.data.markup !== "string"
            ) {
              throw new Error("Unable to load messages.");
            }

            openMessagesModalWithMarkup(
              response.data.markup,
              response.data.unread_count || 0
            );
            closeMainPanels();
            return response.data;
          })
          .catch(function (error) {
            if (messagesDialog) {
              messagesDialog.innerHTML =
                '<div class="sffc-cv-match-studio__messages-modal-error">' +
                escapeHtml(error && error.message ? error.message : "Unable to load messages.") +
                "</div>";
            }
            throw error;
          });
      }

      function startMessageConversation(recipientUserId) {
        var body;

        if (!config.ajaxUrl || !config.accountNonce || !recipientUserId) {
          return Promise.reject(new Error("Messaging is unavailable."));
        }

        body = new window.FormData();
        body.append("action", "sffc_crm_reddit_dashboard_start_message");
        body.append("nonce", config.accountNonce);
        body.append("recipient_user_id", String(recipientUserId));

        return window
          .fetch(config.ajaxUrl, {
            method: "POST",
            credentials: "same-origin",
            body: body,
          })
          .then(parseAjaxJson)
          .then(function (response) {
            if (
              !response ||
              response.success !== true ||
              !response.data ||
              typeof response.data.markup !== "string"
            ) {
              throw new Error("Unable to start that conversation.");
            }

            openMessagesModalWithMarkup(
              response.data.markup,
              response.data.unread_count || 0
            );
            return response.data;
          });
      }

      function markPreviewNotificationRead(notificationId, item) {
        var body;

        if (!notificationId || !config.ajaxUrl || !(config.crmNonce || config.nonce)) {
          return Promise.resolve();
        }

        body = new window.FormData();
        body.append("action", "sffc_crm_mark_notification_read");
        body.append("nonce", config.crmNonce || config.nonce || "");
        body.append("notification_id", String(notificationId));

        return window.fetch(config.ajaxUrl, {
          method: "POST",
          credentials: "same-origin",
          body: body,
        }).then(function () {
          if (item) {
            item.classList.remove("is-unread");
          }
        });
      }

      function setSidebarCollapsed(collapsed) {
        if (isMobileNavViewport()) {
          setMobileNavOpen(!!collapsed);
          return;
        }
        root.classList.toggle("is-sidebar-collapsed", !!collapsed);
        sidebarToggles.forEach(function (toggle) {
          toggle.setAttribute("aria-expanded", collapsed ? "false" : "true");
          toggle.setAttribute(
            "aria-label",
            collapsed ? "Expand sidebar" : "Collapse sidebar"
          );
        });
        saveSidebarCollapsed(!!collapsed);
        window.requestAnimationFrame(syncStandaloneJobDock);
      }

      setSidebarCollapsed(false);
      setNewsletterGroupFilter("jobs");
      root._cvMatchJobsMailboxMobileAppExpanded = false;
      root._cvMatchPreviousState = defaultState;
      if (isMobileNavViewport()) {
        setMobileNavOpen(false);
      }
      if (!isStandaloneJob) {
        setState(root, defaultState);
      }
      if (!isStandaloneJob && defaultState === "jobs-mailbox") {
        ensureJobsMailboxLoaded(false);
        syncJobsMailboxState("");
        syncJobsMailboxMobileApp("", "feed");
      }
      if (!isStandaloneJob && config.onboarding) {
        window.setTimeout(function () {
          if (
            cvOnboardingModal &&
            config.onboarding.shouldShowCvOnboarding
          ) {
            openCvOnboardingModal();
            return;
          }

          if (welcomeModal && config.onboarding.shouldShowWelcome) {
            openWelcomeModal();
          }
        }, 280);
      }
      syncControlledSearchUi();
      syncJobsMailboxState("");
      syncJobsMailboxMobileApp("", "feed");

      function refreshResults() {
        renderResults(root, getVisibleResults(root, activeResults));
      }

      function getControlledSearchBars() {
        return controlledSearchBars
          ? $all(controlledSearchBars, "[data-cv-match-controlled-bar]")
          : [];
      }

      function getControlledSearchFilters() {
        return getControlledSearchBars().map(function (bar) {
          return {
            role: String(
              ($(bar, "[data-cv-match-controlled-role]") || {}).value || ""
            ).trim(),
            seniority: String(
              ($(bar, "[data-cv-match-controlled-seniority]") || {}).value || ""
            ).trim(),
            location: String(
              ($(bar, "[data-cv-match-controlled-location]") || {}).value || ""
            ).trim(),
            specialisation: String(
              ($(bar, "[data-cv-match-controlled-specialisation]") || {}).value ||
                ""
            ).trim(),
          };
        });
      }

      function controlledSearchHasFilters(filters) {
        var values = Array.isArray(filters) ? filters : getControlledSearchFilters();
        return values.some(function (entry) {
          return !!(
            entry.role ||
            entry.seniority ||
            entry.location ||
            entry.specialisation
          );
        });
      }

      function controlledSearchSummary(filters) {
        var values = Array.isArray(filters) ? filters : getControlledSearchFilters();
        return values
          .map(function (entry) {
            return [
              entry.role,
              entry.seniority,
              entry.location,
              entry.specialisation,
            ]
              .filter(Boolean)
              .join(" · ");
          })
          .filter(Boolean)
          .join(" / ");
      }

      function setControlledSearchFeedback(message, isError) {
        if (!controlledSearchFeedback) {
          return;
        }
        controlledSearchFeedback.hidden = !message;
        controlledSearchFeedback.textContent = message || "";
        controlledSearchFeedback.classList.toggle("is-error", !!isError);
        controlledSearchFeedback.classList.toggle(
          "is-success",
          !!message && !isError
        );
      }

      function syncControlledSearchUi() {
        var bars = getControlledSearchBars();

        if (controlledSearchReset) {
          controlledSearchReset.hidden = !root._cvMatchCustomSearchActive;
        }

        if (controlledSearchAdd) {
          controlledSearchAdd.hidden = bars.length >= 5;
        }

        bars.forEach(function (bar, index) {
          var removeButton = $(bar, "[data-cv-match-controlled-remove]");
          bar.classList.toggle("is-additional", index > 0);
          bar.setAttribute("data-criteria-index", String(index + 1));
          if (removeButton) {
            removeButton.hidden = index === 0;
          }
        });
      }

      function buildMatchRequestBody(cvText, filters) {
        var requestBody = new URLSearchParams();
        var values = filters || {};

        requestBody.append("action", "sffc_crm_get_matches");
        requestBody.append("nonce", config.nonce || "");
        requestBody.append("cv_text", cvText);
        requestBody.append("fast_mode", "0");

        if (values.role) {
          requestBody.append("role", values.role);
        }
        if (values.seniority) {
          requestBody.append("seniority", values.seniority);
        }
        if (values.location) {
          requestBody.append("location", values.location);
        }
        if (values.specialisation) {
          requestBody.append("specialisation", values.specialisation);
        }

        return requestBody;
      }

      function buildControlledConsoleCriteria(filters) {
        var values = Array.isArray(filters) ? filters : [];
        var roles = [];
        var sectors = [];
        var locations = [];
        var seniorities = [];
        var criteria = {
          job_title: "",
          sector: [],
          location: [],
          experience_level: [],
          recruiter_firm: [],
          skills_keywords: [],
        };

        values.forEach(function (entry) {
          if (entry.role) {
            roles.push(entry.role);
          }
          if (entry.specialisation) {
            sectors.push(entry.specialisation);
          }
          if (entry.location) {
            locations.push(entry.location);
          }
          if (entry.seniority) {
            seniorities.push(entry.seniority);
          }
        });

        criteria.job_title = roles.join(", ");
        criteria.sector = Array.from(new Set(sectors));
        criteria.location = Array.from(new Set(locations));
        criteria.experience_level = Array.from(new Set(seniorities));

        return criteria;
      }

      function buildControlledSearchBarMarkup() {
        var senioritySelectMarkup =
          controlledSearchSeniority && controlledSearchSeniority.outerHTML
            ? controlledSearchSeniority.outerHTML.replace(
                "<select",
                '<select data-cv-match-controlled-seniority'
              )
            : '<select data-cv-match-controlled-seniority><option value="">All Seniority</option></select>';
        var specialisationSelectMarkup =
          controlledSearchSpecialisation &&
          controlledSearchSpecialisation.outerHTML
            ? controlledSearchSpecialisation.outerHTML.replace(
                "<select",
                '<select data-cv-match-controlled-specialisation'
              )
            : '<select data-cv-match-controlled-specialisation><option value="">All Specialisations</option></select>';

        return (
          '<div class="sffc-cv-match-studio__controlled-search-bar is-additional" data-cv-match-controlled-bar>' +
          '<label class="sffc-cv-match-studio__controlled-search-field">' +
          "<span>Role</span>" +
          '<input type="text" name="role" data-cv-match-controlled-role placeholder="Finance Manager">' +
          "</label>" +
          '<label class="sffc-cv-match-studio__controlled-search-field">' +
          "<span>Seniority</span>" +
          senioritySelectMarkup +
          "</label>" +
          '<label class="sffc-cv-match-studio__controlled-search-field">' +
          "<span>Location</span>" +
          '<input type="text" name="location" data-cv-match-controlled-location placeholder="Global, Remote, or target city">' +
          "</label>" +
          '<label class="sffc-cv-match-studio__controlled-search-field">' +
          "<span>Specialisation</span>" +
          specialisationSelectMarkup +
          "</label>" +
          '<button type="button" class="sffc-cv-match-studio__controlled-search-secondary sffc-cv-match-studio__controlled-search-remove" data-cv-match-controlled-remove>Remove</button>' +
          "</div>"
        );
      }

      function addControlledSearchBar() {
        if (!controlledSearchBars || getControlledSearchBars().length >= 5) {
          return;
        }
        controlledSearchBars.insertAdjacentHTML(
          "beforeend",
          buildControlledSearchBarMarkup()
        );
        syncControlledSearchUi();
      }

      function setCommandStatus(message) {
        if (!commandStatus) {
          return;
        }
        commandStatus.textContent = String(message || "").trim();
      }

      function currentStateName() {
        var activeState = root.querySelector(
          '.sffc-cv-match-studio__state.is-active[data-cv-match-state]'
        );
        return activeState
          ? String(activeState.getAttribute("data-cv-match-state") || "")
          : String(root.getAttribute("data-cv-match-main-state") || defaultState);
      }

      function autoResizeCommandInput() {
        if (!commandInput) {
          return;
        }
        commandInput.style.height = "auto";
        commandInput.style.height = Math.min(Math.max(commandInput.scrollHeight, 24), 148) + "px";
      }

      function autoResizePromptInput() {
        if (!textarea) {
          return;
        }
        textarea.style.height = "auto";
        textarea.style.height = Math.min(Math.max(textarea.scrollHeight, 26), 132) + "px";
      }

      function inputLooksLikeCv(value) {
        var text = String(value || "").trim();
        var normalized = text.toLowerCase();
        var newlineCount = (text.match(/\n/g) || []).length;
        var cvHints = [
          "experience",
          "education",
          "skills",
          "work experience",
          "professional experience",
          "curriculum vitae",
          "internship",
          "analyst",
          "associate",
          "university",
          "bachelor",
          "master",
          "responsibilities"
        ];

        if (!text) {
          return false;
        }

        if (text.length >= 220 || newlineCount >= 2) {
          return true;
        }

        return cvHints.some(function (hint) {
          return normalized.indexOf(hint) !== -1;
        });
      }

      function filterItemsForQuery(items, query) {
        var normalizedQuery = String(query || "").trim().toLowerCase();
        if (!normalizedQuery) {
          return Array.isArray(items) ? items.slice() : [];
        }

        return (Array.isArray(items) ? items : []).filter(function (item) {
          var haystack = [
            item.roleTitle || item.role_title || "",
            item.company || "",
            item.location || "",
            item.sector || "",
            item.seniority || "",
            Array.isArray(item.keywords) ? item.keywords.join(" ") : ""
          ]
            .join(" ")
            .toLowerCase();

          return haystack.indexOf(normalizedQuery) !== -1;
        });
      }

      function runCommandSearch(query) {
        var normalizedQuery = String(query || "").trim();
        var stateName = currentStateName();

        if (!normalizedQuery) {
          if (searchNode) {
            searchNode.value = "";
          }
          refreshResults();
          if (Array.isArray(root._cvMatchLandingTilesItems)) {
            renderLandingTileRoles(root._cvMatchLandingTilesItems, {
              message: config.loggedIn
                ? "Strongest recent matches from your saved CV."
                : "Curated from the private equity roles you opened and the market you arrived from.",
            });
          }
          if (root._cvMatchRecommendedPayload) {
            renderRecommendedRoles(root, root._cvMatchRecommendedPayload);
          }
          setCommandStatus("Upload CV or search roles");
          return;
        }

        if (stateName === "recommended") {
          runRecommendedDatabaseSearch(normalizedQuery);
          setCommandStatus("Searching recommended private equity roles");
          return;
        }

        if (stateName === "landing" && Array.isArray(root._cvMatchLandingTilesItems)) {
          renderLandingTileRoles(
            filterItemsForQuery(root._cvMatchLandingTilesItems, normalizedQuery),
            {
              message: "Filtering suggested roles",
              emptyMessage: "No roles matched that search yet.",
            }
          );
          setCommandStatus("Filtering suggested roles");
          return;
        }

        if (searchNode) {
          searchNode.value = normalizedQuery;
          if (currentStateName() !== "results" && activeResults.length) {
            setState(root, "results");
          }
          refreshResults();
          setCommandStatus("Filtering role matches");
        }
      }

      function submitCommandBar() {
        var value = commandInput ? String(commandInput.value || "").trim() : "";

        if (!value) {
          setCommandStatus("Upload CV or search roles");
          if (commandInput) {
            commandInput.focus();
          }
          return;
        }

        if (inputLooksLikeCv(value)) {
          syncCvTextState(value, commandInput);
          if (textarea) {
            textarea.value = value;
          }
          if (floatingInput) {
            floatingInput.value = value;
          }
          persistCvToProfile(value);
          setCommandStatus(
            config.labels && config.labels.dropReady
              ? config.labels.dropReady
              : "Resume loaded."
          );
          submitScan(commandInput);
          return;
        }

        runCommandSearch(value);
      }

      function renderLandingTileRoles(items, options) {
        var track = $(root, "[data-cv-match-tiles-track]");
        var statusNode = $(root, "[data-cv-match-tiles-status]");
        var cvText = getCurrentCvTextForRoot(root);
        var message = options && options.message ? String(options.message) : "";

        if (!track) {
          return;
        }

        if ((track.getAttribute("data-cv-match-tiles-mode") || "") === "benefits") {
          if (statusNode && message) {
            statusNode.hidden = false;
            statusNode.textContent = message;
          }
          return;
        }

        if (statusNode) {
          statusNode.hidden = true;
          statusNode.textContent = message;
        }

        if (!items.length) {
          track.innerHTML =
            '<article class="sffc-cv-match-studio__tile-role-card sffc-cv-match-studio__tile-role-card--empty">' +
            "<strong>No recommended private equity roles yet</strong>" +
            "<p>" +
            escapeHtml(
              (options && options.emptyMessage) ||
                (config.loggedIn
                  ? "Save or strengthen your CV and MENA Careers will surface stronger recent matches here."
                  : "Open a few roles and MENA Careers will start curating sharper recommendations here.")
            ) +
            "</p>" +
            "</article>";
          return;
        }

        track.innerHTML = items
          .map(function (item) {
            return landingTileRoleCardMarkup(item, cvText);
          })
          .join("");
      }

      function loadLandingTileRoles(force) {
        var track = $(root, "[data-cv-match-tiles-track]");
        var cvText = getCurrentCvTextForRoot(root);
        var recentPayload = getRecentRolesPayload();
        var guestPayload = getGuestSignalsPayload();
        var cacheSignature = JSON.stringify({
          loggedIn: !!config.loggedIn,
          cvHash: hashText(cvText),
          recent: recentPayload,
          guest: guestPayload,
        });
        var requestBody;

        if (!track) {
          return Promise.resolve([]);
        }

        if ((track.getAttribute("data-cv-match-tiles-mode") || "") === "benefits") {
          return Promise.resolve([]);
        }

        if (config.loggedIn && !cvText) {
          renderLandingTileRoles([], {
            message:
              "Save or load a CV and MENA Careers will surface recommended private equity roles here.",
            emptyMessage:
              "Save or load a CV and MENA Careers will surface recommended private equity roles here.",
          });
          return Promise.resolve([]);
        }

        if (!force && root._cvMatchLandingTilesHash === cacheSignature && Array.isArray(root._cvMatchLandingTilesItems)) {
          renderLandingTileRoles(root._cvMatchLandingTilesItems, {
            message: config.loggedIn
              ? "Strongest recent matches from your saved CV."
              : "Curated from the private equity roles you opened and the market you arrived from.",
          });
          return Promise.resolve(root._cvMatchLandingTilesItems);
        }

        renderLandingTileRoles([], {
          message: config.loggedIn
            ? "Loading saved-CV private equity role recommendations…"
            : "Loading guest private equity role recommendations…",
        });

        if (!config.loggedIn) {
          requestBody = new URLSearchParams();
          requestBody.append("action", "sffc_crm_get_recommended_roles");
          requestBody.append("nonce", config.nonce || "");
          requestBody.append("recent_roles", JSON.stringify(recentPayload));
          requestBody.append("guest_context", JSON.stringify(guestPayload));
          requestBody.append(
            "browser_timezone",
            Intl.DateTimeFormat().resolvedOptions().timeZone || ""
          );
          requestBody.append(
            "browser_locale",
            (navigator.languages && navigator.languages[0]) ||
              navigator.language ||
              ""
          );

          return fetch(config.ajaxUrl || "/wp-admin/admin-ajax.php", {
            method: "POST",
            headers: {
              "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
            },
            body: requestBody.toString(),
          })
            .then(parseAjaxJson)
            .then(function (payload) {
              var items;

              if (!payload || !payload.success || !payload.data) {
                throw new Error(
                  payload && payload.data && payload.data.message
                    ? payload.data.message
                    : "Unable to load recommended private equity roles right now."
                );
              }

              items = Array.isArray(payload.data.items)
                ? payload.data.items.map(normalizeItem)
                : [];
              items = sortLandingTileRoles(items).slice(0, 9);
              root._cvMatchLandingTilesItems = items;
              root._cvMatchLandingTilesHash = cacheSignature;
              renderLandingTileRoles(items, {
                message:
                  payload.data.copy ||
                  "Curated from the private equity roles you opened and the market you arrived from.",
                emptyMessage:
                  "Open a few roles and MENA Careers will start curating sharper recommendations here.",
              });
              return items;
            })
            .catch(function (error) {
              renderLandingTileRoles([], {
                message:
                  (error && error.message) ||
                  "Unable to load recommended private equity roles right now.",
                emptyMessage:
                  "Open a few roles and MENA Careers will start curating sharper recommendations here.",
              });
              return [];
            });
        }

        requestBody = new URLSearchParams();
        requestBody.append("action", "sffc_crm_get_matches");
        requestBody.append("nonce", config.nonce || "");
        requestBody.append("cv_text", cvText);
        requestBody.append("fast_mode", "0");

        return fetch(config.ajaxUrl || "/wp-admin/admin-ajax.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
          },
          body: requestBody.toString(),
        })
          .then(parseAjaxJson)
          .then(function (payload) {
            var items;

            if (!payload || !payload.success || !payload.data) {
              throw new Error(
                payload && payload.data && payload.data.message
                  ? payload.data.message
                  : "Unable to load recommended private equity roles right now."
              );
            }

            items = Array.isArray(payload.data.items)
              ? payload.data.items.map(normalizeItem)
              : [];
            items = sortLandingTileRoles(items).slice(0, 9);
            root._cvMatchLandingTilesItems = items;
            root._cvMatchLandingTilesHash = cacheSignature;
            renderLandingTileRoles(items, {
              message: items.length
                ? "Strongest recent matches from your saved CV."
                : "No recommended private equity roles yet for this saved CV.",
            });
            return items;
      })
          .catch(function (error) {
            renderLandingTileRoles([], {
              message:
                (error && error.message) ||
                "Unable to load recommended private equity roles right now.",
            });
            return [];
          });
      }

  function recommendedRoleCardMarkup(item, cvText) {
        var recruiterName = publicRecruiterName(item);
        var matchRating = recommendedMatchRating(item.score);
        var fitClass =
          matchRating === "perfect" || matchRating === "strong"
            ? "strong"
            : matchRating === "ok"
            ? "ok"
            : "weak";
        var roleHref = item.internalPermalink || item.permalink || "#";
        var keywordAttr = escapeHtml(
          (Array.isArray(item.keywords) ? item.keywords : []).join("|")
        );
        var avatarMarkup = item.recruiterPhoto
          ? '<img src="' +
            escapeHtml(item.recruiterPhoto) +
            '" alt="" class="sffc-cv-match-studio__recommended-card-avatar-image">'
          : '<span>' +
            escapeHtml(
              initials(recruiterName || item.recruiterFirm || item.company)
            ) +
            "</span>";
        var postedLabel = relativeTime(item.postedAt) || "Recently added";
        var reason = Array.isArray(item.reasons) && item.reasons.length
          ? item.reasons[0]
          : scoreToneLabel(item.score);
        var senderLabel = recruiterName
          ? recruiterName + (item.company ? " @ " + item.company : "")
          : item.company || item.recruiterFirm || "MENA Careers role";
        var metaLabel = [item.company, item.location].filter(Boolean).join(" • ");
        var durationLabel = String(item.seniority || "").trim();
        var sectorLabel = String(item.sector || "").trim();
        var badgesMarkup =
          durationLabel || sectorLabel
            ? '<span class="sffc-cv-match-studio__recommended-card-badges">' +
              (durationLabel
                ? '<span class="sffc-cv-match-studio__recommended-card-durationbadge"><strong>' +
                  escapeHtml(durationLabel) +
                  "</strong></span>"
                : "") +
              (sectorLabel
                ? '<span class="sffc-cv-match-studio__recommended-card-sectorbadge"><strong>' +
                  escapeHtml(sectorLabel) +
                  "</strong></span>"
                : "") +
              "</span>"
            : "";
        var openAttrs =
          ' href="' +
          escapeHtml(roleHref) +
          '" target="_blank" rel="noopener noreferrer" data-open-role-title="' +
          escapeHtml(item.roleTitle || "Role") +
          '" data-open-role-href="' +
          escapeHtml(roleHref) +
          '" data-open-role-id="' +
          escapeHtml(String(item.jobsPostId || 0)) +
          '" data-open-role-wp-id="' +
          escapeHtml(String(item.wpPostId || 0)) +
          '" data-open-role-crm-id="' +
          escapeHtml(String(item.id || 0)) +
          '" data-open-role-location="' +
          escapeHtml(item.location || "") +
          '" data-open-role-sector="' +
          escapeHtml(item.sector || "") +
          '" data-open-role-seniority="' +
          escapeHtml(item.seniority || "") +
          '" data-open-role-keywords="' +
          keywordAttr +
          '"';
        var quickViewAttrs =
          ' data-open-role-quick-view="1" data-open-role-id="' +
          escapeHtml(String(item.jobsPostId || 0)) +
          '" data-open-role-wp-id="' +
          escapeHtml(String(item.wpPostId || 0)) +
          '" data-open-role-crm-id="' +
          escapeHtml(String(item.id || 0)) +
          '" data-open-role-title="' +
          escapeHtml(item.roleTitle || "Role") +
          '" data-open-role-href="' +
          escapeHtml(item.permalink || roleHref) +
          '" data-open-role-location="' +
          escapeHtml(item.location || "") +
          '" data-open-role-sector="' +
          escapeHtml(item.sector || "") +
          '" data-open-role-seniority="' +
          escapeHtml(item.seniority || "") +
          '" data-open-role-keywords="' +
          keywordAttr +
          '"';

        return (
          '<article class="sffc-cv-match-studio__recommended-card sffc-cv-match-studio__recommended-card--mobile-rebuild" data-cv-match-recommended-rating="' +
          escapeHtml(matchRating) +
          '">' +
          '<span class="sffc-cv-match-studio__recommended-card-accent" aria-hidden="true"></span>' +
          '<span class="sffc-cv-match-studio__recommended-card-avatar sffc-cv-match-studio__recommended-card-avatar--' +
          escapeHtml(fitClass) +
          ' has-match-ring" style="--recommended-card-score: ' +
          escapeHtml(String(item.score || 0)) +
          ';" aria-hidden="true">' +
          avatarMarkup +
          '<span class="sffc-cv-match-studio__recommended-card-score">' +
          escapeHtml(String(item.score || 0) + "%") +
          "</span>" +
          "</span>" +
          '<div class="sffc-cv-match-studio__recommended-card-copy">' +
          '<div class="sffc-cv-match-studio__recommended-card-top">' +
          "<strong>" +
          escapeHtml(senderLabel) +
          "</strong>" +
          "<time>" +
          escapeHtml(postedLabel) +
          "</time>" +
          "</div>" +
          '<a class="sffc-cv-match-studio__recommended-card-title"' +
          openAttrs +
          ">" +
          escapeHtml(item.roleTitle || "Role") +
          "</a>" +
          (metaLabel
            ? '<span class="sffc-cv-match-studio__recommended-card-meta">' +
              escapeHtml(metaLabel) +
              "</span>"
            : "") +
          badgesMarkup +
          '<p class="sffc-cv-match-studio__recommended-card-reason">' +
          escapeHtml(reason) +
          "</p>" +
          '<div class="sffc-cv-match-studio__recommended-card-keywords">' +
          atsKeywordsCellMarkup(item, cvText) +
          "</div>" +
          '<div class="sffc-cv-match-studio__recommended-card-actions">' +
          '<a class="sffc-cv-match-studio__recommended-card-link sffc-cv-match-studio__open-button sffc-cv-match-studio__open-button--listing"' +
          openAttrs +
          ">" +
          "Open role" +
          "</a>" +
          '<button class="sffc-cv-match-studio__recommended-card-button sffc-cv-match-studio__quick-view-button" type="button"' +
          quickViewAttrs +
          ">" +
          "Quick view" +
          "</button>" +
          "</div>" +
          '<button class="sffc-cv-match-studio__recommended-card-pin" type="button" aria-label="Save role" title="Save role">' +
          '<svg viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M10.6 2.8c.3 0 .5.1.7.3l1.6 1.6c.4.4.4 1 0 1.4l-1.5 1.5.5 3.2c.1.5-.5 1-.9.7l-2.4-1.6-2.5 2.5v2.1l-.7.7-.8-.8-.8.8-.7-.7V14l2.5-2.5-1.6-2.4c-.3-.4.1-1 .7-.9l3.2.5 1.5-1.5c.2-.2.4-.3.7-.3h0Z" fill="none" stroke="currentColor" stroke-width="1.1" stroke-linejoin="round" /></svg>' +
          "</button>" +
          "</div>" +
          "</article>"
        );
      }

      function ensureRecommendedFeedState(root) {
        if (!root._cvMatchRecommendedFeedState) {
          root._cvMatchRecommendedFeedState = {
            items: [],
            page: 0,
            perPage: 18,
            visibleCount: 8,
            revealStep: 8,
            hasMore: false,
            loading: false,
            signature: "",
            sort: "most_relevant",
            rating: "all",
            query: "",
          };
        }

        return root._cvMatchRecommendedFeedState;
      }

      function syncRecommendedSearchInputs(value) {
        var normalized = String(value || "");
        recommendedSearchInputs.forEach(function (input) {
          if (input.value !== normalized) {
            input.value = normalized;
          }
        });
      }

      function getSearchTokens(value) {
        return String(value || "")
          .toLowerCase()
          .replace(/\s+/g, " ")
          .trim()
          .split(" ")
          .filter(Boolean);
      }

      function searchTokensMatchText(tokens, value) {
        var haystack = String(value || "").toLowerCase();

        if (!tokens.length) {
          return true;
        }

        return tokens.every(function (token) {
          return haystack.indexOf(token) !== -1;
        });
      }

      function isRecommendedDatabaseSearchNode(node) {
        var stateNode;
        if (!node) {
          return false;
        }

        stateNode = node.closest("[data-cv-match-state]");
        return (
          !!stateNode &&
          stateNode.getAttribute("data-cv-match-state") === "recommended"
        );
      }

      function runRecommendedListSearch(query) {
        var rawQuery = String(query || "");
        var tokens = getSearchTokens(rawQuery);
        var hasQuery = tokens.length > 0;
        var panels = $all(root, "[data-cv-match-newsletter-group-panel]");

        syncRecommendedSearchInputs(rawQuery);

        if (currentStateName() !== "newsletter-groups") {
          setState(root, "newsletter-groups");
        }

        panels.forEach(function (panel) {
          var panelType =
            panel.getAttribute("data-cv-match-newsletter-group-panel") || "";
          var cards = $all(panel, ".sffc-cv-match-studio__newsletter-group-card");
          var matches = 0;

          cards.forEach(function (card) {
            var haystack = [
              card.textContent || "",
              card.getAttribute("data-cv-match-newsletter-group-type") || "",
              card.getAttribute("data-cv-match-newsletter-group-id") || "",
            ].join(" ");
            var matched = searchTokensMatchText(tokens, haystack);
            card.hidden = !matched;
            if (matched) {
              matches += 1;
            }
          });

          if (hasQuery) {
            panel.hidden = matches === 0;
          } else {
            panel.hidden = panelType !== "jobs";
          }
        });
      }

      function scheduleRecommendedListSearch(query) {
        if (recommendedSearchTimer) {
          window.clearTimeout(recommendedSearchTimer);
        }

        recommendedSearchTimer = window.setTimeout(function () {
          recommendedSearchTimer = 0;
          runRecommendedListSearch(query);
        }, 180);
      }

      function runRecommendedDatabaseSearch(query) {
        var feedState = ensureRecommendedFeedState(root);
        var normalizedQuery = String(query || "").trim();

        feedState.query = normalizedQuery;
        feedState.visibleCount = feedState.revealStep;
        syncRecommendedSearchInputs(normalizedQuery);

        if (currentStateName() !== "recommended") {
          setState(root, "recommended");
        }

        return loadRecommendedRoles(true);
      }

      function scheduleRecommendedDatabaseSearch(query) {
        if (recommendedSearchTimer) {
          window.clearTimeout(recommendedSearchTimer);
        }

        recommendedSearchTimer = window.setTimeout(function () {
          recommendedSearchTimer = 0;
          runRecommendedDatabaseSearch(query);
        }, 280);
      }

      function mergeRecommendedFeedItems(existing, incoming) {
        var map = Object.create(null);
        var merged = [];

        existing.concat(incoming).forEach(function (item) {
          var key =
            String(item.id || "") +
            "|" +
            String(item.jobsPostId || "") +
            "|" +
            String(item.wpPostId || "") +
            "|" +
            String(item.permalink || item.internalPermalink || "");

          if (map[key]) {
            return;
          }

          map[key] = true;
          merged.push(item);
        });

        return merged;
      }

      function sortRecommendedFeedItems(items, sortKey) {
        return items.slice().sort(function (left, right) {
          var leftPosted = left.postedAt ? new Date(left.postedAt).getTime() : 0;
          var rightPosted = right.postedAt ? new Date(right.postedAt).getTime() : 0;

          if (sortKey === "most_recent") {
            if (rightPosted !== leftPosted) {
              return rightPosted - leftPosted;
            }

            return (right.score || 0) - (left.score || 0);
          }

          if ((right.score || 0) !== (left.score || 0)) {
            return (right.score || 0) - (left.score || 0);
          }

          return rightPosted - leftPosted;
        });
      }

      function filterRecommendedFeedItems(items, feedState) {
        return items.filter(function (item) {
          var rating = recommendedMatchRating(item.score);

          if (feedState.rating !== "all" && rating !== feedState.rating) {
            return false;
          }

          return true;
        });
      }

      function syncRecommendedSentinel(root) {
        var sentinelNode = $(root, "[data-cv-match-recommended-sentinel]");
        var feedState = ensureRecommendedFeedState(root);
        var filteredItems = filterRecommendedFeedItems(feedState.items, feedState);

        if (!sentinelNode) {
          return;
        }

        sentinelNode.hidden = !(
          feedState.hasMore || filteredItems.length > feedState.visibleCount
        );
      }

      function renderRecommendedRoles(root, payload) {
        var loadingNode = $(root, "[data-cv-match-recommended-loading]");
        var emptyNode = $(root, "[data-cv-match-recommended-empty]");
        var gridNode = $(root, "[data-cv-match-recommended-grid]");
        var kickerNode = $(root, "[data-cv-match-recommended-kicker]");
        var titleNode = $(root, "[data-cv-match-recommended-title]");
        var copyNode = $(root, "[data-cv-match-recommended-copy]");
        var feedStatusNode = $(root, "[data-cv-match-recommended-feed-status]");
        var feedState = ensureRecommendedFeedState(root);
        var filteredItems;
        var sortedItems;
        var visibleItems;
        var cvText = getCurrentCvTextForRoot(root);

        if (payload && Array.isArray(payload.items)) {
          feedState.items = payload.items.slice();
          if (typeof payload.hasMore === "boolean") {
            feedState.hasMore = payload.hasMore;
          }
        }

        filteredItems = filterRecommendedFeedItems(feedState.items, feedState);
        sortedItems = sortRecommendedFeedItems(filteredItems, feedState.sort);
        visibleItems = sortedItems.slice(0, feedState.visibleCount);

        if (kickerNode) {
          kickerNode.textContent =
            (payload && payload.kicker) || "Smart ranking";
        }
        if (titleNode) {
          titleNode.textContent =
            (payload && payload.title) || "Latest job lists";
        }
        if (copyNode) {
          if (!filteredItems.length && feedState.items.length && !feedState.loading) {
            copyNode.textContent =
              "Try another sort or match rating to surface more recommended private equity roles.";
          } else {
            copyNode.textContent =
              (payload && payload.copy) ||
              "MENA Careers is curating your best next roles.";
          }
        }
        if (loadingNode) {
          loadingNode.hidden = true;
        }
        if (feedStatusNode) {
          feedStatusNode.hidden = !feedState.loading || !feedState.page;
        }
        if (emptyNode) {
          emptyNode.hidden = filteredItems.length > 0 || feedState.loading;
        }
        if (!gridNode) {
          syncRecommendedSentinel(root);
          return;
        }

        if (!visibleItems.length) {
          gridNode.hidden = true;
          gridNode.innerHTML = "";
          syncRecommendedSentinel(root);
          return;
        }

        gridNode.hidden = false;
        gridNode.innerHTML = visibleItems
          .map(function (item) {
            return recommendedRoleCardMarkup(item, cvText);
          })
          .join("");
        syncRecommendedSentinel(root);
      }

      function loadRecommendedRoles(force) {
        var loadingNode = $(root, "[data-cv-match-recommended-loading]");
        var emptyNode = $(root, "[data-cv-match-recommended-empty]");
        var gridNode = $(root, "[data-cv-match-recommended-grid]");
        var feedStatusNode = $(root, "[data-cv-match-recommended-feed-status]");
        var recentPayload = getRecentRolesPayload();
        var cvText = getCurrentCvTextForRoot(root);
        var searchQuery;
        var signature;
        var requestBody;
        var feedState = ensureRecommendedFeedState(root);
        var nextPage = force ? 1 : feedState.page + 1;

        searchQuery = String(feedState.query || "").trim();
        signature = JSON.stringify({
          recent: recentPayload,
          cvHash: hashText(cvText),
          loggedIn: !!config.loggedIn,
          query: searchQuery,
        });

        if (feedState.loading) {
          return Promise.resolve(root._cvMatchRecommendedPayload || null);
        }

        if (
          !force &&
          root._cvMatchRecommendedSignature === signature &&
          root._cvMatchRecommendedPayload &&
          !feedState.hasMore
        ) {
          renderRecommendedRoles(root, root._cvMatchRecommendedPayload);
          return Promise.resolve(root._cvMatchRecommendedPayload);
        }

        if (force || feedState.signature !== signature) {
          feedState.items = [];
          feedState.page = 0;
          feedState.visibleCount = feedState.revealStep;
          feedState.hasMore = false;
          feedState.signature = signature;
          root._cvMatchRecommendedPayload = null;
        }

        feedState.loading = true;

        if (loadingNode && nextPage === 1) {
          loadingNode.hidden = false;
        }
        if (emptyNode) {
          emptyNode.hidden = true;
        }
        if (gridNode && nextPage === 1) {
          gridNode.hidden = true;
        }
        if (feedStatusNode) {
          feedStatusNode.hidden = nextPage === 1;
        }

        requestBody = new URLSearchParams();
        requestBody.append("action", "sffc_crm_get_recommended_roles");
        requestBody.append("nonce", config.nonce || "");
        requestBody.append("page", String(nextPage));
        requestBody.append("per_page", String(feedState.perPage));
        requestBody.append("search", searchQuery);
        requestBody.append("recent_roles", JSON.stringify(recentPayload));
        requestBody.append(
          "browser_timezone",
          Intl.DateTimeFormat().resolvedOptions().timeZone || ""
        );
        requestBody.append(
          "browser_locale",
          (navigator.languages && navigator.languages[0]) ||
            navigator.language ||
            ""
        );

        return fetch(config.ajaxUrl || "/wp-admin/admin-ajax.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
          },
          body: requestBody.toString(),
        })
          .then(parseAjaxJson)
          .then(function (payload) {
            var responsePayload;
            var incomingItems;

            if (!payload || !payload.success || !payload.data) {
              throw new Error(
                payload && payload.data && payload.data.message
                  ? payload.data.message
                  : "Unable to load recommended private equity roles right now."
              );
            }

            incomingItems = Array.isArray(payload.data.items)
              ? payload.data.items.map(normalizeItem)
              : [];
            feedState.items = mergeRecommendedFeedItems(feedState.items, incomingItems);
            feedState.page = Number(payload.data.page || nextPage);
            feedState.hasMore = !!payload.data.has_more;
            feedState.signature = signature;
            feedState.loading = false;
            responsePayload = {
              mode: payload.data.mode || "",
              kicker: payload.data.kicker || "",
              title: payload.data.title || "",
              copy: payload.data.copy || "",
              items: feedState.items.slice(),
              hasMore: !!payload.data.has_more,
            };
            root._cvMatchRecommendedPayload = responsePayload;
            root._cvMatchRecommendedSignature = signature;
            renderRecommendedRoles(root, responsePayload);
            return responsePayload;
          })
          .catch(function (error) {
            feedState.loading = false;
            renderRecommendedRoles(root, {
              kicker: "Smart ranking",
              title: "Unable to load recommended private equity roles",
              copy:
                (error && error.message) ||
                "Try again after opening a few roles or saving your CV.",
              items: [],
              hasMore: false,
            });
            return null;
          });
      }

      function advanceRecommendedFeed(root) {
        var feedState = ensureRecommendedFeedState(root);
        var filteredItems = filterRecommendedFeedItems(feedState.items, feedState);

        if (filteredItems.length > feedState.visibleCount) {
          feedState.visibleCount += feedState.revealStep;
          renderRecommendedRoles(root, root._cvMatchRecommendedPayload || { items: feedState.items.slice(), hasMore: feedState.hasMore });
          return Promise.resolve();
        }

        if (feedState.hasMore && !feedState.loading) {
          return loadRecommendedRoles(false);
        }

        return Promise.resolve();
      }

      function initRecommendedFeedObserver(root) {
        var sentinelNode = $(root, "[data-cv-match-recommended-sentinel]");
        var feedState = ensureRecommendedFeedState(root);

        if (!sentinelNode || feedState.observer) {
          return;
        }

        feedState.observer = new window.IntersectionObserver(
          function (entries) {
            entries.forEach(function (entry) {
              if (
                entry.isIntersecting &&
                currentStateName() === "recommended"
              ) {
                advanceRecommendedFeed(root);
              }
            });
          },
          {
            root: null,
            rootMargin: "0px 0px 220px 0px",
            threshold: 0.1,
          }
        );

        feedState.observer.observe(sentinelNode);
      }

      function scrollLandingTiles(direction) {
        var track = $(root, "[data-cv-match-tiles-track]");
        var firstCard;
        var styles;
        var gap;
        var step;

        if (!track) {
          return;
        }

        firstCard = track.firstElementChild;
        if (!firstCard) {
          return;
        }

        styles = window.getComputedStyle(track);
        gap = parseFloat(styles.columnGap || styles.gap || "0") || 0;
        step = ((firstCard.getBoundingClientRect().width || 0) + gap) * 2;

        track.scrollBy({
          left: direction === "prev" ? -step : step,
          behavior: "smooth",
        });
      }

      function resetView() {
        if (isStandaloneJob) {
          setFeedback(root, "", false);
          closeMaterialsModal();
          setMobileNavOpen(false);
          return;
        }
        root._cvMatchResultsReportPayload = null;
        root._cvMatchResultsReportAiPayload = null;
        root._cvMatchResultsReportAiHash = "";
        root._cvMatchResultsReportAiStatus = "";
        root._cvMatchCustomSearchActive = false;
        root._cvMatchCustomSearchSummary = "";
        baseResults = [];
        root._cvMatchBaseResults = [];
        activeResults = [];
        syncControlledSearchUi();
        setControlledSearchFeedback("", false);
        setState(root, defaultState);
        if (!isStandaloneJob && defaultState === "jobs-mailbox") {
          ensureJobsMailboxLoaded(false);
        }
        root._cvMatchPreviousState = defaultState;
        setFeedback(root, "", false);
        hideFloatingShell();
        closeMaterialsModal();
        setMobileNavOpen(false);
        if (textarea) {
          textarea.focus();
        }
      }

      function showFloatingShell() {
        if (isStandaloneJob) {
          if (floatingInput) {
            floatingInput.focus();
          }
          return;
        }
        if (!floatingShell) {
          resetView();
          return;
        }
        floatingShell.hidden = false;
        root.setAttribute("data-cv-match-floating", "open");
        if (floatingInput) {
          floatingInput.focus();
        }
      }

      function hideFloatingShell() {
        if (isStandaloneJob) {
          return;
        }
        if (!floatingShell) {
          return;
        }
        floatingShell.hidden = true;
        root.removeAttribute("data-cv-match-floating");
      }

      function getJobBackState() {
        var previousState = String(root._cvMatchPreviousState || "").trim();
        if (previousState === "jobs-mailbox") {
          return "jobs-mailbox";
        }
        return "results";
      }

      function syncJobBackButton() {
        var jobBackButton = $(root, "[data-cv-match-job-back]");
        var targetState = getJobBackState();

        if (!jobBackButton) {
          return;
        }

        jobBackButton.textContent =
          targetState === "jobs-mailbox" ? "Back to Inbox" : "Back to Matches";
      }

      function applyStandaloneJobCvContext(sourceInput, statusNode) {
        var inputNode = sourceInput || floatingInput || textarea;
        var cvText = inputNode ? String(inputNode.value || "").trim() : "";
        var readyMessage =
          config.labels && config.labels.dropReady
            ? config.labels.dropReady
            : "Resume loaded.";

        if (!cvText) {
          setFeedback(
            root,
            config.labels && config.labels.emptyInput
              ? config.labels.emptyInput
              : "Paste your CV first.",
            true
          );
          if (inputNode) {
            inputNode.focus();
          }
          return false;
        }

        syncCvTextState(cvText, inputNode);
        [statusNode, floatingStatus, fileStatus].forEach(function (
          node,
          index,
          list
        ) {
          if (!node || list.indexOf(node) !== index) {
            return;
          }
          updateFileStatus(readyMessage, node);
        });
        setFeedback(root, readyMessage, false);
        persistCvToProfile(cvText);
        return true;
      }

      function syncCvTextState(cvText, sourceInput) {
        var normalized = String(cvText || "").trim();

        if (textarea && sourceInput !== textarea) {
          textarea.value = normalized;
        }

        if (floatingInput && sourceInput !== floatingInput) {
          floatingInput.value = normalized;
        }

        activeCvText = normalized;
        root._cvMatchActiveCvText = normalized;
        invalidateCareerReport();
        if (commandInput && sourceInput !== commandInput) {
          commandInput.value = normalized;
          autoResizeCommandInput();
        }
        autoResizePromptInput();
        syncJobCvRequirements();
        syncApplicationPacksState();
      }

      function syncCvSourceSelects(nextValue) {
        var normalized = String(nextValue || "").trim();

        cvSourceSelects.forEach(function (selectNode) {
          if (selectNode && selectNode.value !== normalized) {
            selectNode.value = normalized;
          }
        });

        activeCvSource = normalized;
      }

      function loadSavedCvSource(sourceValue, triggerNode) {
        var normalized = String(sourceValue || "").trim();
        var requestBody;

        if (!config.loggedIn || !config.accountNonce || !normalized) {
          return Promise.resolve(false);
        }

        syncCvSourceSelects(normalized);
        updateFileStatus("Loading saved CV…", fileStatus);
        updateFileStatus("Loading saved CV…", floatingStatus);

        requestBody = new window.FormData();
        requestBody.append("action", "sffc_crm_reddit_get_cv_source_text");
        requestBody.append("nonce", config.accountNonce);
        requestBody.append("cv_source", normalized);

        return window
          .fetch(config.ajaxUrl || "/wp-admin/admin-ajax.php", {
            method: "POST",
            body: requestBody,
            credentials: "same-origin",
          })
          .then(parseAjaxJson)
          .then(function (payload) {
            var nextCvText;

            if (!payload || !payload.success) {
              throw new Error(
                (payload && payload.data && payload.data.message) ||
                  "We could not load that saved CV."
              );
            }

            nextCvText = String(
              (payload.data && payload.data.cv_text) || ""
            ).trim();

            if (!nextCvText) {
              throw new Error("That saved CV is empty.");
            }

            syncCvTextState(nextCvText);
            persistedCvHash = hashText(nextCvText);
            syncCvSourceSelects(normalized);
            updateFileStatus(
              config.labels && config.labels.dropReady
                ? config.labels.dropReady
                : "Resume loaded. Review the text and run the scan.",
              fileStatus
            );
            updateFileStatus(
              config.labels && config.labels.dropReady
                ? config.labels.dropReady
                : "Resume loaded. Review the text and run the scan.",
              floatingStatus
            );
            setFeedback(root, "Saved CV loaded into the prompt.", false);
            loadLandingTileRoles(true);
            if (triggerNode && typeof triggerNode.blur === "function") {
              triggerNode.blur();
            }
            return true;
          })
          .catch(function (error) {
            setFeedback(
              root,
              error && error.message
                ? error.message
                : "We could not load that saved CV.",
              true
            );
            updateFileStatus("PDF, DOCX, DOC, or TXT", fileStatus);
            updateFileStatus("PDF, DOCX, DOC, or TXT", floatingStatus);
            return false;
          });
      }

      function setExpertCvReviewFeedback(message, isError) {
        if (!expertCvReviewFeedback) {
          return;
        }

        if (!message) {
          expertCvReviewFeedback.hidden = true;
          expertCvReviewFeedback.textContent = "";
          expertCvReviewFeedback.classList.remove("is-error", "is-success");
          return;
        }

        expertCvReviewFeedback.hidden = false;
        expertCvReviewFeedback.textContent = message;
        expertCvReviewFeedback.classList.toggle("is-error", !!isError);
        expertCvReviewFeedback.classList.toggle("is-success", !isError);
      }

      function submitExpertCvReviewRequest() {
        var requestBody;

        if (!expertCvReviewForm) {
          return;
        }

        if (!config.loggedIn) {
          redirectToMembership();
          return;
        }

        requestBody = new window.FormData(expertCvReviewForm);
        requestBody.append("action", "sffc_cv_match_expert_cv_review_request");
        requestBody.append("nonce", config.nonce || "");

        if (expertCvReviewSubmit) {
          expertCvReviewSubmit.disabled = true;
        }
        setExpertCvReviewFeedback("Sending your Career Assessment request…", false);

        window
          .fetch(config.ajaxUrl || "/wp-admin/admin-ajax.php", {
            method: "POST",
            body: requestBody,
            credentials: "same-origin",
          })
          .then(parseAjaxJson)
          .then(function (payload) {
            if (!payload || !payload.success) {
              throw new Error(
                (payload && payload.data && payload.data.message) ||
                  "We could not send your request right now."
              );
            }

            setExpertCvReviewFeedback(
              (payload.data && payload.data.message) ||
                "Your Career Assessment request has been sent.",
              false
            );
            expertCvReviewForm.reset();
          })
          .catch(function (error) {
            setExpertCvReviewFeedback(
              error && error.message
                ? error.message
                : "We could not send your request right now.",
              true
            );
          })
          .finally(function () {
            if (expertCvReviewSubmit) {
              expertCvReviewSubmit.disabled = false;
            }
          });
      }

      function setLinkedinReviewFeedback(message, isError) {
        if (!linkedinReviewFeedback) {
          return;
        }

        linkedinReviewFeedback.hidden = false;
        linkedinReviewFeedback.textContent = message;
        linkedinReviewFeedback.classList.toggle("is-error", !!isError);
        linkedinReviewFeedback.classList.toggle("is-success", !isError);
      }

      function submitLinkedinReviewRequest() {
        var requestBody;

        if (!linkedinReviewForm) {
          return;
        }

        if (!config.loggedIn) {
          redirectToMembership();
          return;
        }

        requestBody = new window.FormData(linkedinReviewForm);
        requestBody.append("action", "sffc_cv_match_linkedin_review_request");
        requestBody.append("nonce", config.nonce || "");

        if (linkedinReviewSubmit) {
          linkedinReviewSubmit.disabled = true;
        }
        setLinkedinReviewFeedback("Sending your Career Assessment request…", false);

        window
          .fetch(config.ajaxUrl || "/wp-admin/admin-ajax.php", {
            method: "POST",
            body: requestBody,
            credentials: "same-origin",
          })
          .then(parseAjaxJson)
          .then(function (payload) {
            if (!payload || !payload.success) {
              throw new Error(
                (payload && payload.data && payload.data.message) ||
                  "We could not send your request right now."
              );
            }

            setLinkedinReviewFeedback(
              (payload.data && payload.data.message) ||
                "Your Career Assessment request has been sent.",
              false
            );
            linkedinReviewForm.reset();
          })
          .catch(function (error) {
            setLinkedinReviewFeedback(
              error && error.message
                ? error.message
                : "We could not send your request right now.",
              true
            );
          })
          .finally(function () {
            if (linkedinReviewSubmit) {
              linkedinReviewSubmit.disabled = false;
            }
          });
      }

      function setInterviewFeedback(message, isError) {
        if (!interviewFeedback) {
          return;
        }

        if (!message) {
          interviewFeedback.hidden = true;
          interviewFeedback.textContent = "";
          interviewFeedback.classList.remove("is-error", "is-success");
          return;
        }

        interviewFeedback.hidden = false;
        interviewFeedback.textContent = message;
        interviewFeedback.classList.toggle("is-error", !!isError);
        interviewFeedback.classList.toggle("is-success", !isError);
      }

      function stopInterviewLoader() {
        if (interviewLoaderTimer) {
          window.clearInterval(interviewLoaderTimer);
          interviewLoaderTimer = null;
        }
      }

      function setInterviewLoaderProgress(progress, statusMessage) {
        var loaderSteps = interviewLoader
          ? $all(interviewLoader, ".sffc-cv-match-studio__interview-loader-steps span")
          : [];
        var loaderBar = interviewLoader
          ? $(interviewLoader, ".sffc-cv-match-studio__interview-loader-bar i")
          : null;
        var loaderPercent = interviewLoader
          ? $(interviewLoader, "[data-cv-match-interview-loader-percent]")
          : null;
        var loaderCopy = interviewLoader
          ? $(interviewLoader, "[data-cv-match-interview-loader-copy]")
          : null;
        var clamped = Math.max(0, Math.min(100, Math.round(progress)));
        var activeIndex = 0;

        if (clamped >= 70) {
          activeIndex = 2;
        } else if (clamped >= 35) {
          activeIndex = 1;
        }

        if (loaderBar) {
          loaderBar.style.width = clamped + "%";
        }

        if (loaderPercent) {
          loaderPercent.textContent = clamped + "%";
        }

        if (loaderCopy && statusMessage) {
          loaderCopy.textContent = statusMessage;
        }

        loaderSteps.forEach(function (step, index) {
          step.classList.toggle("is-active", index === activeIndex);
          step.classList.toggle("is-complete", index < activeIndex);
        });
      }

      function setInterviewLoading(isLoading) {
        var progressPhases;

        stopInterviewLoader();

        if (interviewLoader) {
          interviewLoader.hidden = !isLoading;
        }

        if (!isLoading) {
          return;
        }

        progressPhases = [
          {
            limit: 35,
            duration: 5000,
            message:
              "Reading your saved CV and mapping the strongest experience signals first.",
          },
          {
            limit: 70,
            duration: 6500,
            message:
              "Parsing the role brief and identifying the likely technical and judgment areas.",
          },
          {
            limit: 92,
            duration: 9000,
            message:
              "Generating tailored interview questions and the answer angles the interviewer is testing.",
          },
        ];

        if (!interviewLoader) {
          return;
        }

        interviewLoaderProgress = 8;
        interviewLoaderStartedAt = Date.now();
        setInterviewLoaderProgress(interviewLoaderProgress, progressPhases[0].message);

        interviewLoaderTimer = window.setInterval(function () {
          var elapsed = Date.now() - interviewLoaderStartedAt;
          var cumulativeDuration = 0;
          var previousLimit = 8;
          var target = 96;
          var activePhase = progressPhases[progressPhases.length - 1];

          progressPhases.some(function (phase) {
            var phaseStart = cumulativeDuration;
            var phaseEnd = cumulativeDuration + phase.duration;

            if (elapsed <= phaseEnd) {
              var phaseElapsed = Math.max(0, elapsed - phaseStart);
              var phaseRatio = Math.min(1, phaseElapsed / phase.duration);
              activePhase = phase;
              target = previousLimit + (phase.limit - previousLimit) * phaseRatio;
              return true;
            }

            cumulativeDuration = phaseEnd;
            previousLimit = phase.limit;
            return false;
          });

          if (elapsed > cumulativeDuration) {
            activePhase = progressPhases[progressPhases.length - 1];
            target = Math.min(96, previousLimit + (elapsed - cumulativeDuration) / 2200);
          }

          interviewLoaderProgress = Math.min(
            target,
            interviewLoaderProgress + (interviewLoaderProgress < 70 ? 1.8 : 1)
          );

          setInterviewLoaderProgress(interviewLoaderProgress, activePhase.message);
        }, 220);
      }

      function setSalaryCheckerFeedback(message, isError) {
        if (!salaryCheckerFeedback) {
          return;
        }

        if (!message) {
          salaryCheckerFeedback.hidden = true;
          salaryCheckerFeedback.textContent = "";
          salaryCheckerFeedback.classList.remove("is-error", "is-success");
          return;
        }

        salaryCheckerFeedback.hidden = false;
        salaryCheckerFeedback.textContent = message;
        salaryCheckerFeedback.classList.toggle("is-error", !!isError);
        salaryCheckerFeedback.classList.toggle("is-success", !isError);
      }

      function submitSalaryChecker() {
        var formData;

        if (!salaryCheckerForm) {
          return;
        }

        if (!config.loggedIn) {
          redirectToMembership();
          return;
        }

        formData = new window.FormData(salaryCheckerForm);
        formData.append("action", "sffc_cv_match_salary_checker");
        formData.append("nonce", config.nonce || "");

        if (salaryCheckerSubmit) {
          salaryCheckerSubmit.disabled = true;
          salaryCheckerSubmit.textContent = "Updating…";
        }
        if (salaryCheckerResult) {
          salaryCheckerResult.classList.add("is-loading");
        }
        setSalaryCheckerFeedback("Checking MENA Careers salary database...", false);

        window
          .fetch(config.ajaxUrl || "/wp-admin/admin-ajax.php", {
            method: "POST",
            body: formData,
            credentials: "same-origin",
          })
          .then(parseAjaxJson)
          .then(function (payload) {
            if (!payload || !payload.success) {
              throw new Error(
                (payload && payload.data && payload.data.message) ||
                  "We could not update the salary estimate right now."
              );
            }

            if (salaryCheckerResult && payload.data && payload.data.html) {
              salaryCheckerResult.innerHTML = payload.data.html;
            }
            setSalaryCheckerFeedback(
              payload.data &&
                typeof payload.data.salary_source === "string" &&
                payload.data.salary_source.indexOf("senna_research") === 0
                ? "MENA Careers salary benchmark loaded."
                : "Directional salary benchmark shown.",
              false
            );
          })
          .catch(function (error) {
            setSalaryCheckerFeedback(
              error && error.message
                ? error.message
                : "We could not update the salary estimate right now.",
              true
            );
          })
          .finally(function () {
            if (salaryCheckerSubmit) {
              salaryCheckerSubmit.disabled = false;
              salaryCheckerSubmit.textContent = "Update estimate";
            }
            if (salaryCheckerResult) {
              salaryCheckerResult.classList.remove("is-loading");
            }
          });
      }

      function queueSalaryCheckerUpdate() {
        if (salaryCheckerTimer) {
          window.clearTimeout(salaryCheckerTimer);
        }

        salaryCheckerTimer = window.setTimeout(function () {
          salaryCheckerTimer = 0;
          submitSalaryChecker();
        }, 260);
      }

      function clearSalaryCheckerPrintMode() {
        if (salaryCheckerPrintRoot && salaryCheckerPrintRoot.parentNode) {
          salaryCheckerPrintRoot.parentNode.removeChild(salaryCheckerPrintRoot);
        }
        salaryCheckerPrintRoot = null;
        document.body.classList.remove(
          "sffc-cv-match-studio--printing-salary-report"
        );
      }

      function printSalaryCheckerReport() {
        if (!salaryCheckerResult || !salaryCheckerResult.textContent.trim()) {
          setSalaryCheckerFeedback(
            "Run the benchmark before downloading the report.",
            true
          );
          return;
        }

        if (salaryCheckerResult.classList.contains("is-loading")) {
          setSalaryCheckerFeedback(
            "Wait for the salary report to finish updating first.",
            true
          );
          return;
        }

        document.body.classList.add(
          "sffc-cv-match-studio--printing-salary-report"
        );
        if (salaryCheckerPrintRoot && salaryCheckerPrintRoot.parentNode) {
          salaryCheckerPrintRoot.parentNode.removeChild(salaryCheckerPrintRoot);
        }
        salaryCheckerPrintRoot = document.createElement("div");
        salaryCheckerPrintRoot.className =
          "sffc-cv-match-studio__salary-checker-print-root";
        salaryCheckerPrintRoot.appendChild(salaryCheckerResult.cloneNode(true));
        document.body.appendChild(salaryCheckerPrintRoot);
        setSalaryCheckerFeedback(
          "Choose Save as PDF in the print dialog to download the monthly report.",
          false
        );

        window.setTimeout(function () {
          if (typeof window.print === "function") {
            window.print();
            window.setTimeout(clearSalaryCheckerPrintMode, 1200);
          } else {
            clearSalaryCheckerPrintMode();
            setSalaryCheckerFeedback(
              "Your browser does not support print export from this view.",
              true
            );
          }
        }, 80);
      }

      function submitInterviewQuestions() {
        var roleTitleInput;
        var companyInput;
        var cvSourceInput;
        var jobDescriptionInput;
        var roleTitle;
        var company;
        var cvSource;
        var jobDescription;
        var requestBody;

        if (!interviewForm) {
          return;
        }

        if (!config.loggedIn || !config.hasPremiumAccess) {
          redirectToMembership();
          return;
        }

        roleTitleInput = $(interviewForm, 'input[name="role_title"]');
        companyInput = $(interviewForm, 'input[name="company"]');
        cvSourceInput = $(interviewForm, 'select[name="cv_source"]');
        jobDescriptionInput = $(interviewForm, 'textarea[name="job_description"]');

        roleTitle = roleTitleInput ? String(roleTitleInput.value || "").trim() : "";
        company = companyInput ? String(companyInput.value || "").trim() : "";
        cvSource = cvSourceInput ? String(cvSourceInput.value || "").trim() : "";
        jobDescription = jobDescriptionInput
          ? String(jobDescriptionInput.value || "").trim()
          : "";

        if (!cvSource) {
          setInterviewFeedback(
            "Save a CV in Profile > Career Assessment first so MENA Careers can tailor the interview prep.",
            true
          );
          if (cvSourceInput) {
            cvSourceInput.focus();
          }
          return;
        }

        if (!jobDescription || jobDescription.length < 120) {
          setInterviewFeedback(
            "Paste more of the job description so MENA Careers can build better interview questions.",
            true
          );
          if (jobDescriptionInput) {
            jobDescriptionInput.focus();
          }
          return;
        }

        setInterviewFeedback("", false);
        if (interviewPlaceholder) {
          interviewPlaceholder.hidden = true;
        }
        if (interviewResult) {
          interviewResult.hidden = true;
          interviewResult.innerHTML = "";
        }
        setInterviewLoading(true);

        if (interviewSubmit) {
          interviewSubmit.disabled = true;
          interviewSubmit.textContent = "Generating Questions…";
        }

        requestBody = new window.FormData();
        requestBody.append(
          "action",
          "sffc_crm_reddit_generate_interview_questions_custom"
        );
        requestBody.append("nonce", config.accountNonce || config.nonce || "");
        requestBody.append("role_title", roleTitle);
        requestBody.append("company", company);
        requestBody.append("cv_source", cvSource);
        requestBody.append("job_description", jobDescription);

        window
          .fetch(config.ajaxUrl || "/wp-admin/admin-ajax.php", {
            method: "POST",
            body: requestBody,
            credentials: "same-origin",
          })
          .then(parseAjaxJson)
          .then(function (payload) {
            if (!payload || !payload.success || !payload.data) {
              throw new Error(
                (payload && payload.data && payload.data.message) ||
                  "We could not generate interview questions right now."
              );
            }

            if (interviewResult) {
              interviewResult.innerHTML = String(payload.data.markup || "");
              interviewResult.hidden = false;
            }

            setInterviewFeedback(
              payload.data.title
                ? "Interview prep ready for " + payload.data.title + "."
                : "Interview prep ready.",
              false
            );
          })
          .catch(function (error) {
            if (interviewPlaceholder) {
              interviewPlaceholder.hidden = false;
            }
            setInterviewFeedback(
              error && error.message
                ? error.message
                : "We could not generate interview questions right now.",
              true
            );
          })
          .finally(function () {
            setInterviewLoaderProgress(100, "Interview prep ready. Finalizing your tailored question set.");
            setInterviewLoading(false);
            if (interviewSubmit) {
              interviewSubmit.disabled = false;
              interviewSubmit.textContent = "Generate Interview Questions";
            }
          });
      }

      function persistCvToProfile(cvText, options) {
        var normalized = String(cvText || "").trim();
        var cvHash = normalized ? hashText(normalized) : "";
        var requestBody;
        var force = !!(options && options.force);
        var rejectOnError = !!(options && options.rejectOnError);

        if (!config.loggedIn || !config.accountNonce || !normalized) {
          return Promise.resolve(null);
        }

        if (!force && normalized.length < 180) {
          return Promise.resolve(null);
        }

        if (!force && persistedCvHash && persistedCvHash === cvHash) {
          return Promise.resolve(null);
        }

        if (!force && pendingCvPersistPromise && pendingCvPersistHash === cvHash) {
          return pendingCvPersistPromise;
        }

        requestBody = new window.FormData();
        requestBody.append("action", "sffc_crm_reddit_save_pasted_cv");
        requestBody.append("nonce", config.accountNonce);
        requestBody.append("cv_text", normalized);
        if (options && options.fileName) {
          requestBody.append("file_name", String(options.fileName || ""));
        }

        pendingCvPersistHash = cvHash;
        pendingCvPersistPromise = window
          .fetch(config.ajaxUrl || "/wp-admin/admin-ajax.php", {
            method: "POST",
            body: requestBody,
            credentials: "same-origin",
          })
          .then(parseAjaxJson)
          .then(function (payload) {
            if (!payload || !payload.success) {
              throw new Error(
                (payload && payload.data && payload.data.message) ||
                  "We could not save your CV right now."
              );
            }

            persistedCvHash = cvHash;
            window.dispatchEvent(
              new window.CustomEvent("sffc:cv-updated", {
                detail: {
                  cvText:
                    (payload.data && payload.data.active_cv_text) || normalized,
                  atsState: (payload.data && payload.data.ats_state) || null,
                  reviewMarkup:
                    (payload.data && payload.data.review_markup) || "",
                  source: "cv_match_studio",
                },
              })
            );

            if (payload.data && payload.data.review_markup) {
              var currentReview = document.querySelector(
                "[data-dashboard-review-shell]"
              );
              if (currentReview) {
                currentReview.outerHTML = String(payload.data.review_markup);
              }
            }

            return payload;
          })
          .catch(function (error) {
            if (rejectOnError) {
              throw error;
            }
            return null;
          })
          .finally(function () {
            pendingCvPersistHash = "";
            pendingCvPersistPromise = null;
          });

        return pendingCvPersistPromise;
      }

      function scheduleCvPersist(cvText) {
        if (!config.loggedIn) {
          return;
        }

        if (cvPersistTimer) {
          window.clearTimeout(cvPersistTimer);
        }

        cvPersistTimer = window.setTimeout(function () {
          persistCvToProfile(cvText);
        }, 900);
      }

      function syncModalBodyLock() {
        var shouldLock = !!(
          (smartApplyModal && !smartApplyModal.hidden) ||
          (materialsModal && !materialsModal.hidden) ||
          (supportModal && !supportModal.hidden) ||
          (emailListModal && !emailListModal.hidden) ||
          (cvOnboardingModal && !cvOnboardingModal.hidden) ||
          (welcomeModal && !welcomeModal.hidden) ||
          (tourOverlay && !tourOverlay.hidden)
        );
        document.body.classList.toggle(
          "sffc-cv-match-studio__modal-open",
          shouldLock
        );
      }

      function stopMaterialsLoader() {
        materialsLoaderTimers.forEach(function (timerId) {
          window.clearTimeout(timerId);
        });
        materialsLoaderTimers = [];

        if (materialsLoaderCountdownTimer) {
          window.clearInterval(materialsLoaderCountdownTimer);
          materialsLoaderCountdownTimer = null;
        }
      }

      function startMaterialsLoader(preferredMaterialType) {
        var steps;
        var statusNode;
        var barNode;
        var etaNode;
        var progressNode;
        var loaderConfig = getMaterialsLoaderConfig(preferredMaterialType);
        var remainingSeconds = loaderConfig.etaSeconds || 75;
        var stageMeta = Array.isArray(loaderConfig.steps) ? loaderConfig.steps : [];
        var stageCount = stageMeta.length || 1;
        var delayStep = Math.max(
          3000,
          Math.round((Math.max(remainingSeconds - 4, 10) * 1000) / stageCount)
        );

        stopMaterialsLoader();

        if (!materialsOutput) {
          return;
        }

        materialsOutput.innerHTML = getMaterialsLoaderMarkup(loaderConfig);
        steps = $all(
          materialsOutput,
          "[data-cv-match-materials-loader-step]"
        );
        statusNode = $(
          materialsOutput,
          "[data-cv-match-materials-loader-status]"
        );
        barNode = $(materialsOutput, "[data-cv-match-materials-loader-bar]");
        etaNode = $(materialsOutput, "[data-cv-match-materials-loader-eta]");
        progressNode = $(
          materialsOutput,
          "[data-cv-match-materials-loader-progress]"
        );

        if (barNode && stageMeta[0]) {
          barNode.style.width = String(stageMeta[0].percent || 0) + "%";
        }

        if (etaNode) {
          etaNode.textContent = "~" + remainingSeconds + " seconds";
        }
        if (progressNode && stageMeta[0]) {
          progressNode.textContent = String(stageMeta[0].percent || 0) + "%";
        }

        materialsLoaderCountdownTimer = window.setInterval(function () {
          remainingSeconds = Math.max(stageCount > 1 ? 6 : 4, remainingSeconds - 1);
          if (etaNode) {
            etaNode.textContent = "~" + remainingSeconds + " seconds";
          }
        }, 1000);

        stageMeta.forEach(function (stage, index) {
          var delay = index === 0 ? 0 : index * delayStep;
          var timerId = window.setTimeout(function () {
            if (statusNode) {
              statusNode.textContent = stage.label;
            }
            if (barNode) {
              barNode.style.width = String(stage.percent || 0) + "%";
            }
            if (progressNode) {
              progressNode.textContent = String(stage.percent || 0) + "%";
            }
            steps.forEach(function (stepNode, stepIndex) {
              stepNode.classList.toggle("is-active", stepIndex === index);
              stepNode.classList.toggle("is-complete", stepIndex < index);
            });
          }, delay);
          materialsLoaderTimers.push(timerId);
        });
      }

      function closeSmartApply() {
        if (!smartApplyModal) {
          return;
        }
        stopSmartApplyLoader();
        smartApplyModal.hidden = true;
        smartApplySequenceState = null;
        smartApplyActiveItem = null;
        updateSmartApplyQueueUi();
        syncModalBodyLock();
      }

      function stopSmartApplyLoader() {
        smartApplyLoaderTimers.forEach(function (timerId) {
          window.clearTimeout(timerId);
        });
        smartApplyLoaderTimers = [];

        if (smartApplyLoaderProgressTimer) {
          window.clearInterval(smartApplyLoaderProgressTimer);
          smartApplyLoaderProgressTimer = null;
        }

        if (smartApplyLoader) {
          smartApplyLoader.hidden = true;
        }
        if (smartApplyLoaderTitle) {
          smartApplyLoaderTitle.textContent = "Preparing your recruiter email";
        }
        if (smartApplyLoaderStatus) {
          smartApplyLoaderStatus.textContent = "Reading your CV signals…";
        }
        if (smartApplyLoaderBar) {
          smartApplyLoaderBar.style.width = "8%";
        }

        smartApplyLoaderSteps.forEach(function (stepNode, index) {
          stepNode.classList.toggle("is-active", index === 0);
          stepNode.classList.remove("is-complete");
        });
      }

      function startSmartApplyLoader(title) {
        var stages = [
          { delay: 0, width: 18, label: "Reading your CV signals…" },
          { delay: 900, width: 36, label: "Checking the live job brief…" },
          { delay: 1800, width: 54, label: "Matching recruiter context…" },
          { delay: 2800, width: 72, label: "Drafting the subject line…" },
          {
            delay: 3900,
            width: 88,
            label: "Writing your tailored outreach email…",
          },
        ];
        var progress = 8;

        stopSmartApplyLoader();

        if (!smartApplyLoader) {
          return;
        }

        smartApplyLoader.hidden = false;
        if (smartApplyLoaderTitle) {
          smartApplyLoaderTitle.textContent =
            title || "Preparing your recruiter email";
        }
        if (smartApplyLoaderBar) {
          smartApplyLoaderBar.style.width = progress + "%";
        }

        stages.forEach(function (stage, index) {
          smartApplyLoaderTimers.push(
            window.setTimeout(function () {
              if (smartApplyLoaderStatus) {
                smartApplyLoaderStatus.textContent = stage.label;
              }
              if (smartApplyLoaderBar) {
                smartApplyLoaderBar.style.width = stage.width + "%";
              }
              smartApplyLoaderSteps.forEach(function (stepNode, stepIndex) {
                stepNode.classList.toggle("is-active", stepIndex === index);
                stepNode.classList.toggle("is-complete", stepIndex < index);
              });
            }, stage.delay)
          );
        });

        smartApplyLoaderProgressTimer = window.setInterval(function () {
          progress = Math.min(progress + 2, 94);
          if (smartApplyLoaderBar) {
            smartApplyLoaderBar.style.width = progress + "%";
          }
        }, 420);
      }

      function setSmartApplyStatus(message, tone) {
        if (!smartApplyStatus) {
          return;
        }

        if (!message) {
          smartApplyStatus.hidden = true;
          smartApplyStatus.textContent = "";
          smartApplyStatus.classList.remove(
            "is-loading",
            "is-error",
            "is-success"
          );
          return;
        }

        smartApplyStatus.hidden = false;
        smartApplyStatus.textContent = message;
        smartApplyStatus.classList.toggle("is-loading", tone === "loading");
        smartApplyStatus.classList.toggle("is-error", tone === "error");
        smartApplyStatus.classList.toggle("is-success", tone === "success");
      }

      function normalizeSmartApplyQueueMember(member) {
        var reasons = Array.isArray(member && member.reasons)
          ? member.reasons
          : [];
        return {
          jobsPostId: Number(member && member.post_id ? member.post_id : 0),
          wpPostId: Number(member && member.post_id ? member.post_id : 0),
          id: Number(member && member.crm_post_id ? member.crm_post_id : 0),
          recruiterId: Number(member && member.recruiter_id ? member.recruiter_id : 0),
          roleTitle: String(member && member.role_title ? member.role_title : "Role"),
          company: String(member && member.company ? member.company : ""),
          location: String(member && member.location ? member.location : ""),
          recruiterName: String(member && member.recruiter_name ? member.recruiter_name : ""),
          recruiterTitle: String(member && member.recruiter_title ? member.recruiter_title : ""),
          recruiterEmail: String(member && member.recruiter_email ? member.recruiter_email : ""),
          recruiterLinkedIn: String(member && member.recruiter_linkedin ? member.recruiter_linkedin : ""),
          recruiterFirm: String(member && member.recruiter_firm ? member.recruiter_firm : ""),
          reasons: reasons,
          gaps: [],
          score: Number(member && member.match_score ? member.match_score : 0),
          _queueMemberId: Number(member && member.id ? member.id : 0),
        };
      }

      function updateSmartApplyQueueUi() {
        var state = smartApplySequenceState;
        var items = state && Array.isArray(state.items) ? state.items : [];
        var activeIndex = state ? Number(state.activeIndex || 0) : 0;
        var current = items[activeIndex] || null;
        var shouldShowMeta = !!(state && items.length > 2);

        if (!smartApplyMeta) {
          return;
        }

        if (!state || !items.length || !shouldShowMeta) {
          smartApplyMeta.hidden = true;
          if (smartApplyQueueProgress) {
            smartApplyQueueProgress.textContent = "";
          }
          if (smartApplyQueueMeta) {
            smartApplyQueueMeta.textContent = "";
          }
          if (smartApplyQueueNext) {
            smartApplyQueueNext.textContent = "Next Email";
            smartApplyQueueNext.disabled = true;
          }
          return;
        }

        smartApplyMeta.hidden = false;
        smartApplyMeta.setAttribute(
          "data-smart-apply-queue-mode",
          state.preview ? "preview" : "live"
        );
        if (smartApplyQueueProgress) {
          smartApplyQueueProgress.textContent =
            String(activeIndex + 1) + " / " + String(items.length);
        }
        if (smartApplyQueueMeta) {
          smartApplyQueueMeta.textContent = state.preview
            ? [
                "Preview queue.",
                current && current.role_title ? current.role_title : "Queued role",
                current && current.recruiter_name
                  ? "to " + current.recruiter_name
                  : current && current.company
                  ? "for " + current.company
                  : "",
              ]
                .filter(Boolean)
                .join(" ")
            : [
                current && current.role_title ? current.role_title : "Queued role",
                current && current.recruiter_name
                  ? current.recruiter_name
                  : current && current.company
                  ? current.company
                  : "",
              ]
                .filter(Boolean)
                .join(" · ");
        }
        if (smartApplyQueueNext) {
          smartApplyQueueNext.disabled = false;
          smartApplyQueueNext.textContent =
            activeIndex >= items.length - 1
              ? state.preview
                ? "Unlock Full Sequence"
                : "Done"
              : state.preview
              ? "Next Preview"
              : "Next Email";
        }
      }

      function getSmartApplyQueueDetails(listId) {
        var requestBody = new window.FormData();
        var crmNonce = config.crmNonce || config.nonce || "";

        if (!listId || !config.ajaxUrl || !crmNonce) {
          return Promise.reject(new Error("Unable to load the recruiter outreach queue."));
        }

        requestBody.append("action", "sffc_crm_get_job_outreach_list_details");
        requestBody.append("nonce", crmNonce);
        requestBody.append("list_id", String(listId));

        return window
          .fetch(config.ajaxUrl, {
            method: "POST",
            credentials: "same-origin",
            body: requestBody,
          })
          .then(parseAjaxJson)
          .then(function (response) {
            if (!response || !response.success || !response.data) {
              throw new Error(
                (response && response.data && response.data.message) ||
                  "Unable to load the recruiter outreach queue."
              );
            }
            return response.data;
          });
      }

      function saveSmartApplyQueueDraft(queueState, member, result, subject, body) {
        var requestBody = new window.FormData();
        var crmNonce = config.crmNonce || config.nonce || "";

        if (
          !queueState ||
          !member ||
          !queueState.listId ||
          !member.id ||
          !body ||
          !config.ajaxUrl ||
          !crmNonce
        ) {
          return Promise.resolve(null);
        }

        requestBody.append("action", "sffc_crm_save_job_outreach_draft");
        requestBody.append("nonce", crmNonce);
        requestBody.append("list_id", String(queueState.listId));
        requestBody.append("member_id", String(member.id));
        requestBody.append("subject", String(subject || ""));
        requestBody.append("body", String(body || ""));
        requestBody.append("target_channel", "email");
        requestBody.append(
          "generated_with_claude",
          result && result.generated_with_claude ? "1" : ""
        );
        requestBody.append(
          "generated_payload",
          JSON.stringify((result && result.result) || {})
        );

        return window
          .fetch(config.ajaxUrl, {
            method: "POST",
            credentials: "same-origin",
            body: requestBody,
          })
          .then(parseAjaxJson)
          .catch(function () {
            return null;
          });
      }

      function advanceSmartApplyQueue() {
        var state = smartApplySequenceState;
        var nextIndex;

        if (!state || !Array.isArray(state.items) || !state.items.length) {
          return;
        }

        nextIndex = Number(state.activeIndex || 0) + 1;

        if (nextIndex >= state.items.length) {
          if (state.preview) {
            if (smartApplyMailto) {
              smartApplyMailto.textContent = "Unlock Full Sequence";
              smartApplyMailto.setAttribute(
                "href",
                config.membershipUrl || "/membership/"
              );
            }
            setSmartApplyStatus(
              "Preview complete. Join MENA Careers to generate the full recruiter outreach sequence and save every draft to your queue.",
              "loading"
            );
          } else {
            setFeedback(root, "Recruiter outreach queue complete.", false);
            closeSmartApply();
          }
          return;
        }

        state.activeIndex = nextIndex;
        updateSmartApplyQueueUi();
        openSmartApply(normalizeSmartApplyQueueMember(state.items[nextIndex]), {
          queueState: state,
          queueIndex: nextIndex,
        });
      }

      function openRecruiterModalShell() {
        if (!recruiterModal) {
          return;
        }
        recruiterModal.hidden = false;
        syncModalBodyLock();
      }

      function closeRecruiterModal() {
        if (!recruiterModal) {
          return;
        }
        recruiterModal.hidden = true;
        syncModalBodyLock();
      }

      function setSupportFeedback(message, isError) {
        if (!supportFeedback) {
          return;
        }

        if (!message) {
          supportFeedback.hidden = true;
          supportFeedback.textContent = "";
          supportFeedback.classList.remove("is-error", "is-success");
          return;
        }

        supportFeedback.hidden = false;
        supportFeedback.textContent = message;
        supportFeedback.classList.toggle("is-error", !!isError);
        supportFeedback.classList.toggle("is-success", !isError);
      }

      function openSupportModal() {
        if (!supportModal) {
          return;
        }
        supportModal.hidden = false;
        setSupportFeedback("", false);
        syncModalBodyLock();
        window.setTimeout(function () {
          var firstInput = supportForm
            ? supportForm.querySelector('input[name="subject"]')
            : null;
          if (firstInput) {
            firstInput.focus();
          }
        }, 40);
      }

      function closeSupportModal() {
        if (!supportModal) {
          return;
        }
        supportModal.hidden = true;
        if (supportForm) {
          supportForm.reset();
        }
        setSupportFeedback("", false);
        syncModalBodyLock();
      }

      function setNewsletterGroupFilter(filter) {
        var activeFilter = filter === "hr-contacts" ? "hr-contacts" : "jobs";

        $all(root, "[data-cv-match-newsletter-group-filter]").forEach(
          function (button) {
            var isActive =
              button.getAttribute("data-cv-match-newsletter-group-filter") ===
              activeFilter;
            button.classList.toggle("is-active", isActive);
            button.setAttribute("aria-pressed", isActive ? "true" : "false");
          }
        );

        $all(root, "[data-cv-match-newsletter-group-panel]").forEach(
          function (panel) {
            panel.hidden =
            panel.getAttribute("data-cv-match-newsletter-group-panel") !==
              activeFilter;
          }
        );
        applyNewsletterGroupCardFilters();
      }

      function normalizeNewsletterGroupFilterValue(value) {
        return String(value || "")
          .toLowerCase()
          .replace(/\s+/g, " ")
          .trim();
      }

      function applyNewsletterGroupCardFilters() {
        var shell = root.querySelector("[data-cv-match-newsletter-groups-shell]");
        if (!shell) {
          return;
        }

        var locationSelect = shell.querySelector(
          "[data-cv-match-newsletter-group-location-filter]"
        );
        var keywordInput = shell.querySelector(
          "[data-cv-match-newsletter-group-keyword-filter]"
        );
        var clearButton = shell.querySelector(
          "[data-cv-match-newsletter-group-clear-filters]"
        );
        var emptyMessage = shell.querySelector(
          "[data-cv-match-newsletter-group-filter-empty]"
        );
        var locationValue = normalizeNewsletterGroupFilterValue(
          locationSelect ? locationSelect.value : ""
        );
        var keywordTokens = tokenize(
          normalizeNewsletterGroupFilterValue(keywordInput ? keywordInput.value : "")
        );
        var hasFilters = !!locationValue || keywordTokens.length > 0;
        var activePanel = shell.querySelector(
          "[data-cv-match-newsletter-group-panel]:not([hidden])"
        );

        $all(shell, ".sffc-cv-match-studio__newsletter-group-card").forEach(
          function (card) {
            var cardLocations = normalizeNewsletterGroupFilterValue(
              card.getAttribute("data-cv-match-newsletter-group-locations") || ""
            );
            var cardSearch = normalizeNewsletterGroupFilterValue(
              card.getAttribute("data-cv-match-newsletter-group-search") ||
                card.textContent ||
                ""
            );
            var locationMatches =
              !locationValue || cardLocations.indexOf(locationValue) !== -1;
            var keywordMatches =
              !keywordTokens.length ||
              keywordTokens.every(function (token) {
                return cardSearch.indexOf(token) !== -1;
              });

            var isVisible = locationMatches && keywordMatches;
            card.hidden = !isVisible;
            card.classList.toggle("is-filtered-out", !isVisible);
          }
        );

        if (clearButton) {
          clearButton.disabled = !hasFilters;
          clearButton.classList.toggle("is-disabled", !hasFilters);
        }

        if (emptyMessage && activePanel) {
          var activeCards = $all(
            activePanel,
            ".sffc-cv-match-studio__newsletter-group-card"
          );
          var visibleCards = activeCards.filter(function (card) {
            return !card.hidden && !card.classList.contains("is-filtered-out");
          });
          emptyMessage.hidden =
            !hasFilters || !activeCards.length || visibleCards.length > 0;
        } else if (emptyMessage) {
          emptyMessage.hidden = true;
        }
      }

      function clearNewsletterGroupCardFilters() {
        var shell = root.querySelector("[data-cv-match-newsletter-groups-shell]");
        if (!shell) {
          return;
        }

        var locationSelect = shell.querySelector(
          "[data-cv-match-newsletter-group-location-filter]"
        );
        var keywordInput = shell.querySelector(
          "[data-cv-match-newsletter-group-keyword-filter]"
        );

        if (locationSelect) {
          locationSelect.value = "";
        }
        if (keywordInput) {
          keywordInput.value = "";
        }

        applyNewsletterGroupCardFilters();
      }

      function closeNewsletterGroupSubscribeDropdowns(except) {
        $all(root, "[data-cv-match-list-subscribe-dropdown]").forEach(
          function (dropdown) {
            if (except && dropdown === except) {
              return;
            }
            dropdown.hidden = true;
            var wrap = dropdown.closest("[data-cv-match-inline-dropdown-wrap]");
            var trigger = wrap
              ? wrap.querySelector("[data-cv-match-list-subscribe-trigger]")
              : null;
            if (trigger) {
              trigger.setAttribute("aria-expanded", "false");
            }
          }
        );
      }

      function closeCommunityAccountMenu() {
        var menu = root.querySelector("[data-cv-match-community-account-menu]");
        var toggle = root.querySelector(
          "[data-cv-match-community-account-toggle]"
        );
        if (menu) {
          menu.hidden = true;
        }
        if (toggle) {
          toggle.setAttribute("aria-expanded", "false");
        }
      }

      function toggleCommunityAccountMenu(toggle) {
        if (!toggle) {
          return;
        }

        var shell = toggle.closest("[data-cv-match-community-account]");
        var menu = shell
          ? shell.querySelector("[data-cv-match-community-account-menu]")
          : null;
        if (!menu) {
          return;
        }

        var willOpen = menu.hidden;
        closeCommunityAccountMenu();
        menu.hidden = !willOpen;
        toggle.setAttribute("aria-expanded", willOpen ? "true" : "false");
      }

      function toggleNewsletterGroupSubscribeDropdown(trigger) {
        if (!trigger) {
          return;
        }

        var wrap = trigger.closest(
          "[data-cv-match-inline-dropdown-wrap]"
        );
        var dropdown = wrap
          ? wrap.querySelector("[data-cv-match-list-subscribe-dropdown]")
          : null;
        if (!dropdown) {
          return;
        }

        var willOpen = dropdown.hidden;
        closeNewsletterGroupSubscribeDropdowns(dropdown);
        closeCustomListModal();
        closeDailyScanDropdown();
        dropdown.hidden = !willOpen;
        trigger.setAttribute("aria-expanded", willOpen ? "true" : "false");
      }

      function setCustomListFeedback(message, isError) {
        if (!customListFeedback) {
          return;
        }

        if (!message) {
          customListFeedback.hidden = true;
          customListFeedback.textContent = "";
          customListFeedback.classList.remove("is-error", "is-success");
          return;
        }

        customListFeedback.hidden = false;
        customListFeedback.textContent = message;
        customListFeedback.classList.toggle("is-error", !!isError);
        customListFeedback.classList.toggle("is-success", !isError);
      }

      function openCustomListModal() {
        if (!customListDropdown) {
          return;
        }
        closeNewsletterGroupSubscribeDropdowns();
        closeDailyScanDropdown();
        customListDropdown.hidden = false;
        var trigger = root.querySelector("[data-cv-match-custom-list-open]");
        if (trigger) {
          trigger.setAttribute("aria-expanded", "true");
        }
        setCustomListFeedback("", false);
        window.setTimeout(function () {
          var firstInput = customListForm
            ? customListForm.querySelector("[data-cv-match-custom-list-requirements]")
            : null;
          if (firstInput) {
            firstInput.focus();
          }
        }, 40);
      }

      function closeCustomListModal() {
        if (!customListDropdown) {
          return;
        }
        customListDropdown.hidden = true;
        var trigger = root.querySelector("[data-cv-match-custom-list-open]");
        if (trigger) {
          trigger.setAttribute("aria-expanded", "false");
        }
        if (customListForm) {
          customListForm.reset();
        }
        setCustomListFeedback("", false);
      }

      function setDailyScanFeedback(message, isError) {
        if (!dailyScanFeedback) {
          return;
        }

        if (!message) {
          dailyScanFeedback.hidden = true;
          dailyScanFeedback.textContent = "";
          dailyScanFeedback.classList.remove("is-error", "is-success");
          return;
        }

        dailyScanFeedback.hidden = false;
        dailyScanFeedback.textContent = message;
        dailyScanFeedback.classList.toggle("is-error", !!isError);
        dailyScanFeedback.classList.toggle("is-success", !isError);
      }

      function openDailyScanDropdown() {
        if (!dailyScanDropdown) {
          return;
        }
        closeNewsletterGroupSubscribeDropdowns();
        closeCustomListModal();
        dailyScanDropdown.hidden = false;
        var trigger = root.querySelector("[data-cv-match-daily-scan-open]");
        if (trigger) {
          trigger.setAttribute("aria-expanded", "true");
        }
        setDailyScanFeedback("", false);
      }

      function closeDailyScanDropdown() {
        if (!dailyScanDropdown) {
          return;
        }
        dailyScanDropdown.hidden = true;
        var trigger = root.querySelector("[data-cv-match-daily-scan-open]");
        if (trigger) {
          trigger.setAttribute("aria-expanded", "false");
        }
        setDailyScanFeedback("", false);
      }

      function setEmailListFeedback(message, isError) {
        if (!emailListFeedback) {
          return;
        }

        if (!message) {
          emailListFeedback.hidden = true;
          emailListFeedback.textContent = "";
          emailListFeedback.classList.remove("is-error", "is-success");
          return;
        }

        emailListFeedback.hidden = false;
        emailListFeedback.textContent = message;
        emailListFeedback.classList.toggle("is-error", !!isError);
        emailListFeedback.classList.toggle("is-success", !isError);
      }

      function openEmailListModal(source) {
        if (!emailListModal) {
          return;
        }

        var groupName = source
          ? source.getAttribute("data-cv-match-email-list-name") || ""
          : "";
        var groupType = source
          ? source.getAttribute("data-cv-match-email-list-type") || "Jobs"
          : "Jobs";
        var groupNameInput = emailListForm
          ? emailListForm.querySelector("[data-cv-match-email-list-group-name]")
          : null;
        var groupTypeInput = emailListForm
          ? emailListForm.querySelector("[data-cv-match-email-list-group-type]")
          : null;

        if (groupNameInput) {
          groupNameInput.value = groupName;
        }
        if (groupTypeInput) {
          groupTypeInput.value = groupType;
        }
        if (emailListSummary) {
          emailListSummary.textContent = groupName
            ? "Set email delivery for " + groupName + " (" + groupType + ")."
            : "Select how often you want this list sent and where it should go.";
        }

        emailListModal.hidden = false;
        setEmailListFeedback("", false);
        syncModalBodyLock();
        window.setTimeout(function () {
          var firstInput = emailListForm
            ? emailListForm.querySelector("[data-cv-match-email-list-frequency]")
            : null;
          if (firstInput) {
            firstInput.focus();
          }
        }, 40);
      }

      function closeEmailListModal() {
        if (!emailListModal) {
          return;
        }
        emailListModal.hidden = true;
        if (emailListForm) {
          emailListForm.reset();
        }
        setEmailListFeedback("", false);
        syncModalBodyLock();
      }

      function markWelcomeSeen() {
        if (!config.loggedIn) {
          return Promise.resolve();
        }

        if (onboardingSeenPromise) {
          return onboardingSeenPromise;
        }

        var formData = new window.FormData();
        formData.append("action", "sffc_cv_match_mark_welcome_seen");
        formData.append("nonce", config.nonce || "");
        formData.append("preferred_industry", getSelectedPreferredIndustry());
        formData.append("preferred_location", getSelectedPreferredLocation());
        getSelectedWelcomeNewsletterIds().forEach(function (newsletterId) {
          formData.append("newsletter_ids[]", newsletterId);
        });

        onboardingSeenPromise = window
          .fetch(config.ajaxUrl || "/wp-admin/admin-ajax.php", {
            method: "POST",
            body: formData,
            credentials: "same-origin",
          })
          .then(parseAjaxJson)
          .catch(function () {
            return null;
          });

        return onboardingSeenPromise;
      }

      function clearTourTarget() {
        if (onboardingState.target) {
          onboardingState.target.classList.remove("is-tour-target");
        }
        onboardingState.target = null;
      }

      function getCurrentTourStep() {
        if (!onboardingState.active || onboardingState.index < 0) {
          return null;
        }

        return onboardingSteps[onboardingState.index] || null;
      }

      function getTourStepTarget(step) {
        if (!step || !step.selector) {
          return null;
        }

        return root.querySelector(step.selector);
      }

      function positionTourPopover(target) {
        if (!tourPopover || !target) {
          return;
        }

        var rect = target.getBoundingClientRect();
        var viewportWidth =
          window.innerWidth || document.documentElement.clientWidth || 0;
        var viewportHeight =
          window.innerHeight || document.documentElement.clientHeight || 0;
        var popoverWidth = Math.min(380, Math.max(300, viewportWidth - 32));
        var left = rect.right + 18;
        var top = rect.top + rect.height / 2 - 110;

        tourPopover.style.right = "auto";
        tourPopover.style.bottom = "auto";

        if (viewportWidth <= 920) {
          left = Math.max(16, (viewportWidth - popoverWidth) / 2);
          top = Math.min(viewportHeight - 210, Math.max(16, rect.bottom + 18));
        } else if (left + popoverWidth > viewportWidth - 16) {
          left = Math.max(16, rect.left - popoverWidth - 18);
        }

        top = Math.max(16, Math.min(top, viewportHeight - 210));

        tourPopover.style.left = Math.round(left) + "px";
        tourPopover.style.top = Math.round(top) + "px";
        tourPopover.style.width = Math.round(popoverWidth) + "px";
      }

      function closeTour() {
        onboardingState.active = false;
        onboardingState.index = -1;
        clearTourTarget();
        if (tourOverlay) {
          tourOverlay.hidden = true;
        }
        syncModalBodyLock();
      }

      function renderTourStep(index) {
        var step = onboardingSteps[index] || null;
        var target;

        if (!step) {
          closeTour();
          return;
        }

        target = getTourStepTarget(step);
        if (!target) {
          renderTourStep(index + 1);
          return;
        }

        onboardingState.active = true;
        onboardingState.index = index;
        clearTourTarget();
        onboardingState.target = target;
        target.classList.add("is-tour-target");

        if (step.state) {
          closeMaterialsModal();
          closeSmartApply();
          closeRecruiterModal();
          setMobileNavOpen(false);
          setState(root, step.state);
        }

        target.scrollIntoView({
          behavior: "smooth",
          block: "center",
          inline: "nearest",
        });

        if (tourOverlay) {
          tourOverlay.hidden = false;
        }
        if (tourTitle) {
          tourTitle.textContent = step.title || "Workspace";
        }
        if (tourCopy) {
          tourCopy.textContent = step.copy || "";
        }
        if (tourProgress) {
          tourProgress.textContent =
            "Step " +
            String(index + 1) +
            " of " +
            String(onboardingSteps.length);
        }
        if (tourHint) {
          tourHint.textContent =
            (config.onboarding &&
              config.onboarding.messages &&
              config.onboarding.messages.clickPrompt) ||
            "Click the highlighted section to continue through your private equity lists and recruiter contact lists.";
        }
        if (tourStepLabel) {
          tourStepLabel.textContent =
            (config.onboarding &&
              config.onboarding.messages &&
              config.onboarding.messages.stepLabel) ||
            "Lists tour";
        }

        window.requestAnimationFrame(function () {
          positionTourPopover(target);
        });

        syncModalBodyLock();
      }

      function saveNewsletterGroupList(button) {
        if (!button || button.disabled) {
          return;
        }

        var groupId = button.getAttribute("data-cv-match-save-list-group-id") || "";
        var groupSlug =
          button.getAttribute("data-cv-match-save-list-group-slug") || "";
        var groupName =
          button.getAttribute("data-cv-match-save-list-group-name") || "";
        var groupType =
          button.getAttribute("data-cv-match-save-list-group-type") || "jobs";
        var groupUrl =
          button.getAttribute("data-cv-match-save-list-group-url") || "#";

        if (!groupId || !groupSlug || !groupName) {
          setFeedback(root, "We could not identify that list.", true);
          return;
        }

        var originalHtml = button.innerHTML;
        var body = new window.FormData();
        body.append("action", "sffc_cv_match_save_list");
        body.append("nonce", config.nonce || "");
        body.append("group_id", groupId);
        body.append("group_slug", groupSlug);
        body.append("group_name", groupName);
        body.append("group_type", groupType);

        button.disabled = true;
        button.classList.add("is-saving");
        button.textContent = "Saving...";

        window
          .fetch(config.ajaxUrl || "/wp-admin/admin-ajax.php", {
            method: "POST",
            body: body,
            credentials: "same-origin",
          })
          .then(parseAjaxJson)
          .then(function (payload) {
            if (!payload || !payload.success) {
              throw new Error(
                (payload && payload.data && payload.data.message) ||
                  "We could not save this list right now."
              );
            }

            button.classList.remove("is-saving");
            button.classList.add("is-saved");
            button.setAttribute("aria-pressed", "true");
            button.textContent =
              (payload.data && payload.data.label) || "Saved";
            setFeedback(
              root,
              (payload.data && payload.data.message) || "List saved.",
              false
            );
            upsertSavedListCard({
              groupId: groupId,
              groupSlug: groupSlug,
              groupName: groupName,
              groupType: groupType,
              groupUrl: groupUrl,
            });
          })
          .catch(function (error) {
            button.innerHTML = originalHtml;
            setFeedback(
              root,
              error && error.message
                ? error.message
                : "We could not save this list right now.",
              true
            );
          })
          .finally(function () {
            button.disabled = false;
            button.classList.remove("is-saving");
          });
      }

      function upsertSavedListCard(list) {
        var shell = root.querySelector("[data-cv-match-saved-lists-shell]");
        if (!shell || !list || !list.groupId) {
          return;
        }

        var grid = shell.querySelector("[data-cv-match-saved-lists-grid]");
        var empty = shell.querySelector("[data-cv-match-saved-lists-empty]");
        if (!grid) {
          grid = document.createElement("div");
          grid.className = "sffc-cv-match-studio__saved-lists-grid";
          grid.setAttribute("data-cv-match-saved-lists-grid", "");
          shell.appendChild(grid);
        }
        if (empty) {
          empty.hidden = true;
        }

        var key = list.groupType + ":" + list.groupId;
        var existing = grid.querySelector('[data-cv-match-saved-list-key="' + key + '"]');
        if (existing) {
          existing.classList.add("is-updated");
          return;
        }

        var card = document.createElement("article");
        card.className = "sffc-cv-match-studio__saved-list-card";
        card.setAttribute("data-cv-match-saved-list-key", key);

        var typeLabel = list.groupType === "hr-contacts" ? "HR Contacts" : "Jobs";
        var safeName = String(list.groupName || "Saved list");
        var safeSlug = String(list.groupSlug || "");
        var safeUrl = String(list.groupUrl || "#");

        card.innerHTML =
          '<div class="sffc-cv-match-studio__saved-list-head"><span>' +
          escapeHtml(typeLabel) +
          "</span><em>Saved now</em></div>" +
          "<h3>" +
          escapeHtml(safeName) +
          "</h3>" +
          '<div class="sffc-cv-match-studio__saved-list-meta"><span>Saved list</span>' +
          (list.groupType === "jobs"
            ? '<span class="is-off" data-cv-match-saved-list-scan>Daily Scan Off</span>'
            : "") +
          "</div>" +
          '<div class="sffc-cv-match-studio__saved-list-action-wrap" data-cv-match-inline-dropdown-wrap>' +
          (list.groupType === "hr-contacts"
            ? '<button type="button" class="sffc-cv-match-studio__newsletter-group-link" data-cv-match-nav-trigger="discover">Open List</button>'
            : '<a class="sffc-cv-match-studio__newsletter-group-link" href="' +
              escapeHtml(safeUrl) +
              '" data-cv-match-newsletter-group-open="' +
              escapeHtml(safeSlug) +
              '" data-cv-match-newsletter-group-label="' +
              escapeHtml(safeName) +
              '">Open List</a>') +
          "</div>";

        grid.insertBefore(card, grid.firstChild);
      }

      function syncDailyScanGroupState(groupIds) {
        var activeIds = (groupIds || []).map(function (id) {
          return String(id || "");
        });
        var activeLookup = {};
        activeIds.forEach(function (id) {
          if (id) {
            activeLookup[id] = true;
          }
        });

        $all(root, "[data-cv-match-newsletter-group-id]").forEach(function (card) {
          var groupId = card.getAttribute("data-cv-match-newsletter-group-id") || "";
          var isActive = !!activeLookup[groupId];
          card.classList.toggle("is-daily-scan-enabled", isActive);

          var actions = card.querySelector(
            ".sffc-cv-match-studio__newsletter-group-actions"
          );
          var badge = card.querySelector("[data-cv-match-daily-scan-badge]");
          if (isActive && !badge && actions) {
            badge = document.createElement("span");
            badge.className =
              "sffc-cv-match-studio__newsletter-group-action sffc-cv-match-studio__newsletter-group-action--scan is-active";
            badge.setAttribute("data-cv-match-daily-scan-badge", groupId);
            badge.textContent = "Daily Scan On";
            actions.appendChild(badge);
          } else if (!isActive && badge) {
            badge.remove();
          }
        });

        $all(root, ".sffc-cv-match-studio__saved-list-card").forEach(function (card) {
          var key = card.getAttribute("data-cv-match-saved-list-key") || "";
          var parts = key.split(":");
          if (parts[0] !== "jobs" || !parts[1]) {
            return;
          }
          var scanStatus = card.querySelector("[data-cv-match-saved-list-scan]");
          if (scanStatus) {
            var enabled = !!activeLookup[parts[1]];
            scanStatus.textContent = enabled ? "Daily Scan On" : "Daily Scan Off";
            scanStatus.classList.toggle("is-on", enabled);
            scanStatus.classList.toggle("is-off", !enabled);
          }
        });
      }

      function setCvOnboardingFeedback(message, isError) {
        if (!cvOnboardingFeedback) {
          return;
        }

        if (!message) {
          cvOnboardingFeedback.hidden = true;
          cvOnboardingFeedback.textContent = "";
          cvOnboardingFeedback.classList.remove("is-error", "is-success");
          return;
        }

        cvOnboardingFeedback.hidden = false;
        cvOnboardingFeedback.textContent = String(message);
        cvOnboardingFeedback.classList.toggle("is-error", !!isError);
        cvOnboardingFeedback.classList.toggle("is-success", !isError);
      }

      function setCvOnboardingClosable(canClose) {
        cvOnboardingCanClose = !!canClose;

        if (cvOnboardingClose) {
          cvOnboardingClose.hidden = !cvOnboardingCanClose;
          cvOnboardingClose.disabled = !cvOnboardingCanClose;
        }
      }

      function openCvOnboardingModal() {
        if (!cvOnboardingModal) {
          return;
        }

        cvOnboardingModal.hidden = false;
        setCvOnboardingClosable(false);
        if (cvOnboardingInput && !String(cvOnboardingInput.value || "").trim()) {
          cvOnboardingInput.value = activeCvText || "";
        }
        if (cvOnboardingStatus && !cvOnboardingStatus.textContent.trim()) {
          cvOnboardingStatus.textContent = "PDF, DOCX, DOC, or TXT";
        }
        setCvOnboardingFeedback("", false);
        syncModalBodyLock();

        window.setTimeout(function () {
          if (cvOnboardingDialog) {
            cvOnboardingDialog.focus();
          }
          if (cvOnboardingInput) {
            cvOnboardingInput.focus();
          }
        }, 40);
      }

      function requireCvBeforeSmartApply(item, options) {
        pendingSmartApplyAfterCv = {
          item: item,
          options: options || null,
        };
        setFeedback(
          root,
          "Upload or paste your CV first, then MENA Careers will draft the recruiter message.",
          true
        );
        openCvOnboardingModal();
      }

      function closeCvOnboardingModal() {
        if (!cvOnboardingModal || !cvOnboardingCanClose) {
          return;
        }

        cvOnboardingModal.hidden = true;
        syncModalBodyLock();
      }

      function submitCvOnboarding() {
        var cvText = cvOnboardingInput
          ? String(cvOnboardingInput.value || "").trim()
          : "";

        if (!cvText) {
          setCvOnboardingFeedback(
            (config.labels && config.labels.cvOnboardingEmpty) ||
              "Upload or paste your CV to get started.",
            true
          );
          if (cvOnboardingInput) {
            cvOnboardingInput.focus();
          }
          return;
        }

        if (cvOnboardingSubmit) {
          cvOnboardingSubmit.disabled = true;
        }

        setCvOnboardingFeedback(
          (config.labels && config.labels.cvOnboardingSaving) ||
            "Saving your CV and opening your private equity lists and recruiter contact lists…",
          false
        );

        syncCvTextState(cvText, cvOnboardingInput);
        persistCvToProfile(cvText, { force: true, rejectOnError: true })
          .then(function (payload) {
            var normalizedText = String(
              (payload && payload.data && payload.data.active_cv_text) || cvText
            ).trim();

            if (!normalizedText) {
              throw new Error(
                (config.labels && config.labels.cvOnboardingError) ||
                  "We could not save your CV right now. Please try again."
              );
            }

            if (cvOnboardingInput) {
              cvOnboardingInput.value = normalizedText;
            }
            setCvOnboardingClosable(true);
            closeCvOnboardingModal();
            if (pendingSmartApplyAfterCv && pendingSmartApplyAfterCv.item) {
              var pendingSmartApply = pendingSmartApplyAfterCv;
              pendingSmartApplyAfterCv = null;
              openSmartApply(pendingSmartApply.item, pendingSmartApply.options || {});
            } else {
              openWelcomeModal();
            }
          })
          .catch(function (error) {
            setCvOnboardingFeedback(
              (error && error.message) ||
                (config.labels && config.labels.cvOnboardingError) ||
                "We could not save your CV right now. Please try again.",
              true
            );
          })
          .finally(function () {
            if (cvOnboardingSubmit) {
              cvOnboardingSubmit.disabled = false;
            }
          });
      }

      function openTour() {
        if (!onboardingSteps.length || !tourOverlay) {
          return;
        }

        closeWelcomeModal(false);
        markWelcomeSeen();
        renderTourStep(0);
      }

      function closeWelcomeModal(markSeen) {
        if (!welcomeModal) {
          return;
        }

        welcomeModal.dataset.stage = "";
        if (welcomeOverview) {
          welcomeOverview.hidden = false;
        }
        if (welcomePlansPanel) {
          welcomePlansPanel.hidden = true;
        }
        if (welcomeCheckoutPanel) {
          welcomeCheckoutPanel.hidden = true;
          $all(welcomeCheckoutPanel, "[data-cv-match-welcome-checkout-form]").forEach(function (panel) {
            panel.hidden = true;
          });
        }
        welcomeModal.hidden = true;
        syncModalBodyLock();
        syncWelcomeProceedButton();

        if (markSeen !== false) {
          markWelcomeSeen();
        }
      }

      function openWelcomePlans() {
        if (!welcomeModal) {
          return;
        }

        welcomeModal.dataset.stage = "plans";
        if (welcomeOverview) {
          welcomeOverview.hidden = false;
        }
        if (welcomePlansPanel) {
          welcomePlansPanel.hidden = false;
        }
        if (welcomeCheckoutPanel) {
          welcomeCheckoutPanel.hidden = true;
        }
        syncWelcomeProceedButton();
      }

      function openWelcomeCheckout(planSlug, fallbackUrl) {
        var targetPanel;

        if (!welcomeModal || !welcomeCheckoutPanel || !planSlug) {
          if (fallbackUrl) {
            window.location.href = fallbackUrl;
          }
          return;
        }

        targetPanel = welcomeCheckoutPanel.querySelector(
          '[data-cv-match-welcome-checkout-form="' + planSlug.replace(/["\\]/g, "\\$&") + '"]'
        );

        if (!targetPanel) {
          if (fallbackUrl) {
            window.location.href = fallbackUrl;
          }
          return;
        }

        welcomeModal.dataset.stage = "checkout";
        if (welcomeOverview) {
          welcomeOverview.hidden = true;
        }
        if (welcomeCheckoutPanel) {
          welcomeCheckoutPanel.hidden = false;
        }
        $all(welcomeCheckoutPanel, "[data-cv-match-welcome-checkout-form]").forEach(function (panel) {
          panel.hidden = panel !== targetPanel;
        });
        syncWelcomeProceedButton();
        refreshWelcomeCheckoutPaymentUI(targetPanel);
        window.setTimeout(function () {
          refreshWelcomeCheckoutPaymentUI(targetPanel);
        }, 500);
      }

      function setWelcomeCvLoaded(fileName) {
        if (welcomeCvCard) {
          welcomeCvCard.classList.add("is-loaded");
        }

        if (welcomeCvUploadStatus) {
          welcomeCvUploadStatus.textContent = fileName
            ? fileName + " loaded and saved."
            : "CV loaded and saved to your MENA Careers profile.";
        }
      }

      function openWelcomeModal() {
        if (!welcomeModal) {
          return;
        }

        if (getCurrentCvText()) {
          setWelcomeCvLoaded("");
        }

        welcomeModal.dataset.stage = "";
        if (welcomeOverview) {
          welcomeOverview.hidden = false;
        }
        if (welcomePlansPanel) {
          welcomePlansPanel.hidden = true;
        }
        if (welcomeCheckoutPanel) {
          welcomeCheckoutPanel.hidden = true;
          $all(welcomeCheckoutPanel, "[data-cv-match-welcome-checkout-form]").forEach(function (panel) {
            panel.hidden = true;
          });
        }
        welcomeModal.hidden = false;
        syncModalBodyLock();
        syncWelcomeProceedButton();

        window.setTimeout(function () {
          if (welcomeDialog) {
            welcomeDialog.focus();
          }
        }, 40);
      }

      function setRecruiterModalLoading(isLoading) {
        if (recruiterModalLoading) {
          recruiterModalLoading.hidden = !isLoading;
        }
        if (recruiterModalBody) {
          recruiterModalBody.hidden = !!isLoading;
        }
      }

      function setRecruiterModalError(message) {
        if (!recruiterModalError) {
          return;
        }
        recruiterModalError.hidden = !message;
        recruiterModalError.textContent = message || "";
      }

      function openRecruiterProfile(payload) {
        var recruiterId = Number(
          payload && payload.recruiterId ? payload.recruiterId : 0
        );
        var roleId = Number(payload && payload.roleId ? payload.roleId : 0);
        var jobsPostId = Number(
          payload && payload.jobsPostId ? payload.jobsPostId : 0
        );
        var recruiterName =
          payload && payload.recruiterName
            ? payload.recruiterName
            : "Recruiter";
        var cacheKey =
          String(recruiterId || 0) +
          ":" +
          String(roleId || 0) +
          ":" +
          String(jobsPostId || 0);

        if (!recruiterModal || !recruiterId) {
          return Promise.resolve();
        }

        openRecruiterModalShell();
        setRecruiterModalError("");

        if (recruiterProfileCache[cacheKey]) {
          if (recruiterModalBody) {
            recruiterModalBody.innerHTML = recruiterProfileCache[cacheKey];
          }
          setRecruiterModalLoading(false);
          return Promise.resolve();
        }

        setRecruiterModalLoading(true);

        var formData = new window.FormData();
        formData.append("action", "sffc_load_cv_match_recruiter_profile");
        formData.append("nonce", config.nonce || "");
        formData.append("recruiter_id", String(recruiterId));
        formData.append("crm_post_id", String(roleId));
        formData.append("jobs_post_id", String(jobsPostId));
        formData.append("recruiter_name", recruiterName);

        return window
          .fetch(config.ajaxUrl, {
            method: "POST",
            body: formData,
            credentials: "same-origin",
          })
          .then(parseAjaxJson)
          .then(function (payloadResponse) {
            if (
              !payloadResponse ||
              !payloadResponse.success ||
              !payloadResponse.data
            ) {
              throw new Error(
                (payloadResponse &&
                  payloadResponse.data &&
                  payloadResponse.data.message) ||
                  "We could not load this recruiter profile yet."
              );
            }

            recruiterProfileCache[cacheKey] = payloadResponse.data.html || "";
            if (recruiterModalBody) {
              recruiterModalBody.innerHTML = recruiterProfileCache[cacheKey];
            }
            setRecruiterModalLoading(false);
          })
          .catch(function (error) {
            setRecruiterModalLoading(false);
            setRecruiterModalError(
              error && error.message
                ? error.message
                : "We could not load this recruiter profile yet."
            );
          });
      }

      function openSmartApply(item, options) {
        if (!smartApplyModal || !item) {
          return;
        }
        smartApplyActiveItem = item;
        var queueState = options && options.queueState ? options.queueState : null;
        var queueIndex =
          options && typeof options.queueIndex === "number"
            ? options.queueIndex
            : -1;
        var queueMember =
          queueState &&
          queueIndex > -1 &&
          queueState.items &&
          queueState.items[queueIndex]
            ? queueState.items[queueIndex]
            : null;
        var cvText = getCurrentCvText();
        var recruiterName =
          publicRecruiterName(item) ||
          item.recruiterFirm ||
          item.company ||
          "Hiring team";
        var cacheKey =
          (queueMember
            ? "queue:" + String(queueMember.id || 0)
            : String(item.jobsPostId || 0)) +
          ":" +
          hashText(String(cvText || ""));
        var cachedDraft =
          smartApplyDraftCache[cacheKey] ||
          (queueMember &&
          queueMember.generated_subject &&
          queueMember.generated_body
            ? {
                subject: String(queueMember.generated_subject || ""),
                body: personalizeMaterialSignature(queueMember.generated_body || ""),
              }
            : null);
        var isLocked = !config.loggedIn || !config.hasPremiumAccess;
        var previewDraft = smartApplyPreviewDraft(item, cvText);

        stopSmartApplyLoader();

        if (!isLocked && !cvText) {
          requireCvBeforeSmartApply(item, options || {});
          return;
        }

        if (smartApplyFrom) {
          smartApplyFrom.innerHTML = smartApplyFromMarkup(cvText);
        }
        if (smartApplyRecipient) {
          smartApplyRecipient.innerHTML = smartApplyRecipientMarkup(item);
        }
        if (smartApplyPackGrid) {
          smartApplyPackGrid.innerHTML = smartApplyPackGridMarkup();
        }
        if (smartApplyEmailSubject) {
          smartApplyEmailSubject.value = isLocked
            ? previewDraft.subject
            : "Generating subject…";
        }
        if (smartApplyEmailBody) {
          smartApplyEmailBody.innerHTML = formatSmartApplyBodyText(
            isLocked
              ? previewDraft.body
              : "MENA Careers is preparing your recruiter email…"
          );
          smartApplyEmailBody.setAttribute(
            "contenteditable",
            isLocked ? "false" : "true"
          );
        }
        if (smartApplyCopy) {
          smartApplyCopy.disabled = isLocked;
          smartApplyCopy.textContent = isLocked ? "Go" : "Copy Email";
        }
        if (smartApplyMailto) {
          smartApplyMailto.textContent = isLocked
            ? "Upgrade for Full Access"
            : "Open in Mail";
          smartApplyMailto.setAttribute(
            "href",
            isLocked ? config.membershipUrl || "/membership/" : "#"
          );
        }

        if (queueState) {
          smartApplySequenceState = queueState;
          smartApplySequenceState.activeIndex = queueIndex > -1 ? queueIndex : 0;
        } else {
          smartApplySequenceState = null;
        }
        updateSmartApplyQueueUi();

        smartApplyModal.hidden = false;
        syncModalBodyLock();

        if (isLocked) {
          setSmartApplyStatus(
            queueState
              ? "Preview mode. This is an example of how MENA Careers runs the recruiter outreach queue one email at a time."
              : "This is a preview. Join MENA Careers to generate the live recruiter intro with MENA Careers.",
            "loading"
          );
          return;
        }

        if (cachedDraft) {
          if (smartApplyEmailSubject) {
            smartApplyEmailSubject.value = cachedDraft.subject || "";
          }
          if (smartApplyEmailBody) {
            smartApplyEmailBody.innerHTML = formatSmartApplyBodyText(
              personalizeMaterialSignature(cachedDraft.body || "")
            );
          }
          if (smartApplyMailto) {
            smartApplyMailto.textContent = "Open in Mail";
            smartApplyMailto.setAttribute(
              "href",
              "mailto:" +
                encodeURIComponent(item.recruiterEmail || "") +
                "?subject=" +
                encodeURIComponent(cachedDraft.subject || "") +
                "&body=" +
                encodeURIComponent(personalizeMaterialSignature(cachedDraft.body || ""))
            );
          }
          setSmartApplyStatus("MENA Careers draft ready.", "success");
          return;
        }

        startSmartApplyLoader("Preparing your recruiter email");
        setSmartApplyStatus(
          "Generating recruiter email with MENA Careers…",
          "loading"
        );

        var formData = new window.FormData();
        formData.append("action", "sffc_crm_reddit_generate_outreach");
        formData.append("nonce", config.outreachNonce || "");
        formData.append("cv_source", "active");
        formData.append("cv_text", cvText);
        formData.append("jobs_post_id", String(item.jobsPostId || 0));
        formData.append("outreach_context", "enquiring_about_role");
        formData.append("target_channel", "email");
        formData.append("recipient_name", recruiterName);
        formData.append("recipient_role", item.recruiterTitle || "");
        formData.append(
          "user_context",
          [
            item.reasons && item.reasons.length
              ? "Match signals: " + item.reasons.slice(0, 3).join("; ")
              : "",
            item.gaps && item.gaps.length
              ? "Keep the note concise and aware of these improvements: " +
                item.gaps.slice(0, 2).join("; ")
              : "Keep the note concise, credible, and tailored to the live brief.",
          ]
            .filter(Boolean)
            .join(" ")
        );

        window
          .fetch(config.ajaxUrl, {
            method: "POST",
            body: formData,
            credentials: "same-origin",
          })
          .then(parseAjaxJson)
          .then(function (payloadResponse) {
            var result =
              payloadResponse && payloadResponse.data
                ? payloadResponse.data.result || {}
                : {};
            var message = result.message || {};
            var subject = String(message.subject || "").trim();
            var body = personalizeMaterialSignature(message.body || "").trim();

            stopSmartApplyLoader();

            if (!payloadResponse || !payloadResponse.success) {
              throw new Error(
                (payloadResponse &&
                  payloadResponse.data &&
                  payloadResponse.data.message) ||
                  "We could not generate this outreach yet."
              );
            }

            if (!payloadResponse.data.generated_with_claude) {
              throw new Error(
                "MENA Careers is unavailable right now. Try again in a moment."
              );
            }

            if (!subject || !body) {
              throw new Error(
                "MENA Careers returned an incomplete outreach draft. Try again."
              );
            }

            smartApplyDraftCache[cacheKey] = {
              subject: subject,
              body: body,
            };

            if (queueMember) {
              queueMember.generated_subject = subject;
              queueMember.generated_body = body;
              queueMember.generated_with_claude = true;
              queueMember.outreach_status = "generated";
            }

            if (smartApplyEmailSubject) {
              smartApplyEmailSubject.value = subject;
            }
            if (smartApplyEmailBody) {
              smartApplyEmailBody.innerHTML = formatSmartApplyBodyText(body);
            }
            if (smartApplyMailto) {
              smartApplyMailto.textContent = "Open in Mail";
              smartApplyMailto.setAttribute(
                "href",
                "mailto:" +
                  encodeURIComponent(item.recruiterEmail || "") +
                  "?subject=" +
                  encodeURIComponent(subject) +
                  "&body=" +
                  encodeURIComponent(body)
              );
            }

            setSmartApplyStatus("MENA Careers draft ready.", "success");
            saveSmartApplyQueueDraft(
              queueState,
              queueMember,
              payloadResponse && payloadResponse.data ? payloadResponse.data : {},
              subject,
              body
            );
          })
          .catch(function (error) {
            stopSmartApplyLoader();
            if (smartApplyEmailSubject) {
              smartApplyEmailSubject.value = "Unable to generate subject";
            }
            if (smartApplyEmailBody) {
              smartApplyEmailBody.innerHTML = formatSmartApplyBodyText(
                "MENA Careers could not prepare this recruiter email right now."
              );
            }
            setSmartApplyStatus(
              error && error.message
                ? error.message
                : "We could not generate this recruiter email right now.",
              "error"
            );
          });
      }

      function getCurrentCvText() {
        var candidates = [
          activeCvText,
          textarea ? textarea.value : "",
          floatingInput ? floatingInput.value : "",
        ];

        for (var i = 0; i < candidates.length; i += 1) {
          var text = String(candidates[i] || "").trim();
          if (text) {
            return text;
          }
        }

        return "";
      }

      function syncJobCvRequirements() {
        var hasCv = getCurrentCvText() !== "";
        var gatedNodes = $all(root, "[data-cv-match-cv-required]");
        var cvMetric = $(root, "[data-cv-match-cv-metric]");
        var cvMetricValue = cvMetric
          ? $(cvMetric, "[data-cv-match-cv-metric-value]")
          : null;
        var cvMetricCopy = cvMetric
          ? $(cvMetric, "[data-cv-match-cv-metric-copy]")
          : null;
        var cvMetricBadge = cvMetric
          ? $(cvMetric, "[data-cv-match-cv-metric-badge]")
          : null;
        var cvMetricVisualValue = cvMetric
          ? $(cvMetric, ".sffc-cv-match-studio__job-split-donut-center strong")
          : null;
        var cvMetricVisualLabel = cvMetric
          ? $(cvMetric, ".sffc-cv-match-studio__job-split-donut-center span")
          : null;

        root.setAttribute("data-cv-match-has-cv", hasCv ? "true" : "false");

        gatedNodes.forEach(function (node) {
          node.classList.toggle("is-locked", !hasCv);
          node.setAttribute("data-cv-match-locked", hasCv ? "false" : "true");
          if (node.matches("button")) {
            node.setAttribute("aria-disabled", hasCv ? "false" : "true");
          }
        });

        if (cvMetricValue) {
          cvMetricValue.textContent = hasCv
            ? cvMetric.getAttribute("data-cv-unlocked-value") || "Ready"
            : cvMetric.getAttribute("data-cv-locked-value") || "Locked";
        }

        if (cvMetricCopy) {
          cvMetricCopy.textContent = hasCv
            ? cvMetric.getAttribute("data-cv-unlocked-copy") ||
              "Your active CV is loaded for live review, tailored materials, and recruiter outreach."
            : cvMetric.getAttribute("data-cv-locked-copy") ||
              "Upload your CV to unlock live review, tailored materials, and recruiter outreach.";
        }

        if (cvMetricBadge) {
          cvMetricBadge.textContent = hasCv
            ? cvMetric.getAttribute("data-cv-unlocked-badge") || "CV loaded"
            : cvMetric.getAttribute("data-cv-locked-badge") || "CV required";
          cvMetricBadge.classList.toggle("is-low", hasCv);
          cvMetricBadge.classList.toggle("is-medium", !hasCv);
        }

        if (cvMetricVisualValue) {
          cvMetricVisualValue.textContent = hasCv
            ? cvMetric.getAttribute("data-cv-unlocked-visual-value") || "100%"
            : cvMetric.getAttribute("data-cv-locked-visual-value") || "Locked";
        }

        if (cvMetricVisualLabel) {
          cvMetricVisualLabel.textContent = hasCv
            ? cvMetric.getAttribute("data-cv-unlocked-visual-label") || "review"
            : cvMetric.getAttribute("data-cv-locked-visual-label") || "CV first";
        }
      }

      function getJobCanvasContext() {
        var canvas = $(root, "[data-cv-match-job-canvas]");
        var roleNode = canvas
          ? $(canvas, ".sffc-cv-match-studio__job-company-copy")
          : null;
        var jdSource = canvas ? $(canvas, "[data-cv-match-job-jd]") : null;

        return {
          canvas: canvas,
          jobsPostId: canvas
            ? Number(canvas.getAttribute("data-jobs-post-id") || 0)
            : 0,
          wpPostId: canvas
            ? Number(canvas.getAttribute("data-wp-post-id") || 0)
            : 0,
          crmPostId: canvas
            ? Number(canvas.getAttribute("data-crm-post-id") || 0)
            : 0,
          roleTitle: roleNode
            ? (($(roleNode, "h3") || {}).textContent || "").trim()
            : "",
          company: roleNode
            ? (($(roleNode, "strong") || {}).textContent || "").trim()
            : "",
          jdText: jdSource
            ? String(jdSource.value || jdSource.textContent || "").trim()
            : "",
        };
      }

      function getStandaloneJobItem() {
        var context = getJobCanvasContext();
        var canvas = context.canvas;
        var recruiterCopy = canvas
          ? $(canvas, ".sffc-cv-match-studio__job-recruiter-copy")
          : null;
        var recruiterNameNode = recruiterCopy ? $("strong", recruiterCopy) : null;
        var recruiterTitleNode = recruiterCopy ? $("span", recruiterCopy) : null;
        var emailAction = canvas
          ? $(canvas, ".sffc-cv-match-studio__job-contact-action.is-email")
          : null;

        if (!context.jobsPostId) {
          return null;
        }

        return {
          jobsPostId: context.jobsPostId,
          wpPostId: context.wpPostId,
          id: context.crmPostId,
          roleTitle: context.roleTitle,
          company: context.company,
          recruiterName: recruiterNameNode
            ? String(recruiterNameNode.textContent || "").trim()
            : "Hiring team",
          recruiterTitle: recruiterTitleNode
            ? String(recruiterTitleNode.textContent || "").trim()
            : "",
          recruiterEmail:
            emailAction && emailAction.getAttribute("href")
              ? String(emailAction.getAttribute("href")).replace(/^mailto:/, "")
              : "",
          reasons: [],
          gaps: [],
        };
      }

      function openCvReport() {
        var context = getJobCanvasContext();
        var cvText = getCurrentCvText();
        var gapUrl = config.cvGapUrl || "/cv-gap-analyser/";

        if (!context.jobsPostId || !context.jdText) {
          setFeedback(
            root,
            "We could not load the job description for this report yet.",
            true
          );
          return;
        }

        if (!cvText) {
          setFeedback(
            root,
            config.labels && config.labels.noCv
              ? config.labels.noCv
              : "Please upload your CV/Resume first to see matches.",
            true
          );
          return;
        }

        try {
          window.sessionStorage.setItem(
            CV_GAP_PREFILL_KEY,
            JSON.stringify({
              jd_text: context.jdText,
              cv_text: cvText,
              job_title: context.roleTitle || "",
              company: context.company || "",
              source: "cv_match_studio",
            })
          );
        } catch (error) {
          setFeedback(
            root,
            "We could not prepare your CV report right now.",
            true
          );
          return;
        }

        window.location.href = gapUrl;
      }

      function setMaterialsStatus(message, type) {
        if (!materialsStatus) {
          return;
        }

        if (!message) {
          materialsStatus.hidden = true;
          materialsStatus.textContent = "";
          materialsStatus.classList.remove("is-error", "is-loading");
          return;
        }

        materialsStatus.hidden = false;
        materialsStatus.textContent = message;
        materialsStatus.classList.toggle("is-error", type === "error");
        materialsStatus.classList.toggle("is-loading", type === "loading");
      }

      function setActiveMaterialsResource(resource, resources, activeIndex) {
        stopMaterialsLoader();
        activeMaterialsResource = resource || null;
        activeMaterialsResources = Array.isArray(resources) ? resources.slice() : [];

        if (materialsTabs && Array.isArray(resources)) {
          $all(materialsTabs, "[data-cv-match-material-tab]").forEach(function (
            button,
            index
          ) {
            button.classList.toggle("is-active", index === activeIndex);
          });
        }

        if (materialsOutput) {
          materialsOutput.innerHTML =
            resource && resource.markup ? resource.markup : "";
        }

        if (materialsActions) {
          materialsActions.hidden = !resource;
        }

        if (materialsDownload) {
          if (resource && resource.download_url) {
            materialsDownload.hidden = false;
            materialsDownload.setAttribute("href", resource.download_url);
            materialsDownload.setAttribute(
              "download",
              resource.download_filename || ""
            );
          } else {
            materialsDownload.hidden = true;
            materialsDownload.setAttribute("href", "#");
            materialsDownload.removeAttribute("download");
          }
        }
      }

      function getPreferredMaterialsIndex(resources, preferredMaterialType) {
        var preferred = String(preferredMaterialType || "").trim();
        var matchIndex = 0;

        if (!preferred || !Array.isArray(resources)) {
          return 0;
        }

        resources.some(function (resource, index) {
          if (
            String((resource && resource.material_type) || "").trim() === preferred
          ) {
            matchIndex = index;
            return true;
          }

          return false;
        });

        return matchIndex;
      }

      function renderMaterialsTabs(resources) {
        if (!materialsTabs) {
          return;
        }

        materialsTabs.innerHTML = resources
          .map(function (resource, index) {
            var kind = String((resource && resource.kind) || "word").trim();
            var artMarkup =
              kind === "pdf"
                ? "" +
                  '<span class="sffc-cv-match-studio__job-material-art sffc-cv-match-studio__job-material-art--pdf" aria-hidden="true">' +
                  '<span class="sffc-cv-match-studio__job-material-pdf-page">' +
                  '<span class="sffc-cv-match-studio__job-material-pdf-fold"></span>' +
                  '<svg viewBox="0 0 64 64" focusable="false" aria-hidden="true"><path d="M25.5 18.5c2.2 0 3.6 1.4 3.6 4.1 0 4.2-2 10-5.5 17.1 5 .9 10 2.3 15.4 4.7 2.3-2.2 4.7-3.7 7.1-3.7 2.6 0 4.4 1.5 4.4 3.9 0 3.8-4.4 6-9.8 6-2.2 0-4.6-.3-7.1-.9-2.9 3.8-6.1 7.1-9.7 7.1-2.8 0-4.6-1.5-4.6-3.7 0-3.1 3.5-5.4 9.3-6.5 2.7-4.7 5.1-10.1 6.4-14.5-1.9 4.1-5 6.7-8.1 6.7-3.4 0-5.8-2.4-5.8-6.1 0-6.1 3.6-12.1 6.4-12.1Zm-.1 3.7c-1.2 0-3.1 3.7-3.1 8.1 0 2 .8 3.1 2 3.1 2 0 4.6-4.1 4.6-8.3 0-1.9-1.1-2.9-3.5-2.9Zm14.8 20.5c-3.5-1.4-7.2-2.5-10.8-3.3-1.8 3.2-3.8 6-5.8 8.5 4.8.5 9.6-.4 16.6-5.2Zm-19 6c-2.7.6-4.3 1.6-4.3 2.7 0 .7.6 1.1 1.5 1.1 1.4 0 3-1.1 5-3.8-.7-.1-1.4-.1-2.2 0Zm22.1-1c1.2 0 2.1-.4 2.1-1.2 0-.5-.3-.7-.8-.7-.9 0-1.8.4-3.1 1.4.7.3 1.2.5 1.8.5Z" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" /></svg>' +
                  '<span class="sffc-cv-match-studio__job-material-pdf-label">PDF</span>' +
                  "</span>" +
                  "</span>"
                : "" +
                  '<span class="sffc-cv-match-studio__job-material-art sffc-cv-match-studio__job-material-art--word" aria-hidden="true">' +
                  '<span class="sffc-cv-match-studio__job-material-word-sheet is-back"></span>' +
                  '<span class="sffc-cv-match-studio__job-material-word-sheet is-mid"></span>' +
                  '<span class="sffc-cv-match-studio__job-material-word-tile">W</span>' +
                  "</span>";
            return (
              "" +
              '<button type="button" class="sffc-cv-match-studio__materials-tab' +
              (index === 0 ? " is-active" : "") +
              '" data-cv-match-material-tab="' +
              index +
              '">' +
              '<span class="sffc-cv-match-studio__materials-tab-kind is-' +
              escapeHtml(kind) +
              '">' +
              artMarkup +
              "</span>" +
              '<span class="sffc-cv-match-studio__materials-tab-copy">' +
              "<strong>" +
              escapeHtml(resource.label || resource.title || "Material") +
              "</strong>" +
              "<small>" +
              escapeHtml(resource.title || "") +
              "</small>" +
              "</span>" +
              "</button>"
            );
          })
          .join("");
      }

      function openMaterialsModal(context) {
        if (!materialsModal) {
          return;
        }

        if (materialsTitle) {
          materialsTitle.textContent =
            context && context.roleTitle
              ? context.roleTitle
              : "Tailored application pack";
        }
        if (materialsSubtitle) {
          materialsSubtitle.textContent =
            context && context.company
              ? "Generated from your uploaded CV for " + context.company + "."
              : "Generated from your uploaded CV and the live job brief.";
        }

        materialsModal.hidden = false;
        syncModalBodyLock();
      }

      function closeMaterialsModal() {
        if (!materialsModal) {
          return;
        }

        stopMaterialsLoader();
        activeMaterialsResources = [];
        materialsModal.hidden = true;
        syncModalBodyLock();
      }

      function buildMaterialsCacheKey(context, cvText) {
        var jobsPostId = Number(context && context.jobsPostId ? context.jobsPostId : 0);
        var jdKey = hashText(String((context && context.jdText) || ""));
        var roleKey = hashText(
          String((context && context.roleTitle) || "") +
            "::" +
            String((context && context.company) || "")
        );

        if (jobsPostId > 0) {
          return String(jobsPostId) + ":" + hashText(cvText || "");
        }

        return "custom:" + roleKey + ":" + jdKey + ":" + hashText(cvText || "");
      }

      function openGeneratedMaterials(
        preferredMaterialType,
        overrideContext,
        overrideCvText,
        selectedMaterialTypes
      ) {
        var context = overrideContext || getJobCanvasContext();
        var cvText = String(overrideCvText || getCurrentCvText()).trim();
        var cacheKey;
        var singleCacheKey = "";
        var requestBody;
        var preferredIndex = 0;
        var preferredMaterial = String(preferredMaterialType || "").trim();
        var cachedPack = null;
        var cachedSingleResource = null;
        var selectedTypes = Array.isArray(selectedMaterialTypes)
          ? selectedMaterialTypes
              .map(function (value) {
                return String(value || "").trim();
              })
              .filter(Boolean)
          : [];
        var packSelectionKey =
          !preferredMaterial && selectedTypes.length
            ? ":" + selectedTypes.slice().sort().join(",")
            : "";

        if (!context.jobsPostId && !String(context.jdText || "").trim()) {
          setFeedback(
            root,
            config.labels && config.labels.materialsError
              ? config.labels.materialsError
              : "We could not generate materials right now.",
            true
          );
          return;
        }

        if (!hasPremiumRecruiterAccess()) {
          redirectToMembership();
          return;
        }

        if (!cvText) {
          setFeedback(
            root,
            config.labels && config.labels.noCv
              ? config.labels.noCv
              : "Please upload your CV/Resume first to see matches.",
            true
          );
          if (floatingInput) {
            floatingInput.focus();
          } else if (textarea) {
            textarea.focus();
          }
          return;
        }

        openMaterialsModal(context);
        setMaterialsStatus("", "");
        if (materialsTabs) {
          materialsTabs.innerHTML = "";
        }
        activeMaterialsResources = [];
        if (materialsOutput) {
          materialsOutput.innerHTML = "";
        }
        if (materialsActions) {
          materialsActions.hidden = true;
        }
        startMaterialsLoader(preferredMaterial);

        cacheKey = buildMaterialsCacheKey(context, cvText) + packSelectionKey;
        cachedPack = materialsPackCache[cacheKey] || null;

        if (preferredMaterial) {
          singleCacheKey = cacheKey + ":" + preferredMaterial;
          cachedSingleResource = materialsPackCache[singleCacheKey] || null;

          if (!cachedSingleResource && Array.isArray(cachedPack)) {
            cachedSingleResource =
              cachedPack[getPreferredMaterialsIndex(cachedPack, preferredMaterial)] ||
              null;
            if (
              cachedSingleResource &&
              String((cachedSingleResource.material_type || "")).trim() !==
                preferredMaterial
            ) {
              cachedSingleResource = null;
            }
          }

          if (cachedSingleResource) {
            setMaterialsStatus("", "");
            renderMaterialsTabs([cachedSingleResource]);
            setActiveMaterialsResource(
              cachedSingleResource,
              [cachedSingleResource],
              0
            );
            return;
          }
        }

        if (!preferredMaterial && cachedPack) {
          preferredIndex = getPreferredMaterialsIndex(
            cachedPack,
            preferredMaterial
          );
          setMaterialsStatus("", "");
          renderMaterialsTabs(cachedPack);
          setActiveMaterialsResource(
            cachedPack[preferredIndex] || cachedPack[0],
            cachedPack,
            preferredIndex
          );
          return;
        }

        requestBody = new URLSearchParams();
        requestBody.append(
          "action",
          preferredMaterial
            ? "sffc_crm_reddit_material_generate"
            : "sffc_crm_reddit_material_pack_generate"
        );
        requestBody.append("nonce", config.materialNonce || "");
        requestBody.append("jobs_post_id", String(context.jobsPostId || 0));
        requestBody.append("cv_text", cvText);
        if (context.roleTitle) {
          requestBody.append("role_title", String(context.roleTitle || ""));
        }
        if (context.company) {
          requestBody.append("company", String(context.company || ""));
        }
        if (context.jdText) {
          requestBody.append("jd_text", String(context.jdText || ""));
        }
        if (preferredMaterial) {
          requestBody.append("material_type", preferredMaterial);
        } else if (selectedTypes.length) {
          requestBody.append("material_types", JSON.stringify(selectedTypes));
        }

        fetch(config.ajaxUrl || "/wp-admin/admin-ajax.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
          },
          body: requestBody.toString(),
        })
          .then(function (response) {
            return parseAjaxJson(response);
          })
          .then(function (payload) {
            var resources = [];
            var usedClaude = false;
            var singleResource = null;

            if (preferredMaterial) {
              singleResource =
                payload &&
                payload.success &&
                payload.data &&
                payload.data.resource &&
                typeof payload.data.resource === "object"
                  ? payload.data.resource
                  : null;
              if (singleResource) {
                resources = [singleResource];
                usedClaude =
                  String((singleResource.source || "")).trim() === "claude";
              }
            } else {
              resources =
                payload &&
                payload.success &&
                payload.data &&
                Array.isArray(payload.data.resources)
                  ? payload.data.resources
                  : [];
              usedClaude = !!(
                payload &&
                payload.success &&
                payload.data &&
                payload.data.generated_with_claude
              );
            }

            if (!resources.length) {
              throw new Error(
                payload && payload.data && payload.data.message
                  ? payload.data.message
                  : config.labels && config.labels.materialsError
                  ? config.labels.materialsError
                  : "We could not generate materials right now."
              );
            }

            if (!usedClaude) {
              throw new Error(
                payload && payload.data && payload.data.message
                  ? payload.data.message
                  : "MENA Careers generation is unavailable right now. No tailored materials were produced."
              );
            }

            if (preferredMaterial) {
              materialsPackCache[singleCacheKey] = resources[0];
            } else {
              materialsPackCache[cacheKey] = resources;
            }
            preferredIndex = getPreferredMaterialsIndex(
              resources,
              preferredMaterial
            );
            setMaterialsStatus("", "");
            renderMaterialsTabs(resources);
            setActiveMaterialsResource(
              resources[preferredIndex] || resources[0],
              resources,
              preferredIndex
            );
          })
          .catch(function (error) {
            stopMaterialsLoader();
            setMaterialsStatus(
              error && error.message
                ? error.message
                : config.labels && config.labels.materialsError
                ? config.labels.materialsError
                : "We could not generate materials right now.",
              "error"
            );
          });
      }

      function openSmartApplyMaterials(materialType) {
        var item = smartApplyActiveItem;

        if (!item) {
          return;
        }

        openGeneratedMaterials(materialType, {
          jobsPostId: Number(item.jobsPostId || 0),
          wpPostId: Number(item.wpPostId || 0),
          crmPostId: Number(item.id || 0),
          roleTitle: String(item.roleTitle || ""),
          company: String(item.company || ""),
          jdText: String(item.jdText || ""),
        });
      }

      function getApplicationPacksScope() {
        return $(root, "[data-cv-match-application-packs]");
      }

      function getApplicationPacksContext() {
        var scope = getApplicationPacksScope();
        var roleInput = scope
          ? $(scope, "[data-cv-match-application-packs-role]")
          : null;
        var companyInput = scope
          ? $(scope, "[data-cv-match-application-packs-company]")
          : null;
        var jdInput = scope
          ? $(scope, "[data-cv-match-application-packs-jd]")
          : null;

        return {
          jobsPostId: 0,
          wpPostId: 0,
          crmPostId: 0,
          roleTitle: roleInput ? String(roleInput.value || "").trim() : "",
          company: companyInput ? String(companyInput.value || "").trim() : "",
          jdText: jdInput ? String(jdInput.value || "").trim() : "",
        };
      }

      function getApplicationPacksCvText() {
        var scope = getApplicationPacksScope();
        var cvInput = scope
          ? $(scope, "[data-cv-match-application-packs-cv]")
          : null;
        var value = cvInput ? String(cvInput.value || "").trim() : "";
        return value || getCurrentCvText();
      }

      function getSelectedApplicationPackTypes() {
        var scope = getApplicationPacksScope();
        return scope
          ? $all(
              scope,
              "[data-cv-match-application-packs-type]:checked"
            ).map(function (input) {
              return String(input.value || "").trim();
            })
          : [];
      }

      function setApplicationPacksFeedback(message, isError) {
        var scope = getApplicationPacksScope();
        var feedback = scope
          ? $(scope, "[data-cv-match-application-packs-feedback]")
          : null;

        if (!feedback) {
          setFeedback(root, message, isError);
          return;
        }

        if (!message) {
          feedback.hidden = true;
          feedback.textContent = "";
          feedback.classList.remove("is-error", "is-success");
          return;
        }

        feedback.hidden = false;
        feedback.textContent = message;
        feedback.classList.toggle("is-error", !!isError);
        feedback.classList.toggle("is-success", !isError);
      }

      function syncApplicationPacksSelection() {
        var scope = getApplicationPacksScope();
        var submitButton = scope
          ? $(scope, "[data-cv-match-application-packs-submit]")
          : null;
        var cards = scope
          ? $all(scope, "[data-cv-match-application-packs-card]")
          : [];
        var selectedTypes = getSelectedApplicationPackTypes();

        cards.forEach(function (card) {
          var input = $(card, "[data-cv-match-application-packs-type]");
          var selected = !!(input && input.checked);
          card.classList.toggle("is-selected", selected);
        });

        if (submitButton) {
          submitButton.disabled = selectedTypes.length < 1;
          submitButton.textContent =
            selectedTypes.length > 1
              ? "Generate Selected Materials"
              : selectedTypes.length === 1
              ? "Generate Selected Material"
              : "Select Materials to Generate";
        }

        if (submitButton && selectedTypes.length === 1) {
          var labelNode = scope.querySelector(
            '[data-cv-match-application-packs-card][data-material-type="' +
              selectedTypes[0] +
              '"] .sffc-cv-match-studio__application-packs-choice-copy strong'
          );
          if (labelNode) {
            submitButton.textContent =
              "Generate " + String(labelNode.textContent || "").trim();
          }
        }
      }

      function syncApplicationPacksState() {
        var scope = getApplicationPacksScope();
        var cvInput = scope
          ? $(scope, "[data-cv-match-application-packs-cv]")
          : null;

        if (cvInput && !String(cvInput.value || "").trim()) {
          cvInput.value = getCurrentCvText();
        }

        syncApplicationPacksSelection();
      }

      function submitApplicationPacksRequest() {
        var context = getApplicationPacksContext();
        var cvText = getApplicationPacksCvText();
        var selectedTypes = getSelectedApplicationPackTypes();

        if (!cvText) {
          setApplicationPacksFeedback(
            "Paste your CV or load your active CV before generating materials.",
            true
          );
          return;
        }

        if (!context.jdText) {
          setApplicationPacksFeedback(
            "Paste the live job description before generating application materials.",
            true
          );
          return;
        }

        if (!selectedTypes.length) {
          setApplicationPacksFeedback(
            "Select at least one material to generate.",
            true
          );
          return;
        }

        setApplicationPacksFeedback("", false);
        openGeneratedMaterials(
          selectedTypes.length === 1 ? selectedTypes[0] : "",
          context,
          cvText,
          selectedTypes
        );
      }

      function updateFileStatus(text, target) {
        if (target) {
          target.textContent = text || "";
        }
        if (fileStatus && target !== fileStatus) {
          fileStatus.textContent = text || "";
        }
        if (floatingStatus && target !== floatingStatus) {
          floatingStatus.textContent = text || "";
        }
      }

      function showJobViewStatus(type, message) {
        var jobLoading = $(root, "[data-cv-match-job-loading]");
        var jobError = $(root, "[data-cv-match-job-error]");
        var jobHtml = $(root, "[data-cv-match-job-html]");

        if (jobLoading) {
          jobLoading.hidden = type !== "loading";
          if (type === "loading") {
            jobLoading.textContent = message || "Loading role details…";
          }
        }

        if (jobError) {
          jobError.hidden = type !== "error";
          if (type === "error") {
            jobError.textContent =
              message || "Unable to load this role right now.";
          }
        }

        if (jobHtml && type !== "ready") {
          jobHtml.innerHTML = "";
        }
      }

      function refreshDiscoveryView(queryState) {
        var discoveryBody = $(root, "[data-cv-match-discovery-body]");
        var discoveryShell = $(root, "[data-cv-match-discovery-shell]");
        var currentUrl = discoveryShell
          ? discoveryShell.getAttribute(
              "data-cv-match-discovery-current-url"
            ) || ""
          : "";
        var requestBody = new URLSearchParams();

        if (!discoveryBody) {
          return Promise.resolve();
        }

        discoveryBody.classList.add("is-loading");
        requestBody.append("action", "sffc_load_cv_match_discovery_view");
        requestBody.append("nonce", config.nonce || "");
        requestBody.append("current_url", currentUrl);
        requestBody.append("search_term", String(queryState.searchTerm || ""));
        requestBody.append(
          "discovery_filter",
          String(queryState.discoveryFilter || "")
        );
        requestBody.append(
          "recruiter_region",
          String(queryState.recruiterRegion || "")
        );
        requestBody.append(
          "recruiter_industry",
          String(queryState.recruiterIndustry || "")
        );
        requestBody.append(
          "recruiter_skill",
          String(queryState.recruiterSkill || "")
        );
        requestBody.append("page", String(queryState.page || 1));

        return fetch(config.ajaxUrl || "/wp-admin/admin-ajax.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
          },
          body: requestBody.toString(),
        })
          .then(function (response) {
            return parseAjaxJson(response);
          })
          .then(function (payload) {
            if (
              !payload ||
              !payload.success ||
              !payload.data ||
              !payload.data.html
            ) {
              throw new Error(
                payload && payload.data && payload.data.message
                  ? payload.data.message
                  : "Unable to load recruiter discovery right now."
              );
            }

            discoveryBody.innerHTML = payload.data.html;
          })
          .finally(function () {
            discoveryBody.classList.remove("is-loading");
          });
      }

      function loadJobView(item) {
        var requestBody;
        var jobHeading = $(root, "[data-cv-match-job-heading]");
        var jobHtml = $(root, "[data-cv-match-job-html]");
        var mainPane = root.querySelector(".sffc-cv-match-studio__main");

        if (!item || (!item.jobsPostId && !item.wpPostId && !item.id)) {
          return Promise.reject(new Error("Missing job id."));
        }

        if (jobHeading) {
          jobHeading.textContent = item.roleTitle || "Loading role details";
        }

        activeJobItem = item;
        closeMaterialsModal();
        showJobViewStatus("loading", "Loading role details…");
        if (mainPane) {
          root._cvMatchResultsScrollTop = mainPane.scrollTop || 0;
        }
        setState(root, "job");
        syncJobBackButton();

        requestBody = new URLSearchParams();
        requestBody.append("action", "sffc_load_cv_match_job_view");
        requestBody.append("nonce", config.nonce || "");
        requestBody.append("jobs_post_id", String(item.jobsPostId));
        requestBody.append("wp_post_id", String(item.wpPostId || 0));
        requestBody.append("crm_post_id", String(item.id || 0));
        requestBody.append("recruiter_id", String(item.recruiterId || 0));
        requestBody.append("role_title", String(item.roleTitle || ""));
        requestBody.append("match_score", String(item.score || 0));
        requestBody.append("match_reasons", JSON.stringify(item.reasons || []));
        requestBody.append(
          "match_warnings",
          JSON.stringify(item.warnings || [])
        );
        requestBody.append("match_gaps", JSON.stringify(item.gaps || []));
        requestBody.append("match_skills", JSON.stringify(item.skills || []));
        requestBody.append(
          "recruiter_open_roles_count",
          String(item.recruiterOpenRolesCount || 0)
        );
        requestBody.append("cv_text", getCurrentCvText());

        return fetch(config.ajaxUrl || "/wp-admin/admin-ajax.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
          },
          body: requestBody.toString(),
        })
          .then(function (response) {
            return parseAjaxJson(response);
          })
          .then(function (payload) {
            if (
              !payload ||
              !payload.success ||
              !payload.data ||
              !payload.data.html
            ) {
              throw new Error(
                payload && payload.data && payload.data.message
                  ? payload.data.message
                  : "Unable to load this role right now."
              );
            }

            if (jobHtml) {
              jobHtml.innerHTML = payload.data.html;
            }
            syncJobCvRequirements();
            if (mainPane) {
              mainPane.scrollTo({ top: 0, behavior: "auto" });
            }
            showJobViewStatus("ready");
          })
          .catch(function (error) {
            showJobViewStatus(
              "error",
              error && error.message
                ? error.message
                : "Unable to load this role right now."
            );
            throw error;
          });
      }

      function getCvMatchTrackerStageMeta(stage) {
        var stages =
          config.pipelineStages && typeof config.pipelineStages === "object"
            ? config.pipelineStages
            : {};
        var readableStage;

        if (stage && Object.prototype.hasOwnProperty.call(stages, stage)) {
          return {
            label: stages[stage].label || "Tracked",
            color: stages[stage].color || "#cbd5e1",
          };
        }

        if (stage) {
          readableStage = getReadableTrackerStageLabel(stage);
          return {
            label: readableStage || "Tracked",
            color: "#cbd5e1",
          };
        }

        return {
          label:
            config.labels && config.labels.trackPending
              ? config.labels.trackPending
              : "Not tracked yet",
          color: "#cbd5e1",
        };
      }

      function getReadableTrackerStageLabel(stage) {
        var normalized = String(stage || "").trim();
        var legacyLabels = {
          applied: "Applied",
          submitted: "Application Submitted",
          application: "Application Submitted",
          message_sent: "Messaged",
          messaged_recruiter: "Messaged",
          followup: "Follow Up",
          followed_up: "Follow Up",
          interview: "Interview",
          oa: "Online Assessment",
          assessment: "Assessment Centre",
          offer: "Offer Received",
        };

        if (!normalized) {
          return "";
        }

        if (Object.prototype.hasOwnProperty.call(legacyLabels, normalized)) {
          return legacyLabels[normalized];
        }

        return normalized
          .replace(/[_-]+/g, " ")
          .replace(/\s+/g, " ")
          .trim()
          .replace(/\b\w/g, function (match) {
            return match.toUpperCase();
          });
      }

      function updateCvMatchJobTrackerUi(jobCanvas, stage, pipelineId, explicitMeta) {
        var statusNode;
        var selectNode;
        var stageMeta;

        if (!jobCanvas) {
          return;
        }

        stageMeta = explicitMeta || getCvMatchTrackerStageMeta(stage);
        statusNode = jobCanvas.querySelector("[data-cv-match-track-status]");
        selectNode = jobCanvas.querySelector("[data-cv-match-track-stage]");

        jobCanvas.setAttribute("data-pipeline-id", pipelineId ? String(pipelineId) : "");
        jobCanvas.setAttribute("data-current-stage", stage || "");

        if (statusNode) {
          statusNode.textContent = stageMeta.label || "Tracked";
          statusNode.style.setProperty(
            "--job-track-color",
            stageMeta.color || "#cbd5e1"
          );
        }

        if (selectNode) {
          selectNode.value = stage || "";
        }
      }

      function updateJobsMailboxTrackerUi(paneNode, stage, pipelineId, explicitMeta) {
        var trackerLabel;
        var trackerToggle;
        var stageMeta;

        if (!paneNode) {
          return;
        }

        stageMeta = explicitMeta || getCvMatchTrackerStageMeta(stage);
        trackerLabel = $(paneNode, "[data-cv-match-mailbox-tracker-label]");
        trackerToggle = $(paneNode, "[data-cv-match-mailbox-tracker-toggle]");

        paneNode.setAttribute(
          "data-pipeline-id",
          pipelineId ? String(pipelineId) : ""
        );
        paneNode.setAttribute("data-current-stage", stage || "");
        paneNode.setAttribute(
          "data-current-stage-label",
          stageMeta.label || "Add to Tracker"
        );

        if (trackerLabel) {
          trackerLabel.textContent = stageMeta.label || "Add to Tracker";
        }

        if (trackerToggle) {
          trackerToggle.classList.toggle("is-tracked", !!stage);
          trackerToggle.style.setProperty(
            "--jobs-mailbox-tracker-accent",
            stageMeta.color || "#cbd5e1"
          );
        }
      }

      function saveTrackerStageForContext(context, stage, options) {
        var requestBody;
        var previousStage;
        var previousPipelineId;
        var onPending;
        var onSuccess;
        var onError;
        var paneNode;

        if (!context || !stage) {
          return Promise.resolve();
        }

        if (!config.loggedIn || !config.hasPremiumAccess) {
          redirectToMembership();
          return Promise.resolve();
        }

        if (!config.ajaxUrl || !(config.crmNonce || config.nonce)) {
          return Promise.reject(new Error("Tracker is unavailable right now."));
        }

        previousStage = String(context.currentStage || "");
        previousPipelineId = Number(context.pipelineId || 0);
        onPending =
          options && typeof options.onPending === "function"
            ? options.onPending
            : null;
        onSuccess =
          options && typeof options.onSuccess === "function"
            ? options.onSuccess
            : null;
        onError =
          options && typeof options.onError === "function"
            ? options.onError
            : null;
        paneNode = options && options.paneNode ? options.paneNode : null;

        if (onPending) {
          onPending();
        }

        requestBody = new window.FormData();
        requestBody.append("action", "sffc_crm_save_dashboard_tracking_status");
        requestBody.append("nonce", config.crmNonce || config.nonce || "");
        requestBody.append("pipeline_id", String(previousPipelineId || 0));
        requestBody.append("stage", stage);
        requestBody.append("role_title", context.roleTitle || "");
        requestBody.append("company", context.company || "");
        requestBody.append("location", context.location || "");
        requestBody.append("external_url", context.externalUrl || "");

        if (context.recruiterId) {
          requestBody.append("recruiter_id", String(context.recruiterId));
        }
        if (context.crmPostId) {
          requestBody.append("post_id", String(context.crmPostId));
        }
        if (context.wpPostId) {
          requestBody.append("wp_post_id", String(context.wpPostId));
        }

        return window
          .fetch(config.ajaxUrl, {
            method: "POST",
            credentials: "same-origin",
            body: requestBody,
          })
          .then(parseAjaxJson)
          .then(function (payload) {
            var nextPipelineId;
            var stageMeta;

            if (!payload || payload.success !== true || !payload.data) {
              throw new Error(
                (payload && payload.data && payload.data.message) ||
                  "Unable to save tracking status."
              );
            }

            nextPipelineId =
              parseInt(payload.data.pipeline_id || "0", 10) || previousPipelineId;
            stageMeta = {
              label:
                (payload.data && payload.data.stage_label) ||
                getCvMatchTrackerStageMeta(stage).label,
              color:
                (payload.data && payload.data.stage_color) ||
                getCvMatchTrackerStageMeta(stage).color,
            };

            if (paneNode) {
              updateJobsMailboxTrackerUi(
                paneNode,
                stage,
                nextPipelineId,
                stageMeta
              );
            }

            if (onSuccess) {
              onSuccess(stageMeta, nextPipelineId);
            }

            return refreshCvMatchTrackerBoard();
          })
          .catch(function (error) {
            if (paneNode) {
              updateJobsMailboxTrackerUi(
                paneNode,
                previousStage,
                previousPipelineId,
                getCvMatchTrackerStageMeta(previousStage)
              );
            }
            if (onError) {
              onError(error);
            }
            throw error;
          });
      }

      function refreshCvMatchTrackerBoard() {
        var trackerShell = root.querySelector(
          ".sffc-cv-match-studio__tracker-dashboard[data-dashboard-shell]"
        );
        var requestBody;
        var currentUrl;
        var href;
        var trackerUrl;

        if (
          !trackerShell ||
          !config.ajaxUrl ||
          !config.dashboardTabNonce ||
          typeof window.fetch !== "function"
        ) {
          return Promise.resolve();
        }

        currentUrl =
          trackerShell.getAttribute("data-dashboard-current-url") ||
          window.location.href;

        try {
          trackerUrl = new window.URL(currentUrl, window.location.origin);
        } catch (error) {
          trackerUrl = new window.URL(window.location.href);
        }

        trackerUrl.searchParams.set("dashboard_tab", "tracking");
        trackerUrl.searchParams.set("matches_filter", "live-feed");
        trackerUrl.searchParams.set("metrics_window", "month");
        trackerUrl.searchParams.set("matches_page", "1");
        href = trackerUrl.toString();

        requestBody = new window.FormData();
        requestBody.append("action", "sffc_crm_reddit_dashboard_tab");
        requestBody.append("nonce", config.dashboardTabNonce);
        requestBody.append("href", href);
        requestBody.append(
          "jobs_post_id",
          trackerShell.getAttribute("data-dashboard-jobs-post-id") || ""
        );
        requestBody.append(
          "per_page",
          trackerShell.getAttribute("data-dashboard-per-page") || ""
        );
        requestBody.append(
          "fallback_role",
          trackerShell.getAttribute("data-dashboard-fallback-role") || ""
        );
        requestBody.append(
          "current_url",
          trackerShell.getAttribute("data-dashboard-current-url") || currentUrl
        );

        return window
          .fetch(config.ajaxUrl, {
            method: "POST",
            credentials: "same-origin",
            body: requestBody,
          })
          .then(parseAjaxJson)
          .then(function (payload) {
            var parser;
            var doc;
            var nextBoard;

            if (!payload || !payload.success || !payload.data || !payload.data.markup) {
              throw new Error("Unable to refresh Tracker right now.");
            }

            parser = new window.DOMParser();
            doc = parser.parseFromString(String(payload.data.markup), "text/html");
            nextBoard = doc.querySelector("[data-dashboard-board]");

            if (!nextBoard) {
              throw new Error("Tracker refresh markup was incomplete.");
            }

            trackerShell.innerHTML = nextBoard.outerHTML;
          })
          .catch(function () {
            return null;
          });
      }

      function saveCvMatchJobTrackerStage(selectNode) {
        var jobCanvas = selectNode
          ? selectNode.closest("[data-cv-match-job-canvas]")
          : null;
        var stage = selectNode ? String(selectNode.value || "") : "";
        var feedbackNode = jobCanvas
          ? jobCanvas.querySelector("[data-cv-match-track-feedback]")
          : null;
        var previousValue;
        var context;

        if (!jobCanvas || !selectNode || !stage) {
          return Promise.resolve();
        }

        if (!config.loggedIn || !config.hasPremiumAccess) {
          redirectToMembership();
          return Promise.resolve();
        }

        if (!config.ajaxUrl || !(config.crmNonce || config.nonce)) {
          if (feedbackNode) {
            feedbackNode.textContent = "Tracker is unavailable right now.";
            feedbackNode.classList.add("is-error");
          }
          return Promise.resolve();
        }

        previousValue = selectNode.getAttribute("data-last-value") || "";
        context = {
          roleTitle: jobCanvas.getAttribute("data-role-title") || "",
          company: jobCanvas.getAttribute("data-company") || "",
          location: jobCanvas.getAttribute("data-location") || "",
          externalUrl: jobCanvas.getAttribute("data-external-url") || "",
          recruiterId: Number(jobCanvas.getAttribute("data-recruiter-id") || 0),
          crmPostId: Number(jobCanvas.getAttribute("data-crm-post-id") || 0),
          wpPostId: Number(jobCanvas.getAttribute("data-wp-post-id") || 0),
          pipelineId: Number(jobCanvas.getAttribute("data-pipeline-id") || 0),
          currentStage: jobCanvas.getAttribute("data-current-stage") || "",
        };

        if (feedbackNode) {
          feedbackNode.textContent = "Saving to Tracker…";
          feedbackNode.classList.remove("is-error", "is-success");
        }

        selectNode.disabled = true;
        return saveTrackerStageForContext(context, stage, {
          onSuccess: function (stageMeta, nextPipelineId) {
            var successMessage =
              context.pipelineId > 0 || context.currentStage
                ? "Tracker updated to " + stageMeta.label + "."
                : "Added to Tracker as " + stageMeta.label + ".";

            updateCvMatchJobTrackerUi(jobCanvas, stage, nextPipelineId, stageMeta);
            selectNode.setAttribute("data-last-value", stage);
            if (feedbackNode) {
              feedbackNode.textContent = successMessage;
              feedbackNode.classList.remove("is-error");
              feedbackNode.classList.add("is-success");
            }
          },
          onError: function (error) {
            selectNode.value = previousValue;
            updateCvMatchJobTrackerUi(
              jobCanvas,
              context.currentStage,
              context.pipelineId,
              getCvMatchTrackerStageMeta(context.currentStage)
            );
            if (feedbackNode) {
              feedbackNode.textContent =
                error && error.message
                  ? error.message
                  : "Unable to save tracking status.";
              feedbackNode.classList.remove("is-success");
              feedbackNode.classList.add("is-error");
            }
          },
        })
          .catch(function (error) {
            return null;
          })
          .finally(function () {
            selectNode.disabled = false;
          });
      }

      function handleFile(file, targetInput, statusNode) {
        updateFileStatus(
          "Reading " + (file && file.name ? file.name : "resume") + "...",
          statusNode || fileStatus
        );
        setFeedback(root, "", false);
        return parseFile(file)
          .then(function (text) {
            if (!text) {
              throw new Error(
                config.labels && config.labels.parseError
                  ? config.labels.parseError
                  : "We could not read that file yet."
              );
            }
            if (targetInput) {
              targetInput.value = text;
            } else if (textarea) {
              textarea.value = text;
            }
            if (targetInput !== textarea && textarea) {
              textarea.value = text;
            }
            if (targetInput !== floatingInput && floatingInput) {
              floatingInput.value = text;
            }
            syncCvTextState(text, targetInput || textarea);
            updateFileStatus(
              config.labels && config.labels.dropReady
                ? config.labels.dropReady
                : "Resume loaded.",
              statusNode || fileStatus
            );
            setFeedback(
              root,
              config.labels && config.labels.dropReady
                ? config.labels.dropReady
                : "Resume loaded.",
              false
            );
            return persistCvToProfile(text, {
              fileName: file && file.name ? file.name : "",
            }).then(function () {
              return text;
            });
          })
          .catch(function (error) {
            updateFileStatus(
              config.labels && config.labels.parseError
                ? config.labels.parseError
                : "We could not read that file yet.",
              statusNode || fileStatus
            );
            setFeedback(
              root,
              error.message ||
                (config.labels && config.labels.parseError
                  ? config.labels.parseError
                  : "We could not read that file yet."),
              true
            );
            throw error;
          });
      }

      function submitScan(sourceInput) {
        var inputNode = sourceInput || textarea;
        var cvText = inputNode ? inputNode.value.trim() : "";
        var requestBody;

        if (!cvText) {
          setFeedback(
            root,
            config.labels && config.labels.emptyInput
              ? config.labels.emptyInput
              : "Paste your CV first.",
            true
          );
          if (inputNode) {
            inputNode.focus();
          }
          return;
        }

        syncCvTextState(cvText, inputNode);
        setFeedback(root, "", false);
        hideFloatingShell();
        root._cvMatchResultsReportAiPayload = null;
        root._cvMatchResultsReportAiHash = "";
        root._cvMatchResultsReportAiStatus = "";
        setState(root, "scanning");
        activeScanner = buildScanner(root);
        persistCvToProfile(cvText);

        if (runButton) {
          runButton.disabled = true;
          runButton.textContent =
            config.labels && config.labels.scanningButton
              ? config.labels.scanningButton
              : "Scanning live roles...";
        }
        if (commandSubmit) {
          commandSubmit.disabled = true;
          commandSubmit.textContent =
            config.labels && config.labels.scanningButton
              ? config.labels.scanningButton
              : "Scanning live roles...";
        }
        setCommandStatus("Scanning live roles...");

        requestBody = buildMatchRequestBody(cvText, {});

        fetch(config.ajaxUrl || "/wp-admin/admin-ajax.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
          },
          body: requestBody.toString(),
        })
          .then(function (response) {
            return parseAjaxJson(response);
          })
          .then(function (payload) {
            if (!payload || !payload.success || !payload.data) {
              throw new Error(
                payload && payload.data && payload.data.message
                  ? payload.data.message
                  : "Unable to score matches right now."
              );
            }

            activeResults = Array.isArray(payload.data.items)
              ? payload.data.items.map(normalizeItem)
              : [];
            baseResults = activeResults.slice();
            root._cvMatchBaseResults = baseResults.slice();
            root._cvMatchCustomSearchActive = false;
            root._cvMatchCustomSearchSummary = "";
            root._cvMatchResultsReportPayload =
              payload && payload.data && payload.data.report
                ? payload.data.report
                : null;
            root._cvMatchResultsReportAiPayload = null;
            root._cvMatchResultsReportAiHash = "";
            root._cvMatchResultsReportAiStatus = "";
            if (activeScanner) {
              activeScanner.updatePreview(
                activeResults
                  .map(function (item) {
                    return {
                      roleTitle: item.roleTitle,
                      company: item.company,
                      companyLogo: item.companyLogo,
                      recruiterName: item.recruiterName,
                      recruiterPhoto: item.recruiterPhoto,
                      recruiterTitle: item.recruiterTitle,
                      tags: [item.location]
                        .concat(item.reasons.slice(0, 2))
                        .filter(Boolean)
                        .slice(0, 3),
                    };
                  })
                  .slice(0, 3)
              );
            }
            activeScanner.complete();

            window.setTimeout(function () {
              setState(root, "results");
              root._cvMatchPreviousState = "results";
              syncControlledSearchUi();
              setControlledSearchFeedback("", false);
              refreshResults();
              setCommandStatus("Role matches loaded");
            }, 260);
          })
          .catch(function (error) {
            if (activeScanner) {
              activeScanner.fail();
            }
            resetView();
            setFeedback(
              root,
              error.message || "Unable to score matches right now.",
              true
            );
          })
          .finally(function () {
            if (runButton) {
              runButton.disabled = false;
              runButton.textContent =
                config.labels && config.labels.scanButton
                  ? config.labels.scanButton
                  : "Scan my CV";
            }
            if (commandSubmit) {
              commandSubmit.disabled = false;
              commandSubmit.textContent = "Run";
            }
          });
      }

      function resetControlledSearchResults() {
        root._cvMatchCustomSearchActive = false;
        root._cvMatchCustomSearchSummary = "";
        activeResults = Array.isArray(root._cvMatchBaseResults)
          ? root._cvMatchBaseResults.slice()
          : [];
        syncControlledSearchUi();
        setControlledSearchFeedback("Showing your default matched roles.", false);
        refreshResults();
      }

      function runControlledSearch() {
        var cvText = getCurrentCvText();
        var filters = getControlledSearchFilters();
        var requestBody;

        if (!cvText) {
          setControlledSearchFeedback("Upload or paste your CV first.", true);
          return;
        }

        if (!controlledSearchHasFilters(filters)) {
          resetControlledSearchResults();
          return;
        }

        requestBody = new URLSearchParams();
        requestBody.append("action", "sffc_crm_console_scan_matches");
        requestBody.append("nonce", config.nonce || "");
        requestBody.append(
          "criteria",
          JSON.stringify(buildControlledConsoleCriteria(filters))
        );
        if (controlledSearchSubmit) {
          controlledSearchSubmit.disabled = true;
          controlledSearchSubmit.textContent = "Searching...";
        }
        setControlledSearchFeedback("Running a controlled role search…", false);

        fetch(config.ajaxUrl || "/wp-admin/admin-ajax.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
          },
          body: requestBody.toString(),
        })
          .then(parseAjaxJson)
          .then(function (payload) {
            if (!payload || !payload.success || !payload.data) {
              throw new Error(
                payload && payload.data && payload.data.message
                  ? payload.data.message
                  : "Unable to run this role search right now."
              );
            }

            activeResults = Array.isArray(payload.data.matches)
              ? payload.data.matches.map(normalizeItem)
              : [];
            root._cvMatchCustomSearchActive = true;
            root._cvMatchCustomSearchSummary = controlledSearchSummary(filters);
            syncControlledSearchUi();
            setControlledSearchFeedback(
              activeResults.length
                ? "Controlled search updated the live role table."
                : "No live roles matched that controlled search.",
              false
            );
            refreshResults();
            setCommandStatus("Controlled search loaded");
          })
          .catch(function (error) {
            setControlledSearchFeedback(
              error && error.message
                ? error.message
                : "Unable to run this role search right now.",
              true
            );
          })
          .finally(function () {
            if (controlledSearchSubmit) {
              controlledSearchSubmit.disabled = false;
              controlledSearchSubmit.textContent = "Search";
            }
          });
      }

      uploadTriggers.forEach(function (button) {
        button.addEventListener("click", function () {
          var shouldAutoRun =
            button.classList.contains("is-utility") ||
            button.classList.contains("sffc-cv-match-studio__landing-upload-zone") ||
            !!button.closest(".sffc-cv-match-studio__prompt-search--landing-card") ||
            !!button.closest('[data-cv-match-state="careers-search"]');
          if (fileInput) {
            fileInput.dataset.targetInput = isStandaloneJob ? "floating" : "main";
            fileInput.dataset.autoRun = shouldAutoRun ? "1" : "";
            if (button.classList.contains("is-utility")) {
              setMobileNavOpen(false);
            }
            fileInput.click();
          }
        });
      });

      if (cvOnboardingUpload && fileInput) {
        cvOnboardingUpload.addEventListener("click", function () {
          fileInput.dataset.targetInput = "onboarding";
          fileInput.dataset.autoRun = "";
          fileInput.click();
        });
      }

      if (welcomeCvUpload && fileInput) {
        welcomeCvUpload.addEventListener("click", function () {
          fileInput.dataset.targetInput = "welcome";
          fileInput.dataset.autoRun = "";
          fileInput.click();
        });
      }

      if (expertCvReviewUpload && fileInput) {
        expertCvReviewUpload.addEventListener("click", function () {
          if (!config.loggedIn) {
            redirectToMembership();
            return;
          }
          fileInput.dataset.targetInput = "expert-review";
          fileInput.dataset.autoRun = "";
          fileInput.click();
        });
      }

      preferredIndustryInputs.forEach(function (input) {
        input.addEventListener("change", function () {
          if (input.checked) {
            handlePreferredIndustryChange(input.value);
          }
        });
      });

      preferredLocationInputs.forEach(function (input) {
        input.addEventListener("change", syncWelcomeOptionStates);
      });

      welcomeNewsletterInputs.forEach(function (input) {
        input.addEventListener("change", function () {
          syncWelcomeOptionStates();
          syncWelcomeProceedButton();
        });
      });

      if (controlledSearchForm) {
        controlledSearchForm.addEventListener("submit", function (event) {
          event.preventDefault();
          runControlledSearch();
        });
      }

      if (controlledSearchAdd) {
        controlledSearchAdd.addEventListener("click", function () {
          addControlledSearchBar();
        });
      }

      if (controlledSearchReset) {
        controlledSearchReset.addEventListener("click", function () {
          resetControlledSearchResults();
        });
      }

      if (controlledSearchForm) {
        controlledSearchForm.addEventListener("click", function (event) {
          var removeButton = event.target.closest("[data-cv-match-controlled-remove]");
          var bar;
          if (!removeButton) {
            return;
          }
          bar = removeButton.closest("[data-cv-match-controlled-bar]");
          if (!bar) {
            return;
          }
          bar.remove();
          syncControlledSearchUi();
        });
      }

      floatingUploadTriggers.forEach(function (button) {
        button.addEventListener("click", function () {
          if (fileInput) {
            fileInput.dataset.targetInput = "floating";
            fileInput.dataset.autoRun = isStandaloneJob ? "1" : "";
            fileInput.click();
          }
        });
      });

      resetButtons.forEach(function (button) {
        button.addEventListener("click", function () {
          resetView();
        });
      });

      floatingOpenButtons.forEach(function (button) {
        button.addEventListener("click", function () {
          showFloatingShell();
        });
      });

      floatingCloseButtons.forEach(function (button) {
        button.addEventListener("click", function () {
          hideFloatingShell();
        });
      });

      if (smartApplyCopy) {
        smartApplyCopy.addEventListener("click", function () {
          if (!smartApplyEmailBody) {
            return;
          }
          navigator.clipboard
            .writeText(getSmartApplyBodyText(smartApplyEmailBody))
            .then(function () {
              smartApplyCopy.textContent = "Copied";
              window.setTimeout(function () {
                smartApplyCopy.textContent = "Copy Email";
              }, 1400);
            });
        });
      }

      if (smartApplyQueueNext) {
        smartApplyQueueNext.addEventListener("click", function () {
          advanceSmartApplyQueue();
        });
      }

      if (smartApplyToolbarButtons.length && smartApplyEmailBody) {
        smartApplyToolbarButtons.forEach(function (button) {
          button.addEventListener("click", function () {
            var command = button.getAttribute("data-smart-apply-toolbar") || "";
            var linkUrl = "";

            if (
              smartApplyEmailBody.getAttribute("contenteditable") === "false"
            ) {
              return;
            }

            smartApplyEmailBody.focus();

            if (command === "link") {
              linkUrl = window.prompt(
                "Enter a URL to insert into this email:",
                "https://"
              );
              if (!linkUrl) {
                return;
              }
              document.execCommand("createLink", false, linkUrl);
              return;
            }

            if (command === "bullets") {
              document.execCommand("insertUnorderedList", false);
              return;
            }

            if (command === "clear") {
              document.execCommand("removeFormat", false);
              return;
            }

            if (command === "bold" || command === "italic") {
              document.execCommand(command, false);
            }
          });
        });
      }

      if (materialsCopy) {
        materialsCopy.addEventListener("click", function () {
          var copySource =
            activeMaterialsResource && activeMaterialsResource.copy_text
              ? activeMaterialsResource.copy_text
              : "";

          if (!copySource && materialsOutput) {
            var inlineCopySource = $(
              materialsOutput,
              "[data-material-copy-source]"
            );
            copySource = inlineCopySource ? inlineCopySource.value : "";
          }

          if (!copySource) {
            return;
          }

          navigator.clipboard.writeText(copySource).then(function () {
            materialsCopy.textContent = "Copied";
            window.setTimeout(function () {
              materialsCopy.textContent = "Copy Material";
            }, 1400);
          });
        });
      }

      if (fileInput) {
        fileInput.addEventListener("change", function () {
          if (fileInput.files && fileInput.files[0]) {
            var targetKind = fileInput.dataset.targetInput || "";
            var selectedFile = fileInput.files[0];
            var targetInput =
              targetKind === "floating"
                ? floatingInput
                : targetKind === "onboarding"
                ? cvOnboardingInput
                : targetKind === "welcome"
                ? cvOnboardingInput || textarea
                : targetKind === "command"
                ? commandInput
                : targetKind === "expert-review"
                ? textarea
                : textarea;
            var targetStatus =
              targetKind === "floating"
                ? floatingStatus
                : targetKind === "onboarding"
                ? cvOnboardingStatus
                : targetKind === "welcome"
                ? welcomeCvUploadStatus
                : targetKind === "command"
                ? commandStatus
                : targetKind === "expert-review"
                ? expertCvReviewUploadStatus
                : fileStatus;
            var shouldAutoRun = fileInput.dataset.autoRun === "1";
            handleFile(selectedFile, targetInput, targetStatus)
              .then(function () {
                if (targetKind === "welcome") {
                  setWelcomeCvLoaded(
                    selectedFile && selectedFile.name ? selectedFile.name : ""
                  );
                }

                if (targetKind === "expert-review") {
                  if (expertCvReviewUploadStatus) {
                    expertCvReviewUploadStatus.textContent =
                      (selectedFile && selectedFile.name
                        ? selectedFile.name
                        : "CV") + " saved to your MENA Careers profile.";
                  }
                  setExpertCvReviewFeedback(
                    "CV uploaded and saved. Add the roles you are targeting, then send it for review.",
                    false
                  );
                  syncCvSourceSelects("active");
                }

                if (shouldAutoRun) {
                  if (isStandaloneJob) {
                    applyStandaloneJobCvContext(
                      targetInput || floatingInput || textarea,
                      targetStatus
                    );
                  } else {
                    submitScan(targetInput || textarea);
                  }
                }
              })
              .catch(function () {
                return null;
              });
          }
          fileInput.dataset.targetInput = "";
          fileInput.dataset.autoRun = "";
        });
      }

      if (dropzone) {
        ["dragenter", "dragover"].forEach(function (eventName) {
          dropzone.addEventListener(eventName, function (event) {
            event.preventDefault();
            dropzone.classList.add("is-dragover");
          });
        });

        ["dragleave", "drop"].forEach(function (eventName) {
          dropzone.addEventListener(eventName, function (event) {
            event.preventDefault();
            if (eventName === "drop") {
              var file =
                event.dataTransfer && event.dataTransfer.files
                  ? event.dataTransfer.files[0]
                  : null;
              var targetInput = isStandaloneJob ? floatingInput || textarea : textarea;
              var targetStatus =
                isStandaloneJob ? floatingStatus || fileStatus : fileStatus;
              if (file) {
                handleFile(file, targetInput, targetStatus)
                  .then(function () {
                    if (isStandaloneJob) {
                      applyStandaloneJobCvContext(targetInput, targetStatus);
                    } else {
                      submitScan(targetInput);
                    }
                  })
                  .catch(function () {
                    return null;
                  });
              }
            }
            dropzone.classList.remove("is-dragover");
          });
        });
      }

      if (runButton) {
        runButton.addEventListener("click", function () {
          submitScan(textarea);
        });
      }

      if (commandSubmit) {
        commandSubmit.addEventListener("click", function () {
          submitCommandBar();
        });
      }

      if (commandUpload && fileInput) {
        commandUpload.addEventListener("click", function () {
          fileInput.dataset.targetInput = "command";
          fileInput.dataset.autoRun = "1";
          fileInput.click();
        });
      }

      recommendedSearchForms.forEach(function (form) {
        form.addEventListener("submit", function (event) {
          var input = $(form, "[data-cv-match-recommended-search-input]");
          event.preventDefault();
          if (recommendedSearchTimer) {
            window.clearTimeout(recommendedSearchTimer);
            recommendedSearchTimer = 0;
          }
          if (isRecommendedDatabaseSearchNode(form)) {
            runRecommendedDatabaseSearch(input ? input.value : "");
          } else {
            runRecommendedListSearch(input ? input.value : "");
          }
        });
      });

      recommendedSearchInputs.forEach(function (input) {
        input.addEventListener("keydown", function (event) {
          if (event.key === " ") {
            event.stopPropagation();
          }
        });

        input.addEventListener("input", function () {
          syncRecommendedSearchInputs(input.value);
          if (isRecommendedDatabaseSearchNode(input)) {
            scheduleRecommendedDatabaseSearch(input.value);
          } else {
            scheduleRecommendedListSearch(input.value);
          }
        });

        input.addEventListener("focus", function () {
          if (isRecommendedDatabaseSearchNode(input)) {
            if (currentStateName() !== "recommended") {
              setState(root, "recommended");
            }
            runRecommendedDatabaseSearch(input.value);
          } else {
            if (currentStateName() !== "newsletter-groups") {
              setState(root, "newsletter-groups");
            }
            runRecommendedListSearch(input.value);
          }
        });
      });

      if (floatingRun) {
        floatingRun.addEventListener("click", function () {
          if (isStandaloneJob) {
            applyStandaloneJobCvContext(floatingInput, floatingStatus);
          } else {
            submitScan(floatingInput);
          }
        });
      }

      if (textarea) {
        autoResizePromptInput();
        textarea.addEventListener("input", function () {
          autoResizePromptInput();
          syncCvTextState(textarea.value, textarea);
          scheduleCvPersist(textarea.value);
        });
        textarea.addEventListener("keydown", function (event) {
          if ((event.metaKey || event.ctrlKey) && event.key === "Enter") {
            event.preventDefault();
            submitScan(textarea);
            return;
          }

          if (event.key === "Enter" && !event.shiftKey) {
            event.preventDefault();
            submitScan(textarea);
          }
        });
      }

      if (commandInput) {
        if (activeCvText && !String(commandInput.value || "").trim()) {
          commandInput.value = activeCvText;
        }
        autoResizeCommandInput();
        commandInput.addEventListener("input", function () {
          autoResizeCommandInput();
          if (inputLooksLikeCv(commandInput.value)) {
            syncCvTextState(commandInput.value, commandInput);
            scheduleCvPersist(commandInput.value);
            setCommandStatus("CV detected. Upload or run the scan.");
          } else {
            setCommandStatus("Search roles or paste your CV");
          }
        });
        commandInput.addEventListener("keydown", function (event) {
          if ((event.metaKey || event.ctrlKey) && event.key === "Enter") {
            event.preventDefault();
            submitCommandBar();
            return;
          }

          if (event.key === "Enter" && !event.shiftKey) {
            event.preventDefault();
            submitCommandBar();
          }
        });
      }

      if (floatingInput) {
        floatingInput.addEventListener("input", function () {
          syncCvTextState(floatingInput.value, floatingInput);
          scheduleCvPersist(floatingInput.value);
        });
        floatingInput.addEventListener("keydown", function (event) {
          if ((event.metaKey || event.ctrlKey) && event.key === "Enter") {
            event.preventDefault();
            if (isStandaloneJob) {
              applyStandaloneJobCvContext(floatingInput, floatingStatus);
            } else {
              submitScan(floatingInput);
            }
          }
        });
      }

      if (cvOnboardingInput) {
        cvOnboardingInput.addEventListener("input", function () {
          syncCvTextState(cvOnboardingInput.value, cvOnboardingInput);
          setCvOnboardingFeedback("", false);
        });
        cvOnboardingInput.addEventListener("keydown", function (event) {
          if ((event.metaKey || event.ctrlKey) && event.key === "Enter") {
            event.preventDefault();
            submitCvOnboarding();
          }
        });
      }

      if (cvOnboardingSubmit) {
        cvOnboardingSubmit.addEventListener("click", function () {
          submitCvOnboarding();
        });
      }

      [searchNode, sortNode, filterNode].forEach(function (node) {
        if (!node) {
          return;
        }
        node.addEventListener("input", refreshResults);
        node.addEventListener("change", refreshResults);
      });

      if (recentSearchNode) {
        recentSearchNode.addEventListener("input", function () {
          renderRecentRoles(root);
        });
      }

      if (sidebarToggles.length) {
        sidebarToggles.forEach(function (toggle) {
          toggle.addEventListener("click", function () {
            if (isMobileNavViewport()) {
              setMobileNavOpen(!root.classList.contains("is-mobile-nav-open"));
              return;
            }
            setSidebarCollapsed(
              !root.classList.contains("is-sidebar-collapsed")
            );
          });
        });
      }

      if (mobileNavToggle) {
        mobileNavToggle.addEventListener("click", function () {
          setMobileCommandOpen(false);
          setMobileNavOpen(!root.classList.contains("is-mobile-nav-open"));
        });
      }

      if (mobileNavClose) {
        mobileNavClose.addEventListener("click", function () {
          setMobileNavOpen(false);
        });
      }

      window.addEventListener("resize", function () {
        if (!isMobileNavViewport()) {
          setMobileNavOpen(false);
          setMobileCommandOpen(false);
        }
        syncStandaloneJobDock();
        if (onboardingState.active && onboardingState.target) {
          positionTourPopover(onboardingState.target);
        }
      });

      window.addEventListener("scroll", function () {
        if (onboardingState.active && onboardingState.target) {
          positionTourPopover(onboardingState.target);
        }
      });

      (function initMobileScrollChrome() {
        var lastScrollTop = 0;
        var ticking = false;
        var threshold = 14;

        function getScrollTop(source) {
          if (source && source !== window) {
            return Math.max(0, source.scrollTop || 0);
          }
          return Math.max(
            0,
            window.pageYOffset ||
              document.documentElement.scrollTop ||
              document.body.scrollTop ||
              0
          );
        }

        function updateChrome(source) {
          ticking = false;
          if (!isMobileNavViewport()) {
            root.classList.remove("is-mobile-chrome-hidden");
            lastScrollTop = getScrollTop(source);
            return;
          }

          if (
            root.classList.contains("is-mobile-nav-open") ||
            root.classList.contains("is-mobile-command-open")
          ) {
            root.classList.remove("is-mobile-chrome-hidden");
            lastScrollTop = getScrollTop(source);
            return;
          }

          var nextScrollTop = getScrollTop(source);
          var delta = nextScrollTop - lastScrollTop;

          if (nextScrollTop < 48) {
            root.classList.remove("is-mobile-chrome-hidden");
          } else if (delta > threshold) {
            root.classList.add("is-mobile-chrome-hidden");
          } else if (delta < -threshold) {
            root.classList.remove("is-mobile-chrome-hidden");
          }

          lastScrollTop = nextScrollTop;
        }

        function requestChromeUpdate(source) {
          if (ticking) {
            return;
          }
          ticking = true;
          window.requestAnimationFrame(function () {
            updateChrome(source);
          });
        }

        lastScrollTop = getScrollTop(mainPane || window);
        window.addEventListener(
          "scroll",
          function () {
            requestChromeUpdate(window);
          },
          { passive: true }
        );
        if (mainPane) {
          mainPane.addEventListener(
            "scroll",
            function () {
              requestChromeUpdate(mainPane);
            },
            { passive: true }
          );
        }
      })();

      window.addEventListener("keydown", function (event) {
        if (event.key === "Escape" && messagesModal && !messagesModal.hidden) {
          closeMessagesModal();
        }
      });

      window.addEventListener("sffc:cv-updated", function (event) {
        var detail = event && event.detail ? event.detail : {};
        var nextCvText = String(detail.cvText || "").trim();

        if (!nextCvText) {
          return;
        }

        syncCvTextState(nextCvText);
        persistedCvHash = hashText(nextCvText);
        if (activeCvSource) {
          syncCvSourceSelects(activeCvSource);
        }
        updateResultsCtaCard(root, detail.atsState || config.initialAtsState || {});
        root._cvMatchRecommendedSignature = "";
        root._cvMatchRecommendedPayload = null;
        loadLandingTileRoles(true);
        loadJobsMailboxSearchResults(root._cvMatchJobsMailboxSearchQuery || "", {
          focusInput: false,
        });
        updateFileStatus(
          config.labels && config.labels.dropReady
            ? config.labels.dropReady
            : "Resume loaded.",
          fileStatus
        );
        if (cvOnboardingStatus) {
          cvOnboardingStatus.textContent =
            (config.labels && config.labels.dropReady) || "Resume loaded.";
        }
        if (cvOnboardingInput) {
          cvOnboardingInput.value = nextCvText;
        }

        if (isCareerReportActive()) {
          loadCareerReport(true);
        }
      });

      root.addEventListener("sffc:gap-analyzer-exit", function (event) {
        if (!event || !event.detail) {
          return;
        }

        var studioMain = mainPane || $(root, ".sffc-cv-match-studio__main");
        var targetState = String(event.detail.targetState || "results");

        closeMaterialsModal();
        closeSmartApply();
        closeRecruiterModal();
        setMobileNavOpen(false);
        setState(root, targetState);
        if (targetState === "jobs-mailbox") {
          syncJobsMailboxState("");
          syncJobsMailboxMobileApp("", "feed");
        }

        if (studioMain) {
          studioMain.scrollTo({ top: 0, behavior: "auto" });
        }
      });

      syncStandaloneJobDock();
      syncJobCvRequirements();
      updateResultsCtaCard(root, config.initialAtsState || {});
      if (activeCvSource) {
        syncCvSourceSelects(activeCvSource);
      }
      loadLandingTileRoles(false);
      initRecommendedFeedObserver(root);
      applyNewsletterGroupCardFilters();

      root.addEventListener("change", function (event) {
        var resultSelect = event.target.closest("[data-cv-match-result-select]");
        var cvSourceSelect = event.target.closest("[data-cv-match-cv-source]");
        var recommendedSortSelect = event.target.closest(
          "[data-cv-match-recommended-sort]"
        );
        var recommendedRatingSelect = event.target.closest(
          "[data-cv-match-recommended-rating]"
        );
        var applicationPacksType = event.target.closest(
          "[data-cv-match-application-packs-type]"
        );
        var newsletterGroupLocationFilter = event.target.closest(
          "[data-cv-match-newsletter-group-location-filter]"
        );

        if (cvSourceSelect && root.contains(cvSourceSelect)) {
          loadSavedCvSource(cvSourceSelect.value, cvSourceSelect);
          return;
        }

        if (
          newsletterGroupLocationFilter &&
          root.contains(newsletterGroupLocationFilter)
        ) {
          applyNewsletterGroupCardFilters();
          return;
        }

        if (recommendedSortSelect && root.contains(recommendedSortSelect)) {
          ensureRecommendedFeedState(root).sort =
            recommendedSortSelect.value || "most_relevant";
          renderRecommendedRoles(root, root._cvMatchRecommendedPayload || { items: ensureRecommendedFeedState(root).items.slice() });
          return;
        }

        if (recommendedRatingSelect && root.contains(recommendedRatingSelect)) {
          ensureRecommendedFeedState(root).rating =
            recommendedRatingSelect.value || "all";
          ensureRecommendedFeedState(root).visibleCount =
            ensureRecommendedFeedState(root).revealStep;
          renderRecommendedRoles(root, root._cvMatchRecommendedPayload || { items: ensureRecommendedFeedState(root).items.slice() });
          return;
        }

        if (applicationPacksType && root.contains(applicationPacksType)) {
          syncApplicationPacksSelection();
          return;
        }

        if (!resultSelect || !root.contains(resultSelect)) {
          return;
        }

        syncResultsBulkBar(root);
      });

      root.addEventListener("submit", function (event) {
        var inboxForm = event.target.closest("[data-dashboard-inbox-form]");
        var cancelSubscriptionForm = event.target.closest(
          "[data-dashboard-cancel-subscription-form]"
        );

        if (
          cancelSubscriptionForm &&
          root.contains(cancelSubscriptionForm)
        ) {
          event.preventDefault();

          var cancelFeedback = cancelSubscriptionForm.querySelector(
            "[data-dashboard-cancel-subscription-feedback]"
          );
          var cancelSubmit = cancelSubscriptionForm.querySelector(
            'button[type="submit"]'
          );
          var cancelReason = cancelSubscriptionForm.querySelector(
            'textarea[name="reason"]'
          );
          var cancelFormData = new window.FormData(cancelSubscriptionForm);
          cancelFormData.set("action", "sffc_crm_reddit_cancel_subscription_request");
          cancelFormData.set("nonce", config.accountNonce || "");

          if (cancelFeedback) {
            cancelFeedback.hidden = false;
            cancelFeedback.classList.remove("is-error");
            cancelFeedback.textContent = "Sending your cancellation reason...";
          }

          if (cancelSubmit) {
            cancelSubmit.disabled = true;
          }

          window
            .fetch(config.ajaxUrl || "/wp-admin/admin-ajax.php", {
              method: "POST",
              credentials: "same-origin",
              body: cancelFormData,
            })
            .then(parseAjaxJson)
            .then(function (response) {
              if (!response || response.success !== true) {
                throw new Error(
                  response && response.data && response.data.message
                    ? response.data.message
                    : "Please add a cancellation reason first."
                );
              }

              if (cancelFeedback) {
                cancelFeedback.classList.remove("is-error");
                cancelFeedback.textContent =
                  (response.data && response.data.message) ||
                  "Reason saved. Redirecting to your account...";
              }

              window.setTimeout(function () {
                window.location.href = config.accountUrl || "/account/";
              }, 650);
            })
            .catch(function (error) {
              if (cancelFeedback) {
                cancelFeedback.hidden = false;
                cancelFeedback.classList.add("is-error");
                cancelFeedback.textContent =
                  error && error.message
                    ? error.message
                    : "Please add a cancellation reason first.";
              }
              if (cancelReason) {
                cancelReason.focus();
              }
            })
            .finally(function () {
              if (cancelSubmit) {
                cancelSubmit.disabled = false;
              }
            });

          return;
        }

        if (messagesModal && inboxForm && messagesModal.contains(inboxForm)) {
          event.preventDefault();

          var conversationInput = inboxForm.querySelector(
            'input[name="conversation_id"]'
          );
          var bodyInput = inboxForm.querySelector('textarea[name="body"]');
          var requestBody = new window.FormData();

          if (!conversationInput || !bodyInput || !config.ajaxUrl || !config.accountNonce) {
            return;
          }

          requestBody.append("action", "sffc_crm_reddit_dashboard_send_message");
          requestBody.append("nonce", config.accountNonce);
          requestBody.append("conversation_id", conversationInput.value || "");
          requestBody.append("body", bodyInput.value || "");

          window
            .fetch(config.ajaxUrl, {
              method: "POST",
              credentials: "same-origin",
              body: requestBody,
            })
            .then(parseAjaxJson)
            .then(function (response) {
              if (
                !response ||
                response.success !== true ||
                !response.data ||
                typeof response.data.markup !== "string"
              ) {
                throw new Error(
                  response &&
                    response.data &&
                    response.data.message
                    ? response.data.message
                    : "Unable to send message."
                );
              }

              openMessagesModalWithMarkup(
                response.data.markup,
                response.data.unread_count || 0
              );
            })
            .catch(function () {});
        }
      });

      root.addEventListener("input", function (event) {
        var mailboxSearchInput = event.target.closest(
          "[data-cv-match-mailbox-search-input]"
        );
        var inboxSearch = event.target.closest("[data-dashboard-inbox-search]");
        var composeSearch = event.target.closest("[data-dashboard-compose-search]");
        var newsletterGroupKeywordFilter = event.target.closest(
          "[data-cv-match-newsletter-group-keyword-filter]"
        );

        if (mailboxSearchInput && root.contains(mailboxSearchInput)) {
          queueJobsMailboxSearch(mailboxSearchInput);
          return;
        }

        if (
          newsletterGroupKeywordFilter &&
          root.contains(newsletterGroupKeywordFilter)
        ) {
          applyNewsletterGroupCardFilters();
          return;
        }

        if (messagesModal && inboxSearch && messagesModal.contains(inboxSearch)) {
          var modalCard = inboxSearch.closest(
            ".sffc-cv-match-studio__main-panel-surface--messages"
          );

          if (modalCard) {
            syncInboxModalState(modalCard);
          }
          return;
        }

        if (messagesModal && composeSearch && messagesModal.contains(composeSearch)) {
          var results = messagesModal.querySelector("[data-dashboard-compose-results]");
          var composeQuery = String(composeSearch.value || "").trim();

          if (!results || !config.ajaxUrl || !config.accountNonce) {
            return;
          }

          if (composeSearch._cvMatchComposeTimer) {
            window.clearTimeout(composeSearch._cvMatchComposeTimer);
          }

          composeSearch._cvMatchComposeTimer = window.setTimeout(function () {
            var searchBody = new window.FormData();
            searchBody.append("action", "sffc_crm_reddit_dashboard_message_users");
            searchBody.append("nonce", config.accountNonce);
            searchBody.append("search", composeQuery);

            results.classList.add("is-loading");

            window
              .fetch(config.ajaxUrl, {
                method: "POST",
                credentials: "same-origin",
                body: searchBody,
              })
              .then(parseAjaxJson)
              .then(function (response) {
                if (
                  !response ||
                  response.success !== true ||
                  !response.data ||
                  typeof response.data.markup !== "string"
                ) {
                  throw new Error("Unable to load members.");
                }

                results.innerHTML = response.data.markup;
              })
              .catch(function () {
                results.innerHTML =
                  '<div class="sffc-cv-match-studio__inbox-user-empty">Unable to load members right now.</div>';
              })
              .finally(function () {
                results.classList.remove("is-loading");
              });
          }, 180);
        }
      });

      root.addEventListener("keydown", function (event) {
        var mailboxSearchInput = event.target.closest(
          "[data-cv-match-mailbox-search-input]"
        );
        var notificationItem = event.target.closest(
          "[data-cv-match-notification-item][role='button']"
        );
        var marketsItem = event.target.closest(
          "[data-cv-match-markets-item][role='link']"
        );

        if (
          mailboxSearchInput &&
          root.contains(mailboxSearchInput) &&
          event.key === " "
        ) {
          event.stopPropagation();
          return;
        }

        if (
          marketsItem &&
          root.contains(marketsItem) &&
          (event.key === "Enter" || event.key === " ") &&
          !event.target.closest("a, button, input, select, textarea")
        ) {
          var marketUrl = marketsItem.getAttribute("data-market-url") || "";
          if (marketUrl && marketUrl !== "#") {
            event.preventDefault();
            window.open(marketUrl, "_blank", "noopener");
          }
          return;
        }

        if (
          notificationItem &&
          root.contains(notificationItem) &&
          (event.key === "Enter" || event.key === " ")
        ) {
          event.preventDefault();
          notificationItem.click();
        }
      });

      root.addEventListener("click", function (event) {
        var homeTrigger = event.target.closest("[data-cv-match-home]");
        var mainPanelTrigger = event.target.closest(
          "[data-cv-match-main-panel-trigger]"
        );
        var mainPanelOpen = event.target.closest("[data-cv-match-main-panel-open]");
        var mainPanelClose = event.target.closest(
          "[data-cv-match-main-panel-close]"
        );
        var navTrigger = event.target.closest("[data-cv-match-nav-trigger]");
        var sidebarTabLink = event.target.closest(
          ".sffc-cv-match-studio__sidebar-nav .sffc-cv-match-studio__sidebar-link"
        );
        var newsletterToggleButton = event.target.closest(
          "[data-cv-match-newsletter-toggle]"
        );
        var newsletterGroupFilterButton = event.target.closest(
          "[data-cv-match-newsletter-group-filter]"
        );
        var newsletterGroupClearFiltersButton = event.target.closest(
          "[data-cv-match-newsletter-group-clear-filters]"
        );
        var newsletterGroupLink = event.target.closest(
          "[data-cv-match-newsletter-group-open]"
        );
        var newsletterGroupSubscribeTrigger = event.target.closest(
          "[data-cv-match-list-subscribe-trigger]"
        );
        var notificationItem = event.target.closest(
          "[data-cv-match-notification-item]"
        );
        var messageOpenButton = event.target.closest(
          "[data-cv-match-message-open]"
        );
        var inboxTabButton = event.target.closest("[data-dashboard-inbox-tab]");
        var inboxThreadButton = event.target.closest("[data-dashboard-inbox-open]");
        var mailboxOpenButton = event.target.closest("[data-cv-match-mailbox-open]");
        var mailboxPinButton = event.target.closest("[data-cv-match-mailbox-pin]");
        var mailboxFilterButton = event.target.closest(
          "[data-cv-match-mailbox-filter]"
        );
        var mailboxActionButton = event.target.closest(
          "[data-cv-match-mailbox-action]"
        );
        var mailboxMenuToggle = event.target.closest(
          "[data-cv-match-mailbox-menu-toggle]"
        );
        var mailboxMenuAction = event.target.closest(
          "[data-cv-match-mailbox-menu-action]"
        );
        var mailboxTrackerToggle = event.target.closest(
          "[data-cv-match-mailbox-tracker-toggle]"
        );
        var mailboxTrackerStage = event.target.closest(
          "[data-cv-match-mailbox-track-stage]"
        );
        var mailboxResetHidden = event.target.closest(
          "[data-cv-match-mailbox-reset-hidden]"
        );
        var mailboxLoadMore = event.target.closest(
          "[data-cv-match-mailbox-loadmore]"
        );
        var mailboxClearSearchButton = event.target.closest(
          "[data-cv-match-mailbox-clear-search]"
        );
        var mailboxStoryButton = event.target.closest(
          "[data-cv-match-mailbox-story-open]"
        );
        var mailboxMobileAppOpenButton = event.target.closest(
          "[data-cv-match-mailbox-mobileapp-open]"
        );
        var mailboxMobileAppBenefit = event.target.closest(
          "[data-cv-match-mailbox-mobileapp-benefit]"
        );
        var mailboxMobileAppBackButton = event.target.closest(
          "[data-cv-match-mailbox-mobileapp-back]"
        );
        var mailboxMobileAppSmartApplyButton = event.target.closest(
          "[data-cv-match-mailbox-mobileapp-smart-apply]"
        );
        var mailboxMobileAppCompareButton = event.target.closest(
          "[data-cv-match-mailbox-mobileapp-compare]"
        );
        var mailboxMobileAppNotInterestedButton = event.target.closest(
          "[data-cv-match-mailbox-mobileapp-not-interested]"
        );
        var mailboxMobileAppViewMatchesButton = event.target.closest(
          "[data-cv-match-mailbox-mobileapp-view-matches]"
        );
        var mailboxMobileAppRefreshButton = event.target.closest(
          "[data-cv-match-mailbox-mobileapp-refresh]"
        );
        var mailboxMaterialsOpenButton = event.target.closest(
          "[data-cv-match-mailbox-materials-open]"
        );
        var mailboxContactToggle = event.target.closest(
          "[data-cv-match-mailbox-contact-toggle]"
        );
        var mailboxContactClose = event.target.closest(
          "[data-cv-match-mailbox-contact-close]"
        );
        var composeToggle = event.target.closest("[data-dashboard-compose-toggle]");
        var composeUser = event.target.closest("[data-dashboard-compose-user]");
        var openRoleLink = event.target.closest(
          ".sffc-cv-match-studio__open-button"
        );
        var quickViewButton = event.target.closest("[data-open-role-quick-view]");
        var recentPinButton = event.target.closest("[data-recent-pin]");
        var explainButton = event.target.closest("[data-result-explain]");
        var smartApplyButton = event.target.closest("[data-smart-apply-open]");
        var smartApplyPackButton = event.target.closest(
          "[data-smart-apply-pack-open]"
        );
        var smartApplyClose = event.target.closest(
          "[data-cv-match-smart-apply-close]"
        );
        var recruiterOpenButton = event.target.closest(
          "[data-cv-match-recruiter-open]"
        );
        var recruiterDiscoveryDismissButton = event.target.closest(
          "[data-cv-match-recruiter-dismiss]"
        );
        var recruiterCloseButton = event.target.closest(
          "[data-cv-match-recruiter-close]"
        );
        var jobBackButton = event.target.closest("[data-cv-match-job-back]");
        var materialsOpenButton = event.target.closest(
          "[data-cv-match-materials-open]"
        );
        var materialsCloseButton = event.target.closest(
          "[data-cv-match-materials-close]"
        );
        var supportOpenButton = event.target.closest(
          "[data-cv-match-support-open]"
        );
        var supportCloseButton = event.target.closest(
          "[data-cv-match-support-close]"
        );
        var customListOpenButton = event.target.closest(
          "[data-cv-match-custom-list-open]"
        );
        var dailyScanOpenButton = event.target.closest(
          "[data-cv-match-daily-scan-open]"
        );
        var emailListOpenButton = event.target.closest(
          "[data-cv-match-email-list-open]"
        );
        var saveListButton = event.target.closest("[data-cv-match-save-list]");
        var emailListCloseButton = event.target.closest(
          "[data-cv-match-email-list-close]"
        );
        var cvOnboardingCloseButton = event.target.closest(
          "[data-cv-match-cv-onboarding-close]"
        );
        var welcomeTourButton = event.target.closest(
          "[data-cv-match-welcome-tour]"
        );
        var welcomePlanSelectButton = event.target.closest(
          "[data-cv-match-welcome-plan-select]"
        );
        var welcomeDismissButton = event.target.closest(
          "[data-cv-match-welcome-dismiss]"
        );
        var tourNextButton = event.target.closest("[data-cv-match-tour-next]");
        var tourSkipButton = event.target.closest("[data-cv-match-tour-skip]");
        var materialsTab = event.target.closest("[data-cv-match-material-tab]");
        var applicationPacksCard = event.target.closest(
          "[data-cv-match-application-packs-card]"
        );
        var applicationPacksSubmit = event.target.closest(
          "[data-cv-match-application-packs-submit]"
        );
        var marketReportButton = event.target.closest(
          "[data-cv-match-market-report-request]"
        );
        var marketsItem = event.target.closest("[data-cv-match-markets-item]");
        var marketsFilterButton = event.target.closest(
          "[data-cv-match-markets-filter]"
        );
        var careerReportRefreshButton = event.target.closest(
          "[data-cv-match-career-report-refresh]"
        );
        var cancelSubscriptionOpen = event.target.closest(
          "[data-dashboard-cancel-subscription-open]"
        );
        var dashboardModalClose = event.target.closest(
          "[data-dashboard-modal-close]"
        );
        var membershipGateAction = event.target.closest(
          [
            "a",
            "button",
            "[role='button']",
            "[data-cv-match-markets-item]",
            "[data-cv-match-newsletter-group-role]",
            "[data-cv-match-newsletter-group-open]",
            "[data-cv-match-notification-item]",
            "[data-dashboard-nav]",
          ].join(", ")
        );
        var joinForm = event.target.closest(
          ".sffc-cv-match-studio__job-post-joinform"
        );
        var footerSidebarLink = event.target.closest(
          ".sffc-cv-match-studio__sidebar-link.is-footer"
        );
        var communityAccountToggle = event.target.closest(
          "[data-cv-match-community-account-toggle]"
        );
        var communityAccountShell = event.target.closest(
          "[data-cv-match-community-account]"
        );

        if (communityAccountToggle && root.contains(communityAccountToggle)) {
          event.preventDefault();
          event.stopPropagation();
          toggleCommunityAccountMenu(communityAccountToggle);
          return;
        }

        if (!communityAccountShell) {
          closeCommunityAccountMenu();
        }

        if (
          !config.loggedIn &&
          membershipGateAction &&
          root.contains(membershipGateAction) &&
          !joinForm &&
          !footerSidebarLink
        ) {
          event.preventDefault();
          event.stopPropagation();
          redirectToMembership();
          return;
        }

        if (customListOpenButton && root.contains(customListOpenButton)) {
          event.preventDefault();
          event.stopPropagation();
          setMobileNavOpen(false);
          if (customListDropdown && !customListDropdown.hidden) {
            closeCustomListModal();
          } else {
            openCustomListModal();
          }
          return;
        }

        if (dailyScanOpenButton && root.contains(dailyScanOpenButton)) {
          event.preventDefault();
          event.stopPropagation();
          setMobileNavOpen(false);
          if (!config.loggedIn) {
            redirectToMembership();
            return;
          }
          if (dailyScanDropdown && !dailyScanDropdown.hidden) {
            closeDailyScanDropdown();
          } else {
            openDailyScanDropdown();
          }
          return;
        }

        if (emailListOpenButton && root.contains(emailListOpenButton)) {
          event.preventDefault();
          event.stopPropagation();
          setMobileNavOpen(false);
          openEmailListModal(emailListOpenButton);
          return;
        }

        if (saveListButton && root.contains(saveListButton)) {
          event.preventDefault();
          event.stopPropagation();

          if (!config.loggedIn || !config.hasPremiumAccess) {
            redirectToMembership();
            return;
          }

          saveNewsletterGroupList(saveListButton);
          return;
        }

        if (emailListCloseButton && root.contains(emailListCloseButton)) {
          event.preventDefault();
          event.stopPropagation();
          closeEmailListModal();
          return;
        }

        if (
          recruiterDiscoveryDismissButton &&
          root.contains(recruiterDiscoveryDismissButton)
        ) {
          event.preventDefault();
          event.stopPropagation();

          var dismissKey =
            recruiterDiscoveryDismissButton.getAttribute(
              "data-cv-match-recruiter-dismiss"
            ) || "";
          var recruiterDiscoveryRow =
            recruiterDiscoveryDismissButton.closest(
              "[data-cv-match-recruiter-discovery-row]"
            );

          if (recruiterDiscoveryRow) {
            recruiterDiscoveryRow.classList.add("is-dismissing");
            window.setTimeout(function () {
              if (recruiterDiscoveryRow && recruiterDiscoveryRow.parentNode) {
                recruiterDiscoveryRow.parentNode.removeChild(recruiterDiscoveryRow);
              }
            }, 180);
          }

          if (config.loggedIn && config.ajaxUrl && dismissKey) {
            var dismissBody = new URLSearchParams();
            dismissBody.append("action", "sffc_cv_match_dismiss_recruiter_discovery");
            dismissBody.append("nonce", config.crmNonce || config.nonce || "");
            dismissBody.append("dismiss_key", dismissKey);

            window
              .fetch(config.ajaxUrl, {
                method: "POST",
                credentials: "same-origin",
                headers: {
                  "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
                },
                body: dismissBody.toString(),
              })
              .catch(function () {});
          }

          return;
        }
        var networkingOpenButton = event.target.closest(
          "[data-cv-match-networking-open]"
        );
        var reportOpenButton = event.target.closest(
          "[data-cv-match-report-open]"
        );
        var bulkClearButton = event.target.closest(
          "[data-cv-match-results-clear]"
        );
        var bulkOutreachButton = event.target.closest(
          "[data-cv-match-results-outreach]"
        );
        var bulkQuickViewButton = event.target.closest(
          "[data-cv-match-results-quick-view]"
        );
        var tilesNavButton = event.target.closest("[data-cv-match-tiles-nav]");

        if (marketReportButton && root.contains(marketReportButton)) {
          event.preventDefault();
          requestCareerReportMarketReport(marketReportButton);
          return;
        }

        if (marketsFilterButton && root.contains(marketsFilterButton)) {
          event.preventDefault();
          var marketsState = marketsFilterButton.closest(
            ".sffc-cv-match-studio__markets-state"
          );
          var activeFilter =
            marketsFilterButton.getAttribute("data-cv-match-markets-filter") ||
            "all";

          if (marketsState) {
            $all(marketsState, "[data-cv-match-markets-filter]").forEach(
              function (button) {
                var isCurrent =
                  (button.getAttribute("data-cv-match-markets-filter") ||
                    "all") === activeFilter;
                button.classList.toggle("is-active", isCurrent);
                button.setAttribute("aria-pressed", isCurrent ? "true" : "false");
              }
            );

            $all(marketsState, "[data-cv-match-markets-item]").forEach(function (
              item
            ) {
              var itemType = item.getAttribute("data-market-type") || "";
              item.hidden = activeFilter !== "all" && itemType !== activeFilter;
            });
          }
          return;
        }

        if (
          marketsItem &&
          root.contains(marketsItem) &&
          !event.target.closest("a, button, input, select, textarea")
        ) {
          var marketUrl = marketsItem.getAttribute("data-market-url") || "";
          if (marketUrl && marketUrl !== "#") {
            event.preventDefault();
            window.open(marketUrl, "_blank", "noopener");
          }
          return;
        }

        if (mailboxClearSearchButton && root.contains(mailboxClearSearchButton)) {
          event.preventDefault();
          loadJobsMailboxSearchResults("", { focusInput: true });
          return;
        }

        if (careerReportRefreshButton && root.contains(careerReportRefreshButton)) {
          event.preventDefault();
          loadCareerReport(true);
          return;
        }

        if (cancelSubscriptionOpen && root.contains(cancelSubscriptionOpen)) {
          event.preventDefault();
          var cancelModal = root.querySelector(
            '[data-dashboard-modal="cancel-subscription"]'
          );
          if (cancelModal) {
            cancelModal.hidden = false;
            cancelModal.setAttribute("aria-hidden", "false");
            var cancelTextarea = cancelModal.querySelector('textarea[name="reason"]');
            if (cancelTextarea) {
              cancelTextarea.focus();
            }
          } else {
            window.location.href = config.accountUrl || "/account/";
          }
          return;
        }

        if (dashboardModalClose && root.contains(dashboardModalClose)) {
          var dashboardModal = dashboardModalClose.closest("[data-dashboard-modal]");
          if (dashboardModal) {
            event.preventDefault();
            dashboardModal.hidden = true;
            dashboardModal.setAttribute("aria-hidden", "true");
            return;
          }
        }
        var mobileSearchTrigger = event.target.closest(
          "[data-cv-match-mobile-search-toggle]"
        );
        var mobileSearchDismiss = event.target.closest(
          "[data-cv-match-mobile-search-close]"
        );
        var discoveryNavLink = event.target.closest(
          ".sffc-cv-match-studio__discovery-state [data-dashboard-nav]"
        );

        if (
          root.classList.contains("is-mobile-command-open") &&
          mainUtility &&
          !mainUtility.contains(event.target)
        ) {
          setMobileCommandOpen(false);
        }

        if (tilesNavButton && root.contains(tilesNavButton)) {
          event.preventDefault();
          scrollLandingTiles(
            tilesNavButton.getAttribute("data-cv-match-tiles-nav") || "next"
          );
          return;
        }

        if (mobileSearchTrigger && root.contains(mobileSearchTrigger)) {
          event.preventDefault();
          setMobileCommandOpen(
            !root.classList.contains("is-mobile-command-open")
          );
          return;
        }

        if (mobileSearchDismiss && root.contains(mobileSearchDismiss)) {
          event.preventDefault();
          setMobileCommandOpen(false);
          return;
        }

        if (
          homeTrigger &&
          root.contains(homeTrigger) &&
          !event.target.closest("[data-cv-match-sidebar-toggle]")
        ) {
          event.preventDefault();
          resetView();
          setSidebarCollapsed(false);
          return;
        }

        if (mainPanelOpen && root.contains(mainPanelOpen)) {
          event.preventDefault();
          openMainPanel(
            mainPanelOpen.getAttribute("data-cv-match-main-panel-open") || "",
            mainPanelOpen
          );
          return;
        }

        if (mainPanelTrigger && root.contains(mainPanelTrigger)) {
          event.preventDefault();
          toggleMainPanel(
            mainPanelTrigger.getAttribute("data-cv-match-main-panel-trigger") ||
              "",
            mainPanelTrigger
          );
          return;
        }

        if (mainPanelClose && root.contains(mainPanelClose)) {
          event.preventDefault();
          closeMainPanels();
          return;
        }

        if (
          !event.target.closest("[data-cv-match-main-panel]") &&
          !event.target.closest("[data-cv-match-main-panel-trigger]")
        ) {
          closeMainPanels();
        }

        if (
          !event.target.closest("[data-cv-match-mailbox-menu]") &&
          !event.target.closest("[data-cv-match-mailbox-menu-toggle]") &&
          !event.target.closest("[data-cv-match-mailbox-tracker-menu]") &&
          !event.target.closest("[data-cv-match-mailbox-tracker-toggle]")
        ) {
          closeJobsMailboxMenus(root);
        }

        if (
          !event.target.closest("[data-cv-match-inline-dropdown-wrap]")
        ) {
          closeNewsletterGroupSubscribeDropdowns();
          closeCustomListModal();
          closeDailyScanDropdown();
        }

        if (
          newsletterGroupSubscribeTrigger &&
          root.contains(newsletterGroupSubscribeTrigger)
        ) {
          event.preventDefault();
          event.stopPropagation();
          toggleNewsletterGroupSubscribeDropdown(
            newsletterGroupSubscribeTrigger
          );
          return;
        }

        if (
          newsletterGroupFilterButton &&
          root.contains(newsletterGroupFilterButton)
        ) {
          var newsletterGroupFilter =
            newsletterGroupFilterButton.getAttribute(
              "data-cv-match-newsletter-group-filter"
            ) || "jobs";
          event.preventDefault();
          setNewsletterGroupFilter(newsletterGroupFilter);
          return;
        }

        if (
          newsletterGroupClearFiltersButton &&
          root.contains(newsletterGroupClearFiltersButton)
        ) {
          event.preventDefault();
          clearNewsletterGroupCardFilters();
          return;
        }

        if (newsletterGroupLink && root.contains(newsletterGroupLink)) {
          var newsletterGroupSlug =
            newsletterGroupLink.getAttribute("data-cv-match-newsletter-group-open") ||
            "";
          var newsletterGroupLabel =
            newsletterGroupLink.getAttribute("data-cv-match-newsletter-group-label") ||
            "";

          event.preventDefault();
          closeMaterialsModal();
          closeSmartApply();
          closeRecruiterModal();
          setMobileNavOpen(false);
          setState(root, "jobs-mailbox");
          setPendingJobsMailboxNewsletterGroup(
            newsletterGroupSlug,
            newsletterGroupLabel
          );
          ensureJobsMailboxLoaded(false).finally(function () {
            applyJobsMailboxNewsletterGroupFilter(
              newsletterGroupSlug,
              newsletterGroupLabel
            );
          });
          return;
        }

        if (welcomeTourButton && root.contains(welcomeTourButton)) {
          event.preventDefault();
          openTour();
          return;
        }

        if (cvOnboardingCloseButton && root.contains(cvOnboardingCloseButton)) {
          event.preventDefault();
          closeCvOnboardingModal();
          return;
        }

        if (welcomeDismissButton && root.contains(welcomeDismissButton)) {
          event.preventDefault();
          closeWelcomeModal();
          return;
        }

        if (welcomePlanSelectButton && root.contains(welcomePlanSelectButton)) {
          event.preventDefault();
          openWelcomeCheckout(
            welcomePlanSelectButton.getAttribute("data-cv-match-welcome-plan-select") || "",
            welcomePlanSelectButton.getAttribute("data-cv-match-welcome-plan-url") || welcomePlanSelectButton.getAttribute("href") || ""
          );
          return;
        }

        if (welcomeProceedButton && root.contains(welcomeProceedButton) && event.target.closest("[data-cv-match-welcome-proceed]")) {
          event.preventDefault();
          openTour();
          return;
        }

        if (tourNextButton && root.contains(tourNextButton)) {
          event.preventDefault();
          renderTourStep(onboardingState.index + 1);
          return;
        }

        if (tourSkipButton && root.contains(tourSkipButton)) {
          event.preventDefault();
          closeTour();
          return;
        }

        if (notificationItem && root.contains(notificationItem)) {
          var notificationAction =
            notificationItem.getAttribute("data-cv-match-notification-action") ||
            "";
          var notificationId =
            notificationItem.getAttribute("data-cv-match-notification-id") || "";
          var unreadBeforeClick = notificationItem.classList.contains("is-unread");

          event.preventDefault();

          Promise.resolve()
            .then(function () {
              if (unreadBeforeClick) {
                return markPreviewNotificationRead(notificationId, notificationItem);
              }
              return null;
            })
            .then(function () {
              if (unreadBeforeClick) {
                var notificationsBadge = $(
                  getMainUtilityScope(notificationItem),
                  '[data-cv-match-main-panel-trigger="notifications"] .sffc-cv-match-studio__utility-badge'
                );
                updateUtilityBadge(
                  "notifications",
                  Math.max(
                    0,
                    (parseInt(
                      notificationsBadge ? notificationsBadge.textContent : "0",
                      10
                    ) || 0) - 1
                  )
                );
              }

              closeMainPanels();

              if (!notificationAction) {
                return;
              }

              if (notificationAction.charAt(0) === "#") {
                var notificationState = notificationAction.slice(1);
                if (notificationState === "messages") {
                  fetchMessagesModal("").catch(function () {});
                  return;
                }

                setState(root, notificationState);
                return;
              }

              window.open(notificationAction, "_blank", "noopener");
            })
            .catch(function () {});
          return;
        }

        if (messageOpenButton && root.contains(messageOpenButton)) {
          event.preventDefault();
          fetchMessagesModal(
            messageOpenButton.getAttribute("data-cv-match-message-open") || ""
          ).catch(function () {});
          return;
        }

        if (smartApplyPackButton && root.contains(smartApplyPackButton)) {
          event.preventDefault();
          openSmartApplyMaterials(
            smartApplyPackButton.getAttribute("data-smart-apply-pack-open") || ""
          );
          return;
        }

        if (applicationPacksSubmit && root.contains(applicationPacksSubmit)) {
          event.preventDefault();
          submitApplicationPacksRequest();
          return;
        }

        if (
          applicationPacksCard &&
          root.contains(applicationPacksCard) &&
          !event.target.closest("[data-cv-match-application-packs-type]")
        ) {
          var applicationPacksInput = $(
            applicationPacksCard,
            "[data-cv-match-application-packs-type]"
          );
          event.preventDefault();
          if (applicationPacksInput) {
            applicationPacksInput.checked = !applicationPacksInput.checked;
            syncApplicationPacksSelection();
          }
          return;
        }

        if (messagesModal && messagesModal.contains(event.target) && inboxTabButton) {
          var inboxModalCard = inboxTabButton.closest(
            ".sffc-cv-match-studio__main-panel-surface--messages"
          );
          var nextTab = String(
            inboxTabButton.getAttribute("data-dashboard-inbox-tab") || "inbox"
          );
          event.preventDefault();
          if (inboxModalCard) {
            $all(inboxModalCard, "[data-dashboard-inbox-tab]").forEach(function (button) {
              button.classList.toggle(
                "is-active",
                button === inboxTabButton
              );
            });

            syncInboxModalState(inboxModalCard);
          }
          return;
        }

        if (messagesModal && messagesModal.contains(event.target) && inboxThreadButton) {
          event.preventDefault();
          fetchMessagesModal(
            inboxThreadButton.getAttribute("data-dashboard-inbox-open") || ""
          ).catch(function () {});
          return;
        }

        if (messagesModal && messagesModal.contains(event.target) && composeToggle) {
          var composePanel = composeToggle
            .closest(".sffc-cv-match-studio__main-panel-surface--messages")
            .querySelector("[data-dashboard-compose-panel]");
          event.preventDefault();
          if (composePanel) {
            composePanel.hidden = !composePanel.hidden;
            composePanel.classList.toggle("is-open", !composePanel.hidden);
          }
          return;
        }

        if (messagesModal && messagesModal.contains(event.target) && composeUser) {
          event.preventDefault();
          startMessageConversation(
            composeUser.getAttribute("data-dashboard-compose-user") || ""
          ).catch(function () {});
          return;
        }

        if (
          messagesModal &&
          !messagesModal.hidden &&
          (event.target.closest("[data-cv-match-messages-close]") ||
            event.target.closest(".sffc-cv-match-studio__messages-modal-backdrop") ||
            event.target.closest("[data-dashboard-modal-close]"))
        ) {
          event.preventDefault();
          closeMessagesModal();
          return;
        }

        if (mailboxPinButton && root.contains(mailboxPinButton)) {
          var mailboxRow = mailboxPinButton.closest("[data-cv-match-mailbox-row]");
          var pinnedMailboxKey =
            mailboxPinButton.getAttribute("data-cv-match-mailbox-pin") ||
            (mailboxRow
              ? mailboxRow.getAttribute("data-cv-match-mailbox-row") || ""
              : "");
          var refreshPinnedMailboxUi = function () {
            var activeFilterButton = $(
              root,
              "[data-cv-match-mailbox-filter].is-active"
            );
            var activeFilterName = activeFilterButton
              ? activeFilterButton.getAttribute("data-cv-match-mailbox-filter") || "all"
              : "";

            syncJobsMailboxState(pinnedMailboxKey);
            if (activeFilterName) {
              applyJobsMailboxFilter(activeFilterName);
            }
          };

          event.preventDefault();
          event.stopPropagation();

          toggleJobsMailboxPin(pinnedMailboxKey, mailboxPinButton)
            .then(function () {
              refreshPinnedMailboxUi();
            })
            .catch(function (error) {
              refreshPinnedMailboxUi();
              setFeedback(
                root,
                error && error.message
                  ? error.message
                  : "Unable to update pinned role.",
                true
              );
            });

          refreshPinnedMailboxUi();
          return;
        }

        if (mailboxOpenButton && root.contains(mailboxOpenButton)) {
          var desktopMailboxKey =
            mailboxOpenButton.getAttribute("data-cv-match-mailbox-open") || "";
          event.preventDefault();
          if (!config.loggedIn) {
            redirectToMembership();
            return;
          }
          incrementJobsMailboxClick(
            getJobsMailboxClickKey(mailboxOpenButton) || desktopMailboxKey
          );
          syncJobsMailboxState(desktopMailboxKey);
          syncJobsMailboxMobileApp(desktopMailboxKey, "feed");
          return;
        }

        if (mailboxStoryButton && root.contains(mailboxStoryButton)) {
          var desktopStoryKey =
            mailboxStoryButton.getAttribute("data-cv-match-mailbox-story-open") || "";
          event.preventDefault();
          incrementJobsMailboxClick(
            getJobsMailboxClickKey(mailboxStoryButton) || desktopStoryKey
          );
          syncJobsMailboxState(desktopStoryKey);
          syncJobsMailboxMobileApp(desktopStoryKey, "feed");
          return;
        }

        if (mailboxFilterButton && root.contains(mailboxFilterButton)) {
          event.preventDefault();
          closeJobsMailboxMenus(root);
          applyJobsMailboxFilter(
            mailboxFilterButton.getAttribute("data-cv-match-mailbox-filter") || "all"
          );
          return;
        }

        if (mailboxActionButton && root.contains(mailboxActionButton)) {
          event.preventDefault();
          performJobsMailboxAction(
            mailboxActionButton.getAttribute("data-cv-match-mailbox-action") || "",
            mailboxActionButton
          );
          return;
        }

        if (mailboxMenuToggle && root.contains(mailboxMenuToggle)) {
          event.preventDefault();
          event.stopPropagation();
          toggleJobsMailboxMenu(mailboxMenuToggle);
          return;
        }

        if (mailboxTrackerToggle && root.contains(mailboxTrackerToggle)) {
          event.preventDefault();
          event.stopPropagation();
          toggleJobsMailboxTrackerMenu(mailboxTrackerToggle);
          return;
        }

        if (mailboxMenuAction && root.contains(mailboxMenuAction)) {
          event.preventDefault();
          event.stopPropagation();
          handleJobsMailboxMenuAction(
            mailboxMenuAction.getAttribute("data-cv-match-mailbox-menu-action") ||
              "",
            mailboxMenuAction
          );
          return;
        }

        if (mailboxTrackerStage && root.contains(mailboxTrackerStage)) {
          var activePaneForTracker =
            mailboxTrackerStage.closest("[data-cv-match-mailbox-mobileapp-detail]") ||
            mailboxTrackerStage.closest("[data-cv-match-mailbox-pane]") ||
            getJobsMailboxActivePane();
          var trackerContext = getJobsMailboxContextFromNode(activePaneForTracker);
          var trackerStage = String(
            mailboxTrackerStage.getAttribute("data-cv-match-mailbox-track-stage") ||
              ""
          );
          event.preventDefault();
          event.stopPropagation();
          if (!trackerContext || !trackerStage || !activePaneForTracker) {
            return;
          }
          closeJobsMailboxMenus(root);
          saveTrackerStageForContext(trackerContext, trackerStage, {
            paneNode: activePaneForTracker,
            onPending: function () {
              mailboxTrackerStage.disabled = true;
            },
            onSuccess: function (stageMeta) {
              setFeedback(
                root,
                "Added to Tracker as " + (stageMeta.label || "Tracked") + ".",
                false
              );
            },
            onError: function (error) {
              setFeedback(
                root,
                error && error.message
                  ? error.message
                  : "Unable to save tracking status.",
                true
              );
            },
          }).finally(function () {
            mailboxTrackerStage.disabled = false;
          });
          return;
        }

        if (
          mailboxMobileAppNotInterestedButton &&
          root.contains(mailboxMobileAppNotInterestedButton)
        ) {
          event.preventDefault();
          var mobileNotInterestedKey = String(
            mailboxMobileAppNotInterestedButton.getAttribute(
              "data-cv-match-mailbox-mobileapp-not-interested"
            ) || ""
          ).trim();
          var mobileNotInterestedContext = getJobsMailboxContextByKey(
            mobileNotInterestedKey
          );

          if (!mobileNotInterestedKey) {
            return;
          }

          addJobsMailboxHidden(mobileNotInterestedKey);
          syncJobsMailboxState("");
          syncJobsMailboxMobileApp("", "feed");
          setFeedback(
            root,
            ((mobileNotInterestedContext &&
              mobileNotInterestedContext.roleTitle) ||
              "This role") + " removed from your mailbox.",
            false
          );
          return;
        }

        if (
          mailboxMobileAppViewMatchesButton &&
          root.contains(mailboxMobileAppViewMatchesButton)
        ) {
          event.preventDefault();
          var mobileInboxSection = $(
            root,
            ".sffc-cv-match-studio__jobs-mailbox-mobileapp-inbox"
          );
          if (mobileInboxSection) {
            mobileInboxSection.scrollIntoView({
              behavior: "smooth",
              block: "start",
            });
          }
          return;
        }

        if (
          mailboxMobileAppRefreshButton &&
          root.contains(mailboxMobileAppRefreshButton)
        ) {
          event.preventDefault();
          if (!config.loggedIn) {
            redirectToMembership();
            return;
          }

          var mobileRefreshKey = refreshJobsMailboxMobileAppFeed();
          var mobileRefreshMailbox = $(root, "[data-cv-match-jobs-mailbox]");

          if (mobileRefreshMailbox) {
            refreshDesktopJobsMailboxPrimary(mobileRefreshMailbox);
            if (mobileRefreshKey) {
              syncJobsMailboxState(mobileRefreshKey);
            }
          }

          setFeedback(
            root,
            mobileRefreshKey
              ? "Top private equity roles refreshed."
              : "No new roles to show yet.",
            !mobileRefreshKey
          );
          return;
        }

        if (mailboxResetHidden && root.contains(mailboxResetHidden)) {
          event.preventDefault();
          closeJobsMailboxMenus(root);
          clearJobsMailboxHidden();
          root._cvMatchJobsMailboxMobileAppExpanded = false;
          root._cvMatchJobsMailboxExpanded = false;
          applyJobsMailboxFilter("all");
          syncJobsMailboxMobileApp("", "feed");
          setFeedback(root, "Mailbox refreshed and filters cleared.", false);
          return;
        }

        if (mailboxLoadMore && root.contains(mailboxLoadMore)) {
          event.preventDefault();
          if (!config.loggedIn) {
            redirectToMembership();
            return;
          }
          var desktopMailbox = $(root, "[data-cv-match-jobs-mailbox]");
          var nextMailboxKey = desktopMailbox
            ? refreshDesktopJobsMailboxPrimary(desktopMailbox)
            : "";
          if (nextMailboxKey) {
            desktopMailbox.classList.remove("has-scrolled-down");
            desktopMailbox.classList.remove("is-scrolling-up");
            desktopMailbox._cvMatchRefreshPromptHasScrolledDown = false;
            desktopMailbox._cvMatchRefreshPromptIsScrollingUp = false;
            desktopMailbox._cvMatchRefreshPromptVisible = false;
            desktopMailbox._cvMatchRefreshPromptMaxScrollTop = 0;
            desktopMailbox._cvMatchRefreshPromptLastScrollTop = 0;
            syncJobsMailboxState(nextMailboxKey);
            setFeedback(root, "Mailbox refreshed with new roles.", false);
          }
          return;
        }

        if (mailboxMobileAppOpenButton && root.contains(mailboxMobileAppOpenButton)) {
          event.preventDefault();
          if (!config.loggedIn || !config.hasPremiumAccess) {
            redirectToMembership();
            return;
          }
          var mobileAppKey =
            mailboxMobileAppOpenButton.getAttribute(
              "data-cv-match-mailbox-mobileapp-open"
            ) || "";
          incrementJobsMailboxClick(
            getJobsMailboxClickKey(mailboxMobileAppOpenButton) || mobileAppKey
          );
          syncJobsMailboxState(mobileAppKey);
          syncJobsMailboxMobileApp(mobileAppKey, "detail");
          return;
        }

        if (mailboxMobileAppBenefit && root.contains(mailboxMobileAppBenefit)) {
          if (!config.loggedIn || !config.hasPremiumAccess) {
            event.preventDefault();
            redirectToMembership();
            return;
          }
        }

        if (mailboxMobileAppBackButton && root.contains(mailboxMobileAppBackButton)) {
          event.preventDefault();
          syncJobsMailboxMobileApp("", "feed");
          return;
        }

        if (
          event.target.closest("[data-cv-match-mailbox-mobileapp-loadmore]") &&
          root.contains(event.target.closest("[data-cv-match-mailbox-mobileapp-loadmore]"))
        ) {
          event.preventDefault();
          root._cvMatchJobsMailboxMobileAppExpanded = true;
          syncJobsMailboxMobileApp("", "feed");
          return;
        }

        if (
          mailboxMobileAppSmartApplyButton &&
          root.contains(mailboxMobileAppSmartApplyButton)
        ) {
          event.preventDefault();
          if (!config.loggedIn) {
            redirectToMembership();
            return;
          }
          var mobileAppSmartKey =
            mailboxMobileAppSmartApplyButton.getAttribute(
              "data-cv-match-mailbox-mobileapp-smart-apply"
            ) || "";
          var mobileAppSmartItem = getJobsMailboxSmartApplyItemByKey(
            mobileAppSmartKey
          );
          if (mobileAppSmartItem) {
            syncJobsMailboxState(mobileAppSmartKey);
            openSmartApply(mobileAppSmartItem);
          }
          return;
        }

        if (
          mailboxMobileAppCompareButton &&
          root.contains(mailboxMobileAppCompareButton)
        ) {
          event.preventDefault();
          var mobileAppCompareKey =
            mailboxMobileAppCompareButton.getAttribute(
              "data-cv-match-mailbox-mobileapp-compare"
            ) || "";
          var mobileAppCompareContext = getJobsMailboxContextByKey(
            mobileAppCompareKey
          );
          if (mobileAppCompareContext) {
            syncJobsMailboxState(mobileAppCompareKey);
            openJobsMailboxContextCvReport(mobileAppCompareContext);
          }
          return;
        }

        if (mailboxMaterialsOpenButton && root.contains(mailboxMaterialsOpenButton)) {
          event.preventDefault();
          openGeneratedMaterials(
            mailboxMaterialsOpenButton.getAttribute(
              "data-cv-match-mailbox-material-type"
            ) || "",
            {
              jobsPostId: Number(
                mailboxMaterialsOpenButton.getAttribute(
                  "data-cv-match-mailbox-jobs-post-id"
                ) || 0
              ),
              wpPostId: Number(
                mailboxMaterialsOpenButton.getAttribute(
                  "data-cv-match-mailbox-wp-post-id"
                ) || 0
              ),
              crmPostId: Number(
                mailboxMaterialsOpenButton.getAttribute(
                  "data-cv-match-mailbox-crm-post-id"
                ) || 0
              ),
              roleTitle:
                mailboxMaterialsOpenButton.getAttribute(
                  "data-cv-match-mailbox-role-title"
                ) || "",
              company:
                mailboxMaterialsOpenButton.getAttribute(
                  "data-cv-match-mailbox-company"
                ) || "",
              jdText: "",
            }
          );
          return;
        }

        if (mailboxContactToggle && root.contains(mailboxContactToggle)) {
          event.preventDefault();
          event.stopPropagation();
          toggleJobsMailboxContactModal(mailboxContactToggle);
          return;
        }

        if (mailboxContactClose && root.contains(mailboxContactClose)) {
          event.preventDefault();
          event.stopPropagation();
          closeJobsMailboxContactModals(root);
          return;
        }

        if (
          !event.target.closest("[data-cv-match-mailbox-contact-modal]") &&
          !event.target.closest("[data-cv-match-mailbox-contact-toggle]")
        ) {
          closeJobsMailboxContactModals(root);
        }

        if (sidebarTabLink && root.contains(sidebarTabLink) && !config.loggedIn) {
          event.preventDefault();
          redirectToMembership();
          return;
        }

        if (newsletterToggleButton && root.contains(newsletterToggleButton)) {
          event.preventDefault();
          if (!config.loggedIn) {
            redirectToMembership();
            return;
          }

          var newsletterId =
            newsletterToggleButton.getAttribute("data-newsletter-id") || "";
          var newsletterCard = newsletterToggleButton.closest(
            "[data-cv-match-newsletter-card]"
          );
          var newsletterFeedback = newsletterCard
            ? newsletterCard.querySelector("[data-cv-match-newsletter-feedback]")
            : null;

          if (!newsletterId || !config.ajaxUrl || !config.nonce) {
            if (newsletterFeedback) {
              newsletterFeedback.hidden = false;
              newsletterFeedback.textContent =
                "We could not update this email update right now.";
              newsletterFeedback.classList.add("is-error");
            }
            return;
          }

          var originalText = newsletterToggleButton.textContent;
          var formData = new window.FormData();
          formData.set("action", "sffc_cv_match_toggle_newsletter_subscription");
          formData.set("nonce", config.nonce || "");
          formData.set("newsletter_id", newsletterId);

          newsletterToggleButton.disabled = true;
          newsletterToggleButton.textContent = "Updating...";
          if (newsletterFeedback) {
            newsletterFeedback.hidden = true;
            newsletterFeedback.textContent = "";
            newsletterFeedback.classList.remove("is-error");
          }

          window
            .fetch(config.ajaxUrl, {
              method: "POST",
              body: formData,
              credentials: "same-origin",
            })
            .then(parseAjaxJson)
            .then(function (payload) {
              if (!payload || !payload.success) {
                throw new Error(
                  (payload && payload.data && payload.data.message) ||
                    "We could not update this email update right now."
                );
              }

              var isSubscribed = !!(payload.data && payload.data.subscribed);
              newsletterToggleButton.textContent =
                (payload.data && payload.data.label) ||
                (isSubscribed ? "On" : "Turn on");
              newsletterToggleButton.setAttribute(
                "aria-pressed",
                isSubscribed ? "true" : "false"
              );
              if (newsletterCard) {
                newsletterCard.classList.toggle("is-subscribed", isSubscribed);
              }
              if (newsletterFeedback) {
                newsletterFeedback.hidden = false;
                newsletterFeedback.textContent =
                  (payload.data && payload.data.message) ||
                  (isSubscribed ? "Alerts are on." : "Alerts are off.");
              }
            })
            .catch(function (error) {
              newsletterToggleButton.textContent = originalText;
              if (newsletterFeedback) {
                newsletterFeedback.hidden = false;
                newsletterFeedback.classList.add("is-error");
                newsletterFeedback.textContent =
                  error && error.message
                    ? error.message
                    : "We could not update this email update right now.";
              }
            })
            .finally(function () {
              newsletterToggleButton.disabled = false;
            });
          return;
        }

        if (navTrigger && root.contains(navTrigger)) {
          var sidebarNav = navTrigger.closest(
            ".sffc-cv-match-studio__sidebar-nav"
          );
          event.preventDefault();
          var targetState =
            navTrigger.getAttribute("data-cv-match-nav-trigger") || "";

          if (sidebarNav && !config.loggedIn) {
            redirectToMembership();
            return;
          }

          if (
            sidebarNav &&
            targetState !== "dashboard" &&
            targetState !== "recommended" &&
            targetState !== "newsletters" &&
            targetState !== "newsletter-groups" &&
            targetState !== "saved-lists" &&
            targetState !== "expert-cv-review" &&
            targetState !== "linkedin-review" &&
            targetState !== "salary-checker" &&
            targetState !== "application-packs" &&
            targetState !== "jobs-mailbox" &&
            targetState !== "discover" &&
            targetState !== "profile" &&
            targetState !== "help" &&
            !hasPremiumRecruiterAccess()
          ) {
            redirectToMembership();
            return;
          }

          if (
            (targetState === "interview" || targetState === "outreach") &&
            !hasPremiumRecruiterAccess()
          ) {
            redirectToMembership();
            return;
          }

          if (
            targetState === "dashboard" ||
            targetState === "careers-search" ||
            targetState === "career-report" ||
            targetState === "application-packs" ||
            targetState === "newsletters" ||
            targetState === "newsletter-groups" ||
            targetState === "saved-lists" ||
            targetState === "expert-cv-review" ||
            targetState === "linkedin-review" ||
            targetState === "salary-checker" ||
            targetState === "jobs-mailbox" ||
            targetState === "outreach" ||
            targetState === "tracker" ||
            targetState === "interview" ||
            targetState === "mentorship" ||
            targetState === "recommended" ||
            targetState === "discover" ||
            targetState === "profile" ||
            targetState === "help"
          ) {
            var currentState = root.getAttribute("data-cv-match-view") || "";
            if (
              currentState &&
              currentState !== "dashboard" &&
              currentState !== "jobs-mailbox" &&
              currentState !== "career-report" &&
              currentState !== "application-packs" &&
              currentState !== "newsletters" &&
              currentState !== "newsletter-groups" &&
              currentState !== "saved-lists" &&
              currentState !== "expert-cv-review" &&
              currentState !== "linkedin-review" &&
              currentState !== "salary-checker" &&
              currentState !== "outreach" &&
              currentState !== "tracker" &&
              currentState !== "interview" &&
              currentState !== "mentorship" &&
              currentState !== "recommended" &&
              currentState !== "discover" &&
              currentState !== "profile" &&
              currentState !== "help"
            ) {
              root._cvMatchPreviousState = currentState;
            }
            closeMaterialsModal();
            closeSmartApply();
            closeRecruiterModal();
            setMobileNavOpen(false);
            setState(root, targetState);
            if (
              onboardingState.active &&
              getCurrentTourStep() &&
              String(getCurrentTourStep().id || "") === targetState
            ) {
              window.setTimeout(function () {
                renderTourStep(onboardingState.index + 1);
              }, 180);
            }
            if (targetState === "jobs-mailbox") {
              clearJobsMailboxNewsletterGroup(
                $(root, "[data-cv-match-jobs-mailbox]")
              );
              ensureJobsMailboxLoaded(false);
              applyJobsMailboxFilter("all");
              syncJobsMailboxState("");
              syncJobsMailboxMobileApp("", "feed");
            }
            if (targetState === "career-report") {
              loadCareerReport(false);
            }
            if (targetState === "application-packs") {
              syncApplicationPacksState();
            }
            if (targetState === "recommended") {
              loadRecommendedRoles(true);
            }
            var studioMain = mainPane || $(root, ".sffc-cv-match-studio__main");
            if (studioMain) {
              studioMain.scrollTo({ top: 0, behavior: "auto" });
            }
            return;
          }

          if (targetState === "results") {
            closeMaterialsModal();
            closeSmartApply();
            closeRecruiterModal();
            setMobileNavOpen(false);
            var fallbackState =
              root._cvMatchPreviousState &&
              root._cvMatchPreviousState !== "career-report" &&
              root._cvMatchPreviousState !== "application-packs" &&
              root._cvMatchPreviousState !== "newsletters" &&
              root._cvMatchPreviousState !== "newsletter-groups" &&
              root._cvMatchPreviousState !== "saved-lists" &&
              root._cvMatchPreviousState !== "expert-cv-review" &&
              root._cvMatchPreviousState !== "linkedin-review" &&
              root._cvMatchPreviousState !== "outreach" &&
              root._cvMatchPreviousState !== "jobs-mailbox" &&
              root._cvMatchPreviousState !== "tracker" &&
              root._cvMatchPreviousState !== "interview" &&
              root._cvMatchPreviousState !== "recommended" &&
              root._cvMatchPreviousState !== "discover" &&
              root._cvMatchPreviousState !== "profile" &&
              root._cvMatchPreviousState !== "help"
                ? root._cvMatchPreviousState
                : activeResults && activeResults.length
                ? "results"
                : defaultState;
            setState(root, fallbackState);
            return;
          }
        }

        if (discoveryNavLink && root.contains(discoveryNavLink)) {
          var discoveryHref = discoveryNavLink.getAttribute("href") || "";
          var discoveryUrl;
          event.preventDefault();

          try {
            discoveryUrl = new window.URL(
              discoveryHref,
              window.location.origin
            );
          } catch (error) {
            return;
          }

          refreshDiscoveryView({
            searchTerm:
              discoveryUrl.searchParams.get("sffc_reddit_search") || "",
            discoveryFilter:
              discoveryUrl.searchParams.get("discovery_filter") || "",
            recruiterRegion:
              discoveryUrl.searchParams.get("recruiter_region") || "",
            recruiterIndustry:
              discoveryUrl.searchParams.get("recruiter_industry") || "",
            recruiterSkill:
              discoveryUrl.searchParams.get("recruiter_skill") || "",
            page: Number(discoveryUrl.searchParams.get("matches_page") || 1),
          }).catch(function () {
            return null;
          });
          return;
        }

        if (supportOpenButton && root.contains(supportOpenButton)) {
          event.preventDefault();
          setMobileNavOpen(false);
          openSupportModal();
          return;
        }

        if (supportCloseButton && root.contains(supportCloseButton)) {
          event.preventDefault();
          closeSupportModal();
          return;
        }

        if (recentPinButton && root.contains(recentPinButton)) {
          event.preventDefault();
          togglePinnedRecentRole(
            recentPinButton.getAttribute("data-recent-pin") || ""
          );
          renderRecentRoles(root);
          return;
        }

        if (bulkClearButton && root.contains(bulkClearButton)) {
          event.preventDefault();
          clearSelectedResults(root);
          return;
        }

        if (bulkOutreachButton && root.contains(bulkOutreachButton)) {
          event.preventDefault();
          createOutreachQueueFromSelection(root);
          return;
        }

        if (bulkQuickViewButton && root.contains(bulkQuickViewButton)) {
          var selectedItems = getSelectedResults(root);
          if (selectedItems.length === 1) {
            event.preventDefault();
            closeRecruiterModal();
            setMobileNavOpen(false);
            root._cvMatchPreviousState = "results";
            loadJobView(selectedItems[0]).catch(function () {
              return null;
            });
          }
          return;
        }

        if (openRoleLink && root.contains(openRoleLink)) {
          incrementJobsMailboxClick(getJobsMailboxClickKey(openRoleLink));
          trackRecentRole({
            title: openRoleLink.getAttribute("data-open-role-title") || "Role",
            href:
              openRoleLink.getAttribute("data-open-role-href") ||
              openRoleLink.getAttribute("href") ||
              "#",
            id: Number(openRoleLink.getAttribute("data-open-role-crm-id") || 0),
            jobsPostId: Number(
              openRoleLink.getAttribute("data-open-role-id") || 0
            ),
            wpPostId: Number(
              openRoleLink.getAttribute("data-open-role-wp-id") || 0
            ),
            location:
              openRoleLink.getAttribute("data-open-role-location") || "",
            sector: openRoleLink.getAttribute("data-open-role-sector") || "",
            seniority:
              openRoleLink.getAttribute("data-open-role-seniority") || "",
            keywords: String(
              openRoleLink.getAttribute("data-open-role-keywords") || ""
            )
              .split("|")
              .filter(Boolean),
          });
          trackGuestSignal(
            {
              title: openRoleLink.getAttribute("data-open-role-title") || "Role",
              href:
                openRoleLink.getAttribute("data-open-role-href") ||
                openRoleLink.getAttribute("href") ||
                "#",
              id: Number(openRoleLink.getAttribute("data-open-role-crm-id") || 0),
              jobsPostId: Number(openRoleLink.getAttribute("data-open-role-id") || 0),
              wpPostId: Number(openRoleLink.getAttribute("data-open-role-wp-id") || 0),
              location: openRoleLink.getAttribute("data-open-role-location") || "",
              sector: openRoleLink.getAttribute("data-open-role-sector") || "",
              seniority: openRoleLink.getAttribute("data-open-role-seniority") || "",
              keywords: String(
                openRoleLink.getAttribute("data-open-role-keywords") || ""
              )
                .split("|")
                .filter(Boolean),
            },
            "open_role"
          );
          root._cvMatchRecommendedSignature = "";
          root._cvMatchRecommendedPayload = null;
          root._cvMatchTerminalSignature = "";
          root._cvMatchTerminalPayload = null;
          renderRecentRoles(root);
          return;
        }

        if (quickViewButton && root.contains(quickViewButton)) {
          incrementJobsMailboxClick(getJobsMailboxClickKey(quickViewButton));
          var openRoleId = Number(
            quickViewButton.getAttribute("data-open-role-id") || 0
          );
          var openRoleWpId = Number(
            quickViewButton.getAttribute("data-open-role-wp-id") || 0
          );
          var openRoleCrmId = Number(
            quickViewButton.getAttribute("data-open-role-crm-id") || 0
          );
          trackRecentRole({
            title:
              quickViewButton.getAttribute("data-open-role-title") || "Role",
            href:
              quickViewButton.getAttribute("data-open-role-href") ||
              "#",
            id: Number(
              quickViewButton.getAttribute("data-open-role-crm-id") || 0
            ),
            jobsPostId: Number(
              quickViewButton.getAttribute("data-open-role-id") || 0
            ),
            wpPostId: Number(
              quickViewButton.getAttribute("data-open-role-wp-id") || 0
            ),
            location:
              quickViewButton.getAttribute("data-open-role-location") || "",
            sector:
              quickViewButton.getAttribute("data-open-role-sector") || "",
            seniority:
              quickViewButton.getAttribute("data-open-role-seniority") || "",
            keywords: String(
              quickViewButton.getAttribute("data-open-role-keywords") || ""
            )
              .split("|")
              .filter(Boolean),
          });
          trackGuestSignal(
            {
              title:
                quickViewButton.getAttribute("data-open-role-title") || "Role",
              href:
                quickViewButton.getAttribute("data-open-role-href") ||
                "#",
              id: Number(
                quickViewButton.getAttribute("data-open-role-crm-id") || 0
              ),
              jobsPostId: Number(
                quickViewButton.getAttribute("data-open-role-id") || 0
              ),
              wpPostId: Number(
                quickViewButton.getAttribute("data-open-role-wp-id") || 0
              ),
              location:
                quickViewButton.getAttribute("data-open-role-location") || "",
              sector:
                quickViewButton.getAttribute("data-open-role-sector") || "",
              seniority:
                quickViewButton.getAttribute("data-open-role-seniority") || "",
              keywords: String(
                quickViewButton.getAttribute("data-open-role-keywords") || ""
              )
                .split("|")
                .filter(Boolean),
            },
            "quick_view"
          );
          root._cvMatchRecommendedSignature = "";
          root._cvMatchRecommendedPayload = null;
          root._cvMatchTerminalSignature = "";
          root._cvMatchTerminalPayload = null;
          renderRecentRoles(root);

          if (openRoleId || openRoleWpId || openRoleCrmId) {
            event.preventDefault();
            var currentView =
              String(root.getAttribute("data-cv-match-view") || "").trim() || "results";
            closeRecruiterModal();
            setMobileNavOpen(false);
            root._cvMatchPreviousState =
              currentView === "jobs-mailbox" ? "jobs-mailbox" : "results";
            syncJobBackButton();
            loadJobView({
              jobsPostId: openRoleId,
              wpPostId: openRoleWpId,
              id: openRoleCrmId,
              roleTitle:
                quickViewButton.getAttribute("data-open-role-title") || "Role",
            }).catch(function () {
              return null;
            });
          }
          return;
        }

        if (recruiterOpenButton) {
          event.preventDefault();
          openRecruiterProfile({
            recruiterId: Number(
              recruiterOpenButton.getAttribute(
                "data-cv-match-recruiter-open"
              ) || 0
            ),
            recruiterName:
              recruiterOpenButton.getAttribute(
                "data-cv-match-recruiter-name"
              ) || "Recruiter",
            roleId: Number(
              recruiterOpenButton.getAttribute(
                "data-cv-match-recruiter-role-id"
              ) || 0
            ),
            jobsPostId: Number(
              recruiterOpenButton.getAttribute(
                "data-cv-match-recruiter-jobs-post-id"
              ) || 0
            ),
          });
          return;
        }

        if (recruiterCloseButton) {
          event.preventDefault();
          closeRecruiterModal();
          return;
        }

        if (materialsOpenButton) {
          event.preventDefault();
          openGeneratedMaterials(
            materialsOpenButton.getAttribute("data-cv-match-material-type") || ""
          );
          return;
        }

        if (materialsCloseButton) {
          event.preventDefault();
          closeMaterialsModal();
          return;
        }

        if (
          materialsTab &&
          materialsTabs &&
          materialsTabs.contains(materialsTab)
        ) {
          event.preventDefault();
          var activeTabIndex = Number(
            materialsTab.getAttribute("data-cv-match-material-tab") || -1
          );
          var cachedResources = Array.isArray(activeMaterialsResources)
            ? activeMaterialsResources
            : [];

          if (activeTabIndex >= 0 && cachedResources[activeTabIndex]) {
            setActiveMaterialsResource(
              cachedResources[activeTabIndex],
              cachedResources,
              activeTabIndex
            );
          }
          return;
        }

        if (networkingOpenButton) {
          event.preventDefault();
          var smartItem = activeJobItem || getStandaloneJobItem();
          if (smartItem) {
            openSmartApply(smartItem);
          }
          return;
        }

        if (reportOpenButton) {
          event.preventDefault();
          openCvReport();
          return;
        }

        if (jobBackButton) {
          event.preventDefault();
          var backState = getJobBackState();
          closeMaterialsModal();
          setMobileNavOpen(false);
          setState(root, backState);
          if (backState === "jobs-mailbox") {
            syncJobsMailboxState("");
          } else {
            root._cvMatchPreviousState = "results";
            var mainPane = root.querySelector(".sffc-cv-match-studio__main");
            if (mainPane && typeof root._cvMatchResultsScrollTop === "number") {
              mainPane.scrollTo({
                top: root._cvMatchResultsScrollTop,
                behavior: "auto",
              });
            }
          }
          return;
        }

        if (smartApplyClose) {
          event.preventDefault();
          closeSmartApply();
          return;
        }

        if (smartApplyButton) {
          event.preventDefault();
          if (!config.loggedIn) {
            redirectToMembership();
            return;
          }
          var smartIndex = Number(
            smartApplyButton.getAttribute("data-smart-apply-open")
          );
          if (
            !Number.isNaN(smartIndex) &&
            root._cvMatchVisibleResults &&
            root._cvMatchVisibleResults[smartIndex]
          ) {
            openSmartApply(root._cvMatchVisibleResults[smartIndex]);
          }
          return;
        }

        if (!explainButton) {
          return;
        }

        var index = explainButton.getAttribute("data-result-explain");
        var detailRow = $(root, '[data-result-detail="' + index + '"]');
        var isOpen = detailRow && !detailRow.hidden;

        $all(root, "[data-result-detail]").forEach(function (row) {
          row.hidden = true;
        });
        $all(root, "[data-result-explain]").forEach(function (button) {
          button.textContent =
            config.labels && config.labels.explain
              ? config.labels.explain
              : "Explain";
        });

        if (detailRow && !isOpen) {
          detailRow.hidden = false;
          explainButton.textContent =
            config.labels && config.labels.hideExplain
              ? config.labels.hideExplain
              : "Hide";
        }
      });

      document.addEventListener("click", function (event) {
        if (!root.contains(event.target)) {
          closeCommunityAccountMenu();
        }
      });

      if (supportForm) {
        supportForm.addEventListener("submit", function (event) {
          event.preventDefault();

          var subjectInput = supportForm.querySelector('input[name="subject"]');
          var messageInput = supportForm.querySelector(
            'textarea[name="message"]'
          );
          var subject = subjectInput
            ? String(subjectInput.value || "").trim()
            : "";
          var message = messageInput
            ? String(messageInput.value || "").trim()
            : "";

          if (!subject) {
            setSupportFeedback("Please add a subject.", true);
            if (subjectInput) {
              subjectInput.focus();
            }
            return;
          }

          if (!message) {
            setSupportFeedback("Please tell us how we can help.", true);
            if (messageInput) {
              messageInput.focus();
            }
            return;
          }

          if (supportSubmit) {
            supportSubmit.disabled = true;
          }
          setSupportFeedback("Sending your message…", false);

          var formData = new window.FormData();
          formData.append("action", "sffc_cv_match_support_message");
          formData.append("nonce", config.nonce || "");
          formData.append("subject", subject);
          formData.append("message", message);

          window
            .fetch(config.ajaxUrl || "/wp-admin/admin-ajax.php", {
              method: "POST",
              body: formData,
              credentials: "same-origin",
            })
            .then(parseAjaxJson)
            .then(function (payload) {
              if (!payload || !payload.success) {
                throw new Error(
                  (payload && payload.data && payload.data.message) ||
                    "We could not send your message right now."
                );
              }

              setSupportFeedback(
                (payload.data && payload.data.message) ||
                  "Your message has been sent to support.",
                false
              );
              window.setTimeout(function () {
                closeSupportModal();
              }, 900);
            })
            .catch(function (error) {
              setSupportFeedback(
                error && error.message
                  ? error.message
                  : "We could not send your message right now.",
                true
              );
            })
            .finally(function () {
              if (supportSubmit) {
                supportSubmit.disabled = false;
              }
          });
        });
      }

      if (customListForm) {
        customListForm.addEventListener("submit", function (event) {
          event.preventDefault();

          var requirementsInput = customListForm.querySelector(
            "[data-cv-match-custom-list-requirements]"
          );
          var requirements = requirementsInput
            ? String(requirementsInput.value || "").trim()
            : "";

          if (!requirements) {
            setCustomListFeedback(
              "Please describe the list you want.",
              true
            );
            if (requirementsInput) {
              requirementsInput.focus();
            }
            return;
          }

          if (customListSubmit) {
            customListSubmit.disabled = true;
          }
          setCustomListFeedback("Sending your request...", false);

          var formData = new window.FormData();
          formData.append("action", "sffc_cv_match_support_message");
          formData.append("nonce", config.nonce || "");
          formData.append("subject", "Custom list request");
          formData.append(
            "message",
            "Custom list request:\n\n" + requirements
          );

          window
            .fetch(config.ajaxUrl || "/wp-admin/admin-ajax.php", {
              method: "POST",
              body: formData,
              credentials: "same-origin",
            })
            .then(parseAjaxJson)
            .then(function (payload) {
              if (!payload || !payload.success) {
                throw new Error(
                  (payload && payload.data && payload.data.message) ||
                    "We could not send your request right now."
                );
              }

              setCustomListFeedback(
                (payload.data && payload.data.message) ||
                  "Your custom list request has been sent.",
                false
              );
              window.setTimeout(function () {
                closeCustomListModal();
              }, 1000);
            })
            .catch(function (error) {
              setCustomListFeedback(
                error && error.message
                  ? error.message
                  : "We could not send your request right now.",
                true
              );
            })
            .finally(function () {
              if (customListSubmit) {
                customListSubmit.disabled = false;
              }
            });
        });
      }

      if (dailyScanForm) {
        dailyScanForm.addEventListener("submit", function (event) {
          event.preventDefault();

          var selectedInputs = $all(
            dailyScanForm,
            'input[name="daily_scan_groups[]"]:checked'
          );
          var groupIds = selectedInputs
            .map(function (input) {
              return String(input.value || "").trim();
            })
            .filter(Boolean);

          if (!groupIds.length) {
            setDailyScanFeedback(
              "Choose at least one list to scan daily.",
              true
            );
            return;
          }

          if (dailyScanSubmit) {
            dailyScanSubmit.disabled = true;
          }
          setDailyScanFeedback("Saving your daily scan...", false);

          var formData = new window.FormData();
          formData.append("action", "sffc_cv_match_save_daily_scan");
          formData.append("nonce", config.nonce || "");
          groupIds.forEach(function (groupId) {
            formData.append("group_ids[]", groupId);
          });

          window
            .fetch(config.ajaxUrl || "/wp-admin/admin-ajax.php", {
              method: "POST",
              body: formData,
              credentials: "same-origin",
            })
            .then(parseAjaxJson)
            .then(function (payload) {
              if (!payload || !payload.success) {
                throw new Error(
                  (payload && payload.data && payload.data.message) ||
                    "We could not save your daily scan right now."
                );
              }

              setDailyScanFeedback(
                (payload.data && payload.data.message) ||
                  "Daily scan saved.",
                false
              );
              syncDailyScanGroupState(
                payload.data && payload.data.group_ids
                  ? payload.data.group_ids
                  : groupIds
              );
              window.setTimeout(function () {
                closeDailyScanDropdown();
              }, 1100);
            })
            .catch(function (error) {
              setDailyScanFeedback(
                error && error.message
                  ? error.message
                  : "We could not save your daily scan right now.",
                true
              );
            })
            .finally(function () {
              if (dailyScanSubmit) {
                dailyScanSubmit.disabled = false;
              }
            });
        });
      }

      if (emailListForm) {
        emailListForm.addEventListener("submit", function (event) {
          event.preventDefault();

          var groupNameInput = emailListForm.querySelector(
            "[data-cv-match-email-list-group-name]"
          );
          var groupTypeInput = emailListForm.querySelector(
            "[data-cv-match-email-list-group-type]"
          );
          var frequencyInput = emailListForm.querySelector(
            "[data-cv-match-email-list-frequency]"
          );
          var emailInput = emailListForm.querySelector(
            "[data-cv-match-email-list-email]"
          );
          var groupName = groupNameInput
            ? String(groupNameInput.value || "").trim()
            : "";
          var groupType = groupTypeInput
            ? String(groupTypeInput.value || "").trim()
            : "Jobs";
          var frequency = frequencyInput
            ? String(frequencyInput.value || "").trim()
            : "daily";
          var preferredEmail = emailInput
            ? String(emailInput.value || "").trim()
            : "";
          var frequencyLabel =
            frequency === "weekly"
              ? "Weekly"
              : frequency === "monthly"
                ? "Monthly"
                : "Daily";

          if (!preferredEmail || preferredEmail.indexOf("@") === -1) {
            setEmailListFeedback("Please enter a valid preferred email.", true);
            if (emailInput) {
              emailInput.focus();
            }
            return;
          }

          if (emailListSubmit) {
            emailListSubmit.disabled = true;
          }
          setEmailListFeedback("Saving your email preference...", false);

          var formData = new window.FormData();
          formData.append("action", "sffc_cv_match_support_message");
          formData.append("nonce", config.nonce || "");
          formData.append(
            "subject",
            "Email list preference: " + (groupName || groupType)
          );
          formData.append(
            "message",
            "List: " +
              (groupName || "Selected list") +
              "\nType: " +
              groupType +
              "\nFrequency: " +
              frequencyLabel +
              "\nPreferred email: " +
              preferredEmail
          );

          window
            .fetch(config.ajaxUrl || "/wp-admin/admin-ajax.php", {
              method: "POST",
              body: formData,
              credentials: "same-origin",
            })
            .then(parseAjaxJson)
            .then(function (payload) {
              if (!payload || !payload.success) {
                throw new Error(
                  (payload && payload.data && payload.data.message) ||
                    "We could not save your email preference right now."
                );
              }

              setEmailListFeedback(
                (payload.data && payload.data.message) ||
                  "Your email preference has been saved.",
                false
              );
              window.setTimeout(function () {
                closeEmailListModal();
              }, 1000);
            })
            .catch(function (error) {
              setEmailListFeedback(
                error && error.message
                  ? error.message
                  : "We could not save your email preference right now.",
                true
              );
            })
            .finally(function () {
              if (emailListSubmit) {
                emailListSubmit.disabled = false;
              }
            });
        });
      }

      root.addEventListener("change", function (event) {
        var mentorshipFocus = event.target.closest(
          "[data-cv-match-mentorship-focus]"
        );
        if (!mentorshipFocus || !root.contains(mentorshipFocus)) {
          return;
        }

        var form = mentorshipFocus.closest("[data-cv-match-mentorship-form]");
        var requestType = form ? form.querySelector('[name="request_type"]') : null;
        if (requestType) {
          requestType.value = mentorshipFocus.value || "mentor_session";
        }
      });

      root.addEventListener("submit", function (event) {
        var mentorshipForm = event.target.closest(
          "[data-cv-match-mentorship-form]"
        );
        if (!mentorshipForm || !root.contains(mentorshipForm)) {
          return;
        }

        event.preventDefault();

        var feedback = mentorshipForm.querySelector(
          "[data-cv-match-mentorship-feedback]"
        );
        var submitButton = mentorshipForm.querySelector(
          "[data-cv-match-mentorship-submit]"
        );
        var quotaLabel = root.querySelector(
          "[data-cv-match-mentorship-quota-label]"
        );
        var formData = new window.FormData(mentorshipForm);
        formData.set("action", "sffc_crm_submit_mentorship_request");
        formData.set("nonce", config.nonce || "");

        if (feedback) {
          feedback.hidden = false;
          feedback.classList.remove("is-error");
          feedback.textContent = "Sending your mentorship request...";
        }
        if (submitButton) {
          submitButton.disabled = true;
        }

        window
          .fetch(config.ajaxUrl || "/wp-admin/admin-ajax.php", {
            method: "POST",
            body: formData,
            credentials: "same-origin",
          })
          .then(parseAjaxJson)
          .then(function (payload) {
            if (!payload || !payload.success) {
              throw new Error(
                (payload && payload.data && payload.data.message) ||
                  "We could not send your request right now."
              );
            }

            var message =
              (payload.data && payload.data.message) ||
              "Request sent. The mentorship team has been notified.";
            if (feedback) {
              feedback.classList.remove("is-error");
              feedback.textContent = message;
            }
            if (
              quotaLabel &&
              payload.data &&
              typeof payload.data.remaining === "number"
            ) {
              quotaLabel.textContent =
                payload.data.remaining === 1
                  ? "1 mentorship session left this week"
                  : payload.data.remaining + " mentorship sessions left this week";
            }
            mentorshipForm.reset();
          })
          .catch(function (error) {
            if (feedback) {
              feedback.hidden = false;
              feedback.classList.add("is-error");
              feedback.textContent =
                error && error.message
                  ? error.message
                  : "We could not send your request right now.";
            }
          })
          .finally(function () {
            if (submitButton) {
              submitButton.disabled = false;
            }
          });
      });

      if (expertCvReviewForm) {
        expertCvReviewForm.addEventListener("submit", function (event) {
          event.preventDefault();
          submitExpertCvReviewRequest();
        });
      }

      if (linkedinReviewForm) {
        linkedinReviewForm.addEventListener("submit", function (event) {
          event.preventDefault();
          submitLinkedinReviewRequest();
        });
      }

      if (salaryCheckerForm) {
        salaryCheckerForm.addEventListener("submit", function (event) {
          event.preventDefault();
          submitSalaryChecker();
        });

        salaryCheckerForm.addEventListener("change", function (event) {
          if (
            event.target &&
            event.target.closest(
              "[data-cv-match-salary-location], [data-cv-match-salary-seniority]"
            )
          ) {
            queueSalaryCheckerUpdate();
          }
        });
      }

      if (salaryCheckerPrint) {
        salaryCheckerPrint.addEventListener("click", function (event) {
          event.preventDefault();
          printSalaryCheckerReport();
        });

        window.addEventListener("afterprint", clearSalaryCheckerPrintMode);
      }

      if (interviewForm) {
        interviewForm.addEventListener("submit", function (event) {
          event.preventDefault();
          submitInterviewQuestions();
        });
      }

      document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
          closeJobsMailboxMenus(root);
          closeSmartApply();
          closeMaterialsModal();
          closeRecruiterModal();
          closeTour();
          closeWelcomeModal();
        }
      });

      root.addEventListener("change", function (event) {
        var trackerSelect = event.target.closest("[data-cv-match-track-stage]");

        if (!trackerSelect) {
          return;
        }

        saveCvMatchJobTrackerStage(trackerSelect);
      });

      startCvMatchNotificationPolling();
    });
  });
})();
