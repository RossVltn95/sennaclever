/**
 * Mobile Interface Controller
 * Native app-like experience with smooth transitions
 */

(function ($) {
  "use strict";

  class MobileInterface {
    constructor() {
      this.currentMode = "browse"; // 'browse' or 'chat'
      this.touchStartX = 0;
      this.touchStartY = 0;
      this.isTransitioning = false;

      this.init();
    }

    init() {
      // Only initialize on mobile or when forced
      if (!this.shouldInitialize()) {
        // Mobile Interface: Not initializing - desktop mode
        return;
      }

      this.createMobileStructure();
      this.waitForCards();
      this.populateQuickActions();
      this.bindEvents();
      this.initializeGestures();
      this.setupFloatingElements();
      this.updateDynamicContent();
    }

    shouldInitialize() {
      // Check if we're on mobile based on CSS media query
      const isMobileView = window.matchMedia("(max-width: 768px)").matches;
      const forceMobile = window.location.search.includes("mobile=1");
      const hasMobileClass = $("body").hasClass("mobile-interface");

      return isMobileView || forceMobile || hasMobileClass;
    }

    waitForCards() {
      // Wait for PE filter cards to load
      const checkForCards = setInterval(() => {
        const $cards = $(
          ".pe-filter-sidebar .question-card, .pe-filter-sidebar .pe-job-card"
        );
        if ($cards.length > 0) {
          clearInterval(checkForCards);
          // PE filter cards loaded, updating mobile view
          this.updateMobileCards();
        }
      }, 500);

      // Stop checking after 10 seconds
      setTimeout(() => clearInterval(checkForCards), 10000);
    }

    updateMobileCards() {
      const $originalSidebar = $(".pe-filter-sidebar")
        .not(".mobile-cards")
        .first();
      if ($originalSidebar.length) {
        const $cards = $originalSidebar
          .find(".question-card, .pe-job-card")
          .clone();
        if ($cards.length) {
          $(".mobile-viewport .mobile-cards").html($cards);
          // Updated mobile cards

          // Re-bind events for new cards
          this.bindCardEvents();
        }
      }
    }

    bindCardEvents() {
      const self = this;
      $(".mobile-viewport .ask-senna-btn")
        .off("click")
        .on("click", function (e) {
          e.preventDefault();
          const cardText = $(this)
            .closest(".question-card")
            .find(".question-title")
            .text();
          self.switchToChat(cardText);
        });
    }

    createMobileStructure() {
      // Creating mobile structure

      // Check if already created
      if ($(".mobile-viewport").length) {
        // Mobile viewport already exists
        return;
      }

      // Get dynamic data from existing elements
      this.dynamicData = this.extractDynamicData();

      // Create mobile viewport wrapper
      const mobileWrapper = `
                <div class="mobile-viewport">
                    <!-- Status Bar -->
                    <div class="mobile-status-bar">
                        <div class="mobile-brand">
                            <span class="brand-s">S</span>
                            <span class="brand-dot">•</span>
                            <span class="brand-text">senna</span>
                        </div>
                        <div class="status-time">9:41</div>
                        <div class="status-icons">
                            <button class="mobile-profile-btn" id="mobile-profile-btn">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                </svg>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Mode Indicator Pills -->
                    <div class="mode-pills">
                        <div class="mode-pill active" data-mode="browse">
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
                        <div class="mode-pill" data-mode="chat">
                            <span class="pill-icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                </svg>
                            </span>
                            <span class="pill-text">Chat</span>
                        </div>
                    </div>
                    
                    <!-- Sliding Content Container -->
                    <div class="mobile-content-slider">
                        <div class="content-panel browse-panel active">
                            <!-- Cards content will be here -->
                            <div class="pe-filter-sidebar mobile-cards">
                                <!-- PE filter cards will be cloned here -->
                            </div>
                        </div>
                        
                        <div class="content-panel chat-panel">
                            <!-- Chat interface -->
                            <div class="sffc-senna-conversation mobile-chat">
                                <div class="senna-messages" id="mobile-senna-messages">
                                    <!-- Messages will appear here -->
                                </div>
                                <!-- Autocomplete input will be moved here -->
                            </div>
                        </div>
                    </div>
                    
                    <!-- Floating Action Button -->
                    <div class="floating-action-button" id="mobile-fab">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="white" stroke="none">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm5 11h-4v4h-2v-4H7v-2h4V7h2v4h4v2z"/>
                        </svg>
                    </div>
                    
                    <!-- Bottom Sheet (hidden by default) -->
                    <div class="bottom-sheet" id="mobile-bottom-sheet">
                        <div class="sheet-handle"></div>
                        <div class="sheet-content">
                        <h3>Lesson Shortcuts</h3>
                            <div class="quick-action-grid" id="mobile-quick-actions">
                                <!-- Dynamic quick actions will be inserted here -->
                            </div>
                        </div>
                    </div>
                    
                    <!-- Profile Panel (slides from right) -->
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
                            <div class="profile-menu">
                                <!-- Dynamic menu items from WordPress -->
                            </div>
                        </div>
                    </div>
                </div>
            `;

      // Add mobile interface
      // Check if should initialize mobile

      // Add body class for mobile
      $("body").addClass("mobile-interface");

      // Look for the actual WordPress container
      const $opportunitiesContainer = $(".sffc-opportunities-wrapper");
      // Look for container

      if ($opportunitiesContainer.length) {
        // Creating mobile wrapper

        // Insert the mobile viewport directly
        $opportunitiesContainer.prepend(mobileWrapper);

        // Clone existing elements into mobile panels
        const $originalSidebar = $(".pe-filter-sidebar")
          .not(".mobile-cards")
          .first();
        const $originalChat = $(".sffc-senna-conversation")
          .not(".mobile-chat")
          .first();

        // Copy cards from PE filter sidebar
        if ($originalSidebar.length) {
          const $cards = $originalSidebar
            .find(".question-card, .pe-job-card, .content-scroll > *")
            .clone();
          $(".mobile-viewport .mobile-cards").html($cards);
          // Copied cards to mobile view
        }

        // Copy chat elements
        if ($originalChat.length) {
          // Copy messages
          const $messages = $originalChat.find(".senna-messages").clone();
          if ($messages.length) {
            $("#mobile-senna-messages").replaceWith(
              $messages.attr("id", "mobile-senna-messages")
            );
          }

          // Copy autocomplete input
          const $autocomplete = $originalChat
            .find(".sffc-autocomplete-container")
            .clone();
          if ($autocomplete.length) {
            $(".mobile-viewport .mobile-chat").append($autocomplete);
          }
        }

        // Mobile structure created successfully
      } else {
        // Container not found
      }
    }

    bindEvents() {
      const self = this;

      // Mode pills
      $(".mode-pill").on("click", function () {
        const mode = $(this).data("mode");
        self.switchMode(mode);
      });

      // Profile button
      $("#mobile-profile-btn").on("click", function () {
        self.showProfilePanel();
      });

      // Profile panel close
      $(".profile-panel-close").on("click", function () {
        self.hideProfilePanel();
      });

      // Member button
      $("#mobile-member-btn").on("click", function () {
        const registrationUrl =
          window.sffc_ajax?.registration_url ||
          "https://joinsenna.com/memberships/";
        window.location.href = registrationUrl;
      });

      // Edit profile button
      $(".profile-edit-btn").on("click", function () {
        if (typeof openProfileBuilder === "function") {
          openProfileBuilder();
          self.hideProfilePanel();
        }
      });

      // Floating Action Button
      $("#mobile-fab").on("click", function () {
        self.showBottomSheet();
      });

      // Bottom sheet handle
      $(".sheet-handle").on("touchstart", function (e) {
        self.handleSheetDrag(e);
      });

      // Quick actions
      $(".quick-action").on("click", function () {
        const action = $(this).data("action");
        self.handleQuickAction(action);
        self.hideBottomSheet();
      });

      // Ask MENA Careers buttons in cards - use delegation for dynamic content
      $(document).on("click", ".mobile-viewport .ask-senna-btn", function (e) {
        e.preventDefault();
        const $card = $(this).closest(".question-card");
        const cardText =
          $card.find(".question-title").text() ||
          $card.find("h2").text() ||
          "Show me opportunities";
        self.switchToChat(cardText);
      });
    }

    initializeGestures() {
      const self = this;
      const $slider = $(".mobile-content-slider");

      if (!$slider.length) return;

      let startX = 0;
      let currentX = 0;
      let isDragging = false;

      $slider.on("touchstart", function (e) {
        if (self.isTransitioning) return;
        startX = e.touches[0].clientX;
        isDragging = true;
        $slider.addClass("dragging");
      });

      $slider.on("touchmove", function (e) {
        if (!isDragging || self.isTransitioning) return;

        currentX = e.touches[0].clientX;
        const diff = currentX - startX;

        // Only allow swiping in the correct direction
        if (
          (self.currentMode === "browse" && diff < 0) ||
          (self.currentMode === "chat" && diff > 0)
        ) {
          const translateX =
            self.currentMode === "browse"
              ? Math.max(diff, -100)
              : Math.min(diff, 100);

          $slider.css("transform", `translateX(${translateX}px)`);
        }
      });

      $slider.on("touchend", function (e) {
        if (!isDragging) return;
        isDragging = false;
        $slider.removeClass("dragging");

        const diff = currentX - startX;
        const threshold = 50;

        // Switch mode if swipe is strong enough
        if (Math.abs(diff) > threshold) {
          if (diff < 0 && self.currentMode === "browse") {
            self.switchMode("chat");
          } else if (diff > 0 && self.currentMode === "chat") {
            self.switchMode("browse");
          } else {
            // Snap back
            $slider.css("transform", "");
          }
        } else {
          // Snap back
          $slider.css("transform", "");
        }
      });
    }

    switchMode(mode) {
      if (this.currentMode === mode || this.isTransitioning) return;

      this.isTransitioning = true;
      const self = this;

      // Update pills
      $(".mode-pill").removeClass("active");
      $(`.mode-pill[data-mode="${mode}"]`).addClass("active");

      // Animate panels
      $(".content-panel").removeClass("active");

      if (mode === "chat") {
        $(".chat-panel").addClass("active");
        $(".browse-panel").addClass("slide-out-left");
        $(".chat-panel").addClass("slide-in-right");

        // Update FAB
        $("#mobile-fab").addClass("chat-mode");
      } else {
        $(".browse-panel").addClass("active");
        $(".chat-panel").addClass("slide-out-right");
        $(".browse-panel").addClass("slide-in-left");

        // Update FAB
        $("#mobile-fab").removeClass("chat-mode");
      }

      setTimeout(() => {
        $(".content-panel").removeClass(
          "slide-out-left slide-out-right slide-in-left slide-in-right"
        );
        self.isTransitioning = false;
      }, 300);

      this.currentMode = mode;

      // Haptic feedback (if available)
      if (window.navigator && window.navigator.vibrate) {
        window.navigator.vibrate(10);
      }
    }

    switchToChat(query = "") {
      this.switchMode("chat");

      if (query) {
        setTimeout(() => {
          // Find the input in mobile chat
          const $mobileInput = $(".mobile-viewport .mobile-chat #senna-input");
          if ($mobileInput.length) {
            $mobileInput.val(query).focus();
          } else {
            $("#senna-input").val(query).focus();
          }

          // Trigger search
          if (
            window.sennaConversational &&
            window.sennaConversational.handleUserInput
          ) {
            window.sennaConversational.handleUserInput();
          } else {
            // Fallback - trigger enter key
            const e = $.Event("keypress");
            e.which = 13;
            $("#senna-input").trigger(e);
          }
        }, 350);
      }
    }

    setupFloatingElements() {
      const self = this;
      let lastScrollTop = 0;

      $(window).on("scroll", function () {
        const scrollTop = $(this).scrollTop();

        if (scrollTop > lastScrollTop && scrollTop > 100) {
          // Scrolling down - hide FAB
          $("#mobile-fab").addClass("hidden");
        } else {
          // Scrolling up - show FAB
          $("#mobile-fab").removeClass("hidden");
        }

        lastScrollTop = scrollTop;
      });
    }

    showBottomSheet() {
      $("#mobile-bottom-sheet").addClass("visible");
      $("body").addClass("sheet-open");
    }

    hideBottomSheet() {
      $("#mobile-bottom-sheet").removeClass("visible");
      $("body").removeClass("sheet-open");
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

    handleSheetDrag(e) {
      // Implement sheet dragging logic
      const $sheet = $("#mobile-bottom-sheet");
      let startY = e.touches[0].clientY;
      let currentHeight = $sheet.height();

      const onMove = (e) => {
        const currentY = e.touches[0].clientY;
        const diff = startY - currentY;
        const newHeight = Math.max(
          200,
          Math.min(window.innerHeight * 0.8, currentHeight + diff)
        );
        $sheet.height(newHeight);
      };

      const onEnd = () => {
        $(document).off("touchmove", onMove);
        $(document).off("touchend", onEnd);

        // Snap to positions
        const height = $sheet.height();
        if (height < 250) {
          this.hideBottomSheet();
        } else if (height < window.innerHeight * 0.5) {
          $sheet.animate({ height: "300px" }, 200);
        } else {
          $sheet.animate({ height: "70vh" }, 200);
        }
      };

      $(document).on("touchmove", onMove);
      $(document).on("touchend", onEnd);
    }

    handleQuickAction(action) {
      // Use dynamic query mapping from extracted data
      const query = this.dynamicData.quickActions[action];

      if (query) {
        this.switchToChat(query);
      }
    }

    extractDynamicData() {
      const data = {
        quickActions: {},
        filters: [],
        jobCount: 0,
      };

      // Extract from existing PE filter cards
      $(".pe-quick-filter-item").each(function () {
        const label = $(this).find(".pe-filter-label").text();
        const icon = $(this).find(".pe-quick-icon-inner").text();
        data.filters.push({ label, icon });
      });

      // Extract from question cards
      $(".question-card").each(function () {
        const title = $(this).find(".question-title").text();
        const category = $(this).find(".question-category").text();
        if (category && title) {
          const key = category.toLowerCase().replace(/\s+/g, "-");
          data.quickActions[key] = title;
        }
        data.jobCount++;
      });

      data.quickActions = {
        "lesson-lbo": "I'd like to learn about Senior Debt Schedule",
        "lesson-dcf": "I'd like to learn about DCF Timeline Build",
        "lesson-comps": "I'd like to learn about Public Trading Comps",
        "lesson-carry": "I'd like to learn about Carry & Distribution Waterfall",
      };

      return data;
    }

    populateQuickActions() {
      const $grid = $("#mobile-quick-actions");
      if (!$grid.length) return;

      const icons = {
        "lesson-lbo":
          '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h18"></path><path d="M3 6h12"></path><path d="M3 18h6"></path></svg>',
        "lesson-dcf":
          '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19h16"></path><path d="M4 5h16"></path><path d="M8 9v6"></path><path d="M12 7v10"></path><path d="M16 11v4"></path></svg>',
        "lesson-comps":
          '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="16" rx="2"></rect><path d="M3 10h18"></path><path d="M8 14h.01"></path><path d="M12 14h.01"></path><path d="M16 14h.01"></path></svg>',
        "lesson-carry":
          '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 18h16"></path><path d="M6 6h12v8H6z"></path><path d="M10 10h4"></path></svg>',
      };

      const labels = {
        "lesson-lbo": "LBO Modelling",
        "lesson-dcf": "DCF Drill",
        "lesson-comps": "Comps Analysis",
        "lesson-carry": "Carry & Waterfall",
      };

      let html = "";
      Object.entries(this.dynamicData.quickActions).forEach(
        ([key, query], index) => {
          if (index < 4) {
            // Limit to 4 quick actions
            html += `
                        <div class="quick-action" data-action="${key}">
                            <div class="action-icon">${
                              icons[key] || icons["lesson-lbo"]
                            }</div>
                            <div class="action-label">${
                              labels[key] || "Lesson"
                            }</div>
                        </div>
                    `;
          }
        }
      );

      $grid.html(html);
    }

    isMobile() {
      // Check for mobile-interface class for testing
      if ($("body").hasClass("mobile-interface")) {
        return true;
      }

      return (
        window.innerWidth <= 768 ||
        /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(
          navigator.userAgent
        )
      );
    }

    updateDynamicContent() {
      // Update status bar time
      setInterval(() => {
        const now = new Date();
        const hours = now.getHours();
        const minutes = now.getMinutes().toString().padStart(2, "0");
        $(".status-time").text(`${hours}:${minutes}`);
      }, 60000);

      // Update job count badge if available
      const jobCount = $(".pe-job-card, .question-card").length;
      if (jobCount > 0) {
        $('.mode-pill[data-mode="browse"] .pill-text').html(
          `Browse <span class="pill-badge">${jobCount}</span>`
        );
      }

      // Sync with existing filter system
      $(document).on("promptFilterApplied", (e) => {
        const filters = e.detail;
        // Mobile: Filters applied

        // Update UI to reflect active filters
        if (filters.location) {
          $(".mobile-status-bar").append(
            '<span class="active-filter-badge">' + filters.location + "</span>"
          );
        }
      });
    }
  }

  // Initialize on DOM ready
  $(document).ready(function () {
    // Check if we're on mobile using media query
    const checkMobileInit = () => {
      const isMobileView = window.matchMedia("(max-width: 768px)").matches;
      const forceMobile = window.location.search.includes("mobile=1");

      if (isMobileView || forceMobile) {
        if (!window.mobileInterface) {
          // Mobile conditions met

          window.mobileInterface = new MobileInterface();
          // Mobile Interface initialized
        }
      } else if (window.mobileInterface) {
        // Desktop mode - clean up mobile interface if it exists
        // Switching to desktop mode
        $(".mobile-viewport").hide();
        $("body").removeClass("mobile-interface");
      }
    };

    // Initial check
    checkMobileInit();

    // Also check on resize for responsive behavior
    let resizeTimeout;
    $(window).on("resize", function () {
      clearTimeout(resizeTimeout);
      resizeTimeout = setTimeout(checkMobileInit, 250);
    });
  });

  // Handle orientation change
  $(window).on("orientationchange", function () {
    // Resize handler above will take care of this
    // Orientation changed
  });
})(jQuery);
