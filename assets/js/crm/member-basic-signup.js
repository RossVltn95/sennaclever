(function () {
  function q(root, selector) {
    return root ? root.querySelector(selector) : null;
  }

  function qa(root, selector) {
    return Array.prototype.slice.call(
      root ? root.querySelectorAll(selector) : []
    );
  }

  function collectFieldState(root) {
    var values = {};

    qa(root, "input[name], select[name], textarea[name]").forEach(function (field) {
      var name = field.getAttribute("name");
      if (!name || field.type === "hidden" || field.type === "submit") {
        return;
      }

      if (
        (field.type === "checkbox" || field.type === "radio") &&
        !field.checked
      ) {
        return;
      }

      values[name] = field.value;
    });

    return values;
  }

  function applyFieldState(panel, values) {
    if (!panel || !values) {
      return;
    }

    Object.keys(values).forEach(function (name) {
      qa(panel, '[name="' + String(name).replace(/"/g, '\\"') + '"]').forEach(function (field) {
        if (field.type === "hidden" || field.type === "submit") {
          return;
        }

        if (field.type === "checkbox" || field.type === "radio") {
          field.checked = String(field.value) === String(values[name]);
          return;
        }

        field.value = values[name];
      });
    });
  }

  function init(root) {
    if (!root || root._sffcBasicMemberSignupReady) {
      return;
    }

    root._sffcBasicMemberSignupReady = true;

    var select = q(root, "[data-sffc-basic-signup-select]");
    var detailsWrap = q(root, "[data-sffc-basic-signup-details]");
    var check = q(root, "[data-sffc-basic-signup-check]");
    var formsWrap = q(root, "[data-sffc-basic-signup-forms]");
    var panels = qa(root, "[data-sffc-basic-signup-panel]");
    var details = qa(root, "[data-sffc-basic-signup-detail]");
    var accessCards = qa(root, "[data-sffc-basic-signup-access-card]");
    var sharedValues = {};
    var config = window.sffcMemberBasicSignup || {};

    if (!select) {
      return;
    }

    function syncSharedValues() {
      panels.forEach(function (panel) {
        var panelValues = collectFieldState(panel);
        Object.keys(panelValues).forEach(function (name) {
          if (panelValues[name] !== "") {
            sharedValues[name] = panelValues[name];
          }
        });
      });
    }

    function placeDetailsBelowPaymentMethods(activePanel) {
      var paymentMethods = q(activePanel, ".mepr-payment-methods-wrapper");
      var submitButton =
        q(activePanel, ".mp-submit") ||
        q(activePanel, ".mepr-submit") ||
        q(activePanel, 'input[type="submit"]') ||
        q(activePanel, 'button[type="submit"]');
      var submitRow = submitButton ? submitButton.closest(".mp-form-row, .mepr-form-row, p, div") : null;

      if (!detailsWrap || !activePanel) {
        return;
      }

      if (paymentMethods && paymentMethods.parentNode) {
        paymentMethods.parentNode.insertBefore(detailsWrap, paymentMethods.nextSibling);
        return;
      }

      if (submitRow && submitRow.parentNode) {
        submitRow.parentNode.insertBefore(detailsWrap, submitRow);
        return;
      }

      activePanel.appendChild(detailsWrap);
    }

    function showSelectedPlan(planId) {
      var activePanel = null;

      details.forEach(function (detail) {
        detail.hidden =
          (detail.getAttribute("data-sffc-basic-signup-detail") || "") !== planId;
      });

      panels.forEach(function (panel) {
        var isActive =
          (panel.getAttribute("data-sffc-basic-signup-panel") || "") === planId;
        panel.hidden = !isActive;
        if (isActive) {
          activePanel = panel;
        }
      });

      if (detailsWrap) {
        detailsWrap.hidden = !planId;
      }

      if (formsWrap) {
        formsWrap.hidden = !planId;
      }

      if (check) {
        check.hidden = !planId;
      }

      if (activePanel) {
        placeDetailsBelowPaymentMethods(activePanel);
        applyFieldState(activePanel, sharedValues);
      }

      accessCards.forEach(function (card) {
        var isSelected =
          (card.getAttribute("data-sffc-basic-signup-plan-target") || "") === planId;
        card.classList.toggle("is-selected", isSelected);
        card.setAttribute("aria-pressed", isSelected ? "true" : "false");
      });
    }

    function refreshCurrencyInBackground() {
      if (!window.fetch || !config.ajaxUrl || !config.currencyNonce) {
        return;
      }

      var timezone = "";
      var locale = "";

      try {
        timezone = Intl.DateTimeFormat().resolvedOptions().timeZone || "";
      } catch (error) {
        timezone = "";
      }

      locale =
        (navigator.languages && navigator.languages[0]) ||
        navigator.language ||
        "";

      if (!timezone && !locale) {
        return;
      }

      var body = new URLSearchParams();
      body.set("action", "sffc_member_basic_signup_currency");
      body.set("nonce", config.currencyNonce);
      body.set("timezone", timezone);
      body.set("locale", locale);

      fetch(config.ajaxUrl, {
        method: "POST",
        credentials: "same-origin",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
        },
        body: body.toString(),
      })
        .then(function (response) {
          return response.ok ? response.json() : null;
        })
        .then(function (payload) {
          if (!payload || !payload.success || !payload.data || !payload.data.plans) {
            return;
          }

          payload.data.plans.forEach(function (plan) {
            var id = String(plan.id || "");
            var option = q(
              root,
              '[data-sffc-basic-signup-option="' + id.replace(/"/g, '\\"') + '"]'
            );
            var price = q(
              root,
              '[data-sffc-basic-signup-price="' + id.replace(/"/g, '\\"') + '"]'
            );

            if (option && plan.option_label) {
              option.textContent = String(plan.option_label);
              if (option.getAttribute("data-sffc-basic-signup-current") === "1") {
                option.textContent += " (Current plan)";
              }
            }

            if (price && typeof plan.price !== "undefined") {
              price.textContent = String(plan.price || "");
            }
          });
        })
        .catch(function () {});
    }

    select.addEventListener("change", function () {
      syncSharedValues();
      showSelectedPlan(select.value || "");
    });

    accessCards.forEach(function (card) {
      card.addEventListener("click", function () {
        var planId = card.getAttribute("data-sffc-basic-signup-plan-target") || "";
        if (!planId) {
          return;
        }

        syncSharedValues();
        select.value = planId;
        select.dispatchEvent(new Event("change", { bubbles: true }));
      });
    });

    root.addEventListener(
      "input",
      function (event) {
        var field = event.target;
        if (!field || !field.name) {
          return;
        }
        if (field.type === "hidden" || field.type === "submit") {
          return;
        }

        if (field.type === "checkbox" || field.type === "radio") {
          if (field.checked) {
            sharedValues[field.name] = field.value;
          }
          return;
        }

        sharedValues[field.name] = field.value;
      },
      true
    );

    root.addEventListener(
      "change",
      function (event) {
        var field = event.target;
        if (!field || !field.name) {
          return;
        }
        if (field.type === "hidden" || field.type === "submit") {
          return;
        }

        if (field.type === "checkbox" || field.type === "radio") {
          if (field.checked) {
            sharedValues[field.name] = field.value;
          }
          return;
        }

        sharedValues[field.name] = field.value;
      },
      true
    );

    if (select.value) {
      showSelectedPlan(select.value);
    }

    if (window.requestIdleCallback) {
      window.requestIdleCallback(function () {
        refreshCurrencyInBackground();
      }, { timeout: 1600 });
    } else {
      window.setTimeout(function () {
        refreshCurrencyInBackground();
      }, 900);
    }
  }

  document.addEventListener("DOMContentLoaded", function () {
    qa(document, "[data-sffc-basic-signup]").forEach(init);
  });
})();
