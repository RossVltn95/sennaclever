(function () {
  "use strict";

  function init(root) {
    var toggle = root.querySelector("[data-sffc-custom-login-password-toggle]");
    var input = root.querySelector('.sffc-custom-login__password-shell input[name="pwd"]');
    var panelTriggers = root.querySelectorAll("[data-sffc-custom-login-show-panel]");
    var panels = root.querySelectorAll("[data-sffc-custom-login-panel]");
    var dialogTriggers = root.querySelectorAll("[data-sffc-custom-login-dialog-open]");
    var dialogs = root.querySelectorAll("[data-sffc-custom-login-dialog]");

    function showPanel(panelName) {
      if (!panelName) {
        return;
      }

      root.dataset.activePanel = panelName;
      panels.forEach(function (panel) {
        var isActive = panel.getAttribute("data-sffc-custom-login-panel") === panelName;
        panel.hidden = !isActive;
        panel.classList.toggle("is-active", isActive);
      });

      var firstField = root.querySelector('[data-sffc-custom-login-panel="' + panelName + '"] input, [data-sffc-custom-login-panel="' + panelName + '"] select, [data-sffc-custom-login-panel="' + panelName + '"] textarea');
      if (firstField && typeof firstField.focus === "function") {
        firstField.focus({ preventScroll: true });
      }
    }

    if (toggle && input) {
      toggle.addEventListener("click", function () {
        var isVisible = input.getAttribute("type") === "text";
        input.setAttribute("type", isVisible ? "password" : "text");
        toggle.textContent = isVisible ? "Show" : "Hide";
      });
    }

    panelTriggers.forEach(function (trigger) {
      trigger.addEventListener("click", function () {
        showPanel(trigger.getAttribute("data-sffc-custom-login-show-panel"));
      });
    });

    function closeDialogs() {
      dialogs.forEach(function (dialog) {
        dialog.hidden = true;
      });
    }

    dialogTriggers.forEach(function (trigger) {
      trigger.addEventListener("click", function () {
        var dialogName = trigger.getAttribute("data-sffc-custom-login-dialog-open");
        var dialog = root.querySelector('[data-sffc-custom-login-dialog="' + dialogName + '"]');
        closeDialogs();
        if (dialog) {
          dialog.hidden = false;
          var closeButton = dialog.querySelector("[data-sffc-custom-login-dialog-close]");
          if (closeButton && typeof closeButton.focus === "function") {
            closeButton.focus({ preventScroll: true });
          }
        }
      });
    });

    dialogs.forEach(function (dialog) {
      dialog.addEventListener("click", function (event) {
        if (event.target === dialog || event.target.closest("[data-sffc-custom-login-dialog-close]")) {
          closeDialogs();
        }
      });
    });

    root.addEventListener("keydown", function (event) {
      if (event.key === "Escape") {
        closeDialogs();
      }
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".sffc-custom-login").forEach(init);
  });
})();
