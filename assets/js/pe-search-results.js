/**
 * Private Equity Search Results
 * Enhanced Google-style functionality with PE-specific features
 */

(function ($) {
  "use strict";

  // Search Results Class
  class PESearchResults {
    constructor(container) {
      this.container = $(container);
      this.pageParam = "sffc_page";
      this.currentQuery = this.getUrlParameter("q");
      this.currentMode = this.normalizeMode(this.getUrlParameter("mode"));
      this.currentPage = this.getCurrentPage();
      this.currentFilters = this.getFiltersFromUrl();

      this.init();
    }

    init() {
      this.bindEvents();
      this.initQuickActions();
      this.initInsights();
      this.initSearchBar();
      this.populateAdvancedFilters();
      this.initPeopleAlsoAsk();
      this.initMobileFilters();
      this.initScrollHandler();
      this.optimizeMobileExperience();
      this.trackSearchMetrics();
      this.syncActiveModeTabs(this.currentMode);
    }

    bindEvents() {
      // Filter buttons
      this.container.on("click", ".sffc-filter-pill", (e) => {
        this.handleFilterClick($(e.currentTarget));
      });

      // Advanced filter dropdowns
      this.container.on("click", ".sffc-advanced-filter-toggle", (e) => {
        e.preventDefault();
        this.toggleAdvancedFilter($(e.currentTarget));
      });

      this.container.on("click", ".sffc-advanced-filter-apply", (e) => {
        e.preventDefault();
        const filterKey = $(e.currentTarget).data("filter");
        const form = $(e.currentTarget)
          .closest(".sffc-advanced-filter")
          .find("[data-filter-form]" + (filterKey ? `[data-filter-form='${filterKey}']` : ""));
        this.applyAdvancedFilters(form);
      });

      this.container.on("click", ".sffc-advanced-filter-clear", (e) => {
        e.preventDefault();
        const form = $(e.currentTarget)
          .closest(".sffc-advanced-filter")
          .find("form[data-filter-form]");
        this.resetAdvancedForm(form);
        this.applyAdvancedFilters(form, { silent: true });
      });

      this.container.on("click", ".sffc-advanced-clear-all", (e) => {
        e.preventDefault();
        this.clearAllFilters();
      });

      $(document).on("click", (e) => {
        this.handleDocumentClick(e);
      });

      // Mobile filter toggle
      this.container.on("click", ".sffc-mobile-filter-toggle", (e) => {
        e.preventDefault();
        this.toggleMobileFilters($(e.currentTarget));
      });

      // Mode switching
      this.container.on("click", ".sffc-mode-tab", (e) => {
        e.preventDefault();
        this.handleModeSwitch($(e.currentTarget));
      });

      this.container.on("click", ".sffc-mobile-mode-btn", (e) => {
        e.preventDefault();
        this.handleModeSwitch($(e.currentTarget));
      });

      // Search bar interactions
      this.container.on("input", ".sffc-search-input", (e) => {
        this.handleSearchInput($(e.currentTarget));
      });

      this.container.on("keydown", ".sffc-search-input", (e) => {
        if (e.key === "Enter") {
          e.preventDefault();
          this.executeSearch();
        }
      });

      this.container.on("click", ".sffc-search-clear", (e) => {
        e.preventDefault();
        this.clearSearchInput($(e.currentTarget));
      });

      this.container.on("click", ".sffc-voice-search", (e) => {
        e.preventDefault();
        this.handleVoiceSearch($(e.currentTarget));
      });

      this.container.on("click", ".sffc-search-submit", (e) => {
        e.preventDefault();
        this.executeSearch();
      });

      // People also ask accordion
      this.container.on("click", ".sffc-ask-question", (e) => {
        e.preventDefault();
        this.toggleAskItem($(e.currentTarget));
      });

      // No results helpers
      this.container.on("click", ".sffc-no-results-reset", (e) => {
        e.preventDefault();
        this.resetFilters();
      });

      this.container.on("click", ".sffc-no-results-alert", (e) => {
        e.preventDefault();
        this.handleAlertRequest();
      });

      // Result item interactions
      this.container.on("click", ".sffc-result-link", (e) => {
        this.trackResultClick($(e.currentTarget));
      });

      // Quick action buttons
      this.container.on("click", ".sffc-quick-action-btn", (e) => {
        e.preventDefault();
        this.handleQuickAction($(e.currentTarget));
      });

      // Bookmark/Save actions
      this.container.on("click", ".sffc-bookmark-btn", (e) => {
        e.preventDefault();
        this.handleBookmark($(e.currentTarget));
      });

      // Share actions
      this.container.on("click", ".sffc-share-btn", (e) => {
        e.preventDefault();
        this.handleShare($(e.currentTarget));
      });

      // Related search links - handle via AJAX to avoid indexable URLs
      this.container.on("click", ".sffc-related-link", (e) => {
        e.preventDefault();
        this.handleRelatedSearch($(e.currentTarget));
      });

      // Pagination - handle via AJAX to avoid indexable URLs
      this.container.on("click", ".sffc-page-btn[data-page]", (e) => {
        e.preventDefault();
        this.handlePagination($(e.currentTarget));
      });

      // Keyboard shortcuts
      $(document).on("keydown", (e) => {
        this.handleKeyboardShortcuts(e);
      });
    }

    handleFilterClick(button) {
      const filter = button.data("filter");

      // Update UI
      this.container.find(".sffc-filter-pill").removeClass("active");
      button.addClass("active");

      // Apply filter
      this.applyFilter(filter);

      // Track filter usage
      this.trackEvent("filter_applied", {
        filter: filter,
        query: this.currentQuery,
      });
    }

    applyFilter(filter) {
      this.currentFilters.primary = filter;

      // Update state without creating indexable URL
      window.history.replaceState(
        { filter: filter, query: this.currentQuery, mode: this.currentMode },
        "",
        window.location.pathname
      );

      // Show loading state
      this.showLoadingState();

      // Reload results with filter
      this.loadFilteredResults(filter, this.currentFilters);
      this.closeMobileFilters();
    }

    loadFilteredResults(filter, extraFilters = null) {
      const filtersPayload = JSON.stringify(extraFilters || this.currentFilters || {});
      $.ajax({
        url: sffc_results.ajax_url,
        type: "POST",
        data: {
          action: "sffc_load_filtered_results",
          query: this.currentQuery,
          mode: this.currentMode || "jobs",
          filter: filter,
          filters: filtersPayload,
          nonce: sffc_results.nonce,
        },
        success: (response) => {
          if (response.success) {
            this.updateResultsDisplay(response.data.html);
            this.hideLoadingState();
          }
        },
        error: () => {
          this.hideLoadingState();
          this.showErrorMessage("Failed to load filtered results");
        },
      });
    }

    initSearchBar() {
      const input = this.container.find(".sffc-search-input");
      if (!input.length) {
        return;
      }

      // Set the input value from URL parameter if it exists
      if (this.currentQuery && this.currentQuery !== input.val()) {
        input.val(this.currentQuery);
      }

      // Set the mode data attribute
      if (this.currentMode) {
        input.data("mode", this.currentMode);
      }

      this.handleSearchInput(input);
    }

    handleSearchInput(input) {
      const value = input.val ? input.val().trim() : "";
      const clearButton = this.container.find(".sffc-search-clear");
      if (!clearButton.length) {
        return;
      }

      if (value.length) {
        clearButton.removeAttr("hidden");
      } else {
        clearButton.attr("hidden", "hidden");
      }
    }

    clearSearchInput(button) {
      const input = this.container.find(".sffc-search-input");
      if (!input.length) {
        return;
      }
      input.val("");
      this.handleSearchInput(input);
      input.focus();
    }

    handleVoiceSearch(button) {
      if (
        "SpeechRecognition" in window ||
        "webkitSpeechRecognition" in window
      ) {
        this.showGlobalMessage(
          "🎙️ Voice search is coming soon. For now, type your mandate and we'll handle the rest."
        );
      } else {
        this.showGlobalMessage(
          "🔇 Voice search is not available in this browser."
        );
      }
    }

    executeSearch() {
      const input = this.container.find(".sffc-search-input");
      if (!input.length) {
        return;
      }

      const query = (input.val() || "").trim();
      if (!query) {
        input.focus();
        return;
      }

      const mode = this.normalizeMode(
        input.data("mode") || this.currentMode || "jobs"
      );

      // Update instance variables
      this.currentQuery = query;
      this.currentMode = mode;
      input.data("mode", mode);

      // Use AJAX to load results instead of generating indexable URLs
      this.showLoadingState();
      this.loadSearchResults(query, mode);
    }

    loadSearchResults(query, mode, page = 1) {
      $.ajax({
        url: sffc_results.ajax_url,
        type: "POST",
        data: {
          action: "sffc_load_filtered_results",
          query: query,
          mode: mode,
          filter: this.currentFilters.primary || "all",
          filters: JSON.stringify(this.currentFilters),
          page: page,
          nonce: sffc_results.nonce,
        },
        success: (response) => {
          if (response.success) {
            this.updateResultsDisplay(response.data.html);
            this.hideLoadingState();
            // Update state without creating indexable URL
            window.history.replaceState(
              { query: query, mode: mode, page: page },
              "",
              window.location.pathname
            );
          }
        },
        error: () => {
          this.hideLoadingState();
          this.showErrorMessage("Failed to load search results");
        },
      });
    }

    handleModeSwitch(button) {
      const newMode = button.data("mode");
      const placeholder = button.data("placeholder");
      const input = this.container.find(".sffc-search-input");

      if (placeholder) {
        input.attr("placeholder", placeholder);
      }

      const normalizedMode = this.normalizeMode(newMode);

      if (!normalizedMode) {
        return;
      }

      if (normalizedMode === this.currentMode) {
        this.syncActiveModeTabs(normalizedMode);
        return;
      }

      // Get current query from input field (user may have typed a new query)
      const currentInputQuery = input.val() ? input.val().trim() : "";
      const queryToUse = currentInputQuery || this.currentQuery || "";

      this.currentMode = normalizedMode;
      this.currentQuery = queryToUse;
      input.data("mode", normalizedMode);
      this.syncActiveModeTabs(normalizedMode);

      // Use AJAX to load results instead of generating indexable URLs
      this.showLoadingState();
      this.loadSearchResults(queryToUse, normalizedMode);
    }

    initMobileFilters() {
      this.closeMobileFilters();
    }

    toggleMobileFilters(button) {
      const filters = this.container.find(".sffc-search-filters");
      if (!filters.length) {
        return;
      }

      const expanded = button.attr("aria-expanded") === "true";
      if (expanded) {
        button.attr("aria-expanded", "false");
        filters.removeClass("is-open");
      } else {
        button.attr("aria-expanded", "true");
        filters.addClass("is-open");
      }
    }

    closeMobileFilters() {
      const button = this.container.find(".sffc-mobile-filter-toggle");
      const filters = this.container.find(".sffc-search-filters");
      if (button.length) {
        button.attr("aria-expanded", "false");
      }
      if (filters.length) {
        filters.removeClass("is-open");
      }
    }

    initScrollHandler() {
      let ticking = false;
      const isMobile = window.innerWidth <= 768;
      
      const updateHeaderSize = () => {
        const scrollTop = $(window).scrollTop();
        const resultsHeader = this.container.find('.sffc-results-header');
        const body = $('body');
        
        // Different thresholds for mobile vs desktop
        const scrollThreshold = isMobile ? 50 : 100;
        
        if (scrollTop > scrollThreshold) {
          // Add compact classes
          body.addClass('sffc-page-scrolled');
          resultsHeader.addClass('sffc-compact');
        } else {
          // Remove compact classes
          body.removeClass('sffc-page-scrolled');
          resultsHeader.removeClass('sffc-compact');
        }
        
        ticking = false;
      };
      
      const requestTick = () => {
        if (!ticking) {
          requestAnimationFrame(updateHeaderSize);
          ticking = true;
        }
      };
      
      // Mobile-optimized scroll handler
      if (isMobile) {
        // Use passive listeners for better performance on mobile
        window.addEventListener('scroll', requestTick, { passive: true });
      } else {
        $(window).on('scroll.sffc-header-resize', requestTick);
      }
      
      // Initial check
      updateHeaderSize();
      
      // Re-initialize on orientation change
      $(window).on('orientationchange resize', () => {
        setTimeout(() => {
          updateHeaderSize();
          this.optimizeMobileExperience();
        }, 100);
      });
    }

    optimizeMobileExperience() {
      const isMobile = window.innerWidth <= 768;
      const isTouch = 'ontouchstart' in window;
      
      if (isMobile || isTouch) {
        // Add mobile-specific classes
        this.container.addClass('sffc-mobile-optimized');
        
        // Optimize touch interactions
        this.container.find('.sffc-result-item').each((index, element) => {
          const $item = $(element);
          
          // Add touch feedback
          $item.on('touchstart', function(e) {
            $(this).addClass('sffc-touch-active');
          });
          
          $item.on('touchend touchcancel', function(e) {
            setTimeout(() => {
              $(this).removeClass('sffc-touch-active');
            }, 150);
          });
        });

        // Optimize quick action buttons for touch
        this.container.find('.sffc-quick-action-btn').each((index, element) => {
          const $btn = $(element);
          
          // Prevent double-tap zoom on buttons
          $btn.on('touchend', function(e) {
            e.preventDefault();
            $(this).trigger('click');
          });
        });

        // Lazy load optimization for mobile
        this.initLazyLoading();
        
        // Infinite scroll for mobile (if needed)
        this.initMobileInfiniteScroll();
      }
    }

    initLazyLoading() {
      // Lazy load images and heavy content on mobile
      const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            const $item = $(entry.target);
            $item.addClass('sffc-loaded');
            observer.unobserve(entry.target);
          }
        });
      }, {
        root: null,
        rootMargin: '50px',
        threshold: 0.1
      });

      this.container.find('.sffc-result-item').each((index, element) => {
        observer.observe(element);
      });
    }

    initMobileInfiniteScroll() {
      if (!this.container.find('.sffc-pagination').length) return;
      
      let loading = false;
      const loadThreshold = 200; // pixels from bottom
      
      const checkScroll = () => {
        if (loading) return;
        
        const scrollTop = $(window).scrollTop();
        const windowHeight = $(window).height();
        const documentHeight = $(document).height();
        
        if (scrollTop + windowHeight >= documentHeight - loadThreshold) {
          const nextPageBtn = this.container.find('.sffc-page-btn[data-page]:not(.current)').first();
          
          if (nextPageBtn.length) {
            loading = true;
            this.loadNextPage(nextPageBtn);
          }
        }
      };
      
      // Throttled scroll handler for infinite scroll
      let scrollTimeout;
      $(window).on('scroll', () => {
        clearTimeout(scrollTimeout);
        scrollTimeout = setTimeout(checkScroll, 100);
      });
    }

    loadNextPage(nextPageBtn) {
      // This would integrate with existing pagination logic
      // For now, just trigger the existing pagination click
      if (nextPageBtn.length) {
        nextPageBtn.trigger('click');
      }
    }

    initPeopleAlsoAsk() {
      this.container.find(".sffc-ask-question").each((_, element) => {
        const $button = $(element);
        const answerId = $button.attr("aria-controls");
        const $answer = answerId ? this.container.find(`#${answerId}`) : null;
        if ($answer && $button.attr("aria-expanded") === "true") {
          $answer.removeAttr("hidden");
        }
      });
    }

    toggleAskItem(button) {
      const answerId = button.attr("aria-controls");
      const answer = answerId ? this.container.find(`#${answerId}`) : null;
      if (!answer || !answer.length) {
        return;
      }

      const expanded = button.attr("aria-expanded") === "true";
      if (expanded) {
        button.attr("aria-expanded", "false");
        answer.attr("hidden", "hidden");
      } else {
        button.attr("aria-expanded", "true");
        answer.removeAttr("hidden");
      }
    }

    resetFilters() {
      this.currentFilters = { primary: "all" };

      // Update state without creating indexable URL
      window.history.replaceState(
        { query: this.currentQuery, mode: this.currentMode },
        "",
        window.location.pathname
      );

      // Reload results via AJAX
      this.showLoadingState();
      this.loadSearchResults(this.currentQuery, this.currentMode);
    }

    handleAlertRequest() {
      this.showGlobalMessage(
        "📬 We'll build instant alerts soon. For now, bookmark your favourite mandates."
      );
    }

    handleQuickAction(button) {
      const action = button.data("action");
      const resultId = button.data("result-id");
      const resultItem = button.closest(".sffc-result-item");

      switch (action) {
        case "apply":
          this.handleApplyAction(resultId, resultItem);
          break;
        case "send_cv":
          this.handleSendCVAction(resultId, resultItem);
          break;
        case "introduce_me":
          this.handleIntroduceMeAction(resultId, resultItem);
          break;
        case "similar":
          this.handleSimilarAction(resultId, resultItem);
          break;
        case "jobs":
          this.handleViewJobsAction(resultId, resultItem);
          break;
        case "analysis":
          this.handleAnalysisAction(resultId, resultItem);
          break;
        default:
          this.handleGenericAction(action, resultId, resultItem);
      }

      // Track action
      this.trackEvent("quick_action", {
        action: action,
        result_id: resultId,
        query: this.currentQuery,
      });
    }

    handleApplyAction(resultId, resultItem) {
      // Show application modal or redirect to application page
      const resultUrl = resultItem.find(".sffc-result-link").attr("href");

      // Check if we have a direct apply URL
      const applyUrl = resultItem.data("apply-url");
      if (applyUrl) {
        window.open(applyUrl, "_blank");
      } else {
        // Open job page in new tab
        window.open(resultUrl, "_blank");
      }

      // Show success feedback
      this.showActionFeedback(resultItem, "Application page opened");
    }

    handleSendCVAction(resultId, resultItem) {
      // Send CV directly or open job application page
      const resultUrl = resultItem.find(".sffc-result-link").attr("href");
      
      // Add loading state to button
      const button = resultItem.find('[data-action="send_cv"]');
      button.addClass('sffc-loading').find('span').text('Sending...');

      // Use same logic as job opportunities advanced - check for application URL
      $.ajax({
        url: sffc_results.ajax_url || (typeof sffc_job_ajax !== 'undefined' ? sffc_job_ajax.ajax_url : '/wp-admin/admin-ajax.php'),
        type: "POST",
        data: {
          action: "sffc_send_cv_to_recruiter",
          job_id: resultId,
          nonce: sffc_results.nonce || (typeof sffc_job_ajax !== 'undefined' ? sffc_job_ajax.nonce : '')
        },
        success: (response) => {
          button.removeClass('sffc-loading').find('span').text('Send CV');
          
          if (response.success) {
            if (response.data.redirect) {
              // Open application URL in new tab
              window.open(response.data.redirect, '_blank');
            }
            this.showActionFeedback(resultItem, response.data.message || "CV sent successfully!");
          } else {
            // Fallback: open job page directly
            window.open(resultUrl, "_blank");
            this.showActionFeedback(resultItem, "Application page opened");
          }
        },
        error: () => {
          button.removeClass('sffc-loading').find('span').text('Send CV');
          // Fallback: open job page directly
          window.open(resultUrl, "_blank");
          this.showActionFeedback(resultItem, "Application page opened");
        }
      });
    }

    handleIntroduceMeAction(resultId, resultItem) {
      // Send introduction request to Emily via messaging system
      const button = resultItem.find('[data-action="introduce_me"]');
      button.addClass('sffc-loading').find('span').text('Requesting...');

      $.ajax({
        url: sffc_results.ajax_url || (typeof sffc_job_ajax !== 'undefined' ? sffc_job_ajax.ajax_url : '/wp-admin/admin-ajax.php'),
        type: "POST",
        data: {
          action: "sffc_request_introduction",
          job_id: resultId,
          nonce: sffc_results.nonce || (typeof sffc_job_ajax !== 'undefined' ? sffc_job_ajax.nonce : '')
        },
        success: (response) => {
          button.removeClass('sffc-loading').find('span').text('Introduce Me');
          
          if (response.success) {
            this.showActionFeedback(resultItem, response.data || "Introduction request sent! Emily will contact you within 24 hours.", 'success');
            // Disable button temporarily
            button.addClass('sffc-requested').prop('disabled', true);
            setTimeout(() => {
              button.removeClass('sffc-requested').prop('disabled', false);
            }, 5000);
          } else {
            if (response.data && response.data.popup) {
              // Show membership/premium popup
              this.showMembershipModal(response.data.popup);
            } else {
              this.showActionFeedback(resultItem, response.data || "Please try again later.", 'error');
            }
          }
        },
        error: (xhr, status, error) => {
          button.removeClass('sffc-loading').find('span').text('Introduce Me');
          
          // Handle different error scenarios
          if (xhr.status === 403) {
            this.showActionFeedback(resultItem, "Please log in to request an introduction.", 'warning');
          } else {
            this.showActionFeedback(resultItem, "Unable to send introduction request. Please try again.", 'error');
          }
        }
      });
    }

    handleSimilarAction(resultId, resultItem) {
      // Get similar results
      const company = resultItem.data("company");
      const category = resultItem.data("category");

      if (company) {
        // Search for similar jobs at the same company
        const similarQuery = `jobs at ${company}`;
        this.navigateToSearch(similarQuery, "jobs");
      } else if (category) {
        // Search for similar roles
        this.navigateToSearch(category, this.currentMode);
      }
    }

    handleBookmark(button) {
      const resultItem = button.closest(".sffc-result-item");
      const resultId = resultItem.data("result-id");
      const isBookmarked = button.hasClass("bookmarked");

      // Toggle bookmark state
      if (isBookmarked) {
        this.removeBookmark(resultId, button);
      } else {
        this.addBookmark(resultId, button);
      }
    }

    addBookmark(resultId, button) {
      $.ajax({
        url: sffc_results.ajax_url,
        type: "POST",
        data: {
          action: "sffc_add_bookmark",
          result_id: resultId,
          nonce: sffc_results.nonce,
        },
        success: (response) => {
          if (response.success) {
            button.addClass("bookmarked");
            button.attr("title", "Remove bookmark");
            this.showActionFeedback(
              button.closest(".sffc-result-item"),
              "Bookmarked"
            );
          }
        },
      });
    }

    removeBookmark(resultId, button) {
      $.ajax({
        url: sffc_results.ajax_url,
        type: "POST",
        data: {
          action: "sffc_remove_bookmark",
          result_id: resultId,
          nonce: sffc_results.nonce,
        },
        success: (response) => {
          if (response.success) {
            button.removeClass("bookmarked");
            button.attr("title", "Bookmark");
            this.showActionFeedback(
              button.closest(".sffc-result-item"),
              "Bookmark removed"
            );
          }
        },
      });
    }

    handleShare(button) {
      const resultItem = button.closest(".sffc-result-item");
      const resultUrl = resultItem.find(".sffc-result-link").attr("href");
      const resultTitle = resultItem.find(".sffc-result-title").text().trim();

      // Modern Web Share API if available
      if (navigator.share) {
        navigator
          .share({
            title: resultTitle,
            url: resultUrl,
          })
          .catch(() => {
            this.fallbackShare(resultUrl, resultTitle);
          });
      } else {
        this.fallbackShare(resultUrl, resultTitle);
      }
    }

    fallbackShare(url, title) {
      // Copy to clipboard
      navigator.clipboard
        .writeText(url)
        .then(() => {
          this.showGlobalMessage("Link copied to clipboard");
        })
        .catch(() => {
          // Show share modal as final fallback
          this.showShareModal(url, title);
        });
    }

    showShareModal(url, title) {
      const modal = $(`
                <div class="sffc-share-modal-overlay">
                    <div class="sffc-share-modal">
                        <h3>Share this result</h3>
                        <div class="sffc-share-options">
                            <a href="https://twitter.com/intent/tweet?url=${encodeURIComponent(
                              url
                            )}&text=${encodeURIComponent(
        title
      )}" target="_blank" class="sffc-share-btn">
                                <span class="sffc-share-icon">🐦</span> Twitter
                            </a>
                            <a href="https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(
                              url
                            )}" target="_blank" class="sffc-share-btn">
                                <span class="sffc-share-icon">💼</span> LinkedIn
                            </a>
                            <button class="sffc-share-btn" data-action="copy">
                                <span class="sffc-share-icon">📋</span> Copy Link
                            </button>
                        </div>
                        <button class="sffc-modal-close">×</button>
                    </div>
                </div>
            `);

      $("body").append(modal);

      // Handle modal interactions
      modal.on("click", ".sffc-modal-close, .sffc-share-modal-overlay", (e) => {
        if (e.target === e.currentTarget) {
          modal.remove();
        }
      });

      modal.on("click", '[data-action="copy"]', () => {
        navigator.clipboard.writeText(url);
        this.showGlobalMessage("Link copied!");
        modal.remove();
      });
    }

    initQuickActions() {
      // Add hover effects and tooltips for quick actions
      this.container.find(".sffc-quick-action-btn").each(function () {
        const button = $(this);
        const action = button.data("action");

        // Add hover animations
        button.on("mouseenter", function () {
          $(this).addClass("sffc-action-hover");
        });

        button.on("mouseleave", function () {
          $(this).removeClass("sffc-action-hover");
        });
      });
    }

    initInsights() {
      // Add interactive features to insights
      this.container.find(".sffc-insight-item").each(function () {
        const insight = $(this);
        const type = insight.data("insight-type");

        // Add click handlers for expandable insights
        if (type === "similar" || type === "company") {
          insight.addClass("sffc-insight-clickable");
          insight.on("click", () => {
            // Expand insight or navigate to detailed view
            // Implementation depends on insight type
          });
        }
      });
    }

    trackResultClick(link) {
      const resultItem = link.closest(".sffc-result-item");
      const resultId = resultItem.data("result-id");
      const resultType = resultItem.data("result-type");
      const position = resultItem.index() + 1;

      // Track click analytics
      this.trackEvent("result_click", {
        result_id: resultId,
        result_type: resultType,
        position: position,
        query: this.currentQuery,
        mode: this.currentMode,
        page: this.currentPage,
      });

      // Add visual feedback
      link.addClass("sffc-clicked");
    }

    trackSearchMetrics() {
      // Track search performance and user behavior
      const searchTime = this.container.find(".sffc-search-time strong").text();
      const resultCount = this.container
        .find(".sffc-results-count strong")
        .text();

      this.trackEvent("search_performed", {
        query: this.currentQuery,
        mode: this.currentMode,
        result_count: resultCount,
        search_time: searchTime,
      });
    }

    handleKeyboardShortcuts(e) {
      // Google-style keyboard shortcuts
      if (e.ctrlKey || e.metaKey) {
        switch (e.key) {
          case "k":
            e.preventDefault();
            this.focusSearchInput();
            break;
          case "/":
            e.preventDefault();
            this.focusSearchInput();
            break;
        }
      }
    }

    handlePagination(button) {
      const page = parseInt(button.data("page"), 10);
      if (!page || page < 1) return;

      const isNext = button.hasClass("sffc-next-btn");
      const isPrev = button.hasClass("sffc-prev-btn");

      // Track pagination usage for analytics
      this.trackEvent("pagination_click", {
        page: page,
        direction: isNext ? "next" : isPrev ? "prev" : "direct",
        query: this.currentQuery,
        mode: this.currentMode,
      });

      // Update current page and load results via AJAX
      this.currentPage = page;
      this.showLoadingState();
      this.loadSearchResults(this.currentQuery, this.currentMode, page);

      // Scroll to top of results
      const resultsTop = this.container.find(".sffc-results-list").offset();
      if (resultsTop) {
        $("html, body").animate({ scrollTop: resultsTop.top - 100 }, 300);
      }
    }

    focusSearchInput() {
      const searchInput = this.container.find(".sffc-search-input");
      if (searchInput.length) {
        searchInput.focus().select();
      }
    }

    navigateToSearch(query, mode) {
      const targetMode = this.normalizeMode(mode || this.currentMode);
      this.currentQuery = query;
      this.currentMode = targetMode;
      this.syncActiveModeTabs(targetMode);

      // Update input field
      const input = this.container.find(".sffc-search-input");
      if (input.length) {
        input.val(query);
        this.handleSearchInput(input);
      }

      // Use AJAX to load results instead of generating indexable URLs
      this.showLoadingState();
      this.loadSearchResults(query, targetMode);
    }

    handleRelatedSearch(button) {
      const query = button.data("query");
      const mode = button.data("mode");
      const filter = button.data("filter");

      if (!query) return;

      // Track the related search click
      this.trackEvent("related_search_click", {
        query: query,
        mode: mode,
        filter: filter,
        original_query: this.currentQuery,
      });

      // Update filter if specified
      if (filter) {
        this.currentFilters.primary = filter;
      }

      // Navigate to the search using AJAX
      this.navigateToSearch(query, mode);
    }

    showLoadingState() {
      this.container
        .find(".sffc-results-list")
        .addClass("sffc-results-loading");
    }

    hideLoadingState() {
      this.container
        .find(".sffc-results-list")
        .removeClass("sffc-results-loading");
    }

    showActionFeedback(element, message, type = 'info') {
      const feedback = $(`<div class="sffc-action-feedback sffc-feedback-${type}">${message}</div>`);
      element.append(feedback);

      setTimeout(() => {
        feedback.fadeOut(() => feedback.remove());
      }, 3000);
    }

    showMembershipModal(popupHtml) {
      // Remove existing modal if any
      $('.sffc-membership-modal').remove();
      
      // Create modal wrapper
      const modal = $(`
        <div class="sffc-membership-modal">
          <div class="sffc-modal-backdrop"></div>
          <div class="sffc-modal-content">
            <button class="sffc-modal-close">&times;</button>
            ${popupHtml}
          </div>
        </div>
      `);
      
      // Add to page
      $('body').append(modal);
      
      // Bind close events
      modal.find('.sffc-modal-close, .sffc-modal-backdrop').on('click', function() {
        modal.fadeOut(300, function() {
          $(this).remove();
        });
      });
      
      // Show modal with animation
      modal.fadeIn(300);
      
      // Close on escape key
      $(document).on('keydown.sffc-modal', function(e) {
        if (e.keyCode === 27) { // ESC key
          modal.fadeOut(300, function() {
            $(this).remove();
          });
          $(document).off('keydown.sffc-modal');
        }
      });
    }

    showErrorMessage(message) {
      this.showGlobalMessage(`⚠️ ${message}`);
    }

    showGlobalMessage(message) {
      const toast = $(`
                <div class="sffc-toast-message">
                    ${message}
                </div>
            `);

      $("body").append(toast);

      setTimeout(() => {
        toast.addClass("sffc-toast-show");
      }, 100);

      setTimeout(() => {
        toast.removeClass("sffc-toast-show");
        setTimeout(() => toast.remove(), 300);
      }, 3000);
    }

    updateResultsDisplay(html) {
      // The AJAX response includes wrapped results list and pagination
      const $resultsMain = this.container.find(".sffc-results-main");
      const $existingList = this.container.find(".sffc-results-list");
      const $existingPagination = this.container.find(".sffc-pagination");

      // Parse the response HTML
      const $html = $("<div>").html(html);
      const $newList = $html.find(".sffc-results-list");
      const $newPagination = $html.find(".sffc-pagination");

      if ($newList.length) {
        // Replace results list content
        $existingList.html($newList.html());
      } else {
        // No results wrapper - it's either no results or raw content
        $existingList.html(html);
      }

      // Handle pagination
      if ($newPagination.length) {
        if ($existingPagination.length) {
          $existingPagination.replaceWith($newPagination);
        } else {
          $existingList.after($newPagination);
        }
      } else {
        // No pagination in response, remove existing
        $existingPagination.remove();
      }

      // Reinitialize interactive elements
      this.initQuickActions();
      this.initInsights();
      this.populateAdvancedFilters();
    }

    getFiltersFromUrl() {
      const params = new URLSearchParams(window.location.search);
      const filters = {
        primary: params.get("filter") || "all",
        location: params.get("location") || "",
        salary_min: params.get("salary_min") || "",
        salary_max: params.get("salary_max") || "",
        industries: params.getAll("industries[]") || [],
        roles: params.getAll("roles[]") || [],
        functions: params.getAll("functions[]") || [],
        regions: params.getAll("regions[]") || [],
      };

      ["industries", "roles", "functions", "regions"].forEach((key) => {
        if (!filters[key].length && params.get(key)) {
          filters[key] = [params.get(key)];
        }
      });

      return filters;
    }

    updateUrlWithFilters(filters) {
      // Store filters in state without creating indexable URL parameters
      window.history.replaceState(
        {
          filters: filters,
          query: this.currentQuery,
          mode: this.currentMode,
        },
        "",
        window.location.pathname
      );
    }

    toggleAdvancedFilter(button) {
      const wrapper = button.closest(".sffc-advanced-filter");
      const panel = wrapper.find(".sffc-advanced-filter-panel");
      const expanded = button.attr("aria-expanded") === "true";
      this.closeAllAdvancedFilters();
      if (!expanded) {
        button.attr("aria-expanded", "true");
        wrapper.addClass("is-open");
        panel.attr("hidden", false);
      }
    }

    closeAllAdvancedFilters() {
      this.container.find(".sffc-advanced-filter-toggle").attr("aria-expanded", "false");
      this.container.find(".sffc-advanced-filter").removeClass("is-open");
      this.container.find(".sffc-advanced-filter-panel").attr("hidden", true);
    }

    handleDocumentClick(event) {
      const target = $(event.target);
      if (
        !target.closest(".sffc-advanced-filter").length &&
        !target.hasClass("sffc-advanced-filter-toggle")
      ) {
        this.closeAllAdvancedFilters();
      }
    }

    applyAdvancedFilters(form, options = {}) {
      if (!form || !form.length) {
        return;
      }

      const values = this.serializeFilterForm(form);
      Object.entries(values).forEach(([key, value]) => {
        if (Array.isArray(value)) {
          if (value.length) {
            this.currentFilters[key] = value;
          } else {
            delete this.currentFilters[key];
          }
        } else if (value) {
          this.currentFilters[key] = value;
        } else {
          delete this.currentFilters[key];
        }
      });

      if (!options.silent) {
        this.updateUrlWithFilters(this.currentFilters);
        this.showLoadingState();
        this.loadFilteredResults(this.currentFilters.primary || "all", this.currentFilters);
      } else {
        this.updateUrlWithFilters(this.currentFilters);
      }

      this.closeAllAdvancedFilters();
    }

    serializeFilterForm(form) {
      const data = new FormData(form[0]);
      const values = {};

      data.forEach((value, key) => {
        if (key.endsWith("[]")) {
          const cleanKey = key.replace("[]", "");
          values[cleanKey] = values[cleanKey] || [];
          if (value) {
            values[cleanKey].push(value);
          }
        } else {
          values[key] = typeof value === "string" ? value.trim() : value;
        }
      });

      return values;
    }

    resetAdvancedForm(form) {
      if (!form || !form.length) {
        return;
      }

      form
        .find("input[type='text'], input[type='number']")
        .val("");
      form
        .find("input[type='checkbox']")
        .prop("checked", false);
    }

    clearAllFilters() {
      this.currentFilters = { primary: "all" };
      this.updateUrlWithFilters(this.currentFilters);
      this.container
        .find(".sffc-advanced-filter-form")
        .each((_, form) => this.resetAdvancedForm($(form)));
      this.showLoadingState();
      this.loadFilteredResults("all", this.currentFilters);
      this.closeAllAdvancedFilters();
    }

    populateAdvancedFilters() {
      const filters = this.currentFilters || {};
      const container = this.container;

      container
        .find("form[data-filter-form='location'] input[name='location']")
        .val(filters.location || "");

      container
        .find("form[data-filter-form='salary'] input[name='salary_min']")
        .val(filters.salary_min || "");

      container
        .find("form[data-filter-form='salary'] input[name='salary_max']")
        .val(filters.salary_max || "");

      ["industries", "roles", "functions", "regions"].forEach((key) => {
        const selected = filters[key] || [];
        const wrapper = container.find(`.sffc-advanced-filter[data-filter-key='${key}']`);
        wrapper
          .find(`form[data-filter-form='${key}'] input[type='checkbox']`)
          .each((_, checkbox) => {
            const value = $(checkbox).val();
            $(checkbox).prop("checked", selected.includes(value));
          });
        const badge = wrapper.find(".sffc-advanced-selected-count");
        if (!selected.length) {
          badge.remove();
        } else if (badge.length) {
          badge.text(selected.length);
        } else {
          wrapper
            .find(".sffc-advanced-filter-toggle")
            .append(`<span class="sffc-advanced-selected-count">${selected.length}</span>`);
        }
      });
    }

    getCurrentPage() {
      const rawValue =
        this.getUrlParameter(this.pageParam) || this.getUrlParameter("page");
      const parsed = parseInt(rawValue, 10);

      if (Number.isNaN(parsed) || parsed < 1) {
        return 1;
      }

      return parsed;
    }

    getUrlParameter(name) {
      const urlParams = new URLSearchParams(window.location.search);
      return urlParams.get(name);
    }

    normalizeMode(mode) {
      if (!mode) {
        return "jobs";
      }

      if (typeof mode === "string" && mode.toLowerCase() === "news") {
        return "insights";
      }

      return mode;
    }

    syncActiveModeTabs(targetMode = this.currentMode) {
      if (!targetMode) {
        return;
      }

      this.container
        .find(".sffc-mode-tab, .sffc-mobile-mode-btn")
        .each((_, element) => {
          const $el = $(element);
          const isActive = $el.data("mode") === targetMode;
          $el.toggleClass("active", isActive);
          if ($el.attr("aria-pressed") !== undefined) {
            $el.attr("aria-pressed", isActive ? "true" : "false");
          }
        });

      this.container.find(".sffc-search-input").each((_, element) => {
        const $input = $(element);
        $input.data("mode", targetMode);
      });
    }

    trackEvent(event, data = {}) {
      // Analytics tracking
      if (typeof gtag !== "undefined") {
        gtag("event", event, {
          event_category: "PE Search Results",
          ...data,
        });
      }

      // Custom analytics can be added here
      if (typeof sffc_analytics !== "undefined") {
        sffc_analytics.track(event, data);
      }
    }
  }

  // Initialize search results when document is ready
  $(document).ready(function () {
    $(".sffc-pe-results-container").each(function () {
      new PESearchResults(this);
    });
  });

  // Add CSS for dynamic elements
  const dynamicCSS = `
        .sffc-action-hover {
            transform: scale(1.05);
        }
        
        .sffc-insight-clickable {
            cursor: pointer;
        }
        
        .sffc-insight-clickable:hover {
            background: #f0f0f0;
        }
        
        .sffc-clicked {
            opacity: 0.7;
        }
        
        .sffc-action-feedback {
            position: absolute;
            top: -30px;
            left: 50%;
            transform: translateX(-50%);
            background: #333;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            white-space: nowrap;
            z-index: 1000;
        }
        
        .sffc-share-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
        }
        
        .sffc-share-modal {
            background: white;
            padding: 24px;
            border-radius: 8px;
            max-width: 400px;
            width: 90%;
            position: relative;
        }
        
        .sffc-share-options {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 16px;
        }
        
        .sffc-share-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
            background: white;
            cursor: pointer;
        }
        
        .sffc-share-btn:hover {
            background: #f5f5f5;
        }
        
        .sffc-modal-close {
            position: absolute;
            top: 8px;
            right: 12px;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }
        
        .sffc-toast-message {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: #333;
            color: white;
            padding: 12px 24px;
            border-radius: 4px;
            z-index: 10000;
            transition: transform 0.3s ease;
            opacity: 0;
        }
        
        .sffc-toast-show {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
        }
    `;

  // Inject CSS
  $("<style>").text(dynamicCSS).appendTo("head");

  /* =========================================================
   OPTIMIZED SCROLL BEHAVIOR - NO FLICKERING
   Consolidated scroll handler for header and CTA
========================================================= */
  $(function () {
    const header = document.querySelector(".sffc-results-header");
    const cta = document.querySelector(".sffc-floating-cta");

    let lastScrollY = window.scrollY;
    let lastDirection = "up";
    let ticking = false;
    const threshold = 8; // ignore small scroll jitters
    const ctaHideOffset = 200;

    function optimizedScrollHandler() {
      const currentY = window.scrollY;
      const delta = currentY - lastScrollY;
      const direction = delta > threshold ? "down" : delta < -threshold ? "up" : lastDirection;

      if (!header) return;

      // Header scroll states
      header.classList.toggle("scrolled", currentY > 40);
      
      // Header collapse/expand behavior
      if (direction === "down" && currentY > 100) {
        header.classList.add("collapsed");
      } else if (direction === "up") {
        header.classList.remove("collapsed");
      }

      // CTA hide/show behavior
      if (cta) {
        if (direction === "down" && currentY > ctaHideOffset) {
          cta.classList.add("hidden");
        } else if (direction === "up" || currentY < ctaHideOffset) {
          cta.classList.remove("hidden");
        }
      }

      lastScrollY = currentY;
      lastDirection = direction;
      ticking = false;
    }

    // Single optimized scroll listener with requestAnimationFrame throttling
    window.addEventListener("scroll", () => {
      if (!ticking) {
        window.requestAnimationFrame(optimizedScrollHandler);
        ticking = true;
      }
    }, { passive: true });
  });

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) entry.target.classList.add("is-visible");
      });
    },
    { threshold: 0.15 }
  );

  document
    .querySelectorAll(".sffc-scroll-reveal, .sffc-scroll-accent")
    .forEach((el) => observer.observe(el));
  /* --- ✅ End cinematic scroll snippet --- */

  /* =========================================================
   PHASE 9 — INTELLIGENT LIGHTING & MOTION INTEGRATION
   Subtle ambient highlight + scroll reveal triggers
========================================================= */
  $(document).ready(function () {
    // Ambient cursor lighting on cards
    $(document).on("mousemove", function (e) {
      $(".sffc-result-item, .sffc-rail-card").each(function () {
        const $card = $(this);
        const rect = this.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        const rx = (x / rect.width) * 100;
        const ry = (y / rect.height) * 100;
        $card.css("--light-x", `${rx}%`);
        $card.css("--light-y", `${ry}%`);
      });
    });

    // Apply light gradient dynamically
    $("<style>")
      .text(
        `
        .sffc-result-item::before,
        .sffc-rail-card::before {
            content: "";
            position: absolute;
            inset: 0;
            background: radial-gradient(
                circle at var(--light-x, 50%) var(--light-y, 50%),
                rgba(14,62,51,0.08) 0%,
                rgba(14,62,51,0.02) 40%,
                transparent 80%
            );
            opacity: 0;
            transition: opacity 0.4s ease;
            border-radius: inherit;
            pointer-events: none;
        }
        .sffc-result-item:hover::before,
        .sffc-rail-card:hover::before {
            opacity: 1;
        }
    `
      )
      .appendTo("head");

    // IntersectionObserver for scroll reveals
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add("is-visible");
          }
        });
      },
      { threshold: 0.1 }
    );

    $(".sffc-scroll-reveal").each(function () {
      observer.observe(this);
    });

    // === Scroll handler consolidated above for better performance ===
  });
})(jQuery);
