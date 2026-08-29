(function ($) {
  "use strict";

  function initLinkedInCRM() {
    var $app = $(".sffc-crm-linkedin");
    var $eventRoot = $(document);

    var config = window.sffcCRMLinkedIn || {};
    var membershipUrl = config.membershipUrl || "";
    var joinUrl = config.joinUrl || config.loginUrl || "/join/";
    var criteriaLocked = $app.attr("data-criteria-locked") === "true";
    window.sffcUserData = window.sffcUserData || {};
    window.sffcUserData.isLoggedIn = !!config.isLoggedIn;
    if (typeof window.sffcUserData.hasMembership === "undefined") {
      window.sffcUserData.hasMembership = !!config.isPremium;
    }

    var sidebarStorageKey = "sffcCrmSidebarCollapsed";
    var sidebarCollapsed = false;
    try {
      sidebarCollapsed =
        window.localStorage &&
        window.localStorage.getItem(sidebarStorageKey) === "1";
    } catch (err) {
      sidebarCollapsed = false;
    }
    $app.toggleClass("sffc-crm-sidebar-collapsed", sidebarCollapsed);
    $app
      .find("[data-sidebar-toggle]")
      .attr("aria-expanded", sidebarCollapsed ? "false" : "true");

    // Tab activation function - MUST be defined before redirectToJoinTab
    function activateTab(tab) {
      if (!tab) {
        return;
      }
      if (tab === "matches") {
        tab = "console";
      }

      $app.toggleClass(
        "sffc-crm-linkedin--console-landing",
        tab === "console" && $app.find(".sffc-console-landing").length > 0
      );
      $app.find(".sffc-crm-linkedin-body").removeClass("sffc-crm-matches-dashboard-mode");

      // Update desktop tabs
      $app.find(".sffc-crm-tab").removeClass("active");
      $app.find('.sffc-crm-tab[data-tab="' + tab + '"]').addClass("active");

      // Update mobile tabs
      $app.find(".sffc-crm-mobile-tab").removeClass("active");
      $app
        .find('.sffc-crm-mobile-tab[data-tab="' + tab + '"]')
        .addClass("active");

      // Update panels
      $app.find(".sffc-crm-linkedin-panel").removeClass("active");
      $app
        .find('.sffc-crm-linkedin-panel[data-panel="' + tab + '"]')
        .addClass("active");

      // Update URL hash for deep linking
      if (history.pushState) {
        var nextParams = new URLSearchParams(window.location.search || "");
        nextParams.set("tab", tab);
        var newurl =
          window.location.protocol +
          "//" +
          window.location.host +
          window.location.pathname +
          "?" +
          nextParams.toString();
        window.history.pushState({ path: newurl }, "", newurl);
      }

      // Scroll to top on mobile
      if ($(window).width() <= 640) {
        window.scrollTo(0, 0);
      }

    }

    $eventRoot.on(
      "click",
      ".sffc-crm-linkedin a, .sffc-crm-linkedin button",
      function (e) {
        if (config.isLoggedIn) {
          return;
        }

        var $target = $(this);
        if (
          $target.hasClass("sffc-crm-tab") ||
          $target.hasClass("sffc-crm-mobile-tab") ||
          $target.hasClass("sffc-crm-mobile-me-item") ||
          $target.closest(".sffc-crm-tabs, .sffc-crm-mobile-nav, .sffc-user-menu").length
        ) {
          return;
        }

        if (
          $target.closest("[data-console-engine]").length ||
          $target.closest("[data-console-search-form]").length
        ) {
          return;
        }

        var targetHref = String(
          $target.attr("href") ||
            $target.closest("a[href]").attr("href") ||
            ""
        );
        if (targetHref.indexOf("/join") !== -1) {
          return;
        }

        e.preventDefault();
        e.stopImmediatePropagation();
        activateTab("console");
      }
    );

    function showMembershipModal() {
      if (
        window.sffcLiveExpert &&
        typeof window.sffcLiveExpert.openMembershipModal === "function"
      ) {
        window.sffcLiveExpert.openMembershipModal();
        return true;
      }

      var $toggle = $(".sffc-live-expert-toggle");
      if ($toggle.length) {
        $toggle.trigger("click");
        return true;
      }

      if (membershipUrl) {
        window.location.href = membershipUrl;
        return true;
      }

      return false;
    }

    function closeIntroMembershipModal() {
      var $modal = $(".sffc-crm-intro-membership-modal");
      if (!$modal.length) {
        return;
      }

      $modal.attr("aria-hidden", "true").removeClass("is-open");
      $("body").removeClass("sffc-crm-intro-membership-modal-open");

      setTimeout(function () {
        if ($modal.attr("aria-hidden") === "true") {
          $modal.remove();
        }
      }, 220);
    }

    function showIntroMembershipModal() {
      closeIntroMembershipModal();

      var modalHtml =
        "" +
        '<div class="sffc-crm-intro-membership-modal is-open" aria-hidden="false" data-intro-membership-modal>' +
        '  <div class="sffc-crm-intro-membership-modal__overlay" data-intro-membership-close></div>' +
        '  <div class="sffc-crm-intro-membership-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="sffc-crm-intro-membership-title">' +
        '    <button type="button" class="sffc-crm-intro-membership-modal__close" aria-label="Close" data-intro-membership-close>×</button>' +
        '    <p class="sffc-crm-intro-membership-modal__eyebrow">Premium recruiter introductions</p>' +
        '    <h3 id="sffc-crm-intro-membership-title">Unlock tailored investment recruiter introductions</h3>' +
        '    <p class="sffc-crm-intro-membership-modal__copy">Membership gives you a more selective introduction process built around recruiter fit, not volume outreach.</p>' +
        '    <div class="sffc-crm-intro-membership-modal__benefits">' +
        '      <div class="sffc-crm-intro-membership-modal__benefit">' +
        '        <span class="sffc-crm-intro-membership-modal__tick" aria-hidden="true">✓</span>' +
        "        <div><strong>Tailored application materials</strong><p>We send tailored application materials to recruiters based on your background and target opportunities.</p></div>" +
        "      </div>" +
        '      <div class="sffc-crm-intro-membership-modal__benefit">' +
        '        <span class="sffc-crm-intro-membership-modal__tick" aria-hidden="true">✓</span>' +
        "        <div><strong>Higher-quality introductions</strong><p>We only introduce you to recruiters where your skills and experience match what they are hiring for.</p></div>" +
        "      </div>" +
        "    </div>" +
        '    <div class="sffc-crm-intro-membership-modal__actions">' +
        '      <button type="button" class="sffc-crm-btn sffc-crm-btn-outline" data-intro-membership-close>Not now</button>' +
        '      <button type="button" class="sffc-crm-btn sffc-crm-btn-primary" data-intro-membership-upgrade>Upgrade to request intros</button>' +
        "    </div>" +
        "  </div>" +
        "</div>";

      $("body")
        .addClass("sffc-crm-intro-membership-modal-open")
        .append(modalHtml);

      return true;
    }

    function redirectToJoinTab() {
      activateTab("profile");
      setTimeout(function () {
        var $profilePanel = $app.find('[data-panel="profile"]');
        if ($profilePanel.length && $profilePanel.offset()) {
          var targetOffset = $profilePanel.offset().top - 80;
          if (!isNaN(targetOffset)) {
            $("html, body")
              .stop(true)
              .animate({ scrollTop: targetOffset }, 300);
          }
        }
      }, 50);
    }

    function ensurePremiumAccess(options) {
      var opts = options || {};

      if (!window.sffcUserData.isLoggedIn) {
        if (!opts.skipJoinRedirect) {
          redirectToJoinTab();
        }
        if (typeof promptLogin === "function") {
          promptLogin();
        }
        return false;
      }

      return true;
    }

    var activeCompanyFilter = "";
    var activeGroupFilter = "";
    var $feedGroupLabel = $app.find("[data-feed-group-label]");
    var $feedFilterForm = $app.find("[data-feed-filter]");
    var feedFilterState = {
      location: "",
      keywords: "",
      startDate: "",
      duration: "",
    };
    var feedGroupDefaultLabel = $feedGroupLabel.length
      ? $feedGroupLabel.data("defaultLabel") || $feedGroupLabel.text()
      : (config.strings && config.strings.feedDefaultLabel) ||
        "All opportunities";
    var currentFeedGroupLabel = feedGroupDefaultLabel;
    var hrLoadRequestActive = false;
    var $dashboardModalEl = $(".sffc-crm-dashboard-modal");
    var dashboardModal = null;
    var i18n = config.strings || {};
    var prepConfig = config.prep || {};

    function applyResourceLibraryFilters($library) {
      if (!$library || !$library.length) {
        return;
      }

      var query = String($library.find("[data-resource-search]").val() || "")
        .toLowerCase()
        .trim();
      var activeType =
        $library.find("[data-resource-filter].active").data("resource-filter") ||
        "all";
      var visibleCount = 0;
      var shownCount = 0;
      var pageSize = parseInt($library.data("resource-page-size"), 10) || 8;
      var visibleLimit =
        parseInt($library.data("resource-visible-limit"), 10) || pageSize;

      $library.find("[data-resource-card]").each(function () {
        var $card = $(this);
        var cardType = String($card.data("resource-type") || "");
        var haystack = String($card.data("resource-search-text") || "");
        var typeMatch = activeType === "all" || cardType === activeType;
        var queryMatch = !query || haystack.indexOf(query) !== -1;
        var isMatch = typeMatch && queryMatch;
        var isVisible = isMatch && shownCount < visibleLimit;

        $card.toggle(isVisible);
        if (isMatch) {
          visibleCount += 1;
        }
        if (isVisible) {
          shownCount += 1;
        }
      });

      $library.find("[data-resource-empty]").prop("hidden", visibleCount > 0);
      $library
        .find("[data-resource-load-more-wrap]")
        .prop("hidden", visibleCount <= visibleLimit || visibleCount === 0);
    }

    function resetResourceLibraryPagination($library) {
      if (!$library || !$library.length) {
        return;
      }

      var pageSize = parseInt($library.data("resource-page-size"), 10) || 8;
      $library.data("resource-visible-limit", pageSize);
    }

    function resolveResourceCardUrl($card) {
      var $library = $card.closest("[data-resource-library]");
      var isLoggedIn =
        String($library.data("resource-auth") || "") === "logged-in";
      var hasPaidAccess =
        String($library.data("resource-paid") || "") === "yes";
      var joinUrl = String($library.data("resource-join-url") || "/join/");
      var upgradeUrl = String(
        $library.data("resource-membership-url") || membershipUrl || "/memberships/"
      );
      var resourceUrl = String($card.data("resource-url") || "");

      if (!isLoggedIn) {
        return joinUrl;
      }

      return resourceUrl;
    }

    $eventRoot.on("input", "[data-resource-search]", function () {
      var $library = $(this).closest("[data-resource-library]");
      resetResourceLibraryPagination($library);
      applyResourceLibraryFilters($library);
    });

    $eventRoot.on("click", "[data-resource-filter]", function () {
      var $button = $(this);
      var $library = $button.closest("[data-resource-library]");
      $library.find("[data-resource-filter]").removeClass("active");
      $button.addClass("active");
      resetResourceLibraryPagination($library);
      applyResourceLibraryFilters($library);
    });

    $eventRoot.on("click", "[data-resource-load-more]", function () {
      var $button = $(this);
      var $library = $button.closest("[data-resource-library]");
      var pageSize = parseInt($library.data("resource-page-size"), 10) || 8;
      var currentLimit =
        parseInt($library.data("resource-visible-limit"), 10) || pageSize;

      $library.data("resource-visible-limit", currentLimit + pageSize);
      applyResourceLibraryFilters($library);
    });

    $eventRoot.on("click", "[data-resource-card]", function (e) {
      if ($(e.target).closest("a, button, input, select, textarea, label").length) {
        return;
      }

      var $card = $(this);
      var destination = resolveResourceCardUrl($card);

      if (!destination || destination === "#") {
        return;
      }

      if (destination.indexOf("/join") === 0 || destination.indexOf("/memberships") === 0 || destination.indexOf(window.location.origin + "/memberships") === 0) {
        window.location.href = destination;
        return;
      }

      window.open(destination, "_blank", "noopener");
    });

    $eventRoot.on("keydown", "[data-resource-card]", function (e) {
      if (e.key !== "Enter" && e.key !== " ") {
        return;
      }

      e.preventDefault();
      $(this).trigger("click");
    });

    $eventRoot.on("click", ".sffc-crm-resource-link", function (e) {
      var $link = $(this);
      var $card = $link.closest("[data-resource-card]");
      var destination = resolveResourceCardUrl($card);

      if (!destination || destination === "#") {
        e.preventDefault();
        return;
      }

      if (destination !== $link.attr("href")) {
        e.preventDefault();
        if (destination.indexOf("/join") === 0 || destination.indexOf("/memberships") === 0 || destination.indexOf(window.location.origin + "/memberships") === 0) {
          window.location.href = destination;
        } else {
          window.open(destination, "_blank", "noopener");
        }
      }
    });

    $("[data-resource-library]").each(function () {
      var $library = $(this);
      resetResourceLibraryPagination($library);
      applyResourceLibraryFilters($library);
    });
    var prepStrings = prepConfig.strings || {};
    var prepNonce = prepConfig.requestNonce || "";
    var quickViewStrings = {
      eyebrow: i18n.dashboardEyebrow || "Live role spotlight",
      openPosting: i18n.dashboardOpenPosting || "Open posting",
      close: i18n.dashboardClose || "Close quick view",
    };

    function setFeedGroupLabel(label) {
      if (!$feedGroupLabel.length) {
        return;
      }
      currentFeedGroupLabel =
        label && label.length ? label : feedGroupDefaultLabel;
      $feedGroupLabel.text(currentFeedGroupLabel);
    }

    setFeedGroupLabel(feedGroupDefaultLabel);

    function maybeShowWelcomeModal() {
      if (!config.isLoggedIn || !config.userId) {
        return;
      }

      var storageKey = "sffc_crm_welcome_" + config.userId;
      if (window.localStorage && localStorage.getItem(storageKey)) {
        return;
      }

      var firstName = "";
      if (config.currentUserName) {
        firstName = config.currentUserName.trim().split(/\s+/)[0];
      }
      var greetingName = firstName || "there";

      var modalHtml =
        "" +
        '<div class="sffc-crm-welcome-overlay" data-welcome-modal>' +
        '  <div class="sffc-crm-welcome-modal" role="dialog" aria-live="assertive" aria-label="Welcome message">' +
        '    <button type="button" class="sffc-crm-welcome-close" aria-label="Dismiss">×</button>' +
        "    <h3>Welcome " +
        greetingName +
        "!</h3>" +
        "    <p>Great to have you onboard.</p>" +
        '    <button type="button" class="sffc-crm-btn sffc-crm-btn-primary" data-welcome-dismiss>Let\'s go</button>' +
        "  </div>" +
        "</div>";

      var $modal = $(modalHtml).appendTo("body");

      function dismissWelcome() {
        $modal.addClass("is-dismissed");
        setTimeout(function () {
          $modal.remove();
        }, 250);
        if (window.localStorage) {
          localStorage.setItem(storageKey, "1");
        }
      }

      $modal.on("click", function (e) {
        if ($(e.target).is("[data-welcome-modal]")) {
          dismissWelcome();
        }
      });
      $modal
        .find("[data-welcome-dismiss], .sffc-crm-welcome-close")
        .on("click", function (e) {
          e.preventDefault();
          dismissWelcome();
        });
    }

    function readFeedFilterForm() {
      if (!$feedFilterForm.length) {
        return;
      }

      feedFilterState.location = (
        $feedFilterForm.find('[name="feed_location"]').val() || ""
      ).trim();
      feedFilterState.keywords = (
        $feedFilterForm.find('[name="feed_keywords"]').val() || ""
      ).trim();
      feedFilterState.startDate = (
        $feedFilterForm.find('[name="feed_start_date"]').val() || ""
      ).trim();
      feedFilterState.duration = (
        $feedFilterForm.find('[name="feed_duration"]').val() || ""
      ).trim();
    }

    function resetFeedFilterState() {
      feedFilterState.location = "";
      feedFilterState.keywords = "";
      feedFilterState.startDate = "";
      feedFilterState.duration = "";
    }

    function hasActiveFeedFilters() {
      return !!(
        feedFilterState.location ||
        feedFilterState.keywords ||
        feedFilterState.startDate ||
        feedFilterState.duration
      );
    }

    function ensureDashboardModalMarkup() {
      // Always query fresh to avoid stale references
      if ($(".sffc-crm-dashboard-modal").length) {
        return;
      }

      var modalHtml =
        "" +
        '<div class="sffc-crm-dashboard-modal" data-dashboard-modal aria-hidden="true">' +
        '  <div class="sffc-crm-dashboard-modal__overlay" data-dashboard-modal-close></div>' +
        '  <div class="sffc-crm-dashboard-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="sffc-crm-dashboard-modal-title">' +
        '    <button type="button" class="sffc-crm-dashboard-modal__close" aria-label="' +
        quickViewStrings.close +
        '" data-dashboard-modal-close>×</button>' +
        '    <div class="sffc-crm-dashboard-modal__header">' +
        '      <p class="sffc-crm-dashboard-modal__eyebrow">' +
        quickViewStrings.eyebrow +
        "</p>" +
        '      <h3 id="sffc-crm-dashboard-modal-title" data-dashboard-modal-title></h3>' +
        '      <p class="sffc-crm-dashboard-modal__company" data-dashboard-modal-company></p>' +
        '      <div class="sffc-crm-dashboard-modal__recruiter">' +
        '        <div class="sffc-crm-dashboard-modal__avatar" data-dashboard-modal-avatar><span data-dashboard-modal-avatar-initial>?</span></div>' +
        '        <div class="sffc-crm-dashboard-modal__recruiter-meta">' +
        "          <strong data-dashboard-modal-recruiter></strong>" +
        "          <span data-dashboard-modal-recruiter-title></span>" +
        "        </div>" +
        "      </div>" +
        '      <p class="sffc-crm-dashboard-modal__meta">' +
        "        <span data-dashboard-modal-location></span>" +
        "        <span data-dashboard-modal-posted></span>" +
        "      </p>" +
        "    </div>" +
        '    <div class="sffc-crm-dashboard-modal__body" data-dashboard-modal-content></div>' +
        '    <div class="sffc-crm-dashboard-modal__actions">' +
        '      <a href="#" class="sffc-crm-btn sffc-crm-btn-primary" data-dashboard-modal-message target="_blank" rel="noopener"></a>' +
        '      <a href="#" class="sffc-crm-btn sffc-crm-btn-outline" data-dashboard-modal-apply target="_blank" rel="noopener">' +
        quickViewStrings.openPosting +
        "</a>" +
        "    </div>" +
        "  </div>" +
        "</div>";

      $("body").append(modalHtml);
      $dashboardModalEl = $(".sffc-crm-dashboard-modal");
    }

    function initDashboardModal() {
      ensureDashboardModalMarkup();

      // Refresh the modal element reference after ensuring markup exists
      $dashboardModalEl = $(".sffc-crm-dashboard-modal");

      if (!$dashboardModalEl.length) {
        return;
      }

      dashboardModal = {
        $modal: $dashboardModalEl,
        $title: $dashboardModalEl.find("[data-dashboard-modal-title]"),
        $company: $dashboardModalEl.find("[data-dashboard-modal-company]"),
        $content: $dashboardModalEl.find("[data-dashboard-modal-content]"),
        $recruiter: $dashboardModalEl.find("[data-dashboard-modal-recruiter]"),
        $recruiterTitle: $dashboardModalEl.find(
          "[data-dashboard-modal-recruiter-title]"
        ),
        $location: $dashboardModalEl.find("[data-dashboard-modal-location]"),
        $posted: $dashboardModalEl.find("[data-dashboard-modal-posted]"),
        $message: $dashboardModalEl.find("[data-dashboard-modal-message]"),
        $apply: $dashboardModalEl.find("[data-dashboard-modal-apply]"),
        $avatar: $dashboardModalEl.find("[data-dashboard-modal-avatar]"),
        $avatarInitial: $dashboardModalEl.find(
          "[data-dashboard-modal-avatar-initial]"
        ),
        $requestPrep: $dashboardModalEl.find(
          "[data-dashboard-modal-request-prep]"
        ),
      };

      maybeShowWelcomeModal();
    }

    function closeDashboardModal() {
      if (!dashboardModal) {
        return;
      }
      dashboardModal.$modal.removeClass("is-open").attr("aria-hidden", "true");
      $("body").removeClass("sffc-crm-dashboard-modal-open");
    }

    function openDashboardModal($trigger, category) {
      if (!dashboardModal || !$trigger || !$trigger.length) {
        return;
      }

      var detailId = $trigger.data("detailId");
      var postId = parseInt($trigger.data("postId"), 10) || 0;
      var $detail = detailId
        ? $("#" + detailId)
        : $trigger
            .closest(".sffc-crm-dashboard-list-item")
            .find(".sffc-crm-dashboard-detail")
            .first();
      var noDescriptionText =
        i18n.dashboardNoDescription || "No description provided yet.";
      var contentHtml =
        $detail && $detail.length
          ? $detail.html()
          : "<p>" + noDescriptionText + "</p>";
      var roleDisplay =
        $trigger.data("roleDisplay") || $trigger.data("roleTitle") || "";
      var company = $trigger.data("company") || "";
      var recruiterName = $trigger.data("recruiter") || "";
      var recruiterTitle = $trigger.data("recruiterTitle") || "";
      var recruiterFirst = $trigger.data("recruiterFirst") || recruiterName;
      var recruiterAvatar = $trigger.data("recruiterAvatar");
      var recruiterInitial = $trigger.data("recruiterInitial") || "?";
      var locationLabel = $trigger.data("locationLabel") || "";
      var postedLabel = $trigger.data("postedLabel") || "";
      var applyUrl = $trigger.data("applyUrl") || "";
      var applyInternal =
        $trigger.data("applyInternal") === 1 ||
        $trigger.data("applyInternal") === "1";
      var recruiterEmail = $trigger.data("recruiterEmail") || "";
      var recruiterLinkedin = $trigger.data("recruiterLinkedin") || "";

      // Category-specific titles and content
      var categoryTitles = {
        "interview-questions": "Interview Questions",
        "case-studies": "CV Template",
        "application-guide": "Cover Letter Template",
        "mock-case-study": "Mock Case Study",
      };

      var modalTitle = roleDisplay;
      if (category && categoryTitles[category]) {
        modalTitle = categoryTitles[category] + " - " + company;

        // Get prep material data from trigger element (decode base64 for HTML content)
        var interviewQuestionsBase64 =
          $trigger.data("interviewQuestions") || "";
        var interviewQuestionsHtml = interviewQuestionsBase64
          ? atob(interviewQuestionsBase64)
          : "";
        var cvTemplateDocx = $trigger.data("cvTemplateDocx") || "";
        var coverLetterBase64 = $trigger.data("coverLetterHtml") || "";
        var coverLetterHtml = coverLetterBase64 ? atob(coverLetterBase64) : "";
        var coverLetterDocx = $trigger.data("coverLetterDocx") || "";
        var caseStudyPdf = $trigger.data("caseStudyPdf") || "";

        // Build content based on category
        if (category === "interview-questions" && interviewQuestionsHtml) {
          contentHtml =
            '<div class="sffc-prep-category-content">' +
            interviewQuestionsHtml +
            "</div>";
        } else if (category === "case-studies") {
          if (cvTemplateDocx) {
            contentHtml =
              '<div class="sffc-prep-category-content">' +
              "<p>Download the CV template for " +
              company +
              ":</p>" +
              '<a href="' +
              cvTemplateDocx +
              '" class="sffc-crm-btn sffc-crm-btn-primary" download target="_blank" rel="noopener">Download CV Template (.docx)</a>' +
              "</div>";
          } else {
            contentHtml =
              '<div class="sffc-prep-category-content"><p>CV template not available yet.</p></div>';
          }
        } else if (category === "application-guide") {
          if (coverLetterHtml || coverLetterDocx) {
            contentHtml = '<div class="sffc-prep-category-content">';
            if (coverLetterHtml) {
              contentHtml += coverLetterHtml;
            }
            if (coverLetterDocx) {
              contentHtml +=
                '<p style="margin-top: 20px;"><a href="' +
                coverLetterDocx +
                '" class="sffc-crm-btn sffc-crm-btn-outline" download target="_blank" rel="noopener">Download as .docx</a></p>';
            }
            contentHtml += "</div>";
          } else {
            contentHtml =
              '<div class="sffc-prep-category-content"><p>Cover letter template not available yet.</p></div>';
          }
        } else if (category === "mock-case-study") {
          if (caseStudyPdf) {
            contentHtml =
              '<div class="sffc-prep-category-content">' +
              "<p>Download the mock case study for " +
              company +
              ":</p>" +
              '<a href="' +
              caseStudyPdf +
              '" class="sffc-crm-btn sffc-crm-btn-primary" download target="_blank" rel="noopener">Download Case Study (.pdf)</a>' +
              '<p style="margin-top: 16px;"><a href="' +
              caseStudyPdf +
              '" class="sffc-crm-btn sffc-crm-btn-outline" target="_blank" rel="noopener">Review My Case Study</a></p>' +
              "</div>";
          } else {
            contentHtml =
              '<div class="sffc-prep-category-content"><p>Mock case study not available yet.</p></div>';
          }
        } else {
          contentHtml =
            '<div class="sffc-prep-category-content"><p>' +
            categoryTitles[category] +
            " not available yet for " +
            company +
            ".</p></div>";
        }
      }

      dashboardModal.$title.text(modalTitle);
      dashboardModal.$company.text(company).toggle(!!company);
      dashboardModal.$recruiter.text(recruiterName).toggle(!!recruiterName);
      dashboardModal.$recruiterTitle
        .text(recruiterTitle)
        .toggle(!!recruiterTitle);
      dashboardModal.$location
        .text(locationLabel)
        .toggleClass("is-hidden", !locationLabel);
      dashboardModal.$posted
        .text(postedLabel)
        .toggleClass("is-hidden", !postedLabel);
      dashboardModal.$content.html(contentHtml);

      dashboardModal.$avatar.find("img").remove();
      if (recruiterAvatar) {
        $("<img />", {
          src: recruiterAvatar,
          alt: recruiterName || "Recruiter",
        }).appendTo(dashboardModal.$avatar);
        dashboardModal.$avatarInitial.hide();
      } else {
        dashboardModal.$avatarInitial.text(recruiterInitial).show();
      }

      var defaultMessageLabel = i18n.messageRecruiter || "Message recruiter";
      var messageWithNameTemplate = i18n.messageRecruiterWithName || "";
      var messageLabel = recruiterFirst
        ? messageWithNameTemplate
          ? messageWithNameTemplate.replace("%s", recruiterFirst)
          : "Message " + recruiterFirst
        : defaultMessageLabel;
      dashboardModal.$message
        .removeClass("is-disabled")
        .text(messageLabel)
        .attr("href", "#")
        .removeAttr("target")
        .removeAttr("rel");

      if (recruiterEmail) {
        var subject = roleDisplay ? encodeURIComponent(roleDisplay) : "";
        var mailto =
          "mailto:" + recruiterEmail + (subject ? "?subject=" + subject : "");
        dashboardModal.$message.attr("href", mailto);
      } else if (recruiterLinkedin) {
        dashboardModal.$message
          .attr("href", recruiterLinkedin)
          .attr("target", "_blank")
          .attr("rel", "noopener");
      } else {
        dashboardModal.$message.addClass("is-disabled");
      }

      dashboardModal.$apply
        .removeClass("is-disabled")
        .attr("href", applyUrl || "#")
        .removeAttr("target")
        .removeAttr("rel");
      if (applyUrl) {
        if (!applyInternal) {
          dashboardModal.$apply
            .attr("target", "_blank")
            .attr("rel", "noopener");
        }
      } else {
        dashboardModal.$apply.addClass("is-disabled");
      }

      if (dashboardModal.$requestPrep && dashboardModal.$requestPrep.length) {
        if (postId) {
          dashboardModal.$requestPrep
            .removeAttr("hidden")
            .removeClass("is-loading is-disabled")
            .prop("disabled", false)
            .text(prepStrings.quickViewCta || "Request Prep Materials");
        } else {
          dashboardModal.$requestPrep.attr("hidden", "hidden");
        }

        dashboardModal.$requestPrep.data({
          postId: postId,
          role: roleDisplay,
          company: company,
        });
      }

      dashboardModal.$modal.addClass("is-open").attr("aria-hidden", "false");
      $("body").addClass("sffc-crm-dashboard-modal-open");
    }

    initDashboardModal();

    var $prepSignupModal = $(".sffc-prep-signup-modal");

    function promptLogin() {
      window.location.href = joinUrl;
    }

    function handleLockedAction(action) {
      if (action === "login") {
        promptLogin();
        return;
      }
      if (membershipUrl) {
        window.location.href = membershipUrl;
        return;
      }
      promptLogin();
    }

    function openPrepSignupModal() {
      if ($prepSignupModal.length) {
        $prepSignupModal.addClass("is-open");
        $("body").addClass("sffc-prep-modal-open");
      } else {
        promptLogin();
      }
    }

    function closePrepSignupModal() {
      if ($prepSignupModal.length) {
        $prepSignupModal.removeClass("is-open");
        $("body").removeClass("sffc-prep-modal-open");
      }
    }

    function setPrepFeedback($card, message, isError) {
      var $feedback = $card.find(".sffc-company-prep-feedback");
      if (!$feedback.length) {
        return;
      }
      if (!message) {
        $feedback.removeClass("is-visible is-error").text("");
        return;
      }
      $feedback
        .text(message)
        .addClass("is-visible")
        .toggleClass("is-error", !!isError);
    }

    function setPrepButtonLoading($button, isLoading) {
      if (!$button.length) {
        return;
      }
      if (isLoading) {
        $button.addClass("is-loading").prop("disabled", true);
      } else if (!$button.is("[hidden]")) {
        $button.removeClass("is-loading").prop("disabled", false);
      } else {
        $button.removeClass("is-loading");
      }
    }

    function markPrepCardPending($card) {
      var $status = $card.find(".sffc-company-prep-status");
      var $badge = $status.find(".sffc-company-prep-badge");
      var $statusText = $status.find(".sffc-company-prep-status-text");
      var $button = $card.find(".sffc-company-prep-request");
      var pendingLabel = prepStrings.pendingLabel || "Pending approval";
      var pendingHelp =
        prepStrings.pendingHelp || "Our prep team is packaging your kit.";

      $card.attr("data-request-status", "pending");
      $button.attr("hidden", "hidden");
      $status
        .removeAttr("hidden")
        .removeClass("is-approved is-rejected")
        .addClass("is-pending");
      if ($badge.length) {
        $badge.text(pendingLabel);
      }
      if ($statusText.length) {
        $statusText.text(pendingHelp);
      }
    }

    function initCompanyPrepRequestHandlers() {
      if (!$eventRoot.find(".sffc-company-prep-card").length) {
        return;
      }
      $eventRoot.on("click", ".sffc-company-prep-request", function (e) {
        e.preventDefault();

        var $button = $(this);
        if ($button.prop("disabled") || $button.hasClass("is-loading")) {
          return;
        }

        if (!ensurePremiumAccess()) {
          return;
        }

        var $card = $button.closest(".sffc-company-prep-card");
        var companyId = parseInt($card.data("company-id"), 10);
        if (!companyId || !prepNonce || !config.ajaxUrl) {
          return;
        }

        setPrepButtonLoading($button, true);
        setPrepFeedback($card, "");

        $.ajax({
          url: config.ajaxUrl,
          type: "POST",
          data: {
            action: "sffc_request_prep_materials",
            nonce: prepNonce,
            company_id: companyId,
          },
        })
          .done(function (response) {
            if (response && response.success) {
              markPrepCardPending($card);
              var successMessage = "";
              if (response.data && response.data.message) {
                successMessage = response.data.message;
              }
              setPrepFeedback(
                $card,
                successMessage || prepStrings.success || ""
              );
              return;
            }

            var errorMessage =
              response && response.data && response.data.message
                ? response.data.message
                : prepStrings.error || "Unable to send your request right now.";
            setPrepFeedback($card, errorMessage, true);
          })
          .fail(function () {
            setPrepFeedback(
              $card,
              prepStrings.error || "Unable to send your request right now.",
              true
            );
          })
          .always(function () {
            setPrepButtonLoading($button, false);
          });
      });

      $eventRoot.on("click", "[data-prep-open-chat]", function (e) {
        e.preventDefault();
        var $toggle = $(".sffc-live-expert-toggle");
        if ($toggle.length) {
          $toggle.trigger("click");
        } else if (
          window.sffcLiveExpert &&
          typeof window.sffcLiveExpert.open === "function"
        ) {
          window.sffcLiveExpert.open();
        }
      });
    }

    initCompanyPrepRequestHandlers();

    $(document).on("click", "[data-prep-signup-close]", function () {
      closePrepSignupModal();
    });

    $(document).on("keydown", function (evt) {
      if (evt.key === "Escape") {
        closePrepSignupModal();
      }
    });

    $(document).on(
      "click",
      "[data-dashboard-modal-request-prep]",
      function (e) {
        e.preventDefault();
        var $btn = $(this);

        if (!config.isLoggedIn) {
          promptLogin();
          return;
        }

        var postId = parseInt($btn.data("postId"), 10);
        if (!postId || $btn.hasClass("is-loading")) {
          return;
        }

        $btn.addClass("is-loading").prop("disabled", true);

        $.ajax({
          url: config.ajaxUrl,
          type: "POST",
          data: {
            action: "sffc_crm_request_post_prep",
            nonce: config.nonce,
            post_id: postId,
          },
        })
          .done(function (response) {
            if (response && response.success) {
              var successMsg =
                response.data && response.data.message
                  ? response.data.message
                  : prepStrings.quickViewSuccess || "Prep team notified.";
              $btn.text(successMsg).addClass("is-disabled");
            } else {
              var errorMsg =
                response && response.data && response.data.message
                  ? response.data.message
                  : prepStrings.quickViewError ||
                    "Unable to notify the prep team.";
              alert(errorMsg);
              $btn.removeClass("is-loading").prop("disabled", false);
            }
          })
          .fail(function () {
            alert(
              prepStrings.quickViewError || "Unable to notify the prep team."
            );
            $btn.removeClass("is-loading").prop("disabled", false);
          });
      }
    );

    function filterFeedByCompany(slug) {
      activeCompanyFilter = slug || "";
      visibleFeedCards = 6; // Reset to initial count
      console.log("Filtering feed by company:", activeCompanyFilter);
      updateFeedVisibility();
    }

    function filterFeedByGroup(slug, labelOverride) {
      activeGroupFilter = slug || "";
      activeCompanyFilter = ""; // Clear company filter when filtering by group
      visibleFeedCards = 6; // Reset to initial count
      console.log("Filtering feed by group:", activeGroupFilter);

      if (!activeGroupFilter) {
        setFeedGroupLabel(feedGroupDefaultLabel);
      } else if (labelOverride) {
        setFeedGroupLabel(labelOverride);
      }

      // If filtering by a specific group, load posts dynamically via AJAX
      if (
        (activeGroupFilter && config.ajaxUrl) ||
        (hasActiveFeedFilters() && config.ajaxUrl)
      ) {
        loadGroupPosts(activeGroupFilter);
      } else {
        updateFeedVisibility();
      }
    }

    function loadGroupPosts(groupSlug, overrides) {
      var $feed = $app.find(".sffc-crm-linkedin-feed");
      var $loadingMsg = $(
        '<div class="sffc-crm-loading" style="padding: 40px; text-align: center; color: #666;">Loading posts...</div>'
      );

      // Show loading message
      $feed.prepend($loadingMsg);

      var requestPayload = {
        action: "sffc_crm_load_group_posts",
        nonce: config.nonce,
        group_slug: groupSlug || "",
        per_page: overrides && overrides.perPage ? overrides.perPage : 50,
        location:
          overrides && overrides.location !== undefined
            ? overrides.location
            : feedFilterState.location,
        keywords:
          overrides && overrides.keywords !== undefined
            ? overrides.keywords
            : feedFilterState.keywords,
        start_date:
          overrides && overrides.startDate !== undefined
            ? overrides.startDate
            : feedFilterState.startDate,
        duration:
          overrides && overrides.duration !== undefined
            ? overrides.duration
            : feedFilterState.duration,
      };

      $.ajax({
        url: config.ajaxUrl,
        type: "POST",
        data: requestPayload,
        success: function (response) {
          $loadingMsg.remove();

          if (response.success && response.data && response.data.html) {
            // Clear ALL existing feed cards completely
            $feed.find(".sffc-crm-linkedin-feed-card").remove();

            // Also remove empty state if it exists
            $feed.find(".sffc-crm-linkedin-empty").remove();

            // Find the dashboard list container (UL)
            var $dashboardList = $feed.find(".sffc-crm-dashboard-list");

            // Find the load more button to preserve it
            var $loadMoreBtn = $feed.find(".sffc-crm-feed-load-more");

            // Insert new posts into the dashboard list
            if ($dashboardList.length) {
              // Append to existing UL
              $dashboardList.append(response.data.html);
            } else if ($loadMoreBtn.length) {
              // No UL found, insert before load more button
              $loadMoreBtn.before(response.data.html);
            } else {
              // Last resort, append to feed
              $feed.append(response.data.html);
            }

            // Reset visible count and update visibility
            visibleFeedCards = 6;
            updateFeedVisibility();
          } else {
            console.error("Failed to load group posts:", response);
            // Fall back to client-side filtering
            updateFeedVisibility();
          }
        },
        error: function (xhr, status, error) {
          $loadingMsg.remove();
          console.error("AJAX error loading group posts:", error);
          // Fall back to client-side filtering
          updateFeedVisibility();
        },
      });
    }

    // Desktop tab click handler
    $eventRoot.on("click", ".sffc-crm-tab", function (e) {
      e.preventDefault();
      var $btn = $(this);
      var tabKey = $btn.data("tab");
      var external = $btn.data("external");

      if (external) {
        window.open(external, "_blank");
        return;
      }

      activateTab(tabKey);
    });

    $eventRoot.on("click", "[data-sidebar-toggle]", function (e) {
      e.preventDefault();
      var isCollapsed = !$app.hasClass("sffc-crm-sidebar-collapsed");
      $app.toggleClass("sffc-crm-sidebar-collapsed", isCollapsed);
      $(this).attr("aria-expanded", isCollapsed ? "false" : "true");
      try {
        if (window.localStorage) {
          window.localStorage.setItem(
            sidebarStorageKey,
            isCollapsed ? "1" : "0"
          );
        }
      } catch (err) {}
    });

    // Mobile tab click handler
    $eventRoot.on("click", ".sffc-crm-mobile-tab", function (e) {
      var $btn = $(this);
      var tabKey = $btn.data("tab");

      // Handle "me" tab differently - toggle dropdown
      if (tabKey === "me") {
        e.preventDefault();
        e.stopPropagation();
        $btn.toggleClass("active");
        return;
      }

      e.preventDefault();
      var external = $btn.data("external");

      if (external) {
        window.open(external, "_blank");
        return;
      }

      activateTab(tabKey);
    });

    $eventRoot.on(
      "click",
      '[data-tab="matches"]:not(.sffc-crm-tab):not(.sffc-crm-mobile-tab)',
      function (e) {
        e.preventDefault();
        activateTab("console");
      }
    );

    $eventRoot.on(
      "click",
      ".criteria-adjust[data-tab], .matches-criteria-item[data-tab]",
      function (e) {
        e.preventDefault();
        activateTab($(this).data("tab"));
      }
    );

    // Close mobile me dropdown when clicking outside
    $(document).on("click", function (e) {
      if (!$(e.target).closest(".sffc-crm-mobile-tab-me").length) {
        $(".sffc-crm-mobile-tab-me .sffc-crm-mobile-tab").removeClass("active");
      }
    });

    // Handle mobile me menu item clicks
    $eventRoot.on("click", ".sffc-crm-mobile-me-item", function (e) {
      var href = $(this).attr("href");
      if (href && href.indexOf("tab=") !== -1) {
        e.preventDefault();
        var urlParams = new URLSearchParams(href.split("?")[1]);
        var tab = urlParams.get("tab");
        if (tab) {
          activateTab(tab);
          $(".sffc-crm-mobile-tab-me .sffc-crm-mobile-tab").removeClass(
            "active"
          );
        }
      }
    });

    // Set default tab from URL parameter or data attribute
    var urlParams = new URLSearchParams(window.location.search);
    var urlTab = urlParams.get("tab");
    var defaultTab = urlTab || $app.data("default-tab") || "feed";
    activateTab(defaultTab);

    var quickSearchQuery = String(urlParams.get("quick_search") || "").trim();
    var resourceSearchQuery = String(urlParams.get("resource_search") || "").trim();

    if (resourceSearchQuery && defaultTab === "resource-library") {
      var $resourceSearch = $app.find("[data-resource-search]").first();
      if ($resourceSearch.length) {
        $resourceSearch.val(resourceSearchQuery).trigger("input");
      }
    }

    if (quickSearchQuery && (defaultTab === "feed" || defaultTab === "dashboard")) {
      var $globalSearch = $app.find(".sffc-crm-search-input").first();
      if ($globalSearch.length) {
        $globalSearch.val(quickSearchQuery).trigger("input");
      }
    }

    // Search functionality
    var searchTimeout;
    $eventRoot.on("input", ".sffc-crm-search-input", function () {
      var $input = $(this);
      var query = $input.val().toLowerCase().trim();

      clearTimeout(searchTimeout);

      searchTimeout = setTimeout(function () {
        if (query === "") {
          // Show all feed cards then re-apply filters if active
          $app.find(".sffc-crm-linkedin-feed-card").show();
          if (activeGroupFilter) {
            filterFeedByGroup(activeGroupFilter);
          } else if (activeCompanyFilter) {
            filterFeedByCompany(activeCompanyFilter);
          }
          return;
        }

        // Filter feed cards
        $app.find(".sffc-crm-linkedin-feed-card").each(function () {
          var $card = $(this);
          var text = $card.text().toLowerCase();

          if (text.indexOf(query) !== -1) {
            $card.show();
          } else {
            $card.hide();
          }
        });

        // Switch to feed tab if searching
        if ($app.find('.sffc-crm-tab[data-tab="feed"]').length) {
          activateTab("feed");
        }
      }, 300);
    });

    // Feed status dropdown - show auth modal if not logged in
    $eventRoot.on("change", ".sffc-crm-feed-status-select", function (e) {
      var $select = $(this);

      // Check if user is logged in
      if (!config.isLoggedIn) {
        e.preventDefault();
        $select.val(""); // Reset dropdown
        promptLogin();
        return false;
      }

      // If logged in, proceed with normal stage update logic
      // (This can be extended later for actual status changes)
    });

    // Pipeline stage update handler
    $eventRoot.on("change", ".sffc-crm-following-stage-select", function () {
      var $select = $(this);
      var pipelineId = parseInt($select.data("pipeline-id"), 10);
      var stage = $select.val();
      var createOnChange = !!$select.data("create-on-change");

      if (!stage || !config.ajaxUrl) {
        return;
      }

      var previousStage = $select.data("current-stage") || "";
      var stageMeta =
        config.stages && config.stages[stage] ? config.stages[stage] : null;
      var $pill = $select
        .closest(".sffc-crm-following-card")
        .find(".sffc-crm-following-status-pill");
      var strings = config.strings || {};
      var toastMessage = strings.stageUpdated || "Updated";
      var errorMessage = strings.stageError || "Unable to update status.";

      var showToast = function () {
        var $msg = $('<span class="sffc-crm-following-toast"></span>').text(
          toastMessage
        );
        $select.after($msg);
        setTimeout(function () {
          $msg.fadeOut(200, function () {
            $(this).remove();
          });
        }, 1600);
      };

      var applyStageMeta = function () {
        if ($pill.length && stageMeta) {
          $pill
            .text(stageMeta.label)
            .css("background", stageMeta.color || "#0a66c2");
        } else if ($pill.length) {
          $pill.text(stage);
        }
      };

      var handleError = function () {
        if (previousStage) {
          $select.val(previousStage);
        }
        alert(errorMessage);
      };

      $select.prop("disabled", true);

      if (!pipelineId && createOnChange) {
        var recruiterId = $select.data("recruiter-id");
        if (!recruiterId) {
          handleError();
          $select.prop("disabled", false);
          return;
        }

        $.post(config.ajaxUrl, {
          action: "sffc_crm_add_to_pipeline",
          nonce: config.nonce,
          recruiter_id: recruiterId,
          post_id: $select.data("post-id") || "",
          stage: stage,
          role_title: $select.data("role-title") || "",
          company: $select.data("company") || "",
        })
          .done(function (response) {
            if (
              response &&
              response.success &&
              response.data &&
              response.data.pipeline_id
            ) {
              $select.data("pipeline-id", response.data.pipeline_id);
              $select.data("current-stage", stage);
              applyStageMeta();
              showToast();
            } else {
              handleError();
            }
          })
          .fail(handleError)
          .always(function () {
            $select.prop("disabled", false);
          });

        return;
      }

      if (!pipelineId) {
        $select.prop("disabled", false);
        return;
      }

      $.post(config.ajaxUrl, {
        action: "sffc_crm_update_pipeline_stage",
        nonce: config.nonce,
        pipeline_id: pipelineId,
        stage: stage,
      })
        .done(function (response) {
          if (response && response.success) {
            $select.data("current-stage", stage);
            applyStageMeta();
            showToast();
          } else {
            handleError();
          }
        })
        .fail(handleError)
        .always(function () {
          $select.prop("disabled", false);
        });
    });

    // Feed action handlers
    $eventRoot.on("click", ".sffc-crm-feed-action", function (e) {
      var $btn = $(this);
      var action = $btn.data("action");

      if (!action) {
        return;
      }

      e.preventDefault();

      // Handle different actions
      switch (action) {
        case "expert":
          // Show expert consultation dialog
          if (config.expertUrl) {
            window.open(config.expertUrl, "_blank");
          }
          break;
        case "message":
          // Show message notification (placeholder for future messaging feature)
          var $card = $btn.closest(".sffc-crm-linkedin-feed-card");
          var recruiterName = $card
            .find(".sffc-crm-linkedin-feed-author strong")
            .first()
            .text();
          if (config.messageUrl) {
            window.location.href = config.messageUrl;
          } else {
            // Visual feedback
            $btn.css("opacity", "0.6");
            setTimeout(function () {
              $btn.css("opacity", "1");
            }, 300);
          }
          break;
        case "prep":
          // Switch to prep tab
          activateTab("prep");
          break;
      }
    });

    // Company quick filters
    $eventRoot.on("click", ".sffc-crm-company-filter", function (e) {
      e.preventDefault();
      e.stopPropagation();

      var $btn = $(this);
      var companySlug = $btn.data("company") || "";

      console.log("Company filter clicked:", companySlug);

      $app.find(".sffc-crm-search-input").val("");
      filterFeedByCompany(companySlug);
      activateTab("feed");

      // Scroll to feed
      var $feed = $app.find('[data-panel="feed"]');
      if ($feed.length) {
        $("html, body").animate(
          {
            scrollTop: $feed.offset().top - 100,
          },
          300
        );
      }
    });

    $eventRoot.on("click", ".sffc-crm-company-filter-clear", function (e) {
      e.preventDefault();
      $app.find(".sffc-crm-search-input").val("");
      filterFeedByCompany("");
      activateTab("feed");
    });

    // Prep folder click - navigate to prep tab
    $eventRoot.on("click", ".sffc-crm-prep-folder", function (e) {
      e.preventDefault();
      e.stopPropagation();

      var $folder = $(this);
      var category = $folder.data("prep-category");
      var targetTab = $folder.data("tab") || "prep";
      var resourceUrl = $folder.data("resourceUrl") || $folder.attr("href");
      var hasDownload = resourceUrl && resourceUrl !== "#";

      if (!ensurePremiumAccess()) {
        return;
      }

      // Add visual feedback
      $folder.addClass("sffc-crm-prep-folder-active");
      setTimeout(function () {
        $folder.removeClass("sffc-crm-prep-folder-active");
      }, 300);

      // Navigate to prep tab
      activateTab(targetTab);

      // Scroll to prep panel safely
      setTimeout(function () {
        var $panel = $app.find('[data-panel="' + targetTab + '"]');
        if ($panel.length && $panel.offset()) {
          var targetOffset = $panel.offset().top - 100;
          if (!isNaN(targetOffset)) {
            $("html, body").stop(true).animate(
              {
                scrollTop: targetOffset,
              },
              300
            );
          }
        }
      }, 50);

      // TODO: Filter prep materials by category if needed
      console.log("Prep category clicked:", category);

      if (hasDownload) {
        window.open(resourceUrl, "_blank", "noopener");
      }
    });

    // View All button - switch to leads tab and filter by group
    $eventRoot.on("click", ".sffc-crm-view-group-btn", function (e) {
      if ($(this).is("[data-match-group-apply-all]")) {
        return;
      }
      e.preventDefault();
      e.stopPropagation();

      var $btn = $(this);
      var groupSlug = $btn.data("group") || "";
      var targetTab = $btn.data("tab") || "feed";

      console.log("View group clicked:", groupSlug, "Target tab:", targetTab);

      $app.find(".sffc-crm-search-input").val("");
      filterFeedByGroup(groupSlug, $btn.data("groupLabel") || "");
      activateTab(targetTab);

      // Scroll to feed
      var $panel = $app.find('[data-panel="' + targetTab + '"]');
      if ($panel.length) {
        $("html, body").animate(
          {
            scrollTop: $panel.offset().top - 100,
          },
          300
        );
      }
    });

    $eventRoot.on("click", "[data-match-queue-toggle]", function (e) {
      e.preventDefault();
      e.stopPropagation();

      var $toggle = $(this);
      var $card = $toggle.closest(".sffc-crm-dashboard-list-item");
      var $queueButton = $card.find(".sffc-crm-express-interest").first();

      if (
        !$queueButton.length ||
        $queueButton.hasClass("is-processing") ||
        $queueButton.prop("disabled")
      ) {
        return;
      }

      $toggle.addClass("is-active");
      $queueButton.trigger("click");
    });

    $eventRoot.on("click", "[data-match-group-apply-all]", function (e) {
      e.preventDefault();
      e.stopPropagation();

      var $card = $(this).closest(".sffc-crm-dashboard-card");
      $card.find("[data-match-queue-toggle]").each(function () {
        var $toggle = $(this);
        var $queueButton = $toggle
          .closest(".sffc-crm-dashboard-list-item")
          .find(".sffc-crm-express-interest")
          .first();
        if (
          $queueButton.length &&
          !$queueButton.hasClass("is-processing") &&
          !$queueButton.prop("disabled")
        ) {
          $toggle.trigger("click");
        }
      });
    });

    $eventRoot.on("click", "[data-matches-apply-all]", function (e) {
      e.preventDefault();
      e.stopPropagation();

      var $panel = $(this).closest('[data-panel="matches"]');
      $panel.find("[data-match-queue-toggle]").each(function () {
        var $toggle = $(this);
        var $queueButton = $toggle
          .closest(".sffc-crm-dashboard-list-item")
          .find(".sffc-crm-express-interest")
          .first();
        if (
          $queueButton.length &&
          !$queueButton.hasClass("is-processing") &&
          !$queueButton.prop("disabled")
        ) {
          $toggle.trigger("click");
        }
      });
    });

    $eventRoot.on(
      "dragstart",
      '.sffc-crm-linkedin-panel[data-panel="matches"] .match-card',
      function (e) {
        ensureMatchKanbanLists();
        var event = e.originalEvent;
        var $card = $(this);
        $card.addClass("is-dragging");
        if (event && event.dataTransfer) {
          event.dataTransfer.effectAllowed = "move";
          event.dataTransfer.setData(
            "text/plain",
            String($card.data("matchId") || "")
          );
        }
      }
    );

    $eventRoot.on(
      "dragend",
      '.sffc-crm-linkedin-panel[data-panel="matches"] .match-card',
      function () {
        $(this).removeClass("is-dragging");
        $(
          '.sffc-crm-linkedin-panel[data-panel="matches"] .group-list, .sffc-crm-linkedin-panel[data-panel="matches"] [data-applications-lane]'
        ).removeClass("is-drag-over");
        updateApplicationsLaneState();
      }
    );

    $eventRoot.on(
      "dragover",
      '.sffc-crm-linkedin-panel[data-panel="matches"] .group-list',
      function (e) {
        e.preventDefault();
        var event = e.originalEvent;
        if (event && event.dataTransfer) {
          event.dataTransfer.dropEffect = "move";
        }
        $(this)
          .addClass("is-drag-over")
          .closest("[data-applications-lane]")
          .addClass("is-drag-over");
      }
    );

    $eventRoot.on(
      "dragleave",
      '.sffc-crm-linkedin-panel[data-panel="matches"] .group-list',
      function () {
        $(this)
          .removeClass("is-drag-over")
          .closest("[data-applications-lane]")
          .removeClass("is-drag-over");
      }
    );

    $eventRoot.on(
      "drop",
      '.sffc-crm-linkedin-panel[data-panel="matches"] .group-list',
      function (e) {
        e.preventDefault();
        var $targetList = $(this);
        var $card = $(
          '.sffc-crm-linkedin-panel[data-panel="matches"] .match-card.is-dragging'
        ).first();
        if (!$card.length) {
          return;
        }
        moveMatchCardToList($card, $targetList);
      }
    );

    $eventRoot.on(
      "click",
      '.sffc-crm-linkedin-panel[data-panel="matches"] [data-criteria-apply]',
      function (e) {
        e.preventDefault();
        var $applicationsList = getApplicationsList();
        if (!$applicationsList.length) {
          return;
        }
        var $cards = limitCardsByApplyAllCredits(
          $(this).closest(".dashboard-group").find(".match-card:visible")
        );
        $cards.each(function () {
          moveMatchCardToList($(this), $applicationsList);
        });
      }
    );

    $eventRoot.on(
      "click",
      '.sffc-crm-linkedin-panel[data-panel="matches"] [data-dashboard-apply-all]',
      function (e) {
        e.preventDefault();
        var $applicationsList = getApplicationsList();
        if (!$applicationsList.length) {
          return;
        }
        var $cards = limitCardsByApplyAllCredits(
          $(this)
            .closest(".matches-dashboard")
            .find("[data-match-source-board] .match-card:visible")
        );
        $cards.each(function () {
          moveMatchCardToList($(this), $applicationsList);
        });
      }
    );

    $eventRoot.on(
      "click",
      '.sffc-crm-linkedin-panel[data-panel="matches"] [data-mobile-applications-open], .sffc-crm-linkedin-panel[data-panel="matches"] .match-card',
      function (e) {
        var isMobile =
          window.matchMedia && window.matchMedia("(max-width: 900px)").matches;
        if (!isMobile) {
          return;
        }
        var $target = $(e.target);
        var isQueueTrigger = $(this).is("[data-mobile-applications-open]");
        if (
          $target.closest("a, button, input, select, textarea").length &&
          !isQueueTrigger
        ) {
          return;
        }
        e.preventDefault();

        var $card = $(this).closest(".match-card");
        if ($card.length && !$card.closest("[data-applications-list]").length) {
          var $applicationsList = getApplicationsList();
          if (!$applicationsList.length) {
            return;
          }
          moveMatchCardToList($card, $applicationsList);
        }

        openMobileApplicationsModal();
      }
    );

    $eventRoot.on(
      "click",
      '.sffc-crm-linkedin-panel[data-panel="matches"] [data-mobile-applications-close]',
      function (e) {
        e.preventDefault();
        closeMobileApplicationsModal();
      }
    );

    $eventRoot.on(
      "click",
      '.sffc-crm-linkedin-panel[data-panel="matches"] [data-applications-lane]',
      function (e) {
        if (
          !window.matchMedia ||
          !window.matchMedia("(max-width: 900px)").matches
        ) {
          return;
        }
        if (e.target === this) {
          closeMobileApplicationsModal();
        }
      }
    );

    $eventRoot.on(
      "input change",
      '.sffc-crm-linkedin-panel[data-panel="matches"] [data-match-strength-slider]',
      function () {
        updateMatchStrengthFilter($(this).closest(".matches-dashboard"));
      }
    );

    $eventRoot.on(
      "click",
      '.sffc-crm-linkedin-panel[data-panel="matches"] [data-application-clear]',
      function (e) {
        e.preventDefault();
        $(
          '.sffc-crm-linkedin-panel[data-panel="matches"] [data-applications-list] .match-card'
        ).each(function () {
          var $card = $(this);
          var origin = $card.attr("data-origin-list-id");
          var $originList = origin
            ? $(
                '.sffc-crm-linkedin-panel[data-panel="matches"] .group-list[data-drop-list-id="' +
                  origin +
                  '"]'
              ).first()
            : $();
          if ($originList.length) {
            moveMatchCardToList($card, $originList);
          }
        });
        updateApplicationsLaneState();
      }
    );

    $eventRoot.on(
      "click",
      '.sffc-crm-linkedin-panel[data-panel="matches"] [data-match-remove]',
      function (e) {
        e.preventDefault();
        var $card = $(this).closest(".match-card");
        removeMatchEverywhere($card);
      }
    );

    $eventRoot.on(
      "click",
      '.sffc-crm-linkedin-panel[data-panel="matches"] [data-match-uncheck]',
      function (e) {
        e.preventDefault();
        e.stopPropagation();

        var $card = $(this).closest(".match-card");
        if (!$card.length) {
          return;
        }

        removeMatchEverywhere($card);
      }
    );

    $eventRoot.on(
      "click",
      '.sffc-crm-linkedin-panel[data-panel="matches"] [data-match-focus]',
      function (e) {
        e.preventDefault();

        var target = $(this).attr("data-match-focus");
        var $dashboard = $(this).closest(".matches-dashboard");
        var $target =
          target === "applications"
            ? $dashboard.find("[data-applications-lane]").first()
            : $dashboard.find("[data-match-source-board]").first();

        $(this)
          .addClass("is-active")
          .siblings("[data-match-focus]")
          .removeClass("is-active");

        if ($target.length && $target[0].scrollIntoView) {
          $target[0].scrollIntoView({
            behavior: "smooth",
            block: "nearest",
            inline: "center",
          });
        }
      }
    );

    $eventRoot.on("click", "[data-matches-load-more]", function (e) {
      e.preventDefault();
      e.stopPropagation();

      var $button = $(this);
      var $card = $button.closest(".sffc-crm-dashboard-card");
      $card
        .find(".sffc-crm-linkedin-matches-card.is-hidden-match")
        .removeAttr("hidden")
        .removeClass("is-hidden-match");
      $button.closest(".sffc-crm-matches-load-more").remove();
    });

    $eventRoot.on("click", "[data-navigate-tab]", function (e) {
      e.preventDefault();
      var targetTab = $(this).data("tab") || "";

      if (!targetTab) {
        return;
      }

      if (targetTab === "prep" && !ensurePremiumAccess()) {
        return;
      }

      activateTab(targetTab);
      var $panel = $app.find('[data-panel="' + targetTab + '"]');
      if ($panel.length) {
        $("html, body").animate(
          {
            scrollTop: $panel.offset().top - 100,
          },
          300
        );
      }
    });

    if ($feedFilterForm.length) {
      $feedFilterForm.on("submit", function (e) {
        e.preventDefault();

        if (!ensurePremiumAccess()) {
          return;
        }

        readFeedFilterForm();
        filterFeedByGroup(activeGroupFilter, currentFeedGroupLabel);
      });

      $feedFilterForm.on("click", "[data-feed-filter-reset]", function () {
        if ($feedFilterForm[0]) {
          $feedFilterForm[0].reset();
        }
        resetFeedFilterState();
        filterFeedByGroup(activeGroupFilter, currentFeedGroupLabel);
      });
    }

    $eventRoot.on("change", ".sffc-crm-group-filter-select", function () {
      var $select = $(this);
      var slug = $select.val();
      var label = slug ? $select.find("option:selected").text() : "";

      $app.find(".sffc-crm-search-input").val("");
      activateTab("feed");
      filterFeedByGroup(slug, label);
    });

    // Load More functionality
    var visibleFeedCards = 6;

    function updateFeedVisibility() {
      var $allCards = $app.find(".sffc-crm-linkedin-feed-card");
      var $visibleCards = $allCards.filter(":visible");
      var actualVisibleCount = 0;

      $allCards.each(function (index) {
        var $card = $(this);

        // Group filter takes priority (exclusive filtering)
        if (activeGroupFilter) {
          var cardGroups = ($card.data("groups") || "").toString().split(",");
          var hasGroup = false;

          for (var i = 0; i < cardGroups.length; i++) {
            if (cardGroups[i].trim() === activeGroupFilter) {
              hasGroup = true;
              break;
            }
          }

          if (!hasGroup) {
            $card.hide();
            return; // continue
          }
        } else if (activeCompanyFilter) {
          // Company filter (only if no group filter)
          var cardCompany = $card.data("company") || "";
          if (cardCompany !== activeCompanyFilter) {
            $card.hide();
            return; // continue
          }
        }

        // Show or hide based on visible count
        if (actualVisibleCount < visibleFeedCards) {
          $card.show();
          actualVisibleCount++;
        } else {
          $card.hide();
        }
      });

      // Update load more button visibility
      var $loadMore = $app.find(".sffc-crm-feed-load-more");
      var totalMatchingCards;

      if (activeGroupFilter) {
        totalMatchingCards = $allCards.filter(function () {
          var cardGroups = ($(this).data("groups") || "").toString().split(",");
          for (var i = 0; i < cardGroups.length; i++) {
            if (cardGroups[i].trim() === activeGroupFilter) {
              return true;
            }
          }
          return false;
        }).length;
      } else if (activeCompanyFilter) {
        totalMatchingCards = $allCards.filter(
          '[data-company="' + activeCompanyFilter + '"]'
        ).length;
      } else {
        totalMatchingCards = $allCards.length;
      }

      if (actualVisibleCount >= totalMatchingCards) {
        $loadMore.hide();
      } else {
        $loadMore.show();
      }
    }

    $eventRoot.on("click", ".sffc-crm-load-more-btn", function (e) {
      e.preventDefault();
      visibleFeedCards += 6;
      updateFeedVisibility();
    });

    // Initialize feed visibility
    updateFeedVisibility();

    // Handle browser back/forward
    $(window).on("popstate", function () {
      var urlParams = new URLSearchParams(window.location.search);
      var tab = urlParams.get("tab") || $app.data("default-tab") || "feed";
      activateTab(tab);
    });

    // Responsive behavior
    var resizeTimeout;
    $(window).on("resize", function () {
      clearTimeout(resizeTimeout);
      resizeTimeout = setTimeout(function () {
        // Update mobile navigation visibility
        if ($(window).width() <= 640) {
          $app.find(".sffc-crm-tabs").hide();
        } else {
          $app.find(".sffc-crm-tabs").show();
          // Reset menu position on desktop
          $(".sffc-user-menu").css("top", "");
        }
      }, 250);
    });

    // Initialize responsive state
    $(window).trigger("resize");

    // Profile dropdown toggle
    $eventRoot.on("click", ".sffc-crm-profile-toggle", function (e) {
      e.stopPropagation();
      var $toggle = $(this);
      var $menu = $toggle.siblings(".sffc-user-menu");
      var isExpanded = $toggle.attr("aria-expanded") === "true";

      // Close all other dropdowns
      $(".sffc-crm-profile-toggle").attr("aria-expanded", "false");
      $(".sffc-user-menu").removeClass("active");

      if (!isExpanded) {
        $toggle.attr("aria-expanded", "true");
        $menu.addClass("active");

        // Position menu on mobile (fixed positioning)
        if ($(window).width() <= 640) {
          var toggleOffset = $toggle.offset();
          var toggleHeight = $toggle.outerHeight();
          var menuTop = toggleOffset.top + toggleHeight + 4;
          $menu.css("top", menuTop + "px");
        }
      }
    });

    // Close dropdown when clicking outside
    $(document).on("click", function (e) {
      if (!$(e.target).closest(".sffc-crm-linkedin-profile").length) {
        $(".sffc-crm-profile-toggle").attr("aria-expanded", "false");
        $(".sffc-user-menu").removeClass("active");
      }
    });

    // Prep dropdown toggle
    $eventRoot.on("click", ".sffc-crm-prep-toggle", function (e) {
      e.preventDefault();
      e.stopPropagation();

      var $button = $(this);
      var $wrapper = $button.closest(".sffc-crm-prep-dropdown-wrapper");
      var $dropdown = $wrapper.find(".sffc-crm-prep-dropdown");
      var isOpen = $dropdown.attr("hidden") === undefined;

      // Close all other dropdowns first
      $(".sffc-crm-prep-dropdown").attr("hidden", "hidden");
      $(".sffc-crm-prep-toggle").attr("aria-expanded", "false");

      // Toggle this dropdown
      if (!isOpen) {
        $dropdown.removeAttr("hidden");
        $button.attr("aria-expanded", "true");
      } else {
        $dropdown.attr("hidden", "hidden");
        $button.attr("aria-expanded", "false");
      }
    });

    // HR Prep dropdown toggle (Previously Hired tab)
    $eventRoot.on("click", ".sffc-crm-hr-prep-toggle", function (e) {
      e.preventDefault();
      e.stopPropagation();

      var $button = $(this);
      var $wrapper = $button.closest(".sffc-crm-hr-prep-dropdown-wrapper");
      var $dropdown = $wrapper.find(".sffc-crm-hr-prep-dropdown");
      var isOpen = $dropdown.attr("hidden") === undefined;

      // Close all other HR prep dropdowns first
      $(".sffc-crm-hr-prep-dropdown").attr("hidden", "hidden");
      $(".sffc-crm-hr-prep-toggle").attr("aria-expanded", "false");

      // Toggle this dropdown
      if (!isOpen) {
        $dropdown.removeAttr("hidden");
        $button.attr("aria-expanded", "true");
      } else {
        $dropdown.attr("hidden", "hidden");
        $button.attr("aria-expanded", "false");
      }
    });

    // Request Intro button - one-click handler
    $eventRoot.on("click", ".sffc-crm-request-intro-btn", function (e) {
      e.preventDefault();
      e.stopPropagation();

      var $button = $(this);
      var introStatus = $button.data("intro-status");

      // Prevent duplicate requests
      if (introStatus === "requested") {
        return;
      }

      if (!window.sffcUserData.isLoggedIn) {
        if (typeof promptLogin === "function") {
          promptLogin();
        }
        return;
      }

      var postId = $button.data("postId");
      var recruiterId = $button.data("recruiterId");
      var sourceType = $button.data("sourceType");
      var roleTitle = $button.data("roleTitle");
      var company = $button.data("company");
      var recruiterName = $button.data("recruiterName");
      var recruiterEmail = $button.data("recruiterEmail");
      var criteriaId =
        $button.data("criteriaId") ||
        $button.closest("[data-criteria-id]").data("criteriaId") ||
        0;

      // Show loading state
      var originalText = $button.text();
      $button.prop("disabled", true).text("Requesting...");

      $.post(config.ajaxUrl, {
        action: "sffc_crm_request_intro_queue",
        nonce: config.nonce,
        post_id: postId,
        recruiter_id: recruiterId,
        source_type: sourceType,
        role_title: roleTitle,
        company_name: company,
        recruiter_name: recruiterName,
        recruiter_email: recruiterEmail,
        criteria_id: criteriaId,
      })
        .done(function (response) {
          if (!response || !response.success) {
            showToast(
              (response && response.data && response.data.message) ||
                "Unable to request intro right now.",
              "error"
            );
            $button.prop("disabled", false).text(originalText);
            return;
          }

          // Update button state to "Requested"
          $button
            .text("Requested")
            .removeClass("sffc-crm-btn-outline")
            .addClass("sffc-crm-btn-success")
            .data("intro-status", "requested")
            .attr("data-intro-status", "requested")
            .prop("disabled", true);

          showToast(
            (response.data && response.data.message) ||
              "Intro request submitted! We'll connect you with the recruiter.",
            "success"
          );
        })
        .fail(function () {
          showToast("Network error. Please try again.", "error");
          $button.prop("disabled", false).text(originalText);
        });
    });

    $eventRoot.on("click", "[data-intro-membership-close]", function (e) {
      e.preventDefault();
      closeIntroMembershipModal();
    });

    $eventRoot.on("click", "[data-intro-membership-upgrade]", function (e) {
      e.preventDefault();
      closeIntroMembershipModal();

      if (membershipUrl) {
        window.location.href = membershipUrl;
        return;
      }

      showMembershipModal();
    });

    $eventRoot.on("keydown", function (e) {
      if (
        e.key === "Escape" &&
        $(".sffc-crm-intro-membership-modal.is-open").length
      ) {
        closeIntroMembershipModal();
      }
    });

    // Close dropdown when clicking outside
    $(document).on("click", function (e) {
      if (!$(e.target).closest(".sffc-crm-prep-dropdown-wrapper").length) {
        $(".sffc-crm-prep-dropdown").attr("hidden", "hidden");
        $(".sffc-crm-prep-toggle").attr("aria-expanded", "false");
      }
      if (!$(e.target).closest(".sffc-crm-message-dropdown-wrapper").length) {
        $(".sffc-crm-message-dropdown").attr("hidden", "hidden");
        $(".sffc-crm-message-toggle").attr("aria-expanded", "false");
      }
      if (!$(e.target).closest(".sffc-crm-hr-prep-dropdown-wrapper").length) {
        $(".sffc-crm-hr-prep-dropdown").attr("hidden", "hidden");
        $(".sffc-crm-hr-prep-toggle").attr("aria-expanded", "false");
      }
    });

    // Category item click - open prep materials modal
    $eventRoot.on("click", ".sffc-crm-prep-category-item", function (e) {
      e.preventDefault();
      e.stopPropagation();

      var $item = $(this);
      var category = $item.data("category");
      var $toggle = $item
        .closest(".sffc-crm-prep-dropdown-wrapper")
        .find(".sffc-crm-prep-toggle");

      // Close the dropdown
      $item.closest(".sffc-crm-prep-dropdown").attr("hidden", "hidden");
      $toggle.attr("aria-expanded", "false");

      // Open prep materials modal
      if (window.SFFCPrepModal) {
        // Try to find company logo and recruiter data
        var $listItem = $item.closest(".sffc-crm-dashboard-list-item");
        var companyLogo = "";
        var recruiterAvatar = "";
        var recruiterInitial = "?";

        if ($listItem.length) {
          var $logoImg = $listItem
            .find(".sffc-crm-dashboard-logo-badge img")
            .first();
          if ($logoImg.length) {
            companyLogo = $logoImg.attr("src");
          }
          var $avatarImg = $listItem
            .find(".sffc-crm-dashboard-avatar img")
            .first();
          if ($avatarImg.length) {
            recruiterAvatar = $avatarImg.attr("src");
          }
          var $avatarSpan = $listItem
            .find(".sffc-crm-dashboard-avatar span")
            .first();
          if ($avatarSpan.length) {
            recruiterInitial = $avatarSpan.text();
          }
        }

        var postData = {
          postId: $toggle.data("postId"),
          roleTitle: $toggle.data("roleTitle") || $toggle.data("roleDisplay"),
          company: $toggle.data("company"),
          location: $toggle.data("locationLabel"),
          sector: $toggle.data("sector"),
          seniority: $toggle.data("seniority"),
          keywords: $toggle.data("keywords"),
          contentSnippet: $toggle.data("contentSnippet"),
          interviewQuestionsUrl: $toggle.data("interviewQuestionsUrl"),
          interviewQuestionsHtml: $toggle.data("interviewQuestionsHtml"),
          cvTemplateUrl: $toggle.data("cvTemplateUrl"),
          coverLetterUrl: $toggle.data("coverLetterUrl"),
          coverLetterHtml: $toggle.data("coverLetterHtml"),
          caseStudyUrl: $toggle.data("caseStudyUrl"),
          applicationProcess: $toggle.data("applicationProcess"),
          teamContacts: $toggle.data("teamContacts"),
          knockoutQuestions: $toggle.data("knockoutQuestions"),
          openingDate: $toggle.data("openingDate"),
          closingDate: $toggle.data("closingDate"),
          startingDate: $toggle.data("startingDate"),
          duration: $toggle.data("duration"),
          recruiterName: $toggle.data("recruiter"),
          recruiterTitle: $toggle.data("recruiterTitle"),
          recruiterEmail: $toggle.data("recruiterEmail"),
          recruiterLinkedin: $toggle.data("recruiterLinkedin"),
          recruiterAvatar: recruiterAvatar,
          recruiterInitial: recruiterInitial,
          companyLogo: $toggle.data("companyLogo") || companyLogo,
          companyInitial: $toggle.data("companyInitial"),
        };
        window.SFFCPrepModal.open(postData);
      }
    });

    // Add to Queue button click
    $eventRoot.on("click", ".sffc-crm-express-interest", function (e) {
      e.preventDefault();

      var $button = $(this);
      var $card = $button.closest("li, article");

      // Prevent double clicks
      if ($button.hasClass("is-processing") || $button.prop("disabled")) {
        return;
      }

      var postId = $button.data("postId");
      var recruiterEmail = $button.data("recruiterEmail");
      var recruiterName = $button.data("recruiterName");
      var roleTitle = $button.data("roleTitle");
      var company = $button.data("company");
      var viewUrl = $button.data("viewUrl") || "";
      var recruiterAvatar = "";
      var recruiterInitial = recruiterName
        ? recruiterName.charAt(0).toUpperCase()
        : "S";
      var companyLogo = "";
      var companyInitial = company ? company.charAt(0).toUpperCase() : "S";

      if ($card.length) {
        var $avatarImg = $card
          .find(".sffc-crm-dashboard-avatar img, .sffc-crm-hr-avatar img")
          .first();
        var $avatarInitial = $card
          .find(".sffc-crm-dashboard-avatar span, .sffc-crm-hr-avatar span")
          .first();
        var $logoImg = $card
          .find(".sffc-crm-dashboard-logo-badge img, .sffc-crm-hr-logo img")
          .first();
        var $logoInitial = $card
          .find(".sffc-crm-dashboard-logo-badge span, .sffc-crm-hr-logo span")
          .first();

        if ($avatarImg.length) {
          recruiterAvatar = $avatarImg.attr("src") || "";
        }
        if ($avatarInitial.length && $avatarInitial.text().trim()) {
          recruiterInitial = $avatarInitial
            .text()
            .trim()
            .charAt(0)
            .toUpperCase();
        }
        if ($logoImg.length) {
          companyLogo = $logoImg.attr("src") || "";
        }
        if ($logoInitial.length && $logoInitial.text().trim()) {
          companyInitial = $logoInitial.text().trim().charAt(0).toUpperCase();
        }
      }

      // Show loading state
      var originalText = $button.text();
      $button
        .addClass("is-processing")
        .prop("disabled", true)
        .text("Adding...");

      // Track the application first
      $.post(config.ajaxUrl, {
        action: "sffc_crm_express_interest",
        nonce: config.nonce,
        post_id: postId,
        recruiter_email: recruiterEmail,
        recruiter_name: recruiterName,
        role_title: roleTitle,
        company: company,
      })
        .done(function (response) {
          if (response && response.success) {
            // Update badge count for tracking tab
            var $trackingBadge = $('[data-badge="recruiter-intros"]');
            var currentCount = parseInt($trackingBadge.text()) || 0;
            $trackingBadge.text(currentCount + 1);

            // Add to Tracking tab dynamically (without page refresh)
            addApplicationToTrackingTab({
              roleTitle: roleTitle,
              company: company,
              recruiterName: recruiterName,
              recruiterEmail: recruiterEmail,
              postId: postId,
              createdAt: "Just now",
              materialsStatus: "not_requested",
              viewUrl: viewUrl,
              recruiterAvatar: recruiterAvatar,
              recruiterInitial: recruiterInitial,
              companyLogo: companyLogo,
              companyInitial: companyInitial,
            });

            if ($card.length) {
              $card.fadeOut(220, function () {
                $(this).remove();
              });
            }
          } else {
            // Show error state
            $button
              .removeClass("is-processing")
              .prop("disabled", false)
              .text(originalText);

            var errorMsg =
              response && response.data && response.data.message
                ? response.data.message
                : "Unable to add this role to your queue. Please try again.";
            alert(errorMsg);
          }
        })
        .fail(function (xhr, status, error) {
          // Handle network/server error
          $button
            .removeClass("is-processing")
            .prop("disabled", false)
            .text(originalText);

          var errorMsg = "Request failed. ";
          if (xhr.status === 0) {
            errorMsg += "Network error - please check your connection.";
          } else if (xhr.status === 403) {
            errorMsg += "Security token expired. Please refresh the page.";
          } else if (xhr.status === 500) {
            errorMsg +=
              "Server error. The database may need updating. Please check Settings > Add Tracking Columns.";
          } else {
            errorMsg += "Error " + xhr.status + ": " + error;
          }

          console.error("Add to Queue Error:", {
            status: xhr.status,
            statusText: xhr.statusText,
            responseText: xhr.responseText,
            error: error,
          });

          alert(errorMsg);
        });
    });

    // View Intro Update modal
    $eventRoot.on("click", ".sffc-crm-view-intro-update", function (e) {
      e.preventDefault();

      var $button = $(this);
      var data = {
        introId: $button.data("introId"),
        status: $button.data("status"),
        statusLabel: $button.data("statusLabel"),
        roleTitle: $button.data("roleTitle"),
        company: $button.data("company"),
        recruiterName: $button.data("recruiterName"),
        createdAt: $button.data("createdAt"),
        adminMessage: $button.data("adminMessage"),
        recruiterReply: $button.data("recruiterReply"),
      };

      openIntroUpdateModal(data);
    });

    function openIntroUpdateModal(data) {
      var statusColors = {
        sent: "#3b82f6",
        accepted: "#10b981",
        rejected: "#ef4444",
        approved: "#10a37f",
        awaiting_response: "#f59e0b",
        pending_review: "#6b7280",
        expired: "#6b7280",
      };

      var statusColor = statusColors[data.status] || "#6b7280";

      var modalHTML =
        '<div class="sffc-intro-update-modal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; display: flex; align-items: center; justify-content: center; padding: 20px;">' +
        '<div class="sffc-intro-update-content" style="background: #ffffff; border-radius: 12px; max-width: 600px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">' +
        '<div class="sffc-intro-update-header" style="background: #0D353E; color: #ffffff; padding: 24px; border-radius: 12px 12px 0 0;">' +
        '<div style="display: flex; justify-content: space-between; align-items: start;">' +
        "<div>" +
        '<h2 style="margin: 0 0 8px 0; font-size: 24px; font-weight: 600;">Intro Request Update</h2>' +
        '<p style="margin: 0; opacity: 0.9; font-size: 14px;">' +
        data.roleTitle +
        " @ " +
        data.company +
        "</p>" +
        "</div>" +
        '<button class="sffc-intro-modal-close" style="background: transparent; border: none; color: #ffffff; font-size: 28px; cursor: pointer; padding: 0; line-height: 1; opacity: 0.7;">&times;</button>' +
        "</div>" +
        "</div>" +
        '<div class="sffc-intro-update-body" style="padding: 30px;">' +
        '<div style="margin-bottom: 24px;">' +
        '<div style="display: inline-block; background: ' +
        statusColor +
        '; color: #ffffff; padding: 8px 16px; border-radius: 6px; font-weight: 600; font-size: 14px; margin-bottom: 16px;">' +
        data.statusLabel +
        "</div>" +
        '<div style="background: #FBF7F0; border-radius: 8px; padding: 20px; margin-bottom: 20px;">' +
        '<p style="margin: 0 0 12px 0; color: #666; font-size: 13px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">Recruiter</p>' +
        '<p style="margin: 0 0 16px 0; color: #0D353E; font-size: 16px; font-weight: 600;">' +
        data.recruiterName +
        "</p>" +
        '<p style="margin: 0; color: #666; font-size: 13px;">Requested: ' +
        data.createdAt +
        "</p>" +
        "</div>" +
        "</div>";

      if (data.adminMessage) {
        modalHTML +=
          '<div style="background: #e5fef7; border-left: 4px solid #63FBC9; padding: 20px; border-radius: 4px; margin-bottom: 20px;">' +
          '<p style="margin: 0 0 10px 0; color: #0D353E; font-size: 14px; font-weight: 600;">Message from MENA Careers:</p>' +
          '<p style="margin: 0; color: #0F1F18; font-size: 14px; line-height: 22px; white-space: pre-wrap;">' +
          data.adminMessage +
          "</p>" +
          "</div>";
      }

      if (data.recruiterReply) {
        modalHTML +=
          '<div style="background: #f0f9ff; border-left: 4px solid #3b82f6; padding: 20px; border-radius: 4px; margin-bottom: 20px;">' +
          '<p style="margin: 0 0 10px 0; color: #0D353E; font-size: 14px; font-weight: 600;">Recruiter Reply:</p>' +
          '<p style="margin: 0; color: #0F1F18; font-size: 14px; line-height: 22px; white-space: pre-wrap;">' +
          data.recruiterReply +
          "</p>" +
          "</div>";
      }

      if (!data.adminMessage && !data.recruiterReply) {
        modalHTML +=
          '<div style="text-align: center; padding: 30px 0; color: #666;">' +
          "<p style=\"margin: 0;\">No messages yet. We'll notify you when there's an update.</p>" +
          "</div>";
      }

      modalHTML += "</div>" + "</div>" + "</div>";

      $("body").append(modalHTML);

      $(".sffc-intro-modal-close, .sffc-intro-update-modal").on(
        "click",
        function (e) {
          if (e.target === this) {
            $(".sffc-intro-update-modal").remove();
          }
        }
      );
    }

    // Remove from Tracking button click
    $eventRoot.on("click", ".sffc-crm-remove-tracking", function (e) {
      e.preventDefault();

      var $button = $(this);
      var applicationId = $button.data("applicationId");
      var $listItem = $button.closest(".sffc-crm-dashboard-list-item");

      function removeTrackingListItem() {
        $listItem.fadeOut(300, function () {
          $listItem.remove();

          var $trackingBadge = $('[data-badge="recruiter-intros"]');
          var currentCount = parseInt($trackingBadge.text(), 10) || 0;
          $trackingBadge.text(Math.max(0, currentCount - 1));

          var $list = $(".sffc-crm-dashboard-list");
          var remainingCount = $list.find(
            ".sffc-crm-dashboard-list-item"
          ).length;
          var $eyebrow = $('[data-panel="intros"]').find(
            ".sffc-crm-dashboard-eyebrow"
          );
          if ($eyebrow.length) {
            $eyebrow.text(remainingCount + " Applications");
          }

          if (remainingCount === 0) {
            var emptyHtml =
              '<div class="sffc-company-prep-empty"><h3>No applications yet</h3><p>Click "Add to Queue" on a role to track it here.</p></div>';
            $list.closest(".sffc-crm-dashboard-card").replaceWith(emptyHtml);
          }
        });
      }

      if (!confirm("Remove this application from tracking?")) {
        return;
      }

      if (!applicationId || applicationId === "new") {
        removeTrackingListItem();
        return;
      }

      $button.prop("disabled", true).text("Removing...");

      $.post(config.ajaxUrl, {
        action: "sffc_crm_remove_application",
        nonce: config.nonce,
        application_id: applicationId,
      })
        .done(function (response) {
          if (response && response.success) {
            removeTrackingListItem();
          } else {
            $button.prop("disabled", false).text("Remove");
            alert("Unable to remove application. Please try again.");
          }
        })
        .fail(function () {
          $button.prop("disabled", false).text("Remove");
          alert("Network error. Please try again.");
        });
    });

    // Helper function to add application to Tracking tab
    function addApplicationToTrackingTab(data) {
      var $trackingPanel = $('[data-panel="intros"]');
      var $emptyState = $trackingPanel.find(".sffc-company-prep-empty");
      var $list = $trackingPanel.find(".sffc-crm-dashboard-list");

      // If empty state exists, replace it with the card structure
      if ($emptyState.length) {
        var cardHtml =
          '<article class="sffc-crm-dashboard-card">' +
          '<header class="sffc-crm-dashboard-card-header">' +
          '<div><p class="sffc-crm-dashboard-eyebrow">1 Applications</p>' +
          "<h3>Application Tracker</h3></div></header>" +
          '<ul class="sffc-crm-dashboard-list"></ul></article>';
        $emptyState.replaceWith(cardHtml);
        $list = $trackingPanel.find(".sffc-crm-dashboard-list");
      }

      // Update count in header
      var $eyebrow = $trackingPanel.find(".sffc-crm-dashboard-eyebrow");
      var count = $list.find(".sffc-crm-dashboard-list-item").length + 1;
      $eyebrow.text(count + " Applications");

      // Build the new list item HTML
      var initial = data.recruiterName
        ? data.recruiterName.charAt(0).toUpperCase()
        : "S";
      var companyInitial = data.company
        ? data.company.charAt(0).toUpperCase()
        : "S";
      var avatarHtml = data.recruiterAvatar
        ? '<img src="' +
          data.recruiterAvatar +
          '" alt="' +
          (data.recruiterName || "Recruiter") +
          '">'
        : "<span>" + (data.recruiterInitial || initial) + "</span>";
      var logoHtml = data.companyLogo
        ? '<img src="' +
          data.companyLogo +
          '" alt="' +
          (data.company || "Company") +
          ' logo">'
        : "<span>" + (data.companyInitial || companyInitial) + "</span>";

      var listItemHtml =
        '<li class="sffc-crm-dashboard-list-item" data-application-id="new">' +
        '<div class="sffc-crm-dashboard-identity">' +
        '<div class="sffc-crm-hr-avatar-wrapper sffc-crm-dashboard-avatar-wrapper">' +
        '<div class="sffc-crm-hr-logo sffc-crm-dashboard-logo-badge">' +
        logoHtml +
        "</div>" +
        '<div class="sffc-crm-hr-avatar sffc-crm-dashboard-avatar">' +
        avatarHtml +
        "</div>" +
        "</div>" +
        '<div class="sffc-crm-dashboard-title">' +
        "<strong>" +
        data.roleTitle +
        ' <span> @ </span><span class="sffc-crm-dashboard-company">' +
        data.company +
        "</span></strong>" +
        '<span class="sffc-crm-dashboard-recruiter"><span class="sffc-crm-dashboard-recruiter-name">' +
        data.recruiterName +
        "</span></span>" +
        '<p class="sffc-crm-dashboard-meta">' +
        '<span><svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"></circle><path d="M12 6v6l3 3" fill="none" stroke="currentColor" stroke-width="2"></path></svg>' +
        data.createdAt +
        "</span>" +
        '<span style="padding: 4px 8px; background: #0d353e; color: #fff; border-radius: 4px; font-size: 12px; font-weight: 500;">Queued</span>' +
        "</p></div></div>" +
        '<div class="sffc-crm-dashboard-actions">' +
        '<a href="' +
        (data.viewUrl || "#") +
        '" class="sffc-crm-btn sffc-crm-btn-ghost sffc-crm-view-role-dashboard" target="_blank" rel="noopener noreferrer">View Role</a>' +
        '<button type="button" class="sffc-crm-btn sffc-crm-btn-ghost sffc-crm-remove-tracking" data-application-id="new">Remove</button>' +
        "</div></li>";

      // Prepend to list (newest first)
      $list.prepend(listItemHtml);

      // Add highlight animation
      $list
        .find(".sffc-crm-dashboard-list-item")
        .first()
        .css("background", "#f0fdf4")
        .animate(
          {
            backgroundColor: "transparent",
          },
          2000
        );
    }

    $eventRoot.on("click", ".sffc-crm-request-intro", function (e) {
      e.preventDefault();

      var $button = $(this);
      if ($button.prop("disabled")) {
        return;
      }

      $button.prop("disabled", true).addClass("is-loading");

      $.post(config.ajaxUrl, {
        action: "sffc_crm_request_intro_queue",
        nonce: config.nonce,
        post_id: $button.data("postId"),
        recruiter_id: $button.data("recruiterId"),
        source_type: $button.data("sourceType"),
        role_title: $button.data("roleTitle"),
        company_name: $button.data("company"),
        recruiter_name: $button.data("recruiter"),
        recruiter_email: $button.data("recruiterEmail"),
        recruiter_linkedin: $button.data("recruiterLinkedin"),
        criteria_id:
          $button.data("criteriaId") ||
          $button.closest("[data-criteria-id]").data("criteriaId") ||
          0,
      })
        .done(function (response) {
          if (!response || !response.success) {
            showToast(
              (response && response.data && response.data.message) ||
                (config.intros &&
                  config.intros.strings &&
                  config.intros.strings.error) ||
                "Unable to request intro right now.",
              "error"
            );
            $button.prop("disabled", false).removeClass("is-loading");
            return;
          }

          $button
            .text(
              (config.intros &&
                config.intros.strings &&
                config.intros.strings.requested) ||
                "Requested"
            )
            .addClass("is-requested")
            .attr("data-request-id", response.data.request_id || 0);

          showToast(
            (response.data && response.data.message) ||
              (config.intros &&
                config.intros.strings &&
                config.intros.strings.success) ||
              "Request added to your intro queue.",
            "success"
          );

          if (response.data && response.data.request) {
            prependIntroCard(response.data.request);
          }
        })
        .fail(function (xhr) {
          var message =
            xhr &&
            xhr.responseJSON &&
            xhr.responseJSON.data &&
            xhr.responseJSON.data.message
              ? xhr.responseJSON.data.message
              : (config.intros &&
                  config.intros.strings &&
                  config.intros.strings.error) ||
                "Unable to request intro right now.";
          showToast(message, "error");
          $button.prop("disabled", false).removeClass("is-loading");
        })
        .always(function () {
          $button.removeClass("is-loading");
        });
    });

    $eventRoot.on("click", ".sffc-crm-cancel-intro", function (e) {
      e.preventDefault();

      var $button = $(this);
      var introId = $button.data("introId");
      if (!introId) {
        return;
      }

      $button.prop("disabled", true);
      $.post(config.ajaxUrl, {
        action: "sffc_crm_cancel_intro_queue",
        nonce: config.nonce,
        intro_id: introId,
      })
        .done(function (response) {
          if (!response || !response.success) {
            showToast(
              (response && response.data && response.data.message) ||
                (config.intros &&
                  config.intros.strings &&
                  config.intros.strings.cancelError) ||
                "Unable to cancel this intro request right now.",
              "error"
            );
            $button.prop("disabled", false);
            return;
          }

          $button.closest(".sffc-crm-intro-card").fadeOut(200, function () {
            $(this).remove();
            syncIntroEmptyState();
          });
          showToast(
            (response.data && response.data.message) ||
              (config.intros &&
                config.intros.strings &&
                config.intros.strings.cancelled) ||
              "Intro request cancelled.",
            "success"
          );
        })
        .fail(function () {
          showToast(
            (config.intros &&
              config.intros.strings &&
              config.intros.strings.cancelError) ||
              "Unable to cancel this intro request right now.",
            "error"
          );
          $button.prop("disabled", false);
        });
    });

    $eventRoot.on("click", ".sffc-crm-open-profile-tab", function (e) {
      e.preventDefault();
      $app.find('.sffc-crm-tab[data-tab="profile"]').trigger("click");
    });

    // ============================================
    // Message Dropdown Functionality
    // ============================================

    // Message dropdown toggle
    $eventRoot.on("click", ".sffc-crm-message-toggle", function (e) {
      e.preventDefault();
      e.stopPropagation();

      var $button = $(this);
      var $wrapper = $button.closest(".sffc-crm-message-dropdown-wrapper");
      var $dropdown = $wrapper.find(".sffc-crm-message-dropdown");
      var isOpen = $dropdown.attr("hidden") === undefined;

      // Close all other message dropdowns first
      $(".sffc-crm-message-dropdown").attr("hidden", "hidden");
      $(".sffc-crm-message-toggle").attr("aria-expanded", "false");

      // Toggle this dropdown
      if (!isOpen) {
        $dropdown.removeAttr("hidden");
        $button.attr("aria-expanded", "true");
      } else {
        $dropdown.attr("hidden", "hidden");
        $button.attr("aria-expanded", "false");
      }
    });

    // Message option click
    $eventRoot.on("click", ".sffc-crm-message-option", function (e) {
      e.preventDefault();
      e.stopPropagation();

      var $option = $(this);
      var messageType = $option.data("messageType");
      var $toggle = $option
        .closest(".sffc-crm-message-dropdown-wrapper")
        .find(".sffc-crm-message-toggle");

      if ($option.is("[data-locked-action]")) {
        handleLockedAction($option.data("lockedAction"));
        return;
      }

      var recruiterEmail = $toggle.data("recruiterEmail");
      var recruiterFirst = $toggle.data("recruiterFirst");
      var recruiterName = $toggle.data("recruiterName");
      var recruiterTitle = $toggle.data("recruiterTitle");
      var recruiterAvatar = $toggle.data("recruiterAvatar");
      var recruiterInitial = $toggle.data("recruiterInitial");
      var company = $toggle.data("company");
      var roleDisplay = $toggle.data("roleDisplay");
      var keywordsJson = $toggle.data("keywords");
      var sector = $toggle.data("sector");
      var seniority = $toggle.data("seniority");
      var defaultApplyUrl = $toggle.data("applyUrl");

      // Close the dropdown
      $option.closest(".sffc-crm-message-dropdown").attr("hidden", "hidden");
      $toggle.attr("aria-expanded", "false");

      if (messageType === "apply") {
        var targetUrl = $option.data("applyUrl") || defaultApplyUrl;
        if (targetUrl) {
          window.open(targetUrl, "_blank", "noopener");
        }
        return;
      }

      var cleanRole = sanitizeRoleLabel(roleDisplay, company);
      var subjectRole =
        cleanRole ||
        roleDisplay ||
        i18n.emailSubjectFallback ||
        "this opportunity";
      var subject =
        "Regarding " + subjectRole + (company ? " at " + company : "");
      var body = "";

      if (messageType === "auto") {
        // Generate template with keywords for auto draft
        body = generateEmailTemplate(
          recruiterFirst,
          recruiterName,
          company,
          cleanRole || roleDisplay || "this role",
          keywordsJson,
          sector,
          seniority
        );
      }

      // Open email preview modal for both types
      openEmailModal({
        type: messageType,
        email: recruiterEmail,
        name: recruiterName,
        firstName: recruiterFirst,
        recruiterTitle: recruiterTitle,
        recruiterAvatar: recruiterAvatar,
        recruiterInitial: recruiterInitial,
        company: company,
        role: cleanRole || roleDisplay,
        subject: subject,
        body: body,
      });
    });

    function sanitizeRoleLabel(roleDisplay, company) {
      if (!roleDisplay) {
        return "";
      }
      var clean = String(roleDisplay);
      if (clean.indexOf("@") !== -1) {
        clean = clean.split("@")[0];
      }
      if (company) {
        var escaped = company.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
        var trailingPattern = new RegExp("\\s+at\\s+" + escaped + "$", "i");
        clean = clean.replace(trailingPattern, "");
      }
      return clean.replace(/\s+/g, " ").trim();
    }

    // Generate email template with 40+ variations
    function generateEmailTemplate(
      recruiterFirst,
      recruiterName,
      company,
      role,
      keywordsJson,
      sector,
      seniority
    ) {
      var name = recruiterFirst || recruiterName || "there";

      // Parse keywords
      var skills = [];
      try {
        if (keywordsJson) {
          var keywords = JSON.parse(keywordsJson);
          if (Array.isArray(keywords)) {
            skills = keywords
              .filter(function (kw) {
                return kw.type === "skill";
              })
              .map(function (kw) {
                var label = kw.label || "";
                return label.toLowerCase();
              })
              .slice(0, 4);
          }
        }
      } catch (e) {}

      // Extract 2-3 key skills for templates
      var skill1 = skills[0] || "financial analysis";
      var skill2 = skills[1] || "team collaboration";
      var skill3 = skills[2] || "problem solving";
      var skillList =
        skills.length > 0
          ? skills.slice(0, 3).join(", ")
          : "financial analysis, team collaboration, and problem solving";

      var templates = [
        "Dear " +
          name +
          ",\n\nI hope you are well. I am reaching out to express my interest in the " +
          role +
          " opportunity at " +
          company +
          ".\n\nThrough my experience in " +
          (sector || "finance") +
          ", I have developed solid " +
          skill1 +
          " skills and strong " +
          skill2 +
          " capability. I particularly enjoy working in fast-paced team environments where collaboration is essential to delivering high-quality outcomes.\n\nI would greatly appreciate the opportunity to discuss how my background and motivation could align with your team's current hiring needs.\n\nKind regards",

        "Hi " +
          name +
          ",\n\nI recently came across the " +
          role +
          " opening at " +
          company +
          " and wanted to reach out directly.\n\nMy background includes hands-on exposure to " +
          skill1 +
          " and " +
          skill2 +
          ", alongside a strong interest in " +
          (sector || "finance") +
          ". I take pride in my " +
          skill3 +
          " and my ability to contribute effectively within collaborative teams.\n\nIf you are currently reviewing candidates, I would be very grateful for the chance to share my CV or speak briefly about the opportunity.\n\nBest regards",

        "Hello " +
          name +
          ",\n\nI hope you're doing well. I would like to express my interest in the " +
          role +
          " position at " +
          company +
          " and thought it best to introduce myself.\n\nI have been building strong foundations in " +
          skill1 +
          " and developing my understanding of " +
          skill2 +
          " and " +
          (sector || "finance") +
          ". I am particularly motivated by opportunities where I can apply my " +
          skill3 +
          " while working closely with high-performing teams.\n\nI would greatly appreciate any opportunity to learn more about the role or your recruitment process.\n\nMany thanks",

        "Dear " +
          name +
          ",\n\nI am writing to express my strong interest in the " +
          role +
          " role at " +
          company +
          ".\n\nAs a student passionate about " +
          (sector || "finance") +
          ", I have developed competencies in " +
          skillList +
          ". I am particularly drawn to " +
          company +
          "'s reputation and would be excited to contribute to your team.\n\nWould you be available for a brief conversation to discuss how my skills and enthusiasm might align with this opportunity?\n\nWarm regards",

        "Hi " +
          name +
          ",\n\nI hope this email finds you well. I wanted to reach out regarding the " +
          role +
          " position I saw at " +
          company +
          ".\n\nMy academic and practical experience has equipped me with strong " +
          skill1 +
          " skills, and I have been actively developing my expertise in " +
          skill2 +
          ". I am confident that my dedication to " +
          skill3 +
          " would make me a valuable addition to your team.\n\nI would be very grateful for the opportunity to discuss this role further at your convenience.\n\nBest wishes",

        "Dear " +
          name +
          ",\n\nI am reaching out to express my enthusiasm for the " +
          role +
          " opportunity at " +
          company +
          ".\n\nThroughout my studies and work experience, I have developed practical skills in " +
          skillList +
          ". I am particularly motivated by " +
          company +
          "'s approach to " +
          (sector || "finance") +
          " and would be thrilled to contribute to your team.\n\nMay I send through my CV for your consideration?\n\nKind regards",

        "Hello " +
          name +
          ",\n\nI recently discovered the " +
          role +
          " position at " +
          company +
          " and felt compelled to reach out.\n\nI have been building my expertise in " +
          skill1 +
          " and " +
          skill2 +
          " through both academic projects and practical experience. My strong " +
          skill3 +
          " and collaborative approach would enable me to hit the ground running in this role.\n\nWould you have time for a brief call to discuss the opportunity?\n\nSincerely",

        "Hi " +
          name +
          ",\n\nI hope you're having a great week. I wanted to express my interest in the " +
          role +
          " role at " +
          company +
          ".\n\nMy background includes solid experience with " +
          skill1 +
          " and " +
          skill2 +
          ", and I am passionate about pursuing a career in " +
          (sector || "finance") +
          ". I believe my " +
          skill3 +
          " and dedication would make me a strong fit for your team.\n\nI would love the opportunity to discuss how I could contribute to " +
          company +
          ".\n\nThank you",

        "Dear " +
          name +
          ",\n\nI am writing to apply for the " +
          role +
          " position at " +
          company +
          ", which I believe aligns perfectly with my skills and career aspirations.\n\nI have developed strong capabilities in " +
          skillList +
          " through live execution work, commercially relevant mandates, and hands-on finance experience. I am particularly excited about the opportunity to work with " +
          company +
          "'s team and contribute to your ongoing projects.\n\nWould you be open to reviewing my application?\n\nBest regards",

        "Hello " +
          name +
          ",\n\nI hope this message finds you well. I am reaching out regarding the " +
          role +
          " opportunity at " +
          company +
          ".\n\nMy experience includes practical application of " +
          skill1 +
          " and " +
          skill2 +
          ", which I believe would be directly relevant to this role. I am eager to bring my " +
          skill3 +
          " and collaborative mindset to your team.\n\nI would greatly appreciate the chance to discuss this opportunity further.\n\nWarm wishes",

        "Hi " +
          name +
          ",\n\nI wanted to reach out about the " +
          role +
          " position at " +
          company +
          " that I came across recently.\n\nI have been actively developing my skills in " +
          skillList +
          " and am particularly interested in opportunities within " +
          (sector || "finance") +
          ". I am confident that my enthusiasm and work ethic would make me a valuable team member.\n\nCould we arrange a time to discuss the role?\n\nKind regards",

        "Dear " +
          name +
          ",\n\nI hope you are well. I am writing to express my interest in joining " +
          company +
          " as a " +
          role +
          ".\n\nAcross my finance experience, I have developed strong " +
          skill1 +
          " and " +
          skill2 +
          " skills. I am particularly drawn to " +
          company +
          "'s work in " +
          (sector || "finance") +
          " and would be excited to contribute my " +
          skill3 +
          " to your team.\n\nI would welcome the opportunity to discuss my application with you.\n\nSincerely",

        "Hello " +
          name +
          ",\n\nI am reaching out to express my strong interest in the " +
          role +
          " role at " +
          company +
          ".\n\nMy background includes hands-on experience with " +
          skill1 +
          " and " +
          skill2 +
          ", and I am passionate about building a career in " +
          (sector || "finance") +
          ". I believe my analytical mindset and " +
          skill3 +
          " would enable me to add value to your team from day one.\n\nMay I share my CV with you?\n\nBest regards",

        "Hi " +
          name +
          ",\n\nI hope this email finds you well. I wanted to reach out regarding the " +
          role +
          " opening at " +
          company +
          ".\n\nI have developed strong competencies in " +
          skillList +
          " through commercially relevant work and practical execution experience. I am particularly motivated by " +
          company +
          "'s reputation and would be thrilled to contribute to your team's success.\n\nWould you be available for a brief conversation?\n\nThank you",

        "Dear " +
          name +
          ",\n\nI am writing to express my enthusiasm for the " +
          role +
          " opportunity at " +
          company +
          ".\n\nMy experience includes practical application of " +
          skill1 +
          ", " +
          skill2 +
          ", and " +
          skill3 +
          ". I am eager to bring my dedication and collaborative approach to " +
          company +
          " and contribute meaningfully to your projects.\n\nI would greatly appreciate the opportunity to discuss this role further.\n\nWarm regards",

        "Hello " +
          name +
          ",\n\nI recently learned about the " +
          role +
          " position at " +
          company +
          " and felt it was an excellent match for my skills and interests.\n\nI have been building expertise in " +
          skill1 +
          " and " +
          skill2 +
          ", alongside a strong foundation in " +
          (sector || "finance") +
          ". My " +
          skill3 +
          " and team-oriented mindset would enable me to contribute effectively to your team.\n\nWould you have time to discuss the opportunity?\n\nKind regards",

        "Hi " +
          name +
          ",\n\nI hope you're doing well. I wanted to express my interest in the " +
          role +
          " role at " +
          company +
          ".\n\nAcross my work experience, I have developed solid skills in " +
          skillList +
          ". I am particularly excited about the prospect of working with " +
          company +
          "'s team and applying my knowledge in a practical setting.\n\nI would love to discuss how I could contribute to your team.\n\nBest wishes",

        "Dear " +
          name +
          ",\n\nI am reaching out to express my strong interest in the " +
          role +
          " opportunity at " +
          company +
          ".\n\nMy academic and professional experience has equipped me with strong " +
          skill1 +
          " and " +
          skill2 +
          " capabilities. I am passionate about " +
          (sector || "finance") +
          " and would be honored to contribute my " +
          skill3 +
          " to your team.\n\nMay I send you my CV for consideration?\n\nSincerely",

        "Hello " +
          name +
          ",\n\nI hope this message finds you well. I am writing to express my interest in the " +
          role +
          " position at " +
          company +
          ".\n\nI have developed practical skills in " +
          skillList +
          " through live work, projects, and commercially relevant execution. I am particularly motivated by opportunities to work in collaborative, fast-paced environments like " +
          company +
          ".\n\nI would greatly appreciate the chance to discuss this opportunity with you.\n\nThank you",

        "Hi " +
          name +
          ",\n\nI wanted to reach out about the " +
          role +
          " role at " +
          company +
          ", which I believe aligns well with my background and career goals.\n\nI have hands-on experience with " +
          skill1 +
          " and " +
          skill2 +
          ", and I am eager to further develop my expertise in " +
          (sector || "finance") +
          ". My " +
          skill3 +
          " and dedication would make me a valuable asset to your team.\n\nCould we arrange a time to discuss the role?\n\nWarm regards",

        "Dear " +
          name +
          ",\n\nI hope you are well. I am writing to express my interest in the " +
          role +
          " opportunity at " +
          company +
          ".\n\nMy experience includes strong foundations in " +
          skill1 +
          ", " +
          skill2 +
          ", and " +
          skill3 +
          ". I am particularly drawn to " +
          company +
          "'s approach and would be excited to contribute to your team's ongoing success.\n\nWould you be open to reviewing my application?\n\nKind regards",

        "Hello " +
          name +
          ",\n\nI am reaching out to express my enthusiasm for the " +
          role +
          " position at " +
          company +
          ".\n\nAcross recent mandates and team-based execution work, I have developed strong " +
          skillList +
          " skills. I am motivated by the opportunity to work with high-performing teams and believe I could contribute meaningfully to " +
          company +
          ".\n\nI would love the chance to discuss this opportunity further.\n\nBest regards",

        "Hi " +
          name +
          ",\n\nI hope this email finds you well. I wanted to reach out regarding the " +
          role +
          " opening at " +
          company +
          ".\n\nMy background includes practical experience with " +
          skill1 +
          " and " +
          skill2 +
          ", alongside a strong interest in " +
          (sector || "finance") +
          ". I am confident that my " +
          skill3 +
          " and collaborative approach would enable me to excel in this role.\n\nMay I discuss the opportunity with you?\n\nSincerely",

        "Dear " +
          name +
          ",\n\nI am writing to apply for the " +
          role +
          " position at " +
          company +
          ".\n\nI have developed strong capabilities in " +
          skillList +
          " through practical execution work and commercially relevant experience. I am particularly excited about " +
          company +
          "'s work and would be honored to contribute to your team.\n\nWould you have time for a brief conversation?\n\nWarm wishes",

        "Hello " +
          name +
          ",\n\nI hope you're having a great day. I wanted to express my interest in the " +
          role +
          " role at " +
          company +
          ".\n\nMy experience includes hands-on application of " +
          skill1 +
          ", " +
          skill2 +
          ", and " +
          skill3 +
          ". I am passionate about building a career in " +
          (sector || "finance") +
          " and believe I could be a strong fit for your team.\n\nI would appreciate the opportunity to discuss this role with you.\n\nThank you",

        "Hi " +
          name +
          ",\n\nI recently came across the " +
          role +
          " opportunity at " +
          company +
          " and wanted to reach out.\n\nI have been building my expertise in " +
          skillList +
          " through live work, complex projects, and commercially relevant execution. I am particularly motivated by " +
          company +
          "'s reputation and would be thrilled to contribute to your team's success.\n\nCould we arrange a call to discuss the opportunity?\n\nBest regards",

        "Dear " +
          name +
          ",\n\nI hope this message finds you well. I am reaching out to express my strong interest in the " +
          role +
          " position at " +
          company +
          ".\n\nMy background includes solid experience with " +
          skill1 +
          " and " +
          skill2 +
          ", and I am eager to further develop my skills in " +
          (sector || "finance") +
          ". I believe my " +
          skill3 +
          " and work ethic would make me a valuable team member.\n\nI would love to discuss how I could contribute to " +
          company +
          ".\n\nKind regards",

        "Hello " +
          name +
          ",\n\nI wanted to reach out regarding the " +
          role +
          " role at " +
          company +
          ", which I find very exciting.\n\nI have developed strong " +
          skillList +
          " capabilities through my studies and work experience. I am particularly drawn to opportunities where I can apply these skills in a collaborative, dynamic environment like " +
          company +
          ".\n\nWould you be available to discuss the role?\n\nWarm regards",

        "Hi " +
          name +
          ",\n\nI hope you are well. I am writing to express my interest in joining " +
          company +
          " as a " +
          role +
          ".\n\nMy experience includes practical application of " +
          skill1 +
          " and " +
          skill2 +
          ", alongside a strong foundation in " +
          skill3 +
          ". I am excited about the opportunity to work with your team and contribute to " +
          company +
          "'s ongoing projects.\n\nMay I send you my CV?\n\nSincerely",

        "Dear " +
          name +
          ",\n\nI am reaching out to express my enthusiasm for the " +
          role +
          " opportunity at " +
          company +
          ".\n\nThroughout my academic and professional journey, I have developed strong " +
          skillList +
          " skills. I am particularly motivated by " +
          company +
          "'s approach to " +
          (sector || "finance") +
          " and would be honored to contribute to your team.\n\nI would appreciate the chance to discuss my application with you.\n\nBest wishes",

        "Hello " +
          name +
          ",\n\nI hope this email finds you well. I wanted to reach out about the " +
          role +
          " position at " +
          company +
          ".\n\nI have hands-on experience with " +
          skill1 +
          ", " +
          skill2 +
          ", and " +
          skill3 +
          ", which I believe would be directly relevant to this role. I am eager to bring my analytical mindset and collaborative approach to your team.\n\nWould you have time to discuss the opportunity?\n\nThank you",

        "Hi " +
          name +
          ",\n\nI recently discovered the " +
          role +
          " opening at " +
          company +
          " and felt it was an excellent match for my skills.\n\nMy background includes strong foundations in " +
          skillList +
          ", and I am passionate about pursuing a career in " +
          (sector || "finance") +
          ". I believe my dedication and work ethic would enable me to add value to your team from day one.\n\nCould we arrange a brief call?\n\nKind regards",

        "Dear " +
          name +
          ",\n\nI hope you're doing well. I am writing to express my strong interest in the " +
          role +
          " role at " +
          company +
          ".\n\nI have developed solid capabilities in " +
          skill1 +
          " and " +
          skill2 +
          " through both academic work and practical experience. I am particularly excited about the prospect of working with " +
          company +
          "'s team and contributing to your projects.\n\nI would love to discuss how I could be an asset to your team.\n\nWarm regards",

        "Hello " +
          name +
          ",\n\nI wanted to reach out regarding the " +
          role +
          " opportunity at " +
          company +
          ".\n\nMy experience includes practical application of " +
          skillList +
          ", and I am eager to bring my " +
          skill3 +
          " and team-oriented mindset to " +
          company +
          ". I am confident that my background and enthusiasm would make me a strong fit for this role.\n\nMay I share my CV with you?\n\nBest regards",

        "Hi " +
          name +
          ",\n\nI hope this message finds you well. I am reaching out to express my interest in the " +
          role +
          " position at " +
          company +
          ".\n\nI have been actively developing my skills in " +
          skill1 +
          " and " +
          skill2 +
          ", alongside a strong interest in " +
          (sector || "finance") +
          ". I am particularly motivated by opportunities to work in dynamic, collaborative environments like " +
          company +
          ".\n\nWould you be available for a brief conversation?\n\nSincerely",

        "Dear " +
          name +
          ",\n\nI am writing to apply for the " +
          role +
          " role at " +
          company +
          ", which I believe aligns perfectly with my background.\n\nThroughout my studies, I have developed strong " +
          skillList +
          " capabilities. I am excited about the opportunity to work with " +
          company +
          "'s team and apply my knowledge in a practical setting.\n\nI would appreciate the chance to discuss my application with you.\n\nThank you",

        "Hello " +
          name +
          ",\n\nI hope you're having a great week. I wanted to express my enthusiasm for the " +
          role +
          " opportunity at " +
          company +
          ".\n\nMy background includes hands-on experience with " +
          skill1 +
          ", " +
          skill2 +
          ", and " +
          skill3 +
          ". I am passionate about " +
          (sector || "finance") +
          " and would be thrilled to contribute my skills and dedication to your team.\n\nCould we discuss the role further?\n\nWarm wishes",

        "Hi " +
          name +
          ",\n\nI recently learned about the " +
          role +
          " position at " +
          company +
          " and wanted to reach out directly.\n\nI have developed practical skills in " +
          skillList +
          " through my academic pursuits and work experience. I am particularly drawn to " +
          company +
          "'s reputation and would be honored to contribute to your team's success.\n\nMay I send you my CV?\n\nKind regards",

        "Dear " +
          name +
          ",\n\nI hope this email finds you well. I am reaching out to express my strong interest in the " +
          role +
          " role at " +
          company +
          ".\n\nMy experience includes solid foundations in " +
          skill1 +
          " and " +
          skill2 +
          ", and I am eager to further develop my expertise in " +
          (sector || "finance") +
          ". I believe my " +
          skill3 +
          " and collaborative mindset would make me a valuable addition to your team.\n\nI would love the opportunity to discuss this role with you.\n\nBest regards",

        "Hello " +
          name +
          ",\n\nI wanted to reach out about the " +
          role +
          " opportunity at " +
          company +
          ", which I find very exciting.\n\nI have been building my expertise in " +
          skillList +
          " through both coursework and practical experience. I am motivated by the opportunity to work with high-performing teams and believe I could contribute meaningfully to " +
          company +
          ".\n\nWould you have time for a brief call?\n\nSincerely",

        "Hi " +
          name +
          ",\n\nI hope you are well. I am writing to express my interest in joining " +
          company +
          " as a " +
          role +
          ".\n\nMy background includes practical application of " +
          skill1 +
          ", " +
          skill2 +
          ", and " +
          skill3 +
          ". I am particularly excited about " +
          company +
          "'s work in " +
          (sector || "finance") +
          " and would be eager to contribute to your team.\n\nI would appreciate the chance to discuss my application.\n\nWarm regards",
      ];

      // Pick random template
      var templateIndex = Math.floor(Math.random() * templates.length);
      return templates[templateIndex];
    }

    $("body").on("click", "[data-dashboard-modal-close]", function (e) {
      e.preventDefault();
      closeDashboardModal();
    });

    initLiveFeedTicker();

    // Close dropdown on ESC key + modals
    $(document).on("keydown", function (e) {
      if (e.key === "Escape") {
        if (dashboardModal && dashboardModal.$modal.hasClass("is-open")) {
          closeDashboardModal();
          return;
        }
        // Close prep dropdown
        $(".sffc-crm-prep-dropdown").attr("hidden", "hidden");
        $(".sffc-crm-prep-toggle").attr("aria-expanded", "false");
        // Close message dropdown
        $(".sffc-crm-message-dropdown").attr("hidden", "hidden");
        $(".sffc-crm-message-toggle").attr("aria-expanded", "false");
        // Close profile dropdown
        $(".sffc-crm-profile-toggle").attr("aria-expanded", "false");
        $(".sffc-user-menu").removeClass("active");
      }
    });

    // ============================================
    // Profile View Functionality
    // ============================================

    // Section Edit Mode Toggle
    $eventRoot.on(
      "click",
      ".sffc-crm-section-edit-btn, .sffc-profile-edit-btn",
      function (e) {
        e.preventDefault();
        var $btn = $(this);
        var $section = $btn.closest(
          ".sffc-crm-profile-section, .sffc-crm-profile-header"
        );
        var $content = $section.find(
          ".sffc-crm-profile-content, .sffc-crm-profile-header-info"
        );

        if ($section.hasClass("sffc-editing")) {
          // Cancel edit mode
          $section.removeClass("sffc-editing");
          // Reload content or revert changes (simplified for now)
        } else {
          // Enter edit mode
          $section.addClass("sffc-editing");

          // Make field values editable
          $content.find(".sffc-crm-field-value").each(function () {
            var $field = $(this);
            if (!$field.find("input, select").length) {
              var currentValue = $field.text().trim();
              var $input = $(
                '<input type="text" class="sffc-crm-field-input" value="' +
                  currentValue +
                  '">'
              );
              $field.html($input);
            }
          });

          // Add save/cancel buttons if not present
          if (!$section.find(".sffc-crm-edit-actions").length) {
            var $actions = $(
              '<div class="sffc-crm-edit-actions">' +
                '<button type="button" class="sffc-crm-btn sffc-crm-btn-primary sffc-crm-btn-sm sffc-save-section">Save</button>' +
                '<button type="button" class="sffc-crm-btn sffc-crm-btn-secondary sffc-crm-btn-sm sffc-cancel-edit">Cancel</button>' +
                "</div>"
            );
            $content.append($actions);
          }
        }
      }
    );

    // Save Section
    $eventRoot.on("click", ".sffc-save-section", function (e) {
      e.preventDefault();
      var $btn = $(this);
      var $section = $btn.closest(
        ".sffc-crm-profile-section, .sffc-crm-profile-header"
      );
      var $content = $section.find(
        ".sffc-crm-profile-content, .sffc-crm-profile-header-info"
      );

      // Collect form data
      var formData = {};
      $content.find(".sffc-crm-field-input").each(function () {
        var $input = $(this);
        var $field = $input.closest(".sffc-crm-profile-field");
        var label = $field.find("label").text().trim();
        formData[label] = $input.val();
      });

      // Make values read-only again
      $content.find(".sffc-crm-field-value").each(function () {
        var $field = $(this);
        var $input = $field.find("input");
        if ($input.length) {
          var newValue = $input.val();
          $field.text(newValue);
        }
      });

      // Remove edit actions
      $section.find(".sffc-crm-edit-actions").remove();
      $section.removeClass("sffc-editing");

      // AJAX save (if config available)
      if (config.ajaxUrl) {
        $.post(config.ajaxUrl, {
          action: "sffc_crm_save_profile",
          nonce: config.nonce,
          data: formData,
        })
          .done(function (response) {
            // Show success message
            showToast("Profile updated successfully", "success");
          })
          .fail(function () {
            showToast("Error saving profile", "error");
          });
      }
    });

    // Cancel Edit
    $eventRoot.on("click", ".sffc-cancel-edit", function (e) {
      e.preventDefault();
      var $btn = $(this);
      var $section = $btn.closest(
        ".sffc-crm-profile-section, .sffc-crm-profile-header"
      );
      $section.removeClass("sffc-editing");
      $section.find(".sffc-crm-edit-actions").remove();

      // Reload or revert (simplified - just reload page for now)
      location.reload();
    });

    // Tag functionality moved to crm-profile.js

    // Document Upload
    $eventRoot.on(
      "click",
      '.sffc-crm-btn-primary[class*="Upload"], button:contains("Upload")',
      function (e) {
        e.preventDefault();

        // Create file input dynamically
        var $fileInput = $(
          '<input type="file" accept=".pdf,.doc,.docx,.xlsx,.xls" style="display:none;">'
        );
        $("body").append($fileInput);

        $fileInput.on("change", function () {
          var file = this.files[0];
          if (file) {
            uploadDocument(file);
          }
          $(this).remove();
        });

        $fileInput.trigger("click");
      }
    );

    // Document Delete
    $eventRoot.on("click", ".sffc-crm-document-delete", function (e) {
      e.preventDefault();
      var $btn = $(this);
      var $card = $btn.closest(".sffc-crm-document-card");
      var docName = $btn.data("document-name") || $card.data("document-name");

      if (confirm('Delete "' + docName + '"?')) {
        // AJAX delete first
        if (config.ajaxUrl) {
          $.post(config.ajaxUrl, {
            action: "sffc_crm_delete_document",
            nonce: config.nonce,
            document_name: docName,
          })
            .done(function (response) {
              if (response && response.success) {
                $card.fadeOut(300, function () {
                  $(this).remove();
                  // Show empty state if no more documents
                  if ($(".sffc-crm-document-card").length === 0) {
                    $(".sffc-crm-documents-grid").html(
                      '<div class="sffc-crm-empty-state">' +
                        "<p>No documents uploaded yet. Click the Upload button to add your CV, cover letters, or other documents.</p>" +
                        "</div>"
                    );
                  }
                });
                showToast("Document deleted", "success");
              } else {
                showToast("Failed to delete document", "error");
              }
            })
            .fail(function () {
              showToast("Failed to delete document", "error");
            });
        } else {
          $card.fadeOut(300, function () {
            $(this).remove();
          });
        }
      }
    });

    // Document type toggle
    $eventRoot.on("click", ".sffc-crm-document-type-toggle", function (e) {
      e.preventDefault();
      var $button = $(this);
      var nextType = $button.data("documentType");
      var documentName = $button.data("documentName");

      $button.prop("disabled", true);

      $.post(config.ajaxUrl, {
        action: "sffc_crm_set_document_type",
        nonce: config.nonce,
        document_name: documentName,
        document_type: nextType,
      })
        .done(function (response) {
          if (!response || !response.success) {
            showToast(
              (response && response.data && response.data.message) ||
                (config.intros &&
                  config.intros.strings &&
                  config.intros.strings.docTypeError) ||
                "Unable to update document type right now.",
              "error"
            );
            $button.prop("disabled", false);
            return;
          }

          location.reload();
        })
        .fail(function () {
          showToast(
            (config.intros &&
              config.intros.strings &&
              config.intros.strings.docTypeError) ||
              "Unable to update document type right now.",
            "error"
          );
          $button.prop("disabled", false);
        });
    });

    $eventRoot.on("click", "[data-criteria-open]", function (e) {
      e.preventDefault();
      openCriteriaModal();
    });

    $eventRoot.on("click", "[data-criteria-close]", function (e) {
      e.preventDefault();
      closeCriteriaModal();
    });

    $eventRoot.on("click", "[data-criteria-edit]", function (e) {
      e.preventDefault();
      var raw =
        $(this).closest("[data-criteria-card]").attr("data-criteria") || "{}";
      var criteria = {};
      try {
        criteria = JSON.parse(raw);
      } catch (err) {
        criteria = {};
      }
      openCriteriaModal(criteria);
    });

    $eventRoot.on("click", "[data-onboarding-option]", function (e) {
      e.preventDefault();
      var $option = $(this);
      var group = $option.attr("data-onboarding-option");
      var value = $option.attr("data-value") || $.trim($option.text());
      var $form = $option.closest("[data-matches-onboarding-form]");
      var stepOrder = ["role", "location", "experience", "contact"];
      var stepIndex = stepOrder.indexOf(group);

      $form
        .find('[data-onboarding-option="' + group + '"]')
        .removeClass("is-selected");
      $option.addClass("is-selected");

      if (group === "role" && value === "Other") {
        var $otherWrap = $form.find("[data-onboarding-other-role]");
        var $otherInput = $form.find("[data-onboarding-other-role-input]");
        $otherWrap.removeAttr("hidden");
        $form
          .find('[data-onboarding-value="role"]')
          .val($.trim($otherInput.val()))
          .trigger("change");
        $otherInput.trigger("focus");
        updateMatchesOnboardingSubmit($form);
        return;
      }

      if (group === "role") {
        $form.find("[data-onboarding-other-role]").attr("hidden", "hidden");
        $form.find("[data-onboarding-other-role-input]").val("");
      }

      $form
        .find('[data-onboarding-value="' + group + '"]')
        .val(value)
        .trigger("change");

      if (stepIndex > -1 && stepIndex < stepOrder.length - 1) {
        setMatchesOnboardingStep($form, stepOrder[stepIndex + 1]);
      }
      updateMatchesOnboardingSubmit($form);
    });

    $eventRoot.on("input", "[data-onboarding-other-role-input]", function () {
      var $input = $(this);
      var $form = $input.closest("[data-matches-onboarding-form]");
      var value = $.trim($input.val());
      $form.find('[data-onboarding-value="role"]').val(value).trigger("change");
      updateMatchesOnboardingSubmit($form);
    });

    $eventRoot.on("input", "[data-onboarding-contact]", function () {
      updateMatchesOnboardingSubmit(
        $(this).closest("[data-matches-onboarding-form]")
      );
    });

    $eventRoot.on(
      "keydown",
      "[data-onboarding-other-role-input]",
      function (e) {
        if (e.key !== "Enter") {
          return;
        }
        e.preventDefault();
        var $input = $(this);
        var $form = $input.closest("[data-matches-onboarding-form]");
        var value = $.trim($input.val());
        if (!value) {
          return;
        }
        $form
          .find('[data-onboarding-value="role"]')
          .val(value)
          .trigger("change");
        setMatchesOnboardingStep($form, "location");
        updateMatchesOnboardingSubmit($form);
      }
    );

    $eventRoot.on("click", "[data-onboarding-other-next]", function (e) {
      e.preventDefault();
      var $form = $(this).closest("[data-matches-onboarding-form]");
      var $input = $form.find("[data-onboarding-other-role-input]");
      var value = $.trim($input.val());
      if (!value) {
        $input.trigger("focus");
        return;
      }
      $form.find('[data-onboarding-value="role"]').val(value).trigger("change");
      setMatchesOnboardingStep($form, "location");
      updateMatchesOnboardingSubmit($form);
    });

    $eventRoot.on("submit", "[data-criteria-form]", function (e) {
      e.preventDefault();
      var $form = $(this);
      var $feedback = $form.find("[data-criteria-feedback]");
      if (
        $form.is("[data-matches-onboarding-form]") &&
        !validateMatchesOnboardingForm($form, $feedback)
      ) {
        return;
      }

      if ($form.is("[data-matches-onboarding-form]") && !config.isLoggedIn) {
        $feedback
          .prop("hidden", false)
          .text(
            "Your matches are ready. Create your MENA Careers account to save them."
          );
        return;
      }

      var formData = new FormData(this);
      formData.append("action", "sffc_crm_save_user_criteria");
      formData.append("nonce", config.nonce);

      $feedback.prop("hidden", false).text("Saving criteria...");
      $form.find('button[type="submit"]').prop("disabled", true);

      $.ajax({
        url: config.ajaxUrl,
        type: "POST",
        data: formData,
        processData: false,
        contentType: false,
      })
        .done(function (response) {
          if (!response || !response.success) {
            $feedback.text(
              (response && response.data && response.data.message) ||
                "Unable to save criteria."
            );
            return;
          }
          showToast("Criteria saved", "success");
          window.location.href = updateQueryString(
            "tab",
            $form.attr("data-criteria-redirect") || "following"
          );
        })
        .fail(function () {
          $feedback.text("Unable to save criteria right now.");
        })
        .always(function () {
          $form.find('button[type="submit"]').prop("disabled", false);
        });
    });

    $eventRoot.on("click", "[data-criteria-delete]", function (e) {
      e.preventDefault();
      var $button = $(this);
      var criteriaId = $button.data("criteriaId");
      if (!criteriaId || !window.confirm("Delete this search criteria?")) {
        return;
      }

      $button.prop("disabled", true);
      $.post(config.ajaxUrl, {
        action: "sffc_crm_delete_user_criteria",
        nonce: config.nonce,
        criteria_id: criteriaId,
      })
        .done(function (response) {
          if (!response || !response.success) {
            showToast(
              (response && response.data && response.data.message) ||
                "Unable to delete criteria.",
              "error"
            );
            $button.prop("disabled", false);
            return;
          }
          $button.closest("[data-criteria-card]").remove();
          showToast("Criteria deleted", "success");
        })
        .fail(function () {
          showToast("Unable to delete criteria right now.", "error");
          $button.prop("disabled", false);
        });
    });

    $eventRoot.on("keydown", "[data-keyword-search]", function (e) {
      if (e.key !== "Enter") {
        return;
      }
      e.preventDefault();
      var value = $(this).val();
      if (value) {
        addCriteriaKeyword($(this).closest("[data-keyword-picker]"), value);
        $(this).val("");
        renderKeywordSuggestions($(this).closest("[data-keyword-picker]"), "");
      }
    });

    $eventRoot.on("input", "[data-keyword-search]", function () {
      renderKeywordSuggestions(
        $(this).closest("[data-keyword-picker]"),
        $(this).val()
      );
    });

    $eventRoot.on("click", "[data-keyword-suggestion]", function (e) {
      e.preventDefault();
      var $picker = $(this).closest("[data-keyword-picker]");
      addCriteriaKeyword($picker, $(this).data("keyword") || $(this).text());
      $picker.find("[data-keyword-search]").val("").focus();
      renderKeywordSuggestions($picker, "");
    });

    $eventRoot.on("click", "[data-keyword-remove]", function (e) {
      e.preventDefault();
      var $picker = $(this).closest("[data-keyword-picker]");
      var value = $(this).data("keyword");
      var keywords = getCriteriaKeywords($picker).filter(function (keyword) {
        return keyword.toLowerCase() !== String(value).toLowerCase();
      });
      setCriteriaKeywords($picker, keywords);
    });

    // Document Download
    $eventRoot.on(
      "click",
      '.sffc-crm-document-card .sffc-crm-icon-btn[title*="Download"]',
      function (e) {
        e.preventDefault();
        // Trigger download (simplified)
        showToast("Download started", "info");
      }
    );

    // ============================================
    // Helper Functions
    // ============================================

    // saveTagData function moved to crm-profile.js

    function openCriteriaModal(criteria) {
      var $modal = $app.find("[data-criteria-modal]");
      var $form = $modal.find("[data-criteria-form]");
      if (!$modal.length || !$form.length) {
        return;
      }

      criteria = criteria || {};
      $form[0].reset();
      $form.find('[name="criteria_id"]').val(criteria.id || "");
      $form.find('[name="name"]').val(criteria.name || "");
      $form.find('[name="job_title"]').val(criteria.job_title || "");
      $form.find('[name="location"]').val((criteria.location || []).join(", "));
      $form
        .find('[name="years_experience"]')
        .val(criteria.years_experience || "");
      setCriteriaKeywords(
        $form.find("[data-keyword-picker]"),
        criteria.skills_keywords || []
      );
      $form.find('[name="sector[]"]').val(criteria.sector || []);
      $form
        .find('[name="experience_level[]"]')
        .val(criteria.experience_level || []);
      $form.find("[data-criteria-cv-existing]").val(criteria.cv_file_id || "");
      $form
        .find("[data-criteria-cover-existing]")
        .val(criteria.cover_letter_file_id || "");
      $form.find("[data-criteria-feedback]").prop("hidden", true).text("");
      $modal
        .find("[data-criteria-modal-title]")
        .text(criteria.id ? "Edit Search Criteria" : "Create Search Criteria");
      $modal.removeAttr("hidden").addClass("is-open");
    }

    function closeCriteriaModal() {
      $app
        .find("[data-criteria-modal]")
        .attr("hidden", "hidden")
        .removeClass("is-open");
    }

    function setMatchesOnboardingStep($form, step) {
      $form.find("[data-onboarding-step]").each(function () {
        var $step = $(this);
        var isActive = $step.attr("data-onboarding-step") === step;
        $step.toggleClass("is-active", isActive).prop("hidden", !isActive);
      });
    }

    function updateMatchesOnboardingSubmit($form) {
      var hasRole =
        $.trim($form.find('[data-onboarding-value="role"]').val()) !== "";
      var hasLocation =
        $.trim($form.find('[data-onboarding-value="location"]').val()) !== "";
      var hasExperience =
        $.trim($form.find('[data-onboarding-value="experience"]').val()) !== "";
      var hasName =
        $.trim($form.find('[data-onboarding-contact="name"]').val()) !== "";
      var email = $.trim($form.find('[data-onboarding-contact="email"]').val());
      var hasEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
      $form
        .find("[data-onboarding-submit]")
        .prop(
          "disabled",
          !(hasRole && hasLocation && hasExperience && hasName && hasEmail)
        );
    }

    function validateMatchesOnboardingForm($form, $feedback) {
      var hasRole =
        $.trim($form.find('[data-onboarding-value="role"]').val()) !== "";
      var hasLocation =
        $.trim($form.find('[data-onboarding-value="location"]').val()) !== "";
      var hasExperience =
        $.trim($form.find('[data-onboarding-value="experience"]').val()) !== "";
      var hasName =
        $.trim($form.find('[data-onboarding-contact="name"]').val()) !== "";
      var email = $.trim($form.find('[data-onboarding-contact="email"]').val());
      var hasEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
      if (hasRole && hasLocation && hasExperience && hasName && hasEmail) {
        return true;
      }

      var missingStep = !hasRole
        ? "role"
        : !hasLocation
        ? "location"
        : !hasExperience
        ? "experience"
        : "contact";
      setMatchesOnboardingStep($form, missingStep);
      $feedback
        .prop("hidden", false)
        .text("Complete each step to get started.");
      return false;
    }

    function ensureMatchKanbanLists() {
      $('.sffc-crm-linkedin-panel[data-panel="matches"] .group-list').each(
        function (index) {
          var $list = $(this);
          if (!$list.attr("data-drop-list-id")) {
            $list.attr("data-drop-list-id", "match-list-" + index);
          }
        }
      );
    }

    function getApplicationsList() {
      ensureMatchKanbanLists();
      return $(
        '.sffc-crm-linkedin-panel[data-panel="matches"] [data-applications-list]'
      ).first();
    }

    function removeMatchEverywhere($card) {
      if (!$card.length) {
        return;
      }

      var postId = String($card.data("postId") || $card.data("matchId") || "");
      if (postId) {
        $('.sffc-crm-linkedin-panel[data-panel="matches"] .match-card')
          .filter(function () {
            var candidateId = String(
              $(this).data("postId") || $(this).data("matchId") || ""
            );
            return candidateId === postId;
          })
          .not($card)
          .remove();
      }

      if ($card.closest("[data-applications-list]").length) {
        persistApplicationQueueRemoval($card);
        return;
      }

      $card.remove();
      updateApplicationsLaneState();
    }

    function moveMatchCardToList($card, $targetList) {
      if (!$card.length || !$targetList.length) {
        return;
      }
      if ($card.hasClass("is-saving")) {
        return;
      }

      ensureMatchKanbanLists();

      var isApplicationsList = $targetList.is("[data-applications-list]");
      var $currentList = $card.closest(".group-list");
      if ($currentList.length && $currentList[0] === $targetList[0]) {
        return;
      }
      var $previousList = $currentList;
      var previousStatus = $.trim($card.find(".status-select").first().text());
      var wasInApplications = $currentList.is("[data-applications-list]");
      if (
        isApplicationsList &&
        !$card.attr("data-origin-list-id") &&
        $currentList.length &&
        !$currentList.is("[data-applications-list]")
      ) {
        $card.attr(
          "data-origin-list-id",
          $currentList.attr("data-drop-list-id") || ""
        );
      }
      if (!$card.attr("data-original-status")) {
        $card.attr(
          "data-original-status",
          $.trim($card.find(".status-select").first().text())
        );
      }

      if (isApplicationsList) {
        $targetList.prepend($card);
        $card.addClass("is-newly-added");
        window.setTimeout(function () {
          $card.removeClass("is-newly-added");
        }, 4000);
      } else {
        $targetList.append($card);
      }
      $card
        .toggleClass("is-in-applications", isApplicationsList)
        .removeClass("is-dragging");
      $card
        .find(".status-select")
        .first()
        .text(
          isApplicationsList
            ? "Queued"
            : $card.attr("data-original-status") || "Ready to apply"
        );
      if (!isApplicationsList) {
        $card.removeAttr("data-origin-list-id");
        $card.prop("hidden", false).removeClass("is-filtered-out");
      }

      updateApplicationsLaneState();
      persistApplicationQueueMove(
        $card,
        isApplicationsList ? "queued" : "cancelled",
        $previousList,
        previousStatus,
        wasInApplications
      );
    }

    function persistApplicationQueueRemoval($card) {
      if (!$card.length || $card.hasClass("is-saving")) {
        return;
      }

      var $previousList = $card.closest(".group-list");
      var previousStatus = $.trim($card.find(".status-select").first().text());
      $card.addClass("is-saving");

      $.post(config.ajaxUrl, {
        action: "sffc_crm_save_application_queue",
        nonce: config.nonce,
        post_id: $card.data("postId") || $card.data("matchId"),
        criteria_id: $card.data("criteriaId") || 0,
        role_title: $card.data("roleTitle") || "",
        company: $card.data("company") || "",
        recruiter_name: $card.data("recruiterName") || "",
        recruiter_email: $card.data("recruiterEmail") || "",
        application_url: $card.data("applicationUrl") || "",
        queue_status: "cancelled",
        queue_position: 0,
      })
        .done(function (response) {
          if (!response || !response.success) {
            showToast(
              (response && response.data && response.data.message) ||
                "Unable to remove application from queue.",
              "error"
            );
            $card
              .find(".status-select")
              .first()
              .text(previousStatus || "Queued");
            return;
          }
          $card.remove();
          updateApplicationsLaneState();
          if (response.data && response.data.credit_summary) {
            syncApplyAllCreditMeters(response.data.credit_summary);
          }
          showToast(
            response.data && response.data.message
              ? response.data.message
              : "Application removed from queue.",
            "success"
          );
        })
        .fail(function (xhr) {
          var message =
            xhr &&
            xhr.responseJSON &&
            xhr.responseJSON.data &&
            xhr.responseJSON.data.message
              ? xhr.responseJSON.data.message
              : "Unable to remove application from queue.";
          showToast(message, "error");
        })
        .always(function () {
          $card.removeClass("is-saving");
          updateApplicationsLaneState();
        });
    }

    function persistApplicationQueueMove(
      $card,
      queueStatus,
      $previousList,
      previousStatus,
      wasInApplications
    ) {
      var postId = $card.data("postId") || $card.data("matchId");
      if (!postId) {
        rollbackApplicationQueueMove(
          $card,
          $previousList,
          previousStatus,
          wasInApplications
        );
        showToast("Unable to save queue change: missing role ID.", "error");
        return;
      }

      $card.addClass("is-saving");
      $.post(config.ajaxUrl, {
        action: "sffc_crm_save_application_queue",
        nonce: config.nonce,
        post_id: postId,
        criteria_id: $card.data("criteriaId") || 0,
        role_title: $card.data("roleTitle") || "",
        company: $card.data("company") || "",
        recruiter_name: $card.data("recruiterName") || "",
        recruiter_email: $card.data("recruiterEmail") || "",
        application_url: $card.data("applicationUrl") || "",
        queue_status: queueStatus,
        queue_position: queueStatus === "queued" ? 0 : $card.index() + 1,
      })
        .done(function (response) {
          if (!response || !response.success) {
            rollbackApplicationQueueMove(
              $card,
              $previousList,
              previousStatus,
              wasInApplications
            );
            showToast(
              (response && response.data && response.data.message) ||
                "Unable to save queue change.",
              "error"
            );
            return;
          }

          if (response.data && response.data.application_id) {
            $card.attr("data-application-id", response.data.application_id);
          }
          if (response.data && response.data.credit_summary) {
            syncApplyAllCreditMeters(response.data.credit_summary);
          }
          showToast(
            response.data && response.data.message
              ? response.data.message
              : "Queue saved",
            "success"
          );
        })
        .fail(function (xhr) {
          rollbackApplicationQueueMove(
            $card,
            $previousList,
            previousStatus,
            wasInApplications
          );
          var message =
            xhr &&
            xhr.responseJSON &&
            xhr.responseJSON.data &&
            xhr.responseJSON.data.message
              ? xhr.responseJSON.data.message
              : "Unable to save queue change.";
          showToast(message, "error");
        })
        .always(function () {
          $card.removeClass("is-saving");
          updateApplicationsLaneState();
        });
    }

    function rollbackApplicationQueueMove(
      $card,
      $previousList,
      previousStatus,
      wasInApplications
    ) {
      if ($previousList && $previousList.length) {
        $previousList.append($card);
      }
      $card.toggleClass("is-in-applications", !!wasInApplications);
      $card
        .find(".status-select")
        .first()
        .text(
          previousStatus ||
            $card.attr("data-original-status") ||
            "Ready to apply"
        );
      updateApplicationsLaneState();
    }

    function openMobileApplicationsModal() {
      $(
        '.sffc-crm-linkedin-panel[data-panel="matches"] [data-applications-lane]'
      ).addClass("is-mobile-open");
      $("body").addClass("sffc-crm-applications-modal-open");
    }

    function closeMobileApplicationsModal() {
      $(
        '.sffc-crm-linkedin-panel[data-panel="matches"] [data-applications-lane]'
      ).removeClass("is-mobile-open");
      $("body").removeClass("sffc-crm-applications-modal-open");
    }

    function updateApplicationsLaneState() {
      ensureMatchKanbanLists();

      var $applicationsList = $(
        '.sffc-crm-linkedin-panel[data-panel="matches"] [data-applications-list]'
      ).first();
      var $applicationsCards = $applicationsList.children(".match-card");
      var count = $applicationsCards.length;
      var label =
        count === 1
          ? "1 role queued"
          : count
          ? count + " roles queued"
          : "Drop roles here to build your queue";
      var $applicationsEmpty = $(
        '.sffc-crm-linkedin-panel[data-panel="matches"] [data-applications-empty]'
      );

      $(
        '.sffc-crm-linkedin-panel[data-panel="matches"] [data-applications-count]'
      ).text(label);
      $(
        '.sffc-crm-linkedin-panel[data-panel="matches"] [data-mobile-applications-count]'
      ).text(count);
      $applicationsList.toggleClass("is-empty", count === 0);
      $applicationsEmpty
        .prop("hidden", count > 0)
        .attr("aria-hidden", count > 0 ? "true" : "false")
        .toggle(count === 0);

      $('.sffc-crm-linkedin-panel[data-panel="matches"] .group-list')
        .not("[data-applications-list]")
        .each(function () {
          var $list = $(this);
          $list
            .find(".group-empty")
            .prop("hidden", $list.children(".match-card").length > 0);
        });

      updateApplyAllCreditMeters(count);
      updateMatchStrengthFilter();
    }

    function getApplyAllRemainingCredits() {
      return Number.MAX_SAFE_INTEGER;
    }

    function limitCardsByApplyAllCredits($cards) {
      return $cards;
    }

    function updateApplyAllCreditMeters(currentQueuedCount) {
      var queuedCount =
        typeof currentQueuedCount === "number"
          ? currentQueuedCount
          : $(
              '.sffc-crm-linkedin-panel[data-panel="matches"] [data-applications-list]'
            )
              .first()
              .children(".match-card").length;

      $("[data-apply-credit-meter]").each(function () {
        var $meter = $(this);
        var limit = parseInt(
          $meter.attr("data-credit-limit") || $meter.data("creditLimit"),
          10
        );
        var initialUsed = parseInt(
          $meter.attr("data-credit-used") || $meter.data("creditUsed"),
          10
        );
        if (isNaN(limit)) {
          limit = 0;
        }
        if (isNaN(initialUsed)) {
          initialUsed = 0;
        }

        if (!$meter.attr("data-credit-base-used")) {
          $meter.attr("data-credit-base-used", initialUsed);
          $meter.attr("data-credit-base-queued", queuedCount);
        }

        var baseUsed = parseInt($meter.attr("data-credit-base-used"), 10);
        var baseQueued = parseInt($meter.attr("data-credit-base-queued"), 10);
        if (isNaN(baseUsed)) {
          baseUsed = initialUsed;
        }
        if (isNaN(baseQueued)) {
          baseQueued = 0;
        }

        var used = Math.max(0, baseUsed - baseQueued + queuedCount);
        var percent =
          limit > 0 ? Math.min(100, Math.round((used / limit) * 100)) : 0;
        var meta =
          limit > 0
            ? used + " of " + limit + " Recruiter Intro Credits"
            : "No active credits";

        $meter.attr("data-credit-used", used);
        $meter.find("[data-credit-percent]").text(percent + "% Used");
        $meter.find("[data-credit-bar]").css("width", percent + "%");
        $meter.find("[data-credit-meta]").text(meta);
      });
    }

    function syncApplyAllCreditMeters(summary) {
      if (!summary) {
        return;
      }

      var limit = parseInt(summary.limit, 10);
      var used = parseInt(summary.used, 10);
      var percent = parseInt(summary.percent, 10);
      if (isNaN(limit)) {
        limit = 0;
      }
      if (isNaN(used)) {
        used = 0;
      }
      if (isNaN(percent)) {
        percent =
          limit > 0 ? Math.min(100, Math.round((used / limit) * 100)) : 0;
      }

      $("[data-apply-credit-meter]").each(function () {
        var $meter = $(this);
        var meta =
          limit > 0
            ? used + " of " + limit + " Recruiter Intro Credits"
            : "No active credits";
        $meter
          .addClass("is-updating")
          .attr("data-credit-used", used)
          .attr("data-credit-limit", limit)
          .attr("data-credit-base-used", used)
          .attr(
            "data-credit-base-queued",
            $(
              '.sffc-crm-linkedin-panel[data-panel="matches"] [data-applications-list]'
            )
              .first()
              .children(".match-card").length
          );
        $meter.find("[data-credit-percent]").text(percent + "% Used");
        $meter.find("[data-credit-bar]").css("width", percent + "%");
        $meter.find("[data-credit-meta]").text(meta);
        window.setTimeout(function () {
          $meter.removeClass("is-updating");
        }, 450);
      });
    }

    function updateMatchStrengthFilter($scope) {
      var $dashboard =
        $scope && $scope.length
          ? $scope
          : $(
              '.sffc-crm-linkedin-panel[data-panel="matches"] .matches-dashboard'
            ).first();
      if (!$dashboard.length) {
        return;
      }

      var $slider = $dashboard.find("[data-match-strength-slider]").first();
      if (!$slider.length) {
        return;
      }

      var threshold = parseInt($slider.val(), 10);
      if (isNaN(threshold)) {
        threshold = 75;
      }

      $dashboard.find("[data-match-strength-value]").text(threshold + "%");

      var visibleCount = 0;
      var totalCount = 0;
      $dashboard
        .find("[data-match-source-board] .match-card")
        .each(function () {
          var $card = $(this);
          totalCount++;
          var rawScore =
            $card.attr("data-match-score") || $card.data("matchScore");
          var score = parseInt(rawScore, 10);
          var hasScore =
            rawScore !== undefined &&
            rawScore !== null &&
            rawScore !== "" &&
            !isNaN(score);
          if (!hasScore) {
            score = threshold;
          }
          var shouldShow = score >= threshold;
          $card
            .prop("hidden", !shouldShow)
            .toggleClass("is-filtered-out", !shouldShow);
          if (shouldShow) {
            visibleCount++;
          }
        });

      $dashboard.find("[data-visible-match-count]").text(visibleCount);
      $dashboard.find("[data-match-empty]").prop("hidden", totalCount > 0);
      $dashboard
        .find("[data-match-filter-empty]")
        .prop("hidden", totalCount === 0 || visibleCount > 0);
    }

    function getCriteriaKeywords($picker) {
      var raw = $picker.find("[data-keyword-input]").val() || "[]";
      try {
        var parsed = JSON.parse(raw);
        return Array.isArray(parsed) ? parsed : [];
      } catch (err) {
        return raw
          ? raw
              .split(",")
              .map(function (item) {
                return item.trim();
              })
              .filter(Boolean)
          : [];
      }
    }

    function setCriteriaKeywords($picker, keywords) {
      keywords = (keywords || [])
        .map(function (keyword) {
          return String(keyword || "").trim();
        })
        .filter(Boolean);

      var unique = [];
      keywords.forEach(function (keyword) {
        var exists = unique.some(function (saved) {
          return saved.toLowerCase() === keyword.toLowerCase();
        });
        if (!exists) {
          unique.push(keyword);
        }
      });

      $picker.find("[data-keyword-input]").val(JSON.stringify(unique));
      renderCriteriaKeywordTags($picker, unique);
    }

    function addCriteriaKeyword($picker, keyword) {
      keyword = String(keyword || "").trim();
      if (!keyword) {
        return;
      }
      var keywords = getCriteriaKeywords($picker);
      keywords.push(keyword);
      setCriteriaKeywords($picker, keywords);
    }

    function renderCriteriaKeywordTags($picker, keywords) {
      var $tags = $picker.find("[data-keyword-tags]");
      $tags.empty();
      if (!keywords.length) {
        $tags.append(
          '<span class="sffc-crm-keyword-empty">No keywords selected yet</span>'
        );
        return;
      }
      keywords.forEach(function (keyword) {
        var $tag = $('<span class="sffc-crm-keyword-tag"></span>');
        $("<span></span>").text(keyword).appendTo($tag);
        $('<button type="button" aria-label="Remove keyword">×</button>')
          .attr("data-keyword-remove", "")
          .attr("data-keyword", keyword)
          .appendTo($tag);
        $tags.append($tag);
      });
    }

    function renderKeywordSuggestions($picker, query) {
      var options = [];
      var rawOptions = $picker.attr("data-keyword-options") || "[]";
      try {
        options = JSON.parse(rawOptions);
      } catch (err) {
        options = [];
      }

      query = String(query || "").trim();
      var $suggestions = $picker.find("[data-keyword-suggestions]");
      if (!query) {
        $suggestions.attr("hidden", "hidden").empty();
        return;
      }

      var selected = getCriteriaKeywords($picker).map(function (keyword) {
        return keyword.toLowerCase();
      });
      var lowerQuery = query.toLowerCase();
      var matches = options
        .filter(function (keyword) {
          return (
            keyword.toLowerCase().indexOf(lowerQuery) !== -1 &&
            selected.indexOf(keyword.toLowerCase()) === -1
          );
        })
        .slice(0, 8);

      if (
        !matches.some(function (keyword) {
          return keyword.toLowerCase() === lowerQuery;
        })
      ) {
        matches.unshift(query);
      }

      $suggestions.empty();
      matches.forEach(function (keyword) {
        $('<button type="button"></button>')
          .attr("data-keyword-suggestion", "")
          .attr("data-keyword", keyword)
          .text(keyword)
          .appendTo($suggestions);
      });
      $suggestions.removeAttr("hidden");
    }

    function updateQueryString(key, value) {
      var url = new URL(window.location.href);
      url.searchParams.set(key, value);
      return url.toString();
    }

    function uploadDocument(file) {
      var formData = new FormData();
      formData.append("file", file);
      formData.append("action", "sffc_crm_upload_document");
      formData.append("nonce", config.nonce);

      // Show upload progress
      showToast("Uploading " + file.name + "...", "info");

      $.ajax({
        url: config.ajaxUrl,
        type: "POST",
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
          showToast("Upload successful!", "success");
          // Add document card to grid
          location.reload(); // Simplified - reload to show new document
        },
        error: function () {
          showToast("Upload failed", "error");
        },
      });
    }

    function showToast(message, type) {
      var bgColor =
        type === "success"
          ? "#057642"
          : type === "error"
          ? "#dc2626"
          : "#0D353E";
      var $toast = $(
        '<div class="sffc-crm-toast" style="position:fixed;top:20px;right:20px;background:' +
          bgColor +
          ';color:#fff;padding:12px 20px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.15);z-index:10000;font-size:14px;font-weight:600;">' +
          message +
          "</div>"
      );

      $("body").append($toast);

      setTimeout(function () {
        $toast.fadeOut(300, function () {
          $(this).remove();
        });
      }, 3000);
    }

    function handleMessageCreditFailure(response) {
      var message =
        (response && response.data && response.data.message) ||
        "Unable to record this email credit. Please try again.";
      var summary = response && response.data && response.data.credit_summary;

      if (summary && parseInt(summary.remaining || 0, 10) <= 0) {
        showOutOfCreditsModal(message);
        return;
      }

      showToast(message, "error");
    }

    function showOutOfCreditsModal(message) {
      var upgradeUrl = membershipUrl || "/memberships/";
      var $existing = $(".sffc-credit-upgrade-modal");
      if ($existing.length) {
        $existing.remove();
      }

      var $modal = $(
        '<div class="sffc-credit-upgrade-modal" role="dialog" aria-modal="true" aria-labelledby="sffc-credit-upgrade-title">' +
          '<div class="sffc-credit-upgrade-modal__panel">' +
          '<button type="button" class="sffc-credit-upgrade-modal__close" aria-label="Close">&times;</button>' +
          '<span class="sffc-credit-upgrade-modal__eyebrow">Credits</span>' +
          '<h3 id="sffc-credit-upgrade-title">You are out of credits</h3>' +
          '<p>' +
          escapeHtml(
            message ||
              "You have used your weekly Recruiter Intro credits. Upgrade to keep messaging recruiters today."
          ) +
          "</p>" +
          '<div class="sffc-credit-upgrade-modal__actions">' +
          '<button type="button" class="sffc-credit-upgrade-modal__upgrade">Upgrade membership</button>' +
          '<button type="button" class="sffc-credit-upgrade-modal__secondary">Not now</button>' +
          "</div>" +
          "</div>" +
          "</div>"
      );

      $("body").append($modal);

      $modal.on(
        "click",
        ".sffc-credit-upgrade-modal__close, .sffc-credit-upgrade-modal__secondary",
        function () {
          $modal.remove();
        }
      );

      $modal.on("click", ".sffc-credit-upgrade-modal__upgrade", function () {
        window.location.href = upgradeUrl;
      });

      $modal.on("click", function (e) {
        if (e.target === $modal[0]) {
          $modal.remove();
        }
      });
    }

    function prependIntroCard(request) {
      var $grid = $("#sffc-crm-intros-grid");
      if (!$grid.length) {
        $grid = $(
          '<div class="sffc-company-prep-grid sffc-crm-intros-grid" id="sffc-crm-intros-grid"></div>'
        );
        $("#sffc-crm-intros-empty").after($grid);
      }

      $("#sffc-crm-intros-empty").remove();
      var html =
        "" +
        '<article class="sffc-company-prep-card sffc-crm-intro-card" data-intro-id="' +
        escapeHtml(request.id || 0) +
        '" data-request-status="' +
        escapeHtml(request.status || "pending_review") +
        '">' +
        '<div class="sffc-company-prep-content">' +
        '<div class="sffc-company-prep-card-header">' +
        "<h3>" +
        escapeHtml(request.recruiter_name || "Hiring Team") +
        "</h3>" +
        '<p class="sffc-company-prep-location">' +
        escapeHtml(request.job_company || "Company") +
        "</p>" +
        "</div>" +
        '<p class="sffc-company-prep-regions">' +
        escapeHtml(request.job_title || "Role") +
        "</p>" +
        '<div class="sffc-company-prep-meta">' +
        '<span class="sffc-company-prep-indicator">' +
        escapeHtml(request.status_label || "Requested") +
        "</span>" +
        '<span class="sffc-crm-intro-date">' +
        escapeHtml(request.created_at_formatted || "") +
        "</span>" +
        "</div>" +
        '<div class="sffc-company-prep-cta">' +
        '<div class="sffc-company-prep-status is-pending">' +
        '<span class="sffc-company-prep-badge">Status</span>' +
        '<p class="sffc-company-prep-status-text">' +
        escapeHtml(request.status_label || "Requested") +
        "</p>" +
        "</div>" +
        '<button type="button" class="sffc-crm-btn sffc-crm-btn-outline sffc-crm-cancel-intro" data-intro-id="' +
        escapeHtml(request.id || 0) +
        '">Cancel Request</button>' +
        "</div>" +
        "</div>" +
        "</article>";

      $grid.prepend(html);
    }

    function syncIntroEmptyState() {
      var $grid = $("#sffc-crm-intros-grid");
      if ($grid.length && !$grid.find(".sffc-crm-intro-card").length) {
        $grid.remove();
        $grid.after(
          '<div class="sffc-company-prep-empty" id="sffc-crm-intros-empty"><p>No applications yet. Click Add to Queue on a role card to add it to your tracker.</p></div>'
        );
      }
    }

    function escapeHtml(value) {
      return String(value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
    }

    // ========================================
    // Companies Functionality
    // ========================================

    // File upload preview
    $eventRoot.on("change", "#company-logo", function (e) {
      var file = e.target.files[0];
      if (file) {
        var $fileName = $(this)
          .closest(".sffc-crm-file-upload")
          .find(".sffc-crm-file-name");
        $fileName.text(file.name);
      }
    });

    // Add company form submission
    $eventRoot.on("submit", ".sffc-crm-add-company-form", function (e) {
      e.preventDefault();

      var $form = $(this);
      var formData = new FormData(this);
      formData.append("action", "sffc_crm_add_company");
      formData.append("nonce", config.nonce);

      if (!config.ajaxUrl) {
        showToast("Please configure AJAX URL", "error");
        return;
      }

      var companyName = $form.find("#company-name").val().trim();
      if (!companyName) {
        showToast("Company name is required", "error");
        return;
      }

      // Disable submit button
      var $submitBtn = $form.find('button[type="submit"]');
      var originalText = $submitBtn.html();
      $submitBtn
        .prop("disabled", true)
        .html(
          '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;animation:spin 1s linear infinite"><circle cx="12" cy="12" r="10" opacity="0.25"/><path d="M12 2a10 10 0 0 1 10 10" opacity="0.75"/></svg> Adding...'
        );

      $.ajax({
        url: config.ajaxUrl,
        type: "POST",
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
          if (response.success) {
            showToast("Company added successfully!", "success");
            $form[0].reset();
            $form.find(".sffc-crm-file-name").text("");

            // Reload to show new company
            setTimeout(function () {
              location.reload();
            }, 1000);
          } else {
            showToast(response.data || "Failed to add company", "error");
            $submitBtn.prop("disabled", false).html(originalText);
          }
        },
        error: function () {
          showToast("Failed to add company", "error");
          $submitBtn.prop("disabled", false).html(originalText);
        },
      });
    });

    // ========================================
    // Ask MENA Careers Composer
    // ========================================

    // Suggestion button click - populate input
    $eventRoot.on("click", ".sffc-crm-suggestion-btn", function (e) {
      e.preventDefault();
      var $btn = $(this);
      var prompt = $btn.data("prompt");
      var $input = $app.find(".sffc-crm-composer-input");

      if (prompt) {
        $input.val(prompt).focus();

        // Add visual feedback
        $btn.css({
          background: "var(--linkedin-hover)",
          "border-color": "var(--senna-primary)",
          color: "var(--senna-primary)",
        });

        setTimeout(function () {
          $btn.css({
            background: "",
            "border-color": "",
            color: "",
          });
        }, 300);
      }
    });

    // Ask MENA Careers button click - submit to AI
    $eventRoot.on("click", ".sffc-crm-ask-senna-btn", function (e) {
      e.preventDefault();
      var $btn = $(this);
      var $input = $app.find(".sffc-crm-composer-input");
      var query = $input.val().trim();

      if (!query) {
        $input.focus();
        return;
      }

      // Disable button and show loading
      var originalHtml = $btn.html();
      $btn
        .prop("disabled", true)
        .html(
          '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;animation:spin 1s linear infinite"><circle cx="12" cy="12" r="10" opacity="0.25"/><path d="M12 2a10 10 0 0 1 10 10" opacity="0.75"/></svg> Asking...'
        );

      // Simulate AI response (replace with actual AJAX call)
      if (config.askSennaUrl) {
        $.ajax({
          url: config.askSennaUrl,
          type: "POST",
          data: {
            query: query,
            action: "sffc_ask_senna",
            nonce: config.nonce,
          },
          success: function (response) {
            if (response.success) {
              showToast("Response from MENA Careers received!", "success");
              $input.val("");
              // Handle response (e.g., show modal, redirect, etc.)
              if (response.data && response.data.url) {
                window.location.href = response.data.url;
              }
            } else {
              showToast(response.data || "Failed to get response", "error");
            }
            $btn.prop("disabled", false).html(originalHtml);
          },
          error: function () {
            showToast("Failed to reach MENA Careers", "error");
            $btn.prop("disabled", false).html(originalHtml);
          },
        });
      } else {
        // Fallback - redirect to learning page with query
        var learningUrl = config.learningUrl || "/learn/";
        window.location.href =
          learningUrl + "?query=" + encodeURIComponent(query);
      }
    });

    // Enter key to submit
    $eventRoot.on("keypress", ".sffc-crm-composer-input", function (e) {
      if (e.which === 13) {
        e.preventDefault();
        $app.find(".sffc-crm-ask-senna-btn").click();
      }
    });

    // Load more HR contacts - NEW IMPLEMENTATION
    $eventRoot.on("click", ".sffc-crm-hr-contacts-load-btn", function (e) {
      e.preventDefault();

      console.log("Load more clicked");
      console.log("Config:", config);
      console.log("AJAX URL:", config.ajaxUrl);
      console.log("Nonce:", config.nonce);

      if (hrLoadRequestActive) {
        console.log("Request already active");
        return;
      }

      if (!config.ajaxUrl) {
        console.error("No AJAX URL configured");
        alert("Configuration error: No AJAX URL");
        return;
      }

      var $btn = $(this);
      var $grid = $app.find(".sffc-crm-hr-grid");
      var currentPage = parseInt($btn.data("page"), 10) || 1;
      var perPage = parseInt($btn.data("per-page"), 10) || 6;
      var total = parseInt($btn.data("total"), 10) || 0;

      console.log("Current page:", currentPage);
      console.log("Per page:", perPage);
      console.log("Total:", total);
      console.log("Grid found:", $grid.length);

      if (!$grid.length) {
        console.error("HR grid not found");
        alert("Error: Grid not found");
        return;
      }

      hrLoadRequestActive = true;
      var originalText = $btn.text();
      $btn.prop("disabled", true).text("Loading...");

      var requestData = {
        action: "sffc_crm_load_more_hr_contacts",
        nonce: config.nonce,
        page: currentPage + 1,
        per_page: perPage,
      };

      console.log("Sending AJAX request:", requestData);

      $.ajax({
        url: config.ajaxUrl,
        type: "POST",
        dataType: "json",
        data: requestData,
        success: function (response) {
          console.log("AJAX response:", response);

          if (response.success && response.data.html) {
            // Append new cards to grid
            $grid.append(response.data.html);

            // Update button page
            $btn.data("page", currentPage + 1);

            // Check if there are more to load
            var loaded = (currentPage + 1) * perPage;
            if (loaded >= total || !response.data.has_more) {
              $btn.closest(".sffc-crm-hr-contacts-load-more").remove();
            }
          } else {
            var msg =
              response.data && response.data.message
                ? response.data.message
                : "Failed to load contacts";
            console.error("Response error:", msg);
            alert(msg);
          }
        },
        error: function (xhr, status, error) {
          console.error("AJAX error:", status, error);
          console.error("XHR:", xhr);
          console.error("Response text:", xhr.responseText);
          alert(
            "Unable to load more contacts. Please try again. Check console for details."
          );
        },
        complete: function () {
          hrLoadRequestActive = false;
          $btn.prop("disabled", false).text(originalText);
        },
      });
    });

    // Delete company
    $eventRoot.on("click", ".sffc-crm-company-delete", function (e) {
      e.preventDefault();
      e.stopPropagation();

      var $btn = $(this);
      var $card = $btn.closest(".sffc-crm-company-card");
      var companyId = $card.data("company-id");
      var companyName = $card.find("h4").text();

      if (!confirm("Remove " + companyName + " from your list?")) {
        return;
      }

      if (!config.ajaxUrl) {
        showToast("Please configure AJAX URL", "error");
        return;
      }

      $.ajax({
        url: config.ajaxUrl,
        type: "POST",
        data: {
          action: "sffc_crm_delete_company",
          nonce: config.nonce,
          company_id: companyId,
        },
        success: function (response) {
          if (response.success) {
            showToast("Company removed", "success");
            $card.fadeOut(300, function () {
              $(this).remove();

              // Update count
              var $count = $(".sffc-crm-companies-list .sffc-crm-count");
              var currentCount = parseInt($count.text()) || 0;
              $count.text(Math.max(0, currentCount - 1));
            });
          } else {
            showToast(response.data || "Failed to remove company", "error");
          }
        },
        error: function () {
          showToast("Failed to remove company", "error");
        },
      });
    });

    // ============================================
    // Email Preview Modal Functionality
    // ============================================

    function openEmailModal(data) {
      var $modal = $("[data-email-modal]");
      if (!$modal.length) {
        return;
      }

      var headingText =
        data.role ||
        (data.company
          ? data.company + " outreach"
          : i18n.emailModalHeading || "Draft your outreach");
      var subtitleText =
        data.type === "auto"
          ? i18n.emailModalTemplateSubtitle ||
            "We drafted a starter email using the highlighted skills."
          : i18n.emailModalManualSubtitle ||
            "Start from scratch and personalize your outreach.";

      $modal.find("[data-email-modal-heading]").text(headingText);
      $modal.find("[data-email-modal-subtitle]").text(subtitleText);

      $modal.find("[data-email-modal-name]").text(data.name || "");

      var titleLine = "";
      if (data.recruiterTitle) {
        titleLine = data.recruiterTitle;
      }
      if (data.company) {
        titleLine = titleLine ? titleLine + " • " + data.company : data.company;
      }
      $modal
        .find("[data-email-modal-rec-title]")
        .text(titleLine || data.company || "");
      $modal.find("[data-email-modal-company]").text(data.company || "");
      $modal.find("[data-email-modal-role]").text(data.role || "");

      var $emailLink = $modal.find("[data-email-modal-to]");
      var hasEmail = !!(data.email && data.email.length);
      if (hasEmail) {
        $emailLink.text(data.email).attr("href", "mailto:" + data.email);
      } else {
        $emailLink
          .text(i18n.emailMissing || "Email not provided")
          .removeAttr("href");
      }

      var $avatar = $modal.find("[data-email-modal-avatar]");
      var $avatarInitial = $modal.find("[data-email-modal-avatar-initial]");
      $avatar.find("img").remove();
      var fallbackInitial = "";
      if (data.recruiterAvatar) {
        $("<img />", {
          src: data.recruiterAvatar,
          alt: data.name || "",
        }).appendTo($avatar);
        $avatar.addClass("has-image");
        $avatarInitial.text("");
      } else {
        $avatar.removeClass("has-image");
        fallbackInitial =
          (data.recruiterInitial || data.name || "?")
            .toString()
            .trim()
            .charAt(0) || "?";
        $avatarInitial.text(fallbackInitial);
      }

      $modal.find("[data-email-modal-subject]").val(data.subject || "");
      $modal.find("[data-email-modal-body]").val(data.body || "");
      setEmailModalFeedback("");

      var $sendBtn = $modal.find("[data-email-modal-send]");
      if (hasEmail) {
        $sendBtn.removeClass("is-disabled").prop("disabled", false);
      } else {
        $sendBtn.addClass("is-disabled").prop("disabled", true);
      }

      $modal.data("emailData", data);
      $modal.attr("aria-hidden", "false");
      $("body").addClass("sffc-crm-email-modal-open");
    }

    function closeEmailModal() {
      var $modal = $("[data-email-modal]");
      $modal.attr("aria-hidden", "true");
      $modal.removeData("emailData");
      $modal.find("[data-email-modal-subject]").val("");
      $modal.find("[data-email-modal-body]").val("");
      setEmailModalFeedback("");
      $("body").removeClass("sffc-crm-email-modal-open");
    }

    function setEmailModalFeedback(message, isError) {
      var $feedback = $("[data-email-modal-feedback]");
      if (!$feedback.length) {
        return;
      }
      if (!message) {
        $feedback.attr("hidden", "hidden").removeClass("is-error").text("");
        return;
      }
      $feedback
        .text(message)
        .removeAttr("hidden")
        .toggleClass("is-error", !!isError);
    }

    // Email modal close handlers
    $eventRoot.on("click", "[data-email-modal-close]", function (e) {
      e.preventDefault();
      closeEmailModal();
    });

    // Email review handler
    $eventRoot.on("click", "[data-email-modal-review]", function (e) {
      e.preventDefault();

      if (!config.isLoggedIn) {
        promptLogin();
        return;
      }

      var $btn = $(this);
      if ($btn.hasClass("is-loading")) {
        return;
      }

      var $modal = $("[data-email-modal]");
      var data = $modal.data("emailData") || {};
      var subject = (
        $modal.find("[data-email-modal-subject]").val() || ""
      ).trim();
      var body = ($modal.find("[data-email-modal-body]").val() || "").trim();

      if (!body) {
        setEmailModalFeedback(
          i18n.emailReviewMissing ||
            "Please add your draft so we can review it.",
          true
        );
        return;
      }

      var ajaxUrl =
        config.ajaxUrl || window.ajaxurl || "/wp-admin/admin-ajax.php";

      $btn.addClass("is-loading").prop("disabled", true);
      setEmailModalFeedback(
        i18n.emailReviewSending || "Sending to our coaching team...",
        false
      );

      $.ajax({
        url: ajaxUrl,
        type: "POST",
        data: {
          action: "sffc_crm_review_email",
          nonce: config.nonce,
          subject: subject,
          body: body,
          recruiter_email: data.email || "",
          recruiter_name: data.name || "",
          recruiter_title: data.recruiterTitle || "",
          company: data.company || "",
          role: data.role || "",
          message_type: data.type || "manual",
        },
      })
        .done(function (response) {
          if (response && response.success) {
            var message =
              response.data && response.data.message
                ? response.data.message
                : i18n.emailReviewSuccess ||
                  "Thanks! A coach will review your email shortly.";
            setEmailModalFeedback(message, false);
          } else {
            var errorMessage =
              response && response.data && response.data.message
                ? response.data.message
                : i18n.emailReviewError ||
                  "Unable to send your draft for review.";
            setEmailModalFeedback(errorMessage, true);
          }
        })
        .fail(function () {
          setEmailModalFeedback(
            i18n.emailReviewError || "Unable to send your draft for review.",
            true
          );
        })
        .always(function () {
          $btn.removeClass("is-loading").prop("disabled", false);
        });
    });

    // Email modal send handler
    $eventRoot.on("click", "[data-email-modal-send]", function (e) {
      e.preventDefault();

      var $modal = $("[data-email-modal]");
      var data = $modal.data("emailData");

      if (!data) {
        return;
      }

      var subject = $modal.find("[data-email-modal-subject]").val() || "";
      var body = $modal.find("[data-email-modal-body]").val() || "";

      setEmailModalFeedback("");

      if (!data.email) {
        setEmailModalFeedback(
          i18n.emailMissing ||
            "Recruiter email not available. Copy your draft and follow up with our team.",
          true
        );
        return;
      }

      var mailto =
        "mailto:" +
        data.email +
        "?subject=" +
        encodeURIComponent(subject) +
        "&body=" +
        encodeURIComponent(body);

      window.location.href = mailto;

      setTimeout(function () {
        closeEmailModal();
      }, 300);
    });

    // Close modal on ESC key
    $(document).on("keydown", function (e) {
      if (e.key === "Escape") {
        var $modal = $("[data-email-modal]");
        if ($modal.attr("aria-hidden") === "false") {
          closeEmailModal();
        }
      }
    });

    // Prevent modal close when clicking inside dialog
    $eventRoot.on("click", ".sffc-crm-email-modal__dialog", function (e) {
      e.stopPropagation();
    });

    // Close modal when clicking overlay
    $eventRoot.on("click", ".sffc-crm-email-modal__overlay", function (e) {
      closeEmailModal();
    });

    // ============================================
    // Community Feed Upvote Handler
    // ============================================
    $eventRoot.on("click", "[data-upvote-btn]", function (e) {
      e.preventDefault();
      var $btn = $(this);
      var $count = $btn.find("[data-upvote-count]");
      var currentCount = parseInt($count.text(), 10) || 0;

      if ($btn.hasClass("is-upvoted")) {
        // Remove upvote
        $btn.removeClass("is-upvoted");
        $count.text(currentCount - 1);
      } else {
        // Add upvote
        $btn.addClass("is-upvoted");
        $count.text(currentCount + 1);
      }
    });

    // ============================================
    // MENTORSHIP REQUESTS
    // ============================================

    var mentorshipRequestMeta = {
      mentor_session: {
        title: "Book a Mentor Session",
        prompt: "Tell us what you want to cover on the call.",
        details: "Share your goal, current situation, and the decisions you want help with.",
      },
      cv_linkedin_review: {
        title: "CV / LinkedIn Review",
        prompt: "Share your target role and upload your CV or paste your LinkedIn URL.",
        details: "Tell us what feedback would be most useful: structure, bullets, positioning, keywords, or overall fit.",
      },
      mock_interview: {
        title: "Mock Interviews",
        prompt: "Tell us which interview format you want to practise.",
        details: "Mention technical, behavioural, case-study, accounting, modelling, investment rationale, or fit interview focus.",
      },
      career_plan: {
        title: "Career Plan",
        prompt: "Tell us where you want to get to and your timeline.",
        details: "Share your target role, location, experience level, and any constraints we should consider.",
      },
    };

    function closeMentorshipModal() {
      $(".sffc-crm-mentorship-modal").remove();
      $("body").removeClass("sffc-crm-mentorship-modal-open");
    }

    function openMentorshipModal(type, title) {
      if (!config.isLoggedIn) {
        if (typeof promptLogin === "function") {
          promptLogin();
          return;
        }
        window.location.href = config.loginUrl || "/wp-login.php";
        return;
      }

      var meta = mentorshipRequestMeta[type] || {};
      var modalTitle = title || meta.title || "Mentorship request";
      var modalHtml =
        '<div class="sffc-crm-mentorship-modal" aria-hidden="false">' +
        '<div class="sffc-crm-mentorship-modal__overlay" data-mentorship-close></div>' +
        '<form class="sffc-crm-mentorship-dialog" data-mentorship-form enctype="multipart/form-data">' +
        '<button type="button" class="sffc-crm-mentorship-modal__close" data-mentorship-close aria-label="Close">×</button>' +
        '<p class="sffc-crm-mentorship-modal__eyebrow">Mentorship request</p>' +
        "<h3>" +
        escapeHtml(modalTitle) +
        "</h3>" +
        "<p>" +
        escapeHtml(meta.prompt || "Tell us how our mentors can help.") +
        "</p>" +
        '<input type="hidden" name="request_type" value="' +
        escapeHtml(type || "") +
        '">' +
        '<div class="sffc-crm-mentorship-form-grid">' +
        '<label>Target role<input type="text" name="target_role" placeholder="Private Equity Associate, Investment Manager, Portfolio Operations Lead"></label>' +
        '<label>Target location<input type="text" name="target_location" placeholder="Dubai, Abu Dhabi, Riyadh, London, Singapore"></label>' +
        '<label>LinkedIn URL<input type="url" name="linkedin_url" placeholder="https://www.linkedin.com/in/..."></label>' +
        '<label>Timeline<input type="text" name="timeline" placeholder="This month, next quarter, before interviews"></label>' +
        "</div>" +
        '<label class="sffc-crm-mentorship-field-wide">Details<textarea name="details" rows="5" required placeholder="' +
        escapeHtml(meta.details || "Add context for the mentor.") +
        '"></textarea></label>' +
        '<label class="sffc-crm-mentorship-upload">CV attachment <span>Optional PDF, DOC, or DOCX</span><input type="file" name="cv_file" accept=".pdf,.doc,.docx"></label>' +
        '<p class="sffc-crm-mentorship-feedback" data-mentorship-feedback hidden></p>' +
        '<div class="sffc-crm-mentorship-modal__actions">' +
        '<button type="button" class="sffc-crm-btn sffc-crm-btn-outline" data-mentorship-close>Cancel</button>' +
        '<button type="submit" class="sffc-crm-btn sffc-crm-btn-primary">Send request</button>' +
        "</div>" +
        "</form>" +
        "</div>";

      closeMentorshipModal();
      $("body").addClass("sffc-crm-mentorship-modal-open").append(modalHtml);
    }

    $eventRoot.on("click", "[data-mentorship-request]", function (e) {
      e.preventDefault();
      var $button = $(this);
      openMentorshipModal(
        $button.data("mentorshipType"),
        $button.data("mentorshipTitle")
      );
    });

    $eventRoot.on("click", "[data-mentorship-close]", function (e) {
      e.preventDefault();
      closeMentorshipModal();
    });

    $eventRoot.on("submit", "[data-mentorship-form]", function (e) {
      e.preventDefault();

      var $form = $(this);
      var $feedback = $form.find("[data-mentorship-feedback]");
      var $submit = $form.find('button[type="submit"]');
      var formData = new FormData($form[0]);
      formData.append("action", "sffc_crm_submit_mentorship_request");
      formData.append("nonce", config.nonce || "");

      $feedback.prop("hidden", false).removeClass("is-error").text("Sending request...");
      $submit.prop("disabled", true).addClass("is-loading");

      $.ajax({
        url: config.ajaxUrl || "/wp-admin/admin-ajax.php",
        type: "POST",
        data: formData,
        processData: false,
        contentType: false,
        dataType: "json",
      })
        .done(function (response) {
          if (response && response.success) {
            var message =
              (response.data && response.data.message) ||
              "Request sent. The mentorship team has been notified.";
            $feedback.removeClass("is-error").text(message);
            showToast(message, "success");
            setTimeout(closeMentorshipModal, 900);
            return;
          }

          var errorMessage =
            (response && response.data && response.data.message) ||
            "Unable to send your request right now.";
          $feedback.addClass("is-error").text(errorMessage);
          showToast(errorMessage, "error");
        })
        .fail(function (xhr) {
          var errorMessage = "Unable to send your request right now.";
          if (xhr && xhr.responseJSON && xhr.responseJSON.data) {
            errorMessage = xhr.responseJSON.data.message || errorMessage;
          }
          $feedback.addClass("is-error").text(errorMessage);
          showToast(errorMessage, "error");
        })
        .always(function () {
          $submit.prop("disabled", false).removeClass("is-loading");
        });
    });

    // ============================================
    // DASHBOARD LIST ITEM CLICK - OPEN PREP MODAL
    // ============================================

    $eventRoot.on("click", ".sffc-crm-dashboard-list-item", function (e) {
      // Don't trigger if clicking on buttons or links inside
      if ($(e.target).closest("button, a").length) {
        return;
      }

      var $item = $(this);
      var $toggle = $item.find(".sffc-crm-prep-toggle");

      if (!$toggle.length || !window.SFFCPrepModal) {
        return;
      }

      // Extract company logo
      var companyLogo = "";
      var $logoImg = $item.find(".sffc-crm-dashboard-logo-badge img").first();
      if ($logoImg.length) {
        companyLogo = $logoImg.attr("src");
      }

      // Extract recruiter data
      var recruiterAvatar =
        $item.find(".sffc-crm-dashboard-avatar img").attr("src") || "";
      var recruiterInitial =
        $item.find(".sffc-crm-dashboard-avatar span").text() || "?";

      // Build comprehensive post data
      var postData = {
        postId: $toggle.data("postId"),
        roleTitle: $toggle.data("roleTitle") || $toggle.data("roleDisplay"),
        company: $toggle.data("company"),
        location: $toggle.data("locationLabel"),
        sector: $toggle.data("sector"),
        seniority: $toggle.data("seniority"),
        keywords: $toggle.data("keywords"),
        contentSnippet: $toggle.data("contentSnippet"),
        interviewQuestionsUrl: $toggle.data("interviewQuestionsUrl"),
        interviewQuestionsHtml: $toggle.data("interviewQuestionsHtml"),
        cvTemplateUrl: $toggle.data("cvTemplateUrl"),
        coverLetterUrl: $toggle.data("coverLetterUrl"),
        coverLetterHtml: $toggle.data("coverLetterHtml"),
        caseStudyUrl: $toggle.data("caseStudyUrl"),
        applicationProcess: $toggle.data("applicationProcess"),
        teamContacts: $toggle.data("teamContacts"),
        knockoutQuestions: $toggle.data("knockoutQuestions"),
        openingDate: $toggle.data("openingDate"),
        closingDate: $toggle.data("closingDate"),
        startingDate: $toggle.data("startingDate"),
        duration: $toggle.data("duration"),
        recruiterName: $toggle.data("recruiter"),
        recruiterTitle: $toggle.data("recruiterTitle"),
        recruiterEmail: $toggle.data("recruiterEmail"),
        recruiterLinkedin: $toggle.data("recruiterLinkedin"),
        recruiterAvatar: recruiterAvatar,
        recruiterInitial: recruiterInitial,
        companyLogo: $toggle.data("companyLogo") || companyLogo,
        companyInitial: $toggle.data("companyInitial"),
      };

      window.SFFCPrepModal.open(postData);
    });

    // ============================================
    // QUESTION CHATBOX
    // ============================================

    $eventRoot.on("click", ".sffc-crm-question-input[readonly]", function () {
      window.location.href =
        sffcCRMLinkedIn.loginUrl || window.location.origin + "/my-account/";
    });

    var isSubmittingQuestion = false;
    $eventRoot.on(
      "click",
      ".sffc-crm-question-submit:not(:disabled)",
      function () {
        if (isSubmittingQuestion) {
          return;
        }

        var $btn = $(this);
        var $input = $(".sffc-crm-question-input");
        var question = ($input.val() || "").trim();

        if (!question) {
          return;
        }

        isSubmittingQuestion = true;
        $btn.prop("disabled", true);

        $.ajax({
          url: sffcCRMLinkedIn.ajaxUrl,
          type: "POST",
          dataType: "json",
          data: {
            action: "sffc_crm_submit_question",
            nonce:
              (sffcCRMLinkedIn.qa && sffcCRMLinkedIn.qa.nonce) ||
              sffcCRMLinkedIn.nonce,
            question: question,
          },
          success: function (response) {
            if (response && response.success) {
              $input.val("");
              appendPendingQuestionToFeed(question);
              alert(
                (sffcCRMLinkedIn.qa &&
                  sffcCRMLinkedIn.qa.strings &&
                  sffcCRMLinkedIn.qa.strings.success) ||
                  "Question submitted to our expert team."
              );
            } else {
              alert(
                (response && response.data && response.data.message) ||
                  (sffcCRMLinkedIn.qa &&
                    sffcCRMLinkedIn.qa.strings &&
                    sffcCRMLinkedIn.qa.strings.error) ||
                  "Unable to send your question right now."
              );
            }
          },
          error: function (xhr) {
            var errorMsg = "Unable to send your question right now.";
            try {
              var response = JSON.parse(xhr.responseText);
              if (response && response.data && response.data.message) {
                errorMsg = response.data.message;
              }
            } catch (e) {}
            alert(errorMsg);
          },
          complete: function () {
            isSubmittingQuestion = false;
            $btn.prop("disabled", false);
          },
        });
      }
    );

    $eventRoot.on(
      "keypress",
      ".sffc-crm-question-input:not([readonly])",
      function (e) {
        if (e.which === 13) {
          e.preventDefault();
          $(".sffc-crm-question-submit").click();
        }
      }
    );

    function appendPendingQuestionToFeed(questionText) {
      var $stream = $(".sffc-crm-live-feed-stream").first();
      if (
        !$stream.length ||
        typeof window.sffcCRMLiveFeedRender !== "function"
      ) {
        return;
      }

      var qaStrings = (sffcCRMLinkedIn.qa && sffcCRMLinkedIn.qa.strings) || {};
      var item = {
        name: sffcCRMLinkedIn.currentUserName || "MENA Careers member",
        question: questionText,
        answer_preview:
          qaStrings.pendingAnswer ||
          "Our in-house mentor team has been notified.",
        expert_name: qaStrings.pendingExpert || "MENA Careers Expert",
        expert_title: qaStrings.pendingExpertTitle || "Awaiting reply",
        time: qaStrings.justNow || "Just now",
        status: "pending",
      };

      var html = window.sffcCRMLiveFeedRender(item);
      if (html) {
        var $node = $(html).addClass("is-new");
        $stream.prepend($node);
      }
    }

    // ============================================
    // SEARCH FUNCTIONALITY
    // ============================================

    var searchTimeout;
    var $searchResults;

    // Create search results dropdown
    var $searchWrapper = $(".sffc-crm-search-wrapper");
    if (
      $searchWrapper.length &&
      !$searchWrapper.find(".sffc-crm-search-results").length
    ) {
      $searchResults = $("<div>", {
        class: "sffc-crm-search-results",
      }).appendTo($searchWrapper);
    }

    $eventRoot.on("input", ".sffc-crm-search-input", function () {
      var query = $(this).val().trim();

      clearTimeout(searchTimeout);

      if (query.length < 2) {
        if ($searchResults) {
          $searchResults.removeClass("is-open").empty();
        }
        return;
      }

      searchTimeout = setTimeout(function () {
        performSearch(query);
      }, 300);
    });

    function performSearch(query) {
      if (!$searchResults) return;

      // Search through feed items
      var results = [];
      var lowerQuery = query.toLowerCase();

      // Search in LinkedIn feed cards (now using dashboard list item structure)
      $(".sffc-crm-linkedin-feed-card").each(function () {
        var $card = $(this);
        var roleTitle = $card
          .find(".sffc-crm-dashboard-title strong")
          .first()
          .text()
          .toLowerCase();
        var recruiter = $card
          .find(".sffc-crm-dashboard-recruiter-name")
          .text()
          .toLowerCase();
        var location = $card
          .find(".sffc-crm-dashboard-meta svg")
          .parent()
          .text()
          .toLowerCase();

        // Extract company from role display (format: "Role @ Company")
        var fullTitle = $card
          .find(".sffc-crm-dashboard-title strong")
          .first()
          .text();
        var company = "";
        if (fullTitle.indexOf(" @ ") > -1) {
          company = fullTitle.split(" @ ")[1].toLowerCase();
        }

        if (
          roleTitle.indexOf(lowerQuery) > -1 ||
          company.indexOf(lowerQuery) > -1 ||
          location.indexOf(lowerQuery) > -1 ||
          recruiter.indexOf(lowerQuery) > -1
        ) {
          results.push({
            title: fullTitle,
            company: company,
            element: $card,
          });
        }
      });

      // Display results
      displaySearchResults(results, query);
    }

    function displaySearchResults(results, query) {
      if (!$searchResults) return;

      $searchResults.empty();

      if (results.length === 0) {
        $searchResults.html(
          '<div class="sffc-crm-search-no-results">No results found for "' +
            escapeHtml(query) +
            '"</div>'
        );
      } else {
        results.slice(0, 10).forEach(function (result) {
          var $resultItem = $("<div>", {
            class: "sffc-crm-search-result-item",
            html:
              '<h4 class="sffc-crm-search-result-title">' +
              escapeHtml(result.title) +
              "</h4>" +
              '<p class="sffc-crm-search-result-meta">' +
              escapeHtml(result.company) +
              "</p>",
          });

          $resultItem.on("click", function () {
            // Switch to feed tab if not already there
            var currentTab = $app.find(".sffc-crm-tab.active").data("tab");
            if (currentTab !== "feed") {
              activateTab("feed");
            }

            // Scroll to the element
            setTimeout(function () {
              result.element[0].scrollIntoView({
                behavior: "smooth",
                block: "center",
              });
              result.element.css("background", "rgba(10, 102, 194, 0.1)");
              setTimeout(function () {
                result.element.css("background", "");
              }, 2000);
            }, 100);

            $searchResults.removeClass("is-open");
            $(".sffc-crm-search-input").val("");
          });

          $searchResults.append($resultItem);
        });
      }

      $searchResults.addClass("is-open");
    }

    function escapeHtml(str) {
      return String(str).replace(/[&<>"']/g, function (match) {
        var map = {
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          '"': "&quot;",
          "'": "&#39;",
        };
        return map[match] || match;
      });
    }

    // Close search results when clicking outside
    $eventRoot.on("click", function (e) {
      if (
        $searchResults &&
        !$(e.target).closest(".sffc-crm-search-wrapper").length
      ) {
        $searchResults.removeClass("is-open");
      }
    });

    // ============================================
    // USER MENU DROPDOWN
    // ============================================

    $eventRoot.on("click", ".sffc-crm-linkedin-profile", function (e) {
      e.stopPropagation();
      var $menu = $(".sffc-user-menu");
      $menu.toggleClass("is-open");
    });

    // Close user menu when clicking outside
    $eventRoot.on("click", function (e) {
      if (!$(e.target).closest(".sffc-crm-linkedin-profile").length) {
        $(".sffc-user-menu").removeClass("is-open");
      }
    });

    // ============================================
    // AVATAR UPLOAD FOR LINKEDIN INTERFACE
    // ============================================

    var $avatarInput = $("#sffc-crm-avatar-input");
    if ($avatarInput.length && config.isLoggedIn) {
      // Handle click on profile card avatar
      $eventRoot.on(
        "click",
        ".sffc-crm-linkedin-profile-card-avatar",
        function (e) {
          if ($(e.target).closest(".sffc-crm-avatar-upload-trigger").length) {
            return; // Let the button handle it
          }
          e.preventDefault();
          $avatarInput.trigger("click");
        }
      );

      // Handle click on avatar upload trigger button
      $eventRoot.on("click", ".sffc-crm-avatar-upload-trigger", function (e) {
        e.preventDefault();
        e.stopPropagation();
        $avatarInput.trigger("click");
      });

      // Handle file selection
      $avatarInput.on("change", function () {
        var file = this.files && this.files[0];
        if (!file) {
          return;
        }

        // Validate file
        var maxSize = 2 * 1024 * 1024; // 2MB
        if (file.size > maxSize) {
          alert("Please upload an image that is 2MB or smaller.");
          $(this).val("");
          return;
        }

        var allowedTypes = ["image/jpeg", "image/png", "image/webp"];
        if (allowedTypes.indexOf(file.type) === -1) {
          alert("Please upload a JPG, PNG, or WebP image.");
          $(this).val("");
          return;
        }

        // Upload the file
        var formData = new FormData();
        formData.append("action", "sffc_crm_upload_avatar");
        formData.append("nonce", config.avatarNonce || config.nonce);
        formData.append("avatar_file", file);

        // Show uploading state
        $(
          ".sffc-crm-linkedin-avatar, .sffc-crm-linkedin-profile-card-avatar"
        ).addClass("is-uploading");

        $.ajax({
          url: config.ajaxUrl,
          type: "POST",
          data: formData,
          processData: false,
          contentType: false,
          success: function (response) {
            $(
              ".sffc-crm-linkedin-avatar, .sffc-crm-linkedin-profile-card-avatar"
            ).removeClass("is-uploading");

            if (
              response &&
              response.success &&
              response.data &&
              response.data.url
            ) {
              var url = response.data.url;
              var cacheBustedUrl =
                url + (url.indexOf("?") === -1 ? "?" : "&") + "t=" + Date.now();

              // Update all avatar images
              $(
                ".sffc-crm-linkedin-avatar img, .sffc-crm-linkedin-profile-card-avatar img"
              ).each(function () {
                $(this).attr("src", cacheBustedUrl);
              });

              // If no img exists, create one and hide the initial span
              $(
                ".sffc-crm-linkedin-avatar, .sffc-crm-linkedin-profile-card-avatar"
              ).each(function () {
                var $container = $(this);
                var $img = $container.find("img");
                if (!$img.length) {
                  $img = $('<img alt="">');
                  $container.find(".sffc-crm-avatar-initial, span").hide();
                  $container.prepend($img);
                }
                $img.attr("src", cacheBustedUrl);
              });

              alert("Profile photo updated successfully!");
            } else {
              var errMsg =
                response && response.data && response.data.message
                  ? response.data.message
                  : "Could not update your photo. Please try again.";
              alert(errMsg);
            }
          },
          error: function (xhr) {
            $(
              ".sffc-crm-linkedin-avatar, .sffc-crm-linkedin-profile-card-avatar"
            ).removeClass("is-uploading");
            var errMsg =
              xhr &&
              xhr.responseJSON &&
              xhr.responseJSON.data &&
              xhr.responseJSON.data.message
                ? xhr.responseJSON.data.message
                : "Upload failed. Please try again.";
            alert(errMsg);
          },
        });

        // Clear the input
        $(this).val("");
      });
    }

    // ============================================
    // EXPERT PANEL TOGGLE
    // ============================================

    $eventRoot.on("click", ".sffc-expert-btn", function (e) {
      e.preventDefault();
      $(".sffc-live-expert-panel").addClass("is-visible");
    });

    // ============================================
    // MEMBERSHIP TOGGLE
    // ============================================

    $eventRoot.on("change", ".sffc-membership-toggle-input", function () {
      if ($(this).is(":checked")) {
        // Redirect to memberships page
        window.location.href = "https://joinsenna.com/memberships/";
      }
    });

    // ============================================
    // SIGNUP FORM
    // ============================================

    var $signupForm = $("#sffc-crm-signup-form");
    if ($signupForm.length) {
      $signupForm.on("submit", function (e) {
        e.preventDefault();

        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');
        var $btnText = $btn.find(".sffc-crm-btn-text");
        var $btnSpinner = $btn.find(".sffc-crm-btn-spinner");
        var $btnArrow = $btn.find(".sffc-crm-btn-arrow");
        var $message = $form.find(".sffc-crm-form-message");

        // Get form data
        var formData = {
          action: "sffc_crm_register_user",
          nonce: config.signupNonce || "",
          first_name: $("#sffc-signup-first-name").val(),
          last_name: $("#sffc-signup-last-name").val(),
          email: $("#sffc-signup-email").val(),
          password: $("#sffc-signup-password").val(),
          terms: $('input[name="terms"]').is(":checked"),
        };

        // Disable form
        $btn.prop("disabled", true);
        $btnText.hide();
        $btnArrow.hide();
        $btnSpinner.show();
        $message.hide().removeClass("success error");

        // Submit AJAX request
        $.ajax({
          url: config.ajaxUrl || "/wp-admin/admin-ajax.php",
          type: "POST",
          data: formData,
          success: function (response) {
            if (response.success) {
              $message
                .addClass("success")
                .text(response.data.message || "Account created successfully!")
                .show();

              // Redirect after short delay
              setTimeout(function () {
                window.location.href = response.data.redirect || "/dashboard/";
              }, 1000);
            } else {
              $message
                .addClass("error")
                .text(
                  response.data.message ||
                    "An error occurred. Please try again."
                )
                .show();

              // Re-enable form
              $btn.prop("disabled", false);
              $btnText.show();
              $btnArrow.show();
              $btnSpinner.hide();
            }
          },
          error: function () {
            $message
              .addClass("error")
              .text(
                "Network error. Please check your connection and try again."
              )
              .show();

            // Re-enable form
            $btn.prop("disabled", false);
            $btnText.show();
            $btnArrow.show();
            $btnSpinner.hide();
          },
        });
      });
    }

    $app.find("[data-matches-onboarding-form]").each(function () {
      updateMatchesOnboardingSubmit($(this));
    });
    ensureMatchKanbanLists();
    updateApplicationsLaneState();
  }

  // Initialize on DOM ready
  $(document).ready(initLinkedInCRM);
})(jQuery);

function initLiveFeedTicker() {
  var $feeds = jQuery(".sffc-crm-live-feed");
  if (!$feeds.length) {
    return;
  }

  $feeds.each(function () {
    var $feed = jQuery(this);
    var rawData = $feed.attr("data-live-feed") || "[]";
    var queue;
    try {
      queue = JSON.parse(rawData) || [];
    } catch (err) {
      queue = [];
    }

    if (!Array.isArray(queue) || !queue.length) {
      return;
    }

    var interval = parseInt($feed.attr("data-live-feed-interval"), 10) || 10000;
    var maxItems = parseInt($feed.attr("data-live-feed-max"), 10) || 8;
    var $stream = $feed.find(".sffc-crm-live-feed-stream");
    var $dropdown = $feed.find("[data-new-post-dropdown]");

    if (!$stream.length || !$dropdown.length) {
      return;
    }

    var pendingItem = null;
    var pendingTimer = null;
    var cooldownTimer = null;
    var initialDelay = parseInt($feed.attr("data-live-feed-initial-delay"), 10);
    if (isNaN(initialDelay) || initialDelay < 0) {
      initialDelay = 10000;
    }
    var alertCooldown = 12000;
    var isCoolingDown = true;

    function showAlertIfReady() {
      if (!pendingItem || isCoolingDown) {
        return;
      }
      $dropdown.removeAttr("hidden");
    }

    function requestNextPending(delayOverride) {
      if (!queue.length) {
        return;
      }
      if (pendingTimer) {
        clearTimeout(pendingTimer);
      }
      var wait = typeof delayOverride === "number" ? delayOverride : interval;
      pendingTimer = setTimeout(function () {
        pendingTimer = null;
        if (pendingItem) {
          requestNextPending(interval);
          return;
        }
        pendingItem = queue.shift();
        showAlertIfReady();
      }, wait);
    }

    function renderItem(item) {
      var fallbackName = "MENA Careers Member";
      if (
        window.sffcCRMLinkedIn &&
        window.sffcCRMLinkedIn.i18n &&
        window.sffcCRMLinkedIn.i18n.liveFeedName
      ) {
        fallbackName = window.sffcCRMLinkedIn.i18n.liveFeedName;
      }

      var qaStrings =
        (window.sffcCRMLinkedIn &&
          window.sffcCRMLinkedIn.qa &&
          window.sffcCRMLinkedIn.qa.strings) ||
        {};
      var isLoggedIn = !!(
        window.sffcCRMLinkedIn && window.sffcCRMLinkedIn.isLoggedIn
      );
      var status = item.status || "answered";
      var name = escapeFeedHtml(item.name || fallbackName);
      var time = escapeFeedHtml(item.time || "");
      var question = escapeFeedHtml(item.question || "");
      var answerPreview = escapeFeedHtml(item.answer_preview || "");
      var lockedCopy = escapeFeedHtml(
        qaStrings.locked || "Sign in to view this expert answer."
      );
      var pendingCopy = escapeFeedHtml(
        qaStrings.pending || "Our mentor team has been notified."
      );
      var verifiedLabel = escapeFeedHtml(
        qaStrings.verifiedLabel || "✓ Expert replied..."
      );

      var answerMarkup;
      if (status === "pending") {
        answerMarkup =
          '<p class="sffc-crm-community-answer-preview">' +
          pendingCopy +
          "</p>";
      } else if (isLoggedIn) {
        answerMarkup =
          '<p class="sffc-crm-community-answer-preview">' +
          (answerPreview || pendingCopy) +
          "</p>";
      } else {
        answerMarkup =
          '<p class="sffc-crm-community-answer-lock">' + lockedCopy + "</p>";
      }

      var answerClass = "sffc-crm-community-answer-text";
      if (!isLoggedIn && status === "answered") {
        answerClass += " is-locked";
      }

      return (
        '<div class="sffc-crm-community-message">' +
        '<div class="sffc-crm-community-message-content">' +
        '<div class="sffc-crm-community-message-header">' +
        "<strong>" +
        name +
        "</strong>" +
        "<span>" +
        time +
        "</span>" +
        "</div>" +
        '<p class="sffc-crm-community-question">' +
        question +
        "</p>" +
        '<div class="sffc-crm-community-answer-meta">' +
        '<span class="sffc-crm-community-answer-icon" aria-hidden="true">' +
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3a9 9 0 1 1-6.36 2.64"/><path d="M8 9h8"/><path d="M8 13h6"/><path d="M8 17h4"/></svg>' +
        "</span>" +
        '<div class="' +
        answerClass +
        '">' +
        "<strong>" +
        verifiedLabel +
        "</strong>" +
        answerMarkup +
        "</div>" +
        "</div>" +
        "</div>" +
        "</div>"
      );
    }

    window.sffcCRMLiveFeedRender = renderItem;

    function injectPendingItem() {
      if (!pendingItem) {
        return;
      }
      var html = renderItem(pendingItem);
      var $item = jQuery(html).addClass("is-new");
      $stream.prepend($item);
      $item[0].offsetHeight; // force reflow for animation
      $item.removeClass("is-new");
      var $messages = $stream.children(".sffc-crm-community-message");
      if ($messages.length > maxItems) {
        $messages.slice(maxItems).remove();
      }
      pendingItem = null;
    }

    $dropdown.on("click", function (e) {
      e.preventDefault();
      injectPendingItem();
      $dropdown.attr("hidden", "hidden").removeClass("is-visible");
      isCoolingDown = true;

      if (cooldownTimer) {
        clearTimeout(cooldownTimer);
      }
      cooldownTimer = setTimeout(function () {
        isCoolingDown = false;
        showAlertIfReady();
      }, alertCooldown);

      requestNextPending();
    });

    // Initial delay before first alert
    cooldownTimer = setTimeout(function () {
      isCoolingDown = false;
      showAlertIfReady();
      requestNextPending(0);
    }, initialDelay);

    $feed.data("liveFeedCleanup", function () {
      if (pendingTimer) {
        clearTimeout(pendingTimer);
      }
      if (cooldownTimer) {
        clearTimeout(cooldownTimer);
      }
      $dropdown.off("click");
    });
  });
}

function escapeFeedHtml(str) {
  return String(str).replace(/[&<>"']/g, function (match) {
    var map = {
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#39;",
    };
    return map[match] || match;
  });
}

// ============================================
// Console Matching Engine (Rebuilt for 3-Step Flow)
// ============================================
(function ($) {
  "use strict";

  var ConsoleEngine = {
    // State
    currentStep: "criteria", // criteria | scanning | results
    currentFilter: "best",
    currentSort: "score-desc",
    selectedCriteria: null,
    allMatches: [],
    scanTimer: null,
    excludedFirms: [],
    compareSelectedIds: [],
    compareMaxSelections: 3,

    // DOM Cache
    $engine: null,
    $steps: {},

    init: function () {
      this.$engine = $("[data-console-engine]");
      if (!this.$engine.length) return;

      // Cache step containers
      this.$steps = {
        criteria: this.$engine.find('[data-console-step="criteria"]'),
        scanning: this.$engine.find('[data-console-step="scanning"]'),
        results: this.$engine.find('[data-console-step="results"]'),
      };

      this.bindEvents();
      if (this.initLandingSearchFromUrl()) {
        return;
      }
      this.initDefaultSelection();

      // Check if user has completed a scan before
      var hasScannedBefore = localStorage.getItem("sffc_console_has_scanned");
      if (hasScannedBefore === "true" && this.selectedCriteria) {
        // Auto-start scan for returning users
        console.log("Auto-starting scan for returning user");
        this.startScan();
      }
    },

    initLandingSearchFromUrl: function () {
      var params = new URLSearchParams(window.location.search || "");
      if (params.get("sffc_console_search") !== "1") {
        return false;
      }

      function collectParamSeries(baseName) {
        var values = [];
        var firstValue = (params.get(baseName) || "").trim();
        if (firstValue) {
          values.push(firstValue);
        }

        for (var index = 2; index <= 5; index++) {
          var value = (params.get(baseName + "_" + index) || "").trim();
          if (value) {
            values.push(value);
          }
        }

        return values;
      }

      var roles = collectParamSeries("role");
      var locations = collectParamSeries("location");
      var specialisations = collectParamSeries("specialisation");
      if (!specialisations.length) {
        specialisations = collectParamSeries("sector");
      }
      var seniorities = collectParamSeries("seniority");
      if (!seniorities.length) {
        seniorities = collectParamSeries("experience_level");
      }
      var agencies = collectParamSeries("agency").filter(function (agency) {
        return agency && !/^all\b/i.test(agency);
      });
      var hasSearchCriteria = !!(
        roles.length ||
        locations.length ||
        specialisations.length ||
        seniorities.length ||
        agencies.length
      );

      if (!hasSearchCriteria) {
        return false;
      }

      this.selectedCriteria = {
        id: 0,
        name: "Landing Search",
        job_title: roles.join(", "),
        sector: specialisations,
        location: locations,
        recruiter_firm: agencies,
        experience_level: seniorities,
        years_experience: "",
        skills_keywords: [],
      };

      this.$engine.attr("data-console-source", "landing-search");
      this.startScan();
      return true;
    },

    bindEvents: function () {
      var self = this;

      // Criteria card selection (radio behavior)
      this.$engine.on("change", 'input[name="console_criteria"]', function () {
        console.log("Radio change event fired for:", $(this).val());
        self.selectCriteria($(this));
      });

      // Also handle direct clicks on the card to ensure selection works
      this.$engine.on("click", ".sffc-console-criteria-card", function (e) {
        var $card = $(this);
        var $radio = $card.find('input[name="console_criteria"]');
        if ($radio.length && !$radio.prop("checked")) {
          console.log("Card clicked, selecting radio:", $radio.val());
          $radio.prop("checked", true).trigger("change");
          self.selectCriteria($radio);
        }
      });

      // Run Scan button
      this.$engine.on("click", "[data-console-run]", function () {
        console.log(
          "Run scan clicked. Selected criteria:",
          self.selectedCriteria
        );
        self.startScan();
      });

      // Filter tabs (Skyscanner-style)
      this.$engine.on("click", "[data-console-filter]", function () {
        var filter = $(this).data("console-filter");
        self.switchFilter(filter);
      });

      // Sort dropdown
      this.$engine.on("change", "[data-console-sort]", function () {
        self.currentSort = $(this).val();
        self.renderResults();
      });

      // Back to criteria (Adjust Criteria button)
      this.$engine.on("click", "[data-console-back]", function () {
        self.goToStep("criteria");
      });

      // Create criteria button (redirect to following tab)
      this.$engine.on("click", '[data-tab="following"]', function () {
        $('.sffc-crm-tab[data-tab="following"]').trigger("click");
      });

      // Message recruiter button
      $(document).on(
        "click",
        ".sffc-console-match-btn--message",
        function (e) {
          e.preventDefault();
          e.stopPropagation();

          if (!window.sffcCRMLinkedIn?.isLoggedIn) {
            window.location.href = "/join/";
            return;
          }

          var $btn = $(this);
          var recruiterEmail = $btn.data("recruiter-email");
          var recruiterName = $btn.data("recruiter-name");
          var matchId =
            $btn.data("match-id") ||
            $btn.closest(".sffc-console-match-card").data("match-id") ||
            0;
          var roleTitle = $btn.data("role-title") || "";
          var companyName = $btn.data("company-name") || "";

          console.log("Message recruiter:", recruiterName, recruiterEmail);

          if (!recruiterEmail) {
            alert(
              'Email not available for this recruiter. Try "Apply (Tailored CV)" instead.'
            );
            return;
          }

          var openMessageClient = function () {
            var subject = encodeURIComponent(
              "Interested in discussing the opportunity"
            );
            var body = encodeURIComponent(
              "Hi " +
                recruiterName +
                ",\n\nI came across your posting on MENA Careers and I'm very interested in learning more about the opportunity.\n\nLooking forward to connecting.\n\nBest regards"
            );
            window.location.href =
              "mailto:" +
              recruiterEmail +
              "?subject=" +
              subject +
              "&body=" +
              body;
          };

          if ($btn.data("credit-recorded")) {
            openMessageClient();
            return;
          }

          if ($btn.prop("disabled") || $btn.hasClass("is-loading")) {
            return;
          }

          $btn.prop("disabled", true).addClass("is-loading");

          $.post(
            window.sffcCRMLinkedIn?.ajaxUrl || "/wp-admin/admin-ajax.php",
            {
              action: "sffc_crm_track_message_email_credit",
              nonce: window.sffcCRMLinkedIn?.nonce || "",
              post_id: matchId,
              recruiter_email: recruiterEmail,
              recruiter_name: recruiterName,
              role_title: roleTitle,
              company_name: companyName,
            },
            function (response) {
              if (response.success) {
                $btn.data("credit-recorded", true);
                if (response.data && response.data.credit_summary) {
                  if (typeof syncApplyAllCreditMeters === "function") {
                    syncApplyAllCreditMeters(response.data.credit_summary);
                  }
                }
                window.setTimeout(openMessageClient, 120);
                return;
              }

              if (response.data && response.data.credit_summary) {
                if (typeof syncApplyAllCreditMeters === "function") {
                  syncApplyAllCreditMeters(response.data.credit_summary);
                }
              }
              handleMessageCreditFailure(response);
            }
          )
            .fail(function (xhr) {
              var response = xhr.responseJSON || {};
              if (response.data && response.data.credit_summary) {
                if (typeof syncApplyAllCreditMeters === "function") {
                  syncApplyAllCreditMeters(response.data.credit_summary);
                }
              }
              handleMessageCreditFailure(response);
            })
            .always(function () {
              $btn.prop("disabled", false).removeClass("is-loading");
            });
        }
      );

      $(document).on("click", ".sffc-membership-cancel-btn", function (e) {
        e.preventDefault();

        var $btn = $(this);
        var subscriptionId = $btn.data("subscription-id");
        var planName = $btn.data("plan-name") || "your membership";
        var $panel = $btn.closest("[data-membership-cancel-panel]");
        var $feedback = $panel.find("[data-membership-cancel-feedback]");

        if (!subscriptionId) {
          return;
        }

        if (
          !window.confirm(
            "Cancel " +
              planName +
              "? We will email you a confirmation once the request is processed."
          )
        ) {
          return;
        }

        $btn.prop("disabled", true).addClass("is-loading");
        $feedback
          .removeClass("is-error is-success")
          .text("Processing your cancellation request...")
          .prop("hidden", false);

        $.post(config.ajaxUrl || "/wp-admin/admin-ajax.php", {
          action: "sffc_cancel_subscription",
          subscription_id: subscriptionId,
          nonce: config.nonce || "",
        })
          .done(function (response) {
            if (response && response.success) {
              $feedback
                .removeClass("is-error")
                .addClass("is-success")
                .text(
                  (response.data && response.data.message) ||
                    "Your cancellation request has been received. Please check your email."
                )
                .prop("hidden", false);
              $btn.text("Cancellation requested");
              showToast("Cancellation request received", "success");
              return;
            }

            $feedback
              .removeClass("is-success")
              .addClass("is-error")
              .text(
                (response.data && response.data.message) ||
                  "Unable to cancel this subscription. Please try again."
              )
              .prop("hidden", false);
            $btn.prop("disabled", false).removeClass("is-loading");
          })
          .fail(function (xhr) {
            var response = xhr.responseJSON || {};
            $feedback
              .removeClass("is-success")
              .addClass("is-error")
              .text(
                (response.data && response.data.message) ||
                  "Unable to cancel this subscription. Please try again."
              )
              .prop("hidden", false);
            $btn.prop("disabled", false).removeClass("is-loading");
          });
      });

      this.$engine.on("click", ".sffc-console-match-btn--list", function (e) {
        e.preventDefault();
        e.stopPropagation();

        var $btn = $(this);
        if ($btn.hasClass("is-added") || $btn.prop("disabled")) {
          return;
        }

        if (!window.sffcCRMLinkedIn?.isLoggedIn) {
          window.location.href = joinUrl;
          return;
        }

        var matchId = $btn.data("match-id");
        var match = self.allMatches.find(function (item) {
          return String(item.id || "") === String(matchId || "");
        });

        if (match) {
          self.openAddToListModal(match, $btn);
        }
      });

      $(document).on("click", ".sffc-console-compare-save", function (e) {
        e.preventDefault();
        e.stopPropagation();

        if (!window.sffcCRMLinkedIn?.isLoggedIn) {
          window.location.href = window.sffcCRMLinkedIn?.joinUrl || "/join/";
          return;
        }

        var $btn = $(this);
        if ($btn.hasClass("is-saved")) {
          self.notify("This role is already saved to one of your lists.", "success");
          return;
        }

        var matchId = $btn.data("match-id");
        var match = self.getMatchById(matchId);
        if (match) {
          self.openAddToListModal(match, $btn);
        }
      });

      $(document).on("change", "[data-compare-toggle]", function (e) {
        e.stopPropagation();
        var $input = $(this);
        var matchId = $input.data("match-id");
        if (!self.toggleCompareSelection(matchId, $input.prop("checked"))) {
          $input.prop("checked", !$input.prop("checked"));
          return;
        }

        if ($("[data-console-compare-modal]").length) {
          if (self.compareSelectedIds.length < 2) {
            self.closeCompareModal();
            self.notify("Select at least 2 roles to keep the comparison open.", "error");
            return;
          }
          self.openCompareModal();
        }
      });

      $(document).on("click", ".sffc-console-compare-btn", function (e) {
        e.preventDefault();
        e.stopPropagation();
        self.openCompareModal($(this).data("match-id"));
      });

      this.$engine.on(
        "click",
        ".sffc-console-match-btn--apply-cv",
        function (e) {
          e.preventDefault();
          e.stopPropagation();

          var $btn = $(this);
          var $card = $btn.closest(".sffc-console-match-card");
          var matchId = $card.data("match-id") || $btn.data("post-id") || 0;
          self.openMatchModal(matchId);
        }
      );

      this.$engine.on("click", ".sffc-console-match-open-modal", function (e) {
        e.preventDefault();
        e.stopPropagation();

        if (!window.sffcCRMLinkedIn?.isLoggedIn) {
          window.location.href = joinUrl;
          return;
        }

        var $trigger = $(this);
        var matchId =
          $trigger.data("match-id") ||
          $trigger.closest(".sffc-console-match-card").data("match-id") ||
          0;

        if (matchId) {
          self.openMatchModal(matchId);
        }
      });

      $(document).on("click", "[data-console-compare-close]", function (e) {
        e.preventDefault();
        self.closeCompareModal();
      });

      $(document).on("keydown", function (e) {
        if (e.key === "Escape" && $("[data-console-compare-modal]").length) {
          self.closeCompareModal();
        }
      });

    },

    initDefaultSelection: function () {
      // Only select default if user hasn't made a selection yet
      if (this.selectedCriteria) {
        console.log("Criteria already selected, skipping init");
        return;
      }

      // Check if user has a saved criteria preference
      var savedCriteriaId = localStorage.getItem(
        "sffc_console_selected_criteria_id"
      );
      if (savedCriteriaId) {
        var $savedRadio = this.$engine
          .find('input[name="console_criteria"]')
          .filter(function () {
            try {
              var payload = JSON.parse($(this).attr("data-criteria-payload"));
              return payload && payload.id == savedCriteriaId;
            } catch (e) {
              return false;
            }
          });

        if ($savedRadio.length) {
          console.log("Restoring saved criteria:", savedCriteriaId);
          $savedRadio.prop("checked", true);
          this.selectCriteria($savedRadio);
          return;
        }
      }

      // Auto-select the default or first criteria
      var $defaultRadio = this.$engine.find(
        'input[name="console_criteria"]:checked'
      );
      if ($defaultRadio.length) {
        console.log("Found default checked radio:", $defaultRadio.val());
        this.selectCriteria($defaultRadio);
      } else {
        var $firstRadio = this.$engine
          .find('input[name="console_criteria"]')
          .first();
        if ($firstRadio.length) {
          console.log(
            "No default found, selecting first radio:",
            $firstRadio.val()
          );
          $firstRadio.prop("checked", true);
          this.selectCriteria($firstRadio);
        }
      }
    },

    selectCriteria: function ($radio) {
      try {
        var payloadStr = $radio.attr("data-criteria-payload");
        this.selectedCriteria = payloadStr ? JSON.parse(payloadStr) : null;
        console.log(
          "✓ Criteria selected:",
          this.selectedCriteria?.name,
          this.selectedCriteria
        );

        // Save selected criteria ID to localStorage for persistence
        if (this.selectedCriteria && this.selectedCriteria.id) {
          localStorage.setItem(
            "sffc_console_selected_criteria_id",
            this.selectedCriteria.id
          );
        }
      } catch (e) {
        console.error("❌ Failed to parse criteria payload:", e, payloadStr);
        this.selectedCriteria = null;
      }

      // Update visual state
      this.$engine
        .find(".sffc-console-criteria-card")
        .removeClass("is-selected");
      $radio.closest(".sffc-console-criteria-card").addClass("is-selected");

      // Also update checked state on all radios
      this.$engine
        .find('input[name="console_criteria"]')
        .not($radio)
        .prop("checked", false);
      $radio.prop("checked", true);
    },

    goToStep: function (step) {
      console.log("Going to step:", step);
      this.currentStep = step;

      // Hide all steps
      Object.values(this.$steps).forEach(function ($step) {
        $step.attr("hidden", true);
      });

      // Show target step
      if (this.$steps[step]) {
        this.$steps[step].removeAttr("hidden");
      }

      // Reset scan UI state when going back to criteria (but keep selected criteria)
      if (step === "criteria") {
        this.resetScanState();
      }
    },

    resetScanState: function () {
      if (this.scanTimer) {
        clearInterval(this.scanTimer);
        this.scanTimer = null;
      }

      // Reset progress UI
      this.$engine.find("[data-console-progress-fill]").css("width", "0%");
      this.$engine.find("[data-console-progress-percent]").text("0% complete");
      this.$engine.find("[data-console-scanned]").text("0 jobs scanned");
      this.$engine.find("[data-console-matches-found]").text("0 matches found");
      this.$engine.find("[data-console-preview-list]").empty();
      this.$engine
        .find("[data-console-progress-status]")
        .removeClass("is-complete")
        .addClass("is-building")
        .find("[data-console-status-text]")
        .text("Building...");
    },

    startScan: function () {
      var self = this;

      console.log("🚀 Starting scan with criteria:", this.selectedCriteria);

      if (!this.selectedCriteria) {
        console.error("❌ No criteria selected");
        alert("Please select a criteria group to scan.");
        return;
      }

      console.log(
        "✓ Using criteria:",
        this.selectedCriteria.name,
        "(ID:",
        this.selectedCriteria.id + ")"
      );

      // Transition to scanning step
      this.goToStep("scanning");

      // Get total jobs count from data attribute
      var totalJobs = parseInt(this.$engine.data("total-jobs")) || 850;

      // Animate scanning progress
      var progress = 0;
      var scannedJobs = 0;
      var matchesFound = 0;
      var previewMatches = [];

      var $progressFill = this.$engine.find("[data-console-progress-fill]");
      var $progressPercent = this.$engine.find(
        "[data-console-progress-percent]"
      );
      var $scannedCount = this.$engine.find("[data-console-scanned]");
      var $matchesCount = this.$engine.find("[data-console-matches-found]");
      var $previewList = this.$engine.find("[data-console-preview-list]");
      var $status = this.$engine.find("[data-console-progress-status]");
      var $completed = this.$engine.find("[data-console-completed]");

      // Start the AJAX request early (parallel with animation)
      var ajaxPromise = this.fetchMatchesAsync();

      this.scanTimer = setInterval(function () {
        // Increment progress (slow start, faster middle, slow end)
        var increment = progress < 20 ? 2 : progress < 80 ? 4 : 1.5;
        progress = Math.min(progress + Math.random() * increment, 95);

        scannedJobs = Math.floor((progress / 100) * totalJobs);

        // Update progress bar with smooth animation
        $progressFill.css("width", progress + "%");
        $progressPercent.text(Math.floor(progress) + "% complete");
        $scannedCount.text(scannedJobs.toLocaleString() + " jobs scanned");

        // Simulate finding matches (trickle in during scan)
        // Show matches at specific progress milestones for consistent UX
        var shouldShowMatch = false;
        if (progress > 15 && previewMatches.length === 0) {
          shouldShowMatch = true; // First match at 15%
        } else if (progress > 25 && previewMatches.length === 1) {
          shouldShowMatch = true; // Second match at 25%
        } else if (
          progress > 35 &&
          previewMatches.length < 8 &&
          Math.random() > 0.5
        ) {
          shouldShowMatch = true; // More matches randomly after 35%
        }

        if (shouldShowMatch && previewMatches.length < 8) {
          matchesFound++;
          $matchesCount.text(
            matchesFound +
              " match" +
              (matchesFound !== 1 ? "es" : "") +
              " found"
          );

          // Add placeholder preview card
          var placeholderMatch = self.generatePlaceholderMatch(matchesFound);
          previewMatches.push(placeholderMatch);
          $previewList.append(self.renderPreviewCard(placeholderMatch));
        }
      }, 120);

      // When AJAX completes, finish the animation
      ajaxPromise
        .then(function (matches) {
          // Clear the interval
          if (self.scanTimer) {
            clearInterval(self.scanTimer);
            self.scanTimer = null;
          }

          // Complete progress to 100%
          $progressFill.css("width", "100%");
          $progressPercent.text("100% complete");
          $scannedCount.text(totalJobs.toLocaleString() + " jobs scanned");
          $matchesCount.text(matches.length + " matches found");

          // Update status to complete
          $status.removeClass("is-building").addClass("is-complete");
          $status.find("[data-console-status-text]").text("Complete");
          $completed.removeAttr("hidden").text("Completed just now");

          // Store matches
          self.allMatches = matches;

          // Clear placeholder previews and show real ones
          $previewList.empty();
          matches.slice(0, 5).forEach(function (match) {
            $previewList.append(self.renderPreviewCard(match));
          });

          // After a brief delay, transition to results
          setTimeout(function () {
            self.showResultsInitial();
          }, 1200);
        })
        .catch(function (error) {
          console.error("Console scan error:", error);

          if (self.scanTimer) {
            clearInterval(self.scanTimer);
            self.scanTimer = null;
          }

          $status.removeClass("is-building").addClass("is-error");
          $status.find("[data-console-status-text]").text("Error");

          // Show error and go back
          setTimeout(function () {
            alert("An error occurred while scanning. Please try again.");
            self.goToStep("criteria");
          }, 500);
        });
    },

    fetchMatchesAsync: function () {
      var self = this;

      return new Promise(function (resolve, reject) {
        $.ajax({
          url: window.sffcCRMLinkedIn?.ajaxUrl || "/wp-admin/admin-ajax.php",
          method: "POST",
          timeout: 30000,
          data: {
            action: "sffc_crm_console_scan_matches",
            nonce: window.sffcCRMLinkedIn?.nonce || "",
            criteria: self.selectedCriteria,
          },
          success: function (response) {
            try {
              if (typeof response === "string") {
                response = JSON.parse(response);
              }

              var data = response && response.data ? response.data : {};
              var debug = data.debug || null;
              console.log("📊 AJAX Response Debug:", debug);
              if (debug) {
                console.log(
                  "Total posts fetched:",
                  debug.total_posts_fetched
                );
                console.log("Criteria used:", debug.criteria_used);
                console.log("Top 10 scores:", debug.top_10_scores);
              }

              if (response && response.success) {
                var matches = Array.isArray(data.matches) ? data.matches : [];
                console.log("✅ Found", matches.length, "matches");
                resolve(matches);
              } else {
                console.warn("⚠️ Console scan returned no matches", response);
                resolve([]);
              }
            } catch (parseError) {
              reject(parseError);
            }
          },
          error: function (xhr, status, error) {
            reject(error || status || "request_failed");
          },
        });
      });
    },

    generatePlaceholderMatch: function (index) {
      var companies = [
        "Goldman Sachs",
        "JP Morgan",
        "Morgan Stanley",
        "Citadel",
        "BlackRock",
        "Bridgewater",
        "Two Sigma",
        "Barclays",
      ];
      var titles = [
        "Finance Manager",
        "VP Finance",
        "Associate Director",
        "Investment Manager",
        "Portfolio Manager",
        "Risk Manager",
      ];
      var recruiters = [
        "Sarah Johnson",
        "Michael Chen",
        "Emily Rodriguez",
        "David Kim",
        "Jessica Martinez",
        "James Wilson",
        "Amanda Taylor",
        "Robert Anderson",
      ];
      var scores = [92, 88, 85, 82, 79, 76, 74, 71];

      return {
        id: "placeholder-" + index,
        role_title: titles[(index - 1) % titles.length],
        company: companies[(index - 1) % companies.length],
        match_score: scores[(index - 1) % scores.length],
        has_recruiter: true,
        recruiter_name: recruiters[(index - 1) % recruiters.length],
        recruiter_photo: "", // No photo for placeholder, will show initial
        is_placeholder: true,
      };
    },

    renderPreviewCard: function (match) {
      var score = match.match_score || 75;
      var insight = match.insight || this.getInsight(score);

      // Prioritize recruiter avatar over company logo
      var avatar = "";
      if (match.has_recruiter && match.recruiter_photo) {
        avatar =
          '<img src="' +
          this.escapeHtml(match.recruiter_photo) +
          '" alt="' +
          this.escapeHtml(match.recruiter_name || "") +
          '">';
      } else if (match.has_recruiter) {
        var recruiterInitial = (match.recruiter_name || "R")
          .charAt(0)
          .toUpperCase();
        avatar =
          '<span class="sffc-console-preview-initial sffc-console-preview-initial--recruiter">' +
          recruiterInitial +
          "</span>";
      } else if (match.company_logo) {
        avatar =
          '<img src="' + this.escapeHtml(match.company_logo) + '" alt="">';
      } else {
        var initial = (match.company || match.role_title || "S")
          .charAt(0)
          .toUpperCase();
        avatar =
          '<span class="sffc-console-preview-initial">' + initial + "</span>";
      }

      return (
        '<li class="sffc-console-preview-item' +
        (match.is_placeholder ? " is-placeholder" : "") +
        '">' +
        '<div class="sffc-console-preview-logo">' +
        avatar +
        "</div>" +
        '<div class="sffc-console-preview-info">' +
        "<strong>" +
        this.escapeHtml(match.role_title || "Opportunity") +
        "</strong>" +
        "<span>" +
        this.escapeHtml(match.company || "") +
        "</span>" +
        "</div>" +
        '<div class="sffc-console-preview-score">' +
        '<span class="sffc-console-preview-score-value">' +
        score +
        "%</span>" +
        '<span class="sffc-console-preview-insight">' +
        this.escapeHtml(insight) +
        "</span>" +
        "</div>" +
        "</li>"
      );
    },

    showResults: function () {
      this.updateFilterCounts();
      this.renderResults();

      // Transition to results step
      this.goToStep("results");
    },

    updateFilterCounts: function () {
      var self = this;

      // Calculate filter counts from the same base set used for rendering.
      var firmFilteredMatches = this.getFirmFilteredMatches(this.allMatches);
      var bestMatches = firmFilteredMatches.filter(function (m) {
        return !m.intro_requested;
      });
      var closingMatches = firmFilteredMatches.filter(function (m) {
        return self.isClosingSoonMatch(m);
      });
      var newestMatches = firmFilteredMatches.slice().sort(function (a, b) {
        return new Date(b.posted_at || 0) - new Date(a.posted_at || 0);
      });

      // Update counts in filter tabs
      this.$engine.find("[data-console-count]").text(firmFilteredMatches.length);
      this.$engine.find("[data-console-best-count]").text(bestMatches.length);
      this.$engine
        .find("[data-console-closing-count]")
        .text(closingMatches.length);
      this.$engine
        .find("[data-console-newest-count]")
        .text(newestMatches.length);

      // Update filter subtitles (featured company/recruiter)
      if (bestMatches.length > 0) {
        this.$engine
          .find("[data-console-best-company]")
          .text(bestMatches[0].company || "");
      }
      if (closingMatches.length > 0) {
        this.$engine
          .find("[data-console-closing-company]")
          .text(closingMatches[0].company || closingMatches[0].role_title || "");
      }
      if (newestMatches.length > 0) {
        this.$engine
          .find("[data-console-newest-company]")
          .text(newestMatches[0].company || "");
      }
    },

    showNotification: function (message) {
      var $notification = $(
        '<div class="sffc-console-notification">' +
          '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>' +
          "<span>" +
          this.escapeHtml(message) +
          "</span>" +
          "</div>"
      );

      this.$engine.find("[data-console-step]").append($notification);

      setTimeout(function () {
        $notification.addClass("is-visible");
      }, 10);

      setTimeout(function () {
        $notification.removeClass("is-visible");
        setTimeout(function () {
          $notification.remove();
        }, 300);
      }, 4000);
    },

    showResultsInitial: function () {
      this.updateFilterCounts();

      // Reset filter to Best
      this.currentFilter = "best";
      this.currentSort = "score-desc";
      this.$engine.find("[data-console-filter]").removeClass("active");
      this.$engine.find('[data-console-filter="best"]').addClass("active");
      this.$engine.find("[data-console-sort]").val("score-desc");
      this.$engine.find("[data-console-sort-label]").text("Best");

      // Build firm filter pills
      this.renderFirmFilters();

      // Render matches
      this.renderResults();

      // Mark that user has completed their first scan
      localStorage.setItem("sffc_console_has_scanned", "true");

      // Transition to results step
      this.goToStep("results");
    },

    renderFirmFilters: function () {
      var self = this;
      var $container = this.$engine.find("[data-console-firm-filters]");
      var criteria = this.selectedCriteria || {};
      var criteriaSummary = this.getCriteriaSummary(criteria);

      // Extract unique firms from matches
      var firms = {};
      this.allMatches.forEach(function (match) {
        if (match.has_recruiter && match.recruiter_firm) {
          var firm = match.recruiter_firm.trim();
          if (firm) {
            firms[firm] = (firms[firm] || 0) + 1;
          }
        }
      });

      var firmList = Object.keys(firms);

      if (firmList.length === 0 && !criteriaSummary.length) {
        $container.attr("hidden", true);
        return;
      }

      // Sort firms by count (descending)
      firmList.sort(function (a, b) {
        return firms[b] - firms[a];
      });

      var pillsHTML =
        '<button type="button" class="sffc-console-criteria-summary" data-console-criteria-toggle aria-expanded="false">' +
        '<span class="sffc-console-criteria-summary__label">Filtered Criteria</span>' +
        '<span class="sffc-console-criteria-summary__chips">' +
        criteriaSummary
          .map(function (item) {
            return (
              '<span class="sffc-console-criteria-chip">' +
              self.escapeHtml(item) +
              "</span>"
            );
          })
          .join("") +
        "</span>" +
        '<svg class="sffc-console-criteria-summary__icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>' +
        "</button>" +
        '<form class="sffc-crm-console-search sffc-console-criteria-search" data-console-criteria-search hidden>' +
        this.renderCriteriaSearchBar(criteria) +
        "</form>";

      if (firmList.length) {
        pillsHTML +=
          '<div class="sffc-console-firm-row">' +
          '<div class="sffc-console-firm-filters-label">Recruitment Firms:</div>' +
          '<div class="sffc-console-firm-pills-wrapper">';
      }

      firmList.forEach(function (firm, index) {
        var count = firms[firm];
        pillsHTML +=
          '<button type="button" class="sffc-console-firm-pill" data-firm-name="' +
          self.escapeHtml(firm) +
          '" data-pill-index="' +
          index +
          '">' +
          '<span class="sffc-console-firm-pill-name">' +
          self.escapeHtml(firm) +
          "</span>" +
          '<span class="sffc-console-firm-pill-count">(' +
          count +
          ")</span>" +
          '<svg class="sffc-console-firm-pill-remove" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">' +
          '<line x1="18" y1="6" x2="6" y2="18"/>' +
          '<line x1="6" y1="6" x2="18" y2="18"/>' +
          "</svg>" +
          "</button>";
      });

      if (firmList.length) {
        pillsHTML += "</div>";

        // Add "Show More" button
        pillsHTML +=
          '<button type="button" class="sffc-console-firm-show-more" data-firm-show-more hidden>' +
          '<span class="sffc-console-firm-show-more-text">+<span class="sffc-console-firm-show-more-count"></span> more</span>' +
          '<svg class="sffc-console-firm-show-more-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">' +
          '<polyline points="6 9 12 15 18 9"/>' +
          "</svg>" +
          "</button>" +
          "</div>";
      }

      $container.html(pillsHTML).removeAttr("hidden");

      // Calculate which pills to show/hide based on 2-line limit
      this.calculateFirmFilterOverflow();

      // Bind click events to pills
      $container.find(".sffc-console-firm-pill").on("click", function () {
        var firmName = $(this).data("firm-name");
        self.toggleFirmExclusion(firmName);
      });

      // Bind click event to "Show More" button
      $container.find("[data-firm-show-more]").on("click", function () {
        self.toggleFirmFiltersExpanded();
      });

      $container.find("[data-console-criteria-toggle]").on("click", function () {
        var $toggle = $(this);
        var expanded = $container.hasClass("is-criteria-expanded");
        $container.toggleClass("is-criteria-expanded", !expanded);
        $toggle.attr("aria-expanded", expanded ? "false" : "true");
        $container.find("[data-console-criteria-search]").prop("hidden", expanded);
      });

      $container.find("[data-console-criteria-search]").on("submit", function (event) {
        event.preventDefault();
        var $form = $(this);
        var role = ($form.find('[name="role"]').val() || "").trim();
        var seniority = ($form.find('[name="seniority"]').val() || "").trim();
        var location = ($form.find('[name="location"]').val() || "").trim();
        var specialisation = ($form.find('[name="specialisation"]').val() || "").trim();

        self.selectedCriteria = {
          id: 0,
          name: "Filtered Criteria",
          job_title: role,
          sector: specialisation ? [specialisation] : [],
          location: location ? [location] : [],
          experience_level: seniority ? [seniority] : [],
          years_experience: "",
          skills_keywords: [],
        };
        self.startScan();
      });
    },

    getCriteriaSummary: function (criteria) {
      var summary = [];
      var role = (criteria.job_title || "").trim();
      var location = Array.isArray(criteria.location)
        ? criteria.location.join(", ")
        : criteria.location || "";
      var sectors = Array.isArray(criteria.sector)
        ? criteria.sector
        : criteria.sector
        ? [criteria.sector]
        : [];
      var seniority = Array.isArray(criteria.experience_level)
        ? criteria.experience_level
        : criteria.experience_level
        ? [criteria.experience_level]
        : [];

      if (role) summary.push(role);
      if (seniority.length) summary.push(seniority.join(", "));
      if (location) summary.push(location);
      if (sectors.length) summary.push(sectors.join(", "));
      if (!summary.length) summary.push("All roles");
      return summary;
    },

    renderCriteriaSearchBar: function (criteria) {
      var role = (criteria.job_title || "").trim();
      var location = Array.isArray(criteria.location)
        ? criteria.location[0] || ""
        : criteria.location || "";
      var seniority = Array.isArray(criteria.experience_level)
        ? criteria.experience_level[0] || ""
        : criteria.experience_level || "";
      var specialisation = Array.isArray(criteria.sector)
        ? criteria.sector[0] || ""
        : criteria.sector || "";
      var seniorityOptions = [
        ["", "All Seniority"],
        ["analyst", "Manager / Senior Manager (2-5 yrs)"],
        ["associate", "Associate (4-7 yrs)"],
        ["senior_associate", "Senior Associate (6-8 yrs)"],
        ["vp", "Vice President / Principal"],
        ["director", "Director / Executive Director"],
        ["md", "Managing Director"],
        ["partner", "Partner"],
        ["c_level", "C-Level / Head of Function"],
        ["board", "Board / Advisor"],
      ];
      var sectorOptions = [
        ["", "All Specialisations"],
        ["pe", "Private Equity"],
        ["ib", "Investment Banking (PE feeder)"],
        ["vc", "Growth Equity / Venture Capital"],
        ["hedge_fund", "Hedge Fund"],
        ["asset_management", "Real Assets / Infrastructure"],
        ["private_credit", "Private Credit / Direct Lending"],
        ["family_office", "Family Office"],
        ["consulting", "Management Consulting"],
        ["corporate", "Corporate Finance / Strategy"],
        ["fintech", "FinTech / Startups"],
        ["real_estate", "Real Estate Investing"],
        ["infrastructure", "Infrastructure / Project Finance"],
        ["energy", "Energy / Natural Resources"],
        ["healthcare", "Healthcare / Life Sciences"],
        ["technology", "Technology / Software"],
        ["government", "Government / Sovereign Funds"],
        ["non_profit", "Impact / Non-Profit"],
        ["other", "Other"],
      ];

      function renderOptions(options, selected) {
        return options
          .map(function (option) {
            return (
              '<option value="' +
              option[0] +
              '"' +
              (String(option[0]) === String(selected) ? " selected" : "") +
              ">" +
              option[1] +
              "</option>"
            );
          })
          .join("");
      }

      return (
        '<div class="sffc-crm-console-search__bar">' +
        '<label class="sffc-crm-console-search__field"><span>Role</span><input type="text" name="role" value="' +
        this.escapeHtml(role) +
        '" placeholder="Finance Manager"></label>' +
        '<label class="sffc-crm-console-search__field"><span>Seniority</span><select name="seniority">' +
        renderOptions(seniorityOptions, seniority) +
        "</select></label>" +
        '<label class="sffc-crm-console-search__field"><span>Location</span><input type="text" name="location" value="' +
        this.escapeHtml(location) +
        '" placeholder="Dubai, London, Singapore"></label>' +
        '<label class="sffc-crm-console-search__field"><span>Specialisation</span><select name="specialisation">' +
        renderOptions(sectorOptions, specialisation) +
        "</select></label>" +
        '<button type="submit" class="sffc-crm-console-search__submit">Search</button>' +
        "</div>"
      );
    },

    calculateFirmFilterOverflow: function () {
      var $container = this.$engine.find("[data-console-firm-filters]");
      var $wrapper = $container.find(".sffc-console-firm-pills-wrapper");
      var $pills = $wrapper.find(".sffc-console-firm-pill");
      var $showMoreBtn = $container.find("[data-firm-show-more]");

      if ($pills.length === 0) {
        return;
      }

      // Temporarily expand to measure
      $wrapper.css("max-height", "none");
      $pills.show();

      // Get wrapper width and pill positions
      var wrapperTop = $wrapper.offset().top;
      var maxLinesBeforeButton = 2;
      var lineHeight = 40; // Approximate height per line (pill height + gap)
      var maxHeight = maxLinesBeforeButton * lineHeight;

      var visibleCount = 0;
      var hiddenCount = 0;

      $pills.each(function (index) {
        var $pill = $(this);
        var pillTop = $pill.offset().top - wrapperTop;

        if (pillTop < maxHeight) {
          visibleCount++;
        } else {
          hiddenCount++;
          $pill.addClass("is-overflow-hidden");
        }
      });

      // Show/hide the "Show More" button
      if (hiddenCount > 0) {
        $showMoreBtn.removeAttr("hidden");
        $showMoreBtn
          .find(".sffc-console-firm-show-more-count")
          .text(hiddenCount);
      } else {
        $showMoreBtn.attr("hidden", true);
      }

      // Collapse wrapper if not expanded
      if (!$container.hasClass("is-expanded")) {
        $wrapper.css("max-height", maxHeight + "px");
      }
    },

    toggleFirmFiltersExpanded: function () {
      var $container = this.$engine.find("[data-console-firm-filters]");
      var $wrapper = $container.find(".sffc-console-firm-pills-wrapper");
      var $pills = $wrapper.find(".sffc-console-firm-pill.is-overflow-hidden");
      var $showMoreBtn = $container.find("[data-firm-show-more]");

      if ($container.hasClass("is-expanded")) {
        // Collapse
        $container.removeClass("is-expanded");
        $wrapper.css("max-height", "80px"); // 2 lines
        $pills.addClass("is-overflow-hidden");
        $showMoreBtn
          .find(".sffc-console-firm-show-more-text")
          .html(
            '+<span class="sffc-console-firm-show-more-count">' +
              $pills.length +
              "</span> more"
          );
        $showMoreBtn.removeClass("is-rotated");
      } else {
        // Expand
        $container.addClass("is-expanded");
        $wrapper.css("max-height", "none");
        $pills.removeClass("is-overflow-hidden");
        $showMoreBtn
          .find(".sffc-console-firm-show-more-text")
          .text("Show less");
        $showMoreBtn.addClass("is-rotated");
      }
    },

    toggleFirmExclusion: function (firmName) {
      var index = this.excludedFirms.indexOf(firmName);

      if (index > -1) {
        // Re-include the firm
        this.excludedFirms.splice(index, 1);
        this.$engine
          .find('[data-firm-name="' + firmName + '"]')
          .removeClass("is-excluded");
      } else {
        // Exclude the firm
        this.excludedFirms.push(firmName);
        this.$engine
          .find('[data-firm-name="' + firmName + '"]')
          .addClass("is-excluded");
      }

      // Re-render results with updated filters
      this.renderResults();
    },

    switchFilter: function (filter) {
      this.currentFilter = filter;
      if (filter === "newest") {
        this.currentSort = "date-desc";
        this.$engine.find("[data-console-sort]").val("date-desc");
      } else if (filter === "closing") {
        this.currentSort = "closing-asc";
        this.$engine.find("[data-console-sort]").val("closing-asc");
      } else if (filter === "best") {
        this.currentSort = "score-desc";
        this.$engine.find("[data-console-sort]").val("score-desc");
      }

      // Update active state
      this.$engine.find("[data-console-filter]").removeClass("active");
      this.$engine
        .find('[data-console-filter="' + filter + '"]')
        .addClass("active");

      // Update sort label
      var labelMap = {
        best: "Best",
        closing: "Closing Soon",
        newest: "Newest",
      };
      this.$engine
        .find("[data-console-sort-label]")
        .text(labelMap[filter] || "Best");

      // Re-render
      this.renderResults();
    },

    renderResults: function () {
      var self = this;
      var $list = this.$engine.find("[data-console-results-list]");
      $list.empty();

      if (!this.allMatches || this.allMatches.length === 0) {
        // Get user's first name for personalized message
        var firstName = "";
        if (config.currentUserName) {
          firstName = config.currentUserName.trim().split(/\s+/)[0];
        }
        var greetingName = firstName || "Hi";

        $list.html(
          '<div class="sffc-console-empty-results">' +
            '<div class="sffc-console-empty-content">' +
              "<p class=\"sffc-console-empty-greeting\"><strong>" + greetingName + ",</strong> Didn't find a match?</p>" +
              "<p class=\"sffc-console-empty-message\">Don't worry! Let us know and we will get you the matches you are looking for in 24-48hrs.</p>" +
              '<div class="sffc-console-empty-actions">' +
                '<button type="button" class="sffc-console-adjust-btn" data-console-back>Adjust Criteria</button>' +
              "</div>" +
              '<div class="sffc-console-notify-form">' +
                '<p class="sffc-console-notify-title">Notify me when there are matches</p>' +
                '<textarea class="sffc-console-notify-notes" placeholder="Add any additional details about what you\'re looking for (optional)..." rows="3"></textarea>' +
                '<button type="button" class="sffc-console-notify-btn" data-console-notify-admin>Notify Me</button>' +
                '<p class="sffc-console-notify-feedback" style="display:none;"></p>' +
              "</div>" +
            "</div>" +
            "</div>"
        );

        // Bind notify admin handler
        $list.find("[data-console-notify-admin]").on("click", function () {
          self.submitCriteriaToAdmin($(this));
        });

        return;
      }

      // Filter matches
      var filtered = this.filterMatches(this.allMatches);

      // Sort matches
      var sorted = this.sortMatches(filtered);

      if (sorted.length === 0) {
        $list.html(
          '<div class="sffc-console-empty-results">' +
            '<div class="sffc-console-empty-content">' +
              '<p class="sffc-console-empty-greeting"><strong>No matches in this view.</strong></p>' +
              '<p class="sffc-console-empty-message">Try another tab or adjust your criteria to widen the search.</p>' +
              '<div class="sffc-console-empty-actions">' +
                '<button type="button" class="sffc-console-adjust-btn" data-console-back>Adjust Criteria</button>' +
              "</div>" +
            "</div>" +
          "</div>"
        );
        return;
      }

      // Render each match with stagger animation
      sorted.forEach(function (match, index) {
        var $card = $(self.renderMatchCard(match, index));
        $list.append($card);
      });

      // Bind click event to open modal
      $list.find(".sffc-console-match-card").on("click", function (e) {
        if (
          !window.sffcCRMLinkedIn?.isLoggedIn &&
          $(this).hasClass("is-guest-masked")
        ) {
          e.preventDefault();
          window.location.href = "/join/";
          return;
        }

        // Don't open modal if clicking on inline controls
        if ($(e.target).closest("button, a, input, label").length) {
          return;
        }
        var matchId = $(this).data("match-id");
        self.openMatchModal(matchId);
      });

      this.syncCompareSelectionUI();
    },

    filterMatches: function (matches) {
      var self = this;

      // First, apply firm exclusions
      var firmFiltered = this.getFirmFilteredMatches(matches);

      // Then apply the current filter
      if (this.currentFilter === "best") {
        return firmFiltered.filter(function (m) {
          return !m.intro_requested;
        });
      } else if (this.currentFilter === "closing") {
        return firmFiltered.filter(function (m) {
          return self.isClosingSoonMatch(m);
        });
      } else if (this.currentFilter === "newest") {
        return firmFiltered; // Return all (firm-filtered), sorting handled separately
      }
      return firmFiltered;
    },

    getFirmFilteredMatches: function (matches) {
      var self = this;
      return (matches || []).filter(function (m) {
        if (m.has_recruiter && m.recruiter_firm) {
          return self.excludedFirms.indexOf(m.recruiter_firm) === -1;
        }
        return true;
      });
    },

    isClosingSoonMatch: function (match) {
      var now = new Date();
      var closingDate = this.parseMatchDate(match.closing_date || match.expires_at);

      if (closingDate) {
        closingDate.setHours(23, 59, 59, 999);
        var sevenDaysFromNow = new Date(now.getTime() + 7 * 24 * 60 * 60 * 1000);
        return closingDate >= now && closingDate <= sevenDaysFromNow;
      }

      var postedDate = this.parseMatchDate(match.posted_at);
      if (!postedDate) {
        return false;
      }

      var ageMs = now.getTime() - postedDate.getTime();
      var ageDays = ageMs / (24 * 60 * 60 * 1000);
      return ageDays >= 5 && ageDays <= 30 && (match.match_score || 0) >= 60;
    },

    parseMatchDate: function (value) {
      if (!value) {
        return null;
      }

      var normalized = String(value).trim();
      if (!normalized || normalized === "0000-00-00" || normalized === "0000-00-00 00:00:00") {
        return null;
      }

      var parsed = new Date(normalized.replace(" ", "T"));
      return isNaN(parsed.getTime()) ? null : parsed;
    },

    sortMatches: function (matches) {
      var sorted = matches.slice();

      switch (this.currentSort) {
        case "score-desc":
          sorted.sort(function (a, b) {
            return (b.match_score || 0) - (a.match_score || 0);
          });
          break;
        case "date-desc":
          sorted.sort(function (a, b) {
            return new Date(b.posted_at || 0) - new Date(a.posted_at || 0);
          });
          break;
        case "closing-asc":
          var self = this;
          sorted.sort(function (a, b) {
            return self.getClosingSortTime(a) - self.getClosingSortTime(b);
          });
          break;
        case "company":
          sorted.sort(function (a, b) {
            return (a.company || "").localeCompare(b.company || "");
          });
          break;
      }

      return sorted;
    },

    getClosingSortTime: function (match) {
      var closingDate = this.parseMatchDate(match.closing_date || match.expires_at);
      if (closingDate) {
        return closingDate.getTime();
      }

      var postedDate = this.parseMatchDate(match.posted_at);
      return postedDate ? postedDate.getTime() + 5 * 24 * 60 * 60 * 1000 : Number.MAX_SAFE_INTEGER;
    },

    maskEmail: function (email) {
      email = String(email || "").trim();
      if (!email || email.indexOf("@") === -1) {
        return "";
      }

      var parts = email.split("@");
      var local = parts.shift() || "";
      var domain = parts.join("@");
      if (!local || !domain) {
        return "";
      }

      return (
        local.charAt(0) +
        "*****" +
        (local.length > 1 ? local.charAt(local.length - 1) : "") +
        "@" +
        domain
      );
    },

    maskPersonName: function (name) {
      return String(name || "Recruiter")
        .trim()
        .split(/\s+/)
        .filter(Boolean)
        .map(function (part) {
          return part.charAt(0).toUpperCase() + "*****";
        })
        .join(" ");
    },

    notify: function (message, type) {
      var bgColor =
        type === "success"
          ? "#057642"
          : type === "error"
          ? "#dc2626"
          : "#0D353E";
      var $toast = $(
        '<div class="sffc-console-toast" style="position:fixed;top:20px;right:20px;background:' +
          bgColor +
          ';color:#fff;padding:12px 20px;border-radius:10px;box-shadow:0 12px 28px rgba(0,0,0,0.16);z-index:10030;font-size:14px;font-weight:700;">' +
          this.escapeHtml(message || "") +
          "</div>"
      );

      $("body").append($toast);
      setTimeout(function () {
        $toast.fadeOut(280, function () {
          $(this).remove();
        });
      }, 2600);
    },

    getMatchById: function (matchId) {
      return this.allMatches.find(function (item) {
        return String(item.id || "") === String(matchId || "");
      });
    },

    normalizeStringList: function (value) {
      var items = [];
      var seen = {};

      function pushItem(item) {
        var text = String(item || "").trim();
        var key = text.toLowerCase();
        if (!text || seen[key]) {
          return;
        }
        seen[key] = true;
        items.push(text);
      }

      function consume(input) {
        if (!input) {
          return;
        }
        if (Array.isArray(input)) {
          input.forEach(consume);
          return;
        }
        if (typeof input === "string") {
          var text = input.trim();
          if (!text) {
            return;
          }
          if ((text.charAt(0) === "[" && text.charAt(text.length - 1) === "]") || (text.charAt(0) === "{" && text.charAt(text.length - 1) === "}")) {
            try {
              consume(JSON.parse(text));
              return;
            } catch (err) {}
          }
          text.split(/[\n,|]+/).forEach(pushItem);
          return;
        }
        pushItem(input);
      }

      consume(value);
      return items;
    },

    getMatchKeywords: function (match) {
      return this.normalizeStringList(
        [].concat(match && match.keywords ? match.keywords : [])
          .concat(match && match.skills_mentioned ? match.skills_mentioned : [])
      );
    },

    getYearsRequiredInfo: function (match) {
      var fallbackRanges = {
        intern: { min: 0, max: 1, label: "0-1 years" },
        graduate: { min: 0, max: 1, label: "0-1 years" },
        junior: { min: 1, max: 2, label: "1-2 years" },
        analyst: { min: 1, max: 3, label: "1-3 years" },
        associate: { min: 3, max: 5, label: "3-5 years" },
        senior: { min: 5, max: 7, label: "5-7 years" },
        manager: { min: 5, max: 8, label: "5-8 years" },
        vp: { min: 6, max: 9, label: "6-9 years" },
        director: { min: 8, max: 12, label: "8-12 years" },
        executive: { min: 10, max: 15, label: "10+ years" },
      };
      var sourceText = [
        match && match.role_title,
        match && match.content_snippet,
        match && match.content,
      ]
        .filter(Boolean)
        .join(" ");
      var rangeMatch = sourceText.match(/(\d+)\s*(?:-|to)\s*(\d+)\s+years?/i);
      if (rangeMatch) {
        return {
          min: parseInt(rangeMatch[1], 10) || 0,
          max: parseInt(rangeMatch[2], 10) || 0,
          label: rangeMatch[1] + "-" + rangeMatch[2] + " years",
        };
      }

      var plusMatch = sourceText.match(/(\d+)\+?\s+years?/i);
      if (plusMatch) {
        var years = parseInt(plusMatch[1], 10) || 0;
        return {
          min: years,
          max: years,
          label: years + "+ years",
        };
      }

      var detectedYears = parseInt(match && match.detected_years, 10) || 0;
      if (detectedYears > 0) {
        return {
          min: detectedYears,
          max: detectedYears,
          label: detectedYears + "+ years",
        };
      }

      var seniorityText = String(match && match.seniority ? match.seniority : match && match.role_title ? match.role_title : "")
        .toLowerCase();

      if (/director|head|partner|managing director/.test(seniorityText)) {
        return fallbackRanges.director;
      }
      if (/vice president|\bvp\b|executive director/.test(seniorityText)) {
        return fallbackRanges.vp;
      }
      if (/manager/.test(seniorityText)) {
        return fallbackRanges.manager;
      }
      if (/senior/.test(seniorityText)) {
        return fallbackRanges.senior;
      }
      if (/associate/.test(seniorityText)) {
        return fallbackRanges.associate;
      }
      if (/analyst/.test(seniorityText)) {
        return fallbackRanges.analyst;
      }
      if (/graduate|intern/.test(seniorityText)) {
        return fallbackRanges.graduate;
      }

      return {
        min: 0,
        max: 0,
        label: "Experience not specified",
      };
    },

    getExplicitSalaryScore: function (match) {
      var min = parseInt(match && match.salary_min, 10) || 0;
      var max = parseInt(match && match.salary_max, 10) || 0;
      var currency = String(match && match.salary_currency ? match.salary_currency : "GBP")
        .toUpperCase()
        .trim();
      if (!min && !max) {
        return null;
      }

      var midpoint = max ? (min + max) / 2 : min;
      var thresholds = {
        AED: { fair: 300000, high: 650000 },
        GBP: { fair: 70000, high: 140000 },
        USD: { fair: 95000, high: 200000 },
        EUR: { fair: 65000, high: 150000 },
        CHF: { fair: 100000, high: 220000 },
        SAR: { fair: 260000, high: 560000 },
        QAR: { fair: 240000, high: 520000 },
      };
      var band = thresholds[currency] || thresholds.GBP;

      if (midpoint >= band.high) {
        return {
          score: 86,
          label: "Likely High Salary",
          className: "salary--high",
          reason: "Compensation band is materially above market midpoint",
        };
      }
      if (midpoint >= band.fair) {
        return {
          score: 58,
          label: "Fair Salary",
          className: "salary--fair",
          reason: "Compensation band sits in the core market range",
        };
      }
      return {
        score: 26,
        label: "Entry Level Salary",
        className: "salary--entry",
        reason: "Compensation band tracks an early-career range",
      };
    },

    getSalaryInsight: function (match) {
      var yearsInfo = this.getYearsRequiredInfo(match);
      var yearsMidpoint = yearsInfo.max
        ? (yearsInfo.min + yearsInfo.max) / 2
        : yearsInfo.min;
      var roleText = String(
        [
          match && match.role_title,
          match && match.seniority,
          match && match.content_snippet,
        ]
          .filter(Boolean)
          .join(" ")
      ).toLowerCase();
      var keywords = this.getMatchKeywords(match).map(function (item) {
        return item.toLowerCase();
      });
      var explicit = this.getExplicitSalaryScore(match);
      var score = explicit ? explicit.score : 10;
      var reasons = [];

      if (explicit && explicit.reason) {
        reasons.push(explicit.reason);
      }

      if (/intern|graduate|junior|assistant/.test(roleText)) {
        score -= 18;
        reasons.push("Role is positioned at the junior end of the market");
      }
      if (/analyst/.test(roleText)) {
        score += 8;
      }
      if (/associate|senior analyst|senior associate/.test(roleText)) {
        score += 16;
        reasons.push("Mid-level seat with more pricing power");
      }
      if (/vice president|\bvp\b|director|head|portfolio manager|fund manager/.test(roleText)) {
        score += 28;
        reasons.push("Leadership or revenue-owning title typically pays more");
      }
      if (/fixed income|credit|private equity|leveraged finance|structured credit|m&a|investment banking|quant|trader|capital markets/.test(roleText)) {
        score += 12;
        reasons.push("Specialist finance desk or product expertise increases pay");
      }

      if (yearsMidpoint >= 8) {
        score += 26;
      } else if (yearsMidpoint >= 5) {
        score += 18;
      } else if (yearsMidpoint >= 3) {
        score += 10;
      } else if (yearsMidpoint <= 1 && yearsInfo.label !== "Experience not specified") {
        score -= 8;
      }

      if (yearsInfo.label !== "Experience not specified") {
        reasons.push(yearsInfo.label + " experience requirement");
      }

      if (keywords.some(function (keyword) { return /cfa|caia|frm|chartered financial analyst/.test(keyword); })) {
        score += 16;
        reasons.push("Premium credential signal like CFA or equivalent");
      }
      if (keywords.some(function (keyword) { return /mba|master|msc|acca|aca|cpa/.test(keyword); })) {
        score += 10;
      }
      if (keywords.some(function (keyword) { return /\bbsc\b|bachelor/.test(keyword); })) {
        score -= 2;
      }
      if (keywords.some(function (keyword) { return /python|modelling|valuation|structuring|origination/.test(keyword); })) {
        score += 6;
      }

      score = Math.max(0, Math.min(100, score));

      if (score >= 78) {
        return {
          label: "Likely High Salary",
          className: "salary--high",
          score: score,
          reason: reasons.slice(0, 2).join(" · ") || "Role and requirement mix point to a premium pay band",
        };
      }
      if (score >= 45) {
        return {
          label: "Fair Salary",
          className: "salary--fair",
          score: score,
          reason: reasons.slice(0, 2).join(" · ") || "Role looks in line with a standard market salary band",
        };
      }
      return {
        label: "Entry Level Salary",
        className: "salary--entry",
        score: score,
        reason: reasons.slice(0, 2).join(" · ") || "Role reads closer to an early-career compensation band",
      };
    },

    formatSalaryDisplay: function (match) {
      if (match && match.salary_text) {
        return String(match.salary_text).trim();
      }
      var min = parseInt(match && match.salary_min, 10) || 0;
      var max = parseInt(match && match.salary_max, 10) || 0;
      var currency = String(match && match.salary_currency ? match.salary_currency : "").trim();
      if (!min && !max) {
        return "Not disclosed";
      }
      if (min && max) {
        return (currency ? currency + " " : "") + min.toLocaleString() + " - " + max.toLocaleString();
      }
      return (currency ? currency + " " : "") + (min || max).toLocaleString() + "+";
    },

    toggleCompareSelection: function (matchId, forcedState) {
      var id = String(matchId || "");
      if (!id) {
        return false;
      }

      var next = this.compareSelectedIds.slice();
      var currentIndex = next.indexOf(id);
      var shouldSelect =
        typeof forcedState === "boolean" ? forcedState : currentIndex === -1;

      if (shouldSelect && currentIndex === -1) {
        if (next.length >= this.compareMaxSelections) {
          this.notify(
            "You can compare up to " + this.compareMaxSelections + " roles at a time.",
            "error"
          );
          return false;
        }
        next.push(id);
      }

      if (!shouldSelect && currentIndex !== -1) {
        next.splice(currentIndex, 1);
      }

      this.compareSelectedIds = next;
      this.syncCompareSelectionUI();
      return true;
    },

    syncCompareSelectionUI: function () {
      var selectedCount = this.compareSelectedIds.length;
      var self = this;

      this.$engine.find(".sffc-console-match-card").each(function () {
        var $card = $(this);
        var matchId = String($card.data("match-id") || "");
        var isSelected = self.compareSelectedIds.indexOf(matchId) !== -1;

        $card.toggleClass("is-compare-selected", isSelected);
        $card
          .find("[data-compare-toggle]")
          .prop("checked", isSelected)
          .attr("aria-checked", isSelected ? "true" : "false");
        $card
          .find("[data-compare-count]")
          .text(selectedCount + "/" + self.compareMaxSelections + " selected");
      });
    },

    getSelectedCompareMatches: function () {
      var self = this;
      return this.compareSelectedIds
        .map(function (id) {
          return self.getMatchById(id);
        })
        .filter(Boolean);
    },

    formatComparisonValue: function (match, key) {
      if (key === "salary_insight") {
        return this.getSalaryInsight(match).label;
      }
      if (key === "salary_range") {
        return this.formatSalaryDisplay(match);
      }
      if (key === "years_required") {
        return this.getYearsRequiredInfo(match).label;
      }
      if (key === "keywords") {
        var items = this.getMatchKeywords(match);
        return items.length ? items.slice(0, 6) : ["Not specified"];
      }
      if (key === "recruiter") {
        return [match.recruiter_name, match.recruiter_title].filter(Boolean).join(" · ") || "Not specified";
      }
      if (key === "summary") {
        var summaryText = String(match.content_snippet || match.content || "Not provided")
          .replace(/\s+/g, " ")
          .trim();
        return summaryText.length > 220
          ? summaryText.slice(0, 217) + "..."
          : summaryText;
      }
      if (key === "posted_at") {
        return match.posted_at ? this.formatTimeAgo(match.posted_at) : "Not specified";
      }
      if (key === "closing_date") {
        if (!match.closing_date) {
          return "Not specified";
        }
        return match.closing_date;
      }
      return String(match && match[key] ? match[key] : "").trim() || "Not specified";
    },

    comparisonValuesAlign: function (values, key) {
      var normalized = values.map(function (value) {
        if (Array.isArray(value)) {
          return value
            .map(function (item) {
              return String(item || "").toLowerCase().trim();
            })
            .filter(Boolean);
        }
        return String(value || "").toLowerCase().trim();
      });

      if (key === "keywords") {
        var shared = normalized[0] || [];
        for (var i = 1; i < normalized.length; i++) {
          shared = shared.filter(function (item) {
            return normalized[i].indexOf(item) !== -1;
          });
        }
        return shared.length > 0;
      }

      if (key === "years_required") {
        var parsed = values.map(function (value) {
          var match = String(value || "").match(/(\d+)/);
          return match ? parseInt(match[1], 10) : null;
        }).filter(function (value) {
          return value !== null;
        });
        if (parsed.length < 2) {
          return false;
        }
        return Math.max.apply(null, parsed) - Math.min.apply(null, parsed) <= 2;
      }

      if (key === "salary_insight") {
        return normalized.every(function (value) {
          return value === normalized[0];
        });
      }

      return normalized.every(function (value) {
        return value === normalized[0];
      });
    },

    buildComparisonRows: function (matches) {
      var self = this;
      var fields = [
        { key: "salary_insight", label: "Salary signal" },
        { key: "salary_range", label: "Salary range" },
        { key: "seniority", label: "Seniority" },
        { key: "years_required", label: "Years experience required" },
        { key: "location", label: "Location" },
        { key: "sector", label: "Sector" },
        { key: "keywords", label: "Keywords" },
        { key: "recruiter", label: "Recruiter" },
        { key: "recruiter_firm", label: "Recruiter firm" },
        { key: "closing_date", label: "Closing date" },
        { key: "summary", label: "Role summary" },
      ];

      return fields
        .map(function (field) {
          var values = matches.map(function (match) {
            return self.formatComparisonValue(match, field.key);
          });
          var hasValue = values.some(function (value) {
            return Array.isArray(value)
              ? value.length && value[0] !== "Not specified"
              : value && value !== "Not specified";
          });
          if (!hasValue) {
            return null;
          }
          return {
            key: field.key,
            label: field.label,
            values: values,
            aligned: self.comparisonValuesAlign(values, field.key),
          };
        })
        .filter(Boolean);
    },

    buildSharedInsights: function (rows) {
      return rows
        .filter(function (row) {
          return row.aligned;
        })
        .slice(0, 5)
        .map(function (row) {
          if (row.key === "keywords" && Array.isArray(row.values[0])) {
            var shared = row.values[0].slice();
            for (var index = 1; index < row.values.length; index++) {
              shared = shared.filter(function (item) {
                return row.values[index].indexOf(item) !== -1;
              });
            }
            return row.label + ": " + shared.slice(0, 3).join(", ");
          }
          return row.label + ": " + (Array.isArray(row.values[0]) ? row.values[0].join(", ") : row.values[0]);
        });
    },

    buildDifferenceInsights: function (rows) {
      return rows
        .filter(function (row) {
          return !row.aligned;
        })
        .slice(0, 6)
        .map(function (row) {
          return row.label + " varies across the selected roles.";
        });
    },

    buildRoleProsCons: function (match) {
      var salaryInsight = this.getSalaryInsight(match);
      var yearsInfo = this.getYearsRequiredInfo(match);
      var keywords = this.getMatchKeywords(match);
      var pros = [];
      var cons = [];
      var yearsFloor = yearsInfo.min || 0;

      if (salaryInsight.className === "salary--high") {
        pros.push("Compensation profile looks premium for this market.");
      } else if (salaryInsight.className === "salary--fair") {
        pros.push("Compensation profile appears balanced for the role level.");
      }

      if ((match.match_score || 0) >= 85) {
        pros.push("Strong profile fit based on the current MENA Careers match score.");
      }
      if (match.salary_text || match.salary_min || match.salary_max) {
        pros.push("Salary information is disclosed, which reduces compensation ambiguity.");
      }
      if (match.has_recruiter) {
        pros.push("Direct recruiter context can improve application conversion.");
      }
      if (yearsFloor > 0 && yearsFloor <= 3) {
        pros.push("Experience bar is still accessible if you are building mid-level momentum.");
      }

      if (!match.salary_text && !match.salary_min && !match.salary_max) {
        cons.push("Compensation is not disclosed, so salary certainty is lower.");
      }
      if (yearsFloor >= 6) {
        cons.push("Experience requirement is materially higher, so competition may be stronger.");
      }
      if (keywords.length >= 6) {
        cons.push("The role asks for a broader specialist skill stack.");
      }
      if (salaryInsight.className === "salary--entry") {
        cons.push("Pay profile looks closer to an entry-level or lower-mid band.");
      }

      if (!pros.length) {
        pros.push("Role aligns on core title, location, and market fit signals.");
      }
      if (!cons.length) {
        cons.push("No major downside stands out from the current CRM post fields.");
      }

      return {
        pros: pros.slice(0, 3),
        cons: cons.slice(0, 3),
      };
    },

    closeCompareModal: function () {
      $("[data-console-compare-modal]").remove();
      $("body").removeClass("sffc-console-compare-modal-open");
    },

    openCompareModal: function (triggerMatchId) {
      if (!window.sffcCRMLinkedIn?.isLoggedIn) {
        window.location.href = window.sffcCRMLinkedIn?.joinUrl || "/join/";
        return;
      }

      if (triggerMatchId && this.compareSelectedIds.indexOf(String(triggerMatchId)) === -1) {
        if (!this.toggleCompareSelection(triggerMatchId, true)) {
          return;
        }
      }

      var matches = this.getSelectedCompareMatches();
      if (matches.length < 2) {
        this.notify("Select at least 2 roles to compare.", "error");
        return;
      }

      this.closeCompareModal();

      var rows = this.buildComparisonRows(matches);
      var similarities = this.buildSharedInsights(rows);
      var differences = this.buildDifferenceInsights(rows);
      var self = this;

      var modalHtml =
        '<div class="sffc-console-compare-modal" data-console-compare-modal>' +
        '<div class="sffc-console-compare-modal__overlay" data-console-compare-close></div>' +
        '<div class="sffc-console-compare-modal__panel" role="dialog" aria-modal="true" aria-labelledby="sffc-console-compare-title">' +
        '<div class="sffc-console-compare-modal__header">' +
        '<div class="sffc-console-compare-modal__header-copy">' +
        '<p class="sffc-console-compare-modal__eyebrow">Role comparison</p>' +
        '<h2 id="sffc-console-compare-title">Compare selected opportunities</h2>' +
        '<p class="sffc-console-compare-modal__subcopy">See where compensation, seniority, recruiter access, and skill requirements align or diverge.</p>' +
        '</div>' +
        '<button type="button" class="sffc-console-compare-modal__close" data-console-compare-close aria-label="Close comparison">×</button>' +
        '</div>' +
        '<div class="sffc-console-compare-modal__body">' +
        '<section class="sffc-console-compare-hero-grid">' +
        matches
          .map(function (match) {
            var salaryInsight = self.getSalaryInsight(match);
            var yearsInfo = self.getYearsRequiredInfo(match);
            return (
              '<article class="sffc-console-compare-role-card">' +
              '<div class="sffc-console-compare-role-card__top">' +
              '<span class="sffc-console-compare-role-card__salary ' +
              salaryInsight.className +
              '">' +
              self.escapeHtml(salaryInsight.label) +
              "</span>" +
              '<label class="sffc-console-compare-role-card__check">' +
              '<input type="checkbox" data-compare-toggle data-match-id="' +
              self.escapeHtml(match.id) +
              '" checked>' +
              "<span>Marked</span>" +
              "</label>" +
              "</div>" +
              '<h3 class="sffc-console-compare-role-card__title">' +
              self.escapeHtml(match.role_title || "Opportunity") +
              "</h3>" +
              '<p class="sffc-console-compare-role-card__meta">' +
              self.escapeHtml([match.company, match.location].filter(Boolean).join(" · ") || "Location pending") +
              "</p>" +
              '<div class="sffc-console-compare-role-card__stats">' +
              '<span>' +
              self.escapeHtml(self.formatSalaryDisplay(match)) +
              "</span>" +
              '<span>' +
              self.escapeHtml(yearsInfo.label) +
              "</span>" +
              "</div>" +
              "</article>"
            );
          })
          .join("") +
        "</section>" +
        '<section class="sffc-console-compare-insights">' +
        '<article class="sffc-console-compare-insights__card">' +
        '<h3>Shared signals</h3>' +
        '<ul>' +
        (similarities.length
          ? similarities
              .map(function (item) {
                return "<li>" + self.escapeHtml(item) + "</li>";
              })
              .join("")
          : "<li>These roles are differentiated more by pay, scope, or requirements than by a shared pattern.</li>") +
        "</ul>" +
        "</article>" +
        '<article class="sffc-console-compare-insights__card is-different">' +
        '<h3>Key differences</h3>' +
        '<ul>' +
        (differences.length
          ? differences
              .map(function (item) {
                return "<li>" + self.escapeHtml(item) + "</li>";
              })
              .join("")
          : "<li>The selected roles are closely aligned across the current CRM fields.</li>") +
        "</ul>" +
        "</article>" +
        "</section>" +
        '<section class="sffc-console-compare-proscons">' +
        matches
          .map(function (match) {
            var roleNotes = self.buildRoleProsCons(match);
            return (
              '<article class="sffc-console-compare-notes-card">' +
              '<h3>' +
              self.escapeHtml(match.role_title || "Opportunity") +
              "</h3>" +
              '<div class="sffc-console-compare-notes-card__cols">' +
              '<div><h4>Pros</h4><ul>' +
              roleNotes.pros
                .map(function (item) {
                  return "<li>" + self.escapeHtml(item) + "</li>";
                })
                .join("") +
              "</ul></div>" +
              '<div><h4>Cons</h4><ul>' +
              roleNotes.cons
                .map(function (item) {
                  return "<li>" + self.escapeHtml(item) + "</li>";
                })
                .join("") +
              "</ul></div>" +
              "</div>" +
              "</article>"
            );
          })
          .join("") +
        "</section>" +
        '<section class="sffc-console-compare-table">' +
        '<div class="sffc-console-compare-table__head">' +
        '<h3>Field-by-field comparison</h3>' +
        '<p>Comparison is based on the CRM post fields currently loaded into the console results.</p>' +
        '</div>' +
        rows
          .map(function (row) {
            return (
              '<div class="sffc-console-compare-row ' +
              (row.aligned ? "is-aligned" : "is-different") +
              '">' +
              '<div class="sffc-console-compare-row__head">' +
              '<span class="sffc-console-compare-row__label">' +
              self.escapeHtml(row.label) +
              "</span>" +
              '<span class="sffc-console-compare-row__status">' +
              (row.aligned ? "Aligned" : "Different") +
              "</span>" +
              "</div>" +
              '<div class="sffc-console-compare-row__values">' +
              row.values
                .map(function (value, index) {
                  var renderedValue = Array.isArray(value)
                    ? value
                        .map(function (item) {
                          return '<span class="sffc-console-compare-chip">' + self.escapeHtml(item) + "</span>";
                        })
                        .join("")
                    : '<p>' + self.escapeHtml(value) + "</p>";
                  return (
                    '<article class="sffc-console-compare-cell">' +
                    '<span class="sffc-console-compare-cell__role">' +
                    self.escapeHtml(matches[index].role_title || matches[index].company || "Role") +
                    "</span>" +
                    '<div class="sffc-console-compare-cell__value">' +
                    renderedValue +
                    "</div>" +
                    "</article>"
                  );
                })
                .join("") +
              "</div>" +
              "</div>"
            );
          })
          .join("") +
        "</section>" +
        "</div>" +
        "</div>" +
        "</div>";

      $("body").append($(modalHtml));
      $("body").addClass("sffc-console-compare-modal-open");
      this.syncCompareSelectionUI();
    },

    openAddToListModal: function (match, $sourceButton) {
      var self = this;
      var $existing = $("[data-console-list-modal]");
      if ($existing.length) {
        $existing.remove();
      }

      var modalHtml =
        '<div class="sffc-console-list-modal" data-console-list-modal>' +
        '<div class="sffc-console-list-modal__overlay" data-console-list-close></div>' +
        '<div class="sffc-console-list-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="sffc-console-list-title">' +
        '<button type="button" class="sffc-console-list-modal__close" data-console-list-close aria-label="Close">&times;</button>' +
        '<p class="sffc-console-list-modal__eyebrow">Outreach lists</p>' +
        '<h3 id="sffc-console-list-title">Add to List</h3>' +
        '<p class="sffc-console-list-modal__copy">Use lists to save contacts and manage your data in one place for seamless follow-up and smarter outreach, all with one click!</p>' +
        '<label class="sffc-console-list-modal__field">' +
        '<span>Choose list</span>' +
        '<select data-console-list-select><option value="">Loading lists...</option></select>' +
        "</label>" +
        '<label class="sffc-console-list-modal__field">' +
        '<span>Or create a new list</span>' +
        '<input type="text" data-console-list-name placeholder="e.g. Dubai PE recruiters">' +
        "</label>" +
        '<p class="sffc-console-list-modal__feedback" data-console-list-feedback hidden></p>' +
        '<div class="sffc-console-list-modal__actions">' +
        '<button type="button" class="sffc-console-match-btn" data-console-list-close>Cancel</button>' +
        '<button type="button" class="sffc-console-match-btn sffc-console-match-btn--list" data-console-list-save>Save to List</button>' +
        "</div>" +
        "</div>" +
        "</div>";

      var $modal = $(modalHtml);
      $("body").append($modal);

      function closeModal() {
        $modal.remove();
      }

      $modal.on("click", "[data-console-list-close]", function (e) {
        e.preventDefault();
        closeModal();
      });

      $.post(window.sffcCRMLinkedIn?.ajaxUrl || "/wp-admin/admin-ajax.php", {
        action: "sffc_crm_get_console_lists",
        nonce: window.sffcCRMLinkedIn?.nonce || "",
      })
        .done(function (response) {
          var lists = response?.data?.lists || [];
          var $select = $modal.find("[data-console-list-select]");
          if (!response || !response.success || !lists.length) {
            $select.html('<option value="">Create a new list below</option>');
            return;
          }
          $select.html(
            '<option value="">Choose a list</option>' +
              lists
                .map(function (list) {
                  return (
                    '<option value="' +
                    self.escapeHtml(list.id) +
                    '">' +
                    self.escapeHtml(list.list_name) +
                    " (" +
                    self.escapeHtml(list.item_count || 0) +
                    ")</option>"
                  );
                })
                .join("")
          );
        })
        .fail(function () {
          $modal
            .find("[data-console-list-select]")
            .html('<option value="">Create a new list below</option>');
        });

      $modal.on("click", "[data-console-list-save]", function (e) {
        e.preventDefault();

        var $save = $(this);
        var $feedback = $modal.find("[data-console-list-feedback]");
        var listId = $modal.find("[data-console-list-select]").val();
        var newListName = $modal.find("[data-console-list-name]").val().trim();

        if (!listId && !newListName) {
          $feedback
            .prop("hidden", false)
            .addClass("is-error")
            .text("Choose a list or create a new one.");
          return;
        }

        $save.prop("disabled", true).addClass("is-loading");
        $feedback.prop("hidden", true).removeClass("is-error").text("");

        $.post(window.sffcCRMLinkedIn?.ajaxUrl || "/wp-admin/admin-ajax.php", {
          action: "sffc_crm_add_console_list_item",
          nonce: window.sffcCRMLinkedIn?.nonce || "",
          list_id: listId,
          new_list_name: newListName,
          match: match,
        })
          .done(function (response) {
            if (!response || !response.success) {
              $feedback
                .prop("hidden", false)
                .addClass("is-error")
                .text(response?.data?.message || "Unable to add this entry.");
              return;
            }

            match.added_to_list = true;
            if ($sourceButton && $sourceButton.length) {
              if ($sourceButton.hasClass("sffc-console-compare-save")) {
                $sourceButton
                  .addClass("is-saved")
                  .attr("aria-pressed", "true")
                  .attr("title", "Saved to your list");
              } else {
                $sourceButton
                  .addClass("is-added")
                  .prop("disabled", true)
                  .html(
                    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg><span>Added</span>'
                  );
              }
            }
            closeModal();
          })
          .fail(function (xhr) {
            if (xhr.status === 401) {
              window.location.href =
                window.sffcCRMLinkedIn?.loginUrl || "/wp-login.php";
              return;
            }
            $feedback
              .prop("hidden", false)
              .addClass("is-error")
              .text("Unable to add this entry right now.");
          })
          .always(function () {
            $save.prop("disabled", false).removeClass("is-loading");
          });
      });
    },

    submitCriteriaToAdmin: function ($btn) {
      var self = this;
      var $form = $btn.closest(".sffc-console-notify-form");
      var $notes = $form.find(".sffc-console-notify-notes");
      var $feedback = $form.find(".sffc-console-notify-feedback");
      var additionalNotes = $notes.val().trim();

      // Disable button and show loading state
      $btn.addClass("is-loading").prop("disabled", true).text("Submitting...");
      $feedback.hide();

      // Prepare criteria data
      var criteriaData = {
        action: "sffc_crm_submit_criteria_request",
        nonce: config.nonce,
        role: this.selectedCriteria?.job_title || "",
        location: Array.isArray(this.selectedCriteria?.location)
          ? this.selectedCriteria.location.join(", ")
          : this.selectedCriteria?.location || "",
        seniority: Array.isArray(this.selectedCriteria?.experience_level)
          ? this.selectedCriteria.experience_level.join(", ")
          : this.selectedCriteria?.experience_level || "",
        additional_notes: additionalNotes,
      };

      $.post(config.ajaxUrl || "/wp-admin/admin-ajax.php", criteriaData)
        .done(function (response) {
          if (response.success) {
            $feedback
              .removeClass("error")
              .addClass("success")
              .html(
                "✓ Got it! We'll notify you when we find matching opportunities in the next 24-48hrs."
              )
              .show();
            $notes.val(""); // Clear the notes
            $btn.text("Submitted").prop("disabled", true);

            // Hide form after 3 seconds
            setTimeout(function () {
              $form.fadeOut();
            }, 3000);
          } else {
            $feedback
              .removeClass("success")
              .addClass("error")
              .text(response.data || "Failed to submit request. Please try again.")
              .show();
            $btn
              .removeClass("is-loading")
              .prop("disabled", false)
              .text("Notify Me");
          }
        })
        .fail(function () {
          $feedback
            .removeClass("success")
            .addClass("error")
            .text("Network error. Please try again.")
            .show();
          $btn
            .removeClass("is-loading")
            .prop("disabled", false)
            .text("Notify Me");
        });
    },

    renderMatchCard: function (match, index) {
      var self = this;
      var score = match.match_score || 75;
      var isCompareSelected =
        this.compareSelectedIds.indexOf(String(match.id || "")) !== -1;
      var salaryInsight = this.getSalaryInsight(match);
      var yearsInfo = this.getYearsRequiredInfo(match);
      // Use response_badge first, then fall back to match.insight, then auto-generate
      var insight =
        match.response_badge || match.insight || this.getInsight(score);
      var insightClass = this.getInsightClass(score);
      var strokeColor = this.getScoreColor(score);
      var isRecruiterView = match.has_recruiter;
      var isLoggedOut = !window.sffcCRMLinkedIn?.isLoggedIn;
      var displayRecruiterName = isLoggedOut
        ? this.maskPersonName(match.recruiter_name)
        : match.recruiter_name || "Recruiter";
      var displayRecruiterTitle = isLoggedOut
        ? "Recruiter"
        : match.recruiter_title || "";

      // ALWAYS show recruiter avatar (recruiter-focused matching)
      var avatarHtml = "";
      if (match.has_recruiter && match.recruiter_photo) {
        avatarHtml =
          '<img src="' +
          this.escapeHtml(match.recruiter_photo) +
          '" alt="' +
          this.escapeHtml(displayRecruiterName || "") +
          '">';
      } else if (match.has_recruiter) {
        var recruiterInitial = (match.recruiter_name || "R")
          .charAt(0)
          .toUpperCase();
        avatarHtml =
          '<span class="sffc-console-match-initial sffc-console-match-initial--recruiter">' +
          recruiterInitial +
          "</span>";
      } else {
        // Fallback to company logo if no recruiter
        if (match.company_logo) {
          avatarHtml =
            '<img src="' +
            this.escapeHtml(match.company_logo) +
            '" alt="' +
            this.escapeHtml(match.company || "") +
            '">';
        } else {
          var companyInitial = (match.company || match.role_title || "S")
            .charAt(0)
            .toUpperCase();
          avatarHtml =
            '<span class="sffc-console-match-initial">' +
            companyInitial +
            "</span>";
        }
      }

      // Grammatically correct hiring verb based on role title
      var hiringVerb = this.getHiringVerb(match.role_title || "");

      var reasonList = Array.isArray(match.reasons) ? match.reasons : [];
      var keywordsList = Array.isArray(match.keywords) ? match.keywords : [];
      var primaryTagSource = reasonList[0] ? "reason" : keywordsList[0] ? "keyword" : "";
      var primaryTag = reasonList[0] || keywordsList[0] || "";

      // Match reasons/tags
      var reasons = "";
      var secondaryReasons = reasonList.slice(
        primaryTagSource === "reason" ? 1 : 0,
        primaryTagSource === "reason" ? 5 : 4
      );
      if (primaryTag || secondaryReasons.length) {
        reasons = '<div class="sffc-console-match-tags">';
        if (primaryTag) {
          reasons +=
            '<span class="sffc-console-match-tag">' +
            self.escapeHtml(primaryTag) +
            "</span>";
        }
        secondaryReasons.forEach(function (reason) {
          reasons +=
            '<span class="sffc-console-match-tag">' +
            self.escapeHtml(reason) +
            "</span>";
        });
        reasons += "</div>";
      }

      // Keywords (shown below match tags, limit to 5)
      var keywords = "";
      var visibleKeywords = keywordsList.slice(
        primaryTagSource === "keyword" ? 1 : 0,
        primaryTagSource === "keyword" ? 6 : 5
      );
      if (visibleKeywords.length) {
        keywords = '<div class="sffc-console-match-keywords">';
        visibleKeywords.forEach(function (keyword) {
          keywords +=
            '<span class="sffc-console-match-keyword">' +
            self.escapeHtml(keyword) +
            "</span>";
        });
        keywords += "</div>";
      }

      // Jobseeker notes (shown below match tags)
      var jobseekerNotes = "";
      if (match.jobseeker_notes) {
        jobseekerNotes =
          '<div class="sffc-console-match-notes">' +
          '<div class="sffc-console-match-notes-icon">' +
          '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
          '<circle cx="12" cy="12" r="10"/>' +
          '<line x1="12" y1="16" x2="12" y2="12"/>' +
          '<line x1="12" y1="8" x2="12.01" y2="8"/>' +
          "</svg>" +
          "</div>" +
          '<div class="sffc-console-match-notes-text">' +
          this.escapeHtml(match.jobseeker_notes) +
          "</div>" +
          "</div>";
      }

      // Meta info - ALWAYS show recruiter info (recruiter-focused)
      var meta = "";
      if (match.has_recruiter) {
        // Show recruiter name, title, firm
        meta =
          '<span class="sffc-console-match-recruiter-name">' +
          this.escapeHtml(displayRecruiterName) +
          "</span>";
        if (displayRecruiterTitle) {
          meta +=
            ' <span class="sffc-console-match-sep">•</span> ' +
            this.escapeHtml(displayRecruiterTitle);
        }
        if (match.recruiter_firm) {
          meta +=
            ' <span class="sffc-console-match-sep">•</span> ' +
            this.escapeHtml(match.recruiter_firm);
        }
      } else {
        // Fallback: company and location if no recruiter
        meta = this.escapeHtml(match.company || "");
        if (match.location) {
          meta +=
            ' <span class="sffc-console-match-sep">•</span> ' +
            this.escapeHtml(match.location);
        }
      }

      if (!isLoggedOut && match.posted_at) {
        var postedDate = this.formatTimeAgo(match.posted_at);
        meta += ' <span class="sffc-console-match-sep">•</span> ' + postedDate;
      }

      // LinkedIn link for recruiter view
      var linkedinLink = "";
      if (!isLoggedOut && isRecruiterView && match.recruiter_linkedin) {
        linkedinLink =
          '<a href="' +
          this.escapeHtml(match.recruiter_linkedin) +
          '" target="_blank" rel="noopener" class="sffc-console-match-linkedin">' +
          '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">' +
          '<path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/>' +
          "</svg>" +
          "</a>";
      }

      var saveButtonClass =
        "sffc-console-compare-save" + (match.added_to_list ? " is-saved" : "");
      var compareSection =
        '<aside class="sffc-console-match-compare" aria-label="Compare role">' +
        '<div class="sffc-console-match-compare__top">' +
        '<div class="sffc-console-match-compare__salary">' +
        '<span class="sffc-console-match-compare__salary-badge ' +
        salaryInsight.className +
        '">' +
        this.escapeHtml(salaryInsight.label) +
        "</span>" +
        '<span class="sffc-console-match-compare__salary-copy">' +
        this.escapeHtml(salaryInsight.reason) +
        "</span>" +
        "</div>" +
        '<button type="button" class="' +
        saveButtonClass +
        '" data-match-id="' +
        (match.id || "") +
        '" aria-label="Save role to list" aria-pressed="' +
        (match.added_to_list ? "true" : "false") +
        '" title="' +
        (match.added_to_list ? "Saved to your list" : "Save role to list") +
        '">' +
        '<svg viewBox="0 0 24 24" fill="' +
        (match.added_to_list ? "currentColor" : "none") +
        '" stroke="currentColor" stroke-width="2" aria-hidden="true">' +
        '<path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54z"/>' +
        "</svg>" +
        "</button>" +
        "</div>" +
        '<button type="button" class="sffc-console-compare-btn" data-match-id="' +
        (match.id || "") +
        '">' +
        '<span>Compare</span>' +
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>' +
        "</button>" +
        '<label class="sffc-console-compare-check">' +
        '<input type="checkbox" data-compare-toggle data-match-id="' +
        (match.id || "") +
        '" ' +
        (isCompareSelected ? "checked" : "") +
        ">" +
        '<span class="sffc-console-compare-check__label">Mark to compare</span>' +
        '<span class="sffc-console-compare-check__count" data-compare-count>' +
        this.compareSelectedIds.length +
        "/" +
        this.compareMaxSelections +
        " selected</span>" +
        "</label>" +
        '<span class="sffc-console-match-compare__years">' +
        this.escapeHtml(yearsInfo.label) +
        "</span>" +
        "</aside>";

      var scoreBadge =
        '<div class="sffc-console-match-score sffc-console-match-score--avatar">' +
        '<div class="sffc-console-match-score-ring">' +
        '<svg class="sffc-console-match-pie" width="72" height="72" viewBox="0 0 56 56" aria-hidden="true">' +
        '<circle cx="28" cy="28" r="22" fill="none" stroke="#e5e7eb" stroke-width="6"/>' +
        '<circle cx="28" cy="28" r="22" fill="none" stroke="' +
        strokeColor +
        '" stroke-width="6" ' +
        'stroke-dasharray="' +
        score * 1.382 +
        ' 138.2" ' +
        'stroke-dashoffset="0" transform="rotate(-90 28 28)" stroke-linecap="round"/>' +
        "</svg>" +
        '<div class="sffc-console-match-logo sffc-console-match-logo--recruiter">' +
        avatarHtml +
        "</div>" +
        '<span class="sffc-console-match-score-badge">' +
        score +
        "%</span>" +
        "</div>" +
        '<span class="sffc-console-match-insight ' +
        insightClass +
        '">' +
        this.escapeHtml(insight) +
        "</span>" +
        "</div>";

      var animationDelay = Math.min(index * 50, 500);
      var cardClass =
        "sffc-console-match-card sffc-console-match-card--recruiter";
      if (isLoggedOut && match.has_recruiter) {
        cardClass += " is-guest-masked";
        if (index >= 5) {
          cardClass += " is-guest-locked";
        }
      }

      return (
        '<article class="' +
        cardClass +
        '" data-match-id="' +
        (match.id || "") +
        '" style="animation-delay: ' +
        animationDelay +
        'ms">' +
        '<div class="sffc-console-match-main">' +
        scoreBadge +
        '<div class="sffc-console-match-content">' +
        '<h3 class="sffc-console-match-title">' +
        '<span class="sffc-console-match-hiring">' +
        hiringVerb +
        "</span> " +
        '<a href="' +
        this.escapeHtml(isLoggedOut ? "/join/" : "#") +
        '" class="sffc-console-match-open-modal" data-match-id="' +
        (match.id || "") +
        '">' +
        this.escapeHtml(match.role_title || "Opportunity") +
        "</a>" +
        linkedinLink +
        "</h3>" +
        '<div class="sffc-console-match-meta">' +
        meta +
        "</div>" +
        reasons +
        keywords +
        jobseekerNotes +
        "</div>" +
        "</div>" +
        compareSection +
        "</article>"
      );
    },

    // Store selected CV for application
    selectedCvForApplication: null,
    userCVs: [],
    currentModalMatch: null,
    materialsPreviewData: null,
    jobDescriptionExpanded: false,
    applicationCvStorageKey: "sffc_console_selected_cv_id",

    getSavedApplicationCvId: function () {
      try {
        return window.localStorage.getItem(this.applicationCvStorageKey) || "";
      } catch (e) {
        return "";
      }
    },

    saveApplicationCvId: function (cvId) {
      try {
        if (cvId) {
          window.localStorage.setItem(this.applicationCvStorageKey, String(cvId));
        } else {
          window.localStorage.removeItem(this.applicationCvStorageKey);
        }
      } catch (e) {}
    },

    openMatchModal: function (matchId) {
      var self = this;
      var match = this.allMatches.find(function (m) {
        return m.id == matchId;
      });

      if (!match) return;

      self.currentModalMatch = match;
      var recruiterFirstName = (match.recruiter_name || "").split(" ")[0];
      var matchScore = match.match_score || match.score || 85;
      var userAvatar = window.sffcCRMLinkedIn?.currentUserAvatar || "";
      var userInitial = window.sffcCRMLinkedIn?.currentUserInitial || "S";
      var jobDescription =
        match.job_description ||
        match.description ||
        match.content ||
        match.content_snippet ||
        "";
      var postedDate = match.posted_date || match.date_posted || match.posted_at || "";

      // Application methods with likelihood scores
      var applicationMethods = [
        {
          id: "tailored",
          title: "Review My Profile First",
          subtitle: "Get an expert review of your profile before you apply and increase your chances of landing the role.",
          badge: "Recommended",
          timing: "24-48 hours",
          likelihood: 92,
          isRecommended: true,
          icon: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="10"/></svg>',
        },
        {
          id: "intro",
          title: "Request Introduction",
          subtitle: "Warm intro to " + this.escapeHtml(recruiterFirstName) + " via MENA Careers",
          badge: null,
          timing: "Within 24 hours",
          likelihood: 78,
          isRecommended: false,
          icon: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        },
        {
          id: "direct",
          title: "Apply Directly",
          subtitle: "Regular application with no MENA Careers tailoring or expert review",
          badge: null,
          timing: "Instant",
          likelihood: 35,
          isRecommended: false,
          icon: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/></svg>',
        },
      ];

      var alreadyRequested = !!match.application_requested;
      var modalHTML =
        '<div class="sffc-match-modal" data-match-modal>' +
        '<div class="sffc-match-modal-overlay" data-modal-close></div>' +
        '<div class="sffc-match-modal-content sffc-match-modal--skyscanner">' +
        '<div class="sffc-match-modal-topbar">' +
        '<div class="sffc-match-modal-topbar-main">' +
        '<button class="sffc-match-modal-back" data-modal-close>' +
        '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>' +
        '<span>Back to matches</span>' +
        '</button>' +
        '<div class="sffc-match-modal-hero-content">' +
        '<div class="sffc-match-modal-hero-badge">' +
        '<span class="sffc-match-hero-score">' + matchScore + '% match</span>' +
        '</div>' +
        '<h1 class="sffc-match-modal-hero-title">' + this.escapeHtml(match.role_title) + '</h1>' +
        '<div class="sffc-match-modal-hero-meta">' +
        '<span class="sffc-match-hero-company">' +
        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V11l-6-4"/><path d="M9 9v.01"/><path d="M9 12v.01"/><path d="M9 15v.01"/><path d="M9 18v.01"/></svg>' +
        this.escapeHtml(match.company || "") +
        '</span>' +
        '<span class="sffc-match-hero-location">' +
        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>' +
        this.escapeHtml(match.location || "") +
        '</span>' +
        (postedDate ? '<span class="sffc-match-hero-posted">' +
        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>' +
        'Posted ' + this.formatTimeAgo(postedDate) +
        '</span>' : '') +
        '</div>' +
        '</div>' +
        '</div>' +
        '<button type="button" class="sffc-match-modal-logo" data-modal-close aria-label="Close modal">' +
        '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>' +
        '</button>' +
        '</div>' +
        // Two-column body
        '<div class="sffc-match-modal-body sffc-match-modal-body--two-col">' +
        // Left column: Application methods
        '<div class="sffc-match-modal-left">' +
        // Section header
        '<div class="sffc-match-modal-section-header">' +
        '<h2 class="sffc-match-modal-section-title">Choose how to apply</h2>' +
        '<p class="sffc-match-modal-section-subtitle">Check what your CV may be missing before you apply, then decide whether to adjust it or continue directly.</p>' +
        '</div>' +
        // Job description accordion
        '<div class="sffc-match-jd-accordion" id="sffc-match-jd-accordion">' +
        '<button class="sffc-match-jd-toggle" id="sffc-match-jd-toggle">' +
        '<div class="sffc-match-jd-toggle-left">' +
        '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>' +
        '<span>Read before applying</span>' +
        '</div>' +
        '<svg class="sffc-match-jd-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>' +
        '</button>' +
        '<div class="sffc-match-jd-content" id="sffc-match-jd-content" style="display: none;">' +
        '<div class="sffc-match-jd-text">' +
        (jobDescription ? this.escapeHtml(jobDescription).replace(/\n/g, '<br>') : '<p class="sffc-match-jd-empty">Job description not available. Our team will review the role details when preparing your application.</p>') +
        '</div>' +
        '</div>' +
        '</div>' +
        // Application methods
        '<div class="sffc-match-modal-methods">';

      // Render application method cards
      applicationMethods.forEach(function (method) {
        var likelihoodClass = method.likelihood >= 80 ? "high" : method.likelihood >= 60 ? "medium" : "low";

        modalHTML +=
          '<div class="sffc-match-method-card' +
          (method.isRecommended ? " sffc-match-method-card--recommended" : "") +
          '" data-method="' + method.id + '">' +
          '<div class="sffc-match-method-row">' +
          '<div class="sffc-match-method-icon">' + method.icon + '</div>' +
          '<div class="sffc-match-method-content">' +
          '<div class="sffc-match-method-header">' +
          '<h3 class="sffc-match-method-title">' + method.title + '</h3>' +
          (method.badge ? '<span class="sffc-match-method-badge">' + method.badge + '</span>' : '') +
          '</div>' +
          '<p class="sffc-match-method-subtitle">' + method.subtitle + '</p>' +
          '<div class="sffc-match-method-stats">' +
          '<div class="sffc-match-method-likelihood">' +
          '<span class="sffc-match-likelihood-label">Likelihood</span>' +
          '<div class="sffc-match-likelihood-meter">' +
          '<div class="sffc-match-likelihood-fill sffc-match-likelihood-fill--' + likelihoodClass + '" style="width: ' + method.likelihood + '%;"></div>' +
          '</div>' +
          '<span class="sffc-match-likelihood-value sffc-match-likelihood-value--' + likelihoodClass + '">' + method.likelihood + '%</span>' +
          '</div>' +
          '<div class="sffc-match-method-timing">' +
          '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>' +
          '<span>' + method.timing + '</span>' +
          '</div>' +
          '</div>' +
          '</div>' +
          '<button class="sffc-match-method-btn' + (method.isRecommended ? " sffc-match-method-btn--primary" : "") + '" data-action="' + method.id + '" data-match-id="' + match.id + '">' +
          'Select' +
          '</button>' +
          '</div>' +
          '</div>';
      });

      modalHTML +=
        '</div>' +
        // CV Selection panel
        '<div class="sffc-match-cv-panel" id="sffc-match-cv-panel" style="display: none;">' +
        '<div class="sffc-match-cv-panel-header">' +
        '<h4 class="sffc-match-cv-panel-title">' +
        '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>' +
        'Select CV to tailor' +
        '</h4>' +
        '</div>' +
        '<div class="sffc-match-cv-list" id="sffc-match-cv-list">' +
        '<div class="sffc-match-cv-loading">Loading your CVs...</div>' +
        '</div>' +
        '</div>' +
        // Selected CV indicator
        '<div class="sffc-match-cv-selected" id="sffc-match-cv-selected" style="display: none;">' +
        '<div class="sffc-match-cv-selected-info">' +
        '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>' +
        '<div class="sffc-match-cv-selected-text">' +
        '<span class="sffc-match-cv-selected-label">CV selected</span>' +
        '<span class="sffc-match-cv-selected-name" id="sffc-match-cv-selected-name">--</span>' +
        '</div>' +
        '</div>' +
        '<button class="sffc-match-cv-adjust-btn" id="sffc-match-cv-adjust-btn">Change</button>' +
        '</div>' +
        '</div>' +
        // Right column: Role details panel
        '<div class="sffc-match-modal-right">' +
        '<div class="sffc-match-details-card">' +
        '<div class="sffc-match-details-card-header">' +
        '<h3>Role details</h3>' +
        '</div>' +
        '<div class="sffc-match-details-card-body">' +
        // Recruiter section
        '<div class="sffc-match-recruiter-section">' +
        '<div class="sffc-match-recruiter-avatar">';

      if (match.recruiter_photo) {
        modalHTML += '<img src="' + this.escapeHtml(match.recruiter_photo) + '" alt="' + this.escapeHtml(match.recruiter_name || "") + '">';
      } else {
        modalHTML += '<span class="sffc-match-recruiter-initial">' + recruiterFirstName.charAt(0).toUpperCase() + '</span>';
      }

      modalHTML +=
        '</div>' +
        '<div class="sffc-match-recruiter-info">' +
        '<span class="sffc-match-recruiter-name">' + this.escapeHtml(match.recruiter_name || "Hiring Manager") + '</span>' +
        '<span class="sffc-match-recruiter-title">' + this.escapeHtml(match.recruiter_title || "Recruiter") + '</span>' +
        '</div>' +
        '</div>' +
        // Details list
        '<div class="sffc-match-details-list">' +
        '<div class="sffc-match-details-item">' +
        '<span class="sffc-match-details-label">Company</span>' +
        '<span class="sffc-match-details-value">' + this.escapeHtml(match.company || "—") + '</span>' +
        '</div>' +
        '<div class="sffc-match-details-item">' +
        '<span class="sffc-match-details-label">Location</span>' +
        '<span class="sffc-match-details-value">' + this.escapeHtml(match.location || "—") + '</span>' +
        '</div>' +
        '<div class="sffc-match-details-item">' +
        '<span class="sffc-match-details-label">Firm</span>' +
        '<span class="sffc-match-details-value">' + this.escapeHtml(match.recruiter_firm || "—") + '</span>' +
        '</div>' +
        '<div class="sffc-match-details-item sffc-match-details-item--highlight">' +
        '<span class="sffc-match-details-label">Match score</span>' +
        '<span class="sffc-match-details-value"><span class="sffc-match-score-pill">' + matchScore + '%</span></span>' +
        '</div>' +
        '</div>' +
        '</div>' +
        '</div>' +
        // Application journey card
        '<div class="sffc-match-journey-card">' +
        '<div class="sffc-match-journey-header">' +
        '<h3>Application journey</h3>' +
        '</div>' +
        '<div class="sffc-match-journey-steps">' +
        '<div class="sffc-match-journey-step sffc-match-journey-step--complete">' +
        '<div class="sffc-match-journey-dot"></div>' +
        '<div class="sffc-match-journey-content">' +
        '<span class="sffc-match-journey-label">Role matched</span>' +
        '<span class="sffc-match-journey-date">Today</span>' +
        '</div>' +
        '</div>' +
        '<div class="sffc-match-journey-step">' +
        '<div class="sffc-match-journey-dot"></div>' +
        '<div class="sffc-match-journey-content">' +
        '<span class="sffc-match-journey-label">Materials prepared</span>' +
        '<span class="sffc-match-journey-date">Select method</span>' +
        '</div>' +
        '</div>' +
        '<div class="sffc-match-journey-step">' +
        '<div class="sffc-match-journey-dot"></div>' +
        '<div class="sffc-match-journey-content">' +
        '<span class="sffc-match-journey-label">Application sent</span>' +
        '<span class="sffc-match-journey-date">—</span>' +
        '</div>' +
        '</div>' +
        '</div>' +
        '</div>' +
        '</div>' +
        '</div>' +
        // Preview panel (hidden by default)
        '<div class="sffc-match-preview-panel" id="sffc-match-preview-panel">' +
        '<div class="sffc-match-preview-topbar">' +
        '<button class="sffc-match-preview-back" id="sffc-match-preview-back">' +
        '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>' +
        '<span>Back</span>' +
        '</button>' +
        '<h2 class="sffc-match-preview-title">Your tailored materials</h2>' +
        '<div class="sffc-match-preview-spacer"></div>' +
        '</div>' +
        '<div class="sffc-match-preview-body">' +
        '<div class="sffc-match-preview-content" id="sffc-match-preview-content">' +
        '<div class="sffc-match-preview-loading">' +
        '<div class="sffc-match-preview-spinner"></div>' +
        '<p>Preparing your package...</p>' +
        '</div>' +
        '</div>' +
        '</div>' +
        '<div class="sffc-match-preview-footer">' +
        '<div class="sffc-match-preview-footer-info">' +
        '<p class="sffc-match-preview-note">We will prepare and deliver your tailored materials for you to review and submit.</p>' +
        '</div>' +
        '<button class="sffc-match-preview-send' + (alreadyRequested ? ' is-requested' : '') + '" id="sffc-match-preview-send" disabled>' +
        (alreadyRequested ? 'Requested' : 'Request Materials') +
        '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/></svg>' +
        '</button>' +
        '</div>' +
        '</div>' +
        '</div>' +
        '</div>';

      $("body").append(modalHTML);
      $("body").addClass("sffc-match-modal-open");

      // Bind events
      $("[data-modal-close]").on("click", function () {
        self.closeMatchModal();
      });

      // Job description toggle
      $("#sffc-match-jd-toggle").on("click", function () {
        var $content = $("#sffc-match-jd-content");
        var $chevron = $(".sffc-match-jd-chevron");
        if ($content.is(":visible")) {
          $content.slideUp(200);
          $chevron.removeClass("sffc-match-jd-chevron--open");
        } else {
          $content.slideDown(200);
          $chevron.addClass("sffc-match-jd-chevron--open");
        }
      });

      // Method selection
      $(".sffc-match-method-btn").on("click", function () {
        var $button = $(this);
        var action = $button.data("action");
        var matchId = $button.data("match-id");
        self.handleMethodSelection(action, matchId, $button);
      });

      // CV adjust button
      $("#sffc-match-cv-adjust-btn").on("click", function () {
        self.showCVSelectionPanel();
      });

      // Preview back button
      $("#sffc-match-preview-back").on("click", function () {
        self.hidePreviewPanel();
      });

      $(document).on("click.matchPreviewCv", function (e) {
        if (!$(e.target).closest(".sffc-match-preview-cv-picker, .sffc-match-preview-cv-trigger, .sffc-match-package-cv-change").length) {
          self.hidePreviewCVPicker();
        }

        if (!$(e.target).closest(".sffc-match-preview-plan-picker, .sffc-match-preview-send").length) {
          self.hidePreviewPlanPicker();
        }

        if (!$(e.target).closest(".sffc-match-method-plan-picker, .sffc-match-method-btn[data-action='tailored'], .sffc-match-method-btn[data-action='intro']").length) {
          self.hideMatchMethodPlanPicker();
        }
      });

      // Send application button
      $("#sffc-match-preview-send").on("click", function () {
        self.sendApplication();
      });

      // Escape key to close
      $(document).on("keydown.matchModal", function (e) {
        if (e.key === "Escape") {
          self.hidePreviewCVPicker();
          self.hidePreviewPlanPicker();
          self.hideMatchMethodPlanPicker();
          self.closeMatchModal();
        }
      });

      // Load user CVs in background
      self.loadUserCVs();
    },

    handleMethodSelection: function (action, matchId, $button) {
      var self = this;

      // Remove active state from other methods
      $(".sffc-match-method-card").removeClass("sffc-match-method-card--selected");
      $button.closest(".sffc-match-method-card").addClass("sffc-match-method-card--selected");

      if (action === "tailored") {
        if (!window.sffcUserData.isLoggedIn) {
          window.location.href = joinUrl || "/join/";
          return;
        }

        self.showPreviewPanel();
      } else if (action === "intro") {
        if (!window.sffcUserData.isLoggedIn) {
          window.location.href = joinUrl || "/join/";
          return;
        }

        this.handleMatchAction("intro", matchId, $button);
      } else if (action === "direct") {
        this.handleMatchAction("direct", matchId, $button);
      }
    },

    loadUserCVs: function () {
      var self = this;

      $.post(
        window.sffcCRMLinkedIn?.ajaxUrl || "/wp-admin/admin-ajax.php",
        {
          action: "sffc_crm_get_cv_versions",
          nonce: window.sffcCRMLinkedIn?.nonce || "",
        },
        function (response) {
          if (response && response.success && response.data && response.data.items) {
            self.userCVs = response.data.items;
            var savedCvId = self.getSavedApplicationCvId();
            var savedCv = self.userCVs.find(function (cv) {
              return String(cv.id) === String(savedCvId);
            });
            var defaultCV = self.userCVs.find(function (cv) {
              return cv.is_default;
            });
            self.selectedCvForApplication = savedCv || defaultCV || self.userCVs[0] || null;
          }
        }
      );
    },

    showCVSelectionPanel: function () {
      var self = this;
      var $panel = $("#sffc-match-cv-panel");
      var $list = $("#sffc-match-cv-list");
      var $selected = $("#sffc-match-cv-selected");

      $selected.hide();
      $panel.slideDown(200);

      if (self.userCVs.length === 0) {
        $list.html(
          '<div class="sffc-match-cv-empty">' +
          '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>' +
          "<p>No CVs uploaded yet</p>" +
          '<a href="/terminal/?tab=profile" class="sffc-match-cv-upload-link">Upload your CV in Profile</a>' +
          "</div>"
        );
        return;
      }

      var html = "";
      self.userCVs.forEach(function (cv) {
        var isSelected = self.selectedCvForApplication && self.selectedCvForApplication.id === cv.id;
        html +=
          '<div class="sffc-match-cv-item' +
          (isSelected ? " sffc-match-cv-item--selected" : "") +
          '" data-cv-id="' +
          cv.id +
          '">' +
          '<div class="sffc-match-cv-item-radio">' +
          (isSelected
            ? '<svg width="18" height="18" viewBox="0 0 24 24" fill="#0d353e" stroke="none"><circle cx="12" cy="12" r="10"/><path d="M9 12l2 2 4-4" stroke="#fff" stroke-width="2" fill="none"/></svg>'
            : '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg>') +
          "</div>" +
          '<div class="sffc-match-cv-item-info">' +
          '<span class="sffc-match-cv-item-label">' +
          self.escapeHtml(cv.label || "CV") +
          "</span>" +
          '<span class="sffc-match-cv-item-date">' +
          (cv.created_at ? "Uploaded " + self.formatTimeAgo(cv.created_at) : "") +
          "</span>" +
          "</div>" +
          (cv.is_default ? '<span class="sffc-match-cv-item-default">Default</span>' : "") +
          "</div>";
      });

      $list.html(html);

      // Bind CV selection
      $(".sffc-match-cv-item").on("click", function () {
        var cvId = $(this).data("cv-id");
        self.selectCV(cvId);
      });
    },

    selectCV: function (cvId) {
      var self = this;
      var cv = self.userCVs.find(function (c) {
        return String(c.id) === String(cvId);
      });
      var customRequest = $.trim($("#sffc-match-package-request-input").val() || "");

      if (!cv) return;

      self.selectedCvForApplication = cv;
      self.saveApplicationCvId(cv.id);
      if (self.materialsPreviewData) {
        self.materialsPreviewData.customRequest = customRequest;
      }

      // Update UI
      $(".sffc-match-cv-item").removeClass("sffc-match-cv-item--selected");
      $(".sffc-match-cv-item[data-cv-id='" + cvId + "']").addClass("sffc-match-cv-item--selected");

      // Update radio icons
      $(".sffc-match-cv-item-radio").each(function () {
        var $this = $(this);
        var itemCvId = $this.closest(".sffc-match-cv-item").data("cv-id");
        if (itemCvId === cvId) {
          $this.html(
            '<svg width="18" height="18" viewBox="0 0 24 24" fill="#0d353e" stroke="none"><circle cx="12" cy="12" r="10"/><path d="M9 12l2 2 4-4" stroke="#fff" stroke-width="2" fill="none"/></svg>'
          );
        } else {
          $this.html(
            '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg>'
          );
        }
      });

      if ($("#sffc-match-preview-panel").is(":visible")) {
        self.generatePreview();
        return;
      }

      // Hide panel and show indicator after brief delay
      setTimeout(function () {
        self.showSelectedCVIndicator();
        self.showPreviewPanel();
      }, 300);
    },

    showSelectedCVIndicator: function () {
      var self = this;
      var $panel = $("#sffc-match-cv-panel");
      var $selected = $("#sffc-match-cv-selected");
      var $name = $("#sffc-match-cv-selected-name");

      $panel.slideUp(200);
      $name.text(self.selectedCvForApplication.label || "Selected CV");
      $selected.slideDown(200);
    },

    showNoCVsMessage: function () {
      var $panel = $("#sffc-match-cv-panel");
      var $list = $("#sffc-match-cv-list");

      $panel.slideDown(200);
      $list.html(
        '<div class="sffc-match-cv-empty">' +
        '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>' +
        "<p>No CVs uploaded yet</p>" +
        '<a href="/terminal/?tab=profile" class="sffc-match-cv-upload-link">Upload your CV in Profile</a>' +
        "</div>"
      );
    },

    showPreviewPanel: function () {
      var self = this;
      var $body = $(".sffc-match-modal-body--two-col");
      var $preview = $("#sffc-match-preview-panel");

      // Hide the two-column view
      $body.addClass("sffc-match-modal-body--hidden");
      // Show preview
      $preview.addClass("sffc-match-preview-panel--visible").show();

      // Generate preview content
      self.generatePreview();
    },

    hidePreviewPanel: function () {
      var $body = $(".sffc-match-modal-body--two-col");
      var $preview = $("#sffc-match-preview-panel");

      this.hidePreviewCVPicker();
      this.hidePreviewPlanPicker();
      this.restoreGapAnalyzerShell();
      $preview.removeClass("sffc-match-preview-panel--visible").hide();
      $body.removeClass("sffc-match-modal-body--hidden");
    },

    generatePreview: function () {
      var self = this;
      var match = self.currentModalMatch;
      var $content = $("#sffc-match-preview-content");

      if (!match) {
        $content.html('<p class="sffc-match-preview-error">Unable to generate preview. Please try again.</p>');
        return;
      }

      // Show loading
      $content.html(
        '<div class="sffc-match-preview-loading">' +
        '<div class="sffc-match-preview-spinner"></div>' +
        '<p>Preparing your tailored analyzer...</p>' +
        '</div>'
      );

      self.fetchGapAnalyzerPayload(match)
        .done(function (response) {
          if (!(response && response.success && response.data)) {
            $content.html('<p class="sffc-match-preview-error">Unable to load the tailored analyzer right now.</p>');
            return;
          }

          self.renderGapAnalyzerPreview(response.data);
          self.syncPreviewSendButtonState(match);
          self.materialsPreviewData = {
            match: match,
            cv: self.selectedCvForApplication,
            customRequest: self.materialsPreviewData?.customRequest || "",
          };
        })
        .fail(function (xhr) {
          var message = xhr?.responseJSON?.data?.message || "Unable to load the tailored analyzer right now.";
          $content.html('<p class="sffc-match-preview-error">' + self.escapeHtml(message) + '</p>');
        });
    },

    fetchGapAnalyzerPayload: function (match) {
      return $.ajax({
        url: window.sffcCRMLinkedIn?.ajaxUrl || "/wp-admin/admin-ajax.php",
        type: "POST",
        dataType: "json",
        data: {
          action: "sffc_crm_get_gap_payload",
          nonce: window.sffcCRMLinkedIn?.nonce || "",
          post_id: match?.id || 0,
          cv_id: this.selectedCvForApplication?.id || "",
        },
      });
    },

    renderGapAnalyzerPreview: function (payload) {
      var $content = $("#sffc-match-preview-content");
      var $body = $(".sffc-match-preview-body");
      var $panel = $("#sffc-match-preview-panel");
      var $shell = $("#sffc-gap-analyzer-shell");
      var $park = $("#sffc-gap-analyzer-park");

      if (!$content.length || !$body.length || !$panel.length || !$shell.length || !$park.length) {
        $content.html('<p class="sffc-match-preview-error">Gap analyzer is unavailable on this page.</p>');
        return;
      }

      this.restoreGapAnalyzerShell();

      $content
        .empty()
        .addClass("sffc-match-preview-content--gap-analyzer");
      $body.addClass("sffc-match-preview-body--gap-analyzer");
      $panel.addClass("sffc-match-preview-panel--gap-analyzer");

      $shell
        .removeAttr("hidden")
        .css("display", "block");

      $content.append($shell);

      this.hydrateGapAnalyzerShell($shell, payload);
    },

    hydrateGapAnalyzerShell: function ($shell, payload) {
      var analyzer = $shell.closest("[data-component='gap-analyzer']").data("gapAnalyzerInstance");
      var jdText = payload?.jd_text || "";
      var cvText = payload?.cv_text || "";
      var hasCv = !!payload?.has_cv;

      if (analyzer && typeof analyzer.loadDocuments === "function") {
        analyzer.loadDocuments(jdText, cvText, {
          autoAnalyze: hasCv,
          resetView: true,
          statusLabel: hasCv ? "Detected" : "JD ready",
          statusDetails: hasCv
            ? "MENA Careers detected the job description and your selected CV."
            : "MENA Careers detected the job description. Add CV text to run the analysis.",
          hintText: hasCv
            ? "MENA Careers detected the job description and your selected CV"
            : "MENA Careers detected the job description. Add CV text to continue",
        });
        return;
      }

      $shell.find('.inst-gap-textarea[data-input="jd"]').val(jdText).trigger("input");
      $shell.find('.inst-gap-textarea[data-input="cv"]').val(cvText).trigger("input");
    },

    restoreGapAnalyzerShell: function () {
      var $content = $("#sffc-match-preview-content");
      var $body = $(".sffc-match-preview-body");
      var $panel = $("#sffc-match-preview-panel");
      var $shell = $("#sffc-gap-analyzer-shell");
      var $park = $("#sffc-gap-analyzer-park");

      if ($content.length) {
        $content.removeClass("sffc-match-preview-content--gap-analyzer");
      }
      if ($body.length) {
        $body.removeClass("sffc-match-preview-body--gap-analyzer");
      }
      if ($panel.length) {
        $panel.removeClass("sffc-match-preview-panel--gap-analyzer");
      }

      if ($shell.length && $park.length && !$shell.parent().is($park)) {
        $shell
          .attr("hidden", "hidden")
          .css("display", "none");
        $park.append($shell);
      }
    },

    sendApplication: function () {
      var self = this;
      var $sendBtn = $("#sffc-match-preview-send");
      var match = self.currentModalMatch;
      var cv = self.selectedCvForApplication;
      var customRequest = $.trim($("#sffc-match-package-request-input").val() || "");

      if (!window.sffcUserData.isLoggedIn) {
        window.location.href = joinUrl || "/join/";
        return;
      }

      if (!match || !cv) {
        alert("Please select a CV first.");
        return;
      }

      if (match.application_requested || $sendBtn.hasClass("is-requested")) {
        self.syncPreviewSendButtonState(match);
        return;
      }

      $sendBtn.prop("disabled", true).removeClass("is-requested").html(
        '<svg class="sffc-btn-spinner" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10" opacity="0.25"/><path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/></svg>' +
        " Sending..."
      );

      $.ajax({
        url: window.sffcCRMLinkedIn?.ajaxUrl || "/wp-admin/admin-ajax.php",
        type: "POST",
        dataType: "json",
        data: {
          action: "sffc_crm_request_app_pack",
          nonce: window.sffcCRMLinkedIn?.nonce || "",
          post_id: match.id || 0,
          recruiter_id: match.recruiter_id || 0,
          recruiter_name: match.recruiter_name || "",
          recruiter_email: match.recruiter_email || "",
          recruiter_linkedin: match.recruiter_linkedin || "",
          role_title: match.role_title || "",
          company_name: match.company || "",
          location: match.location || "",
          role_url: match.apply_url || match.url || "",
          criteria_id: self.selectedCriteria?.id || 0,
          cv_id: cv.id || "",
          custom_request: customRequest,
          application_type: "tailored",
        }
      }).done(function (response) {
        if (response && response.success) {
          match.application_requested = true;
          self.syncPreviewSendButtonState(match);
          self.showNotification(response.data?.message || "Your tailored materials have been requested and queued for the MENA Careers team.");
          return;
        }

        self.resetPreviewSendButton();
        alert(response?.data?.message || "Unable to send application. Please try again.");
      }).fail(function (xhr) {
        var responseJSON = xhr && xhr.responseJSON ? xhr.responseJSON : null;
        var responseText = xhr && typeof xhr.responseText === "string" ? xhr.responseText : "";
        var message = "";

        if (responseJSON && responseJSON.data && responseJSON.data.message) {
          message = responseJSON.data.message;
        } else if (responseText) {
          message = responseText.replace(/<[^>]*>/g, " ").replace(/\s+/g, " ").trim();
        }

        if (/already|queued|requested/i.test(message)) {
          match.application_requested = true;
          self.syncPreviewSendButtonState(match);
          return;
        }

        self.resetPreviewSendButton();
        alert(message || "Unable to submit this request right now. Please try again.");
      });
    },

    resetPreviewSendButton: function () {
      this.hidePreviewCVPicker();
      this.hidePreviewPlanPicker();
      $("#sffc-match-preview-send")
        .prop("disabled", false)
        .removeClass("is-requested")
        .html(
          'Request Materials' +
          '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/></svg>'
        );
    },

    syncPreviewSendButtonState: function (match) {
      var isRequested = !!(match && match.application_requested);
      var $sendBtn = $("#sffc-match-preview-send");
      var hasCv = !!this.selectedCvForApplication;

      if (!$sendBtn.length) {
        return;
      }

      if (isRequested) {
        $sendBtn
          .prop("disabled", true)
          .addClass("is-requested")
          .html(
            'Requested' +
            '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>'
          );
        return;
      }

      if (!hasCv) {
        $sendBtn
          .prop("disabled", true)
          .removeClass("is-requested")
          .html("Upload CV to Continue");
        return;
      }

      $sendBtn
        .prop("disabled", false)
        .removeClass("is-requested")
        .html(
          'Request Materials' +
          '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/></svg>'
        );
    },

    renderPreviewCVPicker: function () {
      if (!this.userCVs.length || !this.selectedCvForApplication) {
        $(".sffc-match-preview-cv-picker-wrap").remove();
        return;
      }

      var html =
        '<div class="sffc-match-preview-cv-picker-wrap">' +
        '<button type="button" class="sffc-match-preview-cv-trigger" id="sffc-match-preview-cv-trigger">' +
        '<span class="sffc-match-preview-cv-trigger-label">CV</span>' +
        '<span class="sffc-match-preview-cv-trigger-name">' + this.escapeHtml(this.selectedCvForApplication?.display_label || this.selectedCvForApplication?.label || "Selected CV") + '</span>' +
        '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="18 15 12 9 6 15"/></svg>' +
        '</button>' +
        '<div class="sffc-match-preview-cv-picker" id="sffc-match-preview-cv-picker" style="display:none;"></div>' +
        '</div>';

      $(".sffc-match-preview-cv-picker-wrap").remove();
      $(".sffc-match-preview-footer-info").append(html);
    },

    renderPreviewPlanPicker: function () {
      var $actionWrap = $(".sffc-match-preview-footer-actions");

      if (!$actionWrap.length) {
        var $sendBtn = $("#sffc-match-preview-send");
        if (!$sendBtn.length) {
          return;
        }

        $sendBtn.wrap('<div class="sffc-match-preview-footer-actions"></div>');
        $actionWrap = $(".sffc-match-preview-footer-actions");
      }

      if ($actionWrap.find("#sffc-match-preview-plan-picker").length) {
        return;
      }

      $actionWrap.append(
        '<div class="sffc-match-preview-plan-picker" id="sffc-match-preview-plan-picker" style="display:none;"></div>'
      );
    },

    bindPreviewCVPicker: function () {
      var self = this;
      $("#sffc-match-preview-cv-trigger, .sffc-match-package-cv-change").off("click.sffcPreviewCv").on("click.sffcPreviewCv", function (e) {
        e.preventDefault();
        e.stopPropagation();
        self.togglePreviewCVPicker();
      });

      $(".sffc-match-package-cv-upload").off("click.sffcPreviewUpload").on("click.sffcPreviewUpload", function (e) {
        e.preventDefault();
        e.stopPropagation();

        var $input = $("#sffc-match-package-cv-upload-input");
        if (!$input.length || $input.data("opening")) {
          return;
        }

        $input.data("opening", true);
        $input[0].click();

        window.setTimeout(function () {
          $input.removeData("opening");
        }, 250);
      });

      $("#sffc-match-package-cv-upload-input").off("change.sffcPreviewUpload").on("change.sffcPreviewUpload", function (e) {
        var file = e.target.files && e.target.files[0] ? e.target.files[0] : null;
        if (file) {
          self.uploadPreviewCV(file);
        }
        $(this).removeData("opening");
        $(this).val("");
      });
    },

    togglePreviewCVPicker: function () {
      var $picker = $("#sffc-match-preview-cv-picker");
      if (!$picker.length) {
        return;
      }

      if ($picker.is(":visible")) {
        this.hidePreviewCVPicker();
        return;
      }

      this.populatePreviewCVPicker();
      $picker.stop(true, true).fadeIn(120);
      $("#sffc-match-preview-cv-trigger").addClass("is-open");
    },

    hidePreviewCVPicker: function () {
      $("#sffc-match-preview-cv-picker").stop(true, true).fadeOut(120);
      $("#sffc-match-preview-cv-trigger").removeClass("is-open");
    },

    showPreviewPlanPicker: function () {
      var $picker = $("#sffc-match-preview-plan-picker");
      if (!$picker.length) {
        return;
      }

      this.hidePreviewCVPicker();
      this.populatePreviewPlanPicker();
      $picker.stop(true, true).fadeIn(120);
      $("#sffc-match-preview-send").addClass("is-open");
    },

    hidePreviewPlanPicker: function () {
      $("#sffc-match-preview-plan-picker").stop(true, true).fadeOut(120);
      $("#sffc-match-preview-send").removeClass("is-open");
    },

    populatePreviewCVPicker: function () {
      var self = this;
      var $picker = $("#sffc-match-preview-cv-picker");
      if (!$picker.length) {
        return;
      }

      if (!self.userCVs.length) {
        $picker.html('<div class="sffc-match-preview-cv-empty"><a href="/terminal/?tab=profile" class="sffc-match-cv-upload-link">Upload a CV in Profile</a></div>');
        return;
      }

      var html = "";
      self.userCVs.forEach(function (cv) {
        var isSelected = self.selectedCvForApplication && String(self.selectedCvForApplication.id) === String(cv.id);
        html +=
          '<button type="button" class="sffc-match-preview-cv-option' + (isSelected ? ' is-selected' : '') + '" data-cv-id="' + self.escapeHtml(String(cv.id)) + '">' +
          '<span class="sffc-match-preview-cv-option-main">' +
          '<span class="sffc-match-preview-cv-option-name">' + self.escapeHtml(cv.display_label || cv.label || "CV") + '</span>' +
          '<span class="sffc-match-preview-cv-option-meta">' + self.escapeHtml(cv.created_at ? "Uploaded " + self.formatTimeAgo(cv.created_at) : "") + '</span>' +
          '</span>' +
          (isSelected ? '<span class="sffc-match-preview-cv-option-check">✓</span>' : '') +
          '</button>';
      });

      $picker.html(html);

      $(".sffc-match-preview-cv-option").off("click").on("click", function () {
        var cvId = $(this).data("cv-id");
        self.selectCV(cvId);
        self.hidePreviewCVPicker();
      });
    },

    populatePreviewPlanPicker: function () {
      var self = this;
      var $picker = $("#sffc-match-preview-plan-picker");
      var plans = Array.isArray(window.sffcCRMLinkedIn?.upgradePlans)
        ? window.sffcCRMLinkedIn.upgradePlans
        : [];
      var fallbackUrl = "/memberships/";

      if (!$picker.length) {
        return;
      }

      if (!plans.length) {
        $picker.html(
          '<a class="sffc-match-preview-plan-fallback" href="' +
            self.escapeHtml(fallbackUrl) +
            '">View Membership Plans</a>'
        );
        return;
      }

      var html =
        '<div class="sffc-match-preview-plan-picker-head">' +
        '<span class="sffc-match-preview-plan-picker-eyebrow">Upgrade required</span>' +
        '<p class="sffc-match-preview-plan-picker-copy">Tailored materials are available on paid plans.</p>' +
        '</div>';

      plans.forEach(function (plan) {
        var targetUrl = plan.url || (fallbackUrl + (plan.slug ? "#" + plan.slug : ""));
        html +=
          '<button type="button" class="sffc-match-preview-plan-option" data-plan-url="' + self.escapeHtml(targetUrl) + '">' +
          '<span class="sffc-match-preview-plan-option-main">' +
          '<span class="sffc-match-preview-plan-option-name">' + self.escapeHtml(plan.name || "Membership") + '</span>' +
          '<span class="sffc-match-preview-plan-option-desc">' + self.escapeHtml(plan.description || "Includes tailored materials") + '</span>' +
          '</span>' +
          '<span class="sffc-match-preview-plan-option-price">' + self.escapeHtml(plan.price || "") + '</span>' +
          '</button>';
      });

      $picker.html(html);

      $(".sffc-match-preview-plan-option").off("click").on("click", function () {
        var targetUrl = $(this).data("plan-url") || fallbackUrl;
        window.location.href = targetUrl;
      });
    },

    showMatchMethodPlanPicker: function ($button, forcedAction) {
      var self = this;
      var $trigger = $button && $button.length
        ? $button
        : $(".sffc-match-method-btn[data-action='tailored'], .sffc-match-method-btn[data-action='intro']").first();
      var $card = $trigger.closest(".sffc-match-method-card");
      var plans = Array.isArray(window.sffcCRMLinkedIn?.upgradePlans)
        ? window.sffcCRMLinkedIn.upgradePlans
        : [];
      var fallbackUrl = "/memberships/";
      var action = forcedAction || String($trigger.data("action") || "tailored");
      var pickerCopy = action === "intro"
        ? "Recruiter introductions are available on paid plans."
        : "Tailored materials are available on paid plans.";
      var html =
        '<div class="sffc-match-method-plan-picker-head">' +
        '<span class="sffc-match-method-plan-picker-eyebrow">Upgrade required</span>' +
        '<p class="sffc-match-method-plan-picker-copy">' + self.escapeHtml(pickerCopy) + '</p>' +
        '</div>';

      if (!$trigger.length || !$card.length) {
        window.location.href = fallbackUrl;
        return;
      }

      self.hideMatchMethodPlanPicker();

      if (!plans.length) {
        html +=
          '<a class="sffc-match-method-plan-fallback" href="' +
          self.escapeHtml(fallbackUrl) +
          '">View Membership Plans</a>';
      } else {
        plans.forEach(function (plan) {
          var targetUrl = plan.url || (fallbackUrl + (plan.slug ? "#" + plan.slug : ""));
          html +=
            '<button type="button" class="sffc-match-method-plan-option" data-plan-url="' + self.escapeHtml(targetUrl) + '">' +
            '<span class="sffc-match-method-plan-option-main">' +
            '<span class="sffc-match-method-plan-option-name">' + self.escapeHtml(plan.name || "Membership") + '</span>' +
            '<span class="sffc-match-method-plan-option-desc">' + self.escapeHtml(plan.description || "Includes tailored materials") + '</span>' +
            '</span>' +
            '<span class="sffc-match-method-plan-option-price">' + self.escapeHtml(plan.price || "") + '</span>' +
            '</button>';
        });
      }

      $card.append('<div class="sffc-match-method-plan-picker" data-action="' + self.escapeHtml(action) + '">' + html + '</div>');
      $card.addClass("sffc-match-method-card--picker-open");
      $trigger.addClass("is-open");

      $(".sffc-match-method-plan-option").off("click").on("click", function () {
        var targetUrl = $(this).data("plan-url") || fallbackUrl;
        window.location.href = targetUrl;
      });
    },

    hideMatchMethodPlanPicker: function () {
      $(".sffc-match-method-plan-picker").remove();
      $(".sffc-match-method-card").removeClass("sffc-match-method-card--picker-open");
      $(".sffc-match-method-btn[data-action='tailored'], .sffc-match-method-btn[data-action='intro']").removeClass("is-open");
    },

    uploadPreviewCV: function (file) {
      var self = this;
      var formData = new FormData();
      var $uploadButton = $(".sffc-match-package-cv-upload");

      formData.append("file", file);
      formData.append("action", "sffc_crm_upload_document");
      formData.append("nonce", window.sffcCRMLinkedIn?.nonce || "");

      $uploadButton.prop("disabled", true).text("Uploading...");

      $.ajax({
        url: window.sffcCRMLinkedIn?.ajaxUrl || "/wp-admin/admin-ajax.php",
        type: "POST",
        data: formData,
        processData: false,
        contentType: false,
        dataType: "json",
      }).done(function (response) {
        var documentData = response && response.data ? response.data.document || {} : {};

        if (!(response && response.success && documentData.name)) {
          $uploadButton.prop("disabled", false).text("Upload CV");
          alert(response?.data?.message || "Unable to upload CV right now. Please try again.");
          return;
        }

        self.selectedCvForApplication = {
          id: "profile_resume",
          label: documentData.name,
          display_label: documentData.name,
          created_at: documentData.uploaded || "",
          is_default: true,
          is_profile_document: true,
        };
        self.userCVs = [self.selectedCvForApplication];
        self.saveApplicationCvId(self.selectedCvForApplication.id);
        self.generatePreview();
        self.showNotification("CV uploaded to your profile.");
      }).fail(function (xhr) {
        var message = xhr?.responseJSON?.data?.message || "Unable to upload CV right now. Please try again.";
        $uploadButton.prop("disabled", false).text("Upload CV");
        alert(message);
      });
    },

    renderProgressTracker: function (currentStage) {
      var stages = [
        { label: "Intro Requested", step: 0 },
        { label: "Preparing", step: 1 },
        { label: "Delivered", step: 2 },
        { label: "Awaiting Response", step: 3 },
      ];

      var html = '<div class="sffc-progress-tracker">';

      stages.forEach(function (stage, index) {
        var isComplete = currentStage > stage.step;
        var isCurrent = currentStage === stage.step;
        var stateClass = isComplete
          ? "complete"
          : isCurrent
          ? "current"
          : "pending";

        html += '<div class="sffc-progress-stage ' + stateClass + '">';
        html += '<div class="sffc-progress-dot">';
        if (isComplete) {
          html +=
            '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>';
        } else {
          html +=
            '<span class="sffc-progress-number">' + (index + 1) + "</span>";
        }
        html += "</div>";
        html += '<div class="sffc-progress-label">' + stage.label + "</div>";
        if (index < stages.length - 1) {
          html +=
            '<div class="sffc-progress-line ' +
            (isComplete ? "complete" : "") +
            '"></div>';
        }
        html += "</div>";
      });

      html += "</div>";
      return html;
    },

    closeMatchModal: function () {
      var self = this;
      // Reset state
      self.currentModalMatch = null;
      self.materialsPreviewData = null;
      self.jobDescriptionExpanded = false;
      self.restoreGapAnalyzerShell();
      self.hideMatchMethodPlanPicker();
      // Remove escape key listener
      $(document).off("keydown.matchModal");
      $("[data-match-modal]").remove();
      $("body").removeClass("sffc-match-modal-open");
    },

    handleMatchAction: function (action, matchId, $button) {
      var self = this;
      var match = self.currentModalMatch || self.allMatches.find(function (m) {
        return m.id == matchId;
      });

      // Handle different actions
      switch (action) {
        case "intro":
          // Request intro - reuse existing logic
          if ($button) {
            $button.prop("disabled", true).text("Requesting...");
          }
          $.post(
            window.sffcCRMLinkedIn?.ajaxUrl || "/wp-admin/admin-ajax.php",
            {
              action: "sffc_crm_request_introduction",
              nonce: window.sffcCRMLinkedIn?.nonce || "",
              post_id: match?.id || matchId || 0,
              recruiter_id: match?.recruiter_id || 0,
              match_score: match?.match_score || match?.score || 85,
              cv_id: self.selectedCvForApplication?.id || "",
            },
            function (response) {
              if (response && response.success) {
                if ($button) {
                  $button.text("Requested").addClass("sffc-match-method-btn--success");
                }
                self.showNotification("Introduction request sent! We'll connect you with the recruiter shortly.");
              } else {
                if ($button) {
                  $button.prop("disabled", false).text("Select");
                }
                alert(response?.data?.message || "Unable to request introduction. Please try again.");
              }
            }
          ).fail(function () {
            if ($button) {
              $button.prop("disabled", false).text("Select");
            }
            alert("Network error. Please try again.");
          });
          break;
        case "direct":
          // Direct application - just go to external link if available
          if (match && match.apply_url) {
            window.open(match.apply_url, "_blank");
          } else {
            // Fallback: use the request application pack for direct apply
            self.requestApplicationPackFromModal(matchId, $button, "direct");
          }
          break;
        case "materials":
          this.requestApplicationPackFromModal(matchId, $button);
          break;
      }
    },

    requestApplicationPackFromModal: function (matchId, $button, applicationType) {
      var self = this;
      var match = this.allMatches.find(function (item) {
        return String(item.id || "") === String(matchId || "");
      });

      if (!window.sffcCRMLinkedIn?.isLoggedIn) {
        window.location.href = "/join/";
        return;
      }

      if (!match) {
        alert("Unable to find this role. Please refresh and try again.");
        return;
      }

      if ($button && ($button.prop("disabled") || $button.hasClass("is-requested"))) {
        return;
      }

      if ($button) {
        $button
          .prop("disabled", true)
          .addClass("is-loading")
          .text("Requesting...");
      }

      $.post(
        window.sffcCRMLinkedIn?.ajaxUrl || "/wp-admin/admin-ajax.php",
        {
          action: "sffc_crm_request_app_pack",
          nonce: window.sffcCRMLinkedIn?.nonce || "",
          post_id: match.id || matchId || 0,
          recruiter_id: match.recruiter_id || 0,
          recruiter_name: match.recruiter_name || "",
          recruiter_email: match.recruiter_email || "",
          recruiter_linkedin: match.recruiter_linkedin || "",
          role_title: match.role_title || "",
          company_name: match.company || "",
          location: match.location || "",
          role_url: match.apply_url || match.url || "",
          criteria_id: self.selectedCriteria?.id || 0,
          cv_id: self.selectedCvForApplication?.id || "",
          application_type: applicationType || "standard",
        },
        function (response) {
          if (response && response.success) {
            if ($button) {
              $button
                .prop("disabled", true)
                .removeClass("is-loading")
                .addClass("is-requested")
                .text("Requested");
            }
            self.showNotification(
              response.data?.message || "Application Pack requested."
            );
            return;
          }

          if ($button) {
            $button
              .prop("disabled", false)
              .removeClass("is-loading")
              .text("Select");
          }
          alert(
            response?.data?.message ||
              "Unable to request this Application Pack. Please try again."
          );
        }
      ).fail(function (xhr) {
        var response = xhr.responseJSON || {};
        if (xhr.status === 401) {
          window.location.href = "/join/";
          return;
        }

        if ($button) {
          $button
            .prop("disabled", false)
            .removeClass("is-loading")
            .text("Select");
        }
        alert(
          response?.data?.message ||
            "Network error. Please check your connection and try again."
        );
      });
    },

    getHiringVerb: function (roleTitle) {
      // Determine grammatically correct hiring verb
      var firstChar = (roleTitle || "").trim().charAt(0).toUpperCase();
      var vowels = ["A", "E", "I", "O", "U"];
      var isVowel = vowels.indexOf(firstChar) !== -1;

      // Vary the verbs for natural language
      var verbs = ["Hiring", "Looking for", "Searching for", "Seeking"];
      var selectedVerb = verbs[Math.floor(Math.random() * verbs.length)];

      // Use "an" for vowels, "a" for consonants
      if (selectedVerb === "Hiring" || selectedVerb === "Seeking") {
        return selectedVerb + (isVowel ? " an" : " a");
      }

      // "Looking for" and "Searching for" don't need articles
      return selectedVerb;
    },

    getInsight: function (score) {
      if (score >= 90) return "Likely to Respond";
      if (score >= 85) return "Highly Recommended";
      if (score >= 80) return "Great Match";
      if (score >= 75) return "Fits Profile";
      if (score >= 70) return "Recommended";
      if (score >= 60) return "Review Match";
      if (score >= 50) return "Medium Match";
      return "Check Before";
    },

    getInsightClass: function (score) {
      if (score >= 90) return "insight--excellent";
      if (score >= 85) return "insight--excellent";
      if (score >= 80) return "insight--great";
      if (score >= 75) return "insight--great";
      if (score >= 70) return "insight--good";
      if (score >= 60) return "insight--medium";
      if (score >= 50) return "insight--medium";
      return "insight--low";
    },

    getScoreColor: function (score) {
      if (score >= 90) return "#059669"; // emerald-600
      if (score >= 80) return "#0891b2"; // cyan-600
      if (score >= 70) return "#2563eb"; // blue-600
      return "#7c3aed"; // violet-600
    },

    formatTimeAgo: function (dateStr) {
      var date = new Date(dateStr);
      var now = new Date();
      var diffMs = now - date;
      var diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

      if (diffDays === 0) return "Today";
      if (diffDays === 1) return "Yesterday";
      if (diffDays < 7) return diffDays + "d ago";
      if (diffDays < 30) return Math.floor(diffDays / 7) + "w ago";
      return Math.floor(diffDays / 30) + "mo ago";
    },

    escapeHtml: function (str) {
      if (!str) return "";
      return String(str)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#39;");
    },
  };

  // ============================================
  // MARKETS TAB FUNCTIONALITY
  // ============================================
  var MarketsEngine = {
    container: null,
    request: null,

    init: function () {
      this.container = $('[data-panel="markets"]');
      if (!this.container.length) return;

      this.bindEvents();
    },

    bindEvents: function () {
      var self = this;

      // Filter buttons
      this.container.on("click", ".sffc-markets-filter-btn", function () {
        var $btn = $(this);
        var filter = $btn.data("filter");

        if ($btn.hasClass("is-active")) {
          return;
        }

        // Update active state
        self.container.find(".sffc-markets-filter-btn").removeClass("is-active");
        $btn.addClass("is-active");

        // Fetch a fresh first page for this filter so pagination stays accurate.
        self.loadPage({
          page: 1,
          filter: filter,
          replace: true,
        });
      });

      // Action buttons
      this.container.on("click", ".sffc-markets-action-btn[data-action]", function (e) {
        e.preventDefault();
        var $btn = $(this);
        var action = $btn.data("action");
        var articleId = $btn.data("article-id");

        self.handleAction(action, articleId, $btn);
      });

      // Load more
      this.container.on("click", ".sffc-markets-load-more-btn", function () {
        var $btn = $(this);
        var page = parseInt($btn.data("page"), 10) + 1;
        var activeFilter = self.getActiveFilter();

        self.loadPage({
          page: page,
          filter: activeFilter,
          replace: false,
          $trigger: $btn,
        });
      });
    },

    getActiveFilter: function () {
      return this.container.find(".sffc-markets-filter-btn.is-active").data("filter") || "all";
    },

    getGrid: function () {
      return this.container.find(".sffc-markets-grid");
    },

    getEmptyState: function () {
      return this.container.find(".sffc-markets-empty");
    },

    getLoadMoreWrap: function () {
      return this.container.find(".sffc-markets-load-more");
    },

    setResultsState: function (html, options) {
      var $grid = this.getGrid();
      var settings = $.extend(
        {
          replace: false,
          hasMore: false,
          count: 0,
          page: 1,
        },
        options || {}
      );
      var $empty = this.getEmptyState();
      var $loadMoreWrap = this.getLoadMoreWrap();
      var $loadMoreBtn = $loadMoreWrap.find(".sffc-markets-load-more-btn");

      if (settings.replace) {
        $grid.html(html || "");
      } else if (html) {
        $grid.append(html);
      }

      var hasCards = $grid.children().length > 0;

      $grid.toggleClass("is-hidden", !hasCards);
      $empty.toggleClass("is-hidden", hasCards);
      $loadMoreWrap.toggleClass("is-hidden", !settings.hasMore);
      $loadMoreBtn.data("page", settings.page);
    },

    loadPage: function (options) {
      var self = this;
      var settings = $.extend(
        {
          page: 1,
          filter: "all",
          replace: false,
          $trigger: $(),
        },
        options || {}
      );
      var $trigger = settings.$trigger;

      if (this.request && this.request.readyState !== 4) {
        this.request.abort();
      }

      if ($trigger.length) {
        $trigger.addClass("is-loading").prop("disabled", true);
      }

      this.request = $.ajax({
        url: sffcCRMLinkedIn.ajaxUrl,
        type: "POST",
        data: {
          action: "sffc_markets_load_more",
          page: settings.page,
          filter: settings.filter,
          nonce: sffcCRMLinkedIn.nonce,
        },
        success: function (response) {
          if (!response || !response.success) {
            self.showToast((response && response.data && response.data.message) || "Failed to load market articles", "error");
            return;
          }

          self.setResultsState(response.data.html || "", {
            replace: settings.replace,
            hasMore: !!response.data.has_more,
            count: parseInt(response.data.count, 10) || 0,
            page: parseInt(response.data.page, 10) || settings.page,
          });
        },
        error: function (xhr, statusText) {
          if (statusText === "abort") {
            return;
          }

          self.showToast("Failed to load market articles", "error");
        },
        complete: function () {
          if ($trigger.length) {
            $trigger.removeClass("is-loading").prop("disabled", false);
          }
        },
      });
    },

    handleAction: function (action, articleId, $btn) {
      var self = this;

      // Show loading state
      $btn.addClass("is-loading").prop("disabled", true);

      switch (action) {
        case "generate-report":
          self.generateReport(articleId, $btn);
          break;
        case "export-data":
          self.exportData(articleId, $btn);
          break;
        case "analyze":
          self.analyzeWithAI(articleId, $btn);
          break;
        default:
          $btn.removeClass("is-loading").prop("disabled", false);
      }
    },

    generateReport: function (articleId, $btn) {
      var self = this;

      $.ajax({
        url: sffcCRMLinkedIn.ajaxUrl,
        type: "POST",
        data: {
          action: "sffc_markets_generate_report",
          article_id: articleId,
          nonce: sffcCRMLinkedIn.nonce,
        },
        success: function (response) {
          if (response.success && response.data.report_url) {
            // Open report in new tab
            window.open(response.data.report_url, "_blank");
            self.showToast("Report generated successfully", "success");
          } else {
            self.showToast(response.data.message || "Failed to generate report", "error");
          }
        },
        error: function () {
          self.showToast("Failed to generate report", "error");
        },
        complete: function () {
          $btn.removeClass("is-loading").prop("disabled", false);
        },
      });
    },

    exportData: function (articleId, $btn) {
      var self = this;

      $.ajax({
        url: sffcCRMLinkedIn.ajaxUrl,
        type: "POST",
        data: {
          action: "sffc_markets_export_data",
          article_id: articleId,
          nonce: sffcCRMLinkedIn.nonce,
        },
        success: function (response) {
          if (response.success && response.data.download_url) {
            // Trigger download
            var link = document.createElement("a");
            link.href = response.data.download_url;
            link.download = response.data.filename || "export.json";
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            self.showToast("Data exported successfully", "success");
          } else {
            self.showToast(response.data.message || "Failed to export data", "error");
          }
        },
        error: function () {
          self.showToast("Failed to export data", "error");
        },
        complete: function () {
          $btn.removeClass("is-loading").prop("disabled", false);
        },
      });
    },

    analyzeWithAI: function (articleId, $btn) {
      var self = this;
      var $card = $btn.closest(".sffc-markets-card");
      var articleTitle = $card.find(".sffc-markets-card-title a").text();
      var articleUrl = $card.find(".sffc-markets-card-title a").attr("href");
      var articleType = $card.data("article-type");
      var typeLabel = $card.find(".sffc-markets-card-type").text() || articleType || "market";

      // Switch to console tab
      $('[data-tab="console"]').click();

      // Pre-fill search with analysis prompt
      setTimeout(function () {
        var $consoleInput = $(".sffc-console-search-input, .sffc-crm-search-input").first();
        var analysisPrompt =
          "Analyze this " +
          typeLabel.toLowerCase() +
          " article for private equity relevance: " +
          articleTitle;

        if (articleUrl) {
          analysisPrompt += "\nURL: " + articleUrl;
        }

        $consoleInput.val(analysisPrompt).trigger("focus");
        self.showToast("Ready to analyze. Press Enter to start.", "info");
      }, 300);

      $btn.removeClass("is-loading").prop("disabled", false);
    },

    showToast: function (message, type) {
      // Use existing toast system if available, otherwise fallback
      if (typeof ConsoleEngine !== "undefined" && ConsoleEngine.showToast) {
        ConsoleEngine.showToast(message, type);
        return;
      }

      // Simple fallback toast
      var $toast = $('<div class="sffc-markets-toast sffc-markets-toast--' + type + '">' + message + "</div>");
      $("body").append($toast);

      setTimeout(function () {
        $toast.addClass("is-visible");
      }, 10);

      setTimeout(function () {
        $toast.removeClass("is-visible");
        setTimeout(function () {
          $toast.remove();
        }, 300);
      }, 3000);
    },
  };

  // Initialize console engine when document is ready
  $(document).ready(function () {
    ConsoleEngine.init();
    MarketsEngine.init();
  });
})(jQuery);
