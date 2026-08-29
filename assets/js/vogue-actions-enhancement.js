/**
 * Vogue Actions Enhancement
 * Adds icons, improved interactions, and state management to redesigned buttons
 * @since 10.5.0
 */

(function ($) {
  "use strict";

  $(document).ready(function () {
    class VogueActionsEnhancement {
      constructor() {
        this.init();
        this.appliedJobs = this.loadAppliedJobs();
        this.savedJobs = this.loadSavedJobs();
      }

      init() {
        this.enhanceButtons();
        this.attachEventHandlers();
        this.updateButtonStates();
        this.injectSVGIcons();
      }

      /**
       * Inject SVG icons into buttons for better visuals
       */
      injectSVGIcons() {
        // Apply button - Arrow/Send icon
        const applyIcon = `<svg class="vogue-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="22" y1="2" x2="11" y2="13"></line>
                    <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                </svg>`;

        // Save button - Bookmark icon
        const saveIcon = `<svg class="vogue-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
                </svg>`;

        // Saved state - Filled bookmark
        const savedIcon = `<svg class="vogue-icon" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2.5">
                    <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
                </svg>`;

        // Tailor CV - Document edit icon
        const tailorIcon = `<svg class="vogue-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>`;

        // Update buttons with icons
        $('.vogue-action-btn[title="Express Interest"]').each(function () {
          if (!$(this).find(".vogue-icon").length) {
            $(this).prepend(applyIcon);
          }
        });

        $('.vogue-action-btn[title="Save"]').each(function () {
          const jobId = $(this).data("job-id");
          const isSaved = $(this).hasClass("saved");

          if (!$(this).find(".vogue-icon").length) {
            $(this).prepend(isSaved ? savedIcon : saveIcon);
          }
        });

        $('.vogue-action-btn[title="Tailor CV"]').each(function () {
          if (!$(this).find(".vogue-icon").length) {
            $(this).prepend(tailorIcon);
          }
        });
      }

      /**
       * Enhance button markup and attributes
       */
      enhanceButtons() {
        $(".vogue-action-btn").each(function () {
          const $btn = $(this);

          // Add ARIA labels for accessibility
          if (!$btn.attr("aria-label")) {
            $btn.attr("aria-label", $btn.attr("title"));
          }

          // Add role for better screen reader support
          $btn.attr("role", "button");

          // Add tabindex for keyboard navigation
          if (!$btn.attr("tabindex")) {
            $btn.attr("tabindex", "0");
          }

          // Wrap text in span if not already wrapped
          if (!$btn.find("span").length) {
            const text = $btn.text().trim();
            $btn.html(`<span>${text}</span>`);
          }
        });
      }

      /**
       * Attach event handlers with improved feedback
       */
      attachEventHandlers() {
        const self = this;

        // ✅ Apply button handler - Fixed to use application URL
        $(document).on(
          "click",
          '.vogue-action-btn[title="Express Interest"]',
          function (e) {
            e.preventDefault();
            e.stopPropagation();

            const $btn = $(this);
            const jobId = $btn.data("job-id");
            const applicationUrl = $btn.data("url");

            // 🧠 Open application URL in a new tab if available
            if (applicationUrl && applicationUrl !== "") {
              window.open(applicationUrl, "_blank");
            } else {
              console.warn(`No application URL found for job ${jobId}`);
            }

            // Visual feedback
            self.setButtonLoading($btn, "Applying...");

            // Optional: Simulate applied state for UX
            setTimeout(() => {
              self.setButtonSuccess($btn, "Applied");
              self.markJobAsApplied(jobId);

              setTimeout(() => {
                $btn.removeClass("success").prop("disabled", true);
                $btn.find("span").text("Applied");
              }, 2000);
            }, 1000);
          }
        );

        // Save/Bookmark button handler (formerly Track)
        $(document).on(
          "click",
          '.vogue-action-btn[title="Save"]:not(.saved)',
          function (e) {
            e.preventDefault();
            e.stopPropagation();

            const $btn = $(this);
            const jobId = $btn.data("job-id");

            // Visual feedback
            self.setButtonLoading($btn, "Saving...");

            // Save action
            setTimeout(() => {
              // Save to localStorage or user preferences
              self.saveJob(jobId);

              // Update to saved state
              $btn.removeClass("saving").addClass("saved");
              $btn.find("span").text("Saved");

              // Update icon to filled bookmark
              const savedIcon = `<svg class="vogue-icon" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2.5">
                            <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
                        </svg>`;
              $btn.find(".vogue-icon").replaceWith(savedIcon);

              // Show success briefly
              $btn.addClass("success");
              setTimeout(() => {
                $btn.removeClass("success");
              }, 1000);
            }, 800);
          }
        );

        // Tailor CV button handler
        $(document).on(
          "click",
          '.vogue-action-btn[title="Tailor CV"]',
          function (e) {
            e.preventDefault();
            e.stopPropagation();

            const $btn = $(this);
            const jobId = $btn.data("job-id");

            // Visual feedback
            self.setButtonLoading($btn, "Loading...");

            setTimeout(() => {
              if (window.tailorCV) {
                window.tailorCV(jobId);
              }

              $btn.removeClass("processing");
              $btn.find("span").text("Tailor CV");
            }, 1000);
          }
        );

        // Keyboard support
        $(document).on("keydown", ".vogue-action-btn", function (e) {
          if (e.key === "Enter" || e.key === " ") {
            e.preventDefault();
            $(this).click();
          }
        });

        // Add ripple effect on click
        $(document).on("mousedown", ".vogue-action-btn", function (e) {
          const $btn = $(this);
          const ripple = $('<span class="vogue-ripple"></span>');

          $btn.append(ripple);

          const size = Math.max($btn.outerWidth(), $btn.outerHeight());
          const x = e.pageX - $btn.offset().left - size / 2;
          const y = e.pageY - $btn.offset().top - size / 2;

          ripple
            .css({
              width: size,
              height: size,
              top: y + "px",
              left: x + "px",
            })
            .addClass("vogue-ripple-animate");

          setTimeout(() => {
            ripple.remove();
          }, 600);
        });
      }

      /**
       * Set button to loading state
       */
      setButtonLoading($btn, text = "Loading...") {
        $btn.addClass("processing");
        $btn.find("span").text(text);
        $btn.prop("disabled", true);
      }

      /**
       * Set button to success state
       */
      setButtonSuccess($btn, text = "Success") {
        $btn.removeClass("processing").addClass("success");
        $btn.find("span").text(text);
      }

      /**
       * Update button states based on stored data
       */
      updateButtonStates() {
        const self = this;

        // Update saved buttons
        $('.vogue-action-btn[title="Save"]').each(function () {
          const jobId = $(this).data("job-id");
          if (self.savedJobs[jobId]) {
            $(this).addClass("saved");
            $(this).find("span").text("Saved");

            // Update icon
            const savedIcon = `<svg class="vogue-icon" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2.5">
                            <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
                        </svg>`;
            $(this).find(".vogue-icon").replaceWith(savedIcon);
          }
        });

        // Update applied buttons
        $('.vogue-action-btn[title="Express Interest"]').each(function () {
          const jobId = $(this).data("job-id");
          if (self.appliedJobs[jobId]) {
            $(this).prop("disabled", true);
            $(this).find("span").text("Applied");
          }
        });
      }

      /**
       * Save job
       */
      saveJob(jobId) {
        this.savedJobs[jobId] = {
          timestamp: new Date().toISOString(),
          status: "saved",
        };
        this.saveSavedJobs();
      }

      /**
       * Mark job as applied
       */
      markJobAsApplied(jobId) {
        this.appliedJobs[jobId] = {
          timestamp: new Date().toISOString(),
          status: "applied",
        };
        this.saveAppliedJobs();

        // Also save the job
        this.saveJob(jobId);
      }

      /**
       * Load saved jobs from localStorage
       */
      loadSavedJobs() {
        try {
          return JSON.parse(localStorage.getItem("vogue_saved_jobs") || "{}");
        } catch (e) {
          return {};
        }
      }

      /**
       * Save saved jobs to localStorage
       */
      saveSavedJobs() {
        localStorage.setItem(
          "vogue_saved_jobs",
          JSON.stringify(this.savedJobs)
        );
      }

      /**
       * Load applied jobs from localStorage
       */
      loadAppliedJobs() {
        try {
          return JSON.parse(localStorage.getItem("vogue_applied_jobs") || "{}");
        } catch (e) {
          return {};
        }
      }

      /**
       * Save applied jobs to localStorage
       */
      saveAppliedJobs() {
        localStorage.setItem(
          "vogue_applied_jobs",
          JSON.stringify(this.appliedJobs)
        );
      }
    }

    // Expose class globally
    window.VogueActionsEnhancement = VogueActionsEnhancement;

    // Initialize enhancement
    window.vogueActionsEnhancement = new VogueActionsEnhancement();

    // Re-initialize when new job cards are loaded
    $(document).on("sffc:jobs_loaded", function () {
      window.vogueActionsEnhancement.init();
    });
  });
})(jQuery);
