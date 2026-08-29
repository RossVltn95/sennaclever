(function () {
  function qa(root, selector) {
    return Array.prototype.slice.call(
      root ? root.querySelectorAll(selector) : []
    );
  }

  function q(root, selector) {
    return root ? root.querySelector(selector) : null;
  }

  function escapeSelector(value) {
    if (window.CSS && typeof window.CSS.escape === "function") {
      return window.CSS.escape(value);
    }
    return String(value || "").replace(/["\\]/g, "\\$&");
  }

  function showPlan(root, button) {
    var slug = button.getAttribute("data-plan-slug") || "";
    var checkout = q(root, "[data-sffc-mena-pricing-checkout]");
    var title = q(root, "[data-sffc-mena-pricing-checkout-title]");
    var copy = q(root, "[data-sffc-mena-pricing-checkout-copy]");
    var price = q(root, "[data-sffc-mena-pricing-checkout-price]");
    var external = q(root, "[data-sffc-mena-pricing-external]");
    var externalLink = q(root, "[data-sffc-mena-pricing-external-link]");
    var selectedForm = slug
      ? q(root, '[data-sffc-mena-pricing-form="' + escapeSelector(slug) + '"]')
      : null;

    qa(root, "[data-sffc-mena-pricing-card]").forEach(function (card) {
      card.classList.toggle(
        "is-selected",
        card.getAttribute("data-plan-slug") === slug
      );
    });

    qa(root, "[data-sffc-mena-pricing-select]").forEach(function (select) {
      select.setAttribute(
        "aria-pressed",
        select.getAttribute("data-plan-slug") === slug ? "true" : "false"
      );
    });

    qa(root, "[data-sffc-mena-pricing-form]").forEach(function (form) {
      form.hidden = form !== selectedForm;
      form.classList.toggle("is-active", form === selectedForm);
    });

    if (external) {
      external.hidden = !!selectedForm;
    }
    if (externalLink) {
      externalLink.href = button.getAttribute("data-plan-url") || "#";
    }
    if (title) {
      title.textContent = button.getAttribute("data-plan-name") || "Selected plan";
    }
    if (copy) {
      copy.textContent =
        button.getAttribute("data-plan-tagline") ||
        "Complete your secure MemberPress checkout below.";
    }
    if (price) {
      price.textContent = button.getAttribute("data-plan-price") || "";
    }
    if (checkout) {
      checkout.hidden = false;
      checkout.classList.add("is-active");
      window.setTimeout(function () {
        checkout.scrollIntoView({ behavior: "smooth", block: "start" });
      }, 50);
    }
  }

  function showWorkflow(root, button) {
    var key = button.getAttribute("data-sffc-mena-pricing-workflow-tab") || "";
    if (!key) {
      return;
    }

    qa(root, "[data-sffc-mena-pricing-workflow-tab]").forEach(function (tab) {
      var isActive =
        tab.getAttribute("data-sffc-mena-pricing-workflow-tab") === key;
      tab.classList.toggle("is-active", isActive);
      tab.setAttribute("aria-selected", isActive ? "true" : "false");
      tab.tabIndex = isActive ? 0 : -1;
    });

    qa(root, "[data-sffc-mena-pricing-workflow-panel]").forEach(function (panel) {
      var isActive =
        panel.getAttribute("data-sffc-mena-pricing-workflow-panel") === key;
      panel.hidden = !isActive;
      panel.classList.toggle("is-active", isActive);
    });
  }

  function moveWorkflowFocus(root, current, direction) {
    var tabs = qa(root, "[data-sffc-mena-pricing-workflow-tab]");
    var currentIndex = tabs.indexOf(current);
    if (currentIndex < 0 || !tabs.length) {
      return;
    }
    var nextIndex = (currentIndex + direction + tabs.length) % tabs.length;
    tabs[nextIndex].focus();
    showWorkflow(root, tabs[nextIndex]);
  }

  function init(root) {
    if (!root || root._sffcMenaPricingReady) {
      return;
    }
    root._sffcMenaPricingReady = true;

    qa(root, "[data-sffc-mena-pricing-select]").forEach(function (button) {
      button.addEventListener("click", function (event) {
        event.preventDefault();
        showPlan(root, button);
      });
    });

    qa(root, "[data-sffc-mena-pricing-workflow-tab]").forEach(function (button) {
      if (!button.classList.contains("is-active")) {
        button.tabIndex = -1;
      }
      button.addEventListener("click", function (event) {
        event.preventDefault();
        showWorkflow(root, button);
      });
      button.addEventListener("keydown", function (event) {
        if (event.key === "ArrowRight" || event.key === "ArrowDown") {
          event.preventDefault();
          moveWorkflowFocus(root, button, 1);
        } else if (event.key === "ArrowLeft" || event.key === "ArrowUp") {
          event.preventDefault();
          moveWorkflowFocus(root, button, -1);
        }
      });
    });
  }

  function boot() {
    qa(document, "[data-sffc-mena-pricing-tiers]").forEach(init);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();
