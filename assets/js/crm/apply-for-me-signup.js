(function () {
  function q(root, selector) {
    return root ? root.querySelector(selector) : null;
  }

  function revealCheckout(root, trigger, checkout) {
    var label;
    var firstField;

    if (!root || !trigger || !checkout) {
      return;
    }

    checkout.hidden = false;
    root.classList.add("is-checkout-open");
    label =
      trigger.getAttribute("data-sffc-personalized-signup-close-label") ||
      "Back";
    trigger.textContent = label;
    trigger.setAttribute("data-sffc-personalized-signup-expanded", "1");

    window.setTimeout(function () {
      checkout.scrollIntoView({ behavior: "smooth", block: "start" });
      firstField = q(
        checkout,
        "input:not([type='hidden']):not([disabled]), select:not([disabled]), textarea:not([disabled])"
      );
      if (firstField && typeof firstField.focus === "function") {
        firstField.focus();
      }
    }, 80);
  }

  function collapseCheckout(root, trigger, checkout) {
    var label;

    if (!root || !trigger || !checkout) {
      return;
    }

    checkout.hidden = true;
    root.classList.remove("is-checkout-open");
    label =
      trigger.getAttribute("data-sffc-personalized-signup-open-label") ||
      "Continue";
    trigger.textContent = label;
    trigger.removeAttribute("data-sffc-personalized-signup-expanded");

    window.setTimeout(function () {
      trigger.scrollIntoView({ behavior: "smooth", block: "center" });
      if (typeof trigger.focus === "function") {
        trigger.focus();
      }
    }, 60);
  }

  function init(root) {
    var trigger;
    var checkout;

    if (!root || root._sffcApplyForMeReady) {
      return;
    }
    root._sffcApplyForMeReady = true;

    trigger = q(root, "[data-sffc-personalized-signup-continue]");
    checkout = q(root, "[data-sffc-personalized-signup-checkout]");

    if (!trigger || !checkout) {
      return;
    }

    trigger.addEventListener("click", function () {
      if (trigger.getAttribute("data-sffc-personalized-signup-expanded") === "1") {
        collapseCheckout(root, trigger, checkout);
        return;
      }
      revealCheckout(root, trigger, checkout);
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    Array.prototype.slice
      .call(document.querySelectorAll("[data-sffc-personalized-signup]"))
      .forEach(init);
  });
})();
