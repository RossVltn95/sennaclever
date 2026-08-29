(function () {
  function setCookie(name, value) {
    if (!name) return;
    document.cookie =
      name +
      "=" +
      encodeURIComponent(String(value || "")) +
      "; path=/; max-age=" +
      60 * 60 * 24 * 30 +
      "; SameSite=Lax";
  }

  function splitName(fullName) {
    var parts = String(fullName || "")
      .trim()
      .split(/\s+/)
      .filter(Boolean);
    return {
      firstName: parts[0] || "",
      lastName: parts.length > 1 ? parts[parts.length - 1] : "",
    };
  }

  function setInputValue(input, value) {
    if (!input) return;
    input.value = String(value || "");
    input.dispatchEvent(new Event("input", { bubbles: true }));
    input.dispatchEvent(new Event("change", { bubbles: true }));
  }

  function applyMemberpressPrefill(root, fullName, email) {
    var nameParts = splitName(fullName);
    var firstName = String(nameParts.firstName || "");
    var lastName = String(nameParts.lastName || "");
    var safeEmail = String(email || "");

    root
      .querySelectorAll(
        'input[name="user_email"], input[name="mepr_user_email"], input[name="email"], #user_email, #user_email1, #mepr_email, #mepr_user_email'
      )
      .forEach(function (input) {
        setInputValue(input, safeEmail);
      });

    root
      .querySelectorAll(
        'input[name="user_first_name"], input[name="mepr_first_name"], input[name="first_name"], #user_first_name, #user_first_name1, #mepr_first_name'
      )
      .forEach(function (input) {
        setInputValue(input, firstName);
      });

    root
      .querySelectorAll(
        'input[name="user_last_name"], input[name="mepr_last_name"], input[name="last_name"], #user_last_name, #user_last_name1, #mepr_last_name'
      )
      .forEach(function (input) {
        setInputValue(input, lastName);
      });
  }

  function syncSignupPrefill(fullName, email, studyLevel, accountType) {
    var config = window.sffcMemberPaidSignup || {};
    if (!config.ajaxUrl || !config.prefillNonce || !window.fetch) {
      return Promise.resolve();
    }

    var nameParts = splitName(fullName);
    var body = new URLSearchParams();
    body.set("action", "sffc_sync_signup_prefill");
    body.set("nonce", config.prefillNonce);
    body.set("email", String(email || ""));
    body.set("first_name", String(nameParts.firstName || ""));
    body.set("last_name", String(nameParts.lastName || ""));
    body.set("study_level", String(studyLevel || ""));
    body.set("account_type", String(accountType || ""));

    return fetch(config.ajaxUrl, {
      method: "POST",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
      },
      body: body.toString(),
      keepalive: true,
    }).catch(function () {});
  }

  function persistIdentity(fullName, email, studyLevel, accountType) {
    var nameParts = splitName(fullName);
    setCookie("sffc_signup_email", email);
    setCookie("sffc_signup_first_name", nameParts.firstName || "");
    setCookie("sffc_signup_last_name", nameParts.lastName || "");
    setCookie("sffc_signup_study_level", studyLevel || "");
    if (accountType) {
      setCookie("sffc_signup_account_type", accountType);
    }
    return syncSignupPrefill(fullName, email, studyLevel, accountType);
  }

  function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(email || "").trim());
  }

  function getRecommendedPath(purpose) {
    if (purpose === "platform") return "platform";
    if (purpose === "all_access") return "all_access";
    return "mentorship";
  }

  function applyRecommendation(root, path) {
    root
      .querySelectorAll("[data-member-paid-signup-plan-card]")
      .forEach(function (card) {
        var isRecommended =
          card.getAttribute("data-member-paid-signup-account-type") === path;
        card.classList.toggle("is-selected", isRecommended);
        var badge = card.querySelector("[data-member-paid-signup-badge]");
        if (badge) {
          badge.hidden = !isRecommended;
        }
      });
  }

  function filterPlans(root, path) {
    root
      .querySelectorAll("[data-member-paid-signup-cycle-panel]")
      .forEach(function (panel) {
        var visibleCount = 0;
        panel
          .querySelectorAll("[data-member-paid-signup-plan-card]")
          .forEach(function (card) {
            var shouldShow =
              card.getAttribute("data-member-paid-signup-account-type") === path;
            card.hidden = !shouldShow;
            if (shouldShow) {
              visibleCount += 1;
            }
          });
        panel.classList.toggle("is-single-plan", visibleCount <= 1);
      });
  }

  function showCycle(root, cycle) {
    root
      .querySelectorAll("[data-member-paid-signup-cycle]")
      .forEach(function (button) {
        button.classList.toggle(
          "is-active",
          button.getAttribute("data-member-paid-signup-cycle") === cycle
        );
      });

    root
      .querySelectorAll("[data-member-paid-signup-cycle-panel]")
      .forEach(function (panel) {
        var isActive =
          panel.getAttribute("data-member-paid-signup-cycle-panel") === cycle;
        panel.hidden = !isActive;
        panel.classList.toggle("is-active", isActive);
      });
  }

  function validateAndShowPlans(root, options) {
    options = options || {};

    var nameInput = root.querySelector("[data-member-paid-signup-name]");
    var emailInput = root.querySelector("[data-member-paid-signup-email]");
    var error = root.querySelector("[data-member-paid-signup-error]");
    var plans = root.querySelector("[data-member-paid-signup-plans]");
    var stageInput = root.querySelector("[data-member-paid-signup-stage]");
    var purposeInput = root.querySelector("[data-member-paid-signup-purpose]");

    var fullName = nameInput ? nameInput.value.trim() : "";
    var email = emailInput ? emailInput.value.trim() : "";
    var stage = stageInput ? String(stageInput.value || "").trim() : "";
    var purpose = purposeInput ? String(purposeInput.value || "").trim() : "";

    var valid = fullName.length >= 2 && isValidEmail(email) && stage && purpose;
    if (error) {
      error.hidden = !!valid;
    }
    if (!valid) {
      if (!options.silent) {
        if (fullName.length < 2 && nameInput) {
          nameInput.focus();
        } else if (!isValidEmail(email) && emailInput) {
          emailInput.focus();
        } else if (!stage) {
          var firstStage = root.querySelector("[data-member-paid-signup-stage-option]");
          if (firstStage) firstStage.focus();
        }
      }
      return false;
    }

    var recommendedPath = getRecommendedPath(purpose);
    persistIdentity(fullName, email, stage, recommendedPath);
    applyMemberpressPrefill(root, fullName, email);
    filterPlans(root, recommendedPath);
    applyRecommendation(root, recommendedPath);
    if (plans) {
      plans.hidden = false;
      if (!options.skipScroll) {
        plans.scrollIntoView({ behavior: "smooth", block: "start" });
      }
    }

    return true;
  }

  function init(root) {
    if (!root || root.getAttribute("data-member-paid-signup-bound") === "1") {
      return;
    }
    root.setAttribute("data-member-paid-signup-bound", "1");

    var form = root.querySelector("[data-member-paid-signup-form]");
    var nameInput = root.querySelector("[data-member-paid-signup-name]");
    var emailInput = root.querySelector("[data-member-paid-signup-email]");
    var error = root.querySelector("[data-member-paid-signup-error]");
    var plans = root.querySelector("[data-member-paid-signup-plans]");
    var checkout = root.querySelector("[data-member-paid-signup-checkout]");
    var stageInput = root.querySelector("[data-member-paid-signup-stage]");
    var purposeGroup = root.querySelector("[data-member-paid-signup-purpose-group]");
    var purposeInput = root.querySelector("[data-member-paid-signup-purpose]");
    var initialPurpose = purposeInput ? String(purposeInput.value || "").trim() : "";
    var initialStage = stageInput ? String(stageInput.value || "").trim() : "";
    var isCompactMobile =
      !!window.matchMedia && window.matchMedia("(max-width: 767px)").matches;

    function getStage() {
      return stageInput ? String(stageInput.value || "").trim() : "";
    }

    function setStage(value) {
      var normalized = String(value || "").trim();
      if (stageInput) {
        stageInput.value = normalized;
      }
      root
        .querySelectorAll("[data-member-paid-signup-stage-option]")
        .forEach(function (button) {
          var isActive =
            button.getAttribute("data-member-paid-signup-stage-option") ===
            normalized;
          button.classList.toggle("is-active", isActive);
          button.setAttribute("aria-pressed", isActive ? "true" : "false");
        });
      if (purposeGroup) {
        purposeGroup.hidden = !normalized;
      }
    }

    function getPurpose() {
      return purposeInput ? String(purposeInput.value || "").trim() : "";
    }

    function setPurpose(value) {
      var normalized = String(value || "").trim();
      if (purposeInput) {
        purposeInput.value = normalized;
      }
      root
        .querySelectorAll("[data-member-paid-signup-purpose-option]")
        .forEach(function (button) {
          var isActive =
            button.getAttribute("data-member-paid-signup-purpose-option") ===
            normalized;
          button.classList.toggle("is-active", isActive);
          button.setAttribute("aria-pressed", isActive ? "true" : "false");
        });
    }

    function syncPurposeState(value) {
      var recommendedPath = getRecommendedPath(value);
      setPurpose(value);
      filterPlans(root, recommendedPath);
      applyRecommendation(root, recommendedPath);
    }

    function showCheckout(planSlug) {
      if (!checkout) return;
      var fullName = nameInput ? nameInput.value.trim() : "";
      var email = emailInput ? emailInput.value.trim() : "";
      applyMemberpressPrefill(root, fullName, email);
      checkout.hidden = false;
      checkout
        .querySelectorAll("[data-member-paid-signup-shell]")
        .forEach(function (shell) {
          shell.hidden =
            shell.getAttribute("data-member-paid-signup-shell") !== planSlug;
        });
      checkout.scrollIntoView({ behavior: "smooth", block: "start" });
    }

    if (form) {
      form.addEventListener("submit", function (event) {
        event.preventDefault();
        validateAndShowPlans(root, { silent: false, skipScroll: false });
      });
    }

    if (nameInput) {
      nameInput.addEventListener("input", function () {
        applyMemberpressPrefill(root, nameInput.value, emailInput ? emailInput.value : "");
      });
    }

    if (emailInput) {
      emailInput.addEventListener("input", function () {
        applyMemberpressPrefill(root, nameInput ? nameInput.value : "", emailInput.value);
      });
    }

    root.addEventListener("click", function (event) {
      var cycleButton = event.target.closest("[data-member-paid-signup-cycle]");
      var planLink = event.target.closest("[data-member-paid-signup-plan-link]");
      var planCard = event.target.closest("[data-member-paid-signup-plan-card]");
      var choosePlanButton = event.target.closest(
        "[data-member-paid-signup-choose-plan]"
      );
      var backButton = event.target.closest("[data-member-paid-signup-back]");

      if (cycleButton) {
        event.preventDefault();
        showCycle(
          root,
          cycleButton.getAttribute("data-member-paid-signup-cycle") || "monthly"
        );
        return;
      }

      var purposeButton = event.target.closest(
        "[data-member-paid-signup-purpose-option]"
      );
      var stageButton = event.target.closest(
        "[data-member-paid-signup-stage-option]"
      );

      if (stageButton) {
        event.preventDefault();
        setStage(
          stageButton.getAttribute("data-member-paid-signup-stage-option") || ""
        );
        return;
      }

      if (purposeButton) {
        event.preventDefault();
        var selectedPurpose =
          purposeButton.getAttribute("data-member-paid-signup-purpose-option") ||
          "";
        syncPurposeState(selectedPurpose);
        validateAndShowPlans(root, { silent: false, skipScroll: false });
        return;
      }

      if (choosePlanButton) {
        event.preventDefault();
        var fullName = nameInput ? nameInput.value.trim() : "";
        var email = emailInput ? emailInput.value.trim() : "";
        var stage = getStage();
        var purpose = getPurpose();
        var accountType =
          choosePlanButton.getAttribute("data-member-paid-signup-account-type") ||
          "";
        var slug =
          choosePlanButton.getAttribute("data-member-paid-signup-choose-plan") ||
          "";
        persistIdentity(
          fullName,
          email,
          stage,
          accountType || getRecommendedPath(purpose)
        );
        showCheckout(slug);
        return;
      }

      if (planCard && !planLink) {
        root
          .querySelectorAll("[data-member-paid-signup-plan-card]")
          .forEach(function (card) {
            card.classList.remove("is-selected");
          });
        planCard.classList.add("is-selected");
        return;
      }

      if (planLink) {
        var fullName = nameInput ? nameInput.value.trim() : "";
        var email = emailInput ? emailInput.value.trim() : "";
        var stage = getStage();
        var purpose = getPurpose();
        var accountType =
          planLink.getAttribute("data-member-paid-signup-account-type") || "";
        persistIdentity(fullName, email, stage, accountType || getRecommendedPath(purpose));
        return;
      }

      if (backButton) {
        event.preventDefault();
        if (checkout) {
          checkout.hidden = true;
        }
      }
    });

    if (initialStage && !isCompactMobile) {
      setStage(initialStage);
    }

    if (initialPurpose && !isCompactMobile) {
      syncPurposeState(initialPurpose);
      validateAndShowPlans(root, { silent: true, skipScroll: true });
    }
  }

  document.addEventListener("DOMContentLoaded", function () {
    document
      .querySelectorAll("[data-sffc-member-paid-signup]")
      .forEach(init);
  });
})();
