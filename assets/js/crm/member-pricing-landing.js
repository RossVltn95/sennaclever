(function () {
  function setSignupEmailCookie(email) {
    if (!email) {
      return;
    }

    setCookie("sffc_signup_email", email);
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
      60 * 60 * 24 * 30 +
      "; SameSite=Lax";

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
          60 * 60 * 24 * 30 +
          "; SameSite=Lax";
      }
    }
  }

  function syncSignupPrefill(fullName, email) {
    var config = window.sffcMemberPricingLanding || {};
    if (!config.ajaxUrl || !config.prefillNonce || !window.fetch) {
      return;
    }

    var nameParts = splitName(fullName);
    var body = new URLSearchParams();
    body.set("action", "sffc_sync_signup_prefill");
    body.set("nonce", config.prefillNonce);
    body.set("email", String(email || ""));
    body.set("first_name", String(nameParts.firstName || ""));
    body.set("last_name", String(nameParts.lastName || ""));

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

  function splitName(fullName) {
    var parts = String(fullName || "")
      .trim()
      .split(/\s+/)
      .filter(Boolean);
    return {
      firstName: parts.shift() || "",
      lastName: parts.join(" "),
    };
  }

  function setSignupIdentity(fullName, email) {
    var nameParts = splitName(fullName);

    setSignupEmailCookie(email);
    if (nameParts.firstName) {
      setCookie("sffc_signup_first_name", nameParts.firstName);
    }
    setCookie("sffc_signup_last_name", nameParts.lastName);
    syncSignupPrefill(fullName, email);
  }

  function getStep(root, number) {
    return root.querySelector('[data-member-pricing-step="' + number + '"]');
  }

  function showStep(root, number) {
    root.querySelectorAll("[data-member-pricing-step]").forEach(function (step) {
      var isActive = step.getAttribute("data-member-pricing-step") === String(number);
      step.hidden = !isActive;
      step.classList.toggle("is-active", isActive);
    });
    root.setAttribute("data-active-step", String(number));
  }

  function init(root) {
    var form = root.querySelector("[data-member-pricing-form]");
    var emailInput = root.querySelector("#sffc-member-pricing-email");
    var error = root.querySelector("[data-member-pricing-error]");
    var identityForm = root.querySelector("[data-member-pricing-identity-form]");
    var identityNameInput = root.querySelector("[data-member-pricing-name]");
    var identityEmailInput = root.querySelector("[data-member-pricing-email]");
    var identityError = root.querySelector("[data-member-pricing-identity-error]");
    var stepTwo = getStep(root, 2);
    var nextButton = stepTwo
      ? stepTwo.querySelector("[data-member-pricing-next]")
      : null;
    var backButtons = root.querySelectorAll("[data-member-pricing-back]");
    var planLinks = root.querySelectorAll("[data-member-pricing-plan-link]");
    var planCards = root.querySelectorAll("[data-member-pricing-plan]");
    var stageScroller = root.querySelector("[data-member-pricing-stage-options]");

    function getEmail() {
      if (identityEmailInput) {
        return identityEmailInput.value.trim();
      }
      return emailInput ? emailInput.value.trim() : "";
    }

    function getFullName() {
      return identityNameInput ? identityNameInput.value.trim() : "";
    }

    function validateEmail() {
      var email = getEmail();
      var valid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
      if (error) {
        error.hidden = valid;
      }
      return valid;
    }

    function validateIdentity() {
      var fullName = getFullName();
      var email = getEmail();
      var valid = fullName.length >= 2 && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
      if (identityError) {
        identityError.hidden = valid;
      }
      return valid;
    }

    if (form) {
      form.addEventListener("submit", function (event) {
        event.preventDefault();
        if (!validateEmail()) {
          if (emailInput) {
            emailInput.focus();
          }
          return;
        }

        setSignupIdentity(getFullName(), getEmail());
        showStep(root, 2);
      });
    }

    if (identityForm) {
      identityForm.addEventListener("submit", function (event) {
        event.preventDefault();
        if (!validateIdentity()) {
          if (!getFullName() && identityNameInput) {
            identityNameInput.focus();
          } else if (identityEmailInput) {
            identityEmailInput.focus();
          }
          return;
        }

        setSignupIdentity(getFullName(), getEmail());
        showStep(root, 3);
      });
    }

    root.addEventListener("click", function (event) {
      var stageButton = event.target.closest("[data-member-pricing-stage]");
      var stageScrollButton = event.target.closest("[data-member-pricing-stage-scroll]");
      var cycleButton = event.target.closest("[data-member-pricing-cycle-toggle]");

      if (stageScrollButton && stageScroller) {
        event.preventDefault();
        stageScroller.scrollBy({
          left:
            stageScrollButton.getAttribute("data-member-pricing-stage-scroll") === "prev"
              ? -260
              : 260,
          behavior: "smooth",
        });
        return;
      }

      if (cycleButton && root.contains(cycleButton)) {
        var cycle = cycleButton.getAttribute("data-member-pricing-cycle-toggle") || "monthly";
        var plansShell = cycleButton.closest("[data-member-pricing-plan-cycle]");

        event.preventDefault();
        if (plansShell) {
          plansShell.setAttribute("data-member-pricing-plan-cycle", cycle);
          plansShell.querySelectorAll("[data-member-pricing-cycle-toggle]").forEach(function (button) {
            button.classList.toggle(
              "is-active",
              button.getAttribute("data-member-pricing-cycle-toggle") === cycle
            );
          });
          plansShell.querySelectorAll("[data-member-pricing-plans]").forEach(function (group) {
            group.classList.toggle(
              "is-active",
              group.getAttribute("data-member-pricing-plans") === cycle
            );
          });
        }
        return;
      }

      if (stageButton && root.contains(stageButton)) {
        event.preventDefault();
        root.setAttribute(
          "data-member-pricing-career-stage",
          stageButton.getAttribute("data-member-pricing-stage") || ""
        );
        showStep(root, 2);
      }
    });

    if (nextButton) {
      nextButton.addEventListener("click", function () {
        setSignupIdentity(getFullName(), getEmail());
        showStep(root, 3);
      });
    }

    backButtons.forEach(function (button) {
      button.addEventListener("click", function () {
        var currentStep = Number(root.getAttribute("data-active-step") || "1");
        showStep(root, Math.max(1, currentStep - 1));
      });
    });

    planCards.forEach(function (card) {
      card.addEventListener("click", function (event) {
        if (event.target.closest("[data-member-pricing-plan-link]")) {
          return;
        }

        planCards.forEach(function (entry) {
          entry.classList.remove("is-selected");
        });
        card.classList.add("is-selected");
      });
    });

    planLinks.forEach(function (link) {
      link.addEventListener("click", function () {
        setSignupIdentity(getFullName(), getEmail());
      });
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("[data-member-pricing]").forEach(init);
  });
})();
