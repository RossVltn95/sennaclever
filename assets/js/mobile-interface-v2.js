/**
 * Mobile Interface Controller V2
 * Properly integrates with desktop structure without duplication
 * Retains glassmorphism design and native app feel
 */

(function (jQuery) {
  "use strict";

  // Ensure $ is available
  var $ = jQuery;

  class MobileInterfaceV2 {
    constructor() {
      this.currentMode = "browse"; // 'browse' or 'chat'
      this.isTransitioning = false;
      this.peFilterSidebar = null;
      this.sennaConversation = null;
      this.lastScrollTop = 0;
      this.scrollThreshold = 10; // Lower threshold for more responsive scrolling
      this.isInputHidden = false;
      this.lastSearchResults = [];

      this.init();
    }

    init() {
      // Only initialize on mobile
      if (!this.shouldInitialize()) {
        return;
      }

      // Wait for desktop elements to be ready
      this.waitForDesktopElements();
    }

    shouldInitialize() {
      const isMobileView = window.matchMedia("(max-width: 768px)").matches;
      const forceMobile = window.location.search.includes("mobile=1");
      return isMobileView || forceMobile;
    }

    waitForDesktopElements() {
      let checkCount = 0;
      const checkElements = setInterval(() => {
        checkCount++;

        // Look for PE filter sidebar (created by desktop JS)
        const $sidebar = $(".pe-filter-sidebar");
        const $sennaConv = $(".sffc-senna-conversation");

        if ($sidebar.length && $sennaConv.length) {
          clearInterval(checkElements);
          this.peFilterSidebar = $sidebar;
          this.sennaConversation = $sennaConv;
          this.transformForMobile();
        } else if (checkCount > 40) {
          // Wait up to 10 seconds
          clearInterval(checkElements);
          // Even if PE sidebar doesn't exist, transform what we have
          this.sennaConversation = $(".sffc-senna-conversation");
          this.transformForMobile();
        }
      }, 250);
    }

    transformForMobile() {
      // Add mobile class to body
      $("body").addClass("mobile-interface-v2");

      // Hide desktop UI chrome only
      this.hideDesktopUIChrome();

      // Create mobile UI elements
      this.createMobileHeader();
      this.createModePills();
      this.createSearchPanel();
      this.createProfilePanel();
      this.createBottomSheet();
      this.createFloatingActionButton();

      // Move and restyle desktop content for mobile
      this.restyleForMobile();

      // Bind events
      this.bindEvents();
      this.initializeGestures();

      // Re-bind ask-senna-btn handler with delay to ensure it takes precedence
      setTimeout(() => {
        this.rebindAskSennaHandler();
      }, 500);

      // Initialize in CHAT mode (default)
      this.switchMode("chat");

      // Show welcome message on first load if no messages exist
      setTimeout(() => {
        const hasMessages = $(".sffc-message").length > 0;
        if (
          !hasMessages &&
          window.sennaConversational &&
          !window.sennaConversational.welcomeMessageShown
        ) {
          window.sennaConversational.presentInitialJobs();
        }
      }, 500);
    }

    createMobileHeader() {
      // Replace desktop header with mobile status bar
      const mobileHeader = `
                <div class="mobile-status-bar">
                    <div class="mobile-brand">
                        <span class="brand-s">S</span>
                        <span class="brand-dot">•</span>
                        <span class="brand-text">senna</span>
                    </div>
                    <div class="status-time"></div>
                    <div class="mobile-header-actions">
                        <button class="mobile-search-btn" id="mobile-search-btn">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"></circle>
                                <path d="m21 21-4.35-4.35"></path>
                            </svg>
                        </button>
                        <button class="mobile-profile-btn" id="mobile-profile-btn">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        </button>
                    </div>
                </div>
            `;

      // Insert at top of wrapper
      $(".sffc-opportunities-wrapper").prepend(mobileHeader);

      // Update time
      this.updateTime();
      setInterval(() => this.updateTime(), 60000);
    }

    createModePills() {
      const modePills = `
                <div class="mode-pills">
                    <div class="mode-pill" data-mode="browse">
                        <span class="pill-icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="3" width="7" height="7"/>
                                <rect x="14" y="3" width="7" height="7"/>
                                <rect x="3" y="14" width="7" height="7"/>
                                <rect x="14" y="14" width="7" height="7"/>
                            </svg>
                        </span>
                        <span class="pill-text">Browse</span>
                    </div>
                    <div class="mode-pill active" data-mode="chat">
                        <span class="pill-icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                            </svg>
                        </span>
                        <span class="pill-text">Chat</span>
                    </div>
                </div>
            `;

      // Insert after status bar
      $(".mobile-status-bar").after(modePills);

      // Show job count from available data
      // This will be populated from AJAX calls, not desktop elements
    }

    hideDesktopUIChrome() {
      // Hide only desktop UI chrome, not content
      // Saved jobs panel hidden on mobile for space
      $(".sffc-opp-header").hide();
      $(".sffc-message-search").hide();
      $(".sffc-menu-toggle").hide();
      $(".sffc-stage-menu").hide();
      $(".conversation-stage").hide();
      $(".sffc-user-header").hide();
      $(".sffc-profile-actions").hide();
      $(".sffc-brand").hide();
      $(".sffc-edit-profile-btn").hide();
      $("#currency-selector-container").hide();
      $(".stage-indicators").hide();
    }

    restyleForMobile() {
      // Make containers visible and restyle them
      $(".sffc-main-container").show().addClass("mobile-restyled");
      $(".sffc-conversational-view").show().addClass("mobile-restyled");

      // Ensure PE filter container is visible
      $("#pe-filter-container").show();

      // Restyle PE filter sidebar for mobile (hidden initially, shown in browse mode)
      $(".pe-filter-sidebar").addClass("mobile-pe-sidebar");

      // Make MENA Careers conversation visible and restyle
      $(".sffc-senna-conversation")
        .show()
        .addClass("mobile-senna-conversation");

      // Ensure messages container is visible
      $(".senna-messages").show();

      // Removed mobile-unified-card and mobile-card-style class additions
      // Let vogue cards use their own styles

      // Make autocomplete visible and mobile-friendly
      $(".sffc-autocomplete-container").show().addClass("mobile-autocomplete");

      // Ensure autocomplete wrapper is visible
      $(".sffc-autocomplete-wrapper").show();

      // Make input visible
      $("#senna-input").show();

      // Watch for new cards added dynamically
      this.observeNewCards();
    }

    observeNewCards() {
      // Observer for new cards - removed conflicting class additions
      const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
          mutation.addedNodes.forEach((node) => {
            if (node.nodeType === 1) {
              // Element node
              // Check if this is a card or contains cards
              const $node = $(node);
              const cardSelectors =
                ".question-card, .pe-job-card, .senna-job-card, .job-card, .job-card-vogue, .job-card-simplified, .sffc-match-card";

              // Removed automatic class additions
              // Let cards use their original styles
            }
          });
        });
      });

      // Observe the main containers for changes
      const containers = document.querySelectorAll(
        ".sffc-opportunities-wrapper, .senna-messages, .pe-filter-sidebar"
      );
      containers.forEach((container) => {
        if (container) {
          observer.observe(container, {
            childList: true,
            subtree: true,
          });
        }
      });

      // Store observer for cleanup
      this.cardObserver = observer;
    }

    // Removed - no longer cloning, using actual elements

    createSearchPanel() {
      const searchPanel = `
                <div class="mobile-search-panel" id="mobile-search-panel">
                    <div class="search-panel-header">
                        <button class="search-panel-close" id="search-panel-close">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                        <h3>Search Messages</h3>
                    </div>
                    <div class="search-panel-content">
                        <div class="mobile-search-input-wrapper">
                            <input type="text" 
                                   class="mobile-search-input" 
                                   id="mobile-message-search-input" 
                                   placeholder="Search conversations..."
                                   autocomplete="off">
                            <button class="mobile-search-clear" id="mobile-search-clear" style="display: none;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="15" y1="9" x2="9" y2="15"></line>
                                    <line x1="9" y1="9" x2="15" y2="15"></line>
                                </svg>
                            </button>
                        </div>
                        <div class="search-filters">
                            <button class="search-filter-btn active" data-filter="all">All</button>
                            <button class="search-filter-btn" data-filter="user">Your Messages</button>
                            <button class="search-filter-btn" data-filter="senna">MENA Careers Replies</button>
                            <button class="search-filter-btn" data-filter="jobs">Jobs</button>
                        </div>
                        <div class="search-results" id="mobile-search-results">
                            <div class="search-placeholder">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" opacity="0.3">
                                    <circle cx="11" cy="11" r="8"></circle>
                                    <path d="m21 21-4.35-4.35"></path>
                                </svg>
                                <p>Start typing to search messages</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;

      $("body").append(searchPanel);
    }

    createProfilePanel() {
      const profilePanel = `
                <div class="mobile-profile-panel" id="mobile-profile-panel">
                    <div class="profile-panel-header">
                        <button class="profile-panel-close">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                        <h3>Profile</h3>
                    </div>
                    <div class="profile-panel-content">
                        <div class="profile-info">
                            <div class="profile-avatar">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                </svg>
                            </div>
                            <h4 class="profile-name">Guest User</h4>
                            <p class="profile-status">Not logged in</p>
                        </div>
                        <div class="profile-actions">
                            <button class="member-btn" id="mobile-member-btn">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="8.5" cy="7" r="4"></circle>
                                    <line x1="20" y1="8" x2="20" y2="14"></line>
                                    <line x1="23" y1="11" x2="17" y2="11"></line>
                                </svg>
                                <span>Become A Member</span>
                            </button>
                            <button class="profile-edit-btn">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                </svg>
                                <span>Edit Profile</span>
                            </button>
                        </div>
                    </div>
                </div>
            `;

      $("body").append(profilePanel);
    }

    createBottomSheet() {
      const bottomSheet = `
                <div class="bottom-sheet" id="mobile-bottom-sheet">
                    <div class="sheet-handle"></div>
                    <div class="sheet-content">
                        <h3>Lesson Shortcuts</h3>
                        <div class="quick-action-grid" id="mobile-quick-actions">
                            <div class="quick-action" data-action="lesson-lbo">
                                <div class="action-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M3 12h18"></path>
                                        <path d="M3 6h12"></path>
                                        <path d="M3 18h6"></path>
                                    </svg>
                                </div>
                                <div class="action-label">LBO Modelling</div>
                            </div>
                            <div class="quick-action" data-action="lesson-dcf">
                                <div class="action-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M4 19h16"></path>
                                        <path d="M4 5h16"></path>
                                        <path d="M8 9v6"></path>
                                        <path d="M12 7v10"></path>
                                        <path d="M16 11v4"></path>
                                    </svg>
                                </div>
                                <div class="action-label">DCF Drill</div>
                            </div>
                            <div class="quick-action" data-action="lesson-comps">
                                <div class="action-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="3" y="4" width="18" height="16" rx="2"></rect>
                                        <path d="M3 10h18"></path>
                                        <path d="M8 14h.01"></path>
                                        <path d="M12 14h.01"></path>
                                        <path d="M16 14h.01"></path>
                                    </svg>
                                </div>
                                <div class="action-label">Comps Analysis</div>
                            </div>
                            <div class="quick-action" data-action="lesson-carry">
                                <div class="action-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M4 18h16"></path>
                                        <path d="M6 6h12v8H6z"></path>
                                        <path d="M10 10h4"></path>
                                    </svg>
                                </div>
                                <div class="action-label">Carry & Waterfall</div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

      $("body").append(bottomSheet);
    }

    createFloatingActionButton() {
      const fab = `
                <div class="floating-action-button collapsed" id="mobile-fab" title="Quick actions">
                    <!-- Plus icon - more prominent -->
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" stroke="none">
                        <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                    </svg>
                    <span class="fab-text">Lessons</span>
                </div>
            `;

      $("body").append(fab);

      // Bind FAB click - use delegation and ensure it works
      const self = this;
      $(document).on(
        "click.mobileFab touchend.mobileFab",
        "#mobile-fab",
        function (e) {
          e.preventDefault();
          e.stopPropagation();

          // Prevent double firing on touch devices
          if (e.type === "touchend" && e.originalEvent.touches.length > 0) {
            return;
          }

          // Toggle bottom sheet
          if ($("#mobile-bottom-sheet").hasClass("visible")) {
            self.hideBottomSheet();
          } else {
            self.showBottomSheet();
          }

          return false;
        }
      );

      // Optional: Expand on long press (removed touchstart to avoid conflicts)
      let expandTimeout;
      $(document)
        .on("mouseenter", "#mobile-fab", function () {
          expandTimeout = setTimeout(() => {
            $(this).addClass("expanded").removeClass("collapsed");
          }, 300);
        })
        .on("mouseleave", "#mobile-fab", function () {
          clearTimeout(expandTimeout);
          $(this).addClass("collapsed").removeClass("expanded");
        });
    }

    rebindAskSennaHandler() {
      // Don't bind here - the handler is already defined in bindEvents()
      // This is called to ensure handlers are active after dynamic content loads
    }

    bindEvents() {
      const self = this;

      // Scroll handler for hiding/showing input in chat mode
      this.initScrollHandler();

      // Mode pills
      $(document).on("click", ".mode-pill", function () {
        const mode = $(this).data("mode");
        self.switchMode(mode);
      });

      // Search button
      $(document).on("click", "#mobile-search-btn", function () {
        self.showSearchPanel();
      });

      // Search panel close
      $(document).on("click", "#search-panel-close", function () {
        self.hideSearchPanel();
      });

      // Search input handling
      $(document).on("input", "#mobile-message-search-input", function () {
        const query = $(this).val().trim();
        self.performSearch(query);

        // Show/hide clear button
        if (query) {
          $("#mobile-search-clear").show();
        } else {
          $("#mobile-search-clear").hide();
          self.showSearchPlaceholder();
        }
      });

      // Clear search
      $(document).on("click", "#mobile-search-clear", function () {
        $("#mobile-message-search-input").val("").trigger("input");
      });

      // Search filter buttons
      $(document).on("click", ".search-filter-btn", function () {
        $(".search-filter-btn").removeClass("active");
        $(this).addClass("active");
        const filter = $(this).data("filter");
        self.currentSearchFilter = filter;

        // Re-run search with new filter
        const query = $("#mobile-message-search-input").val().trim();
        if (query) {
          self.performSearch(query);
        }
      });

      // Click on search result to scroll to message
      $(document).on("click", ".search-result-item", function () {
        const messageId = $(this).data("message-id");
        self.scrollToMessage(messageId);
        self.hideSearchPanel();

        // Switch to chat mode if not already
        if (self.currentMode !== "chat") {
          self.switchMode("chat");
        }
      });

      // Profile button
      $(document).on("click", "#mobile-profile-btn", function () {
        self.showProfilePanel();
      });

      // Profile panel close
      $(document).on("click", ".profile-panel-close", function () {
        self.hideProfilePanel();
      });

      // Close profile panel on overlay click
      $(document).on("click", function (e) {
        if ($("body").hasClass("profile-panel-open")) {
          // Check if click is on the overlay (body::after pseudo-element area)
          if (
            !$(e.target).closest(".mobile-profile-panel, #mobile-profile-btn")
              .length
          ) {
            self.hideProfilePanel();
          }
        }
      });

      // Member button
      $(document).on("click", "#mobile-member-btn", function () {
        const registrationUrl =
          window.sffc_ajax?.registration_url ||
          "https://joinsenna.com/memberships/";
        window.location.href = registrationUrl;
      });

      // Edit profile button
      $(document).on("click", ".profile-edit-btn", function () {
        if (typeof openProfileBuilder === "function") {
          openProfileBuilder();
          self.hideProfilePanel();
        }
      });

      // Bottom sheet close on outside click (FAB handler is already bound in createFloatingActionButton)
      $(document).on("click", function (e) {
        if ($(e.target).is("#mobile-bottom-sheet")) {
          self.hideBottomSheet();
        }
      });

      // Quick actions
      $(document).on("click", ".quick-action", function () {
        const action = $(this).data("action");
        self.handleQuickAction(action);
        self.hideBottomSheet();
      });

      // Ask MENA Careers button - switch to chat and send the question
      $(document).on("click", ".ask-senna-btn", function (e) {
        e.preventDefault();
        e.stopPropagation();

        // Get the card and extract all content
        const $card = $(this).closest(
          ".question-card, .job-card-vogue, .sffc-match-card"
        );

        // Extract card components
        const category = $card.find(".question-category").text().trim();
        const title = $card.find(".question-title").text().trim();
        const preview = $card.find(".question-preview").text().trim();

        // Extract bullet points if they exist
        const bulletPoints = [];
        $card
          .find(".question-bullets li, .filter-bullets li")
          .each(function () {
            bulletPoints.push($(this).text().trim());
          });

        // Build an intelligent prompt based on card content
        let question = "";

        if (category && title && preview) {
          // Create a contextual prompt based on the card type
          if (
            category.includes("COMPENSATION") ||
            category.includes("SALARY")
          ) {
            question = `Explain ${title}. ${preview} Please provide detailed insights on compensation structures, ranges, and what candidates should expect.`;
          } else if (
            category.includes("MARKET INTELLIGENCE") ||
            category.includes("INSIDER")
          ) {
            question = `I want to understand: ${title}. Context: ${preview} Please provide detailed market intelligence and insider insights.`;
          } else if (
            category.includes("REGIONAL") ||
            category.includes("LOCATION")
          ) {
            question = `Tell me about ${title}. ${preview} Include specific opportunities and market dynamics.`;
          } else if (
            category.includes("CAREER") ||
            category.includes("STRATEGY")
          ) {
            question = `Guide me on: ${title}. ${preview} Provide actionable career strategy advice.`;
          } else if (
            category.includes("TREND") ||
            category.includes("INDUSTRY")
          ) {
            question = `Analyze this trend: ${title}. ${preview} What does this mean for job seekers?`;
          } else {
            // Generic template for other categories
            question = `I want to learn about: ${title}. ${preview} Please provide detailed insights as ${category}.`;
          }

          // Add bullet points if they exist
          if (bulletPoints.length > 0) {
            question += ` Key areas to cover: ${bulletPoints.join(", ")}.`;
          }
        } else if (title) {
          // Fallback if we only have title
          question = `Tell me more about: ${title}`;
          if (preview) {
            question += `. ${preview}`;
          }
        } else {
          // Last resort fallback
          question = "Tell me more about this opportunity";
        }

        if (!question) {
          question = "Tell me more about private equity opportunities";
        }

        // Switch to chat mode first
        self.switchMode("chat");

        // Check if this is the first interaction - show welcome message if so
        setTimeout(() => {
          const hasMessages = $(".sffc-message").length > 0;

          if (
            !hasMessages &&
            window.sennaConversational &&
            !window.sennaConversational.welcomeMessageShown
          ) {
            // First interaction - show personalized welcome
            window.sennaConversational.presentInitialJobs();

            // Wait for welcome to display, then send the question
            setTimeout(() => {
              if (window.SennaChat && window.SennaChat.send) {
                window.SennaChat.send(question);
              } else if ($("#senna-input").length) {
                $("#senna-input").val(question);
                const sendBtn = $(
                  ".sffc-send-btn, #senna-send-btn, #senna-send"
                );
                if (sendBtn.length) {
                  sendBtn.click();
                }
              }
            }, 500);
          } else {
            // Already has messages, just send the question
            if (window.SennaChat && window.SennaChat.send) {
              window.SennaChat.send(question);
            } else if ($("#senna-input").length) {
              $("#senna-input").val(question);
              const sendBtn = $(".sffc-send-btn, #senna-send-btn, #senna-send");
              if (sendBtn.length) {
                sendBtn.click();
              }
            }
          }
        }, 350);
      });
    }

    // Removed bindCardEvents and sendMobileMessage - using desktop chat directly

    initializeGestures() {
      const self = this;
      let startX = 0;
      let currentX = 0;
      let isDragging = false;

      // Swipe between modes
      $(document).on(
        "touchstart",
        ".mobile-browse-panel, .mobile-chat-panel",
        function (e) {
          if (self.isTransitioning) return;
          startX = e.touches[0].clientX;
          isDragging = true;
        }
      );

      $(document).on("touchmove", function (e) {
        if (!isDragging || self.isTransitioning) return;
        currentX = e.touches[0].clientX;
      });

      $(document).on("touchend", function (e) {
        if (!isDragging) return;
        isDragging = false;

        const diff = currentX - startX;
        const threshold = 50;

        if (Math.abs(diff) > threshold) {
          if (diff < 0 && self.currentMode === "browse") {
            self.switchMode("chat");
          } else if (diff > 0 && self.currentMode === "chat") {
            self.switchMode("browse");
          }
        }
      });
    }

    switchMode(mode) {
      if (this.currentMode === mode || this.isTransitioning) return;

      this.isTransitioning = true;
      const self = this;

      // Reset input visibility when switching modes
      if (this.isInputHidden) {
        this.showInputContainer();
      }

      // Update pills
      $(".mode-pill").removeClass("active");
      $(`.mode-pill[data-mode="${mode}"]`).addClass("active");

      // Update body class for CSS control
      if (mode === "chat") {
        $("body").addClass("mobile-chat-active");
        // Force show all chat elements with proper visibility
        $(".sffc-senna-conversation").css({
          display: "flex",
          visibility: "visible",
          opacity: "1",
        });
        $(".sffc-autocomplete-container").css({
          display: "block",
          visibility: "visible",
          opacity: "1",
        });
        $(".senna-messages").css({
          display: "block",
          visibility: "visible",
          opacity: "1",
        });
        // Hide browse elements
        $(".pe-filter-sidebar").hide();
        $("#mobile-fab").addClass("chat-mode");
        // Ensure input is visible when switching to chat
        this.showInputContainer();
      } else {
        $("body").removeClass("mobile-chat-active");
        // Show browse elements
        $(".pe-filter-sidebar").css({
          display: "block",
          visibility: "visible",
        });
        // Hide chat elements
        $(".sffc-senna-conversation").hide();
        $("#mobile-fab").removeClass("chat-mode");
      }

      setTimeout(() => {
        self.isTransitioning = false;
      }, 300);

      this.currentMode = mode;

      // Haptic feedback
      if (window.navigator && window.navigator.vibrate) {
        window.navigator.vibrate(10);
      }
    }

    switchToChat(query = "") {
      this.switchMode("chat");

      if (query) {
        setTimeout(() => {
          // Use the actual desktop MENA Careers input
          $("#senna-input").val(query).focus();
          // Trigger the send using the actual desktop send button
          $(".sffc-send-btn, #senna-send").first().click();
        }, 350);
      }
    }

    showSearchPanel() {
      $("#mobile-search-panel").addClass("visible");
      $("body").addClass("search-panel-open");

      // Focus the search input
      setTimeout(() => {
        $("#mobile-message-search-input").focus();
      }, 300);
    }

    hideSearchPanel() {
      $("#mobile-search-panel").removeClass("visible");
      $("body").removeClass("search-panel-open");
      $("#mobile-message-search-input").val("");
      $("#mobile-search-clear").hide();
      this.showSearchPlaceholder();
    }

    performSearch(query) {
      if (!query) {
        this.showSearchPlaceholder();
        this.lastSearchResults = [];
        return;
      }

      const $results = $("#mobile-search-results");
      $results.html('<div class="search-loading">Searching...</div>');

      // Use setTimeout to allow UI to update before search
      setTimeout(() => {
        // Search through messages
        const messages = this.searchMessages(query);

        // Store results for later use
        this.lastSearchResults = messages;

        if (messages.length === 0) {
          $results.html(`
                        <div class="search-no-results">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" opacity="0.3">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="8" y1="8" x2="16" y2="16"></line>
                                <line x1="16" y1="8" x2="8" y2="16"></line>
                            </svg>
                            <p>No results found for "${query}"</p>
                            <small style="opacity: 0.5; font-size: 12px;">Try different keywords or check your filters</small>
                        </div>
                    `);
        } else {
          let resultsHtml = `<div class="search-results-count" style="padding: 8px; color: #1e3a5f; font-size: 12px; opacity: 0.7;">${
            messages.length
          } result${messages.length > 1 ? "s" : ""} found</div>`;
          messages.forEach((msg) => {
            resultsHtml += this.createSearchResultItem(msg);
          });
          $results.html(resultsHtml);
        }
      }, 100);
    }

    searchMessages(query) {
      const results = [];
      const lowerQuery = query.toLowerCase();
      const filter = this.currentSearchFilter || "all";

      // Search through all possible message selectors
      const messageSelectors = [
        ".senna-message",
        ".user-message",
        ".sffc-message-senna",
        ".sffc-message-user",
        ".message-content",
        ".sffc-message-content",
      ];

      // Find all messages using multiple selectors
      $(messageSelectors.join(", ")).each(function () {
        const $msg = $(this);
        const text = $msg.text().toLowerCase();

        // Determine message type
        const isUser =
          $msg.hasClass("user") ||
          $msg.hasClass("user-message") ||
          $msg.hasClass("sffc-message-user") ||
          $msg.closest(".user-message").length > 0;

        const isSenna =
          $msg.hasClass("assistant") ||
          $msg.hasClass("senna") ||
          $msg.hasClass("senna-message") ||
          $msg.hasClass("sffc-message-senna") ||
          $msg.hasClass("bot-message") ||
          !isUser; // Default to MENA Careers if not user

        const hasJobs =
          $msg.find(
            ".job-card, .senna-job-card, .pe-job-card, .sffc-match-card"
          ).length > 0;

        // Apply filter
        if (filter === "user" && !isUser) return;
        if (filter === "senna" && !isSenna) return;
        if (filter === "jobs" && !hasJobs) return;

        // Check if message contains query
        if (text.includes(lowerQuery)) {
          // Avoid duplicates
          const existingResult = results.find((r) => r.element === $msg[0]);
          if (!existingResult) {
            results.push({
              id: $msg.attr("id") || "msg-" + Date.now() + "-" + Math.random(),
              text: $msg.text().substring(0, 150).trim(),
              type: isUser ? "user" : "senna",
              hasJobs: hasJobs,
              element: $msg[0],
            });
          }
        }
      });

      console.log(
        "Search results:",
        results.length,
        "messages found for query:",
        query
      );
      return results;
    }

    createSearchResultItem(msg) {
      const icon =
        msg.type === "user"
          ? `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>`
          : `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                </svg>`;

      const jobBadge = msg.hasJobs
        ? '<span class="result-job-badge">Jobs</span>'
        : "";

      return `
                <div class="search-result-item" data-message-id="${msg.id}">
                    <div class="result-icon ${msg.type}">${icon}</div>
                    <div class="result-content">
                        <div class="result-header">
                            <span class="result-type">${
                              msg.type === "user" ? "You" : "MENA Careers"
                            }</span>
                            ${jobBadge}
                        </div>
                        <div class="result-text">${msg.text}...</div>
                    </div>
                </div>
            `;
    }

    showSearchPlaceholder() {
      $("#mobile-search-results").html(`
                <div class="search-placeholder">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" opacity="0.3">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="m21 21-4.35-4.35"></path>
                    </svg>
                    <p>Start typing to search messages</p>
                </div>
            `);
    }

    scrollToMessage(messageId) {
      // Find message by ID or search for the element directly from search results
      let $message = $(`#${messageId}`);

      if ($message.length === 0) {
        // Try to find by data attribute
        $message = $(
          `.senna-message[data-id="${messageId}"], .user-message[data-id="${messageId}"], .message-content[data-id="${messageId}"]`
        );
      }

      // If still not found, search through our results cache
      if ($message.length === 0 && this.lastSearchResults) {
        const result = this.lastSearchResults.find((r) => r.id === messageId);
        if (result && result.element) {
          $message = $(result.element);
        }
      }

      if ($message.length > 0) {
        // First switch to chat mode if not already there
        if (this.currentMode !== "chat") {
          this.switchMode("chat");
          // Wait for mode switch animation
          setTimeout(() => {
            this.performScroll($message);
          }, 350);
        } else {
          this.performScroll($message);
        }
      } else {
        console.log("Message not found for ID:", messageId);
      }
    }

    performScroll($message) {
      // Highlight the message
      $message.addClass("highlighted");

      // Calculate scroll position
      const messageTop = $message.offset().top;
      const windowHeight = $(window).height();
      const scrollTo = messageTop - windowHeight / 3; // Position message in upper third

      // Smooth scroll to message
      $("html, body").animate(
        {
          scrollTop: scrollTo,
        },
        500,
        () => {
          // Flash effect after scroll
          $message.addClass("flash");
          setTimeout(() => {
            $message.removeClass("flash");
          }, 300);
        }
      );

      // Remove highlight after 3 seconds
      setTimeout(() => {
        $message.removeClass("highlighted");
      }, 3000);
    }

    showProfilePanel() {
      $("#mobile-profile-panel").addClass("visible");
      $("body").addClass("profile-panel-open");

      // Update user info if logged in
      if (window.sffc_ajax && window.sffc_ajax.user_name) {
        $(".profile-name").text(window.sffc_ajax.user_name);
        $(".profile-status").text("Member");
      }
    }

    hideProfilePanel() {
      $("#mobile-profile-panel").removeClass("visible");
      $("body").removeClass("profile-panel-open");
    }

    showBottomSheet() {
      $("#mobile-bottom-sheet").addClass("visible");
      $("body").addClass("sheet-open");
    }

    hideBottomSheet() {
      $("#mobile-bottom-sheet").removeClass("visible");
      $("body").removeClass("sheet-open");
    }

    handleQuickAction(action) {
      const queries = {
        "lesson-lbo": "I'd like to learn about Senior Debt Schedule",
        "lesson-dcf": "I'd like to learn about DCF Timeline Build",
        "lesson-comps": "I'd like to learn about Public Trading Comps",
        "lesson-carry": "I'd like to learn about Carry & Distribution Waterfall",
      };

      const query = queries[action];
      if (query) {
        this.switchToChat(query);
      }
    }

    updateTime() {
      const now = new Date();
      const hours = now.getHours();
      const minutes = now.getMinutes().toString().padStart(2, "0");
      $(".status-time").text(`${hours}:${minutes}`);
    }

    initScrollHandler() {
      const self = this;
      let scrollTimeout;

      // Reset the input state flag to match actual DOM state
      // Check if elements have scroll-hidden class to determine initial state
      const hasScrollHidden = $(".sffc-autocomplete-container").hasClass(
        "scroll-hidden"
      );
      this.isInputHidden = hasScrollHidden;

      // Ensure input starts visible
      if (hasScrollHidden) {
        this.isInputHidden = true; // Set flag before calling show
      }
      this.showInputContainer();

      // Show input when focused
      $(document).on(
        "focus.mobileInput",
        "#senna-input, .sffc-chat-input, .senna-input",
        function () {
          self.showInputContainer();
        }
      );

      // Listen for scroll on the actual chat container
      const $chatContainer = $(
        ".sffc-senna-conversation.mobile-senna-conversation, .senna-messages"
      );

      $chatContainer.on("scroll.mobileInput", function () {
        // Only apply in chat mode
        if (self.currentMode !== "chat") return;

        clearTimeout(scrollTimeout);

        const $container = $(this);
        const currentScrollTop = $container.scrollTop();
        const scrollDiff = currentScrollTop - self.lastScrollTop;

        // Ignore small scroll movements
        if (Math.abs(scrollDiff) < self.scrollThreshold) return;

        // Scrolling down - hide input and mode-pills for full-screen chat
        if (scrollDiff > 0 && currentScrollTop > 100) {
          self.hideInputContainer();
        }
        // Scrolling up - show input and mode-pills
        else if (scrollDiff < 0) {
          self.showInputContainer();
        }

        // Update last scroll position
        self.lastScrollTop = currentScrollTop;

        // Auto-show input when at bottom or top of chat
        scrollTimeout = setTimeout(() => {
          const containerHeight = $container.height();
          const scrollHeight = $container[0].scrollHeight;
          const scrollPosition = currentScrollTop + containerHeight;

          // If user is near bottom of chat OR at top, show input
          if (scrollPosition >= scrollHeight - 50 || currentScrollTop < 100) {
            self.showInputContainer();
          }
        }, 150);
      });

      // Also listen on window for fallback (in case chat isn't scrollable yet)
      $(window).on("scroll.mobileInputFallback", function () {
        // Only if chat container isn't scrollable
        if (self.currentMode !== "chat") return;
        const $chat = $(
          ".sffc-senna-conversation.mobile-senna-conversation, .senna-messages"
        );
        if ($chat.length && $chat[0].scrollHeight > $chat.height()) return; // Chat is scrollable, ignore window scroll

        const currentScrollTop = $(window).scrollTop();
        const scrollDiff = currentScrollTop - self.lastScrollTop;

        if (Math.abs(scrollDiff) < self.scrollThreshold) return;

        if (scrollDiff > 0 && currentScrollTop > 100) {
          self.hideInputContainer();
        } else if (scrollDiff < 0) {
          self.showInputContainer();
        }

        self.lastScrollTop = currentScrollTop;
      });
    }

    hideInputContainer() {
      if (this.isInputHidden) return;

      // Don't hide if input is focused
      const $input = $("#senna-input, .sffc-chat-input, .senna-input");
      if ($input.is(":focus")) return;

      // Hide all input-related elements including mode-pills
      const $container = $(".sffc-autocomplete-container");
      const $wrapper = $(".sffc-autocomplete-wrapper");
      const $inputGroup = $(".sffc-input-group");
      const $messages = $(".senna-messages");
      const $modePills = $(".mode-pills");

      // Add hiding class for animation
      $container.addClass("scroll-hidden");
      $wrapper.addClass("scroll-hidden");
      $inputGroup.addClass("scroll-hidden");
      $messages.addClass("input-hidden");
      $modePills.addClass("scroll-hidden");

      // Update body class for other elements to adjust
      $("body").addClass("mobile-input-hidden");

      this.isInputHidden = true;

      // Haptic feedback if available
      if (window.navigator && window.navigator.vibrate) {
        window.navigator.vibrate(5);
      }
    }

    showInputContainer() {
      // Skip the check - always ensure elements are shown
      // This prevents sync issues between flag and actual DOM state

      // Show all input-related elements
      const $container = $(".sffc-autocomplete-container");
      const $wrapper = $(".sffc-autocomplete-wrapper");
      const $inputGroup = $(".sffc-input-group");
      const $messages = $(".senna-messages");
      const $modePills = $(".mode-pills");

      // Remove hiding class
      $container.removeClass("scroll-hidden");
      $modePills.removeClass("scroll-hidden");
      $wrapper.removeClass("scroll-hidden");
      $inputGroup.removeClass("scroll-hidden");
      $messages.removeClass("input-hidden");

      // Update body class
      $("body").removeClass("mobile-input-hidden");

      this.isInputHidden = false;

      // Haptic feedback if available
      if (window.navigator && window.navigator.vibrate) {
        window.navigator.vibrate(5);
      }
    }
  }

  // Initialize on DOM ready
  $(document).ready(function () {
    // Check if we're on mobile using media query
    const checkMobileInit = () => {
      const isMobileView = window.matchMedia("(max-width: 768px)").matches;
      const forceMobile = window.location.search.includes("mobile=1");

      if (isMobileView || forceMobile) {
        if (!window.mobileInterfaceV2) {
          window.mobileInterfaceV2 = new MobileInterfaceV2();
        }
      } else if (window.mobileInterfaceV2) {
        // Clean up mobile interface on desktop
        $("body").removeClass("mobile-interface-v2 mobile-chat-active");
        $(".mobile-interface-container").remove();
        $("#mobile-profile-panel, #mobile-bottom-sheet, #mobile-fab").remove();
        $(".desktop-hidden-on-mobile")
          .removeClass("desktop-hidden-on-mobile")
          .removeAttr("style");
        $(document).off(".mobile-block");
        // Clean up scroll listeners
        $(".sffc-senna-conversation, .senna-messages").off(".mobileInput");
        $(window).off(".mobileInputFallback");
        $(document).off(".mobileInput");
        window.mobileInterfaceV2 = null;
      }
    };

    // Initial check
    checkMobileInit();

    // Check on resize
    let resizeTimeout;
    $(window).on("resize", function () {
      clearTimeout(resizeTimeout);
      resizeTimeout = setTimeout(checkMobileInit, 250);
    });
  });
})(window.jQuery || jQuery);
