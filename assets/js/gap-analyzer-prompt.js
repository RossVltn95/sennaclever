(function () {
  "use strict";

  function bindPrompt(form) {
    if (!form) {
      return;
    }

    var input = form.querySelector("[data-gap-prompt-input]");
    var submit = form.querySelector("[data-gap-prompt-submit]");
    var hint = form.querySelector("[data-gap-prompt-hint]");
    var titleInput = form.querySelector('input[name="senna_gap_prefill_job_title"]');

    if (!input || !submit) {
      return;
    }

    function updateState() {
      var value = (input.value || "").trim();
      var ready = value.length >= 100;

      submit.disabled = value.length === 0;

      if (!hint) {
        return;
      }

      if (ready) {
        hint.textContent = "Role loaded next. You can add your CV on the next screen.";
      } else if (value.length > 0) {
        hint.textContent = "Paste more of the job description so MENA Careers can read the role properly.";
      } else {
        hint.textContent = "Paste the full role brief to continue.";
      }
    }

    function inferJobTitle(text) {
      var lines = String(text || "")
        .split(/\r\n|\n|\r/)
        .map(function (line) {
          return line.trim();
        })
        .filter(Boolean);

      var blacklist = {
        "about the role": true,
        "job description": true,
        "job summary": true,
        responsibilities: true,
        requirements: true,
        qualifications: true,
        "about us": true,
        overview: true
      };

      for (var i = 0; i < lines.length; i += 1) {
        var candidate = lines[i];
        var normalized = candidate.toLowerCase();

        if (candidate.length > 90) {
          continue;
        }

        if (blacklist[normalized]) {
          continue;
        }

        if (candidate.slice(-1) === ":") {
          continue;
        }

        return candidate;
      }

      return "";
    }

    input.addEventListener("input", updateState);
    form.addEventListener("submit", function () {
      if (!titleInput) {
        return;
      }

      if ((titleInput.value || "").trim() !== "") {
        return;
      }

      var inferred = inferJobTitle(input.value || "");
      if (inferred) {
        titleInput.value = inferred;
      }
    });
    updateState();
  }

  document.addEventListener("DOMContentLoaded", function () {
    var forms = document.querySelectorAll("[data-gap-prompt-form]");
    forms.forEach(bindPrompt);
  });
})();
