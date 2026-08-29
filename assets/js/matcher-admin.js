/**
 * Matcher Admin JavaScript
 * Handles AJAX interactions for the matcher settings admin page
 */

jQuery(document).ready(function ($) {
  // Test Emily Message
  $("#test-emily-message").on("click", function () {
    const button = $(this);
    const originalText = button.text();

    button.prop("disabled", true).text("Sending...");

    $.ajax({
      url: sffc_matcher_admin.ajax_url,
      type: "POST",
      data: {
        action: "sffc_test_emily_message",
        nonce: sffc_matcher_admin.nonce,
      },
      success: function (response) {
        if (response.success) {
          showNotice(sffc_matcher_admin.messages.test_success, "success");
        } else {
          showNotice(
            response.data.message || sffc_matcher_admin.messages.test_error,
            "error"
          );
        }
      },
      error: function () {
        showNotice(sffc_matcher_admin.messages.test_error, "error");
      },
      complete: function () {
        button.prop("disabled", false).text(originalText);
      },
    });
  });

  // Test Claude API
  $("#test-claude-api").on("click", function () {
    const button = $(this);
    const originalText = button.text();

    button.prop("disabled", true).text("Testing...");

    $.ajax({
      url: sffc_matcher_admin.ajax_url,
      type: "POST",
      data: {
        action: "sffc_test_claude_api",
        nonce: sffc_matcher_admin.nonce,
      },
      success: function (response) {
        if (response.success) {
          showNotice("Claude API connection successful!", "success");
        } else {
          showNotice(
            response.data.message || "Claude API test failed",
            "error"
          );
        }
      },
      error: function () {
        showNotice("Failed to test Claude API", "error");
      },
      complete: function () {
        button.prop("disabled", false).text(originalText);
      },
    });
  });

  // Send Onboarding Messages
  $("#send-onboarding-batch").on("click", function () {
    const button = $(this);
    const originalText = button.text();

    if (
      !confirm(
        "Send onboarding messages to all users who haven't received them? This action cannot be undone."
      )
    ) {
      return;
    }

    button.prop("disabled", true).text("Sending...");

    $.ajax({
      url: sffc_matcher_admin.ajax_url,
      type: "POST",
      data: {
        action: "sffc_send_onboarding_message",
        nonce: sffc_matcher_admin.nonce,
      },
      success: function (response) {
        if (response.success) {
          showNotice(
            response.data.message ||
              sffc_matcher_admin.messages.onboarding_success,
            "success"
          );
        } else {
          showNotice(
            response.data.message || "Failed to send onboarding messages.",
            "error"
          );
        }
      },
      error: function () {
        showNotice("Failed to send onboarding messages.", "error");
      },
      complete: function () {
        button.prop("disabled", false).text(originalText);
      },
    });
  });

  // Real-time settings updates
  $("#matcher-settings-form").on("change", 'input[type="range"]', function () {
    const range = $(this);
    const output = range.next("output");
    output.text(range.val() + "%");
  });

  // Form validation
  $("#matcher-settings-form").on("submit", function (e) {
    let isValid = true;

    // Validate minimum match score
    const minScore = parseInt(
      $('input[name="sffc_matcher_options[min_match_score]"]').val()
    );
    if (minScore < 50 || minScore > 95) {
      showNotice("Minimum match score must be between 50% and 95%", "error");
      isValid = false;
    }

    // Validate daily message limit
    const dailyLimit = parseInt(
      $('select[name="sffc_matcher_options[daily_message_limit]"]').val()
    );
    if (dailyLimit < 1 || dailyLimit > 20) {
      showNotice("Daily message limit must be between 1 and 20", "error");
      isValid = false;
    }

    if (!isValid) {
      e.preventDefault();
    }
  });

  // Auto-save for quick settings
  $(".quick-setting").on("change", function () {
    const setting = $(this);
    const settingName = setting.attr("name");
    const settingValue = setting.is(":checkbox")
      ? setting.is(":checked")
        ? 1
        : 0
      : setting.val();

    $.ajax({
      url: sffc_matcher_admin.ajax_url,
      type: "POST",
      data: {
        action: "sffc_update_match_settings",
        nonce: sffc_matcher_admin.nonce,
        [settingName.replace("sffc_matcher_options[", "").replace("]", "")]:
          settingValue,
      },
      success: function (response) {
        if (response.success) {
          showNotice("Setting updated successfully", "success", 2000);
        }
      },
    });
  });

  // Personality preview
  $('select[name="sffc_matcher_options[emily_personality]"]').on(
    "change",
    function () {
      const personality = $(this).val();
      showPersonalityPreview(personality);
    }
  );

  // Custom greeting preview
  $('textarea[name="sffc_matcher_options[custom_greeting]"]').on(
    "input",
    function () {
      const greeting = $(this).val();
      showGreetingPreview(greeting);
    }
  );

  // Health status refresh
  $(".health-refresh").on("click", function () {
    location.reload();
  });

  // Helper functions
  function showNotice(message, type, duration) {
    type = type || "info";
    duration = duration || 5000;

    const noticeClass = "notice notice-" + type + " is-dismissible";
    const notice = $(
      '<div class="' + noticeClass + '"><p>' + message + "</p></div>"
    );

    $(".wrap h1").after(notice);

    if (duration > 0) {
      setTimeout(function () {
        notice.fadeOut();
      }, duration);
    }

    // Make dismissible work
    notice.on("click", ".notice-dismiss", function () {
      notice.fadeOut();
    });
  }

  function showPersonalityPreview(personality) {
    const previews = {
      professional_warm:
        'Professional & Warm: "Hello [Name], I\'m Emily Bradshaw, your dedicated Career Success Manager..."',
      friendly_casual:
        'Friendly & Casual: "Hello [Name]! 👋 Emily here from senna! So glad you\'ve joined us..."',
      executive_formal:
        'Executive & Formal: "Hello [Name], I am Emily Bradshaw, Career Success Manager at senna..."',
      mentor_supportive:
        "Mentor & Supportive: \"Hello [Name], I'm Emily Bradshaw, and I'm genuinely thrilled to be part of your career journey...\"",
    };

    const preview = previews[personality] || "";

    if (preview) {
      let previewDiv = $("#personality-preview");
      if (previewDiv.length === 0) {
        previewDiv = $(
          '<div id="personality-preview" style="margin-top: 10px; padding: 10px; background: #f9f9f9; border-left: 4px solid #2271b1; font-style: italic;"></div>'
        );
        $('select[name="sffc_matcher_options[emily_personality]"]')
          .parent()
          .append(previewDiv);
      }
      previewDiv.text(preview);
    }
  }

  function showGreetingPreview(greeting) {
    if (greeting) {
      const previewText = greeting.replace("{first_name}", "John");
      let previewDiv = $("#greeting-preview");
      if (previewDiv.length === 0) {
        previewDiv = $(
          '<div id="greeting-preview" style="margin-top: 10px; padding: 10px; background: #f0f8ff; border-left: 4px solid #2271b1;"><strong>Preview:</strong> <span class="preview-text"></span></div>'
        );
        $('textarea[name="sffc_matcher_options[custom_greeting]"]')
          .parent()
          .append(previewDiv);
      }
      previewDiv.find(".preview-text").text(previewText);
    } else {
      $("#greeting-preview").remove();
    }
  }

  // Initialize on page load
  if ($('select[name="sffc_matcher_options[emily_personality]"]').length) {
    showPersonalityPreview(
      $('select[name="sffc_matcher_options[emily_personality]"]').val()
    );
  }

  if ($('textarea[name="sffc_matcher_options[custom_greeting]"]').val()) {
    showGreetingPreview(
      $('textarea[name="sffc_matcher_options[custom_greeting]"]').val()
    );
  }

  // Enhanced tooltips for complex settings
  $(".setting-tooltip")
    .on("mouseenter", function () {
      const tooltip = $(this);
      const title = tooltip.attr("title");
      if (title) {
        tooltip.attr("data-original-title", title).removeAttr("title");

        const tooltipDiv = $('<div class="admin-tooltip">' + title + "</div>");
        tooltipDiv.css({
          position: "absolute",
          background: "#333",
          color: "white",
          padding: "8px",
          borderRadius: "4px",
          fontSize: "12px",
          maxWidth: "300px",
          zIndex: 9999,
          boxShadow: "0 2px 8px rgba(0,0,0,0.2)",
        });

        $("body").append(tooltipDiv);

        const offset = tooltip.offset();
        tooltipDiv.css({
          top: offset.top - tooltipDiv.outerHeight() - 10,
          left:
            offset.left +
            tooltip.outerWidth() / 2 -
            tooltipDiv.outerWidth() / 2,
        });
      }
    })
    .on("mouseleave", function () {
      $(".admin-tooltip").remove();
      const tooltip = $(this);
      if (tooltip.attr("data-original-title")) {
        tooltip
          .attr("title", tooltip.attr("data-original-title"))
          .removeAttr("data-original-title");
      }
    });

  // Statistics auto-refresh
  setInterval(function () {
    if ($(".stat-box").length > 0) {
      refreshStatistics();
    }
  }, 30000); // Refresh every 30 seconds

  function refreshStatistics() {
    $.ajax({
      url: sffc_matcher_admin.ajax_url,
      type: "POST",
      data: {
        action: "sffc_get_statistics",
        nonce: sffc_matcher_admin.nonce,
      },
      success: function (response) {
        if (response.success && response.data.stats) {
          updateStatisticsDisplay(response.data.stats);
        }
      },
    });
  }

  function updateStatisticsDisplay(stats) {
    if (stats.matching) {
      $(".stat-box").each(function () {
        const box = $(this);
        const title = box.find("h3").text().toLowerCase();

        if (title.includes("match messages")) {
          box
            .find(".stat-number")
            .text(
              Number(stats.matching.total_matches_sent || 0).toLocaleString()
            );
        } else if (title.includes("today")) {
          box
            .find(".stat-number")
            .text(Number(stats.matching.matches_today || 0).toLocaleString());
        } else if (title.includes("average")) {
          box
            .find(".stat-number")
            .text(Math.round(stats.matching.avg_match_score || 0) + "%");
        } else if (title.includes("response")) {
          box
            .find(".stat-number")
            .text(Math.round(stats.matching.response_rate || 0) + "%");
        }
      });
    }
  }
});
